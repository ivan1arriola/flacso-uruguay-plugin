(function () {
    'use strict';

    function onReady(callback) {
        if (document.readyState === 'loading') {
            document.addEventListener('DOMContentLoaded', callback, { once: true });
            return;
        }
        callback();
    }

    function initTooltips() {
        if (!window.bootstrap || typeof window.bootstrap.Tooltip !== 'function') {
            return;
        }

        document.querySelectorAll('[data-bs-toggle="tooltip"]').forEach(function (el) {
            new window.bootstrap.Tooltip(el);
        });
    }

    function initDocenteDirectory() {
        var grid = document.getElementById('grid-docentes');
        if (!grid) {
            return;
        }

        var items = Array.from(grid.querySelectorAll('.docente-item'));
        var searchInput = document.getElementById('buscador-docentes-archive');
        var liveRegion = document.getElementById('docentes-live-region');
        var emptyNode = null;

        function renderEmptyState() {
            if (emptyNode) {
                return;
            }

            emptyNode = document.createElement('div');
            emptyNode.className = 'dp-directory-empty';
            emptyNode.innerHTML = '' +
                '<div class="flacso-docentes-empty">' +
                '  <div class="flacso-docentes-empty__card" role="status" aria-live="polite">' +
                '    <i class="bi bi-search" aria-hidden="true"></i>' +
                '    <h2>No encontramos perfiles</h2>' +
                '    <p>Ajusta el texto de busqueda para ver resultados.</p>' +
                '  </div>' +
                '</div>';
            grid.appendChild(emptyNode);
        }

        function removeEmptyState() {
            if (emptyNode && emptyNode.parentNode) {
                emptyNode.parentNode.removeChild(emptyNode);
            }
            emptyNode = null;
        }

        function applyFilters() {
            var query = (searchInput ? searchInput.value : '').trim().toLowerCase();
            var visibleCount = 0;

            items.forEach(function (item) {
                var nombre = (item.getAttribute('data-nombre') || '').toLowerCase();
                var shouldShow = query === '' || nombre.indexOf(query) !== -1;

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
                liveRegion.textContent = visibleCount === 1
                    ? '1 perfil visible'
                    : visibleCount + ' perfiles visibles';
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

        var debouncedFilter = debounce(applyFilters, 150);

        if (searchInput) {
            searchInput.addEventListener('input', debouncedFilter);
            searchInput.addEventListener('search', applyFilters);
        }

        applyFilters();
    }

    onReady(function () {
        initTooltips();
        initDocenteDirectory();
    });
})();
