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

if ( function_exists( 'flxai_chat' ) ) {
	$sug = flxc_t_op( 'FlxChatSuggest', array( 'input' => array( 'id' => (string) $mine_id, 'fresh' => true, 'hint' => 'short' ) ), $a );
	flxt_same( 'Suggested reply from the mock.', $sug['data']['flxChatSuggest']['text'] ?? null, 'suggest: real Flux AI, mocked HTTP' );
	$sug_h = flxc_t_op( 'FlxChatSuggest', array( 'input' => array( 'id' => (string) $hid_id, 'fresh' => true ) ), $a );
	flxt_same( 'NOT_FOUND', flxt_code( $sug_h ), 'suggest: hidden chat → NOT_FOUND' );
} else {
	flxt_ok( false, 'suggest: Flux AI is not active on the test site' );
}

flxt_done();
