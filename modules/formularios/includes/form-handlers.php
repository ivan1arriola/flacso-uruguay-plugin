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

    // Webhook de consultas (se envía directamente a la app).
    fc_send_consulta_webhook(
        [
            'nombre'      => $nombre,
            'apellido'    => $apellido,
            'email'       => $email,
            'telefono'    => $telefono,
            'asunto'      => $asunto,
            'mensaje'     => wp_strip_all_tags( $mensaje ),
            'url_referer' => wp_get_referer() ?: '',
            'ip'          => $_SERVER['REMOTE_ADDR'] ?? '',
            'user_agent'  => $_SERVER['HTTP_USER_AGENT'] ?? '',
            'fecha_envio' => current_time( 'c' ),
            'origen'      => 'wordpress_formulario_consultas',
        ]
    );

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
function fc_get_consulta_webhook_url() {
    $webhook_url = trim( (string) get_option( 'fc_consultas_webhook_url', '' ) );
    if ( '' === $webhook_url ) {
        $webhook_url = trim( (string) get_option( 'fc_oferta_webhook_url', '' ) );
    }
    if ( '' === $webhook_url && defined( 'FLACSO_WEBHOOK_URL' ) ) {
        $webhook_url = trim( (string) FLACSO_WEBHOOK_URL );
    }

    return '' === $webhook_url ? '' : esc_url_raw( $webhook_url );
}

function fc_send_consulta_webhook( array $payload ) {
    $webhook_url = fc_get_consulta_webhook_url();
    if ( '' === $webhook_url ) {
        return;
    }

    // Automatically append /general if the webhook is set to the main inquiries endpoint
    if ( preg_match( '/\/api\/consultas\/?$/', $webhook_url ) ) {
        $webhook_url = rtrim( $webhook_url, '/' ) . '/general';
    }

    if ( function_exists( 'fc_dispatch_info_request_webhook' ) ) {
        $result = fc_dispatch_info_request_webhook( $payload, [], $webhook_url );
        if ( ! $result['ok'] ) {
            error_log( '[FLACSO-FC] Webhook consultas ' . ( $result['error'] ?: 'error' ) . ( ! empty( $result['message'] ) ? ' message=' . $result['message'] : '' ) );
        }
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

    if ( function_exists( 'fc_get_info_request_webhook_token' ) ) {
        $token = fc_get_info_request_webhook_token();
        if ( '' !== $token ) {
            $args['headers']['Authorization'] = 'Bearer ' . $token;
            $args['headers']['X-FLACSO-Webhook-Token'] = $token;
        }
    }

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

function fc_send_consulta_webhook_test() {
    $webhook_url = fc_get_consulta_webhook_url();
    if ( '' === $webhook_url ) {
        return [
            'ok'      => false,
            'target'  => '',
            'code'    => 0,
            'body'    => '',
            'error'   => 'No hay endpoint configurado para consultas.',
            'message' => '',
        ];
    }

    // Automatically append /general if the webhook is set to the main inquiries endpoint
    if ( preg_match( '/\/api\/consultas\/?$/', $webhook_url ) ) {
        $webhook_url = rtrim( $webhook_url, '/' ) . '/general';
    }

    if ( function_exists( 'fc_dispatch_info_request_webhook' ) ) {
        return fc_dispatch_info_request_webhook(
            [
                'test'         => true,
                'source'       => 'wordpress_admin',
                'requested_at' => current_time( 'c' ),
            ],
            [ 'X-FLACSO-Webhook-Test' => '1' ],
            $webhook_url
        );
    }

    return [
        'ok'      => false,
        'target'  => $webhook_url,
        'code'    => 0,
        'body'    => '',
        'error'   => 'La utilidad de prueba del webhook no está disponible.',
        'message' => '',
    ];
}
