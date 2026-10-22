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
    private const SAVE_HERO_ACTION = 'flacso_save_home_hero_images';

    public static function init(): void {
        add_action('admin_post_' . self::SAVE_ACTION, [__CLASS__, 'save']);
        add_action('admin_post_' . self::SAVE_HERO_ACTION, [__CLASS__, 'save_hero_images']);
    }

    public static function render(): void {
        if (!current_user_can('manage_options')) {
            return;
        }

        wp_enqueue_media();

        if (function_exists('wp_enqueue_editor')) {
            wp_enqueue_editor();
        }

        $active_campaign = FLACSO_Home_Campaign::active();
        $active_id = (int) ($active_campaign['id'] ?? 0);
        $offer = Flacso_Main_Page_Settings::get_section('oferta_academica');
        $institution = Flacso_Main_Page_Settings::get_section('quienes');
        $cards = is_array($offer['cards'] ?? null) ? $offer['cards'] : [];
        $notice = isset($_GET['flacso_home_notice']) ? sanitize_key(wp_unslash($_GET['flacso_home_notice'])) : '';
        ?>
        <div class="wrap flacso-homepage-admin-v2">
            <h1><?php esc_html_e('Portada del sitio', 'flacso-main-page'); ?></h1>
            <p class="description"><?php esc_html_e('Editá el contenido que utiliza la portada actual. Su estructura, títulos y orden se definen en el tema.', 'flacso-main-page'); ?></p>

            <?php if ($notice === 'images_saved') : ?>
                <div class="notice notice-success is-dismissible"><p>Imágenes de portada actualizadas correctamente.</p></div>
            <?php elseif ($notice === 'invalid_image') : ?>
                <div class="notice notice-error"><p>Seleccioná imágenes válidas de la biblioteca multimedia.</p></div>
            <?php elseif ($notice === 'campaign_changed') : ?>
                <div class="notice notice-warning"><p>La campaña activa cambió. Recargá la página para editar las imágenes correctas.</p></div>
            <?php elseif ($notice === 'saved') : ?>
                <div class="notice notice-success is-dismissible"><p><?php esc_html_e('Cambios de la portada guardados.', 'flacso-main-page'); ?></p></div>
            <?php elseif ($notice === 'invalid_url') : ?>
                <div class="notice notice-error"><p><?php esc_html_e('Revisá los enlaces de la oferta: usá una ruta interna o una URL HTTP/HTTPS válida.', 'flacso-main-page'); ?></p></div>
            <?php elseif ($notice === 'empty_content') : ?>
                <div class="notice notice-error"><p><?php esc_html_e('El texto institucional no puede quedar vacío.', 'flacso-main-page'); ?></p></div>
            <?php endif; ?>

            <div class="flacso-homepage-admin-v2__quicklinks">
                <a class="button button-primary" href="<?php echo esc_url(home_url('/')); ?>" target="_blank" rel="noopener noreferrer"><?php esc_html_e('Ver portada', 'flacso-main-page'); ?></a>
                <a class="button" href="<?php echo esc_url(admin_url('edit.php?post_type=flacso_home_campaign')); ?>"><?php esc_html_e('Gestionar campañas y fechas', 'flacso-main-page'); ?></a>
                <a class="button" href="<?php echo esc_url(admin_url('edit.php?post_type=evento')); ?>"><?php esc_html_e('Gestionar actividades', 'flacso-main-page'); ?></a>
                <a class="button" href="<?php echo esc_url(admin_url('edit.php')); ?>"><?php esc_html_e('Gestionar noticias', 'flacso-main-page'); ?></a>
            </div>

            <section class="flacso-homepage-admin-v2__section" aria-labelledby="flacso-home-hero-images">
                <h2 id="flacso-home-hero-images">Imagen principal de la portada</h2>
                <p>Esta es la fotografía que aparece junto al título de la página de inicio. El texto y el botón se agregan automáticamente: no los incluyas dentro de la imagen.</p>
                <?php if ($active_id > 0) : ?>
                    <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
                        <input type="hidden" name="action" value="<?php echo esc_attr(self::SAVE_HERO_ACTION); ?>">
                        <input type="hidden" name="flacso_active_campaign_id" value="<?php echo esc_attr($active_id); ?>">
                        <?php wp_nonce_field(self::SAVE_HERO_ACTION); ?>
                        <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(240px,1fr));gap:24px;max-width:900px">
                            <?php foreach ([
                                ['key' => 'desktop', 'label' => 'Imagen para computadoras', 'description' => 'Foto horizontal. Se muestra a la derecha del texto.', 'id' => absint($active_campaign['desktop_image_id'] ?? 0)],
                                ['key' => 'mobile', 'label' => 'Imagen para celulares (opcional)', 'description' => 'Si no elegís otra, se utiliza la misma imagen de escritorio.', 'id' => absint($active_campaign['mobile_image_id'] ?? 0)],
                            ] as $field) :
                                $url = $field['id'] ? wp_get_attachment_image_url($field['id'], 'medium_large') : '';
                            ?>
                                <div class="flacso-home-hero-image-field" style="background:#fff;border:1px solid #dcdcde;border-radius:10px;padding:18px">
                                    <h3 style="margin-top:0"><?php echo esc_html($field['label']); ?></h3>
                                    <p><?php echo esc_html($field['description']); ?></p>
                                    <div class="flacso-home-hero-image-preview" style="aspect-ratio:16/10;display:grid;place-items:center;background:#f1f5f9;border-radius:8px;overflow:hidden;margin-bottom:12px">
                                        <img src="<?php echo esc_url($url); ?>" alt="" style="width:100%;height:100%;object-fit:cover;<?php echo $url ? '' : 'display:none;'; ?>">
                                        <span <?php echo $url ? 'hidden' : ''; ?>>Sin imagen seleccionada</span>
                                    </div>
                                    <input type="hidden" name="flacso_hero_image_<?php echo esc_attr($field['key']); ?>" value="<?php echo esc_attr($field['id']); ?>">
                                    <button type="button" class="button button-secondary flacso-home-hero-pick">Elegir o cambiar imagen</button>
                                    <button type="button" class="button-link-delete flacso-home-hero-remove" style="margin-left:12px">Quitar</button>
                                </div>
                            <?php endforeach; ?>
                        </div>
                        <?php submit_button('Guardar imágenes de la portada'); ?>
                    </form>
                    <p><a href="<?php echo esc_url(get_edit_post_link($active_id)); ?>">Editar título, botón y fechas de esta portada</a> · <a href="<?php echo esc_url(home_url('/')); ?>" target="_blank" rel="noopener noreferrer">Ver cómo quedó la portada ↗</a></p>
                <?php else : ?>
                    <p><strong>La portada está mostrando la configuración institucional predeterminada.</strong> Para cambiar la foto desde este panel, primero necesitás una campaña publicada y vigente.</p>
                    <p><a class="button button-primary" href="<?php echo esc_url(admin_url('post-new.php?post_type=flacso_home_campaign')); ?>">Crear portada editable</a></p>
                <?php endif; ?>
            </section>
            <script>
            (function () {
                document.querySelectorAll('.flacso-home-hero-image-field').forEach(function (field) {
                    var input = field.querySelector('input[type="hidden"]');
                    var img = field.querySelector('img');
                    var empty = field.querySelector('span');
                    var pick = field.querySelector('.flacso-home-hero-pick');
                    var remove = field.querySelector('.flacso-home-hero-remove');
                    if (!input || !pick || !window.wp || !wp.media) return;
                    function preview(url) {
                        img.src = url || '';
                        img.style.display = url ? 'block' : 'none';
                        empty.hidden = !!url;
                    }
                    pick.addEventListener('click', function () {
                        var frame = wp.media({title:'Elegir fotografía para la portada',button:{text:'Usar esta imagen'},library:{type:'image'},multiple:false});
                        frame.on('select', function () {
                            var item = frame.state().get('selection').first().toJSON();
                            input.value = item.id;
                            preview(item.sizes && item.sizes.medium_large ? item.sizes.medium_large.url : item.url);
                        });
                        frame.open();
                    });
                    remove.addEventListener('click', function () { input.value = '0'; preview(''); });
                });
            }());
            </script>

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
                    <p><?php esc_html_e('Editá el destino de cada categoría. Si dejás un enlace vacío, la portada usa la página predeterminada de esa categoría. Los títulos y el orden siguen el diseño aprobado.', 'flacso-main-page'); ?></p>
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

    public static function save_hero_images(): void {
        if (!current_user_can('manage_options')) {
            wp_die(esc_html__('No tenés permiso para editar la portada.', 'flacso-main-page'));
        }
        check_admin_referer(self::SAVE_HERO_ACTION);
        $active = FLACSO_Home_Campaign::active();
        $posted_id = isset($_POST['flacso_active_campaign_id']) ? absint($_POST['flacso_active_campaign_id']) : 0;
        if (!$posted_id || $posted_id !== (int) ($active['id'] ?? 0) || !current_user_can('edit_post', $posted_id)) {
            self::redirect('campaign_changed');
        }
        $values = [];
        foreach (['desktop' => FLACSO_Home_Campaign::META_DESKTOP_IMAGE, 'mobile' => FLACSO_Home_Campaign::META_MOBILE_IMAGE] as $key => $meta_key) {
            $input = 'flacso_hero_image_' . $key;
            $id = isset($_POST[$input]) ? absint($_POST[$input]) : 0;
            if ($id && !wp_attachment_is_image($id)) {
                self::redirect('invalid_image');
            }
            $values[$meta_key] = $id;
        }
        foreach ($values as $meta_key => $id) {
            update_post_meta($posted_id, $meta_key, $id);
        }
        self::redirect('images_saved');
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
