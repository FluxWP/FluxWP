<?php
/**
 * Flux Admin GraphQL schema (Flux GraphQL kit). Loaded on flx_gql_register, i.e. only inside a GraphQL
 * request. Resolvers call the FLXA_*_Service classes; the Admin theme screen (wp-admin and the FluxWP
 * app) reads and writes only through these fields.
 *
 * Not here (ISTISNALAR.md): the PWA protocol answers (manifest, service worker, offline page, splash,
 * session bridge) stay on admin-ajax: the browser and the service worker expect files, not GraphQL.
 * The quick task form of the dashboard uses the Flux Tasks fields.
 *
 * @package flx-admin
 */

defined( 'ABSPATH' ) || exit;

/**
 * A field that reads one key of the service array the parent holds.
 *
 * @param string $type Type notation.
 * @param string $desc Description (English source).
 * @param string $key  Key of the parent array.
 * @return array<string,mixed>
 */
function flxa_gql_f( string $type, string $desc, string $key ): array {
	return array(
		'type'        => $type,
		'description' => $desc,
		'resolve'     => static fn( $src ) => is_array( $src ) ? ( $src[ $key ] ?? null ) : null,
	);
}

/**
 * Service result → value, or the WP_Error as a typed GraphQL error.
 *
 * @template T
 * @param T|WP_Error $r Result.
 * @return T
 * @throws FLXGQL_Error On WP_Error.
 */
function flxa_gql_ok( $r ) {
	if ( $r instanceof WP_Error ) {
		throw flx_gql_from_wp_error( $r );
	}
	return $r;
}

/**
 * Enum values: GRAPHQL_NAME => php value.
 *
 * @param array<string,string> $map Values.
 * @return array<string,mixed>
 */
function flxa_gql_values( array $map ): array {
	$out = array();
	foreach ( $map as $name => $value ) {
		$out[ $name ] = array( 'value' => $value );
	}
	return array( 'values' => $out );
}

flx_gql_enum( 'FlxAdminMode', array( 'description' => __( 'Light or dark admin theme.', 'flx-admin' ) ) + flxa_gql_values( array( 'AUTO' => 'auto', 'LIGHT' => 'light', 'DARK' => 'dark' ) ) );
flx_gql_enum( 'FlxAdminRadius', array( 'description' => __( 'Corner roundness.', 'flx-admin' ) ) + flxa_gql_values( array( 'NONE' => 'none', 'SMALL' => 'small', 'MEDIUM' => 'medium', 'LARGE' => 'large' ) ) );
flx_gql_enum( 'FlxAdminFont', array( 'description' => __( 'Admin font.', 'flx-admin' ) ) + flxa_gql_values( array( 'FIGTREE' => 'figtree', 'SYSTEM' => 'system', 'CUSTOM' => 'custom' ) ) );
flx_gql_enum( 'FlxAdminDensity', array( 'description' => __( 'Menu row height.', 'flx-admin' ) ) + flxa_gql_values( array( 'COMFORTABLE' => 'comfortable', 'COMPACT' => 'compact' ) ) );
flx_gql_enum( 'FlxAdminLogoStyle', array( 'description' => __( 'Style of the FluxWP package logos.', 'flx-admin' ) ) + flxa_gql_values( array( 'GRADIENT' => 'gradient', 'PASTEL' => 'pastel' ) ) );
flx_gql_enum(
	'FlxAdminDashStat',
	array( 'description' => __( 'Summary tile of the dashboard.', 'flx-admin' ) ) + flxa_gql_values(
		array(
			'DONATIONS_TODAY' => 'donations_today',
			'DONATIONS_MONTH' => 'donations_month',
			'PENDING'         => 'pending',
			'WHATSAPP'        => 'whatsapp',
			'TASKS'           => 'tasks',
			'SYSTEM'          => 'system',
		)
	)
);
flx_gql_enum( 'FlxAdminMenuEntryType', array( 'description' => __( 'A group heading or a menu item.', 'flx-admin' ) ) + flxa_gql_values( array( 'GROUP' => 'group', 'ITEM' => 'item' ) ) );
flx_gql_enum(
	'FlxAdminMenuOp',
	array( 'description' => __( 'A change to the user\'s own menu.', 'flx-admin' ) ) + flxa_gql_values(
		array(
			'PIN'      => 'pin',
			'UNPIN'    => 'unpin',
			'AWAY'     => 'away',
			'BACK'     => 'back',
			'SUB_AWAY' => 'subaway',
			'SUB_BACK' => 'subback',
			'ORDER'    => 'order',
		)
	)
);
flx_gql_enum( 'FlxAdminWidget', array( 'description' => __( 'Dashboard module with filters.', 'flx-admin' ) ) + flxa_gql_values( array( 'TASKS' => 'tasks', 'DONATIONS' => 'donations' ) ) );
flx_gql_enum( 'FlxAdminTasksScope', array( 'description' => __( 'Which tasks the Tasks module counts.', 'flx-admin' ) ) + flxa_gql_values( array( 'MINE' => 'mine', 'CREATED' => 'created', 'ALL' => 'all' ) ) );
flx_gql_enum( 'FlxAdminTasksChart', array( 'description' => __( 'How the Tasks module splits the chart.', 'flx-admin' ) ) + flxa_gql_values( array( 'STATUS' => 'status', 'PRIORITY' => 'priority', 'LIST' => 'list', 'PERSON' => 'person' ) ) );
flx_gql_enum( 'FlxAdminDonationsSplit', array( 'description' => __( 'How the Donations module splits the chart.', 'flx-admin' ) ) + flxa_gql_values( array( 'TYPE' => 'type', 'PROVIDER' => 'provider', 'NONE' => 'none' ) ) );

/* ───────────────────────── Settings ───────────────────────── */

flx_gql_type(
	'FlxAdminColors',
	array(
		'description' => __( 'The four colors of one theme (hex).', 'flx-admin' ),
		'fields'      => array(
			'bg'      => flxa_gql_f( 'String!', __( 'Page background.', 'flx-admin' ), 'bg' ),
			'side'    => flxa_gql_f( 'String!', __( 'Side menu background.', 'flx-admin' ), 'side' ),
			'surface' => flxa_gql_f( 'String!', __( 'Card background.', 'flx-admin' ), 'surface' ),
			'text'    => flxa_gql_f( 'String!', __( 'Text color.', 'flx-admin' ), 'text' ),
		),
	)
);

flx_gql_type(
	'FlxAdminTheme',
	array(
		'description' => __( 'Look of the admin panel: colors, font, corners, density.', 'flx-admin' ),
		'fields'      => array(
			'radius'     => flxa_gql_f( 'FlxAdminRadius!', __( 'Corner roundness.', 'flx-admin' ), 'radius' ),
			'font'       => flxa_gql_f( 'FlxAdminFont!', __( 'Font family choice.', 'flx-admin' ), 'font' ),
			'fontCustom' => flxa_gql_f( 'String!', __( 'Font name for the custom choice; empty otherwise.', 'flx-admin' ), 'font_custom' ),
			'fontSize'   => flxa_gql_f( 'Int!', __( 'Base font size in pixels: 13, 14 or 15.', 'flx-admin' ), 'font_size' ),
			'density'    => flxa_gql_f( 'FlxAdminDensity!', __( 'Menu row height.', 'flx-admin' ), 'density' ),
			'light'      => flxa_gql_f( 'FlxAdminColors!', __( 'Colors of the light theme (the default color when none is chosen).', 'flx-admin' ), 'light' ),
			'dark'       => flxa_gql_f( 'FlxAdminColors!', __( 'Colors of the dark theme (the default color when none is chosen).', 'flx-admin' ), 'dark' ),
		),
	)
);

flx_gql_type(
	'FlxAdminSettings',
	array(
		'description' => __( 'Settings › Admin theme.', 'flx-admin' ),
		'fields'      => array(
			'enabled'     => flxa_gql_f( 'Boolean!', __( 'The admin theme is on (off restores the standard WordPress dashboard).', 'flx-admin' ), 'enabled' ),
			'brand'       => flxa_gql_f( 'String!', __( 'Name at the top of the side menu; empty = the site name.', 'flx-admin' ), 'brand' ),
			'subtitle'    => flxa_gql_f( 'String!', __( 'Line under the name; empty = "Admin".', 'flx-admin' ), 'subtitle' ),
			'logo'        => array(
				'type'        => 'FlxFile',
				'description' => __( 'The logo chosen here; empty when the site icon or the Flux Admin icon is used.', 'flx-admin' ),
				'resolve'     => static function ( $src ): ?array {
					$s  = is_array( $src ) ? $src : array();
					$id = FLXA_Util::int( $s['logo_id'] ?? 0 );
					if ( ! $id || ! wp_attachment_is_image( $id ) ) {
						return null;
					}
					$path = (string) get_attached_file( $id );
					return flx_gql_file(
						array(
							'id'    => (string) $id,
							'name'  => basename( $path ),
							'mime'  => FLXA_Util::str( get_post_mime_type( $id ), 'image/png' ),
							'size'  => is_file( $path ) ? (int) filesize( $path ) : 0,
							'url'   => FLXA_Util::str( wp_get_attachment_url( $id ) ),
							'thumb' => FLXA_Util::str( wp_get_attachment_image_url( $id, array( 96, 96 ) ) ),
						)
					);
				},
			),
			'logoUrl'     => flxa_gql_f( 'String!', __( 'The logo the panel shows (chosen logo, institution icon, site icon or the Flux Admin icon).', 'flx-admin' ), 'logo_url' ),
			'accent'      => flxa_gql_f( 'String!', __( 'Accent color (hex); the theme color when none is chosen.', 'flx-admin' ), 'accent' ),
			'mode'        => flxa_gql_f( 'FlxAdminMode!', __( 'Default light/dark choice for everyone.', 'flx-admin' ), 'mode' ),
			'darkExclude' => flxa_gql_f( 'String!', __( 'Screen IDs or body classes (one per line) that stay light.', 'flx-admin' ), 'dark_exclude' ),
			'lineIcons'   => flxa_gql_f( 'Boolean!', __( 'Line icons in WordPress menus.', 'flx-admin' ), 'line_icons' ),
			'logoStyle'   => flxa_gql_f( 'FlxAdminLogoStyle!', __( 'Style of the FluxWP package logos.', 'flx-admin' ), 'logo_style' ),
			'bottomBar'   => flxa_gql_f( 'Boolean!', __( 'Bottom bar on mobile.', 'flx-admin' ), 'bottom_bar' ),
			'appRail'     => flxa_gql_f( 'Boolean!', __( 'WhatsApp and Tasks open next to the side menu, which shrinks to icons.', 'flx-admin' ), 'app_rail' ),
			'appSwitcher' => flxa_gql_f( 'Boolean!', __( 'App switcher on the WhatsApp and Tasks screens on phones.', 'flx-admin' ), 'app_switcher' ),
			'fastNav'     => flxa_gql_f( 'Boolean!', __( 'Fast navigation: the clicked menu page is prepared in advance.', 'flx-admin' ), 'fast_nav' ),
			'palette'     => flxa_gql_f( 'Boolean!', __( 'Command palette (Ctrl+K).', 'flx-admin' ), 'palette' ),
			'login'       => flxa_gql_f( 'Boolean!', __( 'Style the login screen too.', 'flx-admin' ), 'login' ),
			'heartbeat'   => flxa_gql_f( 'Int!', __( 'Seconds between live counter refreshes: 30, 60 or 120.', 'flx-admin' ), 'heartbeat' ),
			'theme'       => flxa_gql_f( 'FlxAdminTheme!', __( 'Colors, font, corners and density.', 'flx-admin' ), 'theme' ),
			'dashHeader'  => flxa_gql_f( 'Boolean!', __( 'Greeting, date and summary tiles at the top of the dashboard.', 'flx-admin' ), 'dash_header' ),
			'dashStats'   => flxa_gql_f( '[FlxAdminDashStat!]!', __( 'Summary tiles that show; people only see tiles they have permission for.', 'flx-admin' ), 'dash_stats' ),
			'quickLinks'  => flxa_gql_f( 'String!', __( 'Extra quick actions, one "Label|Address" per line.', 'flx-admin' ), 'quick_links' ),
			'pwa'         => flxa_gql_f( 'Boolean!', __( 'The dashboard can be installed as an app (PWA).', 'flx-admin' ), 'pwa' ),
			'pwaName'     => flxa_gql_f( 'String!', __( 'App name; empty = the panel name.', 'flx-admin' ), 'pwa_name' ),
			'pwaShort'    => flxa_gql_f( 'String!', __( 'Short app name under the icon (20 characters at most); empty = made from the name.', 'flx-admin' ), 'pwa_short' ),
		),
	)
);

flx_gql_type(
	'FlxAdminDashStatChoice',
	array(
		'description' => __( 'A summary tile the dashboard can show.', 'flx-admin' ),
		'fields'      => array(
			'key'   => flxa_gql_f( 'FlxAdminDashStat!', __( 'Tile.', 'flx-admin' ), 'key' ),
			'label' => flxa_gql_f( 'String!', __( 'Tile name.', 'flx-admin' ), 'label' ),
		),
	)
);

flx_gql_type(
	'FlxAdminColorDefaults',
	array(
		'description' => __( 'Colors used when none is chosen.', 'flx-admin' ),
		'fields'      => array(
			'light' => flxa_gql_f( 'FlxAdminColors!', __( 'Light theme.', 'flx-admin' ), 'light' ),
			'dark'  => flxa_gql_f( 'FlxAdminColors!', __( 'Dark theme.', 'flx-admin' ), 'dark' ),
		),
	)
);

flx_gql_type(
	'FlxAdminSettingsConfig',
	array(
		'description' => __( 'Values around the Admin theme settings: defaults, availability and links.', 'flx-admin' ),
		'fields'      => array(
			'siteName'         => flxa_gql_f( 'String!', __( 'Site name (the panel name when the name setting is empty).', 'flx-admin' ), 'site_name' ),
			'defaultAccent'    => flxa_gql_f( 'String!', __( 'Accent color of the site theme.', 'flx-admin' ), 'default_accent' ),
			'colorDefaults'    => flxa_gql_f( 'FlxAdminColorDefaults!', __( 'Theme colors used when none is chosen.', 'flx-admin' ), 'color_defaults' ),
			'dashStatChoices'  => flxa_gql_f( '[FlxAdminDashStatChoice!]!', __( 'Every summary tile the dashboard can show.', 'flx-admin' ), 'dash_stat_choices' ),
			'pwaPossible'      => flxa_gql_f( 'Boolean!', __( 'The site is served over HTTPS, so browsers allow installing.', 'flx-admin' ), 'pwa_possible' ),
			'pwaIconUrl'       => flxa_gql_f( 'String!', __( 'App icon (the site icon, or the Flux Admin icon).', 'flx-admin' ), 'pwa_icon_url' ),
			'pwaDefaultName'   => flxa_gql_f( 'String!', __( 'App name used when the setting is empty.', 'flx-admin' ), 'pwa_default_name' ),
			'pwaDefaultShort'  => flxa_gql_f( 'String!', __( 'Short app name used when the setting is empty.', 'flx-admin' ), 'pwa_default_short' ),
			'systemUrl'        => flxa_gql_f( 'String', __( 'Page of the system check when Flux Speed hosts it; empty when it is on this page.', 'flx-admin' ), 'system_url' ),
		),
	)
);

flx_gql_query(
	'flxAdminSettings',
	array(
		'type'        => 'FlxAdminSettings!',
		'description' => __( 'The Admin theme settings.', 'flx-admin' ),
		'cap'         => 'manage_options',
		'resolve'     => static function (): array {
			flx_gql_cache_tag( FLXA_Settings_Service::CACHE_TAG );
			return FLXA_Settings_Service::get();
		},
	)
);

flx_gql_query(
	'flxAdminSettingsConfig',
	array(
		'type'        => 'FlxAdminSettingsConfig!',
		'description' => __( 'Defaults, availability and links around the Admin theme settings.', 'flx-admin' ),
		'cap'         => 'manage_options',
		'resolve'     => static function (): array {
			flx_gql_cache_tag( FLXA_Settings_Service::CACHE_TAG );
			return FLXA_Settings_Service::meta();
		},
	)
);

flx_gql_input(
	'FlxAdminColorsInput',
	array(
		'description' => __( 'Colors of one theme. Colors you leave out stay as they are; the default color means "not customised".', 'flx-admin' ),
		'fields'      => array(
			'bg'      => array( 'type' => 'String', 'description' => __( 'Page background (hex).', 'flx-admin' ) ),
			'side'    => array( 'type' => 'String', 'description' => __( 'Side menu background (hex).', 'flx-admin' ) ),
			'surface' => array( 'type' => 'String', 'description' => __( 'Card background (hex).', 'flx-admin' ) ),
			'text'    => array( 'type' => 'String', 'description' => __( 'Text color (hex).', 'flx-admin' ) ),
		),
	)
);

flx_gql_input(
	'FlxAdminThemeInput',
	array(
		'description' => __( 'Changes to the look. Fields you leave out stay as they are.', 'flx-admin' ),
		'fields'      => array(
			'radius'     => array( 'type' => 'FlxAdminRadius', 'description' => __( 'Corner roundness.', 'flx-admin' ) ),
			'font'       => array( 'type' => 'FlxAdminFont', 'description' => __( 'Font family choice.', 'flx-admin' ) ),
			'fontCustom' => array( 'type' => 'String', 'description' => __( 'Font name for the custom choice (a font installed on the computer or loaded on the site).', 'flx-admin' ) ),
			'fontSize'   => array( 'type' => 'Int', 'description' => __( 'Base font size in pixels: 13, 14 or 15.', 'flx-admin' ) ),
			'density'    => array( 'type' => 'FlxAdminDensity', 'description' => __( 'Menu row height.', 'flx-admin' ) ),
			'light'      => array( 'type' => 'FlxAdminColorsInput', 'description' => __( 'Light theme colors.', 'flx-admin' ) ),
			'dark'       => array( 'type' => 'FlxAdminColorsInput', 'description' => __( 'Dark theme colors.', 'flx-admin' ) ),
		),
	)
);

/**
 * Settings mutation input → service patch (only the fields that were sent).
 *
 * @param array<string,mixed> $in Mutation input.
 * @return array<string,mixed>
 */
function flxa_gql_settings_patch( array $in ): array {
	$map   = array(
		'enabled'     => 'enabled',
		'brand'       => 'brand',
		'subtitle'    => 'subtitle',
		'logoId'      => 'logo_id',
		'accent'      => 'accent',
		'mode'        => 'mode',
		'darkExclude' => 'dark_exclude',
		'lineIcons'   => 'line_icons',
		'logoStyle'   => 'logo_style',
		'bottomBar'   => 'bottom_bar',
		'appRail'     => 'app_rail',
		'appSwitcher' => 'app_switcher',
		'fastNav'     => 'fast_nav',
		'palette'     => 'palette',
		'login'       => 'login',
		'heartbeat'   => 'heartbeat',
		'dashHeader'  => 'dash_header',
		'dashStats'   => 'dash_stats',
		'quickLinks'  => 'quick_links',
		'pwa'         => 'pwa',
		'pwaName'     => 'pwa_name',
		'pwaShort'    => 'pwa_short',
	);
	$patch = array();
	foreach ( $map as $gql => $key ) {
		if ( array_key_exists( $gql, $in ) && null !== $in[ $gql ] ) {
			$patch[ $key ] = $in[ $gql ];
		}
	}
	if ( isset( $in['theme'] ) && is_array( $in['theme'] ) ) {
		$t     = FLXA_Util::arr( $in['theme'] );
		$theme = array();
		foreach ( array(
			'radius'     => 'radius',
			'font'       => 'font',
			'fontCustom' => 'font_custom',
			'fontSize'   => 'font_size',
			'density'    => 'density',
			'light'      => 'light',
			'dark'       => 'dark',
		) as $gql => $key ) {
			if ( array_key_exists( $gql, $t ) && null !== $t[ $gql ] ) {
				$theme[ $key ] = is_array( $t[ $gql ] ) ? FLXA_Util::arr( $t[ $gql ] ) : $t[ $gql ];
			}
		}
		$patch['theme'] = $theme;
	}
	return $patch;
}

flx_gql_mutation(
	'flxAdminSettingsUpdate',
	array(
		'description' => __( 'Changes Admin theme settings. Fields you leave out stay as they are; nothing is written when any field is invalid.', 'flx-admin' ),
		'cap'         => 'manage_options',
		'input'       => array(
			'enabled'      => array( 'type' => 'Boolean', 'description' => __( 'Turn the admin theme on or off.', 'flx-admin' ) ),
			'brand'        => array( 'type' => 'String', 'description' => __( 'Name at the top of the side menu; empty = the site name.', 'flx-admin' ) ),
			'subtitle'     => array( 'type' => 'String', 'description' => __( 'Line under the name; empty = "Admin".', 'flx-admin' ) ),
			'logoId'       => array( 'type' => 'Int', 'description' => __( 'Media library image to use as the logo; 0 removes the logo.', 'flx-admin' ) ),
			'logoUploadId' => array( 'type' => 'String', 'description' => __( 'Upload ID of a logo sent to /wp-json/flx/v1/upload (context flx-admin/logo); it joins the media library and becomes the logo.', 'flx-admin' ) ),
			'accent'       => array( 'type' => 'String', 'description' => __( 'Accent color (hex); empty or the theme color follows the site theme.', 'flx-admin' ) ),
			'mode'         => array( 'type' => 'FlxAdminMode', 'description' => __( 'Default light/dark choice for everyone.', 'flx-admin' ) ),
			'darkExclude'  => array( 'type' => 'String', 'description' => __( 'Screen IDs or body classes (one per line) that stay light.', 'flx-admin' ) ),
			'lineIcons'    => array( 'type' => 'Boolean', 'description' => __( 'Line icons in WordPress menus.', 'flx-admin' ) ),
			'logoStyle'    => array( 'type' => 'FlxAdminLogoStyle', 'description' => __( 'Style of the FluxWP package logos.', 'flx-admin' ) ),
			'bottomBar'    => array( 'type' => 'Boolean', 'description' => __( 'Bottom bar on mobile.', 'flx-admin' ) ),
			'appRail'      => array( 'type' => 'Boolean', 'description' => __( 'WhatsApp and Tasks open next to the side menu.', 'flx-admin' ) ),
			'appSwitcher'  => array( 'type' => 'Boolean', 'description' => __( 'App switcher on the WhatsApp and Tasks screens on phones.', 'flx-admin' ) ),
			'fastNav'      => array( 'type' => 'Boolean', 'description' => __( 'Fast navigation.', 'flx-admin' ) ),
			'palette'      => array( 'type' => 'Boolean', 'description' => __( 'Command palette (Ctrl+K).', 'flx-admin' ) ),
			'login'        => array( 'type' => 'Boolean', 'description' => __( 'Style the login screen too.', 'flx-admin' ) ),
			'heartbeat'    => array( 'type' => 'Int', 'description' => __( 'Seconds between live counter refreshes: 30, 60 or 120.', 'flx-admin' ) ),
			'theme'        => array( 'type' => 'FlxAdminThemeInput', 'description' => __( 'Colors, font, corners and density.', 'flx-admin' ) ),
			'dashHeader'   => array( 'type' => 'Boolean', 'description' => __( 'Greeting, date and summary tiles at the top of the dashboard.', 'flx-admin' ) ),
			'dashStats'    => array( 'type' => '[FlxAdminDashStat!]', 'description' => __( 'Summary tiles that show.', 'flx-admin' ) ),
			'quickLinks'   => array( 'type' => 'String', 'description' => __( 'Extra quick actions, one "Label|Address" per line.', 'flx-admin' ) ),
			'pwa'          => array( 'type' => 'Boolean', 'description' => __( 'Allow installing the dashboard as an app.', 'flx-admin' ) ),
			'pwaName'      => array( 'type' => 'String', 'description' => __( 'App name; empty = the panel name.', 'flx-admin' ) ),
			'pwaShort'     => array( 'type' => 'String', 'description' => __( 'Short app name (20 characters at most).', 'flx-admin' ) ),
		),
		'output'      => array(
			'settings' => array(
				'type'        => 'FlxAdminSettings!',
				'description' => __( 'The settings after the change.', 'flx-admin' ),
				'resolve'     => static fn(): array => FLXA_Settings_Service::get(),
			),
			'config'   => array(
				'type'        => 'FlxAdminSettingsConfig!',
				'description' => __( 'Defaults, availability and links after the change.', 'flx-admin' ),
				'resolve'     => static fn(): array => FLXA_Settings_Service::meta(),
			),
		),
		'resolve'     => static function ( array $input ) {
			/** @var array<string, mixed> $input */
			$patch = flxa_gql_settings_patch( $input );
			$up    = isset( $input['logoUploadId'] ) ? FLXA_Util::str( $input['logoUploadId'] ) : '';
			if ( '' !== $up ) {
				$file = flx_gql_upload_take( $up, 'flx-admin/logo' );
				if ( $file instanceof WP_Error ) {
					return $file;
				}
				$id = FLXA_Settings_Service::logo_from_upload( $file );
				if ( $id instanceof WP_Error ) {
					return $id;
				}
				$patch['logo_id'] = $id;
			}
			$r = FLXA_Settings_Service::update( $patch );
			return $r instanceof WP_Error ? $r : array();
		},
	)
);

/* ───────────────────────── Side menu ───────────────────────── */

flx_gql_type(
	'FlxAdminMenuSub',
	array(
		'description' => __( 'A submenu item.', 'flx-admin' ),
		'fields'      => array(
			'slug'       => flxa_gql_f( 'String!', __( 'Submenu link (what the layout stores).', 'flx-admin' ), 'slug' ),
			'label'      => flxa_gql_f( 'String!', __( 'Submenu name.', 'flx-admin' ), 'label' ),
			'capability' => flxa_gql_f( 'String!', __( 'Permission it needs.', 'flx-admin' ), 'cap' ),
		),
	)
);

flx_gql_type(
	'FlxAdminMenuItem',
	array(
		'description' => __( 'A menu item the administrator sees (the editor\'s list).', 'flx-admin' ),
		'fields'      => array(
			'slug'       => flxa_gql_f( 'String!', __( 'Menu link (what the layout stores).', 'flx-admin' ), 'slug' ),
			'label'      => flxa_gql_f( 'String!', __( 'Menu name.', 'flx-admin' ), 'label' ),
			'capability' => flxa_gql_f( 'String!', __( 'Permission it needs.', 'flx-admin' ), 'cap' ),
			'icon'       => flxa_gql_f( 'String!', __( 'Icon key; empty when the item has none.', 'flx-admin' ), 'icon' ),
			'iconSvg'    => array(
				'type'        => 'String!',
				'description' => __( 'Inner markup of the 24×24 line icon (shapes only: path, circle, rect, line, polyline).', 'flx-admin' ),
				'resolve'     => static function ( $src ): string {
					$k = is_array( $src ) ? FLXA_Util::str( $src['icon'] ?? '' ) : '';
					/** @var array<string, string> $p */
					$p = flxa_icon_paths();
					return $p[ '' !== $k && isset( $p[ $k ] ) ? $k : 'dot' ];
				},
			),
			'url'        => flxa_gql_f( 'String!', __( 'Where the item opens.', 'flx-admin' ), 'url' ),
			'submenu'    => flxa_gql_f( '[FlxAdminMenuSub!]!', __( 'Submenu items.', 'flx-admin' ), 'sub' ),
		),
	)
);

flx_gql_type(
	'FlxAdminMenuRole',
	array(
		'description' => __( 'A role of the menu editor.', 'flx-admin' ),
		'fields'      => array(
			'key'       => flxa_gql_f( 'String!', __( 'Role key; "_default" is the layout of all other roles.', 'flx-admin' ), 'key' ),
			'label'     => flxa_gql_f( 'String!', __( 'Role name.', 'flx-admin' ), 'label' ),
			'userCount' => flxa_gql_f( 'Int!', __( 'People with the role.', 'flx-admin' ), 'users' ),
		),
	)
);

flx_gql_type(
	'FlxAdminMenuPlatform',
	array(
		'description' => __( 'A place a menu item can be hidden on (web, mobile app, desktop app).', 'flx-admin' ),
		'fields'      => array(
			'key'   => flxa_gql_f( 'String!', __( 'Platform key.', 'flx-admin' ), 'key' ),
			'label' => flxa_gql_f( 'String!', __( 'Platform name.', 'flx-admin' ), 'label' ),
		),
	)
);

flx_gql_type(
	'FlxAdminMenuEntry',
	array(
		'description' => __( 'One row of a menu layout: a group heading or a menu item.', 'flx-admin' ),
		'fields'      => array(
			'type'         => flxa_gql_f( 'FlxAdminMenuEntryType!', __( 'Group heading or menu item.', 'flx-admin' ), 'type' ),
			'label'        => flxa_gql_f( 'String!', __( 'Group name, or the item\'s own name; empty = the original name.', 'flx-admin' ), 'label' ),
			'collapsed'    => flxa_gql_f( 'Boolean!', __( 'Group only: starts collapsed.', 'flx-admin' ), 'collapsed' ),
			'slug'         => array(
				'type'        => 'String',
				'description' => __( 'Item only: the menu link; empty for a group.', 'flx-admin' ),
				'resolve'     => static function ( $src ): ?string {
					$s = is_array( $src ) ? FLXA_Util::str( $src['slug'] ?? '' ) : '';
					return '' !== $s ? $s : null;
				},
			),
			'hidden'       => flxa_gql_f( 'Boolean!', __( 'Item only: hidden from the menu (permissions do not change).', 'flx-admin' ), 'hidden' ),
			'blocked'      => flxa_gql_f( 'Boolean!', __( 'Item only: access is closed; it is not in the menu and opening it by URL shows "no permission".', 'flx-admin' ), 'blocked' ),
			'subHidden'    => flxa_gql_f( '[String!]!', __( 'Item only: submenu links hidden from the menu.', 'flx-admin' ), 'sub_hidden' ),
			'platformsOff' => flxa_gql_f( '[String!]!', __( 'Item only: platforms the item is not shown on.', 'flx-admin' ), 'platforms_off' ),
		),
	)
);

flx_gql_type(
	'FlxAdminMenuLayout',
	array(
		'description' => __( 'The side menu and bottom bar of one role.', 'flx-admin' ),
		'fields'      => array(
			'role'    => flxa_gql_f( 'String!', __( 'Role key ("_default" = all other roles).', 'flx-admin' ), 'role' ),
			'custom'  => flxa_gql_f( 'Boolean!', __( 'The role has its own layout; false = the default layout.', 'flx-admin' ), 'custom' ),
			'entries' => flxa_gql_f( '[FlxAdminMenuEntry!]!', __( 'Rows in order; items new in the menu are at the end under "Other".', 'flx-admin' ), 'entries' ),
			'bottom'  => flxa_gql_f( '[String!]!', __( 'Mobile bottom bar items (up to 4 menu links).', 'flx-admin' ), 'bottom' ),
			'access'  => flxa_gql_f( '[String!]!', __( 'Menu links this role has permission to open.', 'flx-admin' ), 'access' ),
		),
	)
);

flx_gql_type(
	'FlxAdminMenuDefaults',
	array(
		'description' => __( 'The default layout ("reset this role").', 'flx-admin' ),
		'fields'      => array(
			'entries' => flxa_gql_f( '[FlxAdminMenuEntry!]!', __( 'Default rows.', 'flx-admin' ), 'entries' ),
			'bottom'  => flxa_gql_f( '[String!]!', __( 'Default bottom bar.', 'flx-admin' ), 'bottom' ),
		),
	)
);

flx_gql_type(
	'FlxAdminMenuEditor',
	array(
		'description' => __( 'Everything the menu editor needs.', 'flx-admin' ),
		'fields'      => array(
			'catalog'     => array(
				'type'        => '[FlxAdminMenuItem!]!',
				'description' => __( 'The menu as the administrator sees it. It is rebuilt on the next admin page load after plugins change.', 'flx-admin' ),
				'resolve'     => static fn(): array => FLXA_Menu_Service::catalog(),
			),
			'roles'       => array(
				'type'        => '[FlxAdminMenuRole!]!',
				'description' => __( 'Roles that can have their own layout, "all other roles" first.', 'flx-admin' ),
				'resolve'     => static fn(): array => FLXA_Menu_Service::roles(),
			),
			'layouts'     => array(
				'type'        => '[FlxAdminMenuLayout!]!',
				'description' => __( 'The layout of every role.', 'flx-admin' ),
				'resolve'     => static fn(): array => FLXA_Menu_Service::layouts(),
			),
			'defaults'    => array(
				'type'        => 'FlxAdminMenuDefaults!',
				'description' => __( 'The default layout.', 'flx-admin' ),
				'resolve'     => static fn(): array => FLXA_Menu_Service::defaults(),
			),
			'platforms'   => array(
				'type'        => '[FlxAdminMenuPlatform!]!',
				'description' => __( 'Platforms an item can be hidden on; empty without the app package.', 'flx-admin' ),
				'resolve'     => static fn(): array => FLXA_Menu_Service::platforms(),
			),
			'unblockable' => array(
				'type'        => '[String!]!',
				'description' => __( 'Menu links whose access cannot be closed (no lock-out).', 'flx-admin' ),
				'resolve'     => static fn(): array => FLXA_Menu_Service::unblockable(),
			),
		),
	)
);

flx_gql_query(
	'flxAdminMenuEditor',
	array(
		'type'        => 'FlxAdminMenuEditor!',
		'description' => __( 'The role layouts of the side menu and the bottom bar.', 'flx-admin' ),
		'cap'         => 'manage_options',
		'resolve'     => static function (): array {
			flx_gql_cache_tag( FLXA_Menu_Service::CACHE_TAG );
			return array();
		},
	)
);

flx_gql_input(
	'FlxAdminMenuEntryInput',
	array(
		'description' => __( 'One row of a menu layout.', 'flx-admin' ),
		'fields'      => array(
			'type'         => array( 'type' => 'FlxAdminMenuEntryType!', 'description' => __( 'Group heading or menu item.', 'flx-admin' ) ),
			'label'        => array( 'type' => 'String', 'description' => __( 'Group name, or the item\'s own name (40 characters at most); empty = the original name.', 'flx-admin' ) ),
			'collapsed'    => array( 'type' => 'Boolean', 'description' => __( 'Group only: starts collapsed.', 'flx-admin' ) ),
			'slug'         => array( 'type' => 'String', 'description' => __( 'Item only: the menu link. Links that are not in the menu are dropped.', 'flx-admin' ) ),
			'hidden'       => array( 'type' => 'Boolean', 'description' => __( 'Item only: hide from the menu.', 'flx-admin' ) ),
			'blocked'      => array( 'type' => 'Boolean', 'description' => __( 'Item only: close access (not for the Administrator role or the links in unblockable).', 'flx-admin' ) ),
			'subHidden'    => array( 'type' => '[String!]', 'description' => __( 'Item only: submenu links to hide.', 'flx-admin' ) ),
			'platformsOff' => array( 'type' => '[String!]', 'description' => __( 'Item only: platforms the item is not shown on.', 'flx-admin' ) ),
		),
	)
);

flx_gql_input(
	'FlxAdminMenuLayoutInput',
	array(
		'description' => __( 'A layout to save for one role.', 'flx-admin' ),
		'fields'      => array(
			'role'    => array( 'type' => 'String!', 'description' => __( 'Role key ("_default" = all other roles).', 'flx-admin' ) ),
			'entries' => array( 'type' => '[FlxAdminMenuEntryInput!]!', 'description' => __( 'Rows in order (300 at most).', 'flx-admin' ) ),
			'bottom'  => array( 'type' => '[String!]', 'description' => __( 'Mobile bottom bar items (4 at most).', 'flx-admin' ) ),
		),
	)
);

flx_gql_mutation(
	'flxAdminMenuLayoutsSave',
	array(
		'description' => __( 'Saves the layouts of some roles and sends others back to the default; roles you leave out stay as they are.', 'flx-admin' ),
		'cap'         => 'manage_options',
		'input'       => array(
			'layouts' => array( 'type' => '[FlxAdminMenuLayoutInput!]', 'description' => __( 'Layouts to save.', 'flx-admin' ) ),
			'reset'   => array( 'type' => '[String!]', 'description' => __( 'Roles that go back to the default layout.', 'flx-admin' ) ),
		),
		'output'      => array(
			'layouts' => array(
				'type'        => '[FlxAdminMenuLayout!]!',
				'description' => __( 'The layout of every role after the change.', 'flx-admin' ),
				'resolve'     => static fn(): array => FLXA_Menu_Service::layouts(),
			),
		),
		'resolve'     => static function ( array $input ) {
			$layouts = array();
			foreach ( is_array( $input['layouts'] ?? null ) ? $input['layouts'] : array() as $l ) {
				$l       = FLXA_Util::arr( $l );
				$entries = array();
				foreach ( is_array( $l['entries'] ?? null ) ? $l['entries'] : array() as $e ) {
					$e         = FLXA_Util::arr( $e );
					$entries[] = array(
						'type'          => FLXA_Util::str( $e['type'] ?? '' ),
						'label'         => FLXA_Util::str( $e['label'] ?? '' ),
						'collapsed'     => ! empty( $e['collapsed'] ),
						'slug'          => FLXA_Util::str( $e['slug'] ?? '' ),
						'hidden'        => ! empty( $e['hidden'] ),
						'blocked'       => ! empty( $e['blocked'] ),
						'sub_hidden'    => FLXA_Util::strings( $e['subHidden'] ?? array() ),
						'platforms_off' => FLXA_Util::strings( $e['platformsOff'] ?? array() ),
					);
				}
				$layouts[] = array(
					'role'    => FLXA_Util::str( $l['role'] ?? '' ),
					'entries' => $entries,
					'bottom'  => FLXA_Util::strings( $l['bottom'] ?? array() ),
				);
			}
			$r = FLXA_Menu_Service::save( $layouts, FLXA_Util::strings( $input['reset'] ?? array() ) );
			return $r instanceof WP_Error ? $r : array();
		},
	)
);

/* ───────────────────────── Permissions table ───────────────────────── */

flx_gql_type(
	'FlxAdminCapRow',
	array(
		'description' => __( 'A permission of the table.', 'flx-admin' ),
		'fields'      => array(
			'key'   => flxa_gql_f( 'String!', __( 'Permission name, e.g. edit_posts.', 'flx-admin' ), 'key' ),
			'label' => flxa_gql_f( 'String!', __( 'What it allows; empty for permissions without a readable name.', 'flx-admin' ), 'label' ),
		),
	)
);

flx_gql_type(
	'FlxAdminCapGroup',
	array(
		'description' => __( 'A group of the permissions table.', 'flx-admin' ),
		'fields'      => array(
			'key'          => flxa_gql_f( 'String!', __( 'Group key.', 'flx-admin' ), 'key' ),
			'label'        => flxa_gql_f( 'String!', __( 'Group name.', 'flx-admin' ), 'label' ),
			'capabilities' => flxa_gql_f( '[FlxAdminCapRow!]!', __( 'Permissions of the group.', 'flx-admin' ), 'caps' ),
		),
	)
);

flx_gql_type(
	'FlxAdminCapRole',
	array(
		'description' => __( 'A role column of the permissions table.', 'flx-admin' ),
		'fields'      => array(
			'key'             => flxa_gql_f( 'String!', __( 'Role key.', 'flx-admin' ), 'key' ),
			'label'           => flxa_gql_f( 'String!', __( 'Role name.', 'flx-admin' ), 'label' ),
			'userCount'       => flxa_gql_f( 'Int!', __( 'People with the role.', 'flx-admin' ), 'users' ),
			'isAdministrator' => flxa_gql_f( 'Boolean!', __( 'The Administrator role; it cannot be changed.', 'flx-admin' ), 'is_admin' ),
			'granted'         => flxa_gql_f( '[String!]!', __( 'Table permissions this role has.', 'flx-admin' ), 'granted' ),
		),
	)
);

flx_gql_type(
	'FlxAdminCaps',
	array(
		'description' => __( 'The role × permission table (real WordPress permissions).', 'flx-admin' ),
		'fields'      => array(
			'roles'    => array(
				'type'        => '[FlxAdminCapRole!]!',
				'description' => __( 'Role columns.', 'flx-admin' ),
				'resolve'     => static fn(): array => FLXA_Caps_Service::roles(),
			),
			'groups'   => array(
				'type'        => '[FlxAdminCapGroup!]!',
				'description' => __( 'Permission rows by group.', 'flx-admin' ),
				'resolve'     => static fn(): array => FLXA_Caps_Service::groups(),
			),
			'backupAt' => array(
				'type'        => 'FlxDateTime',
				'description' => __( 'When the backup of the permissions before the first change was taken; empty without a backup.', 'flx-admin' ),
				'resolve'     => static function (): ?array {
					$t = FLXA_Caps_Service::backup_time();
					return null === $t ? null : flx_gql_datetime( $t );
				},
			),
		),
	)
);

flx_gql_query(
	'flxAdminCaps',
	array(
		'type'        => 'FlxAdminCaps!',
		'description' => __( 'The role × permission table.', 'flx-admin' ),
		'cap'         => 'manage_options',
		'resolve'     => static function (): array {
			flx_gql_cache_tag( FLXA_Caps_Service::CACHE_TAG );
			return array();
		},
	)
);

flx_gql_input(
	'FlxAdminCapsRoleInput',
	array(
		'description' => __( 'The table permissions one role should have.', 'flx-admin' ),
		'fields'      => array(
			'role'         => array( 'type' => 'String!', 'description' => __( 'Role key. The Administrator role is skipped.', 'flx-admin' ) ),
			'capabilities' => array( 'type' => '[String!]!', 'description' => __( 'Every table permission that should be on; the other table permissions are turned off.', 'flx-admin' ) ),
		),
	)
);

flx_gql_mutation(
	'flxAdminCapsSave',
	array(
		'description' => __( 'Changes the permissions of some roles. These are real permissions. The first change backs everything up.', 'flx-admin' ),
		'cap'         => 'manage_options',
		'input'       => array(
			'roles' => array( 'type' => '[FlxAdminCapsRoleInput!]!', 'description' => __( 'Roles to change; the others stay as they are.', 'flx-admin' ) ),
		),
		'output'      => array(
			'caps' => array(
				'type'        => 'FlxAdminCaps!',
				'description' => __( 'The table after the change.', 'flx-admin' ),
				'resolve'     => static fn(): array => array(),
			),
		),
		'resolve'     => static function ( array $input ) {
			$rows = array();
			foreach ( is_array( $input['roles'] ?? null ) ? $input['roles'] : array() as $r ) {
				$r      = FLXA_Util::arr( $r );
				$rows[] = array(
					'role'         => FLXA_Util::str( $r['role'] ?? '' ),
					'capabilities' => FLXA_Util::strings( $r['capabilities'] ?? array() ),
				);
			}
			$r = FLXA_Caps_Service::save( $rows );
			return $r instanceof WP_Error ? $r : array( 'caps' => array() );
		},
	)
);

flx_gql_mutation(
	'flxAdminCapsRestore',
	array(
		'description' => __( 'Returns every role to the backup taken before the first change, then removes the backup.', 'flx-admin' ),
		'cap'         => 'manage_options',
		'input'       => array(
			'confirm' => array( 'type' => 'Boolean!', 'description' => __( 'Must be true: every role goes back to its state before the first change.', 'flx-admin' ) ),
		),
		'output'      => array(
			'caps' => array(
				'type'        => 'FlxAdminCaps!',
				'description' => __( 'The table after the restore.', 'flx-admin' ),
				'resolve'     => static fn(): array => array(),
			),
		),
		'resolve'     => static function ( array $input ) {
			if ( empty( $input['confirm'] ) ) {
				return FLXA_Util::error( 'flxa_caps_confirm', __( 'Confirm the restore.', 'flx-admin' ), 'INVALID_INPUT', 'confirm' );
			}
			$r = FLXA_Caps_Service::restore();
			return $r instanceof WP_Error ? $r : array( 'caps' => array() );
		},
	)
);

/* ───────────────────────── The signed-in user's own choices ───────────────────────── */

flx_gql_query(
	'flxAdminMode',
	array(
		'type'        => 'FlxAdminMode!',
		'description' => __( 'The signed-in user\'s light/dark choice (the site default when they have not chosen).', 'flx-admin' ),
		'cap'         => 'logged_in',
		'resolve'     => static fn(): string => FLXA_User_Service::mode(),
	)
);

flx_gql_mutation(
	'flxAdminModeSet',
	array(
		'description' => __( 'Saves the signed-in user\'s light/dark choice.', 'flx-admin' ),
		'cap'         => 'logged_in',
		'input'       => array(
			'mode' => array( 'type' => 'FlxAdminMode!', 'description' => __( 'Automatic (device setting), light or dark.', 'flx-admin' ) ),
		),
		'output'      => array(
			'mode' => array(
				'type'        => 'FlxAdminMode',
				'description' => __( 'The saved choice.', 'flx-admin' ),
			),
		),
		'resolve'     => static function ( array $input ) {
			$r = FLXA_User_Service::set_mode( FLXA_Util::str( $input['mode'] ?? '' ) );
			return $r instanceof WP_Error ? $r : array( 'mode' => $r );
		},
	)
);

flx_gql_type(
	'FlxAdminUserMenu',
	array(
		'description' => __( 'A person\'s own menu choices (right-click menu).', 'flx-admin' ),
		'fields'      => array(
			'pins'    => flxa_gql_f( '[String!]!', __( 'Menu links pinned at the top, in order.', 'flx-admin' ), 'pins' ),
			'away'    => flxa_gql_f( '[String!]!', __( 'Menu links moved under "Other".', 'flx-admin' ), 'away' ),
			'subAway' => flxa_gql_f( '[String!]!', __( 'Submenu links moved under "Other".', 'flx-admin' ), 'sub_away' ),
			'subKeep' => flxa_gql_f( '[String!]!', __( 'Submenu links kept in place although they would move under "Other" automatically.', 'flx-admin' ), 'sub_keep' ),
		),
	)
);

flx_gql_query(
	'flxAdminUserMenu',
	array(
		'type'        => 'FlxAdminUserMenu!',
		'description' => __( 'The signed-in user\'s pinned and moved menu items.', 'flx-admin' ),
		'cap'         => 'logged_in',
		'resolve'     => static fn(): array => FLXA_Menu_Service::user_menu(),
	)
);

flx_gql_mutation(
	'flxAdminUserMenuSet',
	array(
		'description' => __( 'Pin, unpin or move one menu item of the signed-in user, or reorder the pins.', 'flx-admin' ),
		'cap'         => 'logged_in',
		'input'       => array(
			'op'    => array( 'type' => 'FlxAdminMenuOp!', 'description' => __( 'What to do.', 'flx-admin' ) ),
			'slug'  => array( 'type' => 'String', 'description' => __( 'The menu (or submenu) link; not used for ORDER.', 'flx-admin' ) ),
			'order' => array( 'type' => '[String!]', 'description' => __( 'ORDER only: the pinned links in the new order.', 'flx-admin' ) ),
		),
		'output'      => array(
			'menu' => array(
				'type'        => 'FlxAdminUserMenu',
				'description' => __( 'The user\'s menu choices after the change.', 'flx-admin' ),
			),
		),
		'resolve'     => static function ( array $input ) {
			$r = FLXA_Menu_Service::user_set(
				FLXA_Util::str( $input['op'] ?? '' ),
				FLXA_Util::str( $input['slug'] ?? '' ),
				FLXA_Util::strings( $input['order'] ?? array() )
			);
			return $r instanceof WP_Error ? $r : array( 'menu' => $r );
		},
	)
);

flx_gql_type(
	'FlxAdminTasksPrefs',
	array(
		'description' => __( 'Filters of the Tasks dashboard module.', 'flx-admin' ),
		'fields'      => array(
			'scope' => flxa_gql_f( 'FlxAdminTasksScope!', __( 'Which tasks it counts.', 'flx-admin' ), 'scope' ),
			'list'  => flxa_gql_f( 'Int!', __( 'Task list ID; 0 = all lists.', 'flx-admin' ), 'list' ),
			'chart' => flxa_gql_f( 'FlxAdminTasksChart!', __( 'How the chart is split.', 'flx-admin' ), 'chart' ),
		),
	)
);

flx_gql_type(
	'FlxAdminDonationsPrefs',
	array(
		'description' => __( 'Filters of the Donations dashboard module.', 'flx-admin' ),
		'fields'      => array(
			'days'  => flxa_gql_f( 'Int!', __( 'Days it covers: 7, 14 or 30.', 'flx-admin' ), 'days' ),
			'split' => flxa_gql_f( 'FlxAdminDonationsSplit!', __( 'How the chart is split.', 'flx-admin' ), 'split' ),
		),
	)
);

flx_gql_type(
	'FlxAdminWidgetPrefs',
	array(
		'description' => __( 'The signed-in user\'s dashboard module filters.', 'flx-admin' ),
		'fields'      => array(
			'tasks'               => flxa_gql_f( 'FlxAdminTasksPrefs!', __( 'Tasks module.', 'flx-admin' ), 'tasks' ),
			'donations'           => flxa_gql_f( 'FlxAdminDonationsPrefs!', __( 'Donations module.', 'flx-admin' ), 'donations' ),
			'tasksAvailable'      => array(
				'type'        => 'Boolean!',
				'description' => __( 'The user can have the Tasks module (Flux Tasks is installed and they may use it).', 'flx-admin' ),
				'resolve'     => static fn(): bool => FLXA_User_Service::widgets_available()['tasks'],
			),
			'donationsAvailable'  => array(
				'type'        => 'Boolean!',
				'description' => __( 'The user can have the Donations module (Flux Donate is installed and they may see donations).', 'flx-admin' ),
				'resolve'     => static fn(): bool => FLXA_User_Service::widgets_available()['donations'],
			),
		),
	)
);

flx_gql_query(
	'flxAdminWidgetPrefs',
	array(
		'type'        => 'FlxAdminWidgetPrefs!',
		'description' => __( 'The signed-in user\'s dashboard module filters.', 'flx-admin' ),
		'cap'         => 'logged_in',
		'resolve'     => static fn(): array => FLXA_User_Service::widget_prefs(),
	)
);

flx_gql_mutation(
	'flxAdminWidgetPrefsSet',
	array(
		'description' => __( 'Saves the filters of one dashboard module and returns the module as the dashboard shows it. Filters you leave out stay as they are.', 'flx-admin' ),
		'cap'         => 'logged_in',
		'input'       => array(
			'widget'         => array( 'type' => 'FlxAdminWidget!', 'description' => __( 'The module.', 'flx-admin' ) ),
			'tasksScope'     => array( 'type' => 'FlxAdminTasksScope', 'description' => __( 'Tasks: which tasks it counts.', 'flx-admin' ) ),
			'tasksList'      => array( 'type' => 'Int', 'description' => __( 'Tasks: task list ID; 0 = all lists.', 'flx-admin' ) ),
			'tasksChart'     => array( 'type' => 'FlxAdminTasksChart', 'description' => __( 'Tasks: how the chart is split.', 'flx-admin' ) ),
			'donationsDays'  => array( 'type' => 'Int', 'description' => __( 'Donations: days it covers (7, 14 or 30).', 'flx-admin' ) ),
			'donationsSplit' => array( 'type' => 'FlxAdminDonationsSplit', 'description' => __( 'Donations: how the chart is split.', 'flx-admin' ) ),
		),
		'output'      => array(
			'html'  => array(
				'type'        => 'String',
				'description' => __( 'The module\'s body as the dashboard shows it (HTML rendered by Flux Admin).', 'flx-admin' ),
			),
			'prefs' => array(
				'type'        => 'FlxAdminWidgetPrefs',
				'description' => __( 'The user\'s filters after the change.', 'flx-admin' ),
				'resolve'     => static fn(): array => FLXA_User_Service::widget_prefs(),
			),
		),
		'resolve'     => static function ( array $input ) {
			$widget = FLXA_Util::str( $input['widget'] ?? '' );
			$prefs  = array();
			foreach ( array( 'tasksScope' => 'scope', 'tasksList' => 'list', 'tasksChart' => 'chart', 'donationsDays' => 'days', 'donationsSplit' => 'split' ) as $gql => $key ) {
				if ( isset( $input[ $gql ] ) ) {
					$prefs[ $key ] = $input[ $gql ];
				}
			}
			$r = FLXA_User_Service::set_widget( $widget, $prefs );
			return $r instanceof WP_Error ? $r : array( 'html' => $r['html'] );
		},
	)
);
