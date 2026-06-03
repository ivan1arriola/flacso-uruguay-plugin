<?php

if (!defined('ABSPATH')) {
    exit;
}

class Tabla_Precio_Schema {
    private const HTML_FIELDS = [
        'precios_nota',
    ];

    private const TEXT_FIELDS = [
        'tabla_precios_tipo',
    ];

    private const JSON_STRING_FIELDS = [
        'precios_filas',
    ];

    public static function init(): void {
        add_action('init', [self::class, 'register_meta'], 12);
        add_action('rest_api_init', [self::class, 'register_rest_fields']);
    }

    public static function register_meta(): void {
        if (!function_exists('register_post_meta')) {
            return;
        }

        foreach (self::HTML_FIELDS as $field) {
            register_post_meta('tabla-precio', $field, [
                'type' => 'string',
                'single' => true,
                'sanitize_callback' => [self::class, 'sanitize_html'],
                'auth_callback' => [self::class, 'user_can_edit_meta'],
                'show_in_rest' => [
                    'schema' => [
                        'description' => sprintf(__('HTML para %s', 'flacso-oferta-academica'), $field),
                        'type' => 'string',
                    ],
                ],
            ]);
        }

        foreach (self::TEXT_FIELDS as $field) {
            register_post_meta('tabla-precio', $field, [
                'type' => 'string',
                'single' => true,
                'sanitize_callback' => 'sanitize_text_field',
                'auth_callback' => [self::class, 'user_can_edit_meta'],
                'show_in_rest' => [
                    'schema' => [
                        'description' => ucfirst(str_replace('_', ' ', $field)),
                        'type' => 'string',
                    ],
                ],
            ]);
        }

        foreach (self::JSON_STRING_FIELDS as $field) {
            register_post_meta('tabla-precio', $field, [
                'type' => 'string',
                'single' => true,
                'sanitize_callback' => [self::class, 'sanitize_prices_rows'],
                'auth_callback' => [self::class, 'user_can_edit_meta'],
                'show_in_rest' => [
                    'schema' => [
                        'description' => ucfirst(str_replace('_', ' ', $field)),
                        'type' => 'string',
                    ],
                ],
            ]);
        }
    }

    public static function register_rest_fields(): void {
        foreach (array_merge(self::HTML_FIELDS, self::TEXT_FIELDS, self::JSON_STRING_FIELDS) as $field) {
            register_rest_field('tabla-precio', $field, [
                'get_callback' => function ($post_array) use ($field) {
                    return self::get_meta_value((int) $post_array['id'], $field);
                },
                'update_callback' => function ($value, $post_obj) use ($field) {
                    $sanitized_value = self::sanitize_rest_field_value($field, $value);

                    if ($sanitized_value === '') {
                        delete_post_meta($post_obj->ID, $field);
                        return true;
                    }

                    return update_post_meta($post_obj->ID, $field, $sanitized_value);
                },
                'schema' => null,
            ]);
        }

        register_rest_field('tabla-precio', 'linked_offers', [
            'get_callback' => function ($post_array) {
                return self::get_linked_offers_summary((int) $post_array['id']);
            },
            'schema' => null,
        ]);
    }

    public static function sanitize_html($value): string {
        $allowed = wp_kses_allowed_html('post');

        if (!isset($allowed['p'])) {
            $allowed['p'] = [];
        }

        if (!isset($allowed['br'])) {
            $allowed['br'] = [];
        }

        if (!isset($allowed['small'])) {
            $allowed['small'] = [];
        }

        return wp_kses((string) $value, $allowed);
    }

    public static function sanitize_prices_rows($value): string {
        if (is_string($value)) {
            $value = trim($value);

            if ($value === '') {
                return '';
            }

            $decoded = json_decode(wp_unslash($value), true);
            if (!is_array($decoded)) {
                return '';
            }

            $value = $decoded;
        }

        if (!is_array($value)) {
            return '';
        }

        $rows = [];

        foreach ($value as $row) {
            if (!is_array($row)) {
                continue;
            }

            $sanitized_row = [
                'concept' => self::sanitize_html($row['concept'] ?? ''),
                'uy' => self::sanitize_html($row['uy'] ?? ''),
                'us' => self::sanitize_html($row['us'] ?? ''),
                'highlight' => filter_var($row['highlight'] ?? false, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE) ?? false,
            ];

            if (
                $sanitized_row['concept'] === ''
                && $sanitized_row['uy'] === ''
                && $sanitized_row['us'] === ''
                && $sanitized_row['highlight'] === false
            ) {
                continue;
            }

            $rows[] = $sanitized_row;
        }

        if (empty($rows)) {
            return '';
        }

        return wp_json_encode($rows, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }

    public static function user_can_edit_meta($allowed, $meta_key, $post_id, $user_id = null): bool {
        return current_user_can('edit_post', $post_id);
    }

    private static function sanitize_rest_field_value(string $field, $value): string {
        if (in_array($field, self::HTML_FIELDS, true)) {
            return self::sanitize_html($value);
        }

        if (in_array($field, self::TEXT_FIELDS, true)) {
            return sanitize_text_field((string) $value);
        }

        if (in_array($field, self::JSON_STRING_FIELDS, true)) {
            return self::sanitize_prices_rows($value);
        }

        return is_scalar($value) ? (string) $value : '';
    }

    public static function get_table_data(int $post_id): array {
        $post = get_post($post_id);

        if (!$post || $post->post_type !== 'tabla-precio') {
            return [];
        }

        return [
            'id' => $post->ID,
            'title' => get_the_title($post),
            'slug' => $post->post_name,
            'status' => $post->post_status,
            'tabla_precios_tipo' => self::get_meta_value($post_id, 'tabla_precios_tipo'),
            'precios_filas' => self::get_meta_value($post_id, 'precios_filas'),
            'precios_nota' => self::get_meta_value($post_id, 'precios_nota'),
            'linked_offers' => self::get_linked_offers_summary($post_id),
        ];
    }

    public static function get_linked_offers_summary(int $post_id): array {
        if ($post_id <= 0) {
            return [];
        }

        $query = new WP_Query([
            'post_type' => 'oferta-academica',
            'post_status' => ['publish', 'draft', 'future', 'pending', 'private'],
            'posts_per_page' => -1,
            'orderby' => 'title',
            'order' => 'ASC',
            'meta_query' => [
                [
                    'key' => 'tabla_precio_id',
                    'value' => $post_id,
                    'compare' => '=',
                ],
            ],
        ]);

        $items = [];

        foreach ($query->posts as $post) {
            $items[] = [
                'id' => (int) $post->ID,
                'title' => get_the_title($post),
                'slug' => $post->post_name,
                'url' => get_permalink($post),
            ];
        }

        wp_reset_postdata();

        return $items;
    }

    private static function get_meta_value(int $post_id, string $key): string {
        $value = get_post_meta($post_id, $key, true);

        if ($value === null || is_array($value) || is_object($value)) {
            return '';
        }

        return (string) $value;
    }
}
