<?php
// Boundary test: the real client must produce an isolated email with BCC and
// the visitor's Reply-To, and never deliver the notification to the visitor.
define('ABSPATH', __DIR__ . '/');
$GLOBALS['options'] = ['flacso_mautic_enabled' => '1', 'flacso_mautic_auth_type' => 'bearer', 'flacso_mautic_token' => 'test-token'];
function get_option($key, $default = false) { return $GLOBALS['options'][$key] ?? $default; }
function is_wp_error($value) { return $value instanceof WP_Error; }
class WP_Error { public function get_error_message() { return 'Transport unavailable'; } }
function check($condition, $message) { if (!$condition) { fwrite(STDERR, "FAIL: $message\n"); exit(1); } }
require dirname(__DIR__) . '/includes/integrations/class-flacso-mautic-client.php';
check(method_exists('FLACSO_Mautic_Client', 'send_general_inquiry_notification'), 'General inquiries must support Mautic delivery');
$calls = [];
$sendMode = 'success';
FLACSO_Mautic_Client::set_http_transport(function ($url, $args) use (&$calls, &$sendMode) {
    $calls[] = ['url' => $url, 'args' => $args];
    $body = ['success' => true];
    if (strpos($url, '/api/contacts?') !== false) $body = ['contacts' => ['41' => ['id' => 41, 'email' => 'inscripciones@flacso.edu.uy']]];
    if (strpos($url, '/api/emails/new') !== false) $body = ['email' => ['id' => 73]];
    if (strpos($url, '/send') !== false) {
        if ($sendMode === 'timeout') return new WP_Error();
        if ($sendMode === 'rejected') $body = ['success' => false];
        if ($sendMode === 'malformed') $body = [];
        if ($sendMode === 'gateway') return ['response' => ['code' => 504], 'body' => 'Gateway timeout'];
    }
    return ['response' => ['code' => 200], 'body' => json_encode($body)];
});
$inquiry = ['event_id' => 'test-inquiry-1', 'nombre' => 'Ana', 'apellido' => 'Pérez', 'email' => 'ana@example.org', 'telefono' => '+59899123456', 'asunto' => 'Horarios', 'mensaje' => "¿Qué horarios tienen?\n<script>alert(1)</script>"];
$result = FLACSO_Mautic_Client::send_general_inquiry_notification($inquiry);
check($result['ok'] === true && $result['email_id'] === 73, 'Confirmed delivery returns its email identity');
$created = array_values(array_filter($calls, fn($call) => strpos($call['url'], '/emails/new') !== false));
check(count($created) === 1, 'Each inquiry creates one independent message');
$mail = json_decode($created[0]['args']['body'], true);
check($mail['bccAddress'] === 'web@flacso.edu.uy', 'Web receives a blind copy');
check($mail['replyToAddress'] === 'ana@example.org', 'Reply goes to the visitor');
check(strpos($mail['subject'], 'Horarios') !== false, 'Visitor subject is preserved');
check(strpos($mail['plainText'], '+59899123456') !== false && strpos($mail['plainText'], 'Ana Pérez') !== false, 'Contact details are included');
check(strpos($mail['customHtml'], '<script>') === false && strpos($mail['customHtml'], '&lt;script&gt;') !== false, 'HTML from the visitor is escaped');
check($mail['emailType'] === 'template' && $mail['publicPreview'] === false, 'Notification is private and is not a broadcast');
$sent = array_values(array_filter($calls, fn($call) => strpos($call['url'], '/send') !== false));
check(count($sent) === 1 && strpos($sent[0]['url'], '/emails/73/contact/41/send') !== false, 'Only Inscripciones receives the notification');
$calls = [];
$bad = $inquiry; $bad['email'] = "ana@example.org\r\nBcc:other@example.org";
check(FLACSO_Mautic_Client::send_general_inquiry_notification($bad)['ok'] === false && count($calls) === 0, 'Header injection is rejected before HTTP');
$sendMode = 'rejected';
check(FLACSO_Mautic_Client::send_general_inquiry_notification($inquiry)['ok'] === false, 'A success=false response cannot confirm delivery');
$sendMode = 'timeout';
$unknown = FLACSO_Mautic_Client::send_general_inquiry_notification($inquiry);
check($unknown['ok'] === false && $unknown['acceptance_unknown'] === true, 'Lost send response requires reconciliation');
$sendMode = 'malformed';
$unknown = FLACSO_Mautic_Client::send_general_inquiry_notification($inquiry);
check($unknown['ok'] === false && $unknown['acceptance_unknown'] === true, 'Unconfirmed HTTP 200 cannot authorize resending');
$sendMode = 'gateway';
$unknown = FLACSO_Mautic_Client::send_general_inquiry_notification($inquiry);
check($unknown['ok'] === false && $unknown['acceptance_unknown'] === true, 'Gateway failure after handoff cannot authorize resending');
$calls = []; $GLOBALS['options']['flacso_mautic_enabled'] = '0';
check(FLACSO_Mautic_Client::send_general_inquiry_notification($inquiry)['ok'] === false && count($calls) === 0, 'Disabled Mautic fails without another provider');
echo "OK general inquiry Mautic notification\n";

$service = dirname(__DIR__) . '/modules/formularios/includes/general-inquiry.php';
check(is_file($service), 'General inquiries must be saved before Mautic delivery');
function wp_insert_post($post, $error = false) { $GLOBALS['saved_post'] = $post; return $GLOBALS['insert_error'] ?? 101; }
function update_post_meta($id, $key, $value) { $GLOBALS['saved_meta'][$key] = $value; return true; }
require $service;
$GLOBALS['options']['flacso_mautic_enabled'] = '1'; $sendMode = 'success';
$saved = fc_send_general_inquiry($inquiry);
check($saved['ok'] === true && $saved['consulta_id'] === 101, 'Delivery retains a local consultation identity');
check($GLOBALS['saved_post']['post_type'] === 'fc_consulta' && $GLOBALS['saved_post']['post_status'] === 'private', 'Consultations are stored privately in WordPress');
check($GLOBALS['saved_post']['post_content'] === $inquiry['mensaje'], 'Original inquiry is preserved locally');
check($GLOBALS['saved_post']['meta_input']['fc_email'] === 'ana@example.org', 'Saved email is available in the existing consultation admin');
check($GLOBALS['saved_meta']['_fc_delivery_status'] === 'accepted', 'Confirmed Mautic acceptance is recorded');
$sendMode = 'timeout';
check(fc_send_general_inquiry($inquiry)['acceptance_unknown'] === true && $GLOBALS['saved_meta']['_fc_delivery_status'] === 'acceptance_unknown', 'Ambiguous delivery is saved for manual reconciliation');
$calls = []; $GLOBALS['insert_error'] = new WP_Error();
check(fc_send_general_inquiry($inquiry)['ok'] === false && count($calls) === 0, 'Failed local persistence prevents sending');
echo "OK general inquiry local persistence\n";
