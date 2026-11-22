# Hallazgos de la auditoría UI/UX

Lista viva, consolidada el 2026-11-21 a partir de las entradas B-0001 a B-0015 de `BITACORA.md`. Todo estado inicial es `nuevo` y **no verificado en navegador**: los conteos vienen de `grep` y los contrastes son cálculo propio.

Tras la revisión T3 (B-0019, lectura de código): **confirmados** H-A09 (severidad media), H-A11, H-A12, H-A13, H-A14 y H-A15; **parcial** H-A01; **reformulado** H-A07. "Confirmado" significa confirmado leyendo el código, no medido ni probado en ejecución.

Columnas: ID · hallazgo · evidencia (`archivo:línea`) · origen (entrada de bitácora) · estado · verificación.
Los estados se actualizan aquí; el porqué de cada cambio va en una entrada nueva de la bitácora.

## A. Bugs probables (confirmar primero)

| ID | Hallazgo | Evidencia | Origen | Estado | Verificación |
|---|---|---|---|---|---|
| H-A01 | Formulario general: los mensajes propios por campo nunca se ven (`display:none` inline gana al CSS y el JS no toca `style`); solo queda el globo nativo de `reportValidity()`. Sin `aria-invalid` ni `aria-describedby` | `modules/formularios/includes/form-render.php:84-119`, `assets/js/formulario-consultas.js:17-24` | B-0002, 0007, 0009, 0019 | confirmado parcial (T3) y medido en `/contactos/` | Playwright: `.invalid-feedback` con `display:none` computado; el `style` inline trae "display: block; … display: none;" y gana el último. En la ficha de maestría no hay `style` inline |
| H-A02 | Mailing sin éxito, error ni carga visibles; `flacso_mailing_status` no lo lee nadie; campo sin `<label>` | `modules/mailing/includes/class-flacso-mailing-subscription.php` | B-0002, 0009 | nuevo | pendiente |
| H-A03 | Párrafo roto en "¿Qué es FLACSO Uruguay?": `wpautop` sobre contenido con saltos entre `<strong>`. Medido en producción: la sección tiene 17 `<br>`, 23 saltos de línea y 19 pares `</strong>…<strong>` | tema `template-parts/home/about.php:4` | B-0011, 0018 | confirmado (medido) | falta ver la opción guardada (`GET /wp-json/flacso/v1/main-page`, sección `quienes`); corregir el texto es decisión del dueño |
| H-A04 | Foco: buscador del header sin indicador; unas 20 reglas `outline:none` por verificar; menú móvil sin trampa de foco; skip link a `#inner-wrap`. Playwright (25 Tab por página, 4 corridas): 0 elementos sin indicador, pero el buscador del header no llegó a enfocarse y la prueba del menú móvil falló, así que ambos siguen sin probar. Hay 2 skip links ("Saltar al contenido principal" a `#inner-wrap` y "Saltar al contenido" a `#main`), con destinos válidos | tema `assets/css/site-chrome.css:408`, `assets/js/site-chrome.js:98-102`, `header.php:49` | B-0007, 0018 | parcial | repetir con selectores correctos |
| H-A05 | Medido: el párrafo editorial del hero de seminarios **no** está oculto (la sospecha original se refuta); en 375 px sí se ocultan las descripciones de las cards (`.seminario-card__description`, `display:none`, alto 0) y en otros anchos una card queda dentro de un `details` colapsado | tema `assets/css/seminarios-listado-polish.css:836-838, 291` | B-0001, 0018 | reformulado | confirmar con el dueño si ocultar las descripciones en móvil es intencional |
| H-A06 | Instagram: si falla el feed, la sección desaparece sin aviso | `modules/main-page/includes/blocks/instagram/class-flacso-instagram-blocks.php:100-112` | B-0009 | nuevo | pendiente |
| H-A07 | `modules/docentes/includes/admin.php` no se carga (`docentes/init.php:24-39` no lo requiere): es código huérfano. Las funciones `docentes_documentacion_page`, `docentes_api_page` y `docentes_migracion_page` no existen en ningún repo y el menú `docentes_panel` probablemente no existe; `class-flacso-admin-panel.php:175` enlaza a `admin.php?page=docentes_panel` (posible enlace roto). El mensaje con clave interna ("El campo cv es obligatorio") sí se confirma | `modules/docentes/includes/admin.php:143-145`, `class-docente-meta.php:307-311`, `includes/core/class-flacso-admin-panel.php:175` | B-0013, 0019 | reformulado (T3) | confirmar en wp-admin (una persona) |
| H-A08 | Admin: pruebas y reenvíos que mandan correo o Telegram reales sin confirmación | `class-flacso-consultas-admin.php:1427-1440`, `modules/formularios/includes/settings.php:118-122` | B-0013 | nuevo | pendiente |
| H-A09 | Secretos devueltos en el `value` del HTML (token global, Meta CAPI, secreto de Mailjet, reCAPTCHA, token compartido y token del bot de Telegram). Matiz: son `type=password` y solo con `manage_options`, el secreto queda en el código fuente HTML del admin; severidad media | `includes/core/class-flacso-integrations-settings.php:1190, 1287, 1710, 1823, 1843, 1853`, `class-flacso-system-settings.php:184, 192` | B-0013, 0019 | confirmado (T3) | pendiente de corregir (WP-11) |
| H-A10 | Logo con URL absoluta fija (rompe en staging o local) | tema `template-parts/home/about.php:4` | B-0011 | nuevo | pendiente |
| H-A11 | **Pérdida silenciosa de consultas**: `preg_match('/[^\x00-\x7F\xC0-\xFF]/u', ...)` marca como spam cualquier carácter entre U+0080 y U+00BF o mayor a U+00FF: ¿ ¡ º ª « » espacio duro, comillas tipográficas, — … € y emoji (pasan á é ñ ü). El éxito simulado ocurre: redirige con `fc_exito=1` sin `error_log` ni guardado. Además: `.ru` por subcadena marca correos como `ana.ruiz@…`; `loan`, `slot`, `trading` y `hola amigo` se buscan por subcadena; reCAPTCHA con puntaje menor a 0,5 también simula éxito | `modules/formularios/includes/validation.php:13-56` (regex `:50`, `.ru` `:30,43-45`), `form-handlers.php:25-30, 69-71`, `form-render.php:22, 50-57` | B-0015, 0019 | **confirmado (T3)**; **corregido en la rama `ui/WP-02a-spam-precision` (commit `d5d9101`), pendiente de merge** | test PHP CLI `tests/formularios-spam-detection-test.php` (falló antes, pasa después); queda por ver en producción que lleguen consultas con `¿`, `€` y comillas |
| H-A12 | `wp_die` en validación y límite de intentos pierde el formulario; tras error de servicio no se restauran mensaje ni teléfono | `form-handlers.php:15, 65, 79, 148`; `form-render.php:118` | B-0015 | nuevo | pendiente |
| H-A13 | `FLACSO_RELAXED_MODE=true` y `FLACSO_USE_NONCE=false` por defecto; un correo inválido se acepta | `modules/main-page/includes/flacso-consultas.php:52-57, 1238-1240, 1385-1389`; además `:47` deja fija una URL de Google Apps Script como webhook por defecto | B-0015, 0019 | confirmado (T3) | falta ver `wp-config` de producción, que puede sobrescribir las constantes |
| H-A14 | Con reCAPTCHA activo se envía sin `checkValidity()` | `modules/formularios/includes/assets.php:45-57` | B-0015 | nuevo | pendiente |
| H-A15 | `#form-consultas`: errores sin código de seguimiento y texto "Volvé a enviar el formulario" contra `REGLAS_DE_ERRORES.md` | `flacso-consultas.php:1478, 1490` | B-0015 | nuevo | pendiente |
| H-A16 | Carrusel de eventos sin pausa por foco ni `prefers-reduced-motion` (WCAG 2.2.2) | `modules/main-page/assets/js/flacso-main-page-react.js:119-128` | B-0015 | nuevo | pendiente |
| H-A17 | FAQ vacía sin mensaje público, sin buscador y con todas las respuestas abiertas | `modules/preguntas-frecuentes/includes/class-flacso-preguntas-frecuentes.php:103-114` | B-0014, 0015 | nuevo | pendiente |
| H-A18 | Gracias con token vencido muestra "Hola Tu consulta"; el CTA de preinscripción cae a la raíz del portal si falta el enlace | `modules/formularios/includes/confirmacion-consulta.php:112-123`; tema `inc/academic-public-model.php:506-509` | B-0015 | nuevo | pendiente |
| H-A19 | Error de JavaScript en consola en la portada y en "Sobre nosotros" (`Cannot read properties of undefined (reading 'min')`) y en `/cedii/` (`Cannot read properties of null (reading 'getAttribute')`); el script de origen no está identificado | medido en producción | B-0018 | nuevo | identificar el script (WP-10) |
| H-A20 | Enlaces rotos (HTTP 400 o más): `/?page_id=13780` (enlazado desde todas las páginas), `/sobre-nosotros/programa/programa-genero-y-cultura/` y `/programa-infancias-y-adolescencia/` (desde Autoridades); 7 redirecciones 301 internas (historia, misión y visión, normativa, autoridades, …); 8 enlaces externos sin `target` ni indicación visible. Sin `target=_blank` sin `noopener` | medido en producción | B-0018 | nuevo | decisión del dueño (contenido y menús) |

## B. Kadence y tokens

| ID | Hallazgo | Origen | Estado |
|---|---|---|---|
| H-B01 | Fallbacks distintos de `--global-palette1` (`#1d3a72`, `#163970`, `#16396f`, `#173f6b`, `#274f9a`) y 5 de palette2 | B-0001, 0002, 0004 | nuevo |
| H-B02 | Paletas paralelas: `--flacso-color-*`, `--oa-*`, `--fc-*`, `--seminario-*`, `--flacso-preview-*`, `--flacso-404-*` | B-0003, 0004, 0006 | nuevo |
| H-B03 | Mismo nombre, valores distintos: `--flacso-radius-lg` (1.5rem, 34, 22, 18 px); `--flacso-section-max-width` (1290 y 1180 px en un mismo archivo) | B-0006 | nuevo |
| H-B04 | Dispersión: unos 12 azules, 11 amarillos, 9 grises, 20 radios, más de 40 sombras, más de 30 tamaños de fuente; pesos 850 y 750 | B-0006 | nuevo |
| H-B05 | Tipografías fijas (Sora, Manrope, Inter, Roboto, Montserrat); Inter se declara sin cargarse | B-0001, 0004 | nuevo |
| H-B06 | CSS global del plugin pisa al tema: `html, body {font-size:16px}`, `overflow-x:hidden`, `h1`-`h4`, `input {font-size:16px !important}` | B-0002 | nuevo |
| H-B07 | 688 `!important` en el tema (481 en `seminarios-listado-polish.css`) y 412 en el plugin | B-0001, 0002 | nuevo |
| H-B08 | Falta mapear hex a `palette1-9` reales (paleta de Kadence no está en los repos) | B-0004, B-0021 | confirmado: paleta1-9 medida en producción (`#1d3a72`, `#fed222`, `#0f1a2d`, `#2e2f34`, `#7a8696`, `#c9d2de`, `#e9edf2`, `#f8f9fa`, `#ffffff`); Kadence sí las imprime en el head |

## C. Consistencia de componentes

| ID | Hallazgo | Origen | Estado |
|---|---|---|---|
| H-C01 | Botón primario: 7 o más variantes (radios 2, 12, 13, 16, 50, 999 px); más de 14 clases de botón | B-0001, 0005 | nuevo |
| H-C02 | Cards con radios de 10 a 24 px; `.evento-card` definida 3 veces; convenios en tema y en plugin | B-0005 | nuevo |
| H-C03 | Cerca de 15 heroes de página, 7 familias de badge, 4 de chip, 4 de cabecera de card | B-0003, 0005 | nuevo |
| H-C04 | 2 formularios de consulta con estilos, validación y textos de éxito distintos | B-0002, 0005 | nuevo |
| H-C05 | Código muerto: `front-page/hero.php`, `.seminarios-hero__details`, `mobile-ux.css`, tres hojas apiladas de seminarios | B-0001, 0005, 0011 | nuevo |
| H-C06 | Bloques sin estilo de editor: 4 de docentes, `contacto-seccion`, `instagram` | B-0002 | nuevo |

## D. Responsive

| ID | Hallazgo | Origen | Estado |
|---|---|---|---|
| H-D01 | Unos 52 breakpoints distintos con pares casi idénticos (767 / 767.98 / 768); ninguno coincide con 767/1024 de Kadence (valor de memoria, a confirmar) | B-0001, 0006, 0008 | nuevo |
| H-D02 | 4 capas CSS móviles que se pisan; padding lateral de 12 px contra 20 px en 320 px; `overflow-x:hidden` global. Medido: el header no es sticky (así que ese efecto no aplica a él) y no hay desborde horizontal en 375, 768, 1024 y 1280 px (320 px no probado) | B-0008, 0018 | parcial |
| H-D03 | `h1` de portada de 64 px fijo; `100vh` sin `dvh`; tabla de precios de la carta sin contenedor con scroll | B-0008 | nuevo |

## E. Accesibilidad

| ID | Hallazgo | Origen | Estado |
|---|---|---|---|
| H-E01 | Contraste bajo (cálculo propio): `#758095` 3,98:1, `#6c7b8d` 4,33:1, `#999` 2,85:1, foco `#fcd116` 1,5:1; unas 63 declaraciones de texto menor a 12 px | B-0001, 0007 | nuevo |
| H-E02 | 8 enlaces `_blank` sin aviso; saltos h2 a h4 en dos listados; botón con `aria-label` fijo que no refleja "Enviando..."; 13 estilos de foco distintos | B-0001, 0002, 0007 | nuevo |

## F. Estados

| ID | Hallazgo | Origen | Estado |
|---|---|---|---|
| H-F01 | Faltan carga y error en eventos, instagram y mapa; "vacío" con 2 o 3 textos distintos en convenios, seminarios y docentes; sin `:focus-visible` en `formularios` | B-0002, 0009 | nuevo |

## G. Contenido y microcopy

| ID | Hallazgo | Origen | Estado |
|---|---|---|---|
| H-G01 | Voseo, tuteo y usted mezclados ("tenés" 9 contra "tienes" 14 en permisos) | B-0003, 0010 | nuevo |
| H-G02 | Unas 20 etiquetas de CTA para unas 8 acciones; "noticias" y "novedades"; "oferta", "propuesta", "programa" y "formación" | B-0010 | nuevo |
| H-G03 | Moneda de 4 formas, fechas de 6 formatos; estados en inglés en el admin; 13 palabras sin tilde | B-0003, 0010 | nuevo |
| H-G04 | 6 text domains con un solo loader confirmado; copy fijo sin traducción en `inc/home.php`, `gracias/*`, `page-contacto.php`, `site-chrome.js` | B-0003, 0010 | nuevo |

## H. Rendimiento

| ID | Hallazgo | Origen | Estado |
|---|---|---|---|
| H-H01 | Hero de portada: JPEG de 336 KB a 2560 px sin `srcset` (es el LCP). Medido en producción: hero de 2560×1709 y 449,7 KB; LCP de 5,5 a 5,9 s desde este entorno (una sola medición sin throttling propio: no es una métrica de campo) | B-0012, 0018 | confirmado parcial |
| H-H02 | Bootstrap completo en todas las páginas, con segunda copia por CDN en formularios y docentes; `bootstrap-avatar@latest` sin versión fija | B-0012 | nuevo |
| H-H03 | Analítica síncrona en `<head>` (28 KB); `oferta-academica/style.css` de 151 KB sin minificar; 4 TTF Roboto de 124 KB en la portada; consulta en bucle sin caché en `archive-programa-academico.php:47-61` | B-0012 | nuevo |
| H-H04 | Medido: en `/formacion/` hay PNG de 3.134 KB y 4.756 KB; `seminarios-artwork.png` pesa 1.930 KB; la portada hace unas 61 peticiones con 536 KB de CSS y 391 KB de JS; hay 20 imágenes sin `width`/`height` en formación y 2 en la portada; imágenes sobredimensionadas (formación 6 a 1280 px, portada 9 a 375 px) | medido en producción | B-0018 | nuevo |

## I. Principios UX

| ID | Hallazgo | Origen | Estado |
|---|---|---|---|
| H-I01 | Leyes: Jakob incumple (↗ en enlaces internos y externos sin distinguir); Similitud incumple parcial (un CTA con 4 estilos); Miller incumple en FAQ; Fitts, Hick, Región común y Posición serial parciales; Proximidad cumple; Conectividad no verificable | B-0014 | nuevo |
| H-I02 | Nielsen: Lenguaje incumple (3 etiquetas para `/formacion/`, "Preinscribirme" contra "Inscribirme"); Prevención de errores incumple en formulario general; Mensajes de error parcial (404 cumple, `#form-consultas` incumple `REGLAS_DE_ERRORES.md`); resto parcial | B-0015 | nuevo |

## J. Admin

| ID | Hallazgo | Origen | Estado |
|---|---|---|---|
| H-J01 | Peores pantallas: Docentes, Consultas, Autoridades (104 estilos inline), Analítica/Meta (750 líneas muertas, 29 `!important`), Sistema y Formulario general | B-0003, 0013 | nuevo |
| H-J02 | Botón primario recoloreado en cada módulo; íconos mezclados (emoji y dashicons); capability `edit_posts` donde correspondería `manage_options` | B-0013 | nuevo |

## Puntos ciegos
Repo `preinscripcionesFLACSOUY` y plugin Mautic (fuera de alcance); `oferta-academica/` del tema leído solo por `grep`; `.worktrees/` excluido; valores reales de la paleta de Kadence.
