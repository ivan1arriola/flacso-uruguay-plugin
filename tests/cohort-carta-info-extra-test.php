<?php
/** «Información extra para Carta»: campo de la cohorte (promociones, becas) que muestra la carta. */

$root = dirname(__DIR__);
$cohort = (string) file_get_contents($root . '/modules/oferta-academica/includes/class-cohorte.php');
$repositories = (string) file_get_contents($root . '/modules/oferta-academica/includes/class-academic-repositories.php');

function info_extra_assert(bool $condition, string $message): void {
    if (!$condition) {
        fwrite(STDERR, "FAIL: {$message}\n");
        exit(1);
    }
}

info_extra_assert(strpos($cohort, "'carta_info_extra_html'  => ['type' => 'string', 'sanitize_callback' => 'wp_kses_post']") !== false, 'el meta de la cohorte está registrado y saneado con wp_kses_post');
info_extra_assert(strpos($cohort, "'flacso-cohorte-carta-info-extra'") !== false && strpos($cohort, 'Información extra para Carta') !== false, 'el editor de Cohorte tiene la sección «Información extra para Carta»');
info_extra_assert(strpos($cohort, "wp_editor(\$carta_info_extra_html, 'carta_info_extra_html'") !== false, 'se edita con el editor de texto de WordPress');
info_extra_assert(strpos($cohort, "'carta_info_extra_html' => 'wp_kses_post',") !== false, 'al guardar se sanea el HTML');
info_extra_assert(strpos($cohort, 'justo después de la sección 01') !== false, 'el editor explica dónde se muestra');

$section = strpos($cohort, "'flacso-cohorte-carta-info-extra'");
info_extra_assert($section > strpos($cohort, "'flacso-cohorte-cursado'") && $section < strpos($cohort, "'flacso-cohorte-preinscripcion'"), 'la sección va entre Cursado y Preinscripción');

$offers = substr($repositories, (int) strpos($repositories, "'ofertas' => ["), (int) strpos($repositories, "'cohortes' => [") - (int) strpos($repositories, "'ofertas' => ["));
$cohorts = substr($repositories, (int) strpos($repositories, "'cohortes' => ["), 600);
info_extra_assert(strpos($cohorts, "'carta_info_extra_html'") !== false, 'la API de cohortes expone la información extra');
info_extra_assert(strpos($offers, 'carta_info_extra_html') === false && strpos($offers, 'carta_presentacion_html') === false, 'la Oferta ya no tiene este campo: cambia por cohorte');
info_extra_assert(strpos($repositories, 'carta_mensaje_html') === false, 'no queda el nombre provisional');

echo "OK cohort carta info extra\n";
