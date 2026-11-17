<?php
/**
 * Flux Tasks document comments and page protection (lock, access): REST (the editor) and GraphQL share
 * FLXG_Doc_Service. Loaded by contract-test.php (helpers and $admin come from there).
 */

echo "Flux Tasks documents: comments, lock, access\n";

$space = (int) flxt_rest( 'POST', '/spaces', array( 'name' => 'Docs ' . wp_rand(), 'list' => 'Inbox', 'access' => 'members' ) )->get_data()['id'];
$res   = flxt_rest( 'POST', '/docs', array( 'space' => $space, 'title' => 'Handbook' ) );
$doc   = (int) $res->get_data()['id'];
$res   = flxt_rest( 'GET', '/docs/' . $doc );
$page  = (int) ( $res->get_data()['pages'][0]['id'] ?? 0 );
flxt_ok( $page > 0, 'doc: first page' );
flxt_same( array( 'admin', null, false ), array( $res->get_data()['pages'][0]['role'] ?? null, flxt_at( $res->get_data(), 'pages', '0', 'locked' ), $res->get_data()['pages'][0]['restricted'] ?? null ), 'doc: page rows carry role, lock and access' );
$res = flxt_rest( 'POST', '/doc-pages/' . $page . '/sync', array( 'since' => 0, 'ops' => array( array( 'b' => 'b1', 'html' => '<p>Hello brave new world</p>', 'after' => '' ) ) ) );
flxt_same( 200, $res->get_status(), 'sync: write a block' );

// People: editor, commenter, viewer, outsider (no project access).
$mk = static function ( string $n ): int {
	$u = (int) wp_insert_user( array( 'user_login' => 'flxt_' . $n . '_' . wp_rand(), 'user_pass' => wp_generate_password(), 'role' => 'subscriber' ) );
	( new WP_User( $u ) )->add_cap( 'flxg_use' );
	return $u;
};
$ed  = $mk( 'ed' );
$cm  = $mk( 'cm' );
$vw  = $mk( 'vw' );
$out = $mk( 'out' );
$res = flxt_rest( 'POST', '/spaces/' . $space . '/members', array( 'members' => array( array( 'user' => $ed, 'role' => 'edit' ), array( 'user' => $cm, 'role' => 'comment' ), array( 'user' => $vw, 'role' => 'view' ) ) ) );
flxt_same( 200, $res->get_status(), 'members saved' );
flxg_memberships( $ed, true );
flxg_memberships( $cm, true );
flxg_memberships( $vw, true );

// Comments over REST.
$anchor = array( 'b' => 'b1', 'o' => 6, 'eb' => 'b1', 'eo' => 11, 'q' => 'brave' );
$res    = flxt_rest( 'POST', '/doc-pages/' . $page . '/comments', array( 'body' => 'Is this right?', 'anchor' => $anchor ), $cm );
$c1     = $res->get_data()['comment'] ?? array();
flxt_same( array( 200, 'Is this right?', 'brave', 'text', true, true ), array( $res->get_status(), $c1['body'] ?? null, $c1['anchor']['q'] ?? null, $c1['anchor']['kind'] ?? null, $c1['canEdit'] ?? null, $c1['canDelete'] ?? null ), 'comment: commenter starts a thread on a text range' );
$res = flxt_rest( 'POST', '/doc-pages/' . $page . '/comments', array( 'body' => 'x', 'anchor' => $anchor ), $vw );
flxt_same( 403, $res->get_status(), 'comment: viewer cannot comment' );
$res = flxt_rest( 'POST', '/doc-pages/' . $page . '/comments', array( 'body' => 'x' ), $cm );
flxt_same( 'flxg_anchor', $res->get_data()['code'] ?? null, 'comment: a thread needs an anchor' );
$res = flxt_rest( 'GET', '/doc-pages/' . $page . '/comments', array(), $out );
flxt_same( 404, $res->get_status(), 'comment: outsider cannot read' );
$res = flxt_rest( 'POST', '/doc-pages/' . $page . '/comments', array( 'body' => 'Yes, fine.', 'parent' => (int) $c1['id'] ), $ed );
$r1  = $res->get_data()['comment'] ?? array();
flxt_same( array( 200, (int) $c1['id'] ), array( $res->get_status(), $r1['parent'] ?? null ), 'reply: anyone who can comment replies under a thread' );
$res = flxt_rest( 'POST', '/doc-comments/' . (int) $c1['id'], array( 'body' => 'changed' ), $ed );
flxt_same( 403, $res->get_status(), 'edit: only the author edits' );
$res = flxt_rest( 'POST', '/doc-comments/' . (int) $c1['id'], array( 'body' => 'Is this word right?' ), $cm );
flxt_same( array( 'Is this word right?', true ), array( $res->get_data()['comment']['body'] ?? null, null !== ( $res->get_data()['comment']['edited'] ?? null ) ), 'edit: author edits, edited time set' );
$res = flxt_rest( 'POST', '/doc-comments/' . (int) $c1['id'], array( 'resolved' => 1 ), $ed );
flxt_same( $ed, $res->get_data()['comment']['resolved']['by'] ?? null, 'resolve: an editor resolves the thread' );
$rev = (int) ( $res->get_data()['rev'] ?? 0 );
$res = flxt_rest( 'POST', '/doc-pages/' . $page . '/sync', array( 'since' => 0 ), $vw );
flxt_same( array( $rev, 'view' ), array( $res->get_data()['cmRev'] ?? null, $res->get_data()['role'] ?? null ), 'sync: tells the comment revision and page role' );
$res = flxt_rest( 'DELETE', '/doc-comments/' . (int) $r1['id'], array(), $cm );
flxt_same( 403, $res->get_status(), 'delete: someone else\'s reply cannot be deleted by a commenter' );
$res = flxt_rest( 'DELETE', '/doc-comments/' . (int) $c1['id'], array(), $cm );
$all = flxt_rest( 'GET', '/doc-pages/' . $page . '/comments', array(), $cm )->get_data()['comments'] ?? array();
flxt_same( array( 2, true, '' ), array( count( $all ), $all[0]['deleted'] ?? null, $all[0]['body'] ?? null ), 'delete: a thread with replies stays as deleted' );
$res = flxt_rest( 'DELETE', '/doc-comments/' . (int) $r1['id'], array(), $admin );
$all = flxt_rest( 'GET', '/doc-pages/' . $page . '/comments', array(), $cm )->get_data()['comments'] ?? array();
flxt_same( array( 200, 0 ), array( $res->get_status(), count( $all ) ), 'delete: project admin deletes; the last reply takes the deleted thread with it' );

// Lock.
$res = flxt_rest( 'POST', '/doc-pages/' . $page . '/lock', array( 'locked' => 1 ), $cm );
flxt_same( 403, $res->get_status(), 'lock: commenter cannot lock' );
$res = flxt_rest( 'POST', '/doc-pages/' . $page . '/lock', array( 'locked' => 1 ), $ed );
flxt_same( $ed, $res->get_data()['locked']['by'] ?? null, 'lock: editor locks the page' );
$res = flxt_rest( 'POST', '/doc-pages/' . $page . '/sync', array( 'since' => 0, 'ops' => array( array( 'b' => 'b1', 'html' => '<p>Changed</p>' ) ) ), $admin );
flxt_same( array( 423, 'flxg_locked' ), array( $res->get_status(), $res->get_data()['code'] ?? null ), 'lock: writes are refused (even for admins) until unlocked' );
$res = flxt_rest( 'POST', '/doc-pages/' . $page, array( 'title' => 'New' ), $ed );
flxt_same( 423, $res->get_status(), 'lock: rename refused' );
$res = flxt_rest( 'POST', '/doc-pages/' . $page . '/comments', array( 'body' => 'Still commentable', 'anchor' => $anchor ), $cm );
flxt_same( 200, $res->get_status(), 'lock: comments still work on a locked page' );
$res = flxt_rest( 'POST', '/doc-pages/' . $page . '/sync', array( 'since' => 0 ), $ed );
flxt_same( $ed, $res->get_data()['locked']['by'] ?? null, 'lock: sync tells others the page is locked' );
$res = flxt_rest( 'POST', '/doc-pages/' . $page . '/lock', array( 'locked' => 0 ), $admin );
flxt_same( null, flxt_at( $res->get_data(), 'locked' ), 'unlock: anyone who can edit unlocks' );
$res = flxt_rest( 'POST', '/doc-pages/' . $page . '/sync', array( 'since' => 0, 'ops' => array( array( 'b' => 'b1', 'html' => '<p>Hello brave new world!</p>' ) ) ), $ed );
flxt_same( 200, $res->get_status(), 'unlock: writes work again' );

// Access: restricted page (and its subpage) only for the listed people and project admins.
$res = flxt_rest( 'POST', '/docs/' . $doc . '/pages', array( 'title' => 'Secret', 'parent' => 0 ) );
$sec = (int) $res->get_data()['id'];
$res = flxt_rest( 'POST', '/docs/' . $doc . '/pages', array( 'title' => 'Secret child', 'parent' => $sec ) );
$kid = (int) $res->get_data()['id'];
$res = flxt_rest( 'POST', '/doc-pages/' . $sec . '/access', array( 'access' => 'restricted', 'members' => array( array( 'user' => $cm, 'role' => 'edit' ), array( 'user' => $out, 'role' => 'edit' ) ) ), $ed );
$mem = array_column( $res->get_data()['members'] ?? array(), 'role', 'user' );
flxt_same( array( 'restricted', 'edit', 'edit', false ), array( $res->get_data()['access'] ?? null, $mem[ $ed ] ?? null, $mem[ $cm ] ?? null, isset( $mem[ $out ] ) ), 'access: restrict keeps the editor, adds members, ignores outsiders' );
$ids = static fn( int $u ): array => array_column( flxt_rest( 'GET', '/docs/' . $doc, array(), $u )->get_data()['pages'] ?? array(), 'id' );
flxt_same( array( true, true ), array( in_array( $sec, $ids( $cm ), true ), in_array( $kid, $ids( $cm ), true ) ), 'access: member sees the page and its subpage' );
flxt_same( array( false, false, true ), array( in_array( $sec, $ids( $vw ), true ), in_array( $kid, $ids( $vw ), true ), in_array( $page, $ids( $vw ), true ) ), 'access: others do not see them (other pages stay)' );
flxt_same( 404, flxt_rest( 'GET', '/doc-pages/' . $kid, array(), $vw )->get_status(), 'access: subpage of a restricted page is closed' );
flxt_same( 'comment', flxt_rest( 'GET', '/doc-pages/' . $sec, array(), $cm )->get_data()['role'] ?? null, 'access: member role is capped by the project role' );
flxt_same( 'admin', flxt_rest( 'GET', '/doc-pages/' . $sec, array(), $admin )->get_data()['role'] ?? null, 'access: project admin always has access' );

// GraphQL: same service.
$q = 'query($p:Int!){ flxTaskDocPage(pageId:$p){ pageId role restricted lockedBy commentCount } flxTaskDocComments(pageId:$p){ databaseId body anchor { blockId quote kind } canEdit } }';
$r = flxt_gql( $q, array( 'p' => $page ), $cm );
flxt_same( array( 'comment', false, null, 1 ), array( $r['data']['flxTaskDocPage']['role'] ?? null, $r['data']['flxTaskDocPage']['restricted'] ?? null, flxt_at( $r, 'data', 'flxTaskDocPage', 'lockedBy' ), $r['data']['flxTaskDocPage']['commentCount'] ?? null ), 'GraphQL: page protection for the viewer' );
flxt_same( array( 'Still commentable', 'b1', 'brave', 'text' ), array( $r['data']['flxTaskDocComments'][0]['body'] ?? null, $r['data']['flxTaskDocComments'][0]['anchor']['blockId'] ?? null, $r['data']['flxTaskDocComments'][0]['anchor']['quote'] ?? null, $r['data']['flxTaskDocComments'][0]['anchor']['kind'] ?? null ), 'GraphQL: comments with their anchor' );
$r = flxt_gql( $q, array( 'p' => $sec ), $vw );
flxt_same( array( null, 'NOT_FOUND' ), array( $r['data']['flxTaskDocPage'] ?? null, flxt_code( $r ) ), 'GraphQL: restricted page is empty for others' );
$m = 'mutation($i:FlxTaskDocCommentCreateInput!){ flxTaskDocCommentCreate(input:$i){ comment { databaseId parentId body } errors { code } } }';
$r = flxt_gql( $m, array( 'i' => array( 'pageId' => $page, 'body' => 'From GraphQL', 'blockId' => 'b1', 'offset' => 0, 'endBlockId' => 'b1', 'endOffset' => 5, 'quote' => 'Hello' ) ), $ed );
$gc = (int) ( $r['data']['flxTaskDocCommentCreate']['comment']['databaseId'] ?? 0 );
flxt_ok( $gc > 0, 'GraphQL: create a thread' );
$r = flxt_gql( $m, array( 'i' => array( 'pageId' => $page, 'body' => 'x', 'blockId' => 'b1' ) ), $vw );
flxt_same( 'FORBIDDEN', flxt_code( $r ) ?? ( $r['data']['flxTaskDocCommentCreate']['errors'][0]['code'] ?? null ), 'GraphQL: viewer cannot comment' );
$r = flxt_gql( 'mutation($i:FlxTaskDocCommentUpdateInput!){ flxTaskDocCommentUpdate(input:$i){ comment { body resolvedBy } } }', array( 'i' => array( 'id' => $gc, 'resolved' => true ) ), $ed );
flxt_same( $ed, $r['data']['flxTaskDocCommentUpdate']['comment']['resolvedBy'] ?? null, 'GraphQL: resolve' );
$r = flxt_gql( 'mutation($i:FlxTaskDocPageLockInput!){ flxTaskDocPageLock(input:$i){ page { lockedBy } } }', array( 'i' => array( 'pageId' => $page, 'locked' => true ) ), $ed );
flxt_same( $ed, $r['data']['flxTaskDocPageLock']['page']['lockedBy'] ?? null, 'GraphQL: lock' );
$r = flxt_gql( 'mutation($i:FlxTaskDocPageLockInput!){ flxTaskDocPageLock(input:$i){ page { lockedBy } } }', array( 'i' => array( 'pageId' => $page, 'locked' => true ) ), $vw );
flxt_same( 'FORBIDDEN', flxt_code( $r ), 'GraphQL: viewer cannot lock' );
$r = flxt_gql( 'mutation($i:FlxTaskDocCommentDeleteInput!){ flxTaskDocCommentDelete(input:$i){ deletedId } }', array( 'i' => array( 'id' => $gc ) ), $ed );
flxt_same( $gc, $r['data']['flxTaskDocCommentDelete']['deletedId'] ?? null, 'GraphQL: author deletes' );

// Deleting the page removes its comments and member rows.
flxt_rest( 'POST', '/doc-pages/' . $page . '/lock', array( 'locked' => 0 ), $admin );
global $wpdb;
flxt_rest( 'DELETE', '/doc-pages/' . $sec, array(), $admin );
flxt_same( 0, (int) $wpdb->get_var( $wpdb->prepare( 'SELECT COUNT(*) FROM ' . flxg_table( 'doc_page_members' ) . ' WHERE page_id = %d', $sec ) ), 'purge: member rows removed with the page' ); // phpcs:ignore
