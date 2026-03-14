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
    var TextareaControl = components.TextareaControl;
    var Notice = components.Notice;
    var InspectorControls = blockEditor.InspectorControls;

    registerBlockType('flacso-uruguay/mapa-contacto', {
        title: __('FLACSO - Mapa de contacto', 'flacso-main-page'),
        icon: 'location-alt',
        category: 'flacso-uruguay',
        description: __('Bloque institucional para mostrar mapa y dirección.', 'flacso-main-page'),
        supports: {
            html: false,
            align: ['wide', 'full'],
            multiple: true,
            reusable: true
        },
        attributes: {
            titulo: { type: 'string', default: __('Ubicación', 'flacso-main-page') },
            etiqueta: { type: 'string', default: 'FLACSO Uruguay' },
            direccion: { type: 'string', default: '8 de Octubre 2882, CP 11600, Montevideo' },
            mapsUrl: { type: 'string', default: 'https://maps.google.com/?q=FLACSO+Uruguay+8+de+Octubre+2882+Montevideo' },
            embedUrl: { type: 'string', default: 'https://www.google.com/maps/embed?pb=!1m18!1m12!1m3!1d3272.8199354511025!2d-56.15957652399946!3d-34.88586767245433!2m3!1f0!2f0!3f0!3m2!1i1024!2i768!4f13.1!3m3!1m2!1s0x959f81cd043a1cd5%3A0x9f771efca246dee8!2sFLACSO%20Uruguay!5e0!3m2!1ses-419!2suy!4v1757699982564!5m2!1ses-419!2suy' },
            agendaUrl: { type: 'string', default: 'https://agenda.flacso.edu.uy/' }
        },
        edit: function (props) {
            var attributes = props.attributes;
            var setAttributes = props.setAttributes;

            var inspector = el(
                InspectorControls,
                {},
                el(
                    PanelBody,
                    { title: __('Configuración del mapa', 'flacso-main-page'), initialOpen: true },
                    el(TextControl, {
                        label: __('Título', 'flacso-main-page'),
                        value: attributes.titulo || '',
                        onChange: function (value) { setAttributes({ titulo: value }); }
                    }),
                    el(TextControl, {
                        label: __('Etiqueta', 'flacso-main-page'),
                        value: attributes.etiqueta || '',
                        onChange: function (value) { setAttributes({ etiqueta: value }); }
                    }),
                    el(TextareaControl, {
                        label: __('Dirección', 'flacso-main-page'),
                        value: attributes.direccion || '',
                        onChange: function (value) { setAttributes({ direccion: value }); }
                    }),
                    el(TextControl, {
                        label: __('URL para abrir en Google Maps', 'flacso-main-page'),
                        value: attributes.mapsUrl || '',
                        onChange: function (value) { setAttributes({ mapsUrl: value }); }
                    }),
                    el(TextareaControl, {
                        label: __('URL embed de Google Maps', 'flacso-main-page'),
                        value: attributes.embedUrl || '',
                        onChange: function (value) { setAttributes({ embedUrl: value }); }
                    }),
                    el(TextControl, {
                        label: __('URL Agenda web', 'flacso-main-page'),
                        value: attributes.agendaUrl || '',
                        onChange: function (value) { setAttributes({ agendaUrl: value }); }
                    })
                )
            );

            var preview = ServerSideRender
                ? el(ServerSideRender, { block: props.name, attributes: attributes })
                : el(Notice, { status: 'warning', isDismissible: false }, __('No se pudo cargar la previsualización.', 'flacso-main-page'));

            return el(Fragment, {}, inspector, preview);
        },
        save: function () {
            return null;
        }
    });
})(window.wp);

