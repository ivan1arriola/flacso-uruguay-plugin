<?php

if (!defined('ABSPATH') || !defined('WP_CLI') || !WP_CLI) {
    return;
}

/** Normaliza metadatos académicos sin recrear ni cambiar IDs. */
final class FLACSO_Academic_Meta_Normalizer {
    /** @var string[] */
    private const OFFER_LEGACY_KEYS = [
        'descripcion_html', 'acreditaciones_html', 'coordinacion_academica',
        'asistente_academica_docente_id', 'asistente_academica_rol', 'asistente_academica_correo',
        'cohorte', 'proximo_inicio', 'proximo_inicio_precision', 'inscripciones_abiertas',
        'inscripciones_mensaje', 'inscripciones_mensaje_cerrado', 'modalidad_html', 'modalidad_resumen',
        'calendario', 'calendario_html', 'calendario_modo', 'malla_curricular_modo',
        'precios_nota', 'tabla_precios_tipo', 'tabla_precio_id', 'precios_filas', 'precio', 'costo',
        'carta_presentacion_html', 'carta_hero_etiqueta', 'carta_cta_titulo',
        'carta_instancias_presenciales', 'visibilidad_carta', 'objetivos_html',
    ];

    /**
     * ## OPTIONS
     *
     * [--dry-run]
     * : Informa los cambios sin escribir.
     *
     * [--apply]
     * : Aplica el plan. Requiere --snapshot.
     *
     * [--snapshot=<path>]
     * : Archivo JSON donde guardar el estado previo de los posts afectados.
     *
     * ## EXAMPLES
     *
     *     wp flacso academic-meta normalize --dry-run
     *     wp flacso academic-meta normalize --apply --snapshot=/tmp/ofertas-before.json
     */
    public function normalize(array $args, array $assoc_args): void {
        $apply = isset($assoc_args['apply']);
        if (!$apply && !isset($assoc_args['dry-run'])) {
            WP_CLI::error('Indicá --dry-run o --apply.');
        }

        $plan = self::build_plan();
        self::render_plan($plan);
        if (!empty($plan['conflicts'])) {
            WP_CLI::error('La normalización tiene conflictos y no puede aplicarse.');
        }
        if (!$apply) {
            WP_CLI::success('Dry-run completado sin escrituras.');
            return;
        }

        $snapshot = (string) ($assoc_args['snapshot'] ?? '');
        if ($snapshot === '') {
            WP_CLI::error('--apply requiere --snapshot=<archivo>.');
        }
        self::write_snapshot($snapshot, array_keys($plan['affected_posts']));
        self::apply_plan($plan);
        self::verify_plan($plan);
        WP_CLI::success('Metadatos académicos normalizados. Snapshot: ' . $snapshot);
    }

    /** @return array<string,mixed> */
    public static function build_plan(): array {
        $plan = [
            'set' => [],
            'delete' => [],
            'conflicts' => [],
            'affected_posts' => [],
        ];
        $offers = get_posts([
            'post_type' => FLACSO_Oferta_Academica::POST_TYPE,
            'post_status' => 'any',
            'posts_per_page' => -1,
            'fields' => 'ids',
            'orderby' => 'ID',
            'order' => 'ASC',
        ]);

        foreach ($offers as $offer_id) {
            $offer_id = absint($offer_id);
            $cohorts = get_posts([
                'post_type' => FLACSO_Cohorte::POST_TYPE,
                'post_status' => 'any',
                'posts_per_page' => -1,
                'fields' => 'ids',
                'meta_key' => FLACSO_Cohorte::META_PARENT_ID,
                'meta_value' => $offer_id,
            ]);
            if (count($cohorts) !== 1) {
                $plan['conflicts'][] = sprintf('Oferta %d: se esperaba exactamente una cohorte y hay %d.', $offer_id, count($cohorts));
                continue;
            }
            $cohort_id = absint($cohorts[0]);
            $plan['affected_posts'][$offer_id] = true;
            $plan['affected_posts'][$cohort_id] = true;

            self::normalize_content($plan, $offer_id);
            self::normalize_people($plan, $offer_id);
            self::normalize_documents($plan, $offer_id, $cohort_id);
            self::move_operational_fields($plan, $offer_id, $cohort_id);
            self::normalize_price_fields($plan, $offer_id, $cohort_id);

            foreach (self::OFFER_LEGACY_KEYS as $key) {
                self::delete($plan, $offer_id, $key);
            }
            self::delete_empty_rows($plan, $offer_id);
        }

        self::find_unknown_fields($plan, $offers);
        return $plan;
    }

    private static function normalize_content(array &$plan, int $offer_id): void {
        $presentation = (string) get_post_meta($offer_id, 'presentacion', true);
        $legacy_description = (string) get_post_meta($offer_id, 'descripcion_html', true);
        if ($presentation === '' && $legacy_description !== '') {
            self::set($plan, $offer_id, 'presentacion', $legacy_description);
        } elseif ($presentation !== '' && $legacy_description !== '' && trim($presentation) !== trim($legacy_description)) {
            $plan['conflicts'][] = sprintf('Oferta %d: presentacion y descripcion_html difieren.', $offer_id);
        }

        $accreditation = (string) get_post_meta($offer_id, 'acreditacion', true);
        $legacy_accreditation = (string) get_post_meta($offer_id, 'acreditaciones_html', true);
        if ($accreditation === '' && $legacy_accreditation !== '') {
            self::set($plan, $offer_id, 'acreditacion', $legacy_accreditation);
        } elseif ($accreditation !== '' && $legacy_accreditation !== '' && trim($accreditation) !== trim($legacy_accreditation)) {
            $plan['conflicts'][] = sprintf('Oferta %d: acreditacion y acreditaciones_html difieren.', $offer_id);
        }

        foreach (['carga_horaria', 'creditos'] as $number_key) {
            if ((float) get_post_meta($offer_id, $number_key, true) === 0.0) {
                self::delete($plan, $offer_id, $number_key);
            }
        }
    }

    private static function normalize_people(array &$plan, int $offer_id): void {
        $teams = FLACSO_Oferta_Academica::sanitize_teams(get_post_meta($offer_id, 'equipos', true));
        $coordination = (array) get_post_meta($offer_id, 'coordinacion_academica', true);
        $prepend = [];
        foreach ($coordination as $group) {
            if (!is_array($group)) {
                continue;
            }
            $prepend[] = [
                'nombre' => (string) ($group['rol'] ?? 'Coordinación académica'),
                'descripcion' => (string) ($group['descripcion'] ?? ''),
                'importancia' => (string) ($group['importancia'] ?? '1'),
                'docentes' => (array) ($group['docentes'] ?? []),
            ];
        }
        if ($prepend) {
            $teams = array_merge(FLACSO_Oferta_Academica::sanitize_teams($prepend), $teams);
        }

        $assistant_id = absint(get_post_meta($offer_id, 'asistente_academica_docente_id', true));
        $assistant_role = trim((string) get_post_meta($offer_id, 'asistente_academica_rol', true));
        $assistant_email = sanitize_email((string) get_post_meta($offer_id, 'asistente_academica_correo', true));
        $found = false;
        if ($assistant_id > 0) {
            foreach ($teams as &$team) {
                foreach ($team['docentes'] as &$member) {
                    if (absint($member['id'] ?? 0) === $assistant_id) {
                        $found = true;
                        if ($assistant_email !== '' && empty($member['correo'])) {
                            $member['correo'] = $assistant_email;
                        }
                    }
                }
                unset($member);
            }
            unset($team);
            if (!$found) {
                $teams[] = [
                    'nombre' => $assistant_role !== '' ? $assistant_role : 'Asistencia académica',
                    'descripcion' => '',
                    'importancia' => '1',
                    'docentes' => [[
                        'id' => $assistant_id,
                        'rol' => $assistant_role,
                        'correo' => $assistant_email,
                    ]],
                ];
            }
        }
        self::set($plan, $offer_id, 'equipos', FLACSO_Oferta_Academica::sanitize_teams($teams));
    }

    private static function normalize_documents(array &$plan, int $offer_id, int $cohort_id): void {
        $raw = get_post_meta($offer_id, 'documentos', true);
        $documents = FLACSO_Oferta_Academica::sanitize_documents($raw);
        $malla = trim((string) get_post_meta($offer_id, 'malla_curricular', true));
        $document_malla = trim((string) ($documents['malla']['link'] ?? ''));
        if ($malla === '' && $document_malla !== '') {
            self::set($plan, $offer_id, 'malla_curricular', $document_malla);
        } elseif ($malla !== '' && $document_malla !== '' && $malla !== $document_malla) {
            $plan['conflicts'][] = sprintf('Oferta %d: las URLs de malla curricular difieren.', $offer_id);
        }

        $calendar = trim((string) get_post_meta($offer_id, 'calendario', true));
        $document_calendar = trim((string) ($documents['calendario']['link'] ?? ''));
        $document_cartamalla = trim((string) ($documents['cartamalla']['link'] ?? ''));
        if ($calendar === '') {
            $calendar = $document_calendar;
        } elseif ($document_calendar !== '' && $calendar !== $document_calendar) {
            if ($document_cartamalla !== '' && $calendar === $document_cartamalla) {
                // El campo plano quedó apuntando por error a la carta/malla. El
                // documento tipado es la fuente inequívoca del calendario.
                $calendar = $document_calendar;
            } else {
                $plan['conflicts'][] = sprintf('Oferta %d: las URLs de calendario difieren.', $offer_id);
            }
        }
        $current_calendar = trim((string) get_post_meta($cohort_id, 'calendario_academico', true));
        if ($current_calendar === '' && $calendar !== '') {
            self::set($plan, $cohort_id, 'calendario_academico', $calendar);
        } elseif ($current_calendar !== '' && $calendar !== '' && $current_calendar !== $calendar) {
            $plan['conflicts'][] = sprintf('Oferta %d: el calendario de oferta difiere del de cohorte %d.', $offer_id, $cohort_id);
        }
        $calendar_description = (string) get_post_meta($offer_id, 'calendario_html', true);
        if ($calendar_description !== '' && get_post_meta($cohort_id, 'calendario_descripcion', true) === '') {
            self::set($plan, $cohort_id, 'calendario_descripcion', $calendar_description);
        }

        $canonical_documents = [];
        if (!empty($documents['cartamalla'])) {
            $canonical_documents['cartamalla'] = $documents['cartamalla'];
        }
        if ($canonical_documents) {
            self::set($plan, $offer_id, 'documentos', $canonical_documents);
        } else {
            self::delete($plan, $offer_id, 'documentos');
        }
    }

    private static function move_operational_fields(array &$plan, int $offer_id, int $cohort_id): void {
        $moves = [
            'modalidad_html' => 'modalidad_descripcion',
            'inscripciones_mensaje' => 'mensaje_preinscripcion_abierta',
            'inscripciones_mensaje_cerrado' => 'mensaje_preinscripcion_cerrada',
            'carta_presentacion_html' => 'presentacion_preinscripcion',
            'carta_hero_etiqueta' => 'etiqueta_preinscripcion',
            'carta_cta_titulo' => 'cta_preinscripcion',
        ];
        foreach ($moves as $source => $target) {
            $value = get_post_meta($offer_id, $source, true);
            $current = get_post_meta($cohort_id, $target, true);
            if (!self::is_empty($value) && self::is_empty($current)) {
                self::set($plan, $cohort_id, $target, $value);
            } elseif (!self::is_empty($value) && !self::is_empty($current) && $value !== $current) {
                $plan['conflicts'][] = sprintf('Oferta %d: %s difiere de cohorte.%s.', $offer_id, $source, $target);
            }
        }

        $modality_text = trim((string) get_post_meta($offer_id, 'modalidad_resumen', true));
        $modality_html = trim((string) get_post_meta($offer_id, 'modalidad_html', true));
        $modality = self::infer_modality($modality_text . ' ' . wp_strip_all_tags($modality_html));
        if ($modality !== '' && get_post_meta($cohort_id, 'modalidad', true) === '') {
            self::set($plan, $cohort_id, 'modalidad', $modality);
        }

        if (metadata_exists('post', $offer_id, 'inscripciones_abiertas')) {
            self::set($plan, $cohort_id, 'preinscripcion_habilitada', rest_sanitize_boolean(get_post_meta($offer_id, 'inscripciones_abiertas', true)));
        }
        if (metadata_exists('post', $offer_id, 'carta_instancias_presenciales')) {
            self::set($plan, $cohort_id, 'instancias_presenciales', rest_sanitize_boolean(get_post_meta($offer_id, 'carta_instancias_presenciales', true)));
        }
    }

    private static function normalize_price_fields(array &$plan, int $offer_id, int $cohort_id): void {
        $table_id = absint(get_post_meta($cohort_id, 'tabla_precio_id', true));
        $note = trim((string) get_post_meta($offer_id, 'precios_nota', true));
        if ($note === '' || $table_id < 1) {
            return;
        }
        $table_note = trim((string) get_post_meta($table_id, 'precios_nota', true));
        if ($table_note === '') {
            self::set($plan, $table_id, 'precios_nota', $note);
            $plan['affected_posts'][$table_id] = true;
        } elseif ($table_note !== $note) {
            $plan['conflicts'][] = sprintf('Oferta %d: la nota de precios difiere de TablaPrecio %d.', $offer_id, $table_id);
        }
    }

    private static function delete_empty_rows(array &$plan, int $post_id): void {
        foreach (get_post_meta($post_id) as $key => $values) {
            $value = get_post_meta($post_id, $key, true);
            if (self::is_empty($value)) {
                self::delete($plan, $post_id, (string) $key);
            }
        }
    }

    private static function find_unknown_fields(array &$plan, array $offer_ids): void {
        $registered = array_keys(get_registered_meta_keys('post', FLACSO_Oferta_Academica::POST_TYPE));
        foreach ($offer_ids as $offer_id) {
            foreach (get_post_meta((int) $offer_id) as $key => $values) {
                if ($key === '' || $key[0] === '_' || in_array($key, $registered, true) || in_array($key, self::OFFER_LEGACY_KEYS, true)) {
                    continue;
                }
                if (str_starts_with($key, 'rank_math_') || str_starts_with($key, 'mailjet_') || str_starts_with($key, 'jetpack_seo_')) {
                    continue;
                }
                if (!self::is_empty(get_post_meta((int) $offer_id, $key, true))) {
                    $plan['conflicts'][] = sprintf('Oferta %d: metadato no clasificado %s.', $offer_id, $key);
                }
            }
        }
    }

    private static function infer_modality(string $value): string {
        $value = strtolower(remove_accents($value));
        if (str_contains($value, 'hibrid')) return 'hibrida';
        if (str_contains($value, 'semipresencial') || str_contains($value, 'semi presencial')) return 'semipresencial';
        if (str_contains($value, 'virtual')) return 'virtual';
        if (str_contains($value, 'presencial')) return 'presencial';
        return '';
    }

    private static function set(array &$plan, int $post_id, string $key, $value): void {
        if (metadata_exists('post', $post_id, $key)) {
            $current = get_post_meta($post_id, $key, true);
            if (maybe_serialize($current) === maybe_serialize($value)) {
                return;
            }
        }
        $plan['set'][$post_id][$key] = $value;
        unset($plan['delete'][$post_id][$key]);
        $plan['affected_posts'][$post_id] = true;
    }

    private static function delete(array &$plan, int $post_id, string $key): void {
        if (isset($plan['set'][$post_id][$key])) {
            return;
        }
        if (metadata_exists('post', $post_id, $key)) {
            $plan['delete'][$post_id][$key] = true;
            $plan['affected_posts'][$post_id] = true;
        }
    }

    private static function is_empty($value): bool {
        return $value === '' || $value === null || $value === [];
    }

    private static function render_plan(array $plan): void {
        $sets = array_sum(array_map('count', $plan['set']));
        $deletes = array_sum(array_map('count', $plan['delete']));
        WP_CLI::line(sprintf('Posts afectados: %d | escrituras: %d | eliminaciones: %d | conflictos: %d', count($plan['affected_posts']), $sets, $deletes, count($plan['conflicts'])));
        foreach ($plan['conflicts'] as $conflict) {
            WP_CLI::warning($conflict);
        }
    }

    private static function write_snapshot(string $path, array $post_ids): void {
        $snapshot = ['created_at' => gmdate('c'), 'posts' => []];
        foreach ($post_ids as $post_id) {
            $snapshot['posts'][(string) $post_id] = [
                'post' => get_post($post_id, ARRAY_A),
                'meta' => get_post_meta($post_id),
            ];
        }
        $written = file_put_contents($path, wp_json_encode($snapshot, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
        if ($written === false) {
            WP_CLI::error('No se pudo escribir el snapshot: ' . $path);
        }
    }

    private static function apply_plan(array $plan): void {
        global $wpdb;
        $wpdb->query('START TRANSACTION');
        try {
            foreach ($plan['set'] as $post_id => $fields) {
                foreach ($fields as $key => $value) {
                    update_post_meta((int) $post_id, $key, $value);
                }
            }
            foreach ($plan['delete'] as $post_id => $keys) {
                foreach (array_keys($keys) as $key) {
                    delete_post_meta((int) $post_id, $key);
                }
            }
            $wpdb->query('COMMIT');
        } catch (Throwable $error) {
            $wpdb->query('ROLLBACK');
            throw $error;
        }
    }

    private static function verify_plan(array $plan): void {
        foreach ($plan['delete'] as $post_id => $keys) {
            foreach (array_keys($keys) as $key) {
                if (metadata_exists('post', (int) $post_id, $key)) {
                    WP_CLI::error(sprintf('Verificación falló: %d todavía contiene %s.', $post_id, $key));
                }
            }
        }
        foreach ($plan['set'] as $post_id => $fields) {
            foreach ($fields as $key => $expected) {
                if (get_post_meta((int) $post_id, $key, true) != $expected) {
                    WP_CLI::error(sprintf('Verificación falló: %d.%s no coincide.', $post_id, $key));
                }
            }
        }
    }
}

WP_CLI::add_command('flacso academic-meta', FLACSO_Academic_Meta_Normalizer::class);
