<?php

if (!defined('ABSPATH')) {
    exit;
}

add_action('rest_api_init', 'flacso_charlas_abiertas_register_rest_routes');
function flacso_charlas_abiertas_register_rest_routes() {
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
                'default' => 'asc',
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
                'default' => 'asc',
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

    register_rest_route('flacso-charlas/v1', '/inscripcion', [
        'methods' => 'POST',
        'callback' => 'flacso_charlas_abiertas_receive_inscripcion',
        'permission_callback' => '__return_true',
    ]);
}

add_filter('rest_prepare_charla_abierta', 'flacso_charlas_abiertas_expose_charla_in_wp_json', 10, 3);
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

function flacso_charlas_abiertas_merge_data($base, $incoming) {
    if (!is_array($incoming)) {
        return $base;
    }

    if (isset($incoming['spreadsheet']) && !is_array($incoming['spreadsheet'])) {
        unset($incoming['spreadsheet']);
    }

    if (isset($incoming['spreadsheet']) && is_array($incoming['spreadsheet'])) {
        $base_spreadsheet = isset($base['spreadsheet']) && is_array($base['spreadsheet']) ? $base['spreadsheet'] : [];
        $incoming['spreadsheet'] = array_merge($base_spreadsheet, $incoming['spreadsheet']);
    }

    return array_merge($base, $incoming);
}

function flacso_charlas_abiertas_decode_json_loose($raw) {
    if (!is_scalar($raw)) {
        return null;
    }

    $text = (string) $raw;
    $text = preg_replace('/^\xEF\xBB\xBF/', '', $text);
    $text = trim($text);
    if ('' === $text) {
        return null;
    }

    $parsed = json_decode($text, true);
    if (is_array($parsed)) {
        return $parsed;
    }

    $start = strpos($text, '{');
    $end = strrpos($text, '}');
    if (false === $start || false === $end || $end <= $start) {
        return null;
    }

    $slice = substr($text, $start, $end - $start + 1);
    $parsed = json_decode($slice, true);
    return is_array($parsed) ? $parsed : null;
}

function flacso_charlas_abiertas_post_google_webhook($url, $json_body) {
    $current_url = (string) $url;
    $max_hops = 4;

    $post_args = [
        'headers' => [
            'Content-Type' => 'application/json; charset=utf-8',
            'Accept' => 'application/json',
        ],
        'body' => $json_body,
        'timeout' => 60,
        // Seguimos redirecciones manualmente para preservar método POST.
        'redirection' => 0,
        'httpversion' => '1.1',
    ];

    for ($i = 0; $i < $max_hops; $i++) {
        $response = wp_remote_post($current_url, $post_args);
        if (is_wp_error($response)) {
            return [
                'response' => $response,
                'url_used' => $current_url,
            ];
        }

        $status = (int) wp_remote_retrieve_response_code($response);
        if (!in_array($status, [301, 302, 303, 307, 308], true)) {
            return [
                'response' => $response,
                'url_used' => $current_url,
            ];
        }

        $location = wp_remote_retrieve_header($response, 'location');
        if (!is_string($location) || '' === trim($location)) {
            return [
                'response' => $response,
                'url_used' => $current_url,
            ];
        }

        $current_url = trim($location);

        // Google Apps Script suele responder 302 a macros/echo y ahí se debe seguir con GET.
        if (in_array($status, [301, 302, 303], true)) {
            $get_response = wp_remote_get($current_url, [
                'headers' => [
                    'Accept' => 'application/json',
                ],
                'timeout' => 60,
                'redirection' => 0,
                'httpversion' => '1.1',
            ]);
            return [
                'response' => $get_response,
                'url_used' => $current_url,
            ];
        }

        // 307/308 preservan método: continuar el loop y reenviar POST.
    }

    return [
        'response' => $response,
        'url_used' => $current_url,
    ];
}

function flacso_charlas_abiertas_build_spreadsheet_info() {
    $id = get_option('flacso_charlas_abiertas_spreadsheet_id', '');
    $name = get_option('flacso_charlas_abiertas_spreadsheet_name', '');

    return [
        'id' => is_scalar($id) ? (string) $id : '',
        'name' => is_scalar($name) ? (string) $name : '',
    ];
}

function flacso_charlas_abiertas_is_public_charla($post) {
    return $post
        && 'charla_abierta' === $post->post_type
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
    $zoom_join_url = (string) get_post_meta($post->ID, '_charla_zoom_join_url', true);
    $youtube_transmision_url = (string) get_post_meta($post->ID, '_charla_youtube_transmision_url', true);
    $duracion_minutos_raw = get_post_meta($post->ID, '_charla_duracion_minutos', true);
    $duracion_minutos = null;
    if ('' !== trim((string) $duracion_minutos_raw) && is_numeric($duracion_minutos_raw)) {
        $duracion_minutos = max(0, (int) $duracion_minutos_raw);
    }
    $direccion = (string) get_post_meta($post->ID, '_charla_direccion', true);
    $descripcion = (string) get_post_meta($post->ID, '_charla_descripcion', true);
    $post_featured_image = get_the_post_thumbnail_url($post, 'full');
    $inicio_timestamp = flacso_charlas_abiertas_parse_inicio_timestamp($inicio);
    $meta = [
        '_charla_inicio' => $inicio,
        '_charla_modalidad' => $modalidad ?: 'virtual',
        '_charla_zoom_join_url' => $zoom_join_url,
        '_charla_youtube_transmision_url' => $youtube_transmision_url,
        '_charla_duracion_minutos' => $duracion_minutos,
        '_charla_direccion' => $direccion,
        '_charla_descripcion' => $descripcion,
    ];

    return [
        'id' => (int) $post->ID,
        'slug' => (string) $post->post_name,
        'titulo' => get_the_title($post),
        'inicio' => $inicio,
        'inicio_timestamp' => $inicio_timestamp,
        'modalidad' => $modalidad ?: 'virtual',
        'zoom_join_url' => $zoom_join_url,
        'youtube_transmision_url' => $youtube_transmision_url,
        'duracion_minutos' => $duracion_minutos,
        'direccion' => $direccion,
        'descripcion' => $descripcion,
        'descripcion_rendered' => apply_filters('the_content', $descripcion),
        'post_featured_image' => is_string($post_featured_image) ? $post_featured_image : '',
        'meta' => $meta,
        'fecha_creacion' => mysql_to_rfc3339($post->post_date_gmt),
        'fecha_actualizacion' => mysql_to_rfc3339($post->post_modified_gmt),
        'endpoint' => rest_url('flacso-charlas/v1/charla-abierta/' . $post->ID),
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
        $order = 'asc';
    }

    $args = [
        'post_type' => 'charla_abierta',
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

function flacso_charlas_abiertas_email_domain_exists($domain) {
    $domain = strtolower(trim((string) $domain));
    if ('' === $domain) {
        return false;
    }

    $ends_with = static function ($value, $suffix) {
        $value = (string) $value;
        $suffix = (string) $suffix;
        if ('' === $suffix) {
            return true;
        }
        return substr($value, -strlen($suffix)) === $suffix;
    };

    // Proveedores confiables: no requiere chequeo DNS.
    if (
        'gmail.com' === $domain
        || 'outlook.com' === $domain
        || $ends_with($domain, '.gmail.com')
        || $ends_with($domain, '.outlook.com')
    ) {
        return true;
    }

    $cache_key = 'flacso_mail_domain_' . md5($domain);
    $cached = get_transient($cache_key);
    if (false !== $cached) {
        return '1' === $cached;
    }

    $exists = false;
    if (function_exists('checkdnsrr')) {
        $exists = checkdnsrr($domain, 'MX')
            || checkdnsrr($domain, 'A')
            || checkdnsrr($domain, 'AAAA')
            || checkdnsrr($domain, 'CNAME');
    }

    set_transient($cache_key, $exists ? '1' : '0', 6 * HOUR_IN_SECONDS);
    return $exists;
}

function flacso_charlas_abiertas_receive_inscripcion(WP_REST_Request $request) {
    $started_at = microtime(true);
    $inscripcion_id = null;
    $spreadsheet_info = flacso_charlas_abiertas_build_spreadsheet_info();

    try {
        $payload = $request->get_json_params();
        if (!is_array($payload) || empty($payload)) {
            return flacso_charlas_abiertas_error_response(
                $started_at,
                400,
                'BAD_REQUEST',
                'Solicitud inválida: falta body',
                null
            );
        }

        $evento = isset($payload['evento']) && is_array($payload['evento']) ? $payload['evento'] : [];
        $inscripcion = isset($payload['inscripcion']) && is_array($payload['inscripcion']) ? $payload['inscripcion'] : [];
        $device = isset($payload['device']) && is_array($payload['device']) ? $payload['device'] : [];
        $meta = isset($payload['meta']) && is_array($payload['meta']) ? $payload['meta'] : [];

        $errors = [];

        $evento_id_raw = $evento['id'] ?? null;
        $evento_id = absint($evento_id_raw);
        if (!$evento_id || get_post_type($evento_id) !== 'charla_abierta') {
            $errors[] = ['field' => 'evento.id', 'message' => 'ID de evento inválido.'];
        }

        $evento_titulo = sanitize_text_field($evento['titulo'] ?? ($evento_id ? get_the_title($evento_id) : ''));
        if ('' === $evento_titulo) {
            $errors[] = ['field' => 'evento.titulo', 'message' => 'Campo requerido.'];
        }

        $evento_inicio = sanitize_text_field($evento['inicio'] ?? ($evento_id ? get_post_meta($evento_id, '_charla_inicio', true) : ''));
        if ('' === $evento_inicio) {
            $errors[] = ['field' => 'evento.inicio', 'message' => 'Campo requerido.'];
        } else {
            $iso_with_offset_pattern = '/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}(?:\.\d+)?(?:Z|[+\-]\d{2}:\d{2})$/';
            if (!preg_match($iso_with_offset_pattern, $evento_inicio)) {
                $errors[] = ['field' => 'evento.inicio', 'message' => 'Debe ser ISO 8601 con zona horaria.'];
            }
        }

        $evento_modalidad = sanitize_text_field($evento['modalidad'] ?? ($evento_id ? get_post_meta($evento_id, '_charla_modalidad', true) : ''));
        if ('' === $evento_modalidad) {
            $errors[] = ['field' => 'evento.modalidad', 'message' => 'Campo requerido.'];
        }

        $nombre = sanitize_text_field($inscripcion['nombre'] ?? '');
        $apellido = sanitize_text_field($inscripcion['apellido'] ?? '');
        // Compatibilidad legacy: si llega nombre_apellido desde clientes viejos, se usa como fallback.
        $nombre_apellido = sanitize_text_field($inscripcion['nombre_apellido'] ?? '');
        if ('' === $nombre_apellido && ('' !== $nombre || '' !== $apellido)) {
            $nombre_apellido = trim($nombre . ' ' . $apellido);
        }
        if ('' === $nombre_apellido) {
            $errors[] = ['field' => 'inscripcion.nombre', 'message' => 'Nombre y apellido requeridos.'];
        }

        $correo = sanitize_email($inscripcion['correo'] ?? '');
        if ('' === $correo || !is_email($correo)) {
            $errors[] = ['field' => 'inscripcion.correo', 'message' => 'Email inválido.'];
        }

        $modalidad_asistencia = sanitize_text_field($inscripcion['modalidad_asistencia'] ?? '');
        if (!in_array($modalidad_asistencia, ['virtual', 'presencial'], true)) {
            $errors[] = ['field' => 'inscripcion.modalidad_asistencia', 'message' => 'Debe ser presencial o virtual.'];
        }

        $celular = sanitize_text_field($inscripcion['celular'] ?? '');
        if ('' !== $celular && !preg_match('/^\+?[0-9()\-\s]{6,20}$/', $celular)) {
            $errors[] = ['field' => 'inscripcion.celular', 'message' => 'Formato inválido.'];
        }

        if (!empty($errors)) {
            return flacso_charlas_abiertas_error_response(
                $started_at,
                422,
                'VALIDATION_ERROR',
                'Errores de validación',
                ['fields' => $errors]
            );
        }

        $evento_duracion_minutos = null;
        if (array_key_exists('duracion_minutos', $evento) && is_scalar($evento['duracion_minutos'])) {
            $duracion_value = trim((string) $evento['duracion_minutos']);
            if ('' !== $duracion_value && is_numeric($duracion_value)) {
                $evento_duracion_minutos = max(0, (int) $duracion_value);
            }
        } else {
            $duracion_meta = get_post_meta($evento_id, '_charla_duracion_minutos', true);
            if ('' !== trim((string) $duracion_meta) && is_numeric($duracion_meta)) {
                $evento_duracion_minutos = max(0, (int) $duracion_meta);
            }
        }

        $clean_payload = [
            'evento' => [
                'id' => is_numeric($evento_id_raw) ? $evento_id : sanitize_text_field((string) $evento_id_raw),
                'titulo' => $evento_titulo,
                'inicio' => $evento_inicio,
                'modalidad' => $evento_modalidad,
                'zoom_join_url' => esc_url_raw($evento['zoom_join_url'] ?? get_post_meta($evento_id, '_charla_zoom_join_url', true)),
                'youtube_transmision_url' => esc_url_raw($evento['youtube_transmision_url'] ?? get_post_meta($evento_id, '_charla_youtube_transmision_url', true)),
                'duracion_minutos' => $evento_duracion_minutos,
                'direccion' => sanitize_text_field($evento['direccion'] ?? get_post_meta($evento_id, '_charla_direccion', true)),
                'descripcion' => wp_kses_post($evento['descripcion'] ?? get_post_meta($evento_id, '_charla_descripcion', true)),
            ],
            'inscripcion' => [
                'nombre' => $nombre,
                'apellido' => $apellido,
                'correo' => $correo,
                'pais_residencia' => sanitize_text_field($inscripcion['pais_residencia'] ?? ''),
                'profesion' => sanitize_text_field($inscripcion['profesion'] ?? ''),
                'institucion' => sanitize_text_field($inscripcion['institucion'] ?? ''),
                'celular' => $celular,
                'modalidad_asistencia' => $modalidad_asistencia,
            ],
            'device' => [
                'ip' => isset($_SERVER['REMOTE_ADDR']) ? sanitize_text_field(wp_unslash($_SERVER['REMOTE_ADDR'])) : sanitize_text_field($device['ip'] ?? ''),
                'user_agent' => sanitize_text_field($device['user_agent'] ?? ''),
                'referer' => esc_url_raw($device['referer'] ?? ''),
                'origin' => esc_url_raw($device['origin'] ?? ''),
                'device_type' => sanitize_text_field($device['device_type'] ?? ''),
                'screen_width' => is_scalar($device['screen_width'] ?? null) ? absint($device['screen_width']) : 0,
                'screen_height' => is_scalar($device['screen_height'] ?? null) ? absint($device['screen_height']) : 0,
                'language' => sanitize_text_field($device['language'] ?? ''),
                'timezone' => sanitize_text_field($device['timezone'] ?? ''),
            ],
            'meta' => [
                'wp_user_logged_in' => !empty($meta['wp_user_logged_in']),
                'timestamp_client' => sanitize_text_field($meta['timestamp_client'] ?? ''),
                'host_post_id' => is_scalar($meta['host_post_id'] ?? null) ? absint($meta['host_post_id']) : 0,
                'post_featured_image' => esc_url_raw($meta['post_featured_image'] ?? ''),
            ],
        ];

        $inscripcion_id = wp_generate_uuid4() . '-' . (string) round(microtime(true) * 1000);
        do_action('flacso_charlas_abiertas_inscripcion_recibida', $clean_payload, $request, $inscripcion_id);

        $webhook_url = flacso_charlas_abiertas_get_webhook_url();
        $webhook_url_used = $webhook_url;
        $webhook_code = null;
        $webhook_data = null;
        if (!empty($webhook_url)) {
            $encoded_payload = wp_json_encode($clean_payload);
            $post_result = flacso_charlas_abiertas_post_google_webhook($webhook_url, $encoded_payload);
            $webhook_response = isset($post_result['response']) ? $post_result['response'] : null;
            if (!empty($post_result['url_used']) && is_string($post_result['url_used'])) {
                $webhook_url_used = $post_result['url_used'];
            }
            if (is_wp_error($webhook_response)) {
                return flacso_charlas_abiertas_error_response(
                    $started_at,
                    500,
                    'INTERNAL_ERROR',
                    'Error reenviando la inscripción al webhook',
                    [
                        'message' => $webhook_response->get_error_message(),
                        'webhook_url' => $webhook_url_used,
                    ]
                );
            }

            $status_code = wp_remote_retrieve_response_code($webhook_response);
            $raw_body = wp_remote_retrieve_body($webhook_response);

            $parsed = flacso_charlas_abiertas_decode_json_loose($raw_body);

            if (!is_array($parsed)) {
                if ($status_code < 200 || $status_code >= 300) {
                    $raw_body_text = is_scalar($raw_body) ? (string) $raw_body : '';
                    $is_google_400_html = (
                        false !== stripos($raw_body_text, '<!DOCTYPE html')
                        && false !== stripos($raw_body_text, 'Error 400')
                        && false !== stripos($raw_body_text, 'google')
                    );

                    if ($is_google_400_html) {
                        return flacso_charlas_abiertas_error_response(
                            $started_at,
                            500,
                            'INTERNAL_ERROR',
                            'Google Apps Script devolvió HTTP 400 al webhook',
                            [
                                'status' => $status_code,
                                'webhook_url' => $webhook_url_used,
                            ]
                        );
                    }

                    return flacso_charlas_abiertas_error_response(
                        $started_at,
                        500,
                        'INTERNAL_ERROR',
                        'El webhook devolvió un estado no exitoso',
                        [
                            'status' => $status_code,
                            'body' => $raw_body_text ? substr($raw_body_text, 0, 500) : null,
                            'webhook_url' => $webhook_url_used,
                        ]
                    );
                }
                return flacso_charlas_abiertas_error_response(
                    $started_at,
                    500,
                    'INTERNAL_ERROR',
                    'El webhook devolvió una respuesta inválida',
                    ['status' => $status_code]
                );
            }

            // Tolerancia: algunos proveedores pueden devolver HTTP no-2xx con body JSON válido.
            if (($status_code < 200 || $status_code >= 300) && (!array_key_exists('ok', $parsed) || !empty($parsed['ok']))) {
                $status_code = 200;
            }

            if ($status_code < 200 || $status_code >= 300) {
                $remote_error = isset($parsed['error']) && is_array($parsed['error']) ? $parsed['error'] : null;
                $remote_message = isset($remote_error['message']) ? sanitize_text_field((string) $remote_error['message']) : 'El webhook devolvió un estado no exitoso';
                return flacso_charlas_abiertas_error_response(
                    $started_at,
                    500,
                    isset($parsed['code']) ? sanitize_text_field((string) $parsed['code']) : 'INTERNAL_ERROR',
                    $remote_message,
                    [
                        'status' => $status_code,
                        'details' => isset($remote_error['details']) ? $remote_error['details'] : null,
                        'webhook_url' => $webhook_url_used,
                    ]
                );
            }

            if (array_key_exists('ok', $parsed) && empty($parsed['ok'])) {
                $remote_error = isset($parsed['error']) && is_array($parsed['error']) ? $parsed['error'] : null;
                $remote_message = isset($remote_error['message']) ? sanitize_text_field((string) $remote_error['message']) : 'Error del webhook';
                return flacso_charlas_abiertas_error_response(
                    $started_at,
                    422,
                    isset($parsed['code']) ? sanitize_text_field((string) $parsed['code']) : 'VALIDATION_ERROR',
                    $remote_message,
                    isset($remote_error['details']) ? $remote_error['details'] : null
                );
            }

            $webhook_code = isset($parsed['code']) ? sanitize_text_field((string) $parsed['code']) : null;
            $webhook_data = isset($parsed['data']) && is_array($parsed['data']) ? $parsed['data'] : null;
        }

        $data = [
            'inscripcion_id' => $inscripcion_id,
            'duplicada' => false,
            'saved' => true,
            'telegram' => !empty($webhook_url) ? 'sent' : 'failed',
            'email' => 'skipped',
            'email_sender' => null,
            'gmail_message_url' => null,
            'spreadsheet' => $spreadsheet_info,
        ];

        $data = flacso_charlas_abiertas_merge_data($data, $webhook_data);
        $response_code = $webhook_code ?: (!empty($data['duplicada']) ? 'DUPLICADA' : 'CONFIRMADA');

        return flacso_charlas_abiertas_success_response(
            $started_at,
            $response_code,
            $data
        );
    } catch (Throwable $e) {
        return flacso_charlas_abiertas_error_response(
            $started_at,
            500,
            'INTERNAL_ERROR',
            'Error en el procesamiento de la inscripción',
            [
                'message' => $e->getMessage(),
                'inscripcion_id' => $inscripcion_id,
                'spreadsheet_id' => $spreadsheet_info['id'],
                'spreadsheet_name' => $spreadsheet_info['name'],
            ]
        );
    }
}
