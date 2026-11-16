<?php
/**
 * Send log. REST list and item delegate here so the JSON stays the same.
 *
 * @package flx-mail
 */

defined( 'ABSPATH' ) || exit;

/**
 * Flux Mail log.
 */
class FLXI_Log_Service {

	/**
	 * REST GET /log body (items, total, pages). Filters match the route: status, channel, s.
	 *
	 * @return array{items:list<array<string,mixed>>,total:int,pages:int}
	 */
	public static function rest_list( WP_REST_Request $r ): array {
		global $wpdb;
		$t       = flxi_table( 'log' );
		$per     = min( 100, max( 1, absint( $r->get_param( 'per_page' ) ? $r->get_param( 'per_page' ) : 25 ) ) );
		$page    = max( 1, absint( $r->get_param( 'page' ) ) );
		$where   = array( '1=1' );
		$prm     = array();
		$status  = sanitize_key( (string) $r->get_param( 'status' ) );
		$channel = sanitize_key( (string) $r->get_param( 'channel' ) );
		$search  = sanitize_text_field( (string) $r->get_param( 's' ) );
		if ( $status ) {
			$where[] = 'status = %s';
			$prm[]   = $status;
		}
		if ( $channel ) {
			$where[] = 'channel = %s';
			$prm[]   = $channel;
		}
		if ( '' !== $search ) {
			$like    = '%' . $wpdb->esc_like( $search ) . '%';
			$where[] = '(recipient LIKE %s OR subject LIKE %s)';
			array_push( $prm, $like, $like );
		}
		$w      = implode( ' AND ', $where );
		$total  = (int) ( $prm ? $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$t} WHERE {$w}", $prm ) ) : $wpdb->get_var( "SELECT COUNT(*) FROM {$t} WHERE {$w}" ) ); // phpcs:ignore WordPress.DB.PreparedSQL
		$sql    = "SELECT id, created_at, channel, template, recipient, subject, status, error FROM {$t} WHERE {$w} ORDER BY id DESC" . $wpdb->prepare( ' LIMIT %d OFFSET %d', $per, ( $page - 1 ) * $per );
		$rows   = $prm ? $wpdb->get_results( $wpdb->prepare( $sql, $prm ), ARRAY_A ) : $wpdb->get_results( $sql, ARRAY_A ); // phpcs:ignore WordPress.DB.PreparedSQL
		$labels = array(
			'sent'    => __( 'Sent', 'flx-mail' ),
			'failed'  => __( 'Failed', 'flx-mail' ),
			'blocked' => __( 'Disabled, not sent', 'flx-mail' ),
			'sending' => __( 'Sending', 'flx-mail' ),
		);
		$items  = array();
		foreach ( (array) $rows as $row ) {
			$ts      = strtotime( $row['created_at'] . ' UTC' );
			$items[] = array(
				'id'           => (int) $row['id'],
				'channel'      => (string) $row['channel'],
				'template'     => (string) $row['template'],
				'recipient'    => (string) $row['recipient'],
				'subject'      => (string) $row['subject'],
				'status'       => (string) $row['status'],
				'status_label' => isset( $labels[ $row['status'] ] ) ? $labels[ $row['status'] ] : $row['status'],
				'error'        => (string) $row['error'],
				'date_label'   => $ts && function_exists( 'flx_date' ) ? flx_date( $ts, 'datetime' ) : (string) $row['created_at'],
			);
		}
		return array(
			'items' => $items,
			'total' => $total,
			'pages' => max( 1, (int) ceil( $total / $per ) ),
		);
	}

	/**
	 * REST GET /log/{id} row, or WP_Error 404.
	 *
	 * @return array<string,mixed>|WP_Error
	 */
	public static function rest_item( WP_REST_Request $r ) {
		global $wpdb;
		$row = $wpdb->get_row( $wpdb->prepare( 'SELECT * FROM ' . flxi_table( 'log' ) . ' WHERE id = %d', (int) $r['id'] ), ARRAY_A ); // phpcs:ignore WordPress.DB.PreparedSQL
		if ( ! $row ) {
			return new WP_Error( 'flxi_404', 'Not found', array( 'status' => 404 ) );
		}
		$body = (string) $row['body'];
		if ( 'email' !== $row['channel'] || ! preg_match( '~<(html|p|div|table|br)[\s>/]~i', $body ) ) {
			$body = '<pre style="white-space:pre-wrap;font:14px/1.5 -apple-system,Segoe UI,sans-serif;margin:16px">' . esc_html( $body ) . '</pre>';
		}
		$row['body']       = $body;
		$ts                = strtotime( $row['created_at'] . ' UTC' );
		$row['date_label'] = $ts && function_exists( 'flx_date' ) ? flx_date( $ts, 'datetime' ) : (string) $row['created_at'];
		return $row;
	}

	/**
	 * One page for flxMailLogs. Adds the template and campaign filters the screen has.
	 *
	 * @param array<string,mixed> $filter status, channel, template, campaign, search.
	 * @return array{items:list<array<string,mixed>>,total:int}
	 */
	public static function page( array $filter, int $offset, int $limit ): array {
		global $wpdb;
		$t     = flxi_table( 'log' );
		$where = array( '1=1' );
		$prm   = array();
		$status  = sanitize_key( (string) ( $filter['status'] ?? '' ) );
		$channel = sanitize_key( (string) ( $filter['channel'] ?? '' ) );
		$search  = sanitize_text_field( (string) ( $filter['search'] ?? '' ) );
		$tpl     = sanitize_text_field( (string) ( $filter['template'] ?? '' ) );
		$camp    = (int) ( $filter['campaign'] ?? 0 );
		if ( $status ) {
			$where[] = 'status = %s';
			$prm[]   = $status;
		}
		if ( $channel ) {
			$where[] = 'channel = %s';
			$prm[]   = $channel;
		}
		if ( '' !== $tpl ) {
			$where[] = 'template = %s';
			$prm[]   = $tpl;
		}
		if ( $camp ) {
			$where[] = 'campaign_id = %d';
			$prm[]   = $camp;
		}
		if ( '' !== $search ) {
			$like    = '%' . $wpdb->esc_like( $search ) . '%';
			$where[] = '(recipient LIKE %s OR subject LIKE %s)';
			array_push( $prm, $like, $like );
		}
		$w     = implode( ' AND ', $where );
		$total = (int) ( $prm ? $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$t} WHERE {$w}", $prm ) ) : $wpdb->get_var( "SELECT COUNT(*) FROM {$t} WHERE {$w}" ) ); // phpcs:ignore WordPress.DB.PreparedSQL
		$sql   = "SELECT id, created_at, channel, template, recipient, subject, status, error, sender, campaign_id, user_id FROM {$t} WHERE {$w} ORDER BY id DESC" . $wpdb->prepare( ' LIMIT %d OFFSET %d', $limit, max( 0, $offset ) );
		$rows  = $prm ? $wpdb->get_results( $wpdb->prepare( $sql, $prm ), ARRAY_A ) : $wpdb->get_results( $sql, ARRAY_A ); // phpcs:ignore WordPress.DB.PreparedSQL
		$items = array();
		foreach ( (array) $rows as $row ) {
			$items[] = self::shape( $row, false );
		}
		if ( function_exists( 'flx_gql_cache_tag' ) ) {
			flx_gql_cache_tag( 'flx-mail:log' );
		}
		return array( 'items' => $items, 'total' => $total );
	}

	/**
	 * @return array<string,mixed>|null
	 */
	public static function one( int $id ): ?array {
		global $wpdb;
		$row = $wpdb->get_row( $wpdb->prepare( 'SELECT * FROM ' . flxi_table( 'log' ) . ' WHERE id = %d', $id ), ARRAY_A ); // phpcs:ignore WordPress.DB.PreparedSQL
		if ( ! $row ) {
			return null;
		}
		if ( function_exists( 'flx_gql_cache_tag' ) ) {
			flx_gql_cache_tag( 'flx-mail:log' );
		}
		return self::shape( $row, true );
	}

	/**
	 * @param array<string,mixed> $row Database row.
	 * @return array<string,mixed>
	 */
	private static function shape( array $row, bool $with_body ): array {
		$ts   = strtotime( (string) $row['created_at'] . ' UTC' );
		$body = null;
		if ( $with_body && flxi_setting( 'log_body' ) ) {
			$raw = (string) ( $row['body'] ?? '' );
			if ( 'email' !== $row['channel'] || ! preg_match( '~<(html|p|div|table|br)[\s>/]~i', $raw ) ) {
				$raw = '<pre style="white-space:pre-wrap;font:14px/1.5 -apple-system,Segoe UI,sans-serif;margin:16px">' . esc_html( $raw ) . '</pre>';
			}
			$body = $raw;
		}
		return array(
			'databaseId' => (int) $row['id'],
			'createdAt'  => $ts ? $ts : null,
			'channel'    => (string) $row['channel'],
			'template'   => (string) $row['template'],
			'recipient'  => (string) $row['recipient'],
			'subject'    => (string) $row['subject'],
			'status'     => (string) $row['status'],
			'error'      => '' !== (string) ( $row['error'] ?? '' ) ? (string) $row['error'] : null,
			'sender'     => (string) ( $row['sender'] ?? '' ),
			'campaignId' => (int) ( $row['campaign_id'] ?? 0 ),
			'userId'     => (int) ( $row['user_id'] ?? 0 ),
			'body'       => $body,
			'person'     => self::person( $row ),
		);
	}

	/**
	 * FlxPerson when Flux CRM is active and the viewer may see the contact. Otherwise null.
	 *
	 * @param array<string,mixed> $row Log row.
	 * @return object|null
	 */
	public static function person( array $row ) {
		if ( ! function_exists( 'flxcrm_contact_for' ) || ! class_exists( 'FLXCRM_Contact_Node' ) || ! class_exists( 'FLXCRM_Contact_Service' ) ) {
			return null;
		}
		$id = 0;
		if ( ! empty( $row['user_id'] ) ) {
			$id = (int) flxcrm_contact_for( array( 'user_id' => (int) $row['user_id'] ) );
		}
		if ( ! $id && is_email( (string) ( $row['recipient'] ?? '' ) ) ) {
			$id = (int) flxcrm_contact_for( array( 'email' => (string) $row['recipient'] ) );
		}
		if ( ! $id ) {
			return null;
		}
		$found = FLXCRM_Contact_Service::by_ids( array( $id ) );
		$c     = $found[ $id ] ?? null;
		if ( ! $c ) {
			return null;
		}
		$ok = function_exists( 'flxcrm_can' ) ? flxcrm_can() : false;
		$ok = (bool) apply_filters( 'flxcrm_gql_can_see_contact', $ok, $c, get_current_user_id() );
		return $ok ? new FLXCRM_Contact_Node( $c ) : null;
	}
}
