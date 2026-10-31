<?php

if (!defined('ABSPATH')) {
    exit;
}

function flacso_eventos_normalize_reunion_platform($value) {
    $platform = sanitize_key((string) $value);
    return in_array($platform, ['zoom', 'meet'], true) ? $platform : 'zoom';
}

function flacso_eventos_parse_duracion_hhmm_a_minutos($value) {
    if (!is_scalar($value) || !preg_match('/^(\d{1,3}):([0-5]\d)$/', trim((string) $value), $matches)) {
        return null;
    }

    return ((int) $matches[1] * 60) + (int) $matches[2];
}

function flacso_eventos_format_duracion_hhmm_desde_minutos($value) {
    if (!is_numeric($value)) {
        return '';
    }

    $minutes = max(0, (int) $value);
    return sprintf('%02d:%02d', (int) floor($minutes / 60), $minutes % 60);
}

function flacso_eventos_get_description_html($post) {
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
        '_charla_descripcion' => ['type' => 'string', 'sanitize_callback' => 'wp_kses_post'],
        '_evento_formulario_url' => ['type' => 'string', 'sanitize_callback' => 'esc_url_raw'],
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

    $show_web = !empty(get_post_meta($post->ID, '_evento_mostrar_web', true));
    $form_url = (string) get_post_meta($post->ID, '_evento_formulario_url', true);
    ?>
    <p>
        <label><input type="checkbox" name="evento_mostrar_web" value="1" <?php checked($show_web); ?>>
            <strong><?php esc_html_e('Mostrar este evento en la sección Eventos de la web', 'flacso-uruguay'); ?></strong>
        </label>
    </p>
    <p class="description"><?php esc_html_e('Los eventos no se publican como Novedades ni generan un post asociado.', 'flacso-uruguay'); ?></p>
    <hr>
    <p><label><strong><?php esc_html_e('URL del formulario de inscripción (Formularios FLACSO)', 'flacso-uruguay'); ?></strong><br>
        <input class="widefat" type="url" name="evento_formulario_url" value="<?php echo esc_attr($form_url); ?>" placeholder="https://formularios.flacso.edu.uy/f/..."></label>
    </p>
    <p class="description"><?php esc_html_e('Si se completa, la página del evento muestra el botón «Inscribirme». El formulario lo gestiona Formularios FLACSO.', 'flacso-uruguay'); ?></p>
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
    update_post_meta($post_id, '_evento_formulario_url', esc_url_raw(wp_unslash($_POST['evento_formulario_url'] ?? '')));

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
