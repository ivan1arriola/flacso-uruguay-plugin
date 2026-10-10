<?php
/**
 * Módulo de Eventos - FLACSO Uruguay.
 */

if (!defined('ABSPATH')) {
    exit;
}

flacso_require('modules/eventos/includes/class-cpt-eventos-manager.php');
flacso_require('modules/eventos/includes/homepage.php');

add_action('init', static function (): void {
    new CPT_Eventos_Manager();
}, 5);
