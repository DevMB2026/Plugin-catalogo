/**
 * Interactividad del hero animado del catálogo ([catalogo_hero]): rota entre
 * los productos ya pintados por hero.php (data-cab-hero-visual), cruza el
 * color de fondo/tarjeta hacia el acento de cada uno (data-hex) y hace crecer
 * la imagen entrante desde la miniatura de "siguiente" mientras la saliente
 * sale volando hacia arriba. Sin PHP no hay nada que animar: si este script
 * no carga, el primer producto se queda fijo y el hero se ve como una
 * portada estática normal.
 */
(function () {
	'use strict';

	var AUTOPLAY_MS = 4200;
	var SWAP_MS = 650;

	function hexToRgb(hex) {
		var h = (hex || '').replace('#', '');
		if (h.length === 3) {
			h = h.split('').map(function (c) { return c + c; }).join('');
		}
		var n = parseInt(h, 16) || 0;
		return { r: (n >> 16) & 255, g: (n >> 8) & 255, b: n & 255 };
	}

	function rgba(hex, a) {
		var c = hexToRgb(hex);
		return 'rgba(' + c.r + ',' + c.g + ',' + c.b + ',' + a + ')';
	}

	function glowBg(hex) {
		return 'radial-gradient(46% 46% at 22% 18%, ' + rgba(hex, 0.9) + ' 0%, transparent 72%),' +
			'radial-gradient(42% 42% at 82% 86%, ' + rgba(hex, 0.7) + ' 0%, transparent 72%)';
	}

	function washBg(hex) {
		return 'linear-gradient(155deg, ' + rgba(hex, 0.5) + ' 0%, transparent 52%),' +
			'radial-gradient(70% 60% at 100% 0%, ' + rgba(hex, 0.35) + ' 0%, transparent 60%)';
	}

	function init(root) {
		var visuals = Array.prototype.slice.call(root.querySelectorAll('[data-cab-hero-visual]'));
		if (visuals.length < 2) {
			return; // Un solo producto: nada que rotar, se queda la portada estática que ya pintó el PHP.
		}

		var frame = root.querySelector('[data-cab-hero-frame]');
		var caption = root.querySelector('[data-cab-hero-caption]');
		var peek = root.querySelector('[data-cab-hero-peek]');
		var idxLabel = root.querySelector('[data-cab-hero-idx]');
		var glowA = root.querySelector('.cab-hero-glow-a');
		var glowB = root.querySelector('.cab-hero-glow-b');
		var washA = root.querySelector('.cab-hero-wash-a');
		var washB = root.querySelector('.cab-hero-wash-b');

		var current = 0;
		var glowFront = glowA;
		var glowBack = glowB;
		var washFront = washA;
		var washBack = washB;
		var locked = false;
		var timer = null;
		var reduced = window.matchMedia && window.matchMedia('(prefers-reduced-motion: reduce)').matches;

		function paintPeek() {
			if (!peek) {
				return;
			}
			var next = visuals[(current + 1) % visuals.length];
			peek.style.setProperty('--cab-hero-peek-accent', next.getAttribute('data-hex'));
			peek.setAttribute('aria-label', 'Ver ' + next.getAttribute('data-nombre'));
		}

		function paintAccent(hex) {
			root.style.setProperty('--cab-hero-accent', hex);
		}

		function goTo(nextIdx) {
			if (locked || nextIdx === current) {
				return;
			}
			locked = true;

			var curEl = visuals[current];
			var nextEl = visuals[nextIdx];
			var hex = nextEl.getAttribute('data-hex');

			nextEl.classList.remove('state-idle');
			nextEl.classList.add('state-enter');
			// Fuerza una repintada con "state-enter" ya aplicado, para que el
			// paso a "state-current" (más abajo) sí dispare la transición en
			// vez de saltar directo al estado final.
			void nextEl.offsetHeight;

			curEl.classList.remove('state-current');
			curEl.classList.add('state-exit');

			if (caption) {
				caption.classList.add('is-swapping');
			}

			glowBack.style.background = glowBg(hex);
			washBack.style.background = washBg(hex);

			requestAnimationFrame(function () {
				requestAnimationFrame(function () {
					nextEl.classList.remove('state-enter');
					nextEl.classList.add('state-current');
					glowBack.classList.add('is-active');
					glowFront.classList.remove('is-active');
					washBack.classList.add('is-active');
					washFront.classList.remove('is-active');
					paintAccent(hex);
				});
			});

			setTimeout(function () {
				if (caption) {
					caption.textContent = nextEl.getAttribute('data-nombre') +
						(nextEl.getAttribute('data-color') ? ' — ' + nextEl.getAttribute('data-color') : '');
					caption.classList.remove('is-swapping');
				}
			}, 200);

			setTimeout(function () {
				curEl.classList.remove('state-exit');
				curEl.classList.add('state-idle');
				var tmp = glowFront; glowFront = glowBack; glowBack = tmp;
				tmp = washFront; washFront = washBack; washBack = tmp;
				current = nextIdx;
				if (idxLabel) {
					idxLabel.textContent = String(current + 1).padStart(2, '0');
				}
				paintPeek();
				locked = false;
			}, SWAP_MS);
		}

		function next() { goTo((current + 1) % visuals.length); }
		function prev() { goTo((current - 1 + visuals.length) % visuals.length); }

		function restartAutoplay() {
			if (timer) {
				clearInterval(timer);
				timer = null;
			}
			if (reduced) {
				return;
			}
			timer = setInterval(next, AUTOPLAY_MS);
		}

		root.querySelectorAll('[data-cab-hero-dir]').forEach(function (btn) {
			btn.addEventListener('click', function () {
				var dir = parseInt(btn.getAttribute('data-cab-hero-dir'), 10);
				if (dir > 0) { next(); } else { prev(); }
				restartAutoplay();
			});
		});

		if (peek) {
			peek.addEventListener('click', function () {
				next();
				restartAutoplay();
			});
		}

		if (frame) {
			frame.addEventListener('mouseenter', function () {
				if (timer) {
					clearInterval(timer);
					timer = null;
				}
			});
			frame.addEventListener('mouseleave', restartAutoplay);
		}

		paintAccent(visuals[0].getAttribute('data-hex'));
		paintPeek();
		restartAutoplay();
	}

	document.addEventListener('DOMContentLoaded', function () {
		document.querySelectorAll('[data-cab-hero]').forEach(init);
	});
})();
