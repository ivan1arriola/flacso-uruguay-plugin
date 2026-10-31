<?php
// Boundary test: the real client must produce an isolated email with
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
check(!isset($mail['bccAddress']), 'No hidden copy is hardcoded');
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
check(is_file($service), 'General inquiry orchestration exists');
function sanitize_email($value) { return filter_var(trim((string) $value), FILTER_VALIDATE_EMAIL) ?: ''; }
function is_email($value) { return filter_var($value, FILTER_VALIDATE_EMAIL) !== false; }
function fc_can_use_telegram() { return !empty($GLOBALS['telegram_ready']); }
function fc_send_telegram_message($text) { $GLOBALS['telegram_messages'][] = $text; return empty($GLOBALS['telegram_fails']); }
function wp_insert_post() { $GLOBALS['stored'] = true; return 1; }
require $service;

// Recepción configurable, con respuesta al consultante; acuse con respuesta a recepción.
$GLOBALS['options']['fc_destinatario_email'] = 'consultas@flacso.edu.uy'; $GLOBALS['options']['flacso_mautic_enabled'] = '1';
$sendMode = 'success'; $calls = [];
$ctx = ['mail' => []];
FLACSO_Mautic_Client::set_http_transport(function ($url, $args) use (&$calls) {
    $calls[] = ['url' => $url, 'args' => $args];
    $body = ['success' => true];
    if (strpos($url, '/api/contacts?') !== false) $body = ['contacts' => ['41' => ['id' => 41, 'email' => 'consultas@flacso.edu.uy'], '42' => ['id' => 42, 'email' => 'ana@example.org']]];
    if (strpos($url, '/api/emails/new') !== false) $body = ['email' => ['id' => 90 + count($calls)]];
    return ['response' => ['code' => 200], 'body' => json_encode($body)];
});
$GLOBALS['telegram_ready'] = true;
$result = fc_send_general_inquiry($inquiry);
check($result['ok'] === true && $result['acuse'] === 'sent' && $result['telegram'] === 'sent', 'Recepción, acuse y Telegram se completan');
$emails = array_values(array_filter($calls, fn($c) => strpos($c['url'], '/api/emails/new') !== false));
check(count($emails) === 2, 'Cada consulta crea exactamente dos correos');
$reception = json_decode($emails[0]['args']['body'], true);
$ack = json_decode($emails[1]['args']['body'], true);
check($reception['replyToAddress'] === 'ana@example.org', 'Recepción responde al consultante');
check($ack['replyToAddress'] === 'consultas@flacso.edu.uy', 'El acuse responde a recepción');
check(strpos($ack['plainText'], 'Ana') !== false && strpos($ack['plainText'], 'Horarios') !== false, 'El acuse usa nombre y asunto');
check(!isset($GLOBALS['stored']), 'La consulta no se guarda en WordPress');
check(strpos($GLOBALS['telegram_messages'][0], 'Horarios') !== false && strpos($GLOBALS['telegram_messages'][0], '&lt;script&gt;') !== false, 'Telegram resume la consulta y escapa HTML');

// Sin acuse si recepción no confirmó; Telegram avisa igual.
$GLOBALS['options']['fc_enviar_acuse'] = '1'; $calls = []; $GLOBALS['telegram_messages'] = [];
FLACSO_Mautic_Client::set_http_transport(function ($url, $args) use (&$calls) {
    $calls[] = $url;
    if (strpos($url, '/api/contacts?') !== false) return ['response' => ['code' => 200], 'body' => json_encode(['contacts' => ['41' => ['id' => 41, 'email' => 'consultas@flacso.edu.uy']]])];
    if (strpos($url, '/api/emails/new') !== false) return ['response' => ['code' => 200], 'body' => json_encode(['email' => ['id' => 7]])];
    return ['response' => ['code' => 200], 'body' => json_encode(['success' => false])];
});
$failed = fc_send_general_inquiry($inquiry);
check($failed['ok'] === false && $failed['acuse'] === 'skipped', 'Sin recepción confirmada no hay acuse');
check(count($GLOBALS['telegram_messages']) === 1 && strpos($GLOBALS['telegram_messages'][0], 'revisar') !== false, 'Telegram avisa aunque el correo falle');

// Ajustes: acuse y Telegram se pueden apagar; sin Telegram configurado se omite.
$GLOBALS['options']['fc_enviar_acuse'] = '0'; $GLOBALS['options']['fc_telegram_notify_consultas'] = '0';
$off = fc_send_general_inquiry($inquiry);
check($off['acuse'] === 'skipped' && $off['telegram'] === 'skipped', 'Acuse y Telegram respetan los ajustes');
$GLOBALS['options']['fc_telegram_notify_consultas'] = '1'; $GLOBALS['telegram_ready'] = false;
check(fc_send_general_inquiry($inquiry)['telegram'] === 'skipped', 'Telegram sin configurar se omite');

// Recepción por defecto si el ajuste está vacío.
$GLOBALS['options']['fc_destinatario_email'] = '';
check(fc_get_reception_email() === 'inscripciones@flacso.edu.uy', 'Sin ajuste se conserva la dirección histórica');
echo "OK general inquiry flow\n";
