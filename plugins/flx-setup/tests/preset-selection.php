<?php
/** Disposable WP integration: FLX_TEST_SITE=1 php preset-selection.php /var/www/html */
if ( '1' !== getenv( 'FLX_TEST_SITE' ) || empty( $argv[1] ) ) { exit( "Disposable WordPress site required.\n" ); }
$_SERVER['HTTP_HOST'] = '127.0.0.1:8893';
require rtrim( $argv[1], '/' ) . '/wp-load.php';
function selection_check( $condition, $message ) {
	if ( ! $condition ) { throw new Exception( $message ); }
	echo 'PASS ' . $message . PHP_EOL;
}
$names = array( 'flx_qa_selection', 'flx_qa_patch', 'flx_qa_migration', 'flxk_state', 'flxk_history', 'flxk_patches', 'page_on_front' );
$before = array();
foreach ( $names as $name ) { $before[ $name ] = get_option( $name, FLXK_ABSENT ); }
$runs = array(); $page = 0;
add_action( 'flxk_applied', static function ( $id, $result ) use ( &$runs ) { $runs[] = $result['run']; }, 10, 2 );
try {
	$target = 'option:flx_qa_selection:';
	$p = array( 'settings' => array( 'flx_qa_selection' => array( 'a' => 'new-a', 'b' => 'new-b' ) ) );
	update_option( 'flx_qa_selection', array( 'a' => 'old-a', 'b' => 'old-b' ) );
	$r = flxk_execute( 'qa-selection', $p, array( 'only' => array( $target . 'a' ) ) );
	selection_check( 1 === $r['written'] && get_option( 'flx_qa_selection' ) === array( 'a' => 'new-a', 'b' => 'old-b' ), 'Only selected targets are written' );
	selection_check( ! isset( flxk_state( 'qa-selection' )['owned'][ $target . 'b' ] ), 'Unchecked targets are not adopted as preset-owned' );
	selection_check( is_wp_error( flxk_execute( 'qa-selection', $p, array( 'only' => array() ) ) ), 'Empty explicit selection is rejected' );
	selection_check( is_wp_error( flxk_execute( 'qa-selection', $p, array( 'only' => array( $target . 'missing' ) ) ) ), 'Unknown or stale targets are rejected' );
	update_option( 'flx_qa_selection', array( 'a' => 'manual-a', 'b' => 'old-b' ) );
	$r = flxk_execute( 'qa-selection', $p, array( 'only' => array( $target . 'a' ) ) );
	selection_check( 0 === $r['written'] && 'manual-a' === get_option( 'flx_qa_selection' )['a'], 'Manual changes stay protected without explicit force' );
	$r = flxk_execute( 'qa-selection', $p, array( 'only' => array( $target . 'a' ), 'force' => array( $target . 'a', $target . 'b' ) ) );
	selection_check( 'new-a' === get_option( 'flx_qa_selection' )['a'] && 'old-b' === get_option( 'flx_qa_selection' )['b'], 'Force cannot write an unchecked target' );
	$r = flxk_execute( 'qa-selection', $p );
	selection_check( 'new-b' === get_option( 'flx_qa_selection' )['b'], 'Legacy callers without a selection still apply every normal change' );
	$state = flxk_state( 'qa-selection' );
	$state['owned'][ $target . 'a' ] = 'older-importer-shape';
	flxk_save_state( 'qa-selection', $state );
	flxk_execute( 'qa-selection', $p, array( 'only' => array( $target . 'a' ) ) );
	selection_check( flxk_state( 'qa-selection' )['owned'][ $target . 'a' ] === flxk_target_hash( $target . 'a' ), 'Matching selected values refresh older comparison hashes without rewriting content' );
	$profile = flxk_profile_all();
	$p = array( 'profile' => array( 'name' => 'Unchecked organisation' ), 'settings' => array( 'flx_qa_selection' => array( 'message' => '{profile.name}' ) ) );
	$r = flxk_execute( 'qa-selection', $p, array( 'only' => array( $target . 'message' ) ) );
	selection_check( get_option( 'flx_qa_selection' )['message'] === ( $profile['name'] ?? '' ) && $r['remaining'] > 0, 'Selected content resolves the actual unchecked profile and counts remaining work' );
	$key = 'qa-selection-page-' . wp_generate_uuid4();
	$p = array( 'content' => array( array( 'type' => 'page', 'key' => $key, 'title' => 'Selection QA', 'body' => '<!-- wp:paragraph --><p>QA</p><!-- /wp:paragraph -->' ) ), 'wp' => array( 'page_on_front' => '@page:' . $key ) );
	$r = flxk_execute( 'qa-selection', $p, array( 'only' => array( 'wp:page_on_front' ) ) );
	selection_check( is_wp_error( $r ) && 'selection' === $r->get_error_code(), 'A missing unchecked reference is rejected before writes' );
	$r = flxk_execute( 'qa-selection', $p, array( 'only' => array( 'content:page:' . $key, 'wp:page_on_front' ), 'force' => true ) );
	$page = flxk_find_by_key( 'page', $key );
	selection_check( $page > 0 && (int) get_option( 'page_on_front' ) === $page, 'Selected dependencies resolve across both execution stages' );
	$p = array( 'settings' => array( 'flx_qa_migration' => array( 'a' => 'A', 'b' => 'B' ) ) );
	$meta = array( 'kind' => 'migration', 'migration' => 'qa-partial' );
	$r = flxk_execute( 'qa-selection', $p, array( 'only' => array( 'option:flx_qa_migration:a' ) ), $meta );
	selection_check( ! isset( flxk_state( 'qa-selection' )['migrations']['qa-partial'] ) && 1 === $r['remaining'], 'A partial migration remains pending' );
	$r = flxk_execute( 'qa-selection', $p, array( 'only' => array( 'option:flx_qa_migration:b' ) ), $meta );
	selection_check( isset( flxk_state( 'qa-selection' )['migrations']['qa-partial'] ) && 0 === $r['remaining'], 'A completed migration is marked applied' );
	$patch = flxk_patch_submit( array( 'settings' => array( 'flx_qa_patch' => array( 'a' => 'A', 'b' => 'B' ) ) ), 'panel', 'Selection QA' );
	selection_check( ! is_wp_error( $patch ), 'Patch fixture is valid' );
	$r = flxk_patch_apply( $patch, 'panel', '', array( 'only' => array( 'option:flx_qa_patch:a' ) ) );
	selection_check( 'pending' === flxk_patches()[ $patch ]['status'], 'Partial patches retain unchecked pending changes' );
	$r = flxk_patch_apply( $patch, 'panel', '', array( 'only' => array( 'option:flx_qa_patch:b' ) ) );
	selection_check( 'applied' === flxk_patches()[ $patch ]['status'], 'A completed selected patch is marked applied' );
} finally {
	foreach ( $before as $name => $value ) { FLXK_ABSENT === $value ? delete_option( $name ) : update_option( $name, $value ); }
	foreach ( $runs as $run ) { delete_option( 'flxk_run_' . $run ); }
	if ( $page ) { wp_delete_post( $page, true ); }
}
