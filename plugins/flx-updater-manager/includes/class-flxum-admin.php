<?php
/**
 * Yönetim: "Updater Manager" menüsü, sekmeler, sunucuya giden işlemler (admin-post) ve depo listesi (AJAX).
 *
 * Her form admin-post.php?action=flxum_do'ya gelir; "op" alanı sunucu panelindeki işlemin adıdır
 * (save_switch, add_site …) ve diğer alanlar olduğu gibi sunucuya gider. Sonuç bir kez gösterilir.
 *
 * @package FLX_Updater_Manager
 */

defined( 'ABSPATH' ) || exit;

final class FLXUM_Admin {

	const CAP  = 'manage_options';
	const SLUG = 'flx-updater-manager';

	/** Formdan sunucuya gönderilmeyen alanlar. */
	const SKIP = array( 'action', 'op', '_wpnonce', '_wp_http_referer', 'tab', 'r' );

	public static function init() {
		add_action( 'admin_menu', array( __CLASS__, 'menu' ), 60 );
		add_action( 'admin_enqueue_scripts', array( __CLASS__, 'assets' ) );
		add_action( 'admin_head', array( __CLASS__, 'menu_logo' ) );
		add_action( 'admin_post_flxum_do', array( __CLASS__, 'handle' ) );
		add_action( 'wp_ajax_flxum_repos', array( __CLASS__, 'ajax_repos' ) );
	}

	public static function tabs() {
		return array(
			'overview'  => __( 'Genel bakış', 'flx-updater-manager' ),
			'countries' => __( 'Ülkeler', 'flx-updater-manager' ),
			'sites'     => __( 'Siteler', 'flx-updater-manager' ),
			'packages'  => __( 'Paketler', 'flx-updater-manager' ),
			'github'    => 'GitHub',
			'security'  => __( 'Güvenlik', 'flx-updater-manager' ),
			'resources' => __( 'Kaynaklar', 'flx-updater-manager' ),
			'connection' => __( 'Bağlantı', 'flx-updater-manager' ),
		);
	}

	public static function menu() {
		add_menu_page( __( 'Updater Manager', 'flx-updater-manager' ), __( 'Updater', 'flx-updater-manager' ), self::CAP, self::SLUG, array( 'FLXUM_Views', 'render' ), 'none', 82 );
	}

	/** Renkli menü logosu (WP data-URI SVG'yi tek renge boyar). */
	public static function menu_logo() {
		echo '<style>#toplevel_page_flx-updater-manager .wp-menu-image{background:url(' . esc_url( FLXUM_URL . 'assets/icon.svg?ver=' . FLXUM_VERSION ) . ') center/20px 20px no-repeat}</style>';
	}

	private static function is_screen() {
		return isset( $_GET['page'] ) && self::SLUG === $_GET['page']; // phpcs:ignore WordPress.Security.NonceVerification
	}

	public static function assets() {
		if ( ! self::is_screen() ) {
			return;
		}
		wp_enqueue_style( 'flxum-admin', FLXUM_URL . 'assets/admin.css', array(), FLXUM_VERSION );
		wp_enqueue_script( 'flxum-admin', FLXUM_URL . 'assets/admin.js', array(), FLXUM_VERSION, true );
		wp_localize_script(
			'flxum-admin',
			'flxumAdmin',
			array(
				'ajax'  => admin_url( 'admin-ajax.php' ),
				'nonce' => wp_create_nonce( 'flxum_repos' ),
				'i18n'  => array(
					'site'    => __( 'Site', 'flx-updater-manager' ),
					'checks'  => __( 'Kontrol', 'flx-updater-manager' ),
					'dl'      => __( 'İndirme', 'flx-updater-manager' ),
					'copied'  => __( 'Kopyalandı', 'flx-updater-manager' ),
					'remove'  => __( 'Kaldır', 'flx-updater-manager' ),
					'add'     => __( 'Depo ekle…', 'flx-updater-manager' ),
					'pick'    => __( 'Depo seçin', 'flx-updater-manager' ),
					'loading' => __( 'Yükleniyor…', 'flx-updater-manager' ),
					'fail'    => __( 'Liste alınamadı', 'flx-updater-manager' ),
					'enter'   => __( 'Enter ile ekleyin', 'flx-updater-manager' ),
					'refresh' => __( 'Listeyi yenile', 'flx-updater-manager' ),
					'working' => __( 'Sunucuda yapılıyor…', 'flx-updater-manager' ),
				),
			)
		);
	}

	/* ------------------------------------------------------------------ */
	/* İşlemler                                                            */
	/* ------------------------------------------------------------------ */

	public static function handle() {
		if ( ! current_user_can( self::CAP ) ) {
			wp_die( esc_html__( 'Bu işlem için yetkiniz yok.', 'flx-updater-manager' ), 403 );
		}
		check_admin_referer( 'flxum_do' );
		$op   = sanitize_key( wp_unslash( $_POST['op'] ?? '' ) );
		$tab  = sanitize_key( wp_unslash( $_POST['tab'] ?? 'overview' ) );
		$tab  = isset( self::tabs()[ $tab ] ) ? $tab : 'overview';
		$args = array();
		if ( isset( $_POST['r'] ) ) {
			$args['r'] = absint( $_POST['r'] );
		}

		if ( 'connect' === $op ) {
			self::connect();
			$tab = self::configured_ok() ? 'overview' : 'connection';
		} elseif ( 'disconnect' === $op ) {
			$s        = flxum_settings();
			$s['key'] = '';
			update_option( 'flxum_settings', $s, false );
			self::flash( array( 'ok' => true, 'message' => __( 'Yönetici anahtarı bu siteden silindi. Sunucudaki anahtar duruyor; sunucu panelinden silebilirsiniz.', 'flx-updater-manager' ) ) );
			$tab = 'connection';
		} elseif ( 'test_key' === $op ) {
			$r = FLXUM_Client::request( 'POST', 'test-key', array( 'key' => trim( (string) wp_unslash( $_POST['key'] ?? '' ) ) ) ); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
			self::flash( is_wp_error( $r ) ? self::err( $r ) : array( 'ok' => true, 'message' => '', 'test' => $r ) );
		} elseif ( preg_match( '/^[a-z_]{2,40}$/', $op ) ) {
			$fields = array();
			foreach ( wp_unslash( $_POST ) as $k => $v ) { // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Sunucu her alanı kendi doğrular.
				if ( ! in_array( $k, self::SKIP, true ) ) {
					$fields[ $k ] = $v;
				}
			}
			$long = in_array( $op, array( 'geo_update', 'flush', 'save_github', 'prune_zips' ), true );
			$r    = FLXUM_Client::action( $op, $fields, $long ? 120 : 30 );
			if ( is_wp_error( $r ) ) {
				self::flash( self::err( $r ) );
			} else {
				$data = (array) ( $r['data'] ?? array() );
				if ( ! empty( $data['new_key'] ) ) {
					$data['new_key'] = flxum_seal( $data['new_key'] );
				}
				self::flash(
					array(
						'ok'      => ! empty( $r['ok'] ),
						'message' => '' !== (string) ( $r['message'] ?? '' ) ? (string) $r['message'] : __( 'Kaydedildi.', 'flx-updater-manager' ),
						'data'    => $data,
					)
				);
			}
		}
		wp_safe_redirect( flxum_admin_url( $tab, $args ) );
		exit;
	}

	private static function configured_ok() {
		return FLXUM_Client::configured() && ! is_wp_error( FLXUM_Client::get( 'info' ) );
	}

	/** Sunucu adresi + anahtar kaydı; kaydetmeden önce sunucuda denenir. */
	private static function connect() {
		$s      = flxum_settings();
		$server = untrailingslashit( esc_url_raw( trim( (string) wp_unslash( $_POST['server'] ?? '' ) ) ) ); // phpcs:ignore WordPress.Security.NonceVerification
		$key    = trim( (string) wp_unslash( $_POST['key'] ?? '' ) ); // phpcs:ignore WordPress.Security.NonceVerification,WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
		if ( '' === $server ) {
			$server = FLXUM_Client::suggested_server();
		}
		$try_key = '' !== $key ? $key : FLXUM_Client::key();
		if ( '' !== $key && ! preg_match( '/^flxm_[a-f0-9]{48}$/', $key ) ) {
			self::flash( array( 'ok' => false, 'message' => __( 'Yönetici anahtarı "flxm_" ile başlar ve 53 karakterdir. Sunucu panelinde Updater Manager kartından üretin.', 'flx-updater-manager' ) ) );
			return;
		}
		$r = FLXUM_Client::request( 'GET', 'info', null, array(), 15, $server, $try_key );
		if ( is_wp_error( $r ) ) {
			self::flash( self::err( $r ) );
			return;
		}
		$s['server'] = defined( 'FLXUM_SERVER' ) && FLXUM_SERVER ? $s['server'] : $server;
		if ( '' !== $key ) {
			$s['key'] = flxum_seal( $key );
		}
		update_option( 'flxum_settings', $s, false );
		self::flash(
			array(
				'ok'      => true,
				/* translators: 1: sunucu sürümü, 2: anahtar adı */
				'message' => sprintf( __( 'Bağlandı: Flux Update Server %1$s, anahtar "%2$s".', 'flx-updater-manager' ), (string) ( $r['version'] ?? '' ), (string) ( $r['key_name'] ?? '' ) ),
			)
		);
	}

	private static function err( WP_Error $e ) {
		return array( 'ok' => false, 'message' => $e->get_error_message() );
	}

	/** Sonraki sayfa açılışında bir kez gösterilecek sonuç. */
	public static function flash( array $r ) {
		set_transient( 'flxum_flash_' . get_current_user_id(), $r, 300 );
	}

	public static function take_flash() {
		$k = 'flxum_flash_' . get_current_user_id();
		$r = get_transient( $k );
		delete_transient( $k );
		return is_array( $r ) ? $r : array();
	}

	/** Depo seçici için GitHub depoları (sunucu üzerinden). */
	public static function ajax_repos() {
		if ( ! current_user_can( self::CAP ) || ! check_ajax_referer( 'flxum_repos', 'nonce', false ) ) {
			wp_send_json( array( 'repos' => array(), 'error' => 'forbidden' ), 403 );
		}
		$r = FLXUM_Client::get( 'repos', empty( $_GET['fresh'] ) ? array() : array( 'fresh' => 1 ), 30 );
		if ( is_wp_error( $r ) ) {
			wp_send_json( array( 'repos' => array(), 'error' => $r->get_error_message() ) );
		}
		wp_send_json( array( 'repos' => (array) ( $r['repos'] ?? array() ), 'error' => (string) ( $r['error'] ?? '' ) ) );
	}
}
