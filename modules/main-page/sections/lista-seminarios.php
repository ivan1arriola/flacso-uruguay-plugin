<?php

if (!defined('ABSPATH')) {
    exit;
}

if (shortcode_exists('lista_seminarios')) {
    remove_shortcode('lista_seminarios');
}

if (!function_exists('flacso_get_seminario_post_type_slug')) {
    function flacso_get_seminario_post_type_slug(): string {
        return post_type_exists('seminario') ? 'seminario' : 'post';
    }
}

if (!function_exists('flacso_get_seminario_start_date')) {
    function flacso_get_seminario_start_date(int $post_id): string {
        if (class_exists('Flacso_Main_Page_Seminarios')) {
            return Flacso_Main_Page_Seminarios::get_start_date($post_id);
        }

        $raw = get_post_meta($post_id, 'fecha_inicio', true);
        $timestamp = $raw ? strtotime($raw) : false;

        return $timestamp ? date('Y-m-d', $timestamp) : '';
    }
}

if (!function_exists('flacso_get_seminario_end_date')) {
    function flacso_get_seminario_end_date(int $post_id): string {
        if (class_exists('Flacso_Main_Page_Seminarios')) {
            return Flacso_Main_Page_Seminarios::get_end_date($post_id);
        }

        $raw = get_post_meta($post_id, 'fecha_fin', true);
        $timestamp = $raw ? strtotime($raw) : false;

        return $timestamp ? date('Y-m-d', $timestamp) : '';
    }
}

if (!function_exists('flacso_get_seminario_meta_value')) {
    function flacso_get_seminario_meta_value(int $post_id, string $field): string {
        if (class_exists('Flacso_Main_Page_Seminarios')) {
            return Flacso_Main_Page_Seminarios::get_meta_value($post_id, $field);
        }

        return (string) get_post_meta($post_id, $field, true);
    }
}

if (!function_exists('flacso_lista_seminarios_render')) {
    function flacso_lista_seminarios_render($atts): string {
        if (function_exists('flacso_global_styles')) {
            flacso_global_styles();
        }

        $atts = shortcode_atts([
            'posts_per_page' => -1,
            'category' => 156,
            'mostrar_fechas' => true,
            'mostrar_boton' => true,
            'texto_boton' => 'Ver más información',
        ], $atts, 'lista_seminarios');

        $atts['mostrar_fechas'] = rest_sanitize_boolean($atts['mostrar_fechas']);
        $atts['mostrar_boton'] = rest_sanitize_boolean($atts['mostrar_boton']);
        $atts['posts_per_page'] = intval($atts['posts_per_page']);
        $atts['category'] = intval($atts['category']);
        $atts['texto_boton'] = sanitize_text_field($atts['texto_boton']);

        $html = flacso_generar_seminarios_combinados_html(0, $atts);

        ob_start();
        ?>
        <div class="seminarios-wrapper flacso-fade-in">
            <div id="contenedor-seminarios" class="seminarios-container">
                <?php echo $html; ?>
            </div>
        </div>

        <style>
        .mes-separador {
            font-family: var(--flacso-heading-font);
            color: var(--flacso-primary);
            background: var(--flacso-bg-light);
            border-left: 4px solid var(--flacso-secondary);
            padding: 0.5rem 0.75rem;
            margin: 2rem 0 1.2rem;
            letter-spacing: 0.5px;
            border-radius: 4px;
        }

        .seminarios-mes {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 1.8rem;
        }

        @media (max-width: 1024px) {
            .seminarios-mes {
                grid-template-columns: repeat(2, 1fr);
            }
        }

        @media (max-width: 768px) {
            .seminarios-mes {
                grid-template-columns: 1fr;
            }
        }

        .seminario-card {
            background: var(--flacso-white);
            border: 1px solid var(--flacso-border);
            border-radius: 16px;
            box-shadow: 0 4px 12px rgba(0,0,0,0.06);
            overflow: hidden;
            transition: var(--flacso-transition);
            animation: flacso-slideInRight 0.8s ease-out;
        }

        .seminario-card:hover {
            transform: translateY(-4px);
            box-shadow: 0 8px 20px rgba(0,0,0,0.1);
        }

        .seminario-card__image-container {
            overflow: hidden;
            position: relative;
            aspect-ratio: 1 / 1;
        }

        .seminario-card__image {
            width: 100%;
            height: 100%;
            object-fit: cover;
            transition: var(--flacso-transition);
        }

        .seminario-card__image:hover {
            transform: scale(1.05);
        }

        .seminario-card__content {
            padding: 1.4rem 1.6rem;
        }

        .seminario-card__title {
            font-family: var(--flacso-heading-font);
            font-size: 1.2rem;
            color: var(--flacso-dark);
            margin-bottom: 0.5rem;
        }

        .seminario-card__title a {
            color: inherit;
            text-decoration: none;
        }

        .seminario-card__title a:hover {
            color: var(--flacso-primary);
        }

        .seminario-card__date {
            color: var(--flacso-secondary);
            font-weight: 600;
            font-size: 0.95rem;
            margin-bottom: 0.5rem;
        }

        .seminario-card__estado {
            font-size: 0.9rem;
            font-weight: 600;
            margin-bottom: 0.8rem;
        }

        .seminario-card__estado.inicia {
            color: var(--flacso-primary);
        }

        .seminario-card__estado.iniciado {
            color: var(--flacso-secondary);
        }

        .seminario-card__excerpt {
            color: var(--flacso-text);
            font-size: 0.95rem;
            margin-bottom: 1rem;
            line-height: 1.5;
        }

        .seminario-card__button {
            text-align: right;
        }

        .seminario-card__content {
            padding: 1rem;
        }
        </style>

        <script>
        (function() {
            document.addEventListener('DOMContentLoaded', function () {
                const contenedor = document.getElementById('contenedor-seminarios');
                if (!contenedor) {
                    return;
                }

                const lazyImages = [].slice.call(contenedor.querySelectorAll('img[data-src]'));
                if (!lazyImages.length) {
                    return;
                }

                if ('IntersectionObserver' in window) {
                    const observer = new IntersectionObserver(function(entries, obs) {
                        entries.forEach(function(entry) {
                            if (entry.isIntersecting) {
                                const img = entry.target;
                                img.src = img.dataset.src;
                                img.classList.add('loaded');
                                obs.unobserve(img);
                            }
                        });
                    }, { rootMargin: '100px 0px' });
                    lazyImages.forEach(img => observer.observe(img));
                } else {
                    lazyImages.forEach(img => {
                        img.src = img.dataset.src;
                        img.classList.add('loaded');
                    });
                }
            });
        })();
        </script>
        <?php

        return ob_get_clean();
    }

    add_shortcode('lista_seminarios', 'flacso_lista_seminarios_render');
}

if (!function_exists('flacso_generar_tarjeta_seminario_general')) {
    function flacso_generar_tarjeta_seminario_general($id, $titulo, $extracto, $link, $imagen, $fecha_texto, bool $mostrar_fechas, bool $mostrar_boton, string $texto_boton, bool $iniciado, bool $expirado, string $fecha_inicio_iso = ''): string {
        $hoy = strtotime(date('Y-m-d'));
        $fecha_inicio_meta = $fecha_inicio_iso ?: flacso_get_seminario_start_date($id);
        $estado_texto = '';
        $estado_clase = '';

        if ($fecha_inicio_meta) {
            $inicio_timestamp = strtotime($fecha_inicio_meta);
            $diferencia = floor(($inicio_timestamp - $hoy) / DAY_IN_SECONDS);

            if ($diferencia > 0) {
                /* translators: %s: number of days until seminar starts */
                $estado_texto = sprintf(__('Comienza en %s día(s)', 'flacso-main-page'), $diferencia);
                $estado_clase = 'inicia';
            } elseif ($diferencia <= 0 && $diferencia > -10) {
                /* translators: %s: number of days since seminar started */
                $estado_texto = sprintf(__('Empezó hace %s día(s)', 'flacso-main-page'), abs($diferencia));
                $estado_clase = 'iniciado';
            }
        }

        $clase_extra = '';
        if ($iniciado) {
            $clase_extra .= ' iniciado';
        }
        if ($expirado) {
            $clase_extra .= ' expirado';
        }

        ob_start();
        ?>
        <div class="seminario-card<?php echo esc_attr($clase_extra); ?>"<?php echo $expirado ? ' style="display:none;"' : ''; ?>>
            <?php if ($imagen) : ?>
            <div class="seminario-card__image-container">
                <a href="<?php echo esc_url($link); ?>" aria-label="<?php echo esc_attr($titulo); ?>">
                    <img data-src="<?php echo esc_url($imagen); ?>" class="seminario-card__image" alt="<?php echo esc_attr($titulo); ?>" width="400" height="200" loading="lazy">
                </a>
            </div>
            <?php endif; ?>
            <div class="seminario-card__content">
                <h3 class="seminario-card__title flacso-fade-in">
                    <a href="<?php echo esc_url($link); ?>"><?php echo esc_html($titulo); ?></a>
                </h3>

                <?php if ($fecha_texto && $mostrar_fechas) : ?>
                    <p class="seminario-card__date"><?php echo esc_html($fecha_texto); ?></p>
                <?php endif; ?>

                <?php if ($estado_texto) : ?>
                    <p class="seminario-card__estado <?php echo esc_attr($estado_clase); ?>">
                        <?php echo esc_html($estado_texto); ?>
                    </p>
                <?php endif; ?>

                <div class="seminario-card__excerpt">
                    <?php echo esc_html(wp_trim_words($extracto, 20, '...')); ?>
                </div>

                <?php if ($mostrar_boton) : ?>
                <div class="seminario-card__button">
                    <a href="<?php echo esc_url($link); ?>" class="flacso-btn flacso-btn-primary flacso-btn-anim">
                        <?php echo esc_html($texto_boton); ?>
                    </a>
                </div>
                <?php endif; ?>
            </div>
        </div>
        <?php

        return ob_get_clean();
    }
}

if (!function_exists('flacso_generar_seminarios_combinados_html')) {
    function flacso_generar_seminarios_combinados_html(int $offset, array $atts): string {
        $defaults = [
            'posts_per_page' => -1,
            'category' => 156,
            'mostrar_fechas' => true,
            'mostrar_boton' => true,
            'texto_boton' => 'Ver más información',
        ];

        $atts = wp_parse_args($atts, $defaults);

        $hoy = current_time('Y-m-d');
        $hoy_timestamp = strtotime($hoy);
        $hace_diez = date('Y-m-d', strtotime('-10 days', $hoy_timestamp));

        $start_keys = ['fecha_inicio'];
        if (class_exists('Flacso_Main_Page_Seminarios')) {
            $start_keys = Flacso_Main_Page_Seminarios::get_meta_keys_for('periodo_inicio');
        }

        $date_meta_query = ['relation' => 'OR'];

        foreach ($start_keys as $key) {
            $date_meta_query[] = [
                'key'     => $key,
                'value'   => $hace_diez,
                'compare' => '>=',
                'type'    => 'DATE',
            ];
        }

        $meta_query = ['relation' => 'AND'];

        if (count($date_meta_query) > 1) {
            $meta_query[] = $date_meta_query;
        }

        // Excluir seminarios privados (no abiertos al público)
        $meta_query[] = [
            'relation' => 'OR',
            [
                'key'     => '_seminario_abierto_publico',
                'compare' => 'NOT EXISTS',
            ],
            [
                'key'     => '_seminario_abierto_publico',
                'value'   => '1',
                'compare' => '=',
            ],
        ];

        $post_type = flacso_get_seminario_post_type_slug();

        $query_args = [
            'post_type'      => $post_type,
            'posts_per_page' => intval($atts['posts_per_page']),
            'offset'         => max(0, $offset),
            'orderby'        => 'date',
            'order'          => 'ASC',
            'meta_query'     => $meta_query,
            'post_status'    => 'publish',
        ];

        if ('post' === $post_type) {
            $query_args['cat'] = intval($atts['category']);
            $query_args['meta_key'] = 'fecha_inicio';
            $query_args['orderby'] = 'meta_value';
            $query_args['meta_type'] = 'DATE';
        }

        $query = new WP_Query($query_args);

        $html = '';

        if ($query->have_posts()) {
            $seminarios_por_mes = [];

            while ($query->have_posts()) {
                $query->the_post();
                $id = get_the_ID();
                $titulo = get_the_title();
                $extracto = get_the_excerpt();
                $link = get_permalink();
                $imagen = get_the_post_thumbnail_url($id, 'medium_large') ?: '';

                $fecha_inicio = flacso_get_seminario_start_date($id);
                $fecha_fin = flacso_get_seminario_end_date($id);
                $fecha_texto = '';

                if (!$fecha_inicio) {
                    continue;
                }

                $fecha_inicio_ts = strtotime($fecha_inicio);
                if (!$fecha_inicio_ts) {
                    continue;
                }

                if ($fecha_inicio || $fecha_fin) {
                    $inicio_str = $fecha_inicio ? date_i18n('d/m/Y', $fecha_inicio_ts) : '';
                    $fin_str = $fecha_fin ? date_i18n('d/m/Y', strtotime($fecha_fin)) : '';
                    $fecha_texto = $inicio_str ? sprintf(__('Del %s', 'flacso-main-page'), $inicio_str) : '';
                    if ($fin_str) {
                        $fecha_texto .= $fecha_texto ? sprintf(__(' al %s', 'flacso-main-page'), $fin_str) : sprintf(__('Hasta %s', 'flacso-main-page'), $fin_str);
                    }
                }

                $iniciado = ($fecha_inicio && $fecha_inicio <= $hoy);
                $expirado = ($fecha_inicio && $fecha_inicio < $hace_diez);

                $mes = strtoupper(date_i18n('F Y', $fecha_inicio_ts));

                if (!isset($seminarios_por_mes[$mes])) {
                    $seminarios_por_mes[$mes] = [
                        'no_iniciados' => [],
                        'iniciados' => [],
                    ];
                }

                if ($expirado) {
                    continue;
                }

                $datos_seminario = [
                    'id'          => $id,
                    'titulo'      => $titulo,
                    'extracto'    => $extracto,
                    'link'        => $link,
                    'imagen'      => $imagen,
                    'fecha_texto' => $fecha_texto,
                    'inicio'      => $fecha_inicio,
                    'iniciado'    => $iniciado,
                    'expirado'    => $expirado,
                ];

                if ($iniciado) {
                    $seminarios_por_mes[$mes]['iniciados'][] = $datos_seminario;
                } else {
                    $seminarios_por_mes[$mes]['no_iniciados'][] = $datos_seminario;
                }
            }

            foreach ($seminarios_por_mes as $mes => $grupos) {
                $seminarios_mes = array_merge($grupos['no_iniciados'], $grupos['iniciados']);
                if (empty($seminarios_mes)) {
                    continue;
                }

                usort($seminarios_mes, static function ($a, $b) {
                    return strcmp($a['inicio'] ?? '', $b['inicio'] ?? '');
                });

                $html .= '<div class="seminario-mes fade-in">';
                $html .= '<h2 class="mes-separador">' . esc_html($mes) . '</h2>';
                $html .= '<div class="seminarios-mes">';

                foreach ($seminarios_mes as $seminario) {
                    $html .= flacso_generar_tarjeta_seminario_general(
                        $seminario['id'],
                        $seminario['titulo'],
                        $seminario['extracto'],
                        $seminario['link'],
                        $seminario['imagen'],
                        $seminario['fecha_texto'],
                        (bool) $atts['mostrar_fechas'],
                        (bool) $atts['mostrar_boton'],
                        $atts['texto_boton'],
                        (bool) $seminario['iniciado'],
                        (bool) $seminario['expirado'],
                        $seminario['inicio'] ?? ''
                    );
                }

                $html .= '</div></div>';
            }
        }

        wp_reset_postdata();
        return $html;
    }
}
if (!function_exists('flacso_fetch_public_seminarios_proximos_items')) {
    /**
     * Obtiene seminarios desde la API pública de FLACSO (fuente única).
     *
     * @param int $today_ts Timestamp de referencia (00:00 local).
     * @return array<int, array<string, mixed>>
     */
    function flacso_fetch_public_seminarios_proximos_items(int $today_ts): array {
        $cache_key = 'flacso_public_seminarios_proximos_v7';
        $cached = get_transient($cache_key);
        if (is_array($cached) && !empty($cached)) {
            return $cached;
        }

        $rows = [];
        $endpoint = 'https://flacso.edu.uy/wp-json/wp/v2/seminario?per_page=100&_embed=1';
        $response = wp_remote_get($endpoint, [
            'timeout' => 12,
        ]);

        if (!is_wp_error($response) && (int) wp_remote_retrieve_response_code($response) === 200) {
            $body = wp_remote_retrieve_body($response);
            if (is_string($body) && $body !== '') {
                $decoded = json_decode($body, true);
                if (is_array($decoded)) {
                    $rows = $decoded;
                }
            }
        }

        // Respaldo robusto: misma API, pero resolución interna sin HTTP externo.
        if (empty($rows) && class_exists('WP_REST_Request') && function_exists('rest_do_request')) {
            $request = new WP_REST_Request('GET', '/wp/v2/seminario');
            $request->set_param('per_page', 100);
            $request->set_param('_embed', 1);

            $rest_response = rest_do_request($request);
            if (!is_wp_error($rest_response) && is_object($rest_response) && method_exists($rest_response, 'is_error') && !$rest_response->is_error()) {
                $data = $rest_response->get_data();
                if (is_array($data)) {
                    $rows = $data;
                }
            }
        }

        if (empty($rows)) {
            return [];
        }

        $items = [];
        $featured_media_ids = [];
        foreach ($rows as $row) {
            if (is_array($row) && !empty($row['featured_media']) && is_numeric($row['featured_media'])) {
                $id = absint($row['featured_media']);
                if ($id > 0) {
                    $featured_media_ids[] = $id;
                }
            }
        }
        $featured_media_ids = array_values(array_unique($featured_media_ids));

        $remote_media_map = [];
        if (!empty($featured_media_ids)) {
            $ids_csv = implode(',', $featured_media_ids);
            $media_endpoint = 'https://flacso.edu.uy/wp-json/wp/v2/media?include=' . rawurlencode($ids_csv) . '&per_page=100';
            $media_response = wp_remote_get($media_endpoint, ['timeout' => 12]);
            if (!is_wp_error($media_response) && (int) wp_remote_retrieve_response_code($media_response) === 200) {
                $media_body = wp_remote_retrieve_body($media_response);
                if (is_string($media_body) && $media_body !== '') {
                    $media_rows = json_decode($media_body, true);
                    if (is_array($media_rows)) {
                        foreach ($media_rows as $media_row) {
                            if (!is_array($media_row) || empty($media_row['id'])) {
                                continue;
                            }
                            $media_id = absint($media_row['id']);
                            if ($media_id <= 0) {
                                continue;
                            }
                            $media_url = '';
                            if (!empty($media_row['media_details']['sizes']['large']['source_url'])) {
                                $media_url = (string) $media_row['media_details']['sizes']['large']['source_url'];
                            } elseif (!empty($media_row['media_details']['sizes']['full']['source_url'])) {
                                $media_url = (string) $media_row['media_details']['sizes']['full']['source_url'];
                            } elseif (!empty($media_row['source_url'])) {
                                $media_url = (string) $media_row['source_url'];
                            }
                            if ($media_url !== '') {
                                $remote_media_map[$media_id] = esc_url_raw($media_url);
                            }
                        }
                    }
                }
            }
        }

        foreach ($rows as $row) {
            if (!is_array($row)) {
                continue;
            }

            $meta = isset($row['meta']) && is_array($row['meta']) ? $row['meta'] : [];
            $inicio_raw = '';
            foreach (['_seminario_periodo_inicio', '_seminario_fecha_inicio', 'fecha_inicio', 'periodo_inicio'] as $meta_key) {
                if (!empty($meta[$meta_key])) {
                    $inicio_raw = (string) $meta[$meta_key];
                    break;
                }
            }
            if ($inicio_raw === '') {
                $content_candidates = [];
                if (isset($row['content']['rendered'])) {
                    $content_candidates[] = (string) $row['content']['rendered'];
                }
                if (isset($row['excerpt']['rendered'])) {
                    $content_candidates[] = (string) $row['excerpt']['rendered'];
                }
                if (isset($row['content'])) {
                    $content_candidates[] = (string) $row['content'];
                }
                if (isset($row['excerpt'])) {
                    $content_candidates[] = (string) $row['excerpt'];
                }

                foreach ($content_candidates as $candidate) {
                    $plain = wp_strip_all_tags($candidate);
                    if (preg_match('/\b(\d{4}-\d{2}-\d{2})\b/u', $plain, $matches)) {
                        $inicio_raw = (string) $matches[1];
                        break;
                    }
                }
            }
            if ($inicio_raw === '') {
                continue;
            }

            $inicio_ts = strtotime($inicio_raw);
            if (!$inicio_ts) {
                continue;
            }

            $diff_days = (int) floor(($inicio_ts - $today_ts) / DAY_IN_SECONDS);
            if ($diff_days < -7) {
                continue;
            }

            $title = '';
            if (isset($row['title']['rendered'])) {
                $title = wp_strip_all_tags((string) $row['title']['rendered']);
            } elseif (isset($row['title'])) {
                $title = wp_strip_all_tags((string) $row['title']);
            }
            if ($title === '') {
                continue;
            }

            $permalink = isset($row['link']) ? esc_url_raw((string) $row['link']) : '';
            if ($permalink === '') {
                continue;
            }

            $img = '';
            if (!empty($row['_embedded']['wp:featuredmedia'][0]['source_url'])) {
                $img = esc_url_raw((string) $row['_embedded']['wp:featuredmedia'][0]['source_url']);
            }
            if ($img === '' && !empty($row['post_featured_image'])) {
                $img = esc_url_raw((string) $row['post_featured_image']);
            }
            if ($img === '' && !empty($row['featured_image'])) {
                $img = esc_url_raw((string) $row['featured_image']);
            }
            if ($img === '' && !empty($row['featured_media']) && is_numeric($row['featured_media'])) {
                $featured_media_id = absint($row['featured_media']);
                if ($featured_media_id > 0) {
                    if (isset($remote_media_map[$featured_media_id]) && is_string($remote_media_map[$featured_media_id])) {
                        $img = $remote_media_map[$featured_media_id];
                    }
                }
            }
            if ($img === '' && !empty($row['featured_media']) && is_numeric($row['featured_media'])) {
                $featured_media_id = absint($row['featured_media']);
                if ($featured_media_id > 0) {
                    $img = wp_get_attachment_image_url($featured_media_id, 'large');
                    if (!$img) {
                        $img = wp_get_attachment_image_url($featured_media_id, 'full');
                    }
                    if (is_string($img)) {
                        $img = esc_url_raw($img);
                    } else {
                        $img = '';
                    }
                }
            }
            if ($img === '') {
                continue;
            }

            $hora = '';
            foreach (['hora_inicio', '_seminario_hora_inicio', '_hora_inicio'] as $hora_key) {
                if (!empty($meta[$hora_key])) {
                    $hora = sanitize_text_field((string) $meta[$hora_key]);
                    break;
                }
            }

            if ($diff_days > 0) {
                $estado_badge = sprintf(__('En %d días', 'flacso-main-page'), $diff_days);
                $badge_color = '#f59e0b';
            } elseif (0 === $diff_days) {
                $estado_badge = __('Inicia hoy', 'flacso-main-page');
                $badge_color = '#10b981';
            } else {
                $estado_badge = sprintf(
                    __('Inició hace %d días', 'flacso-main-page'),
                    abs($diff_days)
                );
                $badge_color = '#3b82f6';
            }

            $items[] = [
                'post_id' => isset($row['id']) ? (int) $row['id'] : 0,
                'title' => $title,
                'permalink' => $permalink,
                'img' => $img,
                'fecha' => date_i18n('j \\d\\e F', $inicio_ts),
                'hora' => $hora,
                'estado_badge' => $estado_badge,
                'badge_color' => $badge_color,
                'inicio_ts' => $inicio_ts,
                'diff_days' => $diff_days,
            ];
        }

        if (!empty($items)) {
            usort($items, static function (array $a, array $b): int {
                $a_diff = isset($a['diff_days']) ? (int) $a['diff_days'] : PHP_INT_MAX;
                $b_diff = isset($b['diff_days']) ? (int) $b['diff_days'] : PHP_INT_MAX;

                $a_group = $a_diff >= 0 ? 0 : 1;
                $b_group = $b_diff >= 0 ? 0 : 1;
                if ($a_group !== $b_group) {
                    return $a_group <=> $b_group;
                }

                if (0 === $a_group) {
                    return $a_diff <=> $b_diff;
                }

                return $b_diff <=> $a_diff;
            });
            set_transient($cache_key, $items, 15 * MINUTE_IN_SECONDS);
        }

        return $items;
    }
}

if (!function_exists('flacso_section_seminarios_proximos_render')) {
    /**
     * Renderiza una sección de seminarios próximos para la página principal.
     *
     * @param int $max_items Cantidad máxima de seminarios a mostrar.
     *
     * @return string
     */
    function flacso_section_seminarios_proximos_render($max_items = 3): string {
        $max_items = max(1, (int) $max_items);
        $today = current_time('Y-m-d');
        $today_ts = strtotime($today);

        // Fuente única: API pública de seminarios.
        $seminarios_items = flacso_fetch_public_seminarios_proximos_items($today_ts);

        if (!empty($seminarios_items)) {
            $normalized_items = [];
            foreach ($seminarios_items as $item) {
                $inicio_ts = isset($item['inicio_ts']) ? (int) $item['inicio_ts'] : 0;
                if ($inicio_ts <= 0) {
                    continue;
                }

                $diff_days = isset($item['diff_days'])
                    ? (int) $item['diff_days']
                    : (int) floor(($inicio_ts - $today_ts) / DAY_IN_SECONDS);

                if ($diff_days < -7) {
                    continue;
                }

                $item['diff_days'] = $diff_days;
                $normalized_items[] = $item;
            }
            $seminarios_items = $normalized_items;
        }

        if (empty($seminarios_items)) {
            ob_start();
            ?>
            <section class="flacso-seminarios-proximos position-relative py-5">
                <div class="flacso-content-shell">
                    <div class="text-center mb-4">
                        <h2 class="h2 mb-3" style="color: var(--global-palette1, #1d3a72); font-weight: 700;">
                            <?php esc_html_e('Seminarios Próximos', 'flacso-main-page'); ?>
                        </h2>
                        <p class="lead mb-3" style="color: var(--global-palette4, #6b7280);">
                            <?php esc_html_e('Formación intensiva con enfoque práctico', 'flacso-main-page'); ?>
                        </p>
                        <p style="color: var(--global-palette4, #6b7280); margin-bottom: 1rem;">
                            <?php esc_html_e('No hay seminarios próximos para mostrar en este momento.', 'flacso-main-page'); ?>
                        </p>
                        <a href="https://flacso.edu.uy/formacion/seminarios/" class="button button-primary" style="background-color: var(--global-palette1, #1d3a72); color: #fff; padding: 0.65rem 1.4rem; border-radius: 8px; text-decoration: none; display: inline-block; font-weight: 600; border: none;">
                            <?php esc_html_e('Ver todos los seminarios abiertos', 'flacso-main-page'); ?>
                        </a>
                    </div>
                </div>
            </section>
            <?php
            return (string) ob_get_clean();
        }

        usort($seminarios_items, static function (array $a, array $b): int {
            $a_diff = isset($a['diff_days']) ? (int) $a['diff_days'] : PHP_INT_MAX;
            $b_diff = isset($b['diff_days']) ? (int) $b['diff_days'] : PHP_INT_MAX;

            $a_group = $a_diff >= 0 ? 0 : 1;
            $b_group = $b_diff >= 0 ? 0 : 1;
            if ($a_group !== $b_group) {
                return $a_group <=> $b_group;
            }

            if (0 === $a_group) {
                return $a_diff <=> $b_diff;
            }

            return $b_diff <=> $a_diff;
        });

        $seminarios_items = array_slice($seminarios_items, 0, $max_items);

        ob_start();
        ?>
        <section class="flacso-seminarios-proximos position-relative py-5">
            <div class="flacso-content-shell">
                <div class="text-center mb-5">
                    <h2 class="h2 mb-3" style="color: var(--global-palette1, #1d3a72); font-weight: 700;">
                        <?php esc_html_e('Seminarios Próximos', 'flacso-main-page'); ?>
                    </h2>
                    <p class="lead mb-0" style="color: var(--global-palette4, #6b7280);">
                        <?php esc_html_e('Formación intensiva con enfoque práctico', 'flacso-main-page'); ?>
                    </p>
                </div>

                <div class="row row-cols-1 row-cols-md-2 row-cols-lg-3 g-4" style="margin: 0;">
                    <?php
                    foreach ($seminarios_items as $seminario_item) :
                        ?>
                        <div class="col">
                            <article class="h-100 seminario-card" style="
                                background: #fff;
                                border-radius: 12px;
                                overflow: hidden;
                                box-shadow: 0 4px 12px rgba(0, 0, 0, 0.08);
                                transition: all 0.3s ease;
                                border: 1px solid #f0f1f3;
                                display: flex;
                                flex-direction: column;
                            ">
                                <!-- Imagen -->
                                <div class="position-relative" style="height: 220px; overflow: hidden; background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);">
                                    <img 
                                        src="<?php echo esc_url($seminario_item['img']); ?>" 
                                        alt="<?php echo esc_attr($seminario_item['title']); ?>" 
                                        style="width: 100%; height: 100%; object-fit: cover; opacity: 0.9;"
                                        loading="lazy">
                                    
                                    <!-- Badge -->
                                    <div style="
                                        position: absolute;
                                        top: 12px;
                                        right: 12px;
                                        background: <?php echo esc_attr($seminario_item['badge_color']); ?>;
                                        color: white;
                                        padding: 6px 14px;
                                        border-radius: 20px;
                                        font-size: 0.85rem;
                                        font-weight: 600;
                                        box-shadow: 0 2px 8px rgba(0, 0, 0, 0.15);
                                    ">
                                        <?php echo esc_html($seminario_item['estado_badge']); ?>
                                    </div>
                                </div>

                                <!-- Contenido -->
                                <div style="flex: 1; padding: 24px; display: flex; flex-direction: column;">
                                    <!-- Fecha y hora -->
                                    <div style="
                                        margin-bottom: 12px;
                                        padding-bottom: 12px;
                                        border-bottom: 1px solid #e5e7eb;
                                    ">
                                        <p style="margin: 0; color: var(--global-palette1, #1d3a72); font-size: 0.9rem; font-weight: 600;">
                                            <i class="bi bi-calendar3" style="margin-right: 6px;"></i>
                                            <?php echo esc_html($seminario_item['fecha']); ?>
                                        </p>
                                        <?php if (!empty($seminario_item['hora'])): ?>
                                            <p style="margin: 6px 0 0 0; color: #6b7280; font-size: 0.9rem;">
                                                <i class="bi bi-clock" style="margin-right: 6px;"></i>
                                                <?php echo esc_html($seminario_item['hora']); ?>
                                            </p>
                                        <?php endif; ?>
                                    </div>

                                    <!-- Título -->
                                    <h3 style="
                                        margin: 0 0 12px 0;
                                        font-size: 1.1rem;
                                        font-weight: 700;
                                        color: #0f1a2d;
                                        line-height: 1.4;
                                        flex-grow: 1;
                                    ">
                                        <?php echo esc_html($seminario_item['title']); ?>
                                    </h3>

                                    <!-- Botón -->
                                    <a href="<?php echo esc_url($seminario_item['permalink']); ?>" style="
                                        display: inline-block;
                                        margin-top: 12px;
                                        padding: 10px 16px;
                                        background: linear-gradient(135deg, var(--global-palette1, #1d3a72), #0f1a2d);
                                        color: white;
                                        text-decoration: none;
                                        border-radius: 6px;
                                        font-size: 0.9rem;
                                        font-weight: 600;
                                        transition: transform 0.2s ease, box-shadow 0.2s ease;
                                        text-align: center;
                                    " 
                                    onmouseover="this.style.transform='translateY(-2px)'; this.style.boxShadow='0 6px 16px rgba(29, 58, 114, 0.25)';"
                                    onmouseout="this.style.transform='translateY(0)'; this.style.boxShadow='none';">
                                        <?php esc_html_e('Ver detalles', 'flacso-main-page'); ?> →
                                    </a>
                                </div>
                            </article>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </section>

        <style>
            .flacso-seminarios-proximos {
                background: linear-gradient(135deg, #f8fafc 0%, #f1f4ff 100%);
                position: relative;
                overflow: hidden;
                padding: clamp(1.5rem, 4vw, 2rem) 0;
            }

            .flacso-seminarios-proximos::before {
                content: '';
                position: absolute;
                top: -50%;
                right: -10%;
                width: 600px;
                height: 600px;
                background: radial-gradient(circle, rgba(29, 58, 114, 0.08) 0%, transparent 70%);
                border-radius: 50%;
                pointer-events: none;
            }

            .flacso-seminarios-proximos .text-center {
                margin-bottom: clamp(1rem, 3vw, 1.5rem);
            }

            .flacso-seminarios-proximos .h2 {
                font-size: clamp(1.5rem, 4vw, 2.5rem);
                color: var(--global-palette1, #1d3a72);
                font-weight: 700;
                margin-bottom: clamp(0.5rem, 1.5vw, 1rem);
            }

            .flacso-seminarios-proximos .lead,
            .flacso-seminarios-proximos p {
                font-size: clamp(0.95rem, 2vw, 1.1rem);
                color: var(--global-palette4, #6b7280);
                line-height: 1.5;
            }

            /* Grid mobile-first */
            .flacso-seminarios-proximos .row {
                margin: 0;
                row-gap: clamp(1rem, 2.5vw, 1.5rem);
                column-gap: clamp(0.8rem, 2vw, 1.2rem);
            }

            .flacso-seminarios-proximos .col {
                padding: 0;
            }

            /* Solo 1 columna en móvil, 2 en tablet, 3 en desktop */
            @media (min-width: 576px) {
                /* Bootstrap's sm breakpoint */
                .flacso-seminarios-proximos .row-cols-sm-2 > * {
                    flex: 0 0 calc(50% - 0.6rem);
                }
            }

            @media (min-width: 992px) {
                /* Bootstrap's lg breakpoint */
                .flacso-seminarios-proximos .row-cols-lg-3 > * {
                    flex: 0 0 calc(33.333% - 0.8rem);
                }
            }

            .seminario-card {
                border-radius: 14px;
                overflow: hidden;
                box-shadow: 0 4px 12px rgba(0, 0, 0, 0.08);
                transition: all 0.3s ease;
                border: 1px solid #f0f1f3;
                display: flex;
                flex-direction: column;
            }

            .seminario-card:hover {
                box-shadow: 0 12px 24px rgba(0, 0, 0, 0.12) !important;
                transform: translateY(-4px);
            }

            .seminario-card > div:first-child {
                aspect-ratio: 1 / 1;
                height: auto;
                overflow: hidden;
                background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
                position: relative;
            }

            .seminario-card img {
                width: 100%;
                height: 100%;
                object-fit: cover;
                opacity: 0.9;
                display: block;
            }

            .seminario-card .position-absolute {
                top: clamp(0.75rem, 2vw, 1rem);
                right: clamp(0.75rem, 2vw, 1rem);
                background: var(--badge-color, #f59e0b);
                color: white;
                padding: clamp(0.4rem, 1vw, 0.6rem) clamp(0.8rem, 2vw, 1rem);
                border-radius: 20px;
                font-size: clamp(0.7rem, 1.5vw, 0.85rem);
                font-weight: 600;
                box-shadow: 0 2px 8px rgba(0, 0, 0, 0.15);
            }

            .seminario-card > div:last-child {
                flex: 1;
                padding: clamp(1rem, 2vw, 1.5rem);
                display: flex;
                flex-direction: column;
                gap: clamp(0.5rem, 1vw, 0.8rem);
            }

            .seminario-card h3 {
                margin: 0;
                font-size: clamp(1rem, 2.5vw, 1.1rem);
                font-weight: 700;
                color: #0f1a2d;
                line-height: 1.3;
                flex-grow: 1;
            }

            .seminario-card p {
                margin: 0;
                font-size: clamp(0.85rem, 1.5vw, 0.9rem);
                color: #6b7280;
                line-height: 1.4;
            }

            .seminario-card p strong {
                display: inline-block;
                margin-right: 0.4rem;
                color: var(--global-palette1, #1d3a72);
            }

            .seminario-card p i {
                margin-right: clamp(0.3rem, 0.5vw, 0.4rem);
            }

            .seminario-card div:last-child > div:first-of-type {
                margin-bottom: clamp(0.7rem, 1.5vw, 1rem);
                padding-bottom: clamp(0.7rem, 1.5vw, 1rem);
                border-bottom: 1px solid #e5e7eb;
            }

            .seminario-card a {
                display: inline-block;
                margin-top: auto;
                padding: clamp(0.7rem, 1.5vw, 0.8rem) clamp(1rem, 2vw, 1.25rem);
                background: linear-gradient(135deg, var(--global-palette1, #1d3a72), #0f1a2d);
                color: white;
                text-decoration: none;
                border-radius: 6px;
                font-size: clamp(0.8rem, 1.5vw, 0.9rem);
                font-weight: 600;
                transition: transform 0.2s ease, box-shadow 0.2s ease;
                text-align: center;
                width: 100%;
            }

            .seminario-card a:hover {
                transform: translateY(-2px);
                box-shadow: 0 6px 16px rgba(29, 58, 114, 0.25);
            }

            @media (max-width: 576px) {
                .flacso-seminarios-proximos {
                    padding: 1.5rem 0;
                }
                
                .seminario-card {
                    margin-bottom: 0;
                }
            }
        </style>

        <?php
        wp_reset_postdata();
        return ob_get_clean();
    }
}
