<?php

if (!defined('ABSPATH')) {
    exit;
}

if (!function_exists('flacso_eventos_get_uncropped_thumbnail_url')) {
    /**
     * Devuelve una imagen destacada sin recorte forzado (prioriza original/full).
     */
    function flacso_eventos_get_uncropped_thumbnail_url(int $post_id, string $placeholder = ''): string
    {
        $thumb_id = get_post_thumbnail_id($post_id);
        if (!$thumb_id) {
            return $placeholder;
        }

        $candidates = [
            wp_get_original_image_url($thumb_id),
            wp_get_attachment_image_url($thumb_id, 'full'),
            wp_get_attachment_image_url($thumb_id, 'large'),
            wp_get_attachment_image_url($thumb_id, 'medium_large'),
        ];

        foreach ($candidates as $candidate) {
            if (!empty($candidate)) {
                return (string) $candidate;
            }
        }

        return $placeholder;
    }
}

if (!function_exists('flacso_section_eventos_get_items')) {
    /**
     * Devuelve los datos normalizados de próximos eventos para renderizado React/PHP.
     *
     * @param int $max_items Cantidad máxima de eventos.
     *
     * @return array<int,array<string,mixed>>
     */
    function flacso_section_eventos_get_items($max_items = 6): array
    {
        $tz        = wp_timezone();
        $now_dt    = new \DateTimeImmutable('now', $tz);
        $today_ymd = date_i18n('Y-m-d', $now_dt->getTimestamp());
        $today_dt  = $now_dt->setTime(0, 0, 0);
        $charset   = get_bloginfo('charset') ?: 'UTF-8';

        try {
            $query_args = [
                'post_type'      => 'evento',
                'posts_per_page' => (int) $max_items,
                'post_status'    => 'publish',
                'meta_key'       => 'evento_inicio_fecha',
                'orderby'        => 'meta_value',
                'order'          => 'ASC',
                'meta_type'      => 'DATE',
                'meta_query'     => [
                    'relation' => 'OR',
                    [
                        'key'     => 'evento_fin_fecha',
                        'value'   => $today_ymd,
                        'compare' => '>=',
                        'type'    => 'DATE',
                    ],
                    [
                        'relation' => 'AND',
                        [
                            'key'     => 'evento_fin_fecha',
                            'compare' => 'NOT EXISTS',
                        ],
                        [
                            'key'     => 'evento_inicio_fecha',
                            'value'   => $today_ymd,
                            'compare' => '>=',
                            'type'    => 'DATE',
                        ],
                    ],
                ],
                'fields' => 'ids',
            ];

            $query_args = apply_filters('flacso_eventos_query_args', $query_args);
            $query = new WP_Query($query_args);
            if (!$query->have_posts()) {
                return [];
            }

            $items = [];

            foreach ($query->posts as $evento_id) {
                $evento_id = (int) $evento_id;
                $inicio_fecha = get_post_meta($evento_id, 'evento_inicio_fecha', true);
                if (!$inicio_fecha) {
                    continue;
                }

                $inicio_hora = get_post_meta($evento_id, 'evento_inicio_hora', true);
                $fin_fecha = get_post_meta($evento_id, 'evento_fin_fecha', true);
                $fin_hora = get_post_meta($evento_id, 'evento_fin_hora', true);

                try {
                    $inicio_dt = new \DateTimeImmutable(
                        trim($inicio_fecha . ' ' . ($inicio_hora ?: '00:00')),
                        $tz
                    );
                } catch (\Exception $e) {
                    $inicio_dt = null;
                }

                if (!$inicio_dt) {
                    continue;
                }

                $fin_dt = null;
                if (!empty($fin_fecha)) {
                    try {
                        $fin_dt = new \DateTimeImmutable(
                            trim($fin_fecha . ' ' . ($fin_hora ?: '23:59')),
                            $tz
                        );
                    } catch (\Exception $e) {
                        $fin_dt = null;
                    }
                }

                $fin_dt_filter = $fin_dt ?: $inicio_dt->setTime(23, 59, 59);
                if ($fin_dt_filter < $now_dt) {
                    continue;
                }

                $inicio_dia = $inicio_dt->setTime(0, 0, 0);
                $dias_restantes = (int) $today_dt->diff($inicio_dia)->format('%r%a');
                $is_running = ($inicio_dt <= $now_dt && $fin_dt_filter >= $now_dt);

                if ($is_running || $dias_restantes <= 0) {
                    $status = __('Hoy', 'flacso-main-page');
                    $status_class = 'is-today';
                } elseif ($dias_restantes === 1) {
                    $status = __('Mañana', 'flacso-main-page');
                    $status_class = 'is-tomorrow';
                } elseif ($dias_restantes > 1) {
                    $status = sprintf(
                        _n('Falta %s día', 'Faltan %s días', $dias_restantes, 'flacso-main-page'),
                        number_format_i18n($dias_restantes)
                    );
                    $status_class = 'is-future';
                } else {
                    $status = __('En curso', 'flacso-main-page');
                    $status_class = 'is-running';
                }

                $post_asociado = get_post_meta($evento_id, 'evento_post_asociado', true);
                $detalle_id = ($post_asociado && get_post($post_asociado)) ? (int) $post_asociado : $evento_id;

                $custom_title = trim((string) get_post_meta($evento_id, 'evento_display_title', true));
                $title = $custom_title ?: get_the_title($detalle_id);
                if (!$title) {
                    continue;
                }

                $excerpt = get_the_excerpt($detalle_id);
                if (!$excerpt) {
                    $excerpt = get_post_field('post_content', $detalle_id);
                }
                $excerpt = preg_replace('/<(br|p|\\/p|li|\\/li|h[1-6])[^>]*>/i', ' ', (string) $excerpt);
                $excerpt = wp_trim_words(
                    wp_strip_all_tags(wp_kses_post((string) $excerpt)),
                    22
                );
                $excerpt = html_entity_decode((string) $excerpt, ENT_QUOTES | ENT_HTML5, 'UTF-8');

                $weekday = date_i18n('l', $inicio_dt->getTimestamp());
                $month_name = date_i18n('F', $inicio_dt->getTimestamp());
                if (function_exists('mb_convert_case')) {
                    $weekday = mb_convert_case((string) $weekday, MB_CASE_TITLE, $charset);
                    $month_name = mb_convert_case((string) $month_name, MB_CASE_TITLE, $charset);
                } else {
                    $weekday = ucfirst((string) $weekday);
                    $month_name = ucfirst((string) $month_name);
                }

                $display_day = date_i18n('j', $inicio_dt->getTimestamp());
                $month = sprintf(__('de %s', 'flacso-main-page'), $month_name);
                $hora_legible = $inicio_hora
                    ? wp_date(get_option('time_format') ?: 'H:i', $inicio_dt->getTimestamp())
                    : '';
                $range = ucfirst(date_i18n('l j \\d\\e F', $inicio_dt->getTimestamp()));
                if ($hora_legible) {
                    $range .= ' a ' . $hora_legible;
                }

                $duration_label = '';
                $diff_secs = max(0, $fin_dt_filter->getTimestamp() - $inicio_dt->getTimestamp());
                $duration_hours = (int) ceil($diff_secs / 3600);
                if ($duration_hours > 0 && $duration_hours < 24) {
                    $duration_label = sprintf(
                        _n('Dura %s hora', 'Dura %s horas', $duration_hours, 'flacso-main-page'),
                        number_format_i18n($duration_hours)
                    );
                } elseif ($duration_hours >= 24) {
                    $duration_days = max(1, (int) ceil($diff_secs / 86400));
                    $duration_label = sprintf(
                        _n('Dura %s día', 'Dura %s días', $duration_days, 'flacso-main-page'),
                        number_format_i18n($duration_days)
                    );
                }

                $placeholder_svg = 'data:image/svg+xml;charset=UTF-8,' . rawurlencode('<svg xmlns="http://www.w3.org/2000/svg" width="800" height="800"><defs><linearGradient id="g" x1="0" y1="0" x2="1" y2="1"><stop offset="0%" stop-color="%23f3f4f6"/><stop offset="100%" stop-color="%23e5e7eb"/></linearGradient></defs><rect width="800" height="800" fill="url(#g)"/><text x="50%" y="50%" dominant-baseline="middle" text-anchor="middle" fill="%239ca3af" font-family="Arial" font-size="32">Evento</text></svg>');
                $thumbnail = flacso_eventos_get_uncropped_thumbnail_url($detalle_id, $placeholder_svg);

                $items[] = [
                    'id' => $evento_id,
                    'link' => get_permalink($detalle_id),
                    'title' => $title,
                    'excerpt' => $excerpt,
                    'weekday' => $weekday,
                    'day' => $display_day,
                    'month' => $month,
                    'status' => $status,
                    'class' => $status_class,
                    'range' => $range,
                    'hora' => $hora_legible,
                    'remaining_days' => $dias_restantes,
                    'duration' => $duration_label,
                    'thumbnail' => $thumbnail,
                    'datetime_iso' => $inicio_dt->format(DATE_ATOM),
                ];
            }

            if (empty($items)) {
                return [];
            }

            return apply_filters('flacso_eventos_items', $items);
        } catch (\Throwable $e) {
            if (defined('WP_DEBUG') && WP_DEBUG) {
                error_log('[FLACSO] Error al obtener items de eventos: ' . $e->getMessage());
            }
            return [];
        }
    }
}

if (!function_exists('flacso_section_eventos_render')) {
    /**
     * Renderiza el bloque de "Próximos eventos" para el CPT 'evento'.
     *
     * @param int $max_items Cantidad máxima de eventos a mostrar.
     *
     * @return string
     */
    function flacso_section_eventos_render($max_items = 6): string {
        $tz         = wp_timezone();
        $now_dt     = new \DateTimeImmutable('now', $tz);
        $today_ymd  = date_i18n('Y-m-d', $now_dt->getTimestamp());
        $today_dt   = $now_dt->setTime(0, 0, 0);
        $charset    = get_bloginfo('charset') ?: 'UTF-8';

        try {
            $query_args = [
                'post_type'      => 'evento',
                'posts_per_page' => $max_items,
                'post_status'    => 'publish',
                'meta_key'       => 'evento_inicio_fecha',
                'orderby'        => 'meta_value',
                'order'          => 'ASC',
                'meta_type'      => 'DATE',
                'meta_query'     => [
                    'relation' => 'OR',
                    [
                        'key'     => 'evento_fin_fecha',
                        'value'   => $today_ymd,
                        'compare' => '>=',
                        'type'    => 'DATE',
                    ],
                    [
                        'relation' => 'AND',
                        [
                            'key'     => 'evento_fin_fecha',
                            'compare' => 'NOT EXISTS',
                        ],
                        [
                            'key'     => 'evento_inicio_fecha',
                            'value'   => $today_ymd,
                            'compare' => '>=',
                            'type'    => 'DATE',
                        ],
                    ],
                ],
                'fields' => 'ids',
            ];

            $query_args = apply_filters('flacso_eventos_query_args', $query_args);
            $query      = new WP_Query($query_args);

            if (!$query->have_posts()) {
                return '';
            }

            $event_data = [];

            foreach ($query->posts as $evento_id) {
                $inicio_fecha = get_post_meta($evento_id, 'evento_inicio_fecha', true);
                if (!$inicio_fecha) {
                    continue;
                }

                $inicio_hora = get_post_meta($evento_id, 'evento_inicio_hora', true);
                $fin_fecha   = get_post_meta($evento_id, 'evento_fin_fecha', true);
                $fin_hora    = get_post_meta($evento_id, 'evento_fin_hora', true);

                try {
                    $inicio_dt = new \DateTimeImmutable(
                        trim($inicio_fecha . ' ' . ($inicio_hora ?: '00:00')),
                        $tz
                    );
                } catch (\Exception $e) {
                    $inicio_dt = null;
                }

                if (!$inicio_dt) {
                    continue;
                }

                $fin_dt = null;
                if (!empty($fin_fecha)) {
                    try {
                        $fin_dt = new \DateTimeImmutable(
                            trim($fin_fecha . ' ' . ($fin_hora ?: '23:59')),
                            $tz
                        );
                    } catch (\Exception $e) {
                        $fin_dt = null;
                    }
                }

                $fin_dt_filter = $fin_dt ?: $inicio_dt->setTime(23, 59, 59);

                if ($fin_dt_filter < $now_dt) {
                    continue;
                }

                $inicio_dia     = $inicio_dt->setTime(0, 0, 0);
                $dias_restantes = (int) $today_dt->diff($inicio_dia)->format('%r%a');
                $is_running     = ($inicio_dt <= $now_dt && $fin_dt_filter >= $now_dt);

                if ($is_running || $dias_restantes <= 0) {
                    $status       = __('Hoy', 'flacso-main-page');
                    $status_class = 'is-today';
                } elseif ($dias_restantes === 1) {
                    $status       = __('Mañana', 'flacso-main-page');
                    $status_class = 'is-tomorrow';
                } elseif ($dias_restantes > 1) {
                    $status       = sprintf(
                        _n(
                            'Falta %s día',
                            'Faltan %s días',
                            $dias_restantes,
                            'flacso-main-page'
                        ),
                        number_format_i18n($dias_restantes)
                    );
                    $status_class = 'is-future';
                } else {
                    $status       = __('En curso', 'flacso-main-page');
                    $status_class = 'is-running';
                }

                // Obtener el post asociado si existe, de lo contrario usar el evento mismo
                $post_asociado = get_post_meta($evento_id, 'evento_post_asociado', true);
                $detalle_id = ($post_asociado && get_post($post_asociado)) ? $post_asociado : $evento_id;

                $custom_title = trim((string) get_post_meta($evento_id, 'evento_display_title', true));
                $title        = $custom_title ?: get_the_title($detalle_id);
                if (!$title) {
                    continue;
                }

                $excerpt = get_the_excerpt($detalle_id);
                if (!$excerpt) {
                    $excerpt = get_post_field('post_content', $detalle_id);
                }
                // Normalize spacing for block tags that get removed later so words don't concatenate.
                $excerpt = preg_replace('/<(br|p|\\/p|li|\\/li|h[1-6])[^>]*>/i', ' ', $excerpt);
                $excerpt = wp_trim_words(
                    wp_strip_all_tags(wp_kses_post($excerpt)),
                    22
                );
                $excerpt = html_entity_decode((string) $excerpt, ENT_QUOTES | ENT_HTML5, 'UTF-8');

                $weekday = date_i18n('l', $inicio_dt->getTimestamp());
                $month_name = date_i18n('F', $inicio_dt->getTimestamp());
                if (function_exists('mb_convert_case')) {
                    $weekday = mb_convert_case((string) $weekday, MB_CASE_TITLE, $charset);
                    $month_name = mb_convert_case((string) $month_name, MB_CASE_TITLE, $charset);
                } else {
                    $weekday = ucfirst((string) $weekday);
                    $month_name = ucfirst((string) $month_name);
                }

                $display_day  = date_i18n('j', $inicio_dt->getTimestamp());
                $month        = sprintf(__('de %s', 'flacso-main-page'), $month_name);
                $hora_legible = $inicio_hora
                    ? wp_date(get_option('time_format') ?: 'H:i', $inicio_dt->getTimestamp())
                    : '';
                $range = ucfirst(date_i18n('l j \\d\\e F', $inicio_dt->getTimestamp()));
                if ($hora_legible) {
                    $range .= ' a ' . $hora_legible;
                }

                
                $duration_label = '';
                if ($fin_dt_filter) {
                    $diff_secs = max(0, $fin_dt_filter->getTimestamp() - $inicio_dt->getTimestamp());
                    $duration_hours = (int) ceil($diff_secs / 3600);
                    if ($duration_hours > 0 && $duration_hours < 24) {
                        $duration_label = sprintf(
                            _n(
                                'Dura %s hora',
                                'Dura %s horas',
                                $duration_hours,
                                'flacso-main-page'
                            ),
                            number_format_i18n($duration_hours)
                        );
                    } else {
                        $duration_days = max(1, (int) ceil($diff_secs / 86400));
                        $duration_label = sprintf(
                            _n(
                                'Dura %s día',
                                'Dura %s días',
                                $duration_days,
                                'flacso-main-page'
                            ),
                            number_format_i18n($duration_days)
                        );
                    }
                }

                $placeholder_svg = 'data:image/svg+xml;charset=UTF-8,' . rawurlencode('<svg xmlns="http://www.w3.org/2000/svg" width="800" height="800"><defs><linearGradient id="g" x1="0" y1="0" x2="1" y2="1"><stop offset="0%" stop-color="%23f3f4f6"/><stop offset="100%" stop-color="%23e5e7eb"/></linearGradient></defs><rect width="800" height="800" fill="url(#g)"/><text x="50%" y="50%" dominant-baseline="middle" text-anchor="middle" fill="%239ca3af" font-family="Arial" font-size="32">Evento</text></svg>');
                $thumbnail = flacso_eventos_get_uncropped_thumbnail_url($detalle_id, $placeholder_svg);

                $event_data[] = [
                    'link'      => get_permalink($detalle_id),
                    'title'     => $title,
                    'excerpt'   => $excerpt,
                    'weekday'   => $weekday,
                    'day'       => $display_day,
                    'month'     => $month,
                    'status'    => $status,
                    'class'     => $status_class,
                    'range'     => $range,
                    'hora'      => $hora_legible,
                    'remaining_days' => $dias_restantes,
                    'duration'  => $duration_label,
                    'thumbnail' => $thumbnail,
                ];
            }

            if (empty($event_data)) {
                return '';
            }

            $event_data = apply_filters('flacso_eventos_items', $event_data);

            $hero_items  = array_slice($event_data, 0, 2);
            $small_items = array_slice($event_data, 2);
            $section_id  = wp_unique_id('flc-eventos-grid-');

            ob_start(); ?>
            <section class="flc-eventos-grid flacso-home-block flacso-home-block--eventos" aria-labelledby="<?php echo esc_attr($section_id); ?>">
                <div class="flacso-content-shell">
                    <header class="flacso-home-block__header flc-eventos-grid__header">
                        <h2 class="flc-eventos-grid__title" id="<?php echo esc_attr($section_id); ?>">
                            <?php esc_html_e('Próximos eventos', 'flacso-main-page'); ?>
                        </h2>
                    </header>

                    <?php if (!empty($hero_items)) : ?>
                        <div class="flc-eventos-hero">
                            <?php foreach ($hero_items as $item) :
                                $has_image = !empty($item['thumbnail']); ?>
                            <article class="flc-eventos-hero-card flc-eventos-hero-card--modern <?php echo esc_attr($item['class']); ?>">
                                <?php if ($has_image) : ?>
                                    <div class="flc-eventos-hero-card__media">
                                        <img src="<?php echo esc_url($item['thumbnail']); ?>"
                                             alt="<?php echo esc_attr($item['title']); ?>"
                                             loading="lazy">
                                    </div>
                                <?php endif; ?>
                                <div class="flc-eventos-hero-card__content">
                                    <div class="flc-eventos-hero-card__date-row">
                                        <div class="flc-eventos-hero-card__date-badge" aria-hidden="true">
                                            <span class="weekday"><?php echo esc_html($item['weekday']); ?></span>
                                            <span class="day"><?php echo esc_html($item['day']); ?></span>
                                            <span class="month"><?php echo esc_html($item['month']); ?></span>
                                        </div>
                                        <span class="status-pill"><?php echo esc_html($item['status']); ?></span>
                                        <?php if (!empty($item['hora'])) : ?>
                                            <span class="hora-pill hora-pill--inline"><?php echo esc_html($item['hora']); ?></span>
                                        <?php endif; ?>
                                        <?php if (!empty($item['duration'])) : ?>
                                            <span class="duration-pill"><?php echo esc_html($item['duration']); ?></span>
                                        <?php endif; ?>
                                    </div>
                                    <h4 class="flc-eventos-hero-card__title">
                                        <?php echo esc_html($item['title']); ?>
                                    </h4>
                                    <?php if (!empty($item['excerpt'])) : ?>
                                        <p class="evento-description"><?php echo esc_html($item['excerpt']); ?></p>
                                    <?php endif; ?>
                                </div>
                                <a class="flc-eventos-hero-card__link"
                                   href="<?php echo esc_url($item['link']); ?>"
                                   aria-label="<?php echo esc_attr(sprintf(__('Ver detalle del evento %s', 'flacso-main-page'), $item['title'])); ?>"></a>
                            </article>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>

                    <?php if (!empty($small_items)) : ?>
                        <div class="flc-eventos-cards">
                            <?php foreach ($small_items as $item) :
                                $has_image = !empty($item['thumbnail']); ?>
                                <article class="evento-card evento-card--modern <?php echo esc_attr($item['class']); ?>">
                                    <?php if ($has_image) : ?>
                                        <div class="evento-card__media-wrapper">
                                            <div class="evento-card__media">
                                                <img src="<?php echo esc_url($item['thumbnail']); ?>"
                                                     alt="<?php echo esc_attr($item['title']); ?>"
                                                     loading="lazy">
                                            </div>
                                            <div class="evento-card__date-overlay" aria-hidden="true">
                                                <span class="weekday"><?php echo esc_html($item['weekday']); ?></span>
                                                <span class="day"><?php echo esc_html($item['day']); ?></span>
                                                <span class="month"><?php echo esc_html($item['month']); ?></span>
                                            </div>
                                        </div>
                                    <?php else : ?>
                                        <div class="evento-card__date" aria-hidden="true">
                                            <span class="weekday"><?php echo esc_html($item['weekday']); ?></span>
                                            <span class="day"><?php echo esc_html($item['day']); ?></span>
                                            <span class="month"><?php echo esc_html($item['month']); ?></span>
                                        </div>
                                    <?php endif; ?>
                                    <div class="evento-card__body">
                                        <div class="evento-card__date-row">
                                            <span class="evento-card__status-pill"><?php echo esc_html($item['status']); ?></span>
                                            <?php if (!empty($item['hora'])) : ?>
                                                <span class="hora-pill hora-pill--inline"><?php echo esc_html($item['hora']); ?></span>
                                            <?php endif; ?>
                                            <?php if (!empty($item['duration'])) : ?>
                                                <span class="duration-pill"><?php echo esc_html($item['duration']); ?></span>
                                            <?php endif; ?>
                                        </div>
                                        <h4 class="evento-card__title">
                                            <a href="<?php echo esc_url($item['link']); ?>">
                                                <?php echo esc_html($item['title']); ?>
                                            </a>
                                        </h4>
                                        <?php if (!empty($item['excerpt'])) : ?>
                                            <p class="evento-card__excerpt">
                                                <?php echo esc_html($item['excerpt']); ?>
                                            </p>
                                        <?php endif; ?>
                                    </div>
                                    <a class="evento-card__link"
                                       href="<?php echo esc_url($item['link']); ?>"
                                       aria-label="<?php echo esc_attr(sprintf(__('Ver detalle del evento %s', 'flacso-main-page'), $item['title'])); ?>"></a>
                                </article>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>
            </section>
            <style>
                .flc-eventos-grid {
                    padding: clamp(2.5rem, 5vw, 4rem) 0;
                    background: linear-gradient(160deg, rgba(240, 245, 255, 0.6) 0%, rgba(255, 255, 255, 0.9) 50%, rgba(248, 250, 255, 0.7) 100%);
                    position: relative;
                    overflow: hidden;
                }

                .flc-eventos-grid::before {
                    content: '';
                    position: absolute;
                    top: -50%;
                    right: -10%;
                    width: 600px;
                    height: 600px;
                    background: radial-gradient(circle, rgba(17, 89, 175, 0.08) 0%, transparent 70%);
                    border-radius: 50%;
                    pointer-events: none;
                }

                .flc-eventos-grid::after {
                    content: '';
                    position: absolute;
                    bottom: -30%;
                    left: -5%;
                    width: 500px;
                    height: 500px;
                    background: radial-gradient(circle, rgba(245, 165, 36, 0.06) 0%, transparent 70%);
                    border-radius: 50%;
                    pointer-events: none;
                }

                .flc-eventos-grid__header {
                    text-align: center;
                    margin-bottom: clamp(2rem, 4vw, 3rem);
                    position: relative;
                    z-index: 1;
                }

                .flc-eventos-grid__title {
                    font-size: clamp(1.8rem, 4.5vw, 2.8rem);
                    margin: 0 0 0.5rem;
                    color: var(--global-palette3, #0f1a2d);
                    letter-spacing: -0.02em;
                    text-transform: none;
                    font-weight: 800;
                    font-family: inherit;
                    background: linear-gradient(135deg, var(--global-palette3) 0%, var(--global-palette1) 100%);
                    -webkit-background-clip: text;
                    -webkit-text-fill-color: transparent;
                    background-clip: text;
                    position: relative;
                    display: inline-block;
                }

                /* Mobile first - grid single column */
                .flc-eventos-hero {
                    display: grid;
                    grid-template-columns: 1fr;
                    gap: clamp(1rem, 3vw, 1.6rem);
                    margin-bottom: clamp(1rem, 2vw, 1.75rem);
                    max-width: 100%;
                }

                /* Tablet - 2 columns */
                @media (min-width: 768px) {
                    .flc-eventos-hero {
                        grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
                    }
                }

                /* Desktop - 3 columns pero adaptativo */
                @media (min-width: 1024px) {
                    .flc-eventos-hero {
                        grid-template-columns: repeat(auto-fit, minmax(350px, 1fr));
                    }
                }

                .flc-eventos-hero-card {
                    display: flex;
                    flex-direction: column;
                    border-radius: 24px;
                    overflow: hidden;
                    background: linear-gradient(145deg, #ffffff 0%, #f8faff 100%);
                    border: 1px solid rgba(13, 31, 68, 0.1);
                    box-shadow: 0 10px 30px rgba(15, 26, 45, 0.1), 0 4px 12px rgba(0, 0, 0, 0.05);
                    position: relative;
                    min-height: auto;
                    transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
                }

                /* Desktop - layout horizontal */
                @media (min-width: 1024px) {
                    .flc-eventos-hero-card {
                        flex-direction: row;
                        align-items: stretch;
                    }
                }

                .flc-eventos-hero-card::after {
                    content: '';
                    position: absolute;
                    top: 0;
                    left: 0;
                    right: 0;
                    height: 3px;
                    background: linear-gradient(90deg, var(--global-palette12) 0%, var(--global-palette15) 100%);
                    opacity: 0;
                    transition: opacity 0.3s ease;
                }

                /* Desktop - layout horizontal */
                @media (min-width: 1024px) {
                    .flc-eventos-hero-card {
                        flex-direction: row;
                        align-items: stretch;
                    }
                }

                .flc-eventos-hero-card:hover {
                    transform: translateY(-6px);
                    box-shadow: 0 16px 40px rgba(15, 26, 45, 0.15), 0 8px 16px rgba(0, 0, 0, 0.08);
                }

                .flc-eventos-hero-card:hover::after {
                    opacity: 1;
                }

                .flc-eventos-hero-card--modern:before {
                    content: "";
                    position: absolute;
                    left: 0;
                    top: 0;
                    bottom: 0;
                    width: 5px;
                    background: linear-gradient(180deg, var(--global-palette12) 0%, var(--global-palette15) 50%, var(--global-palette1) 100%);
                    border-radius: 24px 0 0 24px;
                    box-shadow: 2px 0 8px rgba(17, 89, 175, 0.3);
                    transition: width 0.3s ease;
                }

                .flc-eventos-hero-card:hover:before {
                    width: 7px;
                }

                .flc-eventos-hero-card__content {
                    flex: 1;
                    padding: clamp(1.2rem, 2.5vw, 2rem);
                    display: flex;
                    flex-direction: column;
                    gap: clamp(0.6rem, 1.2vw, 1rem);
                    position: relative;
                    z-index: 2;
                    justify-content: center;
                }

                .flc-eventos-hero-card__media {
                    width: 100%;
                    aspect-ratio: 1/1;
                    overflow: hidden;
                    background: #f8f9fa;
                    flex-shrink: 0;
                    max-height: clamp(220px, 52vh, 430px);
                    max-height: clamp(220px, 52dvh, 430px);
                }

                /* Desktop - imagen a la izquierda ocupando todo el alto */
                @media (min-width: 1024px) {
                    .flc-eventos-hero-card__media {
                        width: 45%;
                        max-width: 400px;
                        aspect-ratio: 1/1;
                        max-height: clamp(220px, 45vh, 400px);
                        max-height: clamp(220px, 45dvh, 400px);
                    }
                }

                .flc-eventos-hero-card__media img {
                    width:100%;
                    height:100%;
                    object-fit:contain;
                    display:block;
                }

                .flc-eventos-hero-card__date-row {
                    display:flex;
                    align-items:center;
                    gap: clamp(0.4rem, 1vw, 0.65rem);
                    margin-bottom: clamp(0.4rem, 1vw, 0.65rem);
                    flex-wrap:wrap;
                }

                .flc-eventos-hero-card__date-badge {
                    background: linear-gradient(135deg, var(--global-palette12) 0%, var(--global-palette1) 100%);
                    color:var(--global-palette9);
                    border-radius:16px;
                    padding: clamp(0.5rem, 1.2vw, 0.75rem) clamp(0.6rem, 1.2vw, 0.85rem);
                    font-size: clamp(0.65rem, 1.5vw, 0.8rem);
                    font-weight:700;
                    letter-spacing:.02em;
                    text-transform: none;
                    box-shadow:0 6px 20px rgba(17,89,175,.35), 0 2px 8px rgba(0,0,0,.1);
                    text-align:center;
                    line-height:1.05;
                    display:flex;
                    flex-direction:column;
                    align-items:center;
                    justify-content:center;
                    min-width: 72px;
                    position: relative;
                    overflow: hidden;
                }

                .flc-eventos-hero-card__date-badge::before {
                    content: '';
                    position: absolute;
                    top: -50%;
                    left: -50%;
                    width: 200%;
                    height: 200%;
                    background: linear-gradient(45deg, transparent, rgba(255,255,255,.15), transparent);
                    transform: rotate(45deg);
                    animation: shine 3s ease-in-out infinite;
                }

                @keyframes shine {
                    0%, 100% { transform: translateX(-100%) translateY(-100%) rotate(45deg); }
                    50% { transform: translateX(100%) translateY(100%) rotate(45deg); }
                }

                .flc-eventos-hero-card__date-badge .day {
                    font-size: clamp(1.2rem, 2vw, 1.5rem);
                    font-weight:700;
                    line-height:1;
                    margin: .1rem 0 .05rem;
                    display:block;
                }

                .status-pill {
                    background: linear-gradient(135deg, #f59e0b 0%, #d97706 100%);
                    color: #ffffff;
                    font-size: clamp(0.65rem, 1.2vw, 0.75rem);
                    letter-spacing:.08em;
                    font-weight:700;
                    text-transform: uppercase;
                    padding: clamp(0.4rem, 0.8vw, 0.5rem) clamp(0.6rem, 1.2vw, 0.8rem);
                    border-radius:10px;
                    white-space:nowrap;
                    box-shadow: 0 4px 12px rgba(245, 158, 11, .35), 0 2px 6px rgba(0,0,0,.1);
                    display: inline-flex;
                    align-items: center;
                    gap: 0.3rem;
                    transition: all 0.2s ease;
                }

                .status-pill:hover {
                    transform: translateY(-1px);
                    box-shadow: 0 6px 16px rgba(245, 158, 11, .45), 0 3px 8px rgba(0,0,0,.15);
                }

                .hora-pill--inline {
                    background: linear-gradient(135deg, var(--global-palette3) 0%, var(--global-palette1) 100%);
                    color:var(--global-palette9);
                    padding: clamp(0.4rem, 0.8vw, 0.5rem) clamp(0.6rem, 1.2vw, 0.75rem);
                    border-radius:10px;
                    font-size: clamp(0.65rem, 1.2vw, 0.75rem);
                    font-weight:700;
                    letter-spacing:.05em;
                    display: inline-flex;
                    align-items: center;
                    gap: 0.3rem;
                    box-shadow: 0 4px 12px rgba(15, 26, 45, .25), 0 2px 6px rgba(0,0,0,.08);
                    transition: all 0.2s ease;
                }

                .hora-pill--inline::before {
                    content: '🕐';
                    font-size: 1em;
                }

                .hora-pill--inline:hover {
                    transform: translateY(-1px);
                    box-shadow: 0 6px 16px rgba(15, 26, 45, .35), 0 3px 8px rgba(0,0,0,.12);
                }

                .duration-pill {
                    background: linear-gradient(135deg, #10b981 0%, #059669 100%);
                    color: #ffffff;
                    padding: clamp(0.4rem, 0.8vw, 0.5rem) clamp(0.6rem, 1.2vw, 0.75rem);
                    border-radius:10px;
                    font-size: clamp(0.65rem, 1.2vw, 0.75rem);
                    font-weight:700;
                    letter-spacing:.05em;
                    border: none;
                    display: inline-flex;
                    align-items: center;
                    gap: 0.3rem;
                    box-shadow: 0 4px 12px rgba(16, 185, 129, .35), 0 2px 6px rgba(0,0,0,.08);
                    transition: all 0.2s ease;
                }

                .duration-pill::before {
                    content: '⏱️';
                    font-size: 1em;
                }

                .duration-pill:hover {
                    transform: translateY(-1px);
                    box-shadow: 0 6px 16px rgba(16, 185, 129, .45), 0 3px 8px rgba(0,0,0,.12);
                }

                .flc-eventos-hero-card__title {
                    margin: 0;
                    font-size: clamp(1.15rem, 3vw, 1.6rem);
                    line-height: 1.3;
                    color: var(--global-palette3, #0f1a2d);
                    display: block;
                    white-space: normal;
                    overflow: visible;
                    text-overflow: unset;
                    font-weight: 700;
                    letter-spacing: -0.01em;
                    background: linear-gradient(135deg, var(--global-palette3) 0%, var(--global-palette1) 100%);
                    -webkit-background-clip: text;
                    -webkit-text-fill-color: transparent;
                    background-clip: text;
                    transition: all 0.3s ease;
                }

                .flc-eventos-hero-card:hover .flc-eventos-hero-card__title {
                    letter-spacing: 0;
                }

                .evento-description {
                    margin: 0;
                    color: var(--global-palette5, #5a6170);
                    font-size: clamp(0.9rem, 1.5vw, 1rem);
                    line-height: 1.6;
                    display: -webkit-box;
                    -webkit-line-clamp: 3;
                    -webkit-box-orient: vertical;
                    overflow: hidden;
                    opacity: 0.9;
                    transition: opacity 0.3s ease;
                }

                .flc-eventos-hero-card:hover .evento-description {
                    opacity: 1;
                }

                .flc-eventos-hero-card__link {
                    position: absolute;
                    inset: 0;
                    z-index: 5;
                    border-radius: inherit;
                }

                /* Small cards modern - mobile first */
                .flc-eventos-cards {
                    display: grid;
                    grid-template-columns: 1fr;
                    gap: clamp(0.8rem, 3vw, 1.4rem);
                }

                @media (min-width: 640px) {
                    .flc-eventos-cards {
                        grid-template-columns: repeat(2, 1fr);
                    }
                }

                @media (min-width: 1024px) {
                    .flc-eventos-cards {
                        grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
                    }
                }

                .evento-card--modern {
                    padding:0;
                    border-radius:20px;
                    overflow:hidden;
                    display:flex;
                    flex-direction:column;
                    box-shadow:0 8px 24px rgba(13,31,68,.1), 0 4px 8px rgba(0,0,0,.04);
                    background: linear-gradient(145deg, #ffffff 0%, #fafbff 100%);
                    border: 1px solid rgba(15, 26, 45, 0.1);
                    transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
                    position: relative;
                }

                .evento-card--modern::before {
                    content: '';
                    position: absolute;
                    top: 0;
                    left: 0;
                    right: 0;
                    height: 2px;
                    background: linear-gradient(90deg, var(--global-palette12) 0%, var(--global-palette15) 100%);
                    opacity: 0;
                    transition: opacity 0.3s ease;
                }

                .evento-card--modern:hover {
                    transform: translateY(-4px) scale(1.01);
                    box-shadow: 0 16px 36px rgba(13, 31, 68, 0.14), 0 8px 12px rgba(0,0,0,.06);
                }

                .evento-card--modern:hover::before {
                    opacity: 1;
                }

                .evento-card--modern .evento-card__media-wrapper {position:relative;}

                .evento-card--modern .evento-card__media {
                    width:100%;
                    aspect-ratio:1/1;
                    border:0;
                    border-radius:0;
                    background: var(--global-palette7);
                }

                .evento-card--modern .evento-card__media img {
                    width:100%;
                    height:100%;
                    object-fit:contain;
                    display:block;
                }

                .evento-card--modern .evento-card__date-overlay {
                    position:absolute;
                    left: clamp(0.6rem, 2vw, 0.85rem);
                    top: clamp(0.6rem, 2vw, 0.85rem);
                    background: linear-gradient(135deg, rgba(255,255,255,.98) 0%, rgba(255,255,255,.95) 100%);
                    color:var(--global-palette3);
                    padding: clamp(0.5rem, 1vw, 0.7rem) clamp(0.7rem, 1.5vw, 0.9rem);
                    border-radius:14px;
                    box-shadow: 0 6px 20px rgba(0,0,0,.15), 0 2px 8px rgba(0,0,0,.08);
                    backdrop-filter: blur(10px);
                    border: 1px solid rgba(255,255,255,.5);
                    font-weight:700;
                    text-align:center;
                    line-height:1.1;
                    display:flex;
                    flex-direction:column;
                    align-items:center;
                    justify-content:center;
                    z-index: 3;
                    transition: transform 0.2s ease;
                }

                .evento-card--modern:hover .evento-card__date-overlay {
                    transform: scale(1.05);
                }
                    font-size: clamp(0.6rem, 1.2vw, 0.7rem);
                    font-weight:600;
                    letter-spacing:.08em;
                    text-align:center;
                    box-shadow:0 4px 12px rgba(0,0,0,.15);
                }

                .evento-card--modern .evento-card__date-overlay .day {
                    display:block;
                    font-size: clamp(1.2rem, 2.5vw, 1.6rem);
                    font-weight:700;
                    line-height:1;
                }

                .evento-card--modern .evento-card__body {
                    padding: clamp(1rem, 2vw, 1.5rem);
                    display: flex;
                    flex-direction: column;
                    gap: clamp(0.5rem, 1vw, 0.7rem);
                    background: linear-gradient(180deg, #ffffff 0%, #fafbff 100%);
                }

                .evento-card__date-row {
                    display:flex;
                    align-items:center;
                    gap: clamp(0.35rem, 1vw, 0.5rem);
                    flex-wrap:wrap;
                }

                .evento-card__status-pill {
                    background:var(--global-palette6);
                    color:var(--global-palette3);
                    font-size: clamp(0.55rem, 1vw, 0.65rem);
                    letter-spacing:.08em;
                    font-weight:700;
                    padding: clamp(0.3rem, 0.7vw, 0.4rem) clamp(0.45rem, 1vw, 0.55rem);
                    border-radius:6px;
                    display:inline-block;
                }

                .evento-card--modern .hora-pill--inline {
                    background:var(--global-palette1);
                    color:var(--global-palette9);
                }

                .evento-card--modern .duration-pill {
                    background:var(--global-palette7);
                    color:var(--global-palette3);
                }

                .evento-card__title {
                    margin: 0;
                    font-size: clamp(1rem, 2vw, 1.2rem);
                    font-weight: 700;
                    line-height: 1.35;
                    display: block;
                    white-space: normal;
                    overflow: visible;
                    text-overflow: unset;
                    letter-spacing: -0.01em;
                }

                .evento-card__title a {
                    color: var(--global-palette3, #0f1a2d);
                    text-decoration: none;
                    background: linear-gradient(135deg, var(--global-palette3) 0%, var(--global-palette1) 100%);
                    -webkit-background-clip: text;
                    -webkit-text-fill-color: transparent;
                    background-clip: text;
                    transition: all 0.2s ease;
                }

                .evento-card--modern:hover .evento-card__title a {
                    letter-spacing: 0;
                }

                .evento-card__excerpt {
                    margin: 0;
                    color: var(--global-palette5);
                    font-size: clamp(0.8rem, 1.5vw, 0.9rem);
                    line-height: 1.4;
                    display: -webkit-box;
                    -webkit-line-clamp: 2;
                    -webkit-box-orient: vertical;
                    overflow: hidden;
                }

                .evento-card__link {
                    position: absolute;
                    inset: 0;
                    z-index: 4;
                    border-radius: inherit;
                    text-decoration: none;
                }

                /* Highlight palettes */
                .flc-eventos-hero-card.is-today:before,
                .evento-card.is-today:before {
                    background:linear-gradient(180deg,var(--global-palette14),var(--global-palette15));
                }

                .flc-eventos-hero-card.is-tomorrow:before,
                .evento-card.is-tomorrow:before {
                    background:linear-gradient(180deg,var(--global-palette12),var(--global-palette1));
                }

                .flc-eventos-hero-card.is-today,
                .evento-card.is-today {
                    border-color:rgba(247,99,12,.7);
                    background:linear-gradient(180deg,rgba(247,99,12,.12),#fff);
                    box-shadow:0 0 0 2px rgba(247,99,12,.15),0 8px 20px rgba(247,99,12,.18);
                }

                .flc-eventos-hero-card.is-tomorrow,
                .evento-card.is-tomorrow {
                    border-color:rgba(17,89,175,.35);
                    background:linear-gradient(180deg,rgba(17,89,175,.08),#fff);
                }

                .evento-card.is-today .evento-card__status-pill,
                .flc-eventos-hero-card.is-today .status-pill {
                    background:linear-gradient(135deg,var(--global-palette15),var(--global-palette14));
                    color:var(--global-palette9);
                    box-shadow:0 3px 10px rgba(247,99,12,.3);
                }

                .evento-card.is-tomorrow .evento-card__status-pill,
                .flc-eventos-hero-card.is-tomorrow .status-pill {
                    background:linear-gradient(135deg,var(--global-palette12),var(--global-palette1));
                    color:var(--global-palette9);
                    box-shadow:0 3px 10px rgba(17,89,175,.25);
                }

                .evento-card.is-today .hora-pill--inline,
                .flacso-hero-card.is-today .hora-pill--inline {
                    background:var(--global-palette14);
                    color:var(--global-palette9);
                }

                .evento-card.is-tomorrow .hora-pill--inline,
                .flacso-hero-card.is-tomorrow .hora-pill--inline {
                    background:var(--global-palette12);
                    color:var(--global-palette9);
                }

                .flc-eventos-hero-card.is-running {
                    border-color:rgba(17,89,175,.25);
                    background:rgba(17,89,175,.04);
                }

                /* Desktop optimizations */
                @media (min-width: 1024px) {
                    .flc-eventos-hero-card {
                        min-height: clamp(250px, 56vh, 300px);
                        min-height: clamp(250px, 56dvh, 300px);
                    }

                    .flc-eventos-hero-card__content {
                        padding: 1.5rem;
                    }

                    .evento-card--modern .evento-card__body {
                        padding: 1.3rem;
                    }
                }

                @media (max-height: 860px) {
                    .flc-eventos-hero-card__media {
                        max-height: clamp(190px, 44vh, 330px);
                        max-height: clamp(190px, 44dvh, 330px);
                    }

                    .evento-description {
                        -webkit-line-clamp: 2;
                    }
                }
            </style>
            <?php

            return ob_get_clean();
        } catch (\Throwable $e) {
            if (defined('WP_DEBUG') && WP_DEBUG) {
                error_log('[FLACSO] Error en sección de eventos: ' . $e->getMessage());
            }
            return '';
        }
    }
}
