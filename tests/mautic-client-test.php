<?php
/**
 * Test suite for FLACSO_Mautic_Client.
 *
 * Verifies settings, configuration checks, authentication headers,
 * test_connection, find_contact_by_email, create_or_update_contact,
 * and error handling (HTTP errors and network timeouts).
 */

$root = dirname(__DIR__);

if (!defined('ABSPATH')) {
    define('ABSPATH', __DIR__ . '/');
}

// --------------------------------------------------------------------------
// Mocks for WordPress functions & HTTP layer
// --------------------------------------------------------------------------

$GLOBALS['mautic_mock_options'] = [];
$GLOBALS['mautic_mock_transients'] = [];

if (!function_exists('get_option')) {
    function get_option($key, $default = false) {
        return $GLOBALS['mautic_mock_options'][$key] ?? $default;
    }
}

if (!function_exists('update_option')) {
    function update_option($key, $value) {
        $GLOBALS['mautic_mock_options'][$key] = $value;
        return true;
    }
}

if (!function_exists('get_transient')) {
    function get_transient($key) {
        return $GLOBALS['mautic_mock_transients'][$key] ?? false;
    }
}

if (!function_exists('set_transient')) {
    function set_transient($key, $value, $expiration) {
        $GLOBALS['mautic_mock_transients'][$key] = $value;
        return true;
    }
}

if (!class_exists('WP_Error')) {
    class WP_Error {
        public function __construct(private string $message, private string $code = '') {}
        public function get_error_message(): string { return $this->message; }
        public function get_error_code(): string { return $this->code; }
    }
}

if (!function_exists('is_wp_error')) {
    function is_wp_error($thing) {
        return $thing instanceof WP_Error;
    }
}

$GLOBALS['mautic_http_calls'] = [];
$GLOBALS['mautic_http_handler'] = null;

if (!function_exists('wp_remote_request')) {
    function wp_remote_request($url, $args = []) {
        $GLOBALS['mautic_http_calls'][] = ['url' => $url, 'args' => $args];
        if (is_callable($GLOBALS['mautic_http_handler'])) {
            return ($GLOBALS['mautic_http_handler'])($url, $args);
        }
        return [
            'response' => ['code' => 200, 'message' => 'OK'],
            'body'     => json_encode(['success' => true]),
        ];
    }
}

if (!function_exists('wp_remote_get')) {
    function wp_remote_get($url, $args = []) {
        $args['method'] = 'GET';
        return wp_remote_request($url, $args);
    }
}

if (!function_exists('wp_remote_post')) {
    function wp_remote_post($url, $args = []) {
        $args['method'] = 'POST';
        return wp_remote_request($url, $args);
    }
}

if (!function_exists('wp_remote_retrieve_response_code')) {
    function wp_remote_retrieve_response_code($res) {
        return is_array($res) ? ($res['response']['code'] ?? 0) : 0;
    }
}

if (!function_exists('wp_remote_retrieve_body')) {
    function wp_remote_retrieve_body($res) {
        return is_array($res) ? ($res['body'] ?? '') : '';
    }
}

// --------------------------------------------------------------------------
// Require target class (will fail TDD step 2 if class doesn't exist)
// --------------------------------------------------------------------------
require_once $root . '/includes/integrations/class-flacso-mautic-client.php';

// Helper assertion function
function mautic_assert(bool $condition, string $message): void {
    if (!$condition) {
        fwrite(STDERR, "FAIL: {$message}\n");
        exit(1);
    }
    echo "PASS: {$message}\n";
}

// --------------------------------------------------------------------------
// Test Group 1: Configuration constants & defaults
// --------------------------------------------------------------------------
echo "\n--- Test Group 1: Configuration constants & defaults ---\n";

mautic_assert(FLACSO_Mautic_Client::OPTION_ENABLED === 'flacso_mautic_enabled', 'OPTION_ENABLED constant matches spec');
mautic_assert(FLACSO_Mautic_Client::OPTION_BASE_URL === 'flacso_mautic_base_url', 'OPTION_BASE_URL constant matches spec');
mautic_assert(FLACSO_Mautic_Client::OPTION_AUTH_TYPE === 'flacso_mautic_auth_type', 'OPTION_AUTH_TYPE constant matches spec');
mautic_assert(FLACSO_Mautic_Client::OPTION_USERNAME === 'flacso_mautic_username', 'OPTION_USERNAME constant matches spec');
mautic_assert(FLACSO_Mautic_Client::OPTION_PASSWORD === 'flacso_mautic_password', 'OPTION_PASSWORD constant matches spec');
mautic_assert(FLACSO_Mautic_Client::OPTION_TOKEN === 'flacso_mautic_token', 'OPTION_TOKEN constant matches spec');
mautic_assert(FLACSO_Mautic_Client::OPTION_CLIENT_ID === 'flacso_mautic_client_id', 'OPTION_CLIENT_ID constant matches spec');
mautic_assert(FLACSO_Mautic_Client::OPTION_CLIENT_SECRET === 'flacso_mautic_client_secret', 'OPTION_CLIENT_SECRET constant matches spec');

$GLOBALS['mautic_mock_options'] = [];
$settings = FLACSO_Mautic_Client::get_settings();
mautic_assert($settings['enabled'] === false, 'Default enabled is false');
mautic_assert($settings['base_url'] === 'https://envios.flacso.edu.uy', 'Default base_url is https://envios.flacso.edu.uy');
mautic_assert($settings['auth_type'] === 'oauth2', 'Default auth_type is oauth2');
mautic_assert($settings['username'] === '', 'Default username is empty');
mautic_assert($settings['password'] === '', 'Default password is empty');
mautic_assert($settings['token'] === '', 'Default token is empty');
mautic_assert($settings['client_id'] === '', 'Default client_id is empty');
mautic_assert($settings['client_secret'] === '', 'Default client_secret is empty');
mautic_assert(FLACSO_Mautic_Client::is_configured() === false, 'is_configured() is false by default');

// Trimming & trailing slash removal
$GLOBALS['mautic_mock_options'] = [
    'flacso_mautic_enabled'   => '1',
    'flacso_mautic_base_url'  => '  https://mautic.example.org///  ',
    'flacso_mautic_auth_type' => 'BASIC',
    'flacso_mautic_username'  => ' admin ',
    'flacso_mautic_password'  => ' secret ',
];
$settings = FLACSO_Mautic_Client::get_settings();
mautic_assert($settings['enabled'] === true, 'Enabled parsed as true from "1"');
mautic_assert($settings['base_url'] === 'https://mautic.example.org', 'base_url trims spaces and trailing slashes');
mautic_assert($settings['auth_type'] === 'basic', 'auth_type is lowercased to basic');
mautic_assert($settings['username'] === 'admin', 'username is trimmed');
mautic_assert($settings['password'] === 'secret', 'password is trimmed');
mautic_assert(FLACSO_Mautic_Client::is_configured() === true, 'is_configured() is true for valid basic auth');

// --------------------------------------------------------------------------
// Test Group 2: is_configured() validation logic
// --------------------------------------------------------------------------
echo "\n--- Test Group 2: is_configured() validation logic ---\n";

// Disabled flag
$GLOBALS['mautic_mock_options'] = [
    'flacso_mautic_enabled'   => '0',
    'flacso_mautic_base_url'  => 'https://envios.flacso.edu.uy',
    'flacso_mautic_auth_type' => 'basic',
    'flacso_mautic_username'  => 'admin',
    'flacso_mautic_password'  => 'secret',
];
mautic_assert(FLACSO_Mautic_Client::is_configured() === false, 'is_configured() is false when enabled is "0"');

// Empty base_url
$GLOBALS['mautic_mock_options'] = [
    'flacso_mautic_enabled'   => '1',
    'flacso_mautic_base_url'  => '',
    'flacso_mautic_auth_type' => 'basic',
    'flacso_mautic_username'  => 'admin',
    'flacso_mautic_password'  => 'secret',
];
// Note: if base_url is explicitly set to empty string, is_configured should be false
mautic_assert(FLACSO_Mautic_Client::is_configured() === false, 'is_configured() is false when base_url is empty');

// Basic auth missing username or password
$GLOBALS['mautic_mock_options'] = [
    'flacso_mautic_enabled'   => '1',
    'flacso_mautic_base_url'  => 'https://envios.flacso.edu.uy',
    'flacso_mautic_auth_type' => 'basic',
    'flacso_mautic_username'  => '',
    'flacso_mautic_password'  => 'secret',
];
mautic_assert(FLACSO_Mautic_Client::is_configured() === false, 'is_configured() is false when username missing in basic auth');

$GLOBALS['mautic_mock_options']['flacso_mautic_username'] = 'admin';
$GLOBALS['mautic_mock_options']['flacso_mautic_password'] = '';
mautic_assert(FLACSO_Mautic_Client::is_configured() === false, 'is_configured() is false when password missing in basic auth');

// OAuth2 Client Credentials mode
$GLOBALS['mautic_mock_options'] = [
    'flacso_mautic_enabled'       => '1',
    'flacso_mautic_base_url'      => 'https://envios.flacso.edu.uy',
    'flacso_mautic_auth_type'     => 'oauth2',
    'flacso_mautic_client_id'     => '',
    'flacso_mautic_client_secret' => 'oauth-secret',
];
mautic_assert(FLACSO_Mautic_Client::is_configured() === false, 'is_configured() is false when client ID missing in oauth2');

$GLOBALS['mautic_mock_options']['flacso_mautic_client_id'] = 'oauth-client';
$GLOBALS['mautic_mock_options']['flacso_mautic_client_secret'] = '';
mautic_assert(FLACSO_Mautic_Client::is_configured() === false, 'is_configured() is false when client secret missing in oauth2');

$GLOBALS['mautic_mock_options']['flacso_mautic_client_secret'] = 'oauth-secret';
mautic_assert(FLACSO_Mautic_Client::is_configured() === true, 'is_configured() is true for OAuth2 client credentials');

// Bearer token mode
$GLOBALS['mautic_mock_options'] = [
    'flacso_mautic_enabled'   => '1',
    'flacso_mautic_base_url'  => 'https://envios.flacso.edu.uy',
    'flacso_mautic_auth_type' => 'bearer',
    'flacso_mautic_token'     => '',
];
mautic_assert(FLACSO_Mautic_Client::is_configured() === false, 'is_configured() is false when token missing in bearer auth');

$GLOBALS['mautic_mock_options']['flacso_mautic_token'] = 'my-bearer-token-123';
mautic_assert(FLACSO_Mautic_Client::is_configured() === true, 'is_configured() is true when token present in bearer auth');

// --------------------------------------------------------------------------
// Test Group 3: test_connection()
// --------------------------------------------------------------------------
echo "\n--- Test Group 3: test_connection() ---\n";

// Not configured
$GLOBALS['mautic_mock_options'] = ['flacso_mautic_enabled' => '0'];
$res = FLACSO_Mautic_Client::test_connection();
mautic_assert($res['ok'] === false, 'test_connection fails when not configured');
mautic_assert($res['code'] === 0, 'test_connection returns code 0 when not configured');

// Configured with Basic Auth, successful 200 OK
$GLOBALS['mautic_mock_options'] = [
    'flacso_mautic_enabled'   => '1',
    'flacso_mautic_base_url'  => 'https://envios.flacso.edu.uy',
    'flacso_mautic_auth_type' => 'basic',
    'flacso_mautic_username'  => 'apiuser',
    'flacso_mautic_password'  => 'apipass',
];
$GLOBALS['mautic_http_calls'] = [];
$GLOBALS['mautic_http_handler'] = function($url, $args) {
    return [
        'response' => ['code' => 200, 'message' => 'OK'],
        'body'     => json_encode(['total' => 5, 'contacts' => []]),
    ];
};

$res = FLACSO_Mautic_Client::test_connection();
mautic_assert($res['ok'] === true, 'test_connection returns ok => true on 200');
mautic_assert($res['code'] === 200, 'test_connection returns code => 200');
mautic_assert(!empty($res['message']), 'test_connection returns descriptive message');

// Verify request details: URL, method, headers, timeout
mautic_assert(count($GLOBALS['mautic_http_calls']) === 1, 'One HTTP call made for test_connection');
$call = $GLOBALS['mautic_http_calls'][0];
mautic_assert($call['url'] === 'https://envios.flacso.edu.uy/api/contacts?limit=1', 'test_connection calls GET /api/contacts?limit=1');
mautic_assert(($call['args']['method'] ?? 'GET') === 'GET', 'test_connection uses GET method');
mautic_assert($call['args']['timeout'] === 4, 'test_connection uses 4s timeout');
$expected_auth = 'Basic ' . base64_encode('apiuser:apipass');
mautic_assert(($call['args']['headers']['Authorization'] ?? '') === $expected_auth, 'test_connection sends Basic auth header');
mautic_assert(($call['args']['headers']['Accept'] ?? '') === 'application/json', 'test_connection sends Accept application/json');

// Bearer Auth header check
$GLOBALS['mautic_mock_options'] = [
    'flacso_mautic_enabled'   => '1',
    'flacso_mautic_base_url'  => 'https://envios.flacso.edu.uy',
    'flacso_mautic_auth_type' => 'bearer',
    'flacso_mautic_token'     => 'secret-pat-token',
];
$GLOBALS['mautic_http_calls'] = [];
$res = FLACSO_Mautic_Client::test_connection();
mautic_assert($res['ok'] === true, 'test_connection with bearer returns ok');
mautic_assert(($GLOBALS['mautic_http_calls'][0]['args']['headers']['Authorization'] ?? '') === 'Bearer secret-pat-token', 'Bearer auth header sent correctly');

// OAuth2 Client Credentials obtains a token, then calls the API with Bearer auth.
$GLOBALS['mautic_mock_options'] = [
    'flacso_mautic_enabled'       => '1',
    'flacso_mautic_base_url'      => 'https://envios.flacso.edu.uy',
    'flacso_mautic_auth_type'     => 'oauth2',
    'flacso_mautic_client_id'     => 'wp-client-diagnostics',
    'flacso_mautic_client_secret' => 'wp-secret-diagnostics',
];
$GLOBALS['mautic_mock_transients'] = [];
$GLOBALS['mautic_http_calls'] = [];
$GLOBALS['mautic_http_handler'] = function($url, $args) {
    if (str_ends_with($url, '/oauth/v2/token')) {
        return [
            'response' => ['code' => 200, 'message' => 'OK'],
            'body' => json_encode([
                'access_token' => 'oauth-access-token',
                'token_type' => 'bearer',
                'expires_in' => 3600,
            ]),
        ];
    }

    return [
        'response' => ['code' => 200, 'message' => 'OK'],
        'body' => json_encode(['total' => 0, 'contacts' => []]),
    ];
};

$res = FLACSO_Mautic_Client::test_connection();
mautic_assert($res['ok'] === true, 'test_connection supports OAuth2 client credentials');
mautic_assert(count($GLOBALS['mautic_http_calls']) === 2, 'OAuth2 connection makes token and API requests');
$token_call = $GLOBALS['mautic_http_calls'][0];
mautic_assert(str_ends_with($token_call['url'], '/oauth/v2/token'), 'OAuth2 token endpoint is called');
mautic_assert(($token_call['args']['method'] ?? '') === 'POST', 'OAuth2 token request uses POST');
mautic_assert(($token_call['args']['headers']['Content-Type'] ?? '') === 'application/x-www-form-urlencoded', 'OAuth2 token request uses form encoding');
parse_str((string) ($token_call['args']['body'] ?? ''), $token_body);
mautic_assert(($token_body['grant_type'] ?? '') === 'client_credentials', 'OAuth2 uses client_credentials grant');
mautic_assert(($token_body['client_id'] ?? '') === 'wp-client-diagnostics', 'OAuth2 sends client ID');
mautic_assert(($token_body['client_secret'] ?? '') === 'wp-secret-diagnostics', 'OAuth2 sends client secret');
$api_call = $GLOBALS['mautic_http_calls'][1];
mautic_assert(($api_call['args']['headers']['Authorization'] ?? '') === 'Bearer oauth-access-token', 'OAuth2 API call uses acquired Bearer token');

// HTTP 401 Unauthorized
$GLOBALS['mautic_mock_options'] = [
    'flacso_mautic_enabled'   => '1',
    'flacso_mautic_base_url'  => 'https://envios.flacso.edu.uy',
    'flacso_mautic_auth_type' => 'bearer',
    'flacso_mautic_token'     => 'secret-pat-token',
];
$GLOBALS['mautic_http_handler'] = function($url, $args) {
    return [
        'response' => ['code' => 401, 'message' => 'Unauthorized'],
        'body'     => json_encode(['errors' => [['message' => 'Credenciales inválidas', 'code' => 401]]]),
    ];
};
$res = FLACSO_Mautic_Client::test_connection();
mautic_assert($res['ok'] === false, 'test_connection returns ok => false on 401');
mautic_assert($res['code'] === 401, 'test_connection returns code 401');
mautic_assert(str_contains($res['message'], 'Credenciales inválidas'), 'test_connection extracts Mautic error message');

// Network failure / timeout (WP_Error)
$GLOBALS['mautic_http_handler'] = function($url, $args) {
    return new WP_Error('cURL error 28: Operation timed out', 'http_request_failed');
};
$res = FLACSO_Mautic_Client::test_connection();
mautic_assert($res['ok'] === false, 'test_connection returns ok => false on timeout');
mautic_assert($res['code'] === 0, 'test_connection returns code 0 on WP_Error');
mautic_assert(str_contains($res['message'], 'timed out'), 'test_connection includes WP_Error message');

// --------------------------------------------------------------------------
// Test Group 4: find_contact_by_email()
// --------------------------------------------------------------------------
echo "\n--- Test Group 4: find_contact_by_email() ---\n";

// Contact exists
$GLOBALS['mautic_http_calls'] = [];
$GLOBALS['mautic_http_handler'] = function($url, $args) {
    if (str_contains($url, 'search=email:')) {
        return [
            'response' => ['code' => 200, 'message' => 'OK'],
            'body'     => json_encode([
                'total'    => 1,
                'contacts' => [
                    '42' => [
                        'id'     => 42,
                        'points' => 10,
                        'fields' => [
                            'core' => [
                                'email'     => ['value' => 'prospecto@ejemplo.com'],
                                'firstname' => ['value' => 'Ana'],
                                'lastname'  => ['value' => 'Gomez'],
                            ],
                        ],
                        'tags' => ['interes-maestria'],
                    ],
                ],
            ]),
        ];
    }
    return ['response' => ['code' => 404, 'message' => 'Not Found'], 'body' => ''];
};

$contact = FLACSO_Mautic_Client::find_contact_by_email('  Prospecto@Ejemplo.com  ');
mautic_assert(is_array($contact), 'find_contact_by_email returns array when found');
mautic_assert(($contact['id'] ?? 0) === 42, 'find_contact_by_email returns correct contact ID');
mautic_assert(count($GLOBALS['mautic_http_calls']) === 1, 'find_contact_by_email made one HTTP request');
mautic_assert(str_contains($GLOBALS['mautic_http_calls'][0]['url'], 'search=email%3Aprospecto%40ejemplo.com') || str_contains($GLOBALS['mautic_http_calls'][0]['url'], 'search=email:prospecto%40ejemplo.com'), 'find_contact_by_email normalizes email to lowercase and encodes');

// Contact not found (total 0, empty contacts)
$GLOBALS['mautic_http_handler'] = function($url, $args) {
    return [
        'response' => ['code' => 200, 'message' => 'OK'],
        'body'     => json_encode(['total' => 0, 'contacts' => []]),
    ];
};
$contact = FLACSO_Mautic_Client::find_contact_by_email('inexistente@ejemplo.com');
mautic_assert($contact === null, 'find_contact_by_email returns null when not found');

// Server error or network error
$GLOBALS['mautic_http_handler'] = function($url, $args) {
    return new WP_Error('Connection refused');
};
$contact = FLACSO_Mautic_Client::find_contact_by_email('error@ejemplo.com');
mautic_assert($contact === null, 'find_contact_by_email returns null on network error');

// Empty email
$contact = FLACSO_Mautic_Client::find_contact_by_email('   ');
mautic_assert($contact === null, 'find_contact_by_email returns null for empty email');

// Client disabled
$GLOBALS['mautic_mock_options']['flacso_mautic_enabled'] = '0';
$contact = FLACSO_Mautic_Client::find_contact_by_email('prospecto@ejemplo.com');
mautic_assert($contact === null, 'find_contact_by_email returns null when client is disabled');
$GLOBALS['mautic_mock_options']['flacso_mautic_enabled'] = '1';

// --------------------------------------------------------------------------
// Test Group 5: create_or_update_contact() - Creation flow
// --------------------------------------------------------------------------
echo "\n--- Test Group 5: create_or_update_contact() - Creation flow ---\n";

$GLOBALS['mautic_http_calls'] = [];
$GLOBALS['mautic_http_handler'] = function($url, $args) {
    // 1. Search request returns 0 contacts
    if (str_contains($url, '/api/contacts?search=')) {
        return [
            'response' => ['code' => 200, 'message' => 'OK'],
            'body'     => json_encode(['total' => 0, 'contacts' => []]),
        ];
    }
    // 2. POST /api/contacts/new creates contact
    if (str_contains($url, '/api/contacts/new') && ($args['method'] ?? 'POST') === 'POST') {
        $body = json_decode($args['body'] ?? '{}', true);
        return [
            'response' => ['code' => 201, 'message' => 'Created'],
            'body'     => json_encode([
                'contact' => [
                    'id'     => 88,
                    'fields' => ['core' => $body],
                ],
            ]),
        ];
    }
    return ['response' => ['code' => 400, 'message' => 'Bad Request'], 'body' => ''];
};

$create_res = FLACSO_Mautic_Client::create_or_update_contact(
    'nuevo@ejemplo.com',
    [
        'firstname' => 'Carlos',
        'lastname'  => 'Rodriguez',
        'phone'     => '+59899112233',
    ],
    ['interes-diploma', 'consulta-abierta-diploma-c1']
);

mautic_assert($create_res['ok'] === true, 'create_or_update_contact returns ok => true on creation');
mautic_assert($create_res['contact_id'] === 88, 'create_or_update_contact returns created contact_id');
mautic_assert($create_res['action'] === 'created', 'create_or_update_contact action is "created"');
mautic_assert($create_res['error'] === null, 'create_or_update_contact error is null on success');

// Verify calls made: 1st search, 2nd POST
mautic_assert(count($GLOBALS['mautic_http_calls']) === 2, 'Creation makes 2 calls: search then POST');
$post_call = $GLOBALS['mautic_http_calls'][1];
mautic_assert(str_ends_with($post_call['url'], '/api/contacts/new'), 'Second call target is /api/contacts/new');
mautic_assert(($post_call['args']['method'] ?? 'POST') === 'POST', 'Second call uses POST');
$sent_payload = json_decode($post_call['args']['body'], true);
mautic_assert($sent_payload['email'] === 'nuevo@ejemplo.com', 'Payload contains email');
mautic_assert($sent_payload['firstname'] === 'Carlos', 'Payload contains firstname');
mautic_assert($sent_payload['tags'] === ['interes-diploma', 'consulta-abierta-diploma-c1'], 'Payload contains tags');

// --------------------------------------------------------------------------
// Test Group 6: create_or_update_contact() - Update flow (deduplication)
// --------------------------------------------------------------------------
echo "\n--- Test Group 6: create_or_update_contact() - Update flow ---\n";

$GLOBALS['mautic_http_calls'] = [];
$GLOBALS['mautic_http_handler'] = function($url, $args) {
    // 1. Search returns existing contact ID 42
    if (str_contains($url, '/api/contacts?search=')) {
        return [
            'response' => ['code' => 200, 'message' => 'OK'],
            'body'     => json_encode([
                'total'    => 1,
                'contacts' => [
                    '42' => [
                        'id' => 42,
                        'fields' => ['core' => ['email' => ['value' => 'existente@ejemplo.com']]],
                    ],
                ],
            ]),
        ];
    }
    // 2. Existing tags are retrieved before the PATCH update.
    if (str_ends_with($url, '/api/contacts/42') && ($args['method'] ?? '') === 'GET') {
        return [
            'response' => ['code' => 200, 'message' => 'OK'],
            'body'     => json_encode(['contact' => ['id' => 42, 'tags' => [['tag' => 'legado']]]]),
        ];
    }
    // 3. PATCH /api/contacts/42/edit updates contact
    if (str_contains($url, '/api/contacts/42/edit') && ($args['method'] ?? '') === 'PATCH') {
        return [
            'response' => ['code' => 200, 'message' => 'OK'],
            'body'     => json_encode([
                'contact' => [
                    'id' => 42,
                ],
            ]),
        ];
    }
    return ['response' => ['code' => 400, 'message' => 'Unexpected call: ' . $url], 'body' => ''];
};

$update_res = FLACSO_Mautic_Client::create_or_update_contact(
    'existente@ejemplo.com',
    ['firstname' => 'Carlos Updated'],
    ['nuevo-tag-c2']
);

mautic_assert($update_res['ok'] === true, 'create_or_update_contact returns ok => true on update');
mautic_assert($update_res['contact_id'] === 42, 'create_or_update_contact returns existing contact_id');
mautic_assert($update_res['action'] === 'updated', 'create_or_update_contact action is "updated"');
mautic_assert($update_res['error'] === null, 'create_or_update_contact error is null on update');

// Verify calls made: search, tags, then PATCH.
mautic_assert(count($GLOBALS['mautic_http_calls']) === 3, 'Update reads tags before PATCH');
$patch_call = $GLOBALS['mautic_http_calls'][2];
mautic_assert(str_ends_with($patch_call['url'], '/api/contacts/42/edit'), 'Third call target is /api/contacts/42/edit');
mautic_assert(($patch_call['args']['method'] ?? '') === 'PATCH', 'Third call uses PATCH');
$patch_payload = json_decode($patch_call['args']['body'], true);
mautic_assert($patch_payload['firstname'] === 'Carlos Updated', 'PATCH payload contains updated field');
mautic_assert($patch_payload['tags'] === ['legado', 'nuevo-tag-c2'], 'PATCH payload preserves and merges tags');

// --------------------------------------------------------------------------
// Test Group 7: Failure modes & error resilience
// --------------------------------------------------------------------------
echo "\n--- Test Group 7: Failure modes & error resilience ---\n";

// HTTP 500 on creation
$GLOBALS['mautic_http_calls'] = [];
$GLOBALS['mautic_http_handler'] = function($url, $args) {
    if (str_contains($url, '/api/contacts?search=')) {
        return ['response' => ['code' => 200, 'message' => 'OK'], 'body' => json_encode(['contacts' => []])];
    }
    return [
        'response' => ['code' => 500, 'message' => 'Internal Server Error'],
        'body'     => json_encode(['errors' => [['message' => 'Database connection failed']]]),
    ];
};

$fail_res = FLACSO_Mautic_Client::create_or_update_contact('fail@ejemplo.com', ['firstname' => 'Test']);
mautic_assert($fail_res['ok'] === false, 'Returns ok => false on HTTP 500');
mautic_assert($fail_res['contact_id'] === null, 'Returns contact_id => null on failure');
mautic_assert($fail_res['action'] === 'failed', 'Returns action => failed');
mautic_assert(str_contains($fail_res['error'], 'Database connection failed') || str_contains($fail_res['error'], '500'), 'Error message contains details');

// Network timeout on PATCH
$GLOBALS['mautic_http_calls'] = [];
$GLOBALS['mautic_http_handler'] = function($url, $args) {
    if (str_contains($url, '/api/contacts?search=')) {
        return ['response' => ['code' => 200, 'message' => 'OK'], 'body' => json_encode(['contacts' => ['99' => ['id' => 99]]])];
    }
    return new WP_Error('cURL error 28: Operation timed out');
};

$timeout_res = FLACSO_Mautic_Client::create_or_update_contact('timeout@ejemplo.com');
mautic_assert($timeout_res['ok'] === false, 'Returns ok => false on PATCH timeout');
mautic_assert($timeout_res['contact_id'] === null, 'Returns contact_id => null on PATCH timeout');
mautic_assert($timeout_res['action'] === 'failed', 'Returns action => failed on timeout');
mautic_assert(str_contains($timeout_res['error'], 'timed out'), 'Error message preserves timeout text');

// Disabled client
$GLOBALS['mautic_mock_options']['flacso_mautic_enabled'] = '0';
$disabled_res = FLACSO_Mautic_Client::create_or_update_contact('disabled@ejemplo.com');
mautic_assert($disabled_res['ok'] === false, 'Returns ok => false when client disabled');
mautic_assert($disabled_res['action'] === 'failed', 'Action is failed when client disabled');

// --------------------------------------------------------------------------
// Test Group 8: send_email_to_contact()
// --------------------------------------------------------------------------
echo "\n--- Test Group 8: send_email_to_contact() ---\n";

// 1. Invalid IDs (<= 0)
$inv1 = FLACSO_Mautic_Client::send_email_to_contact(0, 10, ['{nombre}' => 'Juan']);
mautic_assert($inv1['ok'] === false, 'send_email_to_contact fails when email_id <= 0');
mautic_assert($inv1['status'] === 'failed', 'send_email_to_contact status is failed for email_id <= 0');
mautic_assert($inv1['email_id'] === 0, 'send_email_to_contact preserves email_id 0');
mautic_assert($inv1['contact_id'] === 10, 'send_email_to_contact preserves contact_id 10');
mautic_assert($inv1['error'] === 'Email ID y Contact ID deben ser enteros positivos.', 'send_email_to_contact returns expected validation error');

$inv2 = FLACSO_Mautic_Client::send_email_to_contact(5, -1);
mautic_assert($inv2['ok'] === false, 'send_email_to_contact fails when contact_id <= 0');
mautic_assert($inv2['error'] === 'Email ID y Contact ID deben ser enteros positivos.', 'send_email_to_contact returns expected validation error for negative contact_id');

$inv3 = FLACSO_Mautic_Client::send_email_to_contact(-3, 0);
mautic_assert($inv3['ok'] === false, 'send_email_to_contact fails when both IDs <= 0');

// 2. Unconfigured / disabled client
$GLOBALS['mautic_mock_options']['flacso_mautic_enabled'] = '0';
$dis_res = FLACSO_Mautic_Client::send_email_to_contact(10, 20);
mautic_assert($dis_res['ok'] === false, 'send_email_to_contact fails when client is disabled');
mautic_assert($dis_res['status'] === 'failed', 'send_email_to_contact status is failed when disabled');
mautic_assert($dis_res['email_id'] === 10, 'send_email_to_contact preserves email_id when disabled');
mautic_assert($dis_res['contact_id'] === 20, 'send_email_to_contact preserves contact_id when disabled');
mautic_assert($dis_res['error'] === 'Mautic no está configurado o está deshabilitado.', 'send_email_to_contact returns expected unconfigured error');

// 3. Successful send (HTTP 200 with success: true and Basic auth)
$GLOBALS['mautic_mock_options'] = [
    'flacso_mautic_enabled'   => '1',
    'flacso_mautic_base_url'  => 'https://envios.flacso.edu.uy',
    'flacso_mautic_auth_type' => 'basic',
    'flacso_mautic_username'  => 'mailer_user',
    'flacso_mautic_password'  => 'mailer_pass',
];
$GLOBALS['mautic_http_calls'] = [];
$GLOBALS['mautic_http_handler'] = function($url, $args) {
    return [
        'response' => ['code' => 200, 'message' => 'OK'],
        'body'     => json_encode(['success' => true, 'result' => true]),
    ];
};

$tokens = [
    '{nombre}'                   => 'Ana',
    '{apellido}'                 => 'García',
    '{oferta_academica_nombre}'  => 'Diploma en Políticas Públicas',
];
$success_res = FLACSO_Mautic_Client::send_email_to_contact(15, 88, $tokens);

mautic_assert($success_res['ok'] === true, 'send_email_to_contact returns ok => true on success');
mautic_assert($success_res['status'] === 'sent', 'send_email_to_contact returns status => sent');
mautic_assert($success_res['email_id'] === 15, 'send_email_to_contact returns email_id');
mautic_assert($success_res['contact_id'] === 88, 'send_email_to_contact returns contact_id');
mautic_assert($success_res['error'] === null, 'send_email_to_contact error is null on success');

// Verify HTTP call details
mautic_assert(count($GLOBALS['mautic_http_calls']) === 1, 'Exactly one HTTP request made for send_email_to_contact');
$send_call = $GLOBALS['mautic_http_calls'][0];
mautic_assert($send_call['url'] === 'https://envios.flacso.edu.uy/api/emails/15/contact/88/send', 'URL matches Mautic email send endpoint');
mautic_assert(($send_call['args']['method'] ?? '') === 'POST', 'HTTP method is POST');
mautic_assert(($send_call['args']['timeout'] ?? 0) === 4, 'HTTP timeout is 4 seconds');
$expected_auth = 'Basic ' . base64_encode('mailer_user:mailer_pass');
mautic_assert(($send_call['args']['headers']['Authorization'] ?? '') === $expected_auth, 'Authorization header is Basic encoded');
mautic_assert(($send_call['args']['headers']['Content-Type'] ?? '') === 'application/json', 'Content-Type is application/json');
mautic_assert(($send_call['args']['headers']['Accept'] ?? '') === 'application/json', 'Accept header is application/json');

$send_body = json_decode($send_call['args']['body'] ?? '{}', true);
mautic_assert(isset($send_body['tokens']), 'Body contains tokens key');
mautic_assert($send_body['tokens']['{nombre}'] === 'Ana', 'Body tokens preserve keys and values');
mautic_assert($send_body['tokens']['{oferta_academica_nombre}'] === 'Diploma en Políticas Públicas', 'Body tokens contains program name');

// 3b. Successful send with Bearer auth and result: true
$GLOBALS['mautic_mock_options'] = [
    'flacso_mautic_enabled'   => '1',
    'flacso_mautic_base_url'  => 'https://envios.flacso.edu.uy',
    'flacso_mautic_auth_type' => 'bearer',
    'flacso_mautic_token'     => 'secret-bearer-tok',
];
$GLOBALS['mautic_http_calls'] = [];
$GLOBALS['mautic_http_handler'] = function($url, $args) {
    return [
        'response' => ['code' => 200, 'message' => 'OK'],
        'body'     => json_encode(['result' => true]), // only result: true
    ];
};
$bearer_res = FLACSO_Mautic_Client::send_email_to_contact(4, 99);
mautic_assert($bearer_res['ok'] === true, 'send_email_to_contact accepts result: true as success');
mautic_assert(($GLOBALS['mautic_http_calls'][0]['args']['headers']['Authorization'] ?? '') === 'Bearer secret-bearer-tok', 'Authorization header is Bearer token');

// 4. Failure: HTTP 200 but failedRecipients
$GLOBALS['mautic_http_calls'] = [];
$GLOBALS['mautic_http_handler'] = function($url, $args) {
    return [
        'response' => ['code' => 200, 'message' => 'OK'],
        'body'     => json_encode([
            'success'          => false,
            'failedRecipients' => ['bounced@flacso.edu.uy'],
        ]),
    ];
};
$fail_recip_res = FLACSO_Mautic_Client::send_email_to_contact(15, 88);
mautic_assert($fail_recip_res['ok'] === false, 'send_email_to_contact returns ok => false on failedRecipients');
mautic_assert($fail_recip_res['status'] === 'failed', 'send_email_to_contact status is failed');
mautic_assert($fail_recip_res['email_id'] === 15, 'send_email_to_contact preserves email_id on failedRecipients');
mautic_assert($fail_recip_res['contact_id'] === 88, 'send_email_to_contact preserves contact_id on failedRecipients');
mautic_assert(str_contains($fail_recip_res['error'], 'bounced@flacso.edu.uy'), 'Error message contains failed recipient email');

// 4b. Failure: HTTP 200 but errors array
$GLOBALS['mautic_http_handler'] = function($url, $args) {
    return [
        'response' => ['code' => 200, 'message' => 'OK'],
        'body'     => json_encode([
            'success' => false,
            'errors'  => [['message' => 'Template is unpublished or drafted']],
        ]),
    ];
};
$fail_err_res = FLACSO_Mautic_Client::send_email_to_contact(15, 88);
mautic_assert($fail_err_res['ok'] === false, 'send_email_to_contact returns ok => false on errors array');
mautic_assert(str_contains($fail_err_res['error'], 'Template is unpublished'), 'Error message contains error details');

// 5. Failure: HTTP 404 (template not found)
$GLOBALS['mautic_http_calls'] = [];
$GLOBALS['mautic_http_handler'] = function($url, $args) {
    return [
        'response' => ['code' => 404, 'message' => 'Not Found'],
        'body'     => json_encode(['errors' => [['message' => 'Email not found']]]),
    ];
};
$res_404 = FLACSO_Mautic_Client::send_email_to_contact(999, 88);
mautic_assert($res_404['ok'] === false, 'send_email_to_contact returns ok => false on HTTP 404');
mautic_assert($res_404['status'] === 'failed', 'send_email_to_contact status is failed on 404');
mautic_assert($res_404['email_id'] === 999, 'email_id is 999');
mautic_assert($res_404['contact_id'] === 88, 'contact_id is 88');
mautic_assert(str_contains($res_404['error'], 'Email not found'), 'Error message contains 404 message');

// 5b. Failure: HTTP 500 internal server error
$GLOBALS['mautic_http_handler'] = function($url, $args) {
    return [
        'response' => ['code' => 500, 'message' => 'Internal Server Error'],
        'body'     => '<html>500 Internal Server Error</html>',
    ];
};
$res_500 = FLACSO_Mautic_Client::send_email_to_contact(15, 88);
mautic_assert($res_500['ok'] === false, 'send_email_to_contact returns ok => false on HTTP 500');
mautic_assert($res_500['status'] === 'failed', 'status is failed on 500');
mautic_assert(str_contains($res_500['error'], '500'), 'Error message mentions HTTP 500');

// 6. Network error / timeout
$GLOBALS['mautic_http_handler'] = function($url, $args) {
    return new WP_Error('cURL error 28: Operation timed out after 4001 milliseconds with 0 bytes received', 'http_request_failed');
};
$timeout_res = FLACSO_Mautic_Client::send_email_to_contact(15, 88);
mautic_assert($timeout_res['ok'] === false, 'send_email_to_contact returns ok => false on cURL timeout');
mautic_assert($timeout_res['status'] === 'failed', 'status is failed on timeout');
mautic_assert($timeout_res['email_id'] === 15, 'preserves email_id on timeout');
mautic_assert($timeout_res['contact_id'] === 88, 'preserves contact_id on timeout');
mautic_assert(str_contains($timeout_res['error'], 'timed out'), 'Error message preserves timeout text');

// Test group 9: campaign membership and non-destructive tag updates.
$GLOBALS['mautic_mock_options'] = [
    'flacso_mautic_enabled' => '1',
    'flacso_mautic_base_url' => 'https://mautic.example.org',
    'flacso_mautic_auth_type' => 'basic',
    'flacso_mautic_username' => 'admin',
    'flacso_mautic_password' => 'secret',
];
$GLOBALS['mautic_http_calls'] = [];
$GLOBALS['mautic_http_handler'] = function($url, $args) {
    if (false !== strpos($url, '/api/contacts/42') && 'GET' === $args['method']) {
        return [
            'response' => ['code' => 200],
            'body' => json_encode(['contact' => ['id' => 42, 'tags' => [['tag' => 'legado'], ['tag' => 'interes:curso']]]]),
        ];
    }

    return ['response' => ['code' => 200], 'body' => json_encode(['success' => true])];
};
$merged_tags = FLACSO_Mautic_Client::merge_contact_tags(42, ['interes:curso', 'consulta:nueva']);
mautic_assert(true === $merged_tags['ok'], 'merge_contact_tags succeeds for a valid response');
mautic_assert(2 === count($GLOBALS['mautic_http_calls']), 'merge_contact_tags reads before updating');
$merge_request = $GLOBALS['mautic_http_calls'][1];
$merge_body = json_decode($merge_request['args']['body'], true);
mautic_assert('PATCH' === $merge_request['args']['method'], 'merge_contact_tags uses PATCH');
mautic_assert(in_array('legado', $merge_body['tags'], true), 'merge_contact_tags preserves old tags');
mautic_assert(in_array('consulta:nueva', $merge_body['tags'], true), 'merge_contact_tags adds incoming tags');
mautic_assert(false === str_contains($merge_request['args']['body'], 'secret'), 'merge_contact_tags never sends credentials in its body');

$GLOBALS['mautic_http_calls'] = [];
$GLOBALS['mautic_http_handler'] = function($url, $args) {
    if (false !== strpos($url, '/api/contacts/42/campaigns')) {
        return ['response' => ['code' => 200], 'body' => json_encode(['campaigns' => []])];
    }

    return ['response' => ['code' => 200], 'body' => json_encode(['success' => true])];
};
$campaign_added = FLACSO_Mautic_Client::add_contact_to_campaign(7, 42);
mautic_assert(true === $campaign_added['ok'], 'add_contact_to_campaign adds a non-member');
mautic_assert('added' === $campaign_added['status'], 'add_contact_to_campaign reports added');
mautic_assert(2 === count($GLOBALS['mautic_http_calls']), 'add_contact_to_campaign checks membership first');
mautic_assert(false !== strpos($GLOBALS['mautic_http_calls'][1]['url'], '/api/campaigns/7/contact/42/add'), 'add_contact_to_campaign uses the campaign endpoint');
mautic_assert(false === str_contains($GLOBALS['mautic_http_calls'][1]['args']['body'], 'secret'), 'campaign request body never includes credentials');

$GLOBALS['mautic_http_calls'] = [];
$GLOBALS['mautic_http_handler'] = function($url, $args) {
    return ['response' => ['code' => 200], 'body' => json_encode(['campaigns' => [['id' => 7, 'name' => 'Consulta abierta']]])];
};
$campaign_existing = FLACSO_Mautic_Client::add_contact_to_campaign(7, 42);
mautic_assert(true === $campaign_existing['ok'], 'add_contact_to_campaign accepts an existing member');
mautic_assert('already_member' === $campaign_existing['status'], 'add_contact_to_campaign is idempotent');
mautic_assert(1 === count($GLOBALS['mautic_http_calls']), 'already-member contacts are not posted again');

foreach ([401, 422, 500] as $error_code) {
    $GLOBALS['mautic_http_calls'] = [];
    $GLOBALS['mautic_http_handler'] = function($url, $args) use ($error_code) {
        return ['response' => ['code' => $error_code], 'body' => json_encode(['errors' => [['message' => 'Mautic error']]])];
    };
    $campaign_error = FLACSO_Mautic_Client::add_contact_to_campaign(7, 42);
    mautic_assert(false === $campaign_error['ok'], 'campaign errors fail for HTTP ' . $error_code);
    mautic_assert($error_code === $campaign_error['http_code'], 'campaign errors preserve HTTP ' . $error_code);
}

$GLOBALS['mautic_http_handler'] = function($url, $args) {
    return new WP_Error('http_request_failed', 'Timeout');
};
$campaign_timeout = FLACSO_Mautic_Client::add_contact_to_campaign(7, 42);
mautic_assert(false === $campaign_timeout['ok'], 'campaign timeout fails');
mautic_assert(0 === $campaign_timeout['http_code'], 'campaign timeout has no HTTP code');

// Test group 10: transporte inyectable y destinatario mínimo.
$GLOBALS['mautic_mock_options'] = [
    'flacso_mautic_enabled' => '1',
    'flacso_mautic_base_url' => 'https://mautic.example.org',
    'flacso_mautic_auth_type' => 'basic',
    'flacso_mautic_username' => 'admin',
    'flacso_mautic_password' => 'secret',
];

$transport_calls = [];
FLACSO_Mautic_Client::set_http_transport(function($url, $args) use (&$transport_calls) {
    $transport_calls[] = ['url' => $url, 'args' => $args];
    if (str_contains($url, '/api/contacts?search=')) {
        return ['response' => ['code' => 200], 'body' => json_encode(['contacts' => [['id' => 77]]])];
    }
    if (str_contains($url, '/api/contacts/77/edit')) {
        return ['response' => ['code' => 200], 'body' => json_encode(['contact' => ['id' => 77]])];
    }
    return ['response' => ['code' => 404], 'body' => '{}'];
});
$recipient = FLACSO_Mautic_Client::ensure_delivery_recipient('persona@example.org', 'Ana', 'Pérez');
mautic_assert($recipient['ok'] === true && $recipient['contact_id'] === 77, 'ensure_delivery_recipient reutiliza contacto');
mautic_assert(count($transport_calls) === 2, 'destinatario existente realiza búsqueda y PATCH');
$minimal_body = json_decode($transport_calls[1]['args']['body'], true);
$minimal_keys = array_keys($minimal_body);
sort($minimal_keys);
mautic_assert($minimal_keys === ['email', 'firstname', 'lastname'], 'PATCH del destinatario sólo contiene identidad mínima');
mautic_assert(!str_contains($transport_calls[1]['args']['body'], 'tags'), 'destinatario mínimo no agrega tags');
mautic_assert(!str_contains($transport_calls[1]['args']['body'], 'flacso_'), 'destinatario mínimo no escribe campos de consulta');

$create_calls = 0;
$search_calls = 0;
FLACSO_Mautic_Client::set_http_transport(function($url, $args) use (&$create_calls, &$search_calls) {
    if (str_contains($url, '/api/contacts?search=')) {
        $search_calls++;
        if ($search_calls === 1) {
            return ['response' => ['code' => 200], 'body' => json_encode(['contacts' => []])];
        }
        return ['response' => ['code' => 200], 'body' => json_encode(['contacts' => [['id' => 88]]])];
    }
    if (str_contains($url, '/api/contacts/new')) {
        $create_calls++;
        return new WP_Error('cURL error 28: Operation timed out', 'http_request_failed');
    }
    return ['response' => ['code' => 500], 'body' => '{}'];
});
$reconciled = FLACSO_Mautic_Client::ensure_delivery_recipient('nuevo@example.org', 'Nuevo', 'Contacto');
mautic_assert($reconciled['ok'] === true && $reconciled['action'] === 'reconciled', 'timeout de creación reconcilia por email');
mautic_assert($create_calls === 1, 'timeout de creación nunca repite POST automáticamente');

$unknown_posts = 0;
FLACSO_Mautic_Client::set_http_transport(function($url, $args) use (&$unknown_posts) {
    if (str_contains($url, '/api/contacts?search=')) {
        return ['response' => ['code' => 200], 'body' => json_encode(['contacts' => []])];
    }
    if (str_contains($url, '/api/contacts/new')) {
        $unknown_posts++;
        return new WP_Error('Connection reset after request', 'http_request_failed');
    }
    return ['response' => ['code' => 500], 'body' => '{}'];
});
$unknown = FLACSO_Mautic_Client::ensure_delivery_recipient('incierto@example.org', 'I', 'N');
mautic_assert($unknown['ok'] === false && $unknown['acceptance_unknown'] === true, 'creación incierta queda acceptance_unknown');
mautic_assert($unknown_posts === 1, 'resultado incierto no dispara un segundo POST');
FLACSO_Mautic_Client::set_http_transport(null);

// Test group 11: diagnósticos de campaña y búsqueda de contacto.
$GLOBALS['mautic_mock_options'] = [
    'flacso_mautic_enabled' => '1',
    'flacso_mautic_base_url' => 'https://mautic.example.org',
    'flacso_mautic_auth_type' => 'basic',
    'flacso_mautic_username' => 'admin',
    'flacso_mautic_password' => 'secret',
];
$GLOBALS['mautic_http_calls'] = [];
$GLOBALS['mautic_http_handler'] = function($url, $args) {
    if (str_ends_with($url, '/api/campaigns/4')) {
        return [
            'response' => ['code' => 200],
            'body' => json_encode(['campaign' => ['id' => 4, 'name' => 'Consultas web']]),
        ];
    }
    if (str_contains($url, '/api/contacts?search=email:prueba%40flacso.edu.uy')) {
        return [
            'response' => ['code' => 200],
            'body' => json_encode(['contacts' => [['id' => 10]]]),
        ];
    }
    return ['response' => ['code' => 404], 'body' => json_encode(['errors' => [['message' => 'Not found']]])];
};

$campaign_test = FLACSO_Mautic_Client::test_campaign(4);
mautic_assert($campaign_test['ok'] === true, 'test_campaign valida una campaña accesible');
mautic_assert(str_contains($campaign_test['message'], 'Consultas web'), 'test_campaign devuelve el nombre visible');

$contact_test = FLACSO_Mautic_Client::test_contact_search('prueba@flacso.edu.uy');
mautic_assert($contact_test['ok'] === true, 'test_contact_search ejecuta una consulta de solo lectura');
mautic_assert(str_contains($contact_test['message'], '1 contacto'), 'test_contact_search informa coincidencias');
mautic_assert(FLACSO_Mautic_Client::test_campaign(0)['ok'] === false, 'test_campaign rechaza un ID vacío');
mautic_assert(FLACSO_Mautic_Client::test_contact_search('correo-invalido')['ok'] === false, 'test_contact_search valida el correo');

echo "\nALL TESTS PASSED (100%)\n";
