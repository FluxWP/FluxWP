<?php
/**
 * Flux Headless GraphQL schema (Flux GraphQL kit). Loaded on flx_gql_register, i.e. only inside a GraphQL
 * request. Resolvers call the services.
 *
 * Public by design (cap 'public'): flxRoute and flxSite return only what any visitor of the site already
 * sees (published pages, menus, logo, the public captcha site key). Drafts are returned only to people who
 * can edit them (previewId) and never cached. The front end settings and webhook secrets are for
 * administrators; a secret is write-only and never returned.
 *
 * @package FLX_Headless
 */

defined( 'ABSPATH' ) || exit;

/**
 * Typed parent value of a field.
 *
 * @template T of object
 * @param mixed           $source Parent.
 * @param class-string<T> $class  Expected class.
 * @return T
 * @throws FLXGQL_Error When the parent has another type.
 */
function flxhl_gql_src( $source, string $class ) {
	if ( ! $source instanceof $class ) {
		throw flx_gql_error( 'INTERNAL' );
	}
	return $source;
}

/** Optional string argument: null when not given or empty. */
function flxhl_gql_opt( array $args, string $key ): ?string {
	$v = flx_gql_str( $args[ $key ] ?? '' );
	return '' === $v ? null : $v;
}

/**
 * Field map of a DTO: GraphQL name => [type, description, property or callable].
 *
 * @param class-string                                                      $class
 * @param array<string,array{0: string, 1: string, 2: string|callable}> $fields
 * @return array<string,array<string,mixed>>
 */
function flxhl_gql_fields( string $class, array $fields ): array {
	$out = array();
	foreach ( $fields as $name => $f ) {
		$read         = $f[2];
		$out[ $name ] = array(
			'type'        => $f[0],
			'description' => $f[1],
			'resolve'     => static function ( $s ) use ( $class, $read ) {
				$o = flxhl_gql_src( $s, $class );
				return is_string( $read ) ? $o->{$read} : $read( $o );
			},
		);
	}
	return $out;
}

/* ------------------------------------------------------------------ */
/* Enums                                                               */
/* ------------------------------------------------------------------ */

flx_gql_enum(
	'FlxHeadlessRouteKind',
	array(
		'description' => __( 'What an address shows.', 'flx-headless' ),
		'values'      => array(
			'FRONT_PAGE'        => array( 'value' => 'front_page', 'description' => __( 'The home page (a page or the latest posts).', 'flx-headless' ) ),
			'POSTS_PAGE'        => array( 'value' => 'posts_page', 'description' => __( 'The page that lists the latest posts (blog).', 'flx-headless' ) ),
			'SINGULAR'          => array( 'value' => 'singular', 'description' => __( 'One post, page or other content.', 'flx-headless' ) ),
			'TERM'              => array( 'value' => 'term', 'description' => __( 'A category, tag or other term archive.', 'flx-headless' ) ),
			'POST_TYPE_ARCHIVE' => array( 'value' => 'post_type_archive', 'description' => __( 'The archive of a content type (e.g. the shop).', 'flx-headless' ) ),
			'AUTHOR'            => array( 'value' => 'author', 'description' => __( 'An author archive.', 'flx-headless' ) ),
			'DATE'              => array( 'value' => 'date', 'description' => __( 'A date archive.', 'flx-headless' ) ),
			'SEARCH'            => array( 'value' => 'search', 'description' => __( 'Search results (?s=).', 'flx-headless' ) ),
			'REDIRECT'          => array( 'value' => 'redirect', 'description' => __( 'The address moved; send the visitor to redirect with status (301).', 'flx-headless' ) ),
			'NOT_FOUND'         => array( 'value' => 'not_found', 'description' => __( 'Nothing is at this address (404).', 'flx-headless' ) ),
		),
	)
);

flx_gql_enum(
	'FlxHeadlessWpFront',
	array(
		'description' => __( 'What WordPress does with its own theme pages once a front end serves the site.', 'flx-headless' ),
		'values'      => array(
			'KEEP'     => array( 'value' => 'keep', 'description' => __( 'WordPress keeps showing its pages (both sites are open).', 'flx-headless' ) ),
			'NOINDEX'  => array( 'value' => 'noindex', 'description' => __( 'WordPress pages stay open but tell search engines the front end is the real address (noindex + canonical).', 'flx-headless' ) ),
			'REDIRECT' => array( 'value' => 'redirect', 'description' => __( 'Visitors are sent to the same address on the default front end (301). Cart, checkout, account, previews and editors stay on WordPress.', 'flx-headless' ) ),
		),
	)
);

/* ------------------------------------------------------------------ */
/* Settings types                                                      */
/* ------------------------------------------------------------------ */

flx_gql_type(
	'FlxHeadlessFrontend',
	array(
		'description' => __( 'A headless front end this site serves. The webhook secret is never returned.', 'flx-headless' ),
		'fields'      => flxhl_gql_fields(
			FLXHL_Frontend::class,
			array(
				'id'            => array( 'ID!', __( 'Front end id; pass it as the frontend argument of flxRoute and flxSite.', 'flx-headless' ), 'id' ),
				'name'          => array( 'String!', __( 'Name shown in the panel.', 'flx-headless' ), 'name' ),
				'url'           => array( 'String!', __( 'Address of the front end (https://www.example.com), without a trailing slash.', 'flx-headless' ), 'url' ),
				'origin'        => array( 'String!', __( 'Browser origin of the front end; it is allowed to call /graphql (CORS).', 'flx-headless' ), static fn( FLXHL_Frontend $f ): string => $f->origin() ),
				'languages'     => array( '[String!]!', __( 'Language codes the front end serves; empty means every language.', 'flx-headless' ), 'languages' ),
				'enabled'       => array( 'Boolean!', __( 'The front end is in use (CORS, links, webhook).', 'flx-headless' ), 'enabled' ),
				'isDefault'     => array( 'Boolean!', __( 'Used when a request names no front end and comes from no known origin; previews and WordPress redirects go here.', 'flx-headless' ), 'is_default' ),
				'revalidateUrl' => array( 'String', __( 'Webhook address that receives the signed revalidate calls; empty when none.', 'flx-headless' ), static fn( FLXHL_Frontend $f ): ?string => '' === $f->revalidate_url ? null : $f->revalidate_url ),
				'secretSet'     => array( 'Boolean!', __( 'A webhook secret is saved.', 'flx-headless' ), 'secret_set' ),
				'secretHint'    => array( 'String!', __( 'Masked last four characters of the secret; empty when none. The secret itself is never returned.', 'flx-headless' ), 'secret_hint' ),
				'previewPath'   => array( 'String', __( 'Path on the front end that opens previews (e.g. /api/preview); empty when the editor previews on WordPress.', 'flx-headless' ), static fn( FLXHL_Frontend $f ): ?string => '' === $f->preview_path ? null : $f->preview_path ),
			)
		),
	)
);

flx_gql_type(
	'FlxHeadlessDelivery',
	array(
		'description' => __( 'One revalidate webhook call.', 'flx-headless' ),
		'fields'      => flxhl_gql_fields(
			FLXHL_Delivery::class,
			array(
				'time'         => array( 'FlxDateTime!', __( 'When it was sent.', 'flx-headless' ), static fn( FLXHL_Delivery $d ): ?array => flx_gql_datetime( $d->time ) ),
				'frontendId'   => array( 'ID!', __( 'Front end id.', 'flx-headless' ), 'frontend_id' ),
				'frontendName' => array( 'String!', __( 'Front end name.', 'flx-headless' ), 'frontend_name' ),
				'event'        => array( 'String!', __( 'purge (content changed), all (refresh everything) or test.', 'flx-headless' ), 'event' ),
				'tags'         => array( '[String!]!', __( 'Cache tags sent.', 'flx-headless' ), 'tags' ),
				'paths'        => array( '[String!]!', __( 'Page paths sent.', 'flx-headless' ), 'paths' ),
				'status'       => array( 'Int!', __( 'HTTP status the front end answered; 0 when not waited for or not reached.', 'flx-headless' ), 'status' ),
				'ok'           => array( 'Boolean!', __( 'Delivered (2xx) or sent without waiting.', 'flx-headless' ), 'ok' ),
				'message'      => array( 'String!', __( 'Result in the user\'s language.', 'flx-headless' ), 'message' ),
				'ms'           => array( 'Int!', __( 'Duration in milliseconds.', 'flx-headless' ), 'ms' ),
			)
		),
	)
);

/* ------------------------------------------------------------------ */
/* Route types                                                         */
/* ------------------------------------------------------------------ */

flx_gql_type(
	'FlxHeadlessMeta',
	array(
		'description' => __( 'A meta tag of the page head (<meta name|property content>).', 'flx-headless' ),
		'fields'      => flxhl_gql_fields(
			FLXHL_Meta::class,
			array(
				'name'     => array( 'String', __( 'name attribute (description, twitter:card …); empty for property tags.', 'flx-headless' ), static fn( FLXHL_Meta $m ): ?string => '' === $m->name ? null : $m->name ),
				'property' => array( 'String', __( 'property attribute (og:title …); empty for name tags.', 'flx-headless' ), static fn( FLXHL_Meta $m ): ?string => '' === $m->property ? null : $m->property ),
				'content'  => array( 'String!', __( 'content attribute.', 'flx-headless' ), 'content' ),
			)
		),
	)
);

flx_gql_type(
	'FlxHeadlessAlternate',
	array(
		'description' => __( 'A language version of the page (<link rel="alternate" hreflang>).', 'flx-headless' ),
		'fields'      => flxhl_gql_fields(
			FLXHL_Alternate::class,
			array(
				'lang'     => array( 'String!', __( 'Language code.', 'flx-headless' ), 'lang' ),
				'hreflang' => array( 'String!', __( 'hreflang value.', 'flx-headless' ), 'hreflang' ),
				'url'      => array( 'String!', __( 'Full address on the front end.', 'flx-headless' ), 'url' ),
				'path'     => array( 'String!', __( 'Path on the front end.', 'flx-headless' ), 'path' ),
			)
		),
	)
);

flx_gql_type(
	'FlxHeadlessHead',
	array(
		'description' => __( 'What the front end puts in <head>: the same title, description, canonical, robots, Open Graph and JSON-LD the theme prints (Flux SEO), with addresses on the front end.', 'flx-headless' ),
		'fields'      => flxhl_gql_fields(
			FLXHL_Head::class,
			array(
				'title'       => array( 'String!', __( 'Document title.', 'flx-headless' ), 'title' ),
				'description' => array( 'String!', __( 'Meta description; empty when none.', 'flx-headless' ), 'description' ),
				'canonical'   => array( 'String', __( 'Canonical address on the front end; empty for search and 404.', 'flx-headless' ), 'canonical' ),
				'robots'      => array( '[String!]!', __( 'Robots directives (index, follow, max-image-preview:large …).', 'flx-headless' ), 'robots' ),
				'noindex'     => array( 'Boolean!', __( 'Search engines must not index the page.', 'flx-headless' ), 'noindex' ),
				'image'       => array( 'String', __( 'Share image (og:image); empty when none.', 'flx-headless' ), 'image' ),
				'locale'      => array( 'String!', __( 'Language of the page (BCP 47, e.g. tr-TR) for <html lang>.', 'flx-headless' ), 'locale' ),
				'meta'        => array( '[FlxHeadlessMeta!]!', __( 'Open Graph, Twitter, verification and other meta tags.', 'flx-headless' ), 'meta' ),
				'jsonLd'      => array( '[String!]!', __( 'JSON-LD documents, each a JSON string for <script type="application/ld+json">.', 'flx-headless' ), 'json_ld' ),
			)
		),
	)
);

flx_gql_type(
	'FlxHeadlessRoute',
	array(
		'description' => __( 'What an address on the front end shows.', 'flx-headless' ),
		'fields'      => array_merge(
			flxhl_gql_fields(
				FLXHL_Route::class,
				array(
					'path'       => array( 'String!', __( 'The address asked, normalised (language prefix removed).', 'flx-headless' ), 'path' ),
					'kind'       => array( 'FlxHeadlessRouteKind!', __( 'What the address shows.', 'flx-headless' ), 'kind' ),
					'status'     => array( 'Int!', __( 'HTTP status the front end answers with (200, 301, 404).', 'flx-headless' ), 'status' ),
					'redirect'   => array( 'String', __( 'Front-end path to send the visitor to; only for REDIRECT.', 'flx-headless' ), 'redirect' ),
					'lang'       => array( 'String', __( 'Language code of the address; empty without Flux Lang.', 'flx-headless' ), 'lang' ),
					'title'      => array( 'String!', __( 'Title of the content, term, author or archive.', 'flx-headless' ), 'title' ),
					'page'       => array( 'Int!', __( 'Page number of a listing (/page/2/), 1 otherwise.', 'flx-headless' ), 'page' ),
					'databaseId' => array( 'Int', __( 'Database id of the post, term or author; empty for archives, search and 404.', 'flx-headless' ), static fn( FLXHL_Route $r ): ?int => $r->object_id > 0 ? $r->object_id : null ),
					'postType'   => array( 'String', __( 'Content type of a post or a content type archive.', 'flx-headless' ), 'post_type' ),
					'taxonomy'   => array( 'String', __( 'Taxonomy of a term archive.', 'flx-headless' ), 'taxonomy' ),
					'head'       => array( 'FlxHeadlessHead!', __( 'SEO head of the page.', 'flx-headless' ), 'head' ),
					'alternates' => array( '[FlxHeadlessAlternate!]!', __( 'Language versions of the page (hreflang), the page itself included. Empty without Flux Lang.', 'flx-headless' ), 'alternates' ),
					'cacheTags'  => array( '[String!]!', __( 'Tags to cache the page under; the revalidate webhook sends these tags when the page changes.', 'flx-headless' ), 'cache_tags' ),
					'frontendId' => array( 'ID', __( 'Front end the addresses point at; empty when no front end is set up.', 'flx-headless' ), 'frontend_id' ),
				)
			),
			array(
				'node' => array(
					'type'        => 'UniformResourceIdentifiable',
					'description' => __( 'The WordPress object (Post, Page, Category, User, ContentType …) with every field WPGraphQL and the FluxWP packages give it (flxBlocks, flxSeo, flxTranslations …). Empty for search, date archives, redirects and 404.', 'flx-headless' ),
					'resolve'     => static function ( $s, $args, $context ) {
						$r = flxhl_gql_src( $s, FLXHL_Route::class );
						if ( ! $context instanceof \WPGraphQL\AppContext || $r->object_id <= 0 && 'post_type' !== $r->object_type ) {
							return null;
						}
						return match ( $r->object_type ) {
							'post'      => $context->get_loader( 'post' )->load_deferred( $r->object_id ),
							'term'      => $context->get_loader( 'term' )->load_deferred( $r->object_id ),
							'user'      => $context->get_loader( 'user' )->load_deferred( $r->object_id ),
							'post_type' => null === $r->post_type ? null : $context->get_loader( 'post_type' )->load_deferred( $r->post_type ),
							default     => null,
						};
					},
				),
			)
		),
	)
);

/* ------------------------------------------------------------------ */
/* Site types                                                          */
/* ------------------------------------------------------------------ */

flx_gql_type(
	'FlxHeadlessImage',
	array(
		'description' => __( 'An image.', 'flx-headless' ),
		'fields'      => flxhl_gql_fields(
			FLXHL_Image::class,
			array(
				'url'    => array( 'String!', __( 'Address of the file.', 'flx-headless' ), 'url' ),
				'width'  => array( 'Int!', __( 'Width in pixels; 0 when unknown.', 'flx-headless' ), 'width' ),
				'height' => array( 'Int!', __( 'Height in pixels; 0 when unknown.', 'flx-headless' ), 'height' ),
				'alt'    => array( 'String!', __( 'Alternative text.', 'flx-headless' ), 'alt' ),
			)
		),
	)
);

flx_gql_type(
	'FlxHeadlessMenuItem',
	array(
		'description' => __( 'A menu link. The list is flat; a child points at its parent.', 'flx-headless' ),
		'fields'      => flxhl_gql_fields(
			FLXHL_Menu_Item::class,
			array(
				'id'       => array( 'ID!', __( 'Id inside the menu.', 'flx-headless' ), 'id' ),
				'parentId' => array( 'ID', __( 'Id of the parent link; empty at the top level.', 'flx-headless' ), 'parent' ),
				'label'    => array( 'String!', __( 'Text of the link.', 'flx-headless' ), 'label' ),
				'url'      => array( 'String!', __( 'Address; site pages point at the front end.', 'flx-headless' ), 'url' ),
				'path'     => array( 'String', __( 'Front-end path for site pages (use it with the router); empty for other sites.', 'flx-headless' ), 'path' ),
				'target'   => array( 'String', __( 'Link target (_blank) when set.', 'flx-headless' ), 'target' ),
				'external' => array( 'Boolean!', __( 'The link goes to another site.', 'flx-headless' ), 'external' ),
				'order'    => array( 'Int!', __( 'Position in the menu.', 'flx-headless' ), 'order' ),
			)
		),
	)
);

flx_gql_type(
	'FlxHeadlessMenu',
	array(
		'description' => __( 'A menu: a classic menu location, or a navigation block of the header or footer.', 'flx-headless' ),
		'fields'      => flxhl_gql_fields(
			FLXHL_Menu::class,
			array(
				'location' => array( 'String!', __( 'Where it sits: a menu location (primary …) or header, header-2, footer, footer-2 … for navigation blocks.', 'flx-headless' ), 'location' ),
				'name'     => array( 'String!', __( 'Menu name; may be empty for navigation blocks.', 'flx-headless' ), 'name' ),
				'source'   => array( 'String!', __( 'classic (Appearance > Menus) or block (site editor).', 'flx-headless' ), 'source' ),
				'items'    => array( '[FlxHeadlessMenuItem!]!', __( 'Links in order.', 'flx-headless' ), 'items' ),
			)
		),
	)
);

flx_gql_type(
	'FlxHeadlessColor',
	array(
		'description' => __( 'A colour of the theme palette.', 'flx-headless' ),
		'fields'      => flxhl_gql_fields(
			FLXHL_Color::class,
			array(
				'slug'  => array( 'String!', __( 'Slug (CSS variable --wp--preset--color--<slug>).', 'flx-headless' ), 'slug' ),
				'name'  => array( 'String!', __( 'Name.', 'flx-headless' ), 'name' ),
				'color' => array( 'String!', __( 'CSS colour value.', 'flx-headless' ), 'color' ),
			)
		),
	)
);

flx_gql_type(
	'FlxHeadlessLanguage',
	array(
		'description' => __( 'A language of the site.', 'flx-headless' ),
		'fields'      => flxhl_gql_fields(
			FLXHL_Language::class,
			array(
				'code'      => array( 'String!', __( 'Language code (tr, en …).', 'flx-headless' ), 'code' ),
				'locale'    => array( 'String!', __( 'WordPress locale (tr_TR).', 'flx-headless' ), 'locale' ),
				'name'      => array( 'String!', __( 'Name in that language.', 'flx-headless' ), 'name' ),
				'hreflang'  => array( 'String!', __( 'hreflang value.', 'flx-headless' ), 'hreflang' ),
				'path'      => array( 'String!', __( 'Path prefix (/ for the default language, /en/ …).', 'flx-headless' ), 'path' ),
				'url'       => array( 'String!', __( 'Home address of the language on the front end.', 'flx-headless' ), 'url' ),
				'isDefault' => array( 'Boolean!', __( 'The default language (no prefix).', 'flx-headless' ), 'is_default' ),
			)
		),
	)
);

flx_gql_type(
	'FlxHeadlessLegalPage',
	array(
		'description' => __( 'A legal page.', 'flx-headless' ),
		'fields'      => flxhl_gql_fields(
			FLXHL_Legal::class,
			array(
				'kind'  => array( 'String!', __( 'privacy, terms or a kind a package adds.', 'flx-headless' ), 'kind' ),
				'title' => array( 'String!', __( 'Page title.', 'flx-headless' ), 'title' ),
				'path'  => array( 'String!', __( 'Front-end path.', 'flx-headless' ), 'path' ),
				'url'   => array( 'String!', __( 'Address on the front end.', 'flx-headless' ), 'url' ),
			)
		),
	)
);

flx_gql_type(
	'FlxHeadlessCurrency',
	array(
		'description' => __( 'A currency of the site and how prices are written.', 'flx-headless' ),
		'fields'      => flxhl_gql_fields(
			FLXHL_Currency::class,
			array(
				'code'              => array( 'String!', __( 'ISO 4217 code (TRY, USD …).', 'flx-headless' ), 'code' ),
				'symbol'            => array( 'String!', __( 'Symbol.', 'flx-headless' ), 'symbol' ),
				'decimals'          => array( 'Int!', __( 'Decimals.', 'flx-headless' ), 'decimals' ),
				'decimalSeparator'  => array( 'String!', __( 'Decimal separator.', 'flx-headless' ), 'decimal_separator' ),
				'thousandSeparator' => array( 'String!', __( 'Thousands separator.', 'flx-headless' ), 'thousand_separator' ),
				'position'          => array( 'String!', __( 'Symbol position: left, right, left_space or right_space.', 'flx-headless' ), 'position' ),
				'isDefault'         => array( 'Boolean!', __( 'The store currency.', 'flx-headless' ), 'is_default' ),
			)
		),
	)
);

flx_gql_type(
	'FlxHeadlessContact',
	array(
		'description' => __( 'Public contact details of the organisation (Flux SEO, else the Flux Setup profile).', 'flx-headless' ),
		'fields'      => flxhl_gql_fields(
			FLXHL_Contact::class,
			array(
				'email'    => array( 'String!', __( 'E-mail; empty when none.', 'flx-headless' ), 'email' ),
				'phone'    => array( 'String!', __( 'Phone; empty when none.', 'flx-headless' ), 'phone' ),
				'whatsapp' => array( 'String!', __( 'WhatsApp support line; empty when none.', 'flx-headless' ), 'whatsapp' ),
				'address'  => array( 'String!', __( 'Address on one line; empty when none.', 'flx-headless' ), 'address' ),
			)
		),
	)
);

flx_gql_type(
	'FlxHeadlessCaptcha',
	array(
		'description' => __( 'Public captcha settings (Flux reCAPTCHA); the site key is public by design. Use flxCaptcha(context:) for one form.', 'flx-headless' ),
		'fields'      => flxhl_gql_fields(
			FLXHL_Captcha::class,
			array(
				'provider'  => array( 'String!', __( 'recaptcha.', 'flx-headless' ), 'provider' ),
				'version'   => array( 'String!', __( 'v2 or v3.', 'flx-headless' ), 'version' ),
				'siteKey'   => array( 'String!', __( 'Site key.', 'flx-headless' ), 'site_key' ),
				'scriptUrl' => array( 'String!', __( 'Script to load.', 'flx-headless' ), 'script_url' ),
			)
		),
	)
);

flx_gql_type(
	'FlxHeadlessPart',
	array(
		'description' => __( 'A rendered template part of the theme.', 'flx-headless' ),
		'fields'      => flxhl_gql_fields(
			FLXHL_Part::class,
			array(
				'name' => array( 'String!', __( 'Part slug (header, footer …).', 'flx-headless' ), 'name' ),
				'html' => array( 'String!', __( 'HTML; page links point at the front end. Needs the theme CSS (flxBlocksSiteStyles).', 'flx-headless' ), 'html' ),
			)
		),
	)
);

flx_gql_type(
	'FlxHeadlessSite',
	array(
		'description' => __( 'The frame a front end draws every page in.', 'flx-headless' ),
		'fields'      => array_merge(
			flxhl_gql_fields(
				FLXHL_Site::class,
				array(
					'name'        => array( 'String!', __( 'Site title.', 'flx-headless' ), 'name' ),
					'description' => array( 'String!', __( 'Tagline.', 'flx-headless' ), 'description' ),
					'url'         => array( 'String!', __( 'Address of the front end (or of WordPress when none is set up).', 'flx-headless' ), 'url' ),
					'cmsUrl'      => array( 'String!', __( 'Address of this WordPress.', 'flx-headless' ), 'cms_url' ),
					'graphqlUrl'  => array( 'String!', __( 'GraphQL endpoint.', 'flx-headless' ), 'graphql_url' ),
					'language'    => array( 'String!', __( 'Language code of this answer.', 'flx-headless' ), 'language' ),
					'locale'      => array( 'String!', __( 'Locale of this answer (BCP 47).', 'flx-headless' ), 'locale' ),
					'languages'   => array( '[FlxHeadlessLanguage!]!', __( 'Languages the front end serves, default first.', 'flx-headless' ), 'languages' ),
					'logo'        => array( 'FlxHeadlessImage', __( 'Site logo; empty when none.', 'flx-headless' ), 'logo' ),
					'icon'        => array( 'String', __( 'Site icon (512 px); empty when none.', 'flx-headless' ), 'icon' ),
					'menus'       => array( '[FlxHeadlessMenu!]!', __( 'Menus of the header, footer and menu locations.', 'flx-headless' ), 'menus' ),
					'palette'     => array( '[FlxHeadlessColor!]!', __( 'Theme palette.', 'flx-headless' ), 'palette' ),
					'contact'     => array( 'FlxHeadlessContact!', __( 'Contact details.', 'flx-headless' ), 'contact' ),
					'currencies'  => array( '[FlxHeadlessCurrency!]!', __( 'Currencies, default first; empty without WooCommerce.', 'flx-headless' ), 'currencies' ),
					'legalPages'  => array( '[FlxHeadlessLegalPage!]!', __( 'Privacy policy, terms and other legal pages.', 'flx-headless' ), 'legal' ),
					'captcha'     => array( 'FlxHeadlessCaptcha', __( 'Captcha settings; empty when Flux reCAPTCHA is off.', 'flx-headless' ), 'captcha' ),
					'social'      => array( '[String!]!', __( 'Social profile addresses.', 'flx-headless' ), 'social' ),
					'sitemapUrl'  => array( 'String!', __( 'Sitemap with front-end addresses; serve it at /sitemap.xml (proxy).', 'flx-headless' ), 'sitemap_url' ),
					'searchPath'  => array( 'String!', __( 'Search address to append the words to.', 'flx-headless' ), 'search_path' ),
					'cacheTags'   => array( '[String!]!', __( 'Tags to cache the frame under.', 'flx-headless' ), 'cache_tags' ),
					'frontendId'  => array( 'ID', __( 'Front end the addresses point at; empty when none is set up.', 'flx-headless' ), 'frontend_id' ),
				)
			),
			array(
				'parts' => array(
					'type'        => '[FlxHeadlessPart!]!',
					'description' => __( 'Rendered template parts, for front ends that reuse the theme markup (at most 5).', 'flx-headless' ),
					'args'        => array(
						'names' => array(
							'type'        => '[String!]!',
							'description' => __( 'Part slugs, e.g. ["header", "footer"].', 'flx-headless' ),
						),
					),
					'resolve'     => static function ( $s, array $args ): array {
						$site  = flxhl_gql_src( $s, FLXHL_Site::class );
						$names = array_values( array_map( 'flx_gql_str', flx_gql_arr( $args['names'] ?? array() ) ) );
						return FLXHL_Site_Service::parts( $names, $site->language, $site->frontend_id );
					},
				),
			)
		),
	)
);

/* ------------------------------------------------------------------ */
/* Settings screen type                                                */
/* ------------------------------------------------------------------ */

flx_gql_type(
	'FlxHeadlessSettings',
	array(
		'description' => __( 'The Headless screen: front ends, what WordPress does with its own pages, CORS and the webhook log.', 'flx-headless' ),
		'fields'      => array_merge(
			flxhl_gql_fields(
				FLXHL_Settings::class,
				array(
					'frontends'          => array( '[FlxHeadlessFrontend!]!', __( 'Front ends.', 'flx-headless' ), 'frontends' ),
					'wpFront'            => array( 'FlxHeadlessWpFront!', __( 'What WordPress does with its own pages.', 'flx-headless' ), 'wp_front' ),
					'corsOrigins'        => array( '[String!]!', __( 'Every origin allowed to call /graphql from a browser: this site, the Flux GraphQL list and the front ends.', 'flx-headless' ), 'cors' ),
					'graphqlUrl'         => array( 'String!', __( 'GraphQL endpoint to give the front end.', 'flx-headless' ), 'graphql_url' ),
					'graphqlSettingsUrl' => array( 'String!', __( 'Flux GraphQL settings (CORS list, query mode, cache).', 'flx-headless' ), 'graphql_settings_url' ),
					'persistedOnly'      => array( 'Boolean!', __( 'Only registered (persisted) queries are allowed; front ends send flxRoute / flxSite by id.', 'flx-headless' ), 'persisted_only' ),
					'languagesActive'    => array( 'Boolean!', __( 'Flux Lang is active; front ends can be limited to languages.', 'flx-headless' ), 'lang_active' ),
					'deliveries'         => array( '[FlxHeadlessDelivery!]!', __( 'Last webhook calls, newest first (at most 30).', 'flx-headless' ), 'deliveries' ),
				)
			),
			array(
				'languages' => array(
					'type'        => '[FlxHeadlessLanguage!]!',
					'description' => __( 'Languages of the site for the language picker.', 'flx-headless' ),
					'resolve'     => static function (): array {
						$langs = array();
						if ( class_exists( 'FLXL_Language_Service' ) ) {
							foreach ( FLXL_Language_Service::languages() as $l ) {
								if ( $l->active ) {
									$langs[] = new FLXHL_Language( $l->code, $l->locale, $l->name, $l->hreflang, $l->address, flxhl_home() . $l->address, $l->default );
								}
							}
						}
						return $langs;
					},
				),
			)
		),
	)
);

/* ------------------------------------------------------------------ */
/* Queries                                                             */
/* ------------------------------------------------------------------ */

flx_gql_query(
	'flxRoute',
	array(
		'type'        => 'FlxHeadlessRoute!',
		'description' => __( 'What an address on the front end shows: kind, HTTP status, redirect, language, the WordPress object (node), SEO head, language versions and cache tags. Public; drafts only with previewId for people who can edit them.', 'flx-headless' ),
		'cap'         => 'public',
		'args'        => array(
			'path'      => array(
				'type'        => 'String!',
				'description' => __( 'Front-end path with the query (/about/, /en/blog/page/2/, /?s=word) or a full address of the front end.', 'flx-headless' ),
			),
			'lang'      => array(
				'type'        => 'String',
				'description' => __( 'Language when the path has no language prefix (a front end per language or domain).', 'flx-headless' ),
			),
			'frontend'  => array(
				'type'        => 'ID',
				'description' => __( 'Front end id; without it the caller\'s origin, else the default front end.', 'flx-headless' ),
			),
			'previewId' => array(
				'type'        => 'Int',
				'description' => __( 'Draft or scheduled post to preview. Needs a signed-in user who can edit it (application password); the answer is never cached.', 'flx-headless' ),
			),
		),
		'resolve'     => static function ( $root, array $args ): FLXHL_Route {
			$preview = flx_gql_int( $args['previewId'] ?? 0 );
			if ( $preview > 0 ) {
				flx_gql_no_store();
			}
			$r = FLXHL_Route_Service::resolve( flx_gql_str( $args['path'] ?? '/' ), flxhl_gql_opt( $args, 'lang' ), flxhl_gql_opt( $args, 'frontend' ), $preview );
			flx_gql_cache_tag( array_merge( $r->cache_tags, array( FLXHL_Frontend_Service::CACHE_TAG ) ) );
			return $r;
		},
	)
);

flx_gql_query(
	'flxSite',
	array(
		'type'        => 'FlxHeadlessSite!',
		'description' => __( 'The site frame for a front end: name, logo, languages, menus, palette, contact details, currencies, legal pages and public captcha settings. Public.', 'flx-headless' ),
		'cap'         => 'public',
		'args'        => array(
			'lang'     => array(
				'type'        => 'String',
				'description' => __( 'Language of the menus and texts; default language when empty.', 'flx-headless' ),
			),
			'frontend' => array(
				'type'        => 'ID',
				'description' => __( 'Front end id; without it the caller\'s origin, else the default front end.', 'flx-headless' ),
			),
		),
		'resolve'     => static function ( $root, array $args ): FLXHL_Site {
			$s = FLXHL_Site_Service::site( flxhl_gql_opt( $args, 'lang' ), flxhl_gql_opt( $args, 'frontend' ) );
			flx_gql_cache_tag( $s->cache_tags );
			return $s;
		},
	)
);

flx_gql_query(
	'flxHeadlessSettings',
	array(
		'type'        => 'FlxHeadlessSettings!',
		'description' => __( 'The Headless screen. Webhook secrets are never returned.', 'flx-headless' ),
		'cap'         => 'manage_options',
		'resolve'     => static fn(): FLXHL_Settings => FLXHL_Frontend_Service::settings(),
	)
);

/* ------------------------------------------------------------------ */
/* Mutations                                                           */
/* ------------------------------------------------------------------ */

$flxhl_settings_out = array(
	'settings' => array(
		'type'        => 'FlxHeadlessSettings!',
		'description' => __( 'Settings after the change.', 'flx-headless' ),
		'resolve'     => static fn(): FLXHL_Settings => FLXHL_Frontend_Service::settings(),
	),
);

flx_gql_mutation(
	'flxHeadlessFrontendSave',
	array(
		'description' => __( 'Adds a front end (no id) or changes one. Fields you leave out stay as they are. The secret is write-only: empty keeps the saved one, "__clear__" removes it.', 'flx-headless' ),
		'cap'         => 'manage_options',
		'input'       => array(
			'id'            => array( 'type' => 'ID', 'description' => __( 'Front end to change; empty adds a new one.', 'flx-headless' ) ),
			'name'          => array( 'type' => 'String', 'description' => __( 'Name.', 'flx-headless' ) ),
			'url'           => array( 'type' => 'String', 'description' => __( 'Address with https:// (http:// only for localhost).', 'flx-headless' ) ),
			'languages'     => array( 'type' => '[String!]', 'description' => __( 'Language codes it serves; empty list = every language.', 'flx-headless' ) ),
			'enabled'       => array( 'type' => 'Boolean', 'description' => __( 'In use.', 'flx-headless' ) ),
			'isDefault'     => array( 'type' => 'Boolean', 'description' => __( 'Default front end (only one).', 'flx-headless' ) ),
			'revalidateUrl' => array( 'type' => 'String', 'description' => __( 'Webhook address; empty turns the webhook off.', 'flx-headless' ) ),
			'secret'        => array( 'type' => 'String', 'description' => __( 'Webhook secret (at least 24 characters); write-only, stored sealed.', 'flx-headless' ) ),
			'previewPath'   => array( 'type' => 'String', 'description' => __( 'Preview path on the front end (e.g. /api/preview); empty keeps previews on WordPress.', 'flx-headless' ) ),
		),
		'output'      => array_merge(
			array(
				'frontend' => array(
					'type'        => 'FlxHeadlessFrontend',
					'description' => __( 'The saved front end.', 'flx-headless' ),
				),
			),
			$flxhl_settings_out
		),
		'resolve'     => static function ( array $input ) {
			$in = array();
			foreach ( array( 'id' => 'id', 'name' => 'name', 'url' => 'url', 'revalidateUrl' => 'revalidate_url', 'secret' => 'secret', 'previewPath' => 'preview_path' ) as $gql => $key ) {
				if ( array_key_exists( $gql, $input ) && null !== $input[ $gql ] ) {
					$in[ $key ] = flx_gql_str( $input[ $gql ] );
				}
			}
			foreach ( array( 'enabled' => 'enabled', 'isDefault' => 'default' ) as $gql => $key ) {
				if ( isset( $input[ $gql ] ) ) {
					$in[ $key ] = (bool) $input[ $gql ];
				}
			}
			if ( isset( $input['languages'] ) && is_array( $input['languages'] ) ) {
				$in['languages'] = array_values( array_map( 'flx_gql_str', $input['languages'] ) );
			}
			/** @var array{id?: string, name?: string, url?: string, languages?: list<string>, enabled?: bool, default?: bool, revalidate_url?: string, secret?: string, preview_path?: string} $in */
			$r = FLXHL_Frontend_Service::save( $in );
			return $r instanceof WP_Error ? $r : array( 'frontend' => $r );
		},
	)
);

flx_gql_mutation(
	'flxHeadlessFrontendDelete',
	array(
		'description' => __( 'Removes a front end with its secret.', 'flx-headless' ),
		'cap'         => 'manage_options',
		'input'       => array(
			'id' => array( 'type' => 'ID!', 'description' => __( 'Front end id.', 'flx-headless' ) ),
		),
		'output'      => $flxhl_settings_out,
		'resolve'     => static function ( array $input ) {
			$r = FLXHL_Frontend_Service::delete( flx_gql_str( $input['id'] ?? '' ) );
			return $r instanceof WP_Error ? $r : array();
		},
	)
);

flx_gql_mutation(
	'flxHeadlessWpFrontSet',
	array(
		'description' => __( 'Sets what WordPress does with its own pages once a front end serves the site.', 'flx-headless' ),
		'cap'         => 'manage_options',
		'input'       => array(
			'mode' => array( 'type' => 'FlxHeadlessWpFront!', 'description' => __( 'Mode.', 'flx-headless' ) ),
		),
		'output'      => $flxhl_settings_out,
		'resolve'     => static function ( array $input ) {
			$r = FLXHL_Frontend_Service::set_wp_front( flx_gql_str( $input['mode'] ?? '' ) );
			return $r instanceof WP_Error ? $r : array();
		},
	)
);

flx_gql_mutation(
	'flxHeadlessRevalidateTest',
	array(
		'description' => __( 'Sends a signed test call to a front end\'s webhook and waits for the answer.', 'flx-headless' ),
		'cap'         => 'manage_options',
		'input'       => array(
			'id' => array( 'type' => 'ID!', 'description' => __( 'Front end id.', 'flx-headless' ) ),
		),
		'output'      => array_merge(
			array(
				'delivery' => array(
					'type'        => 'FlxHeadlessDelivery',
					'description' => __( 'Result of the call.', 'flx-headless' ),
				),
			),
			$flxhl_settings_out
		),
		'resolve'     => static function ( array $input ) {
			$r = FLXHL_Revalidate_Service::test( flx_gql_str( $input['id'] ?? '' ) );
			return $r instanceof WP_Error ? $r : array( 'delivery' => $r );
		},
	)
);

flx_gql_mutation(
	'flxHeadlessRevalidateAll',
	array(
		'description' => __( 'Asks every front end to refresh all of its pages (tag flx-headless:all), e.g. after a theme change.', 'flx-headless' ),
		'cap'         => 'manage_options',
		'input'       => array(),
		'output'      => $flxhl_settings_out,
		'resolve'     => static function (): array {
			FLXHL_Revalidate_Service::queue( array( 'flx-headless:all', FLXHL_Frontend_Service::CACHE_TAG ) );
			FLXHL_Revalidate_Service::flush();
			return array();
		},
	)
);

flx_gql_mutation(
	'flxHeadlessLogClear',
	array(
		'description' => __( 'Empties the webhook delivery log.', 'flx-headless' ),
		'cap'         => 'manage_options',
		'input'       => array(),
		'output'      => $flxhl_settings_out,
		'resolve'     => static function (): array {
			FLXHL_Revalidate_Service::log_clear();
			return array();
		},
	)
);

unset( $flxhl_settings_out );
