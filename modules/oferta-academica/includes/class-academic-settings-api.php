<?php

if (!defined("ABSPATH")) {
    exit;
}

/**
 * Endpoints REST compatibles para la configuración global de ofertas e integraciones.
 * Expone /wp-json/flacso/v1/ofertas/settings y /wp-json/flacso/v1/ofertas/settings/mailjet-lists
 */
final class FLACSO_Academic_Settings_API {
    public static function init(): void {
        add_action("rest_api_init", [self::class, "register_routes"]);
    }

    public static function register_routes(): void {
        register_rest_route("flacso/v1", "/ofertas/settings", [
            [
                "methods" => WP_REST_Server::READABLE,
                "callback" => [self::class, "get_settings"],
                "permission_callback" => [self::class, "can_read_settings"],
            ],
            [
                "methods" => WP_REST_Server::EDITABLE,
                "callback" => [self::class, "update_settings"],
                "permission_callback" => [self::class, "can_manage_settings"],
            ],
        ]);

    }

    public static function can_read_settings(WP_REST_Request $request): bool {
        $include_secrets = rest_sanitize_boolean($request->get_param("include_secrets"));
        if (!$include_secrets) {
            return true;
        }
        return self::can_manage_settings();
    }

    public static function can_manage_settings(): bool {
        return current_user_can("manage_options") || current_user_can("edit_posts");
    }

    public static function get_settings(?WP_REST_Request $request = null) {
        $include_secrets = false;
        if ($request instanceof WP_REST_Request) {
            $include_secrets = rest_sanitize_boolean($request->get_param("include_secrets"));
            if ($include_secrets && !self::can_manage_settings()) {
                $include_secrets = false;
            }
        }

        $token = get_option("flacso_webhook_token", "");
        $telegram_bot_token = get_option("flacso_preinscripciones_telegram_bot_token", "");
        $fc_telegram_bot_token = get_option("fc_telegram_bot_token", "");
        $fc_recaptcha_secret_key = get_option("fc_recaptcha_secret_key", "");
        $flacso_mailjet_api_key = get_option("flacso_mailjet_api_key", "");
        $flacso_mailjet_secret_key = get_option("flacso_mailjet_secret_key", "");
        $flacso_meta_access_token = get_option("flacso_meta_access_token", "");

        $masked_token = !empty($token) && !$include_secrets ? "********" : $token;
        $masked_telegram_bot_token = !empty($telegram_bot_token) && !$include_secrets ? "********" : $telegram_bot_token;
        $masked_fc_telegram_bot_token = !empty($fc_telegram_bot_token) && !$include_secrets ? "********" : $fc_telegram_bot_token;
        $masked_fc_recaptcha_secret_key = !empty($fc_recaptcha_secret_key) && !$include_secrets ? "********" : $fc_recaptcha_secret_key;
        $masked_flacso_mailjet_api_key = !empty($flacso_mailjet_api_key) && !$include_secrets ? "********" : $flacso_mailjet_api_key;
        $masked_flacso_mailjet_secret_key = !empty($flacso_mailjet_secret_key) && !$include_secrets ? "********" : $flacso_mailjet_secret_key;
        $masked_flacso_meta_access_token = !empty($flacso_meta_access_token) && !$include_secrets ? "********" : $flacso_meta_access_token;

        return rest_ensure_response([
            "ok" => true,
            "data" => array_merge([                "mensaje_bienvenida" => get_option("flacso_mensaje_bienvenida", ""),
                "flacso_webhook_token" => $masked_token,
                "flacso_google_drive_folder_id" => get_option("flacso_google_drive_folder_id", ""),
                "flacso_webhook_forms_drive_folder_id" => get_option("flacso_webhook_forms_drive_folder_id", ""),
                "flacso_preinscripciones_telegram_bot_token" => $masked_telegram_bot_token,
                "flacso_preinscripciones_telegram_chat_id" => get_option("flacso_preinscripciones_telegram_chat_id", ""),
                "correos_excluidos" => get_option("flacso_correos_excluidos", ""),

                // Variables de integraciones
                "fc_consultas_webhook_url" => get_option("fc_consultas_webhook_url", ""),
                "fc_oferta_webhook_url" => get_option("fc_oferta_webhook_url", ""),
                "flacso_charlas_abiertas_webhook_url" => get_option("flacso_charlas_abiertas_webhook_url", ""),
                "flacso_preinscripciones_webhook_url" => get_option("flacso_preinscripciones_webhook_url", ""),
                "flacso_oferta_consulta_endpoint_url" => get_option("flacso_oferta_consulta_endpoint_url", ""),
                "flacso_external_editor_url" => get_option("flacso_external_editor_url", "https://editor.flacso.edu.uy"),
                "fc_telegram_bot_token" => $masked_fc_telegram_bot_token,
                "fc_telegram_chat_id" => get_option("fc_telegram_chat_id", ""),
                "fc_recaptcha_site_key" => get_option("fc_recaptcha_site_key", ""),
                "fc_recaptcha_secret_key" => $masked_fc_recaptcha_secret_key,
                "flacso_mailjet_api_key" => $masked_flacso_mailjet_api_key,
                "flacso_mailjet_secret_key" => $masked_flacso_mailjet_secret_key,
                "flacso_mailjet_list_id" => get_option("flacso_mailjet_list_id", ""),
                "flacso_mailjet_sender_email" => get_option("flacso_mailjet_sender_email", get_option("admin_email")),
                "flacso_mailjet_sender_name" => get_option("flacso_mailjet_sender_name", wp_specialchars_decode(get_bloginfo("name"), ENT_QUOTES)),
                "flacso_meta_enabled" => (bool) get_option("flacso_meta_enabled", 0),
                "flacso_meta_pixel_id" => get_option("flacso_meta_pixel_id", ""),
                "flacso_meta_access_token" => $masked_flacso_meta_access_token,
                "flacso_meta_test_event_code" => get_option("flacso_meta_test_event_code", ""),
                "flacso_meta_track_pageview" => (bool) get_option("flacso_meta_track_pageview", 1),
                "flacso_general_inquiries_email" => get_option("flacso_general_inquiries_email", ""),
            ], FLACSO_Carta_Settings::all()) // Textos de la carta: claves y valores por defecto en FLACSO_Carta_Settings
        ]);
    }

    public static function update_settings(WP_REST_Request $request) {
        $payload = $request->get_json_params();
        if (!is_array($payload)) {
            $payload = [];
        }

        if (isset($payload["mensaje_bienvenida"])) {
            update_option("flacso_mensaje_bienvenida", wp_kses_post($payload["mensaje_bienvenida"]));
        }
        if (isset($payload["flacso_webhook_token"])) {
            $new_token = sanitize_text_field($payload["flacso_webhook_token"]);
            if ($new_token !== "********") {
                update_option("flacso_webhook_token", $new_token);
            }
        }
        if (isset($payload["flacso_google_drive_folder_id"])) {
            update_option("flacso_google_drive_folder_id", sanitize_text_field($payload["flacso_google_drive_folder_id"]));
        }
        if (isset($payload["flacso_webhook_forms_drive_folder_id"])) {
            update_option("flacso_webhook_forms_drive_folder_id", sanitize_text_field($payload["flacso_webhook_forms_drive_folder_id"]));
        }
        if (isset($payload["flacso_preinscripciones_telegram_bot_token"])) {
            $new_telegram_bot_token = sanitize_text_field($payload["flacso_preinscripciones_telegram_bot_token"]);
            if ($new_telegram_bot_token !== "********") {
                update_option("flacso_preinscripciones_telegram_bot_token", $new_telegram_bot_token);
            }
        }
        if (isset($payload["flacso_preinscripciones_telegram_chat_id"])) {
            update_option("flacso_preinscripciones_telegram_chat_id", sanitize_text_field($payload["flacso_preinscripciones_telegram_chat_id"]));
        }
        if (isset($payload["correos_excluidos"])) {
            update_option("flacso_correos_excluidos", sanitize_textarea_field($payload["correos_excluidos"]));
        }
        FLACSO_Carta_Settings::update_from_payload($payload);

        // Variables de integraciones
        if (isset($payload["fc_consultas_webhook_url"])) {
            update_option("fc_consultas_webhook_url", esc_url_raw($payload["fc_consultas_webhook_url"]));
        }
        if (isset($payload["fc_oferta_webhook_url"])) {
            update_option("fc_oferta_webhook_url", esc_url_raw($payload["fc_oferta_webhook_url"]));
        }
        if (isset($payload["flacso_charlas_abiertas_webhook_url"])) {
            update_option("flacso_charlas_abiertas_webhook_url", esc_url_raw($payload["flacso_charlas_abiertas_webhook_url"]));
        }
        if (isset($payload["flacso_preinscripciones_webhook_url"])) {
            update_option("flacso_preinscripciones_webhook_url", esc_url_raw($payload["flacso_preinscripciones_webhook_url"]));
        }
        if (isset($payload["flacso_oferta_consulta_endpoint_url"])) {
            update_option("flacso_oferta_consulta_endpoint_url", esc_url_raw($payload["flacso_oferta_consulta_endpoint_url"]));
        }
        if (isset($payload["flacso_external_editor_url"])) {
            update_option("flacso_external_editor_url", esc_url_raw($payload["flacso_external_editor_url"]));
        }
        if (isset($payload["fc_telegram_bot_token"])) {
            $new_val = sanitize_text_field($payload["fc_telegram_bot_token"]);
            if ($new_val !== "********") {
                update_option("fc_telegram_bot_token", $new_val);
            }
        }
        if (isset($payload["fc_telegram_chat_id"])) {
            update_option("fc_telegram_chat_id", sanitize_text_field($payload["fc_telegram_chat_id"]));
        }
        if (isset($payload["fc_recaptcha_site_key"])) {
            update_option("fc_recaptcha_site_key", sanitize_text_field($payload["fc_recaptcha_site_key"]));
        }
        if (isset($payload["fc_recaptcha_secret_key"])) {
            $new_val = sanitize_text_field($payload["fc_recaptcha_secret_key"]);
            if ($new_val !== "********") {
                update_option("fc_recaptcha_secret_key", $new_val);
            }
        }
        if (isset($payload["flacso_mailjet_api_key"])) {
            $new_val = sanitize_text_field($payload["flacso_mailjet_api_key"]);
            if ($new_val !== "********") {
                update_option("flacso_mailjet_api_key", $new_val);
            }
        }
        if (isset($payload["flacso_mailjet_secret_key"])) {
            $new_val = sanitize_text_field($payload["flacso_mailjet_secret_key"]);
            if ($new_val !== "********") {
                update_option("flacso_mailjet_secret_key", $new_val);
            }
        }
        if (isset($payload["flacso_mailjet_list_id"])) {
            update_option("flacso_mailjet_list_id", sanitize_text_field($payload["flacso_mailjet_list_id"]));
        }
        if (isset($payload["flacso_mailjet_sender_email"])) {
            update_option("flacso_mailjet_sender_email", sanitize_email($payload["flacso_mailjet_sender_email"]));
        }
        if (isset($payload["flacso_mailjet_sender_name"])) {
            update_option("flacso_mailjet_sender_name", sanitize_text_field($payload["flacso_mailjet_sender_name"]));
        }
        if (isset($payload["flacso_meta_enabled"])) {
            update_option("flacso_meta_enabled", !empty($payload["flacso_meta_enabled"]) ? 1 : 0);
        }
        if (isset($payload["flacso_meta_pixel_id"])) {
            update_option("flacso_meta_pixel_id", sanitize_text_field($payload["flacso_meta_pixel_id"]));
        }
        if (isset($payload["flacso_meta_access_token"])) {
            $new_val = sanitize_text_field($payload["flacso_meta_access_token"]);
            if ($new_val !== "********") {
                update_option("flacso_meta_access_token", $new_val);
            }
        }
        if (isset($payload["flacso_meta_test_event_code"])) {
            update_option("flacso_meta_test_event_code", sanitize_text_field($payload["flacso_meta_test_event_code"]));
        }
        if (isset($payload["flacso_meta_track_pageview"])) {
            update_option("flacso_meta_track_pageview", !empty($payload["flacso_meta_track_pageview"]) ? 1 : 0);
        }
        if (isset($payload["flacso_general_inquiries_email"])) {
            update_option("flacso_general_inquiries_email", sanitize_email($payload["flacso_general_inquiries_email"]));
        }

        return self::get_settings();
    }

    public static function get_mailjet_lists_endpoint() {
        if (class_exists("FLACSO_Mail_Settings") && method_exists("FLACSO_Mail_Settings", "get_contact_lists")) {
            return rest_ensure_response([
                "ok" => true,
                "lists" => FLACSO_Mail_Settings::get_contact_lists(),
            ]);
        }

        // Compatibilidad transitoria para instalaciones que todavía no cargaron
        // el módulo Mailing al resolver este endpoint.
        if (class_exists("FLACSO_Integrations_Settings") && method_exists("FLACSO_Integrations_Settings", "get_mailjet_contact_lists")) {
            return rest_ensure_response([
                "ok" => true,
                "lists" => FLACSO_Integrations_Settings::get_mailjet_contact_lists(),
            ]);
        }

        return rest_ensure_response([
            "ok" => false,
            "message" => "La configuración de Correos/Mailjet no está disponible.",
        ]);
    }
}
