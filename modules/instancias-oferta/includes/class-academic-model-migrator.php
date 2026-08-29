<?php

if (!defined('ABSPATH')) {
    exit;
}

/** Migracion explicita, versionada y auditable del dominio academico. */
final class FLACSO_Academic_Model_Migrator {
    public const VERSION = 1;
    public const BACKUP_META_KEY = '_flacso_academic_model_migration_backup_v1';
    public const RECORD_META_KEY = '_flacso_academic_model_migration_record_v1';
    public const RESULT_OPTION = 'flacso_academic_model_migration_v1_result';
    public const MAP_OPTION = 'flacso_academic_model_migration_v1_map';

    private const INVALID_SEMINAR_IDS = [27240];
    private const KNOWN_ORPHAN_RELATIONS = [
        ['source_id' => 24162, 'missing_id' => 23911, 'meta_key' => '_oferta_seminarios_ids'],
    ];
    private const DUPLICATE_GROUPS = [
        24299 => [24299, 27242],
        23902 => [23902, 27254],
        25623 => [25623, 27256],
        24432 => [24432, 27245],
        23904 => [23904, 27258],
    ];

    /** Analiza sin escribir. El resultado tambien es el plan ejecutable. */
    public static function analyze(): array {
        $offers = self::posts_of_type(FLACSO_Oferta_Academica::POST_TYPE);
        $seminars = self::posts_of_type(FLACSO_Oferta_Academica::LEGACY_SEMINAR_POST_TYPE);
        $cohorts = self::posts_of_type(FLACSO_Cohorte_API::POST_TYPE);
        $instances = self::posts_of_type(FLACSO_Instancia_Oferta::POST_TYPE);

        $valid = [];
        $invalid = [];
        foreach ($seminars as $seminar) {
            $id = absint($seminar->ID);
            if (in_array($id, self::INVALID_SEMINAR_IDS, true) || trim((string) $seminar->post_title) === '') {
                $invalid[] = [
                    'id' => $id,
                    'title' => (string) $seminar->post_title,
                    'reason' => 'titulo_vacio_o_registro_conocido_invalido',
                ];
                continue;
            }
            $valid[$id] = $seminar;
        }

        $canonical_map = [];
        $duplicate_groups = [];
        $absorbed = [];
        $resolved_conflicts = [];
        foreach ($valid as $id => $seminar) {
            $canonical_map[$id] = $id;
        }
        foreach (self::DUPLICATE_GROUPS as $canonical_id => $member_ids) {
            $present = array_values(array_filter($member_ids, static function (int $id) use ($valid): bool {
                return isset($valid[$id]);
            }));
            if (count($present) < 2) {
                continue;
            }
            foreach ($present as $member_id) {
                $canonical_map[$member_id] = $canonical_id;
                if ($member_id !== $canonical_id) {
                    $absorbed[] = $member_id;
                }
            }
            $academic_source = $canonical_id;
            $group_conflicts = self::academic_conflicts($present);
            $duplicate_groups[] = [
                'canonical_id' => $canonical_id,
                'members' => $present,
                'academic_source' => $academic_source,
                'conflicts' => $group_conflicts,
                'conflict_resolution' => 'CANONICAL_WINS',
            ];
            if (!empty($group_conflicts)) {
                $resolved_conflicts[] = [
                    'canonical_id' => $canonical_id,
                    'members' => $present,
                    'academic_source' => $academic_source,
                    'fields' => $group_conflicts,
                    'resolution' => 'CANONICAL_WINS',
                ];
            }
        }

        $canonical_ids = array_values(array_unique(array_values($canonical_map)));
        sort($canonical_ids);
        sort($absorbed);

        $without_temporality = [];
        $seminar_instances = [];
        foreach ($valid as $legacy_id => $seminar) {
            if (!self::seminar_has_temporality($legacy_id)) {
                $without_temporality[] = [
                    'id' => $legacy_id,
                    'canonical_id' => $canonical_map[$legacy_id],
                    'title' => (string) $seminar->post_title,
                    'action' => 'oferta_sin_instancia_temporal_migrable',
                ];
                continue;
            }
            $seminar_instances[] = [
                'legacy_id' => $legacy_id,
                'canonical_id' => $canonical_map[$legacy_id],
                'reuse_legacy_id' => $legacy_id !== $canonical_map[$legacy_id],
            ];
        }

        $cohorts_to_convert = [];
        foreach ($cohorts as $cohort) {
            $offer_id = absint(get_post_meta($cohort->ID, FLACSO_Cohorte_API::META_OFFER_ID, true));
            $offer = get_post($offer_id);
            $cohorts_to_convert[] = [
                'id' => absint($cohort->ID),
                'offer_id' => $offer_id,
                'valid_offer' => $offer && $offer->post_type === FLACSO_Oferta_Academica::POST_TYPE,
                'name' => (string) $cohort->post_title,
            ];
        }

        $relations = self::analyze_relationships($offers, $valid, $canonical_map);
        $ambiguous_duplicates = self::diagnose_ambiguous_duplicates($valid);
        $existing_offer_count = count($offers);
        $existing_instance_count = count($instances);

        return [
            'migration_version' => self::VERSION,
            'dry_run' => true,
            'ofertas_actuales' => $existing_offer_count,
            'seminarios_legacy_encontrados' => count($seminars),
            'seminarios_validos' => count($valid),
            'seminarios_invalidos' => $invalid,
            'grupos_duplicados' => $duplicate_groups,
            'duplicados_absorbidos' => $absorbed,
            'posibles_duplicados_ambiguos' => $ambiguous_duplicates,
            'ofertas_canonicas' => $canonical_ids,
            'cohortes_a_convertir' => $cohorts_to_convert,
            'instancias_desde_seminarios' => $seminar_instances,
            'registros_sin_fechas' => $without_temporality,
            'relaciones_legacy_leidas' => $relations['legacy'],
            'relaciones_a_migrar' => $relations['valid'],
            'relaciones_finales_deduplicadas' => $relations['valid'],
            'relaciones_absorbidas_por_canonicalizacion' => $relations['absorbed'],
            'resumen_relaciones' => [
                'legacy_leidas' => count($relations['legacy']),
                'finales_deduplicadas' => count($relations['valid']),
                'absorbidas_por_canonicalizacion' => count($relations['absorbed']),
                'huerfanas_conocidas' => count($relations['known_orphans']),
            ],
            'referencias_huerfanas_conocidas' => $relations['known_orphans'],
            'referencias_rotas' => $relations['broken'],
            'conflictos_academicos' => [],
            'conflictos_academicos_resueltos' => $resolved_conflicts,
            'expected_final_counts' => [
                'oferta_academica' => $existing_offer_count + count($canonical_ids),
                'instancia_oferta' => $existing_instance_count + count($cohorts) + count($seminar_instances),
                'desde_cohortes' => count($cohorts),
                'desde_seminarios' => count($seminar_instances),
            ],
            '_plan' => [
                'canonical_map' => $canonical_map,
                'duplicate_groups' => $duplicate_groups,
                'valid_seminar_ids' => array_keys($valid),
                'canonical_ids' => $canonical_ids,
                'seminar_instances' => $seminar_instances,
                'cohort_ids' => array_map(static function (array $item): int { return $item['id']; }, $cohorts_to_convert),
            ],
        ];
    }

    public static function run(bool $dry_run = true): array {
        $report = self::analyze();
        if ($dry_run) {
            return $report;
        }

        global $wpdb;
        $transaction = isset($wpdb) && is_object($wpdb) && method_exists($wpdb, 'query');
        if ($transaction) {
            $wpdb->query('START TRANSACTION');
        }

        try {
            $previous_map = get_option(self::MAP_OPTION, []);
            $map = is_array($previous_map) ? $previous_map : [];
            self::apply_cohorts($report['_plan']['cohort_ids'], $map);
            self::apply_seminars($report['_plan'], $map);
            self::apply_relationships($report['_plan']['canonical_map']);

            $report['dry_run'] = false;
            $report['applied_at'] = gmdate(DATE_ATOM);
            $report['migration_map'] = $map;
            unset($report['_plan']);
            update_option(self::MAP_OPTION, $map, false);
            update_option(self::RESULT_OPTION, $report, false);
            if ($transaction) {
                $wpdb->query('COMMIT');
            }
            return $report;
        } catch (Throwable $error) {
            if ($transaction) {
                $wpdb->query('ROLLBACK');
            }
            throw $error;
        }
    }

    public static function verify(): array {
        $offers = self::posts_of_type(FLACSO_Oferta_Academica::POST_TYPE);
        $instances = self::posts_of_type(FLACSO_Instancia_Oferta::POST_TYPE);
        $legacy_seminars = self::posts_of_type(FLACSO_Oferta_Academica::LEGACY_SEMINAR_POST_TYPE);
        $legacy_cohorts = self::posts_of_type(FLACSO_Cohorte_API::POST_TYPE);
        $invalid_left = array_values(array_filter($legacy_seminars, static function ($post): bool {
            return trim((string) $post->post_title) === '' || in_array(absint($post->ID), self::INVALID_SEMINAR_IDS, true);
        }));
        return [
            'migration_version' => self::VERSION,
            'oferta_academica' => count($offers),
            'instancia_oferta' => count($instances),
            'seminarios_legacy_restantes' => count($legacy_seminars),
            'cohortes_legacy_restantes' => count($legacy_cohorts),
            'invalidos_preservados' => array_map(static function ($post): int { return absint($post->ID); }, $invalid_left),
            'migration_map' => get_option(self::MAP_OPTION, []),
        ];
    }

    public static function rollback_report(): array {
        $items = get_posts([
            'post_type' => [
                FLACSO_Oferta_Academica::POST_TYPE,
                FLACSO_Instancia_Oferta::POST_TYPE,
                FLACSO_Oferta_Academica::LEGACY_SEMINAR_POST_TYPE,
                FLACSO_Cohorte_API::POST_TYPE,
            ],
            'post_status' => 'any',
            'posts_per_page' => -1,
            'meta_query' => [['key' => self::BACKUP_META_KEY, 'compare' => 'EXISTS']],
        ]);
        $report = [];
        foreach ($items as $post) {
            $backup = get_post_meta($post->ID, self::BACKUP_META_KEY, true);
            $record = get_post_meta($post->ID, self::RECORD_META_KEY, true);
            $report[] = [
                'current_id' => absint($post->ID),
                'current_post_type' => (string) $post->post_type,
                'record' => is_array($record) ? $record : [],
                'restore' => is_array($backup) ? $backup : [],
            ];
        }
        $created_instances = [];
        foreach ((array) get_option(self::MAP_OPTION, []) as $record) {
            if (is_array($record) && ($record['action'] ?? '') === 'EDICION_CREADA') {
                $created_instances[] = absint($record['instancia_creada'] ?? 0);
            }
        }
        return [
            'migration_version' => self::VERSION,
            'items' => $report,
            'created_instances_to_remove_if_rollback_is_approved' => array_values(array_filter(array_unique($created_instances))),
            'destructive_rollback_implemented' => false,
        ];
    }

    private static function apply_cohorts(array $cohort_ids, array &$map): void {
        foreach ($cohort_ids as $cohort_id) {
            $post = get_post($cohort_id);
            if (!$post || $post->post_type !== FLACSO_Cohorte_API::POST_TYPE) {
                continue;
            }
            $offer_id = absint(get_post_meta($cohort_id, FLACSO_Cohorte_API::META_OFFER_ID, true));
            $offer = get_post($offer_id);
            if (!$offer || $offer->post_type !== FLACSO_Oferta_Academica::POST_TYPE) {
                throw new RuntimeException('Cohorte ' . $cohort_id . ' sin OfertaAcademica valida.');
            }
            self::backup_post($cohort_id);
            self::checked_update_post(['ID' => $cohort_id, 'post_type' => FLACSO_Instancia_Oferta::POST_TYPE]);
            $meta = [
                FLACSO_Instancia_Oferta::META_OFERTA_ID => $offer_id,
                FLACSO_Instancia_Oferta::META_FECHA_INICIO => get_post_meta($cohort_id, FLACSO_Cohorte_API::META_START_DATE, true),
                FLACSO_Instancia_Oferta::META_PRECISION_FECHA_INICIO => get_post_meta($cohort_id, FLACSO_Cohorte_API::META_START_PRECISION, true),
                FLACSO_Instancia_Oferta::META_FECHA_FIN => get_post_meta($cohort_id, FLACSO_Cohorte_API::META_END_DATE, true),
                FLACSO_Instancia_Oferta::META_ESTADO => FLACSO_Instancia_Oferta::normalize_academic_state(get_post_meta($cohort_id, FLACSO_Cohorte_API::META_STATUS, true)),
                FLACSO_Instancia_Oferta::META_FLUJO => FLACSO_Preinscription_Flow::normalize(get_post_meta($cohort_id, FLACSO_Cohorte_API::META_PREINSCRIPTION_FLOW, true)),
                FLACSO_Instancia_Oferta::META_LEGACY_OPEN => FLACSO_Cohorte_API::sanitize_boolean(get_post_meta($cohort_id, FLACSO_Cohorte_API::META_OPEN, true)),
                FLACSO_Instancia_Oferta::META_ORIGEN_LEGACY_ID => $cohort_id,
                FLACSO_Instancia_Oferta::META_ORIGEN_LEGACY_TIPO => FLACSO_Cohorte_API::POST_TYPE,
            ];
            $opening = FLACSO_Instancia_Oferta::normalize_datetime(get_post_meta($cohort_id, FLACSO_Cohorte_API::META_PREINSCRIPTION_START, true));
            $closing = FLACSO_Instancia_Oferta::normalize_datetime(get_post_meta($cohort_id, FLACSO_Cohorte_API::META_PREINSCRIPTION_END, true));
            if ($opening !== null) {
                $meta[FLACSO_Instancia_Oferta::META_PREINSCRIPCION_APERTURA] = $opening;
            }
            if ($closing !== null) {
                $meta[FLACSO_Instancia_Oferta::META_PREINSCRIPCION_CIERRE_MANUAL] = $closing;
            }
            self::update_meta($cohort_id, $meta);
            $record = self::record($cohort_id, $cohort_id, 'COHORTE_CONVERTIDA', $cohort_id);
            update_post_meta($cohort_id, self::RECORD_META_KEY, $record);
            $map[] = $record;
        }
    }

    private static function apply_seminars(array $plan, array &$map): void {
        $valid_ids = $plan['valid_seminar_ids'];
        $canonical_map = $plan['canonical_map'];
        $groups_by_canonical = [];
        foreach ($valid_ids as $legacy_id) {
            $groups_by_canonical[$canonical_map[$legacy_id]][] = $legacy_id;
        }
        $temporal_ids = array_fill_keys(array_map(static function (array $row): int {
            return absint($row['legacy_id']);
        }, $plan['seminar_instances']), true);

        foreach ($groups_by_canonical as $canonical_id => $member_ids) {
            foreach ($member_ids as $member_id) {
                self::backup_post($member_id);
            }
            $academic_source = $canonical_id;
            $completed_academic_fields = self::merge_academic_meta_into_canonical($canonical_id, $member_ids);
            self::checked_update_post(['ID' => $canonical_id, 'post_type' => FLACSO_Oferta_Academica::POST_TYPE]);
            $term_result = wp_set_object_terms($canonical_id, FLACSO_Oferta_Academica::TIPO_SEMINARIO, FLACSO_Oferta_Academica::TYPE_TAXONOMY, false);
            if (is_wp_error($term_result)) {
                throw new RuntimeException('No se pudo asignar tipo seminario a OfertaAcademica ' . $canonical_id . '.');
            }
            $canonical_record = self::record($canonical_id, $canonical_id, 'OFERTA_CANONICA', 0);
            $canonical_record['fuente_academica'] = $academic_source;
            $canonical_record['campos_academicos_completados'] = $completed_academic_fields;
            update_post_meta($canonical_id, self::RECORD_META_KEY, $canonical_record);
            $map[] = $canonical_record;

            foreach ($member_ids as $legacy_id) {
                if (!isset($temporal_ids[$legacy_id])) {
                    $record = self::record($canonical_id, $legacy_id, 'OFERTA_SIN_INSTANCIA_TEMPORAL', 0);
                    $map[] = $record;
                    continue;
                }
                if ($legacy_id === $canonical_id) {
                    $source = get_post($legacy_id);
                    $instance_id = wp_insert_post([
                        'post_type' => FLACSO_Instancia_Oferta::POST_TYPE,
                        'post_status' => $source ? $source->post_status : 'draft',
                        'post_title' => self::edition_visible_name($legacy_id),
                    ], true);
                    if (is_wp_error($instance_id)) {
                        throw new RuntimeException('No se pudo crear la instancia para seminario ' . $legacy_id . '.');
                    }
                    $instance_id = absint($instance_id);
                    $action = 'EDICION_CREADA';
                } else {
                    $instance_id = $legacy_id;
                    self::checked_update_post([
                        'ID' => $legacy_id,
                        'post_type' => FLACSO_Instancia_Oferta::POST_TYPE,
                        'post_title' => self::edition_visible_name($legacy_id),
                    ]);
                    $action = 'ABSORBIDO_COMO_EDICION';
                }
                self::copy_seminar_instance_meta($legacy_id, $instance_id, $canonical_id);
                $record = self::record($canonical_id, $legacy_id, $action, $instance_id);
                update_post_meta($instance_id, self::RECORD_META_KEY, $record);
                $map[] = $record;
            }
        }
    }

    private static function copy_seminar_instance_meta(int $legacy_id, int $instance_id, int $offer_id): void {
        $meta = [
            FLACSO_Instancia_Oferta::META_OFERTA_ID => $offer_id,
            FLACSO_Instancia_Oferta::META_ESTADO => FLACSO_Instancia_Oferta::ESTADO_PLANIFICADA,
            FLACSO_Instancia_Oferta::META_FLUJO => FLACSO_Preinscription_Flow::LEGACY_EDITOR,
            FLACSO_Instancia_Oferta::META_ORIGEN_LEGACY_ID => $legacy_id,
            FLACSO_Instancia_Oferta::META_ORIGEN_LEGACY_TIPO => FLACSO_Oferta_Academica::LEGACY_SEMINAR_POST_TYPE,
        ];
        foreach (FLACSO_Oferta_Academica::seminar_instance_meta_map() as $legacy_key => $instance_key) {
            if (metadata_exists('post', $legacy_id, $legacy_key)) {
                $meta[$instance_key] = get_post_meta($legacy_id, $legacy_key, true);
            }
        }
        $start = (string) ($meta[FLACSO_Instancia_Oferta::META_FECHA_INICIO] ?? '');
        if (preg_match('/^(\d{4})-/', $start, $matches)) {
            $meta[FLACSO_Instancia_Oferta::META_ANIO] = absint($matches[1]);
        }
        // No se fabrica preinscripcion_apertura desde post_date/post_modified.
        self::update_meta($instance_id, $meta);
    }

    private static function apply_relationships(array $canonical_map): void {
        $offers = self::posts_of_type(FLACSO_Oferta_Academica::POST_TYPE);
        foreach ($offers as $offer) {
            $source_id = absint($offer->ID);
            $legacy_ids = get_post_meta($source_id, '_oferta_seminarios_ids', true);
            if (is_array($legacy_ids)) {
                self::backup_post($source_id);
                $planned = [];
                foreach ($legacy_ids as $order => $legacy_id) {
                    $legacy_id = absint($legacy_id);
                    $destination = $canonical_map[$legacy_id] ?? $legacy_id;
                    if (get_post_type($destination) === FLACSO_Oferta_Academica::POST_TYPE) {
                        $planned[] = [
                            'oferta_destino' => $destination,
                            'tipo_relacion' => FLACSO_Relacion_Oferta_Academica::INTEGRA,
                            'orden' => absint($order),
                        ];
                    }
                }
                FLACSO_Relacion_Oferta_Academica::replace_type_relations($source_id, FLACSO_Relacion_Oferta_Academica::INTEGRA, $planned);
            }
            $components = get_post_meta($source_id, '_seminario_seminarios_componentes', true);
            if (is_array($components)) {
                self::backup_post($source_id);
                $planned = [];
                foreach ($components as $order => $component_id) {
                    $component_id = absint($component_id);
                    $destination = $canonical_map[$component_id] ?? $component_id;
                    if (get_post_type($destination) === FLACSO_Oferta_Academica::POST_TYPE) {
                        $planned[] = [
                            'oferta_destino' => $destination,
                            'tipo_relacion' => FLACSO_Relacion_Oferta_Academica::COMPUESTO_POR,
                            'orden' => absint($order),
                        ];
                    }
                }
                FLACSO_Relacion_Oferta_Academica::replace_type_relations($source_id, FLACSO_Relacion_Oferta_Academica::COMPUESTO_POR, $planned);
            }
        }
    }

    private static function analyze_relationships(array $offers, array $valid_seminars, array $canonical_map): array {
        $legacy = [];
        $migratable = [];
        $broken = [];
        $known_orphans = [];
        foreach ($offers as $offer) {
            $ids = get_post_meta($offer->ID, '_oferta_seminarios_ids', true);
            if (!is_array($ids)) {
                continue;
            }
            foreach ($ids as $order => $legacy_id) {
                $legacy_id = absint($legacy_id);
                $relation = [
                    'oferta_origen' => absint($offer->ID),
                    'oferta_destino' => $legacy_id,
                    'tipo_relacion' => FLACSO_Relacion_Oferta_Academica::INTEGRA,
                    'orden' => absint($order),
                ];
                $legacy[] = $relation;
                if (!isset($valid_seminars[$legacy_id]) && !isset($canonical_map[$legacy_id])) {
                    $missing = ['source_id' => absint($offer->ID), 'missing_id' => $legacy_id, 'meta_key' => '_oferta_seminarios_ids'];
                    if (self::is_known_orphan_relation($missing)) {
                        $missing['action'] = 'OMITIR';
                        $known_orphans[] = $missing;
                    } else {
                        $broken[] = $missing;
                    }
                    continue;
                }
                $migratable[] = $relation;
            }
        }
        foreach ($valid_seminars as $seminar_id => $seminar) {
            $components = get_post_meta($seminar_id, '_seminario_seminarios_componentes', true);
            if (!is_array($components)) {
                continue;
            }
            foreach ($components as $order => $component_id) {
                $component_id = absint($component_id);
                $relation = [
                    'oferta_origen' => absint($seminar_id),
                    'oferta_destino' => $component_id,
                    'tipo_relacion' => FLACSO_Relacion_Oferta_Academica::COMPUESTO_POR,
                    'orden' => absint($order),
                ];
                $legacy[] = $relation;
                if (!isset($valid_seminars[$component_id])) {
                    $broken[] = ['source_id' => $seminar_id, 'missing_id' => $component_id, 'meta_key' => '_seminario_seminarios_componentes'];
                    continue;
                }
                $migratable[] = $relation;
            }
        }
        $canonicalized = self::canonicalize_relationships($migratable, $canonical_map);
        return [
            'legacy' => $legacy,
            'valid' => $canonicalized['final'],
            'absorbed' => $canonicalized['absorbed'],
            'known_orphans' => $known_orphans,
            'broken' => $broken,
        ];
    }

    /**
     * Canonicaliza y deduplica por origen, destino y tipo. La primera aparicion
     * valida conserva su orden; las siguientes quedan en el reporte auditado.
     */
    public static function canonicalize_relationships(array $legacy_relations, array $canonical_map): array {
        $final = [];
        $absorbed = [];
        $seen = [];

        foreach ($legacy_relations as $relation) {
            if (!is_array($relation)) {
                continue;
            }
            $legacy_origin = absint($relation['oferta_origen'] ?? 0);
            $legacy_destination = absint($relation['oferta_destino'] ?? 0);
            $type = sanitize_key((string) ($relation['tipo_relacion'] ?? ''));
            $order = max(0, absint($relation['orden'] ?? 0));
            if ($legacy_origin <= 0 || $legacy_destination <= 0 || !in_array($type, FLACSO_Relacion_Oferta_Academica::tipos(), true)) {
                continue;
            }

            $origin = absint($canonical_map[$legacy_origin] ?? $legacy_origin);
            $destination = absint($canonical_map[$legacy_destination] ?? $legacy_destination);
            $key = $origin . ':' . $destination . ':' . $type;
            if (isset($seen[$key])) {
                $absorbed[] = [
                    'oferta_origen' => $origin,
                    'oferta_destino' => $destination,
                    'tipo_relacion' => $type,
                    'origen_legacy' => $legacy_origin,
                    'destino_legacy' => $legacy_destination,
                    'orden_absorbido' => $order,
                    'destino_legacy_conservado' => $seen[$key]['destino_legacy'],
                    'orden_conservado' => $seen[$key]['orden'],
                ];
                continue;
            }

            $seen[$key] = [
                'destino_legacy' => $legacy_destination,
                'orden' => $order,
            ];
            $final[] = [
                'oferta_origen' => $origin,
                'oferta_destino' => $destination,
                'tipo_relacion' => $type,
                'orden' => $order,
            ];
        }

        return ['final' => $final, 'absorbed' => $absorbed];
    }

    private static function academic_conflicts(array $member_ids): array {
        $conflicts = [];
        foreach (FLACSO_Oferta_Academica::seminar_academic_meta_keys() as $key) {
            $values = [];
            foreach ($member_ids as $member_id) {
                $value = get_post_meta($member_id, $key, true);
                if (!self::has_meaningful_meta_value($value)) {
                    continue;
                }
                $values[] = maybe_serialize($value);
            }
            if (count(array_unique($values)) > 1) {
                $conflicts[] = $key;
            }
        }
        return $conflicts;
    }

    /**
     * El contenido academico existente del canonico tiene precedencia. Las
     * ediciones absorbidas solo completan campos realmente vacios.
     *
     * @return array<string, int> meta_key => legacy source id
     */
    private static function merge_academic_meta_into_canonical(int $canonical_id, array $member_ids): array {
        $sources = array_values(array_filter(array_map('absint', $member_ids), static function (int $member_id) use ($canonical_id): bool {
            return $member_id > 0 && $member_id !== $canonical_id;
        }));
        rsort($sources);

        $completed = [];
        foreach (FLACSO_Oferta_Academica::seminar_academic_meta_keys() as $meta_key) {
            if (self::has_meaningful_meta_value(get_post_meta($canonical_id, $meta_key, true))) {
                continue;
            }
            foreach ($sources as $source_id) {
                $value = get_post_meta($source_id, $meta_key, true);
                if (!self::has_meaningful_meta_value($value)) {
                    continue;
                }
                update_post_meta($canonical_id, $meta_key, $value);
                $completed[$meta_key] = $source_id;
                break;
            }
        }
        return $completed;
    }

    private static function has_meaningful_meta_value($value): bool {
        if (is_array($value)) {
            return !empty($value);
        }
        if (is_object($value)) {
            return !empty((array) $value);
        }
        return trim((string) $value) !== '';
    }

    private static function is_known_orphan_relation(array $relation): bool {
        foreach (self::KNOWN_ORPHAN_RELATIONS as $known) {
            if (absint($relation['source_id'] ?? 0) === $known['source_id']
                && absint($relation['missing_id'] ?? 0) === $known['missing_id']
                && (string) ($relation['meta_key'] ?? '') === $known['meta_key']) {
                return true;
            }
        }
        return false;
    }

    private static function diagnose_ambiguous_duplicates(array $valid): array {
        $groups = [];
        foreach ($valid as $id => $post) {
            $key = FLACSO_Oferta_Academica::normalize_identity_title((string) $post->post_title);
            if ($key !== '') {
                $groups[$key][] = $id;
            }
        }
        $known = [];
        foreach (self::DUPLICATE_GROUPS as $ids) {
            sort($ids);
            $known[implode(',', $ids)] = true;
        }
        $ambiguous = [];
        foreach ($groups as $normalized_title => $ids) {
            if (count($ids) < 2) {
                continue;
            }
            sort($ids);
            if (!isset($known[implode(',', $ids)])) {
                $ambiguous[] = ['normalized_title' => $normalized_title, 'ids' => $ids, 'action' => 'REPORT_ONLY'];
            }
        }
        return $ambiguous;
    }

    private static function seminar_has_temporality(int $id): bool {
        if (trim((string) get_post_meta($id, '_seminario_periodo_inicio', true)) !== ''
            || trim((string) get_post_meta($id, '_seminario_periodo_fin', true)) !== '') {
            return true;
        }
        $meetings = get_post_meta($id, '_seminario_encuentros_sincronicos', true);
        return is_array($meetings) && !empty($meetings);
    }

    private static function edition_visible_name(int $legacy_id): string {
        $start = (string) get_post_meta($legacy_id, '_seminario_periodo_inicio', true);
        if (preg_match('/^(\d{4})-/', $start, $matches)) {
            return sprintf(__('Edición %s', 'flacso-uruguay'), $matches[1]);
        }
        return __('Edición', 'flacso-uruguay');
    }

    private static function backup_post(int $post_id): void {
        if (metadata_exists('post', $post_id, self::BACKUP_META_KEY)) {
            return;
        }
        $post = get_post($post_id);
        if (!$post) {
            throw new RuntimeException('No existe post para backup: ' . $post_id);
        }
        $taxonomies = [];
        foreach (get_object_taxonomies($post->post_type) as $taxonomy) {
            $terms = wp_get_object_terms($post_id, $taxonomy, ['fields' => 'ids']);
            $taxonomies[$taxonomy] = is_wp_error($terms) ? [] : array_map('absint', $terms);
        }
        update_post_meta($post_id, self::BACKUP_META_KEY, [
            'migration_version' => self::VERSION,
            'captured_at' => gmdate(DATE_ATOM),
            'post' => [
                'ID' => absint($post->ID),
                'post_type' => (string) $post->post_type,
                'post_status' => (string) $post->post_status,
                'post_title' => (string) $post->post_title,
                'post_name' => (string) $post->post_name,
                'post_content' => (string) $post->post_content,
                'post_excerpt' => (string) $post->post_excerpt,
            ],
            'taxonomies' => $taxonomies,
            'meta' => get_post_meta($post_id),
            'featured_image_id' => get_post_thumbnail_id($post_id),
        ]);
    }

    private static function checked_update_post(array $postarr): void {
        $result = wp_update_post($postarr, true);
        if (is_wp_error($result) || !$result) {
            throw new RuntimeException('Fallo al actualizar post ' . absint($postarr['ID'] ?? 0) . '.');
        }
    }

    private static function update_meta(int $post_id, array $meta): void {
        foreach ($meta as $key => $value) {
            if ($value !== null && $value !== '') {
                update_post_meta($post_id, $key, $value);
            }
        }
    }

    private static function record(int $canonical_id, int $legacy_id, string $action, int $instance_id): array {
        return [
            'canonical_id' => $canonical_id,
            'legacy_id' => $legacy_id,
            'action' => $action,
            'instancia_creada' => $instance_id,
            'timestamp' => gmdate(DATE_ATOM),
            'migration_version' => self::VERSION,
        ];
    }

    private static function posts_of_type(string $post_type): array {
        return get_posts([
            'post_type' => $post_type,
            'post_status' => ['publish', 'draft', 'private', 'pending', 'future'],
            'posts_per_page' => -1,
            'orderby' => 'ID',
            'order' => 'ASC',
        ]);
    }
}

if (defined('WP_CLI') && WP_CLI) {
    /**
     * Migra OfertaAcademica/InstanciaOferta de forma explicita.
     *
     * ## OPTIONS
     *
     * [--dry-run]
     * : Analiza y reporta sin escribir. Es el modo recomendado antes de aplicar.
     *
     * [--verify]
     * : Verifica conteos y remanentes sin escribir.
     *
     * [--rollback-report]
     * : Genera el plan de restauracion; no ejecuta rollback.
     */
    final class FLACSO_Academic_Model_CLI_Command {
        public function __invoke(array $args, array $assoc_args): void {
            if (isset($assoc_args['rollback-report'])) {
                WP_CLI::line(wp_json_encode(FLACSO_Academic_Model_Migrator::rollback_report(), JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
                return;
            }
            if (isset($assoc_args['verify'])) {
                WP_CLI::line(wp_json_encode(FLACSO_Academic_Model_Migrator::verify(), JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
                return;
            }
            $dry_run = isset($assoc_args['dry-run']);
            if (!$dry_run) {
                WP_CLI::confirm('Esto aplicara la migracion academica v1. Verificaste antes --dry-run y un backup externo?');
            }
            $report = FLACSO_Academic_Model_Migrator::run($dry_run);
            unset($report['_plan']);
            WP_CLI::line(wp_json_encode($report, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
            WP_CLI::success($dry_run ? 'Dry-run finalizado sin escrituras.' : 'Migracion academica v1 aplicada.');
        }
    }

    WP_CLI::add_command('flacso migrate academic-model', FLACSO_Academic_Model_CLI_Command::class);
}
