<?php
/**
 * Public submit, track and track-reply.
 *
 * The classic handlers print JSON and exit, so this service writes the same tables and then
 * calls the same post-save methods (mail, SMS, webhooks, survey cache). REST bytes stay put.
 *
 * @package FLX_Forms
 */

defined( 'ABSPATH' ) || exit;

/**
 * GraphQL submission, tracking and replies.
 *
 * @phpstan-type FlxfSubmission array{id:string,trackCode:string}
 * @phpstan-type FlxfSubmitResult array{submission:FlxfSubmission}
 * @phpstan-type FlxfAnswer array{label:string,value:string}
 * @phpstan-type FlxfMoment array{iso:string,timestamp:int,label:string,relative:string}
 * @phpstan-type FlxfReplyView array{body:string,createdAt:FlxfMoment,attachments:list<string>}
 * @phpstan-type FlxfTrackView array{trackCode:string,formTitle:string,submittedAt:FlxfMoment,fields:list<FlxfAnswer>,replies:list<FlxfReplyView>}
 */
class FLXF_Submission_Service {

	private const SUBMIT_MAX    = 8;
	private const SUBMIT_WINDOW = 600;
	private const TEXT_MAX      = 5000;

	/**
	 * Field types whose value is a file URL from the upload endpoint.
	 *
	 * @var list<string>
	 */
	private const FILE_TYPES = array(
		'file',
		'dadfile',
		'file-drop',
		'audio',
		'video',
		'screen',
		'audio_recorder',
		'video_recorder',
		'screen_recorder',
		'image',
		'document',
		'media',
		'allformat',
		'zip',
	);

	/**
	 * Choice fields. A value has to be one of the field's options.
	 *
	 * @var list<string>
	 */
	private const CHOICE_TYPES = array(
		'select',
		'radio',
		'checkbox',
		'multiselect',
		'payselect',
		'payradio',
		'paycheckbox',
		'paymultiselect',
		'chlradio',
		'imgradio',
		'chlcheckbox',
		'trmcheckbox',
	);

	/**
	 * Types a visitor does not fill in.
	 *
	 * @var list<string>
	 */
	private const SKIP_TYPES = array( 'step', 'heading', 'html', 'link', 'raw', 'stripe', 'maps', 'password', 'esign', 'persiapay', 'paypal', 'booking', 'table_matrix' );

	/**
	 * @param array<string,mixed> $input Mutation input.
	 * @return FlxfSubmitResult|WP_Error
	 */
	public static function submit( array $input ): array|WP_Error {
		$limited = self::limit( 'flxf_submit', self::SUBMIT_MAX, self::SUBMIT_WINDOW, __( 'Too many submissions. Wait a moment and try again.', 'flx-forms' ) );
		if ( $limited instanceof WP_Error ) {
			return $limited;
		}
		if ( '' !== trim( self::str( $input['honeypot'] ?? '' ) ) ) {
			return self::invalid( 'honeypot', __( 'The form could not be sent.', 'flx-forms' ) );
		}
		if ( function_exists( 'flx_captcha_gql_verify' ) ) {
			$token = isset( $input['captchaToken'] ) && is_string( $input['captchaToken'] ) && '' !== $input['captchaToken']
				? $input['captchaToken']
				: null;
			flx_captcha_gql_verify( 'flxf_submit', $token, 'captchaToken' );
		}

		$id   = absint( self::num( $input['formId'] ?? 0 ) );
		$load = FLXF_Form_Service::load( $id );
		if ( null === $load ) {
			return self::error( 'NOT_FOUND', __( 'The form was not found.', 'flx-forms' ), 404 );
		}
		if ( FLXF_Form_Service::is_private( $load['structure'] ) && ! is_user_logged_in() ) {
			return self::error( 'NOT_FOUND', __( 'The form was not found.', 'flx-forms' ), 404 );
		}
		$type = self::str( $load['row']['form_type'] );
		if ( ! in_array( $type, array( 'form', 'survey', 'subscribe' ), true ) ) {
			return self::invalid( 'formId', __( 'This form cannot be sent here.', 'flx-forms' ) );
		}

		$given   = self::given( $input['fields'] ?? array() );
		$stored  = array();
		$names   = array();
		$errors  = new WP_Error();
		$n       = 0;
		$booking = FLXF_Form_Service::flag( $load['structure'][0]['booking'] ?? false );
		$dirty   = false;
		foreach ( $load['structure'] as $i => $field ) {
			if ( 0 === $i || FLXF_Form_Service::is_child_row( $field ) ) {
				continue;
			}
			if ( FLXF_Form_Service::flag( $field['hidden'] ?? false ) || FLXF_Form_Service::flag( $field['disabled'] ?? false ) ) {
				continue;
			}
			$ftype = sanitize_key( self::str( $field['type'] ?? '' ) );
			if ( '' === $ftype || in_array( $ftype, self::SKIP_TYPES, true ) ) {
				continue;
			}
			$fid = FLXF_Form_Service::field_id( $field );
			if ( '' === $fid ) {
				continue;
			}
			$raw   = $given[ $fid ] ?? '';
			$value = self::clean_value( $ftype, $raw );
			$need  = FLXF_Form_Service::flag( $field['required'] ?? false );
			if ( $need && '' === $value ) {
				$errors->add( 'flxf_' . $n, __( 'Fill in the required fields.', 'flx-forms' ), self::data( 'fields.' . $fid ) );
				++$n;
				continue;
			}
			if ( '' === $value ) {
				continue;
			}
			$row = self::accept( $ftype, $value, $field, $load['structure'], $id, $booking, $fid, $dirty );
			if ( is_string( $row ) ) {
				$errors->add( 'flxf_' . $n, $row, self::data( 'fields.' . $fid ) );
				++$n;
				continue;
			}
			if ( in_array( $ftype, self::FILE_TYPES, true ) ) {
				$names[] = self::str( $row['url'] ?? '' );
			}
			$stored[] = $row;
		}
		if ( $errors->has_errors() ) {
			return $errors;
		}
		if ( ! $stored ) {
			return self::invalid( 'fields', __( 'Please complete the form.', 'flx-forms' ) );
		}
		if ( $dirty ) {
			self::save_structure( $id, $load['structure'] );
		}

		$track = self::track_code();
		$msg   = self::insert_message( $id, self::str( $load['row']['form_name'] ), $track, $stored );
		if ( $msg < 1 ) {
			return self::error( 'INTERNAL', __( 'The form could not be sent.', 'flx-forms' ), 500 );
		}
		if ( class_exists( '\Emsfb\Upload_Guard' ) && $names ) {
			\Emsfb\Upload_Guard::release_pending_uploads( $names );
		}
		apply_filters(
			'flxf_submit_response',
			array( 'success' => true, 'track' => $track ),
			array(
				'event_type'       => 'form_submit',
				'track_code'       => $track,
				'form_id'          => $id,
				'submitted_values' => $stored,
				'form_fields'      => $load['structure'],
				'page_url'         => '',
			)
		);
		self::submit_effects( $id, self::str( $load['row']['form_name'] ), $track, $stored, $load['structure'], $type );

		return array(
			'submission' => array(
				'id'        => (string) $msg,
				'trackCode' => $track,
			),
		);
	}

	/**
	 * @param string $code Tracking code.
	 * @return FlxfTrackView|WP_Error
	 */
	public static function track( string $code ): array|WP_Error {
		$pub = \Emsfb\_Public::flxf_live();
		if ( ! $pub->flxf_track_lookup_allowed() ) {
			return self::error( 'RATE_LIMITED', __( 'Too many attempts. Please wait a few minutes and try again.', 'flx-forms' ), 429 );
		}
		$code   = sanitize_text_field( $code );
		$legacy = '' === $code || ! $pub->flxf_is_strong_track_code( $code );
		if ( $legacy && ! $pub->flxf_track_legacy_lookup_allowed() ) {
			return self::error( 'RATE_LIMITED', __( 'Too many attempts. Please wait a few minutes and try again.', 'flx-forms' ), 429 );
		}
		global $wpdb;
		/** @var wpdb $wpdb */
		$table = $wpdb->prefix . 'emsfb_msg_';
		$row   = '' === $code ? null : $wpdb->get_row(
			$wpdb->prepare( 'SELECT msg_id, track, content, date, form_title_x FROM %i WHERE track = %s LIMIT 1', $table, $code ),
			ARRAY_A
		);
		if ( ! is_array( $row ) ) {
			$pub->flxf_track_register_failure();
			if ( $legacy ) {
				$pub->flxf_track_register_legacy_failure();
			}
			return self::error( 'NOT_FOUND', __( 'The tracking code was not found.', 'flx-forms' ), 404 );
		}
		$msg_id  = self::num( $row['msg_id'] );
		$rsp     = $wpdb->prefix . 'emsfb_rsp_';
		$replies = $wpdb->get_results(
			$wpdb->prepare( 'SELECT content, date FROM %i WHERE msg_id = %d ORDER BY rsp_id ASC', $rsp, $msg_id ),
			ARRAY_A
		);
		$out = array();
		if ( is_array( $replies ) ) {
			foreach ( $replies as $reply ) {
				$parts = self::reply_parts( self::str( $reply['content'] ?? '' ) );
				$out[] = array(
					'body'        => $parts['body'],
					'createdAt'   => self::moment( self::str( $reply['date'] ?? '' ) ),
					'attachments' => $parts['attachments'],
				);
			}
		}
		return array(
			'trackCode'   => self::str( $row['track'] ),
			'formTitle'   => self::str( $row['form_title_x'] ?? '' ),
			'submittedAt' => self::moment( self::str( $row['date'] ?? '' ) ),
			'fields'      => self::answers( self::str( $row['content'] ?? '' ) ),
			'replies'     => $out,
		);
	}

	/**
	 * @param array<string,mixed> $input Mutation input.
	 * @return FlxfSubmitResult|WP_Error
	 */
	public static function reply( array $input ): array|WP_Error {
		$limited = self::limit( 'flxf_track_reply', self::SUBMIT_MAX, self::SUBMIT_WINDOW, __( 'Too many submissions. Wait a moment and try again.', 'flx-forms' ) );
		if ( $limited instanceof WP_Error ) {
			return $limited;
		}
		$code = sanitize_text_field( self::str( $input['trackCode'] ?? '' ) );
		$body = sanitize_textarea_field( self::str( $input['message'] ?? '' ) );
		if ( strlen( $body ) > self::TEXT_MAX ) {
			$body = substr( $body, 0, self::TEXT_MAX );
		}
		if ( '' === $body ) {
			return self::invalid( 'message', __( 'Write a reply.', 'flx-forms' ) );
		}
		global $wpdb;
		/** @var wpdb $wpdb */
		$table = $wpdb->prefix . 'emsfb_msg_';
		$row   = $wpdb->get_row(
			$wpdb->prepare( 'SELECT msg_id, track, form_id, content FROM %i WHERE track = %s LIMIT 1', $table, $code ),
			ARRAY_A
		);
		if ( ! is_array( $row ) ) {
			return self::error( 'NOT_FOUND', __( 'The tracking code was not found.', 'flx-forms' ), 404 );
		}
		$msg_id = self::num( $row['msg_id'] );
		$track  = self::str( $row['track'] );
		$admin  = self::admin_reply( $track, self::str( $input['adminCode'] ?? '' ) );
		if ( $admin instanceof WP_Error ) {
			return $admin;
		}

		$files = array();
		foreach ( (array) ( $input['attachments'] ?? array() ) as $url ) {
			$url = esc_url_raw( self::str( $url ) );
			if ( '' === $url ) {
				continue;
			}
			if ( ! class_exists( '\Emsfb\Upload_Guard' ) || ! \Emsfb\Upload_Guard::response_attachment_is_reserved( $url, $msg_id, $track ) ) {
				return self::invalid( 'attachments', __( 'Upload the file again.', 'flx-forms' ) );
			}
			$files[] = $url;
		}

		$uid     = is_user_logged_in() ? (int) get_current_user_id() : 0;
		$by      = ( $admin && 0 === $uid ) ? -1 : $uid;
		$safe    = strtr( $body, array( '"' => "\u{201D}", '\\' => "\u{29F5}" ) );
		$payload = array(
			array(
				'id_'   => 'message',
				'name'  => 'Message',
				'type'  => 'text',
				'value' => $safe,
			),
		);
		foreach ( $files as $url ) {
			$payload[] = array(
				'id_'   => 'file',
				'name'  => 'File',
				'type'  => 'allformat',
				'value' => '@file@',
				'url'   => $url,
			);
		}
		$rsp = $wpdb->prefix . 'emsfb_rsp_';
		$ok  = $wpdb->insert(
			$rsp,
			array(
				'msg_id'    => $msg_id,
				'ip'        => self::ip(),
				'content'   => \Emsfb\_Public::flxf_live()->flxf_encode_reply_payload( $payload ),
				'date'      => current_time( 'mysql' ),
				'read_by'   => '',
				'read_'     => $admin ? 1 : 0,
				'reader_ip' => '',
				'rsp_by'    => $by,
			),
			array( '%d', '%s', '%s', '%s', '%s', '%d', '%s', '%d' )
		);
		if ( false === $ok ) {
			return self::error( 'INTERNAL', __( 'The form could not be sent.', 'flx-forms' ), 500 );
		}
		$wpdb->update( $table, array( 'read_' => $admin ? 1 : 0 ), array( 'msg_id' => $msg_id ), array( '%d' ), array( '%d' ) );
		if ( $files && class_exists( '\Emsfb\Upload_Guard' ) ) {
			\Emsfb\Upload_Guard::release_response_attachment_reservations( $files );
			\Emsfb\Upload_Guard::release_pending_uploads( $files );
		}
		self::reply_effects( self::num( $row['form_id'] ?? 0 ), self::str( $row['content'] ?? '' ), $track, $admin );
		return array(
			'submission' => array(
				'id'        => (string) $wpdb->insert_id,
				'trackCode' => $track,
			),
		);
	}

	/**
	 * @param mixed $fields Input list.
	 * @return array<string,string>
	 */
	private static function given( mixed $fields ): array {
		$out = array();
		if ( ! is_array( $fields ) ) {
			return $out;
		}
		foreach ( $fields as $field ) {
			if ( ! is_array( $field ) ) {
				continue;
			}
			$id = sanitize_text_field( self::str( $field['fieldId'] ?? '' ) );
			if ( '' === $id ) {
				continue;
			}
			$out[ $id ] = self::str( $field['value'] ?? '' );
		}
		return $out;
	}

	private static function clean_value( string $type, string $raw ): string {
		if ( in_array( $type, self::FILE_TYPES, true ) ) {
			return esc_url_raw( $raw );
		}
		if ( 'textarea' === $type ) {
			$value = sanitize_textarea_field( $raw );
		} else {
			$value = sanitize_text_field( $raw );
		}
		if ( strlen( $value ) > self::TEXT_MAX ) {
			$value = substr( $value, 0, self::TEXT_MAX );
		}
		return $value;
	}

	/**
	 * A file the upload endpoint stored for this field. The pending ledger still
	 * has no form id; the classic validator applies this field's size and extensions.
	 *
	 * @param array<string,mixed> $field Field definition.
	 * @return string|null Error message, or null when the file is allowed.
	 */
	private static function pending_upload( string $url, array $field, int $form_id ): ?string {
		if ( ! class_exists( '\Emsfb\Upload_Guard' ) ) {
			return __( 'Upload the file again.', 'flx-forms' );
		}
		$base = wp_basename( $url );
		if ( '' === $base || str_contains( $base, '/' ) || str_contains( $base, '\\' ) ) {
			return __( 'Upload the file again.', 'flx-forms' );
		}
		$pending = get_option( \Emsfb\Upload_Guard::PENDING_OPTION, array() );
		if ( ! is_array( $pending ) || ! isset( $pending[ $base ] ) ) {
			return __( 'Upload the file again.', 'flx-forms' );
		}
		$pub     = \Emsfb\_Public::flxf_live();
		$pub->id = $form_id;
		$check   = $pub->flxf_validate_submitted_upload( $url, $field );
		if ( true === $check ) {
			return null;
		}
		return is_string( $check ) && '' !== $check ? $check : __( 'Upload the file again.', 'flx-forms' );
	}

	/**
	 * @param list<array<string,mixed>> $stored Field rows.
	 */
	private static function insert_message( int $form_id, string $title, string $track, array $stored ): int {
		global $wpdb;
		/** @var wpdb $wpdb */
		$table   = $wpdb->prefix . 'emsfb_msg_';
		$rows    = $stored;
		$rows[]  = array(
			'type'   => 'w_link',
			'value'  => home_url(),
			'amount' => -1,
		);
		$encoded = self::encode_rows( $rows );
		$ok      = $wpdb->insert(
			$table,
			array(
				'form_id'      => $form_id,
				'track'        => $track,
				'ip'           => self::ip(),
				'form_title_x' => $title,
				'content'      => $encoded,
				'date'         => current_time( 'mysql' ),
				'read_'        => 0,
				'read_by'      => '',
			),
			array( '%d', '%s', '%s', '%s', '%s', '%s', '%d', '%s' )
		);
		return false === $ok ? 0 : $wpdb->insert_id;
	}

	private static function track_code(): string {
		global $wpdb;
		/** @var wpdb $wpdb */
		$table = $wpdb->prefix . 'emsfb_msg_';
		for ( $i = 0; $i < 5; $i++ ) {
			$code = wp_generate_password( 12, false, false );
			$have = self::num( $wpdb->get_var( $wpdb->prepare( 'SELECT COUNT(*) FROM %i WHERE track = %s', $table, $code ) ) );
			if ( 0 === $have ) {
				return $code;
			}
		}
		return wp_generate_password( 12, false, false );
	}

	/**
	 * @return list<FlxfAnswer>
	 */
	public static function answers( string $content ): array {
		$rows = self::decode_json( $content );
		if ( ! is_array( $rows ) ) {
			return array();
		}
		$out = array();
		foreach ( $rows as $row ) {
			if ( ! is_array( $row ) ) {
				continue;
			}
			$rtype = sanitize_key( self::str( $row['type'] ?? '' ) );
			if ( in_array( $rtype, array( 'w_link', 'closed', 'opened', 'payment' ), true ) ) {
				continue;
			}
			$value = sanitize_text_field( self::str( $row['value'] ?? '' ) );
			if ( '@file@' === $value ) {
				$value = esc_url_raw( self::str( $row['url'] ?? '' ) );
			}
			if ( '' === $value ) {
				continue;
			}
			$label = sanitize_text_field( self::str( $row['name'] ?? $row['label'] ?? $row['id_'] ?? '' ) );
			$out[] = array(
				'label' => $label,
				'value' => $value,
			);
		}
		return $out;
	}

	/**
	 * @return array{body:string,attachments:list<string>}
	 */
	private static function reply_parts( string $content ): array {
		$decoded = self::decode_json( $content );
		$text    = '';
		$files   = array();
		if ( is_array( $decoded ) && array_is_list( $decoded ) ) {
			foreach ( $decoded as $item ) {
				if ( ! is_array( $item ) ) {
					continue;
				}
				$type = sanitize_key( self::str( $item['type'] ?? '' ) );
				$val  = self::str( $item['value'] ?? '' );
				if ( ( 'message' === self::str( $item['id_'] ?? '' ) || 'text' === $type ) && '@file@' !== $val ) {
					$text = $val;
				}
				$url = esc_url_raw( self::str( $item['url'] ?? '' ) );
				if ( '' !== $url && ( 'allformat' === $type || '@file@' === $val ) ) {
					$files[] = $url;
				}
			}
			return array(
				'body'        => sanitize_textarea_field( $text ),
				'attachments' => $files,
			);
		}
		if ( is_string( $decoded ) ) {
			return array(
				'body'        => sanitize_textarea_field( $decoded ),
				'attachments' => array(),
			);
		}
		return array(
			'body'        => sanitize_textarea_field( $content ),
			'attachments' => array(),
		);
	}

	/**
	 * True when the email verification code matches. A wrong code is a WP_Error.
	 * An empty code is a guest reply. Matches set_rMessage_id_Emsfb_api.
	 *
	 * @return bool|WP_Error
	 */
	private static function admin_reply( string $track, string $code ): bool|WP_Error {
		if ( is_user_logged_in() && current_user_can( 'administrator' ) ) {
			return true;
		}
		$code = sanitize_text_field( $code );
		if ( '' === $code ) {
			return false;
		}
		$key = self::email_key();
		if ( strlen( $key ) <= 3 ) {
			return false;
		}
		$want = md5( $track . $key ); // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_md5 -- same check as the classic reply handler.
		if ( ! hash_equals( $want, $code ) ) {
			return self::invalid( 'adminCode', __( 'The confirmation code is not valid.', 'flx-forms' ) );
		}
		return true;
	}

	private static function email_key(): string {
		global $wpdb;
		/** @var wpdb $wpdb */
		$table = $wpdb->prefix . 'emsfb_setting';
		$raw   = $wpdb->get_var( "SELECT setting FROM `{$table}` ORDER BY id DESC LIMIT 1" );
		if ( ! is_string( $raw ) || '' === $raw ) {
			return '';
		}
		$data = json_decode( $raw, true );
		if ( ! is_array( $data ) ) {
			$data = json_decode( str_replace( '\\', '', $raw ), true );
		}
		if ( ! is_array( $data ) ) {
			return '';
		}
		return is_string( $data['email_key'] ?? null ) ? $data['email_key'] : '';
	}

	/**
	 * flx_rate_limit returns true while the bucket still has room.
	 */
	private static function limit( string $bucket, int $max, int $window, string $message ): ?WP_Error {
		$max = self::num( apply_filters( 'flxf_gql_rate_max', $max, $bucket ) );
		if ( $max < 1 ) {
			$max = 1;
		}
		$ip = function_exists( 'flx_client_ip' ) ? flx_client_ip() : '0';
		if ( function_exists( 'flx_rate_limit' ) && ! flx_rate_limit( $bucket . '|' . $ip, $max, $window ) ) {
			return self::error( 'RATE_LIMITED', $message, 429 );
		}
		return null;
	}

	public static function ip(): string {
		$ip = function_exists( 'flx_client_ip' ) ? flx_client_ip() : '0.0.0.0';
		return substr( $ip, 0, 45 );
	}

	/**
	 * One filled field, checked the way the classic submit checks it.
	 *
	 * @param array<string,mixed>            $field     Field definition.
	 * @param array<int,array<string,mixed>> $structure Whole structure. Booking quota is written back here.
	 * @return array<string,mixed>|string Stored row, or an error message.
	 */
	private static function accept( string $type, string $value, array $field, array &$structure, int $form_id, bool $booking, string $fid, bool &$dirty ): array|string {
		$label = sanitize_text_field( self::str( $field['name'] ?? $field['label'] ?? $fid ) );
		$row   = array(
			'id_'   => $fid,
			'name'  => $label,
			'type'  => $type,
			'value' => $value,
		);
		if ( in_array( $type, self::FILE_TYPES, true ) ) {
			$check = $field;
			$check['id_']  = $fid;
			$check['type'] = $type;
			$err = self::pending_upload( $value, $check, $form_id );
			if ( null !== $err ) {
				return $err;
			}
			$row['value'] = '@file@';
			$row['url']   = $value;
			return $row;
		}
		if ( 'email' === $type && ! is_email( $value ) ) {
			return __( 'Enter a valid email address.', 'flx-forms' );
		}
		if ( 'date' === $type ) {
			if ( 1 !== preg_match( '/^\d{4}-\d{1,2}-\d{1,2}$/', $value ) ) {
				return __( 'Enter a valid date.', 'flx-forms' );
			}
			$parts = explode( '-', $value );
			if ( 3 !== count( $parts ) || ! checkdate( (int) $parts[1], (int) $parts[2], (int) $parts[0] ) ) {
				return __( 'Enter a valid date.', 'flx-forms' );
			}
			$today = (string) wp_date( 'Y-m-d' );
			$min   = self::str( $field['milen'] ?? '' );
			$max   = self::str( $field['mlen'] ?? '' );
			if ( '1' === $min ) {
				$min = $today;
			}
			if ( '1' === $max ) {
				$max = $today;
			}
			if ( '' !== $min && strtotime( $min ) > strtotime( $value ) ) {
				return __( 'Enter a valid date.', 'flx-forms' );
			}
			if ( '' !== $max && strtotime( $max ) < strtotime( $value ) ) {
				return __( 'Enter a valid date.', 'flx-forms' );
			}
			return $row;
		}
		if ( 'url' === $type ) {
			$url = esc_url_raw( $value );
			if ( '' === $url || false === wp_http_validate_url( $url ) ) {
				return __( 'Enter a valid URL.', 'flx-forms' );
			}
			$row['value'] = $url;
			return self::length_row( $row, $field );
		}
		if ( 'color' === $type ) {
			if ( 1 !== preg_match( '/^#[0-9a-fA-F]{6}$/', $value ) ) {
				return __( 'Enter a valid color.', 'flx-forms' );
			}
			return $row;
		}
		if ( 'pointr5' === $type || 'rating' === $type ) {
			if ( ! is_numeric( $value ) || (int) $value < 1 || (int) $value > 5 ) {
				return __( 'Enter a valid number.', 'flx-forms' );
			}
			$row['value'] = (string) (int) $value;
			return $row;
		}
		if ( 'pointr10' === $type ) {
			if ( ! is_numeric( $value ) || (int) $value < 0 || (int) $value > 10 ) {
				return __( 'Enter a valid number.', 'flx-forms' );
			}
			$row['value'] = (string) (int) $value;
			return $row;
		}
		if ( 'number' === $type || 'range' === $type ) {
			if ( ! is_numeric( $value ) ) {
				return __( 'Enter a valid number.', 'flx-forms' );
			}
			if ( 'range' === $type ) {
				$min = self::str( $field['milen'] ?? '' );
				$max = self::str( $field['mlen'] ?? '' );
				if ( '' !== $min && is_numeric( $min ) && (float) $min > (float) $value ) {
					return __( 'Enter a valid number.', 'flx-forms' );
				}
				if ( '' !== $max && is_numeric( $max ) && (float) $max < (float) $value ) {
					return __( 'Enter a valid number.', 'flx-forms' );
				}
				return $row;
			}
			return self::length_row( $row, $field );
		}
		if ( in_array( $type, self::CHOICE_TYPES, true ) ) {
			return self::choice_row( $row, $field, $structure, $booking, $fid, $dirty );
		}
		return self::length_row( $row, $field );
	}

	/**
	 * @param array<string,mixed>            $row       Stored row so far.
	 * @param array<string,mixed>            $field     Parent field.
	 * @param array<int,array<string,mixed>> $structure Whole structure.
	 * @return array<string,mixed>|string
	 */
	private static function choice_row( array $row, array $field, array &$structure, bool $booking, string $fid, bool &$dirty ): array|string {
		$options = FLXF_Form_Service::choice_rows( $field, $structure );
		$rtype   = self::str( $row['type'] ?? '' );
		$multi   = 'multiselect' === $rtype || 'paymultiselect' === $rtype || str_contains( $rtype, 'check' );
		$tokens  = $multi ? array_values( array_unique( array_filter( array_map( 'trim', explode( ',', self::str( $row['value'] ?? '' ) ) ), static fn( string $bit ): bool => '' !== $bit ) ) ) : array( self::str( $row['value'] ?? '' ) );
		if ( ! $tokens ) {
			return __( 'Choose one of the listed options.', 'flx-forms' );
		}
		$chosen  = array();
		$matched = null;
		foreach ( $tokens as $token ) {
			$hit = null;
			foreach ( $options as $option ) {
				$ovalue = sanitize_text_field( self::str( $option['value'] ?? '' ) );
				$oid    = FLXF_Form_Service::field_id( $option );
				if ( $token === $ovalue || ( '' !== $oid && $token === $oid ) ) {
					$hit = $option;
					break;
				}
			}
			if ( null === $hit ) {
				return __( 'Choose one of the listed options.', 'flx-forms' );
			}
			if ( $booking ) {
				$exp = self::str( $hit['dateExp'] ?? '' );
				if ( '' !== $exp && false !== strtotime( $exp ) && strtotime( $exp ) < strtotime( (string) wp_date( 'Y-m-d' ) ) ) {
					return __( 'This choice is no longer available.', 'flx-forms' );
				}
				if ( isset( $hit['mlen'] ) && '' !== self::str( $hit['mlen'] ) && self::num( $hit['mlen'] ) <= self::num( $hit['registered_count'] ?? 0 ) ) {
					return __( 'This choice is no longer available.', 'flx-forms' );
				}
				self::bump_booking( $structure, $hit, $fid );
				$dirty = true;
			}
			$stored = sanitize_text_field( self::str( $hit['value'] ?? '' ) );
			if ( '' === $stored ) {
				$stored = FLXF_Form_Service::field_id( $hit );
			}
			$chosen[] = $stored;
			$matched  = $hit;
		}
		if ( $multi ) {
			$row['value'] = implode( '@efb!', $chosen ) . '@efb!';
		} else {
			$row['value'] = $chosen[0];
		}
		$oid = FLXF_Form_Service::field_id( $matched );
		if ( '' !== $oid ) {
			$row['id_ob'] = $oid;
		}
		return $row;
	}

	/**
	 * @param array<int,array<string,mixed>> $structure Whole structure.
	 * @param array<string,mixed>            $option    Matched option row.
	 */
	private static function bump_booking( array &$structure, array $option, string $parent ): void {
		$oid    = FLXF_Form_Service::field_id( $option );
		$ovalue = self::str( $option['value'] ?? '' );
		foreach ( $structure as $i => $row ) {
			if ( 'option' === sanitize_key( self::str( $row['type'] ?? '' ) ) && sanitize_text_field( self::str( $row['parent'] ?? '' ) ) === $parent ) {
				$same = ( '' !== $oid && FLXF_Form_Service::field_id( $row ) === $oid ) || ( '' !== $ovalue && self::str( $row['value'] ?? '' ) === $ovalue );
				if ( $same ) {
					$structure[ $i ]['registered_count'] = self::num( $row['registered_count'] ?? 0 ) + 1;
					return;
				}
				continue;
			}
			if ( FLXF_Form_Service::field_id( $row ) !== $parent || ! isset( $row['options'] ) || ! is_array( $row['options'] ) ) {
				continue;
			}
			$options = $row['options'];
			foreach ( $options as $k => $child ) {
				if ( ! is_array( $child ) ) {
					continue;
				}
				$clean = array();
				foreach ( $child as $ckey => $cval ) {
					if ( is_string( $ckey ) ) {
						$clean[ $ckey ] = $cval;
					}
				}
				$same = ( '' !== $oid && FLXF_Form_Service::field_id( $clean ) === $oid ) || ( '' !== $ovalue && self::str( $clean['value'] ?? '' ) === $ovalue );
				if ( $same ) {
					$clean['registered_count']  = self::num( $clean['registered_count'] ?? 0 ) + 1;
					$options[ $k ]              = $clean;
					$structure[ $i ]['options'] = $options;
					return;
				}
			}
		}
	}

	/**
	 * @param array<string,mixed> $row   Stored row.
	 * @param array<string,mixed> $field Field definition.
	 * @return array<string,mixed>|string
	 */
	private static function length_row( array $row, array $field ): array|string {
		$value = self::str( $row['value'] ?? '' );
		$len   = function_exists( 'mb_strlen' ) ? mb_strlen( $value, 'UTF-8' ) : strlen( $value );
		$min   = is_numeric( self::str( $field['milen'] ?? '' ) ) ? (int) self::str( $field['milen'] ) : 0;
		$max   = is_numeric( self::str( $field['mlen'] ?? '' ) ) ? (int) self::str( $field['mlen'] ) : 0;
		if ( $min > 0 && $len < $min ) {
			return __( 'This answer is too short.', 'flx-forms' );
		}
		if ( $max > 0 && $len > $max ) {
			return __( 'This answer is too long.', 'flx-forms' );
		}
		return $row;
	}

	/**
	 * @param array<int,array<string,mixed>> $structure Updated structure.
	 */
	private static function save_structure( int $form_id, array $structure ): void {
		global $wpdb;
		/** @var wpdb $wpdb */
		$json = wp_json_encode( $structure, JSON_UNESCAPED_UNICODE );
		if ( ! is_string( $json ) ) {
			return;
		}
		$table = $wpdb->prefix . 'emsfb_form';
		$wpdb->update( $table, array( 'form_structer' => $json ), array( 'form_id' => $form_id ), array( '%s' ), array( '%d' ) );
	}

	/**
	 * @param list<array<string,mixed>>      $stored    Stored rows.
	 * @param array<int,array<string,mixed>> $structure Form structure.
	 */
	private static function submit_effects( int $form_id, string $title, string $track, array $stored, array $structure, string $form_type ): void {
		$pub      = self::prepare_public( $form_id, $title );
		$head     = $structure[0] ?? array();
		$settings = is_object( $pub->setting ) ? $pub->setting : null;
		$send     = function_exists( 'emsfb_is_email_sending_enabled_efb' ) && emsfb_is_email_sending_enabled_efb( $settings );
		$pro      = (bool) $pub->pro_efb;

		$email_recipients = array( array(), array() );
		$head_email       = self::str( $head['email'] ?? '' );
		$supporter        = is_object( $settings ) ? self::str( $settings->emailSupporter ?? '' ) : '';
		$femail           = is_object( $settings ) ? self::str( $settings->femail ?? '' ) : '';
		if ( '' !== $femail && is_email( $femail ) ) {
			$email_recipients[2] = $femail;
		}
		if ( '' !== $head_email ) {
			$pub->email_list_efb( $email_recipients, 0, $head_email, true );
		}

		$email_to     = self::str( $head['email_to'] ?? '' );
		$noti_ids     = array();
		$sms_noti_ids = array();
		foreach ( $structure as $field ) {
			$fid   = FLXF_Form_Service::field_id( $field );
			$ftype = sanitize_key( self::str( $field['type'] ?? '' ) );
			if ( '' === $fid ) {
				continue;
			}
			if ( 'email' === $ftype && FLXF_Form_Service::flag( $field['noti'] ?? false ) ) {
				$noti_ids[ $fid ] = true;
			}
			if ( 'mobile' === $ftype && 1 === self::num( $field['smsnoti'] ?? 0 ) ) {
				$sms_noti_ids[ $fid ] = true;
			}
		}

		$phone_numbers = array( array(), array() );
		if ( is_object( $settings ) && 'wpsms' === self::str( $settings->sms_config ?? '' ) ) {
			$phn = self::str( $settings->phnNo ?? '' );
			if ( strlen( $phn ) > 5 ) {
				$phone_numbers[0] = explode( ',', $phn );
			}
		}

		foreach ( $stored as $row ) {
			$rid = self::str( $row['id_'] ?? '' );
			$val = self::str( $row['value'] ?? '' );
			if ( '' === $rid || '' === $val ) {
				continue;
			}
			if ( $send && ( ( '' !== $email_to && $rid === $email_to ) || isset( $noti_ids[ $rid ] ) ) ) {
				$pub->email_list_efb( $email_recipients, 1, $val, true );
			}
			if ( isset( $sms_noti_ids[ $rid ] ) ) {
				$phone_numbers[1][] = $val;
			}
		}

		/** @var array<int,mixed> $email_recipients */
		$pub->flxf_dispatch_submit_effects(
			$track,
			$stored,
			$structure,
			$phone_numbers,
			home_url(),
			$send,
			$email_recipients,
			FLXF_Form_Service::flag( $head['trackingCode'] ?? false ) ? 1 : 0,
			'' !== $supporter ? $supporter : $head_email,
			$pro,
			$form_type
		);
	}

	private static function reply_effects( int $form_id, string $content, string $track, bool $admin ): void {
		if ( $form_id < 1 ) {
			return;
		}
		$load = FLXF_Form_Service::load( $form_id );
		if ( null === $load || ! $load['structure'] ) {
			return;
		}
		$pub     = self::prepare_public( $form_id, self::str( $load['row']['form_name'] ) );
		$setting = is_object( $pub->setting ) ? $pub->setting : (object) array();
		$decoded = self::decode_json( $content );
		$msg     = array();
		if ( is_array( $decoded ) ) {
			foreach ( $decoded as $item ) {
				if ( is_array( $item ) ) {
					$msg[] = $item;
				}
			}
		}
		$active = function_exists( 'emsfb_is_email_sending_enabled_efb' ) && emsfb_is_email_sending_enabled_efb( $setting );
		$pro    = (bool) $pub->pro_efb;
		$pub->flxf_dispatch_reply_effects( $load['structure'], $msg, $setting, $active, $form_id, $admin ? 'admin' : 'guest', home_url(), $track, $pro );
	}

	private static function prepare_public( int $form_id, string $title ): \Emsfb\_Public {
		$pub          = \Emsfb\_Public::flxf_live();
		$pub->id      = $form_id;
		$pub->name    = $title;
		$pub->ip      = self::ip();
		$pub->pro_efb = in_array( self::num( get_option( 'emsfb_pro' ) ), array( 1, 3 ), true );
		$settings     = function_exists( 'get_setting_Emsfb' ) ? get_setting_Emsfb( 'decoded' ) : null;
		if ( is_object( $settings ) ) {
			$pub->setting = $settings;
		}
		if ( function_exists( 'get_efbFunction' ) ) {
			$fn               = get_efbFunction();
			$pub->efbFunction = $fn;
			if ( is_object( $fn ) && method_exists( $fn, 'text_efb' ) ) {
				$text = $fn->text_efb( array( 'msgdml', 'thankFillForm', 'trackNo', 'vmgs', 'WeRecivedUrM', 'thankRegistering', 'welcome', 'thankSubscribing', 'thankDonePoll', 'newMessageReceived', 'youRecivedNewMessage', 'msgSndBut', 'smsWPN', 'messageSent', 'spprt' ) );
				if ( is_array( $text ) ) {
					$pub->lanText = $text;
				}
			}
		}
		return $pub;
	}

	/**
	 * Classic message storage: quotes are swapped, then the JSON quotes are escaped
	 * so a reader that strips backslashes gets the original document back.
	 *
	 * @param list<array<string,mixed>> $rows Field rows.
	 */
	public static function encode_rows( array $rows ): string {
		array_walk_recursive(
			$rows,
			static function ( mixed &$value ): void {
				if ( is_string( $value ) ) {
					$value = strtr( $value, array( '"' => "\u{201D}", '\\' => "\u{29F5}" ) );
				}
			}
		);
		$json = wp_json_encode( $rows, JSON_UNESCAPED_UNICODE );
		$json = is_string( $json ) ? $json : '[]';
		return str_replace( '"', '\\"', $json );
	}

	public static function decode_json( string $content ): mixed {
		$decoded = json_decode( $content, true );
		if ( ! is_array( $decoded ) && ! is_string( $decoded ) ) {
			$decoded = json_decode( str_replace( '\\', '', $content ), true );
		}
		return $decoded;
	}

	/**
	 * Site-local MySQL time as FlxDateTime. flx_gql_datetime() reads a string as UTC.
	 *
	 * @return FlxfMoment
	 */
	public static function moment( string $mysql ): array {
		$gmt = '' !== $mysql ? get_gmt_from_date( $mysql ) : '';
		$out = flx_gql_datetime( $gmt );
		if ( is_array( $out ) ) {
			return $out;
		}
		$now = flx_gql_datetime( time() );
		if ( is_array( $now ) ) {
			return $now;
		}
		return array(
			'iso'       => gmdate( 'Y-m-d\TH:i:s\Z' ),
			'timestamp' => time(),
			'label'     => '',
			'relative'  => '',
		);
	}

	/**
	 * A stored scalar as text. Arrays and objects become an empty string.
	 */
	public static function str( mixed $value ): string {
		return is_scalar( $value ) ? (string) $value : '';
	}

	/**
	 * A stored number as int. Anything else is 0.
	 */
	public static function num( mixed $value ): int {
		if ( is_int( $value ) ) {
			return $value;
		}
		if ( is_float( $value ) || ( is_string( $value ) && is_numeric( $value ) ) ) {
			return (int) $value;
		}
		return 0;
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
