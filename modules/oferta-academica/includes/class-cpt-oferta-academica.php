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
                $new_columns['cohortes'] = __('Cohortes y preinscripción', 'flacso-uruguay');
            }
        }
        return $new_columns;
    }

    public static function render_column(string $column, int $post_id): void {
        if ($column === 'cohortes') {
            $cohortes = get_posts([
                'post_type'      => 'cohorte',
                'posts_per_page' => -1,
                'meta_query'     => [[
                    'key'     => 'oferta_academica_id',
                    'value'   => $post_id,
                    'compare' => '=',
                    'type'    => 'NUMERIC',
                ]],
            ]);

            usort($cohortes, static function ($left, $right): int {
                $left_number = (int) get_post_meta($left->ID, 'numero', true);
                $right_number = (int) get_post_meta($right->ID, 'numero', true);
                return $right_number <=> $left_number;
            });

            if (!empty($cohortes)) {
                echo '<div class="flacso-cohort-list">';
                foreach ($cohortes as $c) {
                    $num = (int) get_post_meta($c->ID, 'numero', true);
                    $roman = $num > 0 ? FLACSO_Cohorte::to_roman($num) : '';
                    $cohort_label = $roman !== ''
                        ? sprintf(__('Cohorte %s', 'flacso-uruguay'), $roman)
                        : (get_the_title($c->ID) ?: __('Cohorte sin número', 'flacso-uruguay'));
                    $estado = FLACSO_Cohorte::sanitize_state(get_post_meta($c->ID, 'estado', true));
                    $edit_c_url = get_edit_post_link($c->ID);
                    $configured = metadata_exists('post', $c->ID, 'preinscripcion_habilitada');
                    $explicitly_open = $configured
                        && rest_sanitize_boolean(get_post_meta($c->ID, 'preinscripcion_habilitada', true));
                    $legacy_open = !$configured && FLACSO_Cohorte::accepts_registration($c->ID);
                    $url = (string) get_post_meta($c->ID, 'link_preinscripcion', true);
                    if ($url === '') {
                        $url = FLACSO_Django_API_Client::url_preinscripcion_oferta($post_id);
                    }

                    echo '<div class="flacso-cohort-card">';
                    echo '<div class="flacso-cohort-card__heading">';
                    echo '<a class="flacso-cohort-card__title" href="' . esc_url($edit_c_url) . '">' . esc_html($cohort_label) . '</a>';
                    echo '<span class="flacso-cohort-state flacso-cohort-state--' . esc_attr($estado) . '">' . esc_html(self::academic_state_label($estado)) . '</span>';
                    echo '</div>';

                    if ($explicitly_open) {
                        echo '<span class="flacso-pre-status flacso-pre-status--open"><span aria-hidden="true">●</span> ' . esc_html__('Preinscripción abierta', 'flacso-uruguay') . '</span>';
                    } elseif ($legacy_open) {
                        echo '<span class="flacso-pre-status flacso-pre-status--legacy"><span aria-hidden="true">●</span> ' . esc_html__('Apertura heredada', 'flacso-uruguay') . '</span>';
                    } elseif ($configured) {
                        echo '<span class="flacso-pre-status flacso-pre-status--closed"><span aria-hidden="true">○</span> ' . esc_html__('Preinscripción cerrada', 'flacso-uruguay') . '</span>';
                    } else {
                        echo '<span class="flacso-pre-status flacso-pre-status--unset"><span aria-hidden="true">○</span> ' . esc_html__('No configurada', 'flacso-uruguay') . '</span>';
                    }

                    if (current_user_can('edit_post', $c->ID)) {
                        $nonce = wp_create_nonce('flacso_preinscripcion_nonce');
                        echo '<div class="flacso-cohort-card__actions">';
                        if ($explicitly_open || $legacy_open) {
                            if ($url !== '') {
                                echo '<a class="button button-small" href="' . esc_url($url) . '" target="_blank" rel="noopener noreferrer">' . esc_html__('Ver', 'flacso-uruguay') . '</a>';
                            }
                            if ($legacy_open) {
                                echo self::preinscription_action_button(
                                    $c->ID,
                                    $nonce,
                                    'flacso_abrir_preinscripcion_cohorte',
                                    __('Confirmar apertura', 'flacso-uruguay'),
                                    'button-primary'
                                );
                            }
                            echo self::preinscription_action_button(
                                $c->ID,
                                $nonce,
                                'flacso_cerrar_preinscripcion_cohorte',
                                __('Cerrar', 'flacso-uruguay'),
                                'flacso-button-danger'
                            );
                        } else {
                            echo self::preinscription_action_button(
                                $c->ID,
                                $nonce,
                                'flacso_abrir_preinscripcion_cohorte',
                                __('Abrir preinscripción', 'flacso-uruguay'),
                                'button-primary'
                            );
                        }
                        echo '</div>';
                        echo '<div class="flacso-cohort-card__notice" role="status" aria-live="polite"></div>';
                    }
                    echo '</div>';
                }
                echo '</div>';
            } else {
                echo '<span class="flacso-cohort-empty">' . esc_html__('Sin cohortes', 'flacso-uruguay') . '</span>';
            }

            $add_url = admin_url('post-new.php?post_type=cohorte&oferta_academica_id=' . $post_id);
            echo '<div class="flacso-cohort-add"><a class="button button-small" href="' . esc_url($add_url) . '"><span class="dashicons dashicons-plus-alt2" aria-hidden="true"></span> ' . esc_html__('Nueva cohorte', 'flacso-uruguay') . '</a></div>';
        }
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
            .post-type-oferta-academica .column-cohortes { width: 330px; }
            .flacso-cohort-list { display: grid; gap: 8px; min-width: 275px; }
            .flacso-cohort-card { padding: 10px; border: 1px solid #dcdcde; border-radius: 6px; background: #fff; }
            .flacso-cohort-card__heading { display: flex; align-items: center; flex-wrap: wrap; gap: 6px; margin-bottom: 7px; }
            .flacso-cohort-card__title { font-weight: 650; }
            .flacso-cohort-state,
            .flacso-pre-status { display: inline-flex; align-items: center; gap: 4px; width: fit-content; border-radius: 999px; padding: 2px 7px; font-size: 11px; font-weight: 650; line-height: 1.55; }
            .flacso-cohort-state { color: #3c434a; background: #f0f0f1; }
            .flacso-cohort-state--en_curso { color: #0f5132; background: #d1e7dd; }
            .flacso-cohort-state--finalizada { color: #50575e; background: #e2e3e5; }
            .flacso-cohort-state--cancelada { color: #842029; background: #f8d7da; }
            .flacso-pre-status--open { color: #116329; background: #edfaef; }
            .flacso-pre-status--legacy { color: #7a4b00; background: #fff6d6; }
            .flacso-pre-status--closed,
            .flacso-pre-status--unset { color: #646970; background: #f6f7f7; }
            .flacso-cohort-card__actions { display: flex; flex-wrap: wrap; gap: 5px; margin-top: 8px; }
            .flacso-cohort-card__actions .button { min-height: 26px; line-height: 24px; }
            .flacso-button-danger { color: #b32d2e !important; border-color: #d63638 !important; }
            .flacso-cohort-card__notice { display: none; margin-top: 7px; font-size: 12px; line-height: 1.35; }
            .flacso-cohort-card__notice.is-success { display: block; color: #116329; }
            .flacso-cohort-card__notice.is-error { display: block; color: #b32d2e; }
            .flacso-cohort-add { margin-top: 8px; }
            .flacso-cohort-add .dashicons { width: 15px; height: 15px; margin-top: 4px; font-size: 15px; }
            .flacso-cohort-empty { color: #646970; }
            @media (max-width: 1100px) {
                .post-type-oferta-academica .column-cohortes { width: 285px; }
                .flacso-cohort-list { min-width: 235px; }
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
                var card = button.closest('.flacso-cohort-card');
                var notice = card.find('.flacso-cohort-card__notice');
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
