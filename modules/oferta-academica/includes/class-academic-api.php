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

        $event_id = !empty($params['event_id'])
            ? sanitize_text_field($params['event_id'])
            : (function_exists('wp_generate_uuid4') ? wp_generate_uuid4() : '');
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

        if (!class_exists('FLACSO_Seminar_Inquiry_Service')) {
            $service_file = dirname(__DIR__, 2) . '/consultas/services/class-flacso-seminar-inquiry-service.php';
            if (file_exists($service_file)) {
                require_once $service_file;
            }
        }

        $result = FLACSO_Seminar_Inquiry_Service::submit($payload);

        if (!empty($result['ok'])) {
            return new WP_REST_Response([
                'success'      => true,
                'message'      => 'Consulta enviada correctamente',
                'timestamp'    => current_time('mysql'),
                'consulta_id'  => $result['consulta_id'] ?? null,
                'email_status' => $result['email'] ?? null,
            ], 200);
        }

        return new WP_REST_Response([
            'success' => false,
            'message' => $result['message'] ?? $result['error'] ?? 'Error al procesar la consulta.',
        ], $result['code'] ?? 500);
    }

    public static function can_write(): bool {
        return current_user_can('edit_posts');
    }
}
