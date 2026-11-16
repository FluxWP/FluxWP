<?php
/**
 * Korunabilen formlar ("bağlamlar"). Başka eklentiler `flx_captcha_contexts` filtresiyle kendi formlarını ekler;
 * eklenen form panelde aç/kapa satırı olarak çıkar. Alanlar:
 *
 *   label      Panelde görünen ad.
 *   desc       Kısa açıklama.
 *   group      wordpress | woocommerce | fluxwp | other (paneldeki başlık).
 *   available  Formun sahibi kurulu mu (false ise satır soluk, "kurulu değil").
 *   default    Ayar hiç kaydedilmemişken açık mı (1/0).
 *   action     v3 eylem adı (Google konsolunda görünür; harf, rakam, / ve _).
 *   selector   (isteğe bağlı) Sayfadaki formu bulan CSS seçici: alan kendiliğinden eklenir (PHP kancası olmayan formlar için).
 *   before     (isteğe bağlı) Form içinde v2 kutucuğunun önüne konacağı öğe seçici (gönder düğmesi).
 *   routes     (isteğe bağlı) Korunan REST yolları (düzenli ifade, ör. '#^/my-plugin/v1/submit$#'). Belirteç
 *              `X-Flx-Captcha` başlığında gelir; ön yüz betiği fetch isteklerine kendisi ekler.
 *   rest_error (isteğe bağlı) callable( $message ): REST hata yanıtı (WP_REST_Response|WP_Error). Yoksa 403 WP_Error.
 *   users      Giriş yapmış kullanıcıya da sorulsun mu ("Giriş yapanlara sorma" ayarına bakmaz). Giriş/kayıt formları için false anlamsızdır.
 *
 * @package FLX_Recaptcha
 */

defined( 'ABSPATH' ) || exit;

function flxrc_contexts() {
	static $list = null;
	if ( null !== $list && did_action( 'init' ) ) {
		return $list;
	}
	$wc    = class_exists( 'WooCommerce' );
	$items = array(
		'login'        => array(
			'label'   => __( 'Login', 'flx-recaptcha' ),
			'desc'    => __( 'wp-login.php and the Flux Speed custom login address (e.g. /fluxadmin)', 'flx-recaptcha' ),
			'group'   => 'wordpress',
			'default' => 1,
			'action'  => 'login',
		),
		'register'     => array(
			'label'   => __( 'Registration', 'flx-recaptcha' ),
			'desc'    => get_option( 'users_can_register' ) ? __( 'WordPress registration form', 'flx-recaptcha' ) : __( 'WordPress registration form (registration is currently closed on this site)', 'flx-recaptcha' ),
			'group'   => 'wordpress',
			'default' => 1,
			'action'  => 'register',
		),
		'lostpassword' => array(
			'label'   => __( 'Password reset', 'flx-recaptcha' ),
			'desc'    => __( 'Lost password form: WordPress and WooCommerce My Account', 'flx-recaptcha' ),
			'group'   => 'wordpress',
			'default' => 1,
			'action'  => 'lostpassword',
		),
		'comments'     => array(
			'label'   => __( 'Comments', 'flx-recaptcha' ),
			'desc'    => __( 'Post and page comment form', 'flx-recaptcha' ),
			'group'   => 'wordpress',
			'default' => 1,
			'action'  => 'comment',
		),
		'wc_login'     => array(
			'label'     => __( 'Account login', 'flx-recaptcha' ),
			'desc'      => __( 'WooCommerce My Account login form', 'flx-recaptcha' ),
			'group'     => 'woocommerce',
			'available' => $wc,
			'default'   => 1,
			'action'    => 'wc_login',
		),
		'wc_register'  => array(
			'label'     => __( 'Account registration', 'flx-recaptcha' ),
			'desc'      => __( 'WooCommerce My Account registration form', 'flx-recaptcha' ),
			'group'     => 'woocommerce',
			'available' => $wc,
			'default'   => 1,
			'action'    => 'wc_register',
		),
		'checkout'     => array(
			'label'     => __( 'Checkout', 'flx-recaptcha' ),
			'desc'      => __( 'Checkout page (classic and block): store orders, Flux Donate and Flux Commerce payments', 'flx-recaptcha' ),
			'group'     => 'woocommerce',
			'available' => $wc,
			'default'   => 1,
			'action'    => 'checkout',
			'routes'    => array( '#^/wc/store(?:/v\d+)?/checkout$#' ),
		),
		'donate'       => array(
			'label'     => __( 'Donation form', 'flx-recaptcha' ),
			'desc'      => __( 'Flux Donate "Donation options" form (add to cart)', 'flx-recaptcha' ),
			'group'     => 'fluxwp',
			'available' => class_exists( 'FLXB_WC_Form' ) || defined( 'FLXB_VERSION' ),
			'default'   => 1,
			'action'    => 'donate',
			'selector'  => 'form.flxwb-form',
			'before'    => '.flxwb-btn--primary',
		),
		'forms'        => array(
			'label'      => __( 'Forms', 'flx-recaptcha' ),
			'desc'       => __( 'Flux Forms submissions (contact, applications, login/registration forms)', 'flx-recaptcha' ),
			'group'      => 'fluxwp',
			'available'  => defined( 'FLXF_PLUGIN_FILE' ) || defined( 'EMSFB_PLUGIN_DIRECTORY' ),
			'default'    => 1,
			'action'     => 'flux_forms',
			'selector'   => '[id^="body_efb_"]',
			'before'     => '#btn_send_efb',
			'routes'     => array( '#^/Emsfb/v1/forms/(?:message|response)/add$#' ),
			'rest_error' => 'flxrc_forms_rest_error',
		),
	);
	$items = (array) apply_filters( 'flx_captcha_contexts', $items );
	$out   = array();
	foreach ( $items as $k => $c ) {
		$k = sanitize_key( $k );
		if ( '' === $k || ! is_array( $c ) || empty( $c['label'] ) ) {
			continue;
		}
		$out[ $k ] = array_merge(
			array(
				'label'      => $k,
				'desc'       => '',
				'group'      => 'other',
				'available'  => true,
				'default'    => 0,
				'action'     => $k,
				'selector'   => '',
				'before'     => '',
				'routes'     => array(),
				'rest_error' => '',
				'users'      => false,
			),
			$c
		);
		$out[ $k ]['action'] = preg_replace( '#[^A-Za-z0-9/_]#', '_', (string) $out[ $k ]['action'] );
	}
	if ( did_action( 'init' ) ) {
		$list = $out;
	}
	return $out;
}

/** Panel grupları. */
function flxrc_groups() {
	return array(
		'wordpress'   => 'WordPress',
		'woocommerce' => 'WooCommerce',
		'fluxwp'      => 'FluxWP',
		'other'       => __( 'Other plugins', 'flx-recaptcha' ),
	);
}

/** Bu form panelde açık mı (anahtar/test durumuna bakmaz). */
function flxrc_context_on( $ctx ) {
	$all = flxrc_contexts();
	if ( ! isset( $all[ $ctx ] ) ) {
		return false;
	}
	$forms = (array) flxrc_settings( 'forms' );
	return array_key_exists( $ctx, $forms ) ? (bool) $forms[ $ctx ] : (bool) $all[ $ctx ]['default'];
}

/**
 * Bu istekte bu form korunuyor mu: genel koruma açık, form açık, form sahibi kurulu, IP izin listesinde değil,
 * giriş yapan kullanıcı muafsa kullanıcı giriş yapmamış. Filtre: flx_captcha_required.
 */
function flxrc_required( $ctx ) {
	$all = flxrc_contexts();
	$on  = flxrc_active() && isset( $all[ $ctx ] ) && ! empty( $all[ $ctx ]['available'] ) && flxrc_context_on( $ctx ) && ! flxrc_ip_allowed();
	if ( $on && is_user_logged_in() && flxrc_settings( 'skip_logged_in' ) && empty( $all[ $ctx ]['users'] ) ) {
		$on = false;
	}
	return (bool) apply_filters( 'flx_captcha_required', $on, $ctx );
}

/** Flux Forms ön yüzü hata yanıtını bu biçimde bekler (kendi reCAPTCHA hatasıyla aynı). */
function flxrc_forms_rest_error( $message ) {
	return new WP_REST_Response(
		array(
			'success' => true,
			'data'    => array(
				'success' => false,
				'm'       => $message,
			),
		),
		200
	);
}
