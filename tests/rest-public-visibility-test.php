<?php

define('ABSPATH', __DIR__ . '/../');
define('REST_REQUEST', true);

$GLOBALS['flacso_test_caps'] = [];
$GLOBALS['flacso_test_posts'] = [];

function add_action($hook, $callback, $priority = 10, $accepted_args = 1) {}
function add_filter($hook, $callback, $priority = 10, $accepted_args = 1) {}
function __($text, $domain = null) { return $text; }
function current_user_can($capability, ...$args) {
    $key = $capability;
    if (!empty($args)) {
        $key .= ':' . implode(':', array_map('strval', $args));
    }
    return !empty($GLOBALS['flacso_test_caps'][$key]) || !empty($GLOBALS['flacso_test_caps'][$capability]);
}
function get_post($post_id) {
    return $GLOBALS['flacso_test_posts'][(int) $post_id] ?? null;
}

class WP_Error {
    private $code;
    private $message;
    private $data;

    public function __construct($code = '', $message = '', $data = null) {
        $this->code = $code;
        $this->message = $message;
        $this->data = $data;
    }

    public function get_error_code() { return $this->code; }
    public function get_error_data() { return $this->data; }
}

final class FakeQuery {
    private $vars;

    public function __construct(array $vars) {
        $this->vars = $vars;
    }

    public function get($key) {
        return $this->vars[$key] ?? null;
    }

    public function set($key, $value): void {
        $this->vars[$key] = $value;
    }
}

final class FakeRequest {
    private $method;
    private $route;

    public function __construct(string $method, string $route) {
        $this->method = $method;
        $this->route = $route;
    }

    public function get_method(): string { return $this->method; }
    public function get_route(): string { return $this->route; }
}

require_once __DIR__ . '/../includes/core/class-flacso-rest-visibility.php';

function assert_same($expected, $actual, string $label): void {
    if ($expected !== $actual) {
        fwrite(STDERR, sprintf("Fallo en '%s': esperado %s, obtuve %s\n", $label, var_export($expected, true), var_export($actual, true)));
        exit(1);
    }
}

function assert_wp_404($value, string $label): void {
    if (!($value instanceof WP_Error)) {
        fwrite(STDERR, "Fallo en '{$label}': se esperaba WP_Error\n");
        exit(1);
    }

    assert_same(404, $value->get_error_data()['status'] ?? null, $label . ' status');
}

// Colecciones publicas: nunca deben consultar borradores/privados.
$GLOBALS['flacso_test_caps'] = [];
$docentes_query = new FakeQuery(['post_type' => 'docente', 'post_status' => 'any']);
FLACSO_REST_Visibility::restrict_rest_query($docentes_query);
assert_same('publish', $docentes_query->get('post_status'), 'docentes anonimos solo publish');

$seminarios_query = new FakeQuery(['post_type' => 'seminario', 'post_status' => 'draft']);
FLACSO_REST_Visibility::restrict_rest_query($seminarios_query);
assert_same('publish', $seminarios_query->get('post_status'), 'seminarios anonimos neutralizan status=draft');

// Un usuario editorial conserva la vista completa de coleccion.
$GLOBALS['flacso_test_caps'] = ['edit_posts' => true];
$editor_query = new FakeQuery(['post_type' => 'docente', 'post_status' => 'any']);
FLACSO_REST_Visibility::restrict_rest_query($editor_query);
assert_same('any', $editor_query->get('post_status'), 'editor conserva post_status any');

// No modificar consultas REST de otros tipos de contenido.
$GLOBALS['flacso_test_caps'] = [];
$post_query = new FakeQuery(['post_type' => 'post', 'post_status' => 'any']);
FLACSO_REST_Visibility::restrict_rest_query($post_query);
assert_same('any', $post_query->get('post_status'), 'otros CPT no se alteran');

// GET por ID: borradores y privados deben parecer inexistentes para anonimos.
$GLOBALS['flacso_test_posts'][10] = (object) ['ID' => 10, 'post_type' => 'docente', 'post_status' => 'draft'];
$result = FLACSO_REST_Visibility::guard_private_item(null, null, new FakeRequest('GET', '/flacso-docentes/v1/docentes/10'));
assert_wp_404($result, 'docente draft anonimo');

$GLOBALS['flacso_test_posts'][20] = (object) ['ID' => 20, 'post_type' => 'seminario', 'post_status' => 'private'];
$result = FLACSO_REST_Visibility::guard_private_item(null, null, new FakeRequest('GET', '/flacso/v1/seminarios/20'));
assert_wp_404($result, 'seminario privado anonimo');

// Publicados siguen siendo publicos.
$GLOBALS['flacso_test_posts'][21] = (object) ['ID' => 21, 'post_type' => 'seminario', 'post_status' => 'publish'];
$result = FLACSO_REST_Visibility::guard_private_item(null, null, new FakeRequest('GET', '/flacso/v1/seminarios/21'));
assert_same(null, $result, 'seminario publicado accesible');

// Un usuario con permiso sobre el post puede leer el borrador por ID.
$GLOBALS['flacso_test_caps'] = ['edit_post:10' => true];
$result = FLACSO_REST_Visibility::guard_private_item(null, null, new FakeRequest('GET', '/flacso-docentes/v1/docentes/10'));
assert_same(null, $result, 'editor puede leer docente draft por ID');

// Escrituras no son interceptadas por esta politica; siguen usando sus permisos existentes.
$GLOBALS['flacso_test_caps'] = [];
$result = FLACSO_REST_Visibility::guard_private_item('continue', null, new FakeRequest('POST', '/flacso/v1/seminarios/20'));
assert_same('continue', $result, 'POST no se intercepta');

fwrite(STDOUT, "OK\n");
