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
        var catalogSection = root.querySelector('.flacso-oa-catalog__section--all');
        var seminarSection = root.querySelector('[data-offer-seminars-section]');
        var featuredItems = Array.prototype.slice.call(root.querySelectorAll('[data-offer-featured] [data-offer-item]'));
        var catalogItems = Array.prototype.slice.call(root.querySelectorAll('.flacso-oa-category [data-offer-item]'));
        var seminarItems = Array.prototype.slice.call(root.querySelectorAll('[data-seminar-item]'));
        var carousel = setupInfiniteCarousel(root.querySelector('[data-offer-carousel]'), featuredItems);
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
            var visibleSeminarItems = 0;

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

            seminarItems.forEach(function (item) {
                var visible = !term || normalize(item.getAttribute('data-search')).indexOf(term) !== -1;
                item.hidden = !visible;
                if (visible) {
                    visibleSeminarItems += 1;
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

            if (seminarSection) {
                seminarSection.hidden = seminarItems.length > 0 && visibleSeminarItems === 0;
            }

            if (catalogSection) {
                catalogSection.hidden = !!term && visibleCatalogItems === 0 && visibleSeminarItems > 0;
            }

            if (empty) {
                empty.hidden = visibleCatalogItems + visibleSeminarItems !== 0;
            }

            if (count) {
                if (term) {
                    var totalResults = visibleCatalogItems + visibleSeminarItems;
                    count.textContent = totalResults === 1
                        ? '1 resultado'
                        : totalResults + ' resultados';
                } else {
                    count.textContent = visibleCatalogItems === 1
                        ? '1 propuesta'
                        : visibleCatalogItems + ' propuestas';
                }
            }

            if (carousel) {
                carousel.rebuild();
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

    function setupInfiniteCarousel(carouselRoot, originalItems) {
        if (!carouselRoot) {
            return null;
        }

        var track = carouselRoot.querySelector('[data-offer-featured]');
        var previous = carouselRoot.querySelector('[data-carousel-previous]');
        var next = carouselRoot.querySelector('[data-carousel-next]');
        var controls = carouselRoot.querySelector('.flacso-oa-catalog__carousel-controls');
        var autoplayTimer = null;
        var resizeTimer = null;
        var loop = null;
        var paused = false;
        var reduceMotion = window.matchMedia('(prefers-reduced-motion: reduce)');

        if (!track) {
            return null;
        }

        function removeClones() {
            track.querySelectorAll('[data-carousel-clone]').forEach(function (clone) {
                clone.remove();
            });
        }

        function makeClone(item) {
            var clone = item.cloneNode(true);
            clone.removeAttribute('data-offer-item');
            clone.setAttribute('data-carousel-clone', '');
            clone.setAttribute('aria-hidden', 'true');
            clone.setAttribute('inert', '');
            clone.querySelectorAll('a, button, input, select, textarea, [tabindex]').forEach(function (element) {
                element.setAttribute('tabindex', '-1');
            });
            return clone;
        }

        function stopAutoplay() {
            if (autoplayTimer) {
                window.clearInterval(autoplayTimer);
                autoplayTimer = null;
            }
        }

        function startAutoplay() {
            stopAutoplay();
            if (!loop || paused || reduceMotion.matches || document.hidden) {
                return;
            }
            autoplayTimer = window.setInterval(function () {
                move(1);
            }, 4500);
        }

        function move(direction) {
            if (!loop) {
                return;
            }
            track.scrollBy({ left: direction * loop.step, behavior: 'smooth' });
        }

        function rebuild() {
            stopAutoplay();
            loop = null;
            removeClones();

            var visibleItems = originalItems.filter(function (item) { return !item.hidden; });
            var canLoop = visibleItems.length > 1;
            carouselRoot.classList.toggle('is-looping', canLoop);
            if (controls) {
                controls.hidden = !canLoop;
            }

            if (!canLoop) {
                track.scrollLeft = 0;
                return;
            }

            var leading = visibleItems.map(makeClone);
            var trailing = visibleItems.map(makeClone);
            leading.reverse().forEach(function (clone) {
                track.insertBefore(clone, track.firstChild);
            });
            trailing.forEach(function (clone) {
                track.appendChild(clone);
            });

            window.requestAnimationFrame(function () {
                var trackPadding = parseFloat(window.getComputedStyle(track).paddingLeft) || 0;
                var originalStart = visibleItems[0].offsetLeft - trackPadding;
                var trailingStart = trailing[0].offsetLeft - trackPadding;
                var step = visibleItems.length > 1
                    ? visibleItems[1].offsetLeft - visibleItems[0].offsetLeft
                    : visibleItems[0].getBoundingClientRect().width;

                loop = {
                    originalStart: originalStart,
                    setWidth: trailingStart - originalStart,
                    step: step
                };
                track.scrollTo({ left: originalStart, behavior: 'auto' });
                startAutoplay();
            });
        }

        track.addEventListener('scroll', function () {
            if (!loop) {
                return;
            }
            if (track.scrollLeft >= loop.originalStart + loop.setWidth - (loop.step / 2)) {
                track.scrollLeft -= loop.setWidth;
            } else if (track.scrollLeft < loop.originalStart - (loop.step / 2)) {
                track.scrollLeft += loop.setWidth;
            }
        }, { passive: true });

        if (previous) {
            previous.addEventListener('click', function () {
                move(-1);
                startAutoplay();
            });
        }
        if (next) {
            next.addEventListener('click', function () {
                move(1);
                startAutoplay();
            });
        }

        carouselRoot.addEventListener('mouseenter', function () {
            paused = true;
            stopAutoplay();
        });
        carouselRoot.addEventListener('mouseleave', function () {
            paused = false;
            startAutoplay();
        });
        carouselRoot.addEventListener('focusin', function () {
            paused = true;
            stopAutoplay();
        });
        carouselRoot.addEventListener('focusout', function (event) {
            if (!carouselRoot.contains(event.relatedTarget)) {
                paused = false;
                startAutoplay();
            }
        });
        document.addEventListener('visibilitychange', startAutoplay);
        if (typeof reduceMotion.addEventListener === 'function') {
            reduceMotion.addEventListener('change', startAutoplay);
        }
        window.addEventListener('resize', function () {
            window.clearTimeout(resizeTimer);
            resizeTimer = window.setTimeout(rebuild, 150);
        });

        return { rebuild: rebuild };
    }

    document.addEventListener('DOMContentLoaded', function () {
        document.querySelectorAll('[data-flacso-offer-catalog]').forEach(setupCatalog);
    });
})();
