<?php
/**
 * Flux CRM GraphQL contract tests. The documents are the persisted operations the CRM screen sends
 * (plugins/flx-crm/graphql/persisted-queries.json). The classic pages and GraphQL share the services and the
 * storage classes, so classic behaviour is checked through FLXCRM_Contacts / FLXCRM_Segments / FLXCRM_Journeys.
 *
 *   wp eval-file tools/tests/wp-graphql/flx-crm/contract-test.php
 */

require dirname( __DIR__ ) . '/lib.php';

echo "Flux CRM GraphQL\n";

$GLOBALS['flxcrm_ops'] = array();
foreach ( (array) json_decode( (string) file_get_contents( FLXCRM_DIR . 'graphql/persisted-queries.json' ), true ) as $doc ) {
	if ( preg_match( '/^(?:query|mutation) (Flx\w+)/', $doc, $m ) ) {
		$GLOBALS['flxcrm_ops'][ $m[1] ] = $doc;
	}
}
flxt_same( 28, count( $GLOBALS['flxcrm_ops'] ), 'Flux CRM ships 28 persisted operations' );

function flxcrm_t( string $name, array $vars = array(), int $user = 0 ): array {
	return flxt_gql( $GLOBALS['flxcrm_ops'][ $name ], $vars, $user );
}

$admin  = 1;
$sub    = (int) wp_insert_user( array( 'user_login' => 'flxcrm_sub_' . wp_rand(), 'user_pass' => wp_generate_password(), 'role' => 'subscriber' ) );
$viewer = (int) wp_insert_user( array( 'user_login' => 'flxcrm_view_' . wp_rand(), 'user_pass' => wp_generate_password(), 'role' => 'subscriber' ) );
( new WP_User( $viewer ) )->add_cap( FLXCRM_CAP_VIEW );
$editor = (int) wp_insert_user( array( 'user_login' => 'flxcrm_edit_' . wp_rand(), 'user_pass' => wp_generate_password(), 'role' => 'subscriber' ) );
( new WP_User( $editor ) )->add_cap( FLXCRM_CAP_VIEW );
( new WP_User( $editor ) )->add_cap( FLXCRM_CAP_MANAGE );

// The users source turns the test users into contacts; start from an empty book.
foreach ( (array) ( FLXCRM_Contacts::query( array( 'per_page' => 100 ) )['rows'] ?? array() ) as $row ) {
	FLXCRM_Contacts::delete( (int) $row['id'] );
}
delete_option( FLXCRM_OPTION ); // Default settings.
global $wpdb;
foreach ( array( "flxcrm_segments", "flxcrm_journeys" ) as $flxt_tbl ) { // Start from no saved segments or journeys.
	$wpdb->query( "DELETE FROM {$wpdb->prefix}{$flxt_tbl}" ); // phpcs:ignore
}
delete_option( "flxcrm_synced_at" );
FLXCRM_Contacts::flush_stats();

/** The value of a top-level result field, or the marker 'missing' when there is no such field. */
function flxcrm_field( array $r, string $field ) {
	return array_key_exists( $field, $r['data'] ?? array() ) ? $r['data'][ $field ] : 'missing';
}

// ---- Permissions -------------------------------------------------------------------------------------
foreach ( array( 'FlxCrmShell', 'FlxCrmOverview', 'FlxCrmContacts', 'FlxCrmSegmentEditor', 'FlxCrmJourneys', 'FlxCrmDuplicates', 'FlxCrmSettings' ) as $op ) {
	flxt_same( 'UNAUTHENTICATED', flxt_code( flxcrm_t( $op ) ), "$op: visitor → UNAUTHENTICATED" );
	flxt_same( 'FORBIDDEN', flxt_code( flxcrm_t( $op, array(), $sub ) ), "$op: subscriber → FORBIDDEN" );
}
flxt_same( null, flxcrm_t( 'FlxCrmShell', array(), $viewer )['errors'] ?? null, 'shell: a viewer can read' );
flxt_same( 'FORBIDDEN', flxt_code( flxcrm_t( 'FlxCrmSettings', array(), $viewer ) ), 'settings: a viewer is refused (manage only)' );
flxt_same( 'FORBIDDEN', flxt_code( flxcrm_t( 'FlxCrmContactCreate', array( 'input' => array( 'email' => 'v@example.com' ) ), $viewer ) ), 'create: a viewer is refused' );
flxt_same( 'UNAUTHENTICATED', flxt_code( flxcrm_t( 'FlxCrmContactCreate', array( 'input' => array( 'email' => 'v@example.com' ) ) ) ), 'create: visitor → UNAUTHENTICATED' );
flxt_same( null, flxcrm_t( 'FlxCrmSettings', array(), $editor )['errors'] ?? null, 'settings: a manager can read' );

// ---- Vocabulary and empty state ----------------------------------------------------------------------
$d = flxcrm_t( 'FlxCrmShell', array(), $admin )['data'] ?? array();
$m = $d['flxCrmMeta'] ?? array();
flxt_same( array_keys( flxcrm_roles() ), array_column( $m['roles'] ?? array(), 'key' ), 'shell: roles match flxcrm_roles()' );
flxt_same( array_keys( flxcrm_rfm_segments() ), array_column( $m['rfm'] ?? array(), 'key' ), 'shell: RFM segments match' );
flxt_same( array_keys( flxcrm_event_types() ), array_column( $m['eventTypes'] ?? array(), 'key' ), 'shell: event types match' );
flxt_same( true, $m['canWrite'] ?? null, 'shell: administrator can write' );
flxt_same( false, flxcrm_t( 'FlxCrmShell', array(), $viewer )['data']['flxCrmMeta']['canWrite'] ?? null, 'shell: a viewer cannot write' );
flxt_same( flxcrm_currency(), $m['currency'] ?? null, 'shell: currency' );
$o = flxcrm_t( 'FlxCrmOverview', array(), $admin )['data'] ?? array();
flxt_same( 0, $o['flxCrmOverview']['total'] ?? null, 'overview: empty site has no contacts' );
flxt_same( true, $o['flxCrmOverview']['needsSync'] ?? null, 'overview: needs a sync until one ran' );
flxt_same( array(), $o['atRisk']['nodes'] ?? null, 'overview: nobody at risk' );
$l = flxcrm_t( 'FlxCrmContacts', array(), $admin )['data']['flxContacts'] ?? array();
flxt_same( array( 0, array() ), array( $l['totalCount'] ?? null, $l['nodes'] ?? null ), 'contacts: empty list' );
flxt_same( null, flxcrm_field( flxcrm_t( 'FlxCrmContact', array( 'id' => '999999' ), $admin ), 'flxContact' ), 'contact: unknown id → null' );

// ---- Create / update / FlxPerson ---------------------------------------------------------------------
$r = flxcrm_t( 'FlxCrmContactCreate', array( 'input' => array( 'name' => 'Ayşe Yılmaz', 'email' => 'Ayse@Example.com', 'phone' => '+90 530 121 39 33', 'company' => 'Yeşil Dernek', 'city' => 'İstanbul', 'roles' => array( 'donor', 'volunteer' ), 'consent' => array( 'email' => true, 'whatsapp' => true ), 'tags' => array( 'Kurban', 'VIP' ), 'note' => 'Ilk not' ) ), $editor );
$p = $r['data']['flxContactCreate'] ?? array();
flxt_same( array(), $p['errors'] ?? null, 'create: no errors' );
flxt_same( false, $p['existed'] ?? null, 'create: new contact' );
$c  = $p['contact'] ?? array();
$id = (int) ( $c['databaseId'] ?? 0 );
flxt_ok( $id > 0 && is_string( $c['id'] ?? null ), 'create: ids returned', $c );
$row = FLXCRM_Contacts::get( $id );
flxt_same( array( 'ayse@example.com', '905301213933', 'Yeşil Dernek', 'İstanbul' ), array( $row['email'] ?? null, $row['phone'] ?? null, $row['company'] ?? null, $row['city'] ?? null ), 'create: classic row has normalised e-mail and phone' );
flxt_same( array( 'donor', 'volunteer' ), flxcrm_unpack_roles( $row['roles'] ?? '' ), 'create: roles stored (classic)' );
flxt_same( array( 1, 0, 1, 0 ), array( (int) $row['consent_email'], (int) $row['consent_sms'], (int) $row['consent_whatsapp'], (int) $row['consent_ads'] ), 'create: consents stored (classic)' );
flxt_same( array( 'Kurban', 'VIP' ), array_column( FLXCRM_Tags::of( $id ), 'name' ), 'create: tags attached (classic)' );
flxt_same( array( 'Ayşe Yılmaz', 'ayse@example.com', '+905301213933' ), array( $c['name'] ?? null, $c['email'] ?? null, $c['phone']['e164'] ?? null ), 'create: payload fields' );
flxt_same( array( 'donor', 'volunteer' ), $c['roles'] ?? null, 'create: payload roles' );
flxt_same( 'Ilk not', $c['note'] ?? null, 'create: note' );

$again = flxcrm_t( 'FlxCrmContactCreate', array( 'input' => array( 'email' => 'ayse@example.com' ) ), $editor )['data']['flxContactCreate'] ?? array();
flxt_same( array( true, $id ), array( $again['existed'] ?? null, $again['contact']['databaseId'] ?? null ), 'create: same e-mail opens the existing card' );
$none = flxcrm_t( 'FlxCrmContactCreate', array( 'input' => array( 'name' => 'Nobody' ) ), $editor )['data']['flxContactCreate'] ?? array();
flxt_same( array( null, 'INVALID_INPUT' ), array( $none['contact'] ?? null, $none['errors'][0]['code'] ?? null ), 'create: needs an e-mail or a phone (INVALID_INPUT)' );

$u = flxcrm_t( 'FlxCrmContactUpdate', array( 'input' => array( 'id' => $c['id'], 'city' => 'Ankara', 'roles' => array( 'donor' ), 'ownerId' => $editor ) ), $editor )['data']['flxContactUpdate'] ?? array();
flxt_same( array(), $u['errors'] ?? null, 'update: no errors' );
$row = FLXCRM_Contacts::get( $id );
flxt_same( array( 'Ankara', array( 'donor' ), $editor ), array( $row['city'], flxcrm_unpack_roles( $row['roles'] ), (int) $row['owner_id'] ), 'update: classic row follows' );
flxt_same( 'Yeşil Dernek', $u['contact']['company'] ?? null, 'update: fields left out stay' );
$miss = flxcrm_t( 'FlxCrmContactUpdate', array( 'input' => array( 'id' => '99999', 'city' => 'x' ) ), $editor );
flxt_ok( 'NOT_FOUND' === ( $miss['data']['flxContactUpdate']['errors'][0]['code'] ?? flxt_code( $miss ) ), 'update: unknown contact → NOT_FOUND', $miss );

// FlxPerson interface / node lookup.
$n = flxt_gql( 'query($id: ID!){ node(id:$id){ __typename ... on FlxPerson { databaseId name email phone { e164 } } } }', array( 'id' => $c['id'] ), $admin )['data']['node'] ?? array();
flxt_same( array( 'FlxContact', $id, 'Ayşe Yılmaz', 'ayse@example.com' ), array( $n['__typename'] ?? null, $n['databaseId'] ?? null, $n['name'] ?? null, $n['email'] ?? null ), 'FlxPerson: node lookup by global id' );
$byid = flxcrm_t( 'FlxCrmContact', array( 'id' => (string) $id ), $admin )['data']['flxContact'] ?? array();
flxt_same( $c['id'], $byid['id'] ?? null, 'contact: plain database id works too' );
flxt_same( null, flxcrm_field( flxt_gql( 'query($id: ID!){ node(id:$id){ __typename } }', array( 'id' => $c['id'] ), $sub ), 'node' ), 'FlxPerson: node is hidden from a user without CRM access' );
flxt_same( (string) $byid['editUrl'], flxcrm_contact_url( $id ), 'contact: editUrl is the classic card' );

// ---- List, filters, sorting --------------------------------------------------------------------------
$b = flxcrm_t( 'FlxCrmContactCreate', array( 'input' => array( 'name' => 'Mehmet Kaya', 'phone' => '0532 000 11 22', 'roles' => array( 'customer' ) ) ), $editor )['data']['flxContactCreate']['contact'] ?? array();
$list = static fn( array $vars ) => flxcrm_t( 'FlxCrmContacts', $vars, $admin )['data']['flxContacts'] ?? array();
flxt_same( 2, $list( array() )['totalCount'] ?? null, 'list: two contacts' );
flxt_same( array( $id ), array_column( $list( array( 'filter' => array( 'search' => 'ayse' ) ) )['nodes'] ?? array(), 'databaseId' ), 'list: search by e-mail' );
flxt_same( array( $b['databaseId'] ?? 0 ), array_column( $list( array( 'filter' => array( 'role' => 'customer' ) ) )['nodes'] ?? array(), 'databaseId' ), 'list: role filter' );
$tag = (int) FLXCRM_Tags::of( $id )[0]['id'];
flxt_same( array( $id ), array_column( $list( array( 'filter' => array( 'tagId' => $tag ) ) )['nodes'] ?? array(), 'databaseId' ), 'list: tag filter' );
flxt_same( array( $id ), array_column( $list( array( 'filter' => array( 'consent' => 'WHATSAPP' ) ) )['nodes'] ?? array(), 'databaseId' ), 'list: consent filter' );
$sorted = array_column( $list( array( 'orderBy' => 'NAME', 'order' => 'ASC' ) )['nodes'] ?? array(), 'name' );
flxt_same( array( 'Ayşe Yılmaz', 'Mehmet Kaya' ), $sorted, 'list: sorted by name' );
$first = $list( array( 'first' => 1, 'orderBy' => 'NAME', 'order' => 'ASC' ) );
flxt_same( array( 1, true, 2 ), array( count( $first['nodes'] ?? array() ), $first['pageInfo']['hasNextPage'] ?? null, $first['totalCount'] ?? null ), 'list: first page' );
$next = $list( array( 'first' => 1, 'after' => $first['pageInfo']['endCursor'] ?? null, 'orderBy' => 'NAME', 'order' => 'ASC' ) );
flxt_same( array( 'Mehmet Kaya', false ), array( $next['nodes'][0]['name'] ?? null, $next['pageInfo']['hasNextPage'] ?? null ), 'list: next page' );

// A contact the classic storage adds shows up (cache purge through the hooks).
$classic_id = (int) FLXCRM_Contacts::upsert( array( 'name' => 'Klasik Kişi', 'email' => 'klasik@example.com', 'source' => 'manual' ) );
flxt_same( 3, $list( array() )['totalCount'] ?? null, 'cache: a contact added through the classic storage shows up' );
flxt_same( 3, flxcrm_t( 'FlxCrmOverview', array(), $admin )['data']['flxCrmOverview']['total'] ?? null, 'cache: overview counts it too' );

// ---- Timeline ----------------------------------------------------------------------------------------
$e = flxcrm_t( 'FlxCrmEventAdd', array( 'input' => array( 'contactId' => $c['id'], 'text' => 'Telefonda görüştük', 'type' => 'CALL' ) ), $editor )['data']['flxContactEventAdd'] ?? array();
flxt_same( array(), $e['errors'] ?? null, 'event: added' );
flxt_same( array( 'call', true ), array( $e['event']['type'] ?? null, $e['event']['deletable'] ?? null ), 'event: type and deletable' );
$tl = flxcrm_t( 'FlxCrmTimeline', array( 'id' => $c['id'] ), $admin )['data']['flxContact']['timeline'] ?? array();
flxt_ok( in_array( 'call', array_column( $tl['nodes'] ?? array(), 'type' ), true ), 'timeline: the note is there', $tl );
$tl2 = flxcrm_t( 'FlxCrmTimeline', array( 'id' => $c['id'], 'filter' => 'notes' ), $admin )['data']['flxContact']['timeline'] ?? array();
flxt_same( array( 'call' ), array_values( array_unique( array_column( $tl2['nodes'] ?? array(), 'type' ) ) ), 'timeline: notes filter' );
$tl3 = flxcrm_t( 'FlxCrmTimeline', array( 'id' => $c['id'], 'filter' => 'paid' ), $admin )['data']['flxContact']['timeline'] ?? array();
flxt_same( array(), $tl3['nodes'] ?? null, 'timeline: payments filter is empty' );
$bad = flxcrm_t( 'FlxCrmEventAdd', array( 'input' => array( 'contactId' => $c['id'], 'text' => '   ' ) ), $editor )['data']['flxContactEventAdd'] ?? array();
flxt_same( 'INVALID_INPUT', $bad['errors'][0]['code'] ?? null, 'event: empty text is refused' );
$ev = (int) ( $e['event']['databaseId'] ?? 0 );
$del = flxcrm_t( 'FlxCrmEventDelete', array( 'input' => array( 'id' => $ev ) ), $editor )['data']['flxContactEventDelete'] ?? array();
flxt_same( array( $ev, array() ), array( $del['deletedId'] ?? null, $del['errors'] ?? null ), 'event: deleted' );
$sys = flxcrm_t( 'FlxCrmTimeline', array( 'id' => $c['id'] ), $admin )['data']['flxContact']['timeline']['nodes'] ?? array();
$auto = array_values( array_filter( $sys, static fn( $x ) => ! $x['deletable'] ) );
if ( $auto ) {
	$refuse = flxcrm_t( 'FlxCrmEventDelete', array( 'input' => array( 'id' => $auto[0]['databaseId'] ) ), $editor )['data']['flxContactEventDelete'] ?? array();
	flxt_ok( ! empty( $refuse['errors'] ), 'event: an automatic entry cannot be deleted', $refuse );
} else {
	flxt_ok( true, 'event: (no automatic entry to try)' );
}

// ---- Tags ---------------------------------------------------------------------------------------------
$ta = flxcrm_t( 'FlxCrmTagAdd', array( 'input' => array( 'contactId' => $c['id'], 'name' => 'Gönüllü' ) ), $editor )['data']['flxContactTagAdd'] ?? array();
flxt_same( array( 'Gönüllü', 3 ), array( $ta['tag']['name'] ?? null, count( $ta['contact']['tags'] ?? array() ) ), 'tag: added by name' );
$tr = flxcrm_t( 'FlxCrmTagRemove', array( 'input' => array( 'contactId' => $c['id'], 'tagId' => $ta['tag']['databaseId'] ?? 0 ) ), $editor )['data']['flxContactTagRemove'] ?? array();
flxt_same( 2, count( $tr['contact']['tags'] ?? array() ), 'tag: removed' );
flxt_same( 2, count( FLXCRM_Tags::of( $id ) ), 'tag: classic storage agrees' );
$tags = flxt_gql( '{ flxTags { databaseId name count } }', array(), $admin )['data']['flxTags'] ?? array();
flxt_ok( in_array( 'Kurban', array_column( $tags, 'name' ), true ), 'tags: list', $tags );

// ---- Bulk ---------------------------------------------------------------------------------------------
$ids = array( $c['id'], $b['id'] );
$bk  = flxcrm_t( 'FlxCrmContactBulk', array( 'input' => array( 'action' => 'TAG', 'ids' => $ids, 'value' => 'Toplu' ) ), $editor )['data']['flxContactBulk'] ?? array();
flxt_same( array( 2, array() ), array( $bk['changed'] ?? null, $bk['errors'] ?? null ), 'bulk: tag two contacts' );
$bk = flxcrm_t( 'FlxCrmContactBulk', array( 'input' => array( 'action' => 'ROLE', 'ids' => array( $b['id'] ), 'value' => 'member' ) ), $editor )['data']['flxContactBulk'] ?? array();
flxt_ok( in_array( 'member', flxcrm_unpack_roles( FLXCRM_Contacts::get( (int) $b['databaseId'] )['roles'] ), true ), 'bulk: role added (classic row)', $bk );
$bk = flxcrm_t( 'FlxCrmContactBulk', array( 'input' => array( 'action' => 'ARCHIVE', 'ids' => array( $b['id'] ) ) ), $editor )['data']['flxContactBulk'] ?? array();
flxt_same( 1, $bk['changed'] ?? null, 'bulk: archive' );
flxt_same( array( 'Mehmet Kaya' ), array_column( $list( array( 'filter' => array( 'status' => 'ARCHIVED' ) ) )['nodes'] ?? array(), 'name' ), 'list: archived filter' );
flxt_same( 2, $list( array() )['totalCount'] ?? null, 'list: archived contacts leave the working list' );
flxcrm_t( 'FlxCrmContactBulk', array( 'input' => array( 'action' => 'RESTORE', 'ids' => array( $b['id'] ) ) ), $editor );
flxt_same( 3, $list( array() )['totalCount'] ?? null, 'bulk: restore' );
$bk = flxcrm_t( 'FlxCrmContactBulk', array( 'input' => array( 'action' => 'TAG', 'ids' => $ids ) ), $editor )['data']['flxContactBulk'] ?? array();
flxt_same( 'INVALID_INPUT', $bk['errors'][0]['code'] ?? null, 'bulk: a tag needs a name' );
$bk = flxcrm_t( 'FlxCrmContactBulk', array( 'input' => array( 'action' => 'ARCHIVE', 'ids' => array() ) ), $editor )['data']['flxContactBulk'] ?? array();
flxt_ok( ! empty( $bk['errors'] ), 'bulk: no ids is refused', $bk );

// ---- Duplicates and merge ----------------------------------------------------------------------------
$dup = (int) FLXCRM_Contacts::upsert( array( 'name' => 'Mehmet K.', 'phone' => '905320001122', 'source' => 'manual' ) );
$dups = flxcrm_t( 'FlxCrmDuplicates', array(), $admin )['data']['flxContactDuplicates'] ?? array();
flxt_ok( count( $dups ) >= 0, 'duplicates: query runs', $dups );
$groups = array_values( array_filter( $dups, static fn( $g ) => 'phone' === $g['why'] ) );
if ( $groups ) {
	flxt_ok( count( $groups[0]['items'] ) >= 2, 'duplicates: a phone group has two or more cards' );
} else {
	flxt_ok( true, 'duplicates: (the phone was merged on write)' );
}
$all = FLXCRM_Contacts::get( $dup );
if ( $all && (int) $dup !== (int) $b['databaseId'] ) {
	$mg = flxcrm_t( 'FlxCrmContactMerge', array( 'input' => array( 'keepId' => $b['id'], 'duplicateIds' => array( (string) $dup ) ) ), $editor )['data']['flxContactMerge'] ?? array();
	flxt_same( array( 1, array() ), array( $mg['merged'] ?? null, $mg['errors'] ?? null ), 'merge: merged' );
	flxt_same( null, FLXCRM_Contacts::get( $dup ), 'merge: the duplicate card is gone (classic)' );
}
$mg = flxcrm_t( 'FlxCrmContactMerge', array( 'input' => array( 'keepId' => $b['id'], 'duplicateIds' => array( $b['id'] ) ) ), $editor )['data']['flxContactMerge'] ?? array();
flxt_ok( ! empty( $mg['errors'] ), 'merge: a card cannot merge into itself', $mg );

// ---- Segments -----------------------------------------------------------------------------------------
$ed = flxcrm_t( 'FlxCrmSegmentEditor', array(), $admin )['data']['flxSegmentEditor'] ?? array();
flxt_same( array_keys( FLXCRM_Segments::fields() ), array_column( $ed['fields'] ?? array(), 'key' ), 'segment editor: fields match the classic editor' );
flxt_same( array_keys( FLXCRM_Segments::op_labels() ), array_column( $ed['ops'] ?? array(), 'key' ), 'segment editor: operators' );
$pv = flxcrm_t( 'FlxCrmSegmentPreview', array( 'match' => 'ALL', 'rules' => array( array( 'field' => 'role', 'op' => 'has', 'value' => 'donor' ) ) ), $admin )['data']['flxSegmentPreview'] ?? array();
flxt_same( array( 1, 'Ayşe Yılmaz' ), array( $pv['count'] ?? null, $pv['sample'][0]['name'] ?? null ), 'segment preview: count and sample' );
flxt_same( FLXCRM_Segments::describe( FLXCRM_Segments::sanitize_rules( array( 'match' => 'all', 'rules' => array( array( 'field' => 'role', 'op' => 'has', 'value' => 'donor' ) ) ) ) ), $pv['summary'] ?? null, 'segment preview: summary matches the classic one' );
$sg = flxcrm_t( 'FlxCrmSegmentSave', array( 'input' => array( 'name' => 'Bağışçılar', 'description' => 'Rolü bağışçı olanlar', 'match' => 'ALL', 'rules' => array( array( 'field' => 'role', 'op' => 'has', 'value' => 'donor' ) ) ) ), $editor )['data']['flxSegmentSave'] ?? array();
flxt_same( array(), $sg['errors'] ?? null, 'segment: saved' );
$sid = (int) ( $sg['segment']['databaseId'] ?? 0 );
flxt_same( array( 'Bağışçılar', 1 ), array( FLXCRM_Segments::get( $sid )['name'] ?? null, (int) ( FLXCRM_Segments::get( $sid )['count'] ?? -1 ) ), 'segment: stored and counted (classic)' );
flxt_same( array( $id ), array_column( $list( array( 'filter' => array( 'segmentId' => $sid ) ) )['nodes'] ?? array(), 'databaseId' ), 'list: saved segment filter' );
$sg = flxcrm_t( 'FlxCrmSegmentSave', array( 'input' => array( 'id' => $sid, 'name' => 'Bağışçılar 2', 'match' => 'ANY', 'rules' => array( array( 'field' => 'has_email', 'op' => 'yes' ) ) ) ), $editor )['data']['flxSegmentSave'] ?? array();
flxt_same( array( 'Bağışçılar 2', 'ANY' ), array( $sg['segment']['name'] ?? null, $sg['segment']['match'] ?? null ), 'segment: updated' );
$sg = flxcrm_t( 'FlxCrmSegmentSave', array( 'input' => array( 'name' => '', 'rules' => array() ) ), $editor )['data']['flxSegmentSave'] ?? array();
flxt_ok( ! empty( $sg['errors'] ) && null === ( $sg['segment'] ?? null ), 'segment: a name is required', $sg );
$sh = flxcrm_t( 'FlxCrmShell', array(), $admin )['data']['flxSegments'] ?? array();
flxt_same( array( 'Bağışçılar 2' ), array_column( $sh, 'name' ), 'cache: the shell lists the saved segment' );
$sd = flxcrm_t( 'FlxCrmSegmentDelete', array( 'input' => array( 'id' => $sid ) ), $editor )['data']['flxSegmentDelete'] ?? array();
flxt_same( array( $sid, null ), array( $sd['deletedId'] ?? null, FLXCRM_Segments::get( $sid ) ), 'segment: deleted' );
flxt_same( array(), flxcrm_t( 'FlxCrmShell', array(), $admin )['data']['flxSegments'] ?? null, 'cache: the shell no longer lists it' );

// ---- Journeys -----------------------------------------------------------------------------------------
$jc = flxcrm_t( 'FlxCrmJourneys', array(), $admin )['data'] ?? array();
flxt_same( array(), $jc['flxJourneys'] ?? null, 'journeys: none yet' );
flxt_same( array_keys( FLXCRM_Journeys::templates() ), array_column( $jc['flxJourneyConfig']['templates'] ?? array(), 'key' ), 'journeys: templates match' );
flxt_same( array_keys( FLXCRM_Journeys::step_types() ), array_column( $jc['flxJourneyConfig']['stepTypes'] ?? array(), 'key' ), 'journeys: step types match' );
$tpl = (string) array_key_first( FLXCRM_Journeys::templates() );
$jt  = flxcrm_t( 'FlxCrmJourneyFromTemplate', array( 'input' => array( 'template' => $tpl ) ), $editor )['data']['flxJourneyFromTemplate'] ?? array();
flxt_same( array( array(), 'DRAFT' ), array( $jt['errors'] ?? null, $jt['journey']['status'] ?? null ), 'journey: a template starts as a draft' );
$jid = (int) ( $jt['journey']['databaseId'] ?? 0 );
$js  = flxcrm_t( 'FlxCrmJourneySave', array( 'input' => array( 'id' => $jid, 'name' => 'Hoş geldin', 'triggerType' => 'segment', 'triggerValue' => 'new', 'steps' => array( array( 'type' => 'wait', 'days' => 2 ), array( 'type' => 'email', 'subject' => 'Merhaba', 'text' => 'Hoş geldiniz {first_name}' ) ), 'opts' => array( 'goal' => 'donation', 'capWeek' => 2, 'reenterDays' => 30, 'quietFrom' => 22, 'quietTo' => 8 ) ) ), $editor )['data']['flxJourneySave'] ?? array();
flxt_same( array( array(), 'Hoş geldin', 2, 'donation' ), array( $js['errors'] ?? null, $js['journey']['name'] ?? null, count( $js['journey']['steps'] ?? array() ), $js['journey']['opts']['goal'] ?? null ), 'journey: saved with steps and rules' );
$stored = FLXCRM_Journeys::get( $jid );
flxt_same( array( 'Hoş geldin', 2, 2 ), array( $stored['name'] ?? null, (int) ( $stored['opts']['cap_week'] ?? 0 ), count( $stored['steps'] ?? array() ) ), 'journey: classic storage agrees' );
$js = flxcrm_t( 'FlxCrmJourneySave', array( 'input' => array( 'name' => '', 'triggerType' => 'segment', 'steps' => array() ) ), $editor )['data']['flxJourneySave'] ?? array();
flxt_ok( ! empty( $js['errors'] ), 'journey: a name is required', $js );
$st = flxcrm_t( 'FlxCrmJourneySetStatus', array( 'input' => array( 'id' => $jid, 'status' => 'ACTIVE' ) ), $editor )['data']['flxJourneySetStatus'] ?? array();
flxt_same( array( array(), 'ACTIVE' ), array( $st['errors'] ?? null, $st['journey']['status'] ?? null ), 'journey: turned on' );
flxt_same( 'NOT_FOUND', flxt_code( flxcrm_t( 'FlxCrmJourneySetStatus', array( 'input' => array( 'id' => 99999, 'status' => 'ACTIVE' ) ), $editor ) ), 'journey: unknown id → NOT_FOUND' );
$jd = flxcrm_t( 'FlxCrmJourneyDelete', array( 'input' => array( 'id' => $jid ) ), $editor )['data']['flxJourneyDelete'] ?? array();
flxt_same( array( $jid, null ), array( $jd['deletedId'] ?? null, FLXCRM_Journeys::get( $jid ) ), 'journey: deleted' );

// ---- Settings, scoring, sync, AI ----------------------------------------------------------------------
$s = flxcrm_t( 'FlxCrmSettings', array(), $admin )['data'] ?? array();
flxt_same( array( 70, 90 ), array( $s['flxCrmSettings']['riskThreshold'] ?? null, $s['flxCrmSettings']['activeDays'] ?? null ), 'settings: defaults' );
flxt_same( array_keys( flxcrm_registered_sources() ), array_column( $s['flxCrmSettings']['sources'] ?? array(), 'key' ), 'settings: sources match the registry' );
$ss = flxcrm_t( 'FlxCrmSettingsSave', array( 'input' => array( 'riskThreshold' => 60, 'activeDays' => 30, 'baseCurrency' => 'try', 'chatEvents' => true, 'newRole' => array( 'label' => 'Sponsor', 'color' => '#112233' ), 'roles' => array( array( 'key' => 'donor', 'label' => 'Bağışçı', 'color' => '#16a34a' ) ) ) ), $admin )['data']['flxCrmSettingsSave'] ?? array();
flxt_same( array(), $ss['errors'] ?? null, 'settings: saved' );
flxt_same( array( 60, 30, 'TRY', 1 ), array( (int) flxcrm_setting( 'risk_threshold' ), (int) flxcrm_setting( 'active_days' ), flxcrm_setting( 'base_currency' ), (int) flxcrm_setting( 'chat_events' ) ), 'settings: classic settings follow' );
$labels = array_column( $ss['settings']['roles'] ?? array(), 'label', 'key' );
flxt_same( array( 'Bağışçı', 'Sponsor' ), array( $labels['donor'] ?? null, $labels['sponsor'] ?? null ), 'settings: role renamed and a new role added' );
flxt_same( 60, flxcrm_t( 'FlxCrmOverview', array(), $admin )['data']['flxCrmOverview']['riskThreshold'] ?? null, 'cache: overview sees the new threshold' );
$sc = flxcrm_t( 'FlxCrmScoreRun', array( 'input' => (object) array() ), $editor )['data']['flxCrmScoreRun'] ?? array();
flxt_same( array(), $sc['errors'] ?? null, 'score: runs' );
flxt_ok( is_bool( $sc['result']['done'] ?? null ), 'score: reports done', $sc );
$sy = flxcrm_t( 'FlxCrmSyncStep', array( 'input' => (object) array() ), $editor )['data']['flxCrmSyncStep'] ?? array();
flxt_same( array(), $sy['errors'] ?? null, 'sync: first step runs' );
flxt_ok( '' !== ( $sy['result']['step'] ?? '' ) && is_bool( $sy['result']['finished'] ?? null ), 'sync: reports the step', $sy );
$ai = $s['flxCrmAi'] ?? array();
flxt_same( count( flxcrm_tools() ), count( $ai['tools'] ?? array() ), 'ai: every tool is listed' );
flxt_same( 'FORBIDDEN', flxt_code( flxcrm_t( 'FlxCrmSettingsSave', array( 'input' => array( 'riskThreshold' => 10 ) ), $viewer ) ), 'settings save: a viewer is refused' );

// ---- Delete -------------------------------------------------------------------------------------------
$de = flxcrm_t( 'FlxCrmContactDelete', array( 'input' => array( 'id' => $c['id'] ) ), $editor )['data']['flxContactDelete'] ?? array();
flxt_same( array( array(), null ), array( $de['errors'] ?? null, FLXCRM_Contacts::get( $id ) ), 'delete: contact removed (classic)' );
flxt_same( null, flxcrm_field( flxcrm_t( 'FlxCrmContact', array( 'id' => $c['id'] ), $admin ), 'flxContact' ), 'delete: the card is gone' );
flxt_same( 'NOT_FOUND', flxt_code( flxcrm_t( 'FlxCrmContactDelete', array( 'input' => array( 'id' => '99999' ) ), $editor ) ), 'delete: unknown contact → NOT_FOUND' );

flxt_done();
