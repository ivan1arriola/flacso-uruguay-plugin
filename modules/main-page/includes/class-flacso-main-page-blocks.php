<?php

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Registra bloques equivalentes a los shortcodes para permitir su inserción con vista previa.
 */
class Flacso_Main_Page_Blocks {
    public static function init(): void {
        add_filter('block_categories_all', [__CLASS__, 'register_category'], 10, 2);
        self::register_editor_assets();
        self::register_blocks();
        add_filter('allowed_block_types_all', [__CLASS__, 'restrict_blocks_for_oferta'], 20, 2);
    }

    /**
     * Añade la categoría "FLACSO Uruguay" al inserter para agrupar los bloques del plugin.
     */
    public static function register_category(array $categories, $editor_context): array {
        $exists = array_filter($categories, static function ($category) {
            return isset($category['slug']) && 'flacso-uruguay' === $category['slug'];
        });

        if (!$exists) {
            $categories[] = [
                'slug'  => 'flacso-uruguay',
                'title' => __('FLACSO Uruguay', 'flacso-main-page'),
                'icon'  => null,
            ];
        }

        return $categories;
    }

    /**
     * Registra un bloque dinámico por cada shortcode principal.
     */
    public static function register_blocks(): void {
        if (!function_exists('register_block_type')) {
            return;
        }

        foreach (self::get_blocks_map() as $block_name => $config) {
            if (isset($config['register']) && $config['register'] === false) {
                continue;
            }
            register_block_type($block_name, [
                'api_version'    => 2,
                'title'          => $config['title'],
                'description'    => $config['description'],
                'category'       => 'flacso-uruguay',
                'icon'           => $config['icon'],
                'keywords'       => $config['keywords'],
                'attributes'     => $config['attributes'] ?? [],
                'supports'       => [
                    'html'      => false,
                    'align'     => ['full', 'wide'],
                    'inserter'  => true,
                    'multiple'  => true,
                    'reusable'  => true,
                ],
                'editor_script'  => 'flacso-shortcode-blocks',
                'render_callback' => static function ($attributes = []) use ($config) {
                    return Flacso_Main_Page_Blocks::render_shortcode_block($config['shortcode'], $attributes);
                },
            ]);
        }
    }

    /**
     * Renderiza el contenido del bloque ejecutando el shortcode correspondiente.
     */
    private static function render_shortcode_block(string $shortcode, array $attributes): string {
        if (!shortcode_exists($shortcode)) {
            return '';
        }

        $shortcode_string = self::build_shortcode_string($shortcode, $attributes);
        return do_shortcode($shortcode_string);
    }

    /**
     * Construye la cadena de shortcode a partir de los atributos recibidos.
     */
    private static function build_shortcode_string(string $shortcode, array $attributes): string {
        if (empty($attributes)) {
            return sprintf('[%s]', $shortcode);
        }

        $parts = [];
        foreach ($attributes as $key => $value) {
            if (null === $value || '' === $value) {
                continue;
            }

            if (is_bool($value)) {
                $value = $value ? 'true' : 'false';
            } elseif (is_array($value)) {
                $value = implode(',', array_map('sanitize_text_field', $value));
            }

            $parts[] = sprintf('%s="%s"', sanitize_key($key), esc_attr((string) $value));
        }

        $attr_string = $parts ? ' ' . implode(' ', $parts) : '';
        return sprintf('[%s%s]', $shortcode, $attr_string);
    }

    /**
     * Devuelve la definición de los bloques/shortcodes disponibles.
     */
    private static function get_shortcodes_map(): array {
        return [
            'flacso-uruguay/homepage-builder' => [
                'title'       => __('Página principal modular', 'flacso-main-page'),
                'description' => __('Construye el home completo con hero full-width, novedades con buscador AJAX, eventos, Instagram y secciones institucionales.', 'flacso-main-page'),
                'shortcode'   => 'flacso_homepage_builder',
                'icon'        => 'layout',
                'keywords'    => ['home', 'landing', 'flacso'],
            ],
            'flacso-uruguay/oferta-academica' => [
                'title'       => __('Oferta académica', 'flacso-main-page'),
                'description' => __('Hero, navegación y cards unificadas para toda la oferta.', 'flacso-main-page'),
                'shortcode'   => 'oferta_academica',
                'icon'        => 'portfolio',
                'keywords'    => ['oferta', 'academica', 'programas'],
                'attributes'  => [
                    'mostrar_filtros' => [
                        'type'    => 'boolean',
                        'default' => true,
                        'label'   => __('Mostrar navegación', 'flacso-main-page'),
                    ],
                    'mostrar_inactivos' => [
                        'type'    => 'boolean',
                        'default' => false,
                        'label'   => __('Incluir programas no vigentes', 'flacso-main-page'),
                    ],
                ],
            ],
            'flacso-uruguay/lista-seminarios' => [
                'title'       => __('Lista de seminarios', 'flacso-main-page'),
                'description' => __('Tarjetas agrupadas por mes con los seminarios publicados.', 'flacso-main-page'),
                'shortcode'   => 'lista_seminarios',
                'icon'        => 'schedule',
                'keywords'    => ['seminarios', 'lista', 'cursos'],
                'attributes'  => [
                    'posts_per_page' => [
                        'type'    => 'number',
                        'default' => -1,
                        'label'   => __('Cantidad de seminarios', 'flacso-main-page'),
                    ],
                    'category' => [
                        'type'    => 'number',
                        'default' => 156,
                        'label'   => __('ID de categoría', 'flacso-main-page'),
                    ],
                    'mostrar_fechas' => [
                        'type'    => 'boolean',
                        'default' => true,
                        'label'   => __('Mostrar fechas', 'flacso-main-page'),
                    ],
                    'mostrar_boton' => [
                        'type'    => 'boolean',
                        'default' => true,
                        'label'   => __('Mostrar botón', 'flacso-main-page'),
                    ],
                    'texto_boton' => [
                        'type'    => 'string',
                        'default' => __('Ver más información', 'flacso-main-page'),
                        'label'   => __('Texto del botón', 'flacso-main-page'),
                    ],
                    'programa' => [
                        'type'    => 'string',
                        'default' => '',
                        'label'   => __('Filtrar por programa', 'flacso-main-page'),
                    ],
                ],
            ],
            'flacso-uruguay/preguntas-frecuentes' => [
                'title'       => __('Preguntas frecuentes / contacto', 'flacso-main-page'),
                'description' => __('Bloque de consulta con CTA y enlace al correo.', 'flacso-main-page'),
                'shortcode'   => 'preguntas_frecuentes',
                'icon'        => 'editor-help',
                'keywords'    => ['faq', 'contacto', 'soporte'],
                'attributes'  => [
                    'titulo' => [
                        'type'    => 'string',
                        'default' => __('Quedamos a tu disposición para despejar cualquier duda', 'flacso-main-page'),
                        'label'   => __('Título', 'flacso-main-page'),
                    ],
                    'descripcion' => [
                        'type'    => 'string',
                        'default' => __('¿Querés saber más sobre contenidos, fechas o modalidades? Escribinos y coordinamos enseguida.', 'flacso-main-page'),
                        'label'   => __('Descripción', 'flacso-main-page'),
                    ],
                    'cta_url' => [
                        'type'    => 'string',
                        'default' => 'https://flacso.edu.uy/preguntas-frecuentes/',
                        'label'   => __('URL del botón principal', 'flacso-main-page'),
                    ],
                    'cta_label' => [
                        'type'    => 'string',
                        'default' => __('Ver preguntas frecuentes', 'flacso-main-page'),
                        'label'   => __('Texto del botón principal', 'flacso-main-page'),
                    ],
                    'mail' => [
                        'type'    => 'string',
                        'default' => 'inscripciones@flacso.edu.uy',
                        'label'   => __('Correo de contacto', 'flacso-main-page'),
                    ],
                    'mail_label' => [
                        'type'    => 'string',
                        'default' => __('Escribinos por correo', 'flacso-main-page'),
                        'label'   => __('Texto del botón secundario', 'flacso-main-page'),
                    ],
                    'background_image' => [
                        'type'    => 'string',
                        'default' => 'https://flacso.edu.uy/wp-content/uploads/2023/08/library-background.jpg',
                        'label'   => __('Imagen de fondo', 'flacso-main-page'),
                    ],
                ],
            ],
            'flacso-uruguay/listar-paginas' => [
                'title'       => __('Listado de páginas (posgrados)', 'flacso-main-page'),
                'description' => __('Grid táctil que muestra los posgrados hijos de una página padre.', 'flacso-main-page'),
                'shortcode'   => 'listar_paginas',
                'icon'        => 'index-card',
                'keywords'    => ['posgrados', 'grid', 'paginas'],
                'register'    => false,
                'style'       => 'flacso-listar-paginas-block-style',
                'editor_style'=> 'flacso-listar-paginas-block-editor-style',
                'editor_script'=> 'flacso-listar-paginas-block-editor',
                'attributes'  => [
                    'padre' => [
                        'type'    => 'string',
                        'default' => '',
                        'label'   => __('Nombre de la página padre', 'flacso-main-page'),
                    ],
                    'padre_id' => [
                        'type'    => 'string',
                        'default' => '',
                        'label'   => __('ID de la página padre', 'flacso-main-page'),
                    ],
                    'posts_per_page' => [
                        'type'    => 'number',
                        'default' => -1,
                        'label'   => __('Cantidad de páginas a mostrar', 'flacso-main-page'),
                    ],
                    'mostrar_inactivos' => [
                        'type'    => 'boolean',
                        'default' => false,
                        'label'   => __('Mostrar programas no vigentes', 'flacso-main-page'),
                    ],
                ],
            ],
            'flacso-uruguay/convenios-responsivos' => [
                'title'       => __('Convenios responsivos (mobile)', 'flacso-main-page'),
                'description' => __('Listado filtrable de convenios con buscador y tarjetas optimizadas para dispositivos pequeños.', 'flacso-main-page'),
                'shortcode'   => 'convenios_responsivos',
                'icon'        => 'megaphone',
                'keywords'    => ['convenios', 'mobile', 'responsive'],
            ],
            'flacso-uruguay/formulario-consulta-ofertas' => [
                'title'       => __('Formulario “Solicita Información” (Oferta Académica)', 'flacso-main-page'),
                'description' => __('Bloque específico para las páginas de oferta académica: captura la consulta, envía confirmación automática y permite botón opcional de preinscripción. Usa el shortcode [Consultas_Fase_1].', 'flacso-main-page'),
                'shortcode'   => 'Consultas_Fase_1',
                'icon'        => 'email',
                'keywords'    => ['solicita', 'información', 'oferta', 'consulta', 'preinscripción'],
                'attributes'  => [
                    'mostrar_preinscripcion' => [
                        'type'    => 'boolean',
                        'default' => true,
                        'label'   => __('Mostrar botón de Preinscripción', 'flacso-main-page'),
                    ],
                ],
            ],
        ];
    }

    public static function get_blocks_map(): array {
        return self::get_shortcodes_map();
    }

    private static function get_flacso_block_names(): array {
        return array_keys(self::get_blocks_map());
    }

    public static function restrict_blocks_for_oferta($allowed, $context): array|bool {
        $post_type = null;
        if (is_array($context)) {
            $post_type = $context['post_type'] ?? null;
        } elseif (is_object($context) && property_exists($context, 'post_type')) {
            $post_type = $context->post_type;
        }

        if ('oferta-academica' !== $post_type) {
            return $allowed;
        }

        if (true === $allowed) {
            if (!class_exists('WP_Block_Type_Registry')) {
                return $allowed;
            }
            $registry = WP_Block_Type_Registry::get_instance();
            $allowed = array_keys($registry->get_all_registered());
        }

        if (!is_array($allowed)) {
            return $allowed;
        }

        $restricted = array_values(array_diff($allowed, self::get_flacso_block_names()));
        return $restricted;
    }

    public static function register_editor_assets(): void {
        if (isset($GLOBALS['flacso_shortcode_blocks_script_registered'])) {
            return;
        }

        wp_register_script(
            'flacso-shortcode-blocks',
            FLACSO_MAIN_PAGE_MODULE_URL . 'assets/js/flacso-blocks.js',
            ['wp-blocks', 'wp-element', 'wp-components', 'wp-editor', 'wp-i18n', 'wp-server-side-render'],
            FLACSO_MAIN_PAGE_VERSION,
            true
        );

        $blocks = [];
        foreach (self::get_blocks_map() as $name => $config) {
            if (isset($config['register']) && $config['register'] === false) {
                continue;
            }
            $blocks[] = [
                'name'        => $name,
                'title'       => $config['title'],
                'description' => $config['description'],
                'icon'        => $config['icon'],
                'category'       => 'flacso-uruguay',
                'keywords'    => $config['keywords'],
                'supports'    => $config['supports'] ?? [
                    'html'     => false,
                    'align'    => ['full', 'wide'],
                    'inserter' => true,
                ],
                'attributes'  => $config['attributes'] ?? [],
            ];
        }

        wp_localize_script('flacso-shortcode-blocks', 'flacsoShortcodeBlocks', [
            'blocks' => $blocks,
        ]);

        $GLOBALS['flacso_shortcode_blocks_script_registered'] = true;
    }
}









