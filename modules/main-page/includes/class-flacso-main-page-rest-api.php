<?php

if (!defined('ABSPATH')) {
    exit;
}

final class Flacso_Main_Page_REST_API {
    private const REST_NAMESPACE = 'flacso/v1';
    private const REST_ROUTE = '/main-page/settings';
    private const SCHEMA_VERSION = 2;

    private const EXTRA_SECTION_LABELS = [
        'sections_visibility' => 'Visibilidad de secciones',
        'sections_order' => 'Orden de la portada',
        'section_heading_color' => 'Color base de encabezados',
        'section_heading_colors' => 'Colores por sección',
    ];

    private const SECTION_DESCRIPTIONS = [
        'hero' => 'Mensaje principal de la portada, imagen y llamados a la acción.',
        'eventos' => 'Próximas actividades y eventos institucionales mostrados en la portada.',
        'novedades_busqueda' => 'Buscador y filtros del módulo de novedades.',
        'novedades' => 'Configuración general del listado cronológico de novedades.',
        'seminarios' => 'Seminarios próximos. Son unidades académicas que también pueden integrar otras ofertas.',
        'quienes' => 'Contenido institucional del bloque “Quiénes somos”.',
        'instagram' => 'Contenido social e integración con Instagram.',
        'oferta_academica' => 'Accesos y presentación de Maestrías, Especializaciones, Diplomas, Diplomados y Seminarios.',
        'mailing' => 'Suscripción a la lista de difusión institucional.',
        'congreso' => 'Bloque histórico y llamada a la acción del Congreso.',
        'contacto' => 'Bloque final de contacto de la portada.',
        'sections_visibility' => 'Activa u oculta bloques sin borrar su contenido.',
        'sections_order' => 'Define el orden real con el que se renderizan las secciones de la portada.',
        'section_heading_color' => 'Color base para los encabezados de las secciones.',
        'section_heading_colors' => 'Ajustes de color específicos por sección.',
    ];

    private const SECTION_GROUPS = [
        'hero' => 'principal',
        'oferta_academica' => 'formacion',
        'seminarios' => 'formacion',
        'eventos' => 'actualidad',
        'novedades_busqueda' => 'actualidad',
        'novedades' => 'actualidad',
        'quienes' => 'institucional',
        'instagram' => 'institucional',
        'congreso' => 'institucional',
        'mailing' => 'conversion',
        'contacto' => 'conversion',
        'sections_visibility' => 'configuracion',
        'sections_order' => 'configuracion',
        'section_heading_color' => 'configuracion',
        'section_heading_colors' => 'configuracion',
    ];

    private const SECTION_ICONS = [
        'hero' => 'home',
        'oferta_academica' => 'graduation-cap',
        'seminarios' => 'book-open',
        'eventos' => 'calendar',
        'novedades_busqueda' => 'search',
        'novedades' => 'newspaper',
        'quienes' => 'building',
        'instagram' => 'instagram',
        'congreso' => 'archive',
        'mailing' => 'mail',
        'contacto' => 'message-circle',
        'sections_visibility' => 'eye',
        'sections_order' => 'list',
        'section_heading_color' => 'palette',
        'section_heading_colors' => 'palette',
    ];

    public static function init(): void {
        add_action('rest_api_init', [self::class, 'register_routes']);
    }

    public static function register_routes(): void {
        register_rest_route(self::REST_NAMESPACE, self::REST_ROUTE, [
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
        ]);
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
            return new WP_Error('flacso_main_page_invalid_payload', __('El body debe ser un objeto JSON.', 'flacso-main-page'), ['status' => 400]);
        }

        $settings_input = $payload['settings'] ?? $payload;
        if (!is_array($settings_input)) {
            return new WP_Error('flacso_main_page_invalid_settings', __('La clave "settings" debe contener un objeto.', 'flacso-main-page'), ['status' => 400]);
        }

        $sanitized = Flacso_Main_Page_Settings::sanitize($settings_input);
        $saved = update_option(Flacso_Main_Page_Settings::OPTION_KEY, $sanitized, false);
        if ($saved || $sanitized === get_option(Flacso_Main_Page_Settings::OPTION_KEY)) {
            wp_cache_delete(Flacso_Main_Page_Settings::OPTION_KEY, 'options');
        }
        Flacso_Main_Page_Settings::invalidate_cache();

        return rest_ensure_response(self::build_response_payload([
            'message' => __('Configuración de la portada guardada correctamente.', 'flacso-main-page'),
        ]));
    }

    private static function build_response_payload(array $extra_data = []): array {
        $settings = Flacso_Main_Page_Settings::get_settings();
        $defaults = Flacso_Main_Page_Settings::get_defaults();

        return [
            'ok' => true,
            'data' => array_merge([
                'schemaVersion' => self::SCHEMA_VERSION,
                'optionKey' => Flacso_Main_Page_Settings::OPTION_KEY,
                'homepageUrl' => home_url('/'),
                'settings' => $settings,
                'defaults' => $defaults,
                'sections' => self::build_sections($settings),
                'groups' => self::build_groups(),
                'sectionAliases' => class_exists('Flacso_Main_Page_Section_Keys')
                    ? Flacso_Main_Page_Section_Keys::aliases()
                    : [],
                'retiredSections' => class_exists('Flacso_Main_Page_Section_Keys')
                    ? Flacso_Main_Page_Section_Keys::retired()
                    : [],
            ], $extra_data),
        ];
    }

    private static function build_sections(array $settings): array {
        $sections = [];
        $visibility = isset($settings['sections_visibility']) && is_array($settings['sections_visibility'])
            ? $settings['sections_visibility']
            : [];
        $order = Flacso_Main_Page_Settings::get_homepage_section_order();

        foreach (array_keys($settings) as $key) {
            $is_system = strpos($key, 'section_') === 0 || strpos($key, 'sections_') === 0;
            $position = array_search($key, $order, true);
            $sections[] = [
                'key' => $key,
                'label' => self::get_section_label($key),
                'description' => self::SECTION_DESCRIPTIONS[$key] ?? '',
                'kind' => $is_system ? 'system' : 'content',
                'group' => self::SECTION_GROUPS[$key] ?? ($is_system ? 'configuracion' : 'otros'),
                'icon' => self::SECTION_ICONS[$key] ?? 'settings',
                'visible' => $is_system ? true : !isset($visibility[$key]) || (bool) $visibility[$key],
                'position' => $position === false ? null : (int) $position,
            ];
        }

        usort($sections, static function (array $a, array $b): int {
            if ($a['kind'] !== $b['kind']) {
                return $a['kind'] === 'content' ? -1 : 1;
            }
            if ($a['position'] !== null || $b['position'] !== null) {
                return ($a['position'] ?? PHP_INT_MAX) <=> ($b['position'] ?? PHP_INT_MAX);
            }
            return strcasecmp((string) $a['label'], (string) $b['label']);
        });
        return $sections;
    }

    private static function build_groups(): array {
        return [
            ['key' => 'principal', 'label' => 'Portada'],
            ['key' => 'formacion', 'label' => 'Formación'],
            ['key' => 'actualidad', 'label' => 'Actualidad'],
            ['key' => 'institucional', 'label' => 'Institucional'],
            ['key' => 'conversion', 'label' => 'Conversión y contacto'],
            ['key' => 'otros', 'label' => 'Otros contenidos'],
            ['key' => 'configuracion', 'label' => 'Configuración avanzada'],
        ];
    }

    private static function get_section_label(string $key): string {
        return self::EXTRA_SECTION_LABELS[$key] ?? Flacso_Main_Page_Settings::get_section_label($key);
    }
}

Flacso_Main_Page_REST_API::init();
