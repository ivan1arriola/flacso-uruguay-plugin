<?php
/**
 * Contrato: GET /flacso/v1/ofertas/{id}/consulta informa a Mautic el estado
 * de preinscripción con la misma cohorte que la consulta. "Abierta" exige
 * enlace de preinscripción; una oferta inexistente responde 404.
 */
if (!defined('ABSPATH')) {
    define('ABSPATH', dirname(__DIR__) . '/');
}
function absint($value) { return abs((int) $value); }
function get_permalink($id) { return 'https://flacso.edu.uy/oferta/' . $id . '/'; }
function add_action(...$args) {}
function register_rest_route($namespace, $route, $args) { $GLOBALS['routes'][$namespace . $route] = $args; }

final class WP_REST_Server { public const READABLE = 'GET'; }
final class WP_REST_Request implements ArrayAccess {
    public function __construct(private array $params) {}
    public function offsetExists($key): bool { return isset($this->params[$key]); }
    public function offsetGet($key): mixed { return $this->params[$key] ?? null; }
    public function offsetSet($key, $value): void { $this->params[$key] = $value; }
    public function offsetUnset($key): void { unset($this->params[$key]); }
}
final class WP_REST_Response {
    public array $headers = [];
    public function __construct(public $data, public int $status) {}
    public function header($name, $value) { $this->headers[$name] = $value; }
}
final class WP_Error {
    public function __construct(public string $code, public string $message, public array $data) {}
}

$GLOBALS['offers'] = [];
final class FLACSO_Academic_Catalog {
    public static function get_offer(int $offer_id): array { return $GLOBALS['offers'][$offer_id] ?? []; }
}

require_once dirname(__DIR__) . '/modules/consultas/services/class-flacso-inquiry-context-service.php';
require_once dirname(__DIR__) . '/modules/consultas/includes/class-flacso-offer-status-api.php';

function status_assert($ok, $message) {
    if (!$ok) { fwrite(STDERR, "FAIL: " . $message . "\n"); exit(1); }
}

function offer_fixture(?array $cohort): array {
    return ['id' => 10, 'nombre' => 'Maestría de prueba', 'abreviacion' => 'MP', 'tipo' => 'maestria',
        'resumen' => 'Resumen', 'cohorte_consulta' => $cohort];
}

$open_cohort = [
    'id' => 105, 'numero' => 6, 'nombre' => 'Cohorte VI', 'fecha_inicio' => '2027-03-01',
    'precision_fecha_inicio' => 'mes', 'modalidad' => 'Virtual',
    'preinscripcion' => ['abierta' => true, 'url' => 'https://preinscripciones.flacso.edu.uy/x', 'desde' => '2026-10-01', 'hasta' => '2027-02-01'],
];
$GLOBALS['offers'][10] = offer_fixture($open_cohort);

$open = FLACSO_Inquiry_Context_Service::offer_status(10);
status_assert($open['estado'] === 'abierta', 'cohorte abierta con enlace es abierta');
status_assert($open['offerWpId'] === 10 && $open['nombre'] === 'Maestría de prueba', 'identifica la oferta');
status_assert($open['url'] === 'https://flacso.edu.uy/oferta/10/', 'incluye la URL pública de la oferta');
status_assert($open['carta']['url'] === 'https://flacso.edu.uy/oferta/10/carta', 'incluye el enlace público a la carta');
status_assert($open['cohorte']['id'] === 105 && $open['cohorte']['nombre'] === 'Cohorte VI', 'incluye la cohorte de consulta');
status_assert($open['cohorte']['precision'] === 'month' && $open['cohorte']['modalidad'] === 'virtual', 'normaliza precisión y modalidad');
status_assert($open['preinscripcion']['url'] === 'https://preinscripciones.flacso.edu.uy/x', 'incluye el enlace de preinscripción');

$no_url = $open_cohort;
$no_url['preinscripcion']['url'] = '';
$GLOBALS['offers'][10] = offer_fixture($no_url);
status_assert(FLACSO_Inquiry_Context_Service::offer_status(10)['estado'] === 'cerrada', 'abierta sin enlace cuenta como cerrada');

$closed = $open_cohort;
$closed['preinscripcion']['abierta'] = false;
$GLOBALS['offers'][10] = offer_fixture($closed);
status_assert(FLACSO_Inquiry_Context_Service::offer_status(10)['estado'] === 'cerrada', 'cohorte cerrada es cerrada');

$GLOBALS['offers'][10] = offer_fixture(null);
$without = FLACSO_Inquiry_Context_Service::offer_status(10);
status_assert($without['estado'] === 'sin_cohorte' && $without['cohorte'] === null, 'sin cohorte publicada informa sin_cohorte');

status_assert(FLACSO_Inquiry_Context_Service::offer_status(999) === null, 'oferta inexistente da null');
status_assert(FLACSO_Inquiry_Context_Service::offer_status(0) === null, 'ID inválido da null');

$GLOBALS['offers'][10] = offer_fixture($open_cohort);
$resolved = FLACSO_Inquiry_Context_Service::resolve(10);
status_assert($resolved['offerStatus'] === 'abierta' && $resolved['cohortWpId'] === 105, 'resolve() conserva su comportamiento');

FLACSO_Offer_Status_API::register_routes();
$route = $GLOBALS['routes']['flacso/v1/ofertas/(?P<id>\d+)/consulta'] ?? null;
status_assert($route !== null && $route['methods'] === 'GET' && $route['permission_callback'] === '__return_true', 'ruta pública de lectura registrada');

$response = FLACSO_Offer_Status_API::status(new WP_REST_Request(['id' => '10']));
status_assert($response instanceof WP_REST_Response && $response->status === 200, 'responde 200');
status_assert($response->data['estado'] === 'abierta', 'devuelve el estado');
status_assert(($response->headers['Cache-Control'] ?? '') === 'no-store', 'prohíbe cachear el estado');

$missing = FLACSO_Offer_Status_API::status(new WP_REST_Request(['id' => '999']));
status_assert($missing instanceof WP_Error && $missing->data['status'] === 404, 'oferta inexistente responde 404');

fwrite(STDOUT, "OK offer status API\n");
