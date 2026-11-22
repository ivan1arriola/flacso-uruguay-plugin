<?php
/**
 * El Editor externo (editor.flacso.edu.uy y su antecesor en Vercel) ya no existe:
 * el plugin no debe leerlo, exponerlo en la API de ajustes ni redirigir hacia él.
 */

$root = dirname(__DIR__);

function editor_gone_assert($condition, string $message): void {
    if (!$condition) {
        fwrite(STDERR, "FAIL: {$message}\n");
        exit(1);
    }
}

editor_gone_assert(!file_exists($root . '/modules/formularios/includes/editor-routing.php'), 'No debe existir el ruteo legado hacia el Editor externo (editor-routing.php)');
editor_gone_assert(strpos((string) file_get_contents($root . '/modules/formularios/init.php'), 'editor-routing') === false, 'formularios/init.php no debe cargar editor-routing.php');

$forbidden = array(
    'flacso_external_editor_url',
    'OPTION_EXTERNAL_EDITOR_URL',
    'editor.flacso.edu.uy',
    'editor-flacso-uy.vercel.app',
    'fc_ensure_info_request_editor_routes',
);

$offenders = array();
foreach (array('modules', 'includes') as $dir) {
    $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root . '/' . $dir, FilesystemIterator::SKIP_DOTS));
    foreach ($iterator as $file) {
        if ($file->getExtension() !== 'php') {
            continue;
        }
        $source = (string) file_get_contents($file->getPathname());
        foreach ($forbidden as $needle) {
            if (strpos($source, $needle) !== false) {
                $offenders[] = str_replace($root . '/', '', str_replace('\\', '/', $file->getPathname())) . " ({$needle})";
            }
        }
    }
}
editor_gone_assert(array() === $offenders, "El código no debe mencionar el Editor externo retirado:\n  - " . implode("\n  - ", $offenders));

echo "OK editor externo retirado\n";
