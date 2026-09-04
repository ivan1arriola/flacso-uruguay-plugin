<?php

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Compatibilidad entre los campos históricos del contacto de carta y el
 * esquema canónico nuevo de la Oferta Académica.
 *
 * Importante: este puente corre sobre `get_post_metadata`. Cualquier lectura
 * interna de metadatos debe quedar protegida para no volver a entrar al mismo
 * filtro indefinidamente.
 */
final class FLACSO_Offer_Carta_Contact_Bridge {
    private const LEGACY_TO_CANONICAL = [
        'asistente_academica_docente_id' => FLACSO_Offer_Carta_Contact_Admin::META_PERSON_ID,
        'asistente_academica_rol' => FLACSO_Offer_Carta_Contact_Admin::META_TITLE,
        'asistente_academica_correo' => FLACSO_Offer_Carta_Contact_Admin::META_EMAIL,
    ];

    /** @var array<string,bool> */
    private static array $resolving = [];

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
        $canonical_key = self::LEGACY_TO_CANONICAL[$meta_key] ?? null;
        $legacy_key = $canonical_key === null
            ? array_search($meta_key, self::LEGACY_TO_CANONICAL, true)
            : false;

        // No intervenir en ninguna metadata ajena al contacto de carta.
        if ($canonical_key === null && $legacy_key === false) {
            return $value;
        }

        if ($object_id < 1 || get_post_type($object_id) !== FLACSO_Oferta_Academica::POST_TYPE) {
            return $value;
        }

        $guard_key = $object_id . ':' . $meta_key . ':' . ($single ? '1' : '0');
        if (isset(self::$resolving[$guard_key])) {
            // Devolver el valor previo (normalmente null) permite que
            // get_metadata_raw continúe con la lectura directa de caché/BD.
            return $value;
        }

        self::$resolving[$guard_key] = true;
        try {
            if ($canonical_key !== null) {
                $canonical = get_metadata_raw('post', $object_id, $canonical_key, $single);
                return $canonical !== null ? $canonical : $value;
            }

            $canonical = get_metadata_raw('post', $object_id, $meta_key, $single);
            if ($canonical !== null) {
                return $canonical;
            }

            $legacy = get_metadata_raw('post', $object_id, (string) $legacy_key, $single);
            return $legacy !== null ? $legacy : $value;
        } finally {
            unset(self::$resolving[$guard_key]);
        }
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
