# Desmantelamiento del Editor y Persistencia Directa de Consultas en WordPress

## 1. Objetivo

Eliminar la aplicación externa **Editor** (Next.js + Prisma + servicio systemd en EC2) y sustituir los webhooks HTTP entre WordPress y el Editor por una arquitectura interna y liviana dentro de `flacso-uruguay-plugin`. 

WordPress interactúa directamente con la base de datos PostgreSQL existente para registrar consultas y utiliza su cliente nativo con la API v3.1 de Mailjet para el despacho de correos transaccionales bajo la premisa irrenunciable: **guardar primero en PostgreSQL, enviar después a Mailjet**.

---

## 2. Alcance y Límites

### Dentro de alcance
- **Persistencia directa en PostgreSQL:** Conservación sin cambios de las tablas existentes `offer_inquiries` y `seminar_inquiries`, más la tabla `Consulta` para contacto general.
- **Servicios PHP internos:** Creación de `FLACSO_Offer_Inquiry_Service`, `FLACSO_Seminar_Inquiry_Service` y `FLACSO_General_Inquiry_Service`.
- **Despacho transaccional Mailjet:** Migración del envío de correos para `consulta_abierta`, `consulta_cerrada`, seminarios y consultas generales hacia `FLACSO_Mailjet_Client`.
- **Estrategia híbrida de plantillas:** Soporte para IDs de plantilla de Mailjet (`TemplateID`) con respaldo HTML institucional generado en PHP si el template no está definido o falla.
- **Idempotencia:** Verificación y unicidad basada en `consultaId` (generado desde `event_id` / UUIDv4).
- **Testabilidad desacoplada:** Inyección de conexión PDO para permitir tests automáticos usando SQLite en memoria sin requerir PostgreSQL activo en desarrollo.
- **Apagado del Editor:** Desactivación de `flacso-editor.service` y archivado del repositorio `flacso-uruguay-editor`.

### Fuera de alcance
- Funcionalidades del CRM y dashboard del Editor: gestión de contactos (`Contact`), programas académicos (`AcademicProgram`), asignaciones de usuarios, fases 2 y 3 de email marketing, sincronización de contactos a listas de Mailjet, autenticación Google/NextAuth, webhooks entrantes de Mailjet y notificaciones de Telegram.
- Las columnas foráneas de CRM en PostgreSQL (`contactId`, `academicProgramId`, `editionId`) se registrarán como `NULL`.

---

## 3. Arquitectura del Sistema

### Flujo Simplificado

```text
                                flacso.edu.uy
                                      │
                            Plugin FLACSO Uruguay
                                      │
              ┌───────────────────────┼───────────────────────┐
              │                       │                       │
              ▼                       ▼                       ▼
      Consulta Oferta        Consulta Seminario       Consulta General
              │                       │                       │
              └───────────────┬───────┘                       │
                              │                               │
                              ▼                               ▼
                 PostgreSQL (offer_inquiries,        PostgreSQL (Consulta)
                     seminar_inquiries)                       │
                              │                               │
                              └───────────────┬───────────────┘
                                              ▼
                                           Mailjet
                                     (API v3.1 Send)
                                              │
                                              ▼
                                 Actualización emailStatus
```

### Estructura de Directorios en `flacso-uruguay-plugin`

```text
flacso-uruguay-plugin/
├── includes/
│   ├── database/
│   │   ├── class-flacso-db.php
│   │   └── repositories/
│   │       ├── class-flacso-offer-inquiry-repository.php
│   │       ├── class-flacso-seminar-inquiry-repository.php
│   │       └── class-flacso-general-inquiry-repository.php
│   └── integrations/
│       └── class-flacso-mailjet-client.php
├── modules/
│   └── consultas/
│       ├── services/
│       │   ├── class-flacso-offer-inquiry-service.php
│       │   ├── class-flacso-seminar-inquiry-service.php
│       │   └── class-flacso-general-inquiry-service.php
│       └── init.php
└── tests/
    ├── inquiry-database-test.php
    ├── inquiry-mailjet-client-test.php
    └── inquiry-services-test.php
```

---

## 4. Capa de Base de Datos y Repositorios

### 4.1. Conexión PDO (`FLACSO_DB`)
- Clase singleton diferida (`class-flacso-db.php`).
- Lee credenciales de infraestructura definidas en `wp-config.php`:
  ```php
  define( 'FLACSO_POSTGRES_DSN', 'pgsql:host=localhost;port=5432;dbname=flacso_editor' );
  define( 'FLACSO_POSTGRES_USER', 'flacso_user' );
  define( 'FLACSO_POSTGRES_PASSWORD', '...' );
  ```
- Opciones PDO obligatorias:
  - `PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION`
  - `PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC`
- Soporte para pruebas: método estático `FLACSO_DB::set_connection(?PDO $pdo)` que permite inyectar una conexión SQLite en memoria (`sqlite::memory:`) durante la ejecución de tests automatizados sin requerir PostgreSQL ni alterar el código de producción.

### 4.2. Repositorios
Cada repositorio abstrae las sentencias SQL preparadas y garantiza:
1. `find_by_consulta_id(string $consulta_id): ?array`
2. `insert(array $data): string`
3. `update_email_status(string $consulta_id, string $status, ?string $sender = null, ?string $message_id = null): bool`

#### Tablas gestionadas:
- **`offer_inquiries`:** Columnas canónicas (`consultaId`, `offerWpId`, `offerName`, `offerType`, `firstName`, `lastName`, `fullName`, `email`, `emailNormalized`, `country`, `profession`, `educationLevel`, `source`, `campaign*`, `urlBase`, `urlReferer`, `inquiryAt`, `replyToEmail`, `programUrl`, `cartaUrl`, `preinscripcionUrl`, `offerStatus`, `emailStatus`, `payload`).
- **`seminar_inquiries`:** Columnas canónicas (`consultaId`, `seminarWpId`, `seminarName`, `seminarType`, `firstName`, `lastName`, `fullName`, `email`, `emailNormalized`, `country`, `source`, `campaign*`, `inquiryAt`, `emailStatus`, `payload`).
- **`Consulta`:** Columnas canónicas (`consultaId`, `controlNumber`, `nombre`, `apellido`, `email`, `emailNormalized`, `telefono`, `asunto`, `mensaje`, `urlReferer`, `ipAddress`, `userAgent`, `emailStatus`).

### 4.3. Idempotencia y Concurrencia
- La columna `consultaId` es `UNIQUE` en las tres tablas.
- Antes de insertar, el servicio consulta la existencia de `consultaId`. Si ya existe, retorna inmediatamente `{ ok: true, duplicate: true, ... }` evitando reenvío de correos o registros duplicados.
- Si dos peticiones simultáneas intentan el `INSERT`, el repositorio captura la violación de unicidad (SQLSTATE `23505`) y recupera el registro existente.

---

## 5. Integración con Mailjet (`FLACSO_Mailjet_Client`)

### 5.1. Configuración
Se reutilizan las opciones existentes en WordPress:
- `flacso_mailjet_api_key`
- `flacso_mailjet_secret_key`
- `flacso_mailjet_sender_email`
- `flacso_mailjet_sender_name`

Y se agregan opciones para IDs de plantillas:
- `flacso_mailjet_template_consulta_abierta`
- `flacso_mailjet_template_consulta_cerrada`
- `flacso_mailjet_template_consulta_seminario`
- `flacso_mailjet_template_consulta_general`

### 5.2. Enfoque Híbrido: TemplateID + Fallback HTML
1. Si el `TemplateID` está configurado para la variante solicitada, despacha mediante API v3.1 con `TemplateLanguage = true`, variables correspondientes y `CustomID = consultaId`.
2. Si el `TemplateID` no está definido, está vacío, o Mailjet retorna un error de plantilla no encontrada, despacha de inmediato con cuerpo HTML y texto plano generado internamente en PHP con el diseño institucional de FLACSO Uruguay.

### 5.3. Normalización del Resultado
Retorna siempre:
```php
[
    'ok'           => bool,
    'status'       => 'sent' | 'failed' | 'skipped',
    'sender'       => string,
    'message_id'   => ?string,
    'message_uuid' => ?string,
    'error'        => ?string,
]
```

---

## 6. Servicios y Ciclo de Vida: "Guardar Primero, Enviar Después"

### 6.1. Secuencia de Ejecución
Para cualquier formulario:
1. **Validar y Sanitizar:** Verificar campos mínimos obligatorios.
2. **Obtener `consultaId`:** Extraer `event_id` o generar UUIDv4.
3. **Verificar Idempotencia:** Si existe en el repositorio $\rightarrow$ salir con éxito y bandera `duplicate = true`.
4. **INSERT en Base de Datos:** Guardar registro con `emailStatus = 'skipped'`.
   - **Si falla el INSERT:** Registrar error, interrumpir flujo y retornar error al usuario. La consulta **no se procesa** y Mailjet **no se llama**.
5. **Despachar Mailjet:** Enviar correo transaccional.
   - Si Mailjet responde exitoso $\rightarrow$ `status = 'sent'`.
   - Si Mailjet falla o excede el timeout de 10s $\rightarrow$ `status = 'failed'`. **La consulta permanece a salvo en PostgreSQL**.
6. **UPDATE en Base de Datos:** Actualizar `emailStatus` y `mailjetMessageId`.
7. **Responder al Cliente:** Retornar éxito con identificador de consulta.

---

## 7. Integración con Handlers de WordPress

1. **`flacso_enviar_consulta_func()`:**
   En `modules/main-page/includes/flacso-consultas.php`, delega en `FLACSO_Offer_Inquiry_Service::submit($offer_payload)`.
2. **`submit_consulta_seminario()`:**
   En `modules/oferta-academica/includes/class-academic-api.php`, delega en `FLACSO_Seminar_Inquiry_Service::submit($payload)`.
3. **`fc_handle_form_submit()`:**
   En `modules/formularios/includes/form-handlers.php`, delega en `FLACSO_General_Inquiry_Service::submit($webhook_payload)`.

---

## 8. Estrategia de Testing

1. **`inquiry-database-test.php`:**
   - Inyección de SQLite en memoria.
   - Verificación de creación de registros, recuperación por `consultaId`, actualización de estado y rechazo de duplicados.
2. **`inquiry-mailjet-client-test.php`:**
   - Mocks de `pre_http_request`.
   - Verificación de payload con `TemplateID`, activación de fallback HTML, y manejo de errores/timeouts.
3. **`inquiry-services-test.php`:**
   - Pruebas integradas de los tres servicios validando: éxito total, fallo de Mailjet con preservación de consulta, y aborto por fallo de DB.

---

## 9. Plan de Despliegue y Retiro del Editor

1. **Paso 1:** Implementación de componentes en `flacso-uruguay-plugin` y verificación local con tests automatizados (100% aprobados).
2. **Paso 2:** En el servidor: asegurar extensión `pdo_pgsql` activa y configurar constantes `FLACSO_POSTGRES_*` en `wp-config.php`.
3. **Paso 3:** Conectar los handlers de WordPress a los nuevos servicios PHP y verificar recepción de consultas reales en producción.
4. **Paso 4:** Detener y deshabilitar el servicio del Editor:
   ```bash
   sudo systemctl stop flacso-editor.service
   sudo systemctl disable flacso-editor.service
   ```
5. **Paso 5:** Archivar el repositorio `flacso-uruguay-editor` en modo solo lectura para resguardo histórico.
