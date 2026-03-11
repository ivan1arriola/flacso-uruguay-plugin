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
    const { createElement: el, Fragment, useMemo, useEffect } = element;
    const { PanelBody, SelectControl, ToggleControl, Spinner, Notice } = components;
    const InspectorControls = blockEditor ? blockEditor.InspectorControls : null;
    const useSelect = data && data.useSelect ? data.useSelect : null;
    const ServerSideRender = serverSideRender || (wp.serverSideRender ? wp.serverSideRender : null);

    const headingOptions = [
        { label: 'H2', value: 'h2' },
        { label: 'H3', value: 'h3' },
        { label: 'H4', value: 'h4' },
        { label: 'H5', value: 'h5' },
    ];

    function DocenteResumenEdit(props) {
        const { attributes, setAttributes } = props;
        const { docId = 0, slug = '', headingTag = 'h3', showAvatar = true } = attributes;

        const docentes = useSelect
            ? useSelect(
                  (select) => {
                      const coreStore = select('core');
                      if (!coreStore) {
                          return null;
                      }
                      return coreStore.getEntityRecords('postType', 'docente', {
                          per_page: -1,
                          orderby: 'title',
                          order: 'asc',
                          _fields: ['id', 'title', 'slug'],
                      });
                  },
                  []
              )
            : null;

        const isLoading = useSelect ? docentes === null : false;
        const options = useMemo(() => {
            if (!Array.isArray(docentes)) {
                return [];
            }
            return docentes.map((doc) => ({
                label: doc.title.rendered || `#${doc.id}`,
                value: doc.id,
            }));
        }, [docentes]);

        const teacherOptions = [{ label: 'Seleccionar docente', value: 0 }].concat(options);
        const hasRecords = options.length > 0;
        useEffect(() => {
            if (!Array.isArray(docentes)) {
                return;
            }
            if (docId) {
                const selected = docentes.find((doc) => doc.id === docId);
                if (selected && slug !== selected.slug) {
                    setAttributes({ slug: selected.slug });
                }
                return;
            }
            if (!docId && slug) {
                const legacy = docentes.find((doc) => doc.slug === slug);
                if (legacy) {
                    setAttributes({ docId: legacy.id });
                }
            }
        }, [docentes, docId, slug]);

        const handleDocChange = (value) => {
            const nextId = parseInt(value, 10) || 0;
            const selected = Array.isArray(docentes) ? docentes.find((doc) => doc.id === nextId) : null;
            setAttributes({
                docId: nextId,
                slug: selected ? selected.slug : '',
            });
        };

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
                        isLoading &&
                            el('p', { className: 'components-placeholder' }, el(Spinner, {}), ' Cargando docentes...'),
                        !isLoading &&
                            !hasRecords &&
                            el(Notice, { status: 'warning', isDismissible: false }, 'Todavía no hay docentes publicados.'),
                        hasRecords &&
                            el(SelectControl, {
                                label: 'Docente',
                                value: docId || 0,
                                options: teacherOptions,
                                onChange: handleDocChange,
                                help: 'Si no lo encuentras, guarda el docente para que aparezca en la lista.',
                            }),
                        el(SelectControl, {
                            label: 'Etiqueta del nombre',
                            value: headingTag || 'h3',
                            options: headingOptions,
                            onChange: (value) => setAttributes({ headingTag: value }),
                        }),
                        el(ToggleControl, {
                            label: 'Mostrar avatar/foto',
                            checked: !!showAvatar,
                            onChange: (value) => setAttributes({ showAvatar: !!value }),
                        })
                    )
                ),
            ServerSideRender
                ? el(ServerSideRender, {
                      block: 'flacso-uruguay/docente-resumen',
                      attributes: {
                          docId: docId || 0,
                          slug,
                          headingTag,
                          showAvatar,
                      },
                  })
                : el(
                      Notice,
                      { status: 'warning', isDismissible: false },
                      'No se pudo cargar la vista previa del bloque.'
                  )
        );
    }

    registerBlockType('flacso-uruguay/docente-resumen', {
        edit: DocenteResumenEdit,
        save: function () {
            return null;
        },
    });
})(window.wp || {});

