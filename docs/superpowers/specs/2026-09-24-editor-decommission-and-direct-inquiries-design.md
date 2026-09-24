# Desmantelamiento del Editor y Persistencia Directa de Consultas en WordPress

## 1. Objetivo

Reemplazar únicamente los webhooks de consultas de ofertas académicas y seminarios por persistencia directa en PostgreSQL y envío transaccional Mailjet dentro del plugin, manteniendo el Editor activo hasta validar ambos flujos en producción.

WordPress interactúa directamente con la base de datos PostgreSQL existente para registrar consultas y utiliza la API v3.1 de Mailjet para el despacho de correos transaccionales bajo la premisa irrenunciable: **guardar primero en PostgreSQL, enviar después a Mailjet**.

---

## 2. Alcance y Límites

### Dentro de alcance (Fase 1 - Urgencia)
- **Persistencia directa en PostgreSQL:** Conservación sin cambios de las tablas existentes `offer_inquiries` y `seminar_inquiries`.
- **Servicios PHP internos:** Creación de `FLACSO_Offer_Inquiry_Service` y `FLACSO_Seminar_Inquiry_Service`.
- **Despacho transaccional Mailjet:** Despacho de correos para `consulta_abierta`, `consulta_cerrada` y consultas de seminario mediante `FLACSO_Mailjet_Client`.
- **Estrategia conservadora de plantillas:**
  - Si el `TemplateID` está configurado localmente $\rightarrow$ se intenta enviar con plantilla Mailjet. Si falla o da timeout $\rightarrow$ se marca `failed` (no se reintenta automáticamente con HTML para evitar dobles envíos si hubo timeout).
  - Si el `TemplateID` no está configurado $\rightarrow$ se envía directamente la plantilla institucional HTML generada en PHP.
- **Identificadores Mailjet:** Persistencia tanto de `mailjetMessageId` como de `mailjetMessageUuid`.
- **Idempotencia:** Verificación y unicidad basada en `consultaId` (`find_by_consulta_id` previo + captura de SQLSTATE `23505`).
- **Constantes de producción:** Adaptación a las constantes ya configuradas en `wp-config.php`:
  `FLACSO_PG_HOST`, `FLACSO_PG_PORT`, `FLACSO_PG_DATABASE`, `FLACSO_PG_USER`, `FLACSO_PG_PASSWORD`.
- **Testabilidad desacoplada y diagnóstico real:** Suite de tests unitarios rápidos con SQLite en memoria y script de verificación transaccional real contra PostgreSQL (`SELECT`, `INSERT`, `UPDATE`, `ROLLBACK`).
- **Despliegue con observación:** Activación paulatina de ofertas, luego seminarios, con verificación en vivo antes de apagar `flacso-editor.service`.

### Fuera de alcance (Diferido a Fase 2)
- Formulario de contacto general (`fc_handle_form_submit`, tabla `general_inquiries` / `Consulta`), para minimizar la superficie de cambio y el riesgo en esta primera migración.
- Funcionalidades del CRM y dashboard del Editor: gestión de contactos (`Contact`), programas académicos (`AcademicProgram`), asignaciones de usuarios, fases 2 y 3 de email marketing, sincronización de contactos a listas de Mailjet, autenticación Google/NextAuth, webhooks entrantes de Mailjet y notificaciones de Telegram.
- Las columnas foráneas de CRM en PostgreSQL (`contactId`, `academicProgramId`, `editionId`) se insertan en `NULL`.

---

## 3. Arquitectura del Sistema

### Flujo de Datos

```text
                                flacso.edu.uy
                                      │
                            Plugin FLACSO Uruguay
                                      │
                      ┌───────────────┴───────────────┐
                      ▼                               ▼
              Consulta Oferta                 Consulta Seminario
                      │                               │
                      ▼                               ▼
               offer_inquiries                seminar_inquiries
                      │                               │
                      └───────────────┬───────────────┘
                                      ▼
                                   Mailjet
                               (API v3.1 Send)
                                      │
                                      ▼
                          Actualización emailStatus,
                       mailjetMessageId y MessageUuid
```

### Estructura de Directorios en `flacso-uruguay-plugin`

```text
flacso-uruguay-plugin/
├── includes/
│   ├── database/
│   │   ├── class-flacso-db.php
│   │   └── repositories/
│   │       ├── class-flacso-offer-inquiry-repository.php
│   │       └── class-flacso-seminar-inquiry-repository.php
│   └── integrations/
│       └── class-flacso-mailjet-client.php
├── modules/
│   └── consultas/
│       ├── services/
│       │   ├── class-flacso-offer-inquiry-service.php
│       │   └── class-flacso-seminar-inquiry-service.php
│       └── init.php
├── scripts/
│   └── verify-postgres-connection.php
└── tests/
    ├── inquiry-db-connection-test.php
    ├── inquiry-repositories-test.php
    ├── inquiry-mailjet-client-test.php
    ├── inquiry-services-test.php
    ├── inquiry-plugin-loader-test.php
    └── inquiry-handler-wiring-test.php
```

---

## 4. Capa de Base de Datos y Repositorios

### 4.1. Conexión PDO (`FLACSO_DB`)
- Ubicación: `includes/database/class-flacso-db.php`.
- Utiliza las constantes ya presentes en producción (`wp-config.php`):
  ```php
  $dsn = sprintf(
      'pgsql:host=%s;port=%s;dbname=%s',
      FLACSO_PG_HOST,
      FLACSO_PG_PORT,
      FLACSO_PG_DATABASE
  );
  ```
- Opciones PDO:
  - `PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION`
  - `PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC`
  - `PDO::ATTR_TIMEOUT => 5`
- Método `FLACSO_DB::set_connection(?PDO $pdo)` para inyectar SQLite en memoria durante tests.
- `FLACSO_DB::is_configured()` verifica la presencia de las 5 constantes o la inyección de conexión.

### 4.2. Repositorios
1. **`FLACSO_Offer_Inquiry_Repository`** (tabla `offer_inquiries`):
   - `find_by_consulta_id(string $consulta_id): ?array`
   - `insert(array $data): array` (retorna `['id' => string, 'consultaId' => string, 'duplicate' => bool]`)
   - `update_email_status(string $consulta_id, string $status, ?string $sender = null, ?string $message_id = null, ?string $message_uuid = null): bool`
   - Generación de `id` con formato CUID (`c` + 24 caracteres alfanuméricos) para compatibilidad Prisma.
   - Claves foráneas de CRM (`contactId`, `academicProgramId`) en `NULL`.

2. **`FLACSO_Seminar_Inquiry_Repository`** (tabla `seminar_inquiries`):
   - Mismo contrato, adaptado a columnas de seminarios (`seminarWpId`, `seminarName`, `seminarType`, `contactId = NULL`, `editionId = NULL`).

### 4.3. Idempotencia y Concurrencia
1. `find_by_consulta_id($consulta_id)`: si ya existe $\rightarrow$ retorna inmediatamente `duplicate = true` sin validar campos no requeridos ni ejecutar `INSERT`.
2. Si no existe $\rightarrow$ ejecuta `INSERT`.
3. Si ocurre una colisión concurrente (SQLSTATE `23505`) $\rightarrow$ recupera el registro existente y retorna `duplicate = true`.

---

## 5. Integración con Mailjet (`FLACSO_Mailjet_Client`)

- Ubicación: `includes/integrations/class-flacso-mailjet-client.php`.
- Reutiliza la configuración centralizada de `FLACSO_Integrations_Settings::get_mailjet_settings()`:
  `flacso_mailjet_api_key`, `flacso_mailjet_secret_key`, `flacso_mailjet_sender_email`, `flacso_mailjet_sender_name`.
- Opciones de plantillas:
  - `flacso_mailjet_template_consulta_abierta`
  - `flacso_mailjet_template_consulta_cerrada`
  - `flacso_mailjet_template_consulta_seminario`
- **Regla conservadora de fallback:**
  - Si hay `TemplateID` configurado $\rightarrow$ despacha vía `TemplateID`. Si Mailjet falla o excede timeout $\rightarrow$ retorna `status = 'failed'` con el error (no se envía fallback HTML automático para evitar correos duplicados por reintento).
  - Si no hay `TemplateID` configurado $\rightarrow$ despacha con cuerpo HTML institucional generado internamente en PHP.
- Extrae y retorna `mailjetMessageId` y `mailjetMessageUuid`.

---

## 6. Servicios y Ciclo de Vida: "Guardar Primero, Enviar Después"

1. **Validación:** Validar campos mínimos indispensables (`nombre`, `correo`, etc.).
2. **Idempotencia:** Verificar `find_by_consulta_id($consulta_id)`. Si existe $\rightarrow$ salir con `duplicate = true`.
3. **INSERT obligatorio:** Guardar en `offer_inquiries` o `seminar_inquiries` con `emailStatus = 'skipped'`.
   - Si el `INSERT` falla $\rightarrow$ se detiene el flujo y se informa error al usuario. Mailjet **nunca** se llama.
4. **Despacho Mailjet:**
   - Si tiene éxito $\rightarrow$ `status = 'sent'`.
   - Si falla $\rightarrow$ `status = 'failed'`. La consulta permanece guardada.
5. **UPDATE:** Actualizar `emailStatus`, `mailjetMessageId` y `mailjetMessageUuid`.
6. **Retorno al cliente:** Responder éxito con `consulta_id` y confirmación de recepción.

---

## 7. Carga del Módulo y Handlers de WordPress

- `flacso-uruguay.php` registra e invoca `flacso_uruguay_load_module('consultas')`.
- `modules/consultas/init.php` carga en orden:
  1. `includes/database/class-flacso-db.php`
  2. `includes/database/repositories/class-flacso-offer-inquiry-repository.php`
  3. `includes/database/repositories/class-flacso-seminar-inquiry-repository.php`
  4. `includes/integrations/class-flacso-mailjet-client.php`
  5. `modules/consultas/services/class-flacso-offer-inquiry-service.php`
  6. `modules/consultas/services/class-flacso-seminar-inquiry-service.php`
- Handlers conectados:
  - `flacso_enviar_consulta_func()` en `modules/main-page/includes/flacso-consultas.php` $\rightarrow$ `FLACSO_Offer_Inquiry_Service::submit($offer_payload)`.
  - `submit_consulta_seminario()` en `modules/oferta-academica/includes/class-academic-api.php` $\rightarrow$ `FLACSO_Seminar_Inquiry_Service::submit($payload)`.

---

## 8. Estrategia de Testing y Diagnóstico

1. **Tests unitarios con SQLite:** Prueban la lógica en memoria sin requerir PostgreSQL ni `pdo_pgsql` en local.
2. **Script de diagnóstico PostgreSQL real (`scripts/verify-postgres-connection.php`):**
   - Ejecuta:
     1. `SELECT COUNT(*)` sobre `offer_inquiries` y `seminar_inquiries`.
     2. Inicia transacción (`BEGIN`).
     3. Inserta fila de prueba en `offer_inquiries` con `consultaId = 'test-diagnostico-...'`.
     4. Actualiza `emailStatus` a `'tested'`.
     5. Lee la fila insertada.
     6. Ejecuta `ROLLBACK`.
   - Comprueba compatibilidad estricta con PostgreSQL antes del corte.

---

## 9. Plan de Despliegue y Observación

1. Implementar clases y validar suite de tests en memoria.
2. Correr `verify-postgres-connection.php` en el entorno con PostgreSQL.
3. Activar flujo directo de ofertas y realizar consulta de prueba controlada (verificar `offer_inquiries` y Mailjet).
4. Activar flujo directo de seminarios y realizar consulta de prueba controlada (verificar `seminar_inquiries` y Mailjet).
5. Mantener Editor encendido durante un período de observación inicial.
6. Una vez confirmado el tráfico normal, detener y deshabilitar `flacso-editor.service`.
