;(function () {
    'use strict';

    var flacsoData = window.flacsoMainPageData || {};
    var sectionOrderList = document.querySelector('[data-section-order-list]');

    if (sectionOrderList) {
        sectionOrderList.addEventListener('click', function (event) {
            var button = event.target.closest('[data-order-action]');
            if (!button) {
                return;
            }

            event.preventDefault();
            var action = button.dataset.orderAction;
            var item = button.closest('.flacso-section-order-item');
            if (!item) {
                return;
            }

            if (action === 'up') {
                var prev = item.previousElementSibling;
                if (prev) {
                    sectionOrderList.insertBefore(item, prev);
                }
            } else if (action === 'down') {
                var next = item.nextElementSibling;
                if (next) {
                    sectionOrderList.insertBefore(next, item);
                }
            }
        });
    }

    var adminMenu = document.querySelector('.flacso-novedades-admin');

    if (adminMenu) {
        var ajaxUrl = flacsoData.ajax_url || window.ajaxurl || '/wp-admin/admin-ajax.php';
        var nonce = adminMenu.dataset.nonce || '';
        var labels = flacsoData.labels || {};
        var stickLabel = labels.stick || 'Fijar';
        var unstickLabel = labels.unstick || 'Desfijar';
        var errorMessage = labels.error || 'No se pudo actualizar la noticia.';
        var orderError = labels.order_error || 'No se pudo guardar el orden.';
        var orderList = adminMenu.querySelector('[data-novedades-order-list]');

        function handleToggle(button) {
            var postId = parseInt(button.dataset.postId, 10);
            if (!postId) {
                return;
            }

            var nextSticky = button.dataset.sticky !== '1';
            button.disabled = true;

            var params = new URLSearchParams();
            params.append('action', 'flacso_section_novedades_toggle_sticky');
            params.append('post_id', postId);
            params.append('sticky', nextSticky ? '1' : '0');
            params.append('nonce', nonce);

            fetch(ajaxUrl, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8',
                },
                body: params.toString(),
            })
                .then(function (response) {
                    return response.json();
                })
                .then(function (data) {
                    if (data.success) {
                        var isSticky = Boolean(data.data && data.data.is_sticky);
                        button.dataset.sticky = isSticky ? '1' : '0';
                        button.textContent = isSticky ? unstickLabel : stickLabel;
                        button.setAttribute('aria-pressed', isSticky ? 'true' : 'false');
                        button.classList.toggle('is-sticky', isSticky);
                    } else {
                        window.alert(data.data || errorMessage);
                    }
                })
                .catch(function () {
                    window.alert(errorMessage);
                })
                .finally(function () {
                    button.disabled = false;
                });
        }

        function getCurrentOrder() {
            if (!orderList) {
                return [];
            }
            return Array.from(orderList.querySelectorAll('.flacso-novedades-admin-item'))
                .map(function (item) {
                    return parseInt(item.dataset.postId, 10);
                })
                .filter(Boolean);
        }

        function updateOrderBadges() {
            if (!orderList) {
                return;
            }
            Array.from(orderList.querySelectorAll('.flacso-novedades-admin-item')).forEach(function (item, index) {
                var badge = item.querySelector('.flacso-novedades-admin-order');
                if (badge) {
                    badge.textContent = index + 1;
                }
            });
        }

        function saveHighlightOrder() {
            var order = getCurrentOrder();
            if (!order.length) {
                return;
            }

            var params = new URLSearchParams();
            params.append('action', 'flacso_section_novedades_save_highlight_order');
            params.append('nonce', nonce);
            order.forEach(function (id) {
                params.append('order[]', id);
            });

            fetch(ajaxUrl, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8',
                },
                body: params.toString(),
            })
                .then(function (response) {
                    return response.json();
                })
                .then(function (data) {
                    if (!data.success) {
                        window.alert(orderError);
                    }
                })
                .catch(function () {
                    window.alert(orderError);
                });
        }

        adminMenu.addEventListener('click', function (event) {
            var orderButton = event.target.closest('.flacso-novedades-order-action');
            if (orderButton && orderList) {
                event.preventDefault();
                var item = orderButton.closest('.flacso-novedades-admin-item');
                if (!item) {
                    return;
                }
                var action = orderButton.dataset.orderAction;
                var sibling = action === 'up' ? item.previousElementSibling : item.nextElementSibling;
                if (!sibling) {
                    return;
                }
                if (action === 'up') {
                    orderList.insertBefore(item, sibling);
                } else {
                    orderList.insertBefore(sibling, item);
                }
                updateOrderBadges();
                saveHighlightOrder();
                return;
            }

            var button = event.target.closest('.flacso-novedades-pin-toggle');
            if (!button) {
                return;
            }
            event.preventDefault();
            handleToggle(button);
        });

        var searchInput = adminMenu.querySelector('[data-novedades-search-input]');
        var searchResults = adminMenu.querySelector('[data-novedades-search-results]');
        var searchTimeout = null;

        function renderSearchPlaceholder() {
            if (searchResults) {
                searchResults.innerHTML = '<p class="text-muted small">Escribe para buscar novedades.</p>';
            }
        }

        function performSearch(query) {
            if (!searchResults) {
                return;
            }

            var sanitized = query.trim();
            if (sanitized === '') {
                renderSearchPlaceholder();
                return;
            }

            searchResults.innerHTML = '<p class="text-muted small">Buscando...</p>';

            var params = new URLSearchParams();
            params.append('action', 'flacso_section_novedades_admin_search');
            params.append('search_term', sanitized);
            params.append('nonce', nonce);


            fetch(ajaxUrl, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8',
                },
                body: params.toString(),
            })
                .then(function (response) {
                    return response.json();
                })
                .then(function (data) {
                    if (data.success && data.data && data.data.html) {
                        searchResults.innerHTML = data.data.html;
                    } else {
                        searchResults.innerHTML = '<p class="text-muted small">No se encontraron novedades.</p>';
                    }
                })
                .catch(function () {
                searchResults.innerHTML = '<p class="text-danger small">Error de conexión.</p>';
                });
        }

        if (searchInput && searchResults) {
            renderSearchPlaceholder();
            searchInput.addEventListener('input', function () {
                clearTimeout(searchTimeout);
                searchTimeout = setTimeout(function () {
                    performSearch(searchInput.value);
                }, 350);
            });
        }
    }

    var colorPresetWrappers = document.querySelectorAll('.flacso-color-presets');
    colorPresetWrappers.forEach(function (wrapper) {
        wrapper.addEventListener('click', function (event) {
            var preset = event.target.closest('[data-color]');
            if (!preset) {
                return;
            }
            event.preventDefault();
            var hex = preset.dataset.color;
            if (!hex) {
                return;
            }
            var picker = preset.closest('.flacso-color-picker');
            if (!picker) {
                return;
            }
            var input = picker.querySelector('input[type="color"]');
            if (!input) {
                return;
            }
            input.value = hex;
            input.dispatchEvent(new Event('input', { bubbles: true }));
            input.dispatchEvent(new Event('change', { bubbles: true }));
        });
    });
}());
