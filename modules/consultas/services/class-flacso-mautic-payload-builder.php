<?php

if (!defined('ABSPATH') && !defined('STDIN')) {
    exit;
}

final class FLACSO_Mautic_Payload_Builder {
    public static function build(array $inquiry): array {
        $code = self::slug((string) ($inquiry['offerAbbreviation'] ?? $inquiry['offer_abbreviation'] ?? ''));
        $number = (int) ($inquiry['cohortNumber'] ?? $inquiry['cohort_number'] ?? 0);
        $status = self::status((string) ($inquiry['offerStatus'] ?? $inquiry['offer_status'] ?? 'sin_cohorte'));
        $date = (string) ($inquiry['fechaInicio'] ?? $inquiry['fecha_inicio'] ?? '');
        $cohort_code = $code !== '' && $number > 0 ? $code . '-c' . $number : '';
        $offer_url = trim((string) ($inquiry['programUrl'] ?? $inquiry['urlBase'] ?? ''));
        if ($offer_url !== '' && substr($offer_url, -5) !== 'carta') {
            $offer_url .= 'carta';
        }

        $fields = [
            'email' => trim((string) ($inquiry['email'] ?? '')),
            'firstname' => trim((string) ($inquiry['firstName'] ?? $inquiry['firstname'] ?? '')),
            'lastname' => trim((string) ($inquiry['lastName'] ?? $inquiry['lastname'] ?? '')),
            'flacso_consulta_id' => (string) ($inquiry['id'] ?? $inquiry['consultaId'] ?? ''),
            'flacso_consulta_fecha' => (string) ($inquiry['inquiryAt'] ?? ''),
            'flacso_origen' => 'web-consultas',
            'flacso_tipo' => strtolower((string) ($inquiry['offerType'] ?? $inquiry['offer_type'] ?? 'oferta')) === 'seminario' ? 'seminario' : 'oferta',
            'flacso_oferta_codigo' => $code,
            'flacso_oferta_nombre' => (string) ($inquiry['offerName'] ?? $inquiry['offer_name'] ?? ''),
            'flacso_oferta_articulo' => (string) ($inquiry['offerArticle'] ?? ''),
            'flacso_oferta_url' => $offer_url,
            'flacso_cohorte_codigo' => $cohort_code,
            'flacso_cohorte_numero' => $number > 0 ? $number : '',
            'flacso_cohorte_nombre' => (string) ($inquiry['cohortName'] ?? $inquiry['cohort_name'] ?? ''),
            'flacso_cohorte_estado' => $status,
            'flacso_modalidad' => self::modality((string) ($inquiry['modalidad'] ?? '')),
            'flacso_fecha_inicio' => preg_match('/^\d{4}-\d{2}-\d{2}$/', $date) ? $date : '',
            'flacso_duracion' => (string) ($inquiry['duracion'] ?? ''),
            'flacso_creditos' => (string) ($inquiry['creditos'] ?? ''),
            'flacso_preinscripcion_url' => (string) ($inquiry['preinscripcionUrl'] ?? ''),
            'flacso_consulta_texto' => (string) ($inquiry['message'] ?? $inquiry['consulta'] ?? ''),
            'flacso_pais' => (string) ($inquiry['country'] ?? $inquiry['pais'] ?? ''),
            'flacso_nivel_academico' => (string) ($inquiry['education_level'] ?? $inquiry['educationLevel'] ?? $inquiry['nivel_academico'] ?? ''),
            'flacso_profesion' => (string) ($inquiry['profession'] ?? $inquiry['profesion'] ?? ''),
        ];
        $tags = [];
        if ($code !== '') {
            $tags[] = 'interes-' . $code;
            if ($cohort_code !== '') {
                $tags[] = $cohort_code;
            }
        }
        $tags[] = 'origen-web-consultas';
        return ['fields' => $fields, 'tags' => array_values(array_unique($tags))];
    }

    private static function status(string $value): string {
        $value = strtolower(trim($value));
        return in_array($value, ['abierta', 'cerrada'], true) ? $value : 'sin_cohorte';
    }
    private static function modality(string $value): string {
        $value = strtolower(trim($value));
        return in_array($value, ['virtual', 'presencial', 'hibrida'], true) ? $value : '';
    }
    private static function slug(string $value): string {
        $value = strtolower(trim($value));
        $value = strtr($value, ['á'=>'a','é'=>'e','í'=>'i','ó'=>'o','ú'=>'u','ñ'=>'n','ü'=>'u']);
        return trim((string) preg_replace('/[^a-z0-9]+/', '-', $value), '-');
    }
}
