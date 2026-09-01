<?php

$root = dirname(__DIR__);
$admin = file_get_contents($root . '/includes/core/class-flacso-preinscription-links-admin.php');
$main = file_get_contents($root . '/flacso-uruguay.php');

function links_admin_assert($condition, string $message): void {
    if (!$condition) {
        fwrite(STDERR, "FAIL: {$message}\n");
        exit(1);
    }
}

links_admin_assert(strpos($main, 'class-flacso-preinscription-links-admin.php') !== false, 'el plugin debe cargar el generador masivo');
links_admin_assert(strpos($admin, "public const PAGE_SLUG = 'flacso-preinscripcion-links'") !== false, 'debe existir una página propia');
links_admin_assert(strpos($admin, 'admin_post_flacso_save_preinscription_links') !== false, 'debe guardar mediante admin-post');
links_admin_assert(strpos($admin, 'check_admin_referer(self::NONCE_ACTION)') !== false, 'debe validar nonce');
links_admin_assert(strpos($admin, "private const BASE_URL = 'https://preinscripciones.flacso.edu.uy'") !== false, 'debe usar el host canónico');
links_admin_assert(strpos($admin, "'/' . \$kind . '/' . rawurlencode(\$slug) . '/'") !== false, 'debe construir URLs desde el slug');
links_admin_assert(strpos($admin, "canonical_parent_url(\$parent_id, 'oferta')") !== false, 'las cohortes deben usar /oferta/<slug>/');
links_admin_assert(strpos($admin, "canonical_parent_url(\$seminar_id, 'seminario')") !== false, 'las ediciones deben usar /seminario/<slug>/');
links_admin_assert(strpos($admin, 'FLACSO_Cohorte::sanitize_registration_url') !== false, 'debe validar links de cohortes');
links_admin_assert(strpos($admin, 'FLACSO_Edicion::sanitize_registration_url') !== false, 'debe validar links de ediciones');
links_admin_assert(strpos($admin, "update_post_meta(\$id, 'link_preinscripcion', \$sanitized)") !== false, 'debe persistir el link canónico');
links_admin_assert(strpos($admin, 'component_edition_ids') !== false, 'debe respetar la herencia de seminarios integrados');
links_admin_assert(strpos($admin, 'Generar y guardar todos los links') !== false, 'la UI debe ofrecer generación masiva explícita');

echo "OK preinscription links generator contract\n";
