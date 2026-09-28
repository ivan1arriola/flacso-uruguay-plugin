<?php
/**
 * Test suite for FLACSO_Inquiry_Marketing_Service.
 *
 * Verifies:
 * 1. Tag generation rules (open, closed, sin_cohorte, missing abbreviation, normalization).
 * 2. sync_inquiry workflows:
 *    - Successful sync -> updates repository with 'synced' and contact_id.
 *    - Skipped sync (Mautic unconfigured/disabled) -> updates repository with 'skipped'.
 *    - Failed sync (API error) -> updates repository with 'failed' and error message.
 *    - Auto-load inquiry data by ID from repository when empty.
 *    - Validation of required email and non-existent inquiries.
 *    - Zero volatile fields sent to Mautic (no ultima_oferta, no estado_actual).
 */

$root = dirname(__DIR__);

if (!defined('ABSPATH')) {
    define('ABSPATH', __DIR__ . '/');
}

function test_assert(bool $cond, string $msg): void {
    if (!$cond) {
        fwrite(STDERR, "FAIL: {$msg}\n");
        exit(1);
    }
}

// --------------------------------------------------------------------------
// Mocks for WordPress functions & HTTP layer
// --------------------------------------------------------------------------

$GLOBALS['mock_options'] = [];

if (!function_exists('get_option')) {
    function get_option($key, $default = false) {
        return $GLOBALS['mock_options'][$key] ?? $default;
    }
}

if (!function_exists('update_option')) {
    function update_option($key, $value) {
        $GLOBALS['mock_options'][$key] = $value;
        return true;
    }
}

if (!function_exists('sanitize_title')) {
    function sanitize_title($str) {
        $str = strtr((string)$str, [
            'á'=>'a', 'é'=>'e', 'í'=>'i', 'ó'=>'o', 'ú'=>'u',
            'Á'=>'a', 'É'=>'e', 'Í'=>'i', 'Ó'=>'o', 'Ú'=>'u',
            'ñ'=>'n', 'Ñ'=>'n', 'ü'=>'u', 'Ü'=>'u',
        ]);
        return strtolower(trim(preg_replace('/[^a-zA-Z0-9_\-]+/', '-', $str), '-'));
    }
}

if (!class_exists('WP_Error')) {
    class WP_Error {
        public function __construct(private string $message, private string $code = '') {}
        public function get_error_message(): string { return $this->message; }
        public function get_error_code(): string { return $this->code; }
    }
}

if (!function_exists('is_wp_error')) {
    function is_wp_error($thing) {
        return $thing instanceof WP_Error;
    }
}

$GLOBALS['http_calls'] = [];
$GLOBALS['http_handler'] = null;

if (!function_exists('wp_remote_request')) {
    function wp_remote_request($url, $args = []) {
        $GLOBALS['http_calls'][] = ['url' => $url, 'args' => $args];
        if (is_callable($GLOBALS['http_handler'])) {
            return ($GLOBALS['http_handler'])($url, $args);
        }
        return [
            'response' => ['code' => 200, 'message' => 'OK'],
            'body'     => json_encode(['success' => true]),
        ];
    }
}

if (!function_exists('wp_remote_get')) {
    function wp_remote_get($url, $args = []) {
        $args['method'] = 'GET';
        return wp_remote_request($url, $args);
    }
}

if (!function_exists('wp_remote_post')) {
    function wp_remote_post($url, $args = []) {
        $args['method'] = 'POST';
        return wp_remote_request($url, $args);
    }
}

if (!function_exists('wp_remote_retrieve_response_code')) {
    function wp_remote_retrieve_response_code($res) {
        return is_array($res) ? ($res['response']['code'] ?? 0) : 0;
    }
}

if (!function_exists('wp_remote_retrieve_body')) {
    function wp_remote_retrieve_body($res) {
        return is_array($res) ? ($res['body'] ?? '') : '';
    }
}

if (!function_exists('wp_json_encode')) {
    function wp_json_encode($data) {
        return json_encode($data);
    }
}

// --------------------------------------------------------------------------
// Database and Repository Setup (SQLite in memory)
// --------------------------------------------------------------------------

require_once $root . '/includes/database/class-flacso-db.php';
require_once $root . '/includes/database/repositories/class-flacso-base-inquiry-repository.php';
require_once $root . '/includes/database/repositories/class-flacso-offer-inquiry-repository.php';
require_once $root . '/includes/integrations/class-flacso-mautic-client.php';
require_once $root . '/modules/consultas/services/class-flacso-inquiry-marketing-service.php';

$pdo = new PDO('sqlite::memory:', null, null, [
    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
]);

$pdo->exec('
CREATE TABLE offer_inquiries (
    id TEXT PRIMARY KEY,
    consultaId TEXT UNIQUE,
    offerWpId INTEGER,
    offerName TEXT,
    offerAbbreviation TEXT,
    offerType TEXT,
    cohortWpId INTEGER,
    cohortNumber INTEGER,
    cohortName TEXT,
    registrationOpenAt TEXT,
    registrationCloseAt TEXT,
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
    offerStatus TEXT DEFAULT "sin_cohorte",
    mauticContactId TEXT,
    mauticSyncStatus TEXT DEFAULT "skipped",
    mauticSyncedAt TEXT,
    mauticLastError TEXT,
    followupDueAt TEXT,
    followupStatus TEXT DEFAULT "none",
    followupSentAt TEXT,
    followupAttempts INTEGER DEFAULT 0,
    followupLastError TEXT,
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
FLACSO_Base_Inquiry_Repository::clear_cache();
$repo = new FLACSO_Offer_Inquiry_Repository();

// Helper to configure Mautic options
function configure_mautic(bool $enabled = true): void {
    update_option('flacso_mautic_enabled', $enabled ? '1' : '0');
    update_option('flacso_mautic_base_url', 'https://envios.flacso.edu.uy');
    update_option('flacso_mautic_auth_type', 'basic');
    update_option('flacso_mautic_username', 'api_user');
    update_option('flacso_mautic_password', 'api_secret');
}

echo "=== Running FLACSO_Inquiry_Marketing_Service tests ===\n";

// ==========================================================================
// TEST SUITE 1: generate_tags
// ==========================================================================
echo "\n--- Suite 1: generate_tags ---\n";

// 1.1 Empty / missing abbreviation returns empty array
test_assert(
    FLACSO_Inquiry_Marketing_Service::generate_tags(null, 10, 'abierta') === [],
    'generate_tags(null) must return []'
);
test_assert(
    FLACSO_Inquiry_Marketing_Service::generate_tags('', 10, 'abierta') === [],
    'generate_tags("") must return []'
);
test_assert(
    FLACSO_Inquiry_Marketing_Service::generate_tags('   ', 10, 'abierta') === [],
    'generate_tags("   ") must return []'
);
test_assert(
    FLACSO_Inquiry_Marketing_Service::generate_tags('---', 10, 'abierta') === [],
    'generate_tags("---") must return []'
);

// 1.2 Open cohort tags
$open_tags = FLACSO_Inquiry_Marketing_Service::generate_tags('DAVIA', 10, 'abierta');
test_assert(
    $open_tags === ['interes-davia', 'interes-davia-c10', 'consulta-abierta-davia-c10'],
    'generate_tags open cohort: interes-davia, interes-davia-c10, consulta-abierta-davia-c10'
);

// 1.3 Closed cohort tags
$closed_tags = FLACSO_Inquiry_Marketing_Service::generate_tags('DAVIA', 10, 'cerrada');
test_assert(
    $closed_tags === ['interes-davia', 'interes-davia-c10', 'consulta-cerrada-davia-c10'],
    'generate_tags closed cohort: interes-davia, interes-davia-c10, consulta-cerrada-davia-c10'
);

// 1.4 sin_cohorte status: only base tag even if cohort_number is passed
$sin_cohorte_tags_1 = FLACSO_Inquiry_Marketing_Service::generate_tags('DAVIA', 10, 'sin_cohorte');
test_assert(
    $sin_cohorte_tags_1 === ['interes-davia'],
    'generate_tags sin_cohorte with cohort_number > 0 returns only base tag'
);

$sin_cohorte_tags_2 = FLACSO_Inquiry_Marketing_Service::generate_tags('DAVIA', null, 'sin_cohorte');
test_assert(
    $sin_cohorte_tags_2 === ['interes-davia'],
    'generate_tags sin_cohorte with null cohort_number returns only base tag'
);

// 1.5 Cohort number <= 0 or null with abierta / cerrada returns only base tag
test_assert(
    FLACSO_Inquiry_Marketing_Service::generate_tags('DAVIA', 0, 'abierta') === ['interes-davia'],
    'generate_tags with cohort_number = 0 returns only base tag'
);
test_assert(
    FLACSO_Inquiry_Marketing_Service::generate_tags('DAVIA', -1, 'cerrada') === ['interes-davia'],
    'generate_tags with cohort_number = -1 returns only base tag'
);
test_assert(
    FLACSO_Inquiry_Marketing_Service::generate_tags('DAVIA', null, 'abierta') === ['interes-davia'],
    'generate_tags with cohort_number = null returns only base tag'
);

// 1.6 Normalization: spaces, mixed case, punctuation, accents
$norm_tags_1 = FLACSO_Inquiry_Marketing_Service::generate_tags('  Mg. Ed  ', 2, 'abierta');
test_assert(
    $norm_tags_1 === ['interes-mg-ed', 'interes-mg-ed-c2', 'consulta-abierta-mg-ed-c2'],
    'generate_tags normalizes "  Mg. Ed  " to "mg-ed"'
);

$norm_tags_2 = FLACSO_Inquiry_Marketing_Service::generate_tags('Diplomatura Género', 3, 'cerrada');
test_assert(
    $norm_tags_2 === ['interes-diplomatura-genero', 'interes-diplomatura-genero-c3', 'consulta-cerrada-diplomatura-genero-c3'],
    'generate_tags normalizes accents "Diplomatura Género" to "diplomatura-genero"'
);

echo "Suite 1: generate_tags passed.\n";

// ==========================================================================
// TEST SUITE 2: sync_inquiry workflows
// ==========================================================================
echo "\n--- Suite 2: sync_inquiry workflows ---\n";

// 2.1 Mautic disabled / unconfigured -> returns skipped and updates DB
configure_mautic(false);
$insert_unconf = $repo->insert([
    'consultaId'        => 'cid-unconf-01',
    'offerAbbreviation' => 'davia',
    'email'             => 'unconf@example.com',
    'mauticSyncStatus'  => 'pending',
]);
$id_unconf = $insert_unconf['id'];

$res_unconf = FLACSO_Inquiry_Marketing_Service::sync_inquiry($id_unconf, [
    'email' => 'unconf@example.com',
]);
test_assert($res_unconf['ok'] === true, 'sync_inquiry when unconfigured returns ok = true');
test_assert($res_unconf['status'] === 'skipped', 'sync_inquiry when unconfigured returns status = skipped');
test_assert(!empty($res_unconf['message']), 'sync_inquiry when unconfigured returns message');

$record_unconf = $repo->find_by_id($id_unconf);
test_assert($record_unconf !== null, 'Record exists in DB');
test_assert($record_unconf['mauticSyncStatus'] === 'skipped', 'Record in DB has mauticSyncStatus = skipped');

// 2.2 Inquiry not found when empty inquiry_data is provided
configure_mautic(true);
$res_not_found = FLACSO_Inquiry_Marketing_Service::sync_inquiry('non-existent-id-999', []);
test_assert($res_not_found['ok'] === false, 'sync_inquiry with non-existent id returns ok = false');
test_assert($res_not_found['status'] === 'failed', 'sync_inquiry with non-existent id returns status = failed');
test_assert($res_not_found['error'] === 'Inquiry not found', 'sync_inquiry with non-existent id returns "Inquiry not found"');

// 2.3 Email is required
$insert_no_email = $repo->insert([
    'consultaId'        => 'cid-no-email-01',
    'offerAbbreviation' => 'davia',
    'email'             => '',
]);
$id_no_email = $insert_no_email['id'];

$res_no_email = FLACSO_Inquiry_Marketing_Service::sync_inquiry($id_no_email, [
    'email' => '',
    'firstName' => 'Carlos',
]);
test_assert($res_no_email['ok'] === false, 'sync_inquiry with empty email returns ok = false');
test_assert($res_no_email['status'] === 'failed', 'sync_inquiry with empty email returns status = failed');
test_assert($res_no_email['error'] === 'Email is required', 'sync_inquiry with empty email returns "Email is required"');

// 2.4 Successful sync with inquiry_data provided directly
$last_mautic_payload = null;
$GLOBALS['http_calls'] = [];
$GLOBALS['http_handler'] = function($url, $args) use (&$last_mautic_payload) {
    if (strpos($url, '/api/contacts?search=') !== false) {
        // Contact not found
        return [
            'response' => ['code' => 200, 'message' => 'OK'],
            'body'     => json_encode(['total' => 0, 'contacts' => []]),
        ];
    }
    if (strpos($url, '/api/contacts/new') !== false) {
        $last_mautic_payload = json_decode($args['body'], true);
        return [
            'response' => ['code' => 201, 'message' => 'Created'],
            'body'     => json_encode([
                'contact' => [
                    'id'     => 12345,
                    'fields' => ['all' => $last_mautic_payload],
                ],
            ]),
        ];
    }
    return ['response' => ['code' => 404, 'message' => 'Not Found'], 'body' => ''];
};

$insert_sync = $repo->insert([
    'consultaId'        => 'cid-sync-01',
    'offerAbbreviation' => 'davia',
    'cohortNumber'      => 10,
    'offerStatus'       => 'abierta',
    'email'             => 'lucia@example.com',
    'firstName'         => 'Lucía',
    'lastName'          => 'Pérez',
    'country'           => 'Uruguay',
    'phone'             => '099111222',
    'profession'        => 'Socióloga',
    'educationLevel'    => 'Universitario',
]);
$id_sync = $insert_sync['id'];

$res_sync = FLACSO_Inquiry_Marketing_Service::sync_inquiry($id_sync, [
    'email'             => 'lucia@example.com',
    'firstName'         => 'Lucía',
    'lastName'          => 'Pérez',
    'country'           => 'Uruguay',
    'phone'             => '099111222',
    'profession'        => 'Socióloga',
    'educationLevel'    => 'Universitario',
    'offerAbbreviation' => 'DAVIA',
    'cohortNumber'      => 10,
    'offerStatus'       => 'abierta',
]);

test_assert($res_sync['ok'] === true, 'sync_inquiry returns ok = true on success');
test_assert($res_sync['status'] === 'synced', 'sync_inquiry returns status = synced');
test_assert($res_sync['contact_id'] === 12345, 'sync_inquiry returns contact_id = 12345');
test_assert(
    $res_sync['tags'] === ['interes-davia', 'interes-davia-c10', 'consulta-abierta-davia-c10'],
    'sync_inquiry returns correct canonical tags'
);

// Verify DB persistence
$record_sync = $repo->find_by_id($id_sync);
test_assert($record_sync !== null, 'Record exists in DB');
test_assert($record_sync['mauticSyncStatus'] === 'synced', 'DB mauticSyncStatus is synced');
test_assert((int)$record_sync['mauticContactId'] === 12345, 'DB mauticContactId is 12345');
test_assert(!empty($record_sync['mauticSyncedAt']), 'DB mauticSyncedAt is populated');
test_assert($record_sync['mauticLastError'] === null, 'DB mauticLastError is null');

// Verify zero volatile fields in Mautic payload
test_assert(is_array($last_mautic_payload), 'Payload was sent to Mautic');
test_assert($last_mautic_payload['email'] === 'lucia@example.com', 'Payload email matches');
test_assert($last_mautic_payload['firstname'] === 'Lucía', 'Payload firstname matches');
test_assert($last_mautic_payload['lastname'] === 'Pérez', 'Payload lastname matches');
test_assert($last_mautic_payload['country'] === 'Uruguay', 'Payload country matches');
test_assert($last_mautic_payload['phone'] === '099111222', 'Payload phone matches');
test_assert($last_mautic_payload['profession'] === 'Socióloga', 'Payload profession matches');
test_assert($last_mautic_payload['education_level'] === 'Universitario', 'Payload education_level matches');
test_assert(!isset($last_mautic_payload['ultima_oferta']), 'Zero volatile fields: no ultima_oferta');
test_assert(!isset($last_mautic_payload['estado_actual']), 'Zero volatile fields: no estado_actual');
test_assert(!isset($last_mautic_payload['offerName']), 'Zero volatile fields: no offerName');
test_assert(!isset($last_mautic_payload['cohortName']), 'Zero volatile fields: no cohortName');

// 2.5 Auto-load inquiry data from DB when empty array is provided
$insert_autoload = $repo->insert([
    'consultaId'        => 'cid-autoload-01',
    'offerAbbreviation' => 'mg-ed',
    'cohortNumber'      => 4,
    'offerStatus'       => 'cerrada',
    'email'             => 'mario@example.com',
    'firstName'         => 'Mario',
    'lastName'          => 'Rossi',
    'country'           => 'Argentina',
    'phone'             => '123456',
    'profession'        => 'Profesor',
    'educationLevel'    => 'Maestría',
]);
$id_autoload = $insert_autoload['id'];

$autoload_payload = null;
$GLOBALS['http_handler'] = function($url, $args) use (&$autoload_payload) {
    if (strpos($url, '/api/contacts?search=') !== false) {
        return [
            'response' => ['code' => 200, 'message' => 'OK'],
            'body'     => json_encode(['total' => 0, 'contacts' => []]),
        ];
    }
    if (strpos($url, '/api/contacts/new') !== false) {
        $autoload_payload = json_decode($args['body'], true);
        return [
            'response' => ['code' => 201, 'message' => 'Created'],
            'body'     => json_encode([
                'contact' => [
                    'id'     => 67890,
                    'fields' => ['all' => $autoload_payload],
                ],
            ]),
        ];
    }
    return ['response' => ['code' => 404, 'message' => 'Not Found'], 'body' => ''];
};

$res_autoload = FLACSO_Inquiry_Marketing_Service::sync_inquiry($id_autoload, []);
test_assert($res_autoload['ok'] === true, 'sync_inquiry with auto-load returns ok = true');
test_assert($res_autoload['status'] === 'synced', 'sync_inquiry with auto-load returns status = synced');
test_assert($res_autoload['contact_id'] === 67890, 'sync_inquiry with auto-load returns contact_id = 67890');
test_assert(
    $res_autoload['tags'] === ['interes-mg-ed', 'interes-mg-ed-c4', 'consulta-cerrada-mg-ed-c4'],
    'sync_inquiry with auto-load generates correct closed tags from DB'
);

$record_autoload = $repo->find_by_id($id_autoload);
test_assert($record_autoload['mauticSyncStatus'] === 'synced', 'DB record auto-load mauticSyncStatus is synced');
test_assert((int)$record_autoload['mauticContactId'] === 67890, 'DB record auto-load mauticContactId is 67890');
test_assert(is_array($autoload_payload), 'Payload was sent from auto-loaded DB data');
test_assert($autoload_payload['email'] === 'mario@example.com', 'Auto-loaded email matches');
test_assert($autoload_payload['firstname'] === 'Mario', 'Auto-loaded firstname matches');
test_assert($autoload_payload['lastname'] === 'Rossi', 'Auto-loaded lastname matches');
test_assert($autoload_payload['profession'] === 'Profesor', 'Auto-loaded profession matches');
test_assert($autoload_payload['education_level'] === 'Maestría', 'Auto-loaded education_level matches');

// 2.6 Failed sync when Mautic API returns error
$insert_fail = $repo->insert([
    'consultaId'        => 'cid-fail-01',
    'offerAbbreviation' => 'davia',
    'email'             => 'error@example.com',
]);
$id_fail = $insert_fail['id'];

$GLOBALS['http_handler'] = function($url, $args) {
    if (strpos($url, '/api/contacts?search=') !== false) {
        return [
            'response' => ['code' => 500, 'message' => 'Internal Server Error'],
            'body'     => json_encode(['errors' => [['message' => 'Database connection dropped in Mautic']]]),
        ];
    }
    return ['response' => ['code' => 500, 'message' => 'Internal Server Error'], 'body' => ''];
};

$res_fail = FLACSO_Inquiry_Marketing_Service::sync_inquiry($id_fail, [
    'email'             => 'error@example.com',
    'offerAbbreviation' => 'davia',
]);

test_assert($res_fail['ok'] === false, 'sync_inquiry returns ok = false on API error');
test_assert($res_fail['status'] === 'failed', 'sync_inquiry returns status = failed');
test_assert(!empty($res_fail['error']), 'sync_inquiry returns error message');

$record_fail = $repo->find_by_id($id_fail);
test_assert($record_fail['mauticSyncStatus'] === 'failed', 'DB record has mauticSyncStatus = failed');
test_assert(!empty($record_fail['mauticLastError']), 'DB record has mauticLastError populated');

// 2.7 Offer without abbreviation: syncs contact with empty tags and marks synced
$insert_no_abbr = $repo->insert([
    'consultaId'        => 'cid-no-abbr-01',
    'offerAbbreviation' => '',
    'email'             => 'noabbr@example.com',
]);
$id_no_abbr = $insert_no_abbr['id'];

$no_abbr_payload = null;
$GLOBALS['http_handler'] = function($url, $args) use (&$no_abbr_payload) {
    if (strpos($url, '/api/contacts?search=') !== false) {
        return [
            'response' => ['code' => 200, 'message' => 'OK'],
            'body'     => json_encode(['total' => 0, 'contacts' => []]),
        ];
    }
    if (strpos($url, '/api/contacts/new') !== false) {
        $no_abbr_payload = json_decode($args['body'], true);
        return [
            'response' => ['code' => 201, 'message' => 'Created'],
            'body'     => json_encode([
                'contact' => [
                    'id'     => 99991,
                    'fields' => ['all' => $no_abbr_payload],
                ],
            ]),
        ];
    }
    return ['response' => ['code' => 404, 'message' => 'Not Found'], 'body' => ''];
};

$res_no_abbr = FLACSO_Inquiry_Marketing_Service::sync_inquiry($id_no_abbr, [
    'email'             => 'noabbr@example.com',
    'firstName'         => 'Elena',
    'offerAbbreviation' => '',
    'cohortNumber'      => 1,
    'offerStatus'       => 'abierta',
]);

test_assert($res_no_abbr['ok'] === true, 'sync_inquiry without abbreviation succeeds');
test_assert($res_no_abbr['status'] === 'synced', 'sync_inquiry without abbreviation is synced');
test_assert($res_no_abbr['contact_id'] === 99991, 'sync_inquiry without abbreviation returns contact_id');
test_assert($res_no_abbr['tags'] === [], 'sync_inquiry without abbreviation returns empty tags');
test_assert(!isset($no_abbr_payload['tags']), 'Payload sent to Mautic has no tags when abbreviation is missing');

$record_no_abbr = $repo->find_by_id($id_no_abbr);
test_assert($record_no_abbr['mauticSyncStatus'] === 'synced', 'DB record has mauticSyncStatus = synced');
test_assert((int)$record_no_abbr['mauticContactId'] === 99991, 'DB record has mauticContactId = 99991');

// 2.8 Support snake_case keys in inquiry_data
$insert_snake = $repo->insert([
    'consultaId' => 'cid-snake-01',
    'email'      => 'snake@example.com',
]);
$id_snake = $insert_snake['id'];

$snake_payload = null;
$GLOBALS['http_handler'] = function($url, $args) use (&$snake_payload) {
    if (strpos($url, '/api/contacts?search=') !== false) {
        return [
            'response' => ['code' => 200, 'message' => 'OK'],
            'body'     => json_encode(['total' => 0, 'contacts' => []]),
        ];
    }
    if (strpos($url, '/api/contacts/new') !== false) {
        $snake_payload = json_decode($args['body'], true);
        return [
            'response' => ['code' => 201, 'message' => 'Created'],
            'body'     => json_encode([
                'contact' => [
                    'id'     => 88882,
                    'fields' => ['all' => $snake_payload],
                ],
            ]),
        ];
    }
    return ['response' => ['code' => 404, 'message' => 'Not Found'], 'body' => ''];
};

$res_snake = FLACSO_Inquiry_Marketing_Service::sync_inquiry($id_snake, [
    'email'              => 'snake@example.com',
    'firstname'          => 'Martín',
    'lastname'           => 'Silva',
    'education_level'    => 'Doctorado',
    'offer_abbreviation' => 'diploma-dh',
    'cohort_number'      => 5,
    'offer_status'       => 'cerrada',
]);

test_assert($res_snake['ok'] === true, 'sync_inquiry with snake_case succeeds');
test_assert($res_snake['status'] === 'synced', 'sync_inquiry with snake_case is synced');
test_assert(
    $res_snake['tags'] === ['interes-diploma-dh', 'interes-diploma-dh-c5', 'consulta-cerrada-diploma-dh-c5'],
    'Tags resolved correctly from snake_case keys'
);
test_assert($snake_payload['firstname'] === 'Martín', 'firstname mapped from snake_case');
test_assert($snake_payload['lastname'] === 'Silva', 'lastname mapped from snake_case');
test_assert($snake_payload['education_level'] === 'Doctorado', 'education_level mapped from snake_case');
// 2.9 Handling of unexpected exceptions during Mautic communication
$insert_exc = $repo->insert([
    'consultaId' => 'cid-exc-01',
    'email'      => 'exc@example.com',
]);
$id_exc = $insert_exc['id'];

$GLOBALS['http_handler'] = function($url, $args) {
    throw new \RuntimeException('Network connection reset by peer');
};

$res_exc = FLACSO_Inquiry_Marketing_Service::sync_inquiry($id_exc, [
    'email'             => 'exc@example.com',
    'offerAbbreviation' => 'davia',
]);
test_assert($res_exc['ok'] === false, 'sync_inquiry handles thrown exception gracefully (ok = false)');
test_assert($res_exc['status'] === 'failed', 'sync_inquiry handles thrown exception gracefully (status = failed)');
test_assert(strpos($res_exc['error'], 'Network connection reset by peer') !== false, 'Error message captures exception message');

$record_exc = $repo->find_by_id($id_exc);
test_assert($record_exc['mauticSyncStatus'] === 'failed', 'DB record marked failed on exception');
test_assert(strpos($record_exc['mauticLastError'], 'Network connection reset by peer') !== false, 'DB error recorded');

// 2.10 Contact update flow (existing contact in Mautic)
$insert_update = $repo->insert([
    'consultaId' => 'cid-update-01',
    'email'      => 'existing@example.com',
]);
$id_update = $insert_update['id'];

$patched_payload = null;
$GLOBALS['http_handler'] = function($url, $args) use (&$patched_payload) {
    if (strpos($url, '/api/contacts?search=') !== false) {
        // Return existing contact with ID 44332
        return [
            'response' => ['code' => 200, 'message' => 'OK'],
            'body'     => json_encode([
                'total'    => 1,
                'contacts' => [
                    44332 => ['id' => 44332, 'fields' => ['all' => ['email' => 'existing@example.com']]],
                ],
            ]),
        ];
    }
    if (strpos($url, '/api/contacts/44332/edit') !== false) {
        $patched_payload = json_decode($args['body'], true);
        return [
            'response' => ['code' => 200, 'message' => 'OK'],
            'body'     => json_encode([
                'contact' => [
                    'id'     => 44332,
                    'fields' => ['all' => $patched_payload],
                ],
            ]),
        ];
    }
    return ['response' => ['code' => 404, 'message' => 'Not Found'], 'body' => ''];
};

$res_update = FLACSO_Inquiry_Marketing_Service::sync_inquiry($id_update, [
    'email'             => 'existing@example.com',
    'firstName'         => 'Gabriela',
    'offerAbbreviation' => 'davia',
    'cohortNumber'      => 10,
    'offerStatus'       => 'abierta',
]);

test_assert($res_update['ok'] === true, 'sync_inquiry with existing contact succeeds');
test_assert($res_update['status'] === 'synced', 'sync_inquiry with existing contact status = synced');
test_assert($res_update['contact_id'] === 44332, 'sync_inquiry returns existing contact ID 44332');
test_assert(is_array($patched_payload), 'PATCH payload was sent to Mautic');
test_assert(
    $patched_payload['tags'] === ['interes-davia', 'interes-davia-c10', 'consulta-abierta-davia-c10'],
    'Tags correctly merged into existing contact payload'
);

$record_update = $repo->find_by_id($id_update);
test_assert($record_update['mauticSyncStatus'] === 'synced', 'DB record updated to synced for existing contact');
test_assert((int)$record_update['mauticContactId'] === 44332, 'DB record has contact ID 44332');

echo "Suite 2: sync_inquiry workflows passed.\n";

echo "\nOK inquiry-marketing-tags-test\n";

