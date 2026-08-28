<?php

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Ejecuta una sola vez la migracion de los metadatos historicos de Oferta
 * Academica hacia entidades Cohorte. Si alguna fila falla no marca la migracion
 * como completada, por lo que puede reintentarse en la siguiente carga.
 */
final class FLACSO_Cohorte_Auto_Migration {
    private const OPTION_STATUS = 'flacso_cohort_migration_v1_status';
    private const OPTION_RESULT = 'flacso_cohort_migration_v1_result';

    public static function init(): void {
        add_action('init', [self::class, 'maybe_run'], 30);
    }

    public static function maybe_run(): void {
        if (wp_installing() || get_option(self::OPTION_STATUS) === 'done') {
            return;
        }
        if (!class_exists('FLACSO_Cohorte_API')) {
            return;
        }

        $result = FLACSO_Cohorte_API::migrate_legacy_offer_meta();
        update_option(self::OPTION_RESULT, $result, false);

        if ((int) ($result['errors'] ?? 0) === 0) {
            update_option(self::OPTION_STATUS, 'done', false);
        }
    }
}
