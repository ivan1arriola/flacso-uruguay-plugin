# Campañas de portada — dominio, administración y contrato del plugin

Estado: propuesta revisada el 28/09/2026; no hay CPT ni cambios funcionales en esta entrega. Diseño del tema: [portada pública](../../../kadence-child-flacso/docs/toDo/2026-home-redesign.md). Secuencia futura: [plan](../../../kadence-child-flacso/docs/toDo/2026-home-redesign-implementation-plan.md).

## Responsabilidad y estado actual

`flacso-uruguay-plugin` conserva campañas, configuración editorial, modelo académico, consultas de contenidos y contratos públicos. No aporta HTML/CSS visual de la **nueva** home. `kadence-child-flacso` compone la página; no consulta metadatos académicos ni resuelve vigencias. No se modifican las APIs existentes de Editor, preinscripciones ni los CPT académicos.

Hoy `Flacso_Main_Page_Settings` guarda hero, visibilidad/orden y secciones en la opción `flacso-main-page_settings`. `Flacso_Main_Page_Unified_Settings` ofrece el panel actual bajo FLACSO → Portada con permiso `manage_options`; la clase `Flacso_Main_Page_Admin` conserva una interfaz legacy y remite a la unificada. El hero actual es una sola configuración global sin historial de campañas, vigencia o prioridad. El plugin define un renderer hero condicional con mucho CSS/JS embebido; el tema homónimo suele prevalecer. El loader registra fuentes/Bootstrap Icons/CSS de home y mantiene shortcodes para otras páginas. La configuración de noticias destacadas ya tiene lógica propia; `evento` ya es CPT y `flacso_section_eventos_get_items()` filtra publicados/visibles/próximos. `Flacso_Mailing_Subscription::render_form()` es el contrato de alta existente.

El modelo académico ya tiene `FLACSO_Academic_Catalog::registration_catalog()`, que deriva ofertas y seminarios abiertos desde `FLACSO_Academic_Repository`, `FLACSO_Cohorte::accepts_registration()` y `FLACSO_Edicion::accepts_registration()`. No crear un segundo booleano «abierto» para home. Antes de usar directamente ese catálogo hay que resolver: el repositorio limita `per_page` a 200 aunque el método pide 300; selecciona una instancia por propuesta; su respuesta pública puede variar con `current_user_can('edit_posts')`; usa fechas `date()/strtotime()` en algunos DTO; y un flag explícito `preinscripcion_habilitada` puede prevalecer sobre ventanas legacy. La home necesita un adaptador de lectura seguro y acotado, no una nueva regla de apertura.

## Modelo de campaña

CPT `flacso_home_campaign`, sin archivo público, sin single indexable ni rewrite; visible solo en administración. `post_title` es el título visible, `post_excerpt` la bajada y `post_status` usa borrador/publicado/papelera de WordPress. El contenido libre del editor no se usa: evita un page builder involuntario. Permitir autor y revisiones, pero no páginas públicas de campaña. La fecha de publicación WordPress **no** es la vigencia de portada: publicar significa «elegible», no «visible ahora». El editor mantiene `post_date` en el presente; programar la fecha nativa de publicación pondría el post en estado `future`, que el resolvedor debe excluir. La programación se hace solo con los campos de vigencia.

Metadatos propuestos, prefijados para evitar colisiones:

| Campo | Tipo y regla | Editor |
| --- | --- | --- |
| `_flacso_home_kicker` | texto plano ≤80 caracteres | Contenido |
| `post_title` | texto plano requerido ≤120 | Contenido |
| `post_excerpt` | texto plano ≤260 | Contenido |
| `_flacso_home_desktop_image_id` | ID de adjunto imagen o 0 | Imagen |
| `_flacso_home_mobile_image_id` | ID de adjunto imagen o 0 | Imagen, opcional |
| `_flacso_home_primary_label`, `_flacso_home_primary_url` | par opcional, ambos o ninguno; URL permitida por WordPress | Botones |
| `_flacso_home_secondary_label`, `_flacso_home_secondary_url` | par opcional, ambos o ninguno | Botones |
| `_flacso_home_start_at_utc`, `_flacso_home_end_at_exclusive_utc` | enteros Unix UTC; inicio requerido; fin exclusivo opcional y mayor que inicio | Vigencia: el editor introduce `Y-m-d H:i` en la zona horaria WordPress |
| `_flacso_home_priority` | entero 0–10, defecto 0 | Vigencia, avanzado |
| `_flacso_home_variant` | `institutional|enrollment|event|call`, defecto `institutional` | Diseño |

No guardar URLs de imagen ni HTML renderizado. Guardar IDs para que el tema obtenga `srcset`, tamaño y alt de la biblioteca. Registrar/sanitizar meta con callbacks y permisos. En salida, convertir a DTO sin metadatos privados: `id`, `source`, `kicker`, `title`, `description`, `desktop_image_id`, `mobile_image_id`, `primary_cta`, `secondary_cta`, `variant`, `start_at_utc`, `end_at_exclusive_utc`. Los dos tiempos del DTO son ISO 8601 UTC o `null` para un fin abierto; el frontend no calcula vigencia. Los CTA son `{label,url}` o `null`. Aceptar rutas internas absolutas de sitio (`/ruta/`) y URLs externas HTTPS; rechazar esquemas activos, URLs protocol-relative (`//host`) y pares de CTA incompletos. El tema escapa la URL y decide `rel`/apertura; no obliga nueva pestaña. Imagen móvil faltante reutiliza desktop; imagen inexistente produce `0`, no URL rota.

La variante define clase/maquetación preaprobada en el tema, nunca fondo/color/márgenes libres desde Admin. El fallback institucional es un DTO generado por el plugin con copia y enlaces editoriales definidos en una configuración acotada o constantes traducibles, no un post ficticio; `source=fallback`. Su contenido inicial no debe decir «preinscripciones abiertas» si no hay oferta abierta. Migrar hero legacy solo mediante vista previa/confirmación, sin sobreescribir opciones ni crear automáticamente una campaña publicada.

## Resolución de vigencia

API interna prevista:

- `flacso_get_active_home_campaign(?DateTimeImmutable $at = null): array` → DTO de campaña o fallback; siempre estructura válida.
- `flacso_get_home_campaign_schedule(?DateTimeImmutable $at = null): array` → `current`, `next`, `scheduled`, `previous` para el panel, con el mismo resolvedor.
- `flacso_get_home_view_model(): array` → vista normalizada con campaña, formación, preinscripciones abiertas, noticias, eventos, institución, newsletter y enlaces, sin markup.

El editor recibe inicio y fin en `Y-m-d H:i` según `wp_timezone()`. La validación rechaza fechas inexistentes o ambiguas en una transición de horario, con un mensaje que pide otra hora; convierte la entrada válida a UTC al guardar. El inicio comienza en el segundo `:00` de ese minuto. El fin que el editor introduce es el **último minuto completo**: guardar `fin UTC + 60 segundos` como límite exclusivo hace que `23:59` caduque al primer instante posterior a ese minuto, habitualmente a las `00:00` locales. Un cambio posterior de zona horaria del sitio modifica la hora que muestra Admin, pero no los instantes ya programados; Admin debe avisar sobre esa consecuencia. `now` se calcula como instante UTC; no se fija `America/Montevideo` en código.

La campaña es elegible si `post_status=publish`, `start_at_utc <= now` y el fin es nulo o `now < end_at_exclusive_utc`. Un fin menor o igual que el inicio, metadatos corruptos, título vacío o estado `future` excluyen la campaña y generan aviso administrativo. Orden entre elegibles: prioridad descendente; inicio UTC más reciente; ID de post descendente. Si no hay elegibles, el resolvedor devuelve el fallback institucional. El panel advierte solapamientos sin prohibirlos.

`next` en `flacso_get_home_campaign_schedule()` significa **próximo cambio visible**, no siguiente fecha de inicio. El resolvedor recorre los inicios y finales exclusivos futuros en orden y compara el ganador antes/después de cada límite; omite una campaña futura que nunca supera a la activa. Si vence una campaña prioritaria y reaparece otra todavía vigente, esa reaparición es el próximo cambio. Si el siguiente ganador es el fallback, Admin muestra «Volverá el mensaje institucional». Si no habrá más cambios, `next=null`; `scheduled` conserva las campañas futuras aunque no lleguen a ganar con la configuración actual.

No usar caché persistente para la resolución en v1: el conjunto de campañas es pequeño y una caché de 5 minutos ocultaría transiciones automáticas aunque no haya `save_post`. Memoización solo por petición, separada por instante si se inyecta `$at` en pruebas. Si más tarde se mide necesidad de transients/object cache, expirar en el siguiente límite temporal y purgar en `save_post_flacso_home_campaign`, cambio de estado, actualización de meta y borrado/restore. La página completa/CDN puede seguir cacheada: antes de activar, medir la política real y garantizar una demora máxima de 60 segundos para ver inicio, vencimiento y rollback, mediante TTL verificado o purga programada independiente del tráfico. Si la infraestructura no puede garantizarlo, no activar campañas automáticas.

## Admin FLACSO → Portada

Reutilizar la ubicación existente y reemplazar gradualmente la pestaña «Encabezado»; no crear dos paneles permanentes. Primera pantalla: «Ahora en portada», «Próximo cambio visible», «Programadas» y «Anteriores», con título, estado, intervalo en zona horaria configurada, variante y acciones Editar/Ver portada. «Ver portada» muestra lo que ve el público **ahora**; «Vista previa de esta campaña» muestra un borrador/programada en una respuesta autenticada, con capability y nonce, cabeceras `no-store` y exclusión de caché compartida. La vista previa nunca convierte el borrador en ganador público. En empate mostrar «Se mostrará» y razón (prioridad/inicio). Botón «Nueva campaña» solo para quien tenga capacidad.

Formulario en cinco grupos: **Contenido** (kicker/título/bajada), **Imagen** (biblioteca WordPress, vista previa escritorio/móvil y guía de alt), **Botones** (hasta dos pares etiqueta+URL y prueba de destino), **Vigencia** (inicio/fin, zona horaria visible, prioridad avanzada, vista previa de quién gana), **Diseño** (cuatro variantes con miniaturas fijas). Ninguna opción CSS. Borrador, vista previa, publicar, revisiones y papelera siguen el ciclo editorial WordPress. Avisar si falta CTA o imagen sin impedir publicación, porque el diseño debe tolerarlo; bloquear solo título/fecha/pares incompletos/URL insegura.

Registrar el CPT con `map_meta_cap=true`, `supports` que incluya autor, excerpt y revisiones, y un mapa explícito de capacidades de lectura, creación, edición propia/ajena/publicada, publicación y borrado. La capacidad `create_posts` debe mapearse expresamente; las primitivas asignadas al rol editorial aprobado permiten crear, editar y publicar, mientras Administración conserva borrado. Verificar `upload_files` para quien use la biblioteca. Registrar cada meta con autorización y `revisions_enabled` cuando la versión de WordPress desplegada lo soporte, y probar la restauración de los campos de vigencia/CTA, no solo la del título. No usar `manage_options` como permiso definitivo de asistentes. Revisar `FLACSO_Editor_Admin_Mode`: hoy oculta/redirige `flacso-main-page`, por lo que conceder capacidades no basta; se necesita una excepción acotada que abra **solo** el nuevo panel, sin exponer otros menús administrados por Editor. Todos los endpoints, guardados y vistas previas verifican capability + nonce; el menú oculto no es control de acceso.

## Datos de las demás secciones

- **Formación:** enlaces a taxonomías/páginas verificadas, con orden editorial fijo en el tema y destinos provistos por plugin/configuración existente. No crear «Doctorados» sin categoría y URL pública reales; no fusionar Diplomas y Diplomados por estética.
- **Preinscripciones abiertas:** adaptador `flacso_get_home_open_preinscriptions(int $limit = 3): array` sobre las mismas reglas académicas, sin llamar al catálogo completo por cada visita. Consultar solo oferta/seminario e instancia `publish`, sin variar según `current_user_can`; paginar/cubrir más de 200 propuestas y medir el número de consultas. Respetar `FLACSO_Cohorte::accepts_registration()` y `FLACSO_Edicion::accepts_registration()`, y normalizar `mostrar_en_formulario` como booleano para no publicar ediciones ocultas por un valor meta `'0'`. Una instancia abierta sin URL de preinscripción HTTPS válida se excluye del bloque y genera aviso administrativo; tampoco se muestra si su página informativa no es pública. Se muestra como máximo una instancia por propuesta: entre instancias abiertas, primero el inicio futuro más próximo, después los inicios pasados y al final fechas desconocidas, con desempate por ID. Entre propuestas elegidas se aplica el mismo orden, luego título e ID. Mostrar modalidad y fecha solo si constan y respetando la precisión. No añadir un estado «abierta» paralelo ni llamar «inscripción» a la preinscripción.
- **Actualidad:** leer `post` publicado de la categoría de novedades existente; respetar destacados/orden actuales en v1, sin duplicarlos; devolver un destacado y hasta tres secundarios distintos. No meter HTML de tarjetas en DTO.
- **Agenda:** aprovechar `flacso_section_eventos_get_items()` o extraer su consulta sin romper su shortcode; normalizar como evento publicado, visible, próximo y sin duplicados. La consulta actual usa `_evento_mostrar_web` y fechas de inicio/fin; conservar ese contrato.
- **Institución:** contenido breve desde opciones actuales de «Quiénes somos» o una fuente editorial única verificada; no duplicar copias incongruentes entre opción, página y template.
- **Newsletter:** conservar `Flacso_Mailing_Subscription::render_form()` y su backend/consentimiento; el adaptador entrega disponibilidad/textos, y el tema inserta el formulario existente como componente de integración excepcional. No crear un segundo POST ni recopilar correo en el tema.

Si una fuente falla, devolver colección vacía y permitir que el tema omita la sección; registrar un diagnóstico administrativo sin filtrar errores al visitante. El contrato incluye `schema_version=1` para detectar desfases entre releases de plugin/tema. La opción de rollout `flacso_home_v2_enabled` pertenece a la configuración operativa, vale `0` por defecto y solo el tema la consulta para seleccionar renderer; únicamente `true`, `1` y `'1'` la activan. La decisión efectiva de renderer se toma una vez por petición antes de encolar assets: `flacso_child_home_render_context(): array` devuelve `mode` (`v2|legacy|emergency`) y `view_model`; el tema responde al filtro `flacso_main_page_requires_legacy_assets` con `mode !== 'v2'`. El loader del plugin aplica ese filtro con valor por defecto verdadero. Un plugin anterior puede cargar CSS extra, pero el fallback no queda sin estilos. No exponer una REST pública nueva si no existe consumidor: la API solicitada es PHP interna.

## Mapa de archivos y compatibilidad

| Actual/propuesto | Acción futura | Justificación |
| --- | --- | --- |
| `modules/main-page/init.php` | modificar | registrar nuevos servicios/CPT sin retirar shortcodes |
| `modules/main-page/includes/class-flacso-main-page-settings.php` | mantener para legacy; migrar solo datos elegidos | no perder opciones ni secciones de páginas existentes |
| `modules/main-page/includes/class-flacso-main-page-admin.php`, `class-flacso-main-page-unified-settings.php` | modificar/reemplazar gradualmente pantalla Portada | evitar paneles duplicados; conservar opciones legacy hasta retirada |
| `modules/main-page/includes/class-flacso-main-page-loader.php` | modificar al activar v2 | no cargar CSS/íconos/fuentes legacy en la nueva home, mantener en shortcodes externos |
| `modules/main-page/sections/landing-page.php` | mantener; dejar de invocar desde `front-page.php` v2 | shortcode/bloque/takeover pueden seguir consumiéndolo |
| `modules/main-page/sections/hero-inscripciones.php` | mantener inicialmente; retirar después de auditoría | su función condicional y shortcode pueden tener consumidores |
| `modules/main-page/sections/posgrados.php`, `novedades-section.php`, `eventos-carousel.php`, `mailing.php` | mantener API/shortcodes; extraer consultas a servicios cuando proceda | evitar cortar páginas no-home y alta mailing |
| `modules/main-page/assets/css/flacso-main-page*.css`, `flacso-novedades-45.css` | dejar de encolar **solo en home v2**; retirar más adelante si no hay consumidores | CSS es del renderer legacy, no del nuevo tema |
| `modules/main-page/assets/js/flacso-main-page-react.js` | no encolar en v2; evaluar retiro tras inventario de filtro | React es optativo en legacy |
| `modules/main-page/includes/class-flacso-home-campaign*.php` | crear clases pequeñas para CPT, campos/validación, resolución y admin | dominio exclusivo del plugin |
| `modules/main-page/includes/class-flacso-home-data.php` | crear agregador de DTOs de home | interfaz única para tema |
| `tests/home-campaign-*.php`, `tests/home-data-contract-test.php` | crear | fechas, permisos, saneamiento, contratos y compatibilidad |

El mapa de templates y CSS del tema está en su [especificación](../../../kadence-child-flacso/docs/toDo/2026-home-redesign.md). El plugin no añade `assets/css/home.css` ni variantes visuales. Puede tener CSS limitado para **su administración**, cargado solo en esa pantalla.

## Pruebas y migración

Pruebas unitarias/WordPress: futura/publicada/expirada; segundo anterior y segundo exacto al inicio/fin exclusivo; fin nulo; prioridad, inicio e ID; reaparece campaña anterior, próximo ganador fallback y candidata futura que no gana; borrador/`future`/papelera; DST inexistente/ambiguo y cambio posterior de timezone; validación y sanitización de título/CTA/URL/imagen; permisos por rol, `create_posts`, edición de publicados, biblioteca y acceso directo; preview autenticada/no-store sin filtración pública; restauración de revisiones de meta; fallback; fuente académica no publicada aun con editor autenticado; catálogo >200, edición oculta `'0'`, URL faltante y varias instancias; fechas imprecisas; módulos ausentes; noticia/evento duplicado; newsletter. El test de contrato ejecuta el DTO con tema anterior y nuevo en staging para comprobar despliegue escalonado. La prueba de caché de página/CDN se realiza aparte en la tarea de activación.

Migración futura: (1) instalar CPT/servicios sin cambiar la home; (2) configurar roles y panel con sombra del hero actual, sin publicación automática; (3) instalar tema v2 con flag apagado; (4) revisar visualmente campaña y fallback; (5) activar v2 y controlar CDN/HTML/métricas; (6) mantener rollback y opciones legacy; (7) retirar markup/CSS/JS antiguos solo tras inventario y ventana de observación. No se hace ninguna de esas operaciones en esta tarea de documentación.

Riesgos por verificar antes de activar: rol editorial real frente a modo Editor; versión WordPress y soporte de revisión de meta; configuración de caché que cumpla los 60 segundos; destino canónico de preinscripciones; coste de consultas del adaptador; semántica «Doctorados» frente a oferta publicada; uso de shortcodes fuera de home; fuente institucional única y derechos de imagen. Antes de activar v2, conservar la opción legacy del hero con una copia institucional aprobada y no fechada, y respaldar su valor anterior: el rollback no debe mostrar una campaña antigua ya vencida. No sincronizar automáticamente cada campaña nueva con esa opción legacy.
