<?php
/**
 * Flux Donate 2.11: Excel / CSV import (upload → preview and matching → run). 2.44.0: the pages are the React
 * screen (packages/modules/src/donate/Import.tsx, FLXB_Admin_Import_Service over GraphQL); this class keeps the
 * file upload (a form post with the file).
 *
 * @package FLX_Bagis
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class FLXB_Page_Import {

	const SLUG = 'flxb-import';

	public static function init() {
		add_action( 'admin_post_flxb_import_upload', array( __CLASS__, 'upload' ) );
	}

	public static function render() {
		if ( ! flxb_can_manage() ) {
			FLXB_Admin::deny();
		}
		FLXB_Admin::screen();
	}

	public static function upload() {
		if ( ! flxb_can_manage() ) {
			FLXB_Admin::deny();
		}
		check_admin_referer( 'flxb_import_upload' );
		$f = $_FILES['file'] ?? null; // phpcs:ignore WordPress.Security.ValidatedSanitizedInput
		if ( empty( $f['tmp_name'] ) || ! is_uploaded_file( $f['tmp_name'] ) ) {
			FLXB_Admin::redirect( self::SLUG, array( 'flxb_msg' => 'error', 'flxb_err' => __( 'Choose a file.', 'flx-donate' ) ) );
		}
		$name = sanitize_file_name( wp_unslash( (string) $f['name'] ) );
		if ( ! in_array( strtolower( pathinfo( $name, PATHINFO_EXTENSION ) ), array( 'xlsx', 'csv', 'txt', 'tsv' ), true ) ) {
			FLXB_Admin::redirect( self::SLUG, array( 'flxb_msg' => 'error', 'flxb_err' => __( 'Only .xlsx or .csv files.', 'flx-donate' ) ) );
		}
		$imp = FLXB_Import::from_file( $f['tmp_name'], $name );
		if ( is_wp_error( $imp ) ) {
			FLXB_Admin::redirect( self::SLUG, array( 'flxb_msg' => 'error', 'flxb_err' => $imp->get_error_message() ) );
		}
		FLXB_Admin::redirect( self::SLUG, array( 'import' => $imp['id'] ) );
	}
}
