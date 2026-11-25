<?php
/**
 * Forms › Settings for the React screen (flxFormsSettings / flxFormsSettingsUpdate). The settings stay the classic
 * JSON row (emsfb_setting, get_setting_Emsfb / set_setting_Emsfb); a save merges the changed keys into it with the
 * same rules as the classic save (set_settings_Emsfb). Secrets are write-only. Payment keys and add-ons live on
 * their own pages; the localisation dictionary (text / act_local_efb) is an exception (.l10n.php).
 *
 * @package FLX_Forms
 */

defined( 'ABSPATH' ) || exit;

final class FLXF_Settings_Service {

	/** Track code styles (classic efb_build_track_options). */
	public const TRACK_STYLES = array( 'date_num', 'date_local_mix', 'date_local_alpha', 'date_en_mix', 'date_local_num', 'unique_num', 'local_mix' );

	/** SMS senders. */
	public const SMS = array( 'null', 'wp_sms_plugin', 'efb_sms_service' );

	/** Response box palette presets. */
	public const PRESETS = array( 'light', 'dark', 'brand', 'custom' );

	/**
	 * Settings keys: GraphQL field => [ classic key, kind, default ].
	 *
	 * @return array<string,array{0:string,1:string,2:mixed}>
	 */
	public static function fields(): array {
		return array(
			'bootstrap'           => array( 'bootstrap', 'bool', false ),
			'locationPicker'      => array( 'osLocationPicker', 'bool', false ),
			'sessionDuration'     => array( 'sessionDuration', 'days', 1 ),
			'trackCodeStyle'      => array( 'trackCodeStyle', 'track', 'date_en_mix' ),
			'shortCaptcha'        => array( 'scaptcha', 'bool', false ),
			'showUploadedFiles'   => array( 'dsupfile', 'bool', true ),
			'downloadButton'      => array( 'activeDlBtn', 'bool', true ),
			'adminSeesName'       => array( 'adminSN', 'bool', false ),
			'siteKey'             => array( 'siteKey', 'text', '' ),
			'silentCaptcha'       => array( 'shield_silent_captcha', 'bool', false ),
			'adminEmail'          => array( 'emailSupporter', 'email', '' ),
			'fromEmail'           => array( 'femail', 'email', '' ),
			'smtp'                => array( 'smtp', 'bool', false ),
			'weeklyReport'        => array( 'weeklyEmailReport', 'bool', true ),
			'emailStatsReport'    => array( 'emailStatsReport', 'bool', true ),
			'emailTemplate'       => array( 'emailTemp', 'html', '' ),
			'emailButtonColor'    => array( 'emailBtnBgColor', 'color', '#202a8d' ),
			'emailButtonText'     => array( 'emailBtnTextColor', 'color', '#ffffff' ),
			'smsSender'           => array( 'sms_config', 'sms', 'null' ),
			'smsAdminNumbers'     => array( 'phnNo', 'phones', '' ),
			'respPreset'          => array( 'respPreset', 'preset', 'light' ),
			'respBrandColor'      => array( 'respBrandColor', 'color', '#0f766e' ),
			'respPrimary'         => array( 'respPrimary', 'color', '#3644d2' ),
			'respPrimaryDark'     => array( 'respPrimaryDark', 'color', '#202a8d' ),
			'respAccent'          => array( 'respAccent', 'color', '#ffc107' ),
			'respText'            => array( 'respText', 'color', '#1a1a2e' ),
			'respTextMuted'       => array( 'respTextMuted', 'color', '#657096' ),
			'respBgCard'          => array( 'respBgCard', 'color', '#ffffff' ),
			'respBgMeta'          => array( 'respBgMeta', 'color', '#f6f7fb' ),
			'respBgTrack'         => array( 'respBgTrack', 'color', '#ffffff' ),
			'respBgResp'          => array( 'respBgResp', 'color', '#f8f9fd' ),
			'respBgEditor'        => array( 'respBgEditor', 'color', '#ffffff' ),
			'respEditorText'      => array( 'respEditorText', 'color', '#1a1a2e' ),
			'respEditorPh'        => array( 'respEditorPh', 'color', '#a0aec0' ),
			'respBtnText'         => array( 'respBtnText', 'color', '#ffffff' ),
			'respFontFamily'      => array( 'respFontFamily', 'text', 'inherit' ),
			'respFontSize'        => array( 'respFontSize', 'size', '0.9rem' ),
			'respCustomFont'      => array( 'respCustomFont', 'text', '' ),
		);
	}

	/**
	 * The stored settings as an array.
	 *
	 * @return array<string,mixed>
	 */
	private static function stored(): array {
		if ( function_exists( 'get_setting_Emsfb' ) ) {
			$raw = get_setting_Emsfb( 'raw' );
			$dec = is_string( $raw ) && '' !== $raw ? json_decode( $raw, true ) : null;
			if ( ! is_array( $dec ) && is_string( $raw ) ) {
				$dec = json_decode( stripslashes( $raw ), true );
			}
			if ( is_array( $dec ) ) {
				/** @var array<string,mixed> $dec */
				return $dec;
			}
		}
		return array();
	}

	/**
	 * Settings for the screen.
	 *
	 * @return array<string,mixed>
	 */
	public static function get(): array {
		$s   = self::stored();
		$out = array();
		foreach ( self::fields() as $field => $spec ) {
			list( $key, $kind, $default ) = $spec;
			$v             = array_key_exists( $key, $s ) && 'null' !== $s[ $key ] ? $s[ $key ] : $default;
			$out[ $field ] = self::read( $kind, $v, $default );
		}
		if ( '' === $out['fromEmail'] ) {
			$host              = (string) wp_parse_url( home_url(), PHP_URL_HOST );
			$out['fromEmail']  = '';
			$out['fromHint']   = 'no-reply@' . ( '' !== $host ? $host : 'example.com' );
		} else {
			$out['fromHint'] = '';
		}
		$out['devMode']       = '1' === self::str( get_option( 'emsfb_dev_mode', '0' ) );
		$out['secretKeySet']  = '' !== self::str( $s['secretKey'] ?? '' ) && 'null' !== self::str( $s['secretKey'] ?? '' );
		$out['trackShortcode'] = '[Easy_Form_Builder_confirmation_code_finder]';
		$out['canEmailReport'] = ! class_exists( '\Emsfb\Email_Monitor' ) || \Emsfb\Email_Monitor::can_manage_setting();
		$out['canEmailStats']  = ! class_exists( '\Emsfb\Email_Monitor' ) || \Emsfb\Email_Monitor::can_manage_email_stats();
		if ( class_exists( '\Emsfb\Email_Monitor' ) ) {
			$out['weeklyReport']     = \Emsfb\Email_Monitor::is_enabled();
			$out['emailStatsReport'] = \Emsfb\Email_Monitor::is_email_stats_enabled();
		}
		return $out;
	}

	/**
	 * Saves the given fields (others stay as they are).
	 *
	 * @param array<string,mixed> $in Fields from the screen.
	 * @return array<string,mixed>|WP_Error Settings after the save.
	 */
	public static function update( array $in ): array|WP_Error {
		$s      = self::stored();
		$fields = self::fields();
		foreach ( $in as $field => $value ) {
			if ( ! isset( $fields[ $field ] ) || null === $value ) {
				continue;
			}
			list( $key, $kind ) = $fields[ $field ];
			$clean              = self::write( $kind, $value );
			if ( $clean instanceof WP_Error ) {
				return $clean;
			}
			if ( 'weeklyEmailReport' === $key && class_exists( '\Emsfb\Email_Monitor' ) ) {
				if ( \Emsfb\Email_Monitor::can_manage_setting() ) {
					\Emsfb\Email_Monitor::update_enabled( $clean );
				}
				$clean = \Emsfb\Email_Monitor::is_enabled();
			}
			if ( 'emailStatsReport' === $key && class_exists( '\Emsfb\Email_Monitor' ) ) {
				if ( \Emsfb\Email_Monitor::can_manage_email_stats() ) {
					\Emsfb\Email_Monitor::update_email_stats_enabled( $clean );
				}
				$clean = \Emsfb\Email_Monitor::is_email_stats_enabled();
			}
			$s[ $key ] = $clean;
		}
		if ( isset( $in['secretKey'] ) && is_string( $in['secretKey'] ) && '' !== trim( $in['secretKey'] ) ) {
			$s['secretKey'] = sanitize_text_field( $in['secretKey'] );
		} elseif ( ! empty( $in['clearSecretKey'] ) ) {
			$s['secretKey'] = '';
		}
		if ( isset( $in['devMode'] ) ) {
			update_option( 'emsfb_dev_mode', ! empty( $in['devMode'] ) ? '1' : '0' );
		}
		if ( ! empty( $s['smtp'] ) ) {
			$check = get_option( 'emsfb_email_status', false );
			if ( ! is_array( $check ) || ! isset( $check['status'] ) || ! in_array( $check['status'], array( 'ok_set_smtp', 'ok' ), true ) ) {
				update_option(
					'emsfb_email_status',
					array(
						'status'  => 'ok_set_smtp',
						'message' => array(
							'title'       => 'configured',
							'description' => 'user configured email settings',
							'id'          => 'email_settings_configured',
						),
					)
				);
			}
		}
		if ( ! isset( $s['efb_version'] ) && defined( 'EMSFB_PLUGIN_VERSION' ) ) {
			$s['efb_version'] = EMSFB_PLUGIN_VERSION;
		}
		$s['package_type'] = 1; // Flux Forms: no licence, always the full version.
		unset( $s['devMode'] );
		$json  = (string) wp_json_encode( $s, JSON_UNESCAPED_UNICODE );
		$email = self::str( $s['emailSupporter'] ?? '' );
		$efb   = function_exists( 'get_efbFunction' ) ? get_efbFunction() : null;
		if ( ! is_object( $efb ) || ! method_exists( $efb, 'set_setting_Emsfb' ) ) {
			return new WP_Error( 'flxf_settings', __( 'The settings could not be saved.', 'flx-forms' ), array( 'graphql_code' => 'INTERNAL' ) );
		}
		$efb->set_setting_Emsfb( $json, '' !== $email ? $email : (string) wp_get_current_user()->user_email );
		wp_cache_delete( 'settings:raw', 'emsfb' );
		wp_cache_delete( 'settings:decoded', 'emsfb' );
		wp_cache_delete( 'emsfb_settings', 'emsfb' );
		delete_transient( 'emsfb_settings_transient' );
		if ( function_exists( 'get_setting_Emsfb' ) ) {
			get_setting_Emsfb( '_clear_cache' );
		}
		return self::get();
	}

	/**
	 * Deletes uploaded files no response points to (classic clear_garbeg_admin).
	 */
	public static function clear_files(): int {
		global $wpdb;
		/** @var wpdb $wpdb */
		$urls = array();
		foreach ( (array) $wpdb->get_col( $wpdb->prepare( 'SELECT content FROM %i WHERE content LIKE %s', $wpdb->prefix . 'emsfb_msg_', '%url%' ) ) as $content ) {
			$json = json_decode( str_replace( '\\', '', self::str( $content ) ), true );
			foreach ( is_array( $json ) ? $json : array() as $row ) {
				if ( is_array( $row ) && isset( $row['url'] ) && is_string( $row['url'] ) && '' !== $row['url'] ) {
					$urls[] = $row['url'];
				}
			}
		}
		$all     = (string) wp_json_encode( $urls );
		$upload  = wp_upload_dir();
		$deleted = 0;
		if ( ! function_exists( 'list_files' ) ) {
			require_once ABSPATH . 'wp-admin/includes/file.php';
		}
		foreach ( (array) list_files( $upload['basedir'] ) as $file ) {
			$file = self::str( $file );
			if ( false === strpos( $file, 'emsfb-PLG-' ) ) {
				continue;
			}
			$name = (string) strrchr( $file, '/' );
			if ( false === strpos( $all, $name ) && false === strpos( $all, str_replace( '/', '\/', $name ) ) ) {
				wp_delete_file( $file );
				++$deleted;
			}
		}
		return $deleted;
	}

	/**
	 * A stored value as the screen reads it.
	 *
	 * @param mixed $v       Stored value.
	 * @param mixed $default Default.
	 * @return mixed
	 */
	private static function read( string $kind, $v, $default ) {
		switch ( $kind ) {
			case 'bool':
				return in_array( $v, array( true, 1, '1', 'true' ), true );
			case 'days':
				$n = (int) self::str( $v );
				return $n >= 1 && $n <= 7 ? $n : 1;
			case 'html':
				return str_replace( '@efb@', '/', self::str( $v ) );
			case 'track':
				return in_array( $v, self::TRACK_STYLES, true ) ? $v : $default;
			case 'sms':
				return in_array( $v, self::SMS, true ) ? $v : 'null';
			case 'preset':
				return in_array( $v, self::PRESETS, true ) ? $v : 'light';
			case 'phones':
				$t = self::str( $v );
				return 'null' === $t ? '' : $t;
			default:
				return self::str( $v );
		}
	}

	/**
	 * A screen value as the classic save stores it.
	 *
	 * @param mixed $v Value from the screen.
	 * @return mixed|WP_Error
	 */
	private static function write( string $kind, $v ) {
		switch ( $kind ) {
			case 'bool':
				return ! empty( $v ) && 'false' !== $v;
			case 'days':
				$n = (int) self::str( $v );
				return $n >= 1 && $n <= 7 ? (string) $n : self::invalid( 'sessionDuration', __( 'Choose between 1 and 7 days.', 'flx-forms' ) );
			case 'track':
				return in_array( $v, self::TRACK_STYLES, true ) ? $v : self::invalid( 'trackCodeStyle', __( 'Unknown track code style.', 'flx-forms' ) );
			case 'sms':
				return in_array( $v, self::SMS, true ) ? $v : self::invalid( 'smsSender', __( 'Unknown SMS sender.', 'flx-forms' ) );
			case 'preset':
				return in_array( $v, self::PRESETS, true ) ? $v : 'custom';
			case 'color':
				$c = sanitize_hex_color( self::str( $v ) );
				return is_string( $c ) && '' !== $c ? $c : self::invalid( 'color', __( 'Enter a colour like #1a2b3c.', 'flx-forms' ) );
			case 'size':
				$t = trim( self::str( $v ) );
				return preg_match( '/^\d+(\.\d+)?(px|rem|em|%)$/', $t ) ? $t : '0.9rem';
			case 'email':
				$t = trim( self::str( $v ) );
				if ( '' === $t ) {
					return '';
				}
				$e = sanitize_email( $t );
				return '' !== $e && is_email( $e ) ? $e : self::invalid( 'email', __( 'Enter a valid e-mail address.', 'flx-forms' ) );
			case 'phones':
				$t = trim( self::str( $v ) );
				if ( '' === $t ) {
					return 'null';
				}
				return preg_match( '/^[+0-9()\-\s,]+$/', $t ) ? $t : self::invalid( 'smsAdminNumbers', __( 'Use numbers like +12345678900, separated by commas.', 'flx-forms' ) );
			case 'html':
				return self::template( self::str( $v ) );
			default:
				return sanitize_text_field( self::str( $v ) );
		}
	}

	/**
	 * The e-mail template with the classic checks and clean-up.
	 *
	 * @return string|WP_Error
	 */
	private static function template( string $v ) {
		$v = trim( $v );
		if ( '' === $v ) {
			return '';
		}
		if ( strlen( $v ) > 50001 || false === strpos( $v, 'shortcode_message' ) ) {
			return self::invalid( 'emailTemplate', __( 'The template must contain the shortcode_message shortcode.', 'flx-forms' ) );
		}
		$data = '';
		if ( preg_match( '/<!--\s*EFBDATA:([\S]+)\s*-->/', $v, $m ) ) {
			$data = $m[0];
			$v    = str_replace( $data, '', $v );
		}
		$rules = array(
			'/<\s*script[^>]*>.*?<\s*\/\s*script\s*>/is',
			'/<\s*script[^>]*>/i',
			'/\bon\w+\s*=\s*(["\'][^"]*["\']|[^\s>]+)/i',
			'/javascript\s*:/i',
			'/vbscript\s*:/i',
			'/data\s*:\s*text\/html/i',
			'/data\s*:\s*text\/javascript/i',
			'/data\s*:\s*application\//i',
			'/expression\s*\(/i',
			'/-moz-binding\s*:/i',
			'/behavior\s*:/i',
			'/<\s*\/?(iframe|object|embed|form|input|textarea|button|select|svg|path|math|base|link|applet)[^>]*>/i',
		);
		foreach ( $rules as $rule ) {
			$v = (string) preg_replace( $rule, '', $v );
		}
		$efb = function_exists( 'get_efbFunction' ) ? get_efbFunction() : null;
		if ( is_object( $efb ) && method_exists( $efb, 'sanitize_full_html_efb' ) ) {
			$v = self::str( $efb->sanitize_full_html_efb( $v ) );
		} else {
			$v = wp_kses_post( $v );
		}
		$v = str_replace( '"', "'", $v );
		if ( '' !== $data ) {
			$v .= "\n" . $data;
		}
		return str_replace( '/', '@efb@', $v );
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
}
