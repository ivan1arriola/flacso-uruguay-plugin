<?php

if (!defined('ABSPATH')) {
    exit;
}

/** Autorrelacion canonica entre ofertas, persistida en el origen. */
final class FLACSO_Relacion_Oferta_Academica {
    public const META_KEY = '_flacso_relaciones_oferta_academica';
    public const INTEGRA = 'integra';
    public const COMPUESTO_POR = 'compuesto_por';
    public const PRECEDE = 'precede';

    public static function tipos(): array {
        return [self::INTEGRA, self::COMPUESTO_POR, self::PRECEDE];
    }

    public static function normalize(array $relations): array {
        $normalized = [];
        foreach ($relations as $relation) {
            if (!is_array($relation)) {
                continue;
            }
            $destination = absint($relation['oferta_destino'] ?? 0);
            $type = sanitize_key((string) ($relation['tipo_relacion'] ?? ''));
            if ($destination <= 0 || !in_array($type, self::tipos(), true)) {
                continue;
            }
            $key = $type . ':' . $destination;
            // La primera aparicion define el orden. Esto es importante al
            // canonicalizar varias ediciones legacy hacia una misma oferta.
            if (isset($normalized[$key])) {
                continue;
            }
            $normalized[$key] = [
                'oferta_destino' => $destination,
                'tipo_relacion' => $type,
                'orden' => max(0, absint($relation['orden'] ?? 0)),
            ];
        }
        uasort($normalized, static function (array $a, array $b): int {
            return [$a['orden'], $a['oferta_destino']] <=> [$b['orden'], $b['oferta_destino']];
        });
        return array_values($normalized);
    }

    public static function get(int $origin_id, string $type = ''): array {
        $relations = get_post_meta($origin_id, self::META_KEY, true);
        $relations = self::normalize(is_array($relations) ? $relations : []);
        if ($type === '') {
            return $relations;
        }
        return array_values(array_filter($relations, static function (array $relation) use ($type): bool {
            return $relation['tipo_relacion'] === $type;
        }));
    }

    public static function replace_type(int $origin_id, string $type, array $destination_ids): void {
        if (!in_array($type, self::tipos(), true)) {
            return;
        }
        $keep = array_values(array_filter(self::get($origin_id), static function (array $relation) use ($type): bool {
            return $relation['tipo_relacion'] !== $type;
        }));
        foreach (array_values(array_unique(array_map('absint', $destination_ids))) as $order => $destination_id) {
            if ($destination_id > 0 && $destination_id !== $origin_id) {
                $keep[] = [
                    'oferta_destino' => $destination_id,
                    'tipo_relacion' => $type,
                    'orden' => $order,
                ];
            }
        }
        $normalized = self::normalize($keep);
        if (self::get($origin_id) !== $normalized) {
            update_post_meta($origin_id, self::META_KEY, $normalized);
        }
    }

    /** Reemplaza un tipo conservando el orden explicito de la primera relacion. */
    public static function replace_type_relations(int $origin_id, string $type, array $relations): void {
        if (!in_array($type, self::tipos(), true)) {
            return;
        }
        $keep = array_values(array_filter(self::get($origin_id), static function (array $relation) use ($type): bool {
            return $relation['tipo_relacion'] !== $type;
        }));
        foreach ($relations as $relation) {
            if (!is_array($relation)) {
                continue;
            }
            $destination_id = absint($relation['oferta_destino'] ?? 0);
            if ($destination_id <= 0 || $destination_id === $origin_id) {
                continue;
            }
            $keep[] = [
                'oferta_destino' => $destination_id,
                'tipo_relacion' => $type,
                'orden' => max(0, absint($relation['orden'] ?? 0)),
            ];
        }
        $normalized = self::normalize($keep);
        if (self::get($origin_id) !== $normalized) {
            update_post_meta($origin_id, self::META_KEY, $normalized);
        }
    }
}
