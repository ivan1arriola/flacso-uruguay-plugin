(function () {
    'use strict';

    function onReady(callback) {
        if (document.readyState === 'loading') {
            document.addEventListener('DOMContentLoaded', callback, { once: true });
        } else {
            callback();
        }
    }

    function initTooltips() {
        if (!window.bootstrap || typeof window.bootstrap.Tooltip !== 'function') {
            return;
        }
        document.querySelectorAll('[data-bs-toggle="tooltip"]').forEach(function (el) {
            return new window.bootstrap.Tooltip(el);
        });
    }

    function initDocenteDirectory() {
        var grid = document.getElementById('grid-docentes');
        if (!grid) {
            return;
        }

        var items = Array.from(grid.querySelectorAll('.docente-item'));
        var searchInput = document.getElementById('buscador-docentes-archive');
        var equipoSelect = document.getElementById('filtro-equipo');
        var posgradoSelect = document.getElementById('filtro-posgrado');
        var liveRegion = document.getElementById('docentes-live-region');
        var emptyNode = null;

        function renderEmptyState() {
            if (emptyNode) {
                return;
            }
            emptyNode = document.createElement('div');
            emptyNode.className = 'col-12 dp-directory-empty';
            emptyNode.innerHTML = '\n                <div class="card border-0 shadow-sm text-center p-5" role="status" aria-live="polite">\n                    <i class="bi bi-search dp-directory-empty__icon mb-3" aria-hidden="true"></i>\n                    <h4 class="text-dark mb-2">No encontramos perfiles</h4>\n                    <p class="text-muted mb-0">Actualiza los filtros o el texto de busqueda para ver resultados.</p>\n                </div>\n            ';
            grid.appendChild(emptyNode);
        }

        function removeEmptyState() {
            if (emptyNode && emptyNode.parentNode) {
                emptyNode.parentNode.removeChild(emptyNode);
            }
            emptyNode = null;
        }

        function tokens(raw) {
            return (raw || '').split(' ').map(function (token) { return token.trim(); }).filter(Boolean);
        }

        function applyFilters() {
            var query = (searchInput ? searchInput.value : '').trim().toLowerCase();
            var equipo = equipoSelect ? equipoSelect.value : '';
            var posgrado = posgradoSelect ? posgradoSelect.value : '';
            var visibleCount = 0;

            items.forEach(function (item) {
                var nombre = (item.getAttribute('data-nombre') || '').toLowerCase();
                var equipos = tokens(item.getAttribute('data-equipos'));
                var pages = tokens(item.getAttribute('data-pages'));

                var matchesText = query === '' || nombre.includes(query);
                var matchesEquipo = equipo === '' || equipos.includes(equipo);
                var matchesPosgrado = posgrado === '' || pages.includes(posgrado);
                var shouldShow = matchesText && matchesEquipo && matchesPosgrado;

                item.hidden = !shouldShow;
                item.classList.toggle('is-hidden', !shouldShow);
                if (shouldShow) {
                    visibleCount += 1;
                }
            });

            if (visibleCount === 0 && items.length) {
                renderEmptyState();
            } else {
                removeEmptyState();
            }

            if (liveRegion) {
                liveRegion.textContent = visibleCount === 1 ? '1 integrante visible' : visibleCount + ' integrantes visibles';
            }
        }

        function debounce(fn, delay) {
            var timer = null;
            return function () {
                var args = arguments;
                clearTimeout(timer);
                timer = setTimeout(function () {
                    fn.apply(null, args);
                }, delay);
            };
        }

        var debouncedFilter = debounce(applyFilters, 200);

        if (searchInput) {
            searchInput.addEventListener('input', debouncedFilter);
            searchInput.addEventListener('search', applyFilters);
        }
        if (equipoSelect) {
            equipoSelect.addEventListener('change', applyFilters);
        }
        if (posgradoSelect) {
            posgradoSelect.addEventListener('change', applyFilters);
        }
        applyFilters();
    }

    function initProgramSearch() {
        var searchInput = document.getElementById('posgrado-search');
        if (!searchInput) {
            return;
        }
        var cards = Array.from(document.querySelectorAll('.dp-program-card'));
        searchInput.addEventListener('input', function () {
            var query = searchInput.value.trim().toLowerCase();
            cards.forEach(function (card) {
                var title = (card.getAttribute('data-title') || '').toLowerCase();
                card.hidden = query !== '' && !title.includes(query);
            });
        });
    }

    onReady(function () {
        initTooltips();
        initDocenteDirectory();
        initProgramSearch();
    });
})();

