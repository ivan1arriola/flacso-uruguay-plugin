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
    $lugar_nombre = get_post_meta($evento_id, '_charla_lugar_nombre', true);
    $direccion = get_post_meta($evento_id, '_charla_direccion', true);
    $google_maps_url = get_post_meta($evento_id, '_charla_google_maps_url', true);
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
    $telefono_input_id = $wrapper_id . '-telefono';
    $telefono_feedback_id = $wrapper_id . '-telefono-feedback';
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
        data-evento-lugar-nombre="<?php echo esc_attr((string) $lugar_nombre); ?>"
        data-evento-direccion="<?php echo esc_attr((string) $direccion); ?>"
        data-evento-google-maps-url="<?php echo esc_url($google_maps_url ?: ''); ?>"
        data-evento-descripcion-b64="<?php echo esc_attr($descripcion_b64); ?>"
        data-wp-user-logged-in="<?php echo esc_attr($is_logged_in); ?>"
        data-host-post-id="<?php echo esc_attr((string) absint($host_post_id)); ?>"
        data-host-post-featured-image="<?php echo esc_url($host_featured_image ?: ''); ?>"
        data-endpoint="<?php echo esc_url(rest_url('flacso-charlas/v1/inscripcion')); ?>"
    >
        <form class="flacso-charla-form" novalidate>
            <h3 class="flacso-form-title">Inscripción a <?php echo esc_html(get_the_title($evento_id)); ?></h3>

            <div class="flacso-form-grid">
                <div class="flacso-form-group">
                    <label for="<?php echo esc_attr($wrapper_id); ?>-nombre">Nombre *</label>
                    <input type="text" id="<?php echo esc_attr($wrapper_id); ?>-nombre" name="nombre" placeholder="Ej. Juan" required />
                </div>

                <div class="flacso-form-group">
                    <label for="<?php echo esc_attr($wrapper_id); ?>-apellido">Apellido *</label>
                    <input type="text" id="<?php echo esc_attr($wrapper_id); ?>-apellido" name="apellido" placeholder="Ej. Pérez" required />
                </div>

                <div class="flacso-form-group flacso-form-group-full">
                    <label for="<?php echo esc_attr($correo_input_id); ?>">Correo *</label>
                    <input
                        type="email"
                        id="<?php echo esc_attr($correo_input_id); ?>"
                        name="correo"
                        class="flacso-correo"
                        placeholder="correo@ejemplo.com"
                        aria-describedby="<?php echo esc_attr($correo_feedback_id); ?>"
                        required
                    />
                    <div id="<?php echo esc_attr($correo_feedback_id); ?>" class="invalid-feedback flacso-correo-feedback" aria-live="polite"></div>
                </div>

                <div class="flacso-form-group">
                    <label for="<?php echo esc_attr($wrapper_id); ?>-pais-residencia">País de residencia</label>
                    <input type="text" id="<?php echo esc_attr($wrapper_id); ?>-pais-residencia" name="pais_residencia" class="flacso-pais-residencia" placeholder="Ej. Uruguay" />
                </div>

                <div class="flacso-form-group">
                    <label for="<?php echo esc_attr($wrapper_id); ?>-profesion">Profesión</label>
                    <input type="text" id="<?php echo esc_attr($wrapper_id); ?>-profesion" name="profesion" placeholder="Ej. Docente, Estudiante" />
                </div>

                <div class="flacso-form-group">
                    <label for="<?php echo esc_attr($wrapper_id); ?>-institucion">Institución</label>
                    <input type="text" id="<?php echo esc_attr($wrapper_id); ?>-institucion" name="institucion" placeholder="Ej. Universidad, FLACSO" />
                </div>

                <div class="flacso-form-group">
                    <label for="<?php echo esc_attr($telefono_input_id); ?>">Número de teléfono</label>
                    <div class="input-group has-validation flacso-telefono-group">
                        <input
                            type="tel"
                            id="<?php echo esc_attr($telefono_input_id); ?>"
                            name="telefono"
                            class="flacso-telefono"
                            aria-describedby="<?php echo esc_attr($telefono_feedback_id); ?>"
                            inputmode="tel"
                        />
                        <input type="hidden" name="telefono_e164" class="flacso-telefono-e164" value="" />
                    </div>
                    <div id="<?php echo esc_attr($telefono_feedback_id); ?>" class="invalid-feedback flacso-telefono-feedback" aria-live="polite"></div>
                </div>

                <div class="flacso-form-group flacso-form-group-full">
                    <label for="<?php echo esc_attr($modalidad_select_id); ?>">Modalidad de asistencia *</label>
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
                </div>
            </div>

            <div class="flacso-form-actions">
                <button type="submit" class="button flacso-btn-submit">
                    <span>Enviar inscripción</span>
                    <svg class="flacso-btn-icon" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
                        <line x1="22" y1="2" x2="11" y2="13"></line>
                        <polygon points="22 2 15 22 11 13 2 9 22 2"></polygon>
                    </svg>
                </button>
            </div>
        </form>
        <div class="flacso-charla-form-result" aria-live="polite"></div>
    </div>
    <?php
    return ob_get_clean();
}
