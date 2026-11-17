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
	 * Upload field types the classic submit validates as files.
	 *
	 * @var list<string>
	 */
	private const FILE_TYPES = array( 'file', 'dadfile', 'audio_recorder', 'video_recorder', 'screen_recorder' );

	/**
	 * Radio types the classic submit matches through the option id.
	 *
	 * @var list<string>
	 */
	private const RADIO_TYPES = array( 'radio', 'payradio', 'chlradio', 'imgradio' );

	/**
	 * Translated texts the shared classic submit reads.
	 *
	 * @var list<string>
	 */
	private const TEXT_KEYS = array(
		'somethingWentWrongPleaseRefresh',
		'pleaseMakeSureAllFields',
		'PleaseFillForm',
		'bkXpM_',
		'bkFlM_',
		'mnvvXXX_',
		'ptrnMmm_',
		'ptrnMmx_',
		'error403',
		'errorSiteKeyM',
		'errorCaptcha',
		'errorMRobot',
		'pleaseEnterVaildValue',
		'msgdml',
		'thankFillForm',
		'trackNo',
		'vmgs',
		'WeRecivedUrM',
		'thankRegistering',
		'welcome',
		'thankSubscribing',
		'thankDonePoll',
		'newMessageReceived',
		'youRecivedNewMessage',
		'msgSndBut',
		'smsWPN',
		'spprt',
		'messageSent',
	);

	/**
	 * Types a visitor does not fill in.
	 *
	 * @var list<string>
	 */
	private const SKIP_TYPES = array( 'step', 'heading', 'html', 'link', 'raw', 'stripe', 'maps', 'password', 'esign', 'persiapay', 'paypal', 'booking', 'table_matrix' );

	/**
	 * The visitor's answers go through the same pipeline the classic submit runs: conditional logic,
	 * required fields, per-field checks, captcha and bot gates, classic storage and post-save effects.
	 *
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
		$token = isset( $input['captchaToken'] ) && is_string( $input['captchaToken'] ) && '' !== $input['captchaToken']
			? $input['captchaToken']
			: null;
		if ( function_exists( 'flx_captcha_gql_verify' ) ) {
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

		$title    = self::str( $load['row']['form_name'] );
		$pub      = self::prepare_public( $id, $title );
		$settings = self::settings_array( $pub );
		$cls      = self::classic_structure( $load['structure'] );
		$before   = $cls;
		$captcha  = $cls[0]['captcha'] ?? '';
		$lan      = is_array( $pub->lanText ) ? $pub->lanText : array();

		$rows = self::build_rows( self::given( $input['fields'] ?? array() ), $cls );
		if ( $rows instanceof WP_Error ) {
			return $rows;
		}
		$rows = $pub->flxf_strip_untouched_rows( $rows );
		if ( ! $rows ) {
			$miss = $pub->flxf_check_required( $cls, array() );
			if ( null !== $miss ) {
				return self::missing( $miss );
			}
			$none = isset( $lan['PleaseFillForm'] ) && is_string( $lan['PleaseFillForm'] ) ? $lan['PleaseFillForm'] : __( 'Please complete the form.', 'flx-forms' );
			return self::invalid( 'fields', self::plain( $none ) );
		}
		/** @var array<int,array<string,mixed>> $rows */
		$rows  = $pub->flxf_dedupe_rows( array_values( $rows ) );
		$logic = $pub->flxf_apply_conditional_logic( $cls, $rows );
		/** @var array<int,array<string,mixed>> $rows */
		$rows = $logic['submitted_values'];
		if ( null !== $logic['blocked'] ) {
			return self::invalid( 'fields', self::plain( $logic['blocked'] ) );
		}
		$miss = $pub->flxf_check_required( $cls, $rows );
		if ( null !== $miss ) {
			return self::missing( $miss );
		}

		$send       = false;
		$recipients = array();
		$phones     = array( array(), array() );
		$sms_config = self::str( $settings['sms_config'] ?? '' );
		$phn        = self::str( $settings['phnNo'] ?? '' );
		if ( 'wpsms' === $sms_config && strlen( $phn ) > 5 ) {
			$phones[0] = explode( ',', $phn );
		}
		$pub->flxf_head_recipients( $cls, $settings, $send, $recipients );
		$sms = 0;
		$val = $pub->flxf_validate_fields( $cls, $rows, $recipients, $phones, $sms, home_url(), $logic['is_conditional'] ? $logic['ignored_set'] : array() );
		/** @var array<int,array<string,mixed>> $cls */
		if ( 0 === (int) $val['is_valid'] ) {
			$field = self::str( $val['field_id'] );
			return self::invalid( '' !== $field ? 'fields.' . $field : 'fields', self::plain( self::str( $val['m'] ) ) );
		}
		$pub->value = $pub->flxf_encode_items( $val['items'] );

		$admin = $pub->flxf_admin_recipients( $settings, $send, $recipients );
		$gate  = $pub->flxf_submit_gates( $cls, $settings, $token, $type, $captcha );
		if ( null !== $gate['error'] ) {
			$text = isset( $lan[ $gate['error'] ] ) && is_string( $lan[ $gate['error'] ] ) ? self::plain( $lan[ $gate['error'] ] ) : __( 'The form could not be sent.', 'flx-forms' );
			if ( 'errorMRobot' === $gate['error'] ) {
				return self::error( 'FORBIDDEN', $text, 403 );
			}
			if ( 'errorSiteKeyM' === $gate['error'] ) {
				return self::error( 'INTERNAL', $text, 500 );
			}
			return self::invalid( 'captchaToken', $text );
		}

		$pub->name = $title;
		$pub->id   = $id;
		if ( $send ) {
			$pub->flxf_user_recipient( $cls, $rows, $recipients );
		}
		$style = is_object( $pub->setting ) && isset( $pub->setting->trackCodeStyle ) && is_string( $pub->setting->trackCodeStyle ) && '' !== $pub->setting->trackCodeStyle
			? $pub->setting->trackCodeStyle
			: 'date_en_mix';
		$track = self::str( $pub->insert_message_db( 0, false, $style ) );
		$msg   = self::message_id( $track );
		if ( $msg < 1 ) {
			return self::error( 'INTERNAL', __( 'The form could not be sent.', 'flx-forms' ), 500 );
		}
		if ( 'booking' === $val['form_condition'] && self::count_booking( $load['structure'], $before, $cls ) ) {
			self::save_structure( $id, $load['structure'] );
		}
		apply_filters(
			'flxf_submit_response',
			array( 'success' => true, 'track' => $track ),
			array(
				'event_type'       => 'form_submit',
				'track_code'       => $track,
				'form_id'          => $id,
				'submitted_values' => $rows,
				'form_fields'      => $cls,
				'page_url'         => '',
			)
		);
		$pub->flxf_dispatch_submit_effects( $track, $rows, $cls, $phones, home_url(), $send, $recipients, $pub->flxf_has_tracking_code( $cls ), $admin, $pub->pro_efb, $type );

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
		$pub    = \Emsfb\_Public::flxf_live();
		$code   = sanitize_text_field( $code );
		$legacy = '' === $code || ! $pub->flxf_is_strong_track_code( $code );
		$gate   = self::track_gate( $pub, $legacy );
		if ( $gate instanceof WP_Error ) {
			return $gate;
		}
		global $wpdb;
		/** @var wpdb $wpdb */
		$table = $wpdb->prefix . 'emsfb_msg_';
		$row   = '' === $code ? null : $wpdb->get_row(
			$wpdb->prepare( 'SELECT msg_id, track, content, date, form_title_x FROM %i WHERE track = %s LIMIT 1', $table, $code ),
			ARRAY_A
		);
		if ( ! is_array( $row ) ) {
			self::track_miss( $pub, $legacy );
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
		/*
		 * The classic reply needs the message id and the tracking code together. GraphQL takes only the
		 * code, so the id comes from the row that code names. The lookup is throttled and its misses
		 * are counted exactly like the tracker's.
		 */
		$pub    = \Emsfb\_Public::flxf_live();
		$legacy = '' === $code || ! $pub->flxf_is_strong_track_code( $code );
		$gate   = self::track_gate( $pub, $legacy );
		if ( $gate instanceof WP_Error ) {
			return $gate;
		}
		global $wpdb;
		/** @var wpdb $wpdb */
		$table = $wpdb->prefix . 'emsfb_msg_';
		$row   = '' === $code ? null : $wpdb->get_row(
			$wpdb->prepare( 'SELECT msg_id, track, form_id, content FROM %i WHERE track = %s LIMIT 1', $table, $code ),
			ARRAY_A
		);
		if ( ! is_array( $row ) ) {
			self::track_miss( $pub, $legacy );
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
		$rsp_id = (int) $wpdb->insert_id;
		$wpdb->update( $table, array( 'read_' => $admin ? 1 : 0 ), array( 'msg_id' => $msg_id ), array( '%d' ), array( '%d' ) );
		if ( $files && class_exists( '\Emsfb\Upload_Guard' ) ) {
			\Emsfb\Upload_Guard::release_response_attachment_reservations( $files );
			\Emsfb\Upload_Guard::release_pending_uploads( $files );
		}
		self::reply_effects( self::num( $row['form_id'] ?? 0 ), self::str( $row['content'] ?? '' ), $track, $admin );
		return array(
			'submission' => array(
				'id'        => (string) $rsp_id,
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

	/**
	 * @return list<FlxfAnswer>
	 */
	private static function answers( string $content ): array {
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

	private static function ip(): string {
		$ip = function_exists( 'flx_client_ip' ) ? flx_client_ip() : '0.0.0.0';
		return substr( $ip, 0, 45 );
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
	 * Per-IP and site-wide throttle of the classic tracker.
	 */
	private static function track_gate( \Emsfb\_Public $pub, bool $legacy ): ?WP_Error {
		if ( ! $pub->flxf_track_lookup_allowed() ) {
			return self::error( 'RATE_LIMITED', __( 'Too many attempts. Please wait a few minutes and try again.', 'flx-forms' ), 429 );
		}
		if ( $legacy && ! $pub->flxf_track_legacy_lookup_allowed() ) {
			return self::error( 'RATE_LIMITED', __( 'Too many attempts. Please wait a few minutes and try again.', 'flx-forms' ), 429 );
		}
		return null;
	}

	/**
	 * A lookup that found nothing counts against the caller, same as the classic tracker.
	 */
	private static function track_miss( \Emsfb\_Public $pub, bool $legacy ): void {
		$pub->flxf_track_register_failure();
		if ( $legacy ) {
			$pub->flxf_track_register_legacy_failure();
		}
	}

	private static function message_id( string $track ): int {
		if ( '' === $track ) {
			return 0;
		}
		global $wpdb;
		/** @var wpdb $wpdb */
		$table = $wpdb->prefix . 'emsfb_msg_';
		return self::num( $wpdb->get_var( $wpdb->prepare( 'SELECT msg_id FROM %i WHERE track = %s ORDER BY msg_id DESC LIMIT 1', $table, $track ) ) );
	}

	/**
	 * Classic message text is HTML (names in <b>). GraphQL errors are plain text.
	 */
	private static function plain( string $message ): string {
		return trim( html_entity_decode( wp_strip_all_tags( $message ), ENT_QUOTES, 'UTF-8' ) );
	}

	/**
	 * @param array{m:string,field_id:string,missing:list<array{id:string,m:string}>} $miss Result of the classic required check.
	 */
	private static function missing( array $miss ): WP_Error {
		if ( ! $miss['missing'] ) {
			$field = $miss['field_id'];
			return self::invalid( '' !== $field ? 'fields.' . $field : 'fields', self::plain( $miss['m'] ) );
		}
		$errors = new WP_Error();
		foreach ( $miss['missing'] as $n => $one ) {
			$errors->add( 'flxf_' . $n, self::plain( $one['m'] ), self::data( '' !== $one['id'] ? 'fields.' . $one['id'] : 'fields' ) );
		}
		return $errors;
	}

	/**
	 * Settings as the array the classic submit reads.
	 *
	 * @return array<string,mixed>
	 */
	private static function settings_array( \Emsfb\_Public $pub ): array {
		if ( ! is_object( $pub->setting ) ) {
			return array();
		}
		$json = wp_json_encode( $pub->setting );
		$out  = is_string( $json ) ? json_decode( $json, true ) : null;
		if ( ! is_array( $out ) ) {
			return array();
		}
		$clean = array();
		foreach ( $out as $key => $value ) {
			if ( is_string( $key ) ) {
				$clean[ $key ] = $value;
			}
		}
		return $clean;
	}

	/**
	 * The structure as the classic submit expects it: every field carries id_, embedded choice
	 * options become option rows under their parent, and fields a visitor cannot answer through
	 * GraphQL (hidden, disabled, display-only) are never required.
	 *
	 * @param array<int,array<string,mixed>> $structure Decoded structure.
	 * @return array<int,array<string,mixed>>
	 */
	private static function classic_structure( array $structure ): array {
		$out = array();
		foreach ( array_values( $structure ) as $i => $row ) {
			if ( 0 === $i ) {
				$out[] = $row;
				continue;
			}
			$fid = FLXF_Form_Service::field_id( $row );
			if ( '' !== $fid ) {
				$row['id_'] = $fid;
			}
			if ( FLXF_Form_Service::is_child_row( $row ) ) {
				$out[] = $row;
				continue;
			}
			$lower = sanitize_key( self::str( $row['type'] ?? '' ) );
			if ( FLXF_Form_Service::flag( $row['hidden'] ?? false ) || FLXF_Form_Service::flag( $row['disabled'] ?? false ) || in_array( $lower, self::SKIP_TYPES, true ) ) {
				$row['required'] = false;
			}
			$options = $row['options'] ?? null;
			unset( $row['options'] );
			$out[] = $row;
			if ( '' === $fid || ! is_array( $options ) ) {
				continue;
			}
			foreach ( array_values( $options ) as $k => $option ) {
				if ( ! is_array( $option ) ) {
					continue;
				}
				$sibling = array();
				foreach ( $option as $key => $value ) {
					if ( is_string( $key ) ) {
						$sibling[ $key ] = $value;
					}
				}
				$oid = FLXF_Form_Service::field_id( $sibling );
				if ( ! isset( $sibling['value'] ) && isset( $sibling['label'] ) ) {
					$sibling['value'] = $sibling['label'];
				}
				$sibling['id_']    = '' !== $oid ? $oid : $fid . '_o' . $k;
				$sibling['type']   = 'option';
				$sibling['parent'] = $fid;
				$out[]             = $sibling;
			}
		}
		return $out;
	}

	/**
	 * Visitor answers as the rows the form client posts, so the classic checks apply unchanged.
	 *
	 * @param array<string,string>           $given Answers by field id.
	 * @param array<int,array<string,mixed>> $cls   Classic structure.
	 * @return list<array<string,mixed>>|WP_Error
	 */
	private static function build_rows( array $given, array $cls ): array|WP_Error {
		$rows   = array();
		$errors = new WP_Error();
		$n      = 0;
		foreach ( $cls as $i => $field ) {
			if ( 0 === $i || FLXF_Form_Service::is_child_row( $field ) ) {
				continue;
			}
			if ( FLXF_Form_Service::flag( $field['hidden'] ?? false ) || FLXF_Form_Service::flag( $field['disabled'] ?? false ) ) {
				continue;
			}
			$type  = self::str( $field['type'] ?? '' );
			$lower = sanitize_key( $type );
			$fid   = self::str( $field['id_'] ?? '' );
			if ( '' === $type || '' === $fid || in_array( $lower, self::SKIP_TYPES, true ) || ! array_key_exists( $fid, $given ) ) {
				continue;
			}
			$value = trim( $given[ $fid ] );
			if ( strlen( $value ) > self::TEXT_MAX ) {
				$value = function_exists( 'mb_substr' ) ? mb_substr( $value, 0, self::TEXT_MAX, 'UTF-8' ) : substr( $value, 0, self::TEXT_MAX );
			}
			if ( '' === $value ) {
				continue;
			}
			$base = array(
				'id_'  => $fid,
				'name' => sanitize_text_field( self::str( $field['name'] ?? $field['label'] ?? $fid ) ),
				'type' => $type,
			);
			if ( in_array( $lower, self::FILE_TYPES, true ) ) {
				$url   = esc_url_raw( $value );
				$error = self::pending_upload( $url );
				if ( null !== $error ) {
					$errors->add( 'flxf_' . $n++, $error, self::data( 'fields.' . $fid ) );
					continue;
				}
				$rows[] = $base + array(
					'value' => '@file@',
					'url'   => $url,
				);
				continue;
			}
			if ( in_array( $lower, self::RADIO_TYPES, true ) || 'select' === $lower || 'payselect' === $lower ) {
				$hit    = self::find_option( $field, $cls, $value );
				$rows[] = $base + array(
					'value' => null === $hit ? $value : self::str( $hit['value'] ?? '' ),
					'id_ob' => null === $hit ? '' : FLXF_Form_Service::field_id( $hit ),
				);
				continue;
			}
			if ( str_contains( $lower, 'checkbox' ) ) {
				foreach ( self::tokens( $value ) as $token ) {
					$hit = self::find_option( $field, $cls, $token );
					if ( null === $hit ) {
						$errors->add( 'flxf_' . $n++, __( 'Choose one of the listed options.', 'flx-forms' ), self::data( 'fields.' . $fid ) );
						break;
					}
					$rows[] = $base + array(
						'value' => self::str( $hit['value'] ?? '' ),
						'id_ob' => FLXF_Form_Service::field_id( $hit ),
					);
				}
				continue;
			}
			if ( 'multiselect' === $lower ) {
				$picked = array();
				foreach ( self::tokens( $value ) as $token ) {
					$hit      = self::find_option( $field, $cls, $token );
					$picked[] = null === $hit ? $token : self::str( $hit['value'] ?? '' );
				}
				$rows[] = $base + array( 'value' => implode( '@efb!', $picked ) );
				continue;
			}
			if ( 'switch' === $lower ) {
				$rows[] = $base + array( 'value' => in_array( strtolower( $value ), array( '1', 'true', 'on', 'yes' ), true ) ? '1' : '0' );
				continue;
			}
			$rows[] = $base + array( 'value' => $value );
		}
		return $errors->has_errors() ? $errors : $rows;
	}

	/**
	 * @return list<string>
	 */
	private static function tokens( string $value ): array {
		$list = preg_split( '/\s*(?:,|@efb!)\s*/', $value );
		$out  = array();
		foreach ( is_array( $list ) ? $list : array() as $bit ) {
			$bit = trim( $bit );
			if ( '' !== $bit && ! in_array( $bit, $out, true ) ) {
				$out[] = $bit;
			}
		}
		return $out;
	}

	/**
	 * The option of a choice field a visitor's token names: by value or by option id.
	 *
	 * @param array<string,mixed>            $field Choice field.
	 * @param array<int,array<string,mixed>> $cls   Classic structure.
	 * @return array<string,mixed>|null
	 */
	private static function find_option( array $field, array $cls, string $token ): ?array {
		foreach ( FLXF_Form_Service::choice_rows( $field, $cls ) as $option ) {
			$ovalue = sanitize_text_field( self::str( $option['value'] ?? '' ) );
			$oid    = FLXF_Form_Service::field_id( $option );
			if ( $token === $ovalue || ( '' !== $oid && $token === $oid ) ) {
				return $option;
			}
		}
		return null;
	}

	/**
	 * Writes the booking counters the classic loop raised back to the stored structure.
	 *
	 * @param array<int,array<string,mixed>> $structure Stored structure, updated in place.
	 * @param array<int,array<string,mixed>> $before    Classic structure before validation.
	 * @param array<int,array<string,mixed>> $after     Classic structure after validation.
	 */
	private static function count_booking( array &$structure, array $before, array $after ): bool {
		$changed = false;
		foreach ( $after as $i => $row ) {
			if ( 'option' !== sanitize_key( self::str( $row['type'] ?? '' ) ) || ! isset( $before[ $i ] ) ) {
				continue;
			}
			if ( self::num( $row['registered_count'] ?? 0 ) > self::num( $before[ $i ]['registered_count'] ?? 0 ) ) {
				self::bump_booking( $structure, $row, self::str( $row['parent'] ?? '' ) );
				$changed = true;
			}
		}
		return $changed;
	}

	/**
	 * A file the upload endpoint stored and the pending ledger still holds. The classic loop then
	 * applies this field's size and extension rules.
	 *
	 * @return string|null Error message, or null when the file is on the ledger.
	 */
	private static function pending_upload( string $url ): ?string {
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
		return null;
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
				$text = $fn->text_efb( self::TEXT_KEYS );
				if ( is_array( $text ) ) {
					$pub->lanText = $text;
				}
			}
		}
		return $pub;
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

	private static function decode_json( string $content ): mixed {
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
	private static function moment( string $mysql ): array {
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
