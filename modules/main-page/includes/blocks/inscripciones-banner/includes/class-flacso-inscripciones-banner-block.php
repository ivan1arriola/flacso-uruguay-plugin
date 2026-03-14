<?php

if (!defined('ABSPATH')) {
    exit;
}

if (!defined('FLACSO_INSCRIPCIONES_BANNER_BLOCK_PATH')) {
    define('FLACSO_INSCRIPCIONES_BANNER_BLOCK_PATH', dirname(__DIR__, 1) . '/');
}

if (!defined('FLACSO_INSCRIPCIONES_BANNER_BLOCK_FILE')) {
    define('FLACSO_INSCRIPCIONES_BANNER_BLOCK_FILE', FLACSO_INSCRIPCIONES_BANNER_BLOCK_PATH . 'block.php');
}

if (!defined('FLACSO_INSCRIPCIONES_BANNER_BLOCK_URL')) {
    define('FLACSO_INSCRIPCIONES_BANNER_BLOCK_URL', plugin_dir_url(FLACSO_INSCRIPCIONES_BANNER_BLOCK_FILE));
}

class Flacso_Inscripciones_Banner_Block {
    public const VERSION = '1.0.0';
    public const BLOCK_NAME = 'flacso-uruguay/inscripciones-banner';
    public const LEGACY_BLOCK_NAME = 'flacso/inscripciones-banner';

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

        $style_path = FLACSO_INSCRIPCIONES_BANNER_BLOCK_PATH . 'assets/style.css';
        $script_path = FLACSO_INSCRIPCIONES_BANNER_BLOCK_PATH . 'assets/block.js';

        wp_register_style(
            'flacso-inscripciones-banner-block-style',
            FLACSO_INSCRIPCIONES_BANNER_BLOCK_URL . 'assets/style.css',
            [],
            file_exists($style_path) ? (string) filemtime($style_path) : $version
        );

        wp_register_script(
            'flacso-inscripciones-banner-block-editor',
            FLACSO_INSCRIPCIONES_BANNER_BLOCK_URL . 'assets/block.js',
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

        $common = [
            'api_version' => 2,
            'title' => __('Banner inscripciones 2026', 'flacso-main-page'),
            'description' => __('Banner con imagen destacada, texto y logo FLACSO Uruguay.', 'flacso-main-page'),
            'category' => 'flacso-uruguay',
            'icon' => 'megaphone',
            'keywords' => ['inscripciones', 'banner', 'destacada'],
            'attributes' => [
                'tagText' => [
                    'type' => 'string',
                    'default' => 'Próximo inicio',
                ],
                'ctaText' => [
                    'type' => 'string',
                    'default' => 'Descuentos especiales disponibles. Solicitá informacion e inscribite hoy.',
                ],
            ],
            'supports' => [
                'html' => false,
                'align' => ['full', 'wide'],
                'inserter' => true,
                'multiple' => true,
                'reusable' => true,
            ],
            'style' => 'flacso-inscripciones-banner-block-style',
            'editor_script' => 'flacso-inscripciones-banner-block-editor',
            'render_callback' => [$this, 'render_block'],
            'example' => [
                'attributes' => [
                    'tagText' => 'Próximo inicio',
                    'ctaText' => 'Descuentos especiales disponibles. Solicitá informacion e inscribite hoy.',
                ],
            ],
        ];

        register_block_type(self::BLOCK_NAME, $common);

        $legacy = $common;
        $legacy['supports']['inserter'] = false;
        register_block_type(self::LEGACY_BLOCK_NAME, $legacy);
    }

    /**
     * @param array<string,mixed> $attributes
     */
    public function render_block(array $attributes = []): string {
        global $post;

        $tag_text = isset($attributes['tagText']) && is_string($attributes['tagText']) && trim($attributes['tagText']) !== ''
            ? trim($attributes['tagText'])
            : 'Próximo inicio';

        $cta_text = isset($attributes['ctaText']) && is_string($attributes['ctaText']) && trim($attributes['ctaText']) !== ''
            ? trim($attributes['ctaText'])
            : 'Descuentos especiales disponibles. Solicitá informacion e inscribite hoy.';

        $logo_url = 'https://flacso.edu.uy/wp-content/uploads/2024/10/384ddefb-522d-432a-bbc8-c86f09bdceef.png';

        $featured_url = '';
        $post_title = '';

        if ($post instanceof WP_Post) {
            $post_title = get_the_title($post);
            if (has_post_thumbnail($post->ID)) {
                $featured_url = (string) get_the_post_thumbnail_url($post->ID, 'full');
            }
        }

        if ($featured_url === '') {
            $svg = '<svg xmlns="http://www.w3.org/2000/svg" width="1400" height="788" viewBox="0 0 1400 788">'
                . '<defs><linearGradient id="g" x1="0" y1="0" x2="1" y2="1">'
                . '<stop offset="0" stop-color="#1d3a72"/><stop offset="1" stop-color="#0f1f3e"/>'
                . '</linearGradient></defs>'
                . '<rect width="1400" height="788" fill="url(#g)"/>'
                . '<circle cx="1080" cy="240" r="210" fill="rgba(254,210,34,0.18)"/>'
                . '<circle cx="1160" cy="320" r="150" fill="rgba(254,210,34,0.12)"/>'
                . '<rect x="90" y="560" width="1220" height="140" rx="16" fill="rgba(0,0,0,0.18)"/>'
                . '<text x="110" y="615" font-family="Inter, Arial" font-size="44" fill="rgba(255,255,255,0.92)">Previsualizacion</text>'
                . '<text x="110" y="670" font-family="Inter, Arial" font-size="28" fill="rgba(255,255,255,0.78)">Defini una imagen destacada para ver la foto real.</text>'
                . '</svg>';
            $featured_url = 'data:image/svg+xml;base64,' . base64_encode($svg);
        }

        $alt = $post_title !== '' ? $post_title : 'Banner Inscripciones';

        ob_start();
        ?>
        <div class="flacso-inscripciones-banner">
            <img
                class="flacso-inscripciones-banner__img"
                src="<?php echo esc_url($featured_url); ?>"
                alt="<?php echo esc_attr($alt); ?>">

            <div class="flacso-inscripciones-banner__overlay">
                <div class="flacso-inscripciones-banner__top">
                    <div class="flacso-inscripciones-banner__tag">
                        <?php echo esc_html($tag_text); ?>
                    </div>
                    <img
                        src="<?php echo esc_url($logo_url); ?>"
                        alt="FLACSO Uruguay"
                        class="flacso-inscripciones-banner__logo">
                </div>

                <div class="flacso-inscripciones-banner__bottom">
                    <div class="flacso-inscripciones-banner__cta">
                        <?php echo esc_html($cta_text); ?>
                    </div>
                </div>
            </div>
        </div>
        <?php

        return (string) ob_get_clean();
    }
}

