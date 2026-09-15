<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Nivel 5 de personalización: sobreescritura de plantillas desde el tema del
 * sitio — mismo patrón que usa WooCommerce. El plugin sigue haciendo TODO el
 * trabajo de datos (conexión a la API, caché); esto solo decide qué archivo
 * PHP dibuja el HTML final.
 *
 * Un desarrollador que quiera un diseño completamente propio copia
 * templates/detail.php (o grid.php, product-card.php, error.php) de este
 * plugin a:
 *
 *   wp-content/themes/SU-TEMA/catalogo-api-bridge/detail.php
 *
 * y el plugin usa esa versión en vez de la suya — sin tocar el plugin, sin
 * arriesgar la conexión con la API. Si el archivo no existe en el tema, se
 * usa la plantilla por defecto del plugin, igual que siempre.
 */
class Catalogo_API_Bridge_Templates {

	const SUBDIR = 'catalogo-api-bridge';

	/**
	 * Devuelve la ruta absoluta a usar para $template_name: la del tema del
	 * sitio si existe (hijo antes que padre, vía locate_template de
	 * WordPress), o si no, la del propio plugin.
	 */
	public static function locate( $template_name ) {
		$from_theme = locate_template( array( self::SUBDIR . '/' . $template_name ) );
		if ( $from_theme ) {
			return $from_theme;
		}
		return CATALOGO_API_BRIDGE_DIR . 'templates/' . $template_name;
	}
}
