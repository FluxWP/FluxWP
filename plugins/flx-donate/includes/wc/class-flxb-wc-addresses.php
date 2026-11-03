<?php
/**
 * Address book (2.0.3): a signed-in customer keeps several addresses. My Account › Addresses lists
 * them (add, delete, make default); the checkout shows a wide drop-down above the billing address
 * with the saved addresses and "Add a new address". A new address used at the checkout is saved
 * to the book automatically.
 *
 * The checkout only shows it when it asks for an address (a donation-only cart asks for none).
 *
 * GPL v2 or later.
 *
 * @package FLX_Bagis
 */

defined( 'ABSPATH' ) || exit;

class FLXB_WC_Addresses {

	const META  = 'flxb_addresses';
	const MAX   = 20;
	const NONCE = 'flxb_addr';

	public static function init() {
		if ( ! apply_filters( 'flxb_wc_address_book', true ) ) {
			return;
		}
		add_action( 'template_redirect', array( __CLASS__, 'handle_post' ) );
		add_action( 'woocommerce_account_edit-address_endpoint', array( __CLASS__, 'account_section' ), 20 );
		add_action( 'wp_enqueue_scripts', array( __CLASS__, 'enqueue' ), 20 );
		add_action( 'woocommerce_before_checkout_billing_form', array( __CLASS__, 'classic_picker' ) );
		add_action( 'woocommerce_checkout_order_processed', array( __CLASS__, 'remember_order' ), 20, 3 );
		add_action( 'woocommerce_store_api_checkout_order_processed', array( __CLASS__, 'remember_order' ), 20 );
	}

	/** Address keys kept in the book (same names as WooCommerce billing fields without the prefix). */
	public static function keys() {
		return array( 'first_name', 'last_name', 'company', 'address_1', 'address_2', 'city', 'state', 'postcode', 'country', 'phone' );
	}

	/* ------------------------------------------------------------------
	 * Storage
	 * ------------------------------------------------------------------ */

	/**
	 * Saved addresses of a user, keyed by id; 'default' => true on the default one. The book is seeded once
	 * from the WooCommerce billing address, so changes typed at the checkout never rewrite saved entries.
	 */
	public static function all( $user_id = 0 ) {
		$user_id = $user_id ? (int) $user_id : get_current_user_id();
		if ( ! $user_id ) {
			return array();
		}
		$book = get_user_meta( $user_id, self::META, true );
		$book = is_array( $book ) ? $book : array();
		if ( ! $book ) {
			$billing = self::from_customer( $user_id );
			if ( self::is_complete( $billing ) ) {
				$billing['id'] = self::new_id();
				$book          = array( $billing );
				update_user_meta( $user_id, self::META, $book );
				update_user_meta( $user_id, self::META . '_default', $billing['id'] );
			}
		}
		$def = (string) get_user_meta( $user_id, self::META . '_default', true );
		$out = array();
		foreach ( $book as $a ) {
			$a = self::clean( (array) $a );
			if ( '' !== $a['id'] && ! isset( $out[ $a['id'] ] ) ) {
				$out[ $a['id'] ] = $a;
			}
		}
		if ( $out && ! isset( $out[ $def ] ) ) {
			$def = (string) key( $out );
		}
		foreach ( $out as $id => $a ) {
			$out[ $id ]['default'] = ( (string) $id === $def );
		}
		return $out;
	}

	private static function new_id() {
		return strtolower( wp_generate_password( 10, false, false ) );
	}

	private static function from_customer( $user_id ) {
		$a = array( 'id' => '', 'label' => '' );
		foreach ( self::keys() as $k ) {
			$a[ $k ] = (string) get_user_meta( $user_id, 'billing_' . $k, true );
		}
		return $a;
	}

	private static function save_book( $user_id, array $book ) {
		$rows = array();
		foreach ( $book as $a ) {
			$a = self::clean( $a );
			if ( '' !== $a['id'] ) {
				$rows[] = $a;
			}
		}
		update_user_meta( $user_id, self::META, array_slice( $rows, -self::MAX ) );
	}

	private static function set_billing( $user_id, array $a ) {
		$customer = new WC_Customer( $user_id );
		foreach ( self::keys() as $k ) {
			$m = 'set_billing_' . $k;
			if ( is_callable( array( $customer, $m ) ) ) {
				$customer->$m( (string) ( $a[ $k ] ?? '' ) );
			}
		}
		$customer->save();
	}

	public static function clean( array $a ) {
		$out = array(
			'id'    => preg_replace( '/[^a-z0-9]/', '', (string) ( $a['id'] ?? '' ) ),
			'label' => mb_substr( sanitize_text_field( (string) ( $a['label'] ?? '' ) ), 0, 40 ),
		);
		foreach ( self::keys() as $k ) {
			$out[ $k ] = mb_substr( sanitize_text_field( (string) ( $a[ $k ] ?? '' ) ), 0, 120 );
		}
		$out['country'] = strtoupper( substr( $out['country'], 0, 2 ) );
		return $out;
	}

	public static function is_complete( array $a ) {
		return '' !== trim( (string) ( $a['address_1'] ?? '' ) ) && '' !== trim( (string) ( $a['city'] ?? '' ) );
	}

	private static function norm( array $a ) {
		$parts = array();
		foreach ( array( 'first_name', 'last_name', 'address_1', 'address_2', 'city', 'state', 'postcode', 'country' ) as $k ) {
			$parts[] = preg_replace( '/\s+/', ' ', function_exists( 'mb_strtolower' ) ? mb_strtolower( trim( (string) ( $a[ $k ] ?? '' ) ) ) : strtolower( trim( (string) ( $a[ $k ] ?? '' ) ) ) );
		}
		return implode( '|', $parts );
	}

	public static function same( array $a, array $b ) {
		return self::norm( $a ) === self::norm( $b );
	}

	/** Add an address unless the user already has it. Returns its id ('' when incomplete). */
	public static function add( $user_id, array $a ) {
		$a = self::clean( $a );
		if ( ! self::is_complete( $a ) ) {
			return '';
		}
		$all = self::all( $user_id );
		foreach ( $all as $id => $old ) {
			if ( self::same( $old, $a ) ) {
				return (string) $id;
			}
		}
		$a['id'] = self::new_id();
		$all[]   = $a;
		self::save_book( $user_id, $all );
		if ( 1 === count( $all ) ) {
			update_user_meta( $user_id, self::META . '_default', $a['id'] );
			self::set_billing( $user_id, $a );
		}
		return $a['id'];
	}

	/** Make a saved address the default; it also becomes the WooCommerce billing address. */
	public static function make_default( $user_id, $id ) {
		$all = self::all( $user_id );
		if ( ! isset( $all[ $id ] ) ) {
			return false;
		}
		update_user_meta( $user_id, self::META . '_default', (string) $id );
		self::set_billing( $user_id, $all[ $id ] );
		return true;
	}

	public static function delete( $user_id, $id ) {
		$all = self::all( $user_id );
		if ( ! isset( $all[ $id ] ) || ! empty( $all[ $id ]['default'] ) ) {
			return false;
		}
		unset( $all[ $id ] );
		self::save_book( $user_id, $all );
		return true;
	}

	/** One line for the drop-down: "Home · Ayşe Yılmaz, Moda Cd. 12, Kadıköy / İstanbul". */
	public static function summary( array $a ) {
		$c      = WC()->countries;
		$states = $c ? $c->get_states( $a['country'] ) : array();
		$state  = is_array( $states ) && isset( $states[ $a['state'] ] ) ? $states[ $a['state'] ] : $a['state'];
		$place  = implode( ' / ', array_filter( array( $a['city'], $state ) ) );
		$name   = trim( $a['first_name'] . ' ' . $a['last_name'] );
		$line   = implode( ', ', array_filter( array( $name, trim( $a['address_1'] . ' ' . $a['address_2'] ), $place ) ) );
		$label  = '' !== $a['label'] ? $a['label'] : ( ! empty( $a['default'] ) ? __( 'Default address', 'flx-donate' ) : '' );
		return '' !== $label ? $label . ' · ' . $line : $line;
	}

	/* ------------------------------------------------------------------
	 * My Account › Addresses
	 * ------------------------------------------------------------------ */

	public static function handle_post() {
		if ( empty( $_POST['flxb_addr_action'] ) || ! is_user_logged_in() ) { // phpcs:ignore WordPress.Security.NonceVerification
			return;
		}
		if ( ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['_flxb_addr'] ?? '' ) ), self::NONCE ) ) {
			wc_add_notice( __( 'The page has expired. Please try again.', 'flx-donate' ), 'error' );
			return;
		}
		$uid    = get_current_user_id();
		$action = sanitize_key( wp_unslash( $_POST['flxb_addr_action'] ) );
		$id     = preg_replace( '/[^a-z0-9]/', '', (string) wp_unslash( $_POST['flxb_addr_id'] ?? '' ) );
		if ( 'add' === $action ) {
			$in = array();
			foreach ( array_merge( array( 'label' ), self::keys() ) as $k ) {
				$in[ $k ] = wp_unslash( $_POST[ 'flxb_' . $k ] ?? '' );
			}
			if ( ! self::is_complete( self::clean( $in ) ) || '' === trim( (string) $in['first_name'] ) ) {
				wc_add_notice( __( 'Please fill in the name, address and city.', 'flx-donate' ), 'error' );
				$GLOBALS['flxb_addr_old'] = $in;
				return;
			}
			self::add( $uid, $in );
			wc_add_notice( __( 'Address saved.', 'flx-donate' ) );
		} elseif ( 'delete' === $action ) {
			self::delete( $uid, $id ) && wc_add_notice( __( 'Address deleted.', 'flx-donate' ) );
		} elseif ( 'default' === $action ) {
			self::make_default( $uid, $id ) && wc_add_notice( __( 'Default address changed.', 'flx-donate' ) );
		}
		wp_safe_redirect( wc_get_account_endpoint_url( 'edit-address' ) );
		exit;
	}

	/** Below WooCommerce's billing / shipping cards on the Addresses page (not on the edit forms). */
	public static function account_section( $type = '' ) {
		if ( '' !== (string) $type || ! is_user_logged_in() ) {
			return;
		}
		$all = self::all();
		$old = (array) ( $GLOBALS['flxb_addr_old'] ?? array() );
		$c   = WC()->countries;
		?>
<section class="flxb-ab" id="adres-defteri">
	<h2 class="flxb-ab__title"><?php esc_html_e( 'Address book', 'flx-donate' ); ?></h2>
	<p class="flxb-ab__help"><?php esc_html_e( 'Saved addresses are offered in a list at the checkout. The default one is filled in automatically.', 'flx-donate' ); ?></p>
	<?php if ( $all ) : ?>
	<ul class="flxb-ab__list">
		<?php foreach ( $all as $id => $a ) : ?>
		<li class="flxb-ab__item<?php echo $a['default'] ? ' is-default' : ''; ?>">
			<div class="flxb-ab__text">
				<strong><?php echo esc_html( '' !== $a['label'] ? $a['label'] : trim( $a['first_name'] . ' ' . $a['last_name'] ) ); ?></strong>
				<?php if ( $a['default'] ) : ?><span class="flxb-ab__badge"><?php esc_html_e( 'Default', 'flx-donate' ); ?></span><?php endif; ?>
				<address><?php echo wp_kses_post( $c->get_formatted_address( array_merge( $a, array( 'company' => $a['company'] ) ) ) ); ?></address>
			</div>
			<?php if ( ! $a['default'] ) : ?>
			<div class="flxb-ab__actions">
				<form method="post"><?php wp_nonce_field( self::NONCE, '_flxb_addr' ); ?><input type="hidden" name="flxb_addr_id" value="<?php echo esc_attr( $id ); ?>">
					<button type="submit" name="flxb_addr_action" value="default" class="button flxb-ab__btn"><?php esc_html_e( 'Make default', 'flx-donate' ); ?></button>
					<button type="submit" name="flxb_addr_action" value="delete" class="button flxb-ab__btn flxb-ab__btn--del" onclick="return confirm(this.dataset.confirm)" data-confirm="<?php esc_attr_e( 'Delete this address?', 'flx-donate' ); ?>"><?php esc_html_e( 'Delete', 'flx-donate' ); ?></button>
				</form>
			</div>
			<?php endif; ?>
		</li>
		<?php endforeach; ?>
	</ul>
	<?php endif; ?>
	<details class="flxb-ab__new"<?php echo $old || ! $all ? ' open' : ''; ?>>
		<summary class="button"><?php esc_html_e( '+ Add a new address', 'flx-donate' ); ?></summary>
		<form method="post" class="flxb-ab__form">
			<?php wp_nonce_field( self::NONCE, '_flxb_addr' ); ?>
			<input type="hidden" name="flxb_addr_action" value="add">
			<?php
			$country = (string) ( $old['country'] ?? ( $c->get_base_country() ? $c->get_base_country() : 'TR' ) );
			woocommerce_form_field( 'flxb_label', array( 'label' => __( 'Address name', 'flx-donate' ), 'placeholder' => __( 'e.g. Home, Office', 'flx-donate' ), 'class' => array( 'form-row-wide' ) ), (string) ( $old['label'] ?? '' ) );
			foreach ( $c->get_address_fields( $country, 'flxb_' ) as $key => $field ) {
				woocommerce_form_field( $key, $field, (string) ( $old[ substr( $key, 5 ) ] ?? ( 'flxb_country' === $key ? $country : '' ) ) );
			}
			?>
			<p><button type="submit" class="button flxb-ab__save"><?php esc_html_e( 'Save address', 'flx-donate' ); ?></button></p>
		</form>
	</details>
</section>
		<?php
	}

	/* ------------------------------------------------------------------
	 * Checkout drop-down
	 * ------------------------------------------------------------------ */

	private static function picker_data() {
		$rows = array();
		foreach ( self::all() as $id => $a ) {
			$rows[] = array(
				'id'      => (string) $id,
				'text'    => self::summary( $a ),
				'default' => ! empty( $a['default'] ),
				'address' => array_intersect_key( $a, array_flip( self::keys() ) ),
			);
		}
		return $rows;
	}

	public static function enqueue() {
		if ( ! is_user_logged_in() || ! function_exists( 'is_account_page' ) ) {
			return;
		}
		if ( is_account_page() && is_wc_endpoint_url( 'edit-address' ) ) {
			wp_enqueue_style( 'flxb-addresses', FLXB_URL . 'assets/wc/addresses.css', array(), FLXB_VERSION );
			wp_enqueue_script( 'wc-country-select' );
			wp_enqueue_script( 'wc-address-i18n' );
			return;
		}
		if ( ! is_checkout() || is_wc_endpoint_url() || FLXB_WC_Cart::trim_fields() ) {
			return; // a donation-only checkout asks for no address
		}
		$rows = self::picker_data();
		if ( ! $rows ) {
			return;
		}
		wp_enqueue_style( 'flxb-addresses', FLXB_URL . 'assets/wc/addresses.css', array(), FLXB_VERSION );
		wp_enqueue_script( 'flxb-addresses', FLXB_URL . 'assets/wc/addresses.js', array(), FLXB_VERSION, true );
		wp_localize_script(
			'flxb-addresses',
			'FLXBAddr',
			array(
				'rows'    => $rows,
				'label'   => __( 'Saved address', 'flx-donate' ),
				'newText' => __( '+ Add a new address', 'flx-donate' ),
				'newHelp' => __( 'Write the new address below; it is saved to your address book with the order.', 'flx-donate' ),
				'manage'  => __( 'Manage addresses', 'flx-donate' ),
				'url'     => wc_get_account_endpoint_url( 'edit-address' ) . '#adres-defteri',
			)
		);
	}

	/** Classic checkout: the list is printed server side, addresses.js fills the inputs. */
	public static function classic_picker() {
		if ( is_user_logged_in() && ! FLXB_WC_Cart::trim_fields() && self::picker_data() ) {
			echo '<div class="flxb-addr" data-flxb-addr-classic></div>';
		}
	}

	/** Save the order's billing address to the customer's book (new address picked at the checkout). */
	public static function remember_order( $order, $posted = null, $maybe_order = null ) {
		$order = $order instanceof WC_Order ? $order : wc_get_order( $order );
		if ( ! $order || ! $order->get_customer_id() ) {
			return;
		}
		$a = array( 'label' => '' );
		foreach ( self::keys() as $k ) {
			$g       = 'get_billing_' . $k;
			$a[ $k ] = is_callable( array( $order, $g ) ) ? (string) $order->$g() : '';
		}
		/* Addresses filled in for a donation-only checkout (no address asked) are not the customer's. */
		if ( $order->get_meta( '_flxb_address_filled' ) ) {
			return;
		}
		self::add( (int) $order->get_customer_id(), $a );
	}
}
