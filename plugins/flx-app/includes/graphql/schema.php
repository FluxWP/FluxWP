<?php
/**
 * Flux App GraphQL schema (Flux GraphQL kit). Loaded on flx_gql_register, i.e. only inside a GraphQL request.
 * Resolvers call FLXAPP_Service; FluxApp (desktop, web, mobile) and the panel read the app contract through
 * these fields, with the same token and `X-Flux-Locale` / `X-FluxWP-Platform` headers as REST.
 *
 * `flxAppConfig`, `flxAppManifest` and `flxAppBootstrap` carry open, versioned documents (`flx-app-config/1`,
 * `flx-app-manifest/1`, `flx-app-bootstrap/1`) that packages extend through PHP filters. They are FlxJSON on
 * purpose; the client validates them with the JSON Schemas of @fluxwp/manifest. Everything else is typed.
 *
 * @package flx-app
 */

defined( 'ABSPATH' ) || exit;

/**
 * A field config: type + description (the default resolver reads the array key with the field name).
 *
 * @param string $type        GraphQL type.
 * @param string $description Description.
 * @return array{type: string, description: string}
 */
function flxapp_gql_f( string $type, string $description ): array {
	return array(
		'type'        => $type,
		'description' => $description,
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
function flxapp_gql_ok( $r ) {
	if ( $r instanceof WP_Error ) {
		throw flx_gql_from_wp_error( $r );
	}
	return $r;
}

flx_gql_enum(
	'FlxAppPlatform',
	array(
		'description' => __( 'Platform of the asking app; decides which menu and screens are sent.', 'flx-app' ),
		'values'      => array(
			'MOBILE'  => array( 'value' => 'mobile' ),
			'DESKTOP' => array( 'value' => 'desktop' ),
		),
	)
);
flx_gql_enum(
	'FlxAppAuthKind',
	array(
		'description' => __( 'How the request signed in.', 'flx-app' ),
		'values'      => array(
			'COOKIE' => array( 'value' => 'cookie' ),
			'ACCESS' => array( 'value' => 'access' ),
			'KEY'    => array( 'value' => 'key' ),
		),
	)
);

/* ───────── me ───────── */

flx_gql_type(
	'FlxAppCount',
	array(
		'description' => __( 'A counter shown as a badge (unread messages, open tasks …).', 'flx-app' ),
		'fields'      => array(
			'key'   => flxapp_gql_f( 'String!', __( 'Counter name.', 'flx-app' ) ),
			'value' => flxapp_gql_f( 'Int!', __( 'Current number.', 'flx-app' ) ),
		),
	)
);
flx_gql_type(
	'FlxAppHashes',
	array(
		'description' => __( 'Short digests of the documents the app caches; a changed digest means refetch.', 'flx-app' ),
		'fields'      => array(
			'me'       => flxapp_gql_f( 'String!', __( 'Digest of flxAppMe.', 'flx-app' ) ),
			'config'   => flxapp_gql_f( 'String!', __( 'Digest of flxAppConfig.', 'flx-app' ) ),
			'manifest' => flxapp_gql_f( 'String!', __( 'Digest of flxAppManifest.', 'flx-app' ) ),
		),
	)
);
flx_gql_type(
	'FlxAppRole',
	array(
		'description' => __( 'A role of the user.', 'flx-app' ),
		'fields'      => array(
			'id'   => flxapp_gql_f( 'String!', __( 'Role key.', 'flx-app' ) ),
			'name' => flxapp_gql_f( 'String!', __( 'Role name in the user\'s language.', 'flx-app' ) ),
		),
	)
);
flx_gql_type(
	'FlxAppUser',
	array(
		'description' => __( 'The signed-in user.', 'flx-app' ),
		'fields'      => array(
			'databaseId' => flxapp_gql_f( 'Int!', __( 'User ID.', 'flx-app' ) ),
			'login'      => flxapp_gql_f( 'String!', __( 'Username.', 'flx-app' ) ),
			'name'       => flxapp_gql_f( 'String!', __( 'Display name.', 'flx-app' ) ),
			'email'      => flxapp_gql_f( 'String!', __( 'E-mail address.', 'flx-app' ) ),
			'avatar'     => flxapp_gql_f( 'String!', __( 'Avatar image address.', 'flx-app' ) ),
			'roles'      => flxapp_gql_f( '[FlxAppRole!]!', __( 'Roles of the user.', 'flx-app' ) ),
			'locale'     => flxapp_gql_f( 'String!', __( 'Language of the user (profile language).', 'flx-app' ) ),
			'isAdmin'    => flxapp_gql_f( 'Boolean!', __( 'Can manage the site settings.', 'flx-app' ) ),
			'profileUrl' => flxapp_gql_f( 'String!', __( 'Profile page in the panel.', 'flx-app' ) ),
		),
	)
);
flx_gql_type(
	'FlxAppSite',
	array(
		'description' => __( 'The site the app is connected to.', 'flx-app' ),
		'fields'      => array(
			'name'     => flxapp_gql_f( 'String!', __( 'Site name.', 'flx-app' ) ),
			'url'      => flxapp_gql_f( 'String!', __( 'Home address.', 'flx-app' ) ),
			'adminUrl' => flxapp_gql_f( 'String!', __( 'Panel address.', 'flx-app' ) ),
			'restUrl'  => flxapp_gql_f( 'String!', __( 'REST API root.', 'flx-app' ) ),
			'icon'     => flxapp_gql_f( 'String!', __( 'Site icon address; empty when none.', 'flx-app' ) ),
			'locale'   => flxapp_gql_f( 'String!', __( 'Site language.', 'flx-app' ) ),
			'timezone' => flxapp_gql_f( 'String!', __( 'Site time zone.', 'flx-app' ) ),
		),
	)
);
flx_gql_type(
	'FlxAppAuth',
	array(
		'description' => __( 'How this request signed in.', 'flx-app' ),
		'fields'      => array(
			'kind'    => flxapp_gql_f( 'FlxAppAuthKind!', __( 'Cookie session, app access token or personal API key.', 'flx-app' ) ),
			'client'  => flxapp_gql_f( 'String', __( 'OAuth client of the token; empty for a cookie session.', 'flx-app' ) ),
			'scope'   => flxapp_gql_f( 'String', __( 'Scopes of the token; empty for a cookie session.', 'flx-app' ) ),
			'expires' => flxapp_gql_f( 'Int', __( 'Unix time the token expires; empty when it does not or for a cookie session.', 'flx-app' ) ),
		),
	)
);
flx_gql_type(
	'FlxAppMenuSubItem',
	array(
		'description' => __( 'A sub item of a menu entry.', 'flx-app' ),
		'fields'      => array(
			'slug'  => flxapp_gql_f( 'String!', __( 'Menu slug.', 'flx-app' ) ),
			'title' => flxapp_gql_f( 'String!', __( 'Title in the user\'s language.', 'flx-app' ) ),
			'url'   => flxapp_gql_f( 'String!', __( 'Panel address.', 'flx-app' ) ),
			'badge' => flxapp_gql_f( 'String', __( 'Badge text (a count); empty when none.', 'flx-app' ) ),
		),
	)
);
flx_gql_type(
	'FlxAppMenuItem',
	array(
		'description' => __( 'A panel menu entry the user may open.', 'flx-app' ),
		'fields'      => array(
			'id'      => flxapp_gql_f( 'String!', __( 'Entry ID.', 'flx-app' ) ),
			'slug'    => flxapp_gql_f( 'String!', __( 'Menu slug.', 'flx-app' ) ),
			'title'   => flxapp_gql_f( 'String!', __( 'Title in the user\'s language.', 'flx-app' ) ),
			'url'     => flxapp_gql_f( 'String!', __( 'Panel address.', 'flx-app' ) ),
			'icon'    => flxapp_gql_f( 'String!', __( 'Icon name.', 'flx-app' ) ),
			'iconUrl' => flxapp_gql_f( 'String', __( 'Coloured package logo; empty when the package has none (use icon).', 'flx-app' ) ),
			'badge'   => flxapp_gql_f( 'String', __( 'Badge text (a count); empty when none.', 'flx-app' ) ),
			'items'   => flxapp_gql_f( '[FlxAppMenuSubItem!]!', __( 'Sub items.', 'flx-app' ) ),
		),
	)
);
flx_gql_type(
	'FlxAppModule',
	array(
		'description' => __( 'A package module with a native screen in the app.', 'flx-app' ),
		'fields'      => array(
			'id'          => flxapp_gql_f( 'String!', __( 'Module key.', 'flx-app' ) ),
			'title'       => flxapp_gql_f( 'String!', __( 'Module title.', 'flx-app' ) ),
			'menu'        => flxapp_gql_f( 'String', __( 'Panel menu slug the module replaces; empty when none.', 'flx-app' ) ),
			'api'         => flxapp_gql_f( 'String', __( 'REST root of the module; empty when none.', 'flx-app' ) ),
			'icon'        => flxapp_gql_f( 'String', __( 'Icon name; empty when none.', 'flx-app' ) ),
			'version'     => flxapp_gql_f( 'String', __( 'Package version; empty when not reported.', 'flx-app' ) ),
			'order'       => flxapp_gql_f( 'Int', __( 'Sort order; empty = default.', 'flx-app' ) ),
			'url'         => flxapp_gql_f( 'String', __( 'Panel page an app without the native screen opens.', 'flx-app' ) ),
			'hasManifest' => flxapp_gql_f( 'Boolean!', __( 'The module has a manifest (flxAppManifest) and opens with it.', 'flx-app' ) ),
			'extra'       => flxapp_gql_f( 'FlxJSON!', __( 'Further keys a package adds to its module entry (open map).', 'flx-app' ) ),
		),
	)
);
flx_gql_type(
	'FlxAppCapability',
	array(
		'description' => __( 'One core WordPress permission, for the app\'s screen and menu decisions.', 'flx-app' ),
		'fields'      => array(
			'key'     => flxapp_gql_f( 'String!', __( 'Capability name.', 'flx-app' ) ),
			'granted' => flxapp_gql_f( 'Boolean!', __( 'The user has it. The server still checks every field.', 'flx-app' ) ),
		),
	)
);
flx_gql_type(
	'FlxAppVersion',
	array(
		'description' => __( 'An installed FluxWP package and its version.', 'flx-app' ),
		'fields'      => array(
			'slug'    => flxapp_gql_f( 'String!', __( 'Package slug.', 'flx-app' ) ),
			'version' => flxapp_gql_f( 'String!', __( 'Version.', 'flx-app' ) ),
		),
	)
);
flx_gql_type(
	'FlxAppLayoutGroup',
	array(
		'description' => __( 'A group of the left menu.', 'flx-app' ),
		'fields'      => array(
			'id'        => flxapp_gql_f( 'String!', __( 'Group key.', 'flx-app' ) ),
			'label'     => flxapp_gql_f( 'String!', __( 'Group name; empty for the first unnamed group.', 'flx-app' ) ),
			'collapsed' => flxapp_gql_f( 'Boolean!', __( 'Starts collapsed.', 'flx-app' ) ),
			'items'     => flxapp_gql_f( '[String!]!', __( 'Menu slugs in the group.', 'flx-app' ) ),
		),
	)
);
flx_gql_type(
	'FlxAppLayout',
	array(
		'description' => __( 'The menu layout as in the panel (Flux Admin).', 'flx-app' ),
		'fields'      => array(
			'pins'   => flxapp_gql_f( '[String!]!', __( 'Menu slugs the person pinned.', 'flx-app' ) ),
			'groups' => flxapp_gql_f( '[FlxAppLayoutGroup!]!', __( 'Menu groups.', 'flx-app' ) ),
		),
	)
);
flx_gql_type(
	'FlxAppMe',
	array(
		'description' => __( 'Everything the app needs after sign-in: user, site, menu, modules, counters and digests.', 'flx-app' ),
		'fields'      => array(
			'user'       => flxapp_gql_f( 'FlxAppUser!', __( 'The signed-in user.', 'flx-app' ) ),
			'site'       => flxapp_gql_f( 'FlxAppSite!', __( 'The site.', 'flx-app' ) ),
			'auth'       => flxapp_gql_f( 'FlxAppAuth!', __( 'How this request signed in.', 'flx-app' ) ),
			'menu'       => flxapp_gql_f( '[FlxAppMenuItem!]!', __( 'Menu the user may open.', 'flx-app' ) ),
			'modules'    => flxapp_gql_f( '[FlxAppModule!]!', __( 'Modules with native screens, by sort order.', 'flx-app' ) ),
			'counts'     => flxapp_gql_f( '[FlxAppCount!]!', __( 'Badge counters.', 'flx-app' ) ),
			'bottom'     => flxapp_gql_f( '[String!]!', __( 'Menu slugs of the phone bottom bar.', 'flx-app' ) ),
			'webSession' => flxapp_gql_f( 'Boolean!', __( 'The app may open panel pages in a web view (admin sessions are on).', 'flx-app' ) ),
			'caps'       => flxapp_gql_f( '[FlxAppCapability!]!', __( 'Core permissions summary.', 'flx-app' ) ),
			'versions'   => flxapp_gql_f( '[FlxAppVersion!]!', __( 'Installed FluxWP packages.', 'flx-app' ) ),
			'layout'     => flxapp_gql_f( 'FlxAppLayout', __( 'Menu layout; empty without Flux Admin.', 'flx-app' ) ),
			'hashes'     => flxapp_gql_f( 'FlxAppHashes!', __( 'Digests for the cached documents.', 'flx-app' ) ),
		),
	)
);
flx_gql_query(
	'flxAppMe',
	array(
		'type'        => 'FlxAppMe!',
		'description' => __( 'The signed-in user, site, menu, modules and layout (REST GET /flxapp/v1/me).', 'flx-app' ),
		'cap'         => 'logged_in',
		'resolve'     => static fn(): array => FLXAPP_Service::me(),
	)
);
flx_gql_query(
	'flxAppCounts',
	array(
		'type'        => '[FlxAppCount!]!',
		'description' => __( 'Only the badge counters (light poll; REST GET /flxapp/v1/counts).', 'flx-app' ),
		'cap'         => 'logged_in',
		'resolve'     => static fn(): array => FLXAPP_Service::counts(),
	)
);

/* ───────── sync ───────── */

flx_gql_type(
	'FlxAppEvent',
	array(
		'description' => __( 'A notification event for the desktop app.', 'flx-app' ),
		'fields'      => array(
			'event'  => flxapp_gql_f( 'String!', __( 'Event type, e.g. flxg.assigned.', 'flx-app' ) ),
			'title'  => flxapp_gql_f( 'String!', __( 'Title.', 'flx-app' ) ),
			'body'   => flxapp_gql_f( 'String!', __( 'Text; empty when the site hides notification text.', 'flx-app' ) ),
			'target' => flxapp_gql_f( 'String!', __( 'Where the app opens when the event is clicked.', 'flx-app' ) ),
			'params' => flxapp_gql_f( 'FlxJSON!', __( 'Parameters of the target (open map).', 'flx-app' ) ),
			'at'     => flxapp_gql_f( 'Int!', __( 'Unix time.', 'flx-app' ) ),
		),
	)
);
flx_gql_type(
	'FlxAppSync',
	array(
		'description' => __( 'Light poll: counters, digests of the cached documents and the new events.', 'flx-app' ),
		'fields'      => array(
			'counts'     => flxapp_gql_f( '[FlxAppCount!]!', __( 'Badge counters.', 'flx-app' ) ),
			'hashes'     => flxapp_gql_f( 'FlxAppHashes!', __( 'Digests; a changed one means refetch that document.', 'flx-app' ) ),
			'events'     => flxapp_gql_f( '[FlxAppEvent!]!', __( 'Events after the asked time (the last 20 are kept).', 'flx-app' ) ),
			'serverTime' => flxapp_gql_f( 'Int!', __( 'Server Unix time; send it back as `since`.', 'flx-app' ) ),
		),
	)
);
flx_gql_query(
	'flxAppSync',
	array(
		'type'        => 'FlxAppSync!',
		'description' => __( 'Light poll (REST GET /flxapp/v1/sync). Poll no faster than every 10 seconds and stop while the app is hidden.', 'flx-app' ),
		'cap'         => 'logged_in',
		'args'        => array(
			'since' => flxapp_gql_f( 'Int', __( 'Unix time of the last poll; events after it are returned.', 'flx-app' ) ),
		),
		'resolve'     => static fn( $root, array $args ): array => FLXAPP_Service::sync( flx_gql_int( $args['since'] ?? 0 ) ),
	)
);

/* ───────── config / manifest / bootstrap (open documents) ───────── */

flx_gql_type(
	'FlxAppConfig',
	array(
		'description' => __( 'The resolved look and behaviour of the app (flx-app-config/1).', 'flx-app' ),
		'fields'      => array(
			'hash'        => flxapp_gql_f( 'String!', __( 'Digest of the document; send it as ifHash next time.', 'flx-app' ) ),
			'notModified' => flxapp_gql_f( 'Boolean!', __( 'ifHash matched: keep the cached document.', 'flx-app' ) ),
			'document'    => flxapp_gql_f( 'FlxJSON', __( 'The flx-app-config/1 document (brand, theme, shell, features …); empty when not modified. Open and versioned, validated by the client.', 'flx-app' ) ),
		),
	)
);
flx_gql_query(
	'flxAppConfig',
	array(
		'type'        => 'FlxAppConfig!',
		'description' => __( 'The app look for the asking platform (REST GET /flxapp/v1/config, with ETag semantics via ifHash).', 'flx-app' ),
		'cap'         => 'logged_in',
		'args'        => array(
			'ifHash' => flxapp_gql_f( 'String', __( 'Digest the app already has; a match returns notModified and no document.', 'flx-app' ) ),
		),
		'resolve'     => static fn( $root, array $args ): array => FLXAPP_Service::config( flx_gql_str( $args['ifHash'] ?? '' ) ),
	)
);
flx_gql_type(
	'FlxAppManifestModule',
	array(
		'description' => __( 'The screens of one package (flx-app-manifest/1).', 'flx-app' ),
		'fields'      => array(
			'id'       => flxapp_gql_f( 'ID!', __( 'Module key.', 'flx-app' ) ),
			'document' => flxapp_gql_f( 'FlxJSON!', __( 'The module manifest (screens, actions, fields); open and versioned, validated by the client.', 'flx-app' ) ),
		),
	)
);
flx_gql_type(
	'FlxAppManifest',
	array(
		'description' => __( 'Screens of the packages the user may open.', 'flx-app' ),
		'fields'      => array(
			'schema'      => flxapp_gql_f( 'String!', __( 'Manifest format, flx-app-manifest/1.', 'flx-app' ) ),
			'hash'        => flxapp_gql_f( 'String!', __( 'Digest; send it as ifHash next time.', 'flx-app' ) ),
			'notModified' => flxapp_gql_f( 'Boolean!', __( 'ifHash matched: keep the cached manifests.', 'flx-app' ) ),
			'platform'    => flxapp_gql_f( 'FlxAppPlatform', __( 'Platform the list was filtered for; empty when the app did not say.', 'flx-app' ) ),
			'modules'     => flxapp_gql_f( '[FlxAppManifestModule!]!', __( 'Module manifests by sort order; empty when not modified.', 'flx-app' ) ),
		),
	)
);
flx_gql_query(
	'flxAppManifest',
	array(
		'type'        => 'FlxAppManifest!',
		'description' => __( 'Screens of the packages for the user and platform (REST GET /flxapp/v1/manifest, ETag via ifHash).', 'flx-app' ),
		'cap'         => 'logged_in',
		'args'        => array(
			'ifHash' => flxapp_gql_f( 'String', __( 'Digest the app already has; a match returns notModified and no modules.', 'flx-app' ) ),
		),
		'resolve'     => static fn( $root, array $args ): array => FLXAPP_Service::manifest( flx_gql_str( $args['ifHash'] ?? '' ) ),
	)
);
flx_gql_type(
	'FlxAppBootstrap',
	array(
		'description' => __( 'Public discovery of the site for the app.', 'flx-app' ),
		'fields'      => array(
			'document' => flxapp_gql_f( 'FlxJSON!', __( 'The flx-app-bootstrap/1 document: site, sign-in methods, apps, brand, theme, packages. Public, no secrets; open and versioned, validated by the client.', 'flx-app' ) ),
		),
	)
);
flx_gql_query(
	'flxAppBootstrap',
	array(
		'type'        => 'FlxAppBootstrap!',
		'description' => __( 'What the app learns before signing in (REST GET /flxapp/v1/app/bootstrap; cached 5 minutes).', 'flx-app' ),
		'cap'         => 'public', // The app asks before it has an account; the document holds public site facts only.
		'resolve'     => static function (): array {
			flx_gql_cache_tag( 'flx-app:bootstrap' );
			return array( 'document' => FLXAPP_Service::bootstrap() );
		},
	)
);

/* ───────── dashboard ───────── */

flx_gql_type(
	'FlxAppDashboardCard',
	array(
		'description' => __( 'A figure card of the app home.', 'flx-app' ),
		'fields'      => array(
			'id'    => flxapp_gql_f( 'String!', __( 'Card key.', 'flx-app' ) ),
			'title' => flxapp_gql_f( 'String!', __( 'Title.', 'flx-app' ) ),
			'value' => flxapp_gql_f( 'String!', __( 'Formatted number or amount.', 'flx-app' ) ),
			'sub'   => flxapp_gql_f( 'String', __( 'Small text under the value.', 'flx-app' ) ),
			'icon'  => flxapp_gql_f( 'String', __( 'Icon name.', 'flx-app' ) ),
			'tone'  => flxapp_gql_f( 'String', __( 'accent, warn, danger or ok; empty = default.', 'flx-app' ) ),
			'url'   => flxapp_gql_f( 'String', __( 'Panel page that opens in a web view.', 'flx-app' ) ),
			'open'  => flxapp_gql_f( 'String', __( 'App target (menu:<slug>, module:<id>, web:<path>).', 'flx-app' ) ),
			'order' => flxapp_gql_f( 'Int', __( 'Sort order.', 'flx-app' ) ),
		),
	)
);
flx_gql_type(
	'FlxAppDashboardItem',
	array(
		'description' => __( 'A row of a home list.', 'flx-app' ),
		'fields'      => array(
			'title' => flxapp_gql_f( 'String!', __( 'Title.', 'flx-app' ) ),
			'sub'   => flxapp_gql_f( 'String', __( 'Second line.', 'flx-app' ) ),
			'meta'  => flxapp_gql_f( 'String', __( 'Right-hand text (time, amount).', 'flx-app' ) ),
			'url'   => flxapp_gql_f( 'String', __( 'Panel page that opens in a web view.', 'flx-app' ) ),
		),
	)
);
flx_gql_type(
	'FlxAppDashboardList',
	array(
		'description' => __( 'A list of the app home.', 'flx-app' ),
		'fields'      => array(
			'id'    => flxapp_gql_f( 'String!', __( 'List key.', 'flx-app' ) ),
			'title' => flxapp_gql_f( 'String!', __( 'Title.', 'flx-app' ) ),
			'icon'  => flxapp_gql_f( 'String', __( 'Icon name.', 'flx-app' ) ),
			'url'   => flxapp_gql_f( 'String', __( 'Panel page that opens in a web view.', 'flx-app' ) ),
			'open'  => flxapp_gql_f( 'String', __( 'App target (menu:<slug>, module:<id>, web:<path>).', 'flx-app' ) ),
			'order' => flxapp_gql_f( 'Int', __( 'Sort order.', 'flx-app' ) ),
			'items' => flxapp_gql_f( '[FlxAppDashboardItem!]!', __( 'Rows.', 'flx-app' ) ),
		),
	)
);
flx_gql_type(
	'FlxAppDashboard',
	array(
		'description' => __( 'The app home: cards and lists the user\'s permissions allow.', 'flx-app' ),
		'fields'      => array(
			'cards' => flxapp_gql_f( '[FlxAppDashboardCard!]!', __( 'Figure cards by sort order.', 'flx-app' ) ),
			'lists' => flxapp_gql_f( '[FlxAppDashboardList!]!', __( 'Lists by sort order.', 'flx-app' ) ),
		),
	)
);
flx_gql_query(
	'flxAppDashboard',
	array(
		'type'        => 'FlxAppDashboard!',
		'description' => __( 'The app home (REST GET /flxapp/v1/dashboard).', 'flx-app' ),
		'cap'         => 'logged_in',
		'resolve'     => static fn(): array => FLXAPP_Service::dashboard(),
	)
);

/* ───────── preferences ───────── */

flx_gql_type(
	'FlxAppPushPref',
	array(
		'description' => __( 'Whether the person wants one notification type.', 'flx-app' ),
		'fields'      => array(
			'event' => flxapp_gql_f( 'String!', __( 'Event type.', 'flx-app' ) ),
			'on'    => flxapp_gql_f( 'Boolean!', __( 'Notifications of this type are on.', 'flx-app' ) ),
		),
	)
);
flx_gql_type(
	'FlxAppPushEvent',
	array(
		'description' => __( 'A notification type the person may switch on or off.', 'flx-app' ),
		'fields'      => array(
			'id'      => flxapp_gql_f( 'String!', __( 'Event type.', 'flx-app' ) ),
			'title'   => flxapp_gql_f( 'String!', __( 'Name in the user\'s language.', 'flx-app' ) ),
			'source'  => flxapp_gql_f( 'String!', __( 'Package that sends it; empty for core.', 'flx-app' ) ),
			'default' => flxapp_gql_f( 'Boolean!', __( 'On until the person changes it.', 'flx-app' ) ),
			'on'      => flxapp_gql_f( 'Boolean!', __( 'Currently on for this person.', 'flx-app' ) ),
		),
	)
);
flx_gql_type(
	'FlxAppPrefs',
	array(
		'description' => __( 'The person\'s app preferences, shared by all their devices.', 'flx-app' ),
		'fields'      => array(
			'order'      => flxapp_gql_f( '[String!]!', __( 'Menu order (slugs).', 'flx-app' ) ),
			'hidden'     => flxapp_gql_f( '[String!]!', __( 'Hidden menu entries.', 'flx-app' ) ),
			'pinned'     => flxapp_gql_f( '[String!]!', __( 'Pinned menu entries.', 'flx-app' ) ),
			'forceWeb'   => flxapp_gql_f( '[String!]!', __( 'Entries always opened in the web view.', 'flx-app' ) ),
			'tabs'       => flxapp_gql_f( '[String!]!', __( 'Bottom bar entries (at most 5).', 'flx-app' ) ),
			'cardOrder'  => flxapp_gql_f( '[String!]!', __( 'Home card order.', 'flx-app' ) ),
			'cardHidden' => flxapp_gql_f( '[String!]!', __( 'Hidden home cards.', 'flx-app' ) ),
			'push'       => flxapp_gql_f( '[FlxAppPushPref!]!', __( 'Notification choices the person made.', 'flx-app' ) ),
			'events'     => flxapp_gql_f( '[FlxAppPushEvent!]!', __( 'Notification types the person may choose from.', 'flx-app' ) ),
			'updated'    => flxapp_gql_f( 'Int!', __( 'Unix time of the last change; 0 = never.', 'flx-app' ) ),
		),
	)
);
flx_gql_query(
	'flxAppPrefs',
	array(
		'type'        => 'FlxAppPrefs!',
		'description' => __( 'The person\'s app preferences (REST GET /flxapp/v1/prefs).', 'flx-app' ),
		'cap'         => 'logged_in',
		'resolve'     => static fn(): array => FLXAPP_Service::prefs( get_current_user_id() ),
	)
);
flx_gql_input(
	'FlxAppPushPrefInput',
	array(
		'description' => __( 'A notification choice.', 'flx-app' ),
		'fields'      => array(
			'event' => flxapp_gql_f( 'String!', __( 'Event type (a flxAppPrefs.events id).', 'flx-app' ) ),
			'on'    => flxapp_gql_f( 'Boolean!', __( 'Turn it on or off.', 'flx-app' ) ),
		),
	)
);
flx_gql_mutation(
	'flxAppPrefsUpdate',
	array(
		'description' => __( 'Changes the preferences that are sent; the others stay as they are (REST POST /flxapp/v1/prefs).', 'flx-app' ),
		'cap'         => 'logged_in',
		'input'       => array(
			'order'      => flxapp_gql_f( '[String!]', __( 'Menu order (slugs).', 'flx-app' ) ),
			'hidden'     => flxapp_gql_f( '[String!]', __( 'Hidden menu entries.', 'flx-app' ) ),
			'pinned'     => flxapp_gql_f( '[String!]', __( 'Pinned menu entries.', 'flx-app' ) ),
			'forceWeb'   => flxapp_gql_f( '[String!]', __( 'Entries always opened in the web view.', 'flx-app' ) ),
			'tabs'       => flxapp_gql_f( '[String!]', __( 'Bottom bar entries (at most 5).', 'flx-app' ) ),
			'cardOrder'  => flxapp_gql_f( '[String!]', __( 'Home card order.', 'flx-app' ) ),
			'cardHidden' => flxapp_gql_f( '[String!]', __( 'Hidden home cards.', 'flx-app' ) ),
			'push'       => flxapp_gql_f( '[FlxAppPushPrefInput!]', __( 'Notification choices to change.', 'flx-app' ) ),
		),
		'output'      => array(
			'prefs' => flxapp_gql_f( 'FlxAppPrefs', __( 'The preferences after the change.', 'flx-app' ) ),
		),
		'resolve'     => static function ( array $input ): array {
			$in = array();
			foreach ( array( 'order', 'hidden', 'pinned', 'forceWeb', 'tabs', 'cardOrder', 'cardHidden' ) as $k ) {
				if ( array_key_exists( $k, $input ) && is_array( $input[ $k ] ) ) {
					$in[ $k ] = $input[ $k ];
				}
			}
			if ( isset( $input['push'] ) && is_array( $input['push'] ) ) {
				$push = array();
				foreach ( $input['push'] as $row ) {
					$row = flx_gql_arr( $row );
					if ( '' !== flx_gql_str( $row['event'] ?? '' ) ) {
						$push[ flx_gql_str( $row['event'] ) ] = ! empty( $row['on'] );
					}
				}
				$in['push'] = $push;
			}
			return array( 'prefs' => FLXAPP_Service::prefs_update( get_current_user_id(), $in ) );
		},
	)
);

/* ───────── session ───────── */

flx_gql_mutation(
	'flxAppSessionCreate',
	array(
		'description' => __( 'A one-time panel sign-in address (valid 60 seconds) for the app\'s web view (REST POST /flxapp/v1/session).', 'flx-app' ),
		'cap'         => 'logged_in',
		'input'       => array(
			'path'  => flxapp_gql_f( 'String', __( 'Panel path, e.g. admin.php?page=flx-crm.', 'flx-app' ) ),
			'url'   => flxapp_gql_f( 'String', __( 'Site address to open instead of a panel path.', 'flx-app' ) ),
			'front' => flxapp_gql_f( 'Boolean', __( 'Treat `path` as a site (front end) path.', 'flx-app' ) ),
			'embed' => flxapp_gql_f( 'Boolean', __( 'Open without the panel chrome (default true).', 'flx-app' ) ),
		),
		'output'      => array(
			'url'       => flxapp_gql_f( 'String', __( 'The sign-in address.', 'flx-app' ) ),
			'expiresIn' => flxapp_gql_f( 'Int', __( 'Seconds the address stays valid.', 'flx-app' ) ),
		),
		'resolve'     => static fn( array $input ) => FLXAPP_Service::session_create( flx_gql_arr( $input ) ),
	)
);
flx_gql_mutation(
	'flxAppSessionEnd',
	array(
		'description' => __( 'Ends the web view cookie sessions this app session opened (REST POST /flxapp/v1/session/end).', 'flx-app' ),
		'cap'         => 'logged_in',
		'output'      => array(
			'ended' => flxapp_gql_f( 'Int!', __( 'Number of sessions ended.', 'flx-app' ) ),
		),
		'resolve'     => static fn(): array => array( 'ended' => FLXAPP_Service::session_end() ),
	)
);
flx_gql_mutation(
	'flxAppLogout',
	array(
		'description' => __( 'Signs this app session out: its tokens, devices and web sessions (REST POST /flxapp/v1/logout).', 'flx-app' ),
		'cap'         => 'logged_in',
		'output'      => array(
			'ok' => flxapp_gql_f( 'Boolean!', __( 'The session is closed.', 'flx-app' ) ),
		),
		'resolve'     => static function (): array {
			FLXAPP_Service::logout();
			return array( 'ok' => true );
		},
	)
);
