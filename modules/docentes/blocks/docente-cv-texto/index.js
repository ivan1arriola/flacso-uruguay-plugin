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
    const { createElement: el, Fragment, useEffect } = element;
    const { PanelBody, SelectControl, Spinner, Notice } = components;
    const InspectorControls = blockEditor ? blockEditor.InspectorControls : null;
    const useSelect = data && data.useSelect ? data.useSelect : null;
    const ServerSideRender = serverSideRender || (wp.serverSideRender ? wp.serverSideRender : null);

    function DocenteCvTextoEdit(props) {
        const { attributes, setAttributes } = props;
        const { docId = 0, slug = '' } = attributes;

        const docentes = useSelect
            ? useSelect((select) => {
                  const core = select('core');
                  if (!core) {
                      return null;
                  }
                  return core.getEntityRecords('postType', 'docente', {
                      per_page: -1,
                      orderby: 'title',
                      order: 'asc',
                      _fields: ['id', 'title', 'slug'],
                  });
              }, [])
            : null;

        const isLoading = useSelect ? docentes === null : false;
        const options = Array.isArray(docentes)
            ? docentes.map((doc) => ({
                  label: doc.title.rendered || `#${doc.id}`,
                  value: doc.id,
              }))
            : [];
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
                        isLoading && el('p', { className: 'components-placeholder' }, el(Spinner, {}), ' Cargando docentes...'),
                        !isLoading && options.length === 0 &&
                            el(Notice, { status: 'warning', isDismissible: false }, 'No hay docentes disponibles.'),
                        options.length > 0 &&
                            el(SelectControl, {
                                label: 'Docente',
                                value: docId || 0,
                                onChange: handleDocChange,
                                options: [{ label: 'Seleccionar docente', value: 0 }].concat(options),
                            })
                    )
                ),
            ServerSideRender
                ? el(ServerSideRender, {
                      block: 'flacso-uruguay/docente-cv-texto',
                      attributes: { docId: docId || 0, slug },
                  })
                : el(Notice, { status: 'warning', isDismissible: false }, 'No se pudo cargar la vista previa del bloque.')
        );
    }

    registerBlockType('flacso-uruguay/docente-cv-texto', {
        edit: DocenteCvTextoEdit,
        save: function () {
            return null;
        },
    });
})(window.wp || {});

