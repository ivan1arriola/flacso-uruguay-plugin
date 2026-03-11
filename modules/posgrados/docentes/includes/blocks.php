<?php
if (!defined('ABSPATH')) exit;

add_filter('block_categories_all', function($categories) {
    $slugs = wp_list_pluck($categories, 'slug');
    $custom = [
        'flacso-uruguay' => __('FLACSO Uruguay', 'flacso-posgrados-docentes'),
    ];

    foreach ($custom as $slug => $label) {
        if (!in_array($slug, $slugs, true)) {
            $categories[] = [
                'slug'  => $slug,
                'title' => $label,
            ];
        }
    }

    return $categories;
});

add_action('init', function() {
    // Legacy block docentes-equipo intentionally disabled.

    // Registrar script del bloque cv-docente
    wp_register_script(
        'dp-cv-docente-block',
        plugins_url('../blocks/cv-docente/index.js', __FILE__),
        ['wp-blocks','wp-element','wp-editor','wp-components','wp-data','wp-server-side-render'],
        filemtime(plugin_dir_path(__FILE__) . '../blocks/cv-docente/index.js')
    );

    // Registrar bloque docente-resumen
    $docente_resumen_dir = plugin_dir_path(__FILE__) . '../blocks/docente-resumen/';
    $docente_resumen_url = plugins_url('../blocks/docente-resumen/', __FILE__);

    wp_register_script(
        'dp-docente-resumen-block',
        plugins_url('../blocks/docente-resumen/index.js', __FILE__),
        ['wp-blocks','wp-element','wp-editor','wp-components','wp-data','wp-server-side-render'],
        filemtime($docente_resumen_dir . 'index.js')
    );

    if (file_exists($docente_resumen_dir . 'style.css')) {
        wp_register_style(
            'dp-docente-resumen-style',
            $docente_resumen_url . 'style.css',
            [],
            filemtime($docente_resumen_dir . 'style.css')
        );
    }

    if (file_exists($docente_resumen_dir . 'editor.css')) {
        wp_register_style(
            'dp-docente-resumen-editor',
            $docente_resumen_url . 'editor.css',
            ['wp-edit-blocks'],
            filemtime($docente_resumen_dir . 'editor.css')
        );
    }

    // Bloque: docentes-lista
    wp_register_script(
        'dp-docentes-lista-block',
        plugins_url('../blocks/docentes-lista/index.js', __FILE__),
        ['wp-blocks','wp-element','wp-editor','wp-components','wp-data','wp-server-side-render'],
        filemtime(plugin_dir_path(__FILE__) . '../blocks/docentes-lista/index.js')
    );

    // Bloque: docente-destacado
    wp_register_script(
        'dp-docente-destacado-block',
        plugins_url('../blocks/docente-destacado/index.js', __FILE__),
        ['wp-blocks','wp-element','wp-editor','wp-components','wp-data','wp-server-side-render'],
        filemtime(plugin_dir_path(__FILE__) . '../blocks/docente-destacado/index.js')
    );

    // Bloque: docente-cv-texto
    wp_register_script(
        'dp-docente-cv-texto-block',
        plugins_url('../blocks/docente-cv-texto/index.js', __FILE__),
        ['wp-blocks','wp-element','wp-editor','wp-components','wp-data','wp-server-side-render'],
        filemtime(plugin_dir_path(__FILE__) . '../blocks/docente-cv-texto/index.js')
    );

    // Bloque: Docentes por equipo (legacy) intentionally disabled.

    // Bloque: CV de docente
    register_block_type(plugin_dir_path(__FILE__) . '../blocks/cv-docente', [
        'editor_script'   => 'dp-cv-docente-block',
        'render_callback' => 'dp_cv_docente_block_render'
    ]);

    // Bloque: Docente destacado/resumen
    register_block_type(plugin_dir_path(__FILE__) . '../blocks/docente-resumen', [
        'editor_script'   => 'dp-docente-resumen-block',
        'style'           => 'dp-docente-resumen-style',
        'editor_style'    => 'dp-docente-resumen-editor',
        'render_callback' => 'dp_docente_resumen_block'
    ]);

    // Bloque: Docentes lista completa
    register_block_type(plugin_dir_path(__FILE__) . '../blocks/docentes-lista', [
        'editor_script'   => 'dp-docentes-lista-block',
        'render_callback' => 'dp_docentes_lista_block'
    ]);

    // Bloque: Docente destacado (rol)
    register_block_type(plugin_dir_path(__FILE__) . '../blocks/docente-destacado', [
        'editor_script'   => 'dp-docente-destacado-block',
        'render_callback' => 'dp_docente_destacado_block'
    ]);

    // Bloque: CV texto
    register_block_type(plugin_dir_path(__FILE__) . '../blocks/docente-cv-texto', [
        'editor_script'   => 'dp-docente-cv-texto-block',
        'render_callback' => 'dp_docente_cv_text_block'
    ]);
});

if (!function_exists('dp_docentes_equipo_bloques')) {
function dp_docentes_equipo_bloques($attributes = [], $content = '', $block = null) {
    if (wp_style_is('dp-docentes-equipo-style', 'registered')) {
        wp_enqueue_style('dp-docentes-equipo-style');
    }

    $attributes = wp_parse_args($attributes, [
        'useCurrentPage' => true,
        'termId' => 0,
        'columns' => 3,
        'pageId' => 0,
    ]);

    $term_id = (int) $attributes['termId'];
    $columns = max(1, min(4, (int) $attributes['columns']));
    $context_page_id = (int) $attributes['pageId'];
    $term_ids = [];

    if (!$context_page_id && $block instanceof WP_Block && !empty($block->context['postId'])) {
        $context_page_id = (int) $block->context['postId'];
    }

    if (!$context_page_id && function_exists('get_the_ID')) {
        $context_page_id = (int) get_the_ID();
    }

    if ($term_id) {
        $term_ids = [$term_id];
    }

    if (!$term_ids && !empty($attributes['useCurrentPage']) && $context_page_id && function_exists('dp_get_equipo_term_ids_by_page')) {
        $term_ids = dp_get_equipo_term_ids_by_page($context_page_id);
    }

    $term_ids = array_values(array_unique(array_filter(array_map('intval', $term_ids))));

    if (!$term_ids) {
        return dp_docentes_wrap_output('<div class="dp-docentes-equipo-block__placeholder">' . esc_html__('No hay un equipo academico asociado a esta pagina.', 'flacso-posgrados-docentes') . '</div>');
    }
    $sections = [];
    $wrapper_classes = sprintf('dp-docentes-equipo-block dp-docentes-equipo-block--cols-%d', $columns);

    foreach ($term_ids as $term_id) {
        $term = get_term($term_id, 'equipo-docente');
        if (!$term || is_wp_error($term)) {
            continue;
        }

        $docentes = get_posts([
            'post_type'      => 'docente',
            'posts_per_page' => -1,
            'orderby'        => 'meta_value',
            'meta_key'       => 'apellido',
            'order'          => 'ASC',
            'tax_query'      => [
                [
                    'taxonomy' => 'equipo-docente',
                    'field'    => 'term_id',
                    'terms'    => $term_id,
                ],
            ],
        ]);

        if (empty($docentes)) {
            if (count($term_ids) === 1) {
                return dp_docentes_wrap_output('<div class="dp-docentes-equipo-block__placeholder">' . esc_html__('No hay integrantes asignados todavia.', 'flacso-posgrados-docentes') . '</div>');
            }
            continue;
        }

        $term_name  = function_exists('dp_get_equipo_relacion_nombre')
            ? dp_get_equipo_relacion_nombre($term_id, $term->name)
            : $term->name;
        $term_color = function_exists('get_equipo_color') ? get_equipo_color($term_id) : '#1d3a72';

        $output  = '<section class="' . esc_attr($wrapper_classes) . '" data-columns="' . esc_attr($columns) . '">';

        if ($term_name) {
            $output .= '<header class="dp-docentes-equipo-block__header">';
            $output .= '<span class="dp-docentes-equipo-block__badge" style="background-color:' . esc_attr($term_color) . '">';
            $output .= esc_html($term_name);
            $output .= '</span>';
            $output .= '</header>';
        }

        $output .= '<div class="dp-docentes-equipo-block__grid">';

        foreach ($docentes as $docente) {
            $nombre   = function_exists('dp_nombre_completo') ? dp_nombre_completo($docente->ID) : get_the_title($docente->ID);
            $prefijo_abrev = get_post_meta($docente->ID, 'prefijo_abrev', true);
            $nombre_meta   = get_post_meta($docente->ID, 'nombre', true);
            $apellido_meta = get_post_meta($docente->ID, 'apellido', true);
            $display_name  = trim(($nombre_meta ?: '') . ' ' . ($apellido_meta ?: ''));
            if ($display_name === '') {
                $display_name = $nombre;
            }
            $resumen  = wp_trim_words(wp_strip_all_tags(get_post_meta($docente->ID, 'cv', true)), 24);
            $avatar   = get_the_post_thumbnail_url($docente->ID, 'medium');
            $correo   = function_exists('dp_get_docente_principal_email') ? dp_get_docente_principal_email($docente->ID) : null;
            $perfil   = get_permalink($docente->ID);

            $output .= '<article class="dp-docentes-equipo-block__item">';
            $output .= '<div class="dp-docentes-equipo-block__avatar">';

            if ($avatar) {
                $output .= '<img src="' . esc_url($avatar) . '" alt="' . esc_attr($nombre) . '">';
            } else {
                $initials = '';
                $segments = array_values(array_filter(preg_split('/\s+/', trim($nombre))));
                if (!empty($segments)) {
                    $first = $segments[0];
                    $last  = $segments[count($segments) - 1];
                    $substr = function ($value) {
                        $value = (string) $value;
                        if (function_exists('mb_substr')) {
                            return mb_substr($value, 0, 1);
                        }
                        return substr($value, 0, 1);
                    };
                    $initials = strtoupper($substr($first) . $substr($last));
                }
                $output .= '<span class="dp-docentes-equipo-block__initials" style="background-color:' . esc_attr($term_color) . '">';
                $output .= esc_html($initials ?: 'D');
                $output .= '</span>';
            }

            $output .= '</div>';
            $output .= '<div class="dp-docentes-equipo-block__body">';
            if ($prefijo_abrev) {
                $output .= '<span class="dp-docentes-equipo-block__abbr">' . esc_html($prefijo_abrev) . '</span>';
            }
            $output .= '<h3 class="dp-docentes-equipo-block__title"><a href="' . esc_url($perfil) . '">' . esc_html($display_name) . '</a></h3>';

            if ($resumen) {
                $output .= '<p class="dp-docentes-equipo-block__excerpt">' . esc_html($resumen) . '</p>';
            }

            if ($correo && !empty($correo['email'])) {
                $label = !empty($correo['label']) ? $correo['label'] . ': ' : '';
                $output .= '<p class="dp-docentes-equipo-block__contact">';
                $output .= esc_html($label) . '<a href="mailto:' . esc_attr($correo['email']) . '">' . esc_html($correo['email']) . '</a>';
                $output .= '</p>';
            }

            $output .= '<div class="dp-docentes-equipo-block__actions">';
            $output .= '<a class="btn btn-primary btn-sm" href="' . esc_url($perfil) . '">' . esc_html__('Ver perfil', 'flacso-posgrados-docentes') . '</a>';
            if (current_user_can('edit_post', $docente->ID)) {
                $output .= '<a class="btn btn-outline-secondary btn-sm" href="' . esc_url(get_edit_post_link($docente->ID, '')) . '">' . esc_html__('Editar docente', 'flacso-posgrados-docentes') . '</a>';
            }
            $output .= '</div>';
            $output .= '</div>';
            $output .= '</article>';
        }

        $output .= '</div></section>';
        $sections[] = $output;
    }

    if (!$sections) {
        return dp_docentes_wrap_output('<div class="dp-docentes-equipo-block__placeholder">' . esc_html__('No hay integrantes asignados todavia.', 'flacso-posgrados-docentes') . '</div>');
    }

    return dp_docentes_wrap_output(implode('', $sections));
}
}

if (!function_exists('dp_docente_resumen_block')) {
function dp_docente_resumen_block($attributes = []) {
    if (!function_exists('flacso_render_docente_profile')) {
        return '';
    }

    $attributes = wp_parse_args($attributes, [
        'docId'      => 0,
        'slug'       => '',
        'headingTag' => 'h3',
        'showAvatar' => true,
    ]);

    $html = flacso_render_docente_profile([
        'docId'      => (int) $attributes['docId'],
        'slug'       => $attributes['slug'],
        'heading'    => $attributes['headingTag'],
        'showAvatar' => !empty($attributes['showAvatar']),
    ]);

    return dp_docentes_wrap_output($html);
}
}

if (!function_exists('dp_docentes_lista_block')) {
function dp_docentes_lista_block($attributes = [], $content = '', $block = null) {
    return dp_docentes_lista_bloques($attributes, $block);
}
}

if (!function_exists('dp_docente_destacado_block')) {
function dp_docente_destacado_block($attributes = []) {
    $html = dp_docente_destacado($attributes);
    return dp_docentes_wrap_output($html);
}
}

if (!function_exists('dp_docente_cv_text_block')) {
function dp_docente_cv_text_block($attributes = []) {
    $html = dp_cv_docente_texto($attributes);
    return dp_docentes_wrap_output('<div class="dp-cv-docente-text-block">' . $html . '</div>');
}
}

if (!function_exists('dp_cv_docente_block_render')) {
function dp_cv_docente_block_render($attributes = []) {
    $html = dp_cv_docente_bloques($attributes);
    return dp_docentes_wrap_output($html);
}
}

if (!function_exists('dp_cv_docente_bloques')) {
function dp_cv_docente_bloques($attributes = []) {
    $attributes = wp_parse_args((array) $attributes, [
        'slug'  => '',
        'docId' => 0,
    ]);

    $slug   = sanitize_title($attributes['slug']);
    $doc_id = absint($attributes['docId']);

    if (!$doc_id && $slug) {
        $doc = get_page_by_path($slug, OBJECT, 'docente');
        if ($doc) {
            $doc_id = $doc->ID;
        }
    }

    if (!$doc_id) {
        return '<div class="dp-cv-docente-block__placeholder">' . esc_html__('Selecciona un perfil en el bloque.', 'flacso-posgrados-docentes') . '</div>';
    }

    $docente = get_post($doc_id);
    if (!$docente || $docente->post_type !== 'docente') {
        return '<div class="dp-cv-docente-block__placeholder">' . esc_html__('El perfil seleccionado no existe.', 'flacso-posgrados-docentes') . '</div>';
    }

    $nombre = function_exists('dp_nombre_completo') ? dp_nombre_completo($docente->ID, true) : get_the_title($docente);
    $cv     = get_post_meta($docente->ID, 'cv', true);

    if (!$cv) {
        $cv = $docente->post_content;
    }

    $cv_html = apply_filters('the_content', $cv);

    $output  = '<article class="dp-cv-docente-block">';
    $output .= '<h2 class="dp-cv-docente-block__title">' . esc_html($nombre) . '</h2>';
    $output .= '<div class="dp-cv-docente-block__content">' . $cv_html . '</div>';
    $output .= '</article>';

    return $output;
}
}





