<?php
/**
 * Flux Tasks GraphQL contract tests (pilot). The documents are the persisted operations the screens
 * send (plugins/flx-tasks/graphql/persisted-queries.json), so a schema change that breaks a screen
 * breaks here too. REST parity: the same service answers /flx-tasks/v1.
 *
 *   wp eval-file tools/tests/wp-graphql/flx-tasks/contract-test.php
 */

require dirname( __DIR__ ) . '/lib.php';

echo "Flux Tasks GraphQL\n";

$GLOBALS["flxt_ops"] = array();
foreach ( (array) json_decode( (string) file_get_contents( FLXG_DIR . 'graphql/persisted-queries.json' ), true ) as $doc ) {
	if ( preg_match( '/^(?:query|mutation) (Flx\w+)/', $doc, $m ) ) {
		$GLOBALS["flxt_ops"][ $m[1] ] = $doc;
	}
}
flxt_same( 9, count( $GLOBALS["flxt_ops"] ), 'the pilot ships 9 persisted operations' );

/** Value at a path; 'missing' when a key is absent (null stays null). */
function flxt_at( $a, string ...$keys ) {
	foreach ( $keys as $k ) {
		if ( ! is_array( $a ) || ! array_key_exists( $k, $a ) ) {
			return 'missing';
		}
		$a = $a[ $k ];
	}
	return $a;
}

function flxt_op( string $name, array $vars = array(), int $user = 0 ): array {
	return flxt_gql( $GLOBALS["flxt_ops"][ $name ], $vars, $user );
}

function flxt_rest( string $method, string $path, array $body = array(), int $user = 1 ) {
	wp_set_current_user( $user );
	$req = new WP_REST_Request( $method, '/flx-tasks/v1' . $path );
	if ( $body ) {
		$req->set_body_params( $body );
	}
	return rest_do_request( $req );
}

// Fixtures over REST (also checks the REST API still works on top of the service).
$admin = 1;
$res   = flxt_rest( 'POST', '/spaces', array( 'name' => 'GQL pilot ' . wp_rand(), 'list' => 'Inbox', 'access' => 'members' ) );
flxt_same( 200, $res->get_status(), 'REST: project created' );
$space = (int) $res->get_data()['id'];
$list  = 0;
foreach ( $res->get_data()['lists'] as $l ) {
	if ( (int) $l['space_id'] === $space ) {
		$list = (int) $l['id'];
	}
}
flxt_ok( $list > 0, 'REST: project has a list' );

$res = flxt_rest( 'POST', '/tasks', array( 'list' => $list, 'title' => 'REST task' ) );
flxt_same( 200, $res->get_status(), 'REST: task created through the service' );
$rest_task = $res->get_data()['task'] ?? array();
flxt_same( 'REST task', $rest_task['title'] ?? null, 'REST: create returns the task' );
$res = flxt_rest( 'GET', '/tasks/' . (int) $rest_task['id'] );
flxt_ok( isset( $res->get_data()['task']['comments_list'], $res->get_data()['unread'] ), 'REST: task screen data unchanged (comments_list, unread)' );

$member = wp_insert_user( array( 'user_login' => 'flxt_tasks_' . wp_rand(), 'user_pass' => wp_generate_password(), 'role' => 'subscriber' ) );
( new WP_User( (int) $member ) )->add_cap( 'flxg_use' );

// Create.
$r = flxt_op( 'FlxTaskCreate', array( 'input' => array( 'listId' => $list, 'title' => 'Pilot task', 'priority' => 2, 'dueDate' => '2026-12-01' ) ), $admin );
$t = $r['data']['flxTaskCreate']['task'] ?? array();
flxt_same( array(), $r['data']['flxTaskCreate']['errors'] ?? null, 'create: no errors' );
flxt_same( array( 'Pilot task', 2, '2026-12-01', 'TASK' ), array( $t['title'] ?? null, $t['priority'] ?? null, $t['dueDate'] ?? null, $t['kind'] ?? null ), 'create: title, priority, due date, kind' );
$id = (int) ( $t['databaseId'] ?? 0 );
flxt_ok( $id > 0, 'create: databaseId' );

$r = flxt_op( 'FlxTaskCreate', array( 'input' => array( 'listId' => $list, 'title' => '  ' ) ), $admin );
flxt_same( 'INVALID_INPUT', $r['data']['flxTaskCreate']['errors'][0]['code'] ?? null, 'create: empty title → payload INVALID_INPUT' );
$r = flxt_op( 'FlxTaskCreate', array( 'input' => array( 'listId' => $list, 'title' => 'x' ) ), (int) $member );
flxt_same( 'FORBIDDEN', flxt_code( $r ), 'create: user without list access → FORBIDDEN' );
$r = flxt_op( 'FlxTaskCreate', array( 'input' => array( 'listId' => $list, 'title' => 'x' ) ) );
flxt_same( 'UNAUTHENTICATED', flxt_code( $r ), 'create: visitor → UNAUTHENTICATED' );

// Read.
$gid = base64_encode( 'FlxTask:' . $id );
$r   = flxt_op( 'FlxTaskDetail', array( 'id' => $gid ), $admin );
$d   = $r['data']['flxTask'] ?? array();
flxt_same( $id, $d['databaseId'] ?? null, 'detail: by global ID' );
flxt_same( array( 'ADMIN', true ), array( $d['role'] ?? null, $d['watching'] ?? null ), 'detail: role and creator follows the task' );
$r = flxt_op( 'FlxTaskDetail', array( 'id' => (string) $id ), $admin );
flxt_same( $id, $r['data']['flxTask']['databaseId'] ?? null, 'detail: by task number' );
$r = flxt_op( 'FlxTaskDetail', array( 'id' => (string) $id ), (int) $member );
flxt_same( array( null, null ), array( flxt_at( $r, 'data', 'flxTask' ), flxt_code( $r ) ), 'detail: invisible task is null, not an error' );
$r = flxt_op( 'FlxTaskDetail', array( 'id' => base64_encode( 'FlxDonation:' . $id ) ), $admin );
flxt_same( 'INVALID_INPUT', flxt_code( $r ), 'detail: ID of another type → INVALID_INPUT' );
$r = flxt_gql( '{ node(id:"' . $gid . '") { __typename ... on FlxTask { title } } }', array(), $admin );
flxt_same( array( '__typename' => 'FlxTask', 'title' => 'Pilot task' ), $r['data']['node'] ?? null, 'node(id:) resolves FlxTask' );

// Update.
$r = flxt_op( 'FlxTaskUpdate', array( 'input' => array( 'id' => $gid, 'title' => 'Renamed', 'dueDate' => null, 'assigneeIds' => array( $admin ) ) ), $admin );
$u = $r['data']['flxTaskUpdate']['task'] ?? array();
flxt_same( array( 'Renamed', null, array( $admin ), 2 ), array( $u['title'] ?? null, flxt_at( $u, 'dueDate' ), $u['assigneeIds'] ?? null, $u['priority'] ?? null ), 'update: changes given fields, null clears the date, others stay' );
$r = flxt_op( 'FlxTaskUpdate', array( 'input' => array( 'id' => $gid, 'title' => 'x' ) ), (int) $member );
flxt_same( 'FORBIDDEN', flxt_code( $r ), 'update: no access → FORBIDDEN' );

// Children, list, search.
$r = flxt_op( 'FlxTaskCreate', array( 'input' => array( 'listId' => $list, 'title' => 'Child', 'parentId' => $id ) ), $admin );
$child = (int) ( $r['data']['flxTaskCreate']['task']['databaseId'] ?? 0 );
$r = flxt_op( 'FlxTaskDetail', array( 'id' => (string) $id ), $admin );
flxt_same( array( $child ), array_column( $r['data']['flxTask']['children'] ?? array(), 'databaseId' ), 'detail: children' );
$r = flxt_op( 'FlxTaskDetail', array( 'id' => (string) $child ), $admin );
flxt_same( 'Renamed', $r['data']['flxTask']['parent']['title'] ?? null, 'detail: parent through the loader' );
$r = flxt_op( 'FlxTaskList', array( 'listId' => $list, 'parentId' => 0, 'first' => 1 ), $admin );
$c = $r['data']['flxTasks'] ?? array();
flxt_same( array( 2, 1, true ), array( $c['totalCount'] ?? null, count( $c['nodes'] ?? array() ), $c['pageInfo']['hasNextPage'] ?? null ), 'list: top-level tasks, first page of 1 of 2' );
$r = flxt_op( 'FlxTaskList', array( 'listId' => $list, 'parentId' => 0, 'first' => 1, 'after' => $c['pageInfo']['endCursor'] ?? '' ), $admin );
flxt_same( false, $r['data']['flxTasks']['pageInfo']['hasNextPage'] ?? null, 'list: second page is the last' );
$r = flxt_op( 'FlxTaskList', array( 'search' => 'Renamed' ), $admin );
flxt_same( array( $id ), array_column( $r['data']['flxTasks']['nodes'] ?? array(), 'databaseId' ), 'list: search across visible lists' );
$r = flxt_op( 'FlxTaskList', array( 'listId' => $list ), (int) $member );
flxt_same( 'NOT_FOUND', flxt_code( $r ), 'list: invisible list → NOT_FOUND' );
$r = flxt_op( 'FlxTaskList', array( 'search' => 'Renamed' ), (int) $member );
flxt_same( array(), $r['data']['flxTasks']['nodes'] ?? null, 'list: other users do not see the project' );

// Comment, watch, notifications.
$r = flxt_op( 'FlxTaskCommentCreate', array( 'input' => array( 'taskId' => $gid, 'body' => 'Hello' ) ), $admin );
flxt_same( array( 'Hello', $admin, $id ), array( $r['data']['flxTaskCommentCreate']['comment']['body'] ?? null, $r['data']['flxTaskCommentCreate']['comment']['authorId'] ?? null, $r['data']['flxTaskCommentCreate']['comment']['taskId'] ?? null ), 'comment: created' );
$r = flxt_op( 'FlxTaskCommentCreate', array( 'input' => array( 'taskId' => $gid, 'body' => '' ) ), $admin );
flxt_same( 'INVALID_INPUT', $r['data']['flxTaskCommentCreate']['errors'][0]['code'] ?? null, 'comment: empty → payload INVALID_INPUT' );
$r = flxt_op( 'FlxTaskDetail', array( 'id' => (string) $id ), $admin );
flxt_same( array( 1, 'Hello' ), array( $r['data']['flxTask']['commentCount'] ?? null, $r['data']['flxTask']['comments'][0]['body'] ?? null ), 'detail: comments and count' );
$r = flxt_op( 'FlxTaskWatch', array( 'input' => array( 'id' => $gid, 'watch' => false ) ), $admin );
flxt_same( array(), $r['data']['flxTaskWatch']['watcherIds'] ?? null, 'watch: stop following' );
$r = flxt_op( 'FlxTaskNotificationsRead', array( 'input' => array( 'taskId' => $gid ) ), $admin );
flxt_ok( is_int( $r['data']['flxTaskNotificationsRead']['unread'] ?? null ), 'notifications read: unread count' );

// Task screen parity with REST GET /tasks/<id>.
$r    = flxt_op( 'FlxTaskDetail', array( 'id' => (string) $id ), $admin );
$g    = $r['data']['flxTask'] ?? array();
$rest = flxt_rest( 'GET', '/tasks/' . $id )->get_data()['task'];
flxt_same( count( $rest['activity'] ), count( $g['activity'] ?? array() ), 'screen: activity entries match REST' );
flxt_same( array_column( $rest['activity'], 'action' ), array_column( $g['activity'] ?? array(), 'action' ), 'screen: activity actions match REST' );
flxt_same( array( $rest['depth'], $rest['depthLimit'] ), array( $g['depth'] ?? null, $g['depthLimit'] ?? null ), 'screen: depth and limit match REST' );
flxt_same( array_column( $rest['children'], 'id' ), array_column( $g['children'] ?? array(), 'databaseId' ), 'screen: children match REST' );
flxt_same( $rest['watchers'], $g['watcherIds'] ?? null, 'screen: watchers match REST' );
$r = flxt_op( 'FlxTaskUpdateDetail', array( 'input' => array( 'id' => (string) $id, 'status' => 'done' ) ), $admin );
$g = $r['data']['flxTaskUpdate']['task'] ?? array();
flxt_same( 'done', $g['status'] ?? null, 'update from the screen: new status' );
flxt_ok( in_array( 'status', array_column( $g['activity'] ?? array(), 'action' ), true ), 'update from the screen: answer has the fresh history' );

// Delete.
$r = flxt_op( 'FlxTaskDelete', array( 'input' => array( 'id' => $gid ) ), (int) $member );
flxt_same( 'FORBIDDEN', flxt_code( $r ), 'delete: no access → FORBIDDEN' );
$r = flxt_op( 'FlxTaskDelete', array( 'input' => array( 'id' => $gid ) ), $admin );
flxt_same( $id, $r['data']['flxTaskDelete']['deletedId'] ?? null, 'delete: deleted' );
$r = flxt_op( 'FlxTaskDetail', array( 'id' => (string) $id ), $admin );
flxt_same( null, flxt_at( $r, 'data', 'flxTask' ), 'delete: task is gone' );

// Persisted: the screens' documents are allowed in strict mode.
$hash = hash( "sha256", $GLOBALS["flxt_ops"]["FlxTaskDetail"] );
wp_set_current_user( $admin );
$r = graphql( array( 'queryId' => $hash, 'variables' => array( 'id' => (string) $rest_task['id'] ) ) );
flxt_same( 'REST task', $r['data']['flxTask']['title'] ?? null, 'persisted queryId runs (strict mode)' );

require __DIR__ . '/docs-test.php';
require __DIR__ . '/gantt-test.php';

flxt_done();
