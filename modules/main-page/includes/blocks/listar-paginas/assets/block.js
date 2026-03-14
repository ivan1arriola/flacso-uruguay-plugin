(function (blocks, element, components, blockEditor, i18n, serverSideRender) {
    if (!blocks || !element || !components || !serverSideRender) {
        return;
    }

    var registerBlockType = blocks.registerBlockType;
    var getBlockType = blocks.getBlockType;
    var el = element.createElement;
    var Fragment = element.Fragment;
    var PanelBody = components.PanelBody;
    var TextControl = components.TextControl;
    var ToggleControl = components.ToggleControl;
    var SelectControl = components.SelectControl;
    var InspectorControls = (blockEditor && blockEditor.InspectorControls) || (window.wp.editor && window.wp.editor.InspectorControls);
    var ServerSideRender = serverSideRender;
    var __ = i18n.__;

    var blockName = 'flacso-uruguay/listar-paginas';

    if (!registerBlockType || !InspectorControls) {
        return;
    }

    if (typeof getBlockType === 'function' && getBlockType(blockName)) {
        return;
    }

    registerBlockType(blockName, {
        title: __('Listado de paginas (posgrados)', 'flacso-main-page'),
        icon: 'index-card',
        category: 'flacso-uruguay',
        attributes: {
            padre: { type: 'string', default: '' },
            padre_id: { type: 'number', default: 0 },
            posts_per_page: { type: 'number', default: -1 },
            mostrar_inactivos: { type: 'boolean', default: false },
            vista: { type: 'string', default: 'catalogo_3d' }
        },
        transforms: {
            from: [
                {
                    type: 'shortcode',
                    tag: 'listar_paginas',
                    attributes: {
                        padre: {
                            type: 'string',
                            shortcode: function (attrs) { return attrs && attrs.named ? (attrs.named.padre || '') : ''; }
                        },
                        padre_id: {
                            type: 'number',
                            shortcode: function (attrs) {
                                var value = attrs && attrs.named ? attrs.named.padre_id : 0;
                                return parseInt(value || 0, 10);
                            }
                        },
                        posts_per_page: {
                            type: 'number',
                            shortcode: function (attrs) {
                                var value = attrs && attrs.named ? attrs.named.posts_per_page : -1;
                                return parseInt(value || -1, 10);
                            }
                        },
                        mostrar_inactivos: {
                            type: 'boolean',
                            shortcode: function (attrs) {
                                var value = attrs && attrs.named ? attrs.named.mostrar_inactivos : '';
                                return String(value || '').toLowerCase() === '1' || String(value || '').toLowerCase() === 'true';
                            }
                        },
                        vista: {
                            type: 'string',
                            shortcode: function (attrs) {
                                return attrs && attrs.named ? (attrs.named.vista || 'catalogo_3d') : 'catalogo_3d';
                            }
                        }
                    },
                    transform: function (attrs) {
                        return blocks.createBlock(blockName, attrs);
                    }
                }
            ]
        },
        edit: function (props) {
            var attributes = props.attributes || {};
            var setAttributes = props.setAttributes;

            return el(
                Fragment,
                {},
                el(
                    InspectorControls,
                    {},
                    el(
                        PanelBody,
                        { title: __('Configuracion', 'flacso-main-page'), initialOpen: true },
                        el(TextControl, {
                            label: __('Nombre de la pagina padre', 'flacso-main-page'),
                            value: attributes.padre || '',
                            onChange: function (value) { setAttributes({ padre: value || '' }); },
                            help: __('Se ignora si completas el ID de pagina padre.', 'flacso-main-page')
                        }),
                        el(TextControl, {
                            label: __('ID de pagina padre', 'flacso-main-page'),
                            type: 'number',
                            value: attributes.padre_id || '',
                            onChange: function (value) { setAttributes({ padre_id: value === '' ? 0 : parseInt(value, 10) || 0 }); }
                        }),
                        el(TextControl, {
                            label: __('Cantidad de paginas (-1 = todas)', 'flacso-main-page'),
                            type: 'number',
                            value: attributes.posts_per_page === undefined ? -1 : attributes.posts_per_page,
                            onChange: function (value) { setAttributes({ posts_per_page: value === '' ? -1 : parseInt(value, 10) || -1 }); }
                        }),
                        el(ToggleControl, {
                            label: __('Mostrar programas no vigentes', 'flacso-main-page'),
                            checked: !!attributes.mostrar_inactivos,
                            onChange: function (value) { setAttributes({ mostrar_inactivos: !!value }); }
                        }),
                        el(SelectControl, {
                            label: __('Vista', 'flacso-main-page'),
                            value: attributes.vista || 'catalogo_3d',
                            options: [
                                { label: __('Catalogo 3D', 'flacso-main-page'), value: 'catalogo_3d' },
                                { label: __('Grid', 'flacso-main-page'), value: 'grid' }
                            ],
                            onChange: function (value) { setAttributes({ vista: value || 'catalogo_3d' }); }
                        })
                    )
                ),
                el(ServerSideRender, {
                    block: blockName,
                    attributes: attributes
                })
            );
        },
        save: function () {
            return null;
        }
    });
})(
    window.wp.blocks,
    window.wp.element,
    window.wp.components,
    window.wp.blockEditor,
    window.wp.i18n,
    window.wp.serverSideRender
);
