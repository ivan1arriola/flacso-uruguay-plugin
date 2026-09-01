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
        $current_edition = self::current_item($editions);

        // Si es un seminario integrado, hereda transitivamente lo de las ediciones de sus componentes
        $raw_components = is_array($seminar['componentes'] ?? null) ? $seminar['componentes'] : [];
        if (!empty($raw_components)) {
            $transitive_docentes = [];
            $transitive_encuentros = [];
            $min_start = '';
            $max_end = '';

            foreach ($raw_components as $comp) {
                $comp_id = is_array($comp) ? absint($comp['seminario_id'] ?? 0) : absint($comp);
                if ($comp_id <= 0) {
                    continue;
                }
                $comp_editions = FLACSO_Academic_Repository::list('ediciones', ['parent_id' => $comp_id, 'per_page' => 50]);
                $comp_current = self::current_item($comp_editions);
                if ($comp_current) {
                    if (!empty($comp_current['docentes']) && is_array($comp_current['docentes'])) {
                        $transitive_docentes = array_merge($transitive_docentes, array_map('absint', $comp_current['docentes']));
                    }
                    if (!empty($comp_current['encuentros_sincronicos']) && is_array($comp_current['encuentros_sincronicos'])) {
                        $transitive_encuentros = array_merge($transitive_encuentros, $comp_current['encuentros_sincronicos']);
                    }
                    $c_start = (string) ($comp_current['fecha_inicio'] ?? '');
                    $c_end = (string) ($comp_current['fecha_fin'] ?? '');
                    if ($c_start !== '' && ($min_start === '' || $c_start < $min_start)) {
                        $min_start = $c_start;
                    }
                    if ($c_end !== '' && ($max_end === '' || $c_end > $max_end)) {
                        $max_end = $c_end;
                    }
                }
            }

            if ($current_edition) {
                $current_edition['docentes'] = array_values(array_unique(array_filter(array_merge(
                    is_array($current_edition['docentes'] ?? null) ? array_map('absint', $current_edition['docentes']) : [],
                    $transitive_docentes
                ))));
                if (empty($current_edition['encuentros_sincronicos'])) {
                    $current_edition['encuentros_sincronicos'] = $transitive_encuentros;
                }
                if (empty($current_edition['fecha_inicio']) && $min_start !== '') {
                    $current_edition['fecha_inicio'] = $min_start;
                }
                if (empty($current_edition['fecha_fin']) && $max_end !== '') {
                    $current_edition['fecha_fin'] = $max_end;
                }
            }
        }

        $seminar['ediciones'] = $editions;
        $seminar['edicion_vigente'] = $current_edition;
        return $seminar;
    }

    public static function registration_catalog(): array {
        $items = [];

        // 1. Ofertas Académicas (Doctorados, Maestrías, Especializaciones, Diplomados, Diplomas)
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
        if (empty($items)) {
            return null;
        }

        $today = function_exists('current_time') ? current_time('Y-m-d') : date('Y-m-d');

        // 1. Edición con preinscripción abierta
        foreach ($items as $item) {
            if (!empty($item['preinscripcion']['abierta'])) {
                return $item;
            }
        }

        // 2. Edición 'en_curso'
        foreach ($items as $item) {
            if (($item['estado'] ?? '') === 'en_curso') {
                return $item;
            }
        }

        // 3. Edición 'planificada' futura más próxima a iniciar (fecha_inicio >= hoy)
        $upcoming_planificadas = [];
        foreach ($items as $item) {
            if (($item['estado'] ?? '') === 'planificada') {
                $start = (string) ($item['fecha_inicio'] ?? '');
                if ($start !== '' && $start >= $today) {
                    $upcoming_planificadas[] = $item;
                }
            }
        }
        if (!empty($upcoming_planificadas)) {
            usort($upcoming_planificadas, static function (array $a, array $b): int {
                return strcmp((string) ($a['fecha_inicio'] ?? ''), (string) ($b['fecha_inicio'] ?? ''));
            });
            return $upcoming_planificadas[0];
        }

        // 4. Cualquier edición 'planificada'
        foreach ($items as $item) {
            if (($item['estado'] ?? '') === 'planificada') {
                return $item;
            }
        }

        // 5. Fallback al primer elemento
        return $items[0] ?? null;
    }
}

function flacso_get_oferta_academica(int $offer_id): array {
    return FLACSO_Academic_Catalog::get_offer($offer_id);
}

function flacso_get_seminario(int $seminar_id): array {
    return FLACSO_Academic_Catalog::get_seminar($seminar_id);
}
