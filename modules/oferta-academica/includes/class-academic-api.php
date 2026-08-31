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
                        return FLACSO_Academic_Repository::save($entity, $request->get_json_params());
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
                        return FLACSO_Academic_Repository::save($entity, $request->get_json_params(), absint($request['id']));
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
    }

    public static function can_write(): bool {
        return current_user_can('edit_posts');
    }
}
