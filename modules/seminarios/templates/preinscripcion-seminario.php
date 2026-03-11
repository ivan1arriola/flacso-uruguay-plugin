<?php
/**
 * Template para página de preinscripción a seminarios
 * Version: 3.1.2 - Mejoras de UI y padding/márgenes
 */

if (!defined('ABSPATH')) exit;

/**
 * Convierte una fecha YYYY-MM-DD al formato español: "Miércoles 23 de Abril 2026"
 */
function format_fecha_espanol($fecha) {
    if (empty($fecha)) return '';
    $timestamp = strtotime($fecha);
    if ($timestamp === false) return esc_html($fecha);
    
    $dias = array('Domingo', 'Lunes', 'Martes', 'Miércoles', 'Jueves', 'Viernes', 'Sábado');
    $meses = array('', 'Enero', 'Febrero', 'Marzo', 'Abril', 'Mayo', 'Junio', 'Julio', 'Agosto', 'Septiembre', 'Octubre', 'Noviembre', 'Diciembre');
    
    $dia_semana = $dias[date('w', $timestamp)];
    $dia = date('j', $timestamp);
    $mes = $meses[date('n', $timestamp)];
    $ano = date('Y', $timestamp);
    
    return "$dia_semana $dia de $mes $ano";
}

// Valida Cédula de Identidad uruguaya - Algoritmo local
function validar_ci_uy($ci) {
    $num = preg_replace('/[^0-9]/', '', (string)$ci);
    $len = strlen($num);
    if ($len < 7 || $len > 8) return false;
    
    // Extraer base y dígito verificador
    $base = substr($num, 0, -1);
    $dv_ingresado = intval(substr($num, -1));
    $base = str_pad($base, 7, '0', STR_PAD_LEFT);
    
    // Calcular dígito verificador
    $pesos = [2, 9, 8, 7, 6, 3, 4];
    $suma = 0;
    for ($i = 0; $i < 7; $i++) {
        $suma += intval($base[$i]) * $pesos[$i];
    }
    $dv_calc = (10 - ($suma % 10)) % 10;
    
    return $dv_ingresado === $dv_calc;
}

// Enqueue Assets
// Font Awesome
wp_enqueue_style('fontawesome', 'https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css', [], '6.5.1');

// intl-tel-input
wp_enqueue_style('intl-tel-input-css', 'https://cdn.jsdelivr.net/npm/intl-tel-input@25.15.0/build/css/intlTelInput.css', [], '25.15.0');
wp_enqueue_script('intl-tel-input-js', 'https://cdn.jsdelivr.net/npm/intl-tel-input@25.15.0/build/js/intlTelInput.min.js', [], '25.15.0', true);

// libphonenumber (Google's library for phone number validation)
wp_enqueue_script('libphonenumber', 'https://cdn.jsdelivr.net/npm/google-libphonenumber@3.2.34/dist/libphonenumber-js.min.js', [], '3.2.34', true);

// jQuery (for intl-tel-input and general use)
wp_enqueue_script('jquery');

// country-select-js (selector de país con banderas)
wp_enqueue_style('country-select-css', 'https://cdn.jsdelivr.net/npm/country-select-js@2.0.1/build/css/countrySelect.min.css', [], '2.0.1');
wp_enqueue_script('country-select-js', 'https://cdn.jsdelivr.net/npm/country-select-js@2.0.1/build/js/countrySelect.min.js', ['jquery'], '2.0.1', true);

// Variables necesarias - Obtener ID del seminario desde la URL
$seminario_id = isset($_GET['ID']) ? intval($_GET['ID']) : 0;

// Verificar que el seminario existe
if (!$seminario_id || get_post_type($seminario_id) !== 'seminario' || get_post_status($seminario_id) !== 'publish') {
    // Redirigir a página de seminarios si no hay ID válido
    wp_redirect(site_url('/formacion/seminarios/'));
    exit;
}

$seminario_titulo = get_the_title($seminario_id);
$seminario_slug = get_post_field('post_name', $seminario_id);
// Imagen destacada para usar como fondo del hero
$hero_image_url = get_the_post_thumbnail_url($seminario_id, 'full');

// Meta fields
$fecha_inicio = get_post_meta($seminario_id, '_seminario_periodo_inicio', true);
$fecha_fin = get_post_meta($seminario_id, '_seminario_periodo_fin', true);
$modalidad = get_post_meta($seminario_id, '_seminario_modalidad', true);
$creditos = get_post_meta($seminario_id, '_seminario_creditos', true);
$carga_horaria = get_post_meta($seminario_id, '_seminario_carga_horaria', true);

// Taxonomías

// Variables de envío
$submission_success = false;
$submission_error = '';
$nombre_full = '';
$email = '';

// Procesar formulario
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['preinscripcion_nonce'])) {
    // Validar nonce
    if (!wp_verify_nonce($_POST['preinscripcion_nonce'], 'preinscripcion_nonce')) {
        $submission_error = 'Error de seguridad. Por favor intenta de nuevo.';
    } else {
        // Sanitizar datos
        $nombre1 = sanitize_text_field($_POST['nombre1'] ?? '');
        $nombre2 = sanitize_text_field($_POST['nombre2'] ?? '');
        $apellido1 = sanitize_text_field($_POST['apellido1'] ?? '');
        $apellido2 = sanitize_text_field($_POST['apellido2'] ?? '');
        $correo = sanitize_email($_POST['correo'] ?? '');
        $celular_e164 = isset($_POST['celular_e164']) ? sanitize_text_field($_POST['celular_e164']) : '';
        $pais_residencia = sanitize_text_field($_POST['pais_residencia'] ?? '');
        $tipo_documento = sanitize_text_field($_POST['tipo_documento'] ?? '');
        $documento_ci = sanitize_text_field($_POST['documento_ci'] ?? '');
        $documento_otro = sanitize_text_field($_POST['documento_otro'] ?? '');
        $genero = sanitize_text_field($_POST['genero'] ?? '');
        $genero_otra = sanitize_text_field($_POST['genero_otra'] ?? '');
        $etnia = sanitize_text_field($_POST['etnia'] ?? '');
        // Combinar documento según el tipo
        $documento = ($tipo_documento === 'CI') ? $documento_ci : $documento_otro;
        $fecha_nacimiento = sanitize_text_field($_POST['fecha_nacimiento'] ?? '');
        $estudios = sanitize_text_field($_POST['estudios'] ?? '');
        $ocupacion = sanitize_text_field($_POST['ocupacion'] ?? '');
        $titulo_denominacion = sanitize_text_field($_POST['titulo_denominacion'] ?? '');
        $posgrado_flacso = sanitize_text_field($_POST['posgrado_flacso'] ?? '');
        $posgrado_cual = sanitize_text_field($_POST['posgrado_cual'] ?? '');
        $acepta_difusion = sanitize_text_field($_POST['acepta_difusion'] ?? '');

        // Validaciones básicas
        if (!$nombre1) {
            $submission_error = 'El nombre es requerido.';
        } elseif ($genero === 'Otra' && !$genero_otra) {
            $submission_error = 'Por favor especifica tu identidad de genero.';
        } elseif (!$etnia) {
            $submission_error = 'La raza/etnia es requerida.';
        } elseif (!$apellido1) {
            $submission_error = 'El apellido es requerido.';
        } elseif (!is_email($correo)) {
            $submission_error = 'Por favor ingresa un correo válido.';
        } elseif (empty($celular_e164)) {
            $submission_error = 'El número de teléfono no tiene un formato válido. Por favor verifica.';
        } elseif (!$pais_residencia || strlen($pais_residencia) !== 2) {
            $submission_error = 'Por favor selecciona un país válido.';
        } elseif (!$tipo_documento) {
            $submission_error = 'El tipo de documento es requerido.';
        } elseif (!$documento) {
            $submission_error = 'El documento es requerido.';
        } elseif ($tipo_documento === 'CI' && !validar_ci_uy($documento)) {
            // Solo validar CI con ci.js
            $submission_error = 'La cédula ingresada no es válida. Por favor verifica los números y el dígito verificador.';
        } elseif ($tipo_documento !== 'CI' && (strlen($documento) < 3 || strlen($documento) > 20)) {
            // Para Pasaporte y Documento no uruguayo: validar largo razonable (3-20 caracteres)
            $submission_error = 'El documento debe tener entre 3 y 20 caracteres.';
        } elseif (!$fecha_nacimiento) {
            $submission_error = 'La fecha de nacimiento es requerida.';
        } elseif (!$titulo_denominacion) {
            $submission_error = 'La denominación del título es requerida.';
        } elseif (!$estudios) {
            $submission_error = 'El nivel de estudios es requerido.';
        } elseif (!$ocupacion) {
            $submission_error = 'La ocupación actual es requerida.';
        } elseif (!$posgrado_flacso) {
            $submission_error = 'Por favor selecciona si has cursado posgrado en FLACSO.';
        } elseif ($posgrado_flacso === 'si' && !$posgrado_cual) {
            $submission_error = 'Por favor indica cuál posgrado cursaste en FLACSO.';
        } elseif (!$acepta_difusion) {
            $submission_error = 'Por favor selecciona si aceptas la difusión de nombre/foto.';
        } else {
            // Validar mayor de edad (17 años)
            $fecha_nac_obj = DateTime::createFromFormat('Y-m-d', $fecha_nacimiento);
            $hoy = new DateTime();
            if (!$fecha_nac_obj) {
                $submission_error = 'La fecha de nacimiento no es válida.';
            } else {
                $edad = $hoy->diff($fecha_nac_obj)->y;
                if ($edad < 17) {
                    $submission_error = 'Debes ser mayor de 17 años para inscribirte.';
                }
            }
        }

        // Validar archivo de título si no hay otros errores
        if (!$submission_error && (empty($_FILES['titulo']['tmp_name']) || $_FILES['titulo']['error'] !== UPLOAD_ERR_OK)) {
            $submission_error = 'El archivo del título es requerido.';
        }

        // Validar archivo de documento de identidad
        if (!$submission_error && (empty($_FILES['documento_identidad']['name'][0]) || empty($_FILES['documento_identidad']['tmp_name'][0]))) {
            $submission_error = 'Debes subir al menos una imagen de tu documento de identidad.';
        }

        // Validar tamaño total de archivos (máximo 25 MB)
        if (!$submission_error) {
            $max_size_bytes = 25 * 1024 * 1024; // 25 MB
            $total_size = 0;
            
            // Sumar tamaño de documento_identidad
            if (!empty($_FILES['documento_identidad']['name'][0])) {
                foreach ($_FILES['documento_identidad']['tmp_name'] as $key => $tmp_name) {
                    if (!empty($tmp_name) && $_FILES['documento_identidad']['error'][$key] === UPLOAD_ERR_OK) {
                        $total_size += $_FILES['documento_identidad']['size'][$key];
                    }
                }
            }
            
            // Sumar tamaño de título
            if (!empty($_FILES['titulo']['tmp_name']) && $_FILES['titulo']['error'] === UPLOAD_ERR_OK) {
                $total_size += $_FILES['titulo']['size'];
            }
            
            if ($total_size > $max_size_bytes) {
                $size_mb = round($total_size / (1024 * 1024), 2);
                $submission_error = "El tamaño total de los archivos ({$size_mb} MB) excede el límite de 25 MB. Por favor selecciona archivos más pequeños.";
            }
        }

        // Si no hay errores, enviar
        if (!$submission_error) {
            $nombre_full = "$nombre1 $apellido1";
            $email = $correo;

            // Obtener IP y User Agent
            $ip_address = $_SERVER['REMOTE_ADDR'] ?? '';
            $user_agent = $_SERVER['HTTP_USER_AGENT'] ?? '';

            // Preparar datos en formato especificado
            $form_data = [
                'seminario' => [
                    'nombre' => $seminario_titulo,
                    'id' => $seminario_id
                ],
                'datos' => [
                    'correo' => $correo,
                    'tipo_documento' => $tipo_documento,
                    'documento' => $documento,
                    'nombre1' => $nombre1,
                    'nombre2' => $nombre2,
                    'apellido1' => $apellido1,
                    'apellido2' => $apellido2,
                    'fecha_nacimiento' => $fecha_nacimiento,
                    'genero' => $genero,
                    'genero_otra' => $genero_otra,
                    'etnia' => $etnia,
                    'celular_e164' => $celular_e164,
                    'pais_residencia' => $pais_residencia,
                    'estudios' => $estudios,
                    'ocupacion' => $ocupacion,
                    'titulo_denominacion' => $titulo_denominacion,
                    'posgrado_flacso' => ($posgrado_flacso === 'si') ? 'sí' : 'no',
                    'posgrado_cual' => $posgrado_cual,
                    'acepta_difusion' => $acepta_difusion
                ],
                'archivos' => [
                    'documento_identidad' => [],
                    'titulo' => null
                ],
                'meta' => [
                    'ip' => $ip_address,
                    'ua' => $user_agent
                ]
            ];

            // Procesar archivos si existen
            if (!empty($_FILES['documento_identidad']['name'][0])) {
                foreach ($_FILES['documento_identidad']['tmp_name'] as $key => $tmp_name) {
                    if (!empty($tmp_name)) {
                        $file_content = file_get_contents($tmp_name);
                        $form_data['archivos']['documento_identidad'][] = [
                            'name' => sanitize_file_name($_FILES['documento_identidad']['name'][$key]),
                            'type' => $_FILES['documento_identidad']['type'][$key],
                            'content' => base64_encode($file_content)
                        ];
                    }
                }
            }

            if (!empty($_FILES['titulo']['tmp_name'])) {
                $file_content = file_get_contents($_FILES['titulo']['tmp_name']);
                $form_data['archivos']['titulo'] = [
                    'name' => sanitize_file_name($_FILES['titulo']['name']),
                    'type' => $_FILES['titulo']['type'],
                    'content' => base64_encode($file_content)
                ];
            }

            // Webhook - usar configuración desde admin o constante como fallback
            $webhook_url = get_option('flacso_seminario_webhook_url', '');
            if (empty($webhook_url) && defined('FLACSO_PREINSCRIPCION_WEBHOOK')) {
                $webhook_url = FLACSO_PREINSCRIPCION_WEBHOOK;
            }
            
            if (!empty($webhook_url)) {
                wp_remote_post($webhook_url, [
                    'body' => json_encode($form_data),
                    'headers' => ['Content-Type' => 'application/json'],
                    'timeout' => 30,
                    'blocking' => false,
                ]);
            }

            $submission_success = true;
        }
    }
}

get_header();
?>

<style>
/* Minimal inline overrides. Full styling via kadence-compat.css using Kadence variables */
#celular { padding-left: 52px; }
.iti { width: 100%; }
</style>

<main id="main" class="site-main flacso-preinscripcion-seminario-main">

<!-- Hero Section -->
<section class="preinsc-hero" aria-label="Preinscripción">
    <?php if ($hero_image_url): ?>
        <img src="<?php echo esc_url($hero_image_url); ?>" alt="" class="preinsc-hero-bg-image" aria-hidden="true">
    <?php endif; ?>
    <div class="preinsc-hero-grid">
        <div>
            <a href="<?php echo esc_url(get_permalink(intval($seminario_id))); ?>" class="btn-back-seminario" aria-label="Volver al seminario">
                <i class="fas fa-arrow-left"></i>
                <span>Volver al seminario</span>
            </a>

            <p class="text-uppercase" style="margin-top: 1rem; opacity: 0.95; color: #ffffff !important;">
                <i class="fas fa-clipboard-check" style="margin-right: 0.5rem;"></i>Preinscripción 2026
            </p>

            <h1 class="preinsc-title">
                <?php echo $seminario_titulo ? esc_html($seminario_titulo) : 'Preinscripción al seminario'; ?>
            </h1>

            <p class="preinsc-subtitle">Completa el formulario para reservar tu lugar</p>
        </div>

        <aside class="preinsc-steps" aria-labelledby="preinsc-steps-title">
            <p id="preinsc-steps-title" class="text-uppercase" style="font-weight:600; margin-bottom: 0.75rem; color: #ffffff !important;">Próximos pasos</p>
            <ol>
                <li>
                    <div class="step-title">Completa tus datos</div>
                    <div class="step-desc">Rellena el formulario con tus datos personales y académicos.</div>
                </li>
                <!-- Paso eliminado: "Acepta los términos" no aplica -->
                <li>
                    <div class="step-title">Recibe confirmación por email</div>
                    <div class="step-desc">Te enviaremos un correo con la confirmación y los pasos siguientes.</div>
                </li>
            </ol>
        </aside>
    </div>
</section>

<!-- Contenido Principal -->
<div class="content-container site-container" style="padding-top: 3rem; padding-bottom: 3rem;">
    <?php if ($submission_success) : ?>
        <!-- Success Message -->
        <div class="success-container">
            <div class="success-content alert alert-success">
                <div class="alert-icon">
                    <i class="fas fa-check-circle" style="color: #28a745;"></i>
                </div>
                <h2 style="margin-bottom: 0.5rem;">¡Preinscripción enviada con éxito!</h2>
                <p style="font-size: 18px; margin-bottom: 1rem;">Gracias, <strong><?php echo esc_html($nombre_full); ?></strong></p>
                <p style="margin-bottom: 1rem;">Recibimos tu solicitud para el seminario <strong><?php echo esc_html($seminario_titulo); ?></strong>. Te enviamos una confirmación al siguiente correo:</p>
                <p style="margin-bottom: 1.5rem;"><strong><?php echo esc_html($email); ?></strong></p>
                <div class="alert alert-info" style="margin-bottom: 1.5rem;">
                    <p style="margin: 0;"><i class="fas fa-lightbulb" style="margin-right: 0.5rem;"></i>En breve te contactaremos con los próximos pasos. Si no ves el correo en unos minutos, revisa tu bandeja de spam o promociones.</p>
                </div>
                <a href="<?php echo esc_url(home_url('/formacion/seminarios/')); ?>" class="btn btn-lg">
                    <i class="fas fa-arrow-left" style="margin-right: 0.5rem;"></i>Volver a seminarios
                </a>
            </div>
        </div>
        <script>
        (function() {
            if (typeof window.fbq !== 'function') {
                return;
            }
            try {
                window.fbq('track', 'SubmitApplication', {
                    content_name: <?php echo wp_json_encode((string) $seminario_titulo); ?>,
                    content_category: 'preinscripcion_seminario',
                    status: 'completed'
                });
            } catch (e) {
                if (window.console && typeof window.console.warn === 'function') {
                    console.warn('[Preinscripcion Seminario] Error enviando SubmitApplication:', e);
                }
            }
        })();
        </script>
    <?php else : ?>
        
            <?php if (!empty($submission_error)) : ?>
                <div class="alert alert-danger">
                    <div class="d-flex align-items-start">
                        <div class="flex-shrink-0">
                            <i class="fas fa-exclamation-circle" style="font-size: 2rem;"></i>
                        </div>
                        <div class="flex-grow-1" style="margin-left: 1rem;">
                            <h4 style="margin: 0 0 0.5rem 0;">Error en el formulario</h4>
                            <p style="margin: 0;"><?php echo esc_html($submission_error); ?></p>
                        </div>
                    </div>
                </div>
            <?php endif; ?>

            <h2 style="font-size: 18px; text-transform: uppercase; letter-spacing: 1px; margin-bottom: 2rem; color: var(--color-secondary);">Formulario de preinscripción</h2>

            <div class="form-container">
            <form id="form-preinscripcion" method="POST" enctype="multipart/form-data" class="needs-validation" novalidate>
                <!-- Datos Personales -->
                <div class="form-section">
                    <h3>
                        <i class="fas fa-user" style="margin-right: 0.5rem;"></i>Datos personales
                    </h3>
                    <div class="form-row">
                        <div class="form-group">
                            <label for="nombre1" class="form-label">Nombre <span class="required">*</span></label>
                            <input type="text" class="form-control" id="nombre1" name="nombre1" placeholder="Tu nombre" required>
                            <div class="invalid-feedback">Por favor ingresa tu nombre.</div>
                        </div>
                        <div class="form-group">
                            <label for="apellido1" class="form-label">Apellido <span class="required">*</span></label>
                            <input type="text" class="form-control" id="apellido1" name="apellido1" placeholder="Tu apellido" required>
                            <div class="invalid-feedback">Por favor ingresa tu apellido.</div>
                        </div>
                    </div>

                    <div class="form-row">
                        <div class="form-group">
                            <label for="nombre2" class="form-label">Segundo Nombre</label>
                            <input type="text" class="form-control" id="nombre2" name="nombre2" placeholder="Tu segundo nombre (opcional)">
                        </div>
                        <div class="form-group">
                            <label for="apellido2" class="form-label">Segundo Apellido</label>
                            <input type="text" class="form-control" id="apellido2" name="apellido2" placeholder="Tu segundo apellido (opcional)">
                        </div>
                    </div>

                    <div class="form-row">
                        <div class="form-group">
                            <label for="fecha_nacimiento" class="form-label">Fecha de Nacimiento <span class="required">*</span></label>
                            <input type="date" class="form-control" id="fecha_nacimiento" name="fecha_nacimiento" max="<?php echo date('Y-m-d', strtotime('-17 years')); ?>" required>
                            <div class="invalid-feedback">Debes ser mayor de 17 años.</div>
                        </div>
                        <div class="form-group">
                            <label for="tipo_documento" class="form-label">Tipo de Documento <span class="required">*</span></label>
                            <select class="form-select" id="tipo_documento" name="tipo_documento">
                                <option value="">Selecciona...</option>
                                <option value="CI">Cédula de Identidad Uruguaya</option>
                                <option value="Pasaporte">Pasaporte</option>
                                <option value="Otro">Documento de identidad no uruguayo</option>
                            </select>
                            <div class="invalid-feedback">Por favor selecciona un tipo de documento.</div>
                        </div>
                    </div>

                    <!-- Campo para CI uruguaya -->
                    <div class="form-group" id="campo_ci" style="display: none;">
                        <label for="documento_ci" class="form-label">Número de Cédula de Identidad <span class="required">*</span></label>
                        <input type="text" class="form-control" id="documento_ci" name="documento_ci" placeholder="12345678" pattern="[0-9]{7,8}" maxlength="8" inputmode="numeric">
                        <div class="invalid-feedback ci-feedback">Por favor ingresa una cédula válida.</div>
                        <small class="form-text">Ingresa solo números, sin puntos ni guiones, con dígito verificador (ej: 12345678)</small>
                    </div>

                    <!-- Campo para otros documentos -->
                    <div class="form-group" id="campo_otro_doc" style="display: none;">
                        <label for="documento_otro" class="form-label">Número de Documento <span class="required">*</span></label>
                        <input type="text" class="form-control" id="documento_otro" name="documento_otro" placeholder="Tu número de documento" maxlength="20">
                        <div class="invalid-feedback otro-doc-feedback">Por favor ingresa un número de documento válido (3-20 caracteres).</div>
                    </div>

                    <div class="form-row">
                        <div class="form-group">
                            <label for="genero" class="form-label">Identidad de genero</label>
                            <select class="form-select" id="genero" name="genero">
                                <option value="">Selecciona...</option>
                                <option value="Mujer">Mujer</option>
                                <option value="Varon">Varon</option>
                                <option value="Mujer trans">Mujer trans</option>
                                <option value="Varon trans">Varon trans</option>
                                <option value="No binarie / no conforme">No binarie / no conforme</option>
                                <option value="Otra">Otra</option>
                                <option value="Prefiero no responder">Prefiero no responder</option>
                            </select>
                        </div>
                        <div class="form-group" id="genero_otra_container" style="display: none;">
                            <label for="genero_otra" class="form-label">Especifica identidad de genero <span class="required">*</span></label>
                            <input type="text" class="form-control" id="genero_otra" name="genero_otra" placeholder="Especifica tu identidad de genero">
                            <div class="invalid-feedback">Por favor especifica tu identidad de genero.</div>
                        </div>
                    </div>

                    <div class="form-group">
                        <label for="etnia" class="form-label">Con que raza/etnia se identifica? <span class="required">*</span></label>
                        <input type="text" class="form-control" id="etnia" name="etnia" placeholder="Ingrese su raza/etnia" required>
                        <div class="invalid-feedback">Por favor ingresa tu raza/etnia.</div>
                    </div>
                </div>

                <!-- Contacto -->
                <div class="form-section">
                    <h3>
                        <i class="fas fa-envelope"></i>
                        <span style="margin-left: 0.75rem;">Información de contacto</span>
                    </h3>
                    <div class="form-row">
                        <div class="form-group">
                            <label for="correo" class="form-label">Correo Electrónico <span class="required">*</span></label>
                            <input type="email" class="form-control" id="correo" name="correo" placeholder="tu@email.com" required>
                            <div class="invalid-feedback">Por favor ingresa una dirección de correo válida (ej: nombre@ejemplo.com).</div>
                        </div>
                        <div class="form-group">
                            <label for="celular" class="form-label">Celular <span class="required">*</span></label>
                            <input type="tel" class="form-control" id="celular" name="celular" placeholder="099 123456 o +598 99 123456" required>
                            <div class="valid-feedback d-none"><i class="fas fa-check-circle"></i> Teléfono válido</div>
                            <div class="invalid-feedback">Por favor ingresa un número de teléfono válido.</div>
                            <small class="phone-error-details d-none"></small>
                            <small class="form-text phone-helper-text">Puedes ingresar: número local (099 123456) o formato internacional (+598 99 123456)</small>
                            <!-- Campo oculto para E.164 -->
                            <input type="hidden" id="celular_e164" name="celular_e164" value="">
                        </div>
                    </div>
                </div>

                <!-- Ubicación -->
                <div class="form-section">
                    <h3>
                        <i class="fas fa-globe"></i>
                        <span style="margin-left: 0.75rem;">Ubicación</span>
                    </h3>
                    <div class="form-group">
                        <label for="pais_residencia_input" class="form-label">País de Residencia <span class="required">*</span></label>
                        <div class="country-select">
                            <input type="text" id="pais_residencia_input" class="form-control country-select-input" placeholder="Selecciona un país..." autocomplete="off" aria-autocomplete="list" required />
                            <input type="hidden" id="pais_residencia" name="pais_residencia" value="<?php echo isset($_POST['pais_residencia']) ? esc_attr(strtolower($_POST['pais_residencia'])) : ''; ?>" />
                        </div>
                        <div class="invalid-feedback">Por favor selecciona tu país de residencia.</div>
                    </div>
                </div>

                <!-- Información Académica y Profesional -->
                <div class="form-section">
                    <h3>
                        <i class="fas fa-graduation-cap" style="margin-right: 0.5rem;"></i>Información académica y profesional
                    </h3>
                    <div class="form-group">
                        <label for="estudios" class="form-label">Estudios <span class="required">*</span></label>
                        <input type="text" class="form-control" id="estudios" name="estudios" placeholder="Ej: Licenciatura en Educación" required>
                        <div class="invalid-feedback">Por favor indica tu nivel de estudios.</div>
                        <small class="form-text">Indica tu nivel de estudios alcanzado</small>
                    </div>
                    <div class="form-group">
                        <label for="ocupacion" class="form-label">Ocupación Actual <span class="required">*</span></label>
                        <input type="text" class="form-control" id="ocupacion" name="ocupacion" placeholder="Ej: Docente, Investigador, etc." required>
                        <div class="invalid-feedback">Por favor indica tu ocupación actual.</div>
                    </div>
                </div>

                <!-- Relación con FLACSO -->
                <div class="form-section">
                    <h3>
                        <i class="fas fa-university" style="margin-right: 0.5rem;"></i>Relación con FLACSO
                    </h3>
                    <div class="form-group" style="margin-bottom: 1rem;">
                        <span class="form-label" style="margin-bottom: 0.75rem; display: block;">¿Has cursado algún posgrado en FLACSO? <span class="required">*</span></span>
                        <div class="radio-button-group">
                            <div class="radio-btn">
                                <input type="radio" id="posgrado_flacso_si" name="posgrado_flacso" value="si" required>
                                <label for="posgrado_flacso_si">Sí</label>
                            </div>
                            <div class="radio-btn">
                                <input type="radio" id="posgrado_flacso_no" name="posgrado_flacso" value="no" required checked>
                                <label for="posgrado_flacso_no">No</label>
                            </div>
                        </div>
                        <div class="invalid-feedback">Por favor selecciona una opción.</div>
                    </div>
                    <div class="form-group" id="posgrado_cual_container" style="display: none;">
                        <label for="posgrado_cual" class="form-label">¿Cuál posgrado? <span class="required">*</span></label>
                        <input type="text" class="form-control" id="posgrado_cual" name="posgrado_cual" placeholder="Ej: Maestría en Educación">
                        <div class="invalid-feedback">Por favor indica cuál posgrado cursaste.</div>
                    </div>
                </div>

                <!-- Archivos -->
                <div class="form-section">
                    <h3>
                        <i class="fas fa-file-upload" style="margin-right: 0.5rem;"></i>Documentación
                    </h3>
                    <div class="form-group">
                        <label for="documento_identidad" class="form-label">Documento de Identidad <span class="required">*</span></label>
                        <input type="file" class="form-control" id="documento_identidad" name="documento_identidad[]" accept="image/*,application/pdf" multiple required>
                        <div class="invalid-feedback">Por favor sube al menos una imagen de tu documento de identidad.</div>
                        <small class="form-text">Puedes subir una o más imágenes (frente y reverso) o un PDF</small>
                    </div>
                    <div class="form-group">
                        <label for="titulo" class="form-label">Archivo del Título de Grado o Terciario <span class="required">*</span></label>
                        <input type="file" class="form-control" id="titulo" name="titulo" accept="image/*,application/pdf" required>
                        <div class="invalid-feedback">Por favor sube una copia de tu título.</div>
                        <small class="form-text">Sube una copia de tu título</small>
                    </div>
                    <div class="form-group">
                        <label for="titulo_denominacion" class="form-label">Denominación del Título de Grado o Terciario <span class="required">*</span></label>
                        <input type="text" class="form-control" id="titulo_denominacion" name="titulo_denominacion" placeholder="Ej: Licenciatura en Educación, Técnico en Informática" required>
                        <div class="invalid-feedback">Por favor indica el nombre de tu título.</div>
                        <small class="form-text">Indica el nombre de tu título de grado o terciario</small>
                    </div>
                </div>

                <!-- Términos -->
                <div class="form-section">
                    <div class="form-group">
                        <span class="form-label" style="margin-bottom: 0.75rem; display: block;">¿Acepta difusión de nombre/foto? <span class="required">*</span></span>
                        <div class="radio-button-group">
                            <div class="radio-btn">
                                <input type="radio" id="acepta_difusion_si" name="acepta_difusion" value="si" required>
                                <label for="acepta_difusion_si">Sí</label>
                            </div>
                            <div class="radio-btn">
                                <input type="radio" id="acepta_difusion_no" name="acepta_difusion" value="no" required>
                                <label for="acepta_difusion_no">No</label>
                            </div>
                        </div>
                        <div class="invalid-feedback">Por favor selecciona una opción.</div>
                    </div>
                </div>

                <!-- Submit -->
                <div style="margin-bottom: 1.5rem;">
                    <button type="submit" class="btn btn-primary btn-lg btn-block">
                        <i class="fas fa-paper-plane" style="margin-right: 0.5rem;"></i>Enviar Preinscripción
                    </button>
                    <div class="submit-loading-message" style="margin-top: 1rem; padding: 1rem; background: rgba(254, 210, 34, 0.1); border-radius: 8px; border: 1px solid rgba(254, 210, 34, 0.3); display: none; text-align: center; color: var(--global-palette4);">
                        <i class="fas fa-hourglass-half" style="margin-right: 0.5rem; animation: spin 2s linear infinite;"></i>
                        <span>Procesando tu preinscripción...</span>
                        <p style="font-size: 13px; margin: 0.5rem 0 0 0; opacity: 0.8;">Este proceso puede tardar un momento. Por favor no cierres esta página.</p>
                    </div>
                </div>

                <p class="text-center" style="color: var(--global-palette5); font-size: 14px; margin: 0;">
                    <span class="text-danger">*</span> Campos obligatorios
                </p>

                <?php wp_nonce_field('preinscripcion_nonce', 'preinscripcion_nonce'); ?>
            </form>
            </div>
        </div>
    <?php endif; ?>
</div>



<!-- JavaScript -->
<script>
// Validador de cédula uruguaya - Única función
function validate_ci(ci) {
    // Limpiar entrada
    ci = ci.replace(/\D/g, '');
    
    // Validar largo
    if (ci.length < 7 || ci.length > 8) return false;
    
    // Extraer base y dígito verificador
    var dig = parseInt(ci[ci.length - 1]);
    var base = ci.substring(0, ci.length - 1);
    
    // Rellenar con ceros a la izquierda si es necesario
    while (base.length < 7) {
        base = '0' + base;
    }
    
    // Calcular dígito verificador
    var pesos = [2, 9, 8, 7, 6, 3, 4];
    var suma = 0;
    for (var i = 0; i < 7; i++) {
        suma += parseInt(base[i]) * pesos[i];
    }
    var dv_calc = (10 - (suma % 10)) % 10;
    
    return dig === dv_calc;
}

(function() {
    'use strict';

    const trackMetaEvent = (eventName, params) => {
        if (typeof window.fbq !== 'function') {
            return;
        }
        try {
            window.fbq('track', eventName, params || {});
        } catch (e) {
            console.warn('[Preinscripcion Seminario] Error enviando evento Meta Pixel:', e);
        }
    };

    // Inicializar intl-tel-input con configuración completa (después de cargar scripts)
    const phoneInput = document.querySelector('#celular');
    if (phoneInput) {
        window.addEventListener('load', function() {
        if (typeof window.intlTelInput !== 'function') return;
        const iti = window.intlTelInput(phoneInput, {
            // Auto-detectar país por IP
            initialCountry: 'auto',
            geoIpLookup: function(callback) {
                fetch('https://ipapi.co/json')
                    .then(res => res.json())
                    .then(data => callback(data.country_code))
                    .catch(() => callback('uy'));
            },
            // Países preferidos (Uruguay primero)
            preferredCountries: ['uy', 'ar', 'br', 'cl', 'co', 'mx', 'pe', 've', 'es', 'pt'],
            // Strict mode: solo números y + al inicio, limita longitud máxima
            strictMode: true,
            // Mostrar código de país separado
            separateDialCode: true,
            // Formatear mientras escribe
            formatAsYouType: true,
            formatOnDisplay: true,
            // Búsqueda de países
            countrySearch: true,
            allowDropdown: true,
            // Placeholder de celular
            placeholderNumberType: 'MOBILE',
            // Validar solo móviles por defecto
            validationNumberTypes: ['MOBILE'],
            autoPlaceholder: 'polite',
            // Traducciones en español
            i18n: {
                searchPlaceholder: 'Buscar',
                zeroSearchResults: 'No se encontraron resultados',
                selectedCountryAriaLabel: 'País seleccionado',
                noCountrySelected: 'Ningún país seleccionado',
                countryListAriaLabel: 'Lista de países',
                // Agregar traducciones para errores de validación
                af: 'Afganistán', ax: 'Islas Åland', al: 'Albania', dz: 'Argelia',
                as: 'Samoa Americana', ad: 'Andorra', ao: 'Angola', ai: 'Anguila',
                ag: 'Antigua y Barbuda', ar: 'Argentina', am: 'Armenia', aw: 'Aruba',
                au: 'Australia', at: 'Austria', az: 'Azerbaiyán', bs: 'Bahamas',
                bh: 'Baréin', bd: 'Bangladés', bb: 'Barbados', by: 'Bielorrusia',
                be: 'Bélgica', bz: 'Belice', bj: 'Benín', bm: 'Bermudas',
                bt: 'Bután', bo: 'Bolivia', ba: 'Bosnia y Herzegovina', bw: 'Botsuana',
                br: 'Brasil', io: 'Territorio Británico del Océano Índico', bn: 'Brunéi',
                bg: 'Bulgaria', bf: 'Burkina Faso', bi: 'Burundi', kh: 'Camboya',
                cm: 'Camerún', ca: 'Canadá', cv: 'Cabo Verde', ky: 'Islas Caimán',
                cf: 'República Centroafricana', td: 'Chad', cl: 'Chile', cn: 'China',
                co: 'Colombia', km: 'Comoras', cd: 'República Democrática del Congo',
                cg: 'Congo', cr: 'Costa Rica', ci: 'Costa de Marfil', hr: 'Croacia',
                cu: 'Cuba', cy: 'Chipre', cz: 'República Checa', dk: 'Dinamarca',
                dj: 'Yibuti', dm: 'Dominica', do: 'República Dominicana', ec: 'Ecuador',
                eg: 'Egipto', sv: 'El Salvador', gq: 'Guinea Ecuatorial', er: 'Eritrea',
                ee: 'Estonia', et: 'Etiopía', fj: 'Fiyi', fi: 'Finlandia',
                fr: 'Francia', gf: 'Guayana Francesa', pf: 'Polinesia Francesa',
                ga: 'Gabón', gm: 'Gambia', ge: 'Georgia', de: 'Alemania',
                gh: 'Ghana', gi: 'Gibraltar', gr: 'Grecia', gl: 'Groenlandia',
                gd: 'Granada', gp: 'Guadalupe', gu: 'Guam', gt: 'Guatemala',
                gn: 'Guinea', gw: 'Guinea-Bisáu', gy: 'Guyana', ht: 'Haití',
                hn: 'Honduras', hk: 'Hong Kong', hu: 'Hungría', is: 'Islandia',
                in: 'India', id: 'Indonesia', ir: 'Irán', iq: 'Irak',
                ie: 'Irlanda', il: 'Israel', it: 'Italia', jm: 'Jamaica',
                jp: 'Japón', jo: 'Jordania', kz: 'Kazajistán', ke: 'Kenia',
                ki: 'Kiribati', kp: 'Corea del Norte', kr: 'Corea del Sur', kw: 'Kuwait',
                kg: 'Kirguistán', la: 'Laos', lv: 'Letonia', lb: 'Líbano',
                ls: 'Lesoto', lr: 'Liberia', ly: 'Libia', li: 'Liechtenstein',
                lt: 'Lituania', lu: 'Luxemburgo', mo: 'Macao', mk: 'Macedonia del Norte',
                mg: 'Madagascar', mw: 'Malaui', my: 'Malasia', mv: 'Maldivas',
                ml: 'Malí', mt: 'Malta', mh: 'Islas Marshall', mq: 'Martinica',
                mr: 'Mauritania', mu: 'Mauricio', mx: 'México', fm: 'Micronesia',
                md: 'Moldavia', mc: 'Mónaco', mn: 'Mongolia', me: 'Montenegro',
                ms: 'Montserrat', ma: 'Marruecos', mz: 'Mozambique', mm: 'Birmania',
                na: 'Namibia', nr: 'Nauru', np: 'Nepal', nl: 'Países Bajos',
                nc: 'Nueva Caledonia', nz: 'Nueva Zelanda', ni: 'Nicaragua', ne: 'Níger',
                ng: 'Nigeria', nu: 'Niue', no: 'Noruega', om: 'Omán',
                pk: 'Pakistán', pw: 'Palaos', ps: 'Palestina', pa: 'Panamá',
                pg: 'Papúa Nueva Guinea', py: 'Paraguay', pe: 'Perú', ph: 'Filipinas',
                pl: 'Polonia', pt: 'Portugal', pr: 'Puerto Rico', qa: 'Catar',
                re: 'Reunión', ro: 'Rumania', ru: 'Rusia', rw: 'Ruanda',
                ws: 'Samoa', sm: 'San Marino', st: 'Santo Tomé y Príncipe', sa: 'Arabia Saudita',
                sn: 'Senegal', rs: 'Serbia', sc: 'Seychelles', sl: 'Sierra Leona',
                sg: 'Singapur', sk: 'Eslovaquia', si: 'Eslovenia', sb: 'Islas Salomón',
                so: 'Somalia', za: 'Sudáfrica', es: 'España', lk: 'Sri Lanka',
                sd: 'Sudán', sr: 'Surinam', sz: 'Esuatini', se: 'Suecia',
                ch: 'Suiza', sy: 'Siria', tw: 'Taiwán', tj: 'Tayikistán',
                tz: 'Tanzania', th: 'Tailandia', tl: 'Timor Oriental', tg: 'Togo',
                to: 'Tonga', tt: 'Trinidad y Tobago', tn: 'Túnez', tr: 'Turquía',
                tm: 'Turkmenistán', tv: 'Tuvalu', ug: 'Uganda', ua: 'Ucrania',
                ae: 'Emiratos Árabes Unidos', gb: 'Reino Unido', us: 'Estados Unidos',
                uy: 'Uruguay', uz: 'Uzbekistán', vu: 'Vanuatu', va: 'Ciudad del Vaticano',
                ve: 'Venezuela', vn: 'Vietnam', ye: 'Yemen', zm: 'Zambia',
                zw: 'Zimbabue'
            },
            // Cargar utils para validación avanzada
            loadUtils: () => import('https://cdn.jsdelivr.net/npm/intl-tel-input@25.15.0/build/js/utils.js')
        });

        // Hacer instancia accesible globalmente
        window._iti = iti;

        // Mapeo de errores según documentación oficial
        const errorMap = [
            'Número inválido',
            'Código de país inválido',
            'Número muy corto',
            'Número muy largo',
            'Número inválido'
        ];

        // Función mejorada de validación con errores específicos
        window.validatePhoneNumber = function() {
            const validFeedback = phoneInput.parentElement.querySelector('.valid-feedback');
            const invalidFeedback = phoneInput.parentElement.querySelector('.invalid-feedback');
            const errorDetails = document.querySelector('.phone-error-details');
            const hidden = document.querySelector('#celular_e164');
            
            if (!window._iti) return false;

            try {
                const value = phoneInput.value.trim();
                
                // Si está vacío, no mostrar error aún (solo si es required al enviar)
                if (!value) {
                    phoneInput.classList.remove('is-valid', 'is-invalid');
                    if (hidden) hidden.value = '';
                    return false;
                }

                // Aceptar números locales (9 dígitos) o internacionales válidos
                const isLocalNumber = /^\d{8,}$/.test(value.replace(/\D/g, ''));
                const isInternational = window._iti.isValidNumber();
                const startsWithPlus = value.trim().startsWith('+');
                
                if (isLocalNumber || isInternational) {
                    // Número válido (local o internacional)
                    phoneInput.classList.remove('is-invalid');
                    phoneInput.classList.add('is-valid');
                    if (validFeedback) validFeedback.classList.remove('d-none');
                    if (invalidFeedback) invalidFeedback.classList.add('d-none');
                    if (errorDetails) {
                        errorDetails.classList.add('d-none');
                        errorDetails.textContent = '';
                    }
                    
                    // El campo celular_e164 SIEMPRE debe tener el número en formato internacional
                    // Si es local, se convierte usando intl-tel-input. Si es internacional, se normaliza a E.164
                    let e164Number = value;
                    if (isInternational || (isLocalNumber && !startsWithPlus)) {
                        try {
                            e164Number = window._iti.getNumber();
                        } catch (e) {
                            // Fallback: si hay error, intentar convertir manualmente
                            const digitsOnly = value.replace(/\D/g, '');
                            e164Number = '+598' + digitsOnly.slice(-8); // Asume Uruguay
                        }
                    }
                    if (hidden) hidden.value = e164Number;
                    
                    console.info('[Preinscripcion] Teléfono válido', { mode: startsWithPlus ? 'international' : 'local' });
                    return true;
                } else {
                    // Número inválido
                    phoneInput.classList.remove('is-valid');
                    phoneInput.classList.add('is-invalid');
                    if (validFeedback) validFeedback.classList.add('d-none');
                    if (invalidFeedback) invalidFeedback.classList.remove('d-none');
                    if (hidden) hidden.value = '';
                    
                    if (errorDetails) {
                        errorDetails.classList.remove('d-none');
                        errorDetails.textContent = 'El número ingresado no es válido.';
                    }
                    console.warn('[Preinscripcion] Teléfono inválido');
                    return false;
                }
            } catch (error) {
                phoneInput.classList.remove('is-valid');
                phoneInput.classList.add('is-invalid');
                if (validFeedback) validFeedback.classList.add('d-none');
                if (invalidFeedback) invalidFeedback.classList.remove('d-none');
                if (errorDetails) {
                    errorDetails.classList.remove('d-none');
                    errorDetails.textContent = 'Error al validar.';
                }
                if (hidden) hidden.value = '';
                console.error('Error validando teléfono:', error);
                return false;
            }
        }

        // Event listeners para validación en tiempo real
        phoneInput.addEventListener('input', function() {
            clearTimeout(window.phoneValidationTimeout);
            window.phoneValidationTimeout = setTimeout(() => {
                window.validatePhoneNumber();
            }, 500); // Debounce: valida 500ms después de escribir
        });

        // Validar cuando cambia país - actualizar placeholder y ejemplo
        phoneInput.addEventListener('countrychange', function() {
            window.validatePhoneNumber();
            updatePhonePlaceholder();
        });

        // Función para actualizar placeholder según país seleccionado
        function updatePhonePlaceholder() {
            if (!window._iti) return;
            try {
                const countryData = window._iti.getSelectedCountryData();
                const dialCode = countryData.dialCode;
                const placeholder = `+${dialCode} ...`;
                
                // Actualizar placeholder y helper text (específico para teléfono)
                phoneInput.setAttribute('placeholder', placeholder);
                
                const helperText = document.querySelector('small.phone-helper-text');
                if (helperText) {
                    helperText.textContent = `Puedes ingresar: número local o formato internacional (${placeholder})`;
                }
            } catch (err) {
                console.warn('Error actualizando placeholder:', err);
            }
        }

        // Validar al perder el foco
        phoneInput.addEventListener('blur', function() {
            window.validatePhoneNumber();
        });

        // Validar cuando abre dropdown de países
        phoneInput.addEventListener('open:countrydropdown', function() {
            try { console.info('[Preinscripcion] Country dropdown opened', window._iti.getSelectedCountryData().name); } catch(e) {}
        });

        // Validar al enviar formulario
        const form = document.querySelector('#form-preinscripcion');
        if (form) {
            form.addEventListener('submit', function(e) {
                if (!window.validatePhoneNumber()) {
                    e.preventDefault();
                    e.stopPropagation();
                    phoneInput.focus();
                    window.scrollTo({ top: phoneInput.offsetTop - 100, behavior: 'smooth' });
                }
            }, false);
        }
        }); // Cierra window.addEventListener('load', ...)
    } // Cierra if (phoneInput)

    // Selector de país con banderas (country-select-js)
    jQuery(function($) {
        const $countryInput = $('#pais_residencia_input');
        const $countryHidden = $('#pais_residencia');
        if ($countryInput.length && typeof $countryInput.countrySelect === 'function') {
            // Inicializar con preferidos y default por teléfono/IP
            $countryInput.countrySelect({
                defaultCountry: 'auto',
                preferredCountries: ['uy','ar','br','cl','co','mx','pe','ve','es','pt'],
                responsiveDropdown: true,
                geoIpLookup: function(callback) {
                    fetch('https://ipapi.co/json')
                        .then(res => res.json())
                        .then(data => callback((data.country_code || 'UY').toLowerCase()))
                        .catch(() => callback('uy'));
                }
            });

            // Preseleccionar: POST → país del teléfono → Uruguay
            const preselected = '<?php echo isset($_POST["pais_residencia"]) ? esc_js(strtolower($_POST["pais_residencia"])) : ""; ?>'.toLowerCase();
            const phoneCountry = (window._iti && window._iti.getSelectedCountryData) 
                ? (window._iti.getSelectedCountryData().iso2 || '').toLowerCase() 
                : '';
            const toSelect = preselected || phoneCountry || 'uy';
            try { $countryInput.countrySelect('selectCountry', toSelect); } catch (e) {}

            // Validación y sincronización del valor (guardar el código del país en el hidden)
            // Crear elemento para bandera emoji (fallback si no carga el sprite)
            let $flagEmoji = $countryInput.parent().find('.flag-emoji');
            if ($flagEmoji.length === 0) {
                $flagEmoji = $('<span class="flag-emoji" aria-hidden="true"></span>');
                $countryInput.parent().append($flagEmoji);
            }

            const countryCodeToEmoji = (code) => {
                if (!code || code.length !== 2) return '';
                try {
                    return code.toUpperCase().split('').map(c => String.fromCodePoint(0x1F1E6 + c.charCodeAt(0) - 65)).join('');
                } catch (e) { return ''; }
            };

            const syncCountry = () => {
                try {
                    const data = $countryInput.countrySelect('getSelectedCountryData');
                    const code = (data && data.iso2) ? data.iso2.toLowerCase() : '';
                    if ($countryHidden.length) {
                        $countryHidden.val(code);
                        $countryHidden.toggleClass('is-invalid', !code);
                        $countryHidden.toggleClass('is-valid', !!code);
                    }
                    // Reflejar estado visual en el input
                    $countryInput.toggleClass('is-invalid', !code);
                    $countryInput.toggleClass('is-valid', !!code);

                    // Actualizar bandera emoji
                    const emoji = countryCodeToEmoji(code);
                    $flagEmoji.text(emoji);
                } catch (e) {}
            };

            // countrySelect triggers change on the input when selection changes
            $countryInput.on('change.countryselect input.countryselect', syncCountry);

            // Force initial selection and sync
            try { 
                if (toSelect) {
                    $countryInput.countrySelect('selectCountry', toSelect);
                }
            } catch(e) {}
            syncCountry();
        }
    });

    // Mostrar/ocultar campo de posgrado y hacer required dinámicamente
    const posgradoRadios = document.querySelectorAll('input[name="posgrado_flacso"]');
    const containerPosgrado = document.querySelector('#posgrado_cual_container');
    const inputPosgradoCual = document.querySelector('#posgrado_cual');
    if (posgradoRadios.length && containerPosgrado) {
        const togglePosgrado = () => {
            const selected = document.querySelector('input[name="posgrado_flacso"]:checked');
            const isSi = selected && selected.value === 'si';
            containerPosgrado.style.display = isSi ? 'block' : 'none';
            // Hacer required si está visible
            if (inputPosgradoCual) {
                inputPosgradoCual.required = isSi;
            }
        };
        posgradoRadios.forEach(radio => radio.addEventListener('change', togglePosgrado));
        togglePosgrado();
    }

    // Mostrar/ocultar "Otra" en identidad de genero
    const generoSelect = document.querySelector('#genero');
    const generoOtraContainer = document.querySelector('#genero_otra_container');
    const generoOtraInput = document.querySelector('#genero_otra');
    if (generoSelect && generoOtraContainer && generoOtraInput) {
        const toggleGeneroOtra = () => {
            const isOtra = generoSelect.value === 'Otra';
            generoOtraContainer.style.display = isOtra ? 'block' : 'none';
            generoOtraInput.required = isOtra;
            if (!isOtra) {
                generoOtraInput.value = '';
                generoOtraInput.classList.remove('is-invalid', 'is-valid');
            }
        };
        generoSelect.addEventListener('change', toggleGeneroOtra);
        toggleGeneroOtra();
    }

    // Mostrar/ocultar campos según tipo de documento
    const tipoDoc = document.querySelector('#tipo_documento');
    const campoCi = document.querySelector('#campo_ci');
    const campoOtroDoc = document.querySelector('#campo_otro_doc');
    const inputCi = document.querySelector('#documento_ci');
    const inputOtro = document.querySelector('#documento_otro');
    
    if (tipoDoc && campoCi && campoOtroDoc && inputCi && inputOtro) {
        const toggleDocFields = () => {
            const tipo = tipoDoc.value;
            
            if (tipo === 'CI') {
                // Mostrar campo CI, ocultar otros
                campoCi.style.display = 'block';
                campoOtroDoc.style.display = 'none';
                inputCi.setAttribute('required', 'required');
                inputOtro.removeAttribute('required');
                inputOtro.value = '';
                inputCi.classList.remove('is-invalid', 'is-valid');
            } else if (tipo === 'Pasaporte' || tipo === 'Otro') {
                // Mostrar campo otros, ocultar CI
                campoCi.style.display = 'none';
                campoOtroDoc.style.display = 'block';
                inputOtro.setAttribute('required', 'required');
                inputCi.removeAttribute('required');
                inputCi.value = '';
                inputOtro.classList.remove('is-invalid', 'is-valid');
                
                // Actualizar label según tipo
                const labelOtro = campoOtroDoc.querySelector('label');
                const placeholderOtro = tipo === 'Pasaporte' ? 'AB1234567' : 'Tu número de documento';
                inputOtro.setAttribute('placeholder', placeholderOtro);
                if (labelOtro) {
                    labelOtro.innerHTML = (tipo === 'Pasaporte' ? 'Número de Pasaporte' : 'Número de Documento') + ' <span class="required">*</span>';
                }
            } else {
                // Ninguno seleccionado, ocultar ambos
                campoCi.style.display = 'none';
                campoOtroDoc.style.display = 'none';
                inputCi.removeAttribute('required');
                inputOtro.removeAttribute('required');
            }
        };
        
        tipoDoc.addEventListener('change', toggleDocFields);
        toggleDocFields(); // Aplicar estado inicial
        
        // Validación para CI: solo números
        inputCi.addEventListener('input', (e) => {
            // Eliminar cualquier carácter no numérico
            e.target.value = e.target.value.replace(/[^0-9]/g, '');
        });
        
        // Validar CI al escribir
        inputCi.addEventListener('keyup', () => {
            const docValue = inputCi.value.trim();
            if (docValue) {
                const isValid = validate_ci(docValue);
                inputCi.classList.toggle('is-invalid', !isValid);
                inputCi.classList.toggle('is-valid', isValid);
            }
        });
        
        inputCi.addEventListener('blur', () => {
            const docValue = inputCi.value.trim();
            if (!docValue) return;
            
            const isValid = validate_ci(docValue);
            inputCi.classList.toggle('is-invalid', !isValid);
            inputCi.classList.toggle('is-valid', isValid);
            
            const fb = document.querySelector('.ci-feedback');
            if (fb) {
                fb.textContent = isValid ? '✓ Cédula válida.' : '✗ La cédula no es válida. Verifica que tenga 7 u 8 dígitos con dígito verificador.';
            }
        });
        
        // Validación para otros documentos: 3-20 caracteres
        inputOtro.addEventListener('keyup', () => {
            const docValue = inputOtro.value.trim();
            if (docValue) {
                const isValid = docValue.length >= 3 && docValue.length <= 20;
                inputOtro.classList.toggle('is-invalid', !isValid);
                inputOtro.classList.toggle('is-valid', isValid);
            }
        });
        
        inputOtro.addEventListener('blur', () => {
            const docValue = inputOtro.value.trim();
            if (!docValue) return;
            
            const isValid = docValue.length >= 3 && docValue.length <= 20;
            inputOtro.classList.toggle('is-invalid', !isValid);
            inputOtro.classList.toggle('is-valid', isValid);
        });
    }

    // Validación de edad (mayor de 17 años) para fecha de nacimiento
    const fechaNacInput = document.querySelector('#fecha_nacimiento');
    if (fechaNacInput) {
        const validateAge = () => {
            const value = fechaNacInput.value;
            if (!value) return;
            
            const birthDate = new Date(value);
            const today = new Date();
            let age = today.getFullYear() - birthDate.getFullYear();
            const monthDiff = today.getMonth() - birthDate.getMonth();
            
            // Ajustar si aún no cumplió años este año
            if (monthDiff < 0 || (monthDiff === 0 && today.getDate() < birthDate.getDate())) {
                age--;
            }
            
            const isValid = age >= 17;
            fechaNacInput.classList.toggle('is-invalid', !isValid);
            fechaNacInput.classList.toggle('is-valid', isValid);
            
            const feedback = fechaNacInput.parentElement.querySelector('.invalid-feedback');
            if (feedback && !isValid) {
                feedback.textContent = 'Debes ser mayor de 17 años.';
            }
        };
        
        fechaNacInput.addEventListener('change', validateAge);
        fechaNacInput.addEventListener('blur', validateAge);
        
        // Validar al enviar
        const form = document.querySelector('#form-preinscripcion');
        if (form) {
            form.addEventListener('submit', function(e) {
                validateAge();
                if (fechaNacInput.classList.contains('is-invalid')) {
                    e.preventDefault();
                    e.stopPropagation();
                    fechaNacInput.focus();
                }
            });
        }
    }

    // Validación en tiempo real para todos los campos (excepto teléfono que tiene lógica propia)
    const liveForm = document.querySelector('#form-preinscripcion');
    if (liveForm) {
        const fields = liveForm.querySelectorAll('input, select, textarea');

        // Validar tamaño total de archivos (máximo 25 MB)
        const validateFileSize = () => {
            const maxSizeMB = 25;
            const maxSizeBytes = maxSizeMB * 1024 * 1024; // 25 MB en bytes
            let totalSize = 0;
            let fileSizeError = '';
            
            // Sumar tamaño de documento_identidad
            const docIdentidadInput = liveForm.querySelector('#documento_identidad');
            if (docIdentidadInput && docIdentidadInput.files) {
                for (let file of docIdentidadInput.files) {
                    totalSize += file.size;
                }
            }
            
            // Sumar tamaño de titulo
            const tituloInput = liveForm.querySelector('#titulo');
            if (tituloInput && tituloInput.files && tituloInput.files.length > 0) {
                for (let file of tituloInput.files) {
                    totalSize += file.size;
                }
            }
            
            if (totalSize > maxSizeBytes) {
                const sizeMB = (totalSize / (1024 * 1024)).toFixed(2);
                fileSizeError = `El tamaño total de los archivos (${sizeMB} MB) excede el límite de ${maxSizeMB} MB. Por favor selecciona archivos más pequeños.`;
            }
            
            return { valid: !fileSizeError, error: fileSizeError };
        };
        
        // Validar tamaño al cambiar archivos
        const fileInputs = liveForm.querySelectorAll('input[type="file"]');
        fileInputs.forEach(input => {
            input.addEventListener('change', () => {
                const validation = validateFileSize();
                if (!validation.valid) {
                    // Mostrar error
                    let errorContainer = liveForm.previousElementSibling;
                    if (!errorContainer || !errorContainer.classList.contains('alert-danger')) {
                        errorContainer = document.createElement('div');
                        liveForm.parentNode.insertBefore(errorContainer, liveForm);
                    }
                    errorContainer.className = 'alert alert-danger';
                    errorContainer.innerHTML = '<div class="d-flex align-items-start"><div class="flex-shrink-0"><i class="fas fa-exclamation-circle" style="font-size: 2rem;"></i></div><div class="flex-grow-1" style="margin-left: 1rem;"><h4 style="margin: 0 0 0.5rem 0;">Error con los archivos</h4><p style="margin: 0;">' + validation.error + '</p></div></div>';
                    input.classList.add('is-invalid');
                    input.scrollIntoView({ behavior: 'smooth', block: 'center' });
                } else {
                    input.classList.remove('is-invalid');
                    const errorContainer = liveForm.previousElementSibling;
                    if (errorContainer && errorContainer.classList.contains('alert-danger') && errorContainer.textContent.includes('tamaño')) {
                        errorContainer.remove();
                    }
                }
            });
        });

        const validateField = (el) => {
            if (el.id === 'celular') return; // ya validado por función dedicada
            if (el.id === 'documento_ci' || el.id === 'documento_otro') return; // ya validado por funciones dedicadas
            if (el.id === 'fecha_nacimiento') return; // ya validado por validateAge
            
            const isRadio = el.type === 'radio';
            if (isRadio) {
                const group = liveForm.querySelectorAll(`input[type="radio"][name="${el.name}"]`);
                const anyChecked = Array.from(group).some(r => r.checked);
                group.forEach(r => {
                    r.classList.toggle('is-invalid', r.required && !anyChecked);
                    r.classList.toggle('is-valid', r.required && anyChecked);
                });
                return anyChecked;
            }
            const valid = el.checkValidity();
            el.classList.toggle('is-invalid', !valid && (el.value !== '' || el.required));
            el.classList.toggle('is-valid', valid && (el.value !== '' || el.required));
            return valid;
        };

        fields.forEach(el => {
            const evt = (el.tagName === 'SELECT' || el.type === 'radio' || el.type === 'checkbox' || el.type === 'date') ? 'change' : 'input';
            // Solo validar el campo específico sin agregar was-validated al form
            el.addEventListener(evt, () => { validateField(el); });
            el.addEventListener('blur', () => { validateField(el); });
        });
        
        // Bootstrap validation: agregar was-validated solo al enviar
        liveForm.addEventListener('submit', function(event) {
            event.preventDefault();
            event.stopPropagation();
            
            // Validar tamaño de archivos antes de cualquier otra cosa
            const fileSizeValidation = validateFileSize();
            if (!fileSizeValidation.valid) {
                event.preventDefault();
                let errorContainer = liveForm.previousElementSibling;
                if (!errorContainer || !errorContainer.classList.contains('alert-danger')) {
                    errorContainer = document.createElement('div');
                    liveForm.parentNode.insertBefore(errorContainer, liveForm);
                }
                errorContainer.className = 'alert alert-danger';
                errorContainer.innerHTML = '<div class="d-flex align-items-start"><div class="flex-shrink-0"><i class="fas fa-exclamation-circle" style="font-size: 2rem;"></i></div><div class="flex-grow-1" style="margin-left: 1rem;"><h4 style="margin: 0 0 0.5rem 0;">Error con los archivos</h4><p style="margin: 0;">' + fileSizeValidation.error + '</p></div></div>';
                errorContainer.scrollIntoView({ behavior: 'smooth', block: 'center' });
                return false;
            }
            
            // Validar country field
            const countryField = liveForm.querySelector('#pais_residencia');
            if (countryField) {
                const isCountryValid = countryField.value.trim() !== '';
                countryField.classList.toggle('is-invalid', !isCountryValid);
                countryField.classList.toggle('is-valid', isCountryValid);
            }
            
            // Validar tipo de documento y documento
            const tipoDocField = liveForm.querySelector('#tipo_documento');
            const docCiField = liveForm.querySelector('#documento_ci');
            const docOtroField = liveForm.querySelector('#documento_otro');
            
            let docIsValid = false;
            const tipoDoc = tipoDocField.value;
            
            if (!tipoDoc) {
                tipoDocField.classList.add('is-invalid');
                docIsValid = false;
            } else {
                tipoDocField.classList.remove('is-invalid');
                tipoDocField.classList.add('is-valid');
                
                // Validar solo el documento que está visible
                if (tipoDoc === 'CI') {
                    const ciValue = docCiField.value.trim();
                    docIsValid = ciValue && validate_ci(ciValue);
                    docCiField.classList.toggle('is-invalid', !docIsValid);
                    docCiField.classList.toggle('is-valid', docIsValid);
                } else if (tipoDoc === 'Pasaporte' || tipoDoc === 'Otro') {
                    const otroValue = docOtroField.value.trim();
                    docIsValid = otroValue && otroValue.length >= 3 && otroValue.length <= 20;
                    docOtroField.classList.toggle('is-invalid', !docIsValid);
                    docOtroField.classList.toggle('is-valid', docIsValid);
                }
            }
            
            // Validar teléfono
            const phoneIsValid = (typeof window.validatePhoneNumber === 'function') ? window.validatePhoneNumber() : true;
            
            // Validar todos los demás campos
            let isFormValid = liveForm.checkValidity() && docIsValid && phoneIsValid;
            
            // Validar edad
            const ageField = liveForm.querySelector('#fecha_nacimiento');
            if (ageField && ageField.classList.contains('is-invalid')) {
                isFormValid = false;
            }
            
            // Marcar formulario como validado visualmente
            liveForm.classList.add('was-validated');
            
            // Si no es válido, no enviar
            if (!isFormValid) {
                console.warn('[Preinscripcion] Validación fallida', { phoneValid: phoneIsValid, documentValid: docIsValid, formValidity: liveForm.checkValidity() });
                // Scroll al primer error
                const firstError = liveForm.querySelector('.is-invalid');
                if (firstError) {
                    firstError.scrollIntoView({ behavior: 'smooth', block: 'center' });
                }
                return false;
            }
            
            // Si todo es válido, enviar vía AJAX
            console.info('[Preinscripcion] Formulario válido — enviando vía AJAX');
            console.log('[Preinscripcion] Iniciando proceso de envío...');
            
            // Deshabilitar botón de envío y mostrar mensaje de carga
            const submitBtn = liveForm.querySelector('button[type="submit"]');
            const btnOriginalText = submitBtn.innerHTML;
            const loadingMessage = liveForm.querySelector('.submit-loading-message');
            submitBtn.disabled = true;
            submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin" style="margin-right: 0.5rem;"></i>Enviando...';
            console.log('[Preinscripcion] Botón de envío deshabilitado');
            
            if (loadingMessage) {
                loadingMessage.style.display = 'block';
                console.log('[Preinscripcion] Mensaje de carga mostrado');
            }
            
            // Preparar FormData (incluye archivos)
            const formData = new FormData(liveForm);
            console.log('[Preinscripcion] FormData preparado:', {
                nombre: formData.get('nombre1'),
                apellido: formData.get('apellido1'),
                correo: formData.get('correo'),
                tipo_documento: formData.get('tipo_documento'),
                pais_residencia: formData.get('pais_residencia')
            });
            
            // Contar archivos
            const docIdentidadFiles = formData.getAll('documento_identidad[]');
            const tituloFile = formData.get('titulo');
            console.log('[Preinscripcion] Archivos adjuntos:', {
                documento_identidad: docIdentidadFiles.length + ' archivo(s)',
                titulo: tituloFile ? '1 archivo' : 'ninguno'
            });
            
            console.log('[Preinscripcion] Enviando petición POST a:', window.location.href);
            
            // Enviar vía fetch
            fetch(window.location.href, {
                method: 'POST',
                body: formData
            })
            .then(response => {
                console.log('[Preinscripcion] Respuesta recibida - Status:', response.status);
                return response.text();
            })
            .then(html => {
                console.log('[Preinscripcion] HTML recibido (longitud):', html.length, 'caracteres');
                
                // Parsear la respuesta para detectar éxito o error
                const parser = new DOMParser();
                const doc = parser.parseFromString(html, 'text/html');
                
                // Buscar el contenedor de éxito
                const successContainer = doc.querySelector('.success-container');
                console.log('[Preinscripcion] ¿Contenedor de éxito encontrado?', successContainer ? 'SÍ' : 'NO');
                
                if (successContainer) {
                    console.log('[Preinscripcion] ✓ Preinscripción exitosa');

                    const pixelPayload = {
                        content_name: '<?php echo esc_js($seminario_titulo); ?>',
                        content_category: 'preinscripcion_seminario',
                        status: 'completed'
                    };
                    trackMetaEvent('SubmitApplication', pixelPayload);
                    
                    // Éxito: ocultar formulario y mostrar mensaje
                    const formContainer = document.querySelector('.form-container');
                    if (formContainer) {
                        formContainer.style.display = 'none';
                        console.log('[Preinscripcion] Formulario ocultado');
                    }
                    
                    // Insertar mensaje de éxito
                    const contentDiv = document.querySelector('.site-container');
                    const successDiv = document.createElement('div');
                    successDiv.innerHTML = successContainer.outerHTML;
                    console.log('[Preinscripcion] Mensaje de éxito preparado');
                    
                    // Reemplazar o insertar después del hero
                    const existingSuccess = document.querySelector('.success-container');
                    if (existingSuccess) {
                        existingSuccess.replaceWith(successDiv.firstElementChild);
                        console.log('[Preinscripcion] Mensaje de éxito reemplazado');
                    } else {
                        const hero = document.querySelector('.preinsc-hero');
                        if (hero && hero.nextElementSibling) {
                            hero.nextElementSibling.insertBefore(successDiv.firstElementChild, hero.nextElementSibling.firstChild);
                        } else {
                            contentDiv.insertBefore(successDiv.firstElementChild, contentDiv.firstChild);
                        }
                        console.log('[Preinscripcion] Mensaje de éxito insertado');
                    }
                    
                    // Scroll suave al mensaje de éxito
                    window.scrollTo({ top: 0, behavior: 'smooth' });
                    console.log('[Preinscripcion] Scroll al inicio completado');
                } else {
                    console.warn('[Preinscripcion] ✗ Error en el envío - no se encontró contenedor de éxito');
                    
                    // Error: buscar mensaje de error en la respuesta
                    const alertDanger = doc.querySelector('.alert-danger');
                    
                    // Buscar o crear contenedor de error antes del formulario
                    let errorContainer = liveForm.previousElementSibling;
                    if (!errorContainer || !errorContainer.classList.contains('alert')) {
                        errorContainer = document.createElement('div');
                        liveForm.parentNode.insertBefore(errorContainer, liveForm);
                    }
                    
                    if (alertDanger) {
                        errorContainer.className = 'alert alert-danger';
                        errorContainer.innerHTML = alertDanger.innerHTML;
                        console.log('[Preinscripcion] Mensaje de error del servidor mostrado');
                    } else {
                        errorContainer.className = 'alert alert-danger';
                        errorContainer.innerHTML = '<div class="d-flex align-items-start"><div class="flex-shrink-0"><i class="fas fa-exclamation-circle" style="font-size: 2rem;"></i></div><div class="flex-grow-1" style="margin-left: 1rem;"><h4 style="margin: 0 0 0.5rem 0;">Error al enviar</h4><p style="margin: 0;">Hubo un problema al procesar tu preinscripción. Por favor verifica los datos e intenta nuevamente.</p></div></div>';
                        console.log('[Preinscripcion] Mensaje de error genérico mostrado');
                    }
                    
                    // Scroll al error
                    errorContainer.scrollIntoView({ behavior: 'smooth', block: 'center' });
                    console.log('[Preinscripcion] Scroll al error completado');
                    
                    // Restaurar botón
                    submitBtn.disabled = false;
                    submitBtn.innerHTML = btnOriginalText;
                    if (loadingMessage) {
                        loadingMessage.style.display = 'none';
                    }
                    console.log('[Preinscripcion] Botón de envío restaurado');
                }
            })
            .catch(error => {
                console.error('[Preinscripcion] ✗ Error de red capturado:', error);
                console.error('[Preinscripcion] Detalles del error:', {
                    name: error.name,
                    message: error.message,
                    stack: error.stack
                });
                
                // Mostrar error genérico
                let errorContainer = liveForm.previousElementSibling;
                if (!errorContainer || !errorContainer.classList.contains('alert')) {
                    errorContainer = document.createElement('div');
                    liveForm.parentNode.insertBefore(errorContainer, liveForm);
                }
                
                errorContainer.className = 'alert alert-danger';
                errorContainer.innerHTML = '<div class="d-flex align-items-start"><div class="flex-shrink-0"><i class="fas fa-exclamation-circle" style="font-size: 2rem;"></i></div><div class="flex-grow-1" style="margin-left: 1rem;"><h4 style="margin: 0 0 0.5rem 0;">Error de conexión</h4><p style="margin: 0;">No se pudo conectar con el servidor. Por favor verifica tu conexión e intenta nuevamente.</p></div></div>';
                
                errorContainer.scrollIntoView({ behavior: 'smooth', block: 'center' });
                console.log('[Preinscripcion] Mensaje de error de conexión mostrado');
                
                // Restaurar botón
                submitBtn.disabled = false;
                submitBtn.innerHTML = btnOriginalText;
                if (loadingMessage) {
                    loadingMessage.style.display = 'none';
                }
                console.log('[Preinscripcion] Botón de envío restaurado después de error');
            });
            
            console.log('[Preinscripcion] Petición fetch iniciada, esperando respuesta...');
            return false;
        }, false);
    }
})();
</script>

</div>
</main>

<?php get_footer();


