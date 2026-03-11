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
    const { createElement: el, Fragment } = element;
    const { PanelBody, ToggleControl, SelectControl, TextControl, Spinner, Notice } = components;
    const InspectorControls = blockEditor ? blockEditor.InspectorControls : null;
    const useSelect = data && data.useSelect ? data.useSelect : null;
    const ServerSideRender = serverSideRender || (wp.serverSideRender ? wp.serverSideRender : null);

    function DocentesListaEdit(props) {
        const { attributes, setAttributes } = props;
        const { termId = 0, slug = '', useCurrentPage = false } = attributes;

        const terms = useSelect
            ? useSelect((select) => {
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
              }, [])
            : null;

        const isLoading = useSelect ? terms === null : false;
        const options = Array.isArray(terms)
            ? terms.map((term) => ({
                  label: term.name,
                  value: term.id,
              }))
            : [];
        const selectOptions = [{ label: 'Seleccionar equipo', value: 0 }].concat(options);

        return el(
            Fragment,
            {},
            InspectorControls &&
                el(
                    InspectorControls,
                    {},
                    el(
                        PanelBody,
                        { title: 'Configuración', initialOpen: true },
                        el(ToggleControl, {
                            label: 'Usar el posgrado actual',
                            checked: !!useCurrentPage,
                            onChange: (value) => setAttributes({ useCurrentPage: !!value }),
                            help: 'Toma el equipo vinculado a la página actual (ideal para plantillas Kadence).',
                        }),
                        !useCurrentPage &&
                            el(
                                Fragment,
                                {},
                                isLoading &&
                                    el('p', { className: 'components-placeholder' }, el(Spinner, {}), ' Cargando equipos...'),
                                !isLoading && options.length === 0 &&
                                    el(Notice, { status: 'warning', isDismissible: false }, 'No hay equipos disponibles todavía.'),
                                options.length > 0 &&
                                    el(SelectControl, {
                                        label: 'Equipo docente',
                                        value: termId || 0,
                                        options: selectOptions,
                                        onChange: (value) => setAttributes({ termId: parseInt(value, 10) || 0 }),
                                    }),
                                el(TextControl, {
                                    label: 'Slug manual (opcional)',
                                    value: slug,
                                    onChange: (value) => setAttributes({ slug: value }),
                                    help: 'Usa este campo solo si el equipo no aparece en la lista.',
                                })
                            )
                    )
                ),
            ServerSideRender
                ? el(ServerSideRender, {
                      block: 'flacso-uruguay/docentes-lista',
                      attributes: { termId: termId || 0, slug, useCurrentPage },
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

