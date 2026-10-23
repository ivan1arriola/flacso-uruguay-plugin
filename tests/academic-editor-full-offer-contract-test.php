<?php
/**
 * Contrato de la migración segura del formulario de Oferta a /editor.
 * Los save_post del dominio permanecen como única persistencia académica.
 */
$root = dirname(__DIR__);

function flacso_offer_full_assert(bool $ok, string $message): void {
    if (!$ok) {
        fwrite(STDERR, 'FAIL: ' . $message . PHP_EOL);
        exit(1);
    }
}

$sources = [];
foreach ([
    'editor/bootstrap.php',
    'editor/views/academic.php',
    'editor/views/offer-form.php',
    'editor/includes/class-editor-router.php',
    'editor/controllers/class-oferta-editor-controller.php',
    'editor/infrastructure/class-wordpress-offer-form.php',
    'editor/assets/js/offer-form.js',
    'modules/oferta-academica/includes/class-oferta-admin-fields.php',
    'modules/oferta-academica/includes/class-academic-team-editor.php',
    'modules/oferta-academica/includes/class-offer-carta-contact-admin.php',
    'modules/oferta-academica/includes/class-academic-document-source-admin.php',
] as $path) {
    $sources[$path] = (string) file_get_contents($root . '/' . $path);
}

$adapter = $sources['editor/infrastructure/class-wordpress-offer-form.php'];
$view = $sources['editor/views/offer-form.php'];
$router = $sources['editor/includes/class-editor-router.php'];
$old_form = $sources['modules/oferta-academica/includes/class-oferta-admin-fields.php'];

flacso_offer_full_assert(strpos($adapter, 'FLACSO_Oferta_Admin_Fields::render($post)') !== false, 'todos los campos se renderizan desde el formulario canónico');
flacso_offer_full_assert(strpos($adapter, 'wp_update_post([') !== false, 'guardar invoca los save_post canónicos');
flacso_offer_full_assert(strpos($adapter, 'save_post') !== false, 'documentar hooks canónicos');
flacso_offer_full_assert(strpos($adapter, "wp_verify_nonce(") !== false, 'validación de nonces de los módulos');
flacso_offer_full_assert(strpos($adapter, "current_user_can('edit_post'") !== false, 'permiso de registro');
flacso_offer_full_assert(strpos($adapter, "isset(\$_POST['flacso_oferta'])") !== false, 'no borrar información si faltan campos');
flacso_offer_full_assert(strpos($adapter, 'set_post_thumbnail(') !== false, 'puede cambiar imagen destacada');
flacso_offer_full_assert(strpos($adapter, 'delete_post_thumbnail(') !== false, 'puede quitar imagen destacada');

foreach ([
    "name=\"post_title\"",
    "name=\"post_status\"",
    "name=\"flacso_thumbnail_id\"",
    "name=\"flacso_editor_offer_form_present\"",
    'flacso_editor_save_offer_',
    '$controller->render_form(',
    'FLACSO_Editor_Editable_Controller_Interface',
] as $needle) {
    flacso_offer_full_assert(strpos($view, $needle) !== false, 'vista contiene ' . $needle);
}
flacso_offer_full_assert(strpos($router, "admin_post_flacso_editor_save_offer") !== false, 'ruta de guardado autenticada');
flacso_offer_full_assert(strpos($router, "check_admin_referer(") !== false, 'protección CSRF');
flacso_offer_full_assert(strpos($router, 'self::$repository->can_edit(') !== false, 'verifica permisos');
flacso_offer_full_assert(strpos($router, "self::new_url(\$id)") !== false, 'retorno al mismo contenido');

foreach ([
    'presentacion',
    'perfil_ingreso_html',
    'requisitos_ingreso_html',
    'perfil_egreso_html',
    'requisitos_egreso_html',
    'titulos_certificaciones_html',
    'malla_curricular',
    'titulos_intermedios',
    'menciones',
    'orientaciones',
    'reconocido_mec',
    'reconocimiento_internacional',
    'reconocimiento_red_flacso',
    'convenio_iin_oea',
    'flacso_oferta_color_principal',
    'FLACSO_Academic_Team_Editor::render_offer_section',
    'FLACSO_Offer_Carta_Contact_Admin::render_section',
] as $needle) {
    flacso_offer_full_assert(strpos($old_form, $needle) !== false, 'el formulario canónico incluye ' . $needle);
}

foreach ([
    'FLACSO_Oferta_Admin_Fields::render_scripts(true)',
    'FLACSO_Academic_Team_Editor::render_script(true)',
    'FLACSO_Offer_Carta_Contact_Admin::render_script(true)',
    'FLACSO_Academic_Document_Source_Admin::render_editor($post)',
] as $needle) {
    flacso_offer_full_assert(strpos($adapter, $needle) !== false, 'se inicializa ' . $needle);
}

flacso_offer_full_assert(strpos($sources['editor/views/academic.php'], "require FLACSO_URUGUAY_PATH . 'editor/views/offer-form.php'") !== false, 'Ofertas muestra formulario real');
flacso_offer_full_assert(strpos($sources['editor/assets/js/offer-form.js'], 'tinyMCE.triggerSave()') !== false, 'el texto enriquecido se sincroniza al guardar');
flacso_offer_full_assert(strpos($sources['editor/assets/js/offer-form.js'], 'beforeunload') !== false, 'avisa sobre cambios sin guardar');
// Jerarquía: identidad > imagen destacada > campos académicos; publicación lateral.
$identity_offset = strpos($view, 'id="flacso-offer-title"');
$image_offset = strpos($view, 'class="flacso-new-editor__card flacso-new-editor__image"');
$fields_offset = strpos($view, 'class="flacso-new-editor__fields"');
$publication_offset = strpos($view, 'class="flacso-new-editor__card flacso-new-editor__publication"');
flacso_offer_full_assert(
    $identity_offset !== false && $image_offset !== false && $fields_offset !== false
        && $publication_offset !== false && $identity_offset < $image_offset
        && $image_offset < $fields_offset && $fields_offset < $publication_offset,
    'la imagen destacada está inmediatamente después del nombre, antes de los campos y la publicación'
);
flacso_offer_full_assert(substr_count($view, 'id="flacso-offer-thumbnail-id"') === 1, 'un único control de imagen destacada');
$css = (string) file_get_contents($root . '/editor/assets/css/editor.css');
flacso_offer_full_assert(strpos($css, 'object-fit:contain') !== false, 'no recorta la imagen');
flacso_offer_full_assert(strpos($css, 'width:min(100%,280px);height:185px') !== false, 'vista previa compacta');
flacso_offer_full_assert(strpos($css, '@media(max-width:782px)') !== false, 'diseño móvil previsto');

echo "OK academic editor full offer contract\n";
