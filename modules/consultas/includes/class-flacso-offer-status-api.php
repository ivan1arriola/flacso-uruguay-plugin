<?php
/**
 * Estado de preinscripción de una oferta para las campañas Mautic.
 *
 * El plugin de Mautic lo consulta al verificar el estado y al resolver los
 * tokens de los correos. Es de lectura pública, como el resto del catálogo.
 */
if (!defined('ABSPATH')) {
    exit;
}

final class FLACSO_Offer_Status_API {
    private const NAMESPACE = 'flacso/v1';

    public static function init(): void {
        if (function_exists('add_action')) {
            add_action('rest_api_init', [self::class, 'register_routes']);
        }
    }

    public static function register_routes(): void {
        register_rest_route(self::NAMESPACE, '/ofertas/(?P<id>\d+)/consulta', [
            'methods' => WP_REST_Server::READABLE,
            'callback' => [self::class, 'status'],
            'permission_callback' => '__return_true',
        ]);
    }

    public static function status(WP_REST_Request $request) {
        $status = FLACSO_Inquiry_Context_Service::offer_status(absint($request['id']));
        if ($status === null) {
            return new WP_Error('not_found', 'Oferta no encontrada.', ['status' => 404]);
        }

        // El estado cambia al abrir o cerrar una cohorte: ningún intermediario debe cachearlo.
        $response = new WP_REST_Response($status, 200);
        $response->header('Cache-Control', 'no-store');
        return $response;
    }
}
