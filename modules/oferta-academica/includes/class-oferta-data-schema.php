<?php

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Estructura de datos para la Oferta Académica (sin renderizado).
 */
class Oferta_Data_Schema {
    private const HTML_FIELDS = [
        'modalidad_html',
        'duracion_html',
        'objetivos_html',
        'perfil_ingreso_html',
        'requisitos_ingreso_html',
        'malla_curricular_html',
        'calendario_html',
        'perfil_egreso_html',
        'requisitos_egreso_html',
        'titulos_certificaciones_html',
    ];

    private const PERSONNEL_GROUPS = [
        'coordinacion_academica' => 'rol',
        'equipos' => 'nombre',
    ];

    private const STRING_ARRAYS = [
        'menciones',
        'orientaciones',
    ];

    private const FALLBACK_USER_HEADER = 'x-flacso-app-user';
    private const FALLBACK_PASSWORD_HEADER = 'x-flacso-app-password';

    public static function init(): void {
        add_action('init', [self::class, 'register_meta'], 12);
        add_action('rest_api_init', [self::class, 'register_rest_routes']);
    }

    public static function register_meta(): void {
        if (!function_exists('register_post_meta')) {
            return;
        }

        foreach (self::HTML_FIELDS as $field) {
            register_post_meta('oferta-academica', $field, [
                'type' => 'string',
                'single' => true,
                'sanitize_callback' => [self::class, 'sanitize_html'],
                'auth_callback' => [self::class, 'user_can_edit_meta'],
                'show_in_rest' => [
                    'schema' => [
                        'description' => sprintf(__('HTML para %s', 'flacso-oferta-academica'), $field),
                        'type' => 'string',
                    ],
                ],
            ]);
        }

        register_post_meta('oferta-academica', 'duracion_meses', [
            'type' => 'string',
            'single' => true,
            'sanitize_callback' => [self::class, 'sanitize_duration'],
            'auth_callback' => [self::class, 'user_can_edit_meta'],
            'show_in_rest' => [
                'schema' => [
                    'description' => __('Duración expresada en meses', 'flacso-oferta-academica'),
                    'type' => 'string',
                ],
            ],
        ]);

        register_post_meta('oferta-academica', 'proximo_inicio', [
            'type' => 'string',
            'single' => true,
            'sanitize_callback' => [self::class, 'sanitize_proximo_inicio'],
            'auth_callback' => [self::class, 'user_can_edit_meta'],
            'show_in_rest' => [
                'schema' => [
                    'description' => __('Fecha del siguiente inicio', 'flacso-oferta-academica'),
                    'type' => 'string',
                ],
            ],
        ]);

        register_post_meta('oferta-academica', 'calendario', [
            'type' => 'string',
            'single' => true,
            'sanitize_callback' => [self::class, 'sanitize_url'],
            'auth_callback' => [self::class, 'user_can_edit_meta'],
            'show_in_rest' => [
                'schema' => [
                    'description' => __('URL PDF de calendario', 'flacso-oferta-academica'),
                    'type' => 'string',
                ],
            ],
        ]);

        register_post_meta('oferta-academica', 'malla_curricular', [
            'type' => 'string',
            'single' => true,
            'sanitize_callback' => [self::class, 'sanitize_url'],
            'auth_callback' => [self::class, 'user_can_edit_meta'],
            'show_in_rest' => [
                'schema' => [
                    'description' => __('URL PDF de malla curricular', 'flacso-oferta-academica'),
                    'type' => 'string',
                ],
            ],
        ]);

        register_post_meta('oferta-academica', 'abreviacion', [
            'type' => 'string',
            'single' => true,
            'sanitize_callback' => [self::class, 'sanitize_abreviacion'],
            'auth_callback' => [self::class, 'user_can_edit_meta'],
            'show_in_rest' => [
                'schema' => [
                    'description' => __('Abreviación del programa', 'flacso-oferta-academica'),
                    'type' => 'string',
                ],
            ],
        ]);

        register_post_meta('oferta-academica', 'correo', [
            'type' => 'string',
            'single' => true,
            'sanitize_callback' => [self::class, 'sanitize_email'],
            'auth_callback' => [self::class, 'user_can_edit_meta'],
            'show_in_rest' => [
                'schema' => [
                    'description' => __('Correo de contacto', 'flacso-oferta-academica'),
                    'type' => 'string',
                ],
            ],
        ]);

        register_post_meta('oferta-academica', 'inscripciones_abiertas', [
            'type' => 'boolean',
            'single' => true,
            'sanitize_callback' => [self::class, 'sanitize_boolean'],
            'auth_callback' => [self::class, 'user_can_edit_meta'],
            'show_in_rest' => [
                'schema' => [
                    'description' => __('Indica si el programa acepta inscripciones abiertas', 'flacso-oferta-academica'),
                    'type' => 'boolean',
                ],
            ],
        ]);

        register_post_meta('oferta-academica', 'proximo_inicio_precision', [
            'type' => 'string',
            'single' => true,
            'sanitize_callback' => [self::class, 'sanitize_precision'],
            'auth_callback' => [self::class, 'user_can_edit_meta'],
            'show_in_rest' => [
                'schema' => [
                    'description' => __('Precisión para el próximo inicio ("day", "month" o "year")', 'flacso-oferta-academica'),
                    'type' => 'string',
                ],
            ],
        ]);

        foreach (self::PERSONNEL_GROUPS as $key => $label) {
            register_post_meta('oferta-academica', $key, [
                'type' => 'array',
                'single' => true,
                'sanitize_callback' => function ($value) use ($label) {
                    return self::sanitize_personnel_groups($value, $label);
                },
                'auth_callback' => [self::class, 'user_can_edit_meta'],
                'show_in_rest' => [
                    'schema' => [
                        'description' => sprintf(__('Listado de %s', 'flacso-oferta-academica'), $key),
                        'type' => 'array',
                        'items' => [
                            'type' => 'object',
                            'properties' => [
                                $label => [
                                    'type' => 'string',
                                ],
                                'docentes' => [
                                    'type' => 'array',
                                    'items' => [
                                        'type' => 'integer',
                                    ],
                                ],
                            ],
                        ],
                    ],
                ],
            ]);
        }

        foreach (self::STRING_ARRAYS as $key) {
            register_post_meta('oferta-academica', $key, [
                'type' => 'array',
                'single' => true,
                'sanitize_callback' => [self::class, 'sanitize_string_array'],
                'auth_callback' => [self::class, 'user_can_edit_meta'],
                'show_in_rest' => [
                    'schema' => [
                        'description' => ucfirst(str_replace('_', ' ', $key)),
                        'type' => 'array',
                        'items' => ['type' => 'string'],
                    ],
                ],
            ]);
        }
    }

    public static function sanitize_html($value): string {
        return wp_kses_post((string) $value);
    }

    public static function sanitize_duration($value): string {
        return preg_replace('/[^0-9]/', '', (string) $value);
    }

    public static function sanitize_proximo_inicio($value): string {
        return trim((string) $value);
    }

    public static function sanitize_url($value): string {
        return esc_url_raw(trim((string) $value));
    }

    public static function sanitize_precision($value): string {
        $value = strtolower(trim((string) $value));
        if (in_array($value, ['day', 'month', 'year'], true)) {
            return $value;
        }
        return '';
    }

    public static function sanitize_abreviacion($value): string {
        return sanitize_text_field((string) $value);
    }

    public static function sanitize_email($value): string {
        return sanitize_email((string) $value);
    }

    public static function sanitize_boolean($value): bool {
        return filter_var($value, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE) ?? false;
    }

    public static function sanitize_string_array($value): array {
        if (!is_array($value)) {
            return [];
        }
        $out = [];
        foreach ($value as $item) {
            $item = trim((string) $item);
            if ($item === '') {
                continue;
            }
            $out[] = sanitize_text_field($item);
        }
        return array_values(array_unique($out));
    }

    private static function sanitize_personnel_groups($value, string $name_key): array {
        if (!is_array($value)) {
            return [];
        }
        $out = [];
        foreach ($value as $item) {
            if (!is_array($item)) {
                continue;
            }
            $label = isset($item[$name_key]) ? sanitize_text_field($item[$name_key]) : '';
            if ($label === '') {
                continue;
            }
            $docentes = [];
            if (isset($item['docentes']) && is_array($item['docentes'])) {
                foreach ($item['docentes'] as $docente) {
                    $docente_id = intval($docente);
                    if ($docente_id <= 0) {
                        continue;
                    }
                    $docentes[] = $docente_id;
                }
            }
            $docentes = array_values(array_unique($docentes));
            $out[] = [$name_key => $label, 'docentes' => $docentes];
        }
        return $out;
    }

    public static function sanitize_personnel_groups_data($value, string $name_key): array {
        return self::sanitize_personnel_groups($value, $name_key);
    }

    public static function user_can_edit_meta($allowed, $meta_key, $post_id, $user_id = null): bool {
        return current_user_can('edit_post', $post_id);
    }

    public static function register_rest_routes(): void {
        register_rest_route('flacso/v1', '/oferta-academica', [
            'methods' => \WP_REST_Server::READABLE,
            'callback' => [self::class, 'rest_list_ofertas'],
            'permission_callback' => '__return_true',
            'args' => [
                'tipo' => [
                    'validate_callback' => fn($value) => is_string($value),
                ],
            ],
        ]);

        register_rest_route('flacso/v1', '/oferta-academica/(?P<id>\d+)', [
            'methods' => \WP_REST_Server::READABLE,
            'callback' => [self::class, 'rest_get_oferta'],
            'permission_callback' => '__return_true',
            'args' => [
                'id' => [
                    'validate_callback' => fn($value) => is_numeric($value),
                ],
            ],
        ]);

        register_rest_route('flacso/v1', '/oferta-academica/(?P<id>\d+)', [
            'methods' => \WP_REST_Server::EDITABLE,
            'callback' => [self::class, 'rest_update_oferta'],
            'permission_callback' => [self::class, 'rest_update_permission'],
            'args' => [
                'id' => [
                    'validate_callback' => fn($value) => is_numeric($value),
                ],
            ],
        ]);
    }

    public static function rest_get_oferta(\WP_REST_Request $request) {
        $post_id = (int) $request['id'];
        $post = get_post($post_id);
        if (!$post || $post->post_type !== 'oferta-academica') {
            return new WP_Error('oferta_not_found', __('La oferta académica no existe.', 'flacso-oferta-academica'), ['status' => 404]);
        }
        $data = self::get_schema($post_id);
        return rest_ensure_response($data);
    }

    public static function rest_list_ofertas(\WP_REST_Request $request) {
        $args = [
            'post_type' => 'oferta-academica',
            'post_status' => 'publish',
            'posts_per_page' => -1,
            'orderby' => 'title',
            'order' => 'ASC',
        ];

        if ($tipo = $request->get_param('tipo')) {
            $args['tax_query'] = [[
                'taxonomy' => 'tipo-oferta-academica',
                'field' => 'slug',
                'terms' => sanitize_text_field($tipo),
            ]];
        }

        $query = new WP_Query($args);
        $data = [];
        foreach ($query->posts as $post) {
            $data[] = self::get_schema($post->ID);
        }
        wp_reset_postdata();

        return rest_ensure_response($data);
    }

    public static function get_schema(int $post_id): array {
        $post = get_post($post_id);
        if (!$post) {
            return [];
        }
        $schema = [
            'id' => $post->ID,
            'titulo' => get_the_title($post),
            'duracion_meses' => self::get_meta_value($post_id, 'duracion_meses'),
            'proximo_inicio' => self::build_proximo_inicio($post_id),
            'calendario' => self::get_meta_value($post_id, 'calendario'),
            'malla_curricular' => self::get_meta_value($post_id, 'malla_curricular'),
            'abreviacion' => self::get_meta_value($post_id, 'abreviacion'),
            'correo' => self::get_meta_value($post_id, 'correo'),
            'inscripciones_abiertas' => self::get_meta_boolean($post_id, 'inscripciones_abiertas'),
        ];

        foreach (self::HTML_FIELDS as $field) {
            $schema[$field] = self::get_meta_value($post_id, $field);
        }

        foreach (self::PERSONNEL_GROUPS as $key => $label) {
            $schema[$key] = self::normalize_personnel_data(get_post_meta($post_id, $key, true), $label);
        }

        foreach (self::STRING_ARRAYS as $key) {
            $schema[$key] = self::normalize_string_array(get_post_meta($post_id, $key, true));
        }

        return $schema;
    }

    public static function rest_update_oferta(\WP_REST_Request $request) {
        $post_id = (int) $request['id'];
        $post = get_post($post_id);
        if (!$post || $post->post_type !== 'oferta-academica') {
            return new WP_Error('oferta_not_found', __('La oferta académica no existe.', 'flacso-oferta-academica'), ['status' => 404]);
        }

        self::maybe_authenticate_request_with_application_password($request);

        $data = $request->get_json_params();
        if (!is_array($data) || empty($data)) {
            $data = $request->get_body_params();
        }
        if (!is_array($data)) {
            $data = [];
        }

        $post_update = ['ID' => $post_id];
        if (isset($data['titulo'])) {
            $post_update['post_title'] = sanitize_text_field($data['titulo']);
        }

        if (count($post_update) > 1) {
            wp_update_post($post_update);
        }

        if (isset($data['proximo_inicio']) && is_array($data['proximo_inicio'])) {
            $proximo_inicio_data = $data['proximo_inicio'];

            if (array_key_exists('valor', $proximo_inicio_data)) {
                $data['proximo_inicio'] = $proximo_inicio_data['valor'];
            } else {
                unset($data['proximo_inicio']);
            }

            if (!array_key_exists('proximo_inicio_precision', $data) && array_key_exists('precision', $proximo_inicio_data)) {
                $data['proximo_inicio_precision'] = $proximo_inicio_data['precision'];
            }
        }

        $meta_map = [
            'duracion_meses' => fn($value) => self::sanitize_duration($value),
            'proximo_inicio' => fn($value) => self::sanitize_proximo_inicio($value),
            'proximo_inicio_precision' => fn($value) => self::sanitize_precision($value),
            'calendario' => fn($value) => self::sanitize_url($value),
            'malla_curricular' => fn($value) => self::sanitize_url($value),
            'abreviacion' => fn($value) => self::sanitize_abreviacion($value),
            'correo' => fn($value) => self::sanitize_email($value),
            'inscripciones_abiertas' => fn($value) => self::sanitize_boolean($value),
        ];

        foreach ($meta_map as $key => $sanitizer) {
            if (array_key_exists($key, $data)) {
                self::update_meta_value($post_id, $key, $sanitizer($data[$key]));
            }
        }

        foreach (self::HTML_FIELDS as $field) {
            if (!array_key_exists($field, $data)) {
                continue;
            }
            self::update_meta_value($post_id, $field, self::sanitize_html($data[$field]));
        }

        foreach (self::STRING_ARRAYS as $key) {
            if (!array_key_exists($key, $data)) {
                continue;
            }
            $value = is_array($data[$key]) ? $data[$key] : preg_split('/\r?\n/', strval($data[$key]));
            self::update_meta_value($post_id, $key, self::sanitize_string_array($value));
        }

        foreach (self::PERSONNEL_GROUPS as $key => $label_key) {
            if (!array_key_exists($key, $data)) {
                continue;
            }
            $value = $data[$key];
            if (!is_array($value)) {
                continue;
            }
            $sanitized = self::sanitize_personnel_groups($value, $label_key);
            self::update_meta_value($post_id, $key, $sanitized);
        }

        return rest_ensure_response(self::get_schema($post_id));
    }

    public static function rest_update_permission(\WP_REST_Request $request): bool {
        self::maybe_authenticate_request_with_application_password($request);
        $post_id = (int) $request['id'];
        if ($post_id <= 0) {
            return false;
        }
        return current_user_can('edit_post', $post_id);
    }

    private static function maybe_authenticate_request_with_application_password(\WP_REST_Request $request): void {
        if (is_user_logged_in()) {
            return;
        }

        if (!function_exists('wp_authenticate_application_password')) {
            return;
        }

        $username = '';
        $app_password = '';

        $fallback_username = trim((string) $request->get_header(self::FALLBACK_USER_HEADER));
        $fallback_password = trim((string) $request->get_header(self::FALLBACK_PASSWORD_HEADER));
        if ($fallback_username !== '' && $fallback_password !== '') {
            $username = $fallback_username;
            $app_password = $fallback_password;
        }

        if ($username === '' || $app_password === '') {
            $authorization = trim((string) $request->get_header('authorization'));

            if ($authorization === '' && !empty($_SERVER['REDIRECT_HTTP_AUTHORIZATION']) && is_string($_SERVER['REDIRECT_HTTP_AUTHORIZATION'])) {
                $authorization = trim($_SERVER['REDIRECT_HTTP_AUTHORIZATION']);
            }

            if (stripos($authorization, 'basic ') === 0) {
                $encoded = substr($authorization, 6);
                $decoded = base64_decode($encoded, true);
                if (is_string($decoded) && strpos($decoded, ':') !== false) {
                    [$username, $app_password] = explode(':', $decoded, 2);
                    $username = trim($username);
                    $app_password = trim($app_password);
                }
            }
        }

        if ($username === '' || $app_password === '') {
            return;
        }

        $user = wp_authenticate_application_password(null, $username, $app_password);
        if ($user instanceof \WP_User) {
            wp_set_current_user((int) $user->ID);
        }
    }

    private static function update_meta_value(int $post_id, string $key, $value): void {
        if (is_bool($value)) {
            update_post_meta($post_id, $key, $value ? 1 : 0);
            return;
        }

        if (is_array($value)) {
            if (empty($value)) {
                delete_post_meta($post_id, $key);
                return;
            }
            update_post_meta($post_id, $key, $value);
            return;
        }

        $value = (string) $value;
        if ($value === '') {
            delete_post_meta($post_id, $key);
            return;
        }

        update_post_meta($post_id, $key, $value);
    }

    private static function get_meta_value(int $post_id, string $key): string {
        $value = get_post_meta($post_id, $key, true);
        if ($value === null) {
            return '';
        }
        if (is_array($value) || is_object($value)) {
            return '';
        }
        return (string) $value;
    }

    private static function get_meta_boolean(int $post_id, string $key): bool {
        $value = get_post_meta($post_id, $key, true);
        if ($value === '' || $value === null) {
            return false;
        }
        return filter_var($value, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE) ?? false;
    }

    private static function build_proximo_inicio(int $post_id): array {
        $valor = self::get_meta_value($post_id, 'proximo_inicio');
        $precision = get_post_meta($post_id, 'proximo_inicio_precision', true);
        $precision = self::detect_precision($valor, $precision);
        return [
            'valor' => $valor,
            'precision' => $precision,
        ];
    }

    private static function detect_precision(string $valor, $stored = null): string {
        if ($stored && in_array($stored, ['day', 'month', 'year'], true)) {
            return $stored;
        }
        if ($valor === '') {
            return 'year';
        }
        if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $valor)) {
            return 'day';
        }
        if (preg_match('/^\d{4}-\d{2}$/', $valor)) {
            return 'month';
        }
        if (preg_match('/^\d{4}$/', $valor)) {
            return 'year';
        }
        return 'year';
    }

    private static function normalize_personnel_data($value, string $name_key): array {
        if (!is_array($value)) {
            return [];
        }
        return self::sanitize_personnel_groups($value, $name_key);
    }

    private static function normalize_string_array($value): array {
        if (!is_array($value)) {
            return [];
        }
        return self::sanitize_string_array($value);
    }
}
