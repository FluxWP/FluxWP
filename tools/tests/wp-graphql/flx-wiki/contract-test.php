<?php
/**
 * Flux Wiki GraphQL contract tests. The documents are the persisted operations the Wiki screen and the
 * visitor-facing reads send (plugins/flx-wiki/graphql/persisted-queries.json). REST, MCP and the classic
 * settings page share the services, so their behaviour is checked through the services and the routes too.
 *
 *   wp eval-file tools/tests/wp-graphql/flx-wiki/contract-test.php
 */

require dirname( __DIR__ ) . '/lib.php';

echo "Flux Wiki GraphQL\n";

$GLOBALS['flxwk_ops'] = array();
foreach ( (array) json_decode( (string) file_get_contents( FLXWK_DIR . 'graphql/persisted-queries.json' ), true ) as $doc ) {
	if ( preg_match( '/^(?:query|mutation) (Flx\w+)/', $doc, $m ) ) {
		$GLOBALS['flxwk_ops'][ $m[1] ] = $doc;
	}
}
flxt_same( 14, count( $GLOBALS['flxwk_ops'] ), 'Flux Wiki ships 14 persisted operations' );

function flxwk_t_op( string $name, array $vars = array(), int $user = 0 ): array {
	return flxt_gql( $GLOBALS['flxwk_ops'][ $name ], $vars, $user );
}

/** Payload errors as [ code, field ] pairs. */
function flxwk_t_errs( array $r, string $field ): array {
	return array_map( static fn( $e ) => array( $e['code'], $e['field'] ), $r['data'][ $field ]['errors'] ?? array() );
}

/* Flux AI is not part of the test site: a stand-in that answers from whatever pages it is given. */
if ( ! function_exists( 'flxai_chat' ) ) {
	function flxai_ready( $source = '' ) {
		return true;
	}
	function flxai_chat( $messages, $opts = array() ) {
		return array( 'content' => 'Follow the installation guide.' );
	}
}

$admin  = 1;
$edit   = (int) wp_insert_user( array( 'user_login' => 'flxwk_ed_' . wp_rand(), 'user_pass' => wp_generate_password(), 'role' => 'editor' ) );
$contr  = (int) wp_insert_user( array( 'user_login' => 'flxwk_co_' . wp_rand(), 'user_pass' => wp_generate_password(), 'role' => 'contributor' ) );
$sub    = (int) wp_insert_user( array( 'user_login' => 'flxwk_su_' . wp_rand(), 'user_pass' => wp_generate_password(), 'role' => 'subscriber' ) );
$orig   = flxwk_settings();
$guides = count( flxwk_seed_files() );
flxwk_seed_import( false );
flxt_ok( $guides > 5, 'the bundled guides are there', $guides );

// Tree and sections: public while the wiki is open.
$r = flxwk_t_op( 'FlxWikiTree' );
flxt_same( null, $r['errors'] ?? null, 'tree: a visitor reads the open wiki' );
$groups = $r['data']['flxWikiTree'] ?? array();
$count  = 0;
foreach ( $groups as $g ) {
	$count += count( $g['pages'] );
}
flxt_same( count( flxwk_all_pages() ), $count, 'tree: every published page is in it exactly once' );
$tree = array_map( static fn( $g ) => $g['section']['slug'] ?? null, $groups );
flxt_ok( count( array_filter( $tree ) ) >= 1, 'tree: sections come first', $tree );
$first = $groups[0]['pages'][0] ?? array();
flxt_same( 0, $first['depth'] ?? null, 'tree: a section starts with a top-level page' );
$child = null;
foreach ( $groups as $g ) {
	foreach ( $g['pages'] as $i => $e ) {
		if ( $e['depth'] > 0 ) {
			$child = array( $g['pages'][ $i - 1 ] ?? null, $e );
			break 2;
		}
	}
}
flxt_ok( null !== $child && $child[0]['depth'] >= $child[1]['depth'] - 1, 'tree: a parent comes before its children' );
$r = flxt_gql( '{ flxWikiSections { databaseId slug name order pageCount url } }' );
flxt_ok( ! empty( $r['data']['flxWikiSections'] ) && ! empty( $r['data']['flxWikiSections'][0]['slug'] ), 'sections: listed for a visitor' );

// Search: same hits as the REST route.
$r    = flxwk_t_op( 'FlxWikiSearch', array( 'query' => 'kurulum' ) );
$gql  = $r['data']['flxWikiSearch'] ?? array();
$rest = rest_do_request( new WP_REST_Request( 'GET', '/flx-wiki/v1/search' ) );
$req  = new WP_REST_Request( 'GET', '/flx-wiki/v1/search' );
$req->set_param( 'q', 'kurulum' );
$rest = rest_do_request( $req )->get_data();
flxt_ok( $gql['total'] > 0, 'search: finds the installation guide', $gql );
flxt_same( $rest['total'], $gql['total'], 'search: total equals the REST route' );
flxt_same( array_column( $rest['results'], 'id' ), array_column( $gql['hits'], 'pageId' ), 'search: same pages in the same order as REST' );
flxt_same( array_column( $rest['results'], 'snippet' ), array_column( $gql['hits'], 'snippet' ), 'search: same snippets as REST' );
$r = flxwk_t_op( 'FlxWikiSearch', array( 'query' => 'kurlum' ) );
flxt_same( 'kurulum', $r['data']['flxWikiSearch']['fixed'] ?? null, 'search: a typo is fixed and reported' );
$r = flxwk_t_op( 'FlxWikiSearch', array( 'query' => '   ' ) );
flxt_same( array( 0, array() ), array( $r['data']['flxWikiSearch']['total'] ?? null, $r['data']['flxWikiSearch']['hits'] ?? null ), 'search: empty words → nothing' );

// One page: by path, by id, node(id:), Markdown and HTML.
$page = $gql['hits'][0] ?? array();
$post = get_post( (int) ( $page['pageId'] ?? 0 ) );
$r    = flxwk_t_op( 'FlxWikiPageView', array( 'path' => get_page_uri( $post ) ) );
$p    = $r['data']['flxWikiPage'] ?? array();
flxt_same( array( $post->ID, get_the_title( $post ), 'PUBLISH' ), array( $p['databaseId'] ?? null, $p['title'] ?? null, $p['status'] ?? null ), 'page: by path' );
flxt_ok( str_contains( (string) ( $p['html'] ?? '' ), '<h' ) && ! str_contains( (string) ( $p['html'] ?? '' ), '<script' ), 'page: rendered HTML' );
flxt_same( false, $p['canEdit'] ?? null, 'page: a visitor cannot edit' );
flxt_same( null, $p['editUrl'] ?? 'x', 'page: no edit link for a visitor' );
$r = flxt_gql( 'query($id: ID!) { flxWikiPage(id: $id) { databaseId markdown } }', array( 'id' => $p['id'] ?? '' ) );
flxt_same( flxwk_md( $post ), $r['data']['flxWikiPage']['markdown'] ?? null, 'page: Markdown source by global ID' );
$r = flxt_gql( 'query($id: ID!) { node(id: $id) { ... on FlxWikiPage { title } } }', array( 'id' => $p['id'] ?? '' ) );
flxt_same( get_the_title( $post ), $r['data']['node']['title'] ?? null, 'node(id:) resolves a wiki page' );
$r = flxt_gql( '{ flxWikiPage(path: "no/such/page") { title } }' );
flxt_ok( array_key_exists( 'flxWikiPage', $r['data'] ?? array() ) && null === $r['data']['flxWikiPage'] && empty( $r['errors'] ), 'page: unknown path → null' );

// Drafts are for editors only.
$draft = FLXWK_Page_Service::save( array( 'title' => 'Hidden draft', 'markdown' => "## Secret\n\nbody", 'status' => 'draft' ) );
flxt_ok( $draft instanceof FLXWK_Page, 'service: draft created' );
wp_set_current_user( $admin );
$did = $draft instanceof FLXWK_Page ? $draft->id : 0;
$r   = flxt_gql( 'query($id: ID!) { flxWikiPage(id: $id) { title } }', array( 'id' => $did ), 0 );
flxt_same( null, $r['data']['flxWikiPage'] ?? 'x', 'draft: a visitor gets null' );
$r = flxt_gql( 'query($id: ID!) { flxWikiPage(id: $id) { title markdown canEdit } }', array( 'id' => $did ), $admin );
flxt_same( array( 'Hidden draft', "## Secret\n\nbody", true ), array( $r['data']['flxWikiPage']['title'] ?? null, $r['data']['flxWikiPage']['markdown'] ?? null, $r['data']['flxWikiPage']['canEdit'] ?? null ), 'draft: an editor reads it' );
$r = flxt_gql( 'query($id: ID!) { node(id: $id) { ... on FlxWikiPage { title } } }', array( 'id' => flx_gql_global_id( 'FlxWikiPage', $did ) ), 0 );
flxt_same( null, $r['data']['node'] ?? 'x', 'draft: node(id:) hides it from a visitor' );
$r = flxwk_t_op( 'FlxWikiSearch', array( 'query' => 'Secret' ), 0 );
flxt_same( 0, $r['data']['flxWikiSearch']['total'] ?? null, 'draft: not searchable' );

// The page list is for editors.
$r = flxwk_t_op( 'FlxWikiPages' );
flxt_same( 'UNAUTHENTICATED', flxt_code( $r ), 'pages: visitor → UNAUTHENTICATED' );
$r = flxwk_t_op( 'FlxWikiPages', array(), $sub );
flxt_same( 'FORBIDDEN', flxt_code( $r ), 'pages: subscriber → FORBIDDEN' );
$r = flxwk_t_op( 'FlxWikiPages', array( 'first' => 5 ), $edit );
$l = $r['data']['flxWikiPages'] ?? array();
flxt_same( array( 5, true ), array( count( $l['nodes'] ?? array() ), $l['pageInfo']['hasNextPage'] ?? null ), 'pages: first page of 5, more follow' );
flxt_same( 'Hidden draft', $l['nodes'][0]['title'] ?? null, 'pages: most recently changed first' );
$total = (int) wp_count_posts( FLXWK_PT )->publish + (int) wp_count_posts( FLXWK_PT )->draft;
flxt_same( $total, $l['totalCount'] ?? null, 'pages: total count' );
$r = flxwk_t_op( 'FlxWikiPages', array( 'first' => 50, 'statuses' => array( 'DRAFT' ) ), $edit );
flxt_same( array( 'Hidden draft' ), array_column( $r['data']['flxWikiPages']['nodes'] ?? array(), 'title' ), 'pages: filter by status' );
$r = flxwk_t_op( 'FlxWikiPages', array( 'search' => 'Hidden draft' ), $edit );
flxt_same( array( 'Hidden draft' ), array_column( $r['data']['flxWikiPages']['nodes'] ?? array(), 'title' ), 'pages: filter by words' );
$sec = (string) ( $groups[0]['section']['slug'] ?? '' );
$r   = flxwk_t_op( 'FlxWikiPages', array( 'section' => $sec, 'first' => 50 ), $edit );
flxt_ok( count( $r['data']['flxWikiPages']['nodes'] ?? array() ) >= 1, 'pages: filter by section', $sec );
flxt_ok( true === ( $r['data']['flxWikiPages']['nodes'][0]['canEdit'] ?? null ), 'pages: editor can edit' );

// Save.
$r = flxwk_t_op( 'FlxWikiPageSave', array( 'input' => array( 'title' => 'GraphQL page', 'markdown' => "## Hello\n\n`code` and [[no-such]]", 'status' => 'PUBLISH', 'section' => 'Test section', 'summary' => 'Short one.', 'menuOrder' => 7 ) ), $admin );
$p = $r['data']['flxWikiPageSave'] ?? array();
flxt_same( array(), $p['errors'] ?? null, 'save: no errors' );
$new = $p['page'] ?? array();
flxt_same( array( 'GraphQL page', 'PUBLISH', 'Test section', 'Short one.', 7 ), array( $new['title'] ?? null, $new['status'] ?? null, $new['section']['name'] ?? null, $new['summary'] ?? null, $new['menuOrder'] ?? null ), 'save: fields are stored' );
flxt_same( "## Hello\n\n`code` and [[no-such]]", $new['markdown'] ?? null, 'save: Markdown source is kept as written' );
$nid = (int) ( $new['databaseId'] ?? 0 );
flxt_ok( str_contains( (string) get_post_field( 'post_content', $nid ), '<h2' ), 'save: post_content holds the HTML copy' );
$r = flxwk_t_op( 'FlxWikiPageSave', array( 'input' => array( 'id' => $new['id'] ?? '', 'markdown' => 'changed' ) ), $admin );
$u = $r['data']['flxWikiPageSave']['page'] ?? array();
flxt_same( array( 'GraphQL page', 'changed', 'PUBLISH', 7 ), array( $u['title'] ?? null, $u['markdown'] ?? null, $u['status'] ?? null, $u['menuOrder'] ?? null ), 'save: a field left out stays as it is' );
$r = flxwk_t_op( 'FlxWikiPageSave', array( 'input' => array( 'markdown' => 'x' ) ), $admin );
flxt_same( array( array( 'INVALID_INPUT', 'title' ) ), flxwk_t_errs( $r, 'flxWikiPageSave' ), 'save: a new page needs a title' );
$r = flxwk_t_op( 'FlxWikiPageSave', array( 'input' => array( 'id' => '99999999', 'title' => 'x' ) ), $admin );
flxt_same( array( array( 'NOT_FOUND', 'id' ) ), flxwk_t_errs( $r, 'flxWikiPageSave' ), 'save: unknown page' );
$r = flxwk_t_op( 'FlxWikiPageSave', array( 'input' => array( 'id' => (string) $nid, 'parentId' => $nid ) ), $admin );
flxt_same( array( array( 'INVALID_INPUT', 'parent' ) ), flxwk_t_errs( $r, 'flxWikiPageSave' ), 'save: a page cannot be its own parent' );
$r = flxwk_t_op( 'FlxWikiPageSave', array( 'input' => array( 'title' => 'Child', 'parentId' => $nid ) ), $admin );
$child = $r['data']['flxWikiPageSave']['page'] ?? array();
flxt_same( array( $nid, 'DRAFT' ), array( $child['parentId'] ?? null, $child['status'] ?? null ), 'save: a child under a parent; a new page is a draft' );
$r = flxwk_t_op( 'FlxWikiPageSave', array( 'input' => array( 'id' => (string) $nid, 'parentId' => (int) ( $child['databaseId'] ?? 0 ) ) ), $admin );
flxt_same( array( array( 'INVALID_INPUT', 'parent' ) ), flxwk_t_errs( $r, 'flxWikiPageSave' ), 'save: a page cannot go under its own child' );
$r = flxwk_t_op( 'FlxWikiPageSave', array( 'input' => array( 'id' => (string) ( $child['databaseId'] ?? 0 ), 'parentId' => 0 ) ), $admin );
flxt_same( null, $r['data']['flxWikiPageSave']['page']['parentId'] ?? 'x', 'save: parentId 0 makes it a top-level page' );

// Permissions of the author roles.
$r = flxwk_t_op( 'FlxWikiPageSave', array( 'input' => array( 'title' => 'By contributor', 'status' => 'PUBLISH' ) ), $contr );
flxt_same( 'PENDING', $r['data']['flxWikiPageSave']['page']['status'] ?? null, 'save: a contributor cannot publish; the page waits for review' );
$cid = (int) ( $r['data']['flxWikiPageSave']['page']['databaseId'] ?? 0 );
$r   = flxwk_t_op( 'FlxWikiPageSave', array( 'input' => array( 'id' => (string) $nid, 'title' => 'Hijack' ) ), $contr );
flxt_same( 'FORBIDDEN', flxt_code( $r ), "save: a contributor cannot edit someone else's page" );
flxt_same( 'GraphQL page', get_the_title( $nid ), 'save: the page was not changed' );
$r = flxwk_t_op( 'FlxWikiPageSave', array( 'input' => array( 'title' => 'x' ) ), $sub );
flxt_same( 'FORBIDDEN', flxt_code( $r ), 'save: subscriber → FORBIDDEN' );
$r = flxwk_t_op( 'FlxWikiPageSave', array( 'input' => array( 'title' => 'x' ) ) );
flxt_same( 'UNAUTHENTICATED', flxt_code( $r ), 'save: visitor → UNAUTHENTICATED' );

// Trash.
$r = flxwk_t_op( 'FlxWikiPageTrash', array( 'input' => array( 'id' => (string) $cid ) ), $sub );
flxt_same( 'FORBIDDEN', flxt_code( $r ), 'trash: subscriber → FORBIDDEN' );
$r = flxwk_t_op( 'FlxWikiPageTrash', array( 'input' => array( 'id' => (string) $nid ) ), $contr );
flxt_same( 'FORBIDDEN', flxt_code( $r ), "trash: a contributor cannot delete someone else's page" );
$r = flxwk_t_op( 'FlxWikiPageTrash', array( 'input' => array( 'id' => (string) $cid ) ), $admin );
flxt_same( array( $cid, 'trash' ), array( $r['data']['flxWikiPageTrash']['trashedId'] ?? null, get_post_status( $cid ) ), 'trash: moved to the trash' );
$r = flxwk_t_op( 'FlxWikiPageTrash', array( 'input' => array( 'id' => '99999999' ) ), $admin );
flxt_same( array( array( 'NOT_FOUND', 'id' ) ), flxwk_t_errs( $r, 'flxWikiPageTrash' ), 'trash: unknown page' );

// Sections.
$r = flxwk_t_op( 'FlxWikiSectionSave', array( 'input' => array( 'name' => 'Guides', 'order' => 3 ) ), $edit );
$s = $r['data']['flxWikiSectionSave']['section'] ?? array();
flxt_same( array( 'Guides', 'guides', 3 ), array( $s['name'] ?? null, $s['slug'] ?? null, $s['order'] ?? null ), 'section: created with an order' );
$sid = (int) ( $s['databaseId'] ?? 0 );
$r   = flxwk_t_op( 'FlxWikiSectionSave', array( 'input' => array( 'id' => $sid, 'name' => 'Handbook' ) ), $edit );
flxt_same( array( 'Handbook', 3 ), array( $r['data']['flxWikiSectionSave']['section']['name'] ?? null, $r['data']['flxWikiSectionSave']['section']['order'] ?? null ), 'section: renamed, the order is kept when omitted' );
$r = flxwk_t_op( 'FlxWikiSectionSave', array( 'input' => array( 'name' => '  ' ) ), $edit );
flxt_same( array( array( 'INVALID_INPUT', 'name' ) ), flxwk_t_errs( $r, 'flxWikiSectionSave' ), 'section: a name is required' );
$r = flxwk_t_op( 'FlxWikiSectionSave', array( 'input' => array( 'name' => 'x' ) ), $contr );
flxt_same( 'FORBIDDEN', flxt_code( $r ), 'section: a contributor cannot manage sections' );
$r = flxwk_t_op( 'FlxWikiSectionDelete', array( 'input' => array( 'id' => $sid ) ), $edit );
flxt_same( array( $sid, false ), array( $r['data']['flxWikiSectionDelete']['deletedId'] ?? null, (bool) get_term( $sid, FLXWK_TAX ) instanceof WP_Term ), 'section: deleted' );
$r = flxwk_t_op( 'FlxWikiSectionDelete', array( 'input' => array( 'id' => 99999999 ) ), $edit );
flxt_same( array( array( 'NOT_FOUND', 'id' ) ), flxwk_t_errs( $r, 'flxWikiSectionDelete' ), 'section: unknown section' );

// Preview.
$r = flxwk_t_op( 'FlxWikiPreview', array( 'input' => array( 'markdown' => "## One\n\n### Two\n\n<script>x</script>" ) ), $edit );
$p = $r['data']['flxWikiPreview']['rendered'] ?? array();
flxt_same( array( array( 'level' => 2, 'id' => 'one', 'text' => 'One' ), array( 'level' => 3, 'id' => 'two', 'text' => 'Two' ) ), $p['toc'] ?? null, 'preview: contents list' );
flxt_ok( ! str_contains( (string) ( $p['html'] ?? '' ), '<script' ), 'preview: HTML is sanitized' );
$req = new WP_REST_Request( 'POST', '/flx-wiki/v1/preview' );
$req->set_param( 'md', "## One\n\n### Two\n\n<script>x</script>" );
wp_set_current_user( $edit );
flxt_same( $p['html'] ?? null, rest_do_request( $req )->get_data()['html'], 'preview: same HTML as the REST route' );
$r = flxwk_t_op( 'FlxWikiPreview', array( 'input' => array( 'markdown' => 'x' ) ), $sub );
flxt_same( 'FORBIDDEN', flxt_code( $r ), 'preview: subscriber → FORBIDDEN' );

// Settings screen: overview + settings.
$r = flxwk_t_op( 'FlxWikiScreen', array(), $edit );
flxt_same( 'FORBIDDEN', flxt_code( $r ), 'screen: editor → FORBIDDEN (manage_options)' );
$r = flxwk_t_op( 'FlxWikiScreen', array(), $admin );
$d = $r['data'] ?? array();
flxt_same( null, $r['errors'] ?? null, 'screen: no errors for the administrator' );
flxt_same( array( 'wiki', flxwk_home_url(), count( flxwk_seed_files() ), (int) wp_count_posts( FLXWK_PT )->publish ), array( $d['flxWikiOverview']['base'] ?? null, $d['flxWikiOverview']['homeUrl'] ?? null, $d['flxWikiOverview']['guideCount'] ?? null, $d['flxWikiOverview']['publishedCount'] ?? null ), 'screen: addresses and counts' );
flxt_same( array( 'flxwk_search', 'flxwk_get_page', 'flxwk_list_pages', 'flxwk_save_page' ), $d['flxWikiOverview']['mcpTools'] ?? null, 'screen: MCP tools' );
$w = $d['flxSettings']['wiki'] ?? array();
flxt_same( array( true, 'USERS', 20, 'NORMAL', 'wiki' ), array( $w['enabled'] ?? null, $w['ask'] ?? null, $w['askLimit'] ?? null, $w['width'] ?? null, $w['base'] ?? null ), 'screen: settings = flxwk_settings defaults' );
flxt_same( 'Developer Wiki', $d['flxWikiOverview']['defaultTitle'] ?? null, 'screen: default name' );

$gen = (int) get_option( 'flxwk_gen', 0 );
$r   = flxwk_t_op( 'FlxWikiSettingsSave', array( 'input' => array( 'wiki' => array( 'title' => 'Handbook', 'width' => 'WIDE', 'ask' => 'ALL', 'askLimit' => 5, 'accent' => '#ff5500', 'base' => 'handbook' ) ) ), $admin );
flxt_same( array(), $r['data']['flxSettingsUpdate']['errors'] ?? null, 'settings: saved' );
$cur = flxwk_settings();
flxt_same( array( 'Handbook', 'wide', 'all', 5, '#ff5500', 'handbook' ), array( $cur['title'], $cur['width'], $cur['ask'], $cur['ask_limit'], $cur['accent'], $cur['base'] ), 'settings: written to flxwk_settings' );
flxt_ok( (int) get_option( 'flxwk_gen', 0 ) > $gen, 'settings: wiki caches are cleared' );
flxt_ok( (bool) get_option( 'flxwk_flush' ), 'settings: a new address base rewrites the address rules' );
$r = flxwk_t_op( 'FlxWikiSettingsSave', array( 'input' => array( 'wiki' => array( 'accent' => 'red' ) ) ), $admin );
flxt_same( 'wiki.accent', $r['data']['flxSettingsUpdate']['errors'][0]['field'] ?? null, 'settings: a bad colour → INVALID_INPUT' );
$r = flxwk_t_op( 'FlxWikiSettingsSave', array( 'input' => array( 'wiki' => array( 'title' => 'x' ) ) ), $edit );
flxt_same( 'FORBIDDEN', flxt_code( $r ), 'settings: editor → FORBIDDEN' );
// The classic page saves through the same service.
( new FLXWK_Admin_Service() )->save_tab( 'ai', array( 'ask' => 'bogus', 'ask_limit' => 9999 ) );
$cur = flxwk_settings();
flxt_same( array( 'users', 500 ), array( $cur['ask'], $cur['ask_limit'] ), 'classic: tab save clamps the same way' );
( new FLXWK_Admin_Service() )->save_tab( 'general', array( 'enabled' => 1, 'base' => 'Wiki!', 'title' => '' ) );
flxt_same( 'wiki', flxwk_settings()['base'], 'classic: the base is sanitised' );
update_option( 'flxwk_settings', $orig );
delete_option( 'flxwk_flush' );

// A closed wiki is for editors only.
update_option( 'flxwk_settings', array_merge( $orig, array( 'enabled' => 0 ) ) );
$r = flxwk_t_op( 'FlxWikiTree' );
flxt_same( 'UNAUTHENTICATED', flxt_code( $r ), 'closed wiki: visitor → UNAUTHENTICATED' );
$r = flxwk_t_op( 'FlxWikiSearch', array( 'query' => 'kurulum' ), $sub );
flxt_same( 'FORBIDDEN', flxt_code( $r ), 'closed wiki: subscriber → FORBIDDEN' );
$r = flxwk_t_op( 'FlxWikiTree', array(), $edit );
flxt_ok( ! empty( $r['data']['flxWikiTree'] ), 'closed wiki: an editor still reads it' );
update_option( 'flxwk_settings', $orig );

// Bundled guides.
$r = flxwk_t_op( 'FlxWikiGuidesImport', array(), $edit );
flxt_same( 'FORBIDDEN', flxt_code( $r ), 'guides: editor → FORBIDDEN' );
$r = flxwk_t_op( 'FlxWikiGuidesImport', array(), $admin );
$g = $r['data']['flxWikiGuidesImport']['result'] ?? array();
flxt_same( array( 0, 0 ), array( $g['created'] ?? null, $g['updated'] ?? null ), 'guides: nothing new to add or update' );
flxt_ok( ( $g['skipped'] ?? 0 ) >= $guides, 'guides: pages already there are skipped', $g );

// Ask the wiki.
$ask = static fn( int $user, string $q = 'How do I install a plugin?' ) => flxwk_t_op( 'FlxWikiAsk', array( 'input' => array( 'question' => $q ) ), $user );
$r   = $ask( 0 );
flxt_same( 'UNAUTHENTICATED', flxt_code( $r ), 'ask: visitor with "logged-in users" → UNAUTHENTICATED' );
$r = $ask( $edit );
flxt_same( null, $r['errors'] ?? null, 'ask: a logged-in user may ask' );
$a = $r['data']['flxWikiAsk']['answer'] ?? array();
flxt_ok( '' !== ( $a['answer'] ?? '' ) && is_array( $a['sources'] ?? null ), 'ask: an answer with its sources', $r );
$r = $ask( $edit, 'x' );
flxt_same( array( array( 'INVALID_INPUT', 'question' ) ), flxwk_t_errs( $r, 'flxWikiAsk' ), 'ask: a short question is refused' );
update_option( 'flxwk_settings', array_merge( $orig, array( 'ask_limit' => 1 ) ) );
delete_option( 'flxwk_ask_lock_' . substr( hash_hmac( 'sha256', 'u' . $edit, wp_salt() ), 0, 32 ) );
$r = $ask( $edit );
$r = $ask( $edit );
flxt_same( 'RATE_LIMITED', flxt_code( $r ), 'ask: the hourly limit → RATE_LIMITED' );
$r = $ask( $admin );
flxt_same( null, $r['errors'] ?? null, 'ask: administrators are not limited' );
update_option( 'flxwk_settings', array_merge( $orig, array( 'ask' => 'off' ) ) );
$r = $ask( $edit );
flxt_same( 'FORBIDDEN', flxt_code( $r ), 'ask: switched off → FORBIDDEN' );
update_option( 'flxwk_settings', array_merge( $orig, array( 'ask' => 'all' ) ) );
$r = $ask( 0 );
flxt_same( null, $r['errors'] ?? null, 'ask: "everyone" lets a visitor ask' );
$req = new WP_REST_Request( 'POST', '/flx-wiki/v1/ask' );
$req->set_param( 'q', 'How do I install a plugin?' );
wp_set_current_user( $admin );
$rest = rest_do_request( $req );
flxt_same( array( 200, array( 'answer', 'html', 'sources' ) ), array( $rest->get_status(), array_keys( $rest->get_data() ) ), 'ask: REST route answers with the same shape' );
update_option( 'flxwk_settings', $orig );

// MCP: the tools call the same services.
wp_set_current_user( $admin );
$tools = apply_filters( 'flx_mcp_tools', array() );
$saved = $tools['flxwk_save_page']['run']( array( 'title' => 'By MCP', 'markdown' => '## A', 'section' => 'MCP', 'excerpt' => 'Sum.' ) );
flxt_same( array( 'By MCP', 'draft', 'MCP' ), array( $saved['title'], $saved['status'], $saved['section'] ), 'MCP: flxwk_save_page creates a draft through the service' );
$threw = false;
try {
	$tools['flxwk_save_page']['run']( array( 'markdown' => 'no title' ) );
} catch ( Exception $e ) {
	$threw = 'A title is required.' === $e->getMessage();
}
flxt_ok( $threw, 'MCP: a new page without a title is refused with the same message' );
wp_set_current_user( $contr );
$threw = false;
try {
	$tools['flxwk_save_page']['run']( array( 'id' => $nid, 'title' => 'Hijack' ) );
} catch ( Exception $e ) {
	$threw = 'You cannot edit this page.' === $e->getMessage();
}
flxt_ok( $threw, "MCP: someone else's page is refused with the same message" );
$found = $tools['flxwk_search']['run']( array( 'query' => 'kurulum' ) );
flxt_same( $gql['total'], $found['total'], 'MCP: flxwk_search finds the same pages' );

// Persisted: the screen's documents are allowed in strict mode.
wp_set_current_user( $admin );
$r = graphql( array( 'queryId' => hash( 'sha256', $GLOBALS['flxwk_ops']['FlxWikiTree'] ) ) );
flxt_ok( ! empty( $r['data']['flxWikiTree'] ), 'persisted queryId runs (strict mode)' );

// Clean up.
foreach ( get_posts( array( 'post_type' => FLXWK_PT, 'post_status' => 'any', 'posts_per_page' => -1, 's' => '' ) ) as $p ) {
	if ( in_array( $p->post_title, array( 'Hidden draft', 'GraphQL page', 'Child', 'By contributor', 'By MCP' ), true ) ) {
		wp_delete_post( $p->ID, true );
	}
}
foreach ( array( 'Test section', 'MCP', 'Guides', 'Handbook' ) as $name ) {
	$t = get_term_by( 'name', $name, FLXWK_TAX );
	if ( $t ) {
		wp_delete_term( $t->term_id, FLXWK_TAX );
	}
}
update_option( 'flxwk_settings', $orig );
flxwk_bump();
foreach ( array( $edit, $contr, $sub ) as $u ) {
	wp_delete_user( $u );
}
flxt_done();
