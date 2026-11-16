<?php
/**
 * Flux Commerce GraphQL schema. Loaded on flx_gql_register only, so the plugin still runs
 * when Flux GraphQL is not installed. Resolvers call the services. Card meta (`d`) is not a field.
 *
 * flxSubscriptionCartAdd is public: the product page already lets a guest choose a period and
 * add it. The mutation goes through FLXGQL_Cart::add_item(); checkout stays on the Store API.
 *
 * @package FLX_Commerce
 */

defined( 'ABSPATH' ) || exit;

function flxcm_gql_view(): Closure {
	return flx_gql_any_cap( 'view_woocommerce_reports', 'manage_woocommerce' );
}

function flxcm_gql_approve(): Closure {
	return flx_gql_any_cap( 'edit_shop_orders', 'manage_woocommerce' );
}

/**
 * @param mixed $result Service result.
 * @return mixed
 * @throws FLXGQL_Error When the service returned WP_Error.
 */
function flxcm_gql_throw( $result ) {
	if ( ! $result instanceof WP_Error ) {
		return $result;
	}
	$data   = $result->get_error_data();
	$status = is_array( $data ) && isset( $data['status'] ) ? FLXCM_Val::int($data['status']) : 0;
	if ( 404 === $status ) {
		$code = 'NOT_FOUND';
	} elseif ( 409 === $status ) {
		$code = 'CONFLICT';
	} elseif ( 403 === $status ) {
		$code = 'FORBIDDEN';
	} elseif ( 400 === $status ) {
		$code = 'INVALID_INPUT';
	} else {
		$code = 'INTERNAL';
	}
	throw new FLXGQL_Error( $code, $result->get_error_message() );
}

/**
 * @param string $tag Cache tag.
 */
function flxcm_gql_tag( string $tag ): void {
	if ( function_exists( 'flx_gql_cache_tag' ) ) {
		flx_gql_cache_tag( $tag );
	}
}

/**
 * @param string $name GraphQL type name.
 */
function flxcm_gql_has_type( string $name ): bool {
	return flxcm_gql_type_exists( $name );
}

/**
 * @param array<string,mixed> $act Account action result.
 * @return array<string,mixed>
 * @throws FLXGQL_Error On refusal.
 */
function flxcm_gql_account( array $act ): array {
	$code = FLXCM_Val::str($act['code']);
	if ( 'OK' !== $code ) {
		throw new FLXGQL_Error( $code, FLXCM_Val::str($act['notice']) );
	}
	return array(
		'subscription' => $act['subscription'],
		'notice'       => FLXCM_Val::str($act['notice']),
		'payUrl'       => '' !== FLXCM_Val::str($act['redirect']) ? FLXCM_Val::str($act['redirect']) : null,
	);
}

FLXCM_Cart_Service::init();

flx_gql_enum(
	'FlxCommerceSegment',
	array(
		'description' => __( 'Customer list filter.', 'flx-commerce' ),
		'values'      => array(
			'ALL'    => 'all',
			'REPEAT' => 'repeat',
			'VIP'    => 'vip',
			'LAPSED' => 'lapsed',
			'NEW'    => 'new',
		),
	)
);
flx_gql_enum(
	'FlxCommerceCustomerSort',
	array(
		'description' => __( 'Customer list sort.', 'flx-commerce' ),
		'values'      => array(
			'LAST_AT' => 'last_at',
			'TOTAL'   => 'total',
			'ORDERS'  => 'orders',
			'NAME'    => 'name',
		),
	)
);
flx_gql_enum(
	'FlxCommerceSortDirection',
	array(
		'description' => __( 'Sort direction.', 'flx-commerce' ),
		'values'      => array(
			'ASC'  => 'asc',
			'DESC' => 'desc',
		),
	)
);
flx_gql_enum(
	'FlxCommerceSubscriptionStatus',
	array(
		'description' => __( 'Subscription status.', 'flx-commerce' ),
		'values'      => array(
			'PENDING'   => 'pending',
			'ACTIVE'    => 'active',
			'PAST_DUE'  => 'past_due',
			'CANCELLED' => 'cancelled',
			'EXPIRED'   => 'expired',
		),
	)
);
flx_gql_enum(
	'FlxCommercePeriod',
	array(
		'description' => __( 'Billing period.', 'flx-commerce' ),
		'values'      => array(
			'MONTH' => 'month',
			'YEAR'  => 'year',
		),
	)
);
flx_gql_enum(
	'FlxCommerceReportPreset',
	array(
		'description' => __( 'Sales report range.', 'flx-commerce' ),
		'values'      => array(
			'D7'         => '7',
			'D30'        => '30',
			'D90'        => '90',
			'MONTH'      => 'month',
			'LAST_MONTH' => 'last_month',
			'YEAR'       => 'year',
		),
	)
);
flx_gql_enum(
	'FlxCommerceTimelineFilter',
	array(
		'description' => __( 'Customer timeline filter.', 'flx-commerce' ),
		'values'      => array(
			'ALL'    => 'all',
			'ORDERS' => 'orders',
			'PAID'   => 'paid',
			'NOTES'  => 'notes',
		),
	)
);

$money = array(
	'type'        => 'FlxMoney!',
	'description' => __( 'Amount.', 'flx-commerce' ),
);
$when  = array(
	'type'        => 'FlxDateTime',
	'description' => __( 'When it happened. Empty when unknown.', 'flx-commerce' ),
);

flx_gql_type(
	'FlxCommerceDay',
	array(
		'description' => __( 'One day of revenue.', 'flx-commerce' ),
		'fields'      => array(
			'date'    => array(
				'type'        => 'String!',
				'description' => __( 'Day, Y-m-d in the site timezone.', 'flx-commerce' ),
			),
			'orders'  => array(
				'type'        => 'Int!',
				'description' => __( 'Paid orders that day.', 'flx-commerce' ),
			),
			'revenue' => $money,
		),
	)
);
flx_gql_connection( 'FlxCommerceDay', __( 'A page of days.', 'flx-commerce' ) );

flx_gql_type(
	'FlxCommerceProductStat',
	array(
		'description' => __( 'Units and revenue for one product.', 'flx-commerce' ),
		'fields'      => array(
			'name'      => array(
				'type'        => 'String!',
				'description' => __( 'Product name.', 'flx-commerce' ),
			),
			'productId' => array(
				'type'        => 'Int!',
				'description' => __( 'Product id. 0 when the line had no product.', 'flx-commerce' ),
			),
			'quantity'  => array(
				'type'        => 'Int!',
				'description' => __( 'Units sold, refunds removed.', 'flx-commerce' ),
			),
			'total'     => $money,
		),
	)
);
flx_gql_connection( 'FlxCommerceProductStat', __( 'A page of products.', 'flx-commerce' ) );

flx_gql_type(
	'FlxCommerceCategoryStat',
	array(
		'description' => __( 'Units and revenue for one category.', 'flx-commerce' ),
		'fields'      => array(
			'name'     => array(
				'type'        => 'String!',
				'description' => __( 'Category name.', 'flx-commerce' ),
			),
			'quantity' => array(
				'type'        => 'Int!',
				'description' => __( 'Units sold.', 'flx-commerce' ),
			),
			'total'    => $money,
		),
	)
);
flx_gql_connection( 'FlxCommerceCategoryStat', __( 'A page of categories.', 'flx-commerce' ) );

flx_gql_type(
	'FlxCommerceMethodStat',
	array(
		'description' => __( 'Orders and revenue for one payment method.', 'flx-commerce' ),
		'fields'      => array(
			'orders' => array(
				'type'        => 'Int!',
				'description' => __( 'Orders.', 'flx-commerce' ),
			),
			'title'  => array(
				'type'        => 'String!',
				'description' => __( 'Method name.', 'flx-commerce' ),
			),
			'total'  => $money,
		),
	)
);
flx_gql_connection( 'FlxCommerceMethodStat', __( 'A page of payment methods.', 'flx-commerce' ) );

flx_gql_type(
	'FlxCommerceStock',
	array(
		'description' => __( 'A product at or under the low-stock threshold.', 'flx-commerce' ),
		'fields'      => array(
			'id'    => array(
				'type'        => 'Int!',
				'description' => __( 'Product id.', 'flx-commerce' ),
			),
			'name'  => array(
				'type'        => 'String!',
				'description' => __( 'Product name.', 'flx-commerce' ),
			),
			'stock' => array(
				'type'        => 'Int!',
				'description' => __( 'Units in stock.', 'flx-commerce' ),
			),
			'url'   => array(
				'type'        => 'String!',
				'description' => __( 'Edit link. Empty when unknown.', 'flx-commerce' ),
			),
		),
	)
);

flx_gql_type(
	'FlxCommerceSummary',
	array(
		'description' => __( 'Store overview for a short range (at most 90 days).', 'flx-commerce' ),
		'fields'      => array(
			'aov'          => $money,
			'aovDelta'     => array(
				'type'        => 'Float',
				'description' => __( 'Percent change against the previous range. Null when the previous range was empty.', 'flx-commerce' ),
			),
			'awaiting'     => array(
				'type'        => 'Int!',
				'description' => __( 'Orders waiting for payment.', 'flx-commerce' ),
			),
			'customers'    => array(
				'type'        => 'Int!',
				'description' => __( 'Customers who ordered in the range.', 'flx-commerce' ),
			),
			'days'         => array(
				'type'        => 'Int!',
				'description' => __( 'Length of the range in days.', 'flx-commerce' ),
			),
			'from'         => array(
				'type'        => 'String!',
				'description' => __( 'First day, Y-m-d.', 'flx-commerce' ),
			),
			'items'        => array(
				'type'        => 'Int!',
				'description' => __( 'Units sold.', 'flx-commerce' ),
			),
			'itemsDelta'   => array(
				'type'        => 'Float',
				'description' => __( 'Percent change in units.', 'flx-commerce' ),
			),
			'lowStock'     => array(
				'type'        => '[FlxCommerceStock!]!',
				'description' => __( 'Up to eight products at or under the threshold.', 'flx-commerce' ),
			),
			'newCustomers' => array(
				'type'        => 'Int!',
				'description' => __( 'Customers whose first order is in the range.', 'flx-commerce' ),
			),
			'orders'       => array(
				'type'        => 'Int!',
				'description' => __( 'Paid orders.', 'flx-commerce' ),
			),
			'ordersDelta'  => array(
				'type'        => 'Float',
				'description' => __( 'Percent change in orders.', 'flx-commerce' ),
			),
			'revenue'      => $money,
			'revenueDelta' => array(
				'type'        => 'Float',
				'description' => __( 'Percent change in net revenue.', 'flx-commerce' ),
			),
			'series'       => array(
				'type'        => '[FlxCommerceDay!]!',
				'description' => __( 'One point per day. A longer report uses flxCommerceReportDays.', 'flx-commerce' ),
			),
			'to'           => array(
				'type'        => 'String!',
				'description' => __( 'Last day, Y-m-d.', 'flx-commerce' ),
			),
		),
	)
);

flx_gql_type(
	'FlxCommerceOrder',
	array(
		'description' => __( 'An order on a customer card.', 'flx-commerce' ),
		'fields'      => array(
			'createdAt'   => $when,
			'id'          => array(
				'type'        => 'ID!',
				'description' => __( 'Order id.', 'flx-commerce' ),
			),
			'number'      => array(
				'type'        => 'String!',
				'description' => __( 'Order number.', 'flx-commerce' ),
			),
			'paid'        => array(
				'type'        => 'Boolean!',
				'description' => __( 'The order counts as paid.', 'flx-commerce' ),
			),
			'status'      => array(
				'type'        => 'String!',
				'description' => __( 'Status slug.', 'flx-commerce' ),
			),
			'statusLabel' => array(
				'type'        => 'String!',
				'description' => __( 'Status name.', 'flx-commerce' ),
			),
			'total'       => $money,
			'url'         => array(
				'type'        => 'String!',
				'description' => __( 'Edit link.', 'flx-commerce' ),
			),
		),
	)
);

flx_gql_type(
	'FlxCommerceEvent',
	array(
		'description' => __( 'One event on the customer timeline.', 'flx-commerce' ),
		'fields'      => array(
			'at'    => $when,
			'label' => array(
				'type'        => 'String!',
				'description' => __( 'Short label.', 'flx-commerce' ),
			),
			'paid'  => array(
				'type'        => 'Boolean!',
				'description' => __( 'The event is a paid order.', 'flx-commerce' ),
			),
			'text'  => array(
				'type'        => 'String!',
				'description' => __( 'Note text. Empty when there is none.', 'flx-commerce' ),
			),
			'title' => array(
				'type'        => 'String!',
				'description' => __( 'Title.', 'flx-commerce' ),
			),
			'type'  => array(
				'type'        => 'String!',
				'description' => __( 'order, paid, refund, note or customer_note.', 'flx-commerce' ),
			),
			'url'   => array(
				'type'        => 'String!',
				'description' => __( 'Related admin link. Empty when there is none.', 'flx-commerce' ),
			),
		),
	)
);

flx_gql_type(
	'FlxCommerceAddress',
	array(
		'description' => __( 'A formatted address from the latest order.', 'flx-commerce' ),
		'fields'      => array(
			'kind' => array(
				'type'        => 'String!',
				'description' => __( 'billing or shipping.', 'flx-commerce' ),
			),
			'text' => array(
				'type'        => 'String!',
				'description' => __( 'Address as the classic screen shows it.', 'flx-commerce' ),
			),
		),
	)
);

flx_gql_type(
	'FlxCommerceCustomer',
	array(
		'description' => __( 'A store customer (order summary). The person, when Flux CRM has one, is the person field.', 'flx-commerce' ),
		'fields'      => array(
			'addresses' => array(
				'type'        => '[FlxCommerceAddress!]!',
				'description' => __( 'Billing and shipping on the latest order.', 'flx-commerce' ),
				'resolve'     => static function ( $row ) {
				$row = FLXCM_Val::map( $row );
					$src  = FLXCM_Customers::get( FLXCM_Val::int(( $row['id'] ?? 0 )) );
					$prof = is_array( $src ) ? FLXCM_Customer_Service::profile( $src ) : array( 'billing' => '', 'shipping' => '' );
					$out  = array();
					if ( '' !== FLXCM_Val::str($prof['billing']) ) {
						$out[] = array( 'kind' => 'billing', 'text' => FLXCM_Val::str($prof['billing']) );
					}
					if ( '' !== FLXCM_Val::str($prof['shipping']) ) {
						$out[] = array( 'kind' => 'shipping', 'text' => FLXCM_Val::str($prof['shipping']) );
					}
					return $out;
				},
			),
			'city'      => array(
				'type'        => 'String!',
				'description' => __( 'City.', 'flx-commerce' ),
			),
			'email'     => array(
				'type'        => 'String!',
				'description' => __( 'E-mail.', 'flx-commerce' ),
			),
			'firstAt'   => $when,
			'guest'     => array(
				'type'        => 'Boolean!',
				'description' => __( 'The customer has no WordPress account.', 'flx-commerce' ),
			),
			'id'        => array(
				'type'        => 'ID!',
				'description' => __( 'Customer id.', 'flx-commerce' ),
			),
			'lastAt'    => $when,
			'name'      => array(
				'type'        => 'String!',
				'description' => __( 'Name.', 'flx-commerce' ),
			),
			'note'      => array(
				'type'        => 'String!',
				'description' => __( 'Shop note.', 'flx-commerce' ),
			),
			'openOrders' => array(
				'type'        => 'Int!',
				'description' => __( 'Orders that are not finished.', 'flx-commerce' ),
				'resolve'     => static function ( $row ) {
				$row = FLXCM_Val::map( $row );
					$src = FLXCM_Customers::get( FLXCM_Val::int(( $row['id'] ?? 0 )) );
					if ( ! is_array( $src ) ) {
						return 0;
					}
					return FLXCM_Val::int(FLXCM_Customer_Service::profile( $src )['open']);
				},
			),
			'orders'    => array(
				'type'        => 'Int!',
				'description' => __( 'Paid orders.', 'flx-commerce' ),
			),
			'phone'     => array(
				'type'        => 'String!',
				'description' => __( 'Phone.', 'flx-commerce' ),
			),
			'products'  => array(
				'type'        => '[FlxCommerceProductStat!]!',
				'description' => __( 'Most bought products, up to five.', 'flx-commerce' ),
				'resolve'     => static function ( $row ) {
				$row = FLXCM_Val::map( $row );
					$src = FLXCM_Customers::get( FLXCM_Val::int(( $row['id'] ?? 0 )) );
					if ( ! is_array( $src ) ) {
						return array();
					}
					$out = array();
					foreach ( FLXCM_Customer_Service::profile( $src )['products'] as $p ) {
						$out[] = array(
							'productId' => FLXCM_Val::int(( $p['id'] ?? 0 )),
							'name'      => FLXCM_Val::str(( $p['name'] ?? '' )),
							'quantity'  => FLXCM_Val::int(( $p['qty'] ?? 0 )),
							'total'     => flxcm_gql_money( $p['total'] ?? 0 ),
						);
					}
					return $out;
				},
			),
			'recentOrders' => array(
				'type'        => '[FlxCommerceOrder!]!',
				'description' => __( 'Latest orders, up to ten.', 'flx-commerce' ),
				'resolve'     => static function ( $row ) {
				$row = FLXCM_Val::map( $row );
					$src = FLXCM_Customers::get( FLXCM_Val::int(( $row['id'] ?? 0 )) );
					if ( ! is_array( $src ) ) {
						return array();
					}
					$out = array();
					foreach ( array_slice( FLXCM_Customer_Service::profile( $src )['orders'], 0, 10 ) as $o ) {
						if ( ! $o instanceof WC_Order ) {
							continue;
						}
						$out[] = array(
							'id'          => FLXCM_Val::str($o->get_id()),
							'number'      => FLXCM_Val::str($o->get_order_number()),
							'status'      => FLXCM_Val::str($o->get_status()),
							'statusLabel' => function_exists( 'flxcm_status_label' ) ? FLXCM_Val::str(flxcm_status_label( $o->get_status() )) : FLXCM_Val::str($o->get_status()),
							'total'       => flxcm_gql_money( $o->get_total(), $o->get_currency() ),
							'createdAt'   => $o->get_date_created() ? flxcm_gql_time( $o->get_date_created()->getTimestamp() ) : null,
							'url'         => FLXCM_Val::str($o->get_edit_order_url()),
							'paid'        => (bool) $o->is_paid(),
						);
					}
					return $out;
				},
			),
			'timeline'  => array(
				'type'        => '[FlxCommerceEvent!]!',
				'description' => __( 'Timeline. filter matches the classic All / Orders / Payments / Notes chips.', 'flx-commerce' ),
				'args'        => array(
					'filter' => array(
						'type'        => 'FlxCommerceTimelineFilter',
						'description' => __( 'Which events to keep. Empty means all.', 'flx-commerce' ),
					),
				),
				'resolve'     => static function ( $row, $args ) {
				$row = FLXCM_Val::map( $row );
				$args = FLXCM_Val::map( $args );
					$src = FLXCM_Customers::get( FLXCM_Val::int(( $row['id'] ?? 0 )) );
					if ( ! is_array( $src ) ) {
						return array();
					}
					$want = FLXCM_Val::str(( $args['filter'] ?? 'all' ));
					$types = array();
					if ( 'orders' === $want ) {
						$types = array( 'order' );
					} elseif ( 'paid' === $want ) {
						$types = array( 'paid', 'refund', 'order_paid' );
					} elseif ( 'notes' === $want ) {
						$types = array( 'note', 'customer_note' );
					}
					$out = array();
					foreach ( array_slice( FLXCM_Customer_Service::profile( $src )['events'], 0, 50 ) as $e ) {
						$keep = ! $types || in_array( FLXCM_Val::str($e['type']), $types, true ) || ( in_array( 'order_paid', $types, true ) && 'order' === $e['type'] && ! empty( $e['paid'] ) );
						if ( ! $keep ) {
							continue;
						}
						$out[] = array(
							'type'  => FLXCM_Val::str(( $e['type'] ?? '' )),
							'at'    => flxcm_gql_time( FLXCM_Val::int(( $e['ts'] ?? 0 )) ),
							'label' => FLXCM_Val::str(( $e['label'] ?? '' )),
							'title' => FLXCM_Val::str(( $e['title'] ?? '' )),
							'text'  => FLXCM_Val::str(( $e['text'] ?? '' )),
							'url'   => FLXCM_Val::str(( $e['url'] ?? '' )),
							'paid'  => (bool) ( $e['paid'] ?? false ),
						);
					}
					return $out;
				},
			),
			'total'     => $money,
			'userId'    => array(
				'type'        => 'Int!',
				'description' => __( 'WordPress user id. 0 for a guest.', 'flx-commerce' ),
			),
		),
	)
);
flx_gql_connection( 'FlxCommerceCustomer', __( 'A page of customers.', 'flx-commerce' ) );

if ( flxcm_gql_has_type( 'FlxContact' ) ) {
	flx_gql_field(
		'FlxCommerceCustomer',
		'person',
		array(
			'type'        => 'FlxContact',
			'description' => __( 'The same person in Flux CRM. Empty when CRM has no match. CRM-only fields stay hidden.', 'flx-commerce' ),
			'resolve'     => static function ( $row, $args, $context ) {
				$row = FLXCM_Val::map( $row );
				$args = FLXCM_Val::map( $args );
				unset( $args );
				if ( ! function_exists( 'flxcrm_contact_for' ) || ! is_object( $context ) || ! method_exists( $context, 'get_loader' ) ) {
					return null;
				}
				$id = FLXCM_Val::int( flxcrm_contact_for( array( 'email' => FLXCM_Val::str( $row['email'] ?? '' ) ) ) );
				if ( $id <= 0 ) {
					return null;
				}
				$loader = $context->get_loader( 'FlxContact' );
				return is_object( $loader ) && method_exists( $loader, 'load_deferred' ) ? $loader->load_deferred( $id ) : null;
			},
		)
	);
}

flx_gql_type(
	'FlxCommerceCustomerCounts',
	array(
		'description' => __( 'Counts behind the customer filter chips.', 'flx-commerce' ),
		'fields'      => array(
			'all'    => array(
				'type'        => 'Int!',
				'description' => __( 'Customers with at least one order.', 'flx-commerce' ),
			),
			'built'  => array(
				'type'        => 'Boolean!',
				'description' => __( 'Older orders have been scanned. False shows the classic not-built notice.', 'flx-commerce' ),
			),
			'builtAt' => $when,
			'lapsed' => array(
				'type'        => 'Int!',
				'description' => __( 'No order in 90 days.', 'flx-commerce' ),
			),
			'new'    => array(
				'type'        => 'Int!',
				'description' => __( 'First order in the last 30 days.', 'flx-commerce' ),
			),
			'ltv'    => $money,
			'repeat' => array(
				'type'        => 'Int!',
				'description' => __( 'Two or more orders.', 'flx-commerce' ),
			),
			'spent'  => $money,
			'vip'    => array(
				'type'        => 'Int!',
				'description' => __( 'At or above the VIP threshold.', 'flx-commerce' ),
			),
		),
	)
);

flx_gql_type(
	'FlxCommerceReceiptFile',
	array(
		'description' => __( 'A receipt file. The bytes are served by the signed link, not by GraphQL.', 'flx-commerce' ),
		'fields'      => array(
			'name' => array(
				'type'        => 'String!',
				'description' => __( 'File name.', 'flx-commerce' ),
			),
			'url'  => array(
				'type'        => 'String!',
				'description' => __( 'Signed download link. Empty unless the viewer can open the file (edit_shop_orders).', 'flx-commerce' ),
			),
		),
	)
);

flx_gql_type(
	'FlxCommerceReceipt',
	array(
		'description' => __( 'A bank transfer waiting for approval.', 'flx-commerce' ),
		'fields'      => array(
			'createdAt'   => $when,
			'email'       => array(
				'type'        => 'String!',
				'description' => __( 'Billing e-mail.', 'flx-commerce' ),
			),
			'files'       => array(
				'type'        => '[FlxCommerceReceiptFile!]!',
				'description' => __( 'Uploaded receipts.', 'flx-commerce' ),
			),
			'id'          => array(
				'type'        => 'ID!',
				'description' => __( 'Order id.', 'flx-commerce' ),
			),
			'name'        => array(
				'type'        => 'String!',
				'description' => __( 'Billing name.', 'flx-commerce' ),
			),
			'number'      => array(
				'type'        => 'String!',
				'description' => __( 'Order number.', 'flx-commerce' ),
			),
			'phone'       => array(
				'type'        => 'String!',
				'description' => __( 'Billing phone.', 'flx-commerce' ),
			),
			'reference'   => array(
				'type'        => 'String!',
				'description' => __( 'Transfer reference.', 'flx-commerce' ),
			),
			'status'      => array(
				'type'        => 'String!',
				'description' => __( 'Order status slug.', 'flx-commerce' ),
			),
			'statusLabel' => array(
				'type'        => 'String!',
				'description' => __( 'Order status name.', 'flx-commerce' ),
			),
			'total'       => $money,
		),
	)
);
flx_gql_connection( 'FlxCommerceReceipt', __( 'A page of receipts.', 'flx-commerce' ) );

flx_gql_type(
	'FlxCommerceSubscription',
	array(
		'description' => __( 'A subscription. The stored card block is not on this type; cardLabel is the brand and last four digits.', 'flx-commerce' ),
		'fields'      => array(
			'amount'         => $money,
			'autoCharge'     => array(
				'type'        => 'Boolean!',
				'description' => __( 'The next renewal will be charged to the stored card.', 'flx-commerce' ),
			),
			'autoRenew'      => array(
				'type'        => 'Boolean!',
				'description' => __( 'Renewal is still on.', 'flx-commerce' ),
			),
			'cancelledAt'    => $when,
			'cardLabel'      => array(
				'type'        => 'String!',
				'description' => __( 'Brand and last four digits. Empty when no card is stored.', 'flx-commerce' ),
			),
			'createdAt'      => $when,
			'email'          => array(
				'type'        => 'String!',
				'description' => __( 'Customer e-mail.', 'flx-commerce' ),
			),
			'endedAt'        => $when,
			'endReason'      => array(
				'type'        => 'String!',
				'description' => __( 'Why it ended. Empty while it runs.', 'flx-commerce' ),
			),
			'hasAccess'      => array(
				'type'        => 'Boolean!',
				'description' => __( 'The customer still has access.', 'flx-commerce' ),
			),
			'id'             => array(
				'type'        => 'ID!',
				'description' => __( 'Subscription id.', 'flx-commerce' ),
			),
			'lastOrderId'    => array(
				'type'        => 'Int!',
				'description' => __( 'Latest order id.', 'flx-commerce' ),
			),
			'lastOrderUrl'   => array(
				'type'        => 'String!',
				'description' => __( 'Order screen for a shop manager, or the customer view for the owner. Empty otherwise.', 'flx-commerce' ),
			),
			'name'           => array(
				'type'        => 'String!',
				'description' => __( 'Customer name.', 'flx-commerce' ),
			),
			'nextChargeAt'   => $when,
			'parentOrderId'  => array(
				'type'        => 'Int!',
				'description' => __( 'First order id.', 'flx-commerce' ),
			),
			'payUrl'         => array(
				'type'        => 'String!',
				'description' => __( 'Payment link while a renewal is unpaid and not set to charge a card. Empty otherwise.', 'flx-commerce' ),
			),
			'paymentMethod'  => array(
				'type'        => 'String!',
				'description' => __( 'Payment method id.', 'flx-commerce' ),
			),
			'period'         => array(
				'type'        => 'String!',
				'description' => __( 'month or year.', 'flx-commerce' ),
			),
			'periodEnd'      => $when,
			'productId'      => array(
				'type'        => 'Int!',
				'description' => __( 'Product id.', 'flx-commerce' ),
			),
			'productName'    => array(
				'type'        => 'String!',
				'description' => __( 'Product name.', 'flx-commerce' ),
			),
			'productUrl'     => array(
				'type'        => 'String!',
				'description' => __( 'Public product address when the product is published. Empty otherwise.', 'flx-commerce' ),
			),
			'renewalOrderId' => array(
				'type'        => 'Int!',
				'description' => __( 'Open renewal order id. 0 when there is none.', 'flx-commerce' ),
			),
			'renewNow'       => array(
				'type'        => 'Boolean!',
				'description' => __( 'The renew-now window on the account page is open.', 'flx-commerce' ),
			),
			'renewals'       => array(
				'type'        => 'Int!',
				'description' => __( 'Renewals already paid.', 'flx-commerce' ),
			),
			'startedAt'      => $when,
			'status'         => array(
				'type'        => 'String!',
				'description' => __( 'pending, active, past_due, cancelled or expired.', 'flx-commerce' ),
			),
			'userId'         => array(
				'type'        => 'Int!',
				'description' => __( 'WordPress user id. 0 for a guest.', 'flx-commerce' ),
			),
		),
	)
);
flx_gql_connection( 'FlxCommerceSubscription', __( 'A page of subscriptions.', 'flx-commerce' ) );

flx_gql_type(
	'FlxCommerceSubscriptionCounts',
	array(
		'description' => __( 'Counts behind the subscription filter chips.', 'flx-commerce' ),
		'fields'      => array(
			'active'    => array(
				'type'        => 'Int!',
				'description' => __( 'Active.', 'flx-commerce' ),
			),
			'all'       => array(
				'type'        => 'Int!',
				'description' => __( 'All statuses.', 'flx-commerce' ),
			),
			'cancelled' => array(
				'type'        => 'Int!',
				'description' => __( 'Renewal off, period still open.', 'flx-commerce' ),
			),
			'due'       => array(
				'type'        => 'Int!',
				'description' => __( 'Active subscriptions renewing within 30 days.', 'flx-commerce' ),
			),
			'expired'   => array(
				'type'        => 'Int!',
				'description' => __( 'Ended.', 'flx-commerce' ),
			),
			'mrr'       => $money,
			'pastDue'   => array(
				'type'        => 'Int!',
				'description' => __( 'In the grace period.', 'flx-commerce' ),
			),
			'pending'   => array(
				'type'        => 'Int!',
				'description' => __( 'Waiting for the first payment.', 'flx-commerce' ),
			),
		),
	)
);

flx_gql_type(
	'FlxCommerceReport',
	array(
		'description' => __( 'Sales report totals. Daily rows and breakdowns are separate connections of at most 50.', 'flx-commerce' ),
		'fields'      => array(
			'aov'          => $money,
			'aovDelta'     => array(
				'type'        => 'Float',
				'description' => __( 'Percent change in average order value.', 'flx-commerce' ),
			),
			'customers'    => array(
				'type'        => 'Int!',
				'description' => __( 'Customers in the range.', 'flx-commerce' ),
			),
			'discount'     => $money,
			'from'         => array(
				'type'        => 'String!',
				'description' => __( 'First day.', 'flx-commerce' ),
			),
			'gross'        => $money,
			'items'        => array(
				'type'        => 'Int!',
				'description' => __( 'Units sold.', 'flx-commerce' ),
			),
			'itemsDelta'   => array(
				'type'        => 'Float',
				'description' => __( 'Percent change in units.', 'flx-commerce' ),
			),
			'newCustomers' => array(
				'type'        => 'Int!',
				'description' => __( 'New customers.', 'flx-commerce' ),
			),
			'orders'       => array(
				'type'        => 'Int!',
				'description' => __( 'Paid orders.', 'flx-commerce' ),
			),
			'ordersDelta'  => array(
				'type'        => 'Float',
				'description' => __( 'Percent change in orders.', 'flx-commerce' ),
			),
			'refunds'      => $money,
			'revenue'      => $money,
			'revenueDelta' => array(
				'type'        => 'Float',
				'description' => __( 'Percent change in net revenue.', 'flx-commerce' ),
			),
			'shipping'     => $money,
			'tax'          => $money,
			'to'           => array(
				'type'        => 'String!',
				'description' => __( 'Last day.', 'flx-commerce' ),
			),
		),
	)
);

flx_gql_type(
	'FlxCommerceOrderStatus',
	array(
		'description' => __( 'An order status the settings screen can mark as paid.', 'flx-commerce' ),
		'fields'      => array(
			'label' => array(
				'type'        => 'String!',
				'description' => __( 'Status name.', 'flx-commerce' ),
			),
			'slug'  => array(
				'type'        => 'String!',
				'description' => __( 'Status slug without the wc- prefix.', 'flx-commerce' ),
			),
		),
	)
);

flx_gql_type(
	'FlxCommerceSettings',
	array(
		'description' => __( 'Store settings. feedUrl is the full feed address the classic screen shows to a shop manager.', 'flx-commerce' ),
		'fields'      => array(
			'cardProviders'    => array(
				'type'        => '[String!]!',
				'description' => __( 'Names of card providers that can renew automatically. Empty when none is installed.', 'flx-commerce' ),
			),
			'cancelAfterDays'  => array(
				'type'        => 'Int!',
				'description' => __( 'Days before an unpaid bank transfer is cancelled. 0 keeps it.', 'flx-commerce' ),
			),
			'deferToDonate'    => array(
				'type'        => 'Boolean!',
				'description' => __( 'Leave donation bank transfers to Flux Donate.', 'flx-commerce' ),
			),
			'donateCart'       => array(
				'type'        => 'Boolean!',
				'description' => __( 'Flux Donate cart is installed, so the donation exclusion can be shown.', 'flx-commerce' ),
			),
			'donateHavale'     => array(
				'type'        => 'Boolean!',
				'description' => __( 'Flux Donate bank transfer is installed, so the defer option can be shown.', 'flx-commerce' ),
			),
			'excludeDonations' => array(
				'type'        => 'Boolean!',
				'description' => __( 'Leave donation orders out of store figures.', 'flx-commerce' ),
			),
			'feed'             => array(
				'type'        => 'Boolean!',
				'description' => __( 'Product feed is enabled.', 'flx-commerce' ),
			),
			'feedBrand'        => array(
				'type'        => 'String!',
				'description' => __( 'Brand written on the feed.', 'flx-commerce' ),
			),
			'feedUrl'          => array(
				'type'        => 'String!',
				'description' => __( 'Full product feed address, the same value the classic settings screen shows.', 'flx-commerce' ),
			),
			'freeShipAmount'   => array(
				'type'        => 'Float!',
				'description' => __( 'Free-shipping threshold. 0 uses the shipping zone.', 'flx-commerce' ),
			),
			'freeShipBar'      => array(
				'type'        => 'Boolean!',
				'description' => __( 'Show the free-shipping bar.', 'flx-commerce' ),
			),
			'freeShipThreshold' => $money,
			'hiddenRedirect'   => array(
				'type'        => 'String!',
				'description' => __( 'Where a hidden product sends a visitor. Empty uses the home page.', 'flx-commerce' ),
			),
			'hideAddress2'     => array(
				'type'        => 'Boolean!',
				'description' => __( 'Hide the second address line at checkout.', 'flx-commerce' ),
			),
			'hideCompany'      => array(
				'type'        => 'Boolean!',
				'description' => __( 'Hide the company field at checkout.', 'flx-commerce' ),
			),
			'lowStock'         => array(
				'type'        => 'Int!',
				'description' => __( 'Low-stock threshold.', 'flx-commerce' ),
			),
			'notifyAdminOrder' => array(
				'type'        => 'Boolean!',
				'description' => __( 'WhatsApp the admin for every new paid order.', 'flx-commerce' ),
			),
			'notifyAdminPhone' => array(
				'type'        => 'String!',
				'description' => __( 'Admin WhatsApp number.', 'flx-commerce' ),
			),
			'notifyCustomerWa' => array(
				'type'        => 'Boolean!',
				'description' => __( 'WhatsApp the customer when an order is paid.', 'flx-commerce' ),
			),
			'orderNotes'       => array(
				'type'        => 'Boolean!',
				'description' => __( 'Show the order-notes field.', 'flx-commerce' ),
			),
			'orderStatuses'    => array(
				'type'        => '[FlxCommerceOrderStatus!]!',
				'description' => __( 'Statuses the classic screen offers as paid. Pending, cancelled, failed, checkout-draft and refunded are left out.', 'flx-commerce' ),
			),
			'paidStatuses'     => array(
				'type'        => '[String!]!',
				'description' => __( 'Order statuses that count as paid.', 'flx-commerce' ),
			),
			'receiptMaxMb'     => array(
				'type'        => 'Int!',
				'description' => __( 'Largest receipt upload in megabytes.', 'flx-commerce' ),
			),
			'receiptNotify'    => array(
				'type'        => 'String!',
				'description' => __( 'E-mail that hears about a new receipt.', 'flx-commerce' ),
			),
			'stickyPay'        => array(
				'type'        => 'Boolean!',
				'description' => __( 'Keep the pay button on screen.', 'flx-commerce' ),
			),
			'subGraceDays'     => array(
				'type'        => 'Int!',
				'description' => __( 'Extra days after a missed renewal.', 'flx-commerce' ),
			),
			'subRenewDays'     => array(
				'type'        => 'Int!',
				'description' => __( 'Days before the period ends that the renewal order opens.', 'flx-commerce' ),
			),
			'whatsappReady'    => array(
				'type'        => 'Boolean!',
				'description' => __( 'Flux Mail has a WhatsApp connection, so WhatsApp notices can be sent.', 'flx-commerce' ),
			),
			'virtualNoAddress' => array(
				'type'        => 'Boolean!',
				'description' => __( 'Skip the address when every product is virtual.', 'flx-commerce' ),
			),
		),
	)
);

flx_gql_type(
	'FlxCommerceBankSettings',
	array(
		'description' => __( 'Bank transfer gateway. The IBAN is the one customers are shown at checkout.', 'flx-commerce' ),
		'fields'      => array(
			'bank'        => array(
				'type'        => 'String!',
				'description' => __( 'Bank name.', 'flx-commerce' ),
			),
			'description' => array(
				'type'        => 'String!',
				'description' => __( 'Text on the checkout page.', 'flx-commerce' ),
			),
			'enabled'     => array(
				'type'        => 'Boolean!',
				'description' => __( 'The gateway is enabled.', 'flx-commerce' ),
			),
			'holder'      => array(
				'type'        => 'String!',
				'description' => __( 'Account holder.', 'flx-commerce' ),
			),
			'iban'        => array(
				'type'        => 'String!',
				'description' => __( 'IBAN shown to the customer.', 'flx-commerce' ),
			),
			'refFormat'   => array(
				'type'        => 'String!',
				'description' => __( 'Reference format.', 'flx-commerce' ),
			),
			'refPrefix'   => array(
				'type'        => 'String!',
				'description' => __( 'Reference prefix.', 'flx-commerce' ),
			),
			'title'       => array(
				'type'        => 'String!',
				'description' => __( 'Title on the checkout page.', 'flx-commerce' ),
			),
		),
	)
);

flx_gql_type(
	'FlxCommerceRenewalStatus',
	array(
		'description' => __( 'When the renewal tick last ran.', 'flx-commerce' ),
		'fields'      => array(
			'at'  => $when,
			'ran' => array(
				'type'        => 'Boolean!',
				'description' => __( 'The tick has run at least once.', 'flx-commerce' ),
			),
		),
	)
);

flx_gql_type(
	'FlxCommerceOffer',
	array(
		'description' => __( 'Monthly and yearly prices of a subscription product.', 'flx-commerce' ),
		'fields'      => array(
			'month'     => array(
				'type'        => 'FlxMoney',
				'description' => __( 'Monthly price. Empty when that period is not offered.', 'flx-commerce' ),
			),
			'name'      => array(
				'type'        => 'String!',
				'description' => __( 'Product name.', 'flx-commerce' ),
			),
			'productId' => array(
				'type'        => 'Int!',
				'description' => __( 'Product id.', 'flx-commerce' ),
			),
			'year'      => array(
				'type'        => 'FlxMoney',
				'description' => __( 'Yearly price. Empty when that period is not offered.', 'flx-commerce' ),
			),
		),
	)
);

flx_gql_type(
	'FlxCommerceCartSubscription',
	array(
		'description' => __( 'Subscription period on a cart line. No card data.', 'flx-commerce' ),
		'fields'      => array(
			'amount' => array(
				'type'        => 'FlxMoney',
				'description' => __( 'Period price. Empty when the product is gone.', 'flx-commerce' ),
			),
			'period' => array(
				'type'        => 'String!',
				'description' => __( 'month or year.', 'flx-commerce' ),
			),
		),
	)
);

flx_gql_type(
	'FlxCommerceFreeShipping',
	array(
		'description' => __( 'Free-shipping bar for the current cart.', 'flx-commerce' ),
		'fields'      => array(
			'enabled'   => array(
				'type'        => 'Boolean!',
				'description' => __( 'The bar is on and a threshold exists.', 'flx-commerce' ),
			),
			'reached'   => array(
				'type'        => 'Boolean!',
				'description' => __( 'The cart has reached the threshold.', 'flx-commerce' ),
			),
			'remaining' => $money,
			'threshold' => $money,
		),
	)
);

/**
 * @param array<string,mixed> $args Query args.
 * @return array{0:string,1:string}
 */
function flxcm_gql_range( array $args ): array {
	return FLXCM_Store_Service::range( FLXCM_Val::str(( $args['preset'] ?? '' )), FLXCM_Val::str(( $args['from'] ?? '' )), FLXCM_Val::str(( $args['to'] ?? '' )) );
}

$range_args = array(
	'preset' => array(
		'type'        => 'FlxCommerceReportPreset',
		'description' => __( 'A named range. from and to override it when both are set and preset is omitted.', 'flx-commerce' ),
	),
	'from'   => array(
		'type'        => 'String',
		'description' => __( 'First day, Y-m-d.', 'flx-commerce' ),
	),
	'to'     => array(
		'type'        => 'String',
		'description' => __( 'Last day, Y-m-d.', 'flx-commerce' ),
	),
);

flx_gql_query(
	'flxCommerceSummary',
	array(
		'type'        => 'FlxCommerceSummary!',
		'cap'         => flxcm_gql_view(),
		'description' => __( 'Store overview. days is 1, 7, 30 or 90, matching the classic chips.', 'flx-commerce' ),
		'args'        => array(
			'days' => array(
				'type'        => 'Int',
				'description' => __( 'Range length. Clamped to 1-90.', 'flx-commerce' ),
			),
		),
		'resolve'     => static function ( $root, $args ) {
			$args = FLXCM_Val::map( $args );
			unset( $root );
			flxcm_gql_tag( 'flx-commerce:summary' );
			$days = FLXCM_Val::int(( $args['days'] ?? 30 ));
			return FLXCM_Store_Service::summary_days( min( 90, max( 1, $days ) ) );
		},
	)
);

flx_gql_query(
	'flxCommerceCustomers',
	array(
		'type'        => 'FlxCommerceCustomerConnection!',
		'cap'         => flxcm_gql_view(),
		'description' => __( 'Customers. At most 50 per page.', 'flx-commerce' ),
		'args'        => flx_gql_connection_args(
			array(
				'search'  => array(
					'type'        => 'String',
					'description' => __( 'Name, e-mail, phone or city.', 'flx-commerce' ),
				),
				'segment' => array(
					'type'        => 'FlxCommerceSegment',
					'description' => __( 'Filter chip.', 'flx-commerce' ),
				),
				'orderby' => array(
					'type'        => 'FlxCommerceCustomerSort',
					'description' => __( 'Sort column.', 'flx-commerce' ),
				),
				'order'   => array(
					'type'        => 'FlxCommerceSortDirection',
					'description' => __( 'Sort direction.', 'flx-commerce' ),
				),
			)
		),
		'resolve'     => static function ( $root, $args ) {
			$args = FLXCM_Val::map( $args );
			unset( $root );
			flxcm_gql_tag( 'flx-commerce:customers' );
			return flx_gql_paginate(
				$args,
				static function ( int $offset, int $limit ) use ( $args ) {
					return FLXCM_Customer_Service::page( $args, FLXCM_Val::int($offset), FLXCM_Val::int($limit) );
				}
			);
		},
	)
);

flx_gql_query(
	'flxCommerceCustomer',
	array(
		'type'        => 'FlxCommerceCustomer',
		'cap'         => flxcm_gql_view(),
		'description' => __( 'One customer. Empty when the id is unknown.', 'flx-commerce' ),
		'args'        => array(
			'id' => array(
				'type'        => 'ID!',
				'description' => __( 'Customer id.', 'flx-commerce' ),
			),
		),
		'resolve'     => static function ( $root, $args ) {
			$args = FLXCM_Val::map( $args );
			unset( $root );
			$row = FLXCM_Customers::get( FLXCM_Val::int($args['id']) );
			if ( ! is_array( $row ) ) {
				return null;
			}
			flxcm_gql_tag( 'flx-commerce:customer:' . FLXCM_Val::int($row['id']) );
			return FLXCM_Customer_Service::map( $row );
		},
	)
);

flx_gql_query(
	'flxCommerceCustomerCounts',
	array(
		'type'        => 'FlxCommerceCustomerCounts!',
		'cap'         => flxcm_gql_view(),
		'description' => __( 'Customer filter counts.', 'flx-commerce' ),
		'resolve'     => static function () {
			flxcm_gql_tag( 'flx-commerce:customers' );
			$c = FLXCM_Customers::counts();
			$built = FLXCM_Val::int(get_option( 'flxcm_customers_built' ));
			return array(
				'all'     => FLXCM_Val::int($c['all']),
				'repeat'  => FLXCM_Val::int($c['repeat']),
				'vip'     => FLXCM_Val::int($c['vip']),
				'lapsed'  => FLXCM_Val::int($c['lapsed']),
				'new'     => FLXCM_Val::int($c['new']),
				'spent'   => flxcm_gql_money( $c['spent'] ),
				'ltv'     => flxcm_gql_money( $c['ltv'] ),
				'built'   => $built > 0,
				'builtAt' => $built > 0 ? flxcm_gql_time( $built ) : null,
			);
		},
	)
);

flx_gql_query(
	'flxCommerceReceipts',
	array(
		'type'        => 'FlxCommerceReceiptConnection!',
		'cap'         => flxcm_gql_approve(),
		'description' => __( 'Bank transfers waiting for approval.', 'flx-commerce' ),
		'args'        => flx_gql_connection_args(),
		'resolve'     => static function ( $root, $args ) {
			$args = FLXCM_Val::map( $args );
			unset( $root );
			flxcm_gql_tag( 'flx-commerce:receipts' );
			return flx_gql_paginate(
				$args,
				static function ( int $offset, int $limit ) {
					return FLXCM_Receipt_Service::page( FLXCM_Val::int($offset), FLXCM_Val::int($limit) );
				}
			);
		},
	)
);

flx_gql_query(
	'flxCommerceReceipt',
	array(
		'type'        => 'FlxCommerceReceipt',
		'cap'         => flxcm_gql_approve(),
		'description' => __( 'One bank transfer. Empty when it is not a bank-transfer order.', 'flx-commerce' ),
		'args'        => array(
			'id' => array(
				'type'        => 'ID!',
				'description' => __( 'Order id.', 'flx-commerce' ),
			),
		),
		'resolve'     => static function ( $root, $args ) {
			$args = FLXCM_Val::map( $args );
			unset( $root );
			flxcm_gql_tag( 'flx-commerce:receipts' );
			if ( ! function_exists( 'wc_get_order' ) ) {
				return null;
			}
			$order = flxcm_order( $args['id'] );
			if ( ! $order || FLXCM_Bank::ID !== $order->get_payment_method() ) {
				return null;
			}
			return FLXCM_Receipt_Service::map( $order );
		},
	)
);

flx_gql_query(
	'flxCommerceSubscriptions',
	array(
		'type'        => 'FlxCommerceSubscriptionConnection!',
		'cap'         => flxcm_gql_view(),
		'description' => __( 'Subscriptions. At most 50 per page.', 'flx-commerce' ),
		'args'        => flx_gql_connection_args(
			array(
				'search' => array(
					'type'        => 'String',
					'description' => __( 'Name, e-mail, product or id.', 'flx-commerce' ),
				),
				'status' => array(
					'type'        => 'FlxCommerceSubscriptionStatus',
					'description' => __( 'Status chip. Empty means every status.', 'flx-commerce' ),
				),
			)
		),
		'resolve'     => static function ( $root, $args ) {
			$args = FLXCM_Val::map( $args );
			unset( $root );
			flxcm_gql_tag( 'flx-commerce:subscriptions' );
			return flx_gql_paginate(
				$args,
				static function ( int $offset, int $limit ) use ( $args ) {
					return FLXCM_Subscription_Service::page( $args, FLXCM_Val::int($offset), FLXCM_Val::int($limit) );
				}
			);
		},
	)
);

flx_gql_query(
	'flxCommerceSubscription',
	array(
		'type'        => 'FlxCommerceSubscription',
		'cap'         => flxcm_gql_view(),
		'description' => __( 'One subscription. Empty when the id is unknown.', 'flx-commerce' ),
		'args'        => array(
			'id' => array(
				'type'        => 'ID!',
				'description' => __( 'Subscription id.', 'flx-commerce' ),
			),
		),
		'resolve'     => static function ( $root, $args ) {
			$args = FLXCM_Val::map( $args );
			unset( $root );
			$row = FLXCM_Subscriptions::export( FLXCM_Val::int($args['id']) );
			if ( ! is_array( $row ) ) {
				return null;
			}
			flxcm_gql_tag( 'flx-commerce:subscription:' . FLXCM_Val::int($row['id']) );
			return FLXCM_Subscription_Service::map( $row );
		},
	)
);

flx_gql_query(
	'flxCommerceSubscriptionCounts',
	array(
		'type'        => 'FlxCommerceSubscriptionCounts!',
		'cap'         => flxcm_gql_view(),
		'description' => __( 'Subscription filter counts and monthly recurring revenue.', 'flx-commerce' ),
		'resolve'     => static function () {
			flxcm_gql_tag( 'flx-commerce:subscriptions' );
			return FLXCM_Subscription_Service::counts();
		},
	)
);

flx_gql_query(
	'flxCommerceReport',
	array(
		'type'        => 'FlxCommerceReport!',
		'cap'         => flxcm_gql_view(),
		'description' => __( 'Sales report totals for a preset or a from/to range.', 'flx-commerce' ),
		'args'        => $range_args,
		'resolve'     => static function ( $root, $args ) {
			$args = FLXCM_Val::map( $args );
			unset( $root );
			flxcm_gql_tag( 'flx-commerce:summary' );
			list( $from, $to ) = flxcm_gql_range( $args );
			return FLXCM_Store_Service::summary_between( $from, $to, true );
		},
	)
);

/**
 * @param string              $key  series, products, categories or methods.
 * @param array<string,mixed> $args Query args.
 * @return array<string,mixed>
 */
function flxcm_gql_report_page( string $key, array $args ): array {
	flxcm_gql_tag( 'flx-commerce:summary' );
	list( $from, $to ) = flxcm_gql_range( $args );
	return flx_gql_paginate(
		$args,
		static function ( int $offset, int $limit ) use ( $key, $from, $to ) {
			$pack = FLXCM_Store_Service::summary_between( $from, $to, false );
			$rows = FLXCM_Val::arr( $pack[ $key ] ?? array() );
			return FLXCM_Store_Service::slice( $rows, FLXCM_Val::int($offset), FLXCM_Val::int($limit) );
		}
	);
}

flx_gql_query(
	'flxCommerceReportDays',
	array(
		'type'        => 'FlxCommerceDayConnection!',
		'cap'         => flxcm_gql_view(),
		'description' => __( 'Daily revenue. Pages of at most 50, so a year is several pages.', 'flx-commerce' ),
		'args'        => flx_gql_connection_args( $range_args ),
		'resolve'     => static function ( $root, $args ) {
			$args = FLXCM_Val::map( $args );
			unset( $root );
			return flxcm_gql_report_page( 'series', $args );
		},
	)
);
flx_gql_query(
	'flxCommerceReportProducts',
	array(
		'type'        => 'FlxCommerceProductStatConnection!',
		'cap'         => flxcm_gql_view(),
		'description' => __( 'Products in the report.', 'flx-commerce' ),
		'args'        => flx_gql_connection_args( $range_args ),
		'resolve'     => static function ( $root, $args ) {
			$args = FLXCM_Val::map( $args );
			unset( $root );
			return flxcm_gql_report_page( 'products', $args );
		},
	)
);
flx_gql_query(
	'flxCommerceReportCategories',
	array(
		'type'        => 'FlxCommerceCategoryStatConnection!',
		'cap'         => flxcm_gql_view(),
		'description' => __( 'Categories in the report.', 'flx-commerce' ),
		'args'        => flx_gql_connection_args( $range_args ),
		'resolve'     => static function ( $root, $args ) {
			$args = FLXCM_Val::map( $args );
			unset( $root );
			return flxcm_gql_report_page( 'categories', $args );
		},
	)
);
flx_gql_query(
	'flxCommerceReportMethods',
	array(
		'type'        => 'FlxCommerceMethodStatConnection!',
		'cap'         => flxcm_gql_view(),
		'description' => __( 'Payment methods in the report.', 'flx-commerce' ),
		'args'        => flx_gql_connection_args( $range_args ),
		'resolve'     => static function ( $root, $args ) {
			$args = FLXCM_Val::map( $args );
			unset( $root );
			return flxcm_gql_report_page( 'methods', $args );
		},
	)
);

flx_gql_query(
	'flxCommerceSettings',
	array(
		'type'        => 'FlxCommerceSettings!',
		'cap'         => 'manage_woocommerce',
		'description' => __( 'Store settings, including the full feed address. Shop managers only.', 'flx-commerce' ),
		'resolve'     => static function () {
			flxcm_gql_tag( 'flx-commerce:settings' );
			return FLXCM_Settings_Service::read();
		},
	)
);
flx_gql_query(
	'flxCommerceBankSettings',
	array(
		'type'        => 'FlxCommerceBankSettings!',
		'cap'         => 'manage_woocommerce',
		'description' => __( 'Bank transfer gateway settings.', 'flx-commerce' ),
		'resolve'     => static function () {
			flxcm_gql_tag( 'flx-commerce:settings' );
			return FLXCM_Settings_Service::bank_read();
		},
	)
);
flx_gql_query(
	'flxCommerceRenewalStatus',
	array(
		'type'        => 'FlxCommerceRenewalStatus!',
		'cap'         => flxcm_gql_view(),
		'description' => __( 'When the renewal tick last finished a pass.', 'flx-commerce' ),
		'resolve'     => static function () {
			flxcm_gql_tag( 'flx-commerce:subscriptions' );
			return FLXCM_Subscription_Service::renewal_status();
		},
	)
);
flx_gql_query(
	'flxMySubscriptions',
	array(
		'type'        => '[FlxCommerceSubscription!]!',
		'cap'         => 'read',
		'description' => __( 'Subscriptions of the signed-in customer. The account page reads this same list.', 'flx-commerce' ),
		'resolve'     => static function () {
			return FLXCM_Subscription_Service::mine( get_current_user_id() );
		},
	)
);
flx_gql_query(
	'flxCommerceSubscriptionOffer',
	array(
		'type'        => 'FlxCommerceOffer',
		'cap'         => 'public',
		'description' => __( 'Public subscription prices for a product. There is no Product type in this schema, so the storefront reads this query. Empty when the product is not a subscription.', 'flx-commerce' ),
		'args'        => array(
			'productId' => array(
				'type'        => 'Int!',
				'description' => __( 'Product id.', 'flx-commerce' ),
			),
		),
		'resolve'     => static function ( $root, $args ) {
			$args = FLXCM_Val::map( $args );
			unset( $root );
			$offer = FLXCM_Cart_Service::offer( FLXCM_Val::int($args['productId']) );
			if ( is_wp_error( $offer ) ) {
				return null;
			}
			return $offer;
		},
	)
);

$sub_out = array(
	'subscription' => array(
		'type'        => 'FlxCommerceSubscription',
		'description' => __( 'The subscription after the change.', 'flx-commerce' ),
	),
);
$acct_out = $sub_out + array(
	'notice' => array(
		'type'        => 'String!',
		'description' => __( 'The same sentence the account page shows.', 'flx-commerce' ),
	),
	'payUrl' => array(
		'type'        => 'String',
		'description' => __( 'Where the customer pays a renewal. Empty unless this was a renew.', 'flx-commerce' ),
	),
);

/**
 * @param string $do cancel, end, resume or renewal.
 * @return Closure
 */
function flxcm_gql_sub_change( string $do ): Closure {
	return static function ( $input ) use ( $do ) {
		$input  = FLXCM_Val::map( $input );
		$result = FLXCM_Subscription_Service::change( FLXCM_Val::int(( $input['id'] ?? 0 )), $do );
		return flxcm_gql_throw( $result );
	};
}

flx_gql_mutation(
	'flxCommerceReceiptApprove',
	array(
		'cap'         => flxcm_gql_approve(),
		'description' => __( 'Marks a bank transfer paid. CONFLICT when the order is not waiting.', 'flx-commerce' ),
		'input'       => array(
			'id' => array(
				'type'        => 'ID!',
				'description' => __( 'Order id.', 'flx-commerce' ),
			),
		),
		'output'      => array(
			'receipt' => array(
				'type'        => 'FlxCommerceReceipt',
				'description' => __( 'The order after approval.', 'flx-commerce' ),
			),
		),
		'resolve'     => static function ( $input ) {
			$input = FLXCM_Val::map( $input );
			$result = FLXCM_Receipt_Service::approve( FLXCM_Val::int(( $input['id'] ?? 0 )) );
			$result = flxcm_gql_throw( $result );
			$order  = flxcm_order( $input['id'] ?? 0 );
			return array( 'receipt' => $order ? FLXCM_Receipt_Service::map( $order ) : null );
		},
	)
);

flx_gql_mutation(
	'flxCommerceCustomerNote',
	array(
		'cap'         => 'manage_woocommerce',
		'description' => __( 'Saves the shop note. NOT_FOUND when the customer is missing. INVALID_INPUT when the note is over 2000 characters.', 'flx-commerce' ),
		'input'       => array(
			'id'   => array(
				'type'        => 'ID!',
				'description' => __( 'Customer id.', 'flx-commerce' ),
			),
			'note' => array(
				'type'        => 'String!',
				'description' => __( 'Note. Empty clears it.', 'flx-commerce' ),
			),
		),
		'output'      => array(
			'customer' => array(
				'type'        => 'FlxCommerceCustomer',
				'description' => __( 'The customer after the note is saved.', 'flx-commerce' ),
			),
		),
		'resolve'     => static function ( $input ) {
			$input = FLXCM_Val::map( $input );
			$result = FLXCM_Customer_Service::save_note( FLXCM_Val::int(( $input['id'] ?? 0 )), FLXCM_Val::str(( $input['note'] ?? '' )), true );
			flxcm_gql_throw( $result );
			$row = FLXCM_Customers::get( FLXCM_Val::int(( $input['id'] ?? 0 )) );
			return array( 'customer' => is_array( $row ) ? FLXCM_Customer_Service::map( $row ) : null );
		},
	)
);

flx_gql_mutation(
	'flxCommerceCustomersRebuild',
	array(
		'cap'         => 'manage_woocommerce',
		'description' => __( 'Rebuilds one page of the customer summary (100 orders). Call again with page + 1 until done.', 'flx-commerce' ),
		'input'       => array(
			'page' => array(
				'type'        => 'Int',
				'description' => __( 'Page, starting at 1.', 'flx-commerce' ),
			),
		),
		'output'      => array(
			'done'    => array(
				'type'        => 'Boolean',
				'description' => __( 'No pages remain.', 'flx-commerce' ),
			),
			'page'    => array(
				'type'        => 'Int',
				'description' => __( 'Page that just ran.', 'flx-commerce' ),
			),
			'scanned' => array(
				'type'        => 'Int',
				'description' => __( 'Orders scanned so far.', 'flx-commerce' ),
			),
			'total'   => array(
				'type'        => 'Int',
				'description' => __( 'Orders to scan.', 'flx-commerce' ),
			),
		),
		'resolve'     => static function ( $input ) {
			$input = FLXCM_Val::map( $input );
			$result = FLXCM_Customer_Service::rebuild_step( max( 1, FLXCM_Val::int(( $input['page'] ?? 1 )) ) );
			return flxcm_gql_throw( $result );
		},
	)
);

flx_gql_mutation(
	'flxCommerceSubscriptionRenew',
	array(
		'cap'         => 'manage_woocommerce',
		'description' => __( 'Opens a renewal order now.', 'flx-commerce' ),
		'input'       => array(
			'id' => array(
				'type'        => 'ID!',
				'description' => __( 'Subscription id.', 'flx-commerce' ),
			),
		),
		'output'      => $sub_out,
		'resolve'     => flxcm_gql_sub_change( 'renewal' ),
	)
);
flx_gql_mutation(
	'flxCommerceSubscriptionCancel',
	array(
		'cap'         => 'manage_woocommerce',
		'description' => __( 'Turns renewal off. Access lasts until the period ends.', 'flx-commerce' ),
		'input'       => array(
			'id' => array(
				'type'        => 'ID!',
				'description' => __( 'Subscription id.', 'flx-commerce' ),
			),
		),
		'output'      => $sub_out,
		'resolve'     => flxcm_gql_sub_change( 'cancel' ),
	)
);
flx_gql_mutation(
	'flxCommerceSubscriptionEnd',
	array(
		'cap'         => 'manage_woocommerce',
		'description' => __( 'Ends the subscription now.', 'flx-commerce' ),
		'input'       => array(
			'id' => array(
				'type'        => 'ID!',
				'description' => __( 'Subscription id.', 'flx-commerce' ),
			),
		),
		'output'      => $sub_out,
		'resolve'     => flxcm_gql_sub_change( 'end' ),
	)
);
flx_gql_mutation(
	'flxCommerceSubscriptionResume',
	array(
		'cap'         => 'manage_woocommerce',
		'description' => __( 'Turns renewal back on.', 'flx-commerce' ),
		'input'       => array(
			'id' => array(
				'type'        => 'ID!',
				'description' => __( 'Subscription id.', 'flx-commerce' ),
			),
		),
		'output'      => $sub_out,
		'resolve'     => flxcm_gql_sub_change( 'resume' ),
	)
);

$settings_input = array(
	'cancelAfterDays'  => array( 'type' => 'Int', 'description' => __( 'Days before cancelling an unpaid transfer.', 'flx-commerce' ) ),
	'deferToDonate'    => array( 'type' => 'Boolean', 'description' => __( 'Leave donation transfers to Flux Donate.', 'flx-commerce' ) ),
	'excludeDonations' => array( 'type' => 'Boolean', 'description' => __( 'Leave donations out of store figures.', 'flx-commerce' ) ),
	'feed'             => array( 'type' => 'Boolean', 'description' => __( 'Enable the product feed.', 'flx-commerce' ) ),
	'feedBrand'        => array( 'type' => 'String', 'description' => __( 'Brand on the feed.', 'flx-commerce' ) ),
	'freeShipAmount'   => array( 'type' => 'Float', 'description' => __( 'Free-shipping threshold.', 'flx-commerce' ) ),
	'freeShipBar'      => array( 'type' => 'Boolean', 'description' => __( 'Show the free-shipping bar.', 'flx-commerce' ) ),
	'hiddenRedirect'   => array( 'type' => 'String', 'description' => __( 'Hidden-product redirect.', 'flx-commerce' ) ),
	'hideAddress2'     => array( 'type' => 'Boolean', 'description' => __( 'Hide the second address line.', 'flx-commerce' ) ),
	'hideCompany'      => array( 'type' => 'Boolean', 'description' => __( 'Hide the company field.', 'flx-commerce' ) ),
	'lowStock'         => array( 'type' => 'Int', 'description' => __( 'Low-stock threshold.', 'flx-commerce' ) ),
	'notifyAdminOrder' => array( 'type' => 'Boolean', 'description' => __( 'WhatsApp the admin for paid orders.', 'flx-commerce' ) ),
	'notifyAdminPhone' => array( 'type' => 'String', 'description' => __( 'Admin WhatsApp number.', 'flx-commerce' ) ),
	'notifyCustomerWa' => array( 'type' => 'Boolean', 'description' => __( 'WhatsApp the customer.', 'flx-commerce' ) ),
	'orderNotes'       => array( 'type' => 'Boolean', 'description' => __( 'Show order notes.', 'flx-commerce' ) ),
	'paidStatuses'     => array( 'type' => '[String!]', 'description' => __( 'Statuses that count as paid.', 'flx-commerce' ) ),
	'receiptMaxMb'     => array( 'type' => 'Int', 'description' => __( 'Receipt size limit in megabytes.', 'flx-commerce' ) ),
	'receiptNotify'    => array( 'type' => 'String', 'description' => __( 'Receipt notification e-mail.', 'flx-commerce' ) ),
	'stickyPay'        => array( 'type' => 'Boolean', 'description' => __( 'Sticky pay button.', 'flx-commerce' ) ),
	'subGraceDays'     => array( 'type' => 'Int', 'description' => __( 'Grace days.', 'flx-commerce' ) ),
	'subRenewDays'     => array( 'type' => 'Int', 'description' => __( 'Days before renewal.', 'flx-commerce' ) ),
	'virtualNoAddress' => array( 'type' => 'Boolean', 'description' => __( 'Skip the address for virtual carts.', 'flx-commerce' ) ),
);

flx_gql_mutation(
	'flxCommerceSettingsSave',
	array(
		'cap'         => 'manage_woocommerce',
		'description' => __( 'Saves store settings. Fields you leave out stay as they are. The feed key is generated, not written here.', 'flx-commerce' ),
		'input'       => $settings_input,
		'output'      => array(
			'settings' => array(
				'type'        => 'FlxCommerceSettings',
				'description' => __( 'Settings after the save.', 'flx-commerce' ),
			),
		),
		'resolve'     => static function ( $input ) {
			$input = FLXCM_Val::map( $input );
			return array( 'settings' => FLXCM_Settings_Service::save( FLXCM_Settings_Service::from_input( $input ) ) );
		},
	)
);

flx_gql_mutation(
	'flxCommerceBankSettingsSave',
	array(
		'cap'         => 'manage_woocommerce',
		'description' => __( 'Saves the bank transfer gateway. Fields you leave out stay as they are.', 'flx-commerce' ),
		'input'       => array(
			'bank'        => array( 'type' => 'String', 'description' => __( 'Bank name.', 'flx-commerce' ) ),
			'description' => array( 'type' => 'String', 'description' => __( 'Checkout text.', 'flx-commerce' ) ),
			'enabled'     => array( 'type' => 'Boolean', 'description' => __( 'Enabled.', 'flx-commerce' ) ),
			'holder'      => array( 'type' => 'String', 'description' => __( 'Account holder.', 'flx-commerce' ) ),
			'iban'        => array( 'type' => 'String', 'description' => __( 'IBAN.', 'flx-commerce' ) ),
			'refFormat'   => array( 'type' => 'String', 'description' => __( 'Reference format.', 'flx-commerce' ) ),
			'refPrefix'   => array( 'type' => 'String', 'description' => __( 'Reference prefix.', 'flx-commerce' ) ),
			'title'       => array( 'type' => 'String', 'description' => __( 'Checkout title.', 'flx-commerce' ) ),
		),
		'output'      => array(
			'settings' => array(
				'type'        => 'FlxCommerceBankSettings',
				'description' => __( 'Gateway settings after the save.', 'flx-commerce' ),
			),
		),
		'resolve'     => static function ( $input ) {
			$input = FLXCM_Val::map( $input );
			return array( 'settings' => FLXCM_Settings_Service::bank_save( $input ) );
		},
	)
);

flx_gql_mutation(
	'flxSubscriptionCartAdd',
	array(
		'cap'         => 'public',
		'description' => __( 'Adds a subscription to the cart. Public because the product page is public. Uses the cart bridge; checkout stays on the Store API.', 'flx-commerce' ),
		'input'       => array(
			'period'    => array(
				'type'        => 'FlxCommercePeriod',
				'description' => __( 'month or year. Empty uses the first period the product offers.', 'flx-commerce' ),
			),
			'productId' => array(
				'type'        => 'Int!',
				'description' => __( 'Subscription product id.', 'flx-commerce' ),
			),
		),
		'output'      => array(
			'cart'    => array(
				'type'        => 'FlxCart',
				'description' => __( 'The cart after the add.', 'flx-commerce' ),
			),
			'itemKey' => array(
				'type'        => 'String',
				'description' => __( 'Cart line key.', 'flx-commerce' ),
			),
		),
		'resolve'     => static function ( $input ) {
			$input = FLXCM_Val::map( $input );
			$result = FLXCM_Cart_Service::add( FLXCM_Val::int(( $input['productId'] ?? 0 )), FLXCM_Val::str(( $input['period'] ?? '' )) );
			return flxcm_gql_throw( $result );
		},
	)
);

/**
 * @param string $do cancel, resume, renew or forget_card.
 */
function flxcm_gql_my_act( string $do ): Closure {
	return static function ( $input ) use ( $do ) {
		$input = FLXCM_Val::map( $input );
		$act = FLXCM_Subscription_Service::account_act( FLXCM_Val::int(( $input['id'] ?? 0 )), $do, get_current_user_id() );
		return flxcm_gql_account( $act );
	};
}

$my_in = array(
	'id' => array(
		'type'        => 'ID!',
		'description' => __( 'Subscription id.', 'flx-commerce' ),
	),
);

flx_gql_mutation(
	'flxMySubscriptionCancel',
	array(
		'cap'         => 'read',
		'description' => __( 'The signed-in customer turns renewal off. FORBIDDEN for someone else subscription.', 'flx-commerce' ),
		'input'       => $my_in,
		'output'      => $acct_out,
		'resolve'     => flxcm_gql_my_act( 'cancel' ),
	)
);
flx_gql_mutation(
	'flxMySubscriptionResume',
	array(
		'cap'         => 'read',
		'description' => __( 'The signed-in customer turns renewal back on.', 'flx-commerce' ),
		'input'       => $my_in,
		'output'      => $acct_out,
		'resolve'     => flxcm_gql_my_act( 'resume' ),
	)
);
flx_gql_mutation(
	'flxMySubscriptionRenew',
	array(
		'cap'         => 'read',
		'description' => __( 'Opens a renewal and returns the payment link the account page redirects to.', 'flx-commerce' ),
		'input'       => $my_in,
		'output'      => $acct_out,
		'resolve'     => flxcm_gql_my_act( 'renew' ),
	)
);
flx_gql_mutation(
	'flxMySubscriptionForgetCard',
	array(
		'cap'         => 'read',
		'description' => __( 'Removes the stored card. Later renewals use a payment link. The sealed token is deleted, not returned.', 'flx-commerce' ),
		'input'       => $my_in,
		'output'      => $acct_out,
		'resolve'     => flxcm_gql_my_act( 'forget_card' ),
	)
);

flx_gql_mutation(
	'flxCommerceRenewalsRun',
	array(
		'cap'         => 'manage_woocommerce',
		'description' => __( 'Runs one bounded renewal pass (the same tick the hourly cron runs). The shutdown pump and cron stay scheduled.', 'flx-commerce' ),
		'input'       => array(),
		'output'      => array(
			'status' => array(
				'type'        => 'FlxCommerceRenewalStatus',
				'description' => __( 'When the pass finished.', 'flx-commerce' ),
			),
		),
		'resolve'     => static function () {
			return array( 'status' => FLXCM_Subscription_Service::renewals_run() );
		},
	)
);

if ( flxcm_gql_has_type( 'FlxCartItem' ) ) {
	flx_gql_field(
		'FlxCartItem',
		'flxSubscription',
		array(
			'type'        => 'FlxCommerceCartSubscription',
			'cap'         => 'public',
			'description' => __( 'Set when the line is a subscription. Empty for an ordinary product.', 'flx-commerce' ),
			'resolve'     => static function ( $source ) {
				return is_array( $source ) ? FLXCM_Cart_Service::line( FLXCM_Val::map( $source ) ) : null;
			},
		)
	);
}
if ( flxcm_gql_has_type( 'FlxCart' ) ) {
	flx_gql_field(
		'FlxCart',
		'flxFreeShipping',
		array(
			'type'        => 'FlxCommerceFreeShipping',
			'cap'         => 'public',
			'description' => __( 'Free-shipping bar for this cart.', 'flx-commerce' ),
			'resolve'     => static function () {
				return FLXCM_Cart_Service::free_shipping();
			},
		)
	);
}
if ( flxcm_gql_has_type( 'FlxContact' ) ) {
	flx_gql_field(
		'FlxContact',
		'flxCommerce',
		array(
			'type'        => 'FlxCommerceCustomer',
			'cap'         => flxcm_gql_view(),
			'description' => __( 'Store summary for this person. Empty when they have no store record. A CRM section will bind this same field when the section registry from Flux CRM lands.', 'flx-commerce' ),
			'resolve'     => static function ( $contact ) {
				$email = '';
				if ( is_object( $contact ) && isset( $contact->email ) ) {
					$email = FLXCM_Val::str($contact->email);
				} elseif ( is_object( $contact ) && isset( $contact->contact ) && is_object( $contact->contact ) && isset( $contact->contact->email ) ) {
					$email = FLXCM_Val::str($contact->contact->email);
				}
				if ( '' === $email ) {
					return null;
				}
				$row = FLXCM_Customers::by_email( $email );
				return is_array( $row ) ? FLXCM_Customer_Service::map( $row ) : null;
			},
		)
	);
}

/**
 * A shop manager who can see the store may see the common fields of the customer person.
 * CRM-only fields keep their own capability check.
 */
add_filter(
	'flxcrm_gql_can_see_contact',
	static function ( bool $ok, $contact, int $user_id ) {
		if ( $ok ) {
			return true;
		}
		$record = is_object( $contact ) && isset( $contact->contact ) ? $contact->contact : $contact;
		if ( ! is_object( $record ) || ! isset( $record->email ) ) {
			return (bool) $ok;
		}
		$uid = FLXCM_Val::int($user_id);
		if ( ! user_can( $uid, 'view_woocommerce_reports' ) && ! user_can( $uid, 'manage_woocommerce' ) ) {
			return false;
		}
		$email = FLXCM_Val::str($record->email);
		if ( '' === $email ) {
			return false;
		}
		return null !== FLXCM_Customers::by_email( $email );
	},
	10,
	3
);
