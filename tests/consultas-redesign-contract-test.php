<?php

$root  = dirname(__DIR__);
$admin = file_get_contents($root . '/modules/consultas/includes/class-flacso-consultas-admin.php');
$css   = file_get_contents($root . '/modules/consultas/assets/css/consultas-admin.css');

function redesign_assert(bool $condition, string $message): void {
    if (!$condition) {
        fwrite(STDERR, "FAIL: {$message}\n");
        exit(1);
    }
}

redesign_assert($admin !== false && $css !== false, 'se pueden leer la vista y la hoja de estilos');
redesign_assert(strpos($admin, '<style>') === false, 'los estilos viven en la hoja de estilos, no en línea');
foreach (['nav-tab-wrapper', 'nav-tab-active', 'paginate_links(', '<dialog', 'showModal()', 'render_base_switch', 'wp-header-end'] as $needle) {
    redesign_assert(strpos($admin, $needle) !== false, "la vista debe incluir {$needle}");
}
redesign_assert(strpos($admin, 'flacso-cp-hero') === false, 'no debe quedar la cabecera hero muerta');
redesign_assert(preg_match('/[\x{1F000}-\x{1FAFF}\x{2300}-\x{23FF}\x{2600}-\x{27BF}\x{1F1E6}-\x{1F1FF}]/u', $admin) === 0, 'la interfaz no debe usar emojis');
redesign_assert(strpos($css, '.flacso-badge.pending') !== false, 'existe la insignia pending');
redesign_assert(strpos($css, 'tabular-nums') !== false, 'las cifras usan números tabulares');

echo "OK consultas-redesign-contract-test\n";
