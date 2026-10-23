/* Controles puramente visuales. El guardado está en el adaptador PHP. */
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
        var form = document.querySelector('.flacso-new-editor__form');
        if (!form) return;

        var isDirty = false;
        var submitting = false;
        var hint = form.querySelector('[data-flacso-dirty]');
        var submit = form.querySelector('[data-flacso-save]');
        function markDirty() {
            isDirty = true;
            if (hint) hint.textContent = 'Hay cambios sin guardar.';
        }

        form.addEventListener('input', markDirty);
        form.addEventListener('change', markDirty);
        form.addEventListener('submit', function () {
            if (window.tinyMCE && typeof window.tinyMCE.triggerSave === 'function') {
                window.tinyMCE.triggerSave();
            }
            submitting = true;
            if (submit) {
                submit.disabled = true;
                submit.textContent = 'Guardando…';
            }
            if (hint) hint.textContent = 'Enviando cambios…';
        });

        window.addEventListener('beforeunload', function (event) {
            if (!submitting && isDirty) {
                event.preventDefault();
                event.returnValue = '';
            }
        });

        // WP Media: mismo selector de imágenes del editor clásico.
        var input = document.getElementById('flacso-offer-thumbnail-id');
        var preview = document.getElementById('flacso-offer-thumbnail-preview');
        var choose = document.getElementById('flacso-offer-thumbnail-pick');
        var remove = document.getElementById('flacso-offer-thumbnail-remove');
        if (!input || !preview || !choose || !remove) return;

        var frame = null;
        function setImage(id, src) {
            input.value = id || 0;
            while (preview.firstChild) preview.removeChild(preview.firstChild);
            if (src) {
                var img = document.createElement('img');
                img.src = src;
                img.alt = '';
                preview.appendChild(img);
            } else {
                var empty = document.createElement('span');
                empty.textContent = 'Sin imagen destacada';
                preview.appendChild(empty);
            }
            markDirty();
        }

        choose.addEventListener('click', function () {
            if (!window.wp || !window.wp.media) {
                window.alert('No se pudo abrir la Biblioteca de medios.');
                return;
            }
            if (!frame) {
                frame = window.wp.media({
                    title: 'Imagen destacada de la oferta',
                    button: { text: 'Utilizar imagen' },
                    library: { type: 'image' },
                    multiple: false
                });
                frame.on('select', function () {
                    var item = frame.state().get('selection').first();
                    var image = item ? item.toJSON() : null;
                    if (!image || !image.id || image.type !== 'image') return;
                    var src = image.sizes && image.sizes.medium ? image.sizes.medium.url : image.url;
                    setImage(image.id, src);
                });
            }
            frame.open();
        });
        remove.addEventListener('click', function () { setImage(0, ''); });

        // TinyMCE no emite necesariamente eventos en el textarea oculto.
        if (window.tinymce && typeof window.tinymce.on === 'function') {
            window.tinymce.on('AddEditor', function (event) {
                if (event.editor && event.editor.on) {
                    event.editor.on('change keyup undo redo', markDirty);
                }
            });
        }
    });
}());
