<?php
/**
 * Conector de base de datos para FLACSO Uruguay (PostgreSQL vía PDO).
 */

if (!defined('ABSPATH') && !defined('STDIN')) {
    exit;
}

class FLACSO_DB {
    private static ?PDO $connection = null;

    public static function connection(): PDO {
        if (self::$connection instanceof PDO) {
            return self::$connection;
        }

        if (!self::is_configured()) {
            throw new RuntimeException('PostgreSQL no está configurado. Defina FLACSO_PG_HOST, FLACSO_PG_PORT, FLACSO_PG_DATABASE, FLACSO_PG_USER y FLACSO_PG_PASSWORD.');
        }

        if (!extension_loaded('pdo_pgsql')) {
            throw new RuntimeException('La extensión PHP pdo_pgsql no está disponible en este servidor.');
        }

        $dsn = sprintf(
            'pgsql:host=%s;port=%s;dbname=%s',
            FLACSO_PG_HOST,
            FLACSO_PG_PORT,
            FLACSO_PG_DATABASE
        );

        self::$connection = new PDO(
            $dsn,
            FLACSO_PG_USER,
            FLACSO_PG_PASSWORD,
            [
                PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_TIMEOUT            => 5,
            ]
        );

        return self::$connection;
    }

    public static function set_connection(?PDO $pdo): void {
        self::$connection = $pdo;
    }

    public static function reset(): void {
        self::$connection = null;
    }

    public static function is_configured(): bool {
        if (self::$connection instanceof PDO) {
            return true;
        }

        return defined('FLACSO_PG_HOST') &&
               defined('FLACSO_PG_PORT') &&
               defined('FLACSO_PG_DATABASE') &&
               defined('FLACSO_PG_USER') &&
               defined('FLACSO_PG_PASSWORD') &&
               FLACSO_PG_HOST !== '' &&
               FLACSO_PG_DATABASE !== '';
    }
}
