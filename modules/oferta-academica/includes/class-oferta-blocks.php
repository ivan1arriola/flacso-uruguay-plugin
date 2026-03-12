<?php

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Registra los bloques de Gutenberg para Oferta Académica.
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

        // Editor JS para bloques de documento PDF (calendario y malla curricular)
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

        // Bloque dato: próximo inicio
        register_block_type('flacso-uruguay/dato-proximo-inicio', [
            'api_version'     => 2,
            'title'           => __('Oferta Académica: Próximo inicio', 'flacso-oferta-academica'),
            'description'     => __('Muestra el próximo inicio de la oferta académica seleccionada.', 'flacso-oferta-academica'),
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

        // Bloque dato: calendario PDF
        register_block_type('flacso-uruguay/dato-calendario', [
            'api_version'     => 2,
            'title'           => __('Oferta Académica: Calendario', 'flacso-oferta-academica'),
            'description'     => __('Muestra un botón para abrir el PDF de calendario de la oferta seleccionada.', 'flacso-oferta-academica'),
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
            ],
            'editor_script'   => 'flacso-oferta-dato-documento-pdf-block',
            'render_callback' => [__CLASS__, 'render_dato_calendario'],
        ]);

        // Bloque dato: malla curricular PDF
        register_block_type('flacso-uruguay/dato-malla-curricular', [
            'api_version'     => 2,
            'title'           => __('Oferta Académica: Malla curricular', 'flacso-oferta-academica'),
            'description'     => __('Muestra un botón para abrir el PDF de malla curricular de la oferta seleccionada.', 'flacso-oferta-academica'),
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
            ],
            'editor_script'   => 'flacso-oferta-dato-documento-pdf-block',
            'render_callback' => [__CLASS__, 'render_dato_malla_curricular'],
        ]);

        register_block_type($blocks_base_path . 'oferta-academica-pagina', [
            'editor_script'   => 'flacso-oferta-block-editor',
            'render_callback' => [__CLASS__, 'render_oferta_completa'],
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

    public static function render_maestrias($attributes, $content): string {
        self::ensure_styles();
        return Oferta_Renderer::render_by_taxonomy('Maestría');
    }

    public static function render_especializaciones($attributes, $content): string {
        self::ensure_styles();
        return Oferta_Renderer::render_by_taxonomy('Especialización');
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
        $oferta_id = self::resolve_oferta_id((array) $attributes);

        $is_editor_preview = self::is_editor_preview_context();

        if (!$oferta_id) {
            return $is_editor_preview
                ? '<p>' . esc_html__('Selecciona una oferta académica.', 'flacso-oferta-academica') . '</p>'
                : '';
        }

        $raw_value = get_post_meta($oferta_id, 'proximo_inicio', true);
        $formatted = self::format_proximo_inicio($raw_value);
        if ($formatted === '') {
            $formatted = __('A definir', 'flacso-oferta-academica');
        }

        $label = esc_html__('Próximo inicio:', 'flacso-oferta-academica');

        return '<div class="flacso-oferta-proximo-inicio">' .
            '<p class="has-text-align-center has-theme-palette-9-color has-theme-palette-1-background-color has-text-color has-background has-link-color" style="border-radius:20px;padding:0.6rem 1rem;">' .
            '<strong>' . $label . '</strong> ' . esc_html($formatted) .
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
                ? '<p>' . esc_html__('Selecciona una oferta académica.', 'flacso-oferta-academica') . '</p>'
                : '';
        }

        $raw_url = trim((string) get_post_meta($oferta_id, $meta_key, true));
        $fallback = isset($attributes['pdfUrlFallback']) ? trim((string) $attributes['pdfUrlFallback']) : '';
        $final_url = $raw_url !== '' ? $raw_url : $fallback;
        $final_url = esc_url_raw($final_url);

        if ($final_url === '') {
            return $is_editor_preview
                ? '<p>' . esc_html(sprintf(__('La oferta seleccionada no tiene URL para %s.', 'flacso-oferta-academica'), strtolower($label))) . '</p>'
                : '';
        }

        if (function_exists('flacso_get_pdf_proxy_url')) {
            $proxied = flacso_get_pdf_proxy_url($final_url, $label);
            if (!empty($proxied)) {
                $final_url = $proxied;
            }
        }

        return '<div class="flacso-oferta-documento-btn-wrapper">' .
            '<a class="flacso-oferta-section__btn flacso-oferta-section__btn--primary" href="' . esc_url($final_url) . '" target="_blank" rel="noopener">' .
            esc_html($label) .
            '</a>' .
            '</div>';
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
