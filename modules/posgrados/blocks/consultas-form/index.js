(function (wp) {
    const { blocks, element, components, blockEditor, serverSideRender, data } = wp || {};
    if (!blocks || !element || !components || !blockEditor || !serverSideRender || !data) {
        return;
    }

    const { registerBlockType } = blocks;
    const { createElement: el, Fragment } = element;
    const { PanelBody, ToggleControl, Notice } = components;
    const InspectorControls = blockEditor.InspectorControls;
    const { useSelect } = data;
    const ServerSideRender = serverSideRender;

    registerBlockType('flacso-uruguay/consultas-form', {
        edit: function (props) {
            const { attributes, setAttributes } = props;
            const { showPreinscription = true } = attributes;

            return el(
                Fragment,
                {},
                el(
                    InspectorControls,
                    {},
                    el(
                        PanelBody,
                        { title: 'Opciones', initialOpen: true },
                        el(ToggleControl, {
                            label: 'Mostrar botón de Preinscripción',
                            checked: !!showPreinscription,
                            onChange: (value) => setAttributes({ showPreinscription: !!value }),
                            help: 'Agrega el acceso directo a /preinscripcion debajo del formulario.'
                        })
                    )
                ),
                el(ServerSideRender, {
                    block: 'flacso-uruguay/consultas-form',
                    attributes: { showPreinscription }
                })
            );
        },
        save: () => null
    });
})(window.wp || {});

