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

add_action( 'wp_enqueue_scripts', static function () {
	if ( function_exists( 'wp_get_environment_type' ) && wp_get_environment_type() === 'local' ) {
		return;
	}

	if ( is_admin() ) {
		return;
	}

	$asset_path = __DIR__ . '/assets/meta-event-quality.js';
	if ( ! is_readable( $asset_path ) ) {
		return;
	}

	wp_enqueue_script(
		'flacso-meta-event-quality',
		FLACSO_URUGUAY_URL . 'modules/core/assets/meta-event-quality.js',
		array( 'flacso-meta-tracking' ),
		(string) filemtime( $asset_path ),
		false
	);
}, 2 );
