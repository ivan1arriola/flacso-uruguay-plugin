<?php
/**
 * Contrato editorial para la marca de actividades exclusivamente presenciales.
 * El campo sigue perteneciendo a la Cohorte y no sustituye "modalidad".
 */
$root = dirname(__DIR__);
$editor = file_get_contents($root . '/modules/oferta-academica/includes/class-cohorte.php');
$repository = file_get_contents($root . '/modules/oferta-academica/includes/class-academic-repositories.php');

function assert_exclusive_sessions(bool $ok, string $message): void {
    if (!$ok) {
        fwrite(STDERR, "FAIL: " . $message . "\n");
        exit(1);
    }
}

assert_exclusive_sessions(
    strpos($editor, 'Esta Oferta Académica incluye instancias solamente presenciales') !== false,
    'el control nombra explícitamente las instancias solamente presenciales'
);
assert_exclusive_sessions(
    strpos($editor, 'sin opción de participación virtual') !== false,
    'la descripción distingue encuentros presenciales exclusivos de híbridos'
);
assert_exclusive_sessions(
    strpos($editor, 'No equivale a modalidad híbrida') !== false,
    'la ayuda no confunde el indicador con modalidad híbrida'
);
assert_exclusive_sessions(
    strpos($editor, 'name="instancias_presenciales" value="1"') !== false,
    'mantiene el checkbox existente para evitar migrar los datos'
);
assert_exclusive_sessions(
    strpos($editor, "update_post_meta(\$post_id, 'instancias_presenciales'") !== false,
    'la bandera se sigue guardando en la cohorte'
);
assert_exclusive_sessions(
    strpos($editor, '<select name="modalidad">') !== false,
    'la modalidad general permanece independiente'
);
assert_exclusive_sessions(
    strpos($repository, "'instancias_presenciales',") !== false,
    'el catálogo sigue exponiendo el campo a la web y carta'
);

echo "OK: indicador exclusivo presencial diferenciado de modalidad\n";
