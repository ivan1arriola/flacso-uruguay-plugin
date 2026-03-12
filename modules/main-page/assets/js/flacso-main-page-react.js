;(function (window, document) {
    'use strict';

    var wpElement = window.wp && window.wp.element;
    if (!wpElement) {
        return;
    }

    var createElement = wpElement.createElement;
    var useEffect = wpElement.useEffect;
    var useRef = wpElement.useRef;
    var useState = wpElement.useState;
    var createRoot = wpElement.createRoot;
    var render = wpElement.render;

    function sanitizeClassSuffix(value) {
        return String(value || '')
            .toLowerCase()
            .replace(/[^a-z0-9_-]/g, '-')
            .replace(/-{2,}/g, '-')
            .replace(/^-+|-+$/g, '');
    }

    function executeInlineScripts(container) {
        if (!container) {
            return;
        }

        var scripts = container.querySelectorAll('script');
        scripts.forEach(function (oldScript) {
            var typeAttr = String(oldScript.getAttribute('type') || '').toLowerCase().trim();
            var isJsType =
                !typeAttr ||
                typeAttr === 'text/javascript' ||
                typeAttr === 'application/javascript' ||
                typeAttr === 'module' ||
                typeAttr === 'text/ecmascript' ||
                typeAttr === 'application/ecmascript';

            if (!isJsType) {
                return;
            }

            var newScript = document.createElement('script');

            Array.prototype.slice.call(oldScript.attributes || []).forEach(function (attr) {
                newScript.setAttribute(attr.name, attr.value);
            });

            if (oldScript.textContent) {
                newScript.textContent = oldScript.textContent;
            }

            if (!oldScript.parentNode) {
                return;
            }

            try {
                oldScript.parentNode.replaceChild(newScript, oldScript);
            } catch (error) {
                if (window.console && typeof window.console.error === 'function') {
                    window.console.error('FLACSO inline script skipped:', error);
                }
            }
        });
    }

    function RawHtmlSection(props) {
        var hostRef = useRef(null);
        var html = typeof props.html === 'string' ? props.html : '';

        useEffect(function () {
            if (!hostRef.current) {
                return;
            }

            hostRef.current.innerHTML = html;
            executeInlineScripts(hostRef.current);
        }, [html]);

        return createElement('div', { ref: hostRef });
    }

    function normalizeIndex(value, length) {
        if (!length) {
            return 0;
        }
        return ((value % length) + length) % length;
    }

    function EventosProximosSection(props) {
        var sectionId = props && props.sectionId ? String(props.sectionId) : 'flacso-eventos-react';
        var title = props && props.title ? String(props.title) : 'Proximos eventos';
        var items = props && props.items && Array.isArray(props.items) ? props.items : [];
        var count = items.length;
        var titleId = sectionId + '-title';
        var listId = sectionId + '-list';
        var autoRotateRef = useRef(null);
        var _useState = useState(0);
        var active = _useState[0];
        var setActive = _useState[1];

        useEffect(function () {
            if (!count) {
                return;
            }
            setActive(function (current) {
                return normalizeIndex(current, count);
            });
        }, [count]);

        function stopAutoplay() {
            if (autoRotateRef.current) {
                window.clearInterval(autoRotateRef.current);
                autoRotateRef.current = null;
            }
        }

        function startAutoplay() {
            stopAutoplay();
            if (count <= 1) {
                return;
            }
            autoRotateRef.current = window.setInterval(function () {
                setActive(function (current) {
                    return normalizeIndex(current + 1, count);
                });
            }, 6000);
        }

        useEffect(function () {
            startAutoplay();
            return function () {
                stopAutoplay();
            };
        }, [count]);

        function move(step) {
            if (!count) {
                return;
            }
            setActive(function (current) {
                return normalizeIndex(current + step, count);
            });
        }

        function goTo(index) {
            if (!count) {
                return;
            }
            setActive(normalizeIndex(index, count));
        }

        if (!count) {
            return null;
        }

        var currentIndex = normalizeIndex(active, count);
        var activeItem = items[currentIndex] || items[0];
        var activeClass = sanitizeClassSuffix(activeItem && activeItem['class'] ? activeItem['class'] : '');
        var chips = [];
        if (activeItem.status) {
            chips.push(createElement('span', { key: 'status', className: 'flc-eventos-react__chip flc-eventos-react__chip--status' }, activeItem.status));
        }
        if (activeItem.hora) {
            chips.push(createElement('span', { key: 'hora', className: 'flc-eventos-react__chip flc-eventos-react__chip--time' }, activeItem.hora));
        }
        if (activeItem.duration) {
            chips.push(createElement('span', { key: 'duration', className: 'flc-eventos-react__chip flc-eventos-react__chip--duration' }, activeItem.duration));
        }
        var layoutClass = 'flc-eventos-react__layout ' + (count > 1 ? 'has-list' : 'no-list');

        return createElement(
            'section',
            {
                className: 'flc-eventos-react',
                'aria-labelledby': titleId,
                onMouseEnter: stopAutoplay,
                onMouseLeave: startAutoplay,
            },
            createElement(
                'div',
                { className: 'flacso-content-shell' },
                createElement(
                    'header',
                    { className: 'flacso-home-block__header flc-eventos-react__header' },
                    createElement('h2', { id: titleId, className: 'flc-eventos-react__title' }, title),
                    count > 1
                        ? createElement(
                              'div',
                              { className: 'flc-eventos-react__controls', role: 'group', 'aria-label': 'Navegacion de eventos' },
                              createElement(
                                  'button',
                                  {
                                      type: 'button',
                                      className: 'flc-eventos-react__arrow',
                                      onClick: function () {
                                          move(-1);
                                      },
                                      'aria-label': 'Evento anterior',
                                  },
                                  '\u2039'
                              ),
                              createElement('span', { className: 'flc-eventos-react__counter', 'aria-live': 'polite' }, String(currentIndex + 1) + ' / ' + String(count)),
                              createElement(
                                  'button',
                                  {
                                      type: 'button',
                                      className: 'flc-eventos-react__arrow',
                                      onClick: function () {
                                          move(1);
                                      },
                                      'aria-label': 'Evento siguiente',
                                  },
                                  '\u203a'
                              )
                          )
                        : null
                ),
                createElement(
                    'div',
                    { className: layoutClass },
                    createElement(
                        'a',
                        {
                            className: 'flc-eventos-react__featured ' + (activeClass ? 'is-' + activeClass : ''),
                            href: activeItem.link || '#',
                            onFocus: stopAutoplay,
                            onBlur: startAutoplay,
                        },
                        createElement(
                            'div',
                            { className: 'flc-eventos-react__media' },
                            createElement('img', {
                                src: activeItem.thumbnail || '',
                                alt: activeItem.title || 'Evento',
                                loading: 'lazy',
                            })
                        ),
                        createElement(
                            'div',
                            { className: 'flc-eventos-react__content' },
                            createElement(
                                'div',
                                { className: 'flc-eventos-react__date-badge', 'aria-hidden': 'true' },
                                createElement('span', { className: 'weekday' }, activeItem.weekday || ''),
                                createElement('span', { className: 'day' }, activeItem.day || ''),
                                createElement('span', { className: 'month' }, activeItem.month || '')
                            ),
                            chips.length ? createElement('div', { className: 'flc-eventos-react__chips' }, chips) : null,
                            createElement('h3', { className: 'flc-eventos-react__event-title' }, activeItem.title || ''),
                            activeItem.excerpt ? createElement('p', { className: 'flc-eventos-react__excerpt' }, activeItem.excerpt) : null
                        )
                    ),
                    count > 1
                        ? createElement(
                              'aside',
                              { className: 'flc-eventos-react__list-wrap', 'aria-label': 'Lista de eventos proximos' },
                              createElement(
                                  'ul',
                                  { id: listId, className: 'flc-eventos-react__list' },
                                  items.map(function (item, index) {
                                      var itemClass = sanitizeClassSuffix(item && item['class'] ? item['class'] : '');
                                      var isActive = index === currentIndex;
                                      return createElement(
                                          'li',
                                          { key: String(item.id || index), className: 'flc-eventos-react__list-item' },
                                          createElement(
                                              'button',
                                              {
                                                  type: 'button',
                                                  className: 'flc-eventos-react__event-btn ' + (isActive ? 'is-active ' : '') + (itemClass ? 'is-' + itemClass : ''),
                                                  onClick: function () {
                                                      goTo(index);
                                                  },
                                                  onFocus: stopAutoplay,
                                                  onBlur: startAutoplay,
                                                  'aria-pressed': isActive ? 'true' : 'false',
                                                  'aria-label': item && item.title ? item.title : 'Evento',
                                              },
                                              createElement(
                                                  'span',
                                                  { className: 'flc-eventos-react__event-btn-date', 'aria-hidden': 'true' },
                                                  createElement('strong', null, item.day || ''),
                                                  createElement('small', null, item.month || '')
                                              ),
                                              createElement(
                                                  'span',
                                                  { className: 'flc-eventos-react__event-btn-copy' },
                                                  createElement('span', { className: 'flc-eventos-react__event-btn-title' }, item.title || ''),
                                                  item.status
                                                      ? createElement('span', { className: 'flc-eventos-react__event-btn-status' }, item.status)
                                                      : null
                                              )
                                          )
                                      );
                                  })
                              )
                          )
                        : null
                )
            )
        );
    }

    function HomePageApp(props) {
        var sections = Array.isArray(props.sections) ? props.sections : [];
        var mainId = typeof props.main_id === 'string' && props.main_id ? props.main_id : 'flacso-home';

        return createElement(
            'div',
            { className: 'flacso-main-page flacso-homepage-completa' },
            createElement(
                'main',
                {
                    className: 'flacso-home-layout',
                    role: 'main',
                    id: mainId,
                },
                sections.map(function (section, index) {
                    var key = sanitizeClassSuffix(section && section.key ? section.key : '') || ('section-' + index);
                    var label = section && section.label ? String(section.label) : '';
                    var sectionContent = section && section.content ? String(section.content) : '';
                    var component = section && section.component ? String(section.component) : '';
                    var sectionData = section && section.data && typeof section.data === 'object' ? section.data : null;
                    var bleedKeys = ['hero'];
                    var isBleedSurface = bleedKeys.indexOf(key) !== -1;
                    var surfaceClass = 'flacso-home-block__surface ' + (isBleedSurface ? 'flacso-home-block__surface--bleed' : 'flacso-home-block__surface--card') + ' flacso-home-block__surface--' + key;
                    var body = null;

                    if (component === 'eventos-proximos' && sectionData && Array.isArray(sectionData.items) && sectionData.items.length) {
                        body = createElement(EventosProximosSection, {
                            sectionId: 'flacso-eventos-react-' + key + '-' + index,
                            title: 'Proximos eventos',
                            items: sectionData.items,
                        });
                    }

                    if (!body) {
                        body = createElement(RawHtmlSection, { html: sectionContent });
                    }

                    return createElement(
                        'article',
                        {
                            key: key + '-' + index,
                            className: 'flacso-home-block flacso-home-block--' + key,
                            'data-section-key': key,
                            'data-section-label': label,
                        },
                        createElement('div', { className: surfaceClass }, body)
                    );
                })
            )
        );
    }

    function mountAt(container) {
        if (!container) {
            return;
        }

        var appId = container.getAttribute('data-flacso-app');
        if (!appId) {
            return;
        }

        var payloadNode = document.getElementById(appId + '-data');
        if (!payloadNode) {
            return;
        }

        var payload = null;
        try {
            payload = JSON.parse(payloadNode.textContent || '{}');
        } catch (error) {
            return;
        }

        var appElement = createElement(HomePageApp, payload || {});

        if (typeof createRoot === 'function') {
            createRoot(container).render(appElement);
            return;
        }

        if (typeof render === 'function') {
            render(appElement, container);
        }
    }

    var roots = document.querySelectorAll('.flacso-main-page-react-root');
    if (!roots.length) {
        return;
    }

    roots.forEach(mountAt);
})(window, document);
