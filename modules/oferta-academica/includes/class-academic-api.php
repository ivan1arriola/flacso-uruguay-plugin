<?php

if (!defined('ABSPATH')) {
    exit;
}

/** API REST del modelo academico final. */
final class FLACSO_Academic_API {
    public static function init(): void {
        add_action('rest_api_init', [self::class, 'register_routes']);
    }

    public static function register_routes(): void {
        foreach (array_keys(FLACSO_Academic_Repository::definitions()) as $entity) {
            register_rest_route('flacso/v1', '/' . $entity, [
                [
                    'methods' => WP_REST_Server::READABLE,
                    'callback' => static function (WP_REST_Request $request) use ($entity) {
                        return rest_ensure_response(FLACSO_Academic_Repository::list($entity, [
                            'per_page' => $request->get_param('per_page'),
                            'parent_id' => $request->get_param('parent_id'),
                        ]));
                    },
                    'permission_callback' => '__return_true',
                ],
                [
                    'methods' => WP_REST_Server::CREATABLE,
                    'callback' => static function (WP_REST_Request $request) use ($entity) {
                        return self::save_entity($entity, $request->get_json_params());
                    },
                    'permission_callback' => [self::class, 'can_write'],
                ],
            ]);

            register_rest_route('flacso/v1', '/' . $entity . '/(?P<id>\d+)', [
                [
                    'methods' => WP_REST_Server::READABLE,
                    'callback' => static function (WP_REST_Request $request) use ($entity) {
                        $data = FLACSO_Academic_Repository::to_array($entity, absint($request['id']));
                        return $data ?: new WP_Error('not_found', __('Registro no encontrado.', 'flacso-uruguay'), ['status' => 404]);
                    },
                    'permission_callback' => '__return_true',
                ],
                [
                    'methods' => WP_REST_Server::EDITABLE,
                    'callback' => static function (WP_REST_Request $request) use ($entity) {
                        return self::save_entity($entity, $request->get_json_params(), absint($request['id']));
                    },
                    'permission_callback' => [self::class, 'can_write'],
                ],
                [
                    'methods' => WP_REST_Server::DELETABLE,
                    'callback' => static function (WP_REST_Request $request) use ($entity) {
                        $id = absint($request['id']);
                        $definition = FLACSO_Academic_Repository::definition($entity);
                        if (!$definition || get_post_type($id) !== $definition['post_type']) {
                            return new WP_Error('not_found', __('Registro no encontrado.', 'flacso-uruguay'), ['status' => 404]);
                        }
                        $deleted = $request->get_param('force') ? wp_delete_post($id, true) : wp_trash_post($id);
                        return $deleted ? ['deleted' => true, 'id' => $id] : new WP_Error('delete_failed', __('No se pudo eliminar.', 'flacso-uruguay'), ['status' => 500]);
                    },
                    'permission_callback' => [self::class, 'can_write'],
                ],
            ]);
        }

        register_rest_route('flacso/v1', '/preinscripciones/catalogo', [
            'methods' => WP_REST_Server::READABLE,
            'callback' => static function () { return rest_ensure_response(FLACSO_Academic_Catalog::registration_catalog()); },
            'permission_callback' => '__return_true',
        ]);

        register_rest_route('flacso/v1', '/consulta-seminario', [
            'methods' => WP_REST_Server::CREATABLE,
            'callback' => [self::class, 'submit_consulta_seminario'],
            'permission_callback' => '__return_true',
        ]);
    }

    /**
     * Persiste una entidad académica y aplica los metadatos que forman parte del
     * contrato público de la entidad pero que no deben confundirse con campos
     * editoriales genéricos del repositorio.
     */
    private static function save_entity(string $entity, array $payload, int $id = 0) {
        $result = FLACSO_Academic_Repository::save($entity, $payload, $id);
        if (is_wp_error($result)) {
            return $result;
        }

        $post_id = absint($result['id'] ?? 0);
        if ($post_id < 1) {
            return $result;
        }

        if ($entity === 'ediciones' && array_key_exists('preinscripcion_habilitada', $payload)) {
            update_post_meta(
                $post_id,
                'preinscripcion_habilitada',
                rest_sanitize_boolean($payload['preinscripcion_habilitada'])
            );
            $result = FLACSO_Academic_Repository::to_array($entity, $post_id);
            $result['preinscripcion_habilitada'] = rest_sanitize_boolean(
                get_post_meta($post_id, 'preinscripcion_habilitada', true)
            );
            $result['preinscripcion'] = FLACSO_Preinscripcion::for_edition($post_id);
        }

        return $result;
    }

    public static function submit_consulta_seminario(WP_REST_Request $request) {
        $params = $request->get_json_params();
        if (empty($params)) {
            $params = $request->get_body_params();
        }

        $campos_obligatorios = ['seminario_id', 'seminario_titulo', 'nombre', 'correo', 'telefono', 'pais', 'consulta'];
        $campos_faltantes = [];

        foreach ($campos_obligatorios as $campo) {
            if (empty($params[$campo])) {
                $campos_faltantes[] = $campo;
            }
        }

        if (!empty($campos_faltantes)) {
            return new WP_REST_Response([
                'success' => false,
                'message' => 'Campos obligatorios faltantes: ' . implode(', ', $campos_faltantes),
            ], 400);
        }

        $seminario_id = intval($params['seminario_id']);
        $seminario = get_post($seminario_id);
        if (!$seminario || $seminario->post_type !== 'seminario') {
            return new WP_REST_Response([
                'success' => false,
                'message' => 'El seminario especificado no existe.',
            ], 404);
        }

        $endpoint_url = get_option('flacso_seminario_consulta_endpoint_url', '');
        if ($endpoint_url === '') {
            $editor_url = get_option('flacso_external_editor_url', '');
            if ($editor_url !== '') {
                $endpoint_url = trailingslashit($editor_url) . 'api/consultas/seminarios';
            }
        }

        if ($endpoint_url === '') {
            $endpoint_url = 'https://editor.flacso.edu.uy/api/consultas/seminarios';
        }

        $webhook_token = get_option('flacso_webhook_token', '');

        $event_id = wp_generate_uuid4();
        $payload = [
            'event_id'         => $event_id,
            'seminario_id'     => (string) $seminario_id,
            'seminario_titulo' => sanitize_text_field($params['seminario_titulo']),
            'nombre'           => sanitize_text_field($params['nombre']),
            'apellido'         => sanitize_text_field($params['apellido'] ?? ''),
            'correo'           => sanitize_email($params['correo']),
            'telefono'         => sanitize_text_field($params['telefono']),
            'pais'             => sanitize_text_field($params['pais']),
            'consulta'         => sanitize_textarea_field($params['consulta']),
            'source'           => sanitize_text_field($params['source'] ?? 'Seminario'),
            'meta'             => [
                'ip'         => isset($_SERVER['REMOTE_ADDR']) ? sanitize_text_field($_SERVER['REMOTE_ADDR']) : '',
                'user_agent' => isset($_SERVER['HTTP_USER_AGENT']) ? sanitize_text_field($_SERVER['HTTP_USER_AGENT']) : '',
                'timestamp'  => current_time('mysql'),
            ],
        ];

        $headers = ['Content-Type' => 'application/json; charset=utf-8'];
        if ($webhook_token !== '') {
            $headers['X-FLACSO-Webhook-Token'] = $webhook_token;
            $headers['Authorization'] = 'Bearer ' . $webhook_token;
        }
        $headers['X-Idempotency-Key'] = $event_id;

        $response = wp_remote_post($endpoint_url, [
            'body'    => wp_json_encode($payload),
            'headers' => $headers,
            'timeout' => 20,
        ]);

        if (is_wp_error($response)) {
            return new WP_REST_Response([
                'success' => false,
                'message' => 'Error de conexión con el CRM: ' . $response->get_error_message(),
            ], 502);
        }

        $response_code = (int) wp_remote_retrieve_response_code($response);
        $response_body = (string) wp_remote_retrieve_body($response);
        $decoded_body = json_decode($response_body, true);

        if ($response_code < 200 || $response_code >= 300) {
            return new WP_REST_Response([
                'success' => false,
                'message' => 'El CRM respondió con código ' . $response_code . '. La consulta no se confirmó.',
                'response_code' => $response_code,
            ], 502);
        }

        $crm_confirmed = !empty($decoded_body['ok']) && !empty($decoded_body['data']['saved']);
        if (!$crm_confirmed) {
            return new WP_REST_Response([
                'success' => false,
                'message' => 'El CRM no confirmó el guardado de la consulta.',
                'editor_response' => $decoded_body,
            ], 502);
        }

        return new WP_REST_Response([
            'success' => true,
            'message' => 'Consulta enviada correctamente',
            'timestamp' => current_time('mysql'),
            'editor_response' => $decoded_body,
        ], 200);
    }

    public static function can_write(): bool {
        return current_user_can('edit_posts');
    }
}
