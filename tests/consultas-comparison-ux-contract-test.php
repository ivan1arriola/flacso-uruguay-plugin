<?php

$source = file_get_contents(dirname(__DIR__) . '/modules/consultas/includes/class-flacso-consultas-admin.php');

function consultas_comparison_ux_assert(bool $condition, string $message): void {
    if (!$condition) {
        fwrite(STDERR, "Fallo: {$message}\n");
        exit(1);
    }
}

foreach ([
    'flacso-comparison-period',
    'flacso-comparison-period--current',
    'flacso-comparison-period--base',
    'flacso-comparison-delta',
    'is-positive',
    'is-negative',
    'is-neutral',
] as $selector) {
    consultas_comparison_ux_assert(
        strpos($source, $selector) !== false,
        "la comparación debe incluir {$selector}"
    );
}

echo "OK consultas comparison UX contract\n";
