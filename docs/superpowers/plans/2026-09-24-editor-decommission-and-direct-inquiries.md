# Desmantelamiento del Editor y Persistencia Directa de Consultas - Plan de Implementación

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Eliminar el intermediario Editor (Next.js/Prisma/EC2) y migrar la recepción de consultas de ofertas, seminarios y contacto general directamente al plugin de WordPress con persistencia en PostgreSQL ("guardar primero") y despacho transaccional en Mailjet ("enviar después").

**Architecture:** Se implementa un diseño en capas dentro de `flacso-uruguay-plugin`: conexión PDO desacoplada (`FLACSO_DB`), repositorios específicos para cada tabla (`offer_inquiries`, `seminar_inquiries`, `Consulta`), un cliente Mailjet v3.1 híbrido (TemplateID + fallback HTML institucional en PHP), y servicios de negocio que orquestan validación, idempotencia, inserción obligatoria y actualización del estado del correo.

**Tech Stack:** PHP 8+, PDO (soporta PostgreSQL `pdo_pgsql` en producción y SQLite en memoria para tests automáticos), Mailjet Send API v3.1 (`wp_remote_post`), WordPress REST API / AJAX handlers.

**Spec:** [`docs/superpowers/specs/2026-09-24-editor-decommission-and-direct-inquiries-design.md`](file:///home/ivan/repositorios/FLACSO%20Uruguay/Migrar/flacso-uruguay-plugin/docs/superpowers/specs/2026-09-24-editor-decommission-and-direct-inquiries-design.md)

## Global Constraints

- Nunca enviar un correo por Mailjet si la consulta no quedó previamente persistida en la base de datos ("guardar primero, enviar después").
- Un fallo en Mailjet nunca debe provocar la pérdida o error fatal de la consulta guardada (debe marcar `emailStatus = 'failed'`).
- Preservar estricta compatibilidad con las columnas canónicas de PostgreSQL (`offer_inquiries`, `seminar_inquiries` y `Consulta`).
- Las claves foráneas de CRM (`contactId`, `academicProgramId`, `editionId`) se insertan como `NULL`.
- Idempotencia estricta: un `consultaId` ya existente no debe duplicar la fila ni reenviar el correo.
- Todos los tests automatizados deben ejecutarse con `php tests/<nombre-test>.php` y pasar en verde usando SQLite en memoria sin requerir PostgreSQL local.

---

### Task 1: Adaptador de Conexión PDO (`FLACSO_DB`)

**Files:**
- Create: `flacso-uruguay-plugin/includes/database/class-flacso-db.php`
- Test: `flacso-uruguay-plugin/tests/inquiry-db-connection-test.php`

**Interfaces:**
- Consumes: Constantes globales `FLACSO_POSTGRES_DSN`, `FLACSO_POSTGRES_USER`, `FLACSO_POSTGRES_PASSWORD`.
- Produces:
  - `FLACSO_DB::connection(): PDO`
  - `FLACSO_DB::set_connection(?PDO $pdo): void`
  - `FLACSO_DB::reset(): void`
  - `FLACSO_DB::is_configured(): bool`

- [ ] **Step 1: Write the failing test**

```php
<?php
// tests/inquiry-db-connection-test.php
$root = dirname(__DIR__);
require_once $root . '/includes/database/class-flacso-db.php';

function db_assert(bool $cond, string $msg): void {
    if (!$cond) {
        fwrite(STDERR, "FAIL: {$msg}\n");
        exit(1);
    }
}

// 1. Sin configuración inicial y sin conexión inyectada
FLACSO_DB::reset();
db_assert(FLACSO_DB::is_configured() === false, 'Sin constantes FLACSO_POSTGRES_* debe retornar false en is_configured');

// 2. Inyección de conexión SQLite en memoria
$test_pdo = new PDO('sqlite::memory:', null, null, [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
]);
FLACSO_DB::set_connection($test_pdo);
db_assert(FLACSO_DB::connection() === $test_pdo, 'connection() debe retornar la instancia inyectada');
db_assert(FLACSO_DB::is_configured() === true, 'Con conexión inyectada is_configured debe retornar true');

// 3. Reset
FLACSO_DB::reset();
db_assert(FLACSO_DB::is_configured() === false, 'Tras reset() vuelve a no configurado');

echo "OK inquiry-db-connection-test\n";
```

- [ ] **Step 2: Run test to verify it fails**

Run: `php tests/inquiry-db-connection-test.php`
Expected: FAIL with `Failed opening required '.../class-flacso-db.php'`

- [ ] **Step 3: Write minimal implementation**

```php
<?php
/**
 * Conector de base de datos para FLACSO Uruguay.
 */

if (!defined('ABSPATH') && !defined('STDIN')) {
    exit;
}

class FLACSO_DB {
    private static ?PDO $connection = null;

    public static function connection(): PDO {
        if (self::$connection instanceof PDO) {
            return self::$connection;
        }

        if (!self::is_configured()) {
            throw new RuntimeException('PostgreSQL no está configurado. Defina FLACSO_POSTGRES_DSN, FLACSO_POSTGRES_USER y FLACSO_POSTGRES_PASSWORD.');
        }

        if (!extension_loaded('pdo_pgsql')) {
            throw new RuntimeException('La extensión PHP pdo_pgsql no está disponible en este servidor.');
        }

        self::$connection = new PDO(
            FLACSO_POSTGRES_DSN,
            FLACSO_POSTGRES_USER,
            FLACSO_POSTGRES_PASSWORD,
            [
                PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_TIMEOUT            => 5,
            ]
        );

        return self::$connection;
    }

    public static function set_connection(?PDO $pdo): void {
        self::$connection = $pdo;
    }

    public static function reset(): void {
        self::$connection = null;
    }

    public static function is_configured(): bool {
        if (self::$connection instanceof PDO) {
            return true;
        }

        return defined('FLACSO_POSTGRES_DSN') &&
               defined('FLACSO_POSTGRES_USER') &&
               defined('FLACSO_POSTGRES_PASSWORD') &&
               FLACSO_POSTGRES_DSN !== '';
    }
}
```

- [ ] **Step 4: Run test to verify it passes**

Run: `php tests/inquiry-db-connection-test.php`
Expected: `OK inquiry-db-connection-test`

- [ ] **Step 5: Commit**

```bash
git add includes/database/class-flacso-db.php tests/inquiry-db-connection-test.php
git commit -m "feat(database): add FLACSO_DB PDO adapter with test injection"
```

---

### Task 2: Repositorios de Consultas (`Offer`, `Seminar`, `General`)

**Files:**
- Create: `flacso-uruguay-plugin/includes/database/repositories/class-flacso-offer-inquiry-repository.php`
- Create: `flacso-uruguay-plugin/includes/database/repositories/class-flacso-seminar-inquiry-repository.php`
- Create: `flacso-uruguay-plugin/includes/database/repositories/class-flacso-general-inquiry-repository.php`
- Test: `flacso-uruguay-plugin/tests/inquiry-repositories-test.php`

**Interfaces:**
- Consumes: `FLACSO_DB::connection(): PDO`
- Produces:
  - `FLACSO_Offer_Inquiry_Repository::find_by_consulta_id(string $consulta_id): ?array`
  - `FLACSO_Offer_Inquiry_Repository::insert(array $data): array` (retorna `['id' => ..., 'consultaId' => ..., 'duplicate' => bool]`)
  - `FLACSO_Offer_Inquiry_Repository::update_email_status(string $consulta_id, string $status, ?string $sender = null, ?string $message_id = null): bool`
  - (Métodos equivalentes para `FLACSO_Seminar_Inquiry_Repository` y `FLACSO_General_Inquiry_Repository`)

- [ ] **Step 1: Write the failing test**

```php
<?php
// tests/inquiry-repositories-test.php
$root = dirname(__DIR__);
require_once $root . '/includes/database/class-flacso-db.php';
require_once $root . '/includes/database/repositories/class-flacso-offer-inquiry-repository.php';
require_once $root . '/includes/database/repositories/class-flacso-seminar-inquiry-repository.php';
require_once $root . '/includes/database/repositories/class-flacso-general-inquiry-repository.php';

function repo_assert(bool $cond, string $msg): void {
    if (!$cond) {
        fwrite(STDERR, "FAIL: {$msg}\n");
        exit(1);
    }
}

// Configurar SQLite en memoria con el esquema de tablas
$pdo = new PDO('sqlite::memory:', null, null, [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
]);

$pdo->exec('
CREATE TABLE offer_inquiries (
    id TEXT PRIMARY KEY,
    consultaId TEXT UNIQUE,
    offerWpId INTEGER,
    offerName TEXT,
    offerType TEXT,
    firstName TEXT,
    lastName TEXT,
    fullName TEXT,
    email TEXT,
    emailNormalized TEXT,
    country TEXT,
    profession TEXT,
    educationLevel TEXT,
    source TEXT DEFAULT "Web",
    campaignProvider TEXT,
    campaignSource TEXT,
    campaignMedium TEXT,
    campaignName TEXT,
    campaignExternalId TEXT,
    campaignContent TEXT,
    campaignTerm TEXT,
    urlBase TEXT,
    urlReferer TEXT,
    inquiryAt TEXT,
    ipAddress TEXT,
    userAgent TEXT,
    replyToEmail TEXT,
    programUrl TEXT,
    cartaUrl TEXT,
    preinscripcionUrl TEXT,
    offerStatus TEXT,
    emailStatus TEXT DEFAULT "skipped",
    emailSender TEXT,
    gmailMessageUrl TEXT,
    mailjetMessageId TEXT,
    payload TEXT,
    createdAt TEXT,
    updatedAt TEXT
);

CREATE TABLE seminar_inquiries (
    id TEXT PRIMARY KEY,
    consultaId TEXT UNIQUE,
    seminarWpId INTEGER,
    seminarName TEXT,
    seminarType TEXT,
    firstName TEXT,
    lastName TEXT,
    fullName TEXT,
    email TEXT,
    emailNormalized TEXT,
    country TEXT,
    profession TEXT,
    educationLevel TEXT,
    source TEXT DEFAULT "Seminario",
    campaignProvider TEXT,
    campaignSource TEXT,
    campaignMedium TEXT,
    campaignName TEXT,
    campaignExternalId TEXT,
    campaignContent TEXT,
    campaignTerm TEXT,
    urlBase TEXT,
    urlReferer TEXT,
    inquiryAt TEXT,
    ipAddress TEXT,
    userAgent TEXT,
    replyToEmail TEXT,
    programUrl TEXT,
    cartaUrl TEXT,
    preinscripcionUrl TEXT,
    offerStatus TEXT,
    emailStatus TEXT DEFAULT "skipped",
    emailSender TEXT,
    gmailMessageUrl TEXT,
    mailjetMessageId TEXT,
    payload TEXT,
    createdAt TEXT,
    updatedAt TEXT
);

CREATE TABLE Consulta (
    id TEXT PRIMARY KEY,
    consultaId TEXT UNIQUE,
    controlNumber TEXT,
    nombre TEXT,
    apellido TEXT,
    email TEXT,
    emailNormalized TEXT,
    telefono TEXT,
    asunto TEXT,
    mensaje TEXT,
    urlReferer TEXT,
    ipAddress TEXT,
    userAgent TEXT,
    emailStatus TEXT DEFAULT "skipped",
    emailSender TEXT,
    mailjetMessageId TEXT,
    createdAt TEXT,
    updatedAt TEXT
);
');

FLACSO_DB::set_connection($pdo);

// 1. Probar offer_inquiries
$offer_repo = new FLACSO_Offer_Inquiry_Repository();
$res1 = $offer_repo->insert([
    'consultaId' => 'cid-offer-001',
    'offerWpId'  => 101,
    'offerName'  => 'Maestría de Prueba',
    'firstName'  => 'Laura',
    'lastName'   => 'Gómez',
    'fullName'   => 'Laura Gómez',
    'email'      => 'laura@ejemplo.com',
    'country'    => 'Uruguay',
]);
repo_assert(!empty($res1['id']), 'Debe retornar ID generado');
repo_assert($res1['duplicate'] === false, 'No debe ser duplicado');

// Idempotencia en offer_inquiries
$res1_dup = $offer_repo->insert([
    'consultaId' => 'cid-offer-001',
    'offerWpId'  => 101,
    'offerName'  => 'Maestría de Prueba',
    'email'      => 'laura@ejemplo.com',
]);
repo_assert($res1_dup['duplicate'] === true, 'Debe detectar duplicado');
repo_assert($res1_dup['id'] === $res1['id'], 'El ID debe ser idéntico al original');

// Actualizar emailStatus
$offer_repo->update_email_status('cid-offer-001', 'sent', 'remitente@flacso.edu.uy', 'mj-12345');
$found_offer = $offer_repo->find_by_consulta_id('cid-offer-001');
repo_assert($found_offer['emailStatus'] === 'sent', 'emailStatus debe ser sent');
repo_assert($found_offer['mailjetMessageId'] === 'mj-12345', 'mailjetMessageId debe ser mj-12345');

// 2. Probar seminar_inquiries
$seminar_repo = new FLACSO_Seminar_Inquiry_Repository();
$res2 = $seminar_repo->insert([
    'consultaId'   => 'cid-sem-001',
    'seminarWpId'  => 202,
    'seminarName'  => 'Seminario Género',
    'firstName'    => 'Carlos',
    'lastName'     => 'Ruiz',
    'fullName'     => 'Carlos Ruiz',
    'email'        => 'carlos@ejemplo.com',
]);
repo_assert(!empty($res2['id']), 'Debe retornar ID de seminario');
$seminar_repo->update_email_status('cid-sem-001', 'failed');
$found_sem = $seminar_repo->find_by_consulta_id('cid-sem-001');
repo_assert($found_sem['emailStatus'] === 'failed', 'emailStatus de seminario debe ser failed');

// 3. Probar Consulta (general)
$general_repo = new FLACSO_General_Inquiry_Repository();
$res3 = $general_repo->insert([
    'consultaId' => 'cid-gen-001',
    'nombre'     => 'Ana',
    'apellido'   => 'Pérez',
    'email'      => 'ana@ejemplo.com',
    'asunto'     => 'Consulta general',
    'mensaje'    => 'Hola',
]);
repo_assert(!empty($res3['controlNumber']), 'Debe generar controlNumber');
$general_repo->update_email_status('cid-gen-001', 'sent', 'contacto@flacso.edu.uy', 'mj-67890');
$found_gen = $general_repo->find_by_consulta_id('cid-gen-001');
repo_assert($found_gen['emailStatus'] === 'sent', 'emailStatus de Consulta debe ser sent');

echo "OK inquiry-repositories-test\n";
```

- [ ] **Step 2: Run test to verify it fails**

Run: `php tests/inquiry-repositories-test.php`
Expected: FAIL with `Failed opening required '.../class-flacso-offer-inquiry-repository.php'`

- [ ] **Step 3: Write minimal implementation**

Implement `FLACSO_Offer_Inquiry_Repository`, `FLACSO_Seminar_Inquiry_Repository`, y `FLACSO_General_Inquiry_Repository`.
Cada una gestiona UUID (con `wp_generate_uuid4()` o `bin2hex(random_bytes(16))`), manejo seguro de JSON con `json_encode`, timestamps ISO y control de colisiones de clave primaria/única capturando `PDOException`.

- [ ] **Step 4: Run test to verify it passes**

Run: `php tests/inquiry-repositories-test.php`
Expected: `OK inquiry-repositories-test`

- [ ] **Step 5: Commit**

```bash
git add includes/database/repositories/ tests/inquiry-repositories-test.php
git commit -m "feat(database): add inquiry repositories for offer, seminar, and general tables"
```

---

### Task 3: Cliente Mailjet Transaccional (`FLACSO_Mailjet_Client`)

**Files:**
- Create: `flacso-uruguay-plugin/includes/integrations/class-flacso-mailjet-client.php`
- Test: `flacso-uruguay-plugin/tests/inquiry-mailjet-client-test.php`

**Interfaces:**
- Consumes: Opciones de WordPress (`flacso_mailjet_*`) y `wp_remote_post`.
- Produces:
  - `FLACSO_Mailjet_Client::send(array $params): array`
  - `FLACSO_Mailjet_Client::send_offer_inquiry(array $inquiry, array $program): array`
  - `FLACSO_Mailjet_Client::send_seminar_inquiry(array $inquiry, array $seminar): array`
  - `FLACSO_Mailjet_Client::send_general_inquiry(array $inquiry): array`

- [ ] **Step 1: Write the failing test**

```php
<?php
// tests/inquiry-mailjet-client-test.php
$root = dirname(__DIR__);

if (!defined('ABSPATH')) {
    define('ABSPATH', __DIR__ . '/');
}

$GLOBALS['mailjet_mock_options'] = [
    'flacso_mailjet_api_key'                 => 'mock-api-key',
    'flacso_mailjet_secret_key'              => 'mock-secret-key',
    'flacso_mailjet_sender_email'            => 'notificaciones@flacso.edu.uy',
    'flacso_mailjet_sender_name'             => 'FLACSO Uruguay',
    'flacso_mailjet_template_consulta_abierta' => '12345',
];

if (!function_exists('get_option')) {
    function get_option($k, $d = false) {
        return $GLOBALS['mailjet_mock_options'][$k] ?? $d;
    }
}

$GLOBALS['mailjet_http_calls'] = [];

if (!function_exists('wp_remote_post')) {
    function wp_remote_post($url, $args) {
        $GLOBALS['mailjet_http_calls'][] = ['url' => $url, 'args' => $args];
        $body = json_decode($args['body'], true);
        
        // Simular respuesta exitosa de Mailjet v3.1
        return [
            'response' => ['code' => 200, 'message' => 'OK'],
            'body'     => json_encode([
                'Messages' => [
                    [
                        'Status' => 'success',
                        'CustomID' => $body['Messages'][0]['CustomID'] ?? '',
                        'To' => [
                            [
                                'Email' => $body['Messages'][0]['To'][0]['Email'] ?? '',
                                'MessageID' => '288230407340150000',
                                'MessageUUID' => 'f7b8a8b1-1234-5678-90ab-cdef12345678',
                            ]
                        ]
                    ]
                ]
            ])
        ];
    }
}

if (!function_exists('is_wp_error')) {
    function is_wp_error($thing) {
        return false;
    }
}

if (!function_exists('wp_remote_retrieve_response_code')) {
    function wp_remote_retrieve_response_code($response) {
        return $response['response']['code'] ?? 0;
    }
}

if (!function_exists('wp_remote_retrieve_body')) {
    function wp_remote_retrieve_body($response) {
        return $response['body'] ?? '';
    }
}

require_once $root . '/includes/integrations/class-flacso-mailjet-client.php';

function mj_assert(bool $cond, string $msg): void {
    if (!$cond) {
        fwrite(STDERR, "FAIL: {$msg}\n");
        exit(1);
    }
}

// 1. Envío de consulta abierta con TemplateID configurado
$res_open = FLACSO_Mailjet_Client::send_offer_inquiry(
    [
        'consultaId' => 'cid-mj-001',
        'email'      => 'interesado@ejemplo.com',
        'firstName'  => 'Mariana',
        'fullName'   => 'Mariana Silva',
    ],
    [
        'id'                     => 55,
        'name'                   => 'Diploma en Políticas Públicas',
        'isInscripcionesAbiertas'=> true,
        'urlBase'                => 'https://flacso.edu.uy/formacion/politicas-publicas',
        'preinscripcionUrl'      => 'https://flacso.edu.uy/formacion/politicas-publicas/preinscripcion',
        'startValue'             => '7 de abril 2026',
        'modalityLabel'          => 'Virtual',
    ]
);

mj_assert($res_open['ok'] === true, 'El envío debe ser exitoso');
mj_assert($res_open['status'] === 'sent', 'Status debe ser sent');
mj_assert($res_open['message_id'] === '288230407340150000', 'Debe capturar message_id');

$last_call = end($GLOBALS['mailjet_http_calls']);
$sent_payload = json_decode($last_call['args']['body'], true);
mj_assert($sent_payload['Messages'][0]['TemplateID'] === 12345, 'Debe incluir TemplateID 12345');
mj_assert($sent_payload['Messages'][0]['TemplateLanguage'] === true, 'TemplateLanguage debe ser true');

// 2. Envío sin TemplateID (debe activar fallback HTML interno)
$res_closed = FLACSO_Mailjet_Client::send_offer_inquiry(
    [
        'consultaId' => 'cid-mj-002',
        'email'      => 'interesado2@ejemplo.com',
        'firstName'  => 'Pedro',
        'fullName'   => 'Pedro Rossi',
    ],
    [
        'id'                     => 56,
        'name'                   => 'Maestría Cerrada',
        'isInscripcionesAbiertas'=> false,
        'urlBase'                => 'https://flacso.edu.uy/formacion/maestria',
    ]
);

mj_assert($res_closed['ok'] === true, 'El fallback debe enviar con éxito');
$last_call2 = end($GLOBALS['mailjet_http_calls']);
$sent_payload2 = json_decode($last_call2['args']['body'], true);
mj_assert(empty($sent_payload2['Messages'][0]['TemplateID']), 'Sin TemplateID configurado no debe enviar TemplateID');
mj_assert(!empty($sent_payload2['Messages'][0]['HTMLPart']), 'Debe incluir HTMLPart generado');

echo "OK inquiry-mailjet-client-test\n";
```

- [ ] **Step 2: Run test to verify it fails**

Run: `php tests/inquiry-mailjet-client-test.php`
Expected: FAIL with `Failed opening required '.../class-flacso-mailjet-client.php'`

- [ ] **Step 3: Write minimal implementation**

Implementar `FLACSO_Mailjet_Client`:
- Autenticación HTTP Basic Auth con `flacso_mailjet_api_key` y `flacso_mailjet_secret_key`.
- `send_offer_inquiry`, `send_seminar_inquiry`, `send_general_inquiry`.
- Soporte para variables estándar (`nombre`, `oferta_academica_nombre`, etc.).
- Generación de HTML y texto plano institucional de respaldo cuando no hay plantilla Mailjet.
- Timeout de 10s y captura de errores sin lanzar excepciones fatales.

- [ ] **Step 4: Run test to verify it passes**

Run: `php tests/inquiry-mailjet-client-test.php`
Expected: `OK inquiry-mailjet-client-test`

- [ ] **Step 5: Commit**

```bash
git add includes/integrations/class-flacso-mailjet-client.php tests/inquiry-mailjet-client-test.php
git commit -m "feat(mailjet): add FLACSO_Mailjet_Client with template and fallback HTML support"
```

---

### Task 4: Servicios de Negocio (`Offer`, `Seminar`, `General`)

**Files:**
- Create: `flacso-uruguay-plugin/modules/consultas/services/class-flacso-offer-inquiry-service.php`
- Create: `flacso-uruguay-plugin/modules/consultas/services/class-flacso-seminar-inquiry-service.php`
- Create: `flacso-uruguay-plugin/modules/consultas/services/class-flacso-general-inquiry-service.php`
- Create: `flacso-uruguay-plugin/modules/consultas/init.php`
- Test: `flacso-uruguay-plugin/tests/inquiry-services-test.php`

**Interfaces:**
- Consumes: Repositorios (`Task 2`) y `FLACSO_Mailjet_Client` (`Task 3`).
- Produces:
  - `FLACSO_Offer_Inquiry_Service::submit(array $data): array`
  - `FLACSO_Seminar_Inquiry_Service::submit(array $data): array`
  - `FLACSO_General_Inquiry_Service::submit(array $data): array`

- [ ] **Step 1: Write the failing test**

```php
<?php
// tests/inquiry-services-test.php
$root = dirname(__DIR__);

if (!defined('ABSPATH')) {
    define('ABSPATH', __DIR__ . '/');
}

require_once $root . '/includes/database/class-flacso-db.php';
require_once $root . '/includes/database/repositories/class-flacso-offer-inquiry-repository.php';
require_once $root . '/includes/database/repositories/class-flacso-seminar-inquiry-repository.php';
require_once $root . '/includes/database/repositories/class-flacso-general-inquiry-repository.php';
require_once $root . '/includes/integrations/class-flacso-mailjet-client.php';
require_once $root . '/modules/consultas/services/class-flacso-offer-inquiry-service.php';
require_once $root . '/modules/consultas/services/class-flacso-seminar-inquiry-service.php';
require_once $root . '/modules/consultas/services/class-flacso-general-inquiry-service.php';

function srv_assert(bool $cond, string $msg): void {
    if (!$cond) {
        fwrite(STDERR, "FAIL: {$msg}\n");
        exit(1);
    }
}

// Configurar SQLite en memoria
$pdo = new PDO('sqlite::memory:', null, null, [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
]);
// Reutilizar DDL de offer_inquiries, seminar_inquiries y Consulta
$pdo->exec('
CREATE TABLE offer_inquiries (
    id TEXT PRIMARY KEY, consultaId TEXT UNIQUE, offerWpId INTEGER, offerName TEXT, offerType TEXT,
    firstName TEXT, lastName TEXT, fullName TEXT, email TEXT, emailNormalized TEXT, country TEXT,
    profession TEXT, educationLevel TEXT, source TEXT, campaignProvider TEXT, campaignSource TEXT,
    campaignMedium TEXT, campaignName TEXT, campaignExternalId TEXT, campaignContent TEXT, campaignTerm TEXT,
    urlBase TEXT, urlReferer TEXT, inquiryAt TEXT, ipAddress TEXT, userAgent TEXT, replyToEmail TEXT,
    programUrl TEXT, cartaUrl TEXT, preinscripcionUrl TEXT, offerStatus TEXT, emailStatus TEXT,
    emailSender TEXT, gmailMessageUrl TEXT, mailjetMessageId TEXT, payload TEXT, createdAt TEXT, updatedAt TEXT
);
CREATE TABLE seminar_inquiries (
    id TEXT PRIMARY KEY, consultaId TEXT UNIQUE, seminarWpId INTEGER, seminarName TEXT, seminarType TEXT,
    firstName TEXT, lastName TEXT, fullName TEXT, email TEXT, emailNormalized TEXT, country TEXT,
    profession TEXT, educationLevel TEXT, source TEXT, campaignProvider TEXT, campaignSource TEXT,
    campaignMedium TEXT, campaignName TEXT, campaignExternalId TEXT, campaignContent TEXT, campaignTerm TEXT,
    urlBase TEXT, urlReferer TEXT, inquiryAt TEXT, ipAddress TEXT, userAgent TEXT, replyToEmail TEXT,
    programUrl TEXT, cartaUrl TEXT, preinscripcionUrl TEXT, offerStatus TEXT, emailStatus TEXT,
    emailSender TEXT, gmailMessageUrl TEXT, mailjetMessageId TEXT, payload TEXT, createdAt TEXT, updatedAt TEXT
);
CREATE TABLE Consulta (
    id TEXT PRIMARY KEY, consultaId TEXT UNIQUE, controlNumber TEXT, nombre TEXT, apellido TEXT,
    email TEXT, emailNormalized TEXT, telefono TEXT, asunto TEXT, mensaje TEXT, urlReferer TEXT,
    ipAddress TEXT, userAgent TEXT, emailStatus TEXT, emailSender TEXT, mailjetMessageId TEXT,
    createdAt TEXT, updatedAt TEXT
);
');
FLACSO_DB::set_connection($pdo);

// 1. Prueba de submit de oferta exitoso (guardar primero, enviar después)
$result_offer = FLACSO_Offer_Inquiry_Service::submit([
    'event_id'        => 'srv-offer-001',
    'id_pagina'       => 10,
    'titulo_posgrado' => 'Maestría en Educación',
    'nombre'          => 'Lucía',
    'apellido'        => 'Méndez',
    'correo'          => 'lucia@ejemplo.com',
    'pais'            => 'Uruguay',
]);
srv_assert($result_offer['ok'] === true, 'Offer submit debe ser ok');
srv_assert($result_offer['consulta_id'] === 'srv-offer-001', 'Debe retornar el consulta_id');

// Verificar que se guardó en BD
$repo = new FLACSO_Offer_Inquiry_Repository();
$saved = $repo->find_by_consulta_id('srv-offer-001');
srv_assert(!empty($saved), 'La fila debe existir en offer_inquiries');

// 2. Prueba de idempotencia (reenvío del mismo event_id)
$result_dup = FLACSO_Offer_Inquiry_Service::submit([
    'event_id'        => 'srv-offer-001',
    'id_pagina'       => 10,
    'nombre'          => 'Lucía',
    'correo'          => 'lucia@ejemplo.com',
]);
srv_assert($result_dup['ok'] === true, 'El duplicado debe responder ok');
srv_assert($result_dup['duplicate'] === true, 'Debe marcar duplicate = true');

// 3. Prueba de submit de seminario
$result_sem = FLACSO_Seminar_Inquiry_Service::submit([
    'event_id'         => 'srv-sem-001',
    'seminario_id'     => 45,
    'seminario_titulo' => 'Seminario Bioética',
    'nombre'           => 'Martín',
    'correo'           => 'martin@ejemplo.com',
    'pais'             => 'Argentina',
    'consulta'         => '¿Cuáles son los aranceles?',
]);
srv_assert($result_sem['ok'] === true, 'Seminar submit debe ser ok');

// 4. Prueba de submit de consulta general
$result_gen = FLACSO_General_Inquiry_Service::submit([
    'event_id' => 'srv-gen-001',
    'nombre'   => 'Valeria',
    'apellido' => 'Sosa',
    'email'    => 'valeria@ejemplo.com',
    'asunto'   => 'Secretaría',
    'mensaje'  => 'Información general de contacto',
]);
srv_assert($result_gen['ok'] === true, 'General submit debe ser ok');

echo "OK inquiry-services-test\n";
```

- [ ] **Step 2: Run test to verify it fails**

Run: `php tests/inquiry-services-test.php`
Expected: FAIL with `Failed opening required '.../class-flacso-offer-inquiry-service.php'`

- [ ] **Step 3: Write minimal implementation**

Implementar los 3 servicios y `modules/consultas/init.php`:
- Validación de campos requeridos.
- Normalización de datos y resolución de links/metas desde WordPress si los objetos existen.
- Idempotencia: chequeo antes de insertar.
- Inserción en base de datos; si falla, se corta el flujo retornando error y **no** se envía email.
- Despacho a Mailjet si la inserción fue exitosa.
- Actualización de `emailStatus` a `sent` o `failed`.

- [ ] **Step 4: Run test to verify it passes**

Run: `php tests/inquiry-services-test.php`
Expected: `OK inquiry-services-test`

- [ ] **Step 5: Commit**

```bash
git add modules/consultas/ tests/inquiry-services-test.php
git commit -m "feat(consultas): add offer, seminar, and general inquiry services"
```

---

### Task 5: Carga e Inicialización en el Plugin (`loader.php` y `flacso-uruguay.php`)

**Files:**
- Modify: `flacso-uruguay-plugin/includes/core/loader.php`
- Modify: `flacso-uruguay-plugin/flacso-uruguay.php`
- Test: `flacso-uruguay-plugin/tests/inquiry-plugin-loader-test.php`

**Interfaces:**
- Consumes: Clases creadas en Tasks 1 a 4.
- Produces: Carga automática de todas las clases al inicializar el plugin.

- [ ] **Step 1: Write the failing test**

```php
<?php
// tests/inquiry-plugin-loader-test.php
$root = dirname(__DIR__);

function loader_assert(bool $cond, string $msg): void {
    if (!$cond) {
        fwrite(STDERR, "FAIL: {$msg}\n");
        exit(1);
    }
}

$loader_code = (string) file_get_contents($root . '/includes/core/loader.php');
$plugin_code = (string) file_get_contents($root . '/flacso-uruguay.php');

loader_assert(strpos($loader_code, 'includes/database/class-flacso-db.php') !== false, 'loader.php debe incluir class-flacso-db.php');
loader_assert(strpos($loader_code, 'class-flacso-offer-inquiry-repository.php') !== false, 'loader.php debe incluir repositorios');
loader_assert(strpos($loader_code, 'includes/integrations/class-flacso-mailjet-client.php') !== false, 'loader.php debe incluir class-flacso-mailjet-client.php');
loader_assert(strpos($plugin_code, 'modules/consultas/init.php') !== false, 'flacso-uruguay.php debe cargar modules/consultas/init.php');

echo "OK inquiry-plugin-loader-test\n";
```

- [ ] **Step 2: Run test to verify it fails**

Run: `php tests/inquiry-plugin-loader-test.php`
Expected: FAIL with assertions failing.

- [ ] **Step 3: Modify `includes/core/loader.php` and `flacso-uruguay.php`**

Añadir las inclusiones requeridas en el orden adecuado (DB $\rightarrow$ Repositorios $\rightarrow$ Mailjet Client $\rightarrow$ Servicios de consultas).

- [ ] **Step 4: Run test to verify it passes**

Run: `php tests/inquiry-plugin-loader-test.php`
Expected: `OK inquiry-plugin-loader-test`

- [ ] **Step 5: Commit**

```bash
git add includes/core/loader.php flacso-uruguay.php tests/inquiry-plugin-loader-test.php
git commit -m "feat(core): load database, mailjet client, and consultas module in plugin lifecycle"
```

---

### Task 6: Conexión de Handlers de Formularios a los Servicios Internos

**Files:**
- Modify: `flacso-uruguay-plugin/modules/main-page/includes/flacso-consultas.php`
- Modify: `flacso-uruguay-plugin/modules/oferta-academica/includes/class-academic-api.php`
- Modify: `flacso-uruguay-plugin/modules/formularios/includes/form-handlers.php`
- Test: `flacso-uruguay-plugin/tests/inquiry-handler-wiring-test.php`

**Interfaces:**
- Consumes: `FLACSO_Offer_Inquiry_Service`, `FLACSO_Seminar_Inquiry_Service`, `FLACSO_General_Inquiry_Service`.
- Produces: Sustitución de llamadas a webhooks externos por llamadas directas a los servicios PHP locales.

- [ ] **Step 1: Write the failing test**

```php
<?php
// tests/inquiry-handler-wiring-test.php
$root = dirname(__DIR__);

function wiring_assert(bool $cond, string $msg): void {
    if (!$cond) {
        fwrite(STDERR, "FAIL: {$msg}\n");
        exit(1);
    }
}

$ofertas_code = (string) file_get_contents($root . '/modules/main-page/includes/flacso-consultas.php');
$seminarios_code = (string) file_get_contents($root . '/modules/oferta-academica/includes/class-academic-api.php');
$general_code = (string) file_get_contents($root . '/modules/formularios/includes/form-handlers.php');

wiring_assert(strpos($ofertas_code, 'FLACSO_Offer_Inquiry_Service::submit') !== false, 'flacso-consultas.php debe delegar en FLACSO_Offer_Inquiry_Service::submit');
wiring_assert(strpos($seminarios_code, 'FLACSO_Seminar_Inquiry_Service::submit') !== false, 'class-academic-api.php debe delegar en FLACSO_Seminar_Inquiry_Service::submit');
wiring_assert(strpos($general_code, 'FLACSO_General_Inquiry_Service::submit') !== false, 'form-handlers.php debe delegar en FLACSO_General_Inquiry_Service::submit');

echo "OK inquiry-handler-wiring-test\n";
```

- [ ] **Step 2: Run test to verify it fails**

Run: `php tests/inquiry-handler-wiring-test.php`
Expected: FAIL

- [ ] **Step 3: Modify handlers in `flacso-consultas.php`, `class-academic-api.php` and `form-handlers.php`**

Reemplazar el envío de webhooks externos por el llamado directo a `FLACSO_*_Inquiry_Service::submit()`.

- [ ] **Step 4: Run test to verify it passes**

Run: `php tests/inquiry-handler-wiring-test.php`
Expected: `OK inquiry-handler-wiring-test`

- [ ] **Step 5: Commit**

```bash
git add modules/main-page/includes/flacso-consultas.php modules/oferta-academica/includes/class-academic-api.php modules/formularios/includes/form-handlers.php tests/inquiry-handler-wiring-test.php
git commit -m "feat(handlers): wire form submit handlers directly to internal inquiry services"
```

---

### Task 7: Script CLI de Verificación y Guía de Desmantelamiento

**Files:**
- Create: `flacso-uruguay-plugin/scripts/verify-postgres-connection.php`
- Create: `flacso-uruguay-plugin/docs/decommission-editor-deployment-guide.md`
- Test: `flacso-uruguay-plugin/tests/inquiry-verify-script-test.php`

**Interfaces:**
- Consumes: `FLACSO_DB`
- Produces:
  - Script CLI ejecutable para probar conectividad y permisos contra PostgreSQL real antes de desplegar.
  - Documentación de procedimientos para apagar `flacso-editor.service` y archivar el repositorio.

- [ ] **Step 1: Write test for script and docs**

Verificar que el script de diagnóstico maneje argumentos `--test-insert`, compruebe si `pdo_pgsql` está activo y reporte con claridad si falta alguna tabla o permiso.

- [ ] **Step 2: Implement `scripts/verify-postgres-connection.php` and `docs/decommission-editor-deployment-guide.md`**

- [ ] **Step 3: Run all plugin tests**

Run:
```bash
php tests/inquiry-db-connection-test.php
php tests/inquiry-repositories-test.php
php tests/inquiry-mailjet-client-test.php
php tests/inquiry-services-test.php
php tests/inquiry-plugin-loader-test.php
php tests/inquiry-handler-wiring-test.php
```
Expected: ALL PASS (100% verde)

- [ ] **Step 4: Commit**

```bash
git add scripts/verify-postgres-connection.php docs/decommission-editor-deployment-guide.md tests/inquiry-verify-script-test.php
git commit -m "docs(deployment): add postgres verification script and editor decommissioning guide"
```
