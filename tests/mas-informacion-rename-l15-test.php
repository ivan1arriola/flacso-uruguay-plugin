<?php

$root = dirname(__DIR__);
$read = static fn(string $p): string => (string) file_get_contents($root . '/' . $p);

function l15_assert(bool $cond, string $msg): void
{
    if (!$cond) {
        fwrite(STDERR, "Fallo: {$msg}\n");
        exit(1);
    }
}

$files = [
    'settings' => $read('modules/formularios/includes/settings.php'),
    'integ' => $read('includes/core/class-flacso-integrations-settings.php'),
    'block' => $read('modules/formularios/blocks/formulario-consulta/block.json'),
    'index' => $read('modules/formularios/blocks/formulario-consulta/index.js'),
    'main' => $read('modules/main-page/assets/js/flacso-consultas-block.js'),
];
$all = implode("\n", $files);

$present = [
    'Recepción de solicitudes de más información',
    'Las solicitudes de más información no se guardan:',
    'Avisar cada solicitud de más información por Telegram',
    'Solicitud de prueba',
    'Enviar solicitud de prueba',
    '(Más información, Ofertas y Charlas)',
    'de todas las solicitudes de más información y de inscripción.',
    'Formulario de más información',
    '"más información"',
    'Formulario de más información FLACSO',
    'Formulario AJAX de solicitudes de más información de posgrados FLACSO.',
    'fc_test_consultas_webhook',
    'flacso-uruguay/formulario-consulta',
    'flacso-uruguay/consultas-form',
];
foreach ($present as $s) {
    l15_assert(strpos($all, $s) !== false, "falta «{$s}»");
}
$absent = ['Recepción de consultas', 'Enviar consulta de prueba', 'Formulario de Consulta', 'Formulario de consultas FLACSO'];
foreach ($absent as $s) {
    l15_assert(strpos($all, $s) === false, "sigue presente «{$s}»");
}

echo "OK\n";
