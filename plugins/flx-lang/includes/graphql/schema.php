<?php
/**
 * Flux Lang GraphQL schema (Flux GraphQL kit). Loaded on flx_gql_register, i.e. only inside a GraphQL
 * request. Resolvers call FLXL_Language_Service; the Languages screen (wp-admin and the FluxWP app)
 * reads and writes only through these fields.
 *
 * Other packages bind to this: `flxLanguage` / `flxTranslations` are on the ContentNode interface (every
 * post type) and on every type a package adds through the `flxl_gql_content_types` filter (docs/flx-lang/GRAPHQL.md).
 *
 * @package flx-lang
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
function flxl_gql_src( $source, string $class ) {
	if ( ! $source instanceof $class ) {
		throw flx_gql_error( 'INTERNAL' );
	}
	return $source;
}

/**
 * Post ID from a global ID or a database ID.
 *
 * @param mixed $id Value.
 * @throws FLXGQL_Error When it is not a post ID.
 */
function flxl_gql_post_id( $id ): int {
	return flx_gql_decode_id( $id, 'post' );
}

/**
 * Post ID of a ContentNode source (a WPGraphQL Post model).
 *
 * @param mixed $source Parent.
 */
function flxl_gql_content_id( $source ): int {
	if ( $source instanceof \WPGraphQL\Model\Model ) {
		return flx_gql_int( $source->databaseId ?? 0 ); // phpcs:ignore WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase
	}
	return $source instanceof WP_Post ? $source->ID : 0;
}

/* ---------------------------------------------------------------------------
 * Enums
 * ------------------------------------------------------------------------- */

flx_gql_enum(
	'FlxLanguageSuggestMode',
	array(
		'description' => __( 'What a first-time visitor gets when their language differs from the page.', 'flx-lang' ),
		'values'      => array(
			'OFF'      => array( 'value' => 'off', 'description' => __( 'Nothing.', 'flx-lang' ) ),
			'BANNER'   => array( 'value' => 'banner', 'description' => __( 'A suggestion bar at the top of the page.', 'flx-lang' ) ),
			'REDIRECT' => array( 'value' => 'redirect', 'description' => __( 'The site opens directly in their language.', 'flx-lang' ) ),
		),
	)
);

flx_gql_enum(
	'FlxLanguageSwitcherStyle',
	array(
		'description' => __( 'How the language switcher names the languages.', 'flx-lang' ),
		'values'      => array(
			'CODE' => array( 'value' => 'code', 'description' => __( 'Language codes (TR · EN).', 'flx-lang' ) ),
			'NAME' => array( 'value' => 'name', 'description' => __( 'Language names.', 'flx-lang' ) ),
			'FLAG' => array( 'value' => 'flag', 'description' => __( 'Flags.', 'flx-lang' ) ),
		),
	)
);

flx_gql_enum(
	'FlxLanguageSwitcherPlace',
	array(
		'description' => __( 'Where the theme shows the language switcher.', 'flx-lang' ),
		'values'      => array(
			'FOOTER' => array( 'value' => 'footer', 'description' => __( 'Footer.', 'flx-lang' ) ),
			'HEADER' => array( 'value' => 'header', 'description' => __( 'Header and mobile menu.', 'flx-lang' ) ),
			'BOTH'   => array( 'value' => 'both', 'description' => __( 'Both.', 'flx-lang' ) ),
		),
	)
);

flx_gql_enum(
	'FlxLanguageJobStatus',
	array(
		'description' => __( 'State of an AI translation job.', 'flx-lang' ),
		'values'      => array(
			'RUNNING' => array( 'value' => 'running', 'description' => __( 'Steps are still to run.', 'flx-lang' ) ),
			'DONE'    => array( 'value' => 'done', 'description' => __( 'The translation is written.', 'flx-lang' ) ),
			'FAILED'  => array( 'value' => 'failed', 'description' => __( 'The last step failed; running it again retries.', 'flx-lang' ) ),
		),
	)
);

/* ---------------------------------------------------------------------------
 * Types
 * ------------------------------------------------------------------------- */

flx_gql_type(
	'FlxLanguage',
	array(
		'description' => __( 'A language of the site.', 'flx-lang' ),
		'fields'      => array(
			'code'     => array(
				'type'        => 'String!',
				'description' => __( 'Language code, also the address prefix (en, de, pt-br).', 'flx-lang' ),
				'resolve'     => static fn( $l ): string => flxl_gql_src( $l, FLXL_Language::class )->code,
			),
			'name'     => array(
				'type'        => 'String!',
				'description' => __( 'Name people see (English, Deutsch).', 'flx-lang' ),
				'resolve'     => static fn( $l ): string => flxl_gql_src( $l, FLXL_Language::class )->name,
			),
			'locale'   => array(
				'type'        => 'String!',
				'description' => __( 'WordPress locale of the language\'s content (en_US).', 'flx-lang' ),
				'resolve'     => static fn( $l ): string => flxl_gql_src( $l, FLXL_Language::class )->locale,
			),
			'flag'     => array(
				'type'        => 'String',
				'description' => __( 'Flag emoji. Empty when none is set.', 'flx-lang' ),
				'resolve'     => static function ( $l ): ?string {
					$f = flxl_gql_src( $l, FLXL_Language::class )->flag;
					return '' === $f ? null : $f;
				},
			),
			'active'   => array(
				'type'        => 'Boolean!',
				'description' => __( 'Visitors can open this language. The default language is always active.', 'flx-lang' ),
				'resolve'     => static fn( $l ): bool => flxl_gql_src( $l, FLXL_Language::class )->active,
			),
			'default'  => array(
				'type'        => 'Boolean!',
				'description' => __( 'The default language (no address prefix).', 'flx-lang' ),
				'resolve'     => static fn( $l ): bool => flxl_gql_src( $l, FLXL_Language::class )->default,
			),
			'address'  => array(
				'type'        => 'String!',
				'description' => __( 'Address of the language root: "/" for the default language, "/en/" for the others.', 'flx-lang' ),
				'resolve'     => static fn( $l ): string => flxl_gql_src( $l, FLXL_Language::class )->address,
			),
			'hreflang' => array(
				'type'        => 'String!',
				'description' => __( 'hreflang value search engines get.', 'flx-lang' ),
				'resolve'     => static fn( $l ): string => flxl_gql_src( $l, FLXL_Language::class )->hreflang,
			),
		),
	)
);

flx_gql_type(
	'FlxTranslation',
	array(
		'description' => __( 'One language version of a content.', 'flx-lang' ),
		'fields'      => array(
			'lang'       => array(
				'type'        => 'String!',
				'description' => __( 'Language code of this version.', 'flx-lang' ),
				'resolve'     => static fn( $t ): string => flxl_gql_src( $t, FLXL_Translation::class )->lang,
			),
			'databaseId' => array(
				'type'        => 'Int!',
				'description' => __( 'Post ID of this version.', 'flx-lang' ),
				'resolve'     => static fn( $t ): int => flxl_gql_src( $t, FLXL_Translation::class )->id,
			),
			'id'         => array(
				'type'        => 'ID!',
				'description' => __( 'Global ID of the post (the same as the id of the content node).', 'flx-lang' ),
				'resolve'     => static fn( $t ): string => flx_gql_global_id( 'post', flxl_gql_src( $t, FLXL_Translation::class )->id ),
			),
			'title'      => array(
				'type'        => 'String!',
				'description' => __( 'Title of this version.', 'flx-lang' ),
				'resolve'     => static fn( $t ): string => flxl_gql_src( $t, FLXL_Translation::class )->title,
			),
			'status'     => array(
				'type'        => 'String!',
				'description' => __( 'WordPress post status (publish, draft, pending …). Anyone sees only published versions; people who can edit the version see the others too.', 'flx-lang' ),
				'resolve'     => static fn( $t ): string => flxl_gql_src( $t, FLXL_Translation::class )->status,
			),
			'url'        => array(
				'type'        => 'String',
				'description' => __( 'Public address of this version. Empty until it is published.', 'flx-lang' ),
				'resolve'     => static fn( $t ): ?string => flxl_gql_src( $t, FLXL_Translation::class )->url,
			),
			'editUrl'    => array(
				'type'        => 'String',
				'description' => __( 'Editor address. Empty when the viewer cannot edit this version.', 'flx-lang' ),
				'resolve'     => static fn( $t ): ?string => flxl_gql_src( $t, FLXL_Translation::class )->edit_url,
			),
			'isSelf'     => array(
				'type'        => 'Boolean!',
				'description' => __( 'This is the content the list was read from.', 'flx-lang' ),
				'resolve'     => static fn( $t ): bool => flxl_gql_src( $t, FLXL_Translation::class )->is_self,
			),
		),
	)
);

flx_gql_type(
	'FlxLanguageSiteText',
	array(
		'description' => __( 'A site text (site title, tagline, home page title …) in one language.', 'flx-lang' ),
		'fields'      => array(
			'lang'  => array(
				'type'        => 'String!',
				'description' => __( 'Language code (never the default language).', 'flx-lang' ),
				'resolve'     => static fn( $t ): string => flxl_gql_src( $t, FLXL_Site_Text::class )->lang,
			),
			'key'   => array(
				'type'        => 'String!',
				'description' => __( 'Text key (blogname, blogdescription, flxseo_home_title …).', 'flx-lang' ),
				'resolve'     => static fn( $t ): string => flxl_gql_src( $t, FLXL_Site_Text::class )->key,
			),
			'value' => array(
				'type'        => 'String!',
				'description' => __( 'The text in that language.', 'flx-lang' ),
				'resolve'     => static fn( $t ): string => flxl_gql_src( $t, FLXL_Site_Text::class )->value,
			),
		),
	)
);

flx_gql_type(
	'FlxLanguageTextField',
	array(
		'description' => __( 'A site text that can be translated.', 'flx-lang' ),
		'fields'      => array(
			'key'         => array(
				'type'        => 'String!',
				'description' => __( 'Text key.', 'flx-lang' ),
				'resolve'     => static fn( $f ): string => flxl_gql_src( $f, FLXL_Text_Field::class )->key,
			),
			'label'       => array(
				'type'        => 'String!',
				'description' => __( 'What the text is, in the user\'s language.', 'flx-lang' ),
				'resolve'     => static fn( $f ): string => flxl_gql_src( $f, FLXL_Text_Field::class )->label,
			),
			'textarea'    => array(
				'type'        => 'Boolean!',
				'description' => __( 'A long text (shown in a multi-line box).', 'flx-lang' ),
				'resolve'     => static fn( $f ): bool => flxl_gql_src( $f, FLXL_Text_Field::class )->textarea,
			),
			'placeholder' => array(
				'type'        => 'String!',
				'description' => __( 'The text in the default language; shown as a hint while the translation is empty.', 'flx-lang' ),
				'resolve'     => static fn( $f ): string => flxl_gql_src( $f, FLXL_Text_Field::class )->placeholder,
			),
		),
	)
);

flx_gql_type(
	'FlxLanguageCatalogEntry',
	array(
		'description' => __( 'A language that can be added to the site.', 'flx-lang' ),
		'fields'      => array(
			'code'   => array(
				'type'        => 'String!',
				'description' => __( 'Language code.', 'flx-lang' ),
				'resolve'     => static fn( $c ): string => flxl_gql_src( $c, FLXL_Catalog_Entry::class )->code,
			),
			'name'   => array(
				'type'        => 'String!',
				'description' => __( 'Name.', 'flx-lang' ),
				'resolve'     => static fn( $c ): string => flxl_gql_src( $c, FLXL_Catalog_Entry::class )->name,
			),
			'locale' => array(
				'type'        => 'String!',
				'description' => __( 'WordPress locale.', 'flx-lang' ),
				'resolve'     => static fn( $c ): string => flxl_gql_src( $c, FLXL_Catalog_Entry::class )->locale,
			),
			'flag'   => array(
				'type'        => 'String!',
				'description' => __( 'Flag emoji.', 'flx-lang' ),
				'resolve'     => static fn( $c ): string => flxl_gql_src( $c, FLXL_Catalog_Entry::class )->flag,
			),
		),
	)
);

flx_gql_type(
	'FlxLanguagePostType',
	array(
		'description' => __( 'A public content type that can be translated.', 'flx-lang' ),
		'fields'      => array(
			'name'     => array(
				'type'        => 'String!',
				'description' => __( 'Post type name (post, page …).', 'flx-lang' ),
				'resolve'     => static fn( $p ): string => flxl_gql_src( $p, FLXL_Post_Type_Choice::class )->name,
			),
			'label'    => array(
				'type'        => 'String!',
				'description' => __( 'Plural label.', 'flx-lang' ),
				'resolve'     => static fn( $p ): string => flxl_gql_src( $p, FLXL_Post_Type_Choice::class )->label,
			),
			'selected' => array(
				'type'        => 'Boolean!',
				'description' => __( 'Translated now.', 'flx-lang' ),
				'resolve'     => static fn( $p ): bool => flxl_gql_src( $p, FLXL_Post_Type_Choice::class )->selected,
			),
		),
	)
);

flx_gql_type(
	'FlxVisitorCountry',
	array(
		'description' => __( 'The country the server sees for the current request.', 'flx-lang' ),
		'fields'      => array(
			'country' => array(
				'type'        => 'String',
				'description' => __( 'Two-letter country code (TR, DE …). Empty when the server sends no country header; the browser time zone is the fallback then.', 'flx-lang' ),
				'resolve'     => static function ( $g ): ?string {
					$c = flxl_gql_src( $g, FLXL_Geo::class )->country;
					return '' === $c ? null : $c;
				},
			),
		),
	)
);

flx_gql_type(
	'FlxLanguageGeo',
	array(
		'description' => __( 'Country information of the settings page: the code and the header it came from.', 'flx-lang' ),
		'fields'      => array(
			'country' => array(
				'type'        => 'String',
				'description' => __( 'Two-letter country code of the current request. Empty when the server sends none.', 'flx-lang' ),
				'resolve'     => static function ( $g ): ?string {
					$c = flxl_gql_src( $g, FLXL_Geo::class )->country;
					return '' === $c ? null : $c;
				},
			),
			'source'  => array(
				'type'        => 'String',
				'description' => __( 'Name of the header the country came from. Empty when none.', 'flx-lang' ),
				'resolve'     => static function ( $g ): ?string {
					$c = flxl_gql_src( $g, FLXL_Geo::class )->source;
					return '' === $c ? null : $c;
				},
			),
		),
	)
);

flx_gql_type(
	'FlxLanguageSettings',
	array(
		'description' => __( 'The Languages settings.', 'flx-lang' ),
		'fields'      => array(
			'defaultLanguage' => array(
				'type'        => 'String!',
				'description' => __( 'Code of the default language (no address prefix).', 'flx-lang' ),
				'resolve'     => static fn( $s ): string => flxl_gql_src( $s, FLXL_Settings::class )->default_language,
			),
			'languages'       => array(
				'type'        => '[FlxLanguage!]!',
				'description' => __( 'Every language, inactive ones too; the default language first.', 'flx-lang' ),
				'resolve'     => static fn( $s ): array => flxl_gql_src( $s, FLXL_Settings::class )->languages,
			),
			'postTypes'       => array(
				'type'        => '[String!]!',
				'description' => __( 'Content types that are translated.', 'flx-lang' ),
				'resolve'     => static fn( $s ): array => flxl_gql_src( $s, FLXL_Settings::class )->post_types,
			),
			'fallback'        => array(
				'type'        => 'Boolean!',
				'description' => __( 'Show the default-language version when a translation is missing.', 'flx-lang' ),
				'resolve'     => static fn( $s ): bool => flxl_gql_src( $s, FLXL_Settings::class )->fallback,
			),
			'suggest'         => array(
				'type'        => 'FlxLanguageSuggestMode!',
				'description' => __( 'Automatic language for first-time visitors.', 'flx-lang' ),
				'resolve'     => static fn( $s ): string => flxl_gql_src( $s, FLXL_Settings::class )->suggest,
			),
			'suggestTo'       => array(
				'type'        => 'String!',
				'description' => __( 'Language used when neither the browser language nor the country\'s language is active.', 'flx-lang' ),
				'resolve'     => static fn( $s ): string => flxl_gql_src( $s, FLXL_Settings::class )->suggest_to,
			),
			'homeCountries'   => array(
				'type'        => '[String!]!',
				'description' => __( 'Two-letter country codes that see the default language when the browser language is not on the site.', 'flx-lang' ),
				'resolve'     => static fn( $s ): array => flxl_gql_src( $s, FLXL_Settings::class )->home_countries,
			),
			'switcher'        => array(
				'type'        => 'FlxLanguageSwitcherStyle!',
				'description' => __( 'How the language switcher names languages.', 'flx-lang' ),
				'resolve'     => static fn( $s ): string => flxl_gql_src( $s, FLXL_Settings::class )->switcher,
			),
			'switcherPlace'   => array(
				'type'        => 'FlxLanguageSwitcherPlace!',
				'description' => __( 'Where the theme shows the language switcher.', 'flx-lang' ),
				'resolve'     => static fn( $s ): string => flxl_gql_src( $s, FLXL_Settings::class )->switcher_place,
			),
			'texts'           => array(
				'type'        => '[FlxLanguageSiteText!]!',
				'description' => __( 'Translated site texts. A text that is not listed shows in the default language.', 'flx-lang' ),
				'resolve'     => static fn( $s ): array => flxl_gql_src( $s, FLXL_Settings::class )->texts,
			),
			'catalog'         => array(
				'type'        => '[FlxLanguageCatalogEntry!]!',
				'description' => __( 'Languages that can still be added.', 'flx-lang' ),
				'resolve'     => static fn( $s ): array => flxl_gql_src( $s, FLXL_Settings::class )->catalog,
			),
			'postTypeChoices' => array(
				'type'        => '[FlxLanguagePostType!]!',
				'description' => __( 'Public content types that can be translated.', 'flx-lang' ),
				'resolve'     => static fn( $s ): array => flxl_gql_src( $s, FLXL_Settings::class )->post_type_choices,
			),
			'textFields'      => array(
				'type'        => '[FlxLanguageTextField!]!',
				'description' => __( 'Site texts that can be translated.', 'flx-lang' ),
				'resolve'     => static fn( $s ): array => flxl_gql_src( $s, FLXL_Settings::class )->text_fields,
			),
			'geo'             => array(
				'type'        => 'FlxLanguageGeo!',
				'description' => __( 'Country information of this request (does country-based suggestion work on this server?).', 'flx-lang' ),
				'resolve'     => static fn( $s ): FLXL_Geo => flxl_gql_src( $s, FLXL_Settings::class )->geo,
			),
			'aiAvailable'     => array(
				'type'        => 'Boolean!',
				'description' => __( 'Flux AI & MCP is installed and ready, so AI translation buttons work.', 'flx-lang' ),
				'resolve'     => static fn( $s ): bool => flxl_gql_src( $s, FLXL_Settings::class )->ai_available,
			),
		),
	)
);

flx_gql_type(
	'FlxLanguageJob',
	array(
		'description' => __( 'An AI translation job: the draft translation plus the parts the AI translates one request at a time (the host stops a request at about 60 seconds).', 'flx-lang' ),
		'fields'      => array(
			'id'            => array(
				'type'        => 'ID!',
				'description' => __( 'Job key. Send it to flxLanguageTranslateStep.', 'flx-lang' ),
				'resolve'     => static fn( $j ): string => flxl_gql_src( $j, FLXL_Job::class )->id,
			),
			'status'        => array(
				'type'        => 'FlxLanguageJobStatus!',
				'description' => __( 'State of the job.', 'flx-lang' ),
				'resolve'     => static fn( $j ): string => flxl_gql_src( $j, FLXL_Job::class )->status,
			),
			'step'          => array(
				'type'        => 'Int!',
				'description' => __( 'Last finished step; -1 before the first. Run steps 0 to total - 1.', 'flx-lang' ),
				'resolve'     => static fn( $j ): int => flxl_gql_src( $j, FLXL_Job::class )->step,
			),
			'total'         => array(
				'type'        => 'Int!',
				'description' => __( 'Number of steps: the title/excerpt/SEO step plus one per body part.', 'flx-lang' ),
				'resolve'     => static fn( $j ): int => flxl_gql_src( $j, FLXL_Job::class )->total,
			),
			'sourceId'      => array(
				'type'        => 'Int!',
				'description' => __( 'Post ID of the source content.', 'flx-lang' ),
				'resolve'     => static fn( $j ): int => flxl_gql_src( $j, FLXL_Job::class )->source_id,
			),
			'translationId' => array(
				'type'        => 'Int!',
				'description' => __( 'Post ID of the translation being written.', 'flx-lang' ),
				'resolve'     => static fn( $j ): int => flxl_gql_src( $j, FLXL_Job::class )->translation_id,
			),
			'lang'          => array(
				'type'        => 'String!',
				'description' => __( 'Target language code.', 'flx-lang' ),
				'resolve'     => static fn( $j ): string => flxl_gql_src( $j, FLXL_Job::class )->lang,
			),
			'errors'        => array(
				'type'        => '[String!]!',
				'description' => __( 'Parts that stayed in the source language ("meta" or a step number). Check them in the editor.', 'flx-lang' ),
				'resolve'     => static fn( $j ): array => flxl_gql_src( $j, FLXL_Job::class )->errors,
			),
			'editUrl'       => array(
				'type'        => 'String',
				'description' => __( 'Editor address of the translation.', 'flx-lang' ),
				'resolve'     => static fn( $j ): ?string => flxl_gql_src( $j, FLXL_Job::class )->edit_url,
			),
		),
	)
);

/* ---------------------------------------------------------------------------
 * Queries
 * ------------------------------------------------------------------------- */

flx_gql_query(
	'flxLanguages',
	array(
		'description' => __( 'The languages visitors can open, default first. Public: the language switcher shows the same list.', 'flx-lang' ),
		'type'        => '[FlxLanguage!]!',
		'cap'         => 'public',
		'resolve'     => static function (): array {
			flx_gql_cache_tag( FLXL_Language_Service::TAG_LANGUAGES );
			return FLXL_Language_Service::languages();
		},
	)
);

flx_gql_query(
	'flxLanguageSettings',
	array(
		'description' => __( 'The Languages settings: every language, content types, switcher, automatic language, site texts and what the screen needs to edit them.', 'flx-lang' ),
		'type'        => 'FlxLanguageSettings!',
		'cap'         => 'manage_options',
		'resolve'     => static fn(): FLXL_Settings => FLXL_Language_Service::settings(),
	)
);

/*
 * The country depends on the visitor, so this answer must never be cached for others. The kit makes a
 * persisted GET of a visitor cacheable; a Cache-Control header set before it (priority 30) keeps it out
 * and the kit then marks the response uncacheable for LiteSpeed too.
 */
add_filter(
	'graphql_response_headers_to_send',
	static function ( $headers ) {
		$headers = is_array( $headers ) ? $headers : array();
		if ( ! empty( $GLOBALS['flxl_gql_visitor_country'] ) ) {
			$headers['Cache-Control']            = 'no-store, private';
			$headers['CDN-Cache-Control']        = 'no-store';
			$headers['X-LiteSpeed-Cache-Control'] = 'no-cache';
			$headers['Vary']                     = trim( flx_gql_str( $headers['Vary'] ?? '' ) . ', CF-IPCountry', ', ' );
		}
		return $headers;
	},
	20
);

flx_gql_query(
	'flxVisitorCountry',
	array(
		'description' => __( 'The visitor\'s country from the server\'s CDN / GeoIP header. Never cached. Public: it only returns the caller\'s own country.', 'flx-lang' ),
		'type'        => 'FlxVisitorCountry!',
		'cap'         => 'public',
		'resolve'     => static function (): FLXL_Geo {
			$GLOBALS['flxl_gql_visitor_country'] = true;
			return FLXL_Language_Service::visitor_country();
		},
	)
);

flx_gql_query(
	'flxLanguageJob',
	array(
		'description' => __( 'An AI translation job you started (it stays readable for a few minutes after it finishes).', 'flx-lang' ),
		'type'        => 'FlxLanguageJob',
		'cap'         => 'logged_in',
		'args'        => array(
			'id' => array(
				'type'        => 'ID!',
				'description' => __( 'Job key.', 'flx-lang' ),
			),
		),
		'resolve'     => static function ( $root, array $args ): FLXL_Job {
			$job = FLXL_Language_Service::job( sanitize_key( flx_gql_str( $args['id'] ?? '' ) ) );
			if ( null === $job ) {
				throw flx_gql_error( 'NOT_FOUND', __( 'Translation job not found or expired.', 'flx-lang' ), 'id' );
			}
			return $job;
		},
	)
);

/* ---------------------------------------------------------------------------
 * Content fields: flxLanguage and flxTranslations
 * ------------------------------------------------------------------------- */

/**
 * Types that get the two content fields, with the callable that returns the post ID of their parent.
 *
 * @return array<string,callable>
 */
function flxl_gql_content_types(): array {
	$types = array( 'ContentNode' => 'flxl_gql_content_id' );
	/**
	 * Types of other packages whose records are posts that Flux Lang translates.
	 *
	 * @param array<string,callable> $types GraphQL type name => callable( $source ): int, the post ID.
	 */
	$more = apply_filters( 'flxl_gql_content_types', array() );
	foreach ( is_array( $more ) ? $more : array() as $type => $cb ) {
		if ( is_string( $type ) && is_callable( $cb ) ) {
			$types[ $type ] = $cb;
		}
	}
	return $types;
}

foreach ( flxl_gql_content_types() as $flxl_type => $flxl_post_id ) {
	flx_gql_field(
		$flxl_type,
		'flxLanguage',
		array(
			'type'        => 'String',
			'description' => __( 'Language code of this content. Empty for a type that is not translated and for shared pages (cart, checkout, my account).', 'flx-lang' ),
			// A node the viewer can see already exposes its language; the code is not private.
			'cap'         => 'public',
			'resolve'     => static function ( $source ) use ( $flxl_post_id ): ?string {
				$id = flx_gql_int( call_user_func( $flxl_post_id, $source ) );
				return $id > 0 ? FLXL_Language_Service::post_language( $id ) : null;
			},
		)
	);
	flx_gql_field(
		$flxl_type,
		'flxTranslations',
		array(
			'type'        => '[FlxTranslation!]!',
			'description' => __( 'The language versions of this content, in language order, including itself. Drafts and private versions are listed only for people who can edit them. Empty for a type that is not translated.', 'flx-lang' ),
			'cap'         => 'public',
			'resolve'     => static function ( $source ) use ( $flxl_post_id ): array {
				$id = flx_gql_int( call_user_func( $flxl_post_id, $source ) );
				if ( $id <= 0 || null === FLXL_Language_Service::post_language( $id ) ) {
					return array();
				}
				flx_gql_cache_tag( 'flx-lang:post:' . $id );
				return FLXL_Language_Service::translations( $id );
			},
		)
	);
}

/* ---------------------------------------------------------------------------
 * Mutations
 * ------------------------------------------------------------------------- */

flx_gql_input(
	'FlxLanguageInput',
	array(
		'description' => __( 'A language of the site.', 'flx-lang' ),
		'fields'      => array(
			'code'   => array(
				'type'        => 'String!',
				'description' => __( 'Language code and address prefix (en, de, pt-br).', 'flx-lang' ),
			),
			'name'   => array(
				'type'        => 'String',
				'description' => __( 'Name people see. Empty keeps the code.', 'flx-lang' ),
			),
			'locale' => array(
				'type'        => 'String',
				'description' => __( 'WordPress locale of the content (en_US).', 'flx-lang' ),
			),
			'flag'   => array(
				'type'        => 'String',
				'description' => __( 'Flag emoji.', 'flx-lang' ),
			),
			'active' => array(
				'type'        => 'Boolean',
				'description' => __( 'Visitors can open it. The default language is always active.', 'flx-lang' ),
			),
		),
	)
);

flx_gql_input(
	'FlxLanguageSiteTextInput',
	array(
		'description' => __( 'A site text in one language. An empty value removes the translation.', 'flx-lang' ),
		'fields'      => array(
			'lang'  => array(
				'type'        => 'String!',
				'description' => __( 'Language code (not the default language).', 'flx-lang' ),
			),
			'key'   => array(
				'type'        => 'String!',
				'description' => __( 'Text key from textFields.', 'flx-lang' ),
			),
			'value' => array(
				'type'        => 'String!',
				'description' => __( 'The text.', 'flx-lang' ),
			),
		),
	)
);

/**
 * Typed input of flxLanguageSettingsUpdate.
 *
 * @param array<mixed> $input Raw GraphQL input.
 * @return array<string,mixed> A FlxlSettingsInput.
 */
function flxl_gql_settings_input( array $input ): array {
	$out  = array();
	$strs = array(
		'addLanguage'   => 'add_language',
		'defaultLanguage' => 'default',
		'suggestTo'     => 'suggest_to',
		'suggest'       => 'suggest',
		'switcher'      => 'switcher',
		'switcherPlace' => 'switcher_place',
	);
	foreach ( $strs as $gql => $key ) {
		if ( isset( $input[ $gql ] ) ) {
			$out[ $key ] = flx_gql_str( $input[ $gql ] );
		}
	}
	if ( isset( $input['fallback'] ) ) {
		$out['fallback'] = (bool) $input['fallback'];
	}
	foreach ( array( 'postTypes' => 'post_types', 'homeCountries' => 'home_countries' ) as $gql => $key ) {
		if ( isset( $input[ $gql ] ) && is_array( $input[ $gql ] ) ) {
			$out[ $key ] = array_values( array_map( 'flx_gql_str', $input[ $gql ] ) );
		}
	}
	if ( isset( $input['languages'] ) && is_array( $input['languages'] ) ) {
		$rows = array();
		foreach ( $input['languages'] as $raw ) {
			$r      = flx_gql_arr( $raw );
			$rows[] = array(
				'code'   => flx_gql_str( $r['code'] ?? '' ),
				'name'   => flx_gql_str( $r['name'] ?? '' ),
				'locale' => flx_gql_str( $r['locale'] ?? '' ),
				'flag'   => flx_gql_str( $r['flag'] ?? '' ),
				'active' => ! isset( $r['active'] ) || (bool) $r['active'],
			);
		}
		$out['languages'] = $rows;
	}
	if ( isset( $input['texts'] ) && is_array( $input['texts'] ) ) {
		$rows = array();
		foreach ( $input['texts'] as $raw ) {
			$r      = flx_gql_arr( $raw );
			$rows[] = array(
				'lang'  => flx_gql_str( $r['lang'] ?? '' ),
				'key'   => flx_gql_str( $r['key'] ?? '' ),
				'value' => flx_gql_str( $r['value'] ?? '' ),
			);
		}
		$out['texts'] = $rows;
	}
	return $out;
}

flx_gql_mutation(
	'flxLanguageSettingsUpdate',
	array(
		'description' => __( 'Saves the Languages settings. A field that is not sent stays as it is; `languages` and `texts`, when sent, replace the whole list (a language that is not listed is removed). Nothing is written when any part is invalid.', 'flx-lang' ),
		'cap'         => 'manage_options',
		'input'       => array(
			'languages'       => array(
				'type'        => '[FlxLanguageInput!]',
				'description' => __( 'All languages after the change. The default language cannot be removed.', 'flx-lang' ),
			),
			'addLanguage'     => array(
				'type'        => 'String',
				'description' => __( 'Code of a catalog language to add. It is added inactive: prepare its content, then activate it.', 'flx-lang' ),
			),
			'defaultLanguage' => array(
				'type'        => 'String',
				'description' => __( 'Code of the default language.', 'flx-lang' ),
			),
			'postTypes'       => array(
				'type'        => '[String!]',
				'description' => __( 'Content types to translate.', 'flx-lang' ),
			),
			'fallback'        => array(
				'type'        => 'Boolean',
				'description' => __( 'Show the default-language version when a translation is missing.', 'flx-lang' ),
			),
			'suggest'         => array(
				'type'        => 'FlxLanguageSuggestMode',
				'description' => __( 'Automatic language for first-time visitors.', 'flx-lang' ),
			),
			'suggestTo'       => array(
				'type'        => 'String',
				'description' => __( 'Language used when neither the browser language nor the country\'s language is active.', 'flx-lang' ),
			),
			'homeCountries'   => array(
				'type'        => '[String!]',
				'description' => __( 'Two-letter country codes that see the default language.', 'flx-lang' ),
			),
			'switcher'        => array(
				'type'        => 'FlxLanguageSwitcherStyle',
				'description' => __( 'How the language switcher names languages.', 'flx-lang' ),
			),
			'switcherPlace'   => array(
				'type'        => 'FlxLanguageSwitcherPlace',
				'description' => __( 'Where the theme shows the language switcher.', 'flx-lang' ),
			),
			'texts'           => array(
				'type'        => '[FlxLanguageSiteTextInput!]',
				'description' => __( 'All translated site texts after the change.', 'flx-lang' ),
			),
		),
		'output'      => array(
			'settings' => array(
				'type'        => 'FlxLanguageSettings',
				'description' => __( 'The settings after the change.', 'flx-lang' ),
			),
		),
		'resolve'     => static function ( array $input ) {
			// @phpstan-ignore argument.type (the typed shape is built by flxl_gql_settings_input()).
			$r = FLXL_Language_Service::save_settings( flxl_gql_settings_input( $input ) );
			return $r instanceof WP_Error ? $r : array( 'settings' => $r );
		},
	)
);

flx_gql_mutation(
	'flxLanguageSet',
	array(
		'description' => __( 'Sets the language of a content (the editor\'s Language box). If another content of the same group already has that language, this one leaves the group.', 'flx-lang' ),
		'cap'         => static fn( $source, array $args ): bool => current_user_can( 'edit_post', flxl_gql_post_id( $args['id'] ?? 0 ) ),
		'input'       => array(
			'id'   => array(
				'type'        => 'ID!',
				'description' => __( 'Global ID or database ID of the content.', 'flx-lang' ),
			),
			'lang' => array(
				'type'        => 'String!',
				'description' => __( 'Language code.', 'flx-lang' ),
			),
		),
		'output'      => array(
			'lang'         => array(
				'type'        => 'String',
				'description' => __( 'Language of the content after the change.', 'flx-lang' ),
			),
			'translations' => array(
				'type'        => '[FlxTranslation!]',
				'description' => __( 'Language versions of the content after the change.', 'flx-lang' ),
			),
		),
		'resolve'     => static function ( array $input ) {
			$id = flxl_gql_post_id( $input['id'] ?? 0 );
			$r  = FLXL_Language_Service::set_post_language( $id, flx_gql_str( $input['lang'] ?? '' ) );
			if ( $r instanceof WP_Error ) {
				return $r;
			}
			flx_gql_purge( array( 'flx-lang:post:' . $id ) );
			return array(
				'lang'         => FLXL_Language_Service::post_language( $id ),
				'translations' => FLXL_Language_Service::translations( $id ),
			);
		},
	)
);

flx_gql_mutation(
	'flxLanguageTranslationCreate',
	array(
		'description' => __( 'Creates the translation of a content in a language: a draft copy to translate by hand (terms, meta and image are copied). Returns the existing one when the language already has a version.', 'flx-lang' ),
		'cap'         => static fn( $source, array $args ): bool => FLXL_Language_Service::can_translate( flxl_gql_post_id( $args['sourceId'] ?? 0 ) ),
		'input'       => array(
			'sourceId' => array(
				'type'        => 'ID!',
				'description' => __( 'Global ID or database ID of the source content.', 'flx-lang' ),
			),
			'lang'     => array(
				'type'        => 'String!',
				'description' => __( 'Target language code.', 'flx-lang' ),
			),
		),
		'output'      => array(
			'translation' => array(
				'type'        => 'FlxTranslation',
				'description' => __( 'The translation.', 'flx-lang' ),
			),
		),
		'resolve'     => static function ( array $input ) {
			$src = flxl_gql_post_id( $input['sourceId'] ?? 0 );
			$r   = FLXL_Language_Service::create_translation( $src, flx_gql_str( $input['lang'] ?? '' ) );
			if ( $r instanceof WP_Error ) {
				return $r;
			}
			flx_gql_purge( array( 'flx-lang:post:' . $src ) );
			return array( 'translation' => $r );
		},
	)
);

flx_gql_mutation(
	'flxLanguageTranslateStart',
	array(
		'description' => __( 'Starts an AI translation of a content (needs Flux AI & MCP): creates the draft translation and the list of parts. Run flxLanguageTranslateStep for step 0 to total - 1.', 'flx-lang' ),
		'cap'         => static fn( $source, array $args ): bool => FLXL_Language_Service::can_translate( flxl_gql_post_id( $args['sourceId'] ?? 0 ) ),
		'input'       => array(
			'sourceId' => array(
				'type'        => 'ID!',
				'description' => __( 'Global ID or database ID of the source content.', 'flx-lang' ),
			),
			'lang'     => array(
				'type'        => 'String!',
				'description' => __( 'Target language code.', 'flx-lang' ),
			),
		),
		'output'      => array(
			'job' => array(
				'type'        => 'FlxLanguageJob',
				'description' => __( 'The job to step through.', 'flx-lang' ),
			),
		),
		'resolve'     => static function ( array $input ) {
			$src = flxl_gql_post_id( $input['sourceId'] ?? 0 );
			$r   = FLXL_Language_Service::translate_start( $src, flx_gql_str( $input['lang'] ?? '' ) );
			if ( $r instanceof WP_Error ) {
				return $r;
			}
			flx_gql_purge( array( 'flx-lang:post:' . $src ) );
			return array( 'job' => $r );
		},
	)
);

flx_gql_mutation(
	'flxLanguageTranslateStep',
	array(
		'description' => __( 'Runs one step of an AI translation job. Each step is one request, so no request runs into the host time limit. The last step writes the body and finishes the job.', 'flx-lang' ),
		'cap'         => 'logged_in',
		'input'       => array(
			'jobId' => array(
				'type'        => 'ID!',
				'description' => __( 'Job key from flxLanguageTranslateStart.', 'flx-lang' ),
			),
			'step'  => array(
				'type'        => 'Int!',
				'description' => __( 'Step to run, from 0 to total - 1.', 'flx-lang' ),
			),
		),
		'output'      => array(
			'job' => array(
				'type'        => 'FlxLanguageJob',
				'description' => __( 'The job after the step.', 'flx-lang' ),
			),
		),
		'resolve'     => static function ( array $input ) {
			$r = FLXL_Language_Service::translate_step( sanitize_key( flx_gql_str( $input['jobId'] ?? '' ) ), max( 0, flx_gql_int( $input['step'] ?? 0 ) ) );
			if ( $r instanceof WP_Error ) {
				return $r;
			}
			if ( 'done' === $r->status ) {
				flx_gql_purge( array( 'flx-lang:post:' . $r->source_id, 'flx-lang:post:' . $r->translation_id ) );
			}
			return array( 'job' => $r );
		},
	)
);
