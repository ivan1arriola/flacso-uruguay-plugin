<?php
$source = file_get_contents(__DIR__ . '/../modules/main-page/includes/flacso-consultas.php');
if ($source === false) {
    fwrite(STDERR, "No se pudo leer el formulario de consultas.\n");
    exit(1);
}

$assert = static function (bool $condition, string $message): void {
    if (!$condition) {
        fwrite(STDERR, "FAIL: {$message}\n");
        exit(1);
    }
};

$assert((bool) preg_match('/<select\s+id="profesion"\s+name="profesion"[^>]*required/s', $source), 'Profesión debe ser un selector obligatorio, no un campo de texto');
$assert(strpos($source, 'name="profesion"') !== false, 'Se conserva el nombre del campo para mantener compatibilidad con las consultas existentes');
$assert(strpos($source, 'Otra área profesional') !== false, 'El selector incluye una opción cerrada para otras áreas');
$assert(strpos($source, 'Prefiero no informar') !== false, 'El selector permite no informar profesión sin texto libre');
$assert(strpos($source, 'function flacso_consultas_profession_options') !== false, 'Existe una lista central de opciones permitidas');
$assert(strpos($source, 'in_array( $data[\'profesion\'], flacso_consultas_profession_options(), true )') !== false, 'El servidor valida la opción recibida contra la lista cerrada');

echo "OK: el campo de profesión es cerrado y se valida en el servidor.\n";
