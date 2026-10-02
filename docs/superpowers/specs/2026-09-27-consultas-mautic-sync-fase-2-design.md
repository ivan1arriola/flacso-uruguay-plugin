# Especificación de Diseño: Cliente Mautic y Sincronización en Paralelo de Consultas (Fase 2)

- **Fecha:** 2026-09-27
- **Estado:** Validado y Aprobado
- **Módulo:** `flacso-uruguay-plugin` (`includes/integrations`, `modules/consultas`, `modules/mailing`, `includes/database`)

---

## 1. Contexto y Objetivos

En la **Fase 1** se estableció la base de datos inmutable para consultas académicas con contexto de cohorte (`offer_inquiries` con `offerAbbreviation`, `cohortWpId`, `cohortNumber`, `cohortName`, `registrationOpenAt`, `registrationCloseAt`, `offerStatus`). Las columnas de integración para Mautic (`mauticContactId`, `mauticSyncStatus`, `mauticSyncedAt`, `mauticLastError`) ya quedaron creadas en el esquema de base de datos.

En esta **Fase 2**, el objetivo es conectar WordPress con la instancia de Mautic institucional (`https://envios.flacso.edu.uy`) para sincronizar prospectos y etiquetarlos con la nomenclatura canónica de marketing en paralelo al flujo habitual de consultas, **sin enviar aún ningún correo desde Mautic**.

### Objetivos Principales de la Fase 2
1. **Cliente API Mautic nativo (`FLACSO_Mautic_Client`)**:
   - Cliente HTTP independiente y robusto para interactuar con la API REST de Mautic (`/api/contacts`).
   - Soporte para autenticación **Basic Auth** (usuario y contraseña de API) y **Bearer Token** (Personal Access Token).
   - Deduplicación por correo antes de crear (`GET /api/contacts?search=email:...`), actualizando el contacto existente vía `PATCH /api/contacts/{id}/edit` o creando uno nuevo vía `POST /api/contacts/new`.
   - Timeout estricto de 4 segundos para evitar retrasar la respuesta al prospecto.
2. **Servicio de Marketing y Etiquetas (`FLACSO_Inquiry_Marketing_Service`)**:
   - Generación determinística de tags según el estado de la oferta y cohorte.
   - Sincronización de campos estables de perfil (`firstname`, `lastname`, `email`, `country`, `profession`, `education_level`, `phone`).
   - **Cero campos volátiles**: no guardar `ultima_oferta` ni `estado_actual` en el contacto de Mautic para prevenir condiciones de carrera cuando un prospecto consulta por múltiples programas.
   - Manejo tolerante ante ofertas sin abreviación: registra contacto sin tags de oferta y emite advertencia descriptiva en el log.
3. **Flujo "Guardar primero, enviar después" sin bloqueo**:
   - Inserción en PostgreSQL primero.
   - Envío de correo transaccional vía Mailjet (se mantiene 100% activo en esta fase).
   - Sincronización inline con Mautic encapsulada en `try/catch`: cualquier falla de Mautic (caída de red, credenciales inválidas, timeout) no interrumpe la consulta ni muestra error al usuario web; se registra el fallo en `offer_inquiries` (`mauticSyncStatus = 'failed'`).
4. **Configuración y Diagnóstico en Panel de Correos (`FLACSO_Mail_Settings`)**:
   - Sección dedicada en `admin.php?page=flacso-correos` con URL base, credenciales, interruptor de activación y botón AJAX de prueba de conexión en vivo.
5. **Visibilidad y Reintento Manual en Consola de Consultas (`FLACSO_Consultas_Admin`)**:
   - Columna visual de estado Mautic con badges (`synced`, `failed`, `pending`, `skipped`).
   - Botón interactivo para reintentar la sincronización de consultas fallidas vía AJAX.

---

## 2. Hoja de Ruta de Fases

```
[Fase 1: Contexto de Cohorte y Snapshots en DB]       <--- COMPLETADA (100% test pass)
       │
       ▼
[Fase 2: Cliente Mautic y Sincronización en Paralelo] <--- ALCANCE ACTUAL
       │ (Mailjet sigue enviando; Mautic sólo recibe contactos y tags)
       ▼
[Fase 3: Correo Inmediato por Mautic]
       │ (Mautic asume el envío transaccional inmediato con plantillas)
       ▼
[Fase 4: Seguimiento Automático (+X días vía WP-Cron)]
       │ (Plugin reevalúa cohorte abierta/cerrada y solicita envío de seguimiento a Mautic)
       ▼
[Fase 5: Retiro Definitivo de Mailjet en Consultas]
```

---

## 3. Arquitectura y Componentes Detallados

### 3.1. Cliente Mautic (`FLACSO_Mautic_Client`)
- **Ubicación:** `flacso-uruguay-plugin/includes/integrations/class-flacso-mautic-client.php`
- **Opciones de configuración:**
  - `flacso_mautic_enabled` (bool, default: `false`)
  - `flacso_mautic_base_url` (string, default: `https://envios.flacso.edu.uy`)
  - `flacso_mautic_auth_type` (string: `'basic'` o `'bearer'`, default: `'basic'`)
  - `flacso_mautic_username` (string)
  - `flacso_mautic_password` (string)
  - `flacso_mautic_token` (string)
- **Métodos públicos principales:**
  1. `public static function get_settings(): array`: Devuelve configuración normalizada.
  2. `public static function is_configured(): bool`: Verifica si los campos mínimos están presentes y el interruptor está activo.
  3. `public static function test_connection(): array`: Prueba conectividad ejecutando `GET /api/contacts?limit=1`. Retorna `['ok' => bool, 'message' => string, 'code' => int]`.
  4. `public static function find_contact_by_email(string $email): ?array`:
     - Consulta `GET /api/contacts?search=email:{email}`.
     - Retorna array del contacto o `null` si no existe o hay error.
  5. `public static function create_or_update_contact(string $email, array $fields = [], array $tags = []): array`:
     - Busca contacto por email.
     - Si existe: `PATCH /api/contacts/{id}/edit` agregando los nuevos tags y actualizando campos.
     - Si no existe: `POST /api/contacts/new` con fields y tags iniciales.
     - Retorna `['ok' => bool, 'contact_id' => ?int, 'error' => ?string, 'action' => 'created'|'updated'|'failed']`.

### 3.2. Servicio de Marketing de Consultas (`FLACSO_Inquiry_Marketing_Service`)
- **Ubicación:** `flacso-uruguay-plugin/modules/consultas/services/class-flacso-inquiry-marketing-service.php`
- **Nomenclatura Canónica de Etiquetas:**
  `public static function generate_tags(?string $abbreviation, ?int $cohort_number, string $offer_status): array`
  - Si `$abbreviation` está vacío: retorna `[]` (y emite `error_log` warning descriptivo).
  - Reglas de tags:
    1. Tag base de programa: `interes-{$abbreviation}` (siempre que exista abreviación).
    2. Tag de cohorte: si `$cohort_number > 0`, se añade `interes-{$abbreviation}-c{$cohort_number}`.
    3. Tag contextual de cohorte:
       - Si `$offer_status === 'abierta'` y `$cohort_number > 0`: `consulta-abierta-{$abbreviation}-c{$cohort_number}`.
       - Si `$offer_status === 'cerrada'` y `$cohort_number > 0`: `consulta-cerrada-{$abbreviation}-c{$cohort_number}`.
       - Si `$offer_status === 'sin_cohorte'`: **no** se genera tag de cohorte ni de estado de cohorte (únicamente `interes-{$abbreviation}`).
- **Mapeo de Campos de Contacto:**
  ```php
  $contact_fields = [
      'firstname'       => $inquiry['firstName'],
      'lastname'        => $inquiry['lastName'],
      'email'           => $inquiry['email'],
      'country'         => $inquiry['country'] ?? '',
      'profession'      => $inquiry['profession'] ?? '',
      'education_level' => $inquiry['educationLevel'] ?? '',
      'phone'           => $inquiry['phone'] ?? '',
  ];
  ```
- **Orquestación de Sincronización:**
  `public static function sync_inquiry(string|int $inquiry_id, array $inquiry_data = []): array`
  - Si `$inquiry_data` no se pasa, la recupera de `offer_inquiries` por ID.
  - Verifica si Mautic está activo (`FLACSO_Mautic_Client::is_configured()`). Si no está activo: actualiza DB con `mauticSyncStatus = 'skipped'` y retorna.
  - Genera tags canónicos.
  - Invoca `FLACSO_Mautic_Client::create_or_update_contact()`.
  - Actualiza el registro en `offer_inquiries` vía repositorio:
    - En éxito: `mauticSyncStatus = 'synced'`, `mauticContactId = $contact_id`, `mauticSyncedAt = current_timestamp`, `mauticLastError = null`.
    - En fallo: `mauticSyncStatus = 'failed'`, `mauticLastError = $error_message`.
  - Retorna array de resultado.

### 3.3. Actualización del Repositorio (`FLACSO_Offer_Inquiry_Repository`)
Se incorpora método específico de actualización de estado Mautic:
```php
public function update_mautic_status(string $id, array $mautic_data): bool
```
- Parámetros en `$mautic_data`: `mauticContactId`, `mauticSyncStatus`, `mauticSyncedAt`, `mauticLastError`.
- Compatible con PostgreSQL y SQLite (entorno de pruebas).

### 3.4. Integración en `FLACSO_Offer_Inquiry_Service::submit()`
El flujo de envío de consultas académicas se expande garantizando que la falla de Mautic nunca bloquee al usuario:
```
1. FLACSO_Inquiry_Context_Service::resolve(...)
2. FLACSO_Offer_Inquiry_Repository::insert($record)  <-- Si falla, aborta
3. FLACSO_Mailjet_Client::send_offer_inquiry(...)    <-- Si falla, marca emailStatus='failed'
4. FLACSO_Inquiry_Marketing_Service::sync_inquiry(...) <-- En try/catch: actualiza mauticSyncStatus
5. Retorna respuesta exitosa al prospecto
```

### 3.5. Configuración en Consola de Correos (`FLACSO_Mail_Settings`)
- En `modules/mailing/includes/class-flacso-mail-settings.php`:
  - Se registra la sección "Mautic Marketing Automation".
  - Campos: URL base, tipo de autenticación, usuario, contraseña/token, interruptor de activación.
  - Botón AJAX `[ Probar conexión ]` conectado a `wp_ajax_flacso_mautic_test_connection`.
  - Indicador visual de estado de conexión en tiempo real.

### 3.6. Consola de Consultas y Reintento (`FLACSO_Consultas_Admin`)
- En `modules/consultas/includes/class-flacso-consultas-admin.php`:
  - Se añade la columna "Mautic" en la tabla para `offer_inquiries`.
  - Badges de estado con tooltips informativos.
  - Botón de acción `[ 🔄 Mautic ]` para filas con estado `failed` o `pending`.
  - Endpoint AJAX `wp_ajax_flacso_consultas_retry_mautic` que ejecuta la sincronización y devuelve el HTML del badge actualizado para modificar el DOM sin recargar.

---

## 4. Estrategia de Pruebas Automatizadas

1. **`tests/mautic-client-test.php`**:
   - Pruebas unitarias completas de `FLACSO_Mautic_Client`.
   - Simulación con mock de peticiones HTTP:
     - Cabecera Basic Auth (Base64) y Bearer Token.
     - Búsqueda de contacto por email con match y sin match.
     - Creación de nuevo contacto (`POST /api/contacts/new`).
     - Actualización de contacto existente (`PATCH /api/contacts/{id}/edit`).
     - Respuestas de error HTTP (401, 500) y timeouts de red.
2. **`tests/inquiry-marketing-tags-test.php`**:
   - Pruebas de generación de tags para todos los casos (abierta con cohorte, cerrada con cohorte, sin cohorte, sin abreviación).
   - Pruebas de orquestación de `sync_inquiry` verificando la persistencia en DB de los estados `synced`, `failed` y `skipped`.
3. **Regresión Completa**:
   - Ejecución de los 46 suites de pruebas del plugin para garantizar 0 regresiones.
