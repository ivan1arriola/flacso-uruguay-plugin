(function (wp) {
	const { registerBlockType } = wp.blocks;
	const { __ } = wp.i18n;
	const { createElement: el, Fragment } = wp.element;
	const { TextControl } = wp.components || {};
	const ServerSideRender = wp.serverSideRender || null;
	const { useBlockProps, InspectorControls } = wp.blockEditor || {};

	registerBlockType('flacso-uruguay/formulario-consulta', {
		title: __('Formulario de Consulta', 'flacso-flacso-formulario-consultas'),
		icon: 'email',
		category: 'flacso',
		attributes: {
			titulo: { type: 'string', default: '' },
		},
		edit: function Edit(props) {
			const { attributes, setAttributes } = props;
			const blockProps = useBlockProps ? useBlockProps() : {};

			const titleControl = TextControl
				? el(TextControl, {
					label: __('Título (opcional)', 'flacso-flacso-formulario-consultas'),
					value: attributes.titulo || '',
					onChange: (val) => setAttributes({ titulo: val }),
					placeholder: __('Ej: Contáctanos', 'flacso-flacso-formulario-consultas'),
				})
				: el('div', null,
					el('label', { style: { display: 'block', marginBottom: '4px', fontWeight: '600' } }, __('Título (opcional)', 'flacso-flacso-formulario-consultas')),
					el('input', {
						type: 'text',
						value: attributes.titulo || '',
						onChange: (event) => setAttributes({ titulo: event.target.value }),
						placeholder: __('Ej: Contáctanos', 'flacso-flacso-formulario-consultas'),
						style: { width: '100%', padding: '8px', border: '1px solid #ccd0d4', borderRadius: '4px' },
					})
				);

			const preview = ServerSideRender
				? el(ServerSideRender, {
					block: 'flacso-uruguay/formulario-consulta',
					attributes,
					className: 'fc-block-ssr',
				})
				: el('div', { className: 'fc-block-preview', style: { border: '1px solid #e6e6e6', borderRadius: '8px', padding: '14px', background: '#fff' } },
					attributes.titulo
						? el('h2', { className: 'h4 mb-3' }, attributes.titulo)
						: el('h2', { className: 'h4 mb-3', style: { color: '#9aa0a6' } }, __('Título (opcional)', 'flacso-flacso-formulario-consultas')),
					el('div', { style: { display: 'grid', gridTemplateColumns: '1fr 1fr', gap: '12px' } },
						el('input', { type: 'text', disabled: true, placeholder: __('Nombre', 'flacso-flacso-formulario-consultas'), style: { padding: '10px', borderRadius: '6px', border: '1px solid #dde1e6' } }),
						el('input', { type: 'text', disabled: true, placeholder: __('Apellido', 'flacso-flacso-formulario-consultas'), style: { padding: '10px', borderRadius: '6px', border: '1px solid #dde1e6' } })
					),
					el('div', { style: { marginTop: '10px' } },
						el('input', { type: 'email', disabled: true, placeholder: __('Correo electrónico', 'flacso-flacso-formulario-consultas'), style: { width: '100%', padding: '10px', borderRadius: '6px', border: '1px solid #dde1e6' } })
					)
				);

			return el(Fragment, null,
				useBlockProps ? el('div', blockProps, titleControl, preview) : el('div', null, titleControl, preview),
				InspectorControls
					? el(InspectorControls, null,
						el('div', { style: { padding: '12px' } }, titleControl)
					)
					: null
			);
		},
		save: function Save() {
			return null; // render dinámico en PHP
		},
	});
})(window.wp);

