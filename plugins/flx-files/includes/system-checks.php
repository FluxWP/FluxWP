<?php
/**
 * Ortak sistem kontrolü satırları (`flx_system_checks`). Ucuz kontroller, dış istek yok.
 *
 * @package FLX_Files
 */

defined( 'ABSPATH' ) || exit;

add_filter(
	'flx_system_checks_versions',
	static function ( $v ) {
		$v['flx-files'] = FLXFM_VERSION;
		return $v;
	}
);

add_filter(
	'flx_system_checks',
	static function ( $checks ) {
		$checks = is_array( $checks ) ? $checks : array();
		$url    = admin_url( 'admin.php?page=flx-files' );
		$src    = 'Flux Files';

		$data     = flxfm_data_dir();
		$ok       = is_dir( $data ) && wp_is_writable( $data );
		$checks[] = array(
			'id'           => 'flxfm.data',
			'source'       => $src,
			'group'        => 'Config',
			'label'        => __( 'Trash and versions folder', 'flx-files' ),
			'status'       => $ok ? 'ok' : 'error',
			'message'      => $ok
				? __( 'Deleted files and earlier versions are kept in a private folder closed to the web.', 'flx-files' )
				: __( 'The private folder under wp-content cannot be written. Deleting and versioning will fail; check the wp-content permissions.', 'flx-files' ),
			'action_url'   => $url,
			'action_label' => __( 'Open', 'flx-files' ),
		);

		$zip      = class_exists( 'ZipArchive' );
		$checks[] = array(
			'id'      => 'flxfm.zip',
			'source'  => $src,
			'group'   => 'Plugins',
			'label'   => __( 'ZIP support', 'flx-files' ),
			'status'  => $zip ? 'ok' : 'warning',
			'message' => $zip
				? __( 'Folders can be downloaded as ZIP, archived and extracted.', 'flx-files' )
				: __( 'The PHP zip extension is missing; ZIP download, archive and extract are off. Enable "zip" in the hosting PHP extensions.', 'flx-files' ),
		);

		$mode     = flxfm_writable_mode();
		$code     = flxfm_code_edit_allowed();
		$checks[] = array(
			'id'      => 'flxfm.mode',
			'source'  => $src,
			'group'   => 'Security',
			'label'   => __( 'File changes', 'flx-files' ),
			'status'  => $mode ? 'info' : 'warning',
			'message' => ! $mode
				? __( 'DISALLOW_FILE_MODS is on: Flux Files is read-only.', 'flx-files' )
				: ( $code
					? __( 'Only administrators can use Flux Files. wp-config.php, .htaccess and WordPress core folders are protected from deletion.', 'flx-files' )
					: __( 'DISALLOW_FILE_EDIT is on: code files (.php, .htaccess …) cannot be edited or uploaded.', 'flx-files' ) ),
		);

		$mcp      = (bool) flxfm_opt( 'mcp_write' );
		$checks[] = array(
			'id'           => 'flxfm.mcp',
			'source'       => $src,
			'group'        => 'Security',
			'label'        => __( 'AI and MCP file access', 'flx-files' ),
			'status'       => $mcp ? 'warning' : 'ok',
			'message'      => $mcp
				? __( 'External MCP clients can change and delete files. Turn this off when you no longer need it.', 'flx-files' )
				: __( 'AI chat asks before every change; external MCP clients can only read. Secret files are never shared.', 'flx-files' ),
			'action_url'   => $url . '#settings',
			'action_label' => __( 'Settings', 'flx-files' ),
		);

		$days = (int) flxfm_opt( 'trash_days' );
		$at   = (int) get_option( 'flxfm_daily_at', 0 );
		if ( $days && $at && $at < time() - 3 * DAY_IN_SECONDS ) {
			$checks[] = array(
				'id'      => 'flxfm.cron',
				'source'  => $src,
				'group'   => 'Cron',
				'label'   => __( 'Trash cleanup', 'flx-files' ),
				'status'  => 'warning',
				/* translators: %s: time ago */
				'message' => sprintf( __( 'The trash was last cleaned %s. It is also cleaned when an administrator opens Flux Files.', 'flx-files' ), flx_time_ago( $at ) ),
			);
		}
		return $checks;
	}
);
