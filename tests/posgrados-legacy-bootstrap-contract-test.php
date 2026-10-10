<?php

$module = file_get_contents(dirname(__DIR__) . '/modules/posgrados/init.php');

function posgrados_bootstrap_assert(bool $condition, string $message): void {
    if (!$condition) {
        fwrite(STDERR, "Fallo: {$message}\n");
        exit(1);
    }
}

$plugin_position = strpos($module, 'class-flacso-posgrados-plugin.php');
$dependencies = [
    'class-flacso-posgrados-admin-page.php',
    'class-flacso-posgrados-seeder.php',
    'class-flacso-posgrados-block.php',
];

posgrados_bootstrap_assert($plugin_position !== false, 'el inicializador de Posgrados debe cargarse');

foreach ($dependencies as $dependency) {
    $position = strpos($module, $dependency);
    posgrados_bootstrap_assert($position !== false, "debe cargar {$dependency}");
    posgrados_bootstrap_assert($position < $plugin_position, "debe cargar {$dependency} antes de inicializar Posgrados");
}

echo "OK posgrados legacy bootstrap contract\n";
