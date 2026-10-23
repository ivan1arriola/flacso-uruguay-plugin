/* Evita perder cambios no guardados al saltar del editor clásico al nuevo. */
(function () {
    'use strict';

    function ready(callback) {
        if (document.readyState === 'loading') {
            document.addEventListener('DOMContentLoaded', callback, { once: true });
        } else {
            callback();
        }
    }

    ready(function () {
        var form = document.getElementById('post');
        if (!form) return;

        var dirty = false;
        function markDirty(event) {
            if (event.target && form.contains(event.target)) {
                dirty = true;
            }
        }

        form.addEventListener('input', markDirty);
        form.addEventListener('change', markDirty);
        form.addEventListener('submit', function () { dirty = false; });

        document.addEventListener('click', function (event) {
            var link = event.target.closest('.flacso-editor-switch__link');
            if (!link || !dirty) return;

            if (!window.confirm('Tenés cambios sin guardar. Si cambiás de editor, se perderán. ¿Querés continuar?')) {
                event.preventDefault();
            }
        });
    });
}());
