(function (wp) {
    const blocks = wp.blocks;
    const element = wp.element;
    const components = wp.components;
    const blockEditor = wp.blockEditor || wp.editor;
    const serverSideRender = wp.serverSideRender;

    if (!blocks || !element || !components) {
        return;
    }

    const { registerBlockType } = blocks;
    const { createElement: el, Fragment } = element;
    const { PanelBody, Notice } = components;
    const InspectorControls = blockEditor ? blockEditor.InspectorControls : null;
    const ServerSideRender = serverSideRender || (wp.serverSideRender ? wp.serverSideRender : null);

    function DocentesListaEdit(props) {
        const { attributes } = props;

        return el(
            Fragment,
            {},
            InspectorControls &&
                el(
                    InspectorControls,
                    {},
                    el(
                        PanelBody,
                        { title: 'Configuracion', initialOpen: true },
                        el(
                            Notice,
                            { status: 'info', isDismissible: false },
                            'Este bloque muestra un listado general de perfiles docentes publicados.'
                        )
                    )
                ),
            ServerSideRender
                ? el(ServerSideRender, {
                      block: 'flacso-uruguay/docentes-lista',
                      attributes: attributes || {},
                  })
                : el(Notice, { status: 'warning', isDismissible: false }, 'No se pudo cargar la vista previa del bloque.')
        );
    }

    registerBlockType('flacso-uruguay/docentes-lista', {
        edit: DocentesListaEdit,
        save: function () {
            return null;
        },
    });
})(window.wp || {});
