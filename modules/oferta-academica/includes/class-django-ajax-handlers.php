<?php

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Handlers AJAX para abrir y cerrar preinscripciones desde wp-admin.
 * Se invocan desde los botones del meta box de Cohorte y Edición.
 */
final class FLACSO_Django_Ajax_Handlers {

    public static function init(): void {
        add_action('wp_ajax_flacso_abrir_preinscripcion_cohorte',  [self::class, 'abrir_cohorte']);
        add_action('wp_ajax_flacso_cerrar_preinscripcion_cohorte', [self::class, 'cerrar_cohorte']);
        add_action('wp_ajax_flacso_abrir_preinscripcion_edicion',  [self::class, 'abrir_edicion']);
        add_action('wp_ajax_flacso_cerrar_preinscripcion_edicion', [self::class, 'cerrar_edicion']);
    }

    // -------------------------------------------------------------------------
    // Cohortes
    // -------------------------------------------------------------------------

    public static function abrir_cohorte(): void {
        self::verify_nonce('flacso_preinscripcion_nonce');
        $cohorte_id = absint($_POST['cohorte_id'] ?? 0);
        $oferta_id  = absint(get_post_meta($cohorte_id, 'oferta_academica_id', true));

        if (
            !$cohorte_id
            || get_post_type($cohorte_id) !== FLACSO_Cohorte::POST_TYPE
            || !$oferta_id
            || get_post_type($oferta_id) !== FLACSO_Oferta_Academica::POST_TYPE
        ) {
            wp_send_json_error(['message' => 'Datos incompletos.'], 400);
        }
        if (!current_user_can('edit_post', $cohorte_id)) {
            wp_send_json_error(['message' => 'Sin permisos.'], 403);
        }

        $result = FLACSO_Django_API_Client::abrir_cohorte($oferta_id, $cohorte_id);

        if (is_wp_error($result)) {
            wp_send_json_error(['message' => $result->get_error_message()], 502);
        }

        // Refleja localmente el mismo invariante confirmado por Django.
        self::set_single_open_instance(
            FLACSO_Cohorte::POST_TYPE,
            FLACSO_Cohorte::META_PARENT_ID,
            $oferta_id,
            $cohorte_id
        );
        $url = $result['url_preinscripcion'] ?? FLACSO_Django_API_Client::url_preinscripcion_oferta($oferta_id);
        if ($url) {
            update_post_meta($cohorte_id, 'link_preinscripcion', $url);
        }

        wp_send_json_success([
            'url'     => $url,
            'abierta' => true,
            'message' => 'Preinscripción abierta correctamente.',
        ]);
    }

    public static function cerrar_cohorte(): void {
        self::verify_nonce('flacso_preinscripcion_nonce');
        $cohorte_id = absint($_POST['cohorte_id'] ?? 0);
        $oferta_id  = absint(get_post_meta($cohorte_id, 'oferta_academica_id', true));

        if (
            !$cohorte_id
            || get_post_type($cohorte_id) !== FLACSO_Cohorte::POST_TYPE
            || !$oferta_id
            || get_post_type($oferta_id) !== FLACSO_Oferta_Academica::POST_TYPE
        ) {
            wp_send_json_error(['message' => 'Datos incompletos.'], 400);
        }
        if (!current_user_can('edit_post', $cohorte_id)) {
            wp_send_json_error(['message' => 'Sin permisos.'], 403);
        }

        $result = FLACSO_Django_API_Client::cerrar_cohortes($oferta_id);

        if (is_wp_error($result)) {
            wp_send_json_error(['message' => $result->get_error_message()], 502);
        }

        self::set_single_open_instance(
            FLACSO_Cohorte::POST_TYPE,
            FLACSO_Cohorte::META_PARENT_ID,
            $oferta_id,
            null
        );

        $url = FLACSO_Django_API_Client::url_preinscripcion_oferta($oferta_id);
        wp_send_json_success([
            'url'     => $url,
            'abierta' => false,
            'message' => 'Preinscripción cerrada.',
        ]);
    }

    // -------------------------------------------------------------------------
    // Ediciones de Seminario
    // -------------------------------------------------------------------------

    public static function abrir_edicion(): void {
        self::verify_nonce('flacso_preinscripcion_nonce');
        $edicion_id    = absint($_POST['edicion_id'] ?? 0);
        $seminario_id  = absint(get_post_meta($edicion_id, 'seminario_id', true));

        if (
            !$edicion_id
            || get_post_type($edicion_id) !== FLACSO_Edicion::POST_TYPE
            || !$seminario_id
            || get_post_type($seminario_id) !== FLACSO_Seminario::POST_TYPE
        ) {
            wp_send_json_error(['message' => 'Datos incompletos.'], 400);
        }
        if (!current_user_can('edit_post', $edicion_id)) {
            wp_send_json_error(['message' => 'Sin permisos.'], 403);
        }

        $result = FLACSO_Django_API_Client::abrir_edicion($seminario_id, $edicion_id);

        if (is_wp_error($result)) {
            wp_send_json_error(['message' => $result->get_error_message()], 502);
        }

        self::set_single_open_instance(
            FLACSO_Edicion::POST_TYPE,
            FLACSO_Edicion::META_PARENT_ID,
            $seminario_id,
            $edicion_id
        );
        $url = $result['url_preinscripcion'] ?? FLACSO_Django_API_Client::url_preinscripcion_seminario($seminario_id);
        if ($url) {
            update_post_meta($edicion_id, 'link_preinscripcion', $url);
        }

        wp_send_json_success([
            'url'     => $url,
            'abierta' => true,
            'message' => 'Preinscripción abierta correctamente.',
        ]);
    }

    public static function cerrar_edicion(): void {
        self::verify_nonce('flacso_preinscripcion_nonce');
        $edicion_id   = absint($_POST['edicion_id'] ?? 0);
        $seminario_id = absint(get_post_meta($edicion_id, 'seminario_id', true));

        if (
            !$edicion_id
            || get_post_type($edicion_id) !== FLACSO_Edicion::POST_TYPE
            || !$seminario_id
            || get_post_type($seminario_id) !== FLACSO_Seminario::POST_TYPE
        ) {
            wp_send_json_error(['message' => 'Datos incompletos.'], 400);
        }
        if (!current_user_can('edit_post', $edicion_id)) {
            wp_send_json_error(['message' => 'Sin permisos.'], 403);
        }

        $result = FLACSO_Django_API_Client::cerrar_ediciones($seminario_id);

        if (is_wp_error($result)) {
            wp_send_json_error(['message' => $result->get_error_message()], 502);
        }

        self::set_single_open_instance(
            FLACSO_Edicion::POST_TYPE,
            FLACSO_Edicion::META_PARENT_ID,
            $seminario_id,
            null
        );

        $url = FLACSO_Django_API_Client::url_preinscripcion_seminario($seminario_id);
        wp_send_json_success([
            'url'     => $url,
            'abierta' => false,
            'message' => 'Preinscripción cerrada.',
        ]);
    }

    // -------------------------------------------------------------------------
    // Helper
    // -------------------------------------------------------------------------

    private static function verify_nonce(string $action): void {
        $nonce = sanitize_text_field(wp_unslash($_POST['_wpnonce'] ?? ''));
        if (!wp_verify_nonce($nonce, $action)) {
            wp_send_json_error(['message' => 'Nonce inválido.'], 403);
        }
    }

    /**
     * Mantiene en WordPress el mismo invariante de Django: una sola instancia
     * abierta por entidad padre. Solo se ejecuta después de un PUT exitoso.
     */
    private static function set_single_open_instance(
        string $post_type,
        string $parent_key,
        int $parent_id,
        ?int $open_id
    ): void {
        $instance_ids = get_posts([
            'post_type'      => $post_type,
            'post_status'    => ['publish', 'draft', 'pending', 'private'],
            'posts_per_page' => -1,
            'fields'         => 'ids',
            'meta_query'     => [[
                'key'     => $parent_key,
                'value'   => $parent_id,
                'compare' => '=',
                'type'    => 'NUMERIC',
            ]],
        ]);

        foreach ($instance_ids as $instance_id) {
            update_post_meta(
                (int) $instance_id,
                'preinscripcion_habilitada',
                $open_id !== null && (int) $instance_id === $open_id
            );
        }
    }
}

FLACSO_Django_Ajax_Handlers::init();
