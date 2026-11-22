<?php
/**
 * Contrato de fc_is_spam_content(): no descarta consultas legítimas y sí detecta spam.
 * Función pura: se carga validation.php real, sin stubs de WordPress.
 */

if (!defined('ABSPATH')) {
    define('ABSPATH', __DIR__ . '/');
}

require_once dirname(__DIR__) . '/modules/formularios/includes/validation.php';

function spam_assert($condition, string $message): void {
    if (!$condition) {
        fwrite(STDERR, "FAIL: {$message}\n");
        exit(1);
    }
}

// [descripción, nombre, apellido, email, asunto, mensaje, motivo esperado ('' = consulta legítima)]
$cases = [
    // Legítimas: antes se descartaban en silencio.
    ['signos ¿ ¡, euro, comillas y guion largo', 'Ana', 'Ruiz', 'ana.ruiz@gmail.com', 'Consulta', '¿Cuándo empieza? El arancel es de 100 € — «FLACSO» “ok”. ¡Gracias!', ''],
    ['acentos y ñ', 'María José', 'Núñez', 'mj@universidad.edu.uy', 'Información', 'Quisiera saber sobre la maestría y si está abierta la inscripción.', ''],
    ['"loan" dentro de un apellido', 'Sloane', 'Pérez', 'sloane@correo.com', 'Hola', 'Consulta sobre el programa', ''],
    ['"hola amigos" no es la frase spam', 'Luis', 'Gómez', 'luis@correo.com', 'Saludo', 'Hola amigos de FLACSO, una consulta sobre becas', ''],
    ['".ru" en medio del dominio, no al final', 'Ana', 'R', 'a@sub.ru.example.com', 'Consulta', 'Texto normal', ''],
    ['correo vacío con texto legítimo', 'Ana', 'Ruiz', '', 'Consulta', 'Texto normal', ''],
    // Spam: se sigue detectando, con su motivo.
    ['cirílico', 'Иван', 'Петров', 'ivan@correo.com', 'Привет', 'Здравствуйте, у меня вопрос', 'escritura_no_latina'],
    ['chino', 'Li', 'Wei', 'li@correo.com', '你好', '我想问一个问题', 'escritura_no_latina'],
    ['árabe', 'Omar', 'Said', 'omar@correo.com', 'مرحبا', 'مرحبا بكم', 'escritura_no_latina'],
    ['dominio .ru', 'Ivan', 'P', 'ivan@mail.ru', 'Consulta', 'Texto normal', 'dominio'],
    ['dominio .xyz', 'A', 'B', 'a@foo.xyz', 'Consulta', 'Texto normal', 'dominio'],
    ['dominio conocido de spam', 'A', 'B', 'a@reachout2me.pro', 'Consulta', 'Texto normal', 'dominio'],
    ['palabras de spam', 'A', 'B', 'a@correo.com', 'Oferta', 'Best bitcoin casino slot', 'palabra'],
    ['plural y mayúsculas', 'A', 'B', 'a@correo.com', 'Oferta', 'Fast PAYDAY LOANS', 'palabra'],
    ['frase de spam', 'A', 'B', 'a@correo.com', 'SEO', 'We offer guest posts for your site', 'palabra'],
    ['frase "hola amigo"', 'A', 'B', 'a@correo.com', 'Saludo', 'Hola amigo, quiero venderte algo', 'palabra'],
    ['vietnamita', 'A', 'B', 'a@correo.com', 'Saludo', 'Xin chào bạn', 'palabra'],
];

foreach ($cases as [$label, $nombre, $apellido, $email, $asunto, $mensaje, $expected]) {
    $reason = null;
    $is_spam = fc_is_spam_content($nombre, $apellido, $email, $asunto, $mensaje, $reason);
    if ($expected === '') {
        spam_assert($is_spam === false, "Consulta legítima descartada: {$label}");
    } else {
        spam_assert($is_spam === true, "Spam no detectado: {$label}");
        spam_assert($reason === $expected, "Motivo esperado '{$expected}', obtenido " . var_export($reason, true) . ": {$label}");
    }
}

// La firma de cinco argumentos sigue funcionando (el handler y los tests existentes la usan así).
spam_assert(fc_is_spam_content('Ana', 'Ruiz', 'ana@correo.com', 'Hola', 'Texto') === false, 'La firma de 5 argumentos debe seguir funcionando');

echo "OK fc_is_spam_content contract test\n";
