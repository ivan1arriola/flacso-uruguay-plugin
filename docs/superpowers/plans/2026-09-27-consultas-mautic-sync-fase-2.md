# Plan de Implementación: Cliente Mautic y Sincronización en Paralelo de Consultas (Fase 2)

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Implementar el cliente HTTP de Mautic y el servicio de marketing para sincronizar prospectos y asignar etiquetas canónicas en paralelo al flujo existente de consultas académicas en PostgreSQL y Mailjet, sin enviar correos desde Mautic aún.

**Architecture:** Cliente nativo `FLACSO_Mautic_Client` con soporte para Basic Auth y Bearer Token, deduplicación por correo previa a la creación, timeout de 4s; servicio de marketing `FLACSO_Inquiry_Marketing_Service` con nomenclatura estricta de tags (`interes-{abbr}`, `interes-{abbr}-c{num}`, `consulta-abierta/cerrada-{abbr}-c{num}`); orquestación desacoplada en `FLACSO_Offer_Inquiry_Service::submit()` bajo el principio "guardar primero, enviar después" sin bloqueo; y paneles de administración actualizados en `FLACSO_Mail_Settings` y `FLACSO_Consultas_Admin`.

**Tech Stack:** PHP 7.4+, WordPress Core HTTP API (`wp_remote_get`, `wp_remote_post`, `wp_remote_request`), PostgreSQL / SQLite in-memory test driver, Mautic REST API v1.

**Spec:** `flacso-uruguay-plugin/docs/superpowers/specs/2026-09-27-consultas-mautic-sync-fase-2-design.md`

## Global Constraints

- Mailjet continúa enviando el 100% de los correos transaccionales inmediatos. Mautic no envía correos en esta fase.
- "Guardar primero, enviar después": la persistencia en PostgreSQL (`offer_inquiries`) debe ocurrir antes de interactuar con Mailjet o Mautic.
- Timeout de Mautic fijado en 4 segundos como máximo.
- Cero fallos visibles para el usuario final: cualquier excepción de red o error HTTP de Mautic debe ser capturado, registrado en el log y persistido como `mauticSyncStatus = 'failed'` en PostgreSQL, retornando HTTP 200 al prospecto.
- Sin campos volátiles en Mautic: no almacenar `ultima_oferta` ni `estado_actual` en el contacto de Mautic.
- Toda prueba automatizada debe ser autónoma, ejecutable con `php tests/<test-file>.php` sin requerir servidor WordPress en vivo.

---

### Task 1: Cliente API Mautic (`FLACSO_Mautic_Client`)

**Files:**
- Create: `flacso-uruguay-plugin/includes/integrations/class-flacso-mautic-client.php`
- Test: `flacso-uruguay-plugin/tests/mautic-client-test.php`

**Interfaces:**
- Produces:
  - `FLACSO_Mautic_Client::get_settings(): array`
  - `FLACSO_Mautic_Client::is_configured(): bool`
  - `FLACSO_Mautic_Client::test_connection(): array`
  - `FLACSO_Mautic_Client::find_contact_by_email(string $email): ?array`
  - `FLACSO_Mautic_Client::create_or_update_contact(string $email, array $fields = [], array $tags = []): array`

- [ ] **Step 1: Crear la prueba unitaria fallida**
  Crear `tests/mautic-client-test.php` con mocks de `wp_remote_get`, `wp_remote_post`, `wp_remote_request` para verificar:
  - Manejo de configuración y estado (`is_configured()`).
  - Cabecera Basic Auth (`Authorization: Basic base64(...)`) y Bearer (`Authorization: Bearer token`).
  - Búsqueda de contacto existente con resultado positivo y negativo.
  - Creación de nuevo contacto (`POST /api/contacts/new`).
  - Edición de contacto existente (`PATCH /api/contacts/{id}/edit`).
  - Tolerancia a errores de red y HTTP (401, 500, timeout).

- [ ] **Step 2: Ejecutar el test para comprobar que falla**
  ```bash
  php tests/mautic-client-test.php
  ```

- [ ] **Step 3: Implementar `FLACSO_Mautic_Client`**
  Escribir `includes/integrations/class-flacso-mautic-client.php` con los métodos requeridos, constantes de opciones, timeout de 4s y sanitización de datos.

- [ ] **Step 4: Ejecutar el test para comprobar que pasa**
  ```bash
  php tests/mautic-client-test.php
  ```

- [ ] **Step 5: Commit de Task 1**
  ```bash
  git add includes/integrations/class-flacso-mautic-client.php tests/mautic-client-test.php
  git commit -m "feat(mautic): implement FLACSO_Mautic_Client with auth and contact deduplication"
  ```

---

### Task 2: Repositorio de Consultas — Método `update_mautic_status`

**Files:**
- Modify: `flacso-uruguay-plugin/includes/database/repositories/class-flacso-offer-inquiry-repository.php`
- Modify: `flacso-uruguay-plugin/tests/inquiry-repositories-test.php`

**Interfaces:**
- Produces:
  - `FLACSO_Offer_Inquiry_Repository::update_mautic_status(string $id, array $mautic_data): bool`
    - Recibe `$mautic_data` con claves opcionales: `mauticContactId`, `mauticSyncStatus`, `mauticSyncedAt`, `mauticLastError`.

- [ ] **Step 1: Agregar prueba unitaria en `tests/inquiry-repositories-test.php`**
  Añadir test que inserte una consulta y luego actualice su estado a `synced` con un ID de contacto, y verifique que `find_by_id` o `select` refleje los valores actualizados.

- [ ] **Step 2: Ejecutar el test para verificar fallo**
  ```bash
  php tests/inquiry-repositories-test.php
  ```

- [ ] **Step 3: Implementar `update_mautic_status()` en `FLACSO_Offer_Inquiry_Repository`**
  Implementar el método construyendo la consulta `UPDATE offer_inquiries SET ... WHERE id = :id` validando que las columnas pertenezcan a la lista blanca permitida.

- [ ] **Step 4: Ejecutar el test para verificar éxito**
  ```bash
  php tests/inquiry-repositories-test.php
  ```

- [ ] **Step 5: Commit de Task 2**
  ```bash
  git add includes/database/repositories/class-flacso-offer-inquiry-repository.php tests/inquiry-repositories-test.php
  git commit -m "feat(consultas): add update_mautic_status to offer inquiry repository"
  ```

---

### Task 3: Servicio de Marketing de Consultas (`FLACSO_Inquiry_Marketing_Service`)

**Files:**
- Create: `flacso-uruguay-plugin/modules/consultas/services/class-flacso-inquiry-marketing-service.php`
- Modify: `flacso-uruguay-plugin/modules/consultas/init.php`
- Create: `flacso-uruguay-plugin/tests/inquiry-marketing-tags-test.php`

**Interfaces:**
- Consumes:
  - `FLACSO_Mautic_Client::create_or_update_contact()`
  - `FLACSO_Offer_Inquiry_Repository::update_mautic_status()`
- Produces:
  - `FLACSO_Inquiry_Marketing_Service::generate_tags(?string $abbreviation, ?int $cohort_number, string $offer_status): array`
  - `FLACSO_Inquiry_Marketing_Service::sync_inquiry(string|int $inquiry_id, array $inquiry_data = []): array`

- [ ] **Step 1: Crear la prueba unitaria fallida**
  Crear `tests/inquiry-marketing-tags-test.php` cubriendo:
  - Generación de tags:
    - Oferta abierta con cohorte (`['interes-davia', 'interes-davia-c10', 'consulta-abierta-davia-c10']`).
    - Oferta cerrada con cohorte (`['interes-davia', 'interes-davia-c10', 'consulta-cerrada-davia-c10']`).
    - Oferta sin cohorte (`['interes-davia']`).
    - Oferta sin abreviación (`[]`, sin excepción).
  - Sincronización exitosa: llamada a Mautic, guardado en repositorio con estado `synced` y contactId.
  - Sincronización omitida: cuando Mautic no está configurado (`skipped`).
  - Sincronización fallida: cuando Mautic retorna error o timeout (`failed`).

- [ ] **Step 2: Ejecutar el test para comprobar fallo**
  ```bash
  php tests/inquiry-marketing-tags-test.php
  ```

- [ ] **Step 3: Implementar `FLACSO_Inquiry_Marketing_Service` y registrar en `init.php`**
  - Implementar la clase con `generate_tags` y `sync_inquiry`.
  - Añadir `'modules/consultas/services/class-flacso-inquiry-marketing-service.php'` y `'includes/integrations/class-flacso-mautic-client.php'` a `$flacso_consultas_files` en `modules/consultas/init.php`.

- [ ] **Step 4: Ejecutar el test para comprobar que pasa**
  ```bash
  php tests/inquiry-marketing-tags-test.php
  ```

- [ ] **Step 5: Commit de Task 3**
  ```bash
  git add modules/consultas/services/class-flacso-inquiry-marketing-service.php modules/consultas/init.php tests/inquiry-marketing-tags-test.php
  git commit -m "feat(consultas): implement FLACSO_Inquiry_Marketing_Service and tag generation"
  ```

---

### Task 4: Integración en `FLACSO_Offer_Inquiry_Service::submit()`

**Files:**
- Modify: `flacso-uruguay-plugin/modules/consultas/services/class-flacso-offer-inquiry-service.php`
- Modify: `flacso-uruguay-plugin/tests/inquiry-services-test.php`

**Interfaces:**
- Consumes:
  - `FLACSO_Inquiry_Marketing_Service::sync_inquiry(string|int $inquiry_id, array $inquiry_data): array`

- [ ] **Step 1: Actualizar pruebas en `tests/inquiry-services-test.php`**
  Añadir aserciones verificando que tras la inserción y el envío de Mailjet, `FLACSO_Inquiry_Marketing_Service::sync_inquiry` se ejecuta en línea y que los datos de Mautic se persisten o devuelven sin romper el flujo principal.

- [ ] **Step 2: Ejecutar el test para verificar fallo**
  ```bash
  php tests/inquiry-services-test.php
  ```

- [ ] **Step 3: Integrar en `submit()`**
  En `FLACSO_Offer_Inquiry_Service::submit()`:
  - Tras `FLACSO_Mailjet_Client::send_offer_inquiry()` (y actualización de `emailStatus`), envolver la llamada a `FLACSO_Inquiry_Marketing_Service::sync_inquiry($inquiry_id, $record)` en un bloque `try / catch (\Throwable $e)`.
  - Retornar en el array de respuesta `'mautic_sync' => ...`.

- [ ] **Step 4: Ejecutar el test para verificar éxito**
  ```bash
  php tests/inquiry-services-test.php
  ```

- [ ] **Step 5: Commit de Task 4**
  ```bash
  git add modules/consultas/services/class-flacso-offer-inquiry-service.php tests/inquiry-services-test.php
  git commit -m "feat(consultas): integrate mautic sync into offer inquiry submit pipeline"
  ```

---

### Task 5: Configuración de Mautic en `FLACSO_Mail_Settings`

**Files:**
- Modify: `flacso-uruguay-plugin/modules/mailing/includes/class-flacso-mail-settings.php`
- Modify: `flacso-uruguay-plugin/tests/mail-console-and-consultas-admin-test.php`

**Interfaces:**
- Consumes:
  - `FLACSO_Mautic_Client::test_connection()`
- Produces:
  - `admin.php?page=flacso-correos`: pestaña/sección Mautic y endpoint AJAX `wp_ajax_flacso_mautic_test_connection`.

- [ ] **Step 1: Actualizar test de panel en `tests/mail-console-and-consultas-admin-test.php`**
  Verificar que `register_settings` registre las opciones de Mautic y que el render de la página contenga la sección de Mautic.

- [ ] **Step 2: Ejecutar el test para verificar fallo**
  ```bash
  php tests/mail-console-and-consultas-admin-test.php
  ```

- [ ] **Step 3: Implementar campos y endpoint AJAX en `FLACSO_Mail_Settings`**
  - Registrar opciones `flacso_mautic_*`.
  - Añadir sección visual en el formulario de la consola de correos con botón AJAX de prueba.
  - Implementar callback AJAX `ajax_test_mautic_connection()`.

- [ ] **Step 4: Ejecutar el test para verificar éxito**
  ```bash
  php tests/mail-console-and-consultas-admin-test.php
  ```

- [ ] **Step 5: Commit de Task 5**
  ```bash
  git add modules/mailing/includes/class-flacso-mail-settings.php tests/mail-console-and-consultas-admin-test.php
  git commit -m "feat(mailing): add mautic settings section and ajax test connection"
  ```

---

### Task 6: Visualización y Reintento Manual en `FLACSO_Consultas_Admin`

**Files:**
- Modify: `flacso-uruguay-plugin/modules/consultas/includes/class-flacso-consultas-admin.php`
- Modify: `flacso-uruguay-plugin/tests/mail-console-and-consultas-admin-test.php`

**Interfaces:**
- Consumes:
  - `FLACSO_Inquiry_Marketing_Service::sync_inquiry()`
- Produces:
  - Columna "Mautic" en la tabla de `offer_inquiries`.
  - Endpoint AJAX `wp_ajax_flacso_consultas_retry_mautic`.

- [ ] **Step 1: Actualizar test en `tests/mail-console-and-consultas-admin-test.php`**
  Añadir aserciones para la cabecera y celdas de la columna Mautic, y para el botón de reintento en caso de fallo.

- [ ] **Step 2: Ejecutar el test para verificar fallo**
  ```bash
  php tests/mail-console-and-consultas-admin-test.php
  ```

- [ ] **Step 3: Implementar columna Mautic y AJAX retry en `FLACSO_Consultas_Admin`**
  - Renderizar columna `Mautic` con badges (`synced`, `failed`, `pending`, `skipped`).
  - Renderizar botón `[ 🔄 Mautic ]` en acciones cuando el estado sea `failed` o `pending`.
  - Implementar método `ajax_retry_mautic()`.

- [ ] **Step 4: Ejecutar el test para verificar éxito**
  ```bash
  php tests/mail-console-and-consultas-admin-test.php
  ```

- [ ] **Step 5: Commit de Task 6**
  ```bash
  git add modules/consultas/includes/class-flacso-consultas-admin.php tests/mail-console-and-consultas-admin-test.php
  git commit -m "feat(consultas): add mautic column and manual retry action in admin console"
  ```

---

### Task 7: Verificación Integral y Suite de Pruebas Completa

**Files:**
- Test: All 46+ test suites in `flacso-uruguay-plugin/tests/`

- [ ] **Step 1: Ejecutar todas las pruebas del plugin**
  ```bash
  for t in tests/*-test.php; do php "$t" || exit 1; done
  ```

- [ ] **Step 2: Ejecutar pruebas del tema child**
  ```bash
  cd ../kadence-child-flacso && for t in tests/*-test.php; do php "$t" || exit 1; done && bash bin/check-theme.sh
  ```

- [ ] **Step 3: Verificar git status limpio**
  ```bash
  git status
  ```
