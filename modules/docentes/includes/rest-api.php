<?php
if (!defined('ABSPATH')) {
    exit;
}

if (!function_exists('dp_rest_check_cap')) {
    function dp_rest_check_cap(string $cap) {
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

if (!function_exists('dp_rest_get_payload')) {
    function dp_rest_get_payload(WP_REST_Request $request): array {
        $payload = $request->get_json_params();
        if (!is_array($payload)) {
            $payload = $request->get_params();
        }
        return is_array($payload) ? $payload : [];
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
        $featured_image = dp_rest_featured_image_payload($post->ID);

        return [
            'id' => (int) $post->ID,
            'title' => $post->post_title,
            'slug' => $post->post_name,
            'content' => $post->post_content,
            'status' => $post->post_status,
            'link' => get_permalink($post),
            'date' => $post->post_date_gmt,
            'modified' => $post->post_modified_gmt,
            'meta' => [
                'prefijo_abrev' => get_post_meta($post->ID, 'prefijo_abrev', true),
                'titulo_academico' => get_post_meta($post->ID, 'titulo_academico', true),
                'nombre' => get_post_meta($post->ID, 'nombre', true),
                'apellido' => get_post_meta($post->ID, 'apellido', true),
                'cv' => get_post_meta($post->ID, 'cv', true),
                'docente_correos' => is_array($correos) ? $correos : [],
                'docente_redes' => is_array($redes) ? $redes : [],
            ],
            // Compatibilidad
            'prefijo_abrev' => get_post_meta($post->ID, 'prefijo_abrev', true),
            'prefijo_full' => get_post_meta($post->ID, 'prefijo_full', true),
            'nombre' => get_post_meta($post->ID, 'nombre', true),
            'apellido' => get_post_meta($post->ID, 'apellido', true),
            'cv' => get_post_meta($post->ID, 'cv', true),
            'correos' => is_array($correos) ? $correos : [],
            'redes' => is_array($redes) ? $redes : [],
            'featured_image' => $featured_image['url'] ?? '',
            'featured_media' => (int) ($featured_image['id'] ?? 0),
            'featured_image_url' => $featured_image['url'] ?? '', // Mantener por retrocompatibilidad momentanea
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

        $args = [
            'post_type' => 'docente',
            'posts_per_page' => $per_page,
            'paged' => $page,
            'orderby' => [
                'meta_value' => 'ASC',
                'title' => 'ASC',
            ],
            'meta_key' => 'apellido',
            'post_status' => $status ?: 'any',
        ];

        if ($search !== '') {
            $args['s'] = $search;
        }

        $include = $request->get_param('include');
        if (!empty($include)) {
            $ids = is_array($include) ? $include : explode(',', $include);
            $args['post__in'] = array_map('intval', $ids);
            $args['orderby'] = 'post__in';
            $args['posts_per_page'] = -1;
            $args['paged'] = 1;
            $args['post_status'] = 'any';
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
        
        // Handle meta key if present
        $meta = $params['meta'] ?? [];
        if (!empty($meta) && is_array($meta)) {
            $params = array_merge($params, $meta);
        }

        $prefijo_abrev = isset($params['prefijo_abrev']) ? sanitize_text_field($params['prefijo_abrev']) : '';
        $titulo_academico = isset($params['titulo_academico']) ? sanitize_text_field($params['titulo_academico']) : '';
        $nombre = isset($params['nombre']) ? sanitize_text_field($params['nombre']) : '';
        $apellido = isset($params['apellido']) ? sanitize_text_field($params['apellido']) : '';
        $title = isset($params['title']) ? sanitize_text_field($params['title']) : '';
        $slug = isset($params['slug']) ? sanitize_title($params['slug']) : '';
        $status = isset($params['status']) ? sanitize_key($params['status']) : 'publish';
        $content = isset($params['content']) ? wp_kses_post($params['content']) : '';
        $cv = isset($params['cv']) ? wp_kses_post($params['cv']) : '';

        // Generar el post_title limpio (solo Nombre + Apellido)
        $title = trim($nombre . ' ' . $apellido);
        if ($title === '') {
            return new WP_Error('dp_docente_missing_title', __('Debes enviar al menos nombre y apellido.', 'flacso-posgrados-docentes'), ['status' => 400]);
        }
        
        $slug = ($slug !== '') ? $slug : sanitize_title($title);

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

        if (array_key_exists('featured_media', $params)) {
            $featured_id = (int) $params['featured_media'];
            if ($featured_id > 0) {
                set_post_thumbnail($doc_id, $featured_id);
            }
        }

        update_post_meta($doc_id, 'prefijo_abrev', $prefijo_abrev);
        update_post_meta($doc_id, 'titulo_academico', $titulo_academico);
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

        // Forzar titulo y slug si se actualizan nombre, apellido o prefijo
        $prefijo = isset($params['prefijo_abrev']) ? sanitize_text_field($params['prefijo_abrev']) : get_post_meta($doc_id, 'prefijo_abrev', true);
        $nombre = isset($params['nombre']) ? sanitize_text_field($params['nombre']) : get_post_meta($doc_id, 'nombre', true);
        $apellido = isset($params['apellido']) ? sanitize_text_field($params['apellido']) : get_post_meta($doc_id, 'apellido', true);
        
        if (array_key_exists('nombre', $params) || array_key_exists('apellido', $params)) {
            $update['post_title'] = trim($nombre . ' ' . $apellido);
            $update['post_name'] = sanitize_title($nombre . '-' . $apellido);
        } else {
            if (array_key_exists('title', $params)) {
                $update['post_title'] = sanitize_text_field($params['title']);
            }
            if (array_key_exists('slug', $params)) {
                $update['post_name'] = sanitize_title($params['slug']);
            }
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

        if (array_key_exists('featured_media', $params)) {
            $featured_id = (int) $params['featured_media'];
            if ($featured_id > 0) {
                set_post_thumbnail($doc_id, $featured_id);
            } else {
                delete_post_thumbnail($doc_id);
            }
        }

        // Handle meta key if present
        $meta = $params['meta'] ?? [];
        if (!empty($meta) && is_array($meta)) {
            $params = array_merge($params, $meta);
        }

        if (array_key_exists('prefijo_abrev', $params)) {
            update_post_meta($doc_id, 'prefijo_abrev', sanitize_text_field($params['prefijo_abrev']));
        }
        if (array_key_exists('titulo_academico', $params)) {
            update_post_meta($doc_id, 'titulo_academico', sanitize_text_field($params['titulo_academico']));
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

add_action('rest_api_init', function () {
    // Ruta para la colección (Listado y Creación)
    register_rest_route('flacso-docentes/v1', '/docentes', [
        'methods' => 'GET, POST',
        'callback' => function ($request) {
            if ($request->get_method() === 'POST') {
                return dp_rest_create_docente($request);
            }
            return dp_rest_get_docentes($request);
        },
        'permission_callback' => function ($request) {
            if ($request->get_method() === 'POST') {
                return dp_rest_can_edit_docentes();
            }
            return dp_rest_can_read_docentes();
        },
    ]);

    // Ruta para ítems individuales (Ver, Actualizar, Eliminar)
    register_rest_route('flacso-docentes/v1', '/docentes/(?P<id>[0-9]+)', [
        [
            'methods' => 'GET',
            'callback' => 'dp_rest_get_docente',
            'permission_callback' => 'dp_rest_can_read_docentes',
        ],
        [
            'methods' => 'POST, PUT, PATCH',
            'callback' => 'dp_rest_update_docente',
            'permission_callback' => 'dp_rest_can_edit_docentes',
        ],
        [
            'methods' => 'DELETE',
            'callback' => 'dp_rest_delete_docente',
            'permission_callback' => 'dp_rest_can_edit_docentes',
        ],
    ]);
});
