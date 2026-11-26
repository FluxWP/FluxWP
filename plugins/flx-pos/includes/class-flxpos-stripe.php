<?php
/**
 * Stripe ön yüzü: ödeme sayfasındaki Payment Element (klasik + blok), belirteçsiz ödemeler için sitenin
 * kendi Stripe ödeme sayfası ve betikler.
 *
 * Stripe.js PCI gereği yalnız js.stripe.com'dan yüklenir (paketlenemez); yalnız Stripe hesabı açıkken ve
 * ödeme sayfasında.
 *
 * License: GPL-3.0-or-later (bkz. README, Kaynak).
 *
 * @package FLX_POS
 */

defined( 'ABSPATH' ) || exit;

final class FLXPOS_Stripe {

	const JS = 'https://js.stripe.com/v3/';

	/** Hesap Stripe mı? */
	public static function is( $account ) {
		return is_array( $account ) && 'stripe' === ( $account['provider'] ?? '' );
	}

	/** Betikleri kaydet (bir kez). */
	public static function register() {
		if ( wp_script_is( 'flxpos-stripe', 'registered' ) ) {
			return;
		}
		wp_register_script( 'flxpos-stripejs', self::JS, array(), null, true ); // phpcs:ignore WordPress.WP.EnqueuedResourceParameters.MissingVersion -- Stripe.js sürümsüz yüklenmeli
		wp_register_script( 'flxpos-stripe', FLXPOS_URL . 'assets/stripe.js', array( 'flxpos-stripejs' ), FLXPOS_VERSION, true );
		wp_add_inline_script( 'flxpos-stripe', 'window.FLXPOS_STRIPE_I18N=' . wp_json_encode( self::i18n() ) . ';', 'before' );
	}

	public static function i18n() {
		return array(
			'pay'     => __( 'Öde', 'flx-pos' ),
			'wait'    => __( 'İşleniyor…', 'flx-pos' ),
			'load'    => __( 'Ödeme formu yüklenemedi. Sayfayı yenileyin ya da başka bir yöntem seçin.', 'flx-pos' ),
			'fail'    => __( 'Ödeme tamamlanamadı. Lütfen yeniden deneyin ya da başka bir kart kullanın.', 'flx-pos' ),
			'secure'  => __( 'Kart bilgileriniz Stripe\'ın güvenli alanlarında işlenir ve sitede saklanmaz.', 'flx-pos' ),
			'test'    => __( 'Test modu: gerçek para çekilmez.', 'flx-pos' ),
			'back'    => __( 'Vazgeç', 'flx-pos' ),
		);
	}

	/**
	 * Ödeme sayfasındaki Payment Element ayarları (yayımlanabilir anahtar; gizli bilgi yok).
	 *
	 * @param array $account Hesap (şifreli de olabilir).
	 */
	public static function config( array $account, $amount, $currency ) {
		FLXPOS_Providers::driver_class( 'stripe' );
		$a   = FLXPOS_Accounts::get( $account['id'], true );
		$cur = strtoupper( (string) $currency );
		$drv = $a ? FLXPOS_Accounts::driver( $a ) : null;
		$pk  = $drv instanceof FLXPOS_Driver_Stripe ? $drv->publishable_key() : '';
		$cfg = array(
			'pk'       => $pk,
			'amount'   => max( 1, FLXPOS_Driver_Stripe::to_minor( $amount, $cur ) ),
			'currency' => strtolower( $cur ),
			'locale'   => flxpos_locale(),
			'save'     => (bool) apply_filters( 'flxpos_stripe_checkout_save', false, $account ),
			'test'     => ! empty( $account['test'] ),
			'primary'  => (string) apply_filters( 'flxpos_stripe_primary', '' ),
		);
		return (array) apply_filters( 'flxpos_stripe_config', $cfg, $account );
	}

	/** Klasik ödeme: Payment Element yeri + belirteç alanı. */
	public static function fields( array $account, $amount, $currency ) {
		self::register();
		wp_enqueue_script( 'flxpos-stripe' );
		$cfg = self::config( $account, $amount, $currency );
		echo '<div class="flxpos-stripe" data-flxpos-stripe="' . esc_attr( wp_json_encode( $cfg ) ) . '">';
		echo '<div class="flxpos-stripe__el" aria-live="polite"></div>';
		echo '<input type="hidden" name="flxpos_token" value="">';
		echo '<p class="flxpos-stripe__err" role="alert" hidden></p>';
		echo '<p class="flxpos-secure">' . esc_html( self::i18n()['secure'] ) . '</p>';
		if ( ! empty( $account['test'] ) ) {
			echo '<p class="flxpos-test">' . esc_html__( 'Test modu: gerçek para çekilmez.', 'flx-pos' ) . '</p>';
		}
		echo '</div>';
	}

	/** Gönderilen belirteç (pm_…), yoksa ''. */
	public static function posted_token() {
		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- WooCommerce ödeme formu (nonce WC'de)
		$t = isset( $_POST['flxpos_token'] ) ? sanitize_text_field( wp_unslash( $_POST['flxpos_token'] ) ) : '';
		return preg_match( '/^pm_[A-Za-z0-9]{6,}$/', $t ) ? $t : '';
	}

	/**
	 * Belirteçsiz ödeme: sitenin kendi Stripe ödeme sayfası (köprüde basılır).
	 */
	public static function page( FLXPOS_Driver_Stripe $drv, FLXPOS_Payment $p, $client_secret ) {
		$title = __( 'Güvenli ödeme', 'flx-pos' );
		$i18n  = self::i18n();
		$cfg   = array(
			'pk'           => $drv->publishable_key(),
			'clientSecret' => (string) $client_secret,
			'returnUrl'    => $p->return_url,
			'locale'       => $p->lang,
			'billing'      => array_filter(
				array(
					'name'  => (string) ( $p->customer['name'] ?? '' ),
					'email' => (string) ( $p->customer['email'] ?? '' ),
					'phone' => (string) ( $p->customer['phone'] ?? '' ),
				)
			),
			'i18n'         => $i18n,
		);
		$back  = $p->order ? $p->order->get_checkout_payment_url() : (string) ( $p->txn['data']['back'] ?? home_url( '/' ) );
		$h     = '<!doctype html><html lang="' . esc_attr( $p->lang ) . '"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><meta name="robots" content="noindex"><title>' . esc_html( $title ) . '</title>';
		$h    .= '<style>:root{--bg:#f4f4f5;--card:#fff;--fg:#18181b;--mut:#52525b;--bd:#e4e4e7;--pri:#2f3a40;--err:#b42318}@media (prefers-color-scheme:dark){:root{--bg:#18181b;--card:#27272a;--fg:#fafafa;--mut:#a1a1aa;--bd:#3f3f46;--pri:#e4e4e7;--err:#fda29b}}*{box-sizing:border-box}body{margin:0;font:16px/1.5 system-ui,-apple-system,Segoe UI,Roboto,sans-serif;background:var(--bg);color:var(--fg);display:flex;min-height:100vh;align-items:flex-start;justify-content:center;padding:32px 16px}main{width:100%;max-width:480px;background:var(--card);border:1px solid var(--bd);border-radius:14px;padding:24px}.bar{display:flex;justify-content:space-between;gap:12px;align-items:baseline;margin:0 0 16px;font-weight:600}.bar strong{font-size:1.15em}#el{min-height:120px}button{width:100%;margin-top:18px;font:inherit;font-weight:600;padding:12px 18px;border-radius:10px;border:0;background:var(--pri);color:var(--card);cursor:pointer}button[disabled]{opacity:.6;cursor:wait}.err{color:var(--err);margin:12px 0 0}.mut{color:var(--mut);font-size:.875em;margin:14px 0 0}.test{color:#b54708;font-size:.875em;margin:8px 0 0}a{color:var(--mut)}.back{display:block;text-align:center;margin-top:14px;font-size:.9em}</style></head><body>';
		$h    .= '<main><p class="bar"><span>' . esc_html( $title ) . '</span><strong>' . esc_html( flxpos_money( $p->amount, $p->currency ) ) . '</strong></p>';
		$h    .= '<form id="flxpos-stripe-page"><div id="el"></div><p class="err" role="alert" hidden></p><button type="submit" disabled>' . esc_html( $i18n['pay'] . ' · ' . flxpos_money( $p->amount, $p->currency ) ) . '</button></form>';
		$h    .= '<p class="mut">' . esc_html( $i18n['secure'] ) . '</p>';
		if ( ! empty( $p->txn['test'] ) ) {
			$h .= '<p class="test">' . esc_html( $i18n['test'] ) . '</p>';
		}
		$h .= '<a class="back" href="' . esc_url( $back ) . '" target="_top">' . esc_html( $i18n['back'] ) . '</a></main>';
		$h .= '<script src="' . esc_url( self::JS ) . '"></script>';
		$h .= '<script>window.FLXPOS_STRIPE_I18N=' . wp_json_encode( $i18n ) . ';</script>';
		$h .= '<script src="' . esc_url( FLXPOS_URL . 'assets/stripe.js?ver=' . FLXPOS_VERSION ) . '"></script>';
		$h .= '<script>window.FLXPOS_STRIPE&&window.FLXPOS_STRIPE.page(' . wp_json_encode( $cfg ) . ');</script></body></html>';
		return $h;
	}
}
