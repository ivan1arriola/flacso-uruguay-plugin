<?php
/**
 * Clase para gestionar la vista de enlaces de seminarios en el admin.
 */

if (!defined('ABSPATH')) {
    exit;
}

class Oferta_Seminarios_Admin_Links {

    public static function init(): void {
        if (is_admin()) {
            add_action('admin_menu', [self::class, 'add_admin_menu'], 20);
        }
    }

    public static function add_admin_menu(): void {
        add_submenu_page(
            'edit.php?post_type=oferta-academica',
            __('Links de Seminarios', 'flacso-uruguay'),
            __('Links de Seminarios', 'flacso-uruguay'),
            'edit_posts',
            'oferta-seminarios-links',
            [self::class, 'render_page']
        );
    }

    public static function render_page(): void {
        $ofertas = get_posts([
            'post_type' => 'oferta-academica',
            'post_status' => 'publish',
            'posts_per_page' => -1,
            'orderby' => 'title',
            'order' => 'ASC',
        ]);

        ?>
        <div class="wrap">
            <h1><?php _e('Enlaces de Seminarios por Programa', 'flacso-uruguay'); ?></h1>
            <p><?php _e('Aquí puedes encontrar las URLs directas a las subpáginas de seminarios de cada oferta académica.', 'flacso-uruguay'); ?></p>

            <table class="widefat fixed striped">
                <thead>
                    <tr>
                        <th><?php _e('Programa / Oferta Académica', 'flacso-uruguay'); ?></th>
                        <th style="width: 100px;"><?php _e('Seminarios', 'flacso-uruguay'); ?></th>
                        <th><?php _e('URL de Seminarios', 'flacso-uruguay'); ?></th>
                        <th style="width: 150px;"><?php _e('Acciones', 'flacso-uruguay'); ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (!empty($ofertas)) : ?>
                        <?php foreach ($ofertas as $oferta) : 
                            $permalink = get_permalink($oferta->ID);
                            $link_seminarios = trailingslashit($permalink) . 'seminarios/';
                        ?>
                            <tr>
                                <td>
                                    <strong><?php echo esc_html($oferta->post_title); ?></strong>
                                    <div class="row-actions">
                                        <span class="edit"><a href="<?php echo get_edit_post_link($oferta->ID); ?>"><?php _e('Editar Oferta', 'flacso-uruguay'); ?></a></span>
                                    </div>
                                </td>
                                <td style="text-align: center;">
                                    <?php 
                                    $seminarios_ids = Oferta_Seminarios_Integration::get_programa_seminarios($oferta->ID);
                                    $count = count($seminarios_ids);
                                    $badge_class = $count > 0 ? 'color: #2271b1; font-weight: bold;' : 'color: #d63638;';
                                    ?>
                                    <span style="<?php echo $badge_class; ?>"><?php echo $count; ?></span>
                                </td>
                                <td>
                                    <code><?php echo esc_url($link_seminarios); ?></code>
                                </td>
                                <td>
                                    <a href="<?php echo esc_url($link_seminarios); ?>" target="_blank" class="button button-small">
                                        <?php _e('Ver Página', 'flacso-uruguay'); ?>
                                    </a>
                                    <button type="button" class="button button-small copy-url" data-url="<?php echo esc_url($link_seminarios); ?>">
                                        <?php _e('Copiar', 'flacso-uruguay'); ?>
                                    </button>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php else : ?>
                        <tr>
                            <td colspan="4"><?php _e('No se encontraron ofertas académicas.', 'flacso-uruguay'); ?></td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>

        <script>
        jQuery(document).ready(function($) {
            $('.copy-url').on('click', function() {
                var url = $(this).data('url');
                var $btn = $(this);
                
                navigator.clipboard.writeText(url).then(function() {
                    var originalText = $btn.text();
                    $btn.text('<?php _e('¡Copiado!', 'flacso-uruguay'); ?>').addClass('button-primary');
                    setTimeout(function() {
                        $btn.text(originalText).removeClass('button-primary');
                    }, 2000);
                });
            });
        });
        </script>
        <?php
    }
}
