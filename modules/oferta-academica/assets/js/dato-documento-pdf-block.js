(function (blocks, element, components, blockEditor, serverSideRender, data, i18n, apiFetch) {
    if (!blocks || !serverSideRender || !data || !apiFetch) {
        return;
    }

    var registerBlockType = blocks.registerBlockType;
    var el = element.createElement;
    var Fragment = element.Fragment;
    var useState = element.useState;
    var useEffect = element.useEffect;
    var InspectorControls = (blockEditor || wp.blockEditor).InspectorControls;
    var PanelBody = components.PanelBody;
    var SelectControl = components.SelectControl;
    var TextControl = components.TextControl;
    var Button = components.Button;
    var Notice = components.Notice;
    var Spinner = components.Spinner;
    var ServerSideRender = serverSideRender;
    var useSelect = data.useSelect;
    var __ = (i18n || wp.i18n).__;

    function getOfertaOptions(ofertas) {
        var options = [{ label: __('Selecciona una oferta...', 'flacso-oferta-academica'), value: 0 }];

        if (Array.isArray(ofertas)) {
            ofertas.forEach(function (post) {
                var label = post.title && post.title.rendered ? post.title.rendered : ('#' + post.id);
                options.push({ label: label, value: post.id });
            });
        }

        return options;
    }

    function DocumentoPdfBlockEdit(props, config) {
        var attrs = props.attributes || {};
        var setAttributes = props.setAttributes;
        var ofertaId = parseInt(attrs.ofertaId || 0, 10);
        var currentUrl = (attrs.pdfUrlFallback || '').trim();

        var _useState = useState('');
        var remoteUrl = _useState[0];
        var setRemoteUrl = _useState[1];

        var _useState2 = useState(false);
        var isLoading = _useState2[0];
        var setIsLoading = _useState2[1];

        var _useState3 = useState('');
        var fetchError = _useState3[0];
        var setFetchError = _useState3[1];

        var _useState4 = useState(false);
        var isSaving = _useState4[0];
        var setIsSaving = _useState4[1];

        var _useState5 = useState('');
        var saveMessage = _useState5[0];
        var setSaveMessage = _useState5[1];

        var _useState6 = useState('');
        var saveError = _useState6[0];
        var setSaveError = _useState6[1];

        var ofertas = useSelect(function (select) {
            return select('core').getEntityRecords('postType', 'oferta-academica', {
                per_page: 100,
                orderby: 'title',
                order: 'asc',
                status: 'publish'
            });
        }, []);

        useEffect(function () {
            if (!ofertaId) {
                setRemoteUrl('');
                setFetchError('');
                return;
            }

            setIsLoading(true);
            setFetchError('');
            setRemoteUrl('');

            apiFetch({ path: '/flacso/v1/oferta-academica/' + ofertaId })
                .then(function (response) {
                    var value = response && response[config.metaKey] ? String(response[config.metaKey]).trim() : '';
                    setRemoteUrl(value);

                    // Si el bloque todavia no tiene fallback, usar el valor actual del CPT.
                    if (!currentUrl && value) {
                        setAttributes({ pdfUrlFallback: value });
                    }
                })
                .catch(function (error) {
                    setFetchError((error && error.message) ? error.message : __('No se pudo leer el dato del CPT.', 'flacso-oferta-academica'));
                })
                .finally(function () {
                    setIsLoading(false);
                });
        }, [ofertaId, config.metaKey]);

        function onSaveToCpt() {
            if (!ofertaId) {
                setSaveError(__('Selecciona una oferta academica.', 'flacso-oferta-academica'));
                setSaveMessage('');
                return;
            }

            var value = (attrs.pdfUrlFallback || '').trim();
            if (!value) {
                setSaveError(__('Ingresa una URL PDF antes de guardar.', 'flacso-oferta-academica'));
                setSaveMessage('');
                return;
            }

            setIsSaving(true);
            setSaveError('');
            setSaveMessage('');

            var payload = {};
            payload[config.metaKey] = value;

            apiFetch({
                path: '/flacso/v1/oferta-academica/' + ofertaId,
                method: 'PUT',
                data: payload
            }).then(function (response) {
                var saved = response && response[config.metaKey] ? String(response[config.metaKey]).trim() : value;
                setRemoteUrl(saved);
                setAttributes({ pdfUrlFallback: saved });
                setSaveMessage(__('URL guardada en la oferta academica.', 'flacso-oferta-academica'));
            }).catch(function (error) {
                setSaveError((error && error.message) ? error.message : __('No se pudo guardar en el CPT.', 'flacso-oferta-academica'));
            }).finally(function () {
                setIsSaving(false);
            });
        }

        var options = getOfertaOptions(ofertas);
        var infoLines = [];
        infoLines.push(
            el(SelectControl, {
                label: __('Selecciona la oferta', 'flacso-oferta-academica'),
                value: ofertaId || 0,
                options: options,
                onChange: function (value) {
                    setAttributes({ ofertaId: parseInt(value || 0, 10), pdfUrlFallback: '' });
                    setSaveMessage('');
                    setSaveError('');
                }
            })
        );

        infoLines.push(
            el(TextControl, {
                label: __('URL del PDF', 'flacso-oferta-academica'),
                help: __('Si el campo del CPT esta vacio, este valor se usa como fallback.', 'flacso-oferta-academica'),
                value: attrs.pdfUrlFallback || '',
                placeholder: 'https://...',
                onChange: function (value) {
                    setAttributes({ pdfUrlFallback: value });
                    setSaveMessage('');
                    setSaveError('');
                }
            })
        );

        if (isLoading) {
            infoLines.push(
                el('div', { className: 'components-base-control' },
                    el(Spinner, {})
                )
            );
        }

        if (!isLoading && ofertaId) {
            infoLines.push(
                el('p', { style: { marginTop: '0.25rem' } },
                    remoteUrl
                        ? __('Valor actual en CPT: ', 'flacso-oferta-academica') + remoteUrl
                        : __('Valor actual en CPT: vacio', 'flacso-oferta-academica')
                )
            );
        }

        if (fetchError) {
            infoLines.push(el(Notice, { status: 'error', isDismissible: false }, fetchError));
        }

        infoLines.push(
            el(Button, {
                variant: 'secondary',
                isBusy: isSaving,
                disabled: isSaving || !ofertaId || !currentUrl,
                onClick: onSaveToCpt
            }, __('Guardar URL en Oferta Academica', 'flacso-oferta-academica'))
        );

        if (saveMessage) {
            infoLines.push(el(Notice, { status: 'success', isDismissible: false }, saveMessage));
        }

        if (saveError) {
            infoLines.push(el(Notice, { status: 'error', isDismissible: false }, saveError));
        }

        return el(
            Fragment,
            {},
            el(
                InspectorControls,
                {},
                el(
                    PanelBody,
                    { title: config.panelTitle, initialOpen: true },
                    infoLines
                )
            ),
            el(ServerSideRender, { block: props.name, attributes: attrs })
        );
    }

    registerBlockType('flacso-uruguay/dato-calendario', {
        attributes: {
            ofertaId: { type: 'integer', default: 0 },
            pdfUrlFallback: { type: 'string', default: '' }
        },
        edit: function (props) {
            return DocumentoPdfBlockEdit(props, {
                metaKey: 'calendario',
                panelTitle: __('Calendario PDF', 'flacso-oferta-academica')
            });
        },
        save: function () {
            return null;
        }
    });

    registerBlockType('flacso-uruguay/dato-malla-curricular', {
        attributes: {
            ofertaId: { type: 'integer', default: 0 },
            pdfUrlFallback: { type: 'string', default: '' }
        },
        edit: function (props) {
            return DocumentoPdfBlockEdit(props, {
                metaKey: 'malla_curricular',
                panelTitle: __('Malla curricular PDF', 'flacso-oferta-academica')
            });
        },
        save: function () {
            return null;
        }
    });
})(window.wp.blocks, window.wp.element, window.wp.components, window.wp.blockEditor, window.wp.serverSideRender, window.wp.data, window.wp.i18n, window.wp.apiFetch);
