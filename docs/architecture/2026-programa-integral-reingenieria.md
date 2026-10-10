# Programa integral de ingeniería del plugin FLACSO Uruguay

**Estado:** propuesta para revisión · 2026-10-10  
**Repositorio:** ivan1arriola/flacso-uruguay-plugin  
**Ámbito:** plugin completo; WordPress, PostgreSQL, Mautic, Meta y aplicaciones externas como fronteras de integración.  
**Relacionado:** ADR-001 (modularización), PR #61 (inventario automático).  
**Naturaleza:** plan de ejecución; NO autoriza cambios de datos, de permisos, de infraestructura ni despliegues automáticos.

## 0. Resumen y condiciones de partida

El plugin funciona como monolito modular WordPress que integra catálogo académico, personas, oferta y seminarios, aranceles, convenios, consultas y analítica, comunicaciones Mautic, Meta, preinscripciones, eventos, formularios, shortcodes y portada.

El inventario estático del PR #61 registró **298 archivos PHP y 4.457 coincidencias heurísticas**, mientras el bootstrap declara **16 módulos**. Son datos de un análisis estático: NO representan cobertura de pruebas, defectos ni número de símbolos únicos.

Hallazgos observables:
- Bootstrapping manual y orden dependiente en flacso-uruguay.php y modules/*/init.php.
- Los archivos de integración/administración y consultas concentran renderizado, acceso a datos y ejecución de operaciones.
- El plugin anuncia PHP 7.4 como mínimo. Aparecieron incompatibilidades con sintaxis PHP 8 en el lint 7.4.
- WordPress y PostgreSQL almacenan ámbitos diferentes; Mautic, Meta y preinscripciones son dependencias externas.
- Hay pruebas independientes, pipeline de lint y despliegue con pasos de recuperación; faltan mapa formal de dependencias, cobertura y pruebas de escenarios completos.
- Subsisten puntos de entrada y adaptadores heredados, particularmente posgrados y shortcodes.

### Objetivos estratégicos

1. **Preservar servicio:** refactorizar sin alterar contenido, contratos públicos ni envíos.
2. **Una fuente de verdad por dato:** propietarios de entidades y datos explícitos.
3. **Aislar dependencias:** desacoplar dominio, aplicación, adaptadores técnicos y vistas.
4. **Asegurar cambios:** reproducibilidad, CI, seguridad, testing y despliegue reversible.
5. **Hacer sostenible el mantenimiento:** cambios pequeños y rastreables, documentación y responsables.
6. **Mejorar UX:** editores legibles, accesibles, coherentes y mobile first.
7. **Medir antes de optimizar:** rendimiento y observabilidad basados en línea base.

### Restricciones no negociables

- Mantener slugs, CPT, taxonomías, meta keys, opciones, shortcodes, REST, rutas de carta (/carta), enlaces y formatos de datos; cualquier ruptura requiere una migración versionada, aprobación y tests.
- No modificar esquemas PostgreSQL/WordPress como efecto secundario de una refactorización.
- Mantener separación entre consultas transaccionales y marketing sujeto a consentimiento.
- No introducir duplicidad de mensajes ni reactivar envíos comerciales antiguos.
- Respetar capacidades de asistentes académicas, administradores y editores en cada operación; ocultar un menú no sustituye autorización.
- No hacer refactorización general ni despliegue a producción sin staging verificable, rollback y evidencia de pruebas.
- No asumir que versión mínima de PHP equivale a la versión de producción: comprobar ambas y documentar decisión.
- No guardar secretos ni datos personales en CI/artifacts/logs del inventario.
- Evitar sobreingeniería: monolito modular, no microservicios ni un contenedor DI obligatorio.

## 1. Gobierno del proyecto

### Papeles (asignar personas al inicio, no asumir nombres)

- **Responsable técnico:** priorización, arquitectura, revisión final y autorización de merge.
- **Responsable funcional:** valida reglas académicas, permisos, cartas, formularios y textos.
- **Operaciones:** ambientes, bases, backups, monitoreo y rollback.
- **QA/validación:** criterios de aceptación y verificación documental y funcional.
- Una persona puede cubrir más de un papel, pero una modificación crítica requiere revisión de otro responsable cuando sea posible.

### Decisiones arquitectónicas (ADRs)

ADR-001 modularización; proponer ADR-002 estrategia de PHP y Composer; ADR-003 propiedad de datos y comunicación; ADR-004 entregas e idempotencia; ADR-005 compatibilidad frontend/API; ADR-006 observabilidad y retención de logs.

### Prácticas de trabajo

- Issues con categoría, prioridad, alcance, DoD, riesgos, archivos estimados, datos afectados, estrategia de test y rollback.
- PR pequeños (idealmente una responsabilidad por PR). No mezclar refactorización mecánica, migración de datos y cambios de interfaz en la misma PR.
- Revisión obligatoria para cambios de permisos, consentimiento, colas, PostgreSQL, clientes externos y rutas REST.
- Checklist de despliegue y bitácora de cambios vinculada a commits.
- Baseline estable antes de cambiar arquitectura; confirmar el estado de PR #60/#61 y evitar duplicar esfuerzos.
- Historial y releases semánticos (breaking / feature / fix), con versión de esquema independiente si corresponde.

## 2. Arquitectura objetivo y límites

### Arquitectura recomendada

**Monolito modular por dominio**, con dependencias hacia adentro: presentación de WordPress (hooks, REST, WP-Admin, Gutenberg), casos de uso, dominio, contratos, adaptadores (WP_Query, postmeta, PDO, Mautic API, Meta HTTP, cron/CLI).

Disposición gradual:

    flacso-uruguay.php                 # solo bootstrap
    config/modules.php                 # registro explícito y dependencias
    src/Core/{Bootstrap,Contracts,Security,Support}/
    src/Academic/{Programs,Offers,Cohorts,Seminars,Editions}/
    src/People/
    src/Inquiries/{Domain,Application,Infrastructure,Presentation}/
    src/Communications/
    src/Pricing/
    src/Agreements/
    src/Enrollments/
    src/Events/
    src/Content/
    resources/{views,css,js}/
    database/migrations/
    tests/{Unit,Integration,Contract,E2E}/
    docs/{architecture,decisions,modules,runbooks}/

No crear carpetas ni abstracciones vacías; elegir capas solo cuando se justifican. Las clases y funciones legadas permanecen como fachadas o aliases hasta verificar que no haya consumidores.

### Matriz de propiedad de datos

| Ámbito | Fuente de verdad propuesta | Referencias en otros sistemas | Regla |
| --- | --- | --- | --- |
| Programas, ofertas, cohortes, seminarios, ediciones | WordPress | Mautic, preinscripciones, web | Leer desde API/casos de uso, no duplicar como origen |
| Personas y roles institucionales | CPT Personas/Equipo | Autoridades, equipos, cartas | Una ficha por persona; relaciones referencian su ID |
| Roles y permisos de acceso | WordPress usuarios/capacidades | Módulos administrativos | Autorización en endpoints/casos de uso |
| Tablas de aranceles y convenios | WordPress | Cartas, ofertas | Versionar condiciones cuando cambien |
| Consultas y estados de procesamiento | PostgreSQL del plugin | WordPress y Mautic | Idempotencia, registros de operación, reintentos |
| Contactos, campañas, analítica de correo | Mautic | WordPress | WordPress solicita eventos y consulta estados, no crea un segundo CRM |
| Preinscripciones y documentos | Aplicación de Preinscripciones | WordPress | WordPress publica datos académicos; no administra solicitudes |
| Leads/ads y eventos publicitarios | Meta | WordPress/Mautic | Webhook autenticado, normalización y deduplicación |

### Reglas de dependencias a imponer

- Core no importa ningún módulo funcional.
- Domain no invoca funciones WordPress, PDO, APIs externas ni produce HTML.
- Application usa interfaces; Infrastructure las implementa; Presentation llama a Application.
- Entre dominios se consumen contratos versionados, servicios de consulta o eventos; nunca ficheros internos de otro módulo.
- Prohibir ciclos de módulos y clases definidas dos veces.
- Integraciones tienen timeout, reintento acotado, saneamiento de errores e idempotencia según operación.
- Registro de hooks agrupado por proveedor del módulo; bootstrap puede enumerar el orden, pero no esconder efectos secundarios.
- Separar escritura y lectura donde mejore claridad y rendimiento, sin incorporar CQRS/event sourcing global por defecto.

## 3. Plan de ejecución: etapas y puertas de salida

Los plazos son **orientativos**, no fechas comprometidas. Paralelizar solo cuando estén estables interfaces y pruebas.

### Fase 0 — Inventario fiable y baseline (2 a 4 semanas)

- AUD-001..005: confirmar inventario en main, analizar AST en vez de solo regex, descubrir mapa de módulos/llamadas/ciclos, catálogo de hooks y REST (métodos y permisos), CPT/meta/options/shortcodes, tablas y cron.
- AUD-006..009: separar código ejecutado, legado referenciado y archivos huérfanos; crear mapa de consumidores y de datos; registrar versión de WP/PHP y topología de producción/staging; establecer línea base operativa.
- AUD-010..012: smoke tests de catálogo, carta, consultas, envío transaccional, permisos, frontend y registro de entidades; etiquetar rutas críticas.

**Puerta G0:** inventario y propiedad de datos revisados; baseline de tests reproducible; lista de contratos críticos y rollback aprobado. Ningún movimiento masivo de archivos antes de G0.

### Fase 1 — Calidad, seguridad básica y DX (3 a 5 semanas)

- PLT-001..003: decisión sobre PHP 7.4 frente a PHP de producción; Composer/autoload PSR-4 incremental, build determinista y sin dependencias de desarrollo en ZIP.
- QLT-001..005: PHPCS WordPress Coding Standards, PHPStan + stubs, formateo, comprobación de arquitectura/ciclos, editorconfig y guías.
- TST-001..004: matriz PHPUnit + WP integration + contratos existentes; fixtures reproducibles y separación de tests rápidos vs integración.
- CIC-001..006: pipeline con lint exhaustivo, análisis, pruebas, paquete, staging y promoción a producción; impedir deploy por push si no superó validaciones.
- SEC-001..003: inventariar permisos por endpoint, CSRF/nonces, secretos y logging de PII; checks iniciales de dependencias.
- OPS-001: restauración verificable de backup de base/código en entorno aislado.

**Puerta G1:** PR bloqueada si falla calidad crítica; release y rollback probados; dependencias y credenciales no expuestas.

### Fase 2 — Núcleo y piloto People (3 a 5 semanas)

- ARC-001..004: módulos declarativos, registro de servicios ligero, logging contextual, errores tipados y fachadas legadas.
- PPL-001..004: mover gradualmente Personas/Equipo y Autoridades al dominio People; perfiles, roles, relaciones y vistas; mantener CPT y API.
- UX-001..003: estilos/JS por pantalla en assets, navegación administrativa, componentes accesibles y mobile-first.

**Puerta G2:** no hay cambios observables de datos ni permisos; contratos People y URL públicas iguales; pruebas de asistentes y capturas escritorio/móvil.

### Fase 3 — Consultas y Comunicaciones (5 a 8 semanas)

- INQ-001..005: separar API de recepción, servicio de dominio, repositorios PostgreSQL, DTO/snapshot, analítica y panel administrativo.
- COM-001..006: separar transaccional y marketing, contrato Mautic, consentimiento, idempotencia, deduplicación, queue worker y DLQ/alarma operativa.
- INT-001..003: adaptadores Meta y Telegram, control de tiempo/errores, secretos y trazabilidad; retirar gradualmente código Mailjet solo tras análisis de referencias.
- PRF-001..002: medición de consultas SQL y procesamiento de colas, eliminación de N+1 basándose en mediciones.

**Puerta G3:** no se pierden consultas, no hay correos duplicados en pruebas de interrupción; fallas externas no bloquean el sitio; trazabilidad end-to-end y recuperación manual documentada.

### Fase 4 — Académico, aranceles y preinscripciones (5 a 8 semanas)

- ACA-001..007: Programa/Oferta/Cohorte, Seminario/Edición, reglas de instancias, equipo académico, documentos y relaciones.
- PRI-001..003: tabla de precios, descuentos y convenios, referencias históricas y validación de presentación.
- ENR-001..003: DTO públicos de preinscripción, caché e invalidación, disponibilidad y contratos con aplicación externa.

**Puerta G4:** equivalencia antes/después para oferta, seminarios, /carta, precios y links; cero cambios de identificación de programas, URLs y resultados por cohortes.

### Fase 5 — Eventos, sitio y UX (4 a 7 semanas)

- EVT-001..003: unificar eventos y charlas sin romper flacso-charlas/v1; registro/inscripción segura, anti-spam y metadatos.
- CNT-001..006: main-page, bloque Gutenberg, shortcodes y compatibilidad posgrados; aislar templates y CSS; gestionar campañas de portada y recursos.
- UX-004..007: administración y frontend mobile-first; contraste, teclado, labels, estados de error y coherencia con tema Kadence.

**Puerta G5:** screenshots y tests de regresión; URLs, SEO, formularios, bloqueos y shortcodes antiguos funcionan; sin variaciones editoriales involuntarias.

### Fase 6 — Endurecimiento, desempeño y retiro legado (4 a 6 semanas)

- SEC-004..007: threat modeling, revisión OWASP WordPress, autorización granular de AJAX/REST/exportaciones y datos personales/retención.
- PRF-003..006: caching e invalidación, presupuestos SQL y páginas críticas, CSS/JS por contexto, pruebas de carga y perfiles.
- OBS-001..005: logs estructurados, alertas (PostgreSQL/Mautic/cron/cola), métricas y runbooks.
- LEG-001..004: uso de funciones y shortcodes legados, plan de deprecación, adaptadores de compatibilidad y eliminación solo con métricas de uso.
- REL-001..003: cierre de deuda, documentación funcional y pruebas de recuperación.

**Puerta G6:** métricas y runbooks activos, dependencias legadas justificadas o retiradas, plataforma mantenible y auditable.

## 4. Workstreams horizontales — requisitos concretos

### Calidad de código y estándares
- Nuevas clases: nombres coherentes y namespace FLACSO; evitar clases globales nuevas.
- PHPCS/WPCS con excepciones justificadas; PHPStan con nivel progresivo y baseline que debe bajar, nunca crecer sin discusión.
- Comentarios/docblocks para contratos públicos; evitar métodos con demasiados efectos secundarios.
- Detectar archivos grandes (>500 líneas como alerta para revisión, no límite automático), duplicación y complejidad.
- Revisión explícita de archivos de ~105 KB de configuración de integraciones, ~92 KB de administración de consultas, ~58 KB de shortcodes init.
- Automatizar formato donde sea seguro sin alterar contenido editorial.

### Pruebas y estrategia de regresión
- Unitarias para reglas, permisos, cálculos, serializadores, clasificación, etiquetas, consentimiento, estado y URLs.
- Integración con WP para CPT/REST/meta, staging PostgreSQL, Mautic/Meta mockeados.
- Contratos que fijan payload y estados entre WordPress, Mautic, Meta y Preinscripciones.
- Pruebas E2E para pantalla admin de asistente, alta/edición de oferta, cohorte, seminario, carta, consulta y errores.
- Casos de caos: DB caída, timeout Mautic, duplicado webhook, proceso cron repetido, dos workers simultáneos, fallo a mitad de deploy.
- Conservar pruebas existentes como baseline; etiquetar pruebas no aisladas; no borrar pruebas solo para pasar CI.

### Seguridad, privacidad y cumplimiento
- Matriz de datos personales por sistema, origen, finalidad, permiso, retención y eliminación; revisión normativa local con responsable institucional.
- REST/AJAX/admin-post: autenticar o validar firmas cuando sean públicas; permiso específico, nonce donde corresponda, validación estricta, rate limit y salida escapada.
- Nonce NO sustituye capabilities; webhook firmado no sustituye saneamiento; webhook público deliberadamente autorizado puede ser correcto.
- SQL preparado, minimización de consultas con datos personales, prohibir PII y tokens en logs y artefactos CI.
- Secretos gestionados fuera del repositorio; rotación y acceso mínimo; inventario de credenciales y webhook callbacks.
- Revisar SSRF, XSS, CSRF, CSV injection, descarga arbitraria, IDOR, escalación de privilegios y abuso de envíos.
- Definir políticas de exportaciones y auditoría de accesos.

### Persistencia y migraciones
- Identificar columnas, constraints, índices y migraciones reales de PostgreSQL; no inferir tablas únicamente de strings SQL.
- WordPress: documentar cada meta clave con tipo, fuente de datos, cardinalidad y autorización.
- Migraciones idempotentes con versión y rollback lógico; preservar datos históricos; respaldar y comparar checksums/conteos.
- Repositorios con operaciones tipadas y contratos que permiten test sin BD real.
- Regla de consistencia para operaciones cruzadas: outbox/inbox o equivalente cuando sea apropiado; no transacciones distribuidas ficticias.

### Rendimiento y escala
- Medir tiempo de carga/cantidad de queries en portada, catálogo, carta, perfiles y consultas; comparar p50/p95 antes/después.
- Instrumentar costo por operación, consultas repetidas y latencia externa; revisar índices de PostgreSQL con EXPLAIN.
- Lazy-load por ruta y por hook; evitar inicializar UI admin en frontend; cargar CSS/JS por pantalla.
- Cachés con invalidación explícita ante cambios de oferta, cohorte, ediciones y textos; no cachear datos personales públicamente.
- Definir límites de latencia luego de obtener baseline; optimizar solo con evidencia y pruebas.

### Operación, resiliencia y observabilidad
- Correlación de consulta -> trabajo en cola -> llamada a Mautic -> estado; identificadores sin datos personales.
- Métricas: consultas/minuto, cola pendiente, antigüedad máxima, fallas por causa, reintentos, latencia de DB/API, cron retrasado, tiempos HTTP.
- Healthcheck seguro sin exponer credenciales; dashboards y alertas accionables con responsables.
- Alertas críticas: crecimiento sostenido de cola, duplicados, fallos de conexión, firma inválida repetida, error masivo de REST, cron detenido.
- Runbooks de rollback de deploy, reconexión de BD, drenado seguro de cola, rotación de claves y restauración de backups.
- Fallar de forma aislada: caída de Mautic no debe provocar caída de páginas públicas.

### UI/UX y accesibilidad
- Sistema de diseño coherente con FLACSO y el tema, sin duplicar componentes CSS.
- Componentes admin: cabecera, filtros, estados, acciones, confirmaciones, tablas/fichas responsive, inputs, mensajes de error.
- WCAG 2.2 AA como objetivo de verificación; pruebas de teclado, lector de pantalla, 320px, 200% zoom, contraste y mensajes útiles.
- Validación de textos editoriales con responsable: una refactorización no reescribe contenido institucional.
- Sincronizar estado visible con estado real de envíos, cohortes y permisos.

### Integraciones y contratos externos
- Versionar esquema REST/JSON, mapear error codes, autenticar, paginar y aplicar backoff a APIs externas.
- WordPress entrega contexto académico a Mautic cuando es necesario, sin duplicar la verdad académica.
- Meta: verificar firma, deduplicar eventos, comprobar permisos y revisar retención de leads.
- Preinscripciones: documentos e inscripciones viven fuera; plugin comparte configuración/catálogo y URLs.
- Detectar consumidores conocidos de endpoints y shortcodes antes de deprecarlos.

### CI/CD, despliegue y entornos
- Staging representativo sin datos personales reales y con endpoints externos de prueba.
- Verificación de versiones PHP en CLI de CI, PHP-FPM del servidor y requisito declarado por plugin.
- Pipeline: encoding -> lint multi-PHP -> PHPCS/static -> unit -> integration -> contract -> build -> scan -> deploy staging -> smoke -> aprobación -> deploy producción -> verificación.
- Artefacto único por SHA con manifiesto SHA256, dependencias runtime y configuración de build reproducible.
- Rollback de archivos y plan explícito para DB/esquema; cambios de esquema compatibles expansión-migración-contracción.
- Pruebas automáticas de post-deploy no deben generar envíos o consultas reales.

## 5. Criterios globales de aceptación / Definition of Done

Cada PR deberá incluir:
1. Requisito/issue trazable, alcance explícito y contrato afectado.
2. Análisis de riesgos y seguridad (permisos, datos, enlaces, secretos).
3. Tests que demuestren el comportamiento anterior y nuevo cuando aplique.
4. Lint y análisis estático sin regresiones, incluyendo todas las versiones PHP soportadas.
5. Sin nuevas consultas N+1 ni aumento de latencia relevante sin justificación.
6. Evidencia de pruebas visuales si hay UI; escritorio y móvil, teclado y errores.
7. Compatibilidad en slugs/REST/meta/options y decisión sobre migración si cambia alguno.
8. Plan de reversión y comprobación posterior; no tocar producción por PR de documentación.
9. Actualización de documentación, changelog y dependencias.
10. Sin datos personales, claves o volcados en logs, commits, capturas y artefactos.

## 6. Métricas: medir primero; objetivos propuestos

| Métrica | Línea base | Objetivo inicial / criterio |
| --- | --- | --- |
| Lint de PHP soportados | Incidencias históricas en 7.4 | 100% de archivos válidos en matriz acordada |
| Inventario de puntos de entrada | Regex heurístico | 100% rutas/hooks/cron críticos con propietario y test |
| Cobertura de pruebas | No medida | Medir por módulo; priorizar reglas críticas, no imponer % global ficticio |
| Vulnerabilidades críticas conocidas | No auditadas | 0 sin remediación o excepción aprobada antes de liberar |
| Contratos públicos rotos | No medidos | 0 cambios involuntarios por refactor |
| Duplicados de correo por reintento | No medido | 0 en pruebas controladas de concurrencia/fallo |
| Disponibilidad y cola de mensajes | No instrumentadas uniformemente | Alertas y umbrales basados en 2-4 semanas de observación |
| Rendimiento p95 | Sin baseline reproducible | No regresión >10% sin justificación; umbral ajustable |
| Restauración | Mecanismos parciales presentes | Ensayo documentado antes de migraciones de datos |
| Accesibilidad | No auditada | Flujos críticos verificados con WCAG 2.2 AA |
| Deuda y modularidad | 298 PHP, 16 módulos, 4457 detecciones heurísticas | Grafo sin ciclos prohibidos; cada nuevo módulo declara dependencias |

Los objetivos de latencia, cola, disponibilidad y cobertura se deben cerrar tras medición real; no inventar un SLO sin datos.

## 7. Registro inicial de riesgos

| Riesgo | Impacto | Prevención y respuesta |
| --- | --- | --- |
| Refactor altera claves/contratos de CPT y cartas | Crítico | Snapshot y tests de URL, meta y render antes/después; despliegue gradual |
| Errores de marketing/consentimiento | Crítico | Tests de permiso, doble opt-in si requerido por política, no suscribir por transaccional |
| Correos duplicados o perdidos | Crítico | Idempotency key, locks, retries controlados, registro de entrega y alerta |
| Fallo PostgreSQL provoca error fatal público | Alto | Captura acotada, fallbacks seguros, circuit breaker si aporta, logs sin datos |
| Cambio en permisos filtra datos | Crítico | Matriz por rol/recurso y tests negativos para rutas admin/REST/exportaciones |
| Cambio en esquema no reversible | Crítico | Expand/contract, ensayo en réplica, respaldo y rollback lógico |
| Incompatibilidad PHP entre runner y FPM | Alto | Decisión ADR y pruebas en ambas versiones, smoke en FPM real |
| Rendimiento degrada tras modularización | Alto | Baseline p95, mediciones de queries y carga condicional |
| Código legado deja de renderizar páginas | Alto | Inventario de consumidores, aliases, contrato de shortcodes y pruebas de render |
| Fugas de PII en artefactos y logs | Alto | Redacción, acceso mínimo, retención y revisión previa a release |

## 8. Secuenciación, esfuerzo y dependencias

Una estimación inicial amplia, sujeta al baseline, es **26 a 43 semanas de desarrollo equivalente de una persona a dedicación completa** para la transformación integral, sin contar decisiones institucionales o cambios mayores de alcance. No es una fecha límite ni una promesa; en dedicación parcial o con validación externa puede prolongarse. Puede reducirse priorizando fases 0-3 y optando por conservar módulos estables. Refinar con estimaciones por issue después de G0.

Dependencias principales:
- Inventario, contratos y baseline antes de cualquier movimiento masivo.
- Acuerdo de PHP, pipeline y staging antes de modificar bootstrap.
- Piloto People antes de repetir patrones en los módulos críticos.
- Contratos de consultas/comunicaciones antes de cambiar colas e integraciones.
- Equivalencia funcional de Academic antes de retirar shortcodes/posgrados.
- Observabilidad antes de desmantelar rutas legacy.

### Cadencia de seguimiento
- Semanal: riesgos, incidentes, número de PRs abiertas, fallos y deuda introducida/eliminada.
- Quincenal: demo funcional con responsables y revisión de roadmap.
- En cada fase: aceptación formal de G0...G6 y reevaluación de estimaciones.
- Mensual: informe de calidad, defectos, performance, errores de producción y reducción de acoplamiento.

## 9. Plan de entrega inicial (primer mes de trabajo)

Semana 1: aprobar alcance/owners; cerrar mapa de contratos críticos; actualizar inventario con AST y rutas REST/shortcodes; confirmar PHP de producción.
Semana 2: construir matriz de acceso por rol y baseline de pruebas; staging aislado, datos sintéticos y backup/restore verificable.
Semana 3: CI con checks separados y matrices de PHP; capturar métricas de rendimiento, cola y errores.
Semana 4: consolidar ADRs y revisar resultados G0; elegir primer PR de bootstrap y piloto People.

No fusionar la planificación y los cambios de arquitectura con refactorizaciones de alto riesgo. Evitar ejecutar pruebas que envíen mensajes reales.

## 10. Backlog ejecutable

El archivo docs/architecture/backlog-reingenieria.csv contiene tareas rastreables por ID, fase, prioridad, tamaño relativo, dependencias, entregable y criterio de aceptación. Convertirlas en issues de GitHub solo tras validar su prioridad y evitar duplicados. Lo no confirmado por código se registra como tarea de comprobación, no como defecto demostrado.

## 11. Exclusiones explícitas

- No migrar WordPress a otro CMS.
- No reemplazar Mautic, Preinscripciones ni Sala Virtual por código en el plugin.
- No crear nuevos microservicios ni bases de datos por principio.
- No reescribir todo el sistema en una sola versión.
- No cambiar textos académicos, datos institucionales o contenido visible por una mera refactorización.
- No manipular el contenido ni las políticas de datos personales sin autorización.

## 12. Primera decisión de implementación

Solicitar aprobación de este plan y ADR-001; después crear el trabajo de G0 y ejecutar las pruebas de inventario y baseline. Todo desarrollo debe preservar las invariantes; el plan se revisa al obtener evidencia real.
