<?php
/**
 * Send log. REST list and item delegate here so the JSON stays the same.
 *
 * @package flx-mail
 */

defined( 'ABSPATH' ) || exit;

/**
 * Flux Mail log.
 *
 * createdAt is the Unix time of the stored GMT created_at string.
 *
 * @phpstan-type FlxiLogRow array{databaseId: int, createdAt: int|null, channel: string, template: string, channelLabel: string, templateLabel: string, recipient: string, subject: string, status: string, error: string|null, sender: string, campaignId: int, userId: int, body: string|null, person: object|null}
 * @phpstan-type FlxiRestLogItem array{id: int, channel: string, template: string, recipient: string, subject: string, status: string, status_label: string, error: string, date_label: string}
 */
class FLXI_Log_Service {

	/**
	 * REST GET /log body (items, total, pages). Filters match the route: status, channel, s.
	 *
	 * @return array{items: list<FlxiRestLogItem>, total: int, pages: int<1, max>}
	 */
	public static function rest_list( WP_REST_Request $r ): array {
		/** @var wpdb $wpdb */
		global $wpdb;
		$t       = flxi_table( 'log' );
		$per     = min( 100, max( 1, absint( flxi_str( $r->get_param( 'per_page' ) ? $r->get_param( 'per_page' ) : 25 ) ) ) );
		$page    = max( 1, absint( flxi_str( $r->get_param( 'page' ) ) ) );
		$where   = array( '1=1' );
		$prm     = array();
		$status  = sanitize_key( flxi_str( $r->get_param( 'status' ) ) );
		$channel = sanitize_key( flxi_str( $r->get_param( 'channel' ) ) );
		$search  = sanitize_text_field( flxi_str( $r->get_param( 's' ) ) );
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
		$total  = (int) ( $prm ? $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$t} WHERE {$w}", $prm ) ) : $wpdb->get_var( "SELECT COUNT(*) FROM {$t} WHERE {$w}" ) ); // phpcs:ignore WordPress.DB.PreparedSQL -- @phpstan-ignore argument.type
		$sql    = "SELECT id, created_at, channel, template, source, recipient, subject, status, error FROM {$t} WHERE {$w} ORDER BY id DESC" . $wpdb->prepare( ' LIMIT %d OFFSET %d', $per, ( $page - 1 ) * $per );
		$rows   = $prm ? $wpdb->get_results( $wpdb->prepare( $sql, $prm ), ARRAY_A ) : $wpdb->get_results( $sql, ARRAY_A ); // phpcs:ignore WordPress.DB.PreparedSQL -- @phpstan-ignore argument.type
		$labels = array(
			'sent'    => __( 'Sent', 'flx-mail' ),
			'failed'  => __( 'Failed', 'flx-mail' ),
			'blocked' => __( 'Disabled, not sent', 'flx-mail' ),
			'sending' => __( 'Sending', 'flx-mail' ),
		);
		$items  = array();
		foreach ( flxi_rows( $rows ) as $row ) {
			$created = flxi_str( $row['created_at'] ?? '' );
			$status  = flxi_str( $row['status'] ?? '' );
			$ts      = strtotime( $created . ' UTC' );
			$items[] = array(
				'id'           => flxi_int( $row['id'] ?? 0 ),
				'channel'      => flxi_str( $row['channel'] ?? '' ),
				'template'     => flxi_str( $row['template'] ?? '' ),
				'recipient'    => flxi_str( $row['recipient'] ?? '' ),
				'subject'      => flxi_str( $row['subject'] ?? '' ),
				'status'       => $status,
				'status_label' => $labels[ $status ] ?? $status,
				'error'        => flxi_str( $row['error'] ?? '' ),
				'date_label'   => $ts && function_exists( 'flx_date' ) ? flxi_str( flx_date( $ts, 'datetime' ) ) : $created,
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
		/** @var wpdb $wpdb */
		global $wpdb;
		$row = $wpdb->get_row( $wpdb->prepare( 'SELECT * FROM ' . flxi_table( 'log' ) . ' WHERE id = %d', flxi_int( $r['id'] ?? 0 ) ), ARRAY_A ); // phpcs:ignore WordPress.DB.PreparedSQL -- @phpstan-ignore argument.type
		if ( ! is_array( $row ) ) {
			return new WP_Error( 'flxi_404', 'Not found', array( 'status' => 404 ) );
		}
		$row     = flxi_arr( $row );
		$body    = flxi_str( $row['body'] ?? '' );
		$channel = flxi_str( $row['channel'] ?? '' );
		if ( 'email' !== $channel || ! preg_match( '~<(html|p|div|table|br)[\s>/]~i', $body ) ) {
			$body = '<pre style="white-space:pre-wrap;font:14px/1.5 -apple-system,Segoe UI,sans-serif;margin:16px">' . esc_html( $body ) . '</pre>';
		}
		$row['body']       = $body;
		$created           = flxi_str( $row['created_at'] ?? '' );
		$ts                = strtotime( $created . ' UTC' );
		$row['date_label'] = $ts && function_exists( 'flx_date' ) ? flx_date( $ts, 'datetime' ) : $created;
		return $row;
	}

	/**
	 * One page for flxMailLogs. Adds the template and campaign filters the screen has.
	 *
	 * @param array<string,mixed> $filter status, channel, template, campaign, search.
	 * @return array{items: list<FlxiLogRow>, total: int}
	 */
	public static function page( array $filter, int $offset, int $limit ): array {
		/** @var wpdb $wpdb */
		global $wpdb;
		$t     = flxi_table( 'log' );
		$where = array( '1=1' );
		$prm   = array();
		$status  = sanitize_key( flxi_str( $filter['status'] ?? '' ) );
		$channel = sanitize_key( flxi_str( $filter['channel'] ?? '' ) );
		$search  = sanitize_text_field( flxi_str( $filter['search'] ?? '' ) );
		$tpl     = sanitize_text_field( flxi_str( $filter['template'] ?? '' ) );
		$camp    = flxi_int( $filter['campaign'] ?? 0 );
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
		$total = (int) ( $prm ? $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$t} WHERE {$w}", $prm ) ) : $wpdb->get_var( "SELECT COUNT(*) FROM {$t} WHERE {$w}" ) ); // phpcs:ignore WordPress.DB.PreparedSQL -- @phpstan-ignore argument.type
		$sql   = "SELECT id, created_at, channel, template, source, recipient, subject, status, error, sender, campaign_id, user_id FROM {$t} WHERE {$w} ORDER BY id DESC" . $wpdb->prepare( ' LIMIT %d OFFSET %d', $limit, max( 0, $offset ) );
		$rows  = $prm ? $wpdb->get_results( $wpdb->prepare( $sql, $prm ), ARRAY_A ) : $wpdb->get_results( $sql, ARRAY_A ); // phpcs:ignore WordPress.DB.PreparedSQL -- @phpstan-ignore argument.type
		$items = array();
		foreach ( flxi_rows( $rows ) as $row ) {
			$items[] = self::shape( $row, false );
		}
		if ( function_exists( 'flx_gql_cache_tag' ) ) {
			flx_gql_cache_tag( 'flx-mail:log' );
		}
		return array( 'items' => $items, 'total' => $total );
	}

	/**
	 * @return FlxiLogRow|null
	 */
	public static function one( int $id ): ?array {
		/** @var wpdb $wpdb */
		global $wpdb;
		$row = $wpdb->get_row( $wpdb->prepare( 'SELECT * FROM ' . flxi_table( 'log' ) . ' WHERE id = %d', $id ), ARRAY_A ); // phpcs:ignore WordPress.DB.PreparedSQL -- @phpstan-ignore argument.type
		if ( ! is_array( $row ) ) {
			return null;
		}
		if ( function_exists( 'flx_gql_cache_tag' ) ) {
			flx_gql_cache_tag( 'flx-mail:log' );
		}
		return self::shape( flxi_arr( $row ), true );
	}

	/**
	 * @param array<string,mixed> $row Database row.
	 * @return FlxiLogRow
	 */
	private static function shape( array $row, bool $with_body ): array {
		$created = flxi_str( $row['created_at'] ?? '' );
		$channel = flxi_str( $row['channel'] ?? '' );
		$error   = flxi_str( $row['error'] ?? '' );
		$ts      = strtotime( $created . ' UTC' );
		$body    = null;
		if ( $with_body && flxi_setting( 'log_body' ) ) {
			$raw = flxi_str( $row['body'] ?? '' );
			if ( 'email' !== $channel || ! preg_match( '~<(html|p|div|table|br)[\s>/]~i', $raw ) ) {
				$raw = '<pre style="white-space:pre-wrap;font:14px/1.5 -apple-system,Segoe UI,sans-serif;margin:16px">' . esc_html( $raw ) . '</pre>';
			}
			$body = $raw;
		}
		return array(
			'databaseId' => flxi_int( $row['id'] ?? 0 ),
			'createdAt'  => $ts ? $ts : null,
			'channel'    => $channel,
			'template'   => flxi_str( $row['template'] ?? '' ),
			'channelLabel'  => flxi_str( flxi_channels()[ $channel ] ?? $channel ),
			'templateLabel' => self::template_label( flxi_str( $row['template'] ?? '' ), flxi_str( $row['source'] ?? '' ) ),
			'recipient'  => flxi_str( $row['recipient'] ?? '' ),
			'subject'    => flxi_str( $row['subject'] ?? '' ),
			'status'     => flxi_str( $row['status'] ?? '' ),
			'error'      => '' !== $error ? $error : null,
			'sender'     => flxi_str( $row['sender'] ?? '' ),
			'campaignId' => flxi_int( $row['campaign_id'] ?? 0 ),
			'userId'     => flxi_int( $row['user_id'] ?? 0 ),
			'body'       => $body,
			'person'     => self::person( $row ),
		);
	}

	/**
	 * Name of the email and its source, as the classic log showed it ("Test · Flux Mail").
	 */
	private static function template_label( string $key, string $source ): string {
		static $types = null, $disc = null;
		$types = $types ?? flxi_arr( flxi_email_types() );
		$disc  = $disc ?? flxi_arr( flxi_discovered() );
		$type  = flxi_arr( $types[ $key ] ?? null );
		$name  = flxi_str( $type['label'] ?? '' );
		if ( '' === $name && isset( $disc[ $key ] ) ) {
			$name = flxi_str( flxi_discovered_label( $key, flxi_arr( $disc[ $key ] ) ) );
		}
		if ( 0 === strpos( $key, 'wc/' ) ) {
			$name = 'WooCommerce · ' . substr( $key, 3 );
		} elseif ( 'flx-mail/campaign' === $key ) {
			$name = __( 'Bulk send', 'flx-mail' );
		} elseif ( 'flx-mail/test' === $key ) {
			$name = __( 'Test', 'flx-mail' );
		}
		return trim( $name . ( '' !== $source ? ' · ' . flxi_str( flxi_source_label( $source ) ) : '' ), ' ·' );
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
			$id = flxi_int( flxcrm_contact_for( array( 'user_id' => flxi_int( $row['user_id'] ) ) ) );
		}
		$recipient = flxi_str( $row['recipient'] ?? '' );
		if ( ! $id && is_email( $recipient ) ) {
			$id = flxi_int( flxcrm_contact_for( array( 'email' => $recipient ) ) );
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
