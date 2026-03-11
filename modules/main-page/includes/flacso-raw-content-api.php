<?php

if (!defined('ABSPATH')) {
    exit;
}

add_action('rest_api_init', 'flacso_register_post_rest_fields');

function flacso_register_post_rest_fields(): void {
    $post_types = ['post'];

    foreach ($post_types as $type) {
        register_rest_field($type, 'raw_content', [
            'get_callback' => static function ($post_arr) {
                $post = get_post($post_arr['id']);
                return $post ? $post->post_content : '';
            },
            'schema' => [
                'description' => __('Contenido original sin procesar', 'flacso-main-page'),
                'type'        => 'string',
                'context'     => ['view'],
            ],
        ]);

        register_rest_field($type, 'plain_text', [
            'get_callback' => static function ($post_arr) {
                $post = get_post($post_arr['id']);
                $content = $post ? wp_strip_all_tags($post->post_content) : '';
                return trim(preg_replace('/\s+/', ' ', $content));
            },
            'schema' => [
                'description' => __('Contenido en texto plano sin etiquetas HTML', 'flacso-main-page'),
                'type'        => 'string',
                'context'     => ['view'],
            ],
        ]);

        register_rest_field($type, 'category_names', [
            'get_callback' => static function ($post_arr) {
                $cats = get_the_category($post_arr['id']);
                if (empty($cats)) {
                    return [];
                }
                return wp_list_pluck($cats, 'name');
            },
            'schema' => [
                'description' => __('Lista de nombres de categorías del post', 'flacso-main-page'),
                'type'        => 'array',
                'items'       => ['type' => 'string'],
                'context'     => ['view'],
            ],
        ]);
    }
}

add_action('rest_api_init', function () {
    register_rest_route('flacso/v1', '/raw-content', [
        'methods'             => 'GET',
        'callback'            => 'flacso_get_raw_content',
        'permission_callback' => 'flacso_check_permissions',
        'args'                => [
            'id' => [
                'required'          => true,
                'validate_callback' => static function ($param, $request, $key) {
                    return is_numeric($param);
                },
            ],
            'type' => [
                'required' => false,
                'default'  => 'post',
                'validate_callback' => static function ($param) {
                    return is_string($param) && post_type_exists($param);
                },
            ],
            'type' => [
                'required' => false,
                'default'  => 'post',
                'validate_callback' => static function ($param) {
                    return is_string($param) && post_type_exists($param);
                },
            ],
        ],
    ]);

    register_rest_route('flacso/v1', '/update-content', [
        'methods'             => 'POST',
        'callback'            => 'flacso_update_raw_content',
        'permission_callback' => 'flacso_check_permissions',
        'args'                => [
            'id' => [
                'required'          => true,
                'validate_callback' => static function ($param, $request, $key) {
                    return is_numeric($param);
                },
            ],
            'type' => [
                'required' => false,
                'default'  => 'post',
                'validate_callback' => static function ($param) {
                    return is_string($param) && post_type_exists($param);
                },
            ],
            'content' => [
                'required' => true,
            ],
        ],
    ]);
});

function flacso_get_raw_content(WP_REST_Request $request) {
    $id = (int) $request['id'];
    $type = sanitize_key($request['type']);

    if (!post_type_exists($type)) {
        return new WP_Error('invalid_type', __('El tipo solicitado no existe.', 'flacso-main-page'), ['status' => 400]);
    }

    $post = get_post($id);
    if (!$post || $post->post_type !== $type) {
        return new WP_Error('not_found', __('No se encontró el contenido solicitado.', 'flacso-main-page'), ['status' => 404]);
    }

    return [
        'id'          => $post->ID,
        'type'        => $post->post_type,
        'title'       => $post->post_title,
        'slug'        => $post->post_name,
        'status'      => $post->post_status,
        'content_raw' => $post->post_content,
        'link'        => get_permalink($post),
    ];
}

function flacso_update_raw_content(WP_REST_Request $request) {
    $id = (int) $request['id'];
    $type = sanitize_key($request['type']);
    $new_content = $request['content'];

    if (!post_type_exists($type)) {
        return new WP_Error('invalid_type', __('El tipo solicitado no existe.', 'flacso-main-page'), ['status' => 400]);
    }

    $post = get_post($id);
    if (!$post || $post->post_type !== $type) {
        return new WP_Error('not_found', __('No se encontró el contenido a actualizar.', 'flacso-main-page'), ['status' => 404]);
    }

    $new_content = preg_replace_callback('/\\\\u([0-9a-fA-F]{4})/', static function ($matches) {
        return mb_convert_encoding(pack('H*', $matches[1]), 'UTF-8', 'UCS-2BE');
    }, $new_content);

    $new_content = wp_unslash($new_content);

    remove_filter('content_save_pre', 'wp_filter_post_kses');
    remove_filter('content_filtered_save_pre', 'wp_filter_post_kses');

    $updated_id = wp_update_post([
        'ID'           => $id,
        'post_content' => $new_content,
    ], true);

    add_filter('content_save_pre', 'wp_filter_post_kses');
    add_filter('content_filtered_save_pre', 'wp_filter_post_kses');

    if (is_wp_error($updated_id)) {
        return new WP_Error('update_failed', __('Error al actualizar el contenido.', 'flacso-main-page'), ['status' => 500]);
    }

    return [
        'success' => true,
        'id'      => $updated_id,
        'message' => __('Contenido actualizado correctamente.', 'flacso-main-page'),
    ];
}

function flacso_check_permissions() {
    return current_user_can('edit_posts');
}
