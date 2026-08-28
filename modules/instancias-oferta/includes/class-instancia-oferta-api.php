<?php

if (!defined('ABSPATH')) {
    exit;
}

/** Contrato administrativo Editor <-> WordPress para Cohortes/Ediciones. */
final class FLACSO_Instancia_Oferta_API {
    private const REST_NAMESPACE = 'flacso/v1';
    private const COLLECTION_ROUTE = '/instancias-oferta';

    public static function init(): void {
        add_action('rest_api_init', [self::class, 'register_routes']);
    }

    public static function register_routes(): void {
        register_rest_route(self::REST_NAMESPACE, self::COLLECTION_ROUTE, [
            [
                'methods' => WP_REST_Server::READABLE,
                'callback' => [self::class, 'list_items'],
                'permission_callback' => [self::class, 'can_read_collection'],
            ],
            [
                'methods' => WP_REST_Server::CREATABLE,
                'callback' => [self::class, 'create_item'],
                'permission_callback' => [self::class, 'can_create_item'],
            ],
        ]);

        register_rest_route(self::REST_NAMESPACE, self::COLLECTION_ROUTE . '/(?P<id>\d+)', [
            [
                'methods' => WP_REST_Server::READABLE,
                'callback' => [self::class, 'get_item'],
                'permission_callback' => [self::class, 'can_read_item'],
            ],
            [
                'methods' => WP_REST_Server::EDITABLE,
                'callback' => [self::class, 'update_item'],
                'permission_callback' => [self::class, 'can_edit_item'],
            ],
        ]);
    }

    public static function can_read_collection(): bool {
        return current_user_can('edit_posts');
    }

    public static function can_create_item(): bool {
        return current_user_can('edit_posts');
    }

    public static function can_read_item(WP_REST_Request $request): bool {
        $post = get_post(absint($request['id']));
        return $post && $post->post_type === FLACSO_Instancia_Oferta::POST_TYPE
            && current_user_can('edit_post', $post->ID);
    }

    public static function can_edit_item(WP_REST_Request $request): bool {
        return self::can_read_item($request);
    }

    public static function list_items(WP_REST_Request $request) {
        $offer_id = absint($request->get_param('academicOfferId'));
        $args = [
            'post_type' => FLACSO_Instancia_Oferta::POST_TYPE,
            'post_status' => 'publish',
            'posts_per_page' => -1,
            'orderby' => 'modified',
            'order' => 'DESC',
            'no_found_rows' => true,
        ];
        if ($offer_id > 0) {
            $args['meta_query'] = [[
                'key' => FLACSO_Instancia_Oferta::META_OFERTA_ID,
                'value' => $offer_id,
                'compare' => '=',
                'type' => 'NUMERIC',
            ]];
        }
        $items = array_map([self::class, 'to_domain_item'], get_posts($args));
        return rest_ensure_response(['items' => $items, 'total' => count($items)]);
    }

    public static function get_item(WP_REST_Request $request) {
        $post = get_post(absint($request['id']));
        if (!$post || $post->post_type !== FLACSO_Instancia_Oferta::POST_TYPE) {
            return self::not_found();
        }
        return rest_ensure_response(self::to_domain_item($post));
    }

    public static function create_item(WP_REST_Request $request) {
        $payload = self::payload($request);
        $validated = self::validate_payload($payload, 0);
        if (is_wp_error($validated)) {
            return $validated;
        }

        $post_id = wp_insert_post([
            'post_type' => FLACSO_Instancia_Oferta::POST_TYPE,
            'post_status' => 'publish',
            'post_title' => $validated['name'],
        ], true);
        if (is_wp_error($post_id)) {
            return $post_id;
        }

        self::persist(absint($post_id), $validated);
        $response = rest_ensure_response(self::to_domain_item(get_post($post_id)));
        $response->set_status(201);
        return $response;
    }

    public static function update_item(WP_REST_Request $request) {
        $post_id = absint($request['id']);
        $post = get_post($post_id);
        if (!$post || $post->post_type !== FLACSO_Instancia_Oferta::POST_TYPE) {
            return self::not_found();
        }

        $payload = array_merge(self::raw_domain_item($post), self::payload($request));
        $validated = self::validate_payload($payload, $post_id);
        if (is_wp_error($validated)) {
            return $validated;
        }

        $updated = wp_update_post([
            'ID' => $post_id,
            'post_title' => $validated['name'],
        ], true);
        if (is_wp_error($updated)) {
            return $updated;
        }

        self::persist($post_id, $validated);
        return rest_ensure_response(self::to_domain_item(get_post($post_id)));
    }

    public static function validate_payload(array $payload, int $post_id = 0) {
        $offer_id = absint($payload['academicOfferId'] ?? 0);
        $offer = get_post($offer_id);
        if (!$offer || !in_array($offer->post_type, ['oferta-academica', 'seminario'], true)) {
            return new WP_Error('flacso_instance_offer_required', __('La Oferta Academica no existe.', 'flacso-uruguay'), ['status' => 400]);
        }

        $name = sanitize_text_field((string) ($payload['name'] ?? ''));
        if ($name === '') {
            return new WP_Error('flacso_instance_name_required', __('El nombre de la Cohorte o Edicion es obligatorio.', 'flacso-uruguay'), ['status' => 400]);
        }

        $flow = (string) ($payload['preinscriptionFlow'] ?? FLACSO_Preinscription_Flow::LEGACY_EDITOR);
        if (!FLACSO_Preinscription_Flow::is_valid($flow)) {
            return new WP_Error('flacso_instance_invalid_flow', __('El sistema de preinscripcion no es valido.', 'flacso-uruguay'), ['status' => 400]);
        }

        $state = sanitize_key((string) ($payload['status'] ?? FLACSO_Instancia_Oferta::ESTADO_PLANIFICADA));
        if (!in_array($state, FLACSO_Instancia_Oferta::estados(), true)) {
            return new WP_Error('flacso_instance_invalid_status', __('El estado de la instancia no es valido.', 'flacso-uruguay'), ['status' => 400]);
        }

        if ($post_id > 0 && FLACSO_Instancia_Oferta::is_flow_locked($post_id)) {
            $current_flow = FLACSO_Instancia_Oferta::get_flow($post_id);
            if ($flow !== $current_flow) {
                return new WP_Error(
                    'flacso_instance_flow_locked',
                    __('No se puede cambiar el sistema porque esta convocatoria ya abrio preinscripciones.', 'flacso-uruguay'),
                    ['status' => 409]
                );
            }
        }

        return [
            // Seminarios existentes usan el mismo contrato de instancia durante
            // la transicion, aunque su contenido aun viva en el CPT historico.
            'academicOfferId' => $offer_id,
            'name' => $name,
            'year' => max(1, absint($payload['year'] ?? wp_date('Y'))),
            'semester' => self::normalize_semester($payload['semester'] ?? ''),
            'number' => max(1, absint($payload['number'] ?? 1)),
            'startDate' => self::date_value($payload['startDate'] ?? ''),
            'endDate' => self::date_value($payload['endDate'] ?? ''),
            'status' => $state,
            'preinscriptionFlow' => $flow,
            'preinscriptionStartDate' => self::date_value($payload['preinscriptionStartDate'] ?? ''),
            'preinscriptionEndDate' => self::date_value($payload['preinscriptionEndDate'] ?? ''),
            'openMessage' => sanitize_textarea_field((string) ($payload['openMessage'] ?? '')),
            'closedMessage' => sanitize_textarea_field((string) ($payload['closedMessage'] ?? '')),
        ];
    }

    public static function to_domain_item($post): array {
        $post_id = is_object($post) ? absint($post->ID ?? 0) : absint($post);
        $raw = self::raw_domain_item(is_object($post) ? $post : get_post($post_id));
        $raw['id'] = $post_id;
        $raw['wpId'] = $post_id;
        $raw['isInscriptionsOpen'] = $raw['status'] === FLACSO_Instancia_Oferta::ESTADO_ABIERTA;
        $raw['flowLocked'] = FLACSO_Instancia_Oferta::is_flow_locked($post_id);
        $raw['pageUrl'] = FLACSO_Preinscription_URL_Resolver::resolve($post_id);
        $raw['backofficeUrl'] = $raw['preinscriptionFlow'] === FLACSO_Preinscription_Flow::GESTOR_PREINSCRIPCIONES
            ? FLACSO_Preinscription_URL_Resolver::resolve_backoffice($post_id)
            : '';
        $raw['updatedAt'] = is_object($post) ? mysql_to_rfc3339((string) $post->post_modified_gmt) : '';
        return $raw;
    }

    private static function persist(int $post_id, array $payload): void {
        if ($payload['status'] === FLACSO_Instancia_Oferta::ESTADO_ABIERTA) {
            FLACSO_Instancia_Oferta::close_other_open_instances($payload['academicOfferId'], $post_id);
        }
        $meta = [
            FLACSO_Instancia_Oferta::META_OFERTA_ID => $payload['academicOfferId'],
            FLACSO_Instancia_Oferta::META_ANIO => $payload['year'],
            FLACSO_Instancia_Oferta::META_SEMESTRE => $payload['semester'],
            FLACSO_Instancia_Oferta::META_NUMERO => $payload['number'],
            FLACSO_Instancia_Oferta::META_FECHA_INICIO => $payload['startDate'],
            FLACSO_Instancia_Oferta::META_FECHA_FIN => $payload['endDate'],
            FLACSO_Instancia_Oferta::META_ESTADO => $payload['status'],
            FLACSO_Instancia_Oferta::META_FLUJO => $payload['preinscriptionFlow'],
            FLACSO_Instancia_Oferta::META_INSCRIPCION_INICIO => $payload['preinscriptionStartDate'],
            FLACSO_Instancia_Oferta::META_INSCRIPCION_FIN => $payload['preinscriptionEndDate'],
            FLACSO_Instancia_Oferta::META_MENSAJE_ABIERTA => $payload['openMessage'],
            FLACSO_Instancia_Oferta::META_MENSAJE_CERRADA => $payload['closedMessage'],
        ];
        foreach ($meta as $key => $value) {
            update_post_meta($post_id, $key, $value);
        }
        if ($payload['status'] === FLACSO_Instancia_Oferta::ESTADO_ABIERTA) {
            update_post_meta($post_id, FLACSO_Instancia_Oferta::META_FLUJO_BLOQUEADO, true);
        }
    }

    private static function raw_domain_item($post): array {
        $post_id = is_object($post) ? absint($post->ID ?? 0) : 0;
        return [
            'academicOfferId' => FLACSO_Instancia_Oferta::get_offer_id($post_id),
            'name' => is_object($post) ? (string) $post->post_title : '',
            'year' => absint(get_post_meta($post_id, FLACSO_Instancia_Oferta::META_ANIO, true)),
            'semester' => (string) get_post_meta($post_id, FLACSO_Instancia_Oferta::META_SEMESTRE, true),
            'number' => max(1, absint(get_post_meta($post_id, FLACSO_Instancia_Oferta::META_NUMERO, true))),
            'startDate' => (string) get_post_meta($post_id, FLACSO_Instancia_Oferta::META_FECHA_INICIO, true),
            'endDate' => (string) get_post_meta($post_id, FLACSO_Instancia_Oferta::META_FECHA_FIN, true),
            'status' => FLACSO_Instancia_Oferta::get_state($post_id),
            'preinscriptionFlow' => FLACSO_Instancia_Oferta::get_flow($post_id),
            'preinscriptionStartDate' => (string) get_post_meta($post_id, FLACSO_Instancia_Oferta::META_INSCRIPCION_INICIO, true),
            'preinscriptionEndDate' => (string) get_post_meta($post_id, FLACSO_Instancia_Oferta::META_INSCRIPCION_FIN, true),
            'openMessage' => (string) get_post_meta($post_id, FLACSO_Instancia_Oferta::META_MENSAJE_ABIERTA, true),
            'closedMessage' => (string) get_post_meta($post_id, FLACSO_Instancia_Oferta::META_MENSAJE_CERRADA, true),
        ];
    }

    private static function payload(WP_REST_Request $request): array {
        $payload = $request->get_json_params();
        return is_array($payload) ? $payload : [];
    }

    private static function normalize_semester($value): string {
        $value = strtoupper(str_replace(' ', '', sanitize_text_field((string) $value)));
        if (in_array($value, ['1', 'S1', '1S'], true)) {
            return '1S';
        }
        if (in_array($value, ['2', 'S2', '2S'], true)) {
            return '2S';
        }
        return '';
    }

    private static function date_value($value): string {
        $value = sanitize_text_field((string) $value);
        return preg_match('/^\d{4}-\d{2}-\d{2}$/', $value) ? $value : '';
    }

    private static function not_found(): WP_Error {
        return new WP_Error('flacso_instance_not_found', __('La Cohorte o Edicion no existe.', 'flacso-uruguay'), ['status' => 404]);
    }
}
