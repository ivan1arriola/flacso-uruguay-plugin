<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }

function fc_can_use_telegram() {
    return get_option( 'fc_use_telegram', '0' ) === '1'
        && ! empty( get_option( 'fc_telegram_bot_token', '' ) )
        && ! empty( get_option( 'fc_telegram_chat_id', '' ) );
}

function fc_send_telegram_message( $text ) {
	$token = get_option( 'fc_telegram_bot_token', '' );
	$chat  = get_option( 'fc_telegram_chat_id', '' );
	if ( empty( $token ) || empty( $chat ) ) { return false; }

	$url = 'https://api.telegram.org/bot' . rawurlencode( $token ) . '/sendMessage';
	$args = [
		'timeout' => 15,
		'body'    => [
			'chat_id'    => $chat,
			'text'       => $text,
			'parse_mode' => 'HTML',
			'disable_web_page_preview' => true,
		],
	];

	$resp = wp_remote_post( $url, $args );
	if ( is_wp_error( $resp ) ) {
		error_log( '[fc_telegram] wp_remote_post error: ' . $resp->get_error_message() );
		return false;
	}

	$code = wp_remote_retrieve_response_code( $resp );
	if ( $code < 200 || $code >= 300 ) {
		error_log( '[fc_telegram] HTTP ' . $code . ' body: ' . wp_remote_retrieve_body( $resp ) );
		return false;
	}

	$body = json_decode( wp_remote_retrieve_body( $resp ), true );
	$ok   = ! empty( $body['ok'] );
	if ( ! $ok ) {
		error_log( '[fc_telegram] response not ok: ' . wp_remote_retrieve_body( $resp ) );
	}

	return $ok;
}

