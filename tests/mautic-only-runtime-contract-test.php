<?php

$root = dirname(__DIR__);

function mautic_runtime_assert(bool $condition, string $message): void {
    if (!$condition) {
        fwrite(STDERR, "FAIL: {$message}\n");
        exit(1);
    }
}

$active_files = [
    'modules/mailing/init.php',
    'modules/mailing/includes/class-flacso-mail-settings.php',
    'modules/mailing/includes/class-flacso-mailing-subscription.php',
    'modules/consultas/init.php',
];

foreach ($active_files as $relative) {
    $source = (string) file_get_contents($root . '/' . $relative);
    mautic_runtime_assert(
        strpos($source, 'FLACSO_Mailjet_Client') === false,
        "El código activo no debe cargar el cliente Mailjet: {$relative}"
    );
}

$plugin_source = (string) file_get_contents($root . '/flacso-uruguay.php');
mautic_runtime_assert(
    strpos($plugin_source, 'class-flacso-mailjet-client.php') === false,
    'El cargador principal no debe cargar el cliente Mailjet'
);

$mail_settings = (string) file_get_contents($root . '/modules/mailing/includes/class-flacso-mail-settings.php');
mautic_runtime_assert(strpos(strtolower($mail_settings), 'mailjet') === false, 'La consola no debe conservar referencias Mailjet');
mautic_runtime_assert(!file_exists($root . '/includes/integrations/class-flacso-mailjet-client.php'), 'El cliente Mailjet debe haber sido retirado');

echo "OK mautic-only-runtime-contract-test\n";
