<?php
/**
 * Google önerileri: isteğe bağlı kurulum kartları (Google Site Kit tarzı).
 *
 * 1. "Google'da bulunun" → Google Site Kit (wordpress.org: google-site-kit). Search Console, Analytics ve
 *    PageSpeed verisi panelde. Kurulu değilse ya da etkin değilse görünür.
 * 2. "Ürünlerinizi Google'da gösterin" → Google for WooCommerce (google-listings-and-ads). Yalnız WooCommerce
 *    etkin ve Site Kit Google hesabına bağlıyken görünür.
 *
 * Kartlar Başlangıç, Sistem Kontrolü ve Paketler ekranlarının üstünde çıkar. "Gerekli değil" kartı site genelinde
 * kapatır (option flxk_google_cards: kapatılan kart kimlikleri). Kur düğmesi eklentiyi wordpress.org'dan kurar,
 * etkinleştirir ve eklentinin kendi kurulum ekranına yönlendirir. Biz Google hesabına bağlanmayız; bağlantıyı
 * kullanıcı eklentinin kendi ekranında yapar.
 *
 * Filtre `flxk_google_cards` kartları değiştirir ya da yenilerini ekler.
 *
 * @package flx-setup
 */

defined( 'ABSPATH' ) || exit;

/** Kart tanımları. */
function flxk_google_cards() {
	$cards = array(
		'site-kit'   => array(
			'slug'  => 'google-site-kit',
			'file'  => 'google-site-kit/google-site-kit.php',
			'title' => __( 'Google\'da bulunun', 'flx-setup' ),
			'text'  => __( 'Sitenizin Google aramalarında nasıl göründüğünü, kaç kişinin geldiğini ve sayfalarınızın ne kadar hızlı açıldığını panelden izleyin. Google Site Kit Search Console, Analytics ve PageSpeed\'i tek ekranda toplar.', 'flx-setup' ),
			'name'  => 'Google Site Kit',
			'install'  => __( 'Google Site Kit\'i kur', 'flx-setup' ),
			'activate' => __( 'Google Site Kit\'i etkinleştir', 'flx-setup' ),
			'next'  => admin_url( 'admin.php?page=googlesitekit-splash' ),
			'show'  => true,
		),
		'google-woo' => array(
			'slug'  => 'google-listings-and-ads',
			'file'  => 'google-listings-and-ads/google-listings-and-ads.php',
			'title' => __( 'Ürünlerinizi Google\'da gösterin', 'flx-setup' ),
			'text'  => __( 'Google hesabınız bağlı. Google for WooCommerce ile ürünleriniz Google Alışveriş, Arama, YouTube ve Görseller\'de ücretsiz listelenir; isterseniz reklam da verebilirsiniz.', 'flx-setup' ),
			'name'  => 'Google for WooCommerce',
			'install'  => __( 'Google for WooCommerce\'ı kur', 'flx-setup' ),
			'activate' => __( 'Google for WooCommerce\'ı etkinleştir', 'flx-setup' ),
			'next'  => admin_url( 'admin.php?page=wc-admin&path=/google/start' ),
			'show'  => class_exists( 'WooCommerce' ) && flxk_google_connected(),
		),
	);
	return (array) apply_filters( 'flxk_google_cards', $cards );
}

/** Site Kit etkin ve en az bir yönetici Google hesabını bağlamış mı? */
function flxk_google_connected() {
	if ( ! defined( 'GOOGLESITEKIT_VERSION' ) ) {
		return false;
	}
	return (bool) get_option( 'googlesitekit_has_connected_admins', false );
}

/** Eklenti durumu: missing | inactive | active. */
function flxk_google_card_state( array $c ) {
	if ( ! function_exists( 'get_plugins' ) ) {
		require_once ABSPATH . 'wp-admin/includes/plugin.php';
	}
	if ( is_plugin_active( $c['file'] ) ) {
		return 'active';
	}
	return isset( get_plugins()[ $c['file'] ] ) ? 'inactive' : 'missing';
}

/** Kartlar bu ekranda gösterilsin mi? */
function flxk_google_cards_screen() {
	$s = function_exists( 'get_current_screen' ) ? get_current_screen() : null;
	if ( $s && 'dashboard' === $s->id ) {
		return true;
	}
	// phpcs:disable WordPress.Security.NonceVerification
	$page = isset( $_GET['page'] ) ? sanitize_key( wp_unslash( $_GET['page'] ) ) : '';
	$tab  = isset( $_GET['tab'] ) ? sanitize_key( wp_unslash( $_GET['tab'] ) ) : '';
	$busy = ! empty( $_GET['flxu_install'] );
	// phpcs:enable
	return 'flx-sistem' === $page || ( 'flx-speed' === $page && 'system' === $tab ) || ( 'flx-setup' === $page && ! $busy );
}

add_action(
	'admin_notices',
	function () {
		if ( ! current_user_can( 'install_plugins' ) || ! current_user_can( 'activate_plugins' ) || ! flxk_google_cards_screen() ) {
			return;
		}
		$closed = (array) get_option( 'flxk_google_cards', array() );
		$shown  = 0;
		foreach ( flxk_google_cards() as $id => $c ) {
			if ( empty( $c['show'] ) || in_array( $id, $closed, true ) ) {
				continue;
			}
			$state = flxk_google_card_state( $c );
			if ( 'active' === $state ) {
				continue;
			}
			$go   = wp_nonce_url( admin_url( 'admin-post.php?action=flxk_google_install&card=' . rawurlencode( $id ) ), 'flxk_google_install_' . $id );
			$skip = wp_nonce_url( admin_url( 'admin-post.php?action=flxk_google_dismiss&card=' . rawurlencode( $id ) ), 'flxk_google_dismiss_' . $id );
			$btn = 'inactive' === $state ? $c['activate'] : $c['install'];
			if ( ! $shown++ ) {
				flxk_google_cards_css();
			}
			?>
			<div class="notice flxk-gcard">
				<div class="flxk-gcard__body">
					<h2><?php echo esc_html( $c['title'] ); ?></h2>
					<p><?php echo esc_html( $c['text'] ); ?></p>
				</div>
				<div class="flxk-gcard__act">
					<a class="button flxk-gcard__skip" href="<?php echo esc_url( $skip ); ?>"><?php esc_html_e( 'Gerekli değil', 'flx-setup' ); ?></a>
					<a class="button button-primary" href="<?php echo esc_url( $go ); ?>"><?php echo esc_html( $btn ); ?></a>
				</div>
			</div>
			<?php
		}
	}
);

function flxk_google_cards_css() {
	?>
	<style>
		.wrap .notice.flxk-gcard,.notice.flxk-gcard{display:flex;align-items:center;justify-content:space-between;gap:14px 28px;flex-wrap:wrap;padding:20px 24px;margin:16px 0;border:1px solid #dcdcde;border-radius:12px;background:#fff;box-shadow:none}
		.flxk-gcard__body{flex:1 1 420px;min-width:0}
		.flxk-gcard h2{margin:0 0 6px;padding:0;font-size:15px;line-height:1.4;color:#1d2327}
		.flxk-gcard p{margin:0;padding:0;color:#646970;font-size:13px;line-height:1.55;max-width:70ch}
		.flxk-gcard__act{display:flex;gap:8px;align-items:center;flex-wrap:wrap}
		.flxk-gcard__act .button{min-height:36px;line-height:34px;padding:0 16px;border-radius:8px;font-weight:600}
		.flxk-gcard__act .flxk-gcard__skip{background:transparent;border-color:transparent;color:#1d2327}
		.flxk-gcard__act .flxk-gcard__skip:hover,.flxk-gcard__act .flxk-gcard__skip:focus{background:#f0f0f1;border-color:transparent;color:#1d2327}
		@media (max-width:600px){.flxk-gcard__act{width:100%}.flxk-gcard__act .button{flex:1;text-align:center}}
		html.flxa-dark .flxk-gcard,body.flxa-dark .flxk-gcard{background:#1b1f2a;border-color:#2c3242}
		html.flxa-dark .flxk-gcard h2,body.flxa-dark .flxk-gcard h2,html.flxa-dark .flxk-gcard__skip,body.flxa-dark .flxk-gcard__skip{color:#e6e8ee}
		html.flxa-dark .flxk-gcard p,body.flxa-dark .flxk-gcard p{color:#9aa1b2}
	</style>
	<?php
}

/** "Gerekli değil": o kart site genelinde kapanır. */
add_action(
	'admin_post_flxk_google_dismiss',
	function () {
		$id = isset( $_GET['card'] ) ? sanitize_key( wp_unslash( $_GET['card'] ) ) : '';
		check_admin_referer( 'flxk_google_dismiss_' . $id );
		if ( current_user_can( 'install_plugins' ) && isset( flxk_google_cards()[ $id ] ) ) {
			$closed = (array) get_option( 'flxk_google_cards', array() );
			update_option( 'flxk_google_cards', array_values( array_unique( array_merge( $closed, array( $id ) ) ) ), false );
		}
		wp_safe_redirect( wp_get_referer() ? wp_get_referer() : admin_url() );
		exit;
	}
);

/** Kur + etkinleştir + eklentinin kurulum ekranına yönlen. */
add_action(
	'admin_post_flxk_google_install',
	function () {
		$id = isset( $_GET['card'] ) ? sanitize_key( wp_unslash( $_GET['card'] ) ) : '';
		check_admin_referer( 'flxk_google_install_' . $id );
		$c = flxk_google_cards()[ $id ] ?? null;
		if ( ! $c || ! current_user_can( 'install_plugins' ) || ! current_user_can( 'activate_plugins' ) ) {
			wp_die( esc_html__( 'Yetkiniz yok.', 'flx-setup' ), 403 );
		}
		$err = flxk_google_install( $c );
		if ( is_wp_error( $err ) ) {
			wp_die(
				'<p>' . esc_html( $err->get_error_message() ) . '</p><p><a href="' . esc_url( admin_url( 'plugin-install.php?s=' . rawurlencode( $c['slug'] ) . '&tab=search&type=term' ) ) . '">' . esc_html__( 'Eklentiler › Yeni ekle\'den deneyin', 'flx-setup' ) . '</a></p>',
				esc_html( $c['name'] )
			);
		}
		wp_safe_redirect( $c['next'] );
		exit;
	}
);

/** @return true|WP_Error */
function flxk_google_install( array $c ) {
	if ( ! function_exists( 'get_plugins' ) ) {
		require_once ABSPATH . 'wp-admin/includes/plugin.php';
	}
	if ( 'missing' === flxk_google_card_state( $c ) ) {
		require_once ABSPATH . 'wp-admin/includes/plugin-install.php';
		require_once ABSPATH . 'wp-admin/includes/class-wp-upgrader.php';
		require_once ABSPATH . 'wp-admin/includes/file.php';
		$api = plugins_api(
			'plugin_information',
			array(
				'slug'   => $c['slug'],
				'fields' => array( 'sections' => false ),
			)
		);
		if ( is_wp_error( $api ) || empty( $api->download_link ) ) {
			/* translators: %s: plugin name */
			return new WP_Error( 'flxk_google', sprintf( __( '%s wordpress.org\'dan alınamadı.', 'flx-setup' ), $c['name'] ) );
		}
		$skin = new WP_Ajax_Upgrader_Skin();
		$res  = ( new Plugin_Upgrader( $skin ) )->install( $api->download_link );
		if ( is_wp_error( $res ) || ! $res ) {
			$msg = is_wp_error( $res ) ? $res->get_error_message() : implode( ' ', (array) $skin->get_error_messages() );
			/* translators: 1: plugin name, 2: error */
			return new WP_Error( 'flxk_google', sprintf( __( '%1$s kurulamadı: %2$s', 'flx-setup' ), $c['name'], $msg ) );
		}
		wp_clean_plugins_cache();
	}
	$res = activate_plugin( $c['file'] );
	return is_wp_error( $res ) ? $res : true;
}
