<?php
/**
 * Adaptador de Seminarios para la portada.
 */

if (!defined('ABSPATH')) {
    exit;
}

add_filter('flacso_main_page_component_files', static function (array $files): array {
    $files[] = FLACSO_URUGUAY_PATH . 'modules/main-page/sections/lista-seminarios.php';
    return $files;
});

add_filter('flacso_homepage_sections', static function (array $sections): array {
    $sections['seminarios'] = [
        'function' => 'flacso_section_seminarios_proximos_render',
        'owner' => 'seminarios',
    ];
    return $sections;
});
