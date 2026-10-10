<?php
/**
 * Adaptador de Oferta Académica para la portada.
 */

if (!defined('ABSPATH')) {
    exit;
}

add_filter('flacso_main_page_component_files', static function (array $files): array {
    $files[] = FLACSO_URUGUAY_PATH . 'modules/main-page/sections/oferta-academica-home.php';
    return $files;
});

add_filter('flacso_homepage_sections', static function (array $sections): array {
    $sections['oferta_academica'] = [
        'function' => 'flacso_section_oferta_educativa_render',
        'owner' => 'oferta-academica',
    ];
    return $sections;
});
