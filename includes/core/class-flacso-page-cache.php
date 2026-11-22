<?php
/**
 * Purga de la caché de página (WP Super Cache) cuando cambia algo que se ve en el
 * sitio público pero no es un post: opciones de portada, carta, anuncio, Meta, etc.
 *
 * WP Super Cache ya vacía la caché al editar posts y menús; no se entera de las opciones.
 */

if (!defined('ABSPATH')) {
    exit;
}

final class FLACSO_Page_Cache {
    /** Opciones exactas que alteran el HTML público. */
    private const OPTIONS = [
        'flacso-main-page_settings',
        'flacso_autoridades_data',
        'flacso_financiacion_html',
        'flacso_section_novedades_highlight_order',
        'flacso_oferta_consulta_button_enabled',
        'flacso_oferta_consulta_endpoint_url',
        'flacso_home_v2_enabled',
        'flacso_maintenance_mode',
        'flacso_mensaje_bienvenida',
        'flacso_seminarios_dias_cierre_post_inicio',
        'flacso_meta_enabled',
        'flacso_meta_pixel_id',
        'flacso_meta_track_pageview',
        'flacso_meta_test_event_code',
        'fc_use_recaptcha',
        'fc_recaptcha_site_key',
        'fc_cargar_bootstrap',
        'sticky_posts',
        'blogname',
        'blogdescription',
        'page_on_front',
        'show_on_front',
    ];

    /** Prefijos de grupos de opciones (carta, anuncio de navegación, mensajes de inscripción, tema). */
    private const PREFIXES = [
        'flacso_carta_',
        'flacso_nav_announcement_',
        'flacso_inscripciones_mensaje_',
        'theme_mods_',
    ];

    private static bool $pending = false;

    public static function init(): void {
        add_action('updated_option', [self::class, 'on_option_change']);
        add_action('added_option', [self::class, 'on_option_change']);
        add_action('deleted_option', [self::class, 'on_option_change']);
    }

    public static function affects_public_pages(string $option): bool {
        if (in_array($option, self::OPTIONS, true)) {
            return true;
        }
        foreach (self::PREFIXES as $prefix) {
            if (strpos($option, $prefix) === 0) {
                return true;
            }
        }
        return false;
    }

    public static function on_option_change($option): void {
        if (self::$pending || !is_string($option) || !self::affects_public_pages($option)) {
            return;
        }
        // Una sola purga por request, al final: guardar la carta actualiza decenas de opciones.
        self::$pending = true;
        add_action('shutdown', [self::class, 'purge']);
    }

    public static function purge(): bool {
        if (!function_exists('wp_cache_clear_cache')) {
            return false; // Sin WP Super Cache activo no hay nada que vaciar.
        }
        wp_cache_clear_cache();
        return true;
    }
}

FLACSO_Page_Cache::init();
