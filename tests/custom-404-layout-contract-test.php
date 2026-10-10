<?php

$root = dirname(__DIR__);
$file = file_get_contents($root . '/modules/core/includes/class-flacso-custom-404.php');

function custom_404_assert($condition, string $message): void {
    if (!$condition) {
        fwrite(STDERR, "Fallo: {$message}\n");
        exit(1);
    }
}

custom_404_assert(strpos($file, 'class Flacso_Custom_404') !== false, 'Flacso_Custom_404 class exists');
custom_404_assert(strpos($file, 'flacso-404-number') !== false, 'Hero visual includes flacso-404-number class');
custom_404_assert(strpos($file, '<div class="flacso-404-number">404</div>') !== false, 'Hero visual renders 404 number');
custom_404_assert(strpos($file, 'flacso-404-compass') !== false, 'Hero visual includes flacso-404-compass');
custom_404_assert(strpos($file, 'No encontramos esta página') !== false, '404 title is present');

fwrite(STDOUT, "OK custom 404 layout contract\n");

