<?php
/**
 * Test de contrato y funcionamiento de FLACSO_Academic_Settings_API
 * Verifica que el endpoint /ofertas/settings exista y que no se vuelva a
 * exponer la ruta de listas de Mailjet retirada de la arquitectura actual.
 */

$root = dirname(__DIR__);

function settings_assert($condition, string $message): void {
    if (!$condition) {
        fwrite(STDERR, "FAIL: {$message}\n");
        exit(1);
    }
}

// Stubs de WordPress
if (!defined("ABSPATH")) {
    define("ABSPATH", __DIR__ . "/");
}

class WP_REST_Server {
    public const READABLE = "GET";
    public const CREATABLE = "POST";
    public const EDITABLE = "POST, PUT, PATCH";
    public const DELETABLE = "DELETE";
    public const ALLMETHODS = "GET, POST, PUT, PATCH, DELETE";
}

class WP_REST_Request {
    private array $params = [];
    private array $json = [];
    private string $method = "GET";

    public function __construct(string $method = "GET", array $params = [], array $json = []) {
        $this->method = $method;
        $this->params = $params;
        $this->json = $json;
    }

    public function get_param(string $key) {
        return $this->params[$key] ?? null;
    }

    public function get_json_params(): array {
        return $this->json;
    }

    public function get_method(): string {
        return $this->method;
    }
}

class WP_REST_Response {
    public $data;
    public int $status;
    public function __construct($data = null, int $status = 200) {
        $this->data = $data;
        $this->status = $status;
    }
}

$GLOBALS["wp_options_store"] = [];

function get_option(string $key, $default = false) {
    return $GLOBALS["wp_options_store"][$key] ?? $default;
}

function update_option(string $key, $value): bool {
    $GLOBALS["wp_options_store"][$key] = $value;
    return true;
}

function rest_sanitize_boolean($value): bool {
    return filter_var($value, FILTER_VALIDATE_BOOLEAN);
}

function rest_ensure_response($response) {
    if ($response instanceof WP_REST_Response) {
        return $response;
    }
    return new WP_REST_Response($response, 200);
}

// Capacidades del usuario simulado: cada caso fija las suyas (por defecto, administrador).
$GLOBALS["wp_current_caps"] = ["manage_options" => true, "edit_posts" => true];

function current_user_can(string $cap): bool {
    return !empty($GLOBALS["wp_current_caps"][$cap]);
}

function sanitize_text_field($str) {
    return trim(strip_tags((string) $str));
}

function sanitize_textarea_field($str) {
    return trim(strip_tags((string) $str));
}

function sanitize_email($email) {
    return trim(strtolower((string) $email));
}

function esc_url_raw($url) {
    return trim((string) $url);
}

function wp_kses_post($content) {
    return (string) $content;
}

function wp_specialchars_decode($str) {
    return (string) $str;
}

function get_bloginfo($show = "name") {
    return "FLACSO Uruguay";
}

$GLOBALS["registered_rest_routes"] = [];

function register_rest_route(string $namespace, string $route, array $args = []): bool {
    $full = "/" . trim($namespace, "/") . "/" . trim($route, "/");
    $GLOBALS["registered_rest_routes"][$full] = $args;
    return true;
}

function add_action(string $tag, $callback, int $priority = 10, int $accepted_args = 1): bool {
    return true;
}

require_once $root . "/modules/oferta-academica/includes/class-carta-settings.php";
require_once $root . "/modules/oferta-academica/includes/class-academic-settings-api.php";

// 1. Registro de rutas
FLACSO_Academic_Settings_API::register_routes();
settings_assert(isset($GLOBALS["registered_rest_routes"]["/flacso/v1/ofertas/settings"]), "Ruta /flacso/v1/ofertas/settings debe estar registrada");
settings_assert(!isset($GLOBALS["registered_rest_routes"]["/flacso/v1/ofertas/settings/mailjet-lists"]), "La ruta retirada /flacso/v1/ofertas/settings/mailjet-lists no debe registrarse");

// 2. Lectura enmascarada (include_secrets = false)
update_option("flacso_webhook_token", "secret-token-12345");
update_option("flacso_mailjet_api_key", "mailjet-key-abcde");
update_option("flacso_carta_mas_info_section_title", "Más información");
update_option("flacso_mailjet_sender_email", "posgrados@flacso.edu.uy");

$req_public = new WP_REST_Request("GET", ["include_secrets" => 0]);
$res_public = FLACSO_Academic_Settings_API::get_settings($req_public);
$data_public = $res_public->data["data"];

settings_assert($res_public->data["ok"] === true, "get_settings debe devolver ok: true");
settings_assert($data_public["flacso_webhook_token"] === "********", "Token debe estar enmascarado");
settings_assert($data_public["flacso_mailjet_api_key"] === "********", "Mailjet key debe estar enmascarada");
settings_assert($data_public["flacso_mailjet_sender_email"] === "posgrados@flacso.edu.uy", "Sender email debe coincidir");
settings_assert($data_public["carta_mas_info_section_title"] === "Más información", "Título carta debe coincidir");
settings_assert(!array_key_exists("financiacion_html", $data_public), "No se expone contenido de financiación genérica retirado");
settings_assert(!array_key_exists("carta_mas_info_financiacion_html", $data_public), "No se expone tarjeta financiera retirada");
settings_assert(!array_key_exists("carta_mas_info_financiacion_title", $data_public), "No se expone título retirado");

// 3. Lectura con secretos (include_secrets = true)
$req_secrets = new WP_REST_Request("GET", ["include_secrets" => 1]);
$res_secrets = FLACSO_Academic_Settings_API::get_settings($req_secrets);
$data_secrets = $res_secrets->data["data"];

settings_assert($data_secrets["flacso_webhook_token"] === "secret-token-12345", "Token real con include_secrets=1");
settings_assert($data_secrets["flacso_mailjet_api_key"] === "mailjet-key-abcde", "Mailjet key real con include_secrets=1");

// 4. Actualización de ajustes (update_settings)
$update_payload = [
    "carta_mas_info_section_title" => "Información Ampliada",
    "flacso_mailjet_sender_name" => "Facultad FLACSO",
    "flacso_webhook_token" => "new-token-9999",
    "flacso_meta_enabled" => 1,
    "fc_consultas_webhook_url" => "https://editor.flacso.edu.uy/api/consultas",
    "financiacion_html" => "<p>Texto financiero retirado</p>",
    "carta_mas_info_financiacion_html" => "<p>Otro texto retirado</p>",
    "carta_mas_info_financiacion_title" => "Financiación antigua",
];

$req_update = new WP_REST_Request("PUT", [], $update_payload);
$res_update = FLACSO_Academic_Settings_API::update_settings($req_update);
$data_updated = $res_update->data["data"];

settings_assert(get_option("flacso_carta_mas_info_section_title") === "Información Ampliada", "Opción flacso_carta_mas_info_section_title actualizada en DB");
settings_assert(get_option("flacso_mailjet_sender_name") === "Facultad FLACSO", "Opción sender_name actualizada");
settings_assert(get_option("flacso_webhook_token") === "new-token-9999", "Token actualizado");
settings_assert(get_option("flacso_meta_enabled") === 1, "Meta enabled actualizado a 1");
settings_assert(get_option("fc_consultas_webhook_url") === "https://editor.flacso.edu.uy/api/consultas", "Webhook url actualizada");
settings_assert(get_option("flacso_financiacion_html", null) === null, "La API ignora escritura de financiación retirada");
settings_assert(get_option("flacso_carta_mas_info_financiacion_title", null) === null, "La API ignora título de tarjeta retirado");

// 5. Permisos: la configuración global (webhooks, carpetas de Drive, secretos) solo la ve y
// la cambia un administrador. Se prueban los permission_callback realmente registrados.
$route = $GLOBALS["registered_rest_routes"]["/flacso/v1/ofertas/settings"];
$read_cb = null;
$write_cb = null;
foreach ($route as $handler) {
    if ($handler["methods"] === WP_REST_Server::READABLE) {
        $read_cb = $handler["permission_callback"];
    }
    if ($handler["methods"] === WP_REST_Server::EDITABLE) {
        $write_cb = $handler["permission_callback"];
    }
}
settings_assert(is_callable($read_cb) && is_callable($write_cb), "La ruta debe registrar permission_callback de lectura y de escritura");

$roles = [
    "anonimo" => [],
    "suscriptor" => ["read" => true],
    "colaborador" => ["edit_posts" => true],
    "editor" => ["edit_posts" => true, "edit_others_posts" => true, "publish_posts" => true],
    "administrador" => ["manage_options" => true, "edit_posts" => true],
];
foreach ($roles as $role => $caps) {
    $GLOBALS["wp_current_caps"] = $caps;
    $is_admin = !empty($caps["manage_options"]);
    foreach ([0, 1] as $include_secrets) {
        $request = new WP_REST_Request("GET", ["include_secrets" => $include_secrets]);
        settings_assert(call_user_func($read_cb, $request) === $is_admin, "Lectura (include_secrets={$include_secrets}) solo para administrador; rol: {$role}");
    }
    settings_assert(call_user_func($write_cb, new WP_REST_Request("POST")) === $is_admin, "Escritura solo para administrador; rol: {$role}");
}

// Defensa en profundidad: aunque el permiso se relaje, get_settings no revela secretos a no administradores.
update_option("flacso_webhook_token", "token-real-777");
foreach (["anonimo", "colaborador", "editor"] as $role) {
    $GLOBALS["wp_current_caps"] = $roles[$role];
    $masked = FLACSO_Academic_Settings_API::get_settings(new WP_REST_Request("GET", ["include_secrets" => 1]))->data["data"];
    settings_assert($masked["flacso_webhook_token"] === "********", "include_secrets=1 no revela secretos al rol {$role}");
}
$GLOBALS["wp_current_caps"] = $roles["administrador"];
$full = FLACSO_Academic_Settings_API::get_settings(new WP_REST_Request("GET", ["include_secrets" => 1]))->data["data"];
settings_assert($full["flacso_webhook_token"] === "token-real-777", "El administrador sí lee el secreto con include_secrets=1");

echo "OK FLACSO_Academic_Settings_API contract & functional test\n";
