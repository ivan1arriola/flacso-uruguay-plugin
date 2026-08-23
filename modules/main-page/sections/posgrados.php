<?php
/**
 * Compatibilidad histórica.
 *
 * La sección canónica de portada es Oferta Académica y vive en
 * `oferta-academica-home.php`. Este archivo permanece para instalaciones o
 * código que todavía hagan require explícito de `sections/posgrados.php`.
 */

if (!defined('ABSPATH')) {
    exit;
}

require_once __DIR__ . '/oferta-academica-home.php';
