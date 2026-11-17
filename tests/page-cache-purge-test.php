<?php
/** El gancho de caché purga por opciones públicas, una sola vez por request, y nunca por opciones internas. */

define('ABSPATH', __DIR__ . '/');

$GLOBALS['shutdown_callbacks'] = [];
$GLOBALS['purges'] = 0;
function add_action(string $hook, $callback, int $priority = 10, int $args = 1): void {
    if ($hook === 'shutdown') {
        $GLOBALS['shutdown_callbacks'][] = $callback;
    }
}

require dirname(__DIR__) . '/includes/core/class-flacso-page-cache.php';

function cache_assert(bool $condition, string $message): void {
    if (!$condition) { fwrite(STDERR, "FAIL: {$message}\n"); exit(1); }
}

foreach (['flacso-main-page_settings', 'flacso_carta_telefono', 'flacso_nav_announcement_message', 'sticky_posts', 'flacso_meta_pixel_id', 'theme_mods_kadence-child-flacso'] as $option) {
    cache_assert(FLACSO_Page_Cache::affects_public_pages($option), "{$option} cambia el HTML público y debe purgar");
}
// Opciones que se escriben en cada request o por el worker: purgarían todo el tiempo.
foreach (['flacso_meta_queue_schema', 'flacso_inquiry_delivery_worker_lock', 'flacso_meta_last_test_result', 'flacso_mailer_history', 'flacso_pdf_audit_state', '_transient_flacso_x', 'cron', 'flacso_academic_assistant_role_version'] as $option) {
    cache_assert(!FLACSO_Page_Cache::affects_public_pages($option), "{$option} no debe purgar la caché");
}

// Guardar la carta actualiza muchas opciones: una sola purga al terminar el request.
FLACSO_Page_Cache::on_option_change('flacso_carta_telefono');
FLACSO_Page_Cache::on_option_change('flacso_carta_pago_pesos');
FLACSO_Page_Cache::on_option_change('flacso_meta_queue_schema');
cache_assert(count($GLOBALS['shutdown_callbacks']) === 1, 'una sola purga diferida por request');

// Sin WP Super Cache activo no hay nada que vaciar y no debe fallar.
cache_assert(FLACSO_Page_Cache::purge() === false, 'sin el plugin de caché la purga se omite');

// Se declara recién ahora: antes de este punto el plugin de caché no existe.
eval('function wp_cache_clear_cache() { $GLOBALS["purges"]++; }');
cache_assert(FLACSO_Page_Cache::purge() === true && $GLOBALS['purges'] === 1, 'con el plugin de caché activo vacía la caché');

echo "OK page cache purge: opciones públicas, una purga por request, sin purgas por opciones internas\n";
