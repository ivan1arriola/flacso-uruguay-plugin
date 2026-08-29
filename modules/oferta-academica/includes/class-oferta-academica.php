<?php

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Raiz del dominio academico. Toda propuesta, incluidos los seminarios, es una
 * OfertaAcademica; la temporalidad pertenece exclusivamente a InstanciaOferta.
 */
final class FLACSO_Oferta_Academica {
    public const POST_TYPE = 'oferta-academica';
    public const LEGACY_SEMINAR_POST_TYPE = 'seminario';
    public const TYPE_TAXONOMY = 'tipo-oferta-academica';

    public const TIPO_DOCTORADO = 'doctorado';
    public const TIPO_MAESTRIA = 'maestria';
    public const TIPO_ESPECIALIZACION = 'especializacion';
    public const TIPO_DIPLOMADO = 'diplomado';
    public const TIPO_DIPLOMA = 'diploma';
    public const TIPO_SEMINARIO = 'seminario';

    /** @return array<string, string> slug => nombre visible */
    public static function tipos(): array {
        return [
            self::TIPO_DOCTORADO => 'Doctorados',
            self::TIPO_MAESTRIA => 'Maestrías',
            self::TIPO_ESPECIALIZACION => 'Especializaciones',
            self::TIPO_DIPLOMADO => 'Diplomados',
            self::TIPO_DIPLOMA => 'Diplomas',
            self::TIPO_SEMINARIO => 'Seminarios',
        ];
    }

    /** @return array<string, string> slug singular => segmento publico */
    public static function segmentos_url(): array {
        return [
            self::TIPO_DOCTORADO => 'doctorados',
            self::TIPO_MAESTRIA => 'maestrias',
            self::TIPO_ESPECIALIZACION => 'especializaciones',
            self::TIPO_DIPLOMADO => 'diplomados',
            self::TIPO_DIPLOMA => 'diplomas',
            self::TIPO_SEMINARIO => 'seminarios',
        ];
    }

    public static function tipo_valido($tipo): bool {
        return isset(self::tipos()[sanitize_key((string) $tipo)]);
    }

    public static function get_tipo(int $oferta_id): string {
        if ($oferta_id <= 0) {
            return '';
        }

        // Compatibilidad de lectura hasta retirar el CPT legacy en Release B.
        if (get_post_type($oferta_id) === self::LEGACY_SEMINAR_POST_TYPE) {
            return self::TIPO_SEMINARIO;
        }

        $terms = wp_get_object_terms($oferta_id, self::TYPE_TAXONOMY);
        if (is_wp_error($terms) || empty($terms)) {
            return '';
        }

        $tipo = sanitize_key((string) $terms[0]->slug);
        return self::tipo_valido($tipo) ? $tipo : '';
    }

    public static function etiqueta_instancia(int $oferta_id): string {
        return self::get_tipo($oferta_id) === self::TIPO_SEMINARIO
            ? __('Edición', 'flacso-uruguay')
            : __('Cohorte', 'flacso-uruguay');
    }

    /** Campos que describen identidad academica y permanecen en OfertaAcademica. */
    public static function seminar_academic_meta_keys(): array {
        return [
            '_seminario_nombre',
            '_seminario_presentacion_seminario',
            '_seminario_objetivo_general',
            '_seminario_objetivos_especificos',
            '_seminario_unidades_academicas',
            '_seminario_forma_aprobacion',
            '_seminario_carga_horaria',
            '_seminario_creditos',
            '_seminario_acreditacion',
            '_seminario_acredita_maestria',
            '_seminario_acredita_doctorado',
        ];
    }

    /** Campos que describen una edicion y se copian a InstanciaOferta. */
    public static function seminar_instance_meta_map(): array {
        return [
            '_seminario_periodo_inicio' => FLACSO_Instancia_Oferta::META_FECHA_INICIO,
            '_seminario_periodo_fin' => FLACSO_Instancia_Oferta::META_FECHA_FIN,
            '_seminario_encuentros_sincronicos' => FLACSO_Instancia_Oferta::META_ENCUENTROS,
            '_seminario_docentes' => FLACSO_Instancia_Oferta::META_DOCENTES,
            '_seminario_modalidad' => FLACSO_Instancia_Oferta::META_MODALIDAD,
            '_seminario_es_asincronico' => FLACSO_Instancia_Oferta::META_ES_ASINCRONICO,
            '_seminario_valor_uyu' => FLACSO_Instancia_Oferta::META_VALOR_UYU,
            '_seminario_valor_usd' => FLACSO_Instancia_Oferta::META_VALOR_USD,
            '_seminario_valor_uyu_15_descuento' => FLACSO_Instancia_Oferta::META_VALOR_UYU_DESCUENTO,
            '_seminario_valor_usd_15_descuento' => FLACSO_Instancia_Oferta::META_VALOR_USD_DESCUENTO,
            '_seminario_abierto_publico' => FLACSO_Instancia_Oferta::META_LEGACY_OPEN,
            '_seminario_mostrar_en_formulario' => FLACSO_Instancia_Oferta::META_MOSTRAR_FORMULARIO,
        ];
    }

    /** Normalizacion usada solo para diagnostico; nunca decide fusiones nuevas. */
    public static function normalize_identity_title(string $title): string {
        $title = trim($title);
        if (function_exists('remove_accents')) {
            $title = remove_accents($title);
        }
        $title = function_exists('mb_strtolower') ? mb_strtolower($title, 'UTF-8') : strtolower($title);
        $title = preg_replace('/^seminario\s+/u', '', $title);
        $title = preg_replace('/[^a-z0-9\s]+/u', ' ', $title);
        return trim((string) preg_replace('/\s+/u', ' ', (string) $title));
    }
}
