<?php
/**
 * Flux App admin service: everything the "Application" settings pages (wp-admin and the FluxWP app) and the
 * personal "App access" page read and write. The classic admin-post handlers and the GraphQL schema both call it,
 * so the two screens behave the same.
 *
 * Failures are WP_Error with `graphql_code` and the REST-like `status`.
 *
 * @package flx-app
 */

defined( 'ABSPATH' ) || exit;

/**
 * @phpstan-type FlxappChoice array{key: string, label: string}
 * @phpstan-type FlxappBrandedApp array{id: string, name: string, platform: string, redirectUris: list<string>}
 * @phpstan-type FlxappAccessRow array{id: int, kind: string, name: string, hint: string, userId: int, user: string, created: int, lastUsed: int, lastIp: string, expires: int}
 */
final class FLXAPP_Admin_Service {

	/**
	 * @param string $code    graphql_code.
	 * @param string $message Message.
	 * @param int    $status  HTTP-like status.
	 * @param string $field   Field name.
	 */
	private static function error( string $code, string $message, int $status, string $field = '' ): WP_Error {
		$data = array(
			'status'       => $status,
			'graphql_code' => $code,
		);
		if ( '' !== $field ) {
			$data['field'] = $field;
		}
		return new WP_Error( 'flxapp_' . strtolower( $code ), $message, $data );
	}

	private static function forbidden(): WP_Error {
		return self::error( 'FORBIDDEN', __( 'You are not allowed to do this.', 'flx-app' ), 403 );
	}

	/**
	 * @param array<string,string> $map Key => label.
	 * @return list<FlxappChoice>
	 */
	private static function choices( array $map ): array {
		$out = array();
		foreach ( $map as $k => $l ) {
			$out[] = array(
				'key'   => (string) $k,
				'label' => (string) $l,
			);
		}
		return $out;
	}

	/* ───────── General ───────── */

	/**
	 * @return array<string,mixed>
	 */
	public static function settings(): array {
		$s = flxapp_settings();
		return array(
			'accessMinutes'   => flx_gql_int( $s['access_minutes'] ),
			'refreshDays'     => flx_gql_int( $s['refresh_days'] ),
			'dynamicClients'  => ! empty( $s['dynamic_clients'] ),
			'webSession'      => ! empty( $s['web_session'] ),
			'otpEnabled'      => ! empty( $s['otp_enabled'] ),
			'otpCreate'       => ! empty( $s['otp_create'] ),
			'otpStaff'        => ! empty( $s['otp_staff'] ),
			'phoneSelfVerify' => ! empty( $s['phone_self_verify'] ),
			'keysCap'         => flx_gql_str( $s['keys_cap'], 'read' ),
			'keysCapChoices'  => self::choices( flxapp_key_cap_choices() ),
			'version'         => FLXAPP_VERSION,
		);
	}

	/**
	 * Saves the sent fields only (the classic form sends all of them).
	 *
	 * @param array<string,mixed> $in Fields (camelCase).
	 * @return array<string,mixed>|WP_Error
	 */
	public static function settings_save( array $in ) {
		if ( ! current_user_can( 'manage_options' ) ) {
			return self::forbidden();
		}
		$s   = flxapp_settings();
		$map = array(
			'accessMinutes'   => array( 'access_minutes', 5, 1440 ),
			'refreshDays'     => array( 'refresh_days', 1, 365 ),
		);
		foreach ( $map as $k => $def ) {
			if ( isset( $in[ $k ] ) ) {
				$s[ $def[0] ] = max( $def[1], min( $def[2], absint( $in[ $k ] ) ) );
			}
		}
		foreach ( array(
			'dynamicClients'  => 'dynamic_clients',
			'webSession'      => 'web_session',
			'otpEnabled'      => 'otp_enabled',
			'otpCreate'       => 'otp_create',
			'otpStaff'        => 'otp_staff',
			'phoneSelfVerify' => 'phone_self_verify',
		) as $k => $opt ) {
			if ( isset( $in[ $k ] ) ) {
				$s[ $opt ] = empty( $in[ $k ] ) ? 0 : 1;
			}
		}
		if ( isset( $in['keysCap'] ) ) {
			$cap          = sanitize_key( flx_gql_str( $in['keysCap'] ) );
			$s['keys_cap'] = array_key_exists( $cap, flxapp_key_cap_choices() ) ? $cap : 'read';
		}
		update_option( 'flxapp_settings', $s, false );
		return self::settings();
	}

	/* ───────── Modules ───────── */

	/**
	 * @return list<array<string,mixed>>
	 */
	public static function modules(): array {
		$off = (array) flxapp_opt( 'modules_off' );
		$out = array();
		foreach ( flxapp_admin_modules() as $id => $m ) {
			$out[] = array(
				'id'      => (string) $id,
				'title'   => (string) $m['title'],
				'menu'    => '' === $m['menu'] ? null : (string) $m['menu'],
				'cap'     => '' === $m['cap'] ? null : (string) $m['cap'],
				'version' => '' === $m['version'] ? null : (string) $m['version'],
				'kind'    => (string) $m['kind'],
				'screens' => (int) ( $m['screens'] ?? 0 ),
				'errors'  => array_values( array_map( 'strval', (array) $m['errors'] ) ),
				'on'      => ! in_array( (string) $id, $off, true ),
			);
		}
		return $out;
	}

	/**
	 * @param list<string> $on Module ids that stay on.
	 * @return list<array<string,mixed>>|WP_Error
	 */
	public static function modules_save( array $on ) {
		if ( ! current_user_can( 'manage_options' ) ) {
			return self::forbidden();
		}
		$all = array_map( 'sanitize_key', array_keys( flxapp_admin_modules() ) );
		$on  = array_map( 'sanitize_key', $on );
		$s   = flxapp_settings();
		// A module whose plugin is off right now keeps its earlier choice.
		$s['modules_off'] = array_values( array_unique( array_merge( array_diff( (array) $s['modules_off'], $all ), array_diff( $all, $on ) ) ) );
		update_option( 'flxapp_settings', $s, false );
		return self::modules();
	}

	/* ───────── Menu ───────── */

	/**
	 * Items the role menu editor hides on some platform.
	 *
	 * @return array{available: bool, editorUrl: string|null, platforms: list<FlxappChoice>, rows: list<array<string,mixed>>}
	 */
	public static function menu(): array {
		$platforms = self::choices( flxapp_platforms() );
		if ( ! function_exists( 'flxa_settings_url' ) || ! function_exists( 'flxa_menus_option' ) ) {
			return array(
				'available' => false,
				'editorUrl' => null,
				'platforms' => $platforms,
				'rows'      => array(),
			);
		}
		$names  = wp_roles()->get_names();
		$labels = array();
		foreach ( function_exists( 'flxa_menu_catalog' ) ? (array) flxa_menu_catalog() : array() as $it ) {
			$it                                 = flx_gql_arr( $it );
			$labels[ flx_gql_str( $it['slug'] ?? '' ) ] = flx_gql_str( $it['label'] ?? '' );
		}
		$rows = array();
		foreach ( (array) ( flx_gql_arr( flxa_menus_option() )['roles'] ?? array() ) as $role => $cfg ) {
			foreach ( (array) ( flx_gql_arr( $cfg )['entries'] ?? array() ) as $e ) {
				$e = flx_gql_arr( $e );
				if ( empty( $e['pf_off'] ) || empty( $e['slug'] ) ) {
					continue;
				}
				$vis = array();
				foreach ( array_keys( flxapp_platforms() ) as $pk ) {
					$vis[] = array(
						'key'     => (string) $pk,
						'visible' => ! in_array( $pk, (array) $e['pf_off'], true ),
					);
				}
				$slug   = flx_gql_str( $e['slug'] );
				$rows[] = array(
					'role'      => '_default' === $role ? __( 'Default', 'flx-app' ) : translate_user_role( flx_gql_str( $names[ $role ] ?? $role ) ),
					'menu'      => '' !== flx_gql_str( $e['label'] ?? '' ) ? flx_gql_str( $e['label'] ) : ( $labels[ $slug ] ?? $slug ),
					'platforms' => $vis,
				);
			}
		}
		return array(
			'available' => true,
			'editorUrl' => (string) flxa_settings_url( 'menu' ),
			'platforms' => $platforms,
			'rows'      => $rows,
		);
	}

	/* ───────── OAuth clients ───────── */

	/**
	 * @return array{clients: list<array<string,mixed>>, connection: list<FlxappChoice>}
	 */
	public static function clients(): array {
		$out = array();
		foreach ( flxapp_clients() as $c ) {
			$c     = flx_gql_arr( $c );
			$kind  = ! empty( $c['builtin'] ) ? 'BUILTIN' : ( ! empty( $c['dynamic'] ) ? 'DYNAMIC' : 'MANUAL' );
			$out[] = array(
				'id'           => flx_gql_str( $c['id'] ?? '' ),
				'name'         => flx_gql_str( $c['name'] ?? '' ),
				'redirectUris' => array_values( array_map( 'strval', (array) ( $c['redirect_uris'] ?? array() ) ) ),
				'kind'         => $kind,
				'hasSecret'    => ! empty( $c['secret_hash'] ),
			);
		}
		$m    = flx_gql_arr( flxapp_metadata() );
		$conn = array();
		foreach ( array( 'issuer', 'authorization_endpoint', 'token_endpoint', 'revocation_endpoint', 'registration_endpoint' ) as $k ) {
			if ( ! empty( $m[ $k ] ) ) {
				$conn[] = array(
					'key'   => $k,
					'label' => flx_gql_str( $m[ $k ] ),
				);
			}
		}
		$conn[] = array(
			'key'   => 'metadata',
			'label' => flxapp_issuer() . '/.well-known/oauth-authorization-server',
		);
		$conn[] = array(
			'key'   => 'MCP',
			'label' => rest_url( 'flx-mcp/v1/mcp' ),
		);
		return array(
			'clients'    => $out,
			'connection' => $conn,
		);
	}

	/**
	 * @param string       $name         Name.
	 * @param list<string> $uris         Redirect URIs.
	 * @param bool         $confidential Generate a client secret.
	 * @return array{client: array<string,mixed>, secret: string|null}|WP_Error
	 */
	public static function client_create( string $name, array $uris, bool $confidential ) {
		if ( ! current_user_can( 'manage_options' ) ) {
			return self::forbidden();
		}
		$res = flxapp_add_client( $name, array_map( 'esc_url_raw', $uris ), $confidential );
		if ( $res instanceof WP_Error ) {
			return self::error( 'INVALID_INPUT', $res->get_error_message(), 400, 'redirectUris' );
		}
		$c = flx_gql_arr( $res[0] );
		return array(
			'client' => array(
				'id'           => flx_gql_str( $c['id'] ?? '' ),
				'name'         => flx_gql_str( $c['name'] ?? '' ),
				'redirectUris' => array_values( array_map( 'strval', (array) ( $c['redirect_uris'] ?? array() ) ) ),
				'kind'         => 'MANUAL',
				'hasSecret'    => ! empty( $c['secret_hash'] ),
			),
			'secret' => '' !== (string) $res[1] ? (string) $res[1] : null,
		);
	}

	/**
	 * @param string $id Client id.
	 * @return true|WP_Error
	 */
	public static function client_delete( string $id ) {
		if ( ! current_user_can( 'manage_options' ) ) {
			return self::forbidden();
		}
		$id = sanitize_text_field( $id );
		if ( ! flxapp_delete_client( $id ) ) {
			return self::error( 'NOT_FOUND', __( 'Could not save.', 'flx-app' ), 404 );
		}
		return true;
	}

	/* ───────── Access: devices, sessions, keys ───────── */

	/**
	 * @param array<string,mixed> $r Token row.
	 * @return FlxappAccessRow
	 */
	private static function access_row( array $r ): array {
		$is_key = 'key' === $r['kind'];
		$last   = $is_key ? flx_gql_int( $r['last_used'] ?? 0 ) : flxapp_grant_last_used( flx_gql_str( $r['grant_id'] ?? '' ) );
		$uid    = flx_gql_int( $r['user_id'] ?? 0 );
		$u      = get_userdata( $uid );
		return array(
			'id'       => flx_gql_int( $r['id'] ?? 0 ),
			'kind'     => $is_key ? 'KEY' : 'SESSION',
			'name'     => flx_gql_str( $r['name'] ?? '' ),
			'hint'     => $is_key ? flx_gql_str( $r['hint'] ?? '' ) : '',
			'userId'   => $uid,
			'user'     => $u ? $u->display_name : '#' . $uid,
			'created'  => flx_gql_int( $r['created'] ?? 0 ),
			'lastUsed' => $last,
			'lastIp'   => $is_key ? flx_gql_str( $r['last_ip'] ?? '' ) : '',
			'expires'  => flx_gql_int( $r['expires'] ?? 0 ),
		);
	}

	/**
	 * @param array<string,mixed> $r Device row.
	 * @return array<string,mixed>
	 */
	private static function device_row( array $r ): array {
		$o    = flx_gql_arr( flxapp_device_out( $r ) );
		$uid  = flx_gql_int( $r['user_id'] ?? 0 );
		$u    = get_userdata( $uid );
		$name = '' !== flx_gql_str( $o['name'] ?? '' ) ? flx_gql_str( $o['name'] ) : ( '' !== flx_gql_str( $o['model'] ?? '' ) ? flx_gql_str( $o['model'] ) : __( 'Unnamed device', 'flx-app' ) );
		return array(
			'id'          => flx_gql_int( $o['id'] ?? 0 ),
			'name'        => $name,
			'platform'    => trim( flx_gql_str( $o['platform_label'] ?? '' ) . ' ' . flx_gql_str( $o['model'] ?? '' ) ),
			'app'         => trim( flx_gql_str( $o['app'] ?? '' ) . ' ' . flx_gql_str( $o['app_version'] ?? '' ) ),
			'brand'       => '' !== flx_gql_str( $o['brand'] ?? '' ) ? flx_gql_str( $o['brand'] ) : null,
			'push'        => ! empty( $o['push'] ),
			'lastSeen'    => flx_gql_int( $o['last_seen'] ?? 0 ),
			'lastSeenAgo' => flx_gql_str( $o['last_seen_label'] ?? '' ),
			'userId'      => $uid,
			'user'        => $u ? $u->display_name : '#' . $uid,
			'current'     => ! empty( $o['current'] ),
		);
	}

	/**
	 * The desktop app download addresses.
	 *
	 * @return array{macos: string, windows: string, windowsMsi: string}
	 */
	private static function downloads(): array {
		return array(
			'macos'      => flxapp_desktop_download_url( 'macos' ),
			'windows'    => flxapp_desktop_download_url( 'windows' ),
			'windowsMsi' => flxapp_desktop_download_url( 'windows-msi' ),
		);
	}

	/**
	 * @param bool $all true = every user (administrators), false = the signed-in user.
	 * @return array<string,mixed>|WP_Error
	 */
	public static function access( bool $all ) {
		if ( $all && ! current_user_can( 'manage_options' ) ) {
			return self::forbidden();
		}
		$uid     = $all ? 0 : get_current_user_id();
		$rows    = array();
		foreach ( flxapp_user_tokens( $uid, array( 'refresh', 'key' ) ) as $r ) {
			$rows[] = self::access_row( flx_gql_arr( $r ) );
		}
		$devices = array();
		foreach ( flxapp_user_devices( $uid ) as $d ) {
			$devices[] = self::device_row( flx_gql_arr( $d ) );
		}
		return array(
			'devices'       => $devices,
			'rows'          => $rows,
			'canCreateKeys' => current_user_can( flx_gql_str( flxapp_opt( 'keys_cap' ), 'read' ) ),
			'canManage'     => current_user_can( 'manage_options' ),
			'downloads'     => self::downloads(),
		);
	}

	/**
	 * @param string $name Key name.
	 * @param int    $days 0, 30, 90 or 365.
	 * @return array{key: string}|WP_Error
	 */
	public static function key_create( string $name, int $days ) {
		if ( ! current_user_can( flx_gql_str( flxapp_opt( 'keys_cap' ), 'read' ) ) ) {
			return self::forbidden();
		}
		$name = sanitize_text_field( $name );
		if ( '' === $name ) {
			return self::error( 'INVALID_INPUT', __( 'Give the key a name.', 'flx-app' ), 400, 'name' );
		}
		$days = in_array( $days, array( 0, 30, 90, 365 ), true ) ? $days : 0;
		$key  = flxapp_issue(
			'key',
			array(
				'user_id' => get_current_user_id(),
				'name'    => $name,
				'scope'   => 'flx mcp',
				'ttl'     => $days * DAY_IN_SECONDS,
			)
		);
		return array( 'key' => flx_gql_str( $key ) );
	}

	/**
	 * @param int $id Token row id.
	 * @return true|WP_Error
	 */
	public static function access_revoke( int $id ) {
		global $wpdb;
		$t   = flxapp_table();
		$row = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$t} WHERE id = %d", $id ), ARRAY_A ); // phpcs:ignore WordPress.DB
		if ( ! is_array( $row ) || ( (int) $row['user_id'] !== get_current_user_id() && ! current_user_can( 'manage_options' ) ) ) {
			return self::forbidden();
		}
		if ( '' !== $row['grant_id'] ) {
			flxapp_revoke_grant( $row['grant_id'] );
		} else {
			flxapp_revoke_id( $row['id'] );
		}
		return true;
	}

	/**
	 * @param int $id Device id.
	 * @return true|WP_Error
	 */
	public static function device_revoke( int $id ) {
		$row = flxapp_device_get( $id );
		if ( ! is_array( $row ) || ( (int) $row['user_id'] !== get_current_user_id() && ! current_user_can( 'manage_options' ) ) ) {
			return self::forbidden();
		}
		flxapp_device_revoke( $row );
		return true;
	}

	/* ───────── Applications (mobile) ───────── */

	/**
	 * @return array<string,mixed>
	 */
	public static function mobile(): array {
		$s       = flxapp_settings();
		$last    = (array) get_option( 'flxapp_push_last', array() );
		$devices = flxapp_user_devices( 0 );
		$push    = count( array_filter( $devices, static fn( $d ) => '' !== (string) flx_gql_arr( $d )['token_hash'] ) );
		$apps    = array();
		foreach ( (array) $s['app_clients'] as $c ) {
			$c      = flx_gql_arr( $c );
			$apps[] = array(
				'id'           => flx_gql_str( $c['id'] ?? '' ),
				'name'         => flx_gql_str( $c['name'] ?? '' ),
				'platform'     => 'desktop' === ( $c['platform'] ?? '' ) ? 'desktop' : 'mobile',
				'redirectUris' => array_values( array_map( 'strval', (array) ( $c['redirect_uris'] ?? array() ) ) ),
			);
		}
		$preset = flxapp_site_preset();
		return array(
			'genericApp'   => ! empty( $s['generic_app'] ),
			'storeIos'     => flx_gql_str( $s['store_ios'] ),
			'storeAndroid' => flx_gql_str( $s['store_android'] ),
			'pushEnabled'  => ! empty( $s['push_enabled'] ),
			'pushHide'     => ! empty( $s['push_hide'] ),
			'hasExpoToken' => flxapp_secret_has( 'push_expo_token' ),
			'embedFront'   => ! empty( $s['embed_front'] ),
			'devRedirects' => ! empty( $s['dev_redirects'] ),
			'apps'         => $apps,
			'deviceCount'  => count( $devices ),
			'pushCount'    => $push,
			'lastPush'     => empty( $last['at'] ) ? null : array(
				'at'      => flx_gql_int( $last['at'] ),
				'ago'     => flx_time_ago( flx_gql_int( $last['at'] ) ),
				'sent'    => flx_gql_int( $last['sent'] ?? 0 ),
				'failed'  => flx_gql_int( $last['failed'] ?? 0 ),
				'error'   => '' !== flx_gql_str( $last['error'] ?? '' ) ? flx_gql_str( $last['error'] ) : null,
			),
			'bootstrapUrl' => rest_url( 'flxapp/v1/app/bootstrap' ),
			'preset'       => $preset ? (string) $preset : null,
		);
	}

	/**
	 * @param array<string,mixed> $in Fields (camelCase); `apps` replaces the branded app list when sent.
	 * @return array<string,mixed>|WP_Error
	 */
	public static function mobile_save( array $in ) {
		if ( ! current_user_can( 'manage_options' ) ) {
			return self::forbidden();
		}
		$s = flxapp_settings();
		foreach ( array(
			'genericApp'   => 'generic_app',
			'pushEnabled'  => 'push_enabled',
			'pushHide'     => 'push_hide',
			'embedFront'   => 'embed_front',
			'devRedirects' => 'dev_redirects',
		) as $k => $opt ) {
			if ( isset( $in[ $k ] ) ) {
				$s[ $opt ] = empty( $in[ $k ] ) ? 0 : 1;
			}
		}
		foreach ( array(
			'storeIos'     => 'store_ios',
			'storeAndroid' => 'store_android',
		) as $k => $opt ) {
			if ( isset( $in[ $k ] ) ) {
				$s[ $opt ] = esc_url_raw( flx_gql_str( $in[ $k ] ), array( 'https' ) );
			}
		}
		if ( isset( $in['apps'] ) && is_array( $in['apps'] ) ) {
			$clients = array();
			foreach ( $in['apps'] as $c ) {
				$c  = flx_gql_arr( $c );
				$id = sanitize_key( flx_gql_str( $c['id'] ?? '' ) );
				if ( '' === $id ) {
					continue;
				}
				$uris      = array_map( 'trim', array_map( 'strval', (array) ( $c['redirectUris'] ?? array() ) ) );
				$clients[ $id ] = array(
					'id'            => $id,
					'name'          => sanitize_text_field( flx_gql_str( $c['name'] ?? '', $id ) ),
					'platform'      => 'desktop' === ( $c['platform'] ?? '' ) ? 'desktop' : 'mobile',
					'redirect_uris' => array_values( array_filter( $uris, 'flxapp_brand_redirect_ok' ) ),
				);
			}
			$s['app_clients'] = array_values( $clients );
		}
		update_option( 'flxapp_settings', $s, false );
		if ( ! empty( $in['clearExpoToken'] ) ) {
			flxapp_secret_put( 'push_expo_token', '' );
		} elseif ( '' !== trim( flx_gql_str( $in['expoToken'] ?? '' ) ) ) {
			flxapp_secret_put( 'push_expo_token', (string) preg_replace( '/[^A-Za-z0-9_\-.]/', '', flx_gql_str( $in['expoToken'] ) ) );
		}
		return self::mobile();
	}

	/**
	 * A test notification to the signed-in administrator's devices.
	 *
	 * @return array{sent: int, failed: int, skipped: int}|WP_Error
	 */
	public static function push_test() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return self::forbidden();
		}
		$r = flx_gql_arr(
			flxapp_push(
				get_current_user_id(),
				array(
					'title'  => get_bloginfo( 'name' ),
					'body'   => __( 'Test notification: this device receives notifications.', 'flx-app' ),
					'target' => 'notifications',
					'now'    => true,
					'test'   => true,
					'public' => true,
				)
			)
		);
		return array(
			'sent'    => flx_gql_int( $r['sent'] ?? 0 ),
			'failed'  => flx_gql_int( $r['failed'] ?? 0 ),
			'skipped' => flx_gql_int( $r['skipped'] ?? 0 ),
		);
	}

	/* ───────── Appearance ───────── */

	/**
	 * Targets for the pickers: groups with items.
	 *
	 * @return list<array{label: string, items: list<FlxappChoice>}>
	 */
	private static function targets(): array {
		$out = array();
		foreach ( flxapp_target_catalog() as $group => $items ) {
			$out[] = array(
				'label' => (string) $group,
				'items' => self::choices( array_map( 'strval', $items ) ),
			);
		}
		return $out;
	}

	/**
	 * @return array<string,mixed>
	 */
	public static function appearance(): array {
		$user   = wp_get_current_user();
		$off    = (array) flxapp_opt( 'screens_off' );
		$screen = array();
		foreach ( flxapp_screen_catalog() as $k => $l ) {
			$screen[] = array(
				'key'   => (string) $k,
				'label' => (string) $l,
				'on'    => ! in_array( (string) $k, $off, true ),
			);
		}
		$labels = array();
		foreach ( flxapp_color_labels() as $k => $l ) {
			$labels[] = array(
				'key'   => (string) $k,
				'label' => (string) $l,
			);
		}
		$cat = array();
		foreach ( flxapp_target_catalog() as $items ) {
			foreach ( $items as $k => $l ) {
				$cat[] = array(
					'key'   => (string) $k,
					'label' => false !== strpos( (string) $l, ' › ' ) ? substr( (string) $l, (int) strrpos( (string) $l, ' › ' ) + strlen( ' › ' ) ) : (string) $l,
				);
			}
		}
		return array(
			'option'         => FLXAPP_Service::doc( flxapp_app_option() ),
			'theme'          => FLXAPP_Service::doc( flxapp_theme() ),
			'fonts'          => array_values( flxapp_fonts() ),
			'colorKeys'      => $labels,
			'targets'        => self::targets(),
			'targetLabels'   => $cat,
			'screens'        => $screen,
			'previewMobile'  => FLXAPP_Service::doc( flxapp_config( $user, 'mobile' ) ),
			'previewDesktop' => FLXAPP_Service::doc( flxapp_config( $user, 'desktop' ) ),
		);
	}

	/**
	 * Saves the theme, the mobile and desktop shell and the enabled screens. $in uses the classic form shape:
	 * theme, mobile, desktop (arrays), screens_on and screens_all.
	 *
	 * @param array<string,mixed> $p Form-shaped input.
	 * @return array<string,mixed>|WP_Error
	 */
	public static function appearance_save( array $p ) {
		if ( ! current_user_can( 'manage_options' ) ) {
			return self::forbidden();
		}
		$cur = flxapp_app_option();
		$mob = (array) ( $p['mobile'] ?? array() );
		$mob['tabs']                    = array_values( array_filter( (array) ( $mob['tabs'] ?? array() ), static fn( $t ) => '' !== (string) ( flx_gql_arr( $t )['target'] ?? '' ) ) );
		$mob['drawer']['enabled']       = ! empty( $mob['drawer']['enabled'] );
		$mob['topbar']['show_logo']     = ! empty( $mob['topbar']['show_logo'] );
		$mob['topbar']['site_switcher'] = ! empty( $mob['topbar']['site_switcher'] );
		$mob['drawer']['groups']        = $cur['shell']['mobile']['drawer']['groups'] ?? null;
		$mob['drawer']['pins']          = $cur['shell']['mobile']['drawer']['pins'] ?? array();
		$mob['topbar']['actions']       = $cur['shell']['mobile']['topbar']['actions'] ?? null;
		$desk                           = (array) ( $p['desktop'] ?? array() );
		$desk['commands']               = array_values( array_filter( (array) ( $desk['commands'] ?? array() ) ) );
		$desk['topbar']['site_switcher'] = ! empty( $desk['topbar']['site_switcher'] );
		$desk['sidebar']                = $cur['shell']['desktop']['sidebar'] ?? array();
		$desk['topbar']['actions']      = $cur['shell']['desktop']['topbar']['actions'] ?? null;
		$new                            = flxapp_app_sanitize(
			array(
				'theme'    => (array) ( $p['theme'] ?? array() ),
				'shell'    => array(
					'mobile'  => $mob,
					'desktop' => $desk,
				),
				'features' => $cur['features'] ?? array(),
				'links'    => $cur['links'] ?? array(),
				'brand'    => $cur['brand'] ?? array(),
			)
		);
		update_option( 'flxapp_app', $new, false );
		$all              = array_filter( explode( ',', flx_gql_str( $p['screens_all'] ?? '' ) ) );
		$on               = array_map( 'strval', (array) ( $p['screens_on'] ?? array() ) );
		$s                = flxapp_settings();
		$s['screens_off'] = array_values( array_unique( array_merge( array_diff( (array) $s['screens_off'], $all ), array_diff( $all, $on ) ) ) );
		update_option( 'flxapp_settings', $s, false );
		return self::appearance();
	}
}
