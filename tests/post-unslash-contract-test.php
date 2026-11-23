<?php
// Los datos de $_POST llegan con barras (magic quotes de WP): hay que usar wp_unslash() antes de sanitizar.
$root = dirname(__DIR__);
$files = [
    'modules/docentes/includes/class-docente-meta.php',
    'modules/docentes/includes/admin.php',
    'modules/autoridades/includes/admin-page.php',
];
foreach ($files as $rel) {
    foreach (file($root . '/' . $rel) as $n => $line) {
        if (strpos($line, '$_POST[') === false || strpos($line, 'wp_unslash') !== false) {
            continue;
        }
        if (preg_match('/sanitize_\w+\(|wp_kses_post\(|esc_url_raw\(|intval\(|foreach\s*\(\s*\$_POST/', $line)) {
            fwrite(STDERR, "FAIL: {$rel}:" . ($n + 1) . " usa \$_POST sin wp_unslash: " . trim($line) . "\n");
            exit(1);
        }
    }
}
echo "OK post-unslash-contract\n";
