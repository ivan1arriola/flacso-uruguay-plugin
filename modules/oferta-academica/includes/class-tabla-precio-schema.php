<?php

if (!defined('ABSPATH')) {
    exit;
}

/** Esquema compartido de precios para Cohorte y EdicionSeminario. */
final class Tabla_Precio_Schema {
    public static function init(): void {
        add_action('init', [self::class, 'register_meta'], 12);
    }

    public static function register_meta(): void {
        $definitions = [
            'tabla_precios_tipo' => ['type' => 'string', 'sanitize_callback' => 'sanitize_text_field'],
            'precios_filas' => ['type' => 'array', 'sanitize_callback' => [self::class, 'sanitize_rows']],
            'precios_nota' => ['type' => 'string', 'sanitize_callback' => 'wp_kses_post'],
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

    public static function sanitize_rows($value): array {
        if (!is_array($value)) {
            return [];
        }
        $rows = [];
        foreach ($value as $row) {
            if (!is_array($row)) {
                continue;
            }
            $normalized = [
                'concepto' => sanitize_text_field((string) ($row['concepto'] ?? '')),
                'uyu' => sanitize_text_field((string) ($row['uyu'] ?? '')),
                'usd' => sanitize_text_field((string) ($row['usd'] ?? '')),
                'destacada' => self::sanitize_boolean($row['destacada'] ?? false),
            ];
            if ($normalized['concepto'] !== '' || $normalized['uyu'] !== '' || $normalized['usd'] !== '') {
                $rows[] = $normalized;
            }
        }
        return $rows;
    }

    public static function get_table_data(int $post_id): array {
        return FLACSO_Academic_Repository::to_array('tablas-precio', $post_id);
    }
}

final class FLACSO_Price_Table_Repository {
    public static function linked_uses(int $table_id): array {
        $result = [];
        foreach ([FLACSO_Cohorte::POST_TYPE, FLACSO_Edicion_Seminario::POST_TYPE] as $post_type) {
            $ids = get_posts([
                'post_type' => $post_type,
                'post_status' => 'any',
                'posts_per_page' => -1,
                'fields' => 'ids',
                'meta_key' => 'tabla_precio_id',
                'meta_value' => $table_id,
            ]);
            foreach ($ids as $id) {
                $result[] = ['id' => (int) $id, 'entidad' => $post_type, 'nombre' => get_the_title($id)];
            }
        }
        return $result;
    }

    public static function monetary_context(int $post_id): ?array {
        $post_type = get_post_type($post_id);
        $table_id = $post_type === 'tabla-precio' ? $post_id : absint(get_post_meta($post_id, 'tabla_precio_id', true));
        if ($table_id < 1 && in_array($post_type, ['oferta-academica', 'seminario'], true)) {
            $temporal_type = $post_type === 'oferta-academica' ? FLACSO_Cohorte::POST_TYPE : FLACSO_Edicion_Seminario::POST_TYPE;
            $parent_key = $post_type === 'oferta-academica' ? FLACSO_Cohorte::META_PARENT_ID : FLACSO_Edicion_Seminario::META_PARENT_ID;
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
