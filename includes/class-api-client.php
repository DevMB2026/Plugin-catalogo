<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Cliente HTTP hacia la API central del catálogo (server-to-server, vía
 * wp_remote_get). En operación normal el front-end NO pasa por aquí: lee
 * de la BD local que mantiene Catalogo_API_Bridge_Store (alimentada por
 * Catalogo_API_Bridge_Sync vía cron). Este cliente solo se usa para la
 * sincronización de fondo y como fallback (con el caché corto de
 * Catalogo_API_Bridge_Cache) cuando esa BD local todavía está vacía.
 *
 * Solo usa parámetros CONFIRMADOS que existen hoy en el backend: brand,
 * category, catalogo, q, page, limit. (El backend también soporta "sort",
 * pero se deja fuera de esta versión a propósito, según lo pedido.)
 */
class Catalogo_API_Bridge_Client {

	// Render free tier duerme el servicio tras inactividad y puede tardar
	// 30-50s en despertar en el primer request; 8s lo hacía fallar seguido.
	// Seguro de subir: en operación normal el front-end lee de la BD local
	// (Catalogo_API_Bridge_Store::query / get_by_slug, ver class-store.php),
	// este timeout solo aplica a la sincronización de fondo (cron) y al
	// fallback cacheado cuando la BD local todavía está vacía.
	const TIMEOUT = 45;

	/**
	 * URL base configurada en Ajustes → Catálogo API, sin diagonal final.
	 */
	private static function base_url() {
		$url = get_option( 'catalogo_api_base_url', 'https://api-catalogo-productos.onrender.com/api/v1' );
		return untrailingslashit( $url );
	}

	/**
	 * GET /products — listado. $args admite: brand, category, catalogo, q, page, limit.
	 */
	public static function get_products( $args = array() ) {
		$allowed = array( 'brand', 'category', 'catalogo', 'q', 'page', 'limit' );
		$query   = array();

		foreach ( $allowed as $key ) {
			if ( isset( $args[ $key ] ) && $args[ $key ] !== '' ) {
				$query[ $key ] = $args[ $key ];
			}
		}

		$url = self::base_url() . '/products';
		if ( ! empty( $query ) ) {
			$url .= '?' . http_build_query( $query );
		}

		return self::request( $url );
	}

	/**
	 * GET /products/slug/:slug — detalle por slug.
	 */
	public static function get_product_by_slug( $slug ) {
		$url = self::base_url() . '/products/slug/' . rawurlencode( $slug );
		return self::request( $url );
	}

	/**
	 * GET /products/changes?since=... — usado por Catalogo_API_Bridge_Sync
	 * para poblar/actualizar la base de datos local. A diferencia de
	 * get_products(), cada producto viene con el mismo detalle completo que
	 * get_product_by_slug() (variantes, opciones, tabla de tallas, etc.),
	 * porque esta es la ÚNICA fuente que alimenta la BD local — tiene que
	 * traer todo lo que hace falta para dibujar tanto el grid como la ficha.
	 *
	 * Devuelve además 'server_time': el cursor a guardar y usar como `since`
	 * en la siguiente llamada. Si `count($data) === Sync::PAGE_SIZE`, todavía
	 * puede haber más — Sync sigue pidiendo con ese mismo `server_time` hasta
	 * recibir menos que el tope.
	 */
	public static function get_changes( $since ) {
		$url = self::base_url() . '/products/changes?since=' . rawurlencode( $since );

		$response = wp_remote_get(
			$url,
			array(
				'timeout' => self::TIMEOUT,
				'headers' => array( 'Accept' => 'application/json' ),
			)
		);

		if ( is_wp_error( $response ) ) {
			return array( 'ok' => false, 'status' => 0, 'data' => null, 'server_time' => null, 'message' => 'No se pudo conectar con el catálogo.' );
		}

		$status  = (int) wp_remote_retrieve_response_code( $response );
		$decoded = json_decode( wp_remote_retrieve_body( $response ), true );

		if ( $status < 200 || $status >= 300 || ! is_array( $decoded ) || empty( $decoded['success'] ) ) {
			return array( 'ok' => false, 'status' => $status, 'data' => null, 'server_time' => null, 'message' => 'El catálogo no está disponible en este momento.' );
		}

		return array(
			'ok'          => true,
			'status'      => $status,
			'data'        => isset( $decoded['data'] ) && is_array( $decoded['data'] ) ? $decoded['data'] : array(),
			'server_time' => isset( $decoded['serverTime'] ) ? $decoded['serverTime'] : null,
			'message'     => '',
		);
	}

	/**
	 * GET /catalogos — lista pública de catálogos curados (marca principal +
	 * productos adicionales). Se usa solo para refrescar la tabla local de
	 * membresías (qué producto pertenece a qué catálogo), consumida por el
	 * atributo `catalogo=""` del shortcode.
	 */
	public static function get_catalogos() {
		$url = self::base_url() . '/catalogos';
		return self::request( $url );
	}

	/**
	 * GET /categories — árbol completo de categorías (con "parent"). Lo usa
	 * únicamente Catalogo_API_Bridge_Store::categories_tree() para agrupar
	 * automáticamente categorías crudas bajo su categoría raíz real, según
	 * la jerarquía que ya maneja la API central — en vez de que cada
	 * categoría hija (ej. "fleece", hija de "Sudaderas" en la API) aparezca
	 * como si fuera su propia categoría suelta en el catálogo.
	 */
	public static function get_categories() {
		$url = self::base_url() . '/categories';
		return self::request( $url );
	}

	/**
	 * Hace la petición y normaliza la respuesta o el error a un solo formato:
	 * ['ok'=>bool, 'status'=>int, 'data'=>mixed|null, 'pagination'=>array|null, 'message'=>string]
	 *
	 * Nunca deja pasar un error fatal ni expone detalle técnico al visitante.
	 */
	private static function request( $url ) {
		$response = wp_remote_get(
			$url,
			array(
				'timeout' => self::TIMEOUT,
				'headers' => array( 'Accept' => 'application/json' ),
			)
		);

		if ( is_wp_error( $response ) ) {
			return array(
				'ok'         => false,
				'status'     => 0,
				'data'       => null,
				'pagination' => null,
				'message'    => 'No se pudo conectar con el catálogo. Intenta más tarde.',
			);
		}

		$status  = (int) wp_remote_retrieve_response_code( $response );
		$body    = wp_remote_retrieve_body( $response );
		$decoded = json_decode( $body, true );

		if ( 404 === $status ) {
			return array(
				'ok'         => false,
				'status'     => 404,
				'data'       => null,
				'pagination' => null,
				'message'    => 'Producto no encontrado.',
			);
		}

		if ( $status < 200 || $status >= 300 || ! is_array( $decoded ) || empty( $decoded['success'] ) ) {
			return array(
				'ok'         => false,
				'status'     => $status,
				'data'       => null,
				'pagination' => null,
				'message'    => 'El catálogo no está disponible en este momento. Intenta más tarde.',
			);
		}

		return array(
			'ok'         => true,
			'status'     => $status,
			'data'       => isset( $decoded['data'] ) ? $decoded['data'] : null,
			'pagination' => isset( $decoded['pagination'] ) ? $decoded['pagination'] : null,
			'message'    => '',
		);
	}
}
