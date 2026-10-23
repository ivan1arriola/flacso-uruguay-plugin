/* Navegación editorial sin modificar ni clonar campos WordPress. */
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
        var postBody = document.getElementById('post-body');
        var mainContent = document.getElementById('post-body-content');
        var normal = document.getElementById('postbox-container-2');
        var side = document.getElementById('postbox-container-1');
        var offer = document.querySelector('#flacso_oferta_academica_fields .flacso-oferta-fields');
        var cohort = document.querySelector('#flacso_cohorte_meta .flacso-cohort-editor');
        var root = offer || cohort;

        // WordPress mantiene su DOM original de editor clásico: si otro plugin
        // cambia la estructura, no se altera el layout ni el guardado.
        if (!postBody || !mainContent || !normal || !side || !root ||
            mainContent.parentElement !== postBody ||
            normal.parentElement !== postBody ||
            side.parentElement !== postBody) {
            return;
        }

        var sections = Array.from(root.querySelectorAll('.flacso-oferta-section, .flacso-academic-section'))
            .filter(function (section) {
                return section.closest('.flacso-oferta-fields, .flacso-cohort-editor') === root &&
                    section.querySelector('.flacso-oferta-section__copy strong, .flacso-academic-section__copy strong');
            });

        if (!sections.length) return;

        var nav = document.createElement('nav');
        nav.id = 'flacso-academic-editor-nav';
        nav.setAttribute('aria-label', 'Secciones de la edición académica');

        var eyebrow = document.createElement('p');
        eyebrow.className = 'flacso-editor-nav__overline';
        eyebrow.textContent = 'FLACSO · Gestión académica';

        var title = document.createElement('strong');
        title.className = 'flacso-editor-nav__title';
        title.textContent = offer ? 'Editar oferta académica' : 'Editar cohorte';

        var body = document.createElement('div');
        body.className = 'flacso-editor-nav__body';

        var linksContainer = document.createElement('div');
        linksContainer.className = 'flacso-editor-nav__links';
        var pairs = [];

        sections.forEach(function (section, index) {
            if (!section.id) {
                section.id = 'flacso-editor-section-' + (index + 1);
            }
            var labelElement = section.querySelector('.flacso-oferta-section__copy strong, .flacso-academic-section__copy strong');
            var link = document.createElement('a');
            link.href = '#' + section.id;
            link.className = 'flacso-editor-nav__link';
            link.textContent = labelElement.textContent.trim();
            link.addEventListener('click', function (event) {
                event.preventDefault();
                section.scrollIntoView({
                    behavior: window.matchMedia('(prefers-reduced-motion: reduce)').matches ? 'auto' : 'smooth',
                    block: 'start'
                });
                setActive(link);
            });
            linksContainer.appendChild(link);
            pairs.push({ section: section, link: link });
        });

        // En pantallas pequeñas se reemplaza la tira de pestañas por un selector.
        var pickerLabel = document.createElement('label');
        pickerLabel.className = 'flacso-editor-nav__picker';
        var pickerText = document.createElement('span');
        pickerText.textContent = 'Ir a sección';
        var picker = document.createElement('select');
        picker.setAttribute('aria-label', 'Ir a sección de edición');
        pairs.forEach(function (pair, index) {
            var option = document.createElement('option');
            option.value = String(index);
            option.textContent = pair.link.textContent;
            picker.appendChild(option);
        });
        picker.addEventListener('change', function () {
            var current = pairs[Number(picker.value)];
            if (!current) return;
            current.section.scrollIntoView({
                behavior: window.matchMedia('(prefers-reduced-motion: reduce)').matches ? 'auto' : 'smooth',
                block: 'start'
            });
            setActive(current.link);
        });
        pickerLabel.appendChild(pickerText);
        pickerLabel.appendChild(picker);

        var actions = document.createElement('div');
        actions.className = 'flacso-editor-nav__actions';
        var nativeSave = document.getElementById('publish');
        var saveButton = null;

        if (nativeSave) {
            saveButton = document.createElement('button');
            saveButton.type = 'button';
            saveButton.className = 'flacso-editor-save';
            // El texto original distingue Publicar, Actualizar y permisos.
            saveButton.textContent = nativeSave.value || nativeSave.textContent.trim() || 'Guardar';
            saveButton.disabled = nativeSave.disabled;
            saveButton.addEventListener('click', function () {
                if (nativeSave.disabled) return;
                // Click nativo: mantiene validaciones, nonces, roles y hooks.
                nativeSave.click();
            });
            actions.appendChild(saveButton);
        }

        var note = document.createElement('p');
        note.className = 'flacso-editor-nav__note';
        note.setAttribute('aria-live', 'polite');
        note.textContent = 'Los cambios se guardan con WordPress.';
        actions.appendChild(note);

        body.appendChild(linksContainer);
        body.appendChild(pickerLabel);
        body.appendChild(actions);
        nav.appendChild(eyebrow);
        nav.appendChild(title);
        nav.appendChild(body);

        postBody.insertBefore(nav, postBody.firstChild);
        postBody.classList.add('flacso-editor-layout');

        function setActive(activeLink) {
            pairs.forEach(function (pair, index) {
                if (pair.link === activeLink) {
                    pair.link.setAttribute('aria-current', 'location');
                    picker.value = String(index);
                } else {
                    pair.link.removeAttribute('aria-current');
                }
            });
        }

        function updateActive() {
            var current = pairs[0];
            var threshold = window.innerWidth <= 782 ? 135 : 115;
            pairs.forEach(function (pair) {
                if (pair.section.getBoundingClientRect().top <= threshold) {
                    current = pair;
                }
            });
            if (current) setActive(current.link);
        }

        var pendingScroll = false;
        window.addEventListener('scroll', function () {
            if (pendingScroll) return;
            pendingScroll = true;
            window.requestAnimationFrame(function () {
                updateActive();
                pendingScroll = false;
            });
        }, { passive: true });
        window.addEventListener('resize', updateActive, { passive: true });
        updateActive();

        // Aviso informativo: nunca afirma que se guardó automáticamente.
        var form = document.getElementById('post');
        if (form) {
            form.addEventListener('input', function (event) {
                if (!root.contains(event.target) &&
                    !mainContent.contains(event.target)) return;
                note.textContent = 'Hay cambios sin guardar.';
                note.classList.add('is-dirty');
            });
            form.addEventListener('change', function (event) {
                if (!root.contains(event.target) &&
                    !mainContent.contains(event.target)) return;
                note.textContent = 'Hay cambios sin guardar.';
                note.classList.add('is-dirty');
            });
            form.addEventListener('submit', function () {
                note.textContent = 'Enviando cambios a WordPress…';
            });
        }
    });
}());
