<?php
/**
 * Contrato visual: el listado de Seminarios comparte patrones responsive con
 * Ofertas Académicas, sin incorporar abreviaciones al dominio.
 */
$root = dirname(__DIR__);
$source = file_get_contents($root . '/modules/seminarios/includes/class-seminario-cpt.php');
$model = file_get_contents($root . '/modules/seminarios/includes/class-seminario.php');
$editor = file_get_contents($root . '/modules/seminarios/includes/class-seminario-admin-fields.php');

function seminario_admin_list_assert(bool $condition, string $message): void {
    if (!$condition) {
        fwrite(STDERR, "FAIL: {$message}\n");
        exit(1);
    }
}

seminario_admin_list_assert(strpos($source, 'public static function admin_list_styles()') !== false, 'se conserva el render nativo de estilos');
seminario_admin_list_assert(strpos($source, 'post-type-seminario #posts-filter .search-box') !== false, 'búsqueda adaptable aislada');
seminario_admin_list_assert(strpos($source, '.post-type-seminario .wp-list-table .toggle-row') !== false, 'área táctil de expansión');
seminario_admin_list_assert(strpos($source, '.post-type-seminario .wp-list-table .row-actions') !== false, 'acciones adaptables');
seminario_admin_list_assert(strpos($source, '@media screen and (max-width: 782px)') !== false, 'vista específica para celular');
seminario_admin_list_assert(strpos($source, '@media screen and (min-width: 783px) and (max-width: 1100px)') !== false, 'ocultamiento de fecha limitado a escritorio');
seminario_admin_list_assert(strpos($source, "'edicion_actual'") !== false, 'se conserva edición actual');
seminario_admin_list_assert(strpos($source, "'preinscripcion'") !== false, 'se conserva preinscripción');
seminario_admin_list_assert(strpos($source, 'sort_by_edition_date') !== false, 'se conserva el orden por edición');
seminario_admin_list_assert(strpos($source, 'accepts_registration') !== false, 'se conserva el estado canónico de preinscripción');
seminario_admin_list_assert(strpos($source, 'display_post_states') === false, 'no incorpora etiqueta de abreviación al listado');
seminario_admin_list_assert(strpos($model, "'abreviacion'") === false, 'el modelo de Seminarios no contiene abreviaciones');
seminario_admin_list_assert(strpos($editor, 'flacso_seminario[abreviacion]') === false, 'no agrega abreviaciones al editor');

$start = strpos($source, 'public static function admin_list_styles()');
$css_start = strpos($source, '<style>', $start);
$css_end = strpos($source, '</style>', $css_start);
seminario_admin_list_assert($css_start !== false && $css_end !== false, 'los estilos están delimitados');
$css = substr($source, $css_start, $css_end - $css_start);
seminario_admin_list_assert(!preg_match('/^\\h*\\.flacso-/m', $css), 'los estilos de estados no afectan otros CPT');

echo "OK seminario admin list UI contract\n";
