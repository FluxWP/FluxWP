<?php
/**
 * Connected accounts: list, change, order, remove, refresh, WhatsApp targets, connect link.
 * REST, GraphQL and MCP use the same methods. Tokens never leave the server: only FLXSM_Account is returned.
 *
 * @package flx-social
 */

defined( 'ABSPATH' ) || exit;

final class FLXSM_Accounts_Service {

	public const CACHE_TAG = 'flx-social:accounts';

	/**
	 * Networks and what each one can do.
	 *
	 * @return list<FLXSM_Network>
	 */
	public static function networks(): array {
		$out = array();
		foreach ( flxsm_providers() as $id => $p ) {
			$out[] = new FLXSM_Network(
				FLXSM_Cast::str( $id ),
				FLXSM_Cast::str( $p->label() ),
				FLXSM_Cast::str( $p->color() ),
				FLXSM_Caps::from_array( FLXSM_Cast::arr( $p->caps() ) )
			);
		}
		return $out;
	}

	/**
	 * Every connected account in the panel order.
	 *
	 * @return list<FLXSM_Account>
	 */
	public static function accounts(): array {
		$out = array();
		foreach ( FLXSM_Cast::rows( flxsm_accounts() ) as $row ) {
			$out[] = FLXSM_Account::from_array( $row );
		}
		return $out;
	}

	/**
	 * One account.
	 *
	 * @param int $id Account ID.
	 * @return FLXSM_Account|WP_Error
	 */
	public static function account( int $id ) {
		$row = flxsm_account_row( $id );
		if ( ! is_array( $row ) ) {
			return self::not_found();
		}
		return FLXSM_Account::from_array( FLXSM_Cast::arr( flxsm_account_public( $row ) ) );
	}

	/**
	 * Renames, enables/disables or reorders one account. Null leaves a value as it is.
	 *
	 * @param int         $id       Account ID.
	 * @param bool|null   $disabled Switch the account off or on.
	 * @param string|null $name     Display name.
	 * @param int|null    $sort     Position.
	 * @return FLXSM_Account|WP_Error
	 */
	public static function update( int $id, ?bool $disabled, ?string $name, ?int $sort ) {
		$row = flxsm_account_row( $id );
		if ( ! is_array( $row ) ) {
			return self::not_found();
		}
		$patch = array();
		if ( null !== $disabled ) {
			$patch['status'] = $disabled ? 'disabled' : 'active';
		}
		if ( null !== $name ) {
			$patch['name'] = mb_substr( sanitize_text_field( $name ), 0, 190 );
		}
		if ( null !== $sort ) {
			$patch['sort'] = $sort;
		}
		if ( $patch ) {
			flxsm_update_account( $id, $patch );
		}
		return self::account( $id );
	}

	/**
	 * Saves the order of the accounts.
	 *
	 * @param list<int> $ids Account IDs in the new order.
	 * @return list<FLXSM_Account>
	 */
	public static function reorder( array $ids ): array {
		foreach ( array_values( $ids ) as $i => $id ) {
			flxsm_update_account( $id, array( 'sort' => $i + 1 ) );
		}
		return self::accounts();
	}

	/**
	 * Removes an account; its open posts are cancelled.
	 *
	 * @param int $id Account ID.
	 * @return true|WP_Error
	 */
	public static function delete( int $id ) {
		if ( ! is_array( flxsm_account_row( $id ) ) ) {
			return self::not_found();
		}
		flxsm_delete_account( $id );
		return true;
	}

	/**
	 * Renews the token and reads the account numbers again.
	 *
	 * @param int $id Account ID.
	 * @return FLXSM_Account|WP_Error
	 */
	public static function refresh( int $id ) {
		$acc  = flxsm_account( $id );
		$prov = is_array( $acc ) ? flxsm_provider( FLXSM_Cast::str( $acc['provider'] ?? '' ) ) : null;
		if ( ! is_array( $acc ) || ! $prov ) {
			return self::not_found();
		}
		$acc = $prov->ensure( $acc );
		if ( is_wp_error( $acc ) ) {
			flxsm_account_failed( $id, $acc->get_error_message(), true );
			return new WP_Error( 'flxsm_expired', $acc->get_error_message(), array( 'status' => 400 ) );
		}
		$acc = FLXSM_Cast::arr( $acc );
		if ( $prov instanceof FLXSM_Pinterest ) {
			$prov->reload_boards( $acc );
		}
		$m = $prov->account_metrics( $acc );
		if ( is_wp_error( $m ) && 'flxsm_unsupported' !== $m->get_error_code() ) {
			$d = FLXSM_Cast::arr( $m->get_error_data() );
			flxsm_account_failed( $id, $m->get_error_message(), ! empty( $d['auth'] ) );
			return new WP_Error( 'flxsm_api', $m->get_error_message(), array( 'status' => 400 ) );
		}
		flxsm_update_account(
			$id,
			array(
				'last_error' => '',
				'status'     => 'disabled' === ( $acc['status'] ?? '' ) ? 'disabled' : 'active',
			)
		);
		flxsm_collect_metrics( 20, $id );
		return self::account( $id );
	}

	/**
	 * Groups and Status the WhatsApp connection can post to.
	 *
	 * @return list<FLXSM_WA_Target>|WP_Error
	 */
	public static function whatsapp_targets() {
		$prov = flxsm_provider( 'whatsapp' );
		if ( ! $prov instanceof FLXSM_WhatsApp ) {
			return self::not_found();
		}
		$t = $prov->targets();
		if ( is_wp_error( $t ) ) {
			return new WP_Error( $t->get_error_code(), $t->get_error_message(), array( 'status' => 400 ) );
		}
		$out = array();
		foreach ( FLXSM_Cast::rows( $t ) as $x ) {
			$ext   = FLXSM_Cast::str( $x['ext_id'] ?? '' );
			$out[] = new FLXSM_WA_Target(
				$ext,
				FLXSM_Cast::str( $x['name'] ?? $ext ),
				FLXSM_Cast::str( $x['kind'] ?? 'group' ),
				FLXSM_Cast::str( $x['avatar'] ?? '' ),
				(bool) flxsm_account_exists( 'whatsapp', $ext )
			);
		}
		return $out;
	}

	/**
	 * Adds WhatsApp groups or Status as accounts.
	 *
	 * @param list<array{ext_id:string,name:string,kind:string,avatar:string}> $items Targets.
	 * @return int|WP_Error Number added.
	 */
	public static function whatsapp_add( array $items ) {
		$cfg = flxsm_wa_config();
		if ( is_wp_error( $cfg ) ) {
			return $cfg;
		}
		$cfg = FLXSM_Cast::arr( $cfg );
		$n   = 0;
		foreach ( $items as $it ) {
			$ext = sanitize_text_field( $it['ext_id'] );
			if ( '' === $ext ) {
				continue;
			}
			flxsm_upsert_account(
				array(
					'provider' => 'whatsapp',
					'via'      => 'evolution',
					'ext_id'   => $ext,
					'name'     => sanitize_text_field( '' !== $it['name'] ? $it['name'] : $ext ),
					'username' => FLXSM_Cast::str( $cfg['instance'] ?? '' ),
					'avatar'   => esc_url_raw( $it['avatar'] ),
					'token'    => array(),
					'expires'  => 0,
					'meta'     => array( 'kind' => 'status' === $it['kind'] ? 'status' : 'group' ),
				)
			);
			++$n;
		}
		return $n;
	}

	/**
	 * The address that starts connecting a network (the browser follows it to the network).
	 *
	 * @param string $for Network key (facebook, youtube, …).
	 * @return string|WP_Error
	 */
	public static function connect_url( string $for ) {
		if ( ! is_array( flxsm_connect_target( sanitize_key( $for ) ) ) ) {
			return new WP_Error( 'flxsm_network', __( 'Unknown network.', 'flx-social' ), array( 'status' => 400 ) );
		}
		return add_query_arg(
			array(
				'for'      => sanitize_key( $for ),
				'_wpnonce' => wp_create_nonce( 'wp_rest' ),
			),
			rest_url( FLXSM_NS . '/oauth/start' )
		);
	}

	/**
	 * @return WP_Error
	 */
	private static function not_found(): WP_Error {
		return new WP_Error( 'flxsm_not_found', __( 'Record not found.', 'flx-social' ), array( 'status' => 404 ) );
	}
}
