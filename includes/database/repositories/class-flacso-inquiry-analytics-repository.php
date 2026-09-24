<?php
/**
 * Repositorio Analítico y de Consultas para la Consola de Correos y Plataforma de Consultas.
 *
 * Lee directamente de PostgreSQL (offer_inquiries, seminar_inquiries, general_inquiries)
 * mediante FLACSO_DB::connection() y provee:
 * - Métricas de entrega Mailjet (24h, 7d, totales)
 * - Resumen analítico por oferta, país, serie temporal (promedio móvil 7d), campañas y comparación entre períodos
 * - Listado paginado (modo agrupado por oferta+correo y modo individual)
 * - Detalle de consulta y exportación CSV deduplicada con BOM UTF-8
 *
 * @package FLACSO_Uruguay
 */

if (!defined('ABSPATH')) {
    exit;
}

class FLACSO_Inquiry_Analytics_Repository {

    public const ALLOWED_TABLES = [
        'offer_inquiries'   => [
            'label'       => 'Ofertas Académicas',
            'item_col'    => 'offerName',
            'wp_id_col'   => 'offerWpId',
            'type_col'    => 'offerType',
            'status_col'  => 'offerStatus',
            'has_email'   => true,
        ],
        'seminar_inquiries' => [
            'label'       => 'Seminarios',
            'item_col'    => 'seminarName',
            'wp_id_col'   => 'seminarWpId',
            'type_col'    => 'seminarType',
            'status_col'  => 'offerStatus',
            'has_email'   => true,
        ],
        'general_inquiries' => [
            'label'       => 'Consultas Generales',
            'item_col'    => 'unit',
            'wp_id_col'   => null,
            'type_col'    => 'source',
            'status_col'  => null,
            'has_email'   => false,
        ],
    ];

    /**
     * Obtiene la conexión PDO activa o null si no está configurada.
     */
    protected static function get_pdo(): ?PDO {
        if (!class_exists('FLACSO_DB') || !FLACSO_DB::is_configured()) {
            return null;
        }
        try {
            return FLACSO_DB::connection();
        } catch (Throwable $e) {
            error_log('[FLACSO Analytics] Error conectando a PostgreSQL: ' . $e->getMessage());
            return null;
        }
    }

    /**
     * Comprueba si una tabla existe en la base de datos actual.
     */
    public static function table_exists(PDO $pdo, string $table): bool {
        if (!isset(self::ALLOWED_TABLES[$table])) {
            return false;
        }
        try {
            $driver = (string) $pdo->getAttribute(PDO::ATTR_DRIVER_NAME);
            if ($driver === 'sqlite') {
                $stmt = $pdo->prepare("SELECT name FROM sqlite_master WHERE type='table' AND name = :t");
                $stmt->execute([':t' => $table]);
                return (bool) $stmt->fetchColumn();
            }
            $stmt = $pdo->prepare("SELECT to_regclass(:t)");
            $stmt->execute([':t' => 'public.' . $table]);
            return $stmt->fetchColumn() !== null;
        } catch (Throwable $e) {
            return false;
        }
    }

    /**
     * Métricas rápidas de entrega de correos y volumen para la Consola de Correos y cabecera.
     */
    public static function get_email_delivery_metrics(): array {
        $default = [
            'db_connected' => false,
            'last_24h'     => ['total' => 0, 'sent' => 0, 'failed' => 0, 'skipped' => 0, 'rate' => 0.0],
            'last_7d'      => ['total' => 0, 'sent' => 0, 'failed' => 0, 'skipped' => 0, 'rate' => 0.0],
            'totals'       => ['offer_inquiries' => 0, 'seminar_inquiries' => 0, 'general_inquiries' => 0],
            'recent_failures' => [],
        ];

        $pdo = self::get_pdo();
        if (!$pdo) {
            return $default;
        }

        $default['db_connected'] = true;
        $cut_24h = gmdate('Y-m-d H:i:s', time() - 86400);
        $cut_7d  = gmdate('Y-m-d H:i:s', time() - 7 * 86400);

        foreach (['offer_inquiries', 'seminar_inquiries', 'general_inquiries'] as $table) {
            if (!self::table_exists($pdo, $table)) {
                continue;
            }
            try {
                $stmt = $pdo->query("SELECT COUNT(*) FROM \"{$table}\"");
                $default['totals'][$table] = (int) $stmt->fetchColumn();
            } catch (Throwable $e) {
                // ignore
            }
        }

        foreach (['offer_inquiries', 'seminar_inquiries'] as $table) {
            if (!self::table_exists($pdo, $table)) {
                continue;
            }
            $item_col = self::ALLOWED_TABLES[$table]['item_col'];

            // 24h
            try {
                $stmt = $pdo->prepare("SELECT \"emailStatus\", COUNT(*) AS cnt FROM \"{$table}\" WHERE \"inquiryAt\" >= :cut GROUP BY \"emailStatus\"");
                $stmt->execute([':cut' => $cut_24h]);
                while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                    $st = strtolower((string) ($row['emailStatus'] ?? 'skipped'));
                    $cnt = (int) ($row['cnt'] ?? 0);
                    $default['last_24h']['total'] += $cnt;
                    if (isset($default['last_24h'][$st])) {
                        $default['last_24h'][$st] += $cnt;
                    } else {
                        $default['last_24h']['skipped'] += $cnt;
                    }
                }
            } catch (Throwable $e) {}

            // 7d
            try {
                $stmt = $pdo->prepare("SELECT \"emailStatus\", COUNT(*) AS cnt FROM \"{$table}\" WHERE \"inquiryAt\" >= :cut GROUP BY \"emailStatus\"");
                $stmt->execute([':cut' => $cut_7d]);
                while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                    $st = strtolower((string) ($row['emailStatus'] ?? 'skipped'));
                    $cnt = (int) ($row['cnt'] ?? 0);
                    $default['last_7d']['total'] += $cnt;
                    if (isset($default['last_7d'][$st])) {
                        $default['last_7d'][$st] += $cnt;
                    } else {
                        $default['last_7d']['skipped'] += $cnt;
                    }
                }
            } catch (Throwable $e) {}

            // Recent failures
            try {
                $stmt = $pdo->prepare(
                    "SELECT \"id\", \"consultaId\", \"{$item_col}\" AS item_name, \"fullName\", \"email\", \"inquiryAt\", \"emailStatus\"
                     FROM \"{$table}\"
                     WHERE \"emailStatus\" = 'failed'
                     ORDER BY \"inquiryAt\" DESC
                     LIMIT 5"
                );
                $stmt->execute();
                while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                    $row['table'] = $table;
                    $default['recent_failures'][] = $row;
                }
            } catch (Throwable $e) {}
        }

        $attempted_24h = $default['last_24h']['sent'] + $default['last_24h']['failed'];
        $default['last_24h']['rate'] = $attempted_24h > 0
            ? round(($default['last_24h']['sent'] / $attempted_24h) * 100, 1)
            : 100.0;

        $attempted_7d = $default['last_7d']['sent'] + $default['last_7d']['failed'];
        $default['last_7d']['rate'] = $attempted_7d > 0
            ? round(($default['last_7d']['sent'] / $attempted_7d) * 100, 1)
            : 100.0;

        return $default;
    }

    /**
     * Obtiene la lista de ofertas o seminarios distintos presentes en la tabla.
     */
    public static function get_distinct_items(string $table = 'offer_inquiries'): array {
        $pdo = self::get_pdo();
        if (!$pdo || !self::table_exists($pdo, $table)) {
            return [];
        }

        $item_col = self::ALLOWED_TABLES[$table]['item_col'];
        $wp_id_col = self::ALLOWED_TABLES[$table]['wp_id_col'];

        try {
            $select_wp = $wp_id_col ? "MAX(\"{$wp_id_col}\") AS wp_id," : "NULL AS wp_id,";
            $sql = "SELECT {$select_wp} \"{$item_col}\" AS nombre, COUNT(*) AS total
                    FROM \"{$table}\"
                    WHERE \"{$item_col}\" IS NOT NULL AND \"{$item_col}\" <> ''
                    GROUP BY \"{$item_col}\"
                    ORDER BY \"{$item_col}\" ASC";
            $stmt = $pdo->query($sql);
            $items = [];
            while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                $items[] = [
                    'id'     => $row['wp_id'] ? (int) $row['wp_id'] : 0,
                    'nombre' => (string) $row['nombre'],
                    'total'  => (int) $row['total'],
                ];
            }
            return $items;
        } catch (Throwable $e) {
            return [];
        }
    }

    /**
     * Obtiene los países distintos registrados en la tabla.
     */
    public static function get_distinct_countries(string $table = 'offer_inquiries'): array {
        $pdo = self::get_pdo();
        if (!$pdo || !self::table_exists($pdo, $table)) {
            return [];
        }

        try {
            $sql = "SELECT \"country\" AS pais, COUNT(*) AS total
                    FROM \"{$table}\"
                    WHERE \"country\" IS NOT NULL AND \"country\" <> ''
                    GROUP BY \"country\"
                    ORDER BY total DESC, \"country\" ASC";
            $stmt = $pdo->query($sql);
            return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
        } catch (Throwable $e) {
            return [];
        }
    }

    /**
     * Determina si un país corresponde a Uruguay.
     */
    public static function is_uruguay(?string $country): bool {
        $norm = strtolower(trim((string) $country));
        return $norm === 'uruguay' || $norm === 'uy' || $norm === 'república oriental del uruguay' || $norm === 'republica oriental del uruguay';
    }

    /**
     * Construye el resumen analítico completo (igual a /api/consultas/resumen del Editor):
     * - Serie temporal diaria + promedio móvil 7d + serie por país
     * - Segmentos: Total, Uruguay, Exterior, Intersección UY/EXT
     * - Consolidado deduplicado por [Oferta + Correo]
     * - Desglose Oferta x País
     * - Atribución y ranking de Campañas (con soporte de exclusiones)
     */
    public static function get_analytics_summary(string $desde, string $hasta, string $offer_filter = '', string $table = 'offer_inquiries'): array {
        $desde_clean = preg_match('/^\d{4}-\d{2}-\d{2}$/', $desde) ? $desde : gmdate('Y-m-d', time() - 29 * 86400);
        $hasta_clean = preg_match('/^\d{4}-\d{2}-\d{2}$/', $hasta) ? $hasta : gmdate('Y-m-d');
        if ($hasta_clean < $desde_clean) {
            [$desde_clean, $hasta_clean] = [$hasta_clean, $desde_clean];
        }

        $start_ts = $desde_clean . ' 00:00:00';
        $end_ts   = $hasta_clean . ' 23:59:59';

        $empty = [
            'rango'   => ['desde' => $desde_clean, 'hasta' => $hasta_clean],
            'filtro'  => ['oferta' => $offer_filter, 'table' => $table],
            'resumen' => [
                'total'    => ['totalConsultas' => 0, 'totalCorreosUnicos' => 0],
                'uruguay'  => ['totalConsultas' => 0, 'totalCorreosUnicos' => 0],
                'exterior' => ['totalConsultas' => 0, 'totalCorreosUnicos' => 0],
                'correosInterseccionUyExt' => 0,
            ],
            'consolidado'          => [],
            'ofertaPais'           => ['filas' => []],
            'serieTemporal'        => [],
            'serieTemporalPorPais' => [],
            'campanas'             => [
                'totalCampanas'   => 0,
                'totalConCampana' => 0,
                'totalSinCampana' => 0,
                'totalConsultas'  => 0,
                'filas'           => [],
                'ocultas'         => [],
            ],
        ];

        $pdo = self::get_pdo();
        if (!$pdo || !self::table_exists($pdo, $table)) {
            return $empty;
        }

        $item_col = self::ALLOWED_TABLES[$table]['item_col'];

        $where = ["\"inquiryAt\" >= :start_ts", "\"inquiryAt\" <= :end_ts"];
        $params = [':start_ts' => $start_ts, ':end_ts' => $end_ts];

        if ($offer_filter !== '') {
            $where[] = "\"{$item_col}\" = :offer_filter";
            $params[':offer_filter'] = $offer_filter;
        }

        $sql = "SELECT
                    \"id\",
                    \"consultaId\",
                    \"{$item_col}\" AS item_name,
                    \"email\",
                    \"emailNormalized\",
                    \"country\",
                    \"source\",
                    \"campaignProvider\",
                    \"campaignSource\",
                    \"campaignMedium\",
                    \"campaignName\",
                    \"campaignExternalId\",
                    \"campaignContent\",
                    \"campaignTerm\",
                    \"inquiryAt\"
                FROM \"{$table}\"
                WHERE " . implode(' AND ', $where) . "
                ORDER BY \"inquiryAt\" ASC";

        try {
            $stmt = $pdo->prepare($sql);
            $stmt->execute($params);
            $rows = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
        } catch (Throwable $e) {
            error_log('[FLACSO Analytics] Error en get_analytics_summary: ' . $e->getMessage());
            return $empty;
        }

        // Inicializar mapa de fechas para serie temporal
        $daily_counts = [];
        $country_daily = [];
        $country_totals = [];

        $cur_ts = strtotime($desde_clean . ' 00:00:00 UTC');
        $end_day_ts = strtotime($hasta_clean . ' 00:00:00 UTC');
        while ($cur_ts <= $end_day_ts) {
            $d_str = gmdate('Y-m-d', $cur_ts);
            $daily_counts[$d_str] = 0;
            $cur_ts += 86400;
        }

        // Deduplicación por [emailNormalized + item_name] (Regla de Negocio FLACSO)
        $seen_offer_email = [];
        $emails_total = [];
        $emails_uy = [];
        $emails_ext = [];
        $offer_stats = [];
        $offer_country_stats = [];

        // Campañas
        $excluded_campaigns = self::get_excluded_campaigns();
        $campaigns_map = [];
        $hidden_campaigns_map = [];
        $total_con_campana = 0;
        $total_sin_campana = 0;

        foreach ($rows as $row) {
            $email_norm = strtolower(trim((string) ($row['emailNormalized'] ?: $row['email'])));
            $offer_name = trim((string) ($row['item_name'] ?: 'Sin especificar'));
            $country    = trim((string) ($row['country'] ?: 'No especificado'));
            $day_str    = substr((string) ($row['inquiryAt'] ?? ''), 0, 10);

            if ($email_norm === '') {
                continue;
            }

            // Campañas (se cuentan sobre registros válidos igual que en el Editor)
            $c_provider = trim((string) ($row['campaignProvider'] ?? ''));
            $c_source   = trim((string) ($row['campaignSource'] ?? ''));
            $c_medium   = trim((string) ($row['campaignMedium'] ?? ''));
            $c_name     = trim((string) ($row['campaignName'] ?? ''));
            $c_ext_id   = trim((string) ($row['campaignExternalId'] ?? ''));
            $c_content  = trim((string) ($row['campaignContent'] ?? ''));
            $c_term     = trim((string) ($row['campaignTerm'] ?? ''));

            $has_campaign = ($c_name !== '' || $c_ext_id !== '' || $c_source !== '' || $c_provider !== '');
            if ($has_campaign) {
                $c_key = $c_ext_id !== ''
                    ? 'id:' . strtolower($c_ext_id)
                    : strtolower($c_provider . '|' . $c_source . '|' . $c_medium . '|' . ($c_name ?: 'sin-nombre'));
                $display_campaign = $c_name !== '' ? $c_name : ($c_source !== '' ? $c_source : ($c_provider ?: 'Campaña atribuida'));

                if (isset($excluded_campaigns[$c_key])) {
                    $hidden_campaigns_map[$c_key] = [
                        'clave'   => $c_key,
                        'campana' => $display_campaign,
                    ];
                } else {
                    $total_con_campana++;
                    if (!isset($campaigns_map[$c_key])) {
                        $campaigns_map[$c_key] = [
                            'clave'          => $c_key,
                            'campana'        => $display_campaign,
                            'proveedor'      => $c_provider,
                            'fuente'         => $c_source,
                            'medio'          => $c_medium,
                            'idExterno'      => $c_ext_id,
                            'consultas'      => 0,
                            'emailsSet'      => [],
                            'ofertasSet'     => [],
                            'contenidosSet'  => [],
                            'terminosSet'    => [],
                        ];
                    }
                    $campaigns_map[$c_key]['consultas']++;
                    $campaigns_map[$c_key]['emailsSet'][$email_norm] = true;
                    $campaigns_map[$c_key]['ofertasSet'][$offer_name] = ($campaigns_map[$c_key]['ofertasSet'][$offer_name] ?? 0) + 1;
                    if ($c_content !== '') {
                        $campaigns_map[$c_key]['contenidosSet'][$c_content] = true;
                    }
                    if ($c_term !== '') {
                        $campaigns_map[$c_key]['terminosSet'][$c_term] = true;
                    }
                }
            } else {
                $total_sin_campana++;
            }

            // Deduplicación por [email_norm + offer_name]
            $dedup_key = $email_norm . '||' . strtolower($offer_name);
            if (isset($seen_offer_email[$dedup_key])) {
                continue;
            }
            $seen_offer_email[$dedup_key] = true;

            if (isset($daily_counts[$day_str])) {
                $daily_counts[$day_str]++;
            }

            if (!isset($country_daily[$country])) {
                $country_daily[$country] = array_fill_keys(array_keys($daily_counts), 0);
                $country_totals[$country] = 0;
            }
            if (isset($country_daily[$country][$day_str])) {
                $country_daily[$country][$day_str]++;
            }
            $country_totals[$country]++;

            $is_uy = self::is_uruguay($country);
            $emails_total[$email_norm] = true;
            if ($is_uy) {
                $emails_uy[$email_norm] = true;
            } else {
                $emails_ext[$email_norm] = true;
            }

            if (!isset($offer_stats[$offer_name])) {
                $offer_stats[$offer_name] = ['oferta' => $offer_name, 'total' => 0, 'uy' => 0, 'ext' => 0];
            }
            $offer_stats[$offer_name]['total']++;
            if ($is_uy) {
                $offer_stats[$offer_name]['uy']++;
            } else {
                $offer_stats[$offer_name]['ext']++;
            }

            $oc_key = $offer_name . '||' . $country;
            if (!isset($offer_country_stats[$oc_key])) {
                $offer_country_stats[$oc_key] = [
                    'oferta' => $offer_name,
                    'pais'   => $country,
                    'total'  => 0,
                    'emails' => [],
                ];
            }
            $offer_country_stats[$oc_key]['total']++;
            $offer_country_stats[$oc_key]['emails'][$email_norm] = true;
        }

        // Calcular intersección UY / EXT
        $intersection_count = 0;
        foreach ($emails_uy as $em => $_) {
            if (isset($emails_ext[$em])) {
                $intersection_count++;
            }
        }

        $total_dedup_uy = 0;
        $total_dedup_ext = 0;
        foreach ($offer_stats as $st) {
            $total_dedup_uy += $st['uy'];
            $total_dedup_ext += $st['ext'];
        }
        $total_dedup = $total_dedup_uy + $total_dedup_ext;

        // Ordenar consolidado por total DESC
        $consolidado = array_values($offer_stats);
        usort($consolidado, static function (array $a, array $b): int {
            return ($b['total'] <=> $a['total']) ?: strcasecmp($a['oferta'], $b['oferta']);
        });

        // Oferta x País
        $oferta_pais_filas = [];
        foreach ($offer_country_stats as $oc) {
            $oferta_pais_filas[] = [
                'oferta'        => $oc['oferta'],
                'pais'          => $oc['pais'],
                'total'         => $oc['total'],
                'correosUnicos' => count($oc['emails']),
            ];
        }
        usort($oferta_pais_filas, static function (array $a, array $b): int {
            return ($b['total'] <=> $a['total']) ?: strcasecmp($a['pais'], $b['pais']);
        });

        // Serie temporal con promedio móvil 7d
        $serie_temporal = self::build_moving_average_series($daily_counts);

        // Serie temporal por país
        arsort($country_totals);
        $serie_por_pais = [];
        foreach ($country_totals as $c_name => $c_tot) {
            $serie_por_pais[] = [
                'pais'          => $c_name,
                'total'         => $c_tot,
                'serieTemporal' => self::build_moving_average_series($country_daily[$c_name]),
            ];
        }

        // Campañas ordenadas
        $campanas_filas = [];
        foreach ($campaigns_map as $c) {
            $ofertas_detalle = [];
            arsort($c['ofertasSet']);
            foreach ($c['ofertasSet'] as $o_name => $o_cnt) {
                $ofertas_detalle[] = ['nombre' => $o_name, 'consultas' => $o_cnt];
            }
            $campanas_filas[] = [
                'clave'          => $c['clave'],
                'campana'        => $c['campana'],
                'proveedor'      => $c['proveedor'],
                'fuente'         => $c['fuente'],
                'medio'          => $c['medio'],
                'idExterno'      => $c['idExterno'],
                'consultas'      => $c['consultas'],
                'correosUnicos'  => count($c['emailsSet']),
                'ofertas'        => count($c['ofertasSet']),
                'ofertasDetalle' => $ofertas_detalle,
                'contenidos'     => array_keys($c['contenidosSet']),
                'terminos'       => array_keys($c['terminosSet']),
            ];
        }
        usort($campanas_filas, static function (array $a, array $b): int {
            return ($b['consultas'] <=> $a['consultas']) ?: strcasecmp($a['campana'], $b['campana']);
        });

        return [
            'rango'   => ['desde' => $desde_clean, 'hasta' => $hasta_clean],
            'filtro'  => ['oferta' => $offer_filter, 'table' => $table],
            'resumen' => [
                'total'    => ['totalConsultas' => $total_dedup, 'totalCorreosUnicos' => count($emails_total)],
                'uruguay'  => ['totalConsultas' => $total_dedup_uy, 'totalCorreosUnicos' => count($emails_uy)],
                'exterior' => ['totalConsultas' => $total_dedup_ext, 'totalCorreosUnicos' => count($emails_ext)],
                'correosInterseccionUyExt' => $intersection_count,
            ],
            'consolidado'          => $consolidado,
            'ofertaPais'           => ['filas' => $oferta_pais_filas],
            'serieTemporal'        => $serie_temporal,
            'serieTemporalPorPais' => $serie_por_pais,
            'campanas'             => [
                'totalCampanas'   => count($campanas_filas),
                'totalConCampana' => $total_con_campana,
                'totalSinCampana' => $total_sin_campana,
                'totalConsultas'  => $total_con_campana + $total_sin_campana,
                'filas'           => $campanas_filas,
                'ocultas'         => array_values($hidden_campaigns_map),
            ],
        ];
    }

    /**
     * Calcula la serie diaria con promedio móvil de 7 días.
     */
    private static function build_moving_average_series(array $daily_counts): array {
        $series = [];
        $values = [];
        foreach ($daily_counts as $fecha => $count) {
            $values[] = (int) $count;
            $len = count($values);
            $window = array_slice($values, max(0, $len - 7), 7);
            $avg = count($window) > 0 ? round(array_sum($window) / count($window), 2) : 0.0;
            $series[] = [
                'fecha'           => $fecha,
                'consultas'       => (int) $count,
                'promedioMovil7'  => $avg,
            ];
        }
        return $series;
    }

    /**
     * Obtiene las campañas excluidas del resumen.
     */
    public static function get_excluded_campaigns(): array {
        if (!function_exists('get_option')) {
            return [];
        }
        $raw = get_option('flacso_consultas_excluded_campaigns', []);
        return is_array($raw) ? $raw : [];
    }

    /**
     * Actualiza la exclusión de una campaña.
     */
    public static function set_campaign_exclusion(string $key, string $name, bool $exclude): void {
        if (!function_exists('update_option')) {
            return;
        }
        $current = self::get_excluded_campaigns();
        if ($exclude) {
            $current[$key] = $name;
        } else {
            unset($current[$key]);
        }
        update_option('flacso_consultas_excluded_campaigns', $current, false);
    }

    /**
     * Listado paginado para la Bandeja e Histórico Unificado (soporta modo agrupado por [oferta+correo] e individual).
     */
    public static function get_paginated_inquiries(array $filters = []): array {
        $table        = isset($filters['table']) && isset(self::ALLOWED_TABLES[$filters['table']]) ? $filters['table'] : 'offer_inquiries';
        $mode         = ($filters['mode'] ?? 'grouped') === 'raw' ? 'raw' : 'grouped';
        $search       = trim((string) ($filters['search'] ?? ''));
        $item_name    = trim((string) ($filters['item_name'] ?? ''));
        $country      = trim((string) ($filters['country'] ?? ''));
        $email_status = trim((string) ($filters['email_status'] ?? ''));
        $desde        = trim((string) ($filters['desde'] ?? ''));
        $hasta        = trim((string) ($filters['hasta'] ?? ''));
        $page         = max(1, (int) ($filters['page'] ?? 1));
        $page_size    = min(100, max(10, (int) ($filters['page_size'] ?? 20)));

        $result = [
            'items'   => [],
            'metrics' => [
                'totalRecords' => 0,
                'totalGroups'  => 0,
                'totalOffers'  => 0,
                'totalEmails'  => 0,
                'oldestAt'     => null,
                'latestAt'     => null,
            ],
            'pageInfo' => [
                'page'            => $page,
                'pageSize'        => $page_size,
                'totalPages'      => 1,
                'totalItems'      => 0,
                'hasPreviousPage' => false,
                'hasNextPage'     => false,
            ],
        ];

        $pdo = self::get_pdo();
        if (!$pdo || !self::table_exists($pdo, $table)) {
            return $result;
        }

        $meta = self::ALLOWED_TABLES[$table];
        $item_col = $meta['item_col'];
        $wp_id_col = $meta['wp_id_col'];
        $has_email = $meta['has_email'];

        // Global metrics for this table
        try {
            $sql_metrics = "SELECT
                                COUNT(*) AS total_records,
                                COUNT(DISTINCT \"{$item_col}\") AS total_offers,
                                COUNT(DISTINCT \"emailNormalized\") AS total_emails,
                                MIN(\"inquiryAt\") AS oldest_at,
                                MAX(\"inquiryAt\") AS latest_at
                            FROM \"{$table}\"";
            $m_row = $pdo->query($sql_metrics)->fetch(PDO::FETCH_ASSOC);
            if ($m_row) {
                $result['metrics']['totalRecords'] = (int) ($m_row['total_records'] ?? 0);
                $result['metrics']['totalOffers']  = (int) ($m_row['total_offers'] ?? 0);
                $result['metrics']['totalEmails']  = (int) ($m_row['total_emails'] ?? 0);
                $result['metrics']['oldestAt']     = $m_row['oldest_at'] ?? null;
                $result['metrics']['latestAt']     = $m_row['latest_at'] ?? null;
            }
        } catch (Throwable $e) {}

        // Build WHERE conditions
        $where = [];
        $params = [];

        if ($search !== '') {
            $driver = (string) $pdo->getAttribute(PDO::ATTR_DRIVER_NAME);
            $like_op = $driver === 'pgsql' ? 'ILIKE' : 'LIKE';
            $where[] = "(\"fullName\" {$like_op} :q OR \"email\" {$like_op} :q OR \"consultaId\" {$like_op} :q OR \"{$item_col}\" {$like_op} :q)";
            $params[':q'] = '%' . $search . '%';
        }

        if ($item_name !== '') {
            $where[] = "\"{$item_col}\" = :item_name";
            $params[':item_name'] = $item_name;
        }

        if ($country !== '') {
            $where[] = "\"country\" = :country";
            $params[':country'] = $country;
        }

        if ($has_email && $email_status !== '') {
            $where[] = "\"emailStatus\" = :email_status";
            $params[':email_status'] = $email_status;
        }

        if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $desde)) {
            $where[] = "\"inquiryAt\" >= :desde";
            $params[':desde'] = $desde . ' 00:00:00';
        }

        if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $hasta)) {
            $where[] = "\"inquiryAt\" <= :hasta";
            $params[':hasta'] = $hasta . ' 23:59:59';
        }

        $where_sql = !empty($where) ? ('WHERE ' . implode(' AND ', $where)) : '';

        $offset = ($page - 1) * $page_size;
        $select_wp = $wp_id_col ? "\"{$wp_id_col}\" AS item_wp_id," : "NULL AS item_wp_id,";
        $select_email = $has_email
            ? "\"emailStatus\", \"emailSender\", \"mailjetMessageId\", \"mailjetMessageUuid\","
            : "'skipped' AS \"emailStatus\", '' AS \"emailSender\", '' AS \"mailjetMessageId\", '' AS \"mailjetMessageUuid\",";

        if ($mode === 'raw') {
            try {
                $stmt_cnt = $pdo->prepare("SELECT COUNT(*) FROM \"{$table}\" {$where_sql}");
                $stmt_cnt->execute($params);
                $total_items = (int) $stmt_cnt->fetchColumn();
                $result['metrics']['totalGroups'] = $total_items;

                $sql_rows = "SELECT
                                \"id\", \"consultaId\", {$select_wp} \"{$item_col}\" AS item_name,
                                \"firstName\", \"lastName\", \"fullName\", \"email\", \"emailNormalized\",
                                \"country\", \"source\", \"campaignName\", \"campaignSource\", \"campaignMedium\",
                                {$select_email}
                                \"inquiryAt\"
                             FROM \"{$table}\"
                             {$where_sql}
                             ORDER BY \"inquiryAt\" DESC
                             LIMIT {$page_size} OFFSET {$offset}";
                $stmt = $pdo->prepare($sql_rows);
                $stmt->execute($params);
                $rows = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
                foreach ($rows as &$r) {
                    $r['count'] = 1;
                    $r['children'] = [$r];
                }
                $result['items'] = $rows;
                $total_pages = max(1, (int) ceil($total_items / $page_size));
                $result['pageInfo'] = [
                    'page'            => $page,
                    'pageSize'        => $page_size,
                    'totalPages'      => $total_pages,
                    'totalItems'      => $total_items,
                    'hasPreviousPage' => $page > 1,
                    'hasNextPage'     => $page < $total_pages,
                ];
            } catch (Throwable $e) {
                error_log('[FLACSO Analytics] Error en get_paginated_inquiries (raw): ' . $e->getMessage());
            }
            return $result;
        }

        // Grouped mode by [item_col + emailNormalized]
        try {
            $sql_cnt = "SELECT COUNT(*) FROM (
                            SELECT 1 FROM \"{$table}\" {$where_sql}
                            GROUP BY \"{$item_col}\", \"emailNormalized\"
                        ) AS grp";
            $stmt_cnt = $pdo->prepare($sql_cnt);
            $stmt_cnt->execute($params);
            $total_groups = (int) $stmt_cnt->fetchColumn();
            $result['metrics']['totalGroups'] = $total_groups;

            $sql_groups = "SELECT
                                \"{$item_col}\" AS item_name,
                                \"emailNormalized\",
                                COUNT(*) AS group_count,
                                MAX(\"inquiryAt\") AS latest_at,
                                MIN(\"inquiryAt\") AS oldest_at
                           FROM \"{$table}\"
                           {$where_sql}
                           GROUP BY \"{$item_col}\", \"emailNormalized\"
                           ORDER BY latest_at DESC
                           LIMIT {$page_size} OFFSET {$offset}";
            $stmt_grp = $pdo->prepare($sql_groups);
            $stmt_grp->execute($params);
            $groups = $stmt_grp->fetchAll(PDO::FETCH_ASSOC) ?: [];

            $items = [];
            foreach ($groups as $grp) {
                $stmt_children = $pdo->prepare(
                    "SELECT
                        \"id\", \"consultaId\", {$select_wp} \"{$item_col}\" AS item_name,
                        \"firstName\", \"lastName\", \"fullName\", \"email\", \"emailNormalized\",
                        \"country\", \"source\", \"campaignName\", \"campaignSource\", \"campaignMedium\",
                        {$select_email}
                        \"inquiryAt\"
                     FROM \"{$table}\"
                     WHERE \"{$item_col}\" = :iname AND \"emailNormalized\" = :enorm
                     ORDER BY \"inquiryAt\" DESC
                     LIMIT 25"
                );
                $stmt_children->execute([
                    ':iname' => (string) $grp['item_name'],
                    ':enorm' => (string) $grp['emailNormalized'],
                ]);
                $children = $stmt_children->fetchAll(PDO::FETCH_ASSOC) ?: [];
                $head = $children[0] ?? [];
                $head['count']    = (int) $grp['group_count'];
                $head['latestAt'] = $grp['latest_at'];
                $head['oldestAt'] = $grp['oldest_at'];
                $head['children'] = $children;
                $items[] = $head;
            }

            $result['items'] = $items;
            $total_pages = max(1, (int) ceil($total_groups / $page_size));
            $result['pageInfo'] = [
                'page'            => $page,
                'pageSize'        => $page_size,
                'totalPages'      => $total_pages,
                'totalItems'      => $total_groups,
                'hasPreviousPage' => $page > 1,
                'hasNextPage'     => $page < $total_pages,
            ];
        } catch (Throwable $e) {
            error_log('[FLACSO Analytics] Error en get_paginated_inquiries (grouped): ' . $e->getMessage());
        }

        return $result;
    }

    /**
     * Obtiene un registro completo por id o consultaId.
     */
    public static function get_inquiry_detail(string $table, string $id): ?array {
        $pdo = self::get_pdo();
        if (!$pdo || !self::table_exists($pdo, $table)) {
            return null;
        }

        try {
            $stmt = $pdo->prepare("SELECT * FROM \"{$table}\" WHERE \"id\" = :id OR \"consultaId\" = :id LIMIT 1");
            $stmt->execute([':id' => $id]);
            $row = $stmt->fetch(PDO::FETCH_ASSOC);
            if (!$row) {
                return null;
            }
            if (!empty($row['payload']) && is_string($row['payload'])) {
                $decoded = json_decode($row['payload'], true);
                if (is_array($decoded)) {
                    $row['payload_decoded'] = $decoded;
                }
            }
            $row['_table'] = $table;
            return $row;
        } catch (Throwable $e) {
            return null;
        }
    }

    /**
     * Obtiene las filas para exportación CSV (deduplicadas por [emailNormalized + itemName] o completas).
     */
    public static function get_export_rows(string $table, string $desde, string $hasta, array $selected_items = [], bool $deduplicate = true): array {
        $pdo = self::get_pdo();
        if (!$pdo || !self::table_exists($pdo, $table)) {
            return [];
        }

        $meta = self::ALLOWED_TABLES[$table];
        $item_col = $meta['item_col'];

        $where = [];
        $params = [];

        if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $desde)) {
            $where[] = "\"inquiryAt\" >= :desde";
            $params[':desde'] = $desde . ' 00:00:00';
        }
        if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $hasta)) {
            $where[] = "\"inquiryAt\" <= :hasta";
            $params[':hasta'] = $hasta . ' 23:59:59';
        }

        $selected_clean = array_values(array_filter(array_map('trim', $selected_items)));
        if (!empty($selected_clean)) {
            $placeholders = [];
            foreach ($selected_clean as $idx => $val) {
                $ph = ':item_' . $idx;
                $placeholders[] = $ph;
                $params[$ph] = $val;
            }
            $where[] = "\"{$item_col}\" IN (" . implode(', ', $placeholders) . ")";
        }

        $where_sql = !empty($where) ? ('WHERE ' . implode(' AND ', $where)) : '';

        try {
            $sql = "SELECT * FROM \"{$table}\" {$where_sql} ORDER BY \"inquiryAt\" DESC LIMIT 25000";
            $stmt = $pdo->prepare($sql);
            $stmt->execute($params);
            $rows = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

            if (!$deduplicate) {
                return $rows;
            }

            $deduped = [];
            $seen = [];
            foreach ($rows as $row) {
                $email_norm = strtolower(trim((string) ($row['emailNormalized'] ?? $row['email'] ?? '')));
                $iname = strtolower(trim((string) ($row[$item_col] ?? '')));
                $key = $email_norm . '||' . $iname;
                if ($email_norm !== '' && isset($seen[$key])) {
                    continue;
                }
                $seen[$key] = true;
                $deduped[] = $row;
            }
            return $deduped;
        } catch (Throwable $e) {
            error_log('[FLACSO Analytics] Error en get_export_rows: ' . $e->getMessage());
            return [];
        }
    }
}
