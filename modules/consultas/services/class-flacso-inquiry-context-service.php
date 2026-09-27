<?php
/**
 * Servicio de resolución del contexto académico y snapshots para consultas de ofertas.
 *
 * @package FLACSO_Uruguay
 */

if (!defined('ABSPATH') && !defined('STDIN')) {
    exit;
}

class FLACSO_Inquiry_Context_Service {
    public static function resolve(int $offer_id, array $inquiry_data = []): array {
        $catalog_data = [];
        if ($offer_id > 0 && class_exists('FLACSO_Academic_Catalog') && method_exists('FLACSO_Academic_Catalog', 'get_offer')) {
            try {
                $catalog_data = FLACSO_Academic_Catalog::get_offer($offer_id);
            } catch (\Throwable $t) {
                $catalog_data = [];
            }
        }

        // 1. Nombre de la oferta
        $offer_name = trim((string)($inquiry_data['offerName'] ?? $inquiry_data['titulo_posgrado'] ?? ''));
        if ($offer_name === '' && !empty($catalog_data['nombre'])) {
            $offer_name = (string)$catalog_data['nombre'];
        } elseif ($offer_name === '' && function_exists('get_post')) {
            $post = get_post($offer_id);
            if ($post && !empty($post->post_title)) {
                $offer_name = (string)$post->post_title;
            }
        }

        // 2. Tipo de oferta
        $offer_type = $inquiry_data['offerType'] ?? $inquiry_data['tipo_oferta'] ?? ($catalog_data['tipo'] ?? null);

        // 3. Abreviación canónica
        $raw_abbr = $inquiry_data['offerAbbreviation'] ?? ($catalog_data['abreviacion'] ?? null);
        $offer_abbr = null;
        if ($raw_abbr !== null && trim((string)$raw_abbr) !== '') {
            $offer_abbr = class_exists('FLACSO_Oferta_Academica')
                ? FLACSO_Oferta_Academica::normalize_abbreviation((string)$raw_abbr)
                : (function_exists('sanitize_title') ? sanitize_title(strtolower(trim((string)$raw_abbr))) : strtolower(trim((string)$raw_abbr)));
        } else {
            error_log(sprintf('[FLACSO] Oferta ID %d (%s) sin abreviacion configurada al procesar consulta.', $offer_id, $offer_name));
        }

        // 4. Cohorte de consulta
        $inquiry_cohort = $catalog_data['cohorte_consulta'] ?? null;
        if ($inquiry_cohort === null && class_exists('FLACSO_Academic_Catalog') && method_exists('FLACSO_Academic_Catalog', 'get_inquiry_cohort')) {
            $inquiry_cohort = FLACSO_Academic_Catalog::get_inquiry_cohort($offer_id);
        }

        $cohort_wp_id = null;
        $cohort_number = null;
        $cohort_name = null;
        $reg_open_at = null;
        $reg_close_at = null;
        $preinscripcion_url = null;
        $offer_status = 'sin_cohorte';

        if (!empty($inquiry_cohort)) {
            $cohort_wp_id = (function_exists('absint') ? absint($inquiry_cohort['id'] ?? 0) : abs((int)($inquiry_cohort['id'] ?? 0))) ?: null;
            $cohort_number = (function_exists('absint') ? absint($inquiry_cohort['numero'] ?? 0) : abs((int)($inquiry_cohort['numero'] ?? 0))) ?: null;
            $cohort_name = (string)($inquiry_cohort['nombre'] ?? '');
            
            $reg_data = $inquiry_cohort['preinscripcion'] ?? [];
            $is_open = !empty($reg_data['abierta']);
            $offer_status = $is_open ? 'abierta' : 'cerrada';
            
            $reg_open_at = !empty($reg_data['desde']) ? (string)$reg_data['desde'] : null;
            $reg_close_at = !empty($reg_data['hasta']) ? (string)$reg_data['hasta'] : null;
            $preinscripcion_url = !empty($reg_data['url']) ? (string)$reg_data['url'] : null;
        }

        return [
            'offerWpId'           => $offer_id > 0 ? $offer_id : null,
            'offerName'           => $offer_name,
            'offerType'           => $offer_type,
            'offerAbbreviation'   => $offer_abbr,
            'cohortWpId'          => $cohort_wp_id,
            'cohortNumber'        => $cohort_number,
            'cohortName'          => $cohort_name,
            'registrationOpenAt'  => $reg_open_at,
            'registrationCloseAt' => $reg_close_at,
            'offerStatus'         => $offer_status,
            'preinscripcionUrl'   => $preinscripcion_url,
            'replyToEmail'        => $catalog_data['correo'] ?? null,
            'startValue'          => $inquiry_cohort['fecha_inicio'] ?? ($catalog_data['cohorte_vigente']['fecha_inicio'] ?? ''),
            'startPrecision'      => $inquiry_cohort['precision_fecha_inicio'] ?? ($catalog_data['cohorte_vigente']['precision_fecha_inicio'] ?? 'dia'),
            'modalityLabel'       => $inquiry_cohort['modalidad'] ?? ($catalog_data['cohorte_vigente']['modalidad'] ?? ''),
        ];
    }
}
