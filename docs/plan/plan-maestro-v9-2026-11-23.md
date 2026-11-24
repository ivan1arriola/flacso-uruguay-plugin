# Plan maestro v9: entregas chicas, primero analítica e UI

Fecha: 2026-11-23. Reemplaza al v8.3 (`docs/plan/plan-maestro-v8.3-2026-11-23.md`), que queda como **catálogo de referencia**: los IDs de ítems (A, P, Q, M, S, R, T, PC, C, U) y las decisiones D1–D19 siguen valiendo y este plan los cita sin repetirlos. La especificación del sistema de diseño (§3 del v8.3) y las reglas comunes (§4.1 del v8.3) siguen vigentes.

Bases: plugin `main` ca707aa (PR #124), tema `main` ffd8988 (PR #122). Fuentes: relevamiento de estado en git/gh, inventario de analítica y atribución de los cuatro repos y de la documentación UI/UX (tres lectores Haiku, 23/11), auditoría documental B-0051 y verificación propia de los datos que mueven entregas.

## 1. Qué cambia respecto del v8.3

1. **Entregas chicas.** Cada entrega es **1 a 3 PR** (normalmente 1), cabe en un día de trabajo de un agente, deja el sitio estable y declara un número o un comportamiento que cambia. Las 18 entregas grandes del v8.3 pasan a **lotes `L01`–`L40`**.
2. **Orden: analítica e UI primero.** La analítica (paquete T del v8.3, antes E18 al final) pasa al frente. Lo de UI con efecto inmediato y sin dependencias de tokens va antes del sistema de diseño. El sistema de diseño (E4–E17) se mantiene, pero partido.
3. **Una pista de lectura obligatoria por delante (L02).** Ningún repo carga GA4 ni GTM: los inyecta algo fuera del código (probablemente Site Kit o el contenedor GTM-NCJBS38S). Hasta saber qué hace ese contenedor no se toca ningún evento.
4. **Lo que agy ya está haciendo (E2 del v8.2) sigue igual** y no se duplica: P1, P2, P3a, P3c, P5 (solo diagnóstico de las cargas de analítica), Q2 y M1. Queda registrado como `L00`.
5. **Analítica desacoplada (24/11).** El tema, los módulos del plugin y el portal solo anuncian hechos del negocio (`dataLayer.push` en el navegador, `do_action( 'flacso/…' )` en el servidor) según un contrato escrito. Un plugin aparte, `flacso-analytics`, es dueño del píxel, de la cola de CAPI, del almacén de atribución y del consentimiento, y el ruteo en el navegador lo hace GTM (D20). El portal sigue el mismo contrato sin compartir código. Si `flacso-analytics` está apagado, el sitio funciona igual.
6. **Meta Ads (lista técnica del 24/11).** Se suman la verificación urgente de `SubmitApplication` en el portal (L02a), el evento de **pago** (L12a–L12c) y un `external_id` estable por persona (L12d).
7. **Se cierra lo viejo:** las PR que el v8.3 daba por abiertas (#118, #122 del plugin y del tema, #123) ya están fusionadas, y también el #124 (rediseño de Más información en el admin). No hay PR abiertos en ningún repo.

## 2. Estado de partida (23/11)

| Bloque del v8.3 | Estado | Evidencia |
|---|---|---|
| E1 Seguridad | código hecho; falta lo de servidor | plugin #118, #119 (deploy excluye `scripts/`, `Requires PHP: 8.3`), #120 (A2, Q1); tema #120 (A1), #121 (A7, A8). Pendiente: evidencia del 404 de `scripts/` en producción, revisión de registros guardados con barra (Q1), Q10 |
| E2 Rendimiento | en curso (agy) | P3a parcial (tema #119), P2 parcial (tema #103). P1 sin hacer: `front-page.css:2-5` sigue con `.ttf`. M1 sin hacer |
| E3 Confirmaciones | parcial | R1 en el admin de consultas (plugin #122, #124). Sin hacer: A3a/A3b, A6 («Hola %s», `confirmacion-consulta.php:123`), A5, M3, M4, PC10 (ramas sin fusionar) |
| E4–E17 Sistema de diseño | piezas sueltas | C1 (tema #104), C4 académica (tema #114), un h1 en noticias y eventos (tema #107), FAQ sin JS (tema #109), PC8 (tema #122) |
| E18 Analítica | sin empezar | rama `fix/pedidos-informacion-conversiones-confirmadas` sin fusionar; su PR (plugin #98) cerrado |

### 2.1 Analítica: lo que hay hoy (verificado en código)

- **Carga.** El plugin carga el píxel de Meta (base en `wp_head`, `class-flacso-meta-tracking.php:298-319`) y los ayudantes `ga4-events.js`, `utm-links.js` y `meta-tracking.js`. **GA4 y GTM no los carga ningún repo**; en producción aparecen dos `gtag/js` (GT-WR4D6ZR y G-38FYG8W6BQ) y `gtm.js` (GTM-NCJBS38S) por página. El plugin solo llama a `gtag`/`dataLayer` si existen. El tema no carga rastreadores (solo llama a los ayudantes del plugin). El portal de preinscripciones carga su propio píxel y `gtag` con el mismo G-38FYG8W6BQ.
- **Eventos.** El `Lead` de «Más información» sale después de que el servidor guarda la solicitud (en /gracias, con `eventID` compartido con CAPI). `SubmitApplication` del portal sale después de que la API devuelve `submissionId`, con ese mismo id en Pixel y CAPI. Antes de confirmar se envían intenciones (`InfoRequestFormView`, `InitiateCheckout`, `form_start`, `form_submit_attempt`).
- **Atribución.** Hay **tres almacenes con reglas distintas**: `flacso_campaign_attribution` (`ga4-events.js`, 17 claves, una clave nueva reemplaza todo), `flacso_utm` (`utm-links.js`, 7 claves, `fbclid` solo no borra la campaña) y `flacso:utm` (portal, 7 claves, reemplaza todo). Mautic es primer contacto y solo recibe `source`, `medium`, `campaign` y `content`. El portal guarda las 7 claves en `campaign_tracking`, pero no se las pasa a Mautic.
- **Consentimiento.** No hay banner, Consent Mode ni puerta de consentimiento en ningún repo. Pixel, GA4 y CAPI se disparan sin consentimiento. El consentimiento existente es solo de comunicaciones (Mautic).
- **Meta Ads (verificado el 24/11).** `SubmitApplication` no aparece en las estadísticas del píxel desde el 10/11, aunque el código del portal (`origin/main` 1feebd8) lo envía por píxel y CAPI con el mismo `event_id` (`meta-capi.ts:100`). La causa probable está en producción: `.env.production.example` trae `META_CAPI_ACCESS_TOKEN=REEMPLAZAR_POR_EL_TOKEN_…` y sin token real el servidor no envía. El `external_id` no identifica a la persona: en el portal es el hash del id del envío (`meta-capi.ts:84`) y en el plugin una cookie aleatoria de 365 días (`meta-tracking.js:99-111`), así que Meta no une consulta y preinscripción. `InitiateCheckout` está escrito en un `onclick` dentro de `flacso-consultas.php:589` y `:1207`. **No existe evento de pago**: los pagos se registran en una planilla.
- **Contradicciones documentales.** `meta-capi-ispconfig-worker.md:43` y el comentario de `class-flacso-meta-tracking.php:295-296` dicen que `PageView` va por CAPI, y el código no lo encola. Integraciones (`class-flacso-integrations-settings.php:1302, 1308`) dice que cubre `SubmitApplication` (el plugin no lo emite) y que a Meta solo van datos del formulario (también van IP, agente de usuario, `fbp`/`fbc` y `external_id`).

### 2.2 UI: lo más visible que sigue abierto
Gracias con «Hola Tu consulta» (A6, H-A18); ofertas que anuncian «Próxima apertura» con la cohorte finalizada (PC10); errores por campo ocultos con estilo en línea en el formulario de consulta (`form-render.php:90-103`, H-A01); el buscador de la cabecera sin foco visible (H-A04) ni término conservado (A9); Contacto repite el título del formulario y los datos de contacto (PC12); la ficha móvil muestra dos llamados a preinscribirse antes del contenido; `id="inner-wrap"` duplicado en la ficha; etiquetas de acción mezcladas («Preinscribirme», «Inscribirme»); botones de prueba del admin que mandan correos reales sin confirmar (H-A08); pantallas de admin peores: Docentes, Autoridades (104 estilos en línea), Analítica/Meta (~750 líneas muertas).

## 3. Decisiones que se necesitan pronto

Se piden en el lote que las usa; mientras tanto vale el supuesto.

| # | Decisión | Lote | Supuesto |
|---|---|---|---|
| D18 | Regla de atribución: primer contacto, último o ambos | L06 | **ambos**: `first_touch` y `last_touch`, cada uno con fecha y URL de aterrizaje |
| D19 | Consentimiento: qué habilita Pixel, coincidencias avanzadas, GA4 y CAPI | L10 | se inventaría y no se cambia nada hasta decidir |
| D20 (nueva) | Quién rutea los eventos en el navegador: GTM (ya cargado en producción; marketing ajusta sin deploy) o código propio en `flacso-analytics` | L04a | **GTM**, con el contenedor exportado y versionado en `docs/analitica/gtm/` después de cada cambio |
| D23 (nueva) | Dónde se registra un pago para que llegue a Meta | L12b | **en el admin del portal** (botón «Registrar pago» en la preinscripción, que guarda fecha, monto y moneda y envía `Purchase` por CAPI); mientras tanto, la planilla se sube a mano como conversiones offline |
| D24 (nueva) | Convención de `offer_code`: igual al nombre del conjunto de anuncios (DG, MESYP…) en Meta, GA4 y Mautic | L04a | sí; se escribe en el contrato de eventos |
| D21 (nueva) | ¿Mautic recibe también `utm_term`, `utm_id`, `fbclid`, `gclid` y los campos de último contacto? | L08 | sí, en campos nuevos; los de primer contacto no cambian |
| D11 | Qué dice una oferta con la cohorte más reciente finalizada o cancelada | L13 | «Sin convocatoria vigente» |
| D4 | 404: precargar el buscador con el slug | L14 | no; accesos a Inicio, Oferta, Investigación y Contacto |
| D17 | Si las solicitudes se guardan en WordPress (textos contradictorios) | L15 | se renombra el texto visible; lo contradictorio no se toca |
| D22 (nueva) | Valor real de `palette1`: el `AGENTS.md` del tema dice `#2c4777` y en producción se midió `#1d3a72` (B-0046); y el botón principal: azul (D14 del v8.3) o amarillo de marca (B-0045) | L01 / L20 | se toma el valor medido en producción; se mantiene D14 hasta que el dueño elija |

## 4. Lotes

Convenciones: **Repo** P = plugin, T = tema, X = portal de preinscripciones, M = plugin de Mautic, D = solo documentación. **Tamaño**: S (≤ 150 líneas), M (≤ 400). Ningún lote supera M; si crece, se parte. «Hecho cuando» es lo que se verifica tras el despliegue.

### 4.0 En curso
| Lote | Qué | Repo | Notas |
|---|---|---|---|
| L00 | E2 del v8.2: portada y fichas más rápidas (P1, P2, P3a, P3c, P5, Q2, M1) | T, P | lo hace agy. En P5 **solo diagnóstico** de las cuatro cargas de analítica: no se quitan ni se unifican hasta L02 |

### 4.1 Arranque (documentación; va primero y es corto)
| Lote | Qué se nota | Incluye | Repo | Tam. | Hecho cuando |
|---|---|---|---|---|---|
| L01 | Una sola fuente de verdad para agentes y personas | v9 copiado a `docs/` del plugin y del tema; v8.3 marcado como catálogo; `AGENTS.md` del plugin deja de condicionar el push a `TAREAS-CI.md` (queda como histórico) y su «Vía 2» se alinea con §0.4; B-0040–B-0049 commiteadas con su procedencia y B-0051 fusionada (rama `docs/auditoria-documental-2026-11-23`); `HALLAZGOS.md` marca H-A09, H-A11 y H-B08 con su estado real; se corrigen las menciones a PHP 7.4 (`plan-t3.md`, `AGENTS.md` del tema §5) y el tope de subagentes (GUIA dice 10, el plan 3: vale 3) | D (P y T) | M | ninguna contradicción de las listadas en este punto queda en `main` |

### 4.2 Pista de analítica (prioridad 1)
Orden: L02a (ya) → L02 → L03 → L04 → L04a → L04b → L05 → (L06, L07, L08) → L09 → L10 → L11 → L12 → L12b → L12c → L12d. L12a no depende de nada y se puede hacer en cualquier momento. La pista va en paralelo con la de UI porque no comparte archivos, salvo `confirmacion-consulta.php` y `flacso-consultas.php`: los toca solo L04 y la pista de UI no entra en ellos hasta después.

**Arquitectura objetivo (desacople).** Los productores (tema, módulos del plugin, portal) solo emiten eventos del contrato `docs/analitica/eventos.md`: `dataLayer.push({ event: 'flacso.<hecho>', … })` en el navegador y `do_action( 'flacso/<hecho>', $evento )` en el servidor. El plugin `flacso-analytics` (repo nuevo o mu-plugin, se decide en L04b) es el único dueño de: píxel base, cola y worker de CAPI, almacén de atribución, `external_id` y consentimiento. GTM rutea en el navegador hacia GA4 y el píxel (D20). El portal sigue el contrato con su propio código y tests de contrato. Regla de cierre: fuera de `flacso-analytics` y del portal no queda ningún `fbq(`, `gtag(` ni `graph.facebook.com`.

| Lote | Qué se nota | Incluye | Repo | Tam. | Requiere | Hecho cuando |
|---|---|---|---|---|---|---|
| L02a | `SubmitApplication` vuelve a llegar a Meta | **Urgente, sin código (dueño o alguien con acceso al servidor del portal):** `META_PIXEL_ID` y `META_CAPI_ACCESS_TOKEN` definidos con valores reales (no el `REEMPLAZAR_POR_…` del ejemplo); producción con el commit 1feebd8 o posterior; una preinscripción de prueba en una oferta de prueba con `META_CAPI_TEST_EVENT_CODE`, que después se borra; la prueba queda marcada para no mezclarse con las reales | — (operación) | S | acceso al servidor del portal y a Events Manager | en «Probar eventos» llega `SubmitApplication` por navegador y por servidor con el mismo `event_id`, con su EMQ anotada |
| L02 | Dirección sabe qué se mide hoy y quién lo mide | **T0 parte 1 (solo lectura):** quién inyecta los dos `gtag/js` y el GTM (Site Kit, ajuste de Kadence, código en el contenedor); exportación del contenedor GTM-NCJBS38S; disparadores y conversiones configurados en GA4 y Meta; resultado en `docs/analitica/estado-actual.md` con la tabla «evento → quién lo dispara → cuándo → a qué destino» | D | S | acceso de lectura a GA4, GTM y Meta (dueño) | la tabla existe y cada fila tiene evidencia (captura o export) |
| L03 | Lo mismo, pero medido: qué llega de verdad | **T0 parte 2:** un recorrido de «Más información» y uno de preinscripción con GA4 DebugView y Meta Test Events, sin formularios reales (entorno local o formulario de prueba acordado); se anotan duplicados, faltantes y el `event_id` de cada envío | D | S | L02 | la tabla de L02 suma «llega / llega duplicado / no llega» |
| L04 | La página de Gracias dice la verdad y el Lead se cuenta una vez | A6 (saludo con nombre o sin él, nunca «Hola Tu consulta»; «qué sigue» y volver a la oferta) + T4/T5 en WordPress (`generate_lead` y `Lead` solo tras la confirmación, un `eventID` por solicitud en Pixel y CAPI); **primer hook del contrato:** `do_action( 'flacso/lead_confirmed', $evento )` después de guardar, sin quitar lo actual; antes, rescatar o cerrar la rama `fix/pedidos-informacion-conversiones-confirmadas` | P | M | L03 | un envío = un `generate_lead` y un `Lead` deduplicado; Gracias sin «Hola Tu consulta» (test); el hook se dispara una vez por solicitud guardada (test) |
| L04a | Meta, GA4 y Mautic hablan de lo mismo | **Contrato de eventos** `docs/analitica/eventos.md`: `flacso.offer_view`, `flacso.list_view`, `flacso.cta_click` (hoy `InitiateCheckout`), `flacso.form_view`, `flacso.lead_confirmed`, `flacso.application_confirmed`, `flacso.payment_confirmed`; parámetros (`offer_code` según D24, `content_category`, `form`, `event_id`), prohibición de datos personales en parámetros, equivalencia con nombres de GA4 y Meta; contenedor GTM exportado a `docs/analitica/gtm/` | D | S | L02, D20, D24 | el contrato cubre todas las filas de la tabla de L02/L03 |
| L04b | nada visible: la analítica deja de vivir dentro del plugin | **`flacso-analytics`**: se mueve `class-flacso-meta-tracking.php` con sus JS (píxel base, cola y worker de CAPI) y se escucha `flacso/lead_confirmed`; el plugin principal queda con un adaptador mínimo que no hace nada si `flacso-analytics` no está; decidir repo propio o mu-plugin y su despliegue | P (+ repo nuevo) | M (2 PR) | L04, L04a | con `flacso-analytics` apagado el sitio funciona y no sale ningún evento; encendido, la tabla de L03 da igual que antes |
| L05 | Integraciones deja de prometer lo que no hace | textos de Integraciones (`class-flacso-integrations-settings.php:1302, 1308`), comentario `class-flacso-meta-tracking.php:295-296` y `meta-capi-ispconfig-worker.md:43` alineados con el código (PageView no va por CAPI, el plugin no emite `SubmitApplication`, qué datos van a Meta) | P | S | L04b | ningún texto contradice el código (grep de las tres frases) |
| L06 | Una sola regla de campaña en el sitio | T1 en `flacso-analytics`: un solo almacén con `first_touch` y `last_touch` (fecha y URL de aterrizaje) que reemplaza a `flacso_campaign_attribution` y `flacso_utm`; el formato viejo se lee una vez y se migra; los campos ocultos del formulario envían ambos; el enlace a preinscripciones lleva `utm_*`, `fbclid`, `fbc` y `fbp` | P | M | D18, L04b | test JS del almacén (primera visita, segunda con UTM, solo `fbclid`, vencimiento a 30 días); una sola clave en `localStorage` |
| L07 | El portal sigue la misma regla | T1 en el portal: `flacso:utm` con la estructura de L06; `campaign_tracking` guarda primer y último contacto (migración nueva) | X | M | L06 | tests de Vitest del almacén y de la migración |
| L08 | Mautic recibe la campaña completa | T2: el payload de WordPress y el del portal mandan las claves acordadas (incluidas `utm_term`, `utm_id`, `fbclid`); el plugin de Mautic las guarda en campos nuevos sin pisar los de primer contacto | P, M, X | M (3 PR S) | D21; campos creados en Mautic (dueño) | un envío de prueba deja los campos completos en el contacto de prueba, sin registros parciales |
| L09 | Una sola puerta a GA4 y al píxel | T3 + Q6: los llamados del tema (7 archivos) y del plugin (`flacso-consultas.php`, `confirmacion-consulta.php`, `convenios-responsivos.php`, `listar-paginas.php`) pasan a `dataLayer.push` del contrato, **una familia por PR**; GTM rutea; se retiran la traducción Meta→GA4 duplicada y el respaldo del tema a `gtag`; `offer_code` presente en `Lead`, `InitiateCheckout` y `SubmitApplication` | P, T | M (3–4 PR S) | L04a, L04b | cada acción del recorrido genera un solo evento en DebugView y Test Events; test de contrato: fuera de `flacso-analytics` no hay `fbq(` ni `gtag(` |
| L10 | Consentimiento | T6 parte 1: inventario de cookies y almacenamiento (`flacso_external_id` 365 días, `_fbc`, almacenes de campaña) y propuesta para D19; parte 2, si se decide: Consent Mode y puerta para el píxel y CAPI en `flacso-analytics` y en el portal | D, luego P/X | S + M | D19 | con rechazo, ningún evento sale a Meta y GA4 usa el modo sin cookies (Test Events y DebugView) |
| L11 | El admin dice si cada integración está bien | T7 parte 1: indicador en Integraciones o Sistema (píxel, CAPI y su cola, GA4 detectado, Mautic, `flacso-analytics` activo) sin mostrar tokens; reemplaza la pantalla Analítica/Meta (~750 líneas muertas, H-J01) | P | M | L05 | cada integración muestra configurada / no configurada / con error; la pantalla vieja no existe |
| L12 | Verificación repetible | T7 parte 2: guion en `docs/analitica/verificacion.md` con resultado esperado para los recorridos de consulta, preinscripción y pago (evento una vez, parámetros, deduplicación, fallo de red) | D | S | L04–L09 | el guion se corrió una vez y su resultado está en la bitácora |
| L12a | Meta aprende de quien paga, ya | **Puente sin código:** la planilla de pagos se exporta como conversiones offline (`Purchase` con correo y teléfono, `offer_code`, monto, moneda y fecha) y se sube a Events Manager cada semana; guía paso a paso en `docs/analitica/pagos-offline.md` | D | S | — | una subida de prueba aceptada, con su tasa de coincidencia anotada |
| L12b | Los pagos quedan registrados en el portal, no solo en la planilla | **Registrar pago en el admin del portal** (D23): en cada preinscripción, botón «Registrar pago» con fecha, monto, moneda y referencia; tabla nueva `payments` con migración; listado y exportación CSV (reemplaza la planilla como fuente); permiso solo para administración | X | M | D23 | se registra un pago de prueba, aparece en el listado y en el CSV; tests de la ruta y la migración |
| L12c | El pago llega a Meta solo | `flacso.payment_confirmed`: al registrar un pago, el portal envía `Purchase` por CAPI (`action_source: system_generated`, `event_id` = id del pago, `value`, `currency`, `offer_code`, datos cifrados de la persona); reintento con la misma cola que `SubmitApplication` | X | S | L12b; L10 si D19 lo exige | en «Probar eventos» llega un `Purchase` por servidor con su `event_id`; L12a deja de hacer falta |
| L12d | Meta une consulta, preinscripción y pago de la misma persona | `external_id` estable por persona: hash del correo normalizado en `Lead`, `InitiateCheckout` (si hay correo conocido), `SubmitApplication` y `Purchase`; se retira el uso del id del envío (`meta-capi.ts:84`) y de la cookie aleatoria como `external_id` | X, P (`flacso-analytics`) | S | L10 (D19) | los cuatro eventos de una persona de prueba llevan el mismo `external_id` en Test Events |

### 4.3 Pista de UI (prioridad 1, en paralelo con la analítica)
Primero arreglos visibles sin tokens nuevos (L13–L19); después el sistema de diseño partido (L20 en adelante).

**Arreglos visibles inmediatos**
| Lote | Qué se nota | Incluye | Repo | Tam. | Requiere | Hecho cuando |
|---|---|---|---|---|---|---|
| L13 | Ninguna oferta anuncia una apertura que no existe | PC10: «Sin convocatoria vigente» en tarjeta y ficha cuando la cohorte más reciente está finalizada o cancelada; revisar antes las ramas `fix/hide-closed-or-missing-offer-cohort-20261021`, `fix/show-planned-academic-cohorts` y `fix/cancelled-seminar-editions-strict-20261021` (rescatar o cerrar) | T (y P si hace falta) | S | D11 | test con fixtures; el diplomado de Gestión de Centros Educativos deja de decir «Próxima apertura» |
| L14 | El buscador recuerda lo que escribiste y el 404 orienta | A9 (término en el campo de la cabecera), foco visible del buscador de la cabecera (H-A04), D4 (404 sin precarga y con cuatro accesos) | T, P | S | D4 | término conservado; foco visible a 360 y 1280 px; 404 con accesos |
| L15 | Ningún «consulta» en lo que ven los editores | R1 restante: Integraciones, Formulario general, títulos de los dos bloques del editor; **no** el formulario público ni la confirmación (los toca L04, se hace después) | P | S | D17 | grep de «consulta» visible en esos archivos solo con los identificadores permitidos |
| L16 | Los errores del formulario se ven | H-A01: los errores por campo dejan de estar ocultos con estilo en línea (`form-render.php:90-103`), con `aria-invalid` y `aria-describedby`; H-A02: el formulario de mailing muestra cargando, éxito y error | P | S | — | con un campo inválido se ve el error y el lector de pantalla lo anuncia |
| L17 | Contacto más corto y claro | PC12 (correo y teléfono una sola vez) y un solo título de formulario | T | S | — | a 360×640 el primer campo o un acceso directo queda en la primera pantalla |
| L18 | Ficha de oferta más clara en el celular | un solo llamado a preinscribirse antes del contenido, `id="inner-wrap"` duplicado resuelto (el salto al contenido funciona), etiqueta única «Preinscribirme» (H-G02/H-I02) | T | S | — | href de «Preinscribirme» idéntico antes y después; un solo `#inner-wrap` |
| L19 | Admin más seguro y pantallas peores mejoradas | H-A08: los botones de prueba piden confirmación; Autoridades sin sus 104 estilos en línea (mismo patrón que Más información: nativo de wp-admin + hoja propia) | P | M (2 PR) | — | ningún botón de prueba envía sin confirmar; 0 `style=""` en Autoridades |

**Sistema de diseño, partido** (mismo contenido que E4–E17 del v8.3, cada lote ≤ 1–2 PR)
| Lote | Qué se nota | Del v8.3 | Repo |
|---|---|---|---|
| L20 | nada visible: línea base y tokens; se adjunta al primer lote visible (L21) en el mismo día | U0b·1–4, U0b·6, U1·0 | T, P (arnés) |
| L21 | cabecera, cuerpo y pie con el mismo ancho; logo del pie siempre visible | U1·1b, PC7 | T |
| L22 | botón del sistema en la noticia | C5, C12 | T |
| L23 | migas y encabezado de sección en la noticia | C2, C3, PC1 (noticias) | T |
| L24 | evento con encabezado y barra que no tapa el foco | U1·1 evento, C13, Q11 evento | T |
| L25 | Novedades con tarjetas y paginación del sistema | C4 Novedades, C11 | T |
| L26 | FAQ y Contacto con ancho de lectura, migas y botones | U1·1 FAQ y Contacto, C10 si D3 | T |
| L27 | insignias de estado con texto en Formación y tipos | C6, C14 tarjetas | T |
| L28 | Programas con la tarjeta del sistema | U1·3b, Q9, D6 Programas | T |
| L29 | Seminarios: una hoja en vez de tres (sin cambio visual) | M2, U3.1 | T |
| L30 | Seminarios con buscador y filtros del sistema | C7a, C8, C9, PC13 | T |
| L31 | Formación con filtros por estado y modalidad | C8 en `/formacion/` | T |
| L32 | Equipo y docentes con tarjeta de retrato | U1·3c, C4 `--retrato`, Q8, P4b | T, P |
| L33 | Convenios con tarjeta de logo | C4 `--logo`, Q13, D6 Convenios | T, P |
| L34 | ficha de oferta reordenada (maqueta validada antes) | U0b·5, U1·3a, C17 | T |
| L35 | ficha de oferta partida en `template-parts` (sin cambio visual) | U3.3, U3.4 | T |
| L36 | carta en una hoja con índice | U3.2, C10, C13, C17 | T |
| L37 | seminario individual alineado a la ficha | U1·3a seminario, U3.5 | T |
| L38 | formularios accesibles y avisos | C7b, C15, P4a | T, P |
| L39 | buscador agrupado por tipo y libros con una tarjeta | C18, PC9, C4 `--libro`, PC11 | T |
| L40 | páginas institucionales, portada editable y sin Bootstrap global | E15, E16 y E17 del v8.3, cada una partida en sus PR al llegar | T, P |

### 4.4 Lo que no cambia de lugar
- E3 restante sin analítica ni UI visible (A3a, A3b, A5, Q3–Q5, M3, M4, M5): **mantenimiento**, un PR por ítem, se intercala cuando el lote siguiente toca el mismo archivo (M3 antes de L11; M5 nunca junto a un PR visual).
- Dueño en paralelo: P8, D8, D10, evidencia del 404 de `scripts/` en producción (E1), creación de campos en Mautic (L08), accesos de lectura a GA4, GTM y Meta (L02).
- Portal de preinscripciones: E1 (seis verificaciones) y E2a (`56f8ee6`, en rama) siguen su propia bitácora; solo L07, L08 y L10 tocan ese repo.

## 5. Reglas de ejecución (además de §4.1 del v8.3)

- **Un lote = una rama = 1–3 PR.** Si al empezar un lote se ve que pasa de M, se parte antes de escribir código y se anota en la bitácora.
- **Un lote abierto por pista.** Como máximo dos lotes en curso a la vez (uno de analítica y uno de UI) más L00 de agy; nunca dos lotes que toquen el mismo archivo.
- **Reserva de lotes (varias sesiones a la vez).** Antes de empezar un lote del plan (`Lnn`): (1) comprobar que nadie lo tomó: `gh pr list --state all --search "Lnn in:title"` en el plugin y en el tema, y ramas o worktrees con `lnn` en el nombre (`git branch -a --list "*lnn*"`, `git worktree list`); (2) si hay un PR abierto o fusionado, o una rama con ese lote, no se toma: se informa y se elige otro; (3) si está libre, se reserva **antes de escribir código**: rama `<tipo>/lnn-slug` desde `origin/main`, `git commit --allow-empty -m "Lnn: reserva"`, push y PR **en borrador** (`gh pr create --draft`) con título `Lnn: …` en cada repo que se vaya a tocar; (4) al terminar, `gh pr ready`. Un PR en borrador no despliega. Si el lote se abandona, se cierra su PR con un comentario que diga por qué.
- **Analítica: leer antes de cambiar.** Ningún lote de la pista de analítica cambia un evento sin la fila correspondiente de L02/L03. Los envíos de prueba nunca usan formularios reales del sitio (mandan correos y crean contactos en Mautic): entorno local o formulario de prueba acordado.
- **Sin PII nueva.** Ningún lote agrega datos personales a parámetros de eventos, URL ni almacenamiento del navegador.
- **Subagentes:** prompts en inglés; lector Haiku antes de cada implementador Sonnet; revisión final Opus. Cada prompt dice cuándo terminar y cuándo detenerse e informar.
- **Merge y despliegue:** según `AGENTS.md` §0 (lo hace una persona), salvo orden explícita del dueño en la conversación. Los lotes de riesgo medio se despliegan solos y se verifican en lectura pública antes del siguiente.

## 6. Orden sugerido de las próximas dos semanas

| Día | Analítica | UI |
|---|---|---|
| 0 (hoy) | **L02a** (token y versión del portal; sin código) y **L12a** (pagos offline, sin código) | — |
| 1 | L01 (documentación) | L13 (PC10) |
| 2 | L02 (con accesos del dueño) | L14 (buscador y 404) |
| 3 | L03 | L16 (errores del formulario) |
| 4 | L04 (Gracias + Lead + primer hook) | L17 (Contacto) |
| 5 | L04a (contrato de eventos) | L18 (ficha móvil) |
| 6–7 | L04b (`flacso-analytics`, 2 PR) | L15 (renombrado restante), L19 (admin) |
| 8 | L05 | L20 + L21 (línea base y cabecera/pie) |
| 9 | L12b (registrar pago en el portal, si D23) | L22 |
| 10 | L12c (`Purchase` por CAPI) o L06 (si D18) | L23 |

Después: L06–L09, L10 (con D19) y L12d, que depende del consentimiento.

Si una decisión no llega, se salta ese lote y se toma el siguiente de la misma pista sin esa dependencia.
