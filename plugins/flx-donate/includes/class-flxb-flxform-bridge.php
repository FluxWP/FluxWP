<?php
/**
 * Flux Donate ↔ Flux Forms.
 *
 * Listens to what Flux Forms already does; nothing in Flux Forms has to change for this part:
 *  - filter `flxf_submit_response` (event_type = payment): the final submit of a paid form, after the
 *    payment guard verified it. Gives the form rows (name, phone, e-mail, consents) and the
 *    confirmation code; the amount comes from the server-side payment record (emsfb_pay_).
 *  - action `efb_stripe_webhook_event`: Stripe webhooks verified by Flux Forms (refunds, subscription
 *    changes, renewals = invoice.paid). When Flux Donate owns the payment layer the same handler is
 *    called by its own webhook route.
 *  - option `flxf_automation_runs`: certificate codes created by Flux Forms automations (Flux Certificates).
 *  - hourly cron + an admin backfill: import completed rows of {prefix}emsfb_pay_ that were not
 *    seen yet (e.g. the visitor paid but closed the page before the final submit).
 *
 * @package FLX_Bagis
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class FLXB_FLXForm_Bridge {

	/** Payment-record statuses that mean "money was received". */
	const PAY_OK = array( 'completed', 'succeeded', 'active', 'trialing', 'past_due', 'refunded', 'partially_refunded' );

	/** Donations recorded in this request that still wait for a certificate code. */
	private static $pending_certs = array();

	public static function init() {
		add_filter( 'flxf_submit_response', array( __CLASS__, 'on_submit' ), 20, 2 );
		add_action( 'efb_stripe_webhook_event', array( __CLASS__, 'on_stripe_event' ), 10, 1 );
		add_action( FLXB_Install::CRON_HOOK, array( __CLASS__, 'sync' ) );
		add_action( 'shutdown', array( __CLASS__, 'fill_certificates' ), 20 );
		add_filter( 'flxf_payment_server_amount', array( __CLASS__, 'server_amount' ), 10, 4 );
	}

	/* ------------------------------------------------------------------
	 * Flux Forms data access (read only)
	 * ------------------------------------------------------------------ */

	public static function pay_table() {
		global $wpdb;
		$t = $wpdb->prefix . 'emsfb_pay_';
		static $ok = null;
		if ( null === $ok ) {
			$ok = ( $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $wpdb->esc_like( $t ) ) ) === $t );
		}
		return $ok ? $t : '';
	}

	public static function pay_row_by_track( $track ) {
		global $wpdb;
		$t = self::pay_table();
		if ( '' === $t || '' === (string) $track ) {
			return null;
		}
		return $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$t} WHERE track = %s ORDER BY id DESC LIMIT 1", (string) $track ) ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
	}

	public static function pay_row_by_ref( $gateway, $ref, $column = 'transaction_id' ) {
		global $wpdb;
		$t = self::pay_table();
		if ( '' === $t || '' === (string) $ref || ! in_array( $column, array( 'transaction_id', 'subscription_id' ), true ) ) {
			return null;
		}
		return $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$t} WHERE {$column} = %s AND gateway = %s ORDER BY id DESC LIMIT 1", (string) $ref, (string) $gateway ) ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
	}

	/** Rows stored for a confirmation code (EFB response content). */
	public static function message_rows( $track ) {
		global $wpdb;
		$t = $wpdb->prefix . 'emsfb_msg_';
		if ( '' === (string) $track ) {
			return array();
		}
		$raw = $wpdb->get_var( $wpdb->prepare( "SELECT content FROM `{$t}` WHERE track = %s ORDER BY msg_id DESC LIMIT 1", (string) $track ) ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		return self::decode_json( $raw );
	}

	public static function decode_json( $raw ) {
		if ( ! is_string( $raw ) || '' === $raw ) {
			return array();
		}
		$d = json_decode( $raw, true );
		if ( ! is_array( $d ) ) {
			$d = json_decode( str_replace( '\\', '', $raw ), true );
		}
		return is_array( $d ) ? $d : array();
	}

	/** Form structure (for field labels). */
	public static function form_structure( $form_id ) {
		global $wpdb;
		$t   = $wpdb->prefix . 'emsfb_form';
		$raw = $wpdb->get_var( $wpdb->prepare( "SELECT form_structer FROM `{$t}` WHERE form_id = %d", (int) $form_id ) ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		return self::decode_json( (string) $raw );
	}

	private static function lower( $s ) {
		$s = wp_strip_all_tags( (string) $s );
		return function_exists( 'mb_strtolower' ) ? mb_strtolower( $s, 'UTF-8' ) : strtolower( $s );
	}

	/**
	 * Donor details from form rows: name, email, phone, consent_kvkk, consent_email, consent_sms.
	 *
	 * @param array $rows      Submitted rows (id_, name, type, value).
	 * @param array $structure Stored form structure (labels), optional.
	 */
	public static function contact_from_rows( array $rows, array $structure = array() ) {
		$labels = array();
		foreach ( $structure as $i => $el ) {
			if ( $i > 0 && is_array( $el ) && ! empty( $el['id_'] ) ) {
				$labels[ (string) $el['id_'] ] = (string) ( $el['name'] ?? '' );
			}
		}
		$out   = array( 'name' => '', 'surname' => '', 'email' => '', 'phone' => '', 'consent_kvkk' => 0, 'consent_email' => 0, 'consent_sms' => 0 );
		$texts = array();
		foreach ( $rows as $r ) {
			$r = is_object( $r ) ? (array) $r : $r;
			if ( ! is_array( $r ) ) {
				continue;
			}
			$type = (string) ( $r['type'] ?? '' );
			if ( in_array( $type, array( 'payment', 'w_link', 'option' ), true ) ) {
				continue;
			}
			$val = $r['value'] ?? '';
			if ( is_array( $val ) ) {
				$val = implode( ', ', array_filter( $val, 'is_scalar' ) );
			}
			$val   = trim( wp_strip_all_tags( (string) $val ) );
			$id    = (string) ( $r['id_'] ?? '' );
			$label = self::lower( ( $labels[ $id ] ?? '' ) !== '' ? $labels[ $id ] : ( $r['name'] ?? '' ) );
			if ( '' === $val ) {
				continue;
			}
			if ( '' === $out['email'] && ( 'email' === $type || ( 'text' === $type && is_email( $val ) ) ) ) {
				$out['email'] = $val;
				continue;
			}
			if ( '' === $out['phone'] && in_array( $type, array( 'mobile', 'tel', 'phone' ), true ) ) {
				$out['phone'] = $val;
				continue;
			}
			if ( preg_match( '/checkbox|switch|radio|yesno/i', $type ) ) {
				$hay = $label . ' ' . self::lower( $val );
				if ( preg_match( '/kvkk|aydınlatma|kişisel veri|personal data|privacy|gizlilik/u', $hay ) ) {
					$out['consent_kvkk'] = 1;
				}
				if ( preg_match( '/(e-?posta|email|e-mail|bülten|newsletter)/u', $hay ) && preg_match( '/izin|onay|ister|consent|agree|abone|subscribe|almak/u', $hay ) ) {
					$out['consent_email'] = 1;
				}
				if ( preg_match( '/\bsms\b|ileti|mesaj|whatsapp/u', $hay ) && preg_match( '/izin|onay|ister|consent|agree|almak/u', $hay ) ) {
					$out['consent_sms'] = 1;
				}
				continue;
			}
			if ( 'text' === $type || 'firstName' === $type || 'lastName' === $type ) {
				$texts[] = array( 'label' => $label, 'value' => $val, 'type' => $type );
			}
		}
		foreach ( $texts as $t ) {
			$is_full = preg_match( '/ad soyad|adı soyadı|ad-soyad|isim soyisim|full ?name/u', $t['label'] );
			$is_last = ! $is_full && ( 'lastName' === $t['type'] || preg_match( '/soyad|soyisim|surname|last ?name|family/u', $t['label'] ) );
			$is_name = $is_full || 'firstName' === $t['type'] || preg_match( '/(^|\s)(ad|adı|adınız|isim|isminiz|name|first ?name)(\s|$)/u', $t['label'] );
			if ( $is_last && '' === $out['surname'] ) {
				$out['surname'] = $t['value'];
			} elseif ( $is_name && '' === $out['name'] ) {
				$out['name'] = $t['value'];
			}
		}
		if ( '' === $out['name'] && $texts ) {
			$out['name'] = $texts[0]['value'];
		}
		$out['name'] = trim( $out['name'] . ' ' . $out['surname'] );
		unset( $out['surname'] );
		return $out;
	}

	/** Does a gift through this form count as a donation? */
	public static function is_donation_form( $form_id ) {
		$form_id = (int) $form_id;
		$ok      = true;
		if ( 'list' === flxb_setting( 'donation_forms', 'payments' ) ) {
			$ok = in_array( $form_id, array_map( 'intval', (array) flxb_setting( 'form_ids', array() ) ), true ) || FLXB_Types::for_form( $form_id ) > 0;
		}
		return (bool) apply_filters( 'flxb_is_donation_form', $ok, $form_id );
	}

	/* ------------------------------------------------------------------
	 * Recording
	 * ------------------------------------------------------------------ */

	private static function pay_status_to_donation( $s ) {
		$s = strtolower( (string) $s );
		if ( in_array( $s, array( 'refunded', 'partially_refunded' ), true ) ) {
			return $s;
		}
		return 'completed';
	}

	/**
	 * One emsfb_pay_ row → a donation (and, for a subscription, the subscription).
	 *
	 * @param object $row     emsfb_pay_ row.
	 * @param array  $contact contact_from_rows() result (may be empty).
	 * @param string $source  submit | webhook | sync | backfill.
	 * @return int Donation id or 0.
	 */
	public static function record_pay_row( $row, array $contact, $source, array $extra = array(), array $rows = array() ) {
		if ( ! is_object( $row ) ) {
			return 0;
		}
		$status = strtolower( (string) $row->status );
		$is_sub = 'subscription' === (string) $row->payment_type;
		if ( ! in_array( $status, self::PAY_OK, true ) && ! ( $is_sub && 'canceled' === $status ) ) {
			return 0;
		}
		if ( ! self::is_donation_form( (int) $row->form_id ) ) {
			return 0;
		}
		if ( 'woocommerce' === (string) $row->gateway ) {
			return 0; // 2.0: paid through a WooCommerce order; the order ledger records it (FLXB_WC_Ledger)
		}
		if ( '' === (string) ( $contact['email'] ?? '' ) && '' !== (string) $row->payer_email ) {
			$contact['email'] = (string) $row->payer_email;
		}
		$gateway  = sanitize_key( (string) $row->gateway );
		$created  = ! empty( $row->created_at ) ? (string) $row->created_at : flxb_now();
		$donor_id = FLXB_Donors::upsert( array_merge( $contact, array( 'allow_anonymous' => true ) ) );
		$type_id = FLXB_Types::for_form( (int) $row->form_id, $created );
		$snap     = self::snapshot( $row, $type_id, $rows );
		$sub_id   = 0;
		$ref      = (string) $row->transaction_id;
		if ( $is_sub ) {
			$ref      = '' !== (string) $row->subscription_id ? (string) $row->subscription_id : $ref;
			$interval = strtolower( (string) $row->interval_unit );
			$sub_id   = FLXB_Subscriptions::upsert(
				array(
					'donor_id'       => $donor_id,
					'amount'         => (float) $row->amount,
					'currency'       => (string) $row->currency,
					'interval_unit'  => $interval,
					'status'         => FLXB_Subscriptions::map_status( $status ),
					'provider'       => $gateway,
					'provider_ref'   => $ref,
					'form_id'        => (int) $row->form_id,
					'type_id'    => $type_id,
					'track'          => (string) $row->track,
					'started_at'     => $created,
					'next_charge_at' => $extra['next_charge_at'] ?? FLXB_Subscriptions::next_charge( $interval, $created ),
				)
			);
		}
		if ( 'canceled' === $status ) {
			$status = 'completed'; // the first charge of a later canceled subscription was received
		}
		return FLXB_Donations::record(
			array(
				'provider'         => $gateway,
				'provider_ref'     => $ref,
				'amount'           => (float) $row->amount,
				'currency'         => (string) $row->currency,
				'type'             => $is_sub ? 'recurring' : 'one_time',
				'status'           => self::pay_status_to_donation( $status ),
				'form_id'          => (int) $row->form_id,
				'track'            => (string) $row->track,
				'pay_id'           => (int) $row->id,
				'donor_id'         => $donor_id,
				'subscription_id'  => $sub_id,
				'type_id'      => $type_id,
				'option_key'       => $snap['option_key'],
				'option_label'     => $snap['option_label'],
				'quantity'         => $snap['quantity'],
				'unit_price'       => $snap['unit_price'],
				'certificate_code' => (string) ( $extra['certificate_code'] ?? self::certificate_for_track( (string) $row->track ) ),
				'source'           => $source,
				'created_at'       => $created,
				'lines'            => (array) $snap['lines'],
				'beneficiaries'    => (array) $snap['beneficiaries'],
				'meta'             => ! empty( $snap['proxy_consent'] ) ? array( 'proxy_consent' => $snap['proxy_consent'] ) : array(),
			)
		);
	}

	/** flxf_submit_response: a paid form was submitted (payment already verified by Flux Forms). */
	public static function on_submit( $response, $args ) {
		try {
			if ( ! is_array( $args ) || 'payment' !== ( $args['event_type'] ?? '' ) || ! is_array( $response ) || empty( $response['success'] ) ) {
				return $response;
			}
			$track = sanitize_text_field( (string) ( $args['track_code'] ?? '' ) );
			$row   = self::pay_row_by_track( $track );
			if ( ! $row ) {
				return $response;
			}
			$rows      = ! empty( $args['enriched_submitted_values'] ) && is_array( $args['enriched_submitted_values'] ) ? $args['enriched_submitted_values'] : (array) ( $args['submitted_values'] ?? array() );
			$structure = is_array( $args['form_fields'] ?? null ) ? $args['form_fields'] : array();
			$id        = self::record_pay_row( $row, self::contact_from_rows( $rows, $structure ), 'submit', array(), $rows );
			if ( $id ) {
				$d = FLXB_Donations::get( $id );
				if ( $d && '' === (string) $d['certificate_code'] ) {
					self::$pending_certs[ $id ] = $track;
				}
			}
		} catch ( \Throwable $e ) {
			self::log( 'on_submit: ' . $e->getMessage() );
		}
		return $response;
	}

	/** Certificate code that a Flux Forms automation created for a confirmation code ('' if none). */
	public static function certificate_for_track( $track ) {
		if ( '' === (string) $track ) {
			return '';
		}
		foreach ( (array) get_option( 'flxf_automation_runs', array() ) as $run ) {
			if ( is_array( $run ) && isset( $run['track'] ) && (string) $run['track'] === (string) $track && ! empty( $run['cert']['code'] ) ) {
				return (string) $run['cert']['code'];
			}
		}
		return '';
	}

	/** End of request: automations may have created the certificate after the reply. */
	public static function fill_certificates() {
		foreach ( self::$pending_certs as $id => $track ) {
			$code = self::certificate_for_track( $track );
			if ( '' !== $code ) {
				FLXB_Donations::set_certificate( $id, $code );
			}
		}
		self::$pending_certs = array();
	}

	/**
	 * What was given: option, quantity and unit price. Taken from the payment record's meta when
	 * Flux Donate started the payment (exact snapshot), else priced again from the form rows with the
	 * type (same function the server used for the charge).
	 */
	public static function snapshot( $row, $type_id, array $rows ) {
		$out  = array( 'option_key' => '', 'option_label' => '', 'quantity' => 1, 'unit_price' => (float) $row->amount, 'lines' => array(), 'beneficiaries' => array(), 'proxy_consent' => null );
		$meta = ! empty( $row->meta ) ? json_decode( (string) $row->meta, true ) : null;
		if ( is_array( $meta ) && ! empty( $meta['flxb'] ) && is_array( $meta['flxb'] ) ) {
			return array_merge( $out, array_intersect_key( $meta['flxb'], $out ) );
		}
		$t = $type_id ? FLXB_Types::get( $type_id ) : null;
		if ( ! $t ) {
			return $out;
		}
		if ( ! $rows ) {
			$rows = self::message_rows( (string) $row->track );
		}
		return self::snapshot_from_price( FLXB_Types::price_rows( $t, (int) $row->form_id, $rows ), (float) $row->amount );
	}

	public static function snapshot_from_price( array $p, $amount ) {
		$out = array( 'option_key' => '', 'option_label' => '', 'quantity' => 1, 'unit_price' => (float) $amount, 'lines' => array(), 'beneficiaries' => array(), 'proxy_consent' => null );
		if ( empty( $p['lines'] ) ) {
			return $out;
		}
		$first = $p['lines'][0];
		$keys  = array_unique( wp_list_pluck( $p['lines'], 'key' ) );
		$lines = array();
		foreach ( $p['lines'] as $l ) {
			$lines[] = array_intersect_key( $l, array_flip( array( 'key', 'label', 'units', 'unit_price', 'amount', 'unit', 'units_per' ) ) );
		}
		return array(
			'option_key'    => 1 === count( $keys ) ? (string) $first['key'] : 'mixed',
			'option_label'  => implode( ', ', array_unique( wp_list_pluck( $p['lines'], 'label' ) ) ),
			'quantity'      => max( 1, (int) $p['quantity'] ),
			'unit_price'    => 1 === count( $p['lines'] ) ? (float) $first['unit_price'] : round( (float) $amount / max( 1, (int) $p['quantity'] ), 4 ),
			'lines'         => $lines,
			'beneficiaries' => isset( $p['beneficiaries'] ) ? array_values( (array) $p['beneficiaries'] ) : array(),
			'proxy_consent' => $p['proxy_consent'] ?? null,
		);
	}

	/**
	 * Filter `flxf_payment_server_amount` (Flux Forms computes every quote and charge through it):
	 * a form linked to a donation type is priced from the type.
	 *
	 * @param array $obj     {price_total, email, valobj} from Flux Forms.
	 * @param array $rows    Visitor's rows.
	 * @param array $fs      Form structure.
	 * @param int   $form_id Form id.
	 */
	public static function server_amount( $obj, $rows, $fs, $form_id = 0 ) {
		if ( ! is_array( $obj ) || ! (int) $form_id ) {
			return $obj;
		}
		$t = FLXB_Types::for_form_type( (int) $form_id, '', false );
		if ( ! $t || empty( $t['form_map'][ (int) $form_id ] ) ) {
			return $obj;
		}
		if ( ! FLXB_Types::is_open( $t ) ) {
			$obj['price_total'] = 0;
			$obj['flxb_error']   = FLXB_Types::closed_message( $t );
			$obj['error']       = $obj['flxb_error']; // Flux Forms shows it in the quote
			return $obj;
		}
		/* The type's prices are in its own currency; the form charges in the form's currency. */
		$form_cur = isset( $fs[0]['currency'] ) && strlen( (string) $fs[0]['currency'] ) > 2 ? strtoupper( (string) $fs[0]['currency'] ) : 'USD';
		if ( $form_cur !== strtoupper( (string) $t['currency'] ) ) {
			$obj['price_total'] = 0;
			$obj['flxb_error']   = __( 'This form and its donation type use different currencies, so the payment was stopped. Please contact the site administrator.', 'flx-donate' );
			$obj['error']       = $obj['flxb_error']; // Flux Forms shows it in the quote
			return $obj;
		}
		$p = FLXB_Types::price_rows( $t, (int) $form_id, is_array( $rows ) ? $rows : array() );
		if ( ! $p['mapped'] ) {
			return $obj;
		}
		if ( ! $p['ok'] ) {
			$obj['price_total'] = 0;
			$obj['flxb_error']   = $p['message'];
			$obj['error']       = $obj['flxb_error']; // Flux Forms shows it in the quote
			return $obj;
		}
		/* Start from Flux Forms's own server total, take out what it charged for the mapped
		 * rows and add the type's price for them; unmapped priced rows keep Flux Forms's price. */
		$map    = $t['form_map'][ (int) $form_id ];
		$opts   = array_map( 'strval', array_keys( (array) ( $map['opts'] ?? array() ) ) );
		$freef  = (string) ( $map['free'] ?? '' );
		$total  = (float) ( $obj['price_total'] ?? 0 );
		$valobj = isset( $obj['valobj'] ) && is_array( $obj['valobj'] ) ? $obj['valobj'] : array();
		foreach ( $valobj as $i => $v ) {
			$id = is_array( $v ) ? (string) ( $v['id_'] ?? '' ) : '';
			if ( '' === $id || ! in_array( $id, $opts, true ) ) {
				continue;
			}
			$total                -= (float) ( $v['price'] ?? 0 );
			$valobj[ $i ]['price'] = 0;
			foreach ( $p['lines'] as $l ) {
				if ( $l['row'] === $id ) {
					$valobj[ $i ]['price'] = $l['amount'];
				}
			}
		}
		if ( '' !== $freef ) {
			foreach ( (array) $rows as $r ) {
				$r = is_object( $r ) ? (array) $r : $r;
				if ( is_array( $r ) && (string) ( $r['id_'] ?? '' ) === $freef && isset( $r['price'] ) ) {
					$total -= abs( (float) $r['price'] );
				}
			}
		}
		$total += $p['total'];
		$obj['valobj']      = $valobj;
		$total              = max( 0, round( $total, 2 ) );
		$obj['price_total'] = $total;
		$obj['flxb']         = array_merge( self::snapshot_from_price( $p, $total ), array( 'type_id' => (int) $t['id'] ) );
		return $obj;
	}

	/* ------------------------------------------------------------------
	 * Stripe events (verified by whoever received the webhook)
	 * ------------------------------------------------------------------ */

	public static function stripe_major( $minor, $currency ) {
		$zero = array( 'BIF', 'CLP', 'DJF', 'GNF', 'JPY', 'KMF', 'KRW', 'MGA', 'PYG', 'RWF', 'UGX', 'VND', 'VUV', 'XAF', 'XOF', 'XPF' );
		return in_array( strtoupper( (string) $currency ), $zero, true ) ? (float) $minor : (float) $minor / 100;
	}

	public static function on_stripe_event( $event ) {
		try {
			if ( ! is_array( $event ) || empty( $event['type'] ) || ! isset( $event['data']['object'] ) || ! is_array( $event['data']['object'] ) ) {
				return;
			}
			$o    = $event['data']['object'];
			$type = (string) $event['type'];
			switch ( $type ) {
				case 'payment_intent.succeeded':
					$row = self::pay_row_by_ref( 'stripe', (string) ( $o['id'] ?? '' ) );
					if ( $row && 'completed' === $row->status ) {
						self::record_pay_row( $row, self::contact_for_track( $row ), 'webhook' );
					}
					break;

				case 'charge.refunded':
					$pi = isset( $o['payment_intent'] ) && is_string( $o['payment_intent'] ) ? $o['payment_intent'] : '';
					if ( '' !== $pi ) {
						$full = ! empty( $o['refunded'] ) || ( isset( $o['amount_refunded'], $o['amount'] ) && (int) $o['amount_refunded'] >= (int) $o['amount'] );
						FLXB_Donations::set_status_by_uniq( FLXB_Donations::uniq( 'stripe', $pi ), $full ? 'refunded' : 'partially_refunded' );
					}
					break;

				case 'customer.subscription.created':
				case 'customer.subscription.updated':
				case 'customer.subscription.deleted':
					$sid = (string) ( $o['id'] ?? '' );
					if ( '' === $sid ) {
						break;
					}
					$extra = array();
					$end   = $o['current_period_end'] ?? ( $o['items']['data'][0]['current_period_end'] ?? null );
					if ( $end ) {
						$extra['next_charge_at'] = gmdate( 'Y-m-d H:i:s', (int) $end );
					}
					if ( ! empty( $o['canceled_at'] ) ) {
						$extra['canceled_at'] = gmdate( 'Y-m-d H:i:s', (int) $o['canceled_at'] );
					}
					$status = 'customer.subscription.deleted' === $type ? 'canceled' : (string) ( $o['status'] ?? 'active' );
					if ( ! FLXB_Subscriptions::get_by_ref( 'stripe', $sid ) ) {
						$row = self::pay_row_by_ref( 'stripe', $sid, 'subscription_id' );
						if ( $row ) {
							self::record_pay_row( $row, self::contact_for_track( $row ), 'webhook', $extra );
						}
					}
					FLXB_Subscriptions::set_status( 'stripe', $sid, $status, $extra );
					break;

				case 'invoice.paid':
				case 'invoice.payment_succeeded':
					self::stripe_invoice_paid( $o );
					break;

				case 'invoice.payment_failed':
					$sid = self::invoice_subscription( $o );
					if ( '' !== $sid ) {
						FLXB_Subscriptions::set_status( 'stripe', $sid, 'past_due' );
					}
					break;
			}
		} catch ( \Throwable $e ) {
			self::log( 'stripe event: ' . $e->getMessage() );
		}
	}

	private static function invoice_subscription( array $o ) {
		$sid = $o['subscription'] ?? ( $o['parent']['subscription_details']['subscription'] ?? '' );
		return is_string( $sid ) ? $sid : '';
	}

	/** A renewal of a recurring donation. */
	private static function stripe_invoice_paid( array $o ) {
		$sid = self::invoice_subscription( $o );
		if ( '' === $sid || empty( $o['id'] ) ) {
			return;
		}
		$sub = FLXB_Subscriptions::get_by_ref( 'stripe', $sid );
		if ( ! $sub ) {
			$row = self::pay_row_by_ref( 'stripe', $sid, 'subscription_id' );
			if ( $row ) {
				self::record_pay_row( $row, self::contact_for_track( $row ), 'webhook' );
				$sub = FLXB_Subscriptions::get_by_ref( 'stripe', $sid );
			}
		}
		if ( ! $sub ) {
			return;
		}
		$period_end = $o['lines']['data'][0]['period']['end'] ?? null;
		FLXB_Subscriptions::upsert(
			array(
				'provider'       => 'stripe',
				'provider_ref'   => $sid,
				'status'         => 'active',
				'next_charge_at' => $period_end ? gmdate( 'Y-m-d H:i:s', (int) $period_end ) : FLXB_Subscriptions::next_charge( $sub['interval_unit'] ),
			)
		);
		/* The first invoice is the gift recorded when the subscription started. */
		if ( 'subscription_create' === ( $o['billing_reason'] ?? '' ) ) {
			return;
		}
		$currency = strtoupper( (string) ( $o['currency'] ?? $sub['currency'] ) );
		FLXB_Donations::record(
			array(
				'provider'        => 'stripe',
				'provider_ref'    => (string) $o['id'],
				'amount'          => self::stripe_major( (int) ( $o['amount_paid'] ?? 0 ), $currency ),
				'currency'        => $currency,
				'type'            => 'recurring',
				'status'          => 'completed',
				'form_id'         => (int) $sub['form_id'],
				'donor_id'        => (int) $sub['donor_id'],
				'subscription_id' => (int) $sub['id'],
				'type_id'     => (int) $sub['type_id'],
				'source'          => 'webhook',
				'created_at'      => ! empty( $o['status_transitions']['paid_at'] ) ? gmdate( 'Y-m-d H:i:s', (int) $o['status_transitions']['paid_at'] ) : flxb_now(),
				'meta'            => array( 'renewal' => true, 'subscription' => $sid ),
			)
		);
	}

	private static function contact_for_track( $row ) {
		return self::contact_from_rows( self::message_rows( (string) $row->track ), self::form_structure( (int) $row->form_id ) );
	}

	/* ------------------------------------------------------------------
	 * Sync / backfill
	 * ------------------------------------------------------------------ */

	/**
	 * Import received payments from emsfb_pay_ (idempotent).
	 *
	 * @param int $min_age Skip rows younger than this many seconds (the form submit records those with richer data).
	 * @return array{seen:int, imported:int, existing:int, skipped:int}
	 */
	public static function import( $min_age = 0, $source = 'backfill' ) {
		global $wpdb;
		$out = array( 'seen' => 0, 'imported' => 0, 'existing' => 0, 'skipped' => 0 );
		$t   = self::pay_table();
		if ( '' === $t ) {
			return $out;
		}
		$after  = 0;
		$cutoff = gmdate( 'Y-m-d H:i:s', time() - (int) $min_age );
		$dn     = flxb_table( 'donations' );
		do {
			$rows = (array) $wpdb->get_results( $wpdb->prepare( "SELECT * FROM {$t} WHERE id > %d AND (created_at IS NULL OR created_at <= %s) ORDER BY id ASC LIMIT 200", $after, $cutoff ) ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			foreach ( $rows as $row ) {
				$after = (int) $row->id;
				++$out['seen'];
				$known = (int) $wpdb->get_var( $wpdb->prepare( "SELECT id FROM {$dn} WHERE pay_id = %d LIMIT 1", (int) $row->id ) ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				if ( $known ) {
					/* Keep refund states in step with Flux Forms's record. */
					if ( in_array( $row->status, array( 'refunded', 'partially_refunded' ), true ) ) {
						$d = FLXB_Donations::get( $known );
						if ( $d ) {
							FLXB_Donations::set_status_by_uniq( $d['uniq'], $row->status );
						}
					}
					++$out['existing'];
					continue;
				}
				$mrows = self::message_rows( (string) $row->track );
				$cmeta = ! empty( $row->meta ) ? json_decode( (string) $row->meta, true ) : null;
				$cont  = self::contact_from_rows( $mrows, self::form_structure( (int) $row->form_id ) );
				/* Providers that asked for the donor's details at the start (iyzico) kept them. */
				if ( is_array( $cmeta ) && ! empty( $cmeta['contact'] ) && is_array( $cmeta['contact'] ) ) {
					$cont = array_merge( $cont, array_filter( $cmeta['contact'] ) );
				}
				$id = self::record_pay_row( $row, $cont, $source, array(), $mrows );
				$id ? ++$out['imported'] : ++$out['skipped'];
			}
		} while ( count( $rows ) === 200 );
		update_option( 'flxb_last_sync', array( 'time' => time(), 'result' => $out, 'source' => $source ), false );
		return $out;
	}

	/** Hourly: catch payments whose final submit never came. */
	public static function sync() {
		self::import( HOUR_IN_SECONDS, 'sync' );
	}

	private static function log( $msg ) {
		if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
			error_log( '[Flux Donate] ' . $msg ); // phpcs:ignore WordPress.PHP.DevelopmentFunctions
		}
	}
}
