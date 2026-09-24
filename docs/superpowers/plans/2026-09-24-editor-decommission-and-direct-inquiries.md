# Desmantelamiento del Editor y Persistencia Directa de Consultas - Plan de Implementación

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Reemplazar únicamente los webhooks de consultas de ofertas académicas y seminarios por persistencia directa en PostgreSQL y envío transaccional Mailjet dentro del plugin, manteniendo el Editor activo hasta validar ambos flujos en producción.

**Architecture:** Capa desacoplada en `flacso-uruguay-plugin`: conexión PDO (`FLACSO_DB`) usando las 5 constantes de producción existentes, repositorios para `offer_inquiries` y `seminar_inquiries`, cliente `FLACSO_Mailjet_Client` reutilizando la configuración centralizada de `FLACSO_Integrations_Settings` con fallback conservador, servicios de negocio ("guardar primero, enviar después"), y carga modular a través de `modules/consultas/init.php`.

**Tech Stack:** PHP 8+, PDO (`pdo_pgsql` en producción, SQLite en memoria para tests), Mailjet API v3.1 Send vía `wp_remote_post()`, WordPress AJAX / REST endpoints.

**Spec:** [`docs/superpowers/specs/2026-09-24-editor-decommission-and-direct-inquiries-design.md`](file:///home/ivan/repositorios/FLACSO%20Uruguay/Migrar/flacso-uruguay-plugin/docs/superpowers/specs/2026-09-24-editor-decommission-and-direct-inquiries-design.md)

## Global Constraints

- Utilizar las constantes existentes de producción en `wp-config.php`: `FLACSO_PG_HOST`, `FLACSO_PG_PORT`, `FLACSO_PG_DATABASE`, `FLACSO_PG_USER`, `FLACSO_PG_PASSWORD`.
- Exclusivamente para `offer_inquiries` y `seminar_inquiries`. El formulario de contacto general queda fuera de esta fase para minimizar riesgo.
- Guardar primero en PostgreSQL; nunca invocar a Mailjet si el `INSERT` falla.
- Si Mailjet falla o da timeout, la consulta permanece a salvo en la base y se marca `emailStatus = 'failed'`.
- En caso de error remoto de Mailjet con `TemplateID`, NO reintentar automáticamente con HTML (evita correos duplicados por timeout).
- Almacenar tanto `mailjetMessageId` como `mailjetMessageUuid`.
- Idempotencia: comprobar `find_by_consulta_id` antes del `INSERT`. Si ya existe, retornar `duplicate = true` sin validar campos obligatorios no relevantes para el duplicado ni volver a enviar correo. Capturar SQLSTATE `23505` ante carreras.
- Todos los tests unitarios deben correr con `php tests/<test>.php` y pasar en verde con SQLite en memoria.
- Incluir script de validación real contra PostgreSQL ejecutando `SELECT`, `INSERT`, `UPDATE`, `ROLLBACK` en transacción.
- Cargar las clases a través de `modules/consultas/init.php` invocado por el ciclo de módulos de `flacso-uruguay.php`.

---

### Task 1: Adaptador de Conexión PDO (`FLACSO_DB`)

**Files:**
- Create: `flacso-uruguay-plugin/includes/database/class-flacso-db.php`
- Test: `flacso-uruguay-plugin/tests/inquiry-db-connection-test.php`

**Interfaces:**
- Consumes: Constantes globales `FLACSO_PG_HOST`, `FLACSO_PG_PORT`, `FLACSO_PG_DATABASE`, `FLACSO_PG_USER`, `FLACSO_PG_PASSWORD`.
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
db_assert(FLACSO_DB::is_configured() === false, 'Sin constantes FLACSO_PG_* debe retornar false en is_configured');

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
Expected: FAIL with missing file or class.

- [ ] **Step 3: Write minimal implementation**

```php
<?php
/**
 * Conector de base de datos para FLACSO Uruguay (PostgreSQL vía PDO).
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
            throw new RuntimeException('PostgreSQL no está configurado. Defina FLACSO_PG_HOST, FLACSO_PG_PORT, FLACSO_PG_DATABASE, FLACSO_PG_USER y FLACSO_PG_PASSWORD.');
        }

        if (!extension_loaded('pdo_pgsql')) {
            throw new RuntimeException('La extensión PHP pdo_pgsql no está disponible en este servidor.');
        }

        $dsn = sprintf(
            'pgsql:host=%s;port=%s;dbname=%s',
            FLACSO_PG_HOST,
            FLACSO_PG_PORT,
            FLACSO_PG_DATABASE
        );

        self::$connection = new PDO(
            $dsn,
            FLACSO_PG_USER,
            FLACSO_PG_PASSWORD,
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

        return defined('FLACSO_PG_HOST') &&
               defined('FLACSO_PG_PORT') &&
               defined('FLACSO_PG_DATABASE') &&
               defined('FLACSO_PG_USER') &&
               defined('FLACSO_PG_PASSWORD') &&
               FLACSO_PG_HOST !== '' &&
               FLACSO_PG_DATABASE !== '';
    }
}
```

- [ ] **Step 4: Run test to verify it passes**

Run: `php tests/inquiry-db-connection-test.php`
Expected: `OK inquiry-db-connection-test`

- [ ] **Step 5: Commit**

```bash
git add includes/database/class-flacso-db.php tests/inquiry-db-connection-test.php
git commit -m "feat(database): add FLACSO_DB adapter supporting existing production PG constants"
```

---

### Task 2: Repositorios de Ofertas y Seminarios

**Files:**
- Create: `flacso-uruguay-plugin/includes/database/repositories/class-flacso-offer-inquiry-repository.php`
- Create: `flacso-uruguay-plugin/includes/database/repositories/class-flacso-seminar-inquiry-repository.php`
- Test: `flacso-uruguay-plugin/tests/inquiry-repositories-test.php`

**Interfaces:**
- Consumes: `FLACSO_DB::connection(): PDO`
- Produces:
  - `FLACSO_Offer_Inquiry_Repository::find_by_consulta_id(string $consulta_id): ?array`
  - `FLACSO_Offer_Inquiry_Repository::insert(array $data): array` (retorna `['id' => ..., 'consultaId' => ..., 'duplicate' => bool]`)
  - `FLACSO_Offer_Inquiry_Repository::update_email_status(string $consulta_id, string $status, ?string $sender = null, ?string $message_id = null, ?string $message_uuid = null): bool`
  - Métodos equivalentes para `FLACSO_Seminar_Inquiry_Repository`

- [ ] **Step 1: Write the failing test**

```php
<?php
// tests/inquiry-repositories-test.php
$root = dirname(__DIR__);
require_once $root . '/includes/database/class-flacso-db.php';
require_once $root . '/includes/database/repositories/class-flacso-offer-inquiry-repository.php';
require_once $root . '/includes/database/repositories/class-flacso-seminar-inquiry-repository.php';

function repo_assert(bool $cond, string $msg): void {
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
    mailjetMessageUuid TEXT,
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
    mailjetMessageUuid TEXT,
    payload TEXT,
    createdAt TEXT,
    updatedAt TEXT
);
');

FLACSO_DB::set_connection($pdo);

// 1. Probar inserción en offer_inquiries
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
    'payload'    => ['test' => true],
]);
repo_assert(!empty($res1['id']), 'Debe retornar ID generado con formato cuid');
repo_assert(strpos($res1['id'], 'c') === 0, 'El ID debe comenzar con c');
repo_assert($res1['duplicate'] === false, 'No debe ser duplicado');

// Idempotencia temprana: si consultaId existe, retorna duplicate de inmediato
// incluso si faltan campos obligatorios para un nuevo INSERT
$res1_dup = $offer_repo->insert([
    'consultaId' => 'cid-offer-001',
]);
repo_assert($res1_dup['duplicate'] === true, 'Debe detectar duplicado antes del INSERT');
repo_assert($res1_dup['id'] === $res1['id'], 'El ID debe ser idéntico al original');

// Actualizar emailStatus con messageId y messageUuid
$offer_repo->update_email_status('cid-offer-001', 'sent', 'remitente@flacso.edu.uy', 'mj-12345', 'uuid-abcde-6789');
$found_offer = $offer_repo->find_by_consulta_id('cid-offer-001');
repo_assert($found_offer['emailStatus'] === 'sent', 'emailStatus debe ser sent');
repo_assert($found_offer['mailjetMessageId'] === 'mj-12345', 'mailjetMessageId debe ser mj-12345');
repo_assert($found_offer['mailjetMessageUuid'] === 'uuid-abcde-6789', 'mailjetMessageUuid debe ser uuid-abcde-6789');

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
    'payload'      => ['origen' => 'seminario'],
]);
repo_assert(!empty($res2['id']), 'Debe retornar ID de seminario');
$seminar_repo->update_email_status('cid-sem-001', 'failed');
$found_sem = $seminar_repo->find_by_consulta_id('cid-sem-001');
repo_assert($found_sem['emailStatus'] === 'failed', 'emailStatus de seminario debe ser failed');

echo "OK inquiry-repositories-test\n";
```

- [ ] **Step 2: Run test to verify it fails**

Run: `php tests/inquiry-repositories-test.php`
Expected: FAIL

- [ ] **Step 3: Write minimal implementation**

Implementar `FLACSO_Offer_Inquiry_Repository` y `FLACSO_Seminar_Inquiry_Repository`:
- Helper `generate_cuid()`: `'c' . substr(bin2hex(random_bytes(12)), 0, 24)`.
- Chequeo de idempotencia al inicio: `find_by_consulta_id($consulta_id)` $\rightarrow$ si existe, retorna `['id' => ..., 'consultaId' => ..., 'duplicate' => true]`.
- Manejo de excepciones PDO: si al insertar ocurre un error de unicidad (SQLSTATE `23505`), recupera el registro existente y retorna `duplicate = true`.
- Actualización de `emailStatus`, `mailjetMessageId`, `mailjetMessageUuid`.

- [ ] **Step 4: Run test to verify it passes**

Run: `php tests/inquiry-repositories-test.php`
Expected: `OK inquiry-repositories-test`

- [ ] **Step 5: Commit**

```bash
git add includes/database/repositories/ tests/inquiry-repositories-test.php
git commit -m "feat(database): add offer and seminar inquiry repositories with cuid and idempotency"
```

---

### Task 3: Cliente Mailjet Transaccional (`FLACSO_Mailjet_Client`)

**Files:**
- Create: `flacso-uruguay-plugin/includes/integrations/class-flacso-mailjet-client.php`
- Test: `flacso-uruguay-plugin/tests/inquiry-mailjet-client-test.php`

**Interfaces:**
- Consumes: `FLACSO_Integrations_Settings::get_mailjet_settings()` y `wp_remote_post`.
- Produces:
  - `FLACSO_Mailjet_Client::send(array $params): array`
  - `FLACSO_Mailjet_Client::send_offer_inquiry(array $inquiry, array $program): array`
  - `FLACSO_Mailjet_Client::send_seminar_inquiry(array $inquiry, array $seminar): array`

- [ ] **Step 1: Write the failing test**

```php
<?php
// tests/inquiry-mailjet-client-test.php
$root = dirname(__DIR__);

if (!defined('ABSPATH')) {
    define('ABSPATH', __DIR__ . '/');
}

$GLOBALS['mailjet_mock_options'] = [
    'flacso_mailjet_api_key'                   => 'mock-api-key',
    'flacso_mailjet_secret_key'                => 'mock-secret-key',
    'flacso_mailjet_sender_email'              => 'notificaciones@flacso.edu.uy',
    'flacso_mailjet_sender_name'               => 'FLACSO Uruguay',
    'flacso_mailjet_template_consulta_abierta' => '12345',
];

if (!function_exists('get_option')) {
    function get_option($k, $d = false) {
        return $GLOBALS['mailjet_mock_options'][$k] ?? $d;
    }
}

$GLOBALS['mailjet_http_calls'] = [];
$GLOBALS['mailjet_mock_simulate_error'] = false;

if (!function_exists('wp_remote_post')) {
    function wp_remote_post($url, $args) {
        $GLOBALS['mailjet_http_calls'][] = ['url' => $url, 'args' => $args];
        if (!empty($GLOBALS['mailjet_mock_simulate_error'])) {
            return ['response' => ['code' => 500, 'message' => 'Internal Error'], 'body' => '{"ErrorMessage":"Error"}'];
        }
        $body = json_decode($args['body'], true);
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

if (!function_exists('is_wp_error')) { function is_wp_error($thing) { return false; } }
if (!function_exists('wp_remote_retrieve_response_code')) { function wp_remote_retrieve_response_code($res) { return $res['response']['code'] ?? 0; } }
if (!function_exists('wp_remote_retrieve_body')) { function wp_remote_retrieve_body($res) { return $res['body'] ?? ''; } }

require_once $root . '/includes/integrations/class-flacso-mailjet-client.php';

function mj_assert(bool $cond, string $msg): void {
    if (!$cond) {
        fwrite(STDERR, "FAIL: {$msg}\n");
        exit(1);
    }
}

// 1. Envío con TemplateID configurado
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
mj_assert($res_open['message_uuid'] === 'f7b8a8b1-1234-5678-90ab-cdef12345678', 'Debe capturar message_uuid');

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
$last_call = end($GLOBALS['mailjet_http_calls']);
$sent_payload = json_decode($last_call['args']['body'], true);
mj_assert(empty($sent_payload['Messages'][0]['TemplateID']), 'Sin TemplateID configurado no debe enviar TemplateID');
mj_assert(!empty($sent_payload['Messages'][0]['HTMLPart']), 'Debe incluir HTMLPart');

// 3. Regla conservadora: Si TemplateID falla en Mailjet, NO hacer segundo envío automático con HTML
$GLOBALS['mailjet_mock_simulate_error'] = true;
$call_count_before = count($GLOBALS['mailjet_http_calls']);
$res_failed = FLACSO_Mailjet_Client::send_offer_inquiry(
    [
        'consultaId' => 'cid-mj-003',
        'email'      => 'error@ejemplo.com',
        'firstName'  => 'Lucía',
    ],
    [
        'id'                     => 55,
        'name'                   => 'Diploma',
        'isInscripcionesAbiertas'=> true,
    ]
);
mj_assert($res_failed['ok'] === false, 'Debe fallar');
mj_assert($res_failed['status'] === 'failed', 'Status debe ser failed');
mj_assert(count($GLOBALS['mailjet_http_calls']) === $call_count_before + 1, 'No debe intentar un segundo envío con HTML tras error remoto');

echo "OK inquiry-mailjet-client-test\n";
```

- [ ] **Step 2: Run test to verify it fails**

Run: `php tests/inquiry-mailjet-client-test.php`
Expected: FAIL

- [ ] **Step 3: Write minimal implementation**

Implementar `FLACSO_Mailjet_Client`:
- Reutilizar `FLACSO_Integrations_Settings::get_mailjet_settings()` si la clase existe, con fallback a `get_option`.
- Cabecera Basic Auth (`base64_encode(api_key:secret_key)`).
- `send_offer_inquiry()` y `send_seminar_inquiry()`.
- Si `template_id` está vacío $\rightarrow$ compilar HTMLPart institucional.
- Si `template_id` existe $\rightarrow$ enviar `TemplateID` + `TemplateLanguage = true`.
- Si Mailjet responde error $\rightarrow$ retornar `status = 'failed'` (no hacer retry con HTML).
- Retornar `['ok' => bool, 'status' => 'sent'|'failed'|'skipped', 'sender' => ..., 'message_id' => ..., 'message_uuid' => ..., 'error' => ...]`.

- [ ] **Step 4: Run test to verify it passes**

Run: `php tests/inquiry-mailjet-client-test.php`
Expected: `OK inquiry-mailjet-client-test`

- [ ] **Step 5: Commit**

```bash
git add includes/integrations/class-flacso-mailjet-client.php tests/inquiry-mailjet-client-test.php
git commit -m "feat(mailjet): add FLACSO_Mailjet_Client reusing existing settings with conservative fallback"
```

---

### Task 4: Servicios de Consultas de Ofertas y Seminarios

**Files:**
- Create: `flacso-uruguay-plugin/modules/consultas/services/class-flacso-offer-inquiry-service.php`
- Create: `flacso-uruguay-plugin/modules/consultas/services/class-flacso-seminar-inquiry-service.php`
- Test: `flacso-uruguay-plugin/tests/inquiry-services-test.php`

**Interfaces:**
- Consumes: `FLACSO_Offer_Inquiry_Repository`, `FLACSO_Seminar_Inquiry_Repository`, `FLACSO_Mailjet_Client`.
- Produces:
  - `FLACSO_Offer_Inquiry_Service::submit(array $data): array`
  - `FLACSO_Seminar_Inquiry_Service::submit(array $data): array`

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
require_once $root . '/includes/integrations/class-flacso-mailjet-client.php';
require_once $root . '/modules/consultas/services/class-flacso-offer-inquiry-service.php';
require_once $root . '/modules/consultas/services/class-flacso-seminar-inquiry-service.php';

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
$pdo->exec('
CREATE TABLE offer_inquiries (
    id TEXT PRIMARY KEY, consultaId TEXT UNIQUE, offerWpId INTEGER, offerName TEXT, offerType TEXT,
    firstName TEXT, lastName TEXT, fullName TEXT, email TEXT, emailNormalized TEXT, country TEXT,
    profession TEXT, educationLevel TEXT, source TEXT, campaignProvider TEXT, campaignSource TEXT,
    campaignMedium TEXT, campaignName TEXT, campaignExternalId TEXT, campaignContent TEXT, campaignTerm TEXT,
    urlBase TEXT, urlReferer TEXT, inquiryAt TEXT, ipAddress TEXT, userAgent TEXT, replyToEmail TEXT,
    programUrl TEXT, cartaUrl TEXT, preinscripcionUrl TEXT, offerStatus TEXT, emailStatus TEXT,
    emailSender TEXT, gmailMessageUrl TEXT, mailjetMessageId TEXT, mailjetMessageUuid TEXT, payload TEXT, createdAt TEXT, updatedAt TEXT
);
CREATE TABLE seminar_inquiries (
    id TEXT PRIMARY KEY, consultaId TEXT UNIQUE, seminarWpId INTEGER, seminarName TEXT, seminarType TEXT,
    firstName TEXT, lastName TEXT, fullName TEXT, email TEXT, emailNormalized TEXT, country TEXT,
    profession TEXT, educationLevel TEXT, source TEXT, campaignProvider TEXT, campaignSource TEXT,
    campaignMedium TEXT, campaignName TEXT, campaignExternalId TEXT, campaignContent TEXT, campaignTerm TEXT,
    urlBase TEXT, urlReferer TEXT, inquiryAt TEXT, ipAddress TEXT, userAgent TEXT, replyToEmail TEXT,
    programUrl TEXT, cartaUrl TEXT, preinscripcionUrl TEXT, offerStatus TEXT, emailStatus TEXT,
    emailSender TEXT, gmailMessageUrl TEXT, mailjetMessageId TEXT, mailjetMessageUuid TEXT, payload TEXT, createdAt TEXT, updatedAt TEXT
);
');
FLACSO_DB::set_connection($pdo);

// 1. Submit de oferta exitoso (guardar primero, enviar después)
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
srv_assert($result_offer['consulta_id'] === 'srv-offer-001', 'Debe retornar consulta_id');

$repo = new FLACSO_Offer_Inquiry_Repository();
$saved = $repo->find_by_consulta_id('srv-offer-001');
srv_assert(!empty($saved), 'La fila debe existir en offer_inquiries');

// 2. Idempotencia: reenvío con mismo event_id retorna duplicate
$result_dup = FLACSO_Offer_Inquiry_Service::submit([
    'event_id'        => 'srv-offer-001',
    'id_pagina'       => 10,
    'nombre'          => 'Lucía',
    'correo'          => 'lucia@ejemplo.com',
]);
srv_assert($result_dup['ok'] === true, 'El duplicado debe retornar ok');
srv_assert($result_dup['duplicate'] === true, 'Debe marcar duplicate = true');

// 3. Submit de seminario
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

$sem_repo = new FLACSO_Seminar_Inquiry_Repository();
$saved_sem = $sem_repo->find_by_consulta_id('srv-sem-001');
srv_assert(!empty($saved_sem), 'La fila debe existir en seminar_inquiries');

echo "OK inquiry-services-test\n";
```

- [ ] **Step 2: Run test to verify it fails**

Run: `php tests/inquiry-services-test.php`
Expected: FAIL

- [ ] **Step 3: Write minimal implementation**

Implementar `FLACSO_Offer_Inquiry_Service` y `FLACSO_Seminar_Inquiry_Service`:
- Validar campos requeridos.
- Verificar idempotencia con el repositorio antes de continuar.
- Insertar en base de datos con `emailStatus = 'skipped'`.
- Si el `INSERT` falla $\rightarrow$ retornar error y **no llamar a Mailjet**.
- Si el `INSERT` es exitoso $\rightarrow$ invocar `FLACSO_Mailjet_Client`.
- Actualizar `emailStatus`, `mailjetMessageId` y `mailjetMessageUuid`.
- Retornar `{ ok: true, consulta_id: ..., email: ..., duplicate: bool }`.

- [ ] **Step 4: Run test to verify it passes**

Run: `php tests/inquiry-services-test.php`
Expected: `OK inquiry-services-test`

- [ ] **Step 5: Commit**

```bash
git add modules/consultas/services/ tests/inquiry-services-test.php
git commit -m "feat(consultas): add offer and seminar inquiry services"
```

---

### Task 5: Carga del Módulo (`modules/consultas/init.php` y `flacso-uruguay.php`)

**Files:**
- Create: `flacso-uruguay-plugin/modules/consultas/init.php`
- Modify: `flacso-uruguay-plugin/flacso-uruguay.php`
- Test: `flacso-uruguay-plugin/tests/inquiry-plugin-loader-test.php`

**Interfaces:**
- Consumes: Arquitectura de módulos de FLACSO Uruguay.
- Produces: `modules/consultas/init.php` cargado dentro del ciclo de inicio del plugin.

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

$init_file = $root . '/modules/consultas/init.php';
loader_assert(file_exists($init_file), 'modules/consultas/init.php debe existir');

$init_code = (string) file_get_contents($init_file);
loader_assert(strpos($init_code, 'class-flacso-db.php') !== false, 'init.php debe cargar class-flacso-db.php');
loader_assert(strpos($init_code, 'class-flacso-offer-inquiry-repository.php') !== false, 'init.php debe cargar repositorios');
loader_assert(strpos($init_code, 'class-flacso-mailjet-client.php') !== false, 'init.php debe cargar class-flacso-mailjet-client.php');
loader_assert(strpos($init_code, 'class-flacso-offer-inquiry-service.php') !== false, 'init.php debe cargar servicios');

$plugin_code = (string) file_get_contents($root . '/flacso-uruguay.php');
loader_assert(strpos($plugin_code, "'consultas'") !== false || strpos($plugin_code, 'modules/consultas/init.php') !== false, 'flacso-uruguay.php debe cargar el modulo consultas');

echo "OK inquiry-plugin-loader-test\n";
```

- [ ] **Step 2: Run test to verify it fails**

Run: `php tests/inquiry-plugin-loader-test.php`
Expected: FAIL

- [ ] **Step 3: Write `modules/consultas/init.php` and register in `flacso-uruguay.php`**

Crear `init.php` que cargue en orden las dependencias. Registrar `'consultas'` en la lista de módulos de `flacso-uruguay.php`.

- [ ] **Step 4: Run test to verify it passes**

Run: `php tests/inquiry-plugin-loader-test.php`
Expected: `OK inquiry-plugin-loader-test`

- [ ] **Step 5: Commit**

```bash
git add modules/consultas/init.php flacso-uruguay.php tests/inquiry-plugin-loader-test.php
git commit -m "feat(core): load consultas module and dependencies via modules/consultas/init.php"
```

---

### Task 6: Sustitución de Webhooks Externos en Handlers de Ofertas y Seminarios

**Files:**
- Modify: `flacso-uruguay-plugin/modules/main-page/includes/flacso-consultas.php`
- Modify: `flacso-uruguay-plugin/modules/oferta-academica/includes/class-academic-api.php`
- Test: `flacso-uruguay-plugin/tests/inquiry-handler-wiring-test.php`

**Interfaces:**
- Consumes: `FLACSO_Offer_Inquiry_Service::submit()`, `FLACSO_Seminar_Inquiry_Service::submit()`.
- Produces: Eliminación del puente HTTP hacia el Editor en ambos handlers.

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

wiring_assert(strpos($ofertas_code, 'FLACSO_Offer_Inquiry_Service::submit') !== false, 'flacso-consultas.php debe delegar en FLACSO_Offer_Inquiry_Service::submit');
wiring_assert(strpos($seminarios_code, 'FLACSO_Seminar_Inquiry_Service::submit') !== false, 'class-academic-api.php debe delegar en FLACSO_Seminar_Inquiry_Service::submit');

echo "OK inquiry-handler-wiring-test\n";
```

- [ ] **Step 2: Run test to verify it fails**

Run: `php tests/inquiry-handler-wiring-test.php`
Expected: FAIL

- [ ] **Step 3: Modify handlers**

En `flacso-consultas.php`, `flacso_consultas_dispatch_single_info_request()` delega directamente en `FLACSO_Offer_Inquiry_Service::submit($data)`.
En `class-academic-api.php`, `submit_consulta_seminario()` delega directamente en `FLACSO_Seminar_Inquiry_Service::submit($payload)`.

- [ ] **Step 4: Run test to verify it passes**

Run: `php tests/inquiry-handler-wiring-test.php`
Expected: `OK inquiry-handler-wiring-test`

- [ ] **Step 5: Commit**

```bash
git add modules/main-page/includes/flacso-consultas.php modules/oferta-academica/includes/class-academic-api.php tests/inquiry-handler-wiring-test.php
git commit -m "feat(handlers): wire offer and seminar handlers directly to internal services"
```

---

### Task 7: Script de Diagnóstico PostgreSQL Real y Guía de Despliegue con Observación

**Files:**
- Create: `flacso-uruguay-plugin/scripts/verify-postgres-connection.php`
- Create: `flacso-uruguay-plugin/docs/decommission-editor-deployment-guide.md`
- Test: `flacso-uruguay-plugin/tests/inquiry-verify-script-test.php`

**Interfaces:**
- Consumes: `FLACSO_DB` y tablas PostgreSQL en producción.
- Produces:
  - Script CLI que ejecuta `SELECT`, `INSERT dentro de transacción`, `UPDATE` y `ROLLBACK`.
  - Guía con el procedimiento ordenado de despliegue y ventana de observación.

- [ ] **Step 1: Write test for script and docs**

```php
<?php
// tests/inquiry-verify-script-test.php
$root = dirname(__DIR__);

function diag_assert(bool $cond, string $msg): void {
    if (!$cond) {
        fwrite(STDERR, "FAIL: {$msg}\n");
        exit(1);
    }
}

$script_file = $root . '/scripts/verify-postgres-connection.php';
$guide_file = $root . '/docs/decommission-editor-deployment-guide.md';

diag_assert(file_exists($script_file), 'verify-postgres-connection.php debe existir');
diag_assert(file_exists($guide_file), 'decommission-editor-deployment-guide.md debe existir');

$script_code = (string) file_get_contents($script_file);
diag_assert(strpos($script_code, 'ROLLBACK') !== false, 'El script debe usar ROLLBACK para pruebas no destructivas');
diag_assert(strpos($script_code, 'offer_inquiries') !== false, 'El script debe validar offer_inquiries');
diag_assert(strpos($script_code, 'seminar_inquiries') !== false, 'El script debe validar seminar_inquiries');

echo "OK inquiry-verify-script-test\n";
```

- [ ] **Step 2: Run test to verify it fails**

Run: `php tests/inquiry-verify-script-test.php`
Expected: FAIL

- [ ] **Step 3: Implement `verify-postgres-connection.php` and `decommission-editor-deployment-guide.md`**

El script CLI comprueba:
1. `pdo_pgsql` cargado.
2. Constantes `FLACSO_PG_*` presentes.
3. Conexión exitosa.
4. Conteo de filas en `offer_inquiries` y `seminar_inquiries`.
5. Transacción `BEGIN`: insert de prueba $\rightarrow$ update de prueba $\rightarrow$ select de verificación $\rightarrow$ `ROLLBACK`.
6. Salida clara con `OK` o detalle del fallo.

La guía documenta el despliegue con ventana de observación antes de apagar el Editor.

- [ ] **Step 4: Run test to verify it passes**

Run: `php tests/inquiry-verify-script-test.php`
Expected: `OK inquiry-verify-script-test`

- [ ] **Step 5: Run all test suite**

Run:
```bash
php tests/inquiry-db-connection-test.php
php tests/inquiry-repositories-test.php
php tests/inquiry-mailjet-client-test.php
php tests/inquiry-services-test.php
php tests/inquiry-plugin-loader-test.php
php tests/inquiry-handler-wiring-test.php
php tests/inquiry-verify-script-test.php
```
Expected: ALL PASS (100% verde)

- [ ] **Step 6: Commit**

```bash
git add scripts/verify-postgres-connection.php docs/decommission-editor-deployment-guide.md tests/inquiry-verify-script-test.php
git commit -m "docs(deployment): add postgres verification script and phased rollout guide"
```
