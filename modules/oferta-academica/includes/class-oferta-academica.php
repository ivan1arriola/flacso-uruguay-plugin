<?php

if (!defined('ABSPATH')) {
    exit;
}

/** Definicion estable de una carrera o trayecto academico. */
final class FLACSO_Oferta_Academica {
    public const POST_TYPE = 'oferta-academica';
    public const TYPE_TAXONOMY = 'tipo-oferta-academica';

    public const TIPO_MAESTRIA = 'maestria';
    public const TIPO_ESPECIALIZACION = 'especializacion';
    public const TIPO_DIPLOMADO = 'diplomado';
    public const TIPO_DIPLOMA = 'diploma';

    public const META_PROGRAM_ID = 'programa_academico_id';
    public const META_SEMINARIOS = 'seminarios';

    /** @return array<string,string> */
    public static function tipos(): array {
        return [
            self::TIPO_MAESTRIA => 'Maestrías',
            self::TIPO_ESPECIALIZACION => 'Especializaciones',
            self::TIPO_DIPLOMADO => 'Diplomados',
            self::TIPO_DIPLOMA => 'Diplomas',
        ];
    }

    /** @return array<string,string> */
    public static function segmentos_url(): array {
        return [
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

    public static function register_meta(): void {
        $definitions = [
            self::META_PROGRAM_ID => ['type' => 'integer', 'sanitize_callback' => 'absint'],
            'abreviacion' => ['type' => 'string', 'sanitize_callback' => 'sanitize_text_field'],
            'correo' => ['type' => 'string', 'sanitize_callback' => 'sanitize_email'],
            'presentacion' => ['type' => 'string', 'sanitize_callback' => 'wp_kses_post'],
            'objetivo_general' => ['type' => 'string', 'sanitize_callback' => 'wp_kses_post'],
            'objetivos_especificos' => ['type' => 'array', 'sanitize_callback' => [self::class, 'sanitize_html_list']],
            'composicion_academica' => ['type' => 'array', 'sanitize_callback' => [self::class, 'sanitize_sections']],
            'forma_aprobacion' => ['type' => 'string', 'sanitize_callback' => 'wp_kses_post'],
            'duracion_meses' => ['type' => 'number', 'sanitize_callback' => [self::class, 'sanitize_number']],
            'duracion_html' => ['type' => 'string', 'sanitize_callback' => 'wp_kses_post'],
            'carga_horaria' => ['type' => 'number', 'sanitize_callback' => [self::class, 'sanitize_number']],
            'carga_horaria_descripcion' => ['type' => 'string', 'sanitize_callback' => 'sanitize_text_field'],
            'creditos' => ['type' => 'number', 'sanitize_callback' => [self::class, 'sanitize_number']],
            'acreditacion' => ['type' => 'string', 'sanitize_callback' => 'wp_kses_post'],
            'perfil_ingreso_html' => ['type' => 'string', 'sanitize_callback' => 'wp_kses_post'],
            'requisitos_ingreso_html' => ['type' => 'string', 'sanitize_callback' => 'wp_kses_post'],
            'perfil_egreso_html' => ['type' => 'string', 'sanitize_callback' => 'wp_kses_post'],
            'requisitos_egreso_html' => ['type' => 'string', 'sanitize_callback' => 'wp_kses_post'],
            'titulos_certificaciones_html' => ['type' => 'string', 'sanitize_callback' => 'wp_kses_post'],
            'financiacion_html' => ['type' => 'string', 'sanitize_callback' => 'wp_kses_post'],
            'menciones' => ['type' => 'array', 'sanitize_callback' => [self::class, 'sanitize_text_list']],
            'orientaciones' => ['type' => 'array', 'sanitize_callback' => [self::class, 'sanitize_text_list']],
            'titulos_intermedios' => ['type' => 'array', 'sanitize_callback' => [self::class, 'sanitize_text_list']],
            'malla_curricular' => ['type' => 'string', 'sanitize_callback' => 'esc_url_raw'],
            'malla_curricular_html' => ['type' => 'string', 'sanitize_callback' => 'wp_kses_post'],
            'documentos' => ['type' => 'object', 'sanitize_callback' => [self::class, 'sanitize_documents']],
            'equipos' => ['type' => 'array', 'sanitize_callback' => [self::class, 'sanitize_teams']],
            'reconocido_mec' => ['type' => 'boolean', 'sanitize_callback' => 'rest_sanitize_boolean'],
            'reconocimiento_internacional' => ['type' => 'boolean', 'sanitize_callback' => 'rest_sanitize_boolean'],
            'convenio_iin_oea' => ['type' => 'boolean', 'sanitize_callback' => 'rest_sanitize_boolean'],
            'mostrar_costos_envio' => ['type' => 'boolean', 'sanitize_callback' => 'rest_sanitize_boolean'],
            'mostrar_expedicion_titulo' => ['type' => 'boolean', 'sanitize_callback' => 'rest_sanitize_boolean'],
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
