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

        // Editor JS para bloques de documentos (calendario y Malla curricular)
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




        register_block_type($blocks_base_path . 'oferta-academica-pagina', [
            'editor_script'   => 'flacso-oferta-block-editor',
            'render_callback' => [__CLASS__, 'render_oferta_completa'],
        ]);

        register_block_type($blocks_base_path . 'oferta-academica-programa', [
            'editor_script'   => 'flacso-oferta-block-editor',
            'render_callback' => [__CLASS__, 'render_oferta_programa'],
        ]);

        register_block_type($blocks_base_path . 'oferta-coordinacion-academica', [
            'editor_script'   => 'flacso-oferta-block-editor',
            'render_callback' => [__CLASS__, 'render_oferta_coordinacion_academica'],
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

    public static function render_oferta_coordinacion_academica($attributes, $content = '', $block = null): string {
        self::ensure_styles();
        return Oferta_Renderer::render_oferta_coordinacion_academica((array) $attributes, $block);
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


}
