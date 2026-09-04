<?php
/**
 * Test de contrato y sincronización de Solicitudes de Información (Consultas).
 * Verifica que el webhook y enriquecedor envíen la oferta académica y su cohorte activa a Editor.
 */

$root = dirname(__DIR__);
$helpers = file_get_contents($root . "/modules/formularios/includes/helpers.php");
$form_posgrados = file_get_contents($root . "/modules/posgrados/includes/class-flacso-posgrados-consultas-form.php");
$form_main = file_get_contents($root . "/modules/main-page/includes/flacso-consultas.php");

function sync_assert($condition, string $message): void {
    if (!$condition) {
        fwrite(STDERR, "FAIL: {$message}\n");
        exit(1);
    }
}

// 1. Verificación estática de contrato en helpers.php
sync_assert(strpos($helpers, "function fc_resolve_info_request_offer_id(") !== false, "fc_resolve_info_request_offer_id debe existir");
sync_assert(strpos($helpers, "function fc_enrich_info_request_program_context(") !== false, "fc_enrich_info_request_program_context debe existir");
sync_assert(strpos($helpers, "function fc_build_info_request_webhook_payload(") !== false, "fc_build_info_request_webhook_payload debe existir");

sync_assert(strpos($helpers, "FLACSO_Academic_Catalog::get_offer") !== false, "fc_enrich_info_request_program_context debe consultar FLACSO_Academic_Catalog::get_offer");
sync_assert(strpos($helpers, "cohorte_vigente") !== false, "fc_enrich_info_request_program_context debe resolver cohorte_vigente");
sync_assert(strpos($helpers, "link_preinscripcion") !== false, "fc_enrich_info_request_program_context debe resolver link_preinscripcion");
sync_assert(strpos($helpers, "oferta_academica_url_preinscripcion") !== false, "fc_enrich_info_request_program_context debe generar oferta_academica_url_preinscripcion");

// 2. Verificación de campos en el payload del webhook
sync_assert(strpos($helpers, "'offer_name'") !== false, "payload debe contener offer_name");
sync_assert(strpos($helpers, "'link_preinscripcion'") !== false, "payload debe contener link_preinscripcion");
sync_assert(strpos($helpers, "'preinscripcion_url'") !== false, "payload debe contener preinscripcion_url");
sync_assert(strpos($helpers, "'oferta_academica_url_preinscripcion'") !== false, "payload debe contener oferta_academica_url_preinscripcion");
sync_assert(strpos($helpers, "'cohorte_nombre'") !== false, "payload debe contener cohorte_nombre");
sync_assert(strpos($helpers, "'inscripciones_abiertas'") !== false, "payload debe contener inscripciones_abiertas");
sync_assert(strpos($helpers, "'modalidad'") !== false, "payload debe contener modalidad");

// 3. Verificación en class-flacso-posgrados-consultas-form.php
sync_assert(strpos($form_posgrados, "FLACSO_Academic_Catalog::get_offer") !== false, "formulario de posgrados debe resolver oferta por catálogo");
sync_assert(strpos($form_posgrados, '$preinsc_url') !== false, 'formulario de posgrados debe definir preinsc_url');
sync_assert(strpos($form_posgrados, "'consulta'") !== false, "formulario de posgrados debe aceptar campo consulta");

// 4. Verificación en flacso-consultas.php
sync_assert(strpos($form_main, "FLACSO_Academic_Catalog::get_offer") !== false, "flacso-consultas debe resolver oferta por catálogo");
sync_assert(strpos($form_main, '$preinsc_btn_url') !== false, 'flacso-consultas debe definir preinsc_btn_url');

// 5. Test unitario funcional simulado
if (!defined("ABSPATH")) {
    define("ABSPATH", __DIR__ . "/");
}

// Stubs mínimos para ejecutar helpers.php de forma aislada
if (!function_exists("wp_generate_uuid4")) {
    function wp_generate_uuid4() { return "test-uuid-4444"; }
}
if (!function_exists("sanitize_email")) {
    function sanitize_email($email) { return trim(strtolower($email)); }
}
if (!function_exists("sanitize_text_field")) {
    function sanitize_text_field($str) { return trim(strip_tags((string) $str)); }
}
if (!function_exists("sanitize_textarea_field")) {
    function sanitize_textarea_field($str) { return trim(strip_tags((string) $str)); }
}
if (!function_exists("esc_url_raw")) {
    function esc_url_raw($url) { return trim((string) $url); }
}
if (!function_exists("current_time")) {
    function current_time($type) { return "2026-09-04T12:00:00Z"; }
}
if (!function_exists("absint")) {
    function absint($maybeint) { return abs((int) $maybeint); }
}
if (!function_exists("add_filter")) {
    function add_filter($tag, $callback, $priority = 10, $accepted_args = 1) { return true; }
}
if (!function_exists("add_action")) {
    function add_action($tag, $callback, $priority = 10, $accepted_args = 1) { return true; }
}
if (!function_exists("wp_unslash")) {
    function wp_unslash($val) { return $val; }
}
if (!function_exists("home_url")) {
    function home_url($path = "") { return "https://flacso.edu.uy" . $path; }
}
if (!function_exists("get_option")) {
    function get_option($opt, $default = false) { return $default; }
}
if (!function_exists("wp_parse_url")) {
    function wp_parse_url($url) { return parse_url($url); }
}
if (!function_exists("get_post_type")) {
    function get_post_type($id) {
        if ($id === 500) {
            return "oferta-academica";
        }
        return "";
    }
}
if (!function_exists("get_the_title")) {
    function get_the_title($id) {
        if ($id === 500) {
            return "Maestría en Políticas Públicas";
        }
        return "";
    }
}
if (!function_exists("get_permalink")) {
    function get_permalink($id) {
        if ($id === 500) {
            return "https://flacso.edu.uy/oferta/politicas-publicas/";
        }
        return "https://flacso.edu.uy/";
    }
}

class FLACSO_Academic_Catalog {
    public static function get_offer(int $id): array {
        if ($id === 500) {
            return [
                "id" => 500,
                "nombre" => "Maestría en Políticas Públicas",
                "tipo" => "maestria",
                "correo" => "politicas@flacso.edu.uy",
                "cohorte_vigente" => [
                    "id" => 999,
                    "numero" => 4,
                    "nombre" => "Cohorte IV (2026)",
                    "fecha_inicio" => "2026-05-15",
                    "precision_fecha_inicio" => "dia",
                    "modalidad" => "Híbrida",
                    "link_preinscripcion" => "https://preinscripciones.flacso.edu.uy/oferta/politicas-publicas/",
                    "preinscripcion" => [
                        "abierta" => true,
                        "url" => "https://preinscripciones.flacso.edu.uy/oferta/politicas-publicas/",
                    ],
                ],
            ];
        }
        return [];
    }
}

require_once $root . "/modules/formularios/includes/helpers.php";

// Caso A: Formulario con campos estándar directos (sin post_type registrado en catálogo)
$form_data = [
    "nombre"          => "María",
    "apellido"        => "Rodríguez",
    "correo"          => "maria@ejemplo.com",
    "pais"            => "Uruguay",
    "profesion"       => "Docente",
    "nivel_academico" => "Universitario",
    "id_pagina"       => 123,
    "titulo_posgrado" => "Maestría en Educación",
    "url_base"        => "https://flacso.edu.uy/formacion/maestrias/educacion/",
    "link_preinscripcion" => "https://preinscripciones.flacso.edu.uy/oferta/educacion/",
    "cohorte_nombre"  => "Cohorte 2026",
    "modalidad"       => "A distancia / Virtual",
    "inscripciones_abiertas" => 1,
    "utm_source"      => "google",
    "utm_campaign"    => "inscripciones_2026",
];

$enriched = fc_enrich_info_request_program_context($form_data);
sync_assert($enriched["offer_name"] === "Maestría en Educación", "offer_name enriquecido fallback directo");
sync_assert($enriched["link_preinscripcion"] === "https://preinscripciones.flacso.edu.uy/oferta/educacion/", "link_preinscripcion enriquecido");
sync_assert($enriched["oferta_academica_url_preinscripcion"] === "https://preinscripciones.flacso.edu.uy/oferta/educacion/", "oferta_academica_url_preinscripcion enriquecido");
sync_assert($enriched["inscripciones_abiertas"] === 1, "inscripciones_abiertas enriquecido");

$payload = fc_build_info_request_webhook_payload($form_data);
sync_assert($payload["email"] === "maria@ejemplo.com", "payload email correcto");
sync_assert($payload["first_name"] === "María", "payload first_name correcto");
sync_assert($payload["last_name"] === "Rodríguez", "payload last_name correcto");
sync_assert($payload["offer_id"] === "123", "payload offer_id correcto");
sync_assert($payload["offer_name"] === "Maestría en Educación", "payload offer_name correcto");
sync_assert($payload["link_preinscripcion"] === "https://preinscripciones.flacso.edu.uy/oferta/educacion/", "payload link_preinscripcion correcto");
sync_assert($payload["oferta_academica_url_preinscripcion"] === "https://preinscripciones.flacso.edu.uy/oferta/educacion/", "payload oferta_academica_url_preinscripcion correcto");
sync_assert($payload["inscripciones_abiertas"] === true, "payload inscripciones_abiertas es booleano true");
sync_assert($payload["cohorte_nombre"] === "Cohorte 2026", "payload cohorte_nombre correcto");
sync_assert($payload["modalidad"] === "A distancia / Virtual", "payload modalidad correcto");
sync_assert($payload["campaign_source"] === "google", "payload campaign_source correcto");

// Caso B: Formulario con ID de Oferta resuelto desde FLACSO_Academic_Catalog y cohorte_vigente
$raw_submission = [
    "nombre"          => "Carlos",
    "apellido"        => "Gómez",
    "correo"          => "carlos@ejemplo.com",
    "pais"            => "Argentina",
    "profesion"       => "Economista",
    "nivel_academico" => "Posgrado",
    "id_pagina"       => 500,
];

$catalog_enriched = fc_enrich_info_request_program_context($raw_submission);
sync_assert($catalog_enriched["offer_name"] === "Maestría en Políticas Públicas", "offer_name desde catálogo");
sync_assert($catalog_enriched["link_preinscripcion"] === "https://preinscripciones.flacso.edu.uy/oferta/politicas-publicas/", "link_preinscripcion desde cohorte_vigente");
sync_assert($catalog_enriched["oferta_academica_url_preinscripcion"] === "https://preinscripciones.flacso.edu.uy/oferta/politicas-publicas/", "oferta_academica_url_preinscripcion desde cohorte_vigente");
sync_assert($catalog_enriched["cohorte_nombre"] === "Cohorte IV (2026)", "cohorte_nombre desde cohorte_vigente");
sync_assert($catalog_enriched["cohorte_id"] === 999, "cohorte_id desde cohorte_vigente");
sync_assert($catalog_enriched["modalidad"] === "Híbrida", "modalidad desde cohorte_vigente");
sync_assert($catalog_enriched["inscripciones_abiertas"] === 1, "inscripciones_abiertas desde cohorte_vigente");
sync_assert($catalog_enriched["correo_programa"] === "politicas@flacso.edu.uy", "correo_programa desde oferta");

$catalog_payload = fc_build_info_request_webhook_payload($raw_submission);
sync_assert($catalog_payload["offer_id"] === "500", "webhook payload offer_id");
sync_assert($catalog_payload["offer_name"] === "Maestría en Políticas Públicas", "webhook payload offer_name");
sync_assert($catalog_payload["link_preinscripcion"] === "https://preinscripciones.flacso.edu.uy/oferta/politicas-publicas/", "webhook payload link_preinscripcion");
sync_assert($catalog_payload["oferta_academica_url_preinscripcion"] === "https://preinscripciones.flacso.edu.uy/oferta/politicas-publicas/", "webhook payload oferta_academica_url_preinscripcion");
sync_assert($catalog_payload["inscripciones_abiertas"] === true, "webhook payload inscripciones_abiertas true");
sync_assert($catalog_payload["cohorte_nombre"] === "Cohorte IV (2026)", "webhook payload cohorte_nombre");
sync_assert($catalog_payload["modalidad"] === "Híbrida", "webhook payload modalidad");
sync_assert($catalog_payload["reply_to"] === "politicas@flacso.edu.uy", "webhook payload reply_to");

echo "OK info-request sync contract & functional test (including FLACSO_Academic_Catalog)\n";
