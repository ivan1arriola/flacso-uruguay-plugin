# Plan de Implementación: Envío de Correos Inmediatos vía Mautic y Fallback Transaccional (Fase 3)

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Implementar el envío de correos transaccionales de consultas de oferta académica a través de plantillas de Mautic, con selector de motor configurable (`flacso_inquiry_email_engine`) y conmutación automática de respaldo (fallback a Mailjet) ante fallos de Mautic.

**Architecture:** Método `send_email_to_contact()` en `FLACSO_Mautic_Client` conectado al endpoint `/api/emails/{id}/contact/{contactId}/send`, compilador de tokens `{token}` en `FLACSO_Inquiry_Marketing_Service`, orquestación determinística en `FLACSO_Offer_Inquiry_Service::submit()` con conmutación resiliente a Mailjet ante caídas de Mautic ("guardar primero, enviar después"), panel de configuración en `FLACSO_Mail_Settings`, y visualización del motor emisor en `FLACSO_Consultas_Admin`.

**Tech Stack:** PHP 7.4+, WordPress Core HTTP API, PostgreSQL / SQLite in-memory test driver, Mautic REST API v1.

**Spec:** `flacso-uruguay-plugin/docs/superpowers/specs/2026-09-28-consultas-mautic-envio-fase-3-design.md`

## Global Constraints

- "Guardar primero, enviar después": la persistencia en PostgreSQL (`offer_inquiries`) debe ocurrir antes de despachar el correo (por Mautic o Mailjet).
- Cero correos perdidos: si el motor activo es Mautic y el envío por Mautic falla, no tiene plantilla configurada o agota el timeout (4s), se activa conmutación automática inmediata a Mailjet (`emailSender = 'mailjet_fallback'`).
- El usuario web siempre recibe respuesta HTTP 200 independientemente del motor o incidencias de red.
- Todas las pruebas deben ser autónomas en PHP puro sin dependencias de servidor externo.

---

### Task 1: Despacho de Correo en `FLACSO_Mautic_Client` (`send_email_to_contact`)

**Files:**
- Modify: `flacso-uruguay-plugin/includes/integrations/class-flacso-mautic-client.php`
- Modify: `flacso-uruguay-plugin/tests/mautic-client-test.php`

**Interfaces:**
- Produces:
  - `FLACSO_Mautic_Client::send_email_to_contact(int $email_id, int $contact_id, array $tokens = []): array`

- [ ] **Step 1: Escribir la prueba unitaria fallida**
  En `tests/mautic-client-test.php`, añadir grupo de pruebas para `send_email_to_contact`:
  - Parámetros inválidos (`$email_id <= 0` o `$contact_id <= 0`) -> retorna `ok => false`.
  - Mautic deshabilitado -> retorna `ok => false`.
  - Petición POST a `/api/emails/{id}/contact/{contactId}/send` con tokens.
  - Respuesta exitosa HTTP 200 con `success: true`.
  - Respuesta fallida HTTP 400 / 500 y manejo de `failedRecipients`.
  - Resiliencia ante timeouts y `WP_Error`.

- [ ] **Step 2: Ejecutar el test para comprobar fallo**
  ```bash
  php tests/mautic-client-test.php
  ```

- [ ] **Step 3: Implementar `send_email_to_contact` en `FLACSO_Mautic_Client`**
  Implementar el método construyendo la URL, enviando payload con `tokens`, aplicando timeout de 4s y parseando la respuesta.

- [ ] **Step 4: Ejecutar el test para comprobar éxito**
  ```bash
  php tests/mautic-client-test.php
  ```

- [ ] **Step 5: Commit de Task 1**
  ```bash
  git add includes/integrations/class-flacso-mautic-client.php tests/mautic-client-test.php
  git commit -m "feat(mautic): implement send_email_to_contact in FLACSO_Mautic_Client"
  ```

---

### Task 2: Compilador de Tokens Canónicos en `FLACSO_Inquiry_Marketing_Service`

**Files:**
- Modify: `flacso-uruguay-plugin/modules/consultas/services/class-flacso-inquiry-marketing-service.php`
- Modify: `flacso-uruguay-plugin/tests/inquiry-marketing-tags-test.php`

**Interfaces:**
- Produces:
  - `FLACSO_Inquiry_Marketing_Service::compile_tokens(array $inquiry, array $program, bool $is_open): array`

- [ ] **Step 1: Escribir la prueba unitaria fallida**
  En `tests/inquiry-marketing-tags-test.php`, añadir pruebas para `compile_tokens()` verificando:
  - Tokens personales: `{nombre}`, `{apellido}`, `{nombre_completo}`, `{correo}`, `{pais}`, `{profesion}`, `{nivel_academico}`.
  - Tokens académicos: `{programa}`, `{oferta_academica_nombre}`, `{oferta_academica_url}`, `{cohorte_nombre}`, `{cohorte_numero}`, `{fecha_inicio}`, `{modalidad}`, `{url_preinscripcion}`, `{url_carta}`.
  - Valores por defecto cuando faltan datos opcionales.

- [ ] **Step 2: Ejecutar el test para comprobar fallo**
  ```bash
  php tests/inquiry-marketing-tags-test.php
  ```

- [ ] **Step 3: Implementar `compile_tokens` en `FLACSO_Inquiry_Marketing_Service`**
  Implementar el método mapeando limpiamente las propiedades de la consulta y del programa.

- [ ] **Step 4: Ejecutar el test para comprobar éxito**
  ```bash
  php tests/inquiry-marketing-tags-test.php
  ```

- [ ] **Step 5: Commit de Task 2**
  ```bash
  git add modules/consultas/services/class-flacso-inquiry-marketing-service.php tests/inquiry-marketing-tags-test.php
  git commit -m "feat(consultas): implement compile_tokens in FLACSO_Inquiry_Marketing_Service"
  ```

---

### Task 3: Configuración de Motor y Plantillas en `FLACSO_Mail_Settings`

**Files:**
- Modify: `flacso-uruguay-plugin/modules/mailing/includes/class-flacso-mail-settings.php`
- Modify: `flacso-uruguay-plugin/tests/mail-console-and-consultas-admin-test.php`

**Interfaces:**
- Produces:
  - Opciones `flacso_inquiry_email_engine`, `flacso_mautic_template_consulta_abierta`, `flacso_mautic_template_consulta_cerrada`.
  - Endpoint AJAX `wp_ajax_flacso_mautic_send_test_email`.

- [ ] **Step 1: Escribir la prueba unitaria fallida**
  En `tests/mail-console-and-consultas-admin-test.php`, añadir aserciones para las nuevas opciones registradas, el renderizado del selector de motor y plantillas, y el callback de prueba de envío.

- [ ] **Step 2: Ejecutar el test para comprobar fallo**
  ```bash
  php tests/mail-console-and-consultas-admin-test.php
  ```

- [ ] **Step 3: Implementar opciones, UI y AJAX test en `FLACSO_Mail_Settings`**
  - Registrar las nuevas opciones en `register_settings()`.
  - Añadir campos visuales en `render_page()` para selector de motor y templates Mautic.
  - Implementar método AJAX `ajax_send_test_mautic_email()`.

- [ ] **Step 4: Ejecutar el test para comprobar éxito**
  ```bash
  php tests/mail-console-and-consultas-admin-test.php
  ```

- [ ] **Step 5: Commit de Task 3**
  ```bash
  git add modules/mailing/includes/class-flacso-mail-settings.php tests/mail-console-and-consultas-admin-test.php
  git commit -m "feat(mailing): add email engine selector and mautic template settings"
  ```

---

### Task 4: Orquestación del Envío y Fallback Automático en `FLACSO_Offer_Inquiry_Service::submit()`

**Files:**
- Modify: `flacso-uruguay-plugin/modules/consultas/services/class-flacso-offer-inquiry-service.php`
- Modify: `flacso-uruguay-plugin/tests/inquiry-services-test.php`

**Interfaces:**
- Consumes:
  - `FLACSO_Mautic_Client::send_email_to_contact()`
  - `FLACSO_Inquiry_Marketing_Service::compile_tokens()`
  - `FLACSO_Inquiry_Marketing_Service::sync_inquiry()`
  - `FLACSO_Mailjet_Client::send_offer_inquiry()`

- [ ] **Step 1: Escribir pruebas unitarias de integración**
  En `tests/inquiry-services-test.php`:
  - Test 1: Motor Mautic con envío exitoso -> Mautic envía, Mailjet no se llama, `emailSender = 'mautic'`.
  - Test 2: Motor Mautic con fallo de Mautic -> fallback a Mailjet ejecutado, `emailSender = 'mailjet_fallback'`, `emailStatus = 'sent'`.
  - Test 3: Motor Mautic sin plantilla configurada -> fallback a Mailjet ejecutado, `emailSender = 'mailjet_fallback'`.
  - Test 4: Motor Mailjet seleccionado -> Mailjet envía directamente, `emailSender = 'mailjet'`.

- [ ] **Step 2: Ejecutar el test para comprobar fallo**
  ```bash
  php tests/inquiry-services-test.php
  ```

- [ ] **Step 3: Implementar la orquestación en `submit()`**
  Reestructurar el pipeline de envío de `FLACSO_Offer_Inquiry_Service::submit()` evaluando `flacso_inquiry_email_engine`:
  - Si `'mautic'`: sincronizar contacto, compilar tokens, enviar email por Mautic; ante cualquier fallo o excepción, ejecutar fallback a Mailjet.
  - Si `'mailjet'`: enviar por Mailjet y sincronizar en paralelo con Mautic.

- [ ] **Step 4: Ejecutar el test para comprobar éxito**
  ```bash
  php tests/inquiry-services-test.php
  ```

- [ ] **Step 5: Commit de Task 4**
  ```bash
  git add modules/consultas/services/class-flacso-offer-inquiry-service.php tests/inquiry-services-test.php
  git commit -m "feat(consultas): integrate mautic email dispatch with mailjet automatic fallback"
  ```

---

### Task 5: Visualización de Motor y Fallback en `FLACSO_Consultas_Admin`

**Files:**
- Modify: `flacso-uruguay-plugin/modules/consultas/includes/class-flacso-consultas-admin.php`
- Modify: `flacso-uruguay-plugin/tests/mail-console-and-consultas-admin-test.php`

- [ ] **Step 1: Escribir pruebas unitarias**
  En `tests/mail-console-and-consultas-admin-test.php`:
  - Verificar que las consultas con `emailSender === 'mautic'` muestren badge distintivo.
  - Verificar que las consultas con `emailSender === 'mailjet_fallback'` muestren aviso de fallback.

- [ ] **Step 2: Ejecutar el test para comprobar fallo**
  ```bash
  php tests/mail-console-and-consultas-admin-test.php
  ```

- [ ] **Step 3: Implementar badges en `FLACSO_Consultas_Admin`**
  Actualizar la celda de estado de correo y modal de detalle para distinguir el motor (`Mautic`, `Mailjet`, `Fallback`).

- [ ] **Step 4: Ejecutar el test para comprobar éxito**
  ```bash
  php tests/mail-console-and-consultas-admin-test.php
  ```

- [ ] **Step 5: Commit de Task 5**
  ```bash
  git add modules/consultas/includes/class-flacso-consultas-admin.php tests/mail-console-and-consultas-admin-test.php
  git commit -m "feat(consultas): display email sender engine and fallback badge in admin table"
  ```

---

### Task 6: Verificación Integral y Suite de Pruebas Completa

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

- [ ] **Step 3: Verificar estado de git**
  ```bash
  git status
  ```
