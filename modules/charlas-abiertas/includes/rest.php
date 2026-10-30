<?php

if (!defined('ABSPATH')) {
    exit;
}

add_action('rest_api_init', 'flacso_charlas_abiertas_register_rest_routes');
function flacso_charlas_abiertas_register_rest_routes() {
    register_rest_route('flacso-charlas/v1', '/eventos', [
        'methods' => 'GET',
        'callback' => 'flacso_charlas_abiertas_list_charlas',
        'permission_callback' => '__return_true',
        'args' => [
            'page' => ['default' => 1, 'sanitize_callback' => 'absint'],
            'per_page' => ['default' => 10, 'sanitize_callback' => 'absint'],
            'search' => ['sanitize_callback' => 'sanitize_text_field'],
            'modalidad' => ['sanitize_callback' => 'sanitize_text_field'],
            'desde' => ['sanitize_callback' => 'sanitize_text_field', 'validate_callback' => 'flacso_charlas_abiertas_validate_iso8601_arg'],
            'hasta' => ['sanitize_callback' => 'sanitize_text_field', 'validate_callback' => 'flacso_charlas_abiertas_validate_iso8601_arg'],
            'order' => ['default' => 'desc', 'sanitize_callback' => 'sanitize_text_field'],
        ],
    ]);

    register_rest_route('flacso-charlas/v1', '/eventos/(?P<id>\d+)', [
        'methods' => 'GET',
        'callback' => 'flacso_charlas_abiertas_get_charla',
        'permission_callback' => '__return_true',
        'args' => [
            'id' => ['required' => true, 'sanitize_callback' => 'absint'],
        ],
    ]);

    register_rest_route('flacso-charlas/v1', '/charla-abierta', [
        'methods' => 'GET',
        'callback' => 'flacso_charlas_abiertas_list_charlas',
        'permission_callback' => '__return_true',
        'args' => [
            'page' => [
                'default' => 1,
                'sanitize_callback' => 'absint',
            ],
            'per_page' => [
                'default' => 10,
                'sanitize_callback' => 'absint',
            ],
            'search' => [
                'sanitize_callback' => 'sanitize_text_field',
            ],
            'modalidad' => [
                'sanitize_callback' => 'sanitize_text_field',
            ],
            'desde' => [
                'sanitize_callback' => 'sanitize_text_field',
                'validate_callback' => 'flacso_charlas_abiertas_validate_iso8601_arg',
            ],
            'hasta' => [
                'sanitize_callback' => 'sanitize_text_field',
                'validate_callback' => 'flacso_charlas_abiertas_validate_iso8601_arg',
            ],
            'order' => [
                'default' => 'desc',
                'sanitize_callback' => 'sanitize_text_field',
            ],
        ],
    ]);

    register_rest_route('flacso-charlas/v1', '/charla-abierta/(?P<id>\d+)', [
        'methods' => 'GET',
        'callback' => 'flacso_charlas_abiertas_get_charla',
        'permission_callback' => '__return_true',
        'args' => [
            'id' => [
                'required' => true,
                'sanitize_callback' => 'absint',
            ],
        ],
    ]);

    // Alias de compatibilidad hacia atrás.
    register_rest_route('flacso-charlas/v1', '/charlas', [
        'methods' => 'GET',
        'callback' => 'flacso_charlas_abiertas_list_charlas',
        'permission_callback' => '__return_true',
        'args' => [
            'page' => [
                'default' => 1,
                'sanitize_callback' => 'absint',
            ],
            'per_page' => [
                'default' => 10,
                'sanitize_callback' => 'absint',
            ],
            'search' => [
                'sanitize_callback' => 'sanitize_text_field',
            ],
            'modalidad' => [
                'sanitize_callback' => 'sanitize_text_field',
            ],
            'desde' => [
                'sanitize_callback' => 'sanitize_text_field',
                'validate_callback' => 'flacso_charlas_abiertas_validate_iso8601_arg',
            ],
            'hasta' => [
                'sanitize_callback' => 'sanitize_text_field',
                'validate_callback' => 'flacso_charlas_abiertas_validate_iso8601_arg',
            ],
            'order' => [
                'default' => 'desc',
                'sanitize_callback' => 'sanitize_text_field',
            ],
        ],
    ]);

    register_rest_route('flacso-charlas/v1', '/charlas/(?P<id>\d+)', [
        'methods' => 'GET',
        'callback' => 'flacso_charlas_abiertas_get_charla',
        'permission_callback' => '__return_true',
        'args' => [
            'id' => [
                'required' => true,
                'sanitize_callback' => 'absint',
            ],
        ],
    ]);
}

add_filter('rest_prepare_evento', 'flacso_charlas_abiertas_expose_charla_in_wp_json', 10, 3);
function flacso_charlas_abiertas_expose_charla_in_wp_json($response, $post, $request) {
    if (!($response instanceof WP_REST_Response)) {
        return $response;
    }

    $data = $response->get_data();
    if (!is_array($data)) {
        return $response;
    }

    $charla_data = flacso_charlas_abiertas_build_charla_data($post);
    if (!is_array($charla_data)) {
        return $response;
    }

    // Exponer en wp-json las mismas claves del endpoint custom.
    $response->set_data(array_merge($data, $charla_data));
    return $response;
}

function flacso_charlas_abiertas_validate_iso8601_arg($value, $request, $param) {
    if (null === $value || '' === $value) {
        return true;
    }

    try {
        new DateTimeImmutable((string) $value);
        return true;
    } catch (Exception $e) {
        return new WP_Error(
            'rest_invalid_param',
            sprintf('El parámetro %s debe ser una fecha ISO 8601 válida.', $param)
        );
    }
}

function flacso_charlas_abiertas_processing_ms($started_at) {
    return round((microtime(true) - $started_at) * 1000, 2);
}

function flacso_charlas_abiertas_error_response($started_at, $http_status, $code, $message, $details = null) {
    return new WP_REST_Response([
        'ok' => false,
        'code' => $code,
        'data' => null,
        'error' => [
            'message' => $message,
            'details' => $details,
        ],
        'processing_ms' => flacso_charlas_abiertas_processing_ms($started_at),
    ], $http_status);
}

function flacso_charlas_abiertas_success_response($started_at, $code, $data) {
    return new WP_REST_Response([
        'ok' => true,
        'code' => $code,
        'data' => $data,
        'error' => null,
        'processing_ms' => flacso_charlas_abiertas_processing_ms($started_at),
    ], 200);
}

function flacso_charlas_abiertas_is_public_charla($post) {
    return $post
        && in_array($post->post_type, ['evento', 'charla_abierta'], true)
        && 'publish' === $post->post_status;
}

function flacso_charlas_abiertas_parse_inicio_timestamp($inicio) {
    if (!is_scalar($inicio) || '' === trim((string) $inicio)) {
        return null;
    }

    try {
        return (new DateTimeImmutable((string) $inicio))->getTimestamp();
    } catch (Exception $e) {
        return null;
    }
}

function flacso_charlas_abiertas_build_charla_data($post) {
    $post = get_post($post);
    if (!$post) {
        return null;
    }

    $inicio = (string) get_post_meta($post->ID, '_charla_inicio', true);
    $modalidad = (string) get_post_meta($post->ID, '_charla_modalidad', true);
    $plataforma_reunion = function_exists('flacso_charlas_abiertas_normalize_reunion_platform')
        ? flacso_charlas_abiertas_normalize_reunion_platform(get_post_meta($post->ID, '_charla_plataforma_reunion', true))
        : 'zoom';
    $reunion_join_url = (string) get_post_meta($post->ID, '_charla_reunion_join_url', true);
    $zoom_join_url = (string) get_post_meta($post->ID, '_charla_zoom_join_url', true);
    if ('' === $reunion_join_url && '' !== $zoom_join_url) {
        $reunion_join_url = $zoom_join_url;
    }
    $youtube_transmision_url = (string) get_post_meta($post->ID, '_charla_youtube_transmision_url', true);
    $duracion_minutos_raw = get_post_meta($post->ID, '_charla_duracion_minutos', true);
    $duracion_minutos = null;
    if ('' !== trim((string) $duracion_minutos_raw) && is_numeric($duracion_minutos_raw)) {
        $duracion_minutos = max(0, (int) $duracion_minutos_raw);
    }
    $lugar_nombre = (string) get_post_meta($post->ID, '_charla_lugar_nombre', true);
    $direccion = (string) get_post_meta($post->ID, '_charla_direccion', true);
    $google_maps_url = (string) get_post_meta($post->ID, '_charla_google_maps_url', true);
    $descripcion = function_exists('flacso_charlas_abiertas_get_charla_descripcion_html')
        ? flacso_charlas_abiertas_get_charla_descripcion_html($post)
        : ((string) get_post_meta($post->ID, '_charla_descripcion', true) ?: (string) $post->post_content);
    $post_featured_image = get_the_post_thumbnail_url($post, 'full');
    $inicio_timestamp = flacso_charlas_abiertas_parse_inicio_timestamp($inicio);
    $is_unified_event = 'evento' === $post->post_type;
    $post_id = $is_unified_event ? 0 : (int) get_post_meta($post->ID, '_charla_post_id', true);
    $evento_id = $is_unified_event ? (int) $post->ID : (int) get_post_meta($post->ID, '_charla_evento_id', true);
    $sync_post_enabled = function_exists('flacso_charlas_abiertas_should_sync_post')
        ? flacso_charlas_abiertas_should_sync_post($post->ID)
        : !empty(get_post_meta($post->ID, '_charla_sync_post', true));
    $sync_evento_enabled = function_exists('flacso_charlas_abiertas_should_sync_evento')
        ? flacso_charlas_abiertas_should_sync_evento($post->ID)
        : !empty(get_post_meta($post->ID, '_charla_sync_evento', true));
    $ocultar_post = get_post_meta($post->ID, '_charla_ocultar_post', true);
    $ocultar_evento = get_post_meta($post->ID, '_charla_ocultar_evento', true);

    $meta = [
        '_charla_inicio' => $inicio,
        '_charla_modalidad' => $modalidad ?: 'virtual',
        '_charla_plataforma_reunion' => $plataforma_reunion,
        '_charla_reunion_join_url' => $reunion_join_url,
        '_charla_zoom_join_url' => $zoom_join_url ?: $reunion_join_url,
        '_charla_youtube_transmision_url' => $youtube_transmision_url,
        '_charla_duracion_minutos' => $duracion_minutos,
        '_charla_lugar_nombre' => $lugar_nombre,
        '_charla_direccion' => $direccion,
        '_charla_google_maps_url' => $google_maps_url,
        '_charla_descripcion' => $descripcion,
        '_charla_post_id' => $post_id,
        '_charla_evento_id' => $evento_id,
        '_evento_mostrar_web' => !empty(get_post_meta($post->ID, '_evento_mostrar_web', true)),
        '_charla_ocultar_post' => !empty($ocultar_post),
        '_charla_ocultar_evento' => !empty($ocultar_evento),
    ];

    return [
        'id' => (int) $post->ID,
        'slug' => (string) $post->post_name,
        'titulo' => get_the_title($post),
        'inicio' => $inicio,
        'inicio_timestamp' => $inicio_timestamp,
        'modalidad' => $modalidad ?: 'virtual',
        'plataforma_reunion' => $plataforma_reunion,
        'reunion_join_url' => $reunion_join_url,
        'zoom_join_url' => $zoom_join_url ?: $reunion_join_url,
        'youtube_transmision_url' => $youtube_transmision_url,
        'duracion_minutos' => $duracion_minutos,
        'lugar_nombre' => $lugar_nombre,
        'direccion' => $direccion,
        'google_maps_url' => $google_maps_url,
        'descripcion' => $descripcion,
        'descripcion_rendered' => apply_filters('the_content', $descripcion),
        'post_featured_image' => is_string($post_featured_image) ? $post_featured_image : '',
        'post_id' => $post_id,
        'post_permalink' => $post_id > 0 && get_post_status($post_id) !== 'trash' ? get_permalink($post_id) : '',
        'evento_id' => $evento_id,
        'evento_permalink' => $evento_id > 0 && get_post_status($evento_id) !== 'trash' ? get_permalink($evento_id) : '',
        'meta' => $meta,
        'fecha_creacion' => mysql_to_rfc3339($post->post_date_gmt),
        'fecha_actualizacion' => mysql_to_rfc3339($post->post_modified_gmt),
        'endpoint' => rest_url('flacso-charlas/v1/eventos/' . $post->ID),
    ];
}

function flacso_charlas_abiertas_list_charlas(WP_REST_Request $request) {
    $started_at = microtime(true);
    $page = max(1, absint($request->get_param('page')));
    $per_page = absint($request->get_param('per_page'));
    $per_page = $per_page > 0 ? min(100, $per_page) : 10;
    $search = sanitize_text_field((string) $request->get_param('search'));
    $modalidad = sanitize_text_field((string) $request->get_param('modalidad'));
    $desde = sanitize_text_field((string) $request->get_param('desde'));
    $hasta = sanitize_text_field((string) $request->get_param('hasta'));
    $order = strtolower(sanitize_text_field((string) $request->get_param('order')));

    if (!in_array($order, ['asc', 'desc'], true)) {
        $order = 'desc';
    }

    $args = [
        'post_type' => 'evento',
        'post_status' => 'publish',
        'posts_per_page' => $per_page,
        'paged' => $page,
        'orderby' => 'meta_value',
        'meta_key' => '_charla_inicio',
        'order' => strtoupper($order),
        'no_found_rows' => false,
    ];

    if ('' !== $search) {
        $args['s'] = $search;
    }

    $meta_query = [];

    if (in_array($modalidad, ['virtual', 'presencial', 'hibrida'], true)) {
        $meta_query[] = [
            'key' => '_charla_modalidad',
            'value' => $modalidad,
            'compare' => '=',
        ];
    }

    if ('' !== $desde) {
        $meta_query[] = [
            'key' => '_charla_inicio',
            'value' => $desde,
            'compare' => '>=',
            'type' => 'CHAR',
        ];
    }

    if ('' !== $hasta) {
        $meta_query[] = [
            'key' => '_charla_inicio',
            'value' => $hasta,
            'compare' => '<=',
            'type' => 'CHAR',
        ];
    }

    if (!empty($meta_query)) {
        if (count($meta_query) > 1) {
            $args['meta_query'] = array_merge(['relation' => 'AND'], $meta_query);
        } else {
            $args['meta_query'] = $meta_query;
        }
    }

    $query = new WP_Query($args);
    $items = [];

    foreach ($query->posts as $post) {
        $items[] = flacso_charlas_abiertas_build_charla_data($post);
    }

    return flacso_charlas_abiertas_success_response(
        $started_at,
        'CHARLAS_LIST',
        [
            'items' => $items,
            'pagination' => [
                'page' => $page,
                'per_page' => $per_page,
                'total_items' => (int) $query->found_posts,
                'total_pages' => (int) $query->max_num_pages,
            ],
            'filters' => [
                'search' => $search,
                'modalidad' => $modalidad,
                'desde' => $desde,
                'hasta' => $hasta,
                'order' => $order,
            ],
        ]
    );
}

function flacso_charlas_abiertas_get_charla(WP_REST_Request $request) {
    $started_at = microtime(true);
    $charla_id = absint($request->get_param('id'));
    $post = get_post($charla_id);

    if (!$post || !flacso_charlas_abiertas_is_public_charla($post)) {
        return flacso_charlas_abiertas_error_response(
            $started_at,
            404,
            'NOT_FOUND',
            'La charla solicitada no existe o no está publicada.',
            null
        );
    }

    return flacso_charlas_abiertas_success_response(
        $started_at,
        'CHARLA_FOUND',
        flacso_charlas_abiertas_build_charla_data($post)
    );
}


