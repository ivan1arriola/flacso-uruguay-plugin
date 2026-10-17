<?php
define('ABSPATH', __DIR__ . '/');
$GLOBALS['settings_registered'] = [];
$GLOBALS['settings_options'] = [];
function register_setting($group, $option, $args) { $GLOBALS['settings_registered'][$option] = [$group, $args]; }
function get_option($option, $default = false) { return $GLOBALS['settings_options'][$option] ?? $default; }
function esc_url_raw($value) { return filter_var($value, FILTER_SANITIZE_URL); }
require_once dirname(__DIR__) . '/modules/mailing/includes/class-flacso-mail-settings.php';
function provider_settings_assert(bool $ok, string $message): void {
    if (!$ok) { fwrite(STDERR, "FAIL: {$message}\n"); exit(1); }
}
FLACSO_Mail_Settings::register_settings();
$provider = FLACSO_Mail_Settings::OPTION_DELIVERY_PROVIDER;
$url = FLACSO_Mail_Settings::OPTION_MAILING_BASE_URL;
provider_settings_assert(isset($GLOBALS['settings_registered'][$provider], $GLOBALS['settings_registered'][$url]), 'registra ambas opciones en Settings API');
provider_settings_assert(FLACSO_Mail_Settings::sanitize_delivery_provider('flacso_mail') === 'flacso_mail', 'acepta proveedor permitido');
provider_settings_assert(FLACSO_Mail_Settings::sanitize_delivery_provider('arbitrary') === 'mautic', 'proveedor inválido vuelve a Mautic');
provider_settings_assert(FLACSO_Mail_Settings::sanitize_mailing_base_url('https://mailing.example.org/') === 'https://mailing.example.org', 'normaliza URL HTTPS');
provider_settings_assert(FLACSO_Mail_Settings::sanitize_mailing_base_url('http://mailing.example.org') === '', 'rechaza HTTP sin TLS');
provider_settings_assert(FLACSO_Mail_Settings::sanitize_mailing_base_url('https://user:pass@mailing.example.org') === '', 'rechaza credenciales embebidas en URL');
$GLOBALS['settings_options'][$provider] = 'flacso_mail';
provider_settings_assert(FLACSO_Mail_Settings::get_settings()['delivery_provider'] === 'flacso_mail', 'lee proveedor guardado en base de datos');
$source = file_get_contents(__DIR__ . '/../modules/mailing/includes/class-flacso-mail-settings.php');
provider_settings_assert(strpos($source, 'settings_fields(self::SETTINGS_GROUP)') !== false, 'la UI usa Settings API con protección nonce/capability');
provider_settings_assert(strpos($source, 'name="<?php echo esc_attr(self::OPTION_DELIVERY_PROVIDER); ?>"') !== false, 'la UI expone el selector de proveedor');
echo "OK mailing provider settings\n";
