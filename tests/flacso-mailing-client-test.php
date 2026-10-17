<?php
if (!defined('ABSPATH')) define('ABSPATH', __DIR__ . '/');
if (!defined('WP_WEBHOOK_HMAC_SECRET')) define('WP_WEBHOOK_HMAC_SECRET', str_repeat('x', 40));
function esc_url_raw($value) { return $value; }
function get_option($key, $default = false) { return $key === 'flacso_mailing_base_url' ? 'https://mailing.example.test' : $default; }
require_once dirname(__DIR__) . '/includes/integrations/class-flacso-mailing-client.php';
function mailing_assert(bool $ok, string $message): void {
    if (!$ok) { fwrite(STDERR, "FAIL: {$message}\n"); exit(1); }
}
$request = null;
FLACSO_Mailing_Client::set_http_transport(function (string $url, array $args) use (&$request) {
    $request = [$url, $args];
    return ['response' => ['code' => 202]];
});
$snapshot = [
    'schemaVersion' => '1', 'consultaId' => 'query-42', 'inquiryType' => 'offer',
    'inquiryAt' => '2026-10-17T12:00:00Z', 'source' => 'web-consultas',
    'recipient' => ['email' => 'ana@example.org', 'firstName' => 'Ana'],
    'academic' => ['status' => 'abierta', 'targetId' => 'target_example'],
];
$result = FLACSO_Mailing_Client::send_academic_inquiry(['id' => 'delivery-42'], $snapshot,
    ['granted' => true, 'acceptedAt' => '2026-10-17T12:00:00Z', 'source' => 'web', 'textVersion' => 'v1']);
mailing_assert($result['ok'] === true && $result['status'] === 'accepted', '202 se acepta');
mailing_assert($request[0] === 'https://mailing.example.test/api/v1/events/academic-inquiries', 'usa endpoint canónico');
$event = json_decode($request[1]['body'], true);
mailing_assert($event['idempotency_key'] === 'wp_delivery_delivery-42', 'clave idempotente estable');
mailing_assert($request[1]['headers']['Idempotency-Key'] === $event['idempotency_key'], 'header y cuerpo coinciden');
mailing_assert($event['data']['snapshot'] === $snapshot, 'envía snapshot exacto');
mailing_assert($event['data']['followup_policy']['enabled'] === true, 'F2 requiere consentimiento y oferta abierta');
$timestamp = $request[1]['headers']['X-Flacso-Timestamp'];
mailing_assert($request[1]['headers']['X-Flacso-Signature'] === 'sha256=' . hash_hmac('sha256', $timestamp . '.' . $request[1]['body'], WP_WEBHOOK_HMAC_SECRET), 'firma cubre timestamp y bytes exactos');
FLACSO_Mailing_Client::set_http_transport(function (string $url, array $args) use (&$request) {
    $request = [$url, $args];
    return ['response' => ['code' => 503]];
});
$retry = FLACSO_Mailing_Client::send_academic_inquiry(['id' => 'delivery-42'], $snapshot, []);
mailing_assert($retry['status'] === 'retryable_failed', '5xx es reintentable');
$retry_event = json_decode($request[1]['body'], true);
mailing_assert($retry_event['data']['marketingConsent'] === ['granted' => false], 'no inventa consentimiento');
mailing_assert($retry_event['data']['followup_policy']['enabled'] === false, 'sin consentimiento no hay F2');
FLACSO_Mailing_Client::set_http_transport(function () { return ['response' => ['code' => 409]]; });
$conflict = FLACSO_Mailing_Client::send_academic_inquiry(['id' => 'delivery-42'], $snapshot, []);
mailing_assert($conflict['status'] === 'failed', '409 es conflicto definitivo');
FLACSO_Mailing_Client::set_http_transport(null);
echo "PASS: FLACSO Mailing client signed event contract\n";
