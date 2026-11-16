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
	 * @return array{orders:array<int,mixed>,total:int,pages:int}
	 */
	public static function query( int $page, int $per ): array {
		if ( ! function_exists( 'wc_get_orders' ) ) {
			return array( 'orders' => array(), 'total' => 0, 'pages' => 1 );
		}
		$q = wc_get_orders(
			array(
				'type'           => 'shop_order',
				'status'         => array( 'on-hold', 'pending' ),
				'payment_method' => FLXCM_Bank::ID,
				'limit'          => $per,
				'paged'          => max( 1, $page ),
				'orderby'        => 'date',
				'order'          => 'DESC',
				'paginate'       => true,
			)
		);
		$orders = is_object( $q ) ? (array) $q->orders : (array) $q;
		$total  = is_object( $q ) ? (int) $q->total : count( $orders );
		$pages  = is_object( $q ) ? (int) $q->max_num_pages : 1;
		return array(
			'orders' => $orders,
			'total'  => $total,
			'pages'  => max( 1, $pages ),
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
		foreach ( $files as $i => $file ) {
			$list[] = array(
				'name' => (string) ( $file['name'] ?? '' ),
				'url'  => FLXCM_Receipts::url( $order, $i, true ),
			);
		}
		return array(
			'id'           => $order->get_id(),
			'number'       => $order->get_order_number(),
			'name'         => trim( $order->get_formatted_billing_full_name() ),
			'email'        => (string) $order->get_billing_email(),
			'phone'        => (string) $order->get_billing_phone(),
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
	 * @return array<string,mixed>|null
	 */
	public static function map( $order ): ?array {
		$row = self::rest_row( $order );
		if ( ! $row || ! $order instanceof WC_Order ) {
			return null;
		}
		$open  = current_user_can( 'edit_shop_orders' );
		$files = array();
		foreach ( $row['files'] as $f ) {
			if ( is_array( $f ) ) {
				$files[] = array(
					'name' => (string) ( $f['name'] ?? '' ),
					'url'  => $open ? (string) ( $f['url'] ?? '' ) : '',
				);
			}
		}
		return array(
			'id'          => (string) (int) $row['id'],
			'number'      => (string) $row['number'],
			'name'        => (string) $row['name'],
			'email'       => (string) $row['email'],
			'phone'       => (string) $row['phone'],
			'total'       => flxcm_gql_money( $order->get_total(), $order->get_currency() ),
			'reference'   => (string) $row['reference'],
			'status'      => (string) $row['status'],
			'statusLabel' => (string) $row['status_label'],
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
		$order = function_exists( 'wc_get_order' ) ? wc_get_order( $id ) : null;
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
		$order = wc_get_order( $id );
		if ( ! $order ) {
			return new WP_Error( 'flxcm_missing', __( 'Order not found.', 'flx-commerce' ), array( 'status' => 404 ) );
		}
		if ( ! FLXCM_Bank::mark_paid( $order ) ) {
			return new WP_Error( 'flxcm_state', __( 'This order is not waiting for approval.', 'flx-commerce' ), array( 'status' => 409 ) );
		}
		$order = wc_get_order( $order->get_id() );
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
		$need = $offset + $limit;
		$q    = self::query( 1, max( 1, $need ) );
		$all  = array();
		foreach ( $q['orders'] as $order ) {
			$row = self::map( $order );
			if ( $row ) {
				$all[] = $row;
			}
		}
		return array(
			'items' => array_slice( $all, $offset, $limit ),
			'total' => (int) $q['total'],
		);
	}
}
