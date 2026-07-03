(function (blocks, element, components, blockEditor, serverSideRender) {
    if (!blocks || !serverSideRender) {
        return;
    }

    const { registerBlockType } = blocks;
    const { Fragment, createElement: el } = element;
    const { PanelBody, TextControl } = components;
    const { InspectorControls } = blockEditor || wp.editor;
    const ServerSideRender = serverSideRender;

    const instagramBlocks = [
        {
            name: 'flacso-uruguay/instagram-publicaciones',
            title: 'Instagram: Publicaciones',
            description: 'Muestra las fotos de Instagram en una cuadrícula.',
            icon: 'camera',
            category: 'flacso-uruguay'
        },
        {
            name: 'flacso-uruguay/instagram-carruseles',
            title: 'Instagram: Carruseles',
            description: 'Muestra publicaciones tipo carrusel de Instagram.',
            icon: 'images-alt2',
            category: 'flacso-uruguay'
        }
    ];

    instagramBlocks.forEach(function(blockConfig) {
        registerBlockType(blockConfig.name, {
            title: blockConfig.title,
            description: blockConfig.description,
            icon: blockConfig.icon,
            category: blockConfig.category,
            supports: {
                html: false,
                align: ['full', 'wide'],
                inserter: true,
                multiple: true,
                reusable: true,
            },
            attributes: {
                title: {
                    type: 'string',
                    default: ''
                }
            },
            edit: function(props) {
                const { attributes, setAttributes } = props;
                return el(
                    Fragment,
                    {},
                    el(
                        InspectorControls,
                        {},
                        el(
                            PanelBody,
                            { title: blockConfig.title, initialOpen: true },
                            el(TextControl, {
                                label: 'Título (opcional)',
                                type: 'text',
                                value: attributes.title,
                                onChange: function(newValue) {
                                    setAttributes({ title: newValue });
                                }
                            })
                        )
                    ),
                    el(ServerSideRender, { block: blockConfig.name, attributes: attributes })
                );
            },
            save: function() { return null; }
        });
    });
})(window.wp.blocks, window.wp.element, window.wp.components, window.wp.blockEditor || window.wp.editor, window.wp.serverSideRender);
