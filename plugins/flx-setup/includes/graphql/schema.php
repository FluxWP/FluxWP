<?php
/**
 * FluxWP Panel GraphQL schema (Flux GraphQL kit). Loaded on flx_gql_register, i.e. only inside a GraphQL
 * request. Resolvers call the FLXK_*_Service classes; the Packages and Settings screens (wp-admin and the
 * FluxWP app) and the setup wizard read and write only through these fields.
 *
 * Secrets stay on the server: the site key and the keys of other sources are never returned, only whether
 * one is saved.
 *
 * @package FLX_Setup
 */

defined( 'ABSPATH' ) || exit;

if ( ! class_exists( 'FLXK_Onboarding' ) ) {
	require_once FLXK_DIR . 'includes/onboarding.php'; // The wizard steps live there (the admin-only load skips it in GraphQL requests).
}

/**
 * Service result → value, or the WP_Error as a typed GraphQL error.
 *
 * @template T
 * @param T|WP_Error $r Result.
 * @return T
 * @throws FLXGQL_Error On WP_Error.
 */
function flxk_gql_ok( $r ) {
	if ( $r instanceof WP_Error ) {
		throw flx_gql_from_wp_error( $r );
	}
	return $r;
}

/**
 * The update engine is loaded (it is not while the old Flux Updater plugin is active).
 *
 * @throws FLXGQL_Error When it is not.
 */
function flxk_gql_engine(): void {
	if ( ! FLXK_Packages_Service::ready() ) {
		throw flx_gql_from_wp_error( FLXK_Packages_Service::unavailable() );
	}
}

/**
 * Typed parent value of a field.
 *
 * @param mixed $source Parent.
 * @return array<string,mixed>
 */
function flxk_gql_src( $source ): array {
	return flx_gql_arr( $source );
}

/**
 * List of strings from an input list.
 *
 * @param mixed $v Input.
 * @return list<string>
 */
function flxk_gql_strings( $v ): array {
	return FLXK_Val::strings( $v );
}

/**
 * Capability of the wizard (filterable, like the wizard page).
 */
function flxk_gql_wizard_cap(): bool {
	return current_user_can( FLXK_Onboarding::cap() );
}

/* ---------- Enums ---------- */

flx_gql_enum(
	'FlxSetupPackageGroup',
	array(
		'description' => __( 'Where a package row belongs in the table.', 'flx-setup' ),
		'values'      => array(
			'FLUX'    => array( 'value' => 'flux', 'description' => __( 'FluxWP package.', 'flx-setup' ) ),
			'PLUGINS' => array( 'value' => 'plugins', 'description' => __( 'Plugin of another maker.', 'flx-setup' ) ),
			'THEMES'  => array( 'value' => 'themes', 'description' => __( 'Theme of another maker.', 'flx-setup' ) ),
			'CORE'    => array( 'value' => 'core', 'description' => __( 'WordPress core and translations.', 'flx-setup' ) ),
		),
	)
);

flx_gql_enum(
	'FlxSetupPackageTab',
	array(
		'description' => __( 'A tab of the package table.', 'flx-setup' ),
		'values'      => array(
			'ALL'     => array( 'value' => 'all', 'description' => __( 'Every row.', 'flx-setup' ) ),
			'FLUX'    => array( 'value' => 'flux', 'description' => __( 'FluxWP packages.', 'flx-setup' ) ),
			'PLUGINS' => array( 'value' => 'plugins', 'description' => __( 'Plugins of other makers.', 'flx-setup' ) ),
			'THEMES'  => array( 'value' => 'themes', 'description' => __( 'Themes of other makers.', 'flx-setup' ) ),
			'CORE'    => array( 'value' => 'core', 'description' => __( 'WordPress core and translations.', 'flx-setup' ) ),
		),
	)
);

flx_gql_enum(
	'FlxSetupPackageKind',
	array(
		'description' => __( 'What a package row is.', 'flx-setup' ),
		'values'      => array(
			'FLUX_PLUGIN'     => array( 'value' => 'flux_plugin', 'description' => __( 'FluxWP plugin.', 'flx-setup' ) ),
			'FLUX_THEME'      => array( 'value' => 'flux_theme', 'description' => __( 'FluxWP theme.', 'flx-setup' ) ),
			'WP_PLUGIN'       => array( 'value' => 'wp_plugin', 'description' => __( 'Plugin of another maker.', 'flx-setup' ) ),
			'WP_THEME'        => array( 'value' => 'wp_theme', 'description' => __( 'Theme of another maker.', 'flx-setup' ) ),
			'WP_TRANSLATIONS' => array( 'value' => 'wp_translations', 'description' => __( 'Translation updates of WordPress, plugins and themes (one row).', 'flx-setup' ) ),
			'WP_CORE'         => array( 'value' => 'wp_core', 'description' => __( 'WordPress itself; it updates on the WordPress updates screen.', 'flx-setup' ) ),
		),
	)
);

flx_gql_enum(
	'FlxSetupPackageState',
	array(
		'description' => __( 'Install state of a package.', 'flx-setup' ),
		'values'      => array(
			'UPDATE'  => array( 'value' => 'update', 'description' => __( 'Installed, a newer version is available.', 'flx-setup' ) ),
			'CURRENT' => array( 'value' => 'current', 'description' => __( 'Installed and up to date.', 'flx-setup' ) ),
			'MISSING' => array( 'value' => 'missing', 'description' => __( 'Not installed.', 'flx-setup' ) ),
		),
	)
);

flx_gql_enum(
	'FlxSetupAutoUpdateMode',
	array(
		'description' => __( 'Automatic update setting of a plugin or theme.', 'flx-setup' ),
		'values'      => array(
			'ON'          => array( 'value' => 'on', 'description' => __( 'On.', 'flx-setup' ) ),
			'OFF'         => array( 'value' => 'off', 'description' => __( 'Off.', 'flx-setup' ) ),
			'LOCKED'      => array( 'value' => 'locked', 'description' => __( 'On for every FluxWP package (FluxWP Panel setting); cannot be changed per package.', 'flx-setup' ) ),
			'UNAVAILABLE' => array( 'value' => 'unavailable', 'description' => __( 'Not applicable: not installed, not a plugin or theme, or automatic updates are off for the site.', 'flx-setup' ) ),
		),
	)
);

flx_gql_enum(
	'FlxSetupRequirementState',
	array(
		'description' => __( 'State of a plugin another package needs.', 'flx-setup' ),
		'values'      => array(
			'ACTIVE'   => array( 'value' => 'active', 'description' => __( 'Installed and active.', 'flx-setup' ) ),
			'INACTIVE' => array( 'value' => 'inactive', 'description' => __( 'Installed, not active.', 'flx-setup' ) ),
			'MISSING'  => array( 'value' => 'missing', 'description' => __( 'Not installed.', 'flx-setup' ) ),
		),
	)
);

flx_gql_enum(
	'FlxSetupConnectionState',
	array(
		'description' => __( 'State of the connection to the update server.', 'flx-setup' ),
		'values'      => array(
			'CONNECTED' => array( 'value' => 'connected', 'description' => __( 'The server answered.', 'flx-setup' ) ),
			'ERROR'     => array( 'value' => 'error', 'description' => __( 'The server could not be reached or refused the site.', 'flx-setup' ) ),
			'PAUSED'    => array( 'value' => 'paused', 'description' => __( 'The server paused updates for this site; checks wait.', 'flx-setup' ) ),
			'UNKNOWN'   => array( 'value' => 'unknown', 'description' => __( 'Not checked yet.', 'flx-setup' ) ),
		),
	)
);

flx_gql_enum(
	'FlxSetupStepKind',
	array(
		'description' => __( 'One step of an install or update.', 'flx-setup' ),
		'values'      => array(
			'INSTALL'  => array( 'value' => 'install', 'description' => __( 'Install a FluxWP package.', 'flx-setup' ) ),
			'WPORG'    => array( 'value' => 'wporg', 'description' => __( 'Install a required plugin from wordpress.org.', 'flx-setup' ) ),
			'ACTIVATE' => array( 'value' => 'activate', 'description' => __( 'Activate a plugin.', 'flx-setup' ) ),
			'UPDATE'   => array( 'value' => 'update', 'description' => __( 'Update a FluxWP package (a backup is taken first).', 'flx-setup' ) ),
			'VERSION'  => array( 'value' => 'version', 'description' => __( 'Install a chosen version of a FluxWP package (a backup is taken first).', 'flx-setup' ) ),
			'WP_PLUGIN' => array( 'value' => 'wp_plugin', 'description' => __( 'Update a plugin of another maker.', 'flx-setup' ) ),
			'WP_THEME'  => array( 'value' => 'wp_theme', 'description' => __( 'Update a theme of another maker.', 'flx-setup' ) ),
			'WP_LANG'   => array( 'value' => 'wp_lang', 'description' => __( 'Update translations.', 'flx-setup' ) ),
		),
	)
);

flx_gql_enum(
	'FlxSetupManageOp',
	array(
		'description' => __( 'Bulk action on installed packages.', 'flx-setup' ),
		'values'      => array(
			'ACTIVATE'   => array( 'value' => 'activate', 'description' => __( 'Activate plugins.', 'flx-setup' ) ),
			'DEACTIVATE' => array( 'value' => 'deactivate', 'description' => __( 'Deactivate plugins.', 'flx-setup' ) ),
			'DELETE'     => array( 'value' => 'delete', 'description' => __( 'Delete plugins and themes from the site.', 'flx-setup' ) ),
			'AUTO_ON'    => array( 'value' => 'auto_on', 'description' => __( 'Turn on automatic updates.', 'flx-setup' ) ),
			'AUTO_OFF'   => array( 'value' => 'auto_off', 'description' => __( 'Turn off automatic updates.', 'flx-setup' ) ),
		),
	)
);

flx_gql_enum(
	'FlxSetupAutoUpdateType',
	array(
		'description' => __( 'What an automatic update setting belongs to.', 'flx-setup' ),
		'values'      => array(
			'PLUGIN' => array( 'value' => 'plugin', 'description' => __( 'A plugin (id is the plugin file).', 'flx-setup' ) ),
			'THEME'  => array( 'value' => 'theme', 'description' => __( 'A theme (id is the theme folder).', 'flx-setup' ) ),
		),
	)
);

flx_gql_enum(
	'FlxSetupSourceType',
	array(
		'description' => __( 'Kind of an update source.', 'flx-setup' ),
		'values'      => array(
			'SERVER' => array( 'value' => 'server', 'description' => __( 'A FluxWP update server.', 'flx-setup' ) ),
			'GITHUB' => array( 'value' => 'github', 'description' => __( 'A public GitHub repository.', 'flx-setup' ) ),
		),
	)
);

flx_gql_enum(
	'FlxSetupSourceState',
	array(
		'description' => __( 'State of an update source.', 'flx-setup' ),
		'values'      => array(
			'OK'        => array( 'value' => 'ok', 'description' => __( 'Connected.', 'flx-setup' ) ),
			'ERROR'     => array( 'value' => 'error', 'description' => __( 'Could not be reached or refused the site.', 'flx-setup' ) ),
			'OFF'       => array( 'value' => 'off', 'description' => __( 'Turned off.', 'flx-setup' ) ),
			'UNCHECKED' => array( 'value' => 'unchecked', 'description' => __( 'Not checked yet.', 'flx-setup' ) ),
		),
	)
);

flx_gql_enum(
	'FlxSetupSourceOp',
	array(
		'description' => __( 'Change of an update source.', 'flx-setup' ),
		'values'      => array(
			'REMOVE' => array( 'value' => 'remove', 'description' => __( 'Remove the source.', 'flx-setup' ) ),
			'TOGGLE' => array( 'value' => 'toggle', 'description' => __( 'Turn the source on or off.', 'flx-setup' ) ),
			'UP'     => array( 'value' => 'up', 'description' => __( 'Move the source up (higher sources win).', 'flx-setup' ) ),
		),
	)
);

flx_gql_enum(
	'FlxSetupBackupDeleteMode',
	array(
		'description' => __( 'Which update backups to delete.', 'flx-setup' ),
		'values'      => array(
			'ONE'      => array( 'value' => 'one', 'description' => __( 'The first id of the list.', 'flx-setup' ) ),
			'SELECTED' => array( 'value' => 'selected', 'description' => __( 'The listed ids.', 'flx-setup' ) ),
			'ALL'      => array( 'value' => 'all', 'description' => __( 'Every unlocked update backup.', 'flx-setup' ) ),
		),
	)
);

flx_gql_enum(
	'FlxSetupCheckStatus',
	array(
		'description' => __( 'Result of a system check row.', 'flx-setup' ),
		'values'      => array(
			'OK'      => array( 'value' => 'ok', 'description' => __( 'Fine.', 'flx-setup' ) ),
			'INFO'    => array( 'value' => 'info', 'description' => __( 'For information.', 'flx-setup' ) ),
			'WARNING' => array( 'value' => 'warning', 'description' => __( 'Needs attention.', 'flx-setup' ) ),
			'ERROR'   => array( 'value' => 'error', 'description' => __( 'Broken.', 'flx-setup' ) ),
		),
	)
);

flx_gql_enum(
	'FlxSetupGraphqlState',
	array(
		'description' => __( 'State of Flux GraphQL on the site.', 'flx-setup' ),
		'values'      => array(
			'OK'       => array( 'value' => 'ok', 'description' => __( 'Installed and working.', 'flx-setup' ) ),
			'MISSING'  => array( 'value' => 'missing', 'description' => __( 'Not installed.', 'flx-setup' ) ),
			'INACTIVE' => array( 'value' => 'inactive', 'description' => __( 'Installed, not active.', 'flx-setup' ) ),
			'CONFLICT' => array( 'value' => 'conflict', 'description' => __( 'Active, but its GraphQL server is not running.', 'flx-setup' ) ),
		),
	)
);

flx_gql_enum(
	'FlxSetupWizardStepKind',
	array(
		'description' => __( 'A step of the setup wizard.', 'flx-setup' ),
		'values'      => array(
			'NAME'    => array( 'value' => 'name', 'description' => __( 'Set the site name.', 'flx-setup' ) ),
			'TAGLINE' => array( 'value' => 'tagline', 'description' => __( 'Set the tagline.', 'flx-setup' ) ),
			'LOGO'    => array( 'value' => 'logo', 'description' => __( 'Use a media library image as the logo (value: attachment id).', 'flx-setup' ) ),
			'PAGES'   => array( 'value' => 'pages', 'description' => __( 'Create starter pages (value: comma separated page keys).', 'flx-setup' ) ),
			'THEME'   => array( 'value' => 'theme', 'description' => __( 'Activate the FluxWP theme.', 'flx-setup' ) ),
			'STYLE'   => array( 'value' => 'style', 'description' => __( 'Apply a colour style of the theme (value: style key).', 'flx-setup' ) ),
			'PRESET'  => array( 'value' => 'preset', 'description' => __( 'Apply a preset (value: preset id).', 'flx-setup' ) ),
			'CONSENT' => array( 'value' => 'consent', 'description' => __( 'Accept the terms and set the usage data consent (value: 1 or 0).', 'flx-setup' ) ),
			'DONE'    => array( 'value' => 'done', 'description' => __( 'Finish the wizard.', 'flx-setup' ) ),
			'SKIP'    => array( 'value' => 'skip', 'description' => __( 'Skip the wizard.', 'flx-setup' ) ),
		),
	)
);

/* ---------- Types ---------- */

flx_gql_type(
	'FlxSetupTag',
	array(
		'description' => __( 'A label under a package name.', 'flx-setup' ),
		'fields'      => array(
			'label' => array( 'type' => 'String!', 'description' => __( 'Text.', 'flx-setup' ) ),
			'kind'  => array( 'type' => 'String!', 'description' => __( 'Style: flux, cat, general or type.', 'flx-setup' ) ),
		),
	)
);

flx_gql_type(
	'FlxSetupRequirement',
	array(
		'description' => __( 'A plugin a package needs.', 'flx-setup' ),
		'fields'      => array(
			'slug'  => array( 'type' => 'String!', 'description' => __( 'Plugin folder.', 'flx-setup' ) ),
			'name'  => array( 'type' => 'String!', 'description' => __( 'Plugin name.', 'flx-setup' ) ),
			'state' => array( 'type' => 'FlxSetupRequirementState!', 'description' => __( 'Whether it is installed and active.', 'flx-setup' ) ),
			'flux'  => array( 'type' => 'Boolean!', 'description' => __( 'It is a FluxWP package (else it comes from wordpress.org).', 'flx-setup' ) ),
		),
	)
);

flx_gql_type(
	'FlxSetupVersion',
	array(
		'description' => __( 'Another version of a package the site can switch to.', 'flx-setup' ),
		'fields'      => array(
			'version'  => array( 'type' => 'String!', 'description' => __( 'Version number.', 'flx-setup' ) ),
			'released' => array(
				'type'        => 'FlxDateTime',
				'description' => __( 'Release date; empty when the server did not say.', 'flx-setup' ),
				'resolve'     => static fn( $v ): ?array => flx_gql_datetime( flxk_gql_src( $v )['released'] ?? null ),
			),
			'older'    => array( 'type' => 'Boolean!', 'description' => __( 'Older than the installed version.', 'flx-setup' ) ),
		),
	)
);

flx_gql_type(
	'FlxSetupAutoUpdate',
	array(
		'description' => __( 'The automatic update switch of a row.', 'flx-setup' ),
		'fields'      => array(
			'mode'      => array( 'type' => 'FlxSetupAutoUpdateMode!', 'description' => __( 'Current setting.', 'flx-setup' ) ),
			'type'      => array(
				'type'        => 'FlxSetupAutoUpdateType',
				'description' => __( 'plugin or theme; empty when there is no switch.', 'flx-setup' ),
				'resolve'     => static function ( $a ): ?string {
					$t = FLXK_Val::str( flxk_gql_src( $a )['type'] ?? '' );
					return '' !== $t ? $t : null;
				},
			),
			'id'        => array(
				'type'        => 'String',
				'description' => __( 'Plugin file or theme folder to pass to flxSetupAutoUpdateSet; empty when there is no switch.', 'flx-setup' ),
				'resolve'     => static function ( $a ): ?string {
					$t = FLXK_Val::str( flxk_gql_src( $a )['id'] ?? '' );
					return '' !== $t ? $t : null;
				},
			),
			'canChange' => array( 'type' => 'Boolean!', 'description' => __( 'The viewer may change it.', 'flx-setup' ) ),
		),
	)
);

flx_gql_type(
	'FlxSetupInfoRow',
	array(
		'description' => __( 'A label and value of the package details.', 'flx-setup' ),
		'fields'      => array(
			'label' => array( 'type' => 'String!', 'description' => __( 'Label.', 'flx-setup' ) ),
			'value' => array( 'type' => 'String!', 'description' => __( 'Value.', 'flx-setup' ) ),
		),
	)
);

flx_gql_type(
	'FlxSetupPackage',
	array(
		'description' => __( 'A row of the package table: a FluxWP package, a plugin or theme of another maker, translations or WordPress core.', 'flx-setup' ),
		'fields'      => array(
			'key'              => array( 'type' => 'String!', 'description' => __( 'Row key: the slug of a FluxWP package, p:<plugin file>, t:<theme folder>, lang or core.', 'flx-setup' ) ),
			'group'            => array( 'type' => 'FlxSetupPackageGroup!', 'description' => __( 'Tab the row belongs to.', 'flx-setup' ) ),
			'kind'             => array( 'type' => 'FlxSetupPackageKind!', 'description' => __( 'What the row is.', 'flx-setup' ) ),
			'id'               => array( 'type' => 'String!', 'description' => __( 'Slug (FluxWP), plugin file, theme folder; empty for translations and core.', 'flx-setup' ) ),
			'name'             => array( 'type' => 'String!', 'description' => __( 'Name.', 'flx-setup' ) ),
			'subtitle'         => array( 'type' => 'String!', 'description' => __( 'Slug, folder or a short status line.', 'flx-setup' ) ),
			'author'           => array( 'type' => 'String!', 'description' => __( 'Author; empty when unknown.', 'flx-setup' ) ),
			'state'            => array( 'type' => 'FlxSetupPackageState!', 'description' => __( 'Install state.', 'flx-setup' ) ),
			'installed'        => array( 'type' => 'String!', 'description' => __( 'Installed version; empty when not installed.', 'flx-setup' ) ),
			'latest'           => array( 'type' => 'String!', 'description' => __( 'Latest version.', 'flx-setup' ) ),
			'released'         => array(
				'type'        => 'FlxDateTime',
				'description' => __( 'Release date of the latest version; empty when unknown.', 'flx-setup' ),
				'resolve'     => static fn( $p ): ?array => flx_gql_datetime( flxk_gql_src( $p )['released'] ?? null ),
			),
			'active'           => array( 'type' => 'Boolean!', 'description' => __( 'The plugin is active (the theme in use).', 'flx-setup' ) ),
			'iconUrl'          => array(
				'type'        => 'String',
				'description' => __( 'Icon address; empty shows a placeholder.', 'flx-setup' ),
				'resolve'     => static function ( $p ): ?string {
					$u = FLXK_Val::str( flxk_gql_src( $p )['iconUrl'] ?? '' );
					return '' !== $u ? $u : null;
				},
			),
			'tags'             => array( 'type' => '[FlxSetupTag!]!', 'description' => __( 'Labels under the name.', 'flx-setup' ) ),
			'category'         => array( 'type' => 'String!', 'description' => __( 'FluxWP category name; empty for other rows.', 'flx-setup' ) ),
			'sourceName'       => array( 'type' => 'String!', 'description' => __( 'Update source of a FluxWP package; empty for other rows.', 'flx-setup' ) ),
			'showSource'       => array( 'type' => 'Boolean!', 'description' => __( 'The source is worth showing (not the main server, or several sources offer it).', 'flx-setup' ) ),
			'sourceCount'      => array( 'type' => 'Int!', 'description' => __( 'How many sources offer the package.', 'flx-setup' ) ),
			'sourceNote'       => array( 'type' => 'String!', 'description' => __( 'Extra line, e.g. which translations are waiting.', 'flx-setup' ) ),
			'requires'         => array( 'type' => '[FlxSetupRequirement!]!', 'description' => __( 'Plugins the package needs.', 'flx-setup' ) ),
			'autoUpdate'       => array(
				'type'        => 'FlxSetupAutoUpdate!',
				'description' => __( 'Automatic update switch.', 'flx-setup' ),
				'resolve'     => static fn( $p ): array => flx_gql_arr( flxk_gql_src( $p )['auto'] ?? array() ),
			),
			'canUpdate'        => array( 'type' => 'Boolean!', 'description' => __( 'An update is waiting and the viewer may install it.', 'flx-setup' ) ),
			'canInstall'       => array( 'type' => 'Boolean!', 'description' => __( 'Not installed and the viewer may install it.', 'flx-setup' ) ),
			'canManage'        => array( 'type' => 'Boolean!', 'description' => __( 'The row can be selected for bulk actions.', 'flx-setup' ) ),
			'canChangeVersion' => array(
				'type'        => 'Boolean!',
				'description' => __( 'The viewer may switch to another version of the package.', 'flx-setup' ),
				'resolve'     => static fn( $p ): bool => FLXK_Val::bool( flxk_gql_src( $p )['canVersion'] ?? false ),
			),
			'versions'         => array( 'type' => '[FlxSetupVersion!]!', 'description' => __( 'Other versions the site can switch to.', 'flx-setup' ) ),
			'description'      => array( 'type' => 'String!', 'description' => __( 'Short description for the details window.', 'flx-setup' ) ),
			'website'          => array( 'type' => 'String!', 'description' => __( 'Web page of the package; empty when there is none.', 'flx-setup' ) ),
			'images'           => array( 'type' => '[String!]!', 'description' => __( 'Example screenshots (https only).', 'flx-setup' ) ),
			'info'             => array( 'type' => '[FlxSetupInfoRow!]!', 'description' => __( 'Technical details for the details window.', 'flx-setup' ) ),
		),
	)
);
flx_gql_connection( 'FlxSetupPackage', __( 'A page of the package table.', 'flx-setup' ) );

flx_gql_type(
	'FlxSetupConnection',
	array(
		'description' => __( 'Connection to the main update server.', 'flx-setup' ),
		'fields'      => array(
			'state'         => array( 'type' => 'FlxSetupConnectionState!', 'description' => __( 'State.', 'flx-setup' ) ),
			'title'         => array( 'type' => 'String!', 'description' => __( 'Short state title.', 'flx-setup' ) ),
			'message'       => array( 'type' => 'String!', 'description' => __( 'What the state means.', 'flx-setup' ) ),
			'host'          => array( 'type' => 'String!', 'description' => __( 'Server host.', 'flx-setup' ) ),
			'site'          => array( 'type' => 'String!', 'description' => __( 'Site name the server knows this site by; empty without a key.', 'flx-setup' ) ),
			'checked'       => array(
				'type'        => 'FlxDateTime',
				'description' => __( 'Last check; empty when never checked.', 'flx-setup' ),
				'resolve'     => static fn( $c ): ?array => flx_gql_datetime( flxk_gql_src( $c )['checked'] ?? null ),
			),
			'checkedLabel'  => array( 'type' => 'String!', 'description' => __( 'Last check as a sentence ("Last check: …").', 'flx-setup' ) ),
			'backingOff'    => array( 'type' => 'Boolean!', 'description' => __( 'The server asked the site to wait before the next check.', 'flx-setup' ) ),
			'hasKey'        => array( 'type' => 'Boolean!', 'description' => __( 'A site key is in use (the key itself is never returned).', 'flx-setup' ) ),
			'keyFromConfig' => array( 'type' => 'Boolean!', 'description' => __( 'The key comes from wp-config.php.', 'flx-setup' ) ),
			'packageCount'  => array( 'type' => 'Int!', 'description' => __( 'Packages in the catalog.', 'flx-setup' ) ),
			'updateCount'   => array( 'type' => 'Int!', 'description' => __( 'FluxWP packages with an update.', 'flx-setup' ) ),
		),
	)
);

flx_gql_type(
	'FlxSetupTabCount',
	array(
		'description' => __( 'Row and update counts of a tab.', 'flx-setup' ),
		'fields'      => array(
			'tab'     => array( 'type' => 'FlxSetupPackageTab!', 'description' => __( 'Tab.', 'flx-setup' ) ),
			'label'   => array( 'type' => 'String!', 'description' => __( 'Tab name.', 'flx-setup' ) ),
			'count'   => array( 'type' => 'Int!', 'description' => __( 'Rows.', 'flx-setup' ) ),
			'updates' => array( 'type' => 'Int!', 'description' => __( 'Rows with an update.', 'flx-setup' ) ),
		),
	)
);

flx_gql_type(
	'FlxSetupFailingSource',
	array(
		'description' => __( 'An update source that could not be reached.', 'flx-setup' ),
		'fields'      => array(
			'name'  => array( 'type' => 'String!', 'description' => __( 'Source name.', 'flx-setup' ) ),
			'error' => array( 'type' => 'String!', 'description' => __( 'What went wrong.', 'flx-setup' ) ),
		),
	)
);

flx_gql_type(
	'FlxSetupBackupState',
	array(
		'description' => __( 'Whether a backup is taken before updates (Flux Backup).', 'flx-setup' ),
		'fields'      => array(
			'ready'       => array( 'type' => 'Boolean!', 'description' => __( 'Flux Backup is active.', 'flx-setup' ) ),
			'installed'   => array( 'type' => 'Boolean!', 'description' => __( 'Flux Backup is installed.', 'flx-setup' ) ),
			'activatable' => array( 'type' => 'Boolean!', 'description' => __( 'It is installed and the viewer may activate it.', 'flx-setup' ) ),
			'installable' => array( 'type' => 'Boolean!', 'description' => __( 'It is not installed, the catalog has it and the viewer may install it.', 'flx-setup' ) ),
		),
	)
);

flx_gql_type(
	'FlxSetupOverview',
	array(
		'description' => __( 'What the top of the Packages screen shows.', 'flx-setup' ),
		'fields'      => array(
			'connection'       => array( 'type' => 'FlxSetupConnection!', 'description' => __( 'Connection to the update server.', 'flx-setup' ) ),
			'tabs'             => array(
				'type'        => '[FlxSetupTabCount!]!',
				'description' => __( 'Tabs with their counts.', 'flx-setup' ),
				'args'        => array(
					'search' => array( 'type' => 'String', 'description' => __( 'Count only the rows that match this text.', 'flx-setup' ) ),
				),
				'resolve'     => static fn( $o, array $args ): array => FLXK_Packages_Service::tab_counts( flx_gql_str( $args['search'] ?? '' ) ),
			),
			'updateCount'      => array( 'type' => 'Int!', 'description' => __( 'Updates the "Update all" button installs (FluxWP and others, not WordPress core).', 'flx-setup' ) ),
			'autoCheck'        => array( 'type' => 'Boolean!', 'description' => __( 'The screen should check for updates by itself now (the last check is not recent).', 'flx-setup' ) ),
			'failingSources'   => array( 'type' => '[FlxSetupFailingSource!]!', 'description' => __( 'Other sources that could not be reached.', 'flx-setup' ) ),
			'backup'           => array( 'type' => 'FlxSetupBackupState!', 'description' => __( 'Backup before updates.', 'flx-setup' ) ),
			'backupUrl'        => array( 'type' => 'String!', 'description' => __( 'Flux Backup page.', 'flx-setup' ) ),
			'settingsUrl'      => array(
				'type'        => 'String',
				'description' => __( 'FluxWP Panel settings page; empty when the viewer may not open it.', 'flx-setup' ),
				'resolve'     => static fn(): ?string => current_user_can( 'manage_options' ) ? FLXU_Admin::url( array(), FLXU_Admin::SETTINGS_SLUG ) : null,
			),
			'wordpressUpdatesUrl' => array( 'type' => 'String!', 'description' => __( 'WordPress updates screen (core updates run there).', 'flx-setup' ) ),
			'canManageSources' => array( 'type' => 'Boolean!', 'description' => __( 'The viewer may open the settings.', 'flx-setup' ) ),
		),
	)
);

flx_gql_type(
	'FlxSetupStep',
	array(
		'description' => __( 'One step of an install or update plan.', 'flx-setup' ),
		'fields'      => array(
			'kind'    => array( 'type' => 'FlxSetupStepKind!', 'description' => __( 'What the step does.', 'flx-setup' ) ),
			'slug'    => array( 'type' => 'String!', 'description' => __( 'Row key of the package (pass it back to flxSetupStepRun).', 'flx-setup' ) ),
			'id'      => array( 'type' => 'String!', 'description' => __( 'Plugin file or theme folder of a row of another maker; empty for FluxWP packages.', 'flx-setup' ) ),
			'name'    => array( 'type' => 'String!', 'description' => __( 'Name to show.', 'flx-setup' ) ),
			'why'     => array( 'type' => 'String!', 'description' => __( 'The package that asked for this one; empty when it was chosen.', 'flx-setup' ) ),
			'version' => array( 'type' => 'String!', 'description' => __( 'Version for a version step.', 'flx-setup' ) ),
		),
	)
);

flx_gql_type(
	'FlxSetupLine',
	array(
		'description' => __( 'A line of a step result.', 'flx-setup' ),
		'fields'      => array(
			'ok'   => array( 'type' => 'Boolean!', 'description' => __( 'It succeeded.', 'flx-setup' ) ),
			'text' => array( 'type' => 'String!', 'description' => __( 'What happened.', 'flx-setup' ) ),
		),
	)
);

flx_gql_type(
	'FlxSetupHistoryBackup',
	array(
		'description' => __( 'The backup taken before an update.', 'flx-setup' ),
		'fields'      => array(
			'id'         => array( 'type' => 'String!', 'description' => __( 'Backup id.', 'flx-setup' ) ),
			'deleted'    => array( 'type' => 'Boolean!', 'description' => __( 'The backup was deleted.', 'flx-setup' ) ),
			'size'       => array( 'type' => 'String!', 'description' => __( 'Size to show; empty when deleted.', 'flx-setup' ) ),
			'locked'     => array( 'type' => 'Boolean!', 'description' => __( 'Locked backups are never deleted automatically.', 'flx-setup' ) ),
			'keepUntil'  => array(
				'type'        => 'FlxDateTime',
				'description' => __( 'When it is deleted automatically; empty when never.', 'flx-setup' ),
				'resolve'     => static fn( $b ): ?array => flx_gql_datetime( flxk_gql_src( $b )['keepUntil'] ?? null ),
			),
			'deletable'  => array( 'type' => 'Boolean!', 'description' => __( 'The viewer may delete it.', 'flx-setup' ) ),
			'selectable' => array( 'type' => 'Boolean!', 'description' => __( 'Shows a checkbox (the first row of a bulk update that shares the backup).', 'flx-setup' ) ),
			'url'        => array( 'type' => 'String!', 'description' => __( 'Where to open it in Flux Backup; empty without Flux Backup.', 'flx-setup' ) ),
		),
	)
);

flx_gql_type(
	'FlxSetupHistoryRow',
	array(
		'description' => __( 'A record of the update history.', 'flx-setup' ),
		'fields'      => array(
			'time'    => array(
				'type'        => 'FlxDateTime!',
				'description' => __( 'When it happened.', 'flx-setup' ),
				'resolve'     => static fn( $r ): array => flx_gql_datetime( flxk_gql_src( $r )['time'] ?? 0 ) ?? array( 'iso' => '', 'timestamp' => 0, 'label' => '', 'relative' => '' ),
			),
			'name'    => array( 'type' => 'String!', 'description' => __( 'Package name.', 'flx-setup' ) ),
			'action'  => array( 'type' => 'String!', 'description' => __( 'What was done ("updated (1.0 → 1.1)").', 'flx-setup' ) ),
			'success' => array( 'type' => 'Boolean!', 'description' => __( 'It succeeded.', 'flx-setup' ) ),
			'message' => array( 'type' => 'String!', 'description' => __( 'What went wrong or was rolled back; empty on success.', 'flx-setup' ) ),
			'by'      => array( 'type' => 'String!', 'description' => __( 'Who did it ("Automatic" or a name).', 'flx-setup' ) ),
			'backup'  => array( 'type' => 'FlxSetupHistoryBackup', 'description' => __( 'Backup taken before it; empty when none.', 'flx-setup' ) ),
		),
	)
);

flx_gql_type(
	'FlxSetupRetention',
	array(
		'description' => __( 'How long update backups are kept.', 'flx-setup' ),
		'fields'      => array(
			'available'   => array( 'type' => 'Boolean!', 'description' => __( 'The viewer can manage update backups here.', 'flx-setup' ) ),
			'days'        => array( 'type' => 'Int!', 'description' => __( 'Days update backups are kept; 0 when Flux Backup does not allow setting it here.', 'flx-setup' ) ),
			'choices'     => array( 'type' => '[Int!]!', 'description' => __( 'Day choices to offer.', 'flx-setup' ) ),
			'count'       => array( 'type' => 'Int!', 'description' => __( 'Update backups now.', 'flx-setup' ) ),
			'size'        => array( 'type' => 'String!', 'description' => __( 'Space they take, for display.', 'flx-setup' ) ),
			'settingsUrl' => array( 'type' => 'String!', 'description' => __( 'Flux Backup settings page.', 'flx-setup' ) ),
		),
	)
);

flx_gql_type(
	'FlxSetupHistory',
	array(
		'description' => __( 'A page of the update history.', 'flx-setup' ),
		'fields'      => array(
			'rows'       => array( 'type' => '[FlxSetupHistoryRow!]!', 'description' => __( 'Records of this page, newest first.', 'flx-setup' ) ),
			'total'      => array( 'type' => 'Int!', 'description' => __( 'All records.', 'flx-setup' ) ),
			'pages'      => array( 'type' => 'Int!', 'description' => __( 'Number of pages.', 'flx-setup' ) ),
			'page'       => array( 'type' => 'Int!', 'description' => __( 'This page, from 1.', 'flx-setup' ) ),
			'canDelete'  => array( 'type' => 'Boolean!', 'description' => __( 'The viewer may delete update backups.', 'flx-setup' ) ),
			'backupPage' => array( 'type' => 'String!', 'description' => __( 'Flux Backup list page; empty without Flux Backup.', 'flx-setup' ) ),
			'retention'  => array(
				'type'        => 'FlxSetupRetention!',
				'description' => __( 'How long update backups are kept.', 'flx-setup' ),
				'resolve'     => static fn(): array => FLXK_Packages_Service::retention(),
			),
		),
	)
);

flx_gql_type(
	'FlxSetupLegalLinks',
	array(
		'description' => __( 'Legal pages the usage data consent refers to.', 'flx-setup' ),
		'fields'      => array(
			'kvkk'    => array( 'type' => 'String!', 'description' => __( 'KVKK Privacy Notice.', 'flx-setup' ) ),
			'privacy' => array( 'type' => 'String!', 'description' => __( 'Privacy Policy.', 'flx-setup' ) ),
			'terms'   => array( 'type' => 'String!', 'description' => __( 'Terms of Use.', 'flx-setup' ) ),
		),
	)
);

flx_gql_type(
	'FlxSetupSettings',
	array(
		'description' => __( 'Connection and update settings. The site key is never returned.', 'flx-setup' ),
		'fields'      => array(
			'server'           => array( 'type' => 'String!', 'description' => __( 'Server address typed in the settings; empty uses the default.', 'flx-setup' ) ),
			'defaultServer'    => array( 'type' => 'String!', 'description' => __( 'Default server address.', 'flx-setup' ) ),
			'serverFromConfig' => array( 'type' => 'Boolean!', 'description' => __( 'The server address comes from wp-config.php (FLX_UPDATE_SERVER) and cannot be changed here.', 'flx-setup' ) ),
			'effectiveServer'  => array( 'type' => 'String!', 'description' => __( 'Server address in use.', 'flx-setup' ) ),
			'hasKey'           => array( 'type' => 'Boolean!', 'description' => __( 'A site key is saved.', 'flx-setup' ) ),
			'keyFromConfig'    => array( 'type' => 'Boolean!', 'description' => __( 'The key comes from wp-config.php (FLX_UPDATE_KEY).', 'flx-setup' ) ),
			'auto'             => array( 'type' => 'Boolean!', 'description' => __( 'Install new versions of FluxWP packages automatically.', 'flx-setup' ) ),
			'health'           => array( 'type' => 'Boolean!', 'description' => __( 'Check that the site still loads after an update and roll the package back if it fails.', 'flx-setup' ) ),
			'usageAvailable'   => array( 'type' => 'Boolean!', 'description' => __( 'The usage data consent can be changed here.', 'flx-setup' ) ),
			'usage'            => array( 'type' => 'Boolean!', 'description' => __( 'Usage data is sent.', 'flx-setup' ) ),
			'legal'            => array( 'type' => 'FlxSetupLegalLinks!', 'description' => __( 'Legal pages.', 'flx-setup' ) ),
		),
	)
);

flx_gql_type(
	'FlxSetupSource',
	array(
		'description' => __( 'An update source: the main server or one added in the settings. Its key is never returned.', 'flx-setup' ),
		'fields'      => array(
			'id'           => array( 'type' => 'String!', 'description' => __( 'Source id; "main" for the main server.', 'flx-setup' ) ),
			'name'         => array( 'type' => 'String!', 'description' => __( 'Name.', 'flx-setup' ) ),
			'url'          => array( 'type' => 'String!', 'description' => __( 'Address.', 'flx-setup' ) ),
			'type'         => array( 'type' => 'FlxSetupSourceType!', 'description' => __( 'Kind of source.', 'flx-setup' ) ),
			'main'         => array( 'type' => 'Boolean!', 'description' => __( 'The main server (set under Connection).', 'flx-setup' ) ),
			'enabled'      => array( 'type' => 'Boolean!', 'description' => __( 'Turned on.', 'flx-setup' ) ),
			'hasKey'       => array( 'type' => 'Boolean!', 'description' => __( 'A site key is saved for it.', 'flx-setup' ) ),
			'state'        => array( 'type' => 'FlxSetupSourceState!', 'description' => __( 'Last known state.', 'flx-setup' ) ),
			'message'      => array( 'type' => 'String!', 'description' => __( 'State as a sentence; empty when not checked or off.', 'flx-setup' ) ),
			'packageCount' => array( 'type' => 'Int!', 'description' => __( 'Packages it offers.', 'flx-setup' ) ),
			'canMoveUp'    => array( 'type' => 'Boolean!', 'description' => __( 'It is not the first added source.', 'flx-setup' ) ),
		),
	)
);

flx_gql_type(
	'FlxSetupSystemCheck',
	array(
		'description' => __( 'A row FluxWP Panel adds to the system check.', 'flx-setup' ),
		'fields'      => array(
			'id'          => array( 'type' => 'String!', 'description' => __( 'Row id.', 'flx-setup' ) ),
			'source'      => array( 'type' => 'String!', 'description' => __( 'Package that reports it.', 'flx-setup' ) ),
			'group'       => array( 'type' => 'String!', 'description' => __( 'Group (Config, Plugins …).', 'flx-setup' ) ),
			'label'       => array( 'type' => 'String!', 'description' => __( 'What is checked.', 'flx-setup' ) ),
			'status'      => array( 'type' => 'FlxSetupCheckStatus!', 'description' => __( 'Result.', 'flx-setup' ) ),
			'message'     => array( 'type' => 'String!', 'description' => __( 'Explanation.', 'flx-setup' ) ),
			'actionUrl'   => array( 'type' => 'String!', 'description' => __( 'Where to fix it; empty when there is nothing to do.', 'flx-setup' ) ),
			'actionLabel' => array( 'type' => 'String!', 'description' => __( 'Name of the fix link.', 'flx-setup' ) ),
		),
	)
);

flx_gql_type(
	'FlxSetupGraphqlStatus',
	array(
		'description' => __( 'Flux GraphQL on this site and the PHP version it needs.', 'flx-setup' ),
		'fields'      => array(
			'state'       => array( 'type' => 'FlxSetupGraphqlState!', 'description' => __( 'State of Flux GraphQL.', 'flx-setup' ) ),
			'version'     => array( 'type' => 'String!', 'description' => __( 'Installed version; empty when not running.', 'flx-setup' ) ),
			'phpVersion'  => array( 'type' => 'String!', 'description' => __( 'PHP version of the site.', 'flx-setup' ) ),
			'phpMin'      => array( 'type' => 'String!', 'description' => __( 'Lowest PHP version of the GraphQL packages.', 'flx-setup' ) ),
			'phpOk'       => array( 'type' => 'Boolean!', 'description' => __( 'The site PHP is new enough.', 'flx-setup' ) ),
			'actionUrl'   => array( 'type' => 'String!', 'description' => __( 'Where the admin installs, activates or fixes it; empty when there is nothing to do.', 'flx-setup' ) ),
			'actionLabel' => array( 'type' => 'String!', 'description' => __( 'Name of that link.', 'flx-setup' ) ),
		),
	)
);

flx_gql_type(
	'FlxSetupWizardUpdate',
	array(
		'description' => __( 'An installed package that has an update before the wizard starts.', 'flx-setup' ),
		'fields'      => array(
			'slug' => array( 'type' => 'String!', 'description' => __( 'Package slug.', 'flx-setup' ) ),
			'name' => array( 'type' => 'String!', 'description' => __( 'Name.', 'flx-setup' ) ),
			'from' => array( 'type' => 'String!', 'description' => __( 'Installed version.', 'flx-setup' ) ),
			'to'   => array( 'type' => 'String!', 'description' => __( 'New version.', 'flx-setup' ) ),
			'icon' => array( 'type' => 'String!', 'description' => __( 'Icon address; empty for a placeholder.', 'flx-setup' ) ),
			'self' => array( 'type' => 'Boolean!', 'description' => __( 'It is FluxWP Panel itself (the wizard reopens after it updates).', 'flx-setup' ) ),
		),
	)
);

/* ---------- Queries ---------- */

flx_gql_query(
	'flxSetupOverview',
	array(
		'description' => __( 'Connection state, tab counts and strips of the Packages screen.', 'flx-setup' ),
		'type'        => 'FlxSetupOverview!',
		'cap'         => 'update_plugins',
		'resolve'     => static function (): array {
			flxk_gql_engine();
			$retention_url = admin_url( 'admin.php?page=flx-backup' );
			return array(
				'connection'          => FLXK_Packages_Service::connection(),
				'updateCount'         => FLXK_Packages_Service::update_count(),
				'autoCheck'           => FLXK_Packages_Service::should_auto_check(),
				'failingSources'      => FLXK_Packages_Service::failing_sources(),
				'backup'              => FLXK_Packages_Service::backup_state(),
				'backupUrl'           => $retention_url,
				'wordpressUpdatesUrl' => admin_url( 'update-core.php' ),
				'canManageSources'    => current_user_can( 'manage_options' ),
			);
		},
	)
);

flx_gql_query(
	'flxSetupPackages',
	array(
		'description' => __( 'The package table: FluxWP packages, plugins and themes of other makers, translations and WordPress core, in the order the classic page shows them.', 'flx-setup' ),
		'type'        => 'FlxSetupPackageConnection!',
		'cap'         => 'update_plugins',
		'args'        => flx_gql_connection_args(
			array(
				'tab'         => array( 'type' => 'FlxSetupPackageTab', 'description' => __( 'Only this tab (default: all).', 'flx-setup' ) ),
				'search'      => array( 'type' => 'String', 'description' => __( 'Only rows that match this text (name, slug, author, category).', 'flx-setup' ) ),
				'onlyUpdates' => array( 'type' => 'Boolean', 'description' => __( 'Only rows with an update.', 'flx-setup' ) ),
			)
		),
		'resolve'     => static function ( $root, array $args ): array {
			flxk_gql_engine();
			$rows = FLXK_Packages_Service::rows(
				flx_gql_str( $args['tab'] ?? 'all', 'all' ),
				flx_gql_str( $args['search'] ?? '' ),
				! empty( $args['onlyUpdates'] )
			);
			return flx_gql_paginate(
				$args,
				static fn( int $offset, int $limit ): array => array(
					'items' => array_slice( $rows, $offset, $limit ),
					'total' => count( $rows ),
				)
			);
		},
	)
);

flx_gql_query(
	'flxSetupPackage',
	array(
		'description' => __( 'One row of the package table; empty when the key is unknown.', 'flx-setup' ),
		'type'        => 'FlxSetupPackage',
		'cap'         => 'update_plugins',
		'args'        => array(
			'key' => array( 'type' => 'String!', 'description' => __( 'Row key.', 'flx-setup' ) ),
		),
		'resolve'     => static function ( $root, array $args ): ?array {
			flxk_gql_engine();
			return FLXK_Packages_Service::row( flx_gql_str( $args['key'] ?? '' ) );
		},
	)
);

flx_gql_query(
	'flxSetupPlan',
	array(
		'description' => __( 'The steps to install or update a selection: required plugins first, then installs, then updates. Nothing runs; send each step to flxSetupStepRun.', 'flx-setup' ),
		'type'        => '[FlxSetupStep!]!',
		'cap'         => flx_gql_any_cap( 'update_plugins', 'install_plugins' ),
		'args'        => array(
			'slugs'    => array( 'type' => '[String!]', 'description' => __( 'FluxWP packages to install or update.', 'flx-setup' ) ),
			'wpKeys'   => array( 'type' => '[String!]', 'description' => __( 'Rows of other makers to update (keys like p:folder/file.php, t:folder or lang).', 'flx-setup' ) ),
			'all'      => array( 'type' => 'Boolean', 'description' => __( 'Every row with an update (not WordPress core).', 'flx-setup' ) ),
			'activate' => array( 'type' => 'Boolean', 'description' => __( 'Activate the installed plugins (default: yes).', 'flx-setup' ) ),
		),
		'resolve'     => static function ( $root, array $args ): array {
			flxk_gql_engine();
			return FLXK_Packages_Service::plan(
				flxk_gql_strings( $args['slugs'] ?? array() ),
				flxk_gql_strings( $args['wpKeys'] ?? array() ),
				! empty( $args['all'] ),
				! array_key_exists( 'activate', $args ) || null === $args['activate'] || (bool) $args['activate']
			);
		},
	)
);

flx_gql_query(
	'flxSetupHistory',
	array(
		'description' => __( 'A page of the update history with the backup of each record.', 'flx-setup' ),
		'type'        => 'FlxSetupHistory!',
		'cap'         => 'update_plugins',
		'args'        => array(
			'page' => array( 'type' => 'Int', 'description' => __( 'Page, from 1 (20 records per page).', 'flx-setup' ) ),
		),
		'resolve'     => static function ( $root, array $args ): array {
			flxk_gql_engine();
			return FLXK_Packages_Service::history( max( 1, flx_gql_int( $args['page'] ?? 1, 1 ) ) );
		},
	)
);

flx_gql_query(
	'flxSetupSettings',
	array(
		'description' => __( 'Connection and update settings.', 'flx-setup' ),
		'type'        => 'FlxSetupSettings!',
		'cap'         => 'manage_options',
		'resolve'     => static function (): array {
			flxk_gql_engine();
			return FLXK_Settings_Service::settings();
		},
	)
);

flx_gql_query(
	'flxSetupSources',
	array(
		'description' => __( 'The main update server and the other sources, in priority order.', 'flx-setup' ),
		'type'        => '[FlxSetupSource!]!',
		'cap'         => 'manage_options',
		'resolve'     => static function (): array {
			flxk_gql_engine();
			return FLXK_Settings_Service::sources();
		},
	)
);

flx_gql_query(
	'flxSetupConnection',
	array(
		'description' => __( 'Connection to the main update server.', 'flx-setup' ),
		'type'        => 'FlxSetupConnection!',
		'cap'         => flx_gql_any_cap( 'update_plugins', 'manage_options' ),
		'resolve'     => static function (): array {
			flxk_gql_engine();
			return FLXK_Packages_Service::connection();
		},
	)
);

flx_gql_query(
	'flxSetupSystemChecks',
	array(
		'description' => __( 'The rows FluxWP Panel adds to the system check: PHP version, Flux GraphQL, update server, other sources and packages waiting for an update.', 'flx-setup' ),
		'type'        => '[FlxSetupSystemCheck!]!',
		'cap'         => 'manage_options',
		'resolve'     => static fn(): array => FLXK_System_Service::checks(),
	)
);

flx_gql_query(
	'flxSetupGraphqlStatus',
	array(
		'description' => __( 'Flux GraphQL on this site and the PHP version it needs.', 'flx-setup' ),
		'type'        => 'FlxSetupGraphqlStatus!',
		'cap'         => 'manage_options',
		'resolve'     => static fn(): array => FLXK_System_Service::graphql(),
	)
);

/* ---------- Mutations ---------- */

flx_gql_mutation(
	'flxSetupCheck',
	array(
		'description' => __( 'Asks every update source for the latest versions ("Check for updates").', 'flx-setup' ),
		'cap'         => 'update_plugins',
		'input'       => array(),
		'output'      => array(
			'ok'           => array( 'type' => 'Boolean', 'description' => __( 'The main server answered.', 'flx-setup' ) ),
			'pendingCount' => array(
				'type'        => 'Int',
				'description' => __( 'FluxWP updates waiting (the menu badge).', 'flx-setup' ),
				'resolve'     => static fn( $p ): int => FLXK_Val::int( flxk_gql_src( $p )['pending'] ?? 0 ),
			),
			'checkedLabel' => array( 'type' => 'String', 'description' => __( 'Last check as a sentence.', 'flx-setup' ) ),
			'summary'      => array( 'type' => 'String', 'description' => __( 'Result in one line.', 'flx-setup' ) ),
		),
		'resolve'     => static function (): array {
			flxk_gql_engine();
			return FLXK_Packages_Service::check();
		},
	)
);

flx_gql_mutation(
	'flxSetupStepRun',
	array(
		'description' => __( 'Runs one install, update or version step. A backup is taken before updates. Send the steps of flxSetupPlan one by one.', 'flx-setup' ),
		'cap'         => flx_gql_any_cap( 'update_plugins', 'install_plugins' ),
		'input'       => array(
			'kind'    => array( 'type' => 'FlxSetupStepKind!', 'description' => __( 'Step kind.', 'flx-setup' ) ),
			'slug'    => array( 'type' => 'String!', 'description' => __( 'Package slug (or the row key of another maker).', 'flx-setup' ) ),
			'id'      => array( 'type' => 'String', 'description' => __( 'Plugin file or theme folder for the steps of other makers.', 'flx-setup' ) ),
			'version' => array( 'type' => 'String', 'description' => __( 'Version for a version step.', 'flx-setup' ) ),
		),
		'output'      => array(
			'ok'           => array( 'type' => 'Boolean', 'description' => __( 'The step succeeded.', 'flx-setup' ) ),
			'lines'        => array( 'type' => '[FlxSetupLine!]', 'description' => __( 'What happened.', 'flx-setup' ) ),
			'pendingCount' => array(
				'type'        => 'Int',
				'description' => __( 'FluxWP updates waiting after the step (the menu badge).', 'flx-setup' ),
				'resolve'     => static fn( $p ): int => FLXK_Val::int( flxk_gql_src( $p )['pending'] ?? 0 ),
			),
			'package'      => array(
				'type'        => 'FlxSetupPackage',
				'description' => __( 'The FluxWP package row after the step; empty for rows of other makers.', 'flx-setup' ),
				'resolve'     => static fn( $p ): ?array => isset( flxk_gql_src( $p )['row'] ) ? flx_gql_arr( flxk_gql_src( $p )['row'] ) : null,
			),
		),
		'resolve'     => static function ( array $input ): array {
			flxk_gql_engine();
			return FLXK_Packages_Service::run_step(
				flx_gql_str( $input['kind'] ?? '' ),
				sanitize_text_field( flx_gql_str( $input['slug'] ?? '' ) ),
				sanitize_text_field( flx_gql_str( $input['id'] ?? '' ) ),
				sanitize_text_field( flx_gql_str( $input['version'] ?? '' ) )
			);
		},
	)
);

flx_gql_mutation(
	'flxSetupManage',
	array(
		'description' => __( 'Activates, deactivates or deletes installed packages, or turns their automatic updates on or off. FluxWP Panel does not close or delete itself and the active theme is not deleted.', 'flx-setup' ),
		'cap'         => 'update_plugins',
		'input'       => array(
			'op'     => array( 'type' => 'FlxSetupManageOp!', 'description' => __( 'What to do.', 'flx-setup' ) ),
			'slugs'  => array( 'type' => '[String!]', 'description' => __( 'FluxWP packages.', 'flx-setup' ) ),
			'wpKeys' => array( 'type' => '[String!]', 'description' => __( 'Rows of other makers (keys like p:folder/file.php or t:folder).', 'flx-setup' ) ),
		),
		'output'      => array(
			'done'    => array( 'type' => 'Int', 'description' => __( 'Packages changed.', 'flx-setup' ) ),
			'skipped' => array(
				'type'        => '[String!]',
				'description' => __( 'Names skipped (already in that state, active theme or FluxWP Panel).', 'flx-setup' ),
				'resolve'     => static fn( $p ): array => FLXK_Val::strings( flxk_gql_src( $p )['skip'] ?? array() ),
			),
			'failed'  => array(
				'type'        => '[String!]',
				'description' => __( 'Names that failed.', 'flx-setup' ),
				'resolve'     => static fn( $p ): array => FLXK_Val::strings( flxk_gql_src( $p )['fail'] ?? array() ),
			),
			'summary' => array(
				'type'        => 'String',
				'description' => __( 'Result in one line.', 'flx-setup' ),
				'resolve'     => static function ( $p ): string {
					$r = flxk_gql_src( $p );
					return FLXK_Packages_Service::manage_summary(
						array(
							'op'   => FLXK_Val::str( $r['op'] ?? '' ),
							'done' => FLXK_Val::int( $r['done'] ?? 0 ),
							'skip' => FLXK_Val::strings( $r['skip'] ?? array() ),
							'fail' => FLXK_Val::strings( $r['fail'] ?? array() ),
						)
					);
				},
			),
		),
		'resolve'     => static function ( array $input ) {
			flxk_gql_engine();
			return FLXK_Packages_Service::manage(
				flx_gql_str( $input['op'] ?? '' ),
				flxk_gql_strings( $input['slugs'] ?? array() ),
				flxk_gql_strings( $input['wpKeys'] ?? array() )
			);
		},
	)
);

flx_gql_mutation(
	'flxSetupAutoUpdateSet',
	array(
		'description' => __( 'Turns WordPress\' automatic updates of one plugin or theme on or off.', 'flx-setup' ),
		'cap'         => flx_gql_any_cap( 'update_plugins', 'update_themes' ),
		'input'       => array(
			'type' => array( 'type' => 'FlxSetupAutoUpdateType!', 'description' => __( 'Plugin or theme.', 'flx-setup' ) ),
			'id'   => array( 'type' => 'String!', 'description' => __( 'Plugin file or theme folder (FlxSetupAutoUpdate.id).', 'flx-setup' ) ),
			'on'   => array( 'type' => 'Boolean!', 'description' => __( 'Turn on.', 'flx-setup' ) ),
		),
		'output'      => array(
			'on'    => array( 'type' => 'Boolean', 'description' => __( 'The setting now.', 'flx-setup' ) ),
			'label' => array( 'type' => 'String', 'description' => __( 'On or Off, in the user\'s language.', 'flx-setup' ) ),
		),
		'resolve'     => static function ( array $input ) {
			flxk_gql_engine();
			return FLXK_Packages_Service::set_auto( flx_gql_str( $input['type'] ?? '' ), sanitize_text_field( flx_gql_str( $input['id'] ?? '' ) ), ! empty( $input['on'] ) );
		},
	)
);

flx_gql_mutation(
	'flxSetupSettingsSave',
	array(
		'description' => __( 'Saves the connection settings and checks the server. Fields you leave out stay as they are; an empty key keeps the saved one.', 'flx-setup' ),
		'cap'         => 'manage_options',
		'input'       => array(
			'server'   => array( 'type' => 'String', 'description' => __( 'Server address (https); empty uses the default server.', 'flx-setup' ) ),
			'key'      => array( 'type' => 'String', 'description' => __( 'New site key; empty keeps the saved one.', 'flx-setup' ) ),
			'clearKey' => array( 'type' => 'Boolean', 'description' => __( 'Remove the saved key.', 'flx-setup' ) ),
			'auto'     => array( 'type' => 'Boolean', 'description' => __( 'Install new versions of FluxWP packages automatically.', 'flx-setup' ) ),
			'health'   => array( 'type' => 'Boolean', 'description' => __( 'Check the site after an update and roll back on a critical error.', 'flx-setup' ) ),
			'usage'    => array( 'type' => 'Boolean', 'description' => __( 'Send usage data (consent).', 'flx-setup' ) ),
		),
		'output'      => array(
			'settings'   => array(
				'type'        => 'FlxSetupSettings',
				'description' => __( 'The settings after the change.', 'flx-setup' ),
				'resolve'     => static fn(): array => FLXK_Settings_Service::settings(),
			),
			'connection' => array(
				'type'        => 'FlxSetupConnection',
				'description' => __( 'The connection after the check.', 'flx-setup' ),
				'resolve'     => static fn(): array => FLXK_Packages_Service::connection(),
			),
			'sources'    => array(
				'type'        => '[FlxSetupSource!]',
				'description' => __( 'The sources after the check.', 'flx-setup' ),
				'resolve'     => static fn(): array => FLXK_Settings_Service::sources(),
			),
		),
		'resolve'     => static function ( array $input ) {
			flxk_gql_engine();
			$in = array();
			foreach ( array( 'server', 'key' ) as $k ) {
				if ( array_key_exists( $k, $input ) && null !== $input[ $k ] ) {
					$in[ $k ] = flx_gql_str( $input[ $k ] );
				}
			}
			foreach ( array( 'clearKey', 'auto', 'health', 'usage' ) as $k ) {
				if ( array_key_exists( $k, $input ) && null !== $input[ $k ] ) {
					$in[ $k ] = (bool) $input[ $k ];
				}
			}
			$r = FLXK_Settings_Service::save( $in );
			return $r instanceof WP_Error ? $r : array();
		},
	)
);

flx_gql_mutation(
	'flxSetupSourceAdd',
	array(
		'description' => __( 'Tests and adds an update source (a public GitHub repository or a FluxWP update server) and loads its packages.', 'flx-setup' ),
		'cap'         => 'manage_options',
		'input'       => array(
			'url'  => array( 'type' => 'String!', 'description' => __( 'Repository or server address (https only).', 'flx-setup' ) ),
			'name' => array( 'type' => 'String', 'description' => __( 'Name; taken from the address when empty.', 'flx-setup' ) ),
			'key'  => array( 'type' => 'String', 'description' => __( 'Site key, only for servers that need one; sent only to that server.', 'flx-setup' ) ),
		),
		'output'      => array(
			'sources' => array(
				'type'        => '[FlxSetupSource!]',
				'description' => __( 'The sources after the change.', 'flx-setup' ),
				'resolve'     => static fn(): array => FLXK_Settings_Service::sources(),
			),
		),
		'resolve'     => static function ( array $input ) {
			flxk_gql_engine();
			$r = FLXK_Settings_Service::source_add( flx_gql_str( $input['name'] ?? '' ), flx_gql_str( $input['url'] ?? '' ), flx_gql_str( $input['key'] ?? '' ) );
			return $r instanceof WP_Error ? $r : array();
		},
	)
);

flx_gql_mutation(
	'flxSetupSourceChange',
	array(
		'description' => __( 'Removes, turns on or off, or moves up an update source. Packages installed from a removed source stay on the site.', 'flx-setup' ),
		'cap'         => 'manage_options',
		'input'       => array(
			'op' => array( 'type' => 'FlxSetupSourceOp!', 'description' => __( 'What to do.', 'flx-setup' ) ),
			'id' => array( 'type' => 'String!', 'description' => __( 'Source id.', 'flx-setup' ) ),
		),
		'output'      => array(
			'sources' => array(
				'type'        => '[FlxSetupSource!]',
				'description' => __( 'The sources after the change.', 'flx-setup' ),
				'resolve'     => static fn(): array => FLXK_Settings_Service::sources(),
			),
		),
		'resolve'     => static function ( array $input ) {
			flxk_gql_engine();
			$r = FLXK_Settings_Service::source_change( flx_gql_str( $input['op'] ?? '' ), flx_gql_str( $input['id'] ?? '' ) );
			return $r instanceof WP_Error ? $r : array();
		},
	)
);

flx_gql_mutation(
	'flxSetupBackupsDelete',
	array(
		'description' => __( 'Deletes update backups. Locked backups and other kinds of backups are never deleted.', 'flx-setup' ),
		'cap'         => 'manage_options',
		'input'       => array(
			'mode' => array( 'type' => 'FlxSetupBackupDeleteMode!', 'description' => __( 'Which backups.', 'flx-setup' ) ),
			'ids'  => array( 'type' => '[String!]', 'description' => __( 'Backup ids for one or selected.', 'flx-setup' ) ),
		),
		'output'      => array(
			'deleted'   => array( 'type' => 'Int', 'description' => __( 'Backups deleted.', 'flx-setup' ) ),
			'retention' => array(
				'type'        => 'FlxSetupRetention',
				'description' => __( 'Update backups left.', 'flx-setup' ),
				'resolve'     => static fn(): array => FLXK_Packages_Service::retention(),
			),
		),
		'resolve'     => static function ( array $input ): array {
			flxk_gql_engine();
			return array(
				'deleted' => FLXK_Packages_Service::delete_backups( flx_gql_str( $input['mode'] ?? '' ), flxk_gql_strings( $input['ids'] ?? array() ) ),
			);
		},
	)
);

flx_gql_mutation(
	'flxSetupRetentionSave',
	array(
		'description' => __( 'Sets how long update backups are kept and applies it to the existing ones.', 'flx-setup' ),
		'cap'         => 'manage_options',
		'input'       => array(
			'days' => array( 'type' => 'Int!', 'description' => __( 'Days (1–365).', 'flx-setup' ) ),
		),
		'output'      => array(
			'deleted'   => array( 'type' => 'Int', 'description' => __( 'Expired backups deleted now.', 'flx-setup' ) ),
			'retention' => array(
				'type'        => 'FlxSetupRetention',
				'description' => __( 'Retention after the change.', 'flx-setup' ),
				'resolve'     => static fn(): array => FLXK_Packages_Service::retention(),
			),
		),
		'resolve'     => static function ( array $input ) {
			flxk_gql_engine();
			$n = FLXK_Packages_Service::set_retention( flx_gql_int( $input['days'] ?? 0 ) );
			return $n instanceof WP_Error ? $n : array( 'deleted' => $n );
		},
	)
);

/* ---------- Setup wizard ---------- */

flx_gql_mutation(
	'flxSetupWizardStep',
	array(
		'description' => __( 'Runs one step of the setup wizard (site name, tagline, logo, pages, theme, colour, preset, consent, finish or skip).', 'flx-setup' ),
		'cap'         => static fn(): bool => flxk_gql_wizard_cap(),
		'input'       => array(
			'kind'  => array( 'type' => 'FlxSetupWizardStepKind!', 'description' => __( 'Step.', 'flx-setup' ) ),
			'value' => array( 'type' => 'String', 'description' => __( 'Value of the step; see the step kinds.', 'flx-setup' ) ),
		),
		'output'      => array(
			'ok'    => array( 'type' => 'Boolean', 'description' => __( 'The step succeeded.', 'flx-setup' ) ),
			'lines' => array( 'type' => '[FlxSetupLine!]', 'description' => __( 'What happened.', 'flx-setup' ) ),
			'pages' => array( 'type' => 'Int', 'description' => __( 'Published pages after the step, when it changes them.', 'flx-setup' ) ),
		),
		'resolve'     => static function ( array $input ) {
			return FLXK_Wizard_Service::step( flx_gql_str( $input['kind'] ?? '' ), sanitize_text_field( flx_gql_str( $input['value'] ?? '' ) ) );
		},
	)
);

flx_gql_mutation(
	'flxSetupWizardUpdates',
	array(
		'description' => __( 'Asks the update server and lists the installed packages that have an update, before the wizard installs anything.', 'flx-setup' ),
		'cap'         => static fn(): bool => flxk_gql_wizard_cap(),
		'input'       => array(),
		'output'      => array(
			'ok'    => array( 'type' => 'Boolean', 'description' => __( 'The server answered.', 'flx-setup' ) ),
			'error' => array( 'type' => 'String', 'description' => __( 'Why it did not; empty when it did.', 'flx-setup' ) ),
			'items' => array( 'type' => '[FlxSetupWizardUpdate!]', 'description' => __( 'Packages with an update; FluxWP Panel itself first.', 'flx-setup' ) ),
		),
		'resolve'     => static function (): array {
			flxk_gql_engine();
			return FLXK_Wizard_Service::updates();
		},
	)
);

flx_gql_mutation(
	'flxSetupWizardLogo',
	array(
		'description' => __( 'Adds an uploaded image (POST /wp-json/flx/v1/upload, context flx-setup/logo) to the media library for the logo.', 'flx-setup' ),
		'cap'         => static fn(): bool => flxk_gql_wizard_cap(),
		'input'       => array(
			'uploadId' => array( 'type' => 'String!', 'description' => __( 'Upload id returned by the upload endpoint.', 'flx-setup' ) ),
		),
		'output'      => array(
			'attachmentId' => array(
				'type'        => 'Int',
				'description' => __( 'Media library id; pass it to the logo step.', 'flx-setup' ),
				'resolve'     => static fn( $p ): ?int => isset( flxk_gql_src( $p )['id'] ) ? FLXK_Val::int( flxk_gql_src( $p )['id'] ) : null,
			),
			'url'          => array( 'type' => 'String', 'description' => __( 'Medium size image address.', 'flx-setup' ) ),
		),
		'resolve'     => static function ( array $input ) {
			$file = flx_gql_upload_take( flx_gql_str( $input['uploadId'] ?? '' ), FLXK_Wizard_Service::LOGO_CONTEXT );
			if ( $file instanceof WP_Error ) {
				return $file;
			}
			return FLXK_Wizard_Service::logo_from_file(
				flx_gql_str( $file['path'] ?? '' ),
				flx_gql_str( $file['name'] ?? 'logo' ),
				flx_gql_str( $file['mime'] ?? '' )
			);
		},
	)
);
