(function () {
    'use strict';

    function normalize(value) {
        return String(value || '')
            .normalize('NFD')
            .replace(/[\u0300-\u036f]/g, '')
            .toLowerCase()
            .trim();
    }

    function setupCatalog(root) {
        var search = root.querySelector('[data-offer-search]');
        var count = root.querySelector('[data-offer-search-count]');
        var empty = root.querySelector('[data-offer-empty]');
        var chips = Array.prototype.slice.call(root.querySelectorAll('[data-offer-category]'));
        var panels = Array.prototype.slice.call(root.querySelectorAll('[data-offer-panel]'));
        var featuredSection = root.querySelector('.flacso-oa-catalog__section--open');
        var featuredItems = Array.prototype.slice.call(root.querySelectorAll('[data-offer-featured] [data-offer-item]'));
        var catalogItems = Array.prototype.slice.call(root.querySelectorAll('.flacso-oa-category [data-offer-item]'));
        var activeCategory = 'all';

        function itemMatches(item, term) {
            var categoryMatches = activeCategory === 'all' || item.getAttribute('data-category') === activeCategory;
            var textMatches = !term || normalize(item.getAttribute('data-search')).indexOf(term) !== -1;
            return categoryMatches && textMatches;
        }

        function update() {
            var term = normalize(search ? search.value : '');
            var visibleCatalogItems = 0;
            var visibleFeaturedItems = 0;

            catalogItems.forEach(function (item) {
                var visible = itemMatches(item, term);
                item.hidden = !visible;
                if (visible) {
                    visibleCatalogItems += 1;
                }
            });

            featuredItems.forEach(function (item) {
                var visible = itemMatches(item, term);
                item.hidden = !visible;
                if (visible) {
                    visibleFeaturedItems += 1;
                }
            });

            panels.forEach(function (panel) {
                var categoryMatches = activeCategory === 'all' || panel.getAttribute('data-offer-panel') === activeCategory;
                var hasVisibleItems = Array.prototype.some.call(
                    panel.querySelectorAll('[data-offer-item]'),
                    function (item) { return !item.hidden; }
                );

                panel.hidden = !categoryMatches || !hasVisibleItems;
                if (!panel.hidden && (term || activeCategory !== 'all')) {
                    panel.open = true;
                }
            });

            if (featuredSection) {
                featuredSection.hidden = visibleFeaturedItems === 0;
            }

            if (empty) {
                empty.hidden = visibleCatalogItems !== 0;
            }

            if (count) {
                count.textContent = visibleCatalogItems === 1
                    ? '1 propuesta'
                    : visibleCatalogItems + ' propuestas';
            }
        }

        chips.forEach(function (chip) {
            chip.addEventListener('click', function () {
                activeCategory = chip.getAttribute('data-offer-category') || 'all';
                chips.forEach(function (candidate) {
                    var selected = candidate === chip;
                    candidate.classList.toggle('is-active', selected);
                    candidate.setAttribute('aria-pressed', selected ? 'true' : 'false');
                });
                update();

                if (window.matchMedia('(max-width: 767px)').matches) {
                    var target = root.querySelector('#toda-la-oferta');
                    if (target) {
                        target.scrollIntoView({ behavior: 'smooth', block: 'start' });
                    }
                }
            });
        });

        if (search) {
            search.addEventListener('input', update);
        }

        function syncDesktopPanels() {
            if (window.matchMedia('(min-width: 1050px)').matches) {
                panels.forEach(function (panel) {
                    panel.open = true;
                });
            }
        }

        window.addEventListener('resize', syncDesktopPanels);
        syncDesktopPanels();
        update();
    }

    document.addEventListener('DOMContentLoaded', function () {
        document.querySelectorAll('[data-flacso-offer-catalog]').forEach(setupCatalog);
    });
})();
