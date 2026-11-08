<?php
/**
 * Bağışçı girişi: e-posta ya da telefona tek kullanımlık kod (mobil/masaüstü uygulama).
 *
 *   POST /flxapp/v1/otp/start  { login, client_id }        → { sent, via, via_label, ttl }
 *   POST /flxapp/v1/otp/verify { login, code, client_id }  → OAuth belirteç cevabı (access + refresh)
 *
 * Kod 6 hane, 10 dk geçerli, 5 deneme. Yanıt, hesabın olup olmadığını belli etmez.
 * E-posta wp_mail ile; telefon Flux Chat (WhatsApp) varsa onunla. Başka kanal: filtre `flxapp_otp_send`.
 * Yetkili personel (edit_posts) varsayılan olarak kodla giremez; OAuth (tarayıcı) kullanır.
 */

defined( 'ABSPATH' ) || exit;

const FLXAPP_OTP_TTL   = 600;
const FLXAPP_OTP_TRIES = 5;

/** Giriş değeri: e-posta ya da telefon (yalnız rakam, TR için 90 önekli). */
function flxapp_otp_normalize( $login ) {
	$login = trim( (string) $login );
	if ( is_email( $login ) ) {
		return array( 'email', strtolower( $login ) );
	}
	if ( function_exists( 'flxc_normalize_phone' ) ) {
		$p = (string) flxc_normalize_phone( $login );
	} else {
		$p = preg_replace( '/\D/', '', $login );
		if ( 10 === strlen( $p ) && '5' === $p[0] ) {
			$p = '90' . $p;
		} elseif ( 11 === strlen( $p ) && '0' === $p[0] ) {
			$p = '9' . $p;
		}
	}
	return strlen( $p ) >= 10 && strlen( $p ) <= 15 ? array( 'phone', $p ) : array( '', '' );
}

/** Telefonun son 10 hanesi (kayıtlarda farklı biçimlerde tutulabilir). */
function flxapp_otp_phone_tail( $p ) {
	return substr( preg_replace( '/\D/', '', (string) $p ), -10 );
}

/** Kullanıcıyı bul; yoksa ve bu bilgiyle bağış/sipariş varsa müşteri hesabı aç (ayar). */
function flxapp_otp_find_user( $type, $value ) {
	global $wpdb;
	$user = null;
	if ( 'email' === $type ) {
		$user = get_user_by( 'email', $value );
	} elseif ( 'phone' === $type && function_exists( 'flxr_user_by_phone' ) && flxr_user_by_phone( $value ) ) {
		$found = flxr_user_by_phone( $value );
		$user  = $found instanceof WP_User ? $found : get_userdata( (int) $found );
	} elseif ( 'phone' === $type ) {
		$tail = flxapp_otp_phone_tail( $value );
		$ids  = $wpdb->get_col( $wpdb->prepare( "SELECT user_id FROM {$wpdb->usermeta} WHERE meta_key IN ('billing_phone','flxapp_phone','flxc_phone','flx_phone','phone') AND meta_value LIKE %s LIMIT 5", '%' . $wpdb->esc_like( substr( $tail, -7 ) ) ) ); // phpcs:ignore WordPress.DB
		foreach ( $ids as $id ) {
			foreach ( array( 'billing_phone', 'flxapp_phone', 'flxc_phone', 'flx_phone', 'phone' ) as $k ) {
				if ( flxapp_otp_phone_tail( get_user_meta( (int) $id, $k, true ) ) === $tail ) {
					$user = get_userdata( (int) $id );
					break 2;
				}
			}
		}
	}
	$user = apply_filters( 'flxapp_otp_find_user', $user ? $user : null, $type, $value );
	if ( ! $user && flxapp_opt( 'otp_create' ) ) {
		$user = flxapp_otp_create_customer( $type, $value );
	}
	return $user instanceof WP_User ? $user : null;
}

/** Bu e-posta/telefonla WooCommerce siparişi (bağış) varsa müşteri hesabı açar ve eski siparişleri bağlar. */
function flxapp_otp_create_customer( $type, $value ) {
	if ( ! function_exists( 'wc_get_orders' ) ) {
		return null;
	}
	$args  = array( 'limit' => 1, 'return' => 'objects', 'customer_id' => 0 );
	$order = null;
	if ( 'email' === $type ) {
		$orders = wc_get_orders( $args + array( 'billing_email' => $value ) );
		$order  = $orders ? $orders[0] : null;
	} else {
		$tail = flxapp_otp_phone_tail( $value );
		foreach ( wc_get_orders( array( 'limit' => 50, 'return' => 'objects', 'customer_id' => 0, 'orderby' => 'date', 'order' => 'DESC' ) ) as $o ) {
			if ( flxapp_otp_phone_tail( $o->get_billing_phone() ) === $tail ) {
				$order = $o;
				break;
			}
		}
	}
	if ( ! $order || ! is_email( $order->get_billing_email() ) || email_exists( $order->get_billing_email() ) ) {
		return null;
	}
	$email = $order->get_billing_email();
	$id    = function_exists( 'wc_create_new_customer' )
		? wc_create_new_customer( $email, '', wp_generate_password( 32 ), array( 'first_name' => $order->get_billing_first_name(), 'last_name' => $order->get_billing_last_name() ) )
		: wp_insert_user( array( 'user_login' => sanitize_user( strstr( $email, '@', true ) . wp_rand( 100, 999 ), true ), 'user_email' => $email, 'user_pass' => wp_generate_password( 32 ), 'role' => get_role( 'customer' ) ? 'customer' : 'subscriber' ) );
	if ( is_wp_error( $id ) ) {
		return null;
	}
	if ( $order->get_billing_phone() ) {
		update_user_meta( $id, 'billing_phone', $order->get_billing_phone() );
	}
	if ( function_exists( 'wc_update_new_customer_past_orders' ) ) {
		wc_update_new_customer_past_orders( $id );
	}
	do_action( 'flxapp_otp_customer_created', $id, $order );
	return get_userdata( $id );
}

/** Kodla girişe izin var mı (personel varsayılan olarak hayır). */
function flxapp_otp_allowed( WP_User $user ) {
	$ok = flxapp_opt( 'otp_staff' ) || ! user_can( $user, 'edit_posts' );
	return (bool) apply_filters( 'flxapp_otp_allowed', $ok, $user );
}

function flxapp_otp_key( $login ) {
	return 'flxapp_otp_' . flxapp_hash( 'otp|' . $login );
}

/** Kodu gönderir. @return string|false kanal */
function flxapp_otp_deliver( WP_User $user, $type, $value, $code ) {
	$site = wp_specialchars_decode( get_bloginfo( 'name' ), ENT_QUOTES );
	/* translators: 1: code, 2: site name */
	$text = sprintf( __( '%1$s is your sign-in code for the %2$s app. Valid for 10 minutes. Do not share this code with anyone.', 'flx-app' ), $code, $site );
	$via  = apply_filters( 'flxapp_otp_send', null, $user, $type, $value, $code, $text );
	if ( $via ) {
		return (string) $via;
	}
	if ( 'phone' === $type && function_exists( 'flxc_send_private_text' ) ) {
		// Gizli gönderim: kod panelde görünmez, sohbette asistanı susturmaz.
		$r = flxc_send_private_text( $value, $text, array( 'source' => 'flxapp-otp' ) );
		if ( true === $r ) {
			return 'whatsapp';
		}
	} elseif ( 'phone' === $type && function_exists( 'flxc_send_text' ) ) {
		$r = flxc_send_text( $value, $text, array( 'source' => 'flxapp-otp', 'name' => $user->display_name ) );
		if ( ! is_wp_error( $r ) ) {
			return 'whatsapp';
		}
	}
	$email = $user->user_email;
	if ( 'email' === $type || is_email( $email ) ) {
		/* translators: %s: site name */
		$subject = sprintf( __( '%s sign-in code', 'flx-app' ), $site );
		$body    = '<div style="font:16px/1.5 -apple-system,Segoe UI,sans-serif;max-width:420px">'
			. '<p>' . esc_html__( 'Your app sign-in code:', 'flx-app' ) . '</p>'
			. '<p style="font-size:32px;letter-spacing:8px;font-weight:700;margin:16px 0">' . esc_html( $code ) . '</p>'
			. '<p style="color:#5f6b64">' . esc_html__( 'The code is valid for 10 minutes. If you did not request it, ignore this email.', 'flx-app' ) . '</p></div>';
		if ( wp_mail( $email, $subject, $body, array( 'Content-Type: text/html; charset=UTF-8' ) ) ) {
			return 'email';
		}
	}
	return false;
}

function flxapp_otp_via_label( $via ) {
	$l = array(
		'email'    => __( 'Email', 'flx-app' ),
		'whatsapp' => 'WhatsApp',
		'sms'      => 'SMS',
	);
	return $l[ $via ] ?? $via;
}

function flxapp_rest_otp_start( WP_REST_Request $req ) {
	if ( ! flxapp_opt( 'otp_enabled' ) ) {
		return new WP_Error( 'flxapp_otp_off', __( 'Sign in with code is turned off.', 'flx-app' ), array( 'status' => 403 ) );
	}
	if ( ! flxapp_client( (string) $req->get_param( 'client_id' ) ) ) {
		return flxapp_oauth_error( 'invalid_client', '', 401 );
	}
	list( $type, $value ) = flxapp_otp_normalize( $req->get_param( 'login' ) );
	if ( '' === $type ) {
		return new WP_Error( 'flxapp_otp_login', __( 'Enter a valid email or phone number.', 'flx-app' ), array( 'status' => 400 ) );
	}
	// IP başına 10 dk'da 6, giriş değeri başına 10 dk'da 3 kod.
	if ( ! flxapp_rate( 'otp_ip', 6, 10 * MINUTE_IN_SECONDS ) || ! flxapp_rate( 'otp_login|' . $value, 3, 10 * MINUTE_IN_SECONDS ) ) {
		return new WP_Error( 'flxapp_rate', __( 'Too many attempts. Try again in a little while.', 'flx-app' ), array( 'status' => 429 ) );
	}
	$via  = 'phone' === $type ? ( function_exists( 'flxc_send_text' ) ? 'whatsapp' : 'email' ) : 'email';
	$user = flxapp_otp_find_user( $type, $value );
	if ( $user && flxapp_otp_allowed( $user ) ) {
		$code = str_pad( (string) random_int( 0, 999999 ), 6, '0', STR_PAD_LEFT );
		$sent = flxapp_otp_deliver( $user, $type, $value, $code );
		if ( $sent ) {
			$via = $sent;
			set_transient(
				flxapp_otp_key( $value ),
				array(
					'user'   => $user->ID,
					'hash'   => wp_hash_password( $code ),
					'tries'  => 0,
					'client' => (string) $req->get_param( 'client_id' ),
				),
				FLXAPP_OTP_TTL
			);
		}
	}
	// Hesap olsun olmasın aynı cevap (hesap varlığı sızmasın).
	return array(
		'sent'      => true,
		'via'       => $via,
		'via_label' => flxapp_otp_via_label( $via ),
		'ttl'       => FLXAPP_OTP_TTL,
	);
}

function flxapp_rest_otp_verify( WP_REST_Request $req ) {
	$client = flxapp_client( (string) $req->get_param( 'client_id' ) );
	if ( ! $client ) {
		return flxapp_oauth_error( 'invalid_client', '', 401 );
	}
	if ( ! flxapp_rate( 'otp_verify', 20, 10 * MINUTE_IN_SECONDS ) ) {
		return new WP_Error( 'flxapp_rate', __( 'Too many attempts. Try again in a little while.', 'flx-app' ), array( 'status' => 429 ) );
	}
	list( $type, $value ) = flxapp_otp_normalize( $req->get_param( 'login' ) );
	$key  = flxapp_otp_key( $value );
	$d    = '' !== $type ? get_transient( $key ) : false;
	$code = preg_replace( '/\D/', '', (string) $req->get_param( 'code' ) );
	$bad  = new WP_Error( 'flxapp_otp_code', __( 'The code is wrong or has expired.', 'flx-app' ), array( 'status' => 400 ) );
	if ( ! $d || $d['client'] !== $client['id'] ) {
		return $bad;
	}
	if ( 6 !== strlen( $code ) || ! wp_check_password( $code, $d['hash'] ) ) {
		++$d['tries'];
		if ( $d['tries'] >= FLXAPP_OTP_TRIES ) {
			delete_transient( $key );
		} else {
			set_transient( $key, $d, FLXAPP_OTP_TTL );
		}
		return $bad;
	}
	delete_transient( $key ); // tek kullanım
	$user = get_userdata( (int) $d['user'] );
	if ( ! $user || ! flxapp_otp_allowed( $user ) ) {
		return $bad;
	}
	if ( ! flxapp_client_usable( $client ) ) {
		return flxapp_oauth_error( 'unauthorized_client', 'app_not_allowed', 403 );
	}
	$app_id = flxapp_app_id_for( $client, (string) $req->get_param( 'app_id' ) );
	do_action( 'flxapp_otp_login', $user->ID, $type );
	return flxapp_token_pair( $client['id'], $user->ID, 'flx', strtolower( wp_generate_password( 24, false ) ), $app_id );
}

add_action(
	'rest_api_init',
	function () {
		register_rest_route( 'flxapp/v1', '/otp/start', array( 'methods' => 'POST', 'callback' => 'flxapp_rest_otp_start', 'permission_callback' => '__return_true' ) );
		register_rest_route( 'flxapp/v1', '/otp/verify', array( 'methods' => 'POST', 'callback' => 'flxapp_rest_otp_verify', 'permission_callback' => '__return_true' ) );
	}
);
