<?php

/**
 * Class for parsing settings from advanced form captcha block.
 */
class Flx_Blocks_Form_Captcha_Settings {

	/**
	 * Public captcha Key
	 *
	 * @var bool|string
	 */
	public $public_key = false;

	/**
	 * Secret captcha Key
	 *
	 * @var bool|string
	 */
	public $secret_key = false;

	/**
	 * Captcha language
	 *
	 * @var bool|string
	 */
	public $language = 'auto';

	/**
	 * Are settings sourced from Flux Captcha plugin
	 *
	 * @var bool
	 */
	public $using_flxbk_captcha = false;

	/**
	 * Are settings sourced from Flux blocks globals
	 *
	 * @var bool
	 */
	public $using_flxbk_settings = true;

	/**
	 * Captcha service
	 * googlev2, googlev3, turnstile, or hcaptcha
	 *
	 * @var string
	 */
	public $service;

	/**
	 * Do we have required settings to show & validate captcha
	 *
	 * @var bool
	 */
	public $is_valid = false;

	public $pass_score = '0.5';

	public $theme = 'light';

	public $size = 'normal';

	// Properties for custom recaptcha notice settings
	public $hideRecaptcha = false;
	
	public $showRecaptchaNotice = false;
	
	public $recaptchaNotice = '';

	public function __construct( $attributes ) {
		$this->is_using_flxbk_captcha_settings( $attributes );
		$this->is_using_flxbk_settings( $attributes );
		$this->get_captcha_service( $attributes );
		$this->get_public_key( $attributes );
		$this->get_secret_key( $attributes );
		$this->get_captcha_language( $attributes );
		$this->get_styles( $attributes );
		$this->get_notice_settings( $attributes );
		$this->has_valid_settings();
	}

	private function has_valid_settings() {
		if ( ! empty( $this->service ) && ! empty( $this->public_key ) && ! empty( $this->secret_key ) ) {
			$this->is_valid = true;
		}
	}

	private function get_captcha_service( $attributes ) {
		if ( $this->using_flxbk_captcha ) {
			$key = array(
				0 => 'googlev2',
				1 => 'googlev3',
				2 => 'turnstile'
			);

			$captcha_key = $this->get_flxbk_captcha_stored_value( 'enable_v3' );
			if ( isset( $key[ $captcha_key ] ) ) {
				$this->service = $key[ $captcha_key ];
			}
		} elseif ( ! empty( $attributes['type'] ) ) {
			$this->service = $attributes['type'];
		} else {
			$this->service = 'googlev2'; // default value from block.json
		}
	}

	private function get_captcha_language( $attributes ) {
		if ( $this->using_flxbk_captcha ) {
			$stored_value   = $this->get_flxbk_captcha_stored_value( 'recaptcha_lang' );
			$filtered_value = apply_filters( 'fxt_recaptcha_option_value', $stored_value, 'recaptcha_lang' );

			$this->language = ! empty( $filtered_value ) ? $filtered_value : false;
		} elseif ( $this->using_flxbk_settings && ! empty( get_option( 'flxbk_recaptcha_language' ) ) ) {
			$this->language = get_option( 'flxbk_recaptcha_language' );
		} elseif ( isset( $attributes['recaptchaLanguage'] ) ) {
			$this->language =  $attributes['recaptchaLanguage'];
		}
	}

	private function is_using_flxbk_captcha_settings( $attributes ) {
		if ( isset( $attributes['useKcSettings'] ) && $attributes['useKcSettings'] ) {
			$this->using_flxbk_captcha = true;
		} else {
			$this->using_flxbk_captcha = false;
		}
	}

	private function is_using_flxbk_settings( $attributes ) {
		if ( !isset( $attributes['useKbSettings'] ) || ( isset( $attributes['useKbSettings'] ) && $attributes['useKbSettings'] ) ) {
			$this->using_flxbk_settings = true;
		} else {
			$this->using_flxbk_settings = false;
		}
	}

	private function get_public_key( $attributes ) {
		if ( $this->using_flxbk_captcha ) {
			$slug = '';
			$key = '';

			switch ( $this->service ) {
				case 'googlev2':
					$slug = 'fxt_re_site_key';
					break;
				case 'googlev3':
					$slug = 'v3_re_site_key';
					break;
				case 'turnstile':
					$slug = 'turnstile_site_key';
					break;
			}
			$key = $this->get_flxbk_captcha_stored_value( $slug, '' );
		} elseif ( ! empty( $this->service ) ) {
			if ( $this->using_flxbk_settings ) {
				$option_key = '';
				switch ( $this->service ) {
					case 'googlev2':
					case 'googlev3':
						$option_key = 'flxbk_recaptcha_site_key';
						break;
					case 'turnstile':
						$option_key = 'flxbk_turnstile_site_key';
						break;
					case 'hcaptcha':
						$option_key = 'flxbk_hcaptcha_site_key';
						break;
				}

				$key = get_option( $option_key );
			} else {
				switch ( $this->service ) {
					case 'googlev2':
					case 'googlev3':
						$option_key = 'recaptchaSiteKey';
						break;
					case 'turnstile':
						$option_key = 'turnstileSiteKey';
						break;
					case 'hcaptcha':
						$option_key = 'hCaptchaSiteKey';
						break;
				}
				$key = isset( $attributes[ $option_key ] ) ? $attributes[ $option_key ] : '';
			}
		}

		if ( ! empty( $key ) ) {
			$this->public_key = $key;
		} else {
			$this->public_key = false;
		}
	}

	private function get_secret_key( $attributes ) {
		if ( $this->using_flxbk_captcha ) {
			$slug = '';

			switch ( $this->service ) {
				case 'googlev2':
					$slug = 'fxt_re_secret_key';
					break;
				case 'googlev3':
					$slug = 'v3_re_secret_key';
					break;
				case 'turnstile':
					$slug = 'turnstile_secret_key';
					break;
			}

			$key = $this->get_flxbk_captcha_stored_value( $slug, '' );
		} elseif ( ! empty( $this->service ) ) {
			if ( $this->using_flxbk_settings ) {
				$option_key = '';
				switch ( $this->service ) {
					case 'googlev2':
					case 'googlev3':
						$option_key = 'flxbk_recaptcha_secret_key';
						break;
					case 'turnstile':
						$option_key = 'flxbk_turnstile_secret_key';
						break;
					case 'hcaptcha':
						$option_key = 'flxbk_hcaptcha_secret_key';
						break;
				}
				$key = get_option( $option_key );
			} else {
				switch ( $this->service ) {
					case 'googlev2':
					case 'googlev3':
						$option_key = 'recaptchaSecretKey';
						break;
					case 'turnstile':
						$option_key = 'turnstileSecretKey';
						break;
					case 'hcaptcha':
						$option_key = 'hCaptchaSecretKey';
						break;
				}
				$key = isset( $attributes[ $option_key ] ) ? $attributes[ $option_key ] : '';
			}
		}

		if ( ! empty( $key ) ) {
			$this->secret_key = $key;
		} else {
			$this->secret_key = false;
		}
	}

	/**
	 * Pull stored value from Flux Captcha Plugin
	 * Function pulled directly from that plugin
	 *
	 * @param $key
	 * @param $default
	 *
	 * @return mixed|string
	 */
	public function get_flxbk_captcha_stored_value( $key, $default = '' ) {
		// Get all stored values.
		$stored = ( apply_filters( 'flxbk_recaptcha_network', false ) ? get_site_option( 'fxt_recaptcha', array() ) : get_option( 'fxt_recaptcha', array() ) );

		// Check if value exists in stored values array.
		if ( ! empty( $stored ) && ! is_array( $stored ) ) {
			$stored = json_decode( $stored, true );
		}
		// Check if value exists in stored values array.
		if ( ! empty( $stored ) && ( ( isset( $stored[ $key ] ) && '0' == $stored[ $key ] ) || ! empty( $stored[ $key ] ) ) ) {
			return $stored[ $key ];
		}

		// Stored value not found, use default value.
		return $default;
	}

	private function get_styles( $attributes ) {
		if ( $this->using_flxbk_captcha ) {
			$this->pass_score = $this->get_flxbk_captcha_stored_value( 'v3_pass_score', '0.5' );
			$this->theme      = $this->get_flxbk_captcha_stored_value( 'fxt_re_theme', 'light' );
			$this->size       = $this->get_flxbk_captcha_stored_value( 'fxt_re_size', 'normal' );
		} else {
			if ( ! empty( $attributes['theme'] ) ) {
				$this->theme = $attributes['theme'];
			}

			if ( ! empty( $attributes['size'] ) ) {
				$this->size = $attributes['size'];
			}
		}
	}

	// Get notice settings from attributes
	private function get_notice_settings( $attributes ) {
		if ( isset( $attributes['hideRecaptcha'] ) ) {
			$this->hideRecaptcha = $attributes['hideRecaptcha'];
		}
		
		if ( isset( $attributes['showRecaptchaNotice'] ) ) {
			$this->showRecaptchaNotice = $attributes['showRecaptchaNotice'];
		}
		
		if ( isset( $attributes['recaptchaNotice'] ) ) {
			$this->recaptchaNotice = $attributes['recaptchaNotice'];
		}
	}
}
