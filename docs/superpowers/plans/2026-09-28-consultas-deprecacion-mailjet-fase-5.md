# Plan de Implementación: Deprecación de Mailjet y Consolidación de Mautic como Motor Primario (Fase 5)

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Consolidar a Mautic como motor primario y por defecto de envío de correos de consultas de oferta académica, relegando Mailjet a respaldo automático de contingencia (fail-safe), actualizando la consola de configuración, el helper de diagnóstico de salud y el reintento administrativo en `FLACSO_Consultas_Admin`.

**Architecture:** Mautic asume la titularidad por defecto (`flacso_inquiry_email_engine` por defecto `'mautic'`), manteniendo Mailjet como fallback de conmutación de emergencia y como motor primario para seminarios. Se agregan helpers de diagnóstico en `FLACSO_Mail_Settings`, se actualiza la interfaz de la consola de correos y se adapta el handler AJAX de reintento (`ajax_retry_email`) en `FLACSO_Consultas_Admin` para orquestar Mautic con fallback automático.

**Tech Stack:** PHP 8+, WordPress 6.x, PostgreSQL/SQLite, Mautic API REST, Mailjet Send API v3.1.

**Spec:** `docs/superpowers/specs/2026-09-28-consultas-deprecacion-mailjet-fase-5-design.md`

## Global Constraints

- Compatibilidad estricta con PHP 8.0+ y WordPress 6.x.
- Mantenimiento absoluto del principio "guardar primero, enviar después".
- Mailjet se mantiene 100% operativo como fallback automático ante fallos de Mautic y como motor principal para `seminario_inquiries`.
- Las 48 suites de pruebas existentes deben mantenerse en 100% verde sin excepciones ni regresiones.
- Pruebas unitarias autónomas ejecutables con `php tests/<test-file>.php` sin requerir servidor web activo.

---

### Task 1: Consolidación de Mautic por Defecto y Diagnóstico de Motores en `FLACSO_Mail_Settings`

**Files:**
- Modify: `modules/mailing/includes/class-flacso-mail-settings.php`
- Test: `tests/mail-console-and-consultas-admin-test.php`

**Interfaces:**
- Consumes: `FLACSO_Mail_Settings::OPTION_INQUIRY_EMAIL_ENGINE`, `FLACSO_Mautic_Client::is_configured()`
- Produces: `FLACSO_Mail_Settings::get_offer_inquiry_engine_status(): array`, `get_settings()['inquiry_email_engine']` por defecto `'mautic'`.

- [ ] **Paso 1: Escribir la prueba fallida**
  En `tests/mail-console-and-consultas-admin-test.php`, añadir comprobaciones para:
  1. `register_settings()` define default `'mautic'` para `OPTION_INQUIRY_EMAIL_ENGINE`.
  2. `get_settings()['inquiry_email_engine']` retorna `'mautic'` cuando no hay opción configurada.
  3. `FLACSO_Mail_Settings::get_offer_inquiry_engine_status()` devuelve el array con `engine`, `is_mautic_primary`, `mautic_ready`, `mailjet_fallback_ready`, y `status_label`.
  4. La salida de `render_page()` incluye los textos de Mautic como motor principal recomendado y Mailjet como modo legado / respaldo.

- [ ] **Paso 2: Ejecutar la prueba y verificar que falle**
  ```bash
  php tests/mail-console-and-consultas-admin-test.php
  ```

- [ ] **Paso 3: Implementar cambios en `FLACSO_Mail_Settings`**
  1. En `register_settings()`: cambiar default de `OPTION_INQUIRY_EMAIL_ENGINE` a `'mautic'`.
  2. En `get_settings()`: cambiar segundo argumento de `get_option(self::OPTION_INQUIRY_EMAIL_ENGINE, 'mautic')` a `'mautic'`.
  3. Implementar `get_offer_inquiry_engine_status(): array`.
  4. En `render_page()`:
     - Actualizar la tarjeta de KPIs para mostrar el estado del motor primario y fallback.
     - Actualizar el selector de motor con labels de Mautic (Principal y Recomendado) y Mailjet (Legado / Contingencia).
     - Rotular las plantillas Mailjet de ofertas como "Plantilla Mailjet de Respaldo (Fallback de Emergencia)".

- [ ] **Paso 4: Ejecutar la prueba y verificar que pase**
  ```bash
  php tests/mail-console-and-consultas-admin-test.php
  ```

- [ ] **Paso 5: Ejecutar la suite completa y confirmar 0 regresiones**
  ```bash
  for f in tests/*-test.php; do php "$f" > /dev/null || echo "FAILED: $f"; done
  ```

- [ ] **Paso 6: Commit**
  ```bash
  git commit -m "feat(mailing): set mautic as default email engine and add engine health diagnostics"
  ```

---

### Task 2: Motor por Defecto en `FLACSO_Offer_Inquiry_Service::submit()`

**Files:**
- Modify: `modules/consultas/services/class-flacso-offer-inquiry-service.php`
- Test: `tests/inquiry-services-test.php`

**Interfaces:**
- Consumes: `get_option('flacso_inquiry_email_engine', 'mautic')`
- Produces: `$result['email_engine'] === 'mautic'` por defecto al omitir la opción.

- [ ] **Paso 1: Escribir la prueba fallida**
  En `tests/inquiry-services-test.php`, agregar un escenario donde `flacso_inquiry_email_engine` no esté presente en `$GLOBALS['mailjet_mock_options']` (o sea nulo) y verificar que `submit()` despacha por el flujo de Mautic (o activa fallback si la plantilla no está lista) y reporta `email_engine === 'mautic'`.

- [ ] **Paso 2: Ejecutar la prueba y verificar que falle**
  ```bash
  php tests/inquiry-services-test.php
  ```

- [ ] **Paso 3: Implementar en `FLACSO_Offer_Inquiry_Service`**
  En `submit()`:
  ```php
  $engine = function_exists('get_option') ? (string) get_option('flacso_inquiry_email_engine', 'mautic') : 'mautic';
  $engine = strtolower(trim($engine));
  if ($engine !== 'mailjet') {
      $engine = 'mautic';
  }
  ```

- [ ] **Paso 4: Ejecutar la prueba y verificar que pase**
  ```bash
  php tests/inquiry-services-test.php
  ```

- [ ] **Paso 5: Ejecutar la suite completa y confirmar 0 regresiones**
  ```bash
  for f in tests/*-test.php; do php "$f" > /dev/null || echo "FAILED: $f"; done
  ```

- [ ] **Paso 6: Commit**
  ```bash
  git commit -m "feat(consultas): default offer inquiry submission engine to mautic"
  ```

---

### Task 3: Reintento Administrativo Resiliente en `FLACSO_Consultas_Admin::ajax_retry_email()`

**Files:**
- Modify: `modules/consultas/includes/class-flacso-consultas-admin.php`
- Test: `tests/mail-console-and-consultas-admin-test.php`

**Interfaces:**
- Consumes: `FLACSO_Offer_Inquiry_Repository`, `FLACSO_Inquiry_Marketing_Service::compile_tokens()`, `FLACSO_Mautic_Client::send_email_to_contact()`, `FLACSO_Mailjet_Client::send_offer_inquiry()`
- Produces: `ajax_retry_email()` con soporte para Mautic y fallback conmutado para ofertas, conservando Mailjet para seminarios.

- [ ] **Paso 1: Escribir la prueba fallida**
  En `tests/mail-console-and-consultas-admin-test.php`:
  1. Test de reintento para `offer_inquiries` con motor `'mautic'`: despacha vía Mautic y actualiza `emailStatus = 'sent'`, `emailSender = 'mautic'`.
  2. Test de reintento para `offer_inquiries` con fallo de Mautic: conmuta automáticamente a Mailjet y actualiza `emailStatus = 'sent'`, `emailSender = 'mailjet_fallback'`.
  3. Test de reintento para `seminario_inquiries`: despacha vía `FLACSO_Mailjet_Client::send_seminar_inquiry()`.

- [ ] **Paso 2: Ejecutar la prueba y verificar que falle**
  ```bash
  php tests/mail-console-and-consultas-admin-test.php
  ```

- [ ] **Paso 3: Implementar la orquestación en `ajax_retry_email()`**
  En `modules/consultas/includes/class-flacso-consultas-admin.php`:
  Para `offer_inquiries`:
  - Obtener motor: `get_option('flacso_inquiry_email_engine', 'mautic')`.
  - Si es `'mautic'`:
    - Sincronizar contacto si `mauticContactId` no está presente.
    - Obtener `template_id` (abierta o cerrada según estado).
    - Compilar tokens canónicos.
    - Despachar con `FLACSO_Mautic_Client::send_email_to_contact()`.
    - Si falla o no hay plantilla, conmutar a `FLACSO_Mailjet_Client::send_offer_inquiry()` con `sender = 'mailjet_fallback'`.
  - Si es `'mailjet'`: despacho directo con `FLACSO_Mailjet_Client::send_offer_inquiry()`.
  Para `seminario_inquiries`: mantener `FLACSO_Mailjet_Client::send_seminar_inquiry()`.

- [ ] **Paso 4: Ejecutar la prueba y verificar que pase**
  ```bash
  php tests/mail-console-and-consultas-admin-test.php
  ```

- [ ] **Paso 5: Ejecutar la suite completa y confirmar 0 regresiones**
  ```bash
  for f in tests/*-test.php; do php "$f" > /dev/null || echo "FAILED: $f"; done
  ```

- [ ] **Paso 6: Commit**
  ```bash
  git commit -m "feat(consultas): orchestrate mautic and fallback in admin retry email action"
  ```

---

### Task 4: Verificación Integral de Fase 5 y Cierre de Proyecto

**Files:**
- Verify: Todas las pruebas unitarias y de integración de `flacso-uruguay-plugin` y `kadence-child-flacso`.

- [ ] **Paso 1: Ejecutar verificación completa en plugin**
  ```bash
  for f in tests/*-test.php; do php "$f" || exit 1; done
  ```

- [ ] **Paso 2: Ejecutar verificación completa en tema hijo**
  ```bash
  cd "/home/ivan/repositorios/FLACSO Uruguay/Migrar/kadence-child-flacso"
  for f in tests/*-test.php; do php "$f" || exit 1; done
  ```

- [ ] **Paso 3: Ejecutar revisión de código de rama completa (Whole-Branch Reviewer con modelo `pro`)**

- [ ] **Paso 4: Publicar commits a `origin/main`**
  ```bash
  git push origin main
  ```
