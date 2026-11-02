<?php
/**
 * Google ile giriş: Google e-posta adresini zaten doğruladığı için bu kişilere
 * "parolanızı belirleyin" (WordPress) ve "hesabınız oluşturuldu" (WooCommerce) e-postaları gitmez,
 * hesap "e-postası doğrulanmış" olarak işaretlenir.
 *
 * Hangi eklentiyle giriş yapıldığından bağımsız çalışır: kayıt isteğinde Google OAuth izleri aranır
 * (Nextend Social Login, Site Kit "Google ile oturum aç", Super Socializer, miniOrange, WooCommerce Social Login vb.).
 *
 * @package flx-mail
 */

defined( 'ABSPATH' ) || exit;

/** Bilinen Google giriş eklentileri: dosya => ad. */
function flxi_social_plugins() {
	return array(
		'nextend-facebook-connect/nextend-facebook-connect.php' => 'Nextend Social Login',
		'nextend-social-login-pro/nextend-social-login-pro.php' => 'Nextend Social Login Pro',
		'google-site-kit/google-site-kit.php'                   => 'Site Kit by Google',
		'super-socializer/super_socializer.php'                 => 'Super Socializer',
		'miniorange-login-openid/miniorange_openid_sso_settings.php' => 'miniOrange Social Login',
		'woocommerce-social-login/woocommerce-social-login.php' => 'WooCommerce Social Login',
		'wordpress-social-login/wp-social-login.php'            => 'WordPress Social Login',
		'oa-social-login/oa-social-login.php'                   => 'OneAll Social Login',
		'login-with-google/login-with-google.php'               => 'Login with Google',
		'loginpress-pro/loginpress-pro.php'                     => 'LoginPress Pro',
		'social-login-wp/social-login-wp.php'                   => 'Social Login WP',
		'wp-social/wp-social.php'                               => 'WP Social',
		'heateor-social-login/heateor-social-login.php'         => 'Heateor Social Login',
		'google-login/google_login.php'                         => 'Google Login',
	);
}

function flxi_active_social_plugins() {
	$active = (array) get_option( 'active_plugins', array() );
	$out    = array();
	foreach ( flxi_social_plugins() as $file => $name ) {
		if ( in_array( $file, $active, true ) ) {
			$out[ $file ] = $name;
		}
	}
	return $out;
}

/** Bu istek bir Google (OAuth) giriş/kayıt isteği mi? */
function flxi_is_google_request() {
	static $res = null;
	if ( null !== $res ) {
		return $res;
	}
	// phpcs:disable WordPress.Security.NonceVerification
	$req  = array_change_key_case( array_merge( $_GET, $_POST ), CASE_LOWER );
	$uri  = strtolower( isset( $_SERVER['REQUEST_URI'] ) ? wp_unslash( $_SERVER['REQUEST_URI'] ) : '' ); // phpcs:ignore
	$ref  = strtolower( isset( $_SERVER['HTTP_REFERER'] ) ? wp_unslash( $_SERVER['HTTP_REFERER'] ) : '' ); // phpcs:ignore
	// phpcs:enable
	$flat = strtolower( http_build_query( $req ) ) . ' ' . $uri;
	$res  = false;
	if ( false !== strpos( $ref, 'accounts.google.' ) ) {
		$res = true; // Google'dan dönüş
	} elseif ( isset( $req['loginsocial'] ) && 'google' === strtolower( (string) $req['loginsocial'] ) ) {
		$res = true; // Nextend
	} elseif ( false !== strpos( $uri, 'googlesitekit_auth' ) || false !== strpos( $uri, 'action=googlesitekit' ) || isset( $req['googlesitekit_auth'] ) ) {
		$res = true; // Site Kit: Google ile oturum aç
	} elseif ( preg_match( '/(provider|social|loginradius|sociallogin|login_provider|mo_openid_login|hauth_done|hauth\.done|oauth)=?[^&\s]*google/', $flat ) ) {
		$res = true; // Super Socializer, miniOrange, WSL, WooCommerce Social Login vb.
	} elseif ( isset( $req['credential'] ) && isset( $req['g_csrf_token'] ) ) {
		$res = true; // Google One Tap / Google Identity Services
	} elseif ( isset( $req['code'], $req['state'] ) && ( false !== strpos( $flat, 'google' ) || isset( $req['scope'] ) && false !== strpos( strtolower( (string) $req['scope'] ), 'googleapis' ) ) ) {
		$res = true; // Genel OAuth dönüşü (scope=...googleapis.com...)
	}
	$res = (bool) apply_filters( 'flxi_is_google_request', $res );
	return $res;
}

/** Kullanıcı Google ile mi oluşturuldu? Kayıt sırasında işaretlenir; eklentilerin kendi meta kayıtlarına da bakılır. */
function flxi_user_is_social( $user_id ) {
	$user_id = (int) $user_id;
	if ( ! $user_id ) {
		return false;
	}
	if ( get_user_meta( $user_id, 'flxi_social', true ) ) {
		return true;
	}
	global $wpdb;
	$known_meta = array( 'googlesitekit_siwg_google_user_hid', $wpdb->prefix . 'googlesitekit_siwg_google_user_hid', 'thechamp_provider', 'heateor_ss_provider', 'mo_openid_user_profile' );
	foreach ( $known_meta as $m ) {
		$v = get_user_meta( $user_id, $m, true );
		if ( $v && ( 'thechamp_provider' !== $m && 'heateor_ss_provider' !== $m || 'google' === strtolower( (string) $v ) ) ) {
			return true;
		}
	}
	// Nextend: kendi tablosu
	$t = $wpdb->prefix . 'social_users';
	if ( $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $t ) ) === $t ) { // phpcs:ignore
		$type = $wpdb->get_var( $wpdb->prepare( "SELECT type FROM $t WHERE ID = %d LIMIT 1", $user_id ) ); // phpcs:ignore
		if ( 'google' === $type ) {
			return true;
		}
	}
	return (bool) apply_filters( 'flxi_user_is_social', false, $user_id );
}

/** Kayıt anında işaretle ve "e-posta doğrulandı" bilgisini yaz. */
add_action(
	'user_register',
	function ( $user_id ) {
		if ( ! flxi_is_google_request() ) {
			return;
		}
		update_user_meta( $user_id, 'flxi_social', 'google' );
		update_user_meta( $user_id, 'flxi_social_at', time() );
		if ( flxi_setting( 'social_verified' ) ) {
			flxi_mark_email_verified( $user_id );
		}
	},
	1
);

/** Yaygın doğrulama eklentilerinin "doğrulandı" kayıtları. */
function flxi_mark_email_verified( $user_id ) {
	update_user_meta( $user_id, 'flxi_email_verified', time() );
	$meta = apply_filters(
		'flxi_verified_meta',
		array(
			'alg_wc_ev_is_activated'   => 1,     // Email Verification for WooCommerce
			'wcemailverified'          => 'true', // Customer Email Verification
			'user_activation_status'   => 1,     // User Verification (PickPlugins)
			'_um_account_status'       => null,  // yalnız varsa ayarlanır (Ultimate Member)
			'pending_email_verification' => null,
		)
	);
	foreach ( $meta as $k => $v ) {
		if ( null === $v ) {
			if ( metadata_exists( 'user', $user_id, $k ) ) {
				if ( '_um_account_status' === $k ) {
					update_user_meta( $user_id, $k, 'approved' );
				} else {
					delete_user_meta( $user_id, $k );
				}
			}
			continue;
		}
		update_user_meta( $user_id, $k, $v );
	}
	do_action( 'flxi_email_verified', $user_id );
}

/** WordPress'in "Oturum açma bilgileri / parolanızı belirleyin" e-postası Google ile kayıt olanlara gitmez. */
add_filter(
	'wp_new_user_notification_email',
	function ( $email, $user ) {
		if ( flxi_setting( 'social_block_core' ) && ( flxi_is_google_request() || flxi_user_is_social( $user->ID ) ) ) {
			$lines            = flxi_header_lines( $email['headers'] ?? array() );
			$lines[]          = 'X-FLXI-Social: 1';
			$email['headers'] = $lines;
		}
		return $email;
	},
	30,
	2
);

/** WooCommerce "Hesabınız oluşturuldu" e-postası da gitmez (içinde parola belirleme bağlantısı vardır). */
add_filter(
	'woocommerce_email_enabled_customer_new_account',
	function ( $enabled, $object ) {
		if ( $enabled && flxi_setting( 'social_block_wc' ) ) {
			$uid = $object instanceof WP_User ? $object->ID : 0;
			if ( flxi_is_google_request() || ( $uid && flxi_user_is_social( $uid ) && time() - (int) get_user_meta( $uid, 'flxi_social_at', true ) < 600 ) ) {
				return false;
			}
		}
		return $enabled;
	},
	20,
	2
);

/** Bağlamda işaret varsa e-posta durdurulur ve kayda "Google ile kayıt" yazılır. */
add_filter(
	'wp_mail',
	function ( $atts ) {
		$lines = flxi_header_lines( $atts['headers'] ?? array() );
		if ( '1' === flxi_header_get( $lines, 'X-FLXI-Social' ) ) {
			$atts['headers']  = flxi_header_remove( $lines, 'X-FLXI-Social' );
			$atts['flxi_social'] = 1;
		}
		return $atts;
	},
	999
);

add_filter(
	'flxi_mail_context',
	function ( $ctx, $atts ) {
		if ( ! empty( $atts['flxi_social'] ) ) {
			$ctx['block']    = true;
			$ctx['block_by'] = 'social';
		}
		return $ctx;
	},
	20,
	2
);

/** WordPress çekirdeğinde e-posta doğrulama yoktur; yine de "doğrulanmış" bilgisini profil ekranında göster. */
add_action(
	'show_user_profile',
	'flxi_profile_social_row'
);
add_action(
	'edit_user_profile',
	'flxi_profile_social_row'
);
function flxi_profile_social_row( $user ) {
	if ( ! flxi_user_is_social( $user->ID ) ) {
		return;
	}
	echo '<h2>' . esc_html__( 'Google ile giriş', 'flx-mail' ) . '</h2><table class="form-table"><tr><th>' . esc_html__( 'E-posta', 'flx-mail' ) . '</th><td><span class="dashicons dashicons-yes-alt" style="color:#0E7A56"></span> ' . esc_html__( 'Google tarafından doğrulandı. Bu hesaba parola belirleme e-postası gönderilmez.', 'flx-mail' ) . '</td></tr></table>';
}
