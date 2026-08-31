<?php

if (!defined('ABSPATH')) {
    exit;
}

/** Adaptador de portada al modelo final de Seminario/EdicionSeminario. */
final class Flacso_Main_Page_Seminarios {
    public static function init(): void {}

    public static function current_edition(int $seminar_id): array {
        if (!class_exists('FLACSO_Academic_Catalog')) {
            return [];
        }
        $seminar = FLACSO_Academic_Catalog::get_seminar($seminar_id);
        return is_array($seminar['edicion_vigente'] ?? null) ? $seminar['edicion_vigente'] : [];
    }

    public static function get_start_date(int $seminar_id): string {
        return (string) (self::current_edition($seminar_id)['fecha_inicio'] ?? '');
    }

    public static function get_end_date(int $seminar_id): string {
        return (string) (self::current_edition($seminar_id)['fecha_fin'] ?? '');
    }
}
