<?php
/**
 * Módulo Core - Funcionalidades base compartidas
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

require_once __DIR__ . '/includes/class-flacso-custom-404.php';
Flacso_Custom_404::init();
