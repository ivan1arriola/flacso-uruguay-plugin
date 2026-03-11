<?php
/**
 * Funciones auxiliares del plugin
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Parseo básico de navegador/SO.
 */
function fc_parse_user_agent_simple( $ua ) {
	$ua = strtolower( (string) $ua );
	$browser = 'Desconocido';
	$os = 'Desconocido';

	if ( strpos( $ua, 'windows nt 10' ) !== false || strpos( $ua, 'windows nt 11' ) !== false ) {
		$os = 'Windows 10/11';
	} elseif ( strpos( $ua, 'windows nt 6.1' ) !== false ) {
		$os = 'Windows 7';
	} elseif ( strpos( $ua, 'mac os x' ) !== false ) {
		$os = 'macOS';
	} elseif ( strpos( $ua, 'android' ) !== false ) {
		$os = 'Android';
	} elseif ( strpos( $ua, 'iphone' ) !== false || strpos( $ua, 'ipad' ) !== false ) {
		$os = 'iOS';
	} elseif ( strpos( $ua, 'linux' ) !== false ) {
		$os = 'Linux';
	}

	if ( strpos( $ua, 'edg' ) !== false ) {
		$browser = 'Edge';
	} elseif ( strpos( $ua, 'chrome' ) !== false && strpos( $ua, 'chromium' ) === false ) {
		$browser = 'Chrome';
	} elseif ( strpos( $ua, 'firefox' ) !== false ) {
		$browser = 'Firefox';
	} elseif ( strpos( $ua, 'safari' ) !== false && strpos( $ua, 'chrome' ) === false ) {
		$browser = 'Safari';
	} elseif ( strpos( $ua, 'opr' ) !== false ) {
		$browser = 'Opera';
	}

	return [
		'browser' => $browser,
		'os'      => $os,
	];
}

/**
 * Resuelve el endpoint destino para solicitudes de informacion.
 *
 * Prioridad:
 * 1) opcion dedicada de oferta
 * 2) opcion de webhook de consultas (fallback de menu)
 */
function fc_get_info_request_webhook_url() {
    $candidate = trim( (string) get_option( 'fc_oferta_webhook_url', '' ) );
    if ( '' !== $candidate ) {
        return esc_url_raw( $candidate );
    }

    $candidate = trim( (string) get_option( 'fc_consultas_webhook_url', '' ) );
    if ( '' !== $candidate ) {
        return esc_url_raw( $candidate );
    }

    return '';
}

/**
 * Normaliza el payload de Solicitud de Informacion para la API del panel.
 * Mantiene tambien los campos legacy para compatibilidad.
 *
 * @param array $data Datos sanitizados desde el formulario.
 * @return array
 */
function fc_build_info_request_webhook_payload( array $data ) {
    $offer_id = isset( $data['id_pagina'] ) ? (string) absint( $data['id_pagina'] ) : '';
    if ( '0' === $offer_id ) {
        $offer_id = '';
    }

    $inquiry_at = isset( $data['fecha_envio'] ) ? sanitize_text_field( (string) $data['fecha_envio'] ) : '';
    if ( '' === $inquiry_at ) {
        $inquiry_at = current_time( 'mysql' );
    }

    return array_merge(
        $data,
        [
            // Campos canónicos (PanelFLACSOConsultas /api/inquiries)
            'email'           => isset( $data['correo'] ) ? sanitize_email( $data['correo'] ) : '',
            'first_name'      => isset( $data['nombre'] ) ? sanitize_text_field( $data['nombre'] ) : '',
            'last_name'       => isset( $data['apellido'] ) ? sanitize_text_field( $data['apellido'] ) : '',
            'country'         => isset( $data['pais'] ) ? sanitize_text_field( $data['pais'] ) : '',
            'profession'      => isset( $data['profesion'] ) ? sanitize_text_field( $data['profesion'] ) : '',
            'education_level' => isset( $data['nivel_academico'] ) ? sanitize_text_field( $data['nivel_academico'] ) : '',
            'offer_id'        => $offer_id,
            'offer_name'      => isset( $data['titulo_posgrado'] ) ? sanitize_text_field( $data['titulo_posgrado'] ) : '',
            'offer_type'      => '',
            'source'          => 'Web',
            'inquiry_at'      => $inquiry_at,

            // Alias utiles para compatibilidad con importadores previos
            'post_id'         => $offer_id,
            'oferta'          => isset( $data['titulo_posgrado'] ) ? sanitize_text_field( $data['titulo_posgrado'] ) : '',
            'origen'          => 'Web',
            'nivel_educativo' => isset( $data['nivel_academico'] ) ? sanitize_text_field( $data['nivel_academico'] ) : '',
        ]
    );
}

/**
 * Envia la solicitud de informacion al endpoint externo.
 *
 * @param array $data Datos sanitizados del formulario.
 * @return array { ok, target, code, body, error }
 */
function fc_send_info_request_webhook( array $data ) {
    $target = fc_get_info_request_webhook_url();
    if ( '' === $target ) {
        return [
            'ok'     => false,
            'target' => '',
            'code'   => 0,
            'body'   => '',
            'error'  => 'No hay endpoint configurado para solicitud de informacion.',
        ];
    }

    $payload = fc_build_info_request_webhook_payload( $data );
    $args    = [
        'body'        => wp_json_encode( $payload ),
        'headers'     => [ 'Content-Type' => 'application/json' ],
        'timeout'     => defined( 'FLACSO_WEBHOOK_TIMEOUT' ) ? (int) FLACSO_WEBHOOK_TIMEOUT : 25,
        'redirection' => 3,
        'blocking'    => true,
        'httpversion' => '1.1',
        'data_format' => 'body',
    ];

    $response = wp_remote_post( $target, $args );
    if ( is_wp_error( $response ) ) {
        return [
            'ok'     => false,
            'target' => $target,
            'code'   => 0,
            'body'   => '',
            'error'  => $response->get_error_message(),
        ];
    }

    $code = (int) wp_remote_retrieve_response_code( $response );
    $body = (string) wp_remote_retrieve_body( $response );

    return [
        'ok'     => $code >= 200 && $code < 300,
        'target' => $target,
        'code'   => $code,
        'body'   => $body,
        'error'  => $code >= 200 && $code < 300 ? '' : 'HTTP ' . $code,
    ];
}

/**
 * Calcula la URL /confirmacion-consulta/ basada en la página que contiene el formulario.
 */
function fc_get_gracias_url_from_referer() {
    $referer = wp_get_referer();
    if ( ! $referer ) {
        return home_url( '/confirmacion-consulta/' );
    }

    $parts = wp_parse_url( $referer );
    if ( empty( $parts['host'] ) ) {
        return home_url( '/confirmacion-consulta/' );
    }

    $scheme = isset( $parts['scheme'] ) ? $parts['scheme'] . '://' : '//';
    $host   = $parts['host'];
    $path   = isset( $parts['path'] ) ? untrailingslashit( $parts['path'] ) : '';

    // Si el path es raíz, usar /confirmacion-consulta/, si no, añadir segmento.
    if ( '' === $path || '/' === $path ) {
        $confirmacion_path = '/confirmacion-consulta/';
    } else {
        $confirmacion_path = trailingslashit( $path ) . 'confirmacion-consulta/';
    }

    return $scheme . $host . $confirmacion_path;
}

/**
 * Registra una consulta en la base de datos (CPT) y retorna los identificadores generados.
 *
 * @param array $payload Datos de la consulta (campos clave).
 * @return array {
 *     @type int    $post_id        ID del post insertado (0 on failure).
 *     @type string $control_number Número de control asignado (vacío si no se pudo guardar).
 *     @type string $error          Mensaje de error cuando falla la inserción.
 * }
 */
function fc_record_consulta_entry( array $payload ) {
    if ( ! post_type_exists( 'fc_consulta' ) && function_exists( 'fc_register_cpt' ) ) {
        fc_register_cpt();
    }

    $defaults = [
        'nombre'            => '',
        'apellido'          => '',
        'email'             => '',
        'telefono'          => '',
        'asunto'            => '',
        'mensaje'           => '',
        'pais'              => '',
        'nivel_academico'   => '',
        'profesion'         => '',
        'url_base'          => '',
        'url_referer'       => '',
        'page_id'           => 0,
        'titulo_posgrado'   => '',
        'ip'                => '',
        'user_agent'        => '',
        'fecha_envio'       => '',
    ];

    $data = wp_parse_args( $payload, $defaults );
    $nombre   = sanitize_text_field( $data['nombre'] );
    $apellido = sanitize_text_field( $data['apellido'] );
    $email    = sanitize_email( $data['email'] );
    $telefono = sanitize_text_field( $data['telefono'] );
    $asunto   = sanitize_text_field( $data['asunto'] );
    $mensaje  = isset( $data['mensaje'] ) ? wp_kses_post( $data['mensaje'] ) : '';
    $pais     = sanitize_text_field( $data['pais'] );
    $nivel    = sanitize_text_field( $data['nivel_academico'] );
    $profesion = sanitize_text_field( $data['profesion'] );
    $url_base = esc_url_raw( $data['url_base'] );
    $url_referer = esc_url_raw( $data['url_referer'] );
    $page_id = absint( $data['page_id'] );
    $titulo_posgrado = sanitize_text_field( $data['titulo_posgrado'] );
    $user_agent = sanitize_text_field( $data['user_agent'] );
    $ip_address = sanitize_text_field( $data['ip'] );

    $post_date = current_time( 'mysql' );
    $ts_local  = current_time( 'timestamp' );
    if ( ! empty( $data['fecha_envio'] ) ) {
        $parsed = strtotime( $data['fecha_envio'] );
        if ( $parsed !== false ) {
            // Mantener la fecha original para el post_date y meta legibles
            $post_date = gmdate( 'Y-m-d H:i:s', $parsed );
            $ts_local  = $parsed;
        }
    }

    $title = $asunto ? $asunto : sprintf(
        __( 'Consulta de %1$s %2$s', 'flacso-flacso-formulario-consultas' ),
        $nombre,
        $apellido
    );

    $post_args = [
        'post_type'    => 'fc_consulta',
        'post_status'  => 'publish',
        'post_title'   => $title,
        'post_content' => $mensaje,
        'post_author'  => 0,
        'post_date'    => $post_date,
    ];

    $post_id = wp_insert_post( $post_args, true );

    if ( is_wp_error( $post_id ) || ! $post_id ) {
        $message = is_wp_error( $post_id ) ? $post_id->get_error_message() : 'wp_insert_post returned falsy value';
        return [
            'post_id' => 0,
            'control_number' => '',
            'error' => $message,
        ];
    }

    update_post_meta( $post_id, 'fc_nombre', $nombre );
    update_post_meta( $post_id, 'fc_apellido', $apellido );
    update_post_meta( $post_id, 'fc_email', $email );
    update_post_meta( $post_id, 'fc_telefono', $telefono );
    update_post_meta( $post_id, 'fc_asunto', $asunto );
    update_post_meta( $post_id, 'fc_mensaje', $mensaje );
    update_post_meta( $post_id, 'fc_pais', $pais );
    update_post_meta( $post_id, 'fc_nivel_academico', $nivel );
    update_post_meta( $post_id, 'fc_profesion', $profesion );
    update_post_meta( $post_id, 'fc_url_base', $url_base );
    update_post_meta( $post_id, 'fc_url_referer', $url_referer );
    update_post_meta( $post_id, 'fc_programa_id', $page_id );
    update_post_meta( $post_id, 'fc_programa_titulo', $titulo_posgrado );
    update_post_meta( $post_id, 'fc_ip', $ip_address );
    update_post_meta( $post_id, 'fc_user_agent', $user_agent );
    $ua_info = fc_parse_user_agent_simple( $user_agent );
    update_post_meta( $post_id, 'fc_navegador', $ua_info['browser'] );
    update_post_meta( $post_id, 'fc_sistema_operativo', $ua_info['os'] );

    // Guardar fecha y hora legibles (locale de WP)
    $fecha_legible = date_i18n( 'l, d \\d\\e F \\d\\e Y', $ts_local );
    $hora_legible  = date_i18n( 'g:i a', $ts_local );
    update_post_meta( $post_id, 'fc_fecha', $fecha_legible );
    update_post_meta( $post_id, 'fc_hora', $hora_legible );

    $last_control = (int) get_option( 'fc_last_control_number', 0 );
    $next_control = $last_control + 1;
    update_option( 'fc_last_control_number', $next_control );
    $control_number = sprintf( 'FC-%06d', $next_control );
    update_post_meta( $post_id, 'fc_control_number', $control_number );

    update_post_meta( $post_id, 'fc_fecha_envio', $post_date );

    return [
        'post_id' => $post_id,
        'control_number' => $control_number,
        'error' => '',
    ];
}

/**
 * Registra una solicitud de información (oferta académica) en CPT dedicado.
 *
 * @param array $payload Datos del formulario de oferta.
 * @return array { post_id, control_number, error }
 */
function fc_record_info_request_entry( array $payload ) {
    // Registrar CPT si aún no existe
    if ( ! post_type_exists( 'fc_info_request' ) && function_exists( 'fc_register_cpt_info_request' ) ) {
        fc_register_cpt_info_request();
    }

    $defaults = [
        'nombre'          => '',
        'apellido'        => '',
        'correo'          => '',
        'pais'            => '',
        'nivel_academico' => '',
        'profesion'       => '',
        'programa_id'     => 0,
        'programa_titulo' => '',
        'url_base'        => '',
        'url_referer'     => '',
        'fecha_envio'     => '',
        'ip'              => '',
        'user_agent'      => '',
    ];
    $data = wp_parse_args( $payload, $defaults );

    $nombre    = sanitize_text_field( $data['nombre'] );
    $apellido  = sanitize_text_field( $data['apellido'] );
    $correo    = sanitize_email( $data['correo'] );
    $pais      = sanitize_text_field( $data['pais'] );
    $nivel     = sanitize_text_field( $data['nivel_academico'] );
    $profesion = sanitize_text_field( $data['profesion'] );
    $pid       = absint( $data['programa_id'] );
    $ptitulo   = sanitize_text_field( $data['programa_titulo'] );
    $url_base  = esc_url_raw( $data['url_base'] );
    $url_ref   = esc_url_raw( $data['url_referer'] );
    $ip        = sanitize_text_field( $data['ip'] );
    $ua        = sanitize_text_field( $data['user_agent'] );

    $ts_local  = current_time( 'timestamp' );
    $post_date = current_time( 'mysql' );
    if ( ! empty( $data['fecha_envio'] ) ) {
        $parsed = strtotime( $data['fecha_envio'] );
        if ( $parsed !== false ) {
            $ts_local  = $parsed;
            $post_date = gmdate( 'Y-m-d H:i:s', $parsed );
        }
    }
    $fecha_legible = date_i18n( 'l, d \\d\\e F \\d\\e Y', $ts_local );
    $hora_legible  = date_i18n( 'g:i a', $ts_local );

    $title = $ptitulo ? sprintf( __( 'Solicitud de %s', 'flacso-flacso-formulario-consultas' ), $ptitulo ) : __( 'Solicitud de información', 'flacso-flacso-formulario-consultas' );

    $post_args = [
        'post_type'    => 'fc_info_request',
        'post_status'  => 'publish',
        'post_title'   => $title,
        'post_content' => '',
        'post_author'  => 0,
        'post_date'    => $post_date,
    ];

    // Permitir insertar aunque el usuario no esté autenticado.
    $grant_caps = function( $allcaps, $caps, $args, $user ) {
        $allcaps['edit_posts'] = true;
        $allcaps['publish_posts'] = true;
        $allcaps['edit_fc_info_requests'] = true;
        $allcaps['edit_fc_info_request'] = true;
        return $allcaps;
    };
    add_filter( 'user_has_cap', $grant_caps, 10, 4 );
    $post_id = wp_insert_post( $post_args, true );
    remove_filter( 'user_has_cap', $grant_caps, 10 );
    if ( is_wp_error( $post_id ) || ! $post_id ) {
        $message = is_wp_error( $post_id ) ? $post_id->get_error_message() : 'wp_insert_post returned falsy value';
        return [ 'post_id' => 0, 'control_number' => '', 'error' => $message ];
    }

    update_post_meta( $post_id, 'fc_nombre', $nombre );
    update_post_meta( $post_id, 'fc_apellido', $apellido );
    update_post_meta( $post_id, 'fc_email', $correo );
    update_post_meta( $post_id, 'fc_pais', $pais );
    update_post_meta( $post_id, 'fc_nivel_academico', $nivel );
    update_post_meta( $post_id, 'fc_profesion', $profesion );
    update_post_meta( $post_id, 'fc_programa_id', $pid );
    update_post_meta( $post_id, 'fc_programa_titulo', $ptitulo );
    update_post_meta( $post_id, 'fc_url_base', $url_base );
    update_post_meta( $post_id, 'fc_url_referer', $url_ref );
    update_post_meta( $post_id, 'fc_ip', $ip );
    update_post_meta( $post_id, 'fc_user_agent', $ua );
    $ua_info = fc_parse_user_agent_simple( $ua );
    update_post_meta( $post_id, 'fc_navegador', $ua_info['browser'] );
    update_post_meta( $post_id, 'fc_sistema_operativo', $ua_info['os'] );
    update_post_meta( $post_id, 'fc_fecha', $fecha_legible );
    update_post_meta( $post_id, 'fc_hora', $hora_legible );

    $last_control = (int) get_option( 'fc_last_info_control_number', 0 );
    $next_control = $last_control + 1;
    update_option( 'fc_last_info_control_number', $next_control );
    $control_number = sprintf( 'FI-%06d', $next_control );
    update_post_meta( $post_id, 'fc_control_number', $control_number );
    update_post_meta( $post_id, 'fc_fecha_envio', $post_date );

    return [
        'post_id' => $post_id,
        'control_number' => $control_number,
        'error' => '',
    ];
}

/**
 * Agrega la query var 'fc_confirmacion_consulta' para el sistema de rewrite
 */
function fc_add_confirmacion_consulta_query_var( $vars ) {
    $vars[] = 'fc_confirmacion_consulta';
    return $vars;
}
add_filter( 'query_vars', 'fc_add_confirmacion_consulta_query_var' );

/**
 * REST API: importar solicitudes de información (lotes desde planillas).
 * Endpoint: POST /wp-json/flacso/v1/info-requests/import
 */
function fc_register_info_request_import_route() {
    register_rest_route(
        'flacso/v1',
        '/info-requests/import',
        [
            'methods'             => 'POST',
            'permission_callback' => function () {
                return current_user_can( 'manage_options' );
            },
            'callback'            => 'fc_api_import_info_requests',
            'args'                => [
                'items' => [
                    'type'     => 'array',
                    'required' => true,
                ],
            ],
        ]
    );
}
add_action( 'rest_api_init', 'fc_register_info_request_import_route' );

function fc_api_import_info_requests( WP_REST_Request $request ) {
    $items = $request->get_param( 'items' );
    if ( ! is_array( $items ) ) {
        return new WP_REST_Response( [ 'error' => 'items must be an array' ], 400 );
    }

    $results = [];
    foreach ( $items as $index => $row ) {
        if ( ! is_array( $row ) ) {
            $results[] = [ 'index' => $index, 'status' => 'error', 'message' => 'row must be object/array' ];
            continue;
        }
        $stored = fc_record_info_request_entry(
            [
                'nombre'          => $row['nombre'] ?? '',
                'apellido'        => $row['apellido'] ?? '',
                'correo'          => $row['correo'] ?? '',
                'pais'            => $row['pais'] ?? '',
                'nivel_academico' => $row['nivel_academico'] ?? '',
                'profesion'       => $row['profesion'] ?? '',
                'programa_id'     => $row['programa_id'] ?? 0,
                'programa_titulo' => $row['programa_titulo'] ?? '',
                'url_base'        => $row['url_base'] ?? '',
                'url_referer'     => $row['url_referer'] ?? '',
                'fecha_envio'     => $row['fecha_envio'] ?? '',
                'ip'              => $row['ip'] ?? '',
                'user_agent'      => $row['user_agent'] ?? '',
            ]
        );
        $results[] = [
            'index'    => $index,
            'status'   => empty( $stored['error'] ) ? 'ok' : 'error',
            'post_id'  => $stored['post_id'],
            'control'  => $stored['control_number'],
            'message'  => $stored['error'],
        ];
    }

    return new WP_REST_Response(
        [
            'imported' => array_sum( array_map( fn( $r ) => $r['status'] === 'ok' ? 1 : 0, $results ) ),
            'results'  => $results,
        ],
        200
    );
}
