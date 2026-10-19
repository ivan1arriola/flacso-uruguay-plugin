<?php

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Ajustes funcionales propios de Seminarios.
 */
final class FLACSO_Seminar_Settings {
    public const PAGE_SLUG = 'flacso-seminarios-settings';
    private const SETTINGS_GROUP = 'flacso_seminarios_settings_group';
    public const OPTION_DIAS_CIERRE_POST_INICIO = 'flacso_seminarios_dias_cierre_post_inicio';

    public static function init(): void {
        if (!is_admin()) {
            return;
        }

        add_action('admin_menu', [self::class, 'register_menu'], 20);
        add_action('admin_init', [self::class, 'register_settings']);
    }

    public static function register_menu(): void {
        add_submenu_page(
            'edit.php?post_type=seminario',
            __('Ajustes de seminarios', 'flacso-uruguay'),
            __('Ajustes', 'flacso-uruguay'),
            'manage_options',
            self::PAGE_SLUG,
            [self::class, 'render_page']
        );
    }

    public static function register_settings(): void {
        register_setting(
            self::SETTINGS_GROUP,
            self::OPTION_DIAS_CIERRE_POST_INICIO,
            [
                'type' => 'integer',
                'sanitize_callback' => [self::class, 'sanitize_days'],
                'default' => 10,
            ]
        );
    }

    public static function sanitize_days($value): int {
        $days = absint($value);
        return min(365, $days);
    }

    public static function render_page(): void {
        if (!current_user_can('manage_options')) {
            return;
        }

        $days = (int) get_option(self::OPTION_DIAS_CIERRE_POST_INICIO, 10);
        ?>
        <div class="wrap">
            <h1><?php esc_html_e('Ajustes de seminarios', 'flacso-uruguay'); ?></h1>
            <p><?php esc_html_e('Configuración global del ciclo de vida de las ediciones. Los datos académicos y de cada edición siguen administrándose en sus editores.', 'flacso-uruguay'); ?></p>
            <form method="post" action="options.php">
                <?php settings_fields(self::SETTINGS_GROUP); ?>
                <table class="form-table" role="presentation">
                    <tr>
                        <th scope="row">
                            <label for="<?php echo esc_attr(self::OPTION_DIAS_CIERRE_POST_INICIO); ?>">
                                <?php esc_html_e('Días para cerrar preinscripciones después del inicio', 'flacso-uruguay'); ?>
                            </label>
                        </th>
                        <td>
                            <input
                                id="<?php echo esc_attr(self::OPTION_DIAS_CIERRE_POST_INICIO); ?>"
                                name="<?php echo esc_attr(self::OPTION_DIAS_CIERRE_POST_INICIO); ?>"
                                type="number"
                                min="0"
                                max="365"
                                value="<?php echo esc_attr((string) $days); ?>"
                            >
                            <p class="description"><?php esc_html_e('Valor global usado cuando una edición no define un comportamiento específico.', 'flacso-uruguay'); ?></p>
                        </td>
                    </tr>
                </table>
                <?php submit_button(__('Guardar ajustes', 'flacso-uruguay')); ?>
            </form>
        </div>
        <?php
    }
}
