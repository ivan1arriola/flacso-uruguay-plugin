<?php
/**
 * Módulo de Convenios.
 */

if (!defined('ABSPATH')) {
    exit;
}

flacso_require('modules/convenios/includes/class-flacso-convenios.php');
flacso_require('modules/convenios/includes/main-page.php');

Flacso_Convenios::init();
