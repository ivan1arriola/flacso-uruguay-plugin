<?php

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Herencia del enlace de inscripción en Ediciones de Seminarios Integrados.
 *
 * La Edición integrada es la fuente de verdad del enlace. Sus Ediciones
 * componentes reciben exactamente el mismo `link_preinscripcion`, pero no se
 * modifica aquí su estado `preinscripcion_habilitada`.
 */
final class FLACSO_Seminario_Integrado_Registration {
    private static bool $syncing = false;

    public static function init(): void {
        add_action('added_post_meta', [self::class, 'on_meta_changed'], 35, 4);
        add_action('updated_post_meta', [self::class, 'on_meta_changed'], 35, 4);
        add_action('save_post_' . FLACSO_Edicion::POST_TYPE, [self::class, 'sync_from_edition'], 50, 1);
    }

    public static function on_meta_changed($meta_id, $object_id, $meta_key, $meta_value): void {
        if (self::$syncing || get_post_type($object_id) !== FLACSO_Edicion::POST_TYPE) {
            return;
        }

        if (!in_array((string) $meta_key, ['link_preinscripcion', 'ediciones_componentes'], true)) {
            return;
        }

        self::sync_from_edition((int) $object_id);
    }

    /**
     * Copia el enlace de una Edición integrada a todas sus Ediciones componentes válidas.
     */
    public static function sync_from_edition(int $edition_id): void {
        if (self::$syncing || get_post_type($edition_id) !== FLACSO_Edicion::POST_TYPE) {
            return;
        }

        $seminar_id = absint(get_post_meta($edition_id, FLACSO_Edicion::META_PARENT_ID, true));
        if (!$seminar_id || !FLACSO_Seminario_Integrado::is_integrated($seminar_id)) {
            return;
        }

        $url = FLACSO_Edicion::sanitize_registration_url(
            (string) get_post_meta($edition_id, 'link_preinscripcion', true)
        );
        if ($url === '') {
            return;
        }

        self::$syncing = true;
        try {
            foreach (FLACSO_Seminario_Integrado::component_edition_ids($edition_id) as $component_edition_id) {
                update_post_meta((int) $component_edition_id, 'link_preinscripcion', $url);
            }
        } finally {
            self::$syncing = false;
        }
    }
}
