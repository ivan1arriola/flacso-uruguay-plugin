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
