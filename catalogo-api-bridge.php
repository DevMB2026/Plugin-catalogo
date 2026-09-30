<?php
/**
 * Plugin Name:       Catálogo API Bridge
 * Description:       Consume el catálogo central de productos vía API (sin precios). Guarda una base de datos local que se sincroniza sola cada ~2 minutos, para no depender de una llamada en vivo por visita.
 * Version:           1.19.0
 * Requires at least: 6.0
 * Requires PHP:      7.4
 * Author:            Prezenza
 * Text Domain:       catalogo-api-bridge
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Acceso directo no permitido.
}

define( 'CATALOGO_API_BRIDGE_VERSION', '1.19.0' );
define( 'CATALOGO_API_BRIDGE_DIR', plugin_dir_path( __FILE__ ) );
define( 'CATALOGO_API_BRIDGE_URL', plugin_dir_url( __FILE__ ) );

require_once CATALOGO_API_BRIDGE_DIR . 'includes/class-api-client.php';
require_once CATALOGO_API_BRIDGE_DIR . 'includes/class-cache.php';
require_once CATALOGO_API_BRIDGE_DIR . 'includes/class-store.php';
require_once CATALOGO_API_BRIDGE_DIR . 'includes/class-sync.php';
require_once CATALOGO_API_BRIDGE_DIR . 'includes/class-templates.php';
require_once CATALOGO_API_BRIDGE_DIR . 'includes/class-settings.php';
require_once CATALOGO_API_BRIDGE_DIR . 'includes/class-rewrite.php';
require_once CATALOGO_API_BRIDGE_DIR . 'includes/class-shortcodes.php';
require_once CATALOGO_API_BRIDGE_DIR . 'includes/class-colors.php';

// Actualizaciones automáticas: el plugin no está en WordPress.org (es de uso
// interno/de negocio), así que usa la librería Plugin Update Checker para
// avisar de versiones nuevas leyendo los tags del repo de GitHub — mismo
// "Hay una actualización disponible" que un plugin normal, sin depender del
// repositorio oficial. Para publicar una versión nueva: sube el número de
// Version de arriba, haz commit, y crea+empuja un tag "vX.Y.Z" en GitHub.
require_once CATALOGO_API_BRIDGE_DIR . 'includes/plugin-update-checker/plugin-update-checker.php';

use YahnisElsts\PluginUpdateChecker\v5\PucFactory;

PucFactory::buildUpdateChecker(
	'https://github.com/DevMB2026/Plugin-catalogo/',
	__FILE__,
	'catalogo-api-bridge'
);

register_activation_hook(
	__FILE__,
	function () {
		Catalogo_API_Bridge_Rewrite::activate();
		Catalogo_API_Bridge_Sync::activate();
	}
);
register_deactivation_hook(
	__FILE__,
	function () {
		Catalogo_API_Bridge_Rewrite::deactivate();
		Catalogo_API_Bridge_Sync::deactivate();
	}
);

add_action(
	'plugins_loaded',
	function () {
		Catalogo_API_Bridge_Settings::init();
		Catalogo_API_Bridge_Rewrite::init();
		Catalogo_API_Bridge_Shortcodes::init();
		Catalogo_API_Bridge_Sync::init();
	}
);

add_action(
	'wp_enqueue_scripts',
	function () {
		wp_enqueue_style(
			'catalogo-api-bridge',
			CATALOGO_API_BRIDGE_URL . 'assets/css/catalogo.css',
			array(),
			CATALOGO_API_BRIDGE_VERSION
		);

		// CSS personalizado desde Ajustes → Catálogo API. wp_add_inline_style()
		// lo agrega DESPUÉS del stylesheet base en el <head> (mismo nivel de
		// especificidad, pero por orden en el documento gana el que viene
		// después) — así el usuario puede sobreescribir cualquier regla del
		// plugin sin usar !important ni tocar archivos.
		$custom_css = get_option( 'catalogo_api_custom_css', '' );
		if ( ! empty( $custom_css ) ) {
			wp_add_inline_style( 'catalogo-api-bridge', $custom_css );
		}

		// El JS de interactividad (selección de género/color/talla) solo hace
		// falta en la ficha de producto — no tiene sentido cargarlo en el grid.
		if ( get_query_var( Catalogo_API_Bridge_Rewrite::QUERY_VAR ) ) {
			wp_enqueue_script(
				'catalogo-api-bridge-detail',
				CATALOGO_API_BRIDGE_URL . 'assets/js/detail.js',
				array(),
				CATALOGO_API_BRIDGE_VERSION,
				true
			);
		}

		// El hero animado ([catalogo_hero]) trae su propio CSS/JS aparte —
		// pesan por la animación (crossfade de color, transición de imagen)
		// y no tiene caso cargarlos en páginas que no usan ese shortcode.
		global $post;
		if ( $post instanceof WP_Post && has_shortcode( $post->post_content, 'catalogo_hero' ) ) {
			wp_enqueue_style(
				'catalogo-api-bridge-hero',
				CATALOGO_API_BRIDGE_URL . 'assets/css/catalogo-hero.css',
				array( 'catalogo-api-bridge' ),
				CATALOGO_API_BRIDGE_VERSION
			);
			wp_enqueue_script(
				'catalogo-api-bridge-hero',
				CATALOGO_API_BRIDGE_URL . 'assets/js/catalogo-hero.js',
				array(),
				CATALOGO_API_BRIDGE_VERSION,
				true
			);
		}
	}
);
