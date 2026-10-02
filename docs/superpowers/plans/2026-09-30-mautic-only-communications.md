# Comunicaciones Solo Mautic Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Retirar Mailjet y los envios directos por plantilla de WordPress para que Mautic sea el unico sistema que ejecuta comunicaciones.

**Architecture:** WordPress guarda snapshots, sincroniza el contacto y lo incorpora a la campana configurada. Mautic decide correos y automatizaciones; WordPress no llama a proveedores de correo ni endpoints de envio de plantillas. Opciones y datos historicos se preservan sin uso operativo.

**Tech Stack:** WordPress 6+, PHP 7.4, PDO PostgreSQL, API REST Mautic, pruebas PHP autonomas.

**Spec:** `docs/superpowers/specs/2026-09-30-mautic-only-communications-design.md`

## Global Constraints

- No eliminar opciones ni columnas historicas automaticamente.
- Mautic es el unico canal de nuevas comunicaciones.
- La campana sigue opt-in: flag `0` e ID `0` por defecto.
- La consola no muestra Mailjet ni IDs de plantillas Mautic.
- Los logs no incluyen PII, payloads ni secretos.

## Review Focus

- Campana apagada o sin ID: se persiste y sincroniza la consulta sin membership. Task 2.
- Timeout/rechazo Mautic: respuesta publica exitosa tras persistencia y cero llamadas Mailjet. Task 2.
- Filas historicas Mailjet: permanecen legibles, sin reintentos de proveedor. Task 3.
- Suscripcion legado: no hace HTTP Mailjet ni expone listas. Task 3.
- Consola: no contiene controles o copy de Mailjet o plantillas. Task 1.

---

### Task 1: Simplificar consola a Mautic

**Files:**
- Modify: `modules/mailing/includes/class-flacso-mail-settings.php`
- Modify: `modules/mailing/init.php`
- Modify: `includes/core/class-flacso-integrations-settings.php`
- Test: `tests/mail-settings-mautic-only-test.php`

**Interfaces:**
- Consumes: `FLACSO_Mautic_Client::get_settings()` and `FLACSO_Mail_Settings::get_mautic_campaign_settings(): array`.
- Produces: pantalla `flacso-correos` con conexion Mautic y campana, sin controles Mailjet o plantillas.

- [ ] **Step 1: Write failing UI test**

Assert source has campaign option keys and has none of `flacso_mailjet_`, `Mailjet`, `flacso_mautic_template_`, or direct-send controls.

- [ ] **Step 2: Run RED**

Run: `php tests/mail-settings-mautic-only-test.php`
Expected: FAIL because legacy controls remain.

- [ ] **Step 3: Implement Mautic-only console**

Keep connection and campaign settings; remove Mailjet registration/rendering/AJAX and direct template controls. Remove the Mailjet integrations card.

- [ ] **Step 4: Run GREEN and commit**

Run: `php tests/mail-settings-mautic-only-test.php`
Expected: PASS.

Commit: `git add modules/mailing/includes/class-flacso-mail-settings.php modules/mailing/init.php includes/core/class-flacso-integrations-settings.php tests/mail-settings-mautic-only-test.php && git commit -m "feat(comunicaciones): orientar consola solo a mautic"`

### Task 2: Detener despachos desde WordPress

**Files:**
- Modify: `modules/consultas/services/class-flacso-offer-inquiry-service.php`
- Modify: `modules/consultas/services/class-flacso-seminar-inquiry-service.php`
- Modify: `modules/consultas/services/class-flacso-inquiry-followup-service.php`
- Test: `tests/inquiry-marketing-tags-test.php`
- Test: `tests/inquiry-services-test.php`

**Interfaces:**
- Consumes: `FLACSO_Inquiry_Marketing_Service::sync_inquiry(string|int, array, ?FLACSO_Offer_Inquiry_Repository): array`.
- Produces: nuevos flujos con `emailStatus='skipped'`, `emailSender='mautic_campaign'`, y sin llamadas a Mailjet o `send_email_to_contact`.

- [ ] **Step 1: Write failing no-dispatch tests**

Cover successful and failed Mautic sync after persistence, zero Mailjet/direct-template calls, and skipped WP-Cron follow-up delivery.

- [ ] **Step 2: Run RED**

Run: `php tests/inquiry-marketing-tags-test.php && php tests/inquiry-services-test.php`
Expected: FAIL because services dispatch providers.

- [ ] **Step 3: Make campaign sync terminal**

Remove engine selection, fallback/list sync and direct template branches. Retain contact/campaign sync and historical follow-up records without provider delivery.

- [ ] **Step 4: Run GREEN and commit**

Run: `php tests/inquiry-marketing-tags-test.php && php tests/inquiry-services-test.php`
Expected: PASS.

Commit: `git add modules/consultas/services/class-flacso-offer-inquiry-service.php modules/consultas/services/class-flacso-seminar-inquiry-service.php modules/consultas/services/class-flacso-inquiry-followup-service.php tests/inquiry-marketing-tags-test.php tests/inquiry-services-test.php && git commit -m "feat(consultas): delegar comunicaciones a mautic"`

### Task 3: Retirar caminos Mailjet de legado

**Files:**
- Modify: `modules/mailing/includes/class-flacso-mailing-subscription.php`
- Modify: `modules/consultas/includes/class-flacso-consultas-admin.php`
- Modify: `modules/consultas/init.php`
- Test: `tests/mailing-subscription-test.php`
- Test: `tests/consultas-admin-test.php`

**Interfaces:**
- Produces: suscripcion y reintentos que no llaman Mailjet; el backoffice retiene lectura historica y reintento Mautic unicamente.

- [ ] **Step 1: Write failing legacy-path tests**

Assert subscription and manual retry make zero Mailjet requests, including historical `mailjet_fallback` rows.

- [ ] **Step 2: Run RED**

Run: `php tests/mailing-subscription-test.php && php tests/consultas-admin-test.php`
Expected: FAIL because legacy code dispatches Mailjet.

- [ ] **Step 3: Remove Mailjet dispatch dependencies**

Stop loading the Mailjet client and use Mautic sync/campaign or explicit unavailable response without campaign context.

- [ ] **Step 4: Run GREEN and commit**

Run: `php tests/mailing-subscription-test.php && php tests/consultas-admin-test.php`
Expected: PASS.

Commit: `git add modules/mailing/includes/class-flacso-mailing-subscription.php modules/consultas/includes/class-flacso-consultas-admin.php modules/consultas/init.php tests/mailing-subscription-test.php tests/consultas-admin-test.php && git commit -m "refactor(mailing): retirar ejecucion mailjet"`

### Task 4: Validacion y publicacion

**Files:**
- Modify: `README.md`

**Interfaces:**
- Consumes: Tasks 1-3.
- Produces: documentacion de Mautic como canal unico y evidencia de publicacion.

- [ ] **Step 1: Update documentation**

Replace Mailjet communication claims with campaign-based Mautic flow.

- [ ] **Step 2: Validate all PHP code and tests**

Run: `python3 .github/scripts/check_encoding.py && while IFS= read -r -d '' f; do php -l "$f"; done < <(find . -type f -name '*.php' -not -path './vendor/*' -not -path './node_modules/*' -print0) && while IFS= read -r -d '' t; do php "$t"; done < <(find tests -type f -name '*-test.php' -print0 | sort -z)`
Expected: PASS.

- [ ] **Step 3: Commit, push and verify release**

Commit: `git add README.md && git commit -m "docs(comunicaciones): documentar canal unico mautic" && git push origin main`

Verify the exact GitHub Actions SHA, deployed plugin version, active plugin state, and public HTTP health.
