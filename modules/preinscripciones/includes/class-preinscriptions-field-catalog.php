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

    private const DOCUMENT_LABELS = [
        'identidad'            => 'Documento de identidad',
        'cv'                   => 'Curriculum Vitae',
        'titulo'              => 'Título',
        'cartaMotivacion'     => 'Carta de motivación',
        'cartaRecomendacion1' => 'Primera carta de recomendación',
        'cartaRecomendacion2' => 'Segunda carta de recomendación',
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

    public static function document_keys(): array {
        return array_keys(self::DOCUMENT_LABELS);
    }

    public static function document_labels(): array {
        return self::DOCUMENT_LABELS;
    }

    public static function has_document(string $key): bool {
        return in_array($key, self::document_keys(), true);
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
