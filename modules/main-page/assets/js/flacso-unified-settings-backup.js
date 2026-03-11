/**
 * Gestor unificado de configuracion FLACSO (vanilla JS)
 * Tabs, guardado parcial por AJAX y previsualizaciones de imagen
 */

(function() {
    'use strict';

    const qs = (sel, ctx = document) => ctx.querySelector(sel);
    const qsa = (sel, ctx = document) => Array.from(ctx.querySelectorAll(sel));

    function init() {
        const tabs = qsa('.flacso-unified-tab');
        const panels = qsa('.flacso-unified-panel');
        const saveButtons = qsa('.flacso-save-section');
        const form = qs('.flacso-unified-form');

        if (!form || !tabs.length || !panels.length) {
            console.warn('FLACSO: Elementos no encontrados. Tabs:', tabs.length, 'Panels:', panels.length, 'Form:', form ? 'OK' : 'NO ENCONTRADO');
            return;
        }

        console.log('FLACSO: Inicializando gestor unificado. Botones encontrados:', saveButtons.length);

        tabs.forEach(tab => {
            tab.addEventListener('click', () => activateTab(tab, tabs, panels));
        });

        saveButtons.forEach(button => {
            button.addEventListener('click', (e) => saveSection(e, form));
        });

        // Previews iniciales y on change
        qsa('[data-preview-target]', form).forEach(input => {
            refreshPreview(input, form);
            input.addEventListener('input', () => refreshPreview(input, form));
            input.addEventListener('change', () => refreshPreview(input, form));
        });

        // Tab almacenado o primero
        const stored = localStorage.getItem('flacsoLastTab');
        const storedTab = stored ? tabs.find(t => t.dataset.tab === stored) : null;
        activateTab(storedTab || tabs[0], tabs, panels);
    }

    function activateTab(tab, tabs, panels) {
        if (!tab) return;
        const tabName = tab.dataset.tab;
        if (!tabName) return;

        tabs.forEach(t => {
            t.setAttribute('aria-selected', 'false');
            t.classList.remove('is-active');
        });
        panels.forEach(p => {
            p.classList.remove('is-active');
            p.style.display = 'none';
        });

        tab.setAttribute('aria-selected', 'true');
        tab.classList.add('is-active');
        const target = document.getElementById(`flacso-panel-${tabName}`);
        if (target) {
            target.classList.add('is-active');
            target.style.display = '';
        }

        localStorage.setItem('flacsoLastTab', tabName);
    }

    function saveSection(event, form) {
        event.preventDefault();
        const button = event.currentTarget;
        const sectionName = button.dataset.section;
        
        if (!sectionName || button.classList.contains('is-saving')) {
            return;
        }

        // Validar que tenemos acceso a los datos globales
        const ajaxUrl = (typeof flacsoSettings !== 'undefined' && flacsoSettings.ajaxUrl) || window.ajaxurl || '';
        const nonce = (typeof flacsoSettings !== 'undefined' && flacsoSettings.nonce) || '';
        
        if (!ajaxUrl) {
            console.error('flacsoSettings.ajaxUrl no definido');
            showNotice('error', 'Error de configuración: URL AJAX no disponible', button);
            return;
        }

        if (!nonce) {
            console.error('flacsoSettings.nonce no definido');
            showNotice('error', 'Error de configuración: Token de seguridad no disponible', button);
            return;
        }

        button.classList.add('is-saving');

        const sectionData = getSectionData(sectionName, form);
        const params = new URLSearchParams();
        params.append('action', 'flacso_save_settings_section');
        params.append('nonce', nonce);
        params.append('section', sectionName);
        appendNested(params, 'data', sectionData);

        fetch(ajaxUrl, {
            method: 'POST',
            credentials: 'same-origin',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8' },
            body: params.toString(),
        })
            .then(res => res.json().catch(() => ({})))
            .then(json => {
                if (json && json.success) {
                    const message = (json.data && json.data.message) || `${sectionName} guardado exitosamente`;
                    showNotice('success', message, button);
                } else {
                    showNotice('error', (json && json.data && json.data.message) || `Error al guardar ${sectionName}`, button);
                }
            })
            .catch(err => {
                console.error('Error guardando sección', err);
                showNotice('error', `Error al guardar ${sectionName}`, button);
            })
            .finally(() => {
                button.classList.remove('is-saving');
            });
    }

    function getSectionData(sectionName, form) {
        const data = {};
        const prefix = `${sectionName}[`;

        qsa('[name]', form).forEach(el => {
            const name = el.name || '';
            if (!name.startsWith(prefix)) {
                return;
            }
            const path = name.slice(prefix.length, -1).split('][');
            let value;
            if (el.type === 'checkbox') {
                value = el.checked ? '1' : '0';
            } else if (el.type === 'radio') {
                if (!el.checked) return;
                value = el.value;
            } else {
                value = el.value;
            }
            assignPath(data, path, value);
        });

        return data;
    }

    function assignPath(target, path, value) {
        if (!path.length) return;
        let current = target;
        path.forEach((segment, idx) => {
            const last = idx === path.length - 1;
            if (last) {
                current[segment] = value;
                return;
            }
            if (typeof current[segment] !== 'object' || current[segment] === null) {
                current[segment] = {};
            }
            current = current[segment];
        });
    }

    function appendNested(params, prefix, obj) {
        Object.keys(obj || {}).forEach(key => {
            const value = obj[key];
            const fullKey = `${prefix}[${key}]`;
            if (value !== null && typeof value === 'object') {
                appendNested(params, fullKey, value);
            } else {
                params.append(fullKey, value);
            }
        });
    }

    function refreshPreview(input, form) {
        const target = input.dataset.previewTarget;
        if (!target) return;
        const preview = form.querySelector(`[data-image-preview="${target}"]`);
        if (!preview) return;

        const url = (input.value || '').trim();
        if (url) {
            let img = preview.querySelector('img');
            if (!img) {
                preview.innerHTML = '';
                img = document.createElement('img');
                preview.appendChild(img);
            }
            img.src = url;
            img.alt = 'Vista previa';
            preview.classList.add('has-image');
        } else {
            preview.classList.remove('has-image');
            preview.innerHTML = '<span class="flacso-image-placeholder">Sin imagen</span>';
        }
    }

    function showNotice(type, message, button) {
        const notice = document.createElement('div');
        notice.className = `flacso-settings-notice ${type}`;
        notice.textContent = message;
        const parent = button.parentElement;
        if (parent) {
            parent.insertAdjacentElement('beforebegin', notice);
            setTimeout(() => {
                notice.style.opacity = '0';
                setTimeout(() => notice.remove(), 300);
            }, 3000);
        }
    }

    // Ejecutar cuando el DOM esté listo
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        // Si el DOM ya está listo, ejecutar inmediatamente
        setTimeout(init, 0);
    }
})();
