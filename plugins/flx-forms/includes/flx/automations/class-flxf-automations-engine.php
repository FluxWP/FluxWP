<?php
/**
 * Flux Forms Automations: runtime.
 *
 * Two entry points, both in the same PHP request as the submission:
 *  1. `flxf_submit_response` (core filter, right before EFB sends its JSON reply): runs each matching
 *     flow up to and including its last "show on thank-you screen" step, so the certificate card can
 *     travel in that very reply (flxf_html).
 *  2. `efb_after_form_integration` (after the reply has been flushed): runs the remaining steps
 *     (sending, e-mails, webhooks) without making the visitor wait.
 * A `shutdown` fallback closes runs whose second half never got its hook.
 *
 * Each (flow, tracking code) pair runs once: a retried submit cannot create a second certificate.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class FLXF_Automations_Engine {

	/** In-flight runs of this request, keyed "flow_id|track". */
	private static $runs = array();

	public static function init() {
		add_filter( 'flxf_submit_response', array( __CLASS__, 'on_response' ), 10, 2 );
		add_action( 'efb_after_form_integration', array( __CLASS__, 'on_integration' ), 20, 1 );
		add_action( 'shutdown', array( __CLASS__, 'on_shutdown' ), 1 );
		add_action( 'flxf_automations_resume', array( __CLASS__, 'resume' ), 10, 1 );
		add_action( 'flxf_external_payment', array( __CLASS__, 'on_external_payment' ), 10, 1 );
	}

	/**
	 * 2.1.0: a payment settled outside the form (Flux Donate: the WooCommerce order of a form was paid).
	 * Server-side only (a PHP action, never a request parameter). Runs the "payment completed" and
	 * "form submitted" flows of the entry like a paid submit: the thank-you cards are kept in the
	 * flxf_auto_ty_<md5 track> transient for the order received page, then the remaining steps run.
	 * Each (flow, tracking code) pair still runs once.
	 *
	 * @param array $c event_type 'payment', track_code, form_id, form_fields, submitted_values,
	 *                 enriched_submitted_values, page_url, and optionally verified_payment
	 *                 { amount, currency, reference, source } computed by the caller on the server.
	 */
	public static function on_external_payment( $c ) {
		if ( ! is_array( $c ) ) {
			return;
		}
		$c['event_type'] = 'payment';
		try {
			$ctx = self::build_context( $c );
			if ( ! $ctx ) {
				return;
			}
			if ( isset( $c['verified_payment'] ) && is_array( $c['verified_payment'] ) && is_numeric( $c['verified_payment']['amount'] ?? null ) ) {
				$v              = $c['verified_payment'];
				$ctx['payment'] = array(
					'amount'    => (float) $v['amount'],
					'currency'  => strtoupper( sanitize_text_field( (string) ( $v['currency'] ?? '' ) ) ),
					'source'    => sanitize_key( (string) ( $v['source'] ?? 'external' ) ),
					'reference' => sanitize_text_field( (string) ( $v['reference'] ?? '' ) ),
				);
			}
			$cards = self::run_matching( $ctx, 'early' );
			$html  = '';
			$redir = null;
			foreach ( $cards as $card ) {
				$html .= $card['html'];
				if ( ! $redir && ! empty( $card['redirect'] ) ) {
					$redir = $card['redirect'];
				}
			}
			if ( '' !== $html ) {
				set_transient( 'flxf_auto_ty_' . md5( $ctx['track'] ), array( 'html' => $html, 'redirect' => $redir ), HOUR_IN_SECONDS );
			}
			self::run_matching( $ctx, 'late' );
		} catch ( \Throwable $e ) {
			self::debug( 'on_external_payment: ' . $e->getMessage() );
		}
	}

	/* ------------------------------------------------------------------
	 * Entry points
	 * ------------------------------------------------------------------ */

	public static function on_response( $response, $args ) {
		if ( ! is_array( $response ) || empty( $response['success'] ) || ! is_array( $args ) ) {
			return $response;
		}
		try {
			$ctx = self::build_context( $args );
			if ( ! $ctx || ! self::shield_allows( $ctx ) ) {
				return $response;
			}
			$cards = self::run_matching( $ctx, 'early' );
			$html  = '';
			$redir = null;
			foreach ( $cards as $c ) {
				$html .= $c['html'];
				if ( ! $redir && ! empty( $c['redirect'] ) ) {
					$redir = $c['redirect'];
				}
			}
			if ( '' !== $html ) {
				set_transient( 'flxf_auto_ty_' . md5( $ctx['track'] ), array( 'html' => $html, 'redirect' => $redir ), HOUR_IN_SECONDS );
			} else {
				$cached = get_transient( 'flxf_auto_ty_' . md5( $ctx['track'] ) );
				if ( is_array( $cached ) && ! empty( $cached['html'] ) ) {
					$html  = (string) $cached['html'];
					$redir = $cached['redirect'] ?? null;
				}
			}
			/* Only a reply that renders the done screen (has the tracking code) gets the card. */
			if ( '' !== $html && isset( $response['track'] ) ) {
				$response['flxf_html'] = $html;
				if ( $redir ) {
					$response['flxf_redirect'] = $redir;
				}
			}
		} catch ( \Throwable $e ) {
			self::debug( 'on_response: ' . $e->getMessage() );
		}
		return $response;
	}

	public static function on_integration( $context ) {
		if ( ! is_array( $context ) ) {
			return;
		}
		try {
			$ctx = self::build_context( $context );
			if ( $ctx ) {
				self::run_matching( $ctx, 'late' );
			}
		} catch ( \Throwable $e ) {
			self::debug( 'on_integration: ' . $e->getMessage() );
		}
	}

	public static function on_shutdown() {
		foreach ( self::$runs as $key => $run ) {
			if ( ! empty( $run['finished'] ) ) {
				continue;
			}
			try {
				/* The post-reply hook never fired (e.g. an anti-spam guard vetoed integrations): run the rest now. */
				self::continue_run( $key, $run['ctx'], count( $run['flow']['actions'] ) - 1 );
			} catch ( \Throwable $e ) {
				self::debug( 'shutdown: ' . $e->getMessage() );
			}
		}
	}

	private static function shield_allows( $ctx ) {
		return (bool) apply_filters(
			'efb_shield_allow_side_effect',
			true,
			array(
				'event'         => $ctx['event'],
				'form_id'       => $ctx['form_id'],
				'tracking_code' => $ctx['track'],
				'recipients'    => array(),
				'source'        => 'flxf_automations',
				'channel'       => 'automation',
			)
		);
	}

	/* ------------------------------------------------------------------
	 * Context
	 * ------------------------------------------------------------------ */

	/**
	 * Normalises EFB's integration context / filter args.
	 * @return array|null event, track, form_id, form_title, fields[id=>label,type,value], rows, payment
	 */
	public static function build_context( array $c ) {
		$event = (string) ( $c['event_type'] ?? '' );
		if ( ! in_array( $event, array( 'form_submit', 'payment' ), true ) ) {
			return null;
		}
		$track   = sanitize_text_field( (string) ( $c['track_code'] ?? '' ) );
		$form_id = absint( $c['form_id'] ?? 0 );
		if ( '' === $track || ! $form_id ) {
			return null;
		}
		$structure = is_array( $c['form_fields'] ?? null ) ? $c['form_fields'] : array();
		$fields    = array();
		foreach ( FLXF_Automations::structure_fields( $structure ) as $f ) {
			$fields[ $f['id'] ] = array( 'label' => $f['label'], 'type' => $f['type'], 'value' => '', 'options' => $f['options'] );
		}
		$rows = ( 'payment' === $event && ! empty( $c['enriched_submitted_values'] ) && is_array( $c['enriched_submitted_values'] ) )
			? $c['enriched_submitted_values']
			: ( is_array( $c['submitted_values'] ?? null ) ? $c['submitted_values'] : array() );
		$clean_rows = array();
		foreach ( $rows as $r ) {
			$r = is_object( $r ) ? (array) $r : $r;
			if ( ! is_array( $r ) ) {
				continue;
			}
			$id   = FLXF_Automations::field_key( $r['id_'] ?? '' );
			$type = (string) ( $r['type'] ?? '' );
			if ( '' === $id || in_array( $type, array( 'w_link', 'payment' ), true ) || 'w_link' === $id || 'payment' === $id ) {
				continue;
			}
			$val = $r['value'] ?? '';
			if ( is_array( $val ) ) {
				$val = implode( ', ', array_map( array( 'FLXF_Automations', 'plain' ), array_filter( $val, 'is_scalar' ) ) );
			}
			$val = FLXF_Automations::plain( is_scalar( $val ) ? (string) $val : '' );
			if ( ! isset( $fields[ $id ] ) ) {
				$fields[ $id ] = array( 'label' => FLXF_Automations::plain( $r['name'] ?? $id ), 'type' => $type, 'value' => '', 'options' => array() );
			}
			$fields[ $id ]['value'] = '' === $fields[ $id ]['value'] ? $val : $fields[ $id ]['value'] . ', ' . $val;
			$clean_rows[]           = array( 'id' => $id, 'label' => $fields[ $id ]['label'], 'type' => $type, 'value' => $val );
		}
		$title = isset( $structure[0]['formName'] ) ? FLXF_Automations::plain( $structure[0]['formName'] ) : '';
		return array(
			'event'      => $event,
			'track'      => $track,
			'form_id'    => $form_id,
			'form_title' => '' !== $title ? $title : FLXF_Automations::form_title( $form_id ),
			'fields'     => $fields,
			'rows'       => $clean_rows,
			'payment'    => 'payment' === $event ? self::payment_info( $track, $rows, $structure ) : null,
			'page_url'   => isset( $c['page_url'] ) ? esc_url_raw( (string) $c['page_url'] ) : '',
		);
	}

	/**
	 * Verified amount for a payment submission. Never the browser's numbers: the Stripe add-on's
	 * own payment row (written server-side, settled by the gateway guard), else the payment row EFB
	 * stored server-side when the payment was created.
	 */
	private static function payment_info( $track, array $rows, array $structure ) {
		$file = defined( 'EMSFB_PLUGIN_DIRECTORY' ) ? EMSFB_PLUGIN_DIRECTORY . 'vendor/stripe/class-Emsfb-stripe-payment.php' : '';
		if ( ! class_exists( '\Emsfb\StripePayment' ) && $file && file_exists( $file ) ) {
			require_once $file;
		}
		if ( class_exists( '\Emsfb\StripePayment' ) ) {
			$row = \Emsfb\StripePayment::get_by_track( $track );
			if ( $row && in_array( (string) $row->status, array( 'completed', 'active', 'trialing' ), true ) ) {
				return array( 'amount' => (float) $row->amount, 'currency' => strtoupper( (string) $row->currency ), 'source' => 'stripe', 'reference' => (string) $row->transaction_id );
			}
		}
		foreach ( $rows as $r ) {
			$r = is_object( $r ) ? (array) $r : $r;
			if ( is_array( $r ) && ( 'payment' === ( $r['type'] ?? '' ) || 'payment' === ( $r['id_'] ?? '' ) ) && isset( $r['total'] ) && is_numeric( $r['total'] ) ) {
				$cur = (string) ( $r['paymentcurrency'] ?? ( $structure[0]['currency'] ?? '' ) );
				return array( 'amount' => (float) $r['total'], 'currency' => strtoupper( $cur ), 'source' => 'stored', 'reference' => (string) ( $r['paymentIntent'] ?? '' ) );
			}
		}
		return array( 'amount' => null, 'currency' => strtoupper( (string) ( $structure[0]['currency'] ?? '' ) ), 'source' => 'none', 'reference' => '' );
	}

	/* ------------------------------------------------------------------
	 * Matching
	 * ------------------------------------------------------------------ */

	private static function flow_matches( array $flow, array $ctx ) {
		$t = $flow['trigger'];
		if ( 'payment_completed' === $t['type'] && 'payment' !== $ctx['event'] ) {
			return false;
		}
		if ( $t['form_id'] && (int) $t['form_id'] !== (int) $ctx['form_id'] ) {
			return false;
		}
		if ( empty( $flow['conditions'] ) ) {
			return true;
		}
		$any = 'any' === $flow['match'];
		foreach ( $flow['conditions'] as $c ) {
			$ok = self::condition_ok( $c, $ctx );
			if ( $any && $ok ) {
				return true;
			}
			if ( ! $any && ! $ok ) {
				return false;
			}
		}
		return ! $any;
	}

	private static function lower( $s ) {
		return function_exists( 'mb_strtolower' ) ? mb_strtolower( (string) $s, 'UTF-8' ) : strtolower( (string) $s );
	}

	private static function number( $s ) {
		$s = str_replace( array( ' ', "\xc2\xa0" ), '', (string) $s );
		if ( preg_match( '/-?\d+(?:[.,]\d+)?/', $s, $m ) ) {
			return (float) str_replace( ',', '.', $m[0] );
		}
		return null;
	}

	private static function condition_ok( array $c, array $ctx ) {
		$v   = isset( $ctx['fields'][ $c['field'] ] ) ? (string) $ctx['fields'][ $c['field'] ]['value'] : '';
		$cmp = (string) $c['value'];
		switch ( $c['op'] ) {
			case 'filled':
				return '' !== trim( $v );
			case 'empty':
				return '' === trim( $v );
			case 'eq':
				return self::lower( trim( $v ) ) === self::lower( trim( $cmp ) );
			case 'neq':
				return self::lower( trim( $v ) ) !== self::lower( trim( $cmp ) );
			case 'contains':
				return '' !== $cmp && false !== strpos( self::lower( $v ), self::lower( $cmp ) );
			case 'gt':
			case 'lt':
				$a = self::number( $v );
				$b = self::number( $cmp );
				if ( null === $a || null === $b ) {
					return false;
				}
				return 'gt' === $c['op'] ? $a > $b : $a < $b;
		}
		return false;
	}

	/** Index of the last show_on_thankyou step, or -1. */
	private static function last_show_index( array $flow ) {
		$idx = -1;
		foreach ( $flow['actions'] as $i => $a ) {
			if ( 'show_on_thankyou' === $a['type'] ) {
				$idx = $i;
			}
		}
		return $idx;
	}

	/**
	 * Runs every matching enabled flow for this phase.
	 * @return array thank-you cards produced in this call: [ ['html','redirect'] ]
	 */
	public static function run_matching( array $ctx, $phase ) {
		$cards = array();
		foreach ( FLXF_Automations::get_flows( true ) as $flow ) {
			if ( empty( $flow['actions'] ) || ! self::flow_matches( $flow, $ctx ) ) {
				continue;
			}
			$key = $flow['id'] . '|' . $ctx['track'];
			if ( 'early' === $phase ) {
				$until = self::last_show_index( $flow );
				if ( $until < 0 ) {
					continue; // Nothing to show: everything runs after the reply.
				}
			} else {
				$until = count( $flow['actions'] ) - 1;
			}
			if ( ! isset( self::$runs[ $key ] ) ) {
				if ( ! FLXF_Automations::claim( $flow['id'], $ctx['track'] ) ) {
					continue; // Already handled by an earlier request with this tracking code.
				}
				self::$runs[ $key ] = array(
					'flow'     => $flow,
					'ctx'      => $ctx,
					'next'     => 0,
					'state'    => array(),
					'cards'    => array(),
					'finished' => false,
					'log'      => array(
						'id'         => substr( md5( $key . microtime() ), 0, 12 ),
						'time'       => time(),
						'flow_id'    => (int) $flow['id'],
						'flow_title' => $flow['title'],
						'form_id'    => (int) $ctx['form_id'],
						'form_title' => $ctx['form_title'],
						'track'      => $ctx['track'],
						'event'      => $ctx['event'],
						'status'     => 'running',
						'steps'      => array(),
					),
				);
			}
			$before = count( self::$runs[ $key ]['cards'] );
			self::continue_run( $key, $ctx, $until );
			$cards = array_merge( $cards, array_slice( self::$runs[ $key ]['cards'], $before ) );
		}
		return $cards;
	}

	private static function continue_run( $key, array $ctx, $until ) {
		$run = &self::$runs[ $key ];
		/* Later phases may carry richer data (e.g. enriched payment rows); keep what we learned. */
		$run['ctx'] = array_merge( $run['ctx'], array_filter( $ctx ) );
		$actions    = $run['flow']['actions'];
		for ( $i = $run['next']; $i <= $until && $i < count( $actions ); $i++ ) {
			$a     = $actions[ $i ];
			$start = microtime( true );
			try {
				$res = self::execute( $a, $run );
			} catch ( \Throwable $e ) {
				$res = array( 'status' => 'error', 'message' => $e->getMessage() );
			}
			$run['log']['steps'][] = array(
				'type'    => $a['type'],
				'status'  => $res['status'],
				'message' => mb_substr( wp_strip_all_tags( (string) ( $res['message'] ?? '' ) ), 0, 500 ),
				'ms'      => (int) round( ( microtime( true ) - $start ) * 1000 ),
			);
			$run['next'] = $i + 1;
		}
		if ( $run['next'] >= count( $actions ) ) {
			$run['finished']      = true;
			$statuses             = wp_list_pluck( $run['log']['steps'], 'status' );
			$bad                  = array_intersect( $statuses, array( 'error', 'partial' ) );
			$run['log']['status'] = ! $bad ? 'ok' : ( ( in_array( 'ok', $statuses, true ) || in_array( 'partial', $statuses, true ) ) ? 'partial' : 'error' );
			update_post_meta( $run['flow']['id'], '_flxf_last_run', time() );
			update_post_meta( $run['flow']['id'], '_flxf_runs', 1 + (int) get_post_meta( $run['flow']['id'], '_flxf_runs', true ) );
		}
		FLXF_Automations::store_run( $run['log'] );
		$hash = md5( $key );
		if ( $run['finished'] ) {
			if ( ! empty( $run['parked'] ) ) {
				delete_transient( 'flxf_auto_pending_' . $hash );
				wp_clear_scheduled_hook( 'flxf_automations_resume', array( $hash ) );
			}
		} elseif ( empty( $run['parked'] ) ) {
			/* Safety net: if this request dies after the reply (some hosts kill it), WP-Cron finishes the run. */
			$run['parked'] = true;
			set_transient( 'flxf_auto_pending_' . $hash, array( 'key' => $key, 'flow_id' => (int) $run['flow']['id'], 'ctx' => $run['ctx'], 'next' => $run['next'], 'state' => $run['state'], 'log' => $run['log'] ), DAY_IN_SECONDS );
			wp_schedule_single_event( time() + 5 * MINUTE_IN_SECONDS, 'flxf_automations_resume', array( $hash ) );
		}
		unset( $run );
	}

	/** WP-Cron: finish a run whose request ended before its post-reply steps. */
	public static function resume( $hash ) {
		$p = get_transient( 'flxf_auto_pending_' . preg_replace( '/[^a-f0-9]/', '', (string) $hash ) );
		if ( ! is_array( $p ) || empty( $p['key'] ) ) {
			return;
		}
		$flow = FLXF_Automations::get_flow( (int) $p['flow_id'] );
		if ( ! $flow ) {
			delete_transient( 'flxf_auto_pending_' . $hash );
			return;
		}
		self::$runs[ $p['key'] ] = array(
			'flow'     => $flow,
			'ctx'      => $p['ctx'],
			'next'     => (int) $p['next'],
			'state'    => (array) $p['state'],
			'cards'    => array(),
			'finished' => false,
			'parked'   => true,
			'log'      => $p['log'],
		);
		try {
			self::continue_run( $p['key'], $p['ctx'], count( $flow['actions'] ) - 1 );
		} catch ( \Throwable $e ) {
			self::debug( 'resume: ' . $e->getMessage() );
		}
	}

	/* ------------------------------------------------------------------
	 * Actions
	 * ------------------------------------------------------------------ */

	private static function execute( array $a, array &$run ) {
		switch ( $a['type'] ) {
			case 'create_certificate':
				return self::act_create_certificate( $a, $run );
			case 'send_certificate':
				return self::act_send_certificate( $a, $run );
			case 'show_on_thankyou':
				return self::act_show( $a, $run );
			case 'send_email':
				return self::act_email( $a, $run );
			case 'webhook':
				return self::act_webhook( $a, $run );
		}
		return array( 'status' => 'skipped', 'message' => 'Unknown step' );
	}

	private static function field_value( array $ctx, $id ) {
		return ( '' !== $id && isset( $ctx['fields'][ $id ] ) ) ? (string) $ctx['fields'][ $id ]['value'] : '';
	}

	/** Computes one template variable from its mapping. @return string|WP_Error|null (null = template default) */
	private static function var_value( array $map, array $tfield, array $ctx ) {
		$is_number = 'number' === $tfield['type'];
		switch ( $map['mode'] ) {
			case 'field':
				$v = self::field_value( $ctx, $map['field'] );
				break;
			case 'fixed':
				$v = self::fill( $map['value'], $ctx, array() );
				break;
			case 'amount_div':
				$amount = $ctx['payment']['amount'] ?? null;
				if ( null === $amount ) {
					return new WP_Error( 'flxf_amount', __( 'No verified payment amount for this submission.', 'flx-forms' ) );
				}
				$unit = (float) $map['unit_price'] > 0 ? (float) $map['unit_price'] : 1;
				/* A companion plugin that owns the prices (Flux Donate) may supply the unit price actually paid. */
				$unit = (float) apply_filters( 'flxf_automation_unit_price', $unit, $ctx );
				$unit = $unit > 0 ? $unit : 1;
				return (string) max( 1, (int) floor( ( (float) $amount / $unit ) + 1e-9 ) );
			case 'option_qty':
				$label = self::field_value( $ctx, $map['field'] );
				$n     = preg_match( '/\d+/', str_replace( array( '.', ' ' ), '', preg_replace( '/[—–-].*$/u', '', $label ) ), $m ) ? (int) $m[0] : 0;
				if ( ! $n && preg_match( '/\d+/', $label, $m ) ) {
					$n = (int) $m[0];
				}
				return (string) max( 1, $n );
			default:
				return null;
		}
		if ( $is_number && '' !== $v ) {
			$num = self::number( $v );
			$v   = null === $num ? '' : (string) ( floor( $num ) == $num ? (int) $num : $num ); // phpcs:ignore Universal.Operators.StrictComparisons
		}
		if ( 'date' === $tfield['type'] && '' !== $v && ! preg_match( '/^\d{4}-\d{2}-\d{2}$/', $v ) ) {
			$ts = 'today' === strtolower( $v ) ? current_time( 'timestamp' ) : strtotime( str_replace( '/', '-', $v ) );
			$v  = $ts ? gmdate( 'Y-m-d', $ts ) : '';
		}
		return $v;
	}

	private static function act_create_certificate( array $a, array &$run ) {
		if ( ! FLXF_Automations::certificates_available() ) {
			return array( 'status' => 'error', 'message' => __( 'Flux Certificates is not active.', 'flx-forms' ) );
		}
		$ctx      = $run['ctx'];
		$template = $a['template_id'] ? flxs_get_template( $a['template_id'] ) : null;
		if ( ! $template ) {
			return array( 'status' => 'error', 'message' => __( 'Certificate template not found.', 'flx-forms' ) );
		}
		$name = trim( self::field_value( $ctx, $a['name_field'] ) . ' ' . self::field_value( $ctx, $a['surname_field'] ) );
		$phone = self::field_value( $ctx, $a['phone_field'] );
		$email = self::field_value( $ctx, $a['email_field'] );
		$notes = array();
		$p     = flxs_upsert_person( $name, $phone, $email );
		if ( is_wp_error( $p ) && in_array( $p->get_error_code(), array( 'flxs_phone', 'flxs_email' ), true ) ) {
			/* One bad contact detail should not cost the donor the certificate. */
			$notes[] = $p->get_error_message();
			$p       = 'flxs_phone' === $p->get_error_code() ? flxs_upsert_person( $name, '', $email ) : flxs_upsert_person( $name, $phone, '' );
			if ( is_wp_error( $p ) ) {
				$notes[] = $p->get_error_message();
				$p       = flxs_upsert_person( $name, '', '' );
			}
		}
		if ( is_wp_error( $p ) ) {
			return array( 'status' => 'error', 'message' => $p->get_error_message() );
		}
		$person = $p['person'];
		$values = array( 'ad_soyad' => $person['name'] );
		foreach ( (array) $template['config']['fields'] as $tf ) {
			if ( in_array( $tf['type'], array( 'static', 'qr', 'number_auto' ), true ) || ! isset( $a['vars'][ $tf['key'] ] ) ) {
				continue;
			}
			$v = self::var_value( $a['vars'][ $tf['key'] ], $tf, $ctx );
			if ( is_wp_error( $v ) ) {
				return array( 'status' => 'error', 'message' => $tf['label'] . ': ' . $v->get_error_message() );
			}
			if ( null !== $v && '' !== $v ) {
				$values[ $tf['key'] ] = $v;
			}
		}
		/* Template defaults for anything unmapped (e.g. fidan_sayisi = 1). */
		foreach ( (array) $template['config']['fields'] as $tf ) {
			if ( ! isset( $values[ $tf['key'] ] ) && isset( $tf['default'] ) && '' !== (string) $tf['default'] && ! in_array( $tf['type'], array( 'static', 'qr', 'number_auto' ), true ) ) {
				$values[ $tf['key'] ] = (string) $tf['default'];
			}
		}
		$cert = flxs_create_certificate( (int) $a['template_id'], (int) $person['id'], $values, 0 );
		if ( is_wp_error( $cert ) ) {
			return array( 'status' => 'error', 'message' => $cert->get_error_message() );
		}
		if ( $a['event_id'] && function_exists( 'flxs_set_certificate_event' ) ) {
			$ev = flxs_set_certificate_event( (int) $cert['id'], (int) $a['event_id'] );
			if ( is_wp_error( $ev ) ) {
				$notes[] = $ev->get_error_message();
			} else {
				$cert = function_exists( 'flxs_get_certificate' ) ? flxs_get_certificate( (int) $cert['id'] ) : $cert;
			}
		}
		$run['state']['cert']   = $cert;
		$run['state']['person'] = $person;
		$run['log']['cert']     = array( 'id' => (int) $cert['id'], 'code' => (string) $cert['code'], 'number' => (string) $cert['number'] );
		$vals = array();
		foreach ( $values as $k => $v ) {
			if ( 'ad_soyad' !== $k ) {
				$vals[] = $k . '=' . $v;
			}
		}
		/* translators: 1: certificate number, 2: person name, 3: template variables */
		$msg = sprintf( __( 'Certificate %1$s for %2$s (%3$s)', 'flx-forms' ), $cert['number'], $person['name'], implode( ', ', $vals ) );
		return array( 'status' => 'ok', 'message' => $msg . ( $notes ? ' · ' . implode( ' · ', $notes ) : '' ) );
	}

	private static function act_send_certificate( array $a, array &$run ) {
		if ( empty( $run['state']['cert'] ) ) {
			return array( 'status' => 'skipped', 'message' => __( 'No certificate was created in an earlier step.', 'flx-forms' ) );
		}
		if ( ! function_exists( 'flxs_send_certificate' ) ) {
			return array( 'status' => 'error', 'message' => __( 'Flux Certificates is not active.', 'flx-forms' ) );
		}
		if ( empty( $a['channels'] ) ) {
			return array( 'status' => 'skipped', 'message' => __( 'No channel selected.', 'flx-forms' ) );
		}
		$providers = array_filter( (array) $a['providers'] );
		$res       = flxs_send_certificate( (int) $run['state']['cert']['id'], $a['channels'], $providers, 'flxf_automation' );
		$ok        = array();
		$err       = array();
		foreach ( (array) $res as $ch => $r ) {
			$label = function_exists( 'flxs_channel_label' ) ? flxs_channel_label( $ch ) : $ch;
			if ( is_wp_error( $r ) ) {
				$err[] = $label . ': ' . $r->get_error_message();
			} else {
				$ok[] = $label;
			}
		}
		if ( $ok && function_exists( 'flxs_kick_queue' ) ) {
			flxs_kick_queue();
		}
		$msg = ( $ok ? sprintf( /* translators: %s: channel list */ __( 'Queued: %s', 'flx-forms' ), implode( ', ', $ok ) ) : '' ) . ( $ok && $err ? ' · ' : '' ) . implode( ' · ', $err );
		return array( 'status' => $err ? ( $ok ? 'partial' : 'error' ) : 'ok', 'message' => $msg );
	}

	private static function act_show( array $a, array &$run ) {
		$cert = $run['state']['cert'] ?? null;
		if ( ! $cert || ! function_exists( 'flxs_certificate_url' ) ) {
			return array( 'status' => 'skipped', 'message' => __( 'No certificate was created in an earlier step.', 'flx-forms' ) );
		}
		$url     = flxs_certificate_url( $cert );
		$pdf     = function_exists( 'flxs_certificate_pdf_url' ) ? flxs_certificate_pdf_url( $cert ) : '';
		$title   = self::fill( '' !== $a['title'] ? $a['title'] : __( 'Your certificate is ready', 'flx-forms' ), $run['ctx'], $run['state'] );
		$msg     = self::fill( $a['message'], $run['ctx'], $run['state'] );
		$view    = '' !== $a['view_label'] ? $a['view_label'] : __( 'View certificate', 'flx-forms' );
		$pdfl    = '' !== $a['pdf_label'] ? $a['pdf_label'] : __( 'Download PDF', 'flx-forms' );
		$sharel  = '' !== ( $a['share_label'] ?? '' ) ? $a['share_label'] : FLXF_Automations::seed_text( 'Share' );
		$preview = ! array_key_exists( 'preview', $a ) || ! empty( $a['preview'] );
		$share   = ! array_key_exists( 'btn_share', $a ) || ! empty( $a['btn_share'] );
		$data    = $preview ? self::certificate_preview_data( $cert ) : null;

		$h  = '<div class="flxf-cert-card' . ( $data ? ' flxf-cert-card--preview' : '' ) . '" role="status"';
		if ( $data ) {
			$data['title'] = $title;
			$data['url']   = $url;
			$h .= ' data-flxf-cert="' . esc_attr( wp_json_encode( $data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES ) ) . '"';
		}
		$h .= '><div class="flxf-cert-card__head">';
		$h .= '<div class="flxf-cert-card__icon" aria-hidden="true"><svg viewBox="0 0 24 24" width="28" height="28" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="9" r="6"/><path d="M8.5 13.9 7 22l5-3 5 3-1.5-8.1"/><path d="m9.5 9 1.7 1.7L14.8 7.3"/></svg></div>';
		$h .= '<div class="flxf-cert-card__body">';
		$h .= '<p class="flxf-cert-card__title">' . esc_html( $title ) . '</p>';
		if ( '' !== trim( $msg ) ) {
			$h .= '<p class="flxf-cert-card__msg">' . nl2br( esc_html( $msg ) ) . '</p>';
		}
		$h .= '</div></div>';
		if ( $data ) {
			$w     = $data['w'] > 0 && $data['h'] > 0 ? $data['w'] : 1060;
			$hh    = $data['w'] > 0 && $data['h'] > 0 ? $data['h'] : 1500;
			/* Whole certificate in view: at most 72% of the screen height, never wider than the card. */
			$style = 'aspect-ratio:' . $w . ' / ' . $hh . ';max-width:calc(72vh * ' . round( $w / $hh, 4 ) . ')';
			$h .= '<figure class="flxf-cert-card__figure' . ( $data['image'] ? '' : ' is-loading' ) . '" style="' . esc_attr( $style ) . '">';
			if ( $data['image'] ) {
				$h .= '<img class="flxf-cert-card__img" src="' . esc_url( $data['image'] ) . '" alt="' . esc_attr( $title ) . '" decoding="async">';
			} else {
				$h .= '<canvas class="flxf-cert-card__canvas" aria-label="' . esc_attr( $title ) . '"></canvas><span class="flxf-cert-card__spinner" aria-hidden="true"></span>';
			}
			$h .= '</figure>';
		}
		if ( ( $a['btn_pdf'] && $pdf ) || $share ) {
			$h .= '<div class="flxf-cert-card__actions">';
			if ( $a['btn_pdf'] && $pdf ) {
				$h .= '<a class="flxf-cert-btn flxf-cert-btn--primary" data-flxf-pdf href="' . esc_url( $pdf ) . '" target="_blank" rel="noopener"><svg aria-hidden="true" viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 3v12"/><path d="m7 10 5 5 5-5"/><path d="M5 21h14"/></svg>' . esc_html( $pdfl ) . '</a>';
			}
			if ( $share ) {
				$h .= '<a class="flxf-cert-btn" data-flxf-share href="' . esc_url( 'https://wa.me/?text=' . rawurlencode( $title . ' ' . $url ) ) . '" target="_blank" rel="noopener"><svg aria-hidden="true" viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="18" cy="5" r="3"/><circle cx="6" cy="12" r="3"/><circle cx="18" cy="19" r="3"/><path d="m8.6 13.5 6.8 4"/><path d="m15.4 6.5-6.8 4"/></svg>' . esc_html( $sharel ) . '</a>';
			}
			$h .= '</div>';
		}
		/* translators: %s: certificate number */
		$h .= '<p class="flxf-cert-card__no">' . esc_html( sprintf( FLXF_Automations::seed_text( 'Certificate no: %s' ), $cert['number'] ) );
		if ( $a['btn_view'] ) {
			$h .= ' · <a class="flxf-cert-card__link" href="' . esc_url( $url ) . '" target="_blank" rel="noopener">' . esc_html( $view ) . '</a>';
		}
		$h .= '</p>';
		$redirect = null;
		if ( $a['redirect'] > 0 ) {
			$redirect = array( 'url' => esc_url_raw( $url ), 'delay' => (int) $a['redirect'] );
			/* translators: %d: seconds */
			$h .= '<p class="flxf-cert-card__note">' . esc_html( sprintf( FLXF_Automations::seed_text( 'Opening your certificate in %d seconds…' ), (int) $a['redirect'] ) ) . '</p>';
		}
		$h .= '</div>';

		$run['cards'][] = array( 'html' => $h, 'redirect' => $redirect );
		return array( 'status' => 'ok', 'message' => $url );
	}

	/**
	 * What the thank-you card needs to show the certificate itself: its stored image, or (for a
	 * certificate created a moment ago, which has no files yet) the data to draw it in the browser
	 * with Flux Certificates's own renderer, plus Flux Certificates's one-time upload of the missing files.
	 * Uses Flux Certificates's public functions only (v1.2.0+). Null when they are not available.
	 */
	private static function certificate_preview_data( array $cert ) {
		if ( ! function_exists( 'flxs_page_data' ) || ! function_exists( 'flxs_certificate_file_url' ) ) {
			return null;
		}
		$cert = function_exists( 'flxs_get_certificate' ) ? ( flxs_get_certificate( (int) $cert['id'] ) ?: $cert ) : $cert;
		$d    = flxs_page_data( $cert );
		if ( empty( $d['template']['config'] ) ) {
			return null;
		}
		$page = (array) ( $d['template']['config']['page'] ?? array() );
		$out  = array(
			'image'    => (string) $d['image'],
			'pdf'      => flxs_certificate_file_url( $cert, 'pdf' ),
			'name'     => (string) ( $d['vars']['ad_soyad'] ?? '' ),
			'filename' => function_exists( 'flxs_pdf_filename' ) ? flxs_pdf_filename( $cert ) : 'sertifika.pdf',
			'w'        => (float) ( $page['w'] ?? 0 ),
			'h'        => (float) ( $page['h'] ?? 0 ),
		);
		$need = function_exists( 'flxs_certificate_missing_files' ) ? flxs_certificate_missing_files( $cert ) : array();
		if ( ( '' === $out['image'] || $need ) && function_exists( 'flxs_register_renderer' ) ) {
			flxs_register_renderer();
			$wp      = wp_scripts();
			$scripts = array();
			foreach ( array( 'flxs-pdf-lib', 'flxs-fontkit', 'flxs-qrcode', 'flxs-pdfjs', 'flxs-renderer' ) as $handle ) {
				if ( empty( $wp->registered[ $handle ]->src ) ) {
					return '' === $out['image'] ? null : $out;
				}
				$r         = $wp->registered[ $handle ];
				$scripts[] = add_query_arg( 'ver', $r->ver, $r->src );
			}
			$config = null;
			$inline = (string) $wp->get_data( 'flxs-renderer', 'data' );
			if ( preg_match( '/=\s*(\{.*\})\s*;?\s*$/s', $inline, $m ) ) {
				$config = json_decode( $m[1], true );
			}
			$out['scripts'] = $scripts;
			$out['config']  = $config;
			$out['render']  = array(
				'template' => $d['template']['config'],
				'values'   => array_merge( (array) ( $cert['data']['values'] ?? array() ), array( 'ad_soyad' => $out['name'] ) ),
				'meta'     => array(
					'number'  => $cert['number'],
					'link'    => flxs_certificate_url( $cert ),
					'code'    => $cert['code'],
					'created' => gmdate( 'Y-m-d', strtotime( $cert['created_at'] . ' UTC' ) ),
				),
			);
			if ( $need && function_exists( 'flxs_public_file_token' ) ) {
				$out['upload'] = array(
					'endpoint' => rest_url( 'flx-certificates/v1/public-files/' . $cert['code'] ),
					'token'    => flxs_public_file_token( $cert ),
					'need'     => array_values( $need ),
				);
			}
		}
		return $out;
	}

	private static function act_email( array $a, array &$run ) {
		$ctx = $run['ctx'];
		$to  = array();
		if ( 'admin' === $a['to'] ) {
			$to[] = get_option( 'admin_email' );
		} elseif ( 'field' === $a['to'] ) {
			$e = sanitize_email( self::field_value( $ctx, $a['to_field'] ) );
			if ( $e && is_email( $e ) ) {
				$to[] = $e;
			}
		} else {
			$to = array_filter( array_map( 'trim', explode( ',', $a['to_custom'] ) ), 'is_email' );
		}
		if ( ! $to ) {
			return array( 'status' => 'error', 'message' => __( 'No valid recipient address.', 'flx-forms' ) );
		}
		$subject = '' !== $a['subject'] ? $a['subject'] : sprintf( /* translators: %s: form title */ __( 'New submission: %s', 'flx-forms' ), '{form_title}' );
		$subject = wp_strip_all_tags( str_replace( array( "\r", "\n" ), ' ', self::fill( $subject, $ctx, $run['state'] ) ) );
		$body    = self::fill( '' !== $a['body'] ? $a['body'] : "{all_fields}\n\n{track}", $ctx, $run['state'] );
		if ( class_exists( 'FLXF_Iletisim' ) && FLXF_Iletisim::active() ) {
			/* 2.1.0: shared design; the wrapper is the editable template "flx-forms/automation-email". */
			$sent = true;
			foreach ( $to as $addr ) {
				$sent = FLXF_Iletisim::send( 'flx-forms/automation-email', $addr, $subject, wpautop( esc_html( $body ) ), '', (string) $ctx['track'] ) && $sent;
			}
		} else {
			$sent = wp_mail( $to, $subject, $body, array( 'Content-Type: text/plain; charset=UTF-8' ) );
		}
		return $sent
			? array( 'status' => 'ok', 'message' => implode( ', ', $to ) )
			: array( 'status' => 'error', 'message' => __( 'wp_mail() could not send the e-mail.', 'flx-forms' ) );
	}

	private static function act_webhook( array $a, array &$run ) {
		$url = (string) $a['url'];
		if ( '' === $url || 0 !== stripos( $url, 'https://' ) ) {
			return array( 'status' => 'error', 'message' => __( 'Webhook URL must start with https://', 'flx-forms' ) );
		}
		/* SSRF guard: refuses private / loopback / reserved hosts. */
		if ( ! wp_http_validate_url( $url ) || ! self::public_host( $url ) ) {
			return array( 'status' => 'error', 'message' => __( 'Webhook URL points to a private or invalid address.', 'flx-forms' ) );
		}
		$ctx  = $run['ctx'];
		$cert = $run['state']['cert'] ?? null;
		$body = array(
			'event'      => 'payment' === $ctx['event'] ? 'payment_completed' : 'form_submitted',
			'flow'       => array( 'id' => (int) $run['flow']['id'], 'title' => $run['flow']['title'] ),
			'form'       => array( 'id' => (int) $ctx['form_id'], 'title' => $ctx['form_title'] ),
			'track'      => $ctx['track'],
			'time'       => gmdate( 'c' ),
			'fields'     => $ctx['rows'],
			'payment'    => $ctx['payment'],
			'certificate' => $cert ? array(
				'id'     => (int) $cert['id'],
				'code'   => (string) $cert['code'],
				'number' => (string) $cert['number'],
				'url'    => function_exists( 'flxs_certificate_url' ) ? flxs_certificate_url( $cert ) : '',
				'pdf'    => function_exists( 'flxs_certificate_pdf_url' ) ? flxs_certificate_pdf_url( $cert ) : '',
			) : null,
		);
		$json    = wp_json_encode( $body, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES );
		$headers = array( 'Content-Type' => 'application/json; charset=utf-8', 'User-Agent' => 'FluxWP-Form-Automations' );
		if ( '' !== $a['secret'] ) {
			$headers['X-FLXF-Signature'] = 'sha256=' . hash_hmac( 'sha256', $json, $a['secret'] );
		}
		$res = wp_safe_remote_post(
			$url,
			array(
				'timeout'     => 8,
				'redirection' => 0,
				'headers'     => $headers,
				'body'        => $json,
			)
		);
		if ( is_wp_error( $res ) ) {
			return array( 'status' => 'error', 'message' => $res->get_error_message() );
		}
		$code = (int) wp_remote_retrieve_response_code( $res );
		return array( 'status' => ( $code >= 200 && $code < 300 ) ? 'ok' : 'error', 'message' => 'HTTP ' . $code );
	}

	/**
	 * wp_http_validate_url() still lets the site's own host through (loopback on many setups), so
	 * resolve the host and refuse private, loopback and reserved addresses outright.
	 */
	private static function public_host( $url ) {
		if ( apply_filters( 'flxf_automations_allow_private_webhook', false, $url ) ) {
			return true;
		}
		$host = strtolower( trim( (string) wp_parse_url( $url, PHP_URL_HOST ), '[]' ) );
		if ( '' === $host || 'localhost' === $host || preg_match( '/\.(localhost|local|internal)$/', $host ) ) {
			return false;
		}
		$ips = filter_var( $host, FILTER_VALIDATE_IP ) ? array( $host ) : (array) gethostbynamel( $host );
		if ( ! $ips ) {
			return false;
		}
		foreach ( $ips as $ip ) {
			if ( ! filter_var( $ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE ) ) {
				return false;
			}
		}
		return true;
	}

	/* ------------------------------------------------------------------
	 * Tags
	 * ------------------------------------------------------------------ */

	public static function format_amount( $amount, $currency ) {
		if ( null === $amount ) {
			return '';
		}
		$dec = floor( (float) $amount ) == (float) $amount ? 0 : 2; // phpcs:ignore Universal.Operators.StrictComparisons
		$sym = array( 'TRY' => '₺', 'USD' => '$', 'EUR' => '€', 'GBP' => '£' );
		$n   = number_format_i18n( (float) $amount, $dec );
		$c   = strtoupper( (string) $currency );
		return isset( $sym[ $c ] ) ? ( 'TRY' === $c ? $n . ' ' . $sym[ $c ] : $sym[ $c ] . $n ) : trim( $n . ' ' . $c );
	}

	/** Replaces {tags} in plain text (escape the result for its output context). */
	public static function fill( $text, array $ctx, array $state ) {
		$text = (string) $text;
		if ( false === strpos( $text, '{' ) ) {
			return $text;
		}
		$lines = array();
		foreach ( $ctx['rows'] ?? array() as $r ) {
			if ( '' !== $r['value'] ) {
				$lines[] = $r['label'] . ': ' . $r['value'];
			}
		}
		$pay  = $ctx['payment'] ?? null;
		$vars = array(
			'form_title'       => $ctx['form_title'] ?? '',
			'track'            => $ctx['track'] ?? '',
			'amount'           => $pay ? self::format_amount( $pay['amount'], $pay['currency'] ) : '',
			'all_fields'       => implode( "\n", $lines ),
			'site'             => get_bloginfo( 'name' ),
			'certificate_link' => '',
			'sertifika_link'   => '',
			'certificate_pdf'  => '',
			'certificate_no'   => '',
			'sertifika_no'     => '',
		);
		if ( ! empty( $state['cert'] ) && function_exists( 'flxs_certificate_url' ) ) {
			$cert = $state['cert'];
			if ( function_exists( 'flxs_certificate_vars' ) ) {
				foreach ( (array) flxs_certificate_vars( $cert ) as $k => $v ) {
					if ( is_scalar( $v ) ) {
						$vars[ $k ] = (string) $v;
					}
				}
			}
			$vars['certificate_link'] = flxs_certificate_url( $cert );
			$vars['sertifika_link']   = $vars['certificate_link'];
			$vars['certificate_pdf']  = function_exists( 'flxs_certificate_pdf_url' ) ? flxs_certificate_pdf_url( $cert ) : '';
			$vars['certificate_no']   = (string) $cert['number'];
			$vars['sertifika_no']     = (string) $cert['number'];
		}
		$text = preg_replace_callback(
			'/\{field:([A-Za-z0-9_\-]+)\}/',
			function ( $m ) use ( $ctx ) {
				return isset( $ctx['fields'][ $m[1] ] ) ? (string) $ctx['fields'][ $m[1] ]['value'] : '';
			},
			$text
		);
		return preg_replace_callback(
			'/\{([a-z0-9_]+)\}/',
			function ( $m ) use ( $vars ) {
				return array_key_exists( $m[1], $vars ) ? $vars[ $m[1] ] : $m[0];
			},
			$text
		);
	}

	private static function debug( $msg ) {
		if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
			error_log( '[FLXF automations] ' . $msg ); // phpcs:ignore WordPress.PHP.DevelopmentFunctions
		}
	}
}
