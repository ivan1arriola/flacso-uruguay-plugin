(function (wp) {
    if (!wp || !wp.blocks || !wp.element || !wp.blockEditor) {
        return;
    }

    var registerBlockType = wp.blocks.registerBlockType;
    var el = wp.element.createElement;
    var __ = wp.i18n && wp.i18n.__ ? wp.i18n.__ : function (value) { return value; };
    var InnerBlocks = wp.blockEditor.InnerBlocks;
    var useBlockProps = wp.blockEditor.useBlockProps;

    var TEMPLATE = [
        ['flacso-uruguay/mapa-contacto', {}],
        ['flacso-uruguay/otros-contactos', {}]
    ];

    registerBlockType('flacso-uruguay/contacto-seccion', {
        title: __('FLACSO - Sección de contacto', 'flacso-main-page'),
        icon: 'email-alt2',
        category: 'flacso-uruguay',
        description: __('Sección compuesta por mapa de contacto y otros contactos.', 'flacso-main-page'),
        supports: {
            html: false,
            align: ['wide', 'full'],
            multiple: true,
            reusable: true
        },
        edit: function () {
            var blockProps = useBlockProps({ className: 'flacso-contacto-seccion' });
            return el(
                'section',
                blockProps,
                el(
                    'div',
                    { className: 'flacso-contacto-seccion__inner' },
                    el(InnerBlocks, {
                        allowedBlocks: ['flacso-uruguay/mapa-contacto', 'flacso-uruguay/otros-contactos'],
                        template: TEMPLATE,
                        templateLock: false
                    })
                )
            );
        },
        save: function () {
            var blockProps = useBlockProps.save({ className: 'flacso-contacto-seccion' });
            return el(
                'section',
                blockProps,
                el('div', { className: 'flacso-contacto-seccion__inner' }, el(InnerBlocks.Content))
            );
        }
    });
})(window.wp);

