(function (blocks, element, components, blockEditor, serverSideRender, i18n) {
    if (!blocks || !serverSideRender) {
        return;
    }

    const { registerBlockType } = blocks;
    const { Fragment, createElement: el } = element;
    const { PanelBody, TextControl, Button, ToggleControl } = components;
    const { InspectorControls, MediaUpload, MediaUploadCheck } = blockEditor || wp.blockEditor;
    const ServerSideRender = serverSideRender;
    const { __ } = i18n || wp.i18n;

    const pageBlockName = 'flacso-uruguay/oferta-academica-pagina';
    const pageAttributes = {
        heroTitle: { type: 'string' },
        heroSubtitle: { type: 'string' },
        heroImageId: { type: 'number' },
        heroImageUrl: { type: 'string' },
    };

    const editPageBlock = (props) => {
        const { attributes: attrs, setAttributes } = props;
        const { heroTitle, heroSubtitle, heroImageId, heroImageUrl } = attrs;

        return el(
            Fragment,
            {},
            el(
                InspectorControls,
                {},
                el(
                    PanelBody,
                    { title: __('Hero', 'flacso-oferta-academica'), initialOpen: true },
                    el(TextControl, {
                        label: __('Título', 'flacso-oferta-academica'),
                        value: heroTitle || '',
                        onChange: (value) => setAttributes({ heroTitle: value }),
                    }),
                    el(TextControl, {
                        label: __('Subtítulo', 'flacso-oferta-academica'),
                        value: heroSubtitle || '',
                        onChange: (value) => setAttributes({ heroSubtitle: value }),
                    }),
                    el('div', { className: 'flacso-oferta-media-control' },
                        el(MediaUploadCheck, {},
                            el(MediaUpload, {
                                onSelect: (media) => {
                                    setAttributes({
                                        heroImageId: media && media.id ? media.id : 0,
                                        heroImageUrl: media && media.url ? media.url : '',
                                    });
                                },
                                allowedTypes: ['image'],
                                value: heroImageId || 0,
                                render: ({ open }) => el(
                                    Fragment,
                                    {},
                                    el(Button, { onClick: open, variant: 'secondary' },
                                        heroImageId ? __('Reemplazar imagen', 'flacso-oferta-academica') : __('Seleccionar imagen', 'flacso-oferta-academica')
                                    ),
                                    heroImageId ? el(Button, {
                                        onClick: () => setAttributes({ heroImageId: 0, heroImageUrl: '' }),
                                        variant: 'link',
                                        isDestructive: true,
                                    }, __('Quitar imagen', 'flacso-oferta-academica')) : null,
                                    heroImageUrl ? el('img', { src: heroImageUrl, style: { maxWidth: '100%', marginTop: '8px', borderRadius: '6px' } }) : null
                                ),
                            })
                        )
                    )
                )
            ),
            el(ServerSideRender, { block: props.name, attributes: attrs })
        );
    };

    const programAttributes = {
        ofertaId: { type: 'number', default: 0 },
        mostrarPreinscripcion: { type: 'boolean', default: true },
        mostrarFormulario: { type: 'boolean', default: true },
    };

    const editProgramBlock = (props) => {
        const { attributes: attrs, setAttributes } = props;
        const ofertaIdValue = attrs.ofertaId ? String(attrs.ofertaId) : '';

        return el(
            Fragment,
            {},
            el(
                InspectorControls,
                {},
                el(
                    PanelBody,
                    { title: __('Configuracion del programa', 'flacso-oferta-academica'), initialOpen: true },
                    el(TextControl, {
                        label: __('ID de oferta academica (opcional)', 'flacso-oferta-academica'),
                        type: 'number',
                        value: ofertaIdValue,
                        help: __('Si queda vacio, se usa la oferta vinculada a la pagina actual (_oferta_page_id).', 'flacso-oferta-academica'),
                        onChange: function (value) {
                            var parsed = parseInt(value, 10);
                            setAttributes({ ofertaId: isNaN(parsed) ? 0 : parsed });
                        },
                    }),
                    el(ToggleControl, {
                        label: __('Mostrar boton de preinscripcion', 'flacso-oferta-academica'),
                        checked: attrs.mostrarPreinscripcion !== false,
                        onChange: function (checked) {
                            setAttributes({ mostrarPreinscripcion: !!checked });
                        },
                    }),
                    el(ToggleControl, {
                        label: __('Mostrar formulario de consulta', 'flacso-oferta-academica'),
                        checked: attrs.mostrarFormulario !== false,
                        onChange: function (checked) {
                            setAttributes({ mostrarFormulario: !!checked });
                        },
                    })
                )
            ),
            el(ServerSideRender, { block: props.name, attributes: attrs })
        );
    };

    registerBlockType(pageBlockName, {
        attributes: pageAttributes,
        edit: editPageBlock,
        save: () => null,
    });

    registerBlockType('flacso-uruguay/oferta-academica-programa', {
        attributes: programAttributes,
        edit: editProgramBlock,
        save: () => null,
    });
})(window.wp.blocks, window.wp.element, window.wp.components, window.wp.blockEditor, window.wp.serverSideRender, window.wp.i18n);

