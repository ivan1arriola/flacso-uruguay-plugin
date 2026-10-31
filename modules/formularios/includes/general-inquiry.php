<?php
/**
 * Consulta general: no se guarda en WordPress ni en base de datos.
 * Cada envío dispara un correo a recepción (reply-to al consultante), un acuse
 * al consultante, ambos por Mautic, y un aviso por Telegram.
 */
if (!defined('ABSPATH')) {
    exit;
}

const FC_DEFAULT_RECEPTION_EMAIL = 'inscripciones@flacso.edu.uy';
const FC_DEFAULT_ACK_SUBJECT = 'Hemos recibido tu consulta';
const FC_DEFAULT_ACK_TEXT = "Hola {nombre}:\n\nRecibimos tu consulta «{asunto}». El equipo de FLACSO Uruguay te responderá a la brevedad.\n\nEste mensaje es una confirmación automática. Si querés agregar información, podés responder a este correo.\n\nFLACSO Uruguay";

/** Correo de recepción de consultas: un único ajuste, con el valor histórico como respaldo. */
function fc_get_reception_email(): string {
    $email = sanitize_email((string) get_option('fc_destinatario_email', ''));
    return is_email($email) ? $email : FC_DEFAULT_RECEPTION_EMAIL;
}

/** Reemplaza {nombre}, {apellido} y {asunto} sin dejar pasar saltos de línea en asuntos. */
function fc_render_inquiry_template(string $template, array $payload): string {
    return strtr($template, [
        '{nombre}' => trim((string) ($payload['nombre'] ?? '')),
        '{apellido}' => trim((string) ($payload['apellido'] ?? '')),
        '{asunto}' => trim((string) ($payload['asunto'] ?? '')),
    ]);
}

function fc_notify_inquiry_telegram(array $payload, string $delivery_status): string {
    if (get_option('fc_telegram_notify_consultas', '1') !== '1'
        || !function_exists('fc_can_use_telegram') || !fc_can_use_telegram()
        || !function_exists('fc_send_telegram_message')) {
        return 'skipped';
    }
    $limit = static function (string $value, int $max): string {
        $value = trim($value);
        return function_exists('mb_substr') ? mb_substr($value, 0, $max) : substr($value, 0, $max);
    };
    $line = static fn(string $label, string $value): string => '<b>' . $label . ':</b> ' . htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
    $lines = [
        '<b>Nueva consulta desde la web</b>',
        $line('Nombre', trim((string) ($payload['nombre'] ?? '') . ' ' . (string) ($payload['apellido'] ?? ''))),
        $line('Correo', (string) ($payload['email'] ?? '')),
        $line('Teléfono', (string) ($payload['telefono'] ?? '')),
        $line('Asunto', $limit((string) ($payload['asunto'] ?? ''), 200)),
        $line('Consulta', $limit((string) ($payload['mensaje'] ?? ''), 700)),
        $line('Correo a recepción', $delivery_status === 'accepted' ? 'enviado' : 'no confirmado, revisar'),
    ];
    return fc_send_telegram_message(implode("\n", $lines)) ? 'sent' : 'failed';
}

/**
 * @return array{ok:bool,status:string,acceptance_unknown:bool,error:?string,acuse:string,telegram:string}
 */
function fc_send_general_inquiry(array $payload): array {
    $reception_email = fc_get_reception_email();
    $subject_template = trim((string) get_option('fc_asunto_admin', ''));
    $subject = $subject_template !== ''
        ? fc_render_inquiry_template($subject_template, $payload)
        : '';

    try {
        $result = FLACSO_Mautic_Client::send_general_inquiry_notification($payload, $reception_email, $subject);
    } catch (Throwable $error) {
        // Once processing starts, do not encourage an automatic resend when
        // we cannot know whether the transport has already handed off email.
        $result = ['ok' => false, 'status' => 'acceptance_unknown', 'acceptance_unknown' => true, 'error' => 'No fue posible confirmar la entrega en Mautic.'];
    }
    $result['acuse'] = 'skipped';

    // El acuse sale sólo si recepción ya aceptó la consulta: no se confirma al
    // consultante algo que el equipo no recibió.
    if (!empty($result['ok']) && get_option('fc_enviar_acuse', '1') === '1') {
        $ack_subject = trim((string) get_option('fc_asunto_usuario', ''));
        $ack_text = trim((string) get_option('fc_texto_acuse', ''));
        try {
            $ack = FLACSO_Mautic_Client::send_general_inquiry_acknowledgement(
                $payload,
                $reception_email,
                fc_render_inquiry_template($ack_subject !== '' ? $ack_subject : FC_DEFAULT_ACK_SUBJECT, $payload),
                fc_render_inquiry_template($ack_text !== '' ? $ack_text : FC_DEFAULT_ACK_TEXT, $payload)
            );
            $result['acuse'] = !empty($ack['ok']) ? 'sent' : 'failed';
            $result['acuse_error'] = (string) ($ack['error'] ?? '');
        } catch (Throwable $error) {
            $result['acuse'] = 'failed';
            $result['acuse_error'] = 'No fue posible enviar el acuse.';
        }
    }

    $result['telegram'] = fc_notify_inquiry_telegram($payload, !empty($result['ok']) ? 'accepted' : 'unconfirmed');
    return $result;
}
