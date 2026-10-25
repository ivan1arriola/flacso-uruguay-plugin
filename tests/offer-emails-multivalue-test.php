<?php
/**
 * Correo de la Oferta Académica multivaluado: parseo, validación y
 * compatibilidad con valores históricos guardados como texto único.
 */
define('ABSPATH', __DIR__ . '/');

$GLOBALS['offer_emails_meta'] = [];

function sanitize_email($email) {
    return preg_replace('/[^a-z0-9.@_+\-]/i', '', (string) $email);
}

function is_email($email) {
    return filter_var($email, FILTER_VALIDATE_EMAIL) !== false ? $email : false;
}

function get_post_meta(int $post_id, string $key = '', bool $single = false) {
    return $GLOBALS['offer_emails_meta'][$post_id][$key] ?? '';
}

require_once dirname(__DIR__) . '/modules/oferta-academica/includes/class-notification-emails.php';
require_once dirname(__DIR__) . '/modules/oferta-academica/includes/class-oferta-academica.php';

function offer_emails_assert($expected, $actual, string $label): void {
    if ($expected !== $actual) {
        fwrite(STDERR, "FAIL: {$label}\nExpected: " . var_export($expected, true) . "\nActual: " . var_export($actual, true) . "\n");
        exit(1);
    }
}

offer_emails_assert(
    ['a@flacso.edu.uy', 'b@flacso.edu.uy', 'c@flacso.edu.uy'],
    FLACSO_Oferta_Academica::sanitize_offer_emails("a@flacso.edu.uy\nB@flacso.edu.uy, c@flacso.edu.uy; a@flacso.edu.uy"),
    'texto separado por líneas, comas y punto y coma, normalizado y sin duplicados'
);
offer_emails_assert(
    ['a@flacso.edu.uy', 'b@flacso.edu.uy'],
    FLACSO_Oferta_Academica::sanitize_offer_emails([' a@flacso.edu.uy ', 'b@flacso.edu.uy', '']),
    'acepta lista'
);
offer_emails_assert([], FLACSO_Oferta_Academica::sanitize_offer_emails(''), 'vacío');
offer_emails_assert([], FLACSO_Oferta_Academica::sanitize_offer_emails(null), 'nulo');
offer_emails_assert(['no-es-correo'], FLACSO_Oferta_Academica::invalid_offer_emails("a@flacso.edu.uy\nno-es-correo"), 'detecta inválidos');
offer_emails_assert([], FLACSO_Oferta_Academica::invalid_offer_emails(['a@flacso.edu.uy']), 'lista válida');

$GLOBALS['offer_emails_meta'][10]['correo_oferta'] = 'historico@flacso.edu.uy';
offer_emails_assert(['historico@flacso.edu.uy'], FLACSO_Oferta_Academica::get_offer_emails(10), 'valor histórico (texto único) sigue funcionando');
$GLOBALS['offer_emails_meta'][11]['correo_oferta'] = ['uno@flacso.edu.uy', 'dos@flacso.edu.uy'];
offer_emails_assert(['uno@flacso.edu.uy', 'dos@flacso.edu.uy'], FLACSO_Oferta_Academica::get_offer_emails(11), 'lista guardada');
offer_emails_assert([], FLACSO_Oferta_Academica::get_offer_emails(12), 'sin correos');

echo "OK: correo de la oferta académica multivaluado\n";
