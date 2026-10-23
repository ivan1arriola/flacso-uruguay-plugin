<?php
/**
 * Punto de entrada del editor académico integrado.
 *
 * Las pantallas del editor son una capa de presentación: el dominio,
 * la persistencia y los permisos continúan en WordPress y /modules.
 */
if (!defined('ABSPATH')) {
    exit;
}

require_once __DIR__ . '/includes/class-editor-router.php';

FLACSO_Editor_Router::init();
