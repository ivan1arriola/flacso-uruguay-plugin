<?php

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Filtro seguro de lectura para los metadatos derivados de Seminarios Integrados.
 *
 * La implementación original consultaba `seminario_id` para cualquier metadato
 * de una Edición. Esa consulta volvía a disparar `get_post_metadata`, produciendo
 * recursión infinita incluso al leer el propio `seminario_id`.
 *
 * Este filtro sólo interviene en las tres claves realmente derivadas. Para el
 * resto retorna inmediatamente sin efectuar nuevas lecturas de metadatos.
 */
final class FLACSO_Seminario_Integrado_Meta_Safe {
    private const EDITION_DERIVED_KEYS = ['docentes', 'encuentros_sincronicos'];

    public static function filter_derived_metadata($value, $object_id, $meta_key, $single, $meta_type) {
        $object_id = absint($object_id);
        $meta_key = (string) $meta_key;

        if ($object_id <= 0) {
            return $value;
        }

        if ($meta_key === 'creditos') {
            if (get_post_type($object_id) !== FLACSO_Seminario::POST_TYPE) {
                return $value;
            }
            if (!FLACSO_Seminario_Integrado::is_integrated($object_id)) {
                return $value;
            }

            $credits = FLACSO_Seminario_Integrado::credits($object_id);
            return $single ? $credits : [$credits];
        }

        // Crucial: no leer ningún otro meta de la Edición salvo que la clave
        // solicitada sea efectivamente derivada. Así `seminario_id`, `anio`,
        // `estado`, etc. nunca pueden reentrar recursivamente en este filtro.
        if (!in_array($meta_key, self::EDITION_DERIVED_KEYS, true)) {
            return $value;
        }

        if (get_post_type($object_id) !== FLACSO_Edicion::POST_TYPE) {
            return $value;
        }

        $seminar_id = absint(get_post_meta($object_id, FLACSO_Edicion::META_PARENT_ID, true));
        if (!$seminar_id || !FLACSO_Seminario_Integrado::is_integrated($seminar_id)) {
            return $value;
        }

        if ($meta_key === 'docentes') {
            $teachers = FLACSO_Seminario_Integrado::edition_teachers($object_id);
            return $single ? $teachers : [$teachers];
        }

        $meetings = FLACSO_Seminario_Integrado::edition_meetings($object_id);
        return $single ? $meetings : [$meetings];
    }
}
