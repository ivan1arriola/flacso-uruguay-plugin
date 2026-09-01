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

links_admin_assert(strpos($main, 'class-flacso-preinscription-links-admin.php') !== false, 'el plugin debe cargar el editor masivo');
links_admin_assert(strpos($admin, "public const PAGE_SLUG = 'flacso-preinscripcion-links'") !== false, 'debe existir una página propia');
links_admin_assert(strpos($admin, 'admin_post_flacso_save_preinscription_links') !== false, 'debe guardar mediante admin-post');
links_admin_assert(strpos($admin, 'check_admin_referer(self::NONCE_ACTION)') !== false, 'debe validar nonce');
links_admin_assert(strpos($admin, 'FLACSO_Cohorte::sanitize_registration_url') !== false, 'debe validar links de cohortes');
links_admin_assert(strpos($admin, 'FLACSO_Edicion::sanitize_registration_url') !== false, 'debe validar links de ediciones');
links_admin_assert(strpos($admin, "update_post_meta(\$id, 'link_preinscripcion', \$sanitized)") !== false, 'debe persistir el link canónico');
links_admin_assert(strpos($admin, "delete_post_meta(\$id, 'link_preinscripcion')") !== false, 'debe permitir limpiar links');
links_admin_assert(strpos($admin, "['cohorte', 'edicion']") !== false, 'debe cubrir cohortes y ediciones');

echo "OK preinscription links admin contract\n";
