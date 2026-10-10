<?php

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Configuración canónica de la portada.
 *
 * La clase conserva compatibilidad de lectura con claves históricas, pero todo
 * valor devuelto o guardado utiliza el modelo actual (`oferta_academica`).
 */
final class Flacso_Main_Page_Settings {
    public const OPTION_KEY = 'flacso-main-page_settings';

    private const SECTION_HEADING_COLOR_CHOICES = ['primary', 'palette7'];

    private static $section_visibility_cache = null;
    private static $settings_cache = null;
    private static $defaults_cache = null;

    public static function invalidate_cache(): void {
        self::$section_visibility_cache = null;
        self::$settings_cache = null;
        self::$defaults_cache = null;
    }

    public static function get_hero_button_defaults(): array {
        return [
            [
                'label' => 'Ver Oferta Académica',
                'url' => '/formacion/',
                'style' => 'primary',
                'enabled' => true,
            ],
            [
                'label' => 'Ver Seminarios',
                'url' => '/formacion/seminarios/',
                'style' => 'outline',
                'enabled' => true,
            ],
            [
                'label' => 'Solicite Información',
                'url' => '/contactos/',
                'style' => 'light',
                'enabled' => false,
            ],
            [
                'label' => '',
                'url' => '',
                'style' => 'ghost',
                'enabled' => false,
            ],
        ];
    }

    public static function get_button_style_options(): array {
        return [
            'primary' => __('Primario (relleno)', 'flacso-main-page'),
            'outline' => __('Contorno', 'flacso-main-page'),
            'light' => __('Claro', 'flacso-main-page'),
            'ghost' => __('Fantasma', 'flacso-main-page'),
        ];
    }

    public static function get_defaults(): array {
        if (self::$defaults_cache !== null) {
            return self::$defaults_cache;
        }

        self::$defaults_cache = [
            'hero' => [
                'kicker' => '',
                'title' => 'Inscripciones Abiertas: Maestrías, Especializaciones, Diplomados y Diplomas',
                'subtitle' => 'Sumate a la oferta académica de FLACSO Uruguay.',
                'background_image' => 'https://flacso.edu.uy/wp-content/uploads/2025/11/primer-plano-de-ejecutivos-de-negocios-en-la-oficina-scaled.jpg',
                'primary_label' => 'Ver Oferta Académica',
                'primary_url' => '/formacion/',
                'secondary_label' => 'Ver Seminarios',
                'secondary_url' => '/formacion/seminarios/',
                'bubble_primary_label' => 'Diplomados y Diplomas',
                'bubble_primary_url' => '/formacion/diplomados/',
                'bubble_secondary_label' => 'Seminarios',
                'bubble_secondary_url' => '/formacion/seminarios/',
                'bubble_primary_enabled' => true,
                'bubble_secondary_enabled' => true,
                'bubble_primary_style' => 'primary',
                'bubble_secondary_style' => 'outline',
                'show_buttons' => true,
                'buttons' => self::get_hero_button_defaults(),
            ],
            'oferta_academica' => [
                'show_title' => true,
                'title' => 'Nuestra Oferta Académica',
                'intro' => '<strong>FLACSO Uruguay</strong> brinda formación mediante <strong>Seminarios, Diplomas, Diplomados, Especializaciones y Maestrías</strong>, con abordajes teóricos y prácticos de problemas de las ciencias sociales.',
                'cards' => [
                    [
                        'key' => 'maestrias',
                        'title' => 'Maestrías',
                        'type' => 'Maestría',
                        'url' => '/formacion/maestrias/',
                        'image' => 'https://flacso.edu.uy/wp-content/uploads/2023/08/IMAGE-SITIO-WEB-9.png',
                        'desc' => 'Formación académica avanzada que culmina en un trabajo de investigación y permite continuar hacia estudios doctorales.',
                    ],
                    [
                        'key' => 'especializaciones',
                        'title' => 'Especializaciones',
                        'type' => 'Especialización',
                        'url' => '/formacion/especializaciones/',
                        'image' => 'https://flacso.edu.uy/wp-content/uploads/2023/08/IMAGE-SITIO-WEB-1.png',
                        'desc' => 'Profundización y actualización de marcos teóricos, metodologías y herramientas profesionales.',
                    ],
                    [
                        'key' => 'diplomas',
                        'title' => 'Diplomas',
                        'type' => 'Diploma',
                        'url' => '/formacion/diplomas/',
                        'image' => 'https://flacso.edu.uy/wp-content/uploads/2023/08/IMAGE-SITIO-WEB-5-1024x1024.png',
                        'desc' => 'Propuestas que combinan análisis temático, herramientas prácticas y trayectos de formación articulados.',
                    ],
                    [
                        'key' => 'diplomados',
                        'title' => 'Diplomados',
                        'type' => 'Diplomado',
                        'url' => '/formacion/diplomados/',
                        'image' => 'https://flacso.edu.uy/wp-content/uploads/2023/08/IMAGE-SITIO-WEB-3.png',
                        'desc' => 'Trayectos académicos orientados a la actualización y continuidad hacia estudios de maestría.',
                    ],
                    [
                        'key' => 'seminarios',
                        'title' => 'Seminarios',
                        'type' => 'Seminario',
                        'url' => '/formacion/seminarios/',
                        'image' => 'https://flacso.edu.uy/wp-content/uploads/2023/08/IMAGE-SITIO-WEB-2.png',
                        'desc' => 'Unidad académica mínima y reutilizable de las distintas propuestas de formación, también disponible de forma independiente.',
                    ],
                ],
                // Controles del catálogo. Conviven con las tarjetas de portada
                // dentro del mismo dominio, evitando dos secciones homónimas.
                'show_filters' => true,
                'show_maestrias' => true,
                'show_especializaciones' => true,
                'show_diplomados' => true,
                'show_diplomas' => true,
                'show_seminarios' => true,
                'show_inactivos' => false,
                'seminarios_limit' => 12,
            ],
            'congreso' => [
                'title' => '',
                'content' => '',
                'cta_label' => 'Libros de Actas del Congreso Disponibles',
                'cta_url' => 'https://congreso.flacso.edu.uy/',
                'background_image' => 'https://flacso.edu.uy/wp-content/uploads/2023/04/IMAGEN-CLAUSURA-CONGRESO-FLACSO.jpg',
                'bubble_primary_label' => '',
                'bubble_primary_url' => '',
                'bubble_secondary_label' => '',
                'bubble_secondary_url' => '',
            ],
            'instagram' => [
                'profile_url' => 'https://www.instagram.com/flacsouruguay/',
                'title' => 'Formación, encuentros y comunidad.',
                'description' => 'En Instagram compartimos seminarios, convocatorias, actividades académicas y momentos de la comunidad FLACSO Uruguay.',
                'cta_label' => 'Ver más en @flacsouruguay',
                'access_token' => '',
                'api_type' => 'basic',
            ],
            'contacto' => [
                'title' => 'Contáctanos',
                'subtitle' => 'Con gusto responderemos todas tus consultas',
                'cta_label' => 'Solicite Información',
                'cta_url' => '/contactos/',
                'background_mode' => 'image_overlay',
                'background_image' => '',
                'background_color' => '#f2f6ff',
                'background_gradient_start' => '#0f1a2d',
                'background_gradient_end' => '#1d3a72',
                'background_gradient_angle' => 135,
                'background_overlay_style' => 'gradient',
                'background_overlay_color' => '#0f1a2d',
                'background_overlay_color_secondary' => '#0f1a2d',
                'background_overlay_opacity' => 0.78,
                'background_overlay_opacity_secondary' => 0.45,
                'background_overlay_angle' => 180,
            ],
            'mailing' => [
                'title' => 'Suscribite a la lista de difusión de FLACSO Uruguay',
                'subtitle' => 'Recibí novedades, actividades y avisos importantes directamente en tu correo.',
                'button_label' => 'Quiero suscribirme',
                'consent_text' => 'Acepto recibir novedades y comunicaciones institucionales de FLACSO Uruguay.',
            ],
            'quienes' => [
                'title' => '¿Qué es FLACSO Uruguay?',
                'content' => '<strong>FLACSO Uruguay</strong> es la sede uruguaya de la <strong>Facultad Latinoamericana de Ciencias Sociales</strong>, una de las principales redes académicas de América Latina y el Caribe. Contribuye al desarrollo de la región mediante formación, investigación crítica y transferencia de conocimiento.',
                'cta_label' => 'Conocer más',
                'cta_url' => '/sobre-nosotros/',
                'background_image' => 'https://www.flacso.org/assets/img/banner/banner-01.jpg',
                'highlight_color' => '#fcd116',
            ],
            'novedades' => [
                'per_page' => 12,
                'per_page_desktop' => 12,
                'per_page_mobile' => 12,
            ],
            'sections_order' => self::get_homepage_section_order_defaults(),
            'sections_visibility' => self::get_section_visibility_defaults(),
            'section_heading_color' => 'primary',
            'section_heading_colors' => [],
        ];

        return self::$defaults_cache;
    }

    public static function get_settings(): array {
        if (self::$settings_cache !== null) {
            return self::$settings_cache;
        }

        $saved = get_option(self::OPTION_KEY, []);
        $saved = is_array($saved) ? $saved : [];
        $saved = self::normalize_settings($saved);
        $defaults = self::get_defaults();

        $merged = wp_parse_args($saved, $defaults);
        foreach ($defaults as $key => $default_value) {
            if (is_array($default_value) && isset($saved[$key]) && is_array($saved[$key])) {
                $merged[$key] = wp_parse_args($saved[$key], $default_value);
            }
        }

        self::$settings_cache = self::normalize_settings($merged);
        return self::$settings_cache;
    }

    private static function normalize_settings(array $settings): array {
        if (class_exists('Flacso_Main_Page_Section_Keys')) {
            $settings = Flacso_Main_Page_Section_Keys::normalize_settings($settings);
        }

        if (isset($settings['mailing']) && is_array($settings['mailing'])) {
            $mailing_title = trim((string) ($settings['mailing']['title'] ?? ''));
            if (in_array($mailing_title, [
                'Suscribite al mailing de FLACSO Uruguay',
                'Suscribite al boletín de FLACSO Uruguay',
                'Suscribite a la lista de difusión de FLACSO Uruguay',
            ], true)) {
                $settings['mailing']['title'] = 'Suscribite a la lista de difusión de FLACSO Uruguay';
            }
        }

        if (isset($settings['oferta_academica']['cards']) && is_array($settings['oferta_academica']['cards'])) {
            foreach ($settings['oferta_academica']['cards'] as $index => $card) {
                if (is_array($card)) {
                    $settings['oferta_academica']['cards'][$index] = self::normalize_offer_card($card);
                }
            }
        }

        if (isset($settings['sections_order']) && is_array($settings['sections_order'])) {
            $settings['sections_order'] = self::sanitize_homepage_section_order($settings['sections_order']);
        }

        return $settings;
    }

    private static function canonical_section_key(string $section): string {
        return class_exists('Flacso_Main_Page_Section_Keys')
            ? Flacso_Main_Page_Section_Keys::canonicalize($section)
            : sanitize_key($section);
    }

    private static function normalize_offer_card(array $card): array {
        $key = sanitize_key((string) ($card['key'] ?? ''));
        $title = strtolower(remove_accents(trim((string) ($card['title'] ?? $card['titulo'] ?? ''))));
        $type = strtolower(remove_accents(trim((string) ($card['type'] ?? $card['tipo'] ?? ''))));
        $url = (string) ($card['url'] ?? '');

        $is_course_card = $key === 'cursos'
            || $title === 'cursos'
            || $type === 'curso'
            || strpos($url, '/cursos/') !== false;

        if ($is_course_card) {
            $card['key'] = 'seminarios';
            $card['title'] = 'Seminarios';
            $card['type'] = 'Seminario';
            $card['url'] = '/formacion/seminarios/';
            $card['desc'] = 'Unidad académica mínima de la oferta y espacio de formación disponible también de forma independiente.';
        }

        return $card;
    }

    public static function get_section(string $section): array {
        $section = self::canonical_section_key($section);
        $settings = self::get_settings();
        $defaults = self::get_defaults();

        if (isset($settings[$section]) && is_array($settings[$section])) {
            return wp_parse_args($settings[$section], $defaults[$section] ?? []);
        }

        return isset($defaults[$section]) && is_array($defaults[$section]) ? $defaults[$section] : [];
    }

    public static function get_value(string $section, string $key, $fallback = '') {
        $section_data = self::get_section($section);
        return $section_data[$key] ?? $fallback;
    }

    public static function get_novedades_per_page(?bool $is_mobile = null): int {
        $defaults = self::get_defaults();
        $default_legacy = self::sanitize_novedades_per_page_value($defaults['novedades']['per_page'] ?? 12, 12);
        $section = self::get_section('novedades');
        $legacy = self::sanitize_novedades_per_page_value($section['per_page'] ?? $default_legacy, $default_legacy);

        $saved = get_option(self::OPTION_KEY, []);
        $saved_novedades = is_array($saved) && isset($saved['novedades']) && is_array($saved['novedades'])
            ? $saved['novedades']
            : [];

        $desktop_source = array_key_exists('per_page_desktop', $saved_novedades)
            ? ($section['per_page_desktop'] ?? $legacy)
            : $legacy;
        $mobile_source = array_key_exists('per_page_mobile', $saved_novedades)
            ? ($section['per_page_mobile'] ?? $legacy)
            : $legacy;

        if ($is_mobile === null) {
            $is_mobile = wp_is_mobile();
        }

        return $is_mobile
            ? self::sanitize_novedades_per_page_value($mobile_source, $legacy)
            : self::sanitize_novedades_per_page_value($desktop_source, $legacy);
    }

    public static function get_section_visibility_defaults(): array {
        $keys = class_exists('Flacso_Main_Page_Section_Keys')
            ? Flacso_Main_Page_Section_Keys::all()
            : self::get_homepage_section_order_defaults();
        return array_fill_keys($keys, true);
    }

    public static function get_section_visibility(): array {
        if (self::$section_visibility_cache !== null) {
            return self::$section_visibility_cache;
        }

        $settings = self::get_settings();
        $defaults = self::get_section_visibility_defaults();
        $visibility = isset($settings['sections_visibility']) && is_array($settings['sections_visibility'])
            ? $settings['sections_visibility']
            : [];

        if (class_exists('Flacso_Main_Page_Section_Keys')) {
            $visibility = Flacso_Main_Page_Section_Keys::normalize_keyed_map($visibility);
        }

        self::$section_visibility_cache = wp_parse_args($visibility, $defaults);
        return self::$section_visibility_cache;
    }

    public static function is_section_visible(string $key): bool {
        $key = self::canonical_section_key($key);
        $visibility = self::get_section_visibility();
        return !array_key_exists($key, $visibility) || (bool) $visibility[$key];
    }

    public static function get_section_heading_color_choice(string $section = ''): string {
        $settings = self::get_settings();
        $defaults = self::get_defaults();
        $base = $settings['section_heading_color'] ?? $defaults['section_heading_color'];
        if (!in_array($base, self::SECTION_HEADING_COLOR_CHOICES, true)) {
            $base = $defaults['section_heading_color'];
        }
        if ($section === '') {
            return $base;
        }
        $section = self::canonical_section_key($section);
        $colors = self::get_section_heading_colors();
        return $colors[$section] ?? $base;
    }

    public static function get_section_heading_colors(): array {
        $settings = self::get_settings();
        $defaults = self::get_defaults();
        $base = $settings['section_heading_color'] ?? $defaults['section_heading_color'];
        if (!in_array($base, self::SECTION_HEADING_COLOR_CHOICES, true)) {
            $base = $defaults['section_heading_color'];
        }

        $result = array_fill_keys(self::get_homepage_section_order_defaults(), $base);
        $configured = isset($settings['section_heading_colors']) && is_array($settings['section_heading_colors'])
            ? $settings['section_heading_colors']
            : [];
        if (class_exists('Flacso_Main_Page_Section_Keys')) {
            $configured = Flacso_Main_Page_Section_Keys::normalize_keyed_map($configured);
        }

        foreach ($configured as $section_key => $choice) {
            if (isset($result[$section_key]) && in_array($choice, self::SECTION_HEADING_COLOR_CHOICES, true)) {
                $result[$section_key] = $choice;
            }
        }
        return $result;
    }

    public static function get_section_label(string $key): string {
        $key = self::canonical_section_key($key);
        $labels = [
            'hero' => __('Hero principal', 'flacso-main-page'),
            'eventos' => __('Eventos', 'flacso-main-page'),
            'novedades_busqueda' => __('Buscador de novedades', 'flacso-main-page'),
            'novedades' => __('Novedades', 'flacso-main-page'),
            'seminarios' => __('Seminarios', 'flacso-main-page'),
            'quienes' => __('Quiénes somos', 'flacso-main-page'),
            'instagram' => __('Instagram', 'flacso-main-page'),
            'oferta_academica' => __('Oferta Académica', 'flacso-main-page'),
            'mailing' => __('Lista de difusión', 'flacso-main-page'),
            'congreso' => __('Congreso', 'flacso-main-page'),
            'contacto' => __('Contacto', 'flacso-main-page'),
        ];
        return $labels[$key] ?? ucfirst(str_replace('_', ' ', $key));
    }

    public static function get_homepage_section_order_defaults(): array {
        return [
            'hero',
            'eventos',
            'novedades',
            'novedades_busqueda',
            'seminarios',
            'quienes',
            'instagram',
            'oferta_academica',
            'mailing',
            'contacto',
            'congreso',
        ];
    }

    public static function get_homepage_section_order(): array {
        $settings = self::get_settings();
        $saved_order = isset($settings['sections_order']) && is_array($settings['sections_order'])
            ? $settings['sections_order']
            : [];
        return self::sanitize_homepage_section_order($saved_order);
    }

    public static function sanitize_homepage_section_order(array $input): array {
        $defaults = self::get_homepage_section_order_defaults();
        if (class_exists('Flacso_Main_Page_Section_Keys')) {
            $input = Flacso_Main_Page_Section_Keys::normalize_order($input);
        } else {
            $input = array_values(array_filter(array_map('sanitize_key', $input)));
        }

        $order = [];
        foreach ($input as $key) {
            if (in_array($key, $defaults, true) && !in_array($key, $order, true)) {
                $order[] = $key;
            }
        }

        $order = array_values(array_diff($order, ['hero']));
        array_unshift($order, 'hero');

        foreach ($defaults as $key) {
            if (!in_array($key, $order, true)) {
                $order[] = $key;
            }
        }
        return $order;
    }

    public static function normalize_url_output(string $url): string {
        $url = trim($url);
        if ($url === '') {
            return '';
        }
        if (preg_match('#^(https?:)?//#i', $url)) {
            return esc_url($url);
        }
        if ($url[0] !== '/') {
            $url = '/' . ltrim($url, '/');
        }
        return esc_url(home_url($url));
    }

    public static function sanitize(array $input): array {
        self::invalidate_cache();
        if (class_exists('Flacso_Main_Page_Section_Keys')) {
            $input = Flacso_Main_Page_Section_Keys::normalize_settings($input);
        }

        $defaults = self::get_defaults();
        $output = $defaults;

        if (isset($input['hero']) && is_array($input['hero'])) {
            $hero = $input['hero'];
            foreach (['kicker', 'title', 'subtitle', 'primary_label', 'secondary_label', 'bubble_primary_label', 'bubble_secondary_label'] as $field) {
                $output['hero'][$field] = wp_kses_post($hero[$field] ?? $defaults['hero'][$field]);
            }
            $output['hero']['background_image'] = esc_url_raw($hero['background_image'] ?? $defaults['hero']['background_image']);
            foreach (['primary_url', 'secondary_url', 'bubble_primary_url', 'bubble_secondary_url'] as $field) {
                $output['hero'][$field] = self::sanitize_relative_url((string) ($hero[$field] ?? $defaults['hero'][$field]));
            }
            $output['hero']['bubble_primary_enabled'] = !empty($hero['bubble_primary_enabled']);
            $output['hero']['bubble_secondary_enabled'] = !empty($hero['bubble_secondary_enabled']);
            $output['hero']['show_buttons'] = array_key_exists('show_buttons', $hero)
                ? !empty($hero['show_buttons'])
                : $defaults['hero']['show_buttons'];

            $styles = array_keys(self::get_button_style_options());
            foreach (['bubble_primary_style', 'bubble_secondary_style'] as $field) {
                $candidate = sanitize_key((string) ($hero[$field] ?? $defaults['hero'][$field]));
                $output['hero'][$field] = in_array($candidate, $styles, true) ? $candidate : $defaults['hero'][$field];
            }

            $button_defaults = self::get_hero_button_defaults();
            $buttons_input = isset($hero['buttons']) && is_array($hero['buttons']) ? $hero['buttons'] : [];
            $output['hero']['buttons'] = [];
            foreach ($button_defaults as $index => $button_default) {
                $button = isset($buttons_input[$index]) && is_array($buttons_input[$index]) ? $buttons_input[$index] : $button_default;
                $style = sanitize_key((string) ($button['style'] ?? $button_default['style']));
                $output['hero']['buttons'][$index] = [
                    'label' => wp_kses_post($button['label'] ?? $button_default['label']),
                    'url' => self::sanitize_relative_url((string) ($button['url'] ?? $button_default['url'])),
                    'style' => in_array($style, $styles, true) ? $style : $button_default['style'],
                    'enabled' => !empty($button['enabled']),
                ];
            }
            $output['hero']['primary_label'] = $output['hero']['buttons'][0]['label'];
            $output['hero']['primary_url'] = $output['hero']['buttons'][0]['url'];
            $output['hero']['secondary_label'] = $output['hero']['buttons'][1]['label'];
            $output['hero']['secondary_url'] = $output['hero']['buttons'][1]['url'];
        }

        if (isset($input['oferta_academica']) && is_array($input['oferta_academica'])) {
            $offer = $input['oferta_academica'];
            $output['oferta_academica']['show_title'] = array_key_exists('show_title', $offer)
                ? !empty($offer['show_title'])
                : $defaults['oferta_academica']['show_title'];
            $output['oferta_academica']['title'] = sanitize_text_field($offer['title'] ?? $defaults['oferta_academica']['title']);
            $output['oferta_academica']['intro'] = wp_kses_post($offer['intro'] ?? $defaults['oferta_academica']['intro']);

            $cards_input = isset($offer['cards']) && is_array($offer['cards']) ? $offer['cards'] : [];
            foreach ($defaults['oferta_academica']['cards'] as $index => $card_default) {
                $card = isset($cards_input[$index]) && is_array($cards_input[$index]) ? $cards_input[$index] : [];
                $output['oferta_academica']['cards'][$index] = self::normalize_offer_card([
                    'key' => $card_default['key'],
                    'title' => sanitize_text_field($card['title'] ?? $card_default['title']),
                    'type' => sanitize_text_field($card['type'] ?? $card_default['type']),
                    'url' => self::sanitize_relative_url((string) ($card['url'] ?? $card_default['url'])),
                    'image' => esc_url_raw($card['image'] ?? $card_default['image']),
                    'desc' => wp_kses_post($card['desc'] ?? $card_default['desc']),
                ]);
            }

            foreach (['show_filters', 'show_maestrias', 'show_especializaciones', 'show_diplomados', 'show_diplomas', 'show_seminarios', 'show_inactivos'] as $field) {
                if (array_key_exists($field, $offer)) {
                    $output['oferta_academica'][$field] = !empty($offer[$field]);
                }
            }
            $limit = absint($offer['seminarios_limit'] ?? $defaults['oferta_academica']['seminarios_limit']);
            $output['oferta_academica']['seminarios_limit'] = max(1, min(50, $limit ?: 12));
        }

        if (isset($input['novedades']) && is_array($input['novedades'])) {
            $news = $input['novedades'];
            $legacy = self::sanitize_novedades_per_page_value($news['per_page'] ?? 12, 12);
            $desktop = self::sanitize_novedades_per_page_value($news['per_page_desktop'] ?? $legacy, $legacy);
            $mobile = self::sanitize_novedades_per_page_value($news['per_page_mobile'] ?? $legacy, $legacy);
            $output['novedades'] = [
                'per_page' => $desktop,
                'per_page_desktop' => $desktop,
                'per_page_mobile' => $mobile,
            ];
        }

        if (isset($input['congreso']) && is_array($input['congreso'])) {
            $section = $input['congreso'];
            foreach (['title', 'content', 'cta_label', 'bubble_primary_label', 'bubble_secondary_label'] as $field) {
                $output['congreso'][$field] = wp_kses_post($section[$field] ?? $defaults['congreso'][$field]);
            }
            foreach (['cta_url', 'bubble_primary_url', 'bubble_secondary_url'] as $field) {
                $output['congreso'][$field] = self::sanitize_relative_url((string) ($section[$field] ?? $defaults['congreso'][$field]));
            }
            $output['congreso']['background_image'] = esc_url_raw($section['background_image'] ?? $defaults['congreso']['background_image']);
        }

        if (isset($input['contacto']) && is_array($input['contacto'])) {
            $section = $input['contacto'];
            foreach (['title', 'subtitle', 'cta_label'] as $field) {
                $output['contacto'][$field] = wp_kses_post($section[$field] ?? $defaults['contacto'][$field]);
            }
            $output['contacto']['cta_url'] = self::sanitize_relative_url((string) ($section['cta_url'] ?? $defaults['contacto']['cta_url']));
            $output['contacto']['background_image'] = esc_url_raw($section['background_image'] ?? $defaults['contacto']['background_image']);
            $mode = sanitize_key((string) ($section['background_mode'] ?? $defaults['contacto']['background_mode']));
            $output['contacto']['background_mode'] = in_array($mode, ['color', 'gradient', 'image', 'image_overlay'], true)
                ? $mode
                : $defaults['contacto']['background_mode'];
            foreach (['background_color', 'background_gradient_start', 'background_gradient_end', 'background_overlay_color', 'background_overlay_color_secondary'] as $field) {
                $color = sanitize_hex_color($section[$field] ?? $defaults['contacto'][$field]);
                $output['contacto'][$field] = $color ?: $defaults['contacto'][$field];
            }
            $overlay_style = sanitize_key((string) ($section['background_overlay_style'] ?? $defaults['contacto']['background_overlay_style']));
            $output['contacto']['background_overlay_style'] = in_array($overlay_style, ['solid', 'gradient'], true)
                ? $overlay_style
                : $defaults['contacto']['background_overlay_style'];
            $output['contacto']['background_gradient_angle'] = self::sanitize_angle_value($section['background_gradient_angle'] ?? 135, 135);
            $output['contacto']['background_overlay_angle'] = self::sanitize_angle_value($section['background_overlay_angle'] ?? 180, 180);
            $output['contacto']['background_overlay_opacity'] = self::sanitize_opacity_value($section['background_overlay_opacity'] ?? 0.78, 0.78);
            $output['contacto']['background_overlay_opacity_secondary'] = self::sanitize_opacity_value($section['background_overlay_opacity_secondary'] ?? 0.45, 0.45);
        }

        if (isset($input['mailing']) && is_array($input['mailing'])) {
            foreach (['title', 'subtitle', 'button_label', 'consent_text'] as $field) {
                $output['mailing'][$field] = wp_kses_post($input['mailing'][$field] ?? $defaults['mailing'][$field]);
            }
        }

        if (isset($input['quienes']) && is_array($input['quienes'])) {
            $section = $input['quienes'];
            foreach (['title', 'content', 'cta_label'] as $field) {
                $output['quienes'][$field] = wp_kses_post($section[$field] ?? $defaults['quienes'][$field]);
            }
            $output['quienes']['cta_url'] = self::sanitize_relative_url((string) ($section['cta_url'] ?? $defaults['quienes']['cta_url']));
            $output['quienes']['background_image'] = esc_url_raw($section['background_image'] ?? $defaults['quienes']['background_image']);
            $color = sanitize_hex_color($section['highlight_color'] ?? $defaults['quienes']['highlight_color']);
            $output['quienes']['highlight_color'] = $color ?: $defaults['quienes']['highlight_color'];
        }

        if (isset($input['instagram']) && is_array($input['instagram'])) {
            $section = $input['instagram'];
            $output['instagram']['profile_url'] = esc_url_raw($section['profile_url'] ?? $defaults['instagram']['profile_url']);
            foreach (['title', 'description', 'cta_label'] as $field) {
                $output['instagram'][$field] = wp_kses_post($section[$field] ?? $defaults['instagram'][$field]);
            }
            $output['instagram']['access_token'] = sanitize_text_field($section['access_token'] ?? $defaults['instagram']['access_token']);
            $api_type = sanitize_key((string) ($section['api_type'] ?? 'basic'));
            $output['instagram']['api_type'] = in_array($api_type, ['basic', 'graph'], true) ? $api_type : 'basic';
        }

        // Secciones cuyo esquema pertenece a otro dominio se preservan con un
        // saneamiento genérico. main-page no necesita conocer sus campos.
        foreach (['eventos', 'seminarios', 'novedades_busqueda'] as $section_key) {
            if (isset($input[$section_key]) && is_array($input[$section_key])) {
                $output[$section_key] = self::sanitize_generic_section($input[$section_key]);
            }
        }

        if (isset($input['sections_visibility']) && is_array($input['sections_visibility'])) {
            $visibility = class_exists('Flacso_Main_Page_Section_Keys')
                ? Flacso_Main_Page_Section_Keys::normalize_keyed_map($input['sections_visibility'])
                : $input['sections_visibility'];
            foreach (self::get_section_visibility_defaults() as $section_key => $default_state) {
                $output['sections_visibility'][$section_key] = array_key_exists($section_key, $visibility)
                    ? !empty($visibility[$section_key])
                    : $default_state;
            }
        }

        if (isset($input['sections_order']) && is_array($input['sections_order'])) {
            $output['sections_order'] = self::sanitize_homepage_section_order($input['sections_order']);
        }

        if (isset($input['section_heading_color'])) {
            $choice = sanitize_key((string) $input['section_heading_color']);
            $output['section_heading_color'] = in_array($choice, self::SECTION_HEADING_COLOR_CHOICES, true)
                ? $choice
                : $defaults['section_heading_color'];
        }

        if (isset($input['section_heading_colors']) && is_array($input['section_heading_colors'])) {
            $colors = class_exists('Flacso_Main_Page_Section_Keys')
                ? Flacso_Main_Page_Section_Keys::normalize_keyed_map($input['section_heading_colors'])
                : $input['section_heading_colors'];
            $output['section_heading_colors'] = [];
            foreach ($colors as $section_key => $choice) {
                $choice = sanitize_key((string) $choice);
                if (in_array($section_key, self::get_homepage_section_order_defaults(), true)
                    && in_array($choice, self::SECTION_HEADING_COLOR_CHOICES, true)) {
                    $output['section_heading_colors'][$section_key] = $choice;
                }
            }
        }

        return self::normalize_settings($output);
    }

    private static function sanitize_generic_section(array $input): array {
        $output = [];
        foreach ($input as $key => $value) {
            $safe_key = is_int($key) ? $key : sanitize_key((string) $key);
            if (is_array($value)) {
                $output[$safe_key] = self::sanitize_generic_section($value);
            } elseif (is_bool($value)) {
                $output[$safe_key] = $value;
            } elseif (is_int($value) || is_float($value)) {
                $output[$safe_key] = $value;
            } elseif (is_scalar($value)) {
                $output[$safe_key] = wp_kses_post((string) $value);
            }
        }
        return $output;
    }

    private static function sanitize_novedades_per_page_value($value, int $fallback): int {
        $parsed = absint($value);
        if ($parsed <= 0) {
            $parsed = $fallback;
        }
        return max(3, min(48, $parsed));
    }

    private static function sanitize_opacity_value($value, float $default): float {
        if (!is_numeric($value)) {
            return $default;
        }
        return round(max(0.0, min(1.0, (float) $value)), 3);
    }

    private static function sanitize_angle_value($value, int $default): int {
        if (!is_numeric($value)) {
            return $default;
        }
        return max(0, min(360, (int) $value));
    }

    private static function sanitize_relative_url(string $value): string {
        $value = trim($value);
        if ($value === '') {
            return '';
        }
        if (preg_match('#^(https?:)?//#i', $value)) {
            return esc_url_raw($value);
        }
        if ($value[0] !== '/') {
            $value = '/' . ltrim($value, '/');
        }
        return esc_url_raw($value);
    }
}
