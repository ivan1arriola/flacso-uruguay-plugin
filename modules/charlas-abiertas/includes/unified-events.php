<?php

if (!defined('ABSPATH')) {
    exit;
}

function flacso_charlas_abiertas_normalize_reunion_platform($value) {
    $platform = sanitize_key((string) $value);
    return in_array($platform, ['zoom', 'meet'], true) ? $platform : 'zoom';
}

function flacso_charlas_abiertas_normalize_form_variant($value) {
    $variant = sanitize_key((string) $value);
    return in_array($variant, ['nombre_apellido', 'nombre_apellido_sin_telefono'], true)
        ? $variant
        : 'estandar';
}

function flacso_charlas_abiertas_parse_duracion_hhmm_a_minutos($value) {
    if (!is_scalar($value) || !preg_match('/^(\d{1,3}):([0-5]\d)$/', trim((string) $value), $matches)) {
        return null;
    }

    return ((int) $matches[1] * 60) + (int) $matches[2];
}

function flacso_charlas_abiertas_format_duracion_hhmm_desde_minutos($value) {
    if (!is_numeric($value)) {
        return '';
    }

    $minutes = max(0, (int) $value);
    return sprintf('%02d:%02d', (int) floor($minutes / 60), $minutes % 60);
}

function flacso_charlas_abiertas_get_charla_descripcion_html($post) {
    $post = get_post($post);
    if (!$post) {
        return '';
    }

    $description = (string) get_post_meta($post->ID, '_charla_descripcion', true);
    return trim($description) !== '' ? $description : (string) $post->post_content;
}

add_action('init', 'flacso_eventos_register_form_meta', 20);
function flacso_eventos_register_form_meta() {
    $definitions = [
        'evento_inicio_fecha' => ['type' => 'string', 'sanitize_callback' => 'sanitize_text_field'],
        'evento_inicio_hora' => ['type' => 'string', 'sanitize_callback' => 'sanitize_text_field'],
        'evento_fin_fecha' => ['type' => 'string', 'sanitize_callback' => 'sanitize_text_field'],
        'evento_fin_hora' => ['type' => 'string', 'sanitize_callback' => 'sanitize_text_field'],
        'evento_display_title' => ['type' => 'string', 'sanitize_callback' => 'sanitize_text_field'],
        '_charla_inicio' => ['type' => 'string', 'sanitize_callback' => 'sanitize_text_field'],
        '_charla_modalidad' => ['type' => 'string', 'sanitize_callback' => 'sanitize_key'],
        '_charla_plataforma_reunion' => ['type' => 'string', 'sanitize_callback' => 'flacso_charlas_abiertas_normalize_reunion_platform'],
        '_charla_reunion_join_url' => ['type' => 'string', 'sanitize_callback' => 'esc_url_raw'],
        '_charla_zoom_join_url' => ['type' => 'string', 'sanitize_callback' => 'esc_url_raw'],
        '_charla_youtube_transmision_url' => ['type' => 'string', 'sanitize_callback' => 'esc_url_raw'],
        '_charla_duracion_minutos' => ['type' => 'integer', 'sanitize_callback' => 'absint'],
        '_charla_direccion' => ['type' => 'string', 'sanitize_callback' => 'sanitize_text_field'],
        '_charla_lugar_nombre' => ['type' => 'string', 'sanitize_callback' => 'sanitize_text_field'],
        '_charla_google_maps_url' => ['type' => 'string', 'sanitize_callback' => 'esc_url_raw'],
        '_charla_descripcion' => ['type' => 'string', 'sanitize_callback' => 'wp_kses_post'],
        '_charla_form_variant' => ['type' => 'string', 'sanitize_callback' => 'flacso_charlas_abiertas_normalize_form_variant'],
        '_evento_formulario_habilitado' => ['type' => 'boolean', 'sanitize_callback' => static function ($value) {
            return !empty($value);
        }],
        '_evento_mostrar_web' => ['type' => 'boolean', 'sanitize_callback' => static function ($value) {
            return !empty($value);
        }],
    ];

    foreach ($definitions as $key => $definition) {
        register_post_meta('evento', $key, array_merge([
            'single' => true,
            'show_in_rest' => true,
            'auth_callback' => static function () {
                return current_user_can('edit_posts');
            },
        ], $definition));
    }
}

add_action('add_meta_boxes_evento', 'flacso_eventos_add_registration_meta_box');
function flacso_eventos_add_registration_meta_box() {
    add_meta_box(
        'flacso_evento_registration',
        __('Publicación e inscripción', 'flacso-uruguay'),
        'flacso_eventos_render_registration_meta_box',
        'evento',
        'normal',
        'high'
    );
}

function flacso_eventos_render_registration_meta_box($post) {
    wp_nonce_field('flacso_evento_registration_save', 'flacso_evento_registration_nonce');

    $enabled = !metadata_exists('post', $post->ID, '_evento_formulario_habilitado')
        || !empty(get_post_meta($post->ID, '_evento_formulario_habilitado', true));
    $show_web = !empty(get_post_meta($post->ID, '_evento_mostrar_web', true));
    $variant = flacso_charlas_abiertas_normalize_form_variant(get_post_meta($post->ID, '_charla_form_variant', true));
    $modalidad = (string) get_post_meta($post->ID, '_charla_modalidad', true) ?: 'virtual';
    $duration = flacso_charlas_abiertas_format_duracion_hhmm_desde_minutos(
        get_post_meta($post->ID, '_charla_duracion_minutos', true)
    );
    $platform = flacso_charlas_abiertas_normalize_reunion_platform(get_post_meta($post->ID, '_charla_plataforma_reunion', true));
    $meeting_url = (string) get_post_meta($post->ID, '_charla_reunion_join_url', true);
    if ('' === $meeting_url) {
        $meeting_url = (string) get_post_meta($post->ID, '_charla_zoom_join_url', true);
    }
    ?>
    <p>
        <label><input type="checkbox" name="evento_mostrar_web" value="1" <?php checked($show_web); ?>>
            <strong><?php esc_html_e('Mostrar este evento en la sección Eventos de la web', 'flacso-uruguay'); ?></strong>
        </label>
    </p>
    <p class="description"><?php esc_html_e('Los eventos no se publican como Novedades ni generan un post asociado.', 'flacso-uruguay'); ?></p>
    <hr>
    <p>
        <label><input type="checkbox" name="evento_formulario_habilitado" value="1" <?php checked($enabled); ?>>
            <strong><?php esc_html_e('Mostrar formulario de inscripción en la página del evento', 'flacso-uruguay'); ?></strong>
        </label>
    </p>
    <p>
        <label for="flacso_charla_form_variant"><strong><?php esc_html_e('Versión del formulario', 'flacso-uruguay'); ?></strong></label><br>
        <select id="flacso_charla_form_variant" name="flacso_charla_form_variant">
            <option value="estandar" <?php selected($variant, 'estandar'); ?>><?php esc_html_e('Estándar', 'flacso-uruguay'); ?></option>
            <option value="nombre_apellido" <?php selected($variant, 'nombre_apellido'); ?>><?php esc_html_e('Nombre y apellido', 'flacso-uruguay'); ?></option>
            <option value="nombre_apellido_sin_telefono" <?php selected($variant, 'nombre_apellido_sin_telefono'); ?>><?php esc_html_e('Nombre y apellido, sin teléfono', 'flacso-uruguay'); ?></option>
        </select>
    </p>
    <p>
        <label for="flacso_charla_modalidad"><strong><?php esc_html_e('Modalidad', 'flacso-uruguay'); ?></strong></label><br>
        <select id="flacso_charla_modalidad" name="flacso_charla_modalidad">
            <option value="virtual" <?php selected($modalidad, 'virtual'); ?>><?php esc_html_e('Virtual', 'flacso-uruguay'); ?></option>
            <option value="presencial" <?php selected($modalidad, 'presencial'); ?>><?php esc_html_e('Presencial', 'flacso-uruguay'); ?></option>
            <option value="hibrida" <?php selected($modalidad, 'hibrida'); ?>><?php esc_html_e('Híbrida', 'flacso-uruguay'); ?></option>
        </select>
    </p>
    <p><label><strong><?php esc_html_e('Duración (HH:MM)', 'flacso-uruguay'); ?></strong><br>
        <input type="text" name="flacso_charla_duracion_hhmm" value="<?php echo esc_attr($duration); ?>" pattern="[0-9]{1,3}:[0-5][0-9]" placeholder="01:30"></label>
    </p>
    <p><label><strong><?php esc_html_e('Plataforma online', 'flacso-uruguay'); ?></strong><br>
        <select name="flacso_charla_plataforma_reunion">
            <option value="zoom" <?php selected($platform, 'zoom'); ?>>Zoom</option>
            <option value="meet" <?php selected($platform, 'meet'); ?>>Google Meet</option>
        </select></label>
    </p>
    <p><label><strong><?php esc_html_e('URL de acceso online', 'flacso-uruguay'); ?></strong><br>
        <input class="widefat" type="url" name="flacso_charla_reunion_join_url" value="<?php echo esc_attr($meeting_url); ?>"></label>
    </p>
    <p><label><strong><?php esc_html_e('YouTube transmisión URL', 'flacso-uruguay'); ?></strong><br>
        <input class="widefat" type="url" name="flacso_charla_youtube_transmision_url" value="<?php echo esc_attr(get_post_meta($post->ID, '_charla_youtube_transmision_url', true)); ?>"></label>
    </p>
    <p><label><strong><?php esc_html_e('Lugar', 'flacso-uruguay'); ?></strong><br>
        <input class="widefat" type="text" name="flacso_charla_lugar_nombre" value="<?php echo esc_attr(get_post_meta($post->ID, '_charla_lugar_nombre', true)); ?>"></label>
    </p>
    <p><label><strong><?php esc_html_e('Dirección', 'flacso-uruguay'); ?></strong><br>
        <input class="widefat" type="text" name="flacso_charla_direccion" value="<?php echo esc_attr(get_post_meta($post->ID, '_charla_direccion', true)); ?>"></label>
    </p>
    <p><label><strong><?php esc_html_e('Google Maps URL', 'flacso-uruguay'); ?></strong><br>
        <input class="widefat" type="url" name="flacso_charla_google_maps_url" value="<?php echo esc_attr(get_post_meta($post->ID, '_charla_google_maps_url', true)); ?>"></label>
    </p>
    <?php
}

add_action('save_post_evento', 'flacso_eventos_save_registration_meta', 20, 2);
function flacso_eventos_save_registration_meta($post_id, $post) {
    if (
        !isset($_POST['flacso_evento_registration_nonce'])
        || !wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['flacso_evento_registration_nonce'])), 'flacso_evento_registration_save')
        || (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE)
        || !current_user_can('edit_post', $post_id)
    ) {
        return;
    }

    update_post_meta($post_id, '_evento_mostrar_web', isset($_POST['evento_mostrar_web']) ? 1 : 0);
    update_post_meta($post_id, '_evento_formulario_habilitado', isset($_POST['evento_formulario_habilitado']) ? 1 : 0);
    update_post_meta($post_id, '_charla_form_variant', flacso_charlas_abiertas_normalize_form_variant(wp_unslash($_POST['flacso_charla_form_variant'] ?? '')));

    $modalidad = sanitize_key(wp_unslash($_POST['flacso_charla_modalidad'] ?? 'virtual'));
    update_post_meta($post_id, '_charla_modalidad', in_array($modalidad, ['virtual', 'presencial', 'hibrida'], true) ? $modalidad : 'virtual');

    update_post_meta($post_id, '_charla_plataforma_reunion', flacso_charlas_abiertas_normalize_reunion_platform(wp_unslash($_POST['flacso_charla_plataforma_reunion'] ?? 'zoom')));
    $meeting_url = esc_url_raw(wp_unslash($_POST['flacso_charla_reunion_join_url'] ?? ($_POST['flacso_charla_zoom_join_url'] ?? '')));
    update_post_meta($post_id, '_charla_reunion_join_url', $meeting_url);
    update_post_meta($post_id, '_charla_zoom_join_url', $meeting_url);

    $fields = [
        'flacso_charla_youtube_transmision_url' => ['_charla_youtube_transmision_url', 'esc_url_raw'],
        'flacso_charla_lugar_nombre' => ['_charla_lugar_nombre', 'sanitize_text_field'],
        'flacso_charla_direccion' => ['_charla_direccion', 'sanitize_text_field'],
        'flacso_charla_google_maps_url' => ['_charla_google_maps_url', 'esc_url_raw'],
    ];
    foreach ($fields as $input => [$meta_key, $sanitizer]) {
        update_post_meta($post_id, $meta_key, $sanitizer(wp_unslash($_POST[$input] ?? '')));
    }

    $duration = flacso_charlas_abiertas_parse_duracion_hhmm_a_minutos(wp_unslash($_POST['flacso_charla_duracion_hhmm'] ?? ''));
    if ($duration === null) {
        delete_post_meta($post_id, '_charla_duracion_minutos');
    } else {
        update_post_meta($post_id, '_charla_duracion_minutos', $duration);
    }

    $date = (string) get_post_meta($post_id, 'evento_inicio_fecha', true);
    $time = (string) get_post_meta($post_id, 'evento_inicio_hora', true);
    if ($date !== '') {
        $dt = DateTimeImmutable::createFromFormat('Y-m-d H:i', $date . ' ' . ($time ?: '00:00'), wp_timezone());
        if ($dt instanceof DateTimeImmutable) {
            update_post_meta($post_id, '_charla_inicio', $dt->format('Y-m-d\TH:i:sP'));
        }
    }

    // El evento es ahora el contenido de detalle; se elimina el acoplamiento.
    delete_post_meta($post_id, 'evento_post_asociado');
}

add_filter('the_content', 'flacso_eventos_append_registration_form', 20);
function flacso_eventos_append_registration_form($content) {
    if (!is_singular('evento') || !in_the_loop() || !is_main_query()) {
        return $content;
    }

    $event_id = get_the_ID();
    $enabled = !metadata_exists('post', $event_id, '_evento_formulario_habilitado')
        || !empty(get_post_meta($event_id, '_evento_formulario_habilitado', true));
    if (!$enabled || !function_exists('flacso_charlas_abiertas_render_block')) {
        return $content;
    }

    return $content . flacso_charlas_abiertas_render_block(['eventoId' => $event_id]);
}

add_action('admin_init', 'flacso_eventos_migrate_legacy_charlas_once');
function flacso_eventos_migrate_legacy_charlas_once() {
    if (get_option('flacso_eventos_unified_migration_v1') || !current_user_can('manage_options')) {
        return;
    }

    $legacy_ids = get_posts([
        'post_type' => 'charla_abierta',
        'post_status' => ['publish', 'draft', 'private', 'pending', 'future'],
        'posts_per_page' => -1,
        'fields' => 'ids',
        'orderby' => 'ID',
        'order' => 'ASC',
    ]);

    $migrated = 0;

    $existing_event_ids = get_posts([
        'post_type' => 'evento',
        'post_status' => ['publish', 'draft', 'private', 'pending', 'future'],
        'posts_per_page' => -1,
        'fields' => 'ids',
    ]);
    foreach ($existing_event_ids as $existing_event_id) {
        $existing_event_id = absint($existing_event_id);
        if (!metadata_exists('post', $existing_event_id, '_evento_mostrar_web')) {
            update_post_meta(
                $existing_event_id,
                '_evento_mostrar_web',
                empty(get_post_meta($existing_event_id, '_charla_ocultar_evento', true)) ? 1 : 0
            );
        }

        if (!metadata_exists('post', $existing_event_id, '_charla_inicio')) {
            $date = (string) get_post_meta($existing_event_id, 'evento_inicio_fecha', true);
            $time = (string) get_post_meta($existing_event_id, 'evento_inicio_hora', true);
            if ($date !== '') {
                $dt = DateTimeImmutable::createFromFormat('Y-m-d H:i', $date . ' ' . ($time ?: '00:00'), wp_timezone());
                if ($dt instanceof DateTimeImmutable) {
                    update_post_meta($existing_event_id, '_charla_inicio', $dt->format('Y-m-d\TH:i:sP'));
                }
            }
        }
    }

    foreach ($legacy_ids as $legacy_id) {
        $legacy_id = absint($legacy_id);
        $event_id = absint(get_post_meta($legacy_id, '_charla_evento_id', true));
        if ($event_id <= 0 || get_post_type($event_id) !== 'evento') {
            $event_id = wp_insert_post([
                'post_type' => 'evento',
                'post_status' => get_post_status($legacy_id) ?: 'draft',
                'post_title' => get_the_title($legacy_id),
                'post_content' => flacso_charlas_abiertas_get_charla_descripcion_html($legacy_id),
                'post_excerpt' => get_post_field('post_excerpt', $legacy_id),
            ]);
        }

        if (is_wp_error($event_id) || $event_id <= 0) {
            continue;
        }

        $legacy_post = get_post($legacy_id);
        wp_update_post([
            'ID' => $event_id,
            'post_title' => $legacy_post ? $legacy_post->post_title : get_the_title($legacy_id),
            'post_content' => flacso_charlas_abiertas_get_charla_descripcion_html($legacy_id),
        ]);

        $meta_keys = [
            '_charla_inicio',
            '_charla_modalidad',
            '_charla_zoom_join_url',
            '_charla_youtube_transmision_url',
            '_charla_duracion_minutos',
            '_charla_direccion',
            '_charla_lugar_nombre',
            '_charla_google_maps_url',
            '_charla_descripcion',
            '_charla_form_variant',
        ];
        foreach ($meta_keys as $meta_key) {
            $value = get_post_meta($legacy_id, $meta_key, true);
            if ($value !== '') {
                update_post_meta($event_id, $meta_key, $value);
            }
        }

        $inicio = (string) get_post_meta($legacy_id, '_charla_inicio', true);
        if ($inicio !== '') {
            try {
                $start = new DateTimeImmutable($inicio);
                update_post_meta($event_id, 'evento_inicio_fecha', $start->format('Y-m-d'));
                update_post_meta($event_id, 'evento_inicio_hora', $start->format('H:i'));
                $duration = max(0, (int) get_post_meta($legacy_id, '_charla_duracion_minutos', true));
                $end = $start->modify('+' . $duration . ' minutes');
                update_post_meta($event_id, 'evento_fin_fecha', $end->format('Y-m-d'));
                update_post_meta($event_id, 'evento_fin_hora', $end->format('H:i'));
            } catch (Exception $e) {
                // Se conserva el ISO original para que el editor pueda corregirlo.
            }
        }

        update_post_meta($event_id, '_evento_formulario_habilitado', 1);
        update_post_meta($event_id, '_evento_mostrar_web', empty(get_post_meta($legacy_id, '_charla_ocultar_evento', true)) ? 1 : 0);
        update_post_meta($event_id, '_evento_legacy_charla_id', $legacy_id);
        update_post_meta($legacy_id, '_charla_evento_id', $event_id);
        delete_post_meta($event_id, 'evento_post_asociado');

        $thumbnail_id = get_post_thumbnail_id($legacy_id);
        if ($thumbnail_id > 0) {
            set_post_thumbnail($event_id, $thumbnail_id);
        }

        $legacy_post_id = absint(get_post_meta($legacy_id, '_charla_post_id', true));
        if ($legacy_post_id > 0 && get_post_type($legacy_post_id) === 'post') {
            update_post_meta($legacy_post_id, '_flacso_evento_legacy_post', $event_id);
            if (is_sticky($legacy_post_id)) {
                unstick_post($legacy_post_id);
            }
        }

        $migrated++;
    }

    update_option('flacso_eventos_unified_migration_v1', [
        'completed_at' => current_time('mysql'),
        'migrated' => $migrated,
    ], false);
}

add_action('pre_get_posts', 'flacso_eventos_exclude_legacy_posts_from_novedades', 20);
function flacso_eventos_exclude_legacy_posts_from_novedades($query) {
    if (!$query instanceof WP_Query || $query->get('category_name') !== 'novedades') {
        return;
    }

    $meta_query = $query->get('meta_query');
    $meta_query = is_array($meta_query) ? $meta_query : [];
    $meta_query[] = [
        'key' => '_flacso_evento_legacy_post',
        'compare' => 'NOT EXISTS',
    ];
    $query->set('meta_query', $meta_query);
}
