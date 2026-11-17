<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }

function fc_can_use_telegram() {
    return FLACSO_Telegram::is_configured();
}

function fc_send_telegram_message( $text ) {
	// Corre dentro del envío del visitante: un Telegram lento no debe retener la plaza PHP.
	return FLACSO_Telegram::send( (string) $text, true, 5 );
}
