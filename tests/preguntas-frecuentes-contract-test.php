<?php
/** Contract test for the FAQ module. */

$root = dirname(__DIR__);
$main = file_get_contents($root . '/flacso-uruguay.php');
$class = file_get_contents($root . '/modules/preguntas-frecuentes/includes/class-flacso-preguntas-frecuentes.php');

$assertions = [
    "load_module('preguntas-frecuentes')" => 'El módulo FAQ debe cargarse desde el plugin principal.',
    "public const POST_TYPE = 'pregunta-frecuente'" => 'El CPT debe conservar el slug acordado.',
    "'show_in_rest'        => true" => 'Las preguntas deben poder editarse con el editor de bloques.',
    "'page-attributes'" => 'El CPT debe permitir definir el orden.',
    "'posts_per_page'         => -1" => 'La vista pública debe recuperar todas las preguntas.',
    "'menu_order' => 'ASC'" => 'La vista pública debe respetar el orden editorial.',
    'data-flacso-faq-item' => 'El HTML debe exponer el marcador para la animación progresiva.',
];

foreach ($assertions as $needle => $message) {
    $haystack = strpos($needle, 'load_module') !== false ? $main : $class;
    if (strpos($haystack, $needle) === false) {
        fwrite(STDERR, $message . PHP_EOL);
        exit(1);
    }
}

if (strpos($class, 'add_shortcode') !== false || strpos($class, 'shortcode_atts') !== false) {
    fwrite(STDERR, 'El módulo FAQ no debe conservar el shortcode histórico.' . PHP_EOL);
    exit(1);
}

if (substr_count($class, "'question' =>") !== 14) {
    fwrite(STDERR, 'La migración inicial debe contener las 14 preguntas del script original.' . PHP_EOL);
    exit(1);
}

echo "FAQ: CPT, orden, renderizador directo y migración inicial correctos.\n";
