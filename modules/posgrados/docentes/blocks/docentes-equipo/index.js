(function (wp) {
    const blocks = wp.blocks;
    const element = wp.element;
    const components = wp.components;
    const blockEditor = wp.blockEditor || wp.editor;
    const data = wp.data;
    const serverSideRender = wp.serverSideRender;

    if (!blocks || !element || !components) {
        return;
    }

    const { registerBlockType } = blocks;
    const { createElement: el, Fragment, useMemo } = element;
    const { PanelBody, ToggleControl, SelectControl, RangeControl, Spinner, Notice } = components;
    const InspectorControls = blockEditor ? blockEditor.InspectorControls : null;
    const useSelect = data && data.useSelect ? data.useSelect : null;
    const ServerSideRender = serverSideRender || (wp.serverSideRender ? wp.serverSideRender : null);

    const clampColumns = (value) => {
        const parsed = parseInt(value, 10);
        if (isNaN(parsed)) {
            return 3;
        }
        return Math.min(4, Math.max(1, parsed));
    };

    function DocentesEquipoEdit(props) {
        const { attributes, setAttributes } = props;
        const { useCurrentPage = true, termId = 0, columns = 3 } = attributes;

        const termRecords = useSelect
            ? useSelect(
                  (select) => {
                      const core = select('core');
                      if (!core) {
                          return null;
                      }
                      return core.getEntityRecords('taxonomy', 'equipo-docente', {
                          per_page: -1,
                          hide_empty: false,
                          orderby: 'name',
                          order: 'asc',
                      });
                  },
                  []
              )
            : null;

        const isLoadingTerms = useSelect ? termRecords === null : false;
        const termOptions = useMemo(() => {
            if (!Array.isArray(termRecords)) {
                return [];
            }
            return termRecords.map((term) => ({
                label: term.name,
                value: term.id,
            }));
        }, [termRecords]);

        const selectOptions = [{ label: 'Seleccionar equipo', value: 0 }].concat(termOptions);
        const hasManualOptions = termOptions.length > 0;

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
                        el(ToggleControl, {
                            label: 'Detectar posgrado actual automaticamente',
                            checked: !!useCurrentPage,
                            onChange: (value) => setAttributes({ useCurrentPage: !!value }),
                            help: 'Ideal para incrustar el bloque en Kadence y reutilizarlo en todas las paginas de posgrado.',
                        }),
                        !useCurrentPage &&
                            el(
                                Fragment,
                                {},
                                isLoadingTerms &&
                                    el('p', { className: 'components-placeholder' }, el(Spinner, {}), ' Cargando equipos...'),
                                !isLoadingTerms &&
                                    !hasManualOptions &&
                                    el(Notice, { status: 'warning', isDismissible: false }, 'No hay equipos disponibles. Guarda un posgrado para generar esta lista.'),
                                hasManualOptions &&
                                    el(SelectControl, {
                                        label: 'Equipo a mostrar',
                                        value: termId || 0,
                                        options: selectOptions,
                                        onChange: (value) => setAttributes({ termId: parseInt(value, 10) || 0 }),
                                    })
                            ),
                        el(RangeControl, {
                            label: 'Columnas',
                            min: 1,
                            max: 4,
                            value: columns || 3,
                            onChange: (value) => setAttributes({ columns: clampColumns(value) }),
                        })
                    )
                ),
            ServerSideRender
                ? el(ServerSideRender, {
                      block: 'flacso-uruguay/docentes-equipo',
                      attributes: { useCurrentPage, termId, columns: clampColumns(columns) },
                  })
                : el(
                      Notice,
                      { status: 'warning', isDismissible: false },
                      'No se pudo generar la vista previa del bloque.'
                  )
        );
    }

    registerBlockType('flacso-uruguay/docentes-equipo', {
        edit: DocentesEquipoEdit,
        save: function () {
            return null;
        },
    });
})(window.wp || {});




