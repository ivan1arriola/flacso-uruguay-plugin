<?php

if (!defined('ABSPATH')) {
    exit;
}

class FLACSO_Integrations_Settings {
    private const PAGE_SLUG = 'flacso-integraciones';
    private const SETTINGS_GROUP = 'flacso_integraciones_group';

    private const OPTION_CONSULTAS_WEBHOOK_URL = 'fc_consultas_webhook_url';
    private const OPTION_UNIFIED_WEBHOOK_TOKEN = 'flacso_webhook_token';
    private const OPTION_INFO_REQUEST_WEBHOOK_URL = 'fc_oferta_webhook_url';
    private const OPTION_OFERTA_FLOTANTE_ENDPOINT = 'flacso_oferta_consulta_endpoint_url';
    private const OPTION_CHARLAS_WEBHOOK_URL = 'flacso_charlas_abiertas_webhook_url';
    private const OPTION_PREINSCRIPCIONES_WEBHOOK_URL = 'flacso_preinscripciones_webhook_url';
    private const OPTION_TELEGRAM_BOT_TOKEN = 'fc_telegram_bot_token';
    private const OPTION_TELEGRAM_CHAT_ID = 'fc_telegram_chat_id';
    private const OPTION_RECAPTCHA_SITE_KEY = 'fc_recaptcha_site_key';
    private const OPTION_RECAPTCHA_SECRET_KEY = 'fc_recaptcha_secret_key';

    public static function init(): void {
        if (!is_admin()) {
            return;
        }

        add_action('admin_menu', [self::class, 'register_menu']);
        add_action('admin_init', [self::class, 'register_settings']);
    }

    public static function register_menu(): void {
        add_submenu_page(
            'options-general.php',
            __('Integraciones FLACSO', 'flacso-uruguay'),
            __('Integraciones FLACSO', 'flacso-uruguay'),
            'manage_options',
            self::PAGE_SLUG,
            [self::class, 'render_page']
        );
    }

    public static function register_settings(): void {
        register_setting(
            self::SETTINGS_GROUP,
            self::OPTION_CONSULTAS_WEBHOOK_URL,
            [
                'type' => 'string',
                'sanitize_callback' => 'esc_url_raw',
                'default' => '',
            ]
        );

        register_setting(
            self::SETTINGS_GROUP,
            self::OPTION_UNIFIED_WEBHOOK_TOKEN,
            [
                'type' => 'string',
                'sanitize_callback' => 'sanitize_text_field',
                'default' => '',
            ]
        );

        register_setting(
            self::SETTINGS_GROUP,
            self::OPTION_INFO_REQUEST_WEBHOOK_URL,
            [
                'type' => 'string',
                'sanitize_callback' => 'esc_url_raw',
                'default' => '',
            ]
        );

        register_setting(
            self::SETTINGS_GROUP,
            self::OPTION_OFERTA_FLOTANTE_ENDPOINT,
            [
                'type' => 'string',
                'sanitize_callback' => [self::class, 'sanitize_oferta_flotante_endpoint'],
                'default' => '',
            ]
        );

        register_setting(
            self::SETTINGS_GROUP,
            self::OPTION_CHARLAS_WEBHOOK_URL,
            [
                'type' => 'string',
                'sanitize_callback' => [self::class, 'sanitize_charlas_webhook_url'],
                'default' => '',
            ]
        );


        register_setting(
            self::SETTINGS_GROUP,
            self::OPTION_PREINSCRIPCIONES_WEBHOOK_URL,
            [
                'type' => 'string',
                'sanitize_callback' => 'esc_url_raw',
                'default' => '',
            ]
        );

        register_setting(
            self::SETTINGS_GROUP,
            self::OPTION_TELEGRAM_BOT_TOKEN,
            [
                'type' => 'string',
                'sanitize_callback' => 'sanitize_text_field',
                'default' => '',
            ]
        );

        register_setting(
            self::SETTINGS_GROUP,
            self::OPTION_TELEGRAM_CHAT_ID,
            [
                'type' => 'string',
                'sanitize_callback' => 'sanitize_text_field',
                'default' => '',
            ]
        );

        register_setting(
            self::SETTINGS_GROUP,
            self::OPTION_RECAPTCHA_SITE_KEY,
            [
                'type' => 'string',
                'sanitize_callback' => 'sanitize_text_field',
                'default' => '',
            ]
        );

        register_setting(
            self::SETTINGS_GROUP,
            self::OPTION_RECAPTCHA_SECRET_KEY,
            [
                'type' => 'string',
                'sanitize_callback' => 'sanitize_text_field',
                'default' => '',
            ]
        );
    }

    public static function sanitize_charlas_webhook_url($value): string {
        if (function_exists('flacso_charlas_abiertas_sanitize_webhook_url')) {
            return (string) flacso_charlas_abiertas_sanitize_webhook_url($value);
        }

        return (string) esc_url_raw($value);
    }

    public static function sanitize_charlas_webhook_token($value): string {
        if (function_exists('flacso_charlas_abiertas_sanitize_webhook_token')) {
            return (string) flacso_charlas_abiertas_sanitize_webhook_token($value);
        }

        return trim((string) $value);
    }

    public static function sanitize_oferta_flotante_endpoint($value): string {
        if (class_exists('Oferta_Consulta_Form') && is_callable(['Oferta_Consulta_Form', 'sanitize_endpoint_url'])) {
            return (string) Oferta_Consulta_Form::sanitize_endpoint_url($value);
        }

        return (string) esc_url_raw($value);
    }

    public static function get_page_url(array $args = []): string {
        return add_query_arg(
            array_merge(
                [
                    'page' => self::PAGE_SLUG,
                ],
                $args
            ),
            admin_url('options-general.php')
        );
    }

    public static function get_redirect_url_from_request(array $args = [], string $default_url = ''): string {
        $base_url = $default_url !== '' ? $default_url : self::get_page_url();

        $requested = isset($_POST['redirect_to'])
            ? esc_url_raw(wp_unslash($_POST['redirect_to']))
            : '';

        if ($requested !== '') {
            $validated = wp_validate_redirect($requested, '');
            if ($validated !== '') {
                $base_url = $validated;
            }
        }

        return add_query_arg($args, $base_url);
    }

    public static function render_page(): void {
        if (!current_user_can('manage_options')) {
            return;
        }

        ?>
        <div class="wrap">
            <h1><?php esc_html_e('Integraciones FLACSO', 'flacso-uruguay'); ?></h1>
            <p><?php esc_html_e('Centralizá aquí los endpoints, tokens y claves que hoy están repartidos entre distintos módulos del plugin.', 'flacso-uruguay'); ?></p>

            <?php settings_errors(); ?>
            <?php self::render_inline_notices(); ?>

            <form method="post" action="options.php">
                <?php settings_fields(self::SETTINGS_GROUP); ?>

                <div class="flacso-integrations-grid">
                    <?php self::render_consultas_card(); ?>
                    <?php self::render_charlas_card(); ?>
                    <?php self::render_oferta_flotante_card(); ?>
                    <?php self::render_preinscripciones_card(); ?>
                    <?php self::render_services_card(); ?>
                </div>

                <?php submit_button(__('Guardar integraciones', 'flacso-uruguay')); ?>
            </form>

            <div class="flacso-integrations-tests">
                <h2><?php esc_html_e('Pruebas rápidas', 'flacso-uruguay'); ?></h2>
                <p><?php esc_html_e('Estas acciones validan que el plugin pueda hablar con la app usando la configuración actual.', 'flacso-uruguay'); ?></p>
                <div class="flacso-integrations-test-grid">
                    <?php self::render_test_form(
                        'fc_test_consultas_webhook',
                        'fc_consultas_webhook_test_nonce',
                        'fc_test_consultas_webhook',
                        __('Probar consultas generales', 'flacso-uruguay'),
                        __('Valida el webhook del formulario de consulta general.', 'flacso-uruguay')
                    ); ?>
                    <?php self::render_test_form(
                        'fc_test_oferta_webhook',
                        'fc_oferta_webhook_test_nonce',
                        'fc_test_oferta_webhook',
                        __('Probar solicitud de información', 'flacso-uruguay'),
                        __('Valida el webhook usado por el bloque de solicitud de información.', 'flacso-uruguay')
                    ); ?>
                    <?php self::render_test_form(
                        'flacso_charlas_abiertas_test_webhook',
                        'flacso_charlas_abiertas_test_webhook_nonce',
                        'flacso_charlas_abiertas_test_webhook',
                        __('Probar charlas abiertas', 'flacso-uruguay'),
                        __('Valida el webhook de inscripciones de charlas abiertas.', 'flacso-uruguay')
                    ); ?>
                </div>
            </div>

            <div class="flacso-integrations-links">
                <h2><?php esc_html_e('Pantallas relacionadas', 'flacso-uruguay'); ?></h2>
                <ul>
                    <li><a href="<?php echo esc_url(admin_url('edit.php?post_type=oferta-academica&page=flacso-oferta-consulta-form')); ?>"><?php esc_html_e('Formulario flotante de Oferta Académica', 'flacso-uruguay'); ?></a></li>
                    <li><a href="<?php echo esc_url(admin_url('admin.php?page=flacso-preinscripciones')); ?>"><?php esc_html_e('Preinscripciones', 'flacso-uruguay'); ?></a></li>
                </ul>
            </div>
        </div>

        <style>
            .flacso-integrations-grid {
                display: grid;
                grid-template-columns: repeat(auto-fit, minmax(320px, 1fr));
                gap: 18px;
                margin-top: 18px;
            }

            .flacso-integrations-card,
            .flacso-integrations-tests,
            .flacso-integrations-links {
                background: #fff;
                border: 1px solid #dcdcde;
                border-radius: 10px;
                padding: 20px;
                box-shadow: 0 1px 2px rgba(16, 24, 40, 0.04);
            }

            .flacso-integrations-card h2,
            .flacso-integrations-tests h2,
            .flacso-integrations-links h2 {
                margin-top: 0;
                margin-bottom: 8px;
            }

            .flacso-integrations-card p.flacso-integrations-lead {
                color: #50575e;
                margin-top: 0;
                margin-bottom: 18px;
            }

            .flacso-integrations-field {
                margin-bottom: 16px;
            }

            .flacso-integrations-field label {
                display: block;
                font-weight: 600;
                margin-bottom: 6px;
            }

            .flacso-integrations-field input {
                width: 100%;
                max-width: none;
            }

            .flacso-integrations-help {
                margin: 6px 0 0;
                color: #646970;
            }

            .flacso-integrations-note {
                display: inline-block;
                margin-top: 10px;
                padding: 6px 10px;
                border-radius: 999px;
                background: #eef4ff;
                color: #1d4ed8;
                font-size: 12px;
                font-weight: 600;
            }

            .flacso-integrations-tests,
            .flacso-integrations-links {
                margin-top: 24px;
            }

            .flacso-integrations-test-grid {
                display: grid;
                grid-template-columns: repeat(auto-fit, minmax(240px, 1fr));
                gap: 14px;
                margin-top: 14px;
            }

            .flacso-integrations-test-card {
                border: 1px solid #dcdcde;
                border-radius: 8px;
                padding: 14px;
            }

            .flacso-integrations-test-card h3 {
                margin: 0 0 6px;
                font-size: 15px;
            }

            .flacso-integrations-test-card p {
                min-height: 40px;
                color: #646970;
            }

            .flacso-integrations-links ul {
                margin: 0;
                padding-left: 18px;
            }

            .flacso-integrations-links li {
                margin-bottom: 8px;
            }

            .flacso-test-result-placeholder {
                margin-top: 12px;
                min-height: 20px;
            }

            .flacso-test-loading {
                color: #64748b;
                font-weight: 500;
                font-size: 13px;
                display: flex;
                align-items: center;
                gap: 6px;
            }

            .flacso-test-result {
                padding: 10px 14px;
                border-radius: 8px;
                font-size: 13px;
                line-height: 1.4;
                font-weight: 600;
                box-shadow: 0 1px 2px rgba(16, 24, 40, 0.05);
            }

            .flacso-test-result.success {
                background: #ecfdf5;
                color: #047857;
                border: 1px solid #a7f3d0;
            }

            .flacso-test-result.error {
                background: #fdf2f2;
                color: #b91c1c;
                border: 1px solid #fecdca;
            }

            .flacso-test-result .desc {
                font-weight: 400;
                font-size: 12px;
                color: currentColor;
                opacity: 0.9;
                display: block;
                margin-top: 4px;
            }
        </style>
        <script>
            document.addEventListener('DOMContentLoaded', function() {
                const forms = document.querySelectorAll('.flacso-integrations-test-card');
                forms.forEach(form => {
                    form.addEventListener('submit', function(e) {
                        e.preventDefault();
                        
                        const button = form.querySelector('input[type="submit"]');
                        const placeholder = form.querySelector('.flacso-test-result-placeholder');
                        const originalButtonVal = button.value;
                        
                        // Set loading state
                        button.disabled = true;
                        button.value = '<?php esc_attr_e('Ejecutando...', 'flacso-uruguay'); ?>';
                        placeholder.innerHTML = '<div class="flacso-test-loading">⚡ <?php esc_html_e('Conectando y validando...', 'flacso-uruguay'); ?></div>';
                        
                        const formData = new FormData(form);
                        
                        fetch(form.action, {
                            method: 'POST',
                            body: formData
                        })
                        .then(response => {
                            const finalUrl = new URL(response.url);
                            let status = '';
                            let code = '';
                            let message = '';
                            let tokenVar = '';

                            if (finalUrl.searchParams.has('fc_consultas_webhook_test')) {
                                status = finalUrl.searchParams.get('fc_consultas_webhook_test');
                                code = finalUrl.searchParams.get('fc_consultas_webhook_code') || '';
                                message = finalUrl.searchParams.get('fc_consultas_webhook_message') || '';
                                tokenVar = 'FLACSO_WEBHOOK_TOKEN';
                            } else if (finalUrl.searchParams.has('fc_oferta_webhook_test')) {
                                status = finalUrl.searchParams.get('fc_oferta_webhook_test');
                                code = finalUrl.searchParams.get('fc_oferta_webhook_code') || '';
                                message = finalUrl.searchParams.get('fc_oferta_webhook_message') || '';
                                tokenVar = 'FLACSO_WEBHOOK_TOKEN';
                            } else if (finalUrl.searchParams.has('flacso_charlas_webhook_test')) {
                                status = finalUrl.searchParams.get('flacso_charlas_webhook_test');
                                code = finalUrl.searchParams.get('flacso_charlas_webhook_code') || '';
                                message = finalUrl.searchParams.get('flacso_charlas_webhook_message') || '';
                                tokenVar = 'FLACSO_WEBHOOK_TOKEN';
                            }

                            let noticeText = '';
                            if (status === 'success') {
                                noticeText = '<?php esc_html_e('La app respondió correctamente y aceptó la prueba del webhook.', 'flacso-uruguay'); ?>';
                            } else {
                                const codeNum = parseInt(code, 10);
                                if (codeNum === 401) {
                                    noticeText = '<?php esc_html_e('La app rechazó el token del webhook. Revisá que coincida con', 'flacso-uruguay'); ?> ' + tokenVar + '.';
                                } else if (codeNum === 404) {
                                    noticeText = '<?php esc_html_e('La URL del webhook respondió 404. Revisá que el endpoint exista en la app.', 'flacso-uruguay'); ?>';
                                } else if (codeNum >= 500) {
                                    noticeText = '<?php esc_html_e('La app respondió con un error interno (HTTP', 'flacso-uruguay'); ?> ' + codeNum + ').';
                                } else if (message) {
                                    noticeText = message;
                                } else {
                                    noticeText = '<?php esc_html_e('No se pudo validar la conexión con la app. Revisá la URL configurada y el token.', 'flacso-uruguay'); ?>';
                                }
                            }

                            if (status === 'success') {
                                placeholder.innerHTML = '<div class="flacso-test-result success">✅ <?php esc_html_e('Conexión Exitosa!', 'flacso-uruguay'); ?><br><span class="desc">' + noticeText + '</span></div>';
                            } else {
                                placeholder.innerHTML = '<div class="flacso-test-result error">❌ <?php esc_html_e('Fallo de Conexión', 'flacso-uruguay'); ?> ' + (code ? '(HTTP ' + code + ')' : '') + '<br><span class="desc">' + noticeText + '</span></div>';
                            }
                        })
                        .catch(err => {
                            console.error(err);
                            placeholder.innerHTML = '<div class="flacso-test-result error">❌ <?php esc_html_e('Error de red', 'flacso-uruguay'); ?><br><span class="desc"><?php esc_html_e('No se pudo establecer comunicación con el servidor local o la app.', 'flacso-uruguay'); ?></span></div>';
                        })
                        .finally(() => {
                            button.disabled = false;
                            button.value = originalButtonVal;
                        });
                    });
                });
            });
        </script>
        <?php
    }

    private static function render_consultas_card(): void {
        ?>
        <section class="flacso-integrations-card">
            <h2><?php esc_html_e('Consultas en la app', 'flacso-uruguay'); ?></h2>
            <p class="flacso-integrations-lead"><?php esc_html_e('Agrupa el formulario general y el bloque de solicitud de información. Ambos comparten el mismo token en FLACSO Editor.', 'flacso-uruguay'); ?></p>

            <?php
            self::render_input_field(
                self::OPTION_CONSULTAS_WEBHOOK_URL,
                __('Formulario de consulta general', 'flacso-uruguay'),
                'url',
                'https://tu-dominio.com/api/consultas/general',
                __('Sugerido para el formulario de consulta general del módulo Formularios.', 'flacso-uruguay')
            );
            self::render_input_field(
                self::OPTION_INFO_REQUEST_WEBHOOK_URL,
                __('Solicitud de información', 'flacso-uruguay'),
                'url',
                'https://tu-dominio.com/api/consultas',
                __('Usado por el bloque “Solicitud de Información” de oferta académica.', 'flacso-uruguay')
            );
            self::render_input_field(
                self::OPTION_UNIFIED_WEBHOOK_TOKEN,
                __('Token unificado', 'flacso-uruguay'),
                'password',
                __('Mismo valor que FLACSO_WEBHOOK_TOKEN', 'flacso-uruguay'),
                __('El token unificado que se enviará en las cabeceras de todas las peticiones webhook.', 'flacso-uruguay')
            );
            ?>

            <span class="flacso-integrations-note"><?php esc_html_e('Variable esperada en la app: FLACSO_WEBHOOK_TOKEN', 'flacso-uruguay'); ?></span>
        </section>
        <?php
    }

    private static function render_charlas_card(): void {
        ?>
        <section class="flacso-integrations-card">
            <h2><?php esc_html_e('Charlas abiertas', 'flacso-uruguay'); ?></h2>
            <p class="flacso-integrations-lead"><?php esc_html_e('Configura el webhook que recibe inscripciones y el token asociado para la app.', 'flacso-uruguay'); ?></p>

            <?php
            self::render_input_field(
                self::OPTION_CHARLAS_WEBHOOK_URL,
                __('Webhook URL', 'flacso-uruguay'),
                'url',
                'https://tu-dominio.com/api/charlas/inscripciones',
                __('Ruta sugerida si el destino es FLACSO Editor.', 'flacso-uruguay')
            );
            ?>
            <div class="flacso-integrations-field">
                <label><?php esc_html_e('Token del webhook', 'flacso-uruguay'); ?></label>
                <input
                    type="text"
                    class="regular-text code"
                    value="<?php esc_attr_e('Compartido (Configurado en consultas)', 'flacso-uruguay'); ?>"
                    disabled
                    readonly
                />
                <p class="flacso-integrations-help"><?php esc_html_e('Usa el mismo token de integración configurado a la izquierda.', 'flacso-uruguay'); ?></p>
            </div>

            <span class="flacso-integrations-note"><?php esc_html_e('Variable esperada en la app: FLACSO_WEBHOOK_TOKEN', 'flacso-uruguay'); ?></span>
        </section>
        <?php
    }

    private static function render_oferta_flotante_card(): void {
        ?>
        <section class="flacso-integrations-card">
            <h2><?php esc_html_e('Formulario de consulta de oferta académica', 'flacso-uruguay'); ?></h2>
            <p class="flacso-integrations-lead"><?php esc_html_e('Este es el endpoint del botón flotante “Solicitar información”. No usa el mismo contrato que el bloque de solicitud de información.', 'flacso-uruguay'); ?></p>

            <?php
            self::render_input_field(
                self::OPTION_OFERTA_FLOTANTE_ENDPOINT,
                __('Endpoint del formulario flotante', 'flacso-uruguay'),
                'url',
                'https://ejemplo.com/webhook/consultas',
                __('Hoy este flujo solo usa URL; no envía token propio.', 'flacso-uruguay')
            );
            ?>

            <span class="flacso-integrations-note"><?php esc_html_e('Flujo distinto al bloque “Solicitud de Información”', 'flacso-uruguay'); ?></span>
        </section>
        <?php
    }

    private static function render_preinscripciones_card(): void {
        ?>
        <section class="flacso-integrations-card">
            <h2><?php esc_html_e('Preinscripciones', 'flacso-uruguay'); ?></h2>
            <p class="flacso-integrations-lead"><?php esc_html_e('Define el webhook externo que recibe los datos de preinscripción. Suele apuntar a Google Apps Script.', 'flacso-uruguay'); ?></p>

            <?php
            self::render_input_field(
                self::OPTION_PREINSCRIPCIONES_WEBHOOK_URL,
                __('Webhook de preinscripciones', 'flacso-uruguay'),
                'url',
                'https://script.google.com/macros/s/.../exec',
                __('Se guarda sobre la misma opción usada por el panel de Preinscripciones.', 'flacso-uruguay')
            );
            ?>
        </section>
        <?php
    }

    private static function render_services_card(): void {
        ?>
        <section class="flacso-integrations-card">
            <h2><?php esc_html_e('Servicios auxiliares', 'flacso-uruguay'); ?></h2>
            <p class="flacso-integrations-lead"><?php esc_html_e('Claves asociadas a integraciones complementarias del formulario general.', 'flacso-uruguay'); ?></p>

            <?php
            self::render_input_field(
                self::OPTION_TELEGRAM_BOT_TOKEN,
                __('Telegram Bot Token', 'flacso-uruguay'),
                'password',
                '123456789:ABC-DEF1234ghIkl-zyx57W2v1u123ew11',
                __('Se usa si están activadas las notificaciones por Telegram en el formulario general.', 'flacso-uruguay')
            );
            self::render_input_field(
                self::OPTION_TELEGRAM_CHAT_ID,
                __('Telegram Chat ID', 'flacso-uruguay'),
                'text',
                '7456441753 o -1001234567890',
                __('Chat o canal de destino para las notificaciones.', 'flacso-uruguay')
            );
            self::render_input_field(
                self::OPTION_RECAPTCHA_SITE_KEY,
                __('reCAPTCHA Site Key', 'flacso-uruguay'),
                'text',
                '6LeIxAcTAAAAA...',
                __('Clave pública usada por el formulario general.', 'flacso-uruguay')
            );
            self::render_input_field(
                self::OPTION_RECAPTCHA_SECRET_KEY,
                __('reCAPTCHA Secret Key', 'flacso-uruguay'),
                'password',
                '6LeIxAcTAAAAA...',
                __('Clave privada usada para validar reCAPTCHA en servidor.', 'flacso-uruguay')
            );
            ?>
        </section>
        <?php
    }

    private static function render_input_field(string $option_name, string $label, string $type, string $placeholder, string $description): void {
        $value = get_option($option_name, '');
        ?>
        <div class="flacso-integrations-field">
            <label for="<?php echo esc_attr($option_name); ?>"><?php echo esc_html($label); ?></label>
            <input
                id="<?php echo esc_attr($option_name); ?>"
                name="<?php echo esc_attr($option_name); ?>"
                type="<?php echo esc_attr($type); ?>"
                class="regular-text code"
                value="<?php echo esc_attr(is_string($value) ? $value : ''); ?>"
                placeholder="<?php echo esc_attr($placeholder); ?>"
                autocomplete="<?php echo 'password' === $type ? 'new-password' : 'off'; ?>"
                spellcheck="false"
            />
            <p class="flacso-integrations-help"><?php echo esc_html($description); ?></p>
        </div>
        <?php
    }

    private static function render_test_form(string $action, string $nonce_name, string $nonce_action, string $title, string $description): void {
        ?>
        <form class="flacso-integrations-test-card" method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
            <h3><?php echo esc_html($title); ?></h3>
            <p><?php echo esc_html($description); ?></p>
            <?php wp_nonce_field($nonce_action, $nonce_name); ?>
            <input type="hidden" name="action" value="<?php echo esc_attr($action); ?>" />
            <input type="hidden" name="redirect_to" value="<?php echo esc_attr(self::get_page_url()); ?>" />
            <?php submit_button(__('Ejecutar prueba', 'flacso-uruguay'), 'secondary', 'submit', false); ?>
            <div class="flacso-test-result-placeholder"></div>
        </form>
        <?php
    }

    private static function render_inline_notices(): void {
        if (!function_exists('fc_render_webhook_test_notice')) {
            return;
        }

        if (isset($_GET['fc_consultas_webhook_test'])) {
            $status = sanitize_key(wp_unslash($_GET['fc_consultas_webhook_test']));
            $code = isset($_GET['fc_consultas_webhook_code']) ? absint(wp_unslash($_GET['fc_consultas_webhook_code'])) : 0;
            $message = isset($_GET['fc_consultas_webhook_message']) ? sanitize_text_field(wp_unslash($_GET['fc_consultas_webhook_message'])) : '';
            fc_render_webhook_test_notice($status, $code, $message, 'FLACSO_WEBHOOK_TOKEN');
        }

        if (isset($_GET['fc_oferta_webhook_test'])) {
            $status = sanitize_key(wp_unslash($_GET['fc_oferta_webhook_test']));
            $code = isset($_GET['fc_oferta_webhook_code']) ? absint(wp_unslash($_GET['fc_oferta_webhook_code'])) : 0;
            $message = isset($_GET['fc_oferta_webhook_message']) ? sanitize_text_field(wp_unslash($_GET['fc_oferta_webhook_message'])) : '';
            fc_render_webhook_test_notice($status, $code, $message, 'FLACSO_WEBHOOK_TOKEN');
        }

        if (!isset($_GET['flacso_charlas_webhook_test'])) {
            return;
        }

        $status = sanitize_key(wp_unslash($_GET['flacso_charlas_webhook_test']));
        $code = isset($_GET['flacso_charlas_webhook_code']) ? absint(wp_unslash($_GET['flacso_charlas_webhook_code'])) : 0;
        $message = isset($_GET['flacso_charlas_webhook_message']) ? sanitize_text_field(wp_unslash($_GET['flacso_charlas_webhook_message'])) : '';

        fc_render_webhook_test_notice($status, $code, $message, 'FLACSO_WEBHOOK_TOKEN');
    }
}

FLACSO_Integrations_Settings::init();
