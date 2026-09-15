<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Orquesta la sincronización de la BD local (Catalogo_API_Bridge_Store)
 * contra la API, vía WP-Cron:
 *  - cada 2 minutos: delta_sync() — solo lo que cambió desde el último
 *    cursor guardado. Esto es lo más cercano a "inmediato" que se puede
 *    lograr sin que el plugin registre webhooks anónimos con la API.
 *  - una vez al día: full_sync() — recorre TODO el catálogo (usando el
 *    mismo endpoint de "changes" con un cursor muy antiguo) y elimina de la
 *    tabla local cualquier producto que ya no exista en la API. Es la red
 *    de seguridad para deltas perdidos y para hard-deletes, que un cursor
 *    de updatedAt no puede representar porque el documento ya no existe.
 *
 * Ninguna de las dos borra datos si la API falla — el estado previo se
 * queda tal cual, solo se registra el error para mostrarlo en Ajustes.
 */
class Catalogo_API_Bridge_Sync {

	const CRON_DELTA_HOOK = 'catalogo_api_delta_sync';
	const CRON_FULL_HOOK  = 'catalogo_api_full_sync';
	const OPT_CURSOR      = 'catalogo_api_sync_cursor';
	const OPT_STATUS      = 'catalogo_api_sync_status'; // ['ok'=>bool,'at'=>ISO,'message'=>string]
	const PAGE_SIZE       = 200; // debe coincidir con CHANGES_LIMIT del backend.
	const EPOCH           = '1970-01-01T00:00:00.000Z';

	public static function init() {
		add_filter( 'cron_schedules', array( __CLASS__, 'add_cron_interval' ) );
		add_action( self::CRON_DELTA_HOOK, array( __CLASS__, 'delta_sync' ) );
		add_action( self::CRON_FULL_HOOK, array( __CLASS__, 'full_sync' ) );
	}

	public static function add_cron_interval( $schedules ) {
		$schedules['catalogo_api_two_min'] = array(
			'interval' => 120,
			'display'  => 'Cada 2 minutos (Catálogo API)',
		);
		return $schedules;
	}

	public static function activate() {
		Catalogo_API_Bridge_Store::install();
		if ( ! wp_next_scheduled( self::CRON_DELTA_HOOK ) ) {
			wp_schedule_event( time() + 120, 'catalogo_api_two_min', self::CRON_DELTA_HOOK );
		}
		if ( ! wp_next_scheduled( self::CRON_FULL_HOOK ) ) {
			wp_schedule_event( time() + 300, 'daily', self::CRON_FULL_HOOK );
		}
		// Primer arranque: intenta poblar la tabla de una vez (si la API
		// responde) para no depender del primer tick de cron en un sitio de
		// bajo tráfico. Si falla, no pasa nada — el bootstrap en vivo del
		// shortcode/detalle cubre mientras tanto, y el cron lo reintentará.
		self::full_sync();
	}

	public static function deactivate() {
		wp_clear_scheduled_hook( self::CRON_DELTA_HOOK );
		wp_clear_scheduled_hook( self::CRON_FULL_HOOK );
	}

	/** Sincronización incremental: solo lo que cambió desde el último cursor guardado. */
	public static function delta_sync() {
		$cursor = get_option( self::OPT_CURSOR, self::EPOCH );
		self::run_from_cursor( $cursor, false );
	}

	/** Recorre TODO el catálogo (cursor = epoch) y poda lo que ya no exista. */
	public static function full_sync() {
		// El árbol de categorías (usado para agrupar automáticamente
		// categorías crudas bajo su raíz real) se cachea aparte y por más
		// tiempo — se refresca aquí también para que un "Sincronizar ahora"
		// manual no se quede con una jerarquía vieja hasta que expire sola.
		delete_transient( 'catalogo_api_categories_tree' );

		$keep_ids = self::run_from_cursor( self::EPOCH, true );
		if ( null !== $keep_ids ) {
			Catalogo_API_Bridge_Store::prune_missing( $keep_ids );
			self::sync_catalogo_membership();
		}
	}

	/**
	 * Pagina Client::get_changes() desde $since hasta agotar los resultados,
	 * haciendo upsert de cada tanda. Devuelve el arreglo de IDs vistos (para
	 * full_sync, que los usa para podar) o null si algo falló.
	 */
	private static function run_from_cursor( $since, $collect_ids ) {
		$seen_ids = array();
		$cursor   = $since;
		$guard    = 0; // corta el loop si algo se porta raro (no debería pasar).

		while ( $guard < 100 ) {
			$guard++;
			$result = Catalogo_API_Bridge_Client::get_changes( $cursor );

			if ( empty( $result['ok'] ) ) {
				self::set_status( false, 'No se pudo conectar con el catálogo en la última sincronización.' );
				return null;
			}

			$products = $result['data'];
			Catalogo_API_Bridge_Store::upsert_many( $products );

			if ( $collect_ids ) {
				foreach ( $products as $p ) {
					if ( ! empty( $p['_id'] ) ) {
						$seen_ids[] = $p['_id'];
					}
				}
			}

			if ( ! empty( $result['server_time'] ) ) {
				$cursor = $result['server_time'];
			}

			if ( count( $products ) < self::PAGE_SIZE ) {
				break; // ya no hay más.
			}
		}

		update_option( self::OPT_CURSOR, $cursor, false );
		self::set_status( true, '' );

		return $collect_ids ? $seen_ids : array();
	}

	/** Refresca la tabla de membresías de catálogos curados (catalogo=""). Solo en full_sync — la curaduría cambia poco. */
	private static function sync_catalogo_membership() {
		$catalogos = Catalogo_API_Bridge_Client::get_catalogos();
		if ( empty( $catalogos['ok'] ) || empty( $catalogos['data'] ) ) {
			return;
		}
		foreach ( $catalogos['data'] as $catalogo ) {
			if ( empty( $catalogo['slug'] ) ) {
				continue;
			}
			$slug         = $catalogo['slug'];
			$product_ids  = array();
			$page         = 1;
			$guard        = 0;
			do {
				$guard++;
				$result = Catalogo_API_Bridge_Client::get_products( array( 'catalogo' => $slug, 'limit' => 100, 'page' => $page ) );
				if ( empty( $result['ok'] ) ) {
					break;
				}
				foreach ( $result['data'] as $p ) {
					if ( ! empty( $p['_id'] ) ) {
						$product_ids[] = $p['_id'];
					}
				}
				$has_more = ! empty( $result['pagination']['totalPages'] ) && $page < $result['pagination']['totalPages'];
				$page++;
			} while ( $has_more && $guard < 50 );

			Catalogo_API_Bridge_Store::replace_membership( $slug, $product_ids );
		}
	}

	private static function set_status( $ok, $message ) {
		update_option( self::OPT_STATUS, array( 'ok' => $ok, 'at' => current_time( 'mysql' ), 'message' => $message ), false );
	}

	public static function get_status() {
		return get_option( self::OPT_STATUS, null );
	}
}
