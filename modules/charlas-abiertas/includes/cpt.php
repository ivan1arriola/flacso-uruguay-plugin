<?php

if (!defined('ABSPATH')) {
    exit;
}

if (!function_exists('flacso_charlas_abiertas_normalize_form_variant')) {
    function flacso_charlas_abiertas_normalize_form_variant($value) {
        $variant = sanitize_key((string) $value);
        return 'nombre_apellido' === $variant ? 'nombre_apellido' : 'estandar';
    }
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
        'supports' => ['title', 'thumbnail', 'custom-fields'],
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

    register_post_meta('charla_abierta', '_charla_lugar_nombre', [
        'single' => true,
        'type' => 'string',
        'show_in_rest' => true,
        'auth_callback' => '__return_true',
        'sanitize_callback' => 'sanitize_text_field',
    ]);

    register_post_meta('charla_abierta', '_charla_google_maps_url', [
        'single' => true,
        'type' => 'string',
        'show_in_rest' => true,
        'auth_callback' => '__return_true',
        'sanitize_callback' => 'esc_url_raw',
    ]);

    register_post_meta('charla_abierta', '_charla_descripcion', [
        'single' => true,
        'type' => 'string',
        'show_in_rest' => true,
        'auth_callback' => '__return_true',
        'sanitize_callback' => 'wp_kses_post',
    ]);

    register_post_meta('charla_abierta', '_charla_form_variant', [
        'single' => true,
        'type' => 'string',
        'show_in_rest' => true,
        'auth_callback' => '__return_true',
        'sanitize_callback' => 'flacso_charlas_abiertas_normalize_form_variant',
    ]);

    register_post_meta('charla_abierta', '_charla_evento_id', [
        'single' => true,
        'type' => 'integer',
        'show_in_rest' => true,
        'auth_callback' => '__return_true',
        'sanitize_callback' => 'absint',
    ]);

    register_post_meta('charla_abierta', '_charla_sync_post', [
        'single' => true,
        'type' => 'boolean',
        'show_in_rest' => true,
        'auth_callback' => '__return_true',
        'sanitize_callback' => static function ($value) {
            return !empty($value);
        },
    ]);

    register_post_meta('charla_abierta', '_charla_sync_evento', [
        'single' => true,
        'type' => 'boolean',
        'show_in_rest' => true,
        'auth_callback' => '__return_true',
        'sanitize_callback' => static function ($value) {
            return !empty($value);
        },
    ]);

    register_post_meta('charla_abierta', '_charla_ocultar_post', [
        'single' => true,
        'type' => 'boolean',
        'show_in_rest' => true,
        'auth_callback' => '__return_true',
        'sanitize_callback' => static function ($value) {
            return !empty($value);
        },
    ]);

    register_post_meta('charla_abierta', '_charla_ocultar_evento', [
        'single' => true,
        'type' => 'boolean',
        'show_in_rest' => true,
        'auth_callback' => '__return_true',
        'sanitize_callback' => static function ($value) {
            return !empty($value);
        },
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
    $lugar_nombre = get_post_meta($post->ID, '_charla_lugar_nombre', true);
    $direccion = get_post_meta($post->ID, '_charla_direccion', true);
    $google_maps_url = get_post_meta($post->ID, '_charla_google_maps_url', true);
    $descripcion = get_post_meta($post->ID, '_charla_descripcion', true);
    $form_variant = flacso_charlas_abiertas_normalize_form_variant(
        get_post_meta($post->ID, '_charla_form_variant', true)
    );
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
        <label for="flacso_charla_lugar_nombre"><strong>Nombre del lugar</strong></label><br>
        <input
            type="text"
            id="flacso_charla_lugar_nombre"
            name="flacso_charla_lugar_nombre"
            value="<?php echo esc_attr($lugar_nombre); ?>"
            style="width:100%;"
            placeholder="Ej. FLACSO Uruguay, Auditorio principal"
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
            placeholder="Ej. Centro Cultural, salón 2, Rivera 1234, Montevideo"
        />
    </p>
    <p>
        <label for="flacso_charla_google_maps_url"><strong>Google Maps URL</strong></label><br>
        <input
            type="url"
            id="flacso_charla_google_maps_url"
            name="flacso_charla_google_maps_url"
            value="<?php echo esc_attr($google_maps_url); ?>"
            style="width:100%;"
            placeholder="https://maps.google.com/..."
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
    <?php
    $linked_post_id = (int) get_post_meta((int) $post->ID, '_charla_post_id', true);
    $linked_post = $linked_post_id > 0 ? get_post($linked_post_id) : null;
    $linked_post_valid = $linked_post && 'post' === $linked_post->post_type && 'trash' !== $linked_post->post_status;
    $sync_post_enabled = flacso_charlas_abiertas_should_sync_post((int) $post->ID);
    $linked_evento_id = flacso_charlas_abiertas_get_linked_evento_id((int) $post->ID);
    $sync_evento_enabled = flacso_charlas_abiertas_should_sync_evento((int) $post->ID);
    ?>
    <hr>
    <p><strong>Integración con Posts</strong></p>
    <p>
        <label>
            <input type="checkbox" name="flacso_charla_sync_post" value="1" <?php checked($sync_post_enabled); ?>>
            Crear automáticamente un post vinculado al guardar esta charla si todavía no existe.
        </label>
    </p>
    <p style="margin:8px 0 0;color:#646970;">
        El post asociado es opcional.
    </p>
    <?php if ($linked_post_valid) : ?>
        <p style="margin:8px 0 0;">
            Post vinculado:
            <a href="<?php echo esc_url(get_edit_post_link($linked_post_id)); ?>">#<?php echo esc_html((string) $linked_post_id); ?> <?php echo esc_html(get_the_title($linked_post_id)); ?></a>
            ·
            <a href="<?php echo esc_url(get_permalink($linked_post_id)); ?>" target="_blank" rel="noopener noreferrer">Ver post</a>
        </p>
        <p style="margin:8px 0 0;color:#646970;">
            Si este post ya existe, no se sobrescribe al guardar cambios en la charla.
        </p>
    <?php elseif ($sync_post_enabled) : ?>
        <p style="margin:8px 0 0;color:#646970;">
            Todavía no hay un post vinculado. Se creará al guardar con la configuración actual de la charla.
        </p>
    <?php else : ?>
        <p style="margin:8px 0 0;color:#646970;">
            Esta charla puede guardarse sin post asociado.
        </p>
    <?php endif; ?>
    <hr>
    <p><strong>Formulario de inscripción</strong></p>
    <p>
        <label for="flacso_charla_form_variant"><strong>Versión del formulario</strong></label><br>
        <select id="flacso_charla_form_variant" name="flacso_charla_form_variant" style="width:100%;max-width:420px;">
            <option value="estandar" <?php selected($form_variant, 'estandar'); ?>>
                Estándar: nombre, apellido y profesión
            </option>
            <option value="nombre_apellido" <?php selected($form_variant, 'nombre_apellido'); ?>>
                Alternativa: nombre y apellido en un solo campo
            </option>
        </select>
    </p>
    <p style="margin:8px 0 0;color:#646970;">
        Esta preferencia se usa en el bloque de la charla y también en cualquier post vinculado nuevo que se cree desde aquí.
    </p>
    <hr>
    <p><strong>Integración con Eventos</strong></p>
    <p>
        <label>
            <input type="checkbox" name="flacso_charla_sync_evento" value="1" <?php checked($sync_evento_enabled); ?>>
            Crear o actualizar automáticamente un evento vinculado al guardar esta charla.
        </label>
    </p>
    <?php if ($linked_evento_id > 0) : ?>
        <p style="margin:8px 0 0;">
            Evento vinculado:
            <a href="<?php echo esc_url(get_edit_post_link($linked_evento_id)); ?>">#<?php echo esc_html((string) $linked_evento_id); ?> <?php echo esc_html(get_the_title($linked_evento_id)); ?></a>
            ·
            <a href="<?php echo esc_url(get_permalink($linked_evento_id)); ?>" target="_blank" rel="noopener noreferrer">Ver evento</a>
        </p>
    <?php else : ?>
        <p style="margin:8px 0 0;color:#646970;">
            Todavía no hay un evento vinculado a esta charla.
        </p>
    <?php endif; ?>
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

function flacso_charlas_abiertas_should_sync_post($charla_id) {
    $charla_id = absint($charla_id);
    if ($charla_id <= 0) {
        return false;
    }

    if (metadata_exists('post', $charla_id, '_charla_sync_post')) {
        return !empty(get_post_meta($charla_id, '_charla_sync_post', true));
    }

    $linked_post_id = (int) get_post_meta($charla_id, '_charla_post_id', true);
    return $linked_post_id > 0 && get_post_type($linked_post_id) === 'post' && get_post_status($linked_post_id) !== 'trash';
}

function flacso_charlas_abiertas_should_sync_evento($charla_id) {
    $charla_id = absint($charla_id);
    if ($charla_id <= 0) {
        return false;
    }

    if (metadata_exists('post', $charla_id, '_charla_sync_evento')) {
        return !empty(get_post_meta($charla_id, '_charla_sync_evento', true));
    }

    return flacso_charlas_abiertas_get_linked_evento_id($charla_id) > 0;
}

function flacso_charlas_abiertas_get_linked_evento_id($charla_id) {
    $charla_id = absint($charla_id);
    if ($charla_id <= 0) {
        return 0;
    }

    $linked_evento_id = absint(get_post_meta($charla_id, '_charla_evento_id', true));
    if ($linked_evento_id > 0) {
        $linked_event = get_post($linked_evento_id);
        if ($linked_event && 'evento' === $linked_event->post_type && 'trash' !== $linked_event->post_status) {
            return $linked_evento_id;
        }
        delete_post_meta($charla_id, '_charla_evento_id');
    }

    if (!post_type_exists('evento')) {
        return 0;
    }

    $existing = get_posts([
        'post_type'      => 'evento',
        'post_status'    => ['publish', 'future', 'draft', 'pending', 'private'],
        'posts_per_page' => 1,
        'fields'         => 'ids',
        'meta_query'     => [
            [
                'key'   => '_evento_charla_abierta_id',
                'value' => (string) $charla_id,
            ],
        ],
    ]);

    if (!empty($existing)) {
        $linked_evento_id = (int) $existing[0];
        update_post_meta($charla_id, '_charla_evento_id', $linked_evento_id);
        return $linked_evento_id;
    }

    return 0;
}

function flacso_charlas_abiertas_sync_evento_from_charla($charla_id, $force_create = false) {
    $charla_id = absint($charla_id);
    if ($charla_id <= 0) {
        return new WP_Error('charla_invalid_id', 'ID de charla inválido.');
    }

    $charla = get_post($charla_id);
    if (!$charla || 'charla_abierta' !== $charla->post_type) {
        return new WP_Error('charla_not_found', 'La charla no existe.');
    }

    if (!post_type_exists('evento')) {
        return new WP_Error('evento_cpt_missing', 'El CPT evento no está disponible.');
    }

    $titulo = trim((string) get_the_title($charla_id));
    if ('' === $titulo) {
        return new WP_Error('charla_missing_title', 'La charla no tiene título.');
    }

    $inicio_raw = trim((string) get_post_meta($charla_id, '_charla_inicio', true));
    if ('' === $inicio_raw) {
        return new WP_Error('charla_missing_start', 'La charla no tiene fecha de inicio.');
    }

    try {
        $inicio_dt = new DateTimeImmutable($inicio_raw);
    } catch (Exception $e) {
        return new WP_Error('charla_invalid_start', 'La fecha de inicio de la charla es inválida.');
    }

    $duracion_minutos = absint(get_post_meta($charla_id, '_charla_duracion_minutos', true));
    $fin_dt = $duracion_minutos > 0
        ? $inicio_dt->modify('+' . $duracion_minutos . ' minutes')
        : $inicio_dt;

    $allowed_statuses = ['publish', 'future', 'draft', 'pending', 'private'];
    $event_status = in_array((string) $charla->post_status, $allowed_statuses, true)
        ? (string) $charla->post_status
        : 'draft';

    $evento_id = $force_create ? 0 : flacso_charlas_abiertas_get_linked_evento_id($charla_id);
    $event_data = [
        'post_type'    => 'evento',
        'post_status'  => $event_status,
        'post_title'   => $titulo,
        'post_content' => (string) get_post_meta($charla_id, '_charla_descripcion', true),
    ];

    if ($evento_id > 0) {
        $event_data['ID'] = $evento_id;
        $updated_id = wp_update_post($event_data, true);
        if (is_wp_error($updated_id)) {
            return $updated_id;
        }
        $evento_id = (int) $updated_id;
    } else {
        $created_id = wp_insert_post($event_data, true);
        if (is_wp_error($created_id)) {
            return $created_id;
        }
        $evento_id = (int) $created_id;
    }

    update_post_meta($evento_id, 'evento_inicio_fecha', $inicio_dt->format('Y-m-d'));
    update_post_meta($evento_id, 'evento_inicio_hora', $inicio_dt->format('H:i'));
    update_post_meta($evento_id, 'evento_fin_fecha', $fin_dt->format('Y-m-d'));
    update_post_meta($evento_id, 'evento_fin_hora', $fin_dt->format('H:i'));
    $post_asociado_id = (int) get_post_meta($charla_id, '_charla_post_id', true);
    if ($post_asociado_id <= 0 || get_post_status($post_asociado_id) !== 'publish') {
        $post_asociado_id = $charla_id;
    }
    update_post_meta($evento_id, 'evento_post_asociado', $post_asociado_id);
    update_post_meta($evento_id, 'evento_display_title', $titulo);
    update_post_meta($evento_id, '_evento_charla_abierta_id', $charla_id);

    $charla_thumbnail_id = get_post_thumbnail_id($charla_id);
    if ($charla_thumbnail_id > 0 && function_exists('set_post_thumbnail')) {
        set_post_thumbnail($evento_id, $charla_thumbnail_id);
    }

    $ocultar_evento = get_post_meta($charla_id, '_charla_ocultar_evento', true);
    update_post_meta($evento_id, '_charla_ocultar_evento', !empty($ocultar_evento) ? '1' : '0');

    update_post_meta($charla_id, '_charla_evento_id', $evento_id);

    return $evento_id;
}

function flacso_charlas_abiertas_sync_post_from_charla($charla_id) {
    $charla_id = absint($charla_id);
    if ($charla_id <= 0) {
        return 0;
    }

    if (!flacso_charlas_abiertas_should_sync_post($charla_id)) {
        return (int) get_post_meta($charla_id, '_charla_post_id', true);
    }

    $charla = get_post($charla_id);
    if (!$charla || 'charla_abierta' !== $charla->post_type) {
        return 0;
    }

    $titulo = trim((string) get_the_title($charla_id));
    if ('' === $titulo) {
        return 0;
    }

    $post_id = (int) get_post_meta($charla_id, '_charla_post_id', true);
    $post_exists = $post_id > 0 && get_post_type($post_id) === 'post' && get_post_status($post_id) !== 'trash';
    if ($post_exists) {
        update_post_meta($post_id, '_post_charla_id', $charla_id);
    } else {
        $descripcion = (string) get_post_meta($charla_id, '_charla_descripcion', true);
        $form_variant = flacso_charlas_abiertas_normalize_form_variant(
            get_post_meta($charla_id, '_charla_form_variant', true)
        );
        $block_attributes = ['eventoId' => $charla_id];
        if ('estandar' !== $form_variant) {
            $block_attributes['variant'] = $form_variant;
        }

        $post_content = $descripcion . "\n\n<!-- wp:flacso-uy/charlas-abiertas-formulario " . wp_json_encode($block_attributes) . " /-->";
        $insert_data = [
            'post_type'    => 'post',
            'post_title'   => $titulo,
            'post_content' => $post_content,
            'post_status'  => $charla->post_status,
        ];
        $inserted_id = wp_insert_post($insert_data);
        if (!is_wp_error($inserted_id) && $inserted_id > 0) {
            $post_id = (int) $inserted_id;
            update_post_meta($charla_id, '_charla_post_id', $post_id);
            update_post_meta($post_id, '_post_charla_id', $charla_id);
        }
    }

    if ($post_id > 0) {
        $ocultar_post = get_post_meta($charla_id, '_charla_ocultar_post', true);
        update_post_meta($post_id, '_charla_ocultar_post', !empty($ocultar_post) ? '1' : '0');

        $charla_thumbnail_id = get_post_thumbnail_id($charla_id);
        if (!$post_exists && $charla_thumbnail_id > 0 && function_exists('set_post_thumbnail')) {
            set_post_thumbnail($post_id, $charla_thumbnail_id);
        }
    }

    return $post_id;
}

function flacso_charlas_abiertas_update_linked_post_visibility($charla_id) {
    $charla_id = absint($charla_id);
    if ($charla_id <= 0) {
        return 0;
    }

    $post_id = (int) get_post_meta($charla_id, '_charla_post_id', true);
    if ($post_id > 0 && get_post_type($post_id) === 'post' && get_post_status($post_id) !== 'trash') {
        $ocultar_post = get_post_meta($charla_id, '_charla_ocultar_post', true);
        update_post_meta($post_id, '_charla_ocultar_post', !empty($ocultar_post) ? '1' : '0');
        return $post_id;
    }

    return 0;
}

function flacso_charlas_abiertas_update_linked_event_visibility($charla_id) {
    $charla_id = absint($charla_id);
    if ($charla_id <= 0) {
        return 0;
    }

    $evento_id = flacso_charlas_abiertas_get_linked_evento_id($charla_id);
    if ($evento_id > 0) {
        $ocultar_evento = get_post_meta($charla_id, '_charla_ocultar_evento', true);
        update_post_meta($evento_id, '_charla_ocultar_evento', !empty($ocultar_evento) ? '1' : '0');
        return $evento_id;
    }

    return 0;
}

add_action('rest_after_insert_charla_abierta', 'flacso_charlas_abiertas_sync_evento_on_rest_save', 10, 3);
function flacso_charlas_abiertas_sync_evento_on_rest_save($post, $request, $creating) {
    if (!($post instanceof WP_Post) || 'charla_abierta' !== $post->post_type || 'publish' !== $post->post_status) {
        return;
    }

    if (flacso_charlas_abiertas_should_sync_post($post->ID)) {
        flacso_charlas_abiertas_sync_post_from_charla($post->ID);
    }

    if (flacso_charlas_abiertas_should_sync_evento($post->ID)) {
        $sync_result = flacso_charlas_abiertas_sync_evento_from_charla($post->ID);
        if (is_wp_error($sync_result) && defined('WP_DEBUG') && WP_DEBUG) {
            error_log('[FLACSO Charlas REST] No se pudo sincronizar evento para charla ' . $post->ID . ': ' . $sync_result->get_error_message());
        }
    }

    flacso_charlas_abiertas_update_linked_post_visibility($post->ID);
    flacso_charlas_abiertas_update_linked_event_visibility($post->ID);
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

    if (isset($_POST['flacso_charla_lugar_nombre'])) {
        update_post_meta($post_id, '_charla_lugar_nombre', sanitize_text_field(wp_unslash($_POST['flacso_charla_lugar_nombre'])));
    }

    if (isset($_POST['flacso_charla_google_maps_url'])) {
        update_post_meta($post_id, '_charla_google_maps_url', esc_url_raw(wp_unslash($_POST['flacso_charla_google_maps_url'])));
    }

    if (isset($_POST['flacso_charla_descripcion'])) {
        update_post_meta($post_id, '_charla_descripcion', wp_kses_post(wp_unslash($_POST['flacso_charla_descripcion'])));
    }

    if (isset($_POST['flacso_charla_form_variant'])) {
        update_post_meta(
            $post_id,
            '_charla_form_variant',
            flacso_charlas_abiertas_normalize_form_variant(
                wp_unslash($_POST['flacso_charla_form_variant'])
            )
        );
    }

    $sync_post_enabled = isset($_POST['flacso_charla_sync_post']) && '1' === sanitize_text_field(wp_unslash($_POST['flacso_charla_sync_post']));
    update_post_meta($post_id, '_charla_sync_post', $sync_post_enabled ? 1 : 0);

    if ($sync_post_enabled) {
        flacso_charlas_abiertas_sync_post_from_charla($post_id);
    }

    $sync_evento_enabled = isset($_POST['flacso_charla_sync_evento']) && '1' === sanitize_text_field(wp_unslash($_POST['flacso_charla_sync_evento']));
    update_post_meta($post_id, '_charla_sync_evento', $sync_evento_enabled ? 1 : 0);

    $ocultar_post_enabled = isset($_POST['flacso_charla_ocultar_post']) && '1' === sanitize_text_field(wp_unslash($_POST['flacso_charla_ocultar_post']));
    update_post_meta($post_id, '_charla_ocultar_post', $ocultar_post_enabled ? 1 : 0);

    $ocultar_evento_enabled = isset($_POST['flacso_charla_ocultar_evento']) && '1' === sanitize_text_field(wp_unslash($_POST['flacso_charla_ocultar_evento']));
    update_post_meta($post_id, '_charla_ocultar_evento', $ocultar_evento_enabled ? 1 : 0);

    flacso_charlas_abiertas_update_linked_post_visibility($post_id);
    flacso_charlas_abiertas_update_linked_event_visibility($post_id);

    if ($sync_evento_enabled) {
        $sync_result = flacso_charlas_abiertas_sync_evento_from_charla($post_id);
        if (is_wp_error($sync_result) && defined('WP_DEBUG') && WP_DEBUG) {
            error_log('[FLACSO Charlas] No se pudo sincronizar evento para charla ' . $post_id . ': ' . $sync_result->get_error_message());
        }
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
        $lugar_nombre = get_post_meta($post_id, '_charla_lugar_nombre', true);
        $zoom = get_post_meta($post_id, '_charla_zoom_join_url', true);
        $direccion = get_post_meta($post_id, '_charla_direccion', true);
        $google_maps_url = get_post_meta($post_id, '_charla_google_maps_url', true);
        $direccion_parts = [];
        if ($lugar_nombre) {
            $direccion_parts[] = esc_html($lugar_nombre);
        }
        if ($direccion) {
            $direccion_parts[] = esc_html($direccion);
        }
        $direccion_html = !empty($direccion_parts)
            ? implode('<br>', $direccion_parts)
            : '<span style="color:#646970;">Sin dirección</span>';
        if ($google_maps_url) {
            $direccion_html .= '<br><a href="' . esc_url($google_maps_url) . '" target="_blank" rel="noopener noreferrer">Ver Google Maps</a>';
        }

        if ('presencial' === $modalidad) {
            echo wp_kses_post($direccion_html);
            return;
        }

        if ('hibrida' === $modalidad) {
            $parts = [];
            $parts[] = $direccion_html;
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

    // Orden por defecto en el admin: las charlas creadas/actualizadas más recientes primero.
    // Evitamos forzar meta_key acá porque excluye charlas sin _charla_inicio
    // y además puede mandar las recién creadas a páginas posteriores del listado.
    if (empty($orderby)) {
        $query->set('orderby', 'date');
        $query->set('order', 'DESC');
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
        'lugar_nombre' => (string) get_post_meta($post->ID, '_charla_lugar_nombre', true),
        'direccion' => (string) get_post_meta($post->ID, '_charla_direccion', true),
        'google_maps_url' => (string) get_post_meta($post->ID, '_charla_google_maps_url', true),
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
    if ((!empty($item['lugar_nombre']) || !empty($item['direccion'])) && in_array((string) $item['modalidad'], ['presencial', 'hibrida'], true)) {
        $location_parts = [];
        if (!empty($item['lugar_nombre'])) {
            $location_parts[] = (string) $item['lugar_nombre'];
        }
        if (!empty($item['direccion'])) {
            $location_parts[] = (string) $item['direccion'];
        }
        echo '<p class="charla-meta">Lugar: ' . esc_html(implode(' - ', $location_parts)) . '</p>';
    }

    $links = [];
    if (!empty($item['google_maps_url'])) {
        $links[] = '<a href="' . esc_url((string) $item['google_maps_url']) . '" target="_blank" rel="noopener noreferrer">Google Maps</a>';
    }
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

add_action('pre_get_posts', 'flacso_charlas_abiertas_exclude_hidden_posts_and_events');
function flacso_charlas_abiertas_exclude_hidden_posts_and_events($query) {
    if (is_admin() || !$query->is_main_query()) {
        return;
    }

    $post_types = $query->get('post_type');
    if (empty($post_types)) {
        $post_types = ['post'];
    } elseif (is_string($post_types)) {
        $post_types = [$post_types];
    }

    $has_post = in_array('post', $post_types, true);
    $has_evento = in_array('evento', $post_types, true);

    if ($has_post || $has_evento) {
        $meta_query = $query->get('meta_query');
        if (!is_array($meta_query)) {
            $meta_query = [];
        }

        $meta_query[] = [
            'relation' => 'AND',
            [
                'relation' => 'OR',
                ['key' => '_charla_ocultar_post', 'compare' => 'NOT EXISTS'],
                ['key' => '_charla_ocultar_post', 'value' => '1', 'compare' => '!=']
            ],
            [
                'relation' => 'OR',
                ['key' => '_charla_ocultar_evento', 'compare' => 'NOT EXISTS'],
                ['key' => '_charla_ocultar_evento', 'value' => '1', 'compare' => '!=']
            ]
        ];

        $query->set('meta_query', $meta_query);
    }
}
