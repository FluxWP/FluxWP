<?php
/**
 * Flux Donate: the one iyzico merchant notification URL ("İşyeri Bildirimleri Url").
 *
 * iyzico allows a single notification URL per merchant, and on the live site the official iyzico
 * WooCommerce plugin needs notifications too. So both share POST /wp-json/flxb/v1/iyzico/notify:
 *
 *  - a notification for a Flux Donate payment (paymentConversationId "flxb_…" / "flxbr-…", or a token
 *    Flux Donate stored) is handled here: X-IYZ-SIGNATURE is checked when sent, then the payment is
 *    retrieved from iyzico by token before anything is recorded (idempotent);
 *  - anything else is handed, inside the same request, to the iyzico WooCommerce plugin's own
 *    webhook route (iyzico/v1/webhook/{iyzicoWebhookUrlKey}) with the same body and signature,
 *    and its answer is returned unchanged;
 *  - iyzico Subscription API notifications (we do not use that product: Flux Donate renews with a
 *    stored card and its own cron) and unknown bodies are acknowledged with 200.
 *
 * The older address Emsfb/v1/forms/payment/webhook/iyzico answers the same way.
 * Signature (same as the iyzico WooCommerce plugin): base64( sha1( secretKey . iyziEventType . token, raw ) ).
 *
 * GPL v2 or later.
 *
 * @package FLX_Bagis
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class FLXB_Iyzico_Notify {

	const ROUTE    = '/iyzico/notify';
	const LAST_OPT = 'flxb_iyzico_notify_last';

	public static function init() {
		add_action( 'rest_api_init', array( __CLASS__, 'routes' ) );
	}

	public static function routes() {
		register_rest_route(
			'flxb/v1',
			self::ROUTE,
			array(
				'methods'             => 'POST',
				'permission_callback' => '__return_true', // iyzico posts here; payments are re-checked with iyzico
				'callback'            => array( __CLASS__, 'handle' ),
			)
		);
	}

	/** The address to paste into the iyzico merchant panel. */
	public static function url() {
		return home_url( '/wp-json/flxb/v1' . self::ROUTE );
	}

	/** The iyzico WooCommerce plugin's webhook key, when that plugin set one. */
	public static function woo_key() {
		$k = get_option( 'iyzicoWebhookUrlKey' );
		return is_string( $k ) && '' !== trim( $k ) ? trim( $k ) : '';
	}

	/** Is the iyzico WooCommerce plugin's webhook route registered in this request? */
	public static function woo_route() {
		$key = self::woo_key();
		if ( '' === $key || ! function_exists( 'rest_get_server' ) ) {
			return '';
		}
		$path = '/iyzico/v1/webhook/' . $key;
		return isset( rest_get_server()->get_routes()[ $path ] ) ? $path : '';
	}

	private static function note( $kind ) {
		update_option( self::LAST_OPT, array( 'at' => time(), 'kind' => $kind ), false );
		do_action( 'flxb_iyzico_notification', $kind );
	}

	private static function reply( $kind, $status = 200, array $extra = array() ) {
		self::note( $kind );
		return new WP_REST_Response( array_merge( array( 'received' => true, 'handled_by' => $kind ), $extra ), $status );
	}

	/** Ours: the conversation id Flux Donate sent, or a token it stored. */
	private static function ours( $conv, $token ) {
		if ( '' !== $conv && preg_match( '/^flxb(r|wc)?[_-]/', $conv ) ) {
			return true;
		}
		return '' !== $token && class_exists( 'FLXB_Payment_Records' ) && FLXB_Payment_Records::get_by_reference( 'iyzico', $token );
	}

	public static function signature( $secret, $event, $token ) {
		return base64_encode( sha1( (string) $secret . (string) $event . (string) $token, true ) ); // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_encode
	}

	/** "iyzico panel addresses" box (Payments › iyzico and System check). */
	public static function admin_box() {
		$id  = 'flxb-iyz-notify-' . wp_rand( 100, 999 );
		$out = '<div class="flxb-iyzurls">';
		$out .= '<h4 class="flxb-iyzurls__title">' . esc_html__( 'iyzico panel addresses', 'flx-donate' ) . '</h4>';
		$out .= '<p class="flxb-help">' . esc_html__( 'In the iyzico merchant panel, open the notification settings, switch payment notifications on and paste this address. iyzico allows one address per merchant; Flux Donate passes WooCommerce notifications on to the iyzico WooCommerce plugin, so the same address serves both.', 'flx-donate' ) . '</p>';
		$out .= FLXB_Admin::copy_field( $id, __( 'Merchant notifications URL (İşyeri Bildirimleri Url)', 'flx-donate' ), self::url() );
		$out .= '<p class="flxb-help"><strong>' . esc_html__( 'Subscription notification URLs (Başarılı / Başarısız Abonelik Bildirimleri Url): not needed, leave them off.', 'flx-donate' ) . '</strong> ' . esc_html__( 'They belong to iyzico\'s own subscription product; Flux Donate renews recurring donations with the saved card and its own scheduled task.', 'flx-donate' ) . '</p>';
		$out .= '<p class="flxb-help">' . esc_html__( 'Callback URL (sent automatically with every payment, nothing to paste):', 'flx-donate' ) . ' <code class="flxb-codeblock">' . esc_html( FLXB_Payment_Iyzico::callback_url() ) . '</code></p>';
		if ( '' !== self::woo_key() ) {
			$out .= '<p class="flxb-help">' . esc_html__( 'The iyzico WooCommerce plugin has a webhook key on this site: notifications that are not for Flux Donate are forwarded to it.', 'flx-donate' ) . '</p>';
		}
		return $out . '</div>';
	}

	public static function handle( WP_REST_Request $request ) {
		$p = $request->get_json_params();
		if ( ! is_array( $p ) ) {
			$p = (array) $request->get_body_params();
		}
		$str   = function ( $k ) use ( $p ) {
			return isset( $p[ $k ] ) && is_scalar( $p[ $k ] ) ? sanitize_text_field( (string) $p[ $k ] ) : '';
		};
		$event = $str( 'iyziEventType' );
		$token = $str( 'token' );
		$conv  = $str( 'paymentConversationId' );
		$sig   = (string) $request->get_header( 'x_iyz_signature' );

		/* iyzico Subscription API (its own subscription product): not used by Flux Donate. */
		if ( '' !== $str( 'subscriptionReferenceCode' ) || 0 === strpos( strtolower( $event ), 'subscription' ) ) {
			return self::reply( 'subscription_ignored' );
		}

		if ( self::ours( $conv, $token ) ) {
			if ( '' !== $sig ) {
				$c = FLXB_Iyzico_Api::credentials();
				if ( '' !== (string) $c['secret_key'] && ! hash_equals( self::signature( $c['secret_key'], $event, $token ), $sig ) ) {
					self::note( 'bad_signature' );
					return new WP_REST_Response( array( 'error' => 'signature_not_valid' ), 400 );
				}
			}
			if ( 0 === strpos( $conv, 'flxbr-' ) && '' === $token ) {
				return self::reply( 'flxb_renewal' ); // stored-card renewals are settled by the cron job itself
			}
			if ( 0 === strpos( $conv, 'flxbwc-' ) ) {
				/* 2.0: a WooCommerce order paid with the Flux Donate iyzico gateway. */
				$paid = class_exists( 'FLXB_WC_Iyzico' ) && '' !== $token ? FLXB_WC_Iyzico::settle_token( $token, 'webhook' ) : false;
				return self::reply( 'flxb_wc', 200, array( 'paid' => (bool) $paid ) );
			}
			$row = '' !== $token && class_exists( 'FLXB_Payment_Records' ) ? FLXB_Payment_Records::get_by_reference( 'iyzico', $token ) : null;
			$pr  = function_exists( 'flxf_payment_provider' ) ? flxf_payment_provider( 'iyzico' ) : null;
			if ( $row && 'pending' === $row->status && $pr instanceof FLXB_Payment_Iyzico ) {
				$res = $pr->finalize( $row, 'webhook' );
				return self::reply( 'flxb', 200, array( 'paid' => ! empty( $res['ok'] ) ) );
			}
			return self::reply( 'flxb', 200, array( 'paid' => $row ? in_array( $row->status, array( 'completed', 'succeeded', 'refunded', 'partially_refunded' ), true ) : false ) );
		}

		$route = self::woo_route();
		if ( '' !== $route ) {
			$fwd = new WP_REST_Request( 'POST', $route );
			$fwd->set_header( 'Content-Type', 'application/json' );
			if ( '' !== $sig ) {
				$fwd->set_header( 'X-IYZ-SIGNATURE', $sig );
			}
			$fwd->set_body( (string) $request->get_body() );
			/* The plugin reads getallheaders(); on servers where that is built from $_SERVER, set it too. */
			$had  = array_key_exists( 'HTTP_X_IYZ_SIGNATURE', $_SERVER );
			$prev = $had ? $_SERVER['HTTP_X_IYZ_SIGNATURE'] : null; // phpcs:ignore WordPress.Security.ValidatedSanitizedInput
			if ( '' !== $sig ) {
				$_SERVER['HTTP_X_IYZ_SIGNATURE'] = $sig;
			}
			try {
				$res = rest_do_request( $fwd );
			} finally {
				if ( $had ) {
					$_SERVER['HTTP_X_IYZ_SIGNATURE'] = $prev;
				} else {
					unset( $_SERVER['HTTP_X_IYZ_SIGNATURE'] );
				}
			}
			self::note( 'woocommerce' );
			$out = rest_get_server()->response_to_data( $res, false );
			return new WP_REST_Response( $out, $res->get_status() );
		}

		return self::reply( 'ignored' );
	}
}
