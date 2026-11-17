<?php
/**
 * Bank-transfer receipts. The guest multipart upload stays on its own route.
 * Approve is shared by REST and GraphQL. The REST row shape is unchanged.
 *
 * @package FLX_Commerce
 */

defined( 'ABSPATH' ) || exit;

final class FLXCM_Receipt_Service {

	/**
	 * @return array{orders:array<mixed>,total:int,pages:int}
	 */
	public static function query( int $page, int $per ): array {
		return self::query_window( array( 'limit' => $per, 'paged' => max( 1, $page ) ) );
	}

	/**
	 * @param array<string,int> $window Either limit + paged or limit + offset.
	 * @return array{orders:array<mixed>,total:int,pages:int}
	 */
	private static function query_window( array $window ): array {
		if ( ! function_exists( 'wc_get_orders' ) ) {
			return array( 'orders' => array(), 'total' => 0, 'pages' => 1 );
		}
		$q = wc_get_orders(
			array_merge(
				array(
					'type'           => 'shop_order',
					'status'         => array( 'on-hold', 'pending' ),
					'payment_method' => FLXCM_Bank::ID,
					'orderby'        => 'date',
					'order'          => 'DESC',
					'paginate'       => true,
				),
				$window
			)
		);
		$orders = array();
		if ( is_object( $q ) && isset( $q->orders ) ) {
			$orders = FLXCM_Val::arr( $q->orders );
		} elseif ( is_array( $q ) ) {
			$orders = $q;
		}
		$total = is_object( $q ) && isset( $q->total ) ? FLXCM_Val::int( $q->total ) : count( $orders );
		$pages = is_object( $q ) && isset( $q->max_num_pages ) ? FLXCM_Val::int( $q->max_num_pages ) : 1;
		return array(
			'orders' => $orders,
			'total'  => $total,
			'pages'  => FLXCM_Val::int( max( 1, $pages ) ),
		);
	}

	/**
	 * REST receipt object.
	 *
	 * @param mixed $order Order.
	 * @return array<string,mixed>|null
	 */
	public static function rest_row( $order ): ?array {
		if ( ! $order instanceof WC_Order ) {
			return null;
		}
		$files = FLXCM_Receipts::of( $order );
		$list  = array();
		foreach ( FLXCM_Val::arr( $files ) as $i => $file ) {
			$file = FLXCM_Val::map( $file );
			$list[] = array(
				'name' => FLXCM_Val::str(( $file['name'] ?? '' )),
				'url'  => FLXCM_Receipts::url( $order, $i, true ),
			);
		}
		return array(
			'id'           => $order->get_id(),
			'number'       => $order->get_order_number(),
			'name'         => trim( $order->get_formatted_billing_full_name() ),
			'email'        => FLXCM_Val::str($order->get_billing_email()),
			'phone'        => FLXCM_Val::str($order->get_billing_phone()),
			'total_label'  => flxcm_money( $order->get_total(), $order->get_currency() ),
			'reference'    => FLXCM_Bank::reference( $order ),
			'status'       => $order->get_status(),
			'status_label' => flxcm_status_label( $order->get_status() ),
			'receipts'     => count( $list ),
			'files'        => $list,
			'date_label'   => $order->get_date_created() ? flxcm_date( $order->get_date_created()->getTimestamp(), 'datetime' ) : '',
		);
	}

	/**
	 * GraphQL receipt. The file URL is present only for a viewer who can open the file
	 * (`edit_shop_orders`, the same check as FLXCM_Receipts::serve()). A shop manager
	 * with only manage_woocommerce still sees the receipt, with an empty URL.
	 *
	 * @return array{id:string,number:string,name:string,email:string,phone:string,total:array{amount:float,currency:string,formatted:string},reference:string,status:string,statusLabel:string,files:list<array{name:string,url:string}>,createdAt:array{iso:string,timestamp:int,label:string,relative:string}|null}|null
	 */
	public static function map( WC_Order $order ): ?array {
		$row = self::rest_row( $order );
		if ( ! $row ) {
			return null;
		}
		$open  = current_user_can( 'edit_shop_orders' );
		$files = array();
		foreach ( FLXCM_Val::arr( $row['files'] ?? array() ) as $f ) {
			$f = FLXCM_Val::map( $f );
			$files[] = array(
				'name' => FLXCM_Val::str(( $f['name'] ?? '' )),
				'url'  => $open ? FLXCM_Val::str(( $f['url'] ?? '' )) : '',
			);
		}
		return array(
			'id'          => FLXCM_Val::str( FLXCM_Val::int( $row['id'] ) ),
			'number'      => FLXCM_Val::str($row['number']),
			'name'        => FLXCM_Val::str($row['name']),
			'email'       => FLXCM_Val::str($row['email']),
			'phone'       => FLXCM_Val::str($row['phone']),
			'total'       => flxcm_gql_money( $order->get_total(), $order->get_currency() ),
			'reference'   => FLXCM_Val::str($row['reference']),
			'status'      => FLXCM_Val::str($row['status']),
			'statusLabel' => FLXCM_Val::str($row['status_label']),
			'files'       => $files,
			'createdAt'   => $order->get_date_created() ? flxcm_gql_time( $order->get_date_created()->getTimestamp() ) : null,
		);
	}

	/**
	 * @return array{items:array<int,array<string,mixed>>,total:int,pages:int}
	 */
	public static function rest_list( int $page, int $per ): array {
		$q     = self::query( $page, $per );
		$items = array();
		foreach ( $q['orders'] as $order ) {
			$row = self::rest_row( $order );
			if ( $row ) {
				$items[] = $row;
			}
		}
		return array(
			'items' => $items,
			'total' => $q['total'],
			'pages' => $q['pages'],
		);
	}

	/**
	 * @return array<string,mixed>|WP_Error
	 */
	public static function rest_one( int $id ) {
		$order = flxcm_order( $id );
		if ( ! $order || FLXCM_Bank::ID !== $order->get_payment_method() ) {
			return new WP_Error( 'flxcm_missing', __( 'Order not found.', 'flx-commerce' ), array( 'status' => 404 ) );
		}
		$row = self::rest_row( $order );
		return $row ? $row : new WP_Error( 'flxcm_missing', __( 'Order not found.', 'flx-commerce' ), array( 'status' => 404 ) );
	}

	/**
	 * @return array{ok:bool,order:array<string,mixed>|null}|WP_Error
	 */
	public static function approve( int $id ) {
		if ( ! function_exists( 'wc_get_order' ) ) {
			return new WP_Error( 'flxcm_missing', __( 'Order not found.', 'flx-commerce' ), array( 'status' => 404 ) );
		}
		$order = flxcm_order( $id );
		if ( ! $order ) {
			return new WP_Error( 'flxcm_missing', __( 'Order not found.', 'flx-commerce' ), array( 'status' => 404 ) );
		}
		if ( ! FLXCM_Bank::mark_paid( $order ) ) {
			return new WP_Error( 'flxcm_state', __( 'This order is not waiting for approval.', 'flx-commerce' ), array( 'status' => 409 ) );
		}
		$order = flxcm_order( $order->get_id() );
		flxcm_gql_purge( array( 'flx-commerce:receipts', 'flx-commerce:summary' ) );
		return array(
			'ok'    => true,
			'order' => self::rest_row( $order ),
		);
	}

	/**
	 * @return array{items:array<int,array<string,mixed>>,total:int}
	 */
	public static function page( int $offset, int $limit ): array {
		$q     = self::query_window( array( 'limit' => max( 1, $limit ), 'offset' => max( 0, $offset ) ) );
		$items = array();
		foreach ( $q['orders'] as $order ) {
			if ( ! $order instanceof WC_Order ) {
				continue;
			}
			$row = self::map( $order );
			if ( $row ) {
				$items[] = $row;
			}
		}
		return array(
			'items' => $items,
			'total' => FLXCM_Val::int($q['total']),
		);
	}
}
