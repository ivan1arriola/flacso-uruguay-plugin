<?php
declare(strict_types=1);
$source = file_get_contents(__DIR__ . '/../modules/main-page/includes/class-flacso-homepage-admin-v2.php');
function verify_home_admin(bool $condition, string $message): void {
    if (!$condition) { fwrite(STDERR, "FAIL: $message\n"); exit(1); }
}
verify_home_admin(str_contains($source, 'Imagen principal de la portada'), 'direct photo editor shown');
verify_home_admin(str_contains($source, "add_action('admin_enqueue_scripts', [__CLASS__, 'enqueue_media'])"), 'media loading runs during enqueue phase');
verify_home_admin(str_contains($source, 'public static function enqueue_media(): void'), 'media loader available');
verify_home_admin(str_contains($source, 'wp_enqueue_media()'), 'media library available');
verify_home_admin(str_contains($source, "wp_nonce_field(self::SAVE_HERO_ACTION)"), 'nonce in photo form');
verify_home_admin(str_contains($source, "check_admin_referer(self::SAVE_HERO_ACTION)"), 'nonce verified on save');
verify_home_admin(str_contains($source, "wp_attachment_is_image"), 'image validation on save');
verify_home_admin(str_contains($source, "FLACSO_Home_Campaign::active()"), 'active campaign is resolved');
verify_home_admin(str_contains($source, 'update_post_meta($posted_id, $meta_key, $id)'), 'campaign photos persisted');
verify_home_admin(str_contains($source, 'set_post_thumbnail($posted_id, $id)'), 'direct photo editor synchronizes featured image');
verify_home_admin(str_contains($source, 'Editar texto y fotografías del hero'), 'clear direct edit link');
verify_home_admin(str_contains($source, 'Cambiar fotografía del hero'), 'photo editor accessible without campaign jargon');
verify_home_admin(str_contains($source, "Flacso_Main_Page_Settings::invalidate_cache()"), 'fallback cache invalidated');
echo "Homepage image admin contract OK\n";
