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
mautic_only_assert(strpos($mautic_view, 'Campaña de Consultas') !== false, 'La vista debe configurar la campaña de consultas');
mautic_only_assert(strpos($mautic_view, 'ID Plantilla Mautic') === false, 'La consola no debe mostrar IDs de plantilla');
mautic_only_assert(strpos($mautic_view, 'Mailjet') === false, 'La consola no debe mostrar Mailjet');

echo "OK mail-settings-mautic-only-test\n";
