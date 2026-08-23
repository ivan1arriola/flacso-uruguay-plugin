<?php
/**
 * Integración de Telegram de FLACSO Uruguay.
 */

if (!defined('ABSPATH')) {
    exit;
}

flacso_require('modules/main-page/includes/class-flacso-telegram-manager.php');

add_action('plugins_loaded', static function (): void {
    if (class_exists('FLACSO_Telegram_Manager')) {
        FLACSO_Telegram_Manager::get_instance();
    }
}, 20);
