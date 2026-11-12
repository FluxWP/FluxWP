<?php
/**
 * Yönetim ekranı: Flux Files menüsü, uygulama betiği, çeviri sözlüğü.
 *
 * @package FLX_Files
 */

defined( 'ABSPATH' ) || exit;

add_action(
	'admin_menu',
	static function () {
		$hook = add_menu_page( 'Flux Files', 'Flux Files', 'flxfm_manage', 'flx-files', 'flxfm_render_page', 'none', 76.4 );
		add_action( 'load-' . $hook, 'flxfm_page_load' );
	}
);

add_action(
	'admin_head',
	static function () {
		echo '<style>#toplevel_page_flx-files .wp-menu-image{background:url(' . esc_url( FLXFM_URL . 'assets/icon.svg' ) . ') center/20px 20px no-repeat}</style>';
	}
);

/** Sayfa yüklenirken: betikler, emoji kapalı. */
function flxfm_page_load() {
	remove_action( 'admin_print_scripts', 'print_emoji_detection_script' );
	remove_action( 'admin_print_styles', 'print_emoji_styles' );
	add_filter(
		'admin_body_class',
		static function ( $c ) {
			return $c . ' flxfm-screen';
		}
	);
	add_action( 'admin_enqueue_scripts', 'flxfm_enqueue' );
}

/** Parça boyutu: sunucunun izin verdiği yükleme sınırının biraz altı, en fazla 8 MB. */
function flxfm_chunk_size() {
	$max = (int) wp_max_upload_size();
	$max = $max > 0 ? $max : 2097152;
	return (int) max( 262144, min( 8388608, $max - 65536 ) );
}

/** Betik ve stiller. */
function flxfm_enqueue() {
	$cm = wp_enqueue_code_editor(
		array(
			'type'       => 'text/x-php',
			'codemirror' => array( 'lint' => false, 'gutters' => array() ),
		)
	);
	wp_enqueue_style( 'flx-files', FLXFM_URL . 'assets/app.css', array( 'code-editor' ), FLXFM_VERSION );
	wp_enqueue_script( 'flx-files', FLXFM_URL . 'assets/app.js', array( 'code-editor' ), FLXFM_VERSION, true );
	$s      = flxfm_settings();
	$up     = wp_get_upload_dir();
	$host   = (string) wp_parse_url( home_url(), PHP_URL_HOST );
	$cmconf = is_array( $cm ) && isset( $cm['codemirror'] ) ? $cm['codemirror'] : array();
	unset( $cmconf['mode'], $cmconf['lint'], $cmconf['gutters'] );
	$open = isset( $_GET['open'] ) ? flxfm_clean( sanitize_text_field( wp_unslash( $_GET['open'] ) ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification
	wp_localize_script(
		'flx-files',
		'FLXFM',
		array(
			'rest'     => esc_url_raw( rest_url( 'flx-files/v1/' ) ),
			'nonce'    => wp_create_nonce( 'wp_rest' ),
			'file'     => add_query_arg(
				array(
					'action'   => 'flxfm_file',
					'_wpnonce' => wp_create_nonce( 'flxfm_file' ),
				),
				admin_url( 'admin-post.php' )
			),
			'host'     => $host ? $host : get_bloginfo( 'name' ),
			'rootName' => basename( flxfm_root() ),
			'abs'      => flxfm_root(),
			'home'     => trailingslashit( site_url() ),
			'uploads'  => array(
				'rel' => flxfm_uploads_rel(),
				'url' => untrailingslashit( $up['baseurl'] ),
			),
			'writable' => flxfm_writable_mode(),
			'ai'       => flxfm_ai_ready(),
			'chunk'    => flxfm_chunk_size(),
			'cm'       => $cmconf,
			'open'     => is_wp_error( $open ) ? '' : $open,
			's'        => array(
				'show_hidden' => (int) $s['show_hidden'],
				'trash_days'  => (int) $s['trash_days'],
				'versions'    => (int) $s['versions'],
				'mcp_write'   => (int) $s['mcp_write'],
				'ai_code'     => (int) $s['ai_code'],
				'shared'      => array_values( (array) $s['shared'] ),
			),
			'i18n'     => flxfm_js_strings(),
		)
	);
}

/** Sayfa. */
function flxfm_render_page() {
	if ( ! current_user_can( 'flxfm_manage' ) ) {
		wp_die( esc_html__( 'You are not allowed to do this.', 'flx-files' ) );
	}
	echo '<div class="wrap flxfm-wrap"><h1 class="screen-reader-text">Flux Files</h1><div id="flxfm-app" class="flxfm" data-mode="browse"><div class="flxfm-boot">' . esc_html__( 'Loading…', 'flx-files' ) . '</div></div><noscript>' . esc_html__( 'Flux Files needs JavaScript.', 'flx-files' ) . '</noscript></div>';
}

/** Arayüz metinleri. `_1` ekli anahtar tekil biçimdir (1 öğe). */
function flxfm_js_strings() {
	return array(
		'error'               => __( 'Something went wrong. Try again.', 'flx-files' ),
		'copied'              => __( 'Copied.', 'flx-files' ),
		'location'            => __( 'Location', 'flx-files' ),
		'root'                => __( 'Site root', 'flx-files' ),
		'browser'             => __( 'Browser', 'flx-files' ),
		'editor'              => __( 'Editor', 'flx-files' ),
		'upload'              => __( 'Upload', 'flx-files' ),
		'new'                 => __( 'New', 'flx-files' ),
		'more'                => __( 'More', 'flx-files' ),
		'readonly_mode'       => __( 'Read-only: file changes are disabled on this site (DISALLOW_FILE_MODS).', 'flx-files' ),
		'back'                => __( 'Back', 'flx-files' ),
		'forward'             => __( 'Forward', 'flx-files' ),
		'folder'              => __( 'Folder', 'flx-files' ),
		'search'              => __( 'Search', 'flx-files' ),
		'search_here'         => __( 'Search in this folder…', 'flx-files' ),
		'search_content_hint' => __( 'Also search inside text and code files', 'flx-files' ),
		'in_content'          => __( 'Content', 'flx-files' ),
		'toggle_view'         => __( 'List or grid', 'flx-files' ),
		'refresh'             => __( 'Refresh', 'flx-files' ),
		'drop_here'           => __( 'Drop files or folders to upload', 'flx-files' ),
		'toggle_tree'         => __( 'Show or hide files', 'flx-files' ),
		'editor_empty'        => __( 'Choose a file on the left to edit it.', 'flx-files' ),
		'close'               => __( 'Close', 'flx-files' ),
		'trash'               => __( 'Trash', 'flx-files' ),
		'activity'            => __( 'Activity', 'flx-files' ),
		/* translators: %s: search words */
		'search_results'      => __( 'Results for "%s"', 'flx-files' ),
		'sort_name_az'        => __( 'Name · A to Z', 'flx-files' ),
		'sort_name_za'        => __( 'Name · Z to A', 'flx-files' ),
		'sort_new'            => __( 'Modified · newest', 'flx-files' ),
		'sort_old'            => __( 'Modified · oldest', 'flx-files' ),
		'sort_big'            => __( 'Size · largest', 'flx-files' ),
		'sort_small'          => __( 'Size · smallest', 'flx-files' ),
		'sort_type'           => __( 'Type', 'flx-files' ),
		'no_results'          => __( 'No results', 'flx-files' ),
		'empty_folder'        => __( 'This folder is empty', 'flx-files' ),
		'empty_hint'          => __( 'Drag files here or use Upload.', 'flx-files' ),
		/* translators: %s: number of items */
		'items_n'             => __( '%s items', 'flx-files' ),
		'items_n_1'           => __( '1 item', 'flx-files' ),
		'actions'             => __( 'Actions', 'flx-files' ),
		'col_name'            => __( 'Name', 'flx-files' ),
		'col_where'           => __( 'Folder', 'flx-files' ),
		'col_modified'        => __( 'Modified', 'flx-files' ),
		'col_size'            => __( 'Size', 'flx-files' ),
		'col_perms'           => __( 'Permissions', 'flx-files' ),
		'col_origin'          => __( 'Original location', 'flx-files' ),
		'col_deleted'         => __( 'Deleted', 'flx-files' ),
		'search_partial'      => __( 'Showing the first results only. Search in a smaller folder for more.', 'flx-files' ),
		'locked_hint'         => __( 'Protected: cannot be moved, renamed or deleted', 'flx-files' ),
		'sensitive_hint'      => __( 'Contains secrets: never shared with AI', 'flx-files' ),
		'core_hint'           => __( 'WordPress core file', 'flx-files' ),
		/* translators: %s: number of selected items */
		'selected_n'          => __( '%s selected', 'flx-files' ),
		/* translators: %s: number of items on the clipboard */
		'clip_n'              => __( '%s on clipboard', 'flx-files' ),
		'storage'             => __( 'Storage', 'flx-files' ),
		'storage_measuring'   => __( 'measuring…', 'flx-files' ),
		/* translators: %s: used disk space, e.g. 12.4 GB */
		'storage_used'        => __( '%s used', 'flx-files' ),
		/* translators: 1: number of files, 2: time ago */
		'storage_tip'         => __( 'Hosting account: %1$s files, measured %2$s', 'flx-files' ),
		'cut'                 => __( 'Cut', 'flx-files' ),
		'copy'                => __( 'Copy', 'flx-files' ),
		'paste'               => __( 'Paste', 'flx-files' ),
		'download'            => __( 'Download', 'flx-files' ),
		'download_zip'        => __( 'Download as ZIP', 'flx-files' ),
		'delete'              => __( 'Delete', 'flx-files' ),
		'clear_selection'     => __( 'Clear selection', 'flx-files' ),
		'upload_files'        => __( 'Upload files', 'flx-files' ),
		'upload_folder'       => __( 'Upload folder', 'flx-files' ),
		'settings'            => __( 'Settings', 'flx-files' ),
		'new_file'            => __( 'New file', 'flx-files' ),
		'new_folder'          => __( 'New folder', 'flx-files' ),
		'folder_info'         => __( 'Folder info', 'flx-files' ),
		'file_info'           => __( 'File info', 'flx-files' ),
		'copy_path'           => __( 'Copy path', 'flx-files' ),
		'path_relative'       => __( 'Path from the site root', 'flx-files' ),
		'path_server'         => __( 'Full server path', 'flx-files' ),
		'path_url'            => __( 'Web address (URL)', 'flx-files' ),
		'open'                => __( 'Open', 'flx-files' ),
		'edit'                => __( 'Edit', 'flx-files' ),
		'preview'             => __( 'Preview', 'flx-files' ),
		'archive'             => __( 'Archive…', 'flx-files' ),
		'archive_hint'        => __( 'The ZIP file is created in this folder.', 'flx-files' ),
		'extract'             => __( 'Extract', 'flx-files' ),
		'duplicate'           => __( 'Duplicate', 'flx-files' ),
		'copy_or_move'        => __( 'Copy or move', 'flx-files' ),
		'move_to'             => __( 'Move to…', 'flx-files' ),
		'copy_to'             => __( 'Copy to…', 'flx-files' ),
		'show_in_folder'      => __( 'Show in folder', 'flx-files' ),
		'permissions'         => __( 'Permissions…', 'flx-files' ),
		'share_on_site'       => __( 'Share on site', 'flx-files' ),
		'unshare'             => __( 'Stop sharing', 'flx-files' ),
		'versions'            => __( 'Version history', 'flx-files' ),
		'rename'              => __( 'Rename', 'flx-files' ),
		/* translators: %s: number of items */
		'cut_n'               => __( '%s items cut. Open a folder and paste.', 'flx-files' ),
		'cut_n_1'             => __( 'Cut. Open a folder and paste.', 'flx-files' ),
		/* translators: %s: number of items */
		'copied_n'            => __( '%s items copied. Open a folder and paste.', 'flx-files' ),
		'copied_n_1'          => __( 'Copied. Open a folder and paste.', 'flx-files' ),
		'renamed'             => __( 'Renamed.', 'flx-files' ),
		/* translators: %s: file name */
		'trash_one'           => __( 'Move "%s" to the trash?', 'flx-files' ),
		/* translators: %s: number of items */
		'trash_many'          => __( 'Move %s items to the trash?', 'flx-files' ),
		'trash_hint'          => __( 'You can restore it from the trash.', 'flx-files' ),
		/* translators: %s: number of items */
		'moved_to_trash'      => __( '%s items moved to the trash.', 'flx-files' ),
		'moved_to_trash_1'    => __( 'Moved to the trash.', 'flx-files' ),
		'some_failed'         => __( 'Some items could not be processed', 'flx-files' ),
		'conflict_title'      => __( 'Already exists', 'flx-files' ),
		/* translators: %s: number of items */
		'conflict_msg'        => __( '%s items already exist in the target folder. Replace them? The replaced files go to the trash.', 'flx-files' ),
		'overwrite'           => __( 'Overwrite', 'flx-files' ),
		'skip'                => __( 'Skip', 'flx-files' ),
		'skipped'             => __( 'Skipped', 'flx-files' ),
		/* translators: %s: number of items */
		'moved_n'             => __( '%s items moved.', 'flx-files' ),
		'moved_n_1'           => __( 'Moved.', 'flx-files' ),
		/* translators: %s: number of items */
		'copied_done_n'       => __( '%s items copied.', 'flx-files' ),
		'copied_done_n_1'     => __( 'Copied.', 'flx-files' ),
		'folder_name'         => __( 'Folder name', 'flx-files' ),
		'create'              => __( 'Create', 'flx-files' ),
		'created'             => __( 'Created.', 'flx-files' ),
		'working'             => __( 'Working…', 'flx-files' ),
		'archived'            => __( 'Archive created.', 'flx-files' ),
		/* translators: %s: archive name */
		'extract_msg'         => __( '"%s" will be extracted into a new folder next to it.', 'flx-files' ),
		/* translators: %s: number of files */
		'extracted'           => __( 'Extracted %s files.', 'flx-files' ),
		'shared_title'        => __( 'Shared on the site', 'flx-files' ),
		'shared_msg'          => __( 'Visitors can browse and download this folder where you add the shortcode below (copied). Add it to any page or post.', 'flx-files' ),
		'unshared'            => __( 'No longer shared.', 'flx-files' ),
		'type'                => __( 'Type', 'flx-files' ),
		'calculate'           => __( 'Calculate', 'flx-files' ),
		'calculating'         => __( 'Calculating…', 'flx-files' ),
		/* translators: %s: number of files */
		'files_n'             => __( '%s files', 'flx-files' ),
		'files_n_1'           => __( '1 file', 'flx-files' ),
		'partial'             => __( 'partial', 'flx-files' ),
		'not_writable'        => __( 'Not writable', 'flx-files' ),
		'notes'               => __( 'Notes', 'flx-files' ),
		'kind_dir'            => __( 'Folder', 'flx-files' ),
		'kind_image'          => __( 'Image', 'flx-files' ),
		'kind_video'          => __( 'Video', 'flx-files' ),
		'kind_audio'          => __( 'Audio', 'flx-files' ),
		'kind_pdf'            => __( 'PDF document', 'flx-files' ),
		'kind_archive'        => __( 'Archive', 'flx-files' ),
		'kind_code'           => __( 'Code', 'flx-files' ),
		'kind_doc'            => __( 'Document', 'flx-files' ),
		'kind_text'           => __( 'Text', 'flx-files' ),
		'kind_font'           => __( 'Font', 'flx-files' ),
		'kind_file'           => __( 'File', 'flx-files' ),
		'perm_owner'          => __( 'Owner', 'flx-files' ),
		'perm_group'          => __( 'Group', 'flx-files' ),
		'perm_public'         => __( 'Everyone', 'flx-files' ),
		'perm_read'           => __( 'Read', 'flx-files' ),
		'perm_write'          => __( 'Write', 'flx-files' ),
		'perm_exec'           => __( 'Execute', 'flx-files' ),
		'perm_public_write'   => __( 'Write access for everyone is not allowed.', 'flx-files' ),
		'perm_numeric'        => __( 'Numeric value', 'flx-files' ),
		'perm_hint'           => __( 'Usual values: 644 for files, 755 for folders.', 'flx-files' ),
		'cancel'              => __( 'Cancel', 'flx-files' ),
		'save'                => __( 'Save', 'flx-files' ),
		'saved'               => __( 'Saved.', 'flx-files' ),
		'choose_here'         => __( 'Choose this folder', 'flx-files' ),
		'loading'             => __( 'Loading…', 'flx-files' ),
		'no_subfolders'       => __( 'No subfolders', 'flx-files' ),
		'previous'            => __( 'Previous', 'flx-files' ),
		'next'                => __( 'Next', 'flx-files' ),
		/* translators: %s: number of versions kept */
		'versions_hint'       => __( 'Each save keeps the previous content. The last %s versions of a file are kept.', 'flx-files' ),
		'no_versions'         => __( 'No earlier versions yet.', 'flx-files' ),
		'diff_vs_current'     => __( 'Difference from the current file', 'flx-files' ),
		'restore_version'     => __( 'Restore this version', 'flx-files' ),
		'restore_version_msg' => __( 'The file goes back to this version. The current content is kept as a new version.', 'flx-files' ),
		'restored'            => __( 'Restored.', 'flx-files' ),
		'via_panel'           => __( 'Panel', 'flx-files' ),
		'via_ai'              => __( 'AI chat', 'flx-files' ),
		'via_mcp'             => __( 'MCP', 'flx-files' ),
		'via_front'           => __( 'Site', 'flx-files' ),
		/* translators: %s: number of lines */
		'lines_same'          => __( '%s unchanged lines', 'flx-files' ),
		'no_changes'          => __( 'No changes.', 'flx-files' ),
		/* translators: 1: uploaded files, 2: total files */
		'uploading_n'         => __( 'Uploading %1$s / %2$s', 'flx-files' ),
		/* translators: %s: number of files */
		'uploaded_n'          => __( '%s files uploaded', 'flx-files' ),
		'uploaded_n_1'        => __( '1 file uploaded', 'flx-files' ),
		'upload_failed'       => __( 'Upload failed', 'flx-files' ),
		/* translators: %s: file name */
		'upload_conflict'     => __( '"%s" already exists. Replace it? The old file is kept as a version.', 'flx-files' ),
		/* translators: %s: days */
		'trash_days'          => __( 'Items are deleted for good after %s days.', 'flx-files' ),
		'trash_forever'       => __( 'Items stay until you empty the trash.', 'flx-files' ),
		'empty_trash'         => __( 'Empty trash', 'flx-files' ),
		'trash_empty'         => __( 'The trash is empty', 'flx-files' ),
		/* translators: %s: date */
		'until'               => __( 'Kept until %s', 'flx-files' ),
		'restore'             => __( 'Restore', 'flx-files' ),
		'delete_forever'      => __( 'Delete permanently', 'flx-files' ),
		'delete_forever_msg'  => __( 'This cannot be undone.', 'flx-files' ),
		'empty_trash_msg'     => __( 'Everything in the trash will be deleted permanently. This cannot be undone.', 'flx-files' ),
		/* translators: %s: path */
		'restored_to'         => __( 'Restored to %s', 'flx-files' ),
		/* translators: %s: number of items */
		'removed_n'           => __( '%s items deleted.', 'flx-files' ),
		'all'                 => __( 'All', 'flx-files' ),
		'log_empty'           => __( 'No activity yet', 'flx-files' ),
		'load_more'           => __( 'Load more', 'flx-files' ),
		/* translators: %s: number of events */
		'events_n'            => __( '%s events', 'flx-files' ),
		'events_n_1'          => __( '1 event', 'flx-files' ),
		'a_create'            => __( 'Created', 'flx-files' ),
		'a_edit'              => __( 'Edited', 'flx-files' ),
		'a_mkdir'             => __( 'New folder', 'flx-files' ),
		'a_rename'            => __( 'Renamed', 'flx-files' ),
		'a_move'              => __( 'Moved', 'flx-files' ),
		'a_copy'              => __( 'Copied', 'flx-files' ),
		'a_trash'             => __( 'Moved to trash', 'flx-files' ),
		'a_restore'           => __( 'Restored', 'flx-files' ),
		'a_purge'             => __( 'Deleted permanently', 'flx-files' ),
		'a_chmod'             => __( 'Permissions changed', 'flx-files' ),
		'a_zip'               => __( 'Archived', 'flx-files' ),
		'a_unzip'             => __( 'Extracted', 'flx-files' ),
		'a_upload'            => __( 'Uploaded', 'flx-files' ),
		'a_revert'            => __( 'Version restored', 'flx-files' ),
		'a_settings'          => __( 'Settings changed', 'flx-files' ),
		'a_share'             => __( 'Shared on site', 'flx-files' ),
		'a_unshare'           => __( 'Sharing stopped', 'flx-files' ),
		'set_view'            => __( 'View', 'flx-files' ),
		'set_hidden'          => __( 'Show hidden files', 'flx-files' ),
		'set_hidden_d'        => __( 'Files starting with a dot, such as .htaccess.', 'flx-files' ),
		'set_safety'          => __( 'Safety', 'flx-files' ),
		'set_trash'           => __( 'Keep trash for (days)', 'flx-files' ),
		'set_trash_d'         => __( '0 keeps items until you empty the trash.', 'flx-files' ),
		'set_versions'        => __( 'Versions per file', 'flx-files' ),
		'set_quota'           => __( 'Disk quota (GB)', 'flx-files' ),
		'set_quota_d'         => __( 'Your hosting plan disk space, e.g. 100. Usage is measured for the whole hosting account. 0 shows usage only.', 'flx-files' ),
		'set_versions_d'      => __( 'Earlier contents kept on every save (text files up to 3 MB). 0 turns it off.', 'flx-files' ),
		'set_ai'              => __( 'AI and MCP', 'flx-files' ),
		'set_ai_d'            => __( 'Flux AI chat can list, read and search files, and asks you before every change. wp-config.php, .env and key files are never shared.', 'flx-files' ),
		'set_mcp'             => __( 'External MCP clients may change files', 'flx-files' ),
		'set_mcp_d'           => __( 'Off: Claude, ChatGPT and other MCP clients can only read. On: they can write, move and delete (to the trash).', 'flx-files' ),
		'set_code'            => __( 'AI may write code files', 'flx-files' ),
		'set_code_d'          => __( 'Lets AI change .php, .js, .css, .html, .htaccess and similar files. WordPress core and protected files stay off-limits.', 'flx-files' ),
		'set_risky'           => __( 'This lets AI change more of the site. Every change is still logged in Activity and earlier versions are kept. Continue?', 'flx-files' ),
		'set_shared'          => __( 'Shared folders', 'flx-files' ),
		'set_shared_d'        => __( 'Folders in uploads that visitors can browse with the [flx_files] shortcode. Right-click a folder › Share on site.', 'flx-files' ),
		'set_shared_none'     => __( 'No shared folders.', 'flx-files' ),
		'show_in_browser'     => __( 'Show in browser', 'flx-files' ),
		/* translators: %s: file name */
		'unsaved_msg'         => __( '"%s" has unsaved changes. Close it anyway?', 'flx-files' ),
		'unsaved_title'       => __( 'Unsaved changes', 'flx-files' ),
		'close_anyway'        => __( 'Close without saving', 'flx-files' ),
		'find'                => __( 'Find', 'flx-files' ),
		'copy_content'        => __( 'Copy content', 'flx-files' ),
		'ai_explain'          => __( 'Explain with AI', 'flx-files' ),
		'ai_explain_sel'      => __( 'Explain the selection with AI', 'flx-files' ),
		'ai_edit'             => __( 'Edit with AI', 'flx-files' ),
		/* translators: 1: line, 2: column */
		'line_col'            => __( 'Line %1$s, column %2$s', 'flx-files' ),
		/* translators: %s: number of lines */
		'lines_n'             => __( '%s lines', 'flx-files' ),
		'unsaved'             => __( 'Unsaved', 'flx-files' ),
		'saved_state'         => __( 'Saved', 'flx-files' ),
		'sensitive_banner'    => __( 'This file holds passwords or keys. It is never sent to AI; be careful when sharing it.', 'flx-files' ),
		'core_banner'         => __( 'WordPress core file: changes are lost when WordPress updates.', 'flx-files' ),
		'not_writable_banner' => __( 'The server does not allow writing this file; it is opened read-only.', 'flx-files' ),
		'core_save'           => __( 'This is a WordPress core file. A mistake can break the site and the change is lost on the next WordPress update. Save anyway? The previous content is kept as a version.', 'flx-files' ),
		'sensitive_save'      => __( 'This file controls the site configuration. A mistake can take the site down. Save anyway? The previous content is kept as a version.', 'flx-files' ),
		'replace_with'        => __( 'Replace with', 'flx-files' ),
		'replace'             => __( 'Replace', 'flx-files' ),
		'replace_all'         => __( 'Replace all', 'flx-files' ),
		/* translators: %s: number of replacements */
		'replaced_n'          => __( '%s replaced.', 'flx-files' ),
		'ai_thinking'         => __( 'AI is working…', 'flx-files' ),
		'ai_edit_q'           => __( 'What should change?', 'flx-files' ),
		'ai_edit_ph'          => __( 'e.g. Add a comment above each function / Fix the syntax error on line 12', 'flx-files' ),
		'ai_edit_hint'        => __( 'AI suggests a change and shows the difference. Nothing is saved until you apply it and press Save.', 'flx-files' ),
		'ai_suggest'          => __( 'Suggest a change', 'flx-files' ),
		'ai_apply'            => __( 'Apply in editor', 'flx-files' ),
		'ai_applied'          => __( 'Applied in the editor. Review it and press Save.', 'flx-files' ),
	);
}
