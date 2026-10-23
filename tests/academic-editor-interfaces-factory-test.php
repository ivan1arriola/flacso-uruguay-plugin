<?php
/**
 * Pruebas de inyección y aislamiento: no requieren instalar WordPress.
 */
if (!defined('ABSPATH')) {
    define('ABSPATH', __DIR__ . '/');
}
$root = dirname(__DIR__);
require_once $root . '/editor/contracts/interface-editor-repository.php';
require_once $root . '/editor/contracts/interface-editor-controller.php';
require_once $root . '/editor/controllers/class-oferta-editor-controller.php';
require_once $root . '/editor/controllers/class-cohorte-editor-controller.php';
require_once $root . '/editor/includes/class-editor-controller-factory.php';

function flacso_editor_contract_assert(bool $condition, string $message): void {
    if (!$condition) {
        fwrite(STDERR, "FAIL: {$message}\n");
        exit(1);
    }
}

final class FLACSO_Fake_Editor_Repository implements FLACSO_Editor_Repository_Interface {
    public function find(int $id): ?array {
        return $id === 9
            ? ['id' => 9, 'type' => 'oferta-academica', 'title' => 'Oferta padre', 'status' => 'publish', 'fields' => []]
            : null;
    }
    public function can_edit(int $id): bool {
        return $id === 9 || $id === 15;
    }
    public function legacy_edit_url(int $id): string {
        return 'legacy:' . $id;
    }
    public function cohorts_for_offer(int $offer_id): array {
        return $offer_id === 9
            ? [['id' => 15, 'type' => 'cohorte', 'title' => 'Cohorte II', 'status' => 'publish', 'fields' => ['academic_status' => 'planificada']]]
            : [];
    }
}

$repository = new FLACSO_Fake_Editor_Repository();
$factory = new FLACSO_Editor_Controller_Factory($repository);
$offer_controller = $factory->for_type('oferta-academica');
$cohort_controller = $factory->for_type('cohorte');

flacso_editor_contract_assert($offer_controller instanceof FLACSO_Editor_Controller_Interface, 'Oferta implementa interfaz');
flacso_editor_contract_assert($cohort_controller instanceof FLACSO_Editor_Controller_Interface, 'Cohorte implementa interfaz');

$offer = $repository->find(9);
$offer_model = $offer_controller->build_view_model($offer);
flacso_editor_contract_assert($offer_model['is_offer'] === true, 'presentador de Oferta');
flacso_editor_contract_assert(count($offer_model['cohorts']) === 1, 'cohortes provienen del repositorio');

$cohort = ['id' => 15, 'type' => 'cohorte', 'title' => 'Cohorte II', 'status' => 'publish', 'fields' => ['parent_id' => 9]];
$cohort_model = $cohort_controller->build_view_model($cohort);
flacso_editor_contract_assert($cohort_model['is_offer'] === false, 'presentador de Cohorte');
flacso_editor_contract_assert($cohort_model['parent']['id'] === 9, 'la Oferta padre viene del repositorio');

$forbidden = ['id' => 16, 'type' => 'cohorte', 'title' => 'Cohorte III', 'status' => 'publish', 'fields' => ['parent_id' => 88]];
flacso_editor_contract_assert($cohort_controller->build_view_model($forbidden)['parent'] === null, 'no expone padres sin acceso');

$failed = false;
try {
    $factory->for_type('seminario');
} catch (InvalidArgumentException $exception) {
    $failed = true;
}
flacso_editor_contract_assert($failed, 'la factory rechaza entidades no soportadas');

foreach ([
    'editor/controllers/class-oferta-editor-controller.php',
    'editor/controllers/class-cohorte-editor-controller.php',
    'editor/includes/class-editor-controller-factory.php',
    'editor/views/academic.php',
] as $relative) {
    $source = (string) file_get_contents($root . '/' . $relative);
    foreach (['get_post_meta(', 'get_posts(', 'wp_update_post(', 'WP_REST_Request'] as $forbidden_call) {
        flacso_editor_contract_assert(strpos($source, $forbidden_call) === false, $relative . ' no debe usar ' . $forbidden_call);
    }
}
$adapter = (string) file_get_contents($root . '/editor/infrastructure/class-wordpress-editor-repository.php');
flacso_editor_contract_assert(strpos($adapter, 'implements FLACSO_Editor_Repository_Interface') !== false, 'el adaptador implementa el contrato');
flacso_editor_contract_assert(strpos($adapter, "current_user_can('edit_post'") !== false, 'el adaptador respeta permisos nativos');
flacso_editor_contract_assert(strpos($adapter, 'get_post_meta(') !== false, 'el adaptador conoce los metadatos');

echo "OK academic editor interfaces factory controllers\n";
