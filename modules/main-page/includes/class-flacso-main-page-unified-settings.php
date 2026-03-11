<?php

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Gestor unificado de configuración FLACSO
 * Interfaz simplificada y robusta
 */
class Flacso_Main_Page_Unified_Settings {
    private const SECTIONS = [
        'hero' => [
            'label' => 'Encabezado',
            'icon' => 'dashicons-image-filter',
        ],
        'eventos' => [
            'label' => 'Eventos',
            'icon' => 'dashicons-calendar-alt',
        ],
        'secciones' => [
            'label' => 'Secciones',
            'icon' => 'dashicons-layout',
        ],
        'novedades' => [
            'label' => 'Novedades',
            'icon' => 'dashicons-admin-post',
        ],
        'posgrados' => [
            'label' => 'Posgrados',
            'icon' => 'dashicons-book-alt',
        ],
        'congreso' => [
            'label' => 'Congreso',
            'icon' => 'dashicons-networking',
        ],
        'quienes' => [
            'label' => 'Quiénes somos',
            'icon' => 'dashicons-groups',
        ],
        'contacto' => [
            'label' => 'Contacto',
            'icon' => 'dashicons-email-alt',
        ],
    ];

    public static function init(): void {
        add_action('admin_enqueue_scripts', [__CLASS__, 'enqueue_scripts']);
    }

    public static function enqueue_scripts($hook): void {
        if (strpos($hook, 'flacso-main-page') === false) {
            return;
        }

        // Cargar el script principal de administración (maneja novedades destacadas y reordenamiento)
        wp_enqueue_script(
            'flacso-main-page-builder',
            FLACSO_MAIN_PAGE_MODULE_URL . 'assets/js/flacso-main-page.js',
            [],
            FLACSO_MAIN_PAGE_VERSION,
            true
        );

        wp_localize_script('flacso-main-page-builder', 'flacsoMainPageData', [
            'ajax_url' => admin_url('admin-ajax.php'),
            'labels' => [
                'stick' => __('Fijar', 'flacso-main-page'),
                'unstick' => __('Desfijar', 'flacso-main-page'),
                'error' => __('No se pudo actualizar la noticia.', 'flacso-main-page'),
                'order_error' => __('No se pudo guardar el orden de novedades.', 'flacso-main-page'),
            ],
        ]);

        wp_enqueue_script(
            'flacso-unified-settings',
            FLACSO_MAIN_PAGE_MODULE_URL . 'assets/js/flacso-unified-settings.js',
            ['flacso-main-page-builder'],
            FLACSO_MAIN_PAGE_VERSION,
            true
        );

        wp_enqueue_style(
            'flacso-unified-settings',
            FLACSO_MAIN_PAGE_MODULE_URL . 'assets/css/flacso-unified-settings.css',
            [],
            FLACSO_MAIN_PAGE_VERSION
        );

        wp_localize_script('flacso-unified-settings', 'flacsoSettings', [
            'ajaxUrl' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('flacso-settings-nonce'),
        ]);
    }

    public static function render_unified_page(): void {
        if (!current_user_can('manage_options')) {
            return;
        }

        if (function_exists('wp_enqueue_editor')) {
            wp_enqueue_editor();
        }
        if (function_exists('wp_enqueue_media')) {
            wp_enqueue_media();
        }

        settings_errors('flacso-main-page_messages');
        $settings = Flacso_Main_Page_Settings::get_settings();
        ?>
        <div class="wrap flacso-unified-wrap">
            <header class="flacso-global-header">
                <h1><?php esc_html_e('Gestor FLACSO', 'flacso-main-page'); ?></h1>
                <p class="description">
                    <?php esc_html_e('Gestiona los módulos del home, las páginas institucionales y los bloques del sitio desde un único panel.', 'flacso-main-page'); ?>
                </p>
            </header>

            <div class="flacso-unified-container">
                <nav class="flacso-unified-tabs" role="tablist" aria-label="<?php esc_attr_e('Secciones del gestor FLACSO', 'flacso-main-page'); ?>">
                    <?php foreach (self::SECTIONS as $key => $config): ?>
                        <button 
                            type="button"
                            class="flacso-unified-tab"
                            role="tab"
                            data-tab="<?php echo esc_attr($key); ?>"
                            aria-selected="<?php echo $key === 'hero' ? 'true' : 'false'; ?>">
                            <span class="dashicons <?php echo esc_attr($config['icon']); ?>"></span>
                            <span><?php echo esc_html($config['label']); ?></span>
                        </button>
                    <?php endforeach; ?>
                </nav>

                <div class="flacso-unified-content">
                    <form class="flacso-unified-form" data-form-section="hero" method="post">
                        <?php wp_nonce_field('flacso-settings-nonce', 'flacso-settings-nonce'); ?>
                        
                        <?php foreach (self::SECTIONS as $key => $config): ?>
                            <section 
                                class="flacso-unified-panel <?php echo $key === 'hero' ? 'is-active' : ''; ?>"
                                id="flacso-panel-<?php echo esc_attr($key); ?>"
                                data-panel="<?php echo esc_attr($key); ?>"
                                role="tabpanel"
                                aria-labelledby="<?php echo esc_attr('flacso-tab-' . $key); ?>">
                                
                                <div class="flacso-section-header">
                                    <h2><?php echo esc_html($config['label']); ?></h2>
                                    <button type="button" class="flacso-save-section" data-section="<?php echo esc_attr($key); ?>">
                                        <span class="dashicons dashicons-cloud-upload"></span>
                                        <?php esc_html_e('Guardar esta sección', 'flacso-main-page'); ?>
                                    </button>
                                </div>

                                <div class="flacso-section-content" data-section-content="<?php echo esc_attr($key); ?>">
                                    <?php self::render_section_content($key, $settings); ?>
                                </div>
                            </section>
                        <?php endforeach; ?>
                    </form>
                </div>
            </div>
        </div>
        <?php
    }

    private static function render_section_content(string $section_key, array $settings): void {
        switch ($section_key) {
            case 'hero':
                self::render_hero_section($settings);
                break;
            case 'eventos':
                self::render_eventos_section($settings);
                break;
            case 'secciones':
                self::render_secciones_section_ui($settings);
                break;
            case 'novedades':
                self::render_novedades_section($settings);
                break;
            case 'posgrados':
                self::render_posgrados_section($settings);
                break;
            case 'oferta_academica':
                self::render_oferta_academica_section($settings);
                break;
            case 'congreso':
                self::render_congreso_section($settings);
                break;
            case 'quienes':
                self::render_quienes_section($settings);
                break;
            case 'contacto':
                self::render_contacto_section($settings);
                break;
        }
    }

    private static function render_hero_section(array $settings): void {
        ?>
        <div class="flacso-form-group">
            <label for="hero_title"><?php esc_html_e('Título', 'flacso-main-page'); ?></label>
            <input 
                type="text" 
                id="hero_title" 
                name="hero[title]" 
                class="regular-text" 
                value="<?php echo esc_attr($settings['hero']['title'] ?? ''); ?>">
        </div>

        <div class="flacso-form-group">
            <label for="hero_subtitle"><?php esc_html_e('Subtítulo', 'flacso-main-page'); ?></label>
            <input 
                type="text" 
                id="hero_subtitle" 
                name="hero[subtitle]" 
                class="regular-text" 
                value="<?php echo esc_attr($settings['hero']['subtitle'] ?? ''); ?>">
        </div>

        <div class="flacso-form-group">
            <label for="hero_background"><?php esc_html_e('Imagen de fondo (URL)', 'flacso-main-page'); ?></label>
            <input 
                type="url" 
                id="hero_background" 
                name="hero[background_image]" 
                class="regular-text" 
                value="<?php echo esc_attr($settings['hero']['background_image'] ?? ''); ?>"
                data-preview-target="hero_background">
            <div class="flacso-image-preview" data-image-preview="hero_background">
                <?php if (!empty($settings['hero']['background_image'])): ?>
                    <img src="<?php echo esc_url($settings['hero']['background_image']); ?>" alt="<?php esc_attr_e('Vista previa', 'flacso-main-page'); ?>">
                <?php else: ?>
                    <span class="flacso-image-placeholder"><?php esc_html_e('Sin imagen', 'flacso-main-page'); ?></span>
                <?php endif; ?>
            </div>
        </div>

        <div class="flacso-form-group">
            <label>
                <input 
                    type="checkbox" 
                    name="hero[show_buttons]" 
                    value="1" 
                    <?php checked(!empty($settings['hero']['show_buttons'] ?? true)); ?>>
                <?php esc_html_e('Mostrar botones principales', 'flacso-main-page'); ?>
            </label>
        </div>

        <h3><?php esc_html_e('Botones principales (hasta 4)', 'flacso-main-page'); ?></h3>
        <?php
        $hero_button_defaults = Flacso_Main_Page_Settings::get_hero_button_defaults();
        $hero_buttons = $settings['hero']['buttons'] ?? $hero_button_defaults;
        $style_options = Flacso_Main_Page_Settings::get_button_style_options();
        
        foreach ($hero_button_defaults as $index => $button_default):
            $button = $hero_buttons[$index] ?? $button_default;
        ?>
            <fieldset class="flacso-hero-button">
                <legend><?php printf(esc_html__('Botón %s', 'flacso-main-page'), $index + 1); ?></legend>
                <div class="flacso-form-group">
                    <label>
                        <input 
                            type="checkbox" 
                            name="hero[buttons][<?php echo esc_attr($index); ?>][enabled]" 
                            value="1" 
                            <?php checked(!empty($button['enabled'])); ?>>
                        <?php esc_html_e('Mostrar este botón', 'flacso-main-page'); ?>
                    </label>
                </div>
                <div class="flacso-form-group">
                    <label for="button_label_<?php echo esc_attr($index); ?>"><?php esc_html_e('Texto del botón', 'flacso-main-page'); ?></label>
                    <input 
                        type="text" 
                        id="button_label_<?php echo esc_attr($index); ?>" 
                        name="hero[buttons][<?php echo esc_attr($index); ?>][label]" 
                        class="regular-text" 
                        value="<?php echo esc_attr($button['label'] ?? ''); ?>">
                </div>
                <div class="flacso-form-group">
                    <label for="button_url_<?php echo esc_attr($index); ?>"><?php esc_html_e('URL del botón', 'flacso-main-page'); ?></label>
                    <input 
                        type="url" 
                        id="button_url_<?php echo esc_attr($index); ?>" 
                        name="hero[buttons][<?php echo esc_attr($index); ?>][url]" 
                        class="regular-text" 
                        value="<?php echo esc_attr($button['url'] ?? ''); ?>">
                </div>
                <div class="flacso-form-group">
                    <label for="button_style_<?php echo esc_attr($index); ?>"><?php esc_html_e('Estilo', 'flacso-main-page'); ?></label>
                    <select id="button_style_<?php echo esc_attr($index); ?>" name="hero[buttons][<?php echo esc_attr($index); ?>][style]">
                        <?php foreach ($style_options as $value => $label): ?>
                            <option value="<?php echo esc_attr($value); ?>" <?php selected($button['style'] ?? '', $value); ?>>
                                <?php echo esc_html($label); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </fieldset>
        <?php endforeach; ?>

        <h3><?php esc_html_e('Botones flotantes', 'flacso-main-page'); ?></h3>
        <div class="flacso-form-group">
            <label for="bubble_primary_label"><?php esc_html_e('Etiqueta burbuja 1', 'flacso-main-page'); ?></label>
            <input 
                type="text" 
                id="bubble_primary_label" 
                name="hero[bubble_primary_label]" 
                class="regular-text" 
                value="<?php echo esc_attr($settings['hero']['bubble_primary_label'] ?? ''); ?>">
        </div>
        <div class="flacso-form-group">
            <label for="bubble_primary_url"><?php esc_html_e('URL burbuja 1', 'flacso-main-page'); ?></label>
            <input 
                type="url" 
                id="bubble_primary_url" 
                name="hero[bubble_primary_url]" 
                class="regular-text" 
                value="<?php echo esc_attr($settings['hero']['bubble_primary_url'] ?? ''); ?>">
        </div>
        <div class="flacso-form-group">
            <label>
                <input 
                    type="checkbox" 
                    name="hero[bubble_primary_enabled]" 
                    value="1" 
                    <?php checked(!empty($settings['hero']['bubble_primary_enabled'] ?? true)); ?>>
                <?php esc_html_e('Mostrar burbuja 1', 'flacso-main-page'); ?>
            </label>
        </div>

        <div class="flacso-form-group">
            <label for="bubble_secondary_label"><?php esc_html_e('Etiqueta burbuja 2', 'flacso-main-page'); ?></label>
            <input 
                type="text" 
                id="bubble_secondary_label" 
                name="hero[bubble_secondary_label]" 
                class="regular-text" 
                value="<?php echo esc_attr($settings['hero']['bubble_secondary_label'] ?? ''); ?>">
        </div>
        <div class="flacso-form-group">
            <label for="bubble_secondary_url"><?php esc_html_e('URL burbuja 2', 'flacso-main-page'); ?></label>
            <input 
                type="url" 
                id="bubble_secondary_url" 
                name="hero[bubble_secondary_url]" 
                class="regular-text" 
                value="<?php echo esc_attr($settings['hero']['bubble_secondary_url'] ?? ''); ?>">
        </div>
        <div class="flacso-form-group">
            <label>
                <input 
                    type="checkbox" 
                    name="hero[bubble_secondary_enabled]" 
                    value="1" 
                    <?php checked(!empty($settings['hero']['bubble_secondary_enabled'] ?? true)); ?>>
                <?php esc_html_e('Mostrar burbuja 2', 'flacso-main-page'); ?>
            </label>
        </div>
        <?php
    }

    private static function render_eventos_section(array $settings): void {
        // Usar el método de la clase admin si existe
        if (method_exists('Flacso_Main_Page_Admin', 'render_eventos_section')) {
            Flacso_Main_Page_Admin::render_eventos_section();
        } else {
            echo '<p>' . esc_html__('Configuración de Eventos', 'flacso-main-page') . '</p>';
        }
    }

    private static function render_secciones_section(array $settings): void {
        $sections_visibility = Flacso_Main_Page_Settings::get_section_visibility();
        ?>
        <p><?php esc_html_e('Activa únicamente las secciones que deben mostrarse en la página principal.', 'flacso-main-page'); ?></p>
        
        <h3><?php esc_html_e('Visibilidad de secciones', 'flacso-main-page'); ?></h3>
        <?php foreach (Flacso_Main_Page_Settings::get_section_visibility_defaults() as $section_key => $default): ?>
            <div class="flacso-form-group">
                <label>
                    <input type="checkbox"
                           name="sections_visibility[<?php echo esc_attr($section_key); ?>]"
                           value="1"
                           <?php checked(!empty($sections_visibility[$section_key])); ?>>
                    <span><?php echo esc_html(Flacso_Main_Page_Settings::get_section_label($section_key)); ?></span>
                </label>
            </div>
        <?php endforeach; ?>

        <h3><?php esc_html_e('Orden de las secciones principales', 'flacso-main-page'); ?></h3>
        <p class="description">
            <?php esc_html_e('Reordena el recorrido de la página principal usando las flechas para mover cada bloque arriba o abajo.', 'flacso-main-page'); ?>
        </p>

        <h3><?php esc_html_e('Color de encabezados por sección', 'flacso-main-page'); ?></h3>
        <p class="description">
            <?php esc_html_e('Elige el color que deben firmar los encabezados de cada sección (el hero mantiene su configuración propia).', 'flacso-main-page'); ?>
        </p>
        <?php
    }

    private static function render_novedades_section(array $settings): void {
        ?>
        <h3><?php esc_html_e('Configuración de Novedades', 'flacso-main-page'); ?></h3>
        <div class="flacso-form-group">
            <label for="novedades_per_page"><?php esc_html_e('Novedades por página', 'flacso-main-page'); ?></label>
            <input 
                type="number" 
                id="novedades_per_page" 
                name="novedades[per_page]" 
                value="<?php echo esc_attr($settings['novedades']['per_page'] ?? 12); ?>"
                min="3"
                max="48">
        </div>
        <p class="description">
            <?php esc_html_e('Define cuántas novedades se muestran en cada página del listado principal.', 'flacso-main-page'); ?>
        </p>

        <h3><?php esc_html_e('Destacados y búsquedas', 'flacso-main-page'); ?></h3>
        <p class="description">
            <?php esc_html_e('Busca publicaciones de la categoría Novedades para destacarlas y verlas primero en la página. Puedes reordenarlas con los botones de arriba/abajo.', 'flacso-main-page'); ?>
        </p>
        <?php
        if (function_exists('flacso_section_novedades_admin_menu_render')) {
            $sticky_admin = flacso_section_novedades_admin_menu_render();
            if ($sticky_admin) {
                echo $sticky_admin;
            } else {
                echo '<p>' . esc_html__('No hay noticias disponibles para administrar.', 'flacso-main-page') . '</p>';
            }
        }
        ?>
        <?php
    }

    private static function render_posgrados_section(array $settings): void {
        $cards = $settings['posgrados']['cards'] ?? [];
        ?>
        <h3><?php esc_html_e('Sección Posgrados', 'flacso-main-page'); ?></h3>
        <div class="flacso-form-group">
            <label for="posgrados_title"><?php esc_html_e('Título', 'flacso-main-page'); ?></label>
            <input 
                type="text" 
                id="posgrados_title" 
                name="posgrados[title]" 
                class="regular-text" 
                value="<?php echo esc_attr($settings['posgrados']['title'] ?? ''); ?>">
        </div>

        <div class="flacso-form-group">
            <label>
                <input 
                    type="checkbox" 
                    name="posgrados[show_title]" 
                    value="1" 
                    <?php checked(!empty($settings['posgrados']['show_title'])); ?>>
                <?php esc_html_e('Mostrar título de sección', 'flacso-main-page'); ?>
            </label>
        </div>

        <div class="flacso-form-group">
            <label for="posgrados_intro"><?php esc_html_e('Descripción', 'flacso-main-page'); ?></label>
            <?php
            $posgrados_intro = $settings['posgrados']['intro'] ?? '';
            if (function_exists('wp_editor')) {
                wp_editor(
                    $posgrados_intro,
                    'posgrados_intro',
                    [
                        'textarea_name' => 'posgrados[intro]',
                        'textarea_rows' => 6,
                        'media_buttons' => true,
                        'tinymce' => true,
                        'quicktags' => true,
                    ]
                );
            } else {
                ?>
                <textarea id="posgrados_intro" name="posgrados[intro]" rows="5" class="regular-text"><?php echo esc_textarea($posgrados_intro); ?></textarea>
                <?php
            }
            ?>
        </div>

        <h3><?php esc_html_e('Tarjetas de programas', 'flacso-main-page'); ?></h3>
        <p class="description"><?php esc_html_e('Actualiza la información de cada tipo de programa.', 'flacso-main-page'); ?></p>
        
        <?php foreach ($cards as $index => $card): ?>
            <fieldset class="flacso-card-fieldset">
                <legend><?php echo esc_html($card['title'] ?? 'Programa'); ?></legend>
                
                <div class="flacso-form-group">
                    <label for="card_<?php echo esc_attr($index); ?>_title"><?php esc_html_e('Título visible', 'flacso-main-page'); ?></label>
                    <input 
                        type="text" 
                        id="card_<?php echo esc_attr($index); ?>_title" 
                        name="posgrados[cards][<?php echo esc_attr($index); ?>][title]" 
                        class="regular-text" 
                        value="<?php echo esc_attr($card['title'] ?? ''); ?>">
                </div>

                <div class="flacso-form-group">
                    <label for="card_<?php echo esc_attr($index); ?>_type"><?php esc_html_e('Etiqueta', 'flacso-main-page'); ?></label>
                    <input 
                        type="text" 
                        id="card_<?php echo esc_attr($index); ?>_type" 
                        name="posgrados[cards][<?php echo esc_attr($index); ?>][type]" 
                        class="regular-text" 
                        value="<?php echo esc_attr($card['type'] ?? ''); ?>">
                </div>

                <div class="flacso-form-group">
                    <label for="card_<?php echo esc_attr($index); ?>_url"><?php esc_html_e('URL', 'flacso-main-page'); ?></label>
                    <input 
                        type="url" 
                        id="card_<?php echo esc_attr($index); ?>_url" 
                        name="posgrados[cards][<?php echo esc_attr($index); ?>][url]" 
                        class="regular-text" 
                        value="<?php echo esc_attr($card['url'] ?? ''); ?>">
                </div>

                <div class="flacso-form-group">
                    <label for="card_<?php echo esc_attr($index); ?>_image"><?php esc_html_e('Imagen (URL)', 'flacso-main-page'); ?></label>
                    <input
                        type="url"
                        id="card_<?php echo esc_attr($index); ?>_image"
                        name="posgrados[cards][<?php echo esc_attr($index); ?>][image]"
                        class="regular-text"
                        value="<?php echo esc_attr($card['image'] ?? ''); ?>"
                        data-preview-target="posgrados_card_<?php echo esc_attr($index); ?>_image">
                    <div class="flacso-image-preview" data-image-preview="posgrados_card_<?php echo esc_attr($index); ?>_image">
                        <?php if (!empty($card['image'])): ?>
                            <img src="<?php echo esc_url($card['image']); ?>" alt="<?php esc_attr_e('Vista previa', 'flacso-main-page'); ?>">
                        <?php else: ?>
                            <span class="flacso-image-placeholder"><?php esc_html_e('Sin imagen', 'flacso-main-page'); ?></span>
                        <?php endif; ?>
                    </div>
                </div>

                <div class="flacso-form-group">
                    <label for="card_<?php echo esc_attr($index); ?>_desc"><?php esc_html_e('Descripción', 'flacso-main-page'); ?></label>
                    <textarea 
                        id="card_<?php echo esc_attr($index); ?>_desc" 
                        name="posgrados[cards][<?php echo esc_attr($index); ?>][desc]" 
                        rows="4"><?php echo esc_textarea($card['desc'] ?? ''); ?></textarea>
                </div>
            </fieldset>
        <?php endforeach;
    }

    private static function render_oferta_academica_section(array $settings): void {
        $oa = Flacso_Main_Page_Settings::get_section('oferta_academica');
        ?>
        <h3><?php esc_html_e('Visibilidad de bloques', 'flacso-main-page'); ?></h3>
        <p class="description"><?php esc_html_e('Controla qué categorías y elementos se muestran dentro del shortcode [oferta_academica].', 'flacso-main-page'); ?></p>
        
        <div class="flacso-form-group">
            <label>
                <input type="checkbox" name="oferta_academica[show_filters]" value="1" <?php checked(!empty($oa['show_filters'])); ?>>
                <?php esc_html_e('Mostrar barra de filtros/ navegación superior', 'flacso-main-page'); ?>
            </label>
        </div>

        <div class="flacso-form-group">
            <label>
                <input type="checkbox" name="oferta_academica[show_maestrias]" value="1" <?php checked(!empty($oa['show_maestrias'])); ?>>
                <?php esc_html_e('Mostrar Maestrías', 'flacso-main-page'); ?>
            </label>
        </div>

        <div class="flacso-form-group">
            <label>
                <input type="checkbox" name="oferta_academica[show_especializaciones]" value="1" <?php checked(!empty($oa['show_especializaciones'])); ?>>
                <?php esc_html_e('Mostrar Especializaciones', 'flacso-main-page'); ?>
            </label>
        </div>

        <div class="flacso-form-group">
            <label>
                <input type="checkbox" name="oferta_academica[show_diplomados]" value="1" <?php checked(!empty($oa['show_diplomados'])); ?>>
                <?php esc_html_e('Mostrar Diplomados', 'flacso-main-page'); ?>
            </label>
        </div>

        <div class="flacso-form-group">
            <label>
                <input type="checkbox" name="oferta_academica[show_diplomas]" value="1" <?php checked(!empty($oa['show_diplomas'])); ?>>
                <?php esc_html_e('Mostrar Diplomas', 'flacso-main-page'); ?>
            </label>
        </div>

        <div class="flacso-form-group">
            <label>
                <input type="checkbox" name="oferta_academica[show_seminarios]" value="1" <?php checked(!empty($oa['show_seminarios'])); ?>>
                <?php esc_html_e('Mostrar Seminarios', 'flacso-main-page'); ?>
            </label>
        </div>

        <h3><?php esc_html_e('Opciones adicionales', 'flacso-main-page'); ?></h3>
        <div class="flacso-form-group">
            <label>
                <input type="checkbox" name="oferta_academica[show_inactivos]" value="1" <?php checked(!empty($oa['show_inactivos'])); ?>>
                <?php esc_html_e('Mostrar programas no vigentes', 'flacso-main-page'); ?>
            </label>
        </div>

        <div class="flacso-form-group">
            <label for="seminarios_limit"><?php esc_html_e('Límite de seminarios', 'flacso-main-page'); ?></label>
            <input 
                type="number" 
                id="seminarios_limit" 
                name="oferta_academica[seminarios_limit]" 
                value="<?php echo esc_attr(intval($oa['seminarios_limit'] ?? 12)); ?>"
                min="1"
                max="50">
        </div>
        <?php
    }

    private static function render_congreso_section(array $settings): void {
        ?>
        <h3><?php esc_html_e('Sección Congreso', 'flacso-main-page'); ?></h3>
        <div class="flacso-form-group">
            <label for="congreso_title"><?php esc_html_e('Título', 'flacso-main-page'); ?></label>
            <input 
                type="text" 
                id="congreso_title" 
                name="congreso[title]" 
                class="regular-text" 
                value="<?php echo esc_attr($settings['congreso']['title'] ?? ''); ?>">
        </div>

        <div class="flacso-form-group">
            <label for="congreso_content"><?php esc_html_e('Contenido', 'flacso-main-page'); ?></label>
            <?php
            $congreso_content = $settings['congreso']['content'] ?? '';
            if (function_exists('wp_editor')) {
                wp_editor(
                    $congreso_content,
                    'congreso_content',
                    [
                        'textarea_name' => 'congreso[content]',
                        'textarea_rows' => 8,
                        'media_buttons' => true,
                        'tinymce' => true,
                        'quicktags' => true,
                    ]
                );
            } else {
                ?>
                <textarea id="congreso_content" name="congreso[content]" rows="6" class="regular-text"><?php echo esc_textarea($congreso_content); ?></textarea>
                <?php
            }
            ?>
        </div>

        <div class="flacso-form-group">
            <label for="congreso_cta_label"><?php esc_html_e('Texto del botón', 'flacso-main-page'); ?></label>
            <input 
                type="text" 
                id="congreso_cta_label" 
                name="congreso[cta_label]" 
                class="regular-text" 
                value="<?php echo esc_attr($settings['congreso']['cta_label'] ?? ''); ?>">
        </div>

        <div class="flacso-form-group">
            <label for="congreso_cta_url"><?php esc_html_e('URL del botón', 'flacso-main-page'); ?></label>
            <input 
                type="url" 
                id="congreso_cta_url" 
                name="congreso[cta_url]" 
                class="regular-text" 
                value="<?php echo esc_attr($settings['congreso']['cta_url'] ?? ''); ?>">
        </div>

        <div class="flacso-form-group">
            <label for="congreso_bg"><?php esc_html_e('Imagen de fondo (URL)', 'flacso-main-page'); ?></label>
            <input 
                type="url" 
                id="congreso_bg" 
                name="congreso[background_image]" 
                class="regular-text" 
                value="<?php echo esc_attr($settings['congreso']['background_image'] ?? ''); ?>"
                data-preview-target="congreso_bg">
            <div class="flacso-image-preview" data-image-preview="congreso_bg">
                <?php if (!empty($settings['congreso']['background_image'])): ?>
                    <img src="<?php echo esc_url($settings['congreso']['background_image']); ?>" alt="<?php esc_attr_e('Vista previa', 'flacso-main-page'); ?>">
                <?php else: ?>
                    <span class="flacso-image-placeholder"><?php esc_html_e('Sin imagen', 'flacso-main-page'); ?></span>
                <?php endif; ?>
            </div>
        </div>
        <?php
    }

    private static function render_quienes_section(array $settings): void {
        ?>
        <h3><?php esc_html_e('Sección ¿Quiénes somos?', 'flacso-main-page'); ?></h3>
        <div class="flacso-form-group">
            <label for="quienes_title"><?php esc_html_e('Título', 'flacso-main-page'); ?></label>
            <input 
                type="text" 
                id="quienes_title" 
                name="quienes[title]" 
                class="regular-text" 
                value="<?php echo esc_attr($settings['quienes']['title'] ?? ''); ?>">
        </div>

        <div class="flacso-form-group">
            <label for="quienes_content"><?php esc_html_e('Contenido', 'flacso-main-page'); ?></label>
            <?php
            $quienes_content = $settings['quienes']['content'] ?? '';
            if (function_exists('wp_editor')) {
                wp_editor(
                    $quienes_content,
                    'quienes_content',
                    [
                        'textarea_name' => 'quienes[content]',
                        'textarea_rows' => 8,
                        'media_buttons' => true,
                        'tinymce' => true,
                        'quicktags' => true,
                    ]
                );
            } else {
                ?>
                <textarea id="quienes_content" name="quienes[content]" rows="6" class="regular-text"><?php echo esc_textarea($quienes_content); ?></textarea>
                <?php
            }
            ?>
        </div>

        <div class="flacso-form-group">
            <label for="quienes_cta_label"><?php esc_html_e('Texto del botón', 'flacso-main-page'); ?></label>
            <input 
                type="text" 
                id="quienes_cta_label" 
                name="quienes[cta_label]" 
                class="regular-text" 
                value="<?php echo esc_attr($settings['quienes']['cta_label'] ?? ''); ?>">
        </div>

        <div class="flacso-form-group">
            <label for="quienes_cta_url"><?php esc_html_e('URL del botón', 'flacso-main-page'); ?></label>
            <input 
                type="url" 
                id="quienes_cta_url" 
                name="quienes[cta_url]" 
                class="regular-text" 
                value="<?php echo esc_attr($settings['quienes']['cta_url'] ?? ''); ?>">
        </div>

        <div class="flacso-form-group">
            <label for="quienes_bg"><?php esc_html_e('Imagen de fondo (URL)', 'flacso-main-page'); ?></label>
            <input 
                type="url" 
                id="quienes_bg" 
                name="quienes[background_image]" 
                class="regular-text" 
                value="<?php echo esc_attr($settings['quienes']['background_image'] ?? ''); ?>"
                data-preview-target="quienes_bg">
            <div class="flacso-image-preview" data-image-preview="quienes_bg">
                <?php if (!empty($settings['quienes']['background_image'])): ?>
                    <img src="<?php echo esc_url($settings['quienes']['background_image']); ?>" alt="<?php esc_attr_e('Vista previa', 'flacso-main-page'); ?>">
                <?php else: ?>
                    <span class="flacso-image-placeholder"><?php esc_html_e('Sin imagen', 'flacso-main-page'); ?></span>
                <?php endif; ?>
            </div>
        </div>

        <div class="flacso-form-group">
            <label for="quienes_color"><?php esc_html_e('Color destacado (hex)', 'flacso-main-page'); ?></label>
            <input 
                type="color" 
                id="quienes_color" 
                name="quienes[highlight_color]" 
                value="<?php echo esc_attr($settings['quienes']['highlight_color'] ?? '#1d3a72'); ?>">
        </div>
        <?php
    }

    private static function render_contacto_section(array $settings): void {
        $contact = $settings['contacto'] ?? [];
        $background_mode = $contact['background_mode'] ?? 'color';
        $background_mode_options = [
            'color' => __('Solo color', 'flacso-main-page'),
            'gradient' => __('Gradiente', 'flacso-main-page'),
            'image' => __('Imagen sin filtro', 'flacso-main-page'),
            'image_overlay' => __('Imagen con filtro', 'flacso-main-page'),
        ];
        ?>
        <h3><?php esc_html_e('Sección Contacto', 'flacso-main-page'); ?></h3>
        <div class="flacso-form-group">
            <label for="contacto_title"><?php esc_html_e('Título', 'flacso-main-page'); ?></label>
            <input 
                type="text" 
                id="contacto_title" 
                name="contacto[title]" 
                class="regular-text" 
                value="<?php echo esc_attr($contact['title'] ?? ''); ?>">
        </div>

        <div class="flacso-form-group">
            <label for="contacto_subtitle"><?php esc_html_e('Subtítulo', 'flacso-main-page'); ?></label>
            <input 
                type="text" 
                id="contacto_subtitle" 
                name="contacto[subtitle]" 
                class="regular-text" 
                value="<?php echo esc_attr($contact['subtitle'] ?? ''); ?>">
        </div>

        <div class="flacso-form-group">
            <label for="contacto_cta_label"><?php esc_html_e('Texto del botón', 'flacso-main-page'); ?></label>
            <input 
                type="text" 
                id="contacto_cta_label" 
                name="contacto[cta_label]" 
                class="regular-text" 
                value="<?php echo esc_attr($contact['cta_label'] ?? ''); ?>">
        </div>

        <div class="flacso-form-group">
            <label for="contacto_cta_url"><?php esc_html_e('URL del botón', 'flacso-main-page'); ?></label>
            <input 
                type="url" 
                id="contacto_cta_url" 
                name="contacto[cta_url]" 
                class="regular-text" 
                value="<?php echo esc_attr($contact['cta_url'] ?? ''); ?>">
        </div>

        <h3><?php esc_html_e('Fondo y estilo visual', 'flacso-main-page'); ?></h3>
        <div class="flacso-form-group">
            <label for="contacto_bg_mode"><?php esc_html_e('Modo de fondo', 'flacso-main-page'); ?></label>
            <select id="contacto_bg_mode" name="contacto[background_mode]">
                <?php foreach ($background_mode_options as $value => $label): ?>
                    <option value="<?php echo esc_attr($value); ?>" <?php selected($background_mode, $value); ?>>
                        <?php echo esc_html($label); ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>

        <div class="flacso-form-group">
            <label for="contacto_bg_color"><?php esc_html_e('Color base o de respaldo', 'flacso-main-page'); ?></label>
            <input 
                type="color" 
                id="contacto_bg_color" 
                name="contacto[background_color]" 
                value="<?php echo esc_attr($contact['background_color'] ?? '#f2f6ff'); ?>">
        </div>
        <?php
    }

    /**
     * UI mejorada para gestionar visibilidad, orden y colores de secciones.
     */
    private static function render_secciones_section_ui(array $settings): void {
        $sections_visibility = Flacso_Main_Page_Settings::get_section_visibility();
        $heading_color = $settings['section_heading_color'] ?? 'primary';
        $heading_colors = $settings['section_heading_colors'] ?? [];
        $heading_color_choices = [
            'primary'  => __('Color institucional (palette1)', 'flacso-main-page'),
            'palette7' => __('Claro (palette7)', 'flacso-main-page'),
        ];
        ?>
        <p><?php esc_html_e('Activa únicamente las secciones que deben mostrarse en la página principal.', 'flacso-main-page'); ?></p>

        <h3><?php esc_html_e('Visibilidad de secciones', 'flacso-main-page'); ?></h3>
        <?php foreach (Flacso_Main_Page_Settings::get_section_visibility_defaults() as $section_key => $default): ?>
            <div class="flacso-form-group">
                <label>
                    <input type="checkbox"
                           name="secciones[sections_visibility][<?php echo esc_attr($section_key); ?>]"
                           value="1"
                           <?php checked(!empty($sections_visibility[$section_key])); ?>>
                    <span><?php echo esc_html(Flacso_Main_Page_Settings::get_section_label($section_key)); ?></span>
                </label>
            </div>
        <?php endforeach; ?>

        <h3><?php esc_html_e('Color de encabezados por sección', 'flacso-main-page'); ?></h3>
        <p class="description">
            <?php esc_html_e('Elige el color que deben firmar los encabezados de cada sección (el hero mantiene su configuración propia).', 'flacso-main-page'); ?>
        </p>
        <div class="flacso-form-group">
            <label for="section_heading_color"><?php esc_html_e('Color global de encabezados', 'flacso-main-page'); ?></label>
            <select id="section_heading_color" name="secciones[section_heading_color]">
                <?php foreach ($heading_color_choices as $value => $label): ?>
                    <option value="<?php echo esc_attr($value); ?>" <?php selected($heading_color, $value); ?>>
                        <?php echo esc_html($label); ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>

        <div class="flacso-section-colors-grid">
            <?php foreach (Flacso_Main_Page_Settings::get_homepage_section_order_defaults() as $section_key): ?>
                <?php if ($section_key === 'hero') { continue; } ?>
                <div class="flacso-form-group">
                    <label for="heading_color_<?php echo esc_attr($section_key); ?>">
                        <?php echo esc_html(Flacso_Main_Page_Settings::get_section_label($section_key)); ?>
                    </label>
                    <select id="heading_color_<?php echo esc_attr($section_key); ?>"
                            name="secciones[section_heading_colors][<?php echo esc_attr($section_key); ?>]">
                        <option value=""><?php esc_html_e('Usar color global', 'flacso-main-page'); ?></option>
                        <?php foreach ($heading_color_choices as $value => $label): ?>
                            <option value="<?php echo esc_attr($value); ?>" <?php selected($heading_colors[$section_key] ?? '', $value); ?>>
                                <?php echo esc_html($label); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
            <?php endforeach; ?>
        </div>
        <?php
    }
}
