<?php
if (!defined('ABSPATH')) exit;

if (!defined('DP_POSGRADO_ROOT_PAGE_ID')) {
    define('DP_POSGRADO_ROOT_PAGE_ID', 12261);
}

if (!defined('DP_POSGRADO_EXCLUDED_BRANCH_IDS')) {
    define('DP_POSGRADO_EXCLUDED_BRANCH_IDS', [12349]);
}

// Función para obtener el nombre completo de un docente
if (!function_exists('dp_nombre_completo')) {
    function dp_nombre_completo($docente_id, $with_complete_prefix=false){
        $prefijo_abrev = get_post_meta($docente_id, 'prefijo_abrev', true);
        $titulo        = get_post_meta($docente_id, 'titulo', true);
        $nombre        = get_post_meta($docente_id, 'nombre', true);
        $apellido      = get_post_meta($docente_id, 'apellido', true);

        if(!$nombre && !$apellido) return get_the_title($docente_id);

        $prefijo = $with_complete_prefix ? $titulo : $prefijo_abrev;
        if (function_exists('dp_strip_prefix_from_name')) {
            $nombre = dp_strip_prefix_from_name($nombre, $prefijo);
        }
        $partes = array_filter([$prefijo, $nombre, $apellido]);
        return implode(' ', $partes);
    }
}

if (!function_exists('dp_strip_prefix_from_name')) {
    function dp_strip_prefix_from_name($nombre, $prefijo) {
        $nombre = trim((string) $nombre);
        $prefijo = trim((string) $prefijo);

        if ($nombre === '' || $prefijo === '') {
            return $nombre;
        }

        $prefijo_base = preg_replace('/[\s\.]+$/u', '', $prefijo);
        if ($prefijo_base === '') {
            return $nombre;
        }

        $pattern = '/^' . preg_quote($prefijo_base, '/') . '\.?\s+/iu';
        return (string) preg_replace($pattern, '', $nombre, 1);
    }
}

/* get_equipo_color removed */


// Función para generar color aleatorio basado en las iniciales (consistente)

// Función para generar gradiente basado en el color

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

/* team-page mapping functions removed */

if (!function_exists('dp_page_has_ancestor_in_list')) {
}

if (!function_exists('dp_get_posgrado_program_tree')) {
}

if (!function_exists('dp_posgrado_tree_contains_page')) {
}
