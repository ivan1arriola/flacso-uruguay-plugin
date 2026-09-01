<?php
/**
 * Integración de WP-CLI para migración y diagnóstico del modelo académico v7.
 */

if (!defined('ABSPATH')) {
    exit;
}

if (!defined('WP_CLI') || !WP_CLI) {
    return;
}

/**
 * Comandos de gestión y migración académica para FLACSO Uruguay.
 */
class FLACSO_Academic_CLI_Command {

    /**
     * Ejecuta la migración y limpieza del modelo académico al estándar v7.
     *
     * ## OPTIONS
     *
     * [--dry-run]
     * : Simula la ejecución sin realizar escrituras en la base de datos.
     *
     * [--force]
     * : Omite la confirmación interactiva.
     *
     * [--data-dir=<path>]
     * : Ruta personalizada hacia el directorio de exportación/staging con los datos JSON.
     *
     * ## EXAMPLES
     *
     *     # Ejecutar simulación
     *     wp flacso academic migrate --dry-run
     *
     *     # Aplicar migración real
     *     wp flacso academic migrate
     *
     * @param array $args
     * @param array $assoc_args
     */
    public function migrate(array $args, array $assoc_args): void {
        global $wpdb;

        $is_dry_run = isset($assoc_args['dry-run']);
        $force = isset($assoc_args['force']);

        if (!$is_dry_run && !$force) {
            WP_CLI::confirm('¿Estás seguro de que deseas ejecutar la migración del modelo académico v7 en la base de datos actual?');
        }

        $data_dir = $assoc_args['data-dir'] ?? null;
        if (!$data_dir) {
            $candidate_paths = [
                FLACSO_URUGUAY_PATH . '../.gemini/antigravity/brain/286909b9-bd28-4f2a-a1c9-c4d1e762b8dd/scratch/export_v7',
                FLACSO_URUGUAY_PATH . 'scripts/export_v7',
                FLACSO_URUGUAY_PATH . 'export_v7',
            ];
            foreach ($candidate_paths as $p) {
                if (is_dir($p)) {
                    $data_dir = realpath($p);
                    break;
                }
            }
        }

        if (!$data_dir || !is_dir($data_dir)) {
            WP_CLI::error("No se encontró el directorio con los datos de exportación JSON (staging). Usa --data-dir=<ruta>");
        }

        WP_CLI::line(WP_CLI::colorize('%G=================================================================%n'));
        WP_CLI::line(WP_CLI::colorize('%GMIGRACIÓN Y LIMPIEZA DEL MODELO ACADÉMICO FLACSO URUGUAY (v7)%n'));
        WP_CLI::line('Modo: ' . ($is_dry_run ? WP_CLI::colorize('%Y[SIMULACIÓN --dry-run]%n') : WP_CLI::colorize('%R[APLICACIÓN REAL]%n')));
        WP_CLI::line("Origen de datos: $data_dir");
        WP_CLI::line(WP_CLI::colorize('%G=================================================================%n'));

        if (!$is_dry_run) {
            $wpdb->query('START TRANSACTION');
        }

        try {
            // =========================================================
            // FASE 1: Tablas de Precio
            // =========================================================
            WP_CLI::log(WP_CLI::colorize('%C▶ FASE 1: Normalizando Tablas de Precio...%n'));
            $tablas = $wpdb->get_results("SELECT ID, post_title FROM {$wpdb->posts} WHERE post_type = 'tabla-precio'");
            foreach ($tablas as $t) {
                $id = (int)$t->ID;
                $raw_filas = get_post_meta($id, 'precios_filas', true);
                if ($raw_filas) {
                    $filas = is_array($raw_filas) ? $raw_filas : json_decode($raw_filas, true);
                    if (!is_array($filas)) {
                        $unser = @unserialize($raw_filas);
                        if (is_array($unser)) $filas = $unser;
                    }
                    if (is_array($filas)) {
                        $normalized_rows = [];
                        foreach ($filas as $f) {
                            $normalized_rows[] = [
                                'concepto' => (string)($f['concepto'] ?? $f['concept'] ?? ''),
                                'uyu' => (string)($f['uyu'] ?? $f['uy'] ?? ''),
                                'usd' => (string)($f['usd'] ?? $f['us'] ?? ''),
                                'destacada' => (bool)($f['destacada'] ?? $f['highlight'] ?? false),
                            ];
                        }
                        WP_CLI::log("  - Tabla #$id ('{$t->post_title}'): " . count($normalized_rows) . " filas normalizadas.");
                        if (!$is_dry_run) {
                            update_post_meta($id, 'precios_filas', $normalized_rows);
                        }
                    }
                }
            }

            // =========================================================
            // FASE 2: Programas Académicos
            // =========================================================
            WP_CLI::log(WP_CLI::colorize('%C▶ FASE 2: Migrando Programas Académicos...%n'));
            $programas_json = self::load_json($data_dir . '/payload_base/programas_academicos.json');
            $programa_map = [];

            foreach ($programas_json as $p) {
                $slug = $p['slug'];
                $title = $p['nombre'];

                $existing = $wpdb->get_var($wpdb->prepare(
                    "SELECT ID FROM {$wpdb->posts} WHERE post_type = 'programa-academico' AND post_name = %s",
                    $slug
                ));

                if ($existing) {
                    $prog_id = (int)$existing;
                    WP_CLI::log("  - Actualizando programa existente: '$title' (ID $prog_id)");
                    if (!$is_dry_run) {
                        wp_update_post([
                            'ID' => $prog_id,
                            'post_title' => $title,
                            'post_status' => 'publish',
                        ]);
                    }
                } else {
                    WP_CLI::log("  - Creando nuevo programa: '$title' (slug '$slug')");
                    if (!$is_dry_run) {
                        $prog_id = wp_insert_post([
                            'post_type' => 'programa-academico',
                            'post_title' => $title,
                            'post_name' => $slug,
                            'post_content' => $p['contenido'] ?? '',
                            'post_excerpt' => $p['resumen'] ?? '',
                            'post_status' => 'publish',
                        ]);
                        if (is_wp_error($prog_id)) {
                            throw new Exception("Error al crear programa $title: " . $prog_id->get_error_message());
                        }
                    } else {
                        $prog_id = 999000 + count($programa_map);
                    }
                }

                $programa_map["programa:$slug"] = $prog_id;
                $programa_map[$slug] = $prog_id;

                if (!$is_dry_run) {
                    update_post_meta($prog_id, 'correo', $p['correo'] ?? '');
                    update_post_meta($prog_id, 'orden', (int)($p['orden'] ?? 0));
                    update_post_meta($prog_id, 'coordinacion', $p['coordinacion'] ?? []);
                }
            }

            // =========================================================
            // FASE 3: Ofertas Académicas
            // =========================================================
            WP_CLI::log(WP_CLI::colorize('%C▶ FASE 3: Migrando y limpiando Ofertas Académicas...%n'));
            $ofertas_json = self::load_json($data_dir . '/staging_local/ofertas_academicas.json');

            foreach ($ofertas_json as $of) {
                $source_key = $of['source_key'];
                $legacy_id = (int)str_replace('oferta:', '', $source_key);
                $prog_source = $of['programa_source_key'];
                $prog_id = $programa_map[$prog_source] ?? null;

                if (!$prog_id) {
                    throw new Exception("No se encontró programa para $prog_source en $source_key");
                }

                $data = $of['data'];
                WP_CLI::log("  - Oferta [ID $legacy_id] '{$data['nombre']}': Prog ID $prog_id | Tipo '{$data['tipo']}'");

                if (!$is_dry_run) {
                    wp_update_post([
                        'ID' => $legacy_id,
                        'post_title' => $data['nombre'],
                        'post_status' => 'publish',
                    ]);

                    update_post_meta($legacy_id, 'programa_academico_id', $prog_id);
                    update_post_meta($legacy_id, 'correo', $data['correo'] ?? '');
                    update_post_meta($legacy_id, 'presentacion', $data['presentacion'] ?? '');
                    update_post_meta($legacy_id, 'objetivo_general', $data['objetivo_general'] ?? '');
                    update_post_meta($legacy_id, 'objetivos_especificos', $data['objetivos_especificos'] ?? []);
                    update_post_meta($legacy_id, 'composicion_academica', $data['composicion_academica'] ?? []);
                    update_post_meta($legacy_id, 'forma_aprobacion', $data['forma_aprobacion'] ?? '');
                    update_post_meta($legacy_id, 'carga_horaria', $data['carga_horaria'] !== null ? (float)$data['carga_horaria'] : '');
                    update_post_meta($legacy_id, 'carga_horaria_descripcion', $data['carga_horaria_descripcion'] ?? '');
                    update_post_meta($legacy_id, 'creditos', $data['creditos'] !== null ? (float)$data['creditos'] : '');
                    update_post_meta($legacy_id, 'acreditacion', $data['acreditacion'] ?? '');

                    // Purgar precios y metadatos legacy
                    delete_post_meta($legacy_id, 'tabla_precio_id');
                    delete_post_meta($legacy_id, 'precios_filas');
                    delete_post_meta($legacy_id, 'precio');
                    delete_post_meta($legacy_id, 'costo');
                    delete_post_meta($legacy_id, 'objetivos_html');

                    // Asignar tipo-oferta-academica
                    wp_set_object_terms($legacy_id, $data['tipo'], 'tipo-oferta-academica', false);

                    // Purgar taxonomías obsoletas
                    wp_delete_object_term_relationships($legacy_id, ['area_tematica', 'seminario_posgrado', 'programa_seminario']);
                }
            }

            // Purgar borradores automáticos
            if (!$is_dry_run) {
                $wpdb->query("DELETE FROM {$wpdb->posts} WHERE post_type = 'oferta-academica' AND post_status = 'auto-draft'");
            }

            // =========================================================
            // FASE 4: Seminarios Canónicos y Duplicados
            // =========================================================
            WP_CLI::log(WP_CLI::colorize('%C▶ FASE 4: Consolidando Seminarios y depurando duplicados...%n'));
            $duplicates_to_delete = [27242, 27254, 27256, 27245, 27258, 27240];
            WP_CLI::log("  - Eliminando seminarios duplicados o corruptos: " . implode(', ', $duplicates_to_delete));
            if (!$is_dry_run) {
                foreach ($duplicates_to_delete as $dup_id) {
                    wp_delete_post($dup_id, true);
                }
            }

            $seminarios_json = self::load_json($data_dir . '/staging_local/seminarios.json');
            foreach ($seminarios_json as $sem) {
                $source_key = $sem['source_key'];
                if ($source_key === 'seminario:27240') {
                    continue;
                }
                $canonical_id = (int)str_replace('seminario:', '', $source_key);
                $prog_source = $sem['programa_source_key'];
                $prog_id = $programa_map[$prog_source] ?? null;

                if (!$prog_id) {
                    throw new Exception("No se encontró programa para $prog_source en seminario $source_key");
                }

                $data = $sem['data'];
                WP_CLI::log("  - Seminario [ID $canonical_id] '{$data['nombre']}': Prog ID $prog_id");

                if (!$is_dry_run) {
                    wp_update_post([
                        'ID' => $canonical_id,
                        'post_title' => $data['nombre'],
                        'post_name' => $data['slug'],
                        'post_content' => $data['contenido'] ?? '',
                        'post_status' => 'publish',
                    ]);

                    update_post_meta($canonical_id, 'programa_academico_id', $prog_id);
                    update_post_meta($canonical_id, 'correo', $data['correo'] ?? '');
                    update_post_meta($canonical_id, 'presentacion', $data['presentacion'] ?? '');
                    update_post_meta($canonical_id, 'objetivo_general', $data['objetivo_general'] ?? '');
                    update_post_meta($canonical_id, 'objetivos_especificos', $data['objetivos_especificos'] ?? []);
                    update_post_meta($canonical_id, 'composicion_academica', $data['composicion_academica'] ?? []);
                    update_post_meta($canonical_id, 'forma_aprobacion', $data['forma_aprobacion'] ?? '');
                    update_post_meta($canonical_id, 'carga_horaria', $data['carga_horaria'] !== null ? (float)$data['carga_horaria'] : '');
                    update_post_meta($canonical_id, 'carga_horaria_descripcion', $data['carga_horaria_descripcion'] ?? '');
                    update_post_meta($canonical_id, 'creditos', $data['creditos'] !== null ? (float)$data['creditos'] : '');
                    update_post_meta($canonical_id, 'acreditacion', $data['acreditacion'] ?? '');
                    update_post_meta($canonical_id, 'acredita_maestria', (bool)($data['acredita_maestria'] ?? false));
                    update_post_meta($canonical_id, 'acredita_doctorado', (bool)($data['acredita_doctorado'] ?? false));
                    update_post_meta($canonical_id, 'docentes_base', $data['docentes_base'] ?? []);

                    delete_post_meta($canonical_id, 'tabla_precio_id');
                    delete_post_meta($canonical_id, 'precios_filas');
                    delete_post_meta($canonical_id, 'precio');
                    delete_post_meta($canonical_id, 'costo');
                    delete_post_meta($canonical_id, 'precios_nota');
                    wp_delete_object_term_relationships($canonical_id, ['area_tematica', 'seminario_posgrado', 'programa_seminario']);
                }
            }

            // =========================================================
            // FASE 5: Recreación de Cohortes
            // =========================================================
            WP_CLI::log(WP_CLI::colorize('%C▶ FASE 5: Recreando Cohortes canónicas...%n'));
            if (!$is_dry_run) {
                $old_cohortes = $wpdb->get_col("SELECT ID FROM {$wpdb->posts} WHERE post_type = 'cohorte'");
                foreach ($old_cohortes as $old_cid) {
                    wp_delete_post((int)$old_cid, true);
                }
            }

            $tabla_precio_map = [
                24160 => 26484, 24161 => 26484, 24162 => 26484,
                24163 => 26487, 24164 => 26485, 24165 => 26487,
                24166 => 26487, 24167 => 26487, 24168 => 26487,
                24169 => 26481, 24170 => 26481, 24171 => 26481,
                24172 => 26488, 24173 => 26481, 24174 => 26486,
                26595 => 26625,
            ];

            $cohortes_json = self::load_json($data_dir . '/staging_local/cohortes.json');
            foreach ($cohortes_json as $c_entry) {
                $of_source = $c_entry['oferta_source_key'];
                $of_id = (int)str_replace('oferta:', '', $of_source);
                $d = $c_entry['data'];

                $num = (int)($d['numero'] ?: 1);
                $roman = self::to_roman($num);
                $canonical_title = "Cohorte $roman";
                $tp_id = $tabla_precio_map[$of_id] ?? null;
                $link_pre = "https://preinscripciones.flacso.edu.uy/ofertas/$of_id/instancias/cohorte-$num/";

                WP_CLI::log("  - Creando $canonical_title para Oferta #$of_id (Número: $num, Estado: {$d['estado']})");

                if (!$is_dry_run) {
                    $cohorte_id = wp_insert_post([
                        'post_type' => 'cohorte',
                        'post_title' => $canonical_title,
                        'post_name' => "cohorte-" . strtolower($roman),
                        'post_parent' => $of_id,
                        'post_status' => 'publish',
                    ]);

                    if (is_wp_error($cohorte_id)) {
                        throw new Exception("Error al crear cohorte: " . $cohorte_id->get_error_message());
                    }

                    update_post_meta($cohorte_id, 'oferta_academica_id', $of_id);
                    update_post_meta($cohorte_id, 'numero', $num);
                    update_post_meta($cohorte_id, 'fecha_inicio', $d['fecha_inicio'] ?? '2027-01-01');
                    update_post_meta($cohorte_id, 'fecha_fin', $d['fecha_fin'] ?? '');
                    update_post_meta($cohorte_id, 'precision_fecha_inicio', $d['precision_fecha_inicio'] ?? 'anio');
                    update_post_meta($cohorte_id, 'estado', $d['estado'] ?? 'planificada');
                    update_post_meta($cohorte_id, 'calendario_academico', $d['calendario_academico'] ?? '');
                    update_post_meta($cohorte_id, 'tabla_precio_id', $tp_id ?: '');
                    update_post_meta($cohorte_id, 'link_preinscripcion', $link_pre);
                    update_post_meta($cohorte_id, 'preinscripcion_desde', $d['preinscripcion_desde'] ?? '');
                    update_post_meta($cohorte_id, 'preinscripcion_hasta', $d['preinscripcion_hasta'] ?? '');
                }
            }

            // =========================================================
            // FASE 6: Ediciones de Seminario
            // =========================================================
            WP_CLI::log(WP_CLI::colorize('%C▶ FASE 6: Creando 53 Ediciones de Seminario...%n'));
            if (!$is_dry_run) {
                $old_eds = $wpdb->get_col("SELECT ID FROM {$wpdb->posts} WHERE post_type = 'edicion-seminario'");
                foreach ($old_eds as $old_eid) {
                    wp_delete_post((int)$old_eid, true);
                }
            }

            $sem_canonical_alias = [
                27242 => 24299, 27254 => 23902, 27256 => 25623,
                27245 => 24432, 27258 => 23904,
            ];

            $ediciones_json = self::load_json($data_dir . '/staging_local/ediciones_seminario.json');
            foreach ($ediciones_json as $ed) {
                $source_key = $ed['source_key'];
                if ($source_key === 'edicion:27240') {
                    continue;
                }
                $sem_source = $ed['seminario_source_key'];
                $raw_sem_id = (int)str_replace('seminario:', '', $sem_source);
                $sem_id = $sem_canonical_alias[$raw_sem_id] ?? $raw_sem_id;
                $d = $ed['data'];
                $title = $d['nombre'];

                WP_CLI::log("  - Edición '{$title}' para Seminario #$sem_id ({$d['anio']}, {$d['estado']})");

                if (!$is_dry_run) {
                    $slug_ed = sanitize_title_with_dashes($title) . '-' . $d['anio'];
                    $ed_id = wp_insert_post([
                        'post_type' => 'edicion-seminario',
                        'post_title' => $title,
                        'post_name' => $slug_ed,
                        'post_parent' => $sem_id,
                        'post_status' => 'publish',
                    ]);

                    if (is_wp_error($ed_id)) {
                        throw new Exception("Error al crear edición: " . $ed_id->get_error_message());
                    }

                    update_post_meta($ed_id, 'seminario_id', $sem_id);
                    update_post_meta($ed_id, 'anio', (int)($d['anio'] ?? 2026));
                    update_post_meta($ed_id, 'fecha_inicio', $d['fecha_inicio'] ?? '');
                    update_post_meta($ed_id, 'fecha_fin', $d['fecha_fin'] ?? '');
                    update_post_meta($ed_id, 'estado', $d['estado'] ?? 'finalizada');
                    update_post_meta($ed_id, 'modalidad', $d['modalidad'] ?? '');
                    update_post_meta($ed_id, 'encuentros_sincronicos', $d['encuentros_sincronicos'] ?? []);
                    update_post_meta($ed_id, 'docentes', $d['docentes'] ?? []);
                    update_post_meta($ed_id, 'tabla_precio_id', $d['tabla_precio_id'] ?? '');
                    update_post_meta($ed_id, 'preinscripcion_desde', $d['preinscripcion_desde'] ?? '');
                    update_post_meta($ed_id, 'preinscripcion_hasta', $d['preinscripcion_hasta'] ?? '');
                    update_post_meta($ed_id, 'link_preinscripcion', $d['link_preinscripcion'] ?? '');
                    update_post_meta($ed_id, 'mensaje_preinscripcion_abierta', $d['mensaje_preinscripcion_abierta'] ?? '');
                    update_post_meta($ed_id, 'mensaje_preinscripcion_cerrada', $d['mensaje_preinscripcion_cerrada'] ?? '');
                    update_post_meta($ed_id, 'mostrar_en_formulario', (bool)($d['mostrar_en_formulario'] ?? false));
                    update_post_meta($ed_id, 'ediciones_componentes', $d['ediciones_componentes'] ?? []);
                }
            }

            // =========================================================
            // FASE 7: Relaciones Cruzadas
            // =========================================================
            WP_CLI::log(WP_CLI::colorize('%C▶ FASE 7: Vinculando Relaciones Cruzadas...%n'));
            $rel_oferta_sem = self::load_json($data_dir . '/staging_local/relaciones_oferta_seminario.json');
            $ofertas_seminarios_grouped = [];

            foreach ($rel_oferta_sem as $rel) {
                $of_id = (int)str_replace('oferta:', '', $rel['oferta_source_key']);
                $raw_sem_id = (int)str_replace('seminario:', '', $rel['seminario_source_key']);
                $sem_id = $sem_canonical_alias[$raw_sem_id] ?? $raw_sem_id;

                if (!isset($ofertas_seminarios_grouped[$of_id])) {
                    $ofertas_seminarios_grouped[$of_id] = [];
                }

                $exists = false;
                foreach ($ofertas_seminarios_grouped[$of_id] as $existing_r) {
                    if ($existing_r['seminario_id'] === $sem_id) {
                        $exists = true;
                        break;
                    }
                }
                if (!$exists) {
                    $ofertas_seminarios_grouped[$of_id][] = [
                        'seminario_id' => $sem_id,
                        'orden' => (int)($rel['orden'] ?? count($ofertas_seminarios_grouped[$of_id])),
                        'caracter' => $rel['caracter'] ?: 'obligatorio',
                        'creditos_reconocidos' => $rel['creditos_reconocidos'] !== null ? (float)$rel['creditos_reconocidos'] : 4,
                    ];
                }
            }

            foreach ($ofertas_seminarios_grouped as $of_id => $sems_arr) {
                WP_CLI::log("  - Oferta #$of_id: guardando " . count($sems_arr) . " relaciones con seminarios.");
                if (!$is_dry_run) {
                    update_post_meta($of_id, 'seminarios', $sems_arr);
                }
            }

            // Componentes de seminarios
            $comp_json = self::load_json($data_dir . '/staging_local/componentes_seminario.json');
            $comp_grouped = [];
            foreach ($comp_json as $c) {
                $padre_id = (int)str_replace('seminario:', '', $c['seminario_source_key'] ?? $c['seminario_padre_source_key'] ?? '');
                $hijo_id = (int)str_replace('seminario:', '', $c['componente_source_key'] ?? $c['seminario_hijo_source_key'] ?? '');
                $hijo_id = $sem_canonical_alias[$hijo_id] ?? $hijo_id;

                if (!isset($comp_grouped[$padre_id])) {
                    $comp_grouped[$padre_id] = [];
                }
                $comp_grouped[$padre_id][] = [
                    'seminario_id' => $hijo_id,
                    'orden' => (int)($c['orden'] ?? count($comp_grouped[$padre_id])),
                ];
            }
            foreach ($comp_grouped as $padre_id => $comps) {
                WP_CLI::log("  - Seminario compuesto #$padre_id: guardando " . count($comps) . " componentes.");
                if (!$is_dry_run) {
                    update_post_meta($padre_id, 'componentes', $comps);
                }
            }

            // =========================================================
            // FASE 8: Limpieza de Taxonomías Obsoletas
            // =========================================================
            WP_CLI::log(WP_CLI::colorize('%C▶ FASE 8: Limpieza de taxonomías obsoletas...%n'));
            if (!$is_dry_run) {
                $wpdb->query("
                    DELETE tr FROM {$wpdb->term_relationships} tr
                    JOIN {$wpdb->term_taxonomy} tt ON tr.term_taxonomy_id = tt.term_taxonomy_id
                    WHERE tt.taxonomy IN ('area_tematica', 'seminario_posgrado', 'programa_seminario')
                ");
                $wpdb->query("
                    UPDATE {$wpdb->term_taxonomy}
                    SET count = 0
                    WHERE taxonomy IN ('area_tematica', 'seminario_posgrado', 'programa_seminario')
                ");
            }

            if (!$is_dry_run) {
                $wpdb->query('COMMIT');
                WP_CLI::success('Migración académica v7 completada exitosamente.');
            } else {
                WP_CLI::success('Simulación completada sin errores (--dry-run). No se modificó la base de datos.');
            }

        } catch (Throwable $e) {
            if (!$is_dry_run) {
                $wpdb->query('ROLLBACK');
                WP_CLI::warning('Transacción revertida por error.');
            }
            WP_CLI::error($e->getMessage());
        }
    }

    /**
     * Muestra el estado del modelo académico y conteos de entidades.
     *
     * ## EXAMPLES
     *
     *     wp flacso academic status
     */
    public function status(array $args, array $assoc_args): void {
        global $wpdb;

        $cpts = [
            'programa-academico' => 'Programas Académicos (esperados: 7)',
            'oferta-academica' => 'Ofertas Académicas (esperadas: 16)',
            'cohorte' => 'Cohortes (esperadas: 16)',
            'seminario' => 'Seminarios (esperados: 48)',
            'edicion-seminario' => 'Ediciones de Seminario (esperadas: 53)',
            'tabla-precio' => 'Tablas de Precio (esperadas: 7)',
        ];

        WP_CLI::line(WP_CLI::colorize('%G=================================================================%n'));
        WP_CLI::line(WP_CLI::colorize('%GESTADO DEL MODELO ACADÉMICO FLACSO URUGUAY (v7)%n'));
        WP_CLI::line(WP_CLI::colorize('%G=================================================================%n'));

        $items = [];
        foreach ($cpts as $post_type => $label) {
            $count = (int)$wpdb->get_var($wpdb->prepare(
                "SELECT COUNT(*) FROM {$wpdb->posts} WHERE post_type = %s AND post_status = 'publish'",
                $post_type
            ));
            $items[] = [
                'post_type' => $post_type,
                'entidad' => $label,
                'publicados' => $count,
            ];
        }

        WP_CLI\Utils\format_items('table', $items, ['post_type', 'entidad', 'publicados']);
    }

    private static function load_json(string $path): array {
        if (!file_exists($path)) {
            throw new Exception("Archivo no encontrado: $path");
        }
        $data = json_decode(file_get_contents($path), true);
        if ($data === null) {
            throw new Exception("Error al parsear JSON: $path");
        }
        return $data;
    }

    private static function to_roman(int $num): string {
        if ($num <= 0) return '';
        $map = [
            1000 => 'M', 900 => 'CM', 500 => 'D', 400 => 'CD',
            100 => 'C', 90 => 'XC', 50 => 'L', 40 => 'XL',
            10 => 'X', 9 => 'IX', 5 => 'V', 4 => 'IV', 1 => 'I'
        ];
        $res = '';
        foreach ($map as $val => $sym) {
            while ($num >= $val) {
                $res .= $sym;
                $num -= $val;
            }
        }
        return $res;
    }
}

WP_CLI::add_command('flacso academic', FLACSO_Academic_CLI_Command::class);

