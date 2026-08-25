<?php

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Centraliza la frontera entre contenido REST publico y contenido editorial.
 *
 * Los endpoints publicos de docentes y seminarios pueden seguir siendo
 * consultados sin autenticacion, pero un usuario anonimo nunca debe recibir
 * posts que no esten publicados. Los usuarios con permisos de edicion
 * mantienen el acceso editorial necesario para el editor externo.
 */
final class FLACSO_REST_Visibility {
    private const GUARDED_POST_TYPES = ['docente', 'seminario'];

    public static function init(): void {
        add_action('pre_get_posts', [self::class, 'restrict_rest_query'], 999);
        add_filter('rest_request_before_callbacks', [self::class, 'guard_private_item'], 10, 3);
    }

    /**
     * Fuerza post_status=publish en colecciones REST publicas de los CPT
     * protegidos. Esto tambien neutraliza parametros como status=draft y
     * ramas internas que construyan la consulta con post_status=any.
     */
    public static function restrict_rest_query($query): void {
        if (!self::is_rest_request()) {
            return;
        }

        if (!is_object($query) || !method_exists($query, 'get') || !method_exists($query, 'set')) {
            return;
        }

        $post_type = $query->get('post_type');
        $post_types = is_array($post_type) ? $post_type : [$post_type];
        $post_types = array_values(array_filter(array_map('strval', $post_types)));

        if (empty(array_intersect(self::GUARDED_POST_TYPES, $post_types))) {
            return;
        }

        if (current_user_can('edit_posts')) {
            return;
        }

        $query->set('post_status', 'publish');
    }

    /**
     * Evita que un GET por ID permita enumerar borradores o privados.
     * Se responde como no encontrado para no revelar la existencia del post.
     */
    public static function guard_private_item($response, $handler, $request) {
        if (!self::is_read_request($request)) {
            return $response;
        }

        $target = self::resolve_guarded_route((string) $request->get_route());
        if ($target === null) {
            return $response;
        }

        $post = get_post($target['id']);
        if (!$post || $post->post_type !== $target['post_type']) {
            return $response;
        }

        if ('publish' === $post->post_status || current_user_can('edit_post', $post->ID)) {
            return $response;
        }

        return new WP_Error(
            'rest_post_invalid_id',
            __('No se encontro el contenido solicitado.', 'flacso-uruguay'),
            ['status' => 404]
        );
    }

    private static function is_rest_request(): bool {
        return defined('REST_REQUEST') && REST_REQUEST;
    }

    private static function is_read_request($request): bool {
        if (!is_object($request) || !method_exists($request, 'get_method') || !method_exists($request, 'get_route')) {
            return false;
        }

        return in_array(strtoupper((string) $request->get_method()), ['GET', 'HEAD'], true);
    }

    private static function resolve_guarded_route(string $route): ?array {
        $routes = [
            '#^/flacso-docentes/v1/docentes/(?P<id>\d+)/?$#' => 'docente',
            '#^/flacso/v1/seminarios/(?P<id>\d+)/?$#' => 'seminario',
        ];

        foreach ($routes as $pattern => $post_type) {
            if (preg_match($pattern, $route, $matches)) {
                return [
                    'id' => (int) $matches['id'],
                    'post_type' => $post_type,
                ];
            }
        }

        return null;
    }
}

FLACSO_REST_Visibility::init();
