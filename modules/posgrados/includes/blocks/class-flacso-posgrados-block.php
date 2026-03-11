<?php

if (!class_exists('FLACSO_Posgrados_Block')) {
    class FLACSO_Posgrados_Block {
        private const DEFAULT_DATE_FORMAT = 'j \\d\\e F \\d\\e Y';
        private const DEFAULT_IMAGE_SIZE  = 'large';
        private const PROXIMO_INICIO_DATE_FORMAT = 'd \\d\\e F \\d\\e Y';
        private static $frontend_style_enqueued = false;

        private const BLOCKS = [
            'dato-resumen' => [
                'title'       => 'Posgrado: Resumen',
                'description' => 'Muestra el extracto/resumen del posgrado.',
                'field'       => 'resumen',
                'type'        => 'html',
                'label'       => 'Resumen',
                'icon'        => 'editor-paragraph',
            ],
            'dato-duracion' => [
                'title'       => 'Posgrado: Duracion',
                'description' => 'Duracion del posgrado.',
                'field'       => 'duracion',
                'type'        => 'text',
                'label'       => 'Duracion',
                'icon'        => 'clock',
            ],
            'dato-abreviacion' => [
                'title'       => 'Posgrado: Abreviacion',
                'description' => 'Abreviacion del programa.',
                'field'       => 'abreviacion',
                'type'        => 'text',
                'label'       => 'Abreviacion',
                'icon'        => 'tag',
            ],
            'dato-tipo' => [
                'title'       => 'Posgrado: Tipo',
                'description' => 'Tipo de posgrado.',
                'field'       => 'tipo',
                'type'        => 'text',
                'label'       => 'Tipo',
                'icon'        => 'welcome-learn-more',
            ],
            'dato-proximo-inicio' => [
                'title'       => 'Posgrado: Proximo inicio',
                'description' => 'Proximo inicio disponible.',
                'field'       => 'proximo_inicio',
                'type'        => 'date',
                'label'       => 'Próximo inicio',
                'icon'        => 'calendar',
                'template'    => 'proximo_banner',
                'bypass_wrapper' => true,
            ],
            'dato-calendario' => [
                'title'       => 'Posgrado: Calendario',
                'description' => 'Link y año del calendario académico.',
                'field'       => 'calendario',
                'type'        => 'calendario',
                'label'       => 'Calendario',
                'icon'        => 'calendar-alt',
                'link_text'   => 'Ver calendario',
            ],
            'dato-malla-link' => [
                'title'        => 'Posgrado: Link Malla Curricular',
                'description'  => 'Enlace hacia la malla curricular.',
                'field'        => 'malla_curricular_link',
                'type'         => 'url',
                'label'        => 'Malla Curricular',
                'icon'         => 'media-document',
                'link_text'    => 'Ver malla curricular',
            ],
            'dato-link' => [
                'title'        => 'Posgrado: Link Principal',
                'description'  => 'Link principal del posgrado.',
                'field'        => 'link',
                'type'         => 'url',
                'label'        => 'Información del programa',
                'icon'         => 'admin-links',
                'link_text'    => 'Ver detalles',
            ],
            'dato-imagen-promocional' => [
                'title'       => 'Posgrado: Imagen Promocional',
                'description' => 'Imagen seleccionada en la tabla administrativa.',
                'field'       => 'imagen',
                'type'        => 'image',
                'label'       => 'Imagen promocional',
                'icon'        => 'format-image',
            ],
            'dato-estado' => [
                'title'       => 'Posgrado: Estado (Activo/Inactivo)',
                'description' => 'Indica si el posgrado esta activo.',
                'field'       => 'activo',
                'type'        => 'status',
                'label'       => 'Estado',
                'icon'        => 'flag',
            ],
            'cta-inscripcion' => [
                'title'       => 'CTA: Carta y Preinscripcion',
                'description' => 'Botones de acceso directo a Carta del programa y Preinscripción.',
                'field'       => 'cta_pair',
                'type'        => 'cta_pair',
                'label'       => 'Acciones',
                'icon'        => 'button',
                'bypass_wrapper' => true,
            ],
            'cta-carta' => [
                'title'       => 'CTA: Carta del Programa',
                'description' => 'Botón directo a la carta del programa.',
                'field'       => 'cta_carta',
                'type'        => 'cta_single',
                'label'       => 'Carta del programa',
                'icon'        => 'feedback',
                'link_text'   => 'Ver carta',
                'bypass_wrapper' => true,
            ],
            'cta-preinscripcion' => [
                'title'       => 'CTA: Preinscripción',
                'description' => 'Botón directo al formulario de preinscripción.',
                'field'       => 'cta_preinscripcion',
                'type'        => 'cta_single',
                'label'       => 'Preinscripción',
                'icon'        => 'welcome-write-blog',
                'link_text'   => 'Ir a preinscripción',
                'bypass_wrapper' => true,
            ],
        ];

        public static function init(): void {
            add_action('init', [__CLASS__, 'register_blocks']);
        }

        public static function register_blocks(): void {
            $script_relative = 'assets/js/block-datos-posgrado.js';
            $script_path     = FLACSO_POSGRADOS_PLUGIN_PATH . $script_relative;
            $script_url      = FLACSO_POSGRADOS_PLUGIN_URL . $script_relative;
            $version         = file_exists($script_path) ? filemtime($script_path) : time();

            wp_register_script(
                'flacso-posgrados-docentes-block',
                $script_url,
                ['wp-blocks', 'wp-element', 'wp-components', 'wp-i18n', 'wp-editor', 'wp-block-editor', 'wp-server-side-render'],
                $version,
                true
            );

            wp_localize_script('flacso-posgrados-docentes-block', 'FLACSO_POS_BLOCKS', self::get_js_config());

            $style_relative = 'assets/css/blocks-posgrados.css';
            $style_path     = FLACSO_POSGRADOS_PLUGIN_PATH . $style_relative;
            $style_url      = FLACSO_POSGRADOS_PLUGIN_URL . $style_relative;
            $style_version  = file_exists($style_path) ? filemtime($style_path) : $version;

            wp_register_style(
                'flacso-posgrados-docentes-blocks',
                $style_url,
                [],
                $style_version
            );

            foreach (self::BLOCKS as $slug => $config) {
                register_block_type('flacso-uruguay/' . $slug, [
                    'editor_script'   => 'flacso-posgrados-docentes-block',
                    'editor_style'    => 'flacso-posgrados-docentes-blocks',
                    'category'        => 'flacso-uruguay',
                    'render_callback' => function ($attributes) use ($slug) {
                        return FLACSO_Posgrados_Block::render_single_block($slug, (array) $attributes);
                    },
                    'attributes'      => self::get_block_attributes($slug),
                ]);
            }
        }

        private static function get_js_config(): array {
            $blocks = [];

            foreach (self::BLOCKS as $slug => $config) {
                $blocks[] = [
                    'name'        => 'flacso-uruguay/' . $slug,
                    'title'       => $config['title'],
                    'description' => $config['description'],
                    'icon'        => $config['icon'],
                    'type'        => $config['type'],
                    'attributes'  => self::get_block_attributes($slug),
                    'category'    => 'flacso-uruguay',
                    'supports'    => [
                        'dateFormat'   => $config['type'] === 'date',
                        'linkOptions'  => in_array($config['type'], ['url', 'calendario'], true),
                        'imageSize'    => $config['type'] === 'image',
                        'statusLabels' => $config['type'] === 'status',
                    ],
                ];
            }

            return [
                'blocks'           => $blocks,
                'defaultDateFormat'=> self::DEFAULT_DATE_FORMAT,
                'defaultImageSize' => self::DEFAULT_IMAGE_SIZE,
            ];
        }

        private static function get_block_attributes(string $slug): array {
            $config = self::BLOCKS[$slug] ?? [];
            $attributes = [
                'wrapperTag' => [
                    'type'    => 'string',
                    'default' => 'div',
                ],
                'wrapperClass' => [
                    'type'    => 'string',
                    'default' => '',
                ],
                'showLabel' => [
                    'type'    => 'boolean',
                    'default' => false,
                ],
                'customLabel' => [
                    'type'    => 'string',
                    'default' => '',
                ],
            ];

            if (($config['type'] ?? '') === 'date') {
                $attributes['dateFormat'] = [
                    'type'    => 'string',
                    'default' => self::DEFAULT_DATE_FORMAT,
                ];
            }

            if (in_array(($config['type'] ?? ''), ['url', 'calendario', 'cta_single'], true)) {
                $attributes['linkText'] = [
                    'type'    => 'string',
                    'default' => $config['link_text'] ?? 'Ver mas',
                ];
                $attributes['openInNewTab'] = [
                    'type'    => 'boolean',
                    'default' => true,
                ];
            }

            if (($config['type'] ?? '') === 'image') {
                $attributes['imageSize'] = [
                    'type'    => 'string',
                    'default' => self::DEFAULT_IMAGE_SIZE,
                ];
            }

            if (($config['type'] ?? '') === 'status') {
                $attributes['activeLabel'] = [
                    'type'    => 'string',
                    'default' => 'Activo',
                ];
                $attributes['inactiveLabel'] = [
                    'type'    => 'string',
                    'default' => 'Inactivo',
                ];
            }

            return $attributes;
        }

        private static function render_single_block(string $slug, array $attributes): string {
            if (!isset(self::BLOCKS[$slug])) {
                return '';
            }

            $config  = self::BLOCKS[$slug];
            $defaults = [];
            foreach (self::get_block_attributes($slug) as $attr_key => $definition) {
                $defaults[$attr_key] = $definition['default'] ?? '';
            }
            $attributes = wp_parse_args($attributes, $defaults);

            $post_id = self::resolve_context_post_id();
            if (!$post_id) {
                return '';
            }

            if (!is_admin() && self::is_posgrados_context($post_id)) {
                self::maybe_enqueue_frontend_style();
            }

            $data = self::collect_data($post_id);
            if (!$data) {
                return '';
            }

            $value = $data[$config['field']] ?? '';
            if ($config['type'] !== 'status' && self::is_empty_value($value)) {
                return '';
            }

            $content = self::render_value($config, $value, $attributes, $data, $post_id);
            if ($content === '') {
                return '';
            }

            if (!empty($config['bypass_wrapper'])) {
                return $content;
            }

            $wrapper_tag   = self::sanitize_tag($attributes['wrapperTag'] ?? 'div');
            $wrapper_class = self::wrap_classes($slug, $attributes['wrapperClass'] ?? '');
            $label_text    = self::resolve_label($attributes, $config);

            $output  = '<' . $wrapper_tag . ' class="' . esc_attr($wrapper_class) . '">';
            if (!empty($attributes['showLabel']) && $label_text) {
                $output .= '<span class="flacso-pos-label">' . esc_html($label_text) . '</span> ';
            }
            $output .= $content;
            $output .= '</' . $wrapper_tag . '>';

            return $output;
        }

        private static function resolve_context_post_id(): int {
            $post_id = get_the_ID();
            if (!$post_id) {
                return 0;
            }

            $slug = sanitize_title(get_post_field('post_name', $post_id) ?: '');
            if (in_array($slug, ['carta', 'preinscripcion'], true)) {
                $parent_id = (int) wp_get_post_parent_id($post_id);
                if ($parent_id) {
                    return $parent_id;
                }
            }

            return (int) $post_id;
        }

        private static function resolve_label(array $attributes, array $config): string {
            if (!empty($attributes['customLabel'])) {
                return (string) $attributes['customLabel'];
            }
            return $config['label'] ?? '';
        }

        private static function render_value(array $config, $value, array $attributes, array $data, int $post_id): string {
            switch ($config['type']) {
                case 'html':
                    if (self::is_empty_value($value)) {
                        return '';
                    }
                    return '<span class="flacso-pos-value">' . wp_kses_post($value) . '</span>';

                case 'text':
                    if (self::is_empty_value($value)) {
                        return '';
                    }
                    return '<span class="flacso-pos-value">' . esc_html($value) . '</span>';

                case 'date':
                    $is_proximo_banner = (($config['template'] ?? '') === 'proximo_banner');
                    $format = ($config['template'] ?? '') === 'proximo_banner'
                        ? self::PROXIMO_INICIO_DATE_FORMAT
                        : ($attributes['dateFormat'] ?? self::DEFAULT_DATE_FORMAT);
                    $formatted = self::format_date($value, $format);
                    if ($formatted === '') {
                        if ($is_proximo_banner) {
                            $formatted = __('A definir', 'flacso-posgrados-docentes');
                        } else {
                            return '';
                        }
                    }
                    if ($is_proximo_banner) {
                        return self::render_proximo_inicio_banner($formatted, $data);
                    }
                    return '<span class="flacso-pos-value">' . esc_html($formatted) . '</span>';

                case 'url':
                    $url = esc_url($value);
                    if (!$url) {
                        return '';
                    }
                    $text = $attributes['linkText'] ?: ($config['label'] ?? 'Ver mas');
                    $target = !empty($attributes['openInNewTab']) ? ' target="_blank" rel="noopener noreferrer"' : '';
                    return '<a class="flacso-pos-link" href="' . $url . '"' . $target . '>' . esc_html($text) . '</a>';

                case 'calendario':
                    if (!is_array($value) || self::is_empty_value($value)) {
                        return '';
                    }
                    $anio = isset($value['anio']) ? trim((string) $value['anio']) : '';
                    $link = isset($value['link']) ? esc_url($value['link']) : '';

                    $parts = [];
                    if ($anio !== '') {
                        $parts[] = '<span class="flacso-pos-calendario-anio">' . esc_html($anio) . '</span>';
                    }

                    if ($link) {
                        $text   = $attributes['linkText'] ?: ($config['link_text'] ?? __('Ver calendario', 'flacso-posgrados-docentes'));
                        $target = !empty($attributes['openInNewTab']) ? ' target="_blank" rel="noopener noreferrer"' : '';
                        $parts[] = '<a class="flacso-pos-calendario-link" href="' . $link . '"' . $target . '>' . esc_html($text) . '</a>';
                    }

                    if (empty($parts)) {
                        return '';
                    }

                    return '<div class="flacso-pos-calendario">' . implode(' ', $parts) . '</div>';

                case 'image':
                    return self::render_promotional_image((int) $value, $data);

                case 'status':
                    if ($value === '' || $value === null) {
                        return '';
                    }
                    $is_active = self::to_bool($value);
                    $text = $is_active ? ($attributes['activeLabel'] ?: 'Activo') : ($attributes['inactiveLabel'] ?: 'Inactivo');
                    $status_class = $is_active ? 'is-active' : 'is-inactive';
                    return '<span class="flacso-pos-status ' . esc_attr($status_class) . '">' . esc_html($text) . '</span>';

                case 'cta_single':
                    return self::render_cta_single((string) $value, $attributes, $config);

                case 'cta_pair':
                    if (!is_array($value) || empty(array_filter($value))) {
                        return '';
                    }
                    return self::render_cta_pair($value);

                default:
                    return '';
            }
        }

        private static function render_promotional_image(int $attachment_id, array $data): string {
            $post_id = self::resolve_context_post_id();

            if (!$attachment_id && $post_id) {
                $attachment_id = (int) get_post_thumbnail_id($post_id);
            }

            if (!$attachment_id) {
                return '';
            }

            $alt = get_post_meta($attachment_id, '_wp_attachment_image_alt', true);
            if ($alt === '') {
                $alt = get_the_title($attachment_id) ?: '';
            }

            $attributes = [
                'class'   => 'flacso-pos-image',
                'loading' => 'lazy',
            ];

            if ($alt !== '') {
                $attributes['alt'] = $alt;
            }

            $image_html = wp_get_attachment_image(
                $attachment_id,
                self::DEFAULT_IMAGE_SIZE,
                false,
                $attributes
            );

            if (!$image_html) {
                $src = wp_get_attachment_image_url($attachment_id, self::DEFAULT_IMAGE_SIZE);
                if (!$src) {
                    return '';
                }
                $image_html = '<img class="flacso-pos-image" src="' . esc_url($src) . '" alt="' . esc_attr($alt) . '" loading="lazy">';
            }

            $link = !empty($data['permalink']) ? esc_url($data['permalink']) : '';
            if ($link) {
                $image_html = '<a class="flacso-pos-image-link" href="' . $link . '">' . $image_html . '</a>';
            }

            return '<div class="flacso-pos-image-wrapper">' . $image_html . '</div>';
        }

        private static function render_cta_single(string $url, array $attributes, array $config): string {
            $url = esc_url($url);
            if (!$url) {
                return '';
            }

            $text = $attributes['linkText'] ?: ($config['link_text'] ?? __('Abrir enlace', 'flacso-posgrados-docentes'));
            $target = !empty($attributes['openInNewTab']) ? ' target="_blank" rel="noopener noreferrer"' : '';

            return '<div class="flacso-pos-cta-single"><a class="flacso-pos-cta button button-primary" href="' . $url . '"' . $target . '>' . esc_html($text) . '</a></div>';
        }

        private static function render_cta_pair(array $value): string {
            $buttons = [];

            if (!empty($value['carta'])) {
                $buttons[] = self::render_cta_button($value['carta'], __('Carta del programa', 'flacso-posgrados-docentes'));
            }

            if (!empty($value['preinscripcion'])) {
                $buttons[] = self::render_cta_button($value['preinscripcion'], __('Preinscripción', 'flacso-posgrados-docentes'));
            }

            if (!$buttons) {
                return '';
            }

            return '<div class="flacso-pos-cta-pair">' . implode('', $buttons) . '</div>';
        }

        private static function render_cta_button(string $url, string $label): string {
            $url = esc_url($url);
            if (!$url) {
                return '';
            }

            return '<a class="flacso-pos-cta button button-secondary" href="' . $url . '" target="_blank" rel="noopener noreferrer">' . esc_html($label) . '</a>';
        }

        private static function render_proximo_inicio_banner(string $formatted): string {
            $heading = esc_html__('Próximo inicio:', 'flacso-posgrados-docentes');
            $paragraph = '<p class="has-text-align-center has-theme-palette-9-color has-text-color has-large-font-size"><strong>'
                . $heading
                . '</strong> '
                . esc_html($formatted)
                . '</p>';

            $inner = '<div class="kt-row-layout-inner flacso-proximo-inicio-row__inner" style="max-width:var(--wp--style--global--content-size,1200px);margin:0 auto;padding:24px 16px;">'
                . '<div class="wp-block-kadence-column kadence-column-flacso-proximo inner-column-1">'
                . '<div class="kt-inside-inner-col">'
                . $paragraph
                . '</div>'
                . '</div>'
                . '</div>';

            return '<div class="wp-block-kadence-rowlayout alignfull flacso-proximo-inicio-row" style="background-color:#163970;margin-top:var(--wp--preset--spacing--50,40px);margin-bottom:var(--wp--preset--spacing--50,40px);">'
                . $inner
                . '</div>';
        }

        private static function maybe_enqueue_frontend_style(): void {
            if (is_admin() || self::$frontend_style_enqueued) {
                return;
            }

            wp_enqueue_style('flacso-posgrados-docentes-blocks');
            self::$frontend_style_enqueued = true;
        }

        private static function is_posgrados_context(int $post_id): bool {
            if (!$post_id) {
                return false;
            }

            if (!class_exists('FLACSO_Posgrados_Pages')) {
                return true;
            }

            $ancestors = array_map('intval', get_post_ancestors($post_id));
            $context   = array_merge([(int) $post_id], $ancestors);

            $root_id     = (int) FLACSO_Posgrados_Pages::ROOT_PAGE_ID;
            $excluded_id = (int) FLACSO_Posgrados_Pages::EXCLUDED_BRANCH_ID;

            if ($root_id && in_array($root_id, $context, true)) {
                if ($excluded_id && in_array($excluded_id, $context, true)) {
                    return false;
                }
                return true;
            }

            $allowed = array_map('intval', FLACSO_Posgrados_Pages::get_allowed_page_ids());
            return in_array((int) $post_id, $allowed, true);
        }

        private static function collect_data(int $post_id): array {
            $oferta_id = self::resolve_oferta_post_id($post_id);
            $meta_source_id = $oferta_id ?: $post_id;
            $tipo = get_post_meta($post_id, 'tipo_posgrado', true);
            if (!$tipo) {
                $parent_id = (int) get_post_field('post_parent', $post_id);
                if ($parent_id) {
                    $title = get_the_title($parent_id);
                    if (in_array($title, FLACSO_Posgrados_Fields::allowed_tipos(), true)) {
                        $tipo = $title;
                    }
                }
            }

            $calendario_anio = get_post_meta($meta_source_id, 'calendario_anio', true);
            $calendario_link = get_post_meta($meta_source_id, 'calendario_link', true);

            $permalink = get_permalink($post_id);
            $custom_link = trim((string) get_post_meta($post_id, 'link', true));
            $primary_link = $custom_link !== '' ? esc_url_raw($custom_link) : $permalink;
            $carta_link = self::get_child_permalink($post_id, 'carta');
            $pre_link   = self::get_child_permalink($post_id, 'preinscripcion');

            $cta_carta = $carta_link ?: ($permalink ? trailingslashit($permalink) . 'carta/' : '');
            $cta_pre   = $pre_link ?: ($permalink ? trailingslashit($permalink) . 'preinscripcion/' : '');

            return [
                'activo'               => get_post_meta($post_id, 'posgrado_activo', true),
                'tipo'                 => $tipo,
                'fecha'                => get_post_meta($post_id, 'fecha_inicio', true),
                'proximo_inicio'       => get_post_meta($meta_source_id, 'proximo_inicio', true),
                'calendario_anio'      => $calendario_anio,
                'calendario_link'      => $calendario_link,
                'calendario'           => [
                    'anio' => $calendario_anio,
                    'link' => $calendario_link,
                ],
                'malla_curricular_link'=> get_post_meta($post_id, 'malla_curricular_link', true),
                'imagen'               => (int) get_post_meta($meta_source_id, 'imagen_promocional', true),
                'abreviacion'          => get_post_meta($post_id, 'abreviacion', true),
                'duracion'             => get_post_meta($post_id, 'duracion', true),
                'link'                 => $primary_link,
                'permalink'            => $permalink,
                'cta_pair'             => [
                    'carta'         => $cta_carta,
                    'preinscripcion'=> $cta_pre,
                ],
                'cta_carta'            => $cta_carta,
                'cta_preinscripcion'   => $cta_pre,
                'resumen'              => has_excerpt($post_id) ? get_the_excerpt($post_id) : '',
            ];
        }

        private static function resolve_oferta_post_id(int $post_id): int {
            if (!$post_id) {
                return 0;
            }

            if (get_post_type($post_id) === 'oferta-academica') {
                return $post_id;
            }

            $related = get_posts([
                'post_type'      => 'oferta-academica',
                'posts_per_page' => 1,
                'post_status'    => 'any',
                'fields'         => 'ids',
                'meta_query'     => [
                    [
                        'key'   => '_oferta_page_id',
                        'value' => $post_id,
                    ],
                ],
            ]);

            return $related ? (int) $related[0] : 0;
        }

        private static function format_date($value, string $format): string {
            $value = (string) $value;
            if ($value === '') {
                return '';
            }

            $timestamp = strtotime($value);
            return $timestamp ? date_i18n($format, $timestamp) : $value;
        }

        private static function sanitize_tag(?string $tag): string {
            $tag = strtolower(trim($tag ?: 'div'));
            return preg_match('/^[a-z0-9:-]+$/', $tag) ? $tag : 'div';
        }

        private static function wrap_classes(string $slug, string $extra): string {
            $base = 'flacso-pos-dato ' . sanitize_html_class(str_replace('/', '-', $slug));
            $extra = self::clean_classes($extra);
            return trim($base . ' ' . $extra);
        }

        private static function clean_classes(string $classes): string {
            $clean = preg_replace('/[^A-Za-z0-9\\-\\_\\s]/', '', $classes);
            return trim((string) $clean);
        }

        private static function is_empty_value($value): bool {
            if (is_array($value)) {
                return empty(array_filter($value));
            }
            return $value === '' || $value === null;
        }

        private static function to_bool($value): bool {
            if (is_bool($value)) {
                return $value;
            }
            if (is_numeric($value)) {
                return (int) $value === 1;
            }
            $value = strtolower(trim((string) $value));
            return in_array($value, ['1', 'true', 'yes', 'on', 'si'], true);
        }

        private static function get_child_permalink(int $parent_id, string $slug): string {
            $slug = sanitize_title($slug);
            if (!$slug || !$parent_id) {
                return '';
            }

            $path = trim(trailingslashit(get_page_uri($parent_id)) . $slug, '/');
            $child = get_page_by_path($path, OBJECT, 'page');
            if ($child) {
                return get_permalink($child);
            }

            $children = get_children([
                'post_parent' => $parent_id,
                'post_type'   => 'page',
                'post_status' => ['publish', 'draft', 'pending', 'future', 'private'],
                'fields'      => 'ids',
            ]);

            foreach ($children as $child_id) {
                if (get_post_field('post_name', $child_id) === $slug) {
                    return get_permalink($child_id);
                }
            }

            return '';
        }
    }
}






