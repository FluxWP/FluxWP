<?php
/**
 * Flux Donate 2.0: donation options on the site ("Bağış Seçenekleri" block flx-wc-bagis/secenekler and
 * the [flx_bagis_secenekleri] shortcode; the block name is the one the theme's donation page uses) and the
 * add-to-cart request.
 *
 * Every open donation type can be shown: option cards (with badges), a free count, a free amount
 * with preset chips, monthly giving, a certificate choice and "on behalf of someone else" with
 * WhatsApp / e-mail delivery cards. The form works without JavaScript (plain POST). No nonce, so
 * cached pages keep working; like WooCommerce's own add-to-cart links it only fills the cart.
 *
 * GPL v2 or later.
 *
 * @package FLX_Bagis
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class FLXB_WC_Form {

	/** 2.17.0: types whose "smart" option view waits for the first pick: [ type id => bool ]. */
	private static $waiting = array();

	public static function init() {
		add_shortcode( 'flx_bagis_secenekleri', array( __CLASS__, 'shortcode' ) );
		add_action( 'init', array( __CLASS__, 'register_block' ) );
		add_action( 'wp_loaded', array( __CLASS__, 'handle_add' ), 25 );
	}

	public static function shortcode( $atts ) {
		$atts = shortcode_atts( array( 'sekme' => '', 'tur' => '' ), $atts, 'flx_bagis_secenekleri' );
		return self::render(
			array(
				'tab'   => (string) $atts['sekme'],
				'types' => (string) $atts['tur'],
			)
		);
	}

	public static function register_block() {
		wp_register_script( 'flxb-wc-block-editor', FLXB_URL . 'assets/wc/block-editor.js', array( 'wp-blocks', 'wp-element', 'wp-components', 'wp-block-editor', 'wp-server-side-render', 'wp-i18n' ), FLXB_VERSION, true );
		$types = array();
		foreach ( flxb_get_types( array( 'status' => 'visible', 'progress' => false ) ) as $t ) {
			$types[] = array( 'value' => $t['slug'], 'label' => $t['title'] );
		}
		wp_localize_script(
			'flxb-wc-block-editor',
			'FLXB_WC_BLOCK',
			array(
				'title'    => __( 'Donation options', 'flx-donate' ),
				'desc'     => __( 'Sapling packages, causes and free-amount donations; added to the cart and paid with WooCommerce.', 'flx-donate' ),
				'tab'       => __( 'Tab shown first', 'flx-donate' ),
				'first'     => __( 'The first one', 'flx-donate' ),
				'types'     => __( 'Donation types', 'flx-donate' ),
				'typesHelp' => __( 'None chosen = the types marked "Show in the Donation options block".', 'flx-donate' ),
				'typeList' => $types,
				'settings' => __( 'Prices and options: Donations › Donation types. Certificates and cart: Donations › Settings › WooCommerce.', 'flx-donate' ),
			)
		);
		register_block_type(
			'flx-wc-bagis/secenekler',
			array(
				'api_version'     => 3,
				'title'           => __( 'Donation options', 'flx-donate' ),
				'category'        => 'widgets',
				'icon'            => 'heart',
				'editor_script'   => 'flxb-wc-block-editor',
				'attributes'      => array(
					'tab'     => array( 'type' => 'string', 'default' => '' ),
					'general' => array( 'type' => 'boolean', 'default' => true ), // Not used since 2.0; kept so saved blocks stay valid.
					'align'   => array( 'type' => 'string', 'default' => '' ),
					'types'   => array( 'type' => 'string', 'default' => '' ),
				),
				'supports'        => array( 'align' => array( 'wide', 'full' ), 'html' => false ),
				'render_callback' => function ( $attrs ) {
					return FLXB_WC_Form::render(
						array(
							'tab'     => (string) ( $attrs['tab'] ?? '' ),
							'align'   => (string) ( $attrs['align'] ?? '' ),
							'types'   => (string) ( $attrs['types'] ?? '' ),
						)
					);
				},
			)
		);
	}

	/** Who is giving: account details used as defaults for certificate delivery. */
	public static function me() {
		$u = wp_get_current_user();
		if ( ! $u || ! $u->ID ) {
			return array( 'name' => '', 'phone' => '', 'email' => '' );
		}
		$phone = (string) get_user_meta( $u->ID, 'billing_phone', true );
		$email = (string) get_user_meta( $u->ID, 'billing_email', true );
		return array(
			'name'  => trim( (string) $u->first_name . ' ' . (string) $u->last_name ) ? trim( $u->first_name . ' ' . $u->last_name ) : (string) $u->display_name,
			'phone' => $phone,
			'email' => '' !== $email ? $email : (string) $u->user_email,
		);
	}

	public static function enqueue() {
		wp_enqueue_style( 'flxwb-front', FLXB_URL . 'assets/wc/front.css', array(), FLXB_VERSION );
		wp_enqueue_script( 'flxwb-front', FLXB_URL . 'assets/wc/front.js', array(), FLXB_VERSION, true );
		if ( ! wp_scripts()->get_data( 'flxwb-front', 'data' ) ) {
			wp_localize_script(
				'flxwb-front',
				'FLXWB',
				array(
					'money'       => array(
						'symbol'   => html_entity_decode( get_woocommerce_currency_symbol(), ENT_QUOTES, 'UTF-8' ),
						'format'   => get_woocommerce_price_format(),
						'decimals' => wc_get_price_decimals(),
						'dec'      => wc_get_price_decimal_separator(),
						'thou'     => wc_get_price_thousand_separator(),
					),
					'rest'        => esc_url_raw( rest_url( 'flxb-wc/v1/' ) ),
					'copied'      => __( 'Copied', 'flx-donate' ),
					'copyFail'    => __( 'Could not copy, please select it by hand.', 'flx-donate' ),
					'uploading'   => __( 'Uploading…', 'flx-donate' ),
					'uploaded'    => __( 'We have your receipt. Thank you! We will let you know once the payment is confirmed.', 'flx-donate' ),
					'tooBig'      => __( 'The file is too large.', 'flx-donate' ),
					'error'       => __( 'Something went wrong. Please try again.', 'flx-donate' ),
					'maxMb'       => (int) FLXB_WC::setting( 'receipt_max_mb' ),
					'another'     => __( 'Add another receipt', 'flx-donate' ),
					'reviewLabel' => __( 'Receipt under review', 'flx-donate' ),
					/* translators: %s: field label */
					'fillField'   => __( 'Please fill in "%s" for the certificate.', 'flx-donate' ),
					'needContact' => __( 'Please write a WhatsApp number or an e-mail address (at least one).', 'flx-donate' ),
					'badEmail'    => __( 'Please enter a valid e-mail address.', 'flx-donate' ),
					'badPhone'    => __( 'Please enter a valid phone number.', 'flx-donate' ),
					'needName'    => __( 'Please write the name of the person you are donating for.', 'flx-donate' ),
					'needConsent' => __( 'Please accept the proxy (vekâlet) text to continue.', 'flx-donate' ),
					'tooMany'     => __( 'Please write at most one name per share.', 'flx-donate' ),
					'monthly'     => __( '/ month', 'flx-donate' ),
					'needOption'  => __( 'Please choose an option.', 'flx-donate' ),
					/* translators: %s: amount */
					'feeUpTo'     => __( '(up to%s)', 'flx-donate' ),
					'feeLine'     => __( 'Transaction fee', 'flx-donate' ),
					/* translators: %s: number of saplings */
					'sapling1'    => _n( '%s Sapling', '%s Saplings', 1, 'flx-donate' ),
					/* translators: %s: number of saplings */
					'saplingN'    => _n( '%s Sapling', '%s Saplings', 2, 'flx-donate' ),
				)
			);
		}
	}

	/** Add-to-cart request (form POST). */
	public static function handle_add() {
		if ( empty( $_POST['flxb_action'] ) || 'add' !== $_POST['flxb_action'] || is_admin() ) { // phpcs:ignore WordPress.Security.NonceVerification
			return;
		}
		$in  = wp_unslash( $_POST ); // phpcs:ignore WordPress.Security.NonceVerification
		$in  = is_array( $in ) ? $in : array();
		$line = self::input( $in );
		$need = self::contact_missing( $line );
		$res  = '' !== $need ? new WP_Error( 'flxb_contact', $need ) : FLXB_WC_Cart::add( $line );
		if ( is_wp_error( $res ) ) {
			$GLOBALS['flxb_form_error'] = $res->get_error_message();
			$GLOBALS['flxb_form_input'] = $in;
			return;
		}
		self::remember_contact( $line );
		FLXB_WC_Fee::remember( $in );
		$after = FLXB_WC::setting( 'after_add' );
		if ( 'checkout' === $after ) {
			$url = wc_get_checkout_url();
		} elseif ( 'stay' === $after ) {
			$url = add_query_arg( 'flxb_added', 1, wp_get_referer() ? wp_get_referer() : FLXB_WC::page_url() );
		} else {
			$url = wc_get_cart_url();
		}
		wp_safe_redirect( $url );
		exit;
	}

	/**
	 * 2.0.5: at least one of WhatsApp / e-mail before "Continue" (the account's own count). '' when fine.
	 * Types without a certificate show no delivery cards; the checkout asks there.
	 */
	public static function contact_missing( array $line ) {
		$t = FLXB_Types::get( (int) $line['type_id'] );
		if ( ! $t || ! FLXB_WC_Cart::type_has_certificate( $t ) || FLXB_Types::requires_proxy( $t ) ) {
			return '';
		}
		$phone = trim( (string) $line['ben_phone'] );
		$email = trim( (string) $line['ben_email'] );
		if ( '' !== $email && ! is_email( $email ) ) {
			return __( 'Please enter a valid e-mail address.', 'flx-donate' );
		}
		if ( '' !== $phone && strlen( preg_replace( '/\D+/', '', $phone ) ) < 10 ) {
			return __( 'Please enter a valid phone number.', 'flx-donate' );
		}
		$me = self::me();
		if ( '' === $phone && '' === $email && ( ! empty( $line['for_other'] ) || ( '' === $me['phone'] && '' === $me['email'] ) ) ) {
			return __( 'Please write a WhatsApp number or an e-mail address (at least one).', 'flx-donate' );
		}
		return '';
	}

	/** 2.0.5: the donor's own WhatsApp / e-mail fill the checkout's contact fields (never overwrite what is there). */
	private static function remember_contact( array $line ) {
		if ( ! empty( $line['for_other'] ) || ! function_exists( 'WC' ) || ! WC()->customer ) {
			return;
		}
		$c     = WC()->customer;
		$email = trim( (string) $line['ben_email'] );
		$phone = trim( (string) $line['ben_phone'] );
		/* 2.0.6: what was typed wins over an older session value; a signed-in account's own details are not rewritten. */
		$guest = ! is_user_logged_in();
		if ( '' !== $email && is_email( $email ) && ( $guest || '' === (string) $c->get_billing_email() ) ) {
			$c->set_billing_email( $email );
		}
		if ( '' !== $phone && ( $guest || '' === (string) $c->get_billing_phone() ) ) {
			$c->set_billing_phone( $phone );
		}
		if ( $guest ) {
			$c->save();
		}
	}

	/** Form fields → build_line() input (per-type field names o[ID], u[ID], a[ID], m[ID]). */
	public static function input( array $in ) {
		$tid = absint( $in['flxb_type'] ?? 0 );
		$pick = function ( $k ) use ( $in, $tid ) {
			return isset( $in[ $k ] ) && is_array( $in[ $k ] ) ? ( $in[ $k ][ $tid ] ?? '' ) : '';
		};
		$opt    = (string) $pick( 'o' );
		$owners = self::owners( $pick( 'owners' ), $pick( 'owner_phones' ), (string) $pick( 'owner_mode' ) );
		return array(
			'type_id'   => $tid,
			'option'    => '__free' === $opt ? '' : $opt,
			'units'     => $pick( 'u' ),
			'amount'    => '__free' === $opt || '' === $opt ? $pick( 'a' ) : '',
			'monthly'   => $pick( 'm' ),
			'cert'      => (string) ( $in['cert'] ?? '' ),
			'address'   => (string) ( $in['address'] ?? '' ),
			'for_other' => ! empty( $in['for_other'] ),
			'ben_name'  => (string) ( $in['ben_name'] ?? '' ),
			'ben_phone' => self::phone_value( (string) ( $in['ben_phone'] ?? '' ) ),
			'ben_email' => (string) ( $in['ben_email'] ?? '' ),
			'send_me'   => ! empty( $in['send_me'] ),
			/* 2.0.1: proxy (vekâlet) types; 2.16.0: one name (+ phone) per share card, "For myself" = none. */
			'beneficiaries'      => $owners[0],
			'beneficiary_phones' => $owners[1],
			'proxy_consent' => (string) $pick( 'proxy' ),
			/* 2.0.7: certificate design picked on the form and its fields */
			'tpl'           => absint( $in['tpl'] ?? 0 ),
			'tpl_values'    => isset( $in['tf'][ absint( $in['tpl'] ?? 0 ) ] ) && is_array( $in['tf'][ absint( $in['tpl'] ?? 0 ) ] ) ? $in['tf'][ absint( $in['tpl'] ?? 0 ) ] : array(),
		);
	}

	/**
	 * 2.16.0: names and phones of the share cards, empty cards dropped, phones kept next to their names.
	 * A plain string (older forms, the API) is still read as one name per line.
	 *
	 * @return array{0: array|string, 1: array}
	 */
	public static function owners( $names, $phones, $mode = '' ) {
		if ( 'self' === $mode ) {
			return array( array(), array() );
		}
		if ( ! is_array( $names ) ) {
			return array( (string) $names, array() );
		}
		$phones = is_array( $phones ) ? array_values( $phones ) : array();
		$out    = array( array(), array() );
		foreach ( array_values( $names ) as $i => $n ) {
			$n = trim( str_replace( array( ';', "\n", "\r" ), ' ', sanitize_text_field( is_scalar( $n ) ? (string) $n : '' ) ) );
			if ( '' === $n ) {
				continue;
			}
			$out[0][] = mb_substr( $n, 0, 80 );
			$out[1][] = self::phone_value( is_scalar( $phones[ $i ] ?? '' ) ? (string) ( $phones[ $i ] ?? '' ) : '' );
		}
		return $out;
	}

	/** 2.16.0: a typed phone in E.164 when the shared phone helper is there ('' when it is not a phone number). */
	public static function phone_value( $raw ) {
		$raw = trim( $raw );
		if ( '' === $raw || ! function_exists( 'flx_phone_normalize' ) ) {
			return $raw;
		}
		return flx_phone_normalize( $raw );
	}

	/** Types shown by a block / shortcode. */
	private static function types_for( array $args ) {
		$out = array();
		if ( '' !== trim( (string) $args['types'] ) ) {
			foreach ( preg_split( '/[\s,]+/', (string) $args['types'] ) as $ref ) {
				$t = is_numeric( $ref ) ? FLXB_Types::get( (int) $ref ) : FLXB_Types::get_by_slug( $ref );
				if ( $t && FLXB_Types::is_open( $t ) ) {
					$out[ (int) $t['id'] ] = FLXB_Types::localize( $t );
				}
			}
			return $out;
		}
		/* Default: the types marked "Show in the Donation options block", in type order. */
		foreach ( FLXB_Types::all( 'active' ) as $t ) {
			if ( FLXB_WC_Cart::in_form( $t ) && FLXB_Types::is_open( $t ) && self::has_choices( $t ) ) {
				$out[ (int) $t['id'] ] = FLXB_Types::localize( $t );
			}
		}
		return $out;
	}

	/** Has the block (with default arguments) anything to show? */
	public static function available() {
		return (bool) self::types_for( array( 'types' => '' ) );
	}

	public static function has_choices( array $t ) {
		if ( $t['allow_free'] ) {
			return true;
		}
		foreach ( $t['options'] as $o ) {
			if ( FLXB_Types::option_on_sale_in( $t, $o, FLXB_WC_Cart::currency() ) ) {
				return true;
			}
		}
		return false;
	}

	private static function tab_key( array $t ) {
		return (string) $t['slug'];
	}

	private static function tab_icon( array $t ) {
		if ( 'fidan' === ( $t['options'][0]['unit'] ?? '' ) ) {
			return 'sprout';
		}
		return $t['allow_free'] && ! $t['options'] ? 'heart' : 'gift';
	}

	/**
	 * Donation options form.
	 *
	 * @param array $args tab (type slug or id), align, types (slugs / ids, comma separated; empty = the
	 *                    types marked "Show in the Donation options block").
	 */
	/**
	 * 2.21.0: a photo band over the form (A · steps layout; Appearance › "Photo band over the form").
	 * Picture: the type's image, else its product's image. Skipped when the page already opens with a cover block.
	 */
	private static function hero( array $t ) {
		if ( 'steps' !== FLXB_WC_Look::get( 'form_style' ) || ! FLXB_WC_Look::get( 'hero' ) ) {
			return '';
		}
		$post = get_post();
		if ( $post && has_block( 'core/cover', $post ) ) {
			return '';
		}
		$img = (int) ( $t['image_id'] ?? 0 );
		if ( ! $img ) {
			$pid = (int) FLXB_Types::meta( $t, 'wc_product_id', 0 );
			$img = $pid ? (int) get_post_thumbnail_id( $pid ) : 0;
		}
		$url = $img ? (string) wp_get_attachment_image_url( $img, 'full' ) : '';
		if ( '' === $url ) {
			$url = FLXB_URL . 'assets/wc/img/fidan-bagisi.jpg';
		}
		$title = $post ? get_the_title( $post ) : (string) $t['title'];
		$desc  = trim( strtok( (string) ( $t['description'] ?? '' ), "\n" ) );
		$html  = '<div class="flxwb-hero" style="--flxwb-hero:url(' . esc_url( $url ) . ')"><div class="flxwb-hero__in">';
		$html .= '<span class="flxwb-hero__pill">' . FLXB_WC::icon( 'sprout' ) . esc_html( (string) $t['title'] ) . '</span>';
		$html .= '<h1 class="flxwb-hero__title">' . esc_html( '' !== $title ? $title : (string) $t['title'] ) . '</h1>';
		if ( '' !== $desc ) {
			$html .= '<p class="flxwb-hero__desc">' . esc_html( $desc ) . '</p>';
		}
		return $html . '</div></div>';
	}

	public static function render( $args = array() ) {
		if ( ! function_exists( 'WC' ) ) {
			return '';
		}
		$args  = wp_parse_args( $args, array( 'tab' => '', 'align' => '', 'types' => '' ) );
		$types = self::types_for( $args );
		if ( ! $types ) {
			return '';
		}
		self::enqueue();
		$err  = (string) ( $GLOBALS['flxb_form_error'] ?? '' );
		$old  = (array) ( $GLOBALS['flxb_form_input'] ?? array() );
		$want = isset( $_GET['sekme'] ) ? sanitize_key( wp_unslash( $_GET['sekme'] ) ) : (string) $args['tab']; // phpcs:ignore WordPress.Security.NonceVerification
		$cur  = (int) key( $types );
		foreach ( $types as $id => $t ) {
			if ( ( ! empty( $old['flxb_type'] ) && (int) $old['flxb_type'] === $id ) || ( empty( $old['flxb_type'] ) && ( self::tab_key( $t ) === $want || (string) $id === $want ) ) ) {
				$cur = $id;
			}
		}
		$me      = self::me();
		$fee     = FLXB_WC_Cart::print_here() ? (float) FLXB_WC::setting( 'print_fee' ) : 0;
		$money   = FLXB_WC_Cart::currency();
		$monthly = FLXB_WC_Cart::monthly_available();
		$data    = array( 'fee' => $fee, 'txfee' => FLXB_WC_Fee::rates_for_form(), 'types' => array(), 'me' => array( 'phone' => (string) $me['phone'], 'email' => (string) $me['email'] ) );
		foreach ( $types as $id => $t ) {
			$opts = array();
			foreach ( $t['options'] as $o ) {
				if ( FLXB_Types::option_on_sale_in( $t, $o, $money ) ) {
					$opts[ $o['key'] ] = array( 'price' => FLXB_Types::option_price( $t, $o, $money ), 'n' => (int) $o['units_per'], 'unit' => (string) $o['unit'], 'qty' => FLXB_WC_Cart::option_takes_units( $t, $o['key'] ) );
				}
			}
			$data['types'][ $id ] = array(
				'opts'    => $opts,
				'free'    => (bool) $t['allow_free'],
				'min'     => FLXB_Types::min_amount_in( $t, $money ),
				'max'     => FLXB_WC_Cart::max_units( $t ),
				'cert'    => FLXB_WC_Cart::type_has_certificate( $t ),
				'proxy'   => FLXB_Types::requires_proxy( $t ),
				'monthly' => $monthly && ! empty( $t['allow_recurring'] ),
			);
		}
		$uid       = 'flxwb-' . wp_rand( 1000, 99999 );
		$sel_cert  = 'print' === ( $old['cert'] ?? '' ) && FLXB_WC_Cart::print_here() ? 'print' : 'online';
		$for_other = ! empty( $old['for_other'] );
		$cur_d     = $data['types'][ $cur ];
		$look      = FLXB_WC_Look::get();
		$style     = (string) $look['form_style'];
		FLXB_WC_Look::enqueue_form();
		$show_tabs = count( $types ) > 1 || 'classic' !== $style;

		/* 2.16.0: the sections are built one by one and printed in the order of Settings › Appearance. */
		$sec = array();

		ob_start();
		foreach ( $types as $id => $t ) :
			?>
		<div class="flxwb-panel" role="tabpanel" id="<?php echo esc_attr( $uid . '-p' . $id ); ?>"<?php echo $show_tabs ? ' aria-labelledby="' . esc_attr( $uid . '-t' . $id ) . '"' : ''; ?> data-flxwb-panel="<?php echo esc_attr( $id ); ?>"<?php echo $id === $cur ? '' : ' hidden'; ?>>
			<?php self::panel( $t, $old, $data['types'][ $id ] ); ?>
		</div>
			<?php
		endforeach;
		$sec['options'] = ob_get_clean();

		ob_start();
		?>
		<fieldset class="flxwb-fs flxwb-who" data-flxwb-who<?php echo $cur_d['proxy'] ? ' hidden' : ''; ?>>
			<legend class="flxwb-legend"><?php esc_html_e( 'Who is this donation for?', 'flx-donate' ); ?></legend>
			<div class="flxwb-seg" role="radiogroup">
				<label class="flxwb-seg__opt"><input type="radio" name="for_other" value="0" <?php checked( ! $for_other ); ?>><span><?php echo FLXB_WC::icon( 'user' ); // phpcs:ignore WordPress.Security.EscapeOutput ?><?php esc_html_e( 'For myself', 'flx-donate' ); ?></span></label>
				<label class="flxwb-seg__opt"><input type="radio" name="for_other" value="1" <?php checked( $for_other ); ?>><span><?php echo FLXB_WC::icon( 'gift' ); // phpcs:ignore WordPress.Security.EscapeOutput ?><?php esc_html_e( 'On behalf of someone else', 'flx-donate' ); ?></span></label>
			</div>
			<label class="flxwb-field" data-flxwb-name<?php echo ! $cur_d['cert'] && ! $for_other ? ' hidden' : ''; ?>>
				<span class="flxwb-field__label" data-flxwb-name-label data-self="<?php esc_attr_e( 'Name on the certificate', 'flx-donate' ); ?>" data-other="<?php esc_attr_e( 'Their name', 'flx-donate' ); ?>"><?php echo $for_other ? esc_html__( 'Their name', 'flx-donate' ) : esc_html__( 'Name on the certificate', 'flx-donate' ); ?></span>
				<input type="text" name="ben_name" maxlength="80" autocomplete="off" value="<?php echo esc_attr( (string) ( $old['ben_name'] ?? '' ) ); ?>" placeholder="<?php esc_attr_e( 'e.g. Ayşe Yılmaz or the Yılmaz family', 'flx-donate' ); ?>">
				<span class="flxwb-field__help" data-flxwb-name-help data-self="<?php esc_attr_e( 'Optional. If you leave it empty, the name from the checkout is used.', 'flx-donate' ); ?>" data-other="<?php esc_attr_e( 'Written on the certificate and the donation record.', 'flx-donate' ); ?>" data-nocert="<?php esc_attr_e( 'Written on the donation record.', 'flx-donate' ); ?>"><?php echo $for_other ? ( $cur_d['cert'] ? esc_html__( 'Written on the certificate and the donation record.', 'flx-donate' ) : esc_html__( 'Written on the donation record.', 'flx-donate' ) ) : esc_html__( 'Optional. If you leave it empty, the name from the checkout is used.', 'flx-donate' ); ?></span>
			</label>
			<div class="flxwb-deliver" data-flxwb-deliver<?php echo $cur_d['cert'] ? '' : ' hidden'; ?>>
				<p class="flxwb-deliver__title"><?php esc_html_e( 'Where should the certificate go?', 'flx-donate' ); ?></p>
				<div class="flxwb-dcard">
					<span class="flxwb-dcard__icon flxwb-dcard__icon--wa"><?php echo FLXB_WC::icon( 'wa' ); // phpcs:ignore WordPress.Security.EscapeOutput ?></span>
					<span class="flxwb-dcard__body"><strong>WhatsApp</strong>
						<?php echo self::phone_input( 'ben_phone', (string) ( $old['ben_phone'] ?? ( $for_other ? '' : $me['phone'] ) ), array( 'data-flxwb-phone' => '' ) ); // phpcs:ignore WordPress.Security.EscapeOutput ?>
					</span>
				</div>
				<label class="flxwb-dcard">
					<span class="flxwb-dcard__icon"><?php echo FLXB_WC::icon( 'mail' ); // phpcs:ignore WordPress.Security.EscapeOutput ?></span>
					<span class="flxwb-dcard__body"><strong><?php esc_html_e( 'E-mail', 'flx-donate' ); ?></strong>
						<input type="email" name="ben_email" autocomplete="off" value="<?php echo esc_attr( (string) ( $old['ben_email'] ?? ( $for_other ? '' : $me['email'] ) ) ); ?>" placeholder="ornek@eposta.com" data-flxwb-email>
					</span>
				</label>
				<p class="flxwb-field__help" data-flxwb-deliver-help data-self="<?php esc_attr_e( 'Empty fields use the phone and e-mail of your account or the checkout.', 'flx-donate' ); ?>" data-other="<?php esc_attr_e( 'Their phone and e-mail. Empty fields use yours.', 'flx-donate' ); ?>"><?php echo $for_other ? esc_html__( 'Their phone and e-mail. Empty fields use yours.', 'flx-donate' ) : esc_html__( 'Empty fields use the phone and e-mail of your account or the checkout.', 'flx-donate' ); ?></p>
				<label class="flxwb-check" data-flxwb-sendme<?php echo $for_other ? '' : ' hidden'; ?>><input type="checkbox" name="send_me" value="1" <?php checked( ! empty( $old['send_me'] ) ); ?>> <?php esc_html_e( 'Send me a copy too', 'flx-donate' ); ?></label>
			</div>
		</fieldset>
		<?php
		foreach ( $types as $id => $t ) {
			if ( ! empty( $data['types'][ $id ]['proxy'] ) ) {
				self::proxy_fields( $t, $old, $id === $cur, (bool) $look['share_phone'] );
			}
		}
		$sec['who'] = ob_get_clean();

		ob_start();
		?>
		<fieldset class="flxwb-fs flxwb-cert" data-flxwb-cert<?php echo $data['types'][ $cur ]['cert'] ? '' : ' hidden'; ?>>
			<legend class="flxwb-legend"><?php esc_html_e( 'Certificate', 'flx-donate' ); ?></legend>
			<div class="flxwb-opts">
				<label class="flxwb-opt">
					<input type="radio" name="cert" value="online" <?php checked( $sel_cert, 'online' ); ?>>
					<span class="flxwb-opt__icon"><?php echo FLXB_WC::icon( 'globe' ); // phpcs:ignore WordPress.Security.EscapeOutput ?></span>
					<span class="flxwb-opt__text"><strong><?php esc_html_e( 'Online certificate', 'flx-donate' ); ?></strong>
						<small><?php esc_html_e( 'Sent by WhatsApp and e-mail once the payment is confirmed, ready to download.', 'flx-donate' ); ?></small></span>
				</label>
				<?php if ( FLXB_WC_Cart::print_here() ) : ?>
				<label class="flxwb-opt">
					<input type="radio" name="cert" value="print" <?php checked( $sel_cert, 'print' ); ?>>
					<span class="flxwb-opt__icon"><?php echo FLXB_WC::icon( 'print' ); // phpcs:ignore WordPress.Security.EscapeOutput ?></span>
					<span class="flxwb-opt__text"><strong><?php esc_html_e( 'Online + printed certificate', 'flx-donate' ); ?></strong>
						<small><?php esc_html_e( 'A printed certificate is also posted to your address.', 'flx-donate' ); ?></small></span>
					<span class="flxwb-opt__price"><?php echo $fee > 0 ? '+' . wp_kses_post( wc_price( $fee ) ) : esc_html__( 'Free', 'flx-donate' ); ?></span>
				</label>
				<?php elseif ( FLXB_WC::print_soon() ) : ?>
				<label class="flxwb-opt is-soon" aria-disabled="true">
					<input type="radio" name="cert" value="print" disabled>
					<span class="flxwb-opt__icon"><?php echo FLXB_WC::icon( 'print' ); // phpcs:ignore WordPress.Security.EscapeOutput ?></span>
					<span class="flxwb-opt__text"><strong><?php esc_html_e( 'Online + printed certificate', 'flx-donate' ); ?></strong>
						<small><?php esc_html_e( 'A printed certificate is also posted to your address.', 'flx-donate' ); ?></small></span>
					<span class="flxwb-soon"><?php esc_html_e( 'Coming soon', 'flx-donate' ); ?></span>
				</label>
				<?php endif; ?>
			</div>
			<?php if ( FLXB_WC_Cart::print_here() ) : ?>
			<label class="flxwb-field" data-flxwb-address<?php echo 'print' === $sel_cert ? '' : ' hidden'; ?>>
				<span class="flxwb-field__label"><?php esc_html_e( 'Postal address for the printed certificate', 'flx-donate' ); ?></span>
				<textarea name="address" rows="3" maxlength="400" autocomplete="street-address" placeholder="<?php esc_attr_e( 'Name, full address, district / city, phone', 'flx-donate' ); ?>"><?php echo esc_textarea( (string) ( $old['address'] ?? '' ) ); ?></textarea>
			</label>
			<?php endif; ?>
		</fieldset>
		<?php
		$sec['cert'] = ob_get_clean();

		$sec['design'] = $cur_d['cert'] || array_filter( array_column( $data['types'], 'cert' ) ) ? FLXB_WC_Designs::render( FLXB_WC::template_id( $types[ $cur ] ), $old ) : '';

		ob_start();
		/* 2.0.5: "Repeat every month" sits under the choices as one compact row (it was squeezed under the cards). */
		foreach ( $types as $id => $t ) :
			if ( empty( $data['types'][ $id ]['monthly'] ) ) {
				continue;
			}
			?>
		<label class="flxwb-monthly" data-flxwb-monthly="<?php echo esc_attr( $id ); ?>"<?php echo $id === $cur ? '' : ' hidden'; ?>><input type="checkbox" name="m[<?php echo esc_attr( $id ); ?>]" value="1" <?php checked( ! empty( $old['m'][ $id ] ) ); ?>>
			<span class="flxwb-monthly__icon"><?php echo FLXB_WC::icon( 'repeat' ); // phpcs:ignore WordPress.Security.EscapeOutput ?></span>
			<span><strong><?php esc_html_e( 'Repeat every month', 'flx-donate' ); ?></strong><small><?php esc_html_e( 'By card, stop any time.', 'flx-donate' ); ?></small></span>
		</label>
			<?php
		endforeach;
		$sec['monthly'] = ob_get_clean();

		ob_start();
		?>
<div class="flxwb <?php echo esc_attr( FLXB_WC_Look::form_class() ); ?><?php echo ! empty( self::$waiting[ $cur ] ) ? ' is-await' : ''; ?><?php echo $args['align'] ? ' align' . esc_attr( $args['align'] ) : ''; ?>" data-flxwb="<?php echo esc_attr( wp_json_encode( $data ) ); ?>"<?php echo '' !== FLXB_WC_Look::vars() ? ' style="' . esc_attr( FLXB_WC_Look::vars() ) . '"' : ''; ?>>
	<?php if ( isset( $_GET['flxb_added'] ) ) : // phpcs:ignore WordPress.Security.NonceVerification ?>
	<div class="flxwb-note flxwb-note--ok" role="status"><?php echo FLXB_WC::icon( 'check' ); // phpcs:ignore WordPress.Security.EscapeOutput ?>
		<span><?php esc_html_e( 'Your donation was added to the cart.', 'flx-donate' ); ?> <a href="<?php echo esc_url( wc_get_cart_url() ); ?>"><?php esc_html_e( 'Go to cart', 'flx-donate' ); ?></a></span>
	</div>
	<?php endif; ?>
	<?php echo self::hero( $types[ $cur ] ); // phpcs:ignore WordPress.Security.EscapeOutput -- escaped inside ?>
	<?php if ( ! empty( self::$waiting[ $cur ] ) ) : ?><noscript><style>.flxwb.is-await .flxwb-sec,.flxwb.is-await .flxwb-end{display:block!important}</style></noscript><?php endif; ?>
	<form class="flxwb-form" method="post" action="" novalidate>
		<input type="hidden" name="flxb_action" value="add">
		<input type="hidden" name="flxb_type" value="<?php echo esc_attr( $cur ); ?>" data-flxwb-type>
		<?php echo FLXB_WC_Currency::switcher(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped inside ?>
		<?php if ( $show_tabs ) : ?>
		<div class="flxwb-types">
			<p class="flxwb-legend flxwb-legend--type" id="<?php echo esc_attr( $uid . '-tl' ); ?>"><?php echo esc_html( '' !== FLXB_WC_Look::label( 'type_label' ) ? FLXB_WC_Look::label( 'type_label' ) : __( 'Donation type', 'flx-donate' ) ); ?></p>
			<div class="flxwb-tabs" role="tablist" aria-labelledby="<?php echo esc_attr( $uid . '-tl' ); ?>">
				<?php foreach ( $types as $id => $t ) : ?>
				<button type="button" role="tab" id="<?php echo esc_attr( $uid . '-t' . $id ); ?>" aria-controls="<?php echo esc_attr( $uid . '-p' . $id ); ?>" aria-selected="<?php echo $id === $cur ? 'true' : 'false'; ?>" data-flxwb-tab="<?php echo esc_attr( $id ); ?>"><?php echo self::tab_inner( $t, $data['types'][ $id ] ); // phpcs:ignore WordPress.Security.EscapeOutput ?></button>
				<?php endforeach; ?>
			</div>
		</div>
		<?php endif; ?>
		<div class="flxwb-main">
		<?php
		foreach ( (array) $look['order'] as $k ) {
			if ( isset( $sec[ $k ] ) && '' !== trim( $sec[ $k ] ) ) {
				echo '<div class="flxwb-sec flxwb-sec--' . esc_attr( $k ) . '" data-flxwb-sec="' . esc_attr( $k ) . '">' . $sec[ $k ] . '</div>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- built above
			}
		}
		?>
		</div>
		<div class="flxwb-end">
			<div class="flxwb-sum" data-flxwb-sum>
				<p class="flxwb-sum__title"><?php esc_html_e( 'Your donation', 'flx-donate' ); ?></p>
				<dl class="flxwb-sum__lines" data-flxwb-sum-lines></dl>
			</div>
			<?php echo FLXB_WC_Fee::form_box( $old ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped inside ?>
			<?php if ( '' !== $err ) : ?>
			<div class="flxwb-note flxwb-note--err" role="alert"><?php echo esc_html( $err ); ?></div>
			<?php endif; ?>
			<div class="flxwb-bar">
				<div class="flxwb-bar__sum">
					<span class="flxwb-bar__label"><?php esc_html_e( 'Total', 'flx-donate' ); ?></span>
					<strong class="flxwb-bar__total" data-flxwb-total aria-live="polite"><?php echo wp_kses_post( wc_price( 0 ) ); ?></strong>
					<span class="flxwb-bar__meta" data-flxwb-meta></span>
				</div>
				<button type="submit" class="flxwb-btn flxwb-btn--primary"><?php echo FLXB_WC::icon( 'cart' ); // phpcs:ignore WordPress.Security.EscapeOutput ?><span><?php echo 'checkout' === FLXB_WC::setting( 'after_add' ) ? esc_html__( 'Continue', 'flx-donate' ) : esc_html__( 'Add to cart', 'flx-donate' ); ?></span></button>
			</div>
			<?php echo self::secure_note(); // phpcs:ignore WordPress.Security.EscapeOutput ?>
		</div>
	</form>
</div>
		<?php
		return ob_get_clean();
	}

	/**
	 * 2.16.0: phone input of the form: the shared FluxWP phone field (country picker + check) when the flx-brand
	 * core has it, else a plain tel input.
	 */
	public static function phone_input( $name, $value, array $attrs = array(), $placeholder = '' ) {
		if ( function_exists( 'flx_phone_field' ) ) {
			return flx_phone_field(
				array(
					'name'        => $name,
					'value'       => $value,
					'attrs'       => $attrs,
					'placeholder' => $placeholder,
					'id'          => 'flxwb-ph-' . wp_rand( 10000, 99999 ),
				)
			);
		}
		$a = '';
		foreach ( $attrs as $k => $v ) {
			$a .= ' ' . esc_attr( $k ) . '="' . esc_attr( $v ) . '"';
		}
		return '<input type="tel" name="' . esc_attr( $name ) . '" inputmode="tel" autocomplete="off" value="' . esc_attr( $value ) . '" placeholder="' . esc_attr( '' !== $placeholder ? $placeholder : '0532 123 45 67' ) . '"' . $a . '>';
	}

	/** 2.16.0: icon name of a type (meta "icon", else from its options). */
	public static function type_icon( array $t ) {
		$i = (string) FLXB_Types::meta( $t, 'icon', '' );
		return '' !== $i && '' !== FLXB_WC::icon( $i ) ? $i : self::tab_icon( $t );
	}

	/** 2.16.0: lowest price of a type in the current currency ("€120", "from €120"); '' for free amounts only. */
	private static function type_price( array $d ) {
		$p = array();
		foreach ( (array) $d['opts'] as $o ) {
			$p[] = (float) $o['price'];
		}
		if ( ! $p ) {
			return '';
		}
		$min = min( $p );
		/* translators: %s: lowest price */
		return count( array_unique( $p ) ) > 1 ? sprintf( __( 'from%s', 'flx-donate' ), wc_price( $min ) ) : wc_price( $min );
	}

	/** 2.16.0: inside of a type tab; the layouts show the parts they need (classic: icon + title). */
	private static function tab_inner( array $t, array $d ) {
		$sub   = (string) FLXB_Types::meta( $t, 'subtitle', '' );
		$img   = ! empty( $t['image_id'] ) ? wp_get_attachment_image( (int) $t['image_id'], 'medium', false, array( 'class' => 'flxwb-tab__photo', 'alt' => '', 'loading' => 'lazy' ) ) : '';
		$price = self::type_price( $d );
		$h     = '<span class="flxwb-tab__media' . ( $img ? ' has-photo' : '' ) . '">' . FLXB_WC::icon( self::type_icon( $t ) ) . $img . '</span>';
		$h    .= '<span class="flxwb-tab__text"><span class="flxwb-tab__title">' . esc_html( self::tab_title( $t ) ) . '</span>' . ( '' !== $sub ? '<small class="flxwb-tab__sub">' . esc_html( $sub ) . '</small>' : '' ) . '</span>';
		$h    .= '' !== $price ? '<span class="flxwb-tab__price">' . wp_kses_post( $price ) . '</span>' : '';
		$h    .= '<span class="flxwb-tab__tick" aria-hidden="true">' . FLXB_WC::icon( 'check' ) . '</span>';
		return $h;
	}

	/** 2.0.2: "Payments are taken securely by iyzico" under the button (iyzico gateway on), else the generic line. */
	public static function secure_note() {
		$iyzico = class_exists( 'FLXB_WC_Iyzico' ) && ( FLXB_WC_Iyzico::official_enabled() || FLXB_WC_Iyzico::usable() );
		$text   = $iyzico ? __( 'Payments are taken securely by iyzico.', 'flx-donate' ) : __( 'Pay safely by card or bank transfer.', 'flx-donate' );
		$sub    = $iyzico ? __( 'Your card details are never stored on our site.', 'flx-donate' ) : '';
		$html   = '<div class="flxwb-pay"><span class="flxwb-pay__icon">' . FLXB_WC::icon( 'lock' ) . '</span><span class="flxwb-pay__text"><strong>' . esc_html( $text ) . '</strong>';
		if ( '' !== $sub ) {
			$html .= '<small>' . esc_html( $sub ) . '</small>';
		}
		return $html . '</span></div>';
	}

	private static function tab_title( array $t ) {
		return (string) $t['title'];
	}

	/** One type's choices. */
	private static function panel( array $t, array $old, array $d ) {
		$id      = (int) $t['id'];
		$custom  = FLXB_WC_Cart::custom_option( $t );
		$sel     = isset( $old['o'][ $id ] ) ? sanitize_key( (string) $old['o'][ $id ] ) : '';
		$pre     = isset( $_GET['paket'] ) ? sanitize_key( wp_unslash( $_GET['paket'] ) ) : ( isset( $_GET['flxb_opt'] ) ? sanitize_key( wp_unslash( $_GET['flxb_opt'] ) ) : '' ); // phpcs:ignore WordPress.Security.NonceVerification
		$pre     = 'custom' === $pre ? $custom : $pre;
		$sel     = '' !== $sel ? $sel : ( isset( $d['opts'][ $pre ] ) ? $pre : '' );
		$cards   = array();
		foreach ( $t['options'] as $o ) {
			if ( isset( $d['opts'][ $o['key'] ] ) ) {
				$cards[] = $o;
			}
		}
		/* 2.17.0: "smart" option view waits for the donor's pick (unless one option only or a pick came with the link). */
		$view = $cards ? (string) FLXB_WC_Look::get( 'opt_view' ) : 'list';
		$wait = 'smart' === $view && '' === $sel && ( count( $cards ) + ( $t['allow_free'] ? 1 : 0 ) ) > 1;
		self::$waiting[ $id ] = $wait;
		if ( '' === $sel && ! $wait ) {
			$sel = $cards ? $cards[0]['key'] : '__free';
		}
		$money   = FLXB_WC_Cart::currency();
		$presets = FLXB_WC_Currency::presets( $t, $money );
		$units   = max( 1, absint( $old['u'][ $id ] ?? ( isset( $_GET['flxb_qty'] ) ? absint( $_GET['flxb_qty'] ) : 1 ) ) ); // phpcs:ignore WordPress.Security.NonceVerification
		$name_o  = 'o[' . $id . ']';
		if ( '' !== (string) $t['description'] ) {
			echo '<p class="flxwb-desc">' . esc_html( (string) $t['description'] ) . '</p>';
		}
		if ( $cards ) :
			?>
		<fieldset class="flxwb-fs flxwb-opts-fs<?php echo 'list' !== $view && ! $wait ? ' is-picked' : ''; ?>" data-flxwb-optview="<?php echo esc_attr( $view ); ?>">
			<legend class="flxwb-legend"><?php echo '' !== FLXB_WC_Look::label( 'option_label' ) ? esc_html( FLXB_WC_Look::label( 'option_label' ) ) : ( 'fidan' === $cards[0]['unit'] ? esc_html__( 'How many saplings would you like to donate?', 'flx-donate' ) : esc_html__( 'Choose an option', 'flx-donate' ) ); ?></legend>
			<div class="flxwb-optrow">
			<?php if ( 'list' !== $view ) : ?>
			<span class="flxwb-pick">
				<select data-flxwb-pick aria-label="<?php echo esc_attr( '' !== FLXB_WC_Look::label( 'option_label' ) ? FLXB_WC_Look::label( 'option_label' ) : __( 'Choose an option', 'flx-donate' ) ); ?>">
					<?php if ( $wait ) : ?><option value="" disabled<?php selected( $sel, '' ); ?>><?php esc_html_e( 'Choose an option', 'flx-donate' ); ?></option><?php endif; ?>
					<?php foreach ( $cards as $o ) : ?>
					<option value="<?php echo esc_attr( $o['key'] ); ?>" <?php selected( $sel, $o['key'] ); ?>><?php echo esc_html( $o['label'] . ' · ' . html_entity_decode( wp_strip_all_tags( wc_price( FLXB_Types::option_price( $t, $o, $money ) ) ), ENT_QUOTES, 'UTF-8' ) ); ?></option>
					<?php endforeach; ?>
					<?php if ( $t['allow_free'] ) : ?><option value="__free" <?php selected( $sel, '__free' ); ?>><?php esc_html_e( 'Another amount', 'flx-donate' ); ?></option><?php endif; ?>
				</select>
				<span class="flxwb-pick__chev" aria-hidden="true"></span>
			</span>
			<?php endif; ?>
			<div class="flxwb-pkgs">
			<?php
			foreach ( $cards as $o ) :
				$n    = max( 1, (int) $o['units_per'] );
				$isu  = '' !== (string) $o['unit'];
				$qty  = ! empty( $d['opts'][ $o['key'] ]['qty'] );
				$cid  = 'flxwb-q-' . $id . '-' . $o['key'];
				?>
				<label class="flxwb-pkg<?php echo '' !== (string) $o['badge'] ? ' has-badge' : ''; ?><?php echo $qty ? ' flxwb-pkg--qty' : ''; ?>">
					<input type="radio" name="<?php echo esc_attr( $name_o ); ?>" value="<?php echo esc_attr( $o['key'] ); ?>" <?php checked( $sel, $o['key'] ); ?>>
					<?php if ( '' !== (string) $o['badge'] ) : ?><span class="flxwb-badge"><?php echo esc_html( FLXB_WC::badge_label( $o['badge'] ) ); ?></span><?php endif; ?>
					<span class="flxwb-pkg__icon"><?php echo FLXB_WC::icon( 'fidan' === $o['unit'] ? 'sprout' : 'gift' ); // phpcs:ignore WordPress.Security.EscapeOutput ?></span>
					<span class="flxwb-pkg__count"<?php echo $qty && 'fidan' === $o['unit'] ? ' data-flxwb-count-label="' . esc_attr( (string) $n ) . '"' : ''; ?>><?php echo esc_html( $qty && 'fidan' === $o['unit'] ? sprintf( /* translators: %s: number of saplings */ _n( '%s Sapling', '%s Saplings', $n * $units, 'flx-donate' ), number_format_i18n( $n * $units ) ) : ( $isu && 'fidan' === $o['unit'] ? sprintf( /* translators: %s: number of saplings */ _n( '%s Sapling', '%s Saplings', $n, 'flx-donate' ), number_format_i18n( $n ) ) : $o['label'] ) ); ?></span>
					<?php if ( $qty ) : ?>
					<span class="flxwb-pkg__price" data-flxwb-line="<?php echo esc_attr( $o['key'] ); ?>" aria-live="polite"><?php echo wp_kses_post( wc_price( FLXB_Types::option_price( $t, $o, $money ) * $units ) ); ?></span>
					<span class="flxwb-stepper flxwb-stepper--inline" role="group" aria-label="<?php esc_attr_e( 'Quantity', 'flx-donate' ); ?>">
						<button type="button" class="flxwb-stepper__btn" data-flxwb-step="-1" aria-controls="<?php echo esc_attr( $cid ); ?>" aria-label="<?php esc_attr_e( 'Fewer', 'flx-donate' ); ?>"<?php echo $units <= 1 ? ' aria-disabled="true"' : ''; ?>><?php echo FLXB_WC::icon( 'minus' ); // phpcs:ignore WordPress.Security.EscapeOutput ?></button>
						<input type="number" id="<?php echo esc_attr( $cid ); ?>" name="u[<?php echo esc_attr( $id ); ?>]" min="1" max="<?php echo esc_attr( $d['max'] ); ?>" step="1" inputmode="numeric" value="<?php echo esc_attr( $units ); ?>" aria-label="<?php echo esc_attr( 'fidan' === $o['unit'] ? __( 'Number of saplings', 'flx-donate' ) : __( 'Quantity', 'flx-donate' ) ); ?>" data-flxwb-count>
						<button type="button" class="flxwb-stepper__btn" data-flxwb-step="1" aria-controls="<?php echo esc_attr( $cid ); ?>" aria-label="<?php esc_attr_e( 'More', 'flx-donate' ); ?>"<?php echo $units >= $d['max'] ? ' aria-disabled="true"' : ''; ?>><?php echo FLXB_WC::icon( 'plus' ); // phpcs:ignore WordPress.Security.EscapeOutput ?></button>
					</span>
					<?php else : ?>
					<span class="flxwb-pkg__price"><?php echo wp_kses_post( wc_price( FLXB_Types::option_price( $t, $o, $money ) ) ); ?></span>
					<?php endif; ?>
					<span class="flxwb-pkg__tick" aria-hidden="true"><?php echo FLXB_WC::icon( 'check' ); // phpcs:ignore WordPress.Security.EscapeOutput ?></span>
				</label>
				<?php
				/* 2.21.0: quick quantity buttons under a quantity option (filter flxb_quick_qty; empty list = none). */
				$quick = $qty ? array_values( array_filter( array_map( 'intval', (array) apply_filters( 'flxb_quick_qty', array( 3, 5, 10, 25, 50 ), $t, $o ) ), static function ( $q ) use ( $d ) { return $q >= 1 && $q <= (int) $d['max']; } ) ) : array();
				if ( count( $quick ) > 1 ) :
					?>
				<div class="flxwb-quick" role="group" aria-label="<?php esc_attr_e( 'Quick choice', 'flx-donate' ); ?>">
					<?php foreach ( $quick as $qn ) : ?>
					<button type="button" data-flxwb-quick="<?php echo esc_attr( (string) $qn ); ?>" aria-controls="<?php echo esc_attr( $cid ); ?>" aria-pressed="<?php echo (int) $units === $qn ? 'true' : 'false'; ?>"><?php echo esc_html( number_format_i18n( $qn * $n ) ); ?></button>
					<?php endforeach; ?>
				</div>
				<?php endif; ?>
			<?php endforeach; ?>
			<?php if ( $t['allow_free'] ) : ?>
				<label class="flxwb-pkg flxwb-pkg--free">
					<input type="radio" name="<?php echo esc_attr( $name_o ); ?>" value="__free" <?php checked( $sel, '__free' ); ?>>
					<span class="flxwb-pkg__icon"><?php echo FLXB_WC::icon( 'heart' ); // phpcs:ignore WordPress.Security.EscapeOutput ?></span>
					<span class="flxwb-pkg__count"><?php esc_html_e( 'Another amount', 'flx-donate' ); ?></span>
					<span class="flxwb-pkg__tick" aria-hidden="true"><?php echo FLXB_WC::icon( 'check' ); // phpcs:ignore WordPress.Security.EscapeOutput ?></span>
				</label>
			<?php endif; ?>
			</div>
			</div>
		</fieldset>
			<?php
		elseif ( $t['allow_free'] ) :
			echo '<input type="hidden" name="' . esc_attr( $name_o ) . '" value="__free">';
		endif;
		if ( $t['allow_free'] ) :
			$min = FLXB_Types::min_amount_in( $t, $money );
			?>
		<fieldset class="flxwb-fs flxwb-free" data-flxwb-free<?php echo $cards && '__free' !== $sel ? ' hidden' : ''; ?>>
			<legend class="flxwb-legend"><?php esc_html_e( 'Donation amount', 'flx-donate' ); ?></legend>
			<?php if ( $presets ) : ?>
			<div class="flxwb-chips">
				<?php foreach ( $presets as $a ) : ?>
				<button type="button" class="flxwb-chip" data-flxwb-amount="<?php echo esc_attr( $a ); ?>"><?php echo wp_kses_post( wc_price( $a ) ); ?></button>
				<?php endforeach; ?>
			</div>
			<?php endif; ?>
			<label class="flxwb-field flxwb-field--amount">
				<span class="flxwb-field__label"><?php echo $presets ? esc_html__( 'Or write another amount', 'flx-donate' ) : esc_html__( 'Amount', 'flx-donate' ); ?></span>
				<span class="flxwb-money">
					<input type="text" name="a[<?php echo esc_attr( $id ); ?>]" inputmode="decimal" autocomplete="off" value="<?php echo esc_attr( (string) ( $old['a'][ $id ] ?? '' ) ); ?>" placeholder="<?php echo esc_attr( wc_format_localized_price( $presets ? $presets[0] : max( $min, 100 ) ) ); ?>" data-flxwb-amount-input>
					<span class="flxwb-money__sym"><?php echo esc_html( html_entity_decode( get_woocommerce_currency_symbol(), ENT_QUOTES, 'UTF-8' ) ); ?></span>
				</span>
				<?php if ( $min > 0 ) : ?>
				<span class="flxwb-field__help"><?php /* translators: %s: lowest amount */ echo esc_html( sprintf( __( 'At least%s', 'flx-donate' ), FLXB_WC::money_text( $min ) ) ); ?></span>
				<?php endif; ?>
			</label>
		</fieldset>
			<?php
		endif;
	}

	/**
	 * 2.0.1: names per share and the proxy (vekâlet) consent for types that require a proxy.
	 * 2.16.0: "For myself / On behalf of someone else"; someone else = one card per share with a name and
	 * (Settings › Appearance) a phone number; "Apply to all" copies the first card to the others.
	 */
	private static function proxy_fields( array $t, array $old, $current = true, $phones = true ) {
		$id     = (int) $t['id'];
		$text   = flxb_proxy_text();
		$names  = $old['owners'][ $id ] ?? array();
		$names  = is_array( $names ) ? array_values( $names ) : flxb_split_names( (string) $names );
		$nums   = isset( $old['owner_phones'][ $id ] ) && is_array( $old['owner_phones'][ $id ] ) ? array_values( $old['owner_phones'][ $id ] ) : array();
		$mode   = isset( $old['owner_mode'][ $id ] ) ? ( 'other' === $old['owner_mode'][ $id ] ? 'other' : 'self' ) : ( array_filter( array_map( 'trim', array_map( 'strval', $names ) ) ) ? 'other' : 'self' );
		$shares = max( 1, count( $names ), absint( $old['u'][ $id ] ?? ( isset( $_GET['flxb_qty'] ) ? absint( $_GET['flxb_qty'] ) : 1 ) ) ); // phpcs:ignore WordPress.Security.NonceVerification
		?>
		<fieldset class="flxwb-fs flxwb-proxy" data-flxwb-proxy="<?php echo esc_attr( $id ); ?>"<?php echo $current ? '' : ' hidden'; ?>>
			<legend class="flxwb-legend"><?php esc_html_e( 'On whose behalf?', 'flx-donate' ); ?></legend>
			<div class="flxwb-seg" role="radiogroup" aria-label="<?php esc_attr_e( 'On whose behalf?', 'flx-donate' ); ?>">
				<label class="flxwb-seg__opt"><input type="radio" name="owner_mode[<?php echo esc_attr( $id ); ?>]" value="self" <?php checked( $mode, 'self' ); ?> data-flxwb-owner-mode><span><?php echo FLXB_WC::icon( 'user' ); // phpcs:ignore WordPress.Security.EscapeOutput ?><?php esc_html_e( 'For myself', 'flx-donate' ); ?></span></label>
				<label class="flxwb-seg__opt"><input type="radio" name="owner_mode[<?php echo esc_attr( $id ); ?>]" value="other" <?php checked( $mode, 'other' ); ?> data-flxwb-owner-mode><span><?php echo FLXB_WC::icon( 'gift' ); // phpcs:ignore WordPress.Security.EscapeOutput ?><?php esc_html_e( 'On behalf of someone else', 'flx-donate' ); ?></span></label>
			</div>
			<p class="flxwb-field__help" data-flxwb-self-help<?php echo 'self' === $mode ? '' : ' hidden'; ?>><?php esc_html_e( 'Given in your name, as you write it at the checkout.', 'flx-donate' ); ?></p>
			<div class="flxwb-shares" data-flxwb-shares<?php echo 'other' === $mode ? '' : ' hidden'; ?>>
				<?php
				for ( $i = 0; $i < $shares; $i++ ) {
					echo self::share_card( $id, $i, (string) ( $names[ $i ] ?? '' ), (string) ( $nums[ $i ] ?? '' ), $phones ); // phpcs:ignore WordPress.Security.EscapeOutput
				}
				?>
				<p class="flxwb-field__help"><?php echo $phones ? esc_html__( 'Shares without a name are given in the name from the checkout. With a phone number we can also send that person the news on WhatsApp.', 'flx-donate' ) : esc_html__( 'Shares without a name are given in the name from the checkout.', 'flx-donate' ); ?></p>
			</div>
			<template data-flxwb-share-tpl><?php echo self::share_card( $id, '__N__', '', '', $phones ); // phpcs:ignore WordPress.Security.EscapeOutput ?></template>
			<?php if ( $text ) : ?>
			<details class="flxwb-proxy__text">
				<summary><?php /* translators: %s: version */ echo esc_html( sprintf( __( 'Read the proxy (vekâlet) text (version%s)', 'flx-donate' ), number_format_i18n( (int) $text['version'] ) ) ); ?></summary>
				<div><?php echo wp_kses_post( wpautop( (string) $text['text'] ) ); ?></div>
			</details>
			<label class="flxwb-check flxwb-proxy__ok"><input type="checkbox" name="proxy[<?php echo esc_attr( $id ); ?>]" value="<?php echo esc_attr( (int) $text['version'] ); ?>" required data-flxwb-consent <?php checked( ! empty( $old['proxy'][ $id ] ) ); ?>> <?php esc_html_e( 'I give my proxy (vekâlet) for the sacrifice according to this text.', 'flx-donate' ); ?></label>
			<?php else : ?>
			<p class="flxwb-note flxwb-note--err"><?php esc_html_e( 'This donation needs a proxy (vekâlet) consent, but it is not set up yet. Please contact the site administrator.', 'flx-donate' ); ?></p>
			<?php endif; ?>
		</fieldset>
		<?php
	}

	/** 2.16.0: one share card (name + phone). $i is 0-based, or "__N__" in the template. */
	private static function share_card( $type_id, $i, $name, $phone, $phones ) {
		$no  = '__N__' === $i ? '__NO__' : number_format_i18n( (int) $i + 1 );
		$nid = 'flxwb-own-' . $type_id . '-' . $i;
		/* translators: %s: share number */
		$h  = '<div class="flxwb-share" data-flxwb-share><div class="flxwb-share__head"><span class="flxwb-share__no" data-flxwb-share-no>' . esc_html( sprintf( __( 'Share%s', 'flx-donate' ), $no ) ) . '</span>';
		$h .= '<button type="button" class="flxwb-share__all" data-flxwb-apply-all hidden>' . FLXB_WC::icon( 'copy' ) . '<span>' . esc_html__( 'Apply to all', 'flx-donate' ) . '</span></button></div>';
		$h .= '<div class="flxwb-share__fields' . ( $phones ? '' : ' is-single' ) . '"><label class="flxwb-field" for="' . esc_attr( $nid ) . '"><span class="flxwb-field__label">' . esc_html__( 'Name', 'flx-donate' ) . '</span><input type="text" id="' . esc_attr( $nid ) . '" name="owners[' . esc_attr( $type_id ) . '][]" maxlength="80" autocomplete="off" value="' . esc_attr( $name ) . '" placeholder="' . esc_attr__( 'e.g. Ali Yılmaz', 'flx-donate' ) . '" data-flxwb-owner-name></label>';
		if ( $phones ) {
			$h .= '<div class="flxwb-field"><span class="flxwb-field__label">' . esc_html__( 'Phone (optional)', 'flx-donate' ) . '</span>' . self::phone_input( 'owner_phones[' . $type_id . '][]', $phone, array( 'data-flxwb-owner-phone' => '' ) ) . '</div>';
		}
		return $h . '</div></div>';
	}
}
