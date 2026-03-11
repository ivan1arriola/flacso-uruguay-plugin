<?php
/**
 * Renderizado del formulario y registro de bloques Gutenberg
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Render del formulario (usado por el bloque y shortcode)
 */
function fc_render_form( $atts = [] ) {
    $atts = shortcode_atts(
        [
            'titulo' => '',
        ],
        $atts
    );
    fc_enqueue_assets();

    $nombre   = isset( $_GET['fc_nombre'] ) ? sanitize_text_field( wp_unslash( $_GET['fc_nombre'] ) ) : '';
    $apellido = isset( $_GET['fc_apellido'] ) ? sanitize_text_field( wp_unslash( $_GET['fc_apellido'] ) ) : '';
    $email    = isset( $_GET['fc_email'] ) ? sanitize_email( wp_unslash( $_GET['fc_email'] ) ) : '';
    $asunto   = isset( $_GET['fc_asunto'] ) ? sanitize_text_field( wp_unslash( $_GET['fc_asunto'] ) ) : '';
    $exito    = ( isset( $_GET['fc_exito'] ) && (int) $_GET['fc_exito'] === 1 ) || ( isset( $_GET['fc_confirmacion_consulta'] ) && (int) $_GET['fc_confirmacion_consulta'] === 1 );

    ob_start();
    ?>
    <div class="flacso-formulario-consultas">
    <div class="fc-form-wrapper" style="padding: 1.5rem 0.75rem; background: linear-gradient(135deg, #f5f7fa 0%, #e9ecef 100%);">
        <div style="max-width: 600px; margin: 0 auto;">
            <!-- Encabezado -->
            <div style="margin-bottom: 1.5rem; text-align: center;">
                <?php if ( ! empty( $atts['titulo'] ) ) : ?>
                    <h2 style="font-size: 1.75rem; font-weight: 700; color: #1a3a5c; margin: 0 0 0.5rem 0;"><?php echo esc_html( $atts['titulo'] ); ?></h2>
                <?php else : ?>
                    <h2 style="font-size: 1.75rem; font-weight: 700; color: #1a3a5c; margin: 0 0 0.5rem 0;"><?php esc_html_e( 'Formulario de consulta', 'flacso-flacso-formulario-consultas' ); ?></h2>
                <?php endif; ?>
                <p style="color: #6c7686; margin: 0 0 1rem 0; font-size: 0.95rem;"><?php esc_html_e( 'Completá los datos y te responderemos a la brevedad.', 'flacso-flacso-formulario-consultas' ); ?></p>
                <p style="color: #999; margin: 0; font-size: 0.85rem;">* <?php esc_html_e( 'Campos obligatorios', 'flacso-flacso-formulario-consultas' ); ?></p>
            </div>

            <!-- Alerta de éxito -->
            <?php if ( $exito ) : ?>
                <div style="background: #d4edda; border: 1px solid #c3e6cb; border-radius: 6px; padding: 1rem; margin-bottom: 1.5rem; display: flex; gap: 0.75rem; align-items: flex-start;">
                    <span style="font-size: 1.25rem; flex-shrink: 0;">✅</span>
                    <div>
                        <strong style="color: #155724;"><?php esc_html_e( '¡Consulta enviada!', 'flacso-flacso-formulario-consultas' ); ?></strong>
                        <p style="color: #155724; margin: 0.25rem 0 0 0; font-size: 0.9rem;"><?php echo esc_html__( 'Recibimos tu consulta y te enviamos un correo de confirmación.', 'flacso-flacso-formulario-consultas' ); ?></p>
                    </div>
                </div>
            <?php endif; ?>

            <!-- Formulario -->
            <form id="fc-form" class="needs-validation" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" method="post" novalidate style="background: white; border-radius: 12px; padding: 1.5rem 1rem; box-shadow: 0 2px 8px rgba(0,0,0,0.1);">
                <input type="hidden" name="action" value="fc_submit_consulta" />
                <?php wp_nonce_field( 'fc_form_submit', 'fc_nonce' ); ?>
                <input type="hidden" name="fc_timestamp" value="<?php echo esc_attr( time() ); ?>" />
                <input type="hidden" name="website" id="website" class="fc-hp" tabindex="-1" autocomplete="off" aria-hidden="true">
                <input type="hidden" name="fc_company" id="fc_company" class="fc-hp" tabindex="-1" autocomplete="off" aria-hidden="true">

                <!-- Nombre y Apellido en fila (responsive) -->
                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1rem; margin-bottom: 1rem;">
                    <div>
                        <label for="fc_nombre" style="display: block; font-weight: 600; margin-bottom: 0.5rem; color: #333; font-size: 0.95rem;"><?php esc_html_e( 'Nombre', 'flacso-flacso-formulario-consultas' ); ?> *</label>
                        <input type="text" id="fc_nombre" name="fc_nombre" class="form-control" value="<?php echo esc_attr( $nombre ); ?>" required minlength="2" style="border: 1px solid #dee2e6; border-radius: 6px; padding: 0.75rem 0.875rem; font-size: 1rem; width: 100%; box-sizing: border-box;">
                        <small style="display: block; color: #dc3545; margin-top: 0.25rem; display: none;" class="invalid-feedback"><?php esc_html_e( 'Mínimo 2 caracteres', 'flacso-flacso-formulario-consultas' ); ?></small>
                    </div>
                    <div>
                        <label for="fc_apellido" style="display: block; font-weight: 600; margin-bottom: 0.5rem; color: #333; font-size: 0.95rem;"><?php esc_html_e( 'Apellido', 'flacso-flacso-formulario-consultas' ); ?> *</label>
                        <input type="text" id="fc_apellido" name="fc_apellido" class="form-control" value="<?php echo esc_attr( $apellido ); ?>" required minlength="2" style="border: 1px solid #dee2e6; border-radius: 6px; padding: 0.75rem 0.875rem; font-size: 1rem; width: 100%; box-sizing: border-box;">
                        <small style="display: block; color: #dc3545; margin-top: 0.25rem; display: none;" class="invalid-feedback"><?php esc_html_e( 'Mínimo 2 caracteres', 'flacso-flacso-formulario-consultas' ); ?></small>
                    </div>
                </div>

                <!-- Email -->
                <div style="margin-bottom: 1rem;">
                    <label for="fc_email" style="display: block; font-weight: 600; margin-bottom: 0.5rem; color: #333; font-size: 0.95rem;"><?php esc_html_e( 'Correo electrónico', 'flacso-flacso-formulario-consultas' ); ?> *</label>
                    <input type="email" id="fc_email" name="fc_email" class="form-control" value="<?php echo esc_attr( $email ); ?>" required style="border: 1px solid #dee2e6; border-radius: 6px; padding: 0.75rem 0.875rem; font-size: 1rem; width: 100%; box-sizing: border-box;">
                    <small style="display: block; color: #dc3545; margin-top: 0.25rem; display: none;" class="invalid-feedback"><?php esc_html_e( 'Correo inválido', 'flacso-flacso-formulario-consultas' ); ?></small>
                </div>

                <!-- Teléfono -->
                <div style="margin-bottom: 1rem;">
                    <label for="fc_telefono" style="display: block; font-weight: 600; margin-bottom: 0.5rem; color: #333; font-size: 0.95rem;"><?php esc_html_e( 'Teléfono', 'flacso-flacso-formulario-consultas' ); ?> *</label>
                    <input type="tel" id="fc_telefono" name="fc_telefono" class="form-control" pattern="[+0-9\s\-\(\)]{2,}" inputmode="tel" placeholder="+598 99 123 456" required style="border: 1px solid #dee2e6; border-radius: 6px; padding: 0.75rem 0.875rem; font-size: 1rem; width: 100%; box-sizing: border-box;">
                    <input type="hidden" id="fc_telefono_full" name="fc_telefono_full" value="" />
                    <small style="display: block; color: #6c757d; margin-top: 0.25rem; font-size: 0.85rem;"><?php esc_html_e( 'Ej: +598 99 123 456', 'flacso-flacso-formulario-consultas' ); ?></small>
                </div>

                <!-- Asunto -->
                <div style="margin-bottom: 1rem;">
                    <label for="fc_asunto" style="display: block; font-weight: 600; margin-bottom: 0.5rem; color: #333; font-size: 0.95rem;"><?php esc_html_e( 'Asunto', 'flacso-flacso-formulario-consultas' ); ?> *</label>
                    <input type="text" id="fc_asunto" name="fc_asunto" class="form-control" value="<?php echo esc_attr( $asunto ); ?>" required minlength="2" style="border: 1px solid #dee2e6; border-radius: 6px; padding: 0.75rem 0.875rem; font-size: 1rem; width: 100%; box-sizing: border-box;">
                    <small style="display: block; color: #dc3545; margin-top: 0.25rem; display: none;" class="invalid-feedback"><?php esc_html_e( 'Mínimo 2 caracteres', 'flacso-flacso-formulario-consultas' ); ?></small>
                </div>

                <!-- Mensaje -->
                <div style="margin-bottom: 1.5rem;">
                    <label for="fc_mensaje" style="display: block; font-weight: 600; margin-bottom: 0.5rem; color: #333; font-size: 0.95rem;"><?php esc_html_e( 'Tu consulta', 'flacso-flacso-formulario-consultas' ); ?> *</label>
                    <textarea id="fc_mensaje" name="fc_mensaje" class="form-control" rows="5" required minlength="2" style="border: 1px solid #dee2e6; border-radius: 6px; padding: 0.75rem 0.875rem; font-size: 1rem; width: 100%; box-sizing: border-box; resize: vertical; min-height: 120px;"></textarea>
                    <small style="display: block; color: #dc3545; margin-top: 0.25rem; display: none;" class="invalid-feedback"><?php esc_html_e( 'Mínimo 2 caracteres', 'flacso-flacso-formulario-consultas' ); ?></small>
                </div>

                <!-- Botón y protección -->
                <div style="margin-bottom: 0;">
                    <button class="btn btn-primary" type="submit" style="width: 100%; padding: 0.875rem; font-size: 1rem; font-weight: 600; border: none; border-radius: 6px; background: linear-gradient(135deg, #1a7c7a 0%, #0f4f4d 100%); color: white; cursor: pointer; transition: transform 0.2s, box-shadow 0.2s;" onmouseover="this.style.transform='translateY(-2px)'; this.style.boxShadow='0 4px 12px rgba(26,124,122,0.3)';" onmouseout="this.style.transform='none'; this.style.boxShadow='none';">
                        <span class="fc-btn-text"><?php esc_html_e( 'Enviar consulta', 'flacso-flacso-formulario-consultas' ); ?></span>
                        <span class="fc-btn-spinner spinner-border spinner-border-sm d-none ms-2" role="status" aria-hidden="true"></span>
                    </button>
                    <p style="color: #999; font-size: 0.8rem; margin-top: 0.75rem; text-align: center; margin-bottom: 0;">🔒 <?php esc_html_e( 'Protegido con validación en servidor', 'flacso-flacso-formulario-consultas' ); ?></p>
                </div>
            </form>
        </div>
    </div>
    </div>
    <?php
    return ob_get_clean();
}

/**
 * Registro del bloque dinámico (Gutenberg) para el formulario
 */
function fc_register_block() {
    if ( function_exists( 'register_block_type' ) ) {
        register_block_type( FC_PLUGIN_DIR . 'blocks/formulario-consulta' );
    }
}
add_action( 'init', 'fc_register_block' );

/**
 * Agrega categoría personalizada para el bloque en el editor
 */
function fc_register_block_category( $categories, $post ) {
    $custom = [
        [
            'slug'  => 'flacso-uruguay',
            'title' => __( 'FLACSO Uruguay', 'flacso-flacso-formulario-consultas' ),
        ],
    ];
    // Evitar duplicados
    $existing = wp_list_pluck( $categories, 'slug' );
    if ( ! in_array( 'flacso-uruguay', $existing, true ) ) {
        return array_merge( $custom, $categories );
    }
    return $categories;
}
add_filter( 'block_categories_all', 'fc_register_block_category', 10, 2 );
