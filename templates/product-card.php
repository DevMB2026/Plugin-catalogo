<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
/** @var array $product Un producto del array $result['data'], inyectado por grid.php. */

$nombre    = isset( $product['nombre'] ) ? $product['nombre'] : '';
$marca     = isset( $product['brand']['nombre'] ) ? $product['brand']['nombre'] : '';
// Mismo nombre "para mostrar" que ya usan los botones/aside del catálogo:
// el corregido a mano en Ajustes si existe, o si no, normalizado a
// "Primera letra mayúscula, resto minúsculas" — la API no es consistente
// en cómo llega capitalizado.
$categoria = Catalogo_API_Bridge_Store::display_category_nombre(
	isset( $product['category']['slug'] ) ? $product['category']['slug'] : '',
	isset( $product['category']['nombre'] ) ? $product['category']['nombre'] : ''
);
$slug      = isset( $product['slug'] ) ? $product['slug'] : '';
$badges    = ( ! empty( $product['badges'] ) && is_array( $product['badges'] ) ) ? $product['badges'] : array();

$imagen = '';
if ( ! empty( $product['media'] ) && is_array( $product['media'] ) ) {
	foreach ( $product['media'] as $media ) {
		if ( ! empty( $media['principal'] ) && ! empty( $media['url'] ) ) {
			$imagen = $media['url'];
			break;
		}
	}
	if ( ! $imagen && ! empty( $product['media'][0]['url'] ) ) {
		$imagen = $product['media'][0]['url'];
	}
}
?>
<a class="catalogo-api-bridge-card" href="<?php echo esc_url( Catalogo_API_Bridge_Rewrite::product_url( $slug ) ); ?>">
	<div class="catalogo-api-bridge-card-img">
		<?php if ( $badges ) : ?>
			<div class="cab-card-tags">
				<?php foreach ( $badges as $b ) : ?>
					<span class="cab-card-tag"><?php echo esc_html( $b['nombre'] ?? '' ); ?></span>
				<?php endforeach; ?>
			</div>
		<?php endif; ?>
		<?php if ( $imagen ) : ?>
			<img src="<?php echo esc_url( $imagen ); ?>" alt="<?php echo esc_attr( $nombre ); ?>" loading="lazy" />
		<?php else : ?>
			<span class="catalogo-api-bridge-no-img">Sin imagen</span>
		<?php endif; ?>
	</div>
	<div class="catalogo-api-bridge-card-body">
		<?php if ( $marca ) : ?>
			<p class="catalogo-api-bridge-card-brand"><?php echo esc_html( strtoupper( $marca ) ); ?></p>
		<?php endif; ?>
		<h3 class="catalogo-api-bridge-card-title"><?php echo esc_html( $nombre ); ?></h3>
		<?php if ( $categoria ) : ?>
			<p class="catalogo-api-bridge-card-categoria"><?php echo esc_html( $categoria ); ?></p>
		<?php endif; ?>
	</div>
</a>
