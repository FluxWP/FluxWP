( function ( wp ) {
	var el = wp.element.createElement;
	var __ = wp.i18n.__;
	wp.blocks.registerBlockType( 'flx-lang/secici', {
		apiVersion: 3,
		title: __( "Flux Language Switcher", 'flx-lang' ),
		icon: 'translation',
		category: 'theme',
		edit: function () {
			var props = wp.blockEditor.useBlockProps( { className: 'flx-lang flxl-switcher' } );
			return el( 'div', props, el( 'a', { className: 'is-current' }, 'TR' ), ' ', el( 'a', null, 'EN' ) );
		},
		save: function () {
			return null;
		}
	} );
} )( window.wp );
