document.addEventListener('DOMContentLoaded', function () {
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
		form.addEventListener('submit', function () {
			var button = form.querySelector('button[type="submit"]');
			var status = form.querySelector('.flacso-hook-form-status');
			if (button) button.disabled = true;
			if (status) status.textContent = 'Enviando…';
		});
	});
});
