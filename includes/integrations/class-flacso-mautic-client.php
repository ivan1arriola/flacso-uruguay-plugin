<?php

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Cliente HTTP para interactuar con la API REST de Mautic.
 *
 * Soporta OAuth2 Client Credentials (recomendado), Basic y Bearer manual,
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
    public const OPTION_CLIENT_ID = 'flacso_mautic_client_id';
    public const OPTION_CLIENT_SECRET = 'flacso_mautic_client_secret';

    private const DEFAULT_BASE_URL = 'https://envios.flacso.edu.uy';
    private const DEFAULT_AUTH_TYPE = 'oauth2';
    private const TIMEOUT_SECONDS = 4;

    /** @var callable|null Transporte HTTP inyectable exclusivamente para pruebas. */
    private static $http_transport = null;

    /** @var array<string,array{token:string,expires_at:int}> */
    private static $oauth2_token_cache = [];

    /** @var string */
    private static $last_auth_error = '';

    public static function set_http_transport(?callable $transport): void {
        self::$http_transport = $transport;
    }

    private static function http_request(string $url, array $args) {
        if (is_callable(self::$http_transport)) {
            return call_user_func(self::$http_transport, $url, $args);
        }

        if (function_exists('wp_remote_request')) {
            return wp_remote_request($url, $args);
        }

        $method = strtoupper((string) ($args['method'] ?? 'GET'));
        if ($method === 'GET' && function_exists('wp_remote_get')) {
            return wp_remote_get($url, $args);
        }
        if ($method === 'POST' && function_exists('wp_remote_post')) {
            return wp_remote_post($url, $args);
        }

        return null;
    }

    /**
     * Obtiene la configuración normalizada de la integración con Mautic.
     *
     * @return array{
     *     enabled: bool,
     *     base_url: string,
     *     auth_type: string,
     *     username: string,
     *     password: string,
     *     token: string,
     *     client_id: string,
     *     client_secret: string
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
        if (!in_array($auth_type, ['oauth2', 'basic', 'bearer'], true)) {
            $auth_type = self::DEFAULT_AUTH_TYPE;
        }

        $username = function_exists('get_option') ? trim((string) get_option(self::OPTION_USERNAME, '')) : '';
        $password = function_exists('get_option') ? trim((string) get_option(self::OPTION_PASSWORD, '')) : '';
        $token = function_exists('get_option') ? trim((string) get_option(self::OPTION_TOKEN, '')) : '';
        $client_id = function_exists('get_option') ? trim((string) get_option(self::OPTION_CLIENT_ID, '')) : '';
        $client_secret = function_exists('get_option') ? trim((string) get_option(self::OPTION_CLIENT_SECRET, '')) : '';

        return [
            'enabled'       => $enabled,
            'base_url'      => $base_url,
            'auth_type'     => $auth_type,
            'username'      => $username,
            'password'      => $password,
            'token'         => $token,
            'client_id'     => $client_id,
            'client_secret' => $client_secret,
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

        if ($settings['auth_type'] === 'oauth2') {
            return !empty($settings['client_id']) && !empty($settings['client_secret']);
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

        $headers = self::get_request_headers($settings);
        if (empty($headers['Authorization'])) {
            return [
                'ok'      => false,
                'code'    => 0,
                'message' => self::$last_auth_error !== '' ? self::$last_auth_error : 'No se pudo obtener una credencial válida para Mautic.',
            ];
        }

        $url = $settings['base_url'] . '/api/contacts?limit=1';
        $args = [
            'method'  => 'GET',
            'headers' => $headers,
            'timeout' => self::TIMEOUT_SECONDS,
        ];

        $response = self::http_request($url, $args);

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
     * Prueba de solo lectura sobre el endpoint de contactos.
     *
     * @return array{ok: bool, code: int, message: string}
     */
    public static function test_contact_search(string $email): array {
        $email = strtolower(trim($email));
        if ($email === '' || filter_var($email, FILTER_VALIDATE_EMAIL) === false) {
            return ['ok' => false, 'code' => 0, 'message' => 'Ingresa un correo válido para la prueba.'];
        }

        $response = self::request_contact_endpoint(
            '/api/contacts?search=email:' . urlencode($email) . '&limit=5',
            'GET'
        );
        if (empty($response['ok'])) {
            return [
                'ok' => false,
                'code' => (int) ($response['http_code'] ?? 0),
                'message' => (string) ($response['error'] ?? 'No se pudo consultar contactos.'),
            ];
        }

        $contacts = $response['data']['contacts'] ?? [];
        $count = is_array($contacts) ? count($contacts) : 0;

        return [
            'ok' => true,
            'code' => (int) ($response['http_code'] ?? 200),
            'message' => $count > 0
                ? sprintf('Mautic respondió correctamente: %d contacto(s) encontrado(s).', $count)
                : 'Mautic respondió correctamente; no hay contactos con ese correo.',
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

        $response = self::http_request($url, $args);

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
     * Obtiene las etiquetas actualmente asociadas a un contacto.
     *
     * @return array{ok: bool, status: string, http_code: int, error: ?string, tags: array}
     */
    public static function get_contact_tags(int $contact_id): array {
        if ($contact_id <= 0) {
            return self::contact_operation_failure('El ID de contacto debe ser un entero positivo.');
        }

        $response = self::request_contact_endpoint('/api/contacts/' . $contact_id, 'GET');
        if (!$response['ok']) {
            return $response;
        }

        $contact = isset($response['data']['contact']) && is_array($response['data']['contact'])
            ? $response['data']['contact']
            : [];
        $response['tags'] = self::normalize_tags($contact['tags'] ?? []);

        return $response;
    }

    /**
     * Une etiquetas nuevas a las existentes sin eliminar las previamente guardadas.
     *
     * @return array{ok: bool, status: string, http_code: int, error: ?string, tags: array}
     */
    public static function merge_contact_tags(int $contact_id, array $tags): array {
        $current = self::get_contact_tags($contact_id);
        if (!$current['ok']) {
            return $current;
        }

        $merged = self::normalize_tags(array_merge($current['tags'], $tags));
        $response = self::request_contact_endpoint(
            '/api/contacts/' . $contact_id . '/edit',
            'PATCH',
            ['tags' => $merged]
        );
        $response['tags'] = $merged;

        return $response;
    }

    /**
     * Consulta las campañas a las que pertenece un contacto.
     *
     * @return array{ok: bool, status: string, http_code: int, error: ?string, campaigns: array}
     */
    public static function get_contact_campaigns(int $contact_id): array {
        if ($contact_id <= 0) {
            $failure = self::contact_operation_failure('El ID de contacto debe ser un entero positivo.');
            $failure['campaigns'] = [];
            return $failure;
        }

        $response = self::request_contact_endpoint('/api/contacts/' . $contact_id . '/campaigns', 'GET');
        $response['campaigns'] = [];
        if (!$response['ok']) {
            return $response;
        }

        $campaigns = $response['data']['campaigns'] ?? [];
        if (!is_array($campaigns)) {
            $campaigns = [];
        }
        $response['campaigns'] = array_values($campaigns);

        return $response;
    }

    /**
     * Incorpora un contacto a una campaña, sin repetir una membresía existente.
     *
     * @return array{ok: bool, status: string, http_code: int, error: ?string}
     */
    public static function add_contact_to_campaign(int $campaign_id, int $contact_id): array {
        if ($campaign_id <= 0 || $contact_id <= 0) {
            return self::contact_operation_failure('Los IDs de campaña y contacto deben ser enteros positivos.');
        }

        $membership = self::get_contact_campaigns($contact_id);
        if (!$membership['ok']) {
            unset($membership['campaigns']);
            return $membership;
        }

        foreach ($membership['campaigns'] as $campaign) {
            $existing_id = is_array($campaign) ? ($campaign['id'] ?? 0) : $campaign;
            if ((int) $existing_id === $campaign_id) {
                return [
                    'ok'        => true,
                    'status'    => 'already_member',
                    'http_code' => $membership['http_code'],
                    'error'     => null,
                ];
            }
        }

        return self::request_contact_endpoint(
            '/api/campaigns/' . $campaign_id . '/contact/' . $contact_id . '/add',
            'POST',
            []
        );
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
                $current_tags = self::get_contact_tags($contact_id);
                if (!$current_tags['ok']) {
                    return [
                        'ok'         => false,
                        'contact_id' => null,
                        'action'     => 'failed',
                        'error'      => $current_tags['error'],
                    ];
                }
                $payload['tags'] = self::normalize_tags(array_merge($current_tags['tags'], $tags));
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

        $response = self::http_request($url, $args);

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
     * Asegura un destinatario mínimo para una entrega transaccional.
     *
     * Sólo puede escribir email, firstname y lastname. Nunca agrega tags,
     * campañas ni campos flacso_ de una consulta.
     */
    public static function ensure_delivery_recipient(string $email, string $first_name, string $last_name): array {
        $email = strtolower(trim($email));
        if ($email === '' || filter_var($email, FILTER_VALIDATE_EMAIL) === false) {
            return [
                'ok' => false, 'status' => 'failed', 'contact_id' => null,
                'action' => 'failed', 'error' => 'Email inválido o vacío.',
                'acceptance_unknown' => false,
            ];
        }
        if (!self::is_configured()) {
            return [
                'ok' => false, 'status' => 'failed', 'contact_id' => null,
                'action' => 'failed', 'error' => 'Mautic no está configurado o está deshabilitado.',
                'acceptance_unknown' => false,
            ];
        }

        $search = self::search_contact_result($email);
        if (!$search['ok']) {
            return [
                'ok' => false, 'status' => 'failed', 'contact_id' => null,
                'action' => 'failed', 'error' => $search['error'],
                'acceptance_unknown' => false,
            ];
        }

        $payload = [
            'email' => $email,
            'firstname' => trim($first_name),
            'lastname' => trim($last_name),
        ];

        if (!empty($search['contact']['id'])) {
            $contact_id = (int) $search['contact']['id'];
            $updated = self::request_contact_endpoint(
                '/api/contacts/' . $contact_id . '/edit',
                'PATCH',
                $payload
            );
            return [
                'ok' => !empty($updated['ok']),
                'status' => !empty($updated['ok']) ? 'ready' : 'failed',
                'contact_id' => !empty($updated['ok']) ? $contact_id : null,
                'action' => !empty($updated['ok']) ? 'updated' : 'failed',
                'error' => $updated['error'] ?? null,
                'acceptance_unknown' => false,
            ];
        }

        $created = self::request_contact_endpoint('/api/contacts/new', 'POST', $payload);
        if (!empty($created['ok'])) {
            $contact = $created['data']['contact'] ?? [];
            $contact_id = is_array($contact) ? (int) ($contact['id'] ?? 0) : 0;
            if ($contact_id > 0) {
                return [
                    'ok' => true, 'status' => 'ready', 'contact_id' => $contact_id,
                    'action' => 'created', 'error' => null, 'acceptance_unknown' => false,
                ];
            }
        }

        // Un POST con fallo de transporte puede haber llegado a Mautic. Antes
        // de repetirlo se reconcilia por email para no crear duplicados.
        if ((int) ($created['http_code'] ?? 0) === 0) {
            $reconciled = self::search_contact_result($email);
            if (!empty($reconciled['ok']) && !empty($reconciled['contact']['id'])) {
                return [
                    'ok' => true,
                    'status' => 'ready',
                    'contact_id' => (int) $reconciled['contact']['id'],
                    'action' => 'reconciled',
                    'error' => null,
                    'acceptance_unknown' => false,
                ];
            }

            return [
                'ok' => false,
                'status' => 'acceptance_unknown',
                'contact_id' => null,
                'action' => 'unknown',
                'error' => 'No fue posible determinar si Mautic creó el destinatario.',
                'acceptance_unknown' => true,
            ];
        }

        return [
            'ok' => false,
            'status' => 'failed',
            'contact_id' => null,
            'action' => 'failed',
            'error' => $created['error'] ?? 'No fue posible crear el destinatario.',
            'acceptance_unknown' => false,
        ];
    }

    /**
     * Lectura de metadatos de campos para validación de contrato.
     */
    public static function get_contact_fields(): array {
        $response = self::request_contact_endpoint('/api/fields/contact?limit=200', 'GET');
        if (!$response['ok']) {
            return $response;
        }
        $fields = $response['data']['fields'] ?? [];
        $response['fields'] = is_array($fields) ? array_values($fields) : [];
        return $response;
    }

    /**
     * Lectura de una plantilla de email sin mutaciones.
     */
    public static function get_email_template(int $email_id): array {
        if ($email_id <= 0) {
            return self::contact_operation_failure('El ID de email debe ser positivo.');
        }
        $response = self::request_contact_endpoint('/api/emails/' . $email_id, 'GET');
        if (!$response['ok']) {
            return $response;
        }
        $email = $response['data']['email'] ?? [];
        $response['email'] = is_array($email) ? $email : [];
        return $response;
    }

    private static function search_contact_result(string $email): array {
        $response = self::request_contact_endpoint(
            '/api/contacts?search=email:' . urlencode(strtolower(trim($email))),
            'GET'
        );
        if (!$response['ok']) {
            return [
                'ok' => false,
                'contact' => null,
                'http_code' => $response['http_code'] ?? 0,
                'error' => $response['error'] ?? 'Error al buscar contacto.',
            ];
        }

        $contacts = $response['data']['contacts'] ?? [];
        if (!is_array($contacts) || empty($contacts)) {
            return ['ok' => true, 'contact' => null, 'http_code' => $response['http_code'], 'error' => null];
        }

        $first = reset($contacts);
        return [
            'ok' => true,
            'contact' => is_array($first) ? $first : null,
            'http_code' => $response['http_code'],
            'error' => null,
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

        $response = self::http_request($url, $args);

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

            $error_msg = self::extract_error_message($response, $code);

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
     * Envía un acuse transaccional y distingue una respuesta perdida de un
     * rechazo HTTP comprobado. Sólo esta vía se usa por la nueva cola.
     */
    public static function send_transactional_email_to_contact(int $email_id, int $contact_id, array $tokens): array {
        if ($email_id <= 0 || $contact_id <= 0) {
            return [
                'ok' => false, 'status' => 'failed', 'http_code' => 0,
                'email_id' => $email_id, 'contact_id' => $contact_id,
                'error' => 'Email ID y Contact ID deben ser enteros positivos.',
                'acceptance_unknown' => false,
            ];
        }
        if (!self::is_configured()) {
            return [
                'ok' => false, 'status' => 'failed', 'http_code' => 0,
                'email_id' => $email_id, 'contact_id' => $contact_id,
                'error' => 'Mautic no está configurado o está deshabilitado.',
                'acceptance_unknown' => false,
            ];
        }

        $settings = self::get_settings();
        $url = $settings['base_url'] . '/api/emails/' . $email_id . '/contact/' . $contact_id . '/send';
        $args = [
            'method' => 'POST',
            'headers' => self::get_request_headers($settings),
            'body' => function_exists('wp_json_encode')
                ? wp_json_encode(['tokens' => $tokens])
                : json_encode(['tokens' => $tokens]),
            'timeout' => self::TIMEOUT_SECONDS,
        ];

        try {
            $response = self::http_request($url, $args);
        } catch (Throwable $e) {
            return [
                'ok' => false, 'status' => 'acceptance_unknown', 'http_code' => 0,
                'email_id' => $email_id, 'contact_id' => $contact_id,
                'error' => $e->getMessage(), 'acceptance_unknown' => true,
            ];
        }

        if (is_wp_error($response) || !is_array($response)) {
            $error = is_wp_error($response)
                ? $response->get_error_message()
                : 'Respuesta HTTP no disponible después de iniciar el envío.';
            return [
                'ok' => false, 'status' => 'acceptance_unknown', 'http_code' => 0,
                'email_id' => $email_id, 'contact_id' => $contact_id,
                'error' => $error, 'acceptance_unknown' => true,
            ];
        }

        $code = function_exists('wp_remote_retrieve_response_code')
            ? (int) wp_remote_retrieve_response_code($response)
            : (int) ($response['response']['code'] ?? 0);
        $body = function_exists('wp_remote_retrieve_body')
            ? wp_remote_retrieve_body($response)
            : (string) ($response['body'] ?? '');
        $data = json_decode($body, true);

        if ($code >= 200 && $code < 300) {
            return [
                'ok' => true, 'status' => 'accepted', 'http_code' => $code,
                'email_id' => $email_id, 'contact_id' => $contact_id,
                'error' => null, 'acceptance_unknown' => false,
                'data' => is_array($data) ? $data : [],
            ];
        }

        return [
            'ok' => false,
            'status' => $code >= 500 ? 'retryable_failed' : 'failed',
            'http_code' => $code,
            'email_id' => $email_id,
            'contact_id' => $contact_id,
            'error' => self::extract_error_message($response, $code),
            'acceptance_unknown' => false,
            'data' => is_array($data) ? $data : [],
        ];
    }

    /**
     * Ejecuta una operación de contacto/campaña y normaliza su resultado.
     *
     * @param string $endpoint Ruta relativa de la API Mautic.
     * @param string $method Método HTTP.
     * @param array|null $payload Cuerpo JSON, o null si la operación no lo requiere.
     * @return array{ok: bool, status: string, http_code: int, error: ?string, data: array}
     */
    private static function request_contact_endpoint(string $endpoint, string $method, ?array $payload = null): array {
        if (!self::is_configured()) {
            return self::contact_operation_failure('Mautic no está configurado o está deshabilitado.');
        }

        $settings = self::get_settings();
        $headers = self::get_request_headers($settings);
        if (empty($headers['Authorization'])) {
            return self::contact_operation_failure(
                self::$last_auth_error !== '' ? self::$last_auth_error : 'No se pudo autenticar contra Mautic.'
            );
        }

        $args = [
            'method'  => $method,
            'headers' => $headers,
            'timeout' => self::TIMEOUT_SECONDS,
        ];
        if ($payload !== null) {
            $args['body'] = function_exists('wp_json_encode') ? wp_json_encode($payload) : json_encode($payload);
        }

        $response = self::http_request($settings['base_url'] . $endpoint, $args);

        if (is_wp_error($response)) {
            return self::contact_operation_failure($response->get_error_message());
        }
        if (!is_array($response)) {
            return self::contact_operation_failure('Respuesta HTTP inválida o no disponible.');
        }

        $code = function_exists('wp_remote_retrieve_response_code')
            ? (int) wp_remote_retrieve_response_code($response)
            : ($response['response']['code'] ?? 0);
        $body = function_exists('wp_remote_retrieve_body')
            ? wp_remote_retrieve_body($response)
            : ($response['body'] ?? '');
        $data = json_decode($body, true);

        if ($code < 200 || $code >= 300) {
            $failure = self::contact_operation_failure(self::extract_error_message($response, $code), $code);
            $failure['data'] = is_array($data) ? $data : [];
            return $failure;
        }

        return [
            'ok'        => true,
            'status'    => 'added',
            'http_code' => $code,
            'error'     => null,
            'data'      => is_array($data) ? $data : [],
        ];
    }

    /**
     * @return array{ok: false, status: string, http_code: int, error: string, data: array}
     */
    private static function contact_operation_failure(string $error, int $http_code = 0): array {
        return [
            'ok'        => false,
            'status'    => 'failed',
            'http_code' => $http_code,
            'error'     => $error,
            'data'      => [],
        ];
    }

    /**
     * @param array $tags Etiquetas como texto o respuestas de la API de Mautic.
     * @return array
     */
    private static function normalize_tags(array $tags): array {
        $normalized = [];
        foreach ($tags as $tag) {
            if (is_array($tag)) {
                $tag = $tag['tag'] ?? $tag['name'] ?? '';
            }
            if (!is_scalar($tag)) {
                continue;
            }
            $tag = trim((string) $tag);
            if ($tag !== '') {
                $normalized[$tag] = $tag;
            }
        }

        return array_values($normalized);
    }

    /**
     * Obtiene un access token OAuth2 usando Client Credentials.
     */
    private static function get_oauth2_access_token(array $settings): string {
        self::$last_auth_error = '';

        $client_id = trim((string) ($settings['client_id'] ?? ''));
        $client_secret = trim((string) ($settings['client_secret'] ?? ''));
        $base_url = rtrim((string) ($settings['base_url'] ?? ''), '/');
        if ($client_id === '' || $client_secret === '' || $base_url === '') {
            self::$last_auth_error = 'Faltan la Clave Pública (Client ID) o la Clave Secreta (Client Secret) de Mautic.';
            return '';
        }

        $cache_key = sha1($base_url . '|' . $client_id . '|' . $client_secret);
        $now = time();
        if (!empty(self::$oauth2_token_cache[$cache_key]['token'])
            && (int) self::$oauth2_token_cache[$cache_key]['expires_at'] > $now + 30) {
            return (string) self::$oauth2_token_cache[$cache_key]['token'];
        }

        $transient_key = 'flacso_mautic_oauth_' . substr($cache_key, 0, 24);
        if (function_exists('get_transient')) {
            $cached = get_transient($transient_key);
            if (is_array($cached)
                && !empty($cached['token'])
                && (int) ($cached['expires_at'] ?? 0) > $now + 30) {
                self::$oauth2_token_cache[$cache_key] = $cached;
                return (string) $cached['token'];
            }
        }

        $response = self::http_request(
            $base_url . '/oauth/v2/token',
            [
                'method' => 'POST',
                'headers' => [
                    'Accept' => 'application/json',
                    'Content-Type' => 'application/x-www-form-urlencoded',
                ],
                'body' => http_build_query([
                    'grant_type' => 'client_credentials',
                    'client_id' => $client_id,
                    'client_secret' => $client_secret,
                ], '', '&'),
                'timeout' => self::TIMEOUT_SECONDS,
            ]
        );

        if (is_wp_error($response)) {
            self::$last_auth_error = 'OAuth2 Mautic: ' . $response->get_error_message();
            return '';
        }
        if (!is_array($response)) {
            self::$last_auth_error = 'OAuth2 Mautic: respuesta HTTP inválida al solicitar el token.';
            return '';
        }

        $code = function_exists('wp_remote_retrieve_response_code')
            ? (int) wp_remote_retrieve_response_code($response)
            : (int) ($response['response']['code'] ?? 0);
        $body = function_exists('wp_remote_retrieve_body')
            ? (string) wp_remote_retrieve_body($response)
            : (string) ($response['body'] ?? '');
        $data = json_decode($body, true);

        if ($code < 200 || $code >= 300 || !is_array($data) || empty($data['access_token'])) {
            $detail = is_array($data)
                ? (string) ($data['error_description'] ?? $data['error'] ?? '')
                : '';
            self::$last_auth_error = $detail !== ''
                ? 'OAuth2 Mautic: ' . $detail
                : sprintf('OAuth2 Mautic: no se pudo obtener el token (HTTP %d).', $code);
            return '';
        }

        $expires_in = max(60, (int) ($data['expires_in'] ?? 3600));
        $cached = [
            'token' => (string) $data['access_token'],
            'expires_at' => $now + $expires_in,
        ];
        self::$oauth2_token_cache[$cache_key] = $cached;

        if (function_exists('set_transient')) {
            $ttl = max(60, $expires_in - 60);
            set_transient($transient_key, $cached, $ttl);
        }

        return (string) $cached['token'];
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

        if (($settings['auth_type'] ?? '') === 'oauth2') {
            $token = self::get_oauth2_access_token($settings);
            if ($token !== '') {
                $headers['Authorization'] = 'Bearer ' . $token;
            }
        } elseif (($settings['auth_type'] ?? '') === 'bearer') {
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
