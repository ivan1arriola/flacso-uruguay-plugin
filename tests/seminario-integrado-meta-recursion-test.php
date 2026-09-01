<?php

$root = dirname(__DIR__);
$init = file_get_contents($root . '/modules/seminarios/init.php');
$safe = file_get_contents($root . '/modules/seminarios/includes/class-seminario-integrado-meta-safe.php');

function integrated_meta_assert($condition, string $message): void {
    if (!$condition) {
        fwrite(STDERR, "FAIL: {$message}\n");
        exit(1);
    }
}

integrated_meta_assert(
    strpos($init, "remove_filter('get_post_metadata', [FLACSO_Seminario_Integrado::class, 'filter_derived_metadata'], 20)") !== false,
    'se retira el filtro de lectura recursivo original'
);

integrated_meta_assert(
    strpos($init, "FLACSO_Seminario_Integrado_Meta_Safe::class, 'filter_derived_metadata'") !== false,
    'se registra el filtro de lectura seguro'
);

integrated_meta_assert(
    strpos($safe, "if (!in_array(\$meta_key, self::EDITION_DERIVED_KEYS, true))") !== false,
    'el filtro retorna antes de consultar seminario_id para metadatos no derivados'
);

integrated_meta_assert(
    strpos($safe, "private const EDITION_DERIVED_KEYS = ['docentes', 'encuentros_sincronicos'];") !== false,
    'sólo docentes y encuentros son interceptados a nivel Edición'
);

echo "OK: derived metadata recursion guard\n";
