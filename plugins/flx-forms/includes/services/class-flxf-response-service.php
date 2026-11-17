<?php
/**
 * Panel responses: list, one response with its replies, read state, delete, admin reply.
 *
 * One service for the three callers. The FluxApp REST routes (`flx-forms/v1/responses`) and the
 * classic panel AJAX handlers keep their own output and call the pieces they share (the list
 * query, the read update, the reply store); the GraphQL screen reads and writes through the same
 * methods. Tables are `emsfb_msg_` (submissions) and `emsfb_rsp_` (replies).
 *
 * @package FLX_Forms
 */

defined( 'ABSPATH' ) || exit;

/**
 * Panel responses.
 *
 * @phpstan-type FlxfMoment array{iso:string,timestamp:int,label:string,relative:string}
 * @phpstan-type FlxfMsgRow array{msg_id:string,form_id:string,track:string,ip:string,form_title_x:string,content:string,date:string,read_date:string|null,read_:string,read_by:string}
 * @phpstan-type FlxfPageArgs array{form:int,unread:bool,status:string,search:string,replies:bool}
 * @phpstan-type FlxfPage array{rows:list<array<mixed>>,total:int}
 * @phpstan-type FlxfFormRow array{id:string,title:string,formType:string,shortcode:string,createdAt:FlxfMoment|null,total:int,unread:int}
 * @phpstan-type FlxfField array{label:string,value:string,file:bool}
 * @phpstan-type FlxfReplyRow array{id:string,by:string,author:string,kind:string,body:string,attachments:list<string>,createdAt:FlxfMoment}
 * @phpstan-type FlxfResponse array{id:string,formId:string,formTitle:string,formType:string,trackCode:string,status:string,createdAt:FlxfMoment,readAt:FlxfMoment|null,preview:string,fields:list<FlxfField>,replies:list<FlxfReplyRow>,open:bool}
 * @phpstan-type FlxfResponseItem array{id:string,formId:string,formTitle:string,formType:string,trackCode:string,status:string,createdAt:FlxfMoment,preview:string,fields:list<FlxfField>}
 */
class FLXF_Response_Service {

	/**
	 * `read_` of a submission: 0 new, 1 read, 2 unpaid payment draft, 3 new follow-up, 4 read follow-up.
	 *
	 * @var array<int,string>
	 */
	private const STATUS = array(
		0 => 'unread',
		1 => 'read',
		2 => 'pending_payment',
		3 => 'unread_followup',
		4 => 'read_followup',
	);

	public const MAX_BULK = 200;

	public const TEXT_MAX = 5000;

	/**
	 * Raw submission page. Same query the FluxApp list uses (payment drafts are left out).
	 *
	 * @param FlxfPageArgs $args   Filters.
	 * @param int          $offset Rows to skip.
	 * @param int          $limit  Page size.
	 * @return FlxfPage
	 */
	public static function page( array $args, int $offset, int $limit ): array {
		global $wpdb;
		/** @var wpdb $wpdb */
		$table = $wpdb->prefix . 'emsfb_msg_';
		$where = array( 'read_ <> 2' );
		if ( $args['form'] > 0 ) {
			$where[] = $wpdb->prepare( 'form_id = %d', $args['form'] );
		}
		if ( $args['unread'] ) {
			$where[] = 'read_ IN (0, 3)';
		}
		$status = array_search( $args['status'], self::STATUS, true );
		if ( false !== $status ) {
			$where[] = $wpdb->prepare( 'read_ = %d', (int) $status );
		}
		if ( '' !== $args['search'] ) {
			$like = '%' . $wpdb->esc_like( $args['search'] ) . '%';
			if ( $args['replies'] ) {
				$where[] = $wpdb->prepare( '(content LIKE %s OR track LIKE %s OR form_title_x LIKE %s OR msg_id IN (SELECT msg_id FROM %i WHERE content LIKE %s))', $like, $like, $like, $wpdb->prefix . 'emsfb_rsp_', $like );
			} else {
				$where[] = $wpdb->prepare( '(content LIKE %s OR track LIKE %s OR form_title_x LIKE %s)', $like, $like, $like );
			}
		}
		$w     = implode( ' AND ', $where );
		$total = (int) $wpdb->get_var( 'SELECT COUNT(*) FROM `' . $table . '` WHERE ' . $w );
		$rows  = $wpdb->get_results( 'SELECT msg_id, form_id, track, content, date, read_, form_title_x FROM `' . $table . '` WHERE ' . $w . ' ORDER BY date DESC, msg_id DESC' . $wpdb->prepare( ' LIMIT %d OFFSET %d', $limit, $offset ), ARRAY_A );
		return array(
			'rows'  => (array) $rows,
			'total' => $total,
		);
	}

	/**
	 * Raw submission row.
	 *
	 * @return array<mixed>|null
	 */
	public static function row( int $id ): ?array {
		global $wpdb;
		/** @var wpdb $wpdb */
		$table = $wpdb->prefix . 'emsfb_msg_';
		$row   = $wpdb->get_row( $wpdb->prepare( 'SELECT * FROM %i WHERE msg_id = %d', $table, $id ), ARRAY_A );
		return is_array( $row ) ? $row : null;
	}

	/**
	 * Raw submission row by tracking code.
	 *
	 * @return array<mixed>|null
	 */
	public static function row_by_track( string $code ): ?array {
		global $wpdb;
		/** @var wpdb $wpdb */
		$table = $wpdb->prefix . 'emsfb_msg_';
		$row   = $wpdb->get_row( $wpdb->prepare( 'SELECT * FROM %i WHERE track = %s LIMIT 1', $table, $code ), ARRAY_A );
		return is_array( $row ) ? $row : null;
	}

	/**
	 * Form id => name.
	 *
	 * @return array<int,string>
	 */
	public static function forms_map(): array {
		global $wpdb;
		/** @var wpdb $wpdb */
		$out   = array();
		$table = $wpdb->prefix . 'emsfb_form';
		$rows  = $wpdb->get_results( $wpdb->prepare( 'SELECT form_id, form_name FROM %i', $table ), ARRAY_A );
		foreach ( (array) $rows as $row ) {
			$out[ FLXF_Submission_Service::num( $row['form_id'] ?? 0 ) ] = FLXF_Submission_Service::str( $row['form_name'] ?? '' );
		}
		return $out;
	}

	/**
	 * Forms of the panel with how many responses each holds. Inactive forms (status other than 1)
	 * are left out, like the classic list. Forms with new responses come first.
	 *
	 * @return list<FlxfFormRow>
	 */
	public static function forms(): array {
		global $wpdb;
		/** @var wpdb $wpdb */
		$form_t = $wpdb->prefix . 'emsfb_form';
		$msg_t  = $wpdb->prefix . 'emsfb_msg_';
		$forms  = $wpdb->get_results( $wpdb->prepare( 'SELECT form_id, form_name, form_type, form_create_date, status FROM %i ORDER BY form_id DESC', $form_t ), ARRAY_A );
		$counts = $wpdb->get_results( $wpdb->prepare( 'SELECT form_id, COUNT(*) AS total, SUM(CASE WHEN read_ IN (0, 3) THEN 1 ELSE 0 END) AS unread FROM %i WHERE read_ <> 2 GROUP BY form_id', $msg_t ), ARRAY_A );
		$by     = array();
		foreach ( (array) $counts as $c ) {
			$by[ FLXF_Submission_Service::num( $c['form_id'] ?? 0 ) ] = array(
				'total'  => FLXF_Submission_Service::num( $c['total'] ?? 0 ),
				'unread' => FLXF_Submission_Service::num( $c['unread'] ?? 0 ),
			);
		}
		$news = array();
		$old  = array();
		foreach ( (array) $forms as $f ) {
			if ( isset( $f['status'] ) && 1 !== FLXF_Submission_Service::num( $f['status'] ) ) {
				continue;
			}
			$id    = FLXF_Submission_Service::num( $f['form_id'] ?? 0 );
			$stat  = $by[ $id ] ?? array(
				'total'  => 0,
				'unread' => 0,
			);
			$made  = FLXF_Submission_Service::str( $f['form_create_date'] ?? '' );
			$row   = array(
				'id'        => (string) $id,
				'title'     => FLXF_Submission_Service::str( $f['form_name'] ?? '' ),
				'formType'  => self::form_type( FLXF_Submission_Service::str( $f['form_type'] ?? '' ) ),
				'shortcode' => '[EMS_Form_Builder id=' . $id . ']',
				'createdAt' => '' !== $made ? FLXF_Submission_Service::moment( $made ) : null,
				'total'     => $stat['total'],
				'unread'    => $stat['unread'],
			);
			if ( $stat['unread'] > 0 ) {
				$news[] = $row;
			} else {
				$old[] = $row;
			}
		}
		return array_merge( $news, $old );
	}

	/**
	 * One response with its fields and replies. Payment drafts (read_ 2) are not responses.
	 *
	 * @param array<mixed> $row Submission row.
	 * @return FlxfResponse
	 */
	public static function view( array $row ): array {
		$id      = FLXF_Submission_Service::num( $row['msg_id'] ?? 0 );
		$form_id = FLXF_Submission_Service::num( $row['form_id'] ?? 0 );
		$forms   = self::forms_map();
		$content = FLXF_Submission_Service::str( $row['content'] ?? '' );
		$fields  = self::fields( $content );
		$replies = self::replies( $id );
		$open    = true;
		foreach ( $replies as $reply ) {
			if ( 'closed' === $reply['kind'] ) {
				$open = false;
			} elseif ( 'opened' === $reply['kind'] ) {
				$open = true;
			}
		}
		$read_date = FLXF_Submission_Service::str( $row['read_date'] ?? '' );
		$status    = self::status( $row['read_'] ?? 0 );
		return array(
			'id'        => (string) $id,
			'formId'    => (string) $form_id,
			'formTitle' => $forms[ $form_id ] ?? FLXF_Submission_Service::str( $row['form_title_x'] ?? '' ),
			'formType'  => self::form_type( self::type_of( $form_id ) ),
			'trackCode' => FLXF_Submission_Service::str( $row['track'] ?? '' ),
			'status'    => $status,
			'createdAt' => FLXF_Submission_Service::moment( FLXF_Submission_Service::str( $row['date'] ?? '' ) ),
			'readAt'    => ( 'unread' !== $status && 'unread_followup' !== $status && '' !== $read_date ) ? FLXF_Submission_Service::moment( $read_date ) : null,
			'preview'   => self::preview( $fields ),
			'fields'    => $fields,
			'replies'   => $replies,
			'open'      => $open,
		);
	}

	/**
	 * List item of a raw row from page().
	 *
	 * @param array<mixed> $row   Submission row.
	 * @param array<int,string>   $forms Form names by id.
	 * @param array<int,string>   $types Form types by id.
	 * @return FlxfResponseItem
	 */
	public static function item( array $row, array $forms, array $types ): array {
		$form_id = FLXF_Submission_Service::num( $row['form_id'] ?? 0 );
		$fields  = self::fields( FLXF_Submission_Service::str( $row['content'] ?? '' ) );
		return array(
			'id'        => (string) FLXF_Submission_Service::num( $row['msg_id'] ?? 0 ),
			'formId'    => (string) $form_id,
			'formTitle' => $forms[ $form_id ] ?? FLXF_Submission_Service::str( $row['form_title_x'] ?? '' ),
			'formType'  => self::form_type( $types[ $form_id ] ?? '' ),
			'trackCode' => FLXF_Submission_Service::str( $row['track'] ?? '' ),
			'status'    => self::status( $row['read_'] ?? 0 ),
			'createdAt' => FLXF_Submission_Service::moment( FLXF_Submission_Service::str( $row['date'] ?? '' ) ),
			'preview'   => self::preview( $fields ),
			'fields'    => $fields,
		);
	}

	/**
	 * Form id => type for every form.
	 *
	 * @return array<int,string>
	 */
	public static function types_map(): array {
		global $wpdb;
		/** @var wpdb $wpdb */
		$out  = array();
		$rows = $wpdb->get_results( $wpdb->prepare( 'SELECT form_id, form_type FROM %i', $wpdb->prefix . 'emsfb_form' ), ARRAY_A );
		foreach ( (array) $rows as $row ) {
			$out[ FLXF_Submission_Service::num( $row['form_id'] ?? 0 ) ] = FLXF_Submission_Service::str( $row['form_type'] ?? '' );
		}
		return $out;
	}

	/**
	 * Mark one submission read, the way the FluxApp does: new becomes read, a new follow-up becomes
	 * a read follow-up. Rows that are already read are left alone.
	 *
	 * @param bool $replies Also mark the replies read (what opening the ticket does in the panel).
	 * @return int|null Submission id, or null when it does not exist.
	 */
	public static function mark_read( int $id, bool $replies = false ): ?int {
		global $wpdb;
		/** @var wpdb $wpdb */
		$table = $wpdb->prefix . 'emsfb_msg_';
		$row   = $wpdb->get_row( $wpdb->prepare( 'SELECT msg_id, read_ FROM %i WHERE msg_id = %d', $table, $id ), ARRAY_A );
		if ( ! is_array( $row ) ) {
			return null;
		}
		$read = FLXF_Submission_Service::num( $row['read_'] ?? 0 );
		$next = 3 === $read ? 4 : 1;
		if ( 0 === $read || 3 === $read ) {
			$wpdb->update(
				$table,
				array(
					'read_'     => $next,
					'read_by'   => wp_get_current_user()->user_login,
					'read_date' => current_time( 'mysql' ),
				),
				array( 'msg_id' => FLXF_Submission_Service::num( $row['msg_id'] ?? 0 ) )
			);
			self::purge( array( $id ) );
		}
		if ( $replies ) {
			$wpdb->update( $wpdb->prefix . 'emsfb_rsp_', array( 'read_' => 1 ), array( 'msg_id' => $id, 'read_' => 0 ), array( '%d' ), array( '%d', '%d' ) );
		}
		return FLXF_Submission_Service::num( $row['msg_id'] ?? 0 );
	}

	/**
	 * Delete submissions and their replies. Returns how many submissions went.
	 *
	 * @param array<mixed> $ids Submission ids.
	 */
	public static function delete( array $ids ): int {
		$ids = self::ids( $ids );
		if ( ! $ids ) {
			return 0;
		}
		global $wpdb;
		/** @var wpdb $wpdb */
		$list  = implode( ',', array_map( 'intval', $ids ) );
		$msg   = $wpdb->prefix . 'emsfb_msg_';
		$rsp   = $wpdb->prefix . 'emsfb_rsp_';
		$gone  = (int) $wpdb->query( "DELETE FROM `{$msg}` WHERE msg_id IN ({$list})" ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- integers only.
		if ( $gone > 0 ) {
			$wpdb->query( "DELETE FROM `{$rsp}` WHERE msg_id IN ({$list})" ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- integers only.
			self::purge( $ids );
		}
		return $gone;
	}

	/**
	 * Delete uploaded files (`emsfb-PLG-*`) that no stored response points to. The classic panel's
	 * "clear unused files" tool.
	 */
	public static function clear_orphan_files(): void {
		global $wpdb;
		/** @var wpdb $wpdb */
		$table  = $wpdb->prefix . 'emsfb_msg_';
		$value  = $wpdb->get_results( $wpdb->prepare( 'SELECT content FROM %i', $table ) );
		$urls   = array();
		foreach ( (array) $value as $v ) {
			if ( ! is_string( $v->content ) || false === strpos( $v->content, 'url' ) ) {
				continue;
			}
			$json = json_decode( str_replace( '\\', '', $v->content ) );
			if ( ! is_array( $json ) && ! is_object( $json ) ) {
				continue;
			}
			foreach ( (array) $json as $row ) {
				if ( ! is_array( $row ) && ! is_object( $row ) ) {
					continue;
				}
				foreach ( (array) $row as $key => $val ) {
					if ( 'url' === $key && is_string( $val ) && '' !== $val ) {
						$urls[] = $val;
					}
				}
			}
		}
		$upload_dir = wp_upload_dir();
		$files      = (array) list_files( $upload_dir['basedir'] );
		$url_text   = (string) wp_json_encode( $urls );
		foreach ( $files as $file ) {
			if ( is_string( $file ) && false !== strpos( $file, 'emsfb-PLG-' ) ) {
				$name = (string) strrchr( $file, '/' );
				if ( false === strpos( $url_text, $name ) ) {
					wp_delete_file( $file );
				}
			}
		}
	}

	/**
	 * Link to the CSV export (admin-post, bytes). The export itself stays the dashboard's handler, so
	 * its capability check, nonce and file are unchanged; this only hands the screen a link that
	 * carries the nonce. The range is the longest the export allows.
	 */
	public static function export_url( int $form_id ): string {
		$args = array(
			'action'   => 'flxf_dash_csv',
			'_wpnonce' => wp_create_nonce( 'flxf_dash_csv' ),
			'from'     => wp_date( 'Y-m-d', time() - 730 * DAY_IN_SECONDS ),
			'to'       => wp_date( 'Y-m-d' ),
		);
		if ( $form_id > 0 ) {
			$args['form'] = $form_id;
		}
		return add_query_arg( $args, admin_url( 'admin-post.php' ) );
	}

	/**
	 * Store an administrator reply exactly like the classic panel does: ticket state markers move the
	 * submission to read (open) or closed, the reply row is written, reservations are released, the
	 * submission is marked read and the visitor gets the notification mail.
	 *
	 * @param int          $id      Submission id.
	 * @param string       $stored  Reply content in the stored (quote-escaped JSON) form.
	 * @param list<string> $urls    Reserved attachment URLs.
	 * @param string       $ip      Client address.
	 * @return bool False when the reply row could not be written.
	 */
	public static function store_reply( int $id, string $stored, array $urls, string $ip ): bool {
		global $wpdb;
		/** @var wpdb $wpdb */
		$msg = $wpdb->prefix . 'emsfb_msg_';
		if ( strpos( $stored, '"type\":\"closed\"' ) ) {
			$wpdb->update( $msg, array( 'read_' => 4 ), array( 'msg_id' => $id ) );
		} elseif ( strpos( $stored, '"type\":\"opened\"' ) ) {
			$wpdb->update( $msg, array( 'read_' => 1 ), array( 'msg_id' => $id ) );
		}
		$ok = $wpdb->insert(
			$wpdb->prefix . 'emsfb_rsp_',
			array(
				'ip'      => $ip,
				'content' => $stored,
				'msg_id'  => $id,
				'rsp_by'  => get_current_user_id(),
				'read_'   => 1,
				'date'    => wp_date( 'Y-m-d H:i:s' ),
			)
		);
		if ( false === $ok ) {
			return false;
		}
		if ( $urls && class_exists( '\Emsfb\Upload_Guard' ) ) {
			\Emsfb\Upload_Guard::release_pending_uploads( $urls );
			\Emsfb\Upload_Guard::release_response_attachment_reservations( $urls );
		}
		$wpdb->update( $msg, array( 'read_' => 1 ), array( 'msg_id' => $id ) );
		$func = \Emsfb::get_efbFunction();
		$func->response_to_user_by_msd_id( $id, $func->is_efb_pro( 1 ) );
		self::purge( array( $id ) );
		return true;
	}

	/**
	 * Reply from the React screen. Same payload the classic panel builds: a message row, optional
	 * file rows and the open / closed marker. Attachments must be reserved for this ticket by the
	 * upload endpoint, exactly like the classic reply.
	 *
	 * @param array<mixed> $input `submissionId`, `message`, `attachments`, `state`.
	 * @return array{response:FlxfResponse}|WP_Error
	 */
	public static function reply( array $input ): array|WP_Error {
		$id    = FLXF_Submission_Service::num( $input['submissionId'] ?? 0 );
		$state = FLXF_Submission_Service::str( $input['state'] ?? 'reply' );
		$row   = $id > 0 ? self::row( $id ) : null;
		if ( null === $row ) {
			return self::error( 'NOT_FOUND', __( 'Response not found.', 'flx-forms' ), 404 );
		}
		$raw = FLXF_Submission_Service::str( $input['message'] ?? '' );
		if ( '' !== $raw && $raw !== wp_strip_all_tags( $raw ) ) {
			return self::invalid( 'message', __( 'HTML is not allowed in a reply.', 'flx-forms' ) );
		}
		$body = sanitize_textarea_field( $raw );
		if ( strlen( $body ) > self::TEXT_MAX ) {
			$body = substr( $body, 0, self::TEXT_MAX );
		}
		if ( 'reply' === $state && '' === $body ) {
			return self::invalid( 'message', __( 'Write a reply.', 'flx-forms' ) );
		}
		$user  = wp_get_current_user();
		$by    = $user->exists() ? $user->display_name : '';
		$track = FLXF_Submission_Service::str( $row['track'] ?? '' );
		$files = array();
		foreach ( (array) ( $input['attachments'] ?? array() ) as $url ) {
			$url = esc_url_raw( FLXF_Submission_Service::str( $url ) );
			if ( '' === $url ) {
				continue;
			}
			if ( ! class_exists( '\Emsfb\Upload_Guard' ) || ! \Emsfb\Upload_Guard::response_attachment_is_reserved( $url, $id, $track ) ) {
				return self::invalid( 'attachments', __( 'The response attachment could not be verified. Please attach the file again.', 'flx-forms' ) );
			}
			$files[] = $url;
		}
		$rows = array();
		if ( 'close' === $state || 'open' === $state ) {
			$rows[] = array(
				'id_'    => 'message',
				'name'   => 'message',
				'type'   => 'close' === $state ? 'closed' : 'opened',
				'amount' => 0,
				'value'  => 'close' === $state ? __( 'The ticket was closed.', 'flx-forms' ) : __( 'The ticket was opened again.', 'flx-forms' ),
				'by'     => $by,
			);
		}
		if ( '' !== $body ) {
			$rows[] = array(
				'id_'    => 'message',
				'name'   => 'message',
				'type'   => 'text',
				'amount' => 0,
				'value'  => $body,
				'by'     => $by,
			);
		}
		foreach ( $files as $url ) {
			$rows[] = array(
				'id_'   => 'file',
				'name'  => 'File',
				'type'  => 'allformat',
				'value' => '@file@',
				'url'   => $url,
				'by'    => $by,
			);
		}
		if ( ! $rows ) {
			return self::invalid( 'message', __( 'Write a reply.', 'flx-forms' ) );
		}
		if ( ! self::store_reply( $id, FLXF_Submission_Service::encode_rows( $rows ), $files, FLXF_Submission_Service::ip() ) ) {
			return self::error( 'INTERNAL', __( 'Something went wrong, please refresh the page.', 'flx-forms' ), 500 );
		}
		return array( 'response' => self::view( self::row( $id ) ?: $row ) );
	}

	/**
	 * Submission ids from a raw list: positive and unique.
	 *
	 * @param array<mixed> $ids Raw ids.
	 * @return list<int>
	 */
	public static function ids( array $ids ): array {
		$out = array();
		foreach ( $ids as $id ) {
			$id = FLXF_Submission_Service::num( $id );
			if ( $id > 0 ) {
				$out[ $id ] = $id;
			}
		}
		return array_values( $out );
	}

	/**
	 * Drop the GraphQL cache tags of the changed submissions.
	 *
	 * @param array<mixed> $ids Submission ids.
	 */
	public static function purge( array $ids ): void {
		if ( ! function_exists( 'flx_gql_purge' ) ) {
			return;
		}
		$tags = array( 'flx-forms:responses', 'flx-forms:list' );
		foreach ( $ids as $id ) {
			$tags[] = 'flx-forms:response:' . (string) FLXF_Submission_Service::num( $id );
		}
		flx_gql_purge( $tags );
	}

	/**
	 * Fields of a stored submission for the panel. File fields carry their URL.
	 *
	 * @return list<FlxfField>
	 */
	public static function fields( string $content ): array {
		$rows = FLXF_Submission_Service::decode_json( $content );
		if ( ! is_array( $rows ) ) {
			return array();
		}
		$out = array();
		foreach ( $rows as $row ) {
			if ( ! is_array( $row ) ) {
				continue;
			}
			$type = sanitize_key( FLXF_Submission_Service::str( $row['type'] ?? '' ) );
			if ( in_array( $type, array( 'w_link', 'closed', 'opened' ), true ) ) {
				continue;
			}
			$label = sanitize_text_field( FLXF_Submission_Service::str( $row['name'] ?? $row['label'] ?? $row['id_'] ?? '' ) );
			if ( 'payment' === $type ) {
				$total = FLXF_Submission_Service::str( $row['total'] ?? '' );
				$how   = FLXF_Submission_Service::str( $row['paymentmethod'] ?? '' );
				$value = trim( $total . ' ' . $how );
				if ( '' !== $value ) {
					$out[] = array(
						'label' => '' !== $label ? $label : __( 'Payment', 'flx-forms' ),
						'value' => sanitize_text_field( $value ),
						'file'  => false,
					);
				}
				continue;
			}
			$raw  = FLXF_Submission_Service::str( $row['value'] ?? '' );
			$file = '@file@' === $raw;
			if ( $file ) {
				$value = esc_url_raw( FLXF_Submission_Service::str( $row['url'] ?? '' ) );
			} else {
				$value = wp_strip_all_tags( $raw );
				$value = (string) preg_replace( '/@efb[^#]*#/', ' ', str_replace( '@efb!', ', ', $value ) );
				$value = trim( rtrim( trim( $value ), ',' ) );
			}
			if ( '' === $value ) {
				continue;
			}
			$out[] = array(
				'label' => $label,
				'value' => $value,
				'file'  => $file,
			);
		}
		return $out;
	}

	/**
	 * Replies of one submission, oldest first.
	 *
	 * @return list<FlxfReplyRow>
	 */
	public static function replies( int $id ): array {
		global $wpdb;
		/** @var wpdb $wpdb */
		$rows = $wpdb->get_results( $wpdb->prepare( 'SELECT * FROM %i WHERE msg_id = %d ORDER BY rsp_id ASC', $wpdb->prefix . 'emsfb_rsp_', $id ), ARRAY_A );
		$out  = array();
		foreach ( (array) $rows as $row ) {
			$by_id  = FLXF_Submission_Service::num( $row['rsp_by'] ?? 0 );
			$name   = __( 'Guest', 'flx-forms' );
			$author = 'guest';
			if ( $by_id > 0 ) {
				$user   = get_user_by( 'id', $by_id );
				$name   = $user instanceof WP_User ? $user->display_name : __( 'Guest', 'flx-forms' );
				$author = 'user';
			} elseif ( -1 === $by_id ) {
				$name   = __( 'Support', 'flx-forms' );
				$author = 'support';
			}
			$kind  = 'reply';
			$body  = '';
			$files = array();
			$items = FLXF_Submission_Service::decode_json( FLXF_Submission_Service::str( $row['content'] ?? '' ) );
			if ( is_array( $items ) && array_is_list( $items ) ) {
				foreach ( $items as $item ) {
					if ( ! is_array( $item ) ) {
						continue;
					}
					$type = sanitize_key( FLXF_Submission_Service::str( $item['type'] ?? '' ) );
					$val  = FLXF_Submission_Service::str( $item['value'] ?? '' );
					if ( 'closed' === $type || 'opened' === $type ) {
						$kind = $type;
						$body = sanitize_textarea_field( $val );
						continue;
					}
					$url = esc_url_raw( FLXF_Submission_Service::str( $item['url'] ?? '' ) );
					if ( '' !== $url && ( 'allformat' === $type || '@file@' === $val ) ) {
						$files[] = $url;
					} elseif ( '' !== $val && '@file@' !== $val ) {
						$body = sanitize_textarea_field( $val );
					}
				}
			} else {
				$parts = self::reply_text( FLXF_Submission_Service::str( $row['content'] ?? '' ) );
				$body  = $parts;
			}
			$out[] = array(
				'id'          => (string) FLXF_Submission_Service::num( $row['rsp_id'] ?? 0 ),
				'by'          => $name,
				'author'      => $author,
				'kind'        => $kind,
				'body'        => $body,
				'attachments' => $files,
				'createdAt'   => FLXF_Submission_Service::moment( FLXF_Submission_Service::str( $row['date'] ?? '' ) ),
			);
		}
		return $out;
	}

	/**
	 * Plain text of an old-format reply (a bare string).
	 */
	private static function reply_text( string $content ): string {
		$decoded = FLXF_Submission_Service::decode_json( $content );
		return sanitize_textarea_field( is_string( $decoded ) ? $decoded : $content );
	}

	/**
	 * Short text of the first filled fields, for the list.
	 *
	 * @param list<FlxfField> $fields Fields.
	 */
	public static function preview( array $fields ): string {
		$bits = array();
		foreach ( $fields as $field ) {
			if ( $field['file'] || '' === $field['value'] ) {
				continue;
			}
			$bits[] = '' !== $field['label'] ? $field['label'] . ': ' . $field['value'] : $field['value'];
			if ( count( $bits ) >= 3 ) {
				break;
			}
		}
		$text = implode( ' | ', $bits );
		return mb_strlen( $text ) > 140 ? mb_substr( $text, 0, 140 ) . '…' : $text;
	}

	/**
	 * Status word of a `read_` value.
	 */
	public static function status( mixed $read ): string {
		return self::STATUS[ FLXF_Submission_Service::num( $read ) ] ?? 'read';
	}

	/**
	 * Form type as the GraphQL enum value. Unknown types read as a plain form.
	 */
	public static function form_type( string $type ): string {
		return in_array( $type, array( 'form', 'survey', 'subscribe', 'payment', 'register', 'login' ), true ) ? $type : 'form';
	}

	private static function type_of( int $form_id ): string {
		/** @var array<int,string>|null $types */
		static $types = null;
		if ( null === $types ) {
			$types = self::types_map();
		}
		return $types[ $form_id ] ?? '';
	}

	/**
	 * @return array{graphql_code:string,field:string,status:int}
	 */
	private static function data( string $field ): array {
		return array(
			'graphql_code' => 'INVALID_INPUT',
			'field'        => $field,
			'status'       => 400,
		);
	}

	private static function invalid( string $field, string $message ): WP_Error {
		return new WP_Error( 'flxf_invalid', $message, self::data( $field ) );
	}

	private static function error( string $code, string $message, int $status ): WP_Error {
		return new WP_Error(
			'flxf_' . strtolower( $code ),
			$message,
			array(
				'graphql_code' => $code,
				'status'       => $status,
			)
		);
	}
}
