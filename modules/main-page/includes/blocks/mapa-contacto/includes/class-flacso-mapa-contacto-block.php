<?php

if (!defined('ABSPATH')) {
    exit;
}

if (!defined('FLACSO_MAPA_CONTACTO_BLOCK_PATH')) {
    define('FLACSO_MAPA_CONTACTO_BLOCK_PATH', dirname(__DIR__, 1) . '/');
}

if (!defined('FLACSO_MAPA_CONTACTO_BLOCK_FILE')) {
    define('FLACSO_MAPA_CONTACTO_BLOCK_FILE', FLACSO_MAPA_CONTACTO_BLOCK_PATH . 'block.php');
}

if (!defined('FLACSO_MAPA_CONTACTO_BLOCK_URL')) {
    define('FLACSO_MAPA_CONTACTO_BLOCK_URL', plugin_dir_url(FLACSO_MAPA_CONTACTO_BLOCK_FILE));
}

class Flacso_Mapa_Contacto_Block {
    public const VERSION = '1.0.0';
    public const BLOCK_NAME = 'flacso-uruguay/mapa-contacto';

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

        $style_path = FLACSO_MAPA_CONTACTO_BLOCK_PATH . 'assets/style.css';
        $editor_style_path = FLACSO_MAPA_CONTACTO_BLOCK_PATH . 'assets/style-editor.css';
        $script_path = FLACSO_MAPA_CONTACTO_BLOCK_PATH . 'assets/block.js';

        wp_register_style(
            'flacso-mapa-contacto-block-style',
            FLACSO_MAPA_CONTACTO_BLOCK_URL . 'assets/style.css',
            [],
            file_exists($style_path) ? (string) filemtime($style_path) : $version
        );

        wp_register_style(
            'flacso-mapa-contacto-block-editor-style',
            FLACSO_MAPA_CONTACTO_BLOCK_URL . 'assets/style-editor.css',
            ['flacso-mapa-contacto-block-style'],
            file_exists($editor_style_path) ? (string) filemtime($editor_style_path) : $version
        );

        wp_register_script(
            'flacso-mapa-contacto-block-editor',
            FLACSO_MAPA_CONTACTO_BLOCK_URL . 'assets/block.js',
            [
                'wp-blocks',
                'wp-element',
                'wp-components',
                'wp-block-editor',
                'wp-i18n',
                'wp-server-side-render',
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
            'title' => __('FLACSO - Mapa de contacto', 'flacso-main-page'),
            'description' => __('Bloque institucional para mostrar mapa, dirección y accesos rápidos.', 'flacso-main-page'),
            'category' => 'flacso-uruguay',
            'icon' => 'location-alt',
            'keywords' => ['mapa', 'contacto', 'ubicación'],
            'attributes' => [
                'titulo' => [
                    'type' => 'string',
                    'default' => __('Ubicación', 'flacso-main-page'),
                ],
                'etiqueta' => [
                    'type' => 'string',
                    'default' => 'FLACSO Uruguay',
                ],
                'direccion' => [
                    'type' => 'string',
                    'default' => '8 de Octubre 2882, CP 11600, Montevideo',
                ],
                'mapsUrl' => [
                    'type' => 'string',
                    'default' => 'https://maps.google.com/?q=FLACSO+Uruguay+8+de+Octubre+2882+Montevideo',
                ],
                'embedUrl' => [
                    'type' => 'string',
                    'default' => 'https://www.google.com/maps/embed?pb=!1m18!1m12!1m3!1d3272.8199354511025!2d-56.15957652399946!3d-34.88586767245433!2m3!1f0!2f0!3f0!3m2!1i1024!2i768!4f13.1!3m3!1m2!1s0x959f81cd043a1cd5%3A0x9f771efca246dee8!2sFLACSO%20Uruguay!5e0!3m2!1ses-419!2suy!4v1757699982564!5m2!1ses-419!2suy',
                ],
                'agendaUrl' => [
                    'type' => 'string',
                    'default' => 'https://agenda.flacso.edu.uy/',
                ],
            ],
            'supports' => [
                'html' => false,
                'align' => ['full', 'wide'],
                'inserter' => true,
                'multiple' => true,
                'reusable' => true,
            ],
            'style' => 'flacso-mapa-contacto-block-style',
            'editor_style' => 'flacso-mapa-contacto-block-editor-style',
            'editor_script' => 'flacso-mapa-contacto-block-editor',
            'render_callback' => [$this, 'render_block'],
        ]);
    }

    /**
     * @param array<string,mixed> $attributes
     */
    public function render_block(array $attributes = []): string {
        $titulo = sanitize_text_field((string) ($attributes['titulo'] ?? __('Ubicación', 'flacso-main-page')));
        $etiqueta = sanitize_text_field((string) ($attributes['etiqueta'] ?? 'FLACSO Uruguay'));
        $direccion = sanitize_text_field((string) ($attributes['direccion'] ?? '8 de Octubre 2882, CP 11600, Montevideo'));
        $maps_url = esc_url((string) ($attributes['mapsUrl'] ?? 'https://maps.google.com/?q=FLACSO+Uruguay+8+de+Octubre+2882+Montevideo'));
        $embed_url = esc_url((string) ($attributes['embedUrl'] ?? 'https://www.google.com/maps/embed'));
        $agenda_url = esc_url((string) ($attributes['agendaUrl'] ?? 'https://agenda.flacso.edu.uy/'));

        if ($titulo === '') {
            $titulo = __('Ubicación', 'flacso-main-page');
        }
        if ($etiqueta === '') {
            $etiqueta = 'FLACSO Uruguay';
        }
        if ($direccion === '') {
            $direccion = '8 de Octubre 2882, CP 11600, Montevideo';
        }

        ob_start();
        ?>
        <section class="flacso-mapa-contacto" aria-label="<?php echo esc_attr($titulo); ?>">
            <div class="fmc-card">
                <header class="fmc-head">
                    <div>
                        <h2 class="fmc-title"><?php echo esc_html($titulo); ?></h2>
                        <p class="fmc-label"><?php echo esc_html($etiqueta); ?></p>
                    </div>
                    <span class="fmc-badge">FLACSO Uruguay</span>
                </header>

                <div class="fmc-body">
                    <div class="fmc-panel">
                        <div class="fmc-panel-card">
                            <p class="fmc-panel-kicker"><?php esc_html_e('Dirección', 'flacso-main-page'); ?></p>
                            <p class="fmc-address"><?php echo esc_html($direccion); ?></p>

                            <div class="fmc-actions">
                                <?php if ($maps_url !== '') : ?>
                                    <a class="fmc-btn fmc-btn-primary" href="<?php echo esc_url($maps_url); ?>" target="_blank" rel="noopener noreferrer">
                                        <?php esc_html_e('Abrir en Google Maps', 'flacso-main-page'); ?>
                                    </a>
                                <?php endif; ?>
                                <?php if ($agenda_url !== '') : ?>
                                    <a class="fmc-btn fmc-btn-secondary" href="<?php echo esc_url($agenda_url); ?>" target="_blank" rel="noopener noreferrer">
                                        <?php esc_html_e('Agenda web', 'flacso-main-page'); ?>
                                    </a>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>

                    <div class="fmc-map-wrap">
                        <?php if ($embed_url !== '') : ?>
                            <iframe
                                class="fmc-map"
                                title="<?php echo esc_attr($titulo); ?>"
                                src="<?php echo esc_url($embed_url); ?>"
                                loading="lazy"
                                referrerpolicy="no-referrer-when-downgrade"></iframe>
                        <?php else : ?>
                            <div class="fmc-map-placeholder"><?php esc_html_e('Mapa no disponible', 'flacso-main-page'); ?></div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </section>
        <?php

        return (string) ob_get_clean();
    }
}

