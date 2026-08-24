<?php

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Contrato publico de Seminarios.
 *
 * Mantiene los datos academicos y de presentacion y elimina claves cuyo uso
 * es exclusivamente operativo/editorial.
 */
final class Seminario_Public_DTO {
    private const PRIVATE_META_FIELDS = [
        'mail_contacto',
        'mostrar_en_formulario',
    ];

    public static function from_legacy(array $payload): array {
        if (isset($payload['meta']) && is_array($payload['meta'])) {
            $payload['meta'] = self::public_meta($payload['meta']);
        }

        if (isset($payload['seminarios_componentes_data']) && is_array($payload['seminarios_componentes_data'])) {
            $payload['seminarios_componentes_data'] = array_map(
                static function ($component) {
                    if (!is_array($component)) {
                        return $component;
                    }
                    if (isset($component['meta']) && is_array($component['meta'])) {
                        $component['meta'] = self::public_meta($component['meta']);
                    }
                    return $component;
                },
                $payload['seminarios_componentes_data']
            );
        }

        return $payload;
    }

    public static function public_meta(array $meta): array {
        foreach (self::PRIVATE_META_FIELDS as $field) {
            unset($meta[$field]);
        }
        return $meta;
    }
}

/** En v1 el contrato administrativo conserva el payload historico. */
final class Seminario_Admin_DTO {
    public static function from_legacy(array $payload): array {
        return $payload;
    }
}

final class Seminario_REST_DTO {
    public static function init(): void {
        add_filter('rest_request_after_callbacks', [self::class, 'transform_response'], 20, 3);
    }

    public static function transform_response($response, $handler, $request) {
        if (!self::is_read_request($request) || is_wp_error($response) || !is_object($response) || !method_exists($response, 'get_data') || !method_exists($response, 'set_data')) {
            return $response;
        }

        $route = (string) $request->get_route();
        $is_collection = preg_match('#^/flacso/v1/seminarios/?$#', $route) === 1;
        $is_item = preg_match('#^/flacso/v1/seminarios/\d+/?$#', $route) === 1;

        if (!$is_collection && !$is_item) {
            return $response;
        }

        $data = $response->get_data();
        $admin = current_user_can('edit_posts');

        if ($is_collection) {
            if (!is_array($data)) {
                return $response;
            }

            $data = array_map(
                static function ($item) use ($admin) {
                    return is_array($item) ? self::map($item, $admin) : $item;
                },
                $data
            );
        } elseif (is_array($data)) {
            $data = self::map($data, $admin);
        }

        $response->set_data($data);
        return $response;
    }

    public static function map(array $payload, bool $admin): array {
        return $admin
            ? Seminario_Admin_DTO::from_legacy($payload)
            : Seminario_Public_DTO::from_legacy($payload);
    }

    private static function is_read_request($request): bool {
        if (!is_object($request) || !method_exists($request, 'get_method') || !method_exists($request, 'get_route')) {
            return false;
        }

        return in_array(strtoupper((string) $request->get_method()), ['GET', 'HEAD'], true);
    }
}

Seminario_REST_DTO::init();
