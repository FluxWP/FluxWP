<?php
/**
 * Terms and privacy on the checkout (2.0.9).
 *
 *  - The terms text links to real pages: WooCommerce's terms page and WordPress' privacy page.
 *    Missing ones are created once: "Donation terms" (a starting text to edit) and, for privacy,
 *    the theme's /kvkk/ page when it is published, else a short "Privacy policy" page.
 *  - The checkout's terms block becomes a required checkbox (block attribute "checkbox").
 *  - The block checkout sends the tick as extension data (flxb.terms); the server refuses an order
 *    without it and keeps the time of consent on the order (_flxb_terms_accepted).
 *
 * GPL v2 or later.
 *
 * @package FLX_Bagis
 */

defined( 'ABSPATH' ) || exit;

class FLXB_WC_Terms {

	const PAGES_OPTION = 'flxb_terms_pages';
	const NS           = 'flxb';

	public static function init() {
		add_action( 'init', array( __CLASS__, 'ensure_pages' ), 30 );
		add_filter( 'render_block', array( __CLASS__, 'block' ), 10, 2 );
		add_action( 'wp_enqueue_scripts', array( __CLASS__, 'enqueue' ), 20 );
		if ( did_action( 'woocommerce_blocks_loaded' ) ) {
			self::schema();
		} else {
			add_action( 'woocommerce_blocks_loaded', array( __CLASS__, 'schema' ) );
		}
		add_action( 'woocommerce_store_api_checkout_update_order_from_request', array( __CLASS__, 'store_api_check' ), 6, 2 );
		add_action( 'woocommerce_checkout_update_order_meta', array( __CLASS__, 'classic_record' ) );
		/* 2.2.3: order-pay page (classic form): one required terms box, no WooCommerce privacy text, "Make payment". */
		add_action( 'woocommerce_checkout_terms_and_conditions', array( __CLASS__, 'no_privacy_text' ), 1 );
		add_filter( 'woocommerce_get_terms_and_conditions_checkbox_text', array( __CLASS__, 'checkbox_text' ) );
		add_filter( 'woocommerce_pay_order_button_text', array( __CLASS__, 'pay_button' ) );
		add_action( 'woocommerce_before_pay_action', array( __CLASS__, 'pay_requires_terms' ) );
		add_action( 'woocommerce_pay_order_after_submit', array( __CLASS__, 'pay_script' ) );
	}

	public static function no_privacy_text() {
		remove_action( 'woocommerce_checkout_terms_and_conditions', 'wc_checkout_privacy_policy_text', 20 );
	}

	public static function checkbox_text() {
		return self::text();
	}

	/**
	 * 2.17.0: one sentence for the one box, with links to the privacy policy and the donation agreement
	 * (WooCommerce terms page). Filter: flxb_terms_text.
	 */
	public static function text() {
		$link = static function ( $id ) {
			$u = $id ? get_permalink( (int) $id ) : '';
			return $u ? array( '<a href="' . esc_url( $u ) . '" target="_blank" rel="noopener">', '</a>' ) : array( '', '' );
		};
		$p = $link( (int) get_option( 'wp_page_for_privacy_policy' ) );
		$t = $link( function_exists( 'wc_terms_and_conditions_page_id' ) ? wc_terms_and_conditions_page_id() : (int) get_option( 'woocommerce_terms_page_id' ) );
		/* translators: 1, 2: privacy policy link; 3, 4: donation agreement link */
		$txt = sprintf( __( 'I have read and accept the%1$sprivacy policy%2$sand the%3$sdonation agreement%4$s.', 'flx-donate' ), $p[0], $p[1], $t[0], $t[1] );
		return (string) apply_filters( 'flxb_terms_text', $txt );
	}

	public static function pay_button() {
		return __( 'Make payment', 'flx-donate' );
	}

	/** WooCommerce checks the box only when its hidden "terms-field" arrives; never let a payment skip it. */
	public static function pay_requires_terms() {
		if ( function_exists( 'wc_terms_and_conditions_checkbox_enabled' ) && wc_terms_and_conditions_checkbox_enabled() ) {
			$_POST['terms-field'] = '1'; // phpcs:ignore WordPress.Security.NonceVerification.Missing -- WooCommerce verified the pay nonce
		}
	}

	/** The box is required in the browser too, with the message beside it. */
	public static function pay_script() {
		$msg = wp_json_encode( __( 'Please read and accept the terms to continue.', 'flx-donate' ) );
		echo '<script>(function(){var f=document.getElementById("order_review"),t=document.getElementById("terms");if(!f||!t)return;t.required=true;var w=t.closest(".form-row")||t.parentNode,m=document.createElement("p");m.className="flxb-terms-err";m.setAttribute("role","alert");m.hidden=true;m.textContent=' . $msg . ';w.appendChild(m);t.addEventListener("change",function(){if(t.checked)m.hidden=true;});f.addEventListener("submit",function(e){if(!t.checked){e.preventDefault();e.stopImmediatePropagation();m.hidden=false;w.scrollIntoView({behavior:"smooth",block:"center"});}},true);})();</script>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
	}

	private static function published( $id ) {
		$p = $id ? get_post( (int) $id ) : null;
		return $p && 'page' === $p->post_type && 'publish' === $p->post_status;
	}

	/** Create and link the pages once (again after a page is deleted or unpublished). */
	public static function ensure_pages() {
		$terms   = (int) get_option( 'woocommerce_terms_page_id' );
		$privacy = (int) get_option( 'wp_page_for_privacy_policy' );
		if ( '1' === get_option( self::PAGES_OPTION ) && self::published( $terms ) && self::published( $privacy ) ) {
			return;
		}
		if ( ! self::published( $terms ) ) {
			$terms = self::page( __( 'Donation terms', 'flx-donate' ), self::terms_content() );
			if ( $terms ) {
				update_option( 'woocommerce_terms_page_id', $terms );
			}
		}
		if ( ! self::published( $privacy ) ) {
			$kvkk    = get_page_by_path( 'kvkk' );
			$privacy = $kvkk && 'publish' === $kvkk->post_status ? (int) $kvkk->ID : self::page( __( 'Privacy policy', 'flx-donate' ), self::privacy_content() );
			if ( $privacy ) {
				update_option( 'wp_page_for_privacy_policy', $privacy );
			}
		}
		update_option( self::PAGES_OPTION, '1', true );
	}

	/** An existing page with this title's slug, published if it was a draft; else a new page. */
	private static function page( $title, $content ) {
		$slug = sanitize_title( $title );
		$old  = get_page_by_path( $slug );
		if ( $old && 'trash' !== $old->post_status ) {
			if ( 'publish' !== $old->post_status ) {
				wp_update_post( array( 'ID' => $old->ID, 'post_status' => 'publish' ) );
			}
			return (int) $old->ID;
		}
		$id = wp_insert_post(
			array(
				'post_type'    => 'page',
				'post_status'  => 'publish',
				'post_title'   => $title,
				'post_name'    => $slug,
				'post_content' => $content,
			)
		);
		return is_wp_error( $id ) ? 0 : (int) $id;
	}

	private static function blocks( array $parts ) {
		$out = '';
		foreach ( $parts as $p ) {
			if ( 'h' === $p[0] ) {
				$out .= "<!-- wp:heading -->\n<h2 class=\"wp-block-heading\">" . esc_html( $p[1] ) . "</h2>\n<!-- /wp:heading -->\n\n";
			} else {
				$out .= "<!-- wp:paragraph -->\n<p>" . esc_html( $p[1] ) . "</p>\n<!-- /wp:paragraph -->\n\n";
			}
		}
		return $out;
	}

	private static function org() {
		return get_bloginfo( 'name' );
	}

	/** A starting text; the association edits it in the page editor. */
	public static function terms_content() {
		$org = self::org();
		return self::blocks(
			array(
				/* translators: %s: association name */
				array( 'p', sprintf( __( 'These terms apply to donations made on this site to%s.', 'flx-donate' ), $org ) ),
				array( 'h', __( 'Your donation', 'flx-donate' ) ),
				array( 'p', __( 'A donation is a gift without anything in return. It is used for the purpose chosen on the donation form (for example sapling planting). When a purpose is fully funded, the association may use the donation for a similar purpose.', 'flx-donate' ) ),
				array( 'h', __( 'Payment', 'flx-donate' ) ),
				array( 'p', __( 'Card payments are processed by the licensed payment institution iyzico with 3D Secure. Card details are not stored on this site. For bank transfers, the donation is confirmed when the money reaches the association account.', 'flx-donate' ) ),
				array( 'h', __( 'Monthly donations', 'flx-donate' ) ),
				array( 'p', __( 'A monthly donation is charged to the same card every month until you cancel it. You can cancel it at any time from your account page or by writing to us.', 'flx-donate' ) ),
				array( 'h', __( 'Certificates', 'flx-donate' ) ),
				array( 'p', __( 'The online certificate is sent to the e-mail address or phone number you give after the payment is confirmed. Please check the names you write on the certificate.', 'flx-donate' ) ),
				array( 'h', __( 'Cancellation and refunds', 'flx-donate' ) ),
				array( 'p', __( 'If a donation was made by mistake (wrong amount or a double payment), write to us within 14 days and it is refunded to the same card or account.', 'flx-donate' ) ),
				array( 'h', __( 'Personal data', 'flx-donate' ) ),
				array( 'p', __( 'Your name and contact details are used to record the donation, send the receipt and certificate, and contact you about your donation. See the privacy policy for details.', 'flx-donate' ) ),
				array( 'h', __( 'Contact', 'flx-donate' ) ),
						array( 'p', '' !== FLXB_WC::support_wa()[0] ? sprintf( /* translators: %s: WhatsApp number */ __( 'For any question about your donation, write to us on WhatsApp:%s', 'flx-donate' ), FLXB_WC::support_wa()[0] ) : __( 'For any question about your donation, write to us through the contact page.', 'flx-donate' ) ),
			)
		);
	}

	public static function privacy_content() {
		$org = self::org();
		return self::blocks(
			array(
				/* translators: %s: association name */
				array( 'p', sprintf( __( '%sprocesses your personal data under the Turkish Personal Data Protection Law (KVKK).', 'flx-donate' ), $org ) ),
				array( 'h', __( 'What we collect', 'flx-donate' ) ),
				array( 'p', __( 'Name, e-mail address, phone number and the details of your donation. Card details are entered on the payment institution and are not stored by us.', 'flx-donate' ) ),
				array( 'h', __( 'Why', 'flx-donate' ) ),
				array( 'p', __( 'To record donations, send receipts and certificates, meet legal obligations and answer your questions.', 'flx-donate' ) ),
				array( 'h', __( 'Your rights', 'flx-donate' ) ),
				array( 'p', __( 'You can ask what data we hold about you, have it corrected or deleted, by writing to us.', 'flx-donate' ) ),
			)
		);
	}

	/** The checkout's terms block: a required checkbox. */
	public static function block( $html, $block ) {
		if ( 'woocommerce/checkout-terms-block' !== ( $block['blockName'] ?? '' ) ) {
			return $html;
		}
		$add = false === strpos( $html, 'data-checkbox=' ) ? ' data-checkbox="true"' : '';
		$add .= false === strpos( $html, 'data-text=' ) ? ' data-text="' . esc_attr( self::text() ) . '"' : '';
		return '' === $add ? $html : preg_replace( '/<div\b/', '<div' . $add, $html, 1 );
	}

	public static function enqueue() {
		if ( ! function_exists( 'is_checkout' ) || ! is_checkout() || is_wc_endpoint_url() ) {
			return;
		}
		wp_enqueue_script( 'flxb-terms', FLXB_URL . 'assets/wc/terms.js', array( 'wp-data' ), FLXB_VERSION, true );
	}

	public static function schema() {
		if ( ! function_exists( 'woocommerce_store_api_register_endpoint_data' ) ) {
			return;
		}
		woocommerce_store_api_register_endpoint_data(
			array(
				'endpoint'        => \Automattic\WooCommerce\StoreApi\Schemas\V1\CheckoutSchema::IDENTIFIER,
				'namespace'       => self::NS,
				'schema_callback' => static function () {
					return array(
						'terms' => array(
							'type'     => 'boolean',
							'context'  => array(),
							'optional' => true,
						),
					);
				},
				'schema_type'     => ARRAY_A,
			)
		);
	}

	/** Is the terms block on the checkout page a checkbox? (Only then is a tick expected.) */
	private static function required() {
		return (bool) apply_filters( 'flxb_wc_terms_required', true );
	}

	public static function store_api_check( $order, $request ) {
		if ( ! $order instanceof WC_Order || ! self::required() ) {
			return;
		}
		/* 2.17.0: newer WooCommerce re-calculates the totals through this route when the payment method changes
		   (?__experimental_calc_totals=true, no order is placed); only the real "Place order" needs the tick. */
		if ( is_object( $request ) && method_exists( $request, 'get_param' ) && $request->get_param( '__experimental_calc_totals' ) ) {
			return;
		}
		$ext = $request['extensions'] ?? array();
		if ( empty( $ext[ self::NS ]['terms'] ) ) {
			throw new \Automattic\WooCommerce\StoreApi\Exceptions\RouteException( 'flxb_terms_required', esc_html__( 'Please read and accept the terms to continue.', 'flx-donate' ), 400 ); // phpcs:ignore
		}
		$order->update_meta_data( '_flxb_terms_accepted', gmdate( 'c' ) );
	}

	/** Classic checkout: WooCommerce itself requires the "terms" box when a terms page is set. */
	public static function classic_record( $order_id ) {
		$order = wc_get_order( $order_id );
		if ( $order && ! empty( $_POST['terms'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Missing
			$order->update_meta_data( '_flxb_terms_accepted', gmdate( 'c' ) );
			$order->save_meta_data();
		}
	}
}
