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
            add_filter('manage_' . self::POST_TYPE . '_posts_columns', [self::class, 'register_columns'], 100);
            add_action('manage_' . self::POST_TYPE . '_posts_custom_column', [self::class, 'render_column'], 10, 2);
            add_action('admin_head-edit.php', [self::class, 'admin_list_styles']);
        }
    }

    public static function disable_block_editor(bool $use_block_editor, string $post_type): bool
    {
        if ($post_type === self::POST_TYPE) {
            return false;
        }
        return $use_block_editor;
    }

    /**
     * Mantiene la tabla centrada en la operación académica.
     * Las columnas SEO de terceros siguen disponibles en la edición individual.
     */
    public static function register_columns(array $columns): array
    {
        $new_columns = [];
        $seo_labels = array(
            'esquema',
            'schema',
            'metadescripción',
            'metadescripcion',
            'meta description',
            'buscar',
            'search',
        );

        foreach ($columns as $key => $label) {
            $normalized_label = strtolower(trim(wp_strip_all_tags((string) $label)));
            if (in_array($normalized_label, $seo_labels, true)) {
                continue;
            }

            if ($key === 'date') {
                continue;
            }

            $new_columns[$key] = $label;

            if ($key === 'title') {
                $new_columns['edicion_actual'] = __('Edición actual', 'flacso-uruguay');
                $new_columns['preinscripcion'] = __('Preinscripción', 'flacso-uruguay');
            }
        }

        $new_columns['date'] = __('Actualizado', 'flacso-uruguay');

        return $new_columns;
    }

    private static function get_ediciones(int $post_id): array
    {
        return get_posts([
            'post_type'      => FLACSO_Edicion::POST_TYPE,
            'post_status'    => array('publish', 'draft', 'pending', 'private'),
            'posts_per_page' => -1,
            'meta_key'       => 'seminario_id',
            'meta_value'     => $post_id,
            'orderby'        => array('date' => 'DESC'),
            'order'          => 'DESC',
        ]);
    }

    private static function get_primary_edicion(array $ediciones): ?WP_Post
    {
        if (empty($ediciones)) {
            return null;
        }

        foreach ($ediciones as $edicion) {
            $estado = FLACSO_Edicion::sanitize_state(get_post_meta($edicion->ID, 'estado', true));
            if (in_array($estado, array('en_curso', 'planificada'), true)) {
                return $edicion;
            }
        }

        return $ediciones[0];
    }

    private static function state_label(string $state): string
    {
        $labels = array(
            'planificada' => __('Planificada', 'flacso-uruguay'),
            'en_curso'    => __('En curso', 'flacso-uruguay'),
            'finalizada'  => __('Finalizada', 'flacso-uruguay'),
            'cancelada'   => __('Cancelada', 'flacso-uruguay'),
        );

        return $labels[$state] ?? ucfirst(str_replace('_', ' ', $state));
    }

    public static function render_column(string $column, int $post_id): void
    {
        if (!in_array($column, array('edicion_actual', 'preinscripcion'), true)) {
            return;
        }

        $ediciones = self::get_ediciones($post_id);
        $edicion = self::get_primary_edicion($ediciones);
        $add_url = admin_url('post-new.php?post_type=' . FLACSO_Edicion::POST_TYPE . '&seminario_id=' . $post_id);

        if ($column === 'edicion_actual') {
            if (!$edicion) {
                echo '<span class="flacso-table-muted">Sin edición</span>';
                echo '<div class="flacso-table-actions"><a href="' . esc_url($add_url) . '">' . esc_html__('Crear edición', 'flacso-uruguay') . '</a></div>';
                return;
            }

            $anio = (int) get_post_meta($edicion->ID, 'anio', true);
            $estado = FLACSO_Edicion::sanitize_state(get_post_meta($edicion->ID, 'estado', true));
            $inicio = (string) get_post_meta($edicion->ID, 'fecha_inicio', true);
            $fin = (string) get_post_meta($edicion->ID, 'fecha_fin', true);
            $edit_url = get_edit_post_link($edicion->ID);
            $title = $anio > 0 ? sprintf(__('Edición %d', 'flacso-uruguay'), $anio) : get_the_title($edicion->ID);

            echo '<div class="flacso-edicion-summary">';
            echo '<a class="flacso-edicion-summary__title" href="' . esc_url($edit_url) . '">' . esc_html($title) . '</a>';
            echo '<span class="flacso-state flacso-state--' . esc_attr($estado) . '">' . esc_html(self::state_label($estado)) . '</span>';
            if ($inicio !== '') {
                $date_label = $inicio . ($fin !== '' && $fin !== $inicio ? ' → ' . $fin : '');
                echo '<span class="flacso-edicion-summary__date">' . esc_html($date_label) . '</span>';
            }
            if (count($ediciones) > 1) {
                echo '<span class="flacso-table-muted">' . esc_html(sprintf(__('+ %d edición(es) anteriores', 'flacso-uruguay'), count($ediciones) - 1)) . '</span>';
            }
            echo '</div>';
            return;
        }

        if (!$edicion) {
            echo '<span class="flacso-status flacso-status--neutral">—</span>';
            return;
        }

        $link = (string) get_post_meta($edicion->ID, 'link_preinscripcion', true);
        $abierta = FLACSO_Edicion::accepts_registration($edicion->ID);

        if ($abierta && $link !== '') {
            echo '<span class="flacso-status flacso-status--open"><span class="dashicons dashicons-yes-alt"></span>' . esc_html__('Abierta', 'flacso-uruguay') . '</span>';
            echo '<div class="flacso-table-actions"><a href="' . esc_url($link) . '" target="_blank" rel="noopener noreferrer">' . esc_html__('Ver portal', 'flacso-uruguay') . ' ↗</a></div>';
        } elseif ($link !== '') {
            echo '<span class="flacso-status flacso-status--closed">' . esc_html__('Cerrada', 'flacso-uruguay') . '</span>';
        } else {
            echo '<span class="flacso-status flacso-status--neutral">' . esc_html__('Sin enlace', 'flacso-uruguay') . '</span>';
        }
    }

    public static function admin_list_styles(): void
    {
        $screen = get_current_screen();
        if (!$screen || $screen->post_type !== self::POST_TYPE) {
            return;
        }
        ?>
        <style>
            .post-type-seminario .wp-list-table { table-layout: fixed; }
            .post-type-seminario .wp-list-table .column-cb { width: 34px; }
            .post-type-seminario .wp-list-table .column-title { width: 36%; }
            .post-type-seminario .wp-list-table .column-edicion_actual { width: 28%; }
            .post-type-seminario .wp-list-table .column-preinscripcion { width: 15%; }
            .post-type-seminario .wp-list-table .column-date { width: 145px; }
            .post-type-seminario .wp-list-table th,
            .post-type-seminario .wp-list-table td { vertical-align: top; }
            .post-type-seminario .wp-list-table .column-title strong a { font-size: 14px; line-height: 1.35; }
            .flacso-edicion-summary { display: flex; flex-wrap: wrap; align-items: center; gap: 5px 7px; }
            .flacso-edicion-summary__title { font-weight: 600; }
            .flacso-edicion-summary__date { flex-basis: 100%; color: #50575e; font-size: 12px; }
            .flacso-state,
            .flacso-status { display: inline-flex; align-items: center; gap: 3px; border-radius: 999px; padding: 2px 8px; font-size: 11px; line-height: 1.6; font-weight: 600; white-space: nowrap; }
            .flacso-state { background: #f0f0f1; color: #3c434a; }
            .flacso-state--en_curso { background: #e7f7ed; color: #116329; }
            .flacso-state--planificada { background: #e8f1fb; color: #135e96; }
            .flacso-state--finalizada { background: #f0f0f1; color: #50575e; }
            .flacso-state--cancelada { background: #fcf0f1; color: #8a2424; }
            .flacso-status--open { background: #e7f7ed; color: #116329; }
            .flacso-status--closed { background: #f0f0f1; color: #50575e; }
            .flacso-status--neutral { background: #f6f7f7; color: #646970; }
            .flacso-status .dashicons { width: 14px; height: 14px; font-size: 14px; }
            .flacso-table-actions { margin-top: 5px; font-size: 12px; }
            .flacso-table-muted { color: #646970; font-size: 12px; }
            @media screen and (max-width: 1100px) {
                .post-type-seminario .wp-list-table .column-date { display: none; }
                .post-type-seminario .wp-list-table .column-title { width: 42%; }
                .post-type-seminario .wp-list-table .column-edicion_actual { width: 34%; }
                .post-type-seminario .wp-list-table .column-preinscripcion { width: 20%; }
            }
        </style>
        <?php
    }

    public static function add_meta_boxes(): void
    {
        add_meta_box(
            'flacso_seminario_ediciones_box',
            __('Ediciones de este Seminario', 'flacso-uruguay'),
            [self::class, 'render_ediciones_meta_box'],
            self::POST_TYPE,
            'normal',
            'high'
        );
    }

    public static function render_ediciones_meta_box($post): void
    {
        $ediciones = self::get_ediciones((int) $post->ID);
        $add_url = admin_url('post-new.php?post_type=' . FLACSO_Edicion::POST_TYPE . '&seminario_id=' . $post->ID);
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
                            $estado = FLACSO_Edicion::sanitize_state(get_post_meta($e->ID, 'estado', true));
                            $modalidad = (string) get_post_meta($e->ID, 'modalidad', true);
                            $inicio = (string) get_post_meta($e->ID, 'fecha_inicio', true);
                            $fin = (string) get_post_meta($e->ID, 'fecha_fin', true);
                            $link = (string) get_post_meta($e->ID, 'link_preinscripcion', true);
                            $abierta = FLACSO_Edicion::accepts_registration($e->ID);
                            $edit_url = get_edit_post_link($e->ID);
                        ?>
                            <tr>
                                <td><strong><a href="<?php echo esc_url($edit_url); ?>">Edición <?php echo esc_html((string) $anio); ?></a></strong></td>
                                <td><span style="font-weight:600;"><?php echo esc_html(self::state_label($estado)); ?></span></td>
                                <td><?php echo $modalidad !== '' ? esc_html($modalidad) : '—'; ?></td>
                                <td><?php echo $inicio ? esc_html($inicio . ($fin ? ' al ' . $fin : '')) : '—'; ?></td>
                                <td>
                                    <?php if ($link) : ?>
                                        <?php echo $abierta ? '<span style="color:#16a34a;font-weight:700;">● Abierta</span>' : '<span style="color:#64748b;">● Cerrada</span>'; ?>
                                        <a href="<?php echo esc_url($link); ?>" target="_blank" rel="noopener noreferrer" style="margin-left:6px;font-size:12px;">Portal ↗</a>
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
                <?php esc_html_e('Agregar nueva edición para este seminario', 'flacso-uruguay'); ?>
            </a>
        </div>
        <?php
    }
}
