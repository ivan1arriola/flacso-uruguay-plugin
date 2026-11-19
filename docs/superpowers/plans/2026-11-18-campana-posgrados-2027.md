# Campaña de posgrados 2027 Implementation Plan

> Para agentes ejecutores: usar `superpowers:subagent-driven-development` (recomendado) o `superpowers:executing-plans` y completar las tareas con la evidencia indicada.

**Goal:** Tener medición y atribución verificables para consultas y preinscripciones, y decidir la campaña con datos confiables hasta el 22/12/2026.

**Architecture:** Separar el embudo «Más información» de WordPress del portal externo de preinscripciones. Inventariar y probar primero lo existente; corregir solo fallas demostradas, conservando la persistencia de consultas y su entrega asíncrona a Mautic. Las decisiones de anuncios y presupuesto dependen de datos validados.

**Tech Stack:** WordPress/PHP 7.4+, JavaScript, GA4/GTM, Meta Pixel y CAPI, Mautic, portal externo y planilla.

**Spec:** Informe «Revisión técnica: web, Meta y GA4», datos al 18/11/2026 (`C:/Users/Iván/.codex/attachments/94492232-d329-49ff-9107-d59b10163784/Texto pegado.txt`); corrección del usuario sobre los dos embudos.

## Global Constraints

- Este documento es un plan: no publica código ni modifica configuraciones o presupuestos.
- El evento Meta `Lead` considerado aquí corresponde a «Más información» en WordPress; la preinscripción ocurre en `preinscripciones.flacso.edu.uy` y tiene un embudo propio.
- No equiparar 424 intentos, 17 `form_submit` y 432 `Lead` sin verificar fuentes, IDs de formularios, períodos, zona horaria y versiones desplegadas.
- No enviar datos personales a GA4. Respetar consentimiento, validación, antispam e idempotencia existentes.
- No agregar dependencias ni refactorizar por anticipación. Cambios de código solo si una prueba demuestra la necesidad.
- Fechas anteriores al correo 24 son objetivos; confirmar su fecha real y adelantar el trabajo si corresponde. Revisión de presupuesto: 25/11; cierre de campaña: 22/12.
- Dueños por confirmar: desarrollo WordPress, analítica/GA4-GTM, Meta Ads, Mautic/correos, portal externo, planilla y coordinación académica.
- Coordinación: agente principal; ejecución de código: Terra; lectura: Luna si se puede asignar (no disponible hoy).

## Review Focus

- Respuesta exitosa sin `persisted`: verificar contrato productor/consumidor y prueba de compatibilidad en tarea 2.
- Fallo parcial de varias ofertas y reintento: comprobar ausencia de duplicados en tarea 2.
- `sessionStorage` bloqueado o recarga: medir pérdida o duplicación de evento en tarea 2.
- Mautic fallido tras persistir en WordPress: distinguir consulta guardada de entrega pendiente en tarea 2.
- Valores UTM y consentimiento: preservar atribución por consulta y evitar PII en tarea 3.

---

## Fase 1 — Antes del correo 24: establecer la verdad

### Tarea 1. Inventario y pruebas de los dos embudos — urgente

**Responsables:** desarrollo, analítica, portal, Mautic y planilla. **Objetivo:** antes del correo 24. **Dependencias:** acceso de lectura a cada sistema.

- [ ] Registrar para cada flujo URL, formulario e ID, programa, versión desplegada, zona horaria y ventana temporal; identificar autores y destinos de cada evento en GA4, GTM, Meta y portal.
- [ ] Construir matriz para tres posgrados distintos: Android e iOS, navegador de Instagram y de Facebook donde cada combinación esté disponible; documentar explícitamente combinaciones no disponibles y alternativa probada.
- [ ] Para «Más información», completar envíos de prueba autorizados y registrar validaciones, captcha, respuesta, persistencia WP, página de gracias, entrega a Mautic y eventos. Identificar y limpiar únicamente los datos de prueba según procedimiento acordado.
- [ ] Para preinscripción externa, comprobar formulario, validaciones, confirmación, registro del portal, contacto Mautic y fila de planilla; identificar responsables y puntos de integración reales, sin suponer que usa el formulario WP.
- [ ] Comparar intentos y confirmaciones solo para el mismo formulario, período y versión; registrar rechazos y evidencia anónima, no inferir una tasa a partir de los tres totales del informe.
- [ ] **Aceptación:** matriz con resultado por combinación, enlaces/capturas sin PII, IDs de prueba, tiempos y responsables de cada incidencia; recorrido externo confirmado o bloqueo asignado a su dueño.

### Tarea 2. Consultas confirmadas y eventos — urgente

**Responsables:** desarrollo y analítica/Meta. **Objetivo:** antes del correo 24, después de tarea 1. **Dependencias:** inventario de GTM y versiones.

**Puntos existentes a revisar:** `includes/core/assets/ga4-events.js`, `includes/core/assets/meta-tracking.js`, `includes/core/class-flacso-meta-tracking.php`, `modules/main-page/includes/flacso-consultas.php`; en el tema hermano, `consulta-seminario.php`, `oferta-academica/page.php`, `oferta-academica/script.js` e `inc/academic-public-model.php`.

- [ ] Confirmar en ejecución: `form_start` y `form_submit_attempt` globales; `InfoRequestFormView` → `form_view`; `Lead` → `generate_lead`; Pixel y solicitud CAPI comparten `eventId` y Meta deduplica. Inventariar todos los disparadores automáticos `form_submit` de GA4/GTM.
- [ ] Contrastar el código desplegado: el cliente rechaza error y `persisted: false`, guarda marcador pendiente en `sessionStorage`, y la página de gracias lo valida antes de emitir `Lead`. La respuesta AJAX exitosa actual puede omitir `persisted`; no exigir `persisted === true` sin actualizar productor y pruebas compatibles.
- [ ] Preferir `generate_lead` existente como consulta confirmada y marcarlo evento clave solo tras validar su alcance; mantener `form_submit_attempt` como paso intermedio. Si se conserva `form_submit`, definirlo por flujo y confirmación real; evitar eventos clave duplicados por GTM o etiquetas paralelas.
- [ ] Probar éxito, rechazo, antispam, `persisted: false`, ausencia del campo, recarga, almacenamiento bloqueado, reintento y fallo parcial multioferta con `saved_offer_ids`: sin falso éxito ni duplicados de consulta o evento. Diferenciar persistencia WP de fallo asíncrono de Mautic.
- [ ] Si se demuestra una brecha, primero agregar la regresión y corregir el punto compartido mínimo; reutilizar `tests/inquiry-handler-wiring-test.php`, `tests/inquiry-services-test.php`, `tests/inquiry-delivery-worker-test.php` e `tests/inquiry-marketing-consent-test.php`. Crear harness JS pequeño solo si los existentes no cubren el comportamiento.
- [ ] Evaluar CAPI de preinscripción con el dueño del portal/Mautic. El relay iniciado en navegador no es medición independiente del servidor; solo si se prueba una brecha, diseñar envío desde confirmación autoritativa, consentimiento y deduplicación con el mismo ID.
- [ ] **Aceptación:** traza de prueba anónima por caso, evento único por confirmación y ninguno por rechazo, contrato documentado y pruebas verdes; documentar limitaciones reales si el navegador bloquea almacenamiento.

### Tarea 3. Atribución UTM y planilla — urgente

**Responsables:** desarrollo, portal, Mautic y planilla. **Objetivo:** antes del correo 24. **Dependencias:** mapa de tarea 1.

**Puntos existentes:** `includes/core/assets/utm-links.js`, `modules/formularios/includes/helpers.php`, `modules/consultas/services/class-flacso-offer-inquiry-service.php`, `class-flacso-seminar-inquiry-service.php`, `class-flacso-mautic-payload-builder.php`, `class-flacso-inquiry-delivery-worker.php` e `includes/integrations/class-flacso-mautic-client.php`.

- [ ] Trazar las cinco UTM desde URL, navegación y campos hasta persistencia por consulta, snapshot, worker, payload y destino Mautic; el snapshot ya incluye source, medium, name, externalId, content y term. Comprobar también el portal externo y cómo llega cada fila a la planilla. No asumir que un formulario Mautic recibe las preinscripciones.
- [ ] Acordar semántica de contacto (primer/último origen) y fuente histórica por preinscripción antes de agregar campos. El builder limita campos de contacto y usa snapshot inmutable; no sobrescribir el origen de una consulta anterior con el de otra.
- [ ] Solo ante una brecha demostrada, agregar campos ocultos y mapeos en el sistema dueño del formulario; si se amplía el contrato Mautic, actualizar manifiesto/validador y pruebas existentes. Verificar preservación de los cinco valores, ausencia de UTM, caracteres codificados y consentimiento; reutilizar `tests/mautic-payload-builder-test.php` y pruebas de servicios/worker.
- [ ] Agregar columna «Canal» a la planilla desde `utm_source` y `utm_medium` de la preinscripción correspondiente; definir representación de ausentes y evitar asociar el último valor del contacto a filas históricas.
- [ ] Probar enlace con UTM, navegación al portal y fila/contacto correctos; comprobar que datos personales no aparecen en eventos GA4.
- [ ] **Aceptación:** tabla de trazabilidad de los cinco campos por flujo y prueba de dos consultas del mismo contacto con orígenes distintos sin pérdida histórica; planilla con canal verificable.

### Tarea 4. Correo 24 — urgente

**Responsables:** Mautic/correos y analítica. **Objetivo:** antes del envío, fecha a confirmar. **Dependencias:** tarea 3 para comprobar destino.

- [ ] Agregar a todos los enlaces aplicables `utm_source=mautic&utm_medium=email&utm_campaign=correo_24_posgrados_2027`, respetando parámetros existentes; probar enlaces y redirecciones reales hasta la página/portal.
- [ ] Investigar los nueve enlaces del correo 23 con 450–500 clics similares y documentar si Mautic permite filtrar bots de seguridad y cómo afecta los informes.
- [ ] Analizar las 122 bajas del correo 23, solapamiento con la base del correo 24 y frecuencia; proponer ajuste al responsable antes del envío.
- [ ] **Aceptación:** inventario de enlaces probado, UTM observadas en destino y decisión documentada sobre frecuencia y posible filtrado de bots.

## Fase 2 — Diagnóstico y mejoras de campaña

### Tarea 5. GA4, páginas y experiencia móvil

**Responsables:** analítica, desarrollo y coordinación académica. **Objetivo:** antes del 25/11 cuando sea posible. **Dependencias:** tareas 1–2.

- [ ] Crear exploración fija por posgrado y fuente con `form_view` → `form_start` → `form_submit_attempt` → confirmación validada; separar consultas WP de preinscripciones externas y documentar alcance de cada evento.
- [ ] Investigar iOS (19 s frente a 36 s Android), consentimiento y WebViews de Instagram/Facebook con pruebas comparables; registrar condiciones y efectos de medición.
- [ ] Revisar 38 sesiones con landing «(sin definir)» y sesiones en `/`: etiquetas, redirecciones, consentimiento y URL de destino; comparar con configuración y tráfico real.
- [ ] Revisar DESI y DCPP: precio, fechas, visibilidad del formulario y carga móvil; contrastar conversión con denominadores y períodos válidos.
- [ ] Medir MESYP, EGCCD y DEVNNA en móvil y contrastar la llegada de 71–72 % con 84 % promedio, verificando definiciones de clic y sesión. Registrar mejoras priorizadas con responsable.
- [ ] **Aceptación:** exploración reproducible y diagnóstico con evidencia, impacto, responsable y próxima acción por incidencia.

### Tarea 6. Meta: optimización, enlaces y creatividades

**Responsable:** Meta Ads con analítica. **Objetivo:** inventario antes del 25/11; cambios tras validar tarea 2.

- [ ] Inventariar evento y fuente de optimización de los 15 conjuntos, incluidos MESYP/EAPET (`lead`), DG (conversión personalizada) y los demás (`Lead`); reconciliar sus definiciones y decidir un evento común apto para el objetivo y volumen disponible.
- [ ] Revisar plantilla propuesta: `utm_source={{site_source_name}}&utm_medium=paid_social&utm_campaign=preinscripciones_posgrados_2027&utm_term={{adset.name}}&utm_content={{ad.name}}`. Probar resolución de macros, codificación y URL final en anuncios publicados antes de aplicarla.
- [ ] Investigar IAPE Reel y MG Reel/Carrusel con ID como campaña, los 102 casos `paid`, y las 13 sesiones de cada macro sin resolver; corregir origen y comprobar nuevos clics.
- [ ] Inspeccionar valores decodificados antes de tratar `+`: puede representar espacios. Codificar correctamente nombres con espacios o `|`; evaluar renombrar conjuntos sin ellos solo si aporta claridad, sin reemplazos indiscriminados.
- [ ] Evaluar Reel para posgrados sin él y carruseles de baja entrega con datos suficientes; revisar SMSYT sin anuncios activos y decidir archivar o mantener según historial y política operativa.
- [ ] **Aceptación:** inventario de los 15 conjuntos, decisión del evento y muestra de enlaces finales con macros resueltas y UTM verificadas; registro de decisiones creativas.

### Tarea 7. Revisión presupuestaria del 25/11 y seguimiento

**Responsables:** Meta Ads y coordinación. **Objetivo:** 25/11 y seguimiento hasta 22/12. **Dependencias:** confirmaciones y atribución validadas, tareas 1–6.

- [ ] Revisar DCPP, DEVBG y DESI (USD 74 gastados sin preinscripción en informe; USD 408 proyectados), DSMSYT e IAPE (muchos leads y 1–2 preinscripciones), y MG, MESYP y MEDUTIC; actualizar gasto, volumen, ventanas y costos con datos confiables.
- [ ] Evaluar reasignación dentro de USD 62/día hasta 22/12, presupuesto de campaña Advantage+, exclusión de 18–24 años (CPL USD 2,38 y 3 leads informados) y riesgos de muestra/aprendizaje. No ejecutar recomendaciones automáticamente.
- [ ] Documentar decisión, responsable, fecha, criterio y línea de base; monitorear resultados y revisar al cierre del 22/12.
- [ ] **Aceptación:** acta del 25/11 con evidencia y decisiones justificadas, más informe de cierre y gasto real.

## Fase 3 — Entrega, verificación y reversión

### Tarea 8. Liberación condicional de correcciones

**Responsables:** desarrollo y operaciones. **Objetivo:** después de pruebas de cada cambio. **Dependencias:** incidencia confirmada y tareas correspondientes.

- [ ] Revisar `git status`, diff y comparación con `origin/main` en ambos repositorios; proteger trabajo ajeno. El plugin tenía el commit local `affc18d` de decoración UTM externa (ventana de 30 días), uno por delante de `origin/main`; verificar estado actual y su contenido antes de cualquier publicación. El tema estaba limpio y alineado en la última revisión.
- [ ] Ejecutar pruebas de regresión pertinentes y, en el plugin, `python scripts/agent_check.py --diff` y `python scripts/agent_check.py`; exigir `RESULTADO: [EXITO]`. Registrar resultados y versión desplegada.
- [ ] Antes de publicar, inspeccionar `.github/workflows/deploy-plugin.yml`: un push del plugin a `main` despliega automáticamente. El despliegue del tema también se activa al hacer push a `main`; inspeccionar `kadence-child-flacso/.github/workflows/deploy-theme.yml` y `theme-ci.yml`. El workflow del tema clona el plugin desde `origin/main` antes de ejecutar `bash bin/check-theme.sh`: si hay dependencia, liberar y verificar primero el plugin y después el tema.
- [ ] Publicar solo cambios revisados y autorizados, sin incluir trabajo ajeno; verificar GitHub Actions, versión, errores y recorridos reales sin PII. Este plan por sí mismo no ejecuta publicación.
- [ ] Preparar reversión mediante `git revert` de los commits propios identificados y verificar nuevamente workflow y recorridos; no descartar ni revertir cambios del usuario.
- [ ] **Aceptación:** evidencia de pruebas, despliegue y verificación por repositorio o registro explícito de que no se requirió cambio; procedimiento de reversión probado a nivel de revisión.
