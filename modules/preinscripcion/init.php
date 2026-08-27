<?php
/**
 * Preinscripción Module
 * Sistema de formularios de preinscripción para Maestrías, Especializaciones, Diplomas y Diplomados
 *
 * @package FLACSO_Uruguay
 * @subpackage Preinscripcion
 */

if (!defined('ABSPATH')) {
    exit;
}

// Definir constantes del módulo
define('FLACSO_PREINSCRIPCION_MODULE_PATH', __DIR__ . '/');
define('FLACSO_PREINSCRIPCION_MODULE_URL', plugin_dir_url(__FILE__));

// Cargar clase principal
require_once FLACSO_PREINSCRIPCION_MODULE_PATH . 'includes/class-formulario-preinscripcion.php';

// Inicializar módulo
add_action('init', function() {
    FLACSO_Formulario_Preinscripcion_Final::get_instance();
}, 5);

/**
 * Analítica del embudo de preinscripción de seminarios.
 * El script se autolimita a la plantilla que contiene
 * .flacso-preinscripcion-seminario-main, por lo que puede cargarse de forma segura
 * en rutas de preinscripción sin acoplarse al tema.
 */
add_action('wp_enqueue_scripts', function() {
    $request_uri = isset($_SERVER['REQUEST_URI']) ? (string) wp_unslash($_SERVER['REQUEST_URI']) : '';
    $is_preinscripcion = (bool) get_query_var('flacso_preinscripcion')
        || strpos($request_uri, '/preinscripcion') !== false;

    if (!$is_preinscripcion) {
        return;
    }

    $relative_path = 'assets/preinscripcion-analytics.js';
    $absolute_path = FLACSO_PREINSCRIPCION_MODULE_PATH . $relative_path;

    wp_enqueue_script(
        'flacso-preinscripcion-analytics',
        FLACSO_PREINSCRIPCION_MODULE_URL . $relative_path,
        array(),
        is_readable($absolute_path) ? (string) filemtime($absolute_path) : FLACSO_URUGUAY_VERSION,
        true
    );

    $seminario_id = isset($_GET['ID']) ? absint(wp_unslash($_GET['ID'])) : 0;
    if ($seminario_id > 0 && get_post_type($seminario_id) === 'seminario') {
        $valor_usd = get_post_meta($seminario_id, '_seminario_valor_usd', true);
        $valor_uyu = get_post_meta($seminario_id, '_seminario_valor_uyu', true);
        $value = 0.0;
        $currency = 'USD';

        if ($valor_usd !== '' && $valor_usd !== null && (float) $valor_usd > 0) {
            $value = (float) $valor_usd;
            $currency = 'USD';
        } elseif ($valor_uyu !== '' && $valor_uyu !== null && (float) $valor_uyu > 0) {
            $value = (float) $valor_uyu;
            $currency = 'UYU';
        }

        wp_add_inline_script(
            'flacso-preinscripcion-analytics',
            'window.flacsoMetaMonetaryContext = ' . wp_json_encode([
                'content_type' => 'seminario',
                'content_id' => $seminario_id,
                'value' => $value,
                'currency' => $currency,
            ]) . ';',
            'before'
        );
    }
}, 50);
