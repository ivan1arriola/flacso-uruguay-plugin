<?php

$root = dirname(__DIR__);
$admin = file_get_contents($root . '/modules/consultas/includes/class-flacso-consultas-admin.php');

function notice_layout_assert(bool $condition, string $message): void {
    if (!$condition) {
        fwrite(STDERR, "FAIL: {$message}\n");
        exit(1);
    }
}

$wrapper = strpos($admin, '<div class="wrap flacso-consultas-platform">');
$diagnostics = strpos($admin, 'self::render_transactional_diagnostics();');

notice_layout_assert($wrapper !== false, 'la plataforma debe tener un contenedor identificable');
notice_layout_assert($diagnostics !== false && $diagnostics > $wrapper, 'el aviso operativo debe renderizarse dentro del contenedor de la plataforma');
notice_layout_assert(strpos($admin, '#wpbody-content > .notice') !== false, 'los avisos globales deben conservar contraste dentro de esta pantalla');
notice_layout_assert(strpos($admin, '.flacso-consultas-notices') !== false, 'los avisos de consultas deben tener una región visual propia');

echo "OK consultas-notice-layout-test\n";
