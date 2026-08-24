<?php

define('ABSPATH', __DIR__ . '/../');

$GLOBALS['flacso_test_caps'] = [];
$GLOBALS['flacso_test_posts'] = [];

function add_filter($hook, $callback, $priority = 10, $accepted_args = 1) {}
function __($text, $domain = null) { return $text; }
function is_wp_error($value) { return $value instanceof WP_Error; }
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

final class FakeDTORequest {
    private $method;
    private $route;

    public function __construct(string $method, string $route) {
        $this->method = $method;
        $this->route = $route;
    }

    public function get_method(): string { return $this->method; }
    public function get_route(): string { return $this->route; }
}

final class FakeDTOResponse {
    private $data;

    public function __construct($data) {
        $this->data = $data;
    }

    public function get_data() { return $this->data; }
    public function set_data($data): void { $this->data = $data; }
}

require_once __DIR__ . '/../modules/docentes/includes/rest-dto.php';
require_once __DIR__ . '/../modules/seminarios/includes/rest-dto.php';
require_once __DIR__ . '/../modules/oferta-academica/includes/rest-dto.php';

function dto_assert_same($expected, $actual, string $label): void {
    if ($expected !== $actual) {
        fwrite(STDERR, sprintf("Fallo en '%s': esperado %s, obtuve %s\n", $label, var_export($expected, true), var_export($actual, true)));
        exit(1);
    }
}

function dto_assert_true($actual, string $label): void {
    dto_assert_same(true, (bool) $actual, $label);
}

function dto_assert_missing(string $key, array $payload, string $label): void {
    if (array_key_exists($key, $payload)) {
        fwrite(STDERR, "Fallo en '{$label}': la clave '{$key}' no debe estar presente\n");
        exit(1);
    }
}

// Docente PublicDTO: oculta correos, conserva datos publicos y redes.
$docente = [
    'id' => 7,
    'title' => 'Ada Lovelace',
    'status' => 'publish',
    'correos' => [['email' => 'interno@example.org']],
    'redes' => [['url' => 'https://example.org']],
    'meta' => [
        'nombre' => 'Ada',
        'apellido' => 'Lovelace',
        'cv' => '<p>CV</p>',
        'docente_correos' => [['email' => 'interno@example.org']],
        'docente_redes' => [['url' => 'https://example.org']],
    ],
];
$public_docente = Docente_Public_DTO::from_legacy($docente);
dto_assert_missing('correos', $public_docente, 'docente publico sin correos top-level');
dto_assert_missing('docente_correos', $public_docente['meta'], 'docente publico sin correos en meta');
dto_assert_true(isset($public_docente['meta']['docente_redes']), 'docente publico conserva redes');
$admin_docente = Docente_Admin_DTO::from_legacy($docente);
dto_assert_true(isset($admin_docente['correos']), 'docente admin conserva correos');

// Seminario PublicDTO: oculta operativa tanto en el seminario como en componentes.
$seminario = [
    'id' => 8,
    'title' => 'Seminario',
    'meta' => [
        'creditos' => 5,
        'mail_contacto' => 'interno@example.org',
        'mostrar_en_formulario' => '1',
    ],
    'seminarios_componentes_data' => [[
        'id' => 9,
        'meta' => [
            'creditos' => 2,
            'mail_contacto' => 'otro@example.org',
            'mostrar_en_formulario' => '0',
        ],
    ]],
];
$public_seminario = Seminario_Public_DTO::from_legacy($seminario);
dto_assert_missing('mail_contacto', $public_seminario['meta'], 'seminario publico sin mail operativo');
dto_assert_missing('mostrar_en_formulario', $public_seminario['meta'], 'seminario publico sin flag operativo');
dto_assert_missing('mail_contacto', $public_seminario['seminarios_componentes_data'][0]['meta'], 'componente publico sin mail operativo');
dto_assert_same(5, $public_seminario['meta']['creditos'], 'seminario publico conserva dato academico');
$admin_seminario = Seminario_Admin_DTO::from_legacy($seminario);
dto_assert_true(isset($admin_seminario['meta']['mail_contacto']), 'seminario admin conserva mail operativo');

// Oferta PublicDTO: oculta integracion Mailjet y diagnostico interno.
$oferta = [
    'id' => 10,
    'titulo' => 'Maestria',
    'correo' => 'publico@example.org',
    'mailjet_contact_list_ids' => [123, 456],
    'validacion_ciclos' => ['es_valida' => false, 'problemas' => ['debug']],
    'tabla_precio' => ['moneda' => 'UYU'],
];
$public_oferta = Oferta_Public_DTO::from_schema($oferta);
dto_assert_missing('mailjet_contact_list_ids', $public_oferta, 'oferta publica sin ids Mailjet');
dto_assert_missing('validacion_ciclos', $public_oferta, 'oferta publica sin diagnostico');
dto_assert_same('publico@example.org', $public_oferta['correo'], 'oferta publica conserva correo de contacto');
$admin_oferta = Oferta_Admin_DTO::from_schema($oferta);
dto_assert_same([123, 456], $admin_oferta['mailjet_contact_list_ids'], 'oferta admin conserva Mailjet');

// wp/v2 tambien debe ocultar Mailjet al publico sin eliminar otros meta.
$wp_oferta = [
    'id' => 10,
    'mailjet_contact_list_ids' => [123],
    'validacion_ciclos' => ['es_valida' => true],
    'meta' => [
        'mailjet_contact_list_ids' => [123],
        'duracion_meses' => '12',
    ],
];
$public_wp_oferta = Oferta_Public_DTO::from_wp_rest($wp_oferta);
dto_assert_missing('mailjet_contact_list_ids', $public_wp_oferta, 'wp oferta publica sin Mailjet top-level');
dto_assert_missing('mailjet_contact_list_ids', $public_wp_oferta['meta'], 'wp oferta publica sin Mailjet meta');
dto_assert_same('12', $public_wp_oferta['meta']['duracion_meses'], 'wp oferta publica conserva meta academica');

// El endpoint custom de Oferta no debe revelar borradores por ID.
$GLOBALS['flacso_test_posts'][33] = (object) [
    'ID' => 33,
    'post_type' => 'oferta-academica',
    'post_status' => 'draft',
];
$GLOBALS['flacso_test_caps'] = [];
$response = new FakeDTOResponse(['id' => 33, 'titulo' => 'Borrador']);
$result = Oferta_REST_DTO::transform_custom_response(
    $response,
    null,
    new FakeDTORequest('GET', '/flacso/v1/oferta-academica/33')
);
dto_assert_true($result instanceof WP_Error, 'oferta draft publica responde error');
dto_assert_same(404, $result->get_error_data()['status'] ?? null, 'oferta draft publica responde 404');

// Un editor con permiso sobre el post conserva el AdminDTO completo.
$GLOBALS['flacso_test_caps'] = ['edit_posts' => true, 'edit_post:33' => true];
$response = new FakeDTOResponse($oferta + ['id' => 33]);
$result = Oferta_REST_DTO::transform_custom_response(
    $response,
    null,
    new FakeDTORequest('GET', '/flacso/v1/oferta-academica/33')
);
dto_assert_true($result instanceof FakeDTOResponse, 'editor recibe respuesta oferta');
dto_assert_true(isset($result->get_data()['mailjet_contact_list_ids']), 'editor recibe AdminDTO de oferta');

fwrite(STDOUT, "OK\n");
