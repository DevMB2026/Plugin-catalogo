/**
 * Interactividad de la ficha de producto — puerto a JS vanilla de la misma
 * lógica de selección/variante/galería que usa ProductoDetalle.jsx en el
 * sitio React, para que el plugin de WordPress se comporte y se vea igual.
 * No depende de nada externo (sin build step, sin frameworks).
 */
(function () {
	'use strict';

	function dedupeByUrl(list) {
		var seen = {};
		var out = [];
		(list || []).forEach(function (m) {
			if (m && m.url && !seen[m.url]) {
				seen[m.url] = true;
				out.push(m);
			}
		});
		return out;
	}

	// Filtra por género usando la etiqueta real de cada foto (m.sexo, puesta a
	// mano en el panel admin) — las fotos sin etiquetar sirven para cualquier
	// género, así que nunca desaparecen por no marcarse.
	function filterGenero(imgs, sexo) {
		if (!sexo) return imgs;
		var propias = imgs.filter(function (m) { return m.sexo === sexo; });
		var genericas = imgs.filter(function (m) { return !m.sexo; });
		var out = propias.concat(genericas);
		return out.length ? out : imgs;
	}

	// Cuando la variante no trae su propia galería (variant.media vacío, el
	// caso más común), las fotos por color viven en productMedia, cada una
	// etiquetada con el _id del valor de color en optionValue — mismo
	// criterio que cab_filter_by_color() en detail.php.
	function filterByColor(imgs, colorValueId) {
		if (!colorValueId) return imgs;
		var propias = imgs.filter(function (m) { return m.optionValue && m.optionValue === colorValueId; });
		// Si el color tiene fotos propias, SOLO esas; la galería general (sin
		// color) solo cuando el color no tiene ninguna. Misma regla que en PHP.
		if (propias.length) return propias;
		var genericas = imgs.filter(function (m) { return !m.optionValue; });
		return genericas.length ? genericas : imgs;
	}

	// Eje de color entre las options del producto: mismo criterio que ya usa
	// detail.php para decidir si un eje se pinta como swatch o como pill.
	function findColorOptionId(options) {
		for (var i = 0; i < (options || []).length; i++) {
			var opt = options[i].option || {};
			var isColor = opt.tipo === 'swatch' || /color/i.test(opt.slug || '') || /color/i.test(opt.nombre || '');
			if (isColor) return opt._id;
		}
		return null;
	}

	function findVariant(variants, selected) {
		var selectedIds = Object.keys(selected).map(function (k) { return selected[k]; }).sort();
		for (var i = 0; i < variants.length; i++) {
			var v = variants[i];
			var ids = (v.optionValues || []).map(function (ov) { return ov._id; }).sort();
			if (ids.length === selectedIds.length && ids.join('|') === selectedIds.join('|')) return v;
		}
		return null;
	}

	function init(article) {
		var data;
		try {
			data = JSON.parse(article.getAttribute('data-product'));
		} catch (e) {
			return;
		}

		var wrap = article.closest('.catalogo-api-bridge-detail-wrap');
		var mainImgEl = wrap.querySelector('.cab-main-img');
		var thumbsEl = wrap.querySelector('.cab-thumbs');
		var navPrevEl = wrap.querySelector('.cab-gallery-nav-prev');
		var navNextEl = wrap.querySelector('.cab-gallery-nav-next');
		var skuLineEl = wrap.querySelector('[data-role="sku-line"]');
		var skuFullEl = wrap.querySelector('[data-role="sku-full"]');
		var composicionSection = wrap.querySelector('[data-role="composicion-section"]');
		var composicionText = wrap.querySelector('[data-role="composicion-text"]');
		var generoValueEl = wrap.querySelector('[data-role="genero-value"]');

		// Estado inicial: lo que el PHP ya marcó como .active en el primer render.
		var selected = {};
		wrap.querySelectorAll('.cab-swatch.active, .cab-pill.active[data-option]').forEach(function (btn) {
			selected[btn.getAttribute('data-option')] = btn.getAttribute('data-value');
		});
		var selSexo = null;
		var activeGenero = wrap.querySelector('.cab-genero-btn.active');
		if (activeGenero) selSexo = activeGenero.getAttribute('data-sexo');
		var imgIdx = 0;
		var currentImages = [];
		var colorOptionId = findColorOptionId(data.options);

		function renderGallery(variant) {
			var variantMedia = dedupeByUrl((variant && variant.media) || []);
			var colorValueId = colorOptionId ? selected[colorOptionId] : null;
			var baseImages = variantMedia.length ? variantMedia : filterByColor(dedupeByUrl(data.productMedia || []), colorValueId);
			currentImages = filterGenero(baseImages, selSexo);
			imgIdx = 0;

			if (!currentImages.length) {
				mainImgEl.innerHTML = '<div class="catalogo-api-bridge-no-img">Sin imagen</div>';
			} else {
				mainImgEl.innerHTML = '<img src="' + currentImages[0].url + '" alt="" class="cab-main-img-el" />';
			}

			if (currentImages.length > 1) {
				var html = '';
				currentImages.forEach(function (im, i) {
					html += '<button type="button" class="cab-thumb' + (i === 0 ? ' active' : '') + '" data-idx="' + i + '"><img src="' + im.url + '" alt="" /></button>';
				});
				thumbsEl.innerHTML = html;
				thumbsEl.style.display = '';
			} else if (thumbsEl) {
				thumbsEl.innerHTML = '';
				thumbsEl.style.display = 'none';
			}

			var navDisplay = currentImages.length > 1 ? '' : 'none';
			if (navPrevEl) navPrevEl.style.display = navDisplay;
			if (navNextEl) navNextEl.style.display = navDisplay;
		}

		function selectThumb(i) {
			if (!currentImages[i]) return;
			imgIdx = i;
			mainImgEl.innerHTML = '<img src="' + currentImages[i].url + '" alt="" class="cab-main-img-el" />';
			thumbsEl.querySelectorAll('.cab-thumb').forEach(function (t, ti) {
				t.classList.toggle('active', ti === i);
			});
		}

		function render() {
			var variant = findVariant(data.variants || [], selected);

			renderGallery(variant);

			if (variant && variant.composicion) {
				composicionText.textContent = variant.composicion;
				composicionSection.style.display = '';
			} else if (composicionSection) {
				composicionSection.style.display = 'none';
			}

			if (variant) {
				var txt = 'SKU ' + variant.sku;
				if (variant.stock > 0) txt += ' · ' + variant.stock + ' en stock';
				skuFullEl.textContent = txt;
				skuLineEl.style.display = '';
			} else if (skuLineEl) {
				skuLineEl.style.display = 'none';
			}

			// Estados activos + etiquetas de valor seleccionado por sección.
			wrap.querySelectorAll('[data-option-id]').forEach(function (section) {
				var optId = section.getAttribute('data-option-id');
				var valId = selected[optId];
				var label = '';
				section.querySelectorAll('.cab-swatch, .cab-pill').forEach(function (btn) {
					var isActive = btn.getAttribute('data-value') === valId;
					btn.classList.toggle('active', isActive);
					if (isActive) label = btn.getAttribute('data-label') || '';
				});
				var valueEl = section.querySelector('[data-role="option-value"]');
				if (valueEl) valueEl.textContent = label;
			});

			wrap.querySelectorAll('.cab-genero-btn').forEach(function (btn) {
				btn.classList.toggle('active', btn.getAttribute('data-sexo') === selSexo);
			});
			if (generoValueEl) {
				generoValueEl.textContent = (data.sexoLabel && data.sexoLabel[selSexo]) || selSexo || '';
			}
		}

		wrap.addEventListener('click', function (e) {
			var swatchOrPill = e.target.closest('.cab-swatch[data-option], .cab-pill[data-option]');
			if (swatchOrPill) {
				selected[swatchOrPill.getAttribute('data-option')] = swatchOrPill.getAttribute('data-value');
				render();
				return;
			}
			var generoBtn = e.target.closest('.cab-genero-btn');
			if (generoBtn) {
				selSexo = generoBtn.getAttribute('data-sexo');
				render();
				return;
			}
			var thumb = e.target.closest('.cab-thumb');
			if (thumb) {
				selectThumb(parseInt(thumb.getAttribute('data-idx'), 10));
				return;
			}
			var navBtn = e.target.closest('.cab-gallery-nav');
			if (navBtn && currentImages.length) {
				var dir = parseInt(navBtn.getAttribute('data-dir'), 10);
				selectThumb((imgIdx + dir + currentImages.length) % currentImages.length);
			}
		});

		// Sincroniza currentImages con lo que el PHP ya pintó, sin recalcular
		// nada más al cargar (evita un "parpadeo" innecesario de imagen).
		var initialVariant = findVariant(data.variants || [], selected);
		var variantMedia = dedupeByUrl((initialVariant && initialVariant.media) || []);
		var initialColorValueId = colorOptionId ? selected[colorOptionId] : null;
		var baseImages = variantMedia.length ? variantMedia : filterByColor(dedupeByUrl(data.productMedia || []), initialColorValueId);
		currentImages = filterGenero(baseImages, selSexo);
	}

	document.addEventListener('DOMContentLoaded', function () {
		document.querySelectorAll('.catalogo-api-bridge-detail[data-product]').forEach(init);
	});
})();
