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

$GLOBALS['mautic_mock_options'] = [];
$settings = FLACSO_Mautic_Client::get_settings();
mautic_assert($settings['enabled'] === false, 'Default enabled is false');
mautic_assert($settings['base_url'] === 'https://envios.flacso.edu.uy', 'Default base_url is https://envios.flacso.edu.uy');
mautic_assert($settings['auth_type'] === 'basic', 'Default auth_type is basic');
mautic_assert($settings['username'] === '', 'Default username is empty');
mautic_assert($settings['password'] === '', 'Default password is empty');
mautic_assert($settings['token'] === '', 'Default token is empty');
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

// HTTP 401 Unauthorized
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
    // 2. PATCH /api/contacts/42/edit updates contact
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

// Verify calls made: 1st search, 2nd PATCH
mautic_assert(count($GLOBALS['mautic_http_calls']) === 2, 'Update makes 2 calls: search then PATCH');
$patch_call = $GLOBALS['mautic_http_calls'][1];
mautic_assert(str_ends_with($patch_call['url'], '/api/contacts/42/edit'), 'Second call target is /api/contacts/42/edit');
mautic_assert(($patch_call['args']['method'] ?? '') === 'PATCH', 'Second call uses PATCH');
$patch_payload = json_decode($patch_call['args']['body'], true);
mautic_assert($patch_payload['firstname'] === 'Carlos Updated', 'PATCH payload contains updated field');
mautic_assert($patch_payload['tags'] === ['nuevo-tag-c2'], 'PATCH payload contains tags');

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

echo "\nALL TESTS PASSED (100%)\n";
