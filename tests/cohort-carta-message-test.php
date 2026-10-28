<?php
/** Mensaje propio de la cohorte para la carta: meta, editor, guardado y exposición. */

$root = dirname(__DIR__);
$cohort = (string) file_get_contents($root . '/modules/oferta-academica/includes/class-cohorte.php');
$repositories = (string) file_get_contents($root . '/modules/oferta-academica/includes/class-academic-repositories.php');

function cohort_message_assert(bool $condition, string $message): void {
    if (!$condition) {
        fwrite(STDERR, "FAIL: {$message}\n");
        exit(1);
    }
}

cohort_message_assert(strpos($cohort, "'carta_mensaje_html'     => ['type' => 'string', 'sanitize_callback' => 'wp_kses_post']") !== false, 'el meta de la cohorte está registrado y saneado con wp_kses_post');
cohort_message_assert(strpos($cohort, "'flacso-cohorte-carta-mensaje'") !== false && strpos($cohort, 'Mensaje en la carta') !== false, 'el editor de Cohorte tiene la sección «Mensaje en la carta»');
cohort_message_assert(strpos($cohort, "wp_editor(\$carta_mensaje_html, 'carta_mensaje_html'") !== false, 'el mensaje se edita con el editor de texto de WordPress');
cohort_message_assert(strpos($cohort, "'carta_mensaje_html' => 'wp_kses_post',") !== false, 'al guardar se sanea el HTML');
cohort_message_assert(strpos($cohort, 'justo después de la sección 01') !== false, 'el editor explica dónde se muestra');

$section = strpos($cohort, "'flacso-cohorte-carta-mensaje'");
cohort_message_assert($section > strpos($cohort, "'flacso-cohorte-cursado'") && $section < strpos($cohort, "'flacso-cohorte-preinscripcion'"), 'la sección va entre Cursado y Preinscripción');
cohort_message_assert(strpos($repositories, "'instancias_presenciales', 'carta_mensaje_html'") !== false, 'la API de cohortes expone el mensaje');

echo "OK cohort carta message\n";
