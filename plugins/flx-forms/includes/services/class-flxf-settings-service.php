<?php
/**
 * Panel settings: read for the screen, validate and store.
 *
 * The classic panel posts the whole settings object to the `set_settings_Emsfb` AJAX handler. That
 * handler and the GraphQL mutation both call {@see FLXF_Settings_Service::save()}, so the same
 * checks, the same sanitising of the e-mail template, the same side options (weekly report, SMTP
 * status, dev mode) and the same stored row result. Secrets are write-only: reads say whether one is
 * saved, writes accept a new one (an empty value keeps the saved one, `__clear__` removes it).
 *
 * @package FLX_Forms
 */

defined( 'ABSPATH' ) || exit;

/**
 * Panel settings.
 *
 * @phpstan-type FlxfResponseStyle array{preset:string,brandColor:string,primary:string,primaryDark:string,accent:string,text:string,textMuted:string,bgCard:string,bgMeta:string,bgTrack:string,bgResp:string,bgEditor:string,editorText:string,editorPh:string,btnText:string,fontFamily:string,fontSize:string,customFont:string}
 * @phpstan-type FlxfSettings array{bootstrap:string,osLocationPicker:bool,version:string,sessionDuration:int,trackCodeStyle:string,scaptcha:bool,dsupfile:bool,activeDlBtn:bool,showIp:bool,adminSN:bool,responseStyle:FlxfResponseStyle,siteKey:string,secretKeySaved:bool,shieldSilentCaptcha:bool,shieldAvailable:bool,mapKeySaved:bool,emailSupporter:string,femail:string,smtp:bool,weeklyEmailReport:bool,emailStatsReport:bool,emailTemp:string,emailBtnBgColor:string,emailBtnTextColor:string,payProvider:string,stripePKey:string,paypalPKey:string,stripeSecretSaved:bool,paypalSecretSaved:bool,payTokenSaved:bool,smsConfig:string,smsPhoneSaved:bool}
 */
class FLXF_Settings_Service {

	public const CLEAR = '__clear__';

	/**
	 * Response colour and font keys with their defaults (the classic panel's own).
	 *
	 * @var array<string,string>
	 */
	public const STYLE_DEFAULTS = array(
		'respPrimary'     => '#3644d2',
		'respPrimaryDark' => '#202a8d',
		'respAccent'      => '#ffc107',
		'respText'        => '#1a1a2e',
		'respTextMuted'   => '#657096',
		'respBgCard'      => '#ffffff',
		'respBgMeta'      => '#f6f7fb',
		'respBgTrack'     => '#ffffff',
		'respBgResp'      => '#f8f9fd',
		'respBgEditor'    => '#ffffff',
		'respEditorText'  => '#1a1a2e',
		'respEditorPh'    => '#a0aec0',
		'respBtnText'     => '#ffffff',
		'respFontFamily'  => 'inherit',
		'respFontSize'    => '0.9rem',
		'respCustomFont'  => '',
		'respPreset'      => 'light',
		'respBrandColor'  => '#0f766e',
	);

	/**
	 * Track code styles the panel offers.
	 *
	 * @var list<string>
	 */
	public const TRACK_STYLES = array( 'date_num', 'date_local_mix', 'date_local_alpha', 'date_en_mix', 'date_local_num', 'unique_num', 'local_mix' );

	/**
	 * Stored key of each write-only secret on the update input.
	 *
	 * @var array<string,string>
	 */
	private const SECRETS = array(
		'secretKey'       => 'secretKey',
		'mapKey'          => 'apiKeyMap',
		'stripeSecretKey' => 'stripeSKey',
		'paypalSecretKey' => 'paypalSKey',
		'payToken'        => 'payToken',
		'smsPhoneNumbers' => 'phnNo',
	);

	/**
	 * Settings for the screen. Secrets are never returned.
	 *
	 * @return FlxfSettings
	 */
	public static function read(): array {
		$ac    = \Emsfb::get_setting_Emsfb( 'decoded' );
		$all   = is_object( $ac ) ? get_object_vars( $ac ) : array();
		$style = static fn( string $key ): string => self::text( $all, $key, self::STYLE_DEFAULTS[ $key ] ?? '' );
		$temp = self::text( $all, 'emailTemp', '' );
		$sms  = self::text( $all, 'sms_config', 'null' );
		return array(
			'bootstrap'           => self::text( $all, 'bootstrap', '' ),
			'osLocationPicker'    => self::flag( $all, 'osLocationPicker', false ),
			'version'             => self::text( $all, 'efb_version', '' ),
			'sessionDuration'     => max( 1, min( 7, (int) self::text( $all, 'sessionDuration', '1' ) ) ),
			'trackCodeStyle'      => in_array( self::text( $all, 'trackCodeStyle', 'date_en_mix' ), self::TRACK_STYLES, true ) ? self::text( $all, 'trackCodeStyle', 'date_en_mix' ) : 'date_en_mix',
			'scaptcha'            => self::flag( $all, 'scaptcha', false ),
			'dsupfile'            => self::flag( $all, 'dsupfile', true ),
			'activeDlBtn'         => self::flag( $all, 'activeDlBtn', true ),
			'showIp'              => self::flag( $all, 'showIp', false ),
			'adminSN'             => self::flag( $all, 'adminSN', false ),
			'responseStyle'       => array(
				'preset'      => $style( 'respPreset' ),
				'brandColor'  => $style( 'respBrandColor' ),
				'primary'     => $style( 'respPrimary' ),
				'primaryDark' => $style( 'respPrimaryDark' ),
				'accent'      => $style( 'respAccent' ),
				'text'        => $style( 'respText' ),
				'textMuted'   => $style( 'respTextMuted' ),
				'bgCard'      => $style( 'respBgCard' ),
				'bgMeta'      => $style( 'respBgMeta' ),
				'bgTrack'     => $style( 'respBgTrack' ),
				'bgResp'      => $style( 'respBgResp' ),
				'bgEditor'    => $style( 'respBgEditor' ),
				'editorText'  => $style( 'respEditorText' ),
				'editorPh'    => $style( 'respEditorPh' ),
				'btnText'     => $style( 'respBtnText' ),
				'fontFamily'  => $style( 'respFontFamily' ),
				'fontSize'    => $style( 'respFontSize' ),
				'customFont'  => $style( 'respCustomFont' ),
			),
			'siteKey'             => self::text( $all, 'siteKey', '' ),
			'secretKeySaved'      => '' !== self::text( $all, 'secretKey', '' ),
			'shieldSilentCaptcha' => self::flag( $all, 'shield_silent_captcha', false ),
			'shieldAvailable'     => self::shield_available(),
			'mapKeySaved'         => '' !== self::text( $all, 'apiKeyMap', '' ),
			'emailSupporter'      => self::text( $all, 'emailSupporter', '' ),
			'femail'              => self::text( $all, 'femail', '' ),
			'smtp'                => self::flag( $all, 'smtp', false ),
			'weeklyEmailReport'   => class_exists( '\Emsfb\Email_Monitor' ) ? (bool) \Emsfb\Email_Monitor::is_enabled() : self::flag( $all, 'weeklyEmailReport', true ),
			'emailStatsReport'    => class_exists( '\Emsfb\Email_Monitor' ) ? (bool) \Emsfb\Email_Monitor::is_email_stats_enabled() : self::flag( $all, 'emailStatsReport', true ),
			'emailTemp'           => self::unpack_url( $temp ),
			'emailBtnBgColor'     => self::text( $all, 'emailBtnBgColor', '#202a8d' ),
			'emailBtnTextColor'   => self::text( $all, 'emailBtnTextColor', '#ffffff' ),
			'payProvider'         => self::text( $all, 'flxfPayProvider', 'stripe' ),
			'stripePKey'          => self::text( $all, 'stripePKey', '' ),
			'paypalPKey'          => self::text( $all, 'paypalPKey', '' ),
			'stripeSecretSaved'   => '' !== self::text( $all, 'stripeSKey', '' ),
			'paypalSecretSaved'   => '' !== self::text( $all, 'paypalSKey', '' ),
			'payTokenSaved'       => '' !== self::text( $all, 'payToken', '' ),
			'smsConfig'           => in_array( $sms, array( 'wpsms', 'ws.team' ), true ) ? $sms : 'null',
			'smsPhoneSaved'       => ! in_array( self::text( $all, 'phnNo', 'null' ), array( '', 'null' ), true ),
		);
	}

	/**
	 * Validate and store. `$m` holds the settings keys to change (stored names); keys that are not
	 * in it stay as they are (the settings table keeps absent properties). The checks are the ones
	 * the classic panel runs. Returns an error code or null.
	 *
	 * @param array<mixed> $m Settings to change, stored key names.
	 * @return string|null `activationNcorrect`, `addSCEmailM`, `emailTemplate` or null on success.
	 */
	public static function save( array $m ): ?string {
		$efb_function       = \Emsfb::get_efbFunction();
		$ac                 = \Emsfb::get_setting_Emsfb( 'decoded' );
		$stored_active_code = is_object( $ac ) && isset( $ac->activeCode ) && is_scalar( $ac->activeCode ) ? trim( (string) $ac->activeCode ) : '';
		$active_code_is_valid    = false;
		$active_code_was_removed = false;
		foreach ( $m as $key => $value ) {
			$text = is_scalar( $value ) ? (string) $value : '';
			if ( in_array( $key, array( 'emailSupporter', 'femail' ), true ) ) {
				$m[ $key ] = sanitize_email( sanitize_text_field( $text ) );
			} elseif ( 'activeCode' === $key ) {
				if ( strlen( $text ) < 1 ) {
					/*
					 * A deliberately cleared field is a licence removal, but an already-empty
					 * Free/Free Plus field is not. The distinction prevents an ordinary Free Plus
					 * settings save from being changed to Free while still removing every copy of a
					 * Pro key when an administrator explicitly clears it.
					 */
					if ( '' !== $stored_active_code ) {
						$active_code_was_removed = true;
						$m['activeCode']         = '';
						$m['package_type']       = 2;
						update_option( 'emsfb_pro', 2 );
						delete_option( 'emsfb_pro_activeCode' );
						delete_option( 'emsfb_pro_ac_date' );
						delete_option( 'emsfb_license_failed_since' );
						delete_option( 'emsfb_license_fail_reason' );
						delete_option( 'emsfb_license_suspend_notified' );
					}
					continue;
				}
				$m['activeCode'] = sanitize_text_field( $text );
				$state           = $efb_function->is_efb_pro( $m['activeCode'] );
				if ( true === (bool) $state ) {
					$active_code_is_valid = true;
					$m['package_type']    = 1;
					update_option( 'emsfb_pro', 1 );
				} else {
					$active_code_is_valid = false;
					$m['package_type']    = 2;
					if ( strlen( $text ) > 1 ) {
						return 'activationNcorrect';
					}
				}
			} elseif ( 'package_type' === $key ) {
				// Keep the validated Pro package even if payload contains stale package_type.
				if ( $active_code_is_valid ) {
					$m[ $key ] = 1;
					continue;
				}
				if ( $active_code_was_removed ) {
					$m[ $key ] = 2;
					continue;
				}
				$package_type = intval( sanitize_text_field( $text ) );
				$m[ $key ]    = in_array( $package_type, array( 0, 1, 2, 3 ), true ) ? $package_type : 2;
			} elseif ( 'emailTemp' === $key ) {
				if ( strlen( $text ) > 5 && false === strpos( $text, 'shortcode_message' ) ) {
					return 'addSCEmailM';
				} elseif ( strlen( $text ) < 6 && strlen( $text ) > 0 ) {
					return 'emailTemplate';
				} elseif ( strlen( $text ) > 50001 ) {
					return 'addSCEmailM';
				}
				$v = str_replace( '@efb@', '/', $text );

				$efbdata_comment = '';
				if ( preg_match( '/<!--\s*EFBDATA:([\S]+)\s*-->/', $v, $efb_match ) ) {
					$efbdata_comment = $efb_match[0];
					$v               = str_replace( $efbdata_comment, '', $v );
				}

				$v = (string) preg_replace( '/<\s*script[^>]*>.*?<\s*\/\s*script\s*>/is', '', $v );
				$v = (string) preg_replace( '/<\s*script[^>]*>/i', '', $v );
				$v = (string) preg_replace( '/\bon\w+\s*=\s*(["\'][^"]*["\']|[^\s>]+)/i', '', $v );
				$v = (string) preg_replace( '/javascript\s*:/i', '', $v );
				$v = (string) preg_replace( '/vbscript\s*:/i', '', $v );
				$v = (string) preg_replace( '/data\s*:\s*text\/html/i', '', $v );
				$v = (string) preg_replace( '/data\s*:\s*text\/javascript/i', '', $v );
				$v = (string) preg_replace( '/data\s*:\s*application\//i', '', $v );
				$v = (string) preg_replace( '/expression\s*\(/i', '', $v );
				$v = (string) preg_replace( '/-moz-binding\s*:/i', '', $v );
				$v = (string) preg_replace( '/behavior\s*:/i', '', $v );
				$v = (string) preg_replace( '/<\s*\/?(iframe|object|embed|form|input|textarea|button|select|svg|path|math|base|link|applet)[^>]*>/i', '', $v );
				$v = FLXF_Submission_Service::str( $efb_function->sanitize_full_html_efb( $v ) );
				$v = str_replace( '"', "'", (string) $v );

				if ( $efbdata_comment ) {
					$v .= "\n" . $efbdata_comment;
				}

				$m[ $key ] = str_replace( '/', '@efb@', $v );
			} elseif ( 'weeklyEmailReport' === $key ) {
				if ( class_exists( '\Emsfb\Email_Monitor' ) && \Emsfb\Email_Monitor::can_manage_setting() ) {
					\Emsfb\Email_Monitor::update_enabled( $value );
				}
				$m[ $key ] = class_exists( '\Emsfb\Email_Monitor' ) ? \Emsfb\Email_Monitor::is_enabled() : true;
			} elseif ( 'emailStatsReport' === $key ) {
				if ( class_exists( '\Emsfb\Email_Monitor' ) && \Emsfb\Email_Monitor::can_manage_email_stats() ) {
					\Emsfb\Email_Monitor::update_email_stats_enabled( $value );
				}
				$m[ $key ] = class_exists( '\Emsfb\Email_Monitor' ) ? \Emsfb\Email_Monitor::is_email_stats_enabled() : true;
			} elseif ( 'smtp' === $key ) {
				if ( in_array( $value, array( 1, true, 'true', '1' ), true ) ) {
					$check = get_option( 'emsfb_email_status', false );
					if ( ! $check ) {
						update_option( 'emsfb_email_status', self::email_ready_status() );
					} elseif ( ! is_array( $check ) || ! isset( $check['status'] ) || ! in_array( $check['status'], array( 'ok_set_smtp', 'ok' ), true ) ) {
						update_option( 'emsfb_email_status', self::email_ready_status() );
					}
				}
			} else {
				$m[ $key ] = sanitize_text_field( $text );
			}
		}

		if ( false === isset( $m['efb_version'] ) ) {
			$m['efb_version'] = EMSFB_PLUGIN_VERSION;
		}

		if ( isset( $m['devMode'] ) ) {
			$dev_mode_value = in_array( $m['devMode'], array( true, 'true', 1, '1' ), true ) ? '1' : '0';
			update_option( 'emsfb_dev_mode', $dev_mode_value );
			unset( $m['devMode'] );
		}

		if ( $active_code_is_valid ) {
			$m['package_type'] = 1;
		} elseif ( $active_code_was_removed ) {
			$m['package_type'] = 2;
		}
		$m['package_type'] = 1; // Flux Forms: lisans yok, her zaman tam sürüm.

		$setting = wp_json_encode( $m, JSON_UNESCAPED_UNICODE );
		$email   = isset( $m['emailSupporter'] ) ? $m['emailSupporter'] : wp_get_current_user()->user_email;
		$efb_function->set_setting_Emsfb( is_string( $setting ) ? $setting : '{}', $email );
		return null;
	}

	/**
	 * Settings change from the screen. Only the fields in `$input` change.
	 *
	 * @param array<mixed> $input Mutation input.
	 * @return array{settings:FlxfSettings}|WP_Error
	 */
	public static function update( array $input ): array|WP_Error {
		$m = array();
		foreach ( array( 'bootstrap' ) as $key ) {
			if ( array_key_exists( $key, $input ) && is_string( $input[ $key ] ) ) {
				$m[ $key ] = self::clean_text( $input[ $key ] );
			}
		}
		foreach ( array(
			'osLocationPicker'    => 'osLocationPicker',
			'scaptcha'            => 'scaptcha',
			'dsupfile'            => 'dsupfile',
			'activeDlBtn'         => 'activeDlBtn',
			'showIp'              => 'showIp',
			'adminSN'             => 'adminSN',
			'shieldSilentCaptcha' => 'shield_silent_captcha',
			'smtp'                => 'smtp',
			'weeklyEmailReport'   => 'weeklyEmailReport',
			'emailStatsReport'    => 'emailStatsReport',
		) as $field => $key ) {
			if ( array_key_exists( $field, $input ) && is_bool( $input[ $field ] ) ) {
				$m[ $key ] = $input[ $field ];
			}
		}
		if ( array_key_exists( 'sessionDuration', $input ) && null !== $input['sessionDuration'] ) {
			$days = FLXF_Submission_Service::num( $input['sessionDuration'] );
			if ( $days < 1 || $days > 7 ) {
				return self::invalid( 'sessionDuration', __( 'Enter a number from 1 to 7.', 'flx-forms' ) );
			}
			$m['sessionDuration'] = $days;
		}
		if ( array_key_exists( 'trackCodeStyle', $input ) && null !== $input['trackCodeStyle'] ) {
			$style = FLXF_Submission_Service::str( $input['trackCodeStyle'] );
			if ( ! in_array( $style, self::TRACK_STYLES, true ) ) {
				return self::invalid( 'trackCodeStyle', __( 'Pick one of the listed styles.', 'flx-forms' ) );
			}
			$m['trackCodeStyle'] = $style;
		}
		foreach ( array( 'siteKey', 'stripePKey', 'paypalPKey' ) as $key ) {
			if ( array_key_exists( $key, $input ) && is_string( $input[ $key ] ) ) {
				$m[ $key ] = self::clean_text( $input[ $key ] );
			}
		}
		foreach ( array( 'emailSupporter', 'femail' ) as $key ) {
			if ( array_key_exists( $key, $input ) && is_string( $input[ $key ] ) ) {
				$mail = self::clean_text( $input[ $key ] );
				if ( '' !== $mail && ! is_email( $mail ) ) {
					return self::invalid( $key, __( 'Enter a valid email address.', 'flx-forms' ) );
				}
				$m[ $key ] = $mail;
			}
		}
		foreach ( array( 'emailBtnBgColor', 'emailBtnTextColor' ) as $key ) {
			if ( array_key_exists( $key, $input ) && is_string( $input[ $key ] ) ) {
				$color = self::color( $input[ $key ] );
				if ( null === $color ) {
					return self::invalid( $key, __( 'Enter a colour like #3644d2.', 'flx-forms' ) );
				}
				$m[ $key ] = $color;
			}
		}
		if ( isset( $input['responseStyle'] ) && is_array( $input['responseStyle'] ) ) {
			$style = self::style( $input['responseStyle'] );
			if ( $style instanceof WP_Error ) {
				return $style;
			}
			$m += $style;
		}
		if ( array_key_exists( 'emailTemp', $input ) && is_string( $input['emailTemp'] ) ) {
			$m['emailTemp'] = self::pack_url( $input['emailTemp'] );
		}
		if ( array_key_exists( 'payProvider', $input ) && is_string( $input['payProvider'] ) ) {
			$provider = sanitize_key( $input['payProvider'] );
			if ( '' === $provider ) {
				return self::invalid( 'payProvider', __( 'Pick a payment provider.', 'flx-forms' ) );
			}
			$m['flxfPayProvider'] = $provider;
		}
		if ( array_key_exists( 'smsConfig', $input ) && is_string( $input['smsConfig'] ) ) {
			if ( ! in_array( $input['smsConfig'], array( 'wpsms', 'ws.team', 'null' ), true ) ) {
				return self::invalid( 'smsConfig', __( 'Pick one of the listed SMS services.', 'flx-forms' ) );
			}
			$m['sms_config'] = $input['smsConfig'];
		}
		foreach ( self::SECRETS as $field => $key ) {
			if ( ! array_key_exists( $field, $input ) || ! is_string( $input[ $field ] ) || '' === $input[ $field ] ) {
				continue; // Empty keeps the saved value.
			}
			if ( self::CLEAR === $input[ $field ] ) {
				$m[ $key ] = 'phnNo' === $key ? 'null' : '';
				continue;
			}
			$value = self::clean_text( $input[ $field ] );
			if ( 'phnNo' === $key ) {
				foreach ( explode( ',', $value ) as $phone ) {
					if ( 1 !== preg_match( '/^\+\d{8,14}$/', (string) preg_replace( '/[\s\(\)\-]/', '', $phone ) ) ) {
						return self::invalid( $field, __( 'Enter phone numbers like +12345678900, separated by commas.', 'flx-forms' ) );
					}
				}
			}
			$m[ $key ] = $value;
		}
		if ( ! $m ) {
			return array( 'settings' => self::read() );
		}
		$err = self::save( $m );
		if ( null !== $err ) {
			return self::invalid( 'emailTemp', self::message( $err ) );
		}
		return array( 'settings' => self::read() );
	}

	/**
	 * Message of a save error code. The classic panel uses its own translated text for the same codes.
	 */
	public static function message( string $code ): string {
		switch ( $code ) {
			case 'emailTemplate':
				return __( 'Enter a valid email template.', 'flx-forms' );
			case 'activationNcorrect':
				return __( 'The activation code is not correct.', 'flx-forms' );
			default:
				return __( 'The email template has to contain the shortcode_message placeholder.', 'flx-forms' );
		}
	}

	/**
	 * `http://` and slashes the way the classic panel packs a template URL.
	 */
	public static function pack_url( string $value ): string {
		$value = preg_replace( '/(http:\/\/)+/', 'http:@efb@', $value ) ?? $value;
		$value = preg_replace( '/(https:\/\/)+/', 'https:@efb@', $value ) ?? $value;
		return preg_replace( '/([\/])+/', '@efb@', $value ) ?? $value;
	}

	/**
	 * Reverse of {@see pack_url()}.
	 */
	public static function unpack_url( string $value ): string {
		return str_replace( array( 'http:@efb@', 'https:@efb@', '@efb@' ), array( 'http://', 'https://', '/' ), $value );
	}

	/**
	 * Response style input to stored keys. Colours are hex; fonts are short text.
	 *
	 * @param array<mixed> $in Style input.
	 * @return array<string,string>|WP_Error
	 */
	private static function style( array $in ): array|WP_Error {
		$map = array(
			'preset'      => 'respPreset',
			'brandColor'  => 'respBrandColor',
			'primary'     => 'respPrimary',
			'primaryDark' => 'respPrimaryDark',
			'accent'      => 'respAccent',
			'text'        => 'respText',
			'textMuted'   => 'respTextMuted',
			'bgCard'      => 'respBgCard',
			'bgMeta'      => 'respBgMeta',
			'bgTrack'     => 'respBgTrack',
			'bgResp'      => 'respBgResp',
			'bgEditor'    => 'respBgEditor',
			'editorText'  => 'respEditorText',
			'editorPh'    => 'respEditorPh',
			'btnText'     => 'respBtnText',
			'fontFamily'  => 'respFontFamily',
			'fontSize'    => 'respFontSize',
			'customFont'  => 'respCustomFont',
		);
		$out = array();
		foreach ( $map as $field => $key ) {
			if ( ! array_key_exists( $field, $in ) || ! is_string( $in[ $field ] ) ) {
				continue;
			}
			if ( in_array( $field, array( 'preset', 'fontFamily', 'fontSize', 'customFont' ), true ) ) {
				$text = self::clean_text( $in[ $field ] );
				if ( strlen( $text ) > 200 ) {
					return self::invalid( 'responseStyle', __( 'The value is too long.', 'flx-forms' ) );
				}
				$out[ $key ] = $text;
				continue;
			}
			$color = self::color( $in[ $field ] );
			if ( null === $color ) {
				return self::invalid( 'responseStyle', __( 'Enter a colour like #3644d2.', 'flx-forms' ) );
			}
			$out[ $key ] = $color;
		}
		return $out;
	}

	/**
	 * A hex colour, lower-cased, or null.
	 */
	private static function color( string $value ): ?string {
		$value = strtolower( trim( $value ) );
		return 1 === preg_match( '/^#(?:[0-9a-f]{3}|[0-9a-f]{6})$/', $value ) ? $value : null;
	}

	private static function clean_text( string $value ): string {
		return sanitize_text_field( $value );
	}

	/**
	 * Is Shield there to answer the silent captcha? Asks the classic integration.
	 */
	private static function shield_available(): bool {
		return class_exists( 'Emsfb_Shield_SilentCaptcha_Integration' ) && Emsfb_Shield_SilentCaptcha_Integration::shield_available();
	}

	/**
	 * @return array<mixed>
	 */
	private static function email_ready_status(): array {
		return array(
			'status'  => 'ok_set_smtp',
			'message' => array(
				'title'       => 'configured',
				'description' => 'user configured email settings',
				'id'          => 'email_settings_configured',
			),
		);
	}

	/**
	 * A stored scalar as text; arrays and objects read as the default.
	 *
	 * @param array<mixed> $all     Stored settings.
	 * @param string              $key     Key.
	 * @param string              $default Value when the key is missing or not a scalar.
	 */
	private static function text( array $all, string $key, string $default ): string {
		if ( ! isset( $all[ $key ] ) || ! is_scalar( $all[ $key ] ) ) {
			return $default;
		}
		$value = (string) $all[ $key ];
		return 'null' === $value && 'sms_config' !== $key && 'phnNo' !== $key ? $default : $value;
	}

	/**
	 * A stored flag. The settings row keeps booleans, 1/0 and 'true'/'false' text.
	 *
	 * @param array<mixed> $all     Stored settings.
	 * @param string              $key     Key.
	 * @param bool                $default Value when the key is missing.
	 */
	private static function flag( array $all, string $key, bool $default ): bool {
		if ( ! array_key_exists( $key, $all ) || null === $all[ $key ] || 'null' === $all[ $key ] ) {
			return $default;
		}
		$v = $all[ $key ];
		if ( is_bool( $v ) ) {
			return $v;
		}
		return in_array( $v, array( 1, '1', 'true', 'on', 'yes' ), true );
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
}
