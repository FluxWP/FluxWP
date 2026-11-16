<?php
/**
 * Flux AI Chatbot GraphQL contract tests. The documents are the persisted operations the Chatbot settings
 * and Site chats screens send (plugins/flx-chatbot/graphql/persisted-queries.json). The classic REST agent
 * routes, the classic settings form and GraphQL share FLXCB_Chat_Service / FLXCB_Settings_Service, so the
 * classic output is compared through the service and through rest_do_request().
 *
 *   wp eval-file tools/tests/wp-graphql/flx-chatbot/contract-test.php
 */

require dirname( __DIR__ ) . '/lib.php';

echo "Flux AI Chatbot GraphQL\n";

$GLOBALS['flxcb_ops'] = array();
foreach ( (array) json_decode( (string) file_get_contents( FLXCB_DIR . 'graphql/persisted-queries.json' ), true ) as $doc ) {
	if ( preg_match( '/^(?:query|mutation) (Flx\w+)/', $doc, $m ) ) {
		$GLOBALS['flxcb_ops'][ $m[1] ] = $doc;
	}
}
flxt_same( 9, count( $GLOBALS['flxcb_ops'] ), 'Flux AI Chatbot ships 9 persisted operations' );

function flxcb_t_op( string $name, array $vars = array(), int $user = 0 ): array {
	return flxt_gql( $GLOBALS['flxcb_ops'][ $name ], $vars, $user );
}

function flxcb_t_user( string $role, array $caps = array() ): int {
	$id = (int) wp_insert_user( array( 'user_login' => 'flxcb_' . wp_rand(), 'user_email' => 'flxcb_' . wp_rand() . '@example.com', 'user_pass' => wp_generate_password(), 'role' => $role, 'first_name' => 'Ayşe' ) );
	foreach ( $caps as $c ) {
		( new WP_User( $id ) )->add_cap( $c );
	}
	return $id;
}

$admin = 1;
$sub   = flxcb_t_user( 'subscriber' );
$agent = flxcb_t_user( 'subscriber', array( 'flxcb_reply' ) );

// Chats: one with the assistant, one waiting for an agent (with a file line and a product card), one closed.
[ $c1 ] = flxcb_create_conversation( array( 'name' => 'Zeynep Alıcı', 'email' => 'zeynep@example.com', 'page_url' => home_url( '/kargo/' ), 'page_title' => 'Shipping' ) );
[ $c2 ] = flxcb_create_conversation( array( 'name' => '', 'email' => 'bekleyen@example.com', 'phone' => '+905301234567' ) );
[ $c3 ] = flxcb_create_conversation( array() );
flxcb_update_conversation( (int) $c2['id'], array( 'status' => 'queued', 'queued_ts' => flxcb_now_ms(), 'lead' => 1, 'rating' => 4, 'rating_note' => 'Hızlı' ) );
flxcb_update_conversation( (int) $c3['id'], array( 'status' => 'closed' ) );
$m1 = flxcb_add_message( array( 'conv_id' => (int) $c1['id'], 'role' => 'visitor', 'body' => 'Kargo ne kadar sürer?' ) );
$m2 = flxcb_add_message( array( 'conv_id' => (int) $c1['id'], 'role' => 'bot', 'body' => 'Orders ship in **1-3 days**.', 'meta' => array( 'cards' => array( array( 'kind' => 'product', 'title' => 'Kupa', 'url' => home_url( '/kupa/' ), 'image' => '', 'price' => '₺120', 'stock' => 'in', 'cart' => '' ) ) ) ) );
$m3 = flxcb_add_message( array( 'conv_id' => (int) $c2['id'], 'role' => 'visitor', 'body' => 'Bir insanla konuşmak istiyorum' ) );
flxcb_update_conversation( (int) $c2['id'], array( 'unread' => 1 ) );
$id1 = (int) $c1['id'];
$id2 = (int) $c2['id'];
$id3 = (int) $c3['id'];

// Permissions.
foreach ( array( 'FlxChatbotInbox', 'FlxChatbotConversationList', 'FlxChatbotSettingsScreen' ) as $op ) {
	flxt_same( 'UNAUTHENTICATED', flxt_code( flxcb_t_op( $op, array() ) ), "$op: visitor → UNAUTHENTICATED" );
	flxt_same( 'FORBIDDEN', flxt_code( flxcb_t_op( $op, array(), $sub ) ), "$op: subscriber → FORBIDDEN" );
}
$r = flxcb_t_op( 'FlxChatbotSettingsScreen', array(), $agent );
flxt_same( 'FORBIDDEN', flxt_code( $r ), 'settings screen: an agent (flxcb_reply) may not read the settings' );
$r = flxcb_t_op( 'FlxChatbotInbox', array(), $agent );
flxt_same( array( false, 'Ayşe', true, null ), array( $r['data']['flxChatbotInbox']['canDelete'] ?? null, $r['data']['flxChatbotInbox']['agentName'] ?? null, array_key_exists( 'settingsUrl', $r['data']['flxChatbotInbox'] ?? array() ), $r['data']['flxChatbotInbox']['settingsUrl'] ?? null ), 'inbox: agent cannot delete and has no settings link' );
$r = flxcb_t_op( 'FlxChatbotInbox', array(), $admin );
flxt_same( array( true, flxcb_admin_url() ), array( $r['data']['flxChatbotInbox']['canDelete'] ?? null, $r['data']['flxChatbotInbox']['settingsUrl'] ?? null ), 'inbox: administrator can delete and sees the settings link' );

// List: open by default, filters, search, paging.
$r = flxcb_t_op( 'FlxChatbotConversationList', array(), $agent );
flxt_same( null, $r['errors'] ?? null, 'list: no errors for an agent' );
$nodes = $r['data']['flxChatbotConversations']['nodes'] ?? array();
flxt_same( array( $id2 ), array_column( array_map( static fn( $n ) => array( 'id' => (int) $n['databaseId'] ), $nodes ), 'id' ), 'list: open chats by default (the one waiting for an agent)' );
$r = flxcb_t_op( 'FlxChatbotConversationList', array( 'filter' => 'ALL' ), $admin );
$ids = array_map( static fn( $n ) => (int) $n['databaseId'], $r['data']['flxChatbotConversations']['nodes'] ?? array() );
flxt_same( 3, $r['data']['flxChatbotConversations']['totalCount'] ?? null, 'list: ALL counts every chat' );
foreach ( array( $id1, $id2, $id3 ) as $i ) {
	flxt_ok( in_array( $i, $ids, true ), "list: ALL has chat $i" );
}
$r = flxcb_t_op( 'FlxChatbotConversationList', array( 'filter' => 'BOT' ), $admin );
flxt_same( array( $id1 ), array_map( static fn( $n ) => (int) $n['databaseId'], $r['data']['flxChatbotConversations']['nodes'] ?? array() ), 'list: BOT filter' );
$r = flxcb_t_op( 'FlxChatbotConversationList', array( 'filter' => 'CLOSED' ), $admin );
flxt_same( array( 'CLOSED' ), array_map( static fn( $n ) => $n['status'], $r['data']['flxChatbotConversations']['nodes'] ?? array() ), 'list: CLOSED filter, status enum' );
$r = flxcb_t_op( 'FlxChatbotConversationList', array( 'filter' => 'ALL', 'search' => 'zeynep' ), $admin );
flxt_same( array( $id1 ), array_map( static fn( $n ) => (int) $n['databaseId'], $r['data']['flxChatbotConversations']['nodes'] ?? array() ), 'list: search by name' );
$r = flxcb_t_op( 'FlxChatbotConversationList', array( 'filter' => 'ALL', 'first' => 1 ), $admin );
flxt_same( array( true, 3, 1 ), array( $r['data']['flxChatbotConversations']['pageInfo']['hasNextPage'] ?? null, $r['data']['flxChatbotConversations']['totalCount'] ?? null, count( $r['data']['flxChatbotConversations']['nodes'] ?? array() ) ), 'list: first page, more follow' );

// Row fields equal the classic REST item.
$r    = flxcb_t_op( 'FlxChatbotConversationList', array( 'filter' => 'OPEN' ), $admin );
$row  = $r['data']['flxChatbotConversations']['nodes'][0] ?? array();
wp_set_current_user( $admin );
$rest = rest_do_request( new WP_REST_Request( 'GET', '/flx-chatbot/v1/agent/conversations' ) );
$item = array_values( array_filter( (array) ( $rest->get_data()['items'] ?? array() ), static fn( $i ) => (int) $i['id'] === $id2 ) )[0] ?? array();
flxt_same( array( $item['title'], $item['email'], $item['phone'] ?: null, $item['unread'], $item['rating'], $item['note'], (bool) $item['lead'], $item['status'] ), array( $row['title'] ?? null, $row['email'] ?? null, $row['phone'] ?? null, $row['unread'] ?? null, $row['rating'] ?? null, $row['ratingNote'] ?? null, $row['isLead'] ?? null, strtolower( (string) ( $row['status'] ?? '' ) ) ), 'list: GraphQL row = classic REST item (title, contact, unread, rating, lead, status)' );
flxt_same( array( 'VISITOR', 'Bir insanla konuşmak istiyorum' ), array( $row['lastMessage']['role'] ?? null, $row['lastMessage']['body'] ?? null ), 'list: last message line' );
flxt_same( $item['last'], array( 'role' => strtolower( (string) ( $row['lastMessage']['role'] ?? '' ) ), 'body' => $row['lastMessage']['body'] ?? null ), 'list: last message = classic REST' );
flxt_same( 'Visitor #' . $id3, FLXCB_Chat_Service::find( $id3 )->title, 'title: "Visitor #n" when nothing is known' );
flxt_same( 'bekleyen@example.com', $row['title'] ?? null, 'title: email when there is no name' );

// Open a chat: messages, read mark.
$r = flxcb_t_op( 'FlxChatbotConversationOpen', array( 'id' => (string) $id1 ), $agent );
$msgs = $r['data']['flxChatbotMessages'] ?? array();
flxt_same( null, $r['errors'] ?? null, 'open: no errors' );
flxt_same( array( 'VISITOR', 'BOT' ), array_column( $msgs['nodes'] ?? array(), 'role' ), 'open: messages oldest first, role enum' );
flxt_same( array( $m2, false ), array( $msgs['lastId'] ?? null, $msgs['hasMore'] ?? null ), 'open: lastId, hasMore' );
$card = $msgs['nodes'][1]['cards'][0] ?? array();
flxt_same( array( 'Kupa', '₺120', true, null, null ), array( $card['title'] ?? null, $card['price'] ?? null, $card['inStock'] ?? null, array_key_exists( 'cartUrl', $card ) ? $card['cartUrl'] : 'x', array_key_exists( 'image', $card ) ? $card['image'] : 'x' ), 'open: product card' );
flxt_same( 'Assistant', $msgs['nodes'][1]['author'] ?? null, 'open: assistant author' );
$r2 = flxcb_t_op( 'FlxChatbotConversationOpen', array( 'id' => (string) $id1, 'afterId' => $m1 ), $agent );
flxt_same( array( $m2 ), array_map( static fn( $n ) => $n['databaseId'], $r2['data']['flxChatbotMessages']['nodes'] ?? array() ), 'open: afterId returns only newer messages' );
// Classic REST messages = GraphQL messages.
$req = new WP_REST_Request( 'GET', '/flx-chatbot/v1/agent/conversations/' . $id1 );
$req->set_param( 'peek', 1 );
wp_set_current_user( $admin );
$restm = rest_do_request( $req )->get_data()['messages'] ?? array();
flxt_same( array_column( $restm, 'id' ), array_column( $msgs['nodes'] ?? array(), 'databaseId' ), 'open: GraphQL message ids = classic REST' );
flxt_same( $restm[1]['cards'] ?? null, array( array( 'kind' => 'product', 'title' => 'Kupa', 'url' => home_url( '/kupa/' ), 'image' => '', 'price' => '₺120', 'stock' => 'in', 'cart' => '' ) ), 'REST: product card keeps its exact shape' );

// Opening marks as read; peek does not.
$r = flxcb_t_op( 'FlxChatbotConversationOpen', array( 'id' => (string) $id2, 'peek' => true ), $agent );
flxt_same( 1, FLXCB_Chat_Service::find( $id2 )->unread, 'open: peek leaves the chat unread' );
$r = flxcb_t_op( 'FlxChatbotConversationOpen', array( 'id' => (string) $id2 ), $agent );
flxt_same( 0, FLXCB_Chat_Service::find( $id2 )->unread, 'open: reading marks the chat read' );
$r = flxcb_t_op( 'FlxChatbotConversationOpen', array( 'id' => '99999' ), $agent );
flxt_same( 'NOT_FOUND', flxt_code( $r ), 'open: unknown chat → NOT_FOUND' );

// Node lookup.
$gid = base64_encode( 'FlxChatbotConversation:' . $id1 );
$r   = flxt_gql( '{ node(id:"' . $gid . '") { ... on FlxChatbotConversation { databaseId title } } }', array(), $agent );
flxt_same( array( $id1, 'Zeynep Alıcı' ), array( $r['data']['node']['databaseId'] ?? null, $r['data']['node']['title'] ?? null ), 'node(id:) loads a chat for an agent' );
$r = flxt_gql( '{ node(id:"' . $gid . '") { ... on FlxChatbotConversation { databaseId } } }', array(), $sub );
flxt_same( array( true, null ), array( array_key_exists( 'node', $r['data'] ?? array() ), $r['data']['node'] ?? null ), 'node(id:) is empty for a subscriber' );

// Reply.
$r = flxcb_t_op( 'FlxChatbotConversationReply', array( 'input' => array( 'id' => (string) $id2, 'text' => "Merhaba, buradayım\n" ) ), $agent );
$p = $r['data']['flxChatbotConversationReply'] ?? array();
flxt_same( array( 'AGENT', 'Ayşe' ), array( $p['conversation']['status'] ?? null, $p['conversation']['agentName'] ?? null ), 'reply: the chat is with the agent' );
$last = flxcb_last_message( $id2 );
flxt_same( array( 'agent', 'Merhaba, buradayım', $agent ), array( $last['role'], $last['body'], (int) $last['user_id'] ), 'reply: stored as the agent\'s message' );
$r = flxcb_t_op( 'FlxChatbotConversationReply', array( 'input' => array( 'id' => (string) $id2, 'text' => '   ' ) ), $agent );
flxt_same( array( 'INVALID_INPUT', 'text' ), array( $r['data']['flxChatbotConversationReply']['errors'][0]['code'] ?? null, $r['data']['flxChatbotConversationReply']['errors'][0]['field'] ?? null ), 'reply: empty → INVALID_INPUT text' );
$r = flxcb_t_op( 'FlxChatbotConversationReply', array( 'input' => array( 'id' => '99999', 'text' => 'x' ) ), $agent );
flxt_same( 'NOT_FOUND', flxt_code( $r ), 'reply: unknown chat → NOT_FOUND' );
$r = flxcb_t_op( 'FlxChatbotConversationReply', array( 'input' => array( 'id' => (string) $id2, 'text' => 'x' ) ), $sub );
flxt_same( 'FORBIDDEN', flxt_code( $r ), 'reply: subscriber → FORBIDDEN' );
$r = flxcb_t_op( 'FlxChatbotConversationReply', array( 'input' => array( 'id' => (string) $id2, 'text' => 'dosya', 'uploadId' => 'AAAAAAAAAAAAAAAAAAAA.nope' ) ), $agent );
flxt_ok( null !== flxt_code( $r ) || ! empty( $r['data']['flxChatbotConversationReply']['errors'] ), 'reply: unknown uploadId is refused' );

// Attached file through the upload context.
$ctx = flx_gql_upload_contexts()['flx-chatbot/reply'] ?? array();
flxt_same( true, isset( $ctx['cap'] ) && is_callable( $ctx['cap'] ), 'upload context flx-chatbot/reply is registered with a callable cap' );
$png = base64_decode( 'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mP8z8BQDwAEhQGAhKmMIQAAAABJRU5ErkJggg==' );
$tmp = wp_tempnam( 'flxcb.png' );
file_put_contents( $tmp, $png );
$stored = flxcb_store_local( $tmp, 'foto.png', strlen( $png ) );
flxt_same( array( 'image/png', 'foto.png' ), array( $stored['mime'] ?? null, $stored['name'] ?? null ), 'store_local: PNG accepted, type from content' );
@unlink( $tmp ); // phpcs:ignore WordPress.PHP.NoSilencedErrors
$bad = wp_tempnam( 'flxcb.php' );
file_put_contents( $bad, '<?php echo 1;' );
$err = flxcb_store_local( $bad, 'x.php', 13 );
flxt_ok( is_wp_error( $err ), 'store_local: a PHP file is refused' );
@unlink( $bad ); // phpcs:ignore WordPress.PHP.NoSilencedErrors
FLXCB_Chat_Service::reply( $id2, $agent, '', $stored );
$rows = flxcb_messages( $id2, 0, 50 );
$file = end( $rows );
flxt_same( array( 'image', 'foto.png' ), array( $file['type'], $file['file_name'] ), 'reply with a file: image message' );
$r    = flxcb_t_op( 'FlxChatbotConversationOpen', array( 'id' => (string) $id2, 'peek' => true ), $agent );
$fm   = array_values( array_filter( $r['data']['flxChatbotMessages']['nodes'] ?? array(), static fn( $n ) => null !== $n['file'] ) )[0] ?? array();
flxt_same( array( 'IMAGE', 'foto.png', 'image/png' ), array( $fm['type'] ?? null, $fm['file']['name'] ?? null, $fm['file']['mime'] ?? null ), 'open: file message (type, name, mime)' );
flxt_ok( str_contains( (string) ( $fm['file']['url'] ?? '' ), '/flx-chatbot/v1/file/' ), 'open: file address is the authenticated REST file route' );

// Typing, close, delete.
$r = flxcb_t_op( 'FlxChatbotConversationTyping', array( 'input' => array( 'id' => (string) $id2 ) ), $agent );
flxt_same( array( true, 1 ), array( $r['data']['flxChatbotConversationTyping']['ok'] ?? null, (int) (bool) get_transient( 'flxcb_typing_a_' . $id2 ) ), 'typing: indicator set for the visitor' );
$r = flxcb_t_op( 'FlxChatbotConversationClose', array( 'input' => array( 'id' => (string) $id2 ) ), $agent );
flxt_same( 'CLOSED', $r['data']['flxChatbotConversationClose']['conversation']['status'] ?? null, 'close: status CLOSED' );
$all = flxcb_messages( $id2, 0, 100 );
flxt_same( 'system', end( $all )['role'] ?? null, 'close: an event line is added' );
$r = flxcb_t_op( 'FlxChatbotConversationDelete', array( 'input' => array( 'id' => (string) $id3 ) ), $agent );
flxt_same( 'FORBIDDEN', flxt_code( $r ), 'delete: an agent without manage_options → FORBIDDEN' );
$r = flxcb_t_op( 'FlxChatbotConversationDelete', array( 'input' => array( 'id' => (string) $id3 ) ), $admin );
flxt_same( array( $id3, null ), array( $r['data']['flxChatbotConversationDelete']['deletedId'] ?? null, flxcb_get_conversation( $id3 ) ), 'delete: administrator deletes the chat' );
$r = flxcb_t_op( 'FlxChatbotConversationDelete', array( 'input' => array( 'id' => (string) $id3 ) ), $admin );
flxt_same( 'NOT_FOUND', flxt_code( $r ), 'delete: again → NOT_FOUND' );
// Classic REST agent routes still work (same service).
wp_set_current_user( $agent );
$req = new WP_REST_Request( 'POST', '/flx-chatbot/v1/agent/conversations/' . $id1 . '/reply' );
$req->set_param( 'text', 'klasik cevap' );
$rr = rest_do_request( $req );
flxt_same( array( 200, 'agent', 'klasik cevap' ), array( $rr->get_status(), end( $rr->get_data()['messages'] )['role'] ?? null, end( $rr->get_data()['messages'] )['body'] ?? null ), 'REST reply goes through the service' );
$req = new WP_REST_Request( 'POST', '/flx-chatbot/v1/agent/conversations/' . $id1 . '/reply' );
$req->set_param( 'text', '' );
flxt_same( 400, rest_do_request( $req )->get_status(), 'REST reply: empty text → 400' );
$req = new WP_REST_Request( 'POST', '/flx-chatbot/v1/agent/conversations/99999/reply' );
$req->set_param( 'text', 'x' );
flxt_same( 404, rest_do_request( $req )->get_status(), 'REST reply: unknown chat → 404' );
flxt_same( 403, rest_do_request( new WP_REST_Request( 'DELETE', '/flx-chatbot/v1/agent/conversations/' . $id1 ) )->get_status(), 'REST delete: agent → 403' );

// Settings screen.
$r = flxcb_t_op( 'FlxChatbotSettingsScreen', array(), $admin );
flxt_same( null, $r['errors'] ?? null, 'settings screen: no errors for the administrator' );
$d = $r['data'] ?? array();
$s = flxcb_settings( true );
$g = $d['flxSettings']['chatbot'] ?? array();
flxt_same( array( 'HYBRID', (int) $s['width'], (bool) $s['mobile_full'], $s['handoff_to'] ), array( $g['mode'] ?? null, $g['width'] ?? null, $g['mobileFull'] ?? null, strtolower( str_replace( '_', '-', (string) ( $g['handoffTo'] ?? '' ) ) ) ), 'settings screen: values = flxcb_settings (enum, int, bool)' );
flxt_same( count( flxcb_fields() ), count( $g ), 'settings screen: every setting is in the screen document' );
$a = $d['flxChatbotAdmin'] ?? array();
flxt_same( array( 'ai', 'live', 'hours' ), array_column( $a['status'] ?? array(), 'key' ), 'settings screen: connection lines' );
flxt_same( array_keys( FLXCB_Settings_Service::default_texts() ), array_column( $a['textDefaults'] ?? array(), 'key' ), 'settings screen: default texts' );
flxt_same( array_keys( flxcb_page_types() ), array_column( $a['pageTypes'] ?? array(), 'key' ), 'settings screen: page types' );
flxt_same( array( '1', '7' ), array( $a['weekdays'][0]['key'] ?? null, $a['weekdays'][6]['key'] ?? null ), 'settings screen: weekdays 1-7' );
flxt_same( array( 2, 1 ), array( $a['stats']['total'] ?? null, $a['stats']['open'] ?? null ), 'settings screen: figures (2 chats, 1 with an agent)' );
$classic_st = flxcb_stats( 30 );
flxt_same( array( $classic_st['total'], $classic_st['resolved'], $classic_st['leads'], $classic_st['open'] ), array( $a['stats']['total'] ?? null, $a['stats']['resolved'] ?? null, $a['stats']['leads'] ?? null, $a['stats']['open'] ?? null ), 'settings screen: figures = flxcb_stats' );
flxt_same( true, is_array( $a['preview']['config'] ?? null ) && true === ( $a['preview']['config']['preview'] ?? null ) && ! empty( $a['preview']['config']['i18n'] ), 'settings screen: preview config is the widget config in preview mode' );
flxt_ok( str_contains( (string) ( $a['preview']['scriptUrl'] ?? '' ), 'assets/widget.js' ), 'settings screen: widget script address' );

// Save.
$in = array( 'enabled' => true, 'botName' => '  Ayşe Asistan ', 'color' => '#2563eb', 'width' => 9999, 'launcherSize' => 10, 'mode' => 'LIVE', 'position' => 'LEFT', 'quickReplies' => "Kargo?\nİade?", 'showOnCheckout' => false, 'retentionDays' => 90, 'hours' => array( '1' => array( 'on' => 1, 'from' => '08:00', 'to' => '17:30' ), '2' => array( 'on' => 0, 'from' => 'bad', 'to' => '18:00' ) ), 'privacyText' => '<a href="/gizlilik" onclick="x()">Gizlilik</a><script>1</script>' );
$r  = flxcb_t_op( 'FlxChatbotSettingsSave', array( 'input' => array( 'settings' => $in ) ), $admin );
$p  = $r['data']['flxChatbotSettingsSave'] ?? array();
flxt_same( array(), $p['errors'] ?? null, 'save: no errors' );
$s = flxcb_settings( true );
flxt_same( array( 1, 'Ayşe Asistan', '#2563eb', 480, 44, 'live', 'left', "Kargo?\nİade?", 0, 90 ), array( (int) $s['enabled'], $s['bot_name'], $s['color'], (int) $s['width'], (int) $s['launcher_size'], $s['mode'], $s['position'], $s['quick_replies'], (int) $s['show_on_checkout'], (int) $s['retention_days'] ), 'save: cleaned and limited like the classic form (trim, width 480, size 44)' );
flxt_same( array( 'on' => 1, 'from' => '08:00', 'to' => '17:30' ), $s['hours'][1] ?? null, 'save: weekly hours' );
flxt_same( array( 'on' => 0, 'from' => '09:00', 'to' => '18:00' ), $s['hours'][2] ?? null, 'save: a bad time falls back to the default' );
flxt_same( '<a href="/gizlilik">Gizlilik</a>1', $s['privacy_text'], 'save: privacy note keeps only safe links' );
flxt_same( array( 'LIVE', 480, 'Ayşe Asistan' ), array( $p['settings']['mode'] ?? null, $p['settings']['width'] ?? null, $p['settings']['botName'] ?? null ), 'save: payload has the saved values' );
flxt_same( 'Ayşe Asistan', FLXCB_Chat_Service::find( $id1 ) ? flxcb_bot_name() : '', 'save: the widget reads the new value' );
$r = flxcb_t_op( 'FlxChatbotSettingsSave', array( 'input' => array( 'settings' => array( 'botName' => 'Başka' ) ) ), $admin );
flxt_same( array( 480, 'Başka', 'live' ), array( (int) flxcb_settings( true )['width'], flxcb_settings()['bot_name'], flxcb_settings()['mode'] ), 'save: fields left out stay as they are' );
$r = flxcb_t_op( 'FlxChatbotSettingsSave', array( 'input' => array( 'settings' => array( 'notifyEmail' => 'not-an-email' ) ) ), $admin );
flxt_same( array( 'INVALID_INPUT', 'notifyEmail' ), array( $r['data']['flxChatbotSettingsSave']['errors'][0]['code'] ?? null, $r['data']['flxChatbotSettingsSave']['errors'][0]['field'] ?? null ), 'save: bad notification email → INVALID_INPUT' );
$r = flxcb_t_op( 'FlxChatbotSettingsSave', array( 'input' => array( 'settings' => array( 'botName' => 'x' ) ) ), $agent );
flxt_same( 'FORBIDDEN', flxt_code( $r ), 'save: agent → FORBIDDEN' );
$hit = 0;
add_action( 'flxcb_settings_saved', static function () use ( &$hit ) { $hit++; } );
flxcb_t_op( 'FlxChatbotSettingsSave', array( 'input' => array( 'settings' => array( 'sound' => false ) ) ), $admin );
flxt_same( 1, $hit, 'save: flxcb_settings_saved fires (the same hook as the classic form)' );
$r = flxt_gql( '{ flxSettings { chatbot { botName } } }', array(), $sub );
flxt_ok( null !== flxt_code( $r ), 'settings: subscriber cannot read' );

// Persisted: the screens' documents are allowed in strict mode.
wp_set_current_user( $admin );
$r = graphql( array( 'queryId' => hash( 'sha256', $GLOBALS['flxcb_ops']['FlxChatbotSettingsScreen'] ) ) );
flxt_ok( ! empty( $r['data']['flxChatbotAdmin'] ), 'persisted queryId runs (strict mode)' );

// Clean up.
foreach ( array( $id1, $id2 ) as $i ) {
	flxcb_delete_conversation( $i );
}
delete_option( 'flxcb_settings' );
flxcb_settings( true );
foreach ( array( $sub, $agent ) as $u ) {
	wp_delete_user( $u );
}
flxt_done();
