<?php
if (!defined('ABSPATH')) {
    exit;
}

class Seminario_CPT
{
    public const POST_TYPE = 'seminario';

    public static function register()
    {
        $labels = array(
            'name'               => __('Seminarios', 'flacso-uruguay'),
            'singular_name'      => __('Seminario', 'flacso-uruguay'),
            'add_new'            => __('Agregar nuevo', 'flacso-uruguay'),
            'add_new_item'       => __('Agregar nuevo seminario', 'flacso-uruguay'),
            'edit_item'          => __('Editar seminario', 'flacso-uruguay'),
            'new_item'           => __('Nuevo seminario', 'flacso-uruguay'),
            'view_item'          => __('Ver seminario', 'flacso-uruguay'),
            'search_items'       => __('Buscar seminarios', 'flacso-uruguay'),
            'not_found'          => __('No se encontraron seminarios', 'flacso-uruguay'),
            'not_found_in_trash' => __('No se encontraron seminarios en la papelera', 'flacso-uruguay'),
            'all_items'          => __('Todos los seminarios', 'flacso-uruguay'),
            'menu_name'          => __('Seminarios', 'flacso-uruguay'),
        );

        $args = array(
            'labels'        => $labels,
            'public'        => true,
            'has_archive'   => true,
            'rewrite'       => array(
                'slug'       => 'formacion/seminarios',
                'with_front' => false,
            ),
            'show_in_rest'  => true,
            'show_in_menu'  => FLACSO_Admin_Panel::PAGE_SLUG,
            'menu_position' => 20,
            'menu_icon'     => 'dashicons-welcome-learn-more',
            'supports'      => array('title', 'thumbnail', 'revisions'),
        );

        register_post_type(self::POST_TYPE, $args);
        add_filter('use_block_editor_for_post_type', array(__CLASS__, 'disable_block_editor'), 10, 2);

        if (is_admin()) {
            add_action('add_meta_boxes', [self::class, 'add_meta_boxes']);
            add_filter('manage_' . self::POST_TYPE . '_posts_columns', [self::class, 'register_columns']);
            add_action('manage_' . self::POST_TYPE . '_posts_custom_column', [self::class, 'render_column'], 10, 2);
        }
    }

    public static function disable_block_editor(bool $use_block_editor, string $post_type): bool
    {
        if ($post_type === self::POST_TYPE) {
            return false;
        }
        return $use_block_editor;
    }

    public static function register_columns(array $columns): array {
        $new_columns = [];
        foreach ($columns as $key => $val) {
            $new_columns[$key] = $val;
            if ($key === 'title') {
                $new_columns['ediciones'] = __('Ediciones Asignadas', 'flacso-uruguay');
            }
        }
        return $new_columns;
    }

    public static function render_column(string $column, int $post_id): void {
        if ($column === 'ediciones') {
            $ediciones = get_posts([
                'post_type'      => FLACSO_Edicion_Seminario::POST_TYPE,
                'posts_per_page' => -1,
                'meta_key'       => 'seminario_id',
                'meta_value'     => $post_id,
                'orderby'        => 'meta_value_num',
                'meta_key_num'   => 'anio',
                'order'          => 'DESC',
            ]);

            if (!empty($ediciones)) {
                echo '<ul style="margin:0;padding-left:14px;list-style:disc;">';
                foreach ($ediciones as $e) {
                    $anio = (int) get_post_meta($e->ID, 'anio', true);
                    $estado = FLACSO_Edicion_Seminario::sanitize_state(get_post_meta($e->ID, 'estado', true));
                    $edit_e_url = get_edit_post_link($e->ID);
                    echo '<li><a href="' . esc_url($edit_e_url) . '">Edición ' . esc_html((string) $anio) . '</a> <small>(' . esc_html($estado) . ')</small></li>';
                }
                echo '</ul>';
            } else {
                echo '<span style="color:#94a3b8;">Sin ediciones</span><br>';
            }

            $add_url = admin_url('post-new.php?post_type=' . FLACSO_Edicion_Seminario::POST_TYPE . '&seminario_id=' . $post_id);
            echo '<div style="margin-top:4px;"><a class="button button-small" href="' . esc_url($add_url) . '">➕ Nueva edición</a></div>';
        }
    }

    public static function add_meta_boxes(): void {
        add_meta_box(
            'flacso_seminario_ediciones_box',
            __('Ediciones de este Seminario', 'flacso-uruguay'),
            [self::class, 'render_ediciones_meta_box'],
            self::POST_TYPE,
            'normal',
            'high'
        );
    }

    public static function render_ediciones_meta_box($post): void {
        $ediciones = get_posts([
            'post_type'      => FLACSO_Edicion_Seminario::POST_TYPE,
            'posts_per_page' => -1,
            'meta_key'       => 'seminario_id',
            'meta_value'     => $post->ID,
            'orderby'        => 'meta_value_num',
            'meta_key_num'   => 'anio',
            'order'          => 'DESC',
        ]);

        $add_url = admin_url('post-new.php?post_type=' . FLACSO_Edicion_Seminario::POST_TYPE . '&seminario_id=' . $post->ID);
        ?>
        <div style="padding: 6px 0;">
            <p style="margin-top:0; color:#475569;">
                <?php esc_html_e('Las ediciones representan las ocurrencias temporales y dictados concretos de este seminario.', 'flacso-uruguay'); ?>
            </p>

            <?php if (!empty($ediciones)) : ?>
                <table class="widefat striped" style="margin-bottom: 15px; border-radius: 4px; overflow: hidden;">
                    <thead>
                        <tr>
                            <th style="font-weight:700;"><?php esc_html_e('Edición', 'flacso-uruguay'); ?></th>
                            <th style="font-weight:700;"><?php esc_html_e('Estado', 'flacso-uruguay'); ?></th>
                            <th style="font-weight:700;"><?php esc_html_e('Modalidad', 'flacso-uruguay'); ?></th>
                            <th style="font-weight:700;"><?php esc_html_e('Fechas', 'flacso-uruguay'); ?></th>
                            <th style="font-weight:700;"><?php esc_html_e('Preinscripción', 'flacso-uruguay'); ?></th>
                            <th style="font-weight:700; text-align:right;"><?php esc_html_e('Acciones', 'flacso-uruguay'); ?></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($ediciones as $e) :
                            $anio = (int) get_post_meta($e->ID, 'anio', true);
                            $estado = FLACSO_Edicion_Seminario::sanitize_state(get_post_meta($e->ID, 'estado', true));
                            $modalidad = (string) get_post_meta($e->ID, 'modalidad', true);
                            $inicio = (string) get_post_meta($e->ID, 'fecha_inicio', true);
                            $fin = (string) get_post_meta($e->ID, 'fecha_fin', true);
                            $link = (string) get_post_meta($e->ID, 'link_preinscripcion', true);
                            $abierta = FLACSO_Edicion_Seminario::accepts_registration($e->ID);
                            $edit_url = get_edit_post_link($e->ID);
                        ?>
                            <tr>
                                <td><strong><a href="<?php echo esc_url($edit_url); ?>">Edición <?php echo esc_html((string) $anio); ?></a></strong></td>
                                <td><span style="font-weight:600;"><?php echo esc_html(ucfirst($estado)); ?></span></td>
                                <td><?php echo $modalidad !== '' ? esc_html($modalidad) : '—'; ?></td>
                                <td><?php echo $inicio ? esc_html($inicio . ($fin ? ' al ' . $fin : '')) : '—'; ?></td>
                                <td>
                                    <?php if ($link) : ?>
                                        <?php echo $abierta ? '<span style="color:#16a34a;font-weight:700;">🟢 Abierta</span>' : '<span style="color:#94a3b8;">⚪ Cerrada</span>'; ?>
                                        <a href="<?php echo esc_url($link); ?>" target="_blank" style="margin-left:6px;font-size:12px;">Portal ↗</a>
                                    <?php else : ?>
                                        <span style="color:#94a3b8;">—</span>
                                    <?php endif; ?>
                                </td>
                                <td style="text-align:right;">
                                    <a class="button button-small" href="<?php echo esc_url($edit_url); ?>"><?php esc_html_e('Editar edición', 'flacso-uruguay'); ?></a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php else : ?>
                <div style="background:#f8fafc; border:1px dashed #cbd5e1; padding:15px; border-radius:6px; margin-bottom:15px; text-align:center; color:#64748b;">
                    <?php esc_html_e('Este seminario aún no tiene ediciones creadas.', 'flacso-uruguay'); ?>
                </div>
            <?php endif; ?>

            <a class="button button-primary" href="<?php echo esc_url($add_url); ?>">
                ➕ <?php esc_html_e('Agregar nueva edición para este seminario', 'flacso-uruguay'); ?>
            </a>
        </div>
        <?php
    }
}
