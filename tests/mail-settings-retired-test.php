<?php
// FLACSO > Correos sólo configura Mautic: sin proveedor alterno, plantillas F1/F2 ni seguimiento automático.
define('ABSPATH', __DIR__ . '/');
$GLOBALS['registered'] = [];
$GLOBALS['opts'] = ['flacso_inquiry_delivery_provider' => 'flacso_mail', 'flacso_mailing_base_url' => 'https://x.example', 'flacso_inquiry_followup_enabled' => '1', 'flacso_inquiry_followup_days' => 5, 'flacso_mautic_enabled' => '1'];
function register_setting($group, $option, $args) { $GLOBALS['registered'][$option] = true; }
function get_option($key, $default = false) { return array_key_exists($key, $GLOBALS['opts']) ? $GLOBALS['opts'][$key] : $default; }
function delete_option($key) { unset($GLOBALS['opts'][$key]); return true; }
function esc_url_raw($v) { return $v; }
function check($ok, $message) { if (!$ok) { fwrite(STDERR, "FAIL: $message\n"); exit(1); } }
require dirname(__DIR__) . '/modules/mailing/includes/class-flacso-mail-settings.php';
require dirname(__DIR__) . '/includes/integrations/class-flacso-mailing-client.php';

FLACSO_Mail_Settings::register_settings();
foreach (['flacso_inquiry_delivery_provider', 'flacso_mailing_base_url', 'flacso_inquiry_followup_enabled', 'flacso_inquiry_followup_days'] as $retired) {
    check(!isset($GLOBALS['registered'][$retired]), "$retired ya no se registra");
}
check(isset($GLOBALS['registered']['flacso_mautic_base_url']), 'La conexión con Mautic sigue registrada');
check(!array_key_exists('delivery_provider', FLACSO_Mail_Settings::get_settings()), 'get_settings no expone proveedor');

FLACSO_Mail_Settings::remove_retired_options();
check(array_keys($GLOBALS['opts']) === ['flacso_mautic_enabled'], 'Se borran las opciones retiradas y se conserva Mautic');
check(FLACSO_Mailing_Client::delivery_provider() === 'mautic', 'Mautic es el único proveedor');

$source = file_get_contents(dirname(__DIR__) . '/modules/mailing/includes/class-flacso-mail-settings.php');
foreach (['FLACSO Mailing (SES)', 'F1 ·', 'F2 ·', 'Validar correo transaccional'] as $text) {
    check(strpos($source, $text) === false, "La pantalla ya no muestra {$text}");
}
echo "OK mail settings retired\n";
