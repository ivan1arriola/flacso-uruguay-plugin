(function(wp){
    var registerBlockType = wp.blocks.registerBlockType;
    var __ = wp.i18n.__;
    var InspectorControls = wp.blockEditor.InspectorControls;
    var PanelBody = wp.components.PanelBody;
    var SelectControl = wp.components.SelectControl;
    var Notice = wp.components.Notice;
    var Spinner = wp.components.Spinner;
    var useEffect = wp.element.useEffect;
    var useState = wp.element.useState;
    var el = wp.element.createElement;

    registerBlockType('flacso-uy/charlas-abiertas-formulario', {
        title: __('Formulario Charlas Abiertas', 'flacso-charlas-abiertas'),
        icon: 'forms',
        category: 'flacso-uruguay',
        attributes: {
            eventoId: { type: 'number', default: 0 }
        },
        edit: function(props){
            var eventoId = props.attributes.eventoId || 0;
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
                        label: item.title && item.title.rendered ? item.title.rendered : ('ID ' + item.id),
                        value: item.id
                    };
                })
            );

            var selected = eventos.find(function(item){ return item.id === eventoId; });
            var selectedLabel = selected && selected.title ? selected.title.rendered : '';

            return [
                el(InspectorControls, { key: 'inspector' },
                    el(PanelBody, { title: __('Configuración del formulario', 'flacso-charlas-abiertas'), initialOpen: true },
                        loading
                            ? el(Spinner)
                            : el(SelectControl, {
                                label: __('Charla (CPT)', 'flacso-charlas-abiertas'),
                                value: eventoId,
                                options: options,
                                onChange: function(val){
                                    props.setAttributes({ eventoId: parseInt(val, 10) || 0 });
                                }
                            })
                    )
                ),
                el('div', { key: 'preview', className: 'components-placeholder' },
                    error ? el(Notice, { status: 'error', isDismissible: false }, error) : null,
                    eventoId > 0
                        ? el('p', null, __('Formulario conectado a charla ID', 'flacso-charlas-abiertas') + ' ' + eventoId + (selectedLabel ? (' (' + selectedLabel + ')') : ''))
                        : el('p', null, __('Selecciona una charla para renderizar el formulario.', 'flacso-charlas-abiertas'))
                )
            ];
        },
        save: function(){
            return null;
        }
    });
})(window.wp);
