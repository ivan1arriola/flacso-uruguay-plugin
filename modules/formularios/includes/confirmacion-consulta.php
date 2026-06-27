<?php
/**
 * Página de agradecimiento virtual
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

function fc_apply_virtual_confirmation_title( $base_title ) {
    $base_title = trim( (string) $base_title );
    $site_name  = trim( (string) get_bloginfo( 'name' ) );
    $full_title = $site_name ? $base_title . ' - ' . $site_name : $base_title;

    add_filter(
        'pre_get_document_title',
        static function () use ( $full_title ) {
            return $full_title;
        },
        999
    );

    add_filter(
        'document_title_parts',
        static function ( $parts ) use ( $base_title ) {
            $parts['title'] = $base_title;
            unset( $parts['tagline'] );
            return $parts;
        },
        999
    );

    add_filter(
        'wp_title',
        static function () use ( $full_title ) {
            return $full_title;
        },
        999
    );

    add_filter(
        'rank_math/frontend/title',
        static function () use ( $full_title ) {
            return $full_title;
        },
        999
    );

    add_filter(
        'wpseo_title',
        static function () use ( $full_title ) {
            return $full_title;
        },
        999
    );
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

    global $wp_query;
    if ( $wp_query ) {
        $wp_query->is_404 = false;
        $wp_query->is_singular = true;
        $wp_query->is_page = true;
        $wp_query->is_archive = false;
        $wp_query->is_home = false;
        $wp_query->is_posts_page = false;
        $wp_query->set_404( false );
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

    fc_apply_virtual_confirmation_title( __( 'Consulta enviada', 'flacso-flacso-formulario-consultas' ) );
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
        if (typeof window.flacsoMetaTrack !== 'function') {
            return;
        }
        var pixelPayload = {
            content_name: <?php echo wp_json_encode( (string) $asunto ); ?>,
            content_category: 'consulta_general',
            status: 'submitted'
        };
        try {
            window.flacsoMetaTrack('Lead', pixelPayload);
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

