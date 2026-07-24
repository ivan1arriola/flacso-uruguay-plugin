document.addEventListener('DOMContentLoaded', function () {
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
		sync();
	});
	document.querySelectorAll('.flacso-hook-form').forEach(function (form) {
		form.addEventListener('submit', function (event) {
			form.classList.add('was-validated');
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
			var status = form.querySelector('.flacso-hook-form-status');
			if (button) button.disabled = true;
			if (status) status.textContent = 'Enviando…';
		});
	});
});
