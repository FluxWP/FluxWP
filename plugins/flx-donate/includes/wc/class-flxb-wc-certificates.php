<?php
/**
 * Flux Donate 2.0: certificates from paid orders (Flux Certificates).
 *
 * When an order is paid (card: at once; bank transfer: when the admin confirms), one certificate is
 * made for every donation line that asks for one and sent through the chosen channels. Runs once
 * per line (line meta _flxb_cert_id).
 *
 * On behalf of someone else: the certificate carries their name and goes to their phone / e-mail;
 * empty contacts fall back to the donor (checkout, then the account's billing_phone / e-mail).
 * "Send me a copy too" also sends the link to the donor.
 *
 * GPL v2 or later.
 *
 * @package FLX_Bagis
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class FLXB_WC_Certificates {

	public static function init() {
		add_action( 'woocommerce_payment_complete', array( __CLASS__, 'issue' ), 20 );
		add_action( 'woocommerce_order_status_processing', array( __CLASS__, 'issue' ), 20 );
		add_action( 'woocommerce_order_status_completed', array( __CLASS__, 'issue' ), 20 );
		add_action( 'woocommerce_thankyou', array( __CLASS__, 'thankyou' ), 5 );
	}

	public static function order_paid( WC_Order $order ) {
		return $order->is_paid() || $order->has_status( array( 'processing', 'completed' ) );
	}

	/** Does a Flux Forms automation of this form make the certificate itself (Flux Forms 2.1+)? */
	public static function form_handles( array $line ) {
		$fid = (int) ( $line['form']['id'] ?? 0 );
		return $fid && function_exists( 'flxf_automations_form_issues_certificate' ) && flxf_automations_form_issues_certificate( $fid );
	}

	/** Does this line get a certificate from Flux Donate? */
	public static function line_wants( $item, array $line ) {
		$row = (int) $item->get_meta( '_flxb_donation_id' ) ? FLXB_Donations::get( (int) $item->get_meta( '_flxb_donation_id' ) ) : null;
		if ( $row && '' !== (string) $row['certificate_code'] ) {
			return false; // already has one (e.g. made by the call-centre agent)
		}
		if ( ! $item->get_meta( 'flxb_cert' ) && ! self::callcenter_line( $item ) ) {
			return false;
		}
		$t = FLXB_Types::get( (int) $line['type_id'] );
		if ( $t && ! FLXB_WC_Cart::type_has_certificate( $t ) ) {
			return false;
		}
		return ! self::form_handles( $line );
	}

	/**
	 * 2.2.1: a line of a Flux Chat order (WhatsApp assistant or agent) gets the online
	 * certificate of its type: those orders were made without the "certificate" choice, so after the
	 * bank transfer was confirmed nothing was sent.
	 */
	private static function callcenter_line( $item ) {
		$order = $item->get_order();
		return $order && FLXB_WC_Callcenter::VIA === $order->get_created_via();
	}

	/** Phone and e-mail of the donor: checkout first, then the account (billing_phone / e-mail). */
	public static function donor_contacts( WC_Order $order ) {
		$phone = (string) $order->get_billing_phone();
		$email = FLXB_WC_Contact::email( $order );
		$uid   = (int) $order->get_customer_id();
		if ( $uid ) {
			if ( '' === $phone ) {
				$phone = (string) get_user_meta( $uid, 'billing_phone', true );
			}
			if ( '' === $email ) {
				$u     = get_userdata( $uid );
				$email = $u ? (string) $u->user_email : '';
			}
		}
		return array( $phone, $email );
	}

	/** 2.2.0: the certificate's referral is the donation's (Flux Roles), not the customer. */
	private static function ref_of( WC_Order $order, WC_Order_Item_Product $item ) {
		$row = (int) $item->get_meta( '_flxb_donation_id' ) ? FLXB_Donations::get( (int) $item->get_meta( '_flxb_donation_id' ) ) : null;
		$ref = $row ? (int) ( $row['ref_user_id'] ?? 0 ) : 0;
		return $ref ? $ref : FLXB_Refs::of_order( $order );
	}

	/**
	 * Make and send the certificates of an order's donation lines.
	 *
	 * @param int|WC_Order $order_id
	 * @return array Certificates made now.
	 */
	public static function issue( $order_id, $args = array() ) {
		$args  = wp_parse_args( is_array( $args ) ? $args : array(), array( 'send' => true, 'force' => false ) );
		$order = $order_id instanceof WC_Order ? $order_id : wc_get_order( $order_id );
		if ( ! $order || ! self::order_paid( $order ) || ! FLXB_WC::certificates_available() ) {
			return array();
		}
		$lines = FLXB_WC_Cart::order_lines( $order );
		if ( ! $lines ) {
			return array();
		}
		$lock = 'flxb_cert_lock_' . $order->get_id();
		if ( get_transient( $lock ) ) {
			return array();
		}
		set_transient( $lock, 1, 60 );
		$made    = array();
		$billing = trim( $order->get_billing_first_name() . ' ' . $order->get_billing_last_name() );
		$billing = '' !== $billing ? $billing : $order->get_billing_company();
		list( $dphone, $demail ) = self::donor_contacts( $order );

		foreach ( $lines as $item ) {
			if ( (int) $item->get_meta( '_flxb_cert_id' ) ) {
				continue;
			}
			$line = FLXB_WC_Cart::item_line( $item );
			if ( ! $line || ! ( $args['force'] ? self::line_wants_any( $item, $line ) : self::line_wants( $item, $line ) ) ) {
				continue;
			}
			$t        = FLXB_Types::get( (int) $line['type_id'] );
			$tpl_id   = FLXB_WC_Designs::template_for( $line, $t ); // 2.0.7: the design the donor picked
			$template = $tpl_id ? flxs_get_template( $tpl_id ) : null;
			if ( ! $template ) {
				$order->add_order_note( __( '\\nThe certificate could not be made: no Flux Certificates template was found (Donations › Settings › WooCommerce).\\n', 'flx-donate' ) );
				break;
			}
			list( $n ) = FLXB_WC_Cart::units( $line, (int) $item->get_quantity() );
			$saplings  = (int) $item->get_meta( 'flxb_fidan' );
			$total     = max( 1, $saplings ? $saplings : (int) $n );
			$phone     = '' !== (string) ( $line['ben_phone'] ?? '' ) ? (string) $line['ben_phone'] : $dphone;
			$email     = '' !== (string) ( $line['ben_email'] ?? '' ) ? (string) $line['ben_email'] : $demail;
			$ids       = array();
			$nos       = array();
			$codes     = array();
			// 2.4.1: one certificate per name on whose behalf (Kader, Ferhat → two certificates).
			foreach ( self::recipients( $line, $item, $billing, $total ) as $rc ) {
				$cert = self::make_one( $order, $item, $line, $template, (int) $tpl_id, $rc['name'], $rc['count'], $phone, $email, $billing );
				if ( is_wp_error( $cert ) ) {
					/* translators: %s: error */
					$order->add_order_note( sprintf( __( '\\nThe certificate could not be made:%s\\n', 'flx-donate' ), $cert->get_error_message() ) );
					continue;
				}
				$ids[]   = (int) $cert['id'];
				$nos[]   = (string) $cert['number'];
				$codes[] = (string) $cert['code'];
				$sent    = $args['send'] ? self::send( $order, $cert, $line, $dphone, $demail ) : __( '\\nNot sent yet.\\n', 'flx-donate' );
				/* translators: 1: certificate number, 2: name, 3: delivery summary */
				$order->add_order_note( sprintf( __( '\\nCertificate made:%1$s(%2$s).%3$s\\n', 'flx-donate' ), $cert['number'], $rc['name'], $sent ) );
				$made[] = $cert;
			}
			if ( ! $ids ) {
				continue;
			}
			$item->update_meta_data( '_flxb_cert_id', $ids[0] );
			$item->update_meta_data( '_flxb_cert_ids', $ids );
			$item->update_meta_data( 'flxb_cert_no', implode( ', ', $nos ) );
			$item->save();
			$did = (int) $item->get_meta( '_flxb_donation_id' );
			if ( $did ) {
				FLXB_Donations::set_certificate( $did, $codes[0] );
				FLXB_Donations::update_meta( $did, array( 'certificates' => $codes ) );
			}
		}
		delete_transient( $lock );
		if ( $made ) {
			do_action( 'flxb_wc_certificates_issued', $order, $made );
		}
		return $made;
	}

	/** line_wants() without the "already has a certificate" check (re-making). */
	private static function line_wants_any( $item, array $line ) {
		if ( ! $item->get_meta( 'flxb_cert' ) && ! self::callcenter_line( $item ) ) {
			return false;
		}
		$t = FLXB_Types::get( (int) $line['type_id'] );
		return ! ( $t && ! FLXB_WC_Cart::type_has_certificate( $t ) ) && ! self::form_handles( $line );
	}

	/**
	 * 2.4.1: who gets a certificate for a line, and for how many units. Names given one per share
	 * (or several names in the "on behalf of" field) each get their own certificate; the same name
	 * twice is one certificate for two. Units left without a name go on one certificate in the
	 * line's name (or the donor's).
	 *
	 * @return array[] name, count
	 */
	public static function recipients( array $line, $item, $billing, $total ) {
		$single = trim( (string) ( $line['ben_name'] ?? '' ) );
		$single = '' !== $single ? $single : trim( (string) $item->get_meta( 'flxb_cert_name' ) );
		$names  = ! empty( $line['beneficiaries'] ) && is_array( $line['beneficiaries'] ) ? array_values( array_filter( array_map( 'trim', array_map( 'strval', $line['beneficiaries'] ) ) ) ) : array();
		if ( ! $names && $total > 1 && '' !== $single && preg_match( '/[,;\n]/u', $single ) ) {
			$names = array_values( array_filter( array_map( 'trim', preg_split( '/\s*(?:[,;\n]|\s+ve\s+|\s+and\s+)\s*/u', $single ) ) ) );
		}
		$names = array_slice( $names, 0, max( 1, (int) $total ) );
		if ( count( $names ) < 2 ) {
			$one = $names ? $names[0] : ( '' !== $single ? $single : $billing );
			return array( array( 'name' => $one, 'count' => max( 1, (int) $total ) ) );
		}
		$out = array();
		foreach ( $names as $nm ) {
			$k = function_exists( 'mb_strtolower' ) ? mb_strtolower( $nm ) : strtolower( $nm );
			if ( isset( $out[ $k ] ) ) {
				++$out[ $k ]['count'];
			} else {
				$out[ $k ] = array( 'name' => $nm, 'count' => 1 );
			}
		}
		$left = (int) $total - count( $names );
		if ( $left > 0 ) {
			$rest = '' !== $billing ? $billing : $names[0];
			$k    = function_exists( 'mb_strtolower' ) ? mb_strtolower( $rest ) : strtolower( $rest );
			if ( isset( $out[ $k ] ) ) {
				$out[ $k ]['count'] += $left;
			} else {
				$out[ $k ] = array( 'name' => $rest, 'count' => $left );
			}
		}
		return array_values( $out );
	}

	/** Make one certificate. @return array|WP_Error */
	private static function make_one( WC_Order $order, $item, array $line, array $template, $tpl_id, $name, $count, $phone, $email, $billing ) {
		$p = flxs_upsert_person( $name, $phone, $email );
		if ( is_wp_error( $p ) && in_array( $p->get_error_code(), array( 'flxs_phone', 'flxs_email' ), true ) ) {
			$p = 'flxs_phone' === $p->get_error_code() ? flxs_upsert_person( $name, '', $email ) : flxs_upsert_person( $name, $phone, '' );
			if ( is_wp_error( $p ) ) {
				$p = flxs_upsert_person( $name, '', '' );
			}
		}
		if ( is_wp_error( $p ) ) {
			return $p;
		}
		$person = $p['person'];
		$values = array( 'ad_soyad' => $name );
		foreach ( (array) $template['config']['fields'] as $tf ) {
			if ( in_array( $tf['type'], array( 'static', 'qr', 'number_auto' ), true ) ) {
				continue;
			}
			if ( 'fidan_sayisi' === $tf['key'] ) {
				$values['fidan_sayisi'] = (string) max( 1, (int) $count );
			} elseif ( 'date' === $tf['type'] && ! isset( $values[ $tf['key'] ] ) ) {
				$values[ $tf['key'] ] = wp_date( 'Y-m-d' );
			} elseif ( ! isset( $values[ $tf['key'] ] ) && isset( $tf['default'] ) && '' !== (string) $tf['default'] ) {
				$values[ $tf['key'] ] = (string) $tf['default'];
			}
		}
		$values = apply_filters( 'flxb_wc_certificate_values', $values, $item, $order, $template, $line );
		if ( ! empty( $line['tpl'] ) && (int) $line['tpl'] === (int) $tpl_id ) {
			$values = array_merge( $values, FLXB_WC_Designs::values( $line, $billing ) ); // 2.0.7: the donor's own words on top
			$values['ad_soyad'] = $name;
		}
		$cert = flxs_create_certificate( (int) $tpl_id, (int) $person['id'], $values, self::ref_of( $order, $item ) );
		if ( is_wp_error( $cert ) ) {
			return $cert;
		}
		$ev = (int) FLXB_WC::setting( 'event_id' );
		if ( $ev && function_exists( 'flxs_set_certificate_event' ) ) {
			$r = flxs_set_certificate_event( (int) $cert['id'], $ev );
			if ( ! is_wp_error( $r ) && function_exists( 'flxs_get_certificate' ) ) {
				$cert = flxs_get_certificate( (int) $cert['id'] );
			}
		}
		return $cert;
	}

	/** Certificate ids of a line (2.4.1: several per line). */
	public static function item_ids( $item ) {
		$ids = array_filter( array_map( 'intval', (array) $item->get_meta( '_flxb_cert_ids' ) ) );
		if ( ! $ids && (int) $item->get_meta( '_flxb_cert_id' ) ) {
			$ids = array( (int) $item->get_meta( '_flxb_cert_id' ) );
		}
		return array_values( $ids );
	}

	/**
	 * 2.4.1: revoke an order's certificates and make them again (one per name), without sending.
	 *
	 * @return array Certificates made.
	 */
	public static function remake( WC_Order $order ) {
		foreach ( FLXB_WC_Cart::order_lines( $order ) as $item ) {
			foreach ( self::item_ids( $item ) as $id ) {
				if ( function_exists( 'flxs_set_certificate_status' ) ) {
					flxs_set_certificate_status( $id, 'revoked' );
				}
			}
			$item->delete_meta_data( '_flxb_cert_id' );
			$item->delete_meta_data( '_flxb_cert_ids' );
			$item->delete_meta_data( 'flxb_cert_no' );
			$item->save();
		}
		delete_transient( 'flxb_cert_lock_' . $order->get_id() );
		$made = self::issue( $order, array( 'send' => false, 'force' => true ) );
		/* translators: %d: number of certificates */
		$order->add_order_note( sprintf( __( '\\nCertificates made again (%d); the old ones were revoked.\\n', 'flx-donate' ), count( $made ) ) );
		return $made;
	}

	/** Send all live certificates of an order. @return string summary */
	public static function send_all( WC_Order $order ) {
		list( $dphone, $demail ) = self::donor_contacts( $order );
		$out = array();
		foreach ( FLXB_WC_Cart::order_lines( $order ) as $item ) {
			$line = FLXB_WC_Cart::item_line( $item );
			foreach ( self::item_ids( $item ) as $id ) {
				$c = function_exists( 'flxs_get_certificate' ) ? flxs_get_certificate( $id ) : null;
				if ( $c && 'revoked' !== $c['status'] ) {
					$out[] = $c['number'] . ': ' . self::send( $order, $c, (array) $line, $dphone, $demail );
				}
			}
		}
		$sum = implode( ' · ', $out );
		if ( $out ) {
			/* translators: %s: summary */
			$order->add_order_note( sprintf( __( '\\nCertificates sent:%s\\n', 'flx-donate' ), $sum ) );
		}
		return $sum;
	}

	/** Queue the certificate on the chosen channels; returns a summary for the order note. */
	public static function send( WC_Order $order, array $cert, array $line, $dphone, $demail ) {
		$channels = array_values( array_intersect( (array) FLXB_WC::setting( 'cert_channels' ), array( 'whatsapp', 'sms', 'email' ) ) );
		if ( ! $channels || ! function_exists( 'flxs_send_certificate' ) ) {
			return __( '\\nNo delivery channel is chosen.\\n', 'flx-donate' );
		}
		/* With both WhatsApp and SMS chosen, SMS is used only when there is no WhatsApp provider. */
		if ( in_array( 'whatsapp', $channels, true ) && in_array( 'sms', $channels, true ) && function_exists( 'flxs_resolve_provider' ) && flxs_resolve_provider( 'whatsapp' ) ) {
			$channels = array_values( array_diff( $channels, array( 'sms' ) ) );
		}
		$source = 'flxb_order_' . $order->get_id();
		$res    = flxs_send_certificate( (int) $cert['id'], $channels, array(), $source );
		$ok     = array();
		$err    = array();
		foreach ( (array) $res as $ch => $r ) {
			$label = function_exists( 'flxs_channel_label' ) ? flxs_channel_label( $ch ) : $ch;
			if ( is_wp_error( $r ) ) {
				$err[] = $label . ': ' . $r->get_error_message();
			} else {
				$ok[] = $label;
			}
		}
		/* "Send me a copy too": the donor's own contacts, when they differ from the recipient's. */
		$copies = array();
		if ( ! empty( $line['send_me'] ) && ( ! empty( $line['ben_phone'] ) || ! empty( $line['ben_email'] ) ) ) {
			$donor = trim( $order->get_billing_first_name() . ' ' . $order->get_billing_last_name() );
			foreach ( $channels as $ch ) {
				$to   = 'email' === $ch ? $demail : $dphone;
				$same = 'email' === $ch ? strtolower( (string) ( $line['ben_email'] ?? '' ) ) === strtolower( $to ) : flxb_normalize_phone( (string) ( $line['ben_phone'] ?? '' ) ) === flxb_normalize_phone( $to );
				if ( '' === (string) $to || $same ) {
					continue;
				}
				$r = flxs_send_certificate( (int) $cert['id'], array( $ch ), array(), $source . '_copy', 0, array( $ch => array( 'to' => $to, 'to_name' => $donor ) ) );
				if ( isset( $r[ $ch ] ) && ! is_wp_error( $r[ $ch ] ) ) {
					$copies[] = function_exists( 'flxs_channel_label' ) ? flxs_channel_label( $ch ) : $ch;
				}
			}
		}
		if ( ( $ok || $copies ) && function_exists( 'flxs_kick_queue' ) ) {
			flxs_kick_queue();
		}
		/* translators: %s: channel list */
		$msg = $ok ? sprintf( __( '\\nQueued:%s.\\n', 'flx-donate' ), implode( ', ', $ok ) ) : '';
		/* translators: %s: channel list */
		$msg .= $copies ? ' ' . sprintf( __( '\\nCopy to the donor:%s.\\n', 'flx-donate' ), implode( ', ', $copies ) ) : '';
		return trim( $msg . ( $err ? ' ' . implode( ' · ', $err ) : '' ) );
	}

	/** Is a certificate due for this order? */
	public static function expects( WC_Order $order ) {
		foreach ( FLXB_WC_Cart::order_lines( $order ) as $item ) {
			$line = FLXB_WC_Cart::item_line( $item );
			if ( $line && self::line_wants( $item, $line ) ) {
				return FLXB_WC::certificates_available();
			}
		}
		return false;
	}

	public static function of( WC_Order $order ) {
		$out = array();
		if ( ! function_exists( 'flxs_get_certificate' ) ) {
			return $out;
		}
		foreach ( FLXB_WC_Cart::order_lines( $order ) as $item ) {
			foreach ( self::item_ids( $item ) as $id ) {
				$c = flxs_get_certificate( $id );
				if ( $c && 'revoked' !== $c['status'] ) {
					$out[] = $c;
				}
			}
		}
		return $out;
	}

	/** Data for the card (drawn in the browser with Flux Certificates's renderer when there is no image yet). */
	public static function preview_data( array $cert ) {
		if ( ! function_exists( 'flxs_page_data' ) || ! function_exists( 'flxs_certificate_file_url' ) ) {
			return null;
		}
		$d = flxs_page_data( $cert );
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
			'url'      => flxs_certificate_url( $cert ),
		);
		$need = function_exists( 'flxs_certificate_missing_files' ) ? flxs_certificate_missing_files( $cert ) : array();
		if ( ( '' === $out['image'] || $need ) && function_exists( 'flxs_renderer_scripts' ) && function_exists( 'flxs_renderer_config' ) ) {
			$out['scripts'] = flxs_renderer_scripts();
			$out['config']  = flxs_renderer_config();
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

	/** Certificate cards (thank-you page and order details). */
	public static function cards_html( WC_Order $order, $context = 'thankyou' ) {
		$certs = self::of( $order );
		if ( ! $certs ) {
			return '';
		}
		FLXB_WC_Form::enqueue();
		wp_enqueue_script( 'flxwb-cert', FLXB_URL . 'assets/wc/cert.js', array(), FLXB_VERSION, true );
		wp_localize_script(
			'flxwb-cert',
			'FLXB_WC_CERT',
			array(
				'copied' => __( 'Link copied', 'flx-donate' ),
				'failed' => __( '\\nThe preview could not be drawn. You can open it with "View certificate".\\n', 'flx-donate' ),
			)
		);
		$preview = 'thankyou' === $context || 1 === count( $certs );
		ob_start();
		?>
<section class="flxwb-certs flxwb-certs--<?php echo esc_attr( $context ); ?>">
	<h2 class="flxwb-certs__title"><?php echo esc_html( _n( '[[\'Your certificate is ready\', \'en\']]', '[[\'Your certificates are ready\', \'en\']]', count( $certs ), 'flx-donate' ) ); ?></h2>
		<?php
		foreach ( $certs as $cert ) :
			$d     = $preview ? self::preview_data( $cert ) : null;
			$url   = flxs_certificate_url( $cert );
			$pdf   = function_exists( 'flxs_certificate_pdf_url' ) ? flxs_certificate_pdf_url( $cert ) : '';
			$name  = function_exists( 'flxs_certificate_name' ) ? flxs_certificate_name( $cert ) : '';
			$fidan = (int) ( $cert['data']['values']['fidan_sayisi'] ?? 0 );
			$title = $cert['title'];
			if ( $d ) {
				$d['title'] = $title;
			}
			?>
	<article class="flxwb-cert"<?php echo $d ? ' data-flxwb-cert="' . esc_attr( wp_json_encode( $d, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES ) ) . '"' : ''; ?>>
		<div class="flxwb-cert__head">
			<span class="flxwb-cert__icon"><?php echo FLXB_WC::icon( 'award' ); // phpcs:ignore WordPress.Security.EscapeOutput ?></span>
			<div>
				<p class="flxwb-cert__name"><?php echo esc_html( $name ); ?></p>
				<p class="flxwb-cert__meta"><?php echo esc_html( $title ); ?><?php echo $fidan ? ' · ' . esc_html( FLXB_WC::units_text( $fidan, 'fidan' ) ) : ''; ?> · <?php /* translators: %s: certificate number */ echo esc_html( sprintf( __( '\\nNo:%s\\n', 'flx-donate' ), $cert['number'] ) ); ?></p>
			</div>
		</div>
			<?php
			if ( $d ) :
				$w = $d['w'] > 0 && $d['h'] > 0 ? $d['w'] : 1060;
				$h = $d['w'] > 0 && $d['h'] > 0 ? $d['h'] : 1500;
				?>
		<figure class="flxwb-cert__figure<?php echo $d['image'] ? '' : ' is-loading'; ?>" style="<?php echo esc_attr( 'aspect-ratio:' . $w . ' / ' . $h . ';max-width:calc(70vh * ' . round( $w / $h, 4 ) . ')' ); ?>">
				<?php if ( $d['image'] ) : ?>
			<img src="<?php echo esc_url( $d['image'] ); ?>" alt="<?php echo esc_attr( $title . ' · ' . $name ); ?>" decoding="async" loading="lazy">
				<?php else : ?>
			<canvas aria-label="<?php echo esc_attr( $title ); ?>"></canvas><span class="flxwb-spinner" aria-hidden="true"></span>
				<?php endif; ?>
		</figure>
			<?php endif; ?>
		<div class="flxwb-cert__actions">
			<?php if ( $pdf ) : ?>
			<a class="flxwb-btn flxwb-btn--primary" data-flxwb-pdf href="<?php echo esc_url( $pdf ); ?>" target="_blank" rel="noopener"><?php esc_html_e( 'Download PDF', 'flx-donate' ); ?></a>
			<?php endif; ?>
			<a class="flxwb-btn" href="<?php echo esc_url( $url ); ?>" target="_blank" rel="noopener"><?php esc_html_e( '\\nView certificate\\n', 'flx-donate' ); ?></a>
			<a class="flxwb-btn" data-flxwb-share data-url="<?php echo esc_attr( $url ); ?>" data-title="<?php echo esc_attr( $title ); ?>" href="<?php echo esc_url( 'https://wa.me/?text=' . rawurlencode( $title . ' ' . $url ) ); ?>" target="_blank" rel="noopener"><?php esc_html_e( 'Share', 'flx-donate' ); ?></a>
		</div>
	</article>
		<?php endforeach; ?>
</section>
		<?php
		return ob_get_clean();
	}

	/** Paid but the certificate is not made yet (e.g. the payment notice is late). */
	public static function pending_html() {
		FLXB_WC_Form::enqueue();
		return '<p class="flxwb-note flxwb-note--info">' . FLXB_WC::icon( 'clock' ) . '<span>' . esc_html__( '\\nYour certificate is being prepared. It will be sent by WhatsApp and e-mail in a few minutes; you can also refresh this page to see it.\\n', 'flx-donate' ) . '</span></p>';
	}

	/* Thank-you page: certificate cards for a paid donation. */
	public static function thankyou( $order_id ) {
		$order = wc_get_order( $order_id );
		if ( ! $order || ! FLXB_WC_Cart::order_lines( $order ) || ! empty( $GLOBALS['flxb_ty_done'] ) ) {
			return;
		}
		if ( self::order_paid( $order ) ) {
			self::issue( $order ); // when the payment notice did not arrive before the page
			$html = self::cards_html( $order, 'thankyou' );
			if ( '' === $html && self::expects( $order ) ) {
				$html = self::pending_html();
			}
			if ( '' !== $html ) {
				$GLOBALS['flxb_ty_done'] = true;
			}
			echo $html; // phpcs:ignore WordPress.Security.EscapeOutput
		}
	}
}
