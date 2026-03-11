;(function (window, document) {
    'use strict';

    var wpElement = window.wp && window.wp.element;
    if (!wpElement) {
        return;
    }

    var createElement = wpElement.createElement;
    var useMemo = wpElement.useMemo;
    var useState = wpElement.useState;
    var createRoot = wpElement.createRoot;
    var render = wpElement.render;

    function normalizeText(value) {
        var text = String(value || '').toLowerCase();
        try {
            text = text.normalize('NFD').replace(/[\u0300-\u036f]/g, '');
        } catch (error) {
            // Fallback for environments without normalize support.
        }
        return text.replace(/[^a-z0-9\s]/g, '').replace(/\s+/g, ' ').trim();
    }

    function getScore(item, query) {
        if (!query) {
            return 1;
        }

        var titleNorm = item.normalized || normalizeText(item.title);
        if (!titleNorm) {
            return 0;
        }

        if (titleNorm === query) {
            return 100;
        }

        if (titleNorm.indexOf(query) === 0) {
            return 80;
        }

        if (titleNorm.indexOf(query) !== -1) {
            return 60;
        }

        var words = query.split(' ');
        var matchedWords = words.filter(function (word) {
            return word && titleNorm.indexOf(word) !== -1;
        }).length;

        if (!matchedWords) {
            return 0;
        }

        return Math.floor((matchedWords / words.length) * 40);
    }

    function ConveniosReactApp(props) {
        var appId = props && props.app_id ? String(props.app_id) : 'flacso-convenios-react';
        var items = props && Array.isArray(props.items) ? props.items : [];
        var title = props && props.title ? String(props.title) : 'Convenios';
        var placeholder = props && props.placeholder ? String(props.placeholder) : '';
        var searchPlaceholder = props && props.search_placeholder ? String(props.search_placeholder) : 'Buscar convenio...';
        var searchLabel = props && props.search_label ? String(props.search_label) : 'Buscar convenio';
        var noResults = props && props.no_results ? String(props.no_results) : 'No se encontraron resultados';
        var noResultsHint = props && props.no_results_hint ? String(props.no_results_hint) : '';
        var countLabel = props && props.count_label ? String(props.count_label) : 'resultados encontrados';
        var clearLabel = props && props.clear_label ? String(props.clear_label) : 'Limpiar busqueda';
        var inputId = appId + '-input';
        var _useState = useState('');
        var query = _useState[0];
        var setQuery = _useState[1];
        var normalizedQuery = normalizeText(query);

        var filtered = useMemo(function () {
            if (!normalizedQuery) {
                return items.slice();
            }

            return items
                .map(function (item) {
                    return {
                        item: item,
                        score: getScore(item, normalizedQuery),
                    };
                })
                .filter(function (entry) {
                    return entry.score > 0;
                })
                .sort(function (a, b) {
                    if (a.score !== b.score) {
                        return b.score - a.score;
                    }
                    return String(a.item.title || '').localeCompare(String(b.item.title || ''), 'es');
                })
                .map(function (entry) {
                    return entry.item;
                });
        }, [items, normalizedQuery]);

        return createElement(
            'section',
            { className: 'flacso-convenios-react' },
            createElement(
                'div',
                { className: 'flacso-content-shell' },
                createElement('h2', { className: 'flacso-convenios-react__title' }, title),
                createElement(
                    'div',
                    { className: 'flacso-convenios-react__searchWrap' },
                    createElement(
                        'label',
                        { className: 'flacso-convenios-react__searchLabel', htmlFor: inputId },
                        searchLabel
                    ),
                    createElement(
                        'div',
                        { className: 'flacso-convenios-react__search' },
                        createElement(
                            'span',
                            { className: 'flacso-convenios-react__searchIcon', 'aria-hidden': 'true' },
                            createElement(
                                'svg',
                                {
                                    viewBox: '0 0 24 24',
                                    width: '18',
                                    height: '18',
                                    fill: 'none',
                                    xmlns: 'http://www.w3.org/2000/svg',
                                },
                                createElement('circle', {
                                    cx: '11',
                                    cy: '11',
                                    r: '7',
                                    stroke: 'currentColor',
                                    strokeWidth: '2',
                                }),
                                createElement('path', {
                                    d: 'M20 20L16.65 16.65',
                                    stroke: 'currentColor',
                                    strokeWidth: '2',
                                    strokeLinecap: 'round',
                                })
                            )
                        ),
                        createElement('input', {
                            id: inputId,
                            className: 'flacso-convenios-react__input',
                            type: 'text',
                            value: query,
                            placeholder: searchPlaceholder,
                            autoComplete: 'off',
                            onChange: function (event) {
                                setQuery(event.target.value);
                            },
                        }),
                        query
                            ? createElement(
                                  'button',
                                  {
                                      type: 'button',
                                      className: 'flacso-convenios-react__clear',
                                      onClick: function () {
                                          setQuery('');
                                      },
                                      'aria-label': clearLabel,
                                  },
                                  '\u2715'
                              )
                            : null
                    ),
                    createElement(
                        'p',
                        { className: 'flacso-convenios-react__counter', 'aria-live': 'polite' },
                        String(filtered.length) + ' ' + countLabel
                    )
                ),
                filtered.length
                    ? createElement(
                          'div',
                          { className: 'flacso-convenios-react__grid' },
                          filtered.map(function (item, index) {
                              var itemId = item && item.id ? String(item.id) : String(index);
                              return createElement(
                                  'a',
                                  {
                                      key: itemId,
                                      className: 'flacso-convenios-react__card',
                                      href: item.permalink || '#',
                                  },
                                  createElement(
                                      'div',
                                      { className: 'flacso-convenios-react__logoFrame' },
                                      createElement(
                                          'div',
                                          { className: 'flacso-convenios-react__logoSquare' },
                                          createElement('img', {
                                              src: item.image || placeholder,
                                              alt: item.title ? 'Logo de ' + item.title : 'Logo de convenio',
                                              loading: 'lazy',
                                              onError: function (event) {
                                                  if (placeholder) {
                                                      event.currentTarget.src = placeholder;
                                                  }
                                              },
                                          })
                                      )
                                  ),
                                  createElement('h3', { className: 'flacso-convenios-react__cardTitle' }, item.title || '')
                              );
                          })
                      )
                    : createElement(
                          'div',
                          { className: 'flacso-convenios-react__empty', role: 'status', 'aria-live': 'polite' },
                          createElement('p', { className: 'flacso-convenios-react__emptyTitle' }, noResults),
                          noResultsHint ? createElement('p', { className: 'flacso-convenios-react__emptyHint' }, noResultsHint) : null
                      )
            )
        );
    }

    function mountConveniosAt(container) {
        if (!container) {
            return;
        }

        var appId = container.getAttribute('data-convenios-app');
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

        var appElement = createElement(ConveniosReactApp, payload || {});

        if (typeof createRoot === 'function') {
            createRoot(container).render(appElement);
            return;
        }

        if (typeof render === 'function') {
            render(appElement, container);
        }
    }

    var roots = document.querySelectorAll('.flacso-convenios-react-root');
    if (!roots.length) {
        return;
    }

    roots.forEach(mountConveniosAt);
})(window, document);
