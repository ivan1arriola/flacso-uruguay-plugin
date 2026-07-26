document.addEventListener('DOMContentLoaded', function () {
	var MAX_FILE_SIZE = 4 * 1024 * 1024;
	var MAX_TOTAL_FILE_SIZE = 4 * 1024 * 1024;
	var ALLOWED_FILE_EXTENSIONS = ['pdf', 'jpg', 'jpeg', 'png', 'webp'];

	function validUyDocument(value) {
		var digits = String(value || '').replace(/\D+/g, '');
		if (digits.length === 7) digits = '0' + digits;
		if (digits.length !== 8) return false;
		var factors = [2, 9, 8, 7, 6, 3, 4];
		var sum = 0;
		for (var i = 0; i < 7; i += 1) sum += Number(digits[i]) * factors[i];
		return ((10 - (sum % 10)) % 10) === Number(digits[7]);
	}

	function controlFor(field) {
		return field ? field.closest('.flacso-hook-control') : null;
	}

	function liveErrorFor(field) {
		var control = controlFor(field);
		if (!control) return null;
		var error = control.querySelector('.flacso-hook-live-error, .flacso-hook-field-error');
		if (!error) {
			error = document.createElement('small');
			control.appendChild(error);
		}
		error.classList.add('flacso-hook-live-error');
		error.setAttribute('aria-live', 'polite');
		return error;
	}

	function setFieldState(field, message, show) {
		var control = controlFor(field);
		var error = liveErrorFor(field);
		field.setCustomValidity(message || '');
		var visible = Boolean(show && message);
		field.classList.toggle('flacso-hook-invalid', visible);
		field.setAttribute('aria-invalid', visible ? 'true' : 'false');
		if (control) control.classList.toggle('flacso-hook-control-invalid', visible);
		if (error) {
			error.textContent = visible ? message : '';
			error.hidden = !visible;
		}
		return !message;
	}

	function totalFileSize(form) {
		return Array.prototype.reduce.call(form.querySelectorAll('input[type="file"]'), function (total, input) {
			return total + Array.prototype.reduce.call(input.files || [], function (subtotal, file) {
				return subtotal + Number(file.size || 0);
			}, 0);
		}, 0);
	}

	function validateField(field, show) {
		if (!field || field.disabled || field.type === 'hidden') return true;
		field.setCustomValidity('');
		var value = String(field.value || '').trim();
		var message = '';

		if (field.required && field.validity.valueMissing) {
			message = field.type === 'file' ? 'Adjuntá este archivo obligatorio.' : 'Completá este campo obligatorio.';
		} else if (field.type === 'email' && value && field.validity.typeMismatch) {
			message = 'Ingresá un correo válido, por ejemplo nombre@dominio.com.';
		} else if (field.type === 'file' && field.files && field.files.length) {
			var file = field.files[0];
			var extension = String(file.name || '').split('.').pop().toLowerCase();
			if (ALLOWED_FILE_EXTENSIONS.indexOf(extension) === -1) {
				message = 'El archivo debe ser PDF, JPG, PNG o WebP.';
			} else if (file.size > MAX_FILE_SIZE) {
				message = 'Este archivo supera los 4 MB. Reducí su tamaño antes de continuar.';
			} else if (totalFileSize(field.form) > MAX_TOTAL_FILE_SIZE) {
				message = 'Los archivos adjuntos superan los 4 MB en total.';
			}
		} else if (field.classList.contains('flacso-hook-phone') && value) {
			if (field._flacsoPhone && typeof field._flacsoPhone.isValidNumber === 'function') {
				if (!field._flacsoPhone.isValidNumber()) message = 'Ingresá un teléfono válido para el país seleccionado.';
			} else if (!/^[+0-9 ()-]{6,30}$/.test(value)) {
				message = 'Ingresá un teléfono válido, incluyendo el código de país.';
			}
		} else if (field.closest('.flacso-hook-document') && value) {
			var documentControl = field.closest('.flacso-hook-document');
			var documentType = documentControl.querySelector('select');
			if (documentType && documentType.value === 'uy' && !validUyDocument(value)) {
				message = 'La cédula no es válida. Revisá el número y el dígito final.';
			} else if (documentType && documentType.value === 'ext' && (value.length < 3 || value.length > 40)) {
				message = 'El documento extranjero debe tener entre 3 y 40 caracteres.';
			}
		}

		return setFieldState(field, message, show);
	}

	document.querySelectorAll('.flacso-hook-phone').forEach(function (input) {
		if (typeof window.intlTelInput !== 'function') return;
		var phone = window.intlTelInput(input, {
			initialCountry: 'uy',
			preferredCountries: ['uy', 'ar', 'br', 'cl', 'py'],
			separateDialCode: true,
			autoPlaceholder: 'aggressive',
			loadUtils: function () {
				return import('https://cdn.jsdelivr.net/npm/intl-tel-input@25.12.4/build/js/utils.js');
			}
		});
		input._flacsoPhone = phone;
	});
	document.querySelectorAll('.flacso-hook-document').forEach(function (control) {
		var select = control.querySelector('select');
		var input = control.querySelector('input');
		if (!select || !input) return;
		function sync() {
			var uruguayan = select.value === 'uy';
			input.inputMode = uruguayan ? 'numeric' : 'text';
			input.placeholder = uruguayan ? '1.234.567-8' : '';
		}
		select.addEventListener('change', sync);
		select.addEventListener('change', function () {
			input.dataset.flacsoTouched = 'true';
			validateField(input, true);
		});
		sync();
	});
	document.querySelectorAll('.flacso-hook-form').forEach(function (form) {
		var fields = form.querySelectorAll('input:not([type="hidden"]), select, textarea');
		fields.forEach(function (field) {
			field.addEventListener('blur', function () {
				field.dataset.flacsoTouched = 'true';
				validateField(field, true);
			});
			field.addEventListener(field.type === 'file' || field.tagName === 'SELECT' || field.type === 'checkbox' || field.type === 'radio' ? 'change' : 'input', function () {
				if (field.type === 'file') field.dataset.flacsoTouched = 'true';
				validateField(field, field.dataset.flacsoTouched === 'true');
				if ((field.type === 'radio' || field.type === 'checkbox') && field.name) {
					form.querySelectorAll('input[name="' + CSS.escape(field.name) + '"]').forEach(function (groupField) {
						validateField(groupField, groupField.dataset.flacsoTouched === 'true' || field.dataset.flacsoTouched === 'true');
					});
				}
				if (field.type === 'file') {
					form.querySelectorAll('input[type="file"]').forEach(function (otherFile) {
						if (otherFile.files && otherFile.files.length) validateField(otherFile, true);
					});
				}
			});
		});

		form.addEventListener('submit', function (event) {
			form.classList.add('was-validated');
			fields.forEach(function (field) {
				field.dataset.flacsoTouched = 'true';
				validateField(field, true);
			});
			if (!form.checkValidity()) {
				event.preventDefault();
				var invalid = form.querySelector(':invalid');
				if (invalid) invalid.focus();
				return;
			}
			form.querySelectorAll('.flacso-hook-phone').forEach(function (input) {
				if (input._flacsoPhone && input.value.trim()) {
					input.value = input._flacsoPhone.getNumber();
				}
			});
			var button = form.querySelector('button[type="submit"]');
			var buttonLabel = form.querySelector('.flacso-hook-submit-label');
			var status = form.querySelector('.flacso-hook-form-status');
			if (button) {
				button.disabled = true;
				button.classList.add('is-loading');
				button.setAttribute('aria-busy', 'true');
			}
			if (buttonLabel) buttonLabel.textContent = 'Enviando…';
			if (status) status.textContent = 'Enviando el formulario. Por favor, esperá.';
		});
	});
});
