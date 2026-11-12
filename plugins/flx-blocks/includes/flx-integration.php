<?php
/**
 * FluxWP integration: shared system check rows.
 *
 * @package flx-blocks
 */

defined( 'ABSPATH' ) || exit;

add_filter(
	'flx_system_checks',
	function ( $checks ) {
		$checks = is_array( $checks ) ? $checks : array();
		$source = __( 'Flux Block Editor', 'flx-blocks' );
		$panel  = admin_url( 'admin.php?page=flx-blocks' );

		$local    = flxbk_fonts_local();
		$checks[] = array(
			'id'           => 'flxbk.fonts',
			'source'       => $source,
			'group'        => 'Config',
			'label'        => __( 'Google Fonts', 'flx-blocks' ),
			'status'       => $local ? 'ok' : 'warning',
			'message'      => $local
				? __( 'Google Fonts are served from this site; visitors do not connect to Google.', 'flx-blocks' )
				: __( 'Google Fonts load from fonts.googleapis.com, which shares visitor IP addresses with Google. Turn on "Load Google Fonts Locally".', 'flx-blocks' ),
			'action_url'   => $panel,
			'action_label' => __( 'Settings', 'flx-blocks' ),
		);

		$captcha  = function_exists( 'flx_captcha_enabled' );
		$checks[] = array(
			'id'      => 'flxbk.captcha',
			'source'  => $source,
			'group'   => 'Forms',
			'label'   => __( 'Form spam protection', 'flx-blocks' ),
			'status'  => $captcha ? 'ok' : 'info',
			'message' => $captcha
				? __( 'Flux reCAPTCHA is active and can protect Flux Block Editor forms.', 'flx-blocks' )
				: __( 'Flux reCAPTCHA is not active. Forms use the honeypot check only unless a captcha key is set on the form.', 'flx-blocks' ),
		);

		$unregistered = get_option( 'fxt_blocks_unregistered_blocks', array() );
		$off          = is_array( $unregistered ) ? count( $unregistered ) : 0;
		$checks[]     = array(
			'id'           => 'flxbk.blocks',
			'source'       => $source,
			'group'        => 'Plugins',
			'label'        => __( 'Blocks', 'flx-blocks' ),
			'status'       => 'info',
			'message'      => $off
				/* translators: %d: number of turned off blocks. */
				? sprintf( _n( '%d block is turned off.', '%d blocks are turned off.', $off, 'flx-blocks' ), $off )
				: __( 'All blocks are on.', 'flx-blocks' ),
			'action_url'   => $panel,
			'action_label' => __( 'Settings', 'flx-blocks' ),
		);

		return $checks;
	}
);

/**
 * FluxWP theme: map the blocks' --global-palette1..9 colors to the theme presets.
 * Declared on body so the theme's dark mode (which swaps the presets on body.flx-dark) carries over.
 *
 * @return string CSS, empty when the active theme is not a FluxWP theme.
 */
function flxbk_theme_palette_css() {
	if ( ! current_theme_supports( 'flx-theme' ) ) {
		return '';
	}
	$map = apply_filters(
		'flxbk_theme_palette',
		array(
			1 => 'var(--wp--preset--color--primary)',
			2 => 'var(--wp--preset--color--primary-dark, var(--wp--preset--color--primary))',
			3 => 'var(--wp--preset--color--ink)',
			4 => 'var(--wp--preset--color--ink)',
			5 => 'var(--wp--preset--color--text)',
			6 => 'var(--wp--preset--color--text)',
			7 => 'var(--wp--preset--color--line)',
			8 => 'var(--wp--preset--color--mint-soft)',
			9 => 'var(--flx-surface, var(--wp--preset--color--white, #fff))',
		)
	);
	$css = '';
	foreach ( (array) $map as $i => $value ) {
		$css .= '--global-palette' . absint( $i ) . ':' . preg_replace( '/[^a-zA-Z0-9\-\(\)\s,#%.]/', '', (string) $value ) . ';';
	}
	return 'body,.editor-styles-wrapper{' . $css . '}';
}

add_action(
	'enqueue_block_assets',
	function () {
		$css = flxbk_theme_palette_css();
		if ( '' === $css ) {
			return;
		}
		wp_register_style( 'flxbk-theme-palette', false, array(), FLXBK_VERSION );
		wp_enqueue_style( 'flxbk-theme-palette' );
		wp_add_inline_style( 'flxbk-theme-palette', $css );
	}
);

/**
 * Flux Speed "remove unused CSS": keep the classes our scripts add at runtime
 * (sliders, lightboxes, sticky header, forms, menus), so trimmed CSS still styles them.
 */
add_filter(
	'flxp_ps_safelist',
	function ( $patterns ) {
		return array_merge(
			(array) $patterns,
			array(
				'splide*', 'slick-*', 'glightbox*', 'gslide*', 'gcontainer', 'ginner-container', 'gnext', 'gprev', 'gclose', 'gbtn',
				'goverlay', 'gloader', 'gdesc*', 'gvideo*', 'mfp-*', 'plyr*', 'fxb-*', 'fxt-*', 'flx-blocks-*', 'flxbk-*', 'aos-*',
				'active', 'triggered', 'toggle-show', 'show-off-canvas', 'is-*', 'item-*', 'header-is-*', 'child-is-*',
				'sub-menu-*', 'menu-item--*', 'button-primary-disabled',
			)
		);
	}
);

/**
 * FluxWP multilingual sites: translate text nodes and image alt texts of Flux blocks through the
 * theme's flx_t(), like the theme does for core leaf blocks (only while a translation is active).
 */
function flxbk_i18n_render_block( $html, $block ) {
	if ( '' === $html || ! function_exists( 'flx_t' ) || ! function_exists( 'flx_i18n_active' ) || ! flx_i18n_active() ) {
		return $html;
	}
	$html = preg_replace_callback(
		'/>([^<>]*\S[^<>]*)</u',
		function ( $m ) {
			return '>' . flx_t( $m[1] ) . '<';
		},
		$html
	);
	$html = preg_replace_callback(
		'/\b(alt|title|aria-label)="([^"]+)"/u',
		function ( $m ) {
			return $m[1] . '="' . esc_attr( flx_t( $m[2] ) ) . '"';
		},
		$html
	);
	if ( in_array( $block['blockName'], array( 'flx/singlebtn', 'flx/navigation-link' ), true ) && function_exists( 'flx_i18n_url' ) && ( function_exists( 'pll_get_post' ) || has_filter( 'wpml_object_id' ) ) ) {
		$html = preg_replace_callback(
			'/\bhref="(\/[^"]*)"/',
			function ( $m ) {
				return 'href="' . esc_url( flx_i18n_url( $m[1] ) ) . '"';
			},
			$html
		);
	}
	return $html;
}
add_action(
	'init',
	function () {
		$blocks = (array) apply_filters(
			'flxbk_i18n_blocks',
			array( 'flx/advancedheading', 'flx/singlebtn', 'flx/listitem', 'flx/infobox', 'flx/testimonial', 'flx/image', 'flx/countup', 'flx/pane', 'flx/tabs', 'flx/progress-bar', 'flx/table-data', 'flx/navigation-link', 'flx/advancedgallery', 'flx/countdown', 'flx/show-more' )
		);
		foreach ( $blocks as $name ) {
			add_filter( 'render_block_' . $name, 'flxbk_i18n_render_block', 5, 2 );
		}
	},
	20
);
