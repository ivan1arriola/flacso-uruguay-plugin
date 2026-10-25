<?php
define('ABSPATH', __DIR__ . '/');
define('WP_WEBHOOK_HMAC_SECRET', str_repeat('z', 40));
define('DAY_IN_SECONDS', 86400);
function esc_url_raw($value) { return $value; }
function sanitize_email($value) { return filter_var(trim((string) $value), FILTER_SANITIZE_EMAIL); }
function sanitize_text_field($value) { return trim(strip_tags((string) $value)); }
function wp_verify_nonce($nonce, $action) { return true; }
function current_time($format) { return '2026-10-17T14:00:00+00:00'; }
function get_transient($key) { return $GLOBALS['mailing_transients'][$key] ?? false; }
function set_transient($key, $value, $expiration) { $GLOBALS['mailing_transients'][$key] = $value; return true; }
function get_option($key, $default = false) {
    return ['flacso_inquiry_delivery_provider' => 'flacso_mail', 'flacso_mailing_base_url' => 'https://mailing.example.test'][$key] ?? $default;
}
$GLOBALS['mailing_transients'] = [];
require_once dirname(__DIR__) . '/includes/integrations/class-flacso-mailing-client.php';
require_once dirname(__DIR__) . '/modules/mailing/includes/class-flacso-mailing-subscription.php';
function subscription_assert(bool $ok, string $message): void {
    if (!$ok) { fwrite(STDERR, "FAIL: {$message}\n"); exit(1); }
}
$requests = [];
FLACSO_Mailing_Client::set_http_transport(function ($url, $args) use (&$requests) {
    $requests[] = [$url, $args]; return ['response' => ['code' => 202]];
});
$process = new ReflectionMethod('Flacso_Mailing_Subscription', 'process');
if (PHP_VERSION_ID < 80100) {
    $process->setAccessible(true);
}
$data = [
    'flacso_mailing_nonce' => 'valid', 'email' => ' ANA@example.org ',
    'first_name' => 'Ana', 'consent' => '1', 'submission_id' => str_repeat('a', 32),
];
$first = $process->invoke(null, $data);
$second = $process->invoke(null, $data);
subscription_assert($first['success'] && $second['success'], 'el evento de suscripción se acepta');
subscription_assert(count($requests) === 2, 'ambas entregas usan el endpoint firmado');
$event = json_decode($requests[0][1]['body'], true);
subscription_assert($event['event_type'] === 'contact.synced', 'emite contact.synced');
subscription_assert($event['idempotency_key'] === 'wp_sub_' . str_repeat('a', 32), 'clave estable por formulario');
subscription_assert($event['data']['email'] === 'ana@example.org', 'correo normalizado');
subscription_assert($event['data']['marketingConsent'] === [
    'granted' => true, 'acceptedAt' => '2026-10-17T14:00:00+00:00',
    'source' => 'shortcode_institucional', 'textVersion' => 'v1-2026',
], 'conserva evidencia explícita del consentimiento');
subscription_assert($event['data']['list_slugs'] === ['suscripcion-institucional'], 'solicita la lista institucional configurada');
subscription_assert($requests[0][1]['body'] === $requests[1][1]['body'], 'un reenvío conserva exactamente el cuerpo firmado');
$data['submission_id'] = 'invalid';
subscription_assert($process->invoke(null, $data)['success'] === false && count($requests) === 2, 'rechaza la clave de envío inválida');
FLACSO_Mailing_Client::set_http_transport(null);
echo "OK mailing subscription event contract\n";
