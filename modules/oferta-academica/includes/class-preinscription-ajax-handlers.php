<?php

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Apertura y cierre de preinscripciones administrados en WordPress.
 *
 * La plataforma de preinscripciones consulta este estado mediante el
 * catálogo REST público; no se sincroniza ni depende de Django.
 */
final class FLACSO_Preinscription_Ajax_Handlers {
    public static function init(): void {
        add_action('wp_ajax_flacso_abrir_preinscripcion_cohorte', [self::class, 'abrir_cohorte']);
        add_action('wp_ajax_flacso_cerrar_preinscripcion_cohorte', [self::class, 'cerrar_cohorte']);
        add_action('wp_ajax_flacso_abrir_preinscripcion_edicion', [self::class, 'abrir_edicion']);
        add_action('wp_ajax_flacso_cerrar_preinscripcion_edicion', [self::class, 'cerrar_edicion']);
    }

    public static function abrir_cohorte(): void {
        self::verify_nonce();
        $cohorte_id = absint($_POST['cohorte_id'] ?? 0);
        $oferta_id = self::cohort_offer_id($cohorte_id);
        self::assert_editable_cohort($cohorte_id, $oferta_id);

        self::set_single_open_instance(
            FLACSO_Cohorte::POST_TYPE,
            FLACSO_Cohorte::META_PARENT_ID,
            $oferta_id,
            $cohorte_id
        );

        $url = self::set_canonical_link($cohorte_id, 'oferta', $oferta_id);
        wp_send_json_success([
            'url' => $url,
            'abierta' => true,
            'message' => 'Preinscripción abierta correctamente.',
        ]);
    }

    public static function cerrar_cohorte(): void {
        self::verify_nonce();
        $cohorte_id = absint($_POST['cohorte_id'] ?? 0);
        $oferta_id = self::cohort_offer_id($cohorte_id);
        self::assert_editable_cohort($cohorte_id, $oferta_id);

        self::set_single_open_instance(
            FLACSO_Cohorte::POST_TYPE,
            FLACSO_Cohorte::META_PARENT_ID,
            $oferta_id,
            null
        );

        wp_send_json_success([
            'url' => (string) get_post_meta($cohorte_id, 'link_preinscripcion', true),
            'abierta' => false,
            'message' => 'Preinscripción cerrada.',
        ]);
    }

    public static function abrir_edicion(): void {
        self::verify_nonce();
        $edicion_id = absint($_POST['edicion_id'] ?? 0);
        $seminario_id = self::edition_seminar_id($edicion_id);
        self::assert_editable_edition($edicion_id, $seminario_id);

        self::set_single_open_instance(
            FLACSO_Edicion::POST_TYPE,
            FLACSO_Edicion::META_PARENT_ID,
            $seminario_id,
            $edicion_id
        );

        if (FLACSO_Edicion::registration_availability($edicion_id)['status'] !== 'open') {
            self::set_single_open_instance(
                FLACSO_Edicion::POST_TYPE,
                FLACSO_Edicion::META_PARENT_ID,
                $seminario_id,
                null
            );
            wp_send_json_error([
                'message' => 'La edición superó el plazo de preinscripción.',
            ], 422);
        }

        $url = self::set_canonical_link($edicion_id, 'seminario', $seminario_id);
        wp_send_json_success([
            'url' => $url,
            'abierta' => true,
            'message' => 'Preinscripción abierta correctamente.',
        ]);
    }

    public static function cerrar_edicion(): void {
        self::verify_nonce();
        $edicion_id = absint($_POST['edicion_id'] ?? 0);
        $seminario_id = self::edition_seminar_id($edicion_id);
        self::assert_editable_edition($edicion_id, $seminario_id);

        self::set_single_open_instance(
            FLACSO_Edicion::POST_TYPE,
            FLACSO_Edicion::META_PARENT_ID,
            $seminario_id,
            null
        );

        wp_send_json_success([
            'url' => (string) get_post_meta($edicion_id, 'link_preinscripcion', true),
            'abierta' => false,
            'message' => 'Preinscripción cerrada.',
        ]);
    }

    private static function verify_nonce(): void {
        $nonce = sanitize_text_field(wp_unslash($_POST['_wpnonce'] ?? ''));
        if (!wp_verify_nonce($nonce, 'flacso_preinscripcion_nonce')) {
            wp_send_json_error(['message' => 'Nonce inválido.'], 403);
        }
    }

    private static function cohort_offer_id(int $cohorte_id): int {
        return absint(get_post_meta($cohorte_id, FLACSO_Cohorte::META_PARENT_ID, true));
    }

    private static function edition_seminar_id(int $edicion_id): int {
        return absint(get_post_meta($edicion_id, FLACSO_Edicion::META_PARENT_ID, true));
    }

    private static function assert_editable_cohort(int $cohorte_id, int $oferta_id): void {
        if (
            $cohorte_id === 0
            || $oferta_id === 0
            || get_post_type($cohorte_id) !== FLACSO_Cohorte::POST_TYPE
            || get_post_type($oferta_id) !== FLACSO_Oferta_Academica::POST_TYPE
        ) {
            wp_send_json_error(['message' => 'Datos incompletos.'], 400);
        }
        if (!current_user_can('edit_post', $cohorte_id)) {
            wp_send_json_error(['message' => 'Sin permisos.'], 403);
        }
    }

    private static function assert_editable_edition(int $edicion_id, int $seminario_id): void {
        if (
            $edicion_id === 0
            || $seminario_id === 0
            || get_post_type($edicion_id) !== FLACSO_Edicion::POST_TYPE
            || get_post_type($seminario_id) !== FLACSO_Seminario::POST_TYPE
        ) {
            wp_send_json_error(['message' => 'Datos incompletos.'], 400);
        }
        if (!current_user_can('edit_post', $edicion_id)) {
            wp_send_json_error(['message' => 'Sin permisos.'], 403);
        }
    }

    private static function set_single_open_instance(
        string $post_type,
        string $parent_key,
        int $parent_id,
        ?int $open_id
    ): void {
        $instance_ids = get_posts([
            'post_type' => $post_type,
            'post_status' => ['publish', 'draft', 'pending', 'private'],
            'posts_per_page' => -1,
            'fields' => 'ids',
            'meta_query' => [[
                'key' => $parent_key,
                'value' => $parent_id,
                'compare' => '=',
                'type' => 'NUMERIC',
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

    private static function set_canonical_link(int $instance_id, string $prefix, int $parent_id): string {
        $url = self::canonical_url($prefix, $parent_id);
        if ($url === '') {
            return (string) get_post_meta($instance_id, 'link_preinscripcion', true);
        }
        update_post_meta($instance_id, 'link_preinscripcion', $url);
        return $url;
    }

    public static function offer_url(int $offer_id): string {
        return self::canonical_url('oferta', $offer_id);
    }

    public static function seminar_url(int $seminar_id): string {
        return self::canonical_url('seminario', $seminar_id);
    }

    private static function canonical_url(string $prefix, int $parent_id): string {
        if (!function_exists('get_post_field')) {
            return '';
        }
        $slug = sanitize_title((string) get_post_field('post_name', $parent_id));
        return $slug === ''
            ? ''
            : sprintf('https://preinscripciones.flacso.edu.uy/%s/%s/', $prefix, $slug);
    }
}

FLACSO_Preinscription_Ajax_Handlers::init();
