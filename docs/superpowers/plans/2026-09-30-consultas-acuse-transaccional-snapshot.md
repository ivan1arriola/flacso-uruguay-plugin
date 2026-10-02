# Consultas: Acuse Transaccional con Snapshot Inmutable Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (- [ ]) syntax for tracking.

**Goal:** Persistir cada consulta con un snapshot inmutable y entregar su acuse por Mautic desde una cola idempotente, sin depender de campos mutables del contacto ni de campanas comerciales.

**Architecture:** Dos tablas nuevas separan snapshots y entregas de offer_inquiries y seminar_inquiries. Un procesador llamado por cron de servidor reserva entregas, valida un manifiesto Mautic, asegura un destinatario minimo y envia tokens inmutables; marketing queda fuera de ese camino y exige consentimiento verificable.

**Tech Stack:** PHP 7.4+, WordPress, PDO PostgreSQL, WP-CLI, cron de servidor, Mautic 7.2, pruebas PHP autonomas con SQLite.

**Spec:** docs/superpowers/specs/2026-09-30-consultas-transaccionales-snapshot-design.md

## Global Constraints

- No activar cola, campana comercial ni envio productivo hasta aprobar la prueba interna de dos snapshots para el mismo contacto.
- Ninguna tarea autoriza por si misma mutaciones de Mautic, envios de correo,
  cambios de produccion ni instalacion de cron; cada accion requiere aprobacion
  operativa explicita en el momento de ejecutarse.
- El acuse usa tokens por entrega; nunca campos de contacto flacso_ para datos de una consulta.
- ensureDeliveryRecipient solo crea o actualiza email, nombre y apellido; no puede asignar tags, campanas ni datos de consulta.
- Consulta, snapshot y entrega inicial se escriben en una unica transaccion PDO.
- accepted significa aceptacion API, no entrega; un resultado incierto pasa a acceptance_unknown y no se reintenta solo.
- No enviar ni registrar flacso_consulta_texto en Mautic, logs, alertas o respuestas administrativas.
- Marketing exige consentimiento explicito con fecha UTC, origen y version de texto; su ausencia no bloquea el acuse.
- El payload exacto queda en almacenamiento restringido, se anonimiza a los 90 dias y pierde todo vinculo reversible con la consulta.
- El manifiesto exige template_id, version funcional y SHA-256; una huella ausente o distinta bloquea la cola.
- No introducir fallback a Mailjet ni wp_mail; los fallos usan FLACSO_Error_Notifier.
- Produccion requiere backup, SHA exacto y verificacion; push no equivale a despliegue.

## Review Focus

- Timeout al crear destinatario: reconciliar por email antes de un segundo POST y no duplicar contactos. Task 4.
- Timeout despues de iniciar envio: guardar attempt_id, usar acceptance_unknown y bloquear reenvio. Task 5.
- Dos consultas del mismo email: cada acuse conserva oferta, fecha, modalidad y enlaces propios. Tasks 2, 3 y 5.
- Consentimiento ausente o incompleto: permitir acuse y rechazar campana comercial. Task 7.
- Retencion vencida: eliminar payload, snapshotId, email, contact ID, consulta_id e inquiry_id; mantener solo metadatos agregados. Task 6.

---

## File Structure

- Create: scripts/migrations/2026-09-30-inquiry-transactional-deliveries.sql
- Create: includes/database/repositories/class-flacso-inquiry-delivery-repository.php
- Create: modules/consultas/services/class-flacso-inquiry-snapshot.php
- Create: modules/consultas/services/class-flacso-inquiry-tag-factory.php
- Create: modules/consultas/services/class-flacso-mautic-contract-manifest.php
- Create: modules/consultas/services/class-flacso-mautic-contract-validator.php
- Create: modules/consultas/services/class-flacso-inquiry-delivery-service.php
- Create: modules/consultas/services/class-flacso-inquiry-delivery-worker.php
- Create: modules/consultas/includes/class-flacso-consultas-cli.php
- Create: tests/inquiry-snapshot-test.php
- Create: tests/inquiry-delivery-repository-test.php
- Create: tests/mautic-contract-validator-test.php
- Create: tests/inquiry-delivery-service-test.php
- Create: tests/inquiry-delivery-worker-test.php
- Create: tests/inquiry-marketing-consent-test.php
- Create: tests/support/inquiry-delivery-bootstrap.php
- Create: docs/mautic/token-delivery-proof.md
- Create: docs/operations/consultas-transaccionales.md
- Modify: modules/consultas/init.php
- Modify: modules/consultas/services/class-flacso-inquiry-context-service.php
- Modify: modules/consultas/services/class-flacso-mautic-payload-builder.php
- Modify: modules/consultas/services/class-flacso-inquiry-marketing-service.php
- Modify: modules/consultas/services/class-flacso-offer-inquiry-service.php
- Modify: modules/consultas/services/class-flacso-seminar-inquiry-service.php
- Modify: modules/consultas/includes/class-flacso-consultas-admin.php
- Modify: includes/integrations/class-flacso-mautic-client.php
- Modify: tests/mautic-client-test.php
- Modify: docs/mautic/data-contract.md
- Modify: docs/mautic/campaigns.md

### Task 1: Preparar pruebas locales y documentar la puerta de tokens

**Files:**
- Create: tests/support/inquiry-delivery-bootstrap.php
- Modify: tests/mautic-client-test.php
- Create: docs/mautic/token-delivery-proof.md
- Modify: docs/mautic/campaigns.md

**Interfaces:**
- Produces: bootstrap SQLite que replica restricciones e indices relevantes de PostgreSQL.
- Produces: transporte HTTP inyectable para FLACSO_Mautic_Client.
- Produces: procedimiento de prueba de tokens pendiente de autorizacion operativa.

- [ ] **Step 1: Escribir el bootstrap y las pruebas de transporte**

Crear el bootstrap SQLite reutilizable para snapshots, entregas, intentos,
transacciones y claves unicas. Hacer que el cliente Mautic acepte un transporte
HTTP inyectable en pruebas, capaz de simular 2xx, 4xx, timeout y respuesta
perdida sin red ni datos reales.

- [ ] **Step 2: Ejecutar la base de pruebas en rojo**

Run: php tests/mautic-client-test.php

Expected: FAIL por no existir el transporte inyectable o el bootstrap.

- [ ] **Step 3: Documentar el procedimiento externo, sin ejecutarlo**

Describir en token-delivery-proof.md que la futura prueba requiere autorizacion
operativa, backup previo, una plantilla aislada y contacto interno autorizado.
Exigir dos snapshots distintos, evidencia redactada y resultado approved o
rejected. Un resultado rejected detiene toda activacion productiva.

- [ ] **Step 4: Ejecutar la prueba local y commit**

Run: php tests/mautic-client-test.php

Expected: la simulacion de transporte termina OK y no hace red.

    git add tests/support/inquiry-delivery-bootstrap.php tests/mautic-client-test.php docs/mautic/token-delivery-proof.md docs/mautic/campaigns.md
    git commit -m "test(consultas): aislar transporte de Mautic"

### Task 2: Crear persistencia atomica de snapshot y entrega

**Files:**
- Create: scripts/migrations/2026-09-30-inquiry-transactional-deliveries.sql
- Create: includes/database/repositories/class-flacso-inquiry-delivery-repository.php
- Create: tests/inquiry-delivery-repository-test.php
- Modify: tests/support/inquiry-delivery-bootstrap.php
- Modify: includes/database/repositories/class-flacso-offer-inquiry-repository.php
- Modify: includes/database/repositories/class-flacso-seminar-inquiry-repository.php

**Interfaces:**
- Produces: FLACSO_Inquiry_Delivery_Repository::persist_inquiry_with_delivery(FLACSO_Base_Inquiry_Repository $repository, array $record, array $snapshot, string $type): array.
- Produces: inquiry_snapshots, inquiry_deliveries e inquiry_delivery_attempts.

- [ ] **Step 1: Escribir prueba de transaccion y anonimizado**

Usar el bootstrap SQLite. Crear una consulta de oferta y afirmar una sola fila en consulta, snapshot y entrega. Forzar error al insertar entrega y afirmar rollback de las tres escrituras. Repetir consultaId y afirmar que no hay nuevas filas. Probar que una entrega terminal de mas de 90 dias pierde snapshotId, consulta_id, referencia de consulta, email, contact ID y payload, conservando tipo, estado, mes agregado y metadatos no identificables.

- [ ] **Step 2: Ejecutar prueba en rojo**

Run: php tests/inquiry-delivery-repository-test.php

Expected: FAIL porque no existen tablas ni repositorio.

- [ ] **Step 3: Crear migracion SQL aditiva**

Crear inquiry_snapshots con id, inquiryType, inquiryId, consultaId, schemaVersion, snapshotJson, timestamps y UNIQUE(inquiryType, inquiryId). Crear inquiry_deliveries con snapshotId nullable y UNIQUE(snapshotId, deliveryType), estado, reserva, payload, plantilla, contactId, reportMonth y anonymizedAt. Crear inquiry_delivery_attempts con attemptId, estado, inicio, fin, HTTP y error redactado. Indexar pendiente, reserva, conciliacion y retencion.

- [ ] **Step 4: Implementar el repositorio**

Usar beginTransaction, insert de consulta existente, insert de snapshot y insert de entrega pending, con rollback ante cualquier excepcion. Implementar claim_pending_batch mediante UPDATE condicional o bloqueo de filas como garantia primaria de exclusividad; el bloqueo de worker no sustituye esa condicion. Implementar record_attempt_start, mark_accepted, mark_retryable_failure, mark_acceptance_unknown, mark_blocked, find_for_reconciliation y anonymize_due_deliveries.

- [ ] **Step 5: Ejecutar pruebas en verde**

Run: php tests/inquiry-delivery-repository-test.php && php tests/inquiry-repositories-test.php

Expected: ambos terminan OK; rollback, unicidad, reserva y desvinculacion irreversible quedan probados.

- [ ] **Step 6: Commit**

    git add scripts/migrations/2026-09-30-inquiry-transactional-deliveries.sql includes/database/repositories tests/inquiry-delivery-repository-test.php tests/inquiry-repositories-test.php
    git commit -m "feat(consultas): persistir entregas transaccionales"

### Task 3: Introducir snapshots tipados y una sola fabrica de tags

**Files:**
- Create: modules/consultas/services/class-flacso-inquiry-snapshot.php
- Create: modules/consultas/services/class-flacso-inquiry-tag-factory.php
- Create: tests/inquiry-snapshot-test.php
- Modify: modules/consultas/services/class-flacso-inquiry-context-service.php
- Modify: modules/consultas/services/class-flacso-mautic-payload-builder.php
- Modify: modules/consultas/services/class-flacso-inquiry-marketing-service.php

**Interfaces:**
- Produces: FLACSO_Inquiry_Snapshot::from_offer(array $form, array $context, string $consulta_id): array.
- Produces: FLACSO_Inquiry_Snapshot::from_seminar(array $form, array $context, string $consulta_id): array.
- Produces: FLACSO_Inquiry_Tag_Factory::from_snapshot(array $snapshot): array.

- [ ] **Step 1: Escribir pruebas de DTO, contexto, tags y privacidad**

Crear oferta y seminario con extensiones propias, y afirmar bloque base compartido. Afirmar que fecha y modalidad provienen de los antiguos startValue y modalityLabel sin alias contradictorios. Afirmar tags exactamente interes-codigo, codigo-cnumero y origen-web-consultas. Afirmar que el builder no devuelve flacso_consulta_texto.

- [ ] **Step 2: Ejecutar pruebas en rojo**

Run: php tests/inquiry-snapshot-test.php && php tests/mautic-payload-builder-test.php

Expected: FAIL por claves duplicadas, tags heredados o texto libre.

- [ ] **Step 3: Implementar DTO y fabrica**

Definir un snapshot base versionado y extensiones separadas offer y seminar. Normalizar el contexto a startDate, startDatePrecision y modality. Hacer que todos los consumidores de tags llamen a la fabrica y que el builder produzca tokens transaccionales desde snapshot.

- [ ] **Step 4: Ejecutar pruebas en verde**

Run: php tests/inquiry-snapshot-test.php && php tests/inquiry-context-test.php && php tests/mautic-payload-builder-test.php && php tests/inquiry-marketing-tags-test.php

Expected: los cuatro tests terminan OK.

- [ ] **Step 5: Commit**

    git add modules/consultas/services tests/inquiry-snapshot-test.php tests/inquiry-context-test.php tests/mautic-payload-builder-test.php tests/inquiry-marketing-tags-test.php
    git commit -m "feat(consultas): unificar snapshots y etiquetas"

### Task 4: Asegurar destinatario minimo y validar contrato Mautic

**Files:**
- Create: modules/consultas/services/class-flacso-mautic-contract-manifest.php
- Create: modules/consultas/services/class-flacso-mautic-contract-validator.php
- Create: tests/mautic-contract-validator-test.php
- Modify: includes/integrations/class-flacso-mautic-client.php
- Modify: tests/mautic-client-test.php

**Interfaces:**
- Produces: FLACSO_Mautic_Client::ensure_delivery_recipient(string $email, string $first_name, string $last_name): array.
- Produces: FLACSO_Mautic_Contract_Validator::validate(): array.
- Consumes: FLACSO_Mautic_Contract_Manifest::definition(): array con template_id, functional_version y content_sha256.

- [ ] **Step 1: Escribir pruebas de destinatario y manifiesto**

Usar el transporte inyectable. Simular contacto existente y afirmar PATCH limitado a email, firstname y lastname, sin tags ni aliases flacso_. Simular timeout de POST, buscar por email y afirmar resultado incierto si la conciliacion no encuentra contacto. Probar alias, tipo, select, plantilla publicada y hash; cada divergencia invalida contrato.

- [ ] **Step 2: Ejecutar pruebas en rojo**

Run: php tests/mautic-client-test.php && php tests/mautic-contract-validator-test.php

Expected: FAIL porque faltan operaciones, manifiesto y validador.

- [ ] **Step 3: Implementar destinatario idempotente**

Buscar primero por email; actualizar minimo si existe; crear si no existe. Ante timeout de POST, buscar nuevamente por email. Si no se puede demostrar el resultado, devolver acceptance_unknown y no emitir otro POST.

- [ ] **Step 4: Implementar manifiesto y validador solo lectura**

Definir campos permitidos, opciones, tags, template_id, version funcional, SHA-256 y campana opcional. Leer campos y plantilla de Mautic, normalizar contenido segun manifiesto y comparar hash. Si no puede leerse o no coincide, devolver bloqueado sin mutar Mautic.

- [ ] **Step 5: Ejecutar pruebas en verde**

Run: php tests/mautic-client-test.php && php tests/mautic-contract-validator-test.php

Expected: no hay contactos duplicados y toda divergencia bloquea la cola.

- [ ] **Step 6: Commit**

    git add includes/integrations/class-flacso-mautic-client.php modules/consultas/services/class-flacso-mautic-contract-manifest.php modules/consultas/services/class-flacso-mautic-contract-validator.php tests/mautic-client-test.php tests/mautic-contract-validator-test.php
    git commit -m "feat(mautic): validar contrato y destinatario minimo"

### Task 5: Implementar procesador de entrega y conciliacion

**Files:**
- Create: modules/consultas/services/class-flacso-inquiry-delivery-service.php
- Create: tests/inquiry-delivery-service-test.php
- Modify: includes/database/repositories/class-flacso-inquiry-delivery-repository.php
- Modify: includes/integrations/class-flacso-mautic-client.php

**Interfaces:**
- Produces: FLACSO_Inquiry_Delivery_Service::process(string $delivery_id): array.
- Produces: estados accepted, retryable_failed, failed, blocked o acceptance_unknown.

- [ ] **Step 1: Escribir prueba de maquina de estados**

Probar contrato invalido a blocked, envio HTTP 2xx a accepted, fallo comprobado antes de POST a retryable_failed y timeout tras iniciar POST a acceptance_unknown. Afirmar que una segunda llamada no reenvia. Enviar dos snapshots del mismo email y afirmar payload y huella propios.

- [ ] **Step 2: Ejecutar prueba en rojo**

Run: php tests/inquiry-delivery-service-test.php

Expected: FAIL porque no existe procesador.

- [ ] **Step 3: Implementar proceso unico**

Validar manifiesto, asegurar destinatario, crear attempt_id y persistir inicio antes de solicitar envio. Guardar payload exacto solo en entrega restringida y respuestas redactadas en intento. Clasificar respuesta perdida despues de inicio como acceptance_unknown; nunca como retry.

- [ ] **Step 4: Ejecutar prueba en verde y commit**

Run: php tests/inquiry-delivery-service-test.php

Expected: OK y ningun resultado incierto produce segundo POST.

    git add modules/consultas/services/class-flacso-inquiry-delivery-service.php includes/database/repositories/class-flacso-inquiry-delivery-repository.php includes/integrations/class-flacso-mautic-client.php tests/inquiry-delivery-service-test.php
    git commit -m "feat(consultas): procesar acuses transaccionales"

### Task 6: Ejecutar cola por cron de servidor y aplicar retencion

**Files:**
- Create: modules/consultas/services/class-flacso-inquiry-delivery-worker.php
- Create: modules/consultas/includes/class-flacso-consultas-cli.php
- Create: tests/inquiry-delivery-worker-test.php
- Create: docs/operations/consultas-transaccionales.md
- Modify: modules/consultas/init.php

**Interfaces:**
- Produces: FLACSO_Inquiry_Delivery_Worker::run(int $limit = 10): array.
- Produces: wp flacso consultas deliveries run --limit=10 and wp flacso consultas deliveries retention.

- [ ] **Step 1: Escribir pruebas de bloqueo y retencion**

Probar que dos workers no reclaman el mismo lote, que una reserva vencida no vuelve a pending y que retention anonimiza entregas terminales de mas de 90 dias. Afirmar que init no registra un hook WP-Cron.

- [ ] **Step 2: Ejecutar prueba en rojo**

Run: php tests/inquiry-delivery-worker-test.php

Expected: FAIL porque no existe worker ni comando.

- [ ] **Step 3: Implementar worker y WP-CLI**

Reclamar lotes por SQL condicional o bloqueo de filas, que es la garantia de idempotencia. Usar bloqueo global con token y vencimiento solo como proteccion adicional contra ejecuciones paralelas, lote maximo de 10 y delegacion al servicio. Registrar CLI solo bajo WP_CLI. El comando manual de una entrega acceptance_unknown debe requerir opcion explicita de conciliacion.

- [ ] **Step 4: Documentar cron y ejecutar pruebas en verde**

Documentar cron de servidor cada minuto, ruta WP, salida protegida, rollback, retencion y conciliacion. No instalarlo aun.

Run: php tests/inquiry-delivery-worker-test.php && php tests/inquiry-plugin-loader-test.php

Expected: OK; no hay WP-Cron de consultas.

- [ ] **Step 5: Commit**

    git add modules/consultas/services/class-flacso-inquiry-delivery-worker.php modules/consultas/includes/class-flacso-consultas-cli.php modules/consultas/init.php tests/inquiry-delivery-worker-test.php docs/operations/consultas-transaccionales.md
    git commit -m "feat(consultas): ejecutar cola transaccional por cron"

### Task 7: Separar marketing, migrar submits y retirar legado tras piloto

**Files:**
- Create: tests/inquiry-marketing-consent-test.php
- Modify: modules/consultas/services/class-flacso-inquiry-marketing-service.php
- Modify: modules/consultas/services/class-flacso-offer-inquiry-service.php
- Modify: modules/consultas/services/class-flacso-seminar-inquiry-service.php
- Modify: modules/consultas/includes/class-flacso-consultas-admin.php
- Modify: docs/mautic/data-contract.md

**Interfaces:**
- Produces: FLACSO_Inquiry_Marketing_Service::sync_commercial_contact(array $snapshot, array $consent): array.
- Consumes: granted, acceptedAt, source y textVersion.

- [ ] **Step 1: Escribir pruebas de consentimiento y submit**

Probar que falta de consentimiento crea snapshot y entrega pero no llama campana. Probar consentimiento incompleto como no apto y completo con tags acumulativos. Probar que submit no llama Mailjet ni ejecuta el bloque posterior al retorno.

- [ ] **Step 2: Ejecutar prueba en rojo**

Run: php tests/inquiry-marketing-consent-test.php && php tests/inquiry-services-test.php

Expected: FAIL porque el flujo actual mezcla consulta, contacto y campana.

- [ ] **Step 3: Implementar la separacion**

Hacer que oferta y seminario construyan snapshot y usen persistencia atomica. Extraer sincronizacion comercial con consentimiento validado; nunca desde el camino de acuse. Mostrar en consola entrega, contrato y conciliaciones sin payload ni PII.

- [ ] **Step 4: Ejecutar piloto controlado**

No ejecutar acciones externas dentro de esta tarea. Documentar el piloto requerido: con backup validado, contacto interno y autorizacion temporal, procesar dos consultas del mismo email y revisar ambos acuses accepted. Confirmar que no ingresa a campana comercial sin consentimiento.

- [ ] **Step 5: Retirar legado solo despues de piloto aprobado**

Eliminar bloques inalcanzables, IDs y ramas Mailjet de consultas, pero no campos Mautic, campanas, contactos ni clases usadas por otros modulos. Sustituir tests con exit(0) por cobertura real.

- [ ] **Step 6: Ejecutar regresion focalizada y commit**

Run: php tests/inquiry-snapshot-test.php && php tests/inquiry-delivery-repository-test.php && php tests/mautic-contract-validator-test.php && php tests/inquiry-delivery-service-test.php && php tests/inquiry-delivery-worker-test.php && php tests/inquiry-marketing-consent-test.php && php tests/inquiry-services-test.php && php tests/inquiry-handler-wiring-test.php && php tests/error-notifier-test.php && git diff --check

Expected: todas OK; ni texto libre en Mautic ni Mailjet en servicios de consultas.

    git add modules/consultas/services modules/consultas/includes/class-flacso-consultas-admin.php docs/mautic/data-contract.md tests
    git commit -m "feat(consultas): separar acuse y marketing"

### Task 8: Publicar y activar con evidencia separada

**Files:**
- Modify: docs/operations/consultas-transaccionales.md
- Modify: docs/mautic/campaigns.md

**Interfaces:**
- Consumes: Tasks 1-7, backup, migracion revisada y piloto aprobado.
- Produces: SHA desplegado, cron, cola, manifiesto y evidencia interna documentados.

- [ ] **Step 1: Validar y publicar main**

Run: git status --short && git diff origin/main...HEAD --check && git push origin main

Expected: solo cambios de este plan, sin secretos, payloads reales ni PII.

- [ ] **Step 2: Desplegar con respaldo**

Crear y validar backup antes de migrar. Desplegar SHA publicado, aplicar migracion una vez y comprobar tablas, indices y permisos. No migrar sin backup restaurable.

- [ ] **Step 3: Instalar cron y verificar contrato**

Instalar cron por minuto, verificar usuario, ruta y salida protegida. Con cola apagada, ejecutar validador solo lectura y comprobar campos, plantilla, hash y estado.

- [ ] **Step 4: Prueba interna y reporte**

Solicitar autorizacion operativa separada antes de activar cola, crear recursos internos, instalar cron o enviar dos entregas. Tras aprobarla, verificar snapshots, intentos y accepted; mantener campana comercial inactiva. Reportar por separado pruebas, CI, SHA, migracion, cron, manifiesto, aceptacion API y entrega interna; no afirmar entrega general por un HTTP 2xx.

---

## Self-Review

- Cobertura: Tasks 2-3 implementan snapshots, atomicidad y tags; Tasks 4-5 cubren Mautic, destinatario, manifiesto, huella e idempotencia; Task 6 cubre cron y retencion; Task 7 cubre consentimiento y retiro gradual; Task 8 cubre despliegue reversible.
- Interfaces: el procesador es el unico llamador de send_email_to_contact; marketing no recibe delivery_id y no altera estados de entrega.
- Riesgo externo: tokens y entrega interna son puertas operativas en Tasks 1 y 8, no supuestos de codigo.
- Proporcion: cada tarea produce una unidad testeable y el borrado legacy solo ocurre despues del piloto.
