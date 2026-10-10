<?php
/**
 * Adaptador de Mailing para la portada.
 */

if (!defined('ABSPATH')) {
    exit;
}

add_filter('flacso_main_page_component_files', static function (array $files): array {
    $files[] = FLACSO_URUGUAY_PATH . 'modules/main-page/sections/mailing.php';
    return $files;
});

add_filter('flacso_homepage_sections', static function (array $sections): array {
    $sections['mailing'] = [
        'function' => 'flacso_section_mailing_render',
        'owner' => 'mailing',
    ];
    return $sections;
});
