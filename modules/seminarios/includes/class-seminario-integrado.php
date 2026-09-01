<?php

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Reglas derivadas para Seminarios Integrados.
 *
 * Un Seminario es integrado cuando `componentes` contiene al menos un Seminario.
 * Los datos derivados nunca se persisten como una copia en el integrado:
 * - créditos = suma transitiva de créditos de los Seminarios componentes;
 * - docentes de una Edición integrada = unión de docentes de sus Ediciones componentes;
 * - encuentros sincrónicos = unión de encuentros de sus Ediciones componentes.
 */
final class FLACSO_Seminario_Integrado {
    public static function component_seminar_ids(int $seminar_id): array {
        $raw = get_post_meta($seminar_id, 'componentes', true);
        if (!is_array($raw)) {
            return [];
        }

        $ids = [];
        foreach ($raw as $component) {
            $id = is_array($component)
                ? absint($component['seminario_id'] ?? 0)
                : absint($component);
            if ($id > 0 && $id !== $seminar_id && get_post_type($id) === FLACSO_Seminario::POST_TYPE) {
                $ids[$id] = $id;
            }
        }
        return array_values($ids);
    }

    public static function is_integrated(int $seminar_id): bool {
        return !empty(self::component_seminar_ids($seminar_id));
    }

    public static function credits(int $seminar_id, array $visited = []): float {
        if ($seminar_id <= 0 || isset($visited[$seminar_id])) {
            return 0.0;
        }
        $visited[$seminar_id] = true;

        $components = self::component_seminar_ids($seminar_id);
        if (empty($components)) {
            return max(0.0, (float) get_post_meta($seminar_id, 'creditos', true));
        }

        $total = 0.0;
        foreach ($components as $component_id) {
            $total += self::credits($component_id, $visited);
        }
        return $total;
    }

    /**
     * Devuelve las Ediciones componentes válidas de una Edición integrada.
     * Sólo acepta Ediciones cuyo Seminario padre sea un componente directo del Seminario integrado.
     * Como máximo se conserva una Edición por Seminario componente.
     */
    public static function component_edition_ids(int $edition_id): array {
        if (get_post_type($edition_id) !== FLACSO_Edicion::POST_TYPE) {
            return [];
        }

        $seminar_id = absint(get_post_meta($edition_id, FLACSO_Edicion::META_PARENT_ID, true));
        $allowed_seminars = array_fill_keys(self::component_seminar_ids($seminar_id), true);
        if (empty($allowed_seminars)) {
            return [];
        }

        $raw = get_post_meta($edition_id, 'ediciones_componentes', true);
        if (!is_array($raw)) {
            return [];
        }

        $result = [];
        $used_parent = [];
        foreach ($raw as $component) {
            $component_edition_id = is_array($component)
                ? absint($component['edicion_id'] ?? 0)
                : absint($component);
            if ($component_edition_id <= 0 || $component_edition_id === $edition_id) {
                continue;
            }
            if (get_post_type($component_edition_id) !== FLACSO_Edicion::POST_TYPE) {
                continue;
            }

            $component_parent = absint(get_post_meta($component_edition_id, FLACSO_Edicion::META_PARENT_ID, true));
            if (!isset($allowed_seminars[$component_parent]) || isset($used_parent[$component_parent])) {
                continue;
            }

            $result[] = $component_edition_id;
            $used_parent[$component_parent] = true;
        }
        return $result;
    }

    public static function edition_teachers(int $edition_id, array $visited = []): array {
        if ($edition_id <= 0 || isset($visited[$edition_id])) {
            return [];
        }
        $visited[$edition_id] = true;

        $seminar_id = absint(get_post_meta($edition_id, FLACSO_Edicion::META_PARENT_ID, true));
        if (!self::is_integrated($seminar_id)) {
            $teachers = get_post_meta($edition_id, 'docentes', true);
            return self::unique_positive_ids(is_array($teachers) ? $teachers : []);
        }

        $teachers = [];
        foreach (self::component_edition_ids($edition_id) as $component_edition_id) {
            $teachers = array_merge($teachers, self::edition_teachers($component_edition_id, $visited));
        }
        return self::unique_positive_ids($teachers);
    }

    public static function edition_meetings(int $edition_id, array $visited = []): array {
        if ($edition_id <= 0 || isset($visited[$edition_id])) {
            return [];
        }
        $visited[$edition_id] = true;

        $seminar_id = absint(get_post_meta($edition_id, FLACSO_Edicion::META_PARENT_ID, true));
        if (!self::is_integrated($seminar_id)) {
            $meetings = get_post_meta($edition_id, 'encuentros_sincronicos', true);
            return is_array($meetings) ? FLACSO_Edicion::sanitize_meetings($meetings) : [];
        }

        $meetings = [];
        foreach (self::component_edition_ids($edition_id) as $component_edition_id) {
            foreach (self::edition_meetings($component_edition_id, $visited) as $meeting) {
                $key = implode('|', [
                    (string) ($meeting['fecha'] ?? ''),
                    (string) ($meeting['hora_inicio'] ?? ''),
                    (string) ($meeting['hora_fin'] ?? ''),
                    (string) ($meeting['zona_horaria'] ?? 'America/Montevideo'),
                ]);
                $meetings[$key] = $meeting;
            }
        }

        $meetings = array_values($meetings);
        usort($meetings, static function (array $a, array $b): int {
            $ka = ($a['fecha'] ?? '') . ' ' . ($a['hora_inicio'] ?? '');
            $kb = ($b['fecha'] ?? '') . ' ' . ($b['hora_inicio'] ?? '');
            return strcmp($ka, $kb);
        });
        return $meetings;
    }

    private static function unique_positive_ids(array $ids): array {
        $result = [];
        foreach ($ids as $id) {
            $id = absint($id);
            if ($id > 0) {
                $result[$id] = $id;
            }
        }
        return array_values($result);
    }
}
