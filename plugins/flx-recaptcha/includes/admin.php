<?php
/**
 * Yönetim: "reCAPTCHA" sayfası (anahtarlar ve test, korunan formlar, ayarlar), kaydetme ve test isteği.
 *
 * @package FLX_Recaptcha
 */

defined( 'ABSPATH' ) || exit;

final class FLXRC_Admin {

	const CAP = 'manage_options';

	public static function init() {
		add_action( 'admin_menu', array( __CLASS__, 'menu' ), 60 );
		add_action( 'admin_enqueue_scripts', array( __CLASS__, 'assets' ) );
		add_action( 'admin_head', array( __CLASS__, 'menu_logo' ) );
		add_action( 'admin_post_flxrc_save', array( __CLASS__, 'save' ) );
		add_action( 'wp_ajax_flxrc_test', array( __CLASS__, 'ajax_test' ) );
		add_action( 'admin_notices', array( __CLASS__, 'nag' ) );
	}

	public static function menu() {
		add_menu_page( __( 'reCAPTCHA', 'flx-recaptcha' ), __( 'reCAPTCHA', 'flx-recaptcha' ), self::CAP, 'flx-recaptcha', array( __CLASS__, 'render' ), 'none', 81 );
	}

	/** Renkli menü logosu (WP data-URI SVG'yi tek renge boyar). */
	public static function menu_logo() {
		echo '<style>#toplevel_page_flx-recaptcha .wp-menu-image{background:url(' . esc_url( FLXRC_URL . 'assets/icon.svg?ver=' . FLXRC_VERSION ) . ') center/20px 20px no-repeat}</style>';
	}

	private static function is_screen() {
		return isset( $_GET['page'] ) && 'flx-recaptcha' === $_GET['page']; // phpcs:ignore WordPress.Security.NonceVerification
	}

	public static function assets() {
		if ( ! self::is_screen() ) {
			return;
		}
		wp_enqueue_style( 'flxrc-admin', FLXRC_URL . 'assets/admin.css', array(), FLXRC_VERSION );
		wp_enqueue_script( 'flxrc-admin', FLXRC_URL . 'assets/admin.js', array(), FLXRC_VERSION, true );
		wp_localize_script(
			'flxrc-admin',
			'flxrcAdmin',
			array(
				'ajax'  => admin_url( 'admin-ajax.php' ),
				'nonce' => wp_create_nonce( 'flxrc_test' ),
				'api'   => flxrc_api_host() . '/recaptcha/api.js',
				'hl'    => substr( get_user_locale(), 0, 2 ),
				'dark'  => 0,
				'i18n'  => array(
					'noKey'    => __( 'Önce site anahtarını yazın.', 'flx-recaptcha' ),
					'noSecret' => __( 'Gizli anahtarı yazın.', 'flx-recaptcha' ),
					'loading'  => __( 'Google reCAPTCHA yükleniyor…', 'flx-recaptcha' ),
					'tick'     => __( 'Kutucuğu işaretleyin; işaretlenince test kendiliğinden sürer.', 'flx-recaptcha' ),
					'testing'  => __( 'Google ile doğrulanıyor…', 'flx-recaptcha' ),
					'loadFail' => __( 'Google reCAPTCHA yüklenemedi. Site anahtarını ve alan adını kontrol edin; Google erişilemiyorsa "recaptcha.net" seçeneğini deneyin.', 'flx-recaptcha' ),
					'reload'   => __( 'Site anahtarı değişti; testi yeni anahtarla yapmak için sayfa yenileniyor…', 'flx-recaptcha' ),
					'fail'     => __( 'Bir şey ters gitti. Tekrar deneyin.', 'flx-recaptcha' ),
					'saved'    => __( 'Anahtarlar kaydedildi; koruma açık.', 'flx-recaptcha' ),
				),
			)
		);
	}

	/** Anahtar yoksa ya da test edilmediyse eklentiler ekranında küçük uyarı. */
	public static function nag() {
		$screen = function_exists( 'get_current_screen' ) ? get_current_screen() : null;
		if ( ! $screen || 'plugins' !== $screen->id || ! current_user_can( self::CAP ) || flxrc_keys_verified() || flxrc_disabled_by_constant() ) {
			return;
		}
		echo '<div class="notice notice-info"><p><strong>Flux reCAPTCHA:</strong> ' . esc_html__( 'Formlar henüz korunmuyor. Google reCAPTCHA anahtarlarını girip test edin.', 'flx-recaptcha' ) . ' <a href="' . esc_url( flxrc_admin_url() ) . '">' . esc_html__( 'Ayarlara git', 'flx-recaptcha' ) . '</a></p></div>';
	}

	/* ---------------------------------------------------------------- */
	/* Sayfa                                                             */
	/* ---------------------------------------------------------------- */

	public static function render() {
		if ( ! current_user_can( self::CAP ) ) {
			wp_die( esc_html__( 'Bu işlem için yetkiniz yok.', 'flx-recaptcha' ), 403 );
		}
		$s        = flxrc_settings();
		$ctxs     = flxrc_contexts();
		$stats    = flxrc_stats_totals( 30 );
		$verified = flxrc_keys_verified();
		$locked   = flxrc_disabled_by_constant();
		$const_k  = ( defined( 'FLXRC_SITE_KEY' ) && FLXRC_SITE_KEY ) || ( defined( 'FLXRC_SECRET_KEY' ) && FLXRC_SECRET_KEY );
		$on       = 0;
		$avail    = 0;
		foreach ( $ctxs as $k => $c ) {
			if ( ! empty( $c['available'] ) ) {
				++$avail;
				$on += flxrc_context_on( $k ) ? 1 : 0;
			}
		}

		echo '<div class="wrap flxrc">';
		echo '<div class="flxrc-head"><div><h1>' . esc_html__( 'reCAPTCHA', 'flx-recaptcha' ) . '</h1><p class="flxrc-lead">' . esc_html__( 'Giriş, kayıt, yorum, ödeme, bağış ve form gönderimlerini botlara karşı Google reCAPTCHA ile korur. Hangi formun korunacağını aşağıdan seçin.', 'flx-recaptcha' ) . '</p></div></div>';
		self::notices();

		if ( $locked ) {
			echo '<div class="flxrc-callout is-warn">' . flxrc_icon( 'alert', 18 ) . '<div><strong>' . esc_html__( 'Koruma kurtarma sabitiyle kapalı.', 'flx-recaptcha' ) . '</strong> ' . esc_html__( 'wp-config.php dosyasındaki FLXRC_DISABLE satırını silince koruma yeniden çalışır.', 'flx-recaptcha' ) . '</div></div>'; // phpcs:ignore WordPress.Security.EscapeOutput
		} elseif ( ! $verified ) {
			echo '<div class="flxrc-callout is-info">' . flxrc_icon( 'info', 18 ) . '<div><strong>' . esc_html__( 'Koruma henüz çalışmıyor.', 'flx-recaptcha' ) . '</strong> ' . esc_html__( 'Anahtarları girip "Test et"e basın. Test geçmeden hiçbir forma doğrulama eklenmez; böylece yanlış anahtarla giriş ekranına kilitlenmezsiniz.', 'flx-recaptcha' ) . '</div></div>'; // phpcs:ignore WordPress.Security.EscapeOutput
		}
		$err = flxrc_status( 'last_error' );
		if ( $verified && is_array( $err ) && ! empty( $err['time'] ) && $err['time'] > time() - DAY_IN_SECONDS ) {
			$what = 'config' === ( $err['kind'] ?? '' ) ? __( 'Google gizli anahtarı reddetti', 'flx-recaptcha' ) : __( 'Google\'a ulaşılamadı', 'flx-recaptcha' );
			echo '<div class="flxrc-callout is-warn">' . flxrc_icon( 'alert', 18 ) . '<div><strong>' . esc_html( $what ) . '</strong> (' . esc_html( wp_date( 'd.m.Y H:i', (int) $err['time'] ) ) . '): <code>' . esc_html( (string) ( $err['why'] ?? '' ) ) . '</code> ' . esc_html( $s['fail_open'] ? __( 'O sırada gönderimler doğrulamasız geçirildi.', 'flx-recaptcha' ) : __( 'O sırada gönderimler engellendi.', 'flx-recaptcha' ) ) . '</div></div>'; // phpcs:ignore WordPress.Security.EscapeOutput
		}

		/* Özet */
		$state = $locked ? array( __( 'Kapalı', 'flx-recaptcha' ), 'is-bad', 'FLXRC_DISABLE' ) : ( $verified ? array( __( 'Açık', 'flx-recaptcha' ), 'is-ok', 'v3' === flxrc_version() ? __( 'v3 · görünmez', 'flx-recaptcha' ) : __( 'v2 · kutucuk', 'flx-recaptcha' ) ) : array( __( 'Test bekliyor', 'flx-recaptcha' ), 'is-warn', __( 'Anahtarları test edin', 'flx-recaptcha' ) ) );
		echo '<div class="flxrc-kpis">';
		self::kpi( 'shield', __( 'Koruma', 'flx-recaptcha' ), $state[0], $state[2], $state[1] );
		/* translators: 1: protected forms, 2: available forms */
		self::kpi( 'list', __( 'Korunan form', 'flx-recaptcha' ), sprintf( __( '%1$s / %2$s', 'flx-recaptcha' ), number_format_i18n( $on ), number_format_i18n( $avail ) ), __( 'kurulu eklentilerdeki formlar', 'flx-recaptcha' ) );
		self::kpi( 'user', __( 'Geçen gönderim', 'flx-recaptcha' ), number_format_i18n( $stats['_all'][0] ), __( 'son 30 gün', 'flx-recaptcha' ) );
		self::kpi( 'bot', __( 'Engellenen', 'flx-recaptcha' ), number_format_i18n( $stats['_all'][1] ), __( 'son 30 gün', 'flx-recaptcha' ), $stats['_all'][1] ? 'is-warn' : '' );
		echo '</div>';

		echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '" class="flxrc-form" data-flxrc-form>';
		wp_nonce_field( 'flxrc_save' );
		echo '<input type="hidden" name="action" value="flxrc_save">';
		echo '<div class="flxrc-grid">';

		/* Anahtarlar */
		echo '<section class="flxrc-card" id="flxrc-keys"><div class="flxrc-card__head"><h2>' . flxrc_icon( 'key', 16 ) . esc_html__( 'Anahtarlar', 'flx-recaptcha' ) . '</h2>'; // phpcs:ignore WordPress.Security.EscapeOutput
		echo $verified ? '<span class="flxrc-badge is-ok">' . flxrc_icon( 'check', 12 ) . esc_html__( 'Test edildi', 'flx-recaptcha' ) . '</span>' : '<span class="flxrc-badge is-warn">' . esc_html__( 'Test edilmedi', 'flx-recaptcha' ) . '</span>'; // phpcs:ignore WordPress.Security.EscapeOutput
		echo '</div>';
		echo '<p class="flxrc-muted">' . sprintf(
			/* translators: %s: Google reCAPTCHA admin console link */
			esc_html__( 'Anahtarları %s alın: reCAPTCHA türünü seçin ve alan adınızı ekleyin. Site anahtarı ile gizli anahtar aynı kayda ait olmalı.', 'flx-recaptcha' ),
			'<a href="https://www.google.com/recaptcha/admin/create" target="_blank" rel="noopener">' . esc_html__( 'Google reCAPTCHA konsolundan', 'flx-recaptcha' ) . flxrc_icon( 'ext', 12 ) . '</a>' // phpcs:ignore WordPress.Security.EscapeOutput
		) . '</p>';
		echo '<fieldset class="flxrc-choice"><legend class="screen-reader-text">' . esc_html__( 'reCAPTCHA türü', 'flx-recaptcha' ) . '</legend>';
		$vers = array(
			'v3' => array( __( 'v3 · Görünmez', 'flx-recaptcha' ), __( 'Ziyaretçiye hiçbir şey sorulmaz; Google 0–1 arası puan verir. Önerilen.', 'flx-recaptcha' ) ),
			'v2' => array( __( 'v2 · "Ben robot değilim"', 'flx-recaptcha' ), __( 'Formda kutucuk görünür; şüpheli ziyaretçiye resim sorulur.', 'flx-recaptcha' ) ),
		);
		foreach ( $vers as $k => $v ) {
			echo '<label class="flxrc-choice__item"><input type="radio" name="version" value="' . esc_attr( $k ) . '"' . checked( flxrc_version(), $k, false ) . '><span><strong>' . esc_html( $v[0] ) . '</strong><small>' . esc_html( $v[1] ) . '</small></span></label>';
		}
		echo '</fieldset>';
		$secret_saved = '' !== flxrc_secret();
		echo '<div class="flxrc-fields">';
		echo '<label><span>' . esc_html__( 'Site anahtarı', 'flx-recaptcha' ) . '</span><input type="text" name="site_key" value="' . esc_attr( (string) $s['site_key'] ) . '" autocomplete="off" spellcheck="false" class="code" data-flxrc-site' . ( defined( 'FLXRC_SITE_KEY' ) && FLXRC_SITE_KEY ? ' disabled placeholder="FLXRC_SITE_KEY" data-key="' . esc_attr( flxrc_site_key() ) . '"' : '' ) . '></label>';
		echo '<label><span>' . esc_html__( 'Gizli anahtar', 'flx-recaptcha' ) . '</span><input type="password" name="secret" value="" autocomplete="new-password" spellcheck="false" class="code" data-flxrc-secret placeholder="' . esc_attr( $secret_saved ? sprintf( /* translators: %s: masked key */ __( 'Kayıtlı (%s). Değiştirmek için yazın.', 'flx-recaptcha' ), flxrc_mask( flxrc_secret() ) ) : __( 'Gizli anahtarı yapıştırın', 'flx-recaptcha' ) ) . '"' . ( defined( 'FLXRC_SECRET_KEY' ) && FLXRC_SECRET_KEY ? ' disabled' : '' ) . '><small>' . esc_html__( 'Bir kez girilir, şifreli saklanır ve bir daha gösterilmez.', 'flx-recaptcha' ) . '</small></label>';
		echo '</div>';
		if ( $const_k ) {
			echo '<p class="flxrc-muted">' . esc_html__( 'Anahtarlardan biri wp-config.php sabitinden geliyor (FLXRC_SITE_KEY / FLXRC_SECRET_KEY); paneldeki değer kullanılmaz.', 'flx-recaptcha' ) . '</p>';
		}
		echo '<div class="flxrc-test" data-flxrc-test><div class="flxrc-test__row"><button type="button" class="button button-primary" data-flxrc-run>' . flxrc_icon( 'play', 14 ) . esc_html__( 'Test et ve kaydet', 'flx-recaptcha' ) . '</button><span class="flxrc-muted">' . esc_html__( 'Google\'dan gerçek bir doğrulama alır; geçerse anahtarlar kaydedilir ve koruma açılır.', 'flx-recaptcha' ) . '</span></div><div class="flxrc-test__box" data-flxrc-box hidden></div><p class="flxrc-test__msg" data-flxrc-msg role="status" hidden></p></div>'; // phpcs:ignore WordPress.Security.EscapeOutput
		$last = flxrc_status( 'last_test' );
		if ( is_array( $last ) && ! empty( $last['time'] ) ) {
			echo '<p class="flxrc-muted">' . esc_html( sprintf( /* translators: 1: date, 2: result */ __( 'Son test: %1$s · %2$s', 'flx-recaptcha' ), wp_date( 'd.m.Y H:i', (int) $last['time'] ), (string) $last['msg'] ) ) . '</p>';
		}
		echo '</section>';

		/* Korunan formlar */
		echo '<section class="flxrc-card flxrc-span-2" id="flxrc-forms"><div class="flxrc-card__head"><h2>' . flxrc_icon( 'list', 16 ) . esc_html__( 'Korunan formlar', 'flx-recaptcha' ) . '</h2><span class="flxrc-muted">' . esc_html__( 'Son 30 gün: geçen / engellenen', 'flx-recaptcha' ) . '</span></div>'; // phpcs:ignore WordPress.Security.EscapeOutput
		foreach ( flxrc_groups() as $gid => $glabel ) {
			$rows = array_filter(
				$ctxs,
				function ( $c ) use ( $gid ) {
					return $c['group'] === $gid || ( 'other' === $gid && ! isset( flxrc_groups()[ $c['group'] ] ) );
				}
			);
			if ( ! $rows ) {
				continue;
			}
			echo '<h3 class="flxrc-sub">' . esc_html( $glabel ) . '</h3><ul class="flxrc-rows">';
			foreach ( $rows as $k => $c ) {
				$av = ! empty( $c['available'] );
				$st = $stats[ $k ] ?? array( 0, 0 );
				echo '<li class="flxrc-row' . ( $av ? '' : ' is-off' ) . '"><div class="flxrc-row__text"><strong>' . esc_html( $c['label'] ) . '</strong>' . ( $av ? '' : ' <span class="flxrc-badge">' . esc_html__( 'kurulu değil', 'flx-recaptcha' ) . '</span>' ) . '<small>' . esc_html( $c['desc'] ) . '</small></div>';
				echo '<div class="flxrc-row__stat" title="' . esc_attr__( 'Son 30 gün: geçen / engellenen', 'flx-recaptcha' ) . '"><span class="is-ok">' . esc_html( number_format_i18n( $st[0] ) ) . '</span><span class="flxrc-sep">/</span><span class="' . ( $st[1] ? 'is-bad' : '' ) . '">' . esc_html( number_format_i18n( $st[1] ) ) . '</span></div>';
				echo '<label class="flxrc-switch"><input type="hidden" name="forms[' . esc_attr( $k ) . ']" value="0"><input type="checkbox" name="forms[' . esc_attr( $k ) . ']" value="1"' . checked( flxrc_context_on( $k ), true, false ) . ( $av ? '' : ' disabled' ) . '><span class="flxrc-switch__ui" aria-hidden="true"></span><span class="screen-reader-text">' . esc_html( $c['label'] ) . '</span></label></li>';
			}
			echo '</ul>';
		}
		if ( defined( 'FLXF_PLUGIN_FILE' ) && flxrc_context_on( 'forms' ) ) {
			echo '<p class="flxrc-muted flxrc-note">' . flxrc_icon( 'info', 14 ) . esc_html__( 'Flux Forms\'ta formun kendi reCAPTCHA seçeneği açıksa kapatın; iki doğrulama aynı sayfada çakışır.', 'flx-recaptcha' ) . '</p>'; // phpcs:ignore WordPress.Security.EscapeOutput
		}
		echo '</section>';

		/* Ayarlar */
		echo '<section class="flxrc-card flxrc-span-3" id="flxrc-settings"><div class="flxrc-card__head"><h2>' . flxrc_icon( 'sliders', 16 ) . esc_html__( 'Ayarlar', 'flx-recaptcha' ) . '</h2></div><div class="flxrc-fields flxrc-fields--grid">'; // phpcs:ignore WordPress.Security.EscapeOutput
		$th = (float) $s['threshold'];
		echo '<label data-flxrc-only="v3"><span>' . esc_html__( 'Bot eşiği (v3)', 'flx-recaptcha' ) . ' <output data-flxrc-out>' . esc_html( number_format_i18n( $th, 1 ) ) . '</output></span><input type="range" name="threshold" min="0.1" max="0.9" step="0.1" value="' . esc_attr( (string) $th ) . '" data-flxrc-range><small>' . esc_html__( 'Puanı bunun altında kalan gönderim engellenir. 0,5 çoğu site için uygun; gerçek kişiler takılıyorsa düşürün.', 'flx-recaptcha' ) . '</small></label>';
		echo '<label data-flxrc-only="v2"><span>' . esc_html__( 'Kutucuk görünümü (v2)', 'flx-recaptcha' ) . '</span><select name="v2_theme">';
		foreach ( array( 'auto' => __( 'Otomatik (sitenin koyu moduna uyar)', 'flx-recaptcha' ), 'light' => __( 'Açık tema', 'flx-recaptcha' ), 'dark' => __( 'Koyu tema', 'flx-recaptcha' ) ) as $k => $l ) {
			echo '<option value="' . esc_attr( $k ) . '"' . selected( $s['v2_theme'], $k, false ) . '>' . esc_html( $l ) . '</option>';
		}
		echo '</select></label>';
		echo '<label data-flxrc-only="v2"><span>' . esc_html__( 'Kutucuk boyutu (v2)', 'flx-recaptcha' ) . '</span><select name="v2_size"><option value="normal"' . selected( $s['v2_size'], 'normal', false ) . '>' . esc_html__( 'Normal', 'flx-recaptcha' ) . '</option><option value="compact"' . selected( $s['v2_size'], 'compact', false ) . '>' . esc_html__( 'Küçük', 'flx-recaptcha' ) . '</option></select></label>';
		echo '<label><span>' . esc_html__( 'Google adresi', 'flx-recaptcha' ) . '</span><select name="domain"><option value="google"' . selected( $s['domain'], 'google', false ) . '>www.google.com</option><option value="recaptcha"' . selected( $s['domain'], 'recaptcha', false ) . '>www.recaptcha.net</option></select><small>' . esc_html__( 'google.com\'un engellendiği ağlarda recaptcha.net kullanın.', 'flx-recaptcha' ) . '</small></label>';
		echo '</div><div class="flxrc-checks">';
		self::check( 'skip_logged_in', $s, __( 'Giriş yapmış kullanıcılara sorma', 'flx-recaptcha' ), __( 'Yorum, ödeme, bağış ve formlarda üyelere doğrulama yapılmaz. Giriş ve kayıt formları her zaman korunur.', 'flx-recaptcha' ) );
		self::check( 'fail_open', $s, __( 'Google\'a ulaşılamazsa gönderimi geçir', 'flx-recaptcha' ), __( 'Önerilen. Kapalıysa Google kesintisinde hiçbir korunan form gönderilemez (yönetici girişi dahil).', 'flx-recaptcha' ) );
		self::check( 'hide_badge', $s, __( 'v3 rozetini gizle', 'flx-recaptcha' ), __( 'Sağ alttaki reCAPTCHA rozeti gizlenir; Google\'ın istediği bilgi metni korunan formların altına eklenir.', 'flx-recaptcha' ), 'v3' );
		echo '</div>';
		$ip = flxrc_client_ip();
		echo '<label class="flxrc-ips"><span>' . esc_html__( 'Doğrulama yapılmayan IP adresleri', 'flx-recaptcha' ) . '</span><textarea name="allow_ips" rows="3" class="code" placeholder="203.0.113.7&#10;198.51.100.0/24">' . esc_textarea( (string) $s['allow_ips'] ) . '</textarea><small>' . esc_html( sprintf( /* translators: %s: current IP */ __( 'Satır başına bir IP ya da aralık (CIDR). Ofis, test ve izleme servisleri için. Şu anki IP adresiniz: %s', 'flx-recaptcha' ), $ip ? $ip : '?' ) ) . '</small></label>';
		echo '<p class="flxrc-muted flxrc-note">' . flxrc_icon( 'info', 14 ) . esc_html__( 'Giriş ekranına kilitlenirseniz wp-config.php dosyasına define( \'FLXRC_DISABLE\', true ); satırını ekleyin; koruma tamamen kapanır.', 'flx-recaptcha' ) . '</p>'; // phpcs:ignore WordPress.Security.EscapeOutput
		echo '</section>';

		echo '</div>';
		echo '<p class="flxrc-actions"><button class="button button-primary button-large">' . esc_html__( 'Ayarları kaydet', 'flx-recaptcha' ) . '</button></p>';
		echo '</form></div>';
	}

	private static function kpi( $icon, $label, $value, $hint = '', $cls = '' ) {
		echo '<div class="flxrc-kpi ' . esc_attr( $cls ) . '"><span class="flxrc-kpi__icon">' . flxrc_icon( $icon, 18 ) . '</span><div class="flxrc-kpi__body"><span class="flxrc-kpi__label">' . esc_html( $label ) . '</span><span class="flxrc-kpi__value">' . esc_html( $value ) . '</span>' . ( '' !== $hint ? '<span class="flxrc-kpi__hint">' . esc_html( $hint ) . '</span>' : '' ) . '</div></div>'; // phpcs:ignore WordPress.Security.EscapeOutput
	}

	private static function check( $key, $s, $label, $help, $only = '' ) {
		echo '<label class="flxrc-check"' . ( $only ? ' data-flxrc-only="' . esc_attr( $only ) . '"' : '' ) . '><input type="hidden" name="' . esc_attr( $key ) . '" value="0"><input type="checkbox" name="' . esc_attr( $key ) . '" value="1"' . checked( (int) $s[ $key ], 1, false ) . '><span><strong>' . esc_html( $label ) . '</strong><small>' . esc_html( $help ) . '</small></span></label>';
	}

	private static function notices() {
		$msg = isset( $_GET['flxrc_msg'] ) ? sanitize_key( $_GET['flxrc_msg'] ) : ''; // phpcs:ignore WordPress.Security.NonceVerification
		$map = array(
			'saved'      => array( 'is-ok', __( 'Kaydedildi.', 'flx-recaptcha' ) ),
			'unverified' => array( 'is-warn', __( 'Kaydedildi. Anahtarlar değiştiği için koruma test edilene kadar kapalı.', 'flx-recaptcha' ) ),
		);
		if ( isset( $map[ $msg ] ) ) {
			echo '<div class="flxrc-notice ' . esc_attr( $map[ $msg ][0] ) . '" role="status">' . flxrc_icon( 'is-ok' === $map[ $msg ][0] ? 'check' : 'alert', 16 ) . esc_html( $map[ $msg ][1] ) . '</div>'; // phpcs:ignore WordPress.Security.EscapeOutput
		}
	}

	/* ---------------------------------------------------------------- */
	/* Kaydet                                                            */
	/* ---------------------------------------------------------------- */

	private static function post( $key, $default = '' ) {
		return isset( $_POST[ $key ] ) ? wp_unslash( $_POST[ $key ] ) : $default; // phpcs:ignore WordPress.Security
	}

	public static function save() {
		check_admin_referer( 'flxrc_save' );
		if ( ! current_user_can( self::CAP ) ) {
			wp_die( esc_html__( 'Bu işlem için yetkiniz yok.', 'flx-recaptcha' ), 403 );
		}
		$s    = flxrc_settings();
		$was  = flxrc_keys_verified();
		$s    = self::apply_keys( $s, (string) self::post( 'version', 'v3' ), self::post( 'site_key', null ), (string) self::post( 'secret' ) );
		$s['threshold']      = max( 0.1, min( 0.9, round( (float) self::post( 'threshold', 0.5 ), 1 ) ) );
		$s['domain']         = 'recaptcha' === self::post( 'domain' ) ? 'recaptcha' : 'google';
		$s['v2_theme']       = in_array( self::post( 'v2_theme' ), array( 'auto', 'light', 'dark' ), true ) ? self::post( 'v2_theme' ) : 'auto';
		$s['v2_size']        = 'compact' === self::post( 'v2_size' ) ? 'compact' : 'normal';
		$s['hide_badge']     = (int) (bool) self::post( 'hide_badge', 0 );
		$s['skip_logged_in'] = (int) (bool) self::post( 'skip_logged_in', 0 );
		$s['fail_open']      = (int) (bool) self::post( 'fail_open', 0 );
		$ips                 = array();
		foreach ( preg_split( '/[\s,]+/', (string) self::post( 'allow_ips' ), -1, PREG_SPLIT_NO_EMPTY ) as $ip ) {
			if ( preg_match( '#^[0-9a-fA-F:.]+(/\d{1,3})?$#', $ip ) ) {
				$ips[] = $ip;
			}
		}
		$s['allow_ips'] = implode( "\n", array_unique( $ips ) );
		$forms          = array();
		foreach ( (array) self::post( 'forms', array() ) as $k => $v ) {
			$forms[ sanitize_key( $k ) ] = (int) (bool) $v;
		}
		$s['forms'] = array_merge( (array) $s['forms'], $forms );
		flxrc_update_settings( $s );
		$msg  = ( $was && ! flxrc_keys_verified() ) ? 'unverified' : 'saved';
		$args = array( 'flxrc_msg' => $msg );
		if ( self::post( 'then_test' ) ) {
			$args = array( 'flxrc_autotest' => 1 );
		}
		wp_safe_redirect( flxrc_admin_url( $args ) . '#flxrc-keys' );
		exit;
	}

	/** Sürüm ve anahtarları ayarlara yazar; özet değişirse test onayı düşer. */
	private static function apply_keys( array $s, $version, $site, $secret ) {
		$s['version'] = 'v2' === $version ? 'v2' : 'v3';
		if ( null !== $site && ! ( defined( 'FLXRC_SITE_KEY' ) && FLXRC_SITE_KEY ) ) {
			$s['site_key'] = preg_replace( '/[^A-Za-z0-9_-]/', '', (string) $site );
		}
		$secret = preg_replace( '/[^A-Za-z0-9_-]/', '', (string) $secret );
		if ( '' !== $secret ) {
			$s['secret'] = flxrc_seal( $secret );
		}
		/* Test onayı yalnız aynı sürüm + anahtarlar için geçerli. */
		$fp = substr( hash( 'sha256', $s['version'] . '|' . ( defined( 'FLXRC_SITE_KEY' ) && FLXRC_SITE_KEY ? FLXRC_SITE_KEY : $s['site_key'] ) . '|' . ( defined( 'FLXRC_SECRET_KEY' ) && FLXRC_SECRET_KEY ? FLXRC_SECRET_KEY : flxrc_unseal( $s['secret'] ) ) ), 0, 24 );
		if ( ! hash_equals( (string) $s['verified'], $fp ) ) {
			$s['verified'] = '';
		}
		return $s;
	}

	/* ---------------------------------------------------------------- */
	/* Test                                                              */
	/* ---------------------------------------------------------------- */

	public static function ajax_test() {
		check_ajax_referer( 'flxrc_test', 'nonce' );
		if ( ! current_user_can( self::CAP ) ) {
			wp_send_json_error( array( 'msg' => __( 'Bu işlem için yetkiniz yok.', 'flx-recaptcha' ) ), 403 );
		}
		$version = 'v2' === self::post( 'version' ) ? 'v2' : 'v3';
		$site    = defined( 'FLXRC_SITE_KEY' ) && FLXRC_SITE_KEY ? FLXRC_SITE_KEY : preg_replace( '/[^A-Za-z0-9_-]/', '', (string) self::post( 'site_key' ) );
		$typed   = preg_replace( '/[^A-Za-z0-9_-]/', '', (string) self::post( 'secret' ) );
		$secret  = '' !== $typed ? $typed : flxrc_secret();
		if ( '' === $site || '' === $secret ) {
			wp_send_json_error( array( 'msg' => __( 'Site anahtarı ve gizli anahtar gerekli.', 'flx-recaptcha' ) ) );
		}
		$res = FLXRC_Verify::test( (string) self::post( 'token' ), $secret );
		if ( $res['ok'] && ( 'v3' === $version ) !== ! empty( $res['v3'] ) ) {
			$res = array(
				'ok'  => false,
				'msg' => 'v3' === $version ? __( 'Bu anahtarlar v2 (kutucuk) anahtarı. Türü v2 seçin ya da Google konsolunda v3 anahtarı oluşturun.', 'flx-recaptcha' ) : __( 'Bu anahtarlar v3 anahtarı. Türü v3 seçin ya da Google konsolunda v2 anahtarı oluşturun.', 'flx-recaptcha' ),
			);
		}
		flxrc_status( 'last_test', array( 'time' => time(), 'ok' => $res['ok'], 'msg' => wp_strip_all_tags( $res['msg'] ) ) );
		if ( ! $res['ok'] ) {
			wp_send_json_error( array( 'msg' => $res['msg'] ) );
		}
		$s = self::apply_keys( flxrc_settings(), $version, $site, $typed );
		flxrc_update_settings( $s );
		$s['verified'] = flxrc_key_fingerprint();
		flxrc_update_settings( $s );
		flxrc_status_clear( 'last_error' );
		wp_send_json_success( array( 'msg' => $res['msg'] ) );
	}
}
