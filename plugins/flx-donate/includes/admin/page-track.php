<?php
/**
 * Flux Donate: tracking page (tracking module). 2.44.0: the list and an item's page are the React screen
 * (packages/modules/src/donate/Track.tsx, FLXB_Admin_Track_Service over GraphQL); this class keeps the
 * address, the redirect of the old page name and the file upload (a form post with the file).
 *
 * @package FLX_Bagis
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class FLXB_Page_Track {

	const SLUG = 'flxb-track';

	public static function init() {
		add_action( 'admin_post_flxb_track_file_upload', array( __CLASS__, 'file_upload' ) );
		/* Links from before 2.11 (page=flxb-kurban). */
		add_action(
			'admin_init',
			function () {
				if ( 'flxb-kurban' === FLXB_Admin::q( 'page' ) ) {
					wp_safe_redirect( FLXB_Admin_Url::page( self::SLUG, array_filter( array( 'item' => absint( FLXB_Admin::q( 'item' ) ) ) ) ) );
					exit;
				}
			}
		);
	}

	/** Address of the list, or of one item. */
	public static function url( $item_id = 0 ) {
		return FLXB_Admin_Url::page( self::SLUG, $item_id ? array( 'item' => (int) $item_id ) : array() );
	}

	public static function render() {
		if ( ! FLXB_Track::can_view() ) {
			FLXB_Admin::deny();
		}
		FLXB_Admin::screen();
	}

	private static function back_item( $id, array $msg ) {
		FLXB_Admin::redirect( self::SLUG, array( 'item' => (int) $id, 'flxb_msg' => $msg[0], 'flxb_err' => $msg[1] ) );
	}

	public static function file_upload() {
		$id = absint( $_POST['id'] ?? 0 ); // phpcs:ignore WordPress.Security.NonceVerification.Missing
		if ( ! FLXB_Track::can() ) {
			FLXB_Admin::deny();
		}
		check_admin_referer( 'flxb_track_file_upload_' . $id );
		if ( empty( $_FILES['file']['tmp_name'] ) ) {
			self::back_item( $id, array( 'error', __( 'Choose a file.', 'flx-donate' ) ) );
		}
		require_once ABSPATH . 'wp-admin/includes/file.php';
		require_once ABSPATH . 'wp-admin/includes/media.php';
		require_once ABSPATH . 'wp-admin/includes/image.php';
		$att = media_handle_upload( 'file', 0 ); // WordPress checks the file type against the allowed list
		$r   = is_wp_error( $att ) ? $att : FLXB_Track::add_file( $id, $att );
		self::back_item( $id, is_wp_error( $r ) ? array( 'error', $r->get_error_message() ) : array( 'saved', __( 'File added.', 'flx-donate' ) ) );
	}
}

class_alias( 'FLXB_Page_Track', 'FLXB_Page_Kurban' ); // before 2.11
