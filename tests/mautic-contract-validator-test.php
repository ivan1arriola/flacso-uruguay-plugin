<?php
$root = dirname(__DIR__);

if (!defined('ABSPATH')) {
    define('ABSPATH', __DIR__ . '/');
}
if (!class_exists('WP_Error')) {
    class WP_Error {
        public function __construct(private string $message) {}
        public function get_error_message(): string { return $this->message; }
    }
}
if (!function_exists('is_wp_error')) {
    function is_wp_error($value) { return $value instanceof WP_Error; }
}
if (!function_exists('get_option')) {
    function get_option($key, $default = false) {
        $options = [
            'flacso_mautic_enabled' => '1',
            'flacso_mautic_base_url' => 'https://mautic.example.org',
            'flacso_mautic_auth_type' => 'basic',
            'flacso_mautic_username' => 'test',
            'flacso_mautic_password' => 'secret',
        ];
        return $options[$key] ?? $default;
    }
}

require_once $root . '/includes/integrations/class-flacso-mautic-client.php';
require_once $root . '/modules/consultas/services/class-flacso-mautic-contract-manifest.php';
require_once $root . '/modules/consultas/services/class-flacso-mautic-contract-validator.php';

function contract_assert(bool $condition, string $message): void {
    if (!$condition) {
        fwrite(STDERR, "FAIL: {$message}\n");
        exit(1);
    }
}

$template = [
    'id' => 3,
    'subject' => 'Recibimos tu consulta sobre {programa}',
    'customHtml' => '<p>Hola {nombre}</p>',
    'isPublished' => true,
];
$sha = hash('sha256', FLACSO_Mautic_Contract_Validator::normalized_template_content($template));
$manifest = [
    'version' => 'test-1',
    'contact_fields' => [
        'flacso_origen' => ['type' => 'text'],
        'flacso_modalidad_perfil' => ['type' => 'select', 'options' => ['virtual', 'presencial']],
    ],
    'template' => [
        'id' => 3,
        'functional_version' => 'ack-test',
        'content_sha256' => $sha,
    ],
];

FLACSO_Mautic_Client::set_http_transport(function(string $url, array $args) use ($template) {
    if (str_contains($url, '/api/fields/contact')) {
        return [
            'response' => ['code' => 200],
            'body' => json_encode(['fields' => [
                ['alias' => 'flacso_origen', 'type' => 'text'],
                ['alias' => 'flacso_modalidad_perfil', 'type' => 'select', 'properties' => ['list' => [
                    ['value' => 'virtual'], ['value' => 'presencial'],
                ]]],
            ]]),
        ];
    }
    if (str_contains($url, '/api/emails/3')) {
        return ['response' => ['code' => 200], 'body' => json_encode(['email' => $template])];
    }
    return ['response' => ['code' => 404], 'body' => '{}'];
});

$valid = FLACSO_Mautic_Contract_Validator::validate($manifest);
contract_assert($valid['ok'] === true && $valid['status'] === 'valid', 'contrato compatible es válido');

$missing = $manifest;
$missing['contact_fields']['alias_inexistente'] = ['type' => 'text'];
$result = FLACSO_Mautic_Contract_Validator::validate($missing);
contract_assert($result['ok'] === true && $result['status'] === 'valid', 'alias de marketing ausente no bloquea el correo transaccional');
$missing_requirement = array_values(array_filter(
    $result['requirements'],
    static fn(array $requirement): bool => ($requirement['key'] ?? '') === 'field:alias_inexistente'
));
contract_assert(!empty($missing_requirement) && ($missing_requirement[0]['blocking'] ?? true) === false, 'campo ausente queda como diagnóstico no bloqueante');

$wrongType = $manifest;
$wrongType['contact_fields']['flacso_origen']['type'] = 'number';
contract_assert(FLACSO_Mautic_Contract_Validator::validate($wrongType)['ok'] === true, 'tipo de campo de marketing incompatible no bloquea el correo transaccional');

$wrongOptions = $manifest;
$wrongOptions['contact_fields']['flacso_modalidad_perfil']['options'][] = 'hibrida';
contract_assert(FLACSO_Mautic_Contract_Validator::validate($wrongOptions)['ok'] === true, 'opción de marketing ausente no bloquea el correo transaccional');

$wrongHash = $manifest;
$wrongHash['template']['content_sha256'] = str_repeat('0', 64);
$wrong_hash_result = FLACSO_Mautic_Contract_Validator::validate($wrongHash);
contract_assert($wrong_hash_result['ok'] === true, 'hash distinto queda como auditoría y no bloquea');
$hash_requirement = array_values(array_filter(
    $wrong_hash_result['requirements'],
    static fn(array $requirement): bool => str_starts_with((string) ($requirement['key'] ?? ''), 'template_sha256:')
));
contract_assert(!empty($hash_requirement) && ($hash_requirement[0]['blocking'] ?? true) === false, 'hash distinto se marca no bloqueante');

$noHash = $manifest;
$noHash['template']['content_sha256'] = '';
contract_assert(FLACSO_Mautic_Contract_Validator::validate($noHash)['ok'] === true, 'una plantilla publicada sin hash aprobado sigue operativa');

// Contrato con los dos F1: se puede validar sólo la plantilla usada por la entrega.
$multi_template = [
    'version' => 'test-2',
    'contact_fields' => [],
    'templates' => [
        'initial_open' => [
            'id' => 3,
            'name' => 'F1 abierta',
            'functional_version' => 'open-test',
            'content_sha256' => $sha,
            'required' => true,
        ],
        'initial_closed' => [
            'id' => 5,
            'name' => 'F1 cerrada',
            'functional_version' => 'closed-test',
            'content_sha256' => '',
            'required' => true,
        ],
    ],
];
$selected = FLACSO_Mautic_Contract_Validator::validate($multi_template, 3);
contract_assert($selected['ok'] === true, 'una entrega abierta valida sólo su plantilla #3');
$unknown_template = FLACSO_Mautic_Contract_Validator::validate($multi_template, 99);
contract_assert($unknown_template['ok'] === false, 'una plantilla no registrada bloquea la entrega');

FLACSO_Mautic_Client::set_http_transport(null);

echo "OK mautic-contract-validator-test\n";
