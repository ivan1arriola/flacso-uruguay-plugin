<?php
define('ABSPATH', __DIR__ . '/');
define('WP_WEBHOOK_HMAC_SECRET', str_repeat('k', 40));
function esc_url_raw($value) { return $value; }
function get_option($key, $default = false) {
    return ['flacso_inquiry_delivery_provider' => 'flacso_mail', 'flacso_mailing_base_url' => 'https://mailing.example.test'][$key] ?? $default;
}
function wp_insert_post($post, $error = false) { $GLOBALS['general_post'] = $post; return 55120; }
function is_wp_error($value) { return false; }
function update_post_meta($id, $key, $value) { $GLOBALS['general_meta'][$key] = $value; return true; }
require_once dirname(__DIR__) . '/includes/integrations/class-flacso-mailing-client.php';
require_once dirname(__DIR__) . '/modules/formularios/includes/general-inquiry.php';
function general_mailing_assert(bool $ok, string $message): void {
    if (!$ok) { fwrite(STDERR, "FAIL: {$message}\n"); exit(1); }
}
$request = null;
FLACSO_Mailing_Client::set_http_transport(function ($url, $args) use (&$request) {
    $request = [$url, $args]; return ['response' => ['code' => 202]];
});
$result = fc_send_general_inquiry([
    'nombre' => 'Ana', 'apellido' => 'Pérez', 'email' => 'ANA@example.org',
    'asunto' => 'Consulta', 'mensaje' => 'Texto privado',
    'fecha_envio' => '2026-10-17T14:00:00-03:00', 'page_url' => 'https://flacso.edu.uy/contacto/',
]);
general_mailing_assert($result['ok'] && $result['consulta_id'] === 55120, 'consulta se guarda y acepta por Mailing');
general_mailing_assert(($GLOBALS['general_meta']['_fc_delivery_provider'] ?? '') === 'flacso_mail', 'proveedor queda registrado en WordPress');
$event = json_decode($request[1]['body'], true);
general_mailing_assert($event['event_type'] === 'general.inquiry.created', 'emite evento operativo');
general_mailing_assert($event['idempotency_key'] === 'wp_general_55120', 'clave estable basada en post persistido');
general_mailing_assert($event['data']['message_body'] === 'Texto privado', 'cuerpo se conserva para la notificación operativa');
general_mailing_assert($event['data']['visitor']['email'] === 'ana@example.org', 'normaliza correo');
general_mailing_assert(str_contains($request[0], '/api/v1/events/general-inquiries'), 'usa endpoint operativo');
$timestamp = $request[1]['headers']['X-Flacso-Timestamp'];
general_mailing_assert($request[1]['headers']['X-Flacso-Signature'] === 'sha256=' . hash_hmac('sha256', $timestamp . '.' . $request[1]['body'], WP_WEBHOOK_HMAC_SECRET), 'firma bytes exactos');
FLACSO_Mailing_Client::set_http_transport(function () { return ['response' => ['code' => 503]]; });
$uncertain = FLACSO_Mailing_Client::send_general_inquiry(55120, [
    'email' => 'ana@example.org', 'mensaje' => 'Texto privado',
]);
general_mailing_assert($uncertain['status'] === 'acceptance_unknown' && !empty($uncertain['acceptance_unknown']), '5xx conserva la consulta para reconciliar aceptación incierta');
FLACSO_Mailing_Client::set_http_transport(null);
echo "OK general inquiry Mailing contract\n";
