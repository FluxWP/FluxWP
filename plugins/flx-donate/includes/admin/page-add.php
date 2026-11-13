<?php
/**
 * Flux Donate: Donations › Add donation. Records a gift received outside the payment provider
 * (bank transfer, cash, other) with its donor, type, option, items and proxy names.
 *
 * @package FLX_Bagis
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class FLXB_Page_Add {

	/** 2.29.0: created_via of orders made on this page. */
	const VIA = 'flxb-manual';

	public static function methods() {
		return array(
			'bank_transfer' => __( 'Bank transfer', 'flx-donate' ),
			'cash'          => __( 'Cash', 'flx-donate' ),
			'other'         => __( 'Other', 'flx-donate' ),
		);
	}

	/** Bank accounts from `flxb_bank_accounts`: id => label (accepts strings or arrays with label/iban/name). */
	private static function accounts() {
		$out = array();
		foreach ( flxb_bank_accounts() as $k => $a ) {
			if ( is_array( $a ) ) {
				$label = trim( implode( ' · ', array_filter( array( (string) ( $a['label'] ?? ( $a['name'] ?? ( $a['bank'] ?? '' ) ) ), (string) ( $a['currency'] ?? '' ), (string) ( $a['iban'] ?? '' ) ), 'strlen' ) ) );
				$id    = (string) ( $a['id'] ?? ( $a['iban'] ?? $k ) );
			} else {
				$label = (string) $a;
				$id    = is_string( $k ) ? $k : (string) $a;
			}
			if ( '' !== $label ) {
				$out[ sanitize_text_field( $id ) ] = sanitize_text_field( $label );
			}
		}
		return $out;
	}

	/** 2.29.0: what the form needs to know about each type (options, certificate, per-person rows). */
	private static function type_data( array $types ) {
		$units = flxb_units();
		$data  = array();
		foreach ( $types as $t ) {
			$opts = array();
			foreach ( $t['options'] as $o ) {
				$u      = (string) $o['unit'];
				$opts[] = array(
					'key'   => $o['key'],
					'label' => $o['label'],
					'price' => (float) $o['price'],
					'per'   => (int) $o['units_per'],
					'unit'  => isset( $units[ $u ] ) ? array( (string) $units[ $u ][0], (string) $units[ $u ][1] ) : null,
				);
			}
			$data[ (int) $t['id'] ] = array(
				'title'    => $t['title'],
				'currency' => $t['currency'],
				'options'  => $opts,
				'free'     => (bool) $t['allow_free'],
				'proxy'    => FLXB_Types::requires_proxy( $t ),
				'cert'     => flxb_wc_active() && class_exists( 'FLXB_WC_Cart' ) && FLXB_WC_Cart::type_has_certificate( $t ),
			);
		}
		return $data;
	}

	/** Radio buttons drawn as a segmented control. */
	private static function seg( $name, array $choices, $on ) {
		$h = '<div class="flxb-seg flxb-seg--radio" role="radiogroup">';
		foreach ( $choices as $k => $l ) {
			$h .= '<label><input type="radio" name="' . esc_attr( $name ) . '" value="' . esc_attr( $k ) . '"' . checked( $on, $k, false ) . '><span>' . esc_html( $l ) . '</span></label>';
		}
		return $h . '</div>';
	}

	/** One person row (2.29.0): name, WhatsApp, e-mail. $i = 0 is the first certificate (takes the remaining units). */
	private static function person_row( $i ) {
		$h  = '<div class="flxb-ppl-row" data-flxb-person>';
		$h .= '<span class="flxb-ppl-row__n" data-flxb-person-n>' . (int) ( $i + 1 ) . '</span>';
		$h .= '<div class="flxb-ppl-row__f"><input type="text" name="people[' . (int) $i . '][name]" maxlength="80" placeholder="' . esc_attr__( 'Full name', 'flx-donate' ) . '" aria-label="' . esc_attr__( 'Full name', 'flx-donate' ) . '"></div>';
		$h .= '<div class="flxb-ppl-row__f">' . ( function_exists( 'flx_phone_field' ) ? flx_phone_field( array( 'name' => 'people[' . (int) $i . '][phone]', 'label' => __( 'WhatsApp', 'flx-donate' ), 'placeholder' => __( 'WhatsApp', 'flx-donate' ), 'message' => false ) ) : '<input type="tel" name="people[' . (int) $i . '][phone]" maxlength="32" placeholder="' . esc_attr__( 'WhatsApp', 'flx-donate' ) . '">' ) . '</div>';
		$h .= '<div class="flxb-ppl-row__f"><input type="email" name="people[' . (int) $i . '][email]" maxlength="190" placeholder="' . esc_attr__( 'E-mail', 'flx-donate' ) . '" aria-label="' . esc_attr__( 'E-mail', 'flx-donate' ) . '"></div>';
		$h .= '<span class="flxb-ppl-row__end"><span class="flxb-tag" data-flxb-person-units></span><button type="button" class="flxb-ppl-row__x" data-flxb-person-remove aria-label="' . esc_attr__( 'Remove', 'flx-donate' ) . '">×</button></span>';
		return $h . '</div>';
	}

	public static function render() {
		if ( FLXB_Refs::staff_create() ) {
			self::request_form();
			return;
		}
		if ( ! flxb_can_manage() ) {
			FLXB_Admin::deny();
		}
		$types = FLXB_Types::all();
		$data  = self::type_data( $types );
		$proxy = flxb_proxy_text();
		$accs  = self::accounts();
		$money = flxb_default_currency();
		$i18n  = array(
			/* translators: 1: quantity, 2: unit price */
			'times'    => __( '%1$s × %2$s', 'flx-donate' ),
			'free'     => __( 'Free amount', 'flx-donate' ),
			'donor'    => __( 'Donor', 'flx-donate' ),
			'toDonor'  => __( 'to the donor', 'flx-donate' ),
			'whatsapp' => __( 'WhatsApp', 'flx-donate' ),
			'email'    => __( 'e-mail', 'flx-donate' ),
			'both'     => __( 'WhatsApp + e-mail', 'flx-donate' ),
			'piece'    => array( __( 'piece', 'flx-donate' ), __( 'pieces', 'flx-donate' ) ),
			/* translators: 1: units given, 2: units in total */
			'used'     => __( '%1$s / %2$s', 'flx-donate' ),
			'certs'    => __( 'Certificates', 'flx-donate' ),
			'names'    => __( 'On behalf of', 'flx-donate' ),
		);
		FLXB_Admin::header( __( 'Add donation', 'flx-donate' ), __( 'Record a gift paid or promised by bank transfer, in cash or another way.', 'flx-donate' ), array(), array( __( 'Donations', 'flx-donate' ), FLXB_Admin_Url::page( 'flxb-donations' ) ) );
		echo '<form class="flxb-form flxb-add2" method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '" data-flxb-add2="' . esc_attr( wp_json_encode( $data ) ) . '" data-i18n="' . esc_attr( wp_json_encode( $i18n ) ) . '" data-locale="' . esc_attr( str_replace( '_', '-', determine_locale() ) ) . '"><input type="hidden" name="action" value="flxb_add_donation">';
		wp_nonce_field( 'flxb_add_donation' );
		echo '<div class="flxb-add2__grid"><div class="flxb-add2__main">';

		/* Gift + donor */
		echo '<section class="flxb-card"><div class="flxb-add2__head"><h2 class="flxb-card__title">' . esc_html__( 'Donation', 'flx-donate' ) . '</h2></div>';
		echo '<div class="flxb-add2__gift"><div class="flxb-field"><label for="flxb-a-pick">' . esc_html__( 'Donation type', 'flx-donate' ) . '</label><select id="flxb-a-pick" name="pick">';
		foreach ( $types as $t ) {
			$d = $data[ (int) $t['id'] ];
			foreach ( $d['options'] as $o ) {
				$label = count( $d['options'] ) > 1 || $d['free'] ? $t['title'] . ' · ' . $o['label'] : $t['title'];
				echo '<option value="' . (int) $t['id'] . '|' . esc_attr( $o['key'] ) . '">' . esc_html( $label . ( $o['price'] > 0 ? ' · ' . wp_strip_all_tags( flxb_money( $o['price'], $t['currency'] ) ) : '' ) ) . '</option>';
			}
			if ( $d['free'] || ! $d['options'] ) {
				echo '<option value="' . (int) $t['id'] . '|">' . esc_html( $t['title'] . ' · ' . __( 'Free amount', 'flx-donate' ) ) . '</option>';
			}
		}
		echo '<option value="0|">' . esc_html__( 'No donation type (free amount)', 'flx-donate' ) . '</option></select>';
		echo '<button type="button" class="flxb-linkbtn flxb-add2__more" data-flxb-price-toggle>' . esc_html__( 'Different unit price', 'flx-donate' ) . '</button></div>';
		echo '<div class="flxb-field" data-flxb-qty><label for="flxb-a-qty">' . esc_html__( 'Quantity', 'flx-donate' ) . '</label><div class="flxb-stepper"><button type="button" class="flxb-stepper__btn" data-flxb-step="-1" aria-label="' . esc_attr__( 'Decrease', 'flx-donate' ) . '">−</button><input type="number" id="flxb-a-qty" name="quantity" min="1" max="1000" step="1" value="1" inputmode="numeric"><button type="button" class="flxb-stepper__btn" data-flxb-step="1" aria-label="' . esc_attr__( 'Increase', 'flx-donate' ) . '">+</button></div></div></div>';
		/* A different unit price (needs a reason) or a free amount: only when needed. */
		echo '<div class="flxb-add2__price" data-flxb-price hidden><div class="flxb-field"><label for="flxb-a-unit">' . esc_html__( 'Unit price', 'flx-donate' ) . '</label><input type="number" id="flxb-a-unit" name="unit_price" min="0" step="0.01"></div><div class="flxb-field"><label for="flxb-a-reason">' . esc_html__( 'Reason for a different price', 'flx-donate' ) . '</label><input type="text" id="flxb-a-reason" name="price_reason" maxlength="190"></div></div>';
		echo '<div class="flxb-add2__price" data-flxb-free hidden><div class="flxb-field"><label for="flxb-a-amount">' . esc_html__( 'Amount', 'flx-donate' ) . '</label><input type="number" id="flxb-a-amount" name="amount" min="0" step="0.01"></div><div class="flxb-field"><label for="flxb-a-cur">' . esc_html__( 'Currency', 'flx-donate' ) . '</label><select id="flxb-a-cur" name="currency">';
		foreach ( array_unique( array_merge( array_keys( flxb_currencies() ), array( 'TRY', 'EUR', 'USD', 'GBP' ) ) ) as $c ) {
			echo '<option value="' . esc_attr( $c ) . '"' . selected( $money, $c, false ) . '>' . esc_html( $c . ' (' . flxb_currency_symbol( $c ) . ')' ) . '</option>';
		}
		echo '</select></div></div>';
		echo '<div class="flxb-field flxb-add2__proxy" data-flxb-proxy hidden><label for="flxb-a-ben">' . esc_html__( 'On behalf of', 'flx-donate' ) . '</label><textarea id="flxb-a-ben" name="beneficiaries" rows="3"></textarea><p class="flxb-help">' . esc_html__( 'One name per line, one per unit. Units without a name are given on behalf of the donor.', 'flx-donate' ) . '</p>';
		if ( $proxy ) {
			/* translators: %s: version number */
			echo '<label class="flxb-check"><input type="checkbox" name="proxy_consent" value="1"> ' . esc_html( sprintf( __( 'The donor accepted the proxy (vekâlet) text, version %s', 'flx-donate' ), number_format_i18n( (int) $proxy['version'] ) ) ) . '</label>';
		}
		echo '</div>';

		/* Donor (2.12.0: ?donor=ID prefills from the donor card) */
		$pre = absint( FLXB_Admin::q( 'donor', '0' ) );
		$pre = $pre ? FLXB_Donors::get( $pre ) : null;
		$pre = is_array( $pre ) ? $pre : array( 'name' => '', 'phone' => '', 'email' => '', 'consent_kvkk' => 0, 'consent_email' => 0, 'consent_sms' => 0 );
		echo '<hr class="flxb-add2__hr"><div class="flxb-add2__head"><h2 class="flxb-card__title">' . esc_html__( 'Donor', 'flx-donate' ) . '</h2><span class="flxb-tag">' . esc_html__( 'Matched on phone or e-mail', 'flx-donate' ) . '</span></div>';
		echo '<div class="flxb-field"><label for="flxb-a-name">' . esc_html__( 'Full name', 'flx-donate' ) . '</label><input type="text" id="flxb-a-name" name="donor_name" maxlength="190" value="' . esc_attr( (string) $pre['name'] ) . '"></div>';
		echo '<div class="flxb-fields-2"><div class="flxb-field"><label for="flxb-a-phone">' . esc_html__( 'Phone', 'flx-donate' ) . '</label>' . ( function_exists( 'flx_phone_field' ) ? flx_phone_field( array( 'name' => 'donor_phone', 'id' => 'flxb-a-phone', 'value' => (string) $pre['phone'] ) ) : '<input type="tel" id="flxb-a-phone" name="donor_phone" maxlength="32" value="' . esc_attr( (string) $pre['phone'] ) . '">' ) . '</div>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- flx_phone_field() escapes.
		echo '<div class="flxb-field"><label for="flxb-a-mail">' . esc_html__( 'E-mail', 'flx-donate' ) . '</label><input type="email" id="flxb-a-mail" name="donor_email" maxlength="190" value="' . esc_attr( (string) $pre['email'] ) . '"></div></div>';
		echo '<div class="flxb-chips">';
		foreach ( array( 'consent_kvkk' => __( 'Privacy notice (KVKK) accepted', 'flx-donate' ), 'consent_email' => __( 'E-mail allowed', 'flx-donate' ), 'consent_sms' => __( 'SMS allowed', 'flx-donate' ) ) as $k => $l ) {
			echo '<label class="flxb-chipcheck"><input type="checkbox" name="' . esc_attr( $k ) . '" value="1"' . checked( (int) $pre[ $k ], 1, false ) . '><span>' . esc_html( $l ) . '</span></label>';
		}
		echo '</div></section>';

		/* 2.29.0: on behalf of other people, one certificate each (same as "Add another person" on the donation form). */
		echo '<section class="flxb-card" data-flxb-people-card><div class="flxb-add2__head"><h2 class="flxb-card__title">' . esc_html__( 'On behalf of', 'flx-donate' ) . '</h2><span class="flxb-tag" data-flxb-people-count></span></div>';
		echo '<label class="flxb-check"><input type="checkbox" name="for_other" value="1" data-flxb-for-other> <span data-flxb-for-other-label>' . esc_html__( 'This donation is on behalf of other people (one certificate each)', 'flx-donate' ) . '</span></label>';
		echo '<div class="flxb-people" data-flxb-people hidden><div data-flxb-people-rows>' . self::person_row( 0 ) . '</div>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- built and escaped above.
		echo '<template data-flxb-person-tpl>' . self::person_row( 999 ) . '</template>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- built and escaped above.
		echo '<div class="flxb-people__foot"><button type="button" class="flxb-btn flxb-btn--sm" data-flxb-person-add>' . esc_html__( '+ Add person', 'flx-donate' ) . '</button><p class="flxb-help">' . esc_html__( 'Each person gets one unit and their own certificate; the remaining units go to the first person. A certificate without WhatsApp or e-mail goes to the donor.', 'flx-donate' ) . '</p></div></div>';
		echo '</section></div>';

		/* Summary + payment */
		echo '<aside class="flxb-add2__side"><section class="flxb-card">';
		echo '<div class="flxb-add2__sum" aria-live="polite"><span class="flxb-add2__sumlbl">' . esc_html__( 'Total', 'flx-donate' ) . '</span><div class="flxb-add2__total" data-flxb-total>—</div><dl class="flxb-add2__line"><dt data-flxb-sum-type></dt><dd data-flxb-sum-calc></dd></dl><ul class="flxb-add2__certs" data-flxb-sum-people hidden></ul></div>';
		echo '<div class="flxb-field"><span class="flxb-label">' . esc_html__( 'Payment method', 'flx-donate' ) . '</span>' . self::seg( 'method', self::methods(), 'bank_transfer' ) . '</div>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped in seg().
		if ( $accs ) {
			echo '<div class="flxb-field" data-flxb-account><label for="flxb-a-acc">' . esc_html__( 'Account', 'flx-donate' ) . '</label><select id="flxb-a-acc" name="account"><option value="">—</option>';
			foreach ( $accs as $k => $l ) {
				echo '<option value="' . esc_attr( $k ) . '">' . esc_html( $l ) . '</option>';
			}
			echo '</select></div>';
		}
		/* 2.14.0: a gift can be recorded before the money arrives (pending) and marked paid later. */
		echo '<div class="flxb-field"><span class="flxb-label">' . esc_html__( 'Status', 'flx-donate' ) . '</span>' . self::seg( 'status', array( 'completed' => __( 'Paid', 'flx-donate' ), 'pending' => __( 'Awaiting payment', 'flx-donate' ) ), 'completed' ) . '</div>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped in seg().
		echo '<div class="flxb-field"><label for="flxb-a-date">' . esc_html__( 'Date', 'flx-donate' ) . '</label><input type="datetime-local" id="flxb-a-date" name="date" value="' . esc_attr( wp_date( 'Y-m-d\TH:i' ) ) . '"></div>';
		echo '<div class="flxb-field"><label for="flxb-a-ref">' . esc_html__( 'Receipt or transaction no.', 'flx-donate' ) . '</label><input type="text" id="flxb-a-ref" name="reference" maxlength="120" placeholder="' . esc_attr__( 'Optional', 'flx-donate' ) . '"></div>';
		$refs = FLXB_Refs::users();
		echo '<details class="flxb-add2__extra"><summary>' . esc_html( $refs ? __( '+ Add referral and note', 'flx-donate' ) : __( '+ Add note', 'flx-donate' ) ) . '</summary>';
		if ( $refs ) {
			echo '<div class="flxb-field"><label for="flxb-a-refuser">' . esc_html__( 'Referral', 'flx-donate' ) . '</label><select id="flxb-a-refuser" name="ref_user_id"><option value="0">' . esc_html__( 'None', 'flx-donate' ) . '</option>';
			foreach ( $refs as $uid => $label ) {
				echo '<option value="' . (int) $uid . '">' . esc_html( $label ) . '</option>';
			}
			echo '</select><p class="flxb-help">' . esc_html__( 'The staff member who brought this donation. They see it under their referrals.', 'flx-donate' ) . '</p></div>';
		}
		echo '<div class="flxb-field"><label for="flxb-a-note">' . esc_html__( 'Notes', 'flx-donate' ) . '</label><textarea id="flxb-a-note" name="note" rows="3"></textarea></div></details>';
		echo '<button type="submit" class="flxb-btn flxb-btn--primary flxb-add2__save">' . esc_html__( 'Save donation', 'flx-donate' ) . '</button>';
		echo '</section></aside></div></form>';
		FLXB_Admin::footer();
	}

	/** People rows from the form: [ { name, phone, email } ] with a name, in order. */
	private static function people_in( $raw ) {
		$out = array();
		foreach ( array_values( is_array( $raw ) ? $raw : array() ) as $r ) {
			$name = is_array( $r ) ? mb_substr( trim( sanitize_text_field( (string) ( $r['name'] ?? '' ) ) ), 0, 80 ) : '';
			if ( '' === $name ) {
				continue;
			}
			$phone = sanitize_text_field( (string) ( $r['phone'] ?? '' ) );
			$out[] = array(
				'name'  => $name,
				'phone' => function_exists( 'flx_phone_normalize' ) && '' !== $phone ? ( flx_phone_normalize( $phone ) ?: $phone ) : $phone,
				'email' => sanitize_email( (string) ( $r['email'] ?? '' ) ),
			);
		}
		return $out;
	}

	public static function save() {
		if ( ! flxb_can_manage() ) {
			FLXB_Admin::deny();
		}
		check_admin_referer( 'flxb_add_donation' );
		$p    = wp_unslash( $_POST ); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- sanitised field by field below.
		$fail = function ( $msg ) {
			FLXB_Admin::redirect( 'flxb-add', array( 'flxb_msg' => 'error', 'flxb_err' => $msg ) );
		};
		$num  = function ( $v ) {
			return round( (float) str_replace( ',', '.', (string) $v ), 2 );
		};
		/* 2.29.0: type and option come as one "type|option" choice. */
		if ( isset( $p['pick'] ) ) {
			$pick              = explode( '|', (string) $p['pick'], 2 );
			$p['type_id']      = $pick[0];
			$p['option_key']   = $pick[1] ?? '';
		}
		$type     = ! empty( $p['type_id'] ) ? FLXB_Types::get( absint( $p['type_id'] ) ) : null;
		$key      = sanitize_key( (string) ( $p['option_key'] ?? '' ) );
		$opt      = $type && '' !== $key ? FLXB_Types::option( $type, $key ) : null;
		$qty      = max( 1, min( 1000, absint( $p['quantity'] ?? 1 ) ) );
		$currency = strtoupper( sanitize_text_field( (string) ( $p['currency'] ?? '' ) ) );
		$currency = preg_match( '/^[A-Z]{3}$/', $currency ) ? $currency : ( $type ? $type['currency'] : flxb_default_currency() );
		$method   = sanitize_key( (string) ( $p['method'] ?? '' ) );
		$method   = isset( self::methods()[ $method ] ) ? $method : 'other';
		$meta     = array( 'created_by' => get_current_user_id(), 'method' => $method );
		if ( '' !== $key && ! $opt ) {
			$fail( __( 'Please choose an option of the selected donation type.', 'flx-donate' ) );
		}
		$given = null;
		if ( $opt ) {
			$currency = (string) $type['currency']; // option prices are in the type's currency
			$given    = '' !== trim( (string) ( $p['unit_price'] ?? '' ) ) ? $num( $p['unit_price'] ) : null;
			$unit     = (float) $opt['price'];
			if ( null !== $given && abs( $given - $unit ) >= 0.005 ) {
				$reason = sanitize_text_field( (string) ( $p['price_reason'] ?? '' ) );
				if ( '' === $reason ) {
					$fail( __( 'Please give a reason for the different unit price.', 'flx-donate' ) );
				}
				$meta['price_override'] = array( 'type_price' => $unit, 'price' => $given, 'reason' => $reason );
				$unit                   = $given;
			}
			if ( $unit <= 0 ) {
				$fail( __( 'This option has no price yet. Enter the unit price and a reason.', 'flx-donate' ) );
			}
			$amount = round( $unit * $qty, 2 );
			$lines  = array( array( 'key' => $opt['key'], 'label' => $opt['label'], 'units' => $qty, 'unit_price' => $unit, 'amount' => $amount, 'unit' => $opt['unit'], 'units_per' => (int) $opt['units_per'] ) );
		} else {
			$amount = $num( $p['amount'] ?? 0 );
			$unit   = $amount;
			$qty    = 1;
			$lines  = array( array( 'key' => '', 'label' => __( 'Free amount', 'flx-donate' ), 'units' => 1, 'unit_price' => $amount, 'amount' => $amount, 'unit' => '', 'units_per' => 1 ) );
		}
		if ( $amount <= 0 ) {
			$fail( __( 'Please enter an amount.', 'flx-donate' ) );
		}
		$is_proxy = $type && FLXB_Types::requires_proxy( $type );
		$names    = flxb_split_names( (string) ( $p['beneficiaries'] ?? '' ) );
		if ( $is_proxy ) {
			$text = flxb_proxy_text();
			if ( ! $text || empty( $p['proxy_consent'] ) ) {
				$fail( __( 'This donation type needs the donor\'s proxy (vekâlet) consent.', 'flx-donate' ) );
			}
			$meta['proxy_consent'] = array( 'version' => (int) $text['version'], 'accepted_at' => flxb_now(), 'source' => 'manual' );
		}
		/* 2.29.0: other people: the first takes the remaining units, every other one unit with their own certificate. */
		$people = ! $is_proxy && ! empty( $p['for_other'] ) ? array_slice( self::people_in( $p['people'] ?? array() ), 0, $qty ) : array();
		foreach ( array( 'account', 'reference' ) as $k ) {
			$v = sanitize_text_field( (string) ( $p[ $k ] ?? '' ) );
			if ( '' !== $v ) {
				$meta[ $k ] = $v;
			}
		}
		if ( 'bank_transfer' !== $method ) {
			unset( $meta['account'] );
		}
		$note = sanitize_textarea_field( (string) ( $p['note'] ?? '' ) );
		if ( '' !== $note ) {
			$meta['note'] = $note;
		}
		$date  = sanitize_text_field( (string) ( $p['date'] ?? '' ) );
		$local = '';
		try {
			$dt      = preg_match( '/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}$/', $date ) ? new DateTimeImmutable( str_replace( 'T', ' ', $date ) . ':00', wp_timezone() ) : null;
			$local   = $dt ? $dt->format( 'Y-m-d H:i:s' ) : '';
			$created = $dt ? $dt->setTimezone( new DateTimeZone( 'UTC' ) )->format( 'Y-m-d H:i:s' ) : flxb_now();
		} catch ( \Exception $e ) {
			$created = flxb_now();
		}
		$dphone = sanitize_text_field( (string) ( $p['donor_phone'] ?? '' ) );
		$donor  = array(
			'name'            => sanitize_text_field( (string) ( $p['donor_name'] ?? '' ) ),
			'phone'           => function_exists( 'flx_phone_normalize' ) && '' !== $dphone ? ( flx_phone_normalize( $dphone ) ?: $dphone ) : $dphone,
			'email'           => sanitize_email( (string) ( $p['donor_email'] ?? '' ) ),
			'consent_kvkk'    => empty( $p['consent_kvkk'] ) ? 0 : 1,
			'consent_email'   => empty( $p['consent_email'] ) ? 0 : 1,
			'consent_sms'     => empty( $p['consent_sms'] ) ? 0 : 1,
			'allow_anonymous' => true,
		);
		$paid  = 'completed' === ( $p['status'] ?? '' );
		$refid = FLXB_Refs::valid( absint( $p['ref_user_id'] ?? 0 ) );

		/* 2.29.0: with a donation type the gift is a WooCommerce order (as on the site and in the Excel import), so
		   certificates, WhatsApp / e-mail delivery and the order screens work the same way. */
		if ( $type && flxb_wc_active() ) {
			$id = self::save_order( $type, $opt, $qty, $amount, $currency, $method, $paid, $people, $names, $meta, $donor, $refid, $local );
			if ( is_wp_error( $id ) ) {
				$fail( $id->get_error_message() );
			}
			FLXB_Admin::redirect( 'flxb-donations', array( 'donation' => $id, 'flxb_msg' => 'saved' ) );
		}

		if ( $people ) {
			$first = $people[0];
			$rest  = array_slice( $people, 1 );
			$names = array_merge( array_fill( 0, max( 1, $qty - count( $rest ) ), $first['name'] ), wp_list_pluck( $rest, 'name' ) );
			$meta['beneficiary_contacts'] = $people;
		}
		$id = FLXB_Donations::record(
			array(
				'uniq'               => 'manual:' . wp_generate_uuid4(),
				'provider'           => $method,
				'provider_ref'       => (string) ( $meta['reference'] ?? '' ),
				'amount'             => $amount,
				'currency'           => $currency,
				'type'               => 'one_time',
				'status'             => $paid ? 'completed' : 'pending',
				'donor_id'           => FLXB_Donors::upsert( $donor ),
				'type_id'            => $type ? (int) $type['id'] : 0,
				'option_key'         => $opt ? $opt['key'] : '',
				'option_label'       => $opt ? $opt['label'] : '',
				'quantity'           => $qty,
				'unit_price'         => $unit,
				'source'             => 'manual',
				'ref_user_id'        => $refid,
				'created_at'         => $created,
				'meta'               => $meta,
				'lines'              => $lines,
				'beneficiaries'      => $names,
			)
		);
		if ( ! $id ) {
			$fail( __( 'Something went wrong. Please check the form and try again.', 'flx-donate' ) );
		}
		FLXB_Admin::redirect( 'flxb-donations', array( 'donation' => $id, 'flxb_msg' => 'saved' ) );
	}

	/**
	 * 2.29.0: write the gift as a WooCommerce order (created_via "flxb-manual") through flxb_create_order().
	 *
	 * @return int|WP_Error First donation id.
	 */
	private static function save_order( array $type, $opt, $qty, $amount, $currency, $method, $paid, array $people, array $names, array $meta, array $donor, $refid, $local ) {
		$line = array(
			'type_id' => (int) $type['id'],
			'option'  => $opt ? (string) $opt['key'] : '',
			'units'   => $opt ? $qty : 1,
			'amount'  => $amount,
		);
		if ( class_exists( 'FLXB_WC_Cart' ) && FLXB_WC_Cart::type_has_certificate( $type ) ) {
			$line['cert'] = 'online';
		}
		if ( ! empty( $meta['proxy_consent'] ) ) {
			$line['proxy_consent'] = $meta['proxy_consent'];
		}
		if ( $names ) {
			$line['beneficiaries'] = $names;
			$line['name']          = $names[0];
		}
		if ( $people ) {
			$first             = $people[0];
			$rest              = array_slice( $people, 1, max( 0, $qty - 1 ) );
			$line['for_other'] = true;
			$line['name']      = $first['name'];
			$line['ben_name']  = $first['name'];
			$line['ben_phone'] = $first['phone'];
			$line['ben_email'] = $first['email'];
			if ( $rest && FLXB_Types::tracks_items( $type ) ) {
				/* Tracked types keep one name per unit (each unit is an item with its own code). */
				$line['beneficiaries'] = array_merge( array_fill( 0, max( 1, $qty - count( $rest ) ), $first['name'] ), wp_list_pluck( $rest, 'name' ) );
			} elseif ( $rest ) {
				$line['people'] = $rest;
			}
		}
		$parts = preg_split( '/\s+/u', trim( (string) $donor['name'] ), 2 );
		$order = flxb_create_order(
			array(
				'lines'          => array( $line ),
				'billing'        => array( 'first_name' => $parts[0] ?? '', 'last_name' => $parts[1] ?? '', 'phone' => $donor['phone'], 'email' => $donor['email'] ),
				'payment_method' => 'bank_transfer' === $method ? FLXB_WC_Havale::ID : self::VIA,
				'payment_title'  => 'bank_transfer' === $method ? '' : self::methods()[ $method ],
				'status'         => $paid ? 'completed' : ( 'bank_transfer' === $method ? 'on-hold' : 'pending' ),
				'created_via'    => self::VIA,
				'ref_user_id'    => $refid,
				'note'           => (string) ( $meta['note'] ?? '' ),
				'currency'       => $currency,
				'created_at'     => $local,
			)
		);
		if ( is_wp_error( $order ) ) {
			return $order;
		}
		$ids = array_values( array_filter( flxb_order_donation_ids( $order ) ) );
		if ( ! $ids ) {
			return new WP_Error( 'flxb_manual', __( 'Something went wrong. Please check the form and try again.', 'flx-donate' ) );
		}
		unset( $meta['proxy_consent'] );
		foreach ( $ids as $did ) {
			FLXB_Donations::update_meta( (int) $did, $meta );
		}
		FLXB_Donors::upsert( $donor ); // consents (KVKK, e-mail, SMS) on the donor card
		return (int) $ids[0];
	}

	/* ---------- 2.3.0: staff donation request (flxb_create_ref) ---------- */

	private static function request_form() {
		$done = absint( FLXB_Admin::q( 'flxb_req', '0' ) );
		FLXB_Admin::header( __( 'New donation request', 'flx-donate' ), __( 'Open a donation on behalf of a donor. The donor gets a payment link and you are the referral.', 'flx-donate' ), array(), current_user_can( FLXB_Refs::CAP_OWN ) ? array( __( 'My referrals', 'flx-donate' ), FLXB_Admin_Url::page( 'flxb-donations' ) ) : array() );
		if ( $done ) {
			self::request_result( $done );
		}
		$data  = array();
		$types = array();
		foreach ( FLXB_Types::all() as $t ) {
			if ( 'open' !== flxb_type_state( $t ) ) {
				continue;
			}
			$opts = array();
			foreach ( $t['options'] as $o ) {
				if ( ! empty( $o['active'] ) && (float) $o['price'] > 0 ) {
					$opts[] = array( 'key' => $o['key'], 'label' => $o['label'], 'price' => (float) $o['price'], 'active' => true );
				}
			}
			if ( $opts || $t['allow_free'] ) {
				$types[]                = $t;
				$data[ (int) $t['id'] ] = array( 'currency' => $t['currency'], 'options' => $opts, 'free' => ! $opts, 'proxy' => false ); // priced options first; a free amount only for types without them
			}
		}
		if ( ! $types ) {
			echo '<div class="flxb-panel"><p class="flxb-empty">' . esc_html__( 'There is no open donation type yet.', 'flx-donate' ) . '</p></div>';
			FLXB_Admin::footer();
			return;
		}
		echo '<form class="flxb-form flxb-addform" method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '" data-flxb-add="' . esc_attr( wp_json_encode( $data ) ) . '"><input type="hidden" name="action" value="flxb_ref_request">';
		wp_nonce_field( 'flxb_ref_request' );
		echo '<div class="flxb-grid flxb-grid--2-1"><div class="flxb-stack">';
		echo '<section class="flxb-card"><div class="flxb-card__head"><h2 class="flxb-card__title">' . esc_html__( 'Donation', 'flx-donate' ) . '</h2></div><div class="flxb-form">';
		echo '<div class="flxb-fields-2"><div class="flxb-field"><label for="flxb-a-type">' . esc_html__( 'Donation type', 'flx-donate' ) . '</label><select id="flxb-a-type" name="type_id">';
		foreach ( $types as $t ) {
			echo '<option value="' . (int) $t['id'] . '">' . esc_html( $t['title'] ) . '</option>';
		}
		echo '</select></div><div class="flxb-field"><label for="flxb-a-opt">' . esc_html__( 'Option', 'flx-donate' ) . '</label><select id="flxb-a-opt" name="option_key"><option value="">' . esc_html__( 'Free amount', 'flx-donate' ) . '</option></select></div></div>';
		echo '<div class="flxb-fields-2"><div class="flxb-field"><label for="flxb-a-qty">' . esc_html__( 'Quantity', 'flx-donate' ) . '</label><div class="flxb-stepper"><button type="button" class="flxb-stepper__btn" data-flxb-step="-1" aria-label="' . esc_attr__( 'Decrease', 'flx-donate' ) . '">−</button><input type="number" id="flxb-a-qty" name="quantity" min="1" max="1000" step="1" value="1" inputmode="numeric"><button type="button" class="flxb-stepper__btn" data-flxb-step="1" aria-label="' . esc_attr__( 'Increase', 'flx-donate' ) . '">+</button></div></div>';
		echo '<div class="flxb-field"><label for="flxb-a-amount">' . esc_html__( 'Amount', 'flx-donate' ) . '</label><input type="number" id="flxb-a-amount" name="amount" min="0" step="0.01"><p class="flxb-help">' . esc_html__( 'Only for a free amount.', 'flx-donate' ) . '</p></div></div>';
		echo '<div class="flxb-field"><label for="flxb-a-ben">' . esc_html__( 'On behalf of', 'flx-donate' ) . '</label><textarea id="flxb-a-ben" name="beneficiaries" rows="3"></textarea><p class="flxb-help">' . esc_html__( 'One name per line, one per unit; this name goes on the certificate. Empty = the donor\'s name.', 'flx-donate' ) . '</p></div>';
		echo '</div></section>';
		echo '<section class="flxb-card"><div class="flxb-card__head"><h2 class="flxb-card__title">' . esc_html__( 'Donor', 'flx-donate' ) . '</h2></div><div class="flxb-form">';
		echo '<div class="flxb-field"><label for="flxb-a-name">' . esc_html__( 'Name', 'flx-donate' ) . '</label><input type="text" id="flxb-a-name" name="donor_name" maxlength="190" required></div>';
		echo '<div class="flxb-fields-2"><div class="flxb-field"><label for="flxb-a-phone">' . esc_html__( 'Phone', 'flx-donate' ) . '</label><input type="tel" id="flxb-a-phone" name="donor_phone" maxlength="32"></div>';
		echo '<div class="flxb-field"><label for="flxb-a-mail">' . esc_html__( 'E-mail', 'flx-donate' ) . '</label><input type="email" id="flxb-a-mail" name="donor_email" maxlength="190"></div></div>';
		echo '<p class="flxb-help">' . esc_html__( 'Phone or e-mail is required: the payment link goes there.', 'flx-donate' ) . '</p>';
		echo '<label class="flxb-check"><input type="checkbox" name="send_email" value="1" checked> ' . esc_html__( 'E-mail the payment link to the donor', 'flx-donate' ) . '</label>';
		echo '</div></section></div>';
		echo '<div class="flxb-stack"><section class="flxb-card"><div class="flxb-form">';
		echo '<div class="flxb-field"><label for="flxb-a-note">' . esc_html__( 'Notes', 'flx-donate' ) . '</label><textarea id="flxb-a-note" name="note" rows="3"></textarea></div>';
		echo '<p class="flxb-help">' . esc_html__( 'The donor pays by card or bank transfer on the payment page. You are told when the donation is paid or cancelled.', 'flx-donate' ) . '</p>';
		echo '<div class="flxb-formactions"><button type="submit" class="flxb-btn flxb-btn--primary">' . esc_html__( 'Create request', 'flx-donate' ) . '</button></div>';
		echo '</div></section></div></div></form>';
		FLXB_Admin::footer();
	}

	/** The request just made: payment link to copy or send on WhatsApp. */
	private static function request_result( $order_id ) {
		$order = function_exists( 'wc_get_order' ) ? wc_get_order( $order_id ) : null;
		if ( ! $order || (int) FLXB_Refs::of_order( $order ) !== get_current_user_id() ) {
			return;
		}
		$url   = $order->get_checkout_payment_url();
		$name  = trim( $order->get_billing_first_name() . ' ' . $order->get_billing_last_name() );
		$phone = preg_replace( '/\D/', '', (string) $order->get_billing_phone() );
		if ( 10 === strlen( $phone ) && '5' === $phone[0] ) {
			$phone = '90' . $phone;
		} elseif ( 11 === strlen( $phone ) && '0' === $phone[0] ) {
			$phone = '9' . $phone;
		}
		/* translators: 1: donor name, 2: amount, 3: payment link */
		$text = sprintf( __( 'Hello %1$s, we have received your donation request (%2$s). You can pay here: %3$s', 'flx-donate' ), $name, wp_strip_all_tags( $order->get_formatted_order_total() ), $url );
		echo '<section class="flxb-card flxb-reqdone"><div class="flxb-card__head"><h2 class="flxb-card__title">' . esc_html__( 'Request created', 'flx-donate' ) . '</h2></div><div class="flxb-form">';
		echo '<div class="flxb-field"><label for="flxb-req-url">' . esc_html__( 'Payment link', 'flx-donate' ) . '</label><input type="text" id="flxb-req-url" class="flxb-input code" readonly value="' . esc_attr( $url ) . '" onclick="this.select()"></div>';
		echo '<div class="flxb-formactions">';
		echo '<button type="button" class="flxb-btn" onclick="var i=document.getElementById(\'flxb-req-url\');i.select();navigator.clipboard&&navigator.clipboard.writeText(i.value);this.textContent=' . esc_attr( wp_json_encode( __( 'Copied', 'flx-donate' ) ) ) . ';">' . esc_html__( 'Copy link', 'flx-donate' ) . '</button>';
		if ( strlen( $phone ) >= 10 ) {
			echo '<a class="flxb-btn flxb-btn--primary" target="_blank" rel="noopener" href="' . esc_url( 'https://wa.me/' . $phone . '?text=' . rawurlencode( $text ) ) . '">' . esc_html__( 'Send on WhatsApp', 'flx-donate' ) . '</a>';
		}
		echo '</div>';
		if ( FLXB_Admin::q( 'mailed' ) ) {
			echo '<p class="flxb-help">' . esc_html__( 'The payment link was also e-mailed to the donor.', 'flx-donate' ) . '</p>';
		}
		echo '</div></section>';
	}

	public static function save_request() {
		if ( ! FLXB_Refs::can_create() ) {
			FLXB_Admin::deny();
		}
		check_admin_referer( 'flxb_ref_request' );
		$p   = wp_unslash( $_POST ); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- flxb_create_ref_request() sanitises.
		$res = flxb_create_ref_request(
			array(
				'type_id'     => absint( $p['type_id'] ?? 0 ),
				'option'      => (string) ( $p['option_key'] ?? '' ),
				'units'       => absint( $p['quantity'] ?? 1 ),
				'amount'      => round( (float) str_replace( ',', '.', (string) ( $p['amount'] ?? 0 ) ), 2 ),
				'names'       => (string) ( $p['beneficiaries'] ?? '' ),
				'donor'       => array( 'name' => $p['donor_name'] ?? '', 'phone' => $p['donor_phone'] ?? '', 'email' => $p['donor_email'] ?? '' ),
				'ref_user_id' => get_current_user_id(),
				'send_email'  => ! empty( $p['send_email'] ),
				'note'        => (string) ( $p['note'] ?? '' ),
			)
		);
		if ( is_wp_error( $res ) ) {
			FLXB_Admin::redirect( 'flxb-add', array( 'flxb_msg' => 'error', 'flxb_err' => $res->get_error_message() ) );
		}
		FLXB_Admin::redirect( 'flxb-add', array_filter( array( 'flxb_req' => (int) $res['order_id'], 'mailed' => $res['emailed'] ? '1' : '' ) ) );
	}
}
