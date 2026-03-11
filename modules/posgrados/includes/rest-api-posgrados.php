<?php

if (!defined('ABSPATH')) {
    exit;
}

if (!function_exists('flacso_pos_rest_capability')) {
    function flacso_pos_rest_capability(): string {
        return class_exists('FLACSO_Posgrados_Fields') ? FLACSO_Posgrados_Fields::CAPABILITY : 'edit_pages';
    }
}

if (!function_exists('flacso_pos_rest_check_cap')) {
    function flacso_pos_rest_check_cap(string $cap) {
        if (!is_user_logged_in()) {
            return new WP_Error(
                'rest_forbidden',
                __('Autenticacion requerida.', 'flacso-posgrados-docentes'),
                ['status' => rest_authorization_required_code()]
            );
        }

        if (!current_user_can($cap)) {
            return new WP_Error(
                'rest_forbidden',
                __('No tienes permisos suficientes.', 'flacso-posgrados-docentes'),
                ['status' => 403]
            );
        }

        return true;
    }
}

if (!function_exists('flacso_pos_rest_can_read')) {
    function flacso_pos_rest_can_read() {
        return flacso_pos_rest_check_cap(flacso_pos_rest_capability());
    }
}

if (!function_exists('flacso_pos_rest_can_edit')) {
    function flacso_pos_rest_can_edit() {
        return flacso_pos_rest_check_cap('edit_others_pages');
    }
}

if (!function_exists('flacso_pos_rest_get_payload')) {
    function flacso_pos_rest_get_payload(WP_REST_Request $request): array {
        $payload = $request->get_json_params();
        if (!is_array($payload)) {
            $payload = $request->get_params();
        }
        return is_array($payload) ? $payload : [];
    }
}

if (!function_exists('flacso_pos_rest_allowed_ids')) {
    function flacso_pos_rest_allowed_ids(): array {
        if (!class_exists('FLACSO_Posgrados_Pages')) {
            return [];
        }
        return FLACSO_Posgrados_Pages::get_allowed_page_ids();
    }
}

if (!function_exists('flacso_pos_rest_is_allowed_posgrado')) {
    function flacso_pos_rest_is_allowed_posgrado(int $post_id): bool {
        $allowed = flacso_pos_rest_allowed_ids();
        if (!$allowed) {
            return false;
        }
        return in_array($post_id, $allowed, true);
    }
}

if (!function_exists('flacso_pos_rest_is_valid_parent')) {
    function flacso_pos_rest_is_valid_parent(int $parent_id): bool {
        if (!$parent_id || !class_exists('FLACSO_Posgrados_Pages')) {
            return false;
        }

        $root_id = (int) FLACSO_Posgrados_Pages::ROOT_PAGE_ID;
        $excluded = (int) FLACSO_Posgrados_Pages::EXCLUDED_BRANCH_ID;

        if ($parent_id === $excluded) {
            return false;
        }

        $parent = get_post($parent_id);
        if (!$parent || $parent->post_type !== FLACSO_Posgrados_Fields::POST_TYPE) {
            return false;
        }

        $parent_parent = (int) wp_get_post_parent_id($parent_id);
        return $parent_parent === $root_id;
    }
}

if (!function_exists('flacso_pos_rest_meta_keys')) {
    function flacso_pos_rest_meta_keys(): array {
        static $keys = null;
        if ($keys !== null) {
            return $keys;
        }

        $defaults = [
            'tipo_posgrado',
            'fecha_inicio',
            'proximo_inicio',
            'calendario_anio',
            'calendario_link',
            'malla_curricular_link',
            'imagen_promocional',
            'posgrado_activo',
            'abreviacion',
            'duracion',
            'link',
        ];

        if (!class_exists('FLACSO_Posgrados_Fields')) {
            $keys = $defaults;
            return $keys;
        }

        $fields = FLACSO_Posgrados_Fields::get_fields();
        $keys = [];
        foreach ($fields as $key => $config) {
            if (($config['source'] ?? '') === 'meta') {
                $keys[] = $key;
            }
        }

        if (!$keys) {
            $keys = $defaults;
        }

        return $keys;
    }
}

if (!function_exists('flacso_pos_rest_sanitize_meta')) {
function flacso_pos_rest_sanitize_meta(string $key, $value) {
    if (!class_exists('FLACSO_Posgrados_Fields')) {
        return is_scalar($value) ? sanitize_text_field((string) $value) : $value;
    }

        switch ($key) {
            case 'tipo_posgrado':
                return FLACSO_Posgrados_Fields::sanitize_tipo($value);
            case 'fecha_inicio':
            case 'proximo_inicio':
                return FLACSO_Posgrados_Fields::sanitize_date($value);
            case 'calendario_anio':
                return FLACSO_Posgrados_Fields::sanitize_year($value);
            case 'calendario_link':
            case 'malla_curricular_link':
            case 'link':
                return FLACSO_Posgrados_Fields::sanitize_url($value);
            case 'imagen_promocional':
                return FLACSO_Posgrados_Fields::sanitize_media_id($value);
            case 'posgrado_activo':
                return (bool) $value;
            case 'abreviacion':
                return FLACSO_Posgrados_Fields::sanitize_abreviacion($value);
            case 'duracion':
                return FLACSO_Posgrados_Fields::sanitize_duracion($value);
        default:
            return is_scalar($value) ? sanitize_text_field((string) $value) : $value;
        }
    }
}

if (!function_exists('flacso_pos_rest_featured_image_payload')) {
    function flacso_pos_rest_featured_image_payload(int $post_id): array {
        $thumbnail_id = (int) get_post_thumbnail_id($post_id);
        if ($thumbnail_id === 0) {
            return [];
        }

        $url = wp_get_attachment_image_url($thumbnail_id, 'full') ?: wp_get_attachment_url($thumbnail_id);
        $srcset = wp_get_attachment_image_srcset($thumbnail_id, 'full');
        $sizes = wp_get_attachment_image_sizes($thumbnail_id, 'full');
        $alt = get_post_meta($thumbnail_id, '_wp_attachment_image_alt', true);

        return [
            'id' => $thumbnail_id,
            'url' => $url ?: '',
            'srcset' => $srcset ?: '',
            'sizes' => $sizes ?: '',
            'alt' => is_string($alt) ? $alt : '',
        ];
    }
}

if (!function_exists('flacso_pos_rest_equipo_payload')) {
    function flacso_pos_rest_equipo_payload(int $term_id): array {
        if (function_exists('dp_rest_build_equipo_payload')) {
            return dp_rest_build_equipo_payload($term_id);
        }

        $term = get_term($term_id, 'equipo-docente');
        if (!$term || is_wp_error($term)) {
            return [];
        }

        return [
            'id' => (int) $term->term_id,
            'name' => $term->name,
            'slug' => $term->slug,
            'link' => get_term_link($term),
        ];
    }
}

if (!function_exists('flacso_pos_rest_build_payload')) {
    function flacso_pos_rest_build_payload($post): array {
        $post = is_numeric($post) ? get_post((int) $post) : $post;
        if (!$post || $post->post_type !== FLACSO_Posgrados_Fields::POST_TYPE) {
            return [];
        }

        $data = [
            'id' => (int) $post->ID,
            'title' => $post->post_title,
            'slug' => $post->post_name,
            'status' => $post->post_status,
            'parent_id' => (int) $post->post_parent,
            'link' => get_permalink($post),
            'date' => $post->post_date_gmt,
            'modified' => $post->post_modified_gmt,
            'excerpt' => $post->post_excerpt,
            'content' => $post->post_content,
        ];

        foreach (flacso_pos_rest_meta_keys() as $key) {
            $value = get_post_meta($post->ID, $key, true);
            if ($key === 'posgrado_activo') {
                $value = !empty($value);
            }
            $data[$key] = $value;
        }

        $image_id = (int) ($data['imagen_promocional'] ?? 0);
        if ($image_id) {
            $data['imagen_promocional_url'] = wp_get_attachment_image_url($image_id, 'large');
        }

        $data['featured_image'] = flacso_pos_rest_featured_image_payload($post->ID);
        $data['featured_image_url'] = $data['featured_image']['url'] ?? '';

        $equipo_ids = function_exists('dp_get_equipo_term_ids_by_page')
            ? dp_get_equipo_term_ids_by_page($post->ID)
            : [];
        if ($equipo_ids) {
            $equipos = [];
            foreach ($equipo_ids as $term_id) {
                $equipos[] = flacso_pos_rest_equipo_payload((int) $term_id);
            }
            $data['equipos'] = $equipos;
        } else {
            $data['equipos'] = [];
        }

        return $data;
    }
}

if (!function_exists('flacso_pos_rest_get_posgrados')) {
    function flacso_pos_rest_get_posgrados(WP_REST_Request $request) {
        $per_page = max(1, (int) $request->get_param('per_page'));
        $per_page = min(100, $per_page);
        $page = max(1, (int) $request->get_param('page'));
        $search = sanitize_text_field((string) $request->get_param('search'));
        $status = sanitize_key((string) $request->get_param('status'));
        $tipo = sanitize_text_field((string) $request->get_param('tipo'));
        $activo = $request->get_param('activo');
        $parent_id = (int) $request->get_param('parent_id');

        $allowed_ids = flacso_pos_rest_allowed_ids();
        if (!$allowed_ids) {
            return new WP_REST_Response(['total' => 0, 'pages' => 0, 'items' => []], 200);
        }

        $args = [
            'post_type' => FLACSO_Posgrados_Fields::POST_TYPE,
            'post_status' => $status ?: 'any',
            'posts_per_page' => $per_page,
            'paged' => $page,
            'post__in' => $allowed_ids,
            'orderby' => 'menu_order title',
            'order' => 'ASC',
        ];

        if ($search !== '') {
            $args['s'] = $search;
        }

        if ($parent_id) {
            $args['post_parent'] = $parent_id;
        }

        $meta_query = [];
        if ($tipo !== '') {
            $meta_query[] = [
                'key' => 'tipo_posgrado',
                'value' => $tipo,
            ];
        }
        if ($activo !== null && $activo !== '') {
            $meta_query[] = [
                'key' => 'posgrado_activo',
                'value' => $activo ? '1' : '0',
                'compare' => '=',
            ];
        }
        if ($meta_query) {
            $args['meta_query'] = $meta_query;
        }

        $query = new WP_Query($args);
        $items = [];
        foreach ($query->posts as $post) {
            $items[] = flacso_pos_rest_build_payload($post);
        }

        return new WP_REST_Response([
            'total' => (int) $query->found_posts,
            'pages' => (int) $query->max_num_pages,
            'items' => $items,
        ], 200);
    }
}

if (!function_exists('flacso_pos_rest_get_posgrado')) {
    function flacso_pos_rest_get_posgrado(WP_REST_Request $request) {
        $post_id = (int) $request['id'];
        $post = get_post($post_id);
        if (!$post || $post->post_type !== FLACSO_Posgrados_Fields::POST_TYPE) {
            return new WP_Error('posgrado_not_found', __('El posgrado solicitado no existe.', 'flacso-posgrados-docentes'), ['status' => 404]);
        }

        if (!flacso_pos_rest_is_allowed_posgrado($post_id)) {
            return new WP_Error('posgrado_not_allowed', __('El posgrado solicitado no pertenece al mapa configurado.', 'flacso-posgrados-docentes'), ['status' => 404]);
        }

        return new WP_REST_Response(flacso_pos_rest_build_payload($post), 200);
    }
}

if (!function_exists('flacso_pos_rest_update_meta_fields')) {
    function flacso_pos_rest_update_meta_fields(int $post_id, array $params): void {
        $meta_keys = flacso_pos_rest_meta_keys();
        $meta_payload = [];

        if (isset($params['meta']) && is_array($params['meta'])) {
            $meta_payload = $params['meta'];
        }

        foreach ($meta_keys as $key) {
            if (array_key_exists($key, $params)) {
                $meta_payload[$key] = $params[$key];
            }
        }

        foreach ($meta_payload as $key => $value) {
            if (!in_array($key, $meta_keys, true)) {
                continue;
            }
            $clean = flacso_pos_rest_sanitize_meta($key, $value);
            update_post_meta($post_id, $key, $clean);
        }
    }
}

if (!function_exists('flacso_pos_rest_create_posgrado')) {
    function flacso_pos_rest_create_posgrado(WP_REST_Request $request) {
        $params = flacso_pos_rest_get_payload($request);

        $title = sanitize_text_field($params['title'] ?? '');
        if ($title === '') {
            return new WP_Error('posgrado_missing_title', __('Debes enviar un titulo.', 'flacso-posgrados-docentes'), ['status' => 400]);
        }

        $parent_id = absint($params['parent_id'] ?? 0);
        if (!$parent_id || !flacso_pos_rest_is_valid_parent($parent_id)) {
            return new WP_Error('posgrado_missing_parent', __('Debes enviar un parent_id valido de la categoria de posgrados.', 'flacso-posgrados-docentes'), ['status' => 400]);
        }

        $post_data = [
            'post_type' => FLACSO_Posgrados_Fields::POST_TYPE,
            'post_title' => $title,
            'post_status' => sanitize_key($params['status'] ?? 'publish'),
            'post_name' => sanitize_title($params['slug'] ?? ''),
            'post_content' => wp_kses_post($params['content'] ?? ''),
            'post_excerpt' => wp_kses_post($params['excerpt'] ?? ($params['post_excerpt'] ?? '')),
            'post_parent' => $parent_id,
        ];

        if ($post_data['post_name'] === '') {
            unset($post_data['post_name']);
        }

        $post_id = wp_insert_post($post_data, true);
        if (is_wp_error($post_id)) {
            return $post_id;
        }

        flacso_pos_rest_update_meta_fields($post_id, $params);

        return new WP_REST_Response(flacso_pos_rest_build_payload($post_id), 201);
    }
}

if (!function_exists('flacso_pos_rest_update_posgrado')) {
    function flacso_pos_rest_update_posgrado(WP_REST_Request $request) {
        $post_id = (int) $request['id'];
        $post = get_post($post_id);
        if (!$post || $post->post_type !== FLACSO_Posgrados_Fields::POST_TYPE) {
            return new WP_Error('posgrado_not_found', __('El posgrado solicitado no existe.', 'flacso-posgrados-docentes'), ['status' => 404]);
        }

        if (!flacso_pos_rest_is_allowed_posgrado($post_id)) {
            return new WP_Error('posgrado_not_allowed', __('El posgrado solicitado no pertenece al mapa configurado.', 'flacso-posgrados-docentes'), ['status' => 404]);
        }

        $params = flacso_pos_rest_get_payload($request);
        $update = ['ID' => $post_id];

        if (array_key_exists('title', $params)) {
            $update['post_title'] = sanitize_text_field($params['title']);
        }
        if (array_key_exists('slug', $params)) {
            $update['post_name'] = sanitize_title($params['slug']);
        }
        if (array_key_exists('status', $params)) {
            $update['post_status'] = sanitize_key($params['status']);
        }
        if (array_key_exists('content', $params)) {
            $update['post_content'] = wp_kses_post($params['content']);
        }
        if (array_key_exists('excerpt', $params) || array_key_exists('post_excerpt', $params)) {
            $update['post_excerpt'] = wp_kses_post($params['excerpt'] ?? $params['post_excerpt']);
        }
        if (array_key_exists('parent_id', $params)) {
            $parent_id = absint($params['parent_id']);
            if (!$parent_id || !flacso_pos_rest_is_valid_parent($parent_id)) {
                return new WP_Error('posgrado_invalid_parent', __('parent_id invalido para posgrados.', 'flacso-posgrados-docentes'), ['status' => 400]);
            }
            $update['post_parent'] = $parent_id;
        }

        if (count($update) > 1) {
            $result = wp_update_post($update, true);
            if (is_wp_error($result)) {
                return $result;
            }
        }

        flacso_pos_rest_update_meta_fields($post_id, $params);

        return new WP_REST_Response(flacso_pos_rest_build_payload($post_id), 200);
    }
}

if (!function_exists('flacso_pos_rest_delete_posgrado')) {
    function flacso_pos_rest_delete_posgrado(WP_REST_Request $request) {
        $post_id = (int) $request['id'];
        $post = get_post($post_id);
        if (!$post || $post->post_type !== FLACSO_Posgrados_Fields::POST_TYPE) {
            return new WP_Error('posgrado_not_found', __('El posgrado solicitado no existe.', 'flacso-posgrados-docentes'), ['status' => 404]);
        }

        if (!flacso_pos_rest_is_allowed_posgrado($post_id)) {
            return new WP_Error('posgrado_not_allowed', __('El posgrado solicitado no pertenece al mapa configurado.', 'flacso-posgrados-docentes'), ['status' => 404]);
        }

        $force = (bool) $request->get_param('force');
        $result = wp_delete_post($post_id, $force);
        if (!$result) {
            return new WP_Error('posgrado_delete_failed', __('No se pudo eliminar el posgrado.', 'flacso-posgrados-docentes'), ['status' => 400]);
        }

        return new WP_REST_Response(['deleted' => true, 'id' => $post_id], 200);
    }
}

add_action('rest_api_init', function() {
    register_rest_route('flacso-posgrados/v1', '/posgrados', [
        [
            'methods' => WP_REST_Server::READABLE,
            'callback' => 'flacso_pos_rest_get_posgrados',
            'permission_callback' => 'flacso_pos_rest_can_read',
        ],
        [
            'methods' => WP_REST_Server::CREATABLE,
            'callback' => 'flacso_pos_rest_create_posgrado',
            'permission_callback' => 'flacso_pos_rest_can_edit',
        ],
    ]);

    register_rest_route('flacso-posgrados/v1', '/posgrados/(?P<id>\\d+)', [
        [
            'methods' => WP_REST_Server::READABLE,
            'callback' => 'flacso_pos_rest_get_posgrado',
            'permission_callback' => 'flacso_pos_rest_can_read',
        ],
        [
            'methods' => WP_REST_Server::EDITABLE,
            'callback' => 'flacso_pos_rest_update_posgrado',
            'permission_callback' => 'flacso_pos_rest_can_edit',
        ],
        [
            'methods' => WP_REST_Server::DELETABLE,
            'callback' => 'flacso_pos_rest_delete_posgrado',
            'permission_callback' => 'flacso_pos_rest_can_edit',
        ],
    ]);
});
