<?php
/**
 * Página de agradecimiento virtual
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Renderiza la página virtual de agradecimiento.
 */
function fc_maybe_render_gracias_page() {
    $is_confirmacion = (int) get_query_var( 'fc_confirmacion_consulta' ) === 1;
    $has_fc_payload  = isset( $_GET['fc_nombre'] ) || isset( $_GET['fc_apellido'] ) || isset( $_GET['fc_email'] ) || isset( $_GET['fc_asunto'] );
    $has_oferta_pid  = isset( $_GET['pid'] ) && absint( $_GET['pid'] ) > 0;

    // Si viene desde solicitud de informacion de oferta academica, deja que la maneje su propio render.
    if ( $has_oferta_pid ) {
        return;
    }

    // Detecta cualquier /algo/confirmacion-consulta/ (sin depender de rewrite).
    if ( ! $is_confirmacion && $has_fc_payload ) {
        $request_uri = sanitize_text_field( wp_unslash( $_SERVER['REQUEST_URI'] ?? '' ) );
        $path        = wp_parse_url( $request_uri, PHP_URL_PATH );
        $segments    = array_filter( explode( '/', trim( (string) $path, '/' ) ) );
        $is_confirmacion = ( ! empty( $segments ) && 'confirmacion-consulta' === strtolower( end( $segments ) ) );
    }

    if ( ! $is_confirmacion ) {
        return;
    }

    status_header( 200 );
    nocache_headers();

    $nombre   = isset( $_GET['fc_nombre'] ) ? sanitize_text_field( wp_unslash( $_GET['fc_nombre'] ) ) : '';
    $apellido = isset( $_GET['fc_apellido'] ) ? sanitize_text_field( wp_unslash( $_GET['fc_apellido'] ) ) : '';
    $email    = isset( $_GET['fc_email'] ) ? sanitize_email( wp_unslash( $_GET['fc_email'] ) ) : '';
    $asunto   = isset( $_GET['fc_asunto'] ) ? sanitize_text_field( wp_unslash( $_GET['fc_asunto'] ) ) : '';

    $nombre_completo = trim( $nombre . ' ' . $apellido );
    if ( '' === $nombre_completo ) {
        $nombre_completo = __( 'Tu consulta', 'flacso-flacso-formulario-consultas' );
    }

    get_header();
    ?>
    <main class="fc-gracias container" style="padding:2rem 0;">
        <div class="fc-gracias__box" style="background:#fff;border:1px solid #ddd;padding:2rem;max-width:760px;margin:0 auto;border-radius:6px;">
            <h1 style="margin-top:0;"><?php esc_html_e( '¡Gracias por tu consulta!', 'flacso-flacso-formulario-consultas' ); ?></h1>
            <p style="font-size:1.05rem;"><?php echo esc_html( sprintf( __( 'Hola %s, recibimos tu mensaje.', 'flacso-flacso-formulario-consultas' ), $nombre_completo ) ); ?></p>
            <?php if ( $asunto ) : ?>
                <p style="font-size:1rem;"><?php echo esc_html( sprintf( __( 'Asunto: %s', 'flacso-flacso-formulario-consultas' ), $asunto ) ); ?></p>
            <?php endif; ?>
            <p style="font-size:1rem;"><?php esc_html_e( 'Responderemos a la brevedad al correo indicado.', 'flacso-flacso-formulario-consultas' ); ?></p>
            <?php if ( $email ) : ?>
                <p style="font-size:1rem;"><?php echo esc_html( sprintf( __( 'Correo: %s', 'flacso-flacso-formulario-consultas' ), $email ) ); ?></p>
            <?php endif; ?>
            <p style="margin-top:1rem;"><a class="button" href="<?php echo esc_url( home_url() ); ?>"><?php esc_html_e( 'Volver al inicio', 'flacso-flacso-formulario-consultas' ); ?></a></p>
        </div>
    </main>
    <script>
    (function() {
        if (typeof window.fbq !== 'function') {
            return;
        }
        var pixelPayload = {
            content_name: <?php echo wp_json_encode( (string) $asunto ); ?>,
            content_category: 'consulta_general',
            status: 'submitted'
        };
        try {
            window.fbq('track', 'Lead', pixelPayload);
        } catch (e) {
            if (window.console && typeof window.console.warn === 'function') {
                console.warn('[Formulario Consultas] Error enviando evento Meta Pixel:', e);
            }
        }
    })();
    </script>
    <?php
    get_footer();
    exit;
}
add_action( 'template_redirect', 'fc_maybe_render_gracias_page', 0 );

