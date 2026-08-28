<?php

if (!defined('ABSPATH')) {
    exit;
}

/** Valores de dominio centralizados para la convivencia de ambos circuitos. */
final class FLACSO_Preinscription_Flow {
    public const LEGACY_EDITOR = 'legacy_editor';
    public const GESTOR_PREINSCRIPCIONES = 'gestor_preinscripciones';

    public static function values(): array {
        return [self::LEGACY_EDITOR, self::GESTOR_PREINSCRIPCIONES];
    }

    public static function normalize($value): string {
        $value = sanitize_key((string) $value);
        return in_array($value, self::values(), true) ? $value : self::LEGACY_EDITOR;
    }

    public static function is_valid($value): bool {
        return in_array((string) $value, self::values(), true);
    }
}
