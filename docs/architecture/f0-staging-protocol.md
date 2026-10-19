# F0 — Protocolo de validación segura de staging

Fecha inicial: 2026-10-10. La F0 de inventario se realiza sin modificar WordPress, PostgreSQL ni APIs externas.

## Requisitos previos

1. Confirmar instalación **independiente** de WordPress en staging con base de datos propia y datos sintéticos o anonimizados.
2. Confirmar \`WP_ENVIRONMENT_TYPE=staging\` y ausencia de referencias a credenciales de producción.
3. Desactivar o simular envíos SMTP/Mautic/Meta y procesos de cron que producen efectos externos. Una instalación de staging no debe recibir los webhooks reales.
4. Validar que backups estén disponibles y que el responsable de operaciones aprueba las comprobaciones.
5. Registrar SHA del checkout y hora del diagnóstico; no capturar ninguna contraseña, token, respuesta de consulta ni dato personal.
6. El probe PHP de este PR se niega a ejecutarse fuera de WP-CLI o si el ambiente es producción/desconocido.

## Comprobación A: WordPress y PHP-FPM

Desde el checkout del repo en staging (no desde producción):

\`\`\`bash
git rev-parse HEAD
wp --info
wp core version
FLACSO_F0_STAGING=1 wp eval-file .github/scripts/f0_runtime_probe.php > f0-runtime.json
\`\`\`

El JSON indica la versión PHP **CLI**, WordPress y los registros efectivamente cargados: CPT, taxonomías, metadatos registrados, shortcodes, rutas y nombres de cron. No exporta valores de opciones ni argumentos de cron.

**Aparte** comprobar PHP-FPM del vhost, su extensión PDO_pgsql y su versión (por consola de administración de ISPConfig o una inspección segura del pool). PHP CLI y FPM pueden diferir; anotar ambas versiones en un reporte privado. No publicar \`phpinfo()\` en internet.

## Comprobación B: inventario de PostgreSQL (solo staging)

Con credenciales de **solo lectura** y una sesión a la BD staging:

\`\`\`sql
SHOW server_version;

SELECT table_schema, table_name
FROM information_schema.tables
WHERE table_schema NOT IN ('pg_catalog', 'information_schema')
  AND table_type = 'BASE TABLE'
ORDER BY table_schema, table_name;

SELECT table_schema, table_name, column_name, data_type, is_nullable
FROM information_schema.columns
WHERE table_schema NOT IN ('pg_catalog', 'information_schema')
ORDER BY table_schema, table_name, ordinal_position;

SELECT schemaname, tablename, indexname
FROM pg_indexes
WHERE schemaname NOT IN ('pg_catalog', 'information_schema')
ORDER BY schemaname, tablename, indexname;
\`\`\`

No consultar filas personales ni exportar datos de contactos. Revisar índices/constraints y propiedad de tablas antes de sugerir migraciones.

## Comprobación C: contratos de presentación (staging)

| Caso | Método seguro | Evidencia a registrar |
|---|---|---|
| Oferta académica | Abrir listado y detalle | CPT, URLs y comportamiento esperado |
| Cohorte | Abrir oferta con varias cohortes | Orden, estados, fechas y enlaces |
| Seminario y edición | Abrir detalle y editor | Instancia, modalidad, fechas, vínculo padre |
| Carta | Abrir /carta de una oferta | URL estable y datos consistentes |
| Aranceles | Comparar tabla y carta | Precio y descuentos sin alteraciones |
| Personas / Equipo | Abrir ficha y listado | Rol, foto y vínculos |
| Shortcodes legacy | Renderizar una página de prueba representativa | No se muestran códigos crudos |
| Preinscripciones | Consultar endpoint de catálogo | Estado y URL por cohorte/edición |
| Consultas | Enviar solo a un destino de prueba | Registro y respuesta sin correo real |
| Mautic | Simular timeout/error y éxito | Sin mensajes duplicados |
| Meta | Enviar webhook de prueba con clave propia | Firma válida/incorrecta sin datos reales |
| Asistente académico | Usuario staging con permisos limitados | Ediciones permitidas y denegadas |
| Admin | Usuario staging de administrador | Flujos esperados |

Los casos que generan modificaciones se harán únicamente en staging, con datos ficticios y autorización. No ejecutar campañas ni utilizar clientes de producción.

## Comprobación D: restauración de respaldos

1. Crear copia aislada de los respaldos de código/WordPress/PostgreSQL en staging.
2. Restaurar dentro de un entorno distinto al de producción.
3. Verificar integridad (conteos y checksums no personales), conectividad y carga del sitio.
4. Documentar tiempos, rollback y pasos manuales. **No ejecutar comandos de restauración en producción** durante F0.

## Comprobación E: referencia de rendimiento

Recolectar con instrumentación habilitada solo en staging:
- TTFB y p95 de portada, catálogo, oferta, /carta, consultas administrativas.
- Cantidad/duración de queries PostgreSQL y WordPress por flujo.
- Edad de cola, cron, tiempos de Mautic **simulado**, memoria, errores.
- Registrar ambiente, tamaño de datos sintéticos y condiciones de medición.
- Los umbrales/SLO se definen después de obtener la línea base.

## Aprobación G0

La fase 0 **no se considera cerrada** hasta contar con: inventario AST o equivalente revisado, mapa de consumidores y entidades, rutas con permisos, contratos fijados, baseline de pruebas funcionales, staging seguro, versiones PHP FPM/CLI, esquema DB revisado, restauración probada y métricas básicas.

Resultados del CI validan únicamente la **parte estática**. No equivalen a una auditoría de ejecución ni de datos del servidor.
