<?php

$module = file_get_contents(dirname(__DIR__) . '/modules/posgrados/init.php');

function posgrados_bootstrap_assert(bool $condition, string $message): void {
    if (!$condition) {
        fwrite(STDERR, "Fallo: {$message}\n");
        exit(1);
    }
}

$retired_admin_files = [
    'class-flacso-posgrados-plugin.php',
    'class-flacso-posgrados-admin-page.php',
    'class-flacso-posgrados-seeder.php',
];

foreach ($retired_admin_files as $file) {
    posgrados_bootstrap_assert(
        strpos($module, $file) === false,
        "el panel administrativo legado no debe cargar {$file}"
    );
}

foreach ([
    'class-flacso-posgrados-consultas-form.php',
    'class-flacso-posgrados-block.php',
] as $compatibility_file) {
    posgrados_bootstrap_assert(
        strpos($module, $compatibility_file) !== false,
        "debe preservar {$compatibility_file} para páginas públicas existentes"
    );
}

echo "OK posgrados legacy bootstrap contract\n";
