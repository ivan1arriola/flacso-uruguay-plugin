<?php
/**
 * Modulo de convenios.
 *
 * @package FLACSO_Uruguay
 */

if (!defined('ABSPATH')) {
    exit;
}

require_once __DIR__ . '/includes/class-flacso-convenios.php';

Flacso_Convenios::init();
