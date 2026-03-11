<?php

if (!defined('ABSPATH')) {
    exit;
}

class Flacso_Main_Page_Admin {
    private const SECTION_PAGES = [
        'hero' => [
            'slug' => 'flacso-main-page',
            'menu_title' => 'Encabezado',
            'page_title' => 'Encabezado',
        ],
        'eventos' => [
            'slug' => 'flacso-main-page-eventos',
            'menu_title' => 'Eventos',
            'page_title' => 'Eventos',
        ],
        'secciones' => [
            'slug' => 'flacso-main-page-secciones',
            'menu_title' => 'Secciones',
            'page_title' => 'Secciones',
        ],
        'novedades' => [
            'slug' => 'flacso-main-page-novedades',
            'menu_title' => 'Novedades',
            'page_title' => 'Novedades',
        ],
        'posgrados' => [
            'slug' => 'flacso-main-page-posgrados',
            'menu_title' => 'Posgrados',
            'page_title' => 'Posgrados',
        ],
        'congreso' => [
            'slug' => 'flacso-main-page-congreso',
            'menu_title' => 'Congreso',
            'page_title' => 'Congreso',
        ],
        'quienes' => [
            'slug' => 'flacso-main-page-quienes',
            'menu_title' => 'Quiénes somos',
            'page_title' => 'Quiénes somos',
        ],
        'contacto' => [
            'slug' => 'flacso-main-page-contacto',
            'menu_title' => 'Contacto',
            'page_title' => 'Contacto',
        ],
    ];

    public static function get_admin_page_slugs(): array {
        return array_column(self::SECTION_PAGES, 'slug');
    }

    public static function register_admin_bar_menu(WP_Admin_Bar $wp_admin_bar): void {
        if (!is_admin_bar_showing() || !current_user_can('manage_options')) {
            return;
        }

        $wp_admin_bar->add_node([
            'id'    => 'flacso-main-page-bar',
            'title' => __('Gestor FLACSO', 'flacso-main-page'),
            'href'  => admin_url('admin.php?page=flacso-main-page'),
            'meta'  => ['title' => __('Gestor FLACSO', 'flacso-main-page')],
        ]);
    }
    public static function init(): void {
        add_action('admin_menu', [__CLASS__, 'register_menu']);
        add_action('admin_bar_menu', [__CLASS__, 'register_admin_bar_menu'], 100);
    }

    public static function register_menu(): void {
        add_menu_page(
            __('Gestor FLACSO', 'flacso-main-page'),
            __('Gestor FLACSO', 'flacso-main-page'),
            'manage_options',
            'flacso-main-page',
            [__CLASS__, 'render_section_page'],
            'dashicons-layout',
            58
        );

        // Mantener solo el item principal: la interfaz unificada muestra todas las secciones.
        remove_submenu_page('flacso-main-page', 'flacso-main-page');
        
        // Agregar página separada para Oferta Académica
        add_submenu_page(
            'flacso-main-page',
            __('Oferta Académica', 'flacso-main-page'),
            __('Oferta Académica', 'flacso-main-page'),
            'manage_options',
            'flacso-main-page-oferta-academica',
            [__CLASS__, 'render_oferta_academica_page']
        );
    }

    public static function render_section_page(): void {
        // Usar la nueva interfaz unificada
        Flacso_Main_Page_Unified_Settings::render_unified_page();
    }

    // Mantener el método legacy para compatibilidad
    public static function render_section_page_legacy(): void {
        if (!current_user_can('manage_options')) {
            return;
        }

        if (function_exists('wp_enqueue_editor')) {
            wp_enqueue_editor();
        }
        if (function_exists('wp_enqueue_media')) {
            wp_enqueue_media();
        }

        self::maybe_save_settings();

        settings_errors('flacso-main-page_messages');
        $settings = Flacso_Main_Page_Settings::get_settings();
        $cards = $settings['posgrados']['cards'] ?? [];
        $page_slug = $_GET['page'] ?? 'flacso-main-page';
        $is_dashboard_page = $page_slug === 'flacso-main-page';
        $active_section = self::get_section_from_page($page_slug);
        ?>
        <div class="wrap">
            <header class="flacso-global-header">
                <h1><?php esc_html_e('Gestor FLACSO', 'flacso-main-page'); ?></h1>
                <p class="description">
                    <?php esc_html_e('Gestiona los modulos del home, las paginas institucionales y los bloques del sitio desde un unico panel.', 'flacso-main-page'); ?>
                </p>
            </header>
            <div class="flacso-admin-ui-bar" data-admin-ui-bar>
                <div class="flacso-admin-ui-bar__primary">
                    <nav class="flacso-admin-tabs" role="tablist" aria-label="<?php esc_attr_e('Secciones del gestor FLACSO','flacso-main-page');?>">
                        <?php foreach (self::SECTION_PAGES as $key => $cfg): $is_active = ($active_section === $key); ?>
                            <button type="button"
                                    class="flacso-admin-tab <?php echo $is_active ? 'is-active' : ''; ?>"
                                    role="tab"
                                    data-tab-target="#flacso-panel-<?php echo esc_attr($key); ?>"
                                    aria-selected="<?php echo $is_active ? 'true':'false'; ?>"
                                    tabindex="<?php echo $is_active ? '0':'-1'; ?>">
                                <?php echo esc_html($cfg['menu_title']); ?>
                            </button>
                        <?php endforeach; ?>
                    </nav>
                    <div class="flacso-admin-quickfilter">
                        <label for="flacso-admin-filter" class="screen-reader-text"><?php esc_html_e('Filtrar campos','flacso-main-page');?></label>
                        <input id="flacso-admin-filter" type="search" placeholder="<?php esc_attr_e('Filtrar ajustes…','flacso-main-page');?>" data-admin-filter>
                    </div>
                </div>
            </div>
            <?php if ($is_dashboard_page) : ?>
                <?php self::render_global_overview_cards(); ?>
            <?php endif; ?>
            <form method="post">
                <?php wp_nonce_field('flacso-main-page_save', 'flacso-main-page_nonce'); ?>

                <?php if ($active_section === 'hero') : ?>
                <div id="flacso-panel-hero" class="flacso-settings-panel" data-panel-section="hero">
                    <h2><?php esc_html_e('Hero principal', 'flacso-main-page'); ?></h2>
                    <?php self::text_input('Flacso_Main_Page_Settings[hero][title]', $settings['hero']['title'], __('Título', 'flacso-main-page')); ?>
                    <?php self::text_input('Flacso_Main_Page_Settings[hero][subtitle]', $settings['hero']['subtitle'], __('Subtítulo', 'flacso-main-page')); ?>
                    <?php self::image_input_with_preview('Flacso_Main_Page_Settings[hero][background_image]', $settings['hero']['background_image'], __('Imagen de fondo (URL)', 'flacso-main-page')); ?>
                    <h3><?php esc_html_e('Botones principales (hasta 4)', 'flacso-main-page'); ?></h3>
                    <p>
                        <label>
                            <input type="checkbox" name="Flacso_Main_Page_Settings[hero][show_buttons]" value="1" <?php checked(!empty($settings['hero']['show_buttons'] ?? true)); ?>>
                            <?php esc_html_e('Mostrar los botones del hero en todos los dispositivos (desmarque para ocultarlos).', 'flacso-main-page'); ?>
                        </label>
                    </p>
                    <p class="description"><?php esc_html_e('Configure hasta cuatro botones principales para el hero. Puede activar o desactivar cada uno y definir su estilo visual.', 'flacso-main-page'); ?></p>
                    <?php
                    $hero_button_defaults = Flacso_Main_Page_Settings::get_hero_button_defaults();
                    $hero_buttons = $settings['hero']['buttons'] ?? $hero_button_defaults;
                    $style_options = Flacso_Main_Page_Settings::get_button_style_options();
                    foreach ($hero_button_defaults as $index => $button_default) :
                        $button = $hero_buttons[$index] ?? $button_default;
                    ?>
                        <fieldset class="flacso-hero-button">
                            <legend><?php printf(esc_html__('Botón %s', 'flacso-main-page'), $index + 1); ?></legend>
                            <p>
                                <label>
                                    <input type="checkbox" name="Flacso_Main_Page_Settings[hero][buttons][<?php echo esc_attr($index); ?>][enabled]" value="1" <?php checked(!empty($button['enabled'])); ?>>
                                    <?php esc_html_e('Mostrar este botón', 'flacso-main-page'); ?>
                                </label>
                            </p>
                            <p>
                                <label>
                                    <span><?php esc_html_e('Texto del botón', 'flacso-main-page'); ?></span><br>
                                    <input type="text" class="regular-text" name="Flacso_Main_Page_Settings[hero][buttons][<?php echo esc_attr($index); ?>][label]" value="<?php echo esc_attr($button['label']); ?>">
                                </label>
                            </p>
                            <?php self::url_input_with_preview('Flacso_Main_Page_Settings[hero][buttons][<?php echo esc_attr($index); ?>][url]', $button['url'], __('URL del botón', 'flacso-main-page'), 'https://'); ?>
                            <p>
                                <label>
                                    <span><?php esc_html_e('Estilo', 'flacso-main-page'); ?></span><br>
                                    <select name="Flacso_Main_Page_Settings[hero][buttons][<?php echo esc_attr($index); ?>][style]">
                                        <?php foreach ($style_options as $value => $label) : ?>
                                            <option value="<?php echo esc_attr($value); ?>" <?php selected($button['style'], $value); ?>><?php echo esc_html($label); ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </label>
                            </p>
                        </fieldset>
                    <?php endforeach; ?>
                    <?php self::render_hero_preview($hero_buttons, !empty($settings['hero']['show_buttons'] ?? true)); ?>
                    <h3><?php esc_html_e('Botones flotantes', 'flacso-main-page'); ?></h3>
                    <?php self::text_input('Flacso_Main_Page_Settings[hero][bubble_primary_label]', $settings['hero']['bubble_primary_label'] ?? '', __('Etiqueta burbuja 1', 'flacso-main-page')); ?>
                    <?php self::url_input_with_preview('Flacso_Main_Page_Settings[hero][bubble_primary_url]', $settings['hero']['bubble_primary_url'] ?? '', __('URL burbuja 1', 'flacso-main-page'), 'https://'); ?>
                    <?php self::select_input('Flacso_Main_Page_Settings[hero][bubble_primary_style]', $settings['hero']['bubble_primary_style'] ?? 'primary', __('Estilo burbuja 1', 'flacso-main-page'), $style_options); ?>
                    <?php $bubble_primary_enabled = isset($settings['hero']['bubble_primary_enabled']) ? (bool) $settings['hero']['bubble_primary_enabled'] : true; ?>
                    <p>
                        <label>
                            <input type="checkbox" name="Flacso_Main_Page_Settings[hero][bubble_primary_enabled]" value="1" <?php checked($bubble_primary_enabled); ?>>
                            <?php esc_html_e('Mostrar burbuja 1 en los botones flotantes', 'flacso-main-page'); ?>
                        </label>
                    </p>
                    <?php self::text_input('Flacso_Main_Page_Settings[hero][bubble_secondary_label]', $settings['hero']['bubble_secondary_label'] ?? '', __('Etiqueta burbuja 2', 'flacso-main-page')); ?>
                    <?php self::url_input_with_preview('Flacso_Main_Page_Settings[hero][bubble_secondary_url]', $settings['hero']['bubble_secondary_url'] ?? '', __('URL burbuja 2', 'flacso-main-page'), 'https://'); ?>
                    <?php self::select_input('Flacso_Main_Page_Settings[hero][bubble_secondary_style]', $settings['hero']['bubble_secondary_style'] ?? 'outline', __('Estilo burbuja 2', 'flacso-main-page'), $style_options); ?>
                    <?php $bubble_secondary_enabled = isset($settings['hero']['bubble_secondary_enabled']) ? (bool) $settings['hero']['bubble_secondary_enabled'] : true; ?>
                    <p>
                        <label>
                            <input type="checkbox" name="Flacso_Main_Page_Settings[hero][bubble_secondary_enabled]" value="1" <?php checked($bubble_secondary_enabled); ?>>
                            <?php esc_html_e('Mostrar burbuja 2 en los botones flotantes', 'flacso-main-page'); ?>
                        </label>
                    </p>
                </div>
                <?php endif; ?>

                <?php if ($active_section === 'eventos') : ?>
                <div id="flacso-panel-eventos" class="flacso-settings-panel" data-panel-section="eventos">
                    <h2><?php esc_html_e('Eventos', 'flacso-main-page'); ?></h2>
                    <?php self::render_eventos_section(); ?>
                </div>
                <?php endif; ?>

                <?php if ($active_section === 'secciones') : ?>
                <div id="flacso-panel-secciones" class="flacso-settings-panel" data-panel-section="secciones">
                    <h2><?php esc_html_e('Visibilidad de secciones', 'flacso-main-page'); ?></h2>
                    <p><?php esc_html_e('Activa únicamente las secciones que deben mostrarse en la página principal.', 'flacso-main-page'); ?></p>
                    <?php $sections_visibility = Flacso_Main_Page_Settings::get_section_visibility(); ?>
                    <?php foreach (Flacso_Main_Page_Settings::get_section_visibility_defaults() as $section_key => $default): ?>
                        <p>
                            <label>
                                <input type="checkbox"
                                       name="<?php echo esc_attr('Flacso_Main_Page_Settings[sections_visibility][' . $section_key . ']'); ?>"
                                       value="1"
                                       <?php checked(!empty($sections_visibility[$section_key])); ?>>
                                <span><?php echo esc_html(Flacso_Main_Page_Settings::get_section_label($section_key)); ?></span>
                            </label>
                        </p>
                    <?php endforeach; ?>
                    <?php $section_order = Flacso_Main_Page_Settings::get_homepage_section_order(); ?>
                    <h3><?php esc_html_e('Orden de las secciones principales', 'flacso-main-page'); ?></h3>
                    <p class="description">
                        <?php esc_html_e('Reordena el recorrido de la página principal usando las flechas para mover cada bloque arriba o abajo.', 'flacso-main-page'); ?>
                    </p>
                    <ul class="flacso-section-order" data-section-order-list>
                        <?php foreach ($section_order as $section_key) : ?>
                            <li class="flacso-section-order-item">
                                <span class="flacso-section-order-label">
                                    <?php echo esc_html(Flacso_Main_Page_Settings::get_section_label($section_key)); ?>
                                </span>
                                <div class="flacso-section-order-actions">
                                    <button type="button" class="flacso-section-order-action" data-order-action="up" aria-label="<?php esc_attr_e('Mover arriba', 'flacso-main-page'); ?>">
                                        &uarr;
                                    </button>
                                    <button type="button" class="flacso-section-order-action" data-order-action="down" aria-label="<?php esc_attr_e('Mover abajo', 'flacso-main-page'); ?>">
                                        &darr;
                                    </button>
                                </div>
                                <input type="hidden" name="Flacso_Main_Page_Settings[sections_order][]" value="<?php echo esc_attr($section_key); ?>">
                            </li>
                    <?php endforeach; ?>
                </ul>
                <p class="description">
                    <?php esc_html_e('Elige el color que deben firmar los encabezados de cada sección (el hero mantiene su configuración propia).', 'flacso-main-page'); ?>
                </p>
                <?php $heading_colors = Flacso_Main_Page_Settings::get_section_heading_colors(); ?>
                <div class="flacso-heading-color-grid">
                    <?php foreach (Flacso_Main_Page_Settings::get_homepage_section_order_defaults() as $section_key) :
                        if ($section_key === 'hero') {
                            continue;
                        }
                        $label = Flacso_Main_Page_Settings::get_section_label($section_key);
                        $current_choice = $heading_colors[$section_key] ?? Flacso_Main_Page_Settings::get_section_heading_color_choice($section_key);
                        ?>
                        <label class="flacso-heading-color-option">
                            <span><?php echo esc_html($label); ?></span>
                            <select name="Flacso_Main_Page_Settings[section_heading_colors][<?php echo esc_attr($section_key); ?>]">
                                <option value="primary" <?php selected($current_choice, 'primary'); ?>><?php esc_html_e('Color institucional', 'flacso-main-page'); ?></option>
                                <option value="palette7" <?php selected($current_choice, 'palette7'); ?>><?php esc_html_e('Blanco (palette7)', 'flacso-main-page'); ?></option>
                            </select>
                        </label>
                    <?php endforeach; ?>
                </div>
            </div>
            <?php endif; ?>

                <?php if ($active_section === 'novedades') : ?>
                <div id="flacso-panel-novedades" class="flacso-settings-panel" data-panel-section="novedades">
                    <h2><?php esc_html_e('Novedades', 'flacso-main-page'); ?></h2>
                    <?php self::number_input(
                        'Flacso_Main_Page_Settings[novedades][per_page]',
                        $settings['novedades']['per_page'] ?? 12,
                        __('Cantidad de novedades por página', 'flacso-main-page'),
                        ['min' => 3, 'max' => 48]
                    ); ?>
                    <p class="description">
                        <?php esc_html_e('Define cuántas novedades se muestran en cada página del listado principal.', 'flacso-main-page'); ?>
                    </p>
                    <h3><?php esc_html_e('Destacados y búsquedas', 'flacso-main-page'); ?></h3>
                    <p class="description">
                        <?php esc_html_e('Busca publicaciones de la categoría Novedades para destacarlas y verlas primero en la página.', 'flacso-main-page'); ?>
                    </p>
                    <?php
                    $sticky_admin = flacso_section_novedades_admin_menu_render();
                    if ($sticky_admin) {
                        echo $sticky_admin;
                    } else {
                        echo '<p>' . esc_html__('No hay noticias disponibles para administrar.', 'flacso-main-page') . '</p>';
                    }
                    ?>
                </div>
                <?php endif; ?>

                <?php if ($active_section === 'posgrados') : ?>
                <div id="flacso-panel-posgrados" class="flacso-settings-panel" data-panel-section="posgrados">
                    <h2><?php esc_html_e('Sección Posgrados', 'flacso-main-page'); ?></h2>
                    <p>
                        <label>
                            <input type="checkbox" name="Flacso_Main_Page_Settings[posgrados][show_title]" value="1" <?php checked(!empty($settings['posgrados']['show_title'])); ?>>
                            <?php esc_html_e('Mostrar título de sección', 'flacso-main-page'); ?>
                        </label>
                    </p>
                    <?php self::text_input('Flacso_Main_Page_Settings[posgrados][title]', $settings['posgrados']['title'], __('Título', 'flacso-main-page')); ?>
                    <?php self::rich_text_editor('Flacso_Main_Page_Settings[posgrados][intro]', $settings['posgrados']['intro'], __('Descripción', 'flacso-main-page')); ?>

                    <h3><?php esc_html_e('Tarjetas', 'flacso-main-page'); ?></h3>
                    <p class="description"><?php esc_html_e('Actualiza la información de cada tipo de programa.', 'flacso-main-page'); ?></p>
                    <?php foreach ($cards as $index => $card) : ?>
                        <div class="flacso-card-block">
                            <h4><?php echo esc_html($card['title']); ?></h4>
                            <?php self::text_input("Flacso_Main_Page_Settings[posgrados][cards][$index][title]", $card['title'], __('Título visible', 'flacso-main-page')); ?>
                            <?php self::text_input("Flacso_Main_Page_Settings[posgrados][cards][$index][type]", $card['type'], __('Etiqueta', 'flacso-main-page')); ?>
                            <?php self::url_input_with_preview("Flacso_Main_Page_Settings[posgrados][cards][$index][url]", $card['url'], __('URL', 'flacso-main-page'), 'https://'); ?>
                            <?php self::image_input_with_preview("Flacso_Main_Page_Settings[posgrados][cards][$index][image]", $card['image'], __('Imagen destacada (URL)', 'flacso-main-page')); ?>
                            <?php self::textarea_input("Flacso_Main_Page_Settings[posgrados][cards][$index][desc]", $card['desc'], __('Descripción', 'flacso-main-page')); ?>
                        </div>
                    <?php endforeach; ?>
                </div>
                <?php endif; ?>

                <?php if ($active_section === 'congreso') : ?>
                <div id="flacso-panel-congreso" class="flacso-settings-panel" data-panel-section="congreso">
                    <h2><?php esc_html_e('Sección Congreso', 'flacso-main-page'); ?></h2>
                    <?php self::text_input('Flacso_Main_Page_Settings[congreso][title]', $settings['congreso']['title'], __('Título', 'flacso-main-page')); ?>
                    <?php self::rich_text_editor('Flacso_Main_Page_Settings[congreso][content]', $settings['congreso']['content'], __('Contenido', 'flacso-main-page')); ?>
                    <?php self::text_input('Flacso_Main_Page_Settings[congreso][cta_label]', $settings['congreso']['cta_label'], __('Texto del botón', 'flacso-main-page')); ?>
                    <?php self::url_input_with_preview('Flacso_Main_Page_Settings[congreso][cta_url]', $settings['congreso']['cta_url'], __('URL del botón', 'flacso-main-page'), 'https://'); ?>
                    <?php self::image_input_with_preview('Flacso_Main_Page_Settings[congreso][background_image]', $settings['congreso']['background_image'] ?? '', __('Imagen de fondo (URL)', 'flacso-main-page')); ?>
                </div>
                <?php endif; ?>

                <?php if ($active_section === 'quienes') : ?>
                <div id="flacso-panel-quienes" class="flacso-settings-panel" data-panel-section="quienes">
                    <h2><?php esc_html_e('Sección ¿Quiénes somos?', 'flacso-main-page'); ?></h2>
                    <?php self::text_input('Flacso_Main_Page_Settings[quienes][title]', $settings['quienes']['title'], __('Título', 'flacso-main-page')); ?>
                    <?php self::rich_text_editor('Flacso_Main_Page_Settings[quienes][content]', $settings['quienes']['content'], __('Contenido', 'flacso-main-page')); ?>
                    <?php self::text_input('Flacso_Main_Page_Settings[quienes][cta_label]', $settings['quienes']['cta_label'], __('Texto del botón', 'flacso-main-page')); ?>
                    <?php self::url_input_with_preview('Flacso_Main_Page_Settings[quienes][cta_url]', $settings['quienes']['cta_url'], __('URL del botón', 'flacso-main-page'), 'https://'); ?>
                    <?php self::image_input_with_preview('Flacso_Main_Page_Settings[quienes][background_image]', $settings['quienes']['background_image'], __('Imagen de fondo (URL)', 'flacso-main-page')); ?>
                    <?php self::text_input('Flacso_Main_Page_Settings[quienes][highlight_color]', $settings['quienes']['highlight_color'], __('Color destacado (hex)', 'flacso-main-page')); ?>
                </div>
                <?php endif; ?>

                <?php if ($active_section === 'contacto') : ?>
                <div id="flacso-panel-contacto" class="flacso-settings-panel" data-panel-section="contacto">
                    <h2><?php esc_html_e('Secci??n Contacto', 'flacso-main-page'); ?></h2>
                    <?php $contact_settings = $settings['contacto']; ?>
                    <?php self::text_input('Flacso_Main_Page_Settings[contacto][title]', $contact_settings['title'], __('T??tulo', 'flacso-main-page')); ?>
                    <?php self::text_input('Flacso_Main_Page_Settings[contacto][subtitle]', $contact_settings['subtitle'], __('Subt??tulo', 'flacso-main-page')); ?>
                    <?php self::text_input('Flacso_Main_Page_Settings[contacto][cta_label]', $contact_settings['cta_label'], __('Texto del bot??n', 'flacso-main-page')); ?>
                    <?php self::url_input_with_preview('Flacso_Main_Page_Settings[contacto][cta_url]', $contact_settings['cta_url'], __('URL del bot??n', 'flacso-main-page'), 'https://'); ?>
                    <?php
                    $background_mode = $contact_settings['background_mode'] ?? 'color';
                    $background_mode_options = [
                        'color' => __('Solo color', 'flacso-main-page'),
                        'gradient' => __('Gradiente', 'flacso-main-page'),
                        'image' => __('Imagen sin filtro', 'flacso-main-page'),
                        'image_overlay' => __('Imagen con filtro', 'flacso-main-page'),
                    ];
                    ?>
                    <div class="flacso-contacto-background-card" data-background-panel>
                        <h3><?php esc_html_e('Fondo y estilo visual', 'flacso-main-page'); ?></h3>
                        <p class="description">
                            <?php esc_html_e('Define si el bloque usar?? un color plano, un gradiente o una imagen (con o sin filtro). Cada modo habilita sus propios controles.', 'flacso-main-page'); ?>
                        </div>
                        <script>
                        (function(){
                            const tabs = document.querySelectorAll('.flacso-admin-tab');
                            const panels = document.querySelectorAll('.flacso-settings-panel');
                            const filterInput = document.querySelector('[data-admin-filter]');
                            
                            function activate(tab){
                                tabs.forEach(t=>{
                                    const isActive = t===tab; 
                                    t.classList.toggle('is-active',isActive); 
                                    t.setAttribute('aria-selected',isActive? 'true':'false'); 
                                    t.tabIndex=isActive?0:-1;
                                });
                                const target = tab.getAttribute('data-tab-target');
                                panels.forEach(p=>{
                                    const shouldShow = ('#' + p.id === target);
                                    p.style.display = shouldShow ? 'block':'none';
                                });
                            }
                            
                            tabs.forEach(t=>{t.addEventListener('click',()=>activate(t));});
                            
                            // Activar el tab correcto al cargar
                            const current = document.querySelector('.flacso-admin-tab.is-active');
                            if(current){
                                activate(current);
                            } else if(tabs.length){
                                activate(tabs[0]);
                            }
                            
                            // Filtrado
                            if(filterInput){
                                filterInput.addEventListener('input',()=>{
                                    const q = filterInput.value.toLowerCase().trim();
                                    panels.forEach(panel=>{
                                        if(!q){
                                            panel.classList.remove('is-filter-hidden'); 
                                            return;
                                        }
                                        const text = panel.textContent.toLowerCase();
                                        if(text.includes(q)){
                                            panel.classList.remove('is-filter-hidden');
                                        } else {
                                            panel.classList.add('is-filter-hidden');
                                        }
                                    });
                                });
                            }
                        })();
                        </script>
                        <style>
                            .flacso-admin-ui-bar{margin-bottom:1.2rem;display:flex;flex-direction:column;gap:.75rem;}
                            .flacso-admin-tabs{display:flex;flex-wrap:wrap;gap:.4rem;}
                            .flacso-admin-tab{background:#fff;border:1px solid #d0d7e2;border-radius:8px;padding:.55rem .9rem;font-size:.85rem;font-weight:600;cursor:pointer;line-height:1.2;display:inline-flex;align-items:center;gap:.4rem;transition:background .2s,border .2s,box-shadow .2s;}
                            .flacso-admin-tab.is-active{background:#1d3a72;color:#fff;border-color:#1d3a72;box-shadow:0 4px 14px rgba(29,58,114,.35);}
                            .flacso-admin-tab:focus-visible{outline:2px solid #1d3a72;outline-offset:2px;}
                            .flacso-admin-quickfilter input{border:1px solid #c7ced8;border-radius:8px;padding:.55rem .75rem;font-size:.85rem;width:240px;max-width:100%;}
                            @media (max-width:800px){.flacso-admin-quickfilter input{width:100%;}}
                            .flacso-settings-panel{background:#fff;border:1px solid #ccd0d4;padding:24px 26px;margin-top:0;border-radius:14px;box-shadow:0 10px 28px rgba(15,26,45,.06);animation:fadeIn .25s ease;}
                            .flacso-settings-panel + .flacso-settings-panel{margin-top:1.4rem;}
                            .flacso-settings-panel h2{margin-top:0;margin-bottom:.85rem;font-size:1.25rem;}
                            .flacso-settings-panel.is-filter-hidden{display:none!important;}
                            .flacso-card-block{border:1px solid #e2e4e7;padding:15px;margin-bottom:16px;border-radius:10px;background:#f8fafc;}
                            .flacso-hero-button{background:#fdfdfd;}
                            .flacso-hero-preview{background:#f1f4ff;border-radius:18px;}
                            .flacso-url-input,.flacso-image-input{background:#f9fbfd;padding:.75rem .85rem;border:1px solid #e3e7ee;border-radius:12px;margin:1rem 0;}
                            .flacso-image-preview img{border-radius:10px;}
                            .flacso-section-order{list-style:none;margin:0;padding:0;display:flex;flex-direction:column;gap:.5rem;}
                            .flacso-section-order-item{background:#f9fbfd;border:1px solid #d9dee5;border-radius:10px;padding:.65rem .75rem;display:flex;align-items:center;justify-content:space-between;gap:.75rem;}
                            .flacso-section-order-actions{display:flex;gap:.4rem;}
                            .flacso-section-order-action{background:#fff;border:1px solid #cbd5e1;border-radius:6px;cursor:pointer;width:34px;height:34px;display:inline-flex;align-items:center;justify-content:center;font-weight:700;transition:background .2s,border .2s;}
                            .flacso-section-order-action:hover,.flacso-section-order-action:focus-visible{background:#1d3a72;color:#fff;border-color:#1d3a72;}
                            @keyframes fadeIn{from{opacity:0;transform:translateY(6px);}to{opacity:1;transform:none;}}
                        </style>
                            <label>
                                <span><?php esc_html_e('Modo de fondo', 'flacso-main-page'); ?></span><br>
                                <select name="Flacso_Main_Page_Settings[contacto][background_mode]" data-background-mode-selector>
                                    <?php foreach ($background_mode_options as $value => $label) : ?>
                                        <option value="<?php echo esc_attr($value); ?>" <?php selected($background_mode, $value); ?>>
                                            <?php echo esc_html($label); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </label>
                        </p>
                        <?php self::color_input('Flacso_Main_Page_Settings[contacto][background_color]', $contact_settings['background_color'] ?? '#f2f6ff', __('Color base o de respaldo', 'flacso-main-page')); ?>
                        <div class="flacso-contacto-background-group" data-background-modes="gradient">
                            <?php self::color_input('Flacso_Main_Page_Settings[contacto][background_gradient_start]', $contact_settings['background_gradient_start'] ?? '#0f1a2d', __('Color inicial del gradiente', 'flacso-main-page')); ?>
                            <?php self::color_input('Flacso_Main_Page_Settings[contacto][background_gradient_end]', $contact_settings['background_gradient_end'] ?? '#1d3a72', __('Color final del gradiente', 'flacso-main-page')); ?>
                            <?php self::number_input('Flacso_Main_Page_Settings[contacto][background_gradient_angle]', $contact_settings['background_gradient_angle'] ?? 135, __('?ngulo del gradiente (0? a 360?)', 'flacso-main-page'), ['min' => 0, 'max' => 360, 'step' => 1]); ?>
                        </div>
                        <div class="flacso-contacto-background-group" data-background-modes="image,image_overlay">
                            <?php self::image_input_with_preview('Flacso_Main_Page_Settings[contacto][background_image]', $contact_settings['background_image'] ?? '', __('Imagen de fondo', 'flacso-main-page')); ?>
                        </div>
                        <div class="flacso-contacto-background-group" data-background-modes="image_overlay">
                            <?php
                            $overlay_style = $contact_settings['background_overlay_style'] ?? 'solid';
                            $overlay_style_options = [
                                'solid' => __('Filtro s?lido', 'flacso-main-page'),
                                'gradient' => __('Filtro degradado', 'flacso-main-page'),
                            ];
                            ?>
                            <?php self::select_input('Flacso_Main_Page_Settings[contacto][background_overlay_style]', $overlay_style, __('Tipo de filtro', 'flacso-main-page'), $overlay_style_options); ?>
                            <?php self::color_input('Flacso_Main_Page_Settings[contacto][background_overlay_color]', $contact_settings['background_overlay_color'] ?? '#0f1a2d', __('Color del filtro (inicio)', 'flacso-main-page')); ?>
                            <?php self::number_input('Flacso_Main_Page_Settings[contacto][background_overlay_opacity]', $contact_settings['background_overlay_opacity'] ?? 0.78, __('Opacidad inicial (0 a 1)', 'flacso-main-page'), ['min' => 0, 'max' => 1, 'step' => 0.05]); ?>
                            <?php self::color_input('Flacso_Main_Page_Settings[contacto][background_overlay_color_secondary]', $contact_settings['background_overlay_color_secondary'] ?? '#0f1a2d', __('Color del filtro (final)', 'flacso-main-page')); ?>
                            <?php self::number_input('Flacso_Main_Page_Settings[contacto][background_overlay_opacity_secondary]', $contact_settings['background_overlay_opacity_secondary'] ?? 0.45, __('Opacidad final (0 a 1)', 'flacso-main-page'), ['min' => 0, 'max' => 1, 'step' => 0.05]); ?>
                            <?php self::number_input('Flacso_Main_Page_Settings[contacto][background_overlay_angle]', $contact_settings['background_overlay_angle'] ?? 180, __('?ngulo del filtro (0? a 360?)', 'flacso-main-page'), ['min' => 0, 'max' => 360, 'step' => 1]); ?>
                        </div>
                    </div>
                </div>
                <?php endif; ?>


                <?php submit_button(__('Guardar ajustes', 'flacso-main-page')); ?>
            </form>
        </div>
        <style>
            .flacso-settings-panel {
                background: #fff;
                border: 1px solid #ccd0d4;
                padding: 20px;
                margin-top: 20px;
                border-radius: 4px;
            }
            .flacso-settings-panel h3 {
                margin-top: 30px;
            }
            .flacso-settings-panel .regular-text,
            .flacso-settings-panel textarea {
                width: 100%;
                max-width: 640px;
            }
            .flacso-card-block {
                border: 1px solid #e2e4e7;
                padding: 15px;
                margin-bottom: 20px;
                border-radius: 4px;
                background: #f9f9f9;
            }
            .flacso-novedades-admin {
                width: 100%;
            }
            .flacso-novedades-admin-panel {
                border-radius: 12px;
                border: 1px solid rgba(29, 58, 114, 0.15);
                padding: 16px;
                background: #fff;
                box-shadow: 0 10px 25px rgba(0, 0, 0, 0.08);
                margin-top: 12px;
            }
            .flacso-novedades-admin-panel summary {
                font-weight: 800;
                cursor: pointer;
                display: flex;
                align-items: center;
                justify-content: space-between;
                gap: 0.6rem;
                padding: 0;
            }
            .flacso-novedades-admin-panel summary::-webkit-details-marker {
                display: none;
            }
            .flacso-novedades-admin-list {
                margin-top: 0.75rem;
                display: flex;
                flex-direction: column;
                gap: 0.65rem;
            }
            .flacso-novedades-admin-item {
                display: flex;
                align-items: center;
                justify-content: space-between;
                gap: 0.8rem;
                flex-wrap: wrap;
                padding: 0.75rem 0.85rem;
                border-bottom: 1px dashed #e2e7ef;
            }
            .flacso-novedades-admin-item__label {
                display: flex;
                align-items: center;
                gap: 0.6rem;
                flex: 1;
                min-width: 200px;
            }
            .flacso-novedades-admin-order {
                display: inline-flex;
                align-items: center;
                justify-content: center;
                width: 32px;
                height: 32px;
                border-radius: 999px;
                background: var(--global-palette8, #eef2ff);
                color: var(--global-palette1, #1d3a72);
                font-weight: 600;
                font-size: 0.85rem;
            }
            .flacso-novedades-admin-title {
                font-size: 0.95rem;
                color: #1f2933;
                flex: 1;
                min-width: 160px;
            }
            .flacso-novedades-admin-actions {
                display: flex;
                align-items: center;
                gap: 0.35rem;
            }
            .flacso-novedades-order-action {
                border-radius: 6px;
                border: 1px solid #cbd5f5;
                background: #fff;
                color: #0f172a;
                width: 34px;
                height: 34px;
                display: inline-flex;
                align-items: center;
                justify-content: center;
                cursor: pointer;
                transition: border 0.2s ease, background 0.2s ease;
            }
            .flacso-novedades-order-action:focus-visible,
            .flacso-novedades-order-action:hover {
                border-color: var(--global-palette1, #1d3a72);
                background: var(--global-palette1, #1d3a72);
                color: #fff;
            }
            .flacso-novedades-pin-toggle {
                border-radius: 999px;
                border: 1px solid var(--global-palette2, #f7b733);
                background: transparent;
                color: var(--global-palette1, #1d3a72);
                padding: 0.4rem 1rem;
                font-size: 0.85rem;
                font-weight: 700;
                letter-spacing: 0.08em;
                
                cursor: pointer;
                transition: all 0.25s ease;
            }
            .flacso-novedades-admin-highlights {
                margin-bottom: 1rem;
            }
            .flacso-novedades-admin-search {
                margin-top: 1.5rem;
            }
            .flacso-novedades-search-field {
                display: flex;
                flex-direction: column;
                gap: 0.4rem;
            }
            .flacso-novedades-search-input {
                width: 100%;
                max-width: 100%;
            }
            .flacso-novedades-search-hint {
                font-size: 0.8rem;
                color: #4b5563;
            }
            .flacso-novedades-search-results {
                margin-top: 0.75rem;
            }
            .flacso-novedades-pin-toggle:hover,
            .flacso-novedades-pin-toggle:focus-visible {
                border-color: var(--global-palette1, #1d3a72);
                color: #0f172a;
            }
            .flacso-novedades-pin-toggle.is-sticky {
                background: var(--global-palette2, #f7b733);
                border-color: var(--global-palette2, #f7b733);
                color: var(--global-palette3, #0f1a2d);
            }
            .flacso-heading-color-grid {
                display: grid;
                grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
                gap: 0.75rem;
                margin-top: 0.5rem;
            }
            .flacso-heading-color-option {
                display: flex;
                align-items: center;
                justify-content: space-between;
                gap: 0.75rem;
                padding: 0.65rem 0.85rem;
                border: 1px solid #e0e4ef;
                border-radius: 6px;
                background: #fff;
            }
            .flacso-heading-color-option span {
                font-weight: 600;
                color: #1f2933;
            }
            .flacso-color-picker {
                display: inline-flex;
                align-items: center;
                flex-wrap: wrap;
                gap: 0.5rem;
            }
            .flacso-color-presets {
                display: inline-flex;
                flex-wrap: wrap;
                gap: 0.35rem;
            }
            .flacso-color-preset {
                width: 28px;
                height: 28px;
                border-radius: 6px;
                border: 1px solid rgba(15, 26, 45, 0.2);
                background: transparent;
                padding: 0;
                cursor: pointer;
            }
            .flacso-color-preset:focus-visible {
                outline: 2px solid var(--global-palette1, #1d3a72);
                outline-offset: 2px;
            }
            .flacso-richtext-editor {
                margin-top: 1rem;
            }
            .flacso-hero-button {
                border: 1px solid #d7d7d7;
                border-radius: 10px;
                padding: 1rem;
                margin-bottom: 1rem;
                background: #fff;
            }
            .flacso-hero-button legend {
                font-weight: 600;
                padding: 0 0.25rem;
            }
            .flacso-hero-button .description {
                margin-top: .25rem;
            }
            .flacso-hero-preview {
                margin-top: 1.5rem;
                padding: 1rem 1.25rem;
                border: 1px solid rgba(13, 31, 68, 0.1);
                border-radius: 16px;
                background: #f1f4ff;
            }
            .flacso-hero-preview__head {
                display: flex;
                align-items: center;
                justify-content: space-between;
                gap: 0.5rem;
                margin-bottom: 0.75rem;
                font-weight: 600;
            }
            .flacso-hero-preview__buttons {
                display: flex;
                flex-wrap: wrap;
                gap: 0.6rem;
                margin-bottom: 0.9rem;
            }
            .flacso-hero-preview-btn {
                padding: 0.45rem 1.1rem;
                border-radius: 999px;
                font-weight: 600;
                text-decoration: none;
                font-size: 0.9rem;
                display: inline-flex;
                align-items: center;
                justify-content: center;
                transition: transform 0.15s ease;
            }
            .flacso-hero-preview-btn:hover {
                transform: translateY(-1px);
            }
            .flacso-hero-preview-btn--primary {
                background: #0f1a2d;
                color: #fff;
            }
            .flacso-hero-preview-btn--outline {
                border: 1px solid rgba(15, 26, 45, 0.3);
                color: rgba(15, 26, 45, 0.9);
            }
            .flacso-hero-preview-btn--light {
                border: 1px solid rgba(15, 26, 45, 0.15);
                color: #0f1a2d;
                background: #fff;
            }
            .flacso-hero-preview-btn--ghost {
                border: 1px dashed rgba(15, 26, 45, 0.5);
                color: rgba(15, 26, 45, 0.8);
                background: transparent;
            }
            .flacso-hero-preview__links {
                list-style: none;
                margin: 0;
                padding: 0;
                display: flex;
                flex-direction: column;
                gap: 0.4rem;
                font-size: 0.85rem;
                color: rgba(15, 26, 45, 0.8);
            }
            .flacso-hero-preview__links li {
                display: flex;
                gap: 0.35rem;
                align-items: center;
                flex-wrap: wrap;
            }
            .flacso-hero-preview__link-style {
                
                font-size: 0.65rem;
                letter-spacing: 0.15em;
                color: rgba(15, 26, 45, 0.6);
            }
            .flacso-hero-preview__links a {
                color: var(--global-palette3, #0f1a2d);
            }
            .flacso-image-input {
                margin-top: 1rem;
                display: flex;
                flex-direction: column;
                gap: 0.65rem;
            }
            .flacso-image-preview {
                width: 100%;
                max-width: 360px;
                border-radius: 12px;
                padding: 0.35rem;
                background: #f8f9ff;
                border: 1px solid rgba(29, 58, 114, 0.15);
            }
            .flacso-image-preview img {
                width: 100%;
                height: 180px;
                object-fit: cover;
                border-radius: 8px;
                display: block;
            }
            .flacso-admin-eventos {
                margin-top: 1rem;
            }
            .flacso-admin-eventos-grid {
                display: grid;
                grid-template-columns: repeat(auto-fit, minmax(240px, 1fr));
                gap: 1rem;
            }
            .flacso-admin-eventos-card {
                border: 1px solid rgba(13, 31, 68, 0.12);
                border-radius: 14px;
                overflow: hidden;
                background: #fff;
                display: flex;
                flex-direction: column;
                min-height: 360px;
                box-shadow: 0 10px 25px rgba(0, 0, 0, 0.05);
            }
            .flacso-admin-eventos-img {
                position: relative;
                padding-top: 100%;
                overflow: hidden;
            }
            .flacso-admin-eventos-img img {
                position: absolute;
                inset: 0;
                width: 100%;
                height: 100%;
                object-fit: cover;
                display: block;
            }
            .flacso-admin-eventos-content {
                flex: 1;
                padding: 1rem 1.25rem;
            }
            .flacso-admin-eventos-content h3 {
                margin: 0.25rem 0;
                font-size: 1rem;
            }
            .flacso-admin-eventos-date {
                font-size: 0.85rem;
                color: #1d3a72;
                font-weight: 600;
                display: flex;
                align-items: center;
                flex-wrap: wrap;
                gap: 0.35rem;
            }
            .flacso-admin-eventos-content p {
                margin: 0.35rem 0;
                color: #4b5563;
                font-size: 0.9rem;
                line-height: 1.4;
            }
            .flacso-admin-eventos-datetime {
                display: inline-flex;
                align-items: center;
                gap: 0.25rem;
            }
            .flacso-admin-eventos-badge {
                font-size: 0.75rem;
                text-transform: uppercase;
                letter-spacing: 0.05em;
                padding: 0.15rem 0.5rem;
                border-radius: 999px;
                background: #1d3a72;
                color: #fff;
                font-weight: 700;
            }
            .flacso-admin-eventos-badge.is-today {
                background: #f97316;
                color: #3b2005;
            }
            .flacso-admin-eventos-badge.is-tomorrow {
                background: #0ea5e9;
                color: #041c2c;
            }
            .flacso-admin-eventos-card.is-today {
                border-color: #fb923c;
                background: linear-gradient(135deg, #fff9f2, #ffe7d1);
                box-shadow: 0 18px 40px rgba(249, 146, 60, 0.25);
            }
            .flacso-admin-eventos-card.is-today .flacso-admin-eventos-date {
                color: #b45309;
            }
            .flacso-admin-eventos-card.is-tomorrow {
                border-color: #38bdf8;
                background: linear-gradient(135deg, #f0f9ff, #dff1ff);
                box-shadow: 0 18px 40px rgba(56, 189, 248, 0.2);
            }
            .flacso-admin-eventos-card.is-tomorrow .flacso-admin-eventos-date {
                color: #0f4c81;
            }
            .flacso-admin-eventos-actions {
                border-top: 1px solid rgba(13, 31, 68, 0.08);
                padding: 0.85rem 1.25rem;
                display: flex;
                justify-content: space-between;
                gap: 0.5rem;
                font-size: 0.85rem;
                background: #f8f9ff;
            }
            .flacso-admin-eventos-actions a {
                color: #0f1a2d;
                text-decoration: none;
                font-weight: 600;
            }
            .flacso-global-header {
                margin-bottom: 1.75rem;
            }
            .flacso-global-header .description {
                margin: 0.35rem 0 0;
                color: #475569;
                max-width: 740px;
            }
            .flacso-global-overview {
                margin-bottom: 1.75rem;
            }
            .flacso-global-overview-grid {
                display: grid;
                grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
                gap: 1rem;
            }
            .flacso-global-overview-card {
                border: 1px solid rgba(13, 31, 68, 0.15);
                border-radius: 14px;
                background: #fff;
                padding: 1rem 1.25rem;
                box-shadow: 0 10px 26px rgba(15, 26, 45, 0.08);
                display: flex;
                flex-direction: column;
                gap: 0.45rem;
                min-height: 170px;
            }
            .flacso-global-overview-card__kicker {
                margin: 0;
                font-size: 0.7rem;
                letter-spacing: 0.2em;
                
                color: #1d3a72;
            }
            .flacso-global-overview-card__title {
                margin: 0;
                font-size: 1.05rem;
            }
            .flacso-global-overview-card__description {
                margin: 0;
                color: #475569;
                flex: 1;
                font-size: 0.9rem;
                line-height: 1.5;
            }
            .flacso-global-overview-card__actions {
                margin-top: 0.35rem;
            }
            .flacso-global-overview-card__action {
                
                font-size: 0.8rem;
                padding: 0.35rem 1rem;
            }
        </style>
        <?php
    }

    private static function render_global_overview_cards(): void {
        $cards = [
            [
                'kicker' => __('Home', 'flacso-main-page'),
                'title' => __('Landing principal', 'flacso-main-page'),
                'description' => __('Hero, eventos, novedades y secciones modulares se coordinan desde aqui.', 'flacso-main-page'),
                'link' => admin_url('admin.php?page=flacso-main-page'),
                'link_label' => __('Configurar landing', 'flacso-main-page'),
            ],
            [
                'kicker' => __('Eventos', 'flacso-main-page'),
                'title' => __('Eventos y novedades', 'flacso-main-page'),
                'description' => __('Gestiona los eventos publicados y fija noticias destacadas en un mismo tablero.', 'flacso-main-page'),
                'link' => admin_url('admin.php?page=flacso-main-page-eventos'),
                'link_label' => __('Ver eventos', 'flacso-main-page'),
            ],
            [
                'kicker' => __('Noticias', 'flacso-main-page'),
                'title' => __('Novedades destacadas', 'flacso-main-page'),
                'description' => __('Controla los listados, fijaciones y busquedas que irradian desde la portada.', 'flacso-main-page'),
                'link' => admin_url('admin.php?page=flacso-main-page-novedades'),
                'link_label' => __('Administrar novedades', 'flacso-main-page'),
            ],
            [
                'kicker' => __('Formacion', 'flacso-main-page'),
                'title' => __('Posgrados y seminarios', 'flacso-main-page'),
                'description' => __('Actualiza tarjetas, introducciones y etiquetas de los programas de FLACSO.', 'flacso-main-page'),
                'link' => admin_url('admin.php?page=flacso-main-page-posgrados'),
                'link_label' => __('Editar posgrados', 'flacso-main-page'),
            ],
            [
                'kicker' => __('Institucional', 'flacso-main-page'),
                'title' => __('Paginas institucionales', 'flacso-main-page'),
                'description' => __('Incluye quienes somos, congreso y contacto con estilos coordinados.', 'flacso-main-page'),
                'link' => admin_url('admin.php?page=flacso-main-page-quienes'),
                'link_label' => __('Gestionar paginas', 'flacso-main-page'),
            ],
            [
                'kicker' => __('Bloques', 'flacso-main-page'),
                'title' => __('Bloques y recursos', 'flacso-main-page'),
                'description' => __('Accede rapido a shortcodes, bloques y enlaces de referencia del plugin.', 'flacso-main-page'),
                'link' => admin_url('admin.php?page=flacso-main-page-info'),
                'link_label' => __('Ver recursos', 'flacso-main-page'),
            ],
        ];
        ?>
        <section class="flacso-global-overview">
            <div class="flacso-global-overview-grid">
                <?php foreach ($cards as $card) : ?>
                    <article class="flacso-global-overview-card">
                        <?php if (!empty($card['kicker'])) : ?>
                            <p class="flacso-global-overview-card__kicker"><?php echo esc_html($card['kicker']); ?></p>
                        <?php endif; ?>
                        <h3 class="flacso-global-overview-card__title"><?php echo esc_html($card['title']); ?></h3>
                        <p class="flacso-global-overview-card__description"><?php echo esc_html($card['description']); ?></p>
                        <?php if (!empty($card['link'])) : ?>
                            <div class="flacso-global-overview-card__actions">
                                <a class="button button-secondary flacso-global-overview-card__action" href="<?php echo esc_url($card['link']); ?>">
                                    <?php echo esc_html($card['link_label'] ?? __('Ver', 'flacso-main-page')); ?>
                                </a>
                            </div>
                        <?php endif; ?>
                    </article>
                <?php endforeach; ?>
            </div>
        </section>
        <?php
    }

    private static function text_input(string $name, $value, string $label): void {
        ?>
        <p>
            <label>
                <span><?php echo esc_html($label); ?></span><br>
                <input type="text" class="regular-text" name="<?php echo esc_attr($name); ?>" value="<?php echo esc_attr((string) ($value ?? '')); ?>">
            </label>
        </p>
        <?php
    }

    private static function number_input(string $name, $value, string $label, array $attributes = []): void {
        $attr_string = '';
        foreach ($attributes as $attr => $attr_value) {
            $attr_string .= ' ' . esc_attr($attr) . '="' . esc_attr((string) $attr_value) . '"';
        }
        ?>
        <p>
            <label>
                <span><?php echo esc_html($label); ?></span><br>
                <input type="number" class="regular-text" name="<?php echo esc_attr($name); ?>" value="<?php echo esc_attr((string) ($value ?? '')); ?>"<?php echo $attr_string; ?>>
            </label>
        </p>
        <?php
    }

    private static function textarea_input(string $name, string $value, string $label): void {
        ?>
        <p>
            <label>
                <span><?php echo esc_html($label); ?></span><br>
                <textarea name="<?php echo esc_attr($name); ?>" rows="4"><?php echo esc_textarea($value); ?></textarea>
            </label>
        </p>
        <?php
    }

    private static function select_input(string $name, string $value, string $label, array $options): void {
        ?>
        <p>
            <label>
                <span><?php echo esc_html($label); ?></span><br>
                <select name="<?php echo esc_attr($name); ?>">
                    <?php foreach ($options as $opt_value => $opt_label) : ?>
                        <option value="<?php echo esc_attr($opt_value); ?>" <?php selected($value, $opt_value); ?>>
                            <?php echo esc_html($opt_label); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </label>
        </p>
        <?php
    }

    private static function image_input_with_preview(string $name, string $value, string $label): void {
        $value = trim($value);
        $preview_id = 'flacso-image-preview-' . sanitize_key(str_replace(['[', ']'], '-', $name));
        $input_id = 'flacso-image-input-' . sanitize_key(str_replace(['[', ']'], '-', $name));
        $placeholder = 'https://via.placeholder.com/360x200?text=Imagen';
        ?>
        <div class="flacso-image-input">
            <label>
                <span><?php echo esc_html($label); ?></span><br>
                <input
                    id="<?php echo esc_attr($input_id); ?>"
                    type="url"
                    class="regular-text flacso-image-preview-input"
                    name="<?php echo esc_attr($name); ?>"
                    value="<?php echo esc_attr($value); ?>"
                    data-preview-target="<?php echo esc_attr($preview_id); ?>"
                    data-placeholder="<?php echo esc_attr($placeholder); ?>"
                >
            </label>
            <button type="button"
                    class="button button-secondary flacso-image-select"
                    data-target-input="#<?php echo esc_attr($input_id); ?>"
                    data-media-title="<?php echo esc_attr($label); ?>"
                    data-media-button="<?php esc_attr_e('Usar imagen', 'flacso-main-page'); ?>">
                <?php esc_html_e('Seleccionar de la biblioteca', 'flacso-main-page'); ?>
            </button>
            <div class="flacso-image-preview" id="<?php echo esc_attr($preview_id); ?>">
                <img src="<?php echo esc_url($value ?: $placeholder); ?>" alt="<?php echo esc_attr($label); ?>">
            </div>
        </div>
        <?php
    }

    private static function url_input_with_preview(string $name, string $value, string $label, string $placeholder = 'https://'): void {
        $value = trim($value);
        $preview_id = 'flacso-url-preview-' . sanitize_key(str_replace(['[', ']'], '-', $name));
        $input_id = 'flacso-url-input-' . sanitize_key(str_replace(['[', ']'], '-', $name));
        $display = $value ?: __('Sin URL', 'flacso-main-page');
        ?>
        <div class="flacso-url-input">
            <label>
                <span><?php echo esc_html($label); ?></span><br>
                <input
                    id="<?php echo esc_attr($input_id); ?>"
                    type="text"
                    inputmode="url"
                    class="regular-text flacso-url-input-field"
                    name="<?php echo esc_attr($name); ?>"
                    value="<?php echo esc_attr($value); ?>"
                    data-preview-target="<?php echo esc_attr($preview_id); ?>"
                    placeholder="<?php echo esc_attr($placeholder); ?>"
                >
            </label>
            <div class="flacso-url-preview" id="<?php echo esc_attr($preview_id); ?>">
                <a href="<?php echo esc_url($value ?: '#'); ?>" target="_blank" rel="noopener" class="flacso-url-preview__link<?php echo $value ? '' : ' flacso-url-preview__link--empty'; ?>" data-placeholder="<?php echo esc_attr($display); ?>">
                    <?php echo esc_html($display); ?>
                </a>
            </div>
        </div>
        <?php
    }

    private static function color_input(string $name, string $value, string $label): void {
        ?>
        <p>
            <label>
                <span><?php echo esc_html($label); ?></span><br>
                <div class="flacso-color-picker">
                    <input type="color" class="flacso-color-input" name="<?php echo esc_attr($name); ?>" value="<?php echo esc_attr($value ?: '#ffffff'); ?>">
                    <?php self::render_color_presets(); ?>
                </div>
            </label>
        </p>
        <?php
    }

    private static function get_kadence_palette(): array {
        return [
            '#1d3a72' => __('Kadence azul institucional', 'flacso-main-page'),
            '#102449' => __('Kadence azul profundo', 'flacso-main-page'),
            '#f7b733' => __('Kadence dorado', 'flacso-main-page'),
            '#0f1a2d' => __('Kadence marino', 'flacso-main-page'),
            '#6b7280' => __('Kadence gris', 'flacso-main-page'),
            '#ffffff' => __('Kadence blanco', 'flacso-main-page'),
        ];
    }

    private static function render_color_presets(): void {
        $palette = self::get_kadence_palette();
        if (empty($palette)) {
            return;
        }
        echo '<div class="flacso-color-presets" role="list">';
        foreach ($palette as $hex => $label) {
            printf(
                '<button type="button" class="flacso-color-preset" data-color="%1$s" style="background:%1$s" aria-label="%2$s" title="%2$s"></button>',
                esc_attr($hex),
                esc_attr($label)
            );
        }
        echo '</div>';
    }

    private static function rich_text_editor(string $name, string $value, string $label, array $editor_args = []): void {
        $editor_id = $editor_args['editor_id'] ?? sanitize_title($name);
        $settings = array_merge([
            'textarea_name' => $name,
            'textarea_rows' => 6,
            'teeny' => true,
            'tinymce' => [
                'toolbar1' => 'bold italic underline bullist numlist link',
                'toolbar2' => '',
            ],
            'media_buttons' => false,
            'quicktags' => true,
        ], $editor_args);
        ?>
        <div class="flacso-richtext-editor">
            <label>
                <span><?php echo esc_html($label); ?></span>
            </label>
            <?php wp_editor($value, $editor_id, $settings); ?>
        </div>
        <?php
    }

    private static function maybe_save_settings(): void {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST' || empty($_POST['Flacso_Main_Page_Settings'])) {
            return;
        }

        check_admin_referer('flacso-main-page_save', 'flacso-main-page_nonce');
        $input = wp_unslash($_POST['Flacso_Main_Page_Settings']);
        $current = Flacso_Main_Page_Settings::get_settings();
        $merged = array_replace_recursive($current, $input);
        $sanitized = Flacso_Main_Page_Settings::sanitize($merged);
        update_option(Flacso_Main_Page_Settings::OPTION_KEY, $sanitized);
        add_settings_error('flacso-main-page_messages', 'flacso-main-page_saved', __('Los ajustes fueron guardados.', 'flacso-main-page'), 'updated');
    }

    private static function get_section_from_page(string $page_slug): string {
        foreach (self::SECTION_PAGES as $section_key => $config) {
            if ($config['slug'] === $page_slug) {
                return $section_key;
            }
        }

        return 'hero';
    }

    private static function render_hero_preview(array $buttons, bool $show_buttons): void {
        $visible_buttons = array_filter($buttons, function ($button) {
            return !empty($button['enabled']) && (!empty($button['label']) || !empty($button['url']));
        });
        ?>
        <div class="flacso-hero-preview">
            <div class="flacso-hero-preview__head">
                <strong><?php esc_html_e('Previsualización de botones', 'flacso-main-page'); ?></strong>
                <?php if (!$show_buttons) : ?>
                    <span class="flacso-hero-preview__status"><?php esc_html_e('Botones ocultos', 'flacso-main-page'); ?></span>
                <?php endif; ?>
            </div>
            <?php if (!empty($visible_buttons)) : ?>
                <div class="flacso-hero-preview__buttons">
                    <?php foreach ($visible_buttons as $button) : ?>
                        <?php
                        $label = $button['label'] ?: __('Sin texto', 'flacso-main-page');
                        $url = $button['url'] ?: '#';
                        ?>
                        <a href="<?php echo esc_url($url); ?>" target="_blank" rel="noopener" class="flacso-hero-preview-btn flacso-hero-preview-btn--<?php echo esc_attr($button['style']); ?>">
                            <?php echo esc_html($label); ?>
                        </a>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
            <ul class="flacso-hero-preview__links">
                <?php foreach ($buttons as $button) : ?>
                    <?php if (empty($button['label']) && empty($button['url'])) : continue; endif; ?>
                    <li>
                        <span class="flacso-hero-preview__link-style"><?php echo esc_html($button['style']); ?></span>
                        <strong><?php echo esc_html($button['label'] ?: __('Sin texto', 'flacso-main-page')); ?></strong>
                        &middot;
                        <a href="<?php echo esc_url($button['url'] ?: '#'); ?>" target="_blank" rel="noopener"><?php echo esc_html($button['url'] ? $button['url'] : __('Sin URL', 'flacso-main-page')); ?></a>
                    </li>
                <?php endforeach; ?>
            </ul>
        </div>
        <?php
    }

    public static function render_eventos_section(): void {
        $query = new WP_Query([
            'post_type'      => 'evento',
            'posts_per_page' => 6,
            'post_status'    => 'publish',
            'meta_key'       => 'evento_inicio_fecha',
            'orderby'        => 'meta_value',
            'order'          => 'ASC',
            'meta_query'     => [
                'relation' => 'OR',
                [
                    'key'     => 'evento_fin_fecha',
                    'value'   => date_i18n('Y-m-d', current_time('timestamp')),
                    'compare' => '>=',
                    'type'    => 'DATE',
                ],
                [
                    'relation' => 'AND',
                    [
                        'key'     => 'evento_fin_fecha',
                        'compare' => 'NOT EXISTS',
                    ],
                    [
                        'key'     => 'evento_inicio_fecha',
                        'value'   => date_i18n('Y-m-d', current_time('timestamp')),
                        'compare' => '>=',
                        'type'    => 'DATE',
                    ],
                ],
            ],
        ]);

        if (!$query->have_posts()) {
            echo '<p>' . esc_html__('No hay eventos publicados.', 'flacso-main-page') . '</p>';
            return;
        }

        $timezone = wp_timezone();
        $now_dt   = new \DateTimeImmutable('now', $timezone);
        $today_dt = $now_dt->setTime(0, 0, 0);

        echo '<div class="flacso-admin-eventos">';
        echo '<div class="flacso-admin-eventos-grid">';

        while ($query->have_posts()) {
            $query->the_post();
            $event_id     = get_the_ID();
            $thumbnail    = get_the_post_thumbnail_url($event_id, 'medium') ?: 'https://via.placeholder.com/320x200?text=Evento';
            $start_date   = get_post_meta($event_id, 'evento_inicio_fecha', true);
            $start_time   = get_post_meta($event_id, 'evento_inicio_hora', true);
            $start_dt     = null;
            if ($start_date) {
                try {
                    $start_dt = new \DateTimeImmutable(
                        trim($start_date . ' ' . ($start_time ?: '00:00')),
                        $timezone
                    );
                } catch (\Exception $exception) {
                    $start_dt = null;
                }
            }

            $date_label = '';
            $time_label = '';
            if ($start_dt instanceof \DateTimeImmutable) {
                $timestamp  = $start_dt->getTimestamp();
                $date_label = esc_html(wp_date('j F Y', $timestamp));
                if (!empty($start_time)) {
                    $time_label = esc_html(wp_date(get_option('time_format') ?: 'H:i', $timestamp));
                }
            } elseif ($start_date) {
                $date_label = esc_html(date_i18n('j F Y', strtotime($start_date)));
            }

            $datetime_label = trim($date_label . ($time_label ? ' | ' . $time_label : ''));
            $card_classes   = 'flacso-admin-eventos-card';
            $status_label   = '';
            $status_class   = '';

            if ($start_dt instanceof \DateTimeImmutable) {
                $start_day = $start_dt->setTime(0, 0, 0);
                $days_diff = (int) $today_dt->diff($start_day)->format('%r%a');
                if ($days_diff === 0) {
                    $card_classes .= ' is-today';
                    $status_label = __('Hoy', 'flacso-main-page');
                    $status_class = 'is-today';
                } elseif ($days_diff === 1) {
                    $card_classes .= ' is-tomorrow';
                    $status_label = __('Mañana', 'flacso-main-page');
                    $status_class = 'is-tomorrow';
                }
            }

            $excerpt = get_the_excerpt($event_id) ?: wp_trim_words(get_post_field('post_content', $event_id), 24);

            ?>
            <article class="<?php echo esc_attr($card_classes); ?>">
                <div class="flacso-admin-eventos-img">
                    <img src="<?php echo esc_url($thumbnail); ?>" alt="<?php echo esc_attr(get_the_title($event_id)); ?>">
                </div>
                <div class="flacso-admin-eventos-content">
                    <p class="flacso-admin-eventos-date">
                        <?php if ($datetime_label) : ?>
                            <span class="flacso-admin-eventos-datetime"><?php echo $datetime_label; ?></span>
                        <?php endif; ?>
                        <?php if ($status_label) : ?>
                            <span class="flacso-admin-eventos-badge <?php echo esc_attr($status_class); ?>">
                                <?php echo esc_html($status_label); ?>
                            </span>
                        <?php endif; ?>
                    </p>
                    <h3><?php echo esc_html(get_the_title($event_id)); ?></h3>
                    <p><?php echo esc_html($excerpt); ?></p>
                </div>
                <div class="flacso-admin-eventos-actions">
                    <a href="<?php echo esc_url(get_edit_post_link($event_id)); ?>"><?php esc_html_e('Editar evento', 'flacso-main-page'); ?></a>
                    <a href="<?php echo esc_url(get_permalink($event_id)); ?>" target="_blank" rel="noopener"><?php esc_html_e('Ver en el sitio', 'flacso-main-page'); ?></a>
                </div>
            </article>
            <?php
        }

        echo '</div></div>';
        wp_reset_postdata();
    }

    public static function render_info_page(): void {
        if (!current_user_can('manage_options')) {
            return;
        }

        $blocks = Flacso_Main_Page_Blocks::get_blocks_map();

        $features = [
            [
                'label' => __('Gestor visual de landing', 'flacso-main-page'),
                'text'  => __('Desde la pestaña principal podés activar o desactivar secciones, reorganizar tarjetas y definir etiquetas.', 'flacso-main-page'),
                'icon'  => 'admin-customizer',
            ],
            [
                'label' => __('Metabox de seminarios', 'flacso-main-page'),
                'text'  => __('Agrega campos estructurados, controla la visibilidad en formularios y sincroniza metadatos legacy.', 'flacso-main-page'),
                'icon'  => 'forms',
            ],
            [
                'label' => __('Bloques Gutenberg/Kadence', 'flacso-main-page'),
                'text'  => __('Todos los shortcodes tienen su bloque equivalente con vista previa en vivo y atributos editables.', 'flacso-main-page'),
                'icon'  => 'screenoptions',
            ],
            [
                'label' => __('Assets y estilos FLACSO', 'flacso-main-page'),
                'text'  => __('El plugin registra estilos base, íconos y helpers reutilizables para mantener la identidad visual.', 'flacso-main-page'),
                'icon'  => 'art',
            ],
            [
                'label' => __('Integración con eventos y posgrados', 'flacso-main-page'),
                'text'  => __('Incluye CPT de eventos, widgets de noticias y normaliza información de posgrados vigentes.', 'flacso-main-page'),
                'icon'  => 'calendar',
            ],
        ];

        ?>
        <div class="wrap flacso-plugin-info">
            <h1><?php esc_html_e('Información general del plugin FLACSO', 'flacso-main-page'); ?></h1>
            <p class="description">
                <?php
                printf(
                    /* translators: %s plugin version */
                    esc_html__('Versión instalada: %s', 'flacso-main-page'),
                    esc_html(defined('FLACSO_MAIN_PAGE_VERSION') ? FLACSO_MAIN_PAGE_VERSION : '—')
                );
                ?>
            </p>

            <section class="flacso-info-section">
                <h2><?php esc_html_e('¿Qué incluye este plugin?', 'flacso-main-page'); ?></h2>
                <div class="flacso-info-grid">
                    <?php foreach ($features as $feature) : ?>
                        <div class="flacso-info-card">
                            <div class="flacso-info-card__icon dashicons dashicons-<?php echo esc_attr($feature['icon']); ?>"></div>
                            <h3><?php echo esc_html($feature['label']); ?></h3>
                            <p><?php echo esc_html($feature['text']); ?></p>
                        </div>
                    <?php endforeach; ?>
                </div>
            </section>

            <section class="flacso-info-section">
                <h2><?php esc_html_e('Paginas gestionadas', 'flacso-main-page'); ?></h2>
                <?php
                $pages = [
                    [
                        'title' => __('Landing principal', 'flacso-main-page'),
                        'text' => __('Hero, eventos, novedades y secciones modulares se coordinan desde este panel.', 'flacso-main-page'),
                        'link' => admin_url('admin.php?page=flacso-main-page'),
                    ],
                    [
                        'title' => __('Eventos y novedades', 'flacso-main-page'),
                        'text' => __('Gestiona los eventos publicados y fija noticias destacadas en el home.', 'flacso-main-page'),
                        'link' => admin_url('admin.php?page=flacso-main-page-eventos'),
                    ],
                    [
                        'title' => __('Posgrados y seminarios', 'flacso-main-page'),
                        'text' => __('Actualiza tarjetas, introducciones y enlaces para cursos y diplomas.', 'flacso-main-page'),
                        'link' => admin_url('admin.php?page=flacso-main-page-posgrados'),
                    ],
                    [
                        'title' => __('Paginas institucionales', 'flacso-main-page'),
                        'text' => __('Quiienes somos, congreso y contacto comparten estilos y contenidos.', 'flacso-main-page'),
                        'link' => admin_url('admin.php?page=flacso-main-page-quienes'),
                    ],
                    [
                        'title' => __('Bloques y recursos', 'flacso-main-page'),
                        'text' => __('Shortcodes y bloques listos para insertar en cualquier pagina.', 'flacso-main-page'),
                        'link' => admin_url('admin.php?page=flacso-main-page-info'),
                    ],
                ];
                ?>
                <div class="flacso-pages-grid">
                    <?php foreach ($pages as $page) : ?>
                        <article class="flacso-page-card">
                            <h3><?php echo esc_html($page['title']); ?></h3>
                            <p><?php echo esc_html($page['text']); ?></p>
                            <?php if (!empty($page['link'])) : ?>
                                <a class="button button-small" href="<?php echo esc_url($page['link']); ?>">
                                    <?php esc_html_e('Abrir panel', 'flacso-main-page'); ?>
                                </a>
                            <?php endif; ?>
                        </article>
                    <?php endforeach; ?>
                </div>
            </section>

            <section class="flacso-info-section">
                <h2><?php esc_html_e('Bloques disponibles', 'flacso-main-page'); ?></h2>
                <table class="widefat fixed striped">
                    <thead>
                        <tr>
                            <th><?php esc_html_e('Bloque', 'flacso-main-page'); ?></th>
                            <th><?php esc_html_e('Descripción', 'flacso-main-page'); ?></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($blocks as $block_name => $block) : ?>
                            <tr>
                                <td>
                                    <strong><?php echo esc_html($block['title']); ?></strong><br>
                                    <code><?php echo esc_html($block_name); ?></code>
                                </td>
                                <td><?php echo esc_html($block['description']); ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </section>

            <section class="flacso-info-section">
                <h2><?php esc_html_e('Recursos útiles', 'flacso-main-page'); ?></h2>
                <ul class="flacso-info-links">
                    <li>
                        <a href="<?php echo esc_url(admin_url('admin.php?page=flacso-main-page')); ?>" class="button button-primary">
                            <?php esc_html_e('Ir al gestor FLACSO', 'flacso-main-page'); ?>
                        </a>
                    </li>
                    <li>
                        <a href="<?php echo esc_url(get_admin_url(null, 'edit.php?post_type=evento')); ?>" class="button">
                            <?php esc_html_e('Gestionar eventos', 'flacso-main-page'); ?>
                        </a>
                    </li>
                    <li>
                        <a href="<?php echo esc_url(site_url('/')); ?>" target="_blank" rel="noopener" class="button">
                            <?php esc_html_e('Vista previa del sitio', 'flacso-main-page'); ?>
                        </a>
                    </li>
                </ul>
            </section>
        </div>

        <style>
            .flacso-plugin-info .description {
                font-size: 1rem;
                margin-bottom: 1.5rem;
            }

            .flacso-info-section + .flacso-info-section {
                margin-top: 2.5rem;
            }

            .flacso-info-grid {
                display: grid;
                grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
                gap: 1rem;
                margin-top: 1rem;
            }

            .flacso-info-card {
                background: #fff;
                border: 1px solid #dcdce4;
                border-radius: 8px;
                padding: 1.25rem;
                box-shadow: 0 4px 14px rgba(0, 0, 0, 0.05);
                min-height: 180px;
            }

            .flacso-info-card__icon {
                font-size: 32px;
                width: 32px;
                height: 32px;
                margin-bottom: 0.75rem;
                color: #1d3a72;
            }

            .flacso-info-card h3 {
                margin: 0 0 0.35rem;
                font-size: 1.05rem;
            }

            .flacso-info-card p {
                margin: 0;
                color: #444;
                line-height: 1.5;
            }

            .flacso-info-links {
                display: flex;
                gap: 0.75rem;
                flex-wrap: wrap;
            }

            .flacso-info-links .button {
                
            }
            .flacso-pages-grid {
                margin-top: 1rem;
                display: grid;
                grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
                gap: 1rem;
            }
            .flacso-page-card {
                border: 1px solid #dcdce4;
                border-radius: 12px;
                padding: 1.25rem;
                background: #fff;
                box-shadow: 0 10px 24px rgba(15, 26, 45, 0.08);
                min-height: 170px;
                display: flex;
                flex-direction: column;
                gap: 0.45rem;
            }
            .flacso-page-card h3 {
                margin: 0;
            }
            .flacso-page-card p {
                margin: 0;
                color: #475569;
                line-height: 1.5;
                flex: 1;
            }
            .flacso-page-card .button-small {
                
                font-size: 0.8rem;
            }
            .flacso-section-order {
                list-style: none;
                margin: 1rem 0 0;
                padding: 0;
                display: flex;
                flex-direction: column;
                gap: 0.5rem;
            }

            .flacso-section-order-item {
                display: flex;
                align-items: center;
                justify-content: space-between;
                gap: 0.75rem;
                padding: 0.65rem 0.8rem;
                border: 1px dashed #d1d5db;
                border-radius: 0.65rem;
                background: #f8fafc;
            }

            .flacso-section-order-label {
                font-weight: 600;
                flex: 1;
            }

            .flacso-section-order-actions {
                display: flex;
                gap: 0.4rem;
            }

            .flacso-section-order-action {
                width: 32px;
                height: 32px;
                border-radius: 999px;
                border: 1px solid #d0d7e0;
                background: #fff;
                color: #0f172a;
                font-size: 1rem;
                cursor: pointer;
                display: inline-flex;
                align-items: center;
                justify-content: center;
            }

            .flacso-section-order-action:focus-visible,
                .flacso-section-order-action:hover {
                    border-color: var(--global-palette1, #1d3a72);
                    color: var(--global-palette1, #1d3a72);
                }
            .flacso-url-input {
                margin-top: 0.75rem;
            }
            .flacso-url-input-field {
                width: 100%;
                max-width: 640px;
            }
            .flacso-url-preview {
                margin-top: 0.35rem;
                font-size: 0.9rem;
            }
            .flacso-url-preview__link {
                color: var(--global-palette5, #4b5563);
                text-decoration: underline;
            }
            .flacso-url-preview__link--empty {
                color: var(--global-palette4, #6b7280);
                text-decoration: none;
            }
            .flacso-image-select {
                margin-top: 0.35rem;
                min-width: 200px;
            }
            .flacso-contacto-background-card {
                margin-top: 1.5rem;
                padding: 1.5rem;
                border-radius: 12px;
                border: 1px solid #d7dbe4;
                background: #f8fafc;
                box-shadow: 0 12px 32px rgba(15, 26, 45, 0.05);
            }
            .flacso-contacto-background-card h3 {
                margin-top: 0;
                margin-bottom: 0.35rem;
            }
            .flacso-contacto-background-card .description {
                margin-bottom: 1rem;
                color: #475569;
            }
            .flacso-contacto-background-group {
                margin-top: 1.25rem;
                padding-top: 1rem;
                border-top: 1px solid rgba(15, 26, 45, 0.1);
            }
            .flacso-contacto-background-group:first-of-type {
                border-top: 0;
                padding-top: 0;
            }
        </style>
        <script>
        document.addEventListener('DOMContentLoaded', function () {
            document.querySelectorAll('.flacso-image-preview-input').forEach(function (input) {
                var targetId = input.dataset.previewTarget;
                if (!targetId) {
                    return;
                }
                var wrapper = document.getElementById(targetId);
                var img = wrapper ? wrapper.querySelector('img') : null;
                if (!img) {
                    return;
                }
                var placeholder = input.dataset.placeholder || img.src;
                function updatePreview() {
                    var value = input.value.trim();
                    img.src = value || placeholder;
                }
                updatePreview();
                input.addEventListener('input', updatePreview);
            });
            Array.prototype.forEach.call(document.querySelectorAll('[data-background-panel]'), function (panel) {
                var selector = panel.querySelector('[data-background-mode-selector]');
                if (!selector) {
                    return;
                }
                var groups = panel.querySelectorAll('[data-background-modes]');
                var toggleGroups = function () {
                    var selectedMode = selector.value || 'color';
                    Array.prototype.forEach.call(groups, function (group) {
                        var list = (group.getAttribute('data-background-modes') || '').split(',');
                        var shouldShow = list.some(function (mode) {
                            return mode.trim() === selectedMode;
                        });
                        group.style.display = shouldShow ? '' : 'none';
                    });
                };
                selector.addEventListener('change', toggleGroups);
                toggleGroups();
            });
        });
        </script>
        <?php
    }

    /**
     * Renderiza la página de administración de Oferta Académica
     */
    public static function render_oferta_academica_page(): void {
        if (!current_user_can('manage_options')) {
            return;
        }

        if (function_exists('wp_enqueue_editor')) {
            wp_enqueue_editor();
        }
        if (function_exists('wp_enqueue_media')) {
            wp_enqueue_media();
        }

        self::maybe_save_settings();
        settings_errors('flacso-main-page_messages');
        $settings = Flacso_Main_Page_Settings::get_settings();
        $oa = $settings['oferta_academica'] ?? [];
        ?>
        <div class="wrap">
            <h1><?php esc_html_e('Oferta Académica', 'flacso-main-page'); ?></h1>
            <p class="description"><?php esc_html_e('Controla qué categorías y elementos se muestran dentro de la página de oferta académica.', 'flacso-main-page'); ?></p>
            
            <form method="post">
                <?php wp_nonce_field('flacso-main-page_save', 'flacso-main-page_nonce'); ?>
                
                <div class="postbox">
                    <h2 class="hndle"><span><?php esc_html_e('Visibilidad de bloques', 'flacso-main-page'); ?></span></h2>
                    <div class="inside">
                        <p>
                            <label>
                                <input type="checkbox" name="Flacso_Main_Page_Settings[oferta_academica][show_filters]" value="1" <?php checked(!empty($oa['show_filters'])); ?>>
                                <?php esc_html_e('Mostrar barra de filtros / navegación superior', 'flacso-main-page'); ?>
                            </label>
                        </p>
                        <p>
                            <label>
                                <input type="checkbox" name="Flacso_Main_Page_Settings[oferta_academica][show_maestrias]" value="1" <?php checked(!empty($oa['show_maestrias'])); ?>>
                                <?php esc_html_e('Mostrar Maestrías', 'flacso-main-page'); ?>
                            </label>
                        </p>
                        <p>
                            <label>
                                <input type="checkbox" name="Flacso_Main_Page_Settings[oferta_academica][show_especializaciones]" value="1" <?php checked(!empty($oa['show_especializaciones'])); ?>>
                                <?php esc_html_e('Mostrar Especializaciones', 'flacso-main-page'); ?>
                            </label>
                        </p>
                        <p>
                            <label>
                                <input type="checkbox" name="Flacso_Main_Page_Settings[oferta_academica][show_diplomados]" value="1" <?php checked(!empty($oa['show_diplomados'])); ?>>
                                <?php esc_html_e('Mostrar Diplomados', 'flacso-main-page'); ?>
                            </label>
                        </p>
                        <p>
                            <label>
                                <input type="checkbox" name="Flacso_Main_Page_Settings[oferta_academica][show_diplomas]" value="1" <?php checked(!empty($oa['show_diplomas'])); ?>>
                                <?php esc_html_e('Mostrar Diplomas', 'flacso-main-page'); ?>
                            </label>
                        </p>
                        <p>
                            <label>
                                <input type="checkbox" name="Flacso_Main_Page_Settings[oferta_academica][show_seminarios]" value="1" <?php checked(!empty($oa['show_seminarios'])); ?>>
                                <?php esc_html_e('Mostrar Seminarios', 'flacso-main-page'); ?>
                            </label>
                        </p>
                    </div>
                </div>
                
                <div class="postbox">
                    <h2 class="hndle"><span><?php esc_html_e('Opciones adicionales', 'flacso-main-page'); ?></span></h2>
                    <div class="inside">
                        <p>
                            <label>
                                <input type="checkbox" name="Flacso_Main_Page_Settings[oferta_academica][show_inactivos]" value="1" <?php checked(!empty($oa['show_inactivos'])); ?>>
                                <?php esc_html_e('Mostrar programas no vigentes', 'flacso-main-page'); ?>
                            </label>
                        </p>
                    </div>
                </div>
                
                <?php submit_button(__('Guardar ajustes', 'flacso-main-page')); ?>
            </form>
        </div>
        <?php
    }
}


