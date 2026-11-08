<?php
/**
 * Flux Donate: iyzico provider (one-time and recurring donations).
 *
 * One-time gift: iyzico Checkout Form. Pay → server initialises a checkout form for the server
 * amount (basket = the donation's lines) → the donor pays on iyzico's page (a popup window, or
 * the same tab when popups are blocked or "redirect" is chosen) → iyzico posts the token to
 * Flux Donate's callback (REST flxb/v1/iyzico/callback) → the payment is retrieved from iyzico by
 * token and counted only when paymentStatus is SUCCESS and the price and currency match the
 * stored server amount. The callback is idempotent; verify() (final submit / confirm) and the
 * optional webhook re-check the same way.
 *
 * Recurring gift (form payment method day / week / month / year): the first payment is the same
 * checkout form; iyzico returns cardUserKey + cardToken when the card was stored. Both are kept
 * encrypted with the Flux Donate recurring donation, and Flux Donate's own cron
 * (FLXB_Iyzico_Recurring, hook flxb_iyzico_renewals) charges the stored card on each due date.
 * No WooCommerce orders are created; nothing is shared with the iyzico Subscription plugin.
 *
 * Refund (full or an amount, per paymentTransactionId) and cancel (same-day void) run from
 * Donations › Payments and the donation detail.
 *
 * GPL v2 or later.
 *
 * @package FLX_Bagis
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class FLXB_Payment_Iyzico extends FLXB_Payment_Provider_Base {

	const METHODS    = array( 'charge', 'day', 'week', 'month', 'year' );
	const CURRENCIES = array( 'TRY', 'EUR', 'USD', 'GBP', 'IRR', 'NOK', 'RUB', 'CHF' );
	const ROUTE_NS   = 'flxb/v1';

	public function id() {
		return 'iyzico';
	}

	public function label() {
		return 'iyzico';
	}

	/** TRY always; other currencies when listed in the settings. */
	public function currencies() {
		$extra = array_filter( array_map( 'trim', explode( ',', strtoupper( (string) $this->setting( 'iyzicoCurrencies' ) ) ) ) );
		return array_values( array_unique( array_merge( array( 'TRY' ), array_intersect( $extra, self::CURRENCIES ) ) ) );
	}

	public function supports( $currency, $method ) {
		return in_array( $method, self::METHODS, true ) && in_array( strtoupper( (string) $currency ), $this->currencies(), true );
	}

	public function supports_refunds() {
		return true;
	}

	public function supports_void() {
		return true;
	}

	public function settings_fields() {
		return array(
			array( 'key' => 'iyzicoApiKey', 'label' => __( 'API key', 'flx-donate' ), 'type' => 'text', 'help' => __( '\\niyzico merchant panel › Settings › Merchant settings. Sandbox keys start with "sandbox-".\\n', 'flx-donate' ) ),
			array( 'key' => 'iyzicoSecretKey', 'label' => __( 'Secret key', 'flx-donate' ), 'type' => 'password' ),
			array( 'key' => 'iyzicoSandbox', 'label' => __( '\\nSandbox (test) mode\\n', 'flx-donate' ), 'type' => 'checkbox', 'default' => '1', 'help' => __( '\\nUses sandbox-api.iyzipay.com; no real money is taken. Turn off with live keys.\\n', 'flx-donate' ) ),
			array(
				'key'     => 'iyzicoMode',
				'label'   => __( '\\nPayment page\\n', 'flx-donate' ),
				'type'    => 'select',
				'default' => 'popup',
				'options' => array(
					'popup'    => __( '\\nPopup window (recommended: the form stays open)\\n', 'flx-donate' ),
					'redirect' => __( '\\nSame tab (redirect to iyzico and back)\\n', 'flx-donate' ),
				),
				'help'    => __( '\\nWith "popup" the donor returns to the open form and the form is sent as usual. When the browser blocks the popup, the same tab is used.\\n', 'flx-donate' ),
			),
			array( 'key' => 'iyzicoCurrencies', 'label' => __( '\\nOther currencies\\n', 'flx-donate' ), 'type' => 'text', 'help' => __( '\\nTRY is always accepted. Add others your iyzico account accepts, comma separated (e.g. USD,EUR).\\n', 'flx-donate' ) ),
			array(
				'key'     => 'iyzicoNoCard',
				'label'   => __( '\\nRecurring gift without a saved card\', \'en\']]', 'flx-donate' ),
				'type'    => 'select',
				'default' => 'keep',
				'options' => array(
					'keep'   => __( '\\nKeep the payment as a one-time donation\\n', 'flx-donate' ),
					'cancel' => __( '\\nCancel the payment (like the iyzico Subscription plugin)\\n', 'flx-donate' ),
				),
				'help'    => __( '\\nIf iyzico does not return a stored card for the first payment of a recurring gift, it cannot be charged again.\\n', 'flx-donate' ),
			),
		);
	}

	/** Note shown under a field whose value comes from another plugin ('' when it does not). */
	public function field_inherited( $key ) {
		if ( ! in_array( $key, array( 'iyzicoApiKey', 'iyzicoSecretKey', 'iyzicoSandbox' ), true ) ) {
			return '';
		}
		$c = FLXB_Iyzico_Api::credentials();
		if ( 'woocommerce' === $c['source'] ) {
			return __( '\\nRead from the iyzico WooCommerce plugin (WooCommerce › Settings › Payments › iyzico). Values here are used only when that plugin has no keys.\\n', 'flx-donate' );
		}
		if ( 'woocommerce-subscription' === $c['source'] ) {
			return __( '\\nRead from the iyzico WooCommerce Subscription plugin. Values here are used only when it has no keys.\\n', 'flx-donate' );
		}
		return '';
	}

	public function is_configured() {
		return '' !== FLXB_Iyzico_Api::credentials()['source'];
	}

	public function mode() {
		return 'redirect' === $this->setting( 'iyzicoMode', 'popup' ) ? 'redirect' : 'popup';
	}

	public function front_assets() {
		return array(
			'scripts' => array(
				'flxf-pay-iyzico' => array(
					'src'  => FLXB_URL . 'providers/assets/flxf-pay-iyzico.js',
					'ver'  => flxb_payments_asset_ver( 'flxf-pay-iyzico.js' ),
					'data' => array(
						'flxfPayIyzico' => array(
							'mode'      => $this->mode(),
							'sandbox'   => (bool) FLXB_Iyzico_Api::credentials()['sandbox'],
							'note'      => __( '\\nYou will enter your card on iyzico\'s secure payment page. It opens in a new window when you press Pay.\\n', 'flx-donate' ),
							'noteTab'   => __( '\\nYou will enter your card on iyzico\'s secure payment page and then come back here.\\n', 'flx-donate' ),
							'recurring' => __( '\\nFor a recurring donation, keep "Save my card" ticked on the iyzico page so the next payments can be taken automatically.\\n', 'flx-donate' ),
							'sandboxNote' => __( '\\nTEST MODE (iyzico sandbox): no real money is taken.\\n', 'flx-donate' ),
							'waiting'   => __( '\\nComplete the payment in the iyzico window...\\n', 'flx-donate' ),
							'focus'     => __( '\\nShow the payment window\\n', 'flx-donate' ),
							'loading'   => __( '\\nOpening the secure payment page...\\n', 'flx-donate' ),
							'closed'    => __( '\\nThe payment was not completed. You can try again.\\n', 'flx-donate' ),
							'done'      => __( '\\nThank you! Your donation has been received.\\n', 'flx-donate' ),
							'code'      => __( '\\nConfirmation code\\n', 'flx-donate' ),
							'again'     => __( '\\nMake another donation\\n', 'flx-donate' ),
							'failed'    => __( '\\nThe payment could not be completed. Please try again or use another card.\\n', 'flx-donate' ),
						),
					),
				),
			),
		);
	}

	/* ------------------------------------------------------------------
	 * Start
	 * ------------------------------------------------------------------ */

	public static function callback_url() {
		return rest_url( self::ROUTE_NS . '/iyzico/callback' );
	}

	public function create_payment( $amount, $currency, array $meta ) {
		$api = FLXB_Iyzico_Api::make();
		if ( ! $api ) {
			return FLXB_Payment_Result::failed( __( '[[\'iyzico is not configured on this site. Please contact the site administrator.\\n', 'flx-donate' ) );
		}
		$currency = strtoupper( (string) $currency );
		$method   = (string) ( $meta['method'] ?? 'charge' );
		if ( ! $this->supports( $currency, $method ) ) {
			return FLXB_Payment_Result::failed( __( '[[\'The currency of this form is not supported.\\n', 'flx-donate' ) );
		}
		$is_sub  = 'charge' !== $method;
		$ref     = (string) ( $meta['reference'] ?? '' );
		$contact = isset( $meta['contact'] ) && is_array( $meta['contact'] ) ? $meta['contact'] : array();
		$lines   = isset( $meta['flxb']['lines'] ) && is_array( $meta['flxb']['lines'] ) ? $meta['flxb']['lines'] : array();
		$name    = '' !== (string) ( $meta['form_name'] ?? '' ) ? (string) $meta['form_name'] : __( 'Donation', 'flx-donate' );
		list( $buyer, $address ) = FLXB_Iyzico_Api::buyer( $contact, (string) ( $meta['email'] ?? '' ), (string) ( $meta['ip'] ?? '' ) );
		$body = array(
			'locale'              => FLXB_Iyzico_Api::locale(),
			'conversationId'      => $ref,
			'price'               => FLXB_Iyzico_Api::price( $amount ),
			'basketId'            => $ref,
			'paymentGroup'        => 'PRODUCT',
			'buyer'               => $buyer,
			'billingAddress'      => $address,
			'basketItems'         => FLXB_Iyzico_Api::basket( $amount, $lines, $name, 'flxb' ),
			'callbackUrl'         => self::callback_url(),
			'currency'            => $currency,
			'paidPrice'           => FLXB_Iyzico_Api::price( $amount ),
			'enabledInstallments' => array( 1 ),
		);
		$body = (array) apply_filters( 'flxb_iyzico_checkout_request', $body, $meta );
		try {
			$r = $api->checkout_initialize( $body );
		} catch ( FLXB_Iyzico_Error $e ) {
			return FLXB_Payment_Result::failed( $e->getMessage() );
		}
		if ( ! FLXB_Iyzico_Api::ok( $r ) || empty( $r['token'] ) || empty( $r['paymentPageUrl'] ) ) {
			$err = FLXB_Iyzico_Api::error_text( $r );
			return FLXB_Payment_Result::failed( '' !== $err ? 'iyzico: ' . $err : __( '\\nThe payment could not be started. Please try again.\\n', 'flx-donate' ) );
		}
		$token  = sanitize_text_field( (string) $r['token'] );
		$record = array(
			'payment_type' => $is_sub ? 'subscription' : 'one-time',
			'status'       => 'pending',
			'meta'         => array(
				'reference' => $ref,
				'contact'   => array_intersect_key( $contact, array_flip( array( 'name', 'email', 'phone', 'consent_kvkk', 'consent_email', 'consent_sms' ) ) ),
				'page_url'  => (string) ( $meta['page_url'] ?? '' ),
				'sandbox'   => (bool) FLXB_Iyzico_Api::credentials()['sandbox'],
				'buyer_ip'  => (string) ( $meta['ip'] ?? '' ),
			),
		);
		if ( $is_sub ) {
			$record['subscription_id'] = 'iyzs_' . substr( $ref, 4 );
			$record['interval_unit']   = strtoupper( $method );
		}
		return FLXB_Payment_Result::redirect(
			$token,
			(string) $r['paymentPageUrl'],
			array(
				'record' => $record,
				'client' => array_filter( array( 'token' => $token, 'mode' => $this->mode(), 'recurring' => $is_sub, 'next' => $is_sub ? strtotime( FLXB_Subscriptions::next_charge( $method ) . ' UTC' ) : 0 ) ),
			)
		);
	}

	/* ------------------------------------------------------------------
	 * Finalise (callback, confirm, final submit, webhook)
	 * ------------------------------------------------------------------ */

	private static function row_meta( $row ) {
		$m = is_object( $row ) && ! empty( $row->meta ) ? json_decode( (string) $row->meta, true ) : null;
		return is_array( $m ) ? $m : array();
	}

	/** Paid states of an iyzico record. */
	private static function is_paid_row( $row ) {
		if ( ! is_object( $row ) ) {
			return false;
		}
		if ( 'subscription' === $row->payment_type ) {
			return in_array( $row->status, array( 'active', 'past_due' ), true );
		}
		return in_array( $row->status, array( 'completed', 'partially_refunded' ), true );
	}

	/**
	 * Ask iyzico about a started checkout and settle the record once. Safe to call repeatedly.
	 *
	 * @return array{ok:bool, final:bool, message:string, notice:string}
	 */
	public function finalize( $row, $source = 'callback' ) {
		$out = array( 'ok' => false, 'final' => false, 'message' => '', 'notice' => '' );
		if ( ! is_object( $row ) || 'iyzico' !== $row->gateway ) {
			$out['message'] = __( '\\nPayment not found.\\n', 'flx-donate' );
			return $out;
		}
		if ( self::is_paid_row( $row ) ) {
			return array_merge( $out, array( 'ok' => true, 'final' => true, 'notice' => (string) ( self::row_meta( $row )['notice'] ?? '' ) ) );
		}
		if ( 'pending' !== $row->status ) {
			return array_merge( $out, array( 'final' => true, 'message' => (string) ( self::row_meta( $row )['error'] ?? __( '\\nThe payment could not be completed. Please try again or use another card.\\n', 'flx-donate' ) ) ) );
		}
		/* One request at a time per payment (iyzico posts the callback; the browser confirms). */
		$lock = 'flxb_iyz_lock_' . (int) $row->id;
		if ( ! add_option( $lock, time(), '', false ) ) {
			if ( time() - (int) get_option( $lock ) < 60 ) {
				for ( $i = 0; $i < 8; $i++ ) {
					usleep( 500000 );
					$again = FLXB_Payment_Records::get( (int) $row->id );
					if ( $again && 'pending' !== $again->status ) {
						return $this->finalize( $again, $source );
					}
				}
				$out['message'] = __( '\\nThe payment could not be verified yet.\\n', 'flx-donate' );
				return $out;
			}
			update_option( $lock, time(), false );
		}
		try {
			return $this->settle( $row, $source );
		} finally {
			delete_option( $lock );
		}
	}

	private function settle( $row, $source = 'callback' ) {
		$out = array( 'ok' => false, 'final' => false, 'message' => '', 'notice' => '' );
		$api = FLXB_Iyzico_Api::make();
		$m   = self::row_meta( $row );
		if ( ! $api ) {
			$out['message'] = __( '[[\'iyzico is not configured on this site. Please contact the site administrator.\\n', 'flx-donate' );
			return $out;
		}
		try {
			$r = $api->checkout_retrieve( (string) $row->transaction_id, (string) ( $m['reference'] ?? '' ) );
		} catch ( FLXB_Iyzico_Error $e ) {
			$out['message'] = $e->getMessage();
			return $out;
		}
		$fail = function ( $status, $msg, $extra = array() ) use ( $row, $m, $out ) {
			$m['error'] = $msg;
			FLXB_Payment_Records::update( $row->id, array( 'status' => $status, 'meta' => array_merge( $m, $extra ) ) );
			return array_merge( $out, array( 'final' => true, 'message' => $msg ) );
		};
		$status = (string) ( $r['paymentStatus'] ?? '' );
		if ( ! FLXB_Iyzico_Api::ok( $r ) || 'SUCCESS' !== $status ) {
			if ( 'FAILURE' === $status || ( FLXB_Iyzico_Api::ok( $r ) && '' !== $status ) ) {
				$err = FLXB_Iyzico_Api::error_text( $r );
				return $fail( 'failed', '' !== $err ? $err : __( '\\nThe payment could not be completed. Please try again or use another card.\\n', 'flx-donate' ) );
			}
			/* Not paid (yet): the donor has not finished, or iyzico could not answer. */
			$out['message'] = __( '\\nThe payment could not be verified yet.\\n', 'flx-donate' );
			return $out;
		}
		/* Paid at iyzico: count it only for this payment and for the stored server amount. */
		$want   = FLXB_Iyzico_Api::minor( $row->amount );
		$basket = (string) ( $r['basketId'] ?? '' );
		$match  = $want > 0
			&& FLXB_Iyzico_Api::minor( $r['price'] ?? 0 ) === $want
			&& FLXB_Iyzico_Api::minor( $r['paidPrice'] ?? 0 ) >= $want
			&& strtoupper( (string) ( $r['currency'] ?? '' ) ) === strtoupper( (string) $row->currency )
			&& ( '' === $basket || '' === (string) ( $m['reference'] ?? '' ) || $basket === (string) $m['reference'] )
			&& ( ! isset( $r['token'] ) || (string) $r['token'] === (string) $row->transaction_id )
			&& -1 !== (int) ( $r['fraudStatus'] ?? 1 );
		$iyz = FLXB_Iyzico_Api::payment_info( $r );
		if ( ! $match ) {
			return $fail( 'mismatch', __( '\\nThe payment could not be verified. Please contact the site administrator.\\n', 'flx-donate' ), array( 'iyzico' => $iyz ) );
		}
		$upd    = array( 'status' => 'completed', 'capture_id' => $iyz['paymentId'] );
		$is_sub = 'subscription' === $row->payment_type;
		$card_k = (string) ( $r['cardUserKey'] ?? '' );
		$card_t = (string) ( $r['cardToken'] ?? '' );
		if ( $is_sub ) {
			if ( '' !== $card_k && '' !== $card_t ) {
				$upd['status'] = 'active';
			} elseif ( 'cancel' === $this->setting( 'iyzicoNoCard', 'keep' ) ) {
				try {
					$c = $api->cancel( $iyz['paymentId'], (string) ( $m['buyer_ip'] ?? '' ), (string) ( $m['reference'] ?? '' ), 'other', 'card_token_missing' );
				} catch ( FLXB_Iyzico_Error $e ) {
					$c = null;
				}
				$msg = __( '\\nYour card could not be saved for the recurring donation, so the payment was canceled and no money was taken. Please try again and keep "Save my card" ticked.\\n', 'flx-donate' );
				if ( ! FLXB_Iyzico_Api::ok( $c ) ) {
					$msg = __( '\\nYour card could not be saved for the recurring donation. Please contact us; the payment will be refunded.\\n', 'flx-donate' );
					$m['cancel_failed'] = FLXB_Iyzico_Api::error_text( $c );
				}
				return $fail( 'canceled', $msg, array( 'iyzico' => $iyz ) );
			} else {
				/* Keep the gift, as a one-time donation. */
				$upd['payment_type']    = 'one-time';
				$upd['subscription_id'] = '';
				$upd['interval_unit']   = '';
				$m['recurring_not_saved'] = true;
				$m['notice'] = __( '\\nYour donation was received, but your card was not saved, so it will not repeat automatically.\\n', 'flx-donate' );
			}
		}
		unset( $m['error'] );
		$m['iyzico'] = $iyz;
		$upd['meta'] = $m;
		FLXB_Payment_Records::update( $row->id, $upd );
		$row = FLXB_Payment_Records::get( (int) $row->id );
		do_action( 'flxb_iyzico_paid', $row, $r );

		/* Flux Donate owns the record: the donation exists even when the donor never sends the form. */
		$did = 0;
		if ( class_exists( 'FLXB_FLXForm_Bridge' ) ) {
			$did = (int) FLXB_FLXForm_Bridge::record_pay_row( $row, isset( $m['contact'] ) && is_array( $m['contact'] ) ? $m['contact'] : array(), $source );
			if ( $did ) {
				FLXB_Donations::update_meta( $did, array( 'iyzico' => $iyz ) );
			}
		}
		if ( 'active' === $upd['status'] ) {
			FLXB_Iyzico_Recurring::start( $row, $card_k, $card_t, $did, $r );
		}
		return array_merge( $out, array( 'ok' => true, 'final' => true, 'notice' => (string) ( $m['notice'] ?? '' ) ) );
	}

	public function verify( $record, array $params = array() ) {
		$no = FLXB_Payment_Result::failed( __( '\\nThe payment could not be verified yet.\\n', 'flx-donate' ) );
		if ( ! is_object( $record ) || 'iyzico' !== $record->gateway ) {
			return $no;
		}
		if ( self::is_paid_row( $record ) ) {
			return FLXB_Payment_Result::completed( (string) $record->transaction_id );
		}
		if ( 'pending' !== $record->status ) {
			$m = self::row_meta( $record );
			return FLXB_Payment_Result::failed( (string) ( $m['error'] ?? __( '\\nThe payment could not be completed. Please try again or use another card.\\n', 'flx-donate' ) ) );
		}
		$res = $this->finalize( $record );
		return $res['ok'] ? FLXB_Payment_Result::completed( (string) $record->transaction_id ) : FLXB_Payment_Result::failed( '' !== $res['message'] ? $res['message'] : __( '\\nThe payment could not be verified yet.\\n', 'flx-donate' ) );
	}

	/**
	 * Optional iyzico notification (Emsfb/v1/forms/payment/webhook/iyzico). Only the token is
	 * read; the result is always fetched from iyzico, so a forged call changes nothing.
	 */
	/** Older notification address (Emsfb/v1/forms/payment/webhook/iyzico): same handling as flxb/v1/iyzico/notify. */
	public function handle_webhook( $request ) {
		return FLXB_Iyzico_Notify::handle( $request );
	}

	/* ------------------------------------------------------------------
	 * Callback page
	 * ------------------------------------------------------------------ */

	public static function routes() {
		register_rest_route(
			self::ROUTE_NS,
			'/iyzico/callback',
			array(
				'methods'             => array( 'POST', 'GET' ),
				'permission_callback' => '__return_true', // iyzico posts here; the token is checked with iyzico
				'callback'            => array( __CLASS__, 'callback' ),
			)
		);
	}

	public static function callback( WP_REST_Request $r ) {
		$token = $r->get_param( 'token' );
		$token = is_string( $token ) ? sanitize_text_field( $token ) : '';
		$row   = '' !== $token ? FLXB_Payment_Records::get_by_reference( 'iyzico', $token ) : null;
		$p     = flxf_payment_provider( 'iyzico' );
		if ( ! $row || ! $p instanceof self ) {
			return self::html( self::page( false, __( '\\nPayment not found.\\n', 'flx-donate' ), home_url( '/' ), '', '' ) );
		}
		if ( FLXB_Payments_Rest::rate_limited( 'iyzico_callback', 60 ) ) {
			return self::html( self::page( false, __( '\\nToo many requests. Please wait a moment and try again.\\n', 'flx-donate' ), home_url( '/' ), '', '' ), 429 );
		}
		$res = $p->finalize( $row );
		if ( ! $res['ok'] && ! $res['final'] ) {
			/* Not settled yet: one more look after a short pause (iyzico may still be finishing). */
			sleep( 1 );
			$res = $p->finalize( FLXB_Payment_Records::get( (int) $row->id ) );
		}
		$msg = $res['ok'] ? ( '' !== $res['notice'] ? $res['notice'] : __( '\\nThank you! Your donation has been received.\\n', 'flx-donate' ) ) : ( '' !== $res['message'] ? $res['message'] : __( '\\nThe payment could not be completed. Please try again or use another card.\\n', 'flx-donate' ) );
		return self::html( self::page( $res['ok'], $msg, self::return_url( $row, $res['ok'] ), (string) $row->track, $res['ok'] ? '' : $msg, $res['ok'] ? $res['notice'] : '' ) );
	}

	/** Back to the form page (same site only), with the outcome for the form's script. */
	private static function return_url( $row, $ok ) {
		$m   = self::row_meta( $row );
		$url = wp_validate_redirect( (string) ( $m['page_url'] ?? '' ), home_url( '/' ) );
		return add_query_arg( array( 'flxb_pay' => rawurlencode( (string) $row->track ), 'flxb_pay_ok' => $ok ? '1' : '0' ), $url );
	}

	private static function html( $body, $status = 200 ) {
		return new WP_REST_Response( array( '__flxb_html' => $body ), $status, array( 'Content-Type' => 'text/html; charset=utf-8', 'Cache-Control' => 'no-store', 'X-Robots-Tag' => 'noindex' ) );
	}

	/**
	 * The page iyzico returns the donor to. In the popup it tells the form (window.opener) and
	 * closes; in the same tab it goes back to the form page.
	 */
	public static function page( $ok, $message, $back, $track, $error, $notice = '' ) {
		$payload = array( 'flxbIyzico' => 1, 'ok' => (bool) $ok, 'track' => (string) $track, 'message' => (string) $error, 'notice' => (string) $notice );
		$h       = wp_parse_url( home_url() );
		$origin  = ( $h['scheme'] ?? 'https' ) . '://' . ( $h['host'] ?? '' ) . ( isset( $h['port'] ) ? ':' . (int) $h['port'] : '' );
		$body    = '<p class="m">' . esc_html( $message ) . '</p>';
		if ( $ok && '' !== $track ) {
			$body .= '<p class="c">' . esc_html__( '\\nConfirmation code\\n', 'flx-donate' ) . ': <b>' . esc_html( $track ) . '</b></p>';
		}
		$body  .= '<p><a class="b" href="' . esc_url( $back ) . '">' . esc_html__( '\\nBack to the form\\n', 'flx-donate' ) . '</a></p>';
		$script = '(function(){var d=' . wp_json_encode( $payload ) . ',b=' . wp_json_encode( esc_url_raw( $back ) ) . ',o=null;try{o=window.opener&&!window.opener.closed?window.opener:null;if(o){o.postMessage(d,' . wp_json_encode( $origin ) . ');}}catch(e){o=null;}if(o){setTimeout(function(){window.close();},1500);}else{setTimeout(function(){location.replace(b);},' . ( $ok ? 1200 : 2500 ) . ');}})();';
		return FLXB_Iyzico_Recurring::page_html( $ok ? __( 'Payment received', 'flx-donate' ) : __( '\\nPayment not completed\\n', 'flx-donate' ), $body, $ok, $script );
	}

	/* ------------------------------------------------------------------
	 * Refund / cancel
	 * ------------------------------------------------------------------ */

	public function refund( $record, $amount = null ) {
		return $this->money( 'refund', $record, null, $amount );
	}

	/**
	 * Refund (full or an amount) or cancel (void) an iyzico payment.
	 *
	 * @param string      $op       refund | cancel.
	 * @param object|null $record   emsfb_pay_ row (first payment), or null.
	 * @param array|null  $donation Flux Donate donation (renewals have no pay row), or null.
	 * @param float|null  $amount   Refund amount, null = all that is left.
	 * @return FLXB_Payment_Result
	 */
	public function money( $op, $record, $donation = null, $amount = null ) {
		$api = FLXB_Iyzico_Api::make();
		if ( ! $api ) {
			return FLXB_Payment_Result::failed( __( '\\niyzico is not configured on this site.\', \'en\']]', 'flx-donate' ) );
		}
		if ( ! $donation && is_object( $record ) && (int) $record->id ) {
			global $wpdb;
			$did      = (int) $wpdb->get_var( $wpdb->prepare( 'SELECT id FROM ' . flxb_table( 'donations' ) . ' WHERE pay_id = %d ORDER BY id ASC LIMIT 1', (int) $record->id ) ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			$donation = $did ? FLXB_Donations::get( $did ) : null;
		}
		$dmeta = $donation ? FLXB_Donations::meta( $donation ) : array();
		if ( ! $record && $donation && (int) $donation['pay_id'] && empty( $dmeta['renewal'] ) ) {
			$record = FLXB_Payment_Records::get( (int) $donation['pay_id'] );
		}
		$rmeta = $record ? self::row_meta( $record ) : array();
		/* A renewal's own payment lives in the donation; a first payment in both. */
		$iyz = ! empty( $dmeta['iyzico']['paymentId'] ) ? $dmeta['iyzico'] : ( $rmeta['iyzico'] ?? array() );
		if ( empty( $iyz['paymentId'] ) ) {
			return FLXB_Payment_Result::failed( __( '\\nThis payment cannot be refunded from the site.\\n', 'flx-donate' ) );
		}
		if ( $record && $donation && ! empty( $dmeta['renewal'] ) ) {
			$record = null; // the pay row belongs to the first payment
		}
		$currency = ! empty( $iyz['currency'] ) ? $iyz['currency'] : ( $donation ? $donation['currency'] : $record->currency );
		$ip       = isset( $_SERVER['REMOTE_ADDR'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ) ) : '127.0.0.1';
		$conv     = 'flxbx-' . $iyz['paymentId'] . '-' . time();
		$save     = function ( $iyz, $status ) use ( $record, $donation, $rmeta ) {
			if ( $record ) {
				$rmeta['iyzico'] = $iyz;
				FLXB_Payment_Records::update( $record->id, array_filter( array( 'meta' => $rmeta, 'status' => $status ) ) );
			}
			if ( $donation ) {
				FLXB_Donations::update_meta( (int) $donation['id'], array( 'iyzico' => $iyz ) );
				if ( $status ) {
					FLXB_Donations::set_status_by_uniq( $donation['uniq'], $status );
				}
			}
		};

		if ( 'cancel' === $op ) {
			try {
				$r = $api->cancel( $iyz['paymentId'], $ip, $conv, 'buyer_request' );
			} catch ( FLXB_Iyzico_Error $e ) {
				return FLXB_Payment_Result::failed( $e->getMessage() );
			}
			if ( ! FLXB_Iyzico_Api::ok( $r ) ) {
				return FLXB_Payment_Result::failed( 'iyzico: ' . FLXB_Iyzico_Api::error_text( $r ) );
			}
			$iyz['canceled_at'] = current_time( 'mysql', true );
			$save( $iyz, 'canceled' );
			if ( $donation ) {
				FLXB_Donations::add_audit( (int) $donation['id'], 'cancel', array( 'provider' => 'iyzico', 'payment' => $iyz['paymentId'] ) );
			}
			return FLXB_Payment_Result::completed( $iyz['paymentId'] );
		}

		$left = 0;
		foreach ( $iyz['items'] as $it ) {
			$left += FLXB_Iyzico_Api::minor( $it['paid'] ) - FLXB_Iyzico_Api::minor( $it['refunded'] ?? 0 );
		}
		$want = null === $amount ? $left : FLXB_Iyzico_Api::minor( $amount );
		if ( $left <= 0 ) {
			return FLXB_Payment_Result::failed( __( '\\nThis payment has already been refunded.\\n', 'flx-donate' ) );
		}
		if ( $want <= 0 || $want > $left ) {
			return FLXB_Payment_Result::failed( sprintf( /* translators: %s: amount */ __( '\\nEnter an amount up to%s.\\n', 'flx-donate' ), flxb_money( $left / 100, $currency ) ) );
		}
		$done  = 0;
		$error = '';
		foreach ( $iyz['items'] as $i => $it ) {
			$rest = FLXB_Iyzico_Api::minor( $it['paid'] ) - FLXB_Iyzico_Api::minor( $it['refunded'] ?? 0 );
			if ( $rest <= 0 || $done >= $want ) {
				continue;
			}
			$part = min( $rest, $want - $done );
			try {
				$r = $api->refund( $it['tx'], $part / 100, $currency, $ip, $conv . '-' . $i );
			} catch ( FLXB_Iyzico_Error $e ) {
				$error = $e->getMessage();
				break;
			}
			if ( ! FLXB_Iyzico_Api::ok( $r ) ) {
				$error = 'iyzico: ' . FLXB_Iyzico_Api::error_text( $r );
				break;
			}
			$iyz['items'][ $i ]['refunded'] = round( ( FLXB_Iyzico_Api::minor( $it['refunded'] ?? 0 ) + $part ) / 100, 2 );
			$done += $part;
		}
		if ( $done > 0 ) {
			$iyz['refunds'][] = array( 'amount' => round( $done / 100, 2 ), 'at' => current_time( 'mysql', true ), 'user' => get_current_user_id() );
			$save( $iyz, $done >= $left ? 'refunded' : 'partially_refunded' );
			if ( $donation ) {
				FLXB_Donations::add_audit( (int) $donation['id'], 'refund', array( 'provider' => 'iyzico', 'amount' => round( $done / 100, 2 ) ) );
			}
		}
		if ( '' !== $error ) {
			return FLXB_Payment_Result::failed( $done > 0 ? sprintf( /* translators: 1: refunded amount, 2: error */ __( '\\n%1$swas refunded, then iyzico stopped:%2$s\\n', 'flx-donate' ), flxb_money( $done / 100, $currency ), $error ) : $error );
		}
		return FLXB_Payment_Result::completed( $iyz['paymentId'], array( 'record' => array( 'status' => $done >= $left ? 'refunded' : 'partially_refunded', 'meta' => array( 'amount' => round( $done / 100, 2 ) ) ) ) );
	}

	/** Cancel a recurring gift: Flux Donate stops charging and forgets the stored card. */
	public function cancel_subscription( $subscription_id ) {
		$s = FLXB_Subscriptions::get_by_ref( 'iyzico', (string) $subscription_id );
		if ( ! $s ) {
			return FLXB_Payment_Result::failed( __( '\\nRecurring donation not found.\\n', 'flx-donate' ) );
		}
		FLXB_Iyzico_Recurring::cancel( (int) $s['id'], 'admin' );
		return FLXB_Payment_Result::completed( (string) $subscription_id );
	}
}

add_action( 'rest_api_init', array( 'FLXB_Payment_Iyzico', 'routes' ) );
