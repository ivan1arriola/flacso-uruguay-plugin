<?php

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Gestiona la relación entre ofertas académicas y seminarios
 */
class Oferta_Seminarios_Integration {
    
    public static function init(): void {
        add_action('add_meta_boxes', [__CLASS__, 'add_meta_box']);
        add_action('save_post_oferta-academica', [__CLASS__, 'save_meta'], 10, 2);
    }

    public static function add_meta_box(): void {
        // Solo si cpt-seminario existe
        if (!post_type_exists('seminario')) {
            return;
        }

        add_meta_box(
            'oferta_seminarios',
            'Seminarios Asociados',
            [__CLASS__, 'render_meta_box'],
            'oferta-academica',
            'normal',
            'default'
        );
    }

    public static function render_meta_box($post): void {
        wp_nonce_field('oferta_seminarios_nonce', 'oferta_seminarios_nonce');

        $seminarios = self::get_programa_seminarios($post->ID);
        $all_seminarios = get_posts([
            'post_type' => 'seminario',
            'posts_per_page' => -1,
            'orderby' => 'title',
            'order' => 'ASC',
        ]);

        echo '<p><label for="oferta_seminarios_ids">Selecciona los seminarios de la oferta académica:</label></p>';
        echo '<select name="oferta_seminarios_ids[]" id="oferta_seminarios_ids" multiple style="width: 100%; min-height: 200px;">';
        
        foreach ($all_seminarios as $seminario) {
            $selected = in_array($seminario->ID, $seminarios) ? 'selected' : '';
            echo '<option value="' . esc_attr($seminario->ID) . '" ' . $selected . '>';
            echo esc_html($seminario->post_title);
            echo '</option>';
        }
        
        echo '</select>';
        echo '<p class="description">Mantén presionado Ctrl (Cmd en Mac) para seleccionar múltiples seminarios.</p>';
    }

    public static function save_meta($post_id, $post): void {
        if (!isset($_POST['oferta_seminarios_nonce']) || 
            !wp_verify_nonce($_POST['oferta_seminarios_nonce'], 'oferta_seminarios_nonce')) {
            return;
        }

        if (!current_user_can('edit_post', $post_id)) {
            return;
        }

        if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) {
            return;
        }

        if (isset($_POST['oferta_seminarios_ids'])) {
            $seminarios_ids = array_map('intval', $_POST['oferta_seminarios_ids']);
            update_post_meta($post_id, '_oferta_seminarios_ids', $seminarios_ids);
        } else {
            delete_post_meta($post_id, '_oferta_seminarios_ids');
        }
    }

    /**
     * Obtener seminarios asociados a un programa
     */
    public static function get_programa_seminarios($programa_id): array {
        return get_post_meta($programa_id, '_oferta_seminarios_ids', true) ?: [];
    }

    /**
     * Obtener data completa de seminarios de un programa
     */
    public static function get_programa_seminarios_data($programa_id): array {
        $seminarios_ids = self::get_programa_seminarios($programa_id);
        $seminarios = [];

        foreach ($seminarios_ids as $seminario_id) {
            $seminario = get_post($seminario_id);
            if ($seminario && $seminario->post_status === 'publish') {
                $seminarios[] = [
                    'id' => $seminario->ID,
                    'titulo' => $seminario->post_title,
                    'excerpt' => $seminario->post_excerpt,
                    'contenido' => $seminario->post_content,
                    'thumbnail' => get_post_thumbnail_id($seminario->ID),
                    'permalink' => get_permalink($seminario->ID),
                    'fecha_inicio' => get_post_meta($seminario->ID, '_seminario_fecha_inicio', true),
                    'fecha_fin' => get_post_meta($seminario->ID, '_seminario_fecha_fin', true),
                    'modalidad' => get_post_meta($seminario->ID, '_seminario_modalidad', true),
                    'costo' => get_post_meta($seminario->ID, '_seminario_costo', true),
                ];
            }
        }

        return $seminarios;
    }
}
