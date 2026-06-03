<?php
if (!defined('ABSPATH')) {
    exit;
}

class Oferta_Rest_API
{
    public static function init()
    {
        add_action('rest_api_init', [self::class, 'register_meta_fields']);
        add_action('rest_api_init', [self::class, 'register_settings_routes']);
    }

    public static function register_settings_routes()
    {
        register_rest_route('flacso/v1', '/ofertas/settings', [
            [
                'methods' => WP_REST_Server::READABLE,
                'callback' => [self::class, 'get_settings'],
                'permission_callback' => [self::class, 'can_manage_settings']
            ],
            [
                'methods' => WP_REST_Server::EDITABLE,
                'callback' => [self::class, 'update_settings'],
                'permission_callback' => [self::class, 'can_manage_settings']
            ]
        ]);
    }

    public static function can_manage_settings()
    {
        return current_user_can('manage_options');
    }

    public static function get_settings()
    {
        $token = get_option('flacso_webhook_token', '');
        $masked_token = !empty($token) ? '********' : '';

        return rest_ensure_response([
            'ok' => true,
            'data' => [
                'financiacion_html' => get_option('flacso_financiacion_html', ''),
                'inscripciones_mensaje_abierto_default' => get_option('flacso_inscripciones_mensaje_abierto_default', 'Descuentos especiales disponibles. Solicitá información e inscribite hoy.'),
                'inscripciones_mensaje_cerrado_default' => get_option('flacso_inscripciones_mensaje_cerrado_default', 'Mantente atento a nuestras próximas aperturas.'),
                'mensaje_bienvenida' => get_option('flacso_mensaje_bienvenida', ''),
                'flacso_webhook_token' => $masked_token,
                'flacso_google_drive_folder_id' => get_option('flacso_google_drive_folder_id', ''),
                'correos_excluidos' => get_option('flacso_correos_excluidos', '')
            ]
        ]);
    }

    public static function update_settings(WP_REST_Request $request)
    {
        $payload = $request->get_json_params();
        if (isset($payload['financiacion_html'])) {
            update_option('flacso_financiacion_html', wp_kses_post($payload['financiacion_html']));
        }
        if (isset($payload['inscripciones_mensaje_abierto_default'])) {
            update_option('flacso_inscripciones_mensaje_abierto_default', sanitize_text_field($payload['inscripciones_mensaje_abierto_default']));
        }
        if (isset($payload['inscripciones_mensaje_cerrado_default'])) {
            update_option('flacso_inscripciones_mensaje_cerrado_default', sanitize_text_field($payload['inscripciones_mensaje_cerrado_default']));
        }
        if (isset($payload['mensaje_bienvenida'])) {
            update_option('flacso_mensaje_bienvenida', wp_kses_post($payload['mensaje_bienvenida']));
        }
        if (isset($payload['flacso_webhook_token'])) {
            $new_token = sanitize_text_field($payload['flacso_webhook_token']);
            if ($new_token !== '********') {
                update_option('flacso_webhook_token', $new_token);
            }
        }
        if (isset($payload['flacso_google_drive_folder_id'])) {
            update_option('flacso_google_drive_folder_id', sanitize_text_field($payload['flacso_google_drive_folder_id']));
        }
        if (isset($payload['correos_excluidos'])) {
            update_option('flacso_correos_excluidos', sanitize_textarea_field($payload['correos_excluidos']));
        }

        return self::get_settings();
    }

    /**
     * Registra todos los campos personalizados en el endpoint estándar de WordPress:
     * wp-json/wp/v2/oferta-academica
     */
    public static function register_meta_fields()
    {
        $meta_keys = [
            'abreviacion', 'correo', 'duracion_meses', 'proximo_inicio', 
            'calendario', 'malla_curricular', 'proximo_inicio_precision', 
            'inscripciones_abiertas', 'modalidad_html', 'duracion_html', 
            'objetivos_html', 'perfil_ingreso_html', 'requisitos_ingreso_html', 
            'malla_curricular_html', 'calendario_html', 'perfil_egreso_html', 
            'requisitos_egreso_html', 'titulos_certificaciones_html', 'financiacion_html',
            'acreditaciones_html',
            'menciones', 'orientaciones', 'coordinacion_academica', 'equipos',
            'inscripciones_mensaje_abierto_default', 'inscripciones_mensaje_cerrado_default',
            'reconocido_mec', 'reconocimiento_internacional', 'mostrar_expedicion_titulo',
            'tabla_precios_tipo', 'carta_presentacion_html', 'precios_filas', 'precios_nota',
            'titulos_intermedios', 'convenio_iin_oea', 'mostrar_costos_envio', 'modalidad_resumen',
            'asistente_academica_docente_id', 'asistente_academica_rol',
            'asistente_academica_correo'
        ];

        foreach ($meta_keys as $key) {
            register_rest_field('oferta-academica', $key, [
                'get_callback' => function ($post_array) use ($key) {
                    if ($key === 'financiacion_html') {
                        return get_option('flacso_financiacion_html', '');
                    }
                    if ($key === 'inscripciones_mensaje_abierto_default') {
                        return get_option('flacso_inscripciones_mensaje_abierto_default', 'Descuentos especiales disponibles. Solicitá información e inscribite hoy.');
                    }
                    if ($key === 'inscripciones_mensaje_cerrado_default') {
                        return get_option('flacso_inscripciones_mensaje_cerrado_default', 'Mantente atento a nuestras próximas aperturas.');
                    }
                    return get_post_meta($post_array['id'], $key, true);
                },
                'update_callback' => function ($value, $post_obj) use ($key) {
                    if ($key === 'financiacion_html') {
                        return update_option('flacso_financiacion_html', $value);
                    }
                    if ($key === 'inscripciones_mensaje_abierto_default') {
                        return update_option('flacso_inscripciones_mensaje_abierto_default', $value);
                    }
                    if ($key === 'inscripciones_mensaje_cerrado_default') {
                        return update_option('flacso_inscripciones_mensaje_cerrado_default', $value);
                    }
                    return update_post_meta($post_obj->ID, $key, $value);
                },
                'schema' => null,
            ]);
        }

        // Registrar el ID del post/página asociada (campo legado)
        register_rest_field('oferta-academica', 'associated_post_id', [
            'get_callback' => function ($post_array) {
                return (int) get_post_meta($post_array['id'], '_oferta_page_id', true);
            },
            'update_callback' => function ($value, $post_obj) {
                return update_post_meta($post_obj->ID, '_oferta_page_id', (int) $value);
            },
            'schema' => null,
        ]);

        // Registrar seminarios asociados a la oferta
        register_rest_field('oferta-academica', '_oferta_seminarios_ids', [
            'get_callback' => function ($post_array) {
                $val = get_post_meta($post_array['id'], '_oferta_seminarios_ids', true);
                return is_array($val) ? array_values(array_map('intval', $val)) : [];
            },
            'update_callback' => function ($value, $post_obj) {
                if (is_array($value)) {
                    $cleaned = array_values(array_unique(array_map('intval', $value)));
                    update_post_meta($post_obj->ID, '_oferta_seminarios_ids', $cleaned);
                } else {
                    delete_post_meta($post_obj->ID, '_oferta_seminarios_ids');
                }
                return true;
            },
            'schema' => [
                'type' => 'array',
                'items' => [
                    'type' => 'integer'
                ]
            ],
        ]);

        // Registrar campo para taxonomías simplificadas (mantenemos compatibilidad con el frontend)
        register_rest_field('oferta-academica', 'taxonomies', [
            'get_callback' => function ($post_array) {
                $taxonomies = [
                    'tipo-oferta-academica' => wp_get_post_terms($post_array['id'], 'tipo-oferta-academica', ['fields' => 'all']),
                    'area_tematica' => wp_get_post_terms($post_array['id'], 'area_tematica', ['fields' => 'all']),
                ];
                $tax_simplified = [];
                foreach ($taxonomies as $tax => $terms) {
                    $tax_simplified[$tax] = array_map(function($t) {
                        return ['id' => $t->term_id, 'name' => $t->name, 'slug' => $t->slug];
                    }, (array)$terms);
                }
                return $tax_simplified;
            },
            'update_callback' => function ($value, $post_obj) {
                if (is_array($value)) {
                    if (isset($value['tipo-oferta-academica'])) {
                        $ids = array_map('intval', (array) $value['tipo-oferta-academica']);
                        wp_set_object_terms($post_obj->ID, $ids, 'tipo-oferta-academica');
                    }
                    if (isset($value['area_tematica'])) {
                        $ids = array_map('intval', (array) $value['area_tematica']);
                        wp_set_object_terms($post_obj->ID, $ids, 'area_tematica');
                    }
                }
                return true;
            },
            'schema' => [
                'type' => 'object',
                'properties' => [
                    'tipo-oferta-academica' => [
                        'type' => 'array',
                        'items' => ['type' => 'integer']
                    ],
                    'area_tematica' => [
                        'type' => 'array',
                        'items' => ['type' => 'integer']
                    ]
                ]
            ],
        ]);

        // Registrar campo para la imagen destacada enriquecida
        register_rest_field('oferta-academica', 'featured_image_data', [
            'get_callback' => function ($post_array) {
                $media_id = get_post_thumbnail_id($post_array['id']);
                if (!$media_id) return null;
                
                $full = wp_get_attachment_image_src($media_id, 'full');
                $large = wp_get_attachment_image_src($media_id, 'large');
                $medium = wp_get_attachment_image_src($media_id, 'medium');
                $alt = get_post_meta($media_id, '_wp_attachment_image_alt', true);
                
                return [
                    'id' => (int)$media_id,
                    'url' => $full[0] ?? '',
                    'large' => $large[0] ?? '',
                    'medium' => $medium[0] ?? '',
                    'alt' => $alt,
                    'width' => $full[1] ?? 0,
                    'height' => $full[2] ?? 0,
                ];
            },
            'schema' => null,
        ]);
    }
}
