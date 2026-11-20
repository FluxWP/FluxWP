<?php
/**
 * Flux Chat GraphQL contract (parts 1 and 2: chat desk, settings, reports). Documents are the persisted operations in
 * plugins/flx-chat/graphql/persisted-queries.json. Visibility, the update cursor and
 * secrets are also queried as raw documents.
 *
 *   wp eval-file tools/tests/wp-graphql/flx-chat/contract-test.php
 */

require dirname( __DIR__ ) . '/lib.php';

echo "Flux Chat GraphQL\n";

$GLOBALS['flxc_ops'] = array();
foreach ( (array) json_decode( (string) file_get_contents( FLXC_DIR . 'graphql/persisted-queries.json' ), true ) as $doc ) {
	if ( is_string( $doc ) && preg_match( '/^(?:query|mutation) (Flx\w+)/', $doc, $m ) ) {
		$GLOBALS['flxc_ops'][ $m[1] ] = $doc;
	}
}
flxt_same( 37, count( $GLOBALS['flxc_ops'] ), 'Flux Chat ships 37 persisted operations' );

/**
 * @param array<string,mixed> $vars Variables.
 */
function flxc_t_op( string $name, array $vars = array(), int $user = 0 ): array {
	return flxt_gql( $GLOBALS['flxc_ops'][ $name ], $vars, $user );
}

function flxc_t_user( string $login, array $caps ): int {
	$id = (int) wp_insert_user(
		array(
			'user_login' => $login . wp_rand(),
			'user_email' => $login . wp_rand() . '@example.com',
			'user_pass'  => wp_generate_password(),
			'role'       => 'subscriber',
		)
	);
	$u = new WP_User( $id );
	foreach ( $caps as $cap ) {
		$u->add_cap( $cap );
	}
	return $id;
}

add_filter(
	'pre_http_request',
	static function ( $pre, $args, $url ) {
		if ( str_contains( (string) $url, 'flx-chat/v1/reports/bg' ) ) {
			$GLOBALS['flxc_t_bg'][] = (array) ( $args['body'] ?? array() );
			return array(
				'headers'  => array(),
				'body'     => '{"ok":true}',
				'response' => array( 'code' => 200, 'message' => 'OK' ),
				'cookies'  => array(),
				'filename' => null,
			);
		}
		if ( ! empty( $GLOBALS['flxc_t_send_ok'] ) && str_contains( (string) $url, '/message/sendText/' ) ) {
			return array(
				'headers'  => array(),
				'body'     => wp_json_encode( array( 'key' => array( 'id' => 'WA-ROUND' ) ) ),
				'response' => array( 'code' => 200, 'message' => 'OK' ),
				'cookies'  => array(),
				'filename' => null,
			);
		}
		if ( ! empty( $GLOBALS['flxc_t_hist_ok'] ) && str_contains( (string) $url, '/chat/findMessages/' ) ) {
			return array(
				'headers'  => array(),
				'body'     => wp_json_encode( array( 'messages' => array( 'records' => array(), 'pages' => 1, 'total' => 0 ) ) ),
				'response' => array( 'code' => 200, 'message' => 'OK' ),
				'cookies'  => array(),
				'filename' => null,
			);
		}
		if ( str_contains( (string) $url, 'chat/completions' ) ) {
			return array(
				'headers'  => array(),
				'body'     => wp_json_encode(
					array(
						'choices' => array( array( 'message' => array( 'content' => 'Suggested reply from the mock.' ), 'finish_reason' => 'stop' ) ),
						'usage'   => array( 'prompt_tokens' => 1, 'completion_tokens' => 1 ),
					)
				),
				'response' => array( 'code' => 200, 'message' => 'OK' ),
				'cookies'  => array(),
				'filename' => null,
			);
		}
		return array(
			'headers'  => array(),
			'body'     => '',
			'response' => array( 'code' => 599, 'message' => 'blocked' ),
			'cookies'  => array(),
			'filename' => null,
		);
	},
	10,
	3
);

if ( function_exists( 'flxai_chat' ) ) {
	if ( ! defined( 'FLXAI_KEY_OPENROUTER' ) ) {
		define( 'FLXAI_KEY_OPENROUTER', 'test-key' );
	}
	update_option( 'flxai_settings', array( 'model' => 'openrouter/test-model' ) );
	unset( $GLOBALS['flxai_settings_cache'] );
}

$secret = 'WEBHOOKSECRETMARKER';
$key    = 'APIKEYMARKER';
flxc_update_settings(
	array(
		'restrict_agents'   => 0,
		'agents_donations'  => 0,
		'connections'       => array(
			array(
				'id'       => 't',
				'label'    => 'Test',
				'color'    => '#116b4a',
				'source'   => 'own',
				'base_url' => 'http://127.0.0.1:9',
				'api_key'  => $key,
				'instance' => 't',
				'secret'   => $secret,
			),
		),
	)
);
flxc_settings( true );

$admin = 1;
$sub   = flxc_t_user( 'flxc_sub_', array() );
$a     = flxc_t_user( 'flxc_a_', array( 'flxc_use' ) );
$b     = flxc_t_user( 'flxc_b_', array( 'flxc_use' ) );
$mgr   = flxc_t_user( 'flxc_m_', array( 'flxc_use', 'flxc_manage' ) );

flxt_same( 'UNAUTHENTICATED', flxt_code( flxc_t_op( 'FlxChatInbox' ) ), 'inbox: visitor → UNAUTHENTICATED' );
flxt_same( 'FORBIDDEN', flxt_code( flxc_t_op( 'FlxChatInbox', array(), $sub ) ), 'inbox: subscriber → FORBIDDEN' );
$inbox = flxc_t_op( 'FlxChatInbox', array(), $a );
flxt_same( null, $inbox['errors'] ?? null, 'inbox: agent' );
flxt_ok( isset( $inbox['data']['flxChatInbox']['rev'] ), 'inbox: rev is present' );
flxt_same( false, $inbox['data']['flxChatInbox']['me']['manage'] ?? null, 'inbox: agent is not a manager' );
$as_mgr = flxc_t_op( 'FlxChatInbox', array(), $mgr );
flxt_same( true, $as_mgr['data']['flxChatInbox']['me']['manage'] ?? null, 'inbox: manager' );

$blob = wp_json_encode( $inbox ) . wp_json_encode( $as_mgr );
flxt_ok( ! str_contains( (string) $blob, $secret ), 'inbox does not contain the webhook secret' );
flxt_ok( ! str_contains( (string) $blob, $key ), 'inbox does not contain the API key' );
flxt_ok( ! str_contains( (string) $blob, 'flx1:' ), 'inbox does not contain a sealed secret' );

$empty = flxc_t_op( 'FlxChatList', array( 'search' => 'no-such-chat-flxc-empty', 'filter' => 'ANY' ), $admin );
flxt_same( 0, $empty['data']['flxChats']['totalCount'] ?? null, 'list: empty search' );

$before = (string) ( (int) flxc_rev() - 1 );
$burst  = array();
for ( $i = 0; $i < 55; $i++ ) {
	$phone = sprintf( '905551%06d', $i );
	$row   = flxc_ensure_chat( 't', $phone . '@s.whatsapp.net', array( 'name' => 'Burst ' . $i ) );
	flxc_update_chat( (int) $row['id'], array( 'unread' => 1 ) );
	$burst[] = (int) $row['id'];
}
$upd = 'query ($since: String!, $afterChat: Int) { flxChatUpdates(since: $since, afterChat: $afterChat) { rev continueRev continueId truncated chats { databaseId } } }';
$page = flxt_gql( $upd, array( 'since' => $before, 'afterChat' => 0 ), $admin );
flxt_same( true, $page['data']['flxChatUpdates']['truncated'] ?? null, 'updates: more than 50 changed chats is truncated' );
flxt_ok( count( $page['data']['flxChatUpdates']['chats'] ?? array() ) <= 50, 'updates: page holds at most 50' );
$seen = array_map( static fn( $n ): int => (int) $n['databaseId'], $page['data']['flxChatUpdates']['chats'] ?? array() );
$guard = 0;
$cursor = $page;
while ( ! empty( $cursor['data']['flxChatUpdates']['truncated'] ) && $guard < 4 ) {
	$cursor = flxt_gql(
		$upd,
		array(
			'since'     => (string) $cursor['data']['flxChatUpdates']['continueRev'],
			'afterChat' => (int) $cursor['data']['flxChatUpdates']['continueId'],
		),
		$admin
	);
	foreach ( $cursor['data']['flxChatUpdates']['chats'] ?? array() as $n ) {
		$seen[] = (int) $n['databaseId'];
	}
	++$guard;
}
$missing = array_values( array_diff( $burst, $seen ) );
flxt_same( array(), $missing, 'updates: continuing from continueRev returns every changed chat' );
$skip = flxt_gql( $upd, array( 'since' => (string) $page['data']['flxChatUpdates']['rev'], 'afterChat' => 0 ), $admin );
$skip_ids = array_map( static fn( $n ): int => (int) $n['databaseId'], $skip['data']['flxChatUpdates']['chats'] ?? array() );
$lost = array_values( array_diff( $burst, array_merge( $seen_first = array_map( static fn( $n ): int => (int) $n['databaseId'], $page['data']['flxChatUpdates']['chats'] ?? array() ), $skip_ids ) ) );
flxt_ok( count( $lost ) > 0, 'updates: the high-water rev skips chats that did not fit the page', $lost );
foreach ( $burst as $bid ) {
	flxc_update_chat( $bid, array( 'assigned_to' => $admin ) );
}

flxc_update_settings( array( 'restrict_agents' => 1 ) );
flxc_settings( true );
wp_set_current_user( $admin );

$mine = flxc_ensure_chat( 't', '905550000001@s.whatsapp.net', array( 'name' => 'Agent A chat FLXC' ) );
$hid  = flxc_ensure_chat( 't', '905550000002@s.whatsapp.net', array( 'name' => 'HIDDENCHATNAME' ) );
$open = flxc_ensure_chat( 't', '905550000003@s.whatsapp.net', array( 'name' => 'Unassigned FLXC' ) );
flxc_update_chat( (int) $mine['id'], array( 'assigned_to' => $a, 'name' => 'Agent A chat FLXC' ) );
flxc_update_chat( (int) $hid['id'], array( 'assigned_to' => $b, 'name' => 'HIDDENCHATNAME' ) );
flxc_update_chat( (int) $open['id'], array( 'assigned_to' => 0, 'name' => 'Unassigned FLXC' ) );
$mine_id = (int) $mine['id'];
$hid_id  = (int) $hid['id'];
$open_id = (int) $open['id'];
flxc_insert_message( array( 'chat_id' => $hid_id, 'conn' => 't', 'direction' => 'in', 'type' => 'text', 'body' => 'secret line', 'user_id' => 0, 'source' => 'contact' ), flxc_get_chat( $hid_id ) );
flxc_insert_message( array( 'chat_id' => $mine_id, 'conn' => 't', 'direction' => 'out', 'type' => 'text', 'body' => 'hello', 'user_id' => $a, 'source' => 'agent' ), flxc_get_chat( $mine_id ) );

$seen_a = flxc_t_op( 'FlxChatList', array( 'search' => 'HIDDENCHATNAME', 'filter' => 'ANY' ), $a );
flxt_same( 0, $seen_a['data']['flxChats']['totalCount'] ?? null, 'list: agent A does not count B\'s chat' );
$seen_b = flxc_t_op( 'FlxChatList', array( 'search' => 'HIDDENCHATNAME', 'filter' => 'ANY' ), $b );
flxt_same( 1, $seen_b['data']['flxChats']['totalCount'] ?? null, 'list: agent B counts their chat' );
$seen_open = flxc_t_op( 'FlxChatList', array( 'search' => 'Unassigned FLXC', 'filter' => 'ANY' ), $a );
flxt_same( 1, $seen_open['data']['flxChats']['totalCount'] ?? null, 'list: an unassigned chat is visible' );

$hidden_q = flxt_gql( 'query ($id: ID!) { flxChat(id: $id) { databaseId title } }', array( 'id' => (string) $hid_id ), $a );
flxt_same( null, $hidden_q['data']['flxChat'] ?? null, 'flxChat: hidden chat is null' );
$gid = flxt_gql( 'query ($id: ID!) { flxChat(id: $id) { id } }', array( 'id' => (string) $hid_id ), $mgr );
$node = flxt_gql( 'query ($id: ID!) { node(id: $id) { ... on FlxChat { databaseId } } }', array( 'id' => (string) ( $gid['data']['flxChat']['id'] ?? '' ) ), $a );
flxt_same( null, $node['data']['node'] ?? null, 'node: agent A cannot open B\'s chat' );

$msgs = flxt_gql( 'query ($id: ID!) { flxChatMessages(chatId: $id) { messages { body } } }', array( 'id' => (string) $hid_id ), $a );
flxt_same( 'NOT_FOUND', flxt_code( $msgs ), 'messages: hidden chat → NOT_FOUND' );
$usage = flxt_gql( 'query ($id: ID!) { flxChatUsage(chatId: $id) { replies } }', array( 'id' => (string) $hid_id ), $a );
flxt_same( null, $usage['data']['flxChatUsage'] ?? null, 'usage: hidden chat is null' );

$rev_now = (string) ( (int) flxc_rev() - 1 );
$delta   = flxt_gql( 'query ($since: String!) { flxChatUpdates(since: $since) { chats { databaseId title } } }', array( 'since' => $rev_now ), $a );
$delta_ids = array_map( static fn( $n ): int => (int) $n['databaseId'], $delta['data']['flxChatUpdates']['chats'] ?? array() );
flxt_ok( ! in_array( $hid_id, $delta_ids, true ), 'updates: agent A does not receive B\'s chat' );
$delta_blob = wp_json_encode( $delta );
flxt_ok( ! str_contains( (string) $delta_blob, 'HIDDENCHATNAME' ), 'updates: hidden title is absent' );
flxt_ok( ! str_contains( (string) $delta_blob, $secret ) && ! str_contains( (string) $delta_blob, $key ), 'updates: no secret' );

$check = flxc_t_op( 'FlxChatCheckNumber', array( 'phone' => '905550000002', 'conn' => 't' ), $a );
flxt_same( 0, $check['data']['flxChatCheckNumber']['chatId'] ?? null, 'check-number: hidden chat id is 0' );
flxt_ok( ! str_contains( wp_json_encode( $check ), 'HIDDENCHATNAME' ), 'check-number: hidden title is absent' );
$check_b = flxc_t_op( 'FlxChatCheckNumber', array( 'phone' => '905550000002', 'conn' => 't' ), $b );
flxt_same( $hid_id, $check_b['data']['flxChatCheckNumber']['chatId'] ?? null, 'check-number: the owner still sees the chat' );

$send = flxc_t_op( 'FlxChatMessageSend', array( 'input' => array( 'chatId' => (string) $mine_id, 'body' => '', 'type' => 'text' ) ), $a );
$codes = array_map( static fn( $e ): string => (string) ( $e['code'] ?? '' ), $send['data']['flxChatMessageSend']['errors'] ?? array() );
flxt_ok( in_array( 'INVALID_INPUT', $codes, true ), 'send: empty body is a payload error', $send );

$exp = flxt_gql( 'mutation ($id: ID!) { flxChatExport(input: { id: $id, format: "md" }) { body filename } }', array( 'id' => (string) $hid_id ), $a );
flxt_same( 'NOT_FOUND', flxt_code( $exp ), 'export: hidden chat → NOT_FOUND' );

$mid = (int) flxc_insert_message( array( 'chat_id' => $mine_id, 'conn' => 't', 'direction' => 'in', 'type' => 'image', 'body' => '', 'user_id' => 0, 'source' => 'contact' ), flxc_get_chat( $mine_id ) );
$det = flxt_gql( 'mutation ($id: Int!) { flxChatReceiptDetach(input: { id: $id, kind: "donation", ref: 1 }) { ok } }', array( 'id' => $mid ), $a );
flxt_same( 'FORBIDDEN', flxt_code( $det ), 'receipt detach: same capability as attach' );

$classic_msgs = flxc_t_op( 'FlxChatMessages', array( 'chatId' => (string) $mine_id ), $a );
$classic_bodies = array();
foreach ( $classic_msgs['data']['flxChatMessages']['messages'] ?? array() as $line ) {
	$classic_bodies[] = (string) ( $line['body'] ?? '' );
}
flxt_ok( in_array( 'hello', $classic_bodies, true ), 'round-trip: classic message is read by GraphQL', $classic_bodies );

$sent_ids = array();
add_action(
	'flxc_message_sent',
	static function ( $id ) use ( &$sent_ids ) {
		$sent_ids[] = (int) $id;
	}
);
$fail_send = flxc_t_op( 'FlxChatMessageSend', array( 'input' => array( 'chatId' => (string) $mine_id, 'body' => 'GQL-ROUND-FAIL', 'type' => 'text' ) ), $a );
$fail_id   = (int) ( $fail_send['data']['flxChatMessageSend']['message']['databaseId'] ?? 0 );
$fail_row  = flxc_get_message( $fail_id );
flxt_same( 'GQL-ROUND-FAIL', is_array( $fail_row ) ? (string) $fail_row['body'] : '', 'round-trip: GraphQL send is stored by the classic writer' );
flxt_same( 'failed', is_array( $fail_row ) ? (string) $fail_row['status'] : '', 'send: a provider error marks the line failed' );
flxt_same( array(), $sent_ids, 'send: a provider error does not fire flxc_message_sent' );
$fail_js = flxc_messages_for_js( is_array( $fail_row ) ? array( $fail_row ) : array() );
flxt_same( 'GQL-ROUND-FAIL', (string) ( $fail_js[0]['body'] ?? '' ), 'round-trip: classic reader returns the GraphQL body' );

$GLOBALS['flxc_t_send_ok'] = true;
$ok_send = flxc_t_op( 'FlxChatMessageSend', array( 'input' => array( 'chatId' => (string) $open_id, 'body' => 'GQL-ROUND-OK', 'type' => 'text' ) ), $a );
$ok_id   = (int) ( $ok_send['data']['flxChatMessageSend']['message']['databaseId'] ?? 0 );
$ok_row  = flxc_get_message( $ok_id );
$GLOBALS['flxc_t_send_ok'] = false;
flxt_same( 'sent', is_array( $ok_row ) ? (string) $ok_row['status'] : '', 'send: a provider success marks the line sent' );
flxt_same( array( $ok_id ), $sent_ids, 'send: flxc_message_sent fires once' );
$open_after = flxc_get_chat( $open_id );
flxt_same( $a, is_array( $open_after ) ? (int) $open_after['assigned_to'] : 0, 'send: the first agent reply assigns the chat once' );

$made = 0;
add_action(
	'flxc_chat_created',
	static function () use ( &$made ) {
		++$made;
	}
);
$created = flxc_t_op( 'FlxChatCreate', array( 'input' => array( 'phone' => '905559990077', 'name' => 'GQL Created FLXC', 'conn' => 't', 'check' => false ) ), $a );
$created_id = (int) ( $created['data']['flxChatCreate']['chat']['databaseId'] ?? 0 );
$created_row = flxc_get_chat( $created_id );
flxt_same( 'GQL Created FLXC', is_array( $created_row ) ? flxc_chat_title( $created_row ) : '', 'round-trip: GraphQL create is read by flxc_chat_title' );
flxt_same( 1, $made, 'create: flxc_chat_created fires once' );

update_option( 'timezone_string', 'Europe/Istanbul' );
update_option( 'gmt_offset', '3' );
$tz_row = flxc_ensure_chat( 't', '905559990088@s.whatsapp.net', array( 'name' => 'TZ FLXC' ) );
$tz_id  = (int) $tz_row['id'];
$tz_stored = flxc_get_chat( $tz_id );
$tz_expect = is_array( $tz_stored ) ? (int) strtotime( $tz_stored['created_at'] . ' UTC' ) : 0;
$tz_q = flxt_gql( 'query ($id: ID!) { flxChat(id: $id) { createdAt { timestamp iso } } }', array( 'id' => (string) $tz_id ), $admin );
flxt_same( $tz_expect, $tz_q['data']['flxChat']['createdAt']['timestamp'] ?? null, 'createdAt is the GMT instant in a non-UTC timezone' );
flxt_ok( str_ends_with( (string) ( $tz_q['data']['flxChat']['createdAt']['iso'] ?? '' ), 'Z' ), 'createdAt iso is UTC' );

$reply_req = new WP_REST_Request( 'POST' );
$reply_req->set_param( 'title', 'Round title' );
$reply_req->set_param( 'body', 'CLASSIC-REPLY-BODY' );
$reply_req->set_param( 'shortcut', 'roundtrip' );
wp_set_current_user( $a );
$reply_saved = FLXC_Desk_Service::save_reply( $reply_req );
$reply_id = 0;
foreach ( is_array( $reply_saved ) ? ( $reply_saved['replies'] ?? array() ) : array() as $reply_row ) {
	if ( is_array( $reply_row ) && 'CLASSIC-REPLY-BODY' === (string) ( $reply_row['body'] ?? '' ) ) {
		$reply_id = (int) $reply_row['id'];
	}
}
$reply_gql = flxt_gql( 'query { flxChatReplies { databaseId body } }', array(), $a );
$reply_seen = false;
foreach ( $reply_gql['data']['flxChatReplies'] ?? array() as $reply_g ) {
	if ( 'CLASSIC-REPLY-BODY' === (string) ( $reply_g['body'] ?? '' ) ) {
		$reply_seen = true;
	}
}
flxt_ok( $reply_seen, 'round-trip: a classic quick reply is read by GraphQL' );
$used = flxc_t_op( 'FlxChatReplyUse', array( 'input' => array( 'id' => $reply_id, 'chatId' => (string) $mine_id ) ), $a );
flxt_same( 'CLASSIC-REPLY-BODY', $used['data']['flxChatReplyUse']['text'] ?? null, 'reply use fills the classic body' );
global $wpdb;
$uses = (int) $wpdb->get_var( $wpdb->prepare( 'SELECT uses FROM ' . flxc_table( 'replies' ) . ' WHERE id = %d', $reply_id ) );
flxt_same( 1, $uses, 'reply use increments the counter once' );

$cat = flxt_gql( 'query { flxCapabilities { key package } flxViewer { capabilities } }', array(), $a );
$chat_caps = array();
foreach ( $cat['data']['flxCapabilities'] ?? array() as $cap_row ) {
	if ( 'flx-chat' === (string) ( $cap_row['package'] ?? '' ) ) {
		$chat_caps[] = (string) $cap_row['key'];
	}
}
sort( $chat_caps );
flxt_same(
	array( 'flx_reports_manage', 'flx_reports_receive', 'flx_reports_view', 'flxc_manage', 'flxc_use' ),
	$chat_caps,
	'catalog keeps every flx-chat capability'
);
flxt_ok( in_array( 'flxc_use', $cat['data']['flxViewer']['capabilities'] ?? array(), true ), 'viewer lists flxc_use for an agent' );
flxt_ok( ! in_array( 'flxc_manage', $cat['data']['flxViewer']['capabilities'] ?? array(), true ), 'viewer omits flxc_manage for an agent' );

if ( function_exists( 'flxai_chat' ) ) {
	$sug = flxc_t_op( 'FlxChatSuggest', array( 'input' => array( 'id' => (string) $mine_id, 'fresh' => true, 'hint' => 'short' ) ), $a );
	flxt_same( 'Suggested reply from the mock.', $sug['data']['flxChatSuggest']['text'] ?? null, 'suggest: real Flux AI, mocked HTTP' );
	$sug_h = flxc_t_op( 'FlxChatSuggest', array( 'input' => array( 'id' => (string) $hid_id, 'fresh' => true ) ), $a );
	flxt_same( 'NOT_FOUND', flxt_code( $sug_h ), 'suggest: hidden chat → NOT_FOUND' );
} else {
	flxt_ok( false, 'suggest: Flux AI is not active on the test site' );
}

// REST parity: a finished history reports more=false on REST and GraphQL alike.
$hist_chat = flxc_ensure_chat( 't', '905550000004@s.whatsapp.net', array( 'name' => 'History FLXC' ) );
$hist_id   = (int) $hist_chat['id'];
flxc_update_chat( $hist_id, array( 'assigned_to' => $a ) );
flxc_insert_message( array( 'chat_id' => $hist_id, 'conn' => 't', 'direction' => 'in', 'type' => 'text', 'body' => 'only line', 'user_id' => 0, 'source' => 'contact' ), flxc_get_chat( $hist_id ) );
$GLOBALS['flxc_t_hist_ok'] = true;
wp_set_current_user( $a );
$rest_hist = rest_do_request( new WP_REST_Request( 'GET', '/flx-chat/v1/chats/' . $hist_id . '/messages' ) );
$rest_data = $rest_hist->get_data();
flxt_same( false, $rest_data['more'] ?? null, 'REST messages: a history that just finished reports more=false' );
$hist_row = flxc_get_chat( $hist_id );
flxt_same( true, ! empty( flxc_decode( is_array( $hist_row ) ? $hist_row['meta'] : '' )['hist']['done'] ), 'REST messages: the history fetch marked the chat done' );
$gql_hist = flxc_t_op( 'FlxChatMessages', array( 'chatId' => (string) $hist_id ), $a );
flxt_same( false, $gql_hist['data']['flxChatMessages']['hasMore'] ?? null, 'GraphQL messages: hasMore matches REST' );
$GLOBALS['flxc_t_hist_ok'] = false;

// The upload gate also covers voice notes: a blocked extension is refused as audio.
$bad_file = wp_tempnam( 'flxc-voice' );
file_put_contents( $bad_file, '<html><script>alert(1)</script></html>' );
wp_set_current_user( $a );
$voice_bad = FLXC_Message_Service::send_upload(
	(array) flxc_get_chat( $mine_id ),
	'audio',
	'',
	0,
	'agent',
	false,
	array( 'path' => $bad_file, 'name' => 'voice.html', 'mime' => 'audio/ogg', 'size' => (int) filesize( $bad_file ) ),
	true
);
flxt_same( 'flxc_type', is_wp_error( $voice_bad ) ? $voice_bad->get_error_code() : '', 'send: an .html file sent as a voice note is refused' );
$big_voice = FLXC_Message_Service::send_upload(
	(array) flxc_get_chat( $mine_id ),
	'audio',
	'',
	0,
	'agent',
	false,
	array( 'path' => $bad_file, 'name' => 'voice.ogg', 'mime' => 'audio/ogg', 'size' => 99 * MB_IN_BYTES ),
	true
);
flxt_same( 'flxc_big', is_wp_error( $big_voice ) ? $big_voice->get_error_code() : '', 'send: flxc_max_upload applies to voice notes' );
wp_delete_file( $bad_file );

// Hidden chat: creating a chat for a number whose chat belongs to B must not show it to A.
$hidden_open = flxt_gql(
	'mutation ($i: FlxChatCreateInput!) { flxChatCreate(input: $i) { chat { databaseId title notes lastMessage { body } } errors { code } } }',
	array( 'i' => array( 'phone' => '905550000002', 'name' => 'RENAMED-BY-A', 'conn' => 't', 'check' => false ) ),
	$a
);
$hidden_blob = wp_json_encode( $hidden_open );
flxt_same( null, $hidden_open['data']['flxChatCreate']['chat'] ?? null, 'create: a number whose chat is hidden returns no chat' );
flxt_ok( ! str_contains( (string) $hidden_blob, 'HIDDENCHATNAME' ) && ! str_contains( (string) $hidden_blob, 'secret line' ), 'create: hidden title and last message are absent' );
$hid_after = flxc_get_chat( $hid_id );
flxt_same( 'HIDDENCHATNAME', is_array( $hid_after ) ? (string) $hid_after['name'] : '', 'create: the hidden chat is not renamed by A' );
$hid_mark = flxt_gql( 'mutation ($i: FlxChatMarkReadInput!) { flxChatMarkRead(input: $i) { chat { databaseId } } }', array( 'i' => array( 'id' => (string) $hid_id ) ), $a );
flxt_ok( null === ( $hid_mark['data']['flxChatMarkRead']['chat'] ?? null ), 'mark read: hidden chat is never returned' );

/* ===== Part 2: settings, connections, system checks ===== */

$settings_q = 'query { flxChatSettings { brandName accent pollInterval welcomeText labels { id name color } connections { id label source baseUrl hasApiKey } context { siteName timezone } } }';
flxt_same( 'UNAUTHENTICATED', flxt_code( flxt_gql( $settings_q ) ), 'settings: visitor → UNAUTHENTICATED' );
flxt_same( 'FORBIDDEN', flxt_code( flxt_gql( $settings_q, array(), $a ) ), 'settings: an agent without flxc_manage → FORBIDDEN' );
flxt_same( 'FORBIDDEN', flxt_code( flxt_gql( $settings_q, array(), $sub ) ), 'settings: subscriber → FORBIDDEN' );
$save_doc = 'mutation ($i: FlxChatSettingsSaveInput!) { flxChatSettingsSave(input: $i) { settings { brandName pollInterval } errors { code } } }';
flxt_same( 'FORBIDDEN', flxt_code( flxt_gql( $save_doc, array( 'i' => array( 'brandName' => 'Nope' ) ), $a ) ), 'settings save: an agent → FORBIDDEN' );
flxt_same( 'FORBIDDEN', flxt_code( flxc_t_op( 'FlxChatWebhookUrlReveal', array( 'input' => array( 'id' => 't' ) ), $a ) ), 'webhook reveal: an agent → FORBIDDEN' );
flxt_same( 'FORBIDDEN', flxt_code( flxc_t_op( 'FlxChatConnectionTest', array( 'input' => array( 'id' => 't' ) ), $a ) ), 'connection test: an agent → FORBIDDEN' );
flxt_same( 'FORBIDDEN', flxt_code( flxc_t_op( 'FlxChatSystemChecks', array(), $a ) ), 'system checks: an agent → FORBIDDEN' );
flxt_same( 'FORBIDDEN', flxt_code( flxc_t_op( 'FlxChatAiTest', array( 'input' => array() ), $a ) ), 'AI test: an agent → FORBIDDEN' );
flxt_same( 'FORBIDDEN', flxt_code( flxc_t_op( 'FlxChatAiModels', array(), $a ) ), 'AI models: an agent → FORBIDDEN' );

// Classic form → GraphQL: the service sanitizes the tab exactly as the classic page always did.
FLXC_Settings_Service::apply(
	'genel',
	array(
		'brand_name'            => '  Classic Brand <b>x</b> ',
		'accent'                => 'not-a-color',
		'poll_interval'         => '2',
		'delete_everyone_hours' => '99',
		'send_read'             => '1',
	)
);
$classic = flxc_t_op( 'FlxChatSettings', array(), $mgr );
flxt_same( null, $classic['errors'] ?? null, 'settings: manager reads' );
$cs = $classic['data']['flxChatSettings'] ?? array();
flxt_same( 'Classic Brand x', $cs['brandName'] ?? null, 'round-trip: a classic tab save is read by GraphQL (text cleaned)' );
flxt_same( '#00a884', $cs['accent'] ?? null, 'round-trip: an invalid color falls back like the classic page' );
flxt_same( 3, $cs['pollInterval'] ?? null, 'round-trip: poll interval is clamped to 3' );
flxt_same( 60, flxc_setting( 'delete_everyone_hours' ), 'classic tab clamps delete-for-everyone hours to 60' );

// GraphQL → classic, partial: only the fields sent change.
FLXC_Settings_Service::apply( 'otomatik', array( 'welcome_enabled' => '1', 'welcome_text' => "Hello there\n" ) );
$writes = 0;
$count_writes = static function () use ( &$writes ) {
	++$writes;
};
add_action( 'update_option_flxc_settings', $count_writes );
$saved = flxt_gql( $save_doc, array( 'i' => array( 'brandName' => 'GraphQL Brand', 'pollInterval' => 999 ) ), $mgr );
remove_action( 'update_option_flxc_settings', $count_writes );
flxt_same( array(), $saved['data']['flxChatSettingsSave']['errors'] ?? null, 'settings save: no input errors' );
flxt_same( 1, $writes, 'settings save: the option is written once' );
flxt_same( 'GraphQL Brand', flxc_setting( 'brand_name' ), 'round-trip: a GraphQL save is read by the classic settings' );
flxt_same( 60, flxc_setting( 'poll_interval' ), 'settings save: poll interval is clamped to 60 like the classic page' );
flxt_same( 'Hello there', trim( (string) flxc_setting( 'welcome_text' ) ), 'settings save: a field that was not sent keeps its value' );

// Labels: same id rules as the classic tab.
$lab = flxt_gql( $save_doc, array( 'i' => array( 'labels' => array( array( 'name' => 'VIP', 'color' => '#ff0000' ), array( 'name' => 'VIP', 'color' => 'bad' ), array( 'name' => '' ) ) ) ), $mgr );
flxt_same( array(), $lab['data']['flxChatSettingsSave']['errors'] ?? null, 'labels save: no input errors' );
$stored_labels = array_map( static fn( $l ): string => $l['id'] . '|' . $l['name'] . '|' . $l['color'], (array) flxc_setting( 'labels' ) );
flxt_same( array( 'vip|VIP|#ff0000', 'vip2|VIP|#25d366' ), $stored_labels, 'labels: ids are made from the name, a repeat gets a suffix, a bad color is replaced, a blank name is dropped' );
$lab_read = flxc_t_op( 'FlxChatSettings', array(), $mgr );
flxt_same( 'vip2', $lab_read['data']['flxChatSettings']['labels'][1]['id'] ?? null, 'round-trip: classic labels are read by GraphQL' );

// Secrets are write-only.
$conn_doc = 'mutation ($i: FlxChatSettingsSaveInput!) { flxChatSettingsSave(input: $i) { settings { connections { id hasApiKey } } errors { code } } }';
$keep = flxt_gql( $conn_doc, array( 'i' => array( 'connections' => array( array( 'id' => 't', 'source' => 'own', 'label' => 'Test', 'baseUrl' => 'http://127.0.0.1:9', 'instance' => 't', 'apiKey' => '' ) ) ) ), $mgr );
flxt_same( true, $keep['data']['flxChatSettingsSave']['settings']['connections'][0]['hasApiKey'] ?? null, 'connection save: an empty key keeps the saved one' );
$classic_conn = flxc_get_connection( 't' );
flxt_same( $key, is_array( $classic_conn ) ? $classic_conn['api_key'] : null, 'connection save: the classic reader still opens the key' );
flxt_same( $secret, is_array( $classic_conn ) ? $classic_conn['secret'] : null, 'connection save: the webhook secret is untouched' );
$swap = flxt_gql( $conn_doc, array( 'i' => array( 'connections' => array( array( 'id' => 't', 'source' => 'own', 'label' => 'Test', 'baseUrl' => 'http://127.0.0.1:9', 'instance' => 't', 'apiKey' => 'NEWAPIKEYMARKER' ) ) ) ), $mgr );
$classic_conn = flxc_get_connection( 't' );
flxt_same( 'NEWAPIKEYMARKER', is_array( $classic_conn ) ? $classic_conn['api_key'] : null, 'round-trip: a key written by GraphQL is read by the classic code' );
flxt_ok( ! str_contains( (string) wp_json_encode( $swap ), 'NEWAPIKEYMARKER' ), 'connection save: the response does not echo the key' );
$read_all = wp_json_encode( flxc_t_op( 'FlxChatSettings', array(), $mgr ) );
flxt_ok( ! str_contains( (string) $read_all, 'NEWAPIKEYMARKER' ) && ! str_contains( (string) $read_all, $secret ) && ! str_contains( (string) $read_all, 'flx1:' ), 'settings read: no key, no webhook secret, no sealed value' );
$cleared = flxt_gql( $conn_doc, array( 'i' => array( 'connections' => array( array( 'id' => 't', 'source' => 'own', 'label' => 'Test', 'baseUrl' => 'http://127.0.0.1:9', 'instance' => 't', 'apiKey' => '__clear__' ) ) ) ), $mgr );
flxt_same( false, $cleared['data']['flxChatSettingsSave']['settings']['connections'][0]['hasApiKey'] ?? null, 'connection save: "__clear__" removes the key' );
$restore = flxt_gql( $conn_doc, array( 'i' => array( 'connections' => array( array( 'id' => 't', 'source' => 'own', 'label' => 'Test', 'baseUrl' => 'http://127.0.0.1:9', 'instance' => 't', 'apiKey' => $key ) ) ) ), $mgr );
flxt_same( true, $restore['data']['flxChatSettingsSave']['settings']['connections'][0]['hasApiKey'] ?? null, 'connection save: the key can be set again' );
// A classic tab post never treats "__clear__" as a command.
FLXC_Settings_Service::apply( 'baglantilar', array( 'connections' => array( array( 'id' => 't', 'source' => 'own', 'label' => 'Test', 'base_url' => 'http://127.0.0.1:9', 'instance' => 't', 'api_key' => '__clear__' ) ) ) );
$classic_conn = flxc_get_connection( 't' );
flxt_same( '__clear__', is_array( $classic_conn ) ? $classic_conn['api_key'] : null, 'classic connection tab: "__clear__" is just text, as before' );
FLXC_Settings_Service::apply( 'baglantilar', array( 'connections' => array( array( 'id' => 't', 'source' => 'own', 'label' => 'Test', 'base_url' => 'http://127.0.0.1:9', 'instance' => 't', 'api_key' => $key ) ) ) );

// Webhook address: manager-only reveal, rotation changes the key everywhere.
$reveal = flxc_t_op( 'FlxChatWebhookUrlReveal', array( 'input' => array( 'id' => 't' ) ), $mgr );
flxt_same( flxc_webhook_url( 't' ), $reveal['data']['flxChatWebhookUrlReveal']['url'] ?? null, 'webhook reveal: the same address the classic page prints' );
flxt_same( 'NOT_FOUND', flxt_code( flxc_t_op( 'FlxChatWebhookUrlReveal', array( 'input' => array( 'id' => 'nope' ) ), $mgr ) ), 'webhook reveal: an unknown line → NOT_FOUND' );
$rot = flxc_t_op( 'FlxChatWebhookUrlReveal', array( 'input' => array( 'id' => 't', 'regenerate' => true ) ), $mgr );
$new_conn = flxc_get_connection( 't' );
flxt_ok( is_array( $new_conn ) && $secret !== $new_conn['secret'] && 32 === strlen( (string) $new_conn['secret'] ), 'webhook reveal: regenerate makes a new 32 character key' );
flxt_same( flxc_webhook_url( 't' ), $rot['data']['flxChatWebhookUrlReveal']['url'] ?? null, 'webhook reveal: the rotated address matches the classic one' );

// Connection actions share the REST service: same answer, nothing secret.
wp_set_current_user( $mgr );
$rest_test = rest_do_request( new WP_REST_Request( 'POST', '/flx-chat/v1/admin/connections/t/test' ) )->get_data();
flxt_same( array( 'stored', 'ok', 'state', 'error', 'webhook_ok', 'webhook_url', 'version' ), array_keys( $rest_test ), 'REST connection test keeps its keys and order' );
$gql_test = flxc_t_op( 'FlxChatConnectionTest', array( 'input' => array( 'id' => 't' ) ), $mgr );
$gt = $gql_test['data']['flxChatConnectionTest']['result'] ?? array();
flxt_same( $rest_test['ok'], $gt['ok'] ?? null, 'connection test: GraphQL ok matches REST' );
flxt_same( $rest_test['stored'], $gt['stored'] ?? null, 'connection test: GraphQL stored matches REST' );
$rest_hook = rest_do_request( new WP_REST_Request( 'POST', '/flx-chat/v1/admin/connections/t/webhook' ) )->get_data();
flxt_same( array( 'ok', 'error' ), array_keys( $rest_hook ), 'REST webhook setup keeps its keys' );
$gql_hook = flxc_t_op( 'FlxChatConnectionWebhook', array( 'input' => array( 'id' => 't' ) ), $mgr );
flxt_same( $rest_hook['ok'], $gql_hook['data']['flxChatConnectionWebhook']['ok'] ?? null, 'connection webhook: GraphQL matches REST' );
$rest_qr = rest_do_request( new WP_REST_Request( 'POST', '/flx-chat/v1/admin/connections/t/qr' ) )->get_data();
flxt_same( array( 'ok', 'qr', 'code', 'state', 'error' ), array_keys( $rest_qr ), 'REST QR keeps its keys' );
$gql_qr = flxc_t_op( 'FlxChatConnectionQr', array( 'input' => array( 'id' => 't' ) ), $mgr );
flxt_same( $rest_qr['ok'], $gql_qr['data']['flxChatConnectionQr']['ok'] ?? null, 'connection QR: GraphQL matches REST' );
flxt_same( 'NOT_FOUND', flxt_code( flxc_t_op( 'FlxChatConnectionImport', array( 'input' => array( 'id' => 'nope' ) ), $mgr ) ), 'connection import: an unknown line → NOT_FOUND' );
$rest_imp = rest_do_request( new WP_REST_Request( 'POST', '/flx-chat/v1/admin/connections/t/import' ) )->get_data();
flxt_same( array( 'ok', 'error' ), array_keys( $rest_imp ), 'REST import failure keeps its keys' );
$gql_imp = flxc_t_op( 'FlxChatConnectionImport', array( 'input' => array( 'id' => 't', 'chats' => 5 ) ), $mgr );
flxt_same( false, $gql_imp['data']['flxChatConnectionImport']['ok'] ?? null, 'connection import: a failed import is ok=false' );
flxt_same( 0, $gql_imp['data']['flxChatConnectionImport']['chats'] ?? null, 'connection import: no chats on failure' );

$checks = flxc_t_op( 'FlxChatSystemChecks', array(), $mgr );
$items  = $checks['data']['flxChatSystemChecks']['items'] ?? array();
flxt_same( count( flxc_system_checks() ), count( $items ), 'system checks: the same table the classic tab prints' );
flxt_same( $checks['data']['flxChatSystemChecks']['errorCount'] ?? null, count( array_filter( $items, static fn( $i ): bool => 'error' === $i['status'] ) ), 'system checks: error count matches the lines' );

$models = flxc_t_op( 'FlxChatAiModels', array( 'first' => 80 ), $mgr );
$rest_models = rest_do_request( new WP_REST_Request( 'GET', '/flx-chat/v1/admin/ai/models' ) );
flxt_same( $rest_models->is_error() ? 'error' : 'ok', isset( $models['errors'] ) ? 'error' : 'ok', 'AI models: GraphQL fails exactly when REST fails' );
$groups = flxc_t_op( 'FlxChatGroups', array( 'refresh' => false ), $mgr );
flxt_same( null, $groups['errors'] ?? null, 'groups: a manager reads the list' );

/* ===== Part 2: reports ===== */

if ( ! function_exists( 'flxr_user_phone' ) ) {
	function flxr_user_phone( $user_id ) {
		return (string) get_user_meta( (int) $user_id, 'flxt_phone', true );
	}
}
if ( ! function_exists( 'flxb_table' ) ) {
	function flxb_table( $name ) {
		global $wpdb;
		return $wpdb->prefix . 'flxb_' . $name;
	}
	global $wpdb;
	$wpdb->query( 'CREATE TABLE IF NOT EXISTS ' . $wpdb->prefix . 'flxb_donations (id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY, status VARCHAR(30), ref_user_id BIGINT, amount DECIMAL(12,2), quantity INT, created_at DATETIME)' ); // phpcs:ignore
}
$phone_meta = defined( 'FLXR_PHONE_META' ) ? FLXR_PHONE_META : 'flxt_phone';
$rpt = flxc_t_user( 'flxc_r_', array( 'flx_reports_manage', 'flx_reports_receive' ) );
update_user_meta( $rpt, $phone_meta, '905550001111' );
update_user_meta( $mgr, $phone_meta, '905550002222' );
delete_option( 'flxc_reports' );
$first_ids = array_map( static fn( $r ): string => $r['id'], (array) ( flxc_t_op( 'FlxChatReports', array(), 1 )['data']['flxChatReports']['rules'] ?? array() ) );
$again_ids = array_map( static fn( $r ): string => $r['id'], (array) ( flxc_t_op( 'FlxChatReports', array(), 1 )['data']['flxChatReports']['rules'] ?? array() ) );
flxt_ok( 2 === count( $first_ids ) && $first_ids === $again_ids, 'reports: the starter rules keep the same ids between two reads', array( $first_ids, $again_ids ) );
update_option( 'flxc_reports', array(), false );

$rep_q = 'query { flxChatReports { on days { value } people { databaseId name hasNumber } rules { id title active audience users content period freq weekday monthday time motivation recipientCount lastSent { iso } } } }';
flxt_same( 'UNAUTHENTICATED', flxt_code( flxt_gql( $rep_q ) ), 'reports: visitor → UNAUTHENTICATED' );
flxt_same( 'FORBIDDEN', flxt_code( flxt_gql( $rep_q, array(), $a ) ), 'reports: an agent → FORBIDDEN' );
flxt_same( 'FORBIDDEN', flxt_code( flxt_gql( $rep_q, array(), $sub ) ), 'reports: subscriber → FORBIDDEN' );
flxt_same( 'FORBIDDEN', flxt_code( flxc_t_op( 'FlxChatReportStatus', array(), $a ) ), 'report status: an agent → FORBIDDEN' );
flxt_same( 'FORBIDDEN', flxt_code( flxc_t_op( 'FlxChatReportRun', array( 'input' => array() ), $a ) ), 'report run: an agent → FORBIDDEN' );
flxt_same( 'FORBIDDEN', flxt_code( flxc_t_op( 'FlxChatReportAdd', array( 'input' => array() ), $a ) ), 'report add: an agent → FORBIDDEN' );
flxt_same( 'FORBIDDEN', flxt_code( flxc_t_op( 'FlxChatReportSend', array( 'input' => array( 'id' => 'x', 'mode' => 'ALL' ) ), $a ) ), 'report send: an agent → FORBIDDEN' );
$rep_ok = flxc_t_op( 'FlxChatReports', array(), $rpt );
flxt_same( null, $rep_ok['errors'] ?? null, 'reports: flx_reports_manage reads without flxc_manage' );
flxt_same( 7, count( $rep_ok['data']['flxChatReports']['days'] ?? array() ), 'reports: seven weekdays' );

// Add / classic read.
$add = flxc_t_op( 'FlxChatReportAdd', array( 'input' => array() ), $rpt );
$rid = (string) ( $add['data']['flxChatReportAdd']['id'] ?? '' );
flxt_ok( '' !== $rid && null !== FLXC_Report_Service::rule( $rid ), 'round-trip: a rule added through GraphQL is read by flxc_reports()' );
$classic_rule = FLXC_Report_Service::rule( $rid );
flxt_same( 0, is_array( $classic_rule ) ? (int) $classic_rule['active'] : -1, 'report add: the new rule starts inactive' );
// Rule ids are generated with capitals; GraphQL must find them exactly (the id is not run through sanitize_key).
$mixed        = FLXC_Report_Service::rules();
$mixed[0]['id'] = 'rMixEdCase';
flxc_reports_save( $mixed );
$rid = 'rMixEdCase';

// Save: junk values are limited like the classic form.
$rsave_doc = 'mutation ($i: FlxChatReportsSaveInput!) { flxChatReportsSave(input: $i) { reports { rules { id title audience weekday monthday time active } } errors { code } } }';
$rsave = flxt_gql( $rsave_doc, array( 'i' => array( 'rules' => array( array( 'id' => $rid, 'title' => 'Evening <i>sum</i>', 'active' => true, 'weekday' => 99, 'monthday' => 0, 'time' => '25:99x', 'audience' => 'USERS', 'users' => array( $rpt, 0 ), 'motivation' => "Line one\nLine two" ) ) ) ), $rpt );
flxt_same( array(), $rsave['data']['flxChatReportsSave']['errors'] ?? null, 'reports save: no input errors' );
$classic_rule = FLXC_Report_Service::rule( $rid );
flxt_same( 'Evening sum', is_array( $classic_rule ) ? $classic_rule['title'] : null, 'reports save: title is cleaned' );
flxt_same( 7, is_array( $classic_rule ) ? $classic_rule['weekday'] : null, 'reports save: weekday is clamped to 7' );
flxt_same( 1, is_array( $classic_rule ) ? $classic_rule['monthday'] : null, 'reports save: month day is clamped to 1' );
flxt_same( '18:00', is_array( $classic_rule ) ? $classic_rule['time'] : null, 'reports save: a bad time falls back to 18:00' );
flxt_same( array( $rpt ), is_array( $classic_rule ) ? $classic_rule['users'] : null, 'reports save: selected people drop zero ids' );
// A field that is not sent keeps its value.
flxt_gql( $rsave_doc, array( 'i' => array( 'rules' => array( array( 'id' => $rid, 'title' => 'Renamed' ) ) ) ), $rpt );
$classic_rule = FLXC_Report_Service::rule( $rid );
flxt_same( true, is_array( $classic_rule ) && 'Renamed' === $classic_rule['title'] && 7 === $classic_rule['weekday'] && 1 === (int) $classic_rule['active'], 'reports save: unsent fields keep their stored value' );
flxt_same( 'NOT_FOUND', flxt_code( flxt_gql( $rsave_doc, array( 'i' => array( 'rules' => array( array( 'id' => 'nosuchrule' ) ) ) ), $rpt ) ), 'reports save: an unknown rule → NOT_FOUND' );

// Classic form → GraphQL: the classic function and the mutation sanitize the same.
$forms = array( $rid => array( 'title' => 'Classic <b>T</b>', 'audience' => 'nope', 'content' => 'general', 'period' => 'week', 'freq' => 'monthly', 'weekday' => '3', 'monthday' => '40', 'time' => '07:30', 'motivation' => 'Go', 'active' => '1' ) );
flxc_reports_save( FLXC_Report_Service::apply_forms( FLXC_Report_Service::rules(), $forms ) );
$rep_read = flxc_t_op( 'FlxChatReports', array(), $rpt );
$gr = array();
foreach ( $rep_read['data']['flxChatReports']['rules'] ?? array() as $row ) {
	if ( $rid === $row['id'] ) {
		$gr = $row;
	}
}
flxt_same( 'Classic T', $gr['title'] ?? null, 'round-trip: a classic form save is read by GraphQL' );
flxt_same( 'RECEIVE', $gr['audience'] ?? null, 'classic form: an unknown audience falls back to RECEIVE' );
flxt_same( 'GENERAL', $gr['content'] ?? null, 'classic form: content reads as GENERAL' );
flxt_same( 28, $gr['monthday'] ?? null, 'classic form: month day is clamped to 28' );
flxt_same( '07:30', $gr['time'] ?? null, 'classic form: a good time is kept' );

// Preview and manual sends (side effects once).
$pv = flxc_t_op( 'FlxChatReportPreview', array( 'id' => $rid ), $rpt );
flxt_ok( is_string( $pv['data']['flxChatReportPreview'] ?? null ) && str_contains( (string) $pv['data']['flxChatReportPreview'], 'Classic T' ), 'report preview: the report text names the rule', $pv );
flxt_same( 'NOT_FOUND', flxt_code( flxc_t_op( 'FlxChatReportPreview', array( 'id' => 'nosuchrule' ), $rpt ) ), 'report preview: an unknown rule → NOT_FOUND' );

$sent_ids = array();
$GLOBALS['flxc_t_send_ok'] = true;
$test_send = flxc_t_op( 'FlxChatReportSend', array( 'input' => array( 'id' => $rid, 'mode' => 'TEST' ) ), $rpt );
flxt_same( 1, $test_send['data']['flxChatReportSend']['sent'] ?? null, 'report send TEST: one message', $test_send );
flxt_same( 1, count( $sent_ids ), 'report send TEST: flxc_message_sent fires once' );
$sent_ids = array();
update_user_meta( $rpt, $phone_meta, '' );
$no_num = flxc_t_op( 'FlxChatReportSend', array( 'input' => array( 'id' => $rid, 'mode' => 'TEST' ) ), $rpt );
$no_codes = array_map( static fn( $e ): string => (string) ( $e['code'] ?? '' ), $no_num['data']['flxChatReportSend']['errors'] ?? array() );
flxt_ok( in_array( 'INVALID_INPUT', $no_codes, true ), 'report send TEST: no number on the profile is a payload error', $no_num );
flxt_same( array(), $sent_ids, 'report send TEST: nothing is sent without a number' );
update_user_meta( $rpt, $phone_meta, '905550001111' );
$sent_ids = array();
$all_send = flxc_t_op( 'FlxChatReportSend', array( 'input' => array( 'id' => $rid, 'mode' => 'ALL' ) ), $rpt );
$expect_n = count( flxc_report_recipients( (array) FLXC_Report_Service::rule( $rid ) ) );
flxt_same( $expect_n, $all_send['data']['flxChatReportSend']['sent'] ?? null, 'report send ALL: one message per recipient' );
flxt_same( $expect_n, count( $sent_ids ), 'report send ALL: flxc_message_sent fires once per recipient' );
$GLOBALS['flxc_t_send_ok'] = false;

// Status, run and the token-protected background route.
$st = flxc_t_op( 'FlxChatReportStatus', array(), $rpt );
flxt_same( null, $st['errors'] ?? null, 'report status: readable' );
flxt_same( false, $st['data']['flxChatReportStatus']['running'] ?? null, 'report status: nothing is running' );
$GLOBALS['flxc_t_bg'] = array();
$run = flxc_t_op( 'FlxChatReportRun', array( 'input' => array() ), $rpt );
flxt_same( true, $run['data']['flxChatReportRun']['started'] ?? null, 'report run: the background request was accepted' );
flxt_same( 1, count( $GLOBALS['flxc_t_bg'] ), 'report run: one background request, not spawn_cron' );
flxt_same( flxc_bench_key(), $GLOBALS['flxc_t_bg'][0]['key'] ?? null, 'report run: the request carries the loopback key' );
wp_set_current_user( 0 );
$no_key = rest_do_request( new WP_REST_Request( 'POST', '/flx-chat/v1/reports/bg' ) );
flxt_ok( $no_key->is_error() || 200 !== $no_key->get_status(), 'reports bg route: refused without the key' );
$with_key = new WP_REST_Request( 'POST', '/flx-chat/v1/reports/bg' );
$with_key->set_param( 'key', flxc_bench_key() );
$bg = rest_do_request( $with_key );
flxt_same( 200, $bg->get_status(), 'reports bg route: accepted with the key' );
flxt_same( 0, (int) ( $bg->get_data()['sent'] ?? -1 ), 'reports bg route: nothing is due, nothing is sent' );

// Delete.
$del = flxc_t_op( 'FlxChatReportDelete', array( 'input' => array( 'id' => $rid ) ), $rpt );
flxt_same( null, $del['errors'] ?? null, 'report delete: no error' );
flxt_same( null, FLXC_Report_Service::rule( $rid ), 'round-trip: a deleted rule is gone for the classic reader' );
flxt_same( 'NOT_FOUND', flxt_code( flxc_t_op( 'FlxChatReportDelete', array( 'input' => array( 'id' => $rid ) ), $rpt ) ), 'report delete: deleting twice → NOT_FOUND' );

flxt_done();
