<?php
/**
 * El JavaScript de Bootstrap y el CSS de bootstrap-avatar (CDN, @latest) no se cargan: ninguna plantilla usa data-bs-*
 * ni componentes de Bootstrap y el estilo de avatares del módulo de docentes es propio.
 * Ejecutable sin WordPress: php tests/bootstrap-js-removed-test.php
 */
$root = dirname(__DIR__);
function bs_check($ok, $d) { if (!$ok) { fwrite(STDERR, "FAIL: " . $d . "\n"); exit(1); } }

$docentes = (string) file_get_contents($root . '/modules/docentes/includes/assets.php');
bs_check(strpos($docentes, 'bootstrap.bundle') === false && strpos($docentes, 'flacso-docentes-bootstrap-js') === false, 'docentes no carga bootstrap.bundle.min.js');
bs_check(strpos($docentes, 'bootstrap-avatar') === false, 'docentes no carga bootstrap-avatar@latest desde el CDN');
bs_check(strpos($docentes, "'flacso-docentes-directory'") !== false, 'el script del directorio sigue registrado');

$js = (string) file_get_contents($root . '/modules/docentes/assets/js/docentes-directory.js');
bs_check(strpos($js, 'bootstrap') === false && strpos($js, 'data-bs-') === false, 'el directorio de docentes no depende de window.bootstrap');

$forms = (string) file_get_contents($root . '/modules/formularios/includes/assets.php');
bs_check(strpos($forms, 'bootstrap.bundle') === false && strpos($forms, "wp_enqueue_script( 'fc-bootstrap' )") === false, 'formularios no carga bootstrap.bundle.min.js');

// Ninguna plantilla ni script propio usa los componentes JS de Bootstrap.
$hits = [];
$it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root . '/modules', FilesystemIterator::SKIP_DOTS));
foreach ($it as $f) {
    if (!preg_match('/\.(php|js)$/', $f->getFilename()) || strpos($f->getFilename(), '.min.') !== false) { continue; }
    if (preg_match('/data-bs-|new bootstrap\.|bootstrap\.(Modal|Tooltip|Collapse|Dropdown|Carousel|Offcanvas|Toast)/', (string) file_get_contents($f->getPathname()))) { $hits[] = $f->getPathname(); }
}
bs_check($hits === [], 'ningún archivo propio usa data-bs-* ni componentes JS de Bootstrap: ' . implode(', ', $hits));
echo "OK sin JavaScript de Bootstrap\n";
