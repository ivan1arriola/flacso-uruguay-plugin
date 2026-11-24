# Plan de implementación · Kadence e identidad FLACSO Uruguay

> Ejecución futura por paquetes. Usar superpowers:subagent-driven-development o superpowers:executing-plans. La revisión T3 de este plan se realizó con GPT-6.2 Astra y razonamiento ultra.

**Objetivo.** Que el equipo de FLACSO pueda editar la identidad visual desde Kadence, componer contenido desde WordPress y actualizar datos académicos desde las pantallas que hoy los gobiernan. Una persona que pasa de la portada al catálogo y a una ficha debe reconocer los mismos patrones y entender su siguiente acción.

**Arquitectura.** Mantener el tema hijo clásico, integrar el Personalizador y los constructores nativos, y aplicar los estilos del sistema a las plantillas del hijo y al editor. Usar patrones de bloques para contenido que requiera revisiones y composición visual. Mantener las consultas y bloques dinámicos sobre la fuente canónica del plugin.

**Base técnica.** WordPress 6.2+, PHP 8.3+ (mínimo de producción; ver `AGENTS.md` §3), Kadence, CSS, plantillas PHP, editor de bloques, pruebas PHP CLI y Playwright de staging. Confirmar versiones e interfaces exactas en la etapa 0. [Auditoría y alcance de las plantillas](auditoria.md).

## Decisión de arquitectura

| Alternativa | Evaluación |
|---|---|
| Parchear colores y familias en cada hoja | Puede resolver propiedades concretas. Conserva la composición duplicada, la dispersión de controles y la edición de contenido en PHP. |
| Integrar Kadence y migrar por familias de plantillas | **Recomendada.** Aprovecha #83. Permite comprobar cada control, mantener las rutas y revertir un lote con cambios acotados. |
| Reescribir como tema de bloques | Ofrece composición amplia en el Editor del sitio. Cambia la interfaz objetivo y obliga a rehacer el despacho de CPT, rutas virtuales y Carta. |

## Contrato editorial y visual

| Quién edita | Interfaz | Control y resultado verificable |
|---|---|---|
| Responsable de diseño con capacidad autorizada | Apariencia → Personalizar → Colores y fuentes y Diseño | Paleta, tipografía de cuerpo y encabezados, ancho general y de lectura, botones y variantes disponibles. Los presets aprobados llegan al frontal y al editor. |
| Responsable de diseño | Constructores de cabecera y pie de Kadence | Logo, menús, CTA, búsqueda, filas/columnas, widgets y responsive. Menús y widgets se asignan en staging antes de pasar a nativo. |
| Editor/a de contenido | Editor de bloques y paneles editoriales existentes | Textos, imágenes, enlaces, portadas, introducciones, promociones y agenda editorial. Cada campo tiene un solo dueño. Los patrones bloquean estructura cuando corresponda, y permiten editar texto, imagen y enlace. |
| Equipo académico | CPT y paneles del plugin | Oferta, cohorte, edición, documentos, aranceles, reconocimiento, equipos y estados. Una edición se refleja en cada superficie que muestra el dato desde el modelo. |
| Responsable institucional | Controles del hijo integrados en Personalizar, si hacen falta | Ajuste acotado para un rol visual institucional sin control equivalente en la versión instalada. Los datos de contacto conservan sus claves y pasan a una interfaz legible y validada. |

Kadence gobierna los estilos que realmente expone. La aceptación exige mover los controles reales. Si falta un control necesario, usar primero clases o ajustes nativos disponibles, y después un único control del hijo en la sección de integración. Registrar para cada propiedad el control, el selector, el valor calculado y la excepción editorial. Validar con el rol real de diseño y el rol editorial real, con sus capacidades existentes. [Guía oficial de estilo de Kadence](https://docs.nexcess.com/software/kadence/theme/theme-style-guide/) · [Botones de Kadence](https://docs.nexcess.com/software/kadence/theme/style-buttons/).

### Reglas propuestas para FLACSO Uruguay

- **Paleta.** Azul institucional de la primera entrada y amarillo de la segunda, con tinta oscura de la tercera para los CTA de marca, como en #83. Blanco y fondos claros para lectura. El gris actual de la quinta entrada es #64748b. Validar el contraste por par real de texto/fondo, estados y anillos de foco. Guardar el estado observado como base de restauración.
- **Color académico.** Mantener el acento configurado por oferta y la tinta calculada. Aplicarlo a una franja, etiqueta o acción académica según el componente. Acompañar «Inscripciones abiertas», cierre y modalidad con texto e iconografía. Usar pares claro/oscuro de foco que funcionen también sobre acentos.
- **Jerarquía.** Un H1 coherente y H2/H3 de sección con la escala de Kadence que se valide. Reservar un preset editorial de portada para el título principal. Separar contenedor normal y estrecho de lectura, y documentar las superficies a ancho completo. Conservar la maqueta vigente de portada como referencia.
- **Componentes.** Definir variantes por función: encabezado institucional, encabezado de listado, encabezado de ficha y Carta editorial e impresa. Una familia para tarjetas, estados, enlaces, botón primario, contorno y acciones de marca. Usar un sistema compartido de geometría, espaciado, foco y estados. Preservar afiches completos y fotografías recortables según plantilla.
- **Recorrido.** Usar «Oferta académica» como nombre de navegación. Mantener una misma etiqueta para acciones con destino y etapa iguales. Propuesta para revisión editorial: «Preinscribirme» si ese es el flujo real, «Consultar» si abre un canal de contacto y «Ver oferta» si navega a la ficha. Reservar el indicador de enlace externo para salidas reales. En móvil, mostrar título y acción prioritaria antes de una imagen de gran formato cuando el estado de la oferta lo permita.
- **Lenguaje e identidad.** Continuar la voz institucional uruguaya que ya usa el sitio y las imágenes aprobadas. Unificar voseo, fechas, moneda, siglas y capitalización en la guía editorial. Gestionar el logo desde identidad del sitio. La paleta, el logo visible y la maqueta aprobada son referencias de partida. La aprobación de un manual de marca formal queda a cargo de FLACSO.

## Paquetes de implementación

Cada lote tiene una sola rama y un PR con dueño de archivos, capturas y prueba de reversión. Si requiere cambios de plugin y tema, preparar PR separados y probar combinación instalada y orden de compatibilidad. Los paquetes amplios de TAREAS-CI siguen en espera. Esta propuesta define el orden y los criterios con los que reabrirlos. Los cambios en .github/ siguen el permiso explícito del repositorio.

### 0 · Entorno y contratos · WP-00 y WP-01

- [ ] Crear instalación local o staging autorizado con versiones de WordPress, padre, hijo y plugin identificadas por versión o SHA. Usar datos académicos ficticios, menús de prueba, configuraciones de los dos modos y SEO equivalente. Documentar disponibilidad de Kadence Blocks y controles reales de la instalación.
- [ ] Exportar de forma acotada valores y asignaciones relevantes de tema y menús. Generar línea base a 320, 375, 768, 1024 y 1280 px, más ambos lados de cada breakpoint afectado. Usar los datos de la auditoría como referencia pública y producir capturas equivalentes del entorno de prueba.
- [ ] Ejecutar `bash bin/check-theme.sh` y los contratos aplicables de `tests/`. Normalizar finales de línea en `tests/academic-menciones-public-test.php` antes de su regex o reemplazar la extracción por carga con stubs. Ejecutar `tests/carta-view-model-test.php` con el plugin contiguo fijado. Registrar contratos omitidos y añadir al runner los que deban impedir regresiones.
- [ ] Completar lista de consumidores para código heredado, incluidas rutas y shortcodes guardados. Documentar la carga compartida de Formación en `ARCHITECTURE.md`.

### 1 · Cabecera, pie, teclado y wrappers · WP-07 y WP-08

- [ ] Revisar `header.php`, `footer.php`, `inc/kadence-compat.php`, `inc/navigation.php`, `inc/assets.php`, `style.css`, `assets/css/site-chrome.css`, `assets/css/mobile-ux.css` y `assets/js/site-chrome.js`. Separar tokens compartidos, estilos móviles de página y comportamiento del chrome hijo. Alcance local para reglas de masthead y dependencias de portada válidas en ambos modos.
- [ ] En `oferta-academica/page.php`, eliminar el uso duplicado de `#inner-wrap` preservando clases y variables del ámbito de oferta. Enlazar el salto a un único landmark principal. Revisar CSS/JS y pruebas que usen ese selector.
- [ ] Reconstruir en los builders logo, menús, Campus Virtual, Agenda, accesos y contacto. Probar una cabecera y un pie por modo, foco visible, tecla Escape, retorno del foco, Tab con menú móvil abierto y salto al contenido. Alterar posición de logo o botón y texto/columna del pie desde el builder y observar en vista previa y sesión anónima tras publicar en staging. Restaurar el modo previo y confirmar sus asignaciones.

### 2 · Sistema visual y editor · WP-04 a WP-06

- [ ] Centralizar roles de paleta, tipografía, ancho y acciones en `style.css` y separar reglas de componentes según encolado. Eliminar aliases redundantes tras buscar consumidores y reutilizar los estilos del padre. Empezar con `front-page.css`, `site-chrome.css`, `formacion-page.css`, `type-listing-cards.css`, `single-seminario.css` y `oferta-academica/style.css`. Cada sustitución se hace en la regla que hoy gana.
- [ ] Usar un control global para cada propiedad cuando el padre lo expone. Incorporar valores en el iframe del editor con el mecanismo soportado por la instalación. Aprovechar clases nativas, presets y variables en bloques de núcleo y Kadence disponibles. Mantener excepciones documentadas de marca y oferta. Cubrir un bloque dinámico, botón, párrafo, encabezado e imagen.
- [ ] Validar desde el Personalizador: variar paleta institucional, familia, tamaño y peso de cuerpo/H1, ancho 1180→1000 a 1280 px y radio/padding del botón cuando estén disponibles. Comprobar normal, hover, focus y disabled en los controles pertinentes. Registrar valores antes, en preview, tras publicar localmente y tras restaurar. Repetir las propiedades pertinentes en móvil y desde el editor. El sondeo de CSS de la auditoría sirve para localizar selectores y no cuenta como prueba editorial.

### 3 · Formación y Seminarios · WP-06, WP-07 y WP-12

- [ ] Usar `formacion-page.php`, `taxonomy-tipo-oferta-academica.php`, `archive-seminario.php`, `seminarios-listado.php`, `formacion-page.css` y `type-listing-cards.css` para alinear anchos, encabezados y tarjetas. Consolidar `seminarios-listado.css`, `-current.css` y `-polish.css` en una hoja específica con los estilos compartidos declarados en sus dependencias. Retirar reglas sustituidas con evidencia de los selectores activos.
- [ ] Conservar meses, filtros, archivos vacíos, búsqueda, entradas integradas, recorte 4:5, estados y enlaces. Probar oferta abierta y cerrada, listados vacíos, títulos largos y navegación mediante teclado. En `template-parts/oferta/seminario.php` y `single-seminario.css`, dar prioridad móvil al título, estado y CTA antes de la imagen grande.
- [ ] Probar el recorrido portada → catálogo → tipo → ficha → acción. Los enlaces internos mantienen destino y los CTA externos conservan URL y marcador de analítica. La selección de puerta de entrada se entiende sin depender del color.

### 4 · Edición de portada, FAQ y contacto · WP-06 y WP-13

- [ ] Mapear en `front-page.php`, `inc/home.php` y los siete parciales la fuente de cada texto, imagen, enlace y bloque. Mantener prioridad campaña activa → configuración editorial vigente → respaldo. Migrar una vez los campos que requieran composición a patrones o contenido editorial con revisiones. Catálogo, agenda y noticias se renderizan desde fuentes dinámicas.
- [ ] Reutilizar destinos y tipos canónicos en portada y Formación. Propuesta T3: enumerar tipos publicados en orden editorial y mostrar Doctorados cuando tengan oferta publicada. Respetar la distinción Diploma/Diplomado y verificar los enlaces de las tarjetas y los recuentos.
- [ ] En `page-contacto.php`, `inc/contact.php` y `page-preguntas-frecuentes.php`, editar encabezados e introducciones sin duplicar la URL virtual. Referenciar una fuente editable por ID en rutas virtuales cuando corresponda. Dar a «Otros contactos» una edición por filas, con lectura compatible del JSON existente. Coordinar el único título del formulario con `fc_render_form` y mantener el renderer de respuestas del plugin.
- [ ] Editar un texto, imagen y CTA con el rol editorial, previsualizar, publicar en staging y restaurar. Probar campaña activa/inactiva, imagen ausente, cambio de URL de Agenda, FAQ sin módulo, Contacto y Formación con y sin alias, y un bloque con estilos del editor.

### 5 · Fichas, Carta, formularios y resto del sitio

- [ ] Revisar `single-oferta-academica.php`, `oferta-academica/page.php`, `inc/academic-public-model.php`, `inc/carta-view-model.php`, `inc/assets.php`, `inc/open-graph.php` y `template-parts/oferta/carta.php`. Usar un detector común que distinga la subruta /carta/ del slug de una oferta que acaba en carta. Eliminar el registro tardío duplicado cuando la prueba cubra el mecanismo temprano y la integración con el proveedor SEO.
- [ ] Probar Carta y ficha regular con fixture de slug «oferta-carta». La Carta emite una decisión coherente de robots en HTTP/HTML y conserva documentos, índice, impresión y CTA. La ficha regular conserva el comportamiento de indexación previsto. Validar ambos modos de cabecera.
- [ ] Cambiar una vez fecha de cohorte/edición o arancel de TablaPrecio y comprobar ficha, Carta y listado cuando corresponda. Cubrir sin cohorte, sin precio, cerrado, integrantes, imagen ausente, documento compartido y separado, contenido enriquecido de bloques y enlaces. Conservar el modelo canónico, la URL del portal, `data-flacso-preinscription-cta` y destino de consulta.
- [ ] Aplicar los componentes compartidos a archivo y ficha de programa, equipo/docente, convenio, eventos, novedades, entrada, página ordinaria, búsqueda, 404, FAQ y confirmaciones. Verificar los cuatro parciales de gracias con datos ficticios y transporte de correo de prueba en staging. Los destinos externos quedan bloqueados en las pruebas automatizadas.

### 6 · Aceptación y reversión por lote

- [ ] El dueño editorial y el responsable de diseño ejecutan sus casos con roles reales en staging. La matriz por ruta contiene selector, control, valor calculado, captura y confirmación anónima. Si una propiedad queda fija por decisión institucional, documentar su alcance, fuente y responsable.
- [ ] Revisar 320/375/768/1024/1280 px, límites de breakpoint, zoom al 200 %, movimiento reducido, teclado, estados vacíos y títulos largos. Exigir foco visible, texto normal 4,5:1 y texto grande/componentes 3:1 donde corresponda. Comprobar tablas con scroll local accesible y cero desborde global. Ejecutar axe con cero errores críticos o graves nuevos y revisar manualmente etiquetas, orden semántico y foco.
- [ ] Ejecutar suite y contratos adicionales en todo PR, incluida la ejecución manual para PR solo CSS. Adjuntar evidencia visual comparable y la propuesta de bitácora con ID asignado por coordinación. Revisar T3 los archivos, contratos, permisos y destinos antes de decisión humana.
- [ ] Ensayar reversión independiente: conservar paquete anterior, valores anteriores de los controles, asignaciones de menús y revisiones del contenido editado. Restaurar solo las claves o asignaciones del lote y su versión de código. Verificar rutas y aspecto tras revertir, preservando cambios académicos posteriores. El workflow del tema carece de rollback automatizado.

## Foco de revisión

| Caso | Etapa responsable |
|---|---|
| Cambio de constructor que afecta layout de páginas y skip-link | 1 |
| Kadence presente pero un control particular ausente o distinto en otra versión | 0 y 2 |
| Acentos académicos claros y oscuros, y oferta cerrada o incompleta | 2, 3 y 5 |
| Ruta virtual o slug de oferta terminado en «carta» | 4 y 5 |
| Campaña y contenido antiguo conviviendo con patrones nuevos | 4 |
| CTA, tablas y buscador con teclado a 320 px y con zoom | 1, 3, 5 y 6 |

**Primer lote ejecutable una vez reabierto.** Entorno reproducible y demostración con cabecera, pie, portada y una ficha de oferta. Probar una sola modificación de ancho y tipografía, un cambio en cada constructor, el enlace de salto y la reversión. Extender a la totalidad de las plantillas una vez registrados los controles efectivos.
