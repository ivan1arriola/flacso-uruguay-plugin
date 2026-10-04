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
mautic_only_assert(stripos($mautic_view, 'Correos transaccionales') !== false, 'La vista debe describir el flujo transaccional');
mautic_only_assert(stripos($mautic_view, 'Campaña de consultas') === false, 'La vista no debe presentar campañas como mecanismo de envío');
mautic_only_assert(strpos($mautic_view, 'flacso_mautic_validate_transactional') !== false, 'La consola debe poder validar el correo transaccional');
mautic_only_assert(strpos($mautic_view, 'F1 · Inscripciones abiertas') !== false, 'La consola debe mostrar F1 abiertas');
mautic_only_assert(strpos($mautic_view, 'F1 · Inscripciones cerradas') !== false, 'La consola debe mostrar F1 cerradas');
mautic_only_assert(strpos($mautic_view, 'F2 · Seguimiento') !== false, 'La consola debe mostrar F2 seguimiento');
mautic_only_assert(strpos($mautic_view, 'Mailjet') === false, 'La consola no debe mostrar Mailjet');

echo "OK mail-settings-mautic-only-test\n";
