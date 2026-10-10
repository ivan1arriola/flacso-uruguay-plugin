<?php
/**
 * Adaptador de Eventos para la portada.
 *
 * El renderer todavía vive físicamente en main-page por compatibilidad, pero
 * Eventos es quien declara su archivo y su sección. Esto elimina el acoplamiento
 * de main-page al dominio y permite mover el renderer sin cambiar el builder.
 */

if (!defined('ABSPATH')) {
    exit;
}

add_filter('flacso_main_page_component_files', static function (array $files): array {
    $files[] = FLACSO_URUGUAY_PATH . 'modules/main-page/sections/eventos-carousel.php';
    return $files;
});

add_filter('flacso_homepage_sections', static function (array $sections): array {
    $sections['eventos'] = [
        'function' => 'flacso_section_eventos_render',
        'owner' => 'eventos',
        'react_component' => 'eventos-proximos',
    ];
    return $sections;
});
