<?php

if (!defined('ABSPATH')) {
    exit;
}

add_action('init', 'flacso_charlas_abiertas_register_block');
function flacso_charlas_abiertas_register_block() {
    wp_register_script(
        'flacso-charlas-abiertas-block-editor',
        FLACSO_CHARLAS_ABIERTAS_URL . 'assets/js/block-editor.js',
        ['wp-blocks', 'wp-element', 'wp-components', 'wp-i18n', 'wp-block-editor', 'wp-api-fetch'],
        FLACSO_CHARLAS_ABIERTAS_VERSION,
        true
    );

    register_block_type('flacso-uy/charlas-abiertas-formulario', [
        'api_version' => 2,
        'editor_script' => 'flacso-charlas-abiertas-block-editor',
        'render_callback' => 'flacso_charlas_abiertas_render_block',
        'attributes' => [
            'eventoId' => [
                'type' => 'number',
                'default' => 0,
            ],
        ],
    ]);
}

function flacso_charlas_abiertas_render_block($attributes) {
    static $assets_enqueued = false;

    $evento_id = isset($attributes['eventoId']) ? absint($attributes['eventoId']) : 0;
    if (!$evento_id) {
        return '<p>Selecciona una charla para mostrar el formulario.</p>';
    }

    $evento = get_post($evento_id);
    if (
        !$evento ||
        'charla_abierta' !== $evento->post_type ||
        in_array($evento->post_status, ['auto-draft', 'trash'], true)
    ) {
        return '<p>La charla seleccionada no está disponible.</p>';
    }

    $inicio = get_post_meta($evento_id, '_charla_inicio', true);
    $modalidad = get_post_meta($evento_id, '_charla_modalidad', true);
    $zoom_join_url = get_post_meta($evento_id, '_charla_zoom_join_url', true);
    $youtube_transmision_url = get_post_meta($evento_id, '_charla_youtube_transmision_url', true);
    $duracion_minutos_raw = get_post_meta($evento_id, '_charla_duracion_minutos', true);
    $duracion_minutos = null;
    if ('' !== trim((string) $duracion_minutos_raw) && is_numeric($duracion_minutos_raw)) {
        $duracion_minutos = max(0, (int) $duracion_minutos_raw);
    }
    $direccion = get_post_meta($evento_id, '_charla_direccion', true);
    $descripcion = get_post_meta($evento_id, '_charla_descripcion', true);
    $descripcion_b64 = base64_encode((string) $descripcion);
    if (!$modalidad) {
        $modalidad = 'virtual';
    }

    if (!$assets_enqueued) {
        flacso_charlas_abiertas_enqueue_front_assets();
        $assets_enqueued = true;
    }

    $wrapper_id = 'flacso-charla-form-' . wp_unique_id();
    $correo_input_id = $wrapper_id . '-correo';
    $correo_feedback_id = $wrapper_id . '-correo-feedback';
    $celular_input_id = $wrapper_id . '-celular';
    $celular_feedback_id = $wrapper_id . '-celular-feedback';
    $modalidad_select_id = $wrapper_id . '-modalidad-asistencia';
    $modalidad_feedback_id = $wrapper_id . '-modalidad-asistencia-feedback';
    $is_logged_in = is_user_logged_in() ? 'true' : 'false';
    $host_post_id = get_the_ID();
    if (!$host_post_id) {
        $host_post_id = get_queried_object_id();
    }
    $host_featured_image = $host_post_id ? get_the_post_thumbnail_url($host_post_id, 'full') : '';

    ob_start();
    ?>
    <div
        id="<?php echo esc_attr($wrapper_id); ?>"
        class="flacso-charla-form-wrapper"
        data-evento-id="<?php echo esc_attr((string) $evento_id); ?>"
        data-evento-titulo="<?php echo esc_attr(get_the_title($evento_id)); ?>"
        data-evento-inicio="<?php echo esc_attr((string) $inicio); ?>"
        data-evento-modalidad="<?php echo esc_attr((string) $modalidad); ?>"
        data-evento-zoom-join-url="<?php echo esc_url($zoom_join_url ?: ''); ?>"
        data-evento-youtube-transmision-url="<?php echo esc_url($youtube_transmision_url ?: ''); ?>"
        data-evento-duracion-minutos="<?php echo esc_attr(null === $duracion_minutos ? '' : (string) $duracion_minutos); ?>"
        data-evento-direccion="<?php echo esc_attr((string) $direccion); ?>"
        data-evento-descripcion-b64="<?php echo esc_attr($descripcion_b64); ?>"
        data-wp-user-logged-in="<?php echo esc_attr($is_logged_in); ?>"
        data-host-post-id="<?php echo esc_attr((string) absint($host_post_id)); ?>"
        data-host-post-featured-image="<?php echo esc_url($host_featured_image ?: ''); ?>"
        data-endpoint="<?php echo esc_url(rest_url('flacso-charlas/v1/inscripcion')); ?>"
    >
        <form class="flacso-charla-form" novalidate>
            <h3>Inscripción a <?php echo esc_html(get_the_title($evento_id)); ?></h3>

            <p>
                <label>Nombre *</label>
                <input type="text" name="nombre" required />
            </p>

            <p>
                <label>Apellido *</label>
                <input type="text" name="apellido" required />
            </p>

            <p>
                <label>Correo *</label>
                <input
                    type="email"
                    id="<?php echo esc_attr($correo_input_id); ?>"
                    name="correo"
                    class="flacso-correo"
                    aria-describedby="<?php echo esc_attr($correo_feedback_id); ?>"
                    required
                />
                <div id="<?php echo esc_attr($correo_feedback_id); ?>" class="invalid-feedback flacso-correo-feedback" aria-live="polite"></div>
            </p>

            <p>
                <label>País de residencia</label>
                <input type="text" name="pais_residencia" class="flacso-pais-residencia" />
            </p>

            <p>
                <label>Profesión</label>
                <input type="text" name="profesion" />
            </p>

            <p>
                <label>Institución</label>
                <input type="text" name="institucion" />
            </p>

            <p>
                <label>Celular</label>
                <div class="input-group has-validation flacso-celular-group">
                    <input
                        type="tel"
                        id="<?php echo esc_attr($celular_input_id); ?>"
                        name="celular"
                        class="flacso-celular"
                        aria-describedby="<?php echo esc_attr($celular_feedback_id); ?>"
                    />
                </div>
                <div id="<?php echo esc_attr($celular_feedback_id); ?>" class="invalid-feedback flacso-celular-feedback" aria-live="polite"></div>
            </p>

            <p>
                <label>Modalidad de asistencia *</label>
                <select
                    id="<?php echo esc_attr($modalidad_select_id); ?>"
                    name="modalidad_asistencia"
                    class="flacso-modalidad-asistencia"
                    aria-describedby="<?php echo esc_attr($modalidad_feedback_id); ?>"
                    required
                >
                    <option value="">Seleccionar</option>
                    <option value="virtual">Virtual</option>
                    <option value="presencial">Presencial</option>
                </select>
                <small class="flacso-modalidad-locknote" hidden>Modalidad fija según la charla seleccionada.</small>
                <div id="<?php echo esc_attr($modalidad_feedback_id); ?>" class="invalid-feedback flacso-modalidad-feedback" aria-live="polite"></div>
            </p>

            <p>
                <button type="submit" class="button">Enviar inscripción</button>
            </p>
        </form>
        <div class="flacso-charla-form-result" aria-live="polite"></div>
    </div>
    <?php
    return ob_get_clean();
}
