<?php

/**
 * Render Callback para el bloque Seminarios Lista
 */

if (!defined('ABSPATH')) {
    exit;
}

function flacso_render_seminarios_lista_block($attributes)
{
    $legacy_programa = !empty($attributes['programa']) ? sanitize_text_field($attributes['programa']) : '';
    $posgrado = !empty($attributes['posgrado']) ? sanitize_text_field($attributes['posgrado']) : '';
    $per_page = !empty($attributes['perPage']) ? absint($attributes['perPage']) : 12;
    $layout = !empty($attributes['layout']) ? sanitize_text_field($attributes['layout']) : 'grid';
    $show_filters = isset($attributes['showFilters']) ? (bool) $attributes['showFilters'] : true;
    $show_search = isset($attributes['showSearch']) ? (bool) $attributes['showSearch'] : true;
    $order_by = !empty($attributes['orderBy']) ? sanitize_text_field($attributes['orderBy']) : 'date';
    $order = !empty($attributes['order']) ? sanitize_text_field($attributes['order']) : 'DESC';

    $current_posgrado = isset($_GET['posgrado']) ? sanitize_text_field(wp_unslash($_GET['posgrado'])) : '';
    if ($current_posgrado === '' && isset($_GET['programa'])) {
        $current_posgrado = sanitize_text_field(wp_unslash($_GET['programa']));
    }
    if ($current_posgrado === '') {
        $current_posgrado = $posgrado !== '' ? $posgrado : $legacy_programa;
    }

    $current_search = isset($_GET['s']) ? sanitize_text_field(wp_unslash($_GET['s'])) : '';
    $paged = (int) get_query_var('paged');
    if ($paged <= 0) {
        $paged = 1;
    }

    $statuses = current_user_can('manage_options')
        ? array('publish', 'private')
        : array('publish');

    $posgrados = get_posts(array(
        'post_type'      => 'oferta-academica',
        'post_status'    => $statuses,
        'posts_per_page' => -1,
        'orderby'        => 'title',
        'order'          => 'ASC',
    ));

    $resolve_oferta_id = static function ($value) use ($posgrados): int {
        if ($value === null || $value === '') {
            return 0;
        }

        if (is_numeric($value)) {
            $id = absint($value);
            foreach ($posgrados as $item) {
                if ((int) $item->ID === $id) {
                    return $id;
                }
            }
            return 0;
        }

        $slug = sanitize_title((string) $value);
        foreach ($posgrados as $item) {
            if ($item->post_name === $slug) {
                return (int) $item->ID;
            }
        }

        return 0;
    };

    $selected_posgrado_id = $resolve_oferta_id($current_posgrado);
    $seminarios_filtrados_por_oferta = array();
    if ($selected_posgrado_id > 0) {
        $seminarios_ids = get_post_meta($selected_posgrado_id, '_oferta_seminarios_ids', true);
        if (is_array($seminarios_ids) && !empty($seminarios_ids)) {
            $seminarios_filtrados_por_oferta = array_values(array_filter(array_unique(array_map('intval', $seminarios_ids))));
        }
    }

    $args = array(
        'post_type'      => 'seminario',
        'posts_per_page' => $per_page,
        'paged'          => $paged,
        'orderby'        => $order_by,
        'order'          => $order,
        'post_status'    => 'publish',
    );

    if ($order_by === 'periodo_inicio') {
        $args['meta_key'] = '_seminario_periodo_inicio';
        $args['orderby'] = 'meta_value';
    }

    if ($selected_posgrado_id > 0) {
        $args['post__in'] = !empty($seminarios_filtrados_por_oferta) ? $seminarios_filtrados_por_oferta : array(0);
    }

    if (!empty($current_search)) {
        $args['s'] = $current_search;
    }

    $query = new WP_Query($args);

    $search_id = wp_unique_id('seminario-search-');
    $posgrado_id = wp_unique_id('seminario-posgrado-');

    $reset_url = get_permalink();
    if (empty($reset_url)) {
        $reset_url = remove_query_arg(array('programa', 'posgrado', 's', 'paged'));
    }

    ob_start();
    ?>
    <section class="flacso-seminarios-lista layout-<?php echo esc_attr($layout); ?>" aria-label="<?php esc_attr_e('Listado de seminarios', 'flacso-uruguay'); ?>">

        <?php if ($show_filters || $show_search) : ?>
            <div class="seminarios-filters">
                <form method="get" class="seminarios-filter-form" aria-label="<?php esc_attr_e('Filtros de seminarios', 'flacso-uruguay'); ?>">

                    <?php if ($show_search) : ?>
                        <div class="filter-search filter-field">
                            <label for="<?php echo esc_attr($search_id); ?>" class="filter-field__label">
                                <?php esc_html_e('Buscar', 'flacso-uruguay'); ?>
                            </label>
                            <input
                                id="<?php echo esc_attr($search_id); ?>"
                                type="text"
                                name="s"
                                placeholder="<?php esc_attr_e('Buscar seminarios...', 'flacso-uruguay'); ?>"
                                value="<?php echo esc_attr($current_search); ?>"
                                class="seminario-search-input"
                            >
                        </div>
                    <?php endif; ?>

                    <?php if ($show_filters) : ?>
                        <div class="filter-taxonomies">
                            <?php if (!empty($posgrados)) : ?>
                                <div class="filter-field">
                                    <label for="<?php echo esc_attr($posgrado_id); ?>" class="filter-field__label">
                                        <?php esc_html_e('Posgrado', 'flacso-uruguay'); ?>
                                    </label>
                                    <select id="<?php echo esc_attr($posgrado_id); ?>" name="posgrado" class="seminario-filter-select">
                                        <option value=""><?php esc_html_e('Todos los posgrados', 'flacso-uruguay'); ?></option>
                                        <?php foreach ($posgrados as $item) : ?>
                                            <?php
                                            $selected = ((string) $selected_posgrado_id === (string) $item->ID) || ($current_posgrado === $item->post_name);
                                            ?>
                                            <option value="<?php echo esc_attr($item->ID); ?>" <?php selected($selected, true); ?>>
                                                <?php echo esc_html(get_the_title($item)); ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                            <?php endif; ?>
                        </div>
                    <?php endif; ?>

                    <button type="submit" class="button seminario-filter-submit">
                        <?php esc_html_e('Filtrar', 'flacso-uruguay'); ?>
                    </button>

                    <?php if (!empty($current_posgrado) || !empty($current_search)) : ?>
                        <a href="<?php echo esc_url($reset_url); ?>" class="seminario-filter-reset">
                            <?php esc_html_e('Limpiar filtros', 'flacso-uruguay'); ?>
                        </a>
                    <?php endif; ?>
                </form>
            </div>
        <?php endif; ?>

        <?php if ($query->have_posts()) : ?>
            <div class="seminarios-grid <?php echo esc_attr($layout); ?>-layout">
                <?php while ($query->have_posts()) : $query->the_post(); ?>
                    <?php
                    $seminario_id = get_the_ID();
                    $nombre = get_post_meta($seminario_id, '_seminario_nombre', true);
                    $periodo_inicio = get_post_meta($seminario_id, '_seminario_periodo_inicio', true);
                    $periodo_fin = get_post_meta($seminario_id, '_seminario_periodo_fin', true);
                    $creditos = get_post_meta($seminario_id, '_seminario_creditos', true);
                    $modalidad = get_post_meta($seminario_id, '_seminario_modalidad', true);
                    $presentacion = get_post_meta($seminario_id, '_seminario_presentacion_seminario', true);
                    $posgrados_list = class_exists('Seminario_Taxonomies')
                        ? Seminario_Taxonomies::get_related_ofertas($seminario_id)
                        : array();
                    ?>

                    <article class="seminario-card">
                        <?php if (has_post_thumbnail()) : ?>
                            <div class="seminario-thumbnail">
                                <a href="<?php the_permalink(); ?>">
                                    <?php the_post_thumbnail('medium_large'); ?>
                                </a>
                            </div>
                        <?php endif; ?>

                        <div class="seminario-content">
                            <h3 class="seminario-title">
                                <a href="<?php the_permalink(); ?>">
                                    <?php echo esc_html($nombre ? $nombre : get_the_title()); ?>
                                </a>
                            </h3>

                            <div class="seminario-meta-list">
                                <?php if (!empty($posgrados_list)) : ?>
                                    <div class="seminario-meta programa">
                                        <span class="meta-label"><?php esc_html_e('Posgrado:', 'flacso-uruguay'); ?></span>
                                        <?php
                                        $posgrados_names = array_map(static function ($item) {
                                            return $item['title'];
                                        }, $posgrados_list);
                                        echo esc_html(implode(', ', $posgrados_names));
                                        ?>
                                    </div>
                                <?php endif; ?>

                                <?php if ($periodo_inicio || $periodo_fin) : ?>
                                    <div class="seminario-meta periodo">
                                        <span class="meta-label"><?php esc_html_e('Periodo:', 'flacso-uruguay'); ?></span>
                                        <?php
                                        if ($periodo_inicio && $periodo_fin) {
                                            echo esc_html(date_i18n('d/m/Y', strtotime($periodo_inicio)) . ' - ' . date_i18n('d/m/Y', strtotime($periodo_fin)));
                                        } elseif ($periodo_inicio) {
                                            echo esc_html(date_i18n('d/m/Y', strtotime($periodo_inicio)));
                                        }
                                        ?>
                                    </div>
                                <?php endif; ?>

                                <?php if ($creditos) : ?>
                                    <div class="seminario-meta creditos">
                                        <span class="meta-label"><?php esc_html_e('Creditos:', 'flacso-uruguay'); ?></span>
                                        <?php echo esc_html($creditos); ?>
                                    </div>
                                <?php endif; ?>

                                <?php if ($modalidad) : ?>
                                    <div class="seminario-meta modalidad">
                                        <span class="meta-label"><?php esc_html_e('Modalidad:', 'flacso-uruguay'); ?></span>
                                        <?php echo esc_html($modalidad); ?>
                                    </div>
                                <?php endif; ?>
                            </div>

                            <?php if ($presentacion) : ?>
                                <div class="seminario-excerpt">
                                    <?php echo esc_html(wp_trim_words(wp_strip_all_tags($presentacion), 30, '...')); ?>
                                </div>
                            <?php endif; ?>

                            <a href="<?php the_permalink(); ?>" class="seminario-link">
                                <?php esc_html_e('Ver detalles', 'flacso-uruguay'); ?>
                                <span aria-hidden="true">&rarr;</span>
                            </a>
                        </div>
                    </article>
                <?php endwhile; ?>
            </div>

            <?php
            if ($query->max_num_pages > 1) :
                $big = 999999999;
                echo '<div class="seminarios-pagination">';
                echo paginate_links(array(
                    'base'      => str_replace($big, '%#%', esc_url(get_pagenum_link($big))),
                    'format'    => '?paged=%#%',
                    'current'   => max(1, $paged),
                    'total'     => $query->max_num_pages,
                    'prev_text' => '&larr; ' . __('Anterior', 'flacso-uruguay'),
                    'next_text' => __('Siguiente', 'flacso-uruguay') . ' &rarr;',
                ));
                echo '</div>';
            endif;
            ?>
        <?php else : ?>
            <div class="seminarios-no-results" role="status">
                <p><?php esc_html_e('No se encontraron seminarios con los filtros seleccionados.', 'flacso-uruguay'); ?></p>
            </div>
        <?php endif; ?>

        <?php wp_reset_postdata(); ?>
    </section>
    <?php

    return ob_get_clean();
}
