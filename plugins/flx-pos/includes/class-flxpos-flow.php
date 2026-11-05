<?php
/**
 * Ödeme akışı: köprü (3D sayfasına geçiş), dönüş ve sunucu bildirimi.
 *
 *  1. Ödeme yöntemi bir işlem kaydı açar, kartı kasaya (FLXPOS_Vault) koyar ve ödeyeni köprüye gönderir:
 *     /?flxpos=bridge&t=<işlem>&s=<imza>&v=<kasa>.
 *  2. Köprü kartı kasadan bir kez açar, sürücünün start() eylemini uygular: bankaya otomatik POST eden
 *     form, yönlendirme, bankanın HTML'i ya da iFrame. Kart sayfaya yalnız bu formda yazılır, hiçbir yerde
 *     saklanmaz.
 *  3. Banka ödeyeni /?flxpos=return&t=…&s=… adresine gönderir; sürücünün finish() sonucu işlenir
 *     (complete()). Bildirim gönderen sağlayıcılar /?flxpos=notify&a=<hesap> adresine yazar.
 *
 * Adresler imzalıdır (flxpos_sign); her işlem bir kez sonuçlanır (FLXPOS_Txn::settle).
 *
 * @package FLX_POS
 */

defined( 'ABSPATH' ) || exit;

final class FLXPOS_Flow {

	const ERR = 'flxpos_error';

	public static function init() {
		add_action( 'init', array( __CLASS__, 'route' ), 20 );
		add_action( 'template_redirect', array( __CLASS__, 'error_notice' ) );
		add_action( 'flxpos_daily', array( 'FLXPOS_Txn', 'expire_stale' ) );
		if ( ! wp_next_scheduled( 'flxpos_daily' ) ) {
			wp_schedule_event( time() + HOUR_IN_SECONDS, 'daily', 'flxpos_daily' );
		}
	}

	/** İmzalı akış adresi. */
	public static function url( $act, $txn_id, array $more = array() ) {
		return add_query_arg(
			array_merge(
				array(
					'flxpos' => $act,
					't'      => (int) $txn_id,
					's'      => flxpos_sign( $act . '|' . (int) $txn_id ),
				),
				$more
			),
			home_url( '/' )
		);
	}

	public static function notify_url( $account_id ) {
		return add_query_arg(
			array(
				'flxpos' => 'notify',
				'a'      => rawurlencode( (string) $account_id ),
			),
			home_url( '/' )
		);
	}

	public static function route() {
		$act = isset( $_GET['flxpos'] ) ? sanitize_key( wp_unslash( $_GET['flxpos'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		if ( ! in_array( $act, array( 'bridge', 'return', 'notify' ), true ) ) {
			return;
		}
		nocache_headers();
		header( 'X-LiteSpeed-Cache-Control: no-cache' );
		do_action( 'litespeed_control_set_nocache', 'flx-pos' );
		if ( ! defined( 'DONOTCACHEPAGE' ) ) {
			define( 'DONOTCACHEPAGE', true );
		}
		if ( 'notify' === $act ) {
			self::notify();
			exit;
		}
		// phpcs:disable WordPress.Security.NonceVerification.Recommended -- imzalı adres; banka dönüşü
		$id  = isset( $_GET['t'] ) ? absint( $_GET['t'] ) : 0;
		$sig = isset( $_GET['s'] ) ? sanitize_text_field( wp_unslash( $_GET['s'] ) ) : '';
		// phpcs:enable
		$txn = $id ? FLXPOS_Txn::get( $id ) : null;
		if ( ! $txn || ! hash_equals( flxpos_sign( $act . '|' . $id ), $sig ) ) {
			wp_die( esc_html__( 'Ödeme bağlantısı geçersiz ya da süresi dolmuş.', 'flx-pos' ), '', array( 'response' => 403 ) );
		}
		if ( 'bridge' === $act ) {
			self::bridge( $txn );
		} else {
			self::back( $txn );
		}
		exit;
	}

	/* ---------------- Köprü ---------------- */

	/** İşlemden sürücüye giden ödeme bilgisi. */
	public static function payment( array $txn, $card = null ) {
		$p              = new FLXPOS_Payment();
		$p->txn         = $txn;
		$p->order       = $txn['order_id'] && function_exists( 'wc_get_order' ) ? wc_get_order( $txn['order_id'] ) : null;
		$p->ref         = $txn['ref'];
		$p->amount      = (float) $txn['amount'];
		$p->currency    = $txn['currency'];
		$p->installment = max( 1, (int) $txn['installment'] );
		$p->card        = $card;
		$p->ip          = $txn['ip'] ? $txn['ip'] : flxpos_client_ip();
		$p->lang        = flxpos_locale();
		$p->return_url  = self::url( 'return', $txn['id'] );
		$p->notify_url  = self::notify_url( $txn['account'] );
		$p->customer    = array_merge(
			array(
				'first'   => '',
				'last'    => '',
				'name'    => '',
				'email'   => '',
				'phone'   => '',
				'city'    => '',
				'address' => '',
				'zip'     => '',
				'country' => '',
				'id'      => '',
			),
			(array) ( $txn['data']['customer'] ?? array() )
		);
		if ( $p->order ) {
			$o           = $p->order;
			$p->customer = array(
				'first'   => (string) $o->get_billing_first_name(),
				'last'    => (string) $o->get_billing_last_name(),
				'name'    => trim( $o->get_billing_first_name() . ' ' . $o->get_billing_last_name() ),
				'email'   => (string) $o->get_billing_email(),
				'phone'   => (string) $o->get_billing_phone(),
				'city'    => (string) $o->get_billing_city(),
				'address' => trim( $o->get_billing_address_1() . ' ' . $o->get_billing_address_2() ),
				'zip'     => (string) $o->get_billing_postcode(),
				'country' => (string) $o->get_billing_country(),
				'id'      => (string) $o->get_customer_id(),
			);
			/* translators: %s: sipariş numarası */
			$p->description = sprintf( __( 'Sipariş %s', 'flx-pos' ), $o->get_order_number() );
			$items          = array();
			foreach ( $o->get_items( array( 'line_item', 'fee', 'shipping' ) ) as $item ) {
				$qty     = max( 1, (int) $item->get_quantity() );
				$total   = (float) $item->get_total() + (float) $item->get_total_tax();
				$items[] = array(
					'id'       => 'i' . $item->get_id(),
					'name'     => wp_strip_all_tags( $item->get_name() ),
					'price'    => round( $total / $qty, 2 ),
					'qty'      => $qty,
					'category' => __( 'Genel', 'flx-pos' ),
				);
			}
			$p->items = $items;
		} else {
			$p->description = (string) ( $txn['data']['description'] ?? $txn['ref'] );
			$p->items       = (array) ( $txn['data']['items'] ?? array() );
		}
		if ( '' === $p->customer['name'] ) {
			$p->customer['name'] = trim( $p->customer['first'] . ' ' . $p->customer['last'] );
		}
		return apply_filters( 'flxpos_payment', $p, $txn );
	}

	private static function bridge( array $txn ) {
		if ( FLXPOS_Txn::PENDING !== $txn['status'] ) {
			self::leave( $txn );
		}
		$drv = FLXPOS_Accounts::driver( $txn['account'] );
		if ( is_wp_error( $drv ) ) {
			self::stop( $txn, $drv->get_error_message() );
		}
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- imzalı adres
		$vid  = isset( $_GET['v'] ) ? sanitize_text_field( wp_unslash( $_GET['v'] ) ) : '';
		$card = $drv->needs_card() ? FLXPOS_Vault::take( $vid ) : null;
		if ( $drv->needs_card() && ! $card ) {
			self::stop( $txn, __( 'Kart bilgisinin süresi doldu; lütfen yeniden deneyin.', 'flx-pos' ) );
		}
		$p = self::payment( $txn, $card );
		if ( $card && method_exists( $drv, 'card_on_return' ) && $drv->card_on_return() ) {
			$p->return_url = add_query_arg( 'vk', FLXPOS_Vault::put_return( $txn['id'], $card ), $p->return_url );
		}
		$act = $drv->start( $p );
		unset( $card );
		if ( is_wp_error( $act ) ) {
			self::stop( $txn, $act->get_error_message() );
		}
		if ( ! empty( $act['data'] ) ) {
			FLXPOS_Txn::update( $txn['id'], array(), (array) $act['data'] );
		}
		if ( isset( $act['result'] ) ) {
			self::complete( FLXPOS_Txn::get( $txn['id'] ), $act['result'] );
			self::leave( FLXPOS_Txn::get( $txn['id'] ) );
		}
		if ( isset( $act['redirect'] ) ) {
			wp_redirect( esc_url_raw( (string) $act['redirect'] ) ); // phpcs:ignore WordPress.Security.SafeRedirect.wp_redirect_wp_redirect -- sağlayıcının ödeme sayfası
			exit;
		}
		self::page( $act, $txn );
	}

	/** Banka sayfasına geçiş sayfası: otomatik POST formu, bankanın HTML'i ya da iFrame. */
	private static function page( array $act, array $txn ) {
		header( 'Content-Type: text/html; charset=utf-8' );
		header( 'Referrer-Policy: no-referrer' );
		header( 'X-Robots-Tag: noindex' );
		if ( isset( $act['html'] ) ) {
			echo $act['html']; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- bankanın 3D sayfası
			return;
		}
		$title = __( 'Güvenli ödemeye geçiliyor…', 'flx-pos' );
		echo '<!doctype html><html lang="' . esc_attr( flxpos_locale() ) . '"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><meta name="robots" content="noindex"><title>' . esc_html( $title ) . '</title>';
		echo '<style>body{margin:0;font:16px/1.5 system-ui,-apple-system,Segoe UI,Roboto,sans-serif;background:#f6f4ef;color:#1f2328;display:flex;min-height:100vh;align-items:center;justify-content:center}main{max-width:520px;width:100%;padding:24px;text-align:center}.sp{width:36px;height:36px;border:3px solid #d0cbbf;border-top-color:#2f3a40;border-radius:50%;margin:0 auto 16px;animation:r 1s linear infinite}@keyframes r{to{transform:rotate(360deg)}}button{font:inherit;padding:10px 18px;border-radius:8px;border:0;background:#2f3a40;color:#fff;cursor:pointer}iframe{width:100%;min-height:640px;border:0;background:#fff;border-radius:12px}.wide{max-width:760px}</style></head><body>';
		if ( isset( $act['iframe'] ) ) {
			echo '<main class="wide"><iframe src="' . esc_url( (string) $act['iframe']['url'] ) . '" title="' . esc_attr__( 'Ödeme formu', 'flx-pos' ) . '" allow="payment"></iframe></main></body></html>';
			return;
		}
		$form = (array) ( $act['form'] ?? array() );
		echo '<main><div class="sp" aria-hidden="true"></div><p>' . esc_html( $title ) . '</p>';
		echo '<form id="flxpos-go" method="post" action="' . esc_url( (string) ( $form['url'] ?? '' ) ) . '">';
		foreach ( (array) ( $form['fields'] ?? array() ) as $k => $v ) {
			echo '<input type="hidden" name="' . esc_attr( (string) $k ) . '" value="' . esc_attr( (string) $v ) . '">';
		}
		echo '<noscript><button type="submit">' . esc_html__( 'Devam et', 'flx-pos' ) . '</button></noscript></form></main>';
		echo '<script>document.getElementById("flxpos-go").submit();</script></body></html>';
	}

	/* ---------------- Dönüş ---------------- */

	private static function back( array $txn ) {
		if ( FLXPOS_Txn::PENDING === $txn['status'] ) {
			$drv = FLXPOS_Accounts::driver( $txn['account'] );
			if ( is_wp_error( $drv ) ) {
				self::stop( $txn, $drv->get_error_message() );
			}
			// phpcs:disable WordPress.Security.NonceVerification -- banka dönüşü; sürücü doğrular
			$req = array(
				'post' => wp_unslash( $_POST ),
				'get'  => wp_unslash( $_GET ),
				'raw'  => (string) file_get_contents( 'php://input' ), // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents
			);
			if ( isset( $_GET['vk'] ) ) {
				$req['card'] = FLXPOS_Vault::take_return( $txn['id'], sanitize_text_field( wp_unslash( $_GET['vk'] ) ) );
			}
			// phpcs:enable
			unset( $req['get']['vk'], $req['get']['flxpos'], $req['get']['t'], $req['get']['s'] ); // yalnız bankanın alanları
			$res = $drv->finish( $txn, $req );
			unset( $req );
			self::complete( $txn, is_array( $res ) ? $res : array( 'status' => 'failed', 'message' => '' ) );
			$txn = FLXPOS_Txn::get( $txn['id'] );
		}
		self::leave( $txn );
	}

	/* ---------------- Bildirim ---------------- */

	private static function notify() {
		// phpcs:disable WordPress.Security.NonceVerification -- sağlayıcı bildirimi; sürücü imzayı doğrular
		$aid = isset( $_GET['a'] ) ? sanitize_text_field( wp_unslash( $_GET['a'] ) ) : '';
		$drv = FLXPOS_Accounts::driver( $aid );
		if ( is_wp_error( $drv ) ) {
			status_header( 404 );
			echo 'unknown';
			return;
		}
		$req = array(
			'post' => wp_unslash( $_POST ),
			'get'  => wp_unslash( $_GET ),
			'raw'  => (string) file_get_contents( 'php://input' ), // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents
		);
		// phpcs:enable
		unset( $req['get']['flxpos'], $req['get']['a'] );
		$n = $drv->notify( $req );
		if ( is_wp_error( $n ) ) {
			status_header( 400 );
			echo esc_html( $n->get_error_message() );
			return;
		}
		$txn = FLXPOS_Txn::by_ref( (string) $n['ref'] );
		if ( $txn && $txn['account'] === $aid ) {
			self::complete( $txn, (array) $n['result'] );
		}
		header( 'Content-Type: text/plain; charset=utf-8' );
		echo esc_html( (string) ( $n['reply'] ?? 'OK' ) );
	}

	/* ---------------- Sonuç ---------------- */

	/**
	 * Bir sonucu işle: işlem kaydı (bir kez), sipariş, kancalar.
	 *
	 * @return string Yeni durum.
	 */
	public static function complete( array $txn, array $res ) {
		$st   = (string) ( $res['status'] ?? 'failed' );
		$set  = array_filter(
			array(
				'bank_ref'    => (string) ( $res['bank_ref'] ?? '' ),
				'auth_code'   => (string) ( $res['auth_code'] ?? '' ),
				'card'        => (string) ( $res['card'] ?? '' ),
				'message'     => (string) ( $res['message'] ?? '' ),
				'installment' => ! empty( $res['installment'] ) ? (int) $res['installment'] : null,
			),
			static fn( $v ) => null !== $v && '' !== $v
		);
		$data = (array) ( $res['data'] ?? array() );
		if ( 'pending' === $st ) {
			if ( $set || $data ) {
				FLXPOS_Txn::update( $txn['id'], $set, $data );
			}
			return FLXPOS_Txn::PENDING;
		}
		if ( 'paid' === $st && null !== ( $res['amount'] ?? null ) && ! flxpos_same_amount( (float) $res['amount'], (float) $txn['amount'] ) ) {
			$st = FLXPOS_Txn::REVIEW;
			/* translators: 1: bankanın çektiği tutar, 2: beklenen tutar */
			$set['message'] = sprintf( __( 'Bankanın çektiği tutar (%1$s) beklenenden (%2$s) farklı; kontrol edin.', 'flx-pos' ), flxpos_dec( $res['amount'] ), flxpos_dec( $txn['amount'] ) );
			$data['paid_amount'] = (float) $res['amount'];
		}
		$map = array(
			'paid'   => FLXPOS_Txn::PAID,
			'review' => FLXPOS_Txn::REVIEW,
			'voided' => FLXPOS_Txn::VOIDED,
		);
		$new = $map[ $st ] ?? FLXPOS_Txn::FAILED;
		if ( ! FLXPOS_Txn::settle( $txn['id'], $new, $set, $data ) ) {
			return (string) ( FLXPOS_Txn::get( $txn['id'] )['status'] ?? $new );
		}
		$txn = FLXPOS_Txn::get( $txn['id'] );
		self::order( $txn );
		do_action( 'flxpos_txn_settled', $txn, $new, $res );
		return $new;
	}

	/** Siparişe işle. */
	private static function order( array $txn ) {
		if ( ! $txn['order_id'] || ! function_exists( 'wc_get_order' ) ) {
			return;
		}
		$o = wc_get_order( $txn['order_id'] );
		if ( ! $o ) {
			return;
		}
		$who = FLXPOS_Accounts::get( $txn['account'] );
		$who = $who ? FLXPOS_Accounts::label( $who ) : $txn['provider'];
		$o->update_meta_data( '_flxpos_txn', $txn['id'] );
		if ( FLXPOS_Txn::PAID === $txn['status'] ) {
			$o->add_order_note(
				sprintf(
					/* translators: 1: POS, 2: tutar, 3: banka referansı, 4: taksit */
					__( 'Kartla ödendi (%1$s): %2$s, referans %3$s, %4$s.', 'flx-pos' ),
					$who,
					flxpos_money( $txn['amount'], $txn['currency'] ),
					'' !== $txn['bank_ref'] ? $txn['bank_ref'] : $txn['ref'],
					$txn['installment'] > 1 ? sprintf( /* translators: %d: taksit sayısı */ __( '%d taksit', 'flx-pos' ), $txn['installment'] ) : __( 'peşin', 'flx-pos' )
				)
			);
			if ( ! $o->is_paid() ) {
				$o->payment_complete( '' !== $txn['bank_ref'] ? $txn['bank_ref'] : $txn['ref'] );
			} else {
				$o->save();
			}
		} elseif ( FLXPOS_Txn::REVIEW === $txn['status'] ) {
			/* translators: 1: POS, 2: açıklama */
			$o->update_status( 'on-hold', sprintf( __( 'Kart ödemesi kontrol gerektiriyor (%1$s): %2$s', 'flx-pos' ), $who, $txn['message'] ) );
		} else {
			/* translators: 1: POS, 2: hata */
			$o->add_order_note( sprintf( __( 'Kart ödemesi başarısız (%1$s): %2$s', 'flx-pos' ), $who, '' !== $txn['message'] ? $txn['message'] : '-' ) );
			$o->save();
		}
	}

	/** Akıştan çık: sonuca göre teşekkür sayfası ya da hata ile ödeme sayfası. */
	private static function leave( array $txn ) {
		$url = (string) apply_filters( 'flxpos_leave_url', '', $txn );
		if ( '' === $url ) {
			$o = $txn['order_id'] && function_exists( 'wc_get_order' ) ? wc_get_order( $txn['order_id'] ) : null;
			$ok = in_array( $txn['status'], array( FLXPOS_Txn::PAID, FLXPOS_Txn::REVIEW ), true );
			if ( $o ) {
				if ( $ok || FLXPOS_Txn::PENDING === $txn['status'] ) {
					$url = $o->get_checkout_order_received_url();
				} else {
					$url = add_query_arg( self::ERR, rawurlencode( self::public_message( $txn['message'] ) ), $o->get_checkout_payment_url() );
				}
			} else {
				$back = (string) ( $txn['data']['back'] ?? home_url( '/' ) );
				$url  = add_query_arg(
					array(
						'flxpos_status' => $txn['status'],
						'flxpos_ref'    => $txn['ref'],
					),
					$back
				);
			}
		}
		wp_safe_redirect( $url, 303 );
		exit;
	}

	/** Akışı hata ile durdur. */
	private static function stop( array $txn, $message ) {
		self::complete( $txn, array( 'status' => 'failed', 'message' => (string) $message ) );
		self::leave( FLXPOS_Txn::get( $txn['id'] ) );
	}

	/** Ödeyene gösterilecek hata (boşsa genel). */
	public static function public_message( $m ) {
		$m = trim( wp_strip_all_tags( (string) $m ) );
		return '' !== $m && 'timeout' !== $m ? mb_substr( $m, 0, 200 ) : __( 'Ödeme tamamlanamadı. Lütfen yeniden deneyin ya da başka bir kart kullanın.', 'flx-pos' );
	}

	public static function error_notice() {
		if ( empty( $_GET[ self::ERR ] ) || ! function_exists( 'wc_add_notice' ) || ! function_exists( 'is_checkout' ) || ! is_checkout() ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			return;
		}
		wc_add_notice( mb_substr( sanitize_text_field( wp_unslash( $_GET[ self::ERR ] ) ), 0, 300 ), 'error' ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
	}
}
