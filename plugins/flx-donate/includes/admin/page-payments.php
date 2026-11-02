<?php
/**
 * Flux Donate: Payments (providers, their settings and webhooks, recent payment records).
 * Shown when Flux Donate owns the payment layer (providers/payments.php). Flux Forms's old
 * Payments / Stripe pages redirect here.
 *
 * @package FLX_Bagis
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class FLXB_Page_Payments {

	const PER_PAGE = 30;

	public static function init() {
		add_action( 'admin_menu', array( __CLASS__, 'takeover' ), 31 );
		add_action( 'admin_post_flxb_pay_save', array( __CLASS__, 'save' ) );
		add_action( 'admin_post_flxb_refund', array( __CLASS__, 'refund' ) );
		add_action( 'admin_post_flxb_sub_cancel', array( __CLASS__, 'cancel' ) );
	}

	/** Flux Forms's former payment pages land here. */
	public static function takeover() {
		$page = isset( $_GET['page'] ) ? sanitize_key( wp_unslash( $_GET['page'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		if ( in_array( $page, array( 'flxf_payments', 'emsfb_stripe' ), true ) ) {
			wp_safe_redirect( FLXB_Admin_Url::page( 'flxb-payments' ) );
			exit;
		}
		remove_submenu_page( 'Emsfb', 'Emsfb_stripe' );
	}

	private static function status( $s ) {
		$s   = strtolower( (string) $s );
		$map = array(
			'completed' => array( 'ok', __( 'Completed', 'flx-donate' ) ),
			'active'    => array( 'ok', __( 'Active', 'flx-donate' ) ),
			'trialing'  => array( 'ok', __( 'Trial', 'flx-donate' ) ),
			'pending'   => array( 'pending', __( 'Pending', 'flx-donate' ) ),
			'processing' => array( 'pending', __( 'Processing', 'flx-donate' ) ),
			'mismatch'  => array( 'failed', __( 'Amount mismatch', 'flx-donate' ) ),
			'past_due'  => array( 'failed', __( 'Past due', 'flx-donate' ) ),
			'canceled'  => array( 'failed', __( 'Canceled', 'flx-donate' ) ),
			'failed'    => array( 'failed', __( 'Failed', 'flx-donate' ) ),
			'incomplete' => array( 'failed', __( 'Incomplete', 'flx-donate' ) ),
			'refunded'  => array( 'refunded', __( 'Refunded', 'flx-donate' ) ),
			'partially_refunded' => array( 'refunded', __( 'Partly refunded', 'flx-donate' ) ),
		);
		return $map[ $s ] ?? array( 'refunded', $s );
	}

	/** Where a setting's value comes from: 'own' (here), 'flxform' (Flux Forms settings) or ''. */
	private static function source( $key ) {
		$o = flxb_payment_options();
		if ( isset( $o['fields'][ $key ] ) && '' !== trim( (string) $o['fields'][ $key ] ) ) {
			return 'own';
		}
		return '' !== flxf_payment_setting( $key ) ? 'flxform' : '';
	}

	public static function render() {
		if ( ! current_user_can( 'manage_options' ) ) {
			FLXB_Admin::deny();
		}
		$providers = flxf_payment_providers();
		$active    = flxf_payment_active_provider_id();
		FLXB_Admin::header( __( 'Payments', 'flx-donate' ), __( 'Payment providers that take the money on your forms, and every payment they recorded.', 'flx-donate' ) );

		echo '<div class="flxb-pcards">';
		foreach ( $providers as $id => $p ) {
			$is_active = $id === $active;
			echo '<section class="flxb-pcard' . ( $is_active ? ' is-active' : '' ) . '">';
			echo '<div class="flxb-pcard__head"><span class="flxb-pcard__icon">' . FLXB_Admin::icon( 'card' ) . '</span><h3>' . esc_html( $p->label() ) . '</h3><span class="flxb-chips">'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
			if ( $is_active ) {
				echo '<span class="flxb-pill flxb-pill--ok">' . esc_html__( 'Active', 'flx-donate' ) . '</span>';
			}
			echo $p->is_configured() ? '<span class="flxb-pill">' . esc_html__( 'Configured', 'flx-donate' ) . '</span>' : '<span class="flxb-pill flxb-pill--pending">' . esc_html__( 'Not configured', 'flx-donate' ) . '</span>';
			echo '</span></div>';

			echo '<form class="flxb-form" method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '"><input type="hidden" name="action" value="flxb_pay_save"><input type="hidden" name="provider" value="' . esc_attr( $id ) . '">';
			wp_nonce_field( 'flxb_pay_save_' . $id );
			foreach ( (array) $p->settings_fields() as $f ) {
				if ( empty( $f['key'] ) || ! preg_match( '/^[A-Za-z][A-Za-z0-9_]*$/', (string) $f['key'] ) ) {
					continue;
				}
				$key  = (string) $f['key'];
				$type = in_array( $f['type'] ?? 'text', array( 'text', 'password', 'select', 'checkbox' ), true ) ? $f['type'] : 'text';
				$src  = self::source( $key );
				$fid  = 'flxb-pay-' . $id . '-' . $key;
				$val  = flxf_payment_setting( $key, (string) ( $f['default'] ?? '' ) );
				$inh  = method_exists( $p, 'field_inherited' ) ? (string) $p->field_inherited( $key ) : '';
				echo '<div class="flxb-field">';
				if ( 'checkbox' === $type ) {
					echo '<label class="flxb-check"><input type="checkbox" id="' . esc_attr( $fid ) . '" name="f[' . esc_attr( $key ) . ']" value="1"' . checked( '' !== $val && '0' !== $val, true, false ) . '> ' . esc_html( (string) ( $f['label'] ?? $key ) ) . '</label>';
				} else {
					echo '<label for="' . esc_attr( $fid ) . '">' . esc_html( (string) ( $f['label'] ?? $key ) ) . ( ! empty( $f['required'] ) ? ' <span class="flxb-muted">*</span>' : '' ) . '</label>';
					if ( 'select' === $type ) {
						echo '<select id="' . esc_attr( $fid ) . '" name="f[' . esc_attr( $key ) . ']">';
						foreach ( (array) ( $f['options'] ?? array() ) as $ov => $ol ) {
							echo '<option value="' . esc_attr( (string) $ov ) . '"' . selected( $val, (string) $ov, false ) . '>' . esc_html( (string) $ol ) . '</option>';
						}
						echo '</select>';
					} elseif ( 'password' === $type ) {
						/* Secrets are never printed; an empty input keeps the saved value. */
						echo '<input type="password" id="' . esc_attr( $fid ) . '" name="f[' . esc_attr( $key ) . ']" value="" autocomplete="new-password" placeholder="' . esc_attr( '' !== $src ? __( 'Saved, leave empty to keep it', 'flx-donate' ) : '' ) . '">';
						if ( 'own' === $src ) {
							echo '<label class="flxb-check flxb-help"><input type="checkbox" name="clear[]" value="' . esc_attr( $key ) . '"> ' . esc_html__( 'Remove the saved value', 'flx-donate' ) . '</label>';
						}
					} else {
						echo '<input type="text" id="' . esc_attr( $fid ) . '" name="f[' . esc_attr( $key ) . ']" value="' . esc_attr( 'own' === $src ? $val : '' ) . '" placeholder="' . esc_attr( 'flxform' === $src ? $val : '' ) . '" autocomplete="off">';
					}
				}
				if ( '' !== $inh ) {
					echo '<p class="flxb-help flxb-help--inherited">' . esc_html( $inh ) . '</p>';
				} elseif ( 'flxform' === $src ) {
					echo '<p class="flxb-help">' . esc_html__( 'Using the value saved in Flux Forms\'s settings. Enter a value here to replace it.', 'flx-donate' ) . '</p>';
				} elseif ( ! empty( $f['help'] ) ) {
					echo '<p class="flxb-help">' . esc_html( (string) $f['help'] ) . '</p>';
				}
				echo '</div>';
			}
			if ( ! $is_active ) {
				echo '<label class="flxb-check"><input type="checkbox" name="activate" value="1"> ' . esc_html__( 'Take payments with this provider', 'flx-donate' ) . '</label>';
			}
			echo '<div class="flxb-formactions"><button type="submit" class="flxb-btn flxb-btn--primary">' . esc_html__( 'Save', 'flx-donate' ) . '</button></div></form>';

			if ( $p instanceof FLXB_Payment_Iyzico ) {
				$c = FLXB_Iyzico_Api::credentials();
				echo '<div class="flxb-webhook"><span class="flxb-label">' . esc_html__( 'Environment', 'flx-donate' ) . '</span> ' . ( '' === $c['source'] ? '<span class="flxb-pill flxb-pill--pending">' . esc_html__( 'No keys', 'flx-donate' ) . '</span>' : ( $c['sandbox'] ? '<span class="flxb-pill flxb-pill--pending">' . esc_html__( 'Sandbox (test)', 'flx-donate' ) . '</span>' : '<span class="flxb-pill flxb-pill--ok">' . esc_html__( 'Live', 'flx-donate' ) . '</span>' ) ) . '</div>';
				echo FLXB_Iyzico_Notify::admin_box(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped inside.
				$last = get_option( 'flxb_iyzico_last_run' );
				$next = wp_next_scheduled( FLXB_Iyzico_Recurring::HOOK );
				echo '<div class="flxb-webhook"><span class="flxb-label">' . esc_html__( 'Recurring charges', 'flx-donate' ) . '</span><p class="flxb-help">' . esc_html( sprintf( /* translators: 1: next run, 2: last run */ __( 'Charged by Flux Donate\'s hourly WP-Cron job. Next run: %1$s. Last charge run: %2$s.', 'flx-donate' ), $next ? flxb_date( gmdate( 'Y-m-d H:i:s', $next ) ) : '—', is_array( $last ) && ! empty( $last['time'] ) ? flxb_date( gmdate( 'Y-m-d H:i:s', (int) $last['time'] ) ) : '—' ) ) . '</p></div>';
			}
			if ( 'iyzico' === $id ) {
				echo '</section>';
				continue;
			}
			echo '<div class="flxb-webhook"><span class="flxb-label">' . esc_html__( 'Webhook URL', 'flx-donate' ) . '</span><code class="flxb-code">' . esc_html( rest_url( 'Emsfb/v1/forms/payment/webhook/' . $id ) ) . '</code>';
			if ( 'stripe' === $id ) {
				echo '<p class="flxb-help">' . esc_html__( 'In the Stripe Dashboard add a webhook endpoint with this URL and the events payment_intent.succeeded, payment_intent.payment_failed, payment_intent.canceled, charge.refunded, customer.subscription.created, customer.subscription.updated, customer.subscription.deleted, invoice.paid and invoice.payment_failed, then save its signing secret above. An endpoint set up earlier for Flux Forms keeps working.', 'flx-donate' ) . '</p>';
			}
			echo '</div></section>';
		}
		echo '</div>';

		/* Recent payment records. */
		$page  = max( 1, absint( FLXB_Admin::q( 'paged', '1' ) ) );
		$rows  = FLXB_Payment_Records::recent( self::PER_PAGE, ( $page - 1 ) * self::PER_PAGE );
		$total = FLXB_Payment_Records::count();
		echo '<section class="flxb-card flxb-panel" style="margin-top:18px"><div class="flxb-card__head"><div><h2 class="flxb-card__title">' . esc_html__( 'Payment records', 'flx-donate' ) . '</h2><p class="flxb-card__hint">' . esc_html__( 'Every payment started on a form, including unfinished ones. Completed ones are also listed under Donations.', 'flx-donate' ) . '</p></div></div>';
		if ( ! $rows ) {
			echo '<p class="flxb-empty">' . esc_html__( 'No payments yet.', 'flx-donate' ) . '</p></section>';
			FLXB_Admin::footer();
			return;
		}
		global $wpdb;
		$ids   = array_map( 'intval', wp_list_pluck( $rows, 'id' ) );
		$known = array();
		foreach ( (array) $wpdb->get_results( 'SELECT id, pay_id FROM ' . flxb_table( 'donations' ) . ' WHERE pay_id IN (' . implode( ',', $ids ) . ')', ARRAY_A ) as $d ) { // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			$known[ (int) $d['pay_id'] ] = (int) $d['id'];
		}
		echo '<div class="flxb-tablewrap"><table class="flxb-table"><thead><tr><th scope="col">' . esc_html__( 'Date', 'flx-donate' ) . '</th><th scope="col">' . esc_html__( 'Form', 'flx-donate' ) . '</th><th scope="col">' . esc_html__( 'Provider', 'flx-donate' ) . '</th><th scope="col" class="num">' . esc_html__( 'Amount', 'flx-donate' ) . '</th><th scope="col">' . esc_html__( 'Status', 'flx-donate' ) . '</th><th scope="col">' . esc_html__( 'Confirmation code', 'flx-donate' ) . '</th><th scope="col">' . esc_html__( 'Provider reference', 'flx-donate' ) . '</th><th scope="col"><span class="screen-reader-text">' . esc_html__( 'Donation', 'flx-donate' ) . '</span></th></tr></thead><tbody>';
		foreach ( $rows as $r ) {
			$st   = self::status( $r->status );
			$prov = flxf_payment_provider( $r->gateway );
			$kind = 'subscription' === $r->payment_type ? flxb_interval_label( strtolower( (string) $r->interval_unit ) ) : '';
			echo '<tr><td class="flxb-col-date" data-label="' . esc_attr__( 'Date', 'flx-donate' ) . '"><span>' . esc_html( $r->created_at ? flxb_date( $r->created_at ) : '—' ) . '</span></td>';
			echo '<td class="flxb-col-main">' . esc_html( '' !== (string) $r->form_name ? (string) $r->form_name : flxb_form_title( (int) $r->form_id ) ) . '<span class="flxb-sub2">#' . (int) $r->form_id . ( '' !== $kind ? ' · ' . esc_html( $kind ) : '' ) . '</span></td>';
			echo '<td data-label="' . esc_attr__( 'Provider', 'flx-donate' ) . '"><span>' . esc_html( $prov ? $prov->label() : flxb_provider_label( (string) $r->gateway ) ) . '</span></td>';
			echo '<td class="num" data-label="' . esc_attr__( 'Amount', 'flx-donate' ) . '"><span>' . esc_html( flxb_money( $r->amount, $r->currency ) ) . '</span></td>';
			echo '<td data-label="' . esc_attr__( 'Status', 'flx-donate' ) . '"><span><span class="flxb-pill flxb-pill--' . esc_attr( $st[0] ) . '">' . esc_html( $st[1] ) . '</span></span></td>';
			echo '<td data-label="' . esc_attr__( 'Confirmation code', 'flx-donate' ) . '"><span><code>' . esc_html( (string) $r->track ) . '</code></span></td>';
			echo '<td data-label="' . esc_attr__( 'Provider reference', 'flx-donate' ) . '"><span><code class="flxb-id">' . esc_html( '' !== (string) $r->subscription_id ? (string) $r->subscription_id : (string) $r->transaction_id ) . '</code></span></td>';
			echo '<td class="flxb-col-act">' . ( isset( $known[ (int) $r->id ] ) ? '<a class="flxb-btn flxb-btn--sm" href="' . esc_url( FLXB_Admin_Url::donation( $known[ (int) $r->id ] ) ) . '">' . esc_html__( 'Donation', 'flx-donate' ) . '</a>' : '' );
			self::money_form( $r, null, FLXB_Admin_Url::page( 'flxb-payments', $page > 1 ? array( 'paged' => $page ) : array() ) );
			echo '</td></tr>';
		}
		echo '</tbody></table></div><div class="flxb-tablefoot"><span>' . esc_html( sprintf( /* translators: %s: number of records */ _n( '%s record', '%s records', $total, 'flx-donate' ), number_format_i18n( $total ) ) ) . '</span>';
		echo FLXB_Admin::pagination( $total, self::PER_PAGE, $page, array( 'page' => 'flxb-payments' ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		echo '</div></section>';
		FLXB_Admin::footer();
	}

	public static function save() {
		if ( ! current_user_can( 'manage_options' ) ) {
			FLXB_Admin::deny();
		}
		$id = isset( $_POST['provider'] ) ? sanitize_key( wp_unslash( $_POST['provider'] ) ) : '';
		check_admin_referer( 'flxb_pay_save_' . $id );
		$p = flxf_payment_provider( $id );
		if ( ! $p ) {
			FLXB_Admin::redirect( 'flxb-payments', array( 'flxb_msg' => 'error' ) );
		}
		$o     = flxb_payment_options();
		$in    = isset( $_POST['f'] ) && is_array( $_POST['f'] ) ? wp_unslash( $_POST['f'] ) : array(); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- sanitised per field below
		$clear = isset( $_POST['clear'] ) ? array_map( 'sanitize_key', (array) wp_unslash( $_POST['clear'] ) ) : array();
		foreach ( (array) $p->settings_fields() as $f ) {
			$key = (string) ( $f['key'] ?? '' );
			if ( '' === $key || ! preg_match( '/^[A-Za-z][A-Za-z0-9_]*$/', $key ) ) {
				continue;
			}
			$type = $f['type'] ?? 'text';
			if ( 'checkbox' === $type ) {
				$o['fields'][ $key ] = ! empty( $in[ $key ] ) ? '1' : '0';
				continue;
			}
			if ( 'password' === $type ) {
				if ( in_array( strtolower( $key ), $clear, true ) ) {
					unset( $o['fields'][ $key ] );
				} elseif ( isset( $in[ $key ] ) && '' !== trim( (string) $in[ $key ] ) ) {
					$o['fields'][ $key ] = trim( sanitize_text_field( (string) $in[ $key ] ) );
				}
				continue;
			}
			$v = isset( $in[ $key ] ) ? trim( sanitize_text_field( (string) $in[ $key ] ) ) : '';
			if ( 'select' === $type && '' !== $v && ! array_key_exists( $v, (array) ( $f['options'] ?? array() ) ) ) {
				$v = '';
			}
			if ( '' === $v ) {
				unset( $o['fields'][ $key ] );
			} else {
				$o['fields'][ $key ] = $v;
			}
		}
		if ( 'stripe' === $id ) {
			$err = '';
			$pk  = (string) ( $o['fields']['stripePKey'] ?? '' );
			$sk  = (string) ( $o['fields']['stripeSKey'] ?? '' );
			$wh  = (string) ( $o['fields']['stripeWebhookSecret'] ?? '' );
			if ( '' !== $pk && ! preg_match( '/^pk_(live|test)_[A-Za-z0-9]+$/', $pk ) ) {
				$err = __( 'A Stripe publishable key starts with pk_live_ or pk_test_.', 'flx-donate' );
			} elseif ( '' !== $sk && ! preg_match( '/^(sk|rk)_(live|test)_[A-Za-z0-9]+$/', $sk ) ) {
				$err = __( 'A Stripe secret key starts with sk_live_, sk_test_ or rk_.', 'flx-donate' );
			} elseif ( '' !== $wh && 0 !== strpos( $wh, 'whsec_' ) ) {
				$err = __( 'A Stripe webhook signing secret starts with whsec_.', 'flx-donate' );
			}
			if ( '' !== $err ) {
				FLXB_Admin::redirect( 'flxb-payments', array( 'flxb_msg' => 'error', 'flxb_err' => $err ) );
			}
		}
		if ( ! empty( $_POST['activate'] ) ) {
			$o['provider'] = $id;
		}
		update_option( FLXB_PAY_OPTION, $o, false );
		FLXB_Admin::redirect( 'flxb-payments', array( 'flxb_msg' => 'saved' ) );
	}

	/** Can this payment be refunded / voided from the site? [refund, void, amount left]. */
	public static function money_caps( $row, $donation ) {
		$gw = $row ? (string) $row->gateway : ( $donation ? (string) $donation['provider'] : '' );
		$p  = '' !== $gw ? flxf_payment_provider( $gw ) : null;
		if ( ! $p || ! flxb_can_manage() ) {
			return array( false, false, 0 );
		}
		$status = $donation ? (string) $donation['status'] : (string) $row->status;
		if ( ! in_array( $status, array( 'completed', 'partially_refunded' ), true ) ) {
			return array( false, false, 0 );
		}
		if ( $p instanceof FLXB_Payment_Iyzico ) {
			$m   = $donation ? FLXB_Donations::meta( $donation ) : array();
			$iyz = ! empty( $m['iyzico']['paymentId'] ) ? $m['iyzico'] : array();
			if ( ! $iyz && $row ) {
				$rm  = json_decode( (string) $row->meta, true );
				$iyz = is_array( $rm ) && ! empty( $rm['iyzico']['paymentId'] ) ? $rm['iyzico'] : array();
			}
			if ( ! $iyz ) {
				return array( false, false, 0 );
			}
			$left = 0.0;
			foreach ( (array) $iyz['items'] as $it ) {
				$left += (float) $it['paid'] - (float) ( $it['refunded'] ?? 0 );
			}
			return array( $left > 0.004, 'completed' === $status && empty( $iyz['refunds'] ), round( $left, 2 ) );
		}
		$can = method_exists( $p, 'supports_refunds' ) ? (bool) $p->supports_refunds() : $p instanceof FLXB_Payment_Stripe;
		$one = $row ? 'one-time' === $row->payment_type : ( $donation && 'one_time' === $donation['type'] );
		return array( $can && $one && $row && 'completed' === $status, false, $donation ? (float) $donation['amount'] : (float) $row->amount );
	}

	/** Refund (amount) and cancel controls for a payment record and / or a donation. */
	public static function money_form( $row, $donation, $back ) {
		if ( ! function_exists( 'flxb_owns_payments' ) || ! flxb_owns_payments() ) {
			return;
		}
		list( $refund, $void, $left ) = self::money_caps( $row, $donation );
		if ( ! $refund && ! $void ) {
			return;
		}
		$cur = $donation ? $donation['currency'] : $row->currency;
		$key = $donation ? 'd' . (int) $donation['id'] : 'p' . (int) $row->id;
		echo '<details class="flxb-money"><summary class="flxb-btn flxb-btn--sm">' . esc_html__( 'Refund / cancel', 'flx-donate' ) . '</summary><div class="flxb-money__body">';
		if ( $refund ) {
			echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '" onsubmit="return confirm(this.dataset.confirm)" data-confirm="' . esc_attr__( 'Refund this amount to the donor through the payment provider?', 'flx-donate' ) . '"><input type="hidden" name="action" value="flxb_refund"><input type="hidden" name="op" value="refund"><input type="hidden" name="back" value="' . esc_attr( $back ) . '">';
			echo $donation ? '<input type="hidden" name="id" value="' . (int) $donation['id'] . '">' : '<input type="hidden" name="pay" value="' . (int) $row->id . '">';
			wp_nonce_field( 'flxb_refund_' . $key );
			echo '<label class="flxb-money__label" for="flxb-ra-' . esc_attr( $key ) . '">' . esc_html( sprintf( /* translators: %s: amount */ __( 'Amount (up to %s)', 'flx-donate' ), flxb_money( $left, $cur, 2 ) ) ) . '</label>';
			echo '<span class="flxb-money__row"><input type="number" class="flxb-input" id="flxb-ra-' . esc_attr( $key ) . '" name="amount" step="0.01" min="0.01" max="' . esc_attr( number_format( $left, 2, '.', '' ) ) . '" value="' . esc_attr( number_format( $left, 2, '.', '' ) ) . '"> <button type="submit" class="flxb-btn flxb-btn--sm">' . esc_html__( 'Refund', 'flx-donate' ) . '</button></span></form>';
		}
		if ( $void ) {
			echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '" onsubmit="return confirm(this.dataset.confirm)" data-confirm="' . esc_attr__( 'Cancel this payment at iyzico? This works only on the day of the payment, before it is settled; the whole amount is released to the donor.', 'flx-donate' ) . '"><input type="hidden" name="action" value="flxb_refund"><input type="hidden" name="op" value="cancel"><input type="hidden" name="back" value="' . esc_attr( $back ) . '">';
			echo $donation ? '<input type="hidden" name="id" value="' . (int) $donation['id'] . '">' : '<input type="hidden" name="pay" value="' . (int) $row->id . '">';
			wp_nonce_field( 'flxb_refund_' . $key );
			echo '<p class="flxb-help">' . esc_html__( 'Same day only: cancel the payment instead of refunding it.', 'flx-donate' ) . '</p><button type="submit" class="flxb-linkbtn flxb-linkbtn--danger">' . esc_html__( 'Cancel payment', 'flx-donate' ) . '</button></form>';
		}
		echo '</div></details>';
	}

	/** Refund (full or an amount) or cancel a payment through its provider. */
	public static function refund() {
		if ( ! flxb_can_manage() ) {
			FLXB_Admin::deny();
		}
		$id  = isset( $_POST['id'] ) ? absint( $_POST['id'] ) : 0;
		$pay = isset( $_POST['pay'] ) ? absint( $_POST['pay'] ) : 0;
		check_admin_referer( 'flxb_refund_' . ( $id ? 'd' . $id : 'p' . $pay ) );
		$op     = isset( $_POST['op'] ) && 'cancel' === $_POST['op'] ? 'cancel' : 'refund';
		$amount = isset( $_POST['amount'] ) && '' !== trim( (string) wp_unslash( $_POST['amount'] ) ) ? round( (float) str_replace( ',', '.', sanitize_text_field( wp_unslash( $_POST['amount'] ) ) ), 2 ) : null;
		$back   = isset( $_POST['back'] ) ? esc_url_raw( wp_unslash( $_POST['back'] ) ) : '';
		$d      = $id ? FLXB_Donations::get( $id ) : null;
		$m      = $d ? FLXB_Donations::meta( $d ) : array();
		$row    = $pay ? FLXB_Payment_Records::get( $pay ) : ( $d && (int) $d['pay_id'] && empty( $m['renewal'] ) ? FLXB_Payment_Records::get( (int) $d['pay_id'] ) : null );
		$gw     = $row ? (string) $row->gateway : ( $d ? (string) $d['provider'] : '' );
		$p      = '' !== $gw ? flxf_payment_provider( $gw ) : null;
		$go     = function ( $args ) use ( $back, $id ) {
			$url = '' !== $back ? $back : ( $id ? FLXB_Admin_Url::donation( $id ) : FLXB_Admin_Url::page( 'flxb-payments' ) );
			wp_safe_redirect( add_query_arg( $args, $url ) );
			exit;
		};
		list( $can_refund, $can_void ) = self::money_caps( $row, $d );
		if ( ! $p || ( 'cancel' === $op ? ! $can_void : ! $can_refund ) ) {
			$go( array( 'flxb_msg' => 'error' ) );
		}
		if ( $p instanceof FLXB_Payment_Iyzico ) {
			$res = $p->money( $op, $row, $d, $amount );
		} else {
			$full = null === $amount || ( $row && $amount >= (float) $row->amount );
			$res  = $p->refund( $row, $full ? null : $amount );
			if ( $res->is_paid() ) {
				$st = $full ? 'refunded' : 'partially_refunded';
				if ( ! $d && $row ) {
					global $wpdb;
					$did = (int) $wpdb->get_var( $wpdb->prepare( 'SELECT id FROM ' . flxb_table( 'donations' ) . ' WHERE pay_id = %d LIMIT 1', (int) $row->id ) ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
					$d   = $did ? FLXB_Donations::get( $did ) : null;
				}
				if ( $d ) {
					FLXB_Donations::set_status_by_uniq( $d['uniq'], $st );
					FLXB_Donations::add_audit( (int) $d['id'], 'refund', array( 'provider' => $gw, 'amount' => $full ? (float) $d['amount'] : $amount ) );
				}
			}
		}
		if ( ! $res->is_paid() ) {
			$go( array( 'flxb_msg' => 'error', 'flxb_err' => rawurlencode( $res->message ) ) );
		}
		$go( array( 'flxb_msg' => 'cancel' === $op ? 'voided' : 'refunded' ) );
	}

	/** Cancel a recurring donation at its provider. */
	public static function cancel() {
		$id = isset( $_POST['id'] ) ? absint( $_POST['id'] ) : 0;
		if ( ! flxb_can_manage() ) {
			FLXB_Admin::deny();
		}
		check_admin_referer( 'flxb_sub_cancel_' . $id );
		$s = FLXB_Subscriptions::get( $id );
		$p = $s ? flxf_payment_provider( $s['provider'] ) : null;
		$back = isset( $_POST['back'] ) ? esc_url_raw( wp_unslash( $_POST['back'] ) ) : FLXB_Admin_Url::page( 'flxb-subscriptions' );
		if ( ! $s || ! $p || ! method_exists( $p, 'cancel_subscription' ) ) {
			wp_safe_redirect( add_query_arg( 'flxb_msg', 'error', $back ) );
			exit;
		}
		$res = $p->cancel_subscription( $s['provider_ref'] );
		if ( ! $res->is_paid() ) {
			wp_safe_redirect( add_query_arg( array( 'flxb_msg' => 'error', 'flxb_err' => rawurlencode( $res->message ) ), $back ) );
			exit;
		}
		FLXB_Subscriptions::set_status( $s['provider'], $s['provider_ref'], 'canceled', array( 'canceled_at' => flxb_now() ) );
		$row = FLXB_Payment_Records::get_by_reference( $s['provider'], $s['provider_ref'], 'subscription_id' );
		if ( $row ) {
			FLXB_Payment_Records::update( $row->id, array( 'status' => 'canceled' ) );
		}
		wp_safe_redirect( add_query_arg( 'flxb_msg', 'canceled', $back ) );
		exit;
	}
}
