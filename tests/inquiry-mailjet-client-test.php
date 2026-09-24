<?php
// tests/inquiry-mailjet-client-test.php
$root = dirname(__DIR__);

if (!defined('ABSPATH')) {
    define('ABSPATH', __DIR__ . '/');
}

$GLOBALS['mailjet_mock_options'] = [
    'flacso_mailjet_api_key'                   => 'mock-api-key',
    'flacso_mailjet_secret_key'                => 'mock-secret-key',
    'flacso_mailjet_sender_email'              => 'notificaciones@flacso.edu.uy',
    'flacso_mailjet_sender_name'               => 'FLACSO Uruguay',
    'flacso_mailjet_template_consulta_abierta' => '12345',
];

if (!function_exists('get_option')) {
    function get_option($k, $d = false) {
        return $GLOBALS['mailjet_mock_options'][$k] ?? $d;
    }
}

$GLOBALS['mailjet_http_calls'] = [];
$GLOBALS['mailjet_mock_simulate_error'] = false;

if (!function_exists('wp_remote_post')) {
    function wp_remote_post($url, $args) {
        $GLOBALS['mailjet_http_calls'][] = ['url' => $url, 'args' => $args];
        if (!empty($GLOBALS['mailjet_mock_simulate_error'])) {
            return ['response' => ['code' => 500, 'message' => 'Internal Error'], 'body' => '{"ErrorMessage":"Error"}'];
        }
        $body = json_decode($args['body'], true);
        return [
            'response' => ['code' => 200, 'message' => 'OK'],
            'body'     => json_encode([
                'Messages' => [
                    [
                        'Status' => 'success',
                        'CustomID' => $body['Messages'][0]['CustomID'] ?? '',
                        'To' => [
                            [
                                'Email' => $body['Messages'][0]['To'][0]['Email'] ?? '',
                                'MessageID' => '288230407340150000',
                                'MessageUUID' => 'f7b8a8b1-1234-5678-90ab-cdef12345678',
                            ]
                        ]
                    ]
                ]
            ])
        ];
    }
}

if (!function_exists('is_wp_error')) { function is_wp_error($thing) { return false; } }
if (!function_exists('wp_remote_retrieve_response_code')) { function wp_remote_retrieve_response_code($res) { return $res['response']['code'] ?? 0; } }
if (!function_exists('wp_remote_retrieve_body')) { function wp_remote_retrieve_body($res) { return $res['body'] ?? ''; } }

require_once $root . '/includes/integrations/class-flacso-mailjet-client.php';

function mj_assert(bool $cond, string $msg): void {
    if (!$cond) {
        fwrite(STDERR, "FAIL: {$msg}\n");
        exit(1);
    }
}

// 1. Envío con TemplateID configurado
$res_open = FLACSO_Mailjet_Client::send_offer_inquiry(
    [
        'consultaId' => 'cid-mj-001',
        'email'      => 'interesado@ejemplo.com',
        'firstName'  => 'Mariana',
        'fullName'   => 'Mariana Silva',
    ],
    [
        'id'                     => 55,
        'name'                   => 'Diploma en Políticas Públicas',
        'isInscripcionesAbiertas'=> true,
        'urlBase'                => 'https://flacso.edu.uy/formacion/politicas-publicas',
        'preinscripcionUrl'      => 'https://flacso.edu.uy/formacion/politicas-publicas/preinscripcion',
        'startValue'             => '7 de abril 2026',
        'modalityLabel'          => 'Virtual',
    ]
);
mj_assert($res_open['ok'] === true, 'El envío debe ser exitoso');
mj_assert($res_open['status'] === 'sent', 'Status debe ser sent');
mj_assert($res_open['message_id'] === '288230407340150000', 'Debe capturar message_id');
mj_assert($res_open['message_uuid'] === 'f7b8a8b1-1234-5678-90ab-cdef12345678', 'Debe capturar message_uuid');

// 2. Envío sin TemplateID (debe activar fallback HTML interno)
$res_closed = FLACSO_Mailjet_Client::send_offer_inquiry(
    [
        'consultaId' => 'cid-mj-002',
        'email'      => 'interesado2@ejemplo.com',
        'firstName'  => 'Pedro',
        'fullName'   => 'Pedro Rossi',
    ],
    [
        'id'                     => 56,
        'name'                   => 'Maestría Cerrada',
        'isInscripcionesAbiertas'=> false,
        'urlBase'                => 'https://flacso.edu.uy/formacion/maestria',
    ]
);
mj_assert($res_closed['ok'] === true, 'El fallback debe enviar con éxito');
$last_call = end($GLOBALS['mailjet_http_calls']);
$sent_payload = json_decode($last_call['args']['body'], true);
mj_assert(empty($sent_payload['Messages'][0]['TemplateID']), 'Sin TemplateID configurado no debe enviar TemplateID');
mj_assert(!empty($sent_payload['Messages'][0]['HTMLPart']), 'Debe incluir HTMLPart');

// 3. Regla conservadora: Si TemplateID falla en Mailjet, NO hacer segundo envío automático con HTML
$GLOBALS['mailjet_mock_simulate_error'] = true;
$call_count_before = count($GLOBALS['mailjet_http_calls']);
$res_failed = FLACSO_Mailjet_Client::send_offer_inquiry(
    [
        'consultaId' => 'cid-mj-003',
        'email'      => 'error@ejemplo.com',
        'firstName'  => 'Lucía',
    ],
    [
        'id'                     => 55,
        'name'                   => 'Diploma',
        'isInscripcionesAbiertas'=> true,
    ]
);
mj_assert($res_failed['ok'] === false, 'Debe fallar');
mj_assert($res_failed['status'] === 'failed', 'Status debe ser failed');
mj_assert(count($GLOBALS['mailjet_http_calls']) === $call_count_before + 1, 'No debe intentar un segundo envío con HTML tras error remoto');

// 4. Envío de consulta de seminario con TemplateID
$GLOBALS['mailjet_mock_simulate_error'] = false;
$GLOBALS['mailjet_mock_options']['flacso_mailjet_template_consulta_seminario'] = '98765';
$res_sem_tpl = FLACSO_Mailjet_Client::send_seminar_inquiry(
    [
        'consultaId' => 'cid-mj-sem-001',
        'email'      => 'estudiante@ejemplo.com',
        'firstName'  => 'Carlos',
        'fullName'   => 'Carlos Gardel',
    ],
    [
        'id'                     => 77,
        'name'                   => 'Seminario de Bioética',
        'urlBase'                => 'https://flacso.edu.uy/formacion/seminarios/bioetica',
        'preinscripcionUrl'      => 'https://flacso.edu.uy/formacion/seminarios/bioetica/preinscripcion',
        'startValue'             => '15 de mayo 2026',
        'modalityLabel'          => 'Virtual',
    ]
);
mj_assert($res_sem_tpl['ok'] === true, 'El envío de seminario con TemplateID debe ser exitoso');
mj_assert($res_sem_tpl['status'] === 'sent', 'Status de seminario debe ser sent');
$last_call = end($GLOBALS['mailjet_http_calls']);
$sent_payload = json_decode($last_call['args']['body'], true);
mj_assert($sent_payload['Messages'][0]['TemplateID'] === 98765, 'TemplateID de seminario debe ser 98765');
mj_assert($sent_payload['Messages'][0]['TemplateLanguage'] === true, 'TemplateLanguage debe ser true');

// 5. Envío de seminario sin TemplateID (fallback HTML)
unset($GLOBALS['mailjet_mock_options']['flacso_mailjet_template_consulta_seminario']);
$res_sem_fallback = FLACSO_Mailjet_Client::send_seminar_inquiry(
    [
        'consultaId' => 'cid-mj-sem-002',
        'email'      => 'estudiante2@ejemplo.com',
        'firstName'  => 'Ana',
    ],
    [
        'id'       => 78,
        'name'     => 'Seminario de Género',
        'urlBase'  => 'https://flacso.edu.uy/formacion/seminarios/genero',
    ]
);
mj_assert($res_sem_fallback['ok'] === true, 'El fallback de seminario debe enviar con éxito');
$last_call = end($GLOBALS['mailjet_http_calls']);
$sent_payload = json_decode($last_call['args']['body'], true);
mj_assert(empty($sent_payload['Messages'][0]['TemplateID']), 'Sin TemplateID no debe enviar TemplateID en seminario');
mj_assert(!empty($sent_payload['Messages'][0]['HTMLPart']), 'Debe incluir HTMLPart en fallback de seminario');

// 6. Validación de omisión cuando Mailjet no está configurado
$saved_api_key = $GLOBALS['mailjet_mock_options']['flacso_mailjet_api_key'];
$GLOBALS['mailjet_mock_options']['flacso_mailjet_api_key'] = '';
$res_skipped = FLACSO_Mailjet_Client::send_offer_inquiry(
    [
        'consultaId' => 'cid-mj-004',
        'email'      => 'nadie@ejemplo.com',
    ],
    [
        'id'   => 55,
        'name' => 'Diploma',
    ]
);
mj_assert($res_skipped['ok'] === false, 'Sin API key no debe enviar');
mj_assert($res_skipped['status'] === 'skipped', 'Status debe ser skipped sin configuración');
$GLOBALS['mailjet_mock_options']['flacso_mailjet_api_key'] = $saved_api_key;

// 7. Validación cuando falta email de destinatario
$res_no_email = FLACSO_Mailjet_Client::send_offer_inquiry(
    [
        'consultaId' => 'cid-mj-005',
        'email'      => '',
    ],
    [
        'id'   => 55,
        'name' => 'Diploma',
    ]
);
mj_assert($res_no_email['ok'] === false, 'Sin email debe fallar/omitir');
mj_assert($res_no_email['status'] === 'skipped', 'Status debe ser skipped sin email');

echo "OK inquiry-mailjet-client-test\n";
