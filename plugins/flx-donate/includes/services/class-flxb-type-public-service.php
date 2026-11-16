<?php
/**
 * Public donation types. REST and GraphQL both read this; the REST body stays the array
 * flxb_get_types() / flxb_get_type() already returned (filter flxb_type_public included).
 *
 * @package FLX_Bagis
 */

defined( 'ABSPATH' ) || exit;

/**
 * Public type row from flxb_type_public().
 *
 * @phpstan-type FlxbPublicOption array{key: string, label: string, price: float|null, prices: array<string, float>, unit: string|null, unit_label: string|null, units_per: int, active: bool}
 * @phpstan-type FlxbPublicProgress array{raised: float, gifts: int, goal: float, percent: float, currency: string}
 * @phpstan-type FlxbPublicRow array{id: int, slug: string, title: string, description: string, image_url: string, currency: string, state: string, state_message: string, category: string, category_label: string, options: list<FlxbPublicOption>, allow_free: bool, min_amount: float, allow_quantity: bool, requires_proxy: bool, progress: FlxbPublicProgress|null}
 */
final class FLXB_Type_Public_Service {

	public const TAG = 'flx-donate:types';

	/**
	 * Visible or open types. Archived types are never included.
	 *
	 * @param string $status   visible|open.
	 * @param string $category Category slug, or empty.
	 * @return list<FlxbPublicRow>
	 */
	public static function list( string $status, string $category ): array {
		$status = in_array( $status, array( 'visible', 'open' ), true ) ? $status : 'visible';
		return flxb_get_types(
			array(
				'status'   => $status,
				'category' => sanitize_key( $category ),
			)
		);
	}

	/**
	 * One type by id or slug.
	 *
	 * @param string $ref Id or slug.
	 * @return FlxbPublicRow|WP_Error
	 */
	public static function one( string $ref ) {
		$t = flxb_get_type( $ref );
		if ( ! is_array( $t ) || 'archived' === ( $t['state'] ?? '' ) ) {
			return new WP_Error( 'flxb_not_found', __( 'Donation type not found.', 'flx-donate' ), array( 'status' => 404 ) );
		}
		return $t;
	}

	/**
	 * The account flxb_bank_account() returns: the donation bank-transfer gateway when that class is loaded, otherwise WooCommerce BACS.
	 *
	 * @return array{holder: string, iban: string, iban_display: string, bank: string}|null
	 */
	public static function bank(): ?array {
		$a = flxb_bank_account();
		return is_array( $a ) && '' !== $a['iban'] ? $a : null;
	}

	/**
	 * @return array{items: list<FlxbPublicRow>, total: int}
	 */
	public static function page( string $status, string $category, int $offset, int $limit ): array {
		$all = self::list( $status, $category );
		return array(
			'items' => array_slice( $all, max( 0, $offset ), max( 0, $limit ) ),
			'total' => count( $all ),
		);
	}
}
