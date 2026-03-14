<?php

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Registra los bloques de Gutenberg para Oferta AcadÃ©mica.
 * Incluye los bloques completos y los datos individuales.
 */
class Oferta_Blocks {

    public static function init(): void {
        self::register_blocks();
    }

    public static function register_blocks(): void {
        $blocks_base_path = dirname(__DIR__) . '/blocks/';

        // Editor JS para bloques completos (oferta-academica y oferta-academica-pagina)
        $script_relative = 'modules/oferta-academica/assets/js/oferta-block.js';
        $script_path     = FLACSO_URUGUAY_PATH . $script_relative;
        $script_url      = FLACSO_URUGUAY_URL . $script_relative;
        $script_version  = file_exists($script_path) ? filemtime($script_path) : FLACSO_OFERTA_ACADEMICA_VERSION;

        wp_register_script(
            'flacso-oferta-block-editor',
            $script_url,
            ['wp-blocks', 'wp-element', 'wp-components', 'wp-block-editor', 'wp-i18n', 'wp-server-side-render'],
            $script_version,
            true
        );

        // Editor JS para dato-proximo-inicio
        $dato_script_relative = 'modules/oferta-academica/assets/js/dato-proximo-inicio-block.js';
        $dato_script_path     = FLACSO_URUGUAY_PATH . $dato_script_relative;
        $dato_script_url      = FLACSO_URUGUAY_URL . $dato_script_relative;
        $dato_script_version  = file_exists($dato_script_path) ? filemtime($dato_script_path) : FLACSO_OFERTA_ACADEMICA_VERSION;

        wp_register_script(
            'flacso-oferta-dato-proximo-inicio-block',
            $dato_script_url,
            ['wp-blocks', 'wp-element', 'wp-components', 'wp-block-editor', 'wp-i18n', 'wp-server-side-render', 'wp-data'],
            $dato_script_version,
            true
        );

        // Editor JS para bloques de documentos (calendario y malla curricular)
        $documento_script_relative = 'modules/oferta-academica/assets/js/dato-documento-pdf-block.js';
        $documento_script_path     = FLACSO_URUGUAY_PATH . $documento_script_relative;
        $documento_script_url      = FLACSO_URUGUAY_URL . $documento_script_relative;
        $documento_script_version  = file_exists($documento_script_path) ? filemtime($documento_script_path) : FLACSO_OFERTA_ACADEMICA_VERSION;

        wp_register_script(
            'flacso-oferta-dato-documento-pdf-block',
            $documento_script_url,
            ['wp-blocks', 'wp-element', 'wp-components', 'wp-block-editor', 'wp-i18n', 'wp-server-side-render', 'wp-data', 'wp-api-fetch'],
            $documento_script_version,
            true
        );

        // Bloque dato: prÃ³ximo inicio
        register_block_type('flacso-uruguay/dato-proximo-inicio', [
            'api_version'     => 2,
            'title'           => __('Oferta AcadÃ©mica: PrÃ³ximo inicio', 'flacso-oferta-academica'),
            'description'     => __('Muestra el prÃ³ximo inicio de la oferta acadÃ©mica seleccionada.', 'flacso-oferta-academica'),
            'category'        => 'flacso-uruguay',
            'icon'            => 'calendar',
            'supports'        => [
                'html' => false,
            ],
            'attributes'      => [
                'ofertaId' => [
                    'type'    => 'integer',
                    'default' => 0,
                ],
            ],
            'editor_script'   => 'flacso-oferta-dato-proximo-inicio-block',
            'render_callback' => [__CLASS__, 'render_dato_proximo_inicio'],
        ]);

        // Bloque dato: calendario (PDF o HTML)
        register_block_type('flacso-uruguay/dato-calendario', [
            'api_version'     => 2,
            'title'           => __('Oferta AcadÃ©mica: Calendario', 'flacso-oferta-academica'),
            'description'     => __('Muestra el calendario de la oferta seleccionada usando PDF o contenido HTML.', 'flacso-oferta-academica'),
            'category'        => 'flacso-uruguay',
            'icon'            => 'media-document',
            'supports'        => [
                'html' => false,
            ],
            'attributes'      => [
                'ofertaId' => [
                    'type'    => 'integer',
                    'default' => 0,
                ],
                'pdfUrlFallback' => [
                    'type'    => 'string',
                    'default' => '',
                ],
                'displayMode' => [
                    'type'    => 'string',
                    'default' => 'auto',
                ],
            ],
            'editor_script'   => 'flacso-oferta-dato-documento-pdf-block',
            'render_callback' => [__CLASS__, 'render_dato_calendario'],
        ]);

        // Bloque dato: malla curricular (PDF o HTML)
        register_block_type('flacso-uruguay/dato-malla-curricular', [
            'api_version'     => 2,
            'title'           => __('Oferta AcadÃ©mica: Malla curricular', 'flacso-oferta-academica'),
            'description'     => __('Muestra la malla curricular de la oferta seleccionada usando PDF o contenido HTML.', 'flacso-oferta-academica'),
            'category'        => 'flacso-uruguay',
            'icon'            => 'media-document',
            'supports'        => [
                'html' => false,
            ],
            'attributes'      => [
                'ofertaId' => [
                    'type'    => 'integer',
                    'default' => 0,
                ],
                'pdfUrlFallback' => [
                    'type'    => 'string',
                    'default' => '',
                ],
                'displayMode' => [
                    'type'    => 'string',
                    'default' => 'auto',
                ],
            ],
            'editor_script'   => 'flacso-oferta-dato-documento-pdf-block',
            'render_callback' => [__CLASS__, 'render_dato_malla_curricular'],
        ]);

        register_block_type($blocks_base_path . 'oferta-academica-pagina', [
            'editor_script'   => 'flacso-oferta-block-editor',
            'render_callback' => [__CLASS__, 'render_oferta_completa'],
        ]);

        register_block_type($blocks_base_path . 'oferta-academica-programa', [
            'editor_script'   => 'flacso-oferta-block-editor',
            'render_callback' => [__CLASS__, 'render_oferta_programa'],
        ]);

        // Bloques individuales por tipo (compatibilidad legacy)
        register_block_type($blocks_base_path . 'maestrias', [
            'render_callback' => [__CLASS__, 'render_maestrias'],
        ]);
        register_block_type($blocks_base_path . 'especializaciones', [
            'render_callback' => [__CLASS__, 'render_especializaciones'],
        ]);
        register_block_type($blocks_base_path . 'diplomados', [
            'render_callback' => [__CLASS__, 'render_diplomados'],
        ]);
        register_block_type($blocks_base_path . 'diplomas', [
            'render_callback' => [__CLASS__, 'render_diplomas'],
        ]);
        register_block_type($blocks_base_path . 'seminarios', [
            'render_callback' => [__CLASS__, 'render_seminarios'],
        ]);
    }

    private static function ensure_styles(): void {
        if (class_exists('Oferta_Renderer') && method_exists('Oferta_Renderer', 'enqueue_styles')) {
            Oferta_Renderer::enqueue_styles();
        }
    }

    public static function render_oferta_completa($attributes, $content): string {
        self::ensure_styles();
        return Oferta_Renderer::render_oferta_pagina((array) $attributes);
    }

    public static function render_oferta_programa($attributes, $content = '', $block = null): string {
        self::ensure_styles();
        return Oferta_Renderer::render_oferta_programa((array) $attributes, $block);
    }

    public static function render_maestrias($attributes, $content): string {
        self::ensure_styles();
        return Oferta_Renderer::render_by_taxonomy('MaestrÃ­a');
    }

    public static function render_especializaciones($attributes, $content): string {
        self::ensure_styles();
        return Oferta_Renderer::render_by_taxonomy('EspecializaciÃ³n');
    }

    public static function render_diplomados($attributes, $content): string {
        self::ensure_styles();
        return Oferta_Renderer::render_by_taxonomy('Diplomado');
    }

    public static function render_diplomas($attributes, $content): string {
        self::ensure_styles();
        return Oferta_Renderer::render_by_taxonomy('Diploma');
    }

    public static function render_seminarios($attributes, $content): string {
        self::ensure_styles();
        return Oferta_Renderer::render_seminarios();
    }

    public static function render_dato_proximo_inicio($attributes, $content = ''): string {
        self::ensure_styles();

        $oferta_id = self::resolve_oferta_id((array) $attributes);

        $is_editor_preview = self::is_editor_preview_context();

        if (!$oferta_id) {
            return $is_editor_preview
                ? '<p>' . esc_html__('Selecciona una oferta acadÃ©mica.', 'flacso-oferta-academica') . '</p>'
                : '';
        }

        $raw_value = get_post_meta($oferta_id, 'proximo_inicio', true);
        $formatted = self::format_proximo_inicio($raw_value);
        if ($formatted === '') {
            $formatted = __('A definir', 'flacso-oferta-academica');
        }

        $label = esc_html__('PrÃ³ximo inicio', 'flacso-oferta-academica');

        return '<div class="flacso-oferta-proximo-inicio" role="status" aria-live="polite">' .
            '<p class="flacso-oferta-proximo-inicio__pill">' .
            '<span class="flacso-oferta-proximo-inicio__label">' . $label . '</span>' .
            '<strong class="flacso-oferta-proximo-inicio__value">' . esc_html($formatted) . '</strong>' .
            '</p>' .
            '</div>';
    }

    public static function render_dato_calendario($attributes, $content = ''): string {
        return self::render_dato_documento_pdf((array) $attributes, 'calendario', __('Calendario', 'flacso-oferta-academica'));
    }

    public static function render_dato_malla_curricular($attributes, $content = ''): string {
        return self::render_dato_documento_pdf((array) $attributes, 'malla_curricular', __('Malla Curricular', 'flacso-oferta-academica'));
    }

    private static function render_dato_documento_pdf(array $attributes, string $meta_key, string $label): string {
        self::ensure_styles();

        $oferta_id = self::resolve_oferta_id($attributes);
        $is_editor_preview = self::is_editor_preview_context();

        if (!$oferta_id) {
            return $is_editor_preview
                ? '<p>' . esc_html__('Selecciona una oferta academica.', 'flacso-oferta-academica') . '</p>'
                : '';
        }

        $raw_url = trim((string) get_post_meta($oferta_id, $meta_key, true));
        $raw_html = trim((string) get_post_meta($oferta_id, $meta_key . '_html', true));
        $fallback = isset($attributes['pdfUrlFallback']) ? trim((string) $attributes['pdfUrlFallback']) : '';
        $final_url = $raw_url !== '' ? $raw_url : $fallback;
        $final_url = esc_url_raw($final_url);

        $display_mode = isset($attributes['displayMode']) ? sanitize_key((string) $attributes['displayMode']) : 'auto';
        if (!in_array($display_mode, ['auto', 'pdf', 'html'], true)) {
            $display_mode = 'auto';
        }

        $can_render_pdf = $final_url !== '' && ($display_mode === 'auto' || $display_mode === 'pdf');
        $can_render_html = $raw_html !== '' && ($display_mode === 'auto' || $display_mode === 'html');

        if (!$can_render_pdf && !$can_render_html) {
            return $is_editor_preview
                ? '<p>' . esc_html(sprintf(__('La oferta seleccionada no tiene PDF ni contenido HTML para %s.', 'flacso-oferta-academica'), strtolower($label))) . '</p>'
                : '';
        }

        if ($can_render_pdf && function_exists('flacso_get_pdf_proxy_url')) {
            $proxied = flacso_get_pdf_proxy_url($final_url, $label);
            if (!empty($proxied)) {
                $final_url = $proxied;
            }
        }

        $config = self::get_documento_card_config($meta_key, $label);

        if ($can_render_pdf) {
            $source_url_for_date = $raw_url !== '' ? $raw_url : $final_url;
            $last_updated_ts = 0;

            if (function_exists('attachment_url_to_postid')) {
                $attachment_id = (int) attachment_url_to_postid($source_url_for_date);
                if ($attachment_id > 0) {
                    $last_updated_ts = (int) get_post_modified_time('U', true, $attachment_id);
                }
            }

            if ($last_updated_ts <= 0) {
                $last_updated_ts = (int) get_post_modified_time('U', true, $oferta_id);
            }

            $updated_line = self::build_updated_line($last_updated_ts);

            return '<article class="flacso-oferta-documento-card-wrapper">' .
                '<div class="flacso-oferta-documento-card" role="region" aria-label="' . esc_attr($config['title']) . '">' .
                '<div class="flacso-oferta-documento-card__icon" aria-hidden="true"><i class="bi ' . esc_attr($config['icon']) . '"></i></div>' .
                '<h3 class="flacso-oferta-documento-card__title">' . esc_html($config['title']) . '</h3>' .
                '<p class="flacso-oferta-documento-card__desc">' . esc_html($config['description']) . '</p>' .
                $updated_line .
                '<a class="flacso-oferta-documento-card__button" href="' . esc_url($final_url) . '" target="_blank" rel="noopener" aria-label="' . esc_attr(sprintf(__('Abrir PDF de %s', 'flacso-oferta-academica'), $config['title'])) . '">' .
                '<i class="bi bi-filetype-pdf" aria-hidden="true"></i>' .
                '<span>' . esc_html__('Ver PDF', 'flacso-oferta-academica') . '</span>' .
                '</a>' .
                '</div>' .
                '</article>';
        }

        $updated_line = self::build_updated_line((int) get_post_modified_time('U', true, $oferta_id));
        $optional_pdf_button = '';

        if ($final_url !== '') {
            $optional_pdf_button = '<a class="flacso-oferta-documento-card__button flacso-oferta-documento-card__button--secondary" href="' . esc_url($final_url) . '" target="_blank" rel="noopener">' .
                '<i class="bi bi-filetype-pdf" aria-hidden="true"></i>' .
                '<span>' . esc_html__('Ver version PDF', 'flacso-oferta-academica') . '</span>' .
                '</a>';
        }

        return '<article class="flacso-oferta-documento-card-wrapper">' .
            '<div class="flacso-oferta-documento-card flacso-oferta-documento-card--html" role="region" aria-label="' . esc_attr($config['title']) . '">' .
            '<div class="flacso-oferta-documento-card__icon" aria-hidden="true"><i class="bi ' . esc_attr($config['icon']) . '"></i></div>' .
            '<h3 class="flacso-oferta-documento-card__title">' . esc_html($config['title']) . '</h3>' .
            '<p class="flacso-oferta-documento-card__desc">' . esc_html($config['description']) . '</p>' .
            $updated_line .
            '<div class="flacso-oferta-documento-card__html">' . wp_kses_post($raw_html) . '</div>' .
            $optional_pdf_button .
            '</div>' .
            '</article>';
    }

    private static function build_updated_line(int $timestamp): string {
        if ($timestamp <= 0) {
            return '';
        }

        return '<p class="flacso-oferta-documento-card__updated">' .
            '<span>' . esc_html__('Ultima actualizacion:', 'flacso-oferta-academica') . '</span> ' .
            '<time datetime="' . esc_attr(wp_date('c', $timestamp)) . '">' . esc_html(date_i18n('d/m/Y', $timestamp)) . '</time>' .
            '</p>';
    }

    private static function get_documento_card_config(string $meta_key, string $label): array {
        if ($meta_key === 'calendario') {
            return [
                'icon' => 'bi-calendar2-check',
                'title' => __('Calendario Academico', 'flacso-oferta-academica'),
                'description' => __('Consulta el cronograma del programa en PDF o en formato web.', 'flacso-oferta-academica'),
            ];
        }

        if ($meta_key === 'malla_curricular') {
            return [
                'icon' => 'bi-journal-bookmark',
                'title' => __('Malla Curricular', 'flacso-oferta-academica'),
                'description' => __('Consulta la malla curricular en PDF o en formato web.', 'flacso-oferta-academica'),
            ];
        }

        return [
            'icon' => 'bi-file-earmark-pdf',
            'title' => $label,
            'description' => __('Abri el documento en formato PDF o HTML.', 'flacso-oferta-academica'),
        ];
    }

    private static function resolve_oferta_id(array $attributes): int {
        $oferta_id = isset($attributes['ofertaId']) ? (int) $attributes['ofertaId'] : 0;
        if (!$oferta_id && isset($attributes['postId'])) {
            $oferta_id = (int) $attributes['postId'];
        }
        if (!$oferta_id && is_singular('oferta-academica')) {
            $oferta_id = (int) get_the_ID();
        }

        return $oferta_id > 0 ? $oferta_id : 0;
    }

    private static function is_editor_preview_context(): bool {
        if (is_admin()) {
            return true;
        }

        // En el editor de bloques, ServerSideRender usa REST y is_admin() puede ser false.
        if (defined('REST_REQUEST') && REST_REQUEST) {
            $context = isset($_REQUEST['context']) ? sanitize_text_field(wp_unslash((string) $_REQUEST['context'])) : '';
            if ($context === 'edit') {
                return true;
            }

            $route = isset($_REQUEST['rest_route']) ? sanitize_text_field(wp_unslash((string) $_REQUEST['rest_route'])) : '';
            if ($route && strpos($route, '/wp/v2/block-renderer/') !== false) {
                return true;
            }
        }

        return false;
    }

    private static function format_proximo_inicio($value): string {
        $value = trim((string) $value);
        if ($value === '') {
            return '';
        }

        $timestamp = strtotime($value);
        if ($timestamp) {
            $formatted = date_i18n('j F Y', $timestamp);
            $parts = preg_split('/\\s+/', trim($formatted));
            if (count($parts) >= 3) {
                $day   = $parts[0];
                $month = self::mb_ucfirst($parts[1]);
                $year  = $parts[2];
                return $day . ' ' . $month . ' del ' . $year;
            }
            return self::mb_ucfirst($formatted);
        }

        return $value;
    }

    private static function mb_ucfirst(string $text): string {
        if ($text === '') {
            return '';
        }

        if (function_exists('mb_convert_case')) {
            return mb_convert_case($text, MB_CASE_TITLE, 'UTF-8');
        }

        return ucfirst($text);
    }
}
