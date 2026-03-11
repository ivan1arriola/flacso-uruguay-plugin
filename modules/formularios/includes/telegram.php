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

/**
 * Construye un mensaje descriptivo de Telegram para nuevas consultas
 */
function fc_build_telegram_message( $nombre, $apellido, $email, $telefono, $asunto, $mensaje, $post_id, $navegador = '', $sistema_operativo = '' ) {
	$site_name = wp_specialchars_decode( get_bloginfo( 'name' ), ENT_QUOTES );
	$fecha = current_time( 'd/m/Y H:i:s' );
	
	$mensaje_html = '<b>📨 Nueva Consulta Recibida</b>' . "\n";
	$mensaje_html .= '━━━━━━━━━━━━━━━━━━━━━━' . "\n\n";
	
	$mensaje_html .= '<b>👤 Remitente:</b>' . "\n";
	$mensaje_html .= '  ' . htmlspecialchars( $nombre . ' ' . $apellido, ENT_QUOTES, 'UTF-8' ) . "\n\n";
	
	$mensaje_html .= '<b>📧 Correo:</b>' . "\n";
	$mensaje_html .= '  ' . htmlspecialchars( $email, ENT_QUOTES, 'UTF-8' ) . "\n\n";
	
	$mensaje_html .= '<b>☎️ Teléfono:</b>' . "\n";
	$mensaje_html .= '  ' . htmlspecialchars( $telefono, ENT_QUOTES, 'UTF-8' ) . "\n\n";
	
	$mensaje_html .= '<b>📌 Asunto:</b>' . "\n";
	$mensaje_html .= '  ' . htmlspecialchars( $asunto, ENT_QUOTES, 'UTF-8' ) . "\n\n";
	
	$mensaje_html .= '<b>💬 Mensaje:</b>' . "\n";
	$mensaje_truncado = strlen( $mensaje ) > 500 ? substr( $mensaje, 0, 497 ) . '...' : $mensaje;
	$mensaje_html .= '<pre>' . htmlspecialchars( $mensaje_truncado, ENT_QUOTES, 'UTF-8' ) . '</pre>' . "\n";
	
	$mensaje_html .= '━━━━━━━━━━━━━━━━━━━━━━' . "\n\n";
	
	$mensaje_html .= '<b>📊 Detalles Técnicos:</b>' . "\n";
	$mensaje_html .= '  <b>ID:</b> #' . intval( $post_id ) . "\n";
	$mensaje_html .= '  <b>Fecha:</b> ' . $fecha . "\n";
	
	if ( ! empty( $navegador ) ) {
		$mensaje_html .= '  <b>Navegador:</b> ' . htmlspecialchars( $navegador, ENT_QUOTES, 'UTF-8' ) . "\n";
	}
	if ( ! empty( $sistema_operativo ) ) {
		$mensaje_html .= '  <b>SO:</b> ' . htmlspecialchars( $sistema_operativo, ENT_QUOTES, 'UTF-8' ) . "\n";
	}
	
	$mensaje_html .= "\n" . '<b>🌐 Sitio:</b> ' . htmlspecialchars( $site_name, ENT_QUOTES, 'UTF-8' );
	
	return $mensaje_html;
}
