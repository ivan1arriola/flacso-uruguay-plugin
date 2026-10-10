<?php

$plugin_main = file_get_contents(dirname(__DIR__) . '/flacso-uruguay.php');

function core_requires_assert(bool $condition, string $message): void {
    if (!$condition) {
        fwrite(STDERR, "Fallo: {$message}\n");
        exit(1);
    }
}

$requires_position = strpos($plugin_main, "includes/core/requires.php");
$loader_position = strpos($plugin_main, "includes/core/loader.php");

core_requires_assert($requires_position !== false, 'el bootstrap debe cargar las utilidades require');
core_requires_assert($loader_position !== false, 'el bootstrap debe cargar el loader de módulos');
core_requires_assert($requires_position < $loader_position, 'las utilidades require deben cargarse antes del loader de módulos');

echo "OK core requires bootstrap contract\n";
