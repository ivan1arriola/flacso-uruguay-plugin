<?php
define('ABSPATH', __DIR__ . '/../');
define('WP_CLI', true);
define('FLACSO_URUGUAY_PATH', __DIR__ . '/../');

class WP_CLI {
    public static $commands = [];
    public static function add_command($name, $callable) {
        self::$commands[$name] = $callable;
    }
    public static function line($msg) {}
    public static function log($msg) {}
    public static function success($msg) {}
    public static function error($msg) {}
    public static function warning($msg) {}
    public static function colorize($msg) { return $msg; }
}

require_once __DIR__ . '/../modules/oferta-academica/includes/class-academic-cli.php';
require_once __DIR__ . '/../modules/oferta-academica/includes/class-academic-meta-normalizer.php';

function assert_cli($condition, string $msg) {
    if (!$condition) {
        fwrite(STDERR, "Fallo CLI test: $msg\n");
        exit(1);
    }
}

assert_cli(isset(WP_CLI::$commands['flacso academic']), 'Comando WP-CLI flacso academic debe estar registrado');
assert_cli(method_exists(FLACSO_Academic_CLI_Command::class, 'migrate'), 'Metodo migrate debe existir');
assert_cli(method_exists(FLACSO_Academic_CLI_Command::class, 'status'), 'Metodo status debe existir');
assert_cli(isset(WP_CLI::$commands['flacso academic-meta']), 'Comando de normalización de metadatos debe estar registrado');
assert_cli(method_exists(FLACSO_Academic_Meta_Normalizer::class, 'normalize'), 'Metodo normalize debe existir');

fwrite(STDOUT, "OK academic CLI contract test\n");
