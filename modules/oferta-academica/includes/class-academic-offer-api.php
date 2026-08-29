<?php

if (!defined('ABSPATH')) {
    exit;
}

/**
 * API administrativa de dominio para Oferta Academica.
 *
 * Esta frontera evita que los clientes conozcan el CPT, post meta o los slugs
 * internos de taxonomias. La persistencia sigue delegada al controlador REST
 * nativo de WordPress para conservar revisiones, hooks y validaciones.
 */
final class FLACSO_Academic_Offer_API {
    private const REST_NAMESPACE = 'flacso/v1';
    private const COLLECTION_ROUTE = '/ofertas';
    private const CORE_COLLECTION_ROUTE = '/wp/v2/oferta-academica';
    private const ADMIN_STATUSES = ['publish', 'draft', 'private'];
    private const MAX_PAGE_SIZE = 100;
    private const USER_PROGRAMS_META_KEY = 'flacso_program_ids';
    private const DETAIL_FIELDS = [
        'abreviacion', 'correo', 'duracion_meses', 'cohorte', 'proximo_inicio',
        'proximo_inicio_precision', 'calendario', 'malla_curricular',
        'calendario_modo', 'malla_curricular_modo', 'inscripciones_abiertas',
        'inscripciones_mensaje', 'inscripciones_mensaje_cerrado',
        'descripcion_html', 'modalidad_html', 'duracion_html', 'objetivos_html',
        'perfil_ingreso_html', 'requisitos_ingreso_html', 'perfil_egreso_html',
        'requisitos_egreso_html', 'financiacion_html', 'titulos_certificaciones_html',
        'acreditaciones_html', 'carta_presentacion_html', 'precios_nota',
        'coordinacion_academica', 'equipos', 'menciones', 'orientaciones',
        'titulos_intermedios', 'tabla_precios_tipo', 'modalidad_resumen',
        'carta_cta_titulo', 'carta_hero_etiqueta', 'asistente_academica_rol',
        'asistente_academica_correo', 'reconocido_mec',
        'reconocimiento_internacional', 'mostrar_expedicion_titulo',
        'convenio_iin_oea', 'mostrar_costos_envio', 'carta_instancias_presenciales',
        'visibilidad_carta', 'asistente_academica_docente_id', 'tabla_precio_id',
        'precios_filas', 'documentos', 'mailjet_contact_list_ids',
        '_seminario_nombre', '_seminario_presentacion_seminario', '_seminario_objetivo_general',
        '_seminario_objetivos_especificos', '_seminario_unidades_academicas',
        '_seminario_forma_aprobacion', '_seminario_carga_horaria', '_seminario_creditos',
        '_seminario_acreditacion', '_seminario_acredita_maestria', '_seminario_acredita_doctorado',
    ];

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
            [
                'methods' => WP_REST_Server::DELETABLE,
                'callback' => [self::class, 'delete_item'],
                'permission_callback' => [self::class, 'can_delete_item'],
            ],
        ]);

        register_rest_route(self::REST_NAMESPACE, self::COLLECTION_ROUTE . '/(?P<id>\d+)/revisions', [
            'methods' => WP_REST_Server::READABLE,
            'callback' => [self::class, 'get_revisions'],
            'permission_callback' => [self::class, 'can_read_item'],
        ]);

        register_rest_route(self::REST_NAMESPACE, self::COLLECTION_ROUTE . '/taxonomies/(?P<kind>types|programs)', [
            'methods' => WP_REST_Server::CREATABLE,
            'callback' => [self::class, 'create_taxonomy_term'],
            'permission_callback' => [self::class, 'can_manage_taxonomies'],
        ]);

        register_rest_route(self::REST_NAMESPACE, self::COLLECTION_ROUTE . '/taxonomies/(?P<kind>types|programs)/(?P<id>\d+)', [
            'methods' => WP_REST_Server::EDITABLE,
            'callback' => [self::class, 'update_taxonomy_term'],
            'permission_callback' => [self::class, 'can_manage_taxonomies'],
        ]);
    }

    public static function can_read_collection(): bool {
        return current_user_can('edit_posts') && self::has_academic_scope();
    }

    public static function can_create_item(): bool {
        return current_user_can('edit_posts') && self::has_academic_scope();
    }

    public static function can_read_item(WP_REST_Request $request): bool {
        $post_id = self::resolve_offer_id(absint($request['id']));
        return $post_id > 0
            && current_user_can('edit_post', $post_id)
            && self::can_access_offer($post_id);
    }

    public static function can_edit_item(WP_REST_Request $request): bool {
        $post_id = absint($request['id']);
        return current_user_can('edit_post', $post_id) && self::can_access_offer($post_id);
    }

    public static function can_delete_item(WP_REST_Request $request): bool {
        $post_id = absint($request['id']);
        return current_user_can('delete_post', $post_id) && self::can_access_offer($post_id);
    }

    public static function can_manage_taxonomies(): bool {
        return current_user_can('manage_options');
    }

    public static function list_items() {
        $items = [];

        foreach (self::ADMIN_STATUSES as $status) {
            $page = 1;
            $total_pages = 1;

            do {
                $request = new WP_REST_Request('GET', self::CORE_COLLECTION_ROUTE);
                $request->set_query_params([
                    'context' => 'edit',
                    'status' => $status,
                    'per_page' => self::MAX_PAGE_SIZE,
                    'page' => $page,
                    '_embed' => true,
                ]);
                $response = rest_do_request($request);

                if (self::is_error_response($response)) {
                    return $response;
                }

                $data = $response->get_data();
                if (!is_array($data)) {
                    return new WP_Error(
                        'flacso_academic_offers_invalid_response',
                        __('WordPress devolvio una respuesta invalida para Oferta Academica.', 'flacso-oferta-academica'),
                        ['status' => 502]
                    );
                }

                foreach ($data as $raw_item) {
                    if (is_array($raw_item)) {
                        $item = self::to_domain_item($raw_item);
                        $program_id = self::extract_relation_id($item['program'] ?? null);
                        if (self::can_access_program($program_id)) {
                            $items[] = $item;
                        }
                    }
                }

                $headers = $response->get_headers();
                $total_pages = max(1, absint($headers['X-WP-TotalPages'] ?? $headers['x-wp-totalpages'] ?? 1));
                $page++;
            } while ($page <= $total_pages);
        }

        usort($items, static fn(array $a, array $b): int => strcasecmp((string) $a['title'], (string) $b['title']));

        return rest_ensure_response([
            'items' => $items,
            'total' => count($items),
        ]);
    }

    public static function get_item(WP_REST_Request $request) {
        $post_id = self::resolve_offer_id(absint($request['id']));
        if ($post_id <= 0) {
            return new WP_Error(
                'flacso_academic_offer_not_found',
                __('La oferta academica no existe.', 'flacso-oferta-academica'),
                ['status' => 404]
            );
        }
        return self::dispatch_item_read($post_id);
    }

    public static function create_item(WP_REST_Request $request) {
        $payload = self::get_json_payload($request);
        $program_id = self::extract_relation_id($payload['program'] ?? null);

        if ($program_id <= 0) {
            return new WP_Error(
                'flacso_academic_offer_program_required',
                __('Cada oferta academica debe pertenecer a un Programa.', 'flacso-oferta-academica'),
                ['status' => 400]
            );
        }

        if (!self::can_access_program($program_id)) {
            return new WP_Error(
                'flacso_academic_offer_program_forbidden',
                __('No tenes permisos sobre el Programa seleccionado.', 'flacso-oferta-academica'),
                ['status' => 403]
            );
        }

        $core_request = new WP_REST_Request('POST', self::CORE_COLLECTION_ROUTE);
        $core_request->set_body_params(self::to_wordpress_payload($payload));
        return self::dispatch_write($core_request, 201);
    }

    public static function update_item(WP_REST_Request $request) {
        $post_id = absint($request['id']);
        $payload = self::get_json_payload($request);
        if (array_key_exists('program', $payload)) {
            $program_id = self::extract_relation_id($payload['program']);
            if ($program_id <= 0 || !self::can_access_program($program_id)) {
                return new WP_Error(
                    'flacso_academic_offer_program_forbidden',
                    __('No tenes permisos sobre el Programa seleccionado.', 'flacso-oferta-academica'),
                    ['status' => 403]
                );
            }
        }
        $core_request = new WP_REST_Request('POST', self::CORE_COLLECTION_ROUTE . '/' . $post_id);
        $core_request->set_body_params(self::to_wordpress_payload($payload));
        return self::dispatch_write($core_request);
    }

    public static function delete_item(WP_REST_Request $request) {
        $post_id = absint($request['id']);
        $core_request = new WP_REST_Request('DELETE', self::CORE_COLLECTION_ROUTE . '/' . $post_id);
        $core_request->set_query_params(['force' => true]);
        $response = rest_do_request($core_request);

        if (self::is_error_response($response)) {
            return $response;
        }

        $data = $response->get_data();
        $previous = is_array($data['previous'] ?? null)
            ? self::to_domain_item($data['previous'])
            : null;

        return rest_ensure_response([
            'deleted' => !empty($data['deleted']),
            'previous' => $previous,
        ]);
    }

    public static function get_revisions(WP_REST_Request $request) {
        $post_id = absint($request['id']);
        $core_request = new WP_REST_Request('GET', self::CORE_COLLECTION_ROUTE . '/' . $post_id . '/revisions');
        $core_request->set_query_params(['context' => 'edit', 'per_page' => self::MAX_PAGE_SIZE]);
        $response = rest_do_request($core_request);

        if (self::is_error_response($response)) {
            return $response;
        }

        $items = [];
        foreach ((array) $response->get_data() as $revision) {
            if (!is_array($revision)) {
                continue;
            }
            $items[] = [
                'id' => absint($revision['id'] ?? 0),
                'date' => (string) ($revision['date'] ?? ''),
                'modified' => (string) ($revision['modified'] ?? ''),
                'authorId' => absint($revision['author'] ?? 0),
            ];
        }

        return rest_ensure_response(['items' => $items, 'total' => count($items)]);
    }

    public static function create_taxonomy_term(WP_REST_Request $request) {
        return self::write_taxonomy_term($request, 0);
    }

    public static function update_taxonomy_term(WP_REST_Request $request) {
        return self::write_taxonomy_term($request, absint($request['id']));
    }

    public static function to_wordpress_payload(array $payload): array {
        $map = [
            'title' => 'title',
            'content' => 'content',
            'excerpt' => 'excerpt',
            'slug' => 'slug',
            'status' => 'status',
            'password' => 'password',
            'featuredMediaId' => 'featured_media',
            'associatedPostId' => 'associated_post_id',
            'seminarIds' => '_oferta_seminarios_ids',
            'relations' => 'relaciones_oferta_academica',
        ];
        $wordpress = [];

        foreach ($map as $domain_key => $wordpress_key) {
            if (array_key_exists($domain_key, $payload)) {
                $wordpress[$wordpress_key] = $payload[$domain_key];
            }
        }

        $fields = $payload['fields'] ?? [];
        if (is_array($fields)) {
            $wordpress['meta'] = $fields;
            foreach ($fields as $key => $value) {
                if (is_string($key) && $key !== '') {
                    $wordpress[$key] = $value;
                }
            }
        }

        $taxonomies = [];
        if (array_key_exists('type', $payload)) {
            $type_id = self::extract_relation_id($payload['type']);
            $taxonomies['tipo-oferta-academica'] = $type_id > 0 ? [$type_id] : [];
        }
        if (array_key_exists('program', $payload)) {
            $program_id = self::extract_relation_id($payload['program']);
            $wordpress['program'] = $program_id > 0 ? ['id' => $program_id] : null;
            $taxonomies['area_tematica'] = $program_id > 0 ? [$program_id] : [];
        }
        if (!empty($taxonomies)) {
            $wordpress['taxonomies'] = $taxonomies;
        }

        return $wordpress;
    }

    public static function to_domain_item(array $raw): array {
        $post_id = absint($raw['id'] ?? 0);
        $schema = $post_id > 0 && class_exists('Oferta_Data_Schema')
            ? Oferta_Data_Schema::get_schema($post_id)
            : [];
        $taxonomies = is_array($raw['taxonomies'] ?? null) ? $raw['taxonomies'] : [];
        $program = is_array($raw['program'] ?? null)
            ? $raw['program']
            : (is_array($schema['program'] ?? null) ? $schema['program'] : null);
        $types = is_array($taxonomies['tipo-oferta-academica'] ?? null)
            ? $taxonomies['tipo-oferta-academica']
            : [];
        $featured = is_array($raw['featured_image_data'] ?? null)
            ? $raw['featured_image_data']
            : null;

        $raw_meta = is_array($raw['meta'] ?? null) ? $raw['meta'] : [];
        $fields = [];
        foreach (self::DETAIL_FIELDS as $field) {
            if (array_key_exists($field, $raw)) {
                $fields[$field] = $raw[$field];
            } elseif (array_key_exists($field, $schema)) {
                $fields[$field] = $schema[$field];
            } elseif (array_key_exists($field, $raw_meta)) {
                $fields[$field] = $raw_meta[$field];
            }
        }

        return [
            'id' => $post_id,
            'title' => self::rendered_value($raw['title'] ?? ($schema['titulo'] ?? '')),
            'content' => self::rendered_value($raw['content'] ?? ''),
            'excerpt' => self::rendered_value($raw['excerpt'] ?? ''),
            'slug' => (string) ($raw['slug'] ?? ''),
            'status' => (string) ($raw['status'] ?? 'publish'),
            'url' => (string) ($raw['link'] ?? ''),
            'visibility' => [
                'passwordProtected' => !empty($raw['password'])
                    || !empty($raw['content']['protected'])
                    || !empty($raw['excerpt']['protected']),
            ],
            'featuredMedia' => $featured ?: [
                'id' => absint($raw['featured_media'] ?? 0),
                'url' => '',
                'large' => '',
                'medium' => '',
                'alt' => '',
            ],
            'associatedPostId' => absint($raw['associated_post_id'] ?? 0),
            'seminarIds' => array_values(array_map('absint', (array) ($raw['_oferta_seminarios_ids'] ?? []))),
            'relations' => array_values((array) ($raw['relaciones_oferta_academica'] ?? [])),
            'program' => $program,
            'type' => $types[0] ?? null,
            'fields' => $fields,
            'priceTable' => $schema['tabla_precio'] ?? ($raw['tabla_precio'] ?? null),
            'cycle' => [
                'items' => $schema['cadena_ciclos'] ?? ($raw['cadena_ciclos'] ?? []),
                'validation' => $schema['validacion_ciclos'] ?? ($raw['validacion_ciclos'] ?? ['es_valida' => true, 'problemas' => []]),
            ],
        ];
    }

    private static function dispatch_item_read(int $post_id) {
        $request = new WP_REST_Request('GET', self::CORE_COLLECTION_ROUTE . '/' . $post_id);
        $request->set_query_params(['context' => 'edit', '_embed' => true]);
        $response = rest_do_request($request);

        if (self::is_error_response($response)) {
            return $response;
        }

        $data = $response->get_data();
        return rest_ensure_response(self::to_domain_item(is_array($data) ? $data : []));
    }

    private static function dispatch_write(WP_REST_Request $request, int $success_status = 200) {
        $response = rest_do_request($request);
        if (self::is_error_response($response)) {
            return $response;
        }

        $data = $response->get_data();
        $result = rest_ensure_response(self::to_domain_item(is_array($data) ? $data : []));
        $result->set_status($success_status);
        return $result;
    }

    private static function write_taxonomy_term(WP_REST_Request $request, int $term_id) {
        $taxonomy = self::taxonomy_from_kind((string) $request['kind']);
        $route = '/wp/v2/' . $taxonomy . ($term_id > 0 ? '/' . $term_id : '');
        $core_request = new WP_REST_Request('POST', $route);
        $core_request->set_body_params(self::get_json_payload($request));
        $response = rest_do_request($core_request);

        if (self::is_error_response($response)) {
            return $response;
        }

        $data = $response->get_data();
        return rest_ensure_response(self::to_domain_term(is_array($data) ? $data : []));
    }

    private static function to_domain_term(array $raw): array {
        return [
            'id' => absint($raw['id'] ?? 0),
            'name' => (string) ($raw['name'] ?? ''),
            'slug' => (string) ($raw['slug'] ?? ''),
            'description' => (string) ($raw['description'] ?? ''),
            'featuredImage' => [
                'id' => absint($raw['featured_image_id'] ?? ($raw['meta']['featured_image_id'] ?? 0)),
                'url' => (string) ($raw['featured_image_url'] ?? ($raw['meta']['featured_image_url'] ?? '')),
                'data' => $raw['featured_image_data'] ?? null,
            ],
        ];
    }

    private static function taxonomy_from_kind(string $kind): string {
        return $kind === 'programs' ? 'area_tematica' : 'tipo-oferta-academica';
    }

    private static function get_json_payload(WP_REST_Request $request): array {
        $payload = $request->get_json_params();
        return is_array($payload) ? $payload : [];
    }

    private static function extract_relation_id($value): int {
        if (is_array($value)) {
            return absint($value['id'] ?? 0);
        }
        return absint($value);
    }

    private static function rendered_value($value): string {
        if (is_array($value)) {
            return (string) ($value['rendered'] ?? $value['raw'] ?? '');
        }
        return (string) $value;
    }

    private static function resolve_offer_id(int $requested_id): int {
        if ($requested_id <= 0) {
            return 0;
        }

        $post = get_post($requested_id);
        if ($post && $post->post_type === 'oferta-academica') {
            return $requested_id;
        }

        $matches = get_posts([
            'post_type' => 'oferta-academica',
            'post_status' => self::ADMIN_STATUSES,
            'posts_per_page' => 1,
            'fields' => 'ids',
            'meta_key' => '_oferta_page_id',
            'meta_value' => $requested_id,
            'no_found_rows' => true,
        ]);

        return !empty($matches) ? absint($matches[0]) : 0;
    }

    private static function is_error_response($response): bool {
        return is_wp_error($response)
            || (is_object($response) && method_exists($response, 'is_error') && $response->is_error());
    }

    private static function has_academic_scope(): bool {
        return current_user_can('manage_options') || !empty(self::get_current_user_program_ids());
    }

    private static function can_access_offer(int $post_id): bool {
        if (current_user_can('manage_options')) {
            return true;
        }

        $program = class_exists('Oferta_Taxonomies')
            ? Oferta_Taxonomies::get_program($post_id)
            : null;
        return self::can_access_program(self::extract_relation_id($program));
    }

    private static function can_access_program(int $program_id): bool {
        if (current_user_can('manage_options')) {
            return true;
        }
        if ($program_id <= 0) {
            return false;
        }
        return in_array($program_id, self::get_current_user_program_ids(), true);
    }

    private static function get_current_user_program_ids(): array {
        $user_id = get_current_user_id();
        if ($user_id <= 0) {
            return [];
        }

        $stored = get_user_meta($user_id, self::USER_PROGRAMS_META_KEY, true);
        $values = is_array($stored) ? $stored : preg_split('/[\s,;]+/', (string) $stored, -1, PREG_SPLIT_NO_EMPTY);
        $ids = array_values(array_unique(array_filter(array_map('absint', (array) $values))));

        /** Permite integrar otro directorio sin duplicar la regla de autorizacion. */
        $filtered = apply_filters('flacso_academic_offer_user_program_ids', $ids, $user_id);
        return array_values(array_unique(array_filter(array_map('absint', is_array($filtered) ? $filtered : []))));
    }
}

FLACSO_Academic_Offer_API::init();
