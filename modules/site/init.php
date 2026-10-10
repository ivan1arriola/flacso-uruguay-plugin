<?php
/**
 * Módulo Site.
 *
 * Funcionalidades transversales del sitio (404, compatibilidad con el tema y
 * anuncios de navegación). La implementación histórica sigue en `modules/core`
 * como compatibilidad física hasta mover esos archivos sin cambiar clases.
 */

if (!defined('ABSPATH')) {
    exit;
}

flacso_require('modules/core/init.php');
