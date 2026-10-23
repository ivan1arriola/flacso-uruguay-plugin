<?php
/**
 * Composition root: cableado explícito del nuevo editor.
 *
 * Los controladores sólo conocen interfaces PHP; el adaptador concreto
 * encapsula WordPress y no hay comunicación HTTP entre componentes.
 */
if (!defined('ABSPATH')) {
    exit;
}

require_once __DIR__ . '/contracts/interface-editor-repository.php';
require_once __DIR__ . '/contracts/interface-editor-controller.php';
require_once __DIR__ . '/contracts/interface-editor-editable-controller.php';
require_once __DIR__ . '/contracts/interface-editor-offer-form.php';
require_once __DIR__ . '/controllers/class-oferta-editor-controller.php';
require_once __DIR__ . '/controllers/class-cohorte-editor-controller.php';
require_once __DIR__ . '/infrastructure/class-wordpress-editor-repository.php';
require_once __DIR__ . '/infrastructure/class-wordpress-offer-form.php';
require_once __DIR__ . '/includes/class-editor-controller-factory.php';
require_once __DIR__ . '/includes/class-editor-router.php';

$flacso_editor_repository = new FLACSO_WordPress_Editor_Repository();
$flacso_editor_offer_form = new FLACSO_WordPress_Offer_Form();
$flacso_editor_controller_factory = new FLACSO_Editor_Controller_Factory($flacso_editor_repository, $flacso_editor_offer_form);

FLACSO_Editor_Router::init($flacso_editor_repository, $flacso_editor_controller_factory, $flacso_editor_offer_form);
