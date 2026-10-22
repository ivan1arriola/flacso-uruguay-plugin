<?php
/** Behavioral test of the homepage campaign image save path. */
declare(strict_types=1);
define('ABSPATH', __DIR__ . '/../');
function absint($value) { return abs((int) $value); }
function wp_unslash($value) { return $value; }
function sanitize_text_field($value) { return (string) $value; }
function wp_verify_nonce($value, $action) { return $value === 'valid' && $action === 'flacso_home_campaign_images'; }
function wp_is_post_revision($post_id) { return false; }
function current_user_can($capability, $post_id = null) { return true; }
function wp_attachment_is_image($id) { return in_array($id, [21, 22], true); }
function update_post_meta($post_id, $key, $value) { $GLOBALS['meta_saved'][$post_id][$key] = $value; }
function set_post_thumbnail($post_id, $id) { $GLOBALS['thumbnail_saved'][$post_id] = $id; }
function delete_post_thumbnail($post_id) { unset($GLOBALS['thumbnail_saved'][$post_id]); }
require_once __DIR__ . '/../modules/main-page/includes/class-flacso-home-campaign.php';
function expect_image(bool $value, string $label): void {
    if (!$value) { fwrite(STDERR, "FAIL: $label\n"); exit(1); }
}
$_POST = [
    'flacso_home_campaign_images_nonce' => 'valid',
    'flacso_campaign_image_desktop' => '21',
    'flacso_campaign_image_mobile' => '22',
];
FLACSO_Home_Campaign::save_images(88, (object) ['ID' => 88]);
expect_image(($GLOBALS['meta_saved'][88][FLACSO_Home_Campaign::META_DESKTOP_IMAGE] ?? null) === 21, 'desktop photo persisted');
expect_image(($GLOBALS['meta_saved'][88][FLACSO_Home_Campaign::META_MOBILE_IMAGE] ?? null) === 22, 'mobile photo persisted');
expect_image(($GLOBALS['thumbnail_saved'][88] ?? null) === 21, 'desktop photo synchronized with featured image');
$_POST['flacso_campaign_image_desktop'] = '999';
FLACSO_Home_Campaign::save_images(88, (object) ['ID' => 88]);
expect_image(($GLOBALS['meta_saved'][88][FLACSO_Home_Campaign::META_DESKTOP_IMAGE] ?? null) === 21, 'invalid image cannot overwrite desktop');
$_POST['flacso_campaign_image_desktop'] = '0';
FLACSO_Home_Campaign::save_images(88, (object) ['ID' => 88]);
expect_image(($GLOBALS['meta_saved'][88][FLACSO_Home_Campaign::META_DESKTOP_IMAGE] ?? null) === 0, 'desktop image cleared');
expect_image(!isset($GLOBALS['thumbnail_saved'][88]), 'featured photo removed as well');
$_POST['flacso_home_campaign_images_nonce'] = 'invalid';
$_POST['flacso_campaign_image_desktop'] = '21';
FLACSO_Home_Campaign::save_images(88, (object) ['ID' => 88]);
expect_image(($GLOBALS['meta_saved'][88][FLACSO_Home_Campaign::META_DESKTOP_IMAGE] ?? null) === 0, 'invalid nonce prevents writes');
echo "Homepage campaign photo persistence OK\n";
