<?php

if (!defined('ABSPATH')) {
    exit;
}

/** Cohortes y ediciones comparten una unica entidad tecnica. */
final class FLACSO_Instancia_Oferta {
    public const POST_TYPE = 'instancia-oferta';

    public const ESTADO_PLANIFICADA = 'planificada';
    public const ESTADO_EN_CURSO = 'en_curso';
    public const ESTADO_FINALIZADA = 'finalizada';
    public const ESTADO_CANCELADA = 'cancelada';

    // Alias de entrada transitorios. Nunca se persisten como estado academico.
    public const LEGACY_ESTADO_ABIERTA = 'preinscripciones_abiertas';
    public const LEGACY_ESTADO_CERRADA = 'preinscripciones_cerradas';

    public const META_OFERTA_ID = 'oferta_academica_id';
    public const META_ANIO = 'anio';
    public const META_SEMESTRE = 'semestre';
    public const META_NUMERO = 'numero';
    public const META_FECHA_INICIO = 'fecha_inicio';
    public const META_FECHA_FIN = 'fecha_fin';
    public const META_PRECISION_FECHA_INICIO = 'precision_fecha_inicio';
    public const META_ESTADO = 'estado';
    public const META_FLUJO = 'flujo_preinscripcion';
    public const META_PREINSCRIPCION_APERTURA = 'preinscripcion_apertura';
    public const META_PREINSCRIPCION_CIERRE_MANUAL = 'preinscripcion_cierre_manual';
    public const META_MENSAJE_ABIERTA = 'mensaje_inscripcion_abierta';
    public const META_MENSAJE_CERRADA = 'mensaje_inscripcion_cerrada';
    public const META_FLUJO_BLOQUEADO = '_flujo_preinscripcion_bloqueado';
    public const META_ORIGEN_LEGACY_ID = 'origen_legacy_id';
    public const META_ORIGEN_LEGACY_TIPO = 'origen_legacy_tipo';
    public const META_LEGACY_OPEN = '_legacy_preinscripciones_abiertas';

    // Datos propios de una edicion de seminario.
    public const META_ENCUENTROS = 'encuentros_sincronicos';
    public const META_DOCENTES = 'docentes';
    public const META_MODALIDAD = 'modalidad';
    public const META_ES_ASINCRONICO = 'es_asincronico';
    public const META_VALOR_UYU = 'valor_uyu';
    public const META_VALOR_USD = 'valor_usd';
    public const META_VALOR_UYU_DESCUENTO = 'valor_uyu_15_descuento';
    public const META_VALOR_USD_DESCUENTO = 'valor_usd_15_descuento';
    public const META_MOSTRAR_FORMULARIO = 'mostrar_en_formulario';

    /** Nombres anteriores aceptados solo como alias de codigo. */
    public const META_INSCRIPCION_INICIO = self::META_PREINSCRIPCION_APERTURA;
    public const META_INSCRIPCION_FIN = self::META_PREINSCRIPCION_CIERRE_MANUAL;

    public static function init(): void {
        register_post_type(self::POST_TYPE, [
            'labels' => [
                'name' => __('Instancias de oferta', 'flacso-uruguay'),
                'singular_name' => __('Instancia de oferta', 'flacso-uruguay'),
            ],
            'public' => false,
            'publicly_queryable' => false,
            'show_ui' => false,
            'show_in_rest' => false,
            'supports' => ['title', 'revisions'],
            'capability_type' => 'post',
            'map_meta_cap' => true,
        ]);

        foreach (self::meta_schema() as $key => $definition) {
            register_post_meta(self::POST_TYPE, $key, array_merge([
                'single' => true,
                'show_in_rest' => false,
                'auth_callback' => static function (): bool {
                    return current_user_can('edit_posts');
                },
            ], $definition));
        }
    }

    public static function estados(): array {
        return [
            self::ESTADO_PLANIFICADA,
            self::ESTADO_EN_CURSO,
            self::ESTADO_FINALIZADA,
            self::ESTADO_CANCELADA,
        ];
    }

    public static function normalize_academic_state($state): string {
        $state = sanitize_key((string) $state);
        $legacy = [
            self::LEGACY_ESTADO_ABIERTA => self::ESTADO_PLANIFICADA,
            self::LEGACY_ESTADO_CERRADA => self::ESTADO_PLANIFICADA,
            'upcoming' => self::ESTADO_PLANIFICADA,
            'open' => self::ESTADO_PLANIFICADA,
            'closed' => self::ESTADO_PLANIFICADA,
            'active' => self::ESTADO_EN_CURSO,
            'completed' => self::ESTADO_FINALIZADA,
            'cancelled' => self::ESTADO_CANCELADA,
        ];
        $state = $legacy[$state] ?? $state;
        return in_array($state, self::estados(), true) ? $state : self::ESTADO_PLANIFICADA;
    }

    public static function meta_schema(): array {
        $text = ['type' => 'string', 'sanitize_callback' => 'sanitize_text_field'];
        $boolean = ['type' => 'boolean', 'sanitize_callback' => 'rest_sanitize_boolean'];
        return [
            self::META_OFERTA_ID => ['type' => 'integer', 'sanitize_callback' => 'absint'],
            self::META_ANIO => ['type' => 'integer', 'sanitize_callback' => 'absint'],
            self::META_SEMESTRE => $text,
            self::META_NUMERO => ['type' => 'integer', 'sanitize_callback' => 'absint'],
            self::META_FECHA_INICIO => $text,
            self::META_FECHA_FIN => $text,
            self::META_PRECISION_FECHA_INICIO => $text,
            self::META_ESTADO => ['type' => 'string', 'sanitize_callback' => [self::class, 'normalize_academic_state']],
            self::META_FLUJO => ['type' => 'string', 'sanitize_callback' => [FLACSO_Preinscription_Flow::class, 'normalize']],
            self::META_PREINSCRIPCION_APERTURA => $text,
            self::META_PREINSCRIPCION_CIERRE_MANUAL => $text,
            self::META_MENSAJE_ABIERTA => ['type' => 'string', 'sanitize_callback' => 'sanitize_textarea_field'],
            self::META_MENSAJE_CERRADA => ['type' => 'string', 'sanitize_callback' => 'sanitize_textarea_field'],
            self::META_FLUJO_BLOQUEADO => $boolean,
            self::META_ORIGEN_LEGACY_ID => ['type' => 'integer', 'sanitize_callback' => 'absint'],
            self::META_ORIGEN_LEGACY_TIPO => ['type' => 'string', 'sanitize_callback' => 'sanitize_key'],
            self::META_LEGACY_OPEN => $boolean,
            self::META_ENCUENTROS => ['type' => 'array', 'sanitize_callback' => null],
            self::META_DOCENTES => ['type' => 'array', 'sanitize_callback' => null],
            self::META_MODALIDAD => $text,
            self::META_ES_ASINCRONICO => $boolean,
            self::META_VALOR_UYU => ['type' => 'number', 'sanitize_callback' => 'floatval'],
            self::META_VALOR_USD => ['type' => 'number', 'sanitize_callback' => 'floatval'],
            self::META_VALOR_UYU_DESCUENTO => ['type' => 'number', 'sanitize_callback' => 'floatval'],
            self::META_VALOR_USD_DESCUENTO => ['type' => 'number', 'sanitize_callback' => 'floatval'],
            self::META_MOSTRAR_FORMULARIO => $boolean,
        ];
    }

    public static function get_flow(int $instance_id): string {
        return FLACSO_Preinscription_Flow::normalize(get_post_meta($instance_id, self::META_FLUJO, true));
    }

    public static function get_offer_id(int $instance_id): int {
        return absint(get_post_meta($instance_id, self::META_OFERTA_ID, true));
    }

    public static function get_state(int $instance_id): string {
        return self::normalize_academic_state(get_post_meta($instance_id, self::META_ESTADO, true));
    }

    public static function get_nombre_visible(int $instance_id): string {
        $post = get_post($instance_id);
        if ($post && trim((string) $post->post_title) !== '') {
            return (string) $post->post_title;
        }
        return class_exists('FLACSO_Oferta_Academica')
            ? FLACSO_Oferta_Academica::etiqueta_instancia(self::get_offer_id($instance_id))
            : __('Instancia', 'flacso-uruguay');
    }

    public static function get_preinscripcion_cierre_efectivo(int $instance_id): ?string {
        $opening = trim((string) get_post_meta($instance_id, self::META_PREINSCRIPCION_APERTURA, true));
        $offer_id = self::get_offer_id($instance_id);
        $type = class_exists('FLACSO_Oferta_Academica') ? FLACSO_Oferta_Academica::get_tipo($offer_id) : '';
        if ($type === FLACSO_Oferta_Academica::TIPO_SEMINARIO) {
            if ($opening === '') {
                return null;
            }
            try {
                return (new DateTimeImmutable($opening))->add(new DateInterval('P7D'))->format(DATE_ATOM);
            } catch (Exception $exception) {
                return null;
            }
        }
        $manual = trim((string) get_post_meta($instance_id, self::META_PREINSCRIPCION_CIERRE_MANUAL, true));
        return $manual !== '' ? self::normalize_datetime($manual) : null;
    }

    public static function acepta_preinscripciones(int $instance_id, string $now = ''): bool {
        $opening = trim((string) get_post_meta($instance_id, self::META_PREINSCRIPCION_APERTURA, true));
        if ($opening === '') {
            // Compatibilidad honesta: se conserva el booleano legacy sin fabricar
            // un timestamp historico. Las instancias nuevas nunca usan este camino.
            return rest_sanitize_boolean(get_post_meta($instance_id, self::META_LEGACY_OPEN, true));
        }

        try {
            $current = new DateTimeImmutable($now !== '' ? $now : gmdate(DATE_ATOM));
            $starts = new DateTimeImmutable($opening);
            if ($current < $starts) {
                return false;
            }
            $closing = self::get_preinscripcion_cierre_efectivo($instance_id);
            return $closing === null || $current < new DateTimeImmutable($closing);
        } catch (Exception $exception) {
            return false;
        }
    }

    public static function normalize_datetime($value): ?string {
        $value = trim(sanitize_text_field((string) $value));
        if ($value === '') {
            return null;
        }
        try {
            return (new DateTimeImmutable($value))->format(DATE_ATOM);
        } catch (Exception $exception) {
            return null;
        }
    }

    public static function is_flow_locked(int $instance_id): bool {
        return trim((string) get_post_meta($instance_id, self::META_PREINSCRIPCION_APERTURA, true)) !== ''
            || rest_sanitize_boolean(get_post_meta($instance_id, self::META_LEGACY_OPEN, true))
            || rest_sanitize_boolean(get_post_meta($instance_id, self::META_FLUJO_BLOQUEADO, true));
    }

    public static function find_open_for_offer(int $offer_id): int {
        foreach (self::find_for_offer($offer_id) as $id) {
            if (self::acepta_preinscripciones($id)) {
                return $id;
            }
        }
        return 0;
    }

    public static function find_latest_for_offer(int $offer_id): int {
        $ids = self::find_for_offer($offer_id);
        return !empty($ids) ? absint($ids[0]) : 0;
    }

    private static function find_for_offer(int $offer_id): array {
        return array_map('absint', get_posts([
            'post_type' => self::POST_TYPE,
            'post_status' => 'publish',
            'posts_per_page' => -1,
            'fields' => 'ids',
            'orderby' => 'modified',
            'order' => 'DESC',
            'meta_query' => [[
                'key' => self::META_OFERTA_ID,
                'value' => $offer_id,
                'compare' => '=',
                'type' => 'NUMERIC',
            ]],
            'no_found_rows' => true,
        ]));
    }

    public static function close_other_open_instances(int $offer_id, int $except_id, string $closed_at = ''): void {
        $closed_at = self::normalize_datetime($closed_at !== '' ? $closed_at : gmdate(DATE_ATOM));
        foreach (self::find_for_offer($offer_id) as $id) {
            if ($id !== $except_id && self::acepta_preinscripciones($id)) {
                update_post_meta($id, self::META_PREINSCRIPCION_CIERRE_MANUAL, $closed_at);
                update_post_meta($id, self::META_FLUJO_BLOQUEADO, true);
            }
        }
    }
}
