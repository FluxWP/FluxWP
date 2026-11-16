<?php
/**
 * Public submit, track and track-reply for GraphQL.
 *
 * The classic handlers (get_form_public_efb, get_track_public_api, set_rMessage_id_Emsfb_api)
 * print JSON and exit. Calling them would change the REST bytes, so this service writes the
 * same tables itself. It does not send the notification email that lives inside the classic
 * submit method. It does fire flxf_submit_response and efb_after_form_integration so automations
 * still see the submission. See docs/flx-forms/GRAPHQL.md.
 *
 * @package FLX_Forms
 */

defined( 'ABSPATH' ) || exit;

/**
 * GraphQL submission, tracking and replies.
 */
class FLXF_Submission_Service {

	private const SUBMIT_MAX    = 8;
	private const SUBMIT_WINDOW = 600;
	private const TRACK_MAX     = 30;
	private const TRACK_WINDOW  = 600;
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
	);

	/**
	 * Types a visitor does not fill in.
	 *
	 * @var list<string>
	 */
	private const SKIP_TYPES = array( 'step', 'heading', 'html', 'link', 'raw', 'stripe' );

	/**
	 * @param array<string,mixed> $input Mutation input.
	 * @return array<string,mixed>|WP_Error
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

		$given  = self::given( $input['fields'] ?? array() );
		$stored = array();
		$names  = array();
		$errors = new WP_Error();
		$n      = 0;
		foreach ( $load['structure'] as $i => $field ) {
			if ( 0 === $i ) {
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
			if ( in_array( $ftype, array( 'email' ), true ) && ! is_email( $value ) ) {
				$errors->add( 'flxf_' . $n, __( 'Enter a valid email address.', 'flx-forms' ), self::data( 'fields.' . $fid ) );
				++$n;
				continue;
			}
			if ( in_array( $ftype, self::FILE_TYPES, true ) ) {
				if ( ! self::pending_upload( $value ) ) {
					$errors->add( 'flxf_' . $n, __( 'Upload the file again.', 'flx-forms' ), self::data( 'fields.' . $fid ) );
					++$n;
					continue;
				}
				$names[] = $value;
			}
			$stored[] = array(
				'id_'   => $fid,
				'name'  => sanitize_text_field( self::str( $field['name'] ?? $field['label'] ?? $fid ) ),
				'type'  => $ftype,
				'value' => $value,
			);
		}
		if ( $errors->has_errors() ) {
			return $errors;
		}

		$track = self::track_code();
		$msg   = self::insert_message( $id, self::str( $load['row']['form_name'] ), $track, $stored );
		if ( $msg < 1 ) {
			return self::error( 'INTERNAL', __( 'The form could not be sent.', 'flx-forms' ), 500 );
		}
		if ( class_exists( '\Emsfb\Upload_Guard' ) && $names ) {
			\Emsfb\Upload_Guard::release_pending_uploads( $names );
		}
		$context = array(
			'event_type'       => 'form_submit',
			'track_code'       => $track,
			'form_id'          => $id,
			'submitted_values' => $stored,
			'form_fields'      => $load['structure'],
			'page_url'         => '',
		);
		apply_filters( 'flxf_submit_response', array( 'success' => true, 'track' => $track ), $context );
		do_action( 'efb_after_form_integration', $context );

		return array(
			'submission' => array(
				'id'        => (string) $msg,
				'trackCode' => $track,
			),
		);
	}

	/**
	 * @param string $code Tracking code.
	 * @return array<string,mixed>|WP_Error
	 */
	public static function track( string $code ): array|WP_Error {
		$limited = self::limit( 'flxf_track', self::TRACK_MAX, self::TRACK_WINDOW, __( 'Too many lookups. Wait a moment and try again.', 'flx-forms' ) );
		if ( $limited instanceof WP_Error ) {
			return $limited;
		}
		$code = sanitize_text_field( $code );
		if ( ! preg_match( '/^[A-Za-z0-9]{4,12}$/', $code ) ) {
			return self::error( 'NOT_FOUND', __( 'The tracking code was not found.', 'flx-forms' ), 404 );
		}
		global $wpdb;
		/** @var wpdb $wpdb */
		$table = $wpdb->prefix . 'emsfb_msg_';
		$row   = $wpdb->get_row(
			$wpdb->prepare( 'SELECT msg_id, track, content, date, form_title_x FROM %i WHERE track = %s LIMIT 1', $table, $code ),
			ARRAY_A
		);
		if ( ! is_array( $row ) ) {
			return self::error( 'NOT_FOUND', __( 'The tracking code was not found.', 'flx-forms' ), 404 );
		}
		$msg_id = self::num( $row['msg_id'] );
		$rsp    = $wpdb->prefix . 'emsfb_rsp_';
		$replies = $wpdb->get_results(
			$wpdb->prepare( 'SELECT content, date FROM %i WHERE msg_id = %d ORDER BY rsp_id ASC', $rsp, $msg_id ),
			ARRAY_A
		);
		$out = array();
		if ( is_array( $replies ) ) {
			foreach ( $replies as $reply ) {
				$out[] = array(
					'body'      => self::reply_text( self::str( $reply['content'] ?? '' ) ),
					'createdAt' => self::str( $reply['date'] ?? '' ),
				);
			}
		}
		return array(
			'trackCode'   => self::str( $row['track'] ),
			'formTitle'   => self::str( $row['form_title_x'] ?? '' ),
			'submittedAt' => self::str( $row['date'] ?? '' ),
			'fields'      => self::answers( self::str( $row['content'] ?? '' ) ),
			'replies'     => $out,
		);
	}

	/**
	 * @param array<string,mixed> $input Mutation input.
	 * @return array<string,mixed>|WP_Error
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
			$wpdb->prepare( 'SELECT msg_id, track FROM %i WHERE track = %s LIMIT 1', $table, $code ),
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

		$by  = $admin ? -1 : ( is_user_logged_in() ? (int) get_current_user_id() : 0 );
		$rsp = $wpdb->prefix . 'emsfb_rsp_';
		$ok  = $wpdb->insert(
			$rsp,
			array(
				'msg_id'    => $msg_id,
				'ip'        => self::ip(),
				'content'   => $body,
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
	 * A file the upload endpoint stored and has not claimed yet. The pending ledger
	 * has no form id (basename => time), so this rejects an arbitrary uploads URL
	 * and still cannot tell form A from form B. See docs/flx-forms/GRAPHQL.md.
	 */
	private static function pending_upload( string $url ): bool {
		if ( ! class_exists( '\Emsfb\Upload_Guard' ) ) {
			return false;
		}
		$base = wp_basename( $url );
		if ( '' === $base || str_contains( $base, '/' ) || str_contains( $base, '\\' ) ) {
			return false;
		}
		$pending = get_option( \Emsfb\Upload_Guard::PENDING_OPTION, array() );
		if ( ! is_array( $pending ) || ! isset( $pending[ $base ] ) ) {
			return false;
		}
		$uploads = wp_upload_dir();
		$baseurl = $uploads['baseurl'];
		$basedir = $uploads['basedir'];
		if ( '' === $baseurl || '' === $basedir || ! str_starts_with( $url, $baseurl ) ) {
			return false;
		}
		$rel  = substr( $url, strlen( $baseurl ) );
		$path = wp_normalize_path( $basedir . $rel );
		$real = realpath( $path );
		$root = realpath( $basedir );
		if ( false === $real || false === $root || ! str_starts_with( $real, $root . '/' ) || ! is_file( $real ) ) {
			return false;
		}
		$check = \Emsfb\Upload_Guard::validate_stored_file( $real, $base, array() );
		return true === $check;
	}

	/**
	 * @param list<array<string,string>> $stored Field rows.
	 */
	private static function insert_message( int $form_id, string $title, string $track, array $stored ): int {
		global $wpdb;
		/** @var wpdb $wpdb */
		$table   = $wpdb->prefix . 'emsfb_msg_';
		$encoded = wp_json_encode( $stored );
		$ok      = $wpdb->insert(
			$table,
			array(
				'form_id'      => $form_id,
				'track'        => $track,
				'ip'           => self::ip(),
				'form_title_x' => $title,
				'content'      => is_string( $encoded ) ? $encoded : '[]',
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
	 * @return list<array{label:string,value:string}>
	 */
	private static function answers( string $content ): array {
		$rows = json_decode( $content, true );
		if ( ! is_array( $rows ) ) {
			$rows = json_decode( str_replace( '\\', '', $content ), true );
		}
		if ( ! is_array( $rows ) ) {
			return array();
		}
		$out = array();
		foreach ( $rows as $row ) {
			if ( ! is_array( $row ) ) {
				continue;
			}
			$value = sanitize_text_field( self::str( $row['value'] ?? '' ) );
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

	private static function reply_text( string $content ): string {
		$decoded = json_decode( $content, true );
		if ( is_string( $decoded ) ) {
			return sanitize_textarea_field( $decoded );
		}
		return sanitize_textarea_field( $content );
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

	private static function ip(): string {
		$ip = function_exists( 'flx_client_ip' ) ? flx_client_ip() : '0.0.0.0';
		return substr( $ip, 0, 45 );
	}

	/**
	 * A stored scalar as text. Arrays and objects become an empty string.
	 */
	private static function str( mixed $value ): string {
		return is_scalar( $value ) ? (string) $value : '';
	}

	/**
	 * A stored number as int. Anything else is 0.
	 */
	private static function num( mixed $value ): int {
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
