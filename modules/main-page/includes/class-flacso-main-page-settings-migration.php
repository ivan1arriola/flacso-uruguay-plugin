<?php
/**
 * Migración estructural de settings de portada.
 */

if (!defined('ABSPATH')) {
    exit;
}

final class Flacso_Main_Page_Settings_Migration {
    private const VERSION_OPTION = 'flacso_main_page_settings_schema_version';
    private const LEGACY_BACKUP_OPTION = 'flacso-main-page_legacy_settings';
    private const TARGET_VERSION = 2;

    public static function init(): void {
        add_action('init', [self::class, 'maybe_migrate'], 1);
    }

    public static function maybe_migrate(): void {
        if ((int) get_option(self::VERSION_OPTION, 0) >= self::TARGET_VERSION) {
            return;
        }

        $raw = get_option(Flacso_Main_Page_Settings::OPTION_KEY, []);
        if (!is_array($raw)) {
            $raw = [];
        }

        self::backup_legacy_values($raw);

        $normalized = class_exists('Flacso_Main_Page_Section_Keys')
            ? Flacso_Main_Page_Section_Keys::normalize_settings($raw)
            : $raw;

        // Completar y sanear únicamente después de convertir las claves. Esto
        // conserva valores actuales y evita reintroducir claves retiradas.
        $normalized = Flacso_Main_Page_Settings::sanitize($normalized);

        if ($normalized !== $raw) {
            update_option(Flacso_Main_Page_Settings::OPTION_KEY, $normalized, false);
        }

        update_option(self::VERSION_OPTION, self::TARGET_VERSION, false);
        Flacso_Main_Page_Settings::invalidate_cache();
    }

    private static function backup_legacy_values(array $raw): void {
        $backup = get_option(self::LEGACY_BACKUP_OPTION, []);
        $backup = is_array($backup) ? $backup : [];
        $changed = false;

        foreach (['festejos', 'posgrados'] as $legacy_key) {
            if (isset($raw[$legacy_key]) && !isset($backup[$legacy_key])) {
                $backup[$legacy_key] = $raw[$legacy_key];
                $changed = true;
            }
        }

        if ($changed) {
            $backup['archived_at'] = current_time('mysql');
            update_option(self::LEGACY_BACKUP_OPTION, $backup, false);
        }
    }
}
