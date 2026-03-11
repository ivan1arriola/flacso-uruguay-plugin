<?php
/**
 * Manejo del envío del formulario
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Maneja el envío del formulario.
 */
function fc_handle_form_submit() {
    if ( ! isset( $_POST['fc_nonce'] ) || ! wp_verify_nonce( wp_unslash( $_POST['fc_nonce'] ), 'fc_form_submit' ) ) {
        wp_die( esc_html__( 'Solicitud no válida.', 'flacso-flacso-formulario-consultas' ) );
    }

    // Honeypots
    if ( ! empty( $_POST['website'] ) || ! empty( $_POST['fc_company'] ) ) {
        wp_safe_redirect( add_query_arg( 'fc_exito', 1, wp_get_referer() ?: home_url() ) );
        exit;
    }

    // Validación de reCAPTCHA v3
    if ( get_option( 'fc_use_recaptcha', '0' ) === '1' ) {
        $token = isset( $_POST['fc_recaptcha_token'] ) ? sanitize_text_field( wp_unslash( $_POST['fc_recaptcha_token'] ) ) : '';
        if ( empty( $token ) || ! fc_verify_recaptcha( $token ) ) {
            wp_safe_redirect( add_query_arg( 'fc_exito', 1, wp_get_referer() ?: home_url() ) );
            exit;
        }
    }

    $nombre   = isset( $_POST['fc_nombre'] ) ? sanitize_text_field( wp_unslash( $_POST['fc_nombre'] ) ) : '';
    $apellido = isset( $_POST['fc_apellido'] ) ? sanitize_text_field( wp_unslash( $_POST['fc_apellido'] ) ) : '';
    $email    = isset( $_POST['fc_email'] ) ? sanitize_email( wp_unslash( $_POST['fc_email'] ) ) : '';
    $telefono = isset( $_POST['fc_telefono_full'] ) ? sanitize_text_field( wp_unslash( $_POST['fc_telefono_full'] ) ) : ( isset( $_POST['fc_telefono'] ) ? sanitize_text_field( wp_unslash( $_POST['fc_telefono'] ) ) : '' );
    $asunto   = isset( $_POST['fc_asunto'] ) ? sanitize_text_field( wp_unslash( $_POST['fc_asunto'] ) ) : '';
    $mensaje  = isset( $_POST['fc_mensaje'] ) ? wp_kses_post( wp_unslash( $_POST['fc_mensaje'] ) ) : '';

    $text_min_2 = function( $str ) { return strlen( trim( (string) $str ) ) >= 2; };
    $phone_ok = function( $str ) {
        $digits = preg_replace( '/[^0-9]/', '', (string) $str );
        return (bool) preg_match( '/^[+0-9\s\-\(\)]+$/', (string) $str ) && strlen( $digits ) >= 2;
    };

    if ( ! is_email( $email )
        || ! $text_min_2( $nombre )
        || ! $text_min_2( $apellido )
        || ! $text_min_2( $asunto )
        || ! $text_min_2( $mensaje )
        || ! $phone_ok( $telefono )
    ) {
        wp_die( esc_html__( 'Datos incompletos o inválidos.', 'flacso-flacso-formulario-consultas' ) );
    }

    // Validación anti-spam adicional
    if ( fc_is_spam_content( $nombre, $apellido, $email, $asunto, $mensaje ) ) {
        wp_safe_redirect( add_query_arg( 'fc_exito', 1, wp_get_referer() ?: home_url() ) );
        exit;
    }

    $stored = fc_record_consulta_entry( [
        'nombre'      => $nombre,
        'apellido'    => $apellido,
        'email'       => $email,
        'telefono'    => $telefono,
        'asunto'      => $asunto,
        'mensaje'     => $mensaje,
        'ip'          => $_SERVER['REMOTE_ADDR'] ?? '',
        'user_agent'  => $_SERVER['HTTP_USER_AGENT'] ?? '',
    ] );

    if ( ! empty( $stored['error'] ) ) {
        error_log( '[FLACSO-FC] Error al guardar la consulta: ' . $stored['error'] );
    }

    $post_id = isset( $stored['post_id'] ) ? (int) $stored['post_id'] : 0;
    $control_number = $stored['control_number'] ?? '';

    // Webhook de consultas (si está configurado).
    fc_send_consulta_webhook(
        [
            'control_number' => $control_number,
            'consulta_id'    => $post_id,
            'nombre'         => $nombre,
            'apellido'       => $apellido,
            'email'          => $email,
            'telefono'       => $telefono,
            'asunto'         => $asunto,
            'mensaje'        => wp_strip_all_tags( $mensaje ),
            'url_referer'    => wp_get_referer() ?: '',
            'ip'             => $_SERVER['REMOTE_ADDR'] ?? '',
            'user_agent'     => $_SERVER['HTTP_USER_AGENT'] ?? '',
            'fecha_envio'    => current_time( 'c' ),
            'origen'         => 'wordpress_formulario_consultas',
        ]
    );

    // Envío de correos
    $admin_email = get_option( 'fc_destinatario_email', get_option( 'admin_email' ) );
    $site_name   = wp_specialchars_decode( get_bloginfo( 'name' ), ENT_QUOTES );
    $asunto_admin   = get_option( 'fc_asunto_admin', '' );
    $asunto_usuario = get_option( 'fc_asunto_usuario', '' );
    if ( empty( $asunto_admin ) ) {
        $asunto_admin = sprintf( __( 'Nueva consulta desde %s', 'flacso-flacso-formulario-consultas' ), $site_name );
    }
    if ( empty( $asunto_usuario ) ) {
        $asunto_usuario = __( 'Hemos recibido tu consulta', 'flacso-flacso-formulario-consultas' );
    }

    // Incluir el asunto del formulario en el subject de ambos correos
    $subject_admin_final = trim( $asunto_admin . ': ' . $asunto );
    $subject_user_final  = trim( $asunto_usuario . ': ' . $asunto );

    $body_admin_inner =
        '<p><strong>' . esc_html__( 'Nueva consulta recibida:', 'flacso-flacso-formulario-consultas' ) . '</strong></p>' .
        '<ul>' .
            ( ! empty( $control_number ) ? '<li><strong>' . esc_html__( 'Número de control', 'flacso-flacso-formulario-consultas' ) . ':</strong> ' . esc_html( $control_number ) . '</li>' : '' ) .
            '<li><strong>' . esc_html__( 'ID de consulta', 'flacso-flacso-formulario-consultas' ) . ':</strong> ' . intval( $post_id ) . '</li>' .
        '</ul>' .
        '<ul>' .
            '<li><strong>' . esc_html__( 'Asunto', 'flacso-flacso-formulario-consultas' ) . ':</strong> ' . esc_html( $asunto ) . '</li>' .
            '<li><strong>' . esc_html__( 'Nombre', 'flacso-flacso-formulario-consultas' ) . ':</strong> ' . esc_html( $nombre ) . '</li>' .
            '<li><strong>' . esc_html__( 'Apellido', 'flacso-flacso-formulario-consultas' ) . ':</strong> ' . esc_html( $apellido ) . '</li>' .
            '<li><strong>' . esc_html__( 'Email', 'flacso-flacso-formulario-consultas' ) . ':</strong> ' . esc_html( $email ) . '</li>' .
            '<li><strong>' . esc_html__( 'Teléfono', 'flacso-flacso-formulario-consultas' ) . ':</strong> ' . esc_html( $telefono ) . '</li>' .
        '</ul>' .
        '<div class="fc-divider"></div>' .
        '<p><strong>' . esc_html__( 'Consulta:', 'flacso-flacso-formulario-consultas' ) . '</strong><br>' . wpautop( esc_html( $mensaje ) ) . '</p>' .
        '<p style="font-size:12px;color:#6b7280;margin-top:12px">' . ( ! empty( $control_number ) ? esc_html__( 'Número de control', 'flacso-flacso-formulario-consultas' ) . ': ' . esc_html( $control_number ) . ' | ' : '' ) . esc_html__( 'ID de consulta', 'flacso-flacso-formulario-consultas' ) . ': ' . intval( $post_id ) . '</p>';
    $body_admin = function_exists('fc_wrap_email_html') ? fc_wrap_email_html( $body_admin_inner, $site_name ) : $body_admin_inner;

    $body_usuario_inner =
        '<p>' . sprintf( esc_html__( 'Hola %1$s, gracias por contactarte con %2$s.', 'flacso-flacso-formulario-consultas' ), esc_html( $nombre ), esc_html( $site_name ) ) . '</p>' .
        '<p>' . esc_html__( 'Hemos recibido tu consulta y te responderemos a la brevedad.', 'flacso-flacso-formulario-consultas' ) . '</p>' .
        '<div class="fc-divider"></div>' .
        '<p><strong>' . esc_html__( 'Resumen enviado:', 'flacso-flacso-formulario-consultas' ) . '</strong></p>' .
        '<ul>' .
            ( ! empty( $control_number ) ? '<li><strong>' . esc_html__( 'Número de control', 'flacso-flacso-formulario-consultas' ) . ':</strong> ' . esc_html( $control_number ) . '</li>' : '' ) .
            '<li>' . esc_html__( 'Asunto', 'flacso-flacso-formulario-consultas' ) . ': ' . esc_html( $asunto ) . '</li>' .
            '<li>' . esc_html__( 'Nombre', 'flacso-flacso-formulario-consultas' ) . ': ' . esc_html( $nombre . ' ' . $apellido ) . '</li>' .
            '<li>' . esc_html__( 'Teléfono', 'flacso-flacso-formulario-consultas' ) . ': ' . esc_html( $telefono ) . '</li>' .
        '</ul>' .
        '<p><strong>' . esc_html__( 'Consulta:', 'flacso-flacso-formulario-consultas' ) . '</strong><br>' . wpautop( esc_html( $mensaje ) ) . '</p>';
    $body_usuario = function_exists('fc_wrap_email_html') ? fc_wrap_email_html( $body_usuario_inner, __( 'Confirmación de consulta', 'flacso-flacso-formulario-consultas' ) ) : $body_usuario_inner;

    $headers_admin = [ 'Content-Type: text/html; charset=UTF-8', 'Reply-To: ' . $nombre . ' ' . $apellido . ' <' . $email . '>' ];
    $headers_user  = [ 'Content-Type: text/html; charset=UTF-8', 'Reply-To: ' . $site_name . ' <' . $admin_email . '>' ];

    // Construir From estándar
    $from_name  = $site_name;
    $from_email = get_option( 'fc_google_impersonated', 'noreply@flacso.edu.uy' );

    $fallback_senders = function_exists('fc_get_fallback_senders_list') ? fc_get_fallback_senders_list() : [];

    // Preferir Gmail API; si falla, intentar fallback rotativo
    if ( fc_can_use_gmail_api() ) {
        if ( is_email( $admin_email ) ) {
            $ok_admin = fc_send_via_gmail_api( $admin_email, $subject_admin_final, $body_admin, $headers_admin, $from_name, $from_email );
            if ( ! $ok_admin ) {
                fc_send_via_wp_mail_with_fallbacks( $admin_email, $subject_admin_final, $body_admin, $headers_admin, $from_name, $fallback_senders );
            }
        }
        $ok_user = fc_send_via_gmail_api( $email, $subject_user_final, $body_usuario, $headers_user, $from_name, $from_email );
        if ( ! $ok_user ) {
            fc_send_via_wp_mail_with_fallbacks( $email, $subject_user_final, $body_usuario, $headers_user, $from_name, $fallback_senders );
        }
    } else {
        if ( is_email( $admin_email ) ) {
            fc_send_via_wp_mail_with_fallbacks( $admin_email, $subject_admin_final, $body_admin, $headers_admin, $from_name, $fallback_senders );
        }
        fc_send_via_wp_mail_with_fallbacks( $email, $subject_user_final, $body_usuario, $headers_user, $from_name, $fallback_senders );
    }

    // Notificación Telegram (si está activo)
    if ( function_exists( 'fc_can_use_telegram' ) && fc_can_use_telegram() ) {
        if ( function_exists( 'fc_build_telegram_message' ) ) {
            // Obtener información de user-agent si existe
            $user_agent = isset( $_SERVER['HTTP_USER_AGENT'] ) ? sanitize_text_field( wp_unslash( $_SERVER['HTTP_USER_AGENT'] ) ) : '';
            $ua_info = ! empty( $user_agent ) ? fc_parse_user_agent_simple( $user_agent ) : [];
            $navegador = $ua_info['browser'] ?? '';
            $sistema_operativo = $ua_info['os'] ?? '';
            
            $tg_message = fc_build_telegram_message( $nombre, $apellido, $email, $telefono, $asunto, $mensaje, $post_id, $navegador, $sistema_operativo );
            fc_send_telegram_message( $tg_message );
        }
    }

    $redirect_base = fc_get_gracias_url_from_referer();
    $redirect = add_query_arg(
        [
            'fc_confirmacion_consulta' => 1,
            'fc_nombre' => rawurlencode( $nombre ),
            'fc_apellido' => rawurlencode( $apellido ),
            'fc_email'  => rawurlencode( $email ),
            'fc_asunto' => rawurlencode( $asunto ),
        ],
        $redirect_base
    );

    wp_safe_redirect( $redirect );
    exit;
}

add_action( 'admin_post_nopriv_fc_submit_consulta', 'fc_handle_form_submit' );
add_action( 'admin_post_fc_submit_consulta', 'fc_handle_form_submit' );

/**
 * Envía la consulta a un webhook externo vía JSON (best-effort).
 *
 * @param array $payload Datos de la consulta.
 */
function fc_send_consulta_webhook( array $payload ) {
    $webhook_url = trim( (string) get_option( 'fc_consultas_webhook_url', '' ) );
    if ( '' === $webhook_url ) {
        $webhook_url = trim( (string) get_option( 'fc_oferta_webhook_url', '' ) );
    }
    if ( '' === $webhook_url && defined( 'FLACSO_WEBHOOK_URL' ) ) {
        $webhook_url = trim( (string) FLACSO_WEBHOOK_URL );
    }
    if ( '' === $webhook_url ) {
        return;
    }

    $args = [
        'body'        => wp_json_encode( $payload ),
        'headers'     => [ 'Content-Type' => 'application/json' ],
        'timeout'     => 20,
        'redirection' => 3,
        'blocking'    => true,
        'httpversion' => '1.1',
        'data_format' => 'body',
    ];

    $response = wp_remote_post( esc_url_raw( $webhook_url ), $args );
    if ( is_wp_error( $response ) ) {
        error_log( '[FLACSO-FC] Webhook consultas error: ' . $response->get_error_message() );
        return;
    }

    $code = (int) wp_remote_retrieve_response_code( $response );
    $body = (string) wp_remote_retrieve_body( $response );
    if ( $code < 200 || $code >= 300 ) {
        error_log( '[FLACSO-FC] Webhook consultas HTTP ' . $code . ' body=' . substr( $body, 0, 500 ) );
        return;
    }

    // Si responde JSON y marca error explícito, dejar registro.
    $json = json_decode( $body, true );
    if ( is_array( $json ) && isset( $json['success'] ) && false === $json['success'] ) {
        error_log( '[FLACSO-FC] Webhook consultas respondió success=false: ' . substr( $body, 0, 500 ) );
    }
}
