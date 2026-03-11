<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }

if ( function_exists( 'fc_render_form' ) ) {
    $atts = isset( $attributes ) && is_array( $attributes ) ? $attributes : [];
    echo fc_render_form( $atts );
} else {
    echo '<div class="notice notice-error">' . esc_html__( 'El formulario no está disponible.', 'flacso-flacso-formulario-consultas' ) . '</div>';
}


