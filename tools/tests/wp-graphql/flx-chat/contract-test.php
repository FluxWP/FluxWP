<?php
/**
 * Flux Chat GraphQL contract (part 1). Documents are the persisted operations in
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
flxt_same( 18, count( $GLOBALS['flxc_ops'] ), 'Flux Chat ships 18 persisted operations' );

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
		unset( $args );
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

flxt_done();
