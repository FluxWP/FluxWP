<?php
/**
 * Flux Lang GraphQL contract tests. The documents are the persisted operations the Languages screen and
 * other clients send (plugins/flx-lang/graphql/persisted-queries.json). The classic settings page and
 * GraphQL share FLXL_Language_Service, so the classic behaviour is checked through the service too.
 *
 *   wp eval-file tools/tests/wp-graphql/flx-lang/contract-test.php
 */

require dirname( __DIR__ ) . '/lib.php';

echo "Flux Lang GraphQL\n";

$GLOBALS['flxl_ops'] = array();
$GLOBALS['flxl_ids'] = array();
foreach ( (array) json_decode( (string) file_get_contents( FLXL_DIR . 'graphql/persisted-queries.json' ), true ) as $id => $doc ) {
	if ( preg_match( '/^(?:query|mutation) (Flx\w+)/', $doc, $m ) ) {
		$GLOBALS['flxl_ops'][ $m[1] ] = $doc;
		$GLOBALS['flxl_ids'][ $m[1] ] = $id;
	}
}
flxt_same( 10, count( $GLOBALS['flxl_ops'] ), 'Flux Lang ships 10 persisted operations' );

function flxl_t_op( string $name, array $vars = array(), int $user = 0 ): array {
	return flxt_gql( $GLOBALS['flxl_ops'][ $name ], $vars, $user );
}

/** Value at a path, null kept (the sentinel tells a missing key from null). */
function flxl_t_at( array $a, string ...$path ) {
	foreach ( $path as $k ) {
		if ( ! is_array( $a ) || ! array_key_exists( $k, $a ) ) {
			return '[missing]';
		}
		$a = $a[ $k ];
	}
	return $a;
}

/** Language row by code from a list. */
function flxl_t_lang( array $langs, string $code ): array {
	foreach ( $langs as $l ) {
		if ( ( $l['code'] ?? '' ) === $code ) {
			return $l;
		}
	}
	return array();
}

/** A scripted Flux AI: the title/excerpt/SEO step returns the JSON with every value marked, the body keeps its blocks. */
function flxai_ready( $source = '' ) {
	return true;
}
function flxai_chat( array $messages, array $opts = array() ) {
	$last = end( $messages );
	$text = is_array( $last ) ? (string) ( $last['content'] ?? '' ) : '';
	if ( str_contains( $text, 'Return only valid JSON' ) ) {
		$json = json_decode( (string) substr( $text, (int) strpos( $text, "\n" ) + 1 ), true );
		foreach ( (array) $json as $k => $v ) {
			$json[ $k ] = '' === $v ? '' : 'EN ' . $v;
		}
		return array( 'content' => wp_json_encode( $json ) );
	}
	return array( 'content' => str_replace( 'Merhaba', 'Hello', $text ) );
}

$admin = 1;
$sub   = (int) wp_insert_user( array( 'user_login' => 'flxl_sub_' . wp_rand(), 'user_pass' => wp_generate_password(), 'role' => 'subscriber' ) );
$ed    = (int) wp_insert_user( array( 'user_login' => 'flxl_ed_' . wp_rand(), 'user_pass' => wp_generate_password(), 'role' => 'editor' ) );

// Start from the defaults: Turkish (default) + English, pages and posts translated.
delete_option( 'flxl_settings' );
flxl_settings( true );
flxl_index_flush();

// Public languages.
$r = flxl_t_op( 'FlxLanguages' );
flxt_same( null, $r['errors'] ?? null, 'languages: visitor can read the list' );
flxt_same( array( 'tr', 'en' ), array_column( $r['data']['flxLanguages'] ?? array(), 'code' ), 'languages: default first, then English' );
$tr = flxl_t_lang( $r['data']['flxLanguages'] ?? array(), 'tr' );
flxt_same( array( true, true, '/', 'tr_TR' ), array( $tr['default'] ?? null, $tr['active'] ?? null, $tr['address'] ?? null, $tr['locale'] ?? null ), 'languages: Turkish is the default, address /' );
flxt_same( '/en/', flxl_t_lang( $r['data']['flxLanguages'] ?? array(), 'en' )['address'] ?? null, 'languages: English address /en/' );

// Settings: permissions and shape.
$r = flxl_t_op( 'FlxLanguageSettingsScreen' );
flxt_same( 'UNAUTHENTICATED', flxt_code( $r ), 'settings: visitor → UNAUTHENTICATED' );
$r = flxl_t_op( 'FlxLanguageSettingsScreen', array(), $sub );
flxt_same( 'FORBIDDEN', flxt_code( $r ), 'settings: subscriber → FORBIDDEN' );
$r = flxl_t_op( 'FlxLanguageSettingsScreen', array(), $ed );
flxt_same( 'FORBIDDEN', flxt_code( $r ), 'settings: editor → FORBIDDEN' );
$r = flxl_t_op( 'FlxLanguageSettingsScreen', array(), $admin );
flxt_same( null, $r['errors'] ?? null, 'settings: administrator reads them' );
$s = $r['data']['flxLanguageSettings'] ?? array();
flxt_same( 'tr', $s['defaultLanguage'] ?? null, 'settings: default language' );
flxt_same( array( 'REDIRECT', 'en', 'CODE', 'FOOTER', true ), array( $s['suggest'] ?? null, $s['suggestTo'] ?? null, $s['switcher'] ?? null, $s['switcherPlace'] ?? null, $s['fallback'] ?? null ), 'settings: classic defaults' );
flxt_same( array( 'TR', 'CY' ), $s['homeCountries'] ?? null, 'settings: home countries' );
flxt_same( array( 'post', 'page' ), array_slice( $s['postTypes'] ?? array(), 0, 2 ), 'settings: translated post types' );
flxt_ok( ! in_array( 'tr', array_column( $s['catalog'] ?? array(), 'code' ), true ) && in_array( 'de', array_column( $s['catalog'] ?? array(), 'code' ), true ), 'settings: catalog lists only languages that are not added' );
flxt_ok( in_array( 'blogname', array_column( $s['textFields'] ?? array(), 'key' ), true ), 'settings: site title is a translatable text' );
flxt_ok( in_array( 'page', array_column( $s['postTypeChoices'] ?? array(), 'name' ), true ) && ! in_array( 'attachment', array_column( $s['postTypeChoices'] ?? array(), 'name' ), true ), 'settings: post type choices skip media' );
flxt_same( array( null, null ), array( flxl_t_at( $s, 'geo', 'country' ), flxl_t_at( $s, 'geo', 'source' ) ), 'settings: no country header on this server' );
flxt_same( true, $s['aiAvailable'] ?? null, 'settings: AI available (scripted Flux AI)' );

// Save: add a language, change options, site texts.
$r = flxl_t_op( 'FlxLanguageSettingsUpdate', array( 'input' => array( 'addLanguage' => 'de', 'fallback' => false, 'suggest' => 'BANNER', 'switcher' => 'NAME', 'switcherPlace' => 'BOTH', 'homeCountries' => array( 'tr', 'cy', 'de' ) ) ), $admin );
$p = $r['data']['flxLanguageSettingsUpdate'] ?? array();
flxt_same( array(), $p['errors'] ?? null, 'save: no errors' );
$s = $p['settings'] ?? array();
flxt_same( array( 'tr', 'en', 'de' ), array_column( $s['languages'] ?? array(), 'code' ), 'save: German added' );
flxt_same( false, flxl_t_lang( $s['languages'] ?? array(), 'de' )['active'] ?? null, 'save: a new language starts inactive' );
flxt_same( array( false, 'BANNER', 'NAME', 'BOTH', array( 'TR', 'CY', 'DE' ) ), array( $s['fallback'] ?? null, $s['suggest'] ?? null, $s['switcher'] ?? null, $s['switcherPlace'] ?? null, $s['homeCountries'] ?? null ), 'save: options and upper-cased countries' );
flxt_same( 'tr', $s['defaultLanguage'] ?? null, 'save: fields that are not sent stay (default language)' );
flxt_same( array( 'post', 'page' ), array_slice( $s['postTypes'] ?? array(), 0, 2 ), 'save: post types stay when not sent' );
flxt_same( array( 'tr', 'en' ), array_column( flxl_t_op( 'FlxLanguages' )['data']['flxLanguages'] ?? array(), 'code' ), 'save: the public list shows active languages only' );

$r = flxl_t_op(
	'FlxLanguageSettingsUpdate',
	array(
		'input' => array(
			'languages' => array(
				array( 'code' => 'tr', 'name' => 'Türkçe', 'locale' => 'tr_TR', 'flag' => '🇹🇷', 'active' => true ),
				array( 'code' => 'en', 'name' => 'English', 'locale' => 'en_US', 'flag' => '🇬🇧', 'active' => true ),
				array( 'code' => 'de', 'name' => 'Deutsch', 'locale' => 'de_DE', 'flag' => '🇩🇪', 'active' => true ),
			),
			'texts'     => array(
				array( 'lang' => 'en', 'key' => 'blogname', 'value' => 'Flux Test Site' ),
				array( 'lang' => 'de', 'key' => 'blogname', 'value' => '' ),
			),
		),
	),
	$admin
);
$p = $r['data']['flxLanguageSettingsUpdate'] ?? array();
flxt_same( array(), $p['errors'] ?? null, 'save: languages and texts' );
flxt_same( array( 'tr', 'en', 'de' ), array_column( $p['settings']['languages'] ?? array(), 'code' ), 'save: languages in the order sent' );
flxt_same( true, flxl_t_lang( $p['settings']['languages'] ?? array(), 'de' )['active'] ?? null, 'save: German activated' );
flxt_same( array( array( 'lang' => 'en', 'key' => 'blogname', 'value' => 'Flux Test Site' ) ), $p['settings']['texts'] ?? null, 'save: empty text is not stored' );
flxt_same( 'Flux Test Site', flxl_text( 'blogname', 'en' ), 'save: the site title translation is used by Flux Lang' );

// Remove German by leaving it out; the texts of other languages stay.
$r = flxl_t_op( 'FlxLanguageSettingsUpdate', array( 'input' => array( 'languages' => array( array( 'code' => 'tr', 'name' => 'Türkçe', 'locale' => 'tr_TR', 'active' => true ), array( 'code' => 'en', 'name' => 'English', 'locale' => 'en_US', 'active' => true ) ) ) ), $admin );
flxt_same( array( 'tr', 'en' ), array_column( $r['data']['flxLanguageSettingsUpdate']['settings']['languages'] ?? array(), 'code' ), 'save: a language that is not listed is removed' );
flxt_same( 'Flux Test Site', $r['data']['flxLanguageSettingsUpdate']['settings']['texts'][0]['value'] ?? null, 'save: texts stay when not sent' );

// Invalid input: each problem with its field, nothing written.
$before = get_option( 'flxl_settings' );
$r      = flxl_t_op(
	'FlxLanguageSettingsUpdate',
	array(
		'input' => array(
			'languages'     => array( array( 'code' => 'tr' ), array( 'code' => 'EN!' ), array( 'code' => 'tr' ), array( 'code' => 'fr', 'locale' => 'fr FR' ) ),
			'suggestTo'     => 'zz',
			'homeCountries' => array( 'TR', 'Turkey' ),
			'postTypes'     => array( 'no_such_type' ),
			'texts'         => array( array( 'lang' => 'tr', 'key' => 'blogname', 'value' => 'x' ), array( 'lang' => 'fr', 'key' => 'nope', 'value' => 'x' ) ),
		),
	),
	$admin
);
$p      = $r['data']['flxLanguageSettingsUpdate'] ?? array();
$fields = array_column( $p['errors'] ?? array(), 'field' );
flxt_same( null, flxl_t_at( $p, 'settings' ), 'invalid: no settings returned' );
foreach ( array( 'languages.1.code', 'languages.2.code', 'languages.3.locale', 'suggestTo', 'homeCountries.1', 'postTypes.0', 'texts.0.lang', 'texts.1.key' ) as $f ) {
	flxt_ok( in_array( $f, $fields, true ), 'invalid: error on ' . $f, $fields );
}
flxt_same( array( 'INVALID_INPUT' ), array_values( array_unique( array_column( $p['errors'] ?? array(), 'code' ) ) ), 'invalid: code INVALID_INPUT' );
flxt_same( $before, get_option( 'flxl_settings' ), 'invalid: nothing was written' );
$r = flxl_t_op( 'FlxLanguageSettingsUpdate', array( 'input' => array( 'languages' => array( array( 'code' => 'en' ) ) ) ), $admin );
flxt_ok( in_array( 'languages', array_column( $r['data']['flxLanguageSettingsUpdate']['errors'] ?? array(), 'field' ), true ), 'invalid: the default language cannot be removed' );
$r = flxl_t_op( 'FlxLanguageSettingsUpdate', array( 'input' => array( 'fallback' => false ) ), $sub );
flxt_same( 'FORBIDDEN', flxt_code( $r ), 'save: subscriber → FORBIDDEN' );
$r = flxl_t_op( 'FlxLanguageSettingsUpdate', array( 'input' => array( 'fallback' => false ) ) );
flxt_same( 'UNAUTHENTICATED', flxt_code( $r ), 'save: visitor → UNAUTHENTICATED' );

// Classic form: same service, bad values are dropped quietly (no error), the rest is saved.
$s = FLXL_Language_Service::save_settings(
	array(
		'languages'      => array( array( 'code' => 'tr', 'name' => 'Türkçe', 'locale' => 'tr_TR', 'active' => true ), array( 'code' => 'EN!', 'name' => 'x' ), array( 'code' => 'en', 'name' => 'English', 'locale' => 'en_US', 'active' => true ) ),
		'default'        => 'tr',
		'post_types'     => array( 'post', 'page', 'no_such_type' ),
		'fallback'       => true,
		'suggest'        => 'nonsense',
		'suggest_to'     => 'zz',
		'home_countries' => array( 'tr', 'Turkey' ),
		'texts'          => array(),
	),
	false
);
flxt_ok( $s instanceof FLXL_Settings, 'classic: bad values do not fail the save' );
flxt_same( array( 'tr', 'en' ), $s instanceof FLXL_Settings ? array_map( static fn( $l ) => $l->code, $s->languages ) : null, 'classic: invalid code skipped' );
flxt_same( array( 'redirect', 'en', array( 'TR' ), array( 'post', 'page' ), array() ), $s instanceof FLXL_Settings ? array( $s->suggest, $s->suggest_to, $s->home_countries, $s->post_types, $s->texts ) : null, 'classic: unknown mode → redirect, unknown language → en, bad country dropped, texts replaced' );

// Content fields: flxLanguage and flxTranslations.
$about = (int) wp_insert_post( array( 'post_type' => 'page', 'post_title' => 'Hakkımızda', 'post_content' => "<!-- wp:paragraph -->\n<p>Merhaba dünya</p>\n<!-- /wp:paragraph -->", 'post_status' => 'publish' ) );
$post  = (int) wp_insert_post( array( 'post_type' => 'post', 'post_title' => 'Haber', 'post_status' => 'publish' ) );
$r     = flxl_t_op( 'FlxContentLanguage', array( 'id' => (string) $about ) );
flxt_same( null, $r['errors'] ?? null, 'content: visitor reads a published page' );
flxt_same( 'tr', $r['data']['contentNode']['flxLanguage'] ?? null, 'content: language of a page without a term is the default language' );
flxt_same( array( 'tr' ), array_column( $r['data']['contentNode']['flxTranslations'] ?? array(), 'lang' ), 'content: only itself before a translation exists' );

// Create the translation by hand (draft copy): the visitor does not see it, editors do.
$r = flxl_t_op( 'FlxLanguageTranslationCreate', array( 'input' => array( 'sourceId' => (string) $about, 'lang' => 'en' ) ), $sub );
flxt_same( 'FORBIDDEN', flxt_code( $r ), 'create: subscriber → FORBIDDEN' );
$r = flxl_t_op( 'FlxLanguageTranslationCreate', array( 'input' => array( 'sourceId' => \GraphQLRelay\Relay::toGlobalId( 'post', (string) $about ), 'lang' => 'en' ) ), $admin );
$t = $r['data']['flxLanguageTranslationCreate'] ?? array();
flxt_same( array(), $t['errors'] ?? null, 'create: global ID accepted, no errors' );
flxt_same( array( 'en', 'draft' ), array( $t['translation']['lang'] ?? null, $t['translation']['status'] ?? null ), 'create: a draft English copy' );
$en = (int) ( $t['translation']['databaseId'] ?? 0 );
flxt_ok( $en > 0 && $en !== $about, 'create: new post' );
flxt_same( 'en', flxl_post_language( $en ), 'create: the copy is English' );
flxt_same( null, flxl_t_at( $t, 'translation', 'url' ), 'create: a draft has no public address' );
flxt_ok( str_contains( (string) ( $t['translation']['editUrl'] ?? '' ), 'post.php' ), 'create: editor address for the administrator' );
$r = flxl_t_op( 'FlxLanguageTranslationCreate', array( 'input' => array( 'sourceId' => (string) $about, 'lang' => 'en' ) ), $admin );
flxt_same( $en, $r['data']['flxLanguageTranslationCreate']['translation']['databaseId'] ?? null, 'create: the existing version is returned, not a second one' );
$r = flxl_t_op( 'FlxLanguageTranslationCreate', array( 'input' => array( 'sourceId' => (string) $about, 'lang' => 'tr' ) ), $admin );
flxt_same( 'INVALID_INPUT', $r['data']['flxLanguageTranslationCreate']['errors'][0]['code'] ?? null, 'create: same language → INVALID_INPUT in the payload' );
$r = flxl_t_op( 'FlxLanguageTranslationCreate', array( 'input' => array( 'sourceId' => (string) $about, 'lang' => 'xx' ) ), $admin );
flxt_same( 'lang', $r['data']['flxLanguageTranslationCreate']['errors'][0]['field'] ?? null, 'create: unknown language → error on lang' );

$r = flxl_t_op( 'FlxContentLanguage', array( 'id' => (string) $about ), $admin );
flxt_same( array( 'tr', 'en' ), array_column( $r['data']['contentNode']['flxTranslations'] ?? array(), 'lang' ), 'content: the administrator sees the draft version' );
flxt_same( array( true, false ), array_column( $r['data']['contentNode']['flxTranslations'] ?? array(), 'isSelf' ), 'content: isSelf marks the content itself' );
$r = flxl_t_op( 'FlxContentLanguage', array( 'id' => (string) $about ) );
flxt_same( array( 'tr' ), array_column( $r['data']['contentNode']['flxTranslations'] ?? array(), 'lang' ), 'content: the visitor does not see the draft version' );
wp_update_post( array( 'ID' => $en, 'post_status' => 'publish' ) );
$r  = flxl_t_op( 'FlxContentLanguage', array( 'id' => (string) $about ) );
$tl = $r['data']['contentNode']['flxTranslations'] ?? array();
flxt_same( array( 'tr', 'en' ), array_column( $tl, 'lang' ), 'content: a published version shows to the visitor' );
flxt_ok( str_contains( (string) ( $tl[1]['url'] ?? '' ), '/en/' ), 'content: public address carries the language prefix', $tl[1]['url'] ?? null );
flxt_same( null, flxl_t_at( $tl[1] ?? array(), 'editUrl' ), 'content: no editor address for the visitor' );
$r = flxl_t_op( 'FlxContentLanguage', array( 'id' => (string) $en ) );
flxt_same( array( 'en', array( 'tr', 'en' ) ), array( $r['data']['contentNode']['flxLanguage'] ?? null, array_column( $r['data']['contentNode']['flxTranslations'] ?? array(), 'lang' ) ), 'content: the translation lists the same group' );

// The field is on every content type; a type that is not translated has no language.
$r = flxt_gql( '{ posts { nodes { databaseId flxLanguage flxTranslations { lang } } } pages { nodes { databaseId flxLanguage } } }', array(), $admin );
flxt_same( null, $r['errors'] ?? null, 'content: Post and Page both have the fields' );
FLXL_Language_Service::save_settings( array( 'post_types' => array( 'page' ) ) );
$r = flxt_gql( '{ posts { nodes { databaseId flxLanguage flxTranslations { lang } } } }', array(), $admin );
flxt_same( array( null, array() ), array( flxl_t_at( $r, 'data', 'posts', 'nodes', '0', 'flxLanguage' ), flxl_t_at( $r, 'data', 'posts', 'nodes', '0', 'flxTranslations' ) ), 'content: a type that is not translated → null language, no versions' );
FLXL_Language_Service::save_settings( array( 'post_types' => array( 'post', 'page' ) ) );

// Set the language by hand.
$r = flxl_t_op( 'FlxLanguageSet', array( 'input' => array( 'id' => (string) $post, 'lang' => 'en' ) ), $sub );
flxt_same( 'FORBIDDEN', flxt_code( $r ), 'set: subscriber → FORBIDDEN' );
$r = flxl_t_op( 'FlxLanguageSet', array( 'input' => array( 'id' => (string) $post, 'lang' => 'en' ) ), $admin );
flxt_same( array( 'en', array() ), array( $r['data']['flxLanguageSet']['lang'] ?? null, $r['data']['flxLanguageSet']['errors'] ?? null ), 'set: a post moves to English' );
$r = flxl_t_op( 'FlxLanguageSet', array( 'input' => array( 'id' => (string) $about, 'lang' => 'en' ) ), $admin );
flxt_same( 'en', flxl_post_language( $about ), 'set: the page takes English although its group had an English version' );
flxt_same( 'en', flxl_post_language( $en ), 'set: the other version keeps English' );
$r = flxl_t_op( 'FlxLanguageSet', array( 'input' => array( 'id' => (string) $about, 'lang' => 'zz' ) ), $admin );
flxt_same( 'lang', $r['data']['flxLanguageSet']['errors'][0]['field'] ?? null, 'set: unknown language → error on lang' );
flxl_set_post_language( $about, 'tr' );
flxl_link_translations( array( 'tr' => $about, 'en' => $en ) );
flxl_set_post_language( $en, 'en' );

// Visitor country: never cached.
unset( $_SERVER['HTTP_CF_IPCOUNTRY'] );
$r = flxl_t_op( 'FlxVisitorCountry' );
flxt_same( null, flxl_t_at( $r, 'data', 'flxVisitorCountry', 'country' ), 'country: null without a country header' );
$_SERVER['HTTP_CF_IPCOUNTRY'] = 'de';
$r = flxl_t_op( 'FlxVisitorCountry' );
flxt_same( 'DE', $r['data']['flxVisitorCountry']['country'] ?? null, 'country: header value' );
$rest = rest_do_request( new WP_REST_Request( 'GET', '/flx-lang/v1/geo' ) );
flxt_same( array( 'country' => 'DE' ), $rest->get_data(), 'country: the REST route returns the same answer' );
$h = apply_filters( 'graphql_response_headers_to_send', array() );
flxt_same( array( 'no-store, private', 'no-cache' ), array( $h['Cache-Control'] ?? null, $h['X-LiteSpeed-Cache-Control'] ?? null ), 'country: the response is marked uncacheable' );
flxt_same( array( 'CF-IPCountry', 'CDN: no-store' ), array( trim( explode( ',', $h['Vary'] ?? '' )[ count( explode( ',', $h['Vary'] ?? '' ) ) - 1 ] ), 'CDN: ' . ( $h['CDN-Cache-Control'] ?? '' ) ), 'country: Vary and CDN headers' );
$seen = get_option( 'flxl_geo_seen' );
flxt_same( 'HTTP_CF_IPCOUNTRY', is_array( $seen ) ? ( $seen['source'] ?? null ) : null, 'country: the header in use is noted for the settings page' );
unset( $_SERVER['HTTP_CF_IPCOUNTRY'] );
$GLOBALS['flxl_gql_visitor_country'] = false;
$h = apply_filters( 'graphql_response_headers_to_send', array() );
flxt_same( false, isset( $h['Cache-Control'] ), 'country: other responses keep the kit cache headers' );

// AI translation: start, steps, job.
$about2 = (int) wp_insert_post( array( 'post_type' => 'page', 'post_title' => 'İletişim', 'post_excerpt' => 'Bize ulaşın', 'post_content' => "<!-- wp:paragraph -->\n<p>Merhaba, bize yazın.</p>\n<!-- /wp:paragraph -->", 'post_status' => 'publish' ) );
$r = flxl_t_op( 'FlxLanguageTranslateStart', array( 'input' => array( 'sourceId' => (string) $about2, 'lang' => 'en' ) ), $sub );
flxt_same( 'FORBIDDEN', flxt_code( $r ), 'translate: subscriber cannot start' );
$r   = flxl_t_op( 'FlxLanguageTranslateStart', array( 'input' => array( 'sourceId' => (string) $about2, 'lang' => 'en' ) ), $admin );
$job = $r['data']['flxLanguageTranslateStart']['job'] ?? array();
flxt_same( array(), $r['data']['flxLanguageTranslateStart']['errors'] ?? null, 'translate: start, no errors' );
flxt_same( array( 'RUNNING', -1, 2, $about2, 'en' ), array( $job['status'] ?? null, $job['step'] ?? null, $job['total'] ?? null, $job['sourceId'] ?? null, $job['lang'] ?? null ), 'translate: running job, title step + one body part' );
$key = (string) ( $job['id'] ?? '' );
flxt_ok( 1 === preg_match( '/^[a-z0-9]{12}$/', $key ), 'translate: job key is lowercase (it travels through sanitize_key)', $key );
flxt_same( 'draft', get_post_status( (int) ( $job['translationId'] ?? 0 ) ), 'translate: the draft translation exists from the start' );
$r = flxl_t_op( 'FlxLanguageJob', array( 'id' => $key ), $sub );
flxt_same( 'NOT_FOUND', flxt_code( $r ), 'job: another user cannot read it' );
$r = flxl_t_op( 'FlxLanguageTranslateStep', array( 'input' => array( 'jobId' => $key, 'step' => 0 ) ), $sub );
flxt_same( 'NOT_FOUND', $r['data']['flxLanguageTranslateStep']['errors'][0]['code'] ?? flxt_code( $r ), 'translate: another user cannot step' );
$r   = flxl_t_op( 'FlxLanguageTranslateStep', array( 'input' => array( 'jobId' => $key, 'step' => 0 ) ), $admin );
$job = $r['data']['flxLanguageTranslateStep']['job'] ?? array();
flxt_same( array( 'RUNNING', 0 ), array( $job['status'] ?? null, $job['step'] ?? null ), 'translate: step 0 done, still running' );
flxt_same( 'EN İletişim', get_post( (int) $job['translationId'] )->post_title, 'translate: step 0 writes the title' );
$r   = flxl_t_op( 'FlxLanguageTranslateStep', array( 'input' => array( 'jobId' => $key, 'step' => 1 ) ), $admin );
$job = $r['data']['flxLanguageTranslateStep']['job'] ?? array();
flxt_same( array( 'DONE', 1, array() ), array( $job['status'] ?? null, $job['step'] ?? null, $job['errors'] ?? null ), 'translate: last step finishes the job' );
flxt_ok( str_contains( get_post( (int) $job['translationId'] )->post_content, 'Hello, bize yazın.' ), 'translate: the body is written' );
$r = flxl_t_op( 'FlxLanguageJob', array( 'id' => $key ), $admin );
flxt_same( 'DONE', $r['data']['flxLanguageJob']['status'] ?? null, 'job: a finished job stays readable' );
$r = flxl_t_op( 'FlxLanguageTranslateStep', array( 'input' => array( 'jobId' => $key, 'step' => 1 ) ), $admin );
flxt_same( 'DONE', $r['data']['flxLanguageTranslateStep']['job']['status'] ?? null, 'translate: stepping a finished job changes nothing' );
$r = flxl_t_op( 'FlxLanguageJob', array( 'id' => 'nope' ), $admin );
flxt_same( 'NOT_FOUND', flxt_code( $r ), 'job: unknown key → NOT_FOUND' );
$r = flxl_t_op( 'FlxLanguageTranslateStart', array( 'input' => array( 'sourceId' => (string) $about2, 'lang' => 'zz' ) ), $admin );
flxt_same( 'lang', $r['data']['flxLanguageTranslateStart']['errors'][0]['field'] ?? null, 'translate: unknown language → error on lang' );

// The service returns the same job the GraphQL fields show.
$about3 = (int) wp_insert_post( array( 'post_type' => 'page', 'post_title' => 'Gizlilik', 'post_content' => "<!-- wp:paragraph -->\n<p>Merhaba</p>\n<!-- /wp:paragraph -->", 'post_status' => 'publish' ) );
wp_set_current_user( $admin );
$started = FLXL_Language_Service::translate_start( $about3, 'en' );
flxt_ok( $started instanceof FLXL_Job, 'translate: service start returns a job' );
if ( $started instanceof FLXL_Job ) {
	FLXL_Language_Service::translate_step( $started->id, 0 );
	$set = get_transient( 'flxl_job_' . $started->id );
	flxt_same( 0, is_array( $set ) ? $set['step'] : null, 'translate: the job remembers its last step' );
}

// HTTP: caching of public reads. The languages list may be cached, the country never.
$base = untrailingslashit( home_url() );
$get  = static function ( string $op, string $extra = '' ) use ( $base ): array {
	$res = wp_remote_get( $base . '/graphql?queryId=' . $GLOBALS['flxl_ids'][ $op ] . $extra, array( 'timeout' => 20, 'reject_unsafe_urls' => false ) );
	return array(
		'code' => (int) wp_remote_retrieve_response_code( $res ),
		'body' => json_decode( (string) wp_remote_retrieve_body( $res ), true ),
		'head' => array_change_key_case( (array) wp_remote_retrieve_headers( $res )->getAll(), CASE_LOWER ),
	);
};
$a = $get( 'FlxLanguages' );
flxt_same( array( 200, array( 'tr', 'en' ) ), array( $a['code'], array_column( $a['body']['data']['flxLanguages'] ?? array(), 'code' ) ), 'http: the languages list is a public persisted GET' );
flxt_ok( str_starts_with( (string) ( $a['head']['cache-control'] ?? '' ), 'public, max-age=' ), 'http: the languages list is cacheable', $a['head']['cache-control'] ?? null );
$c = $get( 'FlxVisitorCountry' );
flxt_same( 200, $c['code'], 'http: the country is a public persisted GET' );
flxt_same( 'no-store, private', $c['head']['cache-control'] ?? null, 'http: the country is never cached' );
flxt_same( 'no-cache', $c['head']['x-litespeed-cache-control'] ?? null, 'http: and LiteSpeed does not store it' );
flxt_ok( ! isset( $c['head']['x-litespeed-tag'] ), 'http: no cache tag on the country answer' );

// Language filter: REST-like. A GraphQL request lists every language unless one is asked for.
flxt_same( true, flxl_uri_is_graphql( '/graphql?queryId=x' ) && flxl_uri_is_graphql( '/blog/graphql' ) && ! flxl_uri_is_graphql( '/graphql-plugin/' ), 'request: the GraphQL address is recognised' );

flxt_done();
