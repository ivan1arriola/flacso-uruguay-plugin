<?php
/**
 * Gestión de assets (estilos y scripts) del plugin
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Registra assets en init para permitir que el bloque los use por handle.
 */
function fc_register_assets() {
	// Siempre registrar; se encolarán cuando sea necesario.
	wp_register_style( 'fc-bootstrap', 'https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css', [], '5.3.3' );
	wp_register_script( 'fc-bootstrap', 'https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js', [], '5.3.3', true );

	wp_register_style( 'fc-iti', 'https://cdn.jsdelivr.net/npm/intl-tel-input@18.6.1/build/css/intlTelInput.min.css', [], '18.6.1' );
	wp_register_script( 'fc-iti', 'https://cdn.jsdelivr.net/npm/intl-tel-input@18.6.1/build/js/intlTelInput.min.js', [], '18.6.1', true );

	wp_register_style( 'fc-styles', FC_PLUGIN_URL . 'assets/css/formulario-consultas.css', [], FC_PLUGIN_VERSION );
	wp_register_script( 'fc-scripts', FC_PLUGIN_URL . 'assets/js/formulario-consultas.js', [ 'jquery' ], FC_PLUGIN_VERSION, true );
}
add_action( 'init', 'fc_register_assets' );

/**
 * Encola estilos y scripts del plugin cuando se renderiza el formulario.
 */
function fc_enqueue_assets() {
    $load_bootstrap = get_option( 'fc_cargar_bootstrap', '1' ) === '1';
	if ( $load_bootstrap ) {
		wp_enqueue_style( 'fc-bootstrap' );
		wp_enqueue_script( 'fc-bootstrap' );
	}
	wp_enqueue_style( 'fc-styles' );
	wp_enqueue_script( 'fc-scripts' );

	// Cargar reCAPTCHA v3 si está activo
	if ( get_option( 'fc_use_recaptcha', '0' ) === '1' ) {
		$site_key = get_option( 'fc_recaptcha_site_key', '' );
		if ( ! empty( $site_key ) ) {
			wp_enqueue_script( 'fc-recaptcha', 'https://www.google.com/recaptcha/api.js?render=' . esc_attr( $site_key ), [], null, true );
			wp_add_inline_script( 'fc-recaptcha', '
				document.addEventListener("DOMContentLoaded", function() {
					const form = document.getElementById("fc-form");
					if (form) {
						form.addEventListener("submit", function(e) {
							e.preventDefault();
							grecaptcha.ready(function() {
								grecaptcha.execute("' . esc_js( $site_key ) . '", {action: "fc_submit"}).then(function(token) {
									let input = document.createElement("input");
									input.type = "hidden";
									input.name = "fc_recaptcha_token";
									input.value = token;
									form.appendChild(input);
									form.submit();
								});
							});
						});
					}
				});
			' );
		}
	}
}
