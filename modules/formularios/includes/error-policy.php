<?php
/**
 * Política común de errores para formularios públicos.
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Mensajes públicos permitidos. Nunca deben contener excepciones, URLs internas,
 * tokens, respuestas HTML ni detalles de infraestructura.
 */
function fc_form_public_error_message( $code, $reference = '' ) {
    $messages = [
        'validation'   => __( 'Revisá los campos marcados e intentá nuevamente.', 'flacso-flacso-formulario-consultas' ),
        'security'     => __( 'La sesión venció. Recargá la página e intentá nuevamente.', 'flacso-flacso-formulario-consultas' ),
        'rate_limit'   => __( 'Recibimos varios intentos seguidos. Esperá unos minutos antes de volver a enviar.', 'flacso-flacso-formulario-consultas' ),
        'unavailable'  => __( 'No pudimos enviar el formulario en este momento. Tus datos no fueron confirmados; intentá nuevamente en unos minutos.', 'flacso-flacso-formulario-consultas' ),
        'partial'      => __( 'Recibimos tus datos, pero una parte del proceso quedó pendiente. No vuelvas a enviar el formulario: nos pondremos en contacto contigo.', 'flacso-flacso-formulario-consultas' ),
    ];

    $message = $messages[ $code ] ?? $messages['unavailable'];
    if ( '' !== $reference ) {
        $message .= ' ' . sprintf(
            /* translators: %s: public support reference */
            __( 'Código de seguimiento: %s.', 'flacso-flacso-formulario-consultas' ),
            sanitize_text_field( $reference )
        );
    }

    return $message;
}

function fc_form_error_reference( $form = 'form' ) {
    $prefix = strtoupper( preg_replace( '/[^a-z0-9]/i', '', (string) $form ) );
    $prefix = substr( $prefix ?: 'FORM', 0, 8 );
    return $prefix . '-' . gmdate( 'Ymd-His' ) . '-' . strtoupper( wp_generate_password( 4, false, false ) );
}

/**
 * Avisa un error operativo al administrador por los canales configurados.
 * Los errores de validación, seguridad, spam y límite de intentos no deben llamar
 * a esta función: no requieren intervención humana.
 */
function fc_notify_form_admin_error( array $incident ) {
    $reference = sanitize_text_field( (string) ( $incident['reference'] ?? fc_form_error_reference() ) );
    $form      = sanitize_text_field( (string) ( $incident['form'] ?? 'Formulario' ) );
    $stage     = sanitize_text_field( (string) ( $incident['stage'] ?? 'procesamiento' ) );
    $detail    = sanitize_text_field( wp_strip_all_tags( (string) ( $incident['detail'] ?? 'Sin detalle' ) ) );
    $detail    = function_exists( 'mb_substr' ) ? mb_substr( $detail, 0, 700 ) : substr( $detail, 0, 700 );
    $url       = esc_url_raw( (string) ( $incident['url'] ?? '' ) );
    $email     = sanitize_email( (string) ( $incident['user_email'] ?? '' ) );

    $lines = [
        'Error en formulario: ' . $form,
        'Referencia: ' . $reference,
        'Etapa: ' . $stage,
        'Fecha: ' . current_time( 'd/m/Y H:i:s' ),
    ];
    if ( '' !== $email ) {
        $lines[] = 'Correo del usuario: ' . $email;
    }
    if ( '' !== $url ) {
        $lines[] = 'Página: ' . $url;
    }
    $lines[] = 'Detalle técnico: ' . $detail;
    $plain = implode( "\n", $lines );

    // Evita tormentas de alertas por el mismo formulario, etapa y detalle.
    $dedupe_key = 'fc_form_alert_' . md5( $form . '|' . $stage . '|' . $detail );
    if ( get_transient( $dedupe_key ) ) {
        return [ 'email' => 'deduplicated', 'telegram' => 'deduplicated' ];
    }
    set_transient( $dedupe_key, 1, 5 * MINUTE_IN_SECONDS );

    $send_email  = ! isset( $incident['send_email'] ) || (bool) $incident['send_email'];
    $send_telegram = ! isset( $incident['send_telegram'] ) || (bool) $incident['send_telegram'];
    $admin_email = sanitize_email( get_option( 'fc_destinatario_email', get_option( 'admin_email' ) ) );
    $email_sent  = false;
    if ( $send_email && is_email( $admin_email ) ) {
        $email_sent = wp_mail(
            $admin_email,
            sprintf( '[FLACSO] Error en %s (%s)', $form, $reference ),
            $plain,
            [ 'Content-Type: text/plain; charset=UTF-8' ]
        );
    }

    $telegram_sent = false;
    if ( $send_telegram && function_exists( 'fc_can_use_telegram' ) && fc_can_use_telegram() && function_exists( 'fc_send_telegram_message' ) ) {
        $telegram_lines = array_map(
            static function( $line ) {
                return htmlspecialchars( $line, ENT_QUOTES, 'UTF-8' );
            },
            $lines
        );
        $telegram_sent = fc_send_telegram_message( '<b>⚠️ Error en formulario</b>' . "\n\n" . implode( "\n", $telegram_lines ) );
    }

    error_log( '[FLACSO-FORM] ' . $plain );
    return [
        'email'    => $email_sent ? 'sent' : 'failed',
        'telegram' => $telegram_sent ? 'sent' : 'failed',
    ];
}
