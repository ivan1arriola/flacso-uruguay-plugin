<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

function fc_register_settings() {
    register_setting( 'fc_options_group', 'fc_destinatario_email', [ 'type' => 'string', 'sanitize_callback' => 'sanitize_email', 'default' => get_option( 'admin_email' ) ] );
    register_setting( 'fc_options_group', 'fc_asunto_admin', [ 'type' => 'string', 'sanitize_callback' => 'sanitize_text_field', 'default' => '' ] );
    register_setting( 'fc_options_group', 'fc_asunto_usuario', [ 'type' => 'string', 'sanitize_callback' => 'sanitize_text_field', 'default' => '' ] );
    register_setting( 'fc_options_group', 'fc_cargar_bootstrap', [ 'type' => 'string', 'sanitize_callback' => function( $v ){ return $v === '1' ? '1' : '0'; }, 'default' => '1' ] );
    register_setting( 'fc_options_group', 'fc_use_gmail_api', [ 'type' => 'string', 'sanitize_callback' => function( $v ){ return $v === '1' ? '1' : '0'; }, 'default' => '0' ] );
    register_setting( 'fc_options_group', 'fc_google_service_account_json', [ 'type' => 'string', 'sanitize_callback' => 'wp_kses_post', 'default' => '' ] );
    register_setting( 'fc_options_group', 'fc_google_impersonated', [ 'type' => 'string', 'sanitize_callback' => 'sanitize_email', 'default' => 'noreply@flacso.edu.uy' ] );
    register_setting( 'fc_options_group', 'fc_fallback_senders', [ 'type' => 'string', 'sanitize_callback' => function( $v ){ return sanitize_text_field( $v ); }, 'default' => '' ] );
    register_setting( 'fc_options_group', 'fc_use_telegram', [ 'type' => 'string', 'sanitize_callback' => function( $v ){ return $v === '1' ? '1' : '0'; }, 'default' => '0' ] );
    register_setting( 'fc_options_group', 'fc_telegram_bot_token', [ 'type' => 'string', 'sanitize_callback' => 'sanitize_text_field', 'default' => '' ] );
    register_setting( 'fc_options_group', 'fc_telegram_chat_id', [ 'type' => 'string', 'sanitize_callback' => 'sanitize_text_field', 'default' => '' ] );
    register_setting( 'fc_options_group', 'fc_use_recaptcha', [ 'type' => 'string', 'sanitize_callback' => function( $v ){ return $v === '1' ? '1' : '0'; }, 'default' => '0' ] );
    register_setting( 'fc_options_group', 'fc_recaptcha_site_key', [ 'type' => 'string', 'sanitize_callback' => 'sanitize_text_field', 'default' => '' ] );
    register_setting( 'fc_options_group', 'fc_recaptcha_secret_key', [ 'type' => 'string', 'sanitize_callback' => 'sanitize_text_field', 'default' => '' ] );
    register_setting( 'fc_options_group', 'fc_consultas_webhook_url', [ 'type' => 'string', 'sanitize_callback' => 'esc_url_raw', 'default' => '' ] );

    add_settings_section( 'fc_main_section', __( 'Ajustes de formulario de consultas', 'flacso-flacso-formulario-consultas' ), function(){
        echo '<p>' . esc_html__( 'Configura el destinatario y los asuntos de los correos. Inserta el formulario con el bloque “Formulario de Consulta”.', 'flacso-flacso-formulario-consultas' ) . '</p>';
    }, 'fc_options_page' );

    add_settings_field( 'fc_destinatario_email', __( 'Email destinatario', 'flacso-flacso-formulario-consultas' ), 'fc_field_email_cb', 'fc_options_page', 'fc_main_section' );
    add_settings_field( 'fc_asunto_admin', __( 'Asunto para administrador', 'flacso-flacso-formulario-consultas' ), 'fc_field_asunto_admin_cb', 'fc_options_page', 'fc_main_section' );
    add_settings_field( 'fc_asunto_usuario', __( 'Asunto para usuario', 'flacso-flacso-formulario-consultas' ), 'fc_field_asunto_usuario_cb', 'fc_options_page', 'fc_main_section' );
    add_settings_field( 'fc_consultas_webhook_url', __( 'Webhook de consultas', 'flacso-flacso-formulario-consultas' ), 'fc_field_consultas_webhook_url_cb', 'fc_options_page', 'fc_main_section' );
    add_settings_field( 'fc_cargar_bootstrap', __( 'Cargar Bootstrap automáticamente', 'flacso-flacso-formulario-consultas' ), 'fc_field_bootstrap_cb', 'fc_options_page', 'fc_main_section' );
    // Opción para Gmail API
    add_settings_field( 'fc_use_gmail_api', __( 'Usar Gmail API (Google Workspace)', 'flacso-flacso-formulario-consultas' ), 'fc_field_use_gmail_api_cb', 'fc_options_page', 'fc_main_section' );
    // Solo mostrar ajustes correspondientes si la opción está habilitada
    if ( get_option( 'fc_use_gmail_api', '0' ) === '1' ) {
        add_settings_field( 'fc_google_impersonated', __( 'Remitente (impersonado)', 'flacso-flacso-formulario-consultas' ), 'fc_field_google_impersonated_cb', 'fc_options_page', 'fc_main_section' );
        add_settings_field( 'fc_google_service_account_json', __( 'Clave de cuenta de servicio (JSON)', 'flacso-flacso-formulario-consultas' ), 'fc_field_google_json_cb', 'fc_options_page', 'fc_main_section' );
    }
    // Remitentes de fallback útiles tanto con como sin Gmail API
    add_settings_field( 'fc_fallback_senders', __( 'Remitentes de fallback (lista)', 'flacso-flacso-formulario-consultas' ), 'fc_field_fallback_senders_cb', 'fc_options_page', 'fc_main_section' );

    // Opción para Telegram
    add_settings_field( 'fc_use_telegram', __( 'Activar notificaciones Telegram', 'flacso-flacso-formulario-consultas' ), 'fc_field_use_telegram_cb', 'fc_options_page', 'fc_main_section' );
    if ( get_option( 'fc_use_telegram', '0' ) === '1' ) {
        add_settings_field( 'fc_telegram_bot_token', __( 'Telegram Bot Token', 'flacso-flacso-formulario-consultas' ), 'fc_field_telegram_bot_token_cb', 'fc_options_page', 'fc_main_section' );
        add_settings_field( 'fc_telegram_chat_id', __( 'Telegram Chat ID', 'flacso-flacso-formulario-consultas' ), 'fc_field_telegram_chat_id_cb', 'fc_options_page', 'fc_main_section' );
    }

    // Opción para reCAPTCHA v3
    add_settings_field( 'fc_use_recaptcha', __( 'Activar Google reCAPTCHA v3', 'flacso-flacso-formulario-consultas' ), 'fc_field_use_recaptcha_cb', 'fc_options_page', 'fc_main_section' );
    if ( get_option( 'fc_use_recaptcha', '0' ) === '1' ) {
        add_settings_field( 'fc_recaptcha_site_key', __( 'reCAPTCHA Site Key', 'flacso-flacso-formulario-consultas' ), 'fc_field_recaptcha_site_key_cb', 'fc_options_page', 'fc_main_section' );
        add_settings_field( 'fc_recaptcha_secret_key', __( 'reCAPTCHA Secret Key', 'flacso-flacso-formulario-consultas' ), 'fc_field_recaptcha_secret_key_cb', 'fc_options_page', 'fc_main_section' );
    }
}
add_action( 'admin_init', 'fc_register_settings' );

/**
 * Ajustes específicos para el formulario “Solicitud de Información” (oferta académica).
 * Solo requiere la URL del webhook al que se envían los datos vía AJAX.
 */
function fc_register_settings_oferta() {
    register_setting(
        'fc_oferta_options_group',
        'fc_oferta_webhook_url',
        [
            'type'              => 'string',
            'sanitize_callback' => 'esc_url_raw',
            'default'           => '',
        ]
    );

    add_settings_section(
        'fc_oferta_main_section',
        __( 'Webhook de Solicitud de Información', 'flacso-flacso-formulario-consultas' ),
        function () {
            echo '<p>' . esc_html__( 'Define la URL del webhook que recibe las consultas de oferta académica (bloque “Solicitud de Información”).', 'flacso-flacso-formulario-consultas' ) . '</p>';
        },
        'fc_oferta_options_page'
    );

    add_settings_field(
        'fc_oferta_webhook_url',
        __( 'URL del Webhook', 'flacso-flacso-formulario-consultas' ),
        function () {
            $value = esc_url( get_option( 'fc_oferta_webhook_url', '' ) );
            echo '<input type="url" class="regular-text code" name="fc_oferta_webhook_url" value="' . $value . '" placeholder="https://tu-dominio/api/inquiries" />';
            echo '<p class="description">' . esc_html__( 'Se usa para el envio AJAX del formulario de Solicitud de Informacion. Este endpoint se configura desde este menu.', 'flacso-flacso-formulario-consultas' ) . '</p>';
        },
        'fc_oferta_options_page',
        'fc_oferta_main_section'
    );
}
add_action( 'admin_init', 'fc_register_settings_oferta' );

function fc_field_email_cb() {
    $value = esc_attr( get_option( 'fc_destinatario_email', get_option( 'admin_email' ) ) );
    echo '<input type="email" class="regular-text" name="fc_destinatario_email" value="' . $value . '" />';
}

function fc_field_asunto_admin_cb() {
    $value = esc_attr( get_option( 'fc_asunto_admin', '' ) );
    echo '<input type="text" class="regular-text" name="fc_asunto_admin" value="' . $value . '" placeholder="' . esc_attr__( 'Nueva consulta desde {sitio}', 'flacso-flacso-formulario-consultas' ) . '" />';
    echo '<p class="description">' . esc_html__( 'Déjalo vacío para usar el asunto por defecto.', 'flacso-flacso-formulario-consultas' ) . '</p>';
}

function fc_field_asunto_usuario_cb() {
    $value = esc_attr( get_option( 'fc_asunto_usuario', '' ) );
    echo '<input type="text" class="regular-text" name="fc_asunto_usuario" value="' . $value . '" placeholder="' . esc_attr__( 'Hemos recibido tu consulta', 'flacso-flacso-formulario-consultas' ) . '" />';
    echo '<p class="description">' . esc_html__( 'Déjalo vacío para usar el asunto por defecto.', 'flacso-flacso-formulario-consultas' ) . '</p>';
}

function fc_field_consultas_webhook_url_cb() {
    $value = esc_url( get_option( 'fc_consultas_webhook_url', '' ) );
    echo '<input type="url" class="regular-text code" name="fc_consultas_webhook_url" value="' . $value . '" placeholder="https://tu-dominio/api/inquiries" />';
    echo '<p class="description">' . esc_html__( 'URL para enviar por POST JSON cada consulta recibida desde este formulario. Se puede editar desde este menu.', 'flacso-flacso-formulario-consultas' ) . '</p>';
}

function fc_field_bootstrap_cb() {
    $value = get_option( 'fc_cargar_bootstrap', '1' );
    echo '<label><input type="checkbox" name="fc_cargar_bootstrap" value="1" ' . checked( '1', $value, false ) . ' /> ' . esc_html__( 'Cargar CSS/JS de Bootstrap 5 al mostrar el formulario', 'flacso-flacso-formulario-consultas' ) . '</label>';
}

function fc_field_use_gmail_api_cb() {
    $value = get_option( 'fc_use_gmail_api', '0' );
    echo '<label><input type="checkbox" name="fc_use_gmail_api" value="1" ' . checked( '1', $value, false ) . ' /> ' . esc_html__( 'Enviar correos mediante Gmail API con cuenta de servicio', 'flacso-flacso-formulario-consultas' ) . '</label>';
    echo '<p class="description">' . esc_html__( 'Requiere dominio Google Workspace con delegación a nivel de dominio y el alcance gmail.send', 'flacso-flacso-formulario-consultas' ) . '</p>';
}

function fc_field_google_impersonated_cb() {
    $value = esc_attr( get_option( 'fc_google_impersonated', 'noreply@flacso.edu.uy' ) );
    echo '<input type="email" class="regular-text" name="fc_google_impersonated" value="' . $value . '" />';
    echo '<p class="description">' . esc_html__( 'Cuenta del dominio desde la que se envía (p.ej., noreply@flacso.edu.uy).', 'flacso-flacso-formulario-consultas' ) . '</p>';
}

function fc_field_google_json_cb() {
    $value = get_option( 'fc_google_service_account_json', '' );
    $masked = '';
    if ( $value ) {
        // Evitar mostrar la clave privada completa
        $masked = preg_replace( '/"private_key"\s*:\s*"[\s\S]*?"/m', '"private_key":"*** oculto ***"', $value );
    }
    echo '<textarea name="fc_google_service_account_json" rows="8" class="large-text code" placeholder="{\n  \"type\": \"service_account\",...\n}">';
    echo esc_textarea( $masked ? $masked : '' );
    echo '</textarea>';
    echo '<p class="description">' . esc_html__( 'Pega aquí el JSON de la cuenta de servicio con delegación a nivel de dominio habilitada.', 'flacso-flacso-formulario-consultas' ) . '</p>';
}

function fc_field_fallback_senders_cb() {
    $value = esc_attr( get_option( 'fc_fallback_senders', '' ) );
    echo '<input type="text" class="regular-text" name="fc_fallback_senders" value="' . $value . '" placeholder="noreply@flacso.edu.uy, wordpress@flacso.edu.uy, web@flacso.edu.uy" />';
    echo '<p class="description">' . esc_html__( 'Lista de correos separados por coma usados si Gmail API no está disponible o falla el envío. Se intentan en orden.', 'flacso-flacso-formulario-consultas' ) . '</p>';
}

function fc_field_use_telegram_cb() {
    $value = get_option( 'fc_use_telegram', '0' );
    echo '<label><input type="checkbox" name="fc_use_telegram" value="1" ' . checked( '1', $value, false ) . ' /> ' . esc_html__( 'Enviar notificaciones a Telegram al recibir una consulta', 'flacso-flacso-formulario-consultas' ) . '</label>';
}

function fc_field_telegram_bot_token_cb() {
    $value = esc_attr( get_option( 'fc_telegram_bot_token', '' ) );
    echo '<input type="text" class="regular-text" name="fc_telegram_bot_token" value="' . $value . '" placeholder="123456789:ABC-DEF1234ghIkl-zyx57W2v1u123ew11" />';
}

function fc_field_telegram_chat_id_cb() {
    $value = esc_attr( get_option( 'fc_telegram_chat_id', '' ) );
    echo '<input type="text" class="regular-text" name="fc_telegram_chat_id" value="' . $value . '" placeholder="7456441753 o -1001234567890" />';
}

function fc_field_use_recaptcha_cb() {
    $value = get_option( 'fc_use_recaptcha', '0' );
    echo '<label><input type="checkbox" name="fc_use_recaptcha" value="1" ' . checked( '1', $value, false ) . ' /> ' . esc_html__( 'Proteger el formulario con Google reCAPTCHA v3', 'flacso-flacso-formulario-consultas' ) . '</label>';
    echo '<p class="description">' . esc_html__( 'Protección invisible contra spam. Obtén tus claves en ', 'flacso-flacso-formulario-consultas' ) . '<a href="https://www.google.com/recaptcha/admin" target="_blank">https://www.google.com/recaptcha/admin</a></p>';
}

function fc_field_recaptcha_site_key_cb() {
    $value = esc_attr( get_option( 'fc_recaptcha_site_key', '' ) );
    echo '<input type="text" class="regular-text" name="fc_recaptcha_site_key" value="' . $value . '" placeholder="6LeIxAcTAAAAAJcZVRqyHh71UMIEGNQ_MXjiZKhI" />';
    echo '<p class="description">' . esc_html__( 'Clave pública de reCAPTCHA v3 (Site Key)', 'flacso-flacso-formulario-consultas' ) . '</p>';
}

function fc_field_recaptcha_secret_key_cb() {
    $value = esc_attr( get_option( 'fc_recaptcha_secret_key', '' ) );
    $masked = $value ? str_repeat( '*', strlen( $value ) - 8 ) . substr( $value, -8 ) : '';
    echo '<input type="password" class="regular-text" name="fc_recaptcha_secret_key" value="' . $value . '" placeholder="6LeIxAcTAAAAAGG-vFI1TnRWxMZNFuojJ4WifJWe" />';
    echo '<p class="description">' . esc_html__( 'Clave secreta de reCAPTCHA v3 (Secret Key)', 'flacso-flacso-formulario-consultas' ) . '</p>';
}

function fc_add_oferta_settings_submenu() {
    add_submenu_page(
        'edit.php?post_type=fc_info_request',
        __( 'Configuracion Webhook', 'flacso-flacso-formulario-consultas' ),
        __( 'Config. Webhook', 'flacso-flacso-formulario-consultas' ),
        'manage_options',
        'fc_oferta_options_page',
        'fc_render_oferta_options_page'
    );
}
add_action( 'admin_menu', 'fc_add_oferta_settings_submenu' );

function fc_add_consulta_settings_submenu() {
    add_submenu_page(
        'edit.php?post_type=fc_consulta',
        __( 'Config. Formulario de Consultas', 'flacso-flacso-formulario-consultas' ),
        __( 'Config. Formulario de Consultas', 'flacso-flacso-formulario-consultas' ),
        'manage_options',
        'fc_options_page',
        'fc_render_options_page'
    );
}
add_action( 'admin_menu', 'fc_add_consulta_settings_submenu' );

function fc_render_options_page() {
    if ( ! current_user_can( 'manage_options' ) ) {
        return;
    }
    ?>
    <div class="wrap">
        <h1><?php esc_html_e( 'Formulario de Consultas', 'flacso-flacso-formulario-consultas' ); ?></h1>
        <form action="options.php" method="post">
            <?php
            settings_fields( 'fc_options_group' );
            do_settings_sections( 'fc_options_page' );
            submit_button();
            ?>
        </form>

        <hr>
        <h2><?php esc_html_e( 'Prueba de envío de correo', 'flacso-flacso-formulario-consultas' ); ?></h2>
        <form action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" method="post">
            <?php wp_nonce_field( 'fc_send_test_email', 'fc_test_nonce' ); ?>
            <input type="hidden" name="action" value="fc_send_test_email" />
            <?php $test_to = esc_attr( get_option( 'fc_destinatario_email', get_option( 'admin_email' ) ) ); ?>
            <p>
                <label for="fc_test_email" class="screen-reader-text"><?php esc_html_e( 'Correo de prueba', 'flacso-flacso-formulario-consultas' ); ?></label>
                <input type="email" id="fc_test_email" name="fc_test_email" value="<?php echo $test_to; ?>" class="regular-text" required />
            </p>
            <?php submit_button( __( 'Enviar correo de prueba', 'flacso-flacso-formulario-consultas' ), 'secondary' ); ?>
        </form>

        <h2><?php esc_html_e( 'Prueba de Telegram', 'flacso-flacso-formulario-consultas' ); ?></h2>
        <form action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" method="post">
            <?php wp_nonce_field( 'fc_send_test_telegram', 'fc_tg_test_nonce' ); ?>
            <input type="hidden" name="action" value="fc_send_test_telegram" />
            <p>
                <label for="fc_tg_message" class="screen-reader-text"><?php esc_html_e( 'Mensaje de prueba', 'flacso-flacso-formulario-consultas' ); ?></label>
                <input type="text" id="fc_tg_message" name="fc_tg_message" value="<?php echo esc_attr__( 'Prueba de Telegram desde Formulario de Consultas', 'flacso-flacso-formulario-consultas' ); ?>" class="regular-text" />
            </p>
            <?php submit_button( __( 'Enviar mensaje de prueba', 'flacso-flacso-formulario-consultas' ), 'secondary' ); ?>
        </form>
    </div>
    <?php
}

function fc_render_oferta_options_page() {
    if ( ! current_user_can( 'manage_options' ) ) {
        return;
    }
    ?>
    <div class="wrap">
        <h1><?php esc_html_e( 'Solicitud de Información (Oferta Académica)', 'flacso-flacso-formulario-consultas' ); ?></h1>
        <form action="options.php" method="post">
            <?php
            settings_fields( 'fc_oferta_options_group' );
            do_settings_sections( 'fc_oferta_options_page' );
            submit_button();
            ?>
        </form>
    </div>
    <?php
}

// Handler de envío de prueba
function fc_handle_send_test_email() {
    if ( ! current_user_can( 'manage_options' ) ) {
        wp_die( esc_html__( 'No tienes permisos suficientes.', 'flacso-flacso-formulario-consultas' ) );
    }
    if ( ! isset( $_POST['fc_test_nonce'] ) || ! wp_verify_nonce( wp_unslash( $_POST['fc_test_nonce'] ), 'fc_send_test_email' ) ) {
        wp_die( esc_html__( 'Solicitud no válida.', 'flacso-flacso-formulario-consultas' ) );
    }
    $to = isset( $_POST['fc_test_email'] ) ? sanitize_email( wp_unslash( $_POST['fc_test_email'] ) ) : '';
    if ( ! is_email( $to ) ) {
        $to = get_option( 'admin_email' );
    }

    $site_name  = wp_specialchars_decode( get_bloginfo( 'name' ), ENT_QUOTES );
    $from_name  = $site_name;
    $from_email = get_option( 'fc_google_impersonated', 'noreply@flacso.edu.uy' );
    $headers    = [ 'Content-Type: text/html; charset=UTF-8', 'Reply-To: ' . $site_name . ' <' . $from_email . '>' ];
    $subject    = sprintf( __( 'Prueba de correo – %s', 'flacso-flacso-formulario-consultas' ), $site_name );
    $body_inner = '<p>' . esc_html__( 'Este es un mensaje de prueba del plugin Formulario de Consultas.', 'flacso-flacso-formulario-consultas' ) . '</p>'
                . '<p>' . esc_html__( 'Si recibes este correo, la configuración de envío está funcionando.', 'flacso-flacso-formulario-consultas' ) . '</p>';
    $body       = function_exists('fc_wrap_email_html') ? fc_wrap_email_html( $body_inner, __( 'Prueba de correo', 'flacso-flacso-formulario-consultas' ) ) : $body_inner;

    $ok = false;
    if ( function_exists( 'fc_can_use_gmail_api' ) && fc_can_use_gmail_api() ) {
        $ok = fc_send_via_gmail_api( $to, $subject, $body, $headers, $from_name, $from_email );
        if ( ! $ok ) {
            $ok = fc_send_via_wp_mail_with_fallbacks( $to, $subject, $body, $headers, $from_name, fc_get_fallback_senders_list() );
        }
    } else {
        $ok = fc_send_via_wp_mail_with_fallbacks( $to, $subject, $body, $headers, $from_name, fc_get_fallback_senders_list() );
    }

    $res = $ok ? 'success' : 'fail';
    $redirect = add_query_arg( [ 'page' => 'fc_options_page', 'fc_test' => $res ], admin_url( 'options-general.php' ) );
    wp_safe_redirect( $redirect );
    exit;
}
add_action( 'admin_post_fc_send_test_email', 'fc_handle_send_test_email' );
function fc_handle_send_test_telegram() {
    if ( ! current_user_can( 'manage_options' ) ) {
        wp_die( esc_html__( 'No tienes permisos suficientes.', 'flacso-flacso-formulario-consultas' ) );
    }
    if ( ! isset( $_POST['fc_tg_test_nonce'] ) || ! wp_verify_nonce( wp_unslash( $_POST['fc_tg_test_nonce'] ), 'fc_send_test_telegram' ) ) {
        wp_die( esc_html__( 'Solicitud no válida.', 'flacso-flacso-formulario-consultas' ) );
    }
    $msg = isset( $_POST['fc_tg_message'] ) ? sanitize_text_field( wp_unslash( $_POST['fc_tg_message'] ) ) : 'Prueba';
    $ok  = false;
    if ( function_exists( 'fc_send_telegram_message' ) ) {
        $ok = fc_send_telegram_message( $msg );
    }
    $res = $ok ? 'success' : 'fail';
    $redirect = add_query_arg( [ 'page' => 'fc_options_page', 'fc_tg_test' => $res ], admin_url( 'options-general.php' ) );
    wp_safe_redirect( $redirect );
    exit;
}
add_action( 'admin_post_fc_send_test_telegram', 'fc_handle_send_test_telegram' );

// Notice post-envío
function fc_settings_admin_notices() {
    if ( ! isset( $_GET['page'] ) || $_GET['page'] !== 'fc_options_page' ) { return; }
    if ( isset( $_GET['fc_test'] ) ) {
        if ( $_GET['fc_test'] === 'success' ) {
            echo '<div class="notice notice-success is-dismissible"><p>' . esc_html__( 'Correo de prueba enviado correctamente.', 'flacso-flacso-formulario-consultas' ) . '</p></div>';
        } else {
            echo '<div class="notice notice-error is-dismissible"><p>' . esc_html__( 'No se pudo enviar el correo de prueba. Revisa la configuración o el registro (debug.log).', 'flacso-flacso-formulario-consultas' ) . '</p></div>';
        }
    }
    if ( isset( $_GET['fc_tg_test'] ) ) {
        if ( $_GET['fc_tg_test'] === 'success' ) {
            echo '<div class="notice notice-success is-dismissible"><p>' . esc_html__( 'Mensaje de Telegram enviado correctamente.', 'flacso-flacso-formulario-consultas' ) . '</p></div>';
        } else {
            echo '<div class="notice notice-error is-dismissible"><p>' . esc_html__( 'No se pudo enviar el mensaje de Telegram. Revisa el token y chat ID.', 'flacso-flacso-formulario-consultas' ) . '</p></div>';
        }
    }
}
add_action( 'admin_notices', 'fc_settings_admin_notices' );


