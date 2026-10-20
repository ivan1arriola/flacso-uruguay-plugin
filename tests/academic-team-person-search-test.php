<?php
/**
 * Contrato de búsqueda segura y renderizado del selector de integrantes.
 * El editor no debe inyectar catálogos completos ni romper el contacto de carta.
 */
define('ABSPATH', dirname(__DIR__) . '/');
class FLACSO_Oferta_Academica { public const POST_TYPE = 'oferta-academica'; }
class FLACSO_Cohorte { public const POST_TYPE = 'cohorte'; }
function absint($value) { return abs((int) $value); }
function esc_attr($value) { return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8'); }
function esc_html($value) { return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8'); }
function esc_textarea($value) { return esc_html($value); }
function esc_html_e($value, $domain = '') { echo esc_html($value); }
function esc_attr_e($value, $domain = '') { echo esc_attr($value); }
function __($value, $domain = '') { return $value; }
function get_post_type($id) { return $id === 41 ? 'docente' : 'post'; }
function get_the_title($id) { return $id === 41 ? 'Persona <Test>' : ''; }
function sanitize_email($value) { return filter_var($value, FILTER_SANITIZE_EMAIL); }
function get_post_meta($id, $key, $single = true) { return $key === 'correo' ? 'persona@flacso.edu.uy' : ''; }
function get_the_post_thumbnail_url($id, $size) { return ''; }
function selected($a, $b, $echo = true) {
    $result = (string) $a === (string) $b ? 'selected="selected"' : '';
    if ($echo) echo $result;
    return $result;
}
function wp_create_nonce($action) { return 'nonce-' . $action; }
function get_current_screen() { return (object) ['post_type' => 'oferta-academica']; }
function wp_enqueue_script($handle) { $GLOBALS['test_enqueued'][] = $handle; }
require_once dirname(__DIR__) . '/modules/oferta-academica/includes/class-academic-team-editor.php';

function team_search_assert($condition, string $message): void {
    if (!$condition) { fwrite(STDERR, "FAIL: {$message}\n"); exit(1); }
}
$class = new ReflectionClass('FLACSO_Academic_Team_Editor');
$method = $class->getMethod('render_member_row');
$method->setAccessible(true);
ob_start();
$method->invoke(null, 'flacso_oferta_equipos[0][docentes][0]', ['id' => 41, 'rol' => 'Coordinación', 'correo' => 'contacto@flacso.edu.uy'], []);
$html = ob_get_clean();
team_search_assert(strpos($html, 'data-person-search') !== false, 'input buscable en integrantes');
team_search_assert(strpos($html, 'data-person-select') !== false, 'select compatibilidad con contacto de carta');
team_search_assert(strpos($html, 'name="flacso_oferta_equipos[0][docentes][0][id]"') !== false, 'nombre de campo intacto');
team_search_assert(strpos($html, 'value="41"') !== false, 'conserva identificador de persona seleccionada');
team_search_assert(strpos($html, 'Persona &lt;Test&gt;') !== false, 'escapa título seleccionado');
team_search_assert(strpos($html, 'selected') !== false, 'conserva la opción seleccionada');
team_search_assert(substr_count($html, '<option') === 2, 'renderiza únicamente vacío y persona ya vinculada');

$source = file_get_contents(dirname(__DIR__) . '/modules/oferta-academica/includes/class-academic-team-editor.php');
team_search_assert(strpos($source, 'posts_per_page' . "' => -1") === false, 'no carga todas las personas en el render');
team_search_assert(strpos($source, 'wp_ajax_flacso_search_team_people') !== false, 'end-point AJAX solo autenticado');
team_search_assert(strpos($source, 'check_ajax_referer') !== false, 'requiere nonce');
team_search_assert(strpos($source, "current_user_can('edit_post', \$post_id)") !== false, 'respeta permisos de edición');
team_search_assert(strpos($source, '$wpdb->prepare(') !== false, 'consulta SQL parametrizada');
team_search_assert(strpos($source, '$wpdb->esc_like(') !== false, 'patrón LIKE seguro');
team_search_assert(strpos($source, 'LIMIT %d OFFSET %d') !== false, 'resultados paginados');
team_search_assert(strpos($source, "pm.meta_key = 'correo'") !== false, 'búsqueda también por correo');
team_search_assert(strpos($source, 'data-team-zone="coordination"') !== false, 'separa Coordinación');
team_search_assert(strpos($source, 'data-team-zone="custom"') !== false, 'separa otros grupos');
team_search_assert(strpos($source, 'data-team-zone-list') !== false, 'preserva grupos al agregar dinámicamente');
team_search_assert(substr_count($source, 'class="flacso-team-members__actions"') === 2, 'agregar integrante está debajo de cada lista');
team_search_assert(strpos($source, 'flacso:academic-team-changed') !== false, 'mantiene sincronización del contacto de carta');
FLACSO_Academic_Team_Editor::enqueue_person_search('post.php');
team_search_assert(in_array('jquery-ui-autocomplete', $GLOBALS['test_enqueued'], true), 'utiliza autocompletado de WordPress');
echo "OK academic team person search and layout\n";
