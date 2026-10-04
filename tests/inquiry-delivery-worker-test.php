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
    function is_wp_error($value) { return $value instanceof WP_Error; }
}

$GLOBALS['worker_options'] = [
    'flacso_inquiry_delivery_queue_enabled' => '1',
    'flacso_mautic_enabled' => '1',
    'flacso_mautic_base_url' => 'https://mautic.example.org',
    'flacso_mautic_auth_type' => 'basic',
    'flacso_mautic_username' => 'test',
    'flacso_mautic_password' => 'secret',
];
if (!function_exists('get_option')) {
    function get_option($key, $default = false) {
        return $GLOBALS['worker_options'][$key] ?? $default;
    }
}
if (!function_exists('add_option')) {
    function add_option($key, $value, $deprecated = '', $autoload = 'yes') {
        if (array_key_exists($key, $GLOBALS['worker_options'])) {
            return false;
        }
        $GLOBALS['worker_options'][$key] = $value;
        return true;
    }
}
if (!function_exists('update_option')) {
    function update_option($key, $value, $autoload = null) {
        $GLOBALS['worker_options'][$key] = $value;
        return true;
    }
}
if (!function_exists('delete_option')) {
    function delete_option($key) {
        unset($GLOBALS['worker_options'][$key]);
        return true;
    }
}

require_once $root . '/includes/database/class-flacso-db.php';
require_once $root . '/includes/database/repositories/class-flacso-base-inquiry-repository.php';
require_once $root . '/includes/database/repositories/class-flacso-offer-inquiry-repository.php';
require_once $root . '/modules/consultas/services/class-flacso-inquiry-tag-factory.php';
require_once $root . '/modules/consultas/services/class-flacso-inquiry-snapshot.php';
require_once $root . '/modules/consultas/services/class-flacso-mautic-contract-manifest.php';
require_once $root . '/includes/database/repositories/class-flacso-inquiry-delivery-repository.php';
require_once $root . '/includes/integrations/class-flacso-mautic-client.php';
require_once $root . '/modules/consultas/services/class-flacso-mautic-contract-validator.php';
require_once $root . '/modules/consultas/services/class-flacso-inquiry-delivery-service.php';
require_once $root . '/modules/consultas/services/class-flacso-inquiry-delivery-worker.php';

function worker_assert(bool $condition, string $message): void {
    if (!$condition) {
        fwrite(STDERR, "FAIL: {$message}\n");
        exit(1);
    }
}

$pdo = flacso_test_delivery_pdo();
FLACSO_DB::set_connection($pdo);
$source = new FLACSO_Offer_Inquiry_Repository();
$repo = new FLACSO_Inquiry_Delivery_Repository($pdo);
FLACSO_Inquiry_Delivery_Worker::set_repository($repo);
FLACSO_Inquiry_Delivery_Service::set_repository($repo);
FLACSO_Inquiry_Delivery_Service::set_contract_validator(fn() => ['ok' => true, 'status' => 'valid']);

$form = ['nombre'=>'Ana','correo'=>'ana@example.org','programUrl'=>'https://flacso.edu.uy/a','inquiryAt'=>'2026-09-30T12:00:00+00:00'];
$ctx = ['offerWpId'=>10,'offerName'=>'A','offerAbbreviation'=>'A','offerType'=>'diploma','offerStatus'=>'abierta','startDate'=>'2027-01-01','startDatePrecision'=>'day','modality'=>'virtual'];
$snapshot = FLACSO_Inquiry_Snapshot::from_offer($form, $ctx, 'worker-1');
$result = $repo->persist_inquiry_with_delivery($source, [
    'consultaId'=>'worker-1','offerWpId'=>10,'offerName'=>'A','offerAbbreviation'=>'a',
    'firstName'=>'Ana','email'=>'ana@example.org','inquiryAt'=>'2026-09-30T12:00:00+00:00',
    'emailStatus'=>'pending','emailSender'=>'mautic_transactional_queue',
], $snapshot, 'offer');
$pdo->prepare('UPDATE inquiry_deliveries SET templateId=3, templateVersion=:v, templateSha256=:s WHERE id=:id')
    ->execute([':v'=>'test',':s'=>str_repeat('a',64),':id'=>$result['delivery_id']]);

$snapshot2 = FLACSO_Inquiry_Snapshot::from_offer(
    ['nombre'=>'Bea','correo'=>'bea@example.org','programUrl'=>'https://flacso.edu.uy/b','inquiryAt'=>'2026-09-30T12:01:00+00:00'],
    ['offerWpId'=>11,'offerName'=>'B','offerAbbreviation'=>'B','offerType'=>'diploma','offerStatus'=>'abierta','startDate'=>'2027-01-01','startDatePrecision'=>'day','modality'=>'virtual'],
    'worker-2'
);
$second = $repo->persist_inquiry_with_delivery($source, [
    'consultaId'=>'worker-2','offerWpId'=>11,'offerName'=>'B','offerAbbreviation'=>'b',
    'firstName'=>'Bea','email'=>'bea@example.org','inquiryAt'=>'2026-09-30T12:01:00+00:00',
    'emailStatus'=>'pending','emailSender'=>'mautic_transactional_queue',
], $snapshot2, 'offer');
$pdo->prepare('UPDATE inquiry_deliveries SET templateId=3, templateVersion=:v, templateSha256=:s WHERE id=:id')
    ->execute([':v'=>'test',':s'=>str_repeat('b',64),':id'=>$second['delivery_id']]);

$sends = 0;
FLACSO_Mautic_Client::set_http_transport(function($url, $args) use (&$sends) {
    if (str_contains($url, '/api/contacts?search=')) {
        return ['response'=>['code'=>200],'body'=>json_encode(['contacts'=>[['id'=>42]]])];
    }
    if (str_contains($url, '/api/contacts/42/edit')) {
        return ['response'=>['code'=>200],'body'=>json_encode(['contact'=>['id'=>42]])];
    }
    if (str_contains($url, '/send')) {
        $sends++;
        return ['response'=>['code'=>200],'body'=>json_encode(['success'=>true])];
    }
    return ['response'=>['code'=>404],'body'=>'{}'];
});

$run = FLACSO_Inquiry_Delivery_Worker::run(10);
worker_assert($run['status'] === 'completed' && $run['processed'] === 1, 'worker procesa lote');
worker_assert($sends === 1, 'worker envía una sola vez');
worker_assert($repo->find_by_delivery_id($result['delivery_id'])['state'] === 'accepted', 'worker deja accepted');
worker_assert($repo->find_by_delivery_id($second['delivery_id'])['state'] === 'pending', 'worker no reclama más de una entrega por ejecución');

$GLOBALS['worker_options']['flacso_inquiry_delivery_worker_lock'] = json_encode(['token'=>'other','expires'=>time()+120]);
$locked = FLACSO_Inquiry_Delivery_Worker::run(10);
worker_assert($locked['status'] === 'locked' && $locked['processed'] === 0, 'lock global evita ejecución paralela');
unset($GLOBALS['worker_options']['flacso_inquiry_delivery_worker_lock']);

$GLOBALS['worker_options']['flacso_inquiry_delivery_queue_enabled'] = '0';
$disabled = FLACSO_Inquiry_Delivery_Worker::run(10);
worker_assert($disabled['status'] === 'disabled', 'cola desactivada no procesa');
$GLOBALS['worker_options']['flacso_inquiry_delivery_queue_enabled'] = '1';

$expired_terminal = gmdate('c', time() - (91 * 86400));
$stmt = $pdo->prepare('UPDATE inquiry_deliveries SET terminalAt=:terminal_at, anonymizedAt=NULL');
$stmt->execute([':terminal_at' => $expired_terminal]);
$retention = FLACSO_Inquiry_Delivery_Worker::retention(90);
worker_assert($retention['anonymized'] === 1, 'worker ejecuta retención');

FLACSO_Mautic_Client::set_http_transport(null);
FLACSO_Inquiry_Delivery_Service::set_contract_validator(null);
FLACSO_Inquiry_Delivery_Service::set_repository(null);
FLACSO_Inquiry_Delivery_Worker::set_repository(null);

echo "OK inquiry-delivery-worker-test\n";
