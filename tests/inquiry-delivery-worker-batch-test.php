<?php
$root = dirname(__DIR__);
require_once __DIR__ . '/support/inquiry-delivery-bootstrap.php';

if (!function_exists('get_option')) {
    function get_option($key, $default = false) {
        return $GLOBALS['batch_options'][$key] ?? $default;
    }
}
if (!function_exists('add_option')) {
    function add_option($key, $value, $deprecated = '', $autoload = 'yes') {
        if (array_key_exists($key, $GLOBALS['batch_options'])) {
            return false;
        }
        $GLOBALS['batch_options'][$key] = $value;
        return true;
    }
}
if (!function_exists('update_option')) {
    function update_option($key, $value, $autoload = null) {
        $GLOBALS['batch_options'][$key] = $value;
        return true;
    }
}
if (!function_exists('delete_option')) {
    function delete_option($key) {
        unset($GLOBALS['batch_options'][$key]);
        return true;
    }
}
if (!class_exists('WP_Error')) {
    class WP_Error {
        private $message;
        public function __construct(string $message) { $this->message = $message; }
        public function get_error_message(): string { return $this->message; }
    }
}
if (!function_exists('is_wp_error')) {
    function is_wp_error($value): bool { return $value instanceof WP_Error; }
}

$GLOBALS['batch_options'] = [
    'flacso_inquiry_delivery_queue_enabled' => '1',
    'flacso_mautic_enabled' => '1',
    'flacso_mautic_base_url' => 'https://mautic.example.org',
    'flacso_mautic_auth_type' => 'basic',
    'flacso_mautic_username' => 'test',
    'flacso_mautic_password' => 'secret',
];

require_once $root . '/includes/database/class-flacso-db.php';
require_once $root . '/includes/database/repositories/class-flacso-inquiry-delivery-repository.php';
require_once $root . '/includes/integrations/class-flacso-mautic-client.php';
require_once $root . '/modules/consultas/services/class-flacso-inquiry-delivery-service.php';
require_once $root . '/modules/consultas/services/class-flacso-inquiry-delivery-worker.php';

function batch_assert(bool $condition, string $message): void {
    if (!$condition) {
        fwrite(STDERR, "FAIL: {$message}\n");
        exit(1);
    }
}

$pdo = flacso_test_delivery_pdo();
$repo = new FLACSO_Inquiry_Delivery_Repository($pdo);
FLACSO_Inquiry_Delivery_Worker::set_repository($repo);
FLACSO_Inquiry_Delivery_Service::set_repository($repo);
FLACSO_Inquiry_Delivery_Service::set_contract_validator(fn() => ['ok' => true, 'status' => 'valid']);

for ($i = 1; $i <= 11; $i++) {
    $snapshot_id = 'batch-snapshot-' . $i;
    $delivery_id = 'batch-delivery-' . $i;
    $email = 'batch-' . $i . '@example.org';
    $pdo->prepare('INSERT INTO inquiry_snapshots (id, inquiryType, inquiryId, consultaId, schemaVersion, snapshotJson, createdAt)
        VALUES (:id, "offer", :inquiry, :consulta, "1", :snapshot, "2026-10-01T00:00:00+00:00")')
        ->execute([
            ':id' => $snapshot_id,
            ':inquiry' => 'batch-inquiry-' . $i,
            ':consulta' => 'batch-consulta-' . $i,
            ':snapshot' => json_encode(['recipient' => ['email' => $email, 'firstName' => 'Ana', 'lastName' => 'Batch']]),
        ]);
    $pdo->prepare('INSERT INTO inquiry_deliveries
        (id, snapshotId, inquiryType, inquiryId, consultaId, deliveryType, state, email, templateId, templateVersion, payloadJson, reportMonth, createdAt, updatedAt)
        VALUES (:id, :snapshot_id, "offer", :inquiry, :consulta, "acknowledgement", "pending", :email, 3, "test", :payload, "2026-10-01", "2026-10-01T00:00:00+00:00", "2026-10-01T00:00:00+00:00")')
        ->execute([
            ':id' => $delivery_id,
            ':snapshot_id' => $snapshot_id,
            ':inquiry' => 'batch-inquiry-' . $i,
            ':consulta' => 'batch-consulta-' . $i,
            ':email' => $email,
            ':payload' => json_encode(['tokens' => ['{nombre}' => 'Ana']]),
        ]);
}

$sends = 0;
FLACSO_Mautic_Client::set_http_transport(function (string $url) use (&$sends): array {
    if (str_contains($url, '/api/contacts?search=')) {
        return ['response' => ['code' => 200], 'body' => json_encode(['contacts' => [['id' => 42]]])];
    }
    if (str_contains($url, '/api/contacts/42/edit')) {
        return ['response' => ['code' => 200], 'body' => json_encode(['contact' => ['id' => 42]])];
    }
    if (str_contains($url, '/send')) {
        $sends++;
        return ['response' => ['code' => 200], 'body' => json_encode(['success' => true])];
    }
    return ['response' => ['code' => 404], 'body' => '{}'];
});

$result = FLACSO_Inquiry_Delivery_Worker::run(99);
batch_assert($result['status'] === 'completed', 'el worker debe completar el lote');
batch_assert($result['processed'] === 10, 'el worker debe procesar como máximo diez entregas');
batch_assert($sends === 10, 'el lote debe enviar diez correos');
batch_assert($repo->find_by_delivery_id('batch-delivery-11')['state'] === 'pending', 'la undécima entrega debe quedar pendiente');
batch_assert(empty($GLOBALS['batch_options']['flacso_inquiry_delivery_worker_lock']), 'el lock debe liberarse al completar el lote');

FLACSO_Mautic_Client::set_http_transport(null);
FLACSO_Inquiry_Delivery_Service::set_contract_validator(null);
FLACSO_Inquiry_Delivery_Service::set_repository(null);
FLACSO_Inquiry_Delivery_Worker::set_repository(null);

echo "OK inquiry-delivery-worker-batch-test\n";
