<?php

declare(strict_types=1);

define('ABSPATH', __DIR__ . '/');

$registered = [];
function add_action($hook, $callback, $priority = 10, $accepted_args = 1): void {
    global $registered;
    $registered[$hook] = [$callback, $priority, $accepted_args];
}
function get_post_type($post_id): string {
    return $post_id === 101 ? 'cohorte' : 'page';
}

require_once __DIR__ . '/../modules/preinscripciones/includes/class-preinscriptions-cache.php';

FLACSO_Preinscriptions_Cache::init();

foreach (['save_post_cohorte', 'save_post_edicion', 'save_post_oferta-academica'] as $hook) {
    if (!isset($registered[$hook])) {
        throw new RuntimeException("Missing cache invalidation hook: {$hook}");
    }
}

foreach (['added_post_meta', 'updated_post_meta', 'deleted_post_meta'] as $hook) {
    if (!isset($registered[$hook]) || $registered[$hook][2] !== 4) {
        throw new RuntimeException("Missing four-argument meta hook: {$hook}");
    }
}

echo "OK preinscriptions-cache-contract-test\n";
