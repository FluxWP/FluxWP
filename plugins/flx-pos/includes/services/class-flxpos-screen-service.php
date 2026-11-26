<?php
/**
 * Flux POS screen service: every WooCommerce payment method (switch, order, preview data), the provider
 * catalog, POS accounts, transactions and settings. GraphQL resolvers call only this class.
 *
 * Methods: the order is WooCommerce's own (option woocommerce_gateway_order) and the switch is the
 * method's own "enabled" setting, so the WooCommerce Payments screen and this screen always agree.
 * The one-card rule belongs to Flux Donate when it is active (its card_method setting), otherwise to
 * Flux POS.
 *
 * @package FLX_POS
 */

defined( 'ABSPATH' ) || exit;

final class FLXPOS_Screen_Service {

	const CAP       = 'manage_woocommerce';
	const CACHE_TAG = 'flx-pos:screen';

	/* ------------------------------------------------------------------ */
	/* Readers for legacy arrays (mixed → typed).                          */
	/* ------------------------------------------------------------------ */

	/** @param mixed $v */
	private static function s( $v ): string {
		return is_scalar( $v ) ? (string) $v : '';
	}

	/** @param mixed $v */
	private static function i( $v ): int {
		return is_numeric( $v ) ? (int) $v : 0;
	}

	/** @param mixed $v */
	private static function f( $v ): float {
		return is_numeric( $v ) ? (float) $v : 0.0;
	}

	/**
	 * @param mixed $v
	 * @return array<array-key,mixed>
	 */
	private static function a( $v ): array {
		return is_array( $v ) ? $v : array();
	}

	/** Who may use the screen. */
	public static function can(): bool {
		return current_user_can( self::CAP ) || current_user_can( 'manage_options' );
	}

	public static function changed(): void {
		if ( function_exists( 'flx_gql_purge' ) ) {
			flx_gql_purge( array( self::CACHE_TAG ) );
		}
	}

	/* ------------------------------------------------------------------ */
	/* Payment methods                                                     */
	/* ------------------------------------------------------------------ */

	/** @return array<string,WC_Payment_Gateway> */
	private static function gateways(): array {
		if ( ! function_exists( 'WC' ) ) {
			return array();
		}
		$out = array();
		foreach ( (array) WC()->payment_gateways()->payment_gateways() as $id => $gw ) {
			if ( $gw instanceof WC_Payment_Gateway ) {
				$out[ (string) $id ] = $gw;
			}
		}
		return $out;
	}

	/**
	 * Logo addresses inside the method's icon HTML.
	 *
	 * @return list<string>
	 */
	private static function icons( WC_Payment_Gateway $gw ): array {
		$html = self::s( $gw->get_icon() );
		if ( '' === $html || ! preg_match_all( '/<img[^>]+src=["\']([^"\']+)["\']/i', $html, $m ) ) {
			return array();
		}
		$out = array();
		foreach ( $m[1] as $src ) {
			$url = esc_url_raw( html_entity_decode( $src, ENT_QUOTES ) );
			if ( '' !== $url ) {
				$out[] = $url;
			}
		}
		return array_slice( array_values( array_unique( $out ) ), 0, 6 );
	}

	/** @return list<FLXPOS_Cap> */
	private static function caps( WC_Payment_Gateway $gw ): array {
		$labels = self::a( FLXPOS_Methods::cap_labels() );
		$help   = self::a( FLXPOS_Methods::cap_help() );
		$caps   = self::a( FLXPOS_Methods::caps( $gw ) );
		$out    = array();
		foreach ( $labels as $k => $l ) {
			$c     = self::a( $caps[ $k ] ?? array() );
			$state = self::s( $c[0] ?? 'unknown' );
			$out[] = new FLXPOS_Cap( (string) $k, self::s( $l ), self::s( $help[ $k ] ?? '' ), in_array( $state, array( 'yes', 'no', 'page', 'flux', 'unknown' ), true ) ? $state : 'unknown', self::s( $c[1] ?? '' ) );
		}
		return $out;
	}

	private static function hidden( string $id ): bool {
		return (bool) FLXPOS_Methods::hidden( $id );
	}

	public static function methods(): FLXPOS_Methods_Info {
		$list = array();
		$n    = 0;
		foreach ( self::gateways() as $id => $gw ) {
			$on     = 'yes' === $gw->enabled;
			$flux   = 0 === strpos( $id, 'flxpos_' );
			$list[] = new FLXPOS_Method(
				$id,
				wp_strip_all_tags( self::s( $gw->get_title() ) ),
				wp_strip_all_tags( self::s( $gw->get_method_title() ) ),
				trim( wp_strip_all_tags( self::s( $gw->get_description() ) ) ),
				$on,
				$n++,
				self::icons( $gw ),
				$flux,
				$on && self::hidden( $id ),
				(bool) $gw->has_fields,
				in_array( $id, array( 'bacs', 'cheque', 'cod', 'flxb_havale' ), true ),
				$flux ? self::s( flxpos_admin_url( array( 'tab' => 'accounts' ) ) ) : admin_url( 'admin.php?page=wc-settings&tab=checkout&section=' . rawurlencode( $id ) ),
				self::caps( $gw )
			);
		}
		$current   = self::s( flxpos_setting( 'card_method' ) );
		$choices   = array( new FLXPOS_Option( 'auto', __( 'Otomatik', 'flx-pos' ) ) );
		$choices[] = new FLXPOS_Option( 'flxpos', 'Flux POS' );
		foreach ( self::a( FLXPOS_Methods::third_party_cards() ) as $k => $l ) {
			$choices[] = new FLXPOS_Option( (string) $k, self::s( $l ) );
		}
		$choices[] = new FLXPOS_Option( 'all', __( 'Hepsini göster', 'flx-pos' ) );
		return new FLXPOS_Methods_Info(
			$list,
			(bool) FLXPOS_Methods::block_checkout(),
			'' !== $current ? $current : 'auto',
			'pos',
			$choices,
			function_exists( 'wc_get_checkout_url' ) ? self::s( wc_get_checkout_url() ) : '',
			function_exists( 'get_woocommerce_currency' ) ? self::s( get_woocommerce_currency() ) : 'TRY'
		);
	}

	/**
	 * Changes the order, the switches and the one-card rule. Unknown ids are skipped; ids missing from
	 * $order keep their place after the given ones.
	 *
	 * @param list<string>                               $order    Method ids in the new order (empty = unchanged).
	 * @param list<array{id:string,enabled:bool}>        $switches Switches to change.
	 * @param string|null                                $card     One-card rule (null = unchanged).
	 * @return true|WP_Error
	 */
	public static function update_methods( array $order, array $switches, ?string $card ) {
		$gws = self::gateways();
		if ( ! $gws ) {
			return new WP_Error( 'flxpos_wc', __( 'WooCommerce etkin değil.', 'flx-pos' ), array( 'graphql_code' => 'INVALID_INPUT' ) );
		}
		if ( $order ) {
			$ids  = array_values( array_unique( array_merge( array_values( array_intersect( $order, array_keys( $gws ) ) ), array_keys( $gws ) ) ) );
			$save = array();
			foreach ( $ids as $i => $id ) {
				$save[ $id ] = $i;
			}
			update_option( 'woocommerce_gateway_order', $save );
		}
		foreach ( $switches as $sw ) {
			$gw = $gws[ $sw['id'] ] ?? null;
			if ( ! $gw ) {
				continue;
			}
			if ( $sw['enabled'] && 0 === strpos( $gw->id, 'flxpos_card' ) && ! FLXPOS_Accounts::active() ) {
				return new WP_Error( 'flxpos_no_account', __( 'Kart ile öde yöntemini açmadan önce bir POS hesabı ekleyin.', 'flx-pos' ), array( 'graphql_code' => 'INVALID_INPUT', 'field' => 'switches' ) );
			}
			$gw->update_option( 'enabled', $sw['enabled'] ? 'yes' : 'no' );
		}
		if ( null !== $card ) {
			$card = preg_match( '/^[a-z0-9_-]{1,60}$/', $card ) ? $card : 'auto';
			$s                = self::a( flxpos_settings() );
			$s['card_method'] = $card;
			update_option( 'flxpos_settings', $s, false );
		}
		if ( function_exists( 'WC' ) ) {
			WC()->payment_gateways()->init(); // reload so the payload shows the new order and switches
		}
		self::changed();
		return true;
	}

	/* ------------------------------------------------------------------ */
	/* Providers and accounts                                              */
	/* ------------------------------------------------------------------ */

	/** @return list<FLXPOS_Provider> */
	public static function providers(): array {
		$labels = self::a( FLXPOS_Providers::mode_labels() );
		$out    = array();
		foreach ( self::a( FLXPOS_Providers::all() ) as $p ) {
			$p      = self::a( $p );
			$modes  = array();
			foreach ( self::a( $p['modes'] ?? array() ) as $k => $v ) {
				$key     = is_int( $k ) ? self::s( $v ) : (string) $k;
				$modes[] = new FLXPOS_Option( $key, self::s( $labels[ $key ] ?? $key ) );
			}
			$fields = array();
			foreach ( self::a( $p['fields'] ?? array() ) as $k => $f ) {
				$f        = self::a( $f );
				$fields[] = new FLXPOS_Field( (string) $k, self::s( $f['label'] ?? $k ), 'secret' === ( $f['type'] ?? '' ), self::s( $f['help'] ?? '' ), self::s( $f['default'] ?? '' ) );
			}
			$caps = array();
			foreach ( self::a( $p['caps'] ?? array() ) as $k => $v ) {
				if ( $v ) {
					$caps[] = (string) $k;
				}
			}
			$eps = array();
			foreach ( array_keys( self::a( self::a( $p['endpoints'] ?? array() )['live'] ?? array() ) ) as $k ) {
				$eps[] = new FLXPOS_Option( (string) $k, self::s( self::a( self::a( $p['endpoints'] )['live'] )[ $k ] ?? '' ) );
			}
			$driver = self::s( $p['driver'] ?? '' );
			$out[]  = new FLXPOS_Provider(
				self::s( $p['id'] ?? '' ),
				self::s( $p['name'] ?? '' ),
				in_array( $p['group'] ?? '', array( 'bank', 'psp', 'wallet', 'meal' ), true ) ? self::s( $p['group'] ) : 'psp',
				'' !== $driver && '' !== FLXPOS_Providers::driver_class( $driver ),
				$modes,
				$fields,
				array_values( array_map( array( __CLASS__, 's' ), self::a( $p['currencies'] ?? array() ) ) ),
				$caps,
				self::s( $p['notes'] ?? '' ),
				$eps,
				count( self::a( $p['test_cards'] ?? array() ) )
			);
		}
		return $out;
	}

	/** @param array<array-key,mixed> $a */
	private static function account_dto( array $a ): FLXPOS_Account_DTO {
		$pid   = self::s( $a['provider'] ?? '' );
		$p     = self::a( FLXPOS_Providers::get( $pid ) );
		$creds = array();
		$saved = self::a( $a['creds'] ?? array() );
		foreach ( self::a( $p['fields'] ?? array() ) as $k => $f ) {
			$f      = self::a( $f );
			$v      = self::s( $saved[ $k ] ?? '' );
			$secret = 'secret' === ( $f['type'] ?? '' );
			if ( $secret ) {
				$plain   = '' !== $v ? self::s( flxpos_decrypt( $v ) ) : '';
				$creds[] = new FLXPOS_Cred( (string) $k, '', '' !== $plain, strlen( $plain ) > 4 ? '••••' . substr( $plain, -4 ) : ( '' !== $plain ? '••••' : '' ) );
			} else {
				$creds[] = new FLXPOS_Cred( (string) $k, $v, '' !== $v, '' );
			}
		}
		foreach ( $saved as $k => $v ) {
			if ( 0 === strpos( (string) $k, 'url_' ) ) {
				$creds[] = new FLXPOS_Cred( (string) $k, self::s( $v ), '' !== self::s( $v ), '' );
			}
		}
		$inst = array();
		foreach ( self::a( $a['installments'] ?? array() ) as $n => $r ) {
			$inst[] = new FLXPOS_Installment( (int) $n, self::f( $r ) );
		}
		$id = self::s( $a['id'] ?? '' );
		return new FLXPOS_Account_DTO(
			$id,
			$pid,
			self::s( $p['name'] ?? $pid ),
			self::s( $p['group'] ?? 'psp' ),
			self::s( $a['label'] ?? '' ),
			self::s( FLXPOS_Accounts::label( $a ) ),
			! empty( $a['enabled'] ),
			! empty( $a['test'] ),
			self::s( $a['mode'] ?? '' ),
			$creds,
			array_values( array_map( array( __CLASS__, 's' ), self::a( $a['currencies'] ?? array() ) ) ),
			$inst,
			self::f( $a['inst_min'] ?? 0 ),
			self::s( $a['title'] ?? '' ),
			self::s( $a['display'] ?? '' ),
			'' !== $id ? self::s( FLXPOS_Flow::notify_url( $id ) ) : '',
			(bool) FLXPOS_Accounts::takes_card( $a )
		);
	}

	/** @return list<FLXPOS_Account_DTO> */
	public static function accounts(): array {
		$out = array();
		foreach ( self::a( FLXPOS_Accounts::all() ) as $a ) {
			$out[] = self::account_dto( self::a( $a ) );
		}
		return $out;
	}

	/**
	 * Adds or updates an account. Secret fields left empty keep the saved value.
	 *
	 * @param array<string,mixed> $in Account fields (id, provider, label, enabled, test, mode, creds, currencies, installments, inst_min, title, display).
	 * @return FLXPOS_Account_DTO|WP_Error
	 */
	public static function save_account( array $in ) {
		$id = FLXPOS_Accounts::save( $in );
		if ( is_wp_error( $id ) ) {
			$id->add_data( array( 'graphql_code' => 'INVALID_INPUT', 'field' => 'provider' ) );
			return $id;
		}
		$a = FLXPOS_Accounts::get( self::s( $id ) );
		self::changed();
		return self::account_dto( self::a( $a ) );
	}

	/** @return true|WP_Error */
	public static function delete_account( string $id ) {
		if ( ! FLXPOS_Accounts::get( $id ) ) {
			return new WP_Error( 'flxpos_account', __( 'POS hesabı bulunamadı.', 'flx-pos' ), array( 'graphql_code' => 'NOT_FOUND' ) );
		}
		FLXPOS_Accounts::delete( $id );
		self::changed();
		return true;
	}

	public static function test_account( string $id ): FLXPOS_Test_Result {
		$drv = FLXPOS_Accounts::driver( $id );
		if ( is_wp_error( $drv ) ) {
			return new FLXPOS_Test_Result( false, true, $drv->get_error_message() );
		}
		if ( ! $drv instanceof FLXPOS_Driver ) {
			return new FLXPOS_Test_Result( false, true, __( 'POS hesabı bulunamadı.', 'flx-pos' ) );
		}
		$t = $drv->test_connection();
		if ( is_wp_error( $t ) ) {
			return new FLXPOS_Test_Result( false, true, $t->get_error_message() );
		}
		if ( null === $t ) {
			return new FLXPOS_Test_Result( false, false, __( 'Bu sağlayıcıda bağlantı testi yok; test kartıyla küçük bir ödeme deneyin.', 'flx-pos' ) );
		}
		return new FLXPOS_Test_Result( true, true, __( 'Bağlantı başarılı, anahtarlar kabul edildi.', 'flx-pos' ) );
	}

	/* ------------------------------------------------------------------ */
	/* Settings                                                            */
	/* ------------------------------------------------------------------ */

	public static function settings(): FLXPOS_Settings_DTO {
		$s      = self::a( flxpos_settings() );
		$routes = array();
		foreach ( self::a( $s['currency_routes'] ?? array() ) as $c => $id ) {
			$routes[] = new FLXPOS_Option( (string) $c, self::s( $id ) );
		}
		$displays = array();
		foreach ( self::a( flxpos_display_modes() ) as $k => $l ) {
			$displays[] = new FLXPOS_Option( (string) $k, self::s( $l ) );
		}
		return new FLXPOS_Settings_DTO(
			self::s( $s['card_title'] ?? '' ),
			self::s( $s['card_desc'] ?? '' ),
			self::s( $s['card_account'] ?? '' ),
			$routes,
			self::s( $s['display'] ?? 'redirect' ),
			! empty( $s['show_brands'] ),
			! empty( $s['block_fallback'] ),
			! empty( $s['save_log'] ),
			$displays
		);
	}

	/**
	 * Partial update: keys left out stay as they are.
	 *
	 * @param array{card_title?:string,card_desc?:string,card_account?:string,routes?:list<array{value:string,label:string}>,display?:string,show_brands?:bool,block_fallback?:bool,save_log?:bool} $in Changes.
	 */
	public static function update_settings( array $in ): FLXPOS_Settings_DTO {
		$s = self::a( flxpos_settings() );
		foreach ( array( 'card_title', 'card_desc' ) as $k ) {
			if ( isset( $in[ $k ] ) ) {
				$s[ $k ] = sanitize_text_field( $in[ $k ] );
			}
		}
		if ( isset( $in['card_account'] ) ) {
			$s['card_account'] = sanitize_key( $in['card_account'] );
		}
		if ( isset( $in['routes'] ) ) {
			$r = array();
			foreach ( $in['routes'] as $row ) {
				if ( '' !== $row['label'] ) {
					$r[ strtoupper( sanitize_key( $row['value'] ) ) ] = sanitize_key( $row['label'] );
				}
			}
			$s['currency_routes'] = $r;
		}
		if ( isset( $in['display'] ) ) {
			$s['display'] = isset( self::a( flxpos_display_modes() )[ $in['display'] ] ) ? $in['display'] : 'redirect';
		}
		foreach ( array( 'show_brands', 'block_fallback', 'save_log' ) as $k ) {
			if ( isset( $in[ $k ] ) ) {
				$s[ $k ] = $in[ $k ] ? 1 : 0;
			}
		}
		update_option( 'flxpos_settings', $s, false );
		self::changed();
		return self::settings();
	}

	/* ------------------------------------------------------------------ */
	/* Transactions                                                        */
	/* ------------------------------------------------------------------ */

	/** @param array<array-key,mixed> $t */
	private static function txn_dto( array $t ): FLXPOS_Txn_DTO {
		$acc   = FLXPOS_Accounts::get( self::s( $t['account'] ?? '' ) );
		$oid   = self::i( $t['order_id'] ?? 0 );
		$order = $oid && function_exists( 'wc_get_order' ) ? wc_get_order( $oid ) : null;
		$st    = self::s( $t['status'] ?? 'pending' );
		$kind  = self::s( $t['kind'] ?? 'sale' );
		return new FLXPOS_Txn_DTO(
			self::i( $t['id'] ?? 0 ),
			self::s( $t['ref'] ?? '' ),
			self::s( $t['bank_ref'] ?? '' ),
			in_array( $kind, array( 'sale', 'refund', 'void' ), true ) ? $kind : 'sale',
			self::s( $t['account'] ?? '' ),
			$acc ? self::s( FLXPOS_Accounts::label( self::a( $acc ) ) ) : self::s( $t['provider'] ?? '' ),
			$oid,
			$order instanceof WC_Order ? self::s( $order->get_order_number() ) : '',
			$order instanceof WC_Order ? self::s( $order->get_edit_order_url() ) : '',
			self::f( $t['amount'] ?? 0 ),
			self::f( $t['refunded'] ?? 0 ),
			self::s( $t['currency'] ?? 'TRY' ),
			max( 1, self::i( $t['installment'] ?? 1 ) ),
			self::s( $t['card'] ?? '' ),
			in_array( $st, array( 'pending', 'paid', 'failed', 'refunded', 'partial', 'voided', 'review' ), true ) ? $st : 'pending',
			self::s( $t['message'] ?? '' ),
			! empty( $t['test'] ),
			(int) strtotime( self::s( $t['created'] ?? '' ) . ' UTC' ),
			'sale' === $kind && in_array( $st, array( 'paid', 'partial' ), true )
		);
	}

	/**
	 * One page of transactions, newest first.
	 *
	 * @param array{status?:string,account?:string,search?:string} $filter Filter.
	 * @return array{items:list<FLXPOS_Txn_DTO>,total:int}
	 */
	public static function txns( array $filter, int $offset, int $limit ): array {
		$limit = max( 1, min( 50, $limit ) );
		$res   = FLXPOS_Txn::query(
			array(
				'status'  => sanitize_key( $filter['status'] ?? '' ),
				'account' => sanitize_key( $filter['account'] ?? '' ),
				's'       => sanitize_text_field( $filter['search'] ?? '' ),
				'per'     => $limit,
				'page'    => (int) floor( max( 0, $offset ) / $limit ) + 1,
			)
		);
		$res   = self::a( $res );
		$items = array();
		foreach ( self::a( $res['rows'] ?? array() ) as $t ) {
			$items[] = self::txn_dto( self::a( $t ) );
		}
		return array(
			'items' => $items,
			'total' => self::i( $res['total'] ?? 0 ),
		);
	}

	/**
	 * Refunds a transaction (all when $amount is null). Orders refund through WooCommerce's Refund
	 * button too; both end in the same driver call.
	 *
	 * @return FLXPOS_Txn_DTO|WP_Error
	 */
	public static function refund( int $id, ?float $amount ) {
		$r = flxpos_refund( $id, $amount, __( 'Panelden iade', 'flx-pos' ) );
		if ( is_wp_error( $r ) ) {
			$r->add_data( array( 'graphql_code' => 'INVALID_INPUT', 'field' => 'amount' ) );
			return $r;
		}
		$t = FLXPOS_Txn::get( $id );
		self::changed();
		return self::txn_dto( self::a( $t ) );
	}
}
