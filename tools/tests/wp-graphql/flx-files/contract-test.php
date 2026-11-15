<?php
/**
 * Flux Files GraphQL contract tests. The documents are the persisted operations the Files screen sends
 * (plugins/flx-files/graphql/persisted-queries.json). REST and GraphQL share FLXFM_Files_Service, so the
 * classic REST output is compared with it too.
 *
 *   wp eval-file tools/tests/wp-graphql/flx-files/contract-test.php
 */

require dirname( __DIR__ ) . '/lib.php';

echo "Flux Files GraphQL\n";

$GLOBALS['flxf_ops'] = array();
foreach ( (array) json_decode( (string) file_get_contents( FLXFM_DIR . 'graphql/persisted-queries.json' ), true ) as $doc ) {
	if ( preg_match( '/^(?:query|mutation) (Flx\w+)/', $doc, $m ) ) {
		$GLOBALS['flxf_ops'][ $m[1] ] = $doc;
	}
}
flxt_same( 26, count( $GLOBALS['flxf_ops'] ), 'Flux Files ships 26 persisted operations' );

function flxf_op( string $name, array $vars = array(), int $user = 0 ): array {
	return flxt_gql( $GLOBALS['flxf_ops'][ $name ], $vars, $user );
}
function flxf_in( string $name, array $input, int $user = 1 ): array {
	return flxf_op( $name, array( 'input' => $input ), $user );
}
function flxf_rm( string $d ): void {
	if ( ! is_dir( $d ) ) {
		return;
	}
	foreach ( new RecursiveIteratorIterator( new RecursiveDirectoryIterator( $d, FilesystemIterator::SKIP_DOTS ), RecursiveIteratorIterator::CHILD_FIRST ) as $f ) {
		$f->isDir() ? rmdir( $f->getPathname() ) : unlink( $f->getPathname() );
	}
	rmdir( $d );
}

$admin = 1;
$sub   = (int) wp_insert_user( array( 'user_login' => 'flxf_sub_' . wp_rand(), 'user_pass' => wp_generate_password(), 'role' => 'subscriber' ) );
$B     = 'wp-content/uploads/flxf-contract';
flxf_rm( flxfm_root() . '/' . $B );
mkdir( flxfm_root() . '/' . $B, 0755, true );
flxfm_trash_purge( true );
delete_option( 'flxfm_log' );

// Permissions: every operation fails closed.
$r = flxf_op( 'FlxFilesBootstrap' );
flxt_same( 'UNAUTHENTICATED', flxt_code( $r ), 'bootstrap: visitor → UNAUTHENTICATED' );
$r = flxf_op( 'FlxFilesBootstrap', array(), $sub );
flxt_same( 'FORBIDDEN', flxt_code( $r ), 'bootstrap: subscriber → FORBIDDEN' );
$r = flxf_in( 'FlxFilesWrite', array( 'path' => "$B/x.txt", 'content' => 'x' ), $sub );
flxt_same( 'FORBIDDEN', flxt_code( $r ), 'write: subscriber → FORBIDDEN' );
flxt_ok( ! file_exists( flxfm_root() . "/$B/x.txt" ), 'write: nothing written for the subscriber' );

// Bootstrap.
$r = flxf_op( 'FlxFilesBootstrap', array(), $admin );
flxt_same( null, $r['errors'] ?? null, 'bootstrap: no errors for the administrator' );
$d = $r['data'] ?? array();
flxt_same( flxfm_root(), $d['flxFilesConfig']['rootPath'] ?? null, 'bootstrap: root path' );
flxt_ok( is_int( $d['flxFilesConfig']['chunkSize'] ?? null ) && $d['flxFilesConfig']['chunkSize'] > 0, 'bootstrap: chunk size (REST upload)' );
flxt_same( (int) flxfm_opt( 'trash_days' ), $d['flxFilesSettings']['trashDays'] ?? null, 'bootstrap: settings trash days' );

// Empty folder.
$r = flxf_op( 'FlxFilesFolder', array( 'path' => $B ), $admin );
flxt_same( 0, $r['data']['flxFilesFolder']['items']['totalCount'] ?? null, 'folder: empty → 0 items' );
flxt_same( false, $r['data']['flxFilesFolder']['items']['pageInfo']['hasNextPage'] ?? null, 'folder: empty → no next page' );

// Mutations: create, write, conflict.
$r = flxf_in( 'FlxFilesFolderCreate', array( 'path' => "$B/d1" ) );
flxt_same( 'd1', $r['data']['flxFilesFolderCreate']['item']['name'] ?? null, 'mkdir: creates folder' );
$r = flxf_in( 'FlxFilesFolderCreate', array( 'path' => "$B/d1" ) );
flxt_ok( ! empty( $r['data']['flxFilesFolderCreate']['errors'] ) || ! empty( $r['errors'] ), 'mkdir: existing folder → error', $r );
$r = flxf_in( 'FlxFilesWrite', array( 'path' => "$B/d1/a.txt", 'content' => "hello\nworld\n", 'createOnly' => true ) );
$hash = $r['data']['flxFilesWrite']['file']['hash'] ?? '';
flxt_ok( '' !== $hash, 'write: returns hash', $r );
$r = flxf_in( 'FlxFilesWrite', array( 'path' => "$B/d1/a.txt", 'content' => 'x', 'createOnly' => true ) );
flxt_ok( ! empty( $r['data']['flxFilesWrite']['errors'] ) || ! empty( $r['errors'] ), 'write: createOnly on existing → error' );
$r = flxf_in( 'FlxFilesWrite', array( 'path' => "$B/d1/a.txt", 'content' => 'stale', 'hash' => 'nope' ) );
flxt_same( 'CONFLICT', $r['data']['flxFilesWrite']['errors'][0]['code'] ?? null, 'write: stale hash → CONFLICT payload error' );
$r = flxf_in( 'FlxFilesWrite', array( 'path' => "$B/d1/a.txt", 'content' => "hello\nworld 2\n", 'hash' => $hash ) );
flxt_same( null, $r['errors'] ?? null, 'write: matching hash saves' );

// File + folder listing, REST equivalence.
$r = flxf_op( 'FlxFilesFile', array( 'path' => "$B/d1/a.txt" ), $admin );
flxt_same( "hello\nworld 2\n", $r['data']['flxFilesFile']['content'] ?? null, 'file: content' );
$r = flxf_op( 'FlxFilesFolder', array( 'path' => $B, 'sort' => 'NAME', 'order' => 'ASC' ), $admin );
$nodes = $r['data']['flxFilesFolder']['items']['nodes'] ?? array();
flxt_same( array( 'd1' ), array_column( $nodes, 'name' ), 'folder: lists the folder' );
$rest = FLXFM_Files_Service::list_folder( $B, true );
flxt_ok( $rest instanceof FLXFM_Listing && count( $rest->items ) === 1, 'service: same folder through the service' );
$r = flxf_op( 'FlxFilesFolder', array( 'path' => "$B/missing" ), $admin );
flxt_same( 'NOT_FOUND', flxt_code( $r ), 'folder: missing → NOT_FOUND' );
$r = flxf_op( 'FlxFilesFolder', array( 'path' => '../../etc' ), $admin );
flxt_ok( null !== flxt_code( $r ), 'folder: path traversal refused', $r );

// Sort + pagination.
foreach ( array( 'b.txt', 'a.txt', 'c.txt' ) as $n ) {
	flxf_in( 'FlxFilesWrite', array( 'path' => "$B/$n", 'content' => $n ) );
}
$r = flxf_op( 'FlxFilesFolder', array( 'path' => $B, 'first' => 2 ), $admin );
$items = $r['data']['flxFilesFolder']['items'] ?? array();
flxt_same( array( 'd1', 'a.txt' ), array_column( $items['nodes'] ?? array(), 'name' ), 'folder: folders first, page 1' );
flxt_same( true, $items['pageInfo']['hasNextPage'] ?? null, 'folder: has next page' );
flxt_same( 4, $items['totalCount'] ?? null, 'folder: total count' );
$r = flxf_op( 'FlxFilesFolder', array( 'path' => $B, 'first' => 2, 'after' => $items['pageInfo']['endCursor'] ?? null ), $admin );
flxt_same( array( 'b.txt', 'c.txt' ), array_column( $r['data']['flxFilesFolder']['items']['nodes'] ?? array(), 'name' ), 'folder: page 2' );
$r = flxf_op( 'FlxFilesFolder', array( 'path' => $B, 'sort' => 'NAME', 'order' => 'DESC' ), $admin );
flxt_same( array( 'd1', 'c.txt', 'b.txt', 'a.txt' ), array_column( $r['data']['flxFilesFolder']['items']['nodes'] ?? array(), 'name' ), 'folder: name desc keeps folders first' );

// Rename, copy, duplicate, move.
$r = flxf_in( 'FlxFilesRename', array( 'path' => "$B/c.txt", 'name' => 'c2.txt' ) );
flxt_same( 'c2.txt', $r['data']['flxFilesRename']['item']['name'] ?? null, 'rename' );
$r = flxf_in( 'FlxFilesRename', array( 'path' => "$B/c2.txt", 'name' => 'bad/name' ) );
flxt_ok( ! empty( $r['data']['flxFilesRename']['errors'] ) || ! empty( $r['errors'] ), 'rename: slash in name refused' );
$r = flxf_in( 'FlxFilesCopy', array( 'paths' => array( "$B/a.txt", "$B/none" ), 'destination' => "$B/d1" ) );
flxt_same( 1, count( $r['data']['flxFilesCopy']['items'] ?? array() ), 'copy: one copied' );
flxt_same( "$B/none", $r['data']['flxFilesCopy']['failed'][0]['path'] ?? null, 'copy: one failed with its path' );
$r = flxf_in( 'FlxFilesDuplicate', array( 'paths' => array( "$B/a.txt" ) ) );
flxt_same( 1, count( $r['data']['flxFilesDuplicate']['items'] ?? array() ), 'duplicate' );
$r = flxf_in( 'FlxFilesMove', array( 'paths' => array( "$B/b.txt" ), 'destination' => "$B/d1" ) );
flxt_same( 1, count( $r['data']['flxFilesMove']['items'] ?? array() ), 'move' );
flxt_ok( file_exists( flxfm_root() . "/$B/d1/b.txt" ) && ! file_exists( flxfm_root() . "/$B/b.txt" ), 'move: file moved on disk' );

// Search, size, chmod, share.
$r = flxf_op( 'FlxFilesSearch', array( 'path' => $B, 'query' => 'world', 'content' => true ), $admin );
flxt_ok( count( $r['data']['flxFilesSearch']['results'] ?? array() ) >= 1, 'search: content match', $r );
$r = flxf_op( 'FlxFilesSize', array( 'path' => $B ), $admin );
flxt_ok( ( $r['data']['flxFilesSize']['files'] ?? 0 ) >= 3, 'size: counts files', $r );
$r = flxf_in( 'FlxFilesChmod', array( 'path' => "$B/d1/a.txt", 'mode' => '640' ) );
flxt_same( '0640', $r['data']['flxFilesChmod']['item']['permissions'] ?? null, 'chmod: 0640' );
$r = flxf_in( 'FlxFilesChmod', array( 'path' => "$B/d1/a.txt", 'mode' => '777' ) );
flxt_ok( ! empty( $r['data']['flxFilesChmod']['errors'] ) || ! empty( $r['errors'] ), 'chmod: 777 refused' );

// Zip / unzip.
$r = flxf_in( 'FlxFilesZip', array( 'paths' => array( "$B/d1" ), 'folder' => $B ) );
$zip = $r['data']['flxFilesZip']['item']['path'] ?? '';
flxt_ok( '' !== $zip && file_exists( flxfm_root() . "/$zip" ), 'zip: archive created', $r );
$r = flxf_in( 'FlxFilesUnzip', array( 'path' => $zip, 'destination' => "$B/out" ) );
flxt_ok( ( $r['data']['flxFilesUnzip']['fileCount'] ?? 0 ) > 0, 'unzip: files extracted', $r );

// Versions.
flxf_in( 'FlxFilesWrite', array( 'path' => "$B/d1/a.txt", 'content' => 'third' ) );
$r = flxf_op( 'FlxFilesVersions', array( 'path' => "$B/d1/a.txt" ), $admin );
$vers = $r['data']['flxFilesVersions'] ?? array();
flxt_ok( count( $vers ) >= 1, 'versions: previous saves listed', $r );
if ( $vers ) {
	$vid = $vers[0]['id'];
	$r   = flxf_op( 'FlxFilesVersionContent', array( 'path' => "$B/d1/a.txt", 'versionId' => $vid ), $admin );
	flxt_ok( is_string( $r['data']['flxFilesVersionContent'] ?? null ), 'version content', $r );
	$r = flxf_in( 'FlxFilesRevert', array( 'path' => "$B/d1/a.txt", 'versionId' => $vid ) );
	flxt_same( null, $r['errors'] ?? null, 'revert: ok' );
}

// Trash: trash → list → restore → trash → purge.
$r = flxf_in( 'FlxFilesTrash', array( 'paths' => array( "$B/d1/b.txt", "$B/nope" ) ) );
flxt_same( 1, count( $r['data']['flxFilesTrash']['trashed'] ?? array() ), 'trash: one trashed' );
flxt_same( "$B/nope", $r['data']['flxFilesTrash']['failed'][0]['path'] ?? null, 'trash: missing path in failed' );
$r   = flxf_op( 'FlxFilesTrash', array(), $admin );
$tn  = $r['data']['flxFilesTrash']['items']['nodes'] ?? array();
flxt_same( 1, count( $tn ), 'trash list: one item' );
$id = $tn[0]['id'] ?? 'x';
$r  = flxf_in( 'FlxFilesTrashRestore', array( 'ids' => array( $id ) ) );
flxt_same( 1, count( $r['data']['flxFilesTrashRestore']['items'] ?? array() ), 'restore: one restored' );
flxf_in( 'FlxFilesTrash', array( 'paths' => array( "$B/d1/b.txt" ) ) );
$r = flxf_in( 'FlxFilesTrashPurge', array( 'all' => true ) );
flxt_ok( ( $r['data']['flxFilesTrashPurge']['removed'] ?? 0 ) >= 1, 'purge all', $r );

// Log + settings + disk.
$r = flxf_op( 'FlxFilesLog', array( 'first' => 5 ), $admin );
flxt_ok( count( $r['data']['flxFilesLog']['nodes'] ?? array() ) > 0, 'log: entries', $r );
flxt_same( 'PANEL', $r['data']['flxFilesLog']['nodes'][0]['via'] ?? null, 'log: via panel' );
$r = flxf_in( 'FlxFilesSettingsUpdate', array( 'settings' => array( 'trashDays' => 7, 'versions' => 5 ) ) );
flxt_same( array( 7, 5 ), array( $r['data']['flxFilesSettingsUpdate']['settings']['trashDays'] ?? null, $r['data']['flxFilesSettingsUpdate']['settings']['versions'] ?? null ), 'settings: update' );
$r = flxf_op( 'FlxFilesDisk', array(), $admin );
flxt_same( null, $r['errors'] ?? null, 'disk: no errors' );

// Cleanup.
flxfm_trash_purge( true );
flxf_rm( flxfm_root() . '/' . $B );
flxt_done();
