<?php

$root = dirname(__DIR__);
$admin_file = $root . '/modules/oferta-academica/includes/class-offer-carta-contact-admin.php';
$bridge_file = $root . '/modules/oferta-academica/includes/class-offer-carta-contact-bridge.php';
$init_file = $root . '/modules/oferta-academica/init.php';

function carta_contact_assert(bool $condition, string $message): void {
    if (!$condition) {
        fwrite(STDERR, "FAIL: {$message}\n");
        exit(1);
    }
}

carta_contact_assert(file_exists($admin_file), 'debe existir el editor de contacto de la oferta');
carta_contact_assert(file_exists($bridge_file), 'debe existir el puente de compatibilidad');

$admin = (string) file_get_contents($admin_file);
$bridge = (string) file_get_contents($bridge_file);
$init = (string) file_get_contents($init_file);

carta_contact_assert(strpos($admin, "FLACSO_Oferta_Academica::POST_TYPE") !== false, 'el contacto debe pertenecer a Oferta Académica');
carta_contact_assert(strpos($admin, "save_post_' . FLACSO_Oferta_Academica::POST_TYPE") !== false, 'debe guardarse al guardar la oferta');
carta_contact_assert(strpos($admin, "FLACSO_Cohorte::POST_TYPE") === false, 'el contacto no puede pertenecer a Cohorte');

foreach (['carta_contacto_docente_id', 'carta_contacto_titulo', 'carta_contacto_correo'] as $key) {
    carta_contact_assert(strpos($admin, $key) !== false, "falta el campo canónico {$key}");
}

carta_contact_assert(strpos($admin, "'post_type' => 'docente'") !== false, 'la persona debe elegirse del CPT docente');
carta_contact_assert(strpos($admin, 'Persona que aparece') !== false, 'debe existir selector de persona');
carta_contact_assert(strpos($admin, 'Título que se muestra') !== false, 'debe existir campo de título visible');
carta_contact_assert(strpos($admin, 'Vista previa del contacto') !== false, 'debe existir vista previa');

foreach (['asistente_academica_docente_id', 'asistente_academica_rol', 'asistente_academica_correo'] as $legacy_key) {
    carta_contact_assert(strpos($bridge, $legacy_key) !== false, "debe conservar compatibilidad con {$legacy_key}");
}

carta_contact_assert(strpos($bridge, 'get_post_metadata') !== false, 'el frontend histórico debe poder leer la configuración nueva');
carta_contact_assert(strpos($bridge, 'private static array $resolving') !== false, 'el puente debe tener guardia explícita contra reentrada');
carta_contact_assert(strpos($bridge, 'isset(self::$resolving[$guard_key])') !== false, 'el puente debe cortar llamadas recursivas');
carta_contact_assert(strpos($bridge, 'finally') !== false && strpos($bridge, 'unset(self::$resolving[$guard_key])') !== false, 'la guardia de reentrada debe liberarse siempre');
carta_contact_assert(strpos($bridge, 'No intervenir en ninguna metadata ajena al contacto de carta.') !== false, 'el filtro debe salir antes para metadata no relacionada');

carta_contact_assert(strpos($init, 'class-offer-carta-contact-admin.php') !== false, 'init debe cargar el editor');
carta_contact_assert(strpos($init, 'class-offer-carta-contact-bridge.php') !== false, 'init debe cargar el puente');
carta_contact_assert(strpos($init, 'FLACSO_Offer_Carta_Contact_Admin::init();') !== false, 'init debe inicializar el editor');
carta_contact_assert(strpos($init, 'FLACSO_Offer_Carta_Contact_Bridge::init();') !== false, 'init debe inicializar el puente');

echo "OK offer-carta-contact-contract-test\n";
