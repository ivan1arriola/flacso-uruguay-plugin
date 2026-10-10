<?php

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Registra el Custom Post Type: oferta-academica
 * y gestiona la visualización contextual de sus cohortes subordinadas.
 */
class CPT_Oferta_Academica {
    public const POST_TYPE = 'oferta-academica';

    public static function init(): void {
        self::register_post_type();
        add_filter('use_block_editor_for_post_type', [self::class, 'disable_block_editor'], 10, 2);
        add_action('template_redirect', [self::class, 'maybe_render_formacion_virtual_page'], 1);

        if (is_admin()) {
            add_action('add_meta_boxes', [self::class, 'add_meta_boxes']);
            add_filter('manage_' . self::POST_TYPE . '_posts_columns', [self::class, 'register_columns']);
            add_action('manage_' . self::POST_TYPE . '_posts_custom_column', [self::class, 'render_column'], 10, 2);
            add_action('restrict_manage_posts', [self::class, 'render_operational_filters']);
            add_filter('posts_clauses', [self::class, 'filter_by_operational_status'], 20, 2);
            add_action('admin_head-edit.php', [self::class, 'render_admin_list_styles']);
            add_action('admin_footer-edit.php', [self::class, 'render_admin_list_script']);
        }
    }

    public static function maybe_render_formacion_virtual_page(): void {
        $request_uri = sanitize_text_field(wp_unslash($_SERVER['REQUEST_URI'] ?? ''));
        $path = trim(wp_parse_url($request_uri, PHP_URL_PATH), '/');
        
        if ($path === 'formacion') {
            global $wp_query;
            if ($wp_query) {
                $wp_query->is_404 = false;
                $wp_query->is_singular = true;
                $wp_query->is_page = true;
                $wp_query->is_archive = false;
                $wp_query->is_home = false;
                $wp_query->set_404(false);
            }

            status_header(200);
            nocache_headers();

            $base_title = 'Oferta Académica';
            $site_name = trim((string) get_bloginfo('name'));
            $full_title = $site_name ? $base_title . ' - ' . $site_name : $base_title;

            add_filter('pre_get_document_title', static function () use ($full_title) { return $full_title; }, 999);
            add_filter('document_title_parts', static function ($parts) use ($base_title) {
                $parts['title'] = $base_title;
                unset($parts['tagline']);
                return $parts;
            }, 999);
            add_filter('wp_title', static function () use ($full_title) { return $full_title; }, 999);
            add_filter('rank_math/frontend/title', static function () use ($full_title) { return $full_title; }, 999);
            add_filter('wpseo_title', static function () use ($full_title) { return $full_title; }, 999);
            
            add_filter('flacso_oferta_academica_hero_image', static function($image) {
                return $image ? $image : 'https://flacso.edu.uy/wp-content/uploads/2025/11/primer-plano-de-ejecutivos-de-negocios-en-la-oficina-scaled.jpg';
            });

            $template = locate_template('page-formacion.php');
            if ($template) {
                include $template;
            } else {
                wp_die(
                    esc_html__('Falta el template page-formacion.php en el theme activo.', 'flacso-uruguay'),
                    esc_html__('Template no disponible', 'flacso-uruguay'),
                    ['response' => 500]
                );
            }
            exit;
        }
    }

    public static function register_post_type(): void {
        $labels = [
            'name'                  => __('Ofertas Académicas', 'flacso-uruguay'),
            'singular_name'         => __('Oferta Académica', 'flacso-uruguay'),
            'menu_name'             => __('Ofertas Académicas', 'flacso-uruguay'),
            'name_admin_bar'        => __('Oferta Académica', 'flacso-uruguay'),
            'add_new'               => __('Añadir Nueva', 'flacso-uruguay'),
            'add_new_item'          => __('Añadir Nueva Oferta Académica', 'flacso-uruguay'),
            'new_item'              => __('Nueva Oferta Académica', 'flacso-uruguay'),
            'edit_item'             => __('Editar Oferta Académica', 'flacso-uruguay'),
            'view_item'             => __('Ver Oferta Académica', 'flacso-uruguay'),
            'all_items'             => __('Todas las Ofertas', 'flacso-uruguay'),
            'search_items'          => __('Buscar Ofertas Académicas', 'flacso-uruguay'),
            'not_found'             => __('No se encontraron ofertas académicas', 'flacso-uruguay'),
            'not_found_in_trash'    => __('No hay ofertas académicas en la papelera', 'flacso-uruguay'),
        ];

        $args = [
            'labels'                => $labels,
            'public'                => true,
            'publicly_queryable'    => true,
            'show_ui'               => true,
            'show_in_menu'          => FLACSO_Admin_Panel::PAGE_SLUG,
            'show_in_rest'          => true,
            'query_var'             => true,
            'rewrite'               => ['slug' => 'formacion/%tipo-oferta-academica%', 'with_front' => false],
            'capability_type'       => 'post',
            'capabilities'          => FLACSO_Academic_Assistant::post_type_capabilities('offer'),
            'map_meta_cap'          => true,
            'has_archive'           => false,
            'hierarchical'          => false,
            'menu_position'         => 5,
            'menu_icon'             => 'dashicons-welcome-learn-more',
            'supports'              => ['title', 'thumbnail', 'revisions'],
            'taxonomies'            => ['tipo-oferta-academica'],
        ];

        register_post_type(self::POST_TYPE, $args);
        add_filter('post_type_link', [self::class, 'oferta_academica_permalink'], 10, 2);
    }

    public static function oferta_academica_permalink($post_link, $post) {
        if (is_object($post) && $post->post_type === self::POST_TYPE) {
            if (strpos($post_link, '%tipo-oferta-academica%') !== false) {
                $terms = wp_get_object_terms($post->ID, 'tipo-oferta-academica');
                if (!is_wp_error($terms) && !empty($terms) && is_object($terms[0])) {
                    $slug = $terms[0]->slug;
                    $segments = FLACSO_Oferta_Academica::segmentos_url();
                    $plural_slug = $segments[$slug] ?? 'otros';
                    $post_link = str_replace('%tipo-oferta-academica%', $plural_slug, $post_link);
                } else {
                    $post_link = str_replace('%tipo-oferta-academica%', 'otros', $post_link);
                }
            }
        }
        return $post_link;
    }

    public static function disable_block_editor(bool $use_block_editor, string $post_type): bool {
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
                $new_columns['cohorte_actual'] = __('Cohorte actual', 'flacso-uruguay');
                $new_columns['preinscripcion'] = __('Preinscripción', 'flacso-uruguay');
            }
        }
        return $new_columns;
    }

    public static function render_column(string $column, int $post_id): void {
        if (!in_array($column, ['cohorte_actual', 'preinscripcion'], true)) {
            return;
        }

        $cohortes = get_posts([
            'post_type'      => 'cohorte',
            'post_status'    => ['publish', 'draft', 'pending', 'private'],
            'posts_per_page' => -1,
            'meta_query'     => [[
                'key'     => 'oferta_academica_id',
                'value'   => $post_id,
                'compare' => '=',
                'type'    => 'NUMERIC',
            ]],
        ]);
        $cohorte = self::get_current_cohort($cohortes);

        if ($column === 'cohorte_actual') {
            if (!$cohorte) {
                $add_url = admin_url('post-new.php?post_type=cohorte&oferta_academica_id=' . $post_id);
                echo '<div class="flacso-current-instance flacso-current-instance--empty">';
                echo '<span class="flacso-table-muted">' . esc_html__('Sin cohorte vigente', 'flacso-uruguay') . '</span>';
                echo '<a href="' . esc_url($add_url) . '">' . esc_html__('Crear cohorte', 'flacso-uruguay') . '</a>';
                echo '</div>';
                return;
            }

            $num = (int) get_post_meta($cohorte->ID, 'numero', true);
            $roman = $num > 0 ? FLACSO_Cohorte::to_roman($num) : '';
            $label = $roman !== '' ? sprintf(__('Cohorte %s', 'flacso-uruguay'), $roman) : (get_the_title($cohorte->ID) ?: __('Cohorte', 'flacso-uruguay'));
            $estado = FLACSO_Cohorte::sanitize_state(get_post_meta($cohorte->ID, 'estado', true));
            $date = FLACSO_Cohorte::format_dates($cohorte->ID);
            echo '<div class="flacso-current-instance">';
            echo '<a class="flacso-current-instance__title" href="' . esc_url(get_edit_post_link($cohorte->ID)) . '">' . esc_html($label) . '</a>';
            echo '<div class="flacso-current-instance__meta">';
            echo '<span class="flacso-state flacso-state--' . esc_attr($estado) . '">' . esc_html(self::academic_state_label($estado)) . '</span>';
            if ($date !== '') {
                echo '<span class="flacso-current-instance__date">' . esc_html($date) . '</span>';
            }
            echo '</div></div>';
            return;
        }

        if (!$cohorte) {
            echo '<span class="flacso-status flacso-status--neutral">—</span>';
            return;
        }

        $configured = metadata_exists('post', $cohorte->ID, 'preinscripcion_habilitada');
        $open = FLACSO_Cohorte::accepts_registration($cohorte->ID);
        $url = (string) get_post_meta($cohorte->ID, 'link_preinscripcion', true) ?: FLACSO_Preinscription_Ajax_Handlers::offer_url($post_id);
        echo '<div class="flacso-current-instance flacso-current-instance--registration' . ($open ? ' flacso-current-instance--open' : '') . '">';
        echo '<span class="flacso-status flacso-status--' . ($open ? 'open' : ($configured ? 'closed' : 'neutral')) . '">';
        echo esc_html($open ? __('Abierta', 'flacso-uruguay') : ($configured ? __('Cerrada', 'flacso-uruguay') : __('Sin configurar', 'flacso-uruguay')));
        echo '</span>';
        if (current_user_can('edit_post', $cohorte->ID)) {
            $nonce = wp_create_nonce('flacso_preinscripcion_nonce');
            echo '<div class="flacso-current-instance__actions">';
            if ($open && $url !== '') {
                echo '<a href="' . esc_url($url) . '" target="_blank" rel="noopener noreferrer">' . esc_html__('Ver portal ↗', 'flacso-uruguay') . '</a>';
            }
            echo self::preinscription_action_button($cohorte->ID, $nonce, $open ? 'flacso_cerrar_preinscripcion_cohorte' : 'flacso_abrir_preinscripcion_cohorte', $open ? __('Cerrar', 'flacso-uruguay') : __('Abrir preinscripción', 'flacso-uruguay'), $open ? 'flacso-button-danger' : 'button-primary');
            echo '</div>';
        }
        echo '<div class="flacso-current-instance__notice" role="status" aria-live="polite"></div></div>';
    }

    private static function get_current_cohort(array $cohortes): ?WP_Post {
        $active = array_filter($cohortes, static function ($cohort): bool {
            return in_array(FLACSO_Cohorte::sanitize_state(get_post_meta($cohort->ID, 'estado', true)), ['en_curso', 'planificada'], true);
        });
        if (empty($active)) {
            return null;
        }
        usort($active, static function ($left, $right): int {
            $left_state = FLACSO_Cohorte::sanitize_state(get_post_meta($left->ID, 'estado', true));
            $right_state = FLACSO_Cohorte::sanitize_state(get_post_meta($right->ID, 'estado', true));
            $priority = ['en_curso' => 0, 'planificada' => 1];
            $comparison = ($priority[$left_state] ?? 2) <=> ($priority[$right_state] ?? 2);
            return $comparison !== 0 ? $comparison : strcmp((string) get_post_meta($left->ID, 'fecha_inicio', true), (string) get_post_meta($right->ID, 'fecha_inicio', true));
        });
        return reset($active) ?: null;
    }

    public static function render_operational_filters(string $post_type): void {
        if ($post_type !== self::POST_TYPE) { return; }
        $selected = sanitize_key((string) ($_GET['flacso_operational_status'] ?? ''));
        $options = ['', 'preinscripcion_abierta', 'en_curso', 'planificada', 'sin_vigente'];
        $labels = ['', __('Preinscripción abierta', 'flacso-uruguay'), __('En curso', 'flacso-uruguay'), __('Planificada', 'flacso-uruguay'), __('Sin cohorte vigente', 'flacso-uruguay')];
        echo '<select name="flacso_operational_status"><option value="">' . esc_html__('Estado operativo', 'flacso-uruguay') . '</option>';
        foreach ($options as $index => $value) { if ($value !== '') { echo '<option value="' . esc_attr($value) . '" ' . selected($selected, $value, false) . '>' . esc_html($labels[$index]) . '</option>'; } }
        echo '</select>';
    }

    public static function filter_by_operational_status(array $clauses, WP_Query $query): array {
        $status = sanitize_key((string) ($query->get('flacso_operational_status') ?: ($_GET['flacso_operational_status'] ?? '')));
        if (!$query->is_main_query() || $query->get('post_type') !== self::POST_TYPE || !in_array($status, ['preinscripcion_abierta', 'en_curso', 'planificada', 'sin_vigente'], true)) { return $clauses; }
        global $wpdb;
        $base = "SELECT 1 FROM {$wpdb->posts} child INNER JOIN {$wpdb->postmeta} parent ON parent.post_id = child.ID AND parent.meta_key = 'oferta_academica_id' AND parent.meta_value = CAST({$wpdb->posts}.ID AS CHAR) LEFT JOIN {$wpdb->postmeta} state ON state.post_id = child.ID AND state.meta_key = 'estado' LEFT JOIN {$wpdb->postmeta} registration ON registration.post_id = child.ID AND registration.meta_key = 'preinscripcion_habilitada' WHERE child.post_type = 'cohorte' AND child.post_status IN ('publish','draft','pending','private') AND COALESCE(NULLIF(state.meta_value, ''), 'planificada') IN ('en_curso','planificada')";
        if ($status === 'sin_vigente') { $clauses['where'] .= " AND NOT EXISTS ({$base})"; return $clauses; }
        $condition = $status === 'preinscripcion_abierta' ? " AND registration.meta_value IN ('1','true')" : " AND state.meta_value = '" . esc_sql($status) . "'";
        $clauses['where'] .= " AND EXISTS ({$base}{$condition})";
        return $clauses;
    }

    private static function academic_state_label(string $state): string {
        $labels = [
            'planificada' => __('Planificada', 'flacso-uruguay'),
            'en_curso'    => __('En curso', 'flacso-uruguay'),
            'finalizada'  => __('Finalizada', 'flacso-uruguay'),
            'cancelada'   => __('Cancelada', 'flacso-uruguay'),
        ];
        return $labels[$state] ?? ucfirst(str_replace('_', ' ', $state));
    }

    private static function preinscription_action_button(
        int $cohort_id,
        string $nonce,
        string $action,
        string $label,
        string $extra_class = ''
    ): string {
        return sprintf(
            '<button type="button" class="button button-small flacso-pre-action %1$s" data-action="%2$s" data-cohorte-id="%3$d" data-nonce="%4$s">%5$s</button>',
            esc_attr($extra_class),
            esc_attr($action),
            $cohort_id,
            esc_attr($nonce),
            esc_html($label)
        );
    }

    public static function render_admin_list_styles(): void {
        $screen = get_current_screen();
        if (!$screen || $screen->post_type !== self::POST_TYPE) {
            return;
        }
        ?>
        <style>
            .post-type-oferta-academica .wp-list-table { table-layout: fixed; border: 1px solid #dbe5f1; border-radius: 8px; overflow: hidden; }
            .post-type-oferta-academica .column-title { width: 34%; }
            .post-type-oferta-academica .column-cohorte_actual { width: 30%; }
            .post-type-oferta-academica .column-preinscripcion { width: 18%; }
            .post-type-oferta-academica .wp-list-table td { vertical-align: middle; padding-top: 11px; padding-bottom: 11px; border-bottom-color: #e9eef5; }
            .post-type-oferta-academica .wp-list-table thead th,
            .post-type-oferta-academica .wp-list-table tfoot th { background: #f6f8fc; color: #42526e; }
            .post-type-oferta-academica .column-title strong a { color: #1d4ed8; font-size: 14px; line-height: 1.35; }
            .flacso-current-instance { display: grid; gap: 4px; }
            .flacso-current-instance__title { width: fit-content; color: #1d4ed8; font-weight: 700; text-decoration: none; }
            .flacso-current-instance__title:hover { color: #1e3a8a; text-decoration: underline; }
            .flacso-current-instance__meta { display: flex; flex-wrap: wrap; align-items: center; gap: 5px; }
            .flacso-current-instance__date { color: #50575e; font-size: 12px; }
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
            .flacso-current-instance--empty a,
            .flacso-current-instance__actions a { font-size: 12px; font-weight: 600; text-decoration: none; }
            .flacso-current-instance__actions { display: flex; flex-wrap: wrap; align-items: center; gap: 5px; }
            .flacso-current-instance__actions .button { min-height: 26px; line-height: 24px; margin: 0; }
            .flacso-button-danger { color: #b32d2e !important; border-color: #d63638 !important; }
            @media (max-width: 1100px) {
                .post-type-oferta-academica .column-title { width: 38%; }
                .post-type-oferta-academica .column-cohorte_actual { width: 34%; }
            }
        </style>
        <?php
    }

    public static function render_admin_list_script(): void {
        $screen = get_current_screen();
        if (!$screen || $screen->post_type !== self::POST_TYPE) {
            return;
        }
        ?>
        <script>
        (function($) {
            $(document).on('click', '.flacso-pre-action', function() {
                var button = $(this);
                var action = String(button.data('action') || '');
                var card = button.closest('.flacso-current-instance');
                var notice = card.find('.flacso-current-instance__notice');
                var originalText = button.text();

                if (action === 'flacso_cerrar_preinscripcion_cohorte'
                    && !window.confirm('<?php echo esc_js(__('¿Cerrar la preinscripción de esta cohorte?', 'flacso-uruguay')); ?>')) {
                    return;
                }

                card.find('.flacso-pre-action').prop('disabled', true);
                notice.removeClass('is-success is-error').hide();
                button.text('<?php echo esc_js(__('Procesando…', 'flacso-uruguay')); ?>');

                $.post(ajaxurl, {
                    action: action,
                    cohorte_id: button.data('cohorte-id'),
                    _wpnonce: button.data('nonce')
                }).done(function(response) {
                    if (response && response.success) {
                        notice.addClass('is-success').text(response.data.message).show();
                        window.setTimeout(function() { window.location.reload(); }, 900);
                        return;
                    }
                    var message = response && response.data && response.data.message
                        ? response.data.message
                        : '<?php echo esc_js(__('No fue posible actualizar la preinscripción.', 'flacso-uruguay')); ?>';
                    notice.addClass('is-error').text(message).show();
                    card.find('.flacso-pre-action').prop('disabled', false);
                    button.text(originalText);
                }).fail(function(xhr) {
                    var message = xhr.responseJSON && xhr.responseJSON.data && xhr.responseJSON.data.message
                        ? xhr.responseJSON.data.message
                        : '<?php echo esc_js(__('Error de comunicación con el sistema de preinscripciones.', 'flacso-uruguay')); ?>';
                    notice.addClass('is-error').text(message).show();
                    card.find('.flacso-pre-action').prop('disabled', false);
                    button.text(originalText);
                });
            });
        })(jQuery);
        </script>
        <?php
    }

    public static function add_meta_boxes(): void {
        // Los datos académicos se administran en sus formularios tipados. El
        // metabox nativo permitiría recrear metadatos legacy o campos sueltos.
        remove_meta_box('postcustom', self::POST_TYPE, 'normal');
        add_meta_box(
            'flacso_oferta_cohortes_box',
            __('Cohortes de esta Oferta Académica', 'flacso-uruguay'),
            [self::class, 'render_cohortes_meta_box'],
            self::POST_TYPE,
            'normal',
            'high'
        );
    }

    public static function render_cohortes_meta_box($post): void {
        $cohortes = get_posts([
            'post_type'      => 'cohorte',
            'posts_per_page' => -1,
            'meta_key'       => 'oferta_academica_id',
            'meta_value'     => $post->ID,
            'orderby'        => 'meta_value_num',
            'meta_key_num'   => 'numero',
            'order'          => 'DESC',
        ]);

        $add_url = admin_url('post-new.php?post_type=cohorte&oferta_academica_id=' . $post->ID);
        ?>
        <div style="padding: 6px 0;">
            <p style="margin-top:0; color:#475569;">
                <?php esc_html_e('Las cohortes son las aperturas temporales de esta oferta académica donde se configuran las fechas y la preinscripción.', 'flacso-uruguay'); ?>
            </p>

            <?php if (!empty($cohortes)) : ?>
                <table class="widefat striped" style="margin-bottom: 15px; border-radius: 4px; overflow: hidden;">
                    <thead>
                        <tr>
                            <th style="font-weight:700;"><?php esc_html_e('Cohorte', 'flacso-uruguay'); ?></th>
                            <th style="font-weight:700;"><?php esc_html_e('Estado', 'flacso-uruguay'); ?></th>
                            <th style="font-weight:700;"><?php esc_html_e('Fechas', 'flacso-uruguay'); ?></th>
                            <th style="font-weight:700;"><?php esc_html_e('Preinscripción', 'flacso-uruguay'); ?></th>
                            <th style="font-weight:700; text-align:right;"><?php esc_html_e('Acciones', 'flacso-uruguay'); ?></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($cohortes as $c) :
                            $num = (int) get_post_meta($c->ID, 'numero', true);
                            $roman = FLACSO_Cohorte::to_roman($num) ?: (string) $num;
                            $estado = FLACSO_Cohorte::sanitize_state(get_post_meta($c->ID, 'estado', true));
                            $fechas_txt = FLACSO_Cohorte::format_dates($c->ID);
                            $link = (string) get_post_meta($c->ID, 'link_preinscripcion', true);
                            $abierta = FLACSO_Cohorte::accepts_registration($c->ID);
                            $edit_url = get_edit_post_link($c->ID);
                        ?>
                            <tr>
                                <td><strong><a href="<?php echo esc_url($edit_url); ?>">Cohorte <?php echo esc_html($roman); ?></a></strong></td>
                                <td><span style="font-weight:600;"><?php echo esc_html(ucfirst($estado)); ?></span></td>
                                <td><?php echo $fechas_txt !== '' ? esc_html($fechas_txt) : '—'; ?></td>
                                <td>
                                    <?php if ($link) : ?>
                                        <?php echo $abierta ? '<span style="color:#16a34a;font-weight:700;">🟢 Abierta</span>' : '<span style="color:#94a3b8;">⚪ Cerrada</span>'; ?>
                                        <a href="<?php echo esc_url($link); ?>" target="_blank" style="margin-left:6px;font-size:12px;">Portal ↗</a>
                                    <?php else : ?>
                                        <span style="color:#94a3b8;">—</span>
                                    <?php endif; ?>
                                </td>
                                <td style="text-align:right;">
                                    <a class="button button-small" href="<?php echo esc_url($edit_url); ?>"><?php esc_html_e('Editar cohorte', 'flacso-uruguay'); ?></a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php else : ?>
                <div style="background:#f8fafc; border:1px dashed #cbd5e1; padding:15px; border-radius:6px; margin-bottom:15px; text-align:center; color:#64748b;">
                    <?php esc_html_e('Esta oferta académica aún no tiene cohortes creadas.', 'flacso-uruguay'); ?>
                </div>
            <?php endif; ?>

            <a class="button button-primary" href="<?php echo esc_url($add_url); ?>">
                ➕ <?php esc_html_e('Agregar nueva cohorte para esta oferta', 'flacso-uruguay'); ?>
            </a>
        </div>
        <?php
    }
}
