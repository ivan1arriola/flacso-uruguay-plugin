<?php
/**
 * Snapshot canónico e inmutable de una consulta académica.
 *
 * @package FLACSO_Uruguay
 */

if (!defined('ABSPATH') && !defined('STDIN')) {
    exit;
}

if (!class_exists('FLACSO_Inquiry_Tag_Factory')) {
    require_once __DIR__ . '/class-flacso-inquiry-tag-factory.php';
}

final class FLACSO_Inquiry_Snapshot {
    public const SCHEMA_VERSION = '1';

    public static function from_offer(array $form, array $context, string $consulta_id): array {
        $snapshot = self::base('offer', $form, $consulta_id);

        $snapshot['academic'] = [
            'wpId'               => self::nullable_int($context['offerWpId'] ?? $form['offerWpId'] ?? $form['id_pagina'] ?? null),
            'code'               => self::slug((string) ($context['offerAbbreviation'] ?? $form['offerAbbreviation'] ?? '')),
            'name'               => trim((string) ($context['offerName'] ?? $form['offerName'] ?? $form['titulo_posgrado'] ?? '')),
            'article'            => trim((string) ($context['offerArticle'] ?? $form['offerArticle'] ?? '')),
            'type'               => trim((string) ($context['offerType'] ?? $form['offerType'] ?? 'oferta')),
            'status'             => self::status((string) ($form['offerStatus'] ?? $context['offerStatus'] ?? 'sin_cohorte')),
            'cohortWpId'         => self::nullable_int($context['cohortWpId'] ?? null),
            'cohortNumber'       => self::nullable_int($context['cohortNumber'] ?? null),
            'cohortName'         => trim((string) ($context['cohortName'] ?? '')),
            'registrationOpenAt' => self::nullable_string($context['registrationOpenAt'] ?? null),
            'registrationCloseAt'=> self::nullable_string($context['registrationCloseAt'] ?? null),
            'startDate'          => trim((string) ($context['startDate'] ?? $form['startDate'] ?? '')),
            'startDatePrecision' => self::precision((string) ($context['startDatePrecision'] ?? $form['startDatePrecision'] ?? 'day')),
            'modality'           => self::modality((string) ($context['modality'] ?? $form['modality'] ?? '')),
            'duration'           => trim((string) ($context['duration'] ?? $form['duration'] ?? $form['duracion'] ?? '')),
            'credits'            => trim((string) ($context['credits'] ?? $form['credits'] ?? $form['creditos'] ?? '')),
        ];

        $snapshot['links'] = self::links($form, $context);
        $snapshot['replyToEmail'] = self::nullable_string($form['replyToEmail'] ?? $form['reply_to'] ?? $context['replyToEmail'] ?? null);
        $snapshot['tags'] = FLACSO_Inquiry_Tag_Factory::from_snapshot($snapshot);

        return $snapshot;
    }

    public static function from_seminar(array $form, array $context, string $consulta_id): array {
        $snapshot = self::base('seminar', $form, $consulta_id);

        $name = trim((string) ($context['seminarName'] ?? $form['seminarName'] ?? $form['seminario_titulo'] ?? $form['titulo'] ?? ''));
        $raw_code = (string) ($context['seminarAbbreviation'] ?? $form['seminarAbbreviation'] ?? $form['offerAbbreviation'] ?? $name);

        $snapshot['academic'] = [
            'wpId'               => self::nullable_int($context['seminarWpId'] ?? $form['seminarWpId'] ?? $form['seminario_id'] ?? $form['id_pagina'] ?? null),
            'code'               => self::slug($raw_code),
            'name'               => $name,
            'article'            => trim((string) ($context['seminarArticle'] ?? $form['seminarArticle'] ?? '')),
            'type'               => 'seminario',
            'status'             => 'sin_cohorte',
            'cohortWpId'         => null,
            'cohortNumber'       => null,
            'cohortName'         => '',
            'registrationOpenAt' => null,
            'registrationCloseAt'=> null,
            'startDate'          => trim((string) ($context['startDate'] ?? $form['startDate'] ?? '')),
            'startDatePrecision' => self::precision((string) ($context['startDatePrecision'] ?? $form['startDatePrecision'] ?? 'day')),
            'modality'           => self::modality((string) ($context['modality'] ?? $form['modality'] ?? '')),
            'duration'           => trim((string) ($context['duration'] ?? $form['duration'] ?? $form['duracion'] ?? '')),
            'credits'            => trim((string) ($context['credits'] ?? $form['credits'] ?? $form['creditos'] ?? '')),
        ];

        $snapshot['links'] = self::links($form, $context);
        $snapshot['replyToEmail'] = self::nullable_string($form['replyToEmail'] ?? $form['reply_to'] ?? $context['replyToEmail'] ?? null);
        $snapshot['tags'] = FLACSO_Inquiry_Tag_Factory::from_snapshot($snapshot);

        return $snapshot;
    }

    public static function delivery_tokens(array $snapshot): array {
        self::assert_snapshot($snapshot);

        $recipient = $snapshot['recipient'];
        $academic = $snapshot['academic'];
        $links = $snapshot['links'];

        $first = trim((string) ($recipient['firstName'] ?? ''));
        $last = trim((string) ($recipient['lastName'] ?? ''));
        $full = trim((string) ($recipient['fullName'] ?? ''));
        if ($full === '') {
            $full = trim($first . ' ' . $last);
        }

        $start = self::format_start_date(
            (string) ($academic['startDate'] ?? ''),
            (string) ($academic['startDatePrecision'] ?? 'day')
        );

        $modality = self::humanize_modality((string) ($academic['modality'] ?? ''));
        $cohort_number = $academic['cohortNumber'] ?? null;

        return [
            '{nombre}'                              => $first,
            '{apellido}'                            => $last,
            '{nombre_completo}'                     => $full,
            '{correo}'                              => (string) ($recipient['email'] ?? ''),
            '{pais}'                                => self::profile_value($snapshot, 'country'),
            '{profesion}'                           => self::profile_value($snapshot, 'profession'),
            '{nivel_academico}'                     => self::profile_value($snapshot, 'educationLevel'),
            '{programa}'                            => (string) ($academic['name'] ?? ''),
            '{oferta_academica_nombre}'             => (string) ($academic['name'] ?? ''),
            '{oferta_academica_articulo}'           => (string) ($academic['article'] ?? ''),
            '{cohorte_nombre}'                      => (string) ($academic['cohortName'] ?? ''),
            '{cohorte_numero}'                      => $cohort_number !== null ? (string) $cohort_number : '',
            '{fecha_inicio}'                        => $start,
            '{oferta_academica_fecha_inicio}'       => $start,
            '{modalidad}'                           => $modality,
            '{oferta_academica_modalidad}'          => $modality,
            '{oferta_academica_url}'                => (string) ($links['programUrl'] ?? ''),
            '{url_oferta_academica}'                => (string) ($links['programUrl'] ?? ''),
            '{url_preinscripcion}'                  => (string) ($links['preinscripcionUrl'] ?? ''),
            '{oferta_academica_url_preinscripcion}' => (string) ($links['preinscripcionUrl'] ?? ''),
            '{link_preinscripcion}'                 => (string) ($links['preinscripcionUrl'] ?? ''),
            '{url_carta}'                           => (string) ($links['cartaUrl'] ?? ''),
        ];
    }

    public static function assert_snapshot(array $snapshot): void {
        if (($snapshot['schemaVersion'] ?? '') !== self::SCHEMA_VERSION) {
            throw new \InvalidArgumentException('Versión de InquirySnapshot inválida.');
        }
        if (empty($snapshot['consultaId']) || empty($snapshot['inquiryType'])) {
            throw new \InvalidArgumentException('InquirySnapshot incompleto.');
        }
        if (empty($snapshot['recipient']) || !is_array($snapshot['recipient'])) {
            throw new \InvalidArgumentException('InquirySnapshot sin destinatario.');
        }
        if (empty($snapshot['academic']) || !is_array($snapshot['academic'])) {
            throw new \InvalidArgumentException('InquirySnapshot sin contexto académico.');
        }
    }

    private static function base(string $type, array $form, string $consulta_id): array {
        $first = trim((string) ($form['firstName'] ?? $form['nombre'] ?? ''));
        $last = trim((string) ($form['lastName'] ?? $form['apellido'] ?? ''));
        $full = trim((string) ($form['fullName'] ?? $form['nombre_apellido'] ?? ''));
        if ($full === '') {
            $full = trim($first . ' ' . $last);
        }
        if ($first === '' && $full !== '') {
            $parts = preg_split('/\s+/', $full);
            $first = (string) ($parts[0] ?? '');
        }

        return [
            'schemaVersion' => self::SCHEMA_VERSION,
            'consultaId'    => trim($consulta_id),
            'inquiryType'   => $type,
            'inquiryAt'     => self::utc((string) ($form['inquiryAt'] ?? $form['fecha_envio'] ?? gmdate('c'))),
            'source'        => 'web-consultas',
            'recipient'     => [
                'email'     => strtolower(trim((string) ($form['email'] ?? $form['correo'] ?? ''))),
                'firstName' => $first,
                'lastName'  => $last,
                'fullName'  => $full,
            ],
            'profile'       => [
                'country'        => trim((string) ($form['country'] ?? $form['pais'] ?? '')),
                'profession'     => trim((string) ($form['profession'] ?? $form['profesion'] ?? '')),
                'educationLevel' => trim((string) ($form['educationLevel'] ?? $form['nivel_academico'] ?? '')),
                'phone'          => trim((string) ($form['phone'] ?? $form['telefono'] ?? '')),
            ],
            'campaign'      => [
                'provider'   => self::nullable_string($form['campaignProvider'] ?? $form['campaign_provider'] ?? $form['utm_provider'] ?? null),
                'source'     => self::nullable_string($form['campaignSource'] ?? $form['campaign_source'] ?? $form['utm_source'] ?? null),
                'medium'     => self::nullable_string($form['campaignMedium'] ?? $form['campaign_medium'] ?? $form['utm_medium'] ?? null),
                'name'       => self::nullable_string($form['campaignName'] ?? $form['campaign_name'] ?? $form['utm_campaign'] ?? null),
                'externalId' => self::nullable_string($form['campaignExternalId'] ?? $form['campaign_external_id'] ?? $form['utm_id'] ?? null),
                'content'    => self::nullable_string($form['campaignContent'] ?? $form['campaign_content'] ?? $form['utm_content'] ?? null),
                'term'       => self::nullable_string($form['campaignTerm'] ?? $form['campaign_term'] ?? $form['utm_term'] ?? null),
            ],
        ];
    }

    private static function links(array $form, array $context): array {
        return [
            'programUrl'        => trim((string) ($form['programUrl'] ?? $form['urlBase'] ?? $form['url_base'] ?? $context['programUrl'] ?? '')),
            'cartaUrl'          => trim((string) ($form['cartaUrl'] ?? $form['url_carta'] ?? $context['cartaUrl'] ?? '')),
            'preinscripcionUrl' => trim((string) ($form['preinscripcionUrl'] ?? $form['url_preinscripcion'] ?? $context['preinscripcionUrl'] ?? '')),
        ];
    }

    private static function profile_value(array $snapshot, string $key): string {
        $value = trim((string) ($snapshot['profile'][$key] ?? ''));
        return $value !== '' ? $value : 'Prefiere no responder';
    }

    private static function nullable_int($value): ?int {
        if ($value === null || $value === '') {
            return null;
        }
        $number = (int) $value;
        return $number > 0 ? $number : null;
    }

    private static function nullable_string($value): ?string {
        if ($value === null) {
            return null;
        }
        $value = trim((string) $value);
        return $value === '' ? null : $value;
    }

    private static function status(string $value): string {
        $value = strtolower(trim($value));
        return in_array($value, ['abierta', 'cerrada'], true) ? $value : 'sin_cohorte';
    }

    private static function precision(string $value): string {
        $value = strtolower(trim($value));
        $map = ['dia' => 'day', 'mes' => 'month', 'anio' => 'year', 'año' => 'year'];
        $value = $map[$value] ?? $value;
        return in_array($value, ['day', 'month', 'year'], true) ? $value : 'day';
    }

    private static function modality(string $value): string {
        $value = self::slug($value);
        $map = [
            'hibrido' => 'hibrida',
            'mixta' => 'hibrida',
            'mixto' => 'hibrida',
        ];
        $value = $map[$value] ?? $value;
        return in_array($value, ['virtual', 'presencial', 'semipresencial', 'hibrida'], true) ? $value : '';
    }

    private static function slug(string $value): string {
        $value = strtolower(trim($value));
        $value = strtr($value, ['á'=>'a','é'=>'e','í'=>'i','ó'=>'o','ú'=>'u','ñ'=>'n','ü'=>'u']);
        return trim((string) preg_replace('/[^a-z0-9]+/', '-', $value), '-');
    }

    private static function utc(string $value): string {
        $ts = strtotime($value);
        return $ts === false ? gmdate('c') : gmdate('c', $ts);
    }

    private static function format_start_date(string $value, string $precision): string {
        $value = trim($value);
        if ($value === '') {
            return 'a confirmar';
        }

        if ($precision === 'year' && preg_match('/^(\d{4})/', $value, $m)) {
            return $m[1];
        }

        if (preg_match('/^(\d{4})-(\d{2})(?:-(\d{2}))?/', $value, $m)) {
            $year = (int) $m[1];
            $month = (int) $m[2];
            $day = isset($m[3]) ? (int) $m[3] : 1;
            $months = [1=>'enero',2=>'febrero',3=>'marzo',4=>'abril',5=>'mayo',6=>'junio',7=>'julio',8=>'agosto',9=>'septiembre',10=>'octubre',11=>'noviembre',12=>'diciembre'];
            if (isset($months[$month])) {
                if ($precision === 'month') {
                    return ucfirst($months[$month] . ' de ' . $year);
                }
                if (checkdate($month, $day, $year)) {
                    return $day . ' de ' . $months[$month] . ' de ' . $year;
                }
            }
        }

        return $value;
    }

    private static function humanize_modality(string $value): string {
        $labels = [
            'virtual' => 'Virtual',
            'presencial' => 'Presencial',
            'semipresencial' => 'Semipresencial',
            'hibrida' => 'Híbrida',
        ];
        return $labels[$value] ?? ($value !== '' ? $value : 'a confirmar');
    }
}
