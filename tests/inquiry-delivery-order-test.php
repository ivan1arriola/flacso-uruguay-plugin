<?php
$root = dirname(__DIR__);
require_once __DIR__ . '/support/inquiry-delivery-bootstrap.php';

if (!class_exists('WP_Error')) {
    class WP_Error {
        public function __construct(private string $message) {}
        public function get_error_message(): string { return $this->message; }
    }
}
if (!function_exists('is_wp_error')) {
    function is_wp_error($value): bool { return $value instanceof WP_Error; }
}

$GLOBALS['delivery_order_options'] = [
    'flacso_mautic_enabled' => '1',
    'flacso_mautic_base_url' => 'https://mautic.example.org',
    'flacso_mautic_auth_type' => 'basic',
    'flacso_mautic_username' => 'test',
    'flacso_mautic_password' => 'secret',
];
$GLOBALS['delivery_order_transients'] = [];

if (!function_exists('get_option')) {
    function get_option($key, $default = false) {
        return $GLOBALS['delivery_order_options'][$key] ?? $default;
    }
}
if (!function_exists('get_transient')) {
    function get_transient($key) {
        return $GLOBALS['delivery_order_transients'][$key] ?? false;
    }
}
if (!function_exists('set_transient')) {
    function set_transient($key, $value, $expiration): bool {
        $GLOBALS['delivery_order_transients'][$key] = $value;
        return true;
    }
}
if (!function_exists('delete_transient')) {
    function delete_transient($key): bool {
        unset($GLOBALS['delivery_order_transients'][$key]);
        return true;
    }
}

require_once $root . '/includes/database/class-flacso-db.php';
require_once $root . '/includes/database/repositories/class-flacso-inquiry-delivery-repository.php';
require_once $root . '/includes/integrations/class-flacso-mautic-client.php';
require_once $root . '/modules/consultas/services/class-flacso-mautic-contract-manifest.php';
require_once $root . '/modules/consultas/services/class-flacso-mautic-contract-validator.php';
require_once $root . '/modules/consultas/services/class-flacso-mautic-delivery-contract-cache.php';
require_once $root . '/modules/consultas/services/class-flacso-inquiry-delivery-service.php';

function delivery_order_assert(bool $condition, string $message): void {
    if (!$condition) {
        fwrite(STDERR, "FAIL: {$message}\n");
        exit(1);
    }
}

$pdo = flacso_test_delivery_pdo();
$repository = new FLACSO_Inquiry_Delivery_Repository($pdo);
FLACSO_Inquiry_Delivery_Service::set_repository($repository);

$pdo->exec("INSERT INTO inquiry_deliveries
    (id, inquiryType, inquiryId, consultaId, deliveryType, state, email, templateId, payloadJson, reportMonth, createdAt, updatedAt)
    VALUES ('invalid-json', 'offer', 'i1', 'c1', 'acknowledgement', 'processing', 'test@example.org', 4, '{invalid', '2026-10-01', '2026-10-01T00:00:00+00:00', '2026-10-01T00:00:00+00:00')");

$validator_calls = 0;
FLACSO_Inquiry_Delivery_Service::set_contract_validator(function () use (&$validator_calls): array {
    $validator_calls++;
    return ['ok' => true, 'status' => 'valid'];
});
$transport_calls = 0;
FLACSO_Mautic_Client::set_http_transport(function () use (&$transport_calls): array {
    $transport_calls++;
    return ['response' => ['code' => 500], 'body' => '{}'];
});

$invalid = FLACSO_Inquiry_Delivery_Service::process('invalid-json');
delivery_order_assert($invalid['status'] === 'failed', 'payload inválido debe fallar localmente');
delivery_order_assert($validator_calls === 0, 'payload inválido no debe validar contrato remoto');
delivery_order_assert($transport_calls === 0, 'payload inválido no debe llamar a Mautic');

$valid_payload = json_encode(['tokens' => ['{nombre}' => 'Ana']]);
$valid_snapshot = json_encode(['recipient' => ['email' => 'test@example.org']]);
$pdo->prepare("INSERT INTO inquiry_snapshots
    (id, inquiryType, inquiryId, consultaId, schemaVersion, snapshotJson, createdAt)
    VALUES ('s2', 'offer', 'i2', 'c2', '1', :snapshot, '2026-10-01T00:00:00+00:00')")
    ->execute([':snapshot' => $valid_snapshot]);
$pdo->prepare("INSERT INTO inquiry_deliveries
    (id, snapshotId, inquiryType, inquiryId, consultaId, deliveryType, state, email, templateId, payloadJson, reportMonth, createdAt, updatedAt)
    VALUES ('missing-identity', 's2', 'offer', 'i2', 'c2', 'acknowledgement', 'processing', 'test@example.org', 0, :payload, '2026-10-01', '2026-10-01T00:00:00+00:00', '2026-10-01T00:00:00+00:00')")
    ->execute([':payload' => $valid_payload]);

$missing_identity = FLACSO_Inquiry_Delivery_Service::process('missing-identity');
delivery_order_assert($missing_identity['status'] === 'blocked', 'identidad incompleta debe bloquear localmente');
delivery_order_assert($validator_calls === 0, 'identidad incompleta no debe validar contrato remoto');
delivery_order_assert($transport_calls === 0, 'identidad incompleta no debe llamar a Mautic');

 $contract_calls = 0;
FLACSO_Mautic_Client::set_http_transport(function (string $url) use (&$contract_calls): array {
    $contract_calls++;
    if (str_contains($url, '/api/emails/4')) {
        return ['response' => ['code' => 200], 'body' => json_encode(['email' => ['isPublished' => true]])];
    }
    return ['response' => ['code' => 404], 'body' => '{}'];
});
FLACSO_Mautic_Delivery_Contract_Cache::clear();
$contract_first = FLACSO_Mautic_Delivery_Contract_Cache::get(4);
delivery_order_assert($contract_first['ok'] === true, 'plantilla seleccionada publicada debe ser válida');
$contract_second = FLACSO_Mautic_Delivery_Contract_Cache::get(4);
delivery_order_assert($contract_second['ok'] === true, 'segunda validación debe conservar resultado válido');
delivery_order_assert($contract_calls === 1, 'la segunda validación cacheada no debe volver a llamar a Mautic');
delivery_order_assert(count($GLOBALS['delivery_order_transients']) === 1, 'resultado válido debe quedar cacheado');
$contract_forced = FLACSO_Mautic_Delivery_Contract_Cache::get(4, true);
delivery_order_assert($contract_forced['ok'] === true, 'la validación forzada debe conservar el contrato válido');
delivery_order_assert($contract_calls === 2, 'la validación forzada debe ignorar la caché anterior');

FLACSO_Mautic_Client::set_http_transport(null);
FLACSO_Inquiry_Delivery_Service::set_contract_validator(null);
FLACSO_Inquiry_Delivery_Service::set_repository(null);

echo "OK inquiry-delivery-order-test\n";
