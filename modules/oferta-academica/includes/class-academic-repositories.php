<?php

if (!defined('ABSPATH')) {
    exit;
}

/** Persistencia y DTOs del modelo academico final. */
final class FLACSO_Academic_Repository {
    /** @return array<string,array<string,mixed>> */
    public static function definitions(): array {
        return [
            'programas-academicos' => [
                'post_type' => FLACSO_Programa_Academico::POST_TYPE,
                'fields' => ['correo', 'coordinacion', 'orden'],
            ],
            'ofertas' => [
                'post_type' => FLACSO_Oferta_Academica::POST_TYPE,
                'fields' => [
                    'programa_academico_id', 'abreviacion', 'correo', 'presentacion', 'objetivo_general',
                    'objetivos_especificos', 'composicion_academica', 'forma_aprobacion',
                    'duracion_meses', 'duracion_html', 'carga_horaria', 'carga_horaria_descripcion', 'creditos', 'acreditacion',
                    'perfil_ingreso_html', 'requisitos_ingreso_html', 'perfil_egreso_html', 'requisitos_egreso_html',
                    'titulos_certificaciones_html', 'financiacion_html', 'menciones', 'orientaciones', 'titulos_intermedios',
                    'malla_curricular', 'malla_curricular_html', 'documentos', 'equipos', 'reconocido_mec',
                    'reconocimiento_internacional', 'convenio_iin_oea', 'mostrar_costos_envio',
                    'mostrar_expedicion_titulo', 'seminarios',
                ],
            ],
            'cohortes' => [
                'post_type' => FLACSO_Cohorte::POST_TYPE,
                'fields' => [
                    'oferta_academica_id', 'numero', 'fecha_inicio', 'fecha_fin', 'anio_inicio', 'anio_fin',
                    'precision_fecha_inicio', 'estado', 'calendario_academico', 'calendario_descripcion',
                    'modalidad', 'modalidad_descripcion', 'tabla_precio_id', 'link_preinscripcion',
                    'preinscripcion_desde', 'preinscripcion_hasta', 'preinscripcion_habilitada',
                    'mensaje_preinscripcion_abierta', 'mensaje_preinscripcion_cerrada',
                    'presentacion_preinscripcion', 'etiqueta_preinscripcion', 'cta_preinscripcion',
                    'instancias_presenciales',
                ],
            ],
            'seminarios' => [
                'post_type' => FLACSO_Seminario::POST_TYPE,
                'fields' => [
                    'programa_academico_id', 'oferta_academica_id', 'correo', 'presentacion', 'objetivo_general',
                    'objetivos_especificos', 'composicion_academica', 'forma_aprobacion',
                    'carga_horaria', 'carga_horaria_descripcion', 'creditos', 'acreditacion',
                    'acredita_maestria', 'acredita_doctorado', 'componentes',
                ],
            ],
            'ediciones' => [
                'post_type' => FLACSO_Edicion::POST_TYPE,
                'fields' => [
                    'seminario_id', 'anio', 'semestre', 'fecha_inicio', 'fecha_fin', 'estado', 'modalidad',
                    'encuentros_sincronicos', 'docentes', 'tabla_precio_id',
                    'link_preinscripcion', 'dias_cierre_post_inicio',
                    'mensaje_preinscripcion_abierta', 'mensaje_preinscripcion_cerrada',
                    'mostrar_en_formulario', 'ediciones_componentes',
                ],
            ],
            'tablas-precio' => [
                'post_type' => 'tabla-precio',
                'fields' => ['tabla_precios_tipo', 'precios_filas', 'precios_nota', 'mostrar_precios_dolares'],
            ],
        ];
    }

    public static function definition(string $entity): array {
        return self::definitions()[$entity] ?? [];
    }

    public static function list(string $entity, array $filters = []): array {
        $definition = self::definition($entity);
        if (!$definition) {
            return [];
        }
        $args = [
            'post_type' => $definition['post_type'],
            'post_status' => current_user_can('edit_posts') ? ['publish', 'draft', 'pending', 'private'] : 'publish',
            'posts_per_page' => min(200, max(1, absint($filters['per_page'] ?? 100))),
            'orderby' => 'date',
            'order' => 'DESC',
        ];
        if (!empty($filters['parent_id'])) {
            $parent_key = self::parent_key($entity);
            if ($parent_key) {
                $args['meta_query'] = [[
                    'key' => $parent_key,
                    'value' => absint($filters['parent_id']),
                    'compare' => '=',
                    'type' => 'NUMERIC',
                ]];
            }
        }
        return array_map(static function ($post) use ($entity): array {
            return self::to_array($entity, $post);
        }, get_posts($args));
    }

    public static function to_array(string $entity, $post): array {
        if (is_numeric($post)) {
            $post = get_post(absint($post));
        }
        $definition = self::definition($entity);
        if (!$post || !$definition || $post->post_type !== $definition['post_type']) {
            return [];
        }
        $data = [
            'id' => (int) $post->ID,
            'nombre' => (string) $post->post_title,
            'slug' => (string) $post->post_name,
            'estado_publicacion' => (string) $post->post_status,
            'resumen' => (string) (get_post_meta($post->ID, 'resumen', true) ?: $post->post_excerpt),
            'imagen' => get_the_post_thumbnail_url($post, 'full') ?: null,
        ];
        foreach ($definition['fields'] as $field) {
            $data[$field] = get_post_meta($post->ID, $field, true);
        }
        if ($entity === 'ofertas') {
            $data['tipo'] = FLACSO_Oferta_Academica::get_tipo((int) $post->ID);
        }
        if ($entity === 'cohortes') {
            $data['nombre'] = FLACSO_Cohorte::display_name(absint($data['numero']));
            $data['numero_romano'] = FLACSO_Cohorte::to_roman(absint($data['numero']));
            $data['preinscripcion'] = FLACSO_Preinscripcion::for_cohort((int) $post->ID);
        }
        if ($entity === 'ediciones' || $entity === 'ediciones-seminario') {
            $anio = absint($data['anio'] ?? 0);
            $semestre = class_exists('FLACSO_Edicion') ? FLACSO_Edicion::sanitize_semester($data['semestre'] ?? null) : (!empty($data['semestre']) ? (int) $data['semestre'] : null);
            $data['semestre'] = $semestre;
            $data['nombre'] = $semestre ? sprintf('Edición %d — Semestre %d', $anio, $semestre) : sprintf('Edición %d', $anio);
            $data['es_asincronica'] = empty($data['encuentros_sincronicos']);
            $data['preinscripcion'] = FLACSO_Preinscripcion::for_edition((int) $post->ID);
        }
        return $data;
    }

    public static function save(string $entity, array $payload, int $id = 0) {
        $definition = self::definition($entity);
        if (!$definition) {
            return new WP_Error('invalid_entity', __('Entidad académica inválida.', 'flacso-uruguay'), ['status' => 400]);
        }
        if ($id && get_post_type($id) !== $definition['post_type']) {
            return new WP_Error('not_found', __('Registro no encontrado.', 'flacso-uruguay'), ['status' => 404]);
        }
        $validation = self::validate_payload($entity, $payload, $id);
        if (is_wp_error($validation)) {
            return $validation;
        }
        $post_data = ['post_type' => $definition['post_type']];
        if ($entity === 'cohortes') {
            $number = array_key_exists('numero', $payload)
                ? absint($payload['numero'])
                : absint(get_post_meta($id, 'numero', true));
            $post_data['post_title'] = FLACSO_Cohorte::display_name($number);
        } elseif (array_key_exists('nombre', $payload)) {
            $post_data['post_title'] = sanitize_text_field((string) $payload['nombre']);
        }
        if (array_key_exists('contenido', $payload)) {
            $post_data['post_content'] = wp_kses_post((string) $payload['contenido']);
        }
        if (array_key_exists('resumen', $payload)) {
            $post_data['post_excerpt'] = sanitize_textarea_field((string) $payload['resumen']);
        }
        if (array_key_exists('estado_publicacion', $payload) || !$id) {
            $post_data['post_status'] = self::sanitize_post_status($payload['estado_publicacion'] ?? 'draft');
        }
        if ($id) {
            $post_data['ID'] = $id;
        }
        if (!$id && empty($post_data['post_title'])) {
            return new WP_Error('required_name', __('El nombre es obligatorio.', 'flacso-uruguay'), ['status' => 400]);
        }
        $post_id = wp_insert_post($post_data, true);
        if (is_wp_error($post_id)) {
            return $post_id;
        }
        foreach ($definition['fields'] as $field) {
            if (array_key_exists($field, $payload)) {
                $value = $payload[$field];
                if ($value === '' || $value === null || $value === []) {
                    delete_post_meta($post_id, $field);
                } else {
                    update_post_meta($post_id, $field, $value);
                }
            }
        }
        if ($entity === 'ediciones' || $entity === 'ediciones-seminario') {
            delete_post_meta($post_id, 'preinscripcion_desde');
            delete_post_meta($post_id, 'preinscripcion_hasta');
        }
        if ($entity === 'ofertas' && array_key_exists('tipo', $payload)) {
            $type = sanitize_key((string) $payload['tipo']);
            wp_set_object_terms($post_id, [$type], FLACSO_Oferta_Academica::TYPE_TAXONOMY, false);
        }
        return self::to_array($entity, $post_id);
    }

    public static function parent_key(string $entity): string {
        return [
            'ofertas' => 'programa_academico_id',
            'seminarios' => 'programa_academico_id',
            'cohortes' => 'oferta_academica_id',
            'ediciones' => 'seminario_id',
            'ediciones-seminario' => 'seminario_id',
        ][$entity] ?? '';
    }

    private static function sanitize_post_status($value): string {
        $status = sanitize_key((string) $value);
        return in_array($status, ['publish', 'draft', 'pending', 'private'], true) ? $status : 'draft';
    }

    private static function validate_payload(string $entity, array $payload, int $id) {
        $parent_rules = [
            'ofertas' => ['programa_academico_id', FLACSO_Programa_Academico::POST_TYPE],
            'seminarios' => ['programa_academico_id', FLACSO_Programa_Academico::POST_TYPE],
            'cohortes' => ['oferta_academica_id', FLACSO_Oferta_Academica::POST_TYPE],
            'ediciones' => ['seminario_id', FLACSO_Seminario::POST_TYPE],
            'ediciones-seminario' => ['seminario_id', FLACSO_Seminario::POST_TYPE],
        ];
        if (isset($parent_rules[$entity])) {
            [$key, $type] = $parent_rules[$entity];
            $parent_id = array_key_exists($key, $payload) ? absint($payload[$key]) : absint($id ? get_post_meta($id, $key, true) : 0);
            if ($parent_id < 1 || get_post_type($parent_id) !== $type) {
                return new WP_Error('invalid_parent', sprintf(__('El campo %s debe referir a un registro válido.', 'flacso-uruguay'), $key), ['status' => 400]);
            }
        }
        if ($entity === 'cohortes') {
            $number = array_key_exists('numero', $payload) ? absint($payload['numero']) : absint($id ? get_post_meta($id, 'numero', true) : 0);
            if ($number < 1) {
                return new WP_Error('invalid_cohort_number', __('El número de cohorte debe ser mayor que cero.', 'flacso-uruguay'), ['status' => 400]);
            }
            $offer_id = array_key_exists('oferta_academica_id', $payload)
                ? absint($payload['oferta_academica_id'])
                : absint($id ? get_post_meta($id, 'oferta_academica_id', true) : 0);
            $duplicates = get_posts([
                'post_type' => FLACSO_Cohorte::POST_TYPE,
                'post_status' => ['publish', 'draft', 'pending', 'private'],
                'posts_per_page' => 1,
                'fields' => 'ids',
                'post__not_in' => $id ? [$id] : [],
                'meta_query' => [
                    'relation' => 'AND',
                    ['key' => 'oferta_academica_id', 'value' => $offer_id, 'compare' => '=', 'type' => 'NUMERIC'],
                    ['key' => 'numero', 'value' => $number, 'compare' => '=', 'type' => 'NUMERIC'],
                ],
            ]);
            if ($duplicates) {
                return new WP_Error('duplicate_cohort_number', __('La oferta ya tiene una cohorte con ese número.', 'flacso-uruguay'), ['status' => 409]);
            }
        }
        if ($entity === 'ediciones' || $entity === 'ediciones-seminario') {
            $seminar_id = array_key_exists('seminario_id', $payload)
                ? absint($payload['seminario_id'])
                : absint($id ? get_post_meta($id, 'seminario_id', true) : 0);
            $anio = array_key_exists('anio', $payload)
                ? (class_exists('FLACSO_Edicion') ? FLACSO_Edicion::sanitize_year($payload['anio']) : absint($payload['anio']))
                : absint($id ? get_post_meta($id, 'anio', true) : (int) date('Y'));
            $semestre = array_key_exists('semestre', $payload)
                ? (class_exists('FLACSO_Edicion') ? FLACSO_Edicion::sanitize_semester($payload['semestre']) : (!empty($payload['semestre']) ? (int) $payload['semestre'] : null))
                : (class_exists('FLACSO_Edicion') ? FLACSO_Edicion::sanitize_semester(get_post_meta($id, 'semestre', true)) : null);

            if (class_exists('FLACSO_Edicion') && FLACSO_Edicion::exists_for_seminar($seminar_id, $anio, $semestre, $id)) {
                $msg = $semestre
                    ? sprintf(__('El seminario ya tiene una edición para el año %d y semestre %d.', 'flacso-uruguay'), $anio, $semestre)
                    : sprintf(__('El seminario ya tiene una edición para el año %d sin semestre.', 'flacso-uruguay'), $anio);
                return new WP_Error('duplicate_edition', $msg, ['status' => 409]);
            }
        }
        $date_pairs = [['fecha_inicio', 'fecha_fin']];
        if ($entity === 'cohortes') {
            $date_pairs[] = ['preinscripcion_desde', 'preinscripcion_hasta'];
        }
        foreach ($date_pairs as $pair) {
            $from = array_key_exists($pair[0], $payload) ? $payload[$pair[0]] : ($id ? get_post_meta($id, $pair[0], true) : '');
            $until = array_key_exists($pair[1], $payload) ? $payload[$pair[1]] : ($id ? get_post_meta($id, $pair[1], true) : '');
            if ($from && $until && strtotime((string) $until) < strtotime((string) $from)) {
                return new WP_Error('invalid_date_range', sprintf(__('%2$s no puede ser anterior a %1$s.', 'flacso-uruguay'), $pair[0], $pair[1]), ['status' => 400]);
            }
        }
        if (array_key_exists('tabla_precio_id', $payload)) {
            $table_id = absint($payload['tabla_precio_id']);
            if ($table_id && get_post_type($table_id) !== 'tabla-precio') {
                return new WP_Error('invalid_price_table', __('tabla_precio_id no corresponde a una TablaPrecio.', 'flacso-uruguay'), ['status' => 400]);
            }
        }
        if (array_key_exists('link_preinscripcion', $payload) && $payload['link_preinscripcion'] !== '') {
            $url = esc_url_raw((string) $payload['link_preinscripcion'], ['https']);
            if (wp_parse_url($url, PHP_URL_HOST) !== 'preinscripciones.flacso.edu.uy') {
                return new WP_Error('invalid_registration_url', __('La preinscripción debe apuntar a preinscripciones.flacso.edu.uy.', 'flacso-uruguay'), ['status' => 400]);
            }
        }
        $relation_rules = [
            'seminarios' => ['seminario_id', FLACSO_Seminario::POST_TYPE],
            'componentes' => ['seminario_id', FLACSO_Seminario::POST_TYPE],
            'ediciones_componentes' => ['edicion_id', FLACSO_Edicion::POST_TYPE],
        ];
        foreach ($relation_rules as $field => $rule) {
            if (!array_key_exists($field, $payload) || !is_array($payload[$field])) {
                continue;
            }
            foreach ($payload[$field] as $relation) {
                $target_id = is_array($relation) ? absint($relation[$rule[0]] ?? 0) : 0;
                if ($target_id < 1 || get_post_type($target_id) !== $rule[1] || ($id && $target_id === $id)) {
                    return new WP_Error('invalid_relation', sprintf(__('Relación inválida en %s.', 'flacso-uruguay'), $field), ['status' => 400]);
                }
            }
        }
        return true;
    }
}

/** Unico contrato de preinscripcion: el Gestor externo. */
final class FLACSO_Preinscripcion {
    public static function for_cohort(int $cohort_id): array {
        return [
            'abierta' => FLACSO_Cohorte::accepts_registration($cohort_id),
            'url' => get_post_meta($cohort_id, 'link_preinscripcion', true) ?: null,
            'desde' => get_post_meta($cohort_id, 'preinscripcion_desde', true) ?: null,
            'hasta' => get_post_meta($cohort_id, 'preinscripcion_hasta', true) ?: null,
        ];
    }

    public static function for_edition(int $edition_id): array {
        $fecha_inicio = (string) get_post_meta($edition_id, 'fecha_inicio', true);
        $days = class_exists('FLACSO_Edicion') ? FLACSO_Edicion::get_days_after_start_limit($edition_id) : 10;
        $hasta = null;
        if ($fecha_inicio !== '') {
            $closing = strtotime($fecha_inicio . ' +' . $days . ' days 23:59:59');
            if ($closing) {
                $hasta = date('Y-m-d H:i:s', $closing);
            }
        }
        $post_date = get_post_field('post_date', $edition_id);
        $desde = $post_date ? date('Y-m-d H:i:s', strtotime($post_date)) : null;
        return [
            'abierta' => class_exists('FLACSO_Edicion') ? FLACSO_Edicion::accepts_registration($edition_id) : true,
            'url' => get_post_meta($edition_id, 'link_preinscripcion', true) ?: null,
            'desde' => $desde,
            'hasta' => $hasta,
            'dias_cierre_post_inicio' => $days,
        ];
    }
}
