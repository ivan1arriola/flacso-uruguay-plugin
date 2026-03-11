<?php
if (!defined('ABSPATH')) { exit; }

trait FLACSO_Formulario_Preinscripcion_Render {
    public function render_hero_header($info) {
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
                        <p class="flacso-hero-badge">Convocatoria 2026 - Inscripciones abiertas</p>
                        <h1 class="flacso-hero-title"><?php echo esc_html($info['titulo_posgrado']); ?></h1>
                        <p class="flacso-hero-description">
                            Presenta tu solicitud y adjunta la documentacion requerida para iniciar el proceso de admision. El formulario te guiara paso a paso.
                        </p>
                        <ul class="flacso-hero-checklist">
                            <li><i class="bi bi-check-circle"></i> Completa los datos personales y de contacto.</li>
                            <li><i class="bi bi-check-circle"></i> Adjunta carta de motivacion y documentos de identidad.</li>
                            <li><i class="bi bi-check-circle"></i> Recibiras confirmacion por correo al finalizar.</li>
                        </ul>
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
                    </div>
                    <div class="flacso-hero-card" aria-label="Resumen de pasos previos">
                        <div class="flacso-hero-card-header">
                            <span class="flacso-hero-card-label">Checklist rapido</span>
                            <h3>Antes de comenzar</h3>
                        </div>
                        <ul class="flacso-hero-card-list">
                            <li><strong>Documentacion:</strong> Carta de motivacion y documento vigente.</li>
                            <li><strong>Duracion estimada:</strong> 12 a 15 minutos para completar todo.</li>
                            <li><strong>Soporte:</strong> inscripciones@flacso.edu.uy</li>
                        </ul>
                        <div class="flacso-hero-metrics">
                            <div class="flacso-hero-metric">
                                <span>Paso 1</span>
                                <strong>Datos personales</strong>
                            </div>
                            <div class="flacso-hero-metric">
                                <span>Paso 2</span>
                                <strong>Documentacion</strong>
                            </div>
                            <div class="flacso-hero-metric">
                                <span>Paso 3</span>
                                <strong>Envio y confirmacion</strong>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </header>
        <?php
    }

    public function render_campos_ocultos($info) {
        $id_posgrado = $info['parent_page_id'] ?: $info['page_id']; ?>
        <input type="hidden" name="id_pagina" value="<?php echo esc_attr($id_posgrado); ?>">
        <input type="hidden" name="titulo_posgrado" value="<?php echo esc_attr($info['titulo_posgrado']); ?>">
        <input type="hidden" name="posgradoAlQuePostula" value="<?php echo esc_attr($info['titulo_posgrado']); ?>">
        <input type="hidden" name="es_maestria" value="<?php echo $info['es_maestria'] ? 'si' : 'no'; ?>">
        <input type="hidden" name="nonce" value="<?php echo wp_create_nonce('flacso_form_nonce'); ?>">
        <input type="hidden" name="action" value="flacso_enviar_preinscripcion">
        <!-- Celular en E.164 (se completa en JS si es valido) -->
        <input type="hidden" name="celular_e164" id="celular_e164" value="">
        <?php
    }

    public function render_seccion_correo() { ?>
        <section class="flacso-seccion mb-4">
            <div class="flacso-seccion-header mb-3">
                <div class="flacso-seccion-icon"><i class="bi bi-envelope"></i></div>
                <div class="flacso-seccion-content">
                    <h2 class="flacso-seccion-title">Correo Electronico</h2>
                    <p class="flacso-seccion-descripcion">Utilice un correo valido y activo para recibir confirmaciones</p>
                </div>
            </div>

            <div class="flacso-input-group">
                <label for="correo" class="form-label fw-semibold mb-2">
                    Correo Electronico <span class="text-danger">*</span>
                </label>
                <input type="email"
                       name="correo"
                       id="correo"
                       class="form-control form-control-flacso"
                       required
                       autocomplete="email"
                       inputmode="email"
                       placeholder="ejemplo@correo.com">
                <div class="invalid-feedback">Por favor ingrese un correo electronico valido.</div>
            </div>
        </section>
    <?php }

    public function render_seccion_info_personal() { ?>
        <section class="flacso-seccion mb-4">
            <div class="flacso-seccion-header mb-3">
                <div class="flacso-seccion-icon"><i class="bi bi-person-vcard"></i></div>
                <div class="flacso-seccion-content">
                    <h2 class="flacso-seccion-title">Informacion Personal</h2>
                    <p class="flacso-seccion-descripcion">Datos personales y de identificacion</p>
                </div>
            </div>

            <div class="flacso-campos-vertical">
                <?php
                $this->render_campo_texto('nombre1',   'Primer Nombre', 'text', true,  'given-name');
                $this->render_campo_texto('nombre2',   'Segundo Nombre', 'text', false, 'additional-name');
                $this->render_campo_texto('apellido1', 'Primer Apellido', 'text', true,  'family-name');
                $this->render_campo_texto('apellido2', 'Segundo Apellido', 'text', false, 'family-name');
                $this->render_campo_texto('fecha_nacimiento', 'Fecha de Nacimiento', 'date', true, 'bday');
                $this->render_campos_documento();
                $this->render_campos_identidad();
                ?>
            </div>
        </section>
    <?php }

    public function render_campo_texto($id, $label, $type = 'text', $required = false, $autocomplete = null) {
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
                   placeholder="<?php echo esc_attr($label); ?>">
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
                <option value="cedula_uruguaya">Cedula de Identidad Uruguaya</option>
                <option value="pasaporte">Pasaporte</option>
                <option value="documento_extranjero">Documento Extranjero</option>
                <option value="otro">Otro documento</option>
            </select>
            <div class="invalid-feedback">Por favor seleccione un tipo de documento.</div>
        </div>

        <div class="flacso-input-group" id="contenedor-cedula" style="display:none;">
            <label for="cedula_uruguaya" class="form-label fw-semibold mb-2">
                Cedula de Identidad Uruguaya <span class="text-danger">*</span>
            </label>
            <input type="text"
                   name="cedula_uruguaya"
                   id="cedula_uruguaya"
                   class="form-control form-control-flacso"
                   placeholder="Ej: 41234567"
                   inputmode="numeric"
                   pattern="\d{7,8}"
                   minlength="7"
                   maxlength="8"
                   autocomplete="off"
                   aria-describedby="cedula-ayuda">
            <div class="invalid-feedback" id="cedula-invalid-feedback">Ingrese 7 u 8 digitos sin puntos ni guiones.</div>
            <div class="form-text mt-1" id="cedula-ayuda">
                <i class="bi bi-info-circle"></i> Ingrese solo numeros, sin puntos ni guiones, e incluya el digito verificador (7 u 8 digitos).
            </div>
        </div>

        <div class="flacso-input-group" id="contenedor-otro-documento" style="display:none;">
            <label for="otro_documento" class="form-label fw-semibold mb-2">
                Numero de Documento <span class="text-danger">*</span>
            </label>
            <input type="text" name="otro_documento" id="otro_documento" class="form-control form-control-flacso" placeholder="Ingrese su numero de documento">
            <div class="invalid-feedback">Por favor ingrese su numero de documento.</div>
        </div>
    <?php }

    public function render_campos_identidad() { ?>
        <div class="flacso-input-group">
            <label for="genero" class="form-label fw-semibold mb-2">Identidad de genero</label>
            <select name="genero" id="genero" class="form-select form-select-flacso">
                <option value="">Seleccionar opcion</option>
                <option value="Mujer">Mujer</option>
                <option value="Varon">Varon</option>
                <option value="Mujer trans">Mujer trans</option>
                <option value="Varon trans">Varon trans</option>
                <option value="No binarie / no conforme">No binarie / no conforme</option>
                <option value="Otra">Otra (especificar)</option>
                <option value="Prefiero no responder">Prefiero no responder</option>
            </select>
        </div>

        <div class="flacso-input-group" id="contenedor-genero-otra" style="display:none;">
            <label for="genero_otra" class="form-label fw-semibold mb-2">
                Especifique su identidad de genero <span class="text-danger">*</span>
            </label>
            <input type="text" name="genero_otra" id="genero_otra" class="form-control form-control-flacso" placeholder="Especifique su identidad de genero">
            <div class="invalid-feedback">Por favor especifique su identidad de genero.</div>
        </div>

        <div class="flacso-input-group">
            <label for="etnia" class="form-label fw-semibold mb-2">
                Con que raza/etnia se identifica? <span class="text-danger">*</span>
            </label>
            <input type="text" name="etnia" id="etnia" class="form-control form-control-flacso" required autocomplete="off" placeholder="Ingrese su raza/etnia">
            <div class="invalid-feedback">Este campo es obligatorio.</div>
        </div>
    <?php }

    public function render_seccion_contacto() { ?>
        <section class="flacso-seccion mb-4">
            <div class="flacso-seccion-header mb-3">
                <div class="flacso-seccion-icon"><i class="bi bi-geo-alt"></i></div>
                <div class="flacso-seccion-content">
                    <h2 class="flacso-seccion-title">Informacion de Contacto</h2>
                    <p class="flacso-seccion-descripcion">Datos para contactarlo</p>
                </div>
            </div>

            <div class="flacso-campos-vertical">
                <?php
                $this->render_campo_telefono();
                $this->render_campo_texto('domicilio', 'Domicilio (incluyendo pais)', 'text', true, 'street-address');
                $this->render_campo_texto('ocupacion', 'Ocupacion Actual', 'text', true, 'organization-title');
                $this->render_campo_texto('estudios', 'Estudios Cursados', 'text', true, 'organization');
                $this->render_campo_pais('pais_nacimiento', 'Pais de Nacimiento', true);
                $this->render_campo_pais('pais_residencia', 'Pais de Residencia', true);
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
                   placeholder="">
            <div class="invalid-feedback" id="celular-invalid-feedback">Por favor ingrese un numero de celular valido.</div>
            <div class="form-text mt-1">
                <i class="bi bi-info-circle"></i> Use el selector de pais y escriba su numero en formato nacional.
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
                   placeholder="Seleccione o ingrese su pais">
            <?php if ($required): ?>
                <div class="invalid-feedback">Por favor seleccione un pais.</div>
            <?php endif; ?>
        </div>
    <?php }

    public function render_seccion_academica($info) { ?>
        <section class="flacso-seccion mb-4">
            <div class="flacso-seccion-header mb-3">
                <div class="flacso-seccion-icon"><i class="bi bi-book"></i></div>
                <div class="flacso-seccion-content">
                    <h2 class="flacso-seccion-title">Informacion Academica</h2>
                    <p class="flacso-seccion-descripcion">Datos sobre su formacion y convenios</p>
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
                Cursa posgrado en FLACSO Uruguay? <span class="text-danger">*</span>
            </label>
            <select name="posgrado_flacso" id="posgrado_flacso" class="form-select form-select-flacso" required>
                <option value="">Seleccionar opcion</option>
                <option value="No">No</option>
                <option value="Si">Si</option>
            </select>
            <div class="invalid-feedback">Por favor seleccione una opcion.</div>
        </div>

        <div class="flacso-input-group" id="contenedor-posgrado-detalle" style="display:none;">
            <label for="posgrado_flacso_detalle" class="form-label fw-semibold mb-2">
                Cual posgrado? <span class="text-danger">*</span>
            </label>
            <input type="text" name="posgrado_flacso_detalle" id="posgrado_flacso_detalle" class="form-control form-control-flacso" autocomplete="off" placeholder="Especifique cual posgrado cursa actualmente">
            <div class="invalid-feedback">Por favor especifique cual posgrado cursa.</div>
        </div>
    <?php }

    public function render_campo_convenio($info) { ?>
        <div class="flacso-input-group">
            <label for="convenio_flacso" class="form-label fw-semibold mb-2">
                Puede adherir a traves de algun convenio? <span class="text-danger">*</span>
            </label>
            <select name="convenio_flacso" id="convenio_flacso" class="form-select form-select-flacso" required>
                <option value="">Seleccionar opcion</option>
                <option value="No">No</option>
                <option value="Si">Si</option>
            </select>
            <div class="invalid-feedback">Por favor seleccione una opcion.</div>
        </div>

        <div class="flacso-input-group" id="contenedor-convenio-detalle" style="display:none;">
            <label for="convenio_flacso_detalle" class="form-label fw-semibold mb-2">
                Cual convenio? <span class="text-danger">*</span>
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
        $max_file_size  = 5;
        $max_total_size = 25; ?>
        <section class="flacso-seccion mb-4">
            <div class="flacso-seccion-header mb-3">
                <div class="flacso-seccion-icon"><i class="bi bi-folder"></i></div>
                <div class="flacso-seccion-content">
                    <h2 class="flacso-seccion-title">Documentacion Requerida</h2>
                    <p class="flacso-seccion-descripcion">Suba todos los documentos solicitados en formato digital</p>
                </div>
            </div>

            <div class="alert alert-info mb-3">
                <div class="d-flex align-items-start">
                    <i class="bi bi-info-circle-fill me-2 mt-1"></i>
                    <div>
                        <strong>Importante:</strong> Todos los documentos deben estar escaneados en formato digital.
                        <strong>Limite total: <?php echo (int)$max_total_size; ?> MiB para todos los archivos combinados.</strong>
                    </div>
                </div>
            </div>

            <div class="flacso-documentacion-seccion mb-3">
                <h3 class="flacso-documentacion-subtitulo mb-2">
                    <i class="bi bi-person-badge me-2"></i> Documentacion Personal
                </h3>
                <div class="flacso-campos-vertical">
                    <?php
                    $this->render_documento_item(
                        'documento_identidad[]',
                        'Documento de Identidad vigente',
                        'Cedula, pasaporte, documento de identidad extranjero, etc. Suba 1 o 2 archivos (frente y dorso). Formatos: PDF, JPG, PNG, WEBP.',
                        true,
                        true
                    );
                    ?>
                </div>
            </div>

            <div class="flacso-documentacion-seccion mb-3">
                <h3 class="flacso-documentacion-subtitulo mb-2">
                    <i class="bi bi-award me-2"></i> Documentacion Academica
                </h3>
                <div class="flacso-doc-grid">
                    <div class="flacso-doc-card">
                        <?php
                        $this->render_documento_item(
                            'cv',
                            'Curriculum Vitae (CV)',
                            'Formatos: PDF, JPG, PNG, WEBP. Maximo 5MB por archivo.',
                            true,
                            false
                        );
                        ?>
                    </div>
                    <div class="flacso-doc-card">
                        <?php
                        $this->render_documento_item(
                            'carta_motivacion',
                            'Carta de Motivacion',
                            'Explique las razones de su interes en el posgrado. Formatos: PDF, JPG, PNG, WEBP. Maximo 5MB.',
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
                                Denominacion del Titulo de Grado y/o Terciarios <span class="text-danger">*</span>
                            </label>
                            <input type="text" name="titulo_grado_especificacion" id="titulo_grado_especificacion" class="form-control form-control-flacso" required autocomplete="off" placeholder="Ej: Licenciatura en Psicologia, Analista en Sistemas, etc.">
                            <div class="invalid-feedback">Por favor ingrese la denominacion de su titulo de grado y/o terciarios.</div>
                            <div class="form-text mt-1"><i class="bi bi-info-circle"></i> Especifique el nombre completo de su titulo universitario y/o terciario.</div>
                        </div>
                    </div>
                </div>
            </div>
        </section>

        <div class="alert alert-warning">
            <div class="d-flex align-items-start">
                <i class="bi bi-exclamation-triangle-fill me-2 mt-1"></i>
                <div>
                    <strong>Limite de archivos:</strong>
                    Cada archivo individual no puede superar los <?php echo (int)$max_file_size; ?> MB.
                    El total de todos los archivos combinados no puede exceder los <?php echo (int)$max_total_size; ?> MB.
                </div>
            </div>
        </div>
    <?php }

    public function render_seccion_cartas_recomendacion() { ?>
        <section class="flacso-seccion mb-4">
            <div class="flacso-seccion-header mb-3">
                <div class="flacso-seccion-icon"><i class="bi bi-envelope-heart"></i></div>
                <div class="flacso-seccion-content">
                    <h2 class="flacso-seccion-title">Cartas de Recomendacion</h2>
                    <p class="flacso-seccion-descripcion">Documentacion de respaldo academico y profesional</p>
                </div>
            </div>

            <div class="flacso-cartas-recomendacion-seccion p-3 bg-light rounded">
                <p class="flacso-cartas-descripcion mb-3">
                    <i class="bi bi-info-circle me-1"></i>
                    Se valorara positivamente que una carta sea academica y la otra profesional.
                </p>

                <div class="flacso-campos-vertical">
                    <?php
                    $this->render_documento_item('carta_recomendacion_1', 'Primera Carta de Recomendacion', 'Formatos: PDF, JPG, PNG, WEBP. Maximo 5MB.', true, false);
                    $this->render_documento_item('carta_recomendacion_2', 'Segunda Carta de Recomendacion', 'Formatos: PDF, JPG, PNG, WEBP. Maximo 5MB.', true, false);
                    ?>
                </div>
            </div>
        </section>
    <?php }

    public function render_documento_titulo_grado() { ?>
        <div class="flacso-input-group">
            <label for="titulo_grado" class="form-label fw-semibold mb-2">
                Documento del Titulo de Grado y/o Terciarios <span class="text-danger">*</span>
            </label>
            <input type="file" name="titulo_grado" id="titulo_grado" class="form-control form-control-flacso" accept=".pdf,image/jpeg,image/png,image/webp,application/pdf" required>
            <div class="invalid-feedback">Este documento es requerido.</div>
            <div class="form-text mt-1"><i class="bi bi-info-circle"></i> Documento que acredite estudios universitarios y/o terciarios de 4 anos o mas de duracion. Formatos: PDF, JPG, PNG, WEBP.</div>
        </div>
    <?php }

    public function render_documento_item($name, $label, $descripcion, $required = false, $multiple = false) {
        $max_file_size = 5;
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
                    <h2 class="flacso-seccion-title">Informacion Adicional</h2>
                    <p class="flacso-seccion-descripcion">Datos complementarios para su postulacion</p>
                </div>
            </div>

            <div class="flacso-campos-vertical">
                <div class="flacso-input-group">
                    <label for="fuente" class="form-label fw-semibold mb-2">
                        Como conocio el posgrado? <span class="text-danger">*</span>
                    </label>
                    <select name="fuente" id="fuente" class="form-select form-select-flacso" required>
                        <option value="">Seleccionar opcion</option>
                        <option value="Facebook">Facebook</option>
                        <option value="Twitter">Twitter</option>
                        <option value="Instagram">Instagram</option>
                        <option value="Linkedin">Linkedin</option>
                        <option value="Web">Web</option>
                        <option value="Recomendacion">Recomendacion</option>
                        <option value="Mailing">Mailing</option>
                        <option value="Otro">Otro</option>
                    </select>
                    <div class="invalid-feedback">Por favor seleccione como conocio el posgrado.</div>
                </div>

                <?php $this->render_radio_buttons('acepta_difusion', 'Acepta difusion de nombre/foto?', array('Si' => 'Si', 'No' => 'No')); ?>

                <?php $this->render_radio_buttons('documentacion_completa', 'Declaro que he subido toda la documentacion requerida', array(
                    'Si' => 'Si, he subido toda la documentacion',
                    'No' => 'No, me falta subir algun documento'
                )); ?>

                <div class="flacso-input-group" id="contenedor-documentacion-faltante" style="display:none;">
                    <label for="documentacion_faltante" class="form-label fw-semibold mb-2">
                        Especifique que documentacion falta <span class="text-danger">*</span>
                    </label>
                    <textarea name="documentacion_faltante" id="documentacion_faltante" class="form-control form-control-flacso" rows="3" placeholder="Describa que documentos faltan por subir y por que..."></textarea>
                    <div class="invalid-feedback">Por favor especifique que documentacion falta.</div>
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
            <div class="invalid-feedback-radio">Por favor seleccione una opcion.</div>
        </div>
    <?php }

    public function render_boton_envio() { ?>
        <div class="flacso-boton-envio text-center py-4 border-top">
            <button type="submit" class="btn btn-success btn-lg px-5 py-3 fw-bold w-100">
                <i class="bi bi-send-check me-2"></i> Enviar Postulacion
            </button>
            <div class="flacso-texto-seguridad mt-3 text-muted small">
                <i class="bi bi-shield-check me-1"></i> Su informacion esta protegida y sera usada exclusivamente para fines academicos
            </div>
        </div>
    <?php }

}
