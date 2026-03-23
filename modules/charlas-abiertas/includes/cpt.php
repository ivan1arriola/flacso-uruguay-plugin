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

function flacso_charlas_abiertas_render_meta_box($post) {
    wp_nonce_field('flacso_charla_detalles_nonce', 'flacso_charla_detalles_nonce_field');

    $inicio = get_post_meta($post->ID, '_charla_inicio', true);
    $modalidad = get_post_meta($post->ID, '_charla_modalidad', true);
    $zoom_join_url = get_post_meta($post->ID, '_charla_zoom_join_url', true);
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
