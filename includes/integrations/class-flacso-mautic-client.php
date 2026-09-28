<?php

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Cliente HTTP para interactuar con la API REST de Mautic.
 *
 * Soporta autenticación Basic y Bearer (Personal Access Tokens),
 * búsqueda y deduplicación de contactos por email, creación (POST /api/contacts/new)
 * y actualización (PATCH /api/contacts/{id}/edit) con tags y campos de perfil.
 */
class FLACSO_Mautic_Client {
    public const OPTION_ENABLED = 'flacso_mautic_enabled';
    public const OPTION_BASE_URL = 'flacso_mautic_base_url';
    public const OPTION_AUTH_TYPE = 'flacso_mautic_auth_type';
    public const OPTION_USERNAME = 'flacso_mautic_username';
    public const OPTION_PASSWORD = 'flacso_mautic_password';
    public const OPTION_TOKEN = 'flacso_mautic_token';

    private const DEFAULT_BASE_URL = 'https://envios.flacso.edu.uy';
    private const DEFAULT_AUTH_TYPE = 'basic';
    private const TIMEOUT_SECONDS = 4;

    /**
     * Obtiene la configuración normalizada de la integración con Mautic.
     *
     * @return array{
     *     enabled: bool,
     *     base_url: string,
     *     auth_type: string,
     *     username: string,
     *     password: string,
     *     token: string
     * }
     */
    public static function get_settings(): array {
        $enabled_raw = function_exists('get_option') ? get_option(self::OPTION_ENABLED, '0') : '0';
        $enabled = !empty($enabled_raw) && $enabled_raw !== '0' && $enabled_raw !== 0;

        $base_url_raw = function_exists('get_option') ? get_option(self::OPTION_BASE_URL, self::DEFAULT_BASE_URL) : self::DEFAULT_BASE_URL;
        $base_url = rtrim(trim((string) $base_url_raw), '/');
        if ($base_url_raw === null || $base_url_raw === false) {
            $base_url = self::DEFAULT_BASE_URL;
        }

        $auth_type_raw = function_exists('get_option') ? get_option(self::OPTION_AUTH_TYPE, self::DEFAULT_AUTH_TYPE) : self::DEFAULT_AUTH_TYPE;
        $auth_type = strtolower(trim((string) $auth_type_raw));
        if (!in_array($auth_type, ['basic', 'bearer'], true)) {
            $auth_type = self::DEFAULT_AUTH_TYPE;
        }

        $username = function_exists('get_option') ? trim((string) get_option(self::OPTION_USERNAME, '')) : '';
        $password = function_exists('get_option') ? trim((string) get_option(self::OPTION_PASSWORD, '')) : '';
        $token = function_exists('get_option') ? trim((string) get_option(self::OPTION_TOKEN, '')) : '';

        return [
            'enabled'   => $enabled,
            'base_url'  => $base_url,
            'auth_type' => $auth_type,
            'username'  => $username,
            'password'  => $password,
            'token'     => $token,
        ];
    }

    /**
     * Verifica si Mautic está habilitado y cuenta con las credenciales mínimas según su tipo de autenticación.
     *
     * @return bool
     */
    public static function is_configured(): bool {
        $settings = self::get_settings();

        if (!$settings['enabled'] || empty($settings['base_url'])) {
            return false;
        }

        if ($settings['auth_type'] === 'bearer') {
            return !empty($settings['token']);
        }

        return !empty($settings['username']) && !empty($settings['password']);
    }

    /**
     * Realiza una prueba de conexión a la API de Mautic ejecutando GET /api/contacts?limit=1.
     *
     * @return array{ok: bool, code: int, message: string}
     */
    public static function test_connection(): array {
        $settings = self::get_settings();

        if (!self::is_configured()) {
            return [
                'ok'      => false,
                'code'    => 0,
                'message' => 'Mautic no está configurado o está deshabilitado.',
            ];
        }

        $url = $settings['base_url'] . '/api/contacts?limit=1';
        $args = [
            'method'  => 'GET',
            'headers' => self::get_request_headers($settings),
            'timeout' => self::TIMEOUT_SECONDS,
        ];

        $response = function_exists('wp_remote_request')
            ? wp_remote_request($url, $args)
            : (function_exists('wp_remote_get') ? wp_remote_get($url, $args) : null);

        if (is_wp_error($response)) {
            return [
                'ok'      => false,
                'code'    => 0,
                'message' => $response->get_error_message(),
            ];
        }

        if (!is_array($response)) {
            return [
                'ok'      => false,
                'code'    => 0,
                'message' => 'Respuesta HTTP inválida o no disponible.',
            ];
        }

        $code = function_exists('wp_remote_retrieve_response_code')
            ? (int) wp_remote_retrieve_response_code($response)
            : ($response['response']['code'] ?? 0);

        if ($code >= 200 && $code < 300) {
            return [
                'ok'      => true,
                'code'    => $code,
                'message' => 'Conexión exitosa con Mautic.',
            ];
        }

        $error_msg = self::extract_error_message($response, $code);

        return [
            'ok'      => false,
            'code'    => $code,
            'message' => $error_msg,
        ];
    }

    /**
     * Busca un contacto en Mautic por su correo electrónico.
     *
     * @param string $email
     * @return array|null Datos del primer contacto encontrado o null si no existe o falla.
     */
    public static function find_contact_by_email(string $email): ?array {
        $email = strtolower(trim($email));
        if ($email === '') {
            return null;
        }

        if (!self::is_configured()) {
            return null;
        }

        $settings = self::get_settings();
        $url = $settings['base_url'] . '/api/contacts?search=email:' . urlencode($email);

        $args = [
            'method'  => 'GET',
            'headers' => self::get_request_headers($settings),
            'timeout' => self::TIMEOUT_SECONDS,
        ];

        $response = function_exists('wp_remote_request')
            ? wp_remote_request($url, $args)
            : (function_exists('wp_remote_get') ? wp_remote_get($url, $args) : null);

        if (is_wp_error($response) || !is_array($response)) {
            return null;
        }

        $code = function_exists('wp_remote_retrieve_response_code')
            ? (int) wp_remote_retrieve_response_code($response)
            : ($response['response']['code'] ?? 0);

        if ($code < 200 || $code >= 300) {
            return null;
        }

        $body = function_exists('wp_remote_retrieve_body')
            ? wp_remote_retrieve_body($response)
            : ($response['body'] ?? '');

        $data = json_decode($body, true);
        if (!is_array($data) || empty($data['contacts']) || !is_array($data['contacts'])) {
            return null;
        }

        $first = reset($data['contacts']);
        return is_array($first) ? $first : null;
    }

    /**
     * Crea o actualiza un contacto en Mautic con deduplicación por correo.
     *
     * @param string $email
     * @param array $fields
     * @param array $tags
     * @return array{
     *     ok: bool,
     *     contact_id: ?int,
     *     action: 'created'|'updated'|'failed',
     *     error: ?string
     * }
     */
    public static function create_or_update_contact(string $email, array $fields = [], array $tags = []): array {
        $email = strtolower(trim($email));
        if ($email === '') {
            return [
                'ok'         => false,
                'contact_id' => null,
                'action'     => 'failed',
                'error'      => 'Email inválido o vacío.',
            ];
        }

        if (!self::is_configured()) {
            return [
                'ok'         => false,
                'contact_id' => null,
                'action'     => 'failed',
                'error'      => 'Mautic no está configurado o está deshabilitado.',
            ];
        }

        $settings = self::get_settings();
        $existing = self::find_contact_by_email($email);

        if ($existing !== null && !empty($existing['id'])) {
            // Actualizar contacto existente
            $contact_id = (int) $existing['id'];
            $url = $settings['base_url'] . '/api/contacts/' . $contact_id . '/edit';
            $action = 'updated';
            $method = 'PATCH';

            $payload = $fields;
            if (!empty($tags)) {
                $payload['tags'] = array_values(array_unique($tags));
            }
        } else {
            // Crear nuevo contacto
            $contact_id = null;
            $url = $settings['base_url'] . '/api/contacts/new';
            $action = 'created';
            $method = 'POST';

            $payload = $fields;
            $payload['email'] = $email;
            if (!empty($tags)) {
                $payload['tags'] = array_values(array_unique($tags));
            }
        }

        $args = [
            'method'  => $method,
            'headers' => self::get_request_headers($settings),
            'body'    => function_exists('wp_json_encode') ? wp_json_encode($payload) : json_encode($payload),
            'timeout' => self::TIMEOUT_SECONDS,
        ];

        $response = function_exists('wp_remote_request')
            ? wp_remote_request($url, $args)
            : (function_exists('wp_remote_post') && $method === 'POST' ? wp_remote_post($url, $args) : null);

        if (is_wp_error($response)) {
            return [
                'ok'         => false,
                'contact_id' => null,
                'action'     => 'failed',
                'error'      => $response->get_error_message(),
            ];
        }

        if (!is_array($response)) {
            return [
                'ok'         => false,
                'contact_id' => null,
                'action'     => 'failed',
                'error'      => 'Respuesta HTTP inválida o no disponible.',
            ];
        }

        $code = function_exists('wp_remote_retrieve_response_code')
            ? (int) wp_remote_retrieve_response_code($response)
            : ($response['response']['code'] ?? 0);

        $body = function_exists('wp_remote_retrieve_body')
            ? wp_remote_retrieve_body($response)
            : ($response['body'] ?? '');

        $data = json_decode($body, true);

        if ($code >= 200 && $code < 300) {
            $result_id = isset($data['contact']['id']) ? (int) $data['contact']['id'] : $contact_id;
            return [
                'ok'         => true,
                'contact_id' => $result_id,
                'action'     => $action,
                'error'      => null,
            ];
        }

        $error_msg = self::extract_error_message($response, $code);

        return [
            'ok'         => false,
            'contact_id' => null,
            'action'     => 'failed',
            'error'      => $error_msg,
        ];
    }

    /**
     * Despacha una plantilla de correo en Mautic hacia un contacto específico con tokens de personalización.
     *
     * @param int $email_id ID de la plantilla de correo en Mautic.
     * @param int $contact_id ID del contacto en Mautic.
     * @param array $tokens Mapa de tokens de personalización (ej: ['{nombre}' => 'Juan']).
     * @return array{
     *     ok: bool,
     *     status: 'sent'|'failed',
     *     email_id: int,
     *     contact_id: int,
     *     error: ?string
     * }
     */
    public static function send_email_to_contact(int $email_id, int $contact_id, array $tokens = []): array {
        if ($email_id <= 0 || $contact_id <= 0) {
            return [
                'ok'         => false,
                'status'     => 'failed',
                'email_id'   => $email_id,
                'contact_id' => $contact_id,
                'error'      => 'Email ID y Contact ID deben ser enteros positivos.',
            ];
        }

        if (!self::is_configured()) {
            return [
                'ok'         => false,
                'status'     => 'failed',
                'email_id'   => $email_id,
                'contact_id' => $contact_id,
                'error'      => 'Mautic no está configurado o está deshabilitado.',
            ];
        }

        $settings = self::get_settings();
        $url = $settings['base_url'] . '/api/emails/' . $email_id . '/contact/' . $contact_id . '/send';

        $payload = [
            'tokens' => $tokens,
        ];

        $args = [
            'method'  => 'POST',
            'headers' => self::get_request_headers($settings),
            'body'    => function_exists('wp_json_encode') ? wp_json_encode($payload) : json_encode($payload),
            'timeout' => self::TIMEOUT_SECONDS,
        ];

        $response = function_exists('wp_remote_request')
            ? wp_remote_request($url, $args)
            : (function_exists('wp_remote_post') ? wp_remote_post($url, $args) : null);

        if (is_wp_error($response)) {
            return [
                'ok'         => false,
                'status'     => 'failed',
                'email_id'   => $email_id,
                'contact_id' => $contact_id,
                'error'      => $response->get_error_message(),
            ];
        }

        if (!is_array($response)) {
            return [
                'ok'         => false,
                'status'     => 'failed',
                'email_id'   => $email_id,
                'contact_id' => $contact_id,
                'error'      => 'Respuesta HTTP inválida o no disponible.',
            ];
        }

        $code = function_exists('wp_remote_retrieve_response_code')
            ? (int) wp_remote_retrieve_response_code($response)
            : ($response['response']['code'] ?? 0);

        $body = function_exists('wp_remote_retrieve_body')
            ? wp_remote_retrieve_body($response)
            : ($response['body'] ?? '');

        $data = json_decode($body, true);

        if ($code >= 200 && $code < 300) {
            $is_success = is_array($data) && (!empty($data['success']) || !empty($data['result']));
            if ($is_success) {
                return [
                    'ok'         => true,
                    'status'     => 'sent',
                    'email_id'   => $email_id,
                    'contact_id' => $contact_id,
                    'error'      => null,
                ];
            }

            $error_msg = null;
            if (is_array($data) && !empty($data['failedRecipients'])) {
                $failed = is_array($data['failedRecipients'])
                    ? implode(', ', array_map(function($item) {
                        return is_scalar($item) ? (string) $item : json_encode($item);
                    }, $data['failedRecipients']))
                    : (string) $data['failedRecipients'];
                $error_msg = 'Destinatarios fallidos: ' . $failed;
            } else {
                $error_msg = self::extract_error_message($response, $code);
            }

            return [
                'ok'         => false,
                'status'     => 'failed',
                'email_id'   => $email_id,
                'contact_id' => $contact_id,
                'error'      => $error_msg,
            ];
        }

        $error_msg = self::extract_error_message($response, $code);

        return [
            'ok'         => false,
            'status'     => 'failed',
            'email_id'   => $email_id,
            'contact_id' => $contact_id,
            'error'      => $error_msg,
        ];
    }

    /**
     * Prepara las cabeceras HTTP de autenticación y contenido para las peticiones a la API de Mautic.
     *
     * @param array $settings
     * @return array
     */
    private static function get_request_headers(array $settings): array {
        $headers = [
            'Accept'       => 'application/json',
            'Content-Type' => 'application/json',
        ];

        if ($settings['auth_type'] === 'bearer') {
            $headers['Authorization'] = 'Bearer ' . $settings['token'];
        } else {
            $headers['Authorization'] = 'Basic ' . base64_encode($settings['username'] . ':' . $settings['password']);
        }

        return $headers;
    }

    /**
     * Extrae un mensaje de error legible a partir de la respuesta devuelta por Mautic o cURL.
     *
     * @param array|\WP_Error $response
     * @param int $code
     * @return string
     */
    private static function extract_error_message($response, int $code = 0): string {
        if (is_wp_error($response)) {
            return $response->get_error_message();
        }

        $body = function_exists('wp_remote_retrieve_body')
            ? wp_remote_retrieve_body($response)
            : ($response['body'] ?? '');

        if (!empty($body)) {
            $data = json_decode($body, true);
            if (is_array($data)) {
                if (!empty($data['failedRecipients'])) {
                    $failed = is_array($data['failedRecipients'])
                        ? implode(', ', array_map(function($item) {
                            return is_scalar($item) ? (string) $item : json_encode($item);
                        }, $data['failedRecipients']))
                        : (string) $data['failedRecipients'];
                    return 'Destinatarios fallidos: ' . $failed;
                }
                if (!empty($data['errors']) && is_array($data['errors'])) {
                    $first = reset($data['errors']);
                    if (is_array($first) && !empty($first['message'])) {
                        return (string) $first['message'];
                    }
                    if (is_string($first)) {
                        return $first;
                    }
                }
                if (!empty($data['message']) && is_string($data['message'])) {
                    return $data['message'];
                }
                if (!empty($data['error']) && is_string($data['error'])) {
                    return $data['error'];
                }
            }
        }

        return sprintf('Error en API Mautic (HTTP %d)', $code);
    }
}
