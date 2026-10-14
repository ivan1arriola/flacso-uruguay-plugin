<?php
/** Exercise the real query helpers without bootstrapping WordPress admin hooks. */
function featured_assert($ok, $message) {
    if (!$ok) { fwrite(STDERR, "FAIL: {$message}\n"); exit(1); }
}
function get_option($key, $default = []) { return $GLOBALS['sticky_ids']; }
function absint($value) { return abs((int) $value); }
function get_posts($args) { return $GLOBALS['sticky_ids']; }
class WP_Query {
    public $posts;
    public function __construct($args) {
        $GLOBALS['query_args'] = $args;
        $ids = isset($args['post__in']) ? $args['post__in'] : array_diff(range(1, 12), $args['post__not_in']);
        $this->posts = array_map(static fn($id) => (object) ['ID' => $id], array_values($ids));
        if ($args['posts_per_page'] !== -1) { $this->posts = array_slice($this->posts, 0, $args['posts_per_page']); }
    }
    public function have_posts() { return !empty($this->posts); }
}
$source = file_get_contents(__DIR__ . '/../modules/main-page/sections/novedades-section.php');
$start = strpos($source, "if (!function_exists('flacso_section_novedades_get_sticky_posts')) {");
$end = strpos($source, "if (!function_exists('flacso_section_novedades_toggle_sticky_ajax')) {", $start);
eval(substr($source, $start, $end - $start));
$start = strpos($source, "if (!function_exists('flacso_section_novedades_get_posts')) {");
eval(substr($source, $start));
foreach ([0, 1, 2, 3, 4, 5, 8] as $count) {
    $GLOBALS['sticky_ids'] = $count ? range(1, $count) : [];
    $featured = flacso_section_novedades_get_sticky_posts(-1);
    featured_assert(count($featured) === $count, 'devuelve todos los destacados: ' . $count);
    if ($count) {
        featured_assert($GLOBALS['query_args']['posts_per_page'] === -1, 'consulta sin límite');
        featured_assert($GLOBALS['query_args']['post_status'] === 'publish' && $GLOBALS['query_args']['category_name'] === 'novedades', 'solo novedades publicadas');
        featured_assert(count(flacso_section_novedades_get_sticky_posts(2)) === min(2, $count), 'conserva el límite de otros consumidores');
    }
    $latest = flacso_section_novedades_get_posts(6, 'novedades', 1);
    featured_assert(count($latest['posts']) <= 6, 'lista cronológica de hasta seis noticias');
    featured_assert(!array_intersect(array_column($latest['posts'], 'ID'), $GLOBALS['sticky_ids']), 'los destacados no se repiten abajo');
}
echo "OK featured query: 0, 1, 2, 3, 4, 5 y 8 destacados, límites y exclusión\n";
