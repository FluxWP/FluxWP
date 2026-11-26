<?php
/**
 * Customer reads and writes shared by REST, admin-ajax and GraphQL.
 *
 * REST rows stay the historical shape (labels as strings). GraphQL rows are a separate map
 * and never include subscription card data.
 *
 * @package FLX_Commerce
 */

defined( 'ABSPATH' ) || exit;

final class FLXCM_Customer_Service {

	/**
	 * REST customer object. Keys and types match the 0.5 response.
	 *
	 * @param array<string,mixed> $r Customer row.
	 * @return array<string,mixed>
	 */
	public static function rest_row( array $r ): array {
		return array(
			'id'          => FLXCM_Val::int($r['id']),
			'name'        => FLXCM_Val::str($r['name']),
			'email'       => FLXCM_Val::str($r['email']),
			'phone'       => FLXCM_Val::str(( $r['phone'] ?? '' )),
			'city'        => FLXCM_Val::str(( $r['city'] ?? '' )),
			'orders'      => FLXCM_Val::int($r['orders']),
			'total'       => round( FLXCM_Val::float($r['total']), 2 ),
			'total_label' => flxcm_money( $r['total'] ),
			'note'        => FLXCM_Val::str(( $r['note'] ?? '' )),
			'first_label' => self::when( $r['first_at'] ?? '' ),
			'last_label'  => self::when( $r['last_at'] ?? '' ),
		);
	}

	/**
	 * @param mixed $mysql GMT mysql or empty.
	 */
	private static function when( $mysql ): string {
		if ( ! $mysql ) {
			return '';
		}
		$ts = strtotime( FLXCM_Val::str($mysql) . ' UTC' );
		return $ts ? FLXCM_Val::str( flxcm_date( $ts, 'datetime' ) ) : '';
	}

	/**
	 * REST list.
	 *
	 * @return array{items:array<int,array<string,mixed>>,total:int,pages:int}
	 */
	public static function rest_list( WP_REST_Request $r ): array {
		$per = FLXCM_Val::int($r['per_page']);
		$q   = FLXCM_Customers::query(
			array(
				's'        => sanitize_text_field( FLXCM_Val::str($r->get_param( 's' )) ),
				'segment'  => sanitize_key( FLXCM_Val::str($r->get_param( 'segment' )) ),
				'orderby'  => sanitize_key( FLXCM_Val::str($r->get_param( 'orderby' )) ),
				'order'    => sanitize_key( FLXCM_Val::str($r->get_param( 'order' )) ),
				'paged'    => FLXCM_Val::int($r['page']),
				'per_page' => $per,
			)
		);
		return array(
			'items' => array_map( array( __CLASS__, 'rest_row' ), $q['rows'] ),
			'total' => FLXCM_Val::int($q['total']),
			'pages' => FLXCM_Val::int($q['pages']),
		);
	}

	/**
	 * REST detail.
	 *
	 * @return array<string,mixed>|WP_Error
	 */
	public static function rest_one( int $id ) {
		$row = FLXCM_Customers::get( $id );
		if ( ! $row ) {
			return new WP_Error( 'flxcm_missing', __( 'Customer not found.', 'flx-commerce' ), array( 'status' => 404 ) );
		}
		return self::rest_row( $row );
	}

	/**
	 * WooCommerce order list (HPOS or posts screen).
	 */
	private static function orders_list_url(): string {
		$hpos = class_exists( '\\Automattic\\WooCommerce\\Utilities\\OrderUtil' ) && \Automattic\WooCommerce\Utilities\OrderUtil::custom_orders_table_usage_is_enabled();
		return admin_url( $hpos ? 'admin.php?page=wc-orders' : 'edit.php?post_type=shop_order' );
	}

	/**
	 * GraphQL customer. Card blocks are not on this row.
	 * Times are GMT mysql strings from the summary table, passed through flx_gql_datetime().
	 *
	 * @param array<string,mixed> $r Customer row.
	 * @return array{id:string,name:string,email:string,phone:string,city:string,orders:int,total:array{amount:float,currency:string,formatted:string},note:string,firstAt:array{iso:string,timestamp:int,label:string,relative:string}|null,lastAt:array{iso:string,timestamp:int,label:string,relative:string}|null,lastDay:string,lastOrderUrl:string,userId:int,guest:bool}
	 */
	public static function map( array $r ): array {
		$last_at  = FLXCM_Val::str( $r['last_at'] ?? '' );
		$last_id  = FLXCM_Val::int( $r['last_order'] ?? 0 );
		$last_ord = $last_id && function_exists( 'wc_get_order' ) ? wc_get_order( $last_id ) : null;
		return array(
			'id'     => FLXCM_Val::str( FLXCM_Val::int( $r['id'] ) ),
			'name'   => FLXCM_Val::str($r['name']),
			'email'  => FLXCM_Val::str($r['email']),
			'phone'  => FLXCM_Val::str(( $r['phone'] ?? '' )),
			'city'   => FLXCM_Val::str(( $r['city'] ?? '' )),
			'orders' => FLXCM_Val::int($r['orders']),
			'total'  => flxcm_gql_money( $r['total'] ),
			'note'   => FLXCM_Val::str(( $r['note'] ?? '' )),
			'firstAt' => flxcm_gql_time( $r['first_at'] ?? '' ),
			'lastAt'  => flxcm_gql_time( $r['last_at'] ?? '' ),
			'lastDay' => '' !== $last_at ? FLXCM_Val::str( get_date_from_gmt( $last_at, 'd.m.Y' ) ) : '',
			'lastOrderUrl' => $last_ord instanceof WC_Order ? $last_ord->get_edit_order_url() : ( '' !== $last_at ? self::orders_list_url() : '' ),
			'userId'  => FLXCM_Val::int(( $r['user_id'] ?? 0 )),
			'guest'   => ! FLXCM_Val::int(( $r['user_id'] ?? 0 )),
		);
	}

	/**
	 * @param array<string,mixed> $args Filters.
	 * @return array{items:array<int,array<string,mixed>>,total:int}
	 */
	public static function page( array $args, int $offset, int $limit ): array {
		$q = FLXCM_Customers::query(
			array(
				's'        => sanitize_text_field( FLXCM_Val::str(( $args['search'] ?? '' )) ),
				'segment'  => sanitize_key( FLXCM_Val::str(( $args['segment'] ?? 'all' )) ),
				'orderby'  => sanitize_key( FLXCM_Val::str(( $args['orderby'] ?? 'last_at' )) ),
				'order'    => sanitize_key( FLXCM_Val::str(( $args['order'] ?? 'desc' )) ),
				'per_page' => $limit,
				'offset'   => $offset,
			)
		);
		$items = array();
		foreach ( $q['rows'] as $row ) {
			$items[] = self::map( $row );
		}
		return array(
			'items' => $items,
			'total' => FLXCM_Val::int($q['total']),
		);
	}

	/**
	 * Stores a note. Ajax keeps the previous behaviour (missing id still returns the text).
	 * GraphQL asks for the row to exist.
	 *
	 * @return array{note:string}|WP_Error
	 */
	public static function save_note( int $id, string $note, bool $must_exist ) {
		global $wpdb;
		if ( ! $wpdb instanceof wpdb ) {
			return new WP_Error( 'flxcm_note', __( 'The note could not be saved.', 'flx-commerce' ), array( 'status' => 500 ) );
		}
		$note = sanitize_textarea_field( $note );
		if ( $must_exist && ! FLXCM_Customers::get( $id ) ) {
			return new WP_Error( 'flxcm_missing', __( 'Customer not found.', 'flx-commerce' ), array( 'status' => 404 ) );
		}
		if ( $must_exist && mb_strlen( $note ) > 2000 ) {
			return new WP_Error( 'flxcm_note', __( 'The note is too long.', 'flx-commerce' ), array( 'status' => 400 ) );
		}
		$wpdb->update( FLXCM_Val::str( FLXCM_Install::table() ), array( 'note' => mb_substr( $note, 0, 2000 ) ), array( 'id' => $id ) );
		flxcm_gql_purge( array( 'flx-commerce:customers', 'flx-commerce:customer:' . $id ) );
		return array( 'note' => $note );
	}

	/**
	 * One rebuild page. Without WooCommerce the step cannot scan orders.
	 *
	 * @return array{done:bool,page:int,scanned:int,total:int}|WP_Error
	 */
	public static function rebuild_step( int $page ) {
		if ( ! flxcm_wc_ready() ) {
			return new WP_Error( 'flxcm_wc', __( 'WooCommerce is required.', 'flx-commerce' ), array( 'status' => 400 ) );
		}
		$out = FLXCM_Customers::rebuild_step( $page );
		flxcm_gql_purge( array( 'flx-commerce:customers', 'flx-commerce:summary' ) );
		return array(
			'done'    => (bool) $out['done'],
			'page'    => FLXCM_Val::int($out['page']),
			'scanned' => FLXCM_Val::int($out['scanned']),
			'total'   => FLXCM_Val::int($out['total']),
		);
	}

	/**
	 * Classic profile bundle, or an empty one when WooCommerce is not loaded.
	 *
	 * @param array<string,mixed> $row Customer row.
	 * @return array{orders:array<int,mixed>,events:array<int,array<string,mixed>>,billing:string,shipping:string,products:array<int,array<string,mixed>>,open:int}
	 */
	public static function profile( array $row ): array {
		if ( ! flxcm_wc_ready() ) {
			return array(
				'orders'   => array(),
				'events'   => array(),
				'billing'  => '',
				'shipping' => '',
				'products' => array(),
				'open'     => 0,
			);
		}
		$d = FLXCM_Page_Customer::profile( $row );
		return array(
			'orders'   => $d['orders'],
			'events'   => $d['events'],
			'billing'  => FLXCM_Val::str($d['billing']),
			'shipping' => FLXCM_Val::str($d['shipping']),
			'products' => $d['products'],
			'open'     => FLXCM_Val::int($d['open']),
		);
	}
}
