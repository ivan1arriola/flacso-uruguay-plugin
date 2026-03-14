<?php

if (!defined('ABSPATH')) {
    exit;
}

if (!defined('FLACSO_CONTACTO_SECCION_BLOCK_PATH')) {
    define('FLACSO_CONTACTO_SECCION_BLOCK_PATH', dirname(__DIR__, 1) . '/');
}

if (!defined('FLACSO_CONTACTO_SECCION_BLOCK_FILE')) {
    define('FLACSO_CONTACTO_SECCION_BLOCK_FILE', FLACSO_CONTACTO_SECCION_BLOCK_PATH . 'block.php');
}

if (!defined('FLACSO_CONTACTO_SECCION_BLOCK_URL')) {
    define('FLACSO_CONTACTO_SECCION_BLOCK_URL', plugin_dir_url(FLACSO_CONTACTO_SECCION_BLOCK_FILE));
}

class Flacso_Contacto_Seccion_Block {
    public const VERSION = '1.0.0';
    public const BLOCK_NAME = 'flacso-uruguay/contacto-seccion';

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

        $style_path = FLACSO_CONTACTO_SECCION_BLOCK_PATH . 'assets/style.css';
        $script_path = FLACSO_CONTACTO_SECCION_BLOCK_PATH . 'assets/block.js';

        wp_register_style(
            'flacso-contacto-seccion-block-style',
            FLACSO_CONTACTO_SECCION_BLOCK_URL . 'assets/style.css',
            [],
            file_exists($style_path) ? (string) filemtime($style_path) : $version
        );

        wp_register_script(
            'flacso-contacto-seccion-block-editor',
            FLACSO_CONTACTO_SECCION_BLOCK_URL . 'assets/block.js',
            [
                'wp-blocks',
                'wp-element',
                'wp-components',
                'wp-block-editor',
                'wp-i18n',
            ],
            file_exists($script_path) ? (string) filemtime($script_path) : $version,
            true
        );
    }

    public function register_block(): void {
        if (!function_exists('register_block_type')) {
            return;
        }

        register_block_type(self::BLOCK_NAME, [
            'api_version' => 2,
            'title' => __('FLACSO - Sección de contacto', 'flacso-main-page'),
            'description' => __('Contenedor de la sección de contacto con mapa y otros contactos.', 'flacso-main-page'),
            'category' => 'flacso-uruguay',
            'icon' => 'email-alt2',
            'keywords' => ['contacto', 'mapa', 'directorio'],
            'supports' => [
                'html' => false,
                'align' => ['full', 'wide'],
                'inserter' => true,
                'multiple' => true,
                'reusable' => true,
            ],
            'style' => 'flacso-contacto-seccion-block-style',
            'editor_script' => 'flacso-contacto-seccion-block-editor',
            'render_callback' => [$this, 'render_block'],
        ]);
    }

    public function render_block(array $attributes = [], string $content = ''): string {
        $inner = trim($content);
        if ($inner === '') {
            return '';
        }

        return '<section class="flacso-contacto-seccion">' .
            '<div class="flacso-contacto-seccion__inner">' . $inner . '</div>' .
            '</section>';
    }
}

