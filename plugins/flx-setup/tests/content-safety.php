<?php
/** Disposable WordPress integration. FLX_TEST_SITE=1 php content-safety.php /path/to/wp */
if ( '1' !== getenv( 'FLX_TEST_SITE' ) || empty( $argv[1] ) ) { exit( "Disposable WordPress site required.\n" ); }
$_SERVER['HTTP_HOST'] = 'localhost';
require rtrim( $argv[1], '/' ) . '/wp-load.php';
require_once ABSPATH . 'wp-admin/includes/file.php';
if ( ! defined( 'FLXK_FORMAT' ) ) { define( 'FLXK_FORMAT', 'flx-preset/1' ); }
if ( ! defined( 'FLXK_DIR' ) ) { define( 'FLXK_DIR', dirname( __DIR__ ) . '/' ); }
foreach ( array( 'helpers', 'profile', 'registry', 'settings-schema', 'blocks', 'importers', 'engine', 'patches' ) as $file ) { require_once FLXK_DIR . 'includes/' . $file . '.php'; }
function safety_check( $ok, $message ) { if ( ! $ok ) { throw new Exception( $message ); } echo 'PASS ' . $message . PHP_EOL; }
$before = array(); foreach ( array( 'flxk_state', 'flxk_history' ) as $name ) { $before[ $name ] = get_option( $name, FLXK_ABSENT ); }
$runs = array(); $posts = array(); $terms = array(); $fixtures = array();
add_action( 'flxk_applied', static function ( $pid, $r ) use ( &$runs ) { $runs[] = $r['run']; }, 10, 2 );
$pid = 'qa-safe-' . substr( wp_generate_uuid4(), 0, 8 );
function safety_item( $suffix ) { return array( 'type' => 'page', 'key' => 'qa-safe-' . $suffix, 'slug' => 'qa-safe-' . $suffix, 'title' => 'Initial', 'body' => 'Initial body', 'meta' => array( '_flxseo_title' => 'Initial SEO' ) ); }
function safety_run( $pid, $item, $mid, $opts = array() ) { return flxk_execute( $pid, array( 'content' => array( $item ) ), $opts, array( 'kind' => 'migration', 'migration' => $mid ) ); }
try {
	$item = safety_item( wp_generate_uuid4() ); $body = 'content:page:' . $item['key']; $seo = 'content-field:page:' . $item['key'] . ':meta:_flxseo_title';
	$r = safety_run( $pid, $item, 'fresh' ); $id = flxk_find_by_key( 'page', $item['key'] ); $posts[] = $id;
	safety_check( $id && 0 === $r['remaining'] && 'Initial SEO' === get_post_meta( $id, '_flxseo_title', true ), 'Fresh install writes body and independently owned SEO' );
	$legacy_hash = flxk_hash( flxk_importers()['page']['read']( $id ) );
	safety_check( $legacy_hash === flxk_state( $pid )['owned'][ $body ], 'Body hash retains legacy shape' );
	$item['meta']['_flxseo_title'] = 'SEO only'; $r = safety_run( $pid, $item, 'seo-only' );
	safety_check( 1 === $r['written'] && 'SEO only' === get_post_meta( $id, '_flxseo_title', true ), 'Metadata-only change is compared and saved' );
	flxk_revert_run( $r['run'] ); safety_check( 'Initial SEO' === get_post_meta( $id, '_flxseo_title', true ), 'SEO rollback restores old value' );
	// Re-adopt matching value without guessing an unknown differing legacy baseline.
	$item['meta']['_flxseo_title'] = 'Initial SEO'; safety_run( $pid, $item, 'adopt-same' );
	update_post_meta( $id, '_flxseo_title', 'User SEO' ); $item['body'] = 'Updated body'; $item['meta']['_flxseo_title'] = 'Preset SEO';
	$r = safety_run( $pid, $item, 'manual-seo' );
	safety_check( 'Updated body' === get_post( $id )->post_content && 'User SEO' === get_post_meta( $id, '_flxseo_title', true ), 'Body upgrade preserves independently edited SEO' );
	safety_check( $r['remaining'] > 0 && ! isset( flxk_state( $pid )['migrations']['manual-seo'] ), 'Manual fields keep migration incomplete' );
	$r = safety_run( $pid, $item, 'manual-seo', array( 'only' => array( $seo ), 'force' => array( $seo ) ) );
	safety_check( 0 === $r['remaining'] && 'Preset SEO' === get_post_meta( $id, '_flxseo_title', true ), 'Explicit field-only force retries and completes migration' );
	wp_update_post( array( 'ID' => $id, 'post_content' => 'User body' ) ); $item['body'] = 'Another body';
	$r = safety_run( $pid, $item, 'manual-body' );
	safety_check( 'User body' === get_post( $id )->post_content && $r['remaining'] > 0 && ! isset( flxk_state( $pid )['migrations']['manual-body'] ), 'Edited owned body stays manual and pending' );
	// Legacy body ownership cannot prove ownership of SEO that was never hashed.
	$st = flxk_state( $pid ); unset( $st['owned'][ $seo ] ); $st['owned'][ $body ] = flxk_hash( flxk_importers()['page']['read']( $id ) ); flxk_save_state( $pid, $st );
	$item['meta']['_flxseo_title'] = 'Legacy upgrade SEO'; $r = safety_run( $pid, $item, 'legacy' );
	safety_check( 'Another body' === get_post( $id )->post_content && 'Preset SEO' === get_post_meta( $id, '_flxseo_title', true ) && $r['remaining'] > 0, 'Legacy body hashes upgrade but unknown SEO baseline remains unresolved' );
	// Same-slug collision, including identical visible body, may not silently adopt content.
	$collision = safety_item( wp_generate_uuid4() ); $cid = wp_insert_post( array( 'post_type' => 'page', 'post_status' => 'publish', 'post_name' => $collision['slug'], 'post_title' => $collision['title'], 'post_content' => $collision['body'] ) ); $posts[] = $cid;
	update_post_meta( $cid, '_flxseo_title', 'Unrelated SEO' ); $ct = 'content:page:' . $collision['key']; $cf = 'content-field:page:' . $collision['key'] . ':meta:_flxseo_title';
	$r = safety_run( $pid, $collision, 'collision' ); safety_check( 0 === $r['written'] && ! metadata_exists( 'post', $cid, '_flxk_key' ) && 'Unrelated SEO' === get_post_meta( $cid, '_flxseo_title', true ), 'Non-owned identical same-slug post is not adopted or changed' );
	$r = safety_run( $pid, $collision, 'collision', array( 'force' => array( $ct, $cf ) ) ); safety_check( 0 === $r['remaining'] && $collision['key'] === get_post_meta( $cid, '_flxk_key', true ), 'Reviewed exact body and field targets can be adopted' );
	flxk_revert_run( $r['run'] ); safety_check( ! metadata_exists( 'post', $cid, '_flxk_key' ) && 'Unrelated SEO' === get_post_meta( $cid, '_flxseo_title', true ), 'Forced-adoption rollback restores absent identity and old SEO' );
	// Absent/empty and multivalue metadata are different snapshots.
	$meta_item = safety_item( wp_generate_uuid4() ); $r = safety_run( $pid, $meta_item, 'metadata-base' ); $mid = flxk_find_by_key( 'page', $meta_item['key'] ); $posts[] = $mid;
	$meta_item['meta']['qa_empty'] = ''; $r = safety_run( $pid, $meta_item, 'empty' ); safety_check( metadata_exists( 'post', $mid, 'qa_empty' ), 'An explicit empty field is written' ); flxk_revert_run( $r['run'] ); safety_check( ! metadata_exists( 'post', $mid, 'qa_empty' ), 'Rollback restores an absent field, not an empty string' ); unset( $meta_item['meta']['qa_empty'] );
	add_post_meta( $mid, 'qa_multi', 'first' ); add_post_meta( $mid, 'qa_multi', 'second' ); $meta_item['meta']['qa_multi'] = 'single'; $mt = 'content-field:page:' . $meta_item['key'] . ':meta:qa_multi';
	$r = safety_run( $pid, $meta_item, 'multi', array( 'force' => array( $mt ) ) ); safety_check( array( 'single' ) === get_post_meta( $mid, 'qa_multi', false ), 'Explicit replacement produces one meta value' ); flxk_revert_run( $r['run'] ); safety_check( array( 'first', 'second' ) === get_post_meta( $mid, 'qa_multi', false ), 'Rollback restores every previous meta row in order' );
	// Terms also have independent baselines and reversible IDs.
	$post = safety_item( wp_generate_uuid4() ); $post['type'] = 'post'; $post['terms'] = array( 'category' => array( 'qa-safe-category-' . wp_generate_uuid4() ) ); $r = safety_run( $pid, $post, 'terms-fresh' ); $tid = flxk_find_by_key( 'post', $post['key'] ); $posts[] = $tid; $old_terms = wp_get_object_terms( $tid, 'category', array( 'fields' => 'ids' ) ); $terms = array_merge( $terms, $old_terms );
	safety_check( 0 === $r['remaining'] && 1 === count( $old_terms ), 'Fresh term assignment replaces default category and completes' );
	wp_set_object_terms( $tid, array( 1 ), 'category' ); $post['body'] = 'New term body'; $r = safety_run( $pid, $post, 'terms-manual' ); safety_check( array( 1 ) === wp_get_object_terms( $tid, 'category', array( 'fields' => 'ids' ) ) && $r['remaining'] > 0, 'User term changes are preserved during body upgrades' );

	$empty_terms = safety_item( wp_generate_uuid4() ); $empty_terms['type'] = 'post'; $empty_terms['terms'] = array( 'category' => array() );
	$r = safety_run( $pid, $empty_terms, 'empty-terms' ); $etid = flxk_find_by_key( 'post', $empty_terms['key'] ); $posts[] = $etid;
	safety_check( 0 === $r['remaining'] && array() === wp_get_object_terms( $etid, 'category', array( 'fields' => 'ids' ) ), 'Explicit empty terms clear the fresh default without an endless pending state' );
	// Exact structured/slashed values, unrelated fields and changes from post-save hooks.
	$meta_item['meta'] = array( '_flxseo_title' => 'Initial SEO', 'qa_structured' => array( 'quote' => 'a"b', 'count' => 2 ), 'qa_slashes' => 'C:\\folder\\file' );
	update_post_meta( $mid, 'unrelated_plugin_value', 'User unrelated' ); $r = safety_run( $pid, $meta_item, 'structured' );
	safety_check( ! $r['errors'] && $meta_item['meta']['qa_structured'] === get_post_meta( $mid, 'qa_structured', true ) && $meta_item['meta']['qa_slashes'] === get_post_meta( $mid, 'qa_slashes', true ), 'Structured and slash-heavy fields survive WordPress storage' );
	safety_check( 'User unrelated' === get_post_meta( $mid, 'unrelated_plugin_value', true ), 'Unrelated plugin metadata is not read, hashed or overwritten' );
	$hook = static function ( $changed ) use ( $mid ) { if ( $changed === $mid ) { update_post_meta( $mid, '_flxseo_title', 'Hook SEO' ); } };
	add_action( 'save_post_page', $hook ); $meta_item['body'] = 'Hook body'; $meta_item['meta']['_flxseo_title'] = 'Planner SEO'; $r = safety_run( $pid, $meta_item, 'hook' ); remove_action( 'save_post_page', $hook );
	safety_check( 'Hook SEO' === get_post_meta( $mid, '_flxseo_title', true ) && $r['errors'] && $r['remaining'] > 0, 'Post-save changes are detected before field write and prevent completion' );
	$failure = safety_item( wp_generate_uuid4() );
	$reject = static function ( $empty, $postarr ) use ( $failure ) { return $postarr['post_name'] === $failure['slug'] ? true : $empty; };
	add_filter( 'wp_insert_post_empty_content', $reject, 10, 2 ); $r = safety_run( $pid, $failure, 'save-error' ); remove_filter( 'wp_insert_post_empty_content', $reject, 10 );
	safety_check( $r['errors'] && $r['remaining'] > 0 && ! isset( flxk_state( $pid )['migrations']['save-error'] ), 'Importer save errors cannot mark a migration applied' );

	$duplicate = wp_insert_post( array( 'post_type' => 'page', 'post_status' => 'publish', 'post_title' => 'Duplicate key', 'post_content' => 'Duplicate body' ) ); $posts[] = $duplicate; update_post_meta( $duplicate, '_flxk_key', $meta_item['key'] );
	$r = safety_run( $pid, $meta_item, 'duplicate-key', array( 'force' => true ) );
	safety_check( 0 === $r['written'] && $r['remaining'] > 0, 'Ambiguous duplicate preset keys fail closed even under force' );
	delete_post_meta( $duplicate, '_flxk_key' );
	$st = flxk_state( $pid ); $st['content_ids'][ 'content:page:' . $meta_item['key'] ] = $duplicate; flxk_save_state( $pid, $st );
	$r = safety_run( $pid, $meta_item, 'remapped-id' ); safety_check( 0 === $r['written'] && $r['remaining'] > 0, 'A changed record ID is not trusted merely because its key matches' );
	// Automatic retries pause unchanged blocked sources but remain visible to manual retry.
	$fixture = wp_tempnam( 'safe-preset' ); $fixtures[] = $fixture;
	$manifest = array( 'format' => FLXK_FORMAT, 'id' => $pid, 'name' => 'QA', 'version' => '1', 'migrations' => array( array( 'id' => 'auto-blocked', 'auto' => true, 'content' => array( $collision ) ) ) );
	file_put_contents( $fixture, wp_json_encode( $manifest ) ); add_filter( 'flx_presets', static function ( $paths ) use ( $pid, $fixture ) { $paths[ $pid ] = $fixture; return $paths; } ); flxk_presets( true ); wp_set_current_user( 1 );
	flxk_auto_migrations(); $count = count( flxk_state( $pid )['runs'] ); flxk_auto_migrations();
	safety_check( $count === count( flxk_state( $pid )['runs'] ) && 1 === count( flxk_pending_migrations( $pid ) ), 'Unchanged blocked automatic migration stays pending without repeated run spam' );

	$later = safety_item( 'later-' . substr( wp_generate_uuid4(), 0, 8 ) );
	$manifest['migrations'][] = array( 'id' => 'later-safe', 'auto' => true, 'content' => array( $later ) ); file_put_contents( $fixture, wp_json_encode( $manifest ) ); flxk_presets( true ); flxk_auto_migrations(); $later_id = flxk_find_by_key( 'page', $later['key'] ); $posts[] = $later_id;
	safety_check( $later_id && isset( flxk_state( $pid )['migrations']['later-safe'] ) && 1 === count( flxk_pending_migrations( $pid ) ), 'A blocked migration does not prevent independent later migrations' );
	echo 'PASS actual WordPress ' . get_bloginfo( 'version' ) . ', PHP ' . PHP_VERSION . PHP_EOL;
} finally {
	foreach ( $before as $name => $value ) { FLXK_ABSENT === $value ? delete_option( $name ) : update_option( $name, $value ); }
	foreach ( array_unique( $runs ) as $run ) { delete_option( 'flxk_run_' . $run ); }
	foreach ( $posts as $id ) { wp_delete_post( $id, true ); }
	foreach ( $terms as $term ) { if ( 1 !== (int) $term ) { wp_delete_term( $term, 'category' ); } }
	foreach ( $fixtures as $file ) { unlink( $file ); }
}
