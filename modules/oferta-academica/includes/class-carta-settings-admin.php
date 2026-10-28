<?php

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Pantalla "Textos de la carta" (FLACSO → Textos de la carta).
 *
 * Permite editar cada texto institucional de la carta y muestra, junto a cada
 * uno, la regla con la que se muestra. La regla es la misma que aplica el tema
 * (FLACSO_Carta_Settings::applies()), no una descripción aparte.
 */
final class FLACSO_Carta_Settings_Admin {
    public const PAGE_SLUG = 'flacso-carta-textos';
    private const ACTION = 'flacso_save_carta_textos';
    private const NONCE = 'flacso_carta_textos_nonce';
    private const CAPABILITY = 'edit_others_posts';

    public static function init(): void {
        if (!is_admin()) {
            return;
        }
        add_action('admin_menu', [self::class, 'register_menu'], 20);
        add_action('admin_post_' . self::ACTION, [self::class, 'handle_save']);
    }

    public static function register_menu(): void {
        add_submenu_page(
            FLACSO_Admin_Panel::PAGE_SLUG,
            __('Textos de la carta', 'flacso-uruguay'),
            __('Textos de la carta', 'flacso-uruguay'),
            self::CAPABILITY,
            self::PAGE_SLUG,
            [self::class, 'render']
        );
    }

    public static function handle_save(): void {
        if (!current_user_can(self::CAPABILITY)) {
            wp_die(esc_html__('No tenés permisos para editar estos textos.', 'flacso-uruguay'), '', ['response' => 403]);
        }
        check_admin_referer(self::ACTION, self::NONCE);

        $values = isset($_POST['carta_textos']) && is_array($_POST['carta_textos']) ? wp_unslash($_POST['carta_textos']) : [];
        $resets = isset($_POST['carta_textos_reset']) && is_array($_POST['carta_textos_reset']) ? wp_unslash($_POST['carta_textos_reset']) : [];

        foreach (FLACSO_Carta_Settings::keys() as $key) {
            if (!empty($resets[$key])) {
                FLACSO_Carta_Settings::reset($key);
            } elseif (array_key_exists($key, $values)) {
                update_option(FLACSO_Carta_Settings::option_name($key), FLACSO_Carta_Settings::sanitize($key, $values[$key]));
            }
        }

        wp_safe_redirect(add_query_arg(['page' => self::PAGE_SLUG, 'updated' => '1'], admin_url('admin.php')));
        exit;
    }

    public static function render(): void {
        if (!current_user_can(self::CAPABILITY)) {
            return;
        }
        $updated = isset($_GET['updated']) && $_GET['updated'] === '1';
        ?>
        <div class="wrap flacso-carta-textos">
            <h1><?php esc_html_e('Textos de la carta', 'flacso-uruguay'); ?></h1>
            <p class="description">
                <?php esc_html_e('Textos institucionales que se repiten en la carta de cada oferta. Debajo de cada uno figura la regla con la que se muestra. Un texto vacío no se muestra; el marcador {anio} se reemplaza por el año de inicio de la cohorte.', 'flacso-uruguay'); ?>
            </p>
            <?php if ($updated) : ?>
                <div class="notice notice-success is-dismissible"><p><?php esc_html_e('Textos guardados.', 'flacso-uruguay'); ?></p></div>
            <?php endif; ?>

            <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
                <input type="hidden" name="action" value="<?php echo esc_attr(self::ACTION); ?>">
                <?php wp_nonce_field(self::ACTION, self::NONCE); ?>

                <?php foreach (FLACSO_Carta_Settings::groups() as $group_key => $group) : ?>
                    <section class="flacso-carta-textos__group">
                        <h2><?php echo esc_html($group[0]); ?></h2>
                        <p class="description"><?php echo esc_html($group[1]); ?></p>
                        <?php foreach (FLACSO_Carta_Settings::keys() as $key) : ?>
                            <?php
                            $meta = FLACSO_Carta_Settings::describe($key);
                            if ($meta === null || $meta['group'] !== $group_key) {
                                continue;
                            }
                            self::render_field($key, $meta);
                            ?>
                        <?php endforeach; ?>
                    </section>
                <?php endforeach; ?>

                <?php submit_button(__('Guardar textos', 'flacso-uruguay')); ?>
            </form>
        </div>
        <?php
        self::render_styles();
    }

    /**
     * @param array{type:string,default:string,group:string,label:string,condition:string,rule:string,multiline:bool} $meta
     */
    private static function render_field(string $key, array $meta): void {
        $id = 'carta-texto-' . $key;
        $value = FLACSO_Carta_Settings::get($key);
        $is_default = $value === $meta['default'];
        $always = $meta['condition'] === FLACSO_Carta_Settings::COND_ALWAYS;
        ?>
        <div class="flacso-carta-textos__field">
            <div class="flacso-carta-textos__head">
                <label for="<?php echo esc_attr($id); ?>"><strong><?php echo esc_html($meta['label']); ?></strong></label>
                <span class="flacso-carta-textos__rule<?php echo $always ? '' : ' is-conditional'; ?>">
                    <span class="dashicons <?php echo $always ? 'dashicons-visibility' : 'dashicons-filter'; ?>" aria-hidden="true"></span>
                    <?php echo esc_html($meta['rule']); ?>
                </span>
            </div>
            <?php if ($meta['multiline'] || $meta['type'] === 'html') : ?>
                <textarea id="<?php echo esc_attr($id); ?>" name="carta_textos[<?php echo esc_attr($key); ?>]" rows="<?php echo $meta['type'] === 'html' ? '5' : '3'; ?>" class="large-text"><?php echo esc_textarea($value); ?></textarea>
            <?php else : ?>
                <input id="<?php echo esc_attr($id); ?>" type="<?php echo $meta['type'] === 'email' ? 'email' : 'text'; ?>" name="carta_textos[<?php echo esc_attr($key); ?>]" value="<?php echo esc_attr($value); ?>" class="large-text">
            <?php endif; ?>
            <div class="flacso-carta-textos__foot">
                <?php if ($meta['type'] === 'html') : ?>
                    <span><?php esc_html_e('Admite HTML básico (negritas, enlaces, párrafos).', 'flacso-uruguay'); ?></span>
                <?php endif; ?>
                <?php if (strpos($meta['default'], '{anio}') !== false) : ?>
                    <span><?php esc_html_e('Marcador: {anio}', 'flacso-uruguay'); ?></span>
                <?php endif; ?>
                <?php if ($value === '') : ?>
                    <span class="flacso-carta-textos__hidden"><?php esc_html_e('Vacío: este texto no se muestra.', 'flacso-uruguay'); ?></span>
                <?php endif; ?>
                <?php if (!$is_default) : ?>
                    <label>
                        <input type="checkbox" name="carta_textos_reset[<?php echo esc_attr($key); ?>]" value="1">
                        <?php esc_html_e('Volver al texto por defecto al guardar', 'flacso-uruguay'); ?>
                    </label>
                    <details>
                        <summary><?php esc_html_e('Ver texto por defecto', 'flacso-uruguay'); ?></summary>
                        <div><?php echo $meta['type'] === 'html' ? wp_kses_post($meta['default']) : esc_html($meta['default']); ?></div>
                    </details>
                <?php endif; ?>
            </div>
        </div>
        <?php
    }

    private static function render_styles(): void {
        ?>
        <style>
            .flacso-carta-textos{max-width:980px}
            .flacso-carta-textos__group{margin:24px 0;padding:18px 20px;border:1px solid #dcdcde;border-radius:8px;background:#fff}
            .flacso-carta-textos__group>h2{margin:0 0 4px}
            .flacso-carta-textos__field{margin-top:18px;padding-top:16px;border-top:1px solid #f0f0f1}
            .flacso-carta-textos__head{display:flex;flex-wrap:wrap;align-items:center;justify-content:space-between;gap:6px 14px;margin-bottom:6px}
            .flacso-carta-textos__rule{display:inline-flex;align-items:center;gap:5px;padding:3px 10px;border-radius:99px;background:#f0f0f1;color:#3c434a;font-size:12px}
            .flacso-carta-textos__rule.is-conditional{background:#e1ebf9;color:#29456d}
            .flacso-carta-textos__rule .dashicons{width:16px;height:16px;font-size:16px}
            .flacso-carta-textos__foot{display:flex;flex-wrap:wrap;align-items:center;gap:6px 16px;margin-top:6px;color:#646970;font-size:12px}
            .flacso-carta-textos__hidden{color:#b32d2e;font-weight:600}
            .flacso-carta-textos__foot details div{margin-top:4px;padding:8px 10px;background:#f6f7f7;border-left:3px solid #c3c4c7}
        </style>
        <?php
    }
}
