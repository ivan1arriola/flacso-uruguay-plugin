<?php
/**
 * Test suite for FLACSO_Inquiry_Followup_Service.
 *
 * Verifies:
 * - Initialization and WP-Cron scheduling.
 * - Scenario 1: Cohort re-evaluation (inquiry originally closed, now opened -> sends open template with active preinscription URL).
 * - Scenario 2: Offer still closed -> sends closed template.
 * - Scenario 3: Skipped due to newer inquiry for the same offer (has_newer_inquiry_for_offer).
 * - Scenario 4: Skipped due to unpublished/deleted/trashed offer.
 * - Scenario 5: Mautic failure with automatic fallback to Mailjet.
 * - Scenario 6: Globally disabled (flacso_inquiry_followup_enabled = false).
 */

$root = dirname(__DIR__);

if (!defined('ABSPATH')) {
    define('ABSPATH', __DIR__ . '/');
}

// --------------------------------------------------------------------------
// Mocks for WordPress options, cron, posts, and HTTP
// --------------------------------------------------------------------------

$GLOBALS['mock_options'] = [];
$GLOBALS['mock_cron_actions'] = [];
$GLOBALS['mock_cron_events'] = [];
$GLOBALS['mock_posts'] = [];
$GLOBALS['mock_http_calls'] = [];
$GLOBALS['mock_mautic_handler'] = null;
$GLOBALS['mock_mailjet_handler'] = null;

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

if (!function_exists('add_action')) {
    function add_action($tag, $callback, $priority = 10, $accepted_args = 1) {
        $GLOBALS['mock_cron_actions'][$tag][] = $callback;
    }
}

if (!function_exists('wp_next_scheduled')) {
    function wp_next_scheduled($hook, $args = []) {
        return $GLOBALS['mock_cron_events'][$hook] ?? false;
    }
}

if (!function_exists('wp_schedule_event')) {
    function wp_schedule_event($timestamp, $recurrence, $hook, $args = []) {
        $GLOBALS['mock_cron_events'][$hook] = [
            'timestamp'  => $timestamp,
            'recurrence' => $recurrence,
            'args'       => $args,
        ];
        return true;
    }
}

if (!function_exists('get_post_status')) {
    function get_post_status($post_id) {
        return $GLOBALS['mock_posts'][$post_id]['status'] ?? false;
    }
}

if (!function_exists('get_post')) {
    function get_post($post_id) {
        if (!isset($GLOBALS['mock_posts'][$post_id])) {
            return null;
        }
        return (object) $GLOBALS['mock_posts'][$post_id];
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

if (!function_exists('wp_remote_request')) {
    function wp_remote_request($url, $args = []) {
        $GLOBALS['mock_http_calls'][] = ['url' => $url, 'args' => $args];
        if (strpos($url, '/api/emails/') !== false || strpos($url, '/api/contacts/') !== false) {
            if (is_callable($GLOBALS['mock_mautic_handler'])) {
                return ($GLOBALS['mock_mautic_handler'])($url, $args);
            }
            return [
                'response' => ['code' => 200, 'message' => 'OK'],
                'body'     => json_encode(['success' => true, 'result' => true]),
            ];
        }
        if (strpos($url, 'api.mailjet.com') !== false) {
            if (is_callable($GLOBALS['mock_mailjet_handler'])) {
                return ($GLOBALS['mock_mailjet_handler'])($url, $args);
            }
            return [
                'response' => ['code' => 200, 'message' => 'OK'],
                'body'     => json_encode([
                    'Messages' => [
                        [
                            'Status' => 'success',
                            'To' => [['Email' => 'test@example.com', 'MessageID' => '123456', 'MessageUUID' => 'uuid-123456']],
                        ],
                    ],
                ]),
            ];
        }
        return [
            'response' => ['code' => 200, 'message' => 'OK'],
            'body'     => json_encode(['success' => true]),
        ];
    }
}

if (!function_exists('wp_remote_post')) {
    function wp_remote_post($url, $args = []) {
        $args['method'] = 'POST';
        return wp_remote_request($url, $args);
    }
}

if (!function_exists('wp_remote_get')) {
    function wp_remote_get($url, $args = []) {
        $args['method'] = 'GET';
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

// --------------------------------------------------------------------------
// Mock for FLACSO_Academic_Catalog
// --------------------------------------------------------------------------

$GLOBALS['mock_academic_catalog'] = [];

if (!class_exists('FLACSO_Academic_Catalog')) {
    class FLACSO_Academic_Catalog {
        public static function get_offer(int $id): array {
            return $GLOBALS['mock_academic_catalog'][$id] ?? [];
        }
    }
}

// --------------------------------------------------------------------------
// Database Setup (SQLite in memory)
// --------------------------------------------------------------------------

require_once $root . '/includes/database/class-flacso-db.php';
require_once $root . '/includes/database/repositories/class-flacso-base-inquiry-repository.php';
require_once $root . '/includes/database/repositories/class-flacso-offer-inquiry-repository.php';
require_once $root . '/includes/integrations/class-flacso-mautic-client.php';
require_once $root . '/includes/integrations/class-flacso-mailjet-client.php';
require_once $root . '/modules/consultas/services/class-flacso-inquiry-marketing-service.php';

// The service under test:
if (file_exists($root . '/modules/consultas/services/class-flacso-inquiry-followup-service.php')) {
    require_once $root . '/modules/consultas/services/class-flacso-inquiry-followup-service.php';
}

function test_assert(bool $cond, string $msg): void {
    if (!$cond) {
        fwrite(STDERR, "FAIL: {$msg}\n");
        exit(1);
    }
    echo "  PASS: {$msg}\n";
}

function setup_in_memory_db(): FLACSO_Offer_Inquiry_Repository {
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
    );');

    FLACSO_DB::set_connection($pdo);
    FLACSO_Base_Inquiry_Repository::clear_cache();
    return new FLACSO_Offer_Inquiry_Repository();
}

function reset_test_environment(): void {
    $GLOBALS['mock_options'] = [
        'flacso_inquiry_followup_enabled'           => true,
        'flacso_inquiry_followup_days'              => 5,
        'flacso_mautic_template_seguimiento_abierta' => 101,
        'flacso_mautic_template_seguimiento_cerrada' => 102,
        'flacso_mautic_base_url'                    => 'https://mautic.flacso.edu.uy',
        'flacso_mautic_auth_type'                   => 'bearer',
        'flacso_mautic_token'                       => 'test_mautic_bearer_token',
        'flacso_mautic_enabled'                     => 1,
        'flacso_mailjet_api_key'                    => 'mailjet_key_test',
        'flacso_mailjet_secret_key'                 => 'mailjet_secret_test',
        'flacso_mailjet_sender_email'               => 'notificaciones@flacso.edu.uy',
        'flacso_mailjet_sender_name'                => 'FLACSO Uruguay',
        'flacso_mailjet_template_consulta_abierta'  => '991',
        'flacso_mailjet_template_consulta_cerrada'  => '992',
    ];
    $GLOBALS['mock_cron_actions'] = [];
    $GLOBALS['mock_cron_events'] = [];
    $GLOBALS['mock_posts'] = [];
    $GLOBALS['mock_http_calls'] = [];
    $GLOBALS['mock_mautic_handler'] = null;
    $GLOBALS['mock_mailjet_handler'] = null;
    $GLOBALS['mock_academic_catalog'] = [];
}

echo "=== Running FLACSO_Inquiry_Followup_Service Tests ===\n\n";

// Verify class exists
test_assert(class_exists('FLACSO_Inquiry_Followup_Service'), 'Class FLACSO_Inquiry_Followup_Service must exist');

// --------------------------------------------------------------------------
// Test: init() registers WP-Cron hook and schedules event
// --------------------------------------------------------------------------
echo "\n--- Test: init() registration ---\n";
reset_test_environment();
FLACSO_Inquiry_Followup_Service::init();

test_assert(
    isset($GLOBALS['mock_cron_actions'][FLACSO_Inquiry_Followup_Service::CRON_HOOK]),
    'init() must register add_action for CRON_HOOK'
);
test_assert(
    isset($GLOBALS['mock_cron_events'][FLACSO_Inquiry_Followup_Service::CRON_HOOK]),
    'init() must schedule hourly event if not already scheduled'
);
test_assert(
    $GLOBALS['mock_cron_events'][FLACSO_Inquiry_Followup_Service::CRON_HOOK]['recurrence'] === 'hourly',
    'init() scheduled event recurrence must be hourly'
);

// --------------------------------------------------------------------------
// Scenario 1: Cohort re-evaluation (closed at inquiry -> opened at followup)
// --------------------------------------------------------------------------
echo "\n--- Scenario 1: Cohort re-evaluation (closed -> opened) ---\n";
reset_test_environment();
$repo = setup_in_memory_db();

// Mock active WordPress post
$GLOBALS['mock_posts'][201] = [
    'ID' => 201,
    'post_title' => 'Maestría en Políticas Públicas',
    'status' => 'publish',
];

// Mock catalog: initially it was closed, but NOW it has open registrations for Cohorte 2026
$GLOBALS['mock_academic_catalog'][201] = [
    'id' => 201,
    'nombre' => 'Maestría en Políticas Públicas',
    'abreviacion' => 'MPP',
    'url' => 'https://flacso.edu.uy/formacion/mpp/',
    'cohorte_consulta' => [
        'id' => 20102,
        'numero' => 2,
        'nombre' => 'Cohorte 2026',
        'fecha_inicio' => '2026-10-15',
        'precision_fecha_inicio' => 'dia',
        'modalidad' => 'virtual',
        'preinscripcion' => [
            'abierta' => true,
            'desde'   => '2026-09-01',
            'hasta'   => '2026-10-10',
            'url'     => 'https://preinscripciones.flacso.edu.uy/oferta/201/cohorte-2026',
        ],
    ],
];

// Insert inquiry in DB: submitted when closed, due in the past, followupStatus = 'pending'
$inquiry_1 = $repo->insert([
    'consultaId'       => 'cid-scen-1',
    'offerWpId'        => 201,
    'offerName'        => 'Maestría en Políticas Públicas',
    'offerStatus'      => 'cerrada',
    'cohortName'       => 'Cohorte 2025 (Cerrada)',
    'cohortNumber'     => 1,
    'preinscripcionUrl'=> '',
    'firstName'        => 'Camila',
    'lastName'         => 'Ríos',
    'email'            => 'camila@example.com',
    'emailNormalized'  => 'camila@example.com',
    'inquiryAt'        => '2026-09-10 10:00:00',
    'followupStatus'   => 'pending',
    'followupDueAt'    => '2026-09-15 10:00:00',
    'mauticContactId'  => '401',
]);

$last_mautic_call = null;
$GLOBALS['mock_mautic_handler'] = function ($url, $args) use (&$last_mautic_call) {
    $last_mautic_call = ['url' => $url, 'args' => $args];
    return [
        'response' => ['code' => 200, 'message' => 'OK'],
        'body'     => json_encode(['success' => true, 'result' => true]),
    ];
};

$res1 = FLACSO_Inquiry_Followup_Service::run_followup_cycle(25, $repo);

test_assert($res1['ok'] === true, 'run_followup_cycle must succeed');
test_assert($res1['processed'] === 1, 'run_followup_cycle must process 1 inquiry');

// Verify Mautic dispatch details
test_assert($last_mautic_call !== null, 'Mautic email dispatch must be invoked');
test_assert(
    strpos($last_mautic_call['url'], '/api/emails/101/contact/401/send') !== false,
    'Mautic must be called with open template (101) and contact ID 401'
);

$mautic_body = json_decode($last_mautic_call['args']['body'], true);
$tokens = $mautic_body['tokens'] ?? [];
test_assert(
    ($tokens['{url_preinscripcion}'] ?? '') === 'https://preinscripciones.flacso.edu.uy/oferta/201/cohorte-2026',
    'Tokens must include updated preinscripcionUrl from newly opened cohort'
);
test_assert(
    ($tokens['{cohorte_nombre}'] ?? '') === 'Cohorte 2026',
    'Tokens must include fresh cohort name (Cohorte 2026)'
);

// Verify DB update
$updated_1 = $repo->find_by_id($inquiry_1['id']);
test_assert($updated_1['followupStatus'] === 'sent', 'followupStatus in DB must be sent');
test_assert(!empty($updated_1['followupSentAt']), 'followupSentAt must be set');
test_assert($updated_1['followupLastError'] === null, 'followupLastError must be null');

// --------------------------------------------------------------------------
// Scenario 2: Offer still closed -> sends closed template
// --------------------------------------------------------------------------
echo "\n--- Scenario 2: Offer still closed ---\n";
reset_test_environment();
$repo = setup_in_memory_db();

$GLOBALS['mock_posts'][202] = [
    'ID' => 202,
    'post_title' => 'Diploma en Ciencias Sociales',
    'status' => 'publish',
];

$GLOBALS['mock_academic_catalog'][202] = [
    'id' => 202,
    'nombre' => 'Diploma en Ciencias Sociales',
    'cohorte_consulta' => [
        'id' => 20201,
        'numero' => 1,
        'nombre' => 'Cohorte 2025',
        'fecha_inicio' => '2026-11-01',
        'modalidad' => 'a_distancia',
        'preinscripcion' => [
            'abierta' => false,
            'url'     => '',
        ],
    ],
];

$inquiry_2 = $repo->insert([
    'consultaId'       => 'cid-scen-2',
    'offerWpId'        => 202,
    'offerName'        => 'Diploma en Ciencias Sociales',
    'offerStatus'      => 'cerrada',
    'email'            => 'estudiante2@example.com',
    'emailNormalized'  => 'estudiante2@example.com',
    'inquiryAt'        => '2026-09-12 10:00:00',
    'followupStatus'   => 'pending',
    'followupDueAt'    => '2026-09-17 10:00:00',
    'mauticContactId'  => '402',
]);

$last_mautic_call = null;
$GLOBALS['mock_mautic_handler'] = function ($url, $args) use (&$last_mautic_call) {
    $last_mautic_call = ['url' => $url, 'args' => $args];
    return [
        'response' => ['code' => 200, 'message' => 'OK'],
        'body'     => json_encode(['success' => true, 'result' => true]),
    ];
};

$res2 = FLACSO_Inquiry_Followup_Service::run_followup_cycle(25, $repo);

test_assert($res2['processed'] === 1, 'Scenario 2 must process 1 inquiry');
test_assert(
    strpos($last_mautic_call['url'], '/api/emails/102/contact/402/send') !== false,
    'Mautic must be called with closed template (102)'
);

$updated_2 = $repo->find_by_id($inquiry_2['id']);
test_assert($updated_2['followupStatus'] === 'sent', 'Scenario 2 followupStatus must be sent');

// --------------------------------------------------------------------------
// Scenario 3: Skipped due to newer inquiry for the same offer
// --------------------------------------------------------------------------
echo "\n--- Scenario 3: Skipped due to newer inquiry ---\n";
reset_test_environment();
$repo = setup_in_memory_db();

$GLOBALS['mock_posts'][203] = ['ID' => 203, 'status' => 'publish'];
$GLOBALS['mock_academic_catalog'][203] = ['id' => 203, 'nombre' => 'Maestría Repetida'];

// First inquiry (older, due for followup)
$inquiry_3a = $repo->insert([
    'consultaId'       => 'cid-scen-3a',
    'offerWpId'        => 203,
    'email'            => 'repetido@example.com',
    'emailNormalized'  => 'repetido@example.com',
    'inquiryAt'        => '2026-09-10 10:00:00',
    'followupStatus'   => 'pending',
    'followupDueAt'    => gmdate('Y-m-d H:i:s', time() - 3600),
    'mauticContactId'  => '403',
]);

// Second inquiry (newer, not due yet)
$inquiry_3b = $repo->insert([
    'consultaId'       => 'cid-scen-3b',
    'offerWpId'        => 203,
    'email'            => 'repetido@example.com',
    'emailNormalized'  => 'repetido@example.com',
    'inquiryAt'        => '2026-09-16 10:00:00',
    'followupStatus'   => 'pending',
    'followupDueAt'    => gmdate('Y-m-d H:i:s', time() + 86400 * 5),
    'mauticContactId'  => '403',
]);

$mautic_dispatched = false;
$GLOBALS['mock_mautic_handler'] = function () use (&$mautic_dispatched) {
    $mautic_dispatched = true;
    return ['response' => ['code' => 200, 'message' => 'OK'], 'body' => '{"success":true}'];
};

$res3 = FLACSO_Inquiry_Followup_Service::run_followup_cycle(25, $repo);

test_assert($res3['processed'] === 1, 'Only the due inquiry (3a) should be claimed');
test_assert($mautic_dispatched === false, 'No emails should be dispatched for skipped inquiry');

$updated_3a = $repo->find_by_id($inquiry_3a['id']);
test_assert($updated_3a['followupStatus'] === 'skipped', 'Older inquiry must be marked as skipped');
test_assert(
    strpos($updated_3a['followupLastError'], 'Existe consulta más reciente para esta oferta') !== false,
    'followupLastError must state that a newer inquiry exists'
);

// --------------------------------------------------------------------------
// Scenario 4: Skipped due to unpublished/deleted/trashed offer
// --------------------------------------------------------------------------
echo "\n--- Scenario 4: Skipped due to unpublished/trashed offer ---\n";
reset_test_environment();
$repo = setup_in_memory_db();

// 4a: Post in trash
$GLOBALS['mock_posts'][204] = ['ID' => 204, 'status' => 'trash'];

$inquiry_4a = $repo->insert([
    'consultaId'       => 'cid-scen-4a',
    'offerWpId'        => 204,
    'email'            => 'trashed@example.com',
    'emailNormalized'  => 'trashed@example.com',
    'inquiryAt'        => '2026-09-10 10:00:00',
    'followupStatus'   => 'pending',
    'followupDueAt'    => '2026-09-15 10:00:00',
    'mauticContactId'  => '404',
]);

// 4b: Invalid offer ID (0)
$inquiry_4b = $repo->insert([
    'consultaId'       => 'cid-scen-4b',
    'offerWpId'        => 0,
    'email'            => 'invalid@example.com',
    'emailNormalized'  => 'invalid@example.com',
    'inquiryAt'        => '2026-09-10 10:00:00',
    'followupStatus'   => 'pending',
    'followupDueAt'    => '2026-09-15 10:00:00',
    'mauticContactId'  => '405',
]);

$res4 = FLACSO_Inquiry_Followup_Service::run_followup_cycle(25, $repo);

test_assert($res4['processed'] === 2, 'Both inquiries with invalid/trashed offer processed');

$updated_4a = $repo->find_by_id($inquiry_4a['id']);
test_assert($updated_4a['followupStatus'] === 'skipped', 'Trashed offer must be skipped');
test_assert(
    strpos($updated_4a['followupLastError'], 'Oferta despublicada o eliminada') !== false,
    'Reason must indicate unpublished or deleted offer'
);

$updated_4b = $repo->find_by_id($inquiry_4b['id']);
test_assert($updated_4b['followupStatus'] === 'skipped', 'Offer ID 0 must be skipped');

// --------------------------------------------------------------------------
// Scenario 5: Mautic failure with automatic fallback to Mailjet
// --------------------------------------------------------------------------
echo "\n--- Scenario 5: Mautic failure with Mailjet fallback ---\n";
reset_test_environment();
$repo = setup_in_memory_db();

$GLOBALS['mock_posts'][205] = ['ID' => 205, 'status' => 'publish'];
$GLOBALS['mock_academic_catalog'][205] = [
    'id' => 205,
    'nombre' => 'Maestría con Fallback',
    'cohorte_consulta' => [
        'id' => 20501,
        'numero' => 1,
        'nombre' => 'Cohorte 2026',
        'preinscripcion' => ['abierta' => true, 'url' => 'https://flacso.edu.uy/pre/'],
    ],
];

$inquiry_5 = $repo->insert([
    'consultaId'       => 'cid-scen-5',
    'offerWpId'        => 205,
    'offerName'        => 'Maestría con Fallback',
    'email'            => 'fallback@example.com',
    'emailNormalized'  => 'fallback@example.com',
    'inquiryAt'        => '2026-09-10 10:00:00',
    'followupStatus'   => 'pending',
    'followupDueAt'    => '2026-09-15 10:00:00',
    'mauticContactId'  => '405',
]);

// Mautic returns 500 error
$GLOBALS['mock_mautic_handler'] = function ($url, $args) {
    return [
        'response' => ['code' => 500, 'message' => 'Internal Server Error'],
        'body'     => json_encode(['errors' => [['message' => 'Mautic service unavailable']]]),
    ];
};

$mailjet_called = false;
$GLOBALS['mock_mailjet_handler'] = function ($url, $args) use (&$mailjet_called) {
    $mailjet_called = true;
    return [
        'response' => ['code' => 200, 'message' => 'OK'],
        'body'     => json_encode([
            'Messages' => [
                [
                    'Status' => 'success',
                    'To' => [['Email' => 'fallback@example.com', 'MessageID' => '998877', 'MessageUUID' => 'uuid-998877']],
                ],
            ],
        ]),
    ];
};

$res5 = FLACSO_Inquiry_Followup_Service::run_followup_cycle(25, $repo);

test_assert($res5['processed'] === 1, 'Inquiry 5 processed');
test_assert($mailjet_called === true, 'Mailjet fallback must be called when Mautic fails');

$updated_5 = $repo->find_by_id($inquiry_5['id']);
test_assert($updated_5['followupStatus'] === 'sent', 'Status must be sent despite Mautic failure');
test_assert(
    strpos($updated_5['followupLastError'], 'Enviado vía Mailjet (Fallback)') !== false,
    'LastError note must record fallback via Mailjet'
);
test_assert(!empty($updated_5['followupSentAt']), 'followupSentAt must be recorded');

// --------------------------------------------------------------------------
// Scenario 6: Globally disabled (flacso_inquiry_followup_enabled = false)
// --------------------------------------------------------------------------
echo "\n--- Scenario 6: Globally disabled ---\n";
reset_test_environment();
$repo = setup_in_memory_db();
$GLOBALS['mock_options']['flacso_inquiry_followup_enabled'] = false;

// Pending inquiry that is due
$inquiry_6 = $repo->insert([
    'consultaId'       => 'cid-scen-6',
    'offerWpId'        => 206,
    'email'            => 'disabled@example.com',
    'emailNormalized'  => 'disabled@example.com',
    'inquiryAt'        => '2026-09-10 10:00:00',
    'followupStatus'   => 'pending',
    'followupDueAt'    => '2026-09-15 10:00:00',
]);

$res6 = FLACSO_Inquiry_Followup_Service::run_followup_cycle(25, $repo);

test_assert($res6['ok'] === true, 'Response must be ok');
test_assert($res6['status'] === 'disabled', 'Status must be disabled');
test_assert($res6['processed'] === 0, 'Processed count must be 0');

$check_6 = $repo->find_by_id($inquiry_6['id']);
test_assert($check_6['followupStatus'] === 'pending', 'Inquiry must remain pending when disabled');

echo "\nOK inquiry-followup-service-test (all scenarios passed)\n";
