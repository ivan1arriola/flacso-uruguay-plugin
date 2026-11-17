<?php
/**
 * Participantes de un evento: personas del CPT docente, en el orden elegido.
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Deja solo ids enteros positivos, sin repetir y en el orden recibido.
 *
 * @param mixed $raw
 * @return int[]
 */
function flacso_eventos_clean_speaker_ids($raw): array {
    $ids = array_filter(array_map('absint', (array) $raw));
    return array_values(array_unique($ids));
}

/** @return int[] Docentes publicados asociados al evento. */
function flacso_eventos_get_speaker_ids(int $event_id): array {
    $ids = flacso_eventos_clean_speaker_ids(get_post_meta($event_id, '_evento_expositores', true));
    return array_values(array_filter($ids, static function (int $id): bool {
        return get_post_type($id) === 'docente' && get_post_status($id) === 'publish';
    }));
}

add_action('init', 'flacso_eventos_register_speakers_meta', 20);
function flacso_eventos_register_speakers_meta(): void {
    register_post_meta('evento', '_evento_expositores', [
        'type' => 'array',
        'single' => true,
        'show_in_rest' => ['schema' => ['type' => 'array', 'items' => ['type' => 'integer']]],
        'sanitize_callback' => 'flacso_eventos_clean_speaker_ids',
        'auth_callback' => static function () {
            return current_user_can('edit_posts');
        },
    ]);
}

add_action('add_meta_boxes_evento', static function (): void {
    add_meta_box(
        'flacso_evento_speakers',
        __('Participantes', 'flacso-uruguay'),
        'flacso_eventos_render_speakers_meta_box',
        'evento',
        'normal',
        'high'
    );
});

function flacso_eventos_render_speakers_meta_box($post): void {
    wp_nonce_field('flacso_evento_speakers_save', 'flacso_evento_speakers_nonce');

    $people = get_posts([
        'post_type' => 'docente',
        'post_status' => 'publish',
        'numberposts' => -1,
        'orderby' => 'title',
        'order' => 'ASC',
    ]);
    $selected = flacso_eventos_clean_speaker_ids(get_post_meta($post->ID, '_evento_expositores', true));

    $row = static function (int $current) use ($people): void {
        echo '<p class="flacso-speaker-row"><select name="evento_expositores[]" style="min-width:320px">';
        echo '<option value="">' . esc_html__('— Elegir persona —', 'flacso-uruguay') . '</option>';
        foreach ($people as $person) {
            $label = function_exists('dp_nombre_completo') ? dp_nombre_completo($person->ID) : $person->post_title;
            printf('<option value="%d"%s>%s</option>', $person->ID, selected($current, $person->ID, false), esc_html($label));
        }
        echo '</select> <button type="button" class="button-link-delete flacso-speaker-remove">'
            . esc_html__('Quitar', 'flacso-uruguay') . '</button></p>';
    };
    ?>
    <p class="description"><?php esc_html_e('Aparecen en la página del evento, en este orden, con foto, cargo y enlace a su perfil. Las personas se administran en Personas / Equipo.', 'flacso-uruguay'); ?></p>
    <div id="flacso-speakers-list">
        <?php foreach ($selected as $id) { $row($id); } ?>
    </div>
    <template id="flacso-speaker-template"><?php $row(0); ?></template>
    <p><button type="button" class="button" id="flacso-speaker-add"><?php esc_html_e('Agregar participante', 'flacso-uruguay'); ?></button></p>
    <script>
    (function () {
        var list = document.getElementById('flacso-speakers-list');
        var tpl = document.getElementById('flacso-speaker-template');
        document.getElementById('flacso-speaker-add').addEventListener('click', function () {
            list.appendChild(tpl.content.cloneNode(true));
        });
        list.addEventListener('click', function (e) {
            var btn = e.target.closest('.flacso-speaker-remove');
            if (btn) btn.closest('.flacso-speaker-row').remove();
        });
    })();
    </script>
    <?php
}

add_action('save_post_evento', 'flacso_eventos_save_speakers', 20);
function flacso_eventos_save_speakers($post_id): void {
    if (
        !isset($_POST['flacso_evento_speakers_nonce'])
        || !wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['flacso_evento_speakers_nonce'])), 'flacso_evento_speakers_save')
        || (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE)
        || !current_user_can('edit_post', $post_id)
    ) {
        return;
    }

    $ids = flacso_eventos_clean_speaker_ids(wp_unslash($_POST['evento_expositores'] ?? []));
    $ids = array_values(array_filter($ids, static function (int $id): bool {
        return get_post_type($id) === 'docente';
    }));
    if ($ids) {
        update_post_meta($post_id, '_evento_expositores', $ids);
    } else {
        delete_post_meta($post_id, '_evento_expositores');
    }
}
