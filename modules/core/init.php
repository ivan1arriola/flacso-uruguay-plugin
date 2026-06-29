<?php
/**
 * Módulo Core - Funcionalidades base compartidas
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

require_once __DIR__ . '/includes/class-flacso-custom-404.php';
require_once __DIR__ . '/includes/class-flacso-kadence-compat.php';
require_once __DIR__ . '/includes/class-flacso-nav-announcement.php';
Flacso_Custom_404::init();
Flacso_Kadence_Compat::init();
Flacso_Nav_Announcement::init();
