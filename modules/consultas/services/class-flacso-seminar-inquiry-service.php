<?php
/**
 * Servicio de procesamiento y persistencia de consultas de seminarios.
 *
 * Aplica el principio "guardar primero, enviar después":
 * 1. Extraer consultaId (o generar UUIDv4).
 * 2. Comprobar idempotencia temprana en repositorio (salir sin llamar a Mailjet si existe).
 * 3. Validar campos obligatorios mínimos.
 * 4. Enriquecer datos con catálogo académico o WordPress.
 * 5. Insertar registro en base de datos (si falla, abortar y NO llamar a Mailjet).
 * 6. Despachar correo transaccional vía Mailjet.
 * 7. Actualizar estado del correo en la base de datos.
 * 8. Retornar resultado estructurado.
 *
 * @package FLACSO_Uruguay
 */

if (!defined('ABSPATH') && !defined('STDIN')) {
    exit;
}

if (!class_exists('FLACSO_DB')) {
    require_once dirname(__DIR__, 3) . '/includes/database/class-flacso-db.php';
}
if (!class_exists('FLACSO_Seminar_Inquiry_Repository')) {
    require_once dirname(__DIR__, 3) . '/includes/database/repositories/class-flacso-seminar-inquiry-repository.php';
}
if (!class_exists('FLACSO_Mailjet_Client')) {
    require_once dirname(__DIR__, 3) . '/includes/integrations/class-flacso-mailjet-client.php';
}

class FLACSO_Seminar_Inquiry_Service {

    /**
     * Procesa una consulta de seminario.
     *
     * @param array $data Datos de la consulta (compatibles con snake_case y camelCase).
     * @return array Resultado con ok, consulta_id, duplicate, email, etc.
     */
    public static function submit(array $data): array {
        // 1. Extraer o generar consultaId
        $consulta_id = trim((string)($data['event_id'] ?? $data['consultaId'] ?? $data['consulta_id'] ?? $data['id'] ?? ''));
        if ($consulta_id === '') {
            $consulta_id = self::generate_uuidv4();
        }

        $repo = new FLACSO_Seminar_Inquiry_Repository();

        // 2. Verificación temprana de idempotencia
        try {
            $existing = $repo->find_by_consulta_id($consulta_id);
            if ($existing !== null) {
                return [
                    'ok'          => true,
                    'consulta_id' => $consulta_id,
                    'duplicate'   => true,
                    'email'       => $existing['emailStatus'] ?? 'skipped',
                    'code'        => 200,
                ];
            }
        } catch (\Throwable $e) {
            error_log('[FLACSO] Error en verificación temprana de idempotencia (seminario): ' . $e->getMessage());
            return [
                'ok'      => false,
                'code'    => 500,
                'error'   => 'db_error',
                'message' => $e->getMessage(),
            ];
        }

        // 3. Normalizar y validar campos requeridos
        $first_name = trim((string)($data['firstName'] ?? $data['nombre'] ?? ''));
        $last_name  = trim((string)($data['lastName'] ?? $data['apellido'] ?? ''));
        $full_name  = trim((string)($data['fullName'] ?? $data['nombre_apellido'] ?? ''));
        if ($full_name === '' && $first_name !== '') {
            $full_name = trim($first_name . ' ' . $last_name);
        } elseif ($first_name === '' && $full_name !== '') {
            $parts = explode(' ', $full_name);
            $first_name = $parts[0];
        }

        $email = trim((string)($data['email'] ?? $data['correo'] ?? ''));

        $seminar_id = isset($data['seminarWpId']) && $data['seminarWpId'] !== ''
            ? (int)$data['seminarWpId']
            : (isset($data['seminario_id']) && $data['seminario_id'] !== ''
                ? (int)$data['seminario_id']
                : (isset($data['id_pagina']) && $data['id_pagina'] !== ''
                    ? (int)$data['id_pagina']
                    : null));

        $seminar_name = trim((string)($data['seminarName'] ?? $data['seminario_titulo'] ?? $data['seminar_name'] ?? $data['titulo'] ?? $data['name'] ?? $data['title'] ?? ''));
        $seminar_type = isset($data['seminarType']) ? (string)$data['seminarType'] : (isset($data['tipo_seminario']) ? (string)$data['tipo_seminario'] : null);

        // Validación de campos mínimos obligatorios
        if ($email === '' || filter_var($email, FILTER_VALIDATE_EMAIL) === false) {
            return [
                'ok'      => false,
                'code'    => 422,
                'error'   => 'validation_error',
                'message' => 'El correo electrónico proporcionado es obligatorio y debe tener un formato válido.',
            ];
        }

        if ($first_name === '' && $full_name === '') {
            return [
                'ok'      => false,
                'code'    => 422,
                'error'   => 'validation_error',
                'message' => 'El nombre del solicitante es obligatorio.',
            ];
        }

        if (($seminar_id === null || $seminar_id <= 0) && $seminar_name === '') {
            return [
                'ok'      => false,
                'code'    => 422,
                'error'   => 'validation_error',
                'message' => 'Debe especificarse el identificador o título del seminario.',
            ];
        }

        // 4. Enriquecimiento / resolución de seminario
        $catalog_data = [];
        if ($seminar_id !== null && $seminar_id > 0) {
            if (class_exists('FLACSO_Academic_Catalog') && method_exists('FLACSO_Academic_Catalog', 'get_seminar')) {
                try {
                    $catalog_data = FLACSO_Academic_Catalog::get_seminar($seminar_id);
                } catch (\Throwable $t) {
                    $catalog_data = [];
                }
            }

            if ($seminar_name === '' && !empty($catalog_data['nombre'])) {
                $seminar_name = (string)$catalog_data['nombre'];
            } elseif ($seminar_name === '' && function_exists('get_post')) {
                $post = get_post($seminar_id);
                if ($post && !empty($post->post_title)) {
                    $seminar_name = (string)$post->post_title;
                }
            }

            if ($seminar_type === null && !empty($catalog_data['tipo'])) {
                $seminar_type = (string)$catalog_data['tipo'];
            }
        }

        $seminar_url = !empty($data['programUrl'])
            ? (string)$data['programUrl']
            : (!empty($data['urlBase'])
                ? (string)$data['urlBase']
                : (!empty($data['url_base'])
                    ? (string)$data['url_base']
                    : (!empty($data['url'])
                        ? (string)$data['url']
                        : (!empty($data['seminar_url'])
                            ? (string)$data['seminar_url']
                            : null))));

        if ($seminar_url === null && $seminar_id !== null && $seminar_id > 0 && function_exists('get_permalink')) {
            $permalink = get_permalink($seminar_id);
            if (!empty($permalink)) {
                $seminar_url = (string)$permalink;
            }
        }

        $carta_url = !empty($data['cartaUrl'])
            ? (string)$data['cartaUrl']
            : (!empty($data['url_carta'])
                ? (string)$data['url_carta']
                : null);

        $preinscripcion_url = !empty($data['preinscripcionUrl'])
            ? (string)$data['preinscripcionUrl']
            : (!empty($data['url_preinscripcion'])
                ? (string)$data['url_preinscripcion']
                : (!empty($catalog_data['edicion_vigente']['preinscripcion']['url'])
                    ? (string)$catalog_data['edicion_vigente']['preinscripcion']['url']
                    : null));

        $start_value = !empty($data['startValue'])
            ? (string)$data['startValue']
            : (!empty($data['periodo_inicio'])
                ? (string)$data['periodo_inicio']
                : (!empty($data['fecha_inicio'])
                    ? (string)$data['fecha_inicio']
                    : (!empty($catalog_data['edicion_vigente']['fecha_inicio'])
                        ? (string)$catalog_data['edicion_vigente']['fecha_inicio']
                        : '')));

        $modality = !empty($data['modalityLabel'])
            ? (string)$data['modalityLabel']
            : (!empty($data['modalidad'])
                ? (string)$data['modalidad']
                : (!empty($catalog_data['modalidad'])
                    ? (string)$catalog_data['modalidad']
                    : ''));

        $phone = trim((string)($data['telefono'] ?? $data['phone'] ?? ''));
        $message = trim((string)($data['consulta'] ?? $data['message'] ?? $data['mensaje'] ?? ''));

        $country = isset($data['country']) ? (string)$data['country'] : (isset($data['pais']) ? (string)$data['pais'] : null);
        $profession = isset($data['profession']) ? (string)$data['profession'] : (isset($data['profesion']) ? (string)$data['profesion'] : null);
        $education_level = isset($data['educationLevel']) ? (string)$data['educationLevel'] : (isset($data['nivel_academico']) ? (string)$data['nivel_academico'] : null);
        $reply_to = isset($data['replyToEmail']) ? (string)$data['replyToEmail'] : (isset($data['reply_to']) ? (string)$data['reply_to'] : null);
        $source = isset($data['source']) ? (string)$data['source'] : (isset($data['origen']) ? (string)$data['origen'] : 'Seminario');

        $ip = isset($data['ipAddress'])
            ? (string)$data['ipAddress']
            : (isset($data['ip_usuario'])
                ? (string)$data['ip_usuario']
                : (isset($data['meta']['ip'])
                    ? (string)$data['meta']['ip']
                    : (isset($_SERVER['REMOTE_ADDR']) ? (string)$_SERVER['REMOTE_ADDR'] : null)));

        $user_agent = isset($data['userAgent'])
            ? (string)$data['userAgent']
            : (isset($data['user_agent'])
                ? (string)$data['user_agent']
                : (isset($data['meta']['user_agent'])
                    ? (string)$data['meta']['user_agent']
                    : (isset($_SERVER['HTTP_USER_AGENT']) ? (string)$_SERVER['HTTP_USER_AGENT'] : null)));

        $url_referer = isset($data['urlReferer'])
            ? (string)$data['urlReferer']
            : (isset($data['url_referer'])
                ? (string)$data['url_referer']
                : (isset($data['referrer_url'])
                    ? (string)$data['referrer_url']
                    : null));

        $inquiry_at = isset($data['inquiryAt'])
            ? (string)$data['inquiryAt']
            : (isset($data['fecha_envio'])
                ? (string)$data['fecha_envio']
                : gmdate('c'));

        // 5. Inserción en Base de Datos (Guardar primero)
        $record = [
            'consultaId'         => $consulta_id,
            'seminarWpId'        => $seminar_id,
            'seminarName'        => $seminar_name,
            'seminarType'        => $seminar_type,
            'firstName'          => $first_name,
            'lastName'           => $last_name,
            'fullName'           => $full_name,
            'email'              => $email,
            'country'            => $country,
            'profession'         => $profession,
            'educationLevel'     => $education_level,
            'source'             => $source,
            'campaignProvider'   => $data['campaignProvider'] ?? $data['campaign_provider'] ?? $data['utm_provider'] ?? null,
            'campaignSource'     => $data['campaignSource'] ?? $data['campaign_source'] ?? $data['utm_source'] ?? null,
            'campaignMedium'     => $data['campaignMedium'] ?? $data['campaign_medium'] ?? $data['utm_medium'] ?? null,
            'campaignName'       => $data['campaignName'] ?? $data['campaign_name'] ?? $data['utm_campaign'] ?? null,
            'campaignExternalId' => $data['campaignExternalId'] ?? $data['campaign_external_id'] ?? $data['utm_id'] ?? null,
            'campaignContent'    => $data['campaignContent'] ?? $data['campaign_content'] ?? $data['utm_content'] ?? null,
            'campaignTerm'       => $data['campaignTerm'] ?? $data['campaign_term'] ?? $data['utm_term'] ?? null,
            'urlBase'            => $seminar_url,
            'urlReferer'         => $url_referer,
            'inquiryAt'          => $inquiry_at,
            'ipAddress'          => $ip,
            'userAgent'          => $user_agent,
            'replyToEmail'       => $reply_to,
            'programUrl'         => $seminar_url,
            'cartaUrl'           => $carta_url,
            'preinscripcionUrl'  => $preinscripcion_url,
            'offerStatus'        => null,
            'emailStatus'        => 'skipped',
            'payload'            => $data,
        ];

        try {
            $insert_result = $repo->insert($record);
        } catch (\Throwable $e) {
            error_log('[FLACSO] Error al insertar consulta de seminario en base de datos: ' . $e->getMessage());
            return [
                'ok'      => false,
                'code'    => 500,
                'error'   => 'db_error',
                'message' => $e->getMessage(),
            ];
        }

        // Si ocurrió colisión de unicidad capturada como duplicado en insert()
        if (!empty($insert_result['duplicate'])) {
            return [
                'ok'          => true,
                'consulta_id' => $consulta_id,
                'duplicate'   => true,
                'email'       => 'skipped',
                'code'        => 200,
            ];
        }

        // 6. Despacho Mailjet (Enviar después)
        $inquiry_payload = [
            'consultaId'       => $consulta_id,
            'firstName'        => $first_name,
            'lastName'         => $last_name,
            'fullName'         => $full_name,
            'email'            => $email,
            'phone'            => $phone,
            'telefono'         => $phone,
            'country'          => $country,
            'pais'             => $country,
            'message'          => $message,
            'consulta'         => $message,
            'seminario_id'     => $seminar_id,
            'seminario_titulo' => $seminar_name,
            'replyToEmail'     => $reply_to,
        ];

        $seminar_payload = [
            'id'                 => $seminar_id,
            'seminario_id'       => $seminar_id,
            'name'               => $seminar_name,
            'title'              => $seminar_name,
            'seminario_titulo'   => $seminar_name,
            'urlBase'            => $seminar_url,
            'url'                => $seminar_url,
            'preinscripcionUrl'  => $preinscripcion_url,
            'preinscripcion_url' => $preinscripcion_url,
            'startValue'         => $start_value,
            'periodo_inicio'     => $start_value,
            'modalityLabel'      => $modality,
            'modalidad'          => $modality,
        ];

        $mail_result = [
            'ok'           => false,
            'status'       => 'failed',
            'sender'       => null,
            'message_id'   => null,
            'message_uuid' => null,
            'error'        => null,
        ];

        try {
            if (class_exists('FLACSO_Mailjet_Client')) {
                $mail_result = FLACSO_Mailjet_Client::send_seminar_inquiry($inquiry_payload, $seminar_payload);
            } else {
                $mail_result['error'] = 'FLACSO_Mailjet_Client no está disponible.';
            }
        } catch (\Throwable $e) {
            error_log('[FLACSO] Excepción al enviar correo de seminario vía Mailjet: ' . $e->getMessage());
            $mail_result = [
                'ok'           => false,
                'status'       => 'failed',
                'sender'       => null,
                'message_id'   => null,
                'message_uuid' => null,
                'error'        => $e->getMessage(),
            ];
        }

        // 7. Actualizar estado del email en la base de datos
        $email_status = $mail_result['status'] ?? 'failed';
        $email_sender = $mail_result['sender'] ?? null;
        $message_id   = $mail_result['message_id'] ?? null;
        $message_uuid = $mail_result['message_uuid'] ?? null;

        try {
            $repo->update_email_status(
                $consulta_id,
                $email_status,
                $email_sender,
                $message_id,
                $message_uuid
            );
        } catch (\Throwable $e) {
            error_log('[FLACSO] Error al actualizar estado de email en base de datos: ' . $e->getMessage());
        }

        // 8. Retornar resultado
        return [
            'ok'                   => true,
            'consulta_id'          => $consulta_id,
            'duplicate'            => false,
            'email'                => $email_status,
            'email_sender'         => $email_sender,
            'mailjet_message_id'   => $message_id,
            'mailjet_message_uuid' => $message_uuid,
            'code'                 => 200,
        ];
    }

    /**
     * Genera un identificador UUIDv4 RFC 4122.
     */
    private static function generate_uuidv4(): string {
        if (function_exists('wp_generate_uuid4')) {
            return wp_generate_uuid4();
        }
        $bytes = random_bytes(16);
        $bytes[6] = chr((ord($bytes[6]) & 0x0f) | 0x40);
        $bytes[8] = chr((ord($bytes[8]) & 0x3f) | 0x80);
        return vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($bytes), 4));
    }
}
