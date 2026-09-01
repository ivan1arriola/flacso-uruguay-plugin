<?php
/**
 * Script de migración y limpieza del modelo académico de FLACSO Uruguay (v7).
 *
 * Uso:
 *   php migrate_academic_v7.php --dry-run
 *   php migrate_academic_v7.php --apply
 */

declare(strict_types=1);

$options = getopt('', ['dry-run', 'apply', 'host:', 'port:', 'dbname:', 'user:', 'password:']);
$is_dry_run = isset($options['dry-run']) || !isset($options['apply']);

$db_host = $options['host'] ?? getenv('DB_HOST') ?: '127.0.0.1';
$db_port = $options['port'] ?? getenv('DB_PORT') ?: '3307';
$db_name = $options['dbname'] ?? getenv('DB_NAME') ?: 'flacso';
$db_user = $options['user'] ?? getenv('DB_USER') ?: 'antigravity_ro';
$db_pass = $options['password'] ?? getenv('DB_PASSWORD') ?: '7cdead317928d9aae419ab90f1c889205bec95e899318cd1';

echo "=================================================================\n";
echo "MIGRACIÓN Y LIMPIEZA DEL MODELO ACADÉMICO FLACSO URUGUAY (v7)\n";
echo "Modo: " . ($is_dry_run ? "SIMULACIÓN (--dry-run, no se guardarán cambios)" : "EJECUCIÓN REAL (--apply)") . "\n";
echo "Conexión: $db_user@$db_host:$db_port/$db_name\n";
echo "=================================================================\n\n";

try {
    $pdo = new PDO("mysql:host=$db_host;port=$db_port;dbname=$db_name;charset=utf8mb4", $db_user, $db_pass, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    ]);
} catch (PDOException $e) {
    die("❌ Error conectando a la base de datos: " . $e->getMessage() . "\n");
}

// Ruta a datos base
$data_dir = '/home/ivan/.gemini/antigravity/brain/286909b9-bd28-4f2a-a1c9-c4d1e762b8dd/scratch/export_v7';
if (!is_dir($data_dir)) {
    $data_dir = __DIR__ . '/export_v7';
}

function load_json(string $path): array {
    if (!file_exists($path)) {
        throw new RuntimeException("Archivo no encontrado: $path");
    }
    $content = file_get_contents($path);
    $data = json_decode($content, true);
    if ($data === null && json_last_error() !== JSON_ERROR_NONE) {
        throw new RuntimeException("Error decodificando JSON en $path: " . json_last_error_msg());
    }
    return $data;
}

function to_roman(int $num): string {
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

function ensure_meta(PDO $pdo, int $post_id, string $meta_key, $meta_value): void {
    $serialized_val = is_array($meta_value) || is_object($meta_value) ? serialize($meta_value) : (string)$meta_value;
    $stmt = $pdo->prepare("SELECT meta_id FROM wp_postmeta WHERE post_id = ? AND meta_key = ?");
    $stmt->execute([$post_id, $meta_key]);
    $existing = $stmt->fetch();
    if ($existing) {
        $upd = $pdo->prepare("UPDATE wp_postmeta SET meta_value = ? WHERE meta_id = ?");
        $upd->execute([$serialized_val, $existing['meta_id']]);
    } else {
        $ins = $pdo->prepare("INSERT INTO wp_postmeta (post_id, meta_key, meta_value) VALUES (?, ?, ?)");
        $ins->execute([$post_id, $meta_key, $serialized_val]);
    }
}

function delete_meta_keys(PDO $pdo, int $post_id, array $keys): void {
    if (empty($keys)) return;
    $in_clause = implode(',', array_fill(0, count($keys), '?'));
    $stmt = $pdo->prepare("DELETE FROM wp_postmeta WHERE post_id = ? AND meta_key IN ($in_clause)");
    $stmt->execute(array_merge([$post_id], $keys));
}

function remove_obsolete_taxonomies(PDO $pdo, int $post_id, array $taxonomies = ['area_tematica', 'seminario_posgrado', 'programa_seminario']): void {
    if (empty($taxonomies)) return;
    $in_clause = implode(',', array_fill(0, count($taxonomies), '?'));
    $stmt = $pdo->prepare("
        DELETE tr FROM wp_term_relationships tr
        JOIN wp_term_taxonomy tt ON tr.term_taxonomy_id = tt.term_taxonomy_id
        WHERE tr.object_id = ? AND tt.taxonomy IN ($in_clause)
    ");
    $stmt->execute(array_merge([$post_id], $taxonomies));
}

function assign_taxonomy_term(PDO $pdo, int $post_id, string $taxonomy, string $term_slug): void {
    $stmt = $pdo->prepare("
        SELECT tt.term_taxonomy_id 
        FROM wp_term_taxonomy tt
        JOIN wp_terms t ON tt.term_id = t.term_id
        WHERE tt.taxonomy = ? AND t.slug = ?
    ");
    $stmt->execute([$taxonomy, $term_slug]);
    $tt = $stmt->fetch();
    if (!$tt) return;
    $tt_id = (int)$tt['term_taxonomy_id'];

    $chk = $pdo->prepare("SELECT * FROM wp_term_relationships WHERE object_id = ? AND term_taxonomy_id = ?");
    $chk->execute([$post_id, $tt_id]);
    if (!$chk->fetch()) {
        $ins = $pdo->prepare("INSERT INTO wp_term_relationships (object_id, term_taxonomy_id, term_order) VALUES (?, ?, 0)");
        $ins->execute([$post_id, $tt_id]);
    }
}

function sanitize_title_with_dashes(string $title): string {
    $title = strip_tags($title);
    $title = mb_strtolower($title, 'UTF-8');
    $title = preg_replace('/[^a-z0-9_-]/', '-', $title);
    $title = preg_replace('/-+/', '-', $title);
    return trim($title, '-');
}

try {
    if (!$is_dry_run) {
        $pdo->beginTransaction();
    }

    // =========================================================================
    // FASE 1: NORMALIZACIÓN DE TABLAS DE PRECIO (tabla-precio)
    // =========================================================================
    echo "▶ FASE 1: Normalizando Tablas de Precio...\n";
    $tablas = $pdo->query("SELECT ID, post_title FROM wp_posts WHERE post_type = 'tabla-precio'")->fetchAll();
    foreach ($tablas as $t) {
        $id = (int)$t['ID'];
        $mstmt = $pdo->prepare("SELECT meta_key, meta_value FROM wp_postmeta WHERE post_id = ?");
        $mstmt->execute([$id]);
        $metas = [];
        foreach ($mstmt->fetchAll() as $row) {
            $metas[$row['meta_key']] = $row['meta_value'];
        }

        $raw_filas = $metas['precios_filas'] ?? null;
        if ($raw_filas) {
            $filas = is_array($raw_filas) ? $raw_filas : json_decode($raw_filas, true);
            if (!is_array($filas)) {
                $unser = @unserialize($raw_filas);
                if (is_array($unser)) $filas = $unser;
            }
            if (is_array($filas)) {
                $normalized_rows = [];
                foreach ($filas as $f) {
                    $concepto = $f['concepto'] ?? $f['concept'] ?? '';
                    $uyu = $f['uyu'] ?? $f['uy'] ?? '';
                    $usd = $f['usd'] ?? $f['us'] ?? '';
                    $destacada = (bool)($f['destacada'] ?? $f['highlight'] ?? false);
                    $normalized_rows[] = [
                        'concepto' => $concepto,
                        'uyu' => $uyu,
                        'usd' => $usd,
                        'destacada' => $destacada,
                    ];
                }
                echo "  - Normalizando tabla #$id ('{$t['post_title']}'): " . count($normalized_rows) . " filas de precios.\n";
                if (!$is_dry_run) {
                    ensure_meta($pdo, $id, 'precios_filas', $normalized_rows);
                }
            }
        }
    }

    // =========================================================================
    // FASE 2: MIGRACIÓN DE PROGRAMAS ACADÉMICOS (programa-academico)
    // =========================================================================
    echo "\n▶ FASE 2: Migrando Programas Académicos...\n";
    $programas_json = load_json($data_dir . '/payload_base/programas_academicos.json');
    $programa_map = []; // slug -> ID

    foreach ($programas_json as $p) {
        $slug = $p['slug'];
        $title = $p['nombre'];
        
        $chk = $pdo->prepare("SELECT ID FROM wp_posts WHERE post_type = 'programa-academico' AND post_name = ?");
        $chk->execute([$slug]);
        $existing = $chk->fetch();

        if ($existing) {
            $prog_id = (int)$existing['ID'];
            echo "  - Actualizando programa existente: '$title' (ID $prog_id, slug '$slug')\n";
            if (!$is_dry_run) {
                $upd = $pdo->prepare("UPDATE wp_posts SET post_title = ?, post_status = 'publish' WHERE ID = ?");
                $upd->execute([$title, $prog_id]);
            }
        } else {
            echo "  - Creando nuevo programa académico: '$title' (slug '$slug')\n";
            if (!$is_dry_run) {
                $ins = $pdo->prepare("
                    INSERT INTO wp_posts (post_author, post_date, post_date_gmt, post_content, post_title, post_excerpt, post_status, comment_status, ping_status, post_name, post_modified, post_modified_gmt, post_type)
                    VALUES (1, NOW(), NOW(), ?, ?, ?, 'publish', 'closed', 'closed', ?, NOW(), NOW(), 'programa-academico')
                ");
                $ins->execute([$p['contenido'] ?? '', $title, $p['resumen'] ?? '', $slug]);
                $prog_id = (int)$pdo->lastInsertId();
            } else {
                $prog_id = 999000 + count($programa_map);
            }
        }
        $programa_map["programa:$slug"] = $prog_id;
        $programa_map[$slug] = $prog_id;

        if (!$is_dry_run) {
            ensure_meta($pdo, $prog_id, 'correo', $p['correo'] ?? '');
            ensure_meta($pdo, $prog_id, 'orden', (int)($p['orden'] ?? 0));
            ensure_meta($pdo, $prog_id, 'coordinacion', $p['coordinacion'] ?? []);
        }
    }
    echo "  Total programas mapeados: " . count($programa_map) . "\n";

    // =========================================================================
    // FASE 3: MIGRACIÓN Y LIMPIEZA DE OFERTAS ACADÉMICAS (oferta-academica)
    // =========================================================================
    echo "\n▶ FASE 3: Migrando y limpiando Ofertas Académicas...\n";
    $ofertas_json = load_json($data_dir . '/staging_local/ofertas_academicas.json');
    $oferta_map = []; // source_key -> ID

    foreach ($ofertas_json as $of) {
        $source_key = $of['source_key'];
        $legacy_id = (int)str_replace('oferta:', '', $source_key);
        $prog_source = $of['programa_source_key'];
        $prog_id = $programa_map[$prog_source] ?? null;

        if (!$prog_id) {
            throw new RuntimeException("No se encontró programa_academico_id para $prog_source en $source_key");
        }

        $data = $of['data'];
        $oferta_map[$source_key] = $legacy_id;

        echo "  - Oferta [ID $legacy_id] '{$data['nombre']}': asignando Programa ID $prog_id, tipo '{$data['tipo']}'\n";

        if (!$is_dry_run) {
            // Actualizar post
            $upd = $pdo->prepare("UPDATE wp_posts SET post_title = ?, post_status = 'publish' WHERE ID = ? AND post_type = 'oferta-academica'");
            $upd->execute([$data['nombre'], $legacy_id]);

            // Asignar meta académico limpio
            ensure_meta($pdo, $legacy_id, 'programa_academico_id', $prog_id);
            ensure_meta($pdo, $legacy_id, 'correo', $data['correo'] ?? '');
            ensure_meta($pdo, $legacy_id, 'presentacion', $data['presentacion'] ?? '');
            ensure_meta($pdo, $legacy_id, 'objetivo_general', $data['objetivo_general'] ?? '');
            ensure_meta($pdo, $legacy_id, 'objetivos_especificos', $data['objetivos_especificos'] ?? []);
            ensure_meta($pdo, $legacy_id, 'composicion_academica', $data['composicion_academica'] ?? []);
            ensure_meta($pdo, $legacy_id, 'forma_aprobacion', $data['forma_aprobacion'] ?? '');
            ensure_meta($pdo, $legacy_id, 'carga_horaria', $data['carga_horaria'] !== null ? (float)$data['carga_horaria'] : '');
            ensure_meta($pdo, $legacy_id, 'carga_horaria_descripcion', $data['carga_horaria_descripcion'] ?? '');
            ensure_meta($pdo, $legacy_id, 'creditos', $data['creditos'] !== null ? (float)$data['creditos'] : '');
            ensure_meta($pdo, $legacy_id, 'acreditacion', $data['acreditacion'] ?? '');

            // Purgar campos indebidos de precios y legacy meta
            delete_meta_keys($pdo, $legacy_id, ['tabla_precio_id', 'precios_filas', 'precio', 'costo', 'objetivos_html', 'destinatarios', 'metodologia', 'requisitos_ingreso']);

            // Purgar taxonomías obsoletas
            remove_obsolete_taxonomies($pdo, $legacy_id);

            // Asignar tipo-oferta-academica
            assign_taxonomy_term($pdo, $legacy_id, 'tipo-oferta-academica', $data['tipo']);
        }
    }

    // Purgar borrador automático de oferta si existe (ID 27260)
    if (!$is_dry_run) {
        $pdo->exec("DELETE FROM wp_posts WHERE post_type = 'oferta-academica' AND post_status = 'auto-draft'");
    }

    // =========================================================================
    // FASE 4: CONSOLIDACIÓN Y LIMPIEZA DE SEMINARIOS (seminario)
    // =========================================================================
    echo "\n▶ FASE 4: Consolidando Seminarios y eliminando duplicados/borradores...\n";
    
    // 1. Eliminar duplicados conocidos y borrador corrupto
    $duplicates_to_delete = [27242, 27254, 27256, 27245, 27258, 27240];
    echo "  - Eliminando seminarios duplicados o corruptos: " . implode(', ', $duplicates_to_delete) . "\n";
    if (!$is_dry_run) {
        $in_dup = implode(',', $duplicates_to_delete);
        $pdo->exec("DELETE FROM wp_postmeta WHERE post_id IN ($in_dup)");
        $pdo->exec("DELETE FROM wp_term_relationships WHERE object_id IN ($in_dup)");
        $pdo->exec("DELETE FROM wp_posts WHERE ID IN ($in_dup) AND post_type = 'seminario'");
    }

    // 2. Cargar los 49 seminarios canónicos
    $seminarios_json = load_json($data_dir . '/staging_local/seminarios.json');
    $seminario_map = []; // source_key -> ID

    foreach ($seminarios_json as $sem) {
        $source_key = $sem['source_key'];
        $canonical_id = (int)str_replace('seminario:', '', $source_key);
        $prog_source = $sem['programa_source_key'];
        $prog_id = $programa_map[$prog_source] ?? null;

        if (!$prog_id) {
            throw new RuntimeException("No se encontró programa_academico_id para $prog_source en seminario $source_key");
        }

        $data = $sem['data'];
        $seminario_map[$source_key] = $canonical_id;

        echo "  - Seminario [ID $canonical_id] '{$data['nombre']}': Prog ID $prog_id\n";

        if (!$is_dry_run) {
            // Asegurar que el post existe y está publicado
            $chk = $pdo->prepare("SELECT ID FROM wp_posts WHERE ID = ? AND post_type = 'seminario'");
            $chk->execute([$canonical_id]);
            if ($chk->fetch()) {
                $upd = $pdo->prepare("UPDATE wp_posts SET post_title = ?, post_name = ?, post_content = ?, post_status = 'publish' WHERE ID = ?");
                $upd->execute([$data['nombre'], $data['slug'], $data['contenido'] ?? '', $canonical_id]);
            } else {
                $ins = $pdo->prepare("
                    INSERT INTO wp_posts (ID, post_author, post_date, post_date_gmt, post_content, post_title, post_excerpt, post_status, comment_status, ping_status, post_name, post_modified, post_modified_gmt, post_type)
                    VALUES (?, 1, NOW(), NOW(), ?, ?, '', 'publish', 'closed', 'closed', ?, NOW(), NOW(), 'seminario')
                ");
                $ins->execute([$canonical_id, $data['contenido'] ?? '', $data['nombre'], $data['slug']]);
            }

            // Metas canónicos
            ensure_meta($pdo, $canonical_id, 'programa_academico_id', $prog_id);
            ensure_meta($pdo, $canonical_id, 'correo', $data['correo'] ?? '');
            ensure_meta($pdo, $canonical_id, 'presentacion', $data['presentacion'] ?? '');
            ensure_meta($pdo, $canonical_id, 'objetivo_general', $data['objetivo_general'] ?? '');
            ensure_meta($pdo, $canonical_id, 'objetivos_especificos', $data['objetivos_especificos'] ?? []);
            ensure_meta($pdo, $canonical_id, 'composicion_academica', $data['composicion_academica'] ?? []);
            ensure_meta($pdo, $canonical_id, 'forma_aprobacion', $data['forma_aprobacion'] ?? '');
            ensure_meta($pdo, $canonical_id, 'carga_horaria', $data['carga_horaria'] !== null ? (float)$data['carga_horaria'] : '');
            ensure_meta($pdo, $canonical_id, 'carga_horaria_descripcion', $data['carga_horaria_descripcion'] ?? '');
            ensure_meta($pdo, $canonical_id, 'creditos', $data['creditos'] !== null ? (float)$data['creditos'] : '');
            ensure_meta($pdo, $canonical_id, 'acreditacion', $data['acreditacion'] ?? '');
            ensure_meta($pdo, $canonical_id, 'acredita_maestria', (bool)($data['acredita_maestria'] ?? false));
            ensure_meta($pdo, $canonical_id, 'acredita_doctorado', (bool)($data['acredita_doctorado'] ?? false));
            ensure_meta($pdo, $canonical_id, 'docentes_base', $data['docentes_base'] ?? []);

            // Purgar precios y campos obsoletos en seminario
            delete_meta_keys($pdo, $canonical_id, ['tabla_precio_id', 'precios_filas', 'precio', 'costo', 'precios_nota', 'modalidad', 'periodo', 'unidades']);
            remove_obsolete_taxonomies($pdo, $canonical_id);
        }
    }

    // =========================================================================
    // FASE 5: DEPURACIÓN Y RECREACIÓN DE COHORTES (cohorte)
    // =========================================================================
    echo "\n▶ FASE 5: Recreando Cohortes canónicas...\n";
    
    // 1. Eliminar cohortes placeholder vacías o inválidas en la BD
    if (!$is_dry_run) {
        $old_cohortes = $pdo->query("SELECT ID FROM wp_posts WHERE post_type = 'cohorte'")->fetchAll(PDO::FETCH_COLUMN);
        if (!empty($old_cohortes)) {
            $in_c = implode(',', $old_cohortes);
            echo "  - Eliminando " . count($old_cohortes) . " cohortes placeholder antiguas...\n";
            $pdo->exec("DELETE FROM wp_postmeta WHERE post_id IN ($in_c)");
            $pdo->exec("DELETE FROM wp_posts WHERE ID IN ($in_c)");
        }
    }

    // 2. Mapeo de tablas de precios por oferta
    $tabla_precio_map = [
        24160 => 26484, // EduTIC -> Maestrías
        24161 => 26484, // MESyP -> Maestrías
        24162 => 26484, // Maestría en Género -> Maestrías
        24163 => 26487, // EGCCD
        24164 => 26485, // EAPET
        24165 => 26487, // Diplomado Género Políticas Públicas
        24166 => 26487, // Diplomado Género Salud Integral
        24167 => 26487, // Diplomado Género VBG
        24168 => 26487, // Diplomado VSNNA
        24169 => 26481, // Diploma China -> Diplomas General
        24170 => 26481, // Diploma VSNNA -> Diplomas General
        24171 => 26481, // Diploma Género -> Diplomas General
        24172 => 26488, // Diploma IAPE
        24173 => 26481, // Diploma Infancias y Políticas -> Diplomas General
        24174 => 26486, // Diploma Salud Mental
        26595 => 26625, // Diploma Archivos
    ];

    $cohortes_json = load_json($data_dir . '/staging_local/cohortes.json');
    $cohorte_map = [];

    foreach ($cohortes_json as $c_entry) {
        $source_key = $c_entry['source_key'];
        $of_source = $c_entry['oferta_source_key'];
        $of_id = (int)str_replace('oferta:', '', $of_source);
        $d = $c_entry['data'];

        $num = (int)($d['numero'] ?: 1);
        $roman = to_roman($num);
        $canonical_title = "Cohorte $roman";
        $tp_id = $tabla_precio_map[$of_id] ?? null;

        $link_pre = "https://preinscripciones.flacso.edu.uy/ofertas/$of_id/instancias/cohorte-$num/";

        echo "  - Creando $canonical_title para Oferta #$of_id (Número: $num, Estado: {$d['estado']}, TablaPrecio: " . ($tp_id ?: 'NULL') . ")\n";

        if (!$is_dry_run) {
            $ins = $pdo->prepare("
                INSERT INTO wp_posts (post_author, post_date, post_date_gmt, post_content, post_title, post_excerpt, post_status, comment_status, ping_status, post_name, post_modified, post_modified_gmt, post_type, post_parent)
                VALUES (1, NOW(), NOW(), '', ?, '', 'publish', 'closed', 'closed', ?, NOW(), NOW(), 'cohorte', ?)
            ");
            $ins->execute([$canonical_title, "cohorte-" . strtolower($roman), $of_id]);
            $cohorte_id = (int)$pdo->lastInsertId();

            ensure_meta($pdo, $cohorte_id, 'oferta_academica_id', $of_id);
            ensure_meta($pdo, $cohorte_id, 'numero', $num);
            ensure_meta($pdo, $cohorte_id, 'fecha_inicio', $d['fecha_inicio'] ?? '2027-01-01');
            ensure_meta($pdo, $cohorte_id, 'fecha_fin', $d['fecha_fin'] ?? '');
            ensure_meta($pdo, $cohorte_id, 'precision_fecha_inicio', $d['precision_fecha_inicio'] ?? 'anio');
            ensure_meta($pdo, $cohorte_id, 'estado', $d['estado'] ?? 'planificada');
            ensure_meta($pdo, $cohorte_id, 'calendario_academico', $d['calendario_academico'] ?? '');
            ensure_meta($pdo, $cohorte_id, 'tabla_precio_id', $tp_id ?: '');
            ensure_meta($pdo, $cohorte_id, 'link_preinscripcion', $link_pre);
            ensure_meta($pdo, $cohorte_id, 'preinscripcion_desde', $d['preinscripcion_desde'] ?? '');
            ensure_meta($pdo, $cohorte_id, 'preinscripcion_hasta', $d['preinscripcion_hasta'] ?? '');
            
            $cohorte_map[$source_key] = $cohorte_id;
        }
    }

    // =========================================================================
    // FASE 6: CREACIÓN DE EDICIONES DE SEMINARIO (edicion-seminario)
    // =========================================================================
    echo "\n▶ FASE 6: Creando 54 Ediciones de Seminario...\n";
    $ediciones_json = load_json($data_dir . '/staging_local/ediciones_seminario.json');
    $edicion_map = [];

    // Limpiar ediciones existentes si hubiera alguna
    if (!$is_dry_run) {
        $old_eds = $pdo->query("SELECT ID FROM wp_posts WHERE post_type = 'edicion-seminario'")->fetchAll(PDO::FETCH_COLUMN);
        if (!empty($old_eds)) {
            $in_e = implode(',', $old_eds);
            $pdo->exec("DELETE FROM wp_postmeta WHERE post_id IN ($in_e)");
            $pdo->exec("DELETE FROM wp_posts WHERE ID IN ($in_e)");
        }
    }

    foreach ($ediciones_json as $ed) {
        $source_key = $ed['source_key'];
        $sem_source = $ed['seminario_source_key'];
        $sem_id = (int)str_replace('seminario:', '', $sem_source);
        $d = $ed['data'];

        $title = $d['nombre'];
        echo "  - Creando Edición '{$title}' para Seminario #$sem_id ({$d['anio']}, estado: {$d['estado']})\n";

        if (!$is_dry_run) {
            $slug_ed = sanitize_title_with_dashes($title) . '-' . $d['anio'];
            $ins = $pdo->prepare("
                INSERT INTO wp_posts (post_author, post_date, post_date_gmt, post_content, post_title, post_excerpt, post_status, comment_status, ping_status, post_name, post_modified, post_modified_gmt, post_type, post_parent)
                VALUES (1, NOW(), NOW(), '', ?, '', 'publish', 'closed', 'closed', ?, NOW(), NOW(), 'edicion-seminario', ?)
            ");
            $ins->execute([$title, $slug_ed, $sem_id]);
            $ed_id = (int)$pdo->lastInsertId();

            ensure_meta($pdo, $ed_id, 'seminario_id', $sem_id);
            ensure_meta($pdo, $ed_id, 'anio', (int)($d['anio'] ?? 2026));
            ensure_meta($pdo, $ed_id, 'fecha_inicio', $d['fecha_inicio'] ?? '');
            ensure_meta($pdo, $ed_id, 'fecha_fin', $d['fecha_fin'] ?? '');
            ensure_meta($pdo, $ed_id, 'estado', $d['estado'] ?? 'finalizada');
            ensure_meta($pdo, $ed_id, 'modalidad', $d['modalidad'] ?? '');
            ensure_meta($pdo, $ed_id, 'encuentros_sincronicos', $d['encuentros_sincronicos'] ?? []);
            ensure_meta($pdo, $ed_id, 'docentes', $d['docentes'] ?? []);
            ensure_meta($pdo, $ed_id, 'tabla_precio_id', $d['tabla_precio_id'] ?? '');
            ensure_meta($pdo, $ed_id, 'preinscripcion_desde', $d['preinscripcion_desde'] ?? '');
            ensure_meta($pdo, $ed_id, 'preinscripcion_hasta', $d['preinscripcion_hasta'] ?? '');
            ensure_meta($pdo, $ed_id, 'link_preinscripcion', $d['link_preinscripcion'] ?? '');
            ensure_meta($pdo, $ed_id, 'mensaje_preinscripcion_abierta', $d['mensaje_preinscripcion_abierta'] ?? '');
            ensure_meta($pdo, $ed_id, 'mensaje_preinscripcion_cerrada', $d['mensaje_preinscripcion_cerrada'] ?? '');
            ensure_meta($pdo, $ed_id, 'mostrar_en_formulario', (bool)($d['mostrar_en_formulario'] ?? false));
            ensure_meta($pdo, $ed_id, 'ediciones_componentes', $d['ediciones_componentes'] ?? []);

            $edicion_map[$source_key] = $ed_id;
        }
    }

    // =========================================================================
    // FASE 7: RELACIONES CRUZADAS (Oferta -> Seminarios, Seminario -> Componentes)
    // =========================================================================
    echo "\n▶ FASE 7: Vinculando Relaciones Cruzadas...\n";

    // 1. Relaciones Oferta -> Seminarios
    $rel_oferta_sem = load_json($data_dir . '/staging_local/relaciones_oferta_seminario.json');
    $ofertas_seminarios_grouped = [];

    $sem_canonical_alias = [
        27242 => 24299,
        27254 => 23902,
        27256 => 25623,
        27245 => 24432,
        27258 => 23904,
    ];

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
        echo "  - Oferta #$of_id: guardando " . count($sems_arr) . " relaciones con seminarios.\n";
        if (!$is_dry_run) {
            ensure_meta($pdo, $of_id, 'seminarios', $sems_arr);
        }
    }

    // 2. Componentes de seminarios
    $comp_json = load_json($data_dir . '/staging_local/componentes_seminario.json');
    $comp_grouped = [];
    foreach ($comp_json as $c) {
        $padre_id = (int)str_replace('seminario:', '', $c['seminario_padre_source_key']);
        $hijo_id = (int)str_replace('seminario:', '', $c['seminario_hijo_source_key']);
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
        echo "  - Seminario compuesto #$padre_id: guardando " . count($comps) . " componentes.\n";
        if (!$is_dry_run) {
            ensure_meta($pdo, $padre_id, 'componentes', $comps);
        }
    }

    // =========================================================================
    // FASE 8: LIMPIEZA FINAL Y RECONTEO DE TAXONOMÍAS
    // =========================================================================
    echo "\n▶ FASE 8: Limpieza final de taxonomías obsoletas...\n";
    if (!$is_dry_run) {
        $pdo->exec("
            DELETE tr FROM wp_term_relationships tr
            JOIN wp_term_taxonomy tt ON tr.term_taxonomy_id = tt.term_taxonomy_id
            WHERE tt.taxonomy IN ('area_tematica', 'seminario_posgrado', 'programa_seminario')
        ");
        
        $pdo->exec("
            UPDATE wp_term_taxonomy
            SET count = 0
            WHERE taxonomy IN ('area_tematica', 'seminario_posgrado', 'programa_seminario')
        ");

        $pdo->exec("
            UPDATE wp_term_taxonomy tt
            SET count = (
                SELECT COUNT(*) FROM wp_term_relationships tr
                JOIN wp_posts p ON tr.object_id = p.ID
                WHERE tr.term_taxonomy_id = tt.term_taxonomy_id AND p.post_status = 'publish'
            )
            WHERE tt.taxonomy = 'tipo-oferta-academica'
        ");
    }

    if (!$is_dry_run) {
        $pdo->commit();
        echo "\n✅ MIGRACIÓN COMPLETADA Y APLICADA EXITOSAMENTE (COMMIT).\n";
    } else {
        echo "\nℹ️ SIMULACIÓN COMPLETADA SIN ERRORES (--dry-run). No se modificó la base de datos.\n";
    }

} catch (Throwable $e) {
    if (!$is_dry_run && $pdo->inTransaction()) {
        $pdo->rollBack();
        echo "\n❌ ERROR: Transacción revertida (ROLLBACK).\n";
    }
    echo "Error: " . $e->getMessage() . "\n";
    echo $e->getTraceAsString() . "\n";
    exit(1);
}
