<?php

declare(strict_types=1);

if (!defined('ABSPATH')) {
    exit;
}

final class FLACSO_Preinscriptions_REST {
    public const NAMESPACE = 'flacso/v1';
    public const ROUTE     = '/preinscripciones';

    /** Campos internos: sólo se entregan al portal de preinscripciones autenticado. */
    private const PRIVATE_TARGET_FIELDS = ['notificationEmail', 'notificationEmails'];

    public static function init(): void {
        if (function_exists('add_action')) {
            add_action('rest_api_init', [self::class, 'register_routes']);
        }
    }

    public static function register_routes(): void {
        if (!function_exists('register_rest_route')) {
            return;
        }

        register_rest_route(self::NAMESPACE, self::ROUTE, [
            'methods'             => 'GET',
            'callback'            => [self::class, 'index'],
            'permission_callback' => [self::class, 'authorize'],
        ]);
    }

    /**
     * El catálogo es público. Un header Authorization, si se envía, debe ser el
     * token del portal (constante FLACSO_PREINSCRIPCIONES_CATALOG_TOKEN en
     * wp-config.php); así una configuración errónea falla visiblemente en vez
     * de entregar en silencio el catálogo sin correos.
     */
    public static function authorize($request) {
        if (self::authorization_header($request) === '') {
            return true;
        }
        if (self::is_portal_request($request)) {
            return true;
        }
        return new WP_Error('preinscriptions_unauthorized', 'Credenciales inválidas.', ['status' => 401]);
    }

    public static function index($request): WP_REST_Response {
        $payload = FLACSO_Preinscriptions_Cache::get_catalog();
        $is_portal = self::is_portal_request($request);

        if (!$is_portal) {
            // Los correos de la Oferta Académica no son públicos (a diferencia del correo de contacto).
            $payload['targets'] = array_map(static function (array $target): array {
                return array_diff_key($target, array_flip(self::PRIVATE_TARGET_FIELDS));
            }, $payload['targets'] ?? []);
        }

        $response = new WP_REST_Response($payload, 200);
        $response->header('Cache-Control', $is_portal ? 'private, no-store' : 'public, max-age=60');
        $response->header('Vary', 'Authorization');

        return $response;
    }

    private static function is_portal_request($request): bool {
        $secret = defined('FLACSO_PREINSCRIPCIONES_CATALOG_TOKEN')
            ? (string) constant('FLACSO_PREINSCRIPCIONES_CATALOG_TOKEN') : '';
        if (strlen($secret) < 32) {
            return false;
        }
        return preg_match('/^Bearer\s+([^\s]+)$/i', self::authorization_header($request), $matches) === 1
            && hash_equals($secret, (string) $matches[1]);
    }

    private static function authorization_header($request): string {
        return is_object($request) && method_exists($request, 'get_header')
            ? trim((string) $request->get_header('authorization'))
            : '';
    }
}
