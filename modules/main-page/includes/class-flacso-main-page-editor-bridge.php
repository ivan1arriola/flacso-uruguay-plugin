<?php
/**
 * Puente del administrador clásico hacia el editor institucional.
 *
 * Conserva el nombre de clase esperado por Flacso_Main_Page_Admin para evitar
 * romper enlaces de WordPress, pero elimina un segundo editor de settings.
 */

if (!defined('ABSPATH')) {
    exit;
}

final class Flacso_Main_Page_Unified_Settings {
    public static function init(): void {
        // Sin assets propios: el editor institucional es la UI canónica.
    }

    public static function editor_url(): string {
        return (string) apply_filters(
            'flacso_main_page_editor_url',
            'https://editor.flacso.edu.uy/main-page'
        );
    }

    public static function render_unified_page(): void {
        if (!current_user_can('manage_options')) {
            return;
        }

        $settings = Flacso_Main_Page_Settings::get_settings();
        $visible = Flacso_Main_Page_Settings::get_section_visibility();
        $order = Flacso_Main_Page_Settings::get_homepage_section_order();
        $visible_count = count(array_filter($visible));
        ?>
        <div class="wrap">
            <h1><?php esc_html_e('Portada institucional', 'flacso-main-page'); ?></h1>
            <p class="description" style="max-width:760px">
                <?php esc_html_e('La edición de la portada se administra desde el Editor FLACSO. WordPress conserva el contenido y expone el contrato REST; el editor es la única interfaz principal para modificarlo.', 'flacso-main-page'); ?>
            </p>

            <p style="margin:24px 0">
                <a class="button button-primary button-hero" href="<?php echo esc_url(self::editor_url()); ?>" target="_blank" rel="noopener noreferrer">
                    <?php esc_html_e('Abrir Editor de Portada', 'flacso-main-page'); ?>
                </a>
                <a class="button button-secondary button-hero" href="<?php echo esc_url(home_url('/')); ?>" target="_blank" rel="noopener noreferrer">
                    <?php esc_html_e('Ver portada pública', 'flacso-main-page'); ?>
                </a>
            </p>

            <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(220px,1fr));gap:16px;max-width:900px;margin-top:26px">
                <div class="card" style="max-width:none;margin:0">
                    <h2><?php esc_html_e('Contrato', 'flacso-main-page'); ?></h2>
                    <p><code>flacso/v1/main-page/settings</code></p>
                    <p><?php esc_html_e('Schema de portada: versión 2.', 'flacso-main-page'); ?></p>
                </div>
                <div class="card" style="max-width:none;margin:0">
                    <h2><?php esc_html_e('Secciones visibles', 'flacso-main-page'); ?></h2>
                    <p style="font-size:32px;margin:8px 0"><strong><?php echo esc_html((string) $visible_count); ?></strong></p>
                    <p><?php echo esc_html(sprintf(__('de %d secciones configurables', 'flacso-main-page'), count($visible))); ?></p>
                </div>
                <div class="card" style="max-width:none;margin:0">
                    <h2><?php esc_html_e('Orden', 'flacso-main-page'); ?></h2>
                    <ol style="margin-left:20px">
                        <?php foreach ($order as $section_key) : ?>
                            <li><?php echo esc_html(Flacso_Main_Page_Settings::get_section_label($section_key)); ?></li>
                        <?php endforeach; ?>
                    </ol>
                </div>
            </div>

            <?php if (isset($settings['oferta_academica']) && is_array($settings['oferta_academica'])) : ?>
                <div class="notice notice-info inline" style="margin-top:24px;max-width:860px">
                    <p><strong><?php esc_html_e('Oferta Académica', 'flacso-main-page'); ?></strong><br>
                    <?php esc_html_e('La clave canónica es oferta_academica. La antigua clave posgrados se migra automáticamente y se conserva únicamente como compatibilidad histórica.', 'flacso-main-page'); ?></p>
                </div>
            <?php endif; ?>
        </div>
        <?php
    }
}
