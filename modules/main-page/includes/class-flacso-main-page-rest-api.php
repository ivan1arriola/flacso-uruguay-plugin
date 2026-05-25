<?php

if (!defined('ABSPATH')) {
    exit;
}

class Flacso_Main_Page_REST_API {
    private const REST_NAMESPACE = 'flacso/v1';
    private const REST_ROUTE = '/main-page/settings';
    private const EXTRA_SECTION_LABELS = [
        'sections_visibility' => 'Visibilidad de secciones',
        'sections_order' => 'Orden del home',
        'section_heading_color' => 'Color base de encabezados',
        'section_heading_colors' => 'Colores por sección',
    ];
    private const SECTION_DESCRIPTIONS = [
        'hero' => 'Encabezado principal, imagen de fondo y botones destacados.',
        'eventos' => 'Bloque de eventos y parámetros vinculados a su visualización.',
        'novedades_destacadas' => 'Selección y despliegue de novedades destacadas.',
        'novedades_busqueda' => 'Buscador y filtros del módulo de novedades.',
        'novedades' => 'Configuración general del listado de novedades.',
        'seminarios' => 'Ajustes del bloque de seminarios en la home.',
        'quienes' => 'Contenido institucional del bloque “Quiénes somos”.',
        'instagram' => 'Datos del bloque social e integración con Instagram.',
        'posgrados' => 'Tarjetas y textos de la oferta educativa principal.',
        'congreso' => 'Hero y llamada a la acción del congreso.',
        'contacto' => 'Bloque de contacto y estilos de fondo.',
        'sections_visibility' => 'Activa u oculta bloques del home sin borrar su configuración.',
        'sections_order' => 'Define el orden con el que se renderizan las secciones de la portada.',
        'section_heading_color' => 'Color base para los encabezados de las secciones.',
        'section_heading_colors' => 'Overrides por sección para los colores de encabezado.',
    ];

    public static function init(): void {
        add_action('rest_api_init', [self::class, 'register_routes']);
    }

    public static function register_routes(): void {
        register_rest_route(
            self::REST_NAMESPACE,
            self::REST_ROUTE,
            [
                [
                    'methods' => WP_REST_Server::READABLE,
                    'callback' => [self::class, 'get_settings'],
                    'permission_callback' => [self::class, 'can_manage_settings'],
                ],
                [
                    'methods' => WP_REST_Server::EDITABLE,
                    'callback' => [self::class, 'update_settings'],
                    'permission_callback' => [self::class, 'can_manage_settings'],
                ],
            ]
        );
    }

    public static function can_manage_settings(): bool {
        return current_user_can('manage_options');
    }

    public static function get_settings(WP_REST_Request $request) {
        return rest_ensure_response(self::build_response_payload());
    }

    public static function update_settings(WP_REST_Request $request) {
        $payload = $request->get_json_params();
        if (!is_array($payload)) {
            return new WP_Error(
                'flacso_main_page_invalid_payload',
                __('El body debe ser un objeto JSON.', 'flacso-main-page'),
                ['status' => 400]
            );
        }

        $settings_input = $payload['settings'] ?? $payload;
        if (!is_array($settings_input)) {
            return new WP_Error(
                'flacso_main_page_invalid_settings',
                __('La clave "settings" debe contener un objeto.', 'flacso-main-page'),
                ['status' => 400]
            );
        }

        $sanitized = Flacso_Main_Page_Settings::sanitize($settings_input);
        $saved = update_option(Flacso_Main_Page_Settings::OPTION_KEY, $sanitized);

        if ($saved || $sanitized === get_option(Flacso_Main_Page_Settings::OPTION_KEY)) {
            wp_cache_delete(Flacso_Main_Page_Settings::OPTION_KEY, 'options');
        }

        Flacso_Main_Page_Settings::invalidate_cache();

        return rest_ensure_response(
            self::build_response_payload([
                'message' => __('Configuración de la portada guardada correctamente.', 'flacso-main-page'),
            ])
        );
    }

    private static function build_response_payload(array $extra_data = []): array {
        $settings = Flacso_Main_Page_Settings::get_settings();
        $defaults = Flacso_Main_Page_Settings::get_defaults();

        return [
            'ok' => true,
            'data' => array_merge(
                [
                    'optionKey' => Flacso_Main_Page_Settings::OPTION_KEY,
                    'settings' => $settings,
                    'defaults' => $defaults,
                    'sections' => self::build_sections($settings),
                ],
                $extra_data
            ),
        ];
    }

    private static function build_sections(array $settings): array {
        $sections = [];

        foreach (array_keys($settings) as $key) {
            $sections[] = [
                'key' => $key,
                'label' => self::get_section_label($key),
                'description' => self::SECTION_DESCRIPTIONS[$key] ?? '',
                'kind' => 0 === strpos($key, 'section_') || 0 === strpos($key, 'sections_')
                    ? 'system'
                    : 'content',
            ];
        }

        return $sections;
    }

    private static function get_section_label(string $key): string {
        if (isset(self::EXTRA_SECTION_LABELS[$key])) {
            return self::EXTRA_SECTION_LABELS[$key];
        }

        return Flacso_Main_Page_Settings::get_section_label($key);
    }
}

Flacso_Main_Page_REST_API::init();
