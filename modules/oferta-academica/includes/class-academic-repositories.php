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
                    'programa_academico_id', 'correo', 'presentacion', 'objetivo_general',
                    'objetivos_especificos', 'composicion_academica', 'forma_aprobacion',
                    'carga_horaria', 'carga_horaria_descripcion', 'creditos', 'acreditacion', 'seminarios',
                ],
            ],
            'cohortes' => [
                'post_type' => FLACSO_Cohorte::POST_TYPE,
                'fields' => [
                    'oferta_academica_id', 'anio', 'periodo', 'numero', 'fecha_inicio', 'fecha_fin',
                    'precision_fecha_inicio', 'estado', 'calendario_academico', 'modalidad', 'tabla_precio_id',
                    'preinscripcion_desde', 'preinscripcion_hasta',
                    'mensaje_preinscripcion_abierta', 'mensaje_preinscripcion_cerrada',
                ],
            ],
            'seminarios' => [
                'post_type' => FLACSO_Seminario::POST_TYPE,
                'fields' => [
                    'programa_academico_id', 'correo', 'presentacion', 'objetivo_general',
                    'objetivos_especificos', 'composicion_academica', 'forma_aprobacion',
                    'carga_horaria', 'carga_horaria_descripcion', 'creditos', 'acreditacion',
                    'acredita_maestria', 'acredita_doctorado', 'docentes_base', 'componentes',
                ],
            ],
            'ediciones-seminario' => [
                'post_type' => FLACSO_Edicion_Seminario::POST_TYPE,
                'fields' => [
                    'seminario_id', 'anio', 'fecha_inicio', 'fecha_fin', 'estado', 'modalidad',
                    'encuentros_sincronicos', 'docentes', 'tabla_precio_id',
                    'preinscripcion_desde', 'preinscripcion_hasta',
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
            'contenido' => (string) $post->post_content,
            'resumen' => (string) $post->post_excerpt,
            'imagen' => get_the_post_thumbnail_url($post, 'full') ?: null,
        ];
        foreach ($definition['fields'] as $field) {
            $data[$field] = get_post_meta($post->ID, $field, true);
        }
        if ($entity === 'ofertas') {
            $data['tipo'] = FLACSO_Oferta_Academica::get_tipo((int) $post->ID);
        }
        if ($entity === 'cohortes') {
            $data['preinscripcion'] = FLACSO_Preinscripcion::for_cohort((int) $post->ID);
        }
        if ($entity === 'ediciones-seminario') {
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
        if (array_key_exists('nombre', $payload)) {
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
                update_post_meta($post_id, $field, $payload[$field]);
            }
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
            'ediciones-seminario' => ['seminario_id', FLACSO_Seminario::POST_TYPE],
        ];
        if (isset($parent_rules[$entity])) {
            [$key, $type] = $parent_rules[$entity];
            $parent_id = array_key_exists($key, $payload) ? absint($payload[$key]) : absint($id ? get_post_meta($id, $key, true) : 0);
            if ($parent_id < 1 || get_post_type($parent_id) !== $type) {
                return new WP_Error('invalid_parent', sprintf(__('El campo %s debe referir a un registro válido.', 'flacso-uruguay'), $key), ['status' => 400]);
            }
        }
        if ($entity === 'ofertas') {
            $type = array_key_exists('tipo', $payload) ? sanitize_key((string) $payload['tipo']) : ($id ? FLACSO_Oferta_Academica::get_tipo($id) : '');
            if (!FLACSO_Oferta_Academica::tipo_valido($type)) {
                return new WP_Error('invalid_offer_type', __('Tipo de oferta inválido.', 'flacso-uruguay'), ['status' => 400]);
            }
        }
        foreach ([['fecha_inicio', 'fecha_fin'], ['preinscripcion_desde', 'preinscripcion_hasta']] as $pair) {
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
        $relation_rules = [
            'seminarios' => ['seminario_id', FLACSO_Seminario::POST_TYPE],
            'componentes' => ['seminario_id', FLACSO_Seminario::POST_TYPE],
            'ediciones_componentes' => ['edicion_id', FLACSO_Edicion_Seminario::POST_TYPE],
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
    public static function base_url(): string {
        return untrailingslashit((string) get_option('flacso_preinscripciones_url', 'https://preinscripciones.flacso.edu.uy'));
    }

    public static function for_cohort(int $cohort_id): array {
        $offer_id = absint(get_post_meta($cohort_id, FLACSO_Cohorte::META_PARENT_ID, true));
        return [
            'abierta' => FLACSO_Cohorte::accepts_registration($cohort_id),
            'url' => self::base_url() . '/ofertas/' . $offer_id . '/cohortes/' . $cohort_id . '/',
            'desde' => get_post_meta($cohort_id, 'preinscripcion_desde', true) ?: null,
            'hasta' => get_post_meta($cohort_id, 'preinscripcion_hasta', true) ?: null,
        ];
    }

    public static function for_edition(int $edition_id): array {
        $seminar_id = absint(get_post_meta($edition_id, FLACSO_Edicion_Seminario::META_PARENT_ID, true));
        return [
            'abierta' => FLACSO_Edicion_Seminario::accepts_registration($edition_id),
            'url' => self::base_url() . '/seminarios/' . $seminar_id . '/ediciones/' . $edition_id . '/',
            'desde' => get_post_meta($edition_id, 'preinscripcion_desde', true) ?: null,
            'hasta' => get_post_meta($edition_id, 'preinscripcion_hasta', true) ?: null,
        ];
    }
}
