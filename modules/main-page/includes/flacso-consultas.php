<?php
/**
 * Merged Plugin:     FLACSO Consultas (Bloque Gutenberg)
 * Plugin URI:        https://www.flacso.edu.uy/
 * Description:       Formulario de consultas FLACSO con integración AJAX, página virtual /gracias y bloque Gutenberg.
 * Version:           1.1.3
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
	define( 'FLACSO_CONSULTAS_VERSION', '1.1.3' );
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
			'post_id'                => 0,
			'titulo_posgrado'        => '',
			'url_base'               => '',
			'url_gracias'            => '',
			'form_variant'           => '',
			'intro_text'             => '',
		),
		$attributes,
		'Consultas_Fase_1'
	);

	$is_preview = flacso_consultas_is_block_preview();
	$explicit_post_id = absint( $atts['post_id'] );

	if ( ! is_singular() && ! $is_preview && ! $explicit_post_id ) {
		return '<p class="text-muted">El formulario de consultas solo está disponible en páginas de posgrados.</p>';
	}

	$id_pagina       = $explicit_post_id ?: get_the_ID();
	$titulo_posgrado = trim( (string) $atts['titulo_posgrado'] );
	if ( $titulo_posgrado === '' ) {
		$titulo_posgrado = get_the_title( $id_pagina );
	}
	$url_actual = esc_url_raw( (string) $atts['url_base'] );
	if ( $url_actual === '' ) {
		$url_actual = get_permalink( $id_pagina );
	}
	$gracias_url = esc_url_raw( (string) $atts['url_gracias'] );
	if ( $gracias_url === '' ) {
		$gracias_url = trailingslashit( $url_actual ) . 'gracias/';
	}
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
	$form_variant    = sanitize_html_class( (string) $atts['form_variant'] );
	$intro_text      = trim( (string) $atts['intro_text'] );
	if ( $intro_text === '' ) {
		$intro_text = __( 'Llená el formulario y recibí toda la información de cursada 2026.', 'flacso-consultas' );
	}

	// ADAPTACIÓN CPT: Solo mostrar si las inscripciones están abiertas
	if ( $mostrar_pre && get_post_type($id_pagina) === 'oferta-academica' ) {
		$abiertas = get_post_meta($id_pagina, 'inscripciones_abiertas', true);
		$mostrar_pre = ($abiertas === '1' || $abiertas === 'true' || $abiertas === true || $abiertas === 1);
	}

	if ( ! wp_script_is( 'jquery', 'enqueued' ) ) {
		wp_enqueue_script( 'jquery' );
	}

	ob_start();
	?>
	<div class="flacso-consultas-formulario shadow-sm mx-auto fade-in <?php echo $form_variant ? 'flacso-consultas-formulario--' . esc_attr( $form_variant ) : ''; ?>" id="form-consultas-container" role="region" aria-labelledby="consultas-title">
		<h3 id="consultas-title" class="mb-1"><strong>Solicitá información</strong></h3>
		<p class="mb-4 text-muted" style="line-height:1.5">
			<?php echo esc_html( $intro_text ); ?>
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
			<?php if ( function_exists( 'fc_render_campaign_attribution_hidden_fields' ) ) { fc_render_campaign_attribution_hidden_fields( fc_get_current_request_url(), wp_get_referer() ?: '' ); } ?>
			<?php if ( FLACSO_USE_NONCE ) { wp_nonce_field( 'flacso_consultas_form', 'flacso_nonce' ); } ?>

			<div class="form-floating mb-3">
				<input
					type="text" id="nombre" name="nombre" class="form-control"
					placeholder="Nombre" required minlength="2" maxlength="50"
					inputmode="text" autocomplete="given-name" aria-required="true" />
				<label for="nombre">Nombre *</label>
				<div class="invalid-feedback">Ingresá tu nombre (mínimo 2 letras)</div>
			</div>

			<div class="form-floating mb-3">
				<input
					type="text" id="apellido" name="apellido" class="form-control"
					placeholder="Apellido" required minlength="2" maxlength="50"
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
					placeholder="Profesión" required minlength="2" maxlength="100"
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
		   aria-label="Ir a Preinscripción 2026"
		   onclick="if(typeof window.flacsoMetaTrack === 'function'){ window.flacsoMetaTrack('InitiateCheckout', { content_name: '<?php echo esc_js($titulo_posgrado); ?>', content_category: 'oferta_academica' }); }">
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
			let infoRequestFormViewTracked = false;
			const metaSessionStorageKey = 'consultaMetaUserData';
			const metaLeadPendingStorageKey = 'consultaMetaLeadPending';
			const buildGraciasUrl = function(baseUrl, pid) {
				const redirectUrl = new URL(baseUrl, window.location.href);
				const currentParams = new URLSearchParams(window.location.search);
				const testEventCode = (currentParams.get('test_event_code') || '').trim();

				redirectUrl.searchParams.set('pid', String(pid || ''));
				if (testEventCode) {
					redirectUrl.searchParams.set('test_event_code', testEventCode);
				}

				return redirectUrl.toString();
			};
			const hashForMeta = async function(value) {
				const str = String(value || '').trim().toLowerCase();
				if (!str || !window.crypto || !window.crypto.subtle || typeof window.TextEncoder !== 'function') {
					return '';
				}

				try {
					const buf = await window.crypto.subtle.digest('SHA-256', new TextEncoder().encode(str));
					return Array.from(new Uint8Array(buf)).map(function(b) {
						return b.toString(16).padStart(2, '0');
					}).join('');
				} catch (e) {
					return '';
				}
			};
			const buildMetaUserData = async function() {
				const nombre = ($form.find('[name="nombre"]').val() || '').trim();
				const apellido = ($form.find('[name="apellido"]').val() || '').trim();
				const correo = ($form.find('[name="correo"]').val() || '').trim();
				const pais = ($form.find('[name="pais"]').val() || '').trim();

				const [emHash, fnHash, lnHash, countryHash] = await Promise.all([
					hashForMeta(correo),
					hashForMeta(nombre),
					hashForMeta(apellido),
					hashForMeta(pais)
				]);

				const payload = {};
				if (emHash) payload.em = emHash;
				if (fnHash) payload.fn = fnHash;
				if (lnHash) payload.ln = lnHash;
				if (countryHash) payload.country = countryHash;

				return payload;
			};
			const persistMetaUserData = function(userData) {
				if (!userData || typeof userData !== 'object') {
					return;
				}

				try {
					sessionStorage.setItem(metaSessionStorageKey, JSON.stringify(userData));
				} catch (e) {}
			};
			const trackInfoRequestFormView = function() {
				if (infoRequestFormViewTracked || !$form.length || typeof window.flacsoMetaTrackCustom !== 'function') {
					return;
				}

				infoRequestFormViewTracked = true;

				try {
					window.flacsoMetaTrackCustom('InfoRequestFormView', {
						content_name: programa,
						content_category: 'solicitud_informacion',
						content_type: 'oferta_academica',
						flacso_stage: 'formulario_visible'
					});
				} catch (e) {}
			};

		if ($form.length) {
			const formContainer = document.getElementById('form-consultas-container') || $form.get(0);

			if (formContainer && 'IntersectionObserver' in window) {
				const observer = new IntersectionObserver(function(entries) {
					entries.forEach(function(entry) {
						if (!entry.isIntersecting || entry.intersectionRatio < 0.35) {
							return;
						}

						trackInfoRequestFormView();
						observer.disconnect();
					});
				}, {
					threshold: [0.35]
				});

				observer.observe(formContainer);
			} else {
				trackInfoRequestFormView();
			}
		}

		const normalizeField = function(field) {
			if (!field || !field.name || field.type === 'hidden') {
				return;
			}

			if (field.tagName === 'SELECT') {
				return;
			}

			const trimmed = String(field.value || '').trim().replace(/\s+/g, ' ');
			field.value = field.type === 'email' ? trimmed.toLowerCase() : trimmed;
		};

		const validateField = function(field) {
			if (!field || field.type === 'hidden') {
				return true;
			}

			const valid = field.checkValidity();
			$(field).toggleClass('is-invalid', !valid).toggleClass('is-valid', valid && Boolean(field.value));
			return valid;
		};

		$form.find('input, select').on('input change blur', function(event) {
			if (event.type === 'blur' || event.type === 'change') {
				normalizeField(this);
			}
			validateField(this);
		});

		$form.on('submit', async function(e) {
			e.preventDefault();
			$form.find('input, select').each(function() {
				normalizeField(this);
				validateField(this);
			});

			if (!this.checkValidity()) {
				$(this).addClass('was-validated');
				const firstInvalid = this.querySelector(':invalid');
				if (firstInvalid && typeof firstInvalid.focus === 'function') {
					try {
						firstInvalid.focus({ preventScroll: true });
					} catch (err) {
						firstInvalid.focus();
					}
					firstInvalid.scrollIntoView({ block: 'center', behavior: 'smooth' });
				}
				showMessage('Revisá los campos marcados en rojo.', 'danger', false);
				return;
			}

			const metaUserData = await buildMetaUserData();
			persistMetaUserData(metaUserData);

			const formData = new FormData(this);
			const payload = new URLSearchParams(formData).toString();
			toggleLoading(true);

			$.ajax({
				url: '<?php echo esc_url( admin_url( 'admin-ajax.php' ) ); ?>',
				type: 'POST',
				data: payload + '&action=flacso_enviar_consulta',
				timeout: <?php echo ( FLACSO_WEBHOOK_TIMEOUT * 1000 ) + 5000; ?>,
				success: function(response) {
					// Solo redirigir a /gracias si la respuesta confirma envío correcto.
					// No disparar Lead si el servidor reportó error.
					if (!response || !response.success) {
						showMessage('No se pudo enviar la consulta. Intentá más tarde.', 'danger');
						return;
					}
					sessionStorage.setItem('consultaNombreCompleto',
						$form.find('[name="nombre"]').val() + ' ' + $form.find('[name="apellido"]').val());
					sessionStorage.setItem('consultaPrograma', $form.find('[name="titulo_posgrado"]').val());
					sessionStorage.setItem('consultaOrigen', $form.find('[name="url_base"]').val());
					const pid = $form.find('[name="id_pagina"]').val();
					const urlBase = $form.find('[name="url_base"]').val();
					const gracias = $form.find('[name="url_gracias"]').val() || (urlBase ? urlBase.replace(/\/$/, '') + '/gracias/' : '<?php echo esc_js( home_url( '/gracias/' ) ); ?>');
					try {
						sessionStorage.setItem(metaLeadPendingStorageKey, JSON.stringify({
							pid: String(pid || ''),
							tipo: (new URL(gracias, window.location.href)).searchParams.get('tipo') || 'consulta',
							createdAt: Date.now()
						}));
					} catch (e) {}
					window.location.href = buildGraciasUrl(gracias, pid);
				},
				error: function() {
					// No redirigir a /gracias en caso de error AJAX.
					// No disparar Lead si el envío falló.
					showMessage('No se pudo enviar la consulta. Revisá tu conexión e intentá de nuevo.', 'danger');
				},
				complete: function() { toggleLoading(false); }
			});
		});

		function toggleLoading(isLoading) {
			$submitBtn.prop('disabled', isLoading).attr('aria-busy', isLoading);
			$submitBtn.find('.btn-text').toggleClass('d-none', isLoading);
			$submitBtn.find('.btn-loading').toggleClass('d-none', !isLoading);
		}
		function showMessage(text, type, shouldScroll) {
			shouldScroll = shouldScroll !== false;
			$message.removeClass('d-none alert-success alert-danger alert-warning')
					.addClass('alert-' + type).text(text).trigger('focus');
			if (shouldScroll) {
				$('html, body').animate({ scrollTop: $message.offset().top - 100 }, 400);
			}
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
		position: sticky;
		bottom: 1.5rem;
		z-index: 100;
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
		return '<p class="text-muted">El botón de preinscripción solo está disponible en páginas de posgrados.</p>';
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
			aria-label="Ir a Preinscripción 2026"
			onclick="if(typeof window.flacsoMetaTrack === 'function'){ window.flacsoMetaTrack('InitiateCheckout', { content_name: '<?php echo esc_js(get_the_title($id_pagina)); ?>', content_category: 'oferta_academica' }); }">
			<i class="bi bi-stars me-2" aria-hidden="true"></i>
			Preinscripción 2026
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
		'campaign_provider',
		'campaign_source',
		'campaign_medium',
		'campaign_name',
		'campaign_external_id',
		'campaign_content',
		'campaign_term',
		'utm_source',
		'utm_medium',
		'utm_campaign',
		'utm_id',
		'utm_content',
		'utm_term',
		'landing_url',
		'referrer_url',
	);
	$data = array();
	foreach ( $fields as $f ) {
		$val = isset( $_POST[ $f ] ) ? wp_unslash( $_POST[ $f ] ) : '';
		if ( 'id_pagina' === $f ) {
			$data[ $f ] = absint( $val );
		} elseif ( in_array( $f, array( 'url_base', 'url_referer', 'landing_url', 'referrer_url' ), true ) ) {
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
	if ( function_exists( 'fc_enrich_info_request_program_context' ) ) {
		$data = fc_enrich_info_request_program_context( $data );
	}

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
	if ( empty( $data['id_pagina'] ) ) {
		error_log( '[FLACSO] Solicitud de información sin ID de oferta académica.' );
		wp_send_json_error( 'No se pudo identificar la oferta académica.' );
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
 * Página virtual /solicitar-info/ para cada oferta académica.
 */
function flacso_consultas_resolve_offer_from_request_path( string $path ) {
	$path = trim( $path, '/' );
	if ( $path === '' || ! preg_match( '#^formacion/[^/]+/([^/]+)/solicitar-info/?$#', $path, $matches ) ) {
		return null;
	}

	$slug = sanitize_title( $matches[1] );
	if ( $slug === '' ) {
		return null;
	}

	$post = get_page_by_path( $slug, OBJECT, 'oferta-academica' );
	if ( $post instanceof WP_Post && $post->post_status === 'publish' ) {
		return $post;
	}

	return null;
}

function flacso_render_solicitar_info_virtual() {
	$path = wp_parse_url( $_SERVER['REQUEST_URI'] ?? '', PHP_URL_PATH );
	if ( ! is_string( $path ) || $path === '' ) {
		return;
	}

	$oferta = flacso_consultas_resolve_offer_from_request_path( $path );
	if ( ! ( $oferta instanceof WP_Post ) ) {
		return;
	}

	global $wp_query, $post;
	if ( $wp_query ) {
		$wp_query->is_404 = false;
		$wp_query->is_singular = true;
		$wp_query->is_page = false;
		$wp_query->is_archive = false;
		$wp_query->is_home = false;
		$wp_query->is_posts_page = false;
		$wp_query->queried_object = $oferta;
		$wp_query->queried_object_id = (int) $oferta->ID;
		$wp_query->post = $oferta;
		$wp_query->posts = array( $oferta );
		$wp_query->post_count = 1;
		$wp_query->set_404( false );
	}

	$post = $oferta;
	setup_postdata( $post );

	$oferta_id = (int) $oferta->ID;
	$oferta_title = get_the_title( $oferta_id );
	$oferta_url = get_permalink( $oferta_id );
	$current_url = home_url( '/' . trim( $path, '/' ) . '/' );
	$gracias_url = add_query_arg( 'tipo', 'solicitud_informacion', trailingslashit( $oferta_url ) . 'gracias/' );
	$thumb_url = get_the_post_thumbnail_url( $oferta_id, 'full' );
	$page_title = sprintf( 'Solicitá información - %s', $oferta_title );
	$intro_text = sprintf(
		'Completá el formulario y recibí información sobre cursada, inscripción y financiación de %s.',
		$oferta_title
	);

	status_header( 200 );
	nocache_headers();
	flacso_consultas_apply_virtual_page_title( $page_title );
	add_filter(
		'body_class',
		static function ( $classes ) {
			$classes[] = 'flacso-solicitar-info-template';
			return $classes;
		},
		20
	);

	get_header();
	?>
	<header class="flacso-solicitar-info-brand" aria-label="<?php echo esc_attr( get_bloginfo( 'name' ) ); ?>">
		<a class="flacso-solicitar-info-brand__link" href="<?php echo esc_url( home_url( '/' ) ); ?>" aria-label="<?php echo esc_attr( get_bloginfo( 'name' ) ); ?>">
			<?php
			$custom_logo_id = (int) get_theme_mod( 'custom_logo' );
			if ( $custom_logo_id ) {
				echo wp_get_attachment_image(
					$custom_logo_id,
					'medium',
					false,
					array(
						'class'   => 'flacso-solicitar-info-brand__logo',
						'loading' => 'eager',
						'alt'     => get_bloginfo( 'name' ),
					)
				);
			} else {
				echo '<span class="flacso-solicitar-info-brand__text">FLACSO Uruguay</span>';
			}
			?>
		</a>
	</header>
	<main class="flacso-solicitar-info-page" aria-labelledby="flacso-solicitar-info-title">
		<h1 id="flacso-solicitar-info-title" class="screen-reader-text"><?php echo esc_html( $page_title ); ?></h1>
		<?php if ( $thumb_url ) : ?>
			<div class="flacso-solicitar-info-page__bg" aria-hidden="true" style="background-image: linear-gradient(rgba(8, 25, 58, .66), rgba(8, 25, 58, .72)), url('<?php echo esc_url( $thumb_url ); ?>');"></div>
		<?php endif; ?>

		<section class="flacso-solicitar-info-page__shell">
			<div class="flacso-solicitar-info-page__form">
				<?php
				echo flacso_consultas_render_form(
					array(
						'mostrar_preinscripcion' => false,
						'post_id'                => $oferta_id,
						'titulo_posgrado'        => $oferta_title,
						'url_base'               => $oferta_url,
						'url_gracias'            => $gracias_url,
						'form_variant'           => 'solicitar-info',
						'intro_text'             => $intro_text,
					)
				);
				?>
			</div>
		</section>
	</main>

	<style>
	body.flacso-solicitar-info-template #masthead,
	body.flacso-solicitar-info-template .site-header,
	body.flacso-solicitar-info-template .flacso-nav-announcement,
	body.flacso-solicitar-info-template .flacso-banner-full-clickable,
	body.flacso-solicitar-info-template #colophon,
	body.flacso-solicitar-info-template .site-footer {
		display: none !important;
	}

	body.flacso-solicitar-info-template #inner-wrap,
	body.flacso-solicitar-info-template .content-area,
	body.flacso-solicitar-info-template .site-main {
		margin-top: 0 !important;
		padding-top: 0 !important;
	}

	.flacso-solicitar-info-brand {
		position: relative;
		z-index: 3;
		background: #fff;
		border-bottom: 1px solid rgba(8, 24, 50, .08);
	}

	.flacso-solicitar-info-brand__link {
		display: flex;
		align-items: center;
		width: min(100% - 32px, 1120px);
		min-height: 70px;
		margin: 0 auto;
		text-decoration: none;
	}

	.flacso-solicitar-info-brand__logo {
		display: block;
		width: auto;
		max-width: min(178px, 54vw);
		max-height: 46px;
		object-fit: contain;
	}

	.flacso-solicitar-info-brand__text {
		color: #10346e;
		font-size: 1.35rem;
		font-weight: 800;
		letter-spacing: 0;
	}

	.flacso-solicitar-info-page {
		position: relative;
		min-height: calc(100svh - 70px);
		background: #0d2347;
		overflow: visible;
	}

	.flacso-solicitar-info-page__bg {
		position: fixed;
		inset: 0;
		z-index: 0;
		background-position: center;
		background-size: cover;
		filter: saturate(.9);
	}

	.flacso-solicitar-info-page__shell {
		position: relative;
		z-index: 1;
		width: min(100% - 32px, 1120px);
		margin: 0 auto;
		padding: clamp(18px, 4vw, 34px) 0 clamp(28px, 6vw, 56px);
		display: flex;
		justify-content: center;
		align-items: flex-start;
	}

	.flacso-solicitar-info-page__form {
		width: min(100%, 526px);
		margin-inline: auto;
	}

	.flacso-consultas-formulario--solicitar-info {
		max-width: 100%;
		margin: 0;
		padding: clamp(22px, 3.4vw, 30px);
		border: 0;
		border-radius: 24px;
		background: linear-gradient(145deg, #ffcf07 0%, #ffd91f 64%, #fff0a3 100%);
		box-shadow: 0 24px 60px rgba(3, 12, 31, .26);
		color: #071832;
	}

	.flacso-consultas-formulario--solicitar-info,
	.flacso-consultas-formulario--solicitar-info * {
		box-sizing: border-box;
	}

	.flacso-consultas-formulario--solicitar-info .visually-hidden {
		position: absolute !important;
		width: 1px !important;
		height: 1px !important;
		padding: 0 !important;
		margin: -1px !important;
		overflow: hidden !important;
		clip: rect(0, 0, 0, 0) !important;
		white-space: nowrap !important;
		border: 0 !important;
	}

	.flacso-consultas-formulario--solicitar-info h3 {
		margin: 0 0 10px;
		color: #071832;
		font-size: clamp(1.85rem, 5vw, 2.45rem);
		line-height: 1.03;
		letter-spacing: 0;
	}

	.flacso-consultas-formulario--solicitar-info p {
		color: #071832 !important;
		font-size: clamp(.98rem, 2.5vw, 1.13rem);
		line-height: 1.42 !important;
		margin-bottom: 18px !important;
	}

	.flacso-consultas-formulario--solicitar-info .form-floating {
		position: relative;
		display: block;
		width: 100%;
		margin-bottom: 14px !important;
	}

	.flacso-consultas-formulario--solicitar-info .form-floating > .form-control,
	.flacso-consultas-formulario--solicitar-info .form-floating > .form-select {
		display: block;
		width: 100% !important;
		min-width: 0;
		height: 66px !important;
		min-height: 66px;
		margin: 0;
		padding: 2.15rem 1rem .62rem !important;
		border: 1px solid rgba(8, 24, 50, .12);
		border-radius: 14px;
		background-color: #fff;
		color: #071832;
		font-size: 1.08rem;
		line-height: 1.22;
		box-shadow: 0 4px 14px rgba(8, 24, 50, .06);
	}

	.flacso-consultas-formulario--solicitar-info .form-floating > .form-control::placeholder {
		color: transparent;
	}

	.flacso-consultas-formulario--solicitar-info .form-floating > .form-select {
		-webkit-appearance: none !important;
		-moz-appearance: none !important;
		appearance: none !important;
		padding-right: 3.1rem !important;
		background-color: #fff;
		background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='18' height='18' viewBox='0 0 24 24' fill='none' stroke='%23071832' stroke-width='2.4' stroke-linecap='round' stroke-linejoin='round'%3E%3Cpath d='m6 9 6 6 6-6'/%3E%3C/svg%3E") !important;
		background-repeat: no-repeat !important;
		background-position: right 1rem center !important;
		background-size: 18px 18px !important;
	}

	.flacso-consultas-formulario--solicitar-info .form-floating > label {
		position: absolute;
		top: .52rem;
		left: 1rem;
		z-index: 2;
		display: block;
		width: calc(100% - 2rem);
		height: auto;
		margin: 0;
		padding: 0;
		font-size: 1rem;
		line-height: 1.1;
		color: #071832;
		pointer-events: none;
		transform: none !important;
		opacity: 1;
	}

	.flacso-consultas-formulario--solicitar-info .invalid-feedback {
		display: none;
		margin-top: .4rem;
		color: #7a2700;
		font-size: .9rem;
		line-height: 1.25;
	}

	.flacso-consultas-formulario--solicitar-info .was-validated .form-control:invalid ~ .invalid-feedback,
	.flacso-consultas-formulario--solicitar-info .was-validated .form-select:invalid ~ .invalid-feedback,
	.flacso-consultas-formulario--solicitar-info .form-control.is-invalid ~ .invalid-feedback,
	.flacso-consultas-formulario--solicitar-info .form-select.is-invalid ~ .invalid-feedback {
		display: block;
	}

	.flacso-consultas-formulario--solicitar-info .form-control.is-valid,
	.flacso-consultas-formulario--solicitar-info .form-select.is-valid,
	.flacso-consultas-formulario--solicitar-info .was-validated .form-control:valid,
	.flacso-consultas-formulario--solicitar-info .was-validated .form-select:valid {
		border-color: rgba(35, 136, 58, .55) !important;
	}

	.flacso-consultas-formulario--solicitar-info .form-control.is-invalid,
	.flacso-consultas-formulario--solicitar-info .form-select.is-invalid,
	.flacso-consultas-formulario--solicitar-info .was-validated .form-control:invalid,
	.flacso-consultas-formulario--solicitar-info .was-validated .form-select:invalid {
		border-color: #b54708 !important;
		box-shadow: 0 0 0 .16rem rgba(181, 71, 8, .14);
	}

	.flacso-consultas-formulario--solicitar-info .btn.btn-primary {
		display: flex !important;
		align-items: center;
		justify-content: center;
		gap: .55rem;
		width: 100% !important;
		min-height: 58px;
		margin-top: 4px !important;
		padding: .9rem 1.25rem !important;
		border-radius: 999px !important;
		background: #23883a !important;
		border-color: #23883a !important;
		color: #fff !important;
		font-size: 1.05rem;
		font-weight: 800 !important;
		text-transform: uppercase;
		letter-spacing: .03em;
		box-shadow: none;
		white-space: normal;
		text-align: center;
	}

	.flacso-consultas-formulario--solicitar-info .btn.btn-primary:hover,
	.flacso-consultas-formulario--solicitar-info .btn.btn-primary:focus-visible {
		background: #1d7431 !important;
		border-color: #1d7431 !important;
	}

	.flacso-consultas-formulario--solicitar-info .btn.btn-primary .d-none {
		display: none !important;
	}

	.flacso-consultas-formulario--solicitar-info .btn.btn-primary .btn-text,
	.flacso-consultas-formulario--solicitar-info .btn.btn-primary .btn-loading {
		align-items: center;
		justify-content: center;
		gap: .55rem;
		min-width: 0;
	}

	.flacso-consultas-formulario--solicitar-info .btn.btn-primary .btn-loading:not(.d-none) {
		display: inline-flex;
	}

	.flacso-consultas-formulario--solicitar-info .btn.btn-primary i {
		flex: 0 0 auto;
		margin: 0 !important;
		font-size: 1.05em;
	}

	@media (max-width: 767px) {
		.flacso-solicitar-info-brand__link {
			min-height: 58px;
			width: min(100% - 24px, 1120px);
		}

		.flacso-solicitar-info-brand__logo {
			max-width: min(146px, 58vw);
			max-height: 38px;
		}

		.flacso-solicitar-info-page {
			min-height: calc(100svh - 58px);
		}

		.flacso-solicitar-info-page__shell {
			width: min(100% - 20px, 1120px);
			padding: 14px 0 28px;
		}

		.flacso-consultas-formulario--solicitar-info {
			border-radius: 20px;
			padding: 20px;
		}

		.flacso-consultas-formulario--solicitar-info h3 {
			font-size: clamp(1.75rem, 9vw, 2.25rem);
		}

		.flacso-consultas-formulario--solicitar-info .form-floating > .form-control,
		.flacso-consultas-formulario--solicitar-info .form-floating > .form-select {
			height: 64px !important;
			min-height: 64px;
			font-size: 1rem;
		}
	}

	</style>
	<?php
	get_footer();
	wp_reset_postdata();
	exit;
}
add_action( 'template_redirect', 'flacso_render_solicitar_info_virtual', 0 );

/**
 * Página /gracias virtual.
 */
function flacso_consultas_apply_virtual_page_title( $base_title ) {
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
                $wp_query->is_page = true;
                $wp_query->is_archive = false;
                $wp_query->is_home = false;
                $wp_query->is_posts_page = false;
                $wp_query->set_404( false );
        }

        $pid = isset( $_GET['pid'] ) ? absint( $_GET['pid'] ) : 0;

        $titulo_programa = $pid ? get_the_title( $pid ) : '';
        $thumb_url       = ( $pid && has_post_thumbnail( $pid ) )
                ? wp_get_attachment_image_url( get_post_thumbnail_id( $pid ), 'full' )
                : '';

        $volver_href = $pid ? get_permalink( $pid ) : home_url( '/' );

        $tipo = isset( $_GET['tipo'] ) ? sanitize_text_field( $_GET['tipo'] ) : 'consulta';
        // Ensure backward compatibility or expected types
        if ( $tipo === 'preinscripcion' ) {
            $tipo = 'preinscripcion_oferta';
        }

        $intro = $titulo_programa ? flacso_intro_con_articulo( $titulo_programa ) : '';
        
        // Define page title based on type
        if ( $tipo === 'preinscripcion_oferta' || $tipo === 'preinscripcion_seminario' ) {
            $page_title = $titulo_programa ? sprintf( 'Preinscripción enviada - %s', $titulo_programa ) : 'Preinscripción enviada';
        } elseif ( $tipo === 'solicitud_informacion' ) {
            $page_title = $titulo_programa ? sprintf( 'Solicitud de información - %s', $titulo_programa ) : 'Solicitud de información';
        } else {
            $page_title = $titulo_programa ? sprintf( 'Consulta enviada - %s', $titulo_programa ) : 'Consulta enviada';
        }

        status_header( 200 );
        nocache_headers();
        flacso_consultas_apply_virtual_page_title( $page_title );
        get_header();
        ?>
                <main class="flacso-gracias-container">
                        <?php if ( $thumb_url ) : ?>
                        <div class="flacso-gracias-bg" aria-hidden="true" style="background-image: linear-gradient(rgba(18,45,75,.72), rgba(18,45,75,.58)), url('<?php echo esc_url( $thumb_url ); ?>');"></div>
                        <?php endif; ?>

                        <div class="flacso-gracias-content">
                                <section class="flacso-gracias-card" aria-labelledby="flacso-gracias-title">
                                        <div class="flacso-gracias-icon" aria-hidden="true">✓</div>
					<?php
						$template_name = '';
						if ( $tipo === 'preinscripcion_oferta' ) {
							$template_name = 'gracias/preinscripcion_oferta.php';
						} elseif ( $tipo === 'preinscripcion_seminario' ) {
							$template_name = 'gracias/preinscripcion_seminario.php';
						} elseif ( $tipo === 'solicitud_informacion' ) {
							$template_name = 'gracias/solicitud_informacion.php';
						} else {
							$template_name = 'gracias/consulta.php';
						}

						$overridden = locate_template(array($template_name));
						if ($overridden !== '') {
							include $overridden;
						} else {
							// Fallback mínimo genérico si el theme no define una vista específica.
							echo '<h1 id="flacso-gracias-title" class="mb-3">¡Tu consulta fue enviada!</h1>';
							echo '<div class="flacso-gracias-mensaje mb-3"><p class="lead mb-2">Hemos recibido tu consulta correctamente.</p></div>';
						}
					?>

                                        <nav class="flacso-gracias-buttons" aria-label="Acciones posteriores">
                                                <a class="flacso-gracias-btn flacso-gracias-btn--primary"
                                                   href="<?php echo esc_url( $volver_href ); ?>" aria-label="Volver al programa seleccionado">
                                                        <span class="flacso-gracias-btn__icon" aria-hidden="true">←</span>
                                                        <span class="flacso-gracias-btn__text">
                                                                <strong>Volver al programa</strong>
                                                                <small><?php echo esc_html( $titulo_programa ?: 'posgrado' ); ?></small>
                                                        </span>
                                                </a>
                                                <a class="flacso-gracias-btn flacso-gracias-btn--secondary"
                                                   href="<?php echo esc_url( home_url( '/formacion/' ) ); ?>" target="_self" rel="noopener">
                                                        <span class="flacso-gracias-btn__icon" aria-hidden="true">▦</span>
                                                        <span class="flacso-gracias-btn__text"><strong>Ver resto de la oferta</strong></span>
                                                </a>
                                        </nav>
                                </section>
                        </div>
                </main>

		<script>
		document.addEventListener('DOMContentLoaded', function() {
			var pid = <?php echo (int) $pid; ?>;
			var programaMeta = <?php echo wp_json_encode( (string) $titulo_programa ); ?>;
			
			if (!programaMeta) {
				programaMeta = sessionStorage.getItem('consultaPrograma') || '';
			}

			var urlParams = new URLSearchParams(window.location.search);
			var tipo = urlParams.get('tipo') || 'consulta';
			if (tipo === 'preinscripcion') tipo = 'preinscripcion_oferta';
			var isPreinscripcion = (tipo === 'preinscripcion_oferta' || tipo === 'preinscripcion_seminario');
			var metaLeadPendingStorageKey = 'consultaMetaLeadPending';
			var shouldTrackLead = false;
			var pendingLead = null;
			var metaUserData = {};

			try {
				metaUserData = JSON.parse(sessionStorage.getItem('consultaMetaUserData') || '{}');
			} catch (e) {
				metaUserData = {};
			}

			try {
				pendingLead = JSON.parse(sessionStorage.getItem(metaLeadPendingStorageKey) || 'null');
			} catch (e) {
				pendingLead = null;
			}

			if (pendingLead && typeof pendingLead === 'object') {
				var pendingAge = Date.now() - Number(pendingLead.createdAt || 0);
				var pendingTipo = String(pendingLead.tipo || 'consulta');
				if (pendingTipo === 'preinscripcion') pendingTipo = 'preinscripcion_oferta';
				shouldTrackLead = pendingAge >= 0 &&
					pendingAge < 30 * 60 * 1000 &&
					String(pendingLead.pid || '') === String(pid || '') &&
					pendingTipo === tipo;
			}

			// Only fire Lead event if it's a general consultation or info request
			// Pre-enrollments fire their own Lead & SubmitApplication events with Advanced Matching hashes prior to redirect
			if (shouldTrackLead && typeof window.flacsoMetaTrack === 'function' && !isPreinscripcion) {
				try {
					// Meta Lead: consulta WordPress enviada correctamente.
					// Debe ejecutarse solo después de confirmación AJAX exitosa y redirección a /gracias.
					window.flacsoMetaTrack('Lead', {
						lead_type: 'consulta_wordpress_oferta',
						form_type: 'consulta_oferta_academica',
						lead_source: 'wordpress_form',
						lead_context: 'oferta_academica',
						content_name: programaMeta || '',
						content_category: 'oferta_academica',
						content_type: 'oferta_academica',
						status: 'submitted'
					}, { userData: metaUserData });
					sessionStorage.removeItem('consultaMetaUserData');
				} catch (e) {
					if (window.console && typeof window.console.warn === 'function') {
						console.warn('[Formulario Consultas] Error enviando Lead:', e);
					}
				}
			}
			sessionStorage.removeItem(metaLeadPendingStorageKey);
			if (!shouldTrackLead || isPreinscripcion) {
				sessionStorage.removeItem('consultaMetaUserData');
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
					var urlParams = new URLSearchParams(window.location.search);
					var tipo = urlParams.get('tipo') || 'consulta';
					if (tipo === 'preinscripcion') tipo = 'preinscripcion_oferta';
					var isPreinscripcion = (tipo === 'preinscripcion_oferta' || tipo === 'preinscripcion_seminario');

					if (isPreinscripcion) {
						var strTipo = (tipo === 'preinscripcion_seminario') ? 'Seminario' : 'Oferta';
						var p = document.createElement('p');
						p.className = 'mb-0 fw-semibold';
						p.textContent = strTipo + ' consultada: ' + programa;
						cont.appendChild(p);
					} else if (tipo === 'solicitud_informacion') {
						var lead = cont.querySelector('.lead');
						if (lead) {
							lead.textContent = 'Hemos recibido tu solicitud de información sobre ' + intro + '.';
						}
					} else {
						var lead = cont.querySelector('.lead');
						if (lead) {
							lead.textContent = 'Gracias por tu interés en ' + intro + ' de FLACSO Uruguay.';
						}
					}
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
                .flacso-gracias-container {
                        position: relative;
                        min-height: min(820px, 86vh);
                        overflow: hidden;
                        background: linear-gradient(135deg, #f4f7fb 0%, #e8eef5 100%);
                }
                .flacso-gracias-bg {
                        position: absolute;
                        inset: 0;
                        z-index: 0;
                        background-size: cover;
                        background-position: center;
                        filter: saturate(.95);
                }
                .flacso-gracias-content {
                        position: relative;
                        z-index: 1;
                        width: min(100% - 32px, 880px);
                        margin: 0 auto;
                        padding: clamp(48px, 8vw, 96px) 0;
                        min-height: inherit;
                        display: flex;
                        align-items: center;
                        justify-content: center;
                }
                .flacso-gracias-card {
                        width: 100%;
                        background: rgba(255,255,255,.96);
                        border: 1px solid rgba(24, 50, 76, .10);
                        border-radius: 8px;
                        box-shadow: 0 28px 70px rgba(15, 35, 56, .20);
                        padding: clamp(28px, 5vw, 52px);
                        text-align: center;
                }
                .flacso-gracias-icon {
                        width: 64px;
                        height: 64px;
                        margin: 0 auto 18px;
                        border-radius: 999px;
                        display: grid;
                        place-items: center;
                        background: #e7f6ed;
                        color: #217a3c;
                        font-size: 2.1rem;
                        font-weight: 800;
                        line-height: 1;
                        border: 1px solid rgba(33,122,60,.18);
                }
                .flacso-gracias-card h1 {
                        margin: 0 0 16px;
                        color: var(--global-palette1, #173f6b);
                        font-size: clamp(2rem, 4vw, 3rem);
                        line-height: 1.08;
                        letter-spacing: 0;
                }
                .flacso-gracias-mensaje {
                        max-width: 680px;
                        margin: 0 auto 28px;
                        color: #334155;
                        font-size: 1rem;
                        line-height: 1.65;
                }
                .flacso-gracias-mensaje p { margin: 0 0 10px; }
                .flacso-gracias-mensaje .lead {
                        font-size: clamp(1.12rem, 2vw, 1.32rem);
                        color: #18324d;
                        font-weight: 650;
                        line-height: 1.45;
                }
                .flacso-gracias-mensaje .fw-semibold { font-weight: 650; }
                .flacso-gracias-card .mb-0 { margin-bottom: 0; }
                .flacso-gracias-card .mb-1 { margin-bottom: .25rem; }
                .flacso-gracias-card .mb-2 { margin-bottom: .5rem; }
                .flacso-gracias-card .mb-3 { margin-bottom: 1rem; }
                .flacso-gracias-buttons {
                        width: min(100%, 460px);
                        margin: 30px auto 0;
                        display: grid;
                        gap: 12px;
                }
                .flacso-gracias-btn {
                        min-height: 62px;
                        border-radius: 8px;
                        padding: 14px 18px;
                        display: flex;
                        align-items: center;
                        gap: 14px;
                        text-decoration: none;
                        transition: transform .18s ease, box-shadow .18s ease, background-color .18s ease, border-color .18s ease;
                }
                .flacso-gracias-btn:hover { transform: translateY(-1px); text-decoration: none; }
                .flacso-gracias-btn--primary {
                        background: var(--global-palette-btn-bg, #173f6b);
                        color: #fff;
                        box-shadow: 0 14px 28px rgba(23,63,107,.22);
                }
                .flacso-gracias-btn--primary:hover {
                        background: var(--global-palette-btn-bg-hover, #0f2f55);
                        color: #fff;
                        box-shadow: 0 18px 34px rgba(23,63,107,.28);
                }
                .flacso-gracias-btn--secondary {
                        background: #fff;
                        color: #173f6b;
                        border: 1px solid rgba(23,63,107,.22);
                }
                .flacso-gracias-btn--secondary:hover {
                        background: #f4f7fb;
                        color: #173f6b;
                        border-color: rgba(23,63,107,.36);
                }
                .flacso-gracias-btn__icon {
                        flex: 0 0 34px;
                        width: 34px;
                        height: 34px;
                        border-radius: 999px;
                        display: grid;
                        place-items: center;
                        background: rgba(255,255,255,.18);
                        font-size: 1.15rem;
                        font-weight: 800;
                }
                .flacso-gracias-btn--secondary .flacso-gracias-btn__icon {
                        background: #edf3f8;
                        color: #173f6b;
                }
                .flacso-gracias-btn__text { min-width: 0; text-align: left; }
                .flacso-gracias-btn__text strong { display: block; line-height: 1.2; }
                .flacso-gracias-btn__text small {
                        display: -webkit-box;
                        -webkit-line-clamp: 2;
                        -webkit-box-orient: vertical;
                        overflow: hidden;
                        margin-top: 3px;
                        color: rgba(255,255,255,.78);
                        line-height: 1.3;
                }
                @media (max-width: 640px) {
                        .flacso-gracias-content { width: min(100% - 24px, 880px); padding: 32px 0; }
                        .flacso-gracias-card { padding: 24px 18px; }
                        .flacso-gracias-icon { width: 56px; height: 56px; font-size: 1.9rem; }
                        .flacso-gracias-btn { align-items: flex-start; }
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
