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
                        $result = FLACSO_Academic_Repository::list_paged($entity, [
                            'per_page' => $request->get_param('per_page'),
                            'page' => $request->get_param('page'),
                            'parent_id' => $request->get_param('parent_id'),
                        ]);
                        $response = rest_ensure_response($result['items']);
                        $response->header('X-WP-Total', (string) $result['total']);
                        $response->header('X-WP-TotalPages', (string) $result['pages']);
                        return $response;
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
                        $id = absint($request['id']);
                        // Sin permiso de edición, solo se entrega contenido publicado (no borradores, privados ni auto-drafts).
                        if (get_post_status($id) !== 'publish' && !current_user_can('edit_post', $id)) {
                            return new WP_Error('not_found', __('Registro no encontrado.', 'flacso-uruguay'), ['status' => 404]);
                        }
                        $data = FLACSO_Academic_Repository::to_array($entity, $id);
                        return $data ?: new WP_Error('not_found', __('Registro no encontrado.', 'flacso-uruguay'), ['status' => 404]);
                    },
                    'permission_callback' => '__return_true',
                ],
                [
                    'methods' => WP_REST_Server::EDITABLE,
                    'callback' => static function (WP_REST_Request $request) use ($entity) {
                        return self::save_entity($entity, $request->get_json_params(), absint($request['id']));
                    },
                    'permission_callback' => [self::class, 'can_edit_item'],
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
                    'permission_callback' => [self::class, 'can_delete_item'],
                ],
            ]);
        }

        register_rest_route('flacso/v1', '/preinscripciones/catalogo', [
            'methods' => WP_REST_Server::READABLE,
            'callback' => static function () { return rest_ensure_response(FLACSO_Academic_Catalog::registration_catalog()); },
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

    public static function can_write(): bool {
        return current_user_can('edit_posts');
    }

    /** Editar exige permiso sobre ese registro, no solo `edit_posts` (que tiene un Colaborador). */
    public static function can_edit_item(WP_REST_Request $request): bool {
        return current_user_can('edit_post', absint($request['id']));
    }

    /** Borrar (también con force=1) exige `delete_post` sobre ese registro. */
    public static function can_delete_item(WP_REST_Request $request): bool {
        return current_user_can('delete_post', absint($request['id']));
    }
}
