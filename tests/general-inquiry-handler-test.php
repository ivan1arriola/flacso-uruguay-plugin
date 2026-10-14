<?php
define('ABSPATH', __DIR__ . '/');
define('MINUTE_IN_SECONDS', 60);
class Redirect extends Exception {}
$GLOBALS['options'] = []; $GLOBALS['transients'] = []; $GLOBALS['deliveries'] = 0;
function check($ok, $message) { if (!$ok) { fwrite(STDERR, "FAIL: $message\n"); exit(1); } }
function add_action(...$args) {}
function wp_verify_nonce(...$args) { return true; }
function wp_unslash($value) { return $value; }
function sanitize_text_field($value) { return trim($value); }
function sanitize_email($value) { return trim($value); }
function wp_kses_post($value) { return strip_tags($value); }
function wp_strip_all_tags($value) { return strip_tags($value); }
function is_email($value) { return filter_var($value, FILTER_VALIDATE_EMAIL); }
function fc_is_spam_content(...$args) { return false; }
function get_option($key, $default = false) { return $GLOBALS['options'][$key] ?? $default; }
function add_option($key, $value, ...$args) { if (isset($GLOBALS['options'][$key])) return false; $GLOBALS['options'][$key] = $value; if (!empty($GLOBALS['simulate_finished_concurrent_request'])) $GLOBALS['transients'][substr($key, 0, -5)] = 1; return true; }
function delete_option($key) { unset($GLOBALS['options'][$key]); }
function get_transient($key) { return $GLOBALS['transients'][$key] ?? false; }
function set_transient($key, $value, $ttl) { $GLOBALS['transients'][$key] = $value; }
function delete_transient($key) { unset($GLOBALS['transients'][$key]); }
function wp_get_referer() { return 'https://flacso.test/contacto/'; }
function home_url() { return 'https://flacso.test/'; }
function fc_get_gracias_url_from_referer() { return 'https://flacso.test/confirmacion-consulta/'; }
function add_query_arg($key, $value, $url = null) { if (is_array($key)) return $value . '?' . http_build_query($key); return $url . '?' . http_build_query([$key => $value]); }
function wp_safe_redirect($url) { throw new Redirect($url); }
function wp_generate_uuid4() { return 'test-inquiry'; }
function current_time($format) { return '2026-10-14T15:00:00Z'; }
function fc_form_error_reference($form) { return 'TEST-REFERENCE'; }
function fc_notify_form_admin_error($data) { $GLOBALS['incident'] = $data; }
function fc_send_general_inquiry($data) { $GLOBALS['deliveries']++; $GLOBALS['payload'] = $data; return $GLOBALS['delivery_result']; }
function fc_dispatch_info_request_webhook(...$args) { throw new Exception('Legacy editor must not be called'); }
require dirname(__DIR__) . '/modules/formularios/includes/form-handlers.php';
$_POST = ['fc_nonce' => 'valid', 'fc_nombre' => 'Ana', 'fc_apellido' => 'Pérez', 'fc_email' => 'ana@example.org', 'fc_telefono' => '+59899123456', 'fc_asunto' => 'Horarios', 'fc_mensaje' => 'Quisiera información'];
$_SERVER['REMOTE_ADDR'] = '127.0.0.1';
function submit() { try { fc_handle_form_submit(); } catch (Redirect $redirect) { return $redirect->getMessage(); } throw new Exception('No redirect'); }
$GLOBALS['delivery_result'] = ['ok' => true];
check(strpos(submit(), 'confirmacion-consulta') !== false, 'Successful Mautic handoff confirms the consultation');
check($GLOBALS['deliveries'] === 1 && $GLOBALS['payload']['email'] === 'ana@example.org', 'Form submits contact data to Mautic processing');
check($GLOBALS['payload']['reference'] === 'TEST-REFERENCE' && !empty($GLOBALS['payload']['delivery_lock']), 'Tracking reference and lock identity accompany the saved consultation');
check(strpos(submit(), 'fc_exito=1') !== false && $GLOBALS['deliveries'] === 1, 'Repeated successful submission does not send twice');
$GLOBALS['transients'] = []; $GLOBALS['delivery_result'] = ['ok' => false, 'acceptance_unknown' => false];
check(strpos(submit(), 'fc_error=unavailable') !== false && empty($GLOBALS['options']), 'Definite failure releases the lock and offers a retry');
$GLOBALS['transients'] = []; $GLOBALS['delivery_result'] = ['ok' => false, 'acceptance_unknown' => true];
check(strpos(submit(), 'fc_error=unconfirmed') !== false, 'Unknown delivery cannot show success');
$attempts = $GLOBALS['deliveries'];
check(strpos(submit(), 'fc_error=unconfirmed') !== false && $GLOBALS['deliveries'] === $attempts, 'Unknown delivery blocks resending until reconciliation');
$GLOBALS['transients'] = []; $GLOBALS['options'] = []; $GLOBALS['simulate_finished_concurrent_request'] = true;
$attempts = $GLOBALS['deliveries'];
check(strpos(submit(), 'fc_exito=1') !== false && $GLOBALS['deliveries'] === $attempts && empty($GLOBALS['options']), 'Completed concurrent request cannot cause a second delivery');
echo "OK general inquiry form handoff and duplicate protection\n";
