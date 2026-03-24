<?php

if (!defined('ABSPATH')) {
    exit;
}

add_action('init', 'flacso_charlas_abiertas_register_cpt');
function flacso_charlas_abiertas_register_cpt() {
    register_post_type('charla_abierta', [
        'labels' => [
            'name' => 'Charlas Abiertas',
            'singular_name' => 'Charla Abierta',
            'add_new_item' => 'Agregar nueva charla',
            'edit_item' => 'Editar charla',
            'new_item' => 'Nueva charla',
            'view_item' => 'Ver charla',
            'search_items' => 'Buscar charlas',
        ],
        'public' => false,
        'publicly_queryable' => false,
        'exclude_from_search' => true,
        'show_ui' => true,
        'show_in_menu' => true,
        'show_in_nav_menus' => false,
        'show_in_rest' => true,
        'rest_base' => 'charla-abierta',
        'menu_icon' => 'dashicons-welcome-learn-more',
        'supports' => ['title', 'thumbnail'],
        'has_archive' => false,
        'rewrite' => false,
        'query_var' => false,
    ]);

    register_post_meta('charla_abierta', '_charla_inicio', [
        'single' => true,
        'type' => 'string',
        'show_in_rest' => true,
        'auth_callback' => '__return_true',
        'sanitize_callback' => 'sanitize_text_field',
    ]);

    register_post_meta('charla_abierta', '_charla_modalidad', [
        'single' => true,
        'type' => 'string',
        'show_in_rest' => true,
        'auth_callback' => '__return_true',
        'sanitize_callback' => 'sanitize_text_field',
    ]);

    register_post_meta('charla_abierta', '_charla_zoom_join_url', [
        'single' => true,
        'type' => 'string',
        'show_in_rest' => true,
        'auth_callback' => '__return_true',
        'sanitize_callback' => 'esc_url_raw',
    ]);

    register_post_meta('charla_abierta', '_charla_youtube_transmision_url', [
        'single' => true,
        'type' => 'string',
        'show_in_rest' => true,
        'auth_callback' => '__return_true',
        'sanitize_callback' => 'esc_url_raw',
    ]);

    register_post_meta('charla_abierta', '_charla_duracion_minutos', [
        'single' => true,
        'type' => 'integer',
        'show_in_rest' => true,
        'auth_callback' => '__return_true',
        'sanitize_callback' => 'absint',
    ]);

    register_post_meta('charla_abierta', '_charla_direccion', [
        'single' => true,
        'type' => 'string',
        'show_in_rest' => true,
        'auth_callback' => '__return_true',
        'sanitize_callback' => 'sanitize_text_field',
    ]);

    register_post_meta('charla_abierta', '_charla_descripcion', [
        'single' => true,
        'type' => 'string',
        'show_in_rest' => true,
        'auth_callback' => '__return_true',
        'sanitize_callback' => 'wp_kses_post',
    ]);
}

add_filter('use_block_editor_for_post_type', 'flacso_charlas_abiertas_disable_block_editor_for_cpt', 10, 2);
function flacso_charlas_abiertas_disable_block_editor_for_cpt($use_block_editor, $post_type) {
    if ('charla_abierta' === $post_type) {
        return false;
    }
    return $use_block_editor;
}

add_action('add_meta_boxes', 'flacso_charlas_abiertas_add_meta_boxes');
function flacso_charlas_abiertas_add_meta_boxes() {
    add_meta_box(
        'flacso_charla_detalles',
        'Datos de la charla',
        'flacso_charlas_abiertas_render_meta_box',
        'charla_abierta',
        'normal',
        'high'
    );
}

function flacso_charlas_abiertas_parse_duracion_hhmm_a_minutos($value) {
    if (!is_scalar($value)) {
        return null;
    }

    $text = trim((string) $value);
    if ('' === $text) {
        return null;
    }

    if (!preg_match('/^(\d{1,3}):([0-5]\d)$/', $text, $matches)) {
        return null;
    }

    $horas = (int) $matches[1];
    $minutos = (int) $matches[2];

    return ($horas * 60) + $minutos;
}

function flacso_charlas_abiertas_format_duracion_hhmm_desde_minutos($value) {
    if (!is_scalar($value) || '' === trim((string) $value) || !is_numeric($value)) {
        return '';
    }

    $total_minutos = max(0, (int) $value);
    $horas = (int) floor($total_minutos / 60);
    $minutos = $total_minutos % 60;

    return sprintf('%02d:%02d', $horas, $minutos);
}

function flacso_charlas_abiertas_render_meta_box($post) {
    wp_nonce_field('flacso_charla_detalles_nonce', 'flacso_charla_detalles_nonce_field');

    $inicio = get_post_meta($post->ID, '_charla_inicio', true);
    $modalidad = get_post_meta($post->ID, '_charla_modalidad', true);
    $zoom_join_url = get_post_meta($post->ID, '_charla_zoom_join_url', true);
    $youtube_transmision_url = get_post_meta($post->ID, '_charla_youtube_transmision_url', true);
    $duracion_minutos = get_post_meta($post->ID, '_charla_duracion_minutos', true);
    $duracion_hhmm = flacso_charlas_abiertas_format_duracion_hhmm_desde_minutos($duracion_minutos);
    $direccion = get_post_meta($post->ID, '_charla_direccion', true);
    $descripcion = get_post_meta($post->ID, '_charla_descripcion', true);
    $inicio_fecha = '';
    $inicio_hora = '';
    $timezone_label = wp_timezone_string();
    $timezone_offset_for_preview = wp_date('P');

    if (!$timezone_label) {
        $timezone_label = 'UTC';
    }

    if (!empty($inicio)) {
        try {
            $inicio_dt = new DateTimeImmutable($inicio);
            $inicio_fecha = $inicio_dt->format('Y-m-d');
            $inicio_hora = $inicio_dt->format('H:i');
        } catch (Exception $e) {
            $inicio_fecha = '';
            $inicio_hora = '';
        }
    }

    if (!$modalidad) {
        $modalidad = 'virtual';
    }
    ?>
    <p>
        <label for="flacso_charla_inicio_fecha"><strong>Fecha de inicio</strong></label><br>
        <input
            type="date"
            id="flacso_charla_inicio_fecha"
            name="flacso_charla_inicio_fecha"
            value="<?php echo esc_attr($inicio_fecha); ?>"
            style="width:100%;max-width:240px;"
        />
    </p>
    <p>
        <label for="flacso_charla_inicio_hora"><strong>Hora de comienzo</strong></label><br>
        <input
            type="time"
            id="flacso_charla_inicio_hora"
            name="flacso_charla_inicio_hora"
            value="<?php echo esc_attr($inicio_hora); ?>"
            step="60"
            style="width:100%;max-width:160px;"
        />
    </p>
    <p>
        <label for="flacso_charla_inicio"><strong>Inicio (ISO8601 generado automáticamente)</strong></label><br>
        <input
            type="text"
            id="flacso_charla_inicio"
            name="flacso_charla_inicio"
            value="<?php echo esc_attr($inicio); ?>"
            placeholder="2026-03-15T18:00:00-03:00"
            style="width:100%;"
            readonly
            data-tz-offset="<?php echo esc_attr($timezone_offset_for_preview); ?>"
        />
        <small style="display:block;margin-top:4px;color:#646970;">
            Zona horaria del sitio: <?php echo esc_html($timezone_label); ?>
        </small>
    </p>
    <p>
        <label for="flacso_charla_modalidad"><strong>Modalidad</strong></label><br>
        <select id="flacso_charla_modalidad" name="flacso_charla_modalidad">
            <option value="virtual" <?php selected($modalidad, 'virtual'); ?>>Virtual</option>
            <option value="presencial" <?php selected($modalidad, 'presencial'); ?>>Presencial</option>
            <option value="hibrida" <?php selected($modalidad, 'hibrida'); ?>>Híbrida</option>
        </select>
    </p>
    <p>
        <label for="flacso_charla_zoom_join_url"><strong>Zoom Join URL</strong></label><br>
        <input
            type="url"
            id="flacso_charla_zoom_join_url"
            name="flacso_charla_zoom_join_url"
            value="<?php echo esc_attr($zoom_join_url); ?>"
            placeholder="https://zoom.us/j/123456789"
            style="width:100%;"
        />
    </p>
    <p>
        <label for="flacso_charla_youtube_transmision_url"><strong>YouTube Transmision URL</strong></label><br>
        <input
            type="url"
            id="flacso_charla_youtube_transmision_url"
            name="flacso_charla_youtube_transmision_url"
            value="<?php echo esc_attr($youtube_transmision_url); ?>"
            placeholder="https://www.youtube.com/watch?v=..."
            style="width:100%;"
        />
    </p>
    <p>
        <label for="flacso_charla_duracion_hhmm"><strong>Duracion (HH:MM)</strong></label><br>
        <input
            type="text"
            id="flacso_charla_duracion_hhmm"
            name="flacso_charla_duracion_hhmm"
            value="<?php echo esc_attr($duracion_hhmm); ?>"
            placeholder="01:30"
            pattern="[0-9]{1,3}:[0-5][0-9]"
            inputmode="numeric"
            style="width:100%;max-width:160px;"
        />
        <small style="display:block;margin-top:4px;color:#646970;">
            Formato HH:MM. En la API se expone en minutos.
        </small>
    </p>
    <p>
        <label for="flacso_charla_direccion"><strong>Dirección</strong></label><br>
        <input
            type="text"
            id="flacso_charla_direccion"
            name="flacso_charla_direccion"
            value="<?php echo esc_attr($direccion); ?>"
            style="width:100%;"
        />
    </p>
    <p>
        <label for="flacso_charla_descripcion_editor"><strong>Descripción (HTML)</strong></label><br>
        <?php
        wp_editor($descripcion, 'flacso_charla_descripcion_editor', [
            'textarea_name' => 'flacso_charla_descripcion',
            'media_buttons' => false,
            'textarea_rows' => 6,
            'teeny' => false,
            'quicktags' => true,
        ]);
        ?>
    </p>
    <script>
      (function() {
        var fecha = document.getElementById('flacso_charla_inicio_fecha');
        var hora = document.getElementById('flacso_charla_inicio_hora');
        var iso = document.getElementById('flacso_charla_inicio');
        if (!fecha || !hora || !iso) {
          return;
        }
        var tzOffset = iso.getAttribute('data-tz-offset') || '+00:00';
        function updateIsoPreview() {
          if (!fecha.value || !hora.value) {
            return;
          }
          var hhmm = hora.value.length === 5 ? hora.value + ':00' : hora.value;
          iso.value = fecha.value + 'T' + hhmm + tzOffset;
        }
        fecha.addEventListener('input', updateIsoPreview);
        hora.addEventListener('input', updateIsoPreview);
      })();
    </script>
    <?php
}

add_action('save_post_charla_abierta', 'flacso_charlas_abiertas_save_meta', 10, 2);
function flacso_charlas_abiertas_save_meta($post_id, $post) {
    if (!isset($_POST['flacso_charla_detalles_nonce_field'])) {
        return;
    }

    if (!wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['flacso_charla_detalles_nonce_field'])), 'flacso_charla_detalles_nonce')) {
        return;
    }

    if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) {
        return;
    }

    if (!current_user_can('edit_post', $post_id)) {
        return;
    }

    if ('charla_abierta' !== $post->post_type) {
        return;
    }

    $inicio_fecha = isset($_POST['flacso_charla_inicio_fecha']) ? sanitize_text_field(wp_unslash($_POST['flacso_charla_inicio_fecha'])) : '';
    $inicio_hora = isset($_POST['flacso_charla_inicio_hora']) ? sanitize_text_field(wp_unslash($_POST['flacso_charla_inicio_hora'])) : '';
    $inicio_guardado = '';

    if (!empty($inicio_fecha) && !empty($inicio_hora)) {
        $tz = wp_timezone();
        $inicio_dt = DateTimeImmutable::createFromFormat('Y-m-d H:i', $inicio_fecha . ' ' . $inicio_hora, $tz);
        if ($inicio_dt instanceof DateTimeImmutable) {
            $inicio_guardado = $inicio_dt->format('Y-m-d\TH:i:sP');
        }
    }

    if (empty($inicio_guardado) && isset($_POST['flacso_charla_inicio'])) {
        // Compatibilidad por si llega el campo ISO manual desde versiones anteriores.
        $inicio_guardado = sanitize_text_field(wp_unslash($_POST['flacso_charla_inicio']));
    }

    if (!empty($inicio_guardado)) {
        update_post_meta($post_id, '_charla_inicio', $inicio_guardado);
    }

    if (isset($_POST['flacso_charla_modalidad'])) {
        $modalidad = sanitize_text_field(wp_unslash($_POST['flacso_charla_modalidad']));
        if (!in_array($modalidad, ['virtual', 'presencial', 'hibrida'], true)) {
            $modalidad = 'virtual';
        }
        update_post_meta($post_id, '_charla_modalidad', $modalidad);
    }

    if (isset($_POST['flacso_charla_zoom_join_url'])) {
        update_post_meta($post_id, '_charla_zoom_join_url', esc_url_raw(wp_unslash($_POST['flacso_charla_zoom_join_url'])));
    }

    if (isset($_POST['flacso_charla_youtube_transmision_url'])) {
        update_post_meta($post_id, '_charla_youtube_transmision_url', esc_url_raw(wp_unslash($_POST['flacso_charla_youtube_transmision_url'])));
    }

    if (isset($_POST['flacso_charla_duracion_hhmm'])) {
        $duracion_hhmm = sanitize_text_field(wp_unslash($_POST['flacso_charla_duracion_hhmm']));
        $duracion_minutos = flacso_charlas_abiertas_parse_duracion_hhmm_a_minutos($duracion_hhmm);

        if (null === $duracion_minutos) {
            if ('' === trim($duracion_hhmm)) {
                delete_post_meta($post_id, '_charla_duracion_minutos');
            }
        } else {
            update_post_meta($post_id, '_charla_duracion_minutos', $duracion_minutos);
        }
    }

    if (isset($_POST['flacso_charla_direccion'])) {
        update_post_meta($post_id, '_charla_direccion', sanitize_text_field(wp_unslash($_POST['flacso_charla_direccion'])));
    }

    if (isset($_POST['flacso_charla_descripcion'])) {
        update_post_meta($post_id, '_charla_descripcion', wp_kses_post(wp_unslash($_POST['flacso_charla_descripcion'])));
    }
}

add_filter('manage_edit-charla_abierta_columns', 'flacso_charlas_abiertas_admin_columns');
function flacso_charlas_abiertas_admin_columns($columns) {
    $new = [];
    $new['cb'] = isset($columns['cb']) ? $columns['cb'] : '<input type="checkbox" />';
    $new['title'] = isset($columns['title']) ? $columns['title'] : 'Título';
    $new['charla_inicio'] = 'Inicio';
    $new['charla_modalidad'] = 'Modalidad';
    $new['charla_acceso'] = 'Acceso / Lugar';
    $new['charla_estado'] = 'Estado';
    $new['date'] = isset($columns['date']) ? $columns['date'] : 'Fecha';
    return $new;
}

add_action('manage_charla_abierta_posts_custom_column', 'flacso_charlas_abiertas_render_admin_column', 10, 2);
function flacso_charlas_abiertas_render_admin_column($column, $post_id) {
    if ('charla_inicio' === $column) {
        $inicio = get_post_meta($post_id, '_charla_inicio', true);
        if (empty($inicio)) {
            echo '<span style="color:#646970;">Sin definir</span>';
            return;
        }
        try {
            $dt = new DateTimeImmutable($inicio);
            echo esc_html(wp_date('d/m/Y H:i', $dt->getTimestamp()));
        } catch (Exception $e) {
            echo esc_html($inicio);
        }
        return;
    }

    if ('charla_modalidad' === $column) {
        $modalidad = get_post_meta($post_id, '_charla_modalidad', true);
        if (!$modalidad) {
            $modalidad = 'virtual';
        }
        echo esc_html(ucfirst($modalidad));
        return;
    }

    if ('charla_acceso' === $column) {
        $modalidad = get_post_meta($post_id, '_charla_modalidad', true);
        $zoom = get_post_meta($post_id, '_charla_zoom_join_url', true);
        $direccion = get_post_meta($post_id, '_charla_direccion', true);

        if ('presencial' === $modalidad) {
            echo $direccion ? esc_html($direccion) : '<span style="color:#646970;">Sin dirección</span>';
            return;
        }

        if ('hibrida' === $modalidad) {
            $parts = [];
            $parts[] = $direccion ? esc_html($direccion) : '<span style="color:#646970;">Sin dirección</span>';
            $parts[] = $zoom ? '<a href="' . esc_url($zoom) . '" target="_blank" rel="noopener noreferrer">Zoom</a>' : '<span style="color:#646970;">Sin Zoom</span>';
            echo wp_kses_post(implode('<br>', $parts));
            return;
        }

        if ($zoom) {
            echo '<a href="' . esc_url($zoom) . '" target="_blank" rel="noopener noreferrer">Abrir Zoom</a>';
            return;
        }

        echo '<span style="color:#646970;">Sin Zoom</span>';
        return;
    }

    if ('charla_estado' === $column) {
        $inicio = get_post_meta($post_id, '_charla_inicio', true);
        if (empty($inicio)) {
            echo '<span style="color:#646970;">Sin fecha</span>';
            return;
        }
        try {
            $dt = new DateTimeImmutable($inicio);
            $now = new DateTimeImmutable('now', wp_timezone());
            if ($dt->getTimestamp() >= $now->getTimestamp()) {
                echo '<span style="color:#0f5132;font-weight:600;">Próxima</span>';
            } else {
                echo '<span style="color:#842029;font-weight:600;">Finalizada</span>';
            }
        } catch (Exception $e) {
            echo '<span style="color:#646970;">Sin fecha</span>';
        }
    }
}

add_filter('manage_edit-charla_abierta_sortable_columns', 'flacso_charlas_abiertas_sortable_columns');
function flacso_charlas_abiertas_sortable_columns($columns) {
    $columns['charla_inicio'] = 'charla_inicio';
    $columns['charla_modalidad'] = 'charla_modalidad';
    return $columns;
}

add_action('pre_get_posts', 'flacso_charlas_abiertas_admin_list_ordering');
function flacso_charlas_abiertas_admin_list_ordering($query) {
    if (!is_admin() || !$query->is_main_query()) {
        return;
    }

    $post_type = $query->get('post_type');
    if ('charla_abierta' !== $post_type) {
        return;
    }

    $orderby = $query->get('orderby');

    if ('charla_inicio' === $orderby) {
        $query->set('meta_key', '_charla_inicio');
        $query->set('orderby', 'meta_value');
        return;
    }

    if ('charla_modalidad' === $orderby) {
        $query->set('meta_key', '_charla_modalidad');
        $query->set('orderby', 'meta_value');
        return;
    }

    // Orden por defecto: próximas primero por fecha de inicio.
    if (empty($orderby)) {
        $query->set('meta_key', '_charla_inicio');
        $query->set('orderby', 'meta_value');
        $query->set('order', 'ASC');
    }
}

add_action('admin_menu', 'flacso_charlas_abiertas_add_visualizer_menu', 30);
function flacso_charlas_abiertas_add_visualizer_menu() {
    add_submenu_page(
        'edit.php?post_type=charla_abierta',
        'Visualizador de Charlas',
        'Visualizador',
        'edit_posts',
        'flacso-charlas-abiertas-visualizador',
        'flacso_charlas_abiertas_render_visualizer_page'
    );
}

function flacso_charlas_abiertas_parse_inicio_timestamp_visualizer($inicio) {
    if (!is_scalar($inicio) || '' === trim((string) $inicio)) {
        return null;
    }

    try {
        return (new DateTimeImmutable((string) $inicio))->getTimestamp();
    } catch (Exception $e) {
        return null;
    }
}

function flacso_charlas_abiertas_build_visualizer_item($post, $now_timestamp) {
    $post = get_post($post);
    if (!$post || 'charla_abierta' !== $post->post_type || 'publish' !== $post->post_status) {
        return null;
    }

    $inicio = (string) get_post_meta($post->ID, '_charla_inicio', true);
    $inicio_timestamp = flacso_charlas_abiertas_parse_inicio_timestamp_visualizer($inicio);
    $duracion_minutos_raw = get_post_meta($post->ID, '_charla_duracion_minutos', true);
    $duracion_minutos = ('' !== trim((string) $duracion_minutos_raw) && is_numeric($duracion_minutos_raw))
        ? max(0, (int) $duracion_minutos_raw)
        : null;
    $fin_timestamp = (null !== $inicio_timestamp && null !== $duracion_minutos && $duracion_minutos > 0)
        ? ($inicio_timestamp + ($duracion_minutos * MINUTE_IN_SECONDS))
        : null;

    $status_key = 'sin_fecha';
    $status_label = 'Sin fecha';
    $priority = 99;
    $grupo = 'historico';

    if (null !== $inicio_timestamp) {
        $faltan_dias = (int) floor(($inicio_timestamp - $now_timestamp) / DAY_IN_SECONDS);
        if ($inicio_timestamp > $now_timestamp) {
            $grupo = 'proximos';
            if ($faltan_dias > 1) {
                $status_key = 'proximo';
                $status_label = sprintf('Faltan %s dias', number_format_i18n($faltan_dias));
                $priority = 3;
            } elseif (1 === $faltan_dias) {
                $status_key = 'manana';
                $status_label = 'Manana';
                $priority = 2;
            } else {
                $status_key = 'hoy';
                $status_label = 'Hoy';
                $priority = 1;
            }
        } elseif (null !== $fin_timestamp && $fin_timestamp > $now_timestamp) {
            $grupo = 'proximos';
            $status_key = 'en_curso';
            $status_label = 'En curso';
            $priority = 0;
        } elseif (wp_date('Y-m-d', $inicio_timestamp) === wp_date('Y-m-d', $now_timestamp)) {
            $grupo = 'proximos';
            $status_key = 'hoy';
            $status_label = 'Hoy';
            $priority = 1;
        } else {
            $status_key = 'finalizado';
            $status_label = 'Finalizado';
        }
    }

    $dias_para_inicio = null !== $inicio_timestamp
        ? (int) floor(($inicio_timestamp - $now_timestamp) / DAY_IN_SECONDS)
        : null;
    $is_urgente = in_array($status_key, ['en_curso', 'hoy', 'manana'], true)
        || ('proximo' === $status_key && null !== $dias_para_inicio && $dias_para_inicio <= 7);

    $modalidad = (string) get_post_meta($post->ID, '_charla_modalidad', true);
    if ('' === $modalidad) {
        $modalidad = 'virtual';
    }

    return [
        'id' => (int) $post->ID,
        'titulo' => get_the_title($post),
        'img' => get_the_post_thumbnail_url($post, 'medium') ?: 'https://via.placeholder.com/300x160?text=Charla',
        'inicio_timestamp' => $inicio_timestamp,
        'fin_timestamp' => $fin_timestamp,
        'duracion_minutos' => $duracion_minutos,
        'duracion_hhmm' => null !== $duracion_minutos ? flacso_charlas_abiertas_format_duracion_hhmm_desde_minutos($duracion_minutos) : '',
        'modalidad' => $modalidad,
        'zoom_join_url' => (string) get_post_meta($post->ID, '_charla_zoom_join_url', true),
        'youtube_transmision_url' => (string) get_post_meta($post->ID, '_charla_youtube_transmision_url', true),
        'direccion' => (string) get_post_meta($post->ID, '_charla_direccion', true),
        'status_key' => $status_key,
        'status_label' => $status_label,
        'priority' => $priority,
        'is_urgente' => $is_urgente,
        'grupo' => $grupo,
    ];
}

function flacso_charlas_abiertas_render_visualizer_card($item) {
    $card_classes = ['charla-card', 'status-' . sanitize_html_class((string) $item['status_key'])];
    if (!empty($item['is_urgente'])) {
        $card_classes[] = 'is-urgente';
    }

    $datetime_label = '';
    if (is_int($item['inicio_timestamp'])) {
        $datetime_label = wp_date('j F Y', (int) $item['inicio_timestamp']);
        $datetime_label .= ' - ' . wp_date('H:i', (int) $item['inicio_timestamp']);
    }

    echo '<article class="' . esc_attr(implode(' ', $card_classes)) . '">';
    echo '<div class="charla-img"><img src="' . esc_url((string) $item['img']) . '" alt=""></div>';
    echo '<div class="charla-content">';
    echo '<h2>' . esc_html((string) $item['titulo']) . '</h2>';

    if ('' !== $datetime_label) {
        echo '<p class="charla-meta">' . esc_html($datetime_label) . '</p>';
    }

    echo '<p class="charla-meta">Modalidad: ' . esc_html(ucfirst((string) $item['modalidad'])) . '</p>';
    if (!empty($item['duracion_hhmm'])) {
        echo '<p class="charla-meta">Duracion: ' . esc_html((string) $item['duracion_hhmm']) . '</p>';
    }
    if (!empty($item['direccion']) && in_array((string) $item['modalidad'], ['presencial', 'hibrida'], true)) {
        echo '<p class="charla-meta">Lugar: ' . esc_html((string) $item['direccion']) . '</p>';
    }

    $links = [];
    if (!empty($item['zoom_join_url'])) {
        $links[] = '<a href="' . esc_url((string) $item['zoom_join_url']) . '" target="_blank" rel="noopener noreferrer">Zoom</a>';
    }
    if (!empty($item['youtube_transmision_url'])) {
        $links[] = '<a href="' . esc_url((string) $item['youtube_transmision_url']) . '" target="_blank" rel="noopener noreferrer">YouTube</a>';
    }
    if (!empty($links)) {
        echo '<p class="charla-links">' . wp_kses_post(implode(' · ', $links)) . '</p>';
    }

    echo '<p class="charla-tiempo"><span class="charla-tiempo-badge">' . esc_html((string) $item['status_label']) . '</span></p>';
    echo '</div>';
    echo '<div class="charla-acciones">';
    echo '<a href="' . esc_url(get_edit_post_link((int) $item['id'])) . '">Editar charla</a> · ';
    echo '<a href="' . esc_url(rest_url('flacso-charlas/v1/charla-abierta/' . (int) $item['id'])) . '" target="_blank" rel="noopener">Ver API</a>';
    echo '</div>';
    echo '</article>';
}

function flacso_charlas_abiertas_render_visualizer_page() {
    $charlas = get_posts([
        'post_type' => 'charla_abierta',
        'post_status' => 'publish',
        'posts_per_page' => -1,
        'meta_key' => '_charla_inicio',
        'orderby' => 'meta_value',
        'order' => 'ASC',
    ]);

    echo '<div class="wrap"><h1>Visualizador de Charlas Abiertas</h1>';

    if (empty($charlas)) {
        echo '<p>No hay charlas publicadas.</p></div>';
        return;
    }

    $now_timestamp = (int) current_time('timestamp');
    $proximos = [];
    $historicos = [];

    foreach ($charlas as $charla) {
        $item = flacso_charlas_abiertas_build_visualizer_item($charla, $now_timestamp);
        if (!$item) {
            continue;
        }

        if ('historico' === $item['grupo']) {
            $historicos[] = $item;
        } else {
            $proximos[] = $item;
        }
    }

    usort($proximos, static function ($a, $b) {
        if ((int) $a['priority'] !== (int) $b['priority']) {
            return ((int) $a['priority'] < (int) $b['priority']) ? -1 : 1;
        }
        $a_start = is_int($a['inicio_timestamp']) ? (int) $a['inicio_timestamp'] : PHP_INT_MAX;
        $b_start = is_int($b['inicio_timestamp']) ? (int) $b['inicio_timestamp'] : PHP_INT_MAX;
        return $a_start <=> $b_start;
    });

    usort($historicos, static function ($a, $b) {
        $a_start = is_int($a['inicio_timestamp']) ? (int) $a['inicio_timestamp'] : PHP_INT_MIN;
        $b_start = is_int($b['inicio_timestamp']) ? (int) $b['inicio_timestamp'] : PHP_INT_MIN;
        return $b_start <=> $a_start;
    });

    $count_en_curso = 0;
    $count_7_dias = 0;
    $count_30_dias = 0;
    foreach ($proximos as $item) {
        if ('en_curso' === $item['status_key']) {
            $count_en_curso++;
            continue;
        }

        if (!is_int($item['inicio_timestamp']) || (int) $item['inicio_timestamp'] < $now_timestamp) {
            continue;
        }

        $diff = (int) $item['inicio_timestamp'] - $now_timestamp;
        if ($diff <= 7 * DAY_IN_SECONDS) {
            $count_7_dias++;
        }
        if ($diff <= 30 * DAY_IN_SECONDS) {
            $count_30_dias++;
        }
    }

    $proximos_destacados = [];
    $proximos_mas_adelante = [];
    foreach ($proximos as $item) {
        if ('en_curso' === $item['status_key']) {
            $proximos_destacados[] = $item;
            continue;
        }

        if (
            is_int($item['inicio_timestamp'])
            && (int) $item['inicio_timestamp'] >= $now_timestamp
            && ((int) $item['inicio_timestamp'] - $now_timestamp) <= 30 * DAY_IN_SECONDS
        ) {
            $proximos_destacados[] = $item;
            continue;
        }

        $proximos_mas_adelante[] = $item;
    }

    ?>
    <style>
        .flacso-charlas-visualizer .charla-toolbar { display:flex; flex-wrap:wrap; gap:10px; margin:14px 0 6px; }
        .flacso-charlas-visualizer .charla-chip { background:#f0f6fc; border:1px solid #c5d9ed; border-radius:999px; color:#174a7c; font-size:12px; font-weight:600; padding:6px 10px; }
        .flacso-charlas-visualizer .charla-chip.is-primary { background:#174a7c; border-color:#174a7c; color:#fff; }
        .flacso-charlas-visualizer .charla-section { margin-top:16px; }
        .flacso-charlas-visualizer .charla-section-title { margin:0 0 10px; font-size:18px; font-weight:700; }
        .flacso-charlas-visualizer .charla-empty { margin:0; color:#555; }
        .flacso-charlas-visualizer .grid-charlas { display:grid; grid-template-columns:repeat(auto-fill,minmax(280px,1fr)); gap:20px; margin-top:20px; }
        .flacso-charlas-visualizer .charla-card { background:#fff; border-radius:10px; overflow:hidden; box-shadow:0 2px 6px rgba(0,0,0,.08); border:1px solid #ddd; display:flex; flex-direction:column; transition:transform .2s ease, box-shadow .2s ease; }
        .flacso-charlas-visualizer .charla-card:hover { transform:translateY(-3px); box-shadow:0 4px 12px rgba(0,0,0,.15); }
        .flacso-charlas-visualizer .charla-card.is-urgente { border-color:#dba617; box-shadow:0 3px 10px rgba(219,166,23,.25); }
        .flacso-charlas-visualizer .charla-card.status-en_curso { border-color:#2f7d32; box-shadow:0 3px 10px rgba(47,125,50,.2); }
        .flacso-charlas-visualizer .charla-card.status-finalizado, .flacso-charlas-visualizer .charla-card.status-sin_fecha { opacity:.92; }
        .flacso-charlas-visualizer .charla-img img { width:100%; height:160px; object-fit:cover; }
        .flacso-charlas-visualizer .charla-content { padding:12px 15px; flex:1; }
        .flacso-charlas-visualizer .charla-content h2 { margin:0 0 8px; font-size:16px; }
        .flacso-charlas-visualizer .charla-content p { margin:4px 0; color:#444; font-size:13px; }
        .flacso-charlas-visualizer .charla-meta { font-size:12px; color:#777; }
        .flacso-charlas-visualizer .charla-links a { text-decoration:none; color:#0073aa; font-weight:600; }
        .flacso-charlas-visualizer .charla-links a:hover { color:#00a0d2; }
        .flacso-charlas-visualizer .charla-tiempo { margin-top:10px; }
        .flacso-charlas-visualizer .charla-tiempo-badge { display:inline-block; font-size:12px; font-weight:700; border-radius:999px; background:#edf5ff; color:#1f4f82; border:1px solid #c8ddf4; padding:4px 10px; }
        .flacso-charlas-visualizer .charla-card.status-finalizado .charla-tiempo-badge, .flacso-charlas-visualizer .charla-card.status-sin_fecha .charla-tiempo-badge { background:#f4f5f6; border-color:#d8dde3; color:#4f5b66; }
        .flacso-charlas-visualizer .charla-card.status-en_curso .charla-tiempo-badge { background:#edf8ee; border-color:#cae8cc; color:#1f5c24; }
        .flacso-charlas-visualizer .charla-acciones { padding:10px 15px; background:#f8f9fa; border-top:1px solid #eee; text-align:right; }
        .flacso-charlas-visualizer .charla-acciones a { text-decoration:none; color:#0073aa; font-weight:500; }
        .flacso-charlas-visualizer .charla-acciones a:hover { color:#00a0d2; }
        .flacso-charlas-visualizer details.charla-colapsable { margin-top:24px; border:1px solid #dcdcde; border-radius:8px; background:#fff; padding:8px 12px 14px; }
        .flacso-charlas-visualizer details.charla-colapsable > summary { cursor:pointer; font-weight:600; padding:4px 2px; user-select:none; }
        .flacso-charlas-visualizer details.charla-colapsable[open] > summary { margin-bottom:8px; }
    </style>
    <?php

    echo '<div class="flacso-charlas-visualizer">';
    echo '<div class="charla-toolbar">';
    echo '<span class="charla-chip is-primary">En foco (en curso + 30 dias): ' . esc_html(number_format_i18n(count($proximos_destacados))) . '</span>';
    echo '<span class="charla-chip">En curso: ' . esc_html(number_format_i18n($count_en_curso)) . '</span>';
    echo '<span class="charla-chip">En 7 dias: ' . esc_html(number_format_i18n($count_7_dias)) . '</span>';
    echo '<span class="charla-chip">En 30 dias: ' . esc_html(number_format_i18n($count_30_dias)) . '</span>';
    echo '<span class="charla-chip">Mas adelante: ' . esc_html(number_format_i18n(count($proximos_mas_adelante))) . '</span>';
    echo '<span class="charla-chip">Historico: ' . esc_html(number_format_i18n(count($historicos))) . '</span>';
    echo '</div>';

    echo '<section class="charla-section">';
    echo '<h2 class="charla-section-title">Lo que se viene (en curso + 30 dias)</h2>';
    if (empty($proximos_destacados)) {
        echo '<p class="charla-empty">No hay charlas cercanas en este momento.</p>';
    } else {
        echo '<div class="grid-charlas">';
        foreach ($proximos_destacados as $item) {
            flacso_charlas_abiertas_render_visualizer_card($item);
        }
        echo '</div>';
    }
    echo '</section>';

    echo '<details class="charla-colapsable">';
    echo '<summary>Ver mas adelante (' . esc_html(number_format_i18n(count($proximos_mas_adelante))) . ')</summary>';
    if (empty($proximos_mas_adelante)) {
        echo '<p class="charla-empty">No hay charlas fuera de la ventana de 30 dias.</p>';
    } else {
        echo '<div class="grid-charlas">';
        foreach ($proximos_mas_adelante as $item) {
            flacso_charlas_abiertas_render_visualizer_card($item);
        }
        echo '</div>';
    }
    echo '</details>';

    echo '<details class="charla-colapsable">';
    echo '<summary>Ver historico (' . esc_html(number_format_i18n(count($historicos))) . ')</summary>';
    if (empty($historicos)) {
        echo '<p class="charla-empty">No hay charlas historicas para mostrar.</p>';
    } else {
        echo '<div class="grid-charlas">';
        foreach ($historicos as $item) {
            flacso_charlas_abiertas_render_visualizer_card($item);
        }
        echo '</div>';
    }
    echo '</details>';

    echo '</div>';
    echo '</div>';
}
