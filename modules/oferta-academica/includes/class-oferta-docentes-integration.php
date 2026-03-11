<?php

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Gestiona la relación entre ofertas académicas y docentes
 */
class Oferta_Docentes_Integration {
    
    public static function init(): void {
        add_action('add_meta_boxes', [__CLASS__, 'add_meta_box']);
        add_action('save_post_oferta-academica', [__CLASS__, 'save_meta'], 10, 2);
    }

    public static function add_meta_box(): void {
        // Solo si cpt-docentes existe
        if (!post_type_exists('docente')) {
            return;
        }

        add_meta_box(
            'oferta_docentes',
            'Docentes Asociados',
            [__CLASS__, 'render_meta_box'],
            'oferta-academica',
            'normal',
            'default'
        );
    }

    public static function render_meta_box($post): void {
        wp_nonce_field('oferta_docentes_nonce', 'oferta_docentes_nonce');

        $docentes = self::get_programa_docentes($post->ID);
        $all_docentes = get_posts([
            'post_type' => 'docente',
            'posts_per_page' => -1,
            'orderby' => 'title',
            'order' => 'ASC',
        ]);

        echo '<p><label for="oferta_docentes_ids">Selecciona los docentes de la oferta académica:</label></p>';
        echo '<select name="oferta_docentes_ids[]" id="oferta_docentes_ids" multiple style="width: 100%; min-height: 200px;">';
        
        foreach ($all_docentes as $docente) {
            $selected = in_array($docente->ID, $docentes) ? 'selected' : '';
            echo '<option value="' . esc_attr($docente->ID) . '" ' . $selected . '>';
            echo esc_html($docente->post_title);
            echo '</option>';
        }
        
        echo '</select>';
        echo '<p class="description">Mantén presionado Ctrl (Cmd en Mac) para seleccionar múltiples docentes.</p>';
    }

    public static function save_meta($post_id, $post): void {
        if (!isset($_POST['oferta_docentes_nonce']) || 
            !wp_verify_nonce($_POST['oferta_docentes_nonce'], 'oferta_docentes_nonce')) {
            return;
        }

        if (!current_user_can('edit_post', $post_id)) {
            return;
        }

        if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) {
            return;
        }

        if (isset($_POST['oferta_docentes_ids'])) {
            $docentes_ids = array_map('intval', $_POST['oferta_docentes_ids']);
            update_post_meta($post_id, '_oferta_docentes_ids', $docentes_ids);
        } else {
            delete_post_meta($post_id, '_oferta_docentes_ids');
        }
    }

    /**
     * Obtener docentes asociados a un programa
     */
    public static function get_programa_docentes($programa_id): array {
        return get_post_meta($programa_id, '_oferta_docentes_ids', true) ?: [];
    }

    /**
     * Obtener data completa de docentes de un programa
     */
    public static function get_programa_docentes_data($programa_id): array {
        $docentes_ids = self::get_programa_docentes($programa_id);
        $docentes = [];

        foreach ($docentes_ids as $docente_id) {
            $docente = get_post($docente_id);
            if ($docente && $docente->post_status === 'publish') {
                $docentes[] = [
                    'id' => $docente->ID,
                    'nombre' => $docente->post_title,
                    'excerpt' => $docente->post_excerpt,
                    'thumbnail' => get_post_thumbnail_id($docente->ID),
                    'email' => get_post_meta($docente->ID, '_docente_email', true),
                    'telefono' => get_post_meta($docente->ID, '_docente_telefono', true),
                    'titulo' => get_post_meta($docente->ID, '_docente_titulo', true),
                    'linkedin' => get_post_meta($docente->ID, '_docente_linkedin', true),
                ];
            }
        }

        return $docentes;
    }
}
