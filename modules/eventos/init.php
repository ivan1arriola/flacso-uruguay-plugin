<?php
/**
 * Módulo de Eventos - FLACSO Uruguay
 * Integración de CPT Eventos
 */

if (!defined('ABSPATH')) {
    exit;
}

// Cargar clases
flacso_safe_require('modules/eventos/includes/class-cpt-eventos-manager.php');

// Inicializar
add_action('init', function() {
    if (class_exists('CPT_Eventos_Manager')) {
        new CPT_Eventos_Manager(); // Instanciar la clase en lugar de llamar init()
    }
}, 5);
