<?php
$root = dirname(__DIR__);
if (!defined('ABSPATH')) {
    define('ABSPATH', __DIR__ . '/');
}
$GLOBALS['consent_options'] = [
    'flacso_mautic_enabled'=>'1',
    'flacso_mautic_base_url'=>'https://mautic.example.org',
    'flacso_mautic_auth_type'=>'basic',
    'flacso_mautic_username'=>'test',
    'flacso_mautic_password'=>'secret',
    'flacso_mautic_campaign_enabled'=>'1',
    'flacso_mautic_campaign_consultas_id'=>2,
];
if (!function_exists('get_option')) {
    function get_option($key, $default=false) { return $GLOBALS['consent_options'][$key] ?? $default; }
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

require_once $root . '/includes/database/class-flacso-db.php';
require_once $root . '/includes/database/repositories/class-flacso-base-inquiry-repository.php';
require_once $root . '/includes/database/repositories/class-flacso-offer-inquiry-repository.php';
require_once $root . '/includes/integrations/class-flacso-mautic-client.php';
require_once $root . '/modules/consultas/services/class-flacso-inquiry-tag-factory.php';
require_once $root . '/modules/consultas/services/class-flacso-inquiry-snapshot.php';
require_once $root . '/modules/consultas/services/class-flacso-mautic-payload-builder.php';
require_once $root . '/modules/consultas/services/class-flacso-inquiry-marketing-service.php';

function consent_assert(bool $condition, string $message): void {
    if (!$condition) {
        fwrite(STDERR, "FAIL: {$message}\n");
        exit(1);
    }
}

$snapshot = FLACSO_Inquiry_Snapshot::from_offer(
    ['firstName'=>'Ana','lastName'=>'Pérez','email'=>'ana@example.org','country'=>'Uruguay'],
    ['offerWpId'=>10,'offerName'=>'DAVIA','offerAbbreviation'=>'DAVIA','offerType'=>'diploma','cohortNumber'=>10,'offerStatus'=>'abierta'],
    'consent-1'
);

$calls = [];
FLACSO_Mautic_Client::set_http_transport(function($url,$args) use (&$calls) {
    $calls[] = ['url'=>$url,'args'=>$args];
    if (str_contains($url, '/api/contacts?search=')) {
        return ['response'=>['code'=>200],'body'=>json_encode(['contacts'=>[]])];
    }
    if (str_contains($url, '/api/contacts/new')) {
        return ['response'=>['code'=>201],'body'=>json_encode(['contact'=>['id'=>42]])];
    }
    if (str_contains($url, '/api/contacts/42/campaigns')) {
        return ['response'=>['code'=>200],'body'=>json_encode(['campaigns'=>[]])];
    }
    if (str_contains($url, '/api/campaigns/2/contact/42/add')) {
        return ['response'=>['code'=>200],'body'=>json_encode(['success'=>true])];
    }
    return ['response'=>['code'=>404],'body'=>'{}'];
});

$no = FLACSO_Inquiry_Marketing_Service::sync_commercial_contact($snapshot, []);
consent_assert($no['status'] === 'skipped' && $no['reason'] === 'consent_required', 'sin consentimiento omite marketing');
consent_assert(count($calls) === 0, 'sin consentimiento no toca Mautic');

$partial = FLACSO_Inquiry_Marketing_Service::sync_commercial_contact($snapshot, [
    'granted'=>true,'acceptedAt'=>'2026-09-30T12:00:00Z','source'=>'web'
]);
consent_assert($partial['status'] === 'skipped', 'consentimiento incompleto no es apto');
consent_assert(count($calls) === 0, 'consentimiento incompleto no toca Mautic');

$ok = FLACSO_Inquiry_Marketing_Service::sync_commercial_contact($snapshot, [
    'granted'=>true,
    'acceptedAt'=>'2026-09-30T12:00:00Z',
    'source'=>'web-consultas',
    'textVersion'=>'2026-09',
]);
consent_assert($ok['status'] === 'synced', 'consentimiento completo sincroniza');
consent_assert($ok['campaign']['status'] === 'joined', 'consentimiento completo permite campaña');

$create = null;
foreach ($calls as $call) {
    if (str_contains($call['url'], '/api/contacts/new')) {
        $create = json_decode((string) ($call['args']['body'] ?? '{}'), true);
    }
}
consent_assert(is_array($create), 'se creó contacto');
consent_assert(($create['tags'] ?? []) === ['interes-davia','davia-c10','origen-web-consultas'], 'tags acumulativos canónicos');
consent_assert(!isset($create['flacso_consulta_texto']), 'texto libre nunca sale a Mautic');
consent_assert(!isset($create['flacso_oferta_nombre']), 'snapshot de consulta no se guarda en contacto');

FLACSO_Mautic_Client::set_http_transport(null);
echo "OK inquiry-marketing-consent-test\n";
