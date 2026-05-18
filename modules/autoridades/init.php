<?php
/**
 * Módulo de Autoridades - FLACSO Uruguay
 * Gestión de autoridades institucionales vinculadas a perfiles de docentes.
 */

if (!defined('ABSPATH')) {
    exit;
}

define('FLACSO_AUTORIDADES_MODULE_LOADED', true);

// Cargar archivos del módulo
flacso_safe_require('modules/autoridades/includes/admin-page.php');
flacso_safe_require('modules/autoridades/includes/shortcode.php');
