<?php
/**
 * F0 runtime-only inspection for a verified staging WordPress instance.
 *
 * Usage:
 *   FLACSO_F0_STAGING=1 wp eval-file .github/scripts/f0_runtime_probe.php > f0-runtime.json
 *
 * Never prints option values, personal records, cron arguments or credentials.
 * REFUSES production. Must not be invoked as part of normal plugin bootstrap.
 */
if (!defined('ABSPATH') || !defined('WP_CLI') || !WP_CLI) {
    fwrite(STDERR, "F0 probe requires WP-CLI in an initialized WordPress site.\n");
    exit(1);
}

if (getenv('FLACSO_F0_STAGING') !== '1') {
    WP_CLI::error('Set FLACSO_F0_STAGING=1 only on a verified staging instance.');
}

$environment = function_exists('wp_get_environment_type') ? wp_get_environment_type() : 'unknown';
if ($environment === 'production' || $environment === 'unknown') {
    WP_CLI::error('Refusing production or unknown WP environment type. Configure WP_ENVIRONMENT_TYPE=staging.');
}

global $wp_version, $wp_post_types, $wp_taxonomies, $shortcode_tags, $wp_meta_keys;
$cpts = array();
foreach ((array) $wp_post_types as $name => $type) {
    if (in_array($name, array('post', 'page', 'attachment', 'revision', 'nav_menu_item', 'custom_css', 'customize_changeset'), true)) {
        continue;
    }
    $cpts[] = array(
        'slug' => (string) $name,
        'public' => (bool) $type->public,
        'show_in_rest' => (bool) $type->show_in_rest,
    );
}
usort($cpts, static function ($a, $b) { return strcmp($a['slug'], $b['slug']); });

$taxonomies = array_keys((array) $wp_taxonomies);
sort($taxonomies, SORT_STRING);

$registered_meta = array();
foreach ((array) $wp_meta_keys as $object_type => $subtypes) {
    foreach ((array) $subtypes as $subtype => $keys) {
        foreach (array_keys((array) $keys) as $key) {
            $registered_meta[] = array(
                'object_type' => (string) $object_type,
                'subtype' => (string) $subtype,
                'key' => (string) $key,
            );
        }
    }
}
usort($registered_meta, static function ($a, $b) {
    return strcmp(json_encode($a), json_encode($b));
});

$shortcodes = array_keys((array) $shortcode_tags);
sort($shortcodes, SORT_STRING);

$routes = array_keys(rest_get_server()->get_routes());
sort($routes, SORT_STRING);

$cron_hooks = array();
if (function_exists('_get_cron_array')) {
    foreach ((array) _get_cron_array() as $jobs) {
        foreach ((array) $jobs as $hook => $_events) {
            $cron_hooks[$hook] = true;
        }
    }
}
$cron_names = array_keys($cron_hooks);
sort($cron_names, SORT_STRING);

$payload = array(
    'scope' => 'staging-runtime-metadata-only',
    'wordpress_version' => $wp_version,
    'php_version' => PHP_VERSION,
    'php_sapi' => PHP_SAPI,
    'wp_environment_type' => $environment,
    'registered_cpts' => $cpts,
    'registered_taxonomies' => $taxonomies,
    'registered_meta_keys' => $registered_meta,
    'registered_shortcodes' => $shortcodes,
    'registered_rest_paths' => $routes,
    'scheduled_cron_hook_names' => $cron_names,
    'limitations' => array(
        'No HTTP calls, no write operations, no schema inspection and no application data.',
        'Rest paths alone do not capture permission_callback, allowed methods or consumers.',
        'Hook and API availability vary by plugins enabled on staging.',
    ),
);
echo wp_json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
echo "\n";
