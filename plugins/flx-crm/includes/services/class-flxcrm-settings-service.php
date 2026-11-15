<?php
/**
 * CRM settings: sources, roles, scoring and the AI / MCP switches. The classic Settings page and GraphQL
 * save through here, so both validate the same way.
 *
 * @package FLX_CRM
 */

defined( 'ABSPATH' ) || exit;

final class FLXCRM_Settings_Service {

	/**
	 * Current settings with the source list and the AI state.
	 *
	 * @return array{chat_events: bool, ai_tools: bool, ai_write: bool, ai_destructive: bool, risk_threshold: int, active_days: int, base_currency: string, currency: string, sources: list<array{key: string, label: string, description: string, installed: bool, enabled: bool, has_role: bool, role: string}>, roles: list<array{key: string, label: string, color: string, builtin: bool}>, synced_at: int|null, scored_at: int|null}
	 */
	public static function get(): array {
		$s       = flxcrm_settings();
		$sources = array();
		foreach ( flxcrm_registered_sources() as $k => $d ) {
			$d         = FLXCRM_Val::arr( $d );
			$sources[] = array(
				'key'         => (string) $k,
				'label'       => FLXCRM_Val::str( $d['label'] ?? $k ),
				'description' => FLXCRM_Val::str( $d['description'] ?? '' ),
				'installed'   => FLXCRM_Val::bool( $d['installed'] ?? false ),
				'enabled'     => (bool) flxcrm_source_on( (string) $k ),
				'has_role'    => null !== ( $d['role'] ?? null ),
				'role'        => FLXCRM_Val::str( flxcrm_source_role( (string) $k ) ),
			);
		}
		$roles = array();
		foreach ( flxcrm_roles() as $k => $r ) {
			$r       = FLXCRM_Val::arr( $r );
			$roles[] = array( 'key' => (string) $k, 'label' => FLXCRM_Val::str( $r['label'] ?? $k ), 'color' => FLXCRM_Val::str( $r['color'] ?? '#64748b' ), 'builtin' => ! empty( $r['builtin'] ) );
		}
		$synced = FLXCRM_Val::int( get_option( 'flxcrm_synced_at' ) );
		$scored = FLXCRM_Val::int( get_option( 'flxcrm_scored_at' ) );
		return array(
			'chat_events'    => FLXCRM_Val::bool( $s['chat_events'] ?? 0 ),
			'ai_tools'       => FLXCRM_Val::bool( $s['ai_tools'] ?? 0 ),
			'ai_write'       => FLXCRM_Val::bool( $s['ai_write'] ?? 0 ),
			'ai_destructive' => FLXCRM_Val::bool( $s['ai_destructive'] ?? 0 ),
			'risk_threshold' => FLXCRM_Val::int( $s['risk_threshold'] ?? 70 ),
			'active_days'    => FLXCRM_Val::int( $s['active_days'] ?? 90 ),
			'base_currency'  => FLXCRM_Val::str( $s['base_currency'] ?? '' ),
			'currency'       => flxcrm_currency(),
			'sources'        => $sources,
			'roles'          => $roles,
			'synced_at'      => $synced > 0 ? $synced : null,
			'scored_at'      => $scored > 0 ? $scored : null,
		);
	}

	/**
	 * Saves settings. Values you leave out stay as they are.
	 *
	 * @param array{chat_events?: bool|null, ai_tools?: bool|null, ai_write?: bool|null, ai_destructive?: bool|null, risk_threshold?: int|null, active_days?: int|null, base_currency?: string|null, sources?: list<array{key: string, enabled?: bool|null, role?: string|null}>|null, roles?: list<array{key: string, label?: string|null, color?: string|null, delete?: bool|null}>|null, new_role?: array{label: string, color?: string|null}|null} $in Changes.
	 * @return true|WP_Error
	 */
	public static function save( array $in ) {
		$known = flxcrm_roles();
		$v     = array();
		foreach ( array( 'chat_events', 'ai_tools', 'ai_write', 'ai_destructive' ) as $k ) {
			if ( isset( $in[ $k ] ) ) {
				$v[ $k ] = $in[ $k ] ? 1 : 0;
			}
		}
		$reg = flxcrm_registered_sources();
		foreach ( $in['sources'] ?? array() as $row ) {
			$k = sanitize_key( $row['key'] );
			if ( ! isset( $reg[ $k ] ) ) {
				return FLXCRM_Contact_Service::error( 'flxcrm_source', __( 'Unknown source.', 'flx-crm' ), 'INVALID_INPUT', 'sources' );
			}
			if ( isset( $row['enabled'] ) ) {
				$v[ 'src_' . $k ] = $row['enabled'] ? 1 : 0;
			}
			if ( isset( $row['role'] ) && null !== FLXCRM_Val::arr( $reg[ $k ] )['role'] ) {
				$r                = sanitize_key( $row['role'] );
				$v[ 'role_' . $k ] = isset( $known[ $r ] ) ? $r : '';
			}
		}
		if ( isset( $in['risk_threshold'] ) ) {
			$v['risk_threshold'] = min( 100, max( 10, $in['risk_threshold'] ) );
		}
		if ( isset( $in['active_days'] ) ) {
			$v['active_days'] = min( 730, max( 7, $in['active_days'] ) );
		}
		if ( isset( $in['base_currency'] ) ) {
			$cur                = strtoupper( (string) preg_replace( '/[^A-Za-z]/', '', $in['base_currency'] ) );
			$v['base_currency'] = 3 === strlen( $cur ) ? $cur : '';
		}
		if ( isset( $in['roles'] ) || isset( $in['new_role'] ) ) {
			$roles = array();
			foreach ( $in['roles'] ?? array() as $r ) {
				$key   = sanitize_key( $r['key'] );
				$label = trim( sanitize_text_field( $r['label'] ?? '' ) );
				if ( '' === $key || ! empty( $r['delete'] ) || ( '' === $label && ! isset( $known[ $key ] ) ) ) {
					continue;
				}
				$color         = sanitize_hex_color( $r['color'] ?? '' );
				$roles[ $key ] = array( 'label' => mb_substr( $label, 0, 40 ), 'color' => $color ? $color : '#64748b' );
			}
			$new = $in['new_role'] ?? null;
			if ( is_array( $new ) && '' !== trim( sanitize_text_field( $new['label'] ) ) ) {
				$label = trim( sanitize_text_field( $new['label'] ) );
				$key   = sanitize_key( remove_accents( $label ) );
				$key   = '' !== $key ? substr( str_replace( '-', '_', $key ), 0, 24 ) : 'role' . wp_rand( 100, 999 );
				if ( ! isset( $roles[ $key ] ) && ! isset( $known[ $key ] ) ) {
					$color         = sanitize_hex_color( $new['color'] ?? '' );
					$roles[ $key ] = array( 'label' => mb_substr( $label, 0, 40 ), 'color' => $color ? $color : '#64748b' );
				}
			}
			$v['roles'] = $roles;
		}
		flxcrm_save_settings( $v );
		FLXCRM_Contact_Service::changed();
		if ( function_exists( 'flx_gql_purge' ) ) {
			flx_gql_purge( array( FLXCRM_Contact_Service::TAG_META, FLXCRM_Segment_Service::TAG ) );
		}
		return true;
	}

	/**
	 * Flux AI / MCP connection and the tool list.
	 *
	 * @return array{ai_installed: bool, mcp_installed: bool, mcp_enabled: bool, mcp_url: string, mcp_admin_url: string, tools_on: int, tools: list<array{name: string, title: string, kind: string, kind_label: string, enabled: bool}>}
	 */
	public static function ai(): array {
		$on     = flxcrm_tools_enabled();
		$kinds  = flxcrm_kind_labels();
		$tools  = array();
		foreach ( flxcrm_tools() as $name => $t ) {
			$t       = FLXCRM_Val::arr( $t );
			$kind    = FLXCRM_Val::str( $t['kind'] ?? 'read' );
			$tools[] = array(
				'name'       => (string) $name,
				'title'      => FLXCRM_Val::str( $t['title'] ?? $name ),
				'kind'       => $kind,
				'kind_label' => FLXCRM_Val::str( $kinds[ $kind ] ?? $kind ),
				'enabled'    => isset( $on[ $name ] ),
			);
		}
		$mcp = function_exists( 'flxai_mcp_url' );
		return array(
			'ai_installed'  => function_exists( 'flxai_chat' ),
			'mcp_installed' => $mcp,
			'mcp_enabled'   => $mcp && function_exists( 'flxai_mcp_enabled' ) && (bool) flxai_mcp_enabled(),
			'mcp_url'       => $mcp ? FLXCRM_Val::str( flxai_mcp_url() ) : '',
			'mcp_admin_url' => $mcp && function_exists( 'flxai_mcp_admin_url' ) && current_user_can( 'manage_options' ) ? FLXCRM_Val::str( flxai_mcp_admin_url() ) : '',
			'tools_on'      => count( $on ),
			'tools'         => $tools,
		);
	}

	/**
	 * Recent changes made with Flux AI, MCP or automation.
	 *
	 * @param int $limit Rows.
	 * @return list<array{at: string, who: string, channel: string, channel_label: string, action: string, target: string, ok: bool, error: string}>
	 */
	public static function ai_log( int $limit = 15 ): array {
		$tools = flxcrm_tools();
		$chs   = flxcrm_channel_labels();
		$out   = array();
		foreach ( flxcrm_tool_log_rows( $limit ) as $r ) {
			$r      = FLXCRM_Val::arr( $r );
			$u      = get_userdata( FLXCRM_Val::int( $r['u'] ?? 0 ) );
			$key    = FLXCRM_Val::str( $r['k'] ?? '' );
			$on     = FLXCRM_Val::str( $r['on'] ?? '' );
			if ( ctype_digit( $on ) && false === strpos( $key, 'tag' ) && false === strpos( $key, 'segment' ) && false === strpos( $key, 'event' ) ) {
				$c  = FLXCRM_Contacts::get( (int) $on );
				$on = $c ? FLXCRM_Val::str( flxcrm_display_name( $c ) ) : '#' . $on;
			}
			$ch    = FLXCRM_Val::str( $r['ch'] ?? '' );
			$out[] = array(
				'at'            => gmdate( 'Y-m-d H:i:s', FLXCRM_Val::int( $r['t'] ?? 0 ) ),
				'who'           => $u ? $u->display_name : '',
				'channel'       => $ch,
				'channel_label' => FLXCRM_Val::str( $chs[ $ch ] ?? $ch ),
				'action'        => FLXCRM_Val::str( FLXCRM_Val::arr( $tools[ $key ] ?? array() )['title'] ?? $key ),
				'target'        => $on,
				'ok'            => FLXCRM_Val::bool( $r['ok'] ?? false ),
				'error'         => FLXCRM_Val::str( $r['err'] ?? '' ),
			);
		}
		return $out;
	}
}
