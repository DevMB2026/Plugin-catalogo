<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Ficha de producto — replica la interacción del sitio React
 * (ProductoDetalle.jsx): selección de género/color/talla resuelve una
 * variante y actualiza galería, composición y SKU en vivo, vía
 * assets/js/detail.js. El PHP solo renderiza el estado INICIAL (primer
 * valor de cada eje) y deja embebidos los datos crudos del producto en un
 * <script type="application/json"> para que el JS pueda recalcular sin
 * volver a pedirle nada a la API.
 */

$slug   = get_query_var( Catalogo_API_Bridge_Rewrite::QUERY_VAR );
$result = Catalogo_API_Bridge_Store::is_empty()
	? Catalogo_API_Bridge_Cache::remember( Catalogo_API_Bridge_Cache::key_for_product_slug( $slug ), function () use ( $slug ) {
		return Catalogo_API_Bridge_Client::get_product_by_slug( $slug );
	} )
	: Catalogo_API_Bridge_Store::get_by_slug( $slug );

if ( empty( $result['ok'] ) && 404 === $result['status'] ) {
	status_header( 404 );
}

get_header();

if ( empty( $result['ok'] ) ) :
	?>
	<main class="catalogo-api-bridge-detail-wrap">
		<?php include Catalogo_API_Bridge_Templates::locate( 'error.php' ); ?>
	</main>
	<?php
	get_footer();
	return;
endif;

$product = $result['data'];

$sexo_label = array( 'hombre' => 'Caballero', 'mujer' => 'Dama', 'unisex' => 'Unisex' );

$nombre      = isset( $product['nombre'] ) ? $product['nombre'] : '';
$marca       = isset( $product['brand']['nombre'] ) ? $product['brand']['nombre'] : '';
$categoria_slug = isset( $product['category']['slug'] ) ? $product['category']['slug'] : '';
// Mismo nombre "para mostrar" que ya usan los botones/aside del catálogo:
// el corregido a mano en Ajustes si existe, o si no, normalizado a
// "Primera letra mayúscula, resto minúsculas" — la API no es consistente
// en cómo llega capitalizado.
$categoria   = Catalogo_API_Bridge_Store::display_category_nombre( $categoria_slug, isset( $product['category']['nombre'] ) ? $product['category']['nombre'] : '' );
$catalogo_url   = Catalogo_API_Bridge_Rewrite::catalog_url();
$sku         = isset( $product['sku'] ) ? $product['sku'] : '';
$descripcion = isset( $product['descripcion'] ) ? $product['descripcion'] : '';
$activo      = ! empty( $product['activo'] );
$sexos       = ( ! empty( $product['sexo'] ) && is_array( $product['sexo'] ) ) ? $product['sexo'] : array();
$options     = ( ! empty( $product['options'] ) && is_array( $product['options'] ) ) ? $product['options'] : array();
$variants    = ( ! empty( $product['variants'] ) && is_array( $product['variants'] ) ) ? $product['variants'] : array();
$applications = ( ! empty( $product['applications'] ) && is_array( $product['applications'] ) ) ? $product['applications'] : array();
$features    = ( ! empty( $product['features'] ) && is_array( $product['features'] ) ) ? $product['features'] : array();
$attributes  = ( ! empty( $product['attributes'] ) && is_array( $product['attributes'] ) ) ? $product['attributes'] : array();
$badges      = ( ! empty( $product['badges'] ) && is_array( $product['badges'] ) ) ? $product['badges'] : array();
// Tabla de medidas: si el producto combina hombre y mujer con cortes
// distintos, puede traer una tabla POR género además de (u opcional en vez
// de) la general — la ficha muestra la que corresponde al género
// seleccionado, con el mismo selector que ya cambia color/talla/galería.
$size_chart_default = ( ! empty( $product['sizeChart']['rows'] ) ) ? $product['sizeChart'] : null;
$size_chart_hombre  = ( ! empty( $product['sizeChartHombre']['rows'] ) ) ? $product['sizeChartHombre'] : null;
$size_chart_mujer   = ( ! empty( $product['sizeChartMujer']['rows'] ) ) ? $product['sizeChartMujer'] : null;
$faq         = ( ! empty( $product['faq'] ) && is_array( $product['faq'] ) ) ? $product['faq'] : array();

// --- Selección inicial: primer valor de cada eje + primer género (igual que el useEffect de React) ---
$selected = array(); // optionId => valueId
foreach ( $options as $o ) {
	$opt_id = isset( $o['option']['_id'] ) ? $o['option']['_id'] : null;
	$first_val = isset( $o['values'][0]['_id'] ) ? $o['values'][0]['_id'] : null;
	if ( $opt_id && $first_val ) {
		$selected[ $opt_id ] = $first_val;
	}
}
$sel_sexo = isset( $sexos[0] ) ? $sexos[0] : null;

// --- Variante que hace match con la selección inicial (mismo algoritmo que React) ---
function cab_find_variant( $variants, $selected ) {
	$selected_ids = array_values( $selected );
	sort( $selected_ids );
	foreach ( $variants as $v ) {
		$ids = array_map(
			function ( $ov ) {
				return isset( $ov['_id'] ) ? $ov['_id'] : null;
			},
			isset( $v['optionValues'] ) && is_array( $v['optionValues'] ) ? $v['optionValues'] : array()
		);
		sort( $ids );
		if ( $ids === $selected_ids ) {
			return $v;
		}
	}
	return null;
}
$variant = cab_find_variant( $variants, $selected );

/** Pinta una sola tabla de medidas ($chart = ['unidad','columns','rows']) dentro de la guía de tallas. */
function cab_render_size_table( $chart ) {
	if ( empty( $chart['rows'] ) ) {
		return;
	}
	?>
	<div class="cab-table-scroll">
		<table>
			<thead>
				<tr>
					<th>Talla</th>
					<?php foreach ( (array) ( $chart['columns'] ?? array() ) as $c ) : ?><th><?php echo esc_html( $c ); ?></th><?php endforeach; ?>
				</tr>
			</thead>
			<tbody>
				<?php foreach ( $chart['rows'] as $row ) : ?>
					<tr>
						<td><strong><?php echo esc_html( $row['label'] ?? '' ); ?></strong></td>
						<?php foreach ( (array) ( $chart['columns'] ?? array() ) as $ci => $c ) : ?>
							<td><?php echo esc_html( $row['values'][ $ci ] ?? '—' ); ?></td>
						<?php endforeach; ?>
					</tr>
				<?php endforeach; ?>
			</tbody>
		</table>
	</div>
	<?php
}

// --- Galería inicial: media de la variante (desduplicada), o si no hay, la del producto ---
function cab_dedupe_media( $media ) {
	$seen = array();
	$out  = array();
	foreach ( (array) $media as $m ) {
		if ( ! empty( $m['url'] ) && empty( $seen[ $m['url'] ] ) ) {
			$seen[ $m['url'] ] = true;
			$out[] = $m;
		}
	}
	return $out;
}
// Filtra por género usando la etiqueta real de cada foto ($m['sexo'], puesta
// a mano en el panel admin) — las fotos sin etiquetar sirven para cualquier
// género, así que nunca desaparecen por no marcarse. El JS recalcula esto
// mismo (filterGenero en detail.js) al cambiar de género.
function cab_filter_genero( $imgs, $sexo ) {
	if ( ! $sexo ) {
		return $imgs;
	}
	$propias   = array_values( array_filter( $imgs, function ( $m ) use ( $sexo ) { return isset( $m['sexo'] ) && $m['sexo'] === $sexo; } ) );
	$genericas = array_values( array_filter( $imgs, function ( $m ) { return empty( $m['sexo'] ); } ) );
	$out       = array_merge( $propias, $genericas );
	return $out ? $out : $imgs;
}

// Cuando la variante no trae su propia galería (variant.media viene vacío,
// como pasa en la mayoría de los productos), las fotos por color están en
// realidad en product.media, cada una etiquetada con el _id del valor de
// color en optionValue — así es como las guarda el panel de admin. Sin este
// filtro, cambiar el swatch de color solo cambiaba el estado "activo" del
// botón pero la foto principal nunca se actualizaba.
function cab_filter_by_color( $imgs, $color_value_id ) {
	if ( ! $color_value_id ) {
		return $imgs;
	}
	$propias   = array_values( array_filter( $imgs, function ( $m ) use ( $color_value_id ) { return ! empty( $m['optionValue'] ) && $m['optionValue'] === $color_value_id; } ) );
	// Si el color tiene fotos propias, SOLO esas: la galería general (fotos
	// sin color) no se mezcla — si no, al elegir Azul salía también la foto
	// gris sin color asignado. Sin fotos propias, se usa la galería general
	// (misma regla que el panel admin y la ficha de la API).
	if ( $propias ) {
		return $propias;
	}
	$genericas = array_values( array_filter( $imgs, function ( $m ) { return empty( $m['optionValue'] ); } ) );
	return $genericas ? $genericas : $imgs;
}

// Eje de color entre las $options del producto: el mismo criterio que ya
// se usa más abajo para decidir si un eje se pinta como swatch o como pill.
$color_value_id = null;
foreach ( $options as $o ) {
	$o_id   = isset( $o['option']['_id'] ) ? $o['option']['_id'] : null;
	$o_tipo = isset( $o['option']['tipo'] ) ? $o['option']['tipo'] : '';
	$o_slug = isset( $o['option']['slug'] ) ? $o['option']['slug'] : '';
	$o_name = isset( $o['option']['nombre'] ) ? $o['option']['nombre'] : '';
	$o_is_color = ( 'swatch' === $o_tipo ) || preg_match( '/color/i', $o_slug ) || preg_match( '/color/i', $o_name );
	if ( $o_is_color && $o_id && isset( $selected[ $o_id ] ) ) {
		$color_value_id = $selected[ $o_id ];
		break;
	}
}

$variant_media = $variant && ! empty( $variant['media'] ) ? cab_dedupe_media( $variant['media'] ) : array();
$product_media = cab_dedupe_media( isset( $product['media'] ) ? $product['media'] : array() );
$base_images   = $variant_media ? $variant_media : cab_filter_by_color( $product_media, $color_value_id );
$images        = cab_filter_genero( $base_images, $sel_sexo );
$main_img      = isset( $images[0] ) ? $images[0] : null;

$disponible = $activo && ( ! $variant || false !== $variant['activo'] );

// Datos crudos para el JS (recalcula todo esto mismo al cambiar género/color/talla).
$js_data = array(
	'sexoLabel'    => $sexo_label,
	'sexos'        => $sexos,
	'options'      => $options,
	'variants'     => $variants,
	'productMedia' => $product['media'] ?? array(),
	'nombre'       => $nombre,
	'activo'       => $activo,
);
?>
<main class="catalogo-api-bridge-detail-wrap">
	<nav class="catalogo-api-bridge-breadcrumb">
		<a href="<?php echo esc_url( $catalogo_url ); ?>">Catálogo</a>
		<?php if ( $categoria ) : ?>
			<span>/</span>
			<?php if ( $categoria_slug ) : ?>
				<a href="<?php echo esc_url( add_query_arg( 'cab_categoria', Catalogo_API_Bridge_Store::filter_slug_for( $categoria_slug ), $catalogo_url ) ); ?>"><?php echo esc_html( $categoria ); ?></a>
			<?php else : ?>
				<span><?php echo esc_html( $categoria ); ?></span>
			<?php endif; ?>
		<?php endif; ?>
	</nav>

	<?php $galeria_layout = get_option( 'catalogo_api_galeria_layout', 'abajo' ); ?>
	<article class="catalogo-api-bridge-detail" data-product='<?php echo wp_json_encode( $js_data, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP ); ?>'>
		<div class="catalogo-api-bridge-detail-gallery<?php echo 'izquierda' === $galeria_layout ? ' catalogo-api-bridge-detail-gallery--izquierda' : ''; ?>">
			<div class="cab-gallery-main">
				<div class="cab-main-img">
					<?php if ( $main_img ) : ?>
						<img src="<?php echo esc_url( $main_img['url'] ); ?>" alt="<?php echo esc_attr( $nombre ); ?>" class="cab-main-img-el" />
					<?php else : ?>
						<div class="catalogo-api-bridge-no-img">Sin imagen</div>
					<?php endif; ?>
				</div>
				<button type="button" class="cab-gallery-nav cab-gallery-nav-prev" data-dir="-1" aria-label="Foto anterior" style="<?php echo count( $images ) > 1 ? '' : 'display:none'; ?>">&#8249;</button>
				<button type="button" class="cab-gallery-nav cab-gallery-nav-next" data-dir="1" aria-label="Foto siguiente" style="<?php echo count( $images ) > 1 ? '' : 'display:none'; ?>">&#8250;</button>
			</div>
			<?php if ( count( $images ) > 1 ) : ?>
				<div class="cab-thumbs">
					<?php foreach ( $images as $i => $im ) : ?>
						<button type="button" class="cab-thumb<?php echo 0 === $i ? ' active' : ''; ?>" data-idx="<?php echo (int) $i; ?>">
							<img src="<?php echo esc_url( $im['url'] ); ?>" alt="" />
						</button>
					<?php endforeach; ?>
				</div>
			<?php endif; ?>
		</div>

		<div class="catalogo-api-bridge-detail-info">
			<a href="<?php echo esc_url( $catalogo_url ); ?>" class="cab-back-btn">&larr; Regresar al catálogo</a>
			<div class="cab-top-row">
				<?php if ( $marca ) : ?><span class="catalogo-api-bridge-detail-brand"><?php echo esc_html( strtoupper( $marca ) ); ?></span><?php endif; ?>
				<span class="cab-badge-disponible" style="<?php echo $disponible ? '' : 'display:none'; ?>">● Disponible</span>
				<?php foreach ( $badges as $b ) : ?>
					<span class="cab-tag"><?php echo esc_html( $b['nombre'] ?? '' ); ?></span>
				<?php endforeach; ?>
			</div>

			<h1 class="catalogo-api-bridge-detail-title"><?php echo esc_html( $nombre ); ?></h1>
			<p class="catalogo-api-bridge-detail-meta">
				<?php echo esc_html( $categoria ); ?>
				<?php if ( $sku ) : ?> &middot; SKU <span class="cab-sku-text"><?php echo esc_html( $sku ); ?></span><?php endif; ?>
			</p>

			<?php
			$whatsapp_numero = get_option( 'catalogo_api_whatsapp_numero', '' );
			if ( $whatsapp_numero ) :
				$whatsapp_url = Catalogo_API_Bridge_Rewrite::build_whatsapp_quote_url( $whatsapp_numero, $nombre, $sku );
				?>
				<a href="<?php echo esc_url( $whatsapp_url ); ?>" class="cab-whatsapp-btn" target="_blank" rel="noopener noreferrer">
					<svg viewBox="0 0 32 32" width="18" height="18" fill="currentColor" aria-hidden="true"><path d="M16.004 3C9.373 3 4 8.373 4 15.004c0 2.386.7 4.61 1.91 6.478L4 29l7.71-1.874A11.94 11.94 0 0 0 16.004 27c6.631 0 12.001-5.373 12.001-12.004C28.005 8.373 22.635 3 16.004 3zm0 21.818a9.78 9.78 0 0 1-5.031-1.386l-.361-.214-4.575 1.112 1.132-4.462-.236-.376a9.767 9.767 0 0 1-1.5-5.204c0-5.417 4.408-9.825 9.826-9.825 2.625 0 5.093 1.023 6.949 2.879a9.756 9.756 0 0 1 2.876 6.951c0 5.417-4.408 9.826-9.825 9.826zm5.393-7.35c-.296-.148-1.75-.864-2.021-.963-.271-.099-.469-.148-.667.148-.198.297-.766.963-.939 1.161-.173.198-.346.223-.642.074-.296-.148-1.25-.461-2.381-1.469-.88-.785-1.475-1.755-1.648-2.052-.173-.297-.019-.457.13-.605.134-.133.297-.346.445-.519.148-.173.198-.297.297-.495.099-.198.05-.372-.025-.52-.074-.148-.667-1.607-.914-2.202-.241-.579-.486-.5-.667-.51l-.568-.01c-.198 0-.52.074-.792.372-.271.297-1.038 1.014-1.038 2.473 0 1.459 1.063 2.868 1.211 3.066.148.198 2.093 3.196 5.073 4.481.709.306 1.262.489 1.693.626.711.226 1.358.194 1.87.118.57-.085 1.75-.716 1.997-1.408.247-.692.247-1.285.173-1.408-.074-.124-.271-.198-.568-.346z"/></svg>
					Cotizar por WhatsApp
				</a>
			<?php endif; ?>

			<?php if ( $sexos ) : ?>
				<div class="cab-section">
					<div class="cab-section-head">
						<p class="cab-section-title">Género</p>
						<span class="cab-section-value" data-role="genero-value"><?php echo esc_html( isset( $sexo_label[ $sel_sexo ] ) ? $sexo_label[ $sel_sexo ] : $sel_sexo ); ?></span>
					</div>
					<div class="cab-pills">
						<?php foreach ( $sexos as $s ) : ?>
							<button type="button" class="cab-pill cab-genero-btn<?php echo $s === $sel_sexo ? ' active' : ''; ?>" data-sexo="<?php echo esc_attr( $s ); ?>">
								<?php echo esc_html( isset( $sexo_label[ $s ] ) ? $sexo_label[ $s ] : $s ); ?>
							</button>
						<?php endforeach; ?>
					</div>
				</div>
			<?php endif; ?>

			<?php foreach ( $options as $o ) :
				$opt_id   = isset( $o['option']['_id'] ) ? $o['option']['_id'] : '';
				$opt_name = isset( $o['option']['nombre'] ) ? $o['option']['nombre'] : '';
				$opt_tipo = isset( $o['option']['tipo'] ) ? $o['option']['tipo'] : '';
				$opt_slug = isset( $o['option']['slug'] ) ? $o['option']['slug'] : '';
				$is_color = ( 'swatch' === $opt_tipo ) || preg_match( '/color/i', $opt_slug ) || preg_match( '/color/i', $opt_name );
				$is_talla = preg_match( '/talla/i', $opt_slug ) || preg_match( '/talla/i', $opt_name );
				$values   = isset( $o['values'] ) && is_array( $o['values'] ) ? $o['values'] : array();
				$sel_val  = isset( $selected[ $opt_id ] ) ? $selected[ $opt_id ] : null;
				$sel_obj  = null;
				foreach ( $values as $v ) { if ( isset( $v['_id'] ) && $v['_id'] === $sel_val ) { $sel_obj = $v; break; } }
				?>
				<div class="cab-section" data-option-id="<?php echo esc_attr( $opt_id ); ?>">
					<div class="cab-section-head">
						<p class="cab-section-title"><?php echo esc_html( $opt_name ); ?></p>
						<span class="cab-section-value" data-role="option-value"><?php echo esc_html( $sel_obj ? $sel_obj['valor'] : '' ); ?></span>
					</div>
					<div class="cab-pills">
						<?php foreach ( $values as $val ) :
							$val_id = isset( $val['_id'] ) ? $val['_id'] : '';
							$active = ( $val_id === $sel_val );
							if ( $is_color ) :
								$bg = Catalogo_API_Bridge_Colors::swatch_background( $val['valor'] ?? '', $val['meta']['hex'] ?? null );
								?>
								<button type="button" class="cab-swatch<?php echo $active ? ' active' : ''; ?>" title="<?php echo esc_attr( $val['valor'] ?? '' ); ?>" style="background:<?php echo esc_attr( $bg ); ?>" data-option="<?php echo esc_attr( $opt_id ); ?>" data-value="<?php echo esc_attr( $val_id ); ?>" data-label="<?php echo esc_attr( $val['valor'] ?? '' ); ?>"></button>
							<?php else : ?>
								<button type="button" class="cab-pill<?php echo $active ? ' active' : ''; ?>" data-option="<?php echo esc_attr( $opt_id ); ?>" data-value="<?php echo esc_attr( $val_id ); ?>" data-label="<?php echo esc_attr( $val['valor'] ?? '' ); ?>">
									<?php echo esc_html( $val['valor'] ?? '' ); ?>
								</button>
							<?php endif; ?>
						<?php endforeach; ?>
					</div>
				</div>

				<?php if ( $is_talla && ( $size_chart_mujer || $size_chart_hombre || $size_chart_default ) ) : ?>
					<details class="cab-sizeguide">
						<summary class="cab-sizeguide-toggle">Guía de tallas</summary>
						<div class="cab-sizeguide-panel">
							<?php if ( $size_chart_mujer || $size_chart_hombre ) : ?>
								<?php if ( $size_chart_mujer ) : ?>
									<div class="cab-sizeguide-block">
										<h3>Dama <span>(<?php echo esc_html( $size_chart_mujer['unidad'] ?? '' ); ?>)</span></h3>
										<?php cab_render_size_table( $size_chart_mujer ); ?>
									</div>
								<?php endif; ?>
								<?php if ( $size_chart_hombre ) : ?>
									<div class="cab-sizeguide-block">
										<h3>Caballero <span>(<?php echo esc_html( $size_chart_hombre['unidad'] ?? '' ); ?>)</span></h3>
										<?php cab_render_size_table( $size_chart_hombre ); ?>
									</div>
								<?php endif; ?>
							<?php else : ?>
								<div class="cab-sizeguide-block">
									<h3>Medidas <span>(<?php echo esc_html( $size_chart_default['unidad'] ?? '' ); ?>)</span></h3>
									<?php cab_render_size_table( $size_chart_default ); ?>
								</div>
							<?php endif; ?>
						</div>
					</details>
				<?php endif; ?>
			<?php endforeach; ?>

			<div class="cab-section" data-role="composicion-section" style="<?php echo ( $variant && ! empty( $variant['composicion'] ) ) ? '' : 'display:none'; ?>">
				<p class="cab-section-title">Composición</p>
				<p class="cab-composicion-text" data-role="composicion-text"><?php echo esc_html( $variant['composicion'] ?? '' ); ?></p>
			</div>

			<div class="cab-sku-line" data-role="sku-line" style="<?php echo $variant ? '' : 'display:none'; ?>">
				<span data-role="sku-full">SKU <?php echo esc_html( $variant['sku'] ?? '' ); ?><?php echo ( ! empty( $variant['stock'] ) && $variant['stock'] > 0 ) ? ' · ' . (int) $variant['stock'] . ' en stock' : ''; ?></span>
			</div>

			<?php if ( $applications ) : ?>
				<div class="cab-section">
					<p class="cab-section-title">Personalización</p>
					<div class="cab-chips">
						<?php foreach ( $applications as $a ) : ?>
							<span class="cab-chip"><?php echo esc_html( $a['nombre'] ?? '' ); ?></span>
						<?php endforeach; ?>
					</div>
				</div>
			<?php endif; ?>

			<?php if ( $features ) : ?>
				<div class="cab-section">
					<p class="cab-section-title">Características</p>
					<ul class="cab-features">
						<?php foreach ( $features as $f ) : ?>
							<li>✓ <?php echo esc_html( $f['nombre'] ?? '' ); ?></li>
						<?php endforeach; ?>
					</ul>
				</div>
			<?php endif; ?>

			<?php if ( $attributes ) : ?>
				<div class="cab-section">
					<p class="cab-section-title">Especificaciones</p>
					<dl class="cab-attrs">
						<?php foreach ( $attributes as $i => $a ) :
							$def = $a['attribute'] ?? array();
							$v   = $a['value'] ?? '';
							$display = $v;
							if ( isset( $def['type'] ) ) {
								if ( 'boolean' === $def['type'] ) {
									$display = $v ? 'Sí' : 'No';
								} elseif ( 'select' === $def['type'] ) {
									foreach ( (array) ( $def['options'] ?? array() ) as $opt ) {
										if ( ( $opt['value'] ?? null ) === $v ) { $display = $opt['label'] ?? $v; break; }
									}
								} elseif ( 'multiselect' === $def['type'] ) {
									$labels = array();
									foreach ( (array) $v as $val ) {
										$lbl = $val;
										foreach ( (array) ( $def['options'] ?? array() ) as $opt ) {
											if ( ( $opt['value'] ?? null ) === $val ) { $lbl = $opt['label'] ?? $val; break; }
										}
										$labels[] = $lbl;
									}
									$display = implode( ', ', $labels );
								} elseif ( 'number' === $def['type'] ) {
									$display = $v . ( ! empty( $def['unit'] ) ? ' ' . $def['unit'] : '' );
								}
							}
							?>
							<div class="cab-attr-row<?php echo ( $i % 2 ) ? '' : ' alt'; ?>">
								<dt><?php echo esc_html( $def['label'] ?? '' ); ?></dt>
								<dd><?php echo esc_html( is_scalar( $display ) ? $display : wp_json_encode( $display ) ); ?></dd>
							</div>
						<?php endforeach; ?>
					</dl>
				</div>
			<?php endif; ?>

			<?php if ( $descripcion ) : ?>
				<div class="cab-section">
					<p class="cab-section-title">Descripción</p>
					<p class="catalogo-api-bridge-detail-desc"><?php echo esc_html( $descripcion ); ?></p>
				</div>
			<?php endif; ?>
		</div>
	</article>

	<?php if ( $faq ) : ?>
		<section class="cab-faq">
			<h2>Preguntas frecuentes</h2>
			<?php foreach ( $faq as $f ) : ?>
				<div class="cab-faq-item">
					<p class="cab-faq-q"><?php echo esc_html( $f['pregunta'] ?? '' ); ?></p>
					<p class="cab-faq-a"><?php echo esc_html( $f['respuesta'] ?? '' ); ?></p>
				</div>
			<?php endforeach; ?>
		</section>
	<?php endif; ?>

	<?php
	// Productos relacionados: otros activos de la misma categoría. Se
	// calcula al final porque reutiliza product-card.php (que espera una
	// variable $product) — para entonces ya no hace falta el $product del
	// producto principal, así que reusar el nombre no rompe nada de arriba.
	$related = Catalogo_API_Bridge_Store::get_related(
		isset( $product['category']['slug'] ) ? $product['category']['slug'] : '',
		isset( $product['_id'] ) ? $product['_id'] : '',
		4
	);
	?>
	<?php if ( $related ) : ?>
		<section class="cab-related">
			<h2>Productos relacionados</h2>
			<div class="catalogo-api-bridge-grid catalogo-api-bridge-grid--cuadricula cab-related-grid">
				<?php foreach ( $related as $product ) : ?>
					<?php include Catalogo_API_Bridge_Templates::locate( 'product-card.php' ); ?>
				<?php endforeach; ?>
			</div>
		</section>
	<?php endif; ?>
</main>
<?php
get_footer();
