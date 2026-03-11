(function (blocks, element, components, blockEditor, serverSideRender, data, i18n) {
    if (!blocks || !serverSideRender || !data) {
        return;
    }

    var registerBlockType = blocks.registerBlockType;
    var el = element.createElement;
    var Fragment = element.Fragment;
    var InspectorControls = (blockEditor || wp.blockEditor).InspectorControls;
    var PanelBody = components.PanelBody;
    var SelectControl = components.SelectControl;
    var ServerSideRender = serverSideRender;
    var useSelect = data.useSelect;
    var __ = (i18n || wp.i18n).__;

    registerBlockType('flacso-uruguay/dato-proximo-inicio', {
        attributes: {
            ofertaId: { type: 'integer', default: 0 }
        },
        edit: function (props) {
            var attrs = props.attributes;
            var setAttributes = props.setAttributes;

            var ofertas = useSelect(function (select) {
                return select('core').getEntityRecords('postType', 'oferta-academica', {
                    per_page: 100,
                    orderby: 'title',
                    order: 'asc',
                    status: 'publish'
                });
            }, []);

            var options = [{ label: __('Selecciona una oferta...', 'flacso-oferta-academica'), value: 0 }];
            if (Array.isArray(ofertas)) {
                ofertas.forEach(function (post) {
                    options.push({ label: post.title && post.title.rendered ? post.title.rendered : ('#' + post.id), value: post.id });
                });
            }

            return el(
                Fragment,
                {},
                el(
                    InspectorControls,
                    {},
                    el(
                        PanelBody,
                        { title: __('Oferta academica', 'flacso-oferta-academica'), initialOpen: true },
                        el(SelectControl, {
                            label: __('Selecciona la oferta', 'flacso-oferta-academica'),
                            value: attrs.ofertaId || 0,
                            options: options,
                            onChange: function (value) {
                                setAttributes({ ofertaId: parseInt(value || 0, 10) });
                            }
                        })
                    )
                ),
                el(ServerSideRender, { block: props.name, attributes: attrs })
            );
        },
        save: function () {
            return null;
        }
    });
})(window.wp.blocks, window.wp.element, window.wp.components, window.wp.blockEditor, window.wp.serverSideRender, window.wp.data, window.wp.i18n);

