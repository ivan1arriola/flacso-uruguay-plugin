(function (wp) {
    if (!wp || !wp.blocks || !wp.element) {
        return;
    }

    var registerBlockType = wp.blocks.registerBlockType;
    var el = wp.element.createElement;
    var Fragment = wp.element.Fragment;
    var components = wp.components;
    var blockEditor = wp.blockEditor || wp.editor;
    var __ = wp.i18n && wp.i18n.__ ? wp.i18n.__ : function (value) { return value; };
    var ServerSideRender = wp.serverSideRender || null;

    if (!components || !blockEditor) {
        return;
    }

    var PanelBody = components.PanelBody;
    var TextControl = components.TextControl;
    var Notice = components.Notice;
    var InspectorControls = blockEditor.InspectorControls;

    function editBlock(props) {
        var attributes = props.attributes || {};
        var setAttributes = props.setAttributes;

        var inspector = el(
            InspectorControls,
            {},
            el(
                PanelBody,
                { title: __('Contenido', 'flacso-main-page'), initialOpen: true },
                el(TextControl, {
                    label: __('Texto superior', 'flacso-main-page'),
                    value: attributes.tagText || '',
                    onChange: function (value) { setAttributes({ tagText: value }); }
                }),
                el(TextControl, {
                    label: __('Texto CTA', 'flacso-main-page'),
                    value: attributes.ctaText || '',
                    onChange: function (value) { setAttributes({ ctaText: value }); }
                }),
                el(
                    Notice,
                    { status: 'info', isDismissible: false },
                    __('La imagen se toma de la imagen destacada de la pagina. Si no hay, se muestra un placeholder.', 'flacso-main-page')
                )
            )
        );

        var preview = ServerSideRender
            ? el(ServerSideRender, { block: props.name, attributes: attributes })
            : el(Notice, { status: 'warning', isDismissible: false }, __('No se pudo cargar la previsualizacion.', 'flacso-main-page'));

        return el(Fragment, {}, inspector, preview);
    }

    function saveBlock() {
        return null;
    }

    var settings = {
        title: __('Banner inscripciones 2026', 'flacso-main-page'),
        icon: 'megaphone',
        category: 'flacso-uruguay',
        description: __('Banner con imagen destacada, texto y logo FLACSO Uruguay.', 'flacso-main-page'),
        supports: {
            html: false,
            align: ['wide', 'full'],
            multiple: true,
            reusable: true
        },
        attributes: {
            tagText: { type: 'string', default: 'Próximo inicio' },
            ctaText: { type: 'string', default: 'Descuentos especiales disponibles. Solicitá informacion e inscribite hoy.' }
        },
        edit: editBlock,
        save: saveBlock
    };

    registerBlockType('flacso-uruguay/inscripciones-banner', settings);

    registerBlockType('flacso/inscripciones-banner', {
        title: settings.title,
        icon: settings.icon,
        category: settings.category,
        description: settings.description,
        supports: {
            html: false,
            inserter: false,
            reusable: true
        },
        attributes: settings.attributes,
        edit: editBlock,
        save: saveBlock
    });
})(window.wp);

