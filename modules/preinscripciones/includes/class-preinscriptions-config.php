<?php

if (!defined('ABSPATH')) {
    exit;
}

final class FLACSO_Preinscriptions_Config {
    public static function sanitize_inputs($value): array {
        if (!is_array($value)) {
            return [];
        }

        $result = [];
        $seen = [];

        foreach ($value as $record) {
            if (!is_array($record)) {
                continue;
            }

            $key = self::canonical_field_key($record['key'] ?? null);
            $position = self::positive_integer($record['position'] ?? null);
            if ($key === null || $position === null || isset($seen[$key])) {
                continue;
            }

            $result[] = [
                'key'      => $key,
                'position' => $position,
                'required' => self::boolean($record['required'] ?? false),
            ];
            $seen[$key] = true;
        }

        usort($result, static function (array $left, array $right): int {
            $position_order = $left['position'] <=> $right['position'];
            return $position_order !== 0 ? $position_order : strcmp($left['key'], $right['key']);
        });

        return $result;
    }

    public static function sanitize_orientations($value): array {
        if (!is_array($value)) {
            return [];
        }

        $result = [];
        $seen = [];

        foreach ($value as $record) {
            if (!is_array($record)) {
                continue;
            }

            $id = self::slug($record['id'] ?? null);
            $name = self::text($record['name'] ?? null);
            if ($id === null || $name === null || isset($seen[$id])) {
                continue;
            }

            $result[] = ['id' => $id, 'name' => $name];
            $seen[$id] = true;
        }

        return $result;
    }

    public static function sanitize_mentions($value): array {
        return self::sanitize_named_items($value);
    }

    public static function sanitize_documents($value): array {
        if (!is_array($value)) {
            return [];
        }

        $result = [];
        $seen = [];
        foreach ($value as $record) {
            if (!is_array($record)) {
                continue;
            }
            $key = is_scalar($record['key'] ?? null) ? trim((string) $record['key']) : '';
            $position = self::positive_integer($record['position'] ?? null);
            if (!FLACSO_Preinscriptions_Field_Catalog::has_document($key) || $position === null || isset($seen[$key])) {
                continue;
            }
            $result[] = [
                'key' => $key,
                'position' => $position,
                'required' => self::boolean($record['required'] ?? false),
                'canDefer' => self::boolean($record['canDefer'] ?? false),
            ];
            $seen[$key] = true;
        }
        usort($result, static function (array $left, array $right): int {
            return ($left['position'] <=> $right['position']) ?: strcmp($left['key'], $right['key']);
        });
        return $result;
    }

    public static function canonical_payload(array $inputs, array $orientations, array $mentions = [], array $documents = []): array {
        $orientations = self::sanitize_orientations($orientations);
        $mentions = self::sanitize_mentions($mentions);
        $documents = self::sanitize_documents($documents);

        usort($orientations, static function (array $left, array $right): int {
            return strcmp($left['id'], $right['id']);
        });

        return [
            'inputs'       => self::sanitize_inputs($inputs),
            'orientations' => $orientations,
            'mentions'     => $mentions,
            'documents'    => $documents,
        ];
    }

    public static function revision(array $canonical): string {
        $stable = self::stable_value($canonical);
        $json = json_encode($stable, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);

        return 'sha256:' . hash('sha256', $json === false ? 'null' : $json);
    }

    private static function sanitize_named_items($value): array {
        if (!is_array($value)) {
            return [];
        }

        $result = [];
        $seen = [];

        foreach ($value as $record) {
            if (!is_array($record)) {
                continue;
            }

            $id = self::slug($record['id'] ?? null);
            $name = self::text($record['name'] ?? null);
            if ($id === null || $name === null || isset($seen[$id])) {
                continue;
            }

            $result[] = ['id' => $id, 'name' => $name];
            $seen[$id] = true;
        }

        return $result;
    }

    private static function canonical_field_key($value): ?string {
        if (!is_scalar($value)) {
            return null;
        }

        $normalized = strtolower(trim((string) $value));
        foreach (FLACSO_Preinscriptions_Field_Catalog::keys() as $approved_key) {
            if (strtolower($approved_key) === $normalized) {
                return $approved_key;
            }
        }

        return null;
    }

    private static function positive_integer($value): ?int {
        if (is_int($value)) {
            $position = $value;
        } elseif (is_string($value) && preg_match('/^-?\d+$/', trim($value)) === 1) {
            $position = (int) trim($value);
        } else {
            return null;
        }

        $position = abs($position);
        return $position > 0 ? $position : null;
    }

    private static function boolean($value): bool {
        if (is_bool($value)) {
            return $value;
        }
        if (is_int($value) || is_float($value)) {
            return $value !== 0 && $value !== 0.0;
        }
        if (!is_string($value)) {
            return false;
        }

        return in_array(strtolower(trim($value)), ['1', 'true', 'yes', 'on', 'si', 'sí'], true);
    }

    private static function slug($value): ?string {
        if (!is_scalar($value)) {
            return null;
        }

        $slug = strtolower(trim((string) $value));
        return $slug !== '' && preg_match('/^[a-z0-9]+(?:-[a-z0-9]+)*$/', $slug) === 1 ? $slug : null;
    }

    private static function text($value): ?string {
        if (!is_scalar($value)) {
            return null;
        }

        $text = trim(strip_tags((string) $value));
        return $text === '' ? null : $text;
    }

    private static function stable_value($value) {
        if (!is_array($value) || $value === []) {
            return $value;
        }

        $keys = array_keys($value);
        $is_list = $keys === range(0, count($value) - 1);
        if ($is_list) {
            return array_map([self::class, 'stable_value'], $value);
        }

        ksort($value, SORT_STRING);
        foreach ($value as $key => $nested_value) {
            $value[$key] = self::stable_value($nested_value);
        }

        return $value;
    }
}
