<?php

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Estructura de datos para la Oferta Académica (sin renderizado).
 */
class Oferta_Data_Schema {
    private const HTML_FIELDS = [
        'descripcion_html',
        'modalidad_html',
        'duracion_html',
        'objetivos_html',
        'perfil_ingreso_html',
        'requisitos_ingreso_html',
        'perfil_egreso_html',
        'requisitos_egreso_html',
        'financiacion_html',
        'titulos_certificaciones_html',
        'acreditaciones_html',
        'carta_presentacion_html',
        'precios_nota',
    ];

    private const PERSONNEL_GROUPS = [
        'coordinacion_academica' => 'rol',
        'equipos' => 'nombre',
    ];

    private const STRING_ARRAYS = [
        'menciones',
        'orientaciones',
    ];

    private const TEXT_FIELDS = [
        'tabla_precios_tipo',
        'modalidad_resumen',
        'carta_cta_titulo',
        'asistente_academica_rol',
    ];

    private const EMAIL_FIELDS = [
        'asistente_academica_correo',
    ];

    private const BOOLEAN_FIELDS = [
        'reconocido_mec',
        'reconocimiento_internacional',
        'mostrar_expedicion_titulo',
        'convenio_iin_oea',
        'mostrar_costos_envio',
        'visibilidad_carta',
    ];

    private const INTEGER_FIELDS = [
        'asistente_academica_docente_id',
        'tabla_precio_id',
    ];

    private const JSON_STRING_FIELDS = [
        'precios_filas',
        'documentos',
    ];

    private const INTEGER_ARRAYS = [
        'titulos_intermedios',
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

        register_post_meta('oferta-academica', 'calendario_modo', [
            'type' => 'string',
            'single' => true,
            'sanitize_callback' => 'sanitize_text_field',
            'auth_callback' => [self::class, 'user_can_edit_meta'],
            'show_in_rest' => [
                'schema' => [
                    'description' => __('Modo de calendario (pdf o html)', 'flacso-oferta-academica'),
                    'type' => 'string',
                ],
            ],
        ]);

        register_post_meta('oferta-academica', 'malla_curricular_modo', [
            'type' => 'string',
            'single' => true,
            'sanitize_callback' => 'sanitize_text_field',
            'auth_callback' => [self::class, 'user_can_edit_meta'],
            'show_in_rest' => [
                'schema' => [
                    'description' => __('Modo de malla curricular (pdf o html)', 'flacso-oferta-academica'),
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
            'sanitize_callback' => [self::class, 'sanitize_meta_boolean'],
            'auth_callback' => [self::class, 'user_can_edit_meta'],
            'show_in_rest' => [
                'schema' => [
                    'description' => __('Indica si el programa acepta inscripciones abiertas', 'flacso-oferta-academica'),
                    'type' => 'boolean',
                ],
            ],
        ]);

        register_post_meta('oferta-academica', 'inscripciones_mensaje', [
            'type' => 'string',
            'single' => true,
            'sanitize_callback' => 'sanitize_text_field',
            'auth_callback' => [self::class, 'user_can_edit_meta'],
            'show_in_rest' => [
                'schema' => [
                    'description' => __('Mensaje personalizado para inscripciones abiertas', 'flacso-oferta-academica'),
                    'type' => 'string',
                ],
            ],
        ]);

        register_post_meta('oferta-academica', 'inscripciones_mensaje_cerrado', [
            'type' => 'string',
            'single' => true,
            'sanitize_callback' => 'sanitize_text_field',
            'auth_callback' => [self::class, 'user_can_edit_meta'],
            'show_in_rest' => [
                'schema' => [
                    'description' => __('Mensaje personalizado para inscripciones cerradas', 'flacso-oferta-academica'),
                    'type' => 'string',
                ],
            ],
        ]);

        register_post_meta('oferta-academica', 'cohorte', [
            'type' => 'string',
            'single' => true,
            'sanitize_callback' => 'sanitize_text_field',
            'auth_callback' => [self::class, 'user_can_edit_meta'],
            'show_in_rest' => [
                'schema' => [
                    'description' => __('Cohorte que va a iniciar', 'flacso-oferta-academica'),
                    'type' => 'string',
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
                                'descripcion' => [
                                    'type' => 'string',
                                ],
                                'importancia' => [
                                    'type' => 'string',
                                ],
                                'docentes' => [
                                    'type' => 'array',
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

        foreach (self::TEXT_FIELDS as $field) {
            register_post_meta('oferta-academica', $field, [
                'type' => 'string',
                'single' => true,
                'sanitize_callback' => 'sanitize_text_field',
                'auth_callback' => [self::class, 'user_can_edit_meta'],
                'show_in_rest' => [
                    'schema' => [
                        'description' => ucfirst(str_replace('_', ' ', $field)),
                        'type' => 'string',
                    ],
                ],
            ]);
        }

        foreach (self::EMAIL_FIELDS as $field) {
            register_post_meta('oferta-academica', $field, [
                'type' => 'string',
                'single' => true,
                'sanitize_callback' => [self::class, 'sanitize_email'],
                'auth_callback' => [self::class, 'user_can_edit_meta'],
                'show_in_rest' => [
                    'schema' => [
                        'description' => ucfirst(str_replace('_', ' ', $field)),
                        'type' => 'string',
                    ],
                ],
            ]);
        }

        foreach (self::BOOLEAN_FIELDS as $field) {
            register_post_meta('oferta-academica', $field, [
                'type' => 'boolean',
                'single' => true,
                'sanitize_callback' => [self::class, 'sanitize_meta_boolean'],
                'auth_callback' => [self::class, 'user_can_edit_meta'],
                'show_in_rest' => [
                    'schema' => [
                        'description' => ucfirst(str_replace('_', ' ', $field)),
                        'type' => 'boolean',
                    ],
                ],
            ]);
        }

        foreach (self::INTEGER_FIELDS as $field) {
            register_post_meta('oferta-academica', $field, [
                'type' => 'integer',
                'single' => true,
                'sanitize_callback' => [self::class, 'sanitize_integer'],
                'auth_callback' => [self::class, 'user_can_edit_meta'],
                'show_in_rest' => [
                    'schema' => [
                        'description' => ucfirst(str_replace('_', ' ', $field)),
                        'type' => 'integer',
                    ],
                ],
            ]);
        }

        foreach (self::JSON_STRING_FIELDS as $field) {
            $sanitize_callback = $field === 'precios_filas' 
                ? [self::class, 'sanitize_prices_rows'] 
                : [self::class, 'sanitize_generic_json_string'];
                
            register_post_meta('oferta-academica', $field, [
                'type' => 'string',
                'single' => true,
                'sanitize_callback' => $sanitize_callback,
                'auth_callback' => [self::class, 'user_can_edit_meta'],
                'show_in_rest' => [
                    'schema' => [
                        'description' => ucfirst(str_replace('_', ' ', $field)),
                        'type' => 'string',
                    ],
                ],
            ]);
        }

        foreach (self::INTEGER_ARRAYS as $field) {
            register_post_meta('oferta-academica', $field, [
                'type' => 'array',
                'single' => true,
                'sanitize_callback' => [self::class, 'sanitize_integer_array'],
                'auth_callback' => [self::class, 'user_can_edit_meta'],
                'show_in_rest' => [
                    'schema' => [
                        'description' => ucfirst(str_replace('_', ' ', $field)),
                        'type' => 'array',
                        'items' => ['type' => 'integer'],
                    ],
                ],
            ]);
        }
    }

    public static function sanitize_html($value): string {
        $allowed = wp_kses_allowed_html('post');

        if (!isset($allowed['p'])) {
            $allowed['p'] = [];
        }

        if (!isset($allowed['br'])) {
            $allowed['br'] = [];
        }

        if (!isset($allowed['small'])) {
            $allowed['small'] = [];
        }

        return wp_kses((string) $value, $allowed);
    }

    public static function sanitize_duration($value): string {
        return preg_replace('/[^0-9.,]/', '', (string) $value);
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

    public static function sanitize_integer($value): int {
        return max(0, intval($value));
    }

    public static function sanitize_boolean($value): bool {
        return filter_var($value, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE) ?? false;
    }

    public static function sanitize_meta_boolean($value): string {
        return self::sanitize_boolean($value) ? '1' : '0';
    }

    public static function resolve_inscripciones_year($proximo_inicio, $cohorte = ''): string {
        $year = '';

        if (is_array($proximo_inicio)) {
            $year = self::extract_year_from_value(
                (string) ($proximo_inicio['valor'] ?? ''),
                (string) ($proximo_inicio['precision'] ?? '')
            );
        } else {
            $year = self::extract_year_from_value((string) $proximo_inicio);
        }

        if ($year !== '') {
            return $year;
        }

        return self::extract_year_from_value((string) $cohorte);
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

    public static function sanitize_integer_array($value): array {
        if (!is_array($value)) {
            $value = preg_split('/[\s,|]+/', trim((string) $value), -1, PREG_SPLIT_NO_EMPTY);
        }

        if (!is_array($value)) {
            return [];
        }

        $out = [];

        foreach ($value as $item) {
            $id = intval($item);
            if ($id > 0) {
                $out[] = $id;
            }
        }

        return array_values(array_unique($out));
    }

    public static function sanitize_prices_rows($value): string {
        if (is_string($value)) {
            $value = trim($value);

            if ($value === '') {
                return '';
            }

            $decoded = json_decode(wp_unslash($value), true);
            if (!is_array($decoded)) {
                return '';
            }

            $value = $decoded;
        }

        if (!is_array($value)) {
            return '';
        }

        $rows = [];

        foreach ($value as $row) {
            if (!is_array($row)) {
                continue;
            }

            $sanitized_row = [
                'concept' => self::sanitize_html($row['concept'] ?? ''),
                'uy' => self::sanitize_html($row['uy'] ?? ''),
                'us' => self::sanitize_html($row['us'] ?? ''),
                'highlight' => self::sanitize_boolean($row['highlight'] ?? false),
            ];

            if (
                $sanitized_row['concept'] === ''
                && $sanitized_row['uy'] === ''
                && $sanitized_row['us'] === ''
                && $sanitized_row['highlight'] === false
            ) {
                continue;
            }

            $rows[] = $sanitized_row;
        }

        if (empty($rows)) {
            return '';
        }

        return wp_json_encode($rows, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }

    public static function sanitize_generic_json_string($value): string {
        if (is_string($value)) {
            $value = trim($value);
            if ($value === '') return '';
            $decoded = json_decode(wp_unslash($value), true);
            if (!is_array($decoded)) return '';
            return wp_json_encode($decoded, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        }
        if (is_array($value)) {
            return wp_json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        }
        return '';
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
            $descripcion = isset($item['descripcion']) ? self::sanitize_html($item['descripcion']) : '';
            $importancia = isset($item['importancia']) ? sanitize_text_field($item['importancia']) : '3';
            $docentes = [];
            if (isset($item['docentes']) && is_array($item['docentes'])) {
                foreach ($item['docentes'] as $docente) {
                    if (is_array($docente)) {
                        $docente_id = isset($docente['id']) ? intval($docente['id']) : 0;
                        $rol = isset($docente['rol']) ? sanitize_text_field($docente['rol']) : '';
                        if ($docente_id > 0) {
                            $docentes[] = ['id' => $docente_id, 'rol' => $rol];
                        }
                    } else {
                        $docente_id = intval($docente);
                        if ($docente_id > 0) {
                            $docentes[] = $docente_id;
                        }
                    }
                }
            }
            if ($label === '' && $descripcion === '' && empty($docentes)) {
                continue;
            }
            $out[] = [$name_key => $label, 'descripcion' => $descripcion, 'importancia' => $importancia, 'docentes' => $docentes];
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

        register_rest_route('flacso/v1', '/oferta-academica/taxonomies', [
            'methods' => \WP_REST_Server::READABLE,
            'callback' => [self::class, 'rest_get_taxonomies'],
            'permission_callback' => '__return_true',
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

    public static function rest_get_taxonomies() {
        $response = [
            'tipo-oferta-academica' => [],
            'area_tematica'         => [],
        ];

        foreach (['tipo-oferta-academica', 'area_tematica'] as $tax) {
            $terms = get_terms([
                'taxonomy'   => $tax,
                'hide_empty' => false,
            ]);

            if (!is_wp_error($terms) && is_array($terms)) {
                foreach ($terms as $t) {
                    $response[$tax][] = [
                        'id'   => (int) $t->term_id,
                        'name' => $t->name,
                        'slug' => $t->slug,
                    ];
                }
            }
        }

        return rest_ensure_response($response);
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
            'inscripciones_mensaje' => self::get_meta_value($post_id, 'inscripciones_mensaje'),
            'inscripciones_mensaje_cerrado' => self::get_meta_value($post_id, 'inscripciones_mensaje_cerrado'),
            'cohorte' => self::get_meta_value($post_id, 'cohorte'),
        ];

        foreach (self::HTML_FIELDS as $field) {
            if ($field === 'financiacion_html') {
                $schema[$field] = get_option('flacso_financiacion_html', '');
            } else {
                $schema[$field] = self::get_meta_value($post_id, $field);
            }
        }

        foreach (self::PERSONNEL_GROUPS as $key => $label) {
            $schema[$key] = self::normalize_personnel_data(get_post_meta($post_id, $key, true), $label);
        }

        foreach (self::STRING_ARRAYS as $key) {
            $schema[$key] = self::normalize_string_array(get_post_meta($post_id, $key, true));
        }

        foreach (self::INTEGER_ARRAYS as $key) {
            $schema[$key] = self::normalize_integer_array(get_post_meta($post_id, $key, true));
        }

        foreach (self::TEXT_FIELDS as $field) {
            $schema[$field] = self::get_meta_value($post_id, $field);
        }

        foreach (self::EMAIL_FIELDS as $field) {
            $schema[$field] = self::get_meta_value($post_id, $field);
        }

        foreach (self::BOOLEAN_FIELDS as $field) {
            if (metadata_exists('post', $post_id, $field)) {
                $schema[$field] = get_post_meta($post_id, $field, true) ? '1' : '0';
            } else {
                $schema[$field] = '';
            }
        }

        $schema['visibilidad_carta'] = !empty($schema['inscripciones_abiertas']) ? '1' : '0';

        foreach (self::INTEGER_FIELDS as $field) {
            $schema[$field] = self::get_meta_int($post_id, $field);
        }

        foreach (self::JSON_STRING_FIELDS as $field) {
            $schema[$field] = self::get_meta_value($post_id, $field);
        }

        $schema['tabla_precio'] = null;

        $tabla_precio_id = isset($schema['tabla_precio_id']) ? intval($schema['tabla_precio_id']) : 0;
        if ($tabla_precio_id > 0 && class_exists('Tabla_Precio_Schema')) {
            $tabla_precio = Tabla_Precio_Schema::get_table_data($tabla_precio_id);

            if (!empty($tabla_precio)) {
                $schema['tabla_precio'] = $tabla_precio;
                $schema['tabla_precios_tipo'] = $tabla_precio['tabla_precios_tipo'] ?? '';
                $schema['precios_filas'] = $tabla_precio['precios_filas'] ?? '';
                $schema['precios_nota'] = $tabla_precio['precios_nota'] ?? '';
            }
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

        if (isset($data['meta']) && is_array($data['meta'])) {
            $data = array_merge($data['meta'], $data);
            unset($data['meta']);
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
            'calendario_modo' => fn($value) => sanitize_text_field($value),
            'malla_curricular_modo' => fn($value) => sanitize_text_field($value),
            'abreviacion' => fn($value) => self::sanitize_abreviacion($value),
            'correo' => fn($value) => self::sanitize_email($value),
            'inscripciones_abiertas' => fn($value) => self::sanitize_meta_boolean($value),
            'inscripciones_mensaje' => fn($value) => sanitize_textarea_field($value),
            'inscripciones_mensaje_cerrado' => fn($value) => sanitize_text_field($value),
            'cohorte' => fn($value) => sanitize_text_field($value),
            'tabla_precios_tipo' => fn($value) => sanitize_text_field($value),
            'modalidad_resumen' => fn($value) => sanitize_text_field($value),
            'carta_cta_titulo' => fn($value) => sanitize_text_field($value),
            'asistente_academica_rol' => fn($value) => sanitize_text_field($value),
            'asistente_academica_correo' => fn($value) => self::sanitize_email($value),
            'asistente_academica_docente_id' => fn($value) => self::sanitize_integer($value),
            'tabla_precio_id' => fn($value) => self::sanitize_integer($value),
            'precios_filas' => fn($value) => self::sanitize_prices_rows($value),
            'documentos' => fn($value) => self::sanitize_generic_json_string($value),
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

        foreach (self::INTEGER_ARRAYS as $key) {
            if (!array_key_exists($key, $data)) {
                continue;
            }
            self::update_meta_value($post_id, $key, self::sanitize_integer_array($data[$key]));
        }

        foreach (self::BOOLEAN_FIELDS as $key) {
            if (isset($data[$key])) {
                self::update_meta_value($post_id, $key, self::sanitize_meta_boolean($data[$key]));
            }
        }

        if (array_key_exists('inscripciones_abiertas', $data)) {
            self::update_meta_value(
                $post_id,
                'visibilidad_carta',
                self::sanitize_meta_boolean($data['inscripciones_abiertas'])
            );
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

    private static function get_meta_int(int $post_id, string $key): int {
        $value = get_post_meta($post_id, $key, true);
        if ($value === '' || $value === null) {
            return 0;
        }
        return max(0, intval($value));
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
        if (preg_match('/^\d{4}[-\/]\d{1,2}[-\/]\d{1,2}$/', $valor) || preg_match('/^\d{1,2}[-\/]\d{1,2}[-\/]\d{4}$/', $valor)) {
            return 'day';
        }
        if (preg_match('/^\d{4}[-\/]\d{1,2}$/', $valor) || preg_match('/^\d{1,2}[-\/]\d{4}$/', $valor)) {
            return 'month';
        }
        if (preg_match('/^\d{4}$/', $valor)) {
            return 'year';
        }
        return 'year';
    }

    private static function extract_year_from_value(string $value, string $precision = ''): string {
        $value = trim($value);
        $precision = self::sanitize_precision($precision);

        if ($value === '') {
            return '';
        }

        if ($precision === 'year' && preg_match('/^\d{4}$/', $value, $matches)) {
            return $matches[0];
        }

        if (preg_match('/^(\d{4})[-\/]\d{1,2}(?:[-\/]\d{1,2})?$/', $value, $matches)) {
            return $matches[1];
        }

        if (preg_match('/^\d{1,2}[-\/](\d{4})$/', $value, $matches)) {
            return $matches[1];
        }

        if (preg_match('/^\d{1,2}[-\/]\d{1,2}[-\/](\d{4})$/', $value, $matches)) {
            return $matches[1];
        }

        if (preg_match('/\b(\d{4})\b/', $value, $matches)) {
            return $matches[1];
        }

        return '';
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

    private static function normalize_integer_array($value): array {
        if (!is_array($value)) {
            return [];
        }
        return self::sanitize_integer_array($value);
    }
}
