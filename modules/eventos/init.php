<?php
/**
 * Módulo de Eventos - FLACSO Uruguay.
 */

if (!defined('ABSPATH')) {
    exit;
}

flacso_safe_require('modules/eventos/includes/class-cpt-eventos-manager.php');
flacso_safe_require('modules/eventos/includes/homepage.php');

add_action('init', static function (): void {
    if (class_exists('CPT_Eventos_Manager')) {
        new CPT_Eventos_Manager();
    }
}, 5);
