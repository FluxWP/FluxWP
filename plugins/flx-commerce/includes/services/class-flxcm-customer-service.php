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
			'id'          => (int) $r['id'],
			'name'        => (string) $r['name'],
			'email'       => (string) $r['email'],
			'phone'       => (string) ( $r['phone'] ?? '' ),
			'city'        => (string) ( $r['city'] ?? '' ),
			'orders'      => (int) $r['orders'],
			'total'       => round( (float) $r['total'], 2 ),
			'total_label' => flxcm_money( $r['total'] ),
			'note'        => (string) ( $r['note'] ?? '' ),
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
		$ts = strtotime( (string) $mysql . ' UTC' );
		return $ts ? flxcm_date( $ts, 'datetime' ) : '';
	}

	/**
	 * REST list.
	 *
	 * @return array{items:array<int,array<string,mixed>>,total:int,pages:int}
	 */
	public static function rest_list( WP_REST_Request $r ): array {
		$per = (int) $r['per_page'];
		$q   = FLXCM_Customers::query(
			array(
				's'        => sanitize_text_field( (string) $r->get_param( 's' ) ),
				'segment'  => sanitize_key( (string) $r->get_param( 'segment' ) ),
				'orderby'  => sanitize_key( (string) $r->get_param( 'orderby' ) ),
				'order'    => sanitize_key( (string) $r->get_param( 'order' ) ),
				'paged'    => (int) $r['page'],
				'per_page' => $per,
			)
		);
		return array(
			'items' => array_map( array( __CLASS__, 'rest_row' ), $q['rows'] ),
			'total' => (int) $q['total'],
			'pages' => (int) $q['pages'],
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
	 * GraphQL customer. Card blocks are not on this row.
	 *
	 * @param array<string,mixed> $r Customer row.
	 * @return array<string,mixed>
	 */
	public static function map( array $r ): array {
		return array(
			'id'     => (string) (int) $r['id'],
			'name'   => (string) $r['name'],
			'email'  => (string) $r['email'],
			'phone'  => (string) ( $r['phone'] ?? '' ),
			'city'   => (string) ( $r['city'] ?? '' ),
			'orders' => (int) $r['orders'],
			'total'  => flxcm_gql_money( $r['total'] ),
			'note'   => (string) ( $r['note'] ?? '' ),
			'firstAt' => flxcm_gql_time( $r['first_at'] ?? '' ),
			'lastAt'  => flxcm_gql_time( $r['last_at'] ?? '' ),
			'userId'  => (int) ( $r['user_id'] ?? 0 ),
			'guest'   => ! (int) ( $r['user_id'] ?? 0 ),
		);
	}

	/**
	 * @param array<string,mixed> $args Filters.
	 * @return array{items:array<int,array<string,mixed>>,total:int}
	 */
	public static function page( array $args, int $offset, int $limit ): array {
		$q = FLXCM_Customers::query(
			array(
				's'        => sanitize_text_field( (string) ( $args['search'] ?? '' ) ),
				'segment'  => sanitize_key( (string) ( $args['segment'] ?? 'all' ) ),
				'orderby'  => sanitize_key( (string) ( $args['orderby'] ?? 'last_at' ) ),
				'order'    => sanitize_key( (string) ( $args['order'] ?? 'desc' ) ),
				'per_page' => $limit,
				'offset'   => $offset,
			)
		);
		$items = array();
		foreach ( $q['rows'] as $row ) {
			if ( is_array( $row ) ) {
				$items[] = self::map( $row );
			}
		}
		return array(
			'items' => $items,
			'total' => (int) $q['total'],
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
		$note = sanitize_textarea_field( $note );
		if ( $must_exist && ! FLXCM_Customers::get( $id ) ) {
			return new WP_Error( 'flxcm_missing', __( 'Customer not found.', 'flx-commerce' ), array( 'status' => 404 ) );
		}
		if ( $must_exist && mb_strlen( $note ) > 2000 ) {
			return new WP_Error( 'flxcm_note', __( 'The note is too long.', 'flx-commerce' ), array( 'status' => 400 ) );
		}
		$wpdb->update( FLXCM_Install::table(), array( 'note' => mb_substr( $note, 0, 2000 ) ), array( 'id' => $id ) );
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
			'page'    => (int) $out['page'],
			'scanned' => (int) $out['scanned'],
			'total'   => (int) $out['total'],
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
			'billing'  => (string) $d['billing'],
			'shipping' => (string) $d['shipping'],
			'products' => $d['products'],
			'open'     => (int) $d['open'],
		);
	}
}
