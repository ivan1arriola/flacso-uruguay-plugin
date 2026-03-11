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
    const { PanelBody, SelectControl, TextControl, Spinner, Notice } = components;
    const InspectorControls = blockEditor ? blockEditor.InspectorControls : null;
    const useSelect = data && data.useSelect ? data.useSelect : null;
    const ServerSideRender = serverSideRender || (wp.serverSideRender ? wp.serverSideRender : null);
    const ROLE_PRESETS = [
        { label: 'Coordinación Académica', value: 'Coordinación Académica' },
        { label: 'Asistencia Académica', value: 'Asistencia Académica' },
        { label: 'Dirección del programa', value: 'Dirección del programa' },
        { label: 'Secretaría Académica', value: 'Secretaría Académica' },
    ];

    function DocenteDestacadoEdit(props) {
        const { attributes, setAttributes } = props;
        const { docId = 0, slug = '', role = '' } = attributes;
        const selectedPreset = ROLE_PRESETS.some((item) => item.value === role) ? role : '__custom__';

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
        const selectOptions = [{ label: 'Seleccionar docente', value: 0 }].concat(options);
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
                        !isLoading && options.length === 0 &&
                            el(Notice, { status: 'warning', isDismissible: false }, 'Publica al menos un docente para usar este bloque.'),
                        options.length > 0 &&
                            el(SelectControl, {
                                label: 'Docente',
                                value: docId || 0,
                                options: selectOptions,
                                onChange: handleDocChange,
                            }),
                        el(SelectControl, {
                            label: 'Rol predefinido',
                            value: selectedPreset,
                            options: [
                                { label: 'Personalizado', value: '__custom__' },
                                ...ROLE_PRESETS,
                            ],
                            onChange: (value) => {
                                if (value === '__custom__') {
                                    return;
                                }
                                setAttributes({ role: value });
                            },
                        }),
                        el(TextControl, {
                            label: 'Rol personalizado',
                            value: selectedPreset === '__custom__' ? role : '',
                            onChange: (value) => setAttributes({ role: value }),
                            help: 'Escribí el rol si no está en la lista',
                        })
                    )
                ),
            ServerSideRender
                ? el(ServerSideRender, {
                      block: 'flacso-uruguay/docente-destacado',
                      attributes: { docId: docId || 0, slug, role },
                  })
                : el(Notice, { status: 'warning', isDismissible: false }, 'No se pudo cargar la vista previa del bloque.')
        );
    }

    registerBlockType('flacso-uruguay/docente-destacado', {
        edit: DocenteDestacadoEdit,
        save: function () {
            return null;
        },
    });
})(window.wp || {});

