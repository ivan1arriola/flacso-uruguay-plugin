<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

function fc_register_settings() {
    $text = [ 'type' => 'string', 'sanitize_callback' => 'sanitize_text_field', 'default' => '' ];
    $flag = static function ( $default ) {
        return [ 'type' => 'string', 'sanitize_callback' => static function ( $v ) { return $v === '1' ? '1' : '0'; }, 'default' => $default ];
    };
    register_setting( 'fc_options_group', 'fc_destinatario_email', [ 'type' => 'string', 'sanitize_callback' => 'sanitize_email', 'default' => '' ] );
    register_setting( 'fc_options_group', 'fc_asunto_admin', $text );
    register_setting( 'fc_options_group', 'fc_asunto_usuario', $text );
    register_setting( 'fc_options_group', 'fc_enviar_acuse', $flag( '1' ) );
    register_setting( 'fc_options_group', 'fc_texto_acuse', [ 'type' => 'string', 'sanitize_callback' => 'sanitize_textarea_field', 'default' => '' ] );
    register_setting( 'fc_options_group', 'fc_telegram_notify_consultas', $flag( '1' ) );
    register_setting( 'fc_options_group', 'fc_cargar_bootstrap', $flag( '1' ) );

    add_settings_section( 'fc_main_section', __( 'Recepción de consultas', 'flacso-flacso-formulario-consultas' ), function(){
        echo '<p>' . esc_html__( 'Las consultas no se guardan: llegan por correo a recepción, el consultante recibe un acuse y el equipo recibe un aviso por Telegram. Los correos salen por Mautic. En asuntos y texto podés usar {nombre}, {apellido} y {asunto}.', 'flacso-flacso-formulario-consultas' ) . '</p>';
    }, 'fc_options_page' );

    add_settings_field( 'fc_destinatario_email', __( 'Correo de recepción', 'flacso-flacso-formulario-consultas' ), 'fc_field_email_cb', 'fc_options_page', 'fc_main_section' );
    add_settings_field( 'fc_asunto_admin', __( 'Asunto del aviso a recepción', 'flacso-flacso-formulario-consultas' ), 'fc_field_asunto_admin_cb', 'fc_options_page', 'fc_main_section' );
    add_settings_field( 'fc_enviar_acuse', __( 'Acuse al consultante', 'flacso-flacso-formulario-consultas' ), 'fc_field_acuse_cb', 'fc_options_page', 'fc_main_section' );
    add_settings_field( 'fc_asunto_usuario', __( 'Asunto del acuse', 'flacso-flacso-formulario-consultas' ), 'fc_field_asunto_usuario_cb', 'fc_options_page', 'fc_main_section' );
    add_settings_field( 'fc_texto_acuse', __( 'Texto del acuse', 'flacso-flacso-formulario-consultas' ), 'fc_field_texto_acuse_cb', 'fc_options_page', 'fc_main_section' );
    add_settings_field( 'fc_telegram_notify_consultas', __( 'Aviso por Telegram', 'flacso-flacso-formulario-consultas' ), 'fc_field_telegram_cb', 'fc_options_page', 'fc_main_section' );
    add_settings_field( 'fc_cargar_bootstrap', __( 'Cargar Bootstrap automáticamente', 'flacso-flacso-formulario-consultas' ), 'fc_field_bootstrap_cb', 'fc_options_page', 'fc_main_section' );
}
add_action( 'admin_init', 'fc_register_settings' );

function fc_field_email_cb() {
    $value = esc_attr( get_option( 'fc_destinatario_email', '' ) );
    echo '<input type="email" class="regular-text" name="fc_destinatario_email" value="' . $value . '" placeholder="' . esc_attr( FC_DEFAULT_RECEPTION_EMAIL ) . '" />';
    echo '<p class="description">' . esc_html__( 'A esta casilla llega cada consulta, con respuesta directa al consultante. Vacío usa el valor por defecto que se muestra.', 'flacso-flacso-formulario-consultas' ) . '</p>';
}

function fc_field_asunto_admin_cb() {
    $value = esc_attr( get_option( 'fc_asunto_admin', '' ) );
    echo '<input type="text" class="regular-text" name="fc_asunto_admin" value="' . $value . '" placeholder="' . esc_attr__( 'Consulta web FLACSO: {asunto}', 'flacso-flacso-formulario-consultas' ) . '" />';
    echo '<p class="description">' . esc_html__( 'Déjalo vacío para usar el asunto por defecto.', 'flacso-flacso-formulario-consultas' ) . '</p>';
}

function fc_field_acuse_cb() {
    echo '<label><input type="checkbox" name="fc_enviar_acuse" value="1" ' . checked( '1', get_option( 'fc_enviar_acuse', '1' ), false ) . ' /> ' . esc_html__( 'Enviar un correo de confirmación a quien consulta', 'flacso-flacso-formulario-consultas' ) . '</label>';
    echo '<p class="description">' . esc_html__( 'Sale sólo cuando recepción ya recibió la consulta. Si el consultante responde, la respuesta llega al correo de recepción.', 'flacso-flacso-formulario-consultas' ) . '</p>';
}

function fc_field_asunto_usuario_cb() {
    $value = esc_attr( get_option( 'fc_asunto_usuario', '' ) );
    echo '<input type="text" class="regular-text" name="fc_asunto_usuario" value="' . $value . '" placeholder="' . esc_attr( FC_DEFAULT_ACK_SUBJECT ) . '" />';
}

function fc_field_texto_acuse_cb() {
    $value = esc_textarea( get_option( 'fc_texto_acuse', '' ) );
    echo '<textarea class="large-text" rows="7" name="fc_texto_acuse" placeholder="' . esc_attr( FC_DEFAULT_ACK_TEXT ) . '">' . $value . '</textarea>';
    echo '<p class="description">' . esc_html__( 'Texto plano. Vacío usa el texto por defecto que se muestra.', 'flacso-flacso-formulario-consultas' ) . '</p>';
}

function fc_field_telegram_cb() {
    echo '<label><input type="checkbox" name="fc_telegram_notify_consultas" value="1" ' . checked( '1', get_option( 'fc_telegram_notify_consultas', '1' ), false ) . ' /> ' . esc_html__( 'Avisar cada consulta por Telegram', 'flacso-flacso-formulario-consultas' ) . '</label>';
    $ready = function_exists( 'fc_can_use_telegram' ) && fc_can_use_telegram();
    $sistema = esc_url( admin_url( 'admin.php?page=flacso-sistema' ) );
    echo '<p class="description">' . ( $ready
        ? esc_html__( 'Telegram está configurado.', 'flacso-flacso-formulario-consultas' )
        : sprintf( wp_kses( __( 'Telegram no está configurado: cargá el bot y el chat en <a href="%s">FLACSO › Sistema</a>.', 'flacso-flacso-formulario-consultas' ), [ 'a' => [ 'href' => [] ] ] ), $sistema ) )
        . '</p>';
}

function fc_field_bootstrap_cb() {
    $value = get_option( 'fc_cargar_bootstrap', '1' );
    echo '<label><input type="checkbox" name="fc_cargar_bootstrap" value="1" ' . checked( '1', $value, false ) . ' /> ' . esc_html__( 'Cargar CSS/JS de Bootstrap 5 al mostrar el formulario', 'flacso-flacso-formulario-consultas' ) . '</label>';
}

function fc_add_consulta_settings_submenu() {
    add_submenu_page(
        FLACSO_Admin_Panel::PAGE_SLUG,
        __( 'Formulario general', 'flacso-flacso-formulario-consultas' ),
        __( 'Formulario general', 'flacso-flacso-formulario-consultas' ),
        'manage_options',
        'fc_options_page',
        'fc_render_options_page'
    );
}
add_action( 'admin_menu', 'fc_add_consulta_settings_submenu' );

function fc_get_consultas_settings_page_url( array $args = [] ) {
    return add_query_arg(
        array_merge(
            [
                'page'      => 'fc_options_page',
            ],
            $args
        ),
        admin_url( 'admin.php' )
    );
}

function fc_render_options_page() {
    if ( ! current_user_can( 'manage_options' ) ) {
        return;
    }
    ?>
    <div class="wrap">
        <h1><?php esc_html_e( 'Formulario general', 'flacso-flacso-formulario-consultas' ); ?></h1>
        <p><?php esc_html_e( 'Destinatario, acuse y avisos del formulario de contacto. El bot de Telegram y reCAPTCHA se administran en FLACSO > Sistema.', 'flacso-flacso-formulario-consultas' ); ?></p>
        <form action="options.php" method="post">
            <?php
            settings_fields( 'fc_options_group' );
            do_settings_sections( 'fc_options_page' );
            submit_button();
            ?>
        </form>

        <hr>
        <h2><?php esc_html_e( 'Consulta de prueba', 'flacso-flacso-formulario-consultas' ); ?></h2>
        <p><?php esc_html_e( 'Recorre el flujo real con una consulta de ejemplo: el aviso va a recepción, el acuse a tu correo y el mensaje a Telegram.', 'flacso-flacso-formulario-consultas' ); ?></p>
        <form action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" method="post">
            <?php wp_nonce_field( 'fc_send_test_email', 'fc_test_nonce' ); ?>
            <input type="hidden" name="action" value="fc_send_test_email" />
            <?php submit_button( __( 'Enviar consulta de prueba', 'flacso-flacso-formulario-consultas' ), 'secondary' ); ?>
        </form>

    </div>
    <?php
}

// Handler de consulta de prueba
function fc_handle_send_test_email() {
    if ( ! current_user_can( 'manage_options' ) ) {
        wp_die( esc_html__( 'No tienes permisos suficientes.', 'flacso-flacso-formulario-consultas' ) );
    }
    if ( ! isset( $_POST['fc_test_nonce'] ) || ! wp_verify_nonce( wp_unslash( $_POST['fc_test_nonce'] ), 'fc_send_test_email' ) ) {
        wp_die( esc_html__( 'Solicitud no válida.', 'flacso-flacso-formulario-consultas' ) );
    }
    $user = wp_get_current_user();
    $result = fc_send_general_inquiry( [
        'event_id' => wp_generate_uuid4(),
        'nombre'   => 'Prueba',
        'apellido' => 'FLACSO',
        'email'    => $user->user_email,
        'telefono' => '+598 0000 0000',
        'asunto'   => 'Consulta de prueba',
        'mensaje'  => 'Mensaje de prueba enviado desde Formulario general.',
    ] );

    $ok = ! empty( $result['ok'] ) && 'failed' !== ( $result['acuse'] ?? '' );
    $args = [ 'fc_test' => $ok ? 'success' : 'fail' ];
    $redirect_url = class_exists( 'FLACSO_Integrations_Settings' )
        ? FLACSO_Integrations_Settings::get_redirect_url_from_request( $args, fc_get_consultas_settings_page_url() )
        : fc_get_consultas_settings_page_url( $args );
    wp_safe_redirect( $redirect_url );
    exit;
}
add_action( 'admin_post_fc_send_test_email', 'fc_handle_send_test_email' );

function fc_handle_test_consultas_webhook() {
    if ( ! current_user_can( 'manage_options' ) ) {
        wp_die( esc_html__( 'No tienes permisos suficientes.', 'flacso-flacso-formulario-consultas' ) );
    }
    if ( ! isset( $_POST['fc_consultas_webhook_test_nonce'] ) || ! wp_verify_nonce( wp_unslash( $_POST['fc_consultas_webhook_test_nonce'] ), 'fc_test_consultas_webhook' ) ) {
        wp_die( esc_html__( 'Solicitud no válida.', 'flacso-flacso-formulario-consultas' ) );
    }

    $result = function_exists( 'fc_send_consulta_webhook_test' )
        ? fc_send_consulta_webhook_test()
        : [
            'ok'      => false,
            'code'    => 0,
            'error'   => 'La prueba del webhook no está disponible.',
            'message' => '',
        ];

    $args = [
        'fc_consultas_webhook_test' => $result['ok'] ? 'success' : 'fail',
    ];

    if ( ! empty( $result['code'] ) ) {
        $args['fc_consultas_webhook_code'] = (int) $result['code'];
    }

    $message = '';
    if ( ! empty( $result['message'] ) ) {
        $message = sanitize_text_field( (string) $result['message'] );
    } elseif ( ! empty( $result['error'] ) ) {
        $message = sanitize_text_field( (string) $result['error'] );
    }

    if ( '' !== $message ) {
        $args['fc_consultas_webhook_message'] = $message;
    }

    $redirect_url = class_exists( 'FLACSO_Integrations_Settings' )
        ? FLACSO_Integrations_Settings::get_redirect_url_from_request( $args, fc_get_consultas_settings_page_url() )
        : fc_get_consultas_settings_page_url( $args );
    wp_safe_redirect( $redirect_url );
    exit;
}
add_action( 'admin_post_fc_test_consultas_webhook', 'fc_handle_test_consultas_webhook' );

function fc_render_webhook_test_notice( $status, $code, $message, $token_variable_name ) {
    if ( 'success' === $status ) {
        echo '<div class="notice notice-success is-dismissible"><p>' . esc_html__( 'La app respondió correctamente y aceptó la prueba del webhook.', 'flacso-flacso-formulario-consultas' ) . '</p></div>';
        return;
    }

    if ( 401 === $code ) {
        $notice = sprintf(
            /* translators: %s: environment variable name */
            __( 'La app rechazó el token del webhook. Revisá que coincida con %s.', 'flacso-flacso-formulario-consultas' ),
            $token_variable_name
        );
    } elseif ( 404 === $code ) {
        $notice = __( 'La URL del webhook respondió 404. Revisá que el endpoint exista en la app.', 'flacso-flacso-formulario-consultas' );
    } elseif ( $code >= 500 ) {
        $notice = sprintf(
            /* translators: %d: HTTP status code */
            __( 'La app respondió con un error interno (HTTP %d).', 'flacso-flacso-formulario-consultas' ),
            $code
        );
    } elseif ( '' !== $message ) {
        $notice = $message;
    } else {
        $notice = __( 'No se pudo validar la conexión con la app. Revisá la URL configurada y el token.', 'flacso-flacso-formulario-consultas' );
    }

    echo '<div class="notice notice-error is-dismissible"><p>' . esc_html( $notice ) . '</p></div>';
}

// Notice post-envío
function fc_settings_admin_notices() {
    $page = isset( $_GET['page'] ) ? sanitize_key( wp_unslash( $_GET['page'] ) ) : '';

    if ( 'fc_options_page' === $page ) {
        if ( isset( $_GET['fc_test'] ) ) {
            if ( $_GET['fc_test'] === 'success' ) {
                echo '<div class="notice notice-success is-dismissible"><p>' . esc_html__( 'Consulta de prueba enviada: revisá la casilla de recepción, tu correo y Telegram.', 'flacso-flacso-formulario-consultas' ) . '</p></div>';
            } else {
                echo '<div class="notice notice-error is-dismissible"><p>' . esc_html__( 'No se pudo completar la consulta de prueba. Revisá la conexión con Mautic en FLACSO › Correos.', 'flacso-flacso-formulario-consultas' ) . '</p></div>';
            }
        }
    }

}
add_action( 'admin_notices', 'fc_settings_admin_notices' );


