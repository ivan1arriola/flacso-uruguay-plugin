<?php

$root = dirname(__DIR__);
$cohorte = file_get_contents($root . '/modules/oferta-academica/includes/class-cohorte.php');

function cohorte_admin_start_date_assert($condition, string $message): void {
    if (!$condition) {
        fwrite(STDERR, "FAIL: {$message}\n");
        exit(1);
    }
}

function cohorte_admin_start_date_method(string $source, string $method): string {
    $start = strpos($source, 'public static function ' . $method);
    $end = strpos($source, 'public static function ', $start + 1);
    return $start === false ? '' : substr($source, $start, $end === false ? null : $end - $start);
}

$render_meta_box = cohorte_admin_start_date_method($cohorte, 'render_meta_box');
$format_dates = cohorte_admin_start_date_method($cohorte, 'format_dates');

cohorte_admin_start_date_assert(
    strpos($render_meta_box, "name=\"fecha_fin\"") === false && strpos($render_meta_box, "name=\"anio_fin\"") === false,
    'el editor no debe pedir una fecha de fin'
);
cohorte_admin_start_date_assert(
    strpos($cohorte, 'flacso-cohorte-start-preview') !== false,
    'el editor debe incluir la previsualizacion de comienzo'
);
cohorte_admin_start_date_assert(
    strpos($cohorte, "get_permalink(\$parent_id)") !== false,
    'el editor debe enlazar la pagina publica de la oferta padre'
);
cohorte_admin_start_date_assert(
    strpos($format_dates, 'fecha_fin') === false && strpos($format_dates, 'anio_fin') === false,
    'el texto publico de fechas debe usar solo el comienzo'
);

echo "OK cohort admin start date contract\n";
