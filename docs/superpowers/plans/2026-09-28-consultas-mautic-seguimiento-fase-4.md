# Seguimiento Automático (+X días) con Reevaluación de Cohorte (Fase 4) Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Implementar el seguimiento automático programado (+X días) de consultas de oferta académica con reevaluación dinámica de cohorte en WordPress, despacho vía Mautic, fallback transparente a Mailjet y control administrativo en PostgreSQL.

**Architecture:** Scheduler periódico en WP-Cron (`flacso_inquiry_followup_cron`) con reclamación atómica en `offer_inquiries` (`claim_due_followups()`), reevaluación del catálogo fresco de ofertas (`FLACSO_Academic_Catalog::get_offer()`), compilación de tokens canónicos, fallback automático a Mailjet ante fallos de Mautic y controles en `FLACSO_Consultas_Admin`.

**Tech Stack:** PHP 7.4/8.x, WordPress WP-Cron, PostgreSQL / SQLite (PDO), Mautic REST API, Mailjet Send API v3.1.

**Spec:** `flacso-uruguay-plugin/docs/superpowers/specs/2026-09-28-consultas-mautic-seguimiento-fase-4-design.md`

## Global Constraints

- Compatible con PostgreSQL (producción) y SQLite (entorno de pruebas automáticas).
- Sin bloqueos en el formulario web: el aspirante recibe respuesta HTTP 200 inmediata.
- Reclamación atómica (`'pending'` -> `'processing'`) para evitar concurrencia y duplicación en WP-Cron.
- Reevaluación obligatoria de la cohorte al día de vencimiento para que los tokens reflejen el estado fresco (inscripciones abiertas, fecha de inicio, cupos).
- Fallback automático a Mailjet si Mautic no está disponible o falla su API.
- TDD estricto en cada tarea con tests unitarios independientes y cero regresiones en las 48 suites existentes.

---

### Task 1: Métodos de Reclamación y Consulta en `FLACSO_Offer_Inquiry_Repository`

**Files:**
- Modify: `includes/database/repositories/class-flacso-offer-inquiry-repository.php`
- Modify: `tests/inquiry-repositories-test.php`

**Interfaces:**
- Produces:
  - `claim_due_followups(int $limit = 25): array`
  - `update_followup_status(string $id, string $status, ?string $error = null, ?string $sent_at = null): bool`
  - `has_newer_inquiry_for_offer(string $email_normalized, int $offer_wp_id, string $current_inquiry_at): bool`

- [ ] **Step 1: Escribir pruebas unitarias que fallen**
  En `tests/inquiry-repositories-test.php`, añadir grupo de pruebas para `claim_due_followups()`, `update_followup_status()` y `has_newer_inquiry_for_offer()`.
  Verificar que sólo reclame registros donde `followupStatus = 'pending'` y `followupDueAt <= NOW()` y `followupAttempts < 3`, transicionándolos atómicamente a `'processing'`.

- [ ] **Step 2: Ejecutar el test para comprobar fallo**
  ```bash
  php tests/inquiry-repositories-test.php
  ```

- [ ] **Step 3: Implementar métodos en `FLACSO_Offer_Inquiry_Repository`**
  Implementar `claim_due_followups()`, `update_followup_status()` y `has_newer_inquiry_for_offer()` con soporte de dialectos SQL para SQLite y PostgreSQL.

- [ ] **Step 4: Ejecutar el test para comprobar éxito**
  ```bash
  php tests/inquiry-repositories-test.php
  ```

- [ ] **Step 5: Commit de Task 1**
  ```bash
  git add includes/database/repositories/class-flacso-offer-inquiry-repository.php tests/inquiry-repositories-test.php
  git commit -m "feat(consultas): add followup claim and status update methods to offer inquiry repository"
  ```

---

### Task 2: Configuración de Seguimiento en `FLACSO_Mail_Settings`

**Files:**
- Modify: `modules/mailing/includes/class-flacso-mail-settings.php`
- Modify: `tests/mail-console-and-consultas-admin-test.php`

**Interfaces:**
- Produces:
  - Opciones registradas: `flacso_inquiry_followup_enabled`, `flacso_inquiry_followup_days`, `flacso_mautic_template_seguimiento_abierta`, `flacso_mautic_template_seguimiento_cerrada`.
  - Método `get_followup_settings(): array`.
  - Renderizado en `render_page()` con campos y ayuda contextual.

- [ ] **Step 1: Escribir pruebas unitarias que fallen**
  En `tests/mail-console-and-consultas-admin-test.php`, verificar el registro de las 4 opciones de seguimiento, sus valores por defecto y su presencia en el HTML renderizado.

- [ ] **Step 2: Ejecutar el test para comprobar fallo**
  ```bash
  php tests/mail-console-and-consultas-admin-test.php
  ```

- [ ] **Step 3: Implementar configuración en `FLACSO_Mail_Settings`**
  Registrar constantes, opciones en `register_settings()`, sanitizaciones y renderizado visual en la consola de correos.

- [ ] **Step 4: Ejecutar el test para comprobar éxito**
  ```bash
  php tests/mail-console-and-consultas-admin-test.php
  ```

- [ ] **Step 5: Commit de Task 2**
  ```bash
  git add modules/mailing/includes/class-flacso-mail-settings.php tests/mail-console-and-consultas-admin-test.php
  git commit -m "feat(mailing): add follow-up configuration settings and templates to mail console"
  ```

---

### Task 3: Programación Inicial de Seguimiento en `FLACSO_Offer_Inquiry_Service::submit()`

**Files:**
- Modify: `modules/consultas/services/class-flacso-offer-inquiry-service.php`
- Modify: `tests/inquiry-services-test.php`

**Interfaces:**
- Consumes: `flacso_inquiry_followup_enabled`, `flacso_inquiry_followup_days`.
- Produces: Asignación de `followupStatus` y `followupDueAt` en el array `$record` insertado en PostgreSQL.

- [ ] **Step 1: Escribir pruebas unitarias que fallen**
  En `tests/inquiry-services-test.php`, verificar que cuando `flacso_inquiry_followup_enabled` es `'1'`, el registro en base de datos contenga `followupStatus = 'pending'` y `followupDueAt` exactamente X días posterior a `inquiryAt`. Si está deshabilitado, `followupStatus = 'none'` y `followupDueAt = null`.

- [ ] **Step 2: Ejecutar el test para comprobar fallo**
  ```bash
  php tests/inquiry-services-test.php
  ```

- [ ] **Step 3: Implementar cálculo de fecha en `FLACSO_Offer_Inquiry_Service::submit()`**
  Agregar la evaluación de opciones y cálculo de `$followup_due_at` previo a `$repo->insert($record)`.

- [ ] **Step 4: Ejecutar el test para comprobar éxito**
  ```bash
  php tests/inquiry-services-test.php
  ```

- [ ] **Step 5: Commit de Task 3**
  ```bash
  git add modules/consultas/services/class-flacso-offer-inquiry-service.php tests/inquiry-services-test.php
  git commit -m "feat(consultas): calculate and schedule followupDueAt on inquiry submission"
  ```

---

### Task 4: Servicio de Seguimiento `FLACSO_Inquiry_Followup_Service`

**Files:**
- Create: `modules/consultas/services/class-flacso-inquiry-followup-service.php`
- Modify: `modules/consultas/init.php`
- Create: `tests/inquiry-followup-service-test.php`

**Interfaces:**
- Produces:
  - `FLACSO_Inquiry_Followup_Service::init()` (registra hook de cron `flacso_inquiry_followup_cron`).
  - `FLACSO_Inquiry_Followup_Service::run_followup_cycle(int $limit = 25): array`.
  - `FLACSO_Inquiry_Followup_Service::process_single_followup(array $inquiry): array`.

- [ ] **Step 1: Escribir pruebas unitarias completas que fallen**
  Crear `tests/inquiry-followup-service-test.php` con escenarios:
  - Reevaluación de cohorte: consulta original cerrada que ahora abrió -> envía plantilla de abierta con tokens frescos.
  - Consulta original abierta que sigue abierta -> envía recordatorio de preinscripción.
  - Descarte por consulta posterior (`has_newer_inquiry_for_offer`) -> marca `skipped`.
  - Descarte por oferta inexistente en WordPress -> marca `skipped`.
  - Fallo de Mautic -> conmutación automática inmediata a Mailjet.

- [ ] **Step 2: Ejecutar el test para comprobar fallo**
  ```bash
  php tests/inquiry-followup-service-test.php
  ```

- [ ] **Step 3: Implementar `FLACSO_Inquiry_Followup_Service`**
  Implementar la clase con la lógica de cron, reclamo de lotes, reevaluación de cohorte en `FLACSO_Academic_Catalog`, compilación de tokens y despacho con fallback. Registrar en `modules/consultas/init.php`.

- [ ] **Step 4: Ejecutar el test para comprobar éxito**
  ```bash
  php tests/inquiry-followup-service-test.php
  ```

- [ ] **Step 5: Commit de Task 4**
  ```bash
  git add modules/consultas/services/class-flacso-inquiry-followup-service.php modules/consultas/init.php tests/inquiry-followup-service-test.php
  git commit -m "feat(consultas): implement FLACSO_Inquiry_Followup_Service with dynamic cohort re-evaluation and cron cycle"
  ```

---

### Task 5: Interfaz Administrativa y Disparo Manual en `FLACSO_Consultas_Admin`

**Files:**
- Modify: `modules/consultas/includes/class-flacso-consultas-admin.php`
- Modify: `tests/mail-console-and-consultas-admin-test.php`

**Interfaces:**
- Produces:
  - Columna / badges de seguimiento en tabla: `pending`, `sent`, `skipped`, `failed`.
  - Bloque de seguimiento en modal de detalle.
  - Endpoint AJAX `wp_ajax_flacso_consultas_trigger_followup` para disparo manual inmediato.

- [ ] **Step 1: Escribir pruebas unitarias que fallen**
  En `tests/mail-console-and-consultas-admin-test.php`, verificar:
  - Generación de badges de seguimiento (`render_followup_status_badge()`).
  - Acción AJAX `ajax_trigger_followup()` con permisos, validación y respuesta JSON.

- [ ] **Step 2: Ejecutar el test para comprobar fallo**
  ```bash
  php tests/mail-console-and-consultas-admin-test.php
  ```

- [ ] **Step 3: Implementar en `FLACSO_Consultas_Admin`**
  Implementar `render_followup_status_badge()`, agregar el bloque al modal de detalle, botón `[ 🚀 Enviar Seguimiento Ahora ]` con handler JS y acción AJAX `ajax_trigger_followup()`.

- [ ] **Step 4: Ejecutar el test para comprobar éxito**
  ```bash
  php tests/mail-console-and-consultas-admin-test.php
  ```

- [ ] **Step 5: Commit de Task 5**
  ```bash
  git add modules/consultas/includes/class-flacso-consultas-admin.php tests/mail-console-and-consultas-admin-test.php
  git commit -m "feat(consultas): add followup status badges, detail modal section and manual trigger action"
  ```

---

### Task 6: Verificación Integral de Fase 4 y Regresión Completa

**Files:**
- Test: Todas las suites en `flacso-uruguay-plugin/tests/*-test.php`
- Test: Todas las suites en `kadence-child-flacso/tests/*-test.php` y `check-theme.sh`

- [ ] **Step 1: Ejecutar todas las pruebas del plugin**
  ```bash
  for t in tests/*-test.php; do php "$t" || exit 1; done
  ```

- [ ] **Step 2: Ejecutar pruebas del tema child**
  ```bash
  cd ../kadence-child-flacso && for t in tests/*-test.php; do php "$t" || exit 1; done && bash bin/check-theme.sh
  ```

- [ ] **Step 3: Revisión final por subagente Senior Code Reviewer (modelo pro)**
  Inspeccionar la rama completa de la Fase 4 contra la especificación técnica.
