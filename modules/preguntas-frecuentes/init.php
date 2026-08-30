<?php
/**
 * Preguntas frecuentes administrables.
 *
 * @package FLACSO_Uruguay
 */

if (!defined('ABSPATH')) {
    exit;
}

require_once __DIR__ . '/includes/class-flacso-preguntas-frecuentes.php';

FLACSO_Preguntas_Frecuentes::init();
