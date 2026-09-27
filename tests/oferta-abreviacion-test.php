<?php
// tests/oferta-abreviacion-test.php
$root = dirname(__DIR__);

if (!defined('ABSPATH')) {
    define('ABSPATH', $root . '/');
}

if (!class_exists('WP_Post')) {
    class WP_Post {
        public int $ID = 0;
        public string $post_type = 'oferta-academica';
        public function __construct(int $id = 0) {
            $this->ID = $id;
        }
    }
}

// Mocks de WordPress
$GLOBALS['mock_post_meta'] = [];
$GLOBALS['mock_transients'] = [];

if (!function_exists('sanitize_title')) {
    function sanitize_title($str) {
        $str = strtolower(trim($str));
        $str = preg_replace('/[^a-z0-9_\-]+/', '-', $str);
        return trim($str, '-');
    }
}
if (!function_exists('sanitize_text_field')) {
    function sanitize_text_field($str) {
        return trim(strip_tags((string)$str));
    }
}
if (!function_exists('sanitize_email')) {
    function sanitize_email($str) {
        return filter_var((string)$str, FILTER_SANITIZE_EMAIL);
    }
}
if (!function_exists('absint')) {
    function absint($val) {
        return abs((int)$val);
    }
}
if (!function_exists('wp_kses_post')) {
    function wp_kses_post($val) {
        return (string)$val;
    }
}
if (!function_exists('esc_url_raw')) {
    function esc_url_raw($val) {
        return (string)$val;
    }
}
if (!function_exists('esc_html')) {
    function esc_html($val) {
        return htmlspecialchars((string)$val, ENT_QUOTES, 'UTF-8');
    }
}
if (!function_exists('__')) {
    function __($text, $domain = 'default') {
        return $text;
    }
}
if (!function_exists('get_post_meta')) {
    function get_post_meta($id, $key, $single = false) {
        $val = $GLOBALS['mock_post_meta'][$id][$key] ?? null;
        if ($val === null) {
            return $single ? '' : [];
        }
        return $single ? $val : [$val];
    }
}
if (!function_exists('update_post_meta')) {
    function update_post_meta($id, $key, $value) {
        $GLOBALS['mock_post_meta'][$id][$key] = $value;
        return true;
    }
}
if (!function_exists('delete_post_meta')) {
    function delete_post_meta($id, $key) {
        unset($GLOBALS['mock_post_meta'][$id][$key]);
        return true;
    }
}
if (!function_exists('set_transient')) {
    function set_transient($key, $value, $expiration = 0) {
        $GLOBALS['mock_transients'][$key] = $value;
        return true;
    }
}
if (!function_exists('get_transient')) {
    function get_transient($key) {
        return $GLOBALS['mock_transients'][$key] ?? false;
    }
}
if (!function_exists('delete_transient')) {
    function delete_transient($key) {
        unset($GLOBALS['mock_transients'][$key]);
        return true;
    }
}
if (!function_exists('wp_unslash')) {
    function wp_unslash($v) {
        return $v;
    }
}
if (!function_exists('wp_verify_nonce')) {
    function wp_verify_nonce($n, $a) {
        return true;
    }
}
if (!function_exists('wp_is_post_revision')) {
    function wp_is_post_revision($id) {
        return false;
    }
}
if (!function_exists('current_user_can')) {
    function current_user_can($cap, ...$args) {
        return true;
    }
}
if (!function_exists('get_posts')) {
    function get_posts($args) {
        $results = [];
        $key = $args['meta_query'][0]['key'] ?? '';
        $val = $args['meta_query'][0]['value'] ?? '';
        $exclude = $args['exclude'] ?? [];
        foreach ($GLOBALS['mock_post_meta'] as $pid => $meta) {
            if (in_array($pid, $exclude, true)) continue;
            if (isset($meta[$key]) && $meta[$key] === $val) {
                $results[] = (object)['ID' => $pid];
            }
        }
        return $results;
    }
}

require_once $root . '/modules/oferta-academica/includes/class-oferta-academica.php';
require_once $root . '/modules/oferta-academica/includes/class-oferta-admin-fields.php';

function test_assert(bool $cond, string $msg): void {
    if (!$cond) {
        fwrite(STDERR, "FAIL: {$msg}\n");
        exit(1);
    }
}

// 1. Normalización
test_assert(FLACSO_Oferta_Academica::normalize_abbreviation('DAVIA') === 'davia', 'DAVIA -> davia');
test_assert(FLACSO_Oferta_Academica::normalize_abbreviation('  Mg. Ed  ') === 'mg-ed', 'Mg. Ed -> mg-ed');
test_assert(FLACSO_Oferta_Academica::normalize_abbreviation('') === '', 'vacio -> vacio');

// 2. Unicidad
$GLOBALS['mock_post_meta'][100] = ['abreviacion' => 'davia'];
test_assert(FLACSO_Oferta_Academica::is_abbreviation_available('davia', 100) === true, 'davia disponible para su propio post');
test_assert(FLACSO_Oferta_Academica::is_abbreviation_available('davia', 101) === false, 'davia ocupada para post 101');
test_assert(FLACSO_Oferta_Academica::is_abbreviation_available('mesyp', 101) === true, 'mesyp libre');

// 3. Admin Fields Save - Guardado con abreviación disponible normalizada
$_POST = [
    'flacso_oferta_academica_nonce' => 'valid_nonce',
    'flacso_oferta' => [
        'abreviacion' => ' MESYP ',
    ],
];
FLACSO_Oferta_Admin_Fields::save(101, new WP_Post(101));
test_assert(($GLOBALS['mock_post_meta'][101]['abreviacion'] ?? '') === 'mesyp', 'save() normaliza y guarda abreviación única');

// 4. Admin Fields Save - Colisión de abreviación no sobrescribe y setea transient
$_POST = [
    'flacso_oferta_academica_nonce' => 'valid_nonce',
    'flacso_oferta' => [
        'abreviacion' => 'DAVIA', // Ya pertenece al post 100
    ],
];
FLACSO_Oferta_Admin_Fields::save(101, new WP_Post(101));
test_assert(($GLOBALS['mock_post_meta'][101]['abreviacion'] ?? '') === 'mesyp', 'save() no sobrescribe abreviación en colisión');
test_assert(isset($GLOBALS['mock_transients']['flacso_oferta_abbr_error_101']), 'save() genera transient de error en colisión');

// 5. Admin Fields Save - Abreviación vacía elimina meta
$_POST = [
    'flacso_oferta_academica_nonce' => 'valid_nonce',
    'flacso_oferta' => [
        'abreviacion' => '   ',
    ],
];
FLACSO_Oferta_Admin_Fields::save(101, new WP_Post(101));
test_assert(!isset($GLOBALS['mock_post_meta'][101]['abreviacion']), 'save() con abreviación vacía elimina el meta');

// 6. Admin notices - renderiza y limpia transient
$GLOBALS['mock_transients']['flacso_oferta_abbr_error_101'] = 'La abreviación "davia" ya está en uso por otra oferta académica.';
$GLOBALS['post'] = new WP_Post(101);
ob_start();
FLACSO_Oferta_Admin_Fields::render_admin_notices();
$output = ob_get_clean();
test_assert(strpos($output, 'notice-error') !== false, 'render_admin_notices() imprime notice-error');
test_assert(strpos($output, 'davia') !== false, 'render_admin_notices() contiene mensaje de error');
test_assert(!isset($GLOBALS['mock_transients']['flacso_oferta_abbr_error_101']), 'render_admin_notices() elimina el transient');

echo "OK oferta-abreviacion-test\n";
