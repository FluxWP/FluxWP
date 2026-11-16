<?php
/**
 * "My donations": the signed-in account, or a lookup session that has just proved an e-mail or phone.
 * Anyone else gets an empty page. The REST list is the same gift_out() rows as before.
 *
 * @package FLX_Bagis
 */

defined( 'ABSPATH' ) || exit;

final class FLXB_My_Donations_Service {

	/**
	 * One page of the account's gifts, in the app's shape.
	 *
	 * @return array{items: list<array<string,mixed>>, total: int, page: int, pages: int}
	 */
	public static function page( int $user_id, int $page, int $per ): array {
		$gifts = $user_id > 0 ? FLXB_App::gifts( $user_id ) : array();
		$per   = max( 1, min( 50, $per ) );
		$page  = max( 1, $page );
		$total = count( $gifts );
		$items = array();
		foreach ( array_slice( $gifts, ( $page - 1 ) * $per, $per ) as $gift ) {
			$items[] = FLXB_App::gift_out( $gift );
		}
		return array(
			'items' => $items,
			'total' => $total,
			'page'  => $page,
			'pages' => (int) ceil( $total / $per ),
		);
	}

	/**
	 * Gifts the current viewer may see: their account, else the lookup session.
	 *
	 * @return list<array<string,mixed>>
	 */
	public static function visible(): array {
		if ( is_user_logged_in() ) {
			$out = array();
			foreach ( FLXB_App::gifts( get_current_user_id() ) as $gift ) {
				$out[] = FLXB_App::gift_out( $gift );
			}
			return $out;
		}
		$s = FLXB_Lookup::session();
		if ( ! is_array( $s ) || '' === $s['type'] ) {
			return array();
		}
		$data  = FLXB_Lookup::proven( $s['type'], $s['value'] );
		$items = $data['items'] ?? array();
		if ( ! is_array( $items ) ) {
			return array();
		}
		$out = array();
		foreach ( $items as $row ) {
			if ( is_array( $row ) ) {
				$out[] = self::row( $row );
			}
		}
		return $out;
	}

	/**
	 * @param array<mixed> $row Row.
	 * @return array<string,mixed>
	 */
	private static function row( array $row ): array {
		$out = array();
		foreach ( $row as $k => $v ) {
			if ( is_string( $k ) ) {
				$out[ $k ] = $v;
			}
		}
		return $out;
	}

	/**
	 * Whether this request may read "My donations" (account or proven lookup session).
	 */
	public static function can_see(): bool {
		if ( is_user_logged_in() ) {
			return true;
		}
		$s = FLXB_Lookup::session();
		return is_array( $s ) && ! empty( $s['type'] );
	}
}
