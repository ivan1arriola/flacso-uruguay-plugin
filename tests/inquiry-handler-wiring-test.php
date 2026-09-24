<?php
// tests/inquiry-handler-wiring-test.php
$root = dirname(__DIR__);

function wiring_assert(bool $cond, string $msg): void {
    if (!$cond) {
        fwrite(STDERR, "FAIL: {$msg}\n");
        exit(1);
    }
}

// ---------------------------------------------------------------------------
// 1. Static assertions
// ---------------------------------------------------------------------------
$ofertas_code = (string) file_get_contents($root . '/modules/main-page/includes/flacso-consultas.php');
$seminarios_code = (string) file_get_contents($root . '/modules/oferta-academica/includes/class-academic-api.php');
$posgrados_code = (string) file_get_contents($root . '/modules/posgrados/includes/class-flacso-posgrados-consultas-form.php');
$helpers_code = (string) file_get_contents($root . '/modules/formularios/includes/helpers.php');

wiring_assert(
    strpos($ofertas_code, 'FLACSO_Offer_Inquiry_Service::submit') !== false,
    'flacso-consultas.php debe delegar en FLACSO_Offer_Inquiry_Service::submit'
);

wiring_assert(
    strpos($seminarios_code, 'FLACSO_Seminar_Inquiry_Service::submit') !== false,
    'class-academic-api.php debe delegar en FLACSO_Seminar_Inquiry_Service::submit'
);

wiring_assert(
    strpos($seminarios_code, 'editor.flacso.edu.uy/api/consultas/seminarios') === false,
    'class-academic-api.php no debe referenciar el endpoint externo del Editor'
);

wiring_assert(
    preg_match('/public\s+static\s+function\s+handle_ajax\s*\(\)\s*:\s*void\s*\{\s*if\s*\(\s*function_exists\(\s*[\'"]flacso_enviar_consulta_func[\'"]\s*\)\s*\)\s*\{\s*flacso_enviar_consulta_func\(\);\s*return;\s*\}/s', $posgrados_code) === 1,
    'FLACSO_Posgrados_Consultas_Form::handle_ajax debe delegar incondicionalmente a flacso_enviar_consulta_func'
);

wiring_assert(
    strpos($helpers_code, "if ( class_exists( 'FLACSO_Offer_Inquiry_Service' ) )") !== false
    && strpos($helpers_code, "return FLACSO_Offer_Inquiry_Service::submit( \$data );") !== false,
    'fc_send_info_request_webhook debe delegar en FLACSO_Offer_Inquiry_Service::submit cuando la clase existe'
);

wiring_assert(
    strpos($seminarios_code, "if (!class_exists('FLACSO_Seminar_Inquiry_Service'))") !== false,
    'class-academic-api.php debe verificar defensivamente la existencia de FLACSO_Seminar_Inquiry_Service'
);

wiring_assert(
    strpos($seminarios_code, "\$result['code'] >= 400 && \$result['code'] <= 599") !== false,
    'class-academic-api.php debe sanitizar el código de error HTTP'
);

// ---------------------------------------------------------------------------
// 2. Functional execution tests with SQLite in-memory DB and mocks
// ---------------------------------------------------------------------------
if (!defined('ABSPATH')) {
    define('ABSPATH', __DIR__ . '/');
}

// Mock WordPress options & Mailjet
$GLOBALS['mailjet_mock_options'] = [
    'flacso_mailjet_api_key'                     => 'mock-key',
    'flacso_mailjet_secret_key'                  => 'mock-secret',
    'flacso_mailjet_sender_email'                => 'notificaciones@flacso.edu.uy',
    'flacso_mailjet_sender_name'                 => 'FLACSO Uruguay',
    'flacso_mailjet_template_consulta_abierta'   => '12345',
    'flacso_mailjet_template_consulta_seminario' => '67890',
];

if (!function_exists('get_option')) {
    function get_option($k, $d = false) {
        return $GLOBALS['mailjet_mock_options'][$k] ?? $d;
    }
}

if (!function_exists('add_action')) {
    function add_action($hook, $callback, $priority = 10, $accepted_args = 1) {}
}
if (!function_exists('add_shortcode')) {
    function add_shortcode($tag, $callback) {}
}

if (!function_exists('sanitize_text_field')) {
    function sanitize_text_field($str) {
        return trim(strip_tags((string) $str));
    }
}
if (!function_exists('sanitize_email')) {
    function sanitize_email($email) {
        return filter_var(trim((string) $email), FILTER_SANITIZE_EMAIL);
    }
}
if (!function_exists('sanitize_textarea_field')) {
    function sanitize_textarea_field($str) {
        return trim(strip_tags((string) $str));
    }
}
if (!function_exists('current_time')) {
    function current_time($type) {
        return date('Y-m-d H:i:s');
    }
}
if (!function_exists('absint')) {
    function absint($v) {
        return abs((int)$v);
    }
}
if (!function_exists('sanitize_key')) {
    function sanitize_key($k) {
        return strtolower(preg_replace('/[^a-z0-9_-]/i', '', (string)$k));
    }
}
if (!function_exists('esc_url_raw')) {
    function esc_url_raw($u) {
        return (string)$u;
    }
}
if (!function_exists('wp_unslash')) {
    function wp_unslash($v) {
        return $v;
    }
}
if (!function_exists('is_email')) {
    function is_email($e) {
        return (bool) filter_var($e, FILTER_VALIDATE_EMAIL);
    }
}
if (!function_exists('wp_json_encode')) {
    function wp_json_encode($d) {
        return json_encode($d);
    }
}
if (!function_exists('add_filter')) {
    function add_filter($h, $c, $p = 10, $a = 1) {}
}
if (!function_exists('__')) {
    function __($text, $domain = '') {
        return $text;
    }
}
if (!function_exists('esc_html__')) {
    function esc_html__($text, $domain = '') {
        return $text;
    }
}
if (!function_exists('get_the_title')) {
    function get_the_title($id) {
        return 'Oferta ' . $id;
    }
}
if (!function_exists('get_permalink')) {
    function get_permalink($id) {
        return 'https://flacso.edu.uy/oferta/' . $id;
    }
}

class TestAjaxSuccessException extends Exception {
    public $data;
    public function __construct($data) {
        $this->data = $data;
        parent::__construct('AJAX Success');
    }
}
class TestAjaxErrorException extends Exception {
    public $data;
    public function __construct($data) {
        $this->data = $data;
        parent::__construct('AJAX Error');
    }
}
if (!function_exists('wp_send_json_success')) {
    function wp_send_json_success($data = null) {
        throw new TestAjaxSuccessException($data);
    }
}
if (!function_exists('wp_send_json_error')) {
    function wp_send_json_error($data = null) {
        throw new TestAjaxErrorException($data);
    }
}

if (!function_exists('wp_generate_uuid4')) {
    function wp_generate_uuid4() {
        $bytes = random_bytes(16);
        $bytes[6] = chr((ord($bytes[6]) & 0x0f) | 0x40);
        $bytes[8] = chr((ord($bytes[8]) & 0x3f) | 0x80);
        return vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($bytes), 4));
    }
}

// Mailjet HTTP mocks
$GLOBALS['mailjet_http_calls'] = [];
if (!function_exists('wp_remote_post')) {
    function wp_remote_post($url, $args) {
        $GLOBALS['mailjet_http_calls'][] = ['url' => $url, 'args' => $args];
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
                                'MessageID' => '999111222',
                                'MessageUUID' => 'a1b2c3d4-e5f6-7890-abcd-ef1234567890',
                            ]
                        ]
                    ]
                ]
            ]),
        ];
    }
}
if (!function_exists('is_wp_error')) {
    function is_wp_error($thing) {
        return false;
    }
}
if (!function_exists('wp_remote_retrieve_response_code')) {
    function wp_remote_retrieve_response_code($res) {
        return $res['response']['code'] ?? 0;
    }
}
if (!function_exists('wp_remote_retrieve_body')) {
    function wp_remote_retrieve_body($res) {
        return $res['body'] ?? '';
    }
}

// WordPress Post mocks
$GLOBALS['mock_posts'] = [];
if (!function_exists('get_post')) {
    function get_post($post_id) {
        return $GLOBALS['mock_posts'][(int)$post_id] ?? null;
    }
}

// WP REST mocks
if (!class_exists('WP_REST_Server')) {
    class WP_REST_Server {
        public const READABLE = 'GET';
        public const CREATABLE = 'POST';
        public const EDITABLE = 'POST, PUT, PATCH';
        public const DELETABLE = 'DELETE';
    }
}

if (!class_exists('WP_REST_Response')) {
    class WP_REST_Response {
        public $data;
        public int $status;
        public function __construct($data = null, int $status = 200) {
            $this->data = $data;
            $this->status = $status;
        }
        public function get_status(): int {
            return $this->status;
        }
        public function get_data() {
            return $this->data;
        }
    }
}

if (!class_exists('WP_REST_Request')) {
    class WP_REST_Request {
        private array $json;
        private array $body;
        public function __construct(string $method = 'POST', array $json = [], array $body = []) {
            $this->json = $json;
            $this->body = $body;
        }
        public function get_json_params(): array {
            return $this->json;
        }
        public function get_body_params(): array {
            return $this->body;
        }
    }
}

// Setup DB
require_once $root . '/includes/database/class-flacso-db.php';
require_once $root . '/includes/database/repositories/class-flacso-offer-inquiry-repository.php';
require_once $root . '/includes/database/repositories/class-flacso-seminar-inquiry-repository.php';
require_once $root . '/includes/integrations/class-flacso-mailjet-client.php';
require_once $root . '/modules/consultas/services/class-flacso-offer-inquiry-service.php';
require_once $root . '/modules/consultas/services/class-flacso-seminar-inquiry-service.php';

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

// Include handlers
require_once $root . '/modules/main-page/includes/flacso-consultas.php';
require_once $root . '/modules/formularios/includes/helpers.php';
require_once $root . '/modules/posgrados/includes/class-flacso-posgrados-consultas-form.php';
require_once $root . '/modules/oferta-academica/includes/class-academic-api.php';

// ---------------------------------------------------------------------------
// 3. Test flacso_consultas_dispatch_single_info_request execution
// ---------------------------------------------------------------------------
$offer_test_data = [
    'id_pagina'       => 101,
    'titulo_posgrado' => 'Maestría en Políticas Públicas',
    'nombre'          => 'Ana',
    'apellido'        => 'García',
    'correo'          => 'ana.garcia@example.com',
    'pais'            => 'Uruguay',
    'nivel_academico' => 'Universitario',
    'profesion'       => 'Socióloga',
    'url_base'        => 'https://flacso.edu.uy/oferta/maestria-politicas-publicas',
    'url_referer'     => 'https://google.com',
    'ip_usuario'      => '127.0.0.1',
    'user_agent'      => 'TestAgent/1.0',
    'fecha_envio'     => '2026-09-24 14:00:00',
];

$offer_res = flacso_consultas_dispatch_single_info_request($offer_test_data);
wiring_assert(is_array($offer_res), 'dispatch_single_info_request debe devolver un array');
wiring_assert(!empty($offer_res['ok']), 'dispatch_single_info_request debe retornar ok => true');
wiring_assert(!empty($offer_res['consulta_id']), 'dispatch_single_info_request debe retornar consulta_id');

$stmt = $pdo->prepare('SELECT * FROM offer_inquiries WHERE consultaId = ?');
$stmt->execute([$offer_res['consulta_id']]);
$offer_row = $stmt->fetch();
wiring_assert(!empty($offer_row), 'Registro de oferta debe persistirse en offer_inquiries');
wiring_assert($offer_row['email'] === 'ana.garcia@example.com', 'Email de oferta debe coincidir');
wiring_assert((int)$offer_row['offerWpId'] === 101, 'offerWpId debe ser 101');
wiring_assert($offer_row['emailStatus'] === 'sent', 'emailStatus de oferta debe ser sent');

// 3.2 Duplicate submission for offer (idempotency)
$offer_dup_res = flacso_consultas_dispatch_single_info_request(array_merge($offer_test_data, [
    'event_id' => $offer_res['consulta_id'],
]));
wiring_assert(!empty($offer_dup_res['ok']), 'Reenvío de oferta con mismo ID debe retornar ok => true');
wiring_assert(!empty($offer_dup_res['duplicate']), 'Reenvío de oferta debe indicar duplicate => true');

// 3.3 Invalid email in offer submission
$offer_invalid = flacso_consultas_dispatch_single_info_request(array_merge($offer_test_data, [
    'correo' => 'invalido',
]));
wiring_assert(empty($offer_invalid['ok']), 'Oferta con email inválido debe retornar ok => false');
wiring_assert(($offer_invalid['code'] ?? 0) === 422, 'Oferta con email inválido debe retornar código 422');

// ---------------------------------------------------------------------------
// 4. Test FLACSO_Academic_API::submit_consulta_seminario execution
// ---------------------------------------------------------------------------
// 4.1 Missing required fields
$req_missing = new WP_REST_Request('POST', ['seminario_id' => 202]);
$res_missing = FLACSO_Academic_API::submit_consulta_seminario($req_missing);
wiring_assert($res_missing instanceof WP_REST_Response, 'Debe devolver WP_REST_Response');
wiring_assert($res_missing->get_status() === 400, 'Faltan campos obligatorios debe devolver 400');

// 4.2 Seminar post not found
$req_notfound = new WP_REST_Request('POST', [
    'seminario_id'     => 999,
    'seminario_titulo' => 'Seminario Inexistente',
    'nombre'           => 'Pedro',
    'correo'           => 'pedro@example.com',
    'telefono'         => '12345678',
    'pais'             => 'Uruguay',
    'consulta'         => 'Hola',
]);
$res_notfound = FLACSO_Academic_API::submit_consulta_seminario($req_notfound);
wiring_assert($res_notfound->get_status() === 404, 'Seminario inexistente debe devolver 404');

// 4.3 Successful submission
$GLOBALS['mock_posts'][202] = (object)[
    'ID'        => 202,
    'post_type' => 'seminario',
];

$req_valid = new WP_REST_Request('POST', [
    'seminario_id'     => 202,
    'seminario_titulo' => 'Seminario de Género y Políticas',
    'nombre'           => 'Carlos',
    'apellido'         => 'Pérez',
    'correo'           => 'carlos.perez@example.com',
    'telefono'         => '+59899112233',
    'pais'             => 'Uruguay',
    'consulta'         => '¿Cuáles son los requisitos de aprobación?',
    'source'           => 'Web Seminario',
]);

$res_valid = FLACSO_Academic_API::submit_consulta_seminario($req_valid);
wiring_assert($res_valid->get_status() === 200, 'Consulta de seminario válida debe devolver 200');
$data_valid = $res_valid->get_data();
wiring_assert(!empty($data_valid['success']), 'Respuesta debe tener success => true');
wiring_assert(!empty($data_valid['consulta_id']), 'Respuesta debe contener consulta_id');
wiring_assert($data_valid['email_status'] === 'sent', 'Respuesta debe contener email_status => sent');

$stmt_sem = $pdo->prepare('SELECT * FROM seminar_inquiries WHERE consultaId = ?');
$stmt_sem->execute([$data_valid['consulta_id']]);
$seminar_row = $stmt_sem->fetch();
wiring_assert(!empty($seminar_row), 'Registro de seminario debe persistirse en seminar_inquiries');
wiring_assert($seminar_row['email'] === 'carlos.perez@example.com', 'Email de seminario debe coincidir');
wiring_assert((int)$seminar_row['seminarWpId'] === 202, 'seminarWpId debe ser 202');
wiring_assert($seminar_row['emailStatus'] === 'sent', 'emailStatus de seminario debe ser sent');

// 4.4 Duplicate submission for seminar (idempotency)
$GLOBALS['mailjet_http_calls'] = [];
$req_dup = new WP_REST_Request('POST', [
    'event_id'         => $data_valid['consulta_id'],
    'seminario_id'     => 202,
    'seminario_titulo' => 'Seminario de Género y Políticas',
    'nombre'           => 'Carlos',
    'correo'           => 'carlos.perez@example.com',
    'telefono'         => '+59899112233',
    'pais'             => 'Uruguay',
    'consulta'         => '¿Cuáles son los requisitos de aprobación?',
]);
$res_dup = FLACSO_Academic_API::submit_consulta_seminario($req_dup);
wiring_assert($res_dup->get_status() === 200, 'Reenvío de seminario debe responder 200');
$data_dup = $res_dup->get_data();
wiring_assert(!empty($data_dup['success']), 'Reenvío de seminario debe retornar success => true');
wiring_assert(empty($GLOBALS['mailjet_http_calls']), 'Reenvío de seminario no debe reenviar email');

// 4.5 Invalid email in seminar submission
$req_invalid_email = new WP_REST_Request('POST', [
    'seminario_id'     => 202,
    'seminario_titulo' => 'Seminario',
    'nombre'           => 'Carlos',
    'correo'           => 'email-invalido',
    'telefono'         => '123',
    'pais'             => 'Uruguay',
    'consulta'         => 'Duda',
]);
$res_invalid_email = FLACSO_Academic_API::submit_consulta_seminario($req_invalid_email);
wiring_assert($res_invalid_email->get_status() === 422, 'Email inválido en seminario debe retornar status 422');
wiring_assert(empty($res_invalid_email->get_data()['success']), 'Email inválido debe retornar success => false');

// ---------------------------------------------------------------------------
// 5. Test fc_send_info_request_webhook delegation to FLACSO_Offer_Inquiry_Service
// ---------------------------------------------------------------------------
$webhook_test_data = [
    'id_pagina'       => 108,
    'titulo_posgrado' => 'Diploma Superior en Políticas',
    'nombre'          => 'Valeria',
    'apellido'        => 'Silva',
    'correo'          => 'valeria.silva@example.com',
    'pais'            => 'Uruguay',
    'nivel_academico' => 'Universitario',
    'profesion'       => 'Economista',
    'url_base'        => 'https://flacso.edu.uy/oferta/diploma-politicas',
    'url_referer'     => 'https://google.com',
];

$webhook_res = fc_send_info_request_webhook($webhook_test_data);
wiring_assert(is_array($webhook_res), 'fc_send_info_request_webhook debe retornar un array');
wiring_assert(!empty($webhook_res['ok']), 'fc_send_info_request_webhook debe retornar ok => true');
wiring_assert(!empty($webhook_res['consulta_id']), 'fc_send_info_request_webhook debe retornar consulta_id');

$stmt_wb = $pdo->prepare('SELECT * FROM offer_inquiries WHERE consultaId = ?');
$stmt_wb->execute([$webhook_res['consulta_id']]);
$wb_row = $stmt_wb->fetch();
wiring_assert(!empty($wb_row), 'Registro enviado por fc_send_info_request_webhook debe persistirse');
wiring_assert($wb_row['email'] === 'valeria.silva@example.com', 'Email del registro webhook debe coincidir');
wiring_assert((int)$wb_row['offerWpId'] === 108, 'offerWpId del webhook debe ser 108');

// ---------------------------------------------------------------------------
// 6. Test FLACSO_Posgrados_Consultas_Form::handle_ajax unconditional delegation
// ---------------------------------------------------------------------------
$_POST = [
    'id_pagina'            => '109',
    'titulo_posgrado'      => 'Especialización en Educación',
    'nombre'               => 'Mateo',
    'apellido'             => 'Ramos',
    'correo'               => 'mateo.ramos@example.com',
    'pais'                 => 'Uruguay',
    'nivel_academico'      => 'Universitario',
    'profesion'            => 'Profesor',
    'url_base'             => 'https://flacso.edu.uy/oferta/especializacion-educacion',
    'dynamic_info_form_id' => '0', // Single posgrado form (no dynamic form id)
];

$ajax_caught = false;
try {
    FLACSO_Posgrados_Consultas_Form::handle_ajax();
} catch (TestAjaxSuccessException $e) {
    $ajax_caught = true;
    $ajax_data = $e->data;
    wiring_assert(!empty($ajax_data['note']) && $ajax_data['note'] === 'ok', 'handle_ajax debe responder con note => ok');
} catch (TestAjaxErrorException $e) {
    wiring_assert(false, 'handle_ajax no debe fallar para una consulta válida');
}
wiring_assert($ajax_caught, 'handle_ajax debe delegar incondicionalmente a flacso_enviar_consulta_func y terminar con éxito');

$stmt_ajax = $pdo->prepare('SELECT * FROM offer_inquiries WHERE email = ?');
$stmt_ajax->execute(['mateo.ramos@example.com']);
$ajax_row = $stmt_ajax->fetch();
wiring_assert(!empty($ajax_row), 'Consulta procesada por handle_ajax debe persistirse en offer_inquiries');
wiring_assert((int)$ajax_row['offerWpId'] === 109, 'offerWpId de handle_ajax debe ser 109');

// ---------------------------------------------------------------------------
// 7. Un fallo real de persistencia nunca puede convertirse en éxito por RELAXED_MODE
// ---------------------------------------------------------------------------
$broken_pdo = new PDO('sqlite::memory:', null, null, [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
]);
FLACSO_DB::set_connection($broken_pdo);
FLACSO_Base_Inquiry_Repository::clear_cache();

$_POST = [
    'id_pagina'            => '110',
    'titulo_posgrado'      => 'Diploma de prueba',
    'nombre'               => 'Laura',
    'apellido'             => 'Pérez',
    'correo'               => 'laura.perez@example.com',
    'pais'                 => 'Uruguay',
    'nivel_academico'      => 'Universitario',
    'profesion'            => 'Docente',
    'url_base'             => 'https://flacso.edu.uy/oferta/diploma-prueba',
    'dynamic_info_form_id' => '0',
];

$db_error_caught = false;
try {
    FLACSO_Posgrados_Consultas_Form::handle_ajax();
} catch (TestAjaxErrorException $e) {
    $db_error_caught = true;
} catch (TestAjaxSuccessException $e) {
    wiring_assert(false, 'Un fallo de PostgreSQL no debe responder success aunque FLACSO_RELAXED_MODE esté activo');
}
wiring_assert($db_error_caught, 'Un fallo de PostgreSQL debe responder wp_send_json_error');

FLACSO_DB::set_connection($pdo);
FLACSO_Base_Inquiry_Repository::clear_cache();

echo "OK inquiry-handler-wiring-test\n";
