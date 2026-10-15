<?php

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Controles de la portada vigente. Los ajustes históricos siguen guardados
 * para poder volver a la portada anterior sin migrar ni borrar datos.
 */
final class Flacso_Homepage_Admin_V2 {
    private const SAVE_ACTION = 'flacso_save_homepage_v2';

    public static function init(): void {
        add_action('admin_post_' . self::SAVE_ACTION, [__CLASS__, 'save']);
    }

    public static function render(): void {
        if (!current_user_can('manage_options')) {
            return;
        }

        if (function_exists('wp_enqueue_editor')) {
            wp_enqueue_editor();
        }

        $offer = Flacso_Main_Page_Settings::get_section('oferta_academica');
        $institution = Flacso_Main_Page_Settings::get_section('quienes');
        $cards = is_array($offer['cards'] ?? null) ? $offer['cards'] : [];
        $notice = isset($_GET['flacso_home_notice']) ? sanitize_key(wp_unslash($_GET['flacso_home_notice'])) : '';
        ?>
        <div class="wrap flacso-homepage-admin-v2">
            <h1><?php esc_html_e('Portada del sitio', 'flacso-main-page'); ?></h1>
            <p class="description"><?php esc_html_e('Editá el contenido que utiliza la portada actual. Su estructura, títulos y orden se definen en el tema.', 'flacso-main-page'); ?></p>

            <?php if ($notice === 'saved') : ?>
                <div class="notice notice-success is-dismissible"><p><?php esc_html_e('Cambios de la portada guardados.', 'flacso-main-page'); ?></p></div>
            <?php elseif ($notice === 'invalid_url') : ?>
                <div class="notice notice-error"><p><?php esc_html_e('Revisá los enlaces de la oferta: usá una ruta interna o una URL HTTP/HTTPS válida.', 'flacso-main-page'); ?></p></div>
            <?php elseif ($notice === 'empty_content') : ?>
                <div class="notice notice-error"><p><?php esc_html_e('El texto institucional no puede quedar vacío.', 'flacso-main-page'); ?></p></div>
            <?php endif; ?>

            <div class="flacso-homepage-admin-v2__quicklinks">
                <a class="button button-primary" href="<?php echo esc_url(home_url('/')); ?>" target="_blank" rel="noopener noreferrer"><?php esc_html_e('Ver portada', 'flacso-main-page'); ?></a>
                <a class="button" href="<?php echo esc_url(admin_url('edit.php?post_type=flacso_home_campaign')); ?>"><?php esc_html_e('Editar campañas del hero', 'flacso-main-page'); ?></a>
                <a class="button" href="<?php echo esc_url(admin_url('edit.php?post_type=evento')); ?>"><?php esc_html_e('Gestionar actividades', 'flacso-main-page'); ?></a>
                <a class="button" href="<?php echo esc_url(admin_url('edit.php')); ?>"><?php esc_html_e('Gestionar noticias', 'flacso-main-page'); ?></a>
            </div>

            <section class="flacso-homepage-admin-v2__section" aria-labelledby="flacso-home-admin-news">
                <h2 id="flacso-home-admin-news"><?php esc_html_e('Noticias fijadas', 'flacso-main-page'); ?></h2>
                <p><?php esc_html_e('Las primeras dos aparecen como destacadas. Las demás siguen visibles entre las últimas noticias. Podés fijarlas y cambiar su orden aquí. Recargá la pantalla para actualizar la lista después de fijar o desfijar.', 'flacso-main-page'); ?></p>
                <?php if (function_exists('flacso_section_novedades_admin_menu_render')) {
                    echo flacso_section_novedades_admin_menu_render();
                } ?>
            </section>

            <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
                <input type="hidden" name="action" value="<?php echo esc_attr(self::SAVE_ACTION); ?>">
                <?php wp_nonce_field(self::SAVE_ACTION); ?>

                <section class="flacso-homepage-admin-v2__section" aria-labelledby="flacso-home-admin-offer">
                    <h2 id="flacso-home-admin-offer"><?php esc_html_e('Tarjetas de oferta académica', 'flacso-main-page'); ?></h2>
                    <p><?php esc_html_e('La portada muestra estas categorías cuando tienen un enlace válido. Los títulos y el orden siguen el diseño aprobado.', 'flacso-main-page'); ?></p>
                    <div class="flacso-homepage-admin-v2__cards">
                        <?php foreach ($cards as $index => $card) :
                            if (!is_array($card)) { continue; }
                            $title = trim(wp_strip_all_tags((string) ($card['title'] ?? '')));
                            if ($title === '') { continue; }
                            ?>
                            <label class="flacso-homepage-admin-v2__card">
                                <strong><?php echo esc_html($title); ?></strong>
                                <span><?php esc_html_e('Enlace de la categoría', 'flacso-main-page'); ?></span>
                                <input type="text" class="regular-text code" name="flacso_home_offer_urls[<?php echo esc_attr($index); ?>]" value="<?php echo esc_attr($card['url'] ?? ''); ?>" placeholder="/formacion/">
                            </label>
                        <?php endforeach; ?>
                    </div>
                </section>

                <section class="flacso-homepage-admin-v2__section" aria-labelledby="flacso-home-admin-about">
                    <h2 id="flacso-home-admin-about"><?php esc_html_e('¿Qué es FLACSO Uruguay?', 'flacso-main-page'); ?></h2>
                    <p><?php esc_html_e('Este texto aparece junto al logo oficial en la portada.', 'flacso-main-page'); ?></p>
                    <?php
                    $content = (string) ($institution['content'] ?? '');
                    if (function_exists('wp_editor')) {
                        wp_editor($content, 'flacso_home_institution_content', [
                            'textarea_name' => 'flacso_home_institution_content',
                            'textarea_rows' => 10,
                            'media_buttons' => false,
                        ]);
                    } else {
                        ?><textarea id="flacso_home_institution_content" name="flacso_home_institution_content" rows="10" class="large-text"><?php echo esc_textarea($content); ?></textarea><?php
                    }
                    ?>
                </section>

                <?php submit_button(__('Guardar oferta y texto institucional', 'flacso-main-page')); ?>
            </form>
        </div>
        <?php
    }

    public static function save(): void {
        if (!current_user_can('manage_options')) {
            wp_die(esc_html__('No tenés permiso para editar la portada.', 'flacso-main-page'));
        }
        check_admin_referer(self::SAVE_ACTION);

        $submitted = isset($_POST['flacso_home_offer_urls']) && is_array($_POST['flacso_home_offer_urls'])
            ? wp_unslash($_POST['flacso_home_offer_urls'])
            : [];
        $content = isset($_POST['flacso_home_institution_content'])
            ? wp_kses_post(wp_unslash((string) $_POST['flacso_home_institution_content']))
            : '';
        if (trim(wp_strip_all_tags($content)) === '') {
            self::redirect('empty_content');
        }

        $offer = Flacso_Main_Page_Settings::get_section('oferta_academica');
        $cards = is_array($offer['cards'] ?? null) ? $offer['cards'] : [];
        foreach ($cards as $index => &$card) {
            if (!is_array($card) || !array_key_exists($index, $submitted)) {
                continue;
            }
            if (!is_string($submitted[$index])) {
                self::redirect('invalid_url');
            }
            $raw_url = trim($submitted[$index]);
            $url = $raw_url === '' || $raw_url === '#' ? '' : FLACSO_Home_Campaign::sanitize_url($raw_url);
            if ($raw_url !== '' && $raw_url !== '#' && $url === '') {
                self::redirect('invalid_url');
            }
            $card['url'] = $url;
        }
        unset($card);

        $saved = get_option(Flacso_Main_Page_Settings::OPTION_KEY, []);
        $saved = is_array($saved) ? $saved : [];
        $saved['oferta_academica'] = array_merge(
            is_array($saved['oferta_academica'] ?? null) ? $saved['oferta_academica'] : [],
            ['cards' => $cards]
        );
        $saved['quienes'] = array_merge(
            is_array($saved['quienes'] ?? null) ? $saved['quienes'] : [],
            ['content' => $content]
        );
        update_option(Flacso_Main_Page_Settings::OPTION_KEY, $saved);
        Flacso_Main_Page_Settings::invalidate_cache();
        self::redirect('saved');
    }

    private static function redirect(string $notice): void {
        wp_safe_redirect(admin_url('admin.php?page=flacso-main-page&flacso_home_notice=' . rawurlencode($notice)));
        exit;
    }
}
