<?php

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Formulario flotante de consultas para la página de Oferta Académica.
 */
class Oferta_Consulta_Form {
    private const OPTION_ENDPOINT_URL = 'flacso_oferta_consulta_endpoint_url';
    private const SETTINGS_GROUP = 'flacso_oferta_consulta_settings';
    private const MENU_SLUG = 'flacso-oferta-consulta-form';

    public static function init(): void {
        if (is_admin()) {
            add_action('admin_menu', [self::class, 'add_admin_menu'], 12);
            add_action('admin_init', [self::class, 'register_settings']);
        }

        add_action('wp_ajax_flacso_oferta_consulta_submit', [self::class, 'handle_ajax_submit']);
        add_action('wp_ajax_nopriv_flacso_oferta_consulta_submit', [self::class, 'handle_ajax_submit']);
    }

    public static function add_admin_menu(): void {
        add_submenu_page(
            'edit.php?post_type=oferta-academica',
            __('Formulario de Consulta', 'flacso-oferta-academica'),
            __('Formulario de Consulta', 'flacso-oferta-academica'),
            'manage_options',
            self::MENU_SLUG,
            [self::class, 'render_settings_page']
        );
    }

    public static function register_settings(): void {
        register_setting(
            self::SETTINGS_GROUP,
            self::OPTION_ENDPOINT_URL,
            [
                'type' => 'string',
                'sanitize_callback' => [self::class, 'sanitize_endpoint_url'],
                'default' => '',
            ]
        );
    }

    public static function sanitize_endpoint_url($value): string {
        $value = trim((string) $value);

        if ($value === '') {
            return '';
        }

        $sanitized = esc_url_raw($value);
        $scheme = wp_parse_url($sanitized, PHP_URL_SCHEME);

        if (!$sanitized || !wp_http_validate_url($sanitized) || !in_array($scheme, ['http', 'https'], true)) {
            add_settings_error(
                self::SETTINGS_GROUP,
                'flacso_oferta_consulta_endpoint_invalid',
                __('La URL del endpoint no es válida. Debe comenzar con http:// o https://', 'flacso-oferta-academica'),
                'error'
            );

            return self::get_endpoint_url();
        }

        return $sanitized;
    }

    public static function get_endpoint_url(): string {
        return trim((string) get_option(self::OPTION_ENDPOINT_URL, ''));
    }

    /**
     * @return array<int,array{id:int,title:string}>
     */
    private static function get_oferta_options(): array {
        $ids = get_posts([
            'post_type' => 'oferta-academica',
            'post_status' => ['publish'],
            'posts_per_page' => -1,
            'orderby' => 'title',
            'order' => 'ASC',
            'fields' => 'ids',
        ]);

        $options = [];
        foreach ($ids as $post_id) {
            $title = trim((string) get_the_title((int) $post_id));
            if ($title === '') {
                continue;
            }

            $options[] = [
                'id' => (int) $post_id,
                'title' => $title,
            ];
        }

        return $options;
    }

    public static function render_settings_page(): void {
        if (!current_user_can('manage_options')) {
            return;
        }

        $endpoint_url = self::get_endpoint_url();
        ?>
        <div class="wrap">
            <h1><?php esc_html_e('Formulario de Consulta de Oferta Académica', 'flacso-oferta-academica'); ?></h1>
            <p><?php esc_html_e('Configura la URL que recibirá por POST los datos enviados desde el botón flotante de consulta.', 'flacso-oferta-academica'); ?></p>

            <?php settings_errors(self::SETTINGS_GROUP); ?>

            <form method="post" action="options.php">
                <?php settings_fields(self::SETTINGS_GROUP); ?>
                <table class="form-table" role="presentation">
                    <tr>
                        <th scope="row">
                            <label for="flacso_oferta_consulta_endpoint_url"><?php esc_html_e('URL del endpoint', 'flacso-oferta-academica'); ?></label>
                        </th>
                        <td>
                            <input
                                id="flacso_oferta_consulta_endpoint_url"
                                name="<?php echo esc_attr(self::OPTION_ENDPOINT_URL); ?>"
                                type="url"
                                class="regular-text code"
                                placeholder="https://ejemplo.com/webhook/consultas"
                                value="<?php echo esc_attr($endpoint_url); ?>"
                            />
                            <p class="description">
                                <?php esc_html_e('La información se enviará por método POST con contenido x-www-form-urlencoded.', 'flacso-oferta-academica'); ?>
                            </p>
                        </td>
                    </tr>
                </table>
                <?php submit_button(__('Guardar configuración', 'flacso-oferta-academica')); ?>
            </form>
        </div>
        <?php
    }

    public static function render_floating_form(): string {
        $options = self::get_oferta_options();

        if (empty($options)) {
            return '';
        }

        $endpoint_configured = self::get_endpoint_url() !== '';
        if (!$endpoint_configured && !current_user_can('manage_options')) {
            return '';
        }

        $dialog_id = function_exists('wp_unique_id')
            ? wp_unique_id('flacso-oa-consulta-')
            : ('flacso-oa-consulta-' . wp_rand(1000, 9999));

        ob_start();
        ?>
        <div
            class="flacso-oa-consulta"
            data-flacso-oa-consulta
            data-ajax-url="<?php echo esc_url(admin_url('admin-ajax.php', 'relative')); ?>"
            data-nonce="<?php echo esc_attr(wp_create_nonce('flacso_oferta_consulta_submit')); ?>"
            data-endpoint-configured="<?php echo $endpoint_configured ? '1' : '0'; ?>"
        >
            <button type="button" class="flacso-oa-consulta__fab" data-oa-consulta-open>
                <span class="dashicons dashicons-email-alt" aria-hidden="true"></span>
                <span><?php esc_html_e('Solicitar información', 'flacso-oferta-academica'); ?></span>
            </button>

            <div class="flacso-oa-consulta__overlay" data-oa-consulta-overlay hidden>
                <section class="flacso-oa-consulta__panel" role="dialog" aria-modal="true" aria-labelledby="<?php echo esc_attr($dialog_id); ?>-title">
                    <button type="button" class="flacso-oa-consulta__close" data-oa-consulta-close aria-label="<?php esc_attr_e('Cerrar formulario', 'flacso-oferta-academica'); ?>">
                        <span aria-hidden="true">&times;</span>
                    </button>

                    <div class="flacso-oa-consulta__content">
                        <p class="flacso-oa-consulta__eyebrow"><?php esc_html_e('Bienvenide', 'flacso-oferta-academica'); ?></p>
                        <h2 id="<?php echo esc_attr($dialog_id); ?>-title" class="flacso-oa-consulta__title"><?php esc_html_e('Recibimos tu consulta', 'flacso-oferta-academica'); ?></h2>
                        <p class="flacso-oa-consulta__subtitle">
                            <?php esc_html_e('Contanos de cuál oferta académica querés información y qué necesitás saber.', 'flacso-oferta-academica'); ?>
                        </p>

                        <form class="flacso-oa-consulta__form" data-oa-consulta-form novalidate>
                            <div class="flacso-oa-consulta__grid">
                                <div class="flacso-oa-consulta__field">
                                    <label for="<?php echo esc_attr($dialog_id); ?>-nombre"><?php esc_html_e('Nombre', 'flacso-oferta-academica'); ?></label>
                                    <input id="<?php echo esc_attr($dialog_id); ?>-nombre" name="nombre" type="text" required autocomplete="given-name" />
                                </div>
                                <div class="flacso-oa-consulta__field">
                                    <label for="<?php echo esc_attr($dialog_id); ?>-apellido"><?php esc_html_e('Apellido', 'flacso-oferta-academica'); ?></label>
                                    <input id="<?php echo esc_attr($dialog_id); ?>-apellido" name="apellido" type="text" required autocomplete="family-name" />
                                </div>
                            </div>

                            <div class="flacso-oa-consulta__field">
                                <label for="<?php echo esc_attr($dialog_id); ?>-correo"><?php esc_html_e('Correo', 'flacso-oferta-academica'); ?></label>
                                <input id="<?php echo esc_attr($dialog_id); ?>-correo" name="correo" type="email" required autocomplete="email" />
                            </div>

                            <div class="flacso-oa-consulta__field">
                                <label for="<?php echo esc_attr($dialog_id); ?>-oferta"><?php esc_html_e('¿De cuál oferta académica querés información?', 'flacso-oferta-academica'); ?></label>
                                <select id="<?php echo esc_attr($dialog_id); ?>-oferta" name="oferta_id" required>
                                    <option value=""><?php esc_html_e('Seleccioná una opción', 'flacso-oferta-academica'); ?></option>
                                    <?php foreach ($options as $option) : ?>
                                        <option value="<?php echo esc_attr((string) $option['id']); ?>">
                                            <?php echo esc_html($option['title']); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>

                            <div class="flacso-oa-consulta__field">
                                <label for="<?php echo esc_attr($dialog_id); ?>-consulta"><?php esc_html_e('Comentarios / información que necesitás', 'flacso-oferta-academica'); ?></label>
                                <textarea id="<?php echo esc_attr($dialog_id); ?>-consulta" name="consulta" rows="5" required></textarea>
                            </div>

                            <div class="flacso-oa-consulta__actions">
                                <button type="submit" class="flacso-oa-consulta__submit" data-oa-consulta-submit>
                                    <?php esc_html_e('Enviar consulta', 'flacso-oferta-academica'); ?>
                                </button>
                            </div>

                            <p class="flacso-oa-consulta__status" data-oa-consulta-status aria-live="polite"></p>
                            <?php if (!$endpoint_configured && current_user_can('manage_options')) : ?>
                                <p class="flacso-oa-consulta__status is-error">
                                    <?php esc_html_e('El endpoint no está configurado todavía. Podés configurarlo en Oferta Académica > Formulario de Consulta.', 'flacso-oferta-academica'); ?>
                                </p>
                            <?php endif; ?>
                        </form>
                    </div>
                </section>
            </div>
        </div>
        <?php

        return (string) ob_get_clean();
    }

    public static function handle_ajax_submit(): void {
        if (!check_ajax_referer('flacso_oferta_consulta_submit', 'nonce', false)) {
            wp_send_json_error(
                ['message' => __('No se pudo validar la solicitud. Recargá la página e intentá de nuevo.', 'flacso-oferta-academica')],
                403
            );
        }

        $endpoint = self::get_endpoint_url();
        if ($endpoint === '') {
            wp_send_json_error(
                ['message' => __('El formulario no está disponible en este momento.', 'flacso-oferta-academica')],
                503
            );
        }

        $nombre = sanitize_text_field(wp_unslash($_POST['nombre'] ?? ''));
        $apellido = sanitize_text_field(wp_unslash($_POST['apellido'] ?? ''));
        $correo = sanitize_email(wp_unslash($_POST['correo'] ?? ''));
        $oferta_id = absint(wp_unslash($_POST['oferta_id'] ?? '0'));
        $consulta = sanitize_textarea_field(wp_unslash($_POST['consulta'] ?? ''));

        if ($nombre === '' || $apellido === '' || $consulta === '' || $oferta_id <= 0) {
            wp_send_json_error(
                ['message' => __('Completá todos los campos obligatorios.', 'flacso-oferta-academica')],
                400
            );
        }

        if (!is_email($correo)) {
            wp_send_json_error(
                ['message' => __('Ingresá un correo válido.', 'flacso-oferta-academica')],
                400
            );
        }

        $oferta = get_post($oferta_id);
        if (
            !($oferta instanceof WP_Post)
            || $oferta->post_type !== 'oferta-academica'
            || ($oferta->post_status !== 'publish' && !current_user_can('edit_post', $oferta_id))
        ) {
            wp_send_json_error(
                ['message' => __('La oferta académica seleccionada no es válida.', 'flacso-oferta-academica')],
                400
            );
        }

        $origin = isset($_SERVER['HTTP_REFERER']) ? esc_url_raw(wp_unslash($_SERVER['HTTP_REFERER'])) : '';
        $user_agent = isset($_SERVER['HTTP_USER_AGENT']) ? sanitize_text_field(wp_unslash($_SERVER['HTTP_USER_AGENT'])) : '';

        $payload = [
            'nombre' => $nombre,
            'apellido' => $apellido,
            'correo' => $correo,
            'oferta_id' => (string) $oferta_id,
            'oferta_titulo' => get_the_title($oferta_id),
            'consulta' => $consulta,
            'url_origen' => $origin,
            'sitio' => home_url('/'),
            'fecha_utc' => gmdate('c'),
            'user_agent' => $user_agent,
        ];

        $response = wp_safe_remote_post($endpoint, [
            'timeout' => 20,
            'redirection' => 3,
            'body' => $payload,
        ]);

        if (is_wp_error($response)) {
            wp_send_json_error(
                ['message' => __('No se pudo enviar la consulta. Intentá nuevamente en unos minutos.', 'flacso-oferta-academica')],
                502
            );
        }

        $status_code = (int) wp_remote_retrieve_response_code($response);
        if ($status_code < 200 || $status_code >= 300) {
            wp_send_json_error(
                ['message' => sprintf(__('No se pudo enviar la consulta (código %d).', 'flacso-oferta-academica'), $status_code)],
                502
            );
        }

        wp_send_json_success([
            'message' => __('Gracias. Recibimos tu consulta y te contactaremos a la brevedad.', 'flacso-oferta-academica'),
        ]);
    }
}
