<?php

if (!defined('ABSPATH')) {
    exit;
}

class FLACSO_Mailjet_Client {
    public const OPTION_MAILJET_API_KEY = 'flacso_mailjet_api_key';
    public const OPTION_MAILJET_SECRET_KEY = 'flacso_mailjet_secret_key';
    public const OPTION_MAILJET_SENDER_EMAIL = 'flacso_mailjet_sender_email';
    public const OPTION_MAILJET_SENDER_NAME = 'flacso_mailjet_sender_name';
    public const OPTION_TEMPLATE_CONSULTA_ABIERTA = 'flacso_mailjet_template_consulta_abierta';
    public const OPTION_TEMPLATE_CONSULTA_CERRADA = 'flacso_mailjet_template_consulta_cerrada';
    public const OPTION_TEMPLATE_CONSULTA_SEMINARIO = 'flacso_mailjet_template_consulta_seminario';

    private const MAILJET_SEND_ENDPOINT = 'https://api.mailjet.com/v3.1/send';

    /**
     * Obtiene la configuración de Mailjet reutilizando FLACSO_Integrations_Settings si está disponible.
     *
     * @return array
     */
    public static function get_settings(): array {
        if (class_exists('FLACSO_Integrations_Settings') && method_exists('FLACSO_Integrations_Settings', 'get_mailjet_settings')) {
            $settings = FLACSO_Integrations_Settings::get_mailjet_settings();
        } else {
            $admin_email = function_exists('get_option') ? (string) get_option('admin_email', '') : '';
            $default_name = 'FLACSO Uruguay';
            if (function_exists('get_bloginfo') && function_exists('wp_specialchars_decode')) {
                $blog_name = get_bloginfo('name');
                if (!empty($blog_name)) {
                    $default_name = wp_specialchars_decode($blog_name, ENT_QUOTES);
                }
            }

            $sender_email_option = function_exists('get_option')
                ? get_option(self::OPTION_MAILJET_SENDER_EMAIL, $admin_email)
                : $admin_email;
            $sender_email = function_exists('sanitize_email')
                ? sanitize_email((string) $sender_email_option)
                : trim((string) $sender_email_option);

            $sender_name_option = function_exists('get_option')
                ? get_option(self::OPTION_MAILJET_SENDER_NAME, $default_name)
                : $default_name;

            $settings = [
                'api_key'      => function_exists('get_option') ? trim((string) get_option(self::OPTION_MAILJET_API_KEY, '')) : '',
                'secret_key'   => function_exists('get_option') ? trim((string) get_option(self::OPTION_MAILJET_SECRET_KEY, '')) : '',
                'sender_email' => $sender_email,
                'sender_name'  => trim((string) $sender_name_option),
            ];
        }

        if (empty($settings['sender_name'])) {
            $settings['sender_name'] = 'FLACSO Uruguay';
        }

        return $settings;
    }

    /**
     * Verifica si las credenciales mínimas de Mailjet están presentes.
     *
     * @return bool
     */
    public static function is_configured(): bool {
        $settings = self::get_settings();

        return !empty($settings['api_key'])
            && !empty($settings['secret_key'])
            && !empty($settings['sender_email']);
    }

    /**
     * Envía un mensaje transaccional a través de la API v3.1 de Mailjet.
     *
     * @param array $params
     * @return array ['ok' => bool, 'status' => 'sent'|'failed'|'skipped', 'sender' => string, 'message_id' => ?string, 'message_uuid' => ?string, 'error' => ?string]
     */
    public static function send(array $params): array {
        $settings = self::get_settings();
        $sender_email = $settings['sender_email'] ?? '';

        if (!self::is_configured()) {
            return [
                'ok'           => false,
                'status'       => 'skipped',
                'sender'       => $sender_email,
                'message_id'   => null,
                'message_uuid' => null,
                'error'        => 'Mailjet no está configurado (faltan API Key, Secret Key o Sender Email).',
            ];
        }

        $to_email = trim((string) ($params['to_email'] ?? $params['to'] ?? $params['email'] ?? ''));
        if ($to_email === '') {
            return [
                'ok'           => false,
                'status'       => 'skipped',
                'sender'       => $sender_email,
                'message_id'   => null,
                'message_uuid' => null,
                'error'        => 'El correo de destino está vacío.',
            ];
        }

        $to_name = trim((string) ($params['to_name'] ?? $params['name'] ?? $params['fullName'] ?? ''));
        $recipient = ['Email' => $to_email];
        if ($to_name !== '') {
            $recipient['Name'] = $to_name;
        }

        $from = [
            'Email' => $sender_email,
            'Name'  => !empty($settings['sender_name']) ? $settings['sender_name'] : 'FLACSO Uruguay',
        ];

        $message = [
            'From' => $from,
            'To'   => [$recipient],
        ];

        $subject = trim((string) ($params['subject'] ?? ''));
        if ($subject !== '') {
            $message['Subject'] = $subject;
        }

        $template_id = $params['template_id'] ?? null;
        $template_id_int = 0;
        if (!empty($template_id) && is_numeric($template_id)) {
            $template_id_int = (int) $template_id;
        }

        if ($template_id_int > 0) {
            $message['TemplateID'] = $template_id_int;
            $message['TemplateLanguage'] = true;

            $variables = $params['variables'] ?? $params['vars'] ?? [];
            if (!empty($variables) && is_array($variables)) {
                $message['Variables'] = $variables;
            }
        } else {
            $html_part = $params['html_part'] ?? $params['html'] ?? '';
            $text_part = $params['text_part'] ?? $params['text'] ?? '';

            if ($html_part !== '') {
                $message['HTMLPart'] = (string) $html_part;
            }
            if ($text_part !== '') {
                $message['TextPart'] = (string) $text_part;
            }
        }

        $custom_id = trim((string) ($params['custom_id'] ?? $params['consultaId'] ?? $params['consulta_id'] ?? $params['event_id'] ?? ''));
        if ($custom_id !== '') {
            $message['CustomID'] = $custom_id;
        }

        $reply_to = trim((string) ($params['reply_to'] ?? $params['replyTo'] ?? ''));
        if ($reply_to !== '') {
            $reply_to_data = ['Email' => $reply_to];
            $reply_to_name = trim((string) ($params['reply_to_name'] ?? ''));
            if ($reply_to_name !== '') {
                $reply_to_data['Name'] = $reply_to_name;
            }
            $message['ReplyTo'] = $reply_to_data;
        }

        $auth_header = 'Basic ' . base64_encode($settings['api_key'] . ':' . $settings['secret_key']);
        $payload = [
            'Messages' => [$message],
        ];

        $json_body = function_exists('wp_json_encode') ? wp_json_encode($payload) : json_encode($payload);

        $http_args = [
            'timeout' => 15,
            'headers' => [
                'Authorization' => $auth_header,
                'Content-Type'  => 'application/json',
                'Accept'        => 'application/json',
            ],
            'body'    => $json_body,
        ];

        $response = wp_remote_post(self::MAILJET_SEND_ENDPOINT, $http_args);

        if (function_exists('is_wp_error') && is_wp_error($response)) {
            return [
                'ok'           => false,
                'status'       => 'failed',
                'sender'       => $sender_email,
                'message_id'   => null,
                'message_uuid' => null,
                'error'        => $response->get_error_message(),
            ];
        }

        $code = function_exists('wp_remote_retrieve_response_code')
            ? (int) wp_remote_retrieve_response_code($response)
            : (int) ($response['response']['code'] ?? 0);

        $body_str = function_exists('wp_remote_retrieve_body')
            ? (string) wp_remote_retrieve_body($response)
            : (string) ($response['body'] ?? '');

        $decoded = json_decode($body_str, true);

        if ($code < 200 || $code >= 300) {
            $error_detail = $decoded['ErrorMessage'] ?? $decoded['message'] ?? $body_str;

            return [
                'ok'           => false,
                'status'       => 'failed',
                'sender'       => $sender_email,
                'message_id'   => null,
                'message_uuid' => null,
                'error'        => sprintf('Mailjet HTTP %d: %s', $code, is_string($error_detail) ? $error_detail : json_encode($error_detail)),
            ];
        }

        $first_msg = $decoded['Messages'][0] ?? null;
        if (is_array($first_msg) && isset($first_msg['Status']) && $first_msg['Status'] === 'error') {
            $errors = $first_msg['Errors'] ?? [];

            return [
                'ok'           => false,
                'status'       => 'failed',
                'sender'       => $sender_email,
                'message_id'   => null,
                'message_uuid' => null,
                'error'        => !empty($errors) ? json_encode($errors) : 'Error reportado por Mailjet en Messages[0]',
            ];
        }

        $message_id = null;
        $message_uuid = null;
        if (isset($first_msg['To'][0]['MessageID'])) {
            $message_id = (string) $first_msg['To'][0]['MessageID'];
        } elseif (isset($first_msg['MessageID'])) {
            $message_id = (string) $first_msg['MessageID'];
        }

        if (isset($first_msg['To'][0]['MessageUUID'])) {
            $message_uuid = (string) $first_msg['To'][0]['MessageUUID'];
        } elseif (isset($first_msg['MessageUUID'])) {
            $message_uuid = (string) $first_msg['MessageUUID'];
        }

        return [
            'ok'           => true,
            'status'       => 'sent',
            'sender'       => $sender_email,
            'message_id'   => $message_id,
            'message_uuid' => $message_uuid,
            'error'        => null,
        ];
    }

    /**
     * Convierte los slugs canónicos de modalidad en etiquetas legibles para correo.
     */
    private static function humanize_modality(string $value): string {
        $raw = trim($value);
        if ($raw === '') {
            return '';
        }

        $key = function_exists('sanitize_key')
            ? sanitize_key($raw)
            : strtolower($raw);

        $labels = [
            'virtual'         => 'Virtual',
            'presencial'      => 'Presencial',
            'semipresencial'  => 'Semipresencial',
            'hibrida'         => 'Híbrida',
            'hibrido'         => 'Híbrida',
        ];

        return $labels[$key] ?? $raw;
    }

    /**
     * Formatea fechas civiles del modelo académico sin desplazamientos de zona horaria.
     */
    private static function format_start_value(string $value, string $precision = 'dia'): string {
        $raw = trim($value);
        if ($raw === '') {
            return '';
        }

        $precision = strtolower(trim($precision));
        $precision = [
            'day'   => 'dia',
            'month' => 'mes',
            'year'  => 'anio',
        ][$precision] ?? $precision;

        if ($precision === 'anio' && preg_match('/^(\\d{4})/', $raw, $m)) {
            return $m[1];
        }

        if (!preg_match('/^(\\d{4})-(\\d{2})(?:-(\\d{2}))?/', $raw, $m)) {
            return $raw;
        }

        $year = (int) $m[1];
        $month = (int) $m[2];
        $day = isset($m[3]) ? (int) $m[3] : 1;
        $months = [
            1 => 'enero', 2 => 'febrero', 3 => 'marzo', 4 => 'abril',
            5 => 'mayo', 6 => 'junio', 7 => 'julio', 8 => 'agosto',
            9 => 'septiembre', 10 => 'octubre', 11 => 'noviembre', 12 => 'diciembre',
        ];

        if (!isset($months[$month])) {
            return $raw;
        }

        if ($precision === 'mes') {
            return ucfirst($months[$month] . ' de ' . $year);
        }

        if (!checkdate($month, $day, $year)) {
            return $raw;
        }

        return $day . ' de ' . $months[$month] . ' de ' . $year;
    }

    /**
     * Envía correo transaccional de consulta sobre una oferta académica.
     * Regla conservadora:
     * - Si hay TemplateID configurado localmente -> se envía vía TemplateID.
     *   Si Mailjet falla -> se retorna 'failed' sin reintento automático HTML para evitar duplicados.
     * - Si TemplateID no está configurado -> se compila y envía el fallback HTML institucional.
     *
     * @param array $inquiry
     * @param array $program
     * @return array
     */
    public static function send_offer_inquiry(array $inquiry, array $program): array {
        $first_name = trim((string) ($inquiry['firstName'] ?? $inquiry['nombre'] ?? ''));
        $last_name = trim((string) ($inquiry['lastName'] ?? $inquiry['apellido'] ?? ''));
        $full_name = trim((string) ($inquiry['fullName'] ?? $inquiry['nombre_apellido'] ?? ''));
        if ($full_name === '') {
            $full_name = trim($first_name . ' ' . $last_name);
        }
        if ($first_name === '' && $full_name !== '') {
            $parts = explode(' ', $full_name);
            $first_name = $parts[0];
        }

        $email = trim((string) ($inquiry['email'] ?? $inquiry['correo'] ?? ''));
        $consulta_id = trim((string) ($inquiry['consultaId'] ?? $inquiry['event_id'] ?? $inquiry['consulta_id'] ?? ''));
        $country = trim((string) ($inquiry['country'] ?? $inquiry['pais'] ?? ''));
        $profession = trim((string) ($inquiry['profession'] ?? $inquiry['profesion'] ?? ''));
        $education_level = trim((string) ($inquiry['educationLevel'] ?? $inquiry['nivel_academico'] ?? ''));

        $is_open = !empty($program['isInscripcionesAbiertas']) || !empty($program['inscripciones_abiertas']);
        $program_name = trim((string) ($program['name'] ?? $program['titulo_posgrado'] ?? $program['title'] ?? 'Oferta académica'));
        $program_url = trim((string) ($program['urlBase'] ?? $program['programUrl'] ?? $program['url'] ?? $program['link'] ?? 'https://flacso.edu.uy/formacion/'));
        $preinscripcion_url = trim((string) ($program['preinscripcionUrl'] ?? $program['preinscripcion_url'] ?? ''));
        $start_precision = trim((string) ($program['startPrecision'] ?? $program['precision_fecha_inicio'] ?? 'dia'));
        $start_value = self::format_start_value(
            trim((string) ($program['startValue'] ?? $program['fecha_inicio'] ?? '')),
            $start_precision
        );
        $modality = self::humanize_modality(
            trim((string) ($program['modalityLabel'] ?? $program['modalidad'] ?? ''))
        );

        // Resolución de TemplateID configurado
        $template_id = '';
        if ($is_open) {
            $template_id = function_exists('get_option')
                ? trim((string) get_option(self::OPTION_TEMPLATE_CONSULTA_ABIERTA, ''))
                : '';
        } else {
            $template_id = function_exists('get_option')
                ? trim((string) get_option(self::OPTION_TEMPLATE_CONSULTA_CERRADA, ''))
                : '';
        }

        $subject = sprintf('📌 %s – %s', $program_name ?: 'Información solicitada', $first_name ?: 'información solicitada');

        $reply_to = trim((string) ($inquiry['replyToEmail'] ?? $inquiry['reply_to'] ?? ''));
        if ($reply_to === '') {
            $reply_to = 'secretaria@flacso.edu.uy';
        }

        $params = [
            'to_email'   => $email,
            'to_name'    => $full_name !== '' ? $full_name : $first_name,
            'subject'    => $template_id !== '' ? '' : $subject,
            'custom_id'  => $consulta_id,
            'reply_to'   => $reply_to,
        ];

        if ($template_id !== '') {
            $params['template_id'] = $template_id;
            $params['variables'] = [
                'nombre'                              => $first_name !== '' ? $first_name : $full_name,
                'apellido'                            => $last_name,
                'correo'                              => $email,
                'pais'                                => $country !== '' ? $country : 'Prefiere no responder',
                'profesion'                           => $profession !== '' ? $profession : 'Prefiere no responder',
                'nivel_academico'                     => $education_level !== '' ? $education_level : 'Prefiere no responder',
                'programa'                            => $program_name,
                'oferta_academica_nombre'             => $program_name,
                'oferta_academica_url'                => $program_url,
                'oferta_academica_fecha_inicio'       => $start_value !== '' ? $start_value : 'a confirmar',
                'oferta_academica_modalidad'          => $modality !== '' ? $modality : 'a confirmar',
                'oferta_academica_url_preinscripcion' => $preinscripcion_url,
                'url_preinscripcion'                  => $preinscripcion_url,
                'link_preinscripcion'                 => $preinscripcion_url,
                'fecha_inicio'                        => $start_value,
                'url_oferta_academica'                => $program_url,
            ];
        } else {
            $fallback = self::compile_offer_inquiry_fallback($inquiry, $program, $is_open);
            $params['html_part'] = $fallback['html'];
            $params['text_part'] = $fallback['text'];
        }

        return self::send($params);
    }

    /**
     * Envía correo transaccional de consulta sobre un seminario.
     * Regla conservadora:
     * - Si hay TemplateID configurado localmente -> se envía vía TemplateID.
     *   Si Mailjet falla -> se retorna 'failed' sin reintento automático HTML para evitar duplicados.
     * - Si TemplateID no está configurado -> se compila y envía el fallback HTML institucional.
     *
     * @param array $inquiry
     * @param array $seminar
     * @return array
     */
    public static function send_seminar_inquiry(array $inquiry, array $seminar): array {
        $first_name = trim((string) ($inquiry['firstName'] ?? $inquiry['nombre'] ?? ''));
        $last_name = trim((string) ($inquiry['lastName'] ?? $inquiry['apellido'] ?? ''));
        $full_name = trim((string) ($inquiry['fullName'] ?? $inquiry['nombre_apellido'] ?? ''));
        if ($full_name === '') {
            $full_name = trim($first_name . ' ' . $last_name);
        }
        if ($first_name === '' && $full_name !== '') {
            $parts = explode(' ', $full_name);
            $first_name = $parts[0];
        }

        $email = trim((string) ($inquiry['email'] ?? $inquiry['correo'] ?? ''));
        $consulta_id = trim((string) ($inquiry['consultaId'] ?? $inquiry['event_id'] ?? $inquiry['consulta_id'] ?? ''));
        $phone = trim((string) ($inquiry['telefono'] ?? $inquiry['phone'] ?? ''));
        $country = trim((string) ($inquiry['country'] ?? $inquiry['pais'] ?? ''));
        $message = trim((string) ($inquiry['consulta'] ?? $inquiry['message'] ?? ''));

        $seminar_id = $seminar['id'] ?? $seminar['seminario_id'] ?? $inquiry['seminario_id'] ?? '';
        $seminar_name = trim((string) ($seminar['name'] ?? $seminar['title'] ?? $seminar['seminario_titulo'] ?? $inquiry['seminario_titulo'] ?? 'Seminario FLACSO Uruguay'));
        $seminar_url = trim((string) ($seminar['urlBase'] ?? $seminar['url'] ?? $seminar['link'] ?? 'https://flacso.edu.uy/formacion/seminarios/'));
        $preinscripcion_url = trim((string) ($seminar['preinscripcionUrl'] ?? $seminar['preinscripcion_url'] ?? ''));
        $start_value = self::format_start_value(
            trim((string) ($seminar['startValue'] ?? $seminar['periodo_inicio'] ?? $seminar['fecha_inicio'] ?? '')),
            'dia'
        );
        $modality = self::humanize_modality(
            trim((string) ($seminar['modalityLabel'] ?? $seminar['modalidad'] ?? ''))
        );

        $template_id = function_exists('get_option')
            ? trim((string) get_option(self::OPTION_TEMPLATE_CONSULTA_SEMINARIO, ''))
            : '';

        $subject = sprintf('Información solicitada: %s', $seminar_name ?: 'Seminario FLACSO Uruguay');

        $reply_to = trim((string) ($inquiry['replyToEmail'] ?? $inquiry['reply_to'] ?? ''));
        if ($reply_to === '') {
            $reply_to = 'inscripciones@flacso.edu.uy';
        }

        $params = [
            'to_email'   => $email,
            'to_name'    => $full_name !== '' ? $full_name : $first_name,
            'subject'    => $template_id !== '' ? '' : $subject,
            'custom_id'  => $consulta_id,
            'reply_to'   => $reply_to,
        ];

        if ($template_id !== '') {
            $params['template_id'] = $template_id;
            $params['variables'] = [
                'nombre'             => $first_name !== '' ? $first_name : $full_name,
                'apellido'           => $last_name,
                'correo'             => $email,
                'telefono'           => $phone,
                'pais'               => $country !== '' ? $country : 'Prefiere no responder',
                'consulta'           => $message,
                'seminario_id'       => $seminar_id,
                'seminario_titulo'   => $seminar_name,
                'seminario_url'      => $seminar_url,
                'preinscripcion_url' => $preinscripcion_url,
                'periodo_inicio'     => $start_value,
                'modalidad'          => $modality,
            ];
        } else {
            $fallback = self::compile_seminar_inquiry_fallback($inquiry, $seminar);
            $params['html_part'] = $fallback['html'];
            $params['text_part'] = $fallback['text'];
        }

        return self::send($params);
    }

    /**
     * Compila el cuerpo HTML y texto institucional para consultas de oferta académica.
     *
     * @param array $inquiry
     * @param array $program
     * @param bool $is_open
     * @return array ['html' => string, 'text' => string]
     */
    private static function compile_offer_inquiry_fallback(array $inquiry, array $program, bool $is_open): array {
        $first_name = trim((string) ($inquiry['firstName'] ?? $inquiry['nombre'] ?? ''));
        $full_name = trim((string) ($inquiry['fullName'] ?? $inquiry['nombre_apellido'] ?? $first_name));
        $display_name = $full_name !== '' ? $full_name : 'interesado/a';

        $program_name = trim((string) ($program['name'] ?? $program['titulo_posgrado'] ?? $program['title'] ?? 'propuesta académica'));
        $program_url = trim((string) ($program['urlBase'] ?? $program['programUrl'] ?? $program['url'] ?? $program['link'] ?? 'https://flacso.edu.uy/formacion/'));
        $preinscripcion_url = trim((string) ($program['preinscripcionUrl'] ?? $program['preinscripcion_url'] ?? ''));
        $start_value = trim((string) ($program['startValue'] ?? $program['fecha_inicio'] ?? ''));
        $modality = trim((string) ($program['modalityLabel'] ?? $program['modalidad'] ?? ''));

        $country = trim((string) ($inquiry['country'] ?? $inquiry['pais'] ?? 'Prefiere no responder'));
        $profession = trim((string) ($inquiry['profession'] ?? $inquiry['profesion'] ?? 'Prefiere no responder'));
        $education_level = trim((string) ($inquiry['educationLevel'] ?? $inquiry['nivel_academico'] ?? 'Prefiere no responder'));
        $email = trim((string) ($inquiry['email'] ?? $inquiry['correo'] ?? ''));

        $start_html = '';
        if ($start_value !== '') {
            $start_html = sprintf('<p style="margin:0 0 16px;font-size:15px;color:#16396f;"><strong>Inicio previsto:</strong> %s%s</p>',
                htmlspecialchars($start_value, ENT_QUOTES, 'UTF-8'),
                $modality !== '' ? ' (' . htmlspecialchars($modality, ENT_QUOTES, 'UTF-8') . ')' : ''
            );
        }

        $buttons_html = '';
        if ($is_open) {
            if ($program_url !== '') {
                $buttons_html .= self::build_button('Ver propuesta completa', $program_url, '#16396f');
            }
            if ($preinscripcion_url !== '') {
                $buttons_html .= self::build_button('Formulario de Preinscripción', $preinscripcion_url, '#27a844');
            }
        } else {
            $buttons_html .= self::build_button('Ver Seminarios disponibles', 'https://flacso.edu.uy/formacion/seminarios/', '#e67e22');
        }

        $body_content_html = '';
        $body_content_text = [];

        if ($is_open) {
            $body_content_html .= sprintf('<p style="margin:0 0 16px;font-size:16px;line-height:1.6;">Estimada/o <strong>%s</strong>,</p>', htmlspecialchars($display_name, ENT_QUOTES, 'UTF-8'));
            $body_content_html .= sprintf('<p style="margin:0 0 16px;font-size:16px;line-height:1.6;">Esperamos que te encuentres muy bien. Te hacemos llegar la información sobre <strong>%s</strong>.</p>', htmlspecialchars($program_name, ENT_QUOTES, 'UTF-8'));
            $body_content_html .= $start_html;
            $body_content_html .= '<p style="margin:0 0 16px;font-size:16px;line-height:1.6;">Nuestra Facultad de Posgrados forma profesionales con excelencia académica, proyección regional y compromiso social. Nuestras cursadas cuentan con acompañamiento personalizado y modalidades flexibles.</p>';
            $body_content_html .= $buttons_html;

            $body_content_text[] = sprintf('Estimada/o %s,', $display_name);
            $body_content_text[] = '';
            $body_content_text[] = sprintf('Esperamos que te encuentres muy bien. Te hacemos llegar la información sobre %s.', $program_name);
            if ($start_value !== '') {
                $body_content_text[] = sprintf('Inicio previsto: %s %s', $start_value, $modality ? '(' . $modality . ')' : '');
            }
            $body_content_text[] = '';
            if ($program_url !== '') {
                $body_content_text[] = sprintf('Propuesta completa: %s', $program_url);
            }
            if ($preinscripcion_url !== '') {
                $body_content_text[] = sprintf('Preinscripción: %s', $preinscripcion_url);
            }
        } else {
            $body_content_html .= sprintf('<p style="margin:0 0 16px;font-size:16px;line-height:1.6;">Estimada/o <strong>%s</strong>,</p>', htmlspecialchars($display_name, ENT_QUOTES, 'UTF-8'));
            $body_content_html .= sprintf('<p style="margin:0 0 16px;font-size:16px;line-height:1.6;">Gracias por tu interés en nuestras propuestas de formación. En este momento, las inscripciones para <strong>%s</strong> no se encuentran abiertas.</p>', htmlspecialchars($program_name, ENT_QUOTES, 'UTF-8'));
            $body_content_html .= '<p style="margin:0 0 16px;font-size:16px;line-height:1.6;">Actualmente contamos con inscripciones disponibles para nuestros seminarios. Cuando se reabra el período de admisiones para este programa, nos pondremos en contacto contigo.</p>';
            $body_content_html .= $buttons_html;

            $body_content_text[] = sprintf('Estimada/o %s,', $display_name);
            $body_content_text[] = '';
            $body_content_text[] = sprintf('Gracias por tu interés en nuestras propuestas de formación. En este momento, las inscripciones para %s no se encuentran abiertas.', $program_name);
            $body_content_text[] = 'Actualmente contamos con inscripciones disponibles para nuestros seminarios: https://flacso.edu.uy/formacion/seminarios/';
        }

        $html = '<div style="margin:0 auto;max-width:680px;background:#ffffff;font-family:Arial,sans-serif;color:#16324f;font-size:16px;line-height:1.6;">';
        $html .= '<div style="background:#16396f;color:#ffffff;padding:24px 20px;text-align:center;">';
        $html .= '<h1 style="margin:0;font-size:22px;line-height:1.3;">FLACSO Uruguay · Información solicitada</h1>';
        $html .= '</div>';
        $html .= '<div style="padding:24px 22px;">';
        $html .= $body_content_html;
        $html .= '<div style="margin:22px 0 0;padding:18px;border-radius:10px;background:#edf3fb;">';
        $html .= '<h2 style="margin:0 0 12px;font-size:16px;color:#16396f;">Datos registrados en tu consulta</h2>';
        $html .= '<ul style="margin:0;padding-left:18px;line-height:1.6;font-size:14px;">';
        $html .= sprintf('<li><strong>País:</strong> %s</li>', htmlspecialchars($country, ENT_QUOTES, 'UTF-8'));
        if ($email !== '') {
            $html .= sprintf('<li><strong>Correo:</strong> %s</li>', htmlspecialchars($email, ENT_QUOTES, 'UTF-8'));
        }
        $html .= sprintf('<li><strong>Nivel académico:</strong> %s</li>', htmlspecialchars($education_level, ENT_QUOTES, 'UTF-8'));
        $html .= sprintf('<li><strong>Profesión:</strong> %s</li>', htmlspecialchars($profession, ENT_QUOTES, 'UTF-8'));
        $html .= '</ul>';
        $html .= '</div>';
        $html .= '</div>';
        $html .= '<div style="background:#16396f;color:#ffffff;padding:18px 20px;font-size:13px;text-align:center;">';
        $html .= '<p style="margin:0 0 6px;">Saludos cordiales,<br/><strong>Equipo FLACSO Uruguay</strong></p>';
        $html .= '<p style="margin:0;">Por consultas: <a href="mailto:secretaria@flacso.edu.uy" style="color:#c9defa;">secretaria@flacso.edu.uy</a></p>';
        $html .= '</div>';
        $html .= '</div>';

        $body_content_text[] = '';
        $body_content_text[] = 'Datos registrados:';
        $body_content_text[] = sprintf('- País: %s', $country);
        if ($email !== '') {
            $body_content_text[] = sprintf('- Correo: %s', $email);
        }
        $body_content_text[] = sprintf('- Nivel académico: %s', $education_level);
        $body_content_text[] = sprintf('- Profesión: %s', $profession);
        $body_content_text[] = '';
        $body_content_text[] = 'Por consultas: secretaria@flacso.edu.uy';

        return [
            'html' => $html,
            'text' => implode("\n", $body_content_text),
        ];
    }

    /**
     * Compila el cuerpo HTML y texto institucional para consultas de seminarios.
     *
     * @param array $inquiry
     * @param array $seminar
     * @return array ['html' => string, 'text' => string]
     */
    private static function compile_seminar_inquiry_fallback(array $inquiry, array $seminar): array {
        $first_name = trim((string) ($inquiry['firstName'] ?? $inquiry['nombre'] ?? ''));
        $full_name = trim((string) ($inquiry['fullName'] ?? $inquiry['nombre_apellido'] ?? $first_name));
        $display_name = $full_name !== '' ? $full_name : 'interesado/a';

        $seminar_name = trim((string) ($seminar['name'] ?? $seminar['title'] ?? $seminar['seminario_titulo'] ?? $inquiry['seminario_titulo'] ?? 'Seminario FLACSO Uruguay'));
        $seminar_url = trim((string) ($seminar['urlBase'] ?? $seminar['url'] ?? $seminar['link'] ?? 'https://flacso.edu.uy/formacion/seminarios/'));
        $preinscripcion_url = trim((string) ($seminar['preinscripcionUrl'] ?? $seminar['preinscripcion_url'] ?? ''));
        $start_value = trim((string) ($seminar['startValue'] ?? $seminar['periodo_inicio'] ?? $seminar['fecha_inicio'] ?? ''));
        $modality = trim((string) ($seminar['modalityLabel'] ?? $seminar['modalidad'] ?? ''));
        $consulta = trim((string) ($inquiry['consulta'] ?? $inquiry['message'] ?? ''));

        $start_html = '';
        if ($start_value !== '') {
            $start_html = sprintf('<p style="margin:0 0 16px;font-size:15px;color:#16396f;"><strong>Período de inicio:</strong> %s%s</p>',
                htmlspecialchars($start_value, ENT_QUOTES, 'UTF-8'),
                $modality !== '' ? ' (' . htmlspecialchars($modality, ENT_QUOTES, 'UTF-8') . ')' : ''
            );
        }

        $buttons_html = '';
        if ($seminar_url !== '') {
            $buttons_html .= self::build_button('Ver ficha del seminario', $seminar_url, '#16396f');
        }
        if ($preinscripcion_url !== '') {
            $buttons_html .= self::build_button('Formulario de Preinscripción', $preinscripcion_url, '#27a844');
        }

        $html = '<div style="margin:0 auto;max-width:680px;background:#ffffff;font-family:Arial,sans-serif;color:#16324f;font-size:16px;line-height:1.6;">';
        $html .= '<div style="background:#16396f;color:#ffffff;padding:24px 20px;text-align:center;">';
        $html .= '<h1 style="margin:0;font-size:22px;line-height:1.3;">FLACSO Uruguay · Información sobre Seminario</h1>';
        $html .= '</div>';
        $html .= '<div style="padding:24px 22px;">';
        $html .= sprintf('<p style="margin:0 0 16px;font-size:16px;line-height:1.6;">Estimada/o <strong>%s</strong>,</p>', htmlspecialchars($display_name, ENT_QUOTES, 'UTF-8'));
        $html .= sprintf('<p style="margin:0 0 16px;font-size:16px;line-height:1.6;">Te enviamos la información solicitada sobre el seminario <strong>%s</strong>.</p>', htmlspecialchars($seminar_name, ENT_QUOTES, 'UTF-8'));
        $html .= $start_html;
        $html .= $buttons_html;

        if ($consulta !== '') {
            $html .= '<div style="margin:16px 0;padding:14px;border-radius:8px;background:#f8fafc;border-left:4px solid #16396f;">';
            $html .= sprintf('<p style="margin:0;font-size:14px;line-height:1.5;"><strong>Tu consulta:</strong> %s</p>', htmlspecialchars($consulta, ENT_QUOTES, 'UTF-8'));
            $html .= '</div>';
        }

        $html .= '</div>';
        $html .= '<div style="background:#16396f;color:#ffffff;padding:18px 20px;font-size:13px;text-align:center;">';
        $html .= '<p style="margin:0 0 6px;">Saludos cordiales,<br/><strong>Equipo FLACSO Uruguay</strong></p>';
        $html .= '<p style="margin:0;">Por consultas: <a href="mailto:inscripciones@flacso.edu.uy" style="color:#c9defa;">inscripciones@flacso.edu.uy</a></p>';
        $html .= '</div>';
        $html .= '</div>';

        $text_lines = [
            sprintf('Estimada/o %s,', $display_name),
            '',
            sprintf('Te enviamos la información solicitada sobre el seminario %s.', $seminar_name),
        ];
        if ($start_value !== '') {
            $text_lines[] = sprintf('Período de inicio: %s %s', $start_value, $modality ? '(' . $modality . ')' : '');
        }
        $text_lines[] = '';
        if ($seminar_url !== '') {
            $text_lines[] = sprintf('Ficha del seminario: %s', $seminar_url);
        }
        if ($preinscripcion_url !== '') {
            $text_lines[] = sprintf('Preinscripción: %s', $preinscripcion_url);
        }
        if ($consulta !== '') {
            $text_lines[] = '';
            $text_lines[] = sprintf('Tu consulta: %s', $consulta);
        }
        $text_lines[] = '';
        $text_lines[] = 'Por consultas: inscripciones@flacso.edu.uy';

        return [
            'html' => $html,
            'text' => implode("\n", $text_lines),
        ];
    }

    /**
     * Genera un botón de llamado a la acción para correos HTML.
     *
     * @param string $label
     * @param string $url
     * @param string $bg_color
     * @param string $text_color
     * @return string
     */
    private static function build_button(string $label, string $url, string $bg_color, string $text_color = '#ffffff'): string {
        $safe_url = trim($url);
        if ($safe_url === '') {
            return '';
        }

        return sprintf(
            '<p style="margin:12px 0 14px;text-align:center;"><a href="%s" style="display:inline-block;padding:11px 20px;border-radius:24px;background:%s;color:%s;text-decoration:none;font-weight:bold;font-size:14px;">%s</a></p>',
            htmlspecialchars($safe_url, ENT_QUOTES, 'UTF-8'),
            htmlspecialchars($bg_color, ENT_QUOTES, 'UTF-8'),
            htmlspecialchars($text_color, ENT_QUOTES, 'UTF-8'),
            htmlspecialchars($label, ENT_QUOTES, 'UTF-8')
        );
    }
}
