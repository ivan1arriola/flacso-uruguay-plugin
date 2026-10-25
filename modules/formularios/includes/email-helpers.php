<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }

/**
 * Configuración y helpers de plantilla de correo
 */
function fc_email_logo_url() {
        $default = 'https://flacso.edu.uy/wp-content/uploads/2026/05/logo_flacso_uruguay_20anos_blanco.png';
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




