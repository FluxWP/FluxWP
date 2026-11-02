<?php
/**
 * Flux Forms · Builder skin.
 *
 * A modern look for the EFB form builder without touching its markup or behaviour:
 * - toolbar, element palette (left, sticky, searchable), field settings drawer (right),
 *   form settings with section navigation, modals;
 * - the canvas uses the same skin as the public form (flx-theme.css rescoped to the
 *   canvas) with the site theme's colours and fonts, so editing looks like the live form.
 * Disable with: add_filter( 'flxf_builder_skin', '__return_false' );
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

function flxf_builder_is_screen( $hook ) {
	$hook = (string) $hook;
	if ( false === strpos( $hook, 'Emsfb' ) ) {
		return false;
	}
	/* The builder lives on the panel (edit) and create pages; skip the add-on/settings-only pages. */
	foreach ( array( 'Emsfb_addons', 'Emsfb_setting' ) as $skip ) {
		if ( false !== strpos( $hook, $skip ) ) {
			return false;
		}
	}
	return true;
}

/** Theme palette and fonts (theme.json presets), with the FluxWP defaults as fallback. */
function flxf_builder_tokens_css() {
	$p = array(
		'primary'      => '#0E7A56',
		'primary-dark' => '#0A5A40',
		'ink'          => '#14324A',
		'text'         => '#4A5B66',
		'mint'         => '#EAF5EE',
		'mint-soft'    => '#F5FAF6',
		'line'         => '#DCE9E0',
	);
	$fonts = array(
		'heading' => "Nunito, 'Segoe UI', system-ui, sans-serif",
		'body'    => "'Nunito Sans', 'Segoe UI', system-ui, sans-serif",
	);
	if ( function_exists( 'wp_get_global_settings' ) ) {
		$palette = wp_get_global_settings( array( 'color', 'palette', 'theme' ) );
		foreach ( (array) $palette as $c ) {
			if ( isset( $c['slug'], $c['color'] ) && isset( $p[ $c['slug'] ] ) && preg_match( '/^#[0-9a-fA-F]{3,8}$/', $c['color'] ) ) {
				$p[ $c['slug'] ] = $c['color'];
			}
		}
		$families = wp_get_global_settings( array( 'typography', 'fontFamilies', 'theme' ) );
		foreach ( (array) $families as $f ) {
			if ( isset( $f['slug'], $f['fontFamily'] ) && isset( $fonts[ $f['slug'] ] ) ) {
				$fonts[ $f['slug'] ] = preg_replace( '/[^A-Za-z0-9 ,\'"\-]/', '', $f['fontFamily'] );
			}
		}
	}
	$css = ':root{';
	foreach ( $p as $k => $v ) {
		$css .= '--flx-' . $k . ':' . $v . ';';
	}
	$css .= '--flx-surface:#FFFFFF;';
	$css .= '--wp--preset--font-family--heading:' . $fonts['heading'] . ';';
	$css .= '--wp--preset--font-family--body:' . $fonts['body'] . ';';
	$css .= '}';
	return $css;
}

add_action(
	'admin_enqueue_scripts',
	function ( $hook ) {
		if ( false !== strpos( (string) $hook, 'Emsfb' ) ) {
			wp_enqueue_style( 'flxf-loader', plugins_url( 'assets/loader.css', __FILE__ ), array(), (string) filemtime( __DIR__ . '/assets/loader.css' ) );
		}
		if ( ! flxf_builder_is_screen( $hook ) || ! apply_filters( 'flxf_builder_skin', true ) ) {
			return;
		}
		$dir = __DIR__ . '/assets/';
		$url = plugins_url( 'assets/', __FILE__ );
		$deps = array();
		foreach ( array( 'Emsfb-style-css', 'Emsfb-admin', 'Emsfb-bootstrap', 'efb-modal-system', 'efb-steps-progress-css' ) as $h ) {
			if ( wp_style_is( $h, 'registered' ) ) {
				$deps[] = $h;
			}
		}
		wp_enqueue_style( 'flxf-builder', $url . 'builder.css', $deps, (string) filemtime( $dir . 'builder.css' ) );
		$css = flxf_builder_tokens_css();
		/* The public form skin, rescoped from the form root to the builder canvas and previews. */
		$theme = __DIR__ . '/../assets/flx-theme.css';
		if ( is_readable( $theme ) && apply_filters( 'flxf_theme_style', true ) ) {
			$skin = (string) file_get_contents( $theme ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents
			$skin = preg_replace( '#/\*.*?\*/#s', '', $skin );
			$skin = str_replace( 'body.flx-dark .body_efb', 'body.flxf-never-dark .body_efb', $skin );
			$skin = str_replace( '.body_efb', ':is(#dropZoneEFB, #settingModalEfb-body)', $skin );
			$css .= "\n" . $skin;
		}
		wp_add_inline_style( 'flxf-builder', $css );
		wp_enqueue_script( 'flxf-builder', $url . 'builder.js', array(), (string) filemtime( $dir . 'builder.js' ), true );
		wp_localize_script(
			'flxf-builder',
			'FLXF_BUILDER',
			array(
				'search'       => __( 'Search elements…', 'flx-forms' ),
				'noResult'     => __( 'No element matches your search.', 'flx-forms' ),
				'fieldTitle'   => __( 'Field settings', 'flx-forms' ),
				'formTitle'    => __( 'Form settings', 'flx-forms' ),
				'done'         => __( 'Done', 'flx-forms' ),
				'close'        => __( 'Close', 'flx-forms' ),
				'elements'     => __( 'Elements', 'flx-forms' ),
				'secGeneral'   => __( 'General', 'flx-forms' ),
				'secNotify'    => __( 'Notifications', 'flx-forms' ),
				'secLogic'     => __( 'Conditional logic', 'flx-forms' ),
				'secAdvanced'  => __( 'Thank-you & advanced', 'flx-forms' ),
				'secSms'       => __( 'SMS', 'flx-forms' ),
				'clickToEdit'  => __( 'Click a field to edit it', 'flx-forms' ),
			)
		);
	},
	1000
);

/* Theme fonts (theme.json font faces) so the canvas uses the site's typefaces. */
add_action(
	'admin_head',
	function () {
		$screen = function_exists( 'get_current_screen' ) ? get_current_screen() : null;
		if ( ! $screen || ! flxf_builder_is_screen( $screen->id ) || ! apply_filters( 'flxf_builder_skin', true ) ) {
			return;
		}
		if ( function_exists( 'wp_print_font_faces' ) ) {
			wp_print_font_faces();
		}
	}
);

add_filter(
	'admin_body_class',
	function ( $classes ) {
		$screen = function_exists( 'get_current_screen' ) ? get_current_screen() : null;
		if ( $screen && flxf_builder_is_screen( $screen->id ) && apply_filters( 'flxf_builder_skin', true ) ) {
			$classes .= ' flxf-builder-skin';
		}
		return $classes;
	}
);
