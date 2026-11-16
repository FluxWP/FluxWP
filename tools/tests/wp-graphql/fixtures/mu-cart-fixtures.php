<?php
/**
 * Test fixture for the cart bridge extension point (works with WooCommerce and with the Store API stand-in):
 * - flxTestCartNoteAdd: a package mutation that adds a line with its own cart item data through
 *   FLXGQL_Cart::add_item() (as Flux Donate adds a donation),
 * - the line shows a "Note" detail (woocommerce_get_item_data) and the kind "test-note" (flx_gql_cart_item_kind).
 */

defined( 'ABSPATH' ) || exit;

add_filter(
	'flx_gql_persisted_manifests',
	static function ( $m ) {
		$m                  = is_array( $m ) ? $m : array();
		$m['flx-test-cart'] = __DIR__ . '/flx-test-cart-persisted.json';
		return $m;
	}
);

add_filter(
	'woocommerce_get_item_data',
	static function ( $data, $item ) {
		if ( is_array( $item ) && ! empty( $item['flx_test_note'] ) ) {
			$data   = is_array( $data ) ? $data : array();
			$data[] = array( 'name' => 'Note', 'value' => (string) $item['flx_test_note'] );
		}
		return $data;
	},
	10,
	2
);

add_filter(
	'flx_gql_cart_item_kind',
	static function ( $kind, $wc, $store ) {
		if ( is_array( $wc ) && ! empty( $wc['flx_test_note'] ) ) {
			return 'test-note';
		}
		foreach ( (array) ( $store['item_data'] ?? array() ) as $d ) {
			if ( 'Note' === ( $d['name'] ?? '' ) ) {
				return 'test-note';
			}
		}
		return $kind;
	},
	10,
	3
);

// A fixed line: quantity cannot be changed in the Store API (as a donation).
add_filter(
	'woocommerce_store_api_product_quantity_editable',
	static function ( $editable, $product, $item ) {
		return is_array( $item ) && ! empty( $item['flx_test_fixed'] ) ? false : $editable;
	},
	10,
	3
);

add_action(
	'flx_gql_register',
	static function () {
		flx_gql_mutation(
			'flxTestCartNoteAdd',
			array(
				'cap'     => 'public',
				'input'   => array(
					'productId' => array( 'type' => 'Int!' ),
					'note'      => array( 'type' => 'String!' ),
				),
				'output'  => array(
					'cart'    => array( 'type' => 'FlxCart' ),
					'itemKey' => array( 'type' => 'String' ),
				),
				'resolve' => static fn( array $input ) => FLXGQL_Cart::add_item(
					(int) $input['productId'],
					1,
					array(),
					array(
						'flx_test_note'  => sanitize_text_field( (string) $input['note'] ),
						'flx_test_fixed' => true,
					)
				),
			)
		);
	}
);
