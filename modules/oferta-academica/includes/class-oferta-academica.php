<?php

if (!defined('ABSPATH')) {
    exit;
}

/** Definicion estable de una carrera o trayecto academico. */
final class FLACSO_Oferta_Academica {
    public const POST_TYPE = 'oferta-academica';
    public const TYPE_TAXONOMY = 'tipo-oferta-academica';

    public const TIPO_DOCTORADO = 'doctorado';
    public const TIPO_MAESTRIA = 'maestria';
    public const TIPO_ESPECIALIZACION = 'especializacion';
    public const TIPO_DIPLOMADO = 'diplomado';
    public const TIPO_DIPLOMA = 'diploma';

    public const META_PROGRAM_ID = 'programa_academico_id';
    public const META_SEMINARIOS = 'seminarios';
    public const META_PRESENTATION_COLORS = 'colores_presentacion';

    /** @return array<string,string> */
    public static function tipos(): array {
        return [
            self::TIPO_DOCTORADO => 'Doctorados',
            self::TIPO_MAESTRIA => 'Maestrías',
            self::TIPO_ESPECIALIZACION => 'Especializaciones',
            self::TIPO_DIPLOMADO => 'Diplomados',
            self::TIPO_DIPLOMA => 'Diplomas',
        ];
    }

    /** @return array<string,string> */
    public static function segmentos_url(): array {
        return [
            self::TIPO_DOCTORADO => 'doctorados',
            self::TIPO_MAESTRIA => 'maestrias',
            self::TIPO_ESPECIALIZACION => 'especializaciones',
            self::TIPO_DIPLOMADO => 'diplomados',
            self::TIPO_DIPLOMA => 'diplomas',
        ];
    }

    public static function tipo_valido($tipo): bool {
        return isset(self::tipos()[sanitize_key((string) $tipo)]);
    }

    public static function get_tipo(int $oferta_id): string {
        $terms = wp_get_object_terms($oferta_id, self::TYPE_TAXONOMY);
        if (is_wp_error($terms) || empty($terms)) {
            return '';
        }
        $tipo = sanitize_key((string) $terms[0]->slug);
        return self::tipo_valido($tipo) ? $tipo : '';
    }

    public static function normalize_abbreviation(string $raw): string {
        return sanitize_text_field(trim($raw));
    }

    public static function is_abbreviation_available(string $abbr, int $exclude_post_id = 0): bool {
        $norm = self::normalize_abbreviation($abbr);
        if ($norm === '') {
            return true;
        }
        if (!function_exists('get_posts')) {
            return true;
        }
        $args = [
            'post_type'      => self::POST_TYPE,
            'post_status'    => ['publish', 'draft', 'pending', 'private'],
            'posts_per_page' => 1,
            'fields'         => 'ids',
            'meta_query'     => [
                [
                    'key'     => 'abreviacion',
                    'value'   => $norm,
                    'compare' => '=',
                ],
            ],
        ];
        if ($exclude_post_id > 0) {
            $args['exclude'] = [$exclude_post_id];
        }
        $existing = get_posts($args);
        return empty($existing);
    }

    public static function register_meta(): void {
        $definitions = [
            self::META_PROGRAM_ID => ['type' => 'integer', 'sanitize_callback' => 'absint'],
            'abreviacion' => ['type' => 'string', 'sanitize_callback' => [self::class, 'normalize_abbreviation']],
            'correo' => ['type' => 'string', 'sanitize_callback' => 'sanitize_email'],
            'correo_oferta' => ['type' => 'array', 'sanitize_callback' => [self::class, 'sanitize_offer_emails']],
            'presentacion' => ['type' => 'string', 'sanitize_callback' => 'wp_kses_post'],
            'composicion_academica' => ['type' => 'array', 'sanitize_callback' => [self::class, 'sanitize_sections']],
            'duracion_meses' => ['type' => 'number', 'sanitize_callback' => [self::class, 'sanitize_number']],
            'carga_horaria' => ['type' => 'number', 'sanitize_callback' => [self::class, 'sanitize_number']],
            'creditos' => ['type' => 'number', 'sanitize_callback' => [self::class, 'sanitize_number']],
            'perfil_ingreso_html' => ['type' => 'string', 'sanitize_callback' => 'wp_kses_post'],
            'requisitos_ingreso_html' => ['type' => 'string', 'sanitize_callback' => 'wp_kses_post'],
            'perfil_egreso_html' => ['type' => 'string', 'sanitize_callback' => 'wp_kses_post'],
            'requisitos_egreso_html' => ['type' => 'string', 'sanitize_callback' => 'wp_kses_post'],
            'titulos_certificaciones_html' => ['type' => 'string', 'sanitize_callback' => 'wp_kses_post'],
            'menciones' => ['type' => 'array', 'sanitize_callback' => [self::class, 'sanitize_text_list']],
            'orientaciones' => ['type' => 'array', 'sanitize_callback' => [self::class, 'sanitize_text_list']],
            'titulos_intermedios' => ['type' => 'array', 'sanitize_callback' => [self::class, 'sanitize_intermediate_titles_meta']],
            'malla_curricular' => ['type' => 'string', 'sanitize_callback' => 'esc_url_raw'],
            'documentos' => ['type' => 'object', 'sanitize_callback' => [self::class, 'sanitize_documents']],
            'equipos' => ['type' => 'array', 'sanitize_callback' => [self::class, 'sanitize_teams']],
            'reconocido_mec' => ['type' => 'boolean', 'sanitize_callback' => 'rest_sanitize_boolean'],
            'reconocimiento_internacional' => ['type' => 'boolean', 'sanitize_callback' => 'rest_sanitize_boolean'],
            'reconocimiento_red_flacso' => ['type' => 'boolean', 'sanitize_callback' => 'rest_sanitize_boolean'],
            'convenio_iin_oea' => ['type' => 'boolean', 'sanitize_callback' => 'rest_sanitize_boolean'],
            'mostrar_costos_envio' => ['type' => 'boolean', 'sanitize_callback' => 'rest_sanitize_boolean'],
            'mostrar_expedicion_titulo' => ['type' => 'boolean', 'sanitize_callback' => 'rest_sanitize_boolean'],
            self::META_PRESENTATION_COLORS => ['type' => 'object', 'sanitize_callback' => [self::class, 'sanitize_presentation_colors']],
            self::META_SEMINARIOS => ['type' => 'array', 'sanitize_callback' => [self::class, 'sanitize_seminars']],
        ];

        foreach ($definitions as $key => $definition) {
            register_post_meta(self::POST_TYPE, $key, array_merge([
                'single' => true,
                'show_in_rest' => false,
                'auth_callback' => static function (): bool { return current_user_can('edit_posts'); },
            ], $definition));
        }
    }

    public static function sanitize_presentation_colors($value): array {
        if (!is_array($value)) {
            return [];
        }

        $principal = trim((string) ($value['principal'] ?? ''));
        if (!preg_match('/^#[0-9a-fA-F]{6}$/', $principal)) {
            return [];
        }

        return [
            'principal' => strtolower($principal),
            'secundarios' => [],
        ];
    }

    public static function sanitize_number($value): float {
        return max(0, (float) str_replace(',', '.', (string) $value));
    }

    public static function sanitize_html_list($value): array {
        if (!is_array($value)) {
            return [];
        }
        return array_values(array_filter(array_map('wp_kses_post', $value), static function ($item): bool {
            return trim(wp_strip_all_tags((string) $item)) !== '';
        }));
    }

    /**
     * Niveles de titulación habilitados para títulos intermedios.
     * Doctorados no forman parte de esta secuencia de procedencia.
     */
    public static function intermediate_level(string $type): int {
        $levels = [
            self::TIPO_MAESTRIA => 4,
            self::TIPO_ESPECIALIZACION => 3,
            self::TIPO_DIPLOMADO => 2,
            self::TIPO_DIPLOMA => 1,
        ];
        return $levels[$type] ?? 0;
    }

    /**
     * Callback exclusivo de register_post_meta(). WordPress entrega
     * (value, meta_key, object_type, object_subtype), no un post_id.
     * La validación con el ID real de la Oferta ocurre al guardar su editor.
     *
     * @param mixed  $value
     * @param string $meta_key
     * @param string $object_type
     * @param string $object_subtype
     * @return array<int,array{oferta_id:int,menciones:array<int,string>}>
     */
    public static function sanitize_intermediate_titles_meta(
        $value,
        $meta_key = '',
        $object_type = '',
        $object_subtype = ''
    ): array {
        return self::sanitize_intermediate_titles($value);
    }

    /**
     * Relación estructurada con otras ofertas: su título proviene de la Oferta
     * vinculada y las menciones son un subconjunto opcional de las que declara.
     *
     * Cuando se recibe el ID de la oferta padre valida estrictamente la
     * jerarquía. La sanitización de metadatos sin contexto preserva solo
     * la estructura y el guardado del editor aplica la validación completa.
     *
     * @return array<int,array{oferta_id:int,menciones:array<int,string>}>
     */
    public static function sanitize_intermediate_titles($value, int $parent_id = 0): array {
        if (!is_array($value)) {
            return [];
        }
        $parent_level = $parent_id > 0 ? self::intermediate_level(self::get_tipo($parent_id)) : 0;
        if ($parent_id > 0 && !$parent_level) {
            return [];
        }

        $result = [];
        $seen = [];
        foreach ($value as $item) {
            if (!is_array($item)) {
                continue;
            }
            $target_id = absint($item['oferta_id'] ?? 0);
            if (!$target_id || $target_id === $parent_id || isset($seen[$target_id])) {
                continue;
            }
            if (get_post_type($target_id) !== self::POST_TYPE) {
                continue;
            }
            if ($parent_id > 0) {
                $target_level = self::intermediate_level(self::get_tipo($target_id));
                if (!$target_level || $target_level >= $parent_level) {
                    continue;
                }
            }

            $available = self::sanitize_text_list(get_post_meta($target_id, 'menciones', true));
            $requested = self::sanitize_text_list($item['menciones'] ?? []);
            $mentions = array_values(array_unique(array_intersect($requested, $available)));
            $result[] = ['oferta_id' => $target_id, 'menciones' => $mentions];
            $seen[$target_id] = true;
        }
        return $result;
    }

    /** Correos de la Oferta Académica (multivaluado); ver FLACSO_Notification_Emails. */
    public static function split_offer_emails($value): array {
        return FLACSO_Notification_Emails::split($value);
    }

    public static function sanitize_offer_emails($value): array {
        return FLACSO_Notification_Emails::sanitize($value);
    }

    /** @return string[] Valores que no son direcciones de correo válidas. */
    public static function invalid_offer_emails($value): array {
        return FLACSO_Notification_Emails::invalid($value);
    }

    public static function get_offer_emails(int $post_id): array {
        return FLACSO_Notification_Emails::from_meta($post_id, 'correo_oferta');
    }

    public static function sanitize_text_list($value): array {
        if (!is_array($value)) {
            return [];
        }
        return array_values(array_filter(array_map(static function ($item): string {
            return sanitize_text_field((string) $item);
        }, $value), static function (string $item): bool { return $item !== ''; }));
    }

    public static function sanitize_documents($value): array {
        if (is_string($value)) {
            $value = json_decode($value, true);
        }
        if (!is_array($value)) {
            return [];
        }
        $result = [];
        foreach ($value as $key => $item) {
            if (!is_array($item)) {
                continue;
            }
            $document = [
                'enabled' => !empty($item['enabled']),
                'link' => esc_url_raw((string) ($item['link'] ?? '')),
            ];
            if (!empty($item['fecha'])) {
                $document['fecha'] = sanitize_text_field((string) $item['fecha']);
            }
            if ($document['enabled'] || $document['link'] !== '') {
                $result[sanitize_key((string) $key)] = $document;
            }
        }
        return $result;
    }

    public static function sanitize_teams($value): array {
        if (!is_array($value)) {
            return [];
        }
        $groups = [];
        foreach ($value as $group) {
            if (!is_array($group)) {
                continue;
            }
            $members = [];
            foreach ((array) ($group['docentes'] ?? []) as $member) {
                $id = is_array($member) ? absint($member['id'] ?? 0) : absint($member);
                if ($id < 1) {
                    continue;
                }
                $entry = ['id' => $id, 'rol' => is_array($member) ? sanitize_text_field((string) ($member['rol'] ?? '')) : ''];
                if (is_array($member) && !empty($member['correo'])) {
                    $entry['correo'] = sanitize_email((string) $member['correo']);
                }
                $members[] = $entry;
            }
            if (!$members) {
                continue;
            }
            $groups[] = [
                'nombre' => sanitize_text_field((string) ($group['nombre'] ?? $group['rol'] ?? '')),
                'descripcion' => wp_kses_post((string) ($group['descripcion'] ?? '')),
                'importancia' => in_array((string) ($group['importancia'] ?? '3'), ['1', '2', '3'], true) ? (string) $group['importancia'] : '3',
                'docentes' => $members,
            ];
        }
        return $groups;
    }

    public static function sanitize_sections($value): array {
        if (!is_array($value)) {
            return [];
        }
        $result = [];
        foreach ($value as $item) {
            if (!is_array($item)) {
                continue;
            }
            $section = [
                'titulo' => sanitize_text_field((string) ($item['titulo'] ?? '')),
                'contenido' => wp_kses_post((string) ($item['contenido'] ?? '')),
            ];
            if ($section['titulo'] !== '' || trim(wp_strip_all_tags($section['contenido'])) !== '') {
                $result[] = $section;
            }
        }
        return $result;
    }

    public static function sanitize_seminars($value): array {
        if (!is_array($value)) {
            return [];
        }
        $result = [];
        $seen = [];
        foreach ($value as $item) {
            if (!is_array($item)) {
                continue;
            }
            $seminar_id = absint($item['seminario_id'] ?? 0);
            if ($seminar_id < 1 || isset($seen[$seminar_id])) {
                continue;
            }
            $character = sanitize_key((string) ($item['caracter'] ?? 'opcional'));
            $result[] = [
                'seminario_id' => $seminar_id,
                'orden' => absint($item['orden'] ?? count($result)),
                'caracter' => in_array($character, ['obligatorio', 'opcional'], true) ? $character : 'opcional',
                'creditos_reconocidos' => isset($item['creditos_reconocidos']) && $item['creditos_reconocidos'] !== ''
                    ? self::sanitize_number($item['creditos_reconocidos'])
                    : null,
            ];
            $seen[$seminar_id] = true;
        }
        usort($result, static function (array $a, array $b): int { return $a['orden'] <=> $b['orden']; });
        return $result;
    }
}
