<?php
$root = dirname(__DIR__);

if (!defined('ABSPATH')) {
    define('ABSPATH', __DIR__ . '/');
}

$GLOBALS['worker_outage_options'] = [
    'flacso_inquiry_delivery_queue_enabled' => '1',
];

if (!function_exists('get_option')) {
    function get_option($key, $default = false) {
        return $GLOBALS['worker_outage_options'][$key] ?? $default;
    }
}
if (!function_exists('add_option')) {
    function add_option($key, $value, $deprecated = '', $autoload = 'yes') {
        if (array_key_exists($key, $GLOBALS['worker_outage_options'])) {
            return false;
        }
        $GLOBALS['worker_outage_options'][$key] = $value;
        return true;
    }
}
if (!function_exists('update_option')) {
    function update_option($key, $value, $autoload = null) {
        $GLOBALS['worker_outage_options'][$key] = $value;
        return true;
    }
}
if (!function_exists('delete_option')) {
    function delete_option($key) {
        unset($GLOBALS['worker_outage_options'][$key]);
        return true;
    }
}

require_once $root . '/includes/database/class-flacso-db.php';
require_once $root . '/includes/database/repositories/class-flacso-inquiry-delivery-repository.php';
require_once $root . '/modules/consultas/services/class-flacso-inquiry-delivery-service.php';
require_once $root . '/modules/consultas/services/class-flacso-inquiry-delivery-worker.php';

function outage_assert(bool $condition, string $message): void {
    if (!$condition) {
        fwrite(STDERR, "FAIL: {$message}\n");
        exit(1);
    }
}

final class FLACSO_Failing_PDO extends PDO {
    public function __construct() {}

    public function prepare(string $query, array $options = []): PDOStatement|false {
        throw new PDOException('SQLSTATE[08006] [7] connection timeout', 7);
    }
}

$repository = new FLACSO_Inquiry_Delivery_Repository(new FLACSO_Failing_PDO());
FLACSO_Inquiry_Delivery_Worker::set_repository($repository);

$result = FLACSO_Inquiry_Delivery_Worker::run();

outage_assert($result['ok'] === false, 'una caída de PostgreSQL no debe informar éxito');
outage_assert($result['status'] === 'database_unavailable', 'una caída de PostgreSQL debe quedar reintentable');
outage_assert($result['processed'] === 0, 'una caída de PostgreSQL no procesa entregas');
outage_assert(!isset($GLOBALS['worker_outage_options']['flacso_inquiry_delivery_worker_lock']), 'el lock debe liberarse tras la caída');

FLACSO_Inquiry_Delivery_Worker::set_repository(null);

echo "OK inquiry-delivery-worker-db-outage-test\n";
