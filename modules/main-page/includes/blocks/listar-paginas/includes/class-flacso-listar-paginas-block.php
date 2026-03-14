<?php

if (!defined('ABSPATH')) {
    exit;
}

if (!defined('FLACSO_LISTAR_PAGINAS_BLOCK_PATH')) {
    define('FLACSO_LISTAR_PAGINAS_BLOCK_PATH', dirname(__DIR__, 1) . '/');
}

if (!defined('FLACSO_LISTAR_PAGINAS_BLOCK_FILE')) {
    define('FLACSO_LISTAR_PAGINAS_BLOCK_FILE', FLACSO_LISTAR_PAGINAS_BLOCK_PATH . 'block.php');
}

if (!defined('FLACSO_LISTAR_PAGINAS_BLOCK_URL')) {
    define('FLACSO_LISTAR_PAGINAS_BLOCK_URL', plugin_dir_url(FLACSO_LISTAR_PAGINAS_BLOCK_FILE));
}

class Flacso_Listar_Paginas_Block {
    const VERSION = '0.2.0';
    const BLOCK_NAME = 'flacso-uruguay/listar-paginas';
    const SHORTCODE = 'listar_paginas';

    /** @var self|null */
    private static $instance = null;

    public static function init(): void {
        if (self::$instance === null) {
            self::$instance = new self();
        }
    }

    private function __construct() {
        add_action('init', [$this, 'register_assets'], 5);
        add_action('init', [$this, 'register_block'], 20);
    }

    public function register_assets(): void {
        $version = defined('FLACSO_MAIN_PAGE_VERSION') ? FLACSO_MAIN_PAGE_VERSION : self::VERSION;

        wp_register_style(
            'flacso-listar-paginas-block-style',
            FLACSO_LISTAR_PAGINAS_BLOCK_URL . 'assets/style.css',
            [],
            $version
        );

        wp_register_style(
            'flacso-listar-paginas-block-editor-style',
            FLACSO_LISTAR_PAGINAS_BLOCK_URL . 'assets/style-editor.css',
            ['flacso-listar-paginas-block-style'],
            $version
        );

        wp_register_script(
            'flacso-listar-paginas-block-editor',
            FLACSO_LISTAR_PAGINAS_BLOCK_URL . 'assets/block.js',
            [
                'wp-blocks',
                'wp-element',
                'wp-components',
                'wp-block-editor',
                'wp-i18n',
                'wp-data',
                'wp-server-side-render',
            ],
            $version,
            true
        );
    }

    public function register_block(): void {
        if (!function_exists('register_block_type')) {
            return;
        }

        register_block_type(self::BLOCK_NAME, [
            'api_version' => 2,
            'title' => __('Listado de paginas (posgrados)', 'flacso-main-page'),
            'description' => __('Muestra las paginas hijas de una pagina padre.', 'flacso-main-page'),
            'category' => 'flacso-uruguay',
            'icon' => 'index-card',
            'keywords' => ['posgrados', 'grid', 'paginas'],
            'attributes' => [
                'padre' => [
                    'type' => 'string',
                    'default' => '',
                ],
                'padre_id' => [
                    'type' => 'number',
                    'default' => 0,
                ],
                'posts_per_page' => [
                    'type' => 'number',
                    'default' => -1,
                ],
                'mostrar_inactivos' => [
                    'type' => 'boolean',
                    'default' => false,
                ],
                'vista' => [
                    'type' => 'string',
                    'default' => 'catalogo_3d',
                ],
            ],
            'supports' => [
                'html' => false,
                'align' => ['full', 'wide'],
                'inserter' => true,
                'multiple' => true,
                'reusable' => true,
            ],
            'style' => 'flacso-listar-paginas-block-style',
            'editor_style' => 'flacso-listar-paginas-block-editor-style',
            'editor_script' => 'flacso-listar-paginas-block-editor',
            'render_callback' => [$this, 'render_block'],
        ]);
    }

    /**
     * @param array<string,mixed> $attributes
     */
    public function render_block(array $attributes = []): string {
        $this->ensure_shortcode_loaded();

        $atts = [
            'padre' => isset($attributes['padre']) ? sanitize_text_field((string) $attributes['padre']) : '',
            'padre_id' => isset($attributes['padre_id']) ? (string) absint($attributes['padre_id']) : '',
            'posts_per_page' => isset($attributes['posts_per_page']) ? (string) intval($attributes['posts_per_page']) : '-1',
            'mostrar_inactivos' => !empty($attributes['mostrar_inactivos']) ? '1' : '0',
            'vista' => isset($attributes['vista']) ? sanitize_key((string) $attributes['vista']) : 'catalogo_3d',
        ];

        if (function_exists('flacso_listar_paginas_shortcode')) {
            return flacso_listar_paginas_shortcode($atts);
        }

        if (shortcode_exists(self::SHORTCODE)) {
            return do_shortcode($this->build_shortcode($atts));
        }

        return '<div class="notice notice-error"><p>'
            . esc_html__('No se pudo renderizar el bloque de listado de paginas.', 'flacso-main-page')
            . '</p></div>';
    }

    private function ensure_shortcode_loaded(): void {
        if (function_exists('flacso_listar_paginas_shortcode')) {
            return;
        }

        if (!defined('FLACSO_MAIN_PAGE_MODULE_PATH')) {
            return;
        }

        $shortcode_file = FLACSO_MAIN_PAGE_MODULE_PATH . 'sections/listar-paginas.php';
        if (file_exists($shortcode_file)) {
            require_once $shortcode_file;
        }
    }

    /**
     * @param array<string,string> $atts
     */
    private function build_shortcode(array $atts): string {
        $parts = [];
        foreach ($atts as $key => $value) {
            if ($value === '') {
                continue;
            }
            $parts[] = sprintf('%s="%s"', sanitize_key($key), esc_attr($value));
        }

        $attr_string = $parts ? (' ' . implode(' ', $parts)) : '';
        return sprintf('[%s%s]', self::SHORTCODE, $attr_string);
    }
}
