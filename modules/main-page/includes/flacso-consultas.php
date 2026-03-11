<?php
/**
 * Merged Plugin:     FLACSO Consultas (Bloque Gutenberg)
 * Plugin URI:        https://www.flacso.edu.uy/
 * Description:       Formulario de consultas FLACSO con integración AJAX, página virtual /gracias y bloque Gutenberg.
 * Version:           1.1.1
 * Author:            FLACSO Uruguay
 * Author URI:        https://www.flacso.edu.uy/
 * Requires at least: 6.3
 * Tested up to:      6.6
 * Requires PHP:      7.4
 * License:           GPL-2.0-or-later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       flacso-consultas
 *
 * @package FlacsoConsultas
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( defined( 'FLACSO_CONSULTAS_LOADED' ) ) {
	return;
}
define( 'FLACSO_CONSULTAS_LOADED', true );

if ( ! defined( 'FLACSO_CONSULTAS_VERSION' ) ) {
	define( 'FLACSO_CONSULTAS_VERSION', '1.1.1' );
}

// Registrar con flacso-common para creación de sinergias
if ( file_exists( dirname(__DIR__) . '/flacso-common.php' ) ) {
	require_once dirname(__DIR__) . '/flacso-common.php';
	if ( function_exists( 'flacso_register_plugin' ) ) {
		flacso_register_plugin( 'flacso-consultas', [ 'name' => 'FLACSO Consultas (Bloque)', 'version' => FLACSO_CONSULTAS_VERSION ] );
	}
}

/**
 * Configuración global.
 */
if ( ! defined( 'FLACSO_CONSULTAS_HABILITADO' ) ) {
	define( 'FLACSO_CONSULTAS_HABILITADO', true );
}
if ( ! defined( 'FLACSO_WEBHOOK_URL' ) ) {
	define( 'FLACSO_WEBHOOK_URL', 'https://script.google.com/macros/s/AKfycbx7Vyd3cOX0_kyY78dASZKsULA6bH_F4r08vjoFBPwtP-b_19JZV5T0mQS-QXSuuamt/exec' );
}
if ( ! defined( 'FLACSO_EMAIL_CONTACTO' ) ) {
	define( 'FLACSO_EMAIL_CONTACTO', 'inscripciones@flacso.edu.uy' );
}
if ( ! defined( 'FLACSO_USE_NONCE' ) ) {
	define( 'FLACSO_USE_NONCE', false );
}
if ( ! defined( 'FLACSO_RELAXED_MODE' ) ) {
	define( 'FLACSO_RELAXED_MODE', true );
}
if ( ! defined( 'FLACSO_WEBHOOK_TIMEOUT' ) ) {
	define( 'FLACSO_WEBHOOK_TIMEOUT', 25 );
}

/**
 * Encola Bootstrap Icons para front y editor.
 */
function flacso_consultas_enqueue_bootstrap_icons() {
	if ( ! wp_style_is( 'bootstrap-icons', 'enqueued' ) ) {
		wp_enqueue_style(
			'bootstrap-icons',
			'https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css',
			array(),
			'1.11.3'
		);
	}
}
add_action( 'enqueue_block_assets', 'flacso_consultas_enqueue_bootstrap_icons' );

/**
 * Helpers generales.
 */
function flacso_get_user_ip() {
	$ip_keys = array(
		'HTTP_X_REAL_IP',
		'HTTP_CLIENT_IP',
		'HTTP_X_FORWARDED_FOR',
		'HTTP_X_FORWARDED',
		'HTTP_X_CLUSTER_CLIENT_IP',
		'HTTP_FORWARDED_FOR',
		'HTTP_FORWARDED',
		'REMOTE_ADDR',
	);
	foreach ( $ip_keys as $key ) {
		if ( ! empty( $_SERVER[ $key ] ) ) {
			$ip = trim( current( explode( ',', $_SERVER[ $key ] ) ) );
			if ( filter_var( $ip, FILTER_VALIDATE_IP ) ) {
				return sanitize_text_field( $ip );
			}
		}
	}
	return 'unknown';
}

function flacso_remove_accents_local( $str ) {
	if ( function_exists( 'remove_accents' ) ) {
		return remove_accents( $str );
	}
	$norm = @iconv( 'UTF-8', 'ASCII//TRANSLIT', $str );
	return false !== $norm ? $norm : $str;
}

function flacso_intro_con_articulo( $titulo_programa ) {
	if ( ! $titulo_programa ) {
		return '';
	}
	$primera = trim( strtok( $titulo_programa, " \t\n\r\0\x0B" ) );
	$clave   = mb_strtolower( flacso_remove_accents_local( $primera ) );
	if ( in_array( $clave, array( 'maestria', 'especializacion' ), true ) ) {
		return 'la ' . $titulo_programa;
	}
	if ( in_array( $clave, array( 'diploma', 'diplomado' ), true ) ) {
		return 'el ' . $titulo_programa;
	}
	return '«' . $titulo_programa . '»';
}

/**
 * Determina si estamos renderizando el bloque en el editor.
 *
 * @return bool
 */
function flacso_consultas_is_block_preview() {
	if ( defined( 'REST_REQUEST' ) && REST_REQUEST ) {
		$context = isset( $_REQUEST['context'] ) ? sanitize_key( wp_unslash( $_REQUEST['context'] ) ) : '';
		return 'edit' === $context;
	}
	return false;
}

/**
 * Render del formulario (bloque + shortcode legacy).
 *
 * @param array $attributes Atributos del bloque/shortcode.
 *
 * @return string
 */
function flacso_consultas_render_form( $attributes = array() ) {
	if ( isset( $attributes['mostrarPreinscripcion'] ) ) {
		$attributes['mostrar_preinscripcion'] = $attributes['mostrarPreinscripcion'] ? 'true' : 'false';
	}

	$atts = shortcode_atts(
		array(
			'mostrar_preinscripcion' => 'true',
		),
		$attributes,
		'Consultas_Fase_1'
	);

	$is_preview = flacso_consultas_is_block_preview();

	if ( ! is_singular() && ! $is_preview ) {
		return '<p class="text-muted">El formulario de consultas solo está disponible en páginas de posgrados.</p>';
	}

	$id_pagina       = get_the_ID();
	$titulo_posgrado = get_the_title( $id_pagina );
	$url_actual      = get_permalink( $id_pagina );
	$gracias_url     = home_url( '/confirmacion-consulta/' );
	if ( ! $id_pagina ) {
		$id_pagina = 0;
	}
	if ( ! $titulo_posgrado ) {
		$titulo_posgrado = __( 'Posgrado destacado', 'flacso-consultas' );
	}
	if ( ! $url_actual ) {
		$url_actual = home_url( '/' );
	}
	$mostrar_pre     = wp_validate_boolean( $atts['mostrar_preinscripcion'] );

	if ( ! wp_script_is( 'jquery', 'enqueued' ) ) {
		wp_enqueue_script( 'jquery' );
	}

	ob_start();
	?>
	<div class="flacso-consultas-formulario shadow-sm mx-auto fade-in" id="form-consultas-container" role="region" aria-labelledby="consultas-title">
		<h3 id="consultas-title" class="mb-1"><strong>Solicitá información</strong></h3>
		<p class="mb-4 text-muted" style="line-height:1.5">
			Llená el formulario y recibí toda la información de cursada 2026.
		</p>

		<?php if ( ! FLACSO_CONSULTAS_HABILITADO ) : ?>
			<div class="alert alert-warning mb-0" role="alert" aria-live="polite">
				<p class="mb-2"><strong>⚠️ El formulario está temporalmente fuera de servicio.</strong></p>
				<p class="mb-0">Podés escribirnos a <a href="mailto:<?php echo esc_attr( FLACSO_EMAIL_CONTACTO ); ?>" class="alert-link"><strong><?php echo esc_html( FLACSO_EMAIL_CONTACTO ); ?></strong></a></p>
			</div>
		<?php else : ?>
		<form id="form-consultas" method="post" autocomplete="on" novalidate aria-describedby="form-ayuda" aria-live="polite">
			<span id="form-ayuda" class="visually-hidden">Todos los campos son obligatorios.</span>

			<input type="hidden" name="id_pagina" value="<?php echo esc_attr( $id_pagina ); ?>">
			<input type="hidden" name="titulo_posgrado" value="<?php echo esc_attr( $titulo_posgrado ); ?>">
			<input type="hidden" name="url_base" value="<?php echo esc_url( $url_actual ); ?>">
			<input type="hidden" name="url_gracias" value="<?php echo esc_url( $gracias_url ); ?>">
			<input type="hidden" name="url_referer" value="<?php echo esc_url( wp_get_referer() ?: $url_actual ); ?>">
			<?php if ( FLACSO_USE_NONCE ) { wp_nonce_field( 'flacso_consultas_form', 'flacso_nonce' ); } ?>

			<div class="form-floating mb-3">
				<input
					type="text" id="nombre" name="nombre" class="form-control"
					placeholder="Nombre" required maxlength="50"
					pattern="[A-Za-zÁáÉéÍíÓóÚúÑñ\s\-]{2,}"
					inputmode="text" autocomplete="given-name" aria-required="true" />
				<label for="nombre">Nombre *</label>
				<div class="invalid-feedback">Ingresá tu nombre (mínimo 2 letras)</div>
			</div>

			<div class="form-floating mb-3">
				<input
					type="text" id="apellido" name="apellido" class="form-control"
					placeholder="Apellido" required maxlength="50"
					pattern="[A-Za-zÁáÉéÍíÓóÚúÑñ\s\-]{2,}"
					inputmode="text" autocomplete="family-name" aria-required="true" />
				<label for="apellido">Apellido *</label>
				<div class="invalid-feedback">Ingresá tu apellido (mínimo 2 letras)</div>
			</div>

			<div class="form-floating mb-3">
				<select id="pais" name="pais" class="form-select" required aria-required="true" aria-describedby="paisHelp" autocomplete="country-name">
					<option value="" selected disabled>Seleccioná…</option>
					<option value="Uruguay">Uruguay</option>
					<option value="Argentina">Argentina</option>
					<option value="Bolivia">Bolivia</option>
					<option value="Brasil">Brasil</option>
					<option value="Chile">Chile</option>
					<option value="Colombia">Colombia</option>
					<option value="Costa Rica">Costa Rica</option>
					<option value="Cuba">Cuba</option>
					<option value="Ecuador">Ecuador</option>
					<option value="El Salvador">El Salvador</option>
					<option value="Guatemala">Guatemala</option>
					<option value="Haití">Haití</option>
					<option value="Honduras">Honduras</option>
					<option value="México">México</option>
					<option value="Nicaragua">Nicaragua</option>
					<option value="Panamá">Panamá</option>
					<option value="Paraguay">Paraguay</option>
					<option value="Perú">Perú</option>
					<option value="República Dominicana">República Dominicana</option>
					<option value="Venezuela">Venezuela</option>
					<option value="Otro">Otro</option>
				</select>
				<label for="pais">País de residencia *</label>
				<div id="paisHelp" class="invalid-feedback">Seleccioná tu país</div>
			</div>

			<div class="form-floating mb-3">
				<select id="nivel_academico" name="nivel_academico" class="form-select" required aria-required="true">
					<option value="" selected disabled>Seleccioná…</option>
					<option value="Título universitario">Título universitario</option>
					<option value="Título terciario no universitario">Título terciario no universitario</option>
					<option value="Estudiante en curso (aún no egresado/a)">Estudiante en curso (aún no egresado/a)</option>
					<option value="Sin formación terciaria">Sin formación terciaria</option>
				</select>
				<label for="nivel_academico">Nivel académico *</label>
				<div class="invalid-feedback">Seleccioná tu nivel académico</div>
			</div>

			<div class="form-floating mb-3">
				<input
					type="email" id="correo" name="correo" class="form-control"
					placeholder="correo@ejemplo.com" required maxlength="100"
					inputmode="email" autocomplete="email" aria-required="true" />
				<label for="correo">Correo electrónico *</label>
				<div class="invalid-feedback">Ingresá un correo válido</div>
			</div>

			<div class="form-floating mb-3">
				<input
					type="text" id="profesion" name="profesion" class="form-control"
					placeholder="Profesión" required maxlength="100"
					pattern="[A-Za-zÁáÉéÍíÓóÚúÑñ0-9\s\-\_\(\)\.]{2,}"
					inputmode="text" autocomplete="organization-title" aria-required="true" />
				<label for="profesion">Profesión *</label>
				<div class="invalid-feedback">Ingresá tu profesión (mínimo 2 caracteres)</div>
			</div>

			<button
				type="submit"
				id="btn-enviar"
				class="btn btn-primary w-100 py-2 mt-2 fw-bold rounded-pill"
				aria-label="Enviar consulta">
				<i class="bi bi-send-fill me-2" aria-hidden="true"></i>
				<span class="btn-text">Enviar consulta</span>
				<span class="btn-loading d-none">
					<span class="spinner-border spinner-border-sm me-2" role="status" aria-hidden="true"></span>
					Enviando...
				</span>
			</button>

			<div id="form-consultas-mensaje" class="alert d-none mt-3" role="alert" aria-live="polite"></div>
		</form>
		<?php endif; ?>
	</div>

	<?php if ( $mostrar_pre ) : ?>
	<div class="d-grid gap-2 mt-4">
		<a
		   href="<?php echo esc_url( trailingslashit( $url_actual ) . 'preinscripcion' ); ?>"
		   class="btn btn-preinsc btn-lg rounded-pill py-3 fw-bold"
		   aria-label="Ir a Preinscripción 2026">
		   <i class="bi bi-stars me-2" aria-hidden="true"></i>
		   Preinscripción 2026
		</a>
	</div>
	<?php endif; ?>

	<script>
	jQuery(function($) {
		const $form = $('#form-consultas');
		const $submitBtn = $('#btn-enviar');
		const $message = $('#form-consultas-mensaje');
		const programa = $form.find('[name="titulo_posgrado"]').val() || '';

		if ($form.length && typeof window.fbq === 'function') {
			window.fbq('track', 'ViewContent', {
				content_name: programa,
				content_category: 'solicitud_informacion',
				content_type: 'oferta_academica'
			});
		}

		$form.find('input, select').on('blur change', function() {
			this.checkValidity() ? $(this).removeClass('is-invalid') : $(this).addClass('is-invalid');
		});

		$form.on('submit', function(e) {
			e.preventDefault();
			if (!this.checkValidity()) {
				$(this).addClass('was-validated');
				showMessage('Revisá los campos marcados en rojo.', 'danger');
				return;
			}

			const formData = new FormData(this);
			const payload = new URLSearchParams(formData).toString();
			toggleLoading(true);

			$.ajax({
				url: '<?php echo esc_url( admin_url( 'admin-ajax.php' ) ); ?>',
				type: 'POST',
				data: payload + '&action=flacso_enviar_consulta',
				timeout: <?php echo ( FLACSO_WEBHOOK_TIMEOUT * 1000 ) + 5000; ?>,
				success: function() {
					sessionStorage.setItem('consultaNombreCompleto',
						$form.find('[name="nombre"]').val() + ' ' + $form.find('[name="apellido"]').val());
					sessionStorage.setItem('consultaPrograma', $form.find('[name="titulo_posgrado"]').val());
					sessionStorage.setItem('consultaOrigen', $form.find('[name="url_base"]').val());
					const pid = $form.find('[name="id_pagina"]').val();
					const gracias = $form.find('[name="url_gracias"]').val() || '<?php echo esc_js( home_url( '/confirmacion-consulta/' ) ); ?>';
					window.location.href = gracias + '?pid=' + encodeURIComponent(pid);
				},
				error: function() {
					sessionStorage.setItem('consultaNombreCompleto',
						$form.find('[name="nombre"]').val() + ' ' + $form.find('[name="apellido"]').val());
					sessionStorage.setItem('consultaPrograma', $form.find('[name="titulo_posgrado"]').val());
					sessionStorage.setItem('consultaOrigen', $form.find('[name="url_base"]').val());
					const pid = $form.find('[name="id_pagina"]').val();
					const gracias = $form.find('[name="url_gracias"]').val() || '<?php echo esc_js( home_url( '/confirmacion-consulta/' ) ); ?>';
					window.location.href = gracias + '?pid=' + encodeURIComponent(pid);
				},
				complete: function() { toggleLoading(false); }
			});
		});

		function toggleLoading(isLoading) {
			$submitBtn.prop('disabled', isLoading).attr('aria-busy', isLoading);
			$submitBtn.find('.btn-text').toggleClass('d-none', isLoading);
			$submitBtn.find('.btn-loading').toggleClass('d-none', !isLoading);
		}
		function showMessage(text, type) {
			$message.removeClass('d-none alert-success alert-danger alert-warning')
					.addClass('alert-' + type).text(text).focus();
			$('html, body').animate({ scrollTop: $message.offset().top - 100 }, 400);
		}
	});
	</script>

	<style>
	.flacso-consultas-formulario {
		padding: 1.5rem;
		background: var(--global-palette2);
		border-radius: 12px;
		border: 1px solid var(--global-palette4);
		font-family: var(--global-body-font-family);
		color: var(--global-palette3);
		max-width: 480px;
		width: 100%;
		margin: 0 auto 2rem;
		box-shadow: 0 4px 16px rgba(0,0,0,.08);
	}
	.flacso-consultas-formulario h3 { font-size: 1.25rem; margin-bottom: .25rem; }
	.flacso-consultas-formulario p  { font-size: .95rem; margin-bottom: 1.25rem; }
	.flacso-consultas-formulario .form-floating > .form-control,
	.flacso-consultas-formulario .form-floating > .form-select {
		height: calc(3.5rem + 2px);
		line-height: 1.25;
	}
	.flacso-consultas-formulario .form-floating > label {
		padding: .75rem .75rem;
		font-size: .9rem;
		color: var(--global-palette3);
		pointer-events: none;
	}
	.flacso-consultas-formulario .form-floating .form-select {
		padding-top: 1.625rem;
		padding-bottom: .625rem;
		background-position: right .75rem center;
	}
	.flacso-consultas-formulario .form-control,
	.flacso-consultas-formulario .form-select {
		border: 1px solid var(--global-palette6);
		border-radius: 10px;
		font-size: .95rem;
		transition: border-color .15s ease-in-out, box-shadow .15s ease-in-out;
	}
	.flacso-consultas-formulario .form-control:focus,
	.flacso-consultas-formulario .form-select:focus {
		border-color: var(--global-palette1);
		box-shadow: 0 0 0 .2rem rgba(13,110,253,.15);
	}
	.was-validated .form-control:invalid,
	.was-validated .form-select:invalid { border-color: #dc3545; }
	.was-validated .form-control:valid,
	.was-validated .form-select:valid   { border-color: #198754; }
	.invalid-feedback { font-size: .85rem; }
	@media (max-width: 576px) {
		.flacso-consultas-formulario { padding: 1.25rem; max-width: 100%; }
	}
	.btn.btn-primary, button.btn.btn-primary, a.btn.btn-primary {
	  background: var(--global-palette-btn-bg) !important;
	  border-color: var(--global-palette-btn-bg) !important;
	  color: var(--global-palette-btn-color, #fff) !important;
	  border-radius: 999px !important;
	  padding: .6rem 1rem !important;
	  font-weight: 600 !important;
	  text-decoration: none !important;
	  font-size: .95rem;
	}
	.btn.btn-primary:hover {
	  background: var(--global-palette-btn-bg-hover, var(--global-palette-btn-bg)) !important;
	  border-color: var(--global-palette-btn-bg-hover, var(--global-palette-btn-bg)) !important;
	  filter: brightness(.98);
	  transform: translateY(-1px);
	}
	.btn-preinsc {
		background: var(--global-palette-btn-bg) !important;
		border: 1px solid var(--global-palette-btn-bg) !important;
		color: #fff !important;
		box-shadow: 0 10px 20px rgba(36,129,56,.25);
		transition: transform .15s ease, box-shadow .15s ease, filter .15s ease;
	}
	.btn-preinsc:hover {
		background: var(--global-palette-btn-bg-hover, var(--global-palette-btn-bg)) !important;
		border-color: var(--global-palette-btn-bg-hover, var(--global-palette-btn-bg)) !important;
		transform: translateY(-2px);
		box-shadow: 0 14px 28px rgba(27,109,43,.32);
		filter: brightness(.98);
		color: #fff !important;
		text-decoration: none;
	}
	</style>
	<?php
	return ob_get_clean();
}

add_shortcode( 'Consultas_Fase_1', 'flacso_consultas_render_form' );

/**
 * Render del bloque/boton de preinscripcion standalone.
 *
 * @return string
 */
function flacso_consultas_render_preinscripcion_button() {
	$is_preview = flacso_consultas_is_block_preview();

	if ( ! is_singular() && ! $is_preview ) {
		return '<p class="text-muted">El boton de preinscripcion solo esta disponible en paginas de posgrados.</p>';
	}

	$id_pagina = get_the_ID();
	$url_actual = $id_pagina ? get_permalink( $id_pagina ) : home_url( '/' );
	$href_preinscripcion = trailingslashit( $url_actual ) . 'preinscripcion';

	ob_start();
	?>
	<div class="d-grid gap-2 mt-2">
		<a
			href="<?php echo esc_url( $href_preinscripcion ); ?>"
			class="btn btn-preinsc btn-lg rounded-pill py-3 fw-bold flacso-preinsc-standalone"
			aria-label="Ir a Preinscripcion 2026">
			<i class="bi bi-stars me-2" aria-hidden="true"></i>
			Preinscripcion 2026
		</a>
	</div>
	<style>
	.flacso-preinsc-standalone.btn-preinsc {
		background: var(--global-palette-btn-bg) !important;
		border: 1px solid var(--global-palette-btn-bg) !important;
		color: #fff !important;
		box-shadow: 0 10px 20px rgba(36,129,56,.25);
		transition: transform .15s ease, box-shadow .15s ease, filter .15s ease;
	}
	.flacso-preinsc-standalone.btn-preinsc:hover {
		background: var(--global-palette-btn-bg-hover, var(--global-palette-btn-bg)) !important;
		border-color: var(--global-palette-btn-bg-hover, var(--global-palette-btn-bg)) !important;
		transform: translateY(-2px);
		box-shadow: 0 14px 28px rgba(27,109,43,.32);
		filter: brightness(.98);
		color: #fff !important;
		text-decoration: none;
	}
	</style>
	<?php
	return ob_get_clean();
}

/**
 * AJAX handler.
 */
function flacso_enviar_consulta_func() {
	if ( FLACSO_USE_NONCE ) {
		if ( empty( $_POST['flacso_nonce'] ) || ! wp_verify_nonce( wp_unslash( $_POST['flacso_nonce'] ), 'flacso_consultas_form' ) ) {
			if ( ! FLACSO_RELAXED_MODE ) {
				wp_send_json_error( 'Error de seguridad. Recargá la página e intentá nuevamente.' );
			} else {
				error_log( '[FLACSO] Nonce inválido, pero RELAXED_MODE=on → seguimos.' );
			}
		}
	}
	if ( ! FLACSO_CONSULTAS_HABILITADO ) {
		wp_send_json_error( 'El formulario está temporalmente fuera de servicio.' );
	}

	$sanitize_deep = function( $value ) use ( &$sanitize_deep ) {
		if ( is_array( $value ) ) {
			$out = array();
			foreach ( $value as $k => $v ) {
				$out[ $k ] = $sanitize_deep( $v );
			}
			return $out;
		}
		return is_scalar( $value ) ? sanitize_text_field( $value ) : '';
	};

	$fields = array(
		'nombre',
		'apellido',
		'pais',
		'nivel_academico',
		'correo',
		'profesion',
		'id_pagina',
		'titulo_posgrado',
		'url_base',
		'url_referer',
	);
	$data = array();
	foreach ( $fields as $f ) {
		$val = isset( $_POST[ $f ] ) ? wp_unslash( $_POST[ $f ] ) : '';
		if ( 'id_pagina' === $f ) {
			$data[ $f ] = absint( $val );
		} elseif ( in_array( $f, array( 'url_base', 'url_referer' ), true ) ) {
			$data[ $f ] = esc_url_raw( $val );
		} elseif ( 'correo' === $f ) {
			$data[ $f ] = sanitize_email( $val );
		} else {
			$data[ $f ] = $sanitize_deep( $val );
		}
	}

	$data['fecha_envio'] = current_time( 'c' );
	$data['ip_usuario']  = flacso_get_user_ip();
	$data['user_agent']  = isset( $_SERVER['HTTP_USER_AGENT'] ) ? sanitize_text_field( wp_unslash( $_SERVER['HTTP_USER_AGENT'] ) ) : '';

	$campos_obligatorios = array( 'nombre', 'apellido', 'pais', 'nivel_academico', 'correo', 'profesion' );
	$faltan              = array();
	foreach ( $campos_obligatorios as $campo ) {
		if ( empty( trim( $data[ $campo ] ?? '' ) ) ) {
			$faltan[] = $campo;
		}
	}
	if ( ! empty( $faltan ) ) {
		error_log( '[FLACSO] Faltan campos obligatorios: ' . implode( ', ', $faltan ) );
		wp_send_json_error( 'Completá todos los campos obligatorios.' );
	}
	if ( ! empty( $data['correo'] ) && ! is_email( $data['correo'] ) ) {
		if ( ! FLACSO_RELAXED_MODE ) {
			wp_send_json_error( 'Correo inválido.' );
		}
		error_log( '[FLACSO] Correo inválido, RELAXED_MODE=on → seguimos.' );
	}

	if ( function_exists( 'fc_record_info_request_entry' ) ) {
		$stored_entry = fc_record_info_request_entry( array(
			'nombre'          => $data['nombre'],
			'apellido'        => $data['apellido'],
			'correo'          => $data['correo'],
			'pais'            => $data['pais'],
			'nivel_academico' => $data['nivel_academico'],
			'profesion'       => $data['profesion'],
			'programa_id'     => $data['id_pagina'],
			'programa_titulo' => $data['titulo_posgrado'],
			'url_base'        => $data['url_base'],
			'url_referer'     => $data['url_referer'],
			'ip'              => $data['ip_usuario'],
			'user_agent'      => $data['user_agent'],
			'fecha_envio'     => $data['fecha_envio'],
		) );
		if ( ! empty( $stored_entry['error'] ) ) {
			error_log( '[FLACSO] Error al guardar solicitud de información: ' . $stored_entry['error'] );
		}
	}

	$delivery = function_exists( 'fc_send_info_request_webhook' )
		? fc_send_info_request_webhook( $data )
		: [ 'ok' => false, 'error' => 'fc_send_info_request_webhook no disponible', 'code' => 0, 'body' => '' ];

	if ( empty( $delivery['ok'] ) ) {
		error_log(
			'[FLACSO] Error webhook solicitud de informacion: ' .
			( $delivery['error'] ?? 'desconocido' ) .
			' code=' . (int) ( $delivery['code'] ?? 0 ) .
			' body=' . substr( (string) ( $delivery['body'] ?? '' ), 0, 500 )
		);
		if ( FLACSO_RELAXED_MODE ) {
			wp_send_json_success(
				array(
					'note' => ( (int) ( $delivery['code'] ?? 0 ) > 0 ) ? 'http_code_relajado' : 'webhook_error_relajado',
					'code' => (int) ( $delivery['code'] ?? 0 ),
				)
			);
		}
		wp_send_json_error( 'No se pudo procesar la consulta. Intentá más tarde.' );
	}


        wp_send_json_success(
                array(
                        'note' => 'ok',
                )
        );
}
add_action( 'wp_ajax_flacso_enviar_consulta', 'flacso_enviar_consulta_func' );
add_action( 'wp_ajax_nopriv_flacso_enviar_consulta', 'flacso_enviar_consulta_func' );

/**
 * Página /gracias virtual.
 */
function flacso_render_gracias_virtual() {
        $path = wp_parse_url( $_SERVER['REQUEST_URI'], PHP_URL_PATH );
        if ( ! preg_match( '#/(gracias|confirmacion-consulta)/?$#', $path ) ) {
                return;
        }

        // Forzar estado 200 y marcar que no es 404 para que el tema no muestre título/estado erróneo.
        global $wp_query;
        if ( $wp_query ) {
                $wp_query->is_404 = false;
                $wp_query->is_singular = true;
                $wp_query->set_404( false );
        }

        $pid = isset( $_GET['pid'] ) ? absint( $_GET['pid'] ) : 0;

        $titulo_programa = $pid ? get_the_title( $pid ) : '';
        $thumb_url       = ( $pid && has_post_thumbnail( $pid ) )
                ? wp_get_attachment_image_url( get_post_thumbnail_id( $pid ), 'full' )
                : '';

        $volver_href = $pid ? get_permalink( $pid ) : home_url( '/' );

        $intro = $titulo_programa ? flacso_intro_con_articulo( $titulo_programa ) : '';

        status_header( 200 );
        nocache_headers();
        get_header();
        ?>
                <main class="flacso-gracias-container position-relative overflow-hidden">
                        <?php if ( $thumb_url ) : ?>
                        <div class="position-absolute top-0 start-0 w-100 h-100" aria-hidden="true" style="z-index:-1;">
				<div class="w-100 h-100" style="
					background-image: linear-gradient(rgba(0,0,0,.35), rgba(0,0,0,.35)), url('<?php echo esc_url( $thumb_url ); ?>');
					background-size: cover; background-position: center; filter: saturate(.95) brightness(.95);
				"></div>
			</div>
			<?php endif; ?>

			<div class="flacso-gracias-content container py-5 d-flex justify-content-center">
				<div class="bg-white rounded-4 shadow-lg p-4 p-md-5 fade-in" style="max-width: 820px;">
					<div class="flacso-gracias-icon display-5 text-success mb-2" aria-hidden="true">✓</div>
					<h1 class="mb-3" style="color: var(--global-palette1);">¡Tu consulta fue enviada!</h1>

					<div class="flacso-gracias-mensaje mb-3">
						<?php if ( $intro ) : ?>
							<p class="lead mb-2">
								<?php printf( 'Gracias por tu interés en %s de FLACSO Uruguay.', esc_html( $intro ) ); ?>
							</p>
						<?php else : ?>
							<p class="lead mb-2">Hemos recibido tu consulta correctamente.</p>
						<?php endif; ?>

						<p class="mb-2">En breve recibirás un correo con toda la información detallada.</p>
						<p class="mb-0">
							Si tenés alguna consulta adicional, podés escribirnos a
							<a href="mailto:<?php echo esc_attr( FLACSO_EMAIL_CONTACTO ); ?>" class="fw-semibold">
								<?php echo esc_html( FLACSO_EMAIL_CONTACTO ); ?>
							</a>.
						</p>
					</div>

					<div class="flacso-gracias-buttons d-grid gap-2 gap-sm-3 d-sm-flex justify-content-center align-items-stretch">
						<a class="btn btn-primary btn-lg rounded-pill mb-2 flacso-gracias-btn"
						   href="<?php echo esc_url( $volver_href ); ?>" aria-label="Volver al posgrado seleccionado">
							<i class="bi bi-arrow-left-circle me-2" aria-hidden="true"></i>
							Volver a «<?php echo esc_html( $titulo_programa ?: 'posgrado' ); ?>»
						</a>
						<a class="btn btn-outline-secondary btn-lg rounded-pill mb-2 flacso-gracias-btn"
						   href="<?php echo esc_url( home_url( '/formacion/' ) ); ?>" target="_self" rel="noopener">
							<i class="bi bi-layers me-2" aria-hidden="true"></i>
							Ver resto de la oferta
						</a>
					</div>
				</div>
			</div>
		</main>

		<script>
		document.addEventListener('DOMContentLoaded', function() {
			var pid = <?php echo (int) $pid; ?>;
			var programaMeta = <?php echo wp_json_encode( (string) $titulo_programa ); ?>;
			if (typeof window.fbq === 'function') {
				try {
					window.fbq('track', 'Lead', {
						content_name: programaMeta || '',
						content_category: 'solicitud_informacion',
						status: 'submitted'
					});
				} catch (e) {
					if (window.console && typeof window.console.warn === 'function') {
						console.warn('[Formulario Consultas] Error enviando Lead:', e);
					}
				}
			}
			if (!pid) {
				var programa = sessionStorage.getItem('consultaPrograma') || '';
				var intro    = programa ? (function(t){
					var primera = (t || '').trim().split(/\s+/)[0] || '';
					var clave   = primera.normalize('NFD').replace(/[\u0300-\u036f]/g,'').toLowerCase();
					if (clave==='maestria' || clave==='especializacion') return 'la ' + t;
					if (clave==='diploma' || clave==='diplomado') return 'el ' + t;
					return '«' + t + '»';
				})(programa) : '';
				var cont = document.querySelector('.flacso-gracias-mensaje');
				if (cont && intro) {
					cont.innerHTML =
						'<p class="lead mb-2">Gracias por tu interés en ' + intro.replace(/</g,'&lt;') + ' de FLACSO Uruguay.</p>' +
						'<p class="mb-2">En breve recibirás un correo con toda la información detallada.</p>' +
						'<p class="mb-0">Si tenés alguna consulta adicional, podés escribirnos a ' +
						'<a class="fw-semibold" href="mailto:<?php echo esc_js( FLACSO_EMAIL_CONTACTO ); ?>">' +
						'<?php echo esc_js( FLACSO_EMAIL_CONTACTO ); ?>' +
						'</a>.</p>';
				}
			}
			setTimeout(function() {
				sessionStorage.removeItem('consultaNombreCompleto');
				sessionStorage.removeItem('consultaPrograma');
				sessionStorage.removeItem('consultaOrigen');
			}, 3000);
		});
		</script>

		<style>
		.flacso-gracias-container { min-height: 80vh; background: var(--global-palette7); }
		.flacso-gracias-buttons .flacso-gracias-btn {
			flex: 1 1 0;
			min-width: 240px;
			max-width: 360px;
			display: inline-flex;
			align-items: center;
			justify-content: center;
			text-align: center;
			white-space: normal;
		}
		@media (max-width: 575.98px) {
			.flacso-gracias-buttons .flacso-gracias-btn { width: 100%; }
		}
                </style>
                <?php
                get_footer();
                exit;
}
// Ejecutar temprano para sobreescribir cualquier 404 del tema
add_action( 'template_redirect', 'flacso_render_gracias_virtual', 1 );

/**
 * Inyecta nonce en cabecera si corresponde.
 */
add_action(
	'wp_head',
	function () {
		if ( is_singular() && FLACSO_USE_NONCE ) {
			echo '<script type="text/javascript">var flacso_ajax_nonce = "' .
				esc_js( wp_create_nonce( 'flacso_consultas_form' ) ) . '";</script>';
		}
	}
);

/**
 * Registro del bloque Gutenberg.
 */
function flacso_consultas_register_block() {
	$asset_path = trailingslashit( FLACSO_MAIN_PAGE_MODULE_PATH ) . 'assets/js/flacso-consultas-block.js';
	$script_version = file_exists( $asset_path ) ? filemtime( $asset_path ) : FLACSO_CONSULTAS_VERSION;
	wp_register_script(
		'flacso-consultas-block-editor',
		trailingslashit( FLACSO_MAIN_PAGE_MODULE_URL ) . 'assets/js/flacso-consultas-block.js',
		array( 'wp-blocks', 'wp-i18n', 'wp-element', 'wp-components', 'wp-block-editor', 'wp-server-side-render' ),
		$script_version,
		true
	);

	register_block_type(
		'flacso-uruguay/consultas-form',
		array(
			'api_version'      => 2,
			'render_callback'  => 'flacso_consultas_render_form',
			'editor_script'    => 'flacso-consultas-block-editor',
			'category'         => 'flacso-uruguay',
			'attributes'       => array(
				'mostrarPreinscripcion' => array(
					'type'    => 'boolean',
					'default' => true,
				),
			),
			'supports'        => array(
				'align' => false,
			),
		)
	);

	register_block_type(
		'flacso-uruguay/preinscripcion-button',
		array(
			'api_version'      => 2,
			'render_callback'  => 'flacso_consultas_render_preinscripcion_button',
			'editor_script'    => 'flacso-consultas-block-editor',
			'category'         => 'flacso-uruguay',
			'supports'         => array(
				'align' => false,
			),
		)
	);
}
add_action( 'init', 'flacso_consultas_register_block' );


