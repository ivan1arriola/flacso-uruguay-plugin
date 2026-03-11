<?php

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Gestiona la asociación entre posts de oferta-academica y páginas de WordPress
 * Patrón Adapter: Cada oferta académica tiene una página asociada que sirve como template
 */
class Oferta_Page_Adapter {
    
    public static function init(): void {
        add_action('add_meta_boxes', [__CLASS__, 'add_meta_box']);
        add_action('save_post_oferta-academica', [__CLASS__, 'save_meta'], 10, 2);
        add_action('save_post_oferta-academica', [__CLASS__, 'sync_featured_image'], 20, 2);
    }

    public static function add_meta_box(): void {
        add_meta_box(
            'oferta_page_adapter',
            'Página Asociada (Adapter)',
            [__CLASS__, 'render_meta_box'],
            'oferta-academica',
            'side',
            'default'
        );
    }

    public static function render_meta_box($post): void {
        wp_nonce_field('oferta_page_adapter_nonce', 'oferta_page_adapter_nonce');
        
        $page_id = get_post_meta($post->ID, '_oferta_page_id', true);
        
        $pages = get_pages(['sort_column' => 'post_title']);
        
        echo '<p><label for="oferta_page_id">Selecciona la página:</label></p>';
        echo '<select name="oferta_page_id" id="oferta_page_id" style="width: 100%;">';
        echo '<option value="">-- Sin página asociada --</option>';
        
        foreach ($pages as $page) {
            $selected = selected($page_id, $page->ID, false);
            echo '<option value="' . esc_attr($page->ID) . '" ' . $selected . '>';
            echo esc_html($page->post_title);
            echo '</option>';
        }
        
        echo '</select>';
        echo '<p class="description">La página asociada servirá como template/patrón para esta oferta académica.</p>';
    }

    public static function save_meta($post_id, $post): void {
        // Verificar nonce
        if (!isset($_POST['oferta_page_adapter_nonce']) || 
            !wp_verify_nonce($_POST['oferta_page_adapter_nonce'], 'oferta_page_adapter_nonce')) {
            return;
        }

        // Verificar permisos
        if (!current_user_can('edit_post', $post_id)) {
            return;
        }

        // Verificar autosave
        if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) {
            return;
        }

        // Guardar o eliminar
        if (isset($_POST['oferta_page_id']) && !empty($_POST['oferta_page_id'])) {
            update_post_meta($post_id, '_oferta_page_id', intval($_POST['oferta_page_id']));
        } else {
            delete_post_meta($post_id, '_oferta_page_id');
        }
    }

    /**
     * Obtener la página asociada a una oferta académica
     */
    public static function get_page_id($post_id): ?int {
        $page_id = get_post_meta($post_id, '_oferta_page_id', true);
        return $page_id ? intval($page_id) : null;
    }

    /**
     * Sincronizar la imagen destacada desde la página asociada
     */
    public static function sync_featured_image($post_id, $post): void {
        // Verificar permisos
        if (!current_user_can('edit_post', $post_id)) {
            return;
        }

        // Verificar autosave
        if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) {
            return;
        }

        // Obtener página asociada
        $page_id = self::get_page_id($post_id);
        if (!$page_id) {
            return;
        }

        // Obtener thumbnail de la página
        $page_thumbnail_id = get_post_thumbnail_id($page_id);
        
        if ($page_thumbnail_id) {
            // Sincronizar: establecer la misma imagen al programa
            set_post_thumbnail($post_id, $page_thumbnail_id);
        }
    }
}
