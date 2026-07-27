<?php
if (!defined('ABSPATH')) { exit; }

trait FLACSO_Formulario_Preinscripcion_Render {
    public function render_hero_header($info) {
        $preinscripciones_cerradas = !empty($info['preinscripcion_cerrada']);
        $id_programa = !empty($info['id_posgrado']) ? (int) $info['id_posgrado'] : (!empty($info['parent_page_id']) ? (int) $info['parent_page_id'] : 0);
        $programa_url = $id_programa > 0 ? get_permalink($id_programa) : '';
        $formulario_consultas_url = $programa_url
            ? trailingslashit($programa_url) . '#form-consultas-container'
            : home_url('/contactos/');
        $seminarios_posgrado_url = home_url('/formacion/seminarios/');
        $mensaje_cierre_parrafo_1 = 'En este momento, las inscripciones a este posgrado se encuentran cerradas. Le invitamos a completar el formulario de consultas para que podamos contactarle en la próxima apertura.';
        $mensaje_cierre_parrafo_2 = 'Asimismo, puede explorar nuestras propuestas de seminarios de posgrado.';
        $badge_text = $preinscripciones_cerradas
            ? 'Preinscripciones cerradas'
            : 'Convocatoria 2026 - Inscripciones abiertas';
        $descripcion = 'Presenta tu solicitud y adjunta la documentación requerida para iniciar el proceso de admisión. El formulario te guiará paso a paso.';
        $checklist_items = $preinscripciones_cerradas
            ? array()
            : array(
                'Completa los datos personales y de contacto.',
                'Adjunta carta de motivación y documentos de identidad.',
                'Recibirás confirmación por correo al finalizar.',
            );

        $bg_style = '';
        if ($info['imagen_destacada']) {
            $bg_style = 'style="background: linear-gradient(135deg, rgba(29,58,114,.9) 0%, rgba(15,26,45,.8) 100%), url(' . esc_url($info['imagen_destacada']) . ') center center/cover;"';
        } else {
            $bg_style = 'style="background: linear-gradient(135deg, #1d3a72 0%, #0f1a2d 100%);"';
        } ?>
        <header class="flacso-hero-header" <?php echo $bg_style; ?>>
            <div class="container">
                <div class="flacso-hero-layout">
                    <div class="flacso-hero-copy">
                        <p class="flacso-hero-badge"><?php echo esc_html($badge_text); ?></p>
                        <h1 class="flacso-hero-title"><?php echo esc_html($info['titulo_posgrado']); ?></h1>
                        <?php if ($preinscripciones_cerradas): ?>
                            <p class="flacso-hero-description"><?php echo esc_html($mensaje_cierre_parrafo_1); ?></p>
                            <p class="flacso-hero-description flacso-hero-description--compact"><?php echo esc_html($mensaje_cierre_parrafo_2); ?></p>
                            <div class="flacso-cierre-actions">
                                <a class="flacso-cierre-btn flacso-cierre-btn--primary" href="<?php echo esc_url($formulario_consultas_url); ?>">Completar solicitud de información</a>
                                <a class="flacso-cierre-btn flacso-cierre-btn--secondary" href="<?php echo esc_url($seminarios_posgrado_url); ?>">Ver seminarios de posgrado</a>
                            </div>
                        <?php else: ?>
                            <p class="flacso-hero-description"><?php echo esc_html($descripcion); ?></p>
                        <?php endif; ?>
                        <?php if (!empty($checklist_items)): ?>
                            <ul class="flacso-hero-checklist">
                                <?php foreach ($checklist_items as $item): ?>
                                    <li><i class="bi bi-check-circle"></i> <?php echo esc_html($item); ?></li>
                                <?php endforeach; ?>
                            </ul>
                        <?php endif; ?>
                        <?php if (!$preinscripciones_cerradas): ?>
                            <div class="flacso-hero-actions">
                                <a class="flacso-btn-primary" href="#flacso-formulario-preinscripcion">
                                    <i class="bi bi-pencil-square"></i>
                                    Comenzar solicitud
                                </a>
                                <a class="flacso-btn-convenios" href="https://flacso.edu.uy/convenios/" target="_blank" rel="noopener noreferrer">
                                    <i class="bi bi-box-arrow-up-right"></i>
                                    Ver convenios disponibles
                                </a>
                            </div>
                        <?php endif; ?>
                    </div>
                    <div class="flacso-hero-card" aria-label="Resumen de estado">
                        <?php if ($preinscripciones_cerradas): ?>
                            <div class="flacso-hero-card-header">
                                <span class="flacso-hero-card-label">Estado actual</span>
                                <h3>Preinscripciones cerradas</h3>
                            </div>
                            <ul class="flacso-hero-card-list">
                                <li><strong>Estado:</strong> Cerramos temporalmente las preinscripciones.</li>
                                <li><strong>Próximo período:</strong> Lo confirmaremos.</li>
                                <li><strong>Soporte:</strong> inscripciones@flacso.edu.uy</li>
                            </ul>
                        <?php else: ?>
                            <div class="flacso-hero-card-header">
                                <span class="flacso-hero-card-label">Checklist rápido</span>
                                <h3>Antes de comenzar</h3>
                            </div>
                            <ul class="flacso-hero-card-list">
                                <li><strong>Documentación:</strong> Carta de motivación y documento vigente.</li>
                                <li><strong>Duración estimada:</strong> 12 a 15 minutos para completar todo.</li>
                                <li><strong>Soporte:</strong> inscripciones@flacso.edu.uy</li>
                            </ul>
                            <div class="flacso-hero-metrics">
                                <div class="flacso-hero-metric">
                                    <span>Paso 1</span>
                                    <strong>Datos personales</strong>
                                </div>
                                <div class="flacso-hero-metric">
                                    <span>Paso 2</span>
                                    <strong>Documentación</strong>
                                </div>
                                <div class="flacso-hero-metric">
                                    <span>Paso 3</span>
                                    <strong>Envío y confirmación</strong>
                                </div>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </header>
        <?php
    }

    public function render_aviso_preinscripciones_cerradas($info = array()) { ?>
        <section class="flacso-preinscripcion-cierre">
            <div class="container">
                <div class="flacso-preinscripcion-cierre-card" role="status" aria-live="polite">
                    <?php
                    $id_programa = !empty($info['id_posgrado']) ? (int) $info['id_posgrado'] : (!empty($info['parent_page_id']) ? (int) $info['parent_page_id'] : 0);
                    $programa_url = $id_programa > 0 ? get_permalink($id_programa) : '';
                    $formulario_consultas_url = $programa_url
                        ? trailingslashit($programa_url) . '#form-consultas-container'
                        : home_url('/contactos/');
                    $seminarios_posgrado_url = home_url('/formacion/seminarios/');
                    $mensaje_cierre_parrafo_1 = 'En este momento, las inscripciones a este posgrado se encuentran cerradas. Le invitamos a completar el formulario de consultas para que podamos contactarle en la próxima apertura.';
                    $mensaje_cierre_parrafo_2 = 'Asimismo, puede explorar nuestras propuestas de seminarios de posgrado.';
                    ?>
                    <h2><i class="bi bi-info-circle-fill me-2"></i>Preinscripciones cerradas</h2>
                    <p><?php echo esc_html($mensaje_cierre_parrafo_1); ?></p>
                    <p><?php echo esc_html($mensaje_cierre_parrafo_2); ?></p>
                    <div class="flacso-cierre-actions">
                        <a class="flacso-cierre-btn flacso-cierre-btn--primary" href="<?php echo esc_url($formulario_consultas_url); ?>">Completar solicitud de información</a>
                        <a class="flacso-cierre-btn flacso-cierre-btn--secondary" href="<?php echo esc_url($seminarios_posgrado_url); ?>">Ver seminarios de posgrado</a>
                    </div>
                </div>
            </div>
        </section>
    <?php }

    public function render_campos_ocultos($info) {
        $id_posgrado = $info['parent_page_id'] ?: $info['page_id']; ?>
        <input type="hidden" name="id_pagina" value="<?php echo esc_attr($id_posgrado); ?>">
        <input type="hidden" name="titulo_posgrado" value="<?php echo esc_attr($info['titulo_posgrado']); ?>">
        <input type="hidden" name="posgradoAlQuePostula" value="<?php echo esc_attr($info['titulo_posgrado']); ?>">
        <input type="hidden" name="es_maestria" value="<?php echo $info['es_maestria'] ? 'si' : 'no'; ?>">
        <input type="hidden" name="submission_id" id="flacso-preinscripcion-submission-id" value="<?php echo esc_attr(wp_generate_uuid4()); ?>">
        <input type="hidden" name="nonce" value="<?php echo wp_create_nonce('flacso_form_nonce'); ?>">
        <input type="hidden" name="action" value="flacso_enviar_preinscripcion">
        <!-- Celular en E.164 (se completa en JS si es válido) -->
        <input type="hidden" name="celular_e164" id="celular_e164" value="">
        <?php if (function_exists('fc_render_campaign_attribution_hidden_fields')) { fc_render_campaign_attribution_hidden_fields(function_exists('fc_get_current_request_url') ? fc_get_current_request_url() : '', wp_get_referer() ?: ''); } ?>
        <?php
    }

    public function render_seccion_correo() { ?>
        <section class="flacso-seccion mb-4">
            <div class="flacso-seccion-header mb-3">
                <div class="flacso-seccion-icon"><i class="bi bi-envelope"></i></div>
                <div class="flacso-seccion-content">
                    <h2 class="flacso-seccion-title">Correo Electrónico</h2>
                    <p class="flacso-seccion-descripcion">Utiliza un correo válido y activo para recibir confirmaciones</p>
                </div>
            </div>

            <div class="flacso-input-group">
                <label for="correo" class="form-label fw-semibold mb-2">
                    Correo Electrónico <span class="text-danger">*</span>
                </label>
                <input type="email"
                       name="correo"
                       id="correo"
                       class="form-control form-control-flacso"
                       required
                       autocomplete="email"
                       inputmode="email"
                       placeholder="ejemplo@correo.com">
                <div class="invalid-feedback">Por favor ingrese un correo electrónico válido.</div>
            </div>
        </section>
    <?php }

    public function render_seccion_info_personal() { ?>
        <section class="flacso-seccion mb-4">
            <div class="flacso-seccion-header mb-3">
                <div class="flacso-seccion-icon"><i class="bi bi-person-vcard"></i></div>
                <div class="flacso-seccion-content">
                    <h2 class="flacso-seccion-title">Información Personal</h2>
                    <p class="flacso-seccion-descripcion">Datos personales y de identificación</p>
                </div>
            </div>

            <div class="flacso-campos-vertical">
                <?php
                $this->render_campo_texto('nombre1',   'Primer Nombre', 'text', true,  'given-name', 'Tu nombre');
                $this->render_campo_texto('nombre2',   'Segundo Nombre', 'text', false, 'additional-name', 'Tu segundo nombre (opcional)');
                $this->render_campo_texto('apellido1', 'Primer Apellido', 'text', true,  'family-name', 'Tu apellido');
                $this->render_campo_texto('apellido2', 'Segundo Apellido', 'text', false, 'family-name', 'Tu segundo apellido (opcional)');
                $this->render_campo_texto('fecha_nacimiento', 'Fecha de Nacimiento', 'date', true, 'bday', 'dd/mm/aaaa');
                $this->render_campos_documento();
                $this->render_campos_identidad();
                ?>
            </div>
        </section>
    <?php }

    public function render_campo_texto($id, $label, $type = 'text', $required = false, $autocomplete = null, $placeholder = null) {
        $required_attr = $required ? 'required' : '';
        $required_badge = $required ? ' <span class="text-danger">*</span>' : '';
        $autocomplete_attr = $autocomplete ? 'autocomplete="' . esc_attr($autocomplete) . '"' : '';
        
        // Para fecha de nacimiento, calcular edad mínima de 18 años
        $extra_attrs = '';
        if ($id === 'fecha_nacimiento' && $type === 'date') {
            $fecha_maxima = date('Y-m-d', strtotime('-18 years'));
            $extra_attrs = 'max="' . $fecha_maxima . '"';
        }
        ?>
        <div class="flacso-input-group">
            <label for="<?php echo esc_attr($id); ?>" class="form-label fw-semibold mb-2">
                <?php echo esc_html($label) . $required_badge; ?>
            </label>
            <input type="<?php echo esc_attr($type); ?>"
                   name="<?php echo esc_attr($id); ?>"
                   id="<?php echo esc_attr($id); ?>"
                   class="form-control form-control-flacso"
                   <?php echo $required_attr; ?>
                   <?php echo $autocomplete_attr; ?>
                   <?php echo $extra_attrs; ?>
                   placeholder="<?php echo esc_attr($placeholder ?: $label); ?>">
            <?php if ($required): ?>
                <div class="invalid-feedback">Este campo es obligatorio.</div>
            <?php endif; ?>
            <?php if ($id === 'fecha_nacimiento'): ?>
                <div class="form-text mt-1">
                    <i class="bi bi-info-circle"></i> Debe tener al menos 18 años.
                </div>
            <?php endif; ?>
        </div>
    <?php }

    public function render_campos_documento() { ?>
        <div class="flacso-input-group">
            <label for="tipo_documento" class="form-label fw-semibold mb-2">
                Tipo de Documento <span class="text-danger">*</span>
            </label>
            <select name="tipo_documento" id="tipo_documento" class="form-select form-select-flacso" required>
                <option value="">Seleccionar tipo</option>
                <option value="cedula_uruguaya">Cédula de Identidad Uruguaya</option>
                <option value="pasaporte">Pasaporte</option>
                <option value="documento_extranjero">Documento Extranjero</option>
                <option value="otro">Otro documento</option>
            </select>
            <div class="invalid-feedback">Por favor seleccione un tipo de documento.</div>
        </div>

        <div class="flacso-input-group" id="contenedor-cedula" style="display:none;">
            <label for="cedula_uruguaya" class="form-label fw-semibold mb-2">
                Cédula de Identidad Uruguaya <span class="text-danger">*</span>
            </label>
            <input type="text"
                   name="cedula_uruguaya"
                   id="cedula_uruguaya"
                   class="form-control form-control-flacso"
                   placeholder="Ej: 4.123.456-7"
                   inputmode="numeric"
                   pattern="[0-9.\-]{9,11}"
                   minlength="7"
                   maxlength="11"
                   autocomplete="off"
                   aria-describedby="cedula-ayuda">
            <div class="invalid-feedback" id="cedula-invalid-feedback">Ingrese una cédula uruguaya válida.</div>
            <div class="form-text mt-1" id="cedula-ayuda">
                <i class="bi bi-info-circle"></i> Escriba los números de la cédula; el formato se agregará automáticamente e incluirá el dígito verificador.
            </div>
        </div>

        <div class="flacso-input-group" id="contenedor-otro-documento" style="display:none;">
            <label for="otro_documento" class="form-label fw-semibold mb-2">
                Número de Documento <span class="text-danger">*</span>
            </label>
            <input type="text" name="otro_documento" id="otro_documento" class="form-control form-control-flacso" placeholder="Ingrese su número de documento">
            <div class="invalid-feedback">Por favor ingrese su número de documento.</div>
        </div>
    <?php }

    public function render_campos_identidad() { ?>
        <div class="flacso-input-group">
            <label for="genero" class="form-label fw-semibold mb-2">Identidad de género</label>
            <select name="genero" id="genero" class="form-select form-select-flacso">
                <option value="">Seleccionar opción</option>
                <option value="Mujer">Mujer</option>
                <option value="Varon">Varón</option>
                <option value="Mujer trans">Mujer trans</option>
                <option value="Varon trans">Varón trans</option>
                <option value="No binarie / no conforme">No binarie / no conforme</option>
                <option value="Otra">Otra (especificar)</option>
                <option value="Prefiero no responder">Prefiero no responder</option>
            </select>
        </div>

        <div class="flacso-input-group" id="contenedor-genero-otra" style="display:none;">
            <label for="genero_otra" class="form-label fw-semibold mb-2">
                Especifique su identidad de género <span class="text-danger">*</span>
            </label>
            <input type="text" name="genero_otra" id="genero_otra" class="form-control form-control-flacso" placeholder="Especifique su identidad de género">
            <div class="invalid-feedback">Por favor especifique su identidad de género.</div>
        </div>

        <div class="flacso-input-group">
            <label for="etnia" class="form-label fw-semibold mb-2">
                ¿Con qué raza/etnia se identifica? <span class="text-danger">*</span>
            </label>
            <select name="etnia" id="etnia" class="form-select form-select-flacso" required>
                <option value="">Seleccionar opción</option>
                <option value="Afrodescendiente">Afrodescendiente</option>
                <option value="Indígena">Indígena</option>
                <option value="Asiática">Asiática</option>
                <option value="Blanca">Blanca</option>
                <option value="Mestiza">Mestiza</option>
                <option value="Otra">Otra</option>
                <option value="Prefiero no responder">Prefiero no responder</option>
            </select>
            <div class="invalid-feedback">Por favor seleccione una opción.</div>
        </div>
    <?php }

    public function render_seccion_contacto() { ?>
        <section class="flacso-seccion mb-4">
            <div class="flacso-seccion-header mb-3">
                <div class="flacso-seccion-icon"><i class="bi bi-geo-alt"></i></div>
                <div class="flacso-seccion-content">
                    <h2 class="flacso-seccion-title">Información de Contacto</h2>
                    <p class="flacso-seccion-descripcion">Datos para contactarlo</p>
                </div>
            </div>

            <div class="flacso-campos-vertical">
                <?php
                $this->render_campo_telefono();
                $this->render_campo_texto('domicilio', 'Domicilio (incluyendo país)', 'text', true, 'street-address');
                $this->render_campo_texto('ocupacion', 'Ocupación Actual', 'text', true, 'organization-title');
                $this->render_campo_texto('estudios', 'Estudios Cursados', 'text', true, 'organization', 'Ej: Licenciatura en Educación');
                $this->render_campo_pais('pais_nacimiento', 'País de Nacimiento', true);
                $this->render_campo_pais('pais_residencia', 'País de Residencia', true);
                ?>
            </div>
        </section>
    <?php }

    public function render_campo_telefono() { ?>
        <div class="flacso-input-group">
            <label for="celular" class="form-label fw-semibold mb-2">
                Celular <span class="text-danger">*</span>
            </label>
            <input type="tel"
                   name="celular"
                   id="celular"
                   class="form-control form-control-flacso"
                   required
                   autocomplete="tel"
                   inputmode="tel"
                   placeholder="Cargando..."> <!-- autoPlaceholder by intl-tel-input -->

            <div class="invalid-feedback" id="celular-invalid-feedback">Por favor ingrese un número de celular válido.</div>
            <div class="form-text mt-1">
                <i class="bi bi-info-circle"></i> Use el selector de país y escriba su número en formato nacional.
            </div>
        </div>
    <?php }

    public function render_campo_pais($id, $label, $required = false) {
        $required_badge = $required ? ' <span class="text-danger">*</span>' : ''; ?>
        <div class="flacso-input-group">
            <label for="<?php echo esc_attr($id); ?>" class="form-label fw-semibold mb-2">
                <?php echo esc_html($label) . $required_badge; ?>
            </label>
            <input type="text"
                   name="<?php echo esc_attr($id); ?>"
                   id="<?php echo esc_attr($id); ?>"
                   class="form-control form-control-flacso country-select-flacso"
                   <?php echo $required ? 'required' : ''; ?>
                   autocomplete="<?php echo $id === 'pais_nacimiento' ? 'bday-country' : 'country'; ?>"
                   placeholder="Seleccione o ingrese su país">
            <?php if ($required): ?>
                <div class="invalid-feedback">Por favor seleccione un país.</div>
            <?php endif; ?>
        </div>
    <?php }

    public function render_seccion_academica($info) { ?>
        <section class="flacso-seccion mb-4">
            <div class="flacso-seccion-header mb-3">
                <div class="flacso-seccion-icon"><i class="bi bi-book"></i></div>
                <div class="flacso-seccion-content">
                    <h2 class="flacso-seccion-title">Información Académica</h2>
                    <p class="flacso-seccion-descripcion">Datos sobre su formación y convenios</p>
                </div>
            </div>

            <div class="flacso-campos-vertical">
                <?php
                $this->render_campo_posgrado_flacso();
                $this->render_campo_convenio($info);
                ?>
            </div>
        </section>
    <?php }

    public function render_campo_posgrado_flacso() { ?>
        <div class="flacso-input-group">
            <label for="posgrado_flacso" class="form-label fw-semibold mb-2">
                ¿Cursa posgrado en FLACSO Uruguay? <span class="text-danger">*</span>
            </label>
            <select name="posgrado_flacso" id="posgrado_flacso" class="form-select form-select-flacso" required>
                <option value="">Seleccionar opción</option>
                <option value="No">No</option>
                <option value="Si">Sí</option>
            </select>
            <div class="invalid-feedback">Por favor seleccione una opción.</div>
        </div>

        <div class="flacso-input-group" id="contenedor-posgrado-detalle" style="display:none;">
            <label for="posgrado_flacso_detalle" class="form-label fw-semibold mb-2">
                ¿Cuál posgrado? <span class="text-danger">*</span>
            </label>
            <input type="text" name="posgrado_flacso_detalle" id="posgrado_flacso_detalle" class="form-control form-control-flacso" autocomplete="off" placeholder="Especifique cuál posgrado cursa actualmente">
            <div class="invalid-feedback">Por favor especifique cuál posgrado cursa.</div>
        </div>
    <?php }

    public function render_campo_convenio($info) { ?>
        <div class="flacso-input-group">
            <label for="convenio_flacso" class="form-label fw-semibold mb-2">
                ¿Puede adherir a través de algún convenio? <span class="text-danger">*</span>
            </label>
            <select name="convenio_flacso" id="convenio_flacso" class="form-select form-select-flacso" required>
                <option value="">Seleccionar opción</option>
                <option value="No">No</option>
                <option value="Si">Sí</option>
            </select>
            <div class="invalid-feedback">Por favor seleccione una opción.</div>
        </div>

        <div class="flacso-input-group" id="contenedor-convenio-detalle" style="display:none;">
            <label for="convenio_flacso_detalle" class="form-label fw-semibold mb-2">
                ¿Cuál convenio? <span class="text-danger">*</span>
            </label>
            <input type="text" name="convenio_flacso_detalle" id="convenio_flacso_detalle" class="form-control form-control-flacso" list="lista-convenios" autocomplete="off" placeholder="Escriba el nombre del convenio">
            <div class="invalid-feedback">Por favor especifique el convenio.</div>

            <?php if (!empty($info['convenios_validos'])): ?>
                <datalist id="lista-convenios">
                    <?php foreach ($info['convenios_validos'] as $convenio): ?>
                        <option value="<?php echo esc_attr($convenio); ?>">
                    <?php endforeach; ?>
                </datalist>
            <?php endif; ?>
        </div>
    <?php }

    public function render_seccion_documentacion($info) {
        $max_file_size  = 3;
        $max_total_size = 25; ?>
        <section class="flacso-seccion mb-4">
            <div class="flacso-seccion-header mb-3">
                <div class="flacso-seccion-icon"><i class="bi bi-folder"></i></div>
                <div class="flacso-seccion-content">
                    <h2 class="flacso-seccion-title">Documentación Requerida</h2>
                    <p class="flacso-seccion-descripcion">Suba todos los documentos solicitados en formato digital</p>
                </div>
            </div>

            <div class="alert alert-info mb-3">
                <div class="d-flex align-items-start">
                    <i class="bi bi-info-circle-fill me-2 mt-1"></i>
                    <div>
                        <strong>Importante:</strong> Todos los documentos deben estar escaneados en formato digital.
                        <strong>Límite total: <?php echo (int)$max_total_size; ?> MiB para todos los archivos combinados.</strong>
                    </div>
                </div>
            </div>

            <div class="flacso-documentacion-seccion mb-3">
                <h3 class="flacso-documentacion-subtitulo mb-2">
                    <i class="bi bi-person-badge me-2"></i> Documentación Personal
                </h3>
                <div class="flacso-campos-vertical">
                    <?php
                    $this->render_documento_item(
                        'documento_identidad[]',
                        'Documento de Identidad vigente',
                        'Cédula, pasaporte, documento de identidad extranjero, etc. Suba 1 o 2 archivos (frente y dorso). Formatos: PDF, JPG, PNG, WEBP.',
                        true,
                        true
                    );
                    ?>
                </div>
            </div>

            <div class="flacso-documentacion-seccion mb-3">
                <h3 class="flacso-documentacion-subtitulo mb-2">
                    <i class="bi bi-award me-2"></i> Documentación Académica
                </h3>
                <div class="flacso-doc-grid">
                    <div class="flacso-doc-card">
                        <?php
                        $this->render_documento_item(
                            'cv',
                            'Curriculum Vitae (CV)',
                            'Formatos: PDF, JPG, PNG, WEBP. Máximo 3 MB por archivo.',
                            true,
                            false
                        );
                        ?>
                    </div>
                    <div class="flacso-doc-card">
                        <?php
                        $this->render_documento_item(
                            'carta_motivacion',
                            'Carta de Motivación',
                            'Explique las razones de su interés en el posgrado. Formatos: PDF, JPG, PNG, WEBP. Máximo 3 MB.',
                            true,
                            false
                        );
                        ?>
                    </div>
                    <div class="flacso-doc-card">
                        <?php $this->render_documento_titulo_grado(); ?>
                    </div>
                    <div class="flacso-doc-card">
                        <div class="flacso-input-group">
                            <label for="titulo_grado_especificacion" class="form-label fw-semibold mb-2">
                                Denominación del Título de Grado y/o Terciarios <span class="text-danger">*</span>
                            </label>
                            <input type="text" name="titulo_grado_especificacion" id="titulo_grado_especificacion" class="form-control form-control-flacso" required autocomplete="off" placeholder="Ej: Licenciatura en Psicología, Analista en Sistemas, etc.">
                            <div class="invalid-feedback">Por favor ingrese la denominación de su título de grado y/o terciarios.</div>
                            <div class="form-text mt-1"><i class="bi bi-info-circle"></i> Especifique el nombre completo de su título universitario y/o terciario.</div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="alert alert-warning mt-3">
                <div class="d-flex align-items-start">
                    <i class="bi bi-exclamation-triangle-fill me-2 mt-1"></i>
                    <div>
                        <strong>Límite de archivos:</strong>
                        Cada archivo individual no puede superar los <?php echo (int)$max_file_size; ?> MB.
                        El total de todos los archivos combinados no puede exceder los <?php echo (int)$max_total_size; ?> MB.
                    </div>
                </div>
            </div>
        </section>
    <?php }

    public function render_seccion_cartas_recomendacion() { ?>
        <section class="flacso-seccion mb-4">
            <div class="flacso-seccion-header mb-3">
                <div class="flacso-seccion-icon"><i class="bi bi-envelope-heart"></i></div>
                <div class="flacso-seccion-content">
                    <h2 class="flacso-seccion-title">Cartas de Recomendación</h2>
                    <p class="flacso-seccion-descripcion">Documentación de respaldo académico y profesional</p>
                </div>
            </div>

            <div class="flacso-cartas-recomendacion-seccion p-3 bg-light rounded">
                <p class="flacso-cartas-descripcion mb-3">
                    <i class="bi bi-info-circle me-1"></i>
                    Se valorará positivamente que una carta sea académica y la otra profesional.
                </p>

                <div class="flacso-campos-vertical">
                    <?php
                    $this->render_documento_item('carta_recomendacion_1', 'Primera Carta de Recomendación', 'Formatos: PDF, JPG, PNG, WEBP. Máximo 3 MB.', true, false);
                    $this->render_documento_item('carta_recomendacion_2', 'Segunda Carta de Recomendación', 'Formatos: PDF, JPG, PNG, WEBP. Máximo 3 MB.', true, false);
                    ?>
                </div>
            </div>
        </section>
    <?php }

    public function render_documento_titulo_grado() { ?>
        <div class="flacso-input-group">
            <label for="titulo_grado" class="form-label fw-semibold mb-2">
                Documento del Título de Grado y/o Terciarios <span class="text-danger">*</span>
            </label>
            <input type="file" name="titulo_grado" id="titulo_grado" class="form-control form-control-flacso" accept=".pdf,image/jpeg,image/png,image/webp,application/pdf" required>
            <div class="invalid-feedback">Este documento es requerido.</div>
            <div class="form-text mt-1"><i class="bi bi-info-circle"></i> Documento que acredite estudios universitarios y/o terciarios de 4 años o más de duración. Formatos: PDF, JPG, PNG, WEBP.</div>
        </div>
    <?php }

    public function render_documento_item($name, $label, $descripcion, $required = false, $multiple = false) {
        $max_file_size = 3;
        $id = str_replace(array('[]', '[', ']'), array('', '_', ''), $name);
        $required_attr = $required ? 'required' : '';
        $multiple_attr = $multiple ? 'multiple' : '';
        $required_badge = $required ? ' <span class="text-danger">*</span>' : ''; ?>
        <div class="flacso-input-group">
            <label for="<?php echo esc_attr($id); ?>" class="form-label fw-semibold mb-2">
                <?php echo esc_html($label) . $required_badge; ?>
            </label>
            <input type="file"
                   name="<?php echo esc_attr($name); ?>"
                   id="<?php echo esc_attr($id); ?>"
                   class="form-control form-control-flacso"
                   accept=".pdf,image/jpeg,image/png,image/webp,application/pdf"
                   <?php echo $required_attr; ?>
                   <?php echo $multiple_attr; ?>
                   data-max-size="<?php echo (int)$max_file_size; ?>">
            <?php if ($required): ?><div class="invalid-feedback">Este documento es requerido.</div><?php endif; ?>
            <div class="form-text mt-1"><i class="bi bi-info-circle"></i> <?php echo esc_html($descripcion); ?></div>
        </div>
    <?php }

    public function render_seccion_adicional() { ?>
        <section class="flacso-seccion mb-4">
            <div class="flacso-seccion-header mb-3">
                <div class="flacso-seccion-icon"><i class="bi bi-chat-dots"></i></div>
                <div class="flacso-seccion-content">
                    <h2 class="flacso-seccion-title">Información Adicional</h2>
                    <p class="flacso-seccion-descripcion">Datos complementarios para su postulación</p>
                </div>
            </div>

            <div class="flacso-campos-vertical">
                <div class="flacso-input-group">
                    <label for="fuente" class="form-label fw-semibold mb-2">
                        ¿Cómo conoció el posgrado? <span class="text-danger">*</span>
                    </label>
                    <select name="fuente" id="fuente" class="form-select form-select-flacso" required>
                        <option value="">Seleccionar opción</option>
                        <option value="Facebook">Facebook</option>
                        <option value="Twitter">Twitter</option>
                        <option value="Instagram">Instagram</option>
                        <option value="Linkedin">LinkedIn</option>
                        <option value="Web">Web</option>
                        <option value="Recomendacion">Recomendación</option>
                        <option value="Mailing">Mailing</option>
                        <option value="Otro">Otro</option>
                    </select>
                    <div class="invalid-feedback">Por favor seleccione cómo conoció el posgrado.</div>
                </div>

                <?php $this->render_radio_buttons('acepta_difusion', '¿Acepta difusión de nombre/foto?', array('Si' => 'Sí', 'No' => 'No')); ?>

                <?php $this->render_radio_buttons('documentacion_completa', 'Declaro que he subido toda la documentación requerida', array(
                    'Si' => 'Sí, he subido toda la documentación',
                    'No' => 'No, me falta subir algún documento'
                )); ?>

                <div class="flacso-input-group" id="contenedor-documentacion-faltante" style="display:none;">
                    <label for="documentacion_faltante" class="form-label fw-semibold mb-2">
                        Especifique qué documentación falta <span class="text-danger">*</span>
                    </label>
                    <textarea name="documentacion_faltante" id="documentacion_faltante" class="form-control form-control-flacso" rows="3" placeholder="Describa qué documentos faltan por subir y por qué..."></textarea>
                    <div class="invalid-feedback">Por favor especifique qué documentación falta.</div>
                </div>
            </div>
        </section>
    <?php }

    public function render_radio_buttons($name, $label, $opciones) { ?>
        <div class="flacso-radio-group">
            <label class="form-label fw-semibold mb-2">
                <?php echo esc_html($label); ?> <span class="text-danger">*</span>
            </label>
            <div class="flacso-radio-options">
                <?php foreach ($opciones as $value => $text): ?>
                    <div class="form-check-radio">
                        <input type="radio" name="<?php echo esc_attr($name); ?>" id="<?php echo esc_attr($name . '_' . sanitize_title($value)); ?>" value="<?php echo esc_attr($value); ?>" class="form-check-input-radio" required>
                        <label for="<?php echo esc_attr($name . '_' . sanitize_title($value)); ?>" class="form-check-label-radio"><?php echo esc_html($text); ?></label>
                    </div>
                <?php endforeach; ?>
            </div>
            <div class="invalid-feedback-radio">Por favor seleccione una opción.</div>
        </div>
    <?php }

    public function render_boton_envio() { ?>
        <div class="flacso-boton-envio text-center py-4 border-top">
            <button type="submit" class="btn btn-success btn-lg px-5 py-3 fw-bold w-100">
                <i class="bi bi-send-check me-2"></i> Enviar Postulación
            </button>
            <div class="flacso-texto-seguridad mt-3 text-muted small">
                <i class="bi bi-shield-check me-1"></i> Su información está protegida y será usada exclusivamente para fines académicos
            </div>
        </div>
    <?php }

}
