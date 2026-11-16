<?php
/**
 * Flux Block Editor GraphQL contract tests. The documents are the persisted operations the Block Editor
 * screen and headless front ends send (plugins/flx-blocks/graphql/persisted-queries.json). The editor's
 * REST routes and the classic dashboard share the services, so they are checked through them too.
 *
 *   wp eval-file tools/tests/wp-graphql/flx-blocks/contract-test.php
 */

require dirname( __DIR__ ) . '/lib.php';

echo "Flux Block Editor GraphQL\n";

$GLOBALS['flxbk_ops'] = array();
$flxbk_manifest       = (array) json_decode( (string) file_get_contents( FLXBK_PATH . 'graphql/persisted-queries.json' ), true );
foreach ( $flxbk_manifest as $doc ) {
	if ( preg_match( '/^(?:query|mutation) (Flx\w+)/', $doc, $m ) ) {
		$GLOBALS['flxbk_ops'][ $m[1] ] = $doc;
	}
}
flxt_same( 13, count( $GLOBALS['flxbk_ops'] ), 'Flux Block Editor ships 13 persisted operations' );

/** Runs an operation; the result as it reaches a client (JSON). */
function flxbk_t_op( string $name, array $vars = array(), int $user = 0 ): array {
	$GLOBALS['flxbk_raw'] = (string) wp_json_encode( flxt_gql( $GLOBALS['flxbk_ops'][ $name ], $vars, $user ) );
	return (array) json_decode( $GLOBALS['flxbk_raw'], true );
}

function flxbk_t_handles( array $assets ): array {
	return array_column( $assets, 'handle' );
}

// Hermetic: Google Fonts are linked, not downloaded (the local font loader would fetch them from Google).
$flxbk_fonts_before = get_option( 'flxbk_font_settings' );
update_option( 'flxbk_font_settings', array( 'load_fonts_local' => 'false' ) );

$admin  = 1;
$editor = (int) wp_insert_user( array( 'user_login' => 'flxbk_ed_' . wp_rand(), 'user_pass' => wp_generate_password(), 'role' => 'editor' ) );
$author = (int) wp_insert_user( array( 'user_login' => 'flxbk_au_' . wp_rand(), 'user_pass' => wp_generate_password(), 'role' => 'author' ) );
$sub    = (int) wp_insert_user( array( 'user_login' => 'flxbk_sub_' . wp_rand(), 'user_pass' => wp_generate_password(), 'role' => 'subscriber' ) );

$row = static function ( string $id, string $color ): string {
	return '<!-- wp:flx/rowlayout {"uniqueID":"' . $id . '","columns":1,"bgColor":"' . $color . '"} -->
<div class="wp-block-flx-rowlayout"><!-- wp:flx/column {"uniqueID":"' . $id . 'c"} -->
<div class="wp-block-flx-column"><div class="fxb-inside-column"><!-- wp:flx/advancedheading {"uniqueID":"' . $id . 'h","color":"#00aa00","typography":"Roboto","googleFont":true,"loadGoogleFont":true} -->
<h2 class="wp-block-flx-advancedheading">Merhaba</h2>
<!-- /wp:flx/advancedheading --></div></div>
<!-- /wp:flx/column --></div>
<!-- /wp:flx/rowlayout -->';
};
$content = $row( '_ta', '#ff0000' ) . "\n<!-- wp:paragraph -->\n<p>Plain paragraph</p>\n<!-- /wp:paragraph -->";
$page    = (int) wp_insert_post( array( 'post_title' => 'Blocks page', 'post_status' => 'publish', 'post_type' => 'page', 'post_content' => $content ) );
$page2   = (int) wp_insert_post( array( 'post_title' => 'Second page', 'post_status' => 'publish', 'post_type' => 'page', 'post_content' => $content ) );
$locked  = (int) wp_insert_post( array( 'post_title' => 'Locked', 'post_status' => 'publish', 'post_type' => 'page', 'post_content' => $content, 'post_password' => 'secret' ) );
$draft   = (int) wp_insert_post( array( 'post_title' => 'Draft', 'post_status' => 'draft', 'post_type' => 'page', 'post_content' => $content, 'post_author' => $admin ) );

// Rendered content: a visitor reads a published page with everything it needs.
$r = flxbk_t_op( 'FlxBlocksContentById', array( 'id' => (string) $page, 'withBlocks' => true ) );
flxt_same( null, $r['errors'] ?? null, 'content: no errors for a visitor' );
$c = $r['data']['contentNode']['flxBlocks'] ?? array();
flxt_ok( str_contains( $c['html'] ?? '', 'Merhaba' ) && str_contains( $c['html'] ?? '', 'Plain paragraph' ), 'content: rendered HTML' );
flxt_ok( str_contains( $c['css'] ?? '', '#fxt-layout-id_ta{background-color:#ff0000;}' ), 'content: generated row CSS', substr( $c['css'] ?? '', 0, 200 ) );
flxt_ok( str_contains( $c['css'] ?? '', 'color:#00aa00' ), 'content: generated heading CSS' );
flxt_ok( ! str_contains( $c['html'] ?? '', '<style' ), 'content: CSS is not inlined into the HTML' );
$sh = flxbk_t_handles( $c['styles'] ?? array() );
flxt_ok( in_array( 'flx-blocks-rowlayout', $sh, true ) && in_array( 'flx-blocks-column', $sh, true ), 'content: block stylesheets', $sh );
flxt_ok( 1 === count( $c['fonts'] ?? array() ) && str_contains( (string) ( $c['fonts'][0] ?? '' ), 'fonts' ), 'content: one font stylesheet', $c['fonts'] ?? null );
flxt_ok( str_contains( $c['headHtml'] ?? '', "id='flx-blocks-rowlayout-css'" ) && str_contains( $c['headHtml'] ?? '', '<style id="flxbk-css">' ), 'content: headHtml has the stylesheet tags and the CSS' );
$blocks = $c['blocks'] ?? array();
flxt_same( array( 'flx/rowlayout', 'flx/column', 'flx/advancedheading', 'core/paragraph' ), array_column( $blocks, 'name' ), 'content: flat block tree in document order' );
flxt_same( array( '0', '0.0', '0.0.0', '1' ), array_column( $blocks, 'clientId' ), 'content: clientIds are position paths' );
flxt_same( array( null, '0', '0.0', null ), array_column( $blocks, 'parentClientId' ), 'content: parents' );
flxt_same( '#ff0000', $blocks[0]['attributes']['bgColor'] ?? null, 'content: attributes as saved' );
flxt_ok( str_contains( $GLOBALS['flxbk_raw'], '"attributes":{}' ), 'content: empty attributes are sent as {}' );

// Two pages in one request each get their complete CSS and files (render state is reset in between).
$q = 'query { a: contentNode(id: "' . $page . '", idType: DATABASE_ID) { flxBlocks { css styles { handle } } } b: contentNode(id: "' . $page2 . '", idType: DATABASE_ID) { flxBlocks { css styles { handle } } } }';
add_filter( 'flx_gql_free_queries_allowed', '__return_true' );
$r = (array) json_decode( (string) wp_json_encode( flxt_gql( $q, array(), $admin ) ), true );
remove_filter( 'flx_gql_free_queries_allowed', '__return_true' );
$a = $r['data']['a']['flxBlocks'] ?? array();
$b = $r['data']['b']['flxBlocks'] ?? array();
flxt_ok( '' !== ( $a['css'] ?? '' ) && ( $a['css'] ?? '' ) === ( $b['css'] ?? '' ), 'content: second render in the same request has the same CSS' );
flxt_same( flxbk_t_handles( $a['styles'] ?? array() ), flxbk_t_handles( $b['styles'] ?? array() ), 'content: second render has the same stylesheets' );
flxt_same( array(), Flx_Blocks_CSS::$styles, 'content: request CSS state is restored' );

// Password protected and draft content.
$r = flxbk_t_op( 'FlxBlocksContentById', array( 'id' => (string) $locked ) );
flxt_same( null, $r['data']['contentNode']['flxBlocks'] ?? null, 'content: password protected → null for a visitor' );
$r = flxbk_t_op( 'FlxBlocksContentById', array( 'id' => (string) $locked ), $admin );
flxt_ok( str_contains( $r['data']['contentNode']['flxBlocks']['html'] ?? '', 'Merhaba' ), 'content: password protected → rendered for an editor of it' );
$r = flxbk_t_op( 'FlxBlocksContentById', array( 'id' => (string) $draft ) );
flxt_same( null, $r['data']['contentNode'] ?? null, 'content: draft → not visible to a visitor' );
$r = flxbk_t_op( 'FlxBlocksContentById', array( 'id' => (string) $draft ), $admin );
flxt_ok( str_contains( $r['data']['contentNode']['flxBlocks']['html'] ?? '', 'Merhaba' ), 'content: draft → rendered for its editor' );

// By path (headless routing).
$r = flxbk_t_op( 'FlxBlocksContentByUri', array( 'uri' => (string) wp_make_link_relative( (string) get_permalink( $page ) ) ) );
flxt_ok( str_contains( $r['data']['nodeByUri']['flxBlocks']['css'] ?? '', 'background-color:#ff0000' ), 'content: by URI', $r['errors'] ?? null );

// Site styles: public.
$r = flxbk_t_op( 'FlxBlocksSiteStyles' );
flxt_ok( str_contains( $r['data']['flxBlocksSiteStyles']['css'] ?? '', '--global-palette1' ), 'site styles: palette variables for a visitor', $r['errors'] ?? null );
flxt_ok( str_contains( $r['data']['flxBlocksSiteStyles']['headHtml'] ?? '', 'flx-blocks-global-css' ), 'site styles: headHtml' );

// Render markup: editors only.
$r = flxbk_t_op( 'FlxBlocksRender', array( 'content' => $row( '_tb', '#0000ff' ) ) );
flxt_same( 'UNAUTHENTICATED', flxt_code( $r ), 'render: visitor → UNAUTHENTICATED' );
$r = flxbk_t_op( 'FlxBlocksRender', array( 'content' => $row( '_tb', '#0000ff' ) ), $sub );
flxt_same( 'FORBIDDEN', flxt_code( $r ), 'render: subscriber → FORBIDDEN' );
$r = flxbk_t_op( 'FlxBlocksRender', array( 'content' => $row( '_tb', '#0000ff' ) ), $author );
flxt_ok( str_contains( $r['data']['flxBlocksRender']['css'] ?? '', 'background-color:#0000ff' ), 'render: author previews markup', $r['errors'] ?? null );
$r = flxbk_t_op( 'FlxBlocksRender', array( 'content' => 'x', 'postId' => $draft ), $author );
flxt_same( 'FORBIDDEN', flxt_code( $r ), 'render: someone else\'s post as context → FORBIDDEN' );
$r = flxbk_t_op( 'FlxBlocksRender', array( 'content' => str_repeat( 'a', FLXBK_Render_Service::MAX_MARKUP + 1 ) ), $author );
flxt_same( 'INVALID_INPUT', flxt_code( $r ), 'render: too long → INVALID_INPUT' );

// Design library.
$mine = (int) wp_insert_post( array( 'post_title' => 'Flux test section', 'post_status' => 'publish', 'post_type' => 'wp_block', 'post_content' => $row( '_tm', '#123456' ) ) );
update_post_meta( $mine, 'wp_pattern_sync_status', 'unsynced' );
register_block_pattern( 'flx-test/hero', array( 'title' => 'Test hero', 'categories' => array( 'flx-test' ), 'keywords' => array( 'flxbanner' ), 'content' => $row( '_tp', '#abcdef' ) ) );
register_block_pattern( 'flx-test/cta', array( 'title' => 'Test call to action', 'categories' => array( 'flx-test' ), 'content' => '<!-- wp:paragraph --><p>CTA</p><!-- /wp:paragraph -->' ) );

$r = flxbk_t_op( 'FlxBlocksLibraryPatterns' );
flxt_same( 'UNAUTHENTICATED', flxt_code( $r ), 'library: visitor → UNAUTHENTICATED' );
$r = flxbk_t_op( 'FlxBlocksLibraryPatterns', array(), $sub );
flxt_same( 'FORBIDDEN', flxt_code( $r ), 'library: subscriber → FORBIDDEN' );
$r   = flxbk_t_op( 'FlxBlocksLibraryPatterns', array( 'first' => 50 ), $author );
$lib = $r['data']['flxBlocksLibrary'] ?? array();
$ids = array_column( $lib['patterns']['nodes'] ?? array(), 'id' );
flxt_ok( in_array( 'flx-test/hero', $ids, true ) && in_array( 'user/' . $mine, $ids, true ), 'library: registered and My patterns', $r['errors'] ?? $ids );
flxt_same( null, $lib['source'] ?? null, 'library: this site has no source id' );
$cats = array_column( $lib['categories'] ?? array(), 'count', 'slug' );
flxt_same( 2, $cats['flx-test'] ?? null, 'library: category counts' );
$r = flxbk_t_op( 'FlxBlocksLibraryPatterns', array( 'category' => 'flx-test', 'first' => 1 ), $author );
$p = $r['data']['flxBlocksLibrary']['patterns'] ?? array();
flxt_same( array( 1, 2, true ), array( count( $p['nodes'] ?? array() ), $p['totalCount'] ?? null, $p['pageInfo']['hasNextPage'] ?? null ), 'library: category filter and paging' );
$r = flxbk_t_op( 'FlxBlocksLibraryPatterns', array( 'search' => 'flxbanner' ), $author );
flxt_same( array( 'flx-test/hero' ), array_column( $r['data']['flxBlocksLibrary']['patterns']['nodes'] ?? array(), 'id' ), 'library: search keywords' );
$r = flxbk_t_op( 'FlxBlocksPatternPreview', array( 'id' => 'flx-test/hero' ), $author );
flxt_ok( str_contains( $r['data']['flxBlocksPattern']['rendered']['headHtml'] ?? '', 'background-color:#abcdef' ), 'library: pattern preview rendered with its CSS', $r['errors'] ?? null );
$r = flxbk_t_op( 'FlxBlocksPatternPreview', array( 'id' => 'nope/none' ), $author );
flxt_same( array( null, null ), array( $r['errors'] ?? null, $r['data']['flxBlocksPattern'] ?? null ), 'library: unknown pattern → null' );
$r = flxbk_t_op( 'FlxBlocksLibraryPatterns', array( 'source' => 'nope' ), $author );
flxt_same( 'NOT_FOUND', flxt_code( $r ), 'library: unknown source → NOT_FOUND' );

// Linked libraries.
$r = flxbk_t_op( 'FlxBlocksLibrarySourceAdd', array( 'input' => array( 'url' => 'https://example.org' ) ), $editor );
flxt_same( 'FORBIDDEN', flxt_code( $r ), 'sources: editor cannot add' );
$r = flxbk_t_op( 'FlxBlocksLibrarySourceAdd', array( 'input' => array( 'url' => 'not a url' ) ), $admin );
$e = $r['data']['flxBlocksLibrarySourceAdd']['errors'][0] ?? array();
flxt_same( array( 'INVALID_INPUT', 'url' ), array( $e['code'] ?? null, $e['field'] ?? null ), 'sources: bad address → payload error on url' );
$r = flxbk_t_op( 'FlxBlocksLibrarySourceAdd', array( 'input' => array( 'url' => 'https://flx-unreachable.invalid', 'key' => 'k' ) ), $admin );
$e = $r['data']['flxBlocksLibrarySourceAdd']['errors'][0] ?? array();
flxt_same( array( 'INVALID_INPUT', 'url' ), array( $e['code'] ?? null, $e['field'] ?? null ), 'sources: unreachable site → payload error on url' );
update_option( Flx_Blocks_Design_Library::OPT_SOURCES, array( array( 'id' => 'abc123', 'name' => 'Other site', 'url' => 'https://other.example/', 'key' => 'secret-key' ) ), false );
$r = flxbk_t_op( 'FlxBlocksDashboard', array(), $editor );
$s = $r['data']['flxBlocksLibrarySources'][0] ?? array();
flxt_same( array( 'abc123', 'Other site', true ), array( $s['id'] ?? null, $s['name'] ?? null, $s['hasKey'] ?? null ), 'sources: listed for editors, key hidden' );
flxt_ok( ! str_contains( wp_json_encode( $r ), 'secret-key' ), 'sources: the key never leaves the server' );
flxt_same( false, $r['data']['flxViewer']['canManageOptions'] ?? null, 'sources: editor cannot manage' );
$rest = rest_do_request( new WP_REST_Request( 'GET', '/flx-blocks/v1/library/sources' ) );
flxt_same( 'abc123', $rest->get_data()['sources'][0]['id'] ?? null, 'sources: REST route reads the same list' );
$r = flxbk_t_op( 'FlxBlocksLibrarySourceRemove', array( 'input' => array( 'id' => 'nope' ) ), $admin );
flxt_same( 'NOT_FOUND', flxt_code( $r ), 'sources: remove unknown → NOT_FOUND' );
$r = flxbk_t_op( 'FlxBlocksLibrarySourceRemove', array( 'input' => array( 'id' => 'abc123' ) ), $admin );
flxt_same( array( array(), array() ), array( $r['data']['flxBlocksLibrarySourceRemove']['sources'] ?? null, $r['data']['flxBlocksLibrarySourceRemove']['errors'] ?? null ), 'sources: removed' );

// Sharing.
$r = flxbk_t_op( 'FlxBlocksLibraryShare', array(), $editor );
flxt_same( 'FORBIDDEN', flxt_code( $r ), 'share: editor → FORBIDDEN' );
$r  = flxbk_t_op( 'FlxBlocksLibraryShareUpdate', array( 'input' => array( 'enabled' => true ) ), $admin );
$sh = $r['data']['flxBlocksLibraryShareUpdate']['share'] ?? array();
flxt_same( array( true, 32 ), array( $sh['enabled'] ?? null, strlen( (string) ( $sh['key'] ?? '' ) ) ), 'share: on with a new key' );
$r = flxbk_t_op( 'FlxBlocksLibraryShareUpdate', array( 'input' => array( 'regenerateKey' => true ) ), $admin );
flxt_ok( ( $r['data']['flxBlocksLibraryShareUpdate']['share']['key'] ?? '' ) !== ( $sh['key'] ?? '' ), 'share: new key' );
$r = flxbk_t_op( 'FlxBlocksLibraryShareUpdate', array( 'input' => array( 'enabled' => false ) ), $admin );
flxt_same( false, $r['data']['flxBlocksLibraryShareUpdate']['share']['enabled'] ?? null, 'share: off' );
flxt_same( false, Flx_Blocks_Design_Library::share_settings()['enabled'], 'share: REST side sees the change' );

// Block Editor dashboard.
$r = flxbk_t_op( 'FlxBlocksDashboard', array(), $sub );
flxt_same( 'FORBIDDEN', flxt_code( $r ), 'dashboard: subscriber → FORBIDDEN' );
$r     = flxbk_t_op( 'FlxBlocksDashboard', array(), $admin );
$types = array_column( $r['data']['flxBlocksBlockTypes'] ?? array(), 'active', 'slug' );
flxt_same( true, $types['flx/rowlayout'] ?? null, 'dashboard: blocks with state', $r['errors'] ?? null );
flxt_same( true, $r['data']['flxViewer']['canManageOptions'] ?? null, 'dashboard: admin may manage the library' );
flxt_same( null, $r['data']['flxBlocksSettings']['editorWidth'] ?? null, 'dashboard: no editor widths for this theme' );
$r = flxbk_t_op( 'FlxBlocksBlockTypeSetActive', array( 'input' => array( 'slug' => 'flx/lottie', 'active' => false ) ), $admin );
flxt_same( false, $r['data']['flxBlocksBlockTypeSetActive']['blockType']['active'] ?? null, 'dashboard: block off' );
flxt_ok( in_array( 'flx/lottie', (array) get_option( 'fxt_blocks_unregistered_blocks' ), true ), 'dashboard: stored where the editor reads it' );
$r = flxbk_t_op( 'FlxBlocksBlockTypeSetActive', array( 'input' => array( 'slug' => 'flx/lottie', 'active' => true ) ), $admin );
flxt_ok( ! in_array( 'flx/lottie', (array) get_option( 'fxt_blocks_unregistered_blocks' ), true ), 'dashboard: block on again' );
$r = flxbk_t_op( 'FlxBlocksBlockTypeSetActive', array( 'input' => array( 'slug' => 'flx/none', 'active' => true ) ), $admin );
flxt_same( 'slug', $r['data']['flxBlocksBlockTypeSetActive']['errors'][0]['field'] ?? null, 'dashboard: unknown block → payload error' );
$r = flxbk_t_op( 'FlxBlocksSettingsUpdate', array( 'input' => array( 'showHeadersInAdminBar' => true ) ), $admin );
flxt_same( true, $r['data']['flxBlocksSettingsUpdate']['settings']['showHeadersInAdminBar'] ?? null, 'dashboard: admin bar setting saved' );
flxt_same( 'true', get_option( 'flxbk_admin_bar_settings' )['show_headers_in_admin_bar'] ?? null, 'dashboard: stored as the classic form stores it' );
$r = flxbk_t_op( 'FlxBlocksSettingsUpdate', array( 'input' => array( 'editorWidth' => array( 'small' => 50 ) ) ), $admin );
flxt_same( 'small', $r['data']['flxBlocksSettingsUpdate']['errors'][0]['field'] ?? null, 'dashboard: width out of range → payload error' );
add_filter( 'flxbk_editor_width', '__return_true' );
$r = flxbk_t_op( 'FlxBlocksSettingsUpdate', array( 'input' => array( 'editorWidth' => array( 'small' => 800, 'postDefault' => 'FULL_WIDTH' ) ) ), $admin );
$w = $r['data']['flxBlocksSettingsUpdate']['settings']['editorWidth'] ?? array();
flxt_same( array( 800, 'FULL_WIDTH', 1140 ), array( $w['small'] ?? null, $w['postDefault'] ?? null, $w['large'] ?? null ), 'dashboard: editor widths saved', $r['errors'] ?? null );
remove_filter( 'flxbk_editor_width', '__return_true' );

if ( false === $flxbk_fonts_before ) {
	delete_option( 'flxbk_font_settings' );
} else {
	update_option( 'flxbk_font_settings', $flxbk_fonts_before );
}

flxt_done();
