<?php
/** Pruebas aisladas del puente Meta Bot -> Consultas sin servidor WordPress. */
define('ABSPATH', __DIR__ . '/');
define('FLACSO_METABOT_INQUIRY_TOKEN', str_repeat('x', 40));

class WP_REST_Server {
    public const READABLE = 'GET';
    public const CREATABLE = 'POST';
}
class WP_REST_Request {
    private array $body;
    private string $bearer;
    public function __construct(array $body = [], string $bearer = '') {
        $this->body = $body;
        $this->bearer = $bearer;
    }
    public function get_json_params(): array { return $this->body; }
    public function get_header(string $name): string {
        return strtolower($name) === 'authorization' ? $this->bearer : '';
    }
}
class WP_REST_Response {
    public $data;
    public int $status;
    public function __construct($data, int $status) { $this->data = $data; $this->status = $status; }
    public function header(string $name, string $value): void {}
}
class WP_Error {
    public string $code;
    public array $data;
    public function __construct(string $code, string $message, array $data) {
        $this->code = $code;
        $this->data = $data;
    }
}
$GLOBALS['routes'] = [];
$GLOBALS['records'] = [];
function add_action(string $name, $callback): void {}
function register_rest_route(string $namespace, string $route, array $args): void {
    $GLOBALS['routes'][$namespace . $route] = $args;
}
function sanitize_text_field($value): string { return trim(strip_tags($value)); }
function sanitize_textarea_field($value): string { return trim(strip_tags($value)); }
function sanitize_email($value): string { return strtolower(trim($value)); }
function is_email($value): bool { return (bool) filter_var($value, FILTER_VALIDATE_EMAIL); }
function get_post(int $id) {
    if ($id === 17) return (object) ['ID' => 17, 'post_type' => 'oferta-academica', 'post_status' => 'publish', 'post_title' => 'Maestría en Género'];
    if ($id === 42) return (object) ['ID' => 42, 'post_type' => 'seminario', 'post_status' => 'publish', 'post_title' => 'Seminario de Educación'];
    if ($id === 55) return (object) ['ID' => 55, 'post_type' => 'seminario', 'post_status' => 'draft', 'post_title' => 'Borrador'];
    return null;
}
function get_posts(array $args): array {
    if ($args['post_type'] === 'oferta-academica') return [get_post(17)];
    return [get_post(42)];
}
function get_the_title($post): string { return $post->post_title; }
function get_permalink($post): string { return 'https://flacso.edu.uy/formacion/' . $post->ID . '/'; }
class FLACSO_Oferta_Academica {
    public static function get_tipo(int $id): string { return 'maestria'; }
}
class FLACSO_Offer_Inquiry_Service {
    public static function submit(array $payload): array {
        $GLOBALS['records'][] = ['kind' => 'offer', 'payload' => $payload];
        return ['ok' => true, 'consulta_id' => $payload['event_id'], 'duplicate' => false];
    }
}
class FLACSO_Seminar_Inquiry_Service {
    public static function submit(array $payload): array {
        $GLOBALS['records'][] = ['kind' => 'seminar', 'payload' => $payload];
        return ['ok' => true, 'consulta_id' => $payload['event_id'], 'duplicate' => false];
    }
}
function chatbot_assert(bool $condition, string $description): void {
    if (!$condition) {
        fwrite(STDERR, "FAIL: {$description}\n");
        exit(1);
    }
}

require_once dirname(__DIR__) . '/modules/consultas/includes/class-flacso-chatbot-inquiry-api.php';
FLACSO_Chatbot_Inquiry_API::register_routes();
chatbot_assert(isset($GLOBALS['routes']['flacso/v1/chatbot/consultas']), 'Ruta de consultas');
chatbot_assert(isset($GLOBALS['routes']['flacso/v1/chatbot/catalogo']), 'Ruta de catálogo');

$bad = FLACSO_Chatbot_Inquiry_API::authorize(new WP_REST_Request([], 'Bearer ' . str_repeat('n', 40)));
chatbot_assert($bad instanceof WP_Error && $bad->data['status'] === 401, 'Rechaza token inválido');
$good = FLACSO_Chatbot_Inquiry_API::authorize(new WP_REST_Request([], 'Bearer ' . str_repeat('x', 40)));
chatbot_assert($good === true, 'Acepta credencial compartida');

$id = 'metabot-' . str_repeat('a', 64);
$base = [
    'eventId' => $id,
    'channel' => 'instagram',
    'academic' => ['kind' => 'offer', 'id' => 17],
    'firstName' => 'Lucía',
    'lastName' => 'Pérez',
    'email' => 'lucia@example.org',
];
$valid = FLACSO_Chatbot_Inquiry_API::submit(new WP_REST_Request($base));
chatbot_assert($valid->status === 200 && $valid->data['ok'], 'Envío válido');
chatbot_assert($GLOBALS['records'][0]['kind'] === 'offer', 'Servicio académico correcto');
chatbot_assert($GLOBALS['records'][0]['payload']['source'] === 'Instagram', 'Conserva origen');
chatbot_assert($GLOBALS['records'][0]['payload']['marketingConsentGranted'] === false, 'No infiere consentimiento');

$seminar = $base;
$seminar['academic'] = ['kind' => 'seminar', 'id' => 42];
$seminar['channel'] = 'messenger';
$seminar['eventId'] = 'metabot-' . str_repeat('b', 64);
$sem_result = FLACSO_Chatbot_Inquiry_API::submit(new WP_REST_Request($seminar));
chatbot_assert($sem_result->status === 200, 'Seminario permitido');
chatbot_assert($GLOBALS['records'][1]['kind'] === 'seminar', 'Servicio seminarios correcto');
chatbot_assert($GLOBALS['records'][1]['payload']['source'] === 'Facebook Messenger', 'Canal Messenger correcto');

$draft = $seminar;
$draft['academic']['id'] = 55;
$denied = FLACSO_Chatbot_Inquiry_API::submit(new WP_REST_Request($draft));
chatbot_assert($denied->status === 422, 'Rechaza borrador');
$wrong_type = $seminar;
$wrong_type['academic']['id'] = 17;
chatbot_assert(FLACSO_Chatbot_Inquiry_API::submit(new WP_REST_Request($wrong_type))->status === 422, 'No confunde oferta con seminario');
$missing_name = $base;
$missing_name['firstName'] = '';
chatbot_assert(FLACSO_Chatbot_Inquiry_API::submit(new WP_REST_Request($missing_name))->status === 422, 'Nombre obligatorio');
chatbot_assert(count($GLOBALS['records']) === 2, 'No registra solicitudes inválidas');

$catalog = FLACSO_Chatbot_Inquiry_API::catalog();
chatbot_assert($catalog->status === 200 && count($catalog->data['items']) === 2, 'Catálogo unifica ambas raíces');
chatbot_assert($catalog->data['items'][1]['kind'] === 'seminar', 'Seminario conserva el tipo técnico');
echo "OK chatbot inquiry API\n";
