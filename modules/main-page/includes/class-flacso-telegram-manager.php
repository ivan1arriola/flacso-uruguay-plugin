<?php
/**
 * Clase encapsulada para gestionar las funciones de Telegram
 * Permite que otros plugins accedan a las funcionalidades de Telegram
 * 
 * @package flacso-main-page
 * @since 1.0.0
 */

if (!defined('ABSPATH')) {
    exit;
}

class FLACSO_Telegram_Manager {
    
    /**
     * Instancia única de la clase
     * @var FLACSO_Telegram_Manager
     */
    private static $instance = null;
    
    /**
     * Indica si Telegram Pulse está disponible
     * @var bool
     */
    private $telegram_available = false;
    
    /**
     * Constructor privado para Singleton
     */
    private function __construct() {
        $this->check_telegram_availability();
        $this->setup_hooks();
    }
    
    /**
     * Obtener instancia única
     * 
     * @return FLACSO_Telegram_Manager
     */
    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    /**
     * Verificar si Telegram Pulse está disponible
     */
    private function check_telegram_availability() {
        $this->telegram_available = class_exists('WPTELEGRAM_API') && 
                                   class_exists('WPTELEGRAM_Core_Simple');
        
        if ($this->telegram_available) {
            $this->telegram_available = WPTELEGRAM_Core_Simple::is_configured();
        }
    }
    
    /**
     * Configurar hooks para que otros plugins puedan usar
     */
    private function setup_hooks() {
        // Hook para permitir que otros plugins verifiquen disponibilidad
        add_filter('flacso_telegram_is_available', [$this, 'is_available']);
        
        // Hook para enviar mensajes desde otros plugins
        add_filter('flacso_telegram_send_message', [$this, 'send_message'], 10, 3);
        
        // Hook para enviar mensaje de prueba
        add_action('flacso_telegram_send_test', [$this, 'send_test_message'], 10, 1);
        
        // Hook para obtener configuración
        add_filter('flacso_telegram_get_settings', [$this, 'get_settings']);
    }
    
    /**
     * Verificar si Telegram está disponible y configurado
     * 
     * @return bool
     */
    public function is_available() {
        return $this->telegram_available;
    }
    
    /**
     * Enviar mensaje a través de Telegram
     * 
     * @param string $message Mensaje a enviar (puede incluir HTML)
     * @param string $parse_mode Modo de parseo (HTML o Markdown)
     * @param string|array|null $chat_id Chat ID específico o null para usar el configurado
     * @return array|WP_Error Respuesta de la API o error
     */
    public function send_message($message, $parse_mode = 'HTML', $chat_id = null) {
        if (!$this->is_available()) {
            return new WP_Error(
                'telegram_not_available',
                'Telegram no está disponible o no está configurado correctamente.'
            );
        }
        
        try {
            $result = WPTELEGRAM_API::send_message($message, $parse_mode, $chat_id);
            
            // Log del envío
            if (!is_wp_error($result)) {
                $this->log_message_sent($message, $result);
            }
            
            return $result;
        } catch (Exception $e) {
            return new WP_Error(
                'telegram_error',
                'Error al enviar mensaje: ' . $e->getMessage()
            );
        }
    }
    
    /**
     * Enviar mensaje de prueba
     * 
     * @param string|null $chat_id Chat ID específico
     * @return array|WP_Error
     */
    public function send_test_message($chat_id = null) {
        if (!$this->is_available()) {
            return new WP_Error(
                'telegram_not_available',
                'Telegram no está disponible o no está configurado correctamente.'
            );
        }
        
        return WPTELEGRAM_API::send_test_message($chat_id);
    }
    
    /**
     * Obtener configuración de Telegram
     * 
     * @return array
     */
    public function get_settings() {
        if (!class_exists('WPTELEGRAM_Core_Simple')) {
            return [];
        }
        
        return WPTELEGRAM_Core_Simple::get_settings();
    }
    
    /**
     * Enviar notificación de seguridad formateada
     * 
     * @param string $title Título de la notificación
     * @param array $data Datos a incluir en la notificación
     * @param string $severity Nivel de severidad (info, warning, critical)
     * @return array|WP_Error
     */
    public function send_security_notification($title, $data = [], $severity = 'info') {
        if (!$this->is_available()) {
            return new WP_Error('telegram_not_available', 'Telegram no disponible');
        }
        
        $message = $this->format_security_message($title, $data, $severity);
        return $this->send_message($message);
    }
    
    /**
     * Formatear mensaje de seguridad
     * 
     * @param string $title Título
     * @param array $data Datos
     * @param string $severity Severidad
     * @return string
     */
    private function format_security_message($title, $data, $severity) {
        $icons = [
            'info' => '🔵',
            'warning' => '⚠️',
            'critical' => '🔴',
            'success' => '✅'
        ];
        
        $icon = $icons[$severity] ?? '🔵';
        
        $message = "{$icon} <b>{$title}</b>\n\n";
        
        foreach ($data as $key => $value) {
            $key_formatted = ucfirst(str_replace('_', ' ', $key));
            $value_escaped = esc_html($value);
            $message .= "<b>{$key_formatted}:</b> {$value_escaped}\n";
        }
        
        $message .= "\n<i>Sitio:</i> " . esc_html(get_bloginfo('name'));
        $message .= "\n<i>Fecha:</i> " . current_time('Y-m-d H:i:s');
        
        return $message;
    }
    
    /**
     * Enviar notificación de evento de WordPress
     * 
     * @param string $event_type Tipo de evento
     * @param array $details Detalles del evento
     * @return array|WP_Error
     */
    public function send_wordpress_event($event_type, $details = []) {
        if (!$this->is_available()) {
            return new WP_Error('telegram_not_available', 'Telegram no disponible');
        }
        
        $event_icons = [
            'login' => '🔐',
            'logout' => '🚪',
            'user_register' => '👤',
            'post_publish' => '📝',
            'plugin_activated' => '🔌',
            'plugin_deactivated' => '🔌',
            'theme_switched' => '🎨',
            'update' => '🔄',
            'error' => '❌',
            'blocked' => '🚫'
        ];
        
        $icon = $event_icons[$event_type] ?? '📢';
        $title = ucwords(str_replace('_', ' ', $event_type));
        
        $message = "{$icon} <b>{$title}</b>\n\n";
        
        foreach ($details as $key => $value) {
            if (is_array($value)) {
                $value = implode(', ', $value);
            }
            $key_formatted = ucfirst(str_replace('_', ' ', $key));
            $value_escaped = esc_html($value);
            $message .= "<b>{$key_formatted}:</b> {$value_escaped}\n";
        }
        
        $message .= "\n<i>Sitio:</i> " . esc_html(get_bloginfo('name'));
        $message .= "\n<i>Hora:</i> " . current_time('H:i:s');
        
        return $this->send_message($message);
    }
    
    /**
     * Registrar mensaje enviado
     * 
     * @param string $message Mensaje enviado
     * @param array $result Resultado de la API
     */
    private function log_message_sent($message, $result) {
        if (defined('WP_DEBUG') && WP_DEBUG) {
            $chats = $result['delivered_to'] ?? ['unknown'];
            $log_message = sprintf(
                'Mensaje de Telegram enviado a %s: %s',
                implode(', ', $chats),
                substr($message, 0, 100)
            );
            error_log($log_message);
        }
    }
    
    /**
     * Obtener información del bot
     * 
     * @return array|WP_Error
     */
    public function get_bot_info() {
        if (!$this->is_available()) {
            return new WP_Error('telegram_not_available', 'Telegram no disponible');
        }
        
        return WPTELEGRAM_API::get_bot_info();
    }
    
    /**
     * Verificar si un chat ID es válido
     * 
     * @param string $chat_id Chat ID a verificar
     * @return bool|WP_Error
     */
    public function validate_chat_id($chat_id) {
        if (!$this->is_available()) {
            return new WP_Error('telegram_not_available', 'Telegram no disponible');
        }
        
        $result = $this->send_message('✅ Chat ID validado correctamente', 'HTML', $chat_id);
        
        return !is_wp_error($result);
    }
}

/**
 * Función helper para obtener la instancia del manager
 * 
 * @return FLACSO_Telegram_Manager
 */
function flacso_telegram() {
    return FLACSO_Telegram_Manager::get_instance();
}

/**
 * Función helper para enviar mensaje rápido
 * 
 * @param string $message Mensaje
 * @param string $parse_mode Modo de parseo
 * @param string|null $chat_id Chat ID
 * @return array|WP_Error
 */
function flacso_telegram_send($message, $parse_mode = 'HTML', $chat_id = null) {
    return flacso_telegram()->send_message($message, $parse_mode, $chat_id);
}

/**
 * Función helper para verificar disponibilidad
 * 
 * @return bool
 */
function flacso_telegram_available() {
    return flacso_telegram()->is_available();
}

