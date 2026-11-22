<?php
/**
 * Packages: the one place that reads the package table, runs install / update / version steps, bulk
 * actions and auto-update switches, and reads the update history and its backups. The classic
 * Packages page (admin-post and admin-ajax handlers), GraphQL and the setup wizard call this.
 *
 * Permission checks stay with the caller, as before (update_plugins to see the page; each step and bulk
 * action checks the capability of the thing it changes inside the service).
 *
 * Errors are WP_Error with `graphql_code` INVALID_INPUT / NOT_FOUND / FORBIDDEN and the input `field`.
 *
 * @package FLX_Setup
 */

defined( 'ABSPATH' ) || exit;

final class FLXK_Packages_Service {

	/** Rows of one history page. */
	public const HISTORY_PER_PAGE = 20;

	/** Steps the browser may run, one request each. */
	public const STEP_KINDS = array( 'install', 'wporg', 'activate', 'update', 'version', 'wp_plugin', 'wp_theme', 'wp_lang' );

	/** Bulk actions on installed packages. */
	public const MANAGE_OPS = array( 'activate', 'deactivate', 'delete', 'auto_on', 'auto_off' );

	/**
	 * Whether the update engine is loaded (it is not when the old Flux Updater plugin is still active).
	 */
	public static function ready(): bool {
		return class_exists( 'FLXU_Updater' ) && class_exists( 'FLXU_Admin' );
	}

	/**
	 * Error for an unavailable engine.
	 */
	public static function unavailable(): WP_Error {
		return new WP_Error(
			'flxk_unavailable',
			__( 'The update engine is not available: the old Flux Updater plugin is still active. Deactivate it.', 'flx-setup' ),
			array( 'graphql_code' => 'NOT_FOUND' )
		);
	}

	/* ------------------------------------------------------------------ */
	/* Connection                                                          */
	/* ------------------------------------------------------------------ */

	/**
	 * Connection state of the main update server, as the status card shows it.
	 *
	 * @return array{state:string,title:string,message:string,host:string,site:string,checked:int|null,checkedLabel:string,backingOff:bool,hasKey:bool,keyFromConfig:bool,packageCount:int,updateCount:int}
	 */
	public static function connection(): array {
		$status = FLXU_Updater::status();
		$state  = FLXU_Admin::connection_state( $status );
		$host   = (string) wp_parse_url( FLXU_Updater::server(), PHP_URL_HOST );
		$key    = FLXU_Updater::key();
		$site   = FLXK_Val::str( $status['site'] ?? '' );
		switch ( $state ) {
			case 'connected':
				$title = __( 'Connected', 'flx-setup' );
				if ( '' !== FLXK_Val::str( $status['error'] ?? '' ) ) {
					$line = FLXK_Val::str( $status['error'] );
				} elseif ( '' === $key ) {
					/* translators: %s: server host */
					$line = sprintf( __( 'Connected without a key (%s): public packages are available. Enter a site key for private packages.', 'flx-setup' ), $host );
				} else {
					/* translators: 1: site name on server, 2: server host */
					$line = sprintf( __( 'Recognized by the server as "%1$s" (%2$s).', 'flx-setup' ), $site, $host );
				}
				break;
			case 'error':
				$title = FLXU_Updater::block_title( $status );
				$title = '' !== $title ? $title : __( 'Connection error', 'flx-setup' );
				$line  = FLXU_Updater::status_message( $status, '' !== $key, $host );
				if ( 'paused' === FLXK_Val::str( $status['code'] ?? '' ) ) {
					$state = 'paused';
				}
				break;
			default:
				$title = __( 'Not checked yet', 'flx-setup' );
				$line  = __( 'Connect to the server with "Test connection".', 'flx-setup' );
		}
		$catalog = self::catalog();
		$updates = 0;
		foreach ( FLXU_Admin::rows( $catalog ) as $row ) {
			if ( 'update' === FLXK_Val::str( FLXK_Val::arr( $row )['state'] ?? '' ) ) {
				++$updates;
			}
		}
		$checked = FLXK_Val::int( $status['checked'] ?? 0 );
		return array(
			'state'         => $state,
			'title'         => $title,
			'message'       => $line,
			'host'          => $host,
			'site'          => $site,
			'checked'       => $checked > 0 ? $checked : null,
			'checkedLabel'  => FLXU_Admin::checked_label( $status ),
			'backingOff'    => FLXU_Updater::backing_off( $status ),
			'hasKey'        => '' !== $key,
			'keyFromConfig' => FLXU_Updater::key_from_config(),
			'packageCount'  => count( $catalog ),
			'updateCount'   => $updates,
		);
	}

	/**
	 * The catalog rows with string keys.
	 *
	 * @return array<string,array<string,mixed>>
	 */
	private static function catalog(): array {
		$out = array();
		foreach ( FLXU_Updater::instance()->catalog() as $slug => $p ) {
			if ( is_array( $p ) ) {
				$out[ (string) $slug ] = FLXK_Val::arr( $p );
			}
		}
		return $out;
	}

	/**
	 * "Check for updates": asks every source, returns the new state.
	 *
	 * @return array{ok:bool,pending:int,checkedLabel:string,summary:string}
	 */
	public static function check(): array {
		FLXU_Updater::instance()->check_now();
		$status  = FLXU_Updater::status();
		$pending = FLXU_Admin::pending_count();
		if ( empty( $status['ok'] ) ) {
			$summary = __( 'Could not connect to the server.', 'flx-setup' );
		} elseif ( $pending ) {
			/* translators: %d: number of updates */
			$summary = sprintf( _n( '%d update available', '%d updates available', $pending, 'flx-setup' ), $pending );
		} else {
			$summary = __( 'All up to date ✓', 'flx-setup' );
		}
		return array(
			'ok'           => ! empty( $status['ok'] ),
			'pending'      => $pending,
			'checkedLabel' => FLXU_Admin::checked_label( $status ),
			'summary'      => $summary,
		);
	}

	/**
	 * Whether the page should check for updates by itself on open (the last check is not recent and the
	 * server did not ask to wait).
	 */
	public static function should_auto_check(): bool {
		$ttl = FLXK_Val::int( apply_filters( 'flxk_auto_check_after', 2 * MINUTE_IN_SECONDS ) );
		if ( $ttl <= 0 ) {
			return false;
		}
		$status = FLXU_Updater::status();
		return ! FLXU_Updater::backing_off( $status ) && ( time() - FLXK_Val::int( $status['checked'] ?? 0 ) ) >= $ttl;
	}

	/**
	 * Other sources that could not be reached ("Could not reach X: …").
	 *
	 * @return list<array{name:string,error:string}>
	 */
	public static function failing_sources(): array {
		$out = array();
		foreach ( FLXU_Sources::failing() as $f ) {
			$f     = FLXK_Val::arr( $f );
			$out[] = array(
				'name'  => FLXK_Val::str( $f['name'] ?? '' ),
				'error' => rtrim( FLXK_Val::str( $f['error'] ?? '' ), '.' ) . '.',
			);
		}
		return $out;
	}

	/**
	 * Flux Backup state for the strip under the table.
	 *
	 * @return array{ready:bool,installable:bool,activatable:bool,installed:bool}
	 */
	public static function backup_state(): array {
		$files = FLXU_Updater::plugin_files();
		return array(
			'ready'       => FLXU_Guard::backup_ready(),
			'installed'   => isset( $files['flx-backup'] ),
			'activatable' => isset( $files['flx-backup'] ) && current_user_can( 'activate_plugins' ),
			'installable' => ! isset( $files['flx-backup'] ) && isset( self::catalog()['flx-backup'] ) && current_user_can( 'install_plugins' ),
		);
	}

	/* ------------------------------------------------------------------ */
	/* The table                                                           */
	/* ------------------------------------------------------------------ */

	/**
	 * Installed plugin files by slug.
	 *
	 * @return array<string,string>
	 */
	private static function plugin_files(): array {
		$out = array();
		foreach ( FLXU_Updater::plugin_files() as $k => $v ) {
			$out[ (string) $k ] = FLXK_Val::str( $v );
		}
		return $out;
	}

	/**
	 * Tabs of the table (key => label).
	 *
	 * @return array<string,string>
	 */
	public static function tabs(): array {
		return array(
			'all'     => __( 'All', 'flx-setup' ),
			'flux'    => __( 'FluxWP', 'flx-setup' ),
			'plugins' => __( 'Plugins', 'flx-setup' ),
			'themes'  => __( 'Themes', 'flx-setup' ),
			'core'    => __( 'WordPress & translations', 'flx-setup' ),
		);
	}

	/**
	 * Every row of the table in the classic order: updates first, then active, not installed, inactive
	 * last (FluxWP before the rest at each step).
	 *
	 * @return list<array<string,mixed>>
	 */
	public static function all_rows(): array {
		// While the page is drawn (FLXU_Updater::$offline) the preload asks for the table several times: built once.
		/** @var list<array<string,mixed>>|null $memo */
		static $memo = null;
		if ( FLXU_Updater::$offline && null !== $memo ) {
			return $memo;
		}
		$memo = null;
		$out  = self::build_rows();
		if ( FLXU_Updater::$offline ) {
			$memo = $out;
		}
		return $out;
	}

	/**
	 * The table rows (see all_rows()).
	 *
	 * @return list<array<string,mixed>>
	 */
	private static function build_rows(): array {
		$catalog = self::catalog();
		$flux    = FLXU_Admin::rows( $catalog );
		$wp      = FLXU_WP::rows( $catalog );
		$files   = self::plugin_files();
		$plugins = array();
		foreach ( FLXU_Installer::installed_plugins() as $slug => $d ) {
			$plugins[ (string) $slug ] = FLXK_Val::arr( $d );
		}
		$auto_all = self::flux_auto_all();
		$out      = array();
		foreach ( array( 0, 1, 2, 3 ) as $rank ) {
			foreach ( $flux as $r ) {
				$r = FLXK_Val::arr( $r );
				if ( FLXU_WP::sort_rank( FLXK_Val::str( $r['state'] ?? '' ), FLXK_Val::bool( $r['active'] ?? false ) ) === $rank ) {
					$slug  = FLXK_Val::str( $r['slug'] ?? '' );
					$out[] = self::flux_row( $r, FLXK_Val::arr( $catalog[ $slug ] ?? array() ), $files, $plugins, $auto_all, $catalog );
				}
			}
			foreach ( $wp as $r ) {
				$r = FLXK_Val::arr( $r );
				if ( FLXU_WP::sort_rank( FLXK_Val::str( $r['state'] ?? '' ), FLXK_Val::bool( $r['active'] ?? false ) ) === $rank ) {
					$out[] = self::wp_row( $r );
				}
			}
		}
		return $out;
	}

	/**
	 * "Install new versions of FluxWP packages automatically" is on.
	 */
	private static function flux_auto_all(): bool {
		return ! empty( FLXU_Updater::settings()['auto'] );
	}

	/**
	 * Auto-update cell: type plugin|theme (empty: none), id (plugin file or theme folder), mode.
	 *
	 * @return array{mode:string,type:string,id:string,canChange:bool}
	 */
	private static function auto_cell( string $type, string $id, bool $flux ): array {
		$none = array(
			'mode'      => 'unavailable',
			'type'      => '',
			'id'        => '',
			'canChange' => false,
		);
		if ( '' === $id || ! in_array( $type, array( 'plugin', 'theme' ), true ) ) {
			return $none;
		}
		if ( $flux && self::flux_auto_all() ) {
			return array(
				'mode'      => 'locked',
				'type'      => $type,
				'id'        => $id,
				'canChange' => false,
			);
		}
		if ( ! wp_is_auto_update_enabled_for_type( $type ) ) {
			return $none;
		}
		$opt = 'theme' === $type ? 'auto_update_themes' : 'auto_update_plugins';
		$on  = in_array( $id, FLXK_Val::strings( get_site_option( $opt, array() ) ), true );
		return array(
			'mode'      => $on ? 'on' : 'off',
			'type'      => $type,
			'id'        => $id,
			'canChange' => current_user_can( 'theme' === $type ? 'update_themes' : 'update_plugins' ),
		);
	}

	/**
	 * A FluxWP package row.
	 *
	 * @param array<string,mixed>                $r        FLXU_Admin::rows() row.
	 * @param array<string,mixed>                $p        Catalog entry.
	 * @param array<string,string>               $files    Slug => plugin file.
	 * @param array<string,array<string,mixed>>  $plugins  Installed plugins by slug.
	 * @param bool                               $auto_all Automatic updates of every FluxWP package are on.
	 * @param array<string,array<string,mixed>>  $catalog  Catalog.
	 * @return array<string,mixed>
	 */
	private static function flux_row( array $r, array $p, array $files, array $plugins, bool $auto_all, array $catalog ): array {
		$slug      = FLXK_Val::str( $r['slug'] ?? '' );
		$type      = FLXK_Val::str( $r['type'] ?? 'plugin' );
		$state     = FLXK_Val::str( $r['state'] ?? 'current' );
		$installed = FLXK_Val::str( $r['installed'] ?? '' );
		$released  = FLXK_Val::str( $r['released'] ?? '' );
		$theme     = 'theme' === $type;
		$cat_key   = FLXK_Val::str( $p['category'] ?? '' );
		$cat       = FLXU_WP::category_label( $cat_key );
		$icon      = FLXU_Updater::icons( $slug );
		$sources   = FLXK_Val::arr( $p['sources'] ?? array() );
		$src_main  = FLXK_Val::str( $p['source'] ?? 'main' );
		$req       = array();
		foreach ( FLXU_Updater::requires_plugins( $slug, $p ) as $dep ) {
			$dep   = FLXK_Val::str( $dep );
			$have  = $plugins[ $dep ] ?? null;
			$req[] = array(
				'slug'  => $dep,
				'name'  => FLXU_Installer::plugin_name( $dep, $catalog, array_map( 'FLXK_Val::arr', $plugins ) ),
				'state' => null === $have ? 'missing' : ( FLXK_Val::bool( $have['active'] ?? false ) ? 'active' : 'inactive' ),
				'flux'  => isset( $catalog[ $dep ] ),
			);
		}
		$versions = array();
		foreach ( FLXK_Val::rows( $p['versions'] ?? array() ) as $v ) {
			$ver = FLXK_Val::str( $v['version'] ?? '' );
			if ( '' === $ver || $ver === $installed ) {
				continue;
			}
			$rel        = FLXK_Val::str( $v['released'] ?? '' );
			$versions[] = array(
				'version'  => $ver,
				'released' => '' !== $rel && false !== strtotime( $rel ) ? (int) strtotime( $rel ) : null,
				'older'    => '' !== $installed && version_compare( $ver, $installed, '<' ),
			);
		}
		$file     = $theme ? $slug : ( $files[ $slug ] ?? '' );
		$preview  = FLXU_Previews::data( $slug, $r, $p );
		$can_up   = current_user_can( $theme ? 'update_themes' : 'update_plugins' );
		$can_ins  = current_user_can( $theme ? 'install_themes' : 'install_plugins' );
		$tags     = array( array( 'label' => 'FluxWP', 'kind' => 'flux' ) );
		$tag_cat  = '' !== $cat ? $cat : ( $theme ? __( 'Theme', 'flx-setup' ) : '' );
		if ( '' !== $tag_cat ) {
			$tags[] = array( 'label' => $tag_cat, 'kind' => 'cat' );
		}
		return array(
			'key'         => $slug,
			'group'       => 'flux',
			'kind'        => $theme ? 'flux_theme' : 'flux_plugin',
			'id'          => $slug,
			'name'        => FLXK_Val::str( $r['name'] ?? $slug ),
			'subtitle'    => $slug,
			'author'      => '',
			'state'       => $state,
			'installed'   => $installed,
			'latest'      => FLXK_Val::str( $r['latest'] ?? '' ),
			'released'    => '' !== $released && false !== strtotime( $released ) ? (int) strtotime( $released ) : null,
			'active'      => FLXK_Val::bool( $r['active'] ?? false ),
			'iconUrl'     => FLXK_Val::str( $icon['svg'] ?? '' ),
			'tags'        => $tags,
			'category'    => $cat,
			'sourceName'  => FLXK_Val::str( $p['source_name'] ?? '' ),
			'showSource'  => 'main' !== $src_main || count( $sources ) > 1,
			'sourceCount' => count( $sources ),
			'requires'    => $req,
			'auto'        => self::auto_cell( $theme ? 'theme' : 'plugin', $file, true ),
			'canUpdate'   => 'update' === $state && $can_up,
			'canInstall'  => 'missing' === $state && $can_ins,
			'canManage'   => ( 'update' === $state && $can_up ) || ( 'missing' === $state && $can_ins ) || ( 'current' === $state && self::can_manage( $type ) ),
			'canVersion'  => '' !== $installed && $versions && $can_up,
			'versions'    => $versions,
			'description' => $preview['description'],
			'website'     => $preview['website'],
			'images'      => $preview['images'],
			'info'        => self::info_rows(
				array(
					array( $theme ? __( 'Folder', 'flx-setup' ) : __( 'Slug', 'flx-setup' ), $slug ),
					array( __( 'Type', 'flx-setup' ), $theme ? __( 'Theme', 'flx-setup' ) : __( 'Plugin', 'flx-setup' ) ),
					array( __( 'Category', 'flx-setup' ), $cat ),
					array( __( 'Installed', 'flx-setup' ), $installed ),
					array( __( 'Latest version', 'flx-setup' ), FLXK_Val::str( $r['latest'] ?? '' ) ),
					array( __( 'Released', 'flx-setup' ), '' !== $released && false !== strtotime( $released ) ? FLXK_Val::str( flx_date( (int) strtotime( $released ) ) ) : '' ),
					array( __( 'Source', 'flx-setup' ), FLXK_Val::str( $p['source_name'] ?? '' ) ),
				)
			),
			'sourceNote'  => '',
			'wpAuto'      => $auto_all,
		);
	}

	/**
	 * A row that is not a FluxWP package: plugin, theme, translations, core.
	 *
	 * @param array<string,mixed> $r FLXU_WP::rows() row.
	 * @return array<string,mixed>
	 */
	private static function wp_row( array $r ): array {
		$kind  = FLXK_Val::str( $r['kind'] ?? '' );
		$state = FLXK_Val::str( $r['state'] ?? 'current' );
		$types = array(
			'wp_plugin' => __( 'Plugin', 'flx-setup' ),
			'wp_theme'  => __( 'Theme', 'flx-setup' ),
			'wp_lang'   => __( 'Translation', 'flx-setup' ),
			'wp_core'   => __( 'Core', 'flx-setup' ),
		);
		$third = in_array( $kind, array( 'wp_plugin', 'wp_theme' ), true );
		$id    = FLXK_Val::str( $r['id'] ?? '' );
		$core  = in_array( $kind, array( 'wp_core', 'wp_lang' ), true );
		$gkind = array(
			'wp_plugin' => 'WP_PLUGIN',
			'wp_theme'  => 'WP_THEME',
			'wp_lang'   => 'WP_TRANSLATIONS',
			'wp_core'   => 'WP_CORE',
		);
		$sub   = FLXK_Val::str( $r['sub'] ?? '' );
		return array(
			'key'         => FLXK_Val::str( $r['key'] ?? '' ),
			'group'       => FLXK_Val::str( $r['group'] ?? 'plugins' ),
			'kind'        => strtolower( $gkind[ $kind ] ?? 'wp_plugin' ),
			'id'          => $id,
			'name'        => FLXK_Val::str( $r['name'] ?? '' ),
			'subtitle'    => $sub,
			'author'      => FLXK_Val::str( $r['author'] ?? '' ),
			'state'       => $state,
			'installed'   => FLXK_Val::str( $r['installed'] ?? '' ),
			'latest'      => FLXK_Val::str( $r['latest'] ?? '' ),
			'released'    => null,
			'active'      => FLXK_Val::bool( $r['active'] ?? false ),
			'iconUrl'     => FLXK_Val::str( $r['icon'] ?? '' ),
			'tags'        => array(
				array(
					'label' => $core ? 'WordPress' : __( 'General', 'flx-setup' ),
					'kind'  => 'general',
				),
				array(
					'label' => $types[ $kind ] ?? '',
					'kind'  => 'type',
				),
			),
			'category'    => '',
			'sourceName'  => '',
			'showSource'  => false,
			'sourceCount' => 0,
			'requires'    => array(),
			'auto'        => self::auto_cell( 'wp_plugin' === $kind ? 'plugin' : ( 'wp_theme' === $kind ? 'theme' : '' ), $id, false ),
			'canUpdate'   => 'update' === $state && FLXK_Val::bool( $r['can'] ?? false ),
			'canInstall'  => false,
			'canManage'   => $third,
			'canVersion'  => false,
			'versions'    => array(),
			'description' => FLXK_Val::str( $r['desc'] ?? ( $third || 'wp_lang' === $kind ? '' : $sub ) ),
			'website'     => esc_url_raw( FLXK_Val::str( $r['web'] ?? '' ), array( 'https', 'http' ) ),
			'images'      => array(),
			'info'        => self::info_rows(
				array(
					array( 'wp_theme' === $kind ? __( 'Folder', 'flx-setup' ) : __( 'File', 'flx-setup' ), $third ? $id : '' ),
					array( __( 'Type', 'flx-setup' ), $types[ $kind ] ?? '' ),
					array( __( 'Author', 'flx-setup' ), FLXK_Val::str( $r['author'] ?? '' ) ),
					array( __( 'Installed', 'flx-setup' ), FLXK_Val::str( $r['installed'] ?? '' ) ),
					array( __( 'Latest version', 'flx-setup' ), FLXK_Val::str( $r['latest'] ?? '' ) ),
					array( __( 'Status', 'flx-setup' ), $third ? ( FLXK_Val::bool( $r['active'] ?? false ) ? __( 'Active', 'flx-setup' ) : __( 'Inactive', 'flx-setup' ) ) : '' ),
				)
			),
			'sourceNote'  => 'wp_lang' === $kind && 'update' === $state ? $sub : '',
			'wpAuto'      => false,
		);
	}

	/**
	 * Detail rows (label, value) without the empty values.
	 *
	 * @param list<array{0:string,1:string}> $meta Label/value pairs.
	 * @return list<array{label:string,value:string}>
	 */
	private static function info_rows( array $meta ): array {
		$out = array();
		foreach ( $meta as $m ) {
			if ( '' !== $m[1] ) {
				$out[] = array(
					'label' => $m[0],
					'value' => $m[1],
				);
			}
		}
		return $out;
	}

	/**
	 * Whether the bulk actions (activate, deactivate, delete) apply to an installed package of this type.
	 */
	public static function can_manage( string $type ): bool {
		return 'theme' === $type ? current_user_can( 'delete_themes' ) : ( current_user_can( 'activate_plugins' ) || current_user_can( 'delete_plugins' ) );
	}

	/**
	 * Rows of a tab, optionally searched and limited to the ones with an update.
	 *
	 * @param string $tab          all|flux|plugins|themes|core.
	 * @param string $search       Search text.
	 * @param bool   $only_updates Only rows with an update.
	 * @return list<array<string,mixed>>
	 */
	public static function rows( string $tab = 'all', string $search = '', bool $only_updates = false ): array {
		$q   = self::norm( $search );
		$out = array();
		foreach ( self::all_rows() as $r ) {
			if ( 'all' !== $tab && $tab !== FLXK_Val::str( $r['group'] ?? '' ) ) {
				continue;
			}
			if ( $only_updates && 'update' !== FLXK_Val::str( $r['state'] ?? '' ) ) {
				continue;
			}
			if ( '' !== $q && false === strpos( self::norm( self::haystack( $r ) ), $q ) ) {
				continue;
			}
			$out[] = $r;
		}
		return $out;
	}

	/**
	 * Text the search looks in.
	 *
	 * @param array<string,mixed> $r Row.
	 */
	private static function haystack( array $r ): string {
		$flux = 'flux' === FLXK_Val::str( $r['group'] ?? '' );
		return FLXK_Val::str( $r['name'] ?? '' ) . ' ' . FLXK_Val::str( $r['subtitle'] ?? '' ) . ' ' . FLXK_Val::str( $r['author'] ?? '' ) . ( $flux ? ' fluxwp ' . FLXK_Val::str( $r['category'] ?? '' ) : '' );
	}

	/** Lower case without accents, Turkish dotless i folded (the classic search did the same). */
	private static function norm( string $s ): string {
		$s = str_replace( array( 'ı', 'İ' ), 'i', $s );
		$s = remove_accents( $s );
		return trim( function_exists( 'mb_strtolower' ) ? mb_strtolower( $s ) : strtolower( $s ) );
	}

	/**
	 * Counts per tab: [ tab => [ rows, updates ] ] for the rows that match the search.
	 *
	 * @param string $search Search text.
	 * @return list<array{tab:string,label:string,count:int,updates:int}>
	 */
	public static function tab_counts( string $search = '' ): array {
		$q      = self::norm( $search );
		$counts = array();
		foreach ( array_keys( self::tabs() ) as $t ) {
			$counts[ $t ] = array( 0, 0 );
		}
		foreach ( self::all_rows() as $r ) {
			if ( '' !== $q && false === strpos( self::norm( self::haystack( $r ) ), $q ) ) {
				continue;
			}
			foreach ( array( 'all', FLXK_Val::str( $r['group'] ?? '' ) ) as $g ) {
				if ( ! isset( $counts[ $g ] ) ) {
					continue;
				}
				++$counts[ $g ][0];
				if ( 'update' === FLXK_Val::str( $r['state'] ?? '' ) ) {
					++$counts[ $g ][1];
				}
			}
		}
		$out = array();
		foreach ( self::tabs() as $key => $label ) {
			$out[] = array(
				'tab'     => (string) $key,
				'label'   => $label,
				'count'   => $counts[ $key ][0],
				'updates' => $counts[ $key ][1],
			);
		}
		return $out;
	}

	/**
	 * Updates waiting: FluxWP packages and the others (not core).
	 *
	 * @return int
	 */
	public static function update_count(): int {
		$n = 0;
		foreach ( self::all_rows() as $r ) {
			if ( 'update' === FLXK_Val::str( $r['state'] ?? '' ) && 'wp_core' !== FLXK_Val::str( $r['kind'] ?? '' ) && ( FLXK_Val::bool( $r['canUpdate'] ?? false ) ) ) {
				++$n;
			}
		}
		return $n;
	}

	/**
	 * One row by key.
	 *
	 * @param string $key Row key.
	 * @return array<string,mixed>|null
	 */
	public static function row( string $key ): ?array {
		foreach ( self::all_rows() as $r ) {
			if ( FLXK_Val::str( $r['key'] ?? '' ) === $key ) {
				return $r;
			}
		}
		return null;
	}

	/* ------------------------------------------------------------------ */
	/* Plan and steps                                                      */
	/* ------------------------------------------------------------------ */

	/**
	 * Turns a selection into ordered steps: required plugins first, then installs, then updates, then the
	 * selected plugins, themes and translations of other makers.
	 *
	 * @param list<string> $slugs    Selected FluxWP packages.
	 * @param list<string> $wp_keys  Selected other rows (p:file, t:folder, lang).
	 * @param bool         $all      Every row with an update.
	 * @param bool         $activate Activate installed packages.
	 * @param bool         $include_wp Add the steps of the other rows (the classic page queues those in the browser).
	 * @return list<array{kind:string,slug:string,id:string,name:string,why:string,version:string}>
	 */
	public static function plan( array $slugs, array $wp_keys, bool $all, bool $activate, bool $include_wp = true ): array {
		$catalog = self::catalog();
		$rows    = FLXU_Admin::rows( $catalog );
		if ( $all ) {
			$slugs = array();
			foreach ( $rows as $r ) {
				$r = FLXK_Val::arr( $r );
				if ( 'update' === FLXK_Val::str( $r['state'] ?? '' ) ) {
					$slugs[] = FLXK_Val::str( $r['slug'] ?? '' );
				}
			}
		}
		$plan  = FLXU_Installer::plan( $slugs, $catalog, $rows );
		$steps = array();
		foreach ( FLXU_Installer::steps( $plan, $catalog, $activate ) as $st ) {
			$st      = FLXK_Val::arr( $st );
			$steps[] = array(
				'kind'    => FLXK_Val::str( $st['kind'] ?? '' ),
				'slug'    => FLXK_Val::str( $st['slug'] ?? '' ),
				'id'      => '',
				'name'    => FLXK_Val::str( $st['name'] ?? '' ),
				'why'     => FLXK_Val::str( $st['why'] ?? '' ),
				'version' => '',
			);
		}
		foreach ( $include_wp ? FLXU_WP::pending( FLXU_WP::rows( $catalog ) ) : array() as $r ) {
			$r = FLXK_Val::arr( $r );
			if ( ! $all && ! in_array( FLXK_Val::str( $r['key'] ?? '' ), $wp_keys, true ) ) {
				continue;
			}
			$steps[] = array(
				'kind'    => FLXK_Val::str( $r['kind'] ?? '' ),
				'slug'    => FLXK_Val::str( $r['key'] ?? '' ),
				'id'      => FLXK_Val::str( $r['id'] ?? '' ),
				'name'    => FLXK_Val::str( $r['name'] ?? '' ),
				'why'     => '',
				'version' => '',
			);
		}
		FLXU_Guard::remember_plan( $steps );
		return $steps;
	}

	/**
	 * Runs one step: install | wporg | activate | update | version (FluxWP package), wp_plugin | wp_theme |
	 * wp_lang (other makers; $id is the plugin file, the theme folder or empty).
	 *
	 * @param string $kind    Step kind.
	 * @param string $slug    FluxWP package slug, or the row key of another row.
	 * @param string $id      Plugin file / theme folder of another row.
	 * @param string $version Version for the "version" step.
	 * @return array{ok:bool,lines:list<array{ok:bool,text:string}>,pending:int,row:array<string,mixed>|null}
	 */
	public static function run_step( string $kind, string $slug, string $id = '', string $version = '' ): array {
		if ( ! in_array( $kind, self::STEP_KINDS, true ) ) {
			return self::step_result( self::fail_lines( __( 'Unknown action.', 'flx-setup' ) ), '' );
		}
		@set_time_limit( 300 ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged
		ob_start(); // Whatever an install or update prints must not break the response.
		if ( in_array( $kind, array( 'wp_plugin', 'wp_theme', 'wp_lang' ), true ) ) {
			$res = FLXU_WP::run_step( $kind, $id );
		} else {
			$res = FLXU_Ajax::run_step( $kind, $slug, $version );
		}
		ob_end_clean();
		$lines = array();
		foreach ( FLXK_Val::rows( $res['lines'] ?? array() ) as $l ) {
			$lines[] = array(
				'ok'   => FLXK_Val::bool( $l['ok'] ?? false ),
				'text' => FLXK_Val::str( $l['text'] ?? '' ),
			);
		}
		return self::step_result(
			array(
				'ok'    => ! empty( $res['ok'] ),
				'lines' => $lines,
			),
			in_array( $kind, array( 'wp_plugin', 'wp_theme', 'wp_lang' ), true ) ? '' : $slug
		);
	}

	/**
	 * @param array{ok:bool,lines:list<array{ok:bool,text:string}>} $res  Step result.
	 * @param string                                                 $slug FluxWP package the row of which is returned.
	 * @return array{ok:bool,lines:list<array{ok:bool,text:string}>,pending:int,row:array<string,mixed>|null}
	 */
	private static function step_result( array $res, string $slug ): array {
		return array(
			'ok'      => $res['ok'],
			'lines'   => $res['lines'],
			'pending' => FLXU_Admin::pending_count(),
			'row'     => '' !== $slug ? self::row( $slug ) : null,
		);
	}

	/**
	 * A failed step with one line.
	 *
	 * @param string $text Message.
	 * @return array{ok:bool,lines:list<array{ok:bool,text:string}>}
	 */
	private static function fail_lines( string $text ): array {
		return array(
			'ok'    => false,
			'lines' => array(
				array(
					'ok'   => false,
					'text' => $text,
				),
			),
		);
	}

	/* ------------------------------------------------------------------ */
	/* Bulk actions and auto-updates                                       */
	/* ------------------------------------------------------------------ */

	/**
	 * Bulk action on installed packages: activate, deactivate, delete, turn automatic updates on or off.
	 * Flux Panel does not close or delete itself; the active theme (and its parent) is not deleted; themes are
	 * not activated or deactivated here (Appearance › Themes does that).
	 *
	 * @param string       $op      One of MANAGE_OPS.
	 * @param list<string> $slugs   FluxWP packages.
	 * @param list<string> $wp_keys Other rows (p:file, t:folder).
	 * @return array{op:string,done:int,skip:list<string>,fail:list<string>}|WP_Error
	 */
	public static function manage( string $op, array $slugs, array $wp_keys ) {
		if ( ! in_array( $op, self::MANAGE_OPS, true ) ) {
			return new WP_Error( 'flxk_op', __( 'Unknown action.', 'flx-setup' ), array( 'graphql_code' => 'INVALID_INPUT', 'field' => 'op' ) );
		}
		require_once ABSPATH . 'wp-admin/includes/plugin.php';
		require_once ABSPATH . 'wp-admin/includes/file.php';
		require_once ABSPATH . 'wp-admin/includes/theme.php';

		$catalog = self::catalog();
		$files   = self::plugin_files();
		$all_pl  = get_plugins();
		$items   = array(); // Each: type, id, name.
		foreach ( array_unique( array_map( static fn( $s ): string => sanitize_key( FLXK_Val::str( $s ) ), $slugs ) ) as $slug ) {
			$p = $catalog[ $slug ] ?? null;
			if ( null === $p ) {
				continue;
			}
			$name    = FLXK_Val::str( $p['name'] ?? $slug );
			$items[] = 'theme' === FLXK_Val::str( $p['type'] ?? '' )
				? array( 'type' => 'theme', 'id' => $slug, 'name' => $name )
				: array( 'type' => 'plugin', 'id' => $files[ $slug ] ?? '', 'name' => $name );
		}
		foreach ( array_unique( array_map( static fn( $k ): string => sanitize_text_field( FLXK_Val::str( $k ) ), $wp_keys ) ) as $key ) {
			$id = (string) substr( $key, 2 );
			if ( 0 === strpos( $key, 'p:' ) && isset( $all_pl[ $id ] ) && ! isset( $catalog[ dirname( $id ) ] ) ) {
				$items[] = array( 'type' => 'plugin', 'id' => $id, 'name' => FLXK_Val::str( $all_pl[ $id ]['Name'] ?? $id ) );
			} elseif ( 0 === strpos( $key, 't:' ) && wp_get_theme( $id )->exists() && ! isset( $catalog[ $id ] ) ) {
				$items[] = array( 'type' => 'theme', 'id' => $id, 'name' => FLXK_Val::str( wp_get_theme( $id )->get( 'Name' ) ) );
			}
		}

		if ( 'auto_on' === $op || 'auto_off' === $op ) {
			// Everything would be skipped one by one with a misleading summary: say why once.
			$types = array_unique( array_column( $items, 'type' ) );
			if ( $types && ! array_filter( $types, static fn( $t ): bool => wp_is_auto_update_enabled_for_type( 'theme' === $t ? 'theme' : 'plugin' ) ) ) {
				return new WP_Error( 'flxk_auto', __( 'Automatic updates are turned off for this site.', 'flx-setup' ), array( 'graphql_code' => 'CONFLICT' ) );
			}
		}

		$done   = 0;
		$skip   = array();
		$fail   = array();
		$del_pl = array();
		$auto   = array(
			'plugin' => FLXK_Val::strings( get_site_option( 'auto_update_plugins', array() ) ),
			'theme'  => FLXK_Val::strings( get_site_option( 'auto_update_themes', array() ) ),
		);
		foreach ( $items as $it ) {
			$id   = $it['id'];
			$name = $it['name'];
			$type = 'theme' === $it['type'] ? 'theme' : 'plugin';
			// A plugin's own activation/deactivation hook or theme deletion can throw: that package fails, the rest go on.
			ob_start();
			try {
				$res = self::manage_one( $op, $type, $id, $auto, $del_pl );
			} catch ( \Throwable $e ) {
				error_log( 'flx-setup manage ' . $op . ' ' . $id . ': ' . $e->getMessage() ); // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
				$res = 'fail';
			}
			ob_end_clean();
			if ( 'queued' === $res ) {
				$del_pl[ $id ] = $name;
			} elseif ( 'done' === $res ) {
				++$done;
			} elseif ( 'skip' === $res ) {
				$skip[] = $name;
			} else {
				$fail[] = $name;
			}
			continue;
		}
		if ( 'auto_on' === $op || 'auto_off' === $op ) {
			update_site_option( 'auto_update_plugins', array_values( array_unique( $auto['plugin'] ) ) );
			update_site_option( 'auto_update_themes', array_values( array_unique( $auto['theme'] ) ) );
		}
		if ( $del_pl ) {
			// Deactivate first, then delete (WordPress does not delete an active plugin).
			ob_start();
			try {
				deactivate_plugins( array_keys( $del_pl ), true );
				$deleted = true === delete_plugins( array_keys( $del_pl ) );
			} catch ( \Throwable $e ) {
				error_log( 'flx-setup manage delete: ' . $e->getMessage() ); // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
				$deleted = false;
			}
			ob_end_clean();
			if ( $deleted ) {
				$done += count( $del_pl );
			} else {
				$fail = array_merge( $fail, array_values( $del_pl ) );
			}
		}
		return array(
			'op'   => $op,
			'done' => $done,
			'skip' => $skip,
			'fail' => $fail,
		);
	}

	/**
	 * One package of a bulk action.
	 *
	 * @param string                           $op     One of MANAGE_OPS.
	 * @param 'plugin'|'theme'                 $type   Package type.
	 * @param string                           $id     Plugin file or theme folder.
	 * @param array<string,list<string>>       $auto   Automatic-update lists (changed in place).
	 * @param array<string,string>             $del_pl Plugins to delete after the loop (id => name).
	 * @return string done|skip|fail
	 */
	private static function manage_one( string $op, string $type, string $id, array &$auto, array &$del_pl ): string {
		if ( '' === $id ) {
			return 'skip';
		}
		if ( 'auto_on' === $op || 'auto_off' === $op ) {
			if ( ! current_user_can( 'theme' === $type ? 'update_themes' : 'update_plugins' ) || ! wp_is_auto_update_enabled_for_type( $type ) ) {
				return 'skip';
			}
			$set = 'auto_on' === $op;
			if ( in_array( $id, $auto[ $type ], true ) === $set ) {
				return 'skip';
			}
			$auto[ $type ] = $set ? array_merge( $auto[ $type ], array( $id ) ) : array_values( array_diff( $auto[ $type ], array( $id ) ) );
			return 'done';
		}
		if ( 'theme' === $type ) {
			$theme = wp_get_theme( $id );
			if ( 'delete' !== $op || ! $theme->exists() || ! current_user_can( 'delete_themes' ) || in_array( $id, array( get_stylesheet(), get_template() ), true ) ) {
				return 'skip';
			}
			return true === delete_theme( $id ) ? 'done' : 'fail';
		}
		if ( plugin_basename( FLXK_FILE ) === $id && 'activate' !== $op ) {
			return 'skip';
		}
		if ( 'activate' === $op ) {
			if ( ! current_user_can( 'activate_plugins' ) || is_plugin_active( $id ) ) {
				return 'skip';
			}
			return is_wp_error( activate_plugin( $id ) ) ? 'fail' : 'done';
		}
		if ( 'deactivate' === $op ) {
			if ( ! current_user_can( 'activate_plugins' ) || ! is_plugin_active( $id ) ) {
				return 'skip';
			}
			deactivate_plugins( $id );
			return 'done';
		}
		if ( ! current_user_can( 'delete_plugins' ) ) {
			return 'skip';
		}
		$del_pl[ $id ] = $id;
		return 'queued';
	}

	/**
	 * The one-line summary of a bulk action (the classic notice).
	 *
	 * @param array{op:string,done:int,skip:list<string>,fail:list<string>} $r Result of manage().
	 */
	public static function manage_summary( array $r ): string {
		$n      = $r['done'];
		$labels = array(
			/* translators: %d: number of packages */
			'activate'   => _n( '%d package activated.', '%d packages activated.', $n, 'flx-setup' ),
			/* translators: %d: number of packages */
			'deactivate' => _n( '%d package deactivated.', '%d packages deactivated.', $n, 'flx-setup' ),
			/* translators: %d: number of packages */
			'delete'     => _n( '%d package removed.', '%d packages removed.', $n, 'flx-setup' ),
			/* translators: %d: number of packages */
			'auto_on'    => _n( 'Automatic updates turned on for %d package.', 'Automatic updates turned on for %d packages.', $n, 'flx-setup' ),
			/* translators: %d: number of packages */
			'auto_off'   => _n( 'Automatic updates turned off for %d package.', 'Automatic updates turned off for %d packages.', $n, 'flx-setup' ),
		);
		$text = sprintf( $labels[ $r['op'] ] ?? '%d', $n );
		if ( $r['skip'] ) {
			/* translators: %s: package names */
			$text .= ' ' . sprintf( __( 'Skipped (already in that state, active theme or FluxWP Panel): %s.', 'flx-setup' ), implode( ', ', $r['skip'] ) );
		}
		if ( $r['fail'] ) {
			/* translators: %s: package names */
			$text .= ' ' . sprintf( __( 'Failed: %s.', 'flx-setup' ), implode( ', ', $r['fail'] ) );
		}
		return $text;
	}

	/**
	 * Turns WordPress' automatic updates of one plugin or theme on or off.
	 *
	 * @param string $type plugin|theme.
	 * @param string $id   Plugin file or theme folder.
	 * @param bool   $on   Turn on.
	 * @return array{on:bool,label:string}|WP_Error
	 */
	public static function set_auto( string $type, string $id, bool $on ) {
		if ( ! in_array( $type, array( 'plugin', 'theme' ), true ) ) {
			return new WP_Error( 'flxk_auto', __( 'You are not allowed to do this.', 'flx-setup' ), array( 'graphql_code' => 'FORBIDDEN' ) );
		}
		if ( ! current_user_can( 'theme' === $type ? 'update_themes' : 'update_plugins' ) ) {
			return new WP_Error( 'flxk_auto', __( 'You are not allowed to do this.', 'flx-setup' ), array( 'graphql_code' => 'FORBIDDEN' ) );
		}
		if ( ! wp_is_auto_update_enabled_for_type( $type ) ) {
			return new WP_Error( 'flxk_auto', __( 'Automatic updates are turned off for this site.', 'flx-setup' ), array( 'graphql_code' => 'CONFLICT' ) );
		}
		if ( 'plugin' === $type ) {
			require_once ABSPATH . 'wp-admin/includes/plugin.php';
			$ok = isset( get_plugins()[ $id ] );
		} else {
			$ok = wp_get_theme( $id )->exists();
		}
		if ( ! $ok ) {
			return new WP_Error( 'flxk_auto', __( 'Package not found.', 'flx-setup' ), array( 'graphql_code' => 'NOT_FOUND', 'field' => 'id' ) );
		}
		$opt  = 'theme' === $type ? 'auto_update_themes' : 'auto_update_plugins';
		$list = FLXK_Val::strings( get_site_option( $opt, array() ) );
		$list = $on ? array_values( array_unique( array_merge( $list, array( $id ) ) ) ) : array_values( array_diff( $list, array( $id ) ) );
		update_site_option( $opt, $list );
		return array(
			'on'    => $on,
			'label' => $on ? __( 'On', 'flx-setup' ) : __( 'Off', 'flx-setup' ),
		);
	}

	/* ------------------------------------------------------------------ */
	/* History and backups                                                 */
	/* ------------------------------------------------------------------ */

	/**
	 * Flux Backup has the update-backup API (list, get, delete).
	 */
	public static function backup_api(): bool {
		return function_exists( 'flxy_backup_get' ) && function_exists( 'flxy_backup_delete' );
	}

	/**
	 * One page of the update history with the backup of each row.
	 *
	 * @param int $page Page, from 1.
	 * @return array{rows:list<array<string,mixed>>,total:int,pages:int,page:int,canDelete:bool,backupPage:string}
	 */
	public static function history( int $page ): array {
		$all   = FLXK_Val::rows( FLXU_Guard::history() );
		$total = count( $all );
		$pages = max( 1, (int) ceil( $total / self::HISTORY_PER_PAGE ) );
		$page  = max( 1, min( $page, $pages ) );
		$slice = array_slice( $all, ( $page - 1 ) * self::HISTORY_PER_PAGE, self::HISTORY_PER_PAGE );
		$api   = self::backup_api();
		$can   = $api && current_user_can( 'manage_options' );
		$bpage = FLXU_Guard::backup_ready() ? admin_url( 'admin.php?page=flx-backup-list' ) : '';
		$seen  = array();
		$rows  = array();
		foreach ( $slice as $e ) {
			$bid = FLXK_Val::str( $e['backup'] ?? '' );
			$rec = ( '' !== $bid && $api ) ? self::backup_record( $bid ) : null;
			// A bulk update shares one backup between several rows: the checkbox is on the first only.
			$first = null !== $rec && ! isset( $seen[ $bid ] );
			if ( null !== $rec ) {
				$seen[ $bid ] = true;
			}
			$deletable = $can && null !== $rec && 'pre_update' === FLXK_Val::str( $rec['type'] ?? '' ) && empty( $rec['locked'] ) && 'running' !== FLXK_Val::str( $rec['status'] ?? '' );
			$backup    = null;
			if ( '' !== $bid ) {
				$keep   = FLXK_Val::int( $rec['keep_until'] ?? 0 );
				$backup = array(
					'id'         => $bid,
					'deleted'    => $api && null === $rec,
					'size'       => null !== $rec ? FLXK_Val::str( size_format( FLXK_Val::int( $rec['size'] ?? 0 ), 1 ) ) : '',
					'locked'     => null !== $rec && ! empty( $rec['locked'] ),
					'keepUntil'  => null !== $rec && empty( $rec['locked'] ) && $keep > 0 ? $keep : null,
					'deletable'  => $deletable,
					'selectable' => $deletable && $first,
					'url'        => '' !== $bpage ? ( null !== $rec ? add_query_arg( 'view', $bid, $bpage ) : add_query_arg( 'id', $bid, $bpage ) ) : '',
				);
			}
			$result = FLXK_Val::str( $e['result'] ?? '' );
			$action = FLXK_Val::str( $e['action'] ?? '' );
			$msg    = FLXK_Val::str( $e['message'] ?? '' );
			$rows[] = array(
				'time'    => FLXK_Val::int( $e['time'] ?? 0 ),
				'name'    => FLXK_Val::str( $e['name'] ?? '' ),
				'action'  => FLXU_Admin::action_label( self::history_entry( $e ) ),
				'success' => 'ok' === $result,
				'message' => '' !== $msg && ( 'ok' !== $result || 'auto_rollback' === $action ) ? $msg : '',
				'by'      => FLXK_Val::bool( $e['auto'] ?? false ) ? __( 'Automatic', 'flx-setup' ) : FLXK_Val::str( $e['user'] ?? '' ),
				'backup'  => $backup,
			);
		}
		return array(
			'rows'       => $rows,
			'total'      => $total,
			'pages'      => $pages,
			'page'       => $page,
			'canDelete'  => $can,
			'backupPage' => $bpage,
		);
	}

	/**
	 * A history entry with every key FLXU_Admin::action_label() reads.
	 *
	 * @param array<string,mixed> $e Entry.
	 * @return array<string,mixed>
	 */
	private static function history_entry( array $e ): array {
		return $e + array(
			'from'    => '',
			'to'      => '',
			'action'  => '',
			'result'  => '',
			'message' => '',
		);
	}

	/**
	 * Flux Backup record by id.
	 *
	 * @param string $id Backup id.
	 * @return array<string,mixed>|null
	 */
	private static function backup_record( string $id ): ?array {
		if ( ! function_exists( 'flxy_backup_get' ) ) {
			return null;
		}
		$r = flxy_backup_get( $id );
		return is_array( $r ) ? FLXK_Val::arr( $r ) : null;
	}

	/**
	 * Update backups that can be deleted (not locked, finished).
	 *
	 * @return list<array<string,mixed>>
	 */
	private static function update_backups(): array {
		if ( ! function_exists( 'flxy_backup_list' ) ) {
			return array();
		}
		$out = array();
		foreach ( FLXK_Val::rows( flxy_backup_list( array( 'type' => 'pre_update' ) ) ) as $r ) {
			if ( empty( $r['locked'] ) && 'running' !== FLXK_Val::str( $r['status'] ?? '' ) ) {
				$out[] = $r;
			}
		}
		return $out;
	}

	/**
	 * Retention of the update backups and what they take now.
	 *
	 * @return array{available:bool,days:int,choices:list<int>,count:int,size:string,settingsUrl:string}
	 */
	public static function retention(): array {
		$list    = self::update_backups();
		$total   = 0;
		foreach ( $list as $r ) {
			$total += FLXK_Val::int( $r['size'] ?? 0 );
		}
		$days    = function_exists( 'flxy_pre_update_keep_days' ) ? FLXK_Val::int( flxy_pre_update_keep_days() ) : 0;
		$choices = array( 1, 3, 7, 14, 30, 60, 90 );
		if ( $days > 0 && ! in_array( $days, $choices, true ) ) {
			$choices[] = $days;
			sort( $choices );
		}
		return array(
			'available'   => self::backup_api() && current_user_can( 'manage_options' ),
			'days'        => $days,
			'choices'     => $choices,
			'count'       => count( $list ),
			'size'        => FLXK_Val::str( size_format( $total, 1 ) ),
			'settingsUrl' => admin_url( 'admin.php?page=flx-backup-settings' ),
		);
	}

	/**
	 * Deletes update backups: one, the selected ones or every unlocked one. Only update backups that are not
	 * locked are deleted.
	 *
	 * @param string       $mode one|selected|all.
	 * @param list<string> $ids  Backup ids (one, selected).
	 * @return int Deleted count.
	 */
	public static function delete_backups( string $mode, array $ids ): int {
		if ( ! self::backup_api() ) {
			return 0;
		}
		if ( 'all' === $mode ) {
			$ids = array();
			foreach ( self::update_backups() as $r ) {
				$ids[] = FLXK_Val::str( $r['id'] ?? '' );
			}
		} elseif ( 'one' === $mode ) {
			$ids = array_slice( $ids, 0, 1 );
		}
		$n      = 0;
		$delete = FLXK_Val::str( 'flxy_backup_delete' ); // Flux Backup API, may not be installed.
		foreach ( array_unique( array_filter( array_map( static fn( $i ): string => sanitize_text_field( FLXK_Val::str( $i ) ), $ids ) ) ) as $id ) {
			$r = self::backup_record( $id );
			if ( null !== $r && 'pre_update' === FLXK_Val::str( $r['type'] ?? '' ) && empty( $r['locked'] ) && is_callable( $delete ) && (bool) $delete( $id ) ) {
				++$n;
			}
		}
		return $n;
	}

	/**
	 * Sets how long update backups are kept and applies it to the existing ones.
	 *
	 * @param int $days Days (1–365).
	 * @return int|WP_Error Number of expired backups deleted.
	 */
	public static function set_retention( int $days ) {
		if ( ! function_exists( 'flxy_set_pre_update_keep_days' ) ) {
			return new WP_Error( 'flxk_backup', __( 'Update backup retention is set on the Flux Backup settings page.', 'flx-setup' ), array( 'graphql_code' => 'NOT_FOUND' ) );
		}
		if ( $days < 1 || $days > 365 ) {
			return new WP_Error( 'flxk_days', __( 'Enter 1 to 365 days.', 'flx-setup' ), array( 'graphql_code' => 'INVALID_INPUT', 'field' => 'days' ) );
		}
		$res = FLXK_Val::arr( flxy_set_pre_update_keep_days( $days ) );
		return FLXK_Val::int( $res['deleted'] ?? 0 );
	}
}
