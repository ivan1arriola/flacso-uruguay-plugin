<?php
/**
 * Integración de Convenios con componentes de portada/shortcode.
 */

if (!defined('ABSPATH')) {
    exit;
}

add_filter('flacso_main_page_component_files', static function (array $files): array {
    $files[] = FLACSO_URUGUAY_PATH . 'modules/main-page/sections/convenios-responsivos.php';
    return $files;
});
