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
        $editions = FLACSO_Academic_Repository::list('ediciones-seminario', ['parent_id' => $seminar_id, 'per_page' => 200]);
        $seminar['ediciones'] = $editions;
        $seminar['edicion_vigente'] = self::current_item($editions);
        return $seminar;
    }

    public static function registration_catalog(): array {
        $items = [];
        foreach (FLACSO_Academic_Repository::list('cohortes', ['per_page' => 200]) as $cohort) {
            if (empty($cohort['preinscripcion']['abierta']) || empty($cohort['link_preinscripcion'])) {
                continue;
            }
            $offer = FLACSO_Academic_Repository::to_array('ofertas', absint($cohort['oferta_academica_id']));
            if ($offer) {
                $items[] = ['kind' => 'oferta_academica', 'oferta' => $offer, 'cohorte' => $cohort];
            }
        }
        foreach (FLACSO_Academic_Repository::list('ediciones-seminario', ['per_page' => 200]) as $edition) {
            if (empty($edition['mostrar_en_formulario']) || empty($edition['preinscripcion']['abierta']) || empty($edition['link_preinscripcion'])) {
                continue;
            }
            $seminar = FLACSO_Academic_Repository::to_array('seminarios', absint($edition['seminario_id']));
            if ($seminar) {
                $items[] = ['kind' => 'seminario', 'seminario' => $seminar, 'edicion' => $edition];
            }
        }
        return $items;
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
