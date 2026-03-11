<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }

/**
 * Configuración y helpers de plantilla de correo
 */
function fc_email_logo_url() {
        // Permite sobreescritura vía filtro si hiciera falta
        $default = 'https://flacso.edu.uy/wp-content/uploads/2024/10/384ddefb-522d-432a-bbc8-c86f09bdceef.png';
        return apply_filters( 'fc_email_logo_url', $default );
}

function fc_email_common_css() {
        $css = '<style>
            /* Reset básico */
            body{margin:0;padding:0;background:#f6f7f9;color:#1f2937}
            table{border-collapse:collapse}
            img{border:0;max-width:100%;line-height:100%;}
            /* Contenedor */
            .fc-wrapper{width:100%;background:#f6f7f9;padding:24px 0}
            .fc-container{width:600px;margin:0 auto;background:#ffffff;border-radius:8px;overflow:hidden;border:1px solid #e5e7eb}
            .fc-header{padding:20px 24px;border-bottom:1px solid #e5e7eb;background:#023a72;text-align:left}
            .fc-brand{display:flex;align-items:center;gap:12px}
            .fc-brand-title{font:600 16px system-ui, -apple-system, Segoe UI, Roboto, Ubuntu, Cantarell, Noto Sans, Helvetica Neue, Arial; color:#ffffff;margin:0}
            .fc-body{padding:24px}
            .fc-body h1{font:600 20px system-ui;margin:0 0 12px;color:#111827}
            .fc-body h2{font:600 18px system-ui;margin:16px 0 8px;color:#111827}
            .fc-body p{font:400 14px system-ui;line-height:1.6;margin:0 0 12px}
            .fc-body ul{padding-left:18px;margin:0 0 12px}
            .fc-divider{height:1px;background:#e5e7eb;margin:16px 0}
            .fc-footer{padding:16px 24px;background:#f9fafb;border-top:1px solid #e5e7eb;color:#6b7280;font:400 12px system-ui}
            .fc-btn{display:inline-block;background:#1d4ed8;color:#fff !important;text-decoration:none;padding:10px 16px;border-radius:6px;font:600 14px system-ui}
            @media only screen and (max-width:480px){
                .fc-container{width:100% !important;border-radius:0}
                .fc-header,.fc-body,.fc-footer{padding:16px}
            }
        </style>';
        return $css;
}

function fc_wrap_email_html( $inner_html, $title = '' ) {
        $site_name = wp_specialchars_decode( get_bloginfo( 'name' ), ENT_QUOTES );
        $logo      = esc_url( fc_email_logo_url() );
        $title_html = $title ? '<h1>' . esc_html( $title ) . '</h1>' : '';
        $css = fc_email_common_css();
        $year = date_i18n( 'Y' );
        return "<!doctype html><html><head><meta charset='UTF-8'>" . $css . "</head><body>\n" .
                "<table role='presentation' class='fc-wrapper' width='100%' cellpadding='0' cellspacing='0'>\n" .
                "  <tr><td align='center'>\n" .
                "    <table role='presentation' class='fc-container' width='600' cellpadding='0' cellspacing='0'>\n" .
                "      <tr><td class='fc-header'>\n" .
                "        <div class='fc-brand'>\n" .
                "          <img src='" . $logo . "' alt='" . esc_attr( $site_name ) . "' width='140' height='auto'/>\n" .
                "        </div>\n" .
                "      </td></tr>\n" .
                "      <tr><td class='fc-body'>" . $title_html . $inner_html . "</td></tr>\n" .
                "      <tr><td class='fc-footer'>© " . esc_html( $year ) . " " . esc_html( $site_name ) . ". " . esc_html__( 'Todos los derechos reservados.', 'flacso-flacso-formulario-consultas' ) . "</td></tr>\n" .
                "    </table>\n" .
                "  </td></tr>\n" .
                "</table>\n" .
                "</body></html>";
}

function fc_can_use_gmail_api() {
    $use = get_option( 'fc_use_gmail_api', '0' ) === '1';
    $json = get_option( 'fc_google_service_account_json', '' );
    $imp  = get_option( 'fc_google_impersonated', '' );
    return $use && ! empty( $json ) && ! empty( $imp ) && function_exists( 'openssl_sign' );
}

function fc_get_fallback_senders_list() {
    $list = get_option( 'fc_fallback_senders', '' );
    if ( empty( $list ) ) { return []; }
    $parts = array_filter( array_map( 'trim', explode( ',', $list ) ) );
    $valid = [];
    foreach ( $parts as $p ) {
        if ( is_email( $p ) ) { $valid[] = $p; }
    }
    return $valid;
}

function fc_send_via_wp_mail_with_fallbacks( $to, $subject, $html, $headers, $from_name, $fallback_senders ) {
    $headers = (array) $headers;
    // Elimina cualquier From existente para evitar duplicados
    $headers = array_values( array_filter( $headers, function( $h ) {
        return stripos( $h, 'from:' ) !== 0; 
    } ) );

    if ( empty( $fallback_senders ) ) {
        $fallback_senders = [ get_option( 'fc_google_impersonated', 'noreply@flacso.edu.uy' ) ];
    }

    foreach ( $fallback_senders as $from_email ) {
        $trial_headers = $headers;
        $trial_headers[] = 'From: ' . ( $from_name ?: $from_email ) . ' <' . $from_email . '>';
        $ok = wp_mail( $to, $subject, $html, $trial_headers );
        if ( $ok ) { return true; }
    }
    return false;
}

function fc_google_get_access_token() {
    $cached = get_transient( 'fc_gmail_access_token' );
    if ( ! empty( $cached ) && is_string( $cached ) ) {
        return $cached;
    }

    $json = get_option( 'fc_google_service_account_json', '' );
    if ( empty( $json ) ) { return new WP_Error( 'no_json', 'Falta la clave de cuenta de servicio.' ); }

    $conf = json_decode( $json, true );
    if ( ! is_array( $conf ) || empty( $conf['client_email'] ) || empty( $conf['private_key'] ) || empty( $conf['token_uri'] ) ) {
        return new WP_Error( 'bad_json', 'Clave de cuenta de servicio inválida.' );
    }

    $now = time();
    $claims = [
        'iss'   => $conf['client_email'],
        'scope' => 'https://www.googleapis.com/auth/gmail.send',
        'aud'   => $conf['token_uri'],
        'iat'   => $now,
        'exp'   => $now + 3600,
        'sub'   => get_option( 'fc_google_impersonated', '' ),
    ];

    $jwt_header = [ 'alg' => 'RS256', 'typ' => 'JWT' ];
    $enc = function( $data ) {
        return rtrim( strtr( base64_encode( wp_json_encode( $data ) ), '+/', '-_' ), '=' );
    };
    $base = $enc( $jwt_header ) . '.' . $enc( $claims );

    $pkey = openssl_pkey_get_private( $conf['private_key'] );
    if ( ! $pkey ) { return new WP_Error( 'no_key', 'No se pudo leer la private_key.' ); }
    $sig = '';
    $ok = openssl_sign( $base, $sig, $pkey, 'sha256' );
    openssl_free_key( $pkey );
    if ( ! $ok ) { return new WP_Error( 'sign_fail', 'Fallo al firmar JWT.' ); }
    $jwt = $base . '.' . rtrim( strtr( base64_encode( $sig ), '+/', '-_' ), '=' );

    $resp = wp_remote_post( $conf['token_uri'], [
        'headers' => [ 'Content-Type' => 'application/x-www-form-urlencoded' ],
        'body'    => [
            'grant_type' => 'urn:ietf:params:oauth:grant-type:jwt-bearer',
            'assertion'  => $jwt,
        ],
        'timeout' => 20,
    ] );
    if ( is_wp_error( $resp ) ) { return $resp; }
    $code = wp_remote_retrieve_response_code( $resp );
    $body = json_decode( wp_remote_retrieve_body( $resp ), true );
    if ( 200 !== $code || empty( $body['access_token'] ) ) {
        return new WP_Error( 'token_fail', 'No se pudo obtener access_token', [ 'response' => $body, 'code' => $code ] );
    }
    $token = $body['access_token'];
    $ttl   = ! empty( $body['expires_in'] ) ? ( (int) $body['expires_in'] - 60 ) : 3300;
    if ( $ttl > 0 ) {
        set_transient( 'fc_gmail_access_token', $token, $ttl );
    }
    return $token;
}

function fc_send_via_gmail_api( $to, $subject, $html, $headers = [], $from_name = '', $from_email = '' ) {
    $token = fc_google_get_access_token();
    if ( is_wp_error( $token ) ) {
        // Fallback inmediato
        return fc_send_via_wp_mail_with_fallbacks( $to, $subject, $html, $headers, $from_name, fc_get_fallback_senders_list() );
    }

    $mime = [];
    // Asegurar headers esenciales
    $mime[] = 'From: ' . ( $from_name ? $from_name : $from_email ) . ' <' . $from_email . '>';
    $mime[] = 'To: ' . $to;
    $mime[] = 'Subject: ' . $subject;
    $mime[] = 'MIME-Version: 1.0';
    $has_ct = false; $has_rt = false;
    foreach ( (array) $headers as $h ) {
        if ( stripos( $h, 'content-type:' ) === 0 ) { $has_ct = true; }
        if ( stripos( $h, 'reply-to:' ) === 0 ) { $has_rt = true; $mime[] = $h; }
    }
    if ( ! $has_ct ) {
        $mime[] = 'Content-Type: text/html; charset=UTF-8';
    }
    if ( ! $has_rt && ! empty( $headers ) ) {
        // Si no se detectó Reply-To en headers, intentar encontrarlo
        foreach ( (array) $headers as $h ) {
            if ( stripos( $h, 'reply-to:' ) === 0 ) { $mime[] = $h; break; }
        }
    }
    $mime[] = '';
    $mime[] = $html;

    $raw = implode( "\r\n", $mime );
    $raw_b64 = rtrim( strtr( base64_encode( $raw ), '+/', '-_' ), '=' );

    $resp = wp_remote_post( 'https://gmail.googleapis.com/gmail/v1/users/me/messages/send', [
        'headers' => [
            'Authorization' => 'Bearer ' . $token,
            'Content-Type'  => 'application/json',
        ],
        'body'    => wp_json_encode( [ 'raw' => $raw_b64 ] ),
        'timeout' => 20,
    ] );

    if ( is_wp_error( $resp ) ) {
        return false;
    }
    $code = wp_remote_retrieve_response_code( $resp );
    return ( $code >= 200 && $code < 300 );
}


