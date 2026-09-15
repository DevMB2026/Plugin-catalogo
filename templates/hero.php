<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
/**
 * @var array  $hero_productos Hasta $hero_limite productos (mismo payload que get_featured()), inyectado por Catalogo_API_Bridge_Shortcodes::render_hero().
 * @var array  $hero_categorias Hasta 4 categorías del catálogo, para la pastilla de navegación — puede venir vacío.
 * @var string $hero_titulo
 * @var string $hero_subtitulo
 * @var string $hero_texto_boton
 * @var string $hero_enlace_boton
 *
 * Las imágenes se dejan como marcador de posición a propósito (icono +
 * "Imagen próximamente"): en cuanto el catálogo tenga fotos listas para este
 * bloque, esta es la única pieza que hay que tocar — reemplazar el bloque
 * ".cab-hero-visual-ph" de abajo por un <img> normal con Catalogo_API_Bridge_Store::main_image( $producto ).
 */
if ( empty( $hero_productos ) ) {
	return;
}
?>
<section class="cab-hero" data-cab-hero>
	<div class="cab-hero-glow" aria-hidden="true">
		<div class="cab-hero-glow-layer cab-hero-glow-a is-active"></div>
		<div class="cab-hero-glow-layer cab-hero-glow-b"></div>
	</div>

	<div class="cab-hero-card">
		<div class="cab-hero-wash" aria-hidden="true">
			<div class="cab-hero-wash-layer cab-hero-wash-a is-active"></div>
			<div class="cab-hero-wash-layer cab-hero-wash-b"></div>
		</div>

		<?php if ( $hero_categorias ) : ?>
			<nav class="cab-hero-nav">
				<span class="cab-hero-brand"><span class="cab-hero-dot"></span><?php echo esc_html( get_bloginfo( 'name' ) ); ?></span>
				<div class="cab-hero-nav-pill">
					<?php foreach ( $hero_categorias as $cat ) : ?>
						<a class="cab-hero-nav-link" href="<?php echo esc_url( add_query_arg( 'cab_categoria', $cat['slug'], Catalogo_API_Bridge_Rewrite::catalog_url() ) ); ?>"><?php echo esc_html( $cat['nombre'] ); ?></a>
					<?php endforeach; ?>
				</div>
			</nav>
		<?php endif; ?>

		<div class="cab-hero-body">
			<div class="cab-hero-copy">
				<?php if ( count( $hero_productos ) > 1 ) : ?>
					<div class="cab-hero-controls">
						<button type="button" class="cab-hero-arrow" data-cab-hero-dir="-1" aria-label="Producto anterior">&lsaquo;</button>
						<button type="button" class="cab-hero-arrow" data-cab-hero-dir="1" aria-label="Producto siguiente">&rsaquo;</button>
						<span class="cab-hero-index"><span data-cab-hero-idx>01</span> / <?php echo esc_html( str_pad( (string) count( $hero_productos ), 2, '0', STR_PAD_LEFT ) ); ?></span>
					</div>
				<?php endif; ?>

				<h2 class="cab-hero-heading"><?php echo esc_html( $hero_titulo ); ?></h2>
				<p class="cab-hero-sub"><?php echo esc_html( $hero_subtitulo ); ?></p>
				<a class="cab-hero-cta" href="<?php echo esc_url( $hero_enlace_boton ); ?>"><?php echo esc_html( $hero_texto_boton ); ?> <span>&rarr;</span></a>
			</div>

			<div class="cab-hero-stage">
				<div class="cab-hero-frame" data-cab-hero-frame>
					<?php foreach ( $hero_productos as $i => $producto ) :
						$nombre   = isset( $producto['nombre'] ) ? $producto['nombre'] : '';
						$acento   = Catalogo_API_Bridge_Colors::accent_for_product( $producto );
						$estado   = 0 === $i ? 'state-current' : 'state-idle';
						?>
						<div class="cab-hero-visual <?php echo esc_attr( $estado ); ?>" data-cab-hero-visual data-index="<?php echo (int) $i; ?>" data-hex="<?php echo esc_attr( $acento['hex'] ); ?>" data-nombre="<?php echo esc_attr( $nombre ); ?>" data-color="<?php echo esc_attr( $acento['nombre'] ); ?>">
							<span class="cab-hero-visual-ph" style="color:<?php echo esc_attr( $acento['hex'] ); ?>">
								<svg viewBox="0 0 64 64" fill="none" xmlns="http://www.w3.org/2000/svg" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round"><path d="M24 8h16l6 6-4 4-2-2v34a3 3 0 0 1-3 3H27a3 3 0 0 1-3-3V16l-2 2-4-4 6-6Z"/><path d="M24 8c1.5 4 4 6 8 6s6.5-2 8-6"/><path d="M18 22 8 26l3 9 7-3"/><path d="M46 22l10 4-3 9-7-3"/><path d="M32 26v22"/></svg>
							</span>
							<span class="cab-hero-visual-label">Imagen próximamente</span>
						</div>
					<?php endforeach; ?>
				</div>

				<p class="cab-hero-caption" data-cab-hero-caption>
					<?php
					$primero = $hero_productos[0];
					$acento0 = Catalogo_API_Bridge_Colors::accent_for_product( $primero );
					echo esc_html( trim( ( $primero['nombre'] ?? '' ) . ( $acento0['nombre'] ? ' — ' . $acento0['nombre'] : '' ) ) );
					?>
				</p>

				<?php if ( count( $hero_productos ) > 1 ) :
					$siguiente        = $hero_productos[1];
					$acento_siguiente = Catalogo_API_Bridge_Colors::accent_for_product( $siguiente );
					?>
					<button type="button" class="cab-hero-peek" data-cab-hero-peek aria-label="Ver siguiente producto" style="--cab-hero-peek-accent:<?php echo esc_attr( $acento_siguiente['hex'] ); ?>">
						<svg viewBox="0 0 64 64" fill="none" xmlns="http://www.w3.org/2000/svg" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round"><path d="M24 8h16l6 6-4 4-2-2v34a3 3 0 0 1-3 3H27a3 3 0 0 1-3-3V16l-2 2-4-4 6-6Z"/><path d="M24 8c1.5 4 4 6 8 6s6.5-2 8-6"/><path d="M18 22 8 26l3 9 7-3"/><path d="M46 22l10 4-3 9-7-3"/><path d="M32 26v22"/></svg>
					</button>
				<?php endif; ?>
			</div>
		</div>
	</div>
</section>
