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

        initReorderLists();
        initInstagramImporter();

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

    function initReorderLists() {
        const lists = qsa('[data-reorder-list]');
        lists.forEach(list => {
            list.addEventListener('click', (event) => {
                const btn = event.target.closest('[data-move]');
                if (!btn) return;
                event.preventDefault();
                const item = btn.closest('[data-reorder-item]');
                if (!item || item.dataset.fixed === 'true') return;
                if (btn.dataset.move === 'up') {
                    const prev = item.previousElementSibling;
                    if (prev && prev.dataset.fixed !== 'true') {
                        item.parentNode.insertBefore(item, prev);
                    }
                } else if (btn.dataset.move === 'down') {
                    const next = item.nextElementSibling;
                    if (next && next.dataset.fixed !== 'true') {
                        item.parentNode.insertBefore(next, item);
                    }
                }
                updateReorderButtons(list);
                updateReorderInputValues(list);
            });
            updateReorderButtons(list);
        });
    }

    function updateReorderInputValues(list) {
        const items = qsa('[data-reorder-item]', list);
        items.forEach((item, index) => {
            const input = qs('input[type="hidden"][name*="[sections_order]"]', item);
            if (input) {
                const key = item.querySelector('[class*="reorder-key"]')?.textContent?.trim();
                if (key) {
                    input.value = key;
                }
            }
        });
    }

    function updateReorderButtons(list) {
        const items = qsa('[data-reorder-item]', list);
        items.forEach((item, index) => {
            qsa('[data-move]', item).forEach(btn => {
                const dir = btn.dataset.move;
                if (item.dataset.fixed === 'true') {
                    btn.disabled = true;
                    return;
                }
                const isFirstMovable = index === 0 || (items[index - 1] && items[index - 1].dataset.fixed === 'true');
                const isLast = index === items.length - 1;
                btn.disabled = (dir === 'up' && isFirstMovable) || (dir === 'down' && isLast);
            });
        });
    }

    function initInstagramImporter() {
        const importer = qs('[data-instagram-importer]');
        if (!importer) return;

        const loadButton = qs('[data-instagram-load]', importer);
        const list = qs('[data-instagram-list]', importer);
        const notice = qs('[data-instagram-notice]', importer);
        if (!loadButton || !list || !notice) return;

        loadButton.addEventListener('click', () => loadInstagramPosts(importer));
        list.addEventListener('click', (event) => {
            const button = event.target.closest('[data-instagram-import]');
            if (!button) return;
            event.preventDefault();
            importInstagramPost(button, importer);
        });
    }

    function getInstagramAjaxConfig() {
        return {
            ajaxUrl: (typeof flacsoSettings !== 'undefined' && flacsoSettings.ajaxUrl) || window.ajaxurl || '',
            nonce: (typeof flacsoSettings !== 'undefined' && flacsoSettings.nonce) || '',
        };
    }

    function loadInstagramPosts(importer) {
        const { ajaxUrl, nonce } = getInstagramAjaxConfig();
        const button = qs('[data-instagram-load]', importer);
        const list = qs('[data-instagram-list]', importer);

        if (!ajaxUrl || !nonce) {
            setInstagramNotice(importer, 'error', 'No se pudo iniciar el importador: falta configuración AJAX.');
            return;
        }

        button.disabled = true;
        importer.classList.add('is-loading');
        setInstagramNotice(importer, 'info', 'Cargando publicaciones recientes...');
        list.innerHTML = '';

        const params = new URLSearchParams();
        params.append('action', 'flacso_instagram_import_preview');
        params.append('nonce', nonce);

        fetch(ajaxUrl, {
            method: 'POST',
            credentials: 'same-origin',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8' },
            body: params.toString(),
        })
            .then(res => res.json().catch(() => ({})))
            .then(json => {
                if (!json || !json.success) {
                    throw new Error((json && json.data && json.data.message) || 'No se pudieron cargar las publicaciones.');
                }

                const items = (json.data && json.data.items) || [];
                renderInstagramItems(importer, items);
                setInstagramNotice(importer, 'success', (json.data && json.data.message) || 'Publicaciones cargadas.');
            })
            .catch(error => {
                console.error('Error cargando Instagram', error);
                setInstagramNotice(importer, 'error', error.message || 'No se pudieron cargar las publicaciones.');
            })
            .finally(() => {
                button.disabled = false;
                importer.classList.remove('is-loading');
            });
    }

    function renderInstagramItems(importer, items) {
        const list = qs('[data-instagram-list]', importer);
        if (!list) return;

        if (!items.length) {
            list.innerHTML = '<p class="description">No hay publicaciones recientes para importar.</p>';
            return;
        }

        list.innerHTML = items.map(item => {
            const thumb = item.thumbnailUrl || item.mediaUrl || '';
            const imported = Boolean(item.imported);
            const badge = imported
                ? '<span class="flacso-instagram-import-card__badge is-imported">Importada</span>'
                : '<span class="flacso-instagram-import-card__badge">Disponible</span>';
            const mediaLabel = formatMediaType(item.mediaType, item.childrenCount);

            return `
                <article class="flacso-instagram-import-card" data-instagram-card="${escapeAttribute(item.id)}">
                    <div class="flacso-instagram-import-card__media">
                        ${thumb ? `<img src="${escapeAttribute(thumb)}" alt="">` : '<span class="dashicons dashicons-format-image"></span>'}
                        <span class="flacso-instagram-import-card__type">${escapeHtml(mediaLabel)}</span>
                    </div>
                    <div class="flacso-instagram-import-card__body">
                        <div class="flacso-instagram-import-card__meta">
                            ${badge}
                            ${item.dateLabel ? `<span>${escapeHtml(item.dateLabel)}</span>` : ''}
                        </div>
                        <h4>${escapeHtml(item.title || 'Publicación de Instagram')}</h4>
                        <p>${escapeHtml(item.caption || 'Sin texto de publicación.')}</p>
                        <div class="flacso-instagram-import-card__actions">
                            ${renderInstagramActions(item)}
                            ${item.permalink ? `<a class="button button-link" href="${escapeAttribute(item.permalink)}" target="_blank" rel="noopener noreferrer">Ver en Instagram</a>` : ''}
                        </div>
                    </div>
                </article>
            `;
        }).join('');
    }

    function importInstagramPost(button, importer) {
        const { ajaxUrl, nonce } = getInstagramAjaxConfig();
        const mediaId = button.dataset.mediaId || '';
        const isReimport = button.dataset.reimport === '1';
        const card = button.closest('[data-instagram-card]');

        if (!ajaxUrl || !nonce || !mediaId) {
            setInstagramNotice(importer, 'error', 'No se pudo importar: faltan datos de la publicación.');
            return;
        }

        button.disabled = true;
        button.textContent = isReimport ? 'Reimportando...' : 'Creando borrador...';
        if (card) card.classList.add('is-importing');

        const params = new URLSearchParams();
        params.append('action', 'flacso_instagram_import_post');
        params.append('nonce', nonce);
        params.append('media_id', mediaId);
        if (isReimport) {
            params.append('reimport', '1');
        }

        fetch(ajaxUrl, {
            method: 'POST',
            credentials: 'same-origin',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8' },
            body: params.toString(),
        })
            .then(res => res.json().catch(() => ({})))
            .then(json => {
                if (!json || !json.success) {
                    throw new Error((json && json.data && json.data.message) || 'No se pudo crear el borrador.');
                }

                const editUrl = json.data && json.data.editUrl;
                if (card && editUrl) {
                    card.classList.add('is-imported');
                    const badge = qs('.flacso-instagram-import-card__badge', card);
                    if (badge) {
                        badge.textContent = 'Importada';
                        badge.classList.add('is-imported');
                    }
                    const actions = qs('.flacso-instagram-import-card__actions', card);
                    if (actions) {
                        const externalLink = qs('.button-link', actions)?.outerHTML || '';
                        actions.innerHTML =
                            renderInstagramActions({ id: mediaId, imported: true, editUrl }) +
                            externalLink;
                    }
                }
                setInstagramNotice(importer, 'success', (json.data && json.data.message) || 'Borrador creado.');
            })
            .catch(error => {
                console.error('Error importando Instagram', error);
                button.disabled = false;
                button.textContent = isReimport ? 'Reimportar' : 'Crear borrador';
                setInstagramNotice(importer, 'error', error.message || 'No se pudo crear el borrador.');
            })
            .finally(() => {
                if (card) card.classList.remove('is-importing');
            });
    }

    function renderInstagramActions(item) {
        const mediaId = escapeAttribute(item.id || '');
        if (item.imported) {
            return `
                <button type="button" class="button button-primary" data-instagram-import data-reimport="1" data-media-id="${mediaId}">Reimportar</button>
                <a class="button button-secondary" href="${escapeAttribute(item.editUrl || '#')}">Editar post</a>
            `;
        }

        return `<button type="button" class="button button-primary" data-instagram-import data-media-id="${mediaId}">Crear borrador</button>`;
    }

    function setInstagramNotice(importer, type, message) {
        const notice = qs('[data-instagram-notice]', importer);
        if (!notice) return;

        notice.className = `flacso-instagram-importer__notice is-${type}`;
        notice.textContent = message || '';
        notice.hidden = !message;
    }

    function formatMediaType(type, childrenCount) {
        const value = String(type || '').toUpperCase();
        if (value === 'VIDEO') return 'Video/Reel';
        if (value === 'CAROUSEL_ALBUM') {
            const count = Number(childrenCount || 0);
            return count > 1 ? `Carrusel · ${count}` : 'Carrusel';
        }
        return 'Imagen';
    }

    function escapeHtml(value) {
        return String(value || '')
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;')
            .replace(/'/g, '&#039;');
    }

    function escapeAttribute(value) {
        return escapeHtml(value).replace(/`/g, '&#096;');
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
