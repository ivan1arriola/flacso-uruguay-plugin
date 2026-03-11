<?php
if (!defined('ABSPATH')) exit;

if (!defined('DP_POSGRADO_ROOT_PAGE_ID')) {
    define('DP_POSGRADO_ROOT_PAGE_ID', 12261);
}

if (!defined('DP_POSGRADO_EXCLUDED_BRANCH_IDS')) {
    define('DP_POSGRADO_EXCLUDED_BRANCH_IDS', [12349]);
}

// Función para obtener el nombre completo de un docente
// Nota: Se define en helpers.php del core si no existe
// Esta es una versión alternativa con más opciones
if (!function_exists('dp_nombre_completo_extended')) {
    function dp_nombre_completo_extended($docente_id, $with_complete_prefix=false){
        $prefijo_abrev = get_post_meta($docente_id, 'prefijo_abrev', true);
        $prefijo_full  = get_post_meta($docente_id, 'prefijo_full', true);
        $nombre        = get_post_meta($docente_id, 'nombre', true);
        $apellido      = get_post_meta($docente_id, 'apellido', true);

        if(!$nombre && !$apellido) return get_the_title($docente_id);

        $prefijo = $with_complete_prefix ? $prefijo_full : $prefijo_abrev;
        $partes = array_filter([$prefijo, $nombre, $apellido]);
        return implode(' ', $partes);
    }
}

// Alias compatible para código que usa la versión extendida
if (!function_exists('dp_nombre_completo')) {
    function dp_nombre_completo($docente_id, $with_complete_prefix=false) {
        return dp_nombre_completo_extended($docente_id, $with_complete_prefix);
    }
}

// Función para obtener el color de un equipo (persistente)
function get_equipo_color($term_id) {
    $color = get_term_meta($term_id, 'equipo_docente_color', true);

    if ($color) {
        return $color; // Color definido manualmente
    }

    // Si no hay color guardado → generar uno determinista por slug
    $term  = get_term($term_id);
    $slug  = $term ? $term->slug : 'default';

    // Hash del slug → convertir en número
    $hash  = crc32($slug);

    // Paleta fija
    $colors = [
        '#0073aa', '#46b450', '#d54e21', '#ffb900',
        '#7928a1', '#dd9933', '#00a0d2', '#e91e63', '#009688'
    ];

    // Seleccionar color según hash
    $index = $hash % count($colors);

    return $colors[$index];
}


// Función para generar color aleatorio basado en las iniciales (consistente)
function generar_color_avatar($nombre) {
    $colores = [
        '#0d6efd', '#6610f2', '#6f42c1', '#d63384', '#dc3545',
        '#fd7e14', '#ffc107', '#198754', '#20c997', '#0dcaf0'
    ];
    
    // Generar índice basado en el nombre para consistencia (usando intval para evitar float)
    $hash = crc32($nombre);
    $indice = abs($hash) % count($colores);
    return $colores[$indice];
}

// Función para generar gradiente basado en el color
function generar_gradiente($color_base) {
    $gradientes = [
        "linear-gradient(135deg, {$color_base} 0%, " . ajustar_luminosidad($color_base, -20) . " 100%)",
        "linear-gradient(135deg, {$color_base} 0%, " . ajustar_luminosidad($color_base, -30) . " 100%)",
        "linear-gradient(135deg, " . ajustar_luminosidad($color_base, 10) . " 0%, {$color_base} 100%)"
    ];
    
    $hash = crc32($color_base);
    $indice = abs($hash) % count($gradientes);
    return $gradientes[$indice];
}

// Función auxiliar para ajustar luminosidad
function ajustar_luminosidad($hex, $percent) {
    $hex = str_replace('#', '', $hex);
    $r = hexdec(substr($hex, 0, 2));
    $g = hexdec(substr($hex, 2, 2));
    $b = hexdec(substr($hex, 4, 2));

    // Usar intval para evitar floats
    $r = intval(max(0, min(255, $r + $r * $percent / 100)));
    $g = intval(max(0, min(255, $g + $g * $percent / 100)));
    $b = intval(max(0, min(255, $b + $b * $percent / 100)));

    return '#' . str_pad(dechex($r), 2, '0', 0) . 
                 str_pad(dechex($g), 2, '0', 0) . 
                 str_pad(dechex($b), 2, '0', 0);
}

if (!function_exists('dp_color_from_string')) {
function dp_color_from_string($string, $s = 70, $l = 55) {
    $hash = abs(crc32((string) $string));
    $hue  = $hash % 360;
    $s = max(0, min(100, (int) $s));
    $l = max(0, min(100, (int) $l));

    $c = (1 - abs(2 * ($l / 100) - 1)) * ($s / 100);
    $x = $c * (1 - abs(fmod($hue / 60, 2) - 1));
    $m = ($l / 100) - ($c / 2);

    $r = $g = $b = 0;

    if ($hue < 60) {
        $r = $c; $g = $x; $b = 0;
    } elseif ($hue < 120) {
        $r = $x; $g = $c; $b = 0;
    } elseif ($hue < 180) {
        $r = 0; $g = $c; $b = $x;
    } elseif ($hue < 240) {
        $r = 0; $g = $x; $b = $c;
    } elseif ($hue < 300) {
        $r = $x; $g = 0; $b = $c;
    } else {
        $r = $c; $g = 0; $b = $x;
    }

    $R = sprintf('%02x', floor(($r + $m) * 255));
    $G = sprintf('%02x', floor(($g + $m) * 255));
    $B = sprintf('%02x', floor(($b + $m) * 255));

    return "#{$R}{$G}{$B}";
}
}

if (!function_exists('dp_adjust_brightness')) {
function dp_adjust_brightness($hex, $percent = 0) {
    return ajustar_luminosidad($hex, $percent);
}
}

if (!function_exists('dp_gradiente_from_color')) {
function dp_gradiente_from_color($hex) {
    $darker = dp_adjust_brightness($hex, -12);
    return "linear-gradient(135deg, {$hex} 0%, {$darker} 100%)";
}
}

if (!function_exists('dp_iniciales')) {
function dp_iniciales($nombre, $apellido, $fallback = 'DP') {
    $nombre = trim((string) $nombre);
    $apellido = trim((string) $apellido);

    if ($nombre !== '' && $apellido !== '') {
        $first = function_exists('mb_substr') ? mb_substr($nombre, 0, 1) : substr($nombre, 0, 1);
        $last  = function_exists('mb_substr') ? mb_substr($apellido, 0, 1) : substr($apellido, 0, 1);
        return strtoupper($first . $last);
    }

    if ($nombre !== '') {
        $chunk = function_exists('mb_substr') ? mb_substr($nombre, 0, 2) : substr($nombre, 0, 2);
        return strtoupper($chunk);
    }

    return strtoupper($fallback);
}
}

if (!function_exists('dp_avatar_markup')) {
function dp_avatar_markup($post_id, $titulo, $size_px = 160, $class_extra = '') {
    $size_px = max(72, (int) $size_px);
    $classes = trim('dp-docente-avatar ' . $class_extra);
    $style   = sprintf('width:%dpx;height:%dpx;', $size_px, $size_px);

    if (has_post_thumbnail($post_id)) {
        $img = get_the_post_thumbnail(
            $post_id,
            'medium',
            [
                'class'    => 'dp-docente-avatar__img',
                'style'    => $style,
                'alt'      => esc_attr($titulo),
                'loading'  => 'lazy',
                'decoding' => 'async',
            ]
        );

        if ($img) {
            return sprintf('<div class="%s" style="%s">%s</div>', esc_attr($classes), esc_attr($style), $img);
        }
    }

    $nombre   = get_post_meta($post_id, 'nombre', true);
    $apellido = get_post_meta($post_id, 'apellido', true);
    $fallback = $titulo ? (function_exists('mb_substr') ? mb_substr($titulo, 0, 2) : substr($titulo, 0, 2)) : 'DP';
    $iniciales = dp_iniciales($nombre, $apellido, $fallback);
    $color     = dp_color_from_string($titulo ?: ($nombre . $apellido));
    $gradient  = dp_gradiente_from_color($color);

    return sprintf(
        '<div class="%1$s flacso-docente-card__initials" style="%2$sbackground:%3$s;">%4$s</div>',
        esc_attr($classes),
        esc_attr($style),
        esc_attr($gradient),
        esc_html($iniciales)
    );
}
}

if (!function_exists('dp_safe_cv_html')) {
function dp_safe_cv_html($html) {
    $allowed = [
        'p'           => ['class' => [], 'style' => []],
        'br'          => [],
        'ul'          => ['class' => [], 'style' => []],
        'ol'          => ['class' => [], 'style' => []],
        'li'          => ['class' => [], 'style' => []],
        'strong'      => [],
        'em'          => [],
        'b'           => [],
        'i'           => [],
        'u'           => [],
        'h3'          => ['class' => []],
        'h4'          => ['class' => []],
        'h5'          => ['class' => []],
        'blockquote'  => ['class' => [], 'style' => []],
        'span'        => ['class' => [], 'style' => []],
        'a'           => ['href' => [], 'target' => [], 'rel' => [], 'class' => []],
    ];

    return wp_kses($html, $allowed);
}
}
function dp_get_docente_emails($docente_id) {
    $correos = get_post_meta($docente_id, 'docente_correos', true);
    if (!is_array($correos)) {
        return [];
    }

    $limpios = [];
    foreach ($correos as $correo) {
        $email = isset($correo['email']) ? sanitize_email($correo['email']) : '';
        if (!$email) continue;
        $limpios[] = [
            'email' => $email,
            'label' => isset($correo['label']) ? sanitize_text_field($correo['label']) : '',
            'principal' => !empty($correo['principal']),
        ];
    }
    return $limpios;
}

function dp_get_docente_principal_email($docente_id) {
    $correos = dp_get_docente_emails($docente_id);
    foreach ($correos as $correo) {
        if (!empty($correo['principal'])) {
            return $correo;
        }
    }
    return isset($correos[0]) ? $correos[0] : null;
}

function dp_get_docente_socials($docente_id) {
    $redes = get_post_meta($docente_id, 'docente_redes', true);
    if (!is_array($redes)) {
        return [];
    }

    $limpias = [];
    foreach ($redes as $red) {
        $url = isset($red['url']) ? esc_url($red['url']) : '';
        if (!$url) continue;
        $limpias[] = [
            'label' => isset($red['label']) ? sanitize_text_field($red['label']) : '',
            'url' => $url,
        ];
    }
    return $limpias;
}

function dp_get_equipo_page_id($term_id) {
    return (int) get_term_meta($term_id, 'equipo_docente_page_id', true);
}

function dp_get_equipo_page($term_id) {
    $page_id = dp_get_equipo_page_id($term_id);
    if (!$page_id) {
        return null;
    }
    $page = get_post($page_id);
    if (!$page || $page->post_status !== 'publish') {
        return null;
    }
    return $page;
}

if (!function_exists('dp_get_equipo_relacion_nombre')) {
function dp_get_equipo_relacion_nombre($term_id, $fallback = '') {
    $label = get_term_meta($term_id, 'equipo_docente_relacion_nombre', true);
    $label = is_string($label) ? trim($label) : '';

    if ($label !== '') {
        return $label;
    }

    if ($fallback !== '') {
        return $fallback;
    }

    $term = get_term($term_id);
    if ($term && !is_wp_error($term)) {
        return $term->name;
    }

    return '';
}
}

function dp_get_equipo_page_data($term_id) {
    $page = dp_get_equipo_page($term_id);
    if (!$page) {
        return null;
    }

    return [
        'id' => $page->ID,
        'title' => get_the_title($page),
        'permalink' => get_permalink($page),
        'excerpt' => wp_trim_words(has_excerpt($page) ? $page->post_excerpt : wp_strip_all_tags($page->post_content), 30),
        'thumbnail' => get_the_post_thumbnail_url($page, 'large'),
        'modified' => get_post_modified_time(get_option('date_format'), false, $page->ID, true),
    ];
}

if (!function_exists('dp_get_equipo_term_ids_by_page')) {
function dp_get_equipo_term_ids_by_page($page_id) {
    $page_id = (int) $page_id;
    if (!$page_id) {
        return [];
    }

    $terms = get_terms([
        'taxonomy'   => 'equipo-docente',
        'hide_empty' => false,
        'fields'     => 'ids',
        'orderby'    => 'term_id',
        'order'      => 'ASC',
        'meta_query' => [
            [
                'key'   => 'equipo_docente_page_id',
                'value' => $page_id,
            ],
        ],
    ]);

    if (is_wp_error($terms) || empty($terms)) {
        return [];
    }

    return array_values(array_map('intval', $terms));
}
}

if (!function_exists('dp_get_equipo_term_id_by_page')) {
function dp_get_equipo_term_id_by_page($page_id) {
    $page_id = (int) $page_id;
    static $cache = [];

    if (!$page_id) {
        return 0;
    }

    if (isset($cache[$page_id])) {
        return $cache[$page_id];
    }

    $terms = dp_get_equipo_term_ids_by_page($page_id);
    if (empty($terms)) {
        $cache[$page_id] = 0;
        return 0;
    }

    foreach ($terms as $term_id) {
        $auto_sync = get_term_meta($term_id, 'equipo_docente_autosync', true);
        if (!empty($auto_sync)) {
            $cache[$page_id] = (int) $term_id;
            return $cache[$page_id];
        }
    }

    $cache[$page_id] = (int) $terms[0];
    return $cache[$page_id];
}
}

if (!function_exists('dp_page_has_ancestor_in_list')) {
function dp_page_has_ancestor_in_list($page_id, array $ancestor_ids) {
    $page_id = (int) $page_id;
    if (!$page_id || empty($ancestor_ids)) {
        return false;
    }

    if (in_array($page_id, $ancestor_ids, true)) {
        return true;
    }

    $ancestors = get_post_ancestors($page_id);
    if (empty($ancestors)) {
        return false;
    }

    return !empty(array_intersect($ancestor_ids, $ancestors));
}
}

if (!function_exists('dp_get_posgrado_program_tree')) {
function dp_get_posgrado_program_tree() {
    $root_id = (int) apply_filters('dp_posgrado_root_page_id', defined('DP_POSGRADO_ROOT_PAGE_ID') ? DP_POSGRADO_ROOT_PAGE_ID : 0);
    $excluded = (array) apply_filters('dp_posgrado_excluded_branch_ids', defined('DP_POSGRADO_EXCLUDED_BRANCH_IDS') ? DP_POSGRADO_EXCLUDED_BRANCH_IDS : []);

    $cache_key = $root_id . ':' . implode(',', $excluded);
    static $cache = [];
    if (isset($cache[$cache_key])) {
        return $cache[$cache_key];
    }

    if (!$root_id) {
        $cache[$cache_key] = [];
        return $cache[$cache_key];
    }

    $excluded = array_map('intval', $excluded);

    $categories = get_pages([
        'parent' => $root_id,
        'post_type' => 'page',
        'post_status' => ['publish'],
        'sort_column' => 'menu_order,post_title',
        'hierarchical' => 0,
        'number' => 0,
    ]);

    $tree = [];

    foreach ($categories as $category) {
        if (in_array($category->ID, $excluded, true) || dp_page_has_ancestor_in_list($category->ID, $excluded)) {
            continue;
        }

        $programs = get_pages([
            'parent' => $category->ID,
            'post_type' => 'page',
            'post_status' => ['publish'],
            'sort_column' => 'menu_order,post_title',
            'hierarchical' => 0,
            'number' => 0,
        ]);

        if (!$programs) {
            continue;
        }

        $programs = array_values(array_filter($programs, function($page) use ($excluded) {
            return !dp_page_has_ancestor_in_list($page->ID, $excluded);
        }));

        if (!$programs) {
            continue;
        }

        $tree[] = [
            'category' => $category,
            'pages' => $programs,
        ];
    }

    $tree = apply_filters('dp_posgrado_program_tree', $tree, $root_id, $excluded);
    $cache[$cache_key] = $tree;

    return $cache[$cache_key];
}
}

if (!function_exists('dp_posgrado_tree_contains_page')) {
function dp_posgrado_tree_contains_page(array $tree, $page_id) {
    $page_id = (int) $page_id;
    if (!$page_id || empty($tree)) {
        return false;
    }

    foreach ($tree as $branch) {
        if (empty($branch['pages'])) {
            continue;
        }
        foreach ($branch['pages'] as $page) {
            if ((int) $page->ID === $page_id) {
                return true;
            }
        }
    }

    return false;
}
}
