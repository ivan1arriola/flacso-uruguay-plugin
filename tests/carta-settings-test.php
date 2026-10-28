<?php

define('ABSPATH', __DIR__ . '/../');

$GLOBALS['test_options'] = [];

function get_option($name, $default = false) { return array_key_exists($name, $GLOBALS['test_options']) ? $GLOBALS['test_options'][$name] : $default; }
function update_option($name, $value) { $GLOBALS['test_options'][$name] = $value; return true; }
function delete_option($name) { unset($GLOBALS['test_options'][$name]); return true; }
function sanitize_text_field($value) { return trim(strip_tags((string) $value)); }
function sanitize_email($value) { return filter_var((string) $value, FILTER_SANITIZE_EMAIL); }
function wp_kses_post($value) { return preg_replace('#<script\b.*?</script>#is', '', (string) $value); }

require_once __DIR__ . '/../modules/oferta-academica/includes/class-carta-settings.php';

function carta_settings_assert(bool $condition, string $message): void {
    if (!$condition) {
        fwrite(STDERR, "FAIL: {$message}\n");
        exit(1);
    }
}

$expected = [
    'carta_badge_mec', 'carta_badge_apostilla', 'carta_badge_red', 'carta_badge_iin', 'carta_badge_presenciales',
    'carta_pago_dolares', 'carta_pago_pesos', 'carta_vencimiento_cuotas', 'carta_titulo_exterior', 'carta_titulo_sin_costo',
    'carta_envio_titulos', 'carta_convenio_iin_titulo', 'carta_convenio_iin_texto', 'carta_financiacion_label',
    'carta_mas_info_section_title', 'carta_mas_info_trayectoria_title', 'carta_mas_info_trayectoria_html',
    'carta_mas_info_gestion_title', 'carta_mas_info_gestion_html',
    'carta_correo_inscripciones', 'carta_telefono', 'carta_contacto_nombre', 'carta_contacto_rol',
];
carta_settings_assert(FLACSO_Carta_Settings::keys() === $expected, 'las claves de la carta están definidas en un solo lugar');
carta_settings_assert(strpos(FLACSO_Carta_Settings::get('carta_pago_pesos'), '{anio}') !== false, 'el texto de pagos en pesos conserva el marcador {anio}');
carta_settings_assert(FLACSO_Carta_Settings::get('carta_mas_info_section_title') === 'Más información', 'el título por defecto coincide con la API de ajustes');
carta_settings_assert(FLACSO_Carta_Settings::get('carta_correo_inscripciones') === 'inscripciones@flacso.edu.uy', 'correo institucional por defecto');
carta_settings_assert(FLACSO_Carta_Settings::get('desconocida') === '', 'una clave desconocida devuelve vacío');
carta_settings_assert(FLACSO_Carta_Settings::option_name('carta_telefono') === 'flacso_carta_telefono', 'nombre de opción con prefijo flacso_');

// Reglas de visibilidad: la misma condición que describe la pantalla de administración.
$apostille = ['reconocimiento_internacional' => true];
$no_apostille = ['reconocimiento_internacional' => false];
carta_settings_assert(FLACSO_Carta_Settings::applies('carta_titulo_exterior', $apostille), 'el título con costo aplica con Apostillado');
carta_settings_assert(!FLACSO_Carta_Settings::applies('carta_titulo_exterior', $no_apostille), 'el título con costo no aplica sin Apostillado');
carta_settings_assert(!FLACSO_Carta_Settings::applies('carta_titulo_sin_costo', $apostille), 'el título sin costo no aplica con Apostillado');
carta_settings_assert(FLACSO_Carta_Settings::applies('carta_titulo_sin_costo', $no_apostille) && FLACSO_Carta_Settings::applies('carta_titulo_sin_costo', []), 'el título sin costo es el caso por defecto');
carta_settings_assert(FLACSO_Carta_Settings::applies('carta_envio_titulos', $apostille) && FLACSO_Carta_Settings::applies('carta_envio_titulos', $no_apostille), 'el envío aplica a todas las cartas');
carta_settings_assert(FLACSO_Carta_Settings::applies('carta_badge_mec', ['reconocido_mec' => true]) && !FLACSO_Carta_Settings::applies('carta_badge_mec', []), 'el distintivo MEC depende de su marca');
carta_settings_assert(FLACSO_Carta_Settings::applies('carta_convenio_iin_texto', ['convenio_iin_oea' => true]) && !FLACSO_Carta_Settings::applies('carta_convenio_iin_texto', []), 'el convenio IIN-OEA depende de su marca');
carta_settings_assert(strpos(FLACSO_Carta_Settings::get('carta_titulo_exterior'), 'envío') === false, 'el texto del título con costo no menciona el envío (se explica aparte)');
carta_settings_assert(strpos(FLACSO_Carta_Settings::visible_text('carta_pago_pesos', [], ['{anio}' => '2027']), 'todo 2027,') !== false, 'visible_text reemplaza los marcadores');
carta_settings_assert(FLACSO_Carta_Settings::visible_text('carta_titulo_exterior', $no_apostille) === '', 'visible_text devuelve vacío si la regla no se cumple');
foreach (FLACSO_Carta_Settings::keys() as $key) {
    $meta = FLACSO_Carta_Settings::describe($key);
    carta_settings_assert($meta !== null && $meta['rule'] !== '' && $meta['label'] !== '' && isset(FLACSO_Carta_Settings::groups()[$meta['group']]), "{$key} tiene etiqueta, regla y grupo");
}

update_option('flacso_carta_vencimiento_cuotas', '');
carta_settings_assert(FLACSO_Carta_Settings::get('carta_vencimiento_cuotas') === '', 'una opción guardada vacía oculta el texto');
update_option('flacso_carta_telefono', '+598 1111 2222');
carta_settings_assert(FLACSO_Carta_Settings::get('carta_telefono') === '+598 1111 2222', 'una opción guardada reemplaza el valor por defecto');

FLACSO_Carta_Settings::update_from_payload([
    'carta_envio_titulos' => '<b>Nuevo</b> envío',
    'carta_mas_info_gestion_html' => '<p>ok</p><script>x()</script>',
    'carta_correo_inscripciones' => 'otro@flacso.edu.uy',
    'carta_mas_info_financiacion_html' => '<p>retirado</p>',
    'no_existe' => 'x',
]);
carta_settings_assert(get_option('flacso_carta_envio_titulos') === 'Nuevo envío', 'los textos simples se sanean');
carta_settings_assert(get_option('flacso_carta_mas_info_gestion_html') === '<p>ok</p>', 'el HTML se sanea');
carta_settings_assert(get_option('flacso_carta_correo_inscripciones') === 'otro@flacso.edu.uy', 'el correo se guarda saneado');
carta_settings_assert(get_option('flacso_carta_mas_info_financiacion_html', null) === null, 'se ignoran las tarjetas retiradas');
carta_settings_assert(get_option('flacso_no_existe', null) === null, 'se ignoran las claves desconocidas');

$all = FLACSO_Carta_Settings::all();
carta_settings_assert(count($all) === count($expected) && $all['carta_envio_titulos'] === 'Nuevo envío', 'all() devuelve todas las claves con sus valores vigentes');

FLACSO_Carta_Settings::reset('carta_envio_titulos');
carta_settings_assert(get_option('flacso_carta_envio_titulos', null) === null && strpos(FLACSO_Carta_Settings::get('carta_envio_titulos'), 'courier') !== false, 'reset vuelve al texto por defecto');

$admin = (string) file_get_contents(__DIR__ . '/../modules/oferta-academica/includes/class-carta-settings-admin.php');
foreach (['add_submenu_page(', 'FLACSO_Admin_Panel::PAGE_SLUG', 'check_admin_referer', 'current_user_can(self::CAPABILITY)', 'describe(', 'carta_textos_reset'] as $needle) {
    carta_settings_assert(strpos($admin, $needle) !== false, "la pantalla de administración incluye {$needle}");
}

echo "OK carta settings\n";
