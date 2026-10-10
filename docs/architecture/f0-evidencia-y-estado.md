# F0 — Evidencia, hallazgos y puertas de salida

**Fecha:** 2026-10-10
**Rama:** audit/f0-baseline-20261010 (PR #62, borrador)
**Objetivo:** cartografiar el estado actual sin realizar operaciones en producción.

## Verificación automática ejecutada

Resultado de GitHub Actions del workflow **F0 Architecture Baseline**, primera ejecución #38072944738:

| Verificación | Resultado |
|---|---|
| PHP sintaxis con PHP 7.4.33 CLI en runner | 299 archivos, 0 errores |
| Pruebas estáticas y del inventario | 8/8 satisfactorias |
| Repository Guard | satisfactorio |
| Inventario generado | CSV + summary JSON descargables |
| Estado de producción (WP/FPM/PostgreSQL/Mautic) | NO inspeccionado |
| Integración real del plugin en WordPress | NO probado en esta ejecución |
| Restauración de respaldos | NO ensayada |
| Rendimiento real o cobertura ejecutable | NO medidos |

Conteos del scanner F0 (coincidencias candidatas, no declaraciones efectivamente registradas): **1.853** llamadas a hooks, puntos de entrada y API de configuración; **214** referencias entre módulos; **una** componente de dependencias posiblemente cíclica; **15** nombres de tipos PHP con declaraciones en más de un archivo (incluyendo stubs de tests).

La comparación con el inventario preliminar del PR #61 puede arrojar totales distintos: usa patrones y reglas de recuento diferentes, y la rama F0 añade el propio probe de staging.

## Observaciones específicas que requieren confirmación

### 16 módulos cargados vs 19 directorios con PHP

El bootstrap enumera 16 módulos. Se encontraron además directorios con código PHP en \`modules/instagram\`, \`modules/site\` y \`modules/telegram\`, cuyos inicializadores no aparecen en el método \`load_modules()\`. Esto **no prueba** que la funcionalidad esté inactiva: parte de esa implementación se carga físicamente desde \`main-page\` y \`core\`. Se deben mapear sus consumidores antes de cambiar el cargador.

### Declaración de clase repetida

\`Flacso_Main_Page_Unified_Settings\` se declara tanto en:
- \`modules/main-page/includes/class-flacso-main-page-editor-bridge.php\`
- \`modules/main-page/includes/class-flacso-main-page-unified-settings.php\`

El \`init.php\` inspeccionado normalmente carga el segundo archivo, no ambos. Cargar ambos sin guardas podría provocar error de redeclaración. **No fusionar ni borrar por ahora**; determinar si existen importaciones alternativas en plugins/temas/instancias.

Las otras duplicaciones detectadas incluyen clases \`WP_Error\`, \`WP_Post\` y dobles de entidades académicas definidos en tests aislados. Se requiere distinguir colisiones de producción de stubs deliberados.

### Posible acoplamiento circular

Una componente del grafo heurístico agrupa:
\`consultas\`, \`mailing\`, \`main-page\`, \`oferta-academica\`, \`seminarios\` y código \`shared\`.

El análisis no demuestra que exista un ciclo de inicialización en tiempo de ejecución. Se debe validar la relación mediante un parser PHP real y revisar invocaciones/hook callbacks.

### Archivos grandes en el inventario

Ejemplos prioritarios (aproximación por líneas):
- \`modules/main-page/sections/novedades-section.php\` — 2.717
- \`includes/core/class-flacso-integrations-settings.php\` — 2.341
- \`modules/main-page/includes/flacso-consultas.php\` — 2.202
- \`modules/consultas/includes/class-flacso-consultas-admin.php\` — 1.969
- \`modules/main-page/includes/class-flacso-main-page-admin.php\` — 1.791

Los tamaños no equivalen a defectos, pero justifican revisar responsabilidades y tests antes de refactorizar.

## Estado de las tareas F0

| ID | Estado | Evidencia y pendiente |
|---|---|---|
| AUD-001 | Parcial | Inventario de archivos ejecutado; reconciliar PR #61 con main |
| AUD-002 | Pendiente | Extractor F0 es heurístico; agregar parser AST verdadero |
| AUD-003 | Parcial | Dependencias y componente cíclica candidata; confirmar semántica |
| AUD-004 | Parcial | Inventario estático de hooks, REST, WP-CLI y cron; faltan métodos y permisos runtime |
| AUD-005 | Parcial | CPT, meta y shortcodes candidatos; faltan metadatos dinámicos y consumidores |
| AUD-006 | Parcial | Tres módulos no enumerados y puente duplicado; confirmar carga/uso |
| AUD-007 | Pendiente | Esquema, constraints e índices de PostgreSQL y datos WP staging sin inspeccionar |
| AUD-008 | Parcial | Runner con PHP 7.4.33; WP, PHP-FPM y extensiones del servidor pendientes |
| AUD-009 | Pendiente | Medición de latencia, carga, queries y procesamiento de cola pendiente |
| AUD-010 | Parcial | Contratos estáticos de entidades y API; pruebas funcionales de catálogo/cartas pendientes |
| AUD-011 | Parcial | Verificación estática de clases delivery/marketing; pruebas integradas con mocks y DB staging pendientes |
| AUD-012 | Parcial | Pruebas de existencia de firma y rutas; matriz real de permisos y pruebas negativas pendientes |

### Qué falta para cerrar la puerta G0

- Montar/confirmar staging realmente aislado y sin correo real.
- Recolectar registros runtime con \`.github/scripts/f0_runtime_probe.php\`.
- Ejecutar inspecciones **solo lectura** de esquema PostgreSQL y meta registradas, con control de acceso.
- Medir baseline real y probar escenarios de falla/rollback en staging.
- Incorporar análisis AST, grafo de consumidores y callbacks/métodos/permisos REST.
- Completar smoke tests funcionales y de autorización por rol.
- Revisar y resolver colisiones de clases y dudas sobre los tres módulos adicionales.

Ver \`docs/architecture/f0-staging-protocol.md\` para los pasos y restricciones.

**Decisión:** G0 todavía no se aprueba. Los resultados de CI prueban la parte estática, no la operación segura de producción. El PR debe mantenerse como borrador hasta revisar las evidencias.
