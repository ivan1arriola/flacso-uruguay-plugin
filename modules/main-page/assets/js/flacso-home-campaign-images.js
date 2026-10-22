(function () {
    'use strict';

    function start() {
        document.querySelectorAll('.flacso-home-campaign-image-field').forEach(function (field) {
            var input = field.querySelector('input[type="hidden"]');
            var img = field.querySelector('img');
            var placeholder = field.querySelector('span');
            var choose = field.querySelector('.flacso-campaign-image-choose');
            var remove = field.querySelector('.flacso-campaign-image-remove');
            if (!input || !img || !placeholder || !choose || !remove) return;

            function update(url, id) {
                input.value = id || 0;
                img.src = url || '';
                img.style.display = url ? 'block' : 'none';
                placeholder.hidden = !!url;
                choose.textContent = url ? 'Cambiar foto' : 'Elegir foto';
            }

            choose.addEventListener('click', function () {
                if (!window.wp || !wp.media) {
                    window.alert('La biblioteca multimedia no pudo cargarse. Actualizá la página e intentá nuevamente.');
                    return;
                }
                var frame = wp.media({
                    title: 'Fotografía del encabezado',
                    button: { text: 'Usar esta foto' },
                    library: { type: 'image' },
                    multiple: false
                });
                frame.on('select', function () {
                    var selection = frame.state().get('selection').first();
                    if (!selection) return;
                    var item = selection.toJSON();
                    var url = item.sizes && item.sizes.large ? item.sizes.large.url : item.url;
                    update(url, item.id);
                });
                frame.open();
            });

            remove.addEventListener('click', function () {
                update('', 0);
            });
        });
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', start);
    } else {
        start();
    }
}());
