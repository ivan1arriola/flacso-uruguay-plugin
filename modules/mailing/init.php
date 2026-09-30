<?php
/**
 * Módulo de Mailing - FLACSO Uruguay
 * Suscripción de contactos a listas de Mailjet
 */

if (!defined('ABSPATH')) {
    exit;
}

if (!defined('FLACSO_MAILING_MODULE_PATH')) {
    define('FLACSO_MAILING_MODULE_PATH', __DIR__ . '/');
}

if (!defined('FLACSO_MAILING_MODULE_URL')) {
    define('FLACSO_MAILING_MODULE_URL', plugin_dir_url(__FILE__));
}

if (!defined('FLACSO_MAILING_MODULE_VERSION')) {
    define('FLACSO_MAILING_MODULE_VERSION', FLACSO_URUGUAY_VERSION);
}

flacso_safe_require('modules/mailing/includes/class-flacso-mail-settings.php');

if (class_exists('FLACSO_Mail_Settings')) {
    FLACSO_Mail_Settings::init();
}
