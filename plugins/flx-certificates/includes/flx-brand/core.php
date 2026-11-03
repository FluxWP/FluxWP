<?php
/**
 * FluxWP brand signature: small FluxWP mark + "Powered by FluxWP" link to fluxwp.com.
 *
 *  - Admin: appended to the footer text of every FluxWP screen (?page=flx*, flx* post types / taxonomies).
 *  - Front end: block `flx/credit` (the FluxWP theme and the presets put it in the footer), shortcode
 *    [flx_credit], and flx_brand_credit() for plugins with their own pages (QR menu).
 *  - Themes that declare add_theme_support( 'flx-credit' ): if the footer template part was rendered
 *    without the block (e.g. edited in the Site Editor), a thin signature bar is added to the footer.
 *
 * Filters:
 *  - flx_brand_show( bool $show, string $context )  false hides the signature (white-label builds).
 *    Contexts: admin, block, shortcode, footer-fallback, or the plugin's own context (qr-menu, ...).
 *  - flx_brand_is_screen( bool $is, WP_Screen|null $screen )  which admin screens are FluxWP screens.
 *  - flx_brand_url( string $url, string $context )
 *
 * Source: FluxWP/fluxwp tools/brand/flx-brand (copy with tools/brand/sync.sh, do not edit the copies).
 *
 * @package flx-brand
 */

defined( 'ABSPATH' ) || exit;

/** Is the signature shown in this context? */
function flx_brand_enabled( $context = '' ) {
	return (bool) apply_filters( 'flx_brand_show', true, (string) $context );
}

/**
 * The few strings of the signature, TR (default) and EN. Kept here instead of a text domain because the
 * module is shared by every FluxWP package and must not depend on which package's translations are loaded.
 */
function flx_brand_str( $key ) {
	$locale = function_exists( 'determine_locale' ) ? determine_locale() : get_locale();
	$tr     = 0 === strpos( (string) $locale, 'tr' );
	$s      = array(
		/* translators: %s: FluxWP */
		'powered' => $tr ? 'Altyapı: %s' : 'Powered by %s',
		'title'   => $tr ? 'FluxWP imzası' : 'FluxWP signature',
		'desc'    => $tr ? 'Küçük FluxWP logosu ve fluxwp.com bağlantısı.' : 'Small FluxWP logo with a link to fluxwp.com.',
		'link'    => $tr ? 'FluxWP web sitesi (yeni sekmede açılır)' : 'FluxWP website (opens in a new tab)',
	);
	$s = apply_filters( 'flx_brand_strings', $s, $tr ? 'tr' : 'en' );
	return isset( $s[ $key ] ) ? $s[ $key ] : '';
}

/** fluxwp.com address. */
function flx_brand_url( $context = '' ) {
	return (string) apply_filters( 'flx_brand_url', 'https://fluxwp.com/', (string) $context );
}

/**
 * FluxWP mark (B · Modul): the F blocks follow the text colour (currentColor), the "added plugin" block stays amber,
 * so it reads on light and dark backgrounds.
 */
function flx_brand_mark( $size = '1.15em' ) {
	$size = esc_attr( $size );
	return '<svg class="flx-credit__mark" width="' . $size . '" height="' . $size . '" viewBox="6 6 52 50" aria-hidden="true" focusable="false" style="display:block;flex:none">'
		. '<g fill="currentColor"><rect x="9" y="9" width="13" height="13" rx="3.5"/><rect x="25.5" y="9" width="13" height="13" rx="3.5"/>'
		. '<rect x="9" y="25.5" width="13" height="13" rx="3.5"/><rect x="25.5" y="25.5" width="13" height="13" rx="3.5"/>'
		. '<rect x="9" y="42" width="13" height="13" rx="3.5"/></g>'
		. '<rect x="44.2" y="6.8" width="13" height="13" rx="3.5" fill="#F59E0B"/></svg>';
}

/**
 * Signature link HTML: mark + "Powered by FluxWP". Empty string when hidden for this context.
 *
 * @param string $context Where it is printed (see flx_brand_show).
 * @param array  $args    size (mark size, CSS length), style (extra inline CSS on the link).
 */
function flx_brand_credit( $context = '', $args = array() ) {
	if ( ! flx_brand_enabled( $context ) ) {
		return '';
	}
	$args  = wp_parse_args( $args, array( 'size' => '1.15em', 'style' => '' ) );
	$label = sprintf( esc_html( flx_brand_str( 'powered' ) ), '<strong style="font-weight:600">FluxWP</strong>' );
	$style = 'display:inline-flex;align-items:center;gap:.4em;color:inherit;text-decoration:none;line-height:1.2;white-space:nowrap;' . $args['style'];

	$css = '';
	if ( empty( $GLOBALS['flx_brand_css'] ) ) {
		$GLOBALS['flx_brand_css'] = true;
		$css = '<style id="flx-credit-css">.flx-credit{opacity:.78;transition:opacity .15s}.flx-credit:hover,.flx-credit:focus-visible{opacity:1}</style>';
	}
	$GLOBALS['flx_brand_printed'] = true;

	return $css . '<a class="flx-credit" href="' . esc_url( flx_brand_url( $context ) ) . '" target="_blank" rel="noopener"'
		. ' title="' . esc_attr( flx_brand_str( 'link' ) ) . '" style="' . esc_attr( $style ) . '"'
		. '>'
		. flx_brand_mark( $args['size'] ) . '<span>' . $label . '</span></a>';
}

/* ---------------------------------------------------------------- Admin */

/** Is the current admin screen a FluxWP screen? */
function flx_brand_is_screen() {
	$is     = false;
	$screen = function_exists( 'get_current_screen' ) ? get_current_screen() : null;
	// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read only, screen detection.
	$page = isset( $_GET['page'] ) ? sanitize_key( wp_unslash( $_GET['page'] ) ) : '';
	if ( '' !== $page && 0 === strpos( $page, 'flx' ) ) {
		$is = true;
	} elseif ( $screen ) {
		foreach ( array( 'post_type', 'taxonomy' ) as $k ) {
			if ( ! empty( $screen->$k ) && 0 === strpos( $screen->$k, 'flx' ) ) {
				$is = true;
			}
		}
		if ( ! $is && ! empty( $screen->id ) && false !== strpos( $screen->id, '_page_flx' ) ) {
			$is = true;
		}
	}
	return (bool) apply_filters( 'flx_brand_is_screen', $is, $screen );
}

add_filter( 'admin_footer_text', 'flx_brand_admin_footer', 100 );

/** Appends the signature to the admin footer text on FluxWP screens. */
function flx_brand_admin_footer( $text ) {
	if ( ! flx_brand_is_screen() ) {
		return $text;
	}
	$credit = flx_brand_credit( 'admin', array( 'style' => 'vertical-align:middle' ) );
	if ( '' === $credit ) {
		return $text;
	}
	$text = trim( (string) $text );
	return ( '' !== $text ? $text . ' <span aria-hidden="true" style="margin:0 .35em;opacity:.5">·</span> ' : '' )
		. '<span id="flx-credit" style="display:inline-block;vertical-align:middle">' . $credit . '</span>';
}

/* ---------------------------------------------------------------- Front end */

add_action( 'init', 'flx_brand_register_block' );
add_shortcode( 'flx_credit', 'flx_brand_shortcode' );
add_filter( 'render_block_core/template-part', 'flx_brand_footer_fallback', 20, 2 );

/** Block flx/credit (server rendered; the editor shows the same HTML). */
function flx_brand_register_block() {
	if ( ! function_exists( 'register_block_type' ) || WP_Block_Type_Registry::get_instance()->is_registered( 'flx/credit' ) ) {
		return;
	}
	wp_register_script( 'flx-credit-editor', false, array( 'wp-blocks', 'wp-element', 'wp-block-editor', 'wp-server-side-render' ), FLX_BRAND_VERSION, true );
	wp_add_inline_script(
		'flx-credit-editor',
		'(function(wp){if(!wp||!wp.blocks||wp.blocks.getBlockType("flx/credit"))return;var el=wp.element.createElement,SSR=wp.serverSideRender;'
		. 'wp.blocks.registerBlockType("flx/credit",{apiVersion:3,icon:el("svg",{viewBox:"6 6 52 50"},el("path",{d:"M12.5 9h6a3.5 3.5 0 0 1 3.5 3.5v6a3.5 3.5 0 0 1-3.5 3.5h-6A3.5 3.5 0 0 1 9 18.5v-6A3.5 3.5 0 0 1 12.5 9zm16.5 0h6a3.5 3.5 0 0 1 3.5 3.5v6a3.5 3.5 0 0 1-3.5 3.5h-6a3.5 3.5 0 0 1-3.5-3.5v-6A3.5 3.5 0 0 1 29 9zM12.5 25.5h6a3.5 3.5 0 0 1 3.5 3.5v6a3.5 3.5 0 0 1-3.5 3.5h-6A3.5 3.5 0 0 1 9 35v-6a3.5 3.5 0 0 1 3.5-3.5zm16.5 0h6a3.5 3.5 0 0 1 3.5 3.5v6a3.5 3.5 0 0 1-3.5 3.5h-6a3.5 3.5 0 0 1-3.5-3.5v-6a3.5 3.5 0 0 1 3.5-3.5zM12.5 42h6a3.5 3.5 0 0 1 3.5 3.5v6a3.5 3.5 0 0 1-3.5 3.5h-6A3.5 3.5 0 0 1 9 51.5v-6A3.5 3.5 0 0 1 12.5 42zm35.2-35.2h6a3.5 3.5 0 0 1 3.5 3.5v6a3.5 3.5 0 0 1-3.5 3.5h-6a3.5 3.5 0 0 1-3.5-3.5v-6a3.5 3.5 0 0 1 3.5-3.5z"})),'
		. 'edit:function(p){return el("div",wp.blockEditor.useBlockProps({style:{pointerEvents:"none"}}),el(SSR,{block:"flx/credit",attributes:p.attributes}));},save:function(){return null;}});})(window.wp);'
	);
	register_block_type(
		'flx/credit',
		array(
			'api_version'     => 3,
			'title'           => flx_brand_str( 'title' ),
			'description'     => flx_brand_str( 'desc' ),
			'category'        => 'theme',
			'keywords'        => array( 'fluxwp', 'credit', 'powered' ),
			'editor_script'   => 'flx-credit-editor',
			'render_callback' => 'flx_brand_render_block',
			'supports'        => array(
				'html'       => false,
				'multiple'   => false,
				'reusable'   => false,
				'color'      => array( 'text' => true, 'background' => false, 'link' => false ),
				'typography' => array( 'fontSize' => true ),
				'spacing'    => array( 'margin' => true ),
			),
		)
	);
}

/** Block render. */
function flx_brand_render_block( $attributes = array(), $content = '' ) {
	$credit = flx_brand_credit( 'block' );
	if ( '' === $credit ) {
		return '';
	}
	$wrap = function_exists( 'get_block_wrapper_attributes' ) ? get_block_wrapper_attributes() : 'class="wp-block-flx-credit"';
	return '<div ' . $wrap . '>' . $credit . '</div>';
}

/** [flx_credit] */
function flx_brand_shortcode() {
	return flx_brand_credit( 'shortcode' );
}

/**
 * Footer fallback: on themes with add_theme_support( 'flx-credit' ), if the footer template part did not
 * print the signature (block removed in the Site Editor), add a thin bar at the end of the footer.
 */
function flx_brand_footer_fallback( $html, $block ) {
	if ( ! current_theme_supports( 'flx-credit' ) || ! empty( $GLOBALS['flx_brand_printed'] ) ) {
		return $html;
	}
	$attrs = isset( $block['attrs'] ) && is_array( $block['attrs'] ) ? $block['attrs'] : array();
	$slug  = isset( $attrs['slug'] ) ? (string) $attrs['slug'] : '';
	$area  = isset( $attrs['area'] ) ? (string) $attrs['area'] : '';
	if ( 'footer' !== $slug && 'footer' !== $area && 0 !== strpos( $slug, 'footer' ) ) {
		return $html;
	}
	$credit = flx_brand_credit( 'footer-fallback', array( 'size' => '1.1em' ) );
	if ( '' === $credit ) {
		return $html;
	}
	$bar = '<div class="flx-credit-bar" style="display:flex;justify-content:center;padding:.75rem 1rem;font-size:.75rem">' . $credit . '</div>';
	$pos = strripos( $html, '</footer>' );
	return false === $pos ? $html . $bar : substr( $html, 0, $pos ) . $bar . substr( $html, $pos );
}
