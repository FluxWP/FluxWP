<?php
/**
 * Panel data for the Forms screen (GraphQL): dashboard numbers, the form list, responses and replies.
 *
 * Tables (see FLXF_Dashboard): emsfb_form, emsfb_msg_ (read_ 0/3 unread, 1/4 read, 2 = draft waiting for a
 * payment, never listed), emsfb_rsp_ (replies). Writes do what the classic panel AJAX handlers do.
 *
 * @package FLX_Forms
 */

defined( 'ABSPATH' ) || exit;

/**
 * Admin reads and writes behind flxForms* operations.
 */
final class FLXF_Admin_Service {

	/** Longest reply kept. */
	private const REPLY_MAX = 5000;

	/**
	 * Same rule as the classic panel (efbFunction::user_permission_efb_admin_dashboard).
	 */
	public static function can(): bool {
		return is_user_logged_in() && ( current_user_can( 'manage_options' ) || current_user_can( 'Emsfb' ) );
	}

	/**
	 * May open the form builder.
	 */
	public static function can_create(): bool {
		return current_user_can( 'manage_options' ) || current_user_can( 'Emsfb_create' );
	}

	/**
	 * Loads the dashboard class (it is only loaded in wp-admin).
	 */
	private static function dashboard_class(): void {
		if ( ! class_exists( 'FLXF_Dashboard' ) ) {
			require_once dirname( __DIR__ ) . '/flx/dashboard/class-flxf-dashboard.php';
		}
	}

	/**
	 * Dashboard numbers for a date range (the classic dashboard payload).
	 *
	 * @param array<string,mixed> $args from, to (Y-m-d), form id.
	 * @return array<string,mixed>
	 */
	public static function dashboard( array $args ): array {
		self::dashboard_class();
		$src = array(
			'from' => self::str( $args['from'] ?? '' ),
			'to'   => self::str( $args['to'] ?? '' ),
			'form' => self::num( $args['form'] ?? 0 ),
		);
		list( $from, $to, $form ) = FLXF_Dashboard::request_filters( $src );
		$out = FLXF_Dashboard::stats( $from, $to, $form );
		$out['csvUrl'] = add_query_arg(
			array(
				'action'   => 'flxf_dash_csv',
				'_wpnonce' => wp_create_nonce( FLXF_Dashboard::CSV_NONCE ),
				'from'     => $from,
				'to'       => $to,
				'form'     => $form,
			),
			admin_url( 'admin-post.php' )
		);
		return $out;
	}

	/**
	 * A page of forms.
	 *
	 * @param array<string,mixed> $f      search, type.
	 * @param int                 $offset Offset.
	 * @param int                 $limit  Rows.
	 * @return array{items:list<array<string,mixed>>,total:int}
	 */
	public static function forms( array $f, int $offset, int $limit ): array {
		global $wpdb;
		/** @var wpdb $wpdb */
		$form  = $wpdb->prefix . 'emsfb_form';
		$msg   = $wpdb->prefix . 'emsfb_msg_';
		$where = '1=1';
		$vals  = array();
		$q     = trim( self::str( $f['search'] ?? '' ) );
		if ( '' !== $q ) {
			if ( ctype_digit( $q ) ) {
				$where .= ' AND (form_name LIKE %s OR form_id = %d)';
				$vals[] = '%' . $wpdb->esc_like( $q ) . '%';
				$vals[] = (int) $q;
			} else {
				$where .= ' AND form_name LIKE %s';
				$vals[] = '%' . $wpdb->esc_like( $q ) . '%';
			}
		}
		$type = sanitize_key( self::str( $f['type'] ?? '' ) );
		if ( '' !== $type ) {
			$where .= ' AND form_type = %s';
			$vals[] = $type;
		}
		// phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQL.NotPrepared -- table names; $where is built from placeholders.
		$total = (int) $wpdb->get_var( $vals ? $wpdb->prepare( "SELECT COUNT(*) FROM `{$form}` WHERE {$where}", ...$vals ) : "SELECT COUNT(*) FROM `{$form}` WHERE {$where}" );
		$rows  = $wpdb->get_results(
			$wpdb->prepare( "SELECT form_id, form_name, form_type, form_create_date, status FROM `{$form}` WHERE {$where} ORDER BY form_id DESC LIMIT %d OFFSET %d", ...array_merge( $vals, array( $limit, $offset ) ) ),
			ARRAY_A
		);
		$ids   = array_map( static fn( $r ) => (int) $r['form_id'], (array) $rows );
		$stats = array();
		if ( $ids ) {
			$in = implode( ',', array_fill( 0, count( $ids ), '%d' ) );
			foreach ( (array) $wpdb->get_results( $wpdb->prepare( "SELECT form_id, COUNT(*) n, SUM(read_ IN (0,3)) u, MAX(`date`) last_date FROM `{$msg}` WHERE read_ <> 2 AND form_id IN ({$in}) GROUP BY form_id", ...$ids ), ARRAY_A ) as $s ) {
				$stats[ (int) $s['form_id'] ] = $s;
			}
		}
		// phpcs:enable
		$items = array();
		foreach ( (array) $rows as $r ) {
			$id      = (int) $r['form_id'];
			$s       = $stats[ $id ] ?? array();
			$items[] = array(
				'databaseId'     => $id,
				'title'          => self::title( self::str( $r['form_name'] ), $id ),
				'formType'       => self::str( $r['form_type'] ) ?: 'form',
				'createdAt'      => self::moment( self::str( $r['form_create_date'] ) ),
				'responses'      => self::num( $s['n'] ?? 0 ),
				'unread'         => self::num( $s['u'] ?? 0 ),
				'lastResponseAt' => ! empty( $s['last_date'] ) ? self::moment( self::str( $s['last_date'] ) ) : null,
				'shortcode'      => '[EMS_Form_Builder id=' . $id . ']',
				'editUrl'        => admin_url( 'admin.php?page=Emsfb&state=edit-form&id=' . $id ),
			);
		}
		return array(
			'items' => $items,
			'total' => $total,
		);
	}

	/**
	 * Every form id and name, for filters.
	 *
	 * @return list<array{databaseId:int,title:string}>
	 */
	public static function form_options(): array {
		global $wpdb;
		/** @var wpdb $wpdb */
		$rows = $wpdb->get_results( $wpdb->prepare( 'SELECT form_id, form_name FROM %i ORDER BY form_name ASC', $wpdb->prefix . 'emsfb_form' ), ARRAY_A );
		$out  = array();
		foreach ( (array) $rows as $r ) {
			$out[] = array(
				'databaseId' => (int) $r['form_id'],
				'title'      => self::title( self::str( $r['form_name'] ), (int) $r['form_id'] ),
			);
		}
		return $out;
	}

	/**
	 * A page of responses.
	 *
	 * @param array<string,mixed> $f      form, status (unread|read), search.
	 * @param int                 $offset Offset.
	 * @param int                 $limit  Rows.
	 * @return array{items:list<array<string,mixed>>,total:int}
	 */
	public static function entries( array $f, int $offset, int $limit ): array {
		global $wpdb;
		/** @var wpdb $wpdb */
		self::dashboard_class();
		$msg   = $wpdb->prefix . 'emsfb_msg_';
		$where = 'read_ <> 2';
		$vals  = array();
		$form  = self::num( $f['form'] ?? 0 );
		if ( $form > 0 ) {
			$where .= ' AND form_id = %d';
			$vals[] = $form;
		}
		$status = sanitize_key( self::str( $f['status'] ?? '' ) );
		if ( 'unread' === $status ) {
			$where .= ' AND read_ IN (0,3)';
		} elseif ( 'read' === $status ) {
			$where .= ' AND read_ IN (1,4)';
		}
		$q = trim( self::str( $f['search'] ?? '' ) );
		if ( '' !== $q ) {
			$where .= ' AND (track = %s OR content LIKE %s)';
			$vals[] = $q;
			$vals[] = '%' . $wpdb->esc_like( $q ) . '%';
		}
		// phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQL.NotPrepared -- table name; $where is built from placeholders.
		$total = (int) $wpdb->get_var( $vals ? $wpdb->prepare( "SELECT COUNT(*) FROM `{$msg}` WHERE {$where}", ...$vals ) : "SELECT COUNT(*) FROM `{$msg}` WHERE {$where}" );
		$rows  = $wpdb->get_results(
			$wpdb->prepare( "SELECT msg_id, form_id, track, `date`, read_, content, form_title_x FROM `{$msg}` WHERE {$where} ORDER BY `date` DESC, msg_id DESC LIMIT %d OFFSET %d", ...array_merge( $vals, array( $limit, $offset ) ) ),
			ARRAY_A
		);
		// phpcs:enable
		$forms = FLXF_Dashboard::forms();
		$items = array();
		foreach ( (array) $rows as $r ) {
			$items[] = self::entry_row( $r, $forms );
		}
		return array(
			'items' => $items,
			'total' => $total,
		);
	}

	/**
	 * One response with its answers and replies. Null when it is missing.
	 *
	 * @return array<string,mixed>|null
	 */
	public static function entry( int $id ): ?array {
		global $wpdb;
		/** @var wpdb $wpdb */
		self::dashboard_class();
		$row = $wpdb->get_row( $wpdb->prepare( 'SELECT * FROM %i WHERE msg_id = %d AND read_ <> 2', $wpdb->prefix . 'emsfb_msg_', $id ), ARRAY_A );
		if ( ! is_array( $row ) ) {
			return null;
		}
		$forms   = FLXF_Dashboard::forms();
		$out     = self::entry_row( $row, $forms );
		$items   = FLXF_Dashboard::decode_json( self::str( $row['content'] ) );
		$fields  = array();
		foreach ( FLXF_Dashboard::flatten( $items ) as $label => $value ) {
			$value    = (string) $value;
			$fields[] = array(
				'label'  => (string) $label,
				'value'  => $value,
				'isLink' => (bool) preg_match( '#^https?://#i', $value ),
			);
		}
		$out['ip']      = self::str( $row['ip'] ?? '' );
		$out['fields']  = $fields;
		$out['closed']  = 4 === self::num( $row['read_'] ?? 0 );
		$out['replies'] = self::replies( $id );
		return $out;
	}

	/**
	 * Marks responses read or unread.
	 *
	 * @param list<int> $ids  Response ids.
	 * @param bool      $read Read.
	 */
	public static function mark( array $ids, bool $read ): int {
		global $wpdb;
		/** @var wpdb $wpdb */
		$ids = self::ids( $ids );
		if ( ! $ids ) {
			return 0;
		}
		$in  = implode( ',', array_fill( 0, count( $ids ), '%d' ) );
		$msg = $wpdb->prefix . 'emsfb_msg_';
		// phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQL.NotPrepared -- table name; ids are placeholders.
		if ( $read ) {
			$n = (int) $wpdb->query( $wpdb->prepare( "UPDATE `{$msg}` SET read_ = IF(read_ = 3, 4, 1), read_date = %s, read_by = %s WHERE read_ IN (0,3) AND msg_id IN ({$in})", wp_date( 'Y-m-d H:i:s' ), (string) get_current_user_id(), ...$ids ) );
			$wpdb->query( $wpdb->prepare( 'UPDATE `' . $wpdb->prefix . "emsfb_rsp_` SET read_ = 1 WHERE msg_id IN ({$in})", ...$ids ) );
		} else {
			$n = (int) $wpdb->query( $wpdb->prepare( "UPDATE `{$msg}` SET read_ = IF(read_ = 4, 3, 0) WHERE read_ IN (1,4) AND msg_id IN ({$in})", ...$ids ) );
		}
		// phpcs:enable
		return $n;
	}

	/**
	 * Deletes responses and their replies.
	 *
	 * @param list<int> $ids Response ids.
	 */
	public static function delete_entries( array $ids ): int {
		global $wpdb;
		/** @var wpdb $wpdb */
		$ids = self::ids( $ids );
		if ( ! $ids ) {
			return 0;
		}
		$in = implode( ',', array_fill( 0, count( $ids ), '%d' ) );
		// phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQL.NotPrepared -- table names; ids are placeholders.
		$n = (int) $wpdb->query( $wpdb->prepare( 'DELETE FROM `' . $wpdb->prefix . "emsfb_msg_` WHERE msg_id IN ({$in})", ...$ids ) );
		$wpdb->query( $wpdb->prepare( 'DELETE FROM `' . $wpdb->prefix . "emsfb_rsp_` WHERE msg_id IN ({$in})", ...$ids ) );
		// phpcs:enable
		return $n;
	}

	/**
	 * Staff reply on a response (same row and notification as the classic panel reply).
	 *
	 * @return array<string,mixed>|WP_Error The response after the reply.
	 */
	public static function reply( int $id, string $message, ?bool $close ): array|WP_Error {
		global $wpdb;
		/** @var wpdb $wpdb */
		$message = trim( sanitize_textarea_field( $message ) );
		if ( '' === $message && null === $close ) {
			return self::invalid( 'message', __( 'Write a reply.', 'flx-forms' ) );
		}
		if ( strlen( $message ) > self::REPLY_MAX ) {
			$message = substr( $message, 0, self::REPLY_MAX );
		}
		$msg = $wpdb->prefix . 'emsfb_msg_';
		$row = $wpdb->get_row( $wpdb->prepare( 'SELECT msg_id, read_ FROM %i WHERE msg_id = %d AND read_ <> 2', $msg, $id ), ARRAY_A );
		if ( ! is_array( $row ) ) {
			return new WP_Error(
				'flxf_not_found',
				__( 'The response was not found.', 'flx-forms' ),
				array(
					'graphql_code' => 'NOT_FOUND',
					'status'       => 404,
				)
			);
		}
		$payload = array();
		if ( '' !== $message ) {
			$payload[] = array(
				'id_'   => 'message',
				'name'  => 'Message',
				'type'  => 'text',
				'value' => strtr( $message, array( '"' => "\u{201D}", '\\' => "\u{29F5}" ) ),
			);
		}
		if ( null !== $close ) {
			$payload[] = array(
				'id_'   => $close ? 'closed' : 'opened',
				'name'  => $close ? 'closed' : 'opened',
				'type'  => $close ? 'closed' : 'opened',
				'value' => '',
			);
		}
		$content = str_replace( '"', '\\"', (string) wp_json_encode( $payload, JSON_UNESCAPED_UNICODE ) );
		$ok      = $wpdb->insert(
			$wpdb->prefix . 'emsfb_rsp_',
			array(
				'ip'      => self::ip(),
				'content' => $content,
				'msg_id'  => $id,
				'rsp_by'  => get_current_user_id(),
				'read_'   => 1,
				'date'    => wp_date( 'Y-m-d H:i:s' ),
			)
		);
		if ( false === $ok ) {
			return new WP_Error(
				'flxf_reply_failed',
				__( 'The reply could not be saved.', 'flx-forms' ),
				array(
					'graphql_code' => 'INTERNAL',
					'status'       => 500,
				)
			);
		}
		$state = true === $close ? 4 : 1;
		$wpdb->update( $msg, array( 'read_' => $state ), array( 'msg_id' => $id ), array( '%d' ), array( '%d' ) );
		if ( '' !== $message && function_exists( 'get_efbFunction' ) ) {
			$efb = get_efbFunction();
			if ( is_object( $efb ) && method_exists( $efb, 'response_to_user_by_msd_id' ) ) {
				$pro = method_exists( $efb, 'is_efb_pro' ) ? $efb->is_efb_pro( 1 ) : false;
				$efb->response_to_user_by_msd_id( $id, $pro );
			}
		}
		$entry = self::entry( $id );
		return null === $entry ? array() : $entry;
	}

	/**
	 * Deletes a form and its responses (classic remove_id_Emsfb).
	 */
	public static function delete_form( int $id ): bool {
		global $wpdb;
		/** @var wpdb $wpdb */
		$r = $wpdb->delete( $wpdb->prefix . 'emsfb_form', array( 'form_id' => $id ), array( '%d' ) );
		if ( false === $r || 0 === $r ) {
			return false;
		}
		$ids = array_map( 'intval', (array) $wpdb->get_col( $wpdb->prepare( 'SELECT msg_id FROM %i WHERE form_id = %d', $wpdb->prefix . 'emsfb_msg_', $id ) ) );
		self::delete_entries( $ids );
		if ( class_exists( 'FLXF_Form_Service' ) ) {
			FLXF_Form_Service::purge( $id );
		}
		return true;
	}

	/**
	 * Copies a form (classic dup_efb). Returns the new form row or null.
	 *
	 * @return array<string,mixed>|null
	 */
	public static function duplicate_form( int $id ): ?array {
		global $wpdb;
		/** @var wpdb $wpdb */
		$table = $wpdb->prefix . 'emsfb_form';
		$row   = $wpdb->get_row( $wpdb->prepare( 'SELECT * FROM %i WHERE form_id = %d', $table, $id ), ARRAY_A );
		if ( ! is_array( $row ) ) {
			return null;
		}
		/* translators: %s: form name. */
		$name = sprintf( __( '%s (copy)', 'flx-forms' ), self::str( $row['form_name'] ) );
		$ok   = $wpdb->insert(
			$table,
			array(
				'form_name'        => $name,
				'form_structer'    => self::str( $row['form_structer'] ),
				'form_email'       => self::str( $row['form_email'] ),
				'form_created_by'  => (string) get_current_user_id(),
				'form_type'        => self::str( $row['form_type'] ),
				'form_create_date' => wp_date( 'Y-m-d H:i:s' ),
			)
		);
		if ( false === $ok ) {
			return null;
		}
		$new  = (int) $wpdb->insert_id;
		$page = self::forms( array( 'search' => (string) $new ), 0, 50 );
		foreach ( $page['items'] as $item ) {
			if ( $new === $item['databaseId'] ) {
				return $item;
			}
		}
		return null;
	}

	/**
	 * Replies on a response, oldest first.
	 *
	 * @return list<array<string,mixed>>
	 */
	private static function replies( int $id ): array {
		global $wpdb;
		/** @var wpdb $wpdb */
		$rows = $wpdb->get_results( $wpdb->prepare( 'SELECT rsp_id, content, `date`, rsp_by FROM %i WHERE msg_id = %d ORDER BY rsp_id ASC', $wpdb->prefix . 'emsfb_rsp_', $id ), ARRAY_A );
		$out  = array();
		foreach ( (array) $rows as $r ) {
			$items = FLXF_Dashboard::decode_json( self::str( $r['content'] ) );
			$text  = '';
			$files = array();
			$state = '';
			foreach ( (array) $items as $item ) {
				if ( ! is_array( $item ) ) {
					continue;
				}
				$type = sanitize_key( self::str( $item['type'] ?? '' ) );
				$val  = self::str( $item['value'] ?? '' );
				if ( in_array( $type, array( 'closed', 'opened' ), true ) ) {
					$state = $type;
					continue;
				}
				$url = esc_url_raw( self::str( $item['url'] ?? '' ) );
				if ( '' !== $url && ( 'allformat' === $type || '@file@' === $val ) ) {
					$files[] = $url;
					continue;
				}
				if ( '' !== $val && '@file@' !== $val ) {
					$text = '' === $text ? $val : $text . "\n" . $val;
				}
			}
			$by    = self::num( $r['rsp_by'] ?? 0 );
			$user  = $by > 0 ? get_userdata( $by ) : false;
			$out[] = array(
				'databaseId'  => (int) $r['rsp_id'],
				'body'        => sanitize_textarea_field( strtr( $text, array( "\u{201D}" => '"', "\u{29F5}" => '\\' ) ) ),
				'createdAt'   => self::moment( self::str( $r['date'] ) ),
				'attachments' => $files,
				'byStaff'     => $by > 0 || -1 === $by,
				'author'      => $user ? (string) $user->display_name : '',
				'state'       => $state,
			);
		}
		return $out;
	}

	/**
	 * @param array<string,mixed>            $r     Row.
	 * @param array<int,array<string,mixed>> $forms Forms by id.
	 * @return array<string,mixed>
	 */
	private static function entry_row( array $r, array $forms ): array {
		$fid = self::num( $r['form_id'] ?? 0 );
		$t   = isset( $forms[ $fid ]['title'] ) ? self::str( $forms[ $fid ]['title'] ) : self::title( self::str( $r['form_title_x'] ?? '' ), $fid );
		return array(
			'databaseId' => self::num( $r['msg_id'] ?? 0 ),
			'formId'     => $fid,
			'formTitle'  => $t,
			'trackCode'  => self::str( $r['track'] ?? '' ),
			'createdAt'  => self::moment( self::str( $r['date'] ?? '' ) ),
			'read'       => ! FLXF_Dashboard::is_unread( $r['read_'] ?? 0 ),
			'preview'    => FLXF_Dashboard::preview( FLXF_Dashboard::decode_json( self::str( $r['content'] ?? '' ) ) ),
		);
	}

	/**
	 * @param array<mixed> $ids Ids.
	 * @return list<int>
	 */
	private static function ids( array $ids ): array {
		return array_values( array_unique( array_filter( array_map( static fn( $v ) => self::num( $v ), $ids ), static fn( int $v ) => $v > 0 ) ) );
	}

	private static function title( string $name, int $id ): string {
		$t = trim( html_entity_decode( wp_strip_all_tags( $name ), ENT_QUOTES | ENT_HTML5, 'UTF-8' ) );
		return '' !== $t ? $t : '#' . $id;
	}

	private static function ip(): string {
		$ip = isset( $_SERVER['REMOTE_ADDR'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ) ) : '';
		return (string) filter_var( $ip, FILTER_VALIDATE_IP ) ? $ip : '';
	}

	/**
	 * Site-local MySQL time as FlxDateTime.
	 *
	 * @return array<string,mixed>
	 */
	private static function moment( string $mysql ): array {
		$gmt = '' !== $mysql ? get_gmt_from_date( $mysql ) : '';
		$out = flx_gql_datetime( '' !== $gmt ? $gmt : time() );
		return is_array( $out ) ? $out : array(
			'iso'       => gmdate( 'Y-m-d\TH:i:s\Z' ),
			'timestamp' => time(),
			'label'     => '',
			'relative'  => '',
		);
	}

	private static function invalid( string $field, string $message ): WP_Error {
		return new WP_Error(
			'flxf_invalid',
			$message,
			array(
				'graphql_code' => 'INVALID_INPUT',
				'field'        => $field,
				'status'       => 400,
			)
		);
	}

	private static function str( mixed $value ): string {
		return is_scalar( $value ) ? (string) $value : '';
	}

	private static function num( mixed $value ): int {
		if ( is_int( $value ) ) {
			return $value;
		}
		if ( is_float( $value ) || ( is_string( $value ) && is_numeric( $value ) ) ) {
			return (int) $value;
		}
		return 0;
	}
}
