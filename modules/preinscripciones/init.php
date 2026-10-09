<?php

if (!defined('ABSPATH')) {
    exit;
}

if (!defined('FLACSO_PREINSCRIPTIONS_MODULE_PATH')) {
    define('FLACSO_PREINSCRIPTIONS_MODULE_PATH', __DIR__ . '/');
}

$flacso_preinscriptions_files = [
    'modules/preinscripciones/includes/class-preinscriptions-field-catalog.php',
    'modules/preinscripciones/includes/class-preinscriptions-config.php',
    'modules/preinscripciones/includes/class-preinscriptions-meta.php',
    'modules/preinscripciones/includes/class-preinscriptions-admin.php',
    'modules/preinscripciones/includes/class-preinscriptions-serializer.php',
    'modules/preinscripciones/includes/class-preinscriptions-cache.php',
    'modules/preinscripciones/includes/class-preinscriptions-rest.php',
];
$flacso_preinscriptions_base_dir = defined('FLACSO_URUGUAY_PATH')
    ? FLACSO_URUGUAY_PATH
    : dirname(__DIR__, 2) . '/';

foreach ($flacso_preinscriptions_files as $flacso_preinscriptions_file) {
    if (function_exists('flacso_safe_require')) {
        flacso_safe_require($flacso_preinscriptions_file);
    } else {
        require_once rtrim($flacso_preinscriptions_base_dir, '/') . '/' . $flacso_preinscriptions_file;
    }
}

if (function_exists('add_action')) {
    add_action('init', [FLACSO_Preinscriptions_Meta::class, 'init'], 5);
    if (!function_exists('is_admin') || is_admin()) {
        FLACSO_Preinscriptions_Admin::init();
    }
    FLACSO_Preinscriptions_Cache::init();
    FLACSO_Preinscriptions_REST::init();
}
