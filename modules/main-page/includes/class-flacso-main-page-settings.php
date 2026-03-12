<?php

if (!defined('ABSPATH')) {
    exit;
}

class Flacso_Main_Page_Settings {
    const OPTION_KEY = 'flacso-main-page_settings';
    private const SECTION_KEYS = [
        'hero',
        'eventos',
        'novedades_destacadas',
        'novedades_busqueda',
        'novedades',
        'seminarios',
        'quienes',
        'instagram',
        'posgrados',
        // REMOVED: 'oferta_academica' - Movido a plugin separado flacso-formacion
        'congreso',
        'contacto',
    ];
    private const HOMEPAGE_SECTION_KEYS = [
        'hero',
        'novedades_destacadas',
        'eventos',
        'seminarios',
        'novedades_busqueda',
        'novedades',
        'quienes',
        'instagram',
        'posgrados',
        'congreso',
        'contacto',
    ];
    private static $section_visibility_cache;
    private static $settings_cache;
    private static $defaults_cache;
    private const SECTION_HEADING_COLOR_CHOICES = ['primary', 'palette7'];

    public static function invalidate_cache(): void {
        self::$section_visibility_cache = null;
        self::$settings_cache = null;
        self::$defaults_cache = null;
    }

    public static function get_hero_button_defaults(): array {
        return [
            [
                'label' => 'Ver Oferta Acad?mica',
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
                'label' => 'Solicite Informaci?n',
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
                'title' => 'Inscripciones 2026 Abiertas: Maestr?as, Especializaciones, Diplomados y Diplomas',
                'subtitle' => 'Sumate a los posgrados de FLACSO Uruguay.',
                'background_image' => 'https://flacso.edu.uy/wp-content/uploads/2025/11/primer-plano-de-ejecutivos-de-negocios-en-la-oficina-scaled.jpg',
                'primary_label' => 'Ver Oferta Acad?mica',
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
            'posgrados' => [
                'show_title' => true,
                'title' => 'NUESTROS POSGRADOS',
                'intro' => '<strong>FLACSO Uruguay</strong> brinda formaciones en diversos niveles: <strong>Seminarios, Diplomas, Diplomados, Especializaciones y Maestr?as</strong>. Todas las propuestas est?n pensadas desde el <strong>abordaje te?rico y pr?ctico de los problemas de las ciencias sociales</strong>. Todas las propuestas acad?micas poseen flexibilidad en la modalidad de ense?anza y <strong>seguimiento de profesionales especializados</strong> en los temas abordados.',
                'cards' => [
                    [
                        'key' => 'maestrias',
                        'title' => 'Maestr?as',
                        'type' => 'Maestr?a',
                        'url' => '/formacion/maestrias/',
                        'image' => 'https://flacso.edu.uy/wp-content/uploads/2023/08/IMAGE-SITIO-WEB-9.png',
                        'desc' => 'Una maestr?a es una oportunidad de crecimiento profesional y acad?mico. Todas las maestr?as tienen m?nimo 18 meses de cursada y terminan en un trabajo de investigaci?n. Una maestr?a es un paso necesario para cursar un doctorado.',
                    ],
                    [
                        'key' => 'especializaciones',
                        'title' => 'Especializaciones',
                        'type' => 'Especializaci?n',
                        'url' => '/formacion/especializaciones/',
                        'image' => 'https://flacso.edu.uy/wp-content/uploads/2023/08/IMAGE-SITIO-WEB-1.png',
                        'desc' => 'La Especializaci?n es el grado acad?mico previo a la Maestr?a. Es una oportunidad de formaci?n que permite la profundizaci?n y actualizaci?n de los marcos te?ricos, incorporaci?n de metodolog?as y herramientas en un tiempo m?s corto que una Maestr?a.',
                    ],
                    [
                        'key' => 'diplomas',
                        'title' => 'Diplomas',
                        'type' => 'Diploma',
                        'url' => '/formacion/diplomas/',
                        'image' => 'https://flacso.edu.uy/wp-content/uploads/2023/08/IMAGE-SITIO-WEB-5-1024x1024.png',
                        'desc' => 'Los diplomas representan propuestas de formaci?n que sirven como salidas intermedias hacia programas acad?micos de mayor grado. Combinan el an?lisis de tem?ticas relevantes y la adquisici?n de habilidades pr?cticas.',
                    ],
                    [
                        'key' => 'diplomados',
                        'title' => 'Diplomados',
                        'type' => 'Diplomado',
                        'url' => '/formacion/diplomados/',
                        'image' => 'https://flacso.edu.uy/wp-content/uploads/2023/08/IMAGE-SITIO-WEB-3.png',
                        'desc' => 'Grado acad?mico similar al de la Especializaci?n, expedido por la unidad acad?mica. A trav?s de seminarios tem?ticos, metodol?gicos y talleres pr?cticos, prepara a cursantes para continuar hacia Maestr?as.',
                    ],
                    [
                        'key' => 'seminarios',
                        'title' => 'Seminarios',
                        'type' => 'Seminario',
                        'url' => '/formacion/seminarios/',
                        'image' => 'https://flacso.edu.uy/wp-content/uploads/2023/08/IMAGE-SITIO-WEB-2.png',
                        'desc' => 'Espacios de formaci?n intensiva y enfoque pr?ctico, con actualizaci?n tem?tica y acompa?amiento docente especializado.',
                    ],
                ],
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
            'contacto' => [
                'title' => 'Cont?ctanos',
                'subtitle' => 'Con gusto responderemos todas tus consultas',
                'cta_label' => 'Solicite Informaci?n',
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
            'quienes' => [
                'title' => '?Qu? es FLACSO Uruguay?',
                'content' => '<strong>FLACSO Uruguay</strong> es la sede uruguaya de la <strong>Facultad Latinoamericana de Ciencias Sociales</strong>, una de las principales <strong>redes acad?micas</strong> de <strong>Am?rica Latina y el Caribe</strong>, con presencia en m?s de <strong>20 pa?ses</strong> de la regi?n. Su objetivo principal es <strong>contribuir al desarrollo de la regi?n</strong> mediante la <strong>formaci?n de profesionales</strong>, la <strong>investigaci?n cr?tica</strong> y la <strong>transferencia de conocimiento</strong>.<br><br><strong>FLACSO Uruguay</strong> ofrece <strong>programas de posgrado</strong> en diversas ?reas de las <strong>ciencias sociales</strong> y desarrolla <strong>proyectos de investigaci?n</strong> en temas como <strong>g?nero</strong>, <strong>pol?tica p?blica</strong>, <strong>medio ambiente</strong>, <strong>desarrollo humano</strong> y <strong>cultura</strong>, con un enfoque <strong>interdisciplinario</strong> y un compromiso con la <strong>transformaci?n social</strong>.',
                'cta_label' => 'Conocer m?s',
                'cta_url' => '/sobre-nosotros/',
                'background_image' => 'https://www.flacso.org/assets/img/banner/banner-01.jpg',
                'highlight_color' => '#fcd116',
            ],
            'novedades' => [
                'per_page' => 12,
            ],
            // REMOVED: 'oferta_academica' - Movido a plugin separado flacso-formacion
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
        $defaults = self::get_defaults();
        self::$settings_cache = self::normalize_settings(wp_parse_args($saved, $defaults));
        return self::$settings_cache;
    }

    private static function normalize_settings(array $settings): array {
        if (!isset($settings['posgrados']) || !is_array($settings['posgrados'])) {
            return $settings;
        }

        if (!isset($settings['posgrados']['cards']) || !is_array($settings['posgrados']['cards'])) {
            return $settings;
        }

        foreach ($settings['posgrados']['cards'] as $index => $card) {
            if (!is_array($card)) {
                continue;
            }
            $settings['posgrados']['cards'][$index] = self::normalize_posgrados_card($card);
        }

        return $settings;
    }

    private static function normalize_posgrados_card(array $card): array {
        $key = sanitize_key((string) ($card['key'] ?? ''));
        $title = strtolower(remove_accents(trim((string) ($card['title'] ?? $card['titulo'] ?? ''))));
        $type = strtolower(remove_accents(trim((string) ($card['type'] ?? $card['tipo'] ?? ''))));
        $url = (string) ($card['url'] ?? '');

        $is_course_card = $key === 'cursos'
            || $title === 'cursos'
            || $type === 'curso'
            || strpos($url, '/cursos/') !== false;

        if (!$is_course_card) {
            return $card;
        }

        $card['key'] = 'seminarios';
        $card['title'] = 'Seminarios';
        $card['type'] = 'Seminario';
        $card['url'] = '/formacion/seminarios/';
        $card['desc'] = 'Espacios de formaci?n intensiva y enfoque pr?ctico, con actualizaci?n tem?tica y acompa?amiento docente especializado.';

        return $card;
    }

    public static function get_section(string $section): array {
        $settings = self::get_settings();
        $defaults = self::get_defaults();
        
        if (isset($settings[$section]) && is_array($settings[$section])) {
            return wp_parse_args($settings[$section], $defaults[$section] ?? []);
        }
        
        return $defaults[$section] ?? [];
    }

    public static function get_value(string $section, string $key, $fallback = '') {
        $section_data = self::get_section($section);
        return $section_data[$key] ?? $fallback;
    }

    public static function get_section_visibility_defaults(): array {
        return array_fill_keys(self::SECTION_KEYS, true);
    }

    public static function get_section_visibility(): array {
        if (self::$section_visibility_cache !== null) {
            return self::$section_visibility_cache;
        }

        $settings = self::get_settings();
        $defaults = self::get_section_visibility_defaults();

        if (isset($settings['sections_visibility']) && is_array($settings['sections_visibility'])) {
            self::$section_visibility_cache = wp_parse_args($settings['sections_visibility'], $defaults);
        } else {
            self::$section_visibility_cache = $defaults;
        }

        return self::$section_visibility_cache;
    }

    public static function is_section_visible(string $key): bool {
        $visibility = self::get_section_visibility();
        if (!array_key_exists($key, $visibility)) {
            return true;
        }
        return (bool) $visibility[$key];
    }

    public static function get_section_heading_color_choice(string $section = ''): string {
        $settings = self::get_settings();
        $defaults = self::get_defaults();
        $base = $settings['section_heading_color'] ?? $defaults['section_heading_color'];
        $base = in_array($base, self::SECTION_HEADING_COLOR_CHOICES, true) ? $base : $defaults['section_heading_color'];
        if ($section === '') {
            return $base;
        }
        $colors = self::get_section_heading_colors();
        return isset($colors[$section]) ? $colors[$section] : $base;
    }

    public static function get_section_heading_colors(): array {
        $settings = self::get_settings();
        $defaults = self::get_defaults();
        $base_choice = $settings['section_heading_color'] ?? $defaults['section_heading_color'];
        if (!in_array($base_choice, self::SECTION_HEADING_COLOR_CHOICES, true)) {
            $base_choice = $defaults['section_heading_color'];
        }
        $result = array_fill_keys(self::HOMEPAGE_SECTION_KEYS, $base_choice);
        if (isset($settings['section_heading_colors']) && is_array($settings['section_heading_colors'])) {
            foreach ($settings['section_heading_colors'] as $section_key => $choice) {
                $section_key = sanitize_key((string) $section_key);
                $choice = sanitize_key((string) $choice);
                if (!in_array($section_key, self::HOMEPAGE_SECTION_KEYS, true)) {
                    continue;
                }
                if (!in_array($choice, self::SECTION_HEADING_COLOR_CHOICES, true)) {
                    continue;
                }
                $result[$section_key] = $choice;
            }
        }
        return $result;
    }

    public static function get_section_label(string $key): string {
        $labels = [
            'hero' => __('Hero principal', 'flacso-main-page'),
            'eventos' => __('Eventos', 'flacso-main-page'),
            'novedades_destacadas' => __('Novedades destacadas', 'flacso-main-page'),
            'novedades_busqueda' => __('Buscador de novedades', 'flacso-main-page'),
            'novedades' => __('Novedades', 'flacso-main-page'),
            'seminarios' => __('Seminarios', 'flacso-main-page'),
            'quienes' => __('Qui?nes somos', 'flacso-main-page'),
            'instagram' => __('Instagram', 'flacso-main-page'),
            'posgrados' => __('Posgrados', 'flacso-main-page'),
            'congreso' => __('Congreso', 'flacso-main-page'),
            'contacto' => __('Contacto', 'flacso-main-page'),
        ];

        return $labels[$key] ?? ucfirst(str_replace('_', ' ', $key));
    }

    public static function get_homepage_section_order_defaults(): array {
        return [
            'hero',
            'novedades_destacadas',
            'eventos',
            'novedades_busqueda',
            'novedades',
            'seminarios',
            'quienes',
            'instagram',
            'posgrados',
            'contacto',
            'congreso',
        ];
    }

    public static function get_homepage_section_order(): array {
        // Order is hardcoded and not configurable
        return self::get_homepage_section_order_defaults();
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

    public static function sanitize_homepage_section_order(array $input): array {
        $defaults = self::get_homepage_section_order_defaults();
        $order = [];
        // Asegurar que hero siempre est? presente y al inicio
        $input = array_values(array_filter($input, static function ($value) {
            return is_scalar($value);
        }));
        // Primero recolectar sin hero, luego se agrega al inicio
        $input = array_map('sanitize_key', $input);

        foreach ($input as $value) {
            $key = (string) $value;
            if (!in_array($key, $defaults, true)) {
                continue;
            }
            if (in_array($key, $order, true)) {
                continue;
            }
            $order[] = $key;
        }

        // Remover hero de donde est? y ponerlo primero
        $order = array_values(array_diff($order, ['hero']));
        array_unshift($order, 'hero');

        foreach ($defaults as $default_key) {
            if (!in_array($default_key, $order, true)) {
                $order[] = $default_key;
            }
        }

        return $order;
    }

    public static function sanitize(array $input): array {
        self::$section_visibility_cache = null;
        self::$settings_cache = null;
        $defaults = self::get_defaults();
        $output = $defaults;

        if (isset($input['hero']) && is_array($input['hero'])) {
            $hero = $input['hero'];
            $output['hero']['title'] = wp_kses_post($hero['title'] ?? $defaults['hero']['title']);
            $output['hero']['subtitle'] = wp_kses_post($hero['subtitle'] ?? $defaults['hero']['subtitle']);
            $output['hero']['background_image'] = esc_url_raw($hero['background_image'] ?? $defaults['hero']['background_image']);
            $output['hero']['primary_label'] = wp_kses_post($hero['primary_label'] ?? $defaults['hero']['primary_label']);
            $output['hero']['primary_url'] = self::sanitize_relative_url($hero['primary_url'] ?? $defaults['hero']['primary_url']);
            $output['hero']['secondary_label'] = wp_kses_post($hero['secondary_label'] ?? $defaults['hero']['secondary_label']);
            $output['hero']['secondary_url'] = self::sanitize_relative_url($hero['secondary_url'] ?? $defaults['hero']['secondary_url']);
            $output['hero']['bubble_primary_label'] = wp_kses_post($hero['bubble_primary_label'] ?? $defaults['hero']['bubble_primary_label']);
            $output['hero']['bubble_primary_url'] = self::sanitize_relative_url($hero['bubble_primary_url'] ?? $defaults['hero']['bubble_primary_url']);
            $output['hero']['bubble_secondary_label'] = wp_kses_post($hero['bubble_secondary_label'] ?? $defaults['hero']['bubble_secondary_label']);
            $output['hero']['bubble_secondary_url'] = self::sanitize_relative_url($hero['bubble_secondary_url'] ?? $defaults['hero']['bubble_secondary_url']);
            $output['hero']['bubble_primary_enabled'] = !empty($hero['bubble_primary_enabled']);
            $output['hero']['bubble_secondary_enabled'] = !empty($hero['bubble_secondary_enabled']);
            $output['hero']['show_buttons'] = array_key_exists('show_buttons', $hero) ? !empty($hero['show_buttons']) : $defaults['hero']['show_buttons'];
            $style_keys = array_keys(self::get_button_style_options());
            $primary_style = $hero['bubble_primary_style'] ?? $defaults['hero']['bubble_primary_style'];
            $secondary_style = $hero['bubble_secondary_style'] ?? $defaults['hero']['bubble_secondary_style'];
            $output['hero']['bubble_primary_style'] = in_array($primary_style, $style_keys, true) ? $primary_style : $defaults['hero']['bubble_primary_style'];
            $output['hero']['bubble_secondary_style'] = in_array($secondary_style, $style_keys, true) ? $secondary_style : $defaults['hero']['bubble_secondary_style'];

            $button_defaults = self::get_hero_button_defaults();
            $buttons_input = $hero['buttons'] ?? [];
            if (empty($buttons_input)) {
                $buttons_input = $button_defaults;
                $buttons_input[0]['label'] = $hero['primary_label'] ?? $button_defaults[0]['label'];
                $buttons_input[0]['url'] = $hero['primary_url'] ?? $button_defaults[0]['url'];
                $buttons_input[1]['label'] = $hero['secondary_label'] ?? $button_defaults[1]['label'];
                $buttons_input[1]['url'] = $hero['secondary_url'] ?? $button_defaults[1]['url'];
                $buttons_input[0]['enabled'] = true;
                $buttons_input[1]['enabled'] = !empty($hero['secondary_label']) || !empty($hero['secondary_url']);
            }

            $allowed_styles = ['primary', 'outline', 'light', 'ghost'];
            $output['hero']['buttons'] = [];
            foreach ($button_defaults as $index => $button_default) {
                $button_input = $buttons_input[$index] ?? $button_default;
                $style = sanitize_key($button_input['style'] ?? $button_default['style']);
                if (!in_array($style, $allowed_styles, true)) {
                    $style = $button_default['style'];
                }
                $output['hero']['buttons'][$index] = [
                    'label' => wp_kses_post($button_input['label'] ?? $button_default['label']),
                    'url' => self::sanitize_relative_url($button_input['url'] ?? $button_default['url']),
                    'style' => $style,
                    'enabled' => !empty($button_input['enabled']),
                ];
            }

            $first_btn = $output['hero']['buttons'][0] ?? $button_defaults[0];
            $second_btn = $output['hero']['buttons'][1] ?? $button_defaults[1];
            $output['hero']['primary_label'] = $first_btn['label'];
            $output['hero']['primary_url'] = $first_btn['url'];
            $output['hero']['secondary_label'] = $second_btn['label'];
            $output['hero']['secondary_url'] = $second_btn['url'];
        }

        if (isset($input['sections_visibility']) && is_array($input['sections_visibility'])) {
            $visibility = $input['sections_visibility'];
            foreach (self::get_section_visibility_defaults() as $section_key => $default_state) {
                $output['sections_visibility'][$section_key] = !empty($visibility[$section_key]);
            }
        }

        if (isset($input['sections_order']) && is_array($input['sections_order'])) {
            $output['sections_order'] = self::sanitize_homepage_section_order($input['sections_order']);
        }

        if (isset($input['section_heading_color'])) {
            $color_choice = sanitize_key($input['section_heading_color']);
            $output['section_heading_color'] = in_array($color_choice, self::SECTION_HEADING_COLOR_CHOICES, true)
                ? $color_choice
                : $defaults['section_heading_color'];
        }

        if (isset($input['section_heading_colors']) && is_array($input['section_heading_colors'])) {
            $colors_input = $input['section_heading_colors'];
            $output['section_heading_colors'] = [];
            foreach (self::HOMEPAGE_SECTION_KEYS as $section_key) {
                if (!isset($colors_input[$section_key])) {
                    continue;
                }
                $choice = sanitize_key($colors_input[$section_key]);
                if (!in_array($choice, self::SECTION_HEADING_COLOR_CHOICES, true)) {
                    continue;
                }
                $output['section_heading_colors'][$section_key] = $choice;
            }
        }

        if (isset($input['posgrados']) && is_array($input['posgrados'])) {
            $pos = $input['posgrados'];
            $output['posgrados']['show_title'] = !empty($pos['show_title']);
            $output['posgrados']['title'] = sanitize_text_field($pos['title'] ?? $defaults['posgrados']['title']);
            $output['posgrados']['intro'] = wp_kses_post($pos['intro'] ?? $defaults['posgrados']['intro']);

            if (!empty($pos['cards']) && is_array($pos['cards'])) {
                foreach ($defaults['posgrados']['cards'] as $index => $card_defaults) {
                    $card_input = $pos['cards'][$index] ?? [];
                    $output['posgrados']['cards'][$index] = [
                        'key' => $card_defaults['key'],
                        'title' => sanitize_text_field($card_input['title'] ?? $card_defaults['title']),
                        'type' => sanitize_text_field($card_input['type'] ?? $card_defaults['type']),
                        'url' => self::sanitize_relative_url($card_input['url'] ?? $card_defaults['url']),
                        'image' => esc_url_raw($card_input['image'] ?? $card_defaults['image']),
                        'desc' => wp_kses_post($card_input['desc'] ?? $card_defaults['desc']),
                    ];
                }
            }
        }

        // Oferta Acad?mica
        if (isset($input['oferta_academica']) && is_array($input['oferta_academica'])) {
            $oa = $input['oferta_academica'];
            $output['oferta_academica']['show_filters'] = !empty($oa['show_filters']);
            $output['oferta_academica']['show_maestrias'] = !empty($oa['show_maestrias']);
            $output['oferta_academica']['show_especializaciones'] = !empty($oa['show_especializaciones']);
            $output['oferta_academica']['show_diplomados'] = !empty($oa['show_diplomados']);
            $output['oferta_academica']['show_diplomas'] = !empty($oa['show_diplomas']);
            $output['oferta_academica']['show_seminarios'] = !empty($oa['show_seminarios']);
            $output['oferta_academica']['show_inactivos'] = !empty($oa['show_inactivos']);
            $limit = isset($oa['seminarios_limit']) ? intval($oa['seminarios_limit']) : $defaults['oferta_academica']['seminarios_limit'];
            if ($limit < 1) { $limit = 1; }
            if ($limit > 50) { $limit = 50; }
            $output['oferta_academica']['seminarios_limit'] = $limit;
        }

        if (isset($input['novedades']) && is_array($input['novedades'])) {
            $novedades = $input['novedades'];
            $per_page = absint($novedades['per_page'] ?? $defaults['novedades']['per_page']);
            $per_page = max(3, min(48, $per_page));
            $output['novedades']['per_page'] = $per_page;
        }

        if (isset($input['congreso']) && is_array($input['congreso'])) {
            $congreso = $input['congreso'];
            $output['congreso']['title'] = wp_kses_post($congreso['title'] ?? $defaults['congreso']['title']);
            $output['congreso']['content'] = wp_kses_post($congreso['content'] ?? $defaults['congreso']['content']);
            $output['congreso']['cta_label'] = wp_kses_post($congreso['cta_label'] ?? $defaults['congreso']['cta_label']);
            $output['congreso']['cta_url'] = self::sanitize_relative_url($congreso['cta_url'] ?? $defaults['congreso']['cta_url']);
            $output['congreso']['background_image'] = esc_url_raw($congreso['background_image'] ?? $defaults['congreso']['background_image']);
        }

        if (isset($input['contacto']) && is_array($input['contacto'])) {
            $contacto = $input['contacto'];
            $output['contacto']['title'] = wp_kses_post($contacto['title'] ?? $defaults['contacto']['title']);
            $output['contacto']['subtitle'] = wp_kses_post($contacto['subtitle'] ?? $defaults['contacto']['subtitle']);
            $output['contacto']['cta_label'] = wp_kses_post($contacto['cta_label'] ?? $defaults['contacto']['cta_label']);
            $output['contacto']['cta_url'] = self::sanitize_relative_url($contacto['cta_url'] ?? $defaults['contacto']['cta_url']);
            $output['contacto']['background_image'] = esc_url_raw($contacto['background_image'] ?? $defaults['contacto']['background_image']);
            $background_color = sanitize_hex_color($contacto['background_color'] ?? $defaults['contacto']['background_color']);
            $output['contacto']['background_color'] = $background_color ?: $defaults['contacto']['background_color'];

            $mode_choices = ['color', 'gradient', 'image', 'image_overlay'];
            $mode_value = sanitize_key($contacto['background_mode'] ?? $defaults['contacto']['background_mode']);
            $output['contacto']['background_mode'] = in_array($mode_value, $mode_choices, true)
                ? $mode_value
                : $defaults['contacto']['background_mode'];

            $gradient_start = sanitize_hex_color($contacto['background_gradient_start'] ?? $defaults['contacto']['background_gradient_start']);
            $gradient_end = sanitize_hex_color($contacto['background_gradient_end'] ?? $defaults['contacto']['background_gradient_end']);
            $output['contacto']['background_gradient_start'] = $gradient_start ?: $defaults['contacto']['background_gradient_start'];
            $output['contacto']['background_gradient_end'] = $gradient_end ?: $defaults['contacto']['background_gradient_end'];
            $gradient_angle_input = $contacto['background_gradient_angle'] ?? $defaults['contacto']['background_gradient_angle'];
            $output['contacto']['background_gradient_angle'] = self::sanitize_angle_value($gradient_angle_input, (int) $defaults['contacto']['background_gradient_angle']);

            $overlay_styles = ['solid', 'gradient'];
            $overlay_style = sanitize_key($contacto['background_overlay_style'] ?? $defaults['contacto']['background_overlay_style']);
            $output['contacto']['background_overlay_style'] = in_array($overlay_style, $overlay_styles, true)
                ? $overlay_style
                : $defaults['contacto']['background_overlay_style'];

            $overlay_color = sanitize_hex_color($contacto['background_overlay_color'] ?? $defaults['contacto']['background_overlay_color']);
            $overlay_color = $overlay_color ?: $defaults['contacto']['background_overlay_color'];
            $output['contacto']['background_overlay_color'] = $overlay_color;

            $overlay_color_secondary = sanitize_hex_color($contacto['background_overlay_color_secondary'] ?? $defaults['contacto']['background_overlay_color_secondary']);
            if (!$overlay_color_secondary) {
                $overlay_color_secondary = $overlay_color;
            }
            $output['contacto']['background_overlay_color_secondary'] = $overlay_color_secondary;

            $overlay_opacity_input = $contacto['background_overlay_opacity'] ?? $defaults['contacto']['background_overlay_opacity'];
            $output['contacto']['background_overlay_opacity'] = self::sanitize_opacity_value($overlay_opacity_input, (float) $defaults['contacto']['background_overlay_opacity']);

            $overlay_opacity_secondary_input = $contacto['background_overlay_opacity_secondary'] ?? $defaults['contacto']['background_overlay_opacity_secondary'];
            $output['contacto']['background_overlay_opacity_secondary'] = self::sanitize_opacity_value($overlay_opacity_secondary_input, (float) $defaults['contacto']['background_overlay_opacity_secondary']);

            $overlay_angle_input = $contacto['background_overlay_angle'] ?? $defaults['contacto']['background_overlay_angle'];
            $output['contacto']['background_overlay_angle'] = self::sanitize_angle_value($overlay_angle_input, (int) $defaults['contacto']['background_overlay_angle']);
        }

        if (isset($input['quienes']) && is_array($input['quienes'])) {
            $quienes = $input['quienes'];
            $output['quienes']['title'] = wp_kses_post($quienes['title'] ?? $defaults['quienes']['title']);
            $output['quienes']['content'] = wp_kses_post($quienes['content'] ?? $defaults['quienes']['content']);
            $output['quienes']['cta_label'] = wp_kses_post($quienes['cta_label'] ?? $defaults['quienes']['cta_label']);
            $output['quienes']['cta_url'] = self::sanitize_relative_url($quienes['cta_url'] ?? $defaults['quienes']['cta_url']);
            $output['quienes']['background_image'] = esc_url_raw($quienes['background_image'] ?? $defaults['quienes']['background_image']);
            $output['quienes']['highlight_color'] = sanitize_hex_color($quienes['highlight_color'] ?? $defaults['quienes']['highlight_color']);
        }

        return $output;
    }

    private static function sanitize_opacity_value($value, float $default): float {
        if (!is_numeric($value)) {
            return $default;
        }
        $value = (float) $value;
        if ($value < 0) {
            $value = 0.0;
        }
        if ($value > 1) {
            $value = 1.0;
        }
        return round($value, 3);
    }

    private static function sanitize_angle_value($value, int $default): int {
        if (!is_numeric($value)) {
            return $default;
        }
        $value = (int) $value;
        if ($value < 0) {
            $value = 0;
        }
        if ($value > 360) {
            $value = 360;
        }
        return $value;
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


