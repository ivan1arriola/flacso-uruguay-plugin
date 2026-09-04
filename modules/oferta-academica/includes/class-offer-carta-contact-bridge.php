<?php

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Compatibilidad entre los campos históricos del contacto de carta y el
 * esquema canónico nuevo de la Oferta Académica.
 *
 * - El admin nuevo puede leer los valores históricos mientras no se haya
 *   guardado la configuración nueva.
 * - El frontend histórico puede seguir solicitando los nombres anteriores.
 * - Al guardar explícitamente el metabox nuevo, se consolidan los datos en
 *   los campos canónicos y se eliminan las copias legacy.
 */
final class FLACSO_Offer_Carta_Contact_Bridge {
    private const LEGACY_TO_CANONICAL = [
        'asistente_academica_docente_id' => FLACSO_Offer_Carta_Contact_Admin::META_PERSON_ID,
        'asistente_academica_rol' => FLACSO_Offer_Carta_Contact_Admin::META_TITLE,
        'asistente_academica_correo' => FLACSO_Offer_Carta_Contact_Admin::META_EMAIL,
    ];

    public static function init(): void {
        add_filter('get_post_metadata', [self::class, 'bridge_meta_reads'], 10, 4);
        add_action('save_post_' . FLACSO_Oferta_Academica::POST_TYPE, [self::class, 'consolidate_legacy_meta'], 30, 2);
    }

    /**
     * Mantiene compatibilidad de lectura en ambos sentidos sin duplicar datos.
     *
     * @param mixed $value
     * @return mixed
     */
    public static function bridge_meta_reads($value, int $object_id, string $meta_key, bool $single) {
        if ($object_id < 1 || get_post_type($object_id) !== FLACSO_Oferta_Academica::POST_TYPE) {
            return $value;
        }

        if (isset(self::LEGACY_TO_CANONICAL[$meta_key])) {
            $canonical_key = self::LEGACY_TO_CANONICAL[$meta_key];
            $canonical = get_metadata_raw('post', $object_id, $canonical_key, $single);
            return $canonical !== null ? $canonical : $value;
        }

        $legacy_key = array_search($meta_key, self::LEGACY_TO_CANONICAL, true);
        if ($legacy_key !== false) {
            $canonical = get_metadata_raw('post', $object_id, $meta_key, $single);
            if ($canonical !== null) {
                return $canonical;
            }
            $legacy = get_metadata_raw('post', $object_id, (string) $legacy_key, $single);
            return $legacy !== null ? $legacy : $value;
        }

        return $value;
    }

    public static function consolidate_legacy_meta(int $post_id, WP_Post $post): void {
        if ($post->post_type !== FLACSO_Oferta_Academica::POST_TYPE) {
            return;
        }
        if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) {
            return;
        }
        if (wp_is_post_revision($post_id) || !current_user_can('edit_post', $post_id)) {
            return;
        }

        // Solo consolidar cuando el usuario guardó explícitamente este metabox.
        if (!isset($_POST['flacso_carta_contact']) || !is_array($_POST['flacso_carta_contact'])) {
            return;
        }

        foreach (array_keys(self::LEGACY_TO_CANONICAL) as $legacy_key) {
            delete_post_meta($post_id, $legacy_key);
        }
    }
}
