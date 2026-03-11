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
    const VERSION = '0.1.2';
    const SHORTCODE = 'listar_paginas';
    private static $instance;

    public static function init(): void {
        if (null === self::$instance) {
            self::$instance = new self();
        }
    }

    private function __construct() {
        add_action('init', [$this, 'register_assets'], 5);
        add_action('init', [$this, 'register_shortcode'], 10);
    }

    public function register_assets(): void {
        wp_register_style(
            'flacso-listar-paginas-block-style',
            FLACSO_LISTAR_PAGINAS_BLOCK_URL . 'assets/style.css',
            [],
            self::VERSION
        );

        wp_register_style(
            'flacso-listar-paginas-block-editor-style',
            FLACSO_LISTAR_PAGINAS_BLOCK_URL . 'assets/style-editor.css',
            ['flacso-listar-paginas-block-style'],
            self::VERSION
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
            self::VERSION,
            true
        );
    }

    public function register_shortcode(): void {
        add_shortcode(self::SHORTCODE, [$this, 'render_shortcode']);
    }

    public function render_shortcode($atts) {
        $atts = shortcode_atts(
            [
                'padre' => '',
                'padre_id' => '',
                'posts_per_page' => -1,
                'mostrar_inactivos' => '0',
            ],
            $atts,
            self::SHORTCODE
        );

        wp_enqueue_style('flacso-listar-paginas-block-style');
        $this->enqueue_bootstrap_icons();

        return $this->render_markup(
            [
                'padre' => sanitize_text_field($atts['padre']),
                'padre_id' => absint($atts['padre_id']),
                'posts_per_page' => intval($atts['posts_per_page']),
                'mostrar_inactivos' => ('1' === $atts['mostrar_inactivos']),
            ]
        );
    }

    private function render_markup($args) {
        $defaults = [
            'padre' => '',
            'padre_id' => 0,
            'posts_per_page' => -1,
            'mostrar_inactivos' => false,
        ];

        $args = wp_parse_args($args, $defaults);

        $parent_id = $this->resolve_parent_id($args['padre'], $args['padre_id']);
        if (is_wp_error($parent_id)) {
            return $this->render_notice($parent_id);
        }

        $query = new WP_Query([
            'post_type' => 'page',
            'post_status' => 'publish',
            'post_parent' => $parent_id,
            'posts_per_page' => intval($args['posts_per_page']),
            'orderby' => 'menu_order',
            'order' => 'ASC',
        ]);

        if (!$query->have_posts()) {
            wp_reset_postdata();
            return $this->render_notice(
                new WP_Error(
                    'flacso-listar-paginas-empty',
                    __('No hay páginas disponibles.', 'flacso-main-page'),
                    ['class' => 'info']
                )
            );
        }

        $vigentes = $this->get_vigentes();
        $mostrar_inactivos = (bool) $args['mostrar_inactivos'];

        ob_start();
        ?>
        <div class="flacso-grid flacso-grid--mosaic">
            <?php
            $index = 0;
            while ($query->have_posts()) :
                $query->the_post();
                $index++;
                $id = get_the_ID();
                $title = get_the_title();
                $thumb = get_the_post_thumbnail_url($id, 'large') ?: 'https://via.placeholder.com/900?text=FLACSO';
                $vigente = array_key_exists($id, $vigentes);

                if (!$vigente && !$mostrar_inactivos) {
                    continue;
                }
                $creado = get_the_date('U');
                $es_nuevo = (time() - $creado) < (30 * DAY_IN_SECONDS);
                $abbr = $vigente ? $vigentes[$id][0] : '';
                $tipo = $vigente ? $vigentes[$id][1] : '';
                $mosaic_classes = [];
                if ($index % 9 === 0) {
                    $mosaic_classes[] = 'flacso-card--wide';
                }
                if ($index % 7 === 0) {
                    $mosaic_classes[] = 'flacso-card--tall';
                }
                $mosaic_class = implode(' ', $mosaic_classes);
                ?>
                <a class="flacso-card flacso-card--mosaic <?php echo esc_attr(trim($mosaic_class)); ?> <?php echo $vigente ? '' : 'inactivo'; ?>" href="<?php the_permalink(); ?>"
                   <?php if (!$vigente) : ?>
                       title="<?php esc_attr_e('Este posgrado no está vigente actualmente', 'flacso-main-page'); ?>"
                   <?php endif; ?>>
                    <div class="flacso-card__media" style="background-image:url('<?php echo esc_url($thumb); ?>');">
                        <div class="flacso-card__scrim"></div>
                        <div class="flacso-card__title-wrap">
                            <div class="flacso-card__title"><?php echo esc_html($title); ?></div>
                            <?php if ($vigente && $tipo) : ?>
                                <div class="flacso-card__subtitle"><?php echo esc_html($tipo); ?></div>
                            <?php endif; ?>
                        </div>
                    </div>
                    <div class="flacso-card__content">
                        <div class="flacso-badges">
                            <?php if ($vigente) : ?>
                                <?php if ($es_nuevo) : ?>
                                    <span class="flacso-badge nuevo"><i class="bi bi-stars"></i> <?php esc_html_e('Nuevo', 'flacso-main-page'); ?></span>
                                <?php endif; ?>
                                <span class="flacso-badge"><i class="bi bi-hash"></i> <?php echo esc_html($abbr); ?></span>
                                <span class="flacso-badge"><i class="bi bi-mortarboard"></i> <?php echo esc_html($tipo); ?></span>
                            <?php else : ?>
                                <span class="flacso-badge"><i class="bi bi-x-circle"></i> <?php esc_html_e('No vigente', 'flacso-main-page'); ?></span>
                            <?php endif; ?>
                        </div>
                    </div>
                </a>
                <?php
            endwhile;
            wp_reset_postdata();
            ?>
        </div>
        <?php
        return ob_get_clean();
    }

    private function render_notice(WP_Error $error) {
        $class = 'info';
        $data = $error->get_error_data();
        if (is_array($data) && isset($data['class'])) {
            $class = sanitize_html_class($data['class']);
        } elseif (in_array($error->get_error_code(), ['missing_parent', 'parent_not_found'], true)) {
            $class = 'danger';
        }

        return sprintf(
            "<div class='alert alert-%s'>%s</div>",
            esc_attr($class),
            wp_kses_post($error->get_error_message())
        );
    }

    private function resolve_parent_id($padre_title, $padre_id) {
        if ($padre_id > 0) {
            return absint($padre_id);
        }

        if ('' === $padre_title) {
            return new WP_Error(
                'missing_parent',
                __('Debes indicar el atributo <strong>padre</strong> o <strong>padre_id</strong>.', 'flacso-main-page'),
                ['class' => 'danger']
            );
        }

        $padre_query = new WP_Query([
            'post_type' => 'page',
            'title' => $padre_title,
            'post_status' => 'all',
            'posts_per_page' => 1,
            'no_found_rows' => true,
            'ignore_sticky_posts' => true,
            'update_post_term_cache' => false,
            'update_post_meta_cache' => false,
            'orderby' => 'post_date ID',
            'order' => 'ASC',
        ]);

        if (!empty($padre_query->post)) {
            $parent = $padre_query->post;
            wp_reset_postdata();
            return (int) $parent->ID;
        }

        wp_reset_postdata();

        return new WP_Error(
            'parent_not_found',
            sprintf(__('No existe la página padre <strong>%s</strong>', 'flacso-main-page'), esc_html($padre_title)),
            ['class' => 'danger']
        );
    }

    public function enqueue_bootstrap_icons() {
        if (wp_style_is('bootstrap-icons', 'enqueued') || wp_style_is('bootstrap-icons', 'registered')) {
            return;
        }

        wp_enqueue_style(
            'bootstrap-icons',
            'https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css',
            [],
            '1.11.3'
        );
    }

    private function get_vigentes() {
        return [
            12330 => ['EDUTIC', 'Maestría'],
            12336 => ['MESYP', 'Maestría'],
            12343 => ['MG', 'Maestría'],
            12310 => ['EAPET', 'Especialización'],
            12316 => ['EGCCD', 'Especialización'],
            12278 => ['DEPPI', 'Diplomado'],
            14444 => ['DESI', 'Diplomado'],
            12282 => ['DEVBG', 'Diplomado'],
            12288 => ['DEVNNA', 'Diplomado'],
            13202 => ['DCCH', 'Diploma'],
            12295 => ['DAVIA', 'Diploma'],
            12299 => ['DG', 'Diploma'],
            20668 => ['IAPE', 'Diploma'],
            12302 => ['DIDYP', 'Diploma'],
            14657 => ['DSMSYT', 'Diploma'],
        ];
    }
}
