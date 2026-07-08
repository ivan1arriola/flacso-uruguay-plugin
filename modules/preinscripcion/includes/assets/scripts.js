/**
 * FLACSO Formulario Preinscripción - Scripts
 * Inicialización y validación del formulario de preinscripción
 */

jQuery(function($){
    'use strict';

    const trackMetaEvent = (eventName, customData, options) => {
        if (typeof window.flacsoMetaTrack !== 'function') {
            return;
        }
        try {
            window.flacsoMetaTrack(eventName, customData || {}, options);
        } catch (e) {
            console.warn('[Preinscripcion] Error enviando evento Meta Pixel:', e);
        }
    };

    function normalizeMetaCurrency(value) {
        const currency = String(value || '').trim().toUpperCase();
        return /^[A-Z]{3}$/.test(currency) ? currency : '';
    }

    // Configuración global (será inyectada por WordPress)
    const config = window.flacsoFormConfig || {
        convenios: [],
        maxFileSize: 3,
        maxTotalSize: 25,
        ajaxUrl: '/wp-admin/admin-ajax.php',
        tituloPosgrado: '',
        preinscripcionesCerradas: false,
        mensajeCierre: 'Por el momento no estamos recibiendo más preinscripciones.'
    };

    // Error map según documentación oficial de intl-tel-input
    const errorMap = [
        "Número inválido",
        "Código de país inválido",
        "Número demasiado corto",
        "Número demasiado largo",
        "Número inválido"
    ];

    function validarCedulaUruguaya(ci){
        const digits = String(ci || '').replace(/\D/g,'');
        if(digits.length < 7 || digits.length > 8){ return false; }
        const padded = (digits.padStart ? digits.padStart(8,'0') : ('00000000'+digits).slice(-8));
        const cuerpo = padded.slice(0,7);
        const digitoVerificador = parseInt(padded.slice(-1), 10);
        const factores = [2,9,8,7,6,3,4];
        let suma = 0;
        for(let i=0;i<factores.length;i++){
            suma += parseInt(cuerpo[i], 10) * factores[i];
        }
        const resto = suma % 10;
        const esperado = resto === 0 ? 0 : 10 - resto;
        return digitoVerificador === esperado;
    }

    function formatearCedulaUruguaya(value){
        const digits = String(value || '').replace(/\D/g,'').slice(0,8);
        if(digits.length <= 1){ return digits; }

        const cuerpo = digits.slice(0, -1);
        const verificador = digits.slice(-1);
        const grupos = [];
        let restante = cuerpo;

        while(restante.length > 3){
            grupos.unshift(restante.slice(-3));
            restante = restante.slice(0, -3);
        }
        if(restante){ grupos.unshift(restante); }

        return grupos.join('.') + '-' + verificador;
    }

    function obtenerDigitosCedula(value){
        return String(value || '').replace(/\D/g,'').slice(0,8);
    }

    const form       = $('#flacso-formulario-preinscripcion');
    const resultado  = $('#flacso-resultado-envio');
    const btnSubmit  = $('.btn.btn-success');
    const preinscripcionesCerradas = !!config.preinscripcionesCerradas;
    const mensajeCierre = (config.mensajeCierre || 'Por el momento no estamos recibiendo más preinscripciones.').trim();
    const raf        = window.requestAnimationFrame || function(cb){ return setTimeout(cb, 16); };
    const caf        = window.cancelAnimationFrame || clearTimeout;
    let itiInstance  = null;
    let telefonoHaSidoInteractuado = false;
    let telefonoPaddingFrame = null;
    let submissionInProgress = false;

    const obtenerPaddingBaseTelefono = () => {
        const input = document.getElementById('celular');
        if(!input) return 0;
        if(!input.dataset.basePadding){
            const computed = window.getComputedStyle(input);
            const base = parseFloat(computed.paddingLeft) || 0;
            input.dataset.basePadding = String(base);
        }
        return parseFloat(input.dataset.basePadding) || 0;
    };

    const ajustarPaddingTelefono = () => {
        const input = document.getElementById('celular');
        if(!input) return;
        const container = input.closest('.iti');
        if(!container) return;
        const selected = container.querySelector('.iti__selected-country');
        const width = selected ? selected.getBoundingClientRect().width || 0 : 0;
        const basePadding = obtenerPaddingBaseTelefono();
        const separacionExtra = 8;
        input.style.setProperty('padding-left', Math.ceil(width + basePadding + separacionExtra) + 'px');
    };

    const programarAjustePaddingTelefono = () => {
        if(telefonoPaddingFrame){
            caf(telefonoPaddingFrame);
        }
        telefonoPaddingFrame = raf(() => ajustarPaddingTelefono());
    };

    const mensajeCedulaBase = 'Ingrese solo números sin puntos ni guiones e incluya el dígito verificador (7 u 8 dígitos).';
    const actualizarFeedbackCedula = (texto) => {
        const fb = $('#cedula-invalid-feedback');
        if(fb.length){ fb.text(texto); }
    };

    const mostrarAvisoCierre = () => {
        if(!resultado.length){
            return;
        }
        resultado.html(`
            <div class="alert alert-warning">
                <div class="d-flex align-items-start">
                    <i class="bi bi-info-circle-fill me-2 mt-1"></i>
                    <div>
                        <h5 class="alert-heading mb-2">Preinscripciones cerradas</h5>
                        <p class="mb-0">${mensajeCierre}</p>
                    </div>
                </div>
            </div>
        `);
    };

    const appendMetaTestEventCode = (targetUrl) => {
        const redirectUrl = new URL(targetUrl, window.location.href);
        const currentParams = new URLSearchParams(window.location.search);
        const testEventCode = (currentParams.get('test_event_code') || '').trim();

        if (testEventCode) {
            redirectUrl.searchParams.set('test_event_code', testEventCode);
        }

        return redirectUrl.toString();
    };

    const limpiarDialCodeDelInput = () => {
        if(!itiInstance) return;
        const input = document.getElementById('celular');
        if(!input) return;
        let val = input.value || '';
        const data = itiInstance.getSelectedCountryData ? itiInstance.getSelectedCountryData() : null;
        const dial = (data && data.dialCode) ? data.dialCode : '';
        if(dial){
            const dialEscaped = dial.replace(/[-/\\^$*+?.()|[\]{}]/g, '\\$&');
            const re = new RegExp('^\\+?\\s*' + dialEscaped + '\\s*');
            val = val.replace(re, '');
        }
        val = val.replace(/[^0-9\\s\\-\\(\\).]/g, '');
        input.value = val.trimStart();
    };

    
    // Inicializar teléfono con detección por IP + placeholders internacionales
    (function initTelefono(){
        const input = document.getElementById('celular');
        if(!input) return;
        itiInstance = window.intlTelInput(input, {
            initialCountry: "auto",
            geoIpLookup: (success, failure) => {
                fetch("https://ipapi.co/json")
                  .then(r => r.json())
                  .then(d => success(d && d.country ? d.country : ""))
                  .catch(() => failure());
            },
            separateDialCode: true,
            nationalMode: true,
            allowDropdown: true,
            countrySearch: true,
            strictMode: true,
            autoPlaceholder: "polite",
            placeholderNumberType: "MOBILE",
            loadUtils: () => import('https://cdn.jsdelivr.net/npm/intl-tel-input@25.12.4/build/js/utils.js')
        });

        // Ajustar padding inicial y en cambios de layout (fuentes/cambios de ancho)
        programarAjustePaddingTelefono();
        setTimeout(programarAjustePaddingTelefono, 200);
        if(document.fonts && document.fonts.ready){
            document.fonts.ready.then(() => { programarAjustePaddingTelefono(); }).catch(()=>{});
        } else {
            setTimeout(programarAjustePaddingTelefono, 350);
        }
        window.addEventListener('resize', programarAjustePaddingTelefono);

        // Event listeners sincronizados con cpt-seminario
        input.addEventListener('input', function() {
            clearTimeout(window.phoneValidationTimeout);
            window.phoneValidationTimeout = setTimeout(() => {
                validarTelefono();
            }, 500); // Debounce: valida 500ms después de escribir
        });

        input.addEventListener('countrychange', ()=> {
            limpiarDialCodeDelInput();
            resetValidacionTelefono();
                        programarAjustePaddingTelefono();
        });

        // Validar al perder el foco
        input.addEventListener('blur', function() {
            validarTelefono();
        });
    })();

    function validarTelefono(){
        const tel = $('#celular');
        const validFeedback = tel.siblings('.valid-feedback');
        const invalidFeedback = tel.siblings('.invalid-feedback');
        const hidden = $('#celular_e164');
        const value = tel.val().trim();
        
        // Reset
        if(value === ''){
            tel.removeClass('is-valid is-invalid');
            hidden.val('');
            return { isValid:false, message:'El número de teléfono es requerido', showInResult:false };
        }
        
        if(!itiInstance){
            tel.removeClass('is-valid').addClass('is-invalid');
            if(invalidFeedback.length){ invalidFeedback.text('Error en la configuración del teléfono'); }
            return { isValid:false, message:'Error en la configuración del teléfono', showInResult:true };
        }

        try {
            // Aceptar números locales (8+ dígitos) o internacionales válidos
            const isLocalNumber = /^\d{8,}$/.test(value.replace(/\D/g, ''));
            const isInternational = itiInstance.isValidNumber();
            const startsWithPlus = value.trim().startsWith('+');
            
            if (isLocalNumber || isInternational) {
                // Número válido (local o internacional)
                tel.removeClass('is-invalid');
                tel.addClass('is-valid');
                if (validFeedback.length) validFeedback.removeClass('d-none');
                if (invalidFeedback.length) invalidFeedback.addClass('d-none');
                
                // El campo celular_e164 SIEMPRE debe tener el número en formato internacional
                let e164Number = value;
                if (isInternational || (isLocalNumber && !startsWithPlus)) {
                    try {
                        e164Number = itiInstance.getNumber();
                    } catch (e) {
                        // Fallback: si hay error, intentar convertir manualmente
                        const digitsOnly = value.replace(/\D/g, '');
                        e164Number = '+598' + digitsOnly.slice(-8); // Asume Uruguay
                    }
                }
                hidden.val(e164Number || '');
                
                console.info('[Preinscripcion] Teléfono válido', { mode: startsWithPlus ? 'international' : 'local' });
                return { isValid:true, message:'Número válido', showInResult:false };
            } else {
                // Número inválido
                tel.removeClass('is-valid');
                tel.addClass('is-invalid');
                if (validFeedback.length) validFeedback.addClass('d-none');
                if (invalidFeedback.length) invalidFeedback.removeClass('d-none');
                hidden.val('');
                
                console.warn('[Preinscripcion] Teléfono inválido');
                return { isValid:false, message:'El número ingresado no es válido', showInResult:true };
            }
        } catch (error) {
            tel.removeClass('is-valid');
            tel.addClass('is-invalid');
            if (validFeedback.length) validFeedback.addClass('d-none');
            if (invalidFeedback.length) invalidFeedback.removeClass('d-none');
            hidden.val('');
            console.error('Error validando teléfono:', error);
            return { isValid:false, message:'Error al validar teléfono', showInResult:true };
        }
    }
    
    function resetValidacionTelefono(){
        $('#celular').removeClass('is-valid is-invalid');
        const fb = $('#celular-invalid-feedback');
        if(fb.length){ fb.text('Por favor ingrese un número de celular válido.'); }
        $('#celular_e164').val('');
    }

    // Country select inputs (no forzamos Uruguay; solo preferidos)
    $('.country-select-flacso').each(function(){
        const $input = $(this);
        const valorActual = ($input.val() || '').trim();
        const defaultIso = 'uy';
        $input.countrySelect({
            preferredCountries:['uy','ar','br','cl','py','bo'],
            responsiveDropdown:true,
            defaultCountry: defaultIso
        });
        // Mostrar bandera y nombre completo por defecto si no hay valor previo
        if(!valorActual){
            $input.countrySelect('setCountry', 'Uruguay');
        } else {
            // Si es código de 2 letras, usar selectCountry; si no, usar setCountry con el nombre
            if(valorActual.length === 2) {
                try { $input.countrySelect('selectCountry', valorActual.toLowerCase()); } catch(e){}
            } else {
                try { $input.countrySelect('setCountry', valorActual); } catch(e){}
            }
        }
    });

    // Campos condicionales
    $('#posgrado_flacso').on('change', function(){
        const d = $('#contenedor-posgrado-detalle'), i = $('#posgrado_flacso_detalle');
        if(this.value==='Si'){ d.slideDown(300); i.prop('required',true); } else { d.slideUp(300); i.prop('required',false).val('').removeClass('is-valid is-invalid'); }
    }).triggerHandler('change');
    $('#convenio_flacso').on('change', function(){
        const d = $('#contenedor-convenio-detalle'), i = $('#convenio_flacso_detalle');
        if(this.value==='Si'){ d.slideDown(300); i.prop('required',true); } else { d.slideUp(300); i.prop('required',false).val('').removeClass('is-valid is-invalid'); }
    }).triggerHandler('change');
    $('#genero').on('change', function(){
        const o = $('#contenedor-genero-otra'), i = $('#genero_otra');
        if(this.value==='Otra'){ o.slideDown(300); i.prop('required',true); } else { o.slideUp(300); i.prop('required',false).val('').removeClass('is-valid is-invalid'); }
    });
    function obtenerLabelArchivo(input) {
        const id = input.id || '';
        const labelEl = form.find('label[for="' + id + '"]').first();
        return labelEl.length ? labelEl.text().replace(/\*|\s+$/g,'').trim() : (input.name || 'Archivo');
    }

    function validarArchivoInput(input) {
        const maxFileSizeMb = Number(config.maxFileSize) || 3;
        const maxFileSizeBytes = maxFileSizeMb * 1024 * 1024;
        const archivos = input.files ? Array.from(input.files) : [];
        const demasiadoGrande = archivos.find(file => file && file.size > maxFileSizeBytes);
        const $input = $(input);

        input.setCustomValidity('');
        $input.removeClass('is-invalid');
        const feedback = $input.siblings('.invalid-feedback');

        if(demasiadoGrande){
            const msg = 'El archivo "' + demasiadoGrande.name + '" supera el límite de ' + maxFileSizeMb + ' MB.';
            input.setCustomValidity(msg);
            $input.addClass('is-invalid');
            if(feedback.length){ feedback.text(msg); }
            return { label: obtenerLabelArchivo(input), msg };
        }

        if(feedback.length && $input.prop('required')){
            feedback.text('Este documento es requerido.');
        }

        if(archivos.length){
            $input.addClass('is-valid');
        } else {
            $input.removeClass('is-valid');
        }

        return null;
    }

    function validarArchivosSeleccionados() {
        const errores = [];
        form.find('input[type="file"]').each(function(){
            const error = validarArchivoInput(this);
            if(error){ errores.push(error); }
        });
        return errores;
    }

    function actualizarObligatoriedadArchivos() {
        const completa = $('input[name="documentacion_completa"]:checked').val();
        const fileInputs = form.find('input[type="file"]');
        const esMaestria = $('input[name="es_maestria"]').val() === 'si';

        fileInputs.each(function() {
            const input = $(this);
            const id = input.attr('id');
            const label = form.find('label[for="' + id + '"]');
            
            // Determinar si este archivo debería ser requerido en el flujo completo
            let debieraSerRequerido = false;
            if (id === 'documento_identidad' || id === 'cv' || id === 'carta_motivacion' || id === 'titulo_grado') {
                debieraSerRequerido = true;
            } else if (id === 'carta_recomendacion_1' || id === 'carta_recomendacion_2') {
                debieraSerRequerido = esMaestria;
            }

            if (completa === 'No') {
                input.prop('required', false);
                input.removeClass('is-invalid is-valid');
                if (this.setCustomValidity) { this.setCustomValidity(''); }
                label.find('.text-danger').addClass('d-none');
            } else {
                if (debieraSerRequerido) {
                    input.prop('required', true);
                    label.find('.text-danger').removeClass('d-none');
                } else {
                    input.prop('required', false);
                    label.find('.text-danger').addClass('d-none');
                }
            }
        });
    }

    $('input[name="documentacion_completa"]').on('change', function(){
        const f = $('#contenedor-documentacion-faltante'), i = $('#documentacion_faltante');
        if(this.value==='No'){ 
            f.slideDown(300); 
            i.prop('required',true); 
        } else { 
            f.slideUp(300); 
            i.prop('required',false).val('').removeClass('is-valid is-invalid'); 
        }
        actualizarObligatoriedadArchivos();
        validarArchivosSeleccionados();
    });

    form.on('change', 'input[type="file"]', function(){
        const error = validarArchivoInput(this);
        if(error){
            mostrarInformeErrores([error]);
        } else {
            resultado.empty();
        }
    });

    // Validación en tiempo real básica
    $('#cedula_uruguaya').on('input', function(){
        const soloDigitos = obtenerDigitosCedula($(this).val());
        const formateada = formatearCedulaUruguaya(soloDigitos);
        if($(this).val() !== formateada){ $(this).val(formateada); }

        this.setCustomValidity('');
        if(soloDigitos === ''){
            $(this).removeClass('is-valid is-invalid');
            actualizarFeedbackCedula(mensajeCedulaBase);
            return;
        }
        if(soloDigitos.length < 7){
            const msg = 'La cédula debe tener 7 u 8 dígitos.';
            this.setCustomValidity(msg);
            $(this).removeClass('is-valid').addClass('is-invalid');
            actualizarFeedbackCedula(msg);
            return;
        }
        if(validarCedulaUruguaya(soloDigitos)){
            $(this).removeClass('is-invalid').addClass('is-valid');
            actualizarFeedbackCedula(mensajeCedulaBase);
        } else {
            const msg = 'El dígito verificador no coincide. Revise el número ingresado.';
            this.setCustomValidity(msg);
            $(this).removeClass('is-valid').addClass('is-invalid');
            actualizarFeedbackCedula(msg);
        }
    });
    $('#otro_documento').on('input', function(){ const v=$(this).val().trim(); $(this).toggleClass('is-invalid', v==='').toggleClass('is-valid', v!==''); });
    $('#correo').on('input', function(){
        const v = $(this).val().trim();
        if(v===''){ $(this).removeClass('is-valid is-invalid'); return; }
        const ok = /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(v);
        $(this).toggleClass('is-valid', ok).toggleClass('is-invalid', !ok);
    });

    $('#fecha_nacimiento').on('input change', function(){
        const v = $(this).val();
        if(v === ''){ $(this).removeClass('is-valid is-invalid'); return; }
        const fechaNac = new Date(v);
        const hoy = new Date();
        let edad = hoy.getFullYear() - fechaNac.getFullYear();
        const m = hoy.getMonth() - fechaNac.getMonth();
        if(m < 0 || (m === 0 && hoy.getDate() < fechaNac.getDate())){ edad--; }
        if(edad >= 18){
            $(this).removeClass('is-invalid').addClass('is-valid');
        } else {
            $(this).removeClass('is-valid').addClass('is-invalid');
            $(this).siblings('.invalid-feedback').text('Debe tener al menos 18 años.');
        }
    });

    $('#tipo_documento').on('change', function(){
        const tipo = $(this).val();
        const cCed = $('#contenedor-cedula'), cOtr = $('#contenedor-otro-documento');
        cCed.hide(); cOtr.hide();
        $('#cedula_uruguaya').val('').removeClass('is-valid is-invalid').prop('required', false).each(function(){ this.setCustomValidity(''); });
        actualizarFeedbackCedula(mensajeCedulaBase);
        $('#otro_documento').val('').removeClass('is-valid is-invalid').prop('required', false);
        if(tipo==='cedula_uruguaya'){ cCed.show(); $('#cedula_uruguaya').prop('required', true); }
        else if(tipo){ cOtr.show(); $('#otro_documento').prop('required', true); }
    });

    // Construir informe de errores tras intentar enviar
    function construirInformeErrores(){
        validarArchivosSeleccionados();
        const errores = [];

        // HTML5 invalids
        form.find(':input').each(function(){
            const el = this;
            if(el.checkValidity && !el.checkValidity()){
                const $el = $(el);
                const id  = el.id || el.name || '(campo)';
                // label
                let label = '';
                const lbl = form.find('label[for="'+id+'"]').first();
                label = lbl.length ? lbl.text().replace(/\*|\s+$/g,'').trim() : (id);
                // mensaje
                let msg = el.validationMessage || 'Campo inválido';
                if($el.is('[type="file"]') && $el.prop('required') && !$el.val()){ msg = 'Este documento es requerido.'; }
                errores.push({label, msg});
            }
        });

        // Teléfono (custom)
        const telVal = validarTelefono();
        if(!telVal.isValid){
            errores.push({ label:'Celular', msg: telVal.message });
        }

        // Tipo de documento coherente
        const tipo = $('#tipo_documento').val();
        if(tipo==='cedula_uruguaya' && !validarCedulaUruguaya(obtenerDigitosCedula($('#cedula_uruguaya').val()))){
            errores.push({ label:'Cédula de Identidad Uruguaya', msg:'Ingrese 7 u 8 dígitos con un dígito verificador válido.' });
        }
        if(tipo && tipo!=='cedula_uruguaya' && !($('#otro_documento').val()||'').trim()){
            errores.push({ label:'Número de Documento', msg:'Este campo es obligatorio.' });
        }

        // Documentación faltante si marcó "No"
        const docComp = $('input[name="documentacion_completa"]:checked').val();
        if(docComp==='No' && !($('#documentacion_faltante').val()||'').trim()){
            errores.push({ label:'Documentación faltante', msg:'Especifique qué documentación falta.' });
        }


        return errores;
    }

    // Mostrar informe de errores
    function mostrarInformeErrores(lista){
        if(!lista.length){ resultado.empty(); return; }
        const items = lista.map(e => `<li><strong>${e.label}:</strong> ${e.msg}</li>`).join('');
        resultado.html(`
            <div class="alert alert-danger">
                <div class="d-flex align-items-start">
                    <i class="bi bi-x-circle-fill me-2 mt-1"></i>
                    <div>
                        <h5 class="alert-heading mb-2">Hay campos que requieren atención</h5>
                        <p class="mb-2">Revise lo siguiente:</p>
                        <ul class="mb-0">${items}</ul>
                    </div>
                </div>
            </div>
        `);
        resultado[0].scrollIntoView({ behavior:'smooth', block:'start' });
    }
    
    const wizardState = {
        sections: $(),
        current: 0,
        initialized: false
    };

    function obtenerLabelCampo(el){
        const id = el.id || el.name || '(campo)';
        const lbl = form.find('label[for="' + id + '"]').first();
        return lbl.length ? lbl.text().replace(/\*|\s+$/g,'').trim() : id;
    }

    function validarCamposDeContenedor($scope){
        validarArchivosSeleccionados();
        const errores = [];
        const seen = new Set();

        $scope.find(':input').each(function(){
            const el = this;
            if(el.disabled || el.type === 'hidden'){ return; }
            if(el.offsetParent === null && !$(el).is('[type="radio"]')){ return; }

            if(el.checkValidity && !el.checkValidity()){
                const key = el.name || el.id || Math.random().toString(36);
                if(seen.has(key)){ return; }
                seen.add(key);

                const $el = $(el);
                let label = obtenerLabelCampo(el);
                let msg = el.validationMessage || 'Campo inválido';
                if($el.is('[type="file"]') && $el.prop('required') && !$el.val()){ msg = 'Este documento es requerido.'; }
                if($el.is('[type="radio"]')){
                    const groupLabel = $el.closest('.flacso-radio-group').find('> .form-label').first().text().replace(/\*|\s+$/g,'').trim();
                    label = groupLabel || label;
                    msg = 'Seleccione una opción.';
                }
                errores.push({label, msg});
            }
        });

        if($scope.find('#celular').length){
            const telVal = validarTelefono();
            if(!telVal.isValid){ errores.push({ label:'Celular', msg: telVal.message }); }
        }

        if($scope.find('#cedula_uruguaya').length){
            const tipo = $('#tipo_documento').val();
            if(tipo === 'cedula_uruguaya' && !validarCedulaUruguaya($('#cedula_uruguaya').val() || '')){
                errores.push({ label:'Cédula de Identidad Uruguaya', msg:'Ingrese 7 u 8 dígitos con un dígito verificador válido.' });
            }
            if(tipo && tipo !== 'cedula_uruguaya' && !($('#otro_documento').val() || '').trim()){
                errores.push({ label:'Número de Documento', msg:'Este campo es obligatorio.' });
            }
        }

        if($scope.find('#documentacion_faltante').length){
            const docComp = $('input[name="documentacion_completa"]:checked').val();
            if(docComp === 'No' && !($('#documentacion_faltante').val() || '').trim()){
                errores.push({ label:'Documentación faltante', msg:'Especifique qué documentación falta.' });
            }
        }

        return errores;
    }

    function validarPasoWizard(index){
        const $section = wizardState.sections.eq(index);
        const errores = validarCamposDeContenedor($section);
        if(errores.length){
            form[0].classList.add('was-validated');
            mostrarInformeErrores(errores);
            const firstInvalid = $section.find(':invalid:visible, .is-invalid:visible').first().get(0);
            if(firstInvalid){ firstInvalid.scrollIntoView({ behavior:'smooth', block:'center' }); firstInvalid.focus(); }
            return false;
        }
        resultado.empty();
        return true;
    }

    function actualizarWizard(){
        const total = wizardState.sections.length;
        wizardState.sections.each(function(i){
            const active = i === wizardState.current;
            $(this).toggleClass('is-active', active).prop('hidden', !active);
        });

        $('.flacso-wizard-step').each(function(i){
            $(this)
                .toggleClass('is-active', i === wizardState.current)
                .toggleClass('is-complete', i < wizardState.current)
                .attr('aria-current', i === wizardState.current ? 'step' : null);
        });

        $('.flacso-wizard-prev').prop('disabled', wizardState.current === 0);
        $('.flacso-wizard-next').toggle(wizardState.current < total - 1);
        $('.flacso-boton-envio').toggle(wizardState.current === total - 1);

        const title = wizardState.sections.eq(wizardState.current).find('.flacso-seccion-title').first().text().trim();
        $('.flacso-wizard-current').text('Paso ' + (wizardState.current + 1) + ' de ' + total + (title ? ': ' + title : ''));
    }

    function inicializarWizard(){
        const $sections = form.find('section.flacso-seccion');
        if($sections.length < 2 || wizardState.initialized){ return; }

        wizardState.sections = $sections;
        wizardState.initialized = true;

        const steps = $sections.map(function(i){
            const title = $(this).find('.flacso-seccion-title').first().text().trim() || ('Paso ' + (i + 1));
            return '<button type="button" class="flacso-wizard-step" data-step="' + i + '"><span>' + (i + 1) + '</span><strong>' + title + '</strong></button>';
        }).get().join('');

        form.prepend('<div class="flacso-wizard" aria-label="Progreso del formulario"><div class="flacso-wizard-current"></div><div class="flacso-wizard-steps">' + steps + '</div></div>');
        $('.flacso-boton-envio').before('<div class="flacso-wizard-nav"><button type="button" class="flacso-wizard-btn flacso-wizard-prev">Anterior</button><button type="button" class="flacso-wizard-btn flacso-wizard-next">Siguiente</button></div>');

        form.on('click', '.flacso-wizard-next', function(){
            if(!validarPasoWizard(wizardState.current)){ return; }
            wizardState.current = Math.min(wizardState.current + 1, wizardState.sections.length - 1);
            actualizarWizard();
            form.find('.flacso-wizard').get(0)?.scrollIntoView({ behavior:'smooth', block:'start' });
        });

        form.on('click', '.flacso-wizard-prev', function(){
            wizardState.current = Math.max(wizardState.current - 1, 0);
            actualizarWizard();
            resultado.empty();
            form.find('.flacso-wizard').get(0)?.scrollIntoView({ behavior:'smooth', block:'start' });
        });

        form.on('click', '.flacso-wizard-step', function(){
            const target = Number($(this).data('step'));
            if(!Number.isFinite(target) || target === wizardState.current){ return; }
            if(target > wizardState.current && !validarPasoWizard(wizardState.current)){ return; }
            wizardState.current = target;
            actualizarWizard();
            resultado.empty();
        });

        actualizarWizard();
    }

    async function enviarFormulario(){
        if(submissionInProgress){
            return;
        }
        if(preinscripcionesCerradas){
            mostrarAvisoCierre();
            btnSubmit.prop('disabled', true);
            return;
        }
        submissionInProgress = true;

        // Validación del teléfono previa
        const vTel = validarTelefono();
        // Preparar campo documento oculto
        const tipoDocumento = $('#tipo_documento').val();
        $('input[name="documento"]').remove();
        if(tipoDocumento==='cedula_uruguaya'){
            const ciLimpia = obtenerDigitosCedula($('#cedula_uruguaya').val());
            form.append('<input type="hidden" name="documento" value="'+ ciLimpia +'">');
        } else {
            form.append('<input type="hidden" name="documento" value="'+ (($('#otro_documento').val()||'').trim()) +'">');
        }

        const formData = new FormData(form[0]);

        resultado.empty();
        form.addClass('flacso-form-is-submitting').attr('aria-hidden', 'true');
        form.before(`
            <section class="flacso-submit-stage" role="status" aria-live="polite" aria-label="Envío de postulación en curso">
                <div class="flacso-submit-stage__card">
                    <div class="flacso-submit-stage__spinner" aria-hidden="true">
                        <span></span><span></span><span></span>
                    </div>
                    <div class="flacso-submit-stage__content">
                        <p class="flacso-submit-stage__eyebrow">Envío en curso</p>
                        <h2>Enviando tu postulación</h2>
                        <p>Estamos subiendo tus adjuntos y registrando la preinscripción. Mantené esta pestaña abierta hasta que termine.</p>
                        <div class="flacso-submit-stage__note">
                            Este proceso puede tardar unos minutos si adjuntaste varios archivos.
                        </div>
                    </div>
                </div>
            </section>
        `);
        $('.flacso-submit-stage').get(0)?.scrollIntoView({ behavior:'smooth', block:'start' });
        btnSubmit.prop('disabled', true).html('<i class="bi bi-hourglass-split me-2"></i>Enviando...');

        const controller = new AbortController();
        const timeoutMs = Number(config.submitTimeoutMs) || 240000;
        const timeoutId = window.setTimeout(() => controller.abort(), timeoutMs);

        try{
            const resp = await fetch(config.ajaxUrl, {
                method:'POST',
                body: formData,
                signal: controller.signal
            });
            const data = await resp.json();
            if(data.success){
                form.hide();
                const nombreCompleto = [$('#nombre1').val(), $('#apellido1').val()].filter(Boolean).join(' ').trim() || 'Postulante';
                const posgrado = config.tituloPosgrado || 'el posgrado seleccionado';

                const pixelPayload = {
                    content_name: posgrado,
                    content_category: 'oferta_academica',
                    status: 'completed',
                    flacso_stage: 'preinscripcion_enviada'
                };

                if (config.idPosgrado) {
                    pixelPayload.content_ids = ['oferta-' + String(config.idPosgrado)];
                }

                const precio = Number(config.valor);
                const currency = normalizeMetaCurrency(config.currency);
                if (Number.isFinite(precio) && precio > 0 && currency) {
                    pixelPayload.value = precio;
                    pixelPayload.currency = currency;
                }

                // La analítica no debe bloquear la confirmación ni la redirección.
                try {
                    trackMetaEvent('SubmitApplication', pixelPayload);
                } catch (trackingError) {
                    console.warn('[Preinscripcion] No se pudo registrar SubmitApplication', trackingError);
                }

                // Guardar en sessionStorage por si falla la obtención por pid
                try {
                    sessionStorage.setItem('consultaPrograma', posgrado);
                    sessionStorage.setItem('consultaNombreCompleto', nombreCompleto);
                } catch (storageError) {
                    console.warn('[Preinscripcion] sessionStorage no disponible', storageError);
                }

                // Redirigir a la página de gracias (hija de la URL actual)
                let currentUrl = window.location.href.split('?')[0];
                if (!currentUrl.endsWith('/')) {
                    currentUrl += '/';
                }
                const graciasUrl = currentUrl + 'gracias/?tipo=preinscripcion&pid=' + encodeURIComponent(config.idPosgrado || '');
                window.location.assign(appendMetaTestEventCode(graciasUrl));
                return;
            } else {
                const msg = data.data || 'Error desconocido del servidor. Por favor, intente nuevamente.';
                $('.flacso-submit-stage').remove();
                form.removeClass('flacso-form-is-submitting').removeAttr('aria-hidden');
                resultado.html(`
                    <div class="alert alert-danger">
                        <div class="d-flex align-items-start">
                            <i class="bi bi-x-circle-fill me-2 mt-1"></i>
                            <div>
                                <h5 class="alert-heading">Error en el envío</h5>
                                <p class="mb-2">${msg}</p>
                                <p class="mb-0 small">Si el problema persiste, contacte a: <a href="mailto:inscripciones@flacso.edu.uy">inscripciones@flacso.edu.uy</a></p>
                            </div>
                        </div>
                    </div>
                `);
                btnSubmit.prop('disabled', false).html('<i class="bi bi-send-check me-2"></i>Enviar Postulación');
                submissionInProgress = false;
            }
        } catch(e){
            console.error('Error en el envío:', e);
            const timeoutError = e && e.name === 'AbortError';
            const errorTitle = timeoutError ? 'Tiempo de espera agotado' : 'Error de conexión';
            const errorMessage = timeoutError
                ? 'No recibimos respuesta del servidor a tiempo. La preinscripción no se confirmó. Revisá que cada archivo pese menos de 3 MB y volvé a intentar.'
                : 'No pudimos procesar su postulación. Por favor, intente nuevamente en unos minutos.';
            $('.flacso-submit-stage').remove();
            form.removeClass('flacso-form-is-submitting').removeAttr('aria-hidden');
            resultado.html(`
                <div class="alert alert-danger">
                    <div class="d-flex align-items-start">
                        <i class="bi bi-x-circle-fill me-2 mt-1"></i>
                        <div>
                            <h5 class="alert-heading">${errorTitle}</h5>
                            <p class="mb-0">${errorMessage}</p>
                        </div>
                    </div>
                </div>
            `);
            btnSubmit.prop('disabled', false).html('<i class="bi bi-send-check me-2"></i>Enviar Postulación');
            submissionInProgress = false;
        } finally {
            window.clearTimeout(timeoutId);
        }
    }

    inicializarWizard();

    // Bootstrap validation + reporte de errores al enviar
    (function(){
        const forms = document.querySelectorAll('.needs-validation');
        Array.from(forms).forEach(f=>{
            f.addEventListener('submit', async function(ev){
                ev.preventDefault(); ev.stopPropagation();

                if(preinscripcionesCerradas){
                    mostrarAvisoCierre();
                    return;
                }

                // Forzar evaluar teléfono si el usuario nunca interactuó
                if(!telefonoHaSidoInteractuado){ telefonoHaSidoInteractuado = true; validarTelefono(); }

                if(wizardState.initialized && wizardState.current < wizardState.sections.length - 1){
                    validarPasoWizard(wizardState.current);
                    return;
                }

                // Construir informe de errores ANTES del checkValidity para incluir custom
                const listaErrores = construirInformeErrores();

                // Validación HTML5
                if(!f.checkValidity() || listaErrores.length){
                    f.classList.add('was-validated');
                    mostrarInformeErrores(listaErrores);
                    const firstInvalid = f.querySelector(':invalid') || document.getElementById('celular');
                    if(firstInvalid){ firstInvalid.scrollIntoView({ behavior:'smooth', block:'center' }); firstInvalid.focus(); }
                    return;
                }

                // Todo ok: enviar
                await enviarFormulario();
                // Desplazar al área de resultado para que el usuario lo vea
                const areaResultado = document.getElementById('flacso-resultado-envio');
                if (areaResultado && typeof areaResultado.scrollIntoView === 'function') {
                    setTimeout(() => {
                        areaResultado.scrollIntoView({ behavior:'smooth', block:'start' });
                    }, 100);
                }
            }, false);
        });
    })();

    // No marcamos de rojo al cargar: sólo tras intento de envío.
});
