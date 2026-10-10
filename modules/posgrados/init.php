<?php
/**
 * Compatibilidad histórica de Posgrados.
 *
 * El dominio canónico es `oferta-academica`. Este módulo se mantiene sólo
 * para consumidores antiguos (REST, bloques o metadatos) mientras se completa
 * su retiro. No debe recibir funcionalidad nueva.
 */

if (!defined('ABSPATH')) {
    exit;
}

if (!defined('FLACSO_POSGRADOS_LEGACY')) {
    define('FLACSO_POSGRADOS_LEGACY', true);
}
if (!defined('FLACSO_POSGRADOS_PLUGIN_URL')) {
    define('FLACSO_POSGRADOS_PLUGIN_URL', FLACSO_URUGUAY_URL . 'modules/posgrados/');
}
if (!defined('FLACSO_POSGRADOS_PLUGIN_PATH')) {
    define('FLACSO_POSGRADOS_PLUGIN_PATH', FLACSO_URUGUAY_PATH . 'modules/posgrados/');
}
if (!defined('FLACSO_POSGRADOS_SLUG')) {
    define('FLACSO_POSGRADOS_SLUG', 'flacso-posgrados-docentes');
}

flacso_safe_require('modules/posgrados/includes/class-flacso-posgrados-pages.php');
flacso_safe_require('modules/posgrados/includes/class-flacso-posgrados-fields.php');
flacso_safe_require('modules/posgrados/includes/class-flacso-posgrados-consultas-form.php');
flacso_safe_require('modules/posgrados/includes/class-flacso-posgrados-docentes-sync.php');
flacso_safe_require('modules/posgrados/includes/rest-api-posgrados.php');
flacso_safe_require('modules/posgrados/includes/blocks/class-flacso-posgrados-block.php');

// El panel de administración legado se retiró: el catálogo se gestiona desde
// Oferta Académica. Se preservan metadatos y bloques para páginas públicas que
// todavía los consumen.
if (class_exists('FLACSO_Posgrados_Fields')) {
    add_action('init', [FLACSO_Posgrados_Fields::class, 'register_meta_and_support']);
}
if (class_exists('FLACSO_Posgrados_Block')) {
    FLACSO_Posgrados_Block::init();
}

do_action('flacso_legacy_posgrados_loaded');
