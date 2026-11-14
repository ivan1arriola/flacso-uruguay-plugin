# Plan integral: menú nativo de WordPress + centro de Consultas y analítica

**Fecha:** 2026-11-14  
**Estado:** Plan de implementación para revisión; ningún cambio funcional ejecutado.  
**Repositorio:** `ivan1arriola/flacso-uruguay-plugin`  
**Punto de partida auditado:** `main`, SHA `8035cc899a2ff1b2c2fa2822b01f69a6935bee08`  
**Ámbito:** navegación wp-admin, consultas académicas, atribución de campañas, datos e historial de personas, pruebas y despliegue.

> Este documento **sustituye como decisión de producto** la organización global de wp-admin de `docs/superpowers/specs/2026-10-31-wp-admin-menu-design.md` y la idea de mostrar seis páginas de Consultas bajo FLACSO de `docs/superpowers/specs/2026-10-10-consultas-pages-redesign-design.md`. Los archivos históricos no se borran; este plan explicita qué decisiones dejan de estar vigentes.

## 1. Objetivos y límites

### Objetivos aprobados

1. **Restaurar el menú estándar de WordPress.** Eliminar el mega-submenú FLACSO que reúne opciones del núcleo y de terceros. FLACSO agregará exclusivamente sus propias entradas a la navegación nativa mediante APIs estándar.
2. **Construir «Consultas y analítica»** como módulo propio de wp-admin, priorizando solicitudes de más información, ofertas académicas y atribución a campañas publicitarias.
3. **Conservar los datos y el trabajo operativo**: bandeja, detalle, estados de email y Mautic, reintentos seguros, filtros, comparaciones y exportación.
4. **Agregar Personas**: búsqueda autorizada y ficha histórica que reúne consultas de ofertas, seminarios y consultas generales de la misma dirección de correo.
5. **Desacoplar y probar** registro de menús, renderizado, análisis y consultas SQL para facilitar mantenimiento.

### Fuera del primer despliegue

- No sustituir la interfaz administrativa de WordPress ni introducir React/Next.js o un «shell» de navegación alternativo.
- No incluir por defecto todas las áreas del núcleo o de plugins terceros en un menú FLACSO.
- No cambiar reglas de envío, contratos de Mautic, consentimiento comercial, cola transaccional, retención ni contenido histórico.
- No declarar rendimiento de Meta Ads, CPL/CPA, impresiones, clics o inversión sin integración y datos verificables de Meta.
- No crear un CRM de gestión humana de casos ni vincular automáticamente preinscripciones: son posibles ampliaciones, no bloqueantes.
- No inferir identidad de personas por coincidencia de nombre, teléfono o IP.
- No ejecutar migraciones, envíos ni tareas de reintento contra producción como parte del refactor visual.

## 2. Diagnóstico de código y riesgos

- `includes/core/class-flacso-admin-menu-organizer.php` registra `organize_menu()` en `admin_menu` con prioridad 1000, mueve secciones de WordPress y de plugins a la raíz `flacso-panel` y agrega encabezados falsos. El resultado es el menú excesivamente largo observado.
- `includes/core/class-flacso-admin-panel.php` crea el menú raíz FLACSO con `add_menu_page()`, añade «Resumen» y un grupo de accesos en la barra superior; su `sort_submenus()` oculta algunas rutas académicas subordinadas.
- Muchos módulos emplean `FLACSO_Admin_Panel::PAGE_SLUG` como padre en `add_submenu_page()` o como `show_in_menu` en `register_post_type()`. Quitar solamente el menú raíz dejaría páginas huérfanas.
- `includes/core/class-flacso-academic-assistant.php` usa `simplify_menu()`, `guard_screens()`, capacidades y límites de contenido del rol `gestion_web`. Se debe mantener la seguridad por permisos, aunque cambie la navegación.
- `modules/consultas/includes/class-flacso-consultas-admin.php` registra seis páginas y conserva un segundo menú de pestañas internas. La capa administrativa agrupa renderización, filtros, acciones AJAX y estilos inline.
- `includes/database/repositories/class-flacso-inquiry-analytics-repository.php` consulta `offer_inquiries`, `seminar_inquiries` y `general_inquiries`. Las estadísticas de campañas cuentan eventos, mientras otros resúmenes deduplican por correo + nombre de oferta; esas cifras no son intercambiables.
- El agrupamiento actual de bandeja es por `item_col` + `emailNormalized`, dentro de una sola tabla; los hijos de cada grupo están limitados a 25. No es una ficha de persona a través de todas las tablas.
- `modules/formularios/includes/helpers.php` puede utilizar `gclid`/`gbraid`/`wbraid`/`fbclid` como último recurso de `campaign_external_id`. Un identificador de clic **no** es un ID real de campaña; usarlo como clave fragmenta las estadísticas.
- `modules/consultas/services/class-flacso-offer-campaign-status.php` consulta por lotes el estado real en Mautic y usa caché: esos estados no deben confundirse con atribución publicitaria ni bloquear el dashboard.
- Hay pruebas de contrato acopladas al diseño antiguo, especialmente `tests/admin-menu-organizer-contract-test.php` y `tests/consultas-pages-routing-contract-test.php`: deberán reemplazarse por contratos del menú nativo sin desactivar controles de seguridad.

## 3. Diseño de navegación decidido

### Menú nativo de WordPress

- Conservar Entradas, Medios, Páginas, Comentarios, Apariencia, Plugins, Usuarios, Herramientas y Ajustes en su posición y estructura habituales, según rol/capacidades y otros plugins activos.
- **Eliminar la reubicación global** de menús del núcleo y de terceros. No crear grupos sintéticos «Inicio FLACSO / Formación / Sitio / Sistema» ni manipular a mano `$menu`/`$submenu` de terceros.
- Quitar el **toplevel «FLACSO»** como contenedor universal. Conservar la pantalla `flacso-panel`, si se retiene, como acceso bajo **Escritorio** (`index.php`), con su URL canónica compatible. Eliminar el extenso grupo de accesos personalizados del menú superior y respetar el enlace nativo del sitio.
- Los CPT propios (`programa-academico`, `oferta-academica`, `seminario`, `docente`, `convenio`, `tabla-precio`, `evento`, FAQ y demás) deben usar menús nativos `show_in_menu => true` o un padre **específico y estable** de su dominio. Para `cohorte` y `edicion` conservar acceso contextual desde oferta/seminario sin exponerlas como secciones independientes si no corresponde.
- Reubicar las pantallas de opciones del plugin con `add_submenu_page()` bajo un padre nativo o propio del dominio: `options-general.php` (ajustes e integraciones), `tools.php` (utilidades), `edit.php?post_type=page` (portada), `edit.php?post_type=oferta-academica` (textos académicos) o el CPT apropiado. Definir la matriz final tras inventario de todas las llamadas; no asumir que cualquier slug acepta todos los permisos.
- Un **único menú superior propio «Consultas y analítica»**, registrado mediante `add_menu_page()`, con submenús **nativos**: Resumen, Solicitudes, Campañas y Personas. «Ofertas» será un desglose de Resumen y Campañas; «Comparación» y «Oferta y país» se integran en el análisis, no generan nuevas entradas permanentes. CSV será una acción visible sólo con permisos de gestión.
- Si existe una página propia de Preinscripciones o Comunicaciones, registrarla como opción propia y pertinente, nunca dentro de un contenedor que absorba el resto de WordPress.

### Matriz de rutas orientativa (validar inventario antes de implementar)

| Función | Ubicación en menú estándar | URL compatible |
| --- | --- | --- |
| Resumen institucional FLACSO | Escritorio > Resumen FLACSO (opcional) | `admin.php?page=flacso-panel` |
| Ofertas, programas, seminarios, docentes, convenios, tablas | Entradas/CPT propios en menú principal | `edit.php?post_type=...` |
| Portada del sitio | Páginas > Portada | `admin.php?page=flacso-main-page` |
| Textos de la carta académica | Ofertas académicas > Textos de la carta | `admin.php?page=flacso-carta-textos` |
| Ajustes de sistema, integraciones y Meta | Ajustes o Herramientas, según función y permisos | Slugs actuales |
| Consultas - Resumen | Consultas y analítica > Resumen | `admin.php?page=flacso-consultas` |
| Consultas - Solicitudes | Consultas y analítica > Solicitudes | `admin.php?page=flacso-consultas-solicitudes` (nuevo) |
| Consultas - Campañas | Consultas y analítica > Campañas | `admin.php?page=flacso-consultas-campanas` |
| Consultas - Personas | Consultas y analítica > Personas | `admin.php?page=flacso-consultas-personas` (nuevo) |
| Exportar CSV | Acción contextual autorizada, sin submenú obligatorio | `admin.php?page=flacso-consultas-exportar` / endpoint existente |

**Migración de rutas:** `flacso-consultas` pasará a Resumen. Los slugs históricos `flacso-consultas-resumen`, `flacso-consultas-oferta-pais`, `flacso-consultas-comparacion`, `flacso-consultas-campanas`, `flacso-consultas-exportar` y enlaces `?tab=...` deben seguir resolviendo o redirigir de forma segura al destino funcional correspondiente, conservando filtros compatibles, sin bucles y sin elevar permisos. Los marcadores de bandeja histórica deben llevar a Solicitudes, no al Resumen por defecto.

## 4. Diseño funcional del centro de Consultas

### Filtros compartidos

- Rango de fechas (últimos 30 días por defecto), tipo de consulta (oferta/seminario/general), programa/ID de oferta, cohorte cuando corresponda, canal receptor (web, Instagram, Messenger, otros), fuente publicitaria, campaña, país y búsqueda contextual.
- Filtros serializables en URL sin datos personales (`from`, `to`, `offer_id`, `campaign_key`, `channel`, `country`, etc.). Mantener selección al pasar de gráfico a tabla y ofrecer «Limpiar filtros».
- Fechas interpretadas en `America/Montevideo`, no por el UTC del servidor. Revisar el hecho de que `createdAt` es UTC mientras algunos `inquiryAt` se guardan como hora local; normalizar sin desplazar datos históricos incorrectamente.
- Validar límites de rango y de paginación. Las comparaciones usarán ventanas de igual duración y mostrarán «no calculable» cuando el denominador sea cero.

### Resumen (home)

1. KPI de eventos/solicitudes, personas únicas, personas-oferta únicas, ofertas con demanda, solicitudes atribuidas y porcentaje de cobertura; incidencias de entrega como panel auxiliar, sin confundir con marketing.
2. Evolución diaria con período anterior y promedio móvil opcional.
3. Rankings por oferta (ID canónico) y campaña publicitaria.
4. Desglose por canal de recepción, fuente publicitaria y país.
5. Tabla de solicitudes recientes y navegación «Ver solicitudes» con los mismos filtros.
6. Estados vacíos, carga y error de datos diferenciados: sin registros no equivale a PostgreSQL inaccesible.

### Solicitudes (bandeja)

- Preservar lista paginada, modo individual y agrupación, búsqueda, datos de contacto, detalle, estado de envío, estado de Mautic, reintentos autorizados y exportación.
- Mostrar canal receptor, fuente/medio publicitario, campaña identificada y calidad de atribución sin duplicar conceptos.
- Cada persona del listado enlaza su ficha; cada campaña/oferta enlaza al correspondiente filtro agregado.
- El detalle operativo debe seguir distinguiendo «aceptado por proveedor» de «entregado», y el seguimiento automatizado de una respuesta humana. No inventar estado «atendido» ni tasa de lectura.

### Campañas (atribución)

- Ranking de campañas: plataforma, ID verdadero cuando exista, nombre, fuente, medio, eventos, personas únicas, personas-oferta únicas, ofertas consultadas y evolución.
- Desglose de ofertas por campaña y de campañas por oferta; clic en filas para explorar sus solicitudes.
- Separar «Sin campaña identificada» de «Orgánico». Mantener exclusiones de campañas de prueba como preferencia analítica auditada, sin borrar eventos.
- Identificar la calidad de atribución: ID verificado, solo nombre/UTM, fuente sin campaña o sin datos.
- No presentar CPL/CPA ni inversión hasta disponer de una API publicitaria enlazada y concordancia verificable.

### Personas (primera versión funcional)

- Buscar por correo o nombre/apellido; los nombres sirven para localizar candidatos, **no** para fusionar identidades.
- Identidad provisional: `lower(trim(emailNormalized/email))` válida. Las coincidencias exactas de correo permiten relacionar eventos en tablas distintas; correos distintos permanecen como perfiles separados salvo un vínculo explícito futuro.
- Ficha de solo lectura: nombre y correo disponible, teléfono, país, profesión y formación cuando estén presentes, primera/última consulta, total de eventos y ofertas distintas.
- Línea de tiempo **completa y paginada** de `offer_inquiries`, `seminar_inquiries` y `general_inquiries`, con fechas, oferta/seminario, canal, campaña, mensaje original disponible, acuse y seguimiento. La ficha no se limita a los 25 hijos de la agrupación actual ni al rango del dashboard.
- Click desde la línea de tiempo al registro original. Si faltan campos en una tabla, mostrarlos como «no registrado» sin imputación ni inferencia.
- No exponer email, teléfono ni datos personales en query strings, logs, payloads de error, analítica pública o cachés compartidas. Resolver selección por POST autenticado o ID opaco ligado a sesión/autorización; definir una implementación comprobable antes de lanzar. No indexar ni publicar rutas de Personas.
- Acceso según capacidad de lectura de consultas; exportación y mutaciones sólo bajo capacidad de gestión. Evaluar si el rol necesita límites por oferta también en consultas, antes de habilitar la ficha longitudinal.

## 5. Semántica de medición y atribución

### Contrato de métricas

| Métrica | Regla |
| --- | --- |
| Eventos de consulta | `COUNT(*)` en registros válidos del conjunto filtrado |
| Personas únicas | `COUNT(DISTINCT email_normalized)` (con normalización definida y sin valores vacíos) |
| Personas-oferta únicas | Distintos `(email_normalized, entity_kind, canonical_wp_id)`; fallback etiquetado sólo para registros sin ID |
| Ofertas consultadas | Distintos `(entity_kind, canonical_wp_id)` con registros |
| Con campaña identificada | Eventos con ID real o combinación de UTM suficiente, según clasificador |
| Cobertura de campaña | Eventos con campaña identificada / total de eventos del mismo universo |
| Sin campaña identificada | Eventos restantes; no equivalen automáticamente a tráfico orgánico |
| Variación | Diferencia absoluta y porcentual entre ventanas de duración igual; no dividir entre cero |

- Documentar si «personas» incluye también consultas generales; presentar subtotal «personas-oferta» sólo para entidades académicas.
- Nunca sumar directamente `personas únicas` por campaña u oferta para obtener el total global: una misma dirección puede aparecer en varias agrupaciones.
- Mantener nombres de campos y comportamientos legacy de las APIs hasta que exista una migración explícita de consumidores.

### Normalización

- Separar `channel`/origen del mensaje (Web, Instagram, Messenger, etc.) de `campaignSource` y `campaignMedium`.
- Separar `campaign_id` / `adset_id` / `ad_id` de `click_id` (`fbclid`/`gclid`/`gbraid`/`wbraid`). Los clicks no son claves de agrupación de campaña.
- Usar ID real de campaña **junto a plataforma** cuando exista; en su ausencia usar una clave normalizada y marcada como inferida por nombre/UTM; no alterar snapshots históricos ni inventar IDs.
- Examinar las capas de captura de landing/UTM y la API del Meta Bot, que hoy identifica el canal pero no garantiza la identificación de anuncio ni campaña de un DM. Recoger esos IDs sólo cuando la plataforma los entrega, con su origen/procedencia.
- Si se necesita persistir campos nuevos, proponer una migración separada, reversible y con respaldo. La v1 puede usar proyecciones de lectura y campos ya existentes.

## 6. Diseño de software

### Separación de responsabilidades

- `FLACSO_Consultas_Admin`: registro de menú/páginas, autorización, enrutamiento, nonces y carga de assets; sacar SQL y marcado extenso.
- `FLACSO_Inquiry_Dashboard_Service` (nuevo): reglas de KPI, filtros, períodos y datos de gráficos.
- `FLACSO_Inquiry_Attribution_Normalizer` (nuevo): normalización de proveedor/fuente/medio/campaña/click y nivel de confianza, sin sobrescribir origen.
- `FLACSO_Inquiry_Person_History_Repository` (nuevo): `search_people()`, `get_person_summary()` y `get_person_timeline()`.
- `FLACSO_Inquiry_Analytics_Repository`: ampliar por consultas agregadas SQL y paginación, conservando endpoints y contratos usados actualmente.
- `modules/consultas/views/`: `dashboard.php`, `inquiries.php`, `campaigns.php`, `people.php`, `person-detail.php` y parciales reutilizables.
- `modules/consultas/assets/css/` y `assets/js/`: estilos aislados del admin, responsive y accesibles; eliminar progresivamente CSS/JS inline del archivo de 2.200+ líneas.
- Server-render PHP + JavaScript progresivo. Si hay solicitudes AJAX/REST para filtros, verificar `current_user_can` y nonce en cada operación. No habilitar APIs públicas con PII.

### Rendimiento

- SQL parametrizado, whitelist de tablas/campos y agregaciones con `GROUP BY`/`COUNT(DISTINCT ...)` y `UNION ALL` paginado para el historial.
- Medir `EXPLAIN (ANALYZE, BUFFERS)` en entorno seguro antes de modificar índices; no asumir que existen índices ideales.
- Mantener límites de página, evitar N+1, cachear solo agregados sin PII por clave de filtros y ventana corta; mostrar errores genéricos sin volcar SQL/payload.
- No disparar llamadas a Mautic ni campañas por cargar gráficos. El estado de una consulta puede refrescarse por servicio existente de forma acotada.

## 7. Secuencia de ejecución: PR separados

### PR 1 — Restaurar WordPress nativo

**Objetivo:** retirar el mega-menú sin perder páginas.

**Archivos previstos:** `includes/core/class-flacso-admin-menu-organizer.php` (desacoplar/desactivar carga y retirar según uso), `includes/core/class-flacso-admin-panel.php`, registradores CPT en `modules/oferta-academica/`, `modules/seminarios/`, `modules/docentes/` y otros registradores de `show_in_menu`; ajustes en `modules/main-page/`, `modules/formularios/`, `modules/mailing/`, `includes/core/`; pruebas de menú y roles.

- [ ] Inventariar **todas** las referencias a `FLACSO_Admin_Panel::PAGE_SLUG`, `flacso-panel`, `show_in_menu`, `add_menu_page`, `add_submenu_page` y accesos directos internos.
- [ ] Registrar una tabla final de pantalla → padre nativo → slug → capability → callback.
- [ ] Escribir pruebas que exijan la presencia normal de los menús de WordPress y prohíban mover los menús de terceros.
- [ ] Registrar cada CPT y pantalla en el padre elegido **antes** de desactivar el organizador y el menú raíz.
- [ ] Mantener el resumen FLACSO bajo Escritorio (si se conserva) sin grupo custom en barra superior.
- [ ] Ajustar `FLACSO_Academic_Assistant::simplify_menu()` y guardias sin ampliar permisos ni ocultar inadvertidamente secciones autorizadas.
- [ ] Sustituir/revisar `tests/admin-menu-organizer-contract-test.php`, `tests/admin-panel-contract-test.php` y permisos por pruebas del comportamiento deseado. No dejar los tests antiguos desactivados sin reemplazo.
- [ ] Verificar administradores y `gestion_web`: CPT, edición, pantallas de opciones, WordPress core y plugins de terceros.
- [ ] Comprobar que no haya 404/«No tienes permisos» en rutas antiguas.

**Done:** menú núcleo y terceros normal; FLACSO agrega sólo menús propios; toda función tiene enlace de acceso o URL válida; roles sin regresiones.

### PR 2 — Contratos de datos, analítica y atribución

**Objetivo:** indicadores consistentes antes de rediseñar la UI.

**Archivos previstos:** `includes/database/repositories/class-flacso-inquiry-analytics-repository.php`; nuevos servicios de dashboard y normalización; tests de analítica y atribución.

- [ ] Auditar columnas reales de las tres tablas y fechas (especialmente generales y campos de campaña).
- [ ] Escribir fixtures con repetición de correo, múltiples ofertas, canales, clics distintos de la misma campaña, sin ID de oferta, datos vacíos y fechas de borde UY.
- [ ] Definir DTO de filtros y respuesta de `get_overview`, `get_daily_series`, `get_offer_ranking`, `get_campaign_ranking`, `get_channel_breakdown`, `get_attribution_quality`.
- [ ] Extraer normalizador de atribución; impedir que `fbclid`/`gclid` sean tratados como ID de campaña; mantener datos históricos originales.
- [ ] Implementar agregados SQL y paginación; revisar tests SQLite/PG y coherencia de totales entre vistas.
- [ ] Medir rendimiento y planificar índices sólo si el plan de consultas lo justifica.

**Done:** cada KPI tiene definición, universo y prueba; campaña por clic no se fragmenta; métricas sin regresiones.

### PR 3 — Centro de Consultas en menú nativo

**Objetivo:** interfaz integrada para análisis y solicitudes.

**Archivos previstos:** `modules/consultas/includes/class-flacso-consultas-admin.php`, `modules/consultas/views/`, CSS/JS, `modules/consultas/init.php` y pruebas de rutas/layout.

- [ ] Registrar `Consultas y analítica` con `add_menu_page` y cuatro submenús nativos planificados, con capability correcta.
- [ ] Convertir `flacso-consultas` en Resumen y agregar Solicitudes; mantener Campañas y preparar Personas sin exponer una página vacía al usuario.
- [ ] Implementar filtros globales, KPIs, gráficos, rankings, drill-down a bandeja y tabla de eventos recientes.
- [ ] Mantener detalles, modo agrupado/individual, estados, Mautic y reintentos idempotentes existentes.
- [ ] Migrar ofertas/país/comparación a componentes del dashboard; exportación como acción protegida.
- [ ] Añadir redirecciones seguras de slugs antiguos y `tab=`, con rutas compatibles y prueba anti-loop.
- [ ] Aplicar estilos del admin, navegación por teclado, foco, contraste, respuesta móvil y estados vacíos/error.

**Done:** desde una campaña/oferta se llega a las solicitudes que la forman; se conserva el trabajo de bandeja y el menú sigue nativo.

### PR 4 — Personas e historial transversal

**Objetivo:** buscar una persona y consultar todo su historial almacenado.

**Archivos previstos:** nuevo `FLACSO_Inquiry_Person_History_Repository`, vistas/persona, AJAX/REST interno autorizado, pruebas de identidad y seguridad.

- [ ] Confirmar disponibilidad de correo normalizado y datos de contacto en las tres fuentes; decidir fallback por tabla.
- [ ] Crear búsqueda paginada por correo/nombre con resultados desambiguados, no fusionados por nombre.
- [ ] Agregar resumen de persona y `UNION ALL` cronológico paginado sin límite artificial de 25 eventos.
- [ ] Enlazar persona desde Solicitudes y viceversa, preservando la identidad del evento y sus atributos originales.
- [ ] Elegir e implementar navegación sin email ni PII en URL; proteger lectura individual y búsquedas por permisos/nonce.
- [ ] Probar dos personas homónimas, mismo correo en varias fuentes, emails distintos, faltantes, paginación profunda, acceso no autorizado e inyección.
- [ ] Revisar acceso longitudinal del rol `gestion_web`: no extender información a ofertas fuera de su alcance sin una decisión explícita de autorización.

**Done:** una persona con varias consultas en distintas fuentes tiene una única ficha por correo, sin fusiones indebidas ni exposición de PII.

### PR 5 — Limpieza, documentación y consolidación

- [ ] Eliminar funciones/estilos/tabs obsoletos solo tras confirmar que no hay consumidores ni rutas rotas.
- [ ] Actualizar `README.md` y documentación administrativa: menú nativo, definiciones KPI, filtros, Personas, permisos y fallback ante caídas.
- [ ] Revisar todos los enlaces internos de admin, menú superior y accesos de roles.
- [ ] Ejecutar suite completa, lint PHP y controles de front/admin.
- [ ] Probar con datos sintéticos los tres tipos de consulta y una campaña con atribución incompleta.
- [ ] Verificar que no se acceda a PII desde caché compartida, logs, API pública o usuarios sin permisos.

### Ampliación futura (otro proyecto/PR)

Integración Meta Ads para coste, impresiones, clics y campañas/anuncios verificables; vincular eventos de consulta con ad/adset/campaign IDs reales. No bloquear PR 1–5 ni simular datos.

## 8. Contratos y escenarios de regresión obligatorios

| Escenario | Resultado exigido |
| --- | --- |
| Usuario administrador abre wp-admin | Menús normales de WordPress y plugins de terceros; no mega-menú FLACSO |
| Usuario `gestion_web` | Sólo menús y acciones permitidos; protección también al escribir URL directa |
| Enlaces existentes a `flacso-panel`/CPT | Pantalla accesible y callback correcto |
| Enlace `flacso-consultas&tab=historico` | Redirige a Solicitudes conservando filtros compatibles |
| Enlace `flacso-consultas-campanas` | Abre Campañas bajo menú nativo |
| CSV con permiso sólo lectura | Rechazado en UI y controlador |
| Varios eventos de la misma persona/oferta | Eventos N; persona única 1; persona-oferta única 1 |
| Una persona consulta dos ofertas | Persona única 1; personas-oferta únicas 2 |
| `fbclid` distinto por clic, misma campaña UTM | No genera campañas ficticias por click ID |
| Sin campaña disponible | Mantener evento y señalar «sin campaña identificada» |
| Persona con historial en tres tablas | Cronología completa, paginada y enlazada |
| Dos homónimos con distinto correo | Fichas separadas |
| Mautic fuera de servicio | Dashboard funciona, estado operativo no se falsea |
| PostgreSQL no disponible | Aviso genérico, nunca una falsa cifra de cero |
| Bordes de fecha en UY | Fecha incluida/excluida correctamente sin desplazamiento de día |
| Usuario sin permiso de consultas | No obtiene datos de personas ni desde URL ni desde AJAX/REST |

Tests existentes de partida: `tests/admin-menu-organizer-contract-test.php`, `tests/admin-panel-contract-test.php`, `tests/gestion-web-inquiries-contract-test.php`, `tests/consultas-pages-routing-contract-test.php`, `tests/consultas-pages-layout-contract-test.php`, `tests/consultas-inbox-view-contract-test.php`, `tests/consultas-inbox-ux-contract-test.php`, `tests/consultas-campaign-state-test.php`, `tests/inquiry-analytics-query-plan-test.php`, más pruebas de repositorios, Mautic, CLI, exportación y roles. Crear tests nuevos para normalización, KPI, fechas y Personas.

## 9. Despliegue, observabilidad y rollback

1. Trabajar en ramas/PR separados, en secuencia. No mezclar el retorno al menú nativo con la modificación del modelo estadístico en un único deploy.
2. Antes de cada merge: revisar el diff, PHP lint, suite de tests (no solamente contratos de cadena), permisos de editor y administrador y snapshots visuales de escritorio/móvil.
3. Verificar el SHA exacto del merge, GitHub Actions, artefacto/despliegue y estado de WordPress **por separado**; un CI verde no prueba que producción esté actualizada.
4. PR 1: registrar rutas nuevas conservando viejas y habilitar menú nativo después; rollback por reversión del cambio de menú, sin tocar contenido ni DB.
5. PR 2–4: preservar tablas y consultas originales. Si se requiere migración posterior, respaldo restaurable, SQL versionado, despliegue por fases y rollback documentado.
6. Pruebas post-deploy autenticadas: admin, Gestión web, CPT, enlaces antiguos, dashboard, una campaña atribuida, persona histórica y denegaciones de acceso.
7. Monitorizar durante el despliegue: errores PHP/wp-admin, consultas SQL lentas, carga del dashboard y errores AJAX. No registrar payloads personales.

## 10. Definición de completo

- [ ] Menú de WordPress recuperado, sin contenedor universal FLACSO ni reagrupación de terceros.
- [ ] Todas las pantallas y entidades FLACSO ubicadas correctamente sin perder rutas, callbacks ni permisos.
- [ ] Centro Consultas y analítica navegable desde menú nativo con Resumen, Solicitudes, Campañas y Personas.
- [ ] Métricas verificadas, atribución sin IDs de clic tomados como campañas y drill-down consistente.
- [ ] Historial individual transversal, seguro, completo y paginado.
- [ ] Exportación y acciones mutables conservadas con permisos y nonces.
- [ ] Pruebas automáticas y visuales pasan, documentación actualizada, despliegue comprobado y rollback disponible.

**Orden recomendado:** PR 1 (menú) → PR 2 (datos) → PR 3 (dashboard) → PR 4 (Personas) → PR 5 (limpieza). Cada entrega puede revisarse y revertirse de forma independiente; ninguna de ellas debe dar por finalizada una integración Meta Ads que aún no existe.
