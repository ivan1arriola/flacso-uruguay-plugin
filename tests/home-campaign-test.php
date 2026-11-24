<?php
define('ABSPATH', __DIR__ . '/../');
function sanitize_key($v) { return preg_replace('/[^a-z0-9_-]/', '', strtolower((string) $v)); }
function esc_url_raw($v, $p = null) { return (string) $v; }
function wp_parse_url($v) { return parse_url($v); }
function wp_strip_all_tags($v) { return strip_tags((string) $v); }
function absint($v) { return abs((int) $v); }
function get_option($k, $d = false) { return $d; }
function get_post_meta($id, $key, $single = true) { return $GLOBALS['home_meta'][$id][$key] ?? ''; }
function get_post_thumbnail_id($id) { return $GLOBALS['home_thumb'][$id] ?? 0; }
function get_posts($args = []) {
    $GLOBALS['get_posts_calls'] = ($GLOBALS['get_posts_calls'] ?? 0) + 1;
    return $GLOBALS['campaign_posts'] ?? [];
}

require_once __DIR__ . '/../modules/main-page/includes/class-flacso-home-campaign.php';
function home_assert($ok, $message) { if (!$ok) { fwrite(STDERR, "FAIL: {$message}\n"); exit(1); } }
home_assert(FLACSO_Home_Campaign::sanitize_url('/formacion/') === '/formacion/', 'acepta rutas internas');
home_assert(FLACSO_Home_Campaign::sanitize_url('javascript:alert(1)') === '', 'rechaza javascript');
home_assert(FLACSO_Home_Campaign::sanitize_url('//evil.example/x') === '', 'rechaza URL protocol-relative');
$fallback = FLACSO_Home_Campaign::fallback();
home_assert($fallback['variant'] === 'institutional', 'fallback institucional');
home_assert(stripos($fallback['title'], 'preinscripciones abiertas') === false, 'fallback no promete apertura');
home_assert($fallback['kicker'] === '', 'fallback no duplica el slogan institucional que presenta el tema');
home_assert($fallback['title'] === 'Formación que conecta, conocimiento que transforma.', 'fallback presenta el slogan como mensaje principal');
home_assert($fallback['description'] === 'Conocé nuestra oferta de posgrados, especializaciones y seminarios.', 'fallback presenta la oferta académica como apoyo');
home_assert($fallback['cta_primary']['label'] === 'Conocé nuestra propuesta académica', 'fallback usa el CTA aprobado');
home_assert($fallback['cta_primary']['url'] === '/formacion/', 'fallback ofrece formación');
home_assert($fallback['cta_secondary'] === null, 'fallback no muestra un botón de consulta');
$post = (object) ['ID' => 77, 'post_title' => 'Portada de prueba', 'post_excerpt' => ''];
$GLOBALS['home_thumb'][77] = 501;
$view = FLACSO_Home_Campaign::from_post($post);
home_assert($view['desktop_image_id'] === 501, 'usa fotografía destacada existente cuando falta el metadato antiguo');
$GLOBALS['home_meta'][77][FLACSO_Home_Campaign::META_DESKTOP_IMAGE] = 602;
$view = FLACSO_Home_Campaign::from_post($post);
home_assert($view['desktop_image_id'] === 602, 'usa fotografía seleccionada en el editor del hero');
$GLOBALS['home_meta'][77][FLACSO_Home_Campaign::META_MOBILE_IMAGE] = 603;
$view = FLACSO_Home_Campaign::from_post($post);
home_assert($view['mobile_image_id'] === 603, 'admite fotografía alternativa para móvil');

// P3c: active() memoriza la consulta dentro de la misma petición.
$GLOBALS['get_posts_calls'] = 0;
$GLOBALS['campaign_posts'] = [];
FLACSO_Home_Campaign::reset_cache();
$first_active = FLACSO_Home_Campaign::active();
$second_active = FLACSO_Home_Campaign::active();
home_assert($first_active === $fallback && $second_active === $fallback, 'active() sin campañas devuelve el respaldo institucional');
home_assert($GLOBALS['get_posts_calls'] === 1, 'active() consulta campañas una sola vez por petición');
FLACSO_Home_Campaign::reset_cache();
FLACSO_Home_Campaign::active();
home_assert($GLOBALS['get_posts_calls'] === 2, 'reset_cache() invalida la memoria estática de active()');

// Q2: convenios-responsivos.php escapa $placeholder e $image con protocolo data permitido.
$convenios_src = (string) file_get_contents(__DIR__ . '/../modules/main-page/sections/convenios-responsivos.php');
home_assert(strpos($convenios_src, 'esc_url($placeholder, $allowed_protocols)') !== false, 'convenios escapa $placeholder con protocolos permitidos');
home_assert(strpos($convenios_src, 'src="<?php echo esc_url($image, $allowed_protocols); ?>"') !== false, 'convenios escapa $image en el mismo echo');

echo "OK home campaign contract\n";
