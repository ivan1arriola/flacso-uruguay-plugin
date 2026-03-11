/**
 * Block: Seminarios Lista
 * Editor JavaScript para el bloque de lista de seminarios
 */

(function() {
    const { registerBlockType } = wp.blocks;
    const { InspectorControls } = wp.blockEditor;
    const { PanelBody, SelectControl, RangeControl, ToggleControl, TextControl } = wp.components;
    const { __ } = wp.i18n;
    const { serverSideRender: ServerSideRender } = wp;

    registerBlockType('flacso-uruguay/seminarios-lista', {
        edit: function(props) {
            const { attributes, setAttributes } = props;
            const {
                posgrado,
                perPage,
                layout,
                showFilters,
                showSearch,
                orderBy,
                order
            } = attributes;

            return [
                <InspectorControls key="inspector">
                    <PanelBody title={__('Configuracion de Seminarios', 'flacso-uruguay')} initialOpen={true}>
                        <TextControl
                            label={__('Filtrar por Posgrado (ID o slug)', 'flacso-uruguay')}
                            value={posgrado}
                            onChange={(value) => setAttributes({ posgrado: value })}
                        />

                        <RangeControl
                            label={__('Seminarios por pagina', 'flacso-uruguay')}
                            value={perPage}
                            onChange={(value) => setAttributes({ perPage: value })}
                            min={6}
                            max={24}
                        />

                        <SelectControl
                            label={__('Diseno', 'flacso-uruguay')}
                            value={layout}
                            options={[
                                { label: 'Cuadricula', value: 'grid' },
                                { label: 'Lista', value: 'list' }
                            ]}
                            onChange={(value) => setAttributes({ layout: value })}
                        />

                        <ToggleControl
                            label={__('Mostrar filtros', 'flacso-uruguay')}
                            checked={showFilters}
                            onChange={(value) => setAttributes({ showFilters: value })}
                        />

                        <ToggleControl
                            label={__('Mostrar buscador', 'flacso-uruguay')}
                            checked={showSearch}
                            onChange={(value) => setAttributes({ showSearch: value })}
                        />

                        <SelectControl
                            label={__('Ordenar por', 'flacso-uruguay')}
                            value={orderBy}
                            options={[
                                { label: 'Fecha de publicacion', value: 'date' },
                                { label: 'Titulo', value: 'title' },
                                { label: 'Periodo de inicio', value: 'periodo_inicio' }
                            ]}
                            onChange={(value) => setAttributes({ orderBy: value })}
                        />

                        <SelectControl
                            label={__('Orden', 'flacso-uruguay')}
                            value={order}
                            options={[
                                { label: 'Descendente', value: 'DESC' },
                                { label: 'Ascendente', value: 'ASC' }
                            ]}
                            onChange={(value) => setAttributes({ order: value })}
                        />
                    </PanelBody>
                </InspectorControls>,

                <ServerSideRender
                    key="preview"
                    block="flacso-uruguay/seminarios-lista"
                    attributes={attributes}
                />
            ];
        },

        save: function() {
            return null; // Server-side rendering
        }
    });
})();
