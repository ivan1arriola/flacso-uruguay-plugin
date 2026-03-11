( function ( blocks, element, components, blockEditor, i18n, data, serverSideRender ) {
    const { registerBlockType, createBlock } = blocks;
    const { Fragment, useMemo } = element;
    const { TextControl, ToggleControl, PanelBody, SelectControl } = components;
    const { InspectorControls } = blockEditor;
    const { __ } = i18n;
    const { useSelect } = data;
    const ServerSideRender = serverSideRender;

    const blockName = 'flacso-uruguay/listar-paginas';

    const ParentSelectControl = ( { value, onChange } ) => {
        const pages = useSelect(
            ( select ) =>
                select( 'core' ).getEntityRecords( 'postType', 'page', {
                    per_page: -1,
                    orderby: 'title',
                    order: 'asc',
                    _fields: [ 'id', 'title' ],
                } ),
            []
        );

        const options = useMemo( () => {
            if ( Array.isArray( pages ) ) {
                const mapped = pages.map( ( page ) => {
                    const label = page && page.title && page.title.rendered ? page.title.rendered : page.id;
                    return { label, value: page.id };
                } );
                return [ { label: __( 'Seleccione una pagina', 'flacso' ), value: 0 }, ...mapped ];
            }
            return [ { label: __( 'Cargando paginas…', 'flacso' ), value: 0 } ];
        }, [ pages ] );

        return (
            <SelectControl
                label={ __( 'Pagina padre', 'flacso' ) }
                value={ value || 0 }
                onChange={ ( selected ) => onChange( selected ? parseInt( selected, 10 ) : 0 ) }
                options={ options }
                help={ __( 'Si eliges aqui, no necesitas llenar el nombre.', 'flacso' ) }
            />
        );
    };

    registerBlockType( blockName, {
        title: __( 'Listado de paginas (posgrados)', 'flacso-main-page' ),
        icon: 'index-card',
        category: 'flacso-uruguay',
        attributes: {
            padre: { type: 'string', default: '' },
            padre_id: { type: 'number', default: 0 },
            posts_per_page: { type: 'number', default: -1 },
            mostrar_inactivos: { type: 'boolean', default: false },
        },
        transforms: {
            from: [
                {
                    type: 'shortcode',
                    tag: 'listar_paginas',
                    attributes: {
                        padre: { type: 'string', shortcode: ( attrs ) => attrs.named.padre || '' },
                        padre_id: { type: 'number', shortcode: ( attrs ) => parseInt( attrs.named.padre_id || 0, 10 ) },
                        posts_per_page: { type: 'number', shortcode: ( attrs ) => parseInt( attrs.named.posts_per_page || -1, 10 ) },
                        mostrar_inactivos: { type: 'boolean', shortcode: ( attrs ) => String( attrs.named.mostrar_inactivos || '' ).toLowerCase() === '1' || attrs.named.mostrar_inactivos === 'true' },
                    },
                    transform: ( attrs ) => createBlock( blockName, attrs ),
                },
            ],
        },
        edit: ( props ) => {
            const { attributes, setAttributes } = props;
            return (
                <Fragment>
                    <InspectorControls>
                        <PanelBody title={ __( 'Configuracion', 'flacso-main-page' ) } initialOpen={ true }>
                            <TextControl
                                label={ __( 'Nombre de la pagina padre', 'flacso-main-page' ) }
                                value={ attributes.padre }
                                onChange={ ( value ) => setAttributes( { padre: value } ) }
                                help={ __( 'Ej: "Diplomados", "Especializaciones". Se ignora si usas ID.', 'flacso-main-page' ) }
                            />
                            <TextControl
                                label={ __( 'ID de la pagina padre (prioridad sobre nombre)', 'flacso-main-page' ) }
                                type="number"
                                value={ attributes.padre_id || '' }
                                onChange={ ( value ) => setAttributes( { padre_id: value ? parseInt( value, 10 ) : 0 } ) }
                            />
                            <ParentSelectControl
                                value={ attributes.padre_id }
                                onChange={ ( value ) => setAttributes( { padre_id: value } ) }
                            />
                            <TextControl
                                label={ __( 'Cantidad de paginas a mostrar (-1 = todas)', 'flacso-main-page' ) }
                                type="number"
                                value={ attributes.posts_per_page ?? -1 }
                                onChange={ ( value ) => setAttributes( { posts_per_page: value === '' ? -1 : parseInt( value, 10 ) } ) }
                            />
                            <ToggleControl
                                label={ __( 'Mostrar programas no vigentes', 'flacso-main-page' ) }
                                checked={ !! attributes.mostrar_inactivos }
                                onChange={ ( value ) => setAttributes( { mostrar_inactivos: !! value } ) }
                            />
                        </PanelBody>
                    </InspectorControls>
                    <ServerSideRender block={ blockName } attributes={ attributes } />
                </Fragment>
            );
        },
        save: () => null,
    } );
} )(
    window.wp.blocks,
    window.wp.element,
    window.wp.components,
    window.wp.blockEditor,
    window.wp.i18n,
    window.wp.data,
    window.wp.serverSideRender
);
