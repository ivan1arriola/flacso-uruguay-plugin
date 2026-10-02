# WordPress a Mautic: Campana de Consultas Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Sincronizar cada consulta academica persistida con Mautic usando el contrato `flacso_*`, tags acumulativos y una campana unica, sin IDs individuales de email ni WP-Cron de marketing.

**Architecture:** Un builder crea el payload desde el snapshot guardado. El cliente Mautic fusiona tags y agrega el contacto a una campana idempotentemente; WordPress persiste estados de sync y campana. La activacion usa un flag y piloto controlado; el seguimiento diferido queda fuera de alcance.

**Tech Stack:** PHP 7.4+, WordPress 6.x, PostgreSQL, SQLite para pruebas, Mautic 7 API REST y Mailjet v3.1.

**Spec:** `docs/superpowers/specs/2026-09-30-wordpress-mautic-campaign-architecture-design.md`

## Global Constraints

- Guardar primero: un fallo de Mautic nunca invalida una consulta publica.
- No renombrar, borrar ni regenerar campos/tags legacy de Mautic.
- Los nuevos aliases usan `flacso_`/snake_case; tags nuevos usan kebab-case y se fusionan sin borrar historial.
- Mailjet solo es fallback ante un fallo real de API; una campana aceptada no prueba entrega.
- No guardar ni registrar credenciales, email completo, mensaje ni payload completo.
- No activar, migrar ni eliminar seguimiento WP-Cron en este plan.
- Produccion requiere backup, SHA exacto, piloto autorizado y rollback verificado.

## Review Focus

- Tags legacy y nuevos deben coexistir sin reemplazo: Tarea 3.
- Cohorte ausente o fecha imprecisa debe producir campos vacios validos: Tarea 2.
- Falla al agregar a campana deja estado reintentable sin fallback por espera normal: Tareas 4-5.
- Timeout Mautic conserva la consulta y el exito publico: Tarea 5.
- Reintento de membership ya existente es idempotente: Tareas 3-4.

## Mapa de archivos

| Archivo | Responsabilidad |
| --- | --- |
| `modules/consultas/services/class-flacso-mautic-payload-builder.php` | payload y tags canonicos |
| `class-flacso-inquiry-marketing-service.php` | orquestacion sync/campana |
| `includes/integrations/class-flacso-mautic-client.php` | API Mautic y membership |
| `class-flacso-offer-inquiry-repository.php` | estado persistente de campana |
| `includes/core/class-flacso-mautic-integration-log.php` | log estructurado y sin PII de operaciones Mautic |
| `scripts/migrations/2026-09-30-offer-inquiries-mautic-campaign.sql` | columnas aditivas e indices |
| `class-flacso-offer-inquiry-service.php` | flujo publico no bloqueante |
| `class-flacso-mail-settings.php` | configuracion, flag y diagnostico |
| `class-flacso-consultas-admin.php` | reintento y consola |
| `docs/mautic/*.md` | contrato, operaciones y piloto |

### Task 1: Documentar contrato y piloto

**Files:**
- Create: `docs/mautic/data-contract.md`, `docs/mautic/tags-and-fields.md`, `docs/mautic/campaigns.md`, `docs/mautic/operations.md`

**Interfaces:** Produce la fuente de verdad de 18 aliases `flacso_*`, tipos, origen, valores cerrados, tags, campos legacy preservados, campana manual y rollback.

- [ ] **Step 1: Documentar el contrato**

Copiar los 18 aliases de la especificacion, incluyendo los valores exactos `oferta|seminario`, `abierta|cerrada|sin_cohorte` y `virtual|presencial|hibrida`.

- [ ] **Step 2: Documentar legacy y campana**

Declarar que `last_program_*`, `last_record_*`, `intereses`, `acquisition_source` y `interes:*` se preservan. Definir la campana manual `Consulta academica`, un contacto de piloto y que no hay waits ni followups.

- [ ] **Step 3: Verificar ausencia de secretos y commit**

Run: `rg -n -i 'password|secret|token' docs/mautic && git add docs/mautic && git commit -m "docs(mautic): documentar contrato y piloto de consultas"`

Expected: solo menciones normativas, nunca valores.

### Task 2: Crear el builder canonico

**Files:**
- Create: `modules/consultas/services/class-flacso-mautic-payload-builder.php`, `tests/mautic-payload-builder-test.php`
- Modify: `modules/consultas/init.php`

**Interfaces:** Produce `FLACSO_Mautic_Payload_Builder::build(array $inquiry): array{fields: array, tags: string[]}`.

- [ ] **Step 1: Escribir test fallido**

Probar snapshots abierta, cerrada, sin cohorte, seminario, modalidad desconocida y fecha imprecisa. Para DAVIA C10 exigir `interes-davia`, `davia-c10`, `origen-web-consultas` y aliases exactos.

- [ ] **Step 2: Ejecutar RED**

Run: `php tests/mautic-payload-builder-test.php`

Expected: FAIL porque la clase no existe.

- [ ] **Step 3: Implementar builder**

Construir solo desde datos persistidos, normalizar estado/modalidad, omitir fechas imprecisas y no incluir HTML, legacy tags ni secretos.

- [ ] **Step 4: Ejecutar GREEN y commit**

Run: `php tests/mautic-payload-builder-test.php`

Expected: PASS.

Commit: `git add modules/consultas/services/class-flacso-mautic-payload-builder.php modules/consultas/init.php tests/mautic-payload-builder-test.php && git commit -m "feat(mautic): add canonical inquiry payload builder"`

### Task 3: Agregar tags no destructivos y campaign membership

**Files:**
- Modify: `includes/integrations/class-flacso-mautic-client.php`, `tests/mautic-client-test.php`

**Interfaces:** Produce `get_contact_tags(int): array`, `merge_contact_tags(int, array): array`, `add_contact_to_campaign(int, int): array`, `get_contact_campaigns(int): array`; cada resultado incluye `ok`, `status`, `http_code` y `error` sin body.

- [ ] **Step 1: Escribir tests HTTP fallidos**

Mockear tags anteriores e incoming tags, alta de campana, miembro existente, 401, 422, 500 y timeout. Afirmar que ningun tag existente desaparece ni el body contiene credenciales.

- [ ] **Step 2: Ejecutar RED**

Run: `php tests/mautic-client-test.php`

Expected: FAIL por metodos ausentes.

- [ ] **Step 3: Implementar cliente**

Obtener tags antes de actualizar, unir valores unicos no vacios y tratar membership confirmado como exito idempotente.

- [ ] **Step 4: Ejecutar GREEN y commit**

Run: `php tests/mautic-client-test.php`

Expected: PASS.

Commit: `git add includes/integrations/class-flacso-mautic-client.php tests/mautic-client-test.php && git commit -m "feat(mautic): preserve tags and add campaign membership client"`

### Task 4: Persistir el estado de campana

**Files:**
- Create: `scripts/migrations/2026-09-30-offer-inquiries-mautic-campaign.sql`
- Modify: `includes/database/repositories/class-flacso-offer-inquiry-repository.php`, `tests/inquiry-repositories-test.php`

**Interfaces:** Produce `update_mautic_campaign_status(string $id, array $state): bool` con `mauticCampaignId`, `mauticCampaignStatus`, `mauticCampaignAttemptedAt`, `mauticCampaignAttempts`, `mauticCampaignLastError`.

- [ ] **Step 1: Escribir tests fallidos**

Extender SQLite y verificar `pending`, `joined`, fallo, incremento acotado de attempts e inmutabilidad del snapshot.

- [ ] **Step 2: Ejecutar RED**

Run: `php tests/inquiry-repositories-test.php`

Expected: FAIL por columnas y metodo ausentes.

- [ ] **Step 3: Implementar migracion aditiva y repositorio**

Usar solo `ADD COLUMN IF NOT EXISTS`, defaults compatibles e indice por estado/fecha. Validar transiciones sin modificar PII.

- [ ] **Step 4: Ejecutar GREEN, revisar SQL y commit**

Run: `php tests/inquiry-repositories-test.php && sed -n '1,220p' scripts/migrations/2026-09-30-offer-inquiries-mautic-campaign.sql`

Expected: PASS y SQL sin operaciones destructivas.

Commit: `git add scripts/migrations/2026-09-30-offer-inquiries-mautic-campaign.sql includes/database/repositories/class-flacso-offer-inquiry-repository.php tests/inquiry-repositories-test.php && git commit -m "feat(consultas): persist mautic campaign synchronization state"`

### Task 5: Orquestar campana bajo flag

**Files:**
- Modify: `modules/consultas/services/class-flacso-inquiry-marketing-service.php`, `modules/consultas/services/class-flacso-offer-inquiry-service.php`, `modules/mailing/includes/class-flacso-mail-settings.php`
- Create: `includes/core/class-flacso-mautic-integration-log.php`, `tests/mautic-integration-log-test.php`
- Modify: `flacso-uruguay.php`
- Test: `tests/inquiry-marketing-tags-test.php`, `tests/inquiry-services-test.php`

**Interfaces:** Consume builder, cliente y repositorio. Produce sync independiente de campaign, controlada por `flacso_mautic_campaign_enabled` y `flacso_mautic_campaign_consultas_id`.

- [ ] **Step 1: Escribir tests fallidos**

Cubrir flag apagado, flag activo, timeout, rechazo de campana y formulario exitoso tras persistir. Afirmar que el fallback no corre por una espera normal de Mautic. En `tests/mautic-integration-log-test.php`, capturar el logger y afirmar que el email se hashea y que token, password, mensaje y payload no aparecen.

- [ ] **Step 2: Ejecutar RED**

Run: `php tests/inquiry-marketing-tags-test.php && php tests/inquiry-services-test.php`

Expected: FAIL porque no existe configuracion ni orquestacion de campana.

- [ ] **Step 3: Registrar ajustes e implementar sync**

Registrar flag default `0` e ID default `0`; builder, upsert, merge de tags y membership actualizan estados separados. Crear `FLACSO_Mautic_Integration_Log::write(array $event): void` para serializar solo consulta, contacto, campana, operacion, resultado, HTTP, error clasificado, fecha y hash de email. `submit()` nunca falla publicamente despues de guardar la consulta.

- [ ] **Step 4: Ejecutar GREEN y commit**

Run: `php tests/mautic-integration-log-test.php && php tests/inquiry-marketing-tags-test.php && php tests/inquiry-services-test.php`

Expected: PASS.

Commit: `git add flacso-uruguay.php includes/core/class-flacso-mautic-integration-log.php modules/consultas/services/class-flacso-inquiry-marketing-service.php modules/consultas/services/class-flacso-offer-inquiry-service.php modules/mailing/includes/class-flacso-mail-settings.php tests/mautic-integration-log-test.php tests/inquiry-marketing-tags-test.php tests/inquiry-services-test.php && git commit -m "feat(consultas): sync canonical snapshots into mautic campaign"`

### Task 6: Consola, reintento y piloto

**Files:**
- Modify: `modules/mailing/includes/class-flacso-mail-settings.php`, `modules/consultas/includes/class-flacso-consultas-admin.php`, `docs/mautic/operations.md`
- Test: `tests/mail-console-and-consultas-admin-test.php`

**Interfaces:** Muestra conexion, sync, campana y fallback; permite retry idempotente solo de membership fallido.

- [ ] **Step 1: Escribir tests fallidos**

Verificar panel Comunicaciones, ausencia de template IDs en el flujo nuevo, controles `manage_options`, errores sin email/mensaje, y retry de campaign membership.

- [ ] **Step 2: Ejecutar RED**

Run: `php tests/mail-console-and-consultas-admin-test.php`

Expected: FAIL por panel y retry ausentes.

- [ ] **Step 3: Implementar UI y diagnostico**

Mantener Mailjet como contingencia secundaria; agregar resumen de readiness y accion de prueba controlada. No crear ni editar campanas desde WordPress.

- [ ] **Step 4: Ejecutar GREEN y commit**

Run: `php tests/mail-console-and-consultas-admin-test.php`

Expected: PASS.

Commit: `git add modules/mailing/includes/class-flacso-mail-settings.php modules/consultas/includes/class-flacso-consultas-admin.php docs/mautic/operations.md tests/mail-console-and-consultas-admin-test.php && git commit -m "feat(comunicaciones): add mautic campaign diagnostics and retry"`

- [ ] **Step 5: Ejecutar piloto manual con aprobacion explicita**

Respaldar la base, aplicar la migracion, crear los campos y `Consulta academica` manualmente, usar un contacto autorizado y verificar upsert, tags, fields y membership. Activar el flag solo despues del piloto y probar rollback apagandolo. Registrar SHA, CI, migracion, salud y evidencia por separado.

### Task 7: Regresion y decision de seguimiento diferido

**Files:**
- Modify: `docs/mautic/operations.md`

- [ ] **Step 1: Ejecutar regresion completa**

Run: `for test_file in tests/*-test.php; do php "$test_file" || exit 1; done && cd ../kadence-child-flacso && for test_file in tests/*-test.php; do php "$test_file" || exit 1; done && bash bin/check-theme.sh`

Expected: todos los comandos terminan en cero.

- [ ] **Step 2: Verificar sintaxis y whitespace**

Run: `cd ../flacso-uruguay-plugin && for file in $(rg --files -g '*.php' -g '!vendor/**'); do php -l "$file" >/dev/null || exit 1; done && git diff --check`

Expected: cero errores.

- [ ] **Step 3: Documentar el bloqueo de followup y publicar**

Registrar que `flacso_inquiry_followup_enabled` queda `0`, sin eventos diferidos Mautic, hasta una especificacion separada de contexto por consulta.

Commit: `git add docs/mautic/operations.md && git commit -m "docs(mautic): record campaign pilot and deferred followup" && git push origin main`

## Spec Coverage Review

Contrato, campos, tags y legacy: Tareas 1-2. Cliente y membership: Tarea 3. Estados reintentables: Tareas 4-5. Configuracion, consola y Mailjet contingencia: Tareas 5-6. Piloto y rollback: Tarea 6. Seguimiento multiple-consulta: explicitamente diferido en Tarea 7; no tiene implementacion en este plan.
