<?php

if (!defined('ABSPATH')) {
    exit;
}

/** Fachada de lectura para tema, Editor y sistema de preinscripciones. */
final class FLACSO_Academic_Catalog {
    public static function get_offer(int $offer_id): array {
        $offer = FLACSO_Academic_Repository::to_array('ofertas', $offer_id);
        if (!$offer) {
            return [];
        }
        $cohorts = FLACSO_Academic_Repository::list('cohortes', ['parent_id' => $offer_id, 'per_page' => 200]);
        $offer['cohortes'] = $cohorts;
        $offer['cohorte_vigente'] = self::current_item($cohorts);
        return $offer;
    }

    public static function get_seminar(int $seminar_id): array {
        $seminar = FLACSO_Academic_Repository::to_array('seminarios', $seminar_id);
        if (!$seminar) {
            return [];
        }
        $editions = FLACSO_Academic_Repository::list('ediciones', ['parent_id' => $seminar_id, 'per_page' => 200]);
        $seminar['ediciones'] = $editions;
        $seminar['edicion_vigente'] = self::current_item($editions);
        return $seminar;
    }

    public static function registration_catalog(): array {
        $items = [];

        // 1. Ofertas Académicas (Maestrías, Especializaciones, Diplomados, Diplomas)
        $offers = FLACSO_Academic_Repository::list('ofertas', ['per_page' => 300]);
        foreach ($offers as $offer) {
            $offer_id = absint($offer['id'] ?? 0);
            if ($offer_id <= 0) {
                continue;
            }
            $cohorts = FLACSO_Academic_Repository::list('cohortes', ['parent_id' => $offer_id, 'per_page' => 100]);
            $open_cohorts = array_values(array_filter($cohorts, static function (array $c): bool {
                return !empty($c['preinscripcion']['abierta']);
            }));
            if (empty($open_cohorts)) {
                continue;
            }
            $chosen_cohort = self::current_item($open_cohorts) ?? $open_cohorts[0];
            $cohort_id = absint($chosen_cohort['id']);
            $modified = function_exists('get_post_modified_time') ? get_post_modified_time('c', true, $cohort_id) : date('c');

            $items[] = [
                'kind' => 'oferta_academica',
                'oferta' => [
                    'id'              => $offer_id,
                    'titulo'          => (string) ($offer['nombre'] ?? ''),
                    'slug'            => (string) ($offer['slug'] ?? ''),
                    'tipo'            => (string) ($offer['tipo'] ?? ''),
                    'url_informacion' => function_exists('get_permalink') ? (string) get_permalink($offer_id) : '',
                    'correo'          => (string) ($offer['correo'] ?? ''),
                ],
                'cohorte' => [
                    'id'                  => $cohort_id,
                    'nombre'              => (string) ($chosen_cohort['nombre'] ?? ''),
                    'numero'              => max(1, absint($chosen_cohort['numero'] ?? 0)),
                    'anio_inicio'         => absint($chosen_cohort['anio_inicio'] ?: ($chosen_cohort['fecha_inicio'] ? date('Y', strtotime($chosen_cohort['fecha_inicio'])) : 2026)),
                    'fecha_inicio'        => (string) ($chosen_cohort['fecha_inicio'] ?? '') ?: null,
                    'fecha_fin'           => (string) ($chosen_cohort['fecha_fin'] ?? '') ?: null,
                    'estado'              => (string) ($chosen_cohort['estado'] ?? 'planificada'),
                    'link_preinscripcion' => (string) ($chosen_cohort['preinscripcion']['url'] ?? ''),
                    'actualizado'         => $modified ?: date('c'),
                ],
            ];
        }

        // 2. Seminarios
        $seminars = FLACSO_Academic_Repository::list('seminarios', ['per_page' => 300]);
        foreach ($seminars as $seminar) {
            $seminar_id = absint($seminar['id'] ?? 0);
            if ($seminar_id <= 0) {
                continue;
            }
            $editions = FLACSO_Academic_Repository::list('ediciones', ['parent_id' => $seminar_id, 'per_page' => 100]);
            $open_editions = array_values(array_filter($editions, static function (array $e): bool {
                if (isset($e['mostrar_en_formulario']) && $e['mostrar_en_formulario'] === false) {
                    return false;
                }
                return !empty($e['preinscripcion']['abierta']);
            }));
            if (empty($open_editions)) {
                continue;
            }
            $chosen_edition = self::current_item($open_editions) ?? $open_editions[0];
            $edition_id = absint($chosen_edition['id']);
            $modified = function_exists('get_post_modified_time') ? get_post_modified_time('c', true, $edition_id) : date('c');

            $items[] = [
                'kind' => 'seminario',
                'seminario' => [
                    'id'              => $seminar_id,
                    'titulo'          => (string) ($seminar['nombre'] ?? ''),
                    'slug'            => (string) ($seminar['slug'] ?? ''),
                    'url_informacion' => function_exists('get_permalink') ? (string) get_permalink($seminar_id) : '',
                    'correo'          => (string) ($seminar['correo'] ?? ''),
                ],
                'edicion' => [
                    'id'                  => $edition_id,
                    'nombre'              => (string) ($chosen_edition['nombre'] ?? ''),
                    'anio'                => absint($chosen_edition['anio'] ?: ($chosen_edition['fecha_inicio'] ? date('Y', strtotime($chosen_edition['fecha_inicio'])) : 2026)),
                    'fecha_inicio'        => (string) ($chosen_edition['fecha_inicio'] ?? '') ?: null,
                    'fecha_fin'           => (string) ($chosen_edition['fecha_fin'] ?? '') ?: null,
                    'estado'              => (string) ($chosen_edition['estado'] ?? 'planificada'),
                    'link_preinscripcion' => (string) ($chosen_edition['preinscripcion']['url'] ?? ''),
                    'actualizado'         => $modified ?: date('c'),
                ],
            ];
        }

        return [
            'schema_version' => 1,
            'items'          => $items,
        ];
    }

    private static function current_item(array $items): ?array {
        foreach ($items as $item) {
            if (($item['estado'] ?? '') === 'en_curso') {
                return $item;
            }
        }
        foreach ($items as $item) {
            if (($item['estado'] ?? '') === 'planificada') {
                return $item;
            }
        }
        return null;
    }
}

function flacso_get_oferta_academica(int $offer_id): array {
    return FLACSO_Academic_Catalog::get_offer($offer_id);
}

function flacso_get_seminario(int $seminar_id): array {
    return FLACSO_Academic_Catalog::get_seminar($seminar_id);
}
