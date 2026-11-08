<?php
/**
 * Sistem kontrolü (filter `flx_system_checks`). Dış HTTP isteği yapmaz.
 */

defined( 'ABSPATH' ) || exit;

add_filter(
	'flx_system_checks',
	function ( $checks ) {
		return array_merge( is_array( $checks ) ? $checks : array(), flxst_system_checks() );
	}
);

function flxst_system_checks() {
	global $wpdb;
	$out = array();
	$add = function ( $id, $label, $status, $message, $url = '', $url_label = '' ) use ( &$out ) {
		$c = array(
			'id'      => 'flx-studio/' . $id,
			'source'  => 'Flux Studio',
			'group'   => __( 'Studio', 'flx-studio' ),
			'label'   => $label,
			'status'  => $status,
			'message' => $message,
		);
		if ( $url ) {
			$c['action_url']   = $url;
			$c['action_label'] = $url_label ? $url_label : __( 'Open', 'flx-studio' );
		}
		$out[] = $c;
	};

	$missing = array();
	foreach ( array( 'items', 'folders', 'people', 'refs', 'templates' ) as $t ) {
		$name = flxst_table( $t );
		if ( $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $name ) ) !== $name ) { // phpcs:ignore WordPress.DB
			$missing[] = $name;
		}
	}
	/* translators: %s: table names */
	$add( 'tables', __( 'Database tables', 'flx-studio' ), $missing ? 'error' : 'ok', $missing ? sprintf( __( 'Missing table: %s. Deactivate and reactivate the plugin.', 'flx-studio' ), implode( ', ', $missing ) ) : __( 'Tables are ready.', 'flx-studio' ) );

	$dir = flxst_upload_dir();
	$sub = flxst_ensure_dir( 'out' );
	$ok  = is_dir( $sub ) && wp_is_writable( $sub );
	$add( 'uploads', __( 'File folder', 'flx-studio' ), $ok ? 'ok' : 'error', $ok ? __( 'wp-content/uploads/flx-studio is writable.', 'flx-studio' ) : __( 'wp-content/uploads/flx-studio could not be created or is not writable; references and thumbnails cannot be saved.', 'flx-studio' ) );

	$ai_url = admin_url( 'admin.php?page=flx-ai-settings' );
	if ( ! flxst_ai_ready() ) {
		$add( 'ai', __( 'Image and video backend', 'flx-studio' ), 'error', __( 'Flux AI 0.6.0 or newer is required (image/video generation runs through Flux AI).', 'flx-studio' ), admin_url( 'plugins.php' ), __( 'Plugins', 'flx-studio' ) );
	} else {
		$cat   = flxst_models();
		$ready = array_keys( array_filter( (array) ( $cat['providers'] ?? array() ), fn( $p ) => ! empty( $p['ready'] ) ) );
		$usable = array_filter( (array) ( $cat['models'] ?? array() ), fn( $m ) => ! empty( $m['providers'] ) );
		if ( ! $usable ) {
			$add( 'ai', __( 'Image and video backend', 'flx-studio' ), 'warning', __( 'No model has a key. Enter a fal.ai, EvoLink or other key in Flux AI & MCP › Settings › Images and video.', 'flx-studio' ), $ai_url, __( 'Flux AI', 'flx-studio' ) );
		} else {
			/* translators: 1: model count, 2: providers */
			$add( 'ai', __( 'Image and video backend', 'flx-studio' ), 'ok', sprintf( __( '%1$d models available (%2$s).', 'flx-studio' ), count( $usable ), implode( ', ', $ready ) ), $ai_url, __( 'Flux AI', 'flx-studio' ) );
		}
	}
	$add( 'text', __( 'Enhance and Brainstorm', 'flx-studio' ), flxst_text_ready() ? 'ok' : 'warning', flxst_text_ready() ? __( 'Flux AI text model is ready.', 'flx-studio' ) : __( 'Flux AI text model is not ready; prompt enhancement and ideas are unavailable, generation still works.', 'flx-studio' ), $ai_url, __( 'Flux AI', 'flx-studio' ) );

	$add( 'https', __( 'Reference images', 'flx-studio' ), is_ssl() || 0 === strpos( home_url(), 'https:' ) ? 'ok' : 'info', is_ssl() || 0 === strpos( home_url(), 'https:' ) ? __( 'References are sent to the provider by site URL.', 'flx-studio' ) : __( 'The site is not HTTPS; references are sent inline (base64), making requests larger.', 'flx-studio' ) );
	return $out;
}
