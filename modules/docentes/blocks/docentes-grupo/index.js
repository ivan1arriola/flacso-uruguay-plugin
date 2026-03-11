(function (wp) {
    if (!wp || !wp.blocks || !wp.element || !wp.components || !wp.data) {
        return;
    }

    var registerBlockType = wp.blocks.registerBlockType;
    var el = wp.element.createElement;
    var Fragment = wp.element.Fragment;
    var useEffect = wp.element.useEffect;
    var useMemo = wp.element.useMemo;
    var useState = wp.element.useState;
    var InspectorControls = (wp.blockEditor && wp.blockEditor.InspectorControls) || (wp.editor && wp.editor.InspectorControls);
    var useBlockProps = (wp.blockEditor && wp.blockEditor.useBlockProps) || (wp.editor && wp.editor.useBlockProps);
    var RichText = (wp.blockEditor && wp.blockEditor.RichText) || (wp.editor && wp.editor.RichText);
    var PanelBody = wp.components.PanelBody;
    var SelectControl = wp.components.SelectControl;
    var TextControl = wp.components.TextControl;
    var Button = wp.components.Button;
    var Spinner = wp.components.Spinner;
    var Notice = wp.components.Notice;
    var useSelect = wp.data.useSelect;
    var coreStore = wp.coreData && wp.coreData.store;
    var ServerSideRender = wp.serverSideRender;

    var HEADING_OPTIONS = [
        { label: "H2", value: "h2" },
        { label: "H3", value: "h3" },
        { label: "H4", value: "h4" },
        { label: "H5", value: "h5" },
        { label: "H6", value: "h6" }
    ];

    registerBlockType("flacso/docentes-grupo", {
        edit: function (props) {
            var attributes = props.attributes || {};
            var setAttributes = props.setAttributes;
            var title = attributes.title || "Docentes";
            var level = attributes.level || "h2";
            var docenteIds = Array.isArray(attributes.docenteIds) ? attributes.docenteIds : [];

            var state = useState("");
            var query = state[0];
            var setQuery = state[1];

            var pageState = useState(1);
            var page = pageState[0];
            var setPage = pageState[1];

            var perPage = 20;

            useEffect(function () {
                setPage(1);
            }, [query]);

            var queryArgs = {
                per_page: perPage,
                page: page,
                orderby: "title",
                order: "asc",
                status: "publish"
            };

            if (query) {
                queryArgs.search = query;
            }

            var docentes = useSelect(
                function (select) {
                    if (!coreStore) return null;
                    return select(coreStore).getEntityRecords("postType", "docente", queryArgs);
                },
                [query, page]
            );

            var isResolving = useSelect(
                function (select) {
                    if (!coreStore) return false;
                    return select(coreStore).isResolving("getEntityRecords", ["postType", "docente", queryArgs]);
                },
                [query, page]
            );

            var selectedDocs = useSelect(
                function (select) {
                    if (!coreStore) return [];
                    if (!docenteIds.length) return [];
                    return select(coreStore).getEntityRecords("postType", "docente", {
                        per_page: Math.min(100, docenteIds.length),
                        include: docenteIds,
                        orderby: "include",
                        status: "publish"
                    });
                },
                [docenteIds.join(",")]
            );

            var selectedMap = useMemo(function () {
                var map = new Map();
                if (Array.isArray(selectedDocs)) {
                    selectedDocs.forEach(function (d) {
                        if (d && d.id) {
                            map.set(d.id, d);
                        }
                    });
                }
                return map;
            }, [selectedDocs]);

            var options = Array.isArray(docentes)
                ? docentes.map(function (d) {
                      return {
                          label: (d && d.title && d.title.rendered) ? d.title.rendered : "(ID " + d.id + ")",
                          value: d.id
                      };
                  })
                : [];

            var addDocente = function (id) {
                var parsed = Number(id);
                if (!parsed) return;
                if (docenteIds.indexOf(parsed) !== -1) return;
                setAttributes({ docenteIds: docenteIds.concat([parsed]) });
            };

            var removeDocente = function (id) {
                setAttributes({
                    docenteIds: docenteIds.filter(function (x) { return x !== id; })
                });
            };

            var moveDocente = function (id, dir) {
                var idx = docenteIds.indexOf(id);
                if (idx === -1) return;
                var next = idx + dir;
                if (next < 0 || next >= docenteIds.length) return;
                var copy = docenteIds.slice();
                var tmp = copy[idx];
                copy[idx] = copy[next];
                copy[next] = tmp;
                setAttributes({ docenteIds: copy });
            };

            var blockProps = useBlockProps ? useBlockProps() : {};

            return el(
                Fragment,
                {},
                InspectorControls && el(
                    InspectorControls,
                    {},
                    el(
                        PanelBody,
                        { title: "Configuracion", initialOpen: true },
                        el(SelectControl, {
                            label: "Nivel de titulo",
                            value: level,
                            options: HEADING_OPTIONS,
                            onChange: function (v) { setAttributes({ level: v }); }
                        }),
                        el("hr", { style: { margin: "12px 0" } }),
                        el(TextControl, {
                            label: "Buscar docente",
                            value: query,
                            onChange: function (v) { setQuery(v); },
                            placeholder: "Ej: Laura, Perez, Breilh"
                        }),
                        isResolving ? el("div", { style: { margin: "6px 0" } }, el(Spinner, {})) : null,
                        el(SelectControl, {
                            label: "Resultados",
                            value: "",
                            options: [{ label: options.length ? "Seleccionar..." : (query ? "Sin resultados" : "Escribi para buscar..."), value: "" }].concat(options),
                            onChange: function (v) { addDocente(v); },
                            disabled: isResolving || !options.length
                        }),
                        el(
                            "div",
                            { style: { display: "flex", gap: "8px", marginTop: "8px", flexWrap: "wrap" } },
                            el(Button, {
                                variant: "secondary",
                                onClick: function () { setPage(Math.max(1, page - 1)); },
                                disabled: page <= 1 || isResolving
                            }, "Anterior"),
                            el(Button, {
                                variant: "secondary",
                                onClick: function () { setPage(page + 1); },
                                disabled: isResolving || !options.length
                            }, "Cargar mas"),
                            el(Button, {
                                variant: "secondary",
                                onClick: function () { setAttributes({ docenteIds: [] }); },
                                disabled: !docenteIds.length
                            }, "Vaciar")
                        )
                    )
                ),
                el(
                    "div",
                    blockProps,
                    el(RichText, {
                        tagName: level,
                        value: title,
                        allowedFormats: [],
                        onChange: function (v) { setAttributes({ title: v }); },
                        placeholder: "Titulo del grupo..."
                    }),
                    docenteIds.length
                        ? el(
                              "div",
                              { style: { marginTop: "10px" } },
                              el("strong", {}, "Seleccionados"),
                              el(
                                  "ul",
                                  { style: { marginTop: "8px", paddingLeft: "18px" } },
                                  docenteIds.map(function (id) {
                                      var d = selectedMap.get(id);
                                      var label = (d && d.title && d.title.rendered) ? d.title.rendered : ("Docente ID " + id);
                                      return el(
                                          "li",
                                          { key: id, style: { margin: "8px 0" } },
                                          el(
                                              "div",
                                              { style: { display: "flex", gap: "8px", alignItems: "center", flexWrap: "wrap" } },
                                              el("span", {}, label),
                                              el(Button, { variant: "secondary", onClick: function () { moveDocente(id, -1); } }, "↑"),
                                              el(Button, { variant: "secondary", onClick: function () { moveDocente(id, 1); } }, "↓"),
                                              el(Button, { variant: "link", isDestructive: true, onClick: function () { removeDocente(id); } }, "Quitar")
                                          )
                                      );
                                  })
                              )
                          )
                        : el(Notice, { status: "info", isDismissible: false }, "Agrega docentes desde el panel lateral usando el buscador."),
                    ServerSideRender ? el(ServerSideRender, {
                        block: "flacso/docentes-grupo",
                        attributes: attributes
                    }) : null
                )
            );
        },
        save: function () {
            return null;
        }
    });
})(window.wp || {});
