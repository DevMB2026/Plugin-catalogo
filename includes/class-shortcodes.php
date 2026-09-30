<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * [catalogo_grid marca="" categoria="" catalogo="" limite="12" estilo="cuadricula"]
 *
 * "catalogo" referencia el slug de un Catalog curado en el backend (ver
 * GET /catalogos) — p.ej. "catalogo-prezenza". Es independiente de "marca":
 * un catálogo puede incluir productos de varias marcas.
 *
 * "estilo" es puramente visual (no toca qué productos se muestran):
 * cuadricula (por defecto), lista o compacta. No afecta la llamada a la
 * API ni su caché — solo cambia clases CSS en el render.
 */
class Catalogo_API_Bridge_Shortcodes {

	const ESTILOS_VALIDOS = array( 'cuadricula', 'lista', 'compacta' );

	public static function init() {
		add_shortcode( 'catalogo_grid', array( __CLASS__, 'render_grid' ) );
		add_shortcode( 'catalogo_hero', array( __CLASS__, 'render_hero' ) );
	}

	public static function render_grid( $atts ) {
		$atts = shortcode_atts(
			array(
				'marca'     => '',
				'categoria' => '',
				'categoria_inicial' => '',
				'orden'     => '',
				'catalogo'  => '',
				'limite'    => 12,
				'estilo'    => 'cuadricula',
			),
			$atts,
			'catalogo_grid'
		);

		// Categoría fija por el dueño de la página (atributo del shortcode). Si
		// no fijó ninguna, el visitante puede filtrar por categoría desde los
		// botones/aside sin salir de la página (?cab_categoria=slug) — un
		// simple recargado con GET, sin JS, igual de espíritu que el resto del
		// plugin (server-rendered).
		//
		// `categoria_inicial` (ej. "chamarras"): la que se ve AL ENTRAR, sin
		// fijarla — el visitante puede cambiar a otra, y "Quitar filtro" lleva a
		// ?cab_categoria=todas para ver todo el catálogo (sin ese valor especial,
		// quitar el filtro volvería a caer en la categoría inicial).
		$categoria_fija    = sanitize_title( $atts['categoria'] );
		$categoria_inicial = sanitize_title( $atts['categoria_inicial'] );
		$categoria_actual  = $categoria_fija;
		if ( empty( $categoria_fija ) ) {
			$pedida = isset( $_GET['cab_categoria'] ) ? sanitize_title( wp_unslash( $_GET['cab_categoria'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			if ( 'todas' === $pedida ) {
				$categoria_actual = '';
			} elseif ( '' !== $pedida ) {
				$categoria_actual = $pedida;
			} else {
				$categoria_actual = $categoria_inicial;
			}
		}

		$args = array(
			'brand'    => sanitize_title( $atts['marca'] ),
			'category' => $categoria_actual,
			'catalogo' => sanitize_title( $atts['catalogo'] ),
			'limit'    => max( 1, min( 100, (int) $atts['limite'] ) ),
			// Orden manual (ej. orden="shell, atractive, hydro, reaction"): esos
			// productos primero y en ese orden, el resto después. Una sola lista
			// sirve para todas las categorías — cada término solo coincide con
			// los productos que lo llevan en su slug.
			'orden'    => Catalogo_API_Bridge_Store::parse_orden( $atts['orden'] ),
		);

		// Valor desconocido (typo, etc.) cae a "cuadricula" en vez de romper
		// el render o dejar pasar una clase CSS arbitraria.
		$estilo = in_array( $atts['estilo'], self::ESTILOS_VALIDOS, true ) ? $atts['estilo'] : 'cuadricula';

		$catalogo_vacio = Catalogo_API_Bridge_Store::is_empty();

		$result = $catalogo_vacio
			? Catalogo_API_Bridge_Cache::remember( Catalogo_API_Bridge_Cache::key_for_products( $args ), function () use ( $args ) {
				return Catalogo_API_Bridge_Client::get_products( $args );
			} )
			: Catalogo_API_Bridge_Store::query( $args );

		// Categorías (botones + aside) y destacados: solo tienen sentido sobre
		// la BD local ya sincronizada — si el sitio apenas se activó y todavía
		// está vacía, se omiten en vez de mostrar bloques huecos.
		$categorias            = $catalogo_vacio ? array() : Catalogo_API_Bridge_Store::get_categories();
		$destacados            = $catalogo_vacio ? array() : Catalogo_API_Bridge_Store::get_featured( 3 );
		$categoria_fija_activa = $categoria_fija;

		ob_start();
		include Catalogo_API_Bridge_Templates::locate( 'grid.php' );
		return ob_get_clean();
	}

	/**
	 * [catalogo_hero titulo="" subtitulo="" boton="" enlace="" limite="4"]
	 *
	 * Hero animado con los productos destacados (mismos que alimentan el
	 * bloque "Productos destacados" del aside): fondo que va cambiando de
	 * color según el producto que esté al frente, imagen central que crece
	 * desde una miniatura y va rotando. Solo tiene sentido sobre la BD local
	 * ya sincronizada — si el catálogo está vacío no hay nada que mostrar.
	 */
	public static function render_hero( $atts ) {
		$atts = shortcode_atts(
			array(
				'titulo'    => 'Prendas con actitud propia, sin esforzarte de más.',
				'subtitulo' => 'Piezas pensadas para el día a día, con materiales que se sienten bien y detalles que se notan de cerca.',
				'boton'     => 'Ver catálogo completo',
				'enlace'    => '',
				'limite'    => 4,
			),
			$atts,
			'catalogo_hero'
		);

		if ( Catalogo_API_Bridge_Store::is_empty() ) {
			return '';
		}

		$hero_productos    = Catalogo_API_Bridge_Store::get_featured( max( 1, min( 8, (int) $atts['limite'] ) ) );
		if ( ! $hero_productos ) {
			return '';
		}

		$hero_titulo       = $atts['titulo'];
		$hero_subtitulo    = $atts['subtitulo'];
		$hero_texto_boton  = $atts['boton'];
		$hero_enlace_boton = $atts['enlace'] ? $atts['enlace'] : Catalogo_API_Bridge_Rewrite::catalog_url();
		$hero_categorias   = array_slice( Catalogo_API_Bridge_Store::get_categories(), 0, 4 );

		ob_start();
		include Catalogo_API_Bridge_Templates::locate( 'hero.php' );
		return ob_get_clean();
	}
}
