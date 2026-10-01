<?php
define('ABSPATH', __DIR__ . '/../');
function sanitize_key($v) { return preg_replace('/[^a-z0-9_-]/', '', strtolower((string) $v)); }
function esc_url_raw($v, $p = null) { return (string) $v; }
function wp_parse_url($v) { return parse_url($v); }
function wp_strip_all_tags($v) { return strip_tags((string) $v); }
function absint($v) { return abs((int) $v); }
function get_option($k, $d = false) { return $d; }
require_once __DIR__ . '/../modules/main-page/includes/class-flacso-home-campaign.php';
function home_assert($ok, $message) { if (!$ok) { fwrite(STDERR, "FAIL: {$message}\n"); exit(1); } }
home_assert(FLACSO_Home_Campaign::sanitize_url('/formacion/') === '/formacion/', 'acepta rutas internas');
home_assert(FLACSO_Home_Campaign::sanitize_url('javascript:alert(1)') === '', 'rechaza javascript');
home_assert(FLACSO_Home_Campaign::sanitize_url('//evil.example/x') === '', 'rechaza URL protocol-relative');
$fallback = FLACSO_Home_Campaign::fallback();
home_assert($fallback['variant'] === 'institutional', 'fallback institucional');
home_assert(stripos($fallback['title'], 'preinscripciones abiertas') === false, 'fallback no promete apertura');
echo "OK home campaign contract\n";
