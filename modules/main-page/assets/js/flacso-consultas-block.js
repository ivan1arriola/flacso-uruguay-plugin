( function( wp ) {
	'use strict';

	if ( ! wp || ! wp.blocks ) {
		return;
	}

	var __ = wp.i18n.__;
	var registerBlockType = wp.blocks.registerBlockType;
	var el = wp.element.createElement;
	var InspectorControls = wp.blockEditor ? wp.blockEditor.InspectorControls : wp.editor.InspectorControls;
	var PanelBody = wp.components.PanelBody;
	var ToggleControl = wp.components.ToggleControl;
	var useBlockProps =
		( wp.blockEditor && wp.blockEditor.useBlockProps ) ? wp.blockEditor.useBlockProps :
		( wp.editor && wp.editor.useBlockProps ) ? wp.editor.useBlockProps :
		function() { return {}; };
	var ServerSideRender = wp.serverSideRender;

	registerBlockType( 'flacso-uruguay/consultas-form', {
		title: __( 'Formulario de consultas FLACSO', 'flacso-consultas' ),
		description: __( 'Formulario AJAX de consultas de posgrados FLACSO.', 'flacso-consultas' ),
		icon: 'feedback',
		category: 'widgets',
		attributes: {
			mostrarPreinscripcion: {
				type: 'boolean',
				default: true,
			},
		},
		edit: function( props ) {
			var attrs = props.attributes;
			var blockProps = useBlockProps ? useBlockProps() : {};

			return [
				el(
					InspectorControls,
					{ key: 'controls' },
					el(
						PanelBody,
						{
							title: __( 'Opciones del formulario', 'flacso-consultas' ),
							initialOpen: true,
						},
						el( ToggleControl, {
							label: __( 'Mostrar botón de Preinscripción', 'flacso-consultas' ),
							checked: attrs.mostrarPreinscripcion,
							onChange: function( value ) {
								props.setAttributes( { mostrarPreinscripcion: value } );
							},
						} )
					)
				),
				el(
					'div',
					Object.assign(
						{},
						blockProps,
						{ key: 'content', className: ( blockProps.className || '' ) + ' flacso-consultas-block-preview' }
					),
					el( ServerSideRender, {
						block: 'flacso-uruguay/consultas-form',
						attributes: attrs,
					} )
				),
			];
		},
		save: function() {
			return null;
		},
	} );

	registerBlockType( 'flacso-uruguay/preinscripcion-button', {
		title: __( 'Boton de preinscripcion FLACSO', 'flacso-consultas' ),
		description: __( 'Muestra solo el boton de Preinscripcion 2026.', 'flacso-consultas' ),
		icon: 'button',
		category: 'widgets',
		edit: function() {
			var blockProps = useBlockProps ? useBlockProps() : {};
			return el(
				'div',
				Object.assign(
					{},
					blockProps,
					{ className: ( blockProps.className || '' ) + ' flacso-preinsc-button-block-preview' }
				),
				el( ServerSideRender, {
					block: 'flacso-uruguay/preinscripcion-button',
					attributes: {},
				} )
			);
		},
		save: function() {
			return null;
		},
	} );
} )( window.wp );

