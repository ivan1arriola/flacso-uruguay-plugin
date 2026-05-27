(function(wp){
    var registerBlockType = wp.blocks.registerBlockType;
    var __ = wp.i18n.__;
    var InspectorControls = wp.blockEditor.InspectorControls;
    var PanelBody = wp.components.PanelBody;
    var SelectControl = wp.components.SelectControl;
    var TextControl = wp.components.TextControl;
    var Notice = wp.components.Notice;
    var Spinner = wp.components.Spinner;
    var useEffect = wp.element.useEffect;
    var useState = wp.element.useState;
    var el = wp.element.createElement;

    function stripHtml(value) {
        return String(value || "").replace(/<[^>]*>/g, "").trim();
    }

    function getStatusLabel(status) {
        var normalized = String(status || "").toLowerCase();
        if (normalized === "publish") {
            return __("Publicada", "flacso-charlas-abiertas");
        }
        if (normalized === "private") {
            return __("Privada", "flacso-charlas-abiertas");
        }
        if (normalized === "future") {
            return __("Programada", "flacso-charlas-abiertas");
        }
        if (normalized === "pending") {
            return __("Pendiente", "flacso-charlas-abiertas");
        }
        return __("Borrador", "flacso-charlas-abiertas");
    }

    function getStatusColors(status) {
        var normalized = String(status || "").toLowerCase();
        if (normalized === "publish") {
            return {
                background: "#e8f7ee",
                color: "#146c43",
                border: "#b7e4c7"
            };
        }
        if (normalized === "private") {
            return {
                background: "#f3f4f6",
                color: "#374151",
                border: "#d1d5db"
            };
        }
        if (normalized === "future") {
            return {
                background: "#eef4ff",
                color: "#1d4ed8",
                border: "#bfd5ff"
            };
        }
        if (normalized === "pending") {
            return {
                background: "#fff7ed",
                color: "#c2410c",
                border: "#fdba74"
            };
        }
        return {
            background: "#fef3c7",
            color: "#92400e",
            border: "#fcd34d"
        };
    }

    function renderBadge(text, colors) {
        return el("span", {
            style: {
                display: "inline-flex",
                alignItems: "center",
                gap: "0.35rem",
                padding: "0.35rem 0.7rem",
                borderRadius: "999px",
                background: colors.background,
                color: colors.color,
                border: "1px solid " + colors.border,
                fontSize: "0.78rem",
                fontWeight: 700,
                lineHeight: 1.2
            }
        }, text);
    }

    function renderTextField(label, placeholder, isFullWidth) {
        return el("div", {
            className: "flacso-form-group" + (isFullWidth ? " flacso-form-group-full" : "")
        }, [
            el("label", { key: "label" }, label),
            el("input", {
                key: "input",
                type: "text",
                value: "",
                placeholder: placeholder,
                disabled: true,
                readOnly: true
            })
        ]);
    }

    function renderEmailField() {
        return el("div", {
            className: "flacso-form-group flacso-form-group-full"
        }, [
            el("label", { key: "label" }, __("Correo *", "flacso-charlas-abiertas")),
            el("input", {
                key: "input",
                type: "email",
                value: "",
                placeholder: "correo@ejemplo.com",
                disabled: true,
                readOnly: true
            })
        ]);
    }

    function renderPhoneField() {
        return el("div", {
            className: "flacso-form-group"
        }, [
            el("label", { key: "label" }, __("Número de teléfono (Opcional)", "flacso-charlas-abiertas")),
            el("input", {
                key: "input",
                type: "tel",
                value: "",
                placeholder: "+598 99 123 456",
                disabled: true,
                readOnly: true
            })
        ]);
    }

    function renderAttendanceField() {
        return el("div", {
            className: "flacso-form-group flacso-form-group-full"
        }, [
            el("label", { key: "label" }, __("Modalidad de asistencia *", "flacso-charlas-abiertas")),
            el("select", {
                key: "select",
                disabled: true,
                value: ""
            }, [
                el("option", { key: "empty", value: "" }, __("Seleccionar", "flacso-charlas-abiertas")),
                el("option", { key: "virtual", value: "virtual" }, __("Virtual", "flacso-charlas-abiertas")),
                el("option", { key: "presencial", value: "presencial" }, __("Presencial", "flacso-charlas-abiertas"))
            ])
        ]);
    }

    function buildPreviewFields(variant) {
        var fields = [];

        if (variant === "nombre_apellido" || variant === "nombre_apellido_sin_telefono") {
            fields.push(
                renderTextField(
                    __("Nombre y apellido *", "flacso-charlas-abiertas"),
                    __("Ej. Juan Pérez", "flacso-charlas-abiertas"),
                    true
                )
            );
        } else {
            fields.push(
                renderTextField(
                    __("Nombre *", "flacso-charlas-abiertas"),
                    __("Ej. Juan", "flacso-charlas-abiertas"),
                    false
                )
            );
            fields.push(
                renderTextField(
                    __("Apellido *", "flacso-charlas-abiertas"),
                    __("Ej. Pérez", "flacso-charlas-abiertas"),
                    false
                )
            );
        }

        fields.push(renderEmailField());
        fields.push(
            renderTextField(
                __("País de residencia", "flacso-charlas-abiertas"),
                __("Ej. Uruguay", "flacso-charlas-abiertas"),
                false
            )
        );

        if (variant !== "nombre_apellido" && variant !== "nombre_apellido_sin_telefono") {
            fields.push(
                renderTextField(
                    __("Profesión", "flacso-charlas-abiertas"),
                    __("Ej. Docente, Estudiante", "flacso-charlas-abiertas"),
                    false
                )
            );
        }

        fields.push(
            renderTextField(
                __("Institución", "flacso-charlas-abiertas"),
                __("Ej. Universidad, FLACSO", "flacso-charlas-abiertas"),
                false
            )
        );
        if (variant !== "nombre_apellido_sin_telefono") {
            fields.push(renderPhoneField());
        }
        fields.push(renderAttendanceField());

        return fields;
    }

    function renderPreview(props, selected) {
        var eventoId = props.attributes.eventoId || 0;
        var variant = props.attributes.variant || "estandar";
        var heading = props.attributes.heading || "";
        var selectedLabel = stripHtml(selected && selected.title && selected.title.rendered ? selected.title.rendered : "");
        var resolvedHeading = heading || (selectedLabel
            ? __("Inscripción a ", "flacso-charlas-abiertas") + selectedLabel
            : __("Inscripción a la charla seleccionada", "flacso-charlas-abiertas"));
        var statusColors = getStatusColors(selected && selected.status);

        if (!eventoId) {
            return el("div", {
                style: {
                    border: "1px dashed #cbd5e1",
                    borderRadius: "18px",
                    background: "linear-gradient(180deg, #fcfdff 0%, #f7f9fc 100%)",
                    padding: "2rem 1.5rem",
                    textAlign: "center"
                }
            }, [
                el("div", {
                    key: "title",
                    style: {
                        fontSize: "1.15rem",
                        fontWeight: 700,
                        color: "#1d3a72",
                        marginBottom: "0.45rem"
                    }
                }, __("Selecciona una charla para ver la vista previa del formulario", "flacso-charlas-abiertas")),
                el("p", {
                    key: "text",
                    style: {
                        margin: 0,
                        color: "#64748b",
                        fontSize: "0.96rem"
                    }
                }, __("Cuando elijas una charla, aquí se mostrará una maqueta visual del formulario con su encabezado y campos.", "flacso-charlas-abiertas"))
            ]);
        }

        return el("div", {
            style: {
                display: "grid",
                gap: "1rem"
            }
        }, [
            el("div", {
                key: "meta",
                style: {
                    display: "flex",
                    flexWrap: "wrap",
                    gap: "0.6rem",
                    alignItems: "center"
                }
            }, [
                renderBadge(
                    __("Charla ID ", "flacso-charlas-abiertas") + eventoId,
                    {
                        background: "#eef4ff",
                        color: "#1d4ed8",
                        border: "#bfd5ff"
                    }
                ),
                renderBadge(
                    variant === "nombre_apellido_sin_telefono"
                        ? __("Alternativo 2", "flacso-charlas-abiertas")
                        : (variant === "nombre_apellido"
                            ? __("Versión alternativa", "flacso-charlas-abiertas")
                            : __("Versión estándar", "flacso-charlas-abiertas")),
                    {
                        background: "#f4f0ff",
                        color: "#6d28d9",
                        border: "#ddd6fe"
                    }
                ),
                renderBadge(
                    getStatusLabel(selected && selected.status),
                    statusColors
                )
            ]),
            el("div", {
                key: "preview-card",
                className: "flacso-charla-form-wrapper",
                style: {
                    margin: 0,
                    maxWidth: "100%",
                    boxShadow: "0 16px 40px rgba(15, 26, 45, 0.07)",
                    borderColor: "#d8e0ea"
                }
            }, [
                el("div", {
                    key: "connected",
                    style: {
                        marginBottom: "1.2rem",
                        padding: "0.9rem 1rem",
                        borderRadius: "12px",
                        background: "#f8fbff",
                        border: "1px solid #d7e7ff",
                        color: "#1e3a5f",
                        fontSize: "0.9rem",
                        lineHeight: 1.45
                    }
                }, [
                    el("strong", { key: "label" }, __("Conectado a:", "flacso-charlas-abiertas") + " "),
                    el("span", { key: "value" }, selectedLabel || __("Charla seleccionada", "flacso-charlas-abiertas"))
                ]),
                el("form", {
                    key: "form",
                    className: "flacso-charla-form",
                    onSubmit: function(e) {
                        e.preventDefault();
                    }
                }, [
                    el("h3", {
                        key: "heading",
                        className: "flacso-form-title"
                    }, resolvedHeading),
                    el("div", {
                        key: "grid",
                        className: "flacso-form-grid"
                    }, buildPreviewFields(variant)),
                    el("div", {
                        key: "actions",
                        className: "flacso-form-actions"
                    }, el("button", {
                        type: "button",
                        className: "flacso-btn-submit",
                        style: {
                            cursor: "default",
                            opacity: 0.94
                        }
                    }, [
                        el("span", { key: "text" }, __("Enviar inscripción", "flacso-charlas-abiertas")),
                        el("span", {
                            key: "icon",
                            className: "flacso-btn-icon",
                            style: {
                                fontSize: "1rem",
                                lineHeight: 1
                            }
                        }, "↗")
                    ]))
                ]),
                el("div", {
                    key: "note",
                    style: {
                        marginTop: "1rem",
                        paddingTop: "1rem",
                        borderTop: "1px solid #e2e8f0",
                        color: "#64748b",
                        fontSize: "0.85rem"
                    }
                }, __("Vista previa de editor. Los campos son ilustrativos y no envían datos desde aquí.", "flacso-charlas-abiertas"))
            ])
        ]);
    }

    registerBlockType('flacso-uy/charlas-abiertas-formulario', {
        title: __('Formulario Charlas Abiertas', 'flacso-charlas-abiertas'),
        icon: 'forms',
        category: 'flacso-uruguay',
        attributes: {
            eventoId: { type: 'number', default: 0 },
            variant: { type: 'string' },
            heading: { type: 'string' }
        },
        edit: function(props){
            var eventoId = props.attributes.eventoId || 0;
            var variant = props.attributes.variant || 'estandar';
            var heading = props.attributes.heading || '';
            var _a = useState([]), eventos = _a[0], setEventos = _a[1];
            var _b = useState(true), loading = _b[0], setLoading = _b[1];
            var _c = useState(''), error = _c[0], setError = _c[1];

            useEffect(function(){
                var editPath = '/wp/v2/charla-abierta?per_page=100&context=edit&status[]=publish&status[]=draft&status[]=private&_fields=id,title,status';
                var publicPath = '/wp/v2/charla-abierta?per_page=100&status=publish&_fields=id,title,status';

                wp.apiFetch({ path: editPath })
                    .then(function(items){
                        setEventos(items || []);
                        setLoading(false);
                    })
                    .catch(function(){
                        wp.apiFetch({ path: publicPath })
                            .then(function(items){
                                setEventos(items || []);
                                setLoading(false);
                            })
                            .catch(function(){
                                setError(__('No se pudieron cargar las charlas.', 'flacso-charlas-abiertas'));
                                setLoading(false);
                            });
                    });
            }, []);

            var options = [{ label: __('Seleccionar charla', 'flacso-charlas-abiertas'), value: 0 }].concat(
                eventos.map(function(item){
                    return {
                        label: item.title && item.title.rendered ? stripHtml(item.title.rendered) : ('ID ' + item.id),
                        value: item.id
                    };
                })
            );

            var selected = eventos.find(function(item){ return item.id === eventoId; });

            return [
                el(InspectorControls, { key: 'inspector' },
                    el(PanelBody, { title: __('Configuración del formulario', 'flacso-charlas-abiertas'), initialOpen: true },
                        loading
                            ? el(Spinner)
                            : [
                                el(SelectControl, {
                                    key: 'evento',
                                    label: __('Charla (CPT)', 'flacso-charlas-abiertas'),
                                    value: eventoId,
                                    options: options,
                                    onChange: function(val){
                                        props.setAttributes({ eventoId: parseInt(val, 10) || 0 });
                                    }
                                }),
                                el(SelectControl, {
                                    key: 'variant',
                                    label: __('Versión del formulario', 'flacso-charlas-abiertas'),
                                    value: variant,
                                    options: [
                                        { label: __('Estándar: nombre, apellido y profesión', 'flacso-charlas-abiertas'), value: 'estandar' },
                                        { label: __('Alternativa: nombre y apellido en un solo campo', 'flacso-charlas-abiertas'), value: 'nombre_apellido' },
                                        { label: __('Alternativo 2: nombre y apellido en un solo campo, sin teléfono', 'flacso-charlas-abiertas'), value: 'nombre_apellido_sin_telefono' }
                                    ],
                                    onChange: function(val){
                                        props.setAttributes({ variant: val || 'estandar' });
                                    }
                                }),
                                el(TextControl, {
                                    key: 'heading',
                                    label: __('Encabezado del formulario', 'flacso-charlas-abiertas'),
                                    value: heading,
                                    help: __('Si lo dejas vacío, se usará "Inscripción a {título de la charla}".', 'flacso-charlas-abiertas'),
                                    onChange: function(val){
                                        props.setAttributes({ heading: val || '' });
                                    }
                                })
                            ]
                    )
                ),
                el('div', {
                    key: 'preview-shell',
                    style: {
                        padding: "0.25rem 0"
                    }
                }, [
                    error ? el(Notice, {
                        key: "error",
                        status: 'error',
                        isDismissible: false
                    }, error) : null,
                    renderPreview(props, selected)
                ])
            ];
        },
        save: function(){
            return null;
        }
    });
})(window.wp);
