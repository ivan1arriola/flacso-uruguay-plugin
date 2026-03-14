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

    // Bloque: docentes-grupo
    $docentes_grupo_dir = plugin_dir_path(__FILE__) . '../blocks/docentes-grupo/';
    $docentes_grupo_url = plugins_url('../blocks/docentes-grupo/', __FILE__);

    wp_register_script(
        'dp-docentes-grupo-block',
        $docentes_grupo_url . 'index.js',
        ['wp-blocks','wp-element','wp-editor','wp-block-editor','wp-components','wp-data','wp-core-data','wp-server-side-render'],
        filemtime($docentes_grupo_dir . 'index.js')
    );

    if (file_exists($docentes_grupo_dir . 'style.css')) {
        wp_register_style(
            'dp-docentes-grupo-style',
            $docentes_grupo_url . 'style.css',
            [],
            filemtime($docentes_grupo_dir . 'style.css')
        );
    }

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

    // Bloque: Docentes grupo (cards consistentes + CV visible)
    register_block_type(plugin_dir_path(__FILE__) . '../blocks/docentes-grupo', [
        'editor_script'   => 'dp-docentes-grupo-block',
        'style'           => 'dp-docentes-grupo-style',
        'editor_style'    => 'dp-docentes-grupo-style',
        'render_callback' => 'dp_docentes_grupo_block_render'
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
    $attributes = wp_parse_args((array) $attributes, [
        'docenteIds' => [],
        'limit' => 0,
    ]);

    $docente_ids = array_values(array_unique(array_filter(array_map('absint', (array) $attributes['docenteIds']))));
    $limit = (int) $attributes['limit'];

    $args = [
        'post_type' => 'docente',
        'post_status' => 'publish',
        'posts_per_page' => ($limit > 0) ? $limit : -1,
        'meta_key' => 'apellido',
        'orderby' => [
            'meta_value' => 'ASC',
            'title' => 'ASC',
        ],
        'no_found_rows' => true,
    ];

    if (!empty($docente_ids)) {
        $args['post__in'] = $docente_ids;
        $args['orderby'] = 'post__in';
    }

    $q = new WP_Query($args);
    if (!$q->have_posts()) {
        return dp_docentes_wrap_output('<p class="alert alert-info" role="status">No hay docentes disponibles.</p>');
    }

    ob_start();
    echo '<div class="docentes-lista-completa" role="list" aria-label="' . esc_attr__('Listado de docentes', 'flacso-posgrados-docentes') . '">';

    $i = 0;
    while ($q->have_posts()) {
        $q->the_post();
        $i++;
        $id = get_the_ID();
        $titulo = function_exists('dp_nombre_completo') ? dp_nombre_completo($id) : get_the_title($id);
        $pref_abrev = get_post_meta($id, 'prefijo_abrev', true);
        $titulo_meta = get_post_meta($id, 'titulo', true);
        $pref = $pref_abrev ?: $titulo_meta;
        $nombre = get_post_meta($id, 'nombre', true);
        $apellido = get_post_meta($id, 'apellido', true);
        $display_name = trim(($nombre ?: '') . ' ' . ($apellido ?: ''));
        if ($display_name === '') {
            $display_name = $titulo;
        }
        $cv_raw = get_post_meta($id, 'cv', true);
        $img_col_order = ($i % 2 === 0) ? 'order-md-2' : 'order-md-1';
        $text_col_order = ($i % 2 === 0) ? 'order-md-1' : 'order-md-2';
        $h_id = 'doc-list-h-' . $id;
        $cv_id = 'doc-list-cv-' . $id;
        ?>
        <article class="card border-0 shadow-sm mb-5 hover-lift" role="listitem" aria-labelledby="<?php echo esc_attr($h_id); ?>" aria-describedby="<?php echo esc_attr($cv_id); ?>">
            <div class="card-body p-4">
                <div class="row g-4 align-items-center">
                    <div class="col-md-3 text-center <?php echo esc_attr($img_col_order); ?>">
                        <?php echo dp_avatar_markup($id, $display_name, 190, 'shadow-lg border border-2 border-white'); ?>
                    </div>
                    <div class="col-md-9 <?php echo esc_attr($text_col_order); ?>">
                        <div class="d-flex justify-content-between align-items-start flex-wrap gap-2 mb-2">
                            <div>
                                <h3 id="<?php echo esc_attr($h_id); ?>" class="mb-1"><?php echo esc_html($display_name); ?></h3>
                                <?php if ($pref): ?>
                                    <p class="text-muted small mb-0"><?php echo esc_html($pref); ?></p>
                                <?php endif; ?>
                            </div>
                            <div class="d-flex gap-2">
                                <a href="<?php echo esc_url(get_permalink($id)); ?>" class="btn btn-outline-secondary btn-sm" aria-label="<?php echo esc_attr(sprintf(__('Ver perfil de %s', 'flacso-posgrados-docentes'), $display_name)); ?>">
                                    <i class="bi bi-chevron-right" aria-hidden="true"></i><span class="visually-hidden"><?php esc_html_e('Ver perfil', 'flacso-posgrados-docentes'); ?></span>
                                </a>
                                <?php if (current_user_can('edit_post', $id)): ?>
                                    <a href="<?php echo esc_url(get_edit_post_link($id, '')); ?>" target="_blank" rel="noopener" class="btn btn-sm btn-palette2 d-print-none" aria-label="<?php echo esc_attr(sprintf(__('Editar docente: %s', 'flacso-posgrados-docentes'), $display_name)); ?>">
                                        <i class="bi bi-pencil me-1" aria-hidden="true"></i><span aria-hidden="true"><?php esc_html_e('Editar docente', 'flacso-posgrados-docentes'); ?></span>
                                    </a>
                                <?php endif; ?>
                            </div>
                        </div>
                        <?php if ($cv_raw): ?>
                            <div id="<?php echo esc_attr($cv_id); ?>" class="cv-completo" style="line-height:1.65">
                                <?php
                                    $cv_html = (strpos($cv_raw, '<p>') === false) ? wpautop($cv_raw) : $cv_raw;
                                    echo dp_safe_cv_html($cv_html);
                                ?>
                            </div>
                        <?php else: ?>
                            <p class="text-muted"><em><?php esc_html_e('No hay informacion curricular disponible.', 'flacso-posgrados-docentes'); ?></em></p>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </article>
        <?php
    }

    echo '</div>';
    wp_reset_postdata();

    return dp_docentes_wrap_output(ob_get_clean());
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

if (!function_exists('dp_docentes_grupo_block_render')) {
function dp_docentes_grupo_block_render($attributes = []) {
    if (wp_style_is('dp-docentes-grupo-style', 'registered')) {
        wp_enqueue_style('dp-docentes-grupo-style');
    }

    $attributes = wp_parse_args((array) $attributes, [
        'title' => 'Docentes',
        'level' => 'h2',
        'docenteIds' => [],
    ]);

    $title = (string) $attributes['title'];
    $level = strtolower((string) $attributes['level']);
    if (!in_array($level, ['h2', 'h3', 'h4', 'h5', 'h6'], true)) {
        $level = 'h2';
    }

    $ids = array_values(array_unique(array_filter(array_map('absint', (array) $attributes['docenteIds']))));

    $allowed_cv_tags = [
        'p' => [], 'br' => [],
        'ul' => [], 'ol' => [], 'li' => [],
        'strong' => [], 'em' => [], 'b' => [], 'i' => [],
        'h3' => [], 'h4' => [], 'h5' => [],
        'a' => ['href' => [], 'target' => [], 'rel' => []],
    ];

    $mb_substr_safe = static function ($value, $start, $length = null) {
        if (function_exists('mb_substr')) {
            return (null === $length) ? mb_substr($value, $start) : mb_substr($value, $start, $length);
        }
        return (null === $length) ? substr($value, $start) : substr($value, $start, $length);
    };


    $build_avatar = static function ($post_id, $alt, $inic) {
        if (has_post_thumbnail($post_id)) {
            $img_id = get_post_thumbnail_id($post_id);
            return wp_get_attachment_image($img_id, 'medium', false, [
                'class' => 'fdc-avatar-img',
                'alt' => esc_attr($alt),
                'loading' => 'lazy',
                'decoding' => 'async',
            ]);
        }
        return '<div class="fdc-avatar-fallback" aria-hidden="true">' . esc_html($inic) . '</div>';
    };

    $build_card = static function ($id) use ($allowed_cv_tags, $mb_substr_safe, $build_avatar) {
        $nombre = (string) get_post_meta($id, 'nombre', true);
        $apellido = (string) get_post_meta($id, 'apellido', true);
        $base = trim($nombre . ' ' . $apellido);
        if ($base === '') {
            $base = get_the_title($id) ?: (string) get_post_field('post_title', $id);
        }

        $titulo_full = trim((string) get_post_meta($id, '_docente_titulo', true));
        if ($titulo_full === '') {
            $titulo_full = trim((string) get_post_meta($id, 'titulo', true));
        }

        $prefijo = trim((string) get_post_meta($id, 'prefijo', true));
        $display_name = $prefijo ? trim($prefijo . ' ' . $base) : $base;

        $cv_raw = trim((string) get_post_meta($id, 'cv', true));
        $cv_html = $cv_raw !== '' ? wp_kses(wpautop($cv_raw), $allowed_cv_tags) : '';

        $inic = 'FL';
        if ($nombre !== '' && $apellido !== '') {
            $inic = $mb_substr_safe($nombre, 0, 1) . $mb_substr_safe($apellido, 0, 1);
        } elseif ($nombre !== '') {
            $inic = $mb_substr_safe($nombre, 0, 2);
        } elseif ($base !== '') {
            $parts = array_values(array_filter(preg_split('/\s+/', $base)));
            if (count($parts) >= 2) {
                $inic = $mb_substr_safe($parts[0], 0, 1) . $mb_substr_safe($parts[1], 0, 1);
            } else {
                $inic = $mb_substr_safe($base, 0, 2);
            }
        }
        $inic = strtoupper((string) $inic);

        $label_id = 'fdc-doc-' . (int) $id . '-' . wp_rand(1000, 9999);
        $can_edit = current_user_can('edit_post', $id);
        $edit_url = $can_edit ? get_edit_post_link($id, 'raw') : '';

        ob_start();
        ?>
        <article class="fdc-card" aria-labelledby="<?php echo esc_attr($label_id); ?>">
            <div class="fdc-top">
                <div class="fdc-avatar">
                    <?php echo $build_avatar($id, $display_name, $inic); ?>
                </div>
                <div class="fdc-meta">
                    <h3 class="fdc-name" id="<?php echo esc_attr($label_id); ?>">
                        <?php echo esc_html($display_name); ?>
                    </h3>

                    <?php if ($titulo_full !== '') : ?>
                        <div class="fdc-title-full"><?php echo esc_html($titulo_full); ?></div>
                    <?php endif; ?>

                    <?php if ($can_edit && $edit_url) : ?>
                        <a class="fdc-edit" href="<?php echo esc_url($edit_url); ?>" target="_blank" rel="noopener noreferrer">
                            <?php esc_html_e('Editar', 'flacso-posgrados-docentes'); ?>
                        </a>
                    <?php endif; ?>
                </div>
            </div>

            <?php if ($cv_html !== '') : ?>
                <div class="fdc-cv">
                    <?php echo $cv_html; ?>
                </div>
            <?php endif; ?>
        </article>
        <?php
        return ob_get_clean();
    };

    ob_start();
    ?>
    <section class="flacso-doc-consistente" aria-label="<?php esc_attr_e('Docentes', 'flacso-posgrados-docentes'); ?>">
        <div class="fdc-wrap">
            <div class="fdc-title">
                <<?php echo esc_html($level); ?>>
                    <?php echo esc_html(wp_strip_all_tags($title)); ?>
                </<?php echo esc_html($level); ?>>
            </div>

            <?php if (!$ids) : ?>
                <div style="padding:22px 18px;text-align:center;color:#666;"><?php esc_html_e('No se seleccionaron docentes.', 'flacso-posgrados-docentes'); ?></div>
            <?php else : ?>
                <?php
                $q = new WP_Query([
                    'post_type'      => 'docente',
                    'post_status'    => 'publish',
                    'posts_per_page' => min(200, count($ids)),
                    'post__in'       => $ids,
                    'orderby'        => 'post__in',
                    'no_found_rows'  => true,
                ]);
                ?>
                <?php if (!$q->have_posts()) : ?>
                    <div style="padding:22px 18px;text-align:center;color:#666;"><?php esc_html_e('No se encontraron docentes.', 'flacso-posgrados-docentes'); ?></div>
                <?php else : ?>
                    <div class="fdc-grid">
                        <?php while ($q->have_posts()) : $q->the_post(); ?>
                            <?php echo $build_card(get_the_ID()); ?>
                        <?php endwhile; wp_reset_postdata(); ?>
                    </div>
                <?php endif; ?>
            <?php endif; ?>
        </div>
    </section>
    <?php

    return function_exists('dp_docentes_wrap_output') ? dp_docentes_wrap_output(ob_get_clean()) : ob_get_clean();
}
}





