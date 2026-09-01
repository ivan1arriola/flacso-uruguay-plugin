<?php

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Contrato publico de Docentes.
 *
 * Conserva los campos de presentacion usados por el sitio. De los correos
 * estructurados solo publica el marcado como principal; la lista completa
 * queda reservada al contexto administrativo.
 */
final class Docente_Public_DTO {
    private const PUBLIC_META_FIELDS = [
        'prefijo_abrev',
        'titulo_academico',
        'cargo',
        'roles',
        'nombre',
        'apellido',
        'cv',
        'docente_redes',
    ];

    public static function from_legacy(array $payload): array {
        if (isset($payload['meta']) && is_array($payload['meta'])) {
            $payload['meta'] = array_intersect_key(
                $payload['meta'],
                array_flip(self::PUBLIC_META_FIELDS)
            );
        }

        return $payload;
    }

    public static function from_wp_rest(array $payload): array {
        if (isset($payload['meta']) && is_array($payload['meta'])) {
            $payload['meta'] = array_intersect_key(
                $payload['meta'],
                array_flip(self::PUBLIC_META_FIELDS)
            );
        }

        return $payload;
    }
}

/**
 * Contrato administrativo de Docentes.
 *
 * En v1 mantiene exactamente el payload historico para no romper el Editor.
 */
final class Docente_Admin_DTO {
    public static function from_legacy(array $payload): array {
        return $payload;
    }

    public static function from_wp_rest(array $payload): array {
        return $payload;
    }
}

final class Docente_REST_DTO {
    public static function init(): void {
        add_filter('rest_request_after_callbacks', [self::class, 'transform_response'], 20, 3);
        add_filter('rest_prepare_docente', [self::class, 'transform_wp_response'], 20, 3);
    }

    public static function transform_response($response, $handler, $request) {
        if (!self::is_read_request($request) || is_wp_error($response) || !is_object($response) || !method_exists($response, 'get_data') || !method_exists($response, 'set_data')) {
            return $response;
        }

        $route = (string) $request->get_route();
        $is_collection = preg_match('#^/flacso-docentes/v1/docentes/?$#', $route) === 1;
        $is_item = preg_match('#^/flacso-docentes/v1/docentes/(?P<id>\d+)/?$#', $route, $matches) === 1;

        if (!$is_collection && !$is_item) {
            return $response;
        }

        $data = $response->get_data();
        $admin = $is_item
            ? current_user_can('edit_post', (int) $matches['id'])
            : current_user_can('edit_posts');

        if ($is_collection) {
            if (!is_array($data) || !isset($data['items']) || !is_array($data['items'])) {
                return $response;
            }

            $data['items'] = array_map(
                static function ($item) use ($admin) {
                    return is_array($item) ? self::map($item, $admin) : $item;
                },
                $data['items']
            );
        } elseif (is_array($data)) {
            $data = self::map($data, $admin);
        }

        $response->set_data($data);
        return $response;
    }

    public static function transform_wp_response($response, $post, $request) {
        if (!self::is_read_request($request) || is_wp_error($response) || !is_object($response) || !method_exists($response, 'get_data') || !method_exists($response, 'set_data')) {
            return $response;
        }

        $data = $response->get_data();
        if (!is_array($data)) {
            return $response;
        }

        $post_id = is_object($post) && isset($post->ID) ? (int) $post->ID : 0;
        $admin = $post_id > 0
            ? current_user_can('edit_post', $post_id)
            : current_user_can('edit_posts');

        $response->set_data(
            $admin
                ? Docente_Admin_DTO::from_wp_rest($data)
                : Docente_Public_DTO::from_wp_rest($data)
        );

        return $response;
    }

    public static function map(array $payload, bool $admin): array {
        return $admin
            ? Docente_Admin_DTO::from_legacy($payload)
            : Docente_Public_DTO::from_legacy($payload);
    }

    private static function is_read_request($request): bool {
        if (!is_object($request) || !method_exists($request, 'get_method') || !method_exists($request, 'get_route')) {
            return false;
        }

        return in_array(strtoupper((string) $request->get_method()), ['GET', 'HEAD'], true);
    }
}

Docente_REST_DTO::init();
