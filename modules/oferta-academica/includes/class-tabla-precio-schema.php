<?php

if (!defined('ABSPATH')) {
    exit;
}

/** Esquema compartido de precios para Cohorte y EdicionSeminario. */
final class Tabla_Precio_Schema {
    public const AMBITOS = ['oferta', 'seminario'];

    public static function init(): void {
        add_action('init', [self::class, 'register_meta'], 12);
        foreach (['added_post_meta', 'updated_post_meta', 'deleted_post_meta'] as $hook) {
            add_action($hook, [FLACSO_Price_Table_Repository::class, 'flush_usage_index'], 10, 3);
        }
    }

    public static function register_meta(): void {
        $definitions = [
            'tabla_precios_tipo' => ['type' => 'string', 'sanitize_callback' => 'sanitize_text_field'],
            'tabla_precios_ambito' => ['type' => 'string', 'sanitize_callback' => [self::class, 'sanitize_ambito']],
            'precios_filas' => ['type' => 'array', 'sanitize_callback' => [self::class, 'sanitize_rows']],
            'precios_columnas' => ['type' => 'array', 'sanitize_callback' => [self::class, 'sanitize_columns']],
            'precios_nota' => ['type' => 'string', 'sanitize_callback' => 'wp_kses_post'],
            'precios_nota_texto' => ['type' => 'string', 'sanitize_callback' => 'sanitize_textarea_field'],
            'precios_nota_letra_chica' => ['type' => 'string', 'sanitize_callback' => 'sanitize_textarea_field'],
            'mostrar_precios_dolares' => ['type' => 'boolean', 'sanitize_callback' => [self::class, 'sanitize_boolean']],
        ];
        foreach ($definitions as $key => $definition) {
            register_post_meta('tabla-precio', $key, array_merge([
                'single' => true,
                'show_in_rest' => false,
                'auth_callback' => static function (): bool { return current_user_can('edit_posts'); },
            ], $definition));
        }
    }

    public static function sanitize_boolean($value): bool {
        return filter_var($value, FILTER_VALIDATE_BOOLEAN);
    }

    public static function sanitize_ambito($value): string {
        $value = sanitize_key((string) $value);
        return in_array($value, self::AMBITOS, true) ? $value : '';
    }

    public static function sanitize_rows($value): array {
        if (is_string($value) && '' !== trim($value)) {
            $decoded = json_decode($value, true);
            $value = is_array($decoded) ? $decoded : $value;
        }
        if (!is_array($value)) {
            return [];
        }
        $rows = [];
        foreach ($value as $row) {
            if (!is_array($row)) {
                continue;
            }
            $normalized = self::normalize_row($row);
            if (self::row_has_content($normalized)) {
                $rows[] = $normalized;
            }
        }
        return $rows;
    }

    /**
     * Fila canónica: concepto (negrita) y detalle (letra chica) en texto plano.
     * Las filas antiguas guardaban HTML en el concepto; se separan acá.
     */
    public static function normalize_row(array $row): array {
        $concept = (string) ($row['concepto'] ?? $row['concept'] ?? '');
        $detail = (string) ($row['detalle'] ?? '');
        if (!array_key_exists('detalle', $row) && $concept !== wp_strip_all_tags($concept)) {
            [$concept, $detail] = self::split_legacy_concept($concept);
        }

        $extras = [];
        foreach ((is_array($row['extras'] ?? null) ? $row['extras'] : []) as $column_id => $extra) {
            $column_id = sanitize_key((string) $column_id);
            if ($column_id !== '') {
                $extras[$column_id] = sanitize_text_field(self::html_to_text((string) $extra));
            }
        }

        return [
            'concepto' => sanitize_text_field($concept),
            'detalle' => sanitize_text_field($detail),
            'uyu' => sanitize_text_field(self::html_to_text((string) ($row['uyu'] ?? $row['uy'] ?? ''))),
            'usd' => sanitize_text_field(self::html_to_text((string) ($row['usd'] ?? $row['us'] ?? ''))),
            'extras' => $extras,
            'destacada' => self::sanitize_boolean($row['destacada'] ?? $row['highlight'] ?? false),
        ];
    }

    public static function row_has_content(array $row): bool {
        foreach (['concepto', 'detalle', 'uyu', 'usd'] as $key) {
            if (trim((string) ($row[$key] ?? '')) !== '') {
                return true;
            }
        }
        return (bool) array_filter((array) ($row['extras'] ?? []), static fn($value): bool => trim((string) $value) !== '');
    }

    public static function sanitize_columns($value): array {
        if (!is_array($value)) {
            return [];
        }
        $columns = [];
        foreach ($value as $column) {
            if (!is_array($column)) {
                continue;
            }
            $id = sanitize_key((string) ($column['id'] ?? ''));
            $title = sanitize_text_field((string) ($column['titulo'] ?? ''));
            if ($id !== '' && $title !== '' && !isset($columns[$id])) {
                $columns[$id] = ['id' => $id, 'titulo' => $title];
            }
        }
        return array_values($columns);
    }

    /** Nota pública en HTML a partir de texto plano: enlaces automáticos y letra chica en <small>. */
    public static function note_html(string $text, string $fine_print): string {
        $format = static fn(string $value): string => nl2br(make_clickable(esc_html(trim($value))), false);
        $parts = [];
        if (trim($text) !== '') {
            $parts[] = $format($text);
        }
        if (trim($fine_print) !== '') {
            $parts[] = '<small>' . $format($fine_print) . '</small>';
        }
        return implode('<br>', $parts);
    }

    /** Lee la nota estructurada; si sólo existe la versión HTML antigua, la separa. */
    public static function note_parts(int $post_id): array {
        if (metadata_exists('post', $post_id, 'precios_nota_texto') || metadata_exists('post', $post_id, 'precios_nota_letra_chica')) {
            return [
                (string) get_post_meta($post_id, 'precios_nota_texto', true),
                (string) get_post_meta($post_id, 'precios_nota_letra_chica', true),
            ];
        }

        $fine_print = [];
        $html = preg_replace_callback('#<small[^>]*>(.*?)</small>#is', static function (array $match) use (&$fine_print): string {
            $fine_print[] = self::html_to_text($match[1]);
            return '';
        }, (string) get_post_meta($post_id, 'precios_nota', true));

        return [self::html_to_text((string) $html), implode("\n", array_filter($fine_print))];
    }

    private static function split_legacy_concept(string $html): array {
        $details = [];
        $html = (string) preg_replace_callback('#<small[^>]*>(.*?)</small>#is', static function (array $match) use (&$details): string {
            $details[] = self::html_to_text($match[1]);
            return '';
        }, $html);

        $lines = array_values(array_filter(array_map('trim', explode("\n", self::html_to_text($html)))));
        $concept = (string) array_shift($lines);
        $details = array_filter(array_merge($lines, $details));

        return [$concept, implode(' ', $details)];
    }

    /** HTML a texto: <br> y párrafos pasan a saltos de línea; los enlaces conservan su destino. */
    private static function html_to_text(string $html): string {
        if (strpos($html, '<') === false) {
            return trim(html_entity_decode($html, ENT_QUOTES, 'UTF-8'));
        }

        $html = (string) preg_replace_callback('#<a[^>]*href=["\']([^"\']+)["\'][^>]*>(.*?)</a>#is', static function (array $match): string {
            $target = preg_replace('#^mailto:#i', '', html_entity_decode($match[1], ENT_QUOTES, 'UTF-8'));
            $label = trim(wp_strip_all_tags($match[2]));
            return ($label === '' || $label === $target || strpos($target, $label) !== false) ? $target : "{$label} ({$target})";
        }, $html);
        $html = (string) preg_replace('#<br\s*/?>|</p>\s*<p[^>]*>#i', "\n", $html);
        $text = html_entity_decode(wp_strip_all_tags($html), ENT_QUOTES, 'UTF-8');
        $text = (string) preg_replace("/[ \t]+/", ' ', $text);

        return trim((string) preg_replace("/ *\n */", "\n", $text));
    }

    public static function get_table_data(int $post_id): array {
        return FLACSO_Academic_Repository::to_array('tablas-precio', $post_id);
    }
}

final class FLACSO_Price_Table_Repository {
    private static ?array $usage_index = null;

    public static function linked_uses(int $table_id): array {
        return self::usage_index()[$table_id] ?? [];
    }

    /**
     * Usos de todas las tablas en una sola consulta, agrupados por tabla.
     * Cada uso incluye su padre (Oferta para Cohortes, Seminario para Ediciones).
     */
    public static function usage_index(): array {
        if (null !== self::$usage_index) {
            return self::$usage_index;
        }

        global $wpdb;
        $cohort = FLACSO_Cohorte::POST_TYPE;
        $edition = FLACSO_Edicion::POST_TYPE;
        $results = $wpdb->get_results($wpdb->prepare(
            "SELECT p.ID, p.post_type, p.post_title, CAST(t.meta_value AS UNSIGNED) AS table_id, parent.meta_value AS parent_id
             FROM {$wpdb->postmeta} t
             INNER JOIN {$wpdb->posts} p ON p.ID = t.post_id
             LEFT JOIN {$wpdb->postmeta} parent ON parent.post_id = p.ID
                AND parent.meta_key = IF(p.post_type = %s, %s, %s)
             WHERE t.meta_key = 'tabla_precio_id'
                AND p.post_type IN (%s, %s)
                AND p.post_status NOT IN ('trash', 'auto-draft', 'inherit')
             ORDER BY p.post_date DESC",
            $cohort,
            FLACSO_Cohorte::META_PARENT_ID,
            FLACSO_Edicion::META_PARENT_ID,
            $cohort,
            $edition
        ));

        self::$usage_index = [];
        foreach ((array) $results as $result) {
            $table_id = (int) $result->table_id;
            if ($table_id < 1) {
                continue;
            }
            self::$usage_index[$table_id][] = [
                'id' => (int) $result->ID,
                'entidad' => (string) $result->post_type,
                'nombre' => (string) $result->post_title,
                'padre_id' => absint($result->parent_id),
            ];
        }

        return self::$usage_index;
    }

    public static function flush_usage_index($meta_id = 0, $object_id = 0, $meta_key = ''): void {
        if ('tabla_precio_id' === $meta_key) {
            self::$usage_index = null;
        }
    }

    /** Ámbito elegido en la tabla; si no se eligió, se deduce de sus usos. */
    public static function ambito(int $table_id): string {
        $stored = Tabla_Precio_Schema::sanitize_ambito(get_post_meta($table_id, 'tabla_precios_ambito', true));
        return $stored !== '' ? $stored : self::inferred_ambito($table_id);
    }

    public static function inferred_ambito(int $table_id): string {
        $entities = array_column(self::linked_uses($table_id), 'entidad');
        if (in_array(FLACSO_Cohorte::POST_TYPE, $entities, true)) {
            return 'oferta';
        }
        return in_array(FLACSO_Edicion::POST_TYPE, $entities, true) ? 'seminario' : '';
    }

    public static function ambito_label(string $ambito): string {
        $labels = [
            'oferta' => __('Oferta académica', 'flacso-uruguay'),
            'seminario' => __('Seminario', 'flacso-uruguay'),
        ];
        return $labels[$ambito] ?? __('Sin ámbito', 'flacso-uruguay');
    }

    /** Ofertas académicas que usan la tabla, con las cohortes de cada una. */
    public static function linked_offers(int $table_id): array {
        $offers = [];
        foreach (self::linked_uses($table_id) as $use) {
            if (FLACSO_Cohorte::POST_TYPE !== $use['entidad']) {
                continue;
            }
            $offer_id = (int) $use['padre_id'];
            $offers[$offer_id] ??= [
                'id' => $offer_id,
                'nombre' => $offer_id > 0 ? get_the_title($offer_id) : __('Sin oferta asignada', 'flacso-uruguay'),
                'cohortes' => [],
            ];
            $offers[$offer_id]['cohortes'][] = $use;
        }
        return array_values($offers);
    }

    /**
     * Tablas agrupadas por ámbito para los selectores de Cohorte y Edición:
     * primero las del ámbito pedido, luego las sin ámbito y al final las del otro.
     */
    public static function grouped_options(string $ambito): array {
        $tables = get_posts([
            'post_type' => 'tabla-precio',
            'post_status' => ['publish', 'draft', 'pending', 'private'],
            'posts_per_page' => -1,
            'orderby' => 'title',
            'order' => 'ASC',
        ]);
        $groups = [$ambito => [], '' => []];
        foreach ($tables as $table) {
            $groups[self::ambito((int) $table->ID)][] = $table;
        }

        $result = [];
        foreach ($groups as $group_ambito => $group_tables) {
            if ($group_tables) {
                $result[] = [
                    'label' => $group_ambito === '' ? __('Sin ámbito', 'flacso-uruguay') : self::ambito_label((string) $group_ambito),
                    'tables' => $group_tables,
                ];
            }
        }
        return $result;
    }

    public static function monetary_context(int $post_id): ?array {
        $post_type = get_post_type($post_id);
        $table_id = $post_type === 'tabla-precio' ? $post_id : absint(get_post_meta($post_id, 'tabla_precio_id', true));
        if ($table_id < 1 && in_array($post_type, ['oferta-academica', 'seminario'], true)) {
            $temporal_type = $post_type === 'oferta-academica' ? FLACSO_Cohorte::POST_TYPE : FLACSO_Edicion::POST_TYPE;
            $parent_key = $post_type === 'oferta-academica' ? FLACSO_Cohorte::META_PARENT_ID : FLACSO_Edicion::META_PARENT_ID;
            $temporal_ids = get_posts([
                'post_type' => $temporal_type,
                'post_status' => ['publish', 'draft', 'pending', 'private'],
                'posts_per_page' => 1,
                'fields' => 'ids',
                'meta_key' => $parent_key,
                'meta_value' => $post_id,
                'orderby' => 'date',
                'order' => 'DESC',
            ]);
            $table_id = !empty($temporal_ids) ? absint(get_post_meta($temporal_ids[0], 'tabla_precio_id', true)) : 0;
        }
        if ($table_id < 1) {
            return null;
        }
        $rows = get_post_meta($table_id, 'precios_filas', true);
        if (!is_array($rows) || !$rows) {
            return null;
        }
        $row = null;
        foreach ($rows as $candidate) {
            if (!empty($candidate['destacada'])) {
                $row = $candidate;
                break;
            }
        }
        $row = $row ?: $rows[0];
        $extract = static function ($value): float {
            $digits = preg_replace('/[^0-9,.-]/', '', (string) $value);
            return (float) str_replace(',', '.', $digits);
        };
        $usd = $extract($row['usd'] ?? '');
        $uyu = $extract($row['uyu'] ?? '');
        if ($usd > 0) {
            return ['value' => $usd, 'currency' => 'USD', 'tableId' => $table_id];
        }
        return $uyu > 0 ? ['value' => $uyu, 'currency' => 'UYU', 'tableId' => $table_id] : null;
    }
}
