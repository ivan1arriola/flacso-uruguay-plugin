<?php

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Contrato publico de Oferta Academica.
 *
 * La oferta mantiene sus datos academicos y de presentacion. Se excluyen
 * detalles de integracion y diagnosticos destinados al Editor/administracion.
 */
final class Oferta_Public_DTO {
    private const PRIVATE_FIELDS = [
        'mailjet_contact_list_ids',
        'validacion_ciclos',
    ];

    public static function from_schema(array $payload): array {
        foreach (self::PRIVATE_FIELDS as $field) {
            unset($payload[$field]);
        }
        return $payload;
    }

    public static function from_wp_rest(array $payload): array {
        foreach (self::PRIVATE_FIELDS as $field) {
            unset($payload[$field]);
        }

        if (isset($payload['meta']) && is_array($payload['meta'])) {
            unset($payload['meta']['mailjet_contact_list_ids']);
        }

        return $payload;
    }
}

/** En v1 el contrato administrativo conserva el payload historico. */
final class Oferta_Admin_DTO {
    public static function from_schema(array $payload): array {
        return $payload;
    }

    public static function from_wp_rest(array $payload): array {
        return $payload;
    }
}

final class Oferta_REST_DTO {
    public static function init(): void {
        add_filter('rest_request_after_callbacks', [self::class, 'transform_custom_response'], 20, 3);
        add_filter('rest_prepare_oferta-academica', [self::class, 'transform_wp_response'], 20, 3);
    }

    public static function transform_custom_response($response, $handler, $request) {
        if (!self::is_read_request($request) || is_wp_error($response) || !is_object($response) || !method_exists($response, 'get_data') || !method_exists($response, 'set_data')) {
            return $response;
        }

        $route = (string) $request->get_route();
        $is_collection = preg_match('#^/flacso/v1/oferta-academica/?$#', $route) === 1;
        $is_item = preg_match('#^/flacso/v1/oferta-academica/(?P<id>\d+)/?$#', $route, $matches) === 1;

        if (!$is_collection && !$is_item) {
            return $response;
        }

        if ($is_item) {
            $post_id = (int) $matches['id'];
            $post = get_post($post_id);
            if ($post && $post->post_type === 'oferta-academica' && 'publish' !== $post->post_status && !current_user_can('edit_post', $post_id)) {
                return new WP_Error(
                    'oferta_not_found',
                    __('La oferta academica no existe.', 'flacso-oferta-academica'),
                    ['status' => 404]
                );
            }
        }

        $data = $response->get_data();
        $admin = current_user_can('edit_posts');

        if ($is_collection) {
            if (!is_array($data)) {
                return $response;
            }

            $data = array_map(
                static function ($item) use ($admin) {
                    return is_array($item) ? self::map_schema($item, $admin) : $item;
                },
                $data
            );
        } elseif (is_array($data)) {
            $data = self::map_schema($data, $admin);
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

        $admin = current_user_can('edit_posts');
        $response->set_data(
            $admin
                ? Oferta_Admin_DTO::from_wp_rest($data)
                : Oferta_Public_DTO::from_wp_rest($data)
        );

        return $response;
    }

    public static function map_schema(array $payload, bool $admin): array {
        return $admin
            ? Oferta_Admin_DTO::from_schema($payload)
            : Oferta_Public_DTO::from_schema($payload);
    }

    private static function is_read_request($request): bool {
        if (!is_object($request) || !method_exists($request, 'get_method') || !method_exists($request, 'get_route')) {
            return false;
        }

        return in_array(strtoupper((string) $request->get_method()), ['GET', 'HEAD'], true);
    }
}

Oferta_REST_DTO::init();
