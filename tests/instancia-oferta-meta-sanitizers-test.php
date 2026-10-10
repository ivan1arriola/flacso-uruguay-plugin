<?php

define('ABSPATH', __DIR__ . '/../');

require_once __DIR__ . '/../modules/instancias-oferta/includes/class-preinscription-flow.php';
require_once __DIR__ . '/../modules/instancias-oferta/includes/class-instancia-oferta.php';

function sanitizer_assert_same($expected, $actual, string $label): void {
    if ($expected !== $actual) {
        fwrite(STDERR, "Fallo {$label}: esperado " . var_export($expected, true) . ', obtuve ' . var_export($actual, true) . "\n");
        exit(1);
    }
}

$schema = FLACSO_Instancia_Oferta::meta_schema();
$numeric_keys = [
    FLACSO_Instancia_Oferta::META_VALOR_UYU,
    FLACSO_Instancia_Oferta::META_VALOR_USD,
    FLACSO_Instancia_Oferta::META_VALOR_UYU_DESCUENTO,
    FLACSO_Instancia_Oferta::META_VALOR_USD_DESCUENTO,
];

foreach ($numeric_keys as $key) {
    $callback = $schema[$key]['sanitize_callback'] ?? null;
    sanitizer_assert_same(true, is_callable($callback), $key . ' tiene sanitizer callable');
    sanitizer_assert_same(
        1234.5,
        call_user_func($callback, '1234.5', $key, 'post', FLACSO_Instancia_Oferta::POST_TYPE),
        $key . ' acepta la firma de cuatro argumentos de sanitize_meta'
    );
}

sanitizer_assert_same(
    [FLACSO_Instancia_Oferta::class, 'sanitize_number'],
    $schema[FLACSO_Instancia_Oferta::META_VALOR_UYU]['sanitize_callback'],
    'no usa floatval directamente como sanitize_callback'
);

fwrite(STDOUT, "OK instancia oferta meta sanitizers\n");
