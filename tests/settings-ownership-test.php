<?php
/**
 * Contrato de propiedad de configuración.
 *
 * Evita que vuelva a aparecer un panel genérico de "Integraciones FLACSO" y
 * asegura que cada dominio sea dueño de sus ajustes.
 */

$root = dirname(__DIR__);

function ownership_assert(bool $condition, string $message): void {
    if (!$condition) {
        fwrite(STDERR, "FAIL: {$message}\n");
        exit(1);
    }
}

$files = [
    'admin' => $root . '/includes/core/class-flacso-admin-panel.php',
    'legacy_integrations' => $root . '/includes/core/class-flacso-integrations-settings.php',
    'system' => $root . '/includes/core/class-flacso-system-settings.php',
    'mail' => $root . '/modules/mailing/includes/class-flacso-mail-settings.php',
    'mail_init' => $root . '/modules/mailing/init.php',
    'portada' => $root . '/modules/main-page/includes/class-flacso-main-page-unified-settings.php',
    'portada_ajax' => $root . '/modules/main-page/includes/class-flacso-ajax-settings.php',
    'seminars' => $root . '/modules/seminarios/includes/class-seminario-settings.php',
    'offer_form' => $root . '/modules/oferta-academica/includes/class-oferta-consulta-form.php',
    'routing' => $root . '/modules/formularios/includes/editor-routing.php',
];

foreach ($files as $name => $path) {
    ownership_assert(file_exists($path), "{$name}: debe existir {$path}");
}

$admin = (string) file_get_contents($files['admin']);
$legacy = (string) file_get_contents($files['legacy_integrations']);
$system = (string) file_get_contents($files['system']);
$mail = (string) file_get_contents($files['mail']);
$mail_init = (string) file_get_contents($files['mail_init']);
$portada = (string) file_get_contents($files['portada']);
$portada_ajax = (string) file_get_contents($files['portada_ajax']);
$seminars = (string) file_get_contents($files['seminars']);
$offer_form = (string) file_get_contents($files['offer_form']);
$routing = (string) file_get_contents($files['routing']);

// Navegación principal por dominios.
ownership_assert(strpos($admin, "admin.php?page=flacso-correos") !== false, 'Panel FLACSO debe enlazar a Correos');
ownership_assert(strpos($admin, "admin.php?page=flacso-integracion-meta") !== false, 'Panel FLACSO debe enlazar a Analítica / Meta');
ownership_assert(strpos($admin, "admin.php?page=flacso-sistema") !== false, 'Panel FLACSO debe enlazar a Sistema');
ownership_assert(strpos($admin, "admin.php?page=flacso-integraciones") === false, 'Panel FLACSO no debe enlazar al panel genérico de Integraciones');
ownership_assert(strpos($admin, "Abrir Editor FLACSO") === false, 'Panel FLACSO no debe promocionar el Editor externo');

// La clase histórica queda como adaptador de Meta/compatibilidad, no como menú.
$menu_start = strpos($legacy, 'public static function register_menu(): void');
$settings_start = strpos($legacy, 'public static function register_settings(): void');
ownership_assert($menu_start !== false && $settings_start !== false, 'Debe existir register_menu en el adaptador histórico');
$menu_code = substr($legacy, $menu_start, $settings_start - $menu_start);
ownership_assert(strpos($menu_code, 'self::PAGE_SLUG_META') !== false, 'El adaptador histórico solo debe registrar Analítica / Meta');
ownership_assert(strpos($menu_code, 'self::PAGE_SLUG,') === false, 'El adaptador histórico no debe registrar la página genérica de Integraciones');

// Correos es dueño de Mailjet.
ownership_assert(strpos($mail, "PAGE_SLUG = 'flacso-correos'") !== false, 'Correos debe tener página propia');
ownership_assert(strpos($mail, "flacso_mailjet_api_key") !== false, 'Correos debe ser dueño de Mailjet');
ownership_assert(strpos($mail, "flacso_mailjet_template_consulta_abierta") !== false, 'Correos debe ser dueño de plantillas transaccionales');
ownership_assert(strpos($mail_init, "FLACSO_Mail_Settings::init()") !== false, 'Mailing debe inicializar su configuración');

// Sistema solo conserva dependencias técnicas todavía externas.
ownership_assert(strpos($system, "PAGE_SLUG = 'flacso-sistema'") !== false, 'Sistema debe tener página propia');
ownership_assert(strpos($system, "fc_consultas_webhook_url") !== false, 'Sistema debe administrar la consulta general todavía externa');
ownership_assert(strpos($system, "flacso_charlas_abiertas_webhook_url") !== false, 'Sistema debe administrar Charlas');
ownership_assert(strpos($system, "flacso_preinscripciones_webhook_url") !== false, 'Sistema debe administrar Preinscripciones');
ownership_assert(strpos($system, "fc_oferta_webhook_url") === false, 'Sistema no debe exponer webhook de ofertas');
ownership_assert(strpos($system, "flacso_oferta_consulta_endpoint_url") === false, 'Sistema no debe exponer endpoint flotante de ofertas');
ownership_assert(strpos($system, "flacso_external_editor_url") === false, 'Sistema no debe exponer URL del Editor');

// Portada es dueña del anuncio de navegación.
ownership_assert(strpos($portada, "'anuncio' =>") !== false, 'Portada debe tener sección Anuncio');
ownership_assert(strpos($portada, "flacso_nav_announcement_enabled") !== false, 'Portada debe leer opciones del anuncio');
ownership_assert(strpos($portada_ajax, "flacso_nav_announcement_enabled") !== false, 'Portada debe guardar opciones del anuncio');

// Seminarios es dueño de su comportamiento global.
ownership_assert(strpos($seminars, "flacso_seminarios_dias_cierre_post_inicio") !== false, 'Seminarios debe ser dueño de días de cierre');

// Oferta ya no conoce un endpoint externo.
ownership_assert(strpos($offer_form, "FLACSO_Offer_Inquiry_Service::submit") !== false, 'Formulario de Oferta debe usar el servicio interno');
ownership_assert(strpos($offer_form, "flacso_oferta_consulta_endpoint_url") === false, 'Formulario de Oferta no debe tener option de endpoint');
ownership_assert(strpos($offer_form, "wp_safe_remote_post") === false, 'Formulario de Oferta no debe publicar a un endpoint remoto');

// El reparador legacy no vuelve a crear el webhook de ofertas.
ownership_assert(strpos($routing, "get_option('fc_oferta_webhook_url'") === false, 'Ruteo legacy no debe leer webhook de oferta');
ownership_assert(strpos($routing, "update_option('fc_oferta_webhook_url'") === false, 'Ruteo legacy no debe recrear webhook de oferta');

echo "OK settings ownership contract\n";
