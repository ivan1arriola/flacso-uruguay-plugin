<?php
/**
 * Puente autenticado entre FLACSO Meta Bot y el módulo Consultas.
 *
 * El chatbot agrupa Seminarios y Ofertas bajo «Posgrados», pero sus
 * consultas continúan utilizando servicios y tablas independientes.
 */
if (!defined('ABSPATH')) {
    exit;
}

final class FLACSO_Chatbot_Inquiry_API {
    private const NAMESPACE = 'flacso/v1';

    public static function init(): void {
        if (function_exists('add_action')) {
            add_action('rest_api_init', [self::class, 'register_routes']);
        }
    }

    public static function register_routes(): void {
        register_rest_route(self::NAMESPACE, '/chatbot/catalogo', [
            'methods' => WP_REST_Server::READABLE,
            'callback' => [self::class, 'catalog'],
            'permission_callback' => '__return_true',
        ]);
        register_rest_route(self::NAMESPACE, '/chatbot/consultas/status', [
            'methods' => WP_REST_Server::READABLE,
            'callback' => [self::class, 'status'],
            'permission_callback' => [self::class, 'authorize'],
        ]);
        register_rest_route(self::NAMESPACE, '/chatbot/consultas', [
            'methods' => WP_REST_Server::CREATABLE,
            'callback' => [self::class, 'submit'],
            'permission_callback' => [self::class, 'authorize'],
        ]);
    }

    /**
     * Token exclusivo del puente, configurado como constante en wp-config.php.
     * No se acepta el token de webhooks de Meta ni una sesión administrativa.
     */
    public static function authorize(WP_REST_Request $request) {
        $secret = defined('FLACSO_METABOT_INQUIRY_TOKEN')
            ? (string) constant('FLACSO_METABOT_INQUIRY_TOKEN') : '';
        if (strlen($secret) < 32) {
            return new WP_Error('chatbot_not_configured', 'Integración no configurada.', ['status' => 503]);
        }

        $header = trim((string) $request->get_header('authorization'));
        if (!preg_match('/^Bearer\s+([^\s]+)$/i', $header, $matches)
            || !hash_equals($secret, (string) $matches[1])) {
            return new WP_Error('chatbot_unauthorized', 'Credenciales inválidas.', ['status' => 401]);
        }
        return true;
    }

    /** Sólo sirve respuestas saludables cuando los servicios y PostgreSQL responden. */
    public static function status(): WP_REST_Response {
        if (!class_exists('FLACSO_DB')
            || !class_exists('FLACSO_Offer_Inquiry_Service')
            || !class_exists('FLACSO_Seminar_Inquiry_Service')) {
            return self::error('service_unavailable', 'Servicio de consultas no disponible.', 503);
        }
        try {
            FLACSO_DB::connection()->query('SELECT 1');
        } catch (Throwable $error) {
            return self::error('database_unavailable', 'Servicio de consultas no disponible.', 503);
        }
        return new WP_REST_Response(['ok' => true], 200);
    }

    /** Catálogo público unificado; sólo se exponen posts publicados. */
    public static function catalog(): WP_REST_Response {
        $items = [];
        foreach (['oferta-academica' => 'offer', 'seminario' => 'seminar'] as $post_type => $kind) {
            $posts = get_posts([
                'post_type' => $post_type,
                'post_status' => 'publish',
                'posts_per_page' => -1,
                'orderby' => 'title',
                'order' => 'ASC',
                'no_found_rows' => true,
            ]);
            foreach ($posts as $post) {
                if ($post->post_status !== 'publish' || $post->post_type !== $post_type) {
                    continue;
                }
                $type = $kind === 'seminar' ? 'seminario' : (
                    class_exists('FLACSO_Oferta_Academica')
                        ? (string) FLACSO_Oferta_Academica::get_tipo((int) $post->ID) : ''
                );
                $items[] = [
                    'id' => (int) $post->ID,
                    'title' => (string) get_the_title($post),
                    'kind' => $kind,
                    'type' => $type,
                    'url' => (string) get_permalink($post),
                ];
            }
        }

        $response = new WP_REST_Response(['schemaVersion' => 1, 'items' => $items], 200);
        $response->header('Cache-Control', 'public, max-age=60');
        return $response;
    }

    /**
     * Identidad académica siempre resuelta del lado del servidor.
     * La clave de idempotencia la calcula el bot a partir del evento Meta.
     */
    public static function submit(WP_REST_Request $request): WP_REST_Response {
        $body = $request->get_json_params();
        if (!is_array($body)) {
            return self::error('invalid_payload', 'Solicitud inválida.', 400);
        }

        $channel = (string) ($body['channel'] ?? '');
        $event_id = (string) ($body['eventId'] ?? '');
        $academic = $body['academic'] ?? null;
        $first = sanitize_text_field((string) ($body['firstName'] ?? ''));
        $last = sanitize_text_field((string) ($body['lastName'] ?? ''));
        $email = sanitize_email((string) ($body['email'] ?? ''));

        if (!in_array($channel, ['instagram', 'messenger'], true)
            || !preg_match('/^metabot-[a-f0-9]{64}$/', $event_id)
            || !is_array($academic)
            || !in_array(($academic['kind'] ?? null), ['offer', 'seminar'], true)
            || !isset($academic['id']) || !is_numeric($academic['id'])
            || (int) $academic['id'] <= 0 || $first === ''
            || strlen($first) > 120 || strlen($last) > 120
            || !is_email($email)) {
            return self::error('invalid_fields', 'Los datos de la consulta son incompletos o inválidos.', 422);
        }

        $kind = (string) $academic['kind'];
        $post_id = (int) $academic['id'];
        $post = get_post($post_id);
        $expected_type = $kind === 'offer' ? 'oferta-academica' : 'seminario';
        if (!$post || $post->post_type !== $expected_type || $post->post_status !== 'publish') {
            return self::error('academic_not_found', 'No se encontró el posgrado publicado.', 422);
        }

        $source = $channel === 'instagram' ? 'Instagram' : 'Facebook Messenger';
        $message = sanitize_textarea_field((string) ($body['message'] ?? ''));
        $payload = [
            'event_id' => $event_id,
            'nombre' => $first,
            'apellido' => $last,
            'correo' => $email,
            'source' => $source,
            'consulta' => mb_substr($message, 0, 2000),
            'telefono' => sanitize_text_field((string) ($body['phone'] ?? '')),
            'marketingConsentGranted' => false,
        ];
        if ($kind === 'offer') {
            $payload['offerWpId'] = $post_id;
            $payload['offerName'] = (string) get_the_title($post);
            $service = 'FLACSO_Offer_Inquiry_Service';
        } else {
            $payload['seminarWpId'] = $post_id;
            $payload['seminarName'] = (string) get_the_title($post);
            $service = 'FLACSO_Seminar_Inquiry_Service';
        }

        if (!class_exists($service)) {
            return self::error('service_unavailable', 'Servicio de consultas no disponible.', 503);
        }

        try {
            $result = $service::submit($payload);
        } catch (Throwable $error) {
            error_log('[FLACSO] Falló el puente de consultas del chatbot: ' . get_class($error));
            return self::error('submission_failed', 'No se pudo registrar la consulta.', 503);
        }

        if (empty($result['ok'])) {
            $status = (int) ($result['code'] ?? 503);
            return self::error('submission_failed', 'No se pudo registrar la consulta.', $status >= 400 && $status <= 599 ? $status : 503);
        }

        return new WP_REST_Response([
            'ok' => true,
            'consultaId' => (string) ($result['consulta_id'] ?? $event_id),
            'duplicate' => !empty($result['duplicate']),
        ], 200);
    }

    private static function error(string $code, string $message, int $status): WP_REST_Response {
        return new WP_REST_Response(['ok' => false, 'code' => $code, 'message' => $message], $status);
    }
}
