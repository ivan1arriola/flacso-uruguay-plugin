<?php

if (!defined('ABSPATH')) {
    exit;
}

if (!defined('FLACSO_INSTAGRAM_BLOCKS_PATH')) {
    define('FLACSO_INSTAGRAM_BLOCKS_PATH', __DIR__ . '/');
}

if (!defined('FLACSO_INSTAGRAM_BLOCKS_URL')) {
    define('FLACSO_INSTAGRAM_BLOCKS_URL', plugin_dir_url(__FILE__));
}

class Flacso_Instagram_Blocks {
    public const VERSION = '1.0.0';

    private static $instance = null;

    public static function init(): void {
        if (self::$instance === null) {
            self::$instance = new self();
        }
    }

    private function __construct() {
        add_action('init', [$this, 'register_assets'], 5);
        add_action('init', [$this, 'register_blocks'], 20);
    }

    public function register_assets(): void {
        $version = defined('FLACSO_MAIN_PAGE_VERSION') ? FLACSO_MAIN_PAGE_VERSION : self::VERSION;
        $script_path = FLACSO_INSTAGRAM_BLOCKS_PATH . 'assets/block.js';

        wp_register_script(
            'flacso-instagram-blocks-editor',
            FLACSO_INSTAGRAM_BLOCKS_URL . 'assets/block.js',
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

    public function register_blocks(): void {
        if (!function_exists('register_block_type')) {
            return;
        }

        $common_args = [
            'api_version' => 2,
            'category' => 'flacso-uruguay',
            'supports' => [
                'html' => false,
                'align' => ['full', 'wide'],
                'inserter' => true,
                'multiple' => true,
                'reusable' => true,
            ],
            'editor_script' => 'flacso-instagram-blocks-editor',
            'attributes' => [
                'title' => [
                    'type' => 'string',
                    'default' => '',
                ],
            ],
        ];

        // Publicaciones (Imágenes)
        register_block_type('flacso-uruguay/instagram-publicaciones', array_merge($common_args, [
            'title' => __('Instagram: Publicaciones', 'flacso-main-page'),
            'description' => __('Muestra las fotos de Instagram en una cuadrícula.', 'flacso-main-page'),
            'icon' => 'camera',
            'keywords' => ['instagram', 'fotos', 'publicaciones'],
            'render_callback' => function ($attributes) {
                return $this->render_block($attributes, ['IMAGE']);
            }
        ]));

        // Carruseles
        register_block_type('flacso-uruguay/instagram-carruseles', array_merge($common_args, [
            'title' => __('Instagram: Carruseles', 'flacso-main-page'),
            'description' => __('Muestra publicaciones tipo carrusel de Instagram.', 'flacso-main-page'),
            'icon' => 'images-alt2',
            'keywords' => ['instagram', 'carrusel', 'album'],
            'render_callback' => function ($attributes) {
                return $this->render_block($attributes, ['CAROUSEL_ALBUM']);
            }
        ]));

    }

    public function render_block(array $attributes, array $types): string {
        $feed = class_exists('Flacso_Instagram_API') ? Flacso_Instagram_API::get_feed() : new WP_Error('no_class', 'API class not found');
        
        if (is_wp_error($feed) || empty($feed)) {
            return '';
        }
        
        $items = array_filter($feed, function($item) use ($types) {
            return in_array($item['media_type'], $types);
        });

        if (empty($items)) {
            return '';
        }

        $title = sanitize_text_field($attributes['title'] ?? '');
        $section_id = 'flacso-ig-block-' . wp_generate_password(6, false);

        ob_start();
        ?>
        <div class="flacso-instagram-block" id="<?php echo esc_attr($section_id); ?>">
            <?php if (!empty($title)) : ?>
                <h3 class="flacso-ig-block-title mb-4"><?php echo esc_html($title); ?></h3>
            <?php endif; ?>
            
            <div class="flacso-instagram-api-feed">
                <?php foreach ($items as $item) : 
                    $caption_preview = wp_trim_words($item['caption'] ?? '', 15);
                ?>
                    <a href="<?php echo esc_url($item['permalink']); ?>" target="_blank" rel="noopener noreferrer" class="flacso-ig-feed-item">
                        <div class="flacso-ig-feed-image" style="background-image: url('<?php echo esc_url($item['thumbnail_url']); ?>');">
                            <?php if (($item['media_type'] ?? '') === 'VIDEO') : ?>
                                <div class="flacso-ig-feed-type-icon"><i class="bi bi-play-fill"></i></div>
                            <?php elseif (($item['media_type'] ?? '') === 'CAROUSEL_ALBUM') : ?>
                                <div class="flacso-ig-feed-type-icon"><i class="bi bi-images"></i></div>
                            <?php endif; ?>
                            <div class="flacso-ig-feed-overlay">
                                <i class="bi bi-instagram"></i>
                                <p><?php echo esc_html($caption_preview); ?></p>
                            </div>
                        </div>
                    </a>
                <?php endforeach; ?>
            </div>
        </div>
        <?php
        return ob_get_clean();
    }
}
