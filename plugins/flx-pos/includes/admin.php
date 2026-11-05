<?php
/**
 * Yönetim: "POS" sayfası. Sekmeler: Hesaplar (ekle/düzenle, bağlantı testi, test kartları), Ödeme yöntemleri
 * (sitedeki tüm yöntemlerin yetenek tablosu, tek kart kuralı), İşlemler (kayıtlar, iade), Ayarlar.
 *
 * @package FLX_POS
 */

defined( 'ABSPATH' ) || exit;

final class FLXPOS_Admin {

	const CAP = 'manage_woocommerce';

	public static function init() {
		add_action( 'admin_menu', array( __CLASS__, 'menu' ), 60 );
		add_action( 'admin_enqueue_scripts', array( __CLASS__, 'assets' ) );
		add_action( 'admin_head', array( __CLASS__, 'menu_logo' ) );
		add_action( 'admin_post_flxpos_save_account', array( __CLASS__, 'save_account' ) );
		add_action( 'admin_post_flxpos_delete_account', array( __CLASS__, 'delete_account' ) );
		add_action( 'admin_post_flxpos_save_settings', array( __CLASS__, 'save_settings' ) );
		add_action( 'admin_post_flxpos_refund', array( __CLASS__, 'refund' ) );
		add_action( 'add_meta_boxes', array( __CLASS__, 'order_box' ), 30 );
	}

	private static function cap() {
		return current_user_can( self::CAP ) ? self::CAP : 'manage_options';
	}

	public static function menu() {
		add_menu_page( __( 'Sanal POS', 'flx-pos' ), __( 'POS', 'flx-pos' ), self::cap(), 'flx-pos', array( __CLASS__, 'render' ), 'none', 56 );
	}

	public static function menu_logo() {
		echo '<style>#toplevel_page_flx-pos .wp-menu-image{background:url(' . esc_url( FLXPOS_URL . 'assets/icon.svg?ver=' . FLXPOS_VERSION ) . ') center/20px 20px no-repeat}</style>';
	}

	private static function is_screen() {
		return isset( $_GET['page'] ) && 'flx-pos' === $_GET['page']; // phpcs:ignore WordPress.Security.NonceVerification
	}

	public static function assets() {
		if ( ! self::is_screen() ) {
			return;
		}
		wp_enqueue_style( 'flxpos-admin', FLXPOS_URL . 'assets/admin.css', array(), FLXPOS_VERSION );
		wp_enqueue_script( 'flxpos-admin', FLXPOS_URL . 'assets/admin.js', array(), FLXPOS_VERSION, true );
		wp_localize_script(
			'flxpos-admin',
			'flxposAdmin',
			array(
				'rest'  => esc_url_raw( rest_url( 'flxpos/v1/' ) ),
				'nonce' => wp_create_nonce( 'wp_rest' ),
				'i18n'  => array(
					'testing' => __( 'Sağlayıcıya bağlanılıyor…', 'flx-pos' ),
					'fail'    => __( 'Bir şey ters gitti. Tekrar deneyin.', 'flx-pos' ),
					'copied'  => __( 'Kopyalandı', 'flx-pos' ),
					'confirm' => __( 'Bu hesap silinsin mi? İşlem kayıtları kalır.', 'flx-pos' ),
					'refund'  => __( 'Bu tutar karta iade edilsin mi? Geri alınamaz.', 'flx-pos' ),
				),
			)
		);
	}

	private static function tabs() {
		return array(
			'accounts' => __( 'Hesaplar', 'flx-pos' ),
			'methods'  => __( 'Ödeme yöntemleri', 'flx-pos' ),
			'txns'     => __( 'İşlemler', 'flx-pos' ),
			'settings' => __( 'Ayarlar', 'flx-pos' ),
		);
	}

	private static function get( $k, $d = '' ) {
		return isset( $_GET[ $k ] ) ? sanitize_text_field( wp_unslash( $_GET[ $k ] ) ) : $d; // phpcs:ignore WordPress.Security.NonceVerification
	}

	/* ---------------------------------------------------------------- */
	/* Sayfa                                                             */
	/* ---------------------------------------------------------------- */

	public static function render() {
		if ( ! current_user_can( self::cap() ) ) {
			wp_die( esc_html__( 'Bu işlem için yetkiniz yok.', 'flx-pos' ), 403 );
		}
		$tab = self::get( 'tab', 'accounts' );
		$tab = isset( self::tabs()[ $tab ] ) ? $tab : 'accounts';
		echo '<div class="wrap flxpos">';
		echo '<div class="flxpos-head"><div><h1>' . esc_html__( 'Sanal POS', 'flx-pos' ) . '</h1><p class="flxpos-lead">' . esc_html__( 'Bankalar ve ödeme kuruluşlarıyla 3D Secure kartlı ödeme. Hesabı ekleyin, test modunda test kartıyla deneyin, sonra canlıya alın.', 'flx-pos' ) . '</p></div></div>';
		if ( ! class_exists( 'WooCommerce' ) ) {
			echo '<div class="flxpos-callout is-warn"><div>' . esc_html__( 'WooCommerce etkin değil: ödeme yöntemleri görünmez. Diğer eklentiler PHP API ile yine ödeme alabilir.', 'flx-pos' ) . '</div></div>';
		}
		self::notices();
		echo '<nav class="flxpos-tabs" aria-label="' . esc_attr__( 'Sanal POS bölümleri', 'flx-pos' ) . '">';
		foreach ( self::tabs() as $k => $l ) {
			echo '<a href="' . esc_url( flxpos_admin_url( array( 'tab' => $k ) ) ) . '" class="' . ( $k === $tab ? 'is-active' : '' ) . '"' . ( $k === $tab ? ' aria-current="page"' : '' ) . '>' . esc_html( $l ) . '</a>';
		}
		echo '</nav>';
		switch ( $tab ) {
			case 'methods':
				self::tab_methods();
				break;
			case 'txns':
				self::tab_txns();
				break;
			case 'settings':
				self::tab_settings();
				break;
			default:
				'' !== self::get( 'edit' ) ? self::editor( self::get( 'edit' ) ) : self::tab_accounts();
		}
		echo '</div>';
	}

	private static function notices() {
		$m = self::get( 'msg' );
		$e = self::get( 'err' );
		$map = array(
			'saved'    => __( 'Kaydedildi.', 'flx-pos' ),
			'deleted'  => __( 'Hesap silindi.', 'flx-pos' ),
			'refunded' => __( 'İade bankaya gönderildi ve onaylandı.', 'flx-pos' ),
		);
		if ( isset( $map[ $m ] ) ) {
			echo '<div class="flxpos-notice is-ok" role="status">' . esc_html( $map[ $m ] ) . '</div>';
		}
		if ( '' !== $e ) {
			echo '<div class="flxpos-notice is-bad" role="alert">' . esc_html( $e ) . '</div>';
		}
	}

	private static function badge( $text, $cls = '' ) {
		return '<span class="flxpos-badge ' . esc_attr( $cls ) . '">' . esc_html( $text ) . '</span>';
	}

	private static function money( $x, $cur ) {
		return flxpos_money( $x, $cur );
	}

	/* ---------------- Hesaplar ---------------- */

	private static function tab_accounts() {
		$all  = FLXPOS_Accounts::all() + FLXPOS_Accounts::virtual();
		$sum  = FLXPOS_Txn::summary( 30 );
		$paid = array();
		$fail = 0;
		foreach ( $sum as $r ) {
			if ( FLXPOS_Txn::PAID === $r['status'] || FLXPOS_Txn::PARTIAL === $r['status'] ) {
				$paid[ $r['currency'] ] = ( $paid[ $r['currency'] ] ?? 0 ) + (float) $r['total'];
			} elseif ( FLXPOS_Txn::FAILED === $r['status'] ) {
				$fail += (int) $r['n'];
			}
		}
		$cm     = class_exists( 'FLXPOS_Methods' ) && function_exists( 'WC' ) ? FLXPOS_Methods::card_method() : 'flxpos';
		$active = FLXPOS_Accounts::active();
		$card   = FLXPOS_Accounts::for_card( function_exists( 'get_woocommerce_currency' ) ? get_woocommerce_currency() : 'TRY' );
		echo '<div class="flxpos-kpis">';
		self::kpi( __( 'Etkin hesap', 'flx-pos' ), number_format_i18n( count( $active ) ), $card ? FLXPOS_Accounts::label( $card ) : __( 'kart hesabı yok', 'flx-pos' ), $card ? 'is-ok' : 'is-warn' );
		$p = array();
		foreach ( $paid as $c => $t ) {
			$p[] = self::money( $t, $c );
		}
		self::kpi( __( 'Tahsilat', 'flx-pos' ), $p ? implode( ' · ', $p ) : '0', __( 'son 30 gün, canlı', 'flx-pos' ) );
		self::kpi( __( 'Başarısız deneme', 'flx-pos' ), number_format_i18n( $fail ), __( 'son 30 gün', 'flx-pos' ), $fail ? 'is-warn' : '' );
		self::kpi( __( 'Kart yöntemi', 'flx-pos' ), 'flxpos' === $cm ? 'Flux POS' : ( 'all' === $cm ? __( 'Hepsi', 'flx-pos' ) : $cm ), __( 'ödeyenin gördüğü', 'flx-pos' ) );
		echo '</div>';

		echo '<section class="flxpos-card"><div class="flxpos-card__head"><h2>' . esc_html__( 'POS hesapları', 'flx-pos' ) . '</h2>';
		self::provider_picker();
		echo '</div>';
		if ( ! $all ) {
			echo '<div class="flxpos-empty"><p><strong>' . esc_html__( 'Henüz hesap yok.', 'flx-pos' ) . '</strong></p><p class="flxpos-muted">' . esc_html__( 'Bankanızı ya da ödeme kuruluşunuzu seçip "Hesap ekle"ye basın. Anahtarları sağlayıcının üye işyeri panelinden alırsınız; test için test modunu açık bırakın.', 'flx-pos' ) . '</p></div>';
		} else {
			echo '<ul class="flxpos-rows">';
			foreach ( $all as $id => $a ) {
				$p    = FLXPOS_Providers::get( $a['provider'] );
				$name = '' !== $a['label'] ? $a['label'] : ( $p ? $p['name'] : $a['provider'] );
				$mode = $p && isset( $p['modes'][ $a['mode'] ] ) ? $p['modes'][ $a['mode'] ] : ( $p ? (string) reset( $p['modes'] ) : '' );
				$curs = $a['currencies'] ? $a['currencies'] : (array) ( $p['currencies'] ?? array() );
				echo '<li class="flxpos-row' . ( empty( $a['enabled'] ) ? ' is-off' : '' ) . '"><div class="flxpos-row__text"><strong>' . esc_html( $name ) . '</strong> ';
				echo empty( $a['test'] ) ? self::badge( __( 'Canlı', 'flx-pos' ), 'is-ok' ) : self::badge( __( 'Test', 'flx-pos' ), 'is-warn' ); // phpcs:ignore WordPress.Security.EscapeOutput
				if ( empty( $a['enabled'] ) ) {
					echo ' ' . self::badge( __( 'Kapalı', 'flx-pos' ) ); // phpcs:ignore WordPress.Security.EscapeOutput
				}
				if ( $card && $card['id'] === $id ) {
					echo ' ' . self::badge( __( 'Kart yöntemi', 'flx-pos' ), 'is-ac' ); // phpcs:ignore WordPress.Security.EscapeOutput
				}
				if ( ! empty( $a['virtual'] ) ) {
					echo ' ' . self::badge( __( 'WooCommerce ayarlarından', 'flx-pos' ) ); // phpcs:ignore WordPress.Security.EscapeOutput
				}
				echo '<small>' . esc_html( ( $p ? $p['name'] : $a['provider'] ) . ' · ' . $mode . ' · ' . implode( ', ', $curs ) . ( $a['installments'] ? ' · ' . sprintf( /* translators: %d: en çok taksit */ __( '%d taksite kadar', 'flx-pos' ), max( array_keys( $a['installments'] ) ) ) : '' ) ) . '</small></div>';
				echo '<div class="flxpos-row__act">';
				if ( empty( $a['virtual'] ) ) {
					echo '<button type="button" class="button" data-flxpos-test="' . esc_attr( $id ) . '">' . esc_html__( 'Bağlantıyı test et', 'flx-pos' ) . '</button> ';
					echo '<a class="button button-primary" href="' . esc_url( flxpos_admin_url( array( 'edit' => $id ) ) ) . '">' . esc_html__( 'Düzenle', 'flx-pos' ) . '</a>';
				} else {
					echo '<a class="button" href="' . esc_url( admin_url( 'admin.php?page=wc-settings&tab=checkout&section=' . FLXPOS_Esnekpos_Module::ID ) ) . '">' . esc_html__( 'WooCommerce ayarları', 'flx-pos' ) . '</a>';
				}
				echo '</div><p class="flxpos-test-msg" data-flxpos-msg="' . esc_attr( $id ) . '" hidden></p></li>';
			}
			echo '</ul>';
		}
		echo '</section>';
		self::provider_list();
	}

	private static function kpi( $label, $value, $hint = '', $cls = '' ) {
		echo '<div class="flxpos-kpi ' . esc_attr( $cls ) . '"><span class="flxpos-kpi__label">' . esc_html( $label ) . '</span><span class="flxpos-kpi__value">' . esc_html( $value ) . '</span>' . ( '' !== $hint ? '<span class="flxpos-kpi__hint">' . esc_html( $hint ) . '</span>' : '' ) . '</div>';
	}

	/** "Hesap ekle" seçicisi (gruplu). */
	private static function provider_picker() {
		$groups = FLXPOS_Providers::group_labels();
		echo '<form method="get" class="flxpos-add"><input type="hidden" name="page" value="flx-pos"><input type="hidden" name="edit" value="new">';
		echo '<label class="screen-reader-text" for="flxpos-add-p">' . esc_html__( 'Sağlayıcı', 'flx-pos' ) . '</label><select id="flxpos-add-p" name="provider" required><option value="">' . esc_html__( 'Banka ya da kuruluş seçin…', 'flx-pos' ) . '</option>';
		foreach ( $groups as $g => $gl ) {
			echo '<optgroup label="' . esc_attr( $gl ) . '">';
			foreach ( FLXPOS_Providers::all() as $p ) {
				if ( $g === $p['group'] && '' !== $p['driver'] ) {
					echo '<option value="' . esc_attr( $p['id'] ) . '">' . esc_html( $p['name'] ) . '</option>';
				}
			}
			echo '</optgroup>';
		}
		echo '</select> <button class="button button-primary">' . esc_html__( 'Hesap ekle', 'flx-pos' ) . '</button></form>';
	}

	/** Desteklenen sağlayıcılar (bilgi). */
	private static function provider_list() {
		$caps = FLXPOS_Providers::cap_labels();
		echo '<details class="flxpos-card flxpos-providers"><summary><h2>' . esc_html__( 'Desteklenen bankalar ve kuruluşlar', 'flx-pos' ) . '</h2></summary>';
		echo '<div class="flxpos-tablewrap"><table class="flxpos-table"><thead><tr><th scope="col">' . esc_html__( 'Sağlayıcı', 'flx-pos' ) . '</th><th scope="col">' . esc_html__( 'Modeller', 'flx-pos' ) . '</th><th scope="col">' . esc_html__( 'Para birimleri', 'flx-pos' ) . '</th><th scope="col">' . esc_html__( 'Siteden', 'flx-pos' ) . '</th></tr></thead><tbody>';
		foreach ( FLXPOS_Providers::all() as $p ) {
			$on = array();
			foreach ( $caps as $k => $l ) {
				if ( ! empty( $p['caps'][ $k ] ) ) {
					$on[] = $l;
				}
			}
			echo '<tr><th scope="row">' . esc_html( $p['name'] ) . ' <small class="flxpos-muted">' . esc_html( FLXPOS_Providers::group_labels()[ $p['group'] ] ?? '' ) . '</small></th>';
			if ( '' === $p['driver'] ) {
				echo '<td colspan="3" class="flxpos-muted">' . esc_html__( 'Sağlayıcının API belgesi bekleniyor; belge gelince eklenecek.', 'flx-pos' ) . '</td></tr>';
				continue;
			}
			echo '<td>' . esc_html( implode( ', ', $p['modes'] ) ) . '</td><td>' . esc_html( implode( ', ', (array) $p['currencies'] ) ) . '</td><td>' . esc_html( $on ? implode( ', ', $on ) : '—' ) . '</td></tr>';
		}
		echo '</tbody></table></div></details>';
	}

	/** Hesap düzenleyici. */
	private static function editor( $id ) {
		$a = 'new' === $id ? null : FLXPOS_Accounts::get( $id );
		if ( 'new' !== $id && ( ! $a || ! empty( $a['virtual'] ) ) ) {
			echo '<div class="flxpos-notice is-bad">' . esc_html__( 'POS hesabı bulunamadı.', 'flx-pos' ) . '</div>';
			return;
		}
		$pid = $a ? $a['provider'] : sanitize_key( self::get( 'provider' ) );
		$p   = FLXPOS_Providers::get( $pid );
		if ( ! $p || '' === $p['driver'] ) {
			echo '<div class="flxpos-notice is-bad">' . esc_html__( 'Sağlayıcı seçin.', 'flx-pos' ) . '</div>';
			return;
		}
		$a = $a ? $a : array_merge( FLXPOS_Accounts::defaults(), array( 'provider' => $pid ) );
		echo '<p><a href="' . esc_url( flxpos_admin_url() ) . '">← ' . esc_html__( 'Hesaplar', 'flx-pos' ) . '</a></p>';
		echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '" class="flxpos-form">';
		wp_nonce_field( 'flxpos_save_account' );
		echo '<input type="hidden" name="action" value="flxpos_save_account"><input type="hidden" name="id" value="' . esc_attr( $a['id'] ) . '"><input type="hidden" name="provider" value="' . esc_attr( $p['id'] ) . '">';
		echo '<div class="flxpos-grid">';

		/* Genel */
		echo '<section class="flxpos-card flxpos-span-2"><div class="flxpos-card__head"><h2>' . esc_html( $p['name'] ) . '</h2>' . self::badge( FLXPOS_Providers::group_labels()[ $p['group'] ] ?? '' ) . '</div>'; // phpcs:ignore WordPress.Security.EscapeOutput
		if ( '' !== $p['notes'] ) {
			echo '<p class="flxpos-muted">' . esc_html( $p['notes'] ) . '</p>';
		}
		echo '<div class="flxpos-fields flxpos-fields--2">';
		echo '<label><span>' . esc_html__( 'Görünen ad (panelde)', 'flx-pos' ) . '</span><input type="text" name="label" value="' . esc_attr( $a['label'] ) . '" placeholder="' . esc_attr( $p['name'] ) . '"></label>';
		echo '<label><span>' . esc_html__( 'Ödeme modeli', 'flx-pos' ) . '</span><select name="mode">';
		foreach ( $p['modes'] as $k => $l ) {
			echo '<option value="' . esc_attr( $k ) . '"' . selected( $a['mode'], $k, false ) . '>' . esc_html( $l ) . '</option>';
		}
		echo '</select></label>';
		echo '</div><div class="flxpos-checks">';
		echo '<label class="flxpos-check"><input type="checkbox" name="enabled" value="1"' . checked( ! empty( $a['enabled'] ), true, false ) . '><span>' . esc_html__( 'Etkin', 'flx-pos' ) . '</span></label>';
		echo '<label class="flxpos-check"><input type="checkbox" name="test" value="1"' . checked( ! empty( $a['test'] ), true, false ) . '><span>' . esc_html__( 'Test modu', 'flx-pos' ) . '<small>' . esc_html__( 'Sağlayıcının test ortamı; gerçek para çekilmez.', 'flx-pos' ) . '</small></span></label>';
		echo '</div>';

		echo '<h3 class="flxpos-sub">' . esc_html__( 'Üye işyeri bilgileri', 'flx-pos' ) . '</h3><div class="flxpos-fields flxpos-fields--2">';
		foreach ( (array) $p['fields'] as $k => $f ) {
			$has = '' !== (string) ( $a['creds'][ $k ] ?? '' );
			echo '<label><span>' . esc_html( $f['label'] ) . '</span>';
			if ( 'secret' === $f['type'] ) {
				echo '<input type="password" name="creds[' . esc_attr( $k ) . ']" value="" autocomplete="new-password" placeholder="' . esc_attr( $has ? __( '•••••••• kayıtlı (değiştirmek için yazın)', 'flx-pos' ) : '' ) . '">';
			} else {
				echo '<input type="text" class="code" name="creds[' . esc_attr( $k ) . ']" value="' . esc_attr( (string) ( $a['creds'][ $k ] ?? $f['default'] ) ) . '" autocomplete="off" spellcheck="false">';
			}
			if ( '' !== $f['help'] ) {
				echo '<small>' . esc_html( $f['help'] ) . '</small>';
			}
			echo '</label>';
		}
		echo '</div>';

		echo '<details class="flxpos-adv"><summary>' . esc_html__( 'Gelişmiş: banka adresleri', 'flx-pos' ) . '</summary><p class="flxpos-muted">' . esc_html__( 'Boş bırakın; banka size farklı bir adres verdiyse yazın.', 'flx-pos' ) . '</p><div class="flxpos-fields flxpos-fields--2">';
		$set = (array) ( $p['endpoints'][ ! empty( $a['test'] ) ? 'test' : 'live' ] ?? array() );
		foreach ( array_keys( (array) ( $p['endpoints']['live'] ?? array() ) ) as $k ) {
			echo '<label><span>' . esc_html( strtoupper( $k ) ) . '</span><input type="url" class="code" name="creds[url_' . esc_attr( $k ) . ']" value="' . esc_attr( (string) ( $a['creds'][ 'url_' . $k ] ?? '' ) ) . '" placeholder="' . esc_attr( (string) ( $set[ $k ] ?? '' ) ) . '"></label>';
		}
		echo '</div></details>';
		echo '</section>';

		/* Yan sütun: adresler + test kartları */
		echo '<aside class="flxpos-card"><h2>' . esc_html__( 'Sağlayıcı paneline yazılacaklar', 'flx-pos' ) . '</h2>';
		echo '<p class="flxpos-muted">' . esc_html__( 'Sağlayıcı bildirim (callback/webhook) adresi istiyorsa bunu verin. Dönüş adresleri her ödemede otomatik gönderilir.', 'flx-pos' ) . '</p>';
		echo '<label class="flxpos-copy"><span>' . esc_html__( 'Bildirim adresi', 'flx-pos' ) . '</span><input type="text" class="code" readonly value="' . esc_attr( '' !== $a['id'] ? FLXPOS_Flow::notify_url( $a['id'] ) : __( 'Kaydedince oluşur', 'flx-pos' ) ) . '"></label>';
		echo '<p class="flxpos-muted">' . esc_html__( 'Sunucu IP adresi (bazı bankalar ister):', 'flx-pos' ) . ' <code>' . esc_html( flxpos_server_ip() ) . '</code></p>';
		if ( $p['test_cards'] ) {
			echo '<h3 class="flxpos-sub">' . esc_html__( 'Test kartları', 'flx-pos' ) . '</h3><ul class="flxpos-cards">';
			foreach ( $p['test_cards'] as $c ) {
				echo '<li><code>' . esc_html( trim( chunk_split( $c[0], 4, ' ' ) ) ) . '</code><small>' . esc_html( $c[1] . '/' . substr( $c[2], -2 ) . ' · CVC ' . $c[3] . ( ! empty( $c[4] ) ? ' · 3D ' . $c[4] : '' ) ) . '</small></li>';
			}
			echo '</ul><p class="flxpos-muted">' . esc_html__( 'Bankaların yayımladığı test kartları; yalnız test modunda çalışır.', 'flx-pos' ) . '</p>';
		}
		echo '</aside>';

		/* Para birimi ve taksit */
		echo '<section class="flxpos-card flxpos-span-3"><h2>' . esc_html__( 'Para birimleri ve taksit', 'flx-pos' ) . '</h2>';
		echo '<div class="flxpos-checks">';
		foreach ( (array) $p['currencies'] as $c ) {
			echo '<label class="flxpos-check"><input type="checkbox" name="currencies[]" value="' . esc_attr( $c ) . '"' . checked( ! $a['currencies'] || in_array( $c, $a['currencies'], true ), true, false ) . '><span>' . esc_html( $c ) . '</span></label>';
		}
		echo '</div>';
		if ( 'wallet' === $p['group'] ) {
			echo '<div class="flxpos-fields flxpos-fields--2"><label><span>' . esc_html__( 'Ödeme sayfasındaki adı', 'flx-pos' ) . '</span><input type="text" name="title" value="' . esc_attr( $a['title'] ) . '" placeholder="' . esc_attr( $p['name'] ) . '"></label></div>';
		} else {
			echo '<p class="flxpos-muted">' . esc_html__( 'Taksit yalnız TL ödemelerde. Vade farkı (%) yazdığınız taksitler gösterilir; 0 yazarsanız vade farksız. Sağlayıcı karta göre taksit veriyorsa yalnız kartın desteklediği taksitler çıkar.', 'flx-pos' ) . '</p>';
			echo '<div class="flxpos-inst">';
			foreach ( array( 2, 3, 4, 5, 6, 7, 8, 9, 10, 11, 12 ) as $n ) {
				$v = isset( $a['installments'][ $n ] ) ? flxpos_dec( $a['installments'][ $n ] ) : '';
				/* translators: %d: taksit sayısı */
				echo '<label><span>' . esc_html( sprintf( __( '%d taksit', 'flx-pos' ), $n ) ) . '</span><input type="text" inputmode="decimal" name="installments[' . (int) $n . ']" value="' . esc_attr( $v ) . '" placeholder="—" size="5"> %</label>';
			}
			echo '</div><div class="flxpos-fields flxpos-fields--2"><label><span>' . esc_html__( 'Taksit için en düşük tutar', 'flx-pos' ) . '</span><input type="text" inputmode="decimal" name="inst_min" value="' . esc_attr( $a['inst_min'] ? flxpos_dec( $a['inst_min'] ) : '' ) . '" placeholder="0"></label></div>';
		}
		echo '</section></div>';

		echo '<div class="flxpos-actions"><button class="button button-primary button-large">' . esc_html__( 'Kaydet', 'flx-pos' ) . '</button>';
		if ( '' !== $a['id'] ) {
			echo ' <button type="button" class="button" data-flxpos-test="' . esc_attr( $a['id'] ) . '">' . esc_html__( 'Bağlantıyı test et', 'flx-pos' ) . '</button>';
		}
		echo '<p class="flxpos-test-msg" data-flxpos-msg="' . esc_attr( $a['id'] ) . '" hidden></p></div></form>';
		if ( '' !== $a['id'] ) {
			echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '" class="flxpos-delete" data-flxpos-confirm>';
			wp_nonce_field( 'flxpos_delete_account' );
			echo '<input type="hidden" name="action" value="flxpos_delete_account"><input type="hidden" name="id" value="' . esc_attr( $a['id'] ) . '"><button class="button-link button-link-delete">' . esc_html__( 'Hesabı sil', 'flx-pos' ) . '</button></form>';
		}
	}

	public static function save_account() {
		if ( ! current_user_can( self::cap() ) ) {
			wp_die( esc_html__( 'Bu işlem için yetkiniz yok.', 'flx-pos' ), 403 );
		}
		check_admin_referer( 'flxpos_save_account' );
		$in = wp_unslash( $_POST ); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput -- FLXPOS_Accounts::save temizler
		$id = FLXPOS_Accounts::save( (array) $in );
		if ( is_wp_error( $id ) ) {
			wp_safe_redirect( flxpos_admin_url( array( 'err' => rawurlencode( $id->get_error_message() ) ) ) );
			exit;
		}
		wp_safe_redirect( flxpos_admin_url( array( 'edit' => $id, 'msg' => 'saved' ) ) );
		exit;
	}

	public static function delete_account() {
		if ( ! current_user_can( self::cap() ) ) {
			wp_die( esc_html__( 'Bu işlem için yetkiniz yok.', 'flx-pos' ), 403 );
		}
		check_admin_referer( 'flxpos_delete_account' );
		FLXPOS_Accounts::delete( sanitize_key( wp_unslash( $_POST['id'] ?? '' ) ) );
		wp_safe_redirect( flxpos_admin_url( array( 'msg' => 'deleted' ) ) );
		exit;
	}

	/* ---------------- Ödeme yöntemleri ---------------- */

	private static function state_html( array $c ) {
		$map = array(
			'yes'     => array( '✓', 'is-ok', __( 'Evet', 'flx-pos' ) ),
			'flux'    => array( '✓', 'is-ok', __( 'Evet, Flux POS ile', 'flx-pos' ) ),
			'page'    => array( '↗', 'is-info', __( 'Sağlayıcının sayfasında', 'flx-pos' ) ),
			'no'      => array( '✕', 'is-bad', __( 'Hayır', 'flx-pos' ) ),
			'unknown' => array( '?', 'is-warn', __( 'Bilinmiyor', 'flx-pos' ) ),
		);
		$m   = $map[ $c[0] ] ?? $map['unknown'];
		$tip = $m[2] . ( '' !== $c[1] ? ': ' . $c[1] : '' );
		return '<span class="flxpos-pill ' . esc_attr( $m[1] ) . '" title="' . esc_attr( $tip ) . '"><span aria-hidden="true">' . esc_html( $m[0] ) . '</span><span class="screen-reader-text">' . esc_html( $tip ) . '</span></span>';
	}

	private static function tab_methods() {
		if ( ! function_exists( 'WC' ) ) {
			return;
		}
		$gws    = FLXPOS_Methods::all();
		$labels = FLXPOS_Methods::cap_labels();
		$help   = FLXPOS_Methods::cap_help();
		$bids   = FLXPOS_Methods::block_ids();
		echo '<section class="flxpos-card"><div class="flxpos-card__head"><div><h2>' . esc_html__( 'Sitedeki ödeme yöntemleri', 'flx-pos' ) . '</h2><p class="flxpos-muted">' . esc_html__( 'Her WooCommerce ödeme eklentisi çalışır. Tablo her yöntemin bu sitede neler yapabildiğini gösterir; bir işarete gelince ayrıntı çıkar.', 'flx-pos' ) . '</p></div>';
		echo '<a class="button" href="' . esc_url( admin_url( 'admin.php?page=wc-settings&tab=checkout' ) ) . '">' . esc_html__( 'WooCommerce ödemeleri', 'flx-pos' ) . '</a></div>';
		/* translators: %s: klasik ya da blok */
		echo '<p class="flxpos-muted">' . esc_html( sprintf( __( 'Ödeme sayfası: %s.', 'flx-pos' ), FLXPOS_Methods::block_checkout() ? __( 'ödeme bloğu', 'flx-pos' ) : __( 'klasik', 'flx-pos' ) ) ) . '</p>';
		echo '<div class="flxpos-tablewrap"><table class="flxpos-table flxpos-gw"><thead><tr><th scope="col">' . esc_html__( 'Yöntem', 'flx-pos' ) . '</th>';
		foreach ( $labels as $k => $l ) {
			echo '<th scope="col" title="' . esc_attr( $help[ $k ] ) . '">' . esc_html( $l ) . '</th>';
		}
		echo '</tr></thead><tbody>';
		foreach ( $gws as $id => $gw ) {
			$caps = FLXPOS_Methods::caps( $gw, $bids );
			$on   = 'yes' === $gw->enabled;
			$name = wp_strip_all_tags( (string) ( $gw->get_method_title() ? $gw->get_method_title() : $gw->get_title() ) );
			echo '<tr class="' . ( $on ? '' : 'is-off' ) . '"><th scope="row"><a href="' . esc_url( admin_url( 'admin.php?page=wc-settings&tab=checkout&section=' . $id ) ) . '">' . esc_html( $name ) . '</a> ' . self::badge( $on ? __( 'Açık', 'flx-pos' ) : __( 'Kapalı', 'flx-pos' ), $on ? 'is-ok' : '' ); // phpcs:ignore WordPress.Security.EscapeOutput
			if ( $on && FLXPOS_Methods::hidden( $id ) ) {
				echo ' <span class="flxpos-badge is-warn" title="' . esc_attr__( 'Ödeyen yalnız bir kart yöntemi görür. Ayarlar sekmesinden değiştirin.', 'flx-pos' ) . '">' . esc_html__( 'Ödemede gizli', 'flx-pos' ) . '</span>';
			}
			$ad = FLXPOS_Methods::adapters()[ $id ] ?? null;
			if ( is_array( $ad ) && ! empty( $ad['note'] ) ) {
				echo '<br><small>' . esc_html( (string) $ad['note'] ) . '</small>';
			}
			echo '</th>';
			foreach ( array_keys( $labels ) as $k ) {
				echo '<td data-label="' . esc_attr( $labels[ $k ] ) . '">' . self::state_html( $caps[ $k ] ?? array( 'unknown', '' ) ) . '</td>'; // phpcs:ignore WordPress.Security.EscapeOutput
			}
			echo '</tr>';
		}
		echo '</tbody></table></div>';
		echo '<p class="flxpos-muted">' . self::state_html( array( 'yes', '' ) ) . ' ' . esc_html__( 'evet', 'flx-pos' ) . ' &nbsp; ' . self::state_html( array( 'page', '' ) ) . ' ' . esc_html__( 'sağlayıcının sayfasında', 'flx-pos' ) . ' &nbsp; ' . self::state_html( array( 'no', '' ) ) . ' ' . esc_html__( 'hayır', 'flx-pos' ) . ' &nbsp; ' . self::state_html( array( 'unknown', '' ) ) . ' ' . esc_html__( 'bilinmiyor, deneyin', 'flx-pos' ) . '</p>'; // phpcs:ignore WordPress.Security.EscapeOutput
		echo '</section>';
	}

	/* ---------------- İşlemler ---------------- */

	private static function tab_txns() {
		$q      = array(
			'status'  => sanitize_key( self::get( 'status' ) ),
			'account' => sanitize_key( self::get( 'account' ) ),
			's'       => self::get( 's' ),
			'page'    => max( 1, (int) self::get( 'paged', 1 ) ),
			'per'     => 30,
		);
		$res    = FLXPOS_Txn::query( $q );
		$labels = FLXPOS_Txn::status_labels();
		$cls    = array(
			'paid'     => 'is-ok',
			'failed'   => 'is-bad',
			'pending'  => '',
			'refunded' => 'is-info',
			'partial'  => 'is-info',
			'voided'   => 'is-info',
			'review'   => 'is-warn',
		);
		echo '<section class="flxpos-card"><form method="get" class="flxpos-filter"><input type="hidden" name="page" value="flx-pos"><input type="hidden" name="tab" value="txns">';
		echo '<label class="screen-reader-text" for="flxpos-st">' . esc_html__( 'Durum', 'flx-pos' ) . '</label><select id="flxpos-st" name="status"><option value="">' . esc_html__( 'Tüm durumlar', 'flx-pos' ) . '</option>';
		foreach ( $labels as $k => $l ) {
			echo '<option value="' . esc_attr( $k ) . '"' . selected( $q['status'], $k, false ) . '>' . esc_html( $l ) . '</option>';
		}
		echo '</select> <label class="screen-reader-text" for="flxpos-ac">' . esc_html__( 'Hesap', 'flx-pos' ) . '</label><select id="flxpos-ac" name="account"><option value="">' . esc_html__( 'Tüm hesaplar', 'flx-pos' ) . '</option>';
		foreach ( FLXPOS_Accounts::all() + FLXPOS_Accounts::virtual() as $id => $a ) {
			echo '<option value="' . esc_attr( $id ) . '"' . selected( $q['account'], $id, false ) . '>' . esc_html( FLXPOS_Accounts::label( $a ) ) . '</option>';
		}
		echo '</select> <input type="search" name="s" value="' . esc_attr( $q['s'] ) . '" placeholder="' . esc_attr__( 'Referans, sipariş, kart…', 'flx-pos' ) . '"> <button class="button">' . esc_html__( 'Süz', 'flx-pos' ) . '</button></form>';
		if ( ! $res['rows'] ) {
			echo '<div class="flxpos-empty"><p>' . esc_html__( 'Kayıt yok.', 'flx-pos' ) . '</p></div></section>';
			return;
		}
		echo '<div class="flxpos-tablewrap"><table class="flxpos-table flxpos-txns"><thead><tr><th scope="col">' . esc_html__( 'Tarih', 'flx-pos' ) . '</th><th scope="col">' . esc_html__( 'Referans', 'flx-pos' ) . '</th><th scope="col">' . esc_html__( 'Sipariş', 'flx-pos' ) . '</th><th scope="col">' . esc_html__( 'Hesap', 'flx-pos' ) . '</th><th scope="col" class="num">' . esc_html__( 'Tutar', 'flx-pos' ) . '</th><th scope="col">' . esc_html__( 'Kart', 'flx-pos' ) . '</th><th scope="col">' . esc_html__( 'Durum', 'flx-pos' ) . '</th><th scope="col">' . esc_html__( 'Mesaj', 'flx-pos' ) . '</th></tr></thead><tbody>';
		foreach ( $res['rows'] as $t ) {
			$acc  = FLXPOS_Accounts::get( $t['account'] );
			$kind = 'sale' !== $t['kind'] ? ( 'void' === $t['kind'] ? __( 'İptal', 'flx-pos' ) : __( 'İade', 'flx-pos' ) ) . ' · ' : '';
			$ord  = $t['order_id'] && function_exists( 'wc_get_order' ) ? wc_get_order( $t['order_id'] ) : null;
			echo '<tr><td data-label="' . esc_attr__( 'Tarih', 'flx-pos' ) . '">' . esc_html( flx_date( strtotime( $t['created'] . ' UTC' ), 'datetime' ) ) . '</td>';
			echo '<td data-label="' . esc_attr__( 'Referans', 'flx-pos' ) . '"><code>' . esc_html( $t['ref'] ) . '</code>' . ( '' !== $t['bank_ref'] ? '<br><small>' . esc_html( $t['bank_ref'] ) . '</small>' : '' ) . '</td>';
			echo '<td data-label="' . esc_attr__( 'Sipariş', 'flx-pos' ) . '">' . ( $ord ? '<a href="' . esc_url( $ord->get_edit_order_url() ) . '">#' . esc_html( $ord->get_order_number() ) . '</a>' : '—' ) . '</td>';
			echo '<td data-label="' . esc_attr__( 'Hesap', 'flx-pos' ) . '">' . esc_html( $acc ? FLXPOS_Accounts::label( $acc ) : $t['provider'] ) . '</td>';
			echo '<td data-label="' . esc_attr__( 'Tutar', 'flx-pos' ) . '" class="num">' . esc_html( $kind . self::money( $t['amount'], $t['currency'] ) ) . ( (int) $t['installment'] > 1 ? '<br><small>' . esc_html( sprintf( /* translators: %d: taksit */ __( '%d taksit', 'flx-pos' ), (int) $t['installment'] ) ) . '</small>' : '' ) . ( (float) $t['refunded'] > 0 ? '<br><small>' . esc_html( sprintf( /* translators: %s: tutar */ __( 'iade: %s', 'flx-pos' ), self::money( $t['refunded'], $t['currency'] ) ) ) . '</small>' : '' ) . '</td>';
			echo '<td data-label="' . esc_attr__( 'Kart', 'flx-pos' ) . '"><code>' . esc_html( '' !== $t['card'] ? $t['card'] : '—' ) . '</code></td>';
			echo '<td data-label="' . esc_attr__( 'Durum', 'flx-pos' ) . '">' . self::badge( $labels[ $t['status'] ] ?? $t['status'], $cls[ $t['status'] ] ?? '' ) . ( $t['test'] ? ' ' . self::badge( __( 'Test', 'flx-pos' ), 'is-warn' ) : '' ) . '</td>'; // phpcs:ignore WordPress.Security.EscapeOutput
			echo '<td data-label="' . esc_attr__( 'Mesaj', 'flx-pos' ) . '" class="flxpos-msg">' . esc_html( $t['message'] );
			if ( ! $t['order_id'] && 'sale' === $t['kind'] && in_array( $t['status'], array( 'paid', 'partial' ), true ) ) {
				self::refund_form( $t );
			}
			echo '</td></tr>';
		}
		echo '</tbody></table></div>';
		$pages = (int) ceil( $res['total'] / 30 );
		if ( $pages > 1 ) {
			echo '<p class="flxpos-pages">' . paginate_links( // phpcs:ignore WordPress.Security.EscapeOutput
				array(
					'base'    => add_query_arg( 'paged', '%#%' ),
					'current' => $q['page'],
					'total'   => $pages,
				)
			) . '</p>';
		}
		echo '</section>';
	}

	/** Siparişsiz işlem için iade formu (siparişliler WooCommerce iade düğmesiyle). */
	private static function refund_form( array $t ) {
		echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '" class="flxpos-refund" data-flxpos-refund>';
		wp_nonce_field( 'flxpos_refund_' . $t['id'] );
		echo '<input type="hidden" name="action" value="flxpos_refund"><input type="hidden" name="txn" value="' . (int) $t['id'] . '"><input type="text" inputmode="decimal" name="amount" size="7" value="' . esc_attr( flxpos_dec( (float) $t['amount'] - (float) $t['refunded'] ) ) . '"> <button class="button button-small">' . esc_html__( 'İade et', 'flx-pos' ) . '</button></form>';
	}

	public static function refund() {
		$id = isset( $_POST['txn'] ) ? absint( $_POST['txn'] ) : 0;
		if ( ! current_user_can( self::cap() ) ) {
			wp_die( esc_html__( 'Bu işlem için yetkiniz yok.', 'flx-pos' ), 403 );
		}
		check_admin_referer( 'flxpos_refund_' . $id );
		$amt = isset( $_POST['amount'] ) ? (float) str_replace( ',', '.', sanitize_text_field( wp_unslash( $_POST['amount'] ) ) ) : null;
		$r   = flxpos_refund( $id, $amt, __( 'Panelden iade', 'flx-pos' ) );
		wp_safe_redirect( flxpos_admin_url( is_wp_error( $r ) ? array( 'tab' => 'txns', 'err' => rawurlencode( $r->get_error_message() ) ) : array( 'tab' => 'txns', 'msg' => 'refunded' ) ) );
		exit;
	}

	/* ---------------- Ayarlar ---------------- */

	private static function tab_settings() {
		$s      = flxpos_settings();
		$cards  = array_filter( FLXPOS_Accounts::active(), static fn( $a ) => 'wallet' !== ( FLXPOS_Providers::get( $a['provider'] )['group'] ?? '' ) );
		$curs   = array();
		foreach ( $cards as $a ) {
			$p    = FLXPOS_Providers::get( $a['provider'] );
			$curs = array_merge( $curs, $a['currencies'] ? $a['currencies'] : (array) $p['currencies'] );
		}
		$curs = array_values( array_unique( $curs ) );
		echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '" class="flxpos-form">';
		wp_nonce_field( 'flxpos_save_settings' );
		echo '<input type="hidden" name="action" value="flxpos_save_settings"><div class="flxpos-grid">';

		echo '<section class="flxpos-card flxpos-span-2"><h2>' . esc_html__( 'Kart ile öde', 'flx-pos' ) . '</h2><div class="flxpos-fields flxpos-fields--2">';
		echo '<label><span>' . esc_html__( 'Ödeme sayfasındaki adı', 'flx-pos' ) . '</span><input type="text" name="card_title" value="' . esc_attr( $s['card_title'] ) . '" placeholder="' . esc_attr__( 'Banka / Kredi Kartı', 'flx-pos' ) . '"></label>';
		echo '<label><span>' . esc_html__( 'Açıklama', 'flx-pos' ) . '</span><input type="text" name="card_desc" value="' . esc_attr( $s['card_desc'] ) . '"></label>';
		echo '<label><span>' . esc_html__( 'Kartları çeken hesap', 'flx-pos' ) . '</span><select name="card_account"><option value="">' . esc_html__( 'İlk uygun hesap', 'flx-pos' ) . '</option>';
		foreach ( $cards as $id => $a ) {
			echo '<option value="' . esc_attr( $id ) . '"' . selected( $s['card_account'], $id, false ) . '>' . esc_html( FLXPOS_Accounts::label( $a ) ) . '</option>';
		}
		echo '</select></label>';
		if ( class_exists( 'FLXPOS_Methods' ) && function_exists( 'WC' ) ) {
			echo '<label><span>' . esc_html__( 'Kart ödeme yöntemi', 'flx-pos' ) . '</span><select name="card_method">';
			$opts = array(
				'auto'   => __( 'Otomatik (Flux POS hesabı varsa o)', 'flx-pos' ),
				'flxpos' => 'Flux POS',
			) + FLXPOS_Methods::third_party_cards() + array( 'all' => __( 'Hepsini göster', 'flx-pos' ) );
			foreach ( $opts as $k => $l ) {
				echo '<option value="' . esc_attr( $k ) . '"' . selected( $s['card_method'], $k, false ) . '>' . esc_html( $l ) . '</option>';
			}
			echo '</select><small>' . esc_html__( 'Birden çok kartlı POS açıksa ödeyen yalnız birini görür.', 'flx-pos' ) . '</small></label>';
		}
		echo '</div>';
		if ( count( $curs ) > 1 && count( $cards ) > 1 ) {
			echo '<h3 class="flxpos-sub">' . esc_html__( 'Para birimine göre hesap', 'flx-pos' ) . '</h3><div class="flxpos-fields flxpos-fields--2">';
			foreach ( $curs as $c ) {
				echo '<label><span>' . esc_html( $c ) . '</span><select name="currency_routes[' . esc_attr( $c ) . ']"><option value="">' . esc_html__( 'Varsayılan', 'flx-pos' ) . '</option>';
				foreach ( $cards as $id => $a ) {
					if ( FLXPOS_Accounts::accepts( $a, $c ) ) {
						echo '<option value="' . esc_attr( $id ) . '"' . selected( $s['currency_routes'][ $c ] ?? '', $id, false ) . '>' . esc_html( FLXPOS_Accounts::label( $a ) ) . '</option>';
					}
				}
				echo '</select></label>';
			}
			echo '</div>';
		}
		echo '</section>';

		echo '<section class="flxpos-card"><h2>' . esc_html__( 'Görünüm ve kayıt', 'flx-pos' ) . '</h2><div class="flxpos-checks flxpos-checks--col">';
		$checks = array(
			'show_brands'    => array( __( 'Kart markası logosu', 'flx-pos' ), __( 'Kart numarası yazılırken Visa, Mastercard, Troy…', 'flx-pos' ) ),
			'block_fallback' => array( __( 'Diğer ödeme eklentilerini blok ödemede göster', 'flx-pos' ), __( 'Kendi blok desteği olmayan, kartı kendi sayfasında alan eklentiler.', 'flx-pos' ) ),
			'save_log'       => array( __( 'Ayrıntılı işlem günlüğü', 'flx-pos' ), __( 'Banka yanıtları işlem kaydında saklanır; kart verisi ve anahtarlar yazılmaz.', 'flx-pos' ) ),
		);
		foreach ( $checks as $k => $l ) {
			echo '<label class="flxpos-check"><input type="checkbox" name="' . esc_attr( $k ) . '" value="1"' . checked( ! empty( $s[ $k ] ), true, false ) . '><span>' . esc_html( $l[0] ) . '<small>' . esc_html( $l[1] ) . '</small></span></label>';
		}
		echo '</div></section></div><div class="flxpos-actions"><button class="button button-primary button-large">' . esc_html__( 'Kaydet', 'flx-pos' ) . '</button></div></form>';
	}

	public static function save_settings() {
		if ( ! current_user_can( self::cap() ) ) {
			wp_die( esc_html__( 'Bu işlem için yetkiniz yok.', 'flx-pos' ), 403 );
		}
		check_admin_referer( 'flxpos_save_settings' );
		$in     = wp_unslash( $_POST ); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput -- aşağıda temizlenir
		$routes = array();
		foreach ( (array) ( $in['currency_routes'] ?? array() ) as $c => $id ) {
			if ( '' !== (string) $id ) {
				$routes[ strtoupper( sanitize_key( $c ) ) ] = sanitize_key( $id );
			}
		}
		$cm  = sanitize_text_field( (string) ( $in['card_method'] ?? 'auto' ) );
		$out = array(
			'card_title'      => sanitize_text_field( (string) ( $in['card_title'] ?? '' ) ),
			'card_desc'       => sanitize_text_field( (string) ( $in['card_desc'] ?? '' ) ),
			'card_account'    => sanitize_key( (string) ( $in['card_account'] ?? '' ) ),
			'currency_routes' => $routes,
			'card_method'     => '' !== $cm ? $cm : 'auto',
			'block_fallback'  => empty( $in['block_fallback'] ) ? 0 : 1,
			'show_brands'     => empty( $in['show_brands'] ) ? 0 : 1,
			'save_log'        => empty( $in['save_log'] ) ? 0 : 1,
		);
		update_option( 'flxpos_settings', $out, false );
		wp_safe_redirect( flxpos_admin_url( array( 'tab' => 'settings', 'msg' => 'saved' ) ) );
		exit;
	}

	/* ---------------- Sipariş kutusu ---------------- */

	public static function order_box() {
		$screen = function_exists( 'wc_get_page_screen_id' ) ? wc_get_page_screen_id( 'shop-order' ) : 'shop_order';
		add_meta_box( 'flxpos-order', __( 'Kart ödemesi', 'flx-pos' ), array( __CLASS__, 'order_box_html' ), $screen, 'side', 'default' );
	}

	public static function order_box_html( $post_or_order ) {
		$order = $post_or_order instanceof WC_Order ? $post_or_order : wc_get_order( $post_or_order->ID );
		$rows  = $order ? FLXPOS_Txn::for_order( $order->get_id() ) : array();
		if ( ! $rows ) {
			echo '<p class="description">' . esc_html__( 'Bu siparişte Flux POS işlemi yok.', 'flx-pos' ) . '</p>';
			return;
		}
		$labels = FLXPOS_Txn::status_labels();
		echo '<ul>';
		foreach ( $rows as $t ) {
			echo '<li><strong>' . esc_html( $labels[ $t['status'] ] ?? $t['status'] ) . '</strong> · ' . esc_html( self::money( $t['amount'], $t['currency'] ) ) . ( $t['test'] ? ' · ' . esc_html__( 'test', 'flx-pos' ) : '' ) . '<br><small><code>' . esc_html( $t['ref'] ) . '</code> ' . esc_html( $t['card'] ) . ( '' !== $t['message'] ? ' · ' . esc_html( $t['message'] ) : '' ) . '</small></li>';
		}
		echo '</ul><p><a href="' . esc_url( flxpos_admin_url( array( 'tab' => 'txns', 's' => $order->get_id() ) ) ) . '">' . esc_html__( 'Tüm işlemler', 'flx-pos' ) . '</a></p>';
	}
}
