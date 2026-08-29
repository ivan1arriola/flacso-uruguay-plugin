<?php

if (!defined('ABSPATH')) {
    exit;
}

/** Edicion administrativa de los datos academicos propios del tipo seminario. */
final class FLACSO_Oferta_Seminario_Admin {
    public static function init(): void {
        add_action('add_meta_boxes', [self::class, 'add_meta_box']);
        add_action('save_post_' . FLACSO_Oferta_Academica::POST_TYPE, [self::class, 'save'], 20, 2);
    }

    public static function add_meta_box(): void {
        add_meta_box(
            'flacso_oferta_seminario_academico',
            __('Datos académicos de seminario', 'flacso-uruguay'),
            [self::class, 'render'],
            FLACSO_Oferta_Academica::POST_TYPE,
            'normal',
            'default'
        );
    }

    public static function render($post): void {
        wp_nonce_field('flacso_oferta_seminario_save', 'flacso_oferta_seminario_nonce');
        echo '<p class="description">' . esc_html__('Estos campos se usan únicamente cuando el tipo de oferta es Seminario. Las fechas, docentes de la edición, modalidad y precios se administran en InstanciaOferta.', 'flacso-uruguay') . '</p>';
        foreach (FLACSO_Oferta_Academica::seminar_academic_meta_keys() as $meta_key) {
            if ($meta_key === '_seminario_nombre') {
                continue;
            }
            $value = get_post_meta($post->ID, $meta_key, true);
            $label = ucwords(str_replace('_', ' ', preg_replace('/^_seminario_/', '', $meta_key)));
            echo '<p><label for="' . esc_attr($meta_key) . '"><strong>' . esc_html($label) . '</strong></label><br>';
            if (is_array($value)) {
                echo '<textarea class="large-text code" rows="5" id="' . esc_attr($meta_key) . '" name="' . esc_attr($meta_key) . '">' . esc_textarea(wp_json_encode($value, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE)) . '</textarea>';
            } elseif (in_array($meta_key, ['_seminario_acredita_maestria', '_seminario_acredita_doctorado'], true)) {
                echo '<label><input type="checkbox" name="' . esc_attr($meta_key) . '" value="1" ' . checked((bool) $value, true, false) . '> ' . esc_html__('Sí', 'flacso-uruguay') . '</label>';
            } else {
                echo '<textarea class="large-text" rows="4" id="' . esc_attr($meta_key) . '" name="' . esc_attr($meta_key) . '">' . esc_textarea((string) $value) . '</textarea>';
            }
            echo '</p>';
        }

        $components = FLACSO_Relacion_Oferta_Academica::get((int) $post->ID, FLACSO_Relacion_Oferta_Academica::COMPUESTO_POR);
        $ids = array_map(static function (array $relation): int { return absint($relation['oferta_destino']); }, $components);
        echo '<p><label for="flacso_seminario_component_ids"><strong>' . esc_html__('Componentes (IDs de OfertaAcadémica)', 'flacso-uruguay') . '</strong></label><br>';
        echo '<input class="large-text" id="flacso_seminario_component_ids" name="flacso_seminario_component_ids" value="' . esc_attr(implode(', ', $ids)) . '"></p>';
    }

    public static function save(int $post_id, $post): void {
        if (!isset($_POST['flacso_oferta_seminario_nonce'])
            || !wp_verify_nonce((string) $_POST['flacso_oferta_seminario_nonce'], 'flacso_oferta_seminario_save')
            || !current_user_can('edit_post', $post_id)
            || (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE)) {
            return;
        }
        foreach (FLACSO_Oferta_Academica::seminar_academic_meta_keys() as $meta_key) {
            if ($meta_key === '_seminario_nombre') {
                update_post_meta($post_id, $meta_key, (string) $post->post_title);
                continue;
            }
            $short_key = str_replace('_seminario_', '', $meta_key);
            if (in_array($meta_key, ['_seminario_acredita_maestria', '_seminario_acredita_doctorado'], true)) {
                update_post_meta($post_id, $meta_key, isset($_POST[$meta_key]) ? '1' : '0');
                continue;
            }
            if (!isset($_POST[$meta_key])) {
                continue;
            }
            $raw = wp_unslash($_POST[$meta_key]);
            if (in_array($short_key, ['objetivos_especificos', 'unidades_academicas'], true)) {
                $decoded = json_decode((string) $raw, true);
                $raw = is_array($decoded) ? $decoded : [];
            }
            update_post_meta($post_id, $meta_key, Seminario_Meta::sanitize_value($raw, $meta_key));
        }

        $component_ids = isset($_POST['flacso_seminario_component_ids'])
            ? preg_split('/[\s,;]+/', sanitize_text_field(wp_unslash((string) $_POST['flacso_seminario_component_ids'])))
            : [];
        FLACSO_Relacion_Oferta_Academica::replace_type(
            $post_id,
            FLACSO_Relacion_Oferta_Academica::COMPUESTO_POR,
            array_map('absint', is_array($component_ids) ? $component_ids : [])
        );
    }
}
