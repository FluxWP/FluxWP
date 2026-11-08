<?php
/**
 * Formlara bağlanma. Başka eklentinin koduna dokunulmaz: WordPress ve WooCommerce kancaları, Flux Donate'in
 * bağış isteği (wp_loaded) ve korunan REST yolları (rest_pre_dispatch) kullanılır.
 *
 * @package FLX_Recaptcha
 */

defined( 'ABSPATH' ) || exit;

final class FLXRC_Integrations {

	public static function init() {
		if ( flxrc_disabled_by_constant() ) {
			return;
		}

		/* WordPress giriş ekranı (wp-login.php; Flux Speed özel adresi de aynı dosyayı yükler). */
		add_action( 'login_form', array( __CLASS__, 'print_login' ) );
		add_action( 'register_form', array( __CLASS__, 'print_register' ) );
		add_action( 'lostpassword_form', array( __CLASS__, 'print_lostpassword' ) );
		add_filter( 'authenticate', array( __CLASS__, 'verify_login' ), 99, 3 );
		add_filter( 'registration_errors', array( __CLASS__, 'verify_register' ), 10, 3 );
		add_action( 'lostpassword_post', array( __CLASS__, 'verify_lostpassword' ), 10, 1 );
		add_filter( 'shake_error_codes', array( __CLASS__, 'shake' ) );

		/* Yorumlar. */
		add_filter( 'comment_form_submit_field', array( __CLASS__, 'comment_field' ), 10, 1 );
		add_action( 'pre_comment_on_post', array( __CLASS__, 'verify_comment' ), 1 );

		/* WooCommerce Hesabım ve ödeme. */
		add_action( 'woocommerce_login_form', array( __CLASS__, 'print_wc_login' ) );
		add_action( 'woocommerce_register_form', array( __CLASS__, 'print_wc_register' ) );
		add_action( 'woocommerce_lostpassword_form', array( __CLASS__, 'print_lostpassword' ) );
		add_filter( 'woocommerce_process_login_errors', array( __CLASS__, 'verify_wc_login' ), 10, 1 );
		add_filter( 'woocommerce_process_registration_errors', array( __CLASS__, 'verify_wc_register' ), 10, 1 );
		add_action( 'woocommerce_review_order_before_submit', array( __CLASS__, 'print_checkout' ) );
		add_action( 'woocommerce_after_checkout_validation', array( __CLASS__, 'verify_checkout' ), 10, 2 );

		/* Flux Donate bağış formu: Flux Donate isteği wp_loaded 25'te işler; biz 24'te bakarız. */
		add_action( 'wp_loaded', array( __CLASS__, 'verify_donate' ), 24 );

		/* Sayfada korunan blok/kısa kod varsa betiği yükle (alanı ön yüz betiği ekler). */
		add_filter( 'render_block', array( __CLASS__, 'on_block' ), 10, 2 );
		add_filter( 'do_shortcode_tag', array( __CLASS__, 'on_shortcode' ), 10, 2 );

		/* REST yolları (blok ödeme, Flux Forms, başka eklentilerin kaydettiği yollar). */
		add_filter( 'rest_pre_dispatch', array( __CLASS__, 'verify_rest' ), 10, 3 );
	}

	/* ---------------------------------------------------------------- */
	/* WordPress giriş, kayıt, şifre sıfırlama                           */
	/* ---------------------------------------------------------------- */

	public static function print_login() {
		echo FLXRC_Front::field( 'login' ); // phpcs:ignore WordPress.Security.EscapeOutput
	}

	public static function print_register() {
		echo FLXRC_Front::field( 'register' ); // phpcs:ignore WordPress.Security.EscapeOutput
	}

	public static function print_lostpassword() {
		echo FLXRC_Front::field( 'lostpassword' ); // phpcs:ignore WordPress.Security.EscapeOutput
	}

	/** wp-login.php POST'u mu (giriş ekranından gelen form; XML-RPC, REST, WP-CLI, Flux Forms girişi değil). */
	private static function is_login_screen_post() {
		return did_action( 'login_init' ) && isset( $_SERVER['REQUEST_METHOD'] ) && 'POST' === $_SERVER['REQUEST_METHOD'];
	}

	public static function verify_login( $user, $username = '', $password = '' ) {
		unset( $password );
		// phpcs:ignore WordPress.Security.NonceVerification
		if ( ! self::is_login_screen_post() || '' === (string) $username || isset( $_POST['woocommerce-login-nonce'] ) ) {
			return $user;
		}
		$ok = FLXRC_Verify::check( 'login' );
		return is_wp_error( $ok ) ? new WP_Error( 'flxrc_failed', '<strong>' . esc_html__( 'Error', 'flx-recaptcha' ) . ':</strong> ' . esc_html( $ok->get_error_message() ) ) : $user;
	}

	public static function verify_register( $errors, $login = '', $email = '' ) {
		unset( $login, $email );
		if ( ! self::is_login_screen_post() ) {
			return $errors;
		}
		$ok = FLXRC_Verify::check( 'register' );
		if ( is_wp_error( $ok ) && $errors instanceof WP_Error ) {
			$errors->add( 'flxrc_failed', '<strong>' . esc_html__( 'Error', 'flx-recaptcha' ) . ':</strong> ' . esc_html( $ok->get_error_message() ) );
		}
		return $errors;
	}

	/** WordPress ve WooCommerce "şifremi unuttum" (ikisi de lostpassword_post çalıştırır). Yöneticinin "Sıfırlama bağlantısı gönder"i etkilenmez. */
	public static function verify_lostpassword( $errors ) {
		// phpcs:ignore WordPress.Security.NonceVerification
		if ( ! self::is_login_screen_post() && ! isset( $_POST['wc_reset_password'] ) ) {
			return;
		}
		$ok = FLXRC_Verify::check( 'lostpassword' );
		if ( is_wp_error( $ok ) && $errors instanceof WP_Error ) {
			$errors->add( 'flxrc_failed', did_action( 'login_init' ) ? '<strong>' . esc_html__( 'Error', 'flx-recaptcha' ) . ':</strong> ' . esc_html( $ok->get_error_message() ) : $ok->get_error_message() );
		}
	}

	public static function shake( $codes ) {
		$codes[] = 'flxrc_failed';
		return $codes;
	}

	/* ---------------------------------------------------------------- */
	/* Yorumlar                                                          */
	/* ---------------------------------------------------------------- */

	public static function comment_field( $submit_field ) {
		return FLXRC_Front::field( 'comments' ) . $submit_field;
	}

	public static function verify_comment() {
		$ok = FLXRC_Verify::check( 'comments' );
		if ( is_wp_error( $ok ) ) {
			wp_die(
				'<p>' . esc_html( $ok->get_error_message() ) . '</p>',
				esc_html__( 'Comment not sent', 'flx-recaptcha' ),
				array(
					'response'  => 403,
					'back_link' => true,
				)
			);
		}
	}

	/* ---------------------------------------------------------------- */
	/* WooCommerce                                                       */
	/* ---------------------------------------------------------------- */

	public static function print_wc_login() {
		echo FLXRC_Front::field( 'wc_login' ); // phpcs:ignore WordPress.Security.EscapeOutput
	}

	public static function print_wc_register() {
		echo FLXRC_Front::field( 'wc_register' ); // phpcs:ignore WordPress.Security.EscapeOutput
	}

	public static function print_checkout() {
		echo FLXRC_Front::field( 'checkout' ); // phpcs:ignore WordPress.Security.EscapeOutput
	}

	public static function verify_wc_login( $error ) {
		$ok = FLXRC_Verify::check( 'wc_login' );
		if ( is_wp_error( $ok ) ) {
			$error = $error instanceof WP_Error ? $error : new WP_Error();
			$error->add( 'flxrc_failed', $ok->get_error_message() );
		}
		return $error;
	}

	public static function verify_wc_register( $error ) {
		$ok = FLXRC_Verify::check( 'wc_register' );
		if ( is_wp_error( $ok ) ) {
			$error = $error instanceof WP_Error ? $error : new WP_Error();
			$error->add( 'flxrc_failed', $ok->get_error_message() );
		}
		return $error;
	}

	public static function verify_checkout( $data, $errors ) {
		unset( $data );
		$ok = FLXRC_Verify::check( 'checkout' );
		if ( is_wp_error( $ok ) && $errors instanceof WP_Error ) {
			$errors->add( 'flxrc_failed', $ok->get_error_message() );
		}
	}

	/* ---------------------------------------------------------------- */
	/* Flux Donate bağış formu                                           */
	/* ---------------------------------------------------------------- */

	/**
	 * Flux Donate "Bağış seçenekleri" formu (flxb_action=add). Doğrulama geçmezse istek Flux Donate'e
	 * ulaşmadan durur ve form, Flux Donate'in kendi hata kutusuyla girilen değerler korunarak yeniden gösterilir.
	 */
	public static function verify_donate() {
		// phpcs:ignore WordPress.Security.NonceVerification
		if ( empty( $_POST['flxb_action'] ) || 'add' !== $_POST['flxb_action'] || is_admin() ) {
			return;
		}
		$ok = FLXRC_Verify::check( 'donate' );
		if ( ! is_wp_error( $ok ) ) {
			return;
		}
		$in                         = wp_unslash( $_POST ); // phpcs:ignore WordPress.Security.NonceVerification
		$GLOBALS['flxb_form_error'] = $ok->get_error_message();
		$GLOBALS['flxb_form_input'] = is_array( $in ) ? $in : array();
		unset( $_POST['flxb_action'], $_REQUEST['flxb_action'] ); // phpcs:ignore WordPress.Security.NonceVerification
	}

	/* ---------------------------------------------------------------- */
	/* Betiğin yüklenmesi gereken bloklar ve kısa kodlar                 */
	/* ---------------------------------------------------------------- */

	public static function on_block( $html, $block ) {
		$map  = (array) apply_filters(
			'flxrc_block_contexts',
			array(
				'flx-wc-bagis/secenekler'  => 'donate',
				'easy-form-builder/form'   => 'forms',
				'woocommerce/checkout'     => 'checkout',
			)
		);
		$name = isset( $block['blockName'] ) ? (string) $block['blockName'] : '';
		if ( isset( $map[ $name ] ) && ! is_admin() ) {
			FLXRC_Front::need( $map[ $name ] );
		}
		return $html;
	}

	public static function on_shortcode( $output, $tag ) {
		$map = (array) apply_filters(
			'flxrc_shortcode_contexts',
			array(
				'flx_bagis_secenekleri' => 'donate',
				'EMS_Form_Builder'      => 'forms',
				'ems_form_builder'      => 'forms',
			)
		);
		if ( isset( $map[ $tag ] ) && ! is_admin() ) {
			FLXRC_Front::need( $map[ $tag ] );
		}
		return $output;
	}

	/* ---------------------------------------------------------------- */
	/* REST                                                              */
	/* ---------------------------------------------------------------- */

	/** Korunan REST yolu: belirteç X-Flx-Captcha başlığında. */
	public static function verify_rest( $result, $server, $request ) {
		unset( $server );
		if ( null !== $result || ! $request instanceof WP_REST_Request || 'POST' !== $request->get_method() ) {
			return $result;
		}
		$route = (string) $request->get_route();
		foreach ( flxrc_contexts() as $ctx => $c ) {
			foreach ( (array) $c['routes'] as $re ) {
				if ( ! @preg_match( $re, $route ) ) { // phpcs:ignore WordPress.PHP.NoSilencedErrors
					continue;
				}
				$token = (string) $request->get_header( 'x_flx_captcha' );
				$ok    = FLXRC_Verify::check( $ctx, $token );
				if ( true === $ok ) {
					return $result;
				}
				if ( $c['rest_error'] && is_callable( $c['rest_error'] ) ) {
					return call_user_func( $c['rest_error'], $ok->get_error_message() );
				}
				return new WP_Error( 'flxrc_failed', $ok->get_error_message(), array( 'status' => 403 ) );
			}
		}
		return $result;
	}
}
