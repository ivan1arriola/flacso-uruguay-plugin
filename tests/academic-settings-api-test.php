<?php
/**
 * Test de contrato y funcionamiento de FLACSO_Academic_Settings_API
 * Verifica que los endpoints /ofertas/settings y /ofertas/settings/mailjet-lists existan y funcionen.
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

function current_user_can(string $cap): bool {
    return true;
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

require_once $root . "/modules/oferta-academica/includes/class-academic-settings-api.php";

// 1. Registro de rutas
FLACSO_Academic_Settings_API::register_routes();
settings_assert(isset($GLOBALS["registered_rest_routes"]["/flacso/v1/ofertas/settings"]), "Ruta /flacso/v1/ofertas/settings debe estar registrada");
settings_assert(isset($GLOBALS["registered_rest_routes"]["/flacso/v1/ofertas/settings/mailjet-lists"]), "Ruta /flacso/v1/ofertas/settings/mailjet-lists debe estar registrada");

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
];

$req_update = new WP_REST_Request("PUT", [], $update_payload);
$res_update = FLACSO_Academic_Settings_API::update_settings($req_update);
$data_updated = $res_update->data["data"];

settings_assert(get_option("flacso_carta_mas_info_section_title") === "Información Ampliada", "Opción flacso_carta_mas_info_section_title actualizada en DB");
settings_assert(get_option("flacso_mailjet_sender_name") === "Facultad FLACSO", "Opción sender_name actualizada");
settings_assert(get_option("flacso_webhook_token") === "new-token-9999", "Token actualizado");
settings_assert(get_option("flacso_meta_enabled") === 1, "Meta enabled actualizado a 1");
settings_assert(get_option("fc_consultas_webhook_url") === "https://editor.flacso.edu.uy/api/consultas", "Webhook url actualizada");

// 5. Integración con FLACSO_Integrations_Settings para mailjet-lists
class FLACSO_Integrations_Settings {
    public static function get_mailjet_contact_lists(): array {
        return [
            ["id" => 1234, "name" => "Lista Posgrados 2026", "count" => 450],
        ];
    }
}

$res_lists = FLACSO_Academic_Settings_API::get_mailjet_lists_endpoint();
settings_assert($res_lists->data["ok"] === true, "mailjet lists endpoint ok: true");
settings_assert($res_lists->data["lists"][0]["id"] === 1234, "mailjet list id 1234 devuelta");

echo "OK FLACSO_Academic_Settings_API contract & functional test\n";
