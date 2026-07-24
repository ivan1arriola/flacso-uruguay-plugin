<?php
/**
 * Formularios configurables con entrega directa a un webhook.
 *
 * Este módulo es deliberadamente independiente del módulo "formularios".
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'FLACSO_WEBHOOK_FORMS_PATH', __DIR__ . '/' );
define( 'FLACSO_WEBHOOK_FORMS_URL', plugin_dir_url( __FILE__ ) );

require_once FLACSO_WEBHOOK_FORMS_PATH . 'includes/class-flacso-webhook-forms.php';

Flacso_Webhook_Forms::init();
