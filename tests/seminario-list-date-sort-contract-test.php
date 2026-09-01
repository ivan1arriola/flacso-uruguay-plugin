<?php

$root = dirname(__DIR__);
$seminario = file_get_contents($root . '/modules/seminarios/includes/class-seminario-cpt.php');

function seminar_list_date_assert($condition, string $message): void {
    if (!$condition) {
        fwrite(STDERR, "FAIL: {$message}\n");
        exit(1);
    }
}

seminar_list_date_assert(strpos($seminario, "manage_edit-' . self::POST_TYPE . '_sortable_columns") !== false, 'la columna de edición debe ser ordenable');
seminar_list_date_assert(strpos($seminario, "'edicion_actual'] = 'edicion_fecha_inicio'") !== false, 'debe ordenar por fecha de edición');
seminar_list_date_assert(strpos($seminario, "add_filter('posts_clauses'") !== false, 'debe adaptar el ORDER BY del listado');
seminar_list_date_assert(strpos($seminario, "MAX(CAST(NULLIF(fecha.meta_value, '') AS DATE))") !== false, 'debe ordenar por fecha real y no por texto visible');
seminar_list_date_assert(strpos($seminario, "return \$parsed->format('d/m/Y');") !== false, 'las fechas deben mostrarse en formato uruguayo');
seminar_list_date_assert(strpos($seminario, "self::format_uy_date(\$inicio)") !== false, 'el listado debe usar el formateador UY');

echo "OK seminario list date sort contract\n";
