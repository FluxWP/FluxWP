<?php
/**
 * Flux Donate 2.4.0: "Payment awaiting approval" card on the donation detail.
 *
 * A gift waiting for a bank transfer (or with a receipt linked from Flux Chat) gets a card at
 * the top: receipts open in a preview window (PDF, image), and the manager confirms or rejects. A
 * rejection carries a reason that is sent to the donor: WhatsApp through Flux Chat (so it
 * shows in the chat) or Flux Mail, and e-mail when there is an address.
 *
 * GPL v2 or later.
 *
 * @package FLX_Bagis
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class FLXB_Approval {

	public static function init() {
		add_action( 'admin_post_flxb_pay_approve', array( __CLASS__, 'approve' ) );
		add_action( 'admin_post_flxb_pay_reject', array( __CLASS__, 'reject' ) );
	}

	/** May the current user approve or reject this gift? */
	public static function can( array $d ) {
		return (int) $d['wc_order_id'] ? current_user_can( 'edit_shop_orders' ) : flxb_can_manage();
	}

	/** The WooCommerce order of a gift, when it is still waiting for its payment. */
	private static function open_order( array $d ) {
		$oid = (int) $d['wc_order_id'];
		if ( ! $oid || ! function_exists( 'wc_get_order' ) ) {
			return null;
		}
		$order = wc_get_order( $oid );
		return $order && $order->has_status( array( 'pending', 'on-hold' ) ) ? $order : null;
	}

	/**
	 * Is the gift waiting for a manager's approval? Bank transfers and gifts with a receipt; a card
	 * payment in progress (no receipt) is not.
	 *
	 * @return array|null { order, receipts }
	 */
	public static function pending( array $d ) {
		if ( 'pending' !== $d['status'] ) {
			return null;
		}
		$order = self::open_order( $d );
		if ( (int) $d['wc_order_id'] && ! $order ) {
			return null;
		}
		$receipts = self::receipts( $d, $order );
		$havale   = ( $order && class_exists( 'FLXB_WC_Havale' ) && FLXB_WC_Havale::ID === $order->get_payment_method() ) || in_array( (string) $d['provider'], array( 'bank_transfer', 'havale', 'flxb_havale' ), true );
		if ( ! $receipts && ! $havale ) {
			return null;
		}
		return array(
			'order'    => $order,
			'receipts' => $receipts,
		);
	}

	/**
	 * Receipts from both places: uploaded on the site (order meta) and linked from Flux Chat
	 * (donation meta.receipts).
	 *
	 * @return array[] name, url, kind (image|pdf|file), meta, warn
	 */
	public static function receipts( array $d, $order ) {
		$out = array();
		if ( $order && class_exists( 'FLXB_WC_Receipts' ) ) {
			foreach ( FLXB_WC_Receipts::of( $order ) as $i => $r ) {
				$out[] = array(
					'name' => (string) $r['name'],
					'url'  => FLXB_WC_Receipts::url( $order, $i, true ),
					'kind' => self::kind( (string) ( $r['type'] ?? '' ), (string) $r['name'] ),
					'meta' => implode( ' · ', array_filter( array( ! empty( $r['at'] ) ? wp_date( 'd.m.Y H:i', (int) $r['at'] ) : '', __( 'Site', 'flx-donate' ), ! empty( $r['size'] ) ? size_format( (int) $r['size'] ) : '' ) ) ),
					'warn' => ! empty( $r['dup'] ) ? FLXB_WC_Receipts::dup_text( $r ) : '',
				);
			}
		}
		$m = FLXB_Donations::meta( $d );
		foreach ( array_filter( (array) ( $m['receipts'] ?? array() ), 'is_array' ) as $r ) {
			$url = (string) ( $r['url'] ?? '' );
			if ( ! preg_match( '#^https?://#i', $url ) ) {
				continue;
			}
			$name  = '' !== (string) ( $r['file'] ?? '' ) ? (string) $r['file'] : __( 'Receipt', 'flx-donate' );
			$out[] = array(
				'name' => $name,
				'url'  => $url,
				'kind' => self::kind( (string) ( $r['mime'] ?? '' ), $name ),
				'meta' => implode( ' · ', array_filter( array( ! empty( $r['at'] ) ? flxb_date( (string) $r['at'] ) : '', 'whatsapp' === ( $r['source'] ?? '' ) ? 'WhatsApp' : (string) ( $r['source'] ?? '' ) ) ) ),
				'warn' => ! empty( $r['warn'] ) ? sanitize_text_field( (string) $r['warn'] ) : '',
			);
		}
		return $out;
	}

	private static function kind( $mime, $name ) {
		$ext = strtolower( pathinfo( $name, PATHINFO_EXTENSION ) );
		if ( 'application/pdf' === $mime || 'pdf' === $ext ) {
			return 'pdf';
		}
		if ( 0 === strpos( $mime, 'image/' ) || in_array( $ext, array( 'jpg', 'jpeg', 'jpe', 'png', 'webp', 'gif', 'heic' ), true ) ) {
			return 'image';
		}
		return 'file';
	}

	/** The card at the top of the donation detail. */
	public static function card( array $d, array $sibs ) {
		$p = self::pending( $d );
		if ( ! $p ) {
			return;
		}
		$order = $p['order'];
		$total = $order ? (float) $order->get_total() : array_sum( array_map( 'floatval', wp_list_pluck( $sibs, 'amount' ) ) );
		$cur   = $order ? $order->get_currency() : $d['currency'];
		$bits  = array( flxb_money( $total, $cur, 2 ), $order ? $order->get_payment_method_title() : flxb_provider_label( $d['provider'] ) );
		if ( $order ) {
			/* translators: %s: order number */
			$bits[] = sprintf( __( 'order #%s', 'flx-donate' ), $order->get_order_number() );
		}
		echo '<section class="flxb-card flxb-approve" aria-labelledby="flxb-approve-title"><div class="flxb-card__head"><div><h2 class="flxb-card__title" id="flxb-approve-title">' . esc_html__( 'Payment awaiting approval', 'flx-donate' ) . '</h2>';
		echo '<p class="flxb-card__hint">' . esc_html( implode( ' · ', array_filter( $bits ) ) ) . '</p></div>';
		if ( self::can( $d ) ) {
			echo '<div class="flxb-approve__actions">';
			echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '" onsubmit="return confirm(' . esc_attr( wp_json_encode( __( 'Has the payment reached the bank? When you confirm, the certificate is made and sent to the donor.', 'flx-donate' ) ) ) . ')">';
			wp_nonce_field( 'flxb_pay_' . (int) $d['id'] );
			echo '<input type="hidden" name="action" value="flxb_pay_approve"><input type="hidden" name="id" value="' . (int) $d['id'] . '">';
			echo '<button type="submit" class="flxb-btn flxb-btn--primary">' . esc_html__( 'Approve', 'flx-donate' ) . '</button></form>';
			echo '<button type="button" class="flxb-btn flxb-btn--danger" data-flxb-open="flxb-reject">' . esc_html__( 'Reject', 'flx-donate' ) . '</button>';
			echo '</div>';
		}
		echo '</div>';
		if ( $p['receipts'] ) {
			echo '<ul class="flxb-approve__files">';
			foreach ( $p['receipts'] as $r ) {
				echo '<li><button type="button" class="flxb-approve__file" data-flxb-preview="' . esc_url( $r['url'] ) . '" data-kind="' . esc_attr( $r['kind'] ) . '" data-name="' . esc_attr( $r['name'] ) . '">';
				echo '<span class="flxb-approve__icon" aria-hidden="true">' . ( 'pdf' === $r['kind'] ? 'PDF' : ( 'image' === $r['kind'] ? 'IMG' : 'DOC' ) ) . '</span>';
				echo '<span class="flxb-approve__name"><strong>' . esc_html( $r['name'] ) . '</strong>' . ( '' !== $r['meta'] ? '<small>' . esc_html( $r['meta'] ) . '</small>' : '' ) . '</span>';
				echo '<span class="flxb-approve__go">' . esc_html__( 'Preview', 'flx-donate' ) . '</span></button>';
				if ( '' !== $r['warn'] ) {
					echo '<p class="flxb-notice flxb-notice--warn">' . esc_html( $r['warn'] ) . '</p>';
				}
				echo '</li>';
			}
			echo '</ul>';
		} else {
			echo '<p class="flxb-muted">' . esc_html__( 'No receipt uploaded yet. Check the bank account before approving.', 'flx-donate' ) . '</p>';
		}
		echo '</section>';

		// Preview window.
		echo '<dialog class="flxb-modal flxb-modal--preview" id="flxb-preview" aria-labelledby="flxb-preview-title"><div class="flxb-modal__head"><h2 class="flxb-modal__title" id="flxb-preview-title"></h2><a class="flxb-btn flxb-btn--ghost" target="_blank" rel="noopener" data-flxb-preview-open>' . esc_html__( 'Open in new tab', 'flx-donate' ) . '</a><button type="button" class="flxb-modal__x" data-flxb-close aria-label="' . esc_attr__( 'Close', 'flx-donate' ) . '">×</button></div><div class="flxb-modal__body" data-flxb-preview-body></div>';
		if ( self::can( $d ) ) {
			echo '<div class="flxb-modal__foot"><button type="button" class="flxb-btn flxb-btn--danger" data-flxb-open="flxb-reject">' . esc_html__( 'Reject', 'flx-donate' ) . '</button><button type="button" class="flxb-btn flxb-btn--primary" data-flxb-approve>' . esc_html__( 'Approve', 'flx-donate' ) . '</button></div>';
		}
		echo '</dialog>';

		if ( ! self::can( $d ) ) {
			return;
		}
		$donor = (int) $d['donor_id'] ? FLXB_Donors::get( (int) $d['donor_id'] ) : null;
		$to    = array_filter( array( $donor && '' !== (string) $donor['phone'] ? 'WhatsApp' : '', $donor && is_email( (string) $donor['email'] ) ? __( 'e-mail', 'flx-donate' ) : '' ) );
		echo '<dialog class="flxb-modal" id="flxb-reject" aria-labelledby="flxb-reject-title"><form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '">';
		wp_nonce_field( 'flxb_pay_' . (int) $d['id'] );
		echo '<input type="hidden" name="action" value="flxb_pay_reject"><input type="hidden" name="id" value="' . (int) $d['id'] . '">';
		echo '<div class="flxb-modal__head"><h2 class="flxb-modal__title" id="flxb-reject-title">' . esc_html__( 'Reject the payment', 'flx-donate' ) . '</h2><button type="button" class="flxb-modal__x" data-flxb-close aria-label="' . esc_attr__( 'Close', 'flx-donate' ) . '">×</button></div>';
		echo '<div class="flxb-modal__body flxb-modal__body--form"><label class="flxb-field"><span>' . esc_html__( 'Reason (sent to the donor)', 'flx-donate' ) . '</span><textarea name="reason" rows="4" required maxlength="600" placeholder="' . esc_attr__( 'e.g. The recipient name on the receipt does not match our account.', 'flx-donate' ) . '"></textarea></label>';
		if ( $to ) {
			/* translators: %s: channels (WhatsApp, e-mail) */
			echo '<label class="flxb-check"><input type="checkbox" name="notify" value="1" checked> ' . esc_html( sprintf( __( 'Tell the donor (%s)', 'flx-donate' ), implode( ', ', $to ) ) ) . '</label>';
		} else {
			echo '<p class="flxb-muted">' . esc_html__( 'The donor has no phone or e-mail; the reason is only kept on the order.', 'flx-donate' ) . '</p>';
		}
		echo '<p class="flxb-muted">' . esc_html__( 'The donation is canceled. The donor can make a new one.', 'flx-donate' ) . '</p></div>';
		echo '<div class="flxb-modal__foot"><button type="button" class="flxb-btn" data-flxb-close>' . esc_html__( 'Cancel', 'flx-donate' ) . '</button><button type="submit" class="flxb-btn flxb-btn--danger">' . esc_html__( 'Reject', 'flx-donate' ) . '</button></div>';
		echo '</form></dialog>';
	}

	/** Load the gift from the request and check the nonce and the right to act. */
	private static function from_request() {
		$id = isset( $_POST['id'] ) ? absint( $_POST['id'] ) : 0;
		check_admin_referer( 'flxb_pay_' . $id );
		$d = FLXB_Donations::get( $id );
		if ( ! $d || ! self::can( $d ) ) {
			FLXB_Admin::deny();
		}
		if ( ! self::pending( $d ) ) {
			FLXB_Admin::redirect( 'flxb-donations', array( 'donation' => $id, 'flxb_msg' => 'error', 'flxb_err' => __( 'This donation is no longer awaiting approval.', 'flx-donate' ) ) );
		}
		return $d;
	}

	/** Group of donation rows paid together (one order). */
	private static function group( array $d ) {
		return (int) $d['wc_order_id'] ? FLXB_Donations::siblings( $d ) : array( $d );
	}

	public static function approve() {
		$d = self::from_request();
		self::do_approve( $d );
		FLXB_Admin::redirect( 'flxb-donations', array( 'donation' => (int) $d['id'], 'flxb_msg' => 'approved' ) );
	}

	/** Approve a pending gift (admin page and mobile app). Returns the order, when there is one. */
	public static function do_approve( array $d ) {
		$order = self::open_order( $d );
		$who   = wp_get_current_user()->display_name;
		if ( $order ) {
			$order->update_meta_data( '_flxb_state', 'approved' );
			$order->save_meta_data();
			/* translators: %s: user */
			$order->add_order_note( sprintf( __( 'Bank transfer confirmed (%s).', 'flx-donate' ), $who ) );
			$order->payment_complete();
		} else {
			if ( FLXB_Donations::set_status_by_uniq( $d['uniq'], 'completed' ) ) {
				FLXB_Donations::add_audit( (int) $d['id'], 'status', array( 'status' => array( 'pending', 'completed' ) ) );
			}
		}
		do_action( 'flxb_payment_approved', (int) $d['id'], $order );
		return $order;
	}

	public static function reject() {
		$d      = self::from_request();
		$reason = isset( $_POST['reason'] ) ? trim( sanitize_textarea_field( wp_unslash( $_POST['reason'] ) ) ) : '';
		if ( '' === $reason ) {
			FLXB_Admin::redirect( 'flxb-donations', array( 'donation' => (int) $d['id'], 'flxb_msg' => 'error', 'flxb_err' => __( 'Please write a reason.', 'flx-donate' ) ) );
		}
		$sent = self::do_reject( $d, $reason, ! empty( $_POST['notify'] ) );
		$msg  = $sent
			/* translators: %s: channels */
			? sprintf( __( 'Payment rejected; the donor was told by%s.', 'flx-donate' ), implode( ', ', $sent ) )
			: __( 'Payment rejected.', 'flx-donate' );
		FLXB_Admin::redirect( 'flxb-donations', array( 'donation' => (int) $d['id'], 'flxb_msg' => 'rejected', 'flxb_err' => $msg ) );
	}

	/**
	 * Reject a pending gift (admin page and mobile app).
	 *
	 * @return string[] channels the donor was told on.
	 */
	public static function do_reject( array $d, $reason, $notify = true ) {
		$reason = mb_substr( $reason, 0, 600 );
		$order  = self::open_order( $d );
		$who    = wp_get_current_user()->display_name;
		foreach ( self::group( $d ) as $s ) {
			FLXB_Donations::update_meta( (int) $s['id'], array( 'rejected' => array( 'reason' => $reason, 'by' => get_current_user_id(), 'at' => flxb_now() ) ) );
		}
		if ( $order ) {
			$order->update_meta_data( '_flxb_state', 'rejected' );
			$order->update_meta_data( '_flxb_reject_reason', $reason );
			$order->save_meta_data();
			/* translators: 1: user, 2: reason */
			$order->update_status( 'cancelled', sprintf( __( 'Payment rejected by%1$s. Reason:%2$s', 'flx-donate' ), $who, $reason ) );
		} elseif ( FLXB_Donations::set_status_by_uniq( $d['uniq'], 'canceled' ) ) {
			FLXB_Donations::add_audit( (int) $d['id'], 'status', array( 'status' => array( 'pending', 'canceled' ) ) );
		}
		$sent = $notify ? self::notify_rejected( FLXB_Donations::get( (int) $d['id'] ), $reason, $order ) : array();
		FLXB_Donations::add_audit( (int) $d['id'], 'rejected', array( 'reason' => $reason, 'sent' => $sent ) );
		if ( $order && $sent ) {
			/* translators: %s: channels */
			$order->add_order_note( sprintf( __( 'Rejection sent to the donor:%s', 'flx-donate' ), implode( ', ', $sent ) ) );
		}
		do_action( 'flxb_payment_rejected', (int) $d['id'], $reason, $order, $sent );
		return $sent;
	}

	/** Message text for the donor. */
	public static function rejected_text( array $d, $reason, $order ) {
		$donor = (int) $d['donor_id'] ? FLXB_Donors::get( (int) $d['donor_id'] ) : null;
		$name  = $donor ? trim( (string) $donor['name'] ) : '';
		$total = $order ? flxb_money( $order->get_total(), $order->get_currency(), 2 ) : flxb_money( $d['amount'], $d['currency'], 2 );
		$lines = array(
			/* translators: %s: donor name */
			'' !== $name ? sprintf( __( 'Hello %s,', 'flx-donate' ), $name ) : __( 'Hello,', 'flx-donate' ),
			/* translators: %s: amount */
			sprintf( __( 'We could not confirm the payment of your%sdonation.', 'flx-donate' ), $total ),
			/* translators: %s: reason */
			sprintf( __( 'Reason: %s', 'flx-donate' ), $reason ),
			__( 'If you think this is a mistake, please reply to this message. Thank you.', 'flx-donate' ),
		);
		return (string) apply_filters( 'flxb_payment_rejected_text', implode( "\n\n", $lines ), $d, $reason, $order );
	}

	/**
	 * Tell the donor: WhatsApp (Flux Chat, else Flux Mail) and e-mail.
	 *
	 * @return string[] channels the message went out on.
	 */
	private static function notify_rejected( $d, $reason, $order ) {
		$sent  = array();
		$donor = $d && (int) $d['donor_id'] ? FLXB_Donors::get( (int) $d['donor_id'] ) : null;
		if ( ! $donor ) {
			return $sent;
		}
		$phone   = (string) $donor['phone'];
		$email   = is_email( (string) $donor['email'] ) ? (string) $donor['email'] : '';
		$blocked = function ( $channel ) use ( $phone, $email ) {
			return function_exists( 'flx_contact_blocked' ) && flx_contact_blocked( $phone, $email, $channel );
		};
		$text = self::rejected_text( $d, $reason, $order );
		if ( '' !== $phone && ! $blocked( 'whatsapp' ) ) {
			$ok = false;
			if ( function_exists( 'flxc_send_text' ) ) {
				$r  = flxc_send_text( $phone, $text, array( 'source' => 'flx-donate', 'user_id' => get_current_user_id(), 'name' => (string) $donor['name'] ) );
				$ok = $r && ! is_wp_error( $r );
			}
			if ( ! $ok && function_exists( 'flxi_send_message' ) ) {
				$r  = flxi_send_message( 'whatsapp', $phone, $text, array( 'source' => 'flx-donate', 'template' => 'flx-donate/payment-rejected' ) );
				$ok = ! empty( $r['ok'] );
			}
			if ( $ok ) {
				$sent[] = 'WhatsApp';
			}
		}
		if ( '' !== $email && ! $blocked( 'email' ) ) {
			$ok = FLXB_Mail::send(
				'flx-donate/payment-rejected',
				$email,
				array(
					'ad'       => (string) $donor['name'],
					'tutar'    => $order ? flxb_money( $order->get_total(), $order->get_currency(), 2 ) : flxb_money( $d['amount'], $d['currency'], 2 ),
					'aciklama' => $reason,
					'siparis'  => $order ? $order->get_order_number() : (string) $d['id'],
				)
			);
			if ( $ok ) {
				$sent[] = __( 'e-mail', 'flx-donate' );
			}
		}
		return $sent;
	}
}
