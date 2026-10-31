<?php

$source = (string) file_get_contents(dirname(__DIR__) . '/modules/mailing/includes/class-flacso-mail-settings.php');
$start = strpos($source, 'public static function render_page');
$mautic_view = $start === false ? '' : substr($source, $start);

function mautic_only_assert(bool $condition, string $message): void {
    if (!$condition) {
        fwrite(STDERR, "FAIL: {$message}\n");
        exit(1);
    }
}

mautic_only_assert(strpos($source, 'public static function render_page') !== false, 'La consola debe tener una vista solo Mautic');
mautic_only_assert(stripos($mautic_view, 'Campaña de consultas') === false, 'La vista no debe presentar campañas como mecanismo de envío');
mautic_only_assert(strpos($mautic_view, 'flacso_mautic_validate_transactional') === false, 'La vista no debe validar las plantillas F1/F2 retiradas');
mautic_only_assert(strpos($mautic_view, 'F1 · ') === false && strpos($mautic_view, 'F2 · ') === false, 'La vista no debe mostrar plantillas F1/F2');
mautic_only_assert(strpos($mautic_view, 'FLACSO Mailing') === false, 'La vista no debe ofrecer otro proveedor');
mautic_only_assert(strpos($mautic_view, 'Mailjet') === false, 'La consola no debe mostrar Mailjet');

echo "OK mail-settings-mautic-only-test\n";
