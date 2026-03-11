<?php
if (!defined('ABSPATH')) exit;

if (!function_exists('dp_rest_check_cap')) {
    function dp_rest_check_cap(string $cap) {
        if (!is_user_logged_in()) {
            return new WP_Error(
                'rest_forbidden',
                __('Autenticación requerida.', 'flacso-posgrados-docentes'),
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

if (!function_exists('dp_rest_can_read_docentes')) {
    function dp_rest_can_read_docentes() {
        return dp_rest_check_cap('edit_posts');
    }
}

if (!function_exists('dp_rest_can_edit_docentes')) {
    function dp_rest_can_edit_docentes() {
        return dp_rest_check_cap('edit_others_posts');
    }
}

if (!function_exists('dp_rest_can_manage_equipos')) {
    function dp_rest_can_manage_equipos() {
        return dp_rest_check_cap('manage_categories');
    }
}

if (!function_exists('dp_rest_get_payload')) {
    function dp_rest_get_payload(WP_REST_Request $request): array {
        $payload = $request->get_json_params();
        if (!is_array($payload)) {
            $payload = $request->get_params();
        }
        return is_array($payload) ? $payload : [];
    }
}

if (!function_exists('dp_rest_normalize_term_ids')) {
    function dp_rest_normalize_term_ids($raw_terms): array {
        if ($raw_terms === null) {
            return [];
        }

        if (is_string($raw_terms)) {
            $raw_terms = array_filter(array_map('trim', explode(',', $raw_terms)));
        }

        if (!is_array($raw_terms)) {
            $raw_terms = [$raw_terms];
        }

        $term_ids = [];
        foreach ($raw_terms as $term_value) {
            if ($term_value === '' || $term_value === null) {
                continue;
            }
            if (is_numeric($term_value)) {
                $term_ids[] = (int) $term_value;
                continue;
            }
            $slug = sanitize_title((string) $term_value);
            if ($slug === '') {
                continue;
            }
            $term = get_term_by('slug', $slug, 'equipo-docente');
            if ($term && !is_wp_error($term)) {
                $term_ids[] = (int) $term->term_id;
            }
        }

        $term_ids = array_values(array_unique(array_filter($term_ids)));
        return $term_ids;
    }
}

if (!function_exists('dp_rest_sanitize_docente_correos')) {
    function dp_rest_sanitize_docente_correos($raw): array {
        if (!is_array($raw)) {
            return [];
        }

        $clean = [];
        foreach ($raw as $correo) {
            if (!is_array($correo)) {
                continue;
            }
            $email = isset($correo['email']) ? sanitize_email($correo['email']) : '';
            if (!$email) {
                continue;
            }
            $label = isset($correo['label']) ? sanitize_text_field($correo['label']) : '';
            $principal = !empty($correo['principal']);
            $clean[] = [
                'email' => $email,
                'label' => $label,
                'principal' => $principal,
            ];
        }

        return $clean;
    }
}

if (!function_exists('dp_rest_sanitize_docente_redes')) {
    function dp_rest_sanitize_docente_redes($raw): array {
        if (!is_array($raw)) {
            return [];
        }

        $clean = [];
        foreach ($raw as $red) {
            if (!is_array($red)) {
                continue;
            }
            $url = isset($red['url']) ? esc_url_raw($red['url']) : '';
            if (!$url) {
                continue;
            }
            $label = isset($red['label']) ? sanitize_text_field($red['label']) : '';
            $clean[] = [
                'url' => $url,
                'label' => $label,
            ];
        }

        return $clean;
    }
}

if (!function_exists('dp_rest_build_equipo_payload')) {
    function dp_rest_build_equipo_payload($term): array {
        $term = is_numeric($term) ? get_term((int) $term, 'equipo-docente') : $term;
        if (!$term || is_wp_error($term)) {
            return [];
        }

        $color = get_term_meta($term->term_id, 'equipo_docente_color', true);
        $page_id = (int) get_term_meta($term->term_id, 'equipo_docente_page_id', true);
        $relacion_nombre = (string) get_term_meta($term->term_id, 'equipo_docente_relacion_nombre', true);
        $autosync = get_term_meta($term->term_id, 'equipo_docente_autosync', true);

        return [
            'id' => (int) $term->term_id,
            'name' => $term->name,
            'slug' => $term->slug,
            'description' => $term->description,
            'count' => (int) $term->count,
            'link' => get_term_link($term),
            'color' => $color,
            'page_id' => $page_id,
            'relation_name' => $relacion_nombre,
            'autosync' => !empty($autosync),
        ];
    }
}

if (!function_exists('dp_rest_featured_image_payload')) {
    function dp_rest_featured_image_payload(int $post_id): array {
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

if (!function_exists('dp_rest_build_docente_payload')) {
    function dp_rest_build_docente_payload($post): array {
        $post = is_numeric($post) ? get_post((int) $post) : $post;
        if (!$post || $post->post_type !== 'docente') {
            return [];
        }

        $correos = get_post_meta($post->ID, 'docente_correos', true);
        $redes = get_post_meta($post->ID, 'docente_redes', true);
        $equipos = wp_get_post_terms($post->ID, 'equipo-docente');
        $equipos_detalle = [];
        if ($equipos && !is_wp_error($equipos)) {
            foreach ($equipos as $equipo) {
                $equipos_detalle[] = dp_rest_build_equipo_payload($equipo);
            }
        }

        $featured_image = dp_rest_featured_image_payload($post->ID);

        return [
            'id' => (int) $post->ID,
            'title' => $post->post_title,
            'slug' => $post->post_name,
            'status' => $post->post_status,
            'link' => get_permalink($post),
            'date' => $post->post_date_gmt,
            'modified' => $post->post_modified_gmt,
            'prefijo_abrev' => get_post_meta($post->ID, 'prefijo_abrev', true),
            'prefijo_full' => get_post_meta($post->ID, 'prefijo_full', true),
            'nombre' => get_post_meta($post->ID, 'nombre', true),
            'apellido' => get_post_meta($post->ID, 'apellido', true),
            'cv' => get_post_meta($post->ID, 'cv', true),
            'correos' => is_array($correos) ? $correos : [],
            'redes' => is_array($redes) ? $redes : [],
            'equipos' => $equipos && !is_wp_error($equipos) ? wp_list_pluck($equipos, 'term_id') : [],
            'equipos_detalle' => $equipos_detalle,
            'featured_image' => $featured_image,
            'featured_image_url' => $featured_image['url'] ?? '',
        ];
    }
}

if (!function_exists('dp_rest_get_docentes')) {
    function dp_rest_get_docentes(WP_REST_Request $request) {
        $per_page = (int) $request->get_param('per_page');
        if ($per_page <= 0) {
            $per_page = 50;
        }
        $per_page = min(100, $per_page);

        $page = max(1, (int) $request->get_param('page'));
        $search = sanitize_text_field((string) $request->get_param('search'));
        $status = sanitize_key((string) $request->get_param('status'));
        $equipo = $request->get_param('equipo');
        $page_id = (int) $request->get_param('page_id');

        $args = [
            'post_type' => 'docente',
            'posts_per_page' => $per_page,
            'paged' => $page,
            'orderby' => 'meta_value',
            'meta_key' => 'apellido',
            'order' => 'ASC',
            'post_status' => $status ?: 'any',
        ];

        if ($search !== '') {
            $args['s'] = $search;
        }

        $tax_query = [];
        if ($equipo !== null && $equipo !== '') {
            if (is_numeric($equipo)) {
                $tax_query[] = [
                    'taxonomy' => 'equipo-docente',
                    'field' => 'term_id',
                    'terms' => [(int) $equipo],
                ];
            } else {
                $tax_query[] = [
                    'taxonomy' => 'equipo-docente',
                    'field' => 'slug',
                    'terms' => [sanitize_title((string) $equipo)],
                ];
            }
        }

        if ($page_id) {
            $term_ids = get_terms([
                'taxonomy' => 'equipo-docente',
                'hide_empty' => false,
                'fields' => 'ids',
                'meta_query' => [
                    [
                        'key' => 'equipo_docente_page_id',
                        'value' => $page_id,
                    ],
                ],
            ]);
            if (!is_wp_error($term_ids) && !empty($term_ids)) {
                $tax_query[] = [
                    'taxonomy' => 'equipo-docente',
                    'field' => 'term_id',
                    'terms' => $term_ids,
                ];
            }
        }

        if ($tax_query) {
            $args['tax_query'] = $tax_query;
        }

        $query = new WP_Query($args);
        $items = [];
        foreach ($query->posts as $post) {
            $items[] = dp_rest_build_docente_payload($post);
        }

        return new WP_REST_Response([
            'total' => (int) $query->found_posts,
            'pages' => (int) $query->max_num_pages,
            'items' => $items,
        ], 200);
    }
}

if (!function_exists('dp_rest_get_docente')) {
    function dp_rest_get_docente(WP_REST_Request $request) {
        $doc_id = (int) $request['id'];
        $post = get_post($doc_id);
        if (!$post || $post->post_type !== 'docente') {
            return new WP_Error('dp_docente_not_found', __('El perfil solicitado no existe.', 'flacso-posgrados-docentes'), ['status' => 404]);
        }

        return new WP_REST_Response(dp_rest_build_docente_payload($post), 200);
    }
}

if (!function_exists('dp_rest_create_docente')) {
    function dp_rest_create_docente(WP_REST_Request $request) {
        $params = dp_rest_get_payload($request);

        $prefijo_abrev = isset($params['prefijo_abrev']) ? sanitize_text_field($params['prefijo_abrev']) : '';
        $prefijo_full  = isset($params['prefijo_full']) ? sanitize_text_field($params['prefijo_full']) : '';
        $nombre = isset($params['nombre']) ? sanitize_text_field($params['nombre']) : '';
        $apellido = isset($params['apellido']) ? sanitize_text_field($params['apellido']) : '';
        $title = isset($params['title']) ? sanitize_text_field($params['title']) : '';
        $slug = isset($params['slug']) ? sanitize_title($params['slug']) : '';
        $status = isset($params['status']) ? sanitize_key($params['status']) : 'publish';
        $content = isset($params['content']) ? wp_kses_post($params['content']) : '';
        $cv = isset($params['cv']) ? wp_kses_post($params['cv']) : '';

        if ($title === '') {
            $title = trim($prefijo_abrev . ' ' . $nombre . ' ' . $apellido);
        }

        if ($title === '') {
            return new WP_Error('dp_docente_missing_title', __('Debes enviar un titulo o nombre/apellido.', 'flacso-posgrados-docentes'), ['status' => 400]);
        }

        $post_data = [
            'post_type' => 'docente',
            'post_title' => $title,
            'post_status' => $status ?: 'publish',
            'post_content' => $content,
        ];

        if ($slug !== '') {
            $post_data['post_name'] = $slug;
        }

        $doc_id = wp_insert_post($post_data, true);
        if (is_wp_error($doc_id)) {
            return $doc_id;
        }

        update_post_meta($doc_id, 'prefijo_abrev', $prefijo_abrev);
        update_post_meta($doc_id, 'prefijo_full', $prefijo_full);
        update_post_meta($doc_id, 'nombre', $nombre);
        update_post_meta($doc_id, 'apellido', $apellido);
        update_post_meta($doc_id, 'cv', $cv);

        $correos = $params['correos'] ?? ($params['docente_correos'] ?? null);
        if ($correos !== null) {
            update_post_meta($doc_id, 'docente_correos', dp_rest_sanitize_docente_correos($correos));
        }

        $redes = $params['redes'] ?? ($params['docente_redes'] ?? null);
        if ($redes !== null) {
            update_post_meta($doc_id, 'docente_redes', dp_rest_sanitize_docente_redes($redes));
        }

        if (array_key_exists('equipos', $params) || array_key_exists('equipo_ids', $params)) {
            $equipos_raw = $params['equipos'] ?? $params['equipo_ids'];
            $term_ids = dp_rest_normalize_term_ids($equipos_raw);
            wp_set_object_terms($doc_id, $term_ids, 'equipo-docente', false);
        }

        return new WP_REST_Response(dp_rest_build_docente_payload($doc_id), 201);
    }
}

if (!function_exists('dp_rest_update_docente')) {
    function dp_rest_update_docente(WP_REST_Request $request) {
        $doc_id = (int) $request['id'];
        $post = get_post($doc_id);
        if (!$post || $post->post_type !== 'docente') {
            return new WP_Error('dp_docente_not_found', __('El perfil solicitado no existe.', 'flacso-posgrados-docentes'), ['status' => 404]);
        }

        $params = dp_rest_get_payload($request);
        $update = ['ID' => $doc_id];

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

        if (count($update) > 1) {
            $result = wp_update_post($update, true);
            if (is_wp_error($result)) {
                return $result;
            }
        }

        if (array_key_exists('prefijo_abrev', $params)) {
            update_post_meta($doc_id, 'prefijo_abrev', sanitize_text_field($params['prefijo_abrev']));
        }
        if (array_key_exists('prefijo_full', $params)) {
            update_post_meta($doc_id, 'prefijo_full', sanitize_text_field($params['prefijo_full']));
        }
        if (array_key_exists('nombre', $params)) {
            update_post_meta($doc_id, 'nombre', sanitize_text_field($params['nombre']));
        }
        if (array_key_exists('apellido', $params)) {
            update_post_meta($doc_id, 'apellido', sanitize_text_field($params['apellido']));
        }
        if (array_key_exists('cv', $params)) {
            update_post_meta($doc_id, 'cv', wp_kses_post($params['cv']));
        }

        $correos = $params['correos'] ?? ($params['docente_correos'] ?? null);
        if ($correos !== null) {
            update_post_meta($doc_id, 'docente_correos', dp_rest_sanitize_docente_correos($correos));
        }

        $redes = $params['redes'] ?? ($params['docente_redes'] ?? null);
        if ($redes !== null) {
            update_post_meta($doc_id, 'docente_redes', dp_rest_sanitize_docente_redes($redes));
        }

        if (array_key_exists('equipos', $params) || array_key_exists('equipo_ids', $params)) {
            $equipos_raw = $params['equipos'] ?? $params['equipo_ids'];
            $term_ids = dp_rest_normalize_term_ids($equipos_raw);
            wp_set_object_terms($doc_id, $term_ids, 'equipo-docente', false);
        }

        return new WP_REST_Response(dp_rest_build_docente_payload($doc_id), 200);
    }
}

if (!function_exists('dp_rest_delete_docente')) {
    function dp_rest_delete_docente(WP_REST_Request $request) {
        $doc_id = (int) $request['id'];
        $force = (bool) $request->get_param('force');
        $result = wp_delete_post($doc_id, $force);
        if (!$result) {
            return new WP_Error('dp_docente_delete_failed', __('No se pudo eliminar el perfil.', 'flacso-posgrados-docentes'), ['status' => 400]);
        }
        return new WP_REST_Response(['deleted' => true, 'id' => $doc_id], 200);
    }
}

if (!function_exists('dp_rest_get_equipos')) {
    function dp_rest_get_equipos(WP_REST_Request $request) {
        $terms = get_terms([
            'taxonomy' => 'equipo-docente',
            'hide_empty' => false,
        ]);

        if (is_wp_error($terms)) {
            return $terms;
        }

        $items = [];
        foreach ($terms as $term) {
            $items[] = dp_rest_build_equipo_payload($term);
        }

        return new WP_REST_Response([
            'total' => count($items),
            'items' => $items,
        ], 200);
    }
}

if (!function_exists('dp_rest_get_equipo')) {
    function dp_rest_get_equipo(WP_REST_Request $request) {
        $term_id = (int) $request['id'];
        $term = get_term($term_id, 'equipo-docente');
        if (!$term || is_wp_error($term)) {
            return new WP_Error('dp_equipo_not_found', __('El equipo solicitado no existe.', 'flacso-posgrados-docentes'), ['status' => 404]);
        }

        return new WP_REST_Response(dp_rest_build_equipo_payload($term), 200);
    }
}

if (!function_exists('dp_rest_create_equipo')) {
    function dp_rest_create_equipo(WP_REST_Request $request) {
        $params = dp_rest_get_payload($request);

        $name = isset($params['name']) ? sanitize_text_field($params['name']) : '';
        if ($name === '') {
            return new WP_Error('dp_equipo_missing_name', __('Debes enviar el nombre del equipo.', 'flacso-posgrados-docentes'), ['status' => 400]);
        }

        $args = [];
        if (!empty($params['slug'])) {
            $args['slug'] = sanitize_title($params['slug']);
        }
        if (isset($params['description'])) {
            $args['description'] = sanitize_textarea_field($params['description']);
        }

        $term = wp_insert_term($name, 'equipo-docente', $args);
        if (is_wp_error($term)) {
            return $term;
        }

        $term_id = (int) $term['term_id'];
        dp_rest_update_equipo_meta($term_id, $params);

        return new WP_REST_Response(dp_rest_build_equipo_payload($term_id), 201);
    }
}

if (!function_exists('dp_rest_update_equipo_meta')) {
    function dp_rest_update_equipo_meta(int $term_id, array $params): void {
        if (array_key_exists('color', $params)) {
            $color = sanitize_hex_color($params['color']);
            if ($color) {
                update_term_meta($term_id, 'equipo_docente_color', $color);
            } else {
                delete_term_meta($term_id, 'equipo_docente_color');
            }
        }

        if (array_key_exists('page_id', $params)) {
            $page_id = absint($params['page_id']);
            if ($page_id) {
                update_term_meta($term_id, 'equipo_docente_page_id', $page_id);
            } else {
                delete_term_meta($term_id, 'equipo_docente_page_id');
            }
        }

        if (array_key_exists('relation_name', $params)) {
            $relation = sanitize_text_field($params['relation_name']);
            if ($relation !== '') {
                update_term_meta($term_id, 'equipo_docente_relacion_nombre', $relation);
            } else {
                delete_term_meta($term_id, 'equipo_docente_relacion_nombre');
            }
        }

        if (array_key_exists('autosync', $params)) {
            $autosync = !empty($params['autosync']) ? 1 : 0;
            if ($autosync) {
                update_term_meta($term_id, 'equipo_docente_autosync', 1);
            } else {
                delete_term_meta($term_id, 'equipo_docente_autosync');
            }
        }
    }
}

if (!function_exists('dp_rest_update_equipo')) {
    function dp_rest_update_equipo(WP_REST_Request $request) {
        $term_id = (int) $request['id'];
        $term = get_term($term_id, 'equipo-docente');
        if (!$term || is_wp_error($term)) {
            return new WP_Error('dp_equipo_not_found', __('El equipo solicitado no existe.', 'flacso-posgrados-docentes'), ['status' => 404]);
        }

        $params = dp_rest_get_payload($request);
        $args = [];
        if (array_key_exists('name', $params)) {
            $args['name'] = sanitize_text_field($params['name']);
        }
        if (array_key_exists('slug', $params)) {
            $args['slug'] = sanitize_title($params['slug']);
        }
        if (array_key_exists('description', $params)) {
            $args['description'] = sanitize_textarea_field($params['description']);
        }

        if (!empty($args)) {
            $result = wp_update_term($term_id, 'equipo-docente', $args);
            if (is_wp_error($result)) {
                return $result;
            }
        }

        dp_rest_update_equipo_meta($term_id, $params);

        return new WP_REST_Response(dp_rest_build_equipo_payload($term_id), 200);
    }
}

if (!function_exists('dp_rest_delete_equipo')) {
    function dp_rest_delete_equipo(WP_REST_Request $request) {
        $term_id = (int) $request['id'];
        $result = wp_delete_term($term_id, 'equipo-docente');
        if (is_wp_error($result)) {
            return $result;
        }
        return new WP_REST_Response(['deleted' => true, 'id' => $term_id], 200);
    }
}

add_action('rest_api_init', function() {
    register_rest_route('flacso-docentes/v1', '/docentes', [
        [
            'methods' => WP_REST_Server::READABLE,
            'callback' => 'dp_rest_get_docentes',
            'permission_callback' => 'dp_rest_can_read_docentes',
        ],
        [
            'methods' => WP_REST_Server::CREATABLE,
            'callback' => 'dp_rest_create_docente',
            'permission_callback' => 'dp_rest_can_edit_docentes',
        ],
    ]);

    register_rest_route('flacso-docentes/v1', '/docentes/(?P<id>\\d+)', [
        [
            'methods' => WP_REST_Server::READABLE,
            'callback' => 'dp_rest_get_docente',
            'permission_callback' => 'dp_rest_can_read_docentes',
        ],
        [
            'methods' => WP_REST_Server::EDITABLE,
            'callback' => 'dp_rest_update_docente',
            'permission_callback' => 'dp_rest_can_edit_docentes',
        ],
        [
            'methods' => WP_REST_Server::DELETABLE,
            'callback' => 'dp_rest_delete_docente',
            'permission_callback' => 'dp_rest_can_edit_docentes',
        ],
    ]);

    register_rest_route('flacso-docentes/v1', '/equipos', [
        [
            'methods' => WP_REST_Server::READABLE,
            'callback' => 'dp_rest_get_equipos',
            'permission_callback' => 'dp_rest_can_read_docentes',
        ],
        [
            'methods' => WP_REST_Server::CREATABLE,
            'callback' => 'dp_rest_create_equipo',
            'permission_callback' => 'dp_rest_can_manage_equipos',
        ],
    ]);

    register_rest_route('flacso-docentes/v1', '/equipos/(?P<id>\\d+)', [
        [
            'methods' => WP_REST_Server::READABLE,
            'callback' => 'dp_rest_get_equipo',
            'permission_callback' => 'dp_rest_can_read_docentes',
        ],
        [
            'methods' => WP_REST_Server::EDITABLE,
            'callback' => 'dp_rest_update_equipo',
            'permission_callback' => 'dp_rest_can_manage_equipos',
        ],
        [
            'methods' => WP_REST_Server::DELETABLE,
            'callback' => 'dp_rest_delete_equipo',
            'permission_callback' => 'dp_rest_can_manage_equipos',
        ],
    ]);
});
