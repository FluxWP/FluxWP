<?php
/**
 * Flux Studio GraphQL contract tests. The documents are the persisted operations the Studio screen
 * sends (plugins/flx-studio/graphql/persisted-queries.json). REST (flx-studio/v1) and GraphQL share
 * FLXST_Service, so parity is checked against REST responses.
 *
 * No real generation: the Flux AI functions (flxai_*) are stubbed below.
 *
 *   wp eval-file tools/tests/wp-graphql/flx-studio/contract-test.php
 */

require dirname( __DIR__ ) . '/lib.php';

echo "Flux Studio GraphQL\n";

/* ---------------------------------------------------------------- Flux AI stubs (flx-ai is not installed here) */

if ( ! function_exists( 'flxai_media_submit' ) ) {
	$GLOBALS['flxst_t_submits'] = 0;
	function flxai_media_providers() {
		return array(
			'default_image' => 'stub-image',
			'default_video' => 'stub-video',
			'providers'     => array( 'stub' => array( 'label' => 'Stub', 'ready' => true ) ),
			'models'        => array(
				'stub-image' => array(
					'label'       => 'Stub image',
					'kind'        => 'image',
					'routes'      => array( 'stub' ),
					'providers'   => array( 'stub' ),
					'auto'        => 'stub',
					'aspects'     => array( '1:1', '16:9' ),
					'resolutions' => array( '1K', '2K' ),
					'qualities'   => array(),
					'max_refs'    => 4,
					'max_n'       => 4,
					'mask'        => false,
					'price'       => array( '1K' => 0.04 ),
				),
				'stub-video' => array(
					'label'       => 'Stub video',
					'kind'        => 'video',
					'routes'      => array( 'stub' ),
					'providers'   => array( 'stub' ),
					'auto'        => 'stub',
					'aspects'     => array( '16:9' ),
					'resolutions' => array( '720p' ),
					'qualities'   => array(),
					'duration'    => array( 4, 10, 6 ),
					'max_refs'    => 2,
					'max_n'       => 1,
					'mask'        => false,
					'price'       => array( '720p' => 0.1 ),
				),
			),
		);
	}
	function flxai_media_submit( $req ) {
		$GLOBALS['flxst_t_submits']++;
		return array( 'job' => 'flxm_' . dechex( 0xabc000 + $GLOBALS['flxst_t_submits'] ), 'state' => 'running', 'provider' => 'stub' );
	}
	function flxai_media_status( $job ) {
		$dir = wp_upload_dir();
		$png = (string) $dir['basedir'] . '/flxst-test.png';
		if ( ! is_file( $png ) ) {
			imagepng( imagecreatetruecolor( 8, 8 ), $png );
		}
		return array(
			'job'      => $job,
			'state'    => 'done',
			'provider' => 'stub',
			'cost'     => 0.04,
			'outputs'  => array_fill( 0, 2, array( 'url' => $dir['baseurl'] . '/flxst-test.png', 'path' => $png, 'mime' => 'image/png', 'width' => 8, 'height' => 8, 'bytes' => (int) filesize( $png ), 'stored' => true ) ),
		);
	}
	function flxai_media_cancel( $job ) {
		return array( 'job' => $job, 'state' => 'cancelled', 'provider' => 'stub', 'error' => 'Cancelled.' );
	}
	function flxai_chat( $messages, $opts = array() ) {
		return array( 'content' => 'brainstorm' === ( $opts['purpose'] ?? '' ) ? '{"ideas":[{"title":"Bir","prompt":"One"},{"title":"İki","prompt":"Two"}]}' : 'A detailed prompt' );
	}
	function flxai_ready( $source = '' ) {
		return true;
	}
	function flxai_budget_status( $fresh = false ) {
		return array( 'daily' => array( 'label' => 'Daily', 'limit' => 5, 'spent' => 1.5, 'pct' => 30, 'over' => false ) );
	}
} else {
	/*
	 * The real Flux AI is on the test site: keys are fake and the providers are mocked at the HTTP layer
	 * (fal.ai queue: submit, status, result; the file download; the OpenRouter chat for Enhance and Brainstorm).
	 */
	$GLOBALS['flxst_t_submits'] = 0;
	foreach ( array( 'FLXAI_KEY_FAL', 'FLXAI_KEY_OPENROUTER' ) as $c ) {
		if ( ! defined( $c ) ) {
			define( $c, 'test-key' );
		}
	}
	flxai_update_settings( array( 'model' => 'openrouter/test-model', 'limit_day' => 5 ) );
	// The fake CDN has no DNS record; the checked download (flx-brand http.php) needs a public address for it.
	add_filter(
		'flx_host_ips',
		static function ( $ips, $host ) {
			return 'cdn.example.test' === $host ? array( '93.184.216.34' ) : $ips;
		},
		10,
		2
	);
	add_filter(
		'pre_http_request',
		static function ( $pre, $args, $url ) {
			$url  = (string) $url;
			$resp = static function ( $body, string $type = 'application/json' ) {
				return array( 'headers' => array( 'content-type' => $type ), 'body' => $body, 'response' => array( 'code' => 200, 'message' => 'OK' ), 'cookies' => array(), 'filename' => null );
			};
			if ( str_contains( $url, 'queue.fal.run' ) ) {
				if ( 'POST' === ( $args['method'] ?? '' ) ) {
					$GLOBALS['flxst_t_submits']++;
					return $resp( wp_json_encode( array( 'request_id' => 'req' . $GLOBALS['flxst_t_submits'] ) ) );
				}
				if ( str_ends_with( $url, '/status' ) ) {
					return $resp( wp_json_encode( array( 'status' => 'COMPLETED' ) ) );
				}
				$img = array( 'url' => 'https://cdn.example.test/out.png', 'content_type' => 'image/png' );
				return $resp( wp_json_encode( array( 'images' => array( $img, $img ) ) ) );
			}
			if ( str_starts_with( $url, 'https://cdn.example.test/' ) ) {
				ob_start();
				imagepng( imagecreatetruecolor( 8, 8 ) );
				$png = (string) ob_get_clean();
				$out = $resp( $png, 'image/png' );
				if ( ! empty( $args['stream'] ) && ! empty( $args['filename'] ) ) {
					file_put_contents( (string) $args['filename'], $png ); // phpcs:ignore
					$out['filename'] = (string) $args['filename'];
				}
				return $out;
			}
			if ( str_contains( $url, 'chat/completions' ) ) {
				$text = str_contains( (string) ( $args['body'] ?? '' ), 'ideas' ) ? '{"ideas":[{"title":"Bir","prompt":"One"},{"title":"İki","prompt":"Two"}]}' : 'A detailed prompt';
				return $resp( wp_json_encode( array( 'choices' => array( array( 'message' => array( 'content' => $text ), 'finish_reason' => 'stop' ) ), 'usage' => array( 'prompt_tokens' => 1, 'completion_tokens' => 1 ) ) ) );
			}
			return $pre;
		},
		10,
		3
	);
}
$flxst_cat = flxst_models();
$IMG       = (string) ( $flxst_cat['default_image'] ?? '' );
$VID       = (string) ( $flxst_cat['default_video'] ?? '' );
$vcat      = $flxst_cat['models'][ $VID ] ?? array();
$vdur      = array_values( (array) ( $vcat['duration'] ?? array( 4, 10, 6 ) ) );
$vsec      = (int) ( $vdur[2] ?? 6 );
/** The poll lock of a Flux AI job (3 s) would hide a second status call in the same second. */
function flxst_t_unlock( string $job ): void {
	if ( function_exists( 'flxai_media_job_get' ) && ( $row = flxai_media_job_get( $job ) ) ) {
		delete_transient( 'flxai_mpoll_' . $row['id'] );
	}
}

$GLOBALS['flxst_ops'] = array();
foreach ( (array) json_decode( (string) file_get_contents( FLXST_DIR . 'graphql/persisted-queries.json' ), true ) as $doc ) {
	if ( preg_match( '/^(?:query|mutation) (Flx\w+)/', $doc, $m ) ) {
		$GLOBALS['flxst_ops'][ $m[1] ] = $doc;
	}
}
flxt_same( 26, count( $GLOBALS['flxst_ops'] ), 'Flux Studio ships 26 persisted operations' );

function flxst_t_op( string $name, array $vars = array(), int $user = 0 ): array {
	return flxt_gql( $GLOBALS['flxst_ops'][ $name ], $vars, $user );
}

/** Runs a mutation with an input object. */
function flxst_t_mut( string $name, array $input, int $user ): array {
	return flxst_t_op( $name, array( 'input' => $input ), $user );
}

/** REST call as a user. */
function flxst_t_rest( string $method, string $path, array $body = array(), int $user = 1 ): array {
	wp_set_current_user( $user );
	$req = new WP_REST_Request( $method, '/flx-studio/v1' . $path );
	foreach ( $body as $k => $v ) {
		$req->set_param( $k, $v );
	}
	$res = rest_do_request( $req );
	return array( 'status' => $res->get_status(), 'data' => $res->get_data() );
}

$admin = 1;
$mk    = static function ( string $login, string $name ): int {
	return (int) wp_insert_user( array( 'user_login' => $login . wp_rand(), 'user_email' => $login . wp_rand() . '@example.com', 'user_pass' => wp_generate_password(), 'role' => 'subscriber', 'display_name' => $name ) );
};
$sub   = $mk( 'flxst_sub', 'No Studio' );
$ayse  = $mk( 'flxst_a', 'Ayşe Üretici' );
$can   = $mk( 'flxst_b', 'Can Üretici' );
( new WP_User( $ayse ) )->add_cap( 'flxst_use' );
( new WP_User( $can ) )->add_cap( 'flxst_use' );

/* ---------------------------------------------------------------- Permissions */

$r = flxst_t_op( 'FlxStudioWorkspace' );
flxt_same( 'UNAUTHENTICATED', flxt_code( $r ), 'workspace: visitor → UNAUTHENTICATED' );
$r = flxst_t_op( 'FlxStudioWorkspace', array(), $sub );
flxt_same( 'FORBIDDEN', flxt_code( $r ), 'workspace: user without Studio access → FORBIDDEN' );
$r = flxst_t_op( 'FlxStudioItems', array(), $sub );
flxt_same( 'FORBIDDEN', flxt_code( $r ), 'items: user without Studio access → FORBIDDEN' );
$r = flxst_t_mut( 'FlxStudioGenerate', array( 'prompt' => 'x' ), $sub );
flxt_same( 'FORBIDDEN', flxt_code( $r ), 'generate: user without Studio access → FORBIDDEN' );
$r = flxst_t_op( 'FlxStudioStats', array(), $ayse );
flxt_same( 'FORBIDDEN', flxt_code( $r ), 'stats: Studio user without manage → FORBIDDEN' );
$r = flxst_t_mut( 'FlxStudioSettingsUpdate', array( 'maxCount' => 2 ), $ayse );
flxt_same( 'FORBIDDEN', flxt_code( $r ), 'settings: Studio user without manage → FORBIDDEN' );

/* ---------------------------------------------------------------- Workspace + REST parity */

$r = flxst_t_op( 'FlxStudioWorkspace', array(), $ayse );
flxt_same( null, $r['errors'] ?? null, 'workspace: no errors for a Studio user' );
$ws = $r['data']['flxStudio'] ?? array();
flxt_same( array( true, true, false, FLXST_VERSION ), array( $ws['aiMedia'] ?? null, $ws['aiText'] ?? null, $ws['canManage'] ?? null, $ws['version'] ?? null ), 'workspace: AI flags, canManage false, version' );
flxt_same( array_keys( $flxst_cat['models'] ), array_column( $ws['catalog']['models'] ?? array(), 'id' ), 'workspace: models from Flux AI' );
$vid = array_column( $ws['catalog']['models'] ?? array(), null, 'id' )[ $VID ] ?? array();
flxt_same( array( (int) ( $vdur[0] ?? 4 ), (int) ( $vdur[1] ?? 10 ), $vsec ), array( $vid['durationMin'] ?? null, $vid['durationMax'] ?? null, $vid['durationDefault'] ?? null ), 'workspace: video duration range' );
flxt_same( array( $IMG, $VID ), array( $ws['settings']['imageModel'] ?? null, $ws['settings']['videoModel'] ?? null ), 'workspace: settings start with the Flux AI defaults' );
$bud = flxai_budget_status( true );
flxt_same( array( (string) array_key_first( $bud ), (float) reset( $bud )['spent'] ), array( $ws['budget'][0]['period'] ?? null, (float) ( $ws['budget'][0]['spent'] ?? -1 ) ), 'workspace: spending limit from Flux AI' );
$boot = flxst_t_rest( 'GET', '/boot', array(), $ayse );
flxt_same( 200, $boot['status'], 'REST boot works' );
flxt_same( array_keys( $boot['data']['models']['models'] ), array_column( $ws['catalog']['models'] ?? array(), 'id' ), 'parity: REST boot and GraphQL list the same models' );
flxt_same( $boot['data']['settings']['max_n'], $ws['settings']['maxCount'] ?? null, 'parity: max images per request' );

/* ---------------------------------------------------------------- Folders */

$r = flxst_t_mut( 'FlxStudioFolderCreate', array( 'name' => '  ' ), $ayse );
flxt_same( 'INVALID_INPUT', $r['data']['flxStudioFolderCreate']['errors'][0]['code'] ?? null, 'folder: empty name → INVALID_INPUT payload error' );
$r = flxst_t_mut( 'FlxStudioFolderCreate', array( 'name' => 'Kampanya', 'shared' => true ), $ayse );
$f1 = $r['data']['flxStudioFolderCreate']['folder'] ?? array();
flxt_same( array( 'Kampanya', true, $ayse, null ), array( $f1['name'] ?? null, $f1['shared'] ?? null, $f1['ownerId'] ?? null, array_key_exists( 'parentId', $f1 ) ? $f1['parentId'] : 'x' ), 'folder: created shared, top level' );
$r = flxst_t_mut( 'FlxStudioFolderCreate', array( 'name' => 'Özel', 'parentId' => $f1['databaseId'] ), $ayse );
$f2 = $r['data']['flxStudioFolderCreate']['folder'] ?? array();
flxt_same( $f1['databaseId'] ?? 0, $f2['parentId'] ?? null, 'folder: subfolder' );
$r = flxst_t_op( 'FlxStudioWorkspace', array(), $can );
flxt_same( array( $f1['databaseId'] ), array_column( $r['data']['flxStudio']['folders'] ?? array(), 'databaseId' ), 'folder: another user sees only the shared one' );
$r = flxst_t_mut( 'FlxStudioFolderUpdate', array( 'id' => $f2['databaseId'], 'name' => 'Başka' ), $can );
flxt_same( 'NOT_FOUND', flxt_code( $r ), 'folder: a stranger cannot rename it → NOT_FOUND' );
$r = flxst_t_mut( 'FlxStudioFolderUpdate', array( 'id' => $f1['databaseId'], 'parentId' => $f2['databaseId'] ), $ayse );
flxt_same( null, $r['data']['flxStudioFolderUpdate']['folder']['parentId'] ?? null, 'folder: cannot move into its own subfolder (parent stays top level)' );
$r = flxst_t_mut( 'FlxStudioFolderUpdate', array( 'id' => $f2['databaseId'], 'name' => 'Taslaklar' ), $ayse );
flxt_same( 'Taslaklar', $r['data']['flxStudioFolderUpdate']['folder']['name'] ?? null, 'folder: renamed' );

/* ---------------------------------------------------------------- Generate → dispatch → poll */

$r = flxst_t_mut( 'FlxStudioGenerate', array( 'prompt' => '' ), $ayse );
flxt_ok( 'INVALID_INPUT' === ( $r['data']['flxStudioGenerate']['errors'][0]['code'] ?? flxt_code( $r ) ), 'generate: empty prompt → INVALID_INPUT', $r );
$r = flxst_t_mut( 'FlxStudioGenerate', array( 'prompt' => 'Kırmızı bir elma', 'count' => 2, 'aspect' => '1:1', 'folderId' => $f2['databaseId'], 'title' => 'Elma', 'refs' => array() ), $ayse );
flxt_same( null, $r['errors'] ?? null, 'generate: no errors' );
$items = $r['data']['flxStudioGenerate']['items'] ?? array();
flxt_same( array( 2, 'QUEUED', 'IMAGE', $IMG, 'Elma', $f2['databaseId'], 2 ), array( count( $items ), $items[0]['state'] ?? null, $items[0]['kind'] ?? null, $items[0]['model'] ?? null, $items[0]['title'] ?? null, $items[0]['folderId'] ?? null, $items[0]['params']['count'] ?? null ), 'generate: two queued items with the request saved' );
$job = (string) ( $items[0]['job'] ?? '' );
flxt_ok( 0 === strpos( $job, 'flxd_' ), 'generate: items wait under a flxd_ job until sent', $job );
flxt_same( 0, $GLOBALS['flxst_t_submits'], 'generate: nothing is sent to Flux AI during the request (deferred)' );
flxt_ok( str_starts_with( (string) ( $items[0]['id'] ?? '' ), 'Rmx4' ), 'generate: item has a global ID (FlxStudioItem)', $items[0]['id'] ?? null );

// What the shutdown hook does after the response.
flxst_dispatch( $job );
flxt_same( 1, $GLOBALS['flxst_t_submits'], 'dispatch: one submit for both outputs' );

$r = flxst_t_mut( 'FlxStudioPoll', array( 'jobs' => array( $job ) ), $can );
flxt_same( array(), $r['data']['flxStudioPoll']['items'] ?? null, 'poll: another user gets nothing for a job that is not theirs' );
flxst_t_unlock( (string) ( flxst_item( (int) ( $items[0]['databaseId'] ?? 0 ) )['job'] ?? '' ) );
$r = flxst_t_mut( 'FlxStudioPoll', array( 'jobs' => array( $job ) ), $ayse );
$polled = $r['data']['flxStudioPoll']['items'] ?? array();
flxt_same( array( 'DONE', 'DONE' ), array_column( $polled, 'state' ), 'poll: finished items' );
flxt_ok( str_starts_with( (string) ( $polled[0]['job'] ?? '' ), 'flxm_' ) && ! empty( $polled[0]['url'] ), 'poll: real job id and file address', $polled[0] ?? null );
$job_cost = function_exists( 'flxai_media_job_get' ) ? (float) ( flxai_media_job_get( (string) ( $polled[0]['job'] ?? '' ) )['cost'] ?? 0 ) : 0.04;
flxt_same( array( true, round( $job_cost, 4 ) ), array( ( $polled[0]['cost'] ?? 0 ) > 0 && ( $polled[0]['cost'] ?? 0 ) === ( $polled[1]['cost'] ?? -1 ), round( (float) ( $polled[0]['cost'] ?? 0 ) + (float) ( $polled[1]['cost'] ?? 0 ), 4 ) ), 'poll: cost split between the outputs' );
$first = (int) ( $polled[0]['databaseId'] ?? 0 );

// Gallery + REST parity + visibility.
$r = flxst_t_op( 'FlxStudioItems', array( 'first' => 10 ), $ayse );
flxt_same( null, $r['errors'] ?? null, 'items: no errors' );
$page = $r['data']['flxStudioItems'] ?? array();
$rest = flxst_t_rest( 'GET', '/items', array( 'limit' => 10 ), $ayse );
flxt_same( array_column( (array) $rest['data'], 'id' ), array_column( $page['nodes'] ?? array(), 'databaseId' ), 'parity: REST and GraphQL list the same items in the same order' );
$r = flxst_t_op( 'FlxStudioItems', array( 'first' => 1 ), $ayse );
flxt_same( array( 1, true ), array( count( $r['data']['flxStudioItems']['nodes'] ?? array() ), $r['data']['flxStudioItems']['pageInfo']['hasNextPage'] ?? null ), 'items: page size and next page' );
$r2 = flxst_t_op( 'FlxStudioItems', array( 'first' => 5, 'after' => $r['data']['flxStudioItems']['pageInfo']['endCursor'] ?? null ), $ayse );
flxt_same( 1, count( $r2['data']['flxStudioItems']['nodes'] ?? array() ), 'items: second page' );
$r = flxst_t_op( 'FlxStudioItems', array( 'folderId' => 0 ), $ayse );
flxt_same( 0, count( $r['data']['flxStudioItems']['nodes'] ?? array() ), 'items: folder filter 0 = outside any folder' );
$r = flxst_t_op( 'FlxStudioItems', array( 'search' => 'elma', 'kind' => 'VIDEO' ), $ayse );
flxt_same( 0, count( $r['data']['flxStudioItems']['nodes'] ?? array() ), 'items: kind filter' );
$r = flxst_t_op( 'FlxStudioItems', array(), $can );
flxt_same( 0, count( $r['data']['flxStudioItems']['nodes'] ?? array() ), 'items: items in a folder of another user stay hidden until it is shared' );
$r = flxst_t_mut( 'FlxStudioFolderUpdate', array( 'id' => $f2['databaseId'], 'shared' => true ), $ayse );
$r = flxst_t_op( 'FlxStudioItems', array(), $can );
flxt_same( 2, count( $r['data']['flxStudioItems']['nodes'] ?? array() ), 'items: shared folder shows them to everyone with Studio access' );
$r = flxst_t_op( 'FlxStudioItem', array( 'id' => $first ), $can );
flxt_same( $first, $r['data']['flxStudioItem']['databaseId'] ?? null, 'item: readable in a shared folder' );
$r = flxst_t_mut( 'FlxStudioItemUpdate', array( 'id' => $first, 'favorite' => true ), $can );
flxt_same( 'NOT_FOUND', flxt_code( $r ), 'item: only the owner changes it → NOT_FOUND' );
$r = flxst_t_op( 'FlxStudioItem', array( 'id' => $first ), $sub );
flxt_same( 'FORBIDDEN', flxt_code( $r ), 'item: no Studio access → FORBIDDEN' );
$r = flxst_t_op( 'FlxStudioItems', array( 'allOwners' => true ), $ayse );
flxt_same( 2, count( $r['data']['flxStudioItems']['nodes'] ?? array() ), 'items: allOwners is ignored without manage' );

// Update, move, delete.
$r = flxst_t_mut( 'FlxStudioItemUpdate', array( 'id' => $first, 'favorite' => true, 'title' => 'Sevilen elma' ), $ayse );
flxt_same( array( true, 'Sevilen elma' ), array( $r['data']['flxStudioItemUpdate']['item']['favorite'] ?? null, $r['data']['flxStudioItemUpdate']['item']['title'] ?? null ), 'item: starred and renamed' );
$r = flxst_t_op( 'FlxStudioItems', array( 'favorite' => true ), $ayse );
flxt_same( array( $first ), array_column( $r['data']['flxStudioItems']['nodes'] ?? array(), 'databaseId' ), 'items: favorite filter' );
$r = flxst_t_mut( 'FlxStudioItemsMove', array( 'ids' => array( $first ), 'folderId' => 0 ), $ayse );
flxt_same( array( $first ), $r['data']['flxStudioItemsMove']['movedIds'] ?? null, 'items: moved out of the folder' );
$r = flxst_t_mut( 'FlxStudioItemsMove', array( 'ids' => array( $first ), 'folderId' => $f1['databaseId'] ), $can );
flxt_ok( 'FORBIDDEN' === flxt_code( $r ) || array() === ( $r['data']['flxStudioItemsMove']['movedIds'] ?? null ), 'items: a stranger moves nothing', $r );
$r = flxst_t_mut( 'FlxStudioItemToLibrary', array( 'id' => $first ), $ayse );
flxt_same( 'FORBIDDEN', flxt_code( $r ), 'library: needs upload_files → FORBIDDEN' );
( new WP_User( $ayse ) )->add_cap( 'upload_files' );
wp_set_current_user( 0 ); // Reload the user's capabilities.
$r = flxst_t_mut( 'FlxStudioItemToLibrary', array( 'id' => $first ), $ayse );
flxt_ok( ( $r['data']['flxStudioItemToLibrary']['library']['attachmentId'] ?? 0 ) > 0, 'library: attachment made', array( $r, current_user_can( 'upload_files' ), $first ) );
$r = flxst_t_mut( 'FlxStudioItemsDelete', array( 'ids' => array( $first, 999999 ) ), $can );
flxt_same( array(), $r['data']['flxStudioItemsDelete']['deletedIds'] ?? null, 'delete: a stranger deletes nothing' );
$r = flxst_t_mut( 'FlxStudioItemsDelete', array( 'ids' => array( $first, 999999 ) ), $ayse );
flxt_same( array( $first ), $r['data']['flxStudioItemsDelete']['deletedIds'] ?? null, 'delete: own item deleted, unknown id skipped' );

// Video, cancel before it is sent.
$r = flxst_t_mut( 'FlxStudioGenerate', array( 'prompt' => 'Dalgalar', 'kind' => 'VIDEO', 'model' => $VID, 'duration' => $vsec, 'audio' => false ), $ayse );
$v = $r['data']['flxStudioGenerate']['items'][0] ?? array();
flxt_same( array( 'VIDEO', $vsec, false ), array( $v['kind'] ?? null, $v['params']['duration'] ?? null, $v['params']['audio'] ?? null ), 'video: duration and sound kept' );
$r = flxst_t_mut( 'FlxStudioCancel', array( 'job' => $v['job'] ?? '' ), $can );
flxt_same( 'NOT_FOUND', flxt_code( $r ), 'cancel: not the owner → NOT_FOUND' );
$r = flxst_t_mut( 'FlxStudioCancel', array( 'job' => $v['job'] ?? '' ), $ayse );
flxt_same( array( 'CANCELLED' ), array_column( $r['data']['flxStudioCancel']['items'] ?? array(), 'state' ), 'cancel: dropped before it was sent' );
$before = $GLOBALS['flxst_t_submits'];
flxst_dispatch( (string) ( $v['job'] ?? '' ) );
flxt_same( $before, $GLOBALS['flxst_t_submits'], 'cancel: a cancelled job is never sent to Flux AI' );

// Area edit needs a model that supports it.
$r = flxst_t_mut( 'FlxStudioGenerate', array( 'prompt' => 'Düzenle', 'parentId' => 999999, 'maskUploadId' => 'x.y' ), $ayse );
flxt_ok( null !== flxt_code( $r ) || ! empty( $r['data']['flxStudioGenerate']['errors'] ), 'edit: invalid mask upload is refused', $r );

/* ---------------------------------------------------------------- Enhance, Brainstorm */

$r = flxst_t_mut( 'FlxStudioEnhance', array( 'prompt' => 'elma', 'mode' => 'IMAGE', 'modelLabel' => 'Model' ), $ayse );
flxt_same( 'A detailed prompt', $r['data']['flxStudioEnhance']['prompt'] ?? null, 'enhance: prompt from the text model' );
$r = flxst_t_mut( 'FlxStudioEnhance', array( 'prompt' => '  ' ), $ayse );
flxt_same( 'INVALID_INPUT', $r['data']['flxStudioEnhance']['errors'][0]['code'] ?? flxt_code( $r ), 'enhance: empty draft → INVALID_INPUT' );
$r = flxst_t_mut( 'FlxStudioBrainstorm', array( 'brief' => 'Kapak', 'count' => 2, 'style' => 'YOUTUBE' ), $ayse );
$bs = $r['data']['flxStudioBrainstorm'] ?? array();
flxt_same( array( 'Bir', 'İki' ), array_column( $bs['ideas'] ?? array(), 'title' ), 'brainstorm: ideas' );
flxt_ok( preg_match( '/^[a-z0-9]{12}$/', (string) ( $bs['batch'] ?? '' ) ) === 1, 'brainstorm: batch id', $bs['batch'] ?? null );

/* ---------------------------------------------------------------- People and references (upload flow) */

$r = flxst_t_mut( 'FlxStudioPersonCreate', array( 'name' => 'Deniz' ), $ayse );
$p = $r['data']['flxStudioPersonCreate']['person'] ?? array();
flxt_same( array( 'Deniz', $ayse, array() ), array( $p['name'] ?? null, $p['ownerId'] ?? null, $p['photos'] ?? null ), 'person: created' );
$r = flxst_t_op( 'FlxStudioWorkspace', array(), $can );
flxt_same( array( $p['databaseId'] ), array_column( $r['data']['flxStudio']['people'] ?? array(), 'databaseId' ), 'person: shared with every Studio user by default' );

/** Simulates POST /flx/v1/upload: a file in the private folder and its transient. */
function flxst_t_upload( int $user, string $context ): string {
	$id     = wp_generate_password( 20, false );
	$secret = wp_generate_password( 12, false );
	$path   = flx_gql_upload_dir() . '/' . $id . '.png';
	imagepng( imagecreatetruecolor( 12, 10 ), $path );
	set_transient( 'flxgql_up_' . $id, array( 'path' => $path, 'name' => 'foto.png', 'mime' => 'image/png', 'size' => (int) filesize( $path ), 'user' => $user, 'context' => $context, 'secret' => wp_hash( $secret ), 'created' => time() ), HOUR_IN_SECONDS );
	return $id . '.' . $secret;
}

$up = flxst_t_upload( $ayse, 'flx-studio/reference' );
$r  = flxst_t_mut( 'FlxStudioRefCreate', array( 'uploadId' => $up, 'personId' => $p['databaseId'], 'name' => 'Önden', 'role' => 'identity' ), $can );
flxt_ok( null !== flxt_code( $r ) || ! empty( $r['data']['flxStudioRefCreate']['errors'] ), 'ref: another user cannot take the upload', $r );
$r = flxst_t_mut( 'FlxStudioRefCreate', array( 'uploadId' => $up, 'personId' => $p['databaseId'], 'name' => 'Önden', 'role' => 'identity' ), $ayse );
$ref = $r['data']['flxStudioRefCreate']['ref'] ?? array();
flxt_same( array( $p['databaseId'], 'Önden', 'identity', true, 12, 10 ), array( $ref['personId'] ?? null, $ref['name'] ?? null, $ref['role'] ?? null, $ref['saved'] ?? null, $ref['width'] ?? null, $ref['height'] ?? null ), 'ref: a person photo from the upload (always saved)' );
$r = flxst_t_mut( 'FlxStudioRefCreate', array( 'uploadId' => $up ), $ayse );
flxt_ok( null !== flxt_code( $r ), 'ref: an upload can be taken only once', $r );
$r = flxst_t_mut( 'FlxStudioRefCreate', array( 'uploadId' => flxst_t_upload( $ayse, 'flx-studio/reference' ), 'name' => 'Stil', 'saved' => true ), $ayse );
$ref2 = $r['data']['flxStudioRefCreate']['ref'] ?? array();
flxt_same( array( null, true ), array( array_key_exists( 'personId', $ref2 ) ? $ref2['personId'] : 'x', $ref2['saved'] ?? null ), 'ref: a saved reference without a person' );
$r = flxst_t_mut( 'FlxStudioRefUpdate', array( 'id' => $ref2['databaseId'], 'role' => 'style', 'name' => 'Stil 1' ), $ayse );
flxt_same( array( 'style', 'Stil 1' ), array( $r['data']['flxStudioRefUpdate']['ref']['role'] ?? null, $r['data']['flxStudioRefUpdate']['ref']['name'] ?? null ), 'ref: role and name changed' );
$r = flxst_t_mut( 'FlxStudioRefUpdate', array( 'id' => $ref2['databaseId'], 'name' => 'X' ), $can );
flxt_same( 'NOT_FOUND', flxt_code( $r ), 'ref: a stranger cannot change it → NOT_FOUND' );
$r = flxst_t_mut( 'FlxStudioGenerate', array( 'prompt' => 'Deniz sahilde', 'refs' => array( array( 'type' => 'PERSON', 'id' => $p['databaseId'], 'role' => 'identity' ), array( 'type' => 'REF', 'id' => $ref2['databaseId'] ) ) ), $ayse );
$g = $r['data']['flxStudioGenerate']['items'][0] ?? array();
flxt_same( array( 'PERSON', 'REF' ), array_column( $g['params']['refs'] ?? array(), 'type' ), 'generate: references are resolved and kept with the item' );
$r = flxst_t_mut( 'FlxStudioPersonRename', array( 'id' => $p['databaseId'], 'name' => 'Deniz K.' ), $ayse );
flxt_same( 'Deniz K.', $r['data']['flxStudioPersonRename']['person']['name'] ?? null, 'person: renamed' );
$r = flxst_t_mut( 'FlxStudioPersonDelete', array( 'id' => $p['databaseId'] ), $can );
flxt_same( 'NOT_FOUND', flxt_code( $r ), 'person: a stranger cannot delete → NOT_FOUND' );
$r = flxst_t_mut( 'FlxStudioPersonDelete', array( 'id' => $p['databaseId'] ), $ayse );
flxt_same( $p['databaseId'], $r['data']['flxStudioPersonDelete']['deletedId'] ?? null, 'person: deleted with the photos' );
$r = flxst_t_mut( 'FlxStudioRefDelete', array( 'id' => $ref2['databaseId'] ), $ayse );
flxt_same( $ref2['databaseId'], $r['data']['flxStudioRefDelete']['deletedId'] ?? null, 'ref: deleted' );

/* ---------------------------------------------------------------- Templates */

$r = flxst_t_mut( 'FlxStudioTemplateCreate', array( 'name' => 'Kare elma', 'prompt' => 'elma', 'shared' => true, 'params' => array( 'kind' => 'IMAGE', 'model' => $IMG, 'aspect' => '1:1', 'count' => 2 ) ), $ayse );
$tpls = $r['data']['flxStudioTemplateCreate']['templates'] ?? array();
flxt_same( array( 'Kare elma', 'IMAGE', $IMG, 2 ), array( $tpls[0]['name'] ?? null, $tpls[0]['params']['kind'] ?? null, $tpls[0]['params']['model'] ?? null, $tpls[0]['params']['count'] ?? null ), 'template: saved with its settings' );
$r = flxst_t_mut( 'FlxStudioTemplateDelete', array( 'id' => $tpls[0]['databaseId'] ?? 0 ), $can );
flxt_same( 'NOT_FOUND', flxt_code( $r ), 'template: a stranger cannot delete → NOT_FOUND' );
$r = flxst_t_mut( 'FlxStudioTemplateDelete', array( 'id' => $tpls[0]['databaseId'] ?? 0 ), $ayse );
flxt_same( array(), $r['data']['flxStudioTemplateDelete']['templates'] ?? null, 'template: deleted' );

/* ---------------------------------------------------------------- Settings, stats (administrator) */

$r = flxst_t_mut( 'FlxStudioSettingsUpdate', array( 'maxCount' => 99, 'maxBatch' => 0, 'sharedPeople' => false, 'textModel' => 'gpt-test' ), $admin );
$s = $r['data']['flxStudioSettingsUpdate']['settings'] ?? array();
flxt_same( array( 8, 1, false, 'gpt-test' ), array( $s['maxCount'] ?? null, $s['maxBatch'] ?? null, $s['sharedPeople'] ?? null, $s['textModel'] ?? null ), 'settings: values are clamped (1–8, 1–40)' );
flxt_same( 8, (int) ( flxst_settings()['max_n'] ?? 0 ), 'settings: the option holds the same (REST and the classic screen read it)' );
$r = flxst_t_op( 'FlxStudioWorkspace', array(), $ayse );
flxt_same( null, array_key_exists( 'textModel', $r['data']['flxStudio']['settings'] ?? array() ) ? $r['data']['flxStudio']['settings']['textModel'] : 'x', 'settings: the text model is hidden from Studio users' );
update_option( 'flxst_settings', array_merge( (array) get_option( 'flxst_settings', array() ), array( 'max_n' => 100 ) ) );
flxt_same( 8, (int) ( get_option( 'flxst_settings', array() )['max_n'] ?? 0 ), 'settings: any write to the option is clamped (presets, flxSettingsUpdate)' );
flxst_t_mut( 'FlxStudioSettingsUpdate', array( 'maxCount' => 4, 'maxBatch' => 20, 'sharedPeople' => true, 'textModel' => '' ), $admin );

$r = flxst_t_op( 'FlxStudioStats', array( 'days' => 7 ), $admin );
flxt_same( null, $r['errors'] ?? null, 'stats: no errors for the administrator' );
$st = $r['data']['flxStudioStats'] ?? array();
$rs = flxst_t_rest( 'GET', '/stats', array( 'days' => 7 ), $admin );
flxt_same( array( (int) $rs['data']['totals']['image'], (int) $rs['data']['totals']['failed'] ), array( $st['image'] ?? null, $st['failed'] ?? null ), 'parity: REST and GraphQL stats totals' );
flxt_same( 7, $st['days'] ?? null, 'stats: days' );
$r = flxst_t_op( 'FlxStudioItems', array( 'allOwners' => true, 'first' => 50 ), $admin );
flxt_ok( count( $r['data']['flxStudioItems']['nodes'] ?? array() ) >= 2, 'items: administrators see every user\'s items with allOwners' );

// Budget query.
$r = flxst_t_op( 'FlxStudioBudget', array(), $ayse );
$bud = flxai_budget_status( true );
flxt_same( array( (string) array_key_first( $bud ), (float) reset( $bud )['pct'] ), array( $r['data']['flxStudioBudget'][0]['period'] ?? null, (float) ( $r['data']['flxStudioBudget'][0]['pct'] ?? -1 ) ), 'budget: from Flux AI' );

// Folder delete: items move up.
$r = flxst_t_mut( 'FlxStudioFolderDelete', array( 'id' => $f1['databaseId'] ), $ayse );
flxt_same( $f1['databaseId'], $r['data']['flxStudioFolderDelete']['deletedId'] ?? null, 'folder: deleted' );
$r = flxst_t_op( 'FlxStudioWorkspace', array(), $ayse );
$left = array_column( $r['data']['flxStudio']['folders'] ?? array(), 'parentId', 'databaseId' );
flxt_same( null, array_key_exists( $f2['databaseId'], $left ) ? $left[ $f2['databaseId'] ] : 'x', 'folder: subfolders move up one level' );

flxt_done();
