<?php

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Cohortes de Oferta Academica.
 *
 * La cohorte pasa a ser la fuente de verdad para fecha de inicio y estado de
 * inscripciones. Los metadatos historicos de oferta-academica se mantienen como
 * espejo de compatibilidad mientras tema, formularios y otros consumidores se
 * migran al nuevo contrato.
 */
final class FLACSO_Cohorte_API {
    public const POST_TYPE = 'cohorte';
    private const REST_NAMESPACE = 'flacso/v1';
    private const ROUTE = '/cohortes';
    private const MIGRATION_ACTION = 'flacso_migrate_offer_cohorts';
    private const MIGRATION_MARKER = '_flacso_cohorte_migrated_post_id';

    public const META_OFFER_ID = 'oferta_academica_id';
    public const META_START_DATE = 'start_date';
    public const META_START_PRECISION = 'start_date_precision';
    public const META_END_DATE = 'end_date';
    public const META_STATUS = 'cohort_status';
    public const META_OPEN = 'is_inscriptions_open';
    public const META_OPEN_MESSAGE = 'open_message';
    public const META_CLOSED_MESSAGE = 'closed_message';
    public const META_YEAR = 'year';
    public const META_SEMESTER = 'semester';
    public const META_NUMBER = 'cohort_number';
    public const META_PREINSCRIPTION_FLOW = 'flujo_preinscripcion';
    public const META_PREINSCRIPTION_START = 'preinscription_start_date';
    public const META_PREINSCRIPTION_END = 'preinscription_end_date';
    public const META_FLOW_LOCKED = '_flujo_preinscripcion_bloqueado';

    private const PREINSCRIPTION_SCHEMA_OPTION = 'flacso_cohorte_preinscription_schema_version';
    private const PREINSCRIPTION_SCHEMA_VERSION = 1;

    private static bool $syncing = false;

    public static function init(): void {
        add_action('init', [self::class, 'register_post_type'], 7);
        add_action('init', [self::class, 'register_meta'], 8);
        add_action('rest_api_init', [self::class, 'register_routes']);
        add_action('save_post_' . self::POST_TYPE, [self::class, 'sync_legacy_offer_meta'], 20, 3);
        // El CPT/endpoints legacy permanecen en Release A. Sus migraciones
        // anteriores quedan deliberadamente sin hooks: solo se migra por WP-CLI.
    }

    public static function register_post_type(): void {
        register_post_type(self::POST_TYPE, [
            'labels' => [
                'name' => __('Cohortes', 'flacso-oferta-academica'),
                'singular_name' => __('Cohorte', 'flacso-oferta-academica'),
            ],
            'public' => false,
            'show_ui' => false,
            'show_in_rest' => false,
            'supports' => ['title'],
            'capability_type' => 'post',
            'map_meta_cap' => true,
        ]);
    }

    public static function register_meta(): void {
        $common = [
            'single' => true,
            'auth_callback' => static fn(): bool => current_user_can('edit_posts'),
            'show_in_rest' => false,
        ];

        register_post_meta(self::POST_TYPE, self::META_OFFER_ID, $common + [
            'type' => 'integer',
            'sanitize_callback' => 'absint',
        ]);
        register_post_meta(self::POST_TYPE, self::META_START_DATE, $common + [
            'type' => 'string',
            'sanitize_callback' => [self::class, 'sanitize_date_value'],
        ]);
        register_post_meta(self::POST_TYPE, self::META_START_PRECISION, $common + [
            'type' => 'string',
            'sanitize_callback' => [self::class, 'sanitize_precision'],
        ]);
        register_post_meta(self::POST_TYPE, self::META_END_DATE, $common + [
            'type' => 'string',
            'sanitize_callback' => [self::class, 'sanitize_date_value'],
        ]);
        register_post_meta(self::POST_TYPE, self::META_STATUS, $common + [
            'type' => 'string',
            'sanitize_callback' => 'sanitize_key',
        ]);
        register_post_meta(self::POST_TYPE, self::META_OPEN, $common + [
            'type' => 'boolean',
            'sanitize_callback' => [self::class, 'sanitize_boolean'],
        ]);
        register_post_meta(self::POST_TYPE, self::META_OPEN_MESSAGE, $common + [
            'type' => 'string',
            'sanitize_callback' => 'sanitize_text_field',
        ]);
        register_post_meta(self::POST_TYPE, self::META_CLOSED_MESSAGE, $common + [
            'type' => 'string',
            'sanitize_callback' => 'sanitize_text_field',
        ]);
        register_post_meta(self::POST_TYPE, self::META_YEAR, $common + [
            'type' => 'integer',
            'sanitize_callback' => 'absint',
        ]);
        register_post_meta(self::POST_TYPE, self::META_SEMESTER, $common + [
            'type' => 'string',
            'sanitize_callback' => [self::class, 'sanitize_semester'],
        ]);
        register_post_meta(self::POST_TYPE, self::META_NUMBER, $common + [
            'type' => 'integer',
            'sanitize_callback' => 'absint',
        ]);
        register_post_meta(self::POST_TYPE, self::META_PREINSCRIPTION_FLOW, $common + [
            'type' => 'string',
            'sanitize_callback' => [FLACSO_Preinscription_Flow::class, 'normalize'],
        ]);
        register_post_meta(self::POST_TYPE, self::META_PREINSCRIPTION_START, $common + [
            'type' => 'string',
            'sanitize_callback' => [self::class, 'sanitize_date_value'],
        ]);
        register_post_meta(self::POST_TYPE, self::META_PREINSCRIPTION_END, $common + [
            'type' => 'string',
            'sanitize_callback' => [self::class, 'sanitize_date_value'],
        ]);
        register_post_meta(self::POST_TYPE, self::META_FLOW_LOCKED, $common + [
            'type' => 'boolean',
            'sanitize_callback' => [self::class, 'sanitize_boolean'],
        ]);
    }

    public static function register_routes(): void {
        register_rest_route(self::REST_NAMESPACE, self::ROUTE, [
            [
                'methods' => WP_REST_Server::READABLE,
                'callback' => [self::class, 'list_items'],
                'permission_callback' => static fn(): bool => current_user_can('edit_posts'),
            ],
            [
                'methods' => WP_REST_Server::CREATABLE,
                'callback' => [self::class, 'create_item'],
                'permission_callback' => static fn(): bool => current_user_can('edit_posts'),
            ],
        ]);

        register_rest_route(self::REST_NAMESPACE, self::ROUTE . '/(?P<id>\d+)', [
            [
                'methods' => WP_REST_Server::READABLE,
                'callback' => [self::class, 'get_item'],
                'permission_callback' => [self::class, 'can_access_item'],
            ],
            [
                'methods' => WP_REST_Server::EDITABLE,
                'callback' => [self::class, 'update_item'],
                'permission_callback' => [self::class, 'can_access_item'],
            ],
        ]);
    }

    public static function list_items(WP_REST_Request $request) {
        $offer_id = absint($request->get_param('offerWpId') ?: $request->get_param('academicOfferId'));
        $args = [
            'post_type' => self::POST_TYPE,
            'post_status' => ['publish', 'draft', 'private'],
            'posts_per_page' => -1,
            'orderby' => 'date',
            'order' => 'DESC',
        ];
        if ($offer_id > 0) {
            if (!current_user_can('edit_post', $offer_id)) {
                return new WP_Error('flacso_cohort_forbidden', __('No tenes permisos sobre esta oferta.', 'flacso-oferta-academica'), ['status' => 403]);
            }
            $args['meta_query'] = [[
                'key' => self::META_OFFER_ID,
                'value' => $offer_id,
                'compare' => '=',
                'type' => 'NUMERIC',
            ]];
        }

        $items = [];
        foreach (get_posts($args) as $post) {
            $item = self::to_domain_item($post);
            if ($item['offerWpId'] > 0 && current_user_can('edit_post', $item['offerWpId'])) {
                $items[] = $item;
            }
        }

        return rest_ensure_response(['items' => $items, 'total' => count($items)]);
    }

    public static function get_item(WP_REST_Request $request) {
        $post = get_post(absint($request['id']));
        if (!$post instanceof WP_Post || $post->post_type !== self::POST_TYPE) {
            return new WP_Error('flacso_cohort_not_found', __('La cohorte no existe.', 'flacso-oferta-academica'), ['status' => 404]);
        }
        return rest_ensure_response(self::to_domain_item($post));
    }

    public static function create_item(WP_REST_Request $request) {
        return self::save_from_request($request, 0);
    }

    public static function update_item(WP_REST_Request $request) {
        return self::save_from_request($request, absint($request['id']));
    }

    public static function can_access_item(WP_REST_Request $request): bool {
        $post = get_post(absint($request['id']));
        if (!$post instanceof WP_Post || $post->post_type !== self::POST_TYPE) {
            return false;
        }
        $offer_id = absint(get_post_meta($post->ID, self::META_OFFER_ID, true));
        return $offer_id > 0 && current_user_can('edit_post', $offer_id);
    }

    private static function save_from_request(WP_REST_Request $request, int $cohort_id) {
        $payload = $request->get_json_params();
        if (!is_array($payload)) {
            $payload = $request->get_body_params();
        }

        $existing = $cohort_id > 0 ? get_post($cohort_id) : null;
        $offer_id = absint($payload['offerWpId'] ?? ($existing ? get_post_meta($existing->ID, self::META_OFFER_ID, true) : 0));
        $name = sanitize_text_field((string) ($payload['name'] ?? ($existing ? $existing->post_title : '')));

        if ($offer_id <= 0 || !in_array(get_post_type($offer_id), ['oferta-academica', 'seminario'], true)) {
            return new WP_Error('flacso_cohort_offer_required', __('La cohorte debe pertenecer a una Oferta Academica valida.', 'flacso-oferta-academica'), ['status' => 400]);
        }
        if (!current_user_can('edit_post', $offer_id)) {
            return new WP_Error('flacso_cohort_forbidden', __('No tenes permisos sobre esta oferta.', 'flacso-oferta-academica'), ['status' => 403]);
        }
        if ($name === '') {
            return new WP_Error('flacso_cohort_name_required', __('El nombre de la cohorte es obligatorio.', 'flacso-oferta-academica'), ['status' => 400]);
        }

        $current_flow = $existing instanceof WP_Post ? self::get_preinscription_flow((int) $existing->ID) : FLACSO_Preinscription_Flow::LEGACY_EDITOR;
        $requested_flow = array_key_exists('preinscriptionFlow', $payload)
            ? (string) $payload['preinscriptionFlow']
            : $current_flow;
        if (!FLACSO_Preinscription_Flow::is_valid($requested_flow)) {
            return new WP_Error('flacso_cohort_invalid_flow', __('El sistema de preinscripcion no es valido.', 'flacso-oferta-academica'), ['status' => 400]);
        }
        if ($existing instanceof WP_Post && self::is_flow_locked((int) $existing->ID) && $requested_flow !== $current_flow) {
            return new WP_Error(
                'flacso_cohort_flow_locked',
                __('No se puede cambiar el sistema porque esta convocatoria ya abrio preinscripciones.', 'flacso-oferta-academica'),
                ['status' => 409]
            );
        }
        $payload['preinscriptionFlow'] = $requested_flow;

        $postarr = [
            'post_type' => self::POST_TYPE,
            'post_status' => 'publish',
            'post_title' => $name,
        ];
        if ($cohort_id > 0) {
            $postarr['ID'] = $cohort_id;
        }

        self::$syncing = true;
        $saved_id = wp_insert_post($postarr, true);
        self::$syncing = false;
        if (is_wp_error($saved_id)) {
            return $saved_id;
        }

        update_post_meta($saved_id, self::META_OFFER_ID, $offer_id);
        self::save_payload_meta($saved_id, $payload);

        $is_open = self::sanitize_boolean($payload['isInscriptionsOpen'] ?? get_post_meta($saved_id, self::META_OPEN, true));
        if ($is_open) {
            self::close_other_cohorts($offer_id, (int) $saved_id);
            update_post_meta($saved_id, self::META_FLOW_LOCKED, true);
        }

        self::sync_legacy_offer_meta((int) $saved_id, get_post($saved_id), true);
        return rest_ensure_response(self::to_domain_item(get_post($saved_id)));
    }

    private static function save_payload_meta(int $cohort_id, array $payload): void {
        $map = [
            'startDate' => [self::META_START_DATE, [self::class, 'sanitize_date_value']],
            'startDatePrecision' => [self::META_START_PRECISION, [self::class, 'sanitize_precision']],
            'endDate' => [self::META_END_DATE, [self::class, 'sanitize_date_value']],
            'status' => [self::META_STATUS, 'sanitize_key'],
            'isInscriptionsOpen' => [self::META_OPEN, [self::class, 'sanitize_boolean']],
            'openMessage' => [self::META_OPEN_MESSAGE, 'sanitize_text_field'],
            'closedMessage' => [self::META_CLOSED_MESSAGE, 'sanitize_text_field'],
            'year' => [self::META_YEAR, 'absint'],
            'semester' => [self::META_SEMESTER, [self::class, 'sanitize_semester']],
            'number' => [self::META_NUMBER, 'absint'],
            'preinscriptionFlow' => [self::META_PREINSCRIPTION_FLOW, [FLACSO_Preinscription_Flow::class, 'normalize']],
            'preinscriptionStartDate' => [self::META_PREINSCRIPTION_START, [self::class, 'sanitize_date_value']],
            'preinscriptionEndDate' => [self::META_PREINSCRIPTION_END, [self::class, 'sanitize_date_value']],
        ];
        foreach ($map as $key => [$meta_key, $sanitize]) {
            if (!array_key_exists($key, $payload)) {
                continue;
            }
            update_post_meta($cohort_id, $meta_key, call_user_func($sanitize, $payload[$key]));
        }
    }

    private static function close_other_cohorts(int $offer_id, int $keep_id): void {
        $ids = get_posts([
            'post_type' => self::POST_TYPE,
            'post_status' => ['publish', 'draft', 'private'],
            'posts_per_page' => -1,
            'fields' => 'ids',
            'post__not_in' => [$keep_id],
            'meta_query' => [[
                'key' => self::META_OFFER_ID,
                'value' => $offer_id,
                'compare' => '=',
                'type' => 'NUMERIC',
            ]],
        ]);
        foreach ($ids as $id) {
            if (self::sanitize_boolean(get_post_meta($id, self::META_OPEN, true))) {
                update_post_meta($id, self::META_OPEN, false);
                update_post_meta($id, self::META_FLOW_LOCKED, true);
            }
        }
    }

    public static function sync_legacy_offer_meta(int $post_id, $post, bool $update): void {
        if (self::$syncing || !$post instanceof WP_Post || $post->post_type !== self::POST_TYPE) {
            return;
        }
        $offer_id = absint(get_post_meta($post_id, self::META_OFFER_ID, true));
        if ($offer_id <= 0 || !in_array(get_post_type($offer_id), ['oferta-academica', 'seminario'], true)) {
            return;
        }

        $source = self::get_current_cohort_for_offer($offer_id);
        if (!$source instanceof WP_Post) {
            return;
        }

        if (get_post_type($offer_id) === 'seminario') {
            update_post_meta($offer_id, '_seminario_abierto_publico', self::sanitize_boolean(get_post_meta($source->ID, self::META_OPEN, true)));
        } else {
            update_post_meta($offer_id, 'cohorte', $source->post_title);
            update_post_meta($offer_id, 'proximo_inicio', (string) get_post_meta($source->ID, self::META_START_DATE, true));
            update_post_meta($offer_id, 'proximo_inicio_precision', (string) (get_post_meta($source->ID, self::META_START_PRECISION, true) ?: 'day'));
            update_post_meta($offer_id, 'inscripciones_abiertas', self::sanitize_boolean(get_post_meta($source->ID, self::META_OPEN, true)));
            update_post_meta($offer_id, 'inscripciones_mensaje', (string) get_post_meta($source->ID, self::META_OPEN_MESSAGE, true));
            update_post_meta($offer_id, 'inscripciones_mensaje_cerrado', (string) get_post_meta($source->ID, self::META_CLOSED_MESSAGE, true));
        }
        clean_post_cache($offer_id);
    }

    private static function get_current_cohort_for_offer(int $offer_id): ?WP_Post {
        $base = [
            'post_type' => self::POST_TYPE,
            'post_status' => ['publish', 'draft', 'private'],
            'posts_per_page' => 1,
            'orderby' => 'modified',
            'order' => 'DESC',
            'meta_query' => [[
                'key' => self::META_OFFER_ID,
                'value' => $offer_id,
                'compare' => '=',
                'type' => 'NUMERIC',
            ]],
        ];
        $open = $base;
        $open['meta_query'][] = [
            'key' => self::META_OPEN,
            'value' => '1',
            'compare' => '=',
        ];
        $items = get_posts($open);
        if (!empty($items)) {
            return $items[0];
        }
        $items = get_posts($base);
        return !empty($items) ? $items[0] : null;
    }

    private static function to_domain_item(WP_Post $post): array {
        $item = [
            'wpId' => (int) $post->ID,
            'offerWpId' => absint(get_post_meta($post->ID, self::META_OFFER_ID, true)),
            'name' => (string) $post->post_title,
            'startDate' => (string) get_post_meta($post->ID, self::META_START_DATE, true),
            'startDatePrecision' => (string) (get_post_meta($post->ID, self::META_START_PRECISION, true) ?: 'day'),
            'endDate' => (string) get_post_meta($post->ID, self::META_END_DATE, true),
            'status' => (string) get_post_meta($post->ID, self::META_STATUS, true),
            'isInscriptionsOpen' => self::sanitize_boolean(get_post_meta($post->ID, self::META_OPEN, true)),
            'openMessage' => (string) get_post_meta($post->ID, self::META_OPEN_MESSAGE, true),
            'closedMessage' => (string) get_post_meta($post->ID, self::META_CLOSED_MESSAGE, true),
            'year' => self::get_year((int) $post->ID),
            'semester' => (string) get_post_meta($post->ID, self::META_SEMESTER, true),
            'number' => max(1, absint(get_post_meta($post->ID, self::META_NUMBER, true))),
            'preinscriptionFlow' => self::get_preinscription_flow((int) $post->ID),
            'preinscriptionStartDate' => (string) get_post_meta($post->ID, self::META_PREINSCRIPTION_START, true),
            'preinscriptionEndDate' => (string) get_post_meta($post->ID, self::META_PREINSCRIPTION_END, true),
            'flowLocked' => self::is_flow_locked((int) $post->ID),
            'createdAt' => get_post_time(DATE_ATOM, true, $post),
            'updatedAt' => get_post_modified_time(DATE_ATOM, true, $post),
        ];
        $item['pageUrl'] = FLACSO_Preinscription_URL_Resolver::resolve((int) $post->ID);
        $item['backofficeUrl'] = FLACSO_Preinscription_URL_Resolver::resolve_backoffice((int) $post->ID);
        return $item;
    }

    public static function migrate_legacy_offer_meta(): array {
        $result = ['scanned' => 0, 'created' => 0, 'skipped' => 0, 'errors' => 0];
        $offers = get_posts([
            'post_type' => 'oferta-academica',
            'post_status' => ['publish', 'draft', 'private'],
            'posts_per_page' => -1,
            'orderby' => 'ID',
            'order' => 'ASC',
        ]);

        foreach ($offers as $offer) {
            $result['scanned']++;
            $name = sanitize_text_field((string) get_post_meta($offer->ID, 'cohorte', true));
            $start = self::sanitize_date_value(get_post_meta($offer->ID, 'proximo_inicio', true));
            $open_message = sanitize_text_field((string) get_post_meta($offer->ID, 'inscripciones_mensaje', true));
            $closed_message = sanitize_text_field((string) get_post_meta($offer->ID, 'inscripciones_mensaje_cerrado', true));
            $has_legacy = $name !== '' || $start !== '' || metadata_exists('post', $offer->ID, 'inscripciones_abiertas') || $open_message !== '' || $closed_message !== '';
            if (!$has_legacy) {
                $result['skipped']++;
                continue;
            }

            $marker = absint(get_post_meta($offer->ID, self::MIGRATION_MARKER, true));
            if ($marker > 0 && get_post_type($marker) === self::POST_TYPE) {
                $result['skipped']++;
                continue;
            }

            if ($name === '') {
                $name = __('Cohorte inicial', 'flacso-oferta-academica');
            }

            $existing = get_posts([
                'post_type' => self::POST_TYPE,
                'post_status' => ['publish', 'draft', 'private'],
                'posts_per_page' => 1,
                'title' => $name,
                'meta_query' => [[
                    'key' => self::META_OFFER_ID,
                    'value' => $offer->ID,
                    'compare' => '=',
                    'type' => 'NUMERIC',
                ]],
            ]);
            if (!empty($existing)) {
                update_post_meta($offer->ID, self::MIGRATION_MARKER, $existing[0]->ID);
                $result['skipped']++;
                continue;
            }

            self::$syncing = true;
            $cohort_id = wp_insert_post([
                'post_type' => self::POST_TYPE,
                'post_status' => 'publish',
                'post_title' => $name,
            ], true);
            self::$syncing = false;
            if (is_wp_error($cohort_id)) {
                $result['errors']++;
                continue;
            }

            update_post_meta($cohort_id, self::META_OFFER_ID, $offer->ID);
            update_post_meta($cohort_id, self::META_START_DATE, $start);
            update_post_meta($cohort_id, self::META_START_PRECISION, self::sanitize_precision(get_post_meta($offer->ID, 'proximo_inicio_precision', true)));
            update_post_meta($cohort_id, self::META_OPEN, self::sanitize_boolean(get_post_meta($offer->ID, 'inscripciones_abiertas', true)));
            update_post_meta($cohort_id, self::META_OPEN_MESSAGE, $open_message);
            update_post_meta($cohort_id, self::META_CLOSED_MESSAGE, $closed_message);
            update_post_meta($cohort_id, self::META_STATUS, 'upcoming');
            update_post_meta($cohort_id, self::META_PREINSCRIPTION_FLOW, FLACSO_Preinscription_Flow::LEGACY_EDITOR);
            update_post_meta($cohort_id, self::META_NUMBER, 1);
            $year = absint(substr($start, 0, 4));
            if ($year > 0) {
                update_post_meta($cohort_id, self::META_YEAR, $year);
            }
            if (self::sanitize_boolean(get_post_meta($offer->ID, 'inscripciones_abiertas', true))) {
                update_post_meta($cohort_id, self::META_FLOW_LOCKED, true);
            }
            update_post_meta($offer->ID, self::MIGRATION_MARKER, $cohort_id);
            self::sync_legacy_offer_meta((int) $cohort_id, get_post($cohort_id), true);
            $result['created']++;
        }

        return $result;
    }

    public static function register_migration_page(): void {
        add_submenu_page(
            'edit.php?post_type=oferta-academica',
            __('Migrar cohortes', 'flacso-oferta-academica'),
            __('Migrar cohortes', 'flacso-oferta-academica'),
            'manage_options',
            'flacso-cohort-migration',
            [self::class, 'render_migration_page']
        );
    }

    public static function render_migration_page(): void {
        if (!current_user_can('manage_options')) {
            return;
        }
        $result = isset($_GET['cohort_result']) ? json_decode(base64_decode((string) wp_unslash($_GET['cohort_result'])), true) : null;
        ?>
        <div class="wrap">
            <h1><?php esc_html_e('Migracion de cohortes', 'flacso-oferta-academica'); ?></h1>
            <p><?php esc_html_e('Crea una Cohorte por cada Oferta Academica que todavia guarda fecha, nombre o estado de inscripciones en metadatos legacy. La operacion es idempotente y conserva los metadatos antiguos como espejo de compatibilidad.', 'flacso-oferta-academica'); ?></p>
            <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
                <?php wp_nonce_field(self::MIGRATION_ACTION); ?>
                <input type="hidden" name="action" value="<?php echo esc_attr(self::MIGRATION_ACTION); ?>" />
                <?php submit_button(__('Ejecutar migracion de cohortes', 'flacso-oferta-academica')); ?>
            </form>
            <?php if (is_array($result)) : ?>
                <h2><?php esc_html_e('Resultado', 'flacso-oferta-academica'); ?></h2>
                <p><?php echo esc_html(sprintf('Escaneadas: %d · Creadas: %d · Omitidas: %d · Errores: %d', (int) $result['scanned'], (int) $result['created'], (int) $result['skipped'], (int) $result['errors'])); ?></p>
            <?php endif; ?>
        </div>
        <?php
    }

    public static function handle_migration(): void {
        if (!current_user_can('manage_options')) {
            wp_die(esc_html__('No autorizado.', 'flacso-oferta-academica'));
        }
        check_admin_referer(self::MIGRATION_ACTION);
        $result = self::migrate_legacy_offer_meta();
        wp_safe_redirect(add_query_arg([
            'post_type' => 'oferta-academica',
            'page' => 'flacso-cohort-migration',
            'cohort_result' => base64_encode(wp_json_encode($result)),
        ], admin_url('edit.php')));
        exit;
    }

    public static function sanitize_precision($value): string {
        $value = sanitize_key((string) $value);
        return in_array($value, ['day', 'month', 'year'], true) ? $value : 'day';
    }

    public static function sanitize_date_value($value): string {
        $value = sanitize_text_field((string) $value);
        return preg_match('/^\d{4}(?:-\d{2})?(?:-\d{2})?$/', $value) ? $value : '';
    }

    public static function sanitize_boolean($value): bool {
        if (is_bool($value)) {
            return $value;
        }
        return in_array(strtolower(trim((string) $value)), ['1', 'true', 'yes', 'si', 'on'], true);
    }

    public static function sanitize_semester($value): string {
        $value = strtoupper(str_replace(' ', '', sanitize_text_field((string) $value)));
        if (in_array($value, ['1', 'S1', '1S'], true)) {
            return '1S';
        }
        if (in_array($value, ['2', 'S2', '2S'], true)) {
            return '2S';
        }
        return '';
    }

    public static function get_offer_id(int $cohort_id): int {
        return absint(get_post_meta($cohort_id, self::META_OFFER_ID, true));
    }

    public static function get_preinscription_flow(int $cohort_id): string {
        return FLACSO_Preinscription_Flow::normalize(get_post_meta($cohort_id, self::META_PREINSCRIPTION_FLOW, true));
    }

    public static function is_flow_locked(int $cohort_id): bool {
        return self::sanitize_boolean(get_post_meta($cohort_id, self::META_OPEN, true))
            || self::sanitize_boolean(get_post_meta($cohort_id, self::META_FLOW_LOCKED, true));
    }

    public static function get_year(int $cohort_id): int {
        $year = absint(get_post_meta($cohort_id, self::META_YEAR, true));
        if ($year > 0) {
            return $year;
        }
        return absint(substr((string) get_post_meta($cohort_id, self::META_START_DATE, true), 0, 4));
    }

    public static function find_open_for_offer(int $offer_id): int {
        return self::find_for_offer($offer_id, true);
    }

    public static function find_latest_for_offer(int $offer_id): int {
        return self::find_for_offer($offer_id, false);
    }

    private static function find_for_offer(int $offer_id, bool $open_only): int {
        $meta_query = [[
            'key' => self::META_OFFER_ID,
            'value' => $offer_id,
            'compare' => '=',
            'type' => 'NUMERIC',
        ]];
        if ($open_only) {
            $meta_query[] = ['key' => self::META_OPEN, 'value' => '1', 'compare' => '='];
        }
        $ids = get_posts([
            'post_type' => self::POST_TYPE,
            'post_status' => ['publish', 'draft', 'private'],
            'posts_per_page' => 1,
            'fields' => 'ids',
            'orderby' => 'modified',
            'order' => 'DESC',
            'meta_query' => $meta_query,
        ]);
        return !empty($ids) ? absint($ids[0]) : 0;
    }

    public static function migrate_preinscription_defaults(): void {
        if (absint(get_option(self::PREINSCRIPTION_SCHEMA_OPTION, 0)) >= self::PREINSCRIPTION_SCHEMA_VERSION) {
            return;
        }
        $ids = get_posts([
            'post_type' => self::POST_TYPE,
            'post_status' => ['publish', 'draft', 'private'],
            'posts_per_page' => -1,
            'fields' => 'ids',
        ]);
        foreach ($ids as $id) {
            $id = absint($id);
            if (!FLACSO_Preinscription_Flow::is_valid(get_post_meta($id, self::META_PREINSCRIPTION_FLOW, true))) {
                update_post_meta($id, self::META_PREINSCRIPTION_FLOW, FLACSO_Preinscription_Flow::LEGACY_EDITOR);
            }
            if (self::sanitize_boolean(get_post_meta($id, self::META_OPEN, true))) {
                update_post_meta($id, self::META_FLOW_LOCKED, true);
            }
            if (absint(get_post_meta($id, self::META_NUMBER, true)) <= 0) {
                update_post_meta($id, self::META_NUMBER, 1);
            }
        }
        update_option(self::PREINSCRIPTION_SCHEMA_OPTION, self::PREINSCRIPTION_SCHEMA_VERSION, false);
    }
}

if (!function_exists('flacso_migrate_offer_cohorts')) {
    function flacso_migrate_offer_cohorts(): array {
        return FLACSO_Cohorte_API::migrate_legacy_offer_meta();
    }
}
