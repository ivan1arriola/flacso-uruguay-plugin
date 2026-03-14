(function (wp) {
    if (!wp || !wp.blocks || !wp.element) {
        return;
    }

    var registerBlockType = wp.blocks.registerBlockType;
    var el = wp.element.createElement;
    var Fragment = wp.element.Fragment;
    var components = wp.components;
    var blockEditor = wp.blockEditor || wp.editor;
    var __ = wp.i18n && wp.i18n.__ ? wp.i18n.__ : function (value) { return value; };
    var ServerSideRender = wp.serverSideRender || null;

    if (!components || !blockEditor) {
        return;
    }

    var PanelBody = components.PanelBody;
    var TextControl = components.TextControl;
    var Button = components.Button;
    var Notice = components.Notice;
    var InspectorControls = blockEditor.InspectorControls;

    var DEFAULT_CONTACTS = [
        { area: 'Secretaría Académica', persona: 'Lena Fontela', email: 'lfontela@flacso.edu.uy' },
        { area: 'Inscripciones', persona: '', email: 'inscripciones@flacso.edu.uy' },
        { area: 'Asistente Académica Maestría en Educación, Innovación y Tecnología', persona: 'Analía Bombau', email: 'edutic@flacso.edu.uy' },
        { area: 'Asistente Académica Diploma en Género y Políticas de Igualdad', persona: 'Florencia Quartino', email: 'genero@flacso.edu.uy' },
        { area: 'Soporte Web', persona: 'Ivan Arriola', email: 'web@flacso.edu.uy' },
        { area: 'Asistente Académica de la Especialización en Análisis, Producción y Edición de Textos', persona: 'Lourdes García', email: 'producciontextual@flacso.edu.uy' },
        { area: 'Diplomado Superior en Género y Políticas de Igualdad', persona: 'Florencia Quartino', email: 'genero@flacso.edu.uy' },
        { area: 'Asistente Académica de Maestría en Género y Políticas de Igualdad', persona: 'Diva Seluja', email: 'maestriagenero@flacso.edu.uy' },
        { area: 'Asistente Académica Maestría en Educación, Sociedad y Política', persona: 'Alexis Larrosa', email: 'mesyp@flacso.edu.uy' },
        { area: 'Soporte Virtual', persona: '', email: 'soportevirtual@flacso.edu.uy' }
    ];

    function ensureContact(item) {
        return {
            area: item && typeof item.area === 'string' ? item.area : '',
            persona: item && typeof item.persona === 'string' ? item.persona : '',
            email: item && typeof item.email === 'string' ? item.email : ''
        };
    }

    function normalizeContacts(contactos) {
        if (!Array.isArray(contactos) || contactos.length === 0) {
            return DEFAULT_CONTACTS.slice();
        }
        return contactos.map(ensureContact);
    }

    function cloneContacts(contactos) {
        return normalizeContacts(contactos).map(function (item) {
            return {
                area: item.area,
                persona: item.persona,
                email: item.email
            };
        });
    }

    function moveItem(list, fromIndex, toIndex) {
        if (toIndex < 0 || toIndex >= list.length) {
            return list;
        }
        var next = list.slice();
        var item = next.splice(fromIndex, 1)[0];
        next.splice(toIndex, 0, item);
        return next;
    }

    registerBlockType('flacso-uruguay/otros-contactos', {
        title: __('FLACSO - Otros contactos', 'flacso-main-page'),
        icon: 'id-alt',
        category: 'flacso-uruguay',
        description: __('Directorio institucional de contactos.', 'flacso-main-page'),
        supports: {
            html: false,
            align: ['wide', 'full'],
            multiple: true,
            reusable: true
        },
        attributes: {
            title: {
                type: 'string',
                default: __('Otros contactos', 'flacso-main-page')
            },
            contactos: {
                type: 'array',
                default: DEFAULT_CONTACTS
            }
        },
        edit: function (props) {
            var attributes = props.attributes;
            var setAttributes = props.setAttributes;
            var contactos = normalizeContacts(attributes.contactos);

            function setContactos(nextContacts) {
                setAttributes({ contactos: nextContacts.map(ensureContact) });
            }

            function updateContact(index, key, value) {
                var next = cloneContacts(contactos);
                next[index][key] = value;
                setContactos(next);
            }

            function addContact() {
                var next = cloneContacts(contactos);
                next.push({ area: '', persona: '', email: '' });
                setContactos(next);
            }

            function removeContact(index) {
                if (contactos.length <= 1) {
                    return;
                }
                var next = cloneContacts(contactos).filter(function (_, i) {
                    return i !== index;
                });
                setContactos(next);
            }

            function moveUp(index) {
                setContactos(moveItem(cloneContacts(contactos), index, index - 1));
            }

            function moveDown(index) {
                setContactos(moveItem(cloneContacts(contactos), index, index + 1));
            }

            function resetDefaults() {
                setAttributes({ contactos: DEFAULT_CONTACTS.slice() });
            }

            var inspector = el(
                InspectorControls,
                {},
                el(
                    PanelBody,
                    { title: __('Encabezado', 'flacso-main-page'), initialOpen: true },
                    el(TextControl, {
                        label: __('Título', 'flacso-main-page'),
                        value: attributes.title || '',
                        onChange: function (value) {
                            setAttributes({ title: value });
                        }
                    })
                ),
                el(
                    PanelBody,
                    { title: __('Contactos', 'flacso-main-page'), initialOpen: false },
                    contactos.map(function (contacto, index) {
                        return el(
                            'div',
                            { className: 'foc-editor-contact', key: 'foc-editor-contact-' + index },
                            el(
                                'div',
                                { className: 'foc-editor-contact__header' },
                                el('strong', {}, __('Contacto', 'flacso-main-page') + ' ' + (index + 1)),
                                el(
                                    'div',
                                    { className: 'foc-editor-contact__actions' },
                                    el(Button, {
                                        size: 'small',
                                        disabled: index === 0,
                                        onClick: function () { moveUp(index); }
                                    }, __('Subir', 'flacso-main-page')),
                                    el(Button, {
                                        size: 'small',
                                        disabled: index === contactos.length - 1,
                                        onClick: function () { moveDown(index); }
                                    }, __('Bajar', 'flacso-main-page')),
                                    el(Button, {
                                        size: 'small',
                                        isDestructive: true,
                                        disabled: contactos.length <= 1,
                                        onClick: function () { removeContact(index); }
                                    }, __('Eliminar', 'flacso-main-page'))
                                )
                            ),
                            el(TextControl, {
                                label: __('Área', 'flacso-main-page'),
                                value: contacto.area,
                                onChange: function (value) { updateContact(index, 'area', value); }
                            }),
                            el(TextControl, {
                                label: __('Persona', 'flacso-main-page'),
                                value: contacto.persona,
                                onChange: function (value) { updateContact(index, 'persona', value); }
                            }),
                            el(TextControl, {
                                label: __('Email', 'flacso-main-page'),
                                type: 'email',
                                value: contacto.email,
                                onChange: function (value) { updateContact(index, 'email', value); }
                            })
                        );
                    }),
                    el(
                        'div',
                        { className: 'foc-editor-panel-buttons' },
                        el(Button, {
                            variant: 'secondary',
                            onClick: addContact
                        }, __('Agregar contacto', 'flacso-main-page')),
                        el(Button, {
                            variant: 'tertiary',
                            onClick: resetDefaults
                        }, __('Restaurar lista por defecto', 'flacso-main-page'))
                    )
                )
            );

            var preview = ServerSideRender
                ? el(ServerSideRender, {
                    block: props.name,
                    attributes: {
                        title: attributes.title,
                        contactos: contactos
                    }
                })
                : el(Notice, { status: 'warning', isDismissible: false }, __('No se pudo cargar la previsualización.', 'flacso-main-page'));

            return el(
                Fragment,
                {},
                inspector,
                el('div', { className: 'foc-editor-preview-wrap' }, preview)
            );
        },
        save: function () {
            return null;
        }
    });
})(window.wp);
