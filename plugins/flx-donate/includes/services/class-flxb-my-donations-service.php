<?php
/**
 * "My donations": the signed-in account, or a lookup session that has just proved an e-mail or phone.
 * Anyone else gets an empty page. The REST list is the same gift_out() rows as before.
 *
 * @package FLX_Bagis
 */

defined( 'ABSPATH' ) || exit;

/**
 * One gift the account and the lookup page already return, plus the stored UTC time.
 *
 * @phpstan-type FlxbMyGift array{id: int, title: string, status: string, date: string, amount: float, currency: string, created_at: string}
 */
final class FLXB_My_Donations_Service {

	/**
	 * One page of the account's gifts, in the app's shape (REST stays gift_out()).
	 *
	 * @return array{items: list<array{id: int, order_id: int, date: string, title: string, amount: float, currency: string, status: string}>, total: int, page: int, pages: int}
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
	 * The rows are gift_out(); created_at is the UTC value the donation row stores.
	 *
	 * @return list<FlxbMyGift>
	 */
	public static function visible(): array {
		if ( is_user_logged_in() ) {
			return self::from_groups( FLXB_App::gifts( get_current_user_id() ) );
		}
		$s = FLXB_Lookup::session();
		if ( ! is_array( $s ) || '' === $s['type'] ) {
			return array();
		}
		return self::from_groups( FLXB_Lookup::gifts( $s['type'], $s['value'] ) );
	}

	/**
	 * @param list<array<int, array<string, mixed>>> $groups Grouped donation rows.
	 * @return list<FlxbMyGift>
	 */
	private static function from_groups( array $groups ): array {
		$out = array();
		foreach ( $groups as $gift ) {
			$first = $gift[0] ?? null;
			if ( ! is_array( $first ) ) {
				continue;
			}
			$row   = FLXB_App::gift_out( $gift );
			$out[] = array(
				'id'         => (int) $row['id'],
				'title'      => (string) $row['title'],
				'status'     => (string) $row['status'],
				'date'       => (string) $row['date'],
				'amount'     => (float) $row['amount'],
				'currency'   => (string) $row['currency'],
				'created_at' => is_scalar( $first['created_at'] ?? null ) ? (string) $first['created_at'] : '',
			);
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
