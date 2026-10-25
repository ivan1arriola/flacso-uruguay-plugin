<?php
/**
 * Servicio de resolución del contexto académico para consultas de ofertas.
 *
 * @package FLACSO_Uruguay
 */

if (!defined('ABSPATH') && !defined('STDIN')) {
    exit;
}

class FLACSO_Inquiry_Context_Service {
    public static function resolve(int $offer_id, array $inquiry_data = []): array {
        return self::from_catalog($offer_id, self::catalog_offer($offer_id), $inquiry_data);
    }

    /**
     * Estado público de una oferta para las campañas Mautic. Usa la misma
     * cohorte que la consulta; "abierta" exige además enlace de preinscripción,
     * como el catálogo de Preinscripciones. Null si la oferta no existe.
     */
    public static function offer_status(int $offer_id): ?array {
        $catalog_data = self::catalog_offer($offer_id);
        if (!$catalog_data) {
            return null;
        }

        $context = self::from_catalog($offer_id, $catalog_data, []);
        $status = (string) $context['offerStatus'];
        if ($status === 'abierta' && empty($context['preinscripcionUrl'])) {
            $status = 'cerrada';
        }

        return [
            'offerWpId' => $offer_id,
            'nombre' => (string) $context['offerName'],
            'tipo' => (string) ($context['offerType'] ?? ''),
            'resumen' => (string) ($catalog_data['resumen'] ?? ''),
            'url' => function_exists('get_permalink') ? (string) get_permalink($offer_id) : '',
            'estado' => $status,
            'cohorte' => $context['cohortWpId'] === null ? null : [
                'id' => $context['cohortWpId'],
                'numero' => $context['cohortNumber'],
                'nombre' => (string) $context['cohortName'],
                'fechaInicio' => (string) $context['startDate'],
                'precision' => $context['startDatePrecision'],
                'modalidad' => $context['modality'],
            ],
            'preinscripcion' => [
                'url' => $context['preinscripcionUrl'],
                'desde' => $context['registrationOpenAt'],
                'hasta' => $context['registrationCloseAt'],
            ],
        ];
    }

    private static function catalog_offer(int $offer_id): array {
        if ($offer_id <= 0 || !class_exists('FLACSO_Academic_Catalog') || !method_exists('FLACSO_Academic_Catalog', 'get_offer')) {
            return [];
        }
        try {
            return FLACSO_Academic_Catalog::get_offer($offer_id);
        } catch (\Throwable $t) {
            return [];
        }
    }

    private static function from_catalog(int $offer_id, array $catalog_data, array $inquiry_data): array {
        $offer_name = trim((string)($inquiry_data['offerName'] ?? $inquiry_data['titulo_posgrado'] ?? ''));
        if ($offer_name === '' && !empty($catalog_data['nombre'])) {
            $offer_name = (string)$catalog_data['nombre'];
        } elseif ($offer_name === '' && $offer_id > 0 && function_exists('get_post')) {
            $post = get_post($offer_id);
            if ($post && !empty($post->post_title)) {
                $offer_name = (string)$post->post_title;
            }
        }

        $offer_type = $inquiry_data['offerType'] ?? $inquiry_data['tipo_oferta'] ?? ($catalog_data['tipo'] ?? null);

        $raw_abbr = $inquiry_data['offerAbbreviation'] ?? ($catalog_data['abreviacion'] ?? null);
        $offer_abbr = null;
        if ($raw_abbr !== null && trim((string)$raw_abbr) !== '') {
            $offer_abbr = class_exists('FLACSO_Oferta_Academica')
                ? FLACSO_Oferta_Academica::normalize_abbreviation((string)$raw_abbr)
                : (function_exists('sanitize_title') ? sanitize_title(strtolower(trim((string)$raw_abbr))) : strtolower(trim((string)$raw_abbr)));
        } else {
            error_log(sprintf('[FLACSO] Oferta ID %d (%s) sin abreviacion configurada al procesar consulta.', $offer_id, $offer_name));
        }

        $inquiry_cohort = $catalog_data['cohorte_consulta'] ?? null;
        if ($inquiry_cohort === null && class_exists('FLACSO_Academic_Catalog') && method_exists('FLACSO_Academic_Catalog', 'get_inquiry_cohort')) {
            try {
                $inquiry_cohort = FLACSO_Academic_Catalog::get_inquiry_cohort($offer_id);
            } catch (\Throwable $t) {
                $inquiry_cohort = null;
            }
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
            $offer_status = !empty($reg_data['abierta']) ? 'abierta' : 'cerrada';
            $reg_open_at = !empty($reg_data['desde']) ? (string)$reg_data['desde'] : null;
            $reg_close_at = !empty($reg_data['hasta']) ? (string)$reg_data['hasta'] : null;
            $preinscripcion_url = !empty($reg_data['url']) ? (string)$reg_data['url'] : null;
        }

        return [
            'offerWpId'            => $offer_id > 0 ? $offer_id : null,
            'offerName'            => $offer_name,
            'offerType'            => $offer_type,
            'offerAbbreviation'    => $offer_abbr,
            'cohortWpId'           => $cohort_wp_id,
            'cohortNumber'         => $cohort_number,
            'cohortName'           => $cohort_name,
            'registrationOpenAt'   => $reg_open_at,
            'registrationCloseAt'  => $reg_close_at,
            'offerStatus'          => $offer_status,
            'preinscripcionUrl'    => $preinscripcion_url,
            'replyToEmail'         => $catalog_data['correo'] ?? null,
            'startDate'            => $inquiry_cohort['fecha_inicio'] ?? ($catalog_data['cohorte_mas_reciente']['fecha_inicio'] ?? ''),
            'startDatePrecision'   => self::normalize_precision((string) ($inquiry_cohort['precision_fecha_inicio'] ?? ($catalog_data['cohorte_mas_reciente']['precision_fecha_inicio'] ?? 'dia'))),
            'modality'             => self::normalize_modality((string) ($inquiry_cohort['modalidad'] ?? ($catalog_data['cohorte_mas_reciente']['modalidad'] ?? ''))),
        ];
    }

    private static function normalize_precision(string $value): string {
        $value = strtolower(trim($value));
        return ['dia'=>'day','mes'=>'month','anio'=>'year','año'=>'year'][$value] ?? (in_array($value, ['day','month','year'], true) ? $value : 'day');
    }

    private static function normalize_modality(string $value): string {
        $value = strtolower(trim($value));
        $value = strtr($value, ['á'=>'a','é'=>'e','í'=>'i','ó'=>'o','ú'=>'u','ü'=>'u']);
        $value = ['hibrido'=>'hibrida','mixta'=>'hibrida','mixto'=>'hibrida'][$value] ?? $value;
        return in_array($value, ['virtual','presencial','semipresencial','hibrida'], true) ? $value : '';
    }
}
