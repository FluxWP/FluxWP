<?php
/**
 * FluxWP theme GraphQL schema (Flux GraphQL kit). Loaded on flx_gql_register, i.e. only inside a GraphQL
 * request. Resolvers call FLX_Theme_Service; the Theme Settings and System Check screens (wp-admin and
 * the FluxWP app) read and write only through these fields.
 *
 * Global styles source (one only): the merged theme.json settings WordPress serves for the site, i.e.
 * Site Editor changes over theme.json over WordPress defaults. Theme Settings only adds the dark-mode
 * switch, the table header colour and the background pattern.
 *
 * @package fluxwp
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
function flx_theme_gql_src( $source, string $class ) {
	if ( ! $source instanceof $class ) {
		throw flx_gql_error( 'INTERNAL' );
	}
	return $source;
}

/**
 * A field that reads one property of the parent DTO.
 *
 * @template T of object
 * @param class-string<T> $class Parent class.
 * @param string          $prop  Public property of the parent.
 * @param string          $type  GraphQL type.
 * @param string          $desc  Description.
 * @return array<string,mixed>
 */
function flx_theme_gql_prop( string $class, string $prop, string $type, string $desc ): array {
	return array(
		'type'        => $type,
		'description' => $desc,
		'resolve'     => static fn( $s ) => flx_theme_gql_src( $s, $class )->$prop,
	);
}

/**
 * FlxDateTime value of a Unix time; null for an empty one.
 *
 * @return array<string,mixed>|null
 */
function flx_theme_gql_time( ?int $ts ): ?array {
	return null === $ts || $ts <= 0 ? null : flx_gql_datetime( $ts );
}

/* ---------- Enums ---------- */

flx_gql_enum(
	'FlxThemeVisibility',
	array(
		'description' => __( 'Who sees the public site.', 'fluxwp' ),
		'values'      => array(
			'LIVE'        => array( 'value' => 'live', 'description' => __( 'Everyone sees the whole site.', 'fluxwp' ) ),
			'COMING_SOON' => array( 'value' => 'coming-soon', 'description' => __( 'Visitors only see the coming-soon page.', 'fluxwp' ) ),
			'STORE'       => array( 'value' => 'store', 'description' => __( 'The site is open; store pages show a coming-soon notice (needs WooCommerce).', 'fluxwp' ) ),
		),
	)
);
flx_gql_enum(
	'FlxThemeSiteType',
	array(
		'description' => __( 'Wording of My account, the cart and the mobile menu.', 'fluxwp' ),
		'values'      => array(
			'SHOP'     => array( 'value' => 'shop' ),
			'DONATION' => array( 'value' => 'donation' ),
		),
	)
);
flx_gql_enum(
	'FlxThemePattern',
	array(
		'description' => __( 'Background decoration of title bands, menus and consent cards.', 'fluxwp' ),
		'values'      => array(
			'DOTS'   => array( 'value' => 'dots' ),
			'LEAVES' => array( 'value' => 'leaves' ),
			'NONE'   => array( 'value' => 'none' ),
		),
	)
);
flx_gql_enum(
	'FlxThemeDarkMode',
	array(
		'description' => __( 'Dark mode of the public site.', 'fluxwp' ),
		'values'      => array(
			'AUTO' => array( 'value' => 'auto', 'description' => __( 'Follows the visitor\'s device.', 'fluxwp' ) ),
			'OFF'  => array( 'value' => 'off', 'description' => __( 'Always light.', 'fluxwp' ) ),
		),
	)
);
flx_gql_enum(
	'FlxThemePagination',
	array(
		'description' => __( 'How archive pages continue.', 'fluxwp' ),
		'values'      => array(
			'INFINITE' => array( 'value' => 'sonsuz' ),
			'NUMBERED' => array( 'value' => 'numara' ),
		),
	)
);
flx_gql_enum(
	'FlxThemeCookiePosition',
	array(
		'description' => __( 'Cookie banner layout.', 'fluxwp' ),
		'values'      => array(
			'CARD' => array( 'value' => 'card' ),
			'BAR'  => array( 'value' => 'bar' ),
		),
	)
);
flx_gql_enum(
	'FlxThemeGaMode',
	array(
		'description' => __( 'How Google Analytics loads.', 'fluxwp' ),
		'values'      => array(
			'CONSENT' => array( 'value' => 'consent', 'description' => __( 'Google tag in the head, cookie-free until consent (Consent Mode v2).', 'fluxwp' ) ),
			'AFTER'   => array( 'value' => 'after', 'description' => __( 'Only after the visitor accepts.', 'fluxwp' ) ),
		),
	)
);
flx_gql_enum(
	'FlxThemeCheckStatus',
	array(
		'description' => __( 'Result of a System Check row.', 'fluxwp' ),
		'values'      => array(
			'OK'      => array( 'value' => 'ok' ),
			'WARNING' => array( 'value' => 'warning' ),
			'ERROR'   => array( 'value' => 'error' ),
			'INFO'    => array( 'value' => 'info' ),
		),
	)
);

/* ---------- Theme Settings ---------- */

flx_gql_type(
	'FlxThemeOption',
	array(
		'description' => __( 'A page or donation type offered in a select.', 'fluxwp' ),
		'fields'      => array(
			'databaseId' => flx_theme_gql_prop( FLX_Theme_Option::class, 'id', 'Int!', __( 'Post or donation type ID.', 'fluxwp' ) ),
			'title'      => flx_theme_gql_prop( FLX_Theme_Option::class, 'title', 'String!', __( 'Name.', 'fluxwp' ) ),
		),
	)
);

$flx_theme_s = FLX_Theme_Settings::class;
flx_gql_type(
	'FlxThemeSettings',
	array(
		'description' => __( 'Theme Settings (Appearance › Theme Settings) with what the screen needs around them.', 'fluxwp' ),
		'fields'      => array(
			'visibility'          => flx_theme_gql_prop( $flx_theme_s, 'visibility', 'FlxThemeVisibility!', __( 'Who sees the site. With WooCommerce this is the same setting as WooCommerce › Settings › Site visibility.', 'fluxwp' ) ),
			'comingSoonTitle'     => flx_theme_gql_prop( $flx_theme_s, 'coming_soon_title', 'String!', __( 'Entire-site coming-soon title; empty uses the translated default.', 'fluxwp' ) ),
			'comingSoonText'      => flx_theme_gql_prop( $flx_theme_s, 'coming_soon_text', 'String!', __( 'Entire-site coming-soon message; empty uses the translated default.', 'fluxwp' ) ),
			'storeNoticeTitle'    => flx_theme_gql_prop( $flx_theme_s, 'store_title', 'String!', __( 'Store-notice title; empty uses the default for the site type.', 'fluxwp' ) ),
			'storeNoticeText'     => flx_theme_gql_prop( $flx_theme_s, 'store_text', 'String!', __( 'Store-notice message; empty uses the default for the site type.', 'fluxwp' ) ),
			'storeNoticeTitleDefault' => flx_theme_gql_prop( $flx_theme_s, 'store_title_default', 'String!', __( 'Default store-notice title for the site type.', 'fluxwp' ) ),
			'storeNoticeTextDefault'  => flx_theme_gql_prop( $flx_theme_s, 'store_text_default', 'String!', __( 'Default store-notice message for the site type.', 'fluxwp' ) ),
			'contactEmail'        => flx_theme_gql_prop( $flx_theme_s, 'contact_email', 'String!', __( 'Public contact email shown on the coming-soon page; empty shows none.', 'fluxwp' ) ),
			'siteType'            => flx_theme_gql_prop( $flx_theme_s, 'site_type', 'FlxThemeSiteType!', __( 'Shop or donation wording.', 'fluxwp' ) ),
			'pattern'             => flx_theme_gql_prop( $flx_theme_s, 'pattern', 'FlxThemePattern!', __( 'Background decoration.', 'fluxwp' ) ),
			'tableHead'           => flx_theme_gql_prop( $flx_theme_s, 'table_head', 'String!', __( 'Table header colour (#RRGGBB); empty uses the style colour.', 'fluxwp' ) ),
			'newsIntro'           => flx_theme_gql_prop( $flx_theme_s, 'news_intro', 'String!', __( 'News archive introduction; empty uses the translated default.', 'fluxwp' ) ),
			'darkMode'            => flx_theme_gql_prop( $flx_theme_s, 'dark_mode', 'FlxThemeDarkMode!', __( 'Dark mode of the public site.', 'fluxwp' ) ),
			'animations'          => flx_theme_gql_prop( $flx_theme_s, 'animations', 'Boolean!', __( 'Scroll reveal animations.', 'fluxwp' ) ),
			'pagination'          => flx_theme_gql_prop( $flx_theme_s, 'pagination', 'FlxThemePagination!', __( 'Archive pagination.', 'fluxwp' ) ),
			'activeCard'          => flx_theme_gql_prop( $flx_theme_s, 'active_card', 'Boolean!', __( 'Show the active project card under the home page slider.', 'fluxwp' ) ),
			'activeCampaign'      => flx_theme_gql_prop( $flx_theme_s, 'active_campaign', 'Int!', __( 'Donation type whose progress the card shows; 0 picks the first open type with a goal.', 'fluxwp' ) ),
			'cookieEnabled'       => flx_theme_gql_prop( $flx_theme_s, 'cookie_enabled', 'Boolean!', __( 'Show the cookie banner on the first visit.', 'fluxwp' ) ),
			'cookieTitle'         => flx_theme_gql_prop( $flx_theme_s, 'cookie_title', 'String!', __( 'Banner title.', 'fluxwp' ) ),
			'cookieText'          => flx_theme_gql_prop( $flx_theme_s, 'cookie_text', 'String!', __( 'Banner text; links allowed.', 'fluxwp' ) ),
			'cookieAccept'        => flx_theme_gql_prop( $flx_theme_s, 'cookie_accept', 'String!', __( 'Accept button label.', 'fluxwp' ) ),
			'cookieReject'        => flx_theme_gql_prop( $flx_theme_s, 'cookie_reject', 'String!', __( 'Necessary-only button label.', 'fluxwp' ) ),
			'cookieMore'          => flx_theme_gql_prop( $flx_theme_s, 'cookie_more', 'String!', __( 'Policy link label.', 'fluxwp' ) ),
			'cookiePage'          => flx_theme_gql_prop( $flx_theme_s, 'cookie_page', 'Int!', __( 'Cookie policy page ID; 0 finds /cerez-politikasi/.', 'fluxwp' ) ),
			'cookiePosition'      => flx_theme_gql_prop( $flx_theme_s, 'cookie_position', 'FlxThemeCookiePosition!', __( 'Banner layout.', 'fluxwp' ) ),
			'cookieRevision'      => flx_theme_gql_prop( $flx_theme_s, 'cookie_revision', 'Int!', __( 'Consent version; raising it asks everyone again.', 'fluxwp' ) ),
			'ga4Id'               => flx_theme_gql_prop( $flx_theme_s, 'ga4_id', 'String!', __( 'Google Analytics 4 measurement ID (G-XXXXXXX); empty when not set.', 'fluxwp' ) ),
			'gaMode'              => flx_theme_gql_prop( $flx_theme_s, 'ga_mode', 'FlxThemeGaMode!', __( 'How Google Analytics loads.', 'fluxwp' ) ),
			'consentScripts'      => flx_theme_gql_prop( $flx_theme_s, 'consent_scripts', 'String', __( 'Extra code added after consent. Null for users who may not add raw code.', 'fluxwp' ) ),
			'bankHolder'          => flx_theme_gql_prop( $flx_theme_s, 'bank_holder', 'String!', __( 'Account holder for Havale/EFT.', 'fluxwp' ) ),
			'bankIban'            => flx_theme_gql_prop( $flx_theme_s, 'bank_iban', 'String!', __( 'IBAN without spaces; empty when not set.', 'fluxwp' ) ),
			'bankInUse'           => array(
				'type'        => 'String',
				'description' => __( 'The account the site really shows when it is not this one (Flux Donate / WooCommerce transfer settings win); null when the same.', 'fluxwp' ),
				'resolve'     => static function ( $s ): ?string {
					$v = flx_theme_gql_src( $s, FLX_Theme_Settings::class )->bank_in_use;
					return '' === $v ? null : $v;
				},
			),
			'woocommerceActive'   => flx_theme_gql_prop( $flx_theme_s, 'woocommerce', 'Boolean!', __( 'WooCommerce is active (store-only coming soon needs it).', 'fluxwp' ) ),
			'activeCardAvailable' => flx_theme_gql_prop( $flx_theme_s, 'active_card_available', 'Boolean!', __( 'The home page campaign card exists (Flux Donate).', 'fluxwp' ) ),
			'canEditScripts'      => flx_theme_gql_prop( $flx_theme_s, 'can_edit_scripts', 'Boolean!', __( 'The user may save raw code in consentScripts.', 'fluxwp' ) ),
			'presetsUrl'          => array(
				'type'        => 'String',
				'description' => __( 'Presets tab of Theme Settings; null without Flux Setup.', 'fluxwp' ),
				'resolve'     => static function ( $s ): ?string {
					$v = flx_theme_gql_src( $s, FLX_Theme_Settings::class )->presets_url;
					return '' === $v ? null : $v;
				},
			),
			'campaigns'           => flx_theme_gql_prop( $flx_theme_s, 'campaigns', '[FlxThemeOption!]!', __( 'Donation types to pick the home page card from (the first 100); empty without Flux Donate.', 'fluxwp' ) ),
		),
	)
);
unset( $flx_theme_s );

flx_gql_query(
	'flxThemeSettings',
	array(
		'type'        => 'FlxThemeSettings!',
		'description' => __( 'Theme Settings and the context the Theme Settings screen needs.', 'fluxwp' ),
		'cap'         => 'manage_options',
		'resolve'     => static function (): FLX_Theme_Settings {
			flx_gql_cache_tag( FLX_Theme_Service::CACHE_TAG );
			return FLX_Theme_Service::settings();
		},
	)
);

flx_gql_mutation(
	'flxThemeSettingsSave',
	array(
		'description' => __( 'Changes Theme Settings. Only the fields you send change; nothing is written when any of them is invalid.', 'fluxwp' ),
		'cap'         => 'manage_options',
		'input'       => array(
			'visibility'      => array( 'type' => 'FlxThemeVisibility', 'description' => __( 'Who sees the site (also updates WooCommerce).', 'fluxwp' ) ),
			'comingSoonTitle' => array( 'type' => 'String', 'description' => __( 'Entire-site coming-soon title.', 'fluxwp' ) ),
			'comingSoonText'  => array( 'type' => 'String', 'description' => __( 'Entire-site coming-soon message.', 'fluxwp' ) ),
			'storeNoticeTitle' => array( 'type' => 'String', 'description' => __( 'Store-notice title.', 'fluxwp' ) ),
			'storeNoticeText' => array( 'type' => 'String', 'description' => __( 'Store-notice message.', 'fluxwp' ) ),
			'contactEmail'    => array( 'type' => 'String', 'description' => __( 'Public contact email.', 'fluxwp' ) ),
			'siteType'        => array( 'type' => 'FlxThemeSiteType', 'description' => __( 'Shop or donation wording.', 'fluxwp' ) ),
			'pattern'         => array( 'type' => 'FlxThemePattern', 'description' => __( 'Background decoration.', 'fluxwp' ) ),
			'tableHead'       => array( 'type' => 'String', 'description' => __( 'Table header colour (#RRGGBB); empty clears it.', 'fluxwp' ) ),
			'newsIntro'       => array( 'type' => 'String', 'description' => __( 'News archive introduction.', 'fluxwp' ) ),
			'darkMode'        => array( 'type' => 'FlxThemeDarkMode', 'description' => __( 'Dark mode of the public site.', 'fluxwp' ) ),
			'animations'      => array( 'type' => 'Boolean', 'description' => __( 'Scroll reveal animations.', 'fluxwp' ) ),
			'pagination'      => array( 'type' => 'FlxThemePagination', 'description' => __( 'Archive pagination.', 'fluxwp' ) ),
			'activeCard'      => array( 'type' => 'Boolean', 'description' => __( 'Show the active project card on the home page.', 'fluxwp' ) ),
			'activeCampaign'  => array( 'type' => 'Int', 'description' => __( 'Donation type ID of the card; 0 = automatic.', 'fluxwp' ) ),
			'cookieEnabled'   => array( 'type' => 'Boolean', 'description' => __( 'Show the cookie banner.', 'fluxwp' ) ),
			'cookieTitle'     => array( 'type' => 'String', 'description' => __( 'Banner title.', 'fluxwp' ) ),
			'cookieText'      => array( 'type' => 'String', 'description' => __( 'Banner text; links allowed.', 'fluxwp' ) ),
			'cookieAccept'    => array( 'type' => 'String', 'description' => __( 'Accept button label.', 'fluxwp' ) ),
			'cookieReject'    => array( 'type' => 'String', 'description' => __( 'Necessary-only button label.', 'fluxwp' ) ),
			'cookieMore'      => array( 'type' => 'String', 'description' => __( 'Policy link label.', 'fluxwp' ) ),
			'cookiePage'      => array( 'type' => 'Int', 'description' => __( 'Policy page ID; 0 = automatic.', 'fluxwp' ) ),
			'cookiePosition'  => array( 'type' => 'FlxThemeCookiePosition', 'description' => __( 'Banner layout.', 'fluxwp' ) ),
			'cookieReset'     => array( 'type' => 'Boolean', 'description' => __( 'true asks every visitor again (raises the consent version).', 'fluxwp' ) ),
			'ga4Id'           => array( 'type' => 'String', 'description' => __( 'GA4 measurement ID (G-XXXXXXX); empty turns it off.', 'fluxwp' ) ),
			'gaMode'          => array( 'type' => 'FlxThemeGaMode', 'description' => __( 'How Google Analytics loads.', 'fluxwp' ) ),
			'consentScripts'  => array( 'type' => 'String', 'description' => __( 'Extra code added after consent; needs the unfiltered_html capability.', 'fluxwp' ) ),
			'bankHolder'      => array( 'type' => 'String', 'description' => __( 'Account holder.', 'fluxwp' ) ),
			'bankIban'        => array( 'type' => 'String', 'description' => __( 'IBAN (TR + 24 digits); empty clears it.', 'fluxwp' ) ),
		),
		'output'      => array(
			'settings' => array(
				'type'        => 'FlxThemeSettings',
				'description' => __( 'The settings after the change; null when something was invalid.', 'fluxwp' ),
				'resolve'     => static fn(): FLX_Theme_Settings => FLX_Theme_Service::settings(),
			),
		),
		'resolve'     => static function ( array $input ) {
			$map     = array(
				'visibility'       => 'visibility',
				'comingSoonTitle'  => 'coming_soon_title',
				'comingSoonText'   => 'coming_soon_text',
				'storeNoticeTitle' => 'store_title',
				'storeNoticeText'  => 'store_text',
				'contactEmail'     => 'contact_email',
				'siteType'         => 'site_type',
				'pattern'          => 'pattern',
				'tableHead'        => 'table_head',
				'newsIntro'        => 'news_intro',
				'darkMode'         => 'dark_mode',
				'animations'       => 'animations',
				'pagination'       => 'pagination',
				'activeCard'       => 'active_card',
				'activeCampaign'   => 'active_campaign',
				'cookieEnabled'    => 'cookie_enabled',
				'cookieTitle'      => 'cookie_title',
				'cookieText'       => 'cookie_text',
				'cookieAccept'     => 'cookie_accept',
				'cookieReject'     => 'cookie_reject',
				'cookieMore'       => 'cookie_more',
				'cookiePage'       => 'cookie_page',
				'cookiePosition'   => 'cookie_position',
				'cookieReset'      => 'cookie_reset',
				'ga4Id'            => 'ga4_id',
				'gaMode'           => 'ga_mode',
				'consentScripts'   => 'consent_scripts',
				'bankHolder'       => 'bank_holder',
				'bankIban'         => 'bank_iban',
			);
			$changes = array();
			foreach ( $map as $gql => $key ) {
				if ( array_key_exists( $gql, $input ) && null !== $input[ $gql ] ) {
					$changes[ $key ] = $input[ $gql ];
				}
			}
			$r = FLX_Theme_Service::save( $changes );
			return $r instanceof WP_Error ? $r : array( 'settings' => $r );
		},
	)
);

/* ---------- Global styles ---------- */

flx_gql_type(
	'FlxThemeColor',
	array(
		'description' => __( 'A colour of the site palette.', 'fluxwp' ),
		'fields'      => array(
			'slug'  => flx_theme_gql_prop( FLX_Theme_Color::class, 'slug', 'String!', __( 'Palette slug (CSS variable --wp--preset--color--<slug>).', 'fluxwp' ) ),
			'name'  => flx_theme_gql_prop( FLX_Theme_Color::class, 'name', 'String!', __( 'Name.', 'fluxwp' ) ),
			'color' => flx_theme_gql_prop( FLX_Theme_Color::class, 'color', 'String!', __( 'CSS colour value.', 'fluxwp' ) ),
		),
	)
);
flx_gql_type(
	'FlxThemeFont',
	array(
		'description' => __( 'A font family of the site.', 'fluxwp' ),
		'fields'      => array(
			'slug'       => flx_theme_gql_prop( FLX_Theme_Font::class, 'slug', 'String!', __( 'Font slug (CSS variable --wp--preset--font-family--<slug>).', 'fluxwp' ) ),
			'name'       => flx_theme_gql_prop( FLX_Theme_Font::class, 'name', 'String!', __( 'Name.', 'fluxwp' ) ),
			'fontFamily' => flx_theme_gql_prop( FLX_Theme_Font::class, 'font_family', 'String!', __( 'CSS font-family value.', 'fluxwp' ) ),
		),
	)
);
flx_gql_type(
	'FlxThemeGlobalStyles',
	array(
		'description' => __( 'Colours, fonts and the dark-mode switch of the public site, for headless front ends and the app.', 'fluxwp' ),
		'fields'      => array(
			'palette'    => flx_theme_gql_prop( FLX_Theme_Styles::class, 'palette', '[FlxThemeColor!]!', __( 'Colours: Site Editor changes over theme.json over WordPress defaults.', 'fluxwp' ) ),
			'fonts'      => flx_theme_gql_prop( FLX_Theme_Styles::class, 'fonts', '[FlxThemeFont!]!', __( 'Font families; empty when the theme declares none.', 'fluxwp' ) ),
			'darkMode'   => flx_theme_gql_prop( FLX_Theme_Styles::class, 'dark_mode', 'Boolean!', __( 'The site follows the visitor\'s dark mode (false = always light).', 'fluxwp' ) ),
			'tableHead'  => flx_theme_gql_prop( FLX_Theme_Styles::class, 'table_head', 'String!', __( 'Table header colour (#RRGGBB); empty uses the palette colour "table-head".', 'fluxwp' ) ),
			'pattern'    => flx_theme_gql_prop( FLX_Theme_Styles::class, 'pattern', 'FlxThemePattern!', __( 'Background decoration.', 'fluxwp' ) ),
		),
	)
);
flx_gql_query(
	'flxThemeGlobalStyles',
	array(
		'type'        => 'FlxThemeGlobalStyles!',
		'description' => __( 'Public design tokens of the site (palette, fonts, dark mode).', 'fluxwp' ),
		// Public on purpose: these are the same colours and fonts every visitor's stylesheet already carries.
		'cap'         => 'public',
		'resolve'     => static function (): FLX_Theme_Styles {
			flx_gql_cache_tag( FLX_Theme_Service::CACHE_TAG );
			return FLX_Theme_Service::global_styles();
		},
	)
);

/* ---------- Payment logos ---------- */

flx_gql_type(
	'FlxThemePayLogo',
	array(
		'description' => __( 'A payment or trust logo shown in the footer.', 'fluxwp' ),
		'fields'      => array(
			'key'   => flx_theme_gql_prop( FLX_Theme_Pay_Logo::class, 'key', 'String!', __( 'Built-in key (iyzico …) or a:<attachment ID>.', 'fluxwp' ) ),
			'label' => flx_theme_gql_prop( FLX_Theme_Pay_Logo::class, 'label', 'String!', __( 'Name.', 'fluxwp' ) ),
			'url'   => flx_theme_gql_prop( FLX_Theme_Pay_Logo::class, 'url', 'String!', __( 'Image URL.', 'fluxwp' ) ),
			'chip'  => flx_theme_gql_prop( FLX_Theme_Pay_Logo::class, 'chip', 'Boolean!', __( 'Shown in a white box on a dark footer.', 'fluxwp' ) ),
		),
	)
);
flx_gql_type(
	'FlxThemePayLogos',
	array(
		'description' => __( 'The footer payment logos.', 'fluxwp' ),
		'fields'      => array(
			'automatic' => flx_theme_gql_prop( FLX_Theme_Pay_Logos::class, 'automatic', 'Boolean!', __( 'The list is automatic (iyzico band and the logos of enabled virtual POS plugins), not hand-picked.', 'fluxwp' ) ),
			'current'   => flx_theme_gql_prop( FLX_Theme_Pay_Logos::class, 'current', '[FlxThemePayLogo!]!', __( 'Logos the footer shows, in order.', 'fluxwp' ) ),
			'builtin'   => flx_theme_gql_prop( FLX_Theme_Pay_Logos::class, 'builtin', '[FlxThemePayLogo!]!', __( 'Logos the theme and plugins provide.', 'fluxwp' ) ),
			'automaticList' => flx_theme_gql_prop( FLX_Theme_Pay_Logos::class, 'automatic_list', '[FlxThemePayLogo!]!', __( 'What the automatic list shows (the iyzico band and the logos of enabled virtual POS plugins).', 'fluxwp' ) ),
		),
	)
);
flx_gql_query(
	'flxThemePayLogos',
	array(
		'type'        => 'FlxThemePayLogos!',
		'description' => __( 'Footer payment logos and the ones that can be added.', 'fluxwp' ),
		'cap'         => 'manage_options',
		'resolve'     => static function (): FLX_Theme_Pay_Logos {
			flx_gql_cache_tag( FLX_Theme_Service::CACHE_TAG );
			return FLX_Theme_Service::pay_logos();
		},
	)
);
flx_gql_mutation(
	'flxThemePayLogosSave',
	array(
		'description' => __( 'Hand-picks the footer payment logos, or goes back to the automatic list.', 'fluxwp' ),
		'cap'         => 'manage_options',
		'input'       => array(
			'keys'      => array( 'type' => '[String!]', 'description' => __( 'Logo keys in order (built-in keys and a:<attachment ID>). Unknown keys are dropped.', 'fluxwp' ) ),
			'automatic' => array( 'type' => 'Boolean', 'description' => __( 'true returns to the automatic list; keys are then ignored.', 'fluxwp' ) ),
		),
		'output'      => array(
			'logos' => array(
				'type'        => 'FlxThemePayLogos',
				'description' => __( 'The logos after the change.', 'fluxwp' ),
				'resolve'     => static fn(): FLX_Theme_Pay_Logos => FLX_Theme_Service::pay_logos(),
			),
		),
		'resolve'     => static function ( array $input ) {
			$keys = array();
			foreach ( flx_gql_arr( $input['keys'] ?? array() ) as $k ) {
				$keys[] = flx_gql_str( $k );
			}
			$r = FLX_Theme_Service::save_pay_logos( $keys, ! empty( $input['automatic'] ) );
			return $r instanceof WP_Error ? $r : array( 'logos' => $r );
		},
	)
);

/* ---------- System Check ---------- */

flx_gql_type(
	'FlxThemeCheck',
	array(
		'description' => __( 'One row of the System Check.', 'fluxwp' ),
		'fields'      => array(
			'id'          => flx_theme_gql_prop( FLX_Theme_Check::class, 'id', 'String!', __( 'Check ID.', 'fluxwp' ) ),
			'source'      => flx_theme_gql_prop( FLX_Theme_Check::class, 'source', 'String!', __( 'Package code; "theme" or empty for the theme.', 'fluxwp' ) ),
			'sourceLabel' => array(
				'type'        => 'String',
				'description' => __( 'Readable package name; null for the theme.', 'fluxwp' ),
				'resolve'     => static function ( $c ): ?string {
					$v = flx_theme_gql_src( $c, FLX_Theme_Check::class )->source_label;
					return '' === $v ? null : $v;
				},
			),
			'group'       => flx_theme_gql_prop( FLX_Theme_Check::class, 'group', 'String!', __( 'Group title.', 'fluxwp' ) ),
			'label'       => flx_theme_gql_prop( FLX_Theme_Check::class, 'label', 'String!', __( 'What is checked.', 'fluxwp' ) ),
			'status'      => flx_theme_gql_prop( FLX_Theme_Check::class, 'status', 'FlxThemeCheckStatus!', __( 'Result.', 'fluxwp' ) ),
			'message'     => flx_theme_gql_prop( FLX_Theme_Check::class, 'message', 'String!', __( 'What was found; may be empty.', 'fluxwp' ) ),
			'actionUrl'   => array(
				'type'        => 'String',
				'description' => __( 'Page that fixes it; null when there is none.', 'fluxwp' ),
				'resolve'     => static function ( $c ): ?string {
					$v = flx_theme_gql_src( $c, FLX_Theme_Check::class )->action_url;
					return '' === $v ? null : $v;
				},
			),
			'actionLabel' => array(
				'type'        => 'String',
				'description' => __( 'Button label for actionUrl; null for the default.', 'fluxwp' ),
				'resolve'     => static function ( $c ): ?string {
					$v = flx_theme_gql_src( $c, FLX_Theme_Check::class )->action_label;
					return '' === $v ? null : $v;
				},
			),
		),
	)
);
flx_gql_type(
	'FlxThemeFixEntry',
	array(
		'description' => __( 'A fix applied through Flux AI / MCP.', 'fluxwp' ),
		'fields'      => array(
			'time'    => array(
				'type'        => 'FlxDateTime!',
				'description' => __( 'When.', 'fluxwp' ),
				'resolve'     => static fn( $e ): ?array => flx_theme_gql_time( flx_theme_gql_src( $e, FLX_Theme_Fix_Entry::class )->time ),
			),
			'user'    => flx_theme_gql_prop( FLX_Theme_Fix_Entry::class, 'user', 'String!', __( 'User login.', 'fluxwp' ) ),
			'label'   => flx_theme_gql_prop( FLX_Theme_Fix_Entry::class, 'label', 'String!', __( 'The fix.', 'fluxwp' ) ),
			'value'   => flx_theme_gql_prop( FLX_Theme_Fix_Entry::class, 'value', 'String!', __( 'Value sent; empty when none.', 'fluxwp' ) ),
			'before'  => flx_theme_gql_prop( FLX_Theme_Fix_Entry::class, 'before', 'String!', __( 'Value before.', 'fluxwp' ) ),
			'after'   => flx_theme_gql_prop( FLX_Theme_Fix_Entry::class, 'after', 'String!', __( 'Value after.', 'fluxwp' ) ),
			'success' => flx_theme_gql_prop( FLX_Theme_Fix_Entry::class, 'ok', 'Boolean!', __( 'The fix worked.', 'fluxwp' ) ),
			'message' => flx_theme_gql_prop( FLX_Theme_Fix_Entry::class, 'message', 'String!', __( 'Result message.', 'fluxwp' ) ),
		),
	)
);
flx_gql_type(
	'FlxThemeSystem',
	array(
		'description' => __( 'The System Check: every plugin\'s checks plus the recent AI fixes. Run when asked.', 'fluxwp' ),
		'fields'      => array(
			'checkedAt'       => array(
				'type'        => 'FlxDateTime!',
				'description' => __( 'When the checks ran.', 'fluxwp' ),
				'resolve'     => static fn( $s ): ?array => flx_theme_gql_time( flx_theme_gql_src( $s, FLX_Theme_System::class )->checked_at ),
			),
			'checks'          => flx_theme_gql_prop( FLX_Theme_System::class, 'checks', '[FlxThemeCheck!]!', __( 'Rows in group order, problems first inside a group.', 'fluxwp' ) ),
			'fixLogAvailable' => flx_theme_gql_prop( FLX_Theme_System::class, 'fix_log_available', 'Boolean!', __( 'Flux SEO & MCP keeps a fix log.', 'fluxwp' ) ),
			'fixLog'          => flx_theme_gql_prop( FLX_Theme_System::class, 'fix_log', '[FlxThemeFixEntry!]!', __( 'The last 20 fixes made through MCP, newest first.', 'fluxwp' ) ),
		),
	)
);
flx_gql_query(
	'flxThemeSystem',
	array(
		'type'        => 'FlxThemeSystem!',
		'description' => __( 'Runs the System Check.', 'fluxwp' ),
		'cap'         => 'manage_options',
		'resolve'     => static fn(): FLX_Theme_System => FLX_Theme_Service::system(),
	)
);

/* ---------- Migrate from the previous theme ---------- */

flx_gql_type(
	'FlxThemeMigrationSource',
	array(
		'description' => __( 'A previous theme whose settings, templates and styles can be copied to FluxWP.', 'fluxwp' ),
		'fields'      => array(
			'stylesheet' => flx_theme_gql_prop( FLX_Theme_Migration_Source::class, 'stylesheet', 'String!', __( 'Theme folder name.', 'fluxwp' ) ),
			'lastRun'    => array(
				'type'        => 'FlxDateTime',
				'description' => __( 'Last time it was migrated; null when never.', 'fluxwp' ),
				'resolve'     => static fn( $s ): ?array => flx_theme_gql_time( flx_theme_gql_src( $s, FLX_Theme_Migration_Source::class )->last_run ),
			),
		),
	)
);
flx_gql_query(
	'flxThemeMigrationSources',
	array(
		'type'        => '[FlxThemeMigrationSource!]!',
		'description' => __( 'Previous themes that have data to copy (usually none).', 'fluxwp' ),
		'cap'         => 'switch_themes',
		'resolve'     => static fn(): array => FLX_Theme_Service::migration_sources(),
	)
);
flx_gql_mutation(
	'flxThemeMigrate',
	array(
		'description' => __( 'Copies the previous theme\'s settings, templates, parts and styles to this theme. The previous theme is not changed; same-named records here are not overwritten. Safe to repeat.', 'fluxwp' ),
		'cap'         => 'switch_themes',
		'input'       => array(
			'source' => array( 'type' => 'String!', 'description' => __( 'Previous theme folder name (flxThemeMigrationSources.stylesheet).', 'fluxwp' ) ),
		),
		'output'      => array(
			'log' => array(
				'type'        => '[String!]!',
				'description' => __( 'What was done.', 'fluxwp' ),
			),
		),
		'resolve'     => static function ( array $input ) {
			$r = FLX_Theme_Service::migrate( flx_gql_str( $input['source'] ?? '' ) );
			return $r instanceof WP_Error ? $r : array( 'log' => $r );
		},
	)
);
