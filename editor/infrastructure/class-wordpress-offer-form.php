<?php

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Adaptador de edición completo.
 *
 * Reutiliza el formulario de Oferta y todos los save_post registrados por
 * los módulos originales: no implementa otra persistencia de metadatos.
 */
final class FLACSO_WordPress_Offer_Form implements FLACSO_Editor_Offer_Form_Interface {
    public function render(int $id): void {
        $post = get_post($id);
        if (!$post instanceof WP_Post || $post->post_type !== 'oferta-academica') {
            return;
        }

        // Renderizador canónico. Incluye todos los campos académicos,
        // equipos estables, contacto de carta, sus nonces y relaciones.
        FLACSO_Oferta_Admin_Fields::render($post);
    }

    public function enqueue_assets(): void {
        wp_enqueue_editor();
        wp_enqueue_media();
        wp_enqueue_script('jquery-ui-autocomplete');
    }

    public function render_head(): void {
        FLACSO_Oferta_Admin_Fields::render_styles(true);
        FLACSO_Academic_Admin_UI::render_styles(true);
        FLACSO_Academic_Team_Editor::render_styles(true);
        FLACSO_Offer_Carta_Contact_Admin::render_styles(true);
    }

    public function render_footer(int $id): void {
        $post = get_post($id);
        if (!$post instanceof WP_Post || $post->post_type !== 'oferta-academica') {
            return;
        }

        FLACSO_Academic_Admin_UI::render_scripts(true);
        FLACSO_Oferta_Admin_Fields::render_scripts(true);
        FLACSO_Academic_Team_Editor::render_script(true);
        FLACSO_Offer_Carta_Contact_Admin::render_script(true);
        // Conserva los modos de malla curricular: URL o PDF de Medios.
        FLACSO_Academic_Document_Source_Admin::render_editor($post);
    }

    public function save(int $id, array $data): array {
        $post = get_post($id);
        if (!$post instanceof WP_Post || $post->post_type !== 'oferta-academica'
            || !current_user_can('edit_post', $id)) {
            return ['success' => false, 'message' => 'No tenés permisos para modificar esta oferta.'];
        }

        // Evita pérdida masiva de metadatos si algún formulario se envía
        // incompleto por un cambio de JS, plantilla o un cliente malicioso.
        if (
            empty($_POST['flacso_editor_offer_form_present'])
            || !isset($_POST['flacso_oferta']) || !is_array($_POST['flacso_oferta'])
            || !isset($_POST['flacso_oferta_academica_nonce'])
            || !wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['flacso_oferta_academica_nonce'])), 'flacso_save_oferta_academica')
            || !isset($_POST['flacso_academic_teams_v2_nonce'])
            || !wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['flacso_academic_teams_v2_nonce'])), 'flacso_save_academic_teams_v2')
            || !isset($_POST['flacso_offer_carta_contact_nonce'])
            || !wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['flacso_offer_carta_contact_nonce'])), 'flacso_save_offer_carta_contact')
        ) {
            return ['success' => false, 'message' => 'El formulario está incompleto o la sesión venció. Recargá la página antes de guardar.'];
        }

        $title = sanitize_text_field((string) ($data['title'] ?? ''));
        if ($title === '') {
            return ['success' => false, 'message' => 'El nombre de la oferta no puede quedar vacío.'];
        }

        $original_status = (string) $post->post_status;
        $new_status = sanitize_key((string) ($data['status'] ?? $original_status));
        $valid_statuses = ['draft', 'pending', 'publish', 'private', 'future'];
        if (!in_array($new_status, $valid_statuses, true)) {
            return ['success' => false, 'message' => 'El estado de publicación no es válido.'];
        }

        $post_type = get_post_type_object('oferta-academica');
        $publish_capability = $post_type && isset($post_type->cap->publish_posts)
            ? (string) $post_type->cap->publish_posts
            : 'publish_posts';
        if ($new_status !== $original_status
            && in_array($new_status, ['publish', 'private', 'future'], true)
            && !current_user_can($publish_capability)) {
            return ['success' => false, 'message' => 'No tenés permisos para publicar esta oferta.'];
        }
        // No permitir programar publicaciones sin gestionar la fecha.
        if ($new_status === 'future' && $original_status !== 'future') {
            return ['success' => false, 'message' => 'La programación de publicaciones se gestiona desde el editor clásico.'];
        }

        // Comprobar la imagen antes de modificar datos académicos. Si un
        // adjunto no es válido no se produce un guardado parcial.
        if (array_key_exists('thumbnail_id', $data)) {
            $candidate_image_id = absint($data['thumbnail_id']);
            if ($candidate_image_id > 0 && !wp_attachment_is_image($candidate_image_id)) {
                return ['success' => false, 'message' => 'La imagen destacada seleccionada no es válida.'];
            }
        }

        // wp_update_post() invoca TODOS los hooks save_post existentes con
        // los mismos nombres de campos POST y nonces usados por el clásico.
        // Conservar la fecha, slug, contenido y demás propiedades del post.
        $result = wp_update_post([
            'ID' => $id,
            'post_title' => $title,
            'post_status' => $new_status,
        ], true);

        if (is_wp_error($result)) {
            return ['success' => false, 'message' => $result->get_error_message()];
        }

        // La imagen destacada es un campo nativo separado de los metadatos
        // académicos. Se valida el adjunto antes de actualizarlo.
        if (array_key_exists('thumbnail_id', $data)) {
            $thumbnail_id = absint($data['thumbnail_id']);
            if ($thumbnail_id === 0) {
                delete_post_thumbnail($id);
            } elseif (wp_attachment_is_image($thumbnail_id) && current_user_can('edit_post', $id)) {
                set_post_thumbnail($id, $thumbnail_id);
            }
        }

        return ['success' => true, 'message' => 'Oferta académica guardada correctamente.'];
    }
}
