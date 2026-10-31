<?php
// Una sola conexión con Telegram: el bot de las alertas anteriores se migra y no queda duplicada.
define('ABSPATH', __DIR__ . '/');
$GLOBALS['opts'] = [];
function get_option($key, $default = false) { return array_key_exists($key, $GLOBALS['opts']) ? $GLOBALS['opts'][$key] : $default; }
function update_option($key, $value) { $GLOBALS['opts'][$key] = $value; return true; }
function delete_option($key) { unset($GLOBALS['opts'][$key]); return true; }
function check($condition, $message) { if (!$condition) { fwrite(STDERR, "FAIL: $message\n"); exit(1); } }
require dirname(__DIR__) . '/includes/core/class-flacso-telegram.php';

check(!FLACSO_Telegram::is_configured(), 'Sin credenciales no está configurado');

$GLOBALS['opts'] = ['flacso_error_alert_telegram_bot_token' => '1:old', 'flacso_error_alert_telegram_chat_id' => '-10', 'fc_use_telegram' => '1', 'flacso_preinscripciones_telegram_bot_token' => 'x'];
check(FLACSO_Telegram::is_configured() && FLACSO_Telegram::credentials()['token'] === '1:old', 'Antes de migrar se leen las credenciales de las alertas');
FLACSO_Telegram::migrate_legacy_options();
check($GLOBALS['opts']['fc_telegram_bot_token'] === '1:old' && $GLOBALS['opts']['fc_telegram_chat_id'] === '-10', 'La migración copia bot y chat a la conexión única');
check(array_keys($GLOBALS['opts']) === ['fc_telegram_bot_token', 'fc_telegram_chat_id'], 'La migración borra las opciones anteriores');

$GLOBALS['opts'] = ['fc_telegram_bot_token' => '2:new', 'fc_telegram_chat_id' => '-20', 'flacso_error_alert_telegram_bot_token' => '1:old', 'flacso_error_alert_telegram_chat_id' => '-10'];
FLACSO_Telegram::migrate_legacy_options();
check($GLOBALS['opts']['fc_telegram_bot_token'] === '2:new' && !isset($GLOBALS['opts']['flacso_error_alert_telegram_bot_token']), 'La conexión actual gana sobre la anterior');

$sent = [];
function wp_remote_post($url, $args) { $GLOBALS['sent'][] = [$url, $args]; return ['response' => ['code' => 200]]; }
function wp_remote_retrieve_response_code($r) { return $r['response']['code']; }
function is_wp_error($v) { return false; }
check(FLACSO_Telegram::send('hola', true) === true, 'Envía con la conexión única');
check(strpos($GLOBALS['sent'][0][0], 'bot2%3Anew') !== false && $GLOBALS['sent'][0][1]['body']['parse_mode'] === 'HTML' && $GLOBALS['sent'][0][1]['body']['chat_id'] === '-20', 'Usa el bot y el chat configurados');
echo "OK telegram connection\n";
