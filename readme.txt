=== Catálogo API Bridge ===
Contributors: prezenza
Tags: catálogo, api, productos
Requires at least: 6.0
Tested up to: 7.0
Requires PHP: 7.4
Stable tag: 1.18.0
License: proprietary

Consume el catálogo central de productos (API propia) para mostrarlo en WordPress, sin precios y sin depender de una llamada a la API en cada visita.

== Description ==

Este plugin conecta cualquier sitio WordPress con el catálogo central de productos (API propia). No es un CMS de productos: la API sigue siendo la única fuente de verdad, pero WordPress guarda una base de datos local con la última copia sincronizada — así el catálogo se sirve siempre desde este sitio, nunca con una llamada en vivo por visita, y sigue funcionando si la API llega a caerse. La sincronización se actualiza sola cada ~2 minutos, más una revisión completa diaria.

Fase 5A (esta versión): solo catálogo público, sin precios y sin autenticación de distribuidor. La API Key de distribuidor se agregará en una fase posterior, en un módulo separado.

== Configuración ==

1. Activa el plugin.
2. Ve a Ajustes → Catálogo API y confirma/edita la URL base de la API.
3. Usa el shortcode `[catalogo_grid]` en cualquier página o entrada para mostrar el catálogo. Admite los atributos `marca`, `categoria`, `categoria_inicial`, `orden`, `catalogo`, `limite` y `estilo` — ninguno obligatorio. `categoria` fija la categoría (el visitante no puede cambiarla); `categoria_inicial` solo es la que se ve al entrar (ej. `[catalogo_grid categoria_inicial="chamarras"]`); `orden` pone primero ciertos productos y en ese orden (ej. `orden="shell, atractive, hydro, reaction"`).
4. El detalle de cada producto se sirve automáticamente en `/catalogo/producto/{slug}/`.
5. Usa `[catalogo_hero]` para el hero animado con los productos destacados. Admite `titulo`, `subtitulo`, `boton`, `enlace` y `limite` — ninguno obligatorio.

== Changelog ==

= 1.18.0 =
* Nuevo atributo `orden` en `[catalogo_grid]`: los productos cuyo slug contiene esas palabras salen primero, en el orden escrito (ej. `orden="shell, atractive, hydro, reaction"`); el resto va después con el orden de siempre. Cada palabra se compara como palabra completa del slug (`shell` encuentra `chamarra-shell`) y una misma lista sirve para todas las categorías.

= 1.17.0 =
* Nuevo atributo `categoria_inicial` en `[catalogo_grid]`: la categoría que se ve al entrar al catálogo (ej. `categoria_inicial="chamarras"`), sin fijarla — el visitante puede cambiar a otra, y "Quitar filtro" lleva a `?cab_categoria=todas` para ver todo.

= 1.16.1 =
* Prueba del sistema de actualizaciones automáticas (Plugin Update Checker) — sin cambios funcionales.

= 1.16.0 =
* Actualizaciones automáticas: el plugin ya avisa solo cuando hay una versión nueva (Escritorio → Plugins, igual que cualquier plugin de la tienda oficial), leyendo los tags publicados en https://github.com/DevMB2026/Plugin-catalogo. No requiere acción del sitio que lo tiene instalado — para publicar una versión nueva basta con subir el número de Version, hacer commit y empujar un tag `vX.Y.Z`.

= 1.15.0 =
* Nuevo shortcode `[catalogo_hero]`: hero animado con los productos destacados (mismos que ya alimentaba "Productos destacados" del aside) — el fondo y la tarjeta cambian de color según el acento del producto que esté al frente (primer valor del eje "Color"), la imagen central crece desde una miniatura de "siguiente producto" y la anterior sale volando hacia arriba, con flechas y avance automático. Admite `titulo`, `subtitulo`, `boton`, `enlace` y `limite` (ninguno obligatorio). Las imágenes se dejan como marcador de posición a propósito — se conecta un `<img>` real cuando el catálogo tenga fotos listas para este bloque. Nuevo `Catalogo_API_Bridge_Colors::accent_for_product()`.

= 1.12.1 =
* Cambiado: el panel Ajustes → Categorías del catálogo ahora muestra una fila por cada categoría YA FUSIONADA (la que el visitante realmente ve, ej. "Sudaderas") en vez de una fila por cada category_slug crudo de la API (ej. "Sudaderas", "Fleece", "Hoodie", "Cat"... por separado) — la fusión sigue siendo automática según la jerarquía real de la API, esto solo simplifica el panel para editar nombre/portada/orden/mostrar de la categoría final, sin la lista larga y repetida. Se quitó el campo "Grupo" (ya no hace falta, la fusión es automática).

= 1.12.0 =
* Corregido: las categorías del catálogo se armaban a partir de la categoría cruda de cada producto tal cual venía de la API, sin usar la jerarquía real que la API ya maneja (campo "parent") — categorías que en realidad son sub-categorías de otra (ej. "Fleece", "Hoodie" o "Basica", todas hijas de "Sudaderas" en la API) aparecían como si fueran categorías sueltas y de primer nivel, inflando el catálogo con entradas que no corresponden a las categorías reales. Ahora el plugin consulta GET /categories y agrupa automáticamente cada categoría cruda bajo su categoría raíz real, sin depender de que alguien lo configure a mano en Ajustes (esa opción sigue disponible para casos que la API no cubra).

= 1.11.1 =
* Corregido: el nombre de categoría normalizado (o corregido a mano en Ajustes) solo se veía en los botones/aside del catálogo — la ficha de producto (migajero de pan y meta bajo el título) y la categoría de cada tarjeta seguían mostrando el nombre tal cual venía de la API, sin normalizar y sin la corrección manual. Ahora se usa el mismo nombre resuelto en todos lados.

= 1.11.0 =
* Nueva sección "Productos relacionados" al final de la ficha de producto: hasta 4 productos activos de la misma categoría, usando las mismas tarjetas del listado. Nuevo método público `Catalogo_API_Bridge_Store::get_related()`.

= 1.10.0 =
* Nuevo bloque "Categorías del catálogo" en Ajustes → Catálogo API (mismo sistema que ya tenía Catálogo Distribuidor Bridge): nombre, portada, orden y visibilidad de cada categoría, editables a mano — por defecto vienen del catálogo sincronizado (nombre normalizado, foto del producto más reciente de esa categoría, orden alfabético).
* El listado (`[catalogo_grid]`) ahora muestra botones grandes de categoría arriba y un aside con filtro de categorías + productos destacados, con el mismo filtrado por GET (`?cab_categoria=slug`) sin salir de la página. Antes el grid solo pintaba las tarjetas de producto, sin ninguna forma de navegar por categoría.
* Nuevo campo "URL de tu página de catálogo" en Ajustes → Catálogo API: se usa para el enlace "Catálogo" del migajero de pan y para el nuevo botón "Regresar al catálogo" en la ficha de producto (antes el migajero de pan iba fijo a la portada del sitio). Si se deja vacío, sigue cayendo a la portada.
* Nuevos métodos públicos en `Catalogo_API_Bridge_Store`: `get_categories()`, `get_featured()` y `main_image()` — disponibles también para quien use el nivel 3 de personalización (headless).

= 1.9.0 =
* Corregido: la pestaña del navegador (y el título SEO) de la ficha de producto mostraba "Blog" en vez del nombre real del producto — la ruta `/catalogo/producto/{slug}/` es virtual y no tiene una página de WordPress real detrás, así que el sitio caía al título por defecto. Ahora se usa el nombre del producto.

= 1.8.0 =
* Fotos por género: cada foto (galería del producto y de cada variante) puede etiquetarse desde el panel admin como "Caballero", "Dama" o "Ambos". La ficha ahora muestra las fotos que corresponden al género seleccionado, en vez de intentar adivinar por el nombre del archivo (que fallaba cuando el nombre traía ambas palabras a la vez, ej. SKUs de dama+caballero fusionados en un mismo producto).

= 1.7.0 =
* Tabla de medidas por género: si un producto combina hombre y mujer con cortes/medidas distintas, la ficha ahora puede mostrar la tabla de medidas correcta según el género que el cliente tenga seleccionado (mismo selector que ya cambia color/talla/galería), en vez de una sola tabla genérica.

= 1.6.0 =
* Base de datos local: el catálogo ahora se guarda en una tabla propia de este sitio (en vez de solo un caché temporal) y se sirve siempre desde ahí — ninguna visita hace ya una llamada en vivo a la API. Un alta, edición o baja en la API se refleja aquí en cuestión de minutos mediante una sincronización automática (cada ~2 minutos), más una revisión completa diaria que limpia cualquier producto eliminado por completo. Si la API llega a caerse, el catálogo sigue mostrando la última sincronización buena, sin borrar nada.
* Nuevo bloque "Estado de sincronización" en Ajustes → Catálogo API, con la hora de la última sincronización, cuántos productos hay guardados localmente, y un botón "Sincronizar ahora" para forzarla sin esperar al siguiente ciclo.

= 1.5.0 =
* Nuevo bloque "CSS personalizado del catálogo" directo en Ajustes → Catálogo API: editor de código grande (monoespaciado, ancho completo) donde se puede escribir CSS sin salir del plugin ni usar Apariencia → Personalizar → CSS adicional. Se guarda con el mismo botón "Guardar cambios" y se aplica en el sitio con `wp_add_inline_style()` (después del CSS base, para poder sobreescribirlo sin `!important`). Incluye botón "Restaurar CSS predeterminado" con confirmación. El campo se sanea eliminando cualquier etiqueta HTML/script antes de guardarse.

= 1.4.0 =
* Caché en dos capas: la copia "fresca" ahora dura 1 hora (antes 10 minutos) para reducir cuántas veces cada sitio le pega a la API central — importante si varios sitios usan el mismo catálogo. Además, se guarda una copia de respaldo sin expiración: si la API llega a fallar (caída, error 5xx), el catálogo sigue mostrando el último dato bueno conocido en vez de un error, sin tapar respuestas legítimas como 404 o 401.
* Autor actualizado a Prezenza.

= 1.3.0 =
* Nivel 5 de personalización: sobreescritura de plantillas desde el tema del sitio (mismo patrón que WooCommerce). Si el tema activo tiene una carpeta `catalogo-api-bridge/` con `grid.php`, `product-card.php`, `detail.php` o `error.php`, el plugin usa esa versión en vez de la suya — sin dejar de manejar la conexión a la API ni el caché. Si el archivo no existe en el tema, se usa la plantilla por defecto, sin ningún cambio de comportamiento.
* Documentado en Ajustes → Catálogo API.

= 1.2.0 =
* Nuevo atributo `estilo` en el shortcode `[catalogo_grid]`: `cuadricula` (por defecto, igual que antes), `lista` (filas con imagen, marca, nombre y categoría) o `compacta` (grid denso, solo imagen y nombre). No cambia qué productos se muestran, solo su acomodo.
* Nuevo ajuste en Ajustes → Catálogo API: "Galería en la ficha de producto", con dos opciones — miniaturas abajo de la imagen principal (como antes) o miniaturas en columna a la izquierda (acomodo tipo Shein). Solo afecta la página de un producto individual.
* Se documenta en la página de ajustes cómo personalizar el diseño con CSS adicional de WordPress, con las clases estables del plugin.

= 1.1.0 =
* La ficha de producto ahora tiene la misma información e interacción que el sitio React: selector de Género, círculos de color reales (usando el hex guardado en la API o, si no hay, un diccionario de colores por nombre), tallas, galería con miniaturas por variante, Composición y SKU que se actualizan en vivo al cambiar la selección — sin recargar la página. Antes solo mostraba nombre, descripción e imágenes sueltas.
* Se agregan las secciones de Personalización, Características, Especificaciones, Tabla de medidas y Preguntas frecuentes cuando el producto las trae.

= 1.0.2 =
* Fix: en temas con un `<main>` de layout tipo grid (ej. Woodmart, sistema de 12 columnas), la ficha de producto se comprimía a un ancho mínimo (~75px, texto una palabra por línea) porque nuestro contenedor no indicaba cuántas columnas debía ocupar y el navegador le asignaba solo 1 de 12. Se agrega `grid-column: 1 / -1` al contenedor principal de la ficha para que ocupe todo el ancho disponible sin importar el sistema de columnas del tema.

= 1.0.1 =
* Fix: en algunos temas la columna de la galería de imágenes en la ficha de producto se colapsaba a 0px de ancho (conflicto de CSS Grid con el layout del tema). Se agrega `min-width: 0` a las columnas y `width: 100%` a los contenedores para que no dependan del contexto del tema.

= 1.0.0 =
* Primera versión (Fase 5A): listado, detalle, caché, manejo de errores/404. Sin precios, sin API Key de distribuidor.
