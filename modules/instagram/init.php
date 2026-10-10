<?php
/**
 * Módulo de Instagram.
 *
 * La implementación se conserva temporalmente en main-page para evitar una
 * mudanza física riesgosa, pero este módulo es ahora su propietario lógico.
 */

if (!defined('ABSPATH')) {
    exit;
}

flacso_require('modules/main-page/includes/class-flacso-instagram-api.php');

if (is_admin() || (function_exists('wp_doing_ajax') && wp_doing_ajax())) {
    flacso_require('modules/main-page/includes/class-flacso-instagram-post-importer.php');
    Flacso_Instagram_Post_Importer::init();
}

add_filter('flacso_main_page_component_files', static function (array $files): array {
    $files[] = FLACSO_URUGUAY_PATH . 'modules/main-page/sections/instagram.php';
    $files[] = FLACSO_URUGUAY_PATH . 'modules/main-page/includes/blocks/instagram/block.php';
    return $files;
});

add_filter('flacso_homepage_sections', static function (array $sections): array {
    $sections['instagram'] = [
        'function' => 'flacso_section_instagram_render',
        'owner' => 'instagram',
    ];
    return $sections;
});
