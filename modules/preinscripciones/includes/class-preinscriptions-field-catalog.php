<?php

if (!defined('ABSPATH')) {
    exit;
}

final class FLACSO_Preinscriptions_Field_Catalog {
    private const LABELS = [
        'documento'       => 'Documento',
        'fechaNacimiento' => 'Fecha de nacimiento',
        'titulo'          => 'Título',
        'escolaridad'     => 'Escolaridad',
        'orientacion'     => 'Orientación',
        'mencion'         => 'Mención',
        'institucion'     => 'Institución',
        'ocupacion'       => 'Ocupación',
    ];

    public static function keys(): array {
        return array_keys(self::LABELS);
    }

    public static function labels(): array {
        return self::LABELS;
    }

    public static function has(string $key): bool {
        return self::canonical_key($key) !== null;
    }

    private static function canonical_key(string $key): ?string {
        $normalized = strtolower(trim($key));
        foreach (self::keys() as $approved_key) {
            if (strtolower($approved_key) === $normalized) {
                return $approved_key;
            }
        }

        return null;
    }
}
