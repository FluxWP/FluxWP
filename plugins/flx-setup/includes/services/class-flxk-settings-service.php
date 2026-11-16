<?php
/**
 * Update settings: connection to the update server, automatic updates, post-update check, usage data
 * consent, and the other update sources. The classic Settings page (admin-post handlers) and GraphQL call
 * this. Permission checks stay with the caller (manage_options).
 *
 * The site key and the keys of other sources are never returned: only "a key is saved" is.
 *
 * Errors are WP_Error with `graphql_code` INVALID_INPUT and the input `field`.
 *
 * @package FLX_Setup
 */

defined( 'ABSPATH' ) || exit;

final class FLXK_Settings_Service {

	/**
	 * Connection settings, as the form shows them. The key itself is not included.
	 *
	 * @return array{server:string,defaultServer:string,serverFromConfig:bool,effectiveServer:string,hasKey:bool,keyFromConfig:bool,auto:bool,health:bool,usageAvailable:bool,usage:bool,legal:array{kvkk:string,privacy:string,terms:string}}
	 */
	public static function settings(): array {
		$s     = FLXU_Updater::settings();
		$legal = array(
			'kvkk'    => '',
			'privacy' => '',
			'terms'   => '',
		);
		if ( function_exists( 'flxk_legal_urls' ) ) {
			$l     = FLXK_Val::arr( flxk_legal_urls() );
			$legal = array(
				'kvkk'    => FLXK_Val::str( $l['kvkk'] ?? '' ),
				'privacy' => FLXK_Val::str( $l['privacy'] ?? '' ),
				'terms'   => FLXK_Val::str( $l['terms'] ?? '' ),
			);
		}
		return array(
			'server'           => FLXK_Val::str( $s['server'] ?? '' ),
			'defaultServer'    => defined( 'FLXU_DEFAULT_SERVER' ) ? FLXK_Val::str( FLXU_DEFAULT_SERVER ) : '',
			'serverFromConfig' => defined( 'FLX_UPDATE_SERVER' ) && FLX_UPDATE_SERVER,
			'effectiveServer'  => FLXU_Updater::server(),
			'hasKey'           => '' !== FLXK_Val::str( $s['key'] ?? '' ),
			'keyFromConfig'    => FLXU_Updater::key_from_config(),
			'auto'             => FLXK_Val::bool( $s['auto'] ?? false ),
			'health'           => FLXK_Val::bool( $s['health'] ?? false ),
			'usageAvailable'   => function_exists( 'flxk_telemetry_enabled' ),
			'usage'            => function_exists( 'flxk_telemetry_enabled' ) && flxk_telemetry_enabled(),
			'legal'            => $legal,
		);
	}

	/**
	 * Saves the connection settings and checks the server. Fields that are null stay as they are.
	 *
	 * @param array{server?:string|null,key?:string|null,clearKey?:bool|null,auto?:bool|null,health?:bool|null,usage?:bool|null} $in     Changes.
	 * @param bool                                                                                                                  $strict An address that is not https is an error (the classic form silently falls back to the default server).
	 * @return array{connected:bool}|WP_Error
	 */
	public static function save( array $in, bool $strict = true ) {
		$old = FLXU_Updater::settings();
		$new = array(
			'server' => FLXK_Val::str( $old['server'] ?? '' ),
			'key'    => FLXK_Val::str( $old['key'] ?? '' ),
			'auto'   => FLXK_Val::bool( $old['auto'] ?? false ) ? 1 : 0,
			'health' => FLXK_Val::bool( $old['health'] ?? false ) ? 1 : 0,
		);
		if ( isset( $in['server'] ) && ! ( defined( 'FLX_UPDATE_SERVER' ) && FLX_UPDATE_SERVER ) ) {
			$server = esc_url_raw( trim( $in['server'] ) );
			if ( '' !== $server && 'https' !== wp_parse_url( $server, PHP_URL_SCHEME ) ) {
				if ( $strict ) {
					return new WP_Error( 'flxk_server', __( 'Leave empty to use the default server. https only.', 'flx-setup' ), array( 'graphql_code' => 'INVALID_INPUT', 'field' => 'server' ) );
				}
				$server = '';
			}
			$new['server'] = untrailingslashit( $server );
		}
		if ( ! empty( $in['clearKey'] ) ) {
			$new['key'] = '';
		} elseif ( isset( $in['key'] ) && '' !== trim( sanitize_text_field( $in['key'] ) ) ) {
			// An empty key keeps the saved one.
			$new['key'] = trim( sanitize_text_field( $in['key'] ) );
		}
		if ( isset( $in['auto'] ) ) {
			$new['auto'] = $in['auto'] ? 1 : 0;
		}
		if ( isset( $in['health'] ) ) {
			$new['health'] = $in['health'] ? 1 : 0;
		}
		update_site_option( FLXU_Updater::SETTINGS_OPTION, $new );
		if ( isset( $in['usage'] ) && function_exists( 'flxk_set_telemetry' ) ) {
			flxk_set_telemetry( (bool) $in['usage'] );
		}
		FLXU_Updater::instance()->check_now();
		return array( 'connected' => 'connected' === FLXU_Admin::connection_state( FLXU_Updater::status() ) );
	}

	/**
	 * The main server and the other sources with their last state.
	 *
	 * @return list<array{id:string,name:string,url:string,type:string,main:bool,enabled:bool,hasKey:bool,state:string,message:string,packageCount:int,canMoveUp:bool}>
	 */
	public static function sources(): array {
		$out   = array();
		$extra = FLXU_Sources::extra();
		foreach ( FLXU_Sources::all() as $s ) {
			$s      = FLXK_Val::arr( $s );
			$id     = FLXK_Val::str( $s['id'] ?? '' );
			$main   = ! empty( $s['main'] );
			$on     = FLXK_Val::bool( $s['enabled'] ?? true );
			$st     = FLXK_Val::arr( FLXU_Sources::status( $id ) );
			$state  = 'unchecked';
			$msg    = '';
			$count  = 0;
			$has_k  = '' !== FLXK_Val::str( $s['key'] ?? '' );
			if ( ! $on ) {
				$state = 'off';
			} elseif ( empty( $st['checked'] ) ) {
				$state = 'unchecked';
			} elseif ( empty( $st['ok'] ) ) {
				$state = 'error';
				$msg   = FLXU_Updater::status_message( $st, $has_k, FLXU_Sources::host( $s ) );
			} else {
				$state = 'ok';
				$count = FLXK_Val::int( $st['count'] ?? 0 );
				/* translators: %d: package count */
				$msg = sprintf( _n( 'Connected · %d package', 'Connected · %d packages', $count, 'flx-setup' ), $count );
			}
			$index = -1;
			foreach ( $extra as $i => $e ) {
				if ( FLXK_Val::str( FLXK_Val::arr( $e )['id'] ?? '' ) === $id ) {
					$index = (int) $i;
				}
			}
			$out[] = array(
				'id'           => $id,
				'name'         => FLXK_Val::str( $s['name'] ?? $id ),
				'url'          => FLXK_Val::str( $s['url'] ?? '' ),
				'type'         => 'github' === FLXK_Val::str( $s['type'] ?? '' ) ? 'github' : 'server',
				'main'         => $main,
				'enabled'      => $on,
				'hasKey'       => $has_k,
				'state'        => $state,
				'message'      => $msg,
				'packageCount' => $count,
				'canMoveUp'    => $index > 0,
			);
		}
		return $out;
	}

	/**
	 * Adds a source (tests it first) and checks every source.
	 *
	 * @param string $name Name; taken from the address when empty.
	 * @param string $url  Repository or server address (https).
	 * @param string $key  Site key for servers that need one.
	 * @return true|WP_Error
	 */
	public static function source_add( string $name, string $url, string $key ) {
		$res = FLXU_Sources::add( $name, $url, trim( $key ) );
		if ( is_wp_error( $res ) ) {
			$res->add_data( array( 'graphql_code' => 'INVALID_INPUT', 'field' => 'url' ) );
			return $res;
		}
		FLXU_Updater::instance()->check_now();
		return true;
	}

	/**
	 * Removes, turns on/off or moves up a source.
	 *
	 * @param string $op  remove|toggle|up.
	 * @param string $id  Source id.
	 * @return true|WP_Error
	 */
	public static function source_change( string $op, string $id ) {
		$id = sanitize_key( $id );
		if ( 'main' === $id || null === FLXU_Sources::get( $id ) ) {
			return new WP_Error( 'flxk_source', __( 'Package not found.', 'flx-setup' ), array( 'graphql_code' => 'NOT_FOUND', 'field' => 'id' ) );
		}
		if ( 'remove' === $op ) {
			FLXU_Sources::remove( $id );
		} elseif ( 'toggle' === $op ) {
			FLXU_Sources::toggle( $id );
		} elseif ( 'up' === $op ) {
			FLXU_Sources::move_up( $id );
		} else {
			return new WP_Error( 'flxk_op', __( 'Unknown action.', 'flx-setup' ), array( 'graphql_code' => 'INVALID_INPUT', 'field' => 'op' ) );
		}
		FLXU_Updater::instance()->check_now();
		return true;
	}
}
