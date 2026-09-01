<?php

$root = dirname(__DIR__);
$safe = file_get_contents($root . '/modules/seminarios/includes/class-seminario-sort-safe.php');
$init = file_get_contents($root . '/modules/seminarios/init.php');

function seminar_sort_collation_assert($condition, string $message): void {
    if (!$condition) {
        fwrite(STDERR, "FAIL: {$message}\n");
        exit(1);
    }
}

seminar_sort_collation_assert(strpos($safe, 'CAST(relacion.meta_value AS UNSIGNED)') !== false, 'la relación debe compararse como ID numérico');
seminar_sort_collation_assert(strpos($safe, 'CAST($wpdb->posts.ID AS CHAR)') === false, 'no debe comparar meta_value contra un CHAR con otra collation');
seminar_sort_collation_assert(strpos($safe, "MAX(CAST(NULLIF(fecha.meta_value, '') AS DATE))") !== false, 'debe mantener el orden por fecha real');
seminar_sort_collation_assert(strpos($init, 'FLACSO_Seminario_Sort_Safe::init();') !== false, 'debe activar el filtro seguro');

echo "OK seminario sort collation\n";
