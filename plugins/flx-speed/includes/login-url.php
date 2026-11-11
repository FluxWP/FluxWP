<?php
/**
 * Özel yönetim giriş adresi: wp-login.php yerine /<slug> (örn. /fluxadmin).
 *
 * - Giriş ekranı yalnız özel adreste açılır; wp-login.php ve giriş yapmamış ziyaretçinin wp-admin istekleri
 *   404 (tema 404 sayfası) ya da ana sayfaya yönlendirme alır.
 * - admin-ajax.php, admin-post.php, REST, wp-cron, XML-RPC, WP-CLI ve WooCommerce hesap girişi etkilenmez.
 * - WordPress'in ürettiği tüm wp-login.php bağlantıları (çıkış, şifre sıfırlama e-postası, kayıt) yeni adrese çevrilir.
 * - Kurtarma: wp-config.php'ye define( 'FLXP_LOGIN_DISABLE', true ); özellik kapanır. FLXP_LOGIN_SLUG sabiti paneli ezer.
 * - Önbellek: giriş ve engel yanıtları önbelleğe girmez (LiteSpeed dahil); adres değişince LiteSpeed temizlenir.
 *
 * @package flx-speed
 */

defined( 'ABSPATH' ) || exit;

class FLXP_Login_URL {

	/** Bu istek özel giriş adresi mi. */
	private static $is_login = false;

	/** Bu istek engellenecek mi (wp-login.php, misafir wp-admin). */
	private static $blocked = false;

	public static function boot() {
		if ( '' === self::slug() ) {
			return;
		}
		add_action( 'plugins_loaded', array( __CLASS__, 'detect' ), 1 );
		add_action( 'wp_loaded', array( __CLASS__, 'route' ), 1 );
		foreach ( array( 'site_url', 'network_site_url', 'wp_redirect' ) as $f ) {
			add_filter( $f, array( __CLASS__, 'filter_url' ), 100 );
		}
		add_filter( 'wp_speculation_rules_href_exclude_paths', array( __CLASS__, 'speculation_exclude' ) );
		add_filter( 'wp_login_errors', array( __CLASS__, 'moved_message' ) );
		// /login, /admin, /dashboard kısayolları gerçek adresi ele vermesin.
		remove_action( 'template_redirect', 'wp_redirect_admin_locations', 1000 );
	}

	/** Kurtarma sabiti tanımlı mı. */
	public static function disabled_by_constant() {
		return defined( 'FLXP_LOGIN_DISABLE' ) && FLXP_LOGIN_DISABLE;
	}

	/** Etkin özel adres ('' = kapalı). */
	public static function slug() {
		if ( self::disabled_by_constant() ) {
			return '';
		}
		if ( defined( 'FLXP_LOGIN_SLUG' ) && is_string( FLXP_LOGIN_SLUG ) ) {
			$s = self::clean( FLXP_LOGIN_SLUG );
			return is_wp_error( self::validate( $s, false ) ) ? '' : $s;
		}
		return (string) flxp_settings( 'login_slug' );
	}

	/** Yazılan değeri adres parçasına çevirir. */
	public static function clean( $s ) {
		return strtolower( trim( (string) $s, " \t\n\r\0\x0B/" ) );
	}

	/** Kullanılamayacak adresler (çekirdek yolları ve çekirdeğin kendi kısayolları). */
	public static function reserved() {
		return array( 'wp-admin', 'wp-login', 'wp-login-php', 'wp-content', 'wp-includes', 'wp-json', 'wp-cron', 'wp-signup', 'wp-activate', 'xmlrpc', 'admin', 'login', 'dashboard', 'feed', 'comments', 'search', 'author', 'page', 'embed', 'category', 'tag', 'sitemap', 'robots', 'index' );
	}

	/**
	 * Adres kurallarına uyuyor mu.
	 *
	 * @param string $s           Temizlenmiş adres.
	 * @param bool   $check_posts Sayfa/yazı adlarıyla çakışmayı da denetle.
	 * @return true|WP_Error
	 */
	public static function validate( $s, $check_posts = true ) {
		if ( ! preg_match( '/^[a-z0-9][a-z0-9_-]{2,39}$/', $s ) ) {
			return new WP_Error( 'flxp_login_slug', __( 'The login URL must be 3-40 characters; use only lowercase letters (a-z), digits, hyphens and underscores.', 'flx-speed' ) );
		}
		if ( in_array( $s, self::reserved(), true ) || 0 === strpos( $s, 'wp-' ) ) {
			return new WP_Error( 'flxp_login_slug', __( 'WordPress already uses this address; pick another name.', 'flx-speed' ) );
		}
		if ( $check_posts && get_page_by_path( $s, OBJECT, array_values( get_post_types( array( 'public' => true ) ) ) ) ) {
			return new WP_Error( 'flxp_login_slug', __( 'A page or post already uses this address; pick another name.', 'flx-speed' ) );
		}
		return true;
	}

	/** Yeni giriş adresi (tam URL). */
	public static function url( $scheme = null, $slug = null ) {
		$slug = null === $slug ? self::slug() : $slug;
		if ( get_option( 'permalink_structure' ) ) {
			return user_trailingslashit( home_url( '/' . $slug, $scheme ) );
		}
		return home_url( '/?' . $slug, $scheme );
	}

	/** İstek yolu (sorgu olmadan, sondaki / atılmış, kodu çözülmüş). */
	private static function request_path() {
		$uri  = isset( $_SERVER['REQUEST_URI'] ) ? rawurldecode( (string) wp_unslash( $_SERVER['REQUEST_URI'] ) ) : '/'; // phpcs:ignore
		$path = (string) wp_parse_url( $uri, PHP_URL_PATH );
		return untrailingslashit( $path );
	}

	/** Özel adresin yolu (alt klasör kurulumu dahil). */
	private static function login_path() {
		return untrailingslashit( (string) wp_parse_url( home_url( '/' . self::slug() ), PHP_URL_PATH ) );
	}

	/** plugins_loaded: isteğin türünü belirler. */
	public static function detect() {
		global $pagenow;
		$path = self::request_path();
		$slug = self::slug();

		$is_slug = strtolower( $path ) === strtolower( self::login_path() );
		if ( ! get_option( 'permalink_structure' ) ) {
			$is_slug = isset( $_GET[ $slug ] ) && untrailingslashit( $path ) === untrailingslashit( (string) wp_parse_url( home_url( '/' ), PHP_URL_PATH ) ); // phpcs:ignore
		}

		if ( $is_slug ) {
			self::$is_login          = true;
			$pagenow                 = 'wp-login.php'; // phpcs:ignore WordPress.WP.GlobalVariablesOverride
			$_SERVER['SCRIPT_NAME'] = self::login_path(); // is_login() bu adresi tanısın.
			return;
		}

		if ( isset( $_GET['flxp_404'] ) ) { // phpcs:ignore
			self::$blocked = true;
			return;
		}

		// basename(REQUEST_URI) /wp-login.php/x yolunda "x" döner. Betiğin kendisine bak.
		if ( self::is_legacy_login() && ! self::passthrough() ) {
			self::$blocked = true;
			$pagenow       = 'index.php'; // phpcs:ignore WordPress.WP.GlobalVariablesOverride
		}
	}

	/**
	 * Çalışan betik varsayılan giriş dosyası mı.
	 * PATH_INFO (/wp-login.php/x), sondaki eğik çizgi, çift eğik çizgi, büyük/küçük harf ve alt klasör.
	 */
	private static function is_legacy_login() {
		$names = array( 'wp-login.php', 'wp-register.php' );
		if ( ! is_multisite() ) {
			$names[] = 'wp-signup.php';
			$names[] = 'wp-activate.php';
		}
		$raw = array();
		foreach ( array( 'SCRIPT_NAME', 'PHP_SELF', 'SCRIPT_FILENAME' ) as $key ) {
			if ( ! empty( $_SERVER[ $key ] ) ) { // phpcs:ignore
				$raw[] = (string) wp_unslash( $_SERVER[ $key ] ); // phpcs:ignore
			}
		}
		global $pagenow;
		if ( is_string( $pagenow ) && '' !== $pagenow ) {
			$raw[] = $pagenow;
		}
		$uri  = isset( $_SERVER['REQUEST_URI'] ) ? (string) wp_unslash( $_SERVER['REQUEST_URI'] ) : ''; // phpcs:ignore
		$path = (string) wp_parse_url( rawurldecode( $uri ), PHP_URL_PATH );
		if ( '' !== $path ) {
			$raw[] = $path;
		}
		foreach ( $raw as $item ) {
			$item = strtolower( str_replace( '\\', '/', $item ) );
			$item = preg_replace( '#/+#', '/', $item );
			foreach ( $names as $name ) {
				if ( preg_match( '#(^|/)' . preg_quote( $name, '#' ) . '(/|$)#', $item ) ) {
					return true;
				}
			}
		}
		return false;
	}

	/**
	 * Eski wp-login.php adresinde çalışmaya devam eden işlemler.
	 * postpass: önbellekteki eski şifreli yazı formları. flxapp_authorize: Flux App OAuth (istemci wp-login.php'yi çağırır).
	 */
	private static function passthrough() {
		$action = isset( $_REQUEST['action'] ) ? sanitize_key( wp_unslash( $_REQUEST['action'] ) ) : ''; // phpcs:ignore
		if ( '' === $action ) {
			return false;
		}
		$allow = array();
		if ( isset( $_POST['post_password'] ) ) { // phpcs:ignore
			$allow[] = 'postpass';
		}
		if ( function_exists( 'flxapp_authorize_url' ) ) {
			$allow[] = 'flxapp_authorize';
		}
		/**
		 * wp-login.php üzerinde engellenmeyecek ?action= değerleri.
		 *
		 * @param string[] $allow
		 */
		$allow = (array) apply_filters( 'flxp_login_passthrough_actions', $allow );
		return in_array( $action, $allow, true );
	}

	/** Misafir wp-admin isteği mi (ajax, admin-post, cron, CLI hariç). */
	private static function is_guest_admin() {
		global $pagenow;
		if ( ! is_admin() || is_user_logged_in() ) {
			return false;
		}
		if ( wp_doing_ajax() || wp_doing_cron() || ( defined( 'WP_CLI' ) && WP_CLI ) || in_array( $pagenow, array( 'admin-ajax.php', 'admin-post.php' ), true ) ) {
			return false;
		}
		return true;
	}

	/** wp_loaded: giriş ekranını açar ya da isteği engeller. */
	public static function route() {
		if ( self::$is_login ) {
			self::no_cache();
			global $error, $interim_login, $action, $user_login; // wp-login.php bu değişkenleri global bekler.
			require_once ABSPATH . 'wp-login.php';
			exit;
		}
		if ( self::$blocked || self::is_guest_admin() ) {
			self::deny();
		}
	}

	/** Önbelleğe girmesin (tarayıcı, LiteSpeed, diğer sayfa önbellekleri). */
	private static function no_cache() {
		if ( ! defined( 'DONOTCACHEPAGE' ) ) {
			define( 'DONOTCACHEPAGE', true );
		}
		do_action( 'litespeed_control_set_nocache', 'flx-speed: login url' );
		if ( ! headers_sent() ) {
			nocache_headers();
			flx_rest_nocache();
		}
	}

	/** Engellenen istek: 404 sayfası ya da ana sayfa. */
	private static function deny() {
		self::no_cache();
		/**
		 * Engellenen isteğin gideceği adres; boş ise 404 gösterilir.
		 *
		 * @param string $url
		 */
		$to = (string) apply_filters( 'flxp_login_blocked_redirect', 'home' === flxp_settings( 'login_blocked' ) ? home_url( '/' ) : '' );
		if ( '' !== $to ) {
			wp_safe_redirect( $to, 302 );
			exit;
		}
		self::render_404();
	}

	/** Temanın 404 sayfası (önyüz bağlamında). */
	private static function render_404() {
		global $pagenow, $wp_query;
		$pagenow = 'index.php'; // phpcs:ignore WordPress.WP.GlobalVariablesOverride
		if ( is_admin() ) {
			// wp-admin içinde tema ve WooCommerce önyüz kodu yüklü değil: 404 önyüzde gösterilir.
			wp_safe_redirect( add_query_arg( 'flxp_404', '1', home_url( '/' ) ), 302 );
			exit;
		}
		remove_action( 'template_redirect', 'redirect_canonical' );
		if ( ! defined( 'WP_USE_THEMES' ) ) {
			define( 'WP_USE_THEMES', true );
		}
		$_SERVER['REQUEST_URI'] = '/flxp-404-' . wp_generate_password( 12, false ) . '/';
		wp();
		$wp_query->set_404();
		status_header( 404 );
		require_once ABSPATH . WPINC . '/template-loader.php';
		exit;
	}

	/** wp-login.php içeren adresleri yeni adrese çevirir; sorgu dizesi olduğu gibi kalır. */
	public static function filter_url( $url ) {
		if ( ! is_string( $url ) || false === strpos( $url, 'wp-login.php' ) ) {
			return $url;
		}
		$parts = explode( '?', $url, 2 );
		if ( 'wp-login.php' !== basename( $parts[0] ) ) {
			return $url;
		}
		$scheme = wp_parse_url( $parts[0], PHP_URL_SCHEME );
		$new    = self::url( $scheme ? $scheme : null );
		if ( isset( $parts[1] ) && '' !== $parts[1] ) {
			$new .= ( false === strpos( $new, '?' ) ? '?' : '&' ) . $parts[1];
		}
		return $new;
	}

	/** Adres değişikliğinden sonra açılan giriş ekranında bilgi. */
	public static function moved_message( $errors ) {
		if ( self::$is_login && isset( $_GET['flxp_moved'] ) && is_wp_error( $errors ) ) { // phpcs:ignore
			$errors->add( 'flxp_moved', __( 'The login URL has changed. Bookmark this page and log in again.', 'flx-speed' ), 'message' );
		}
		return $errors;
	}

	/** Tarayıcı ön yüklemesi giriş adresini çağırmasın. */
	public static function speculation_exclude( $paths ) {
		$p = self::login_path();
		$paths[] = ( '' === $p ? '/' : $p ) . '*';
		return $paths;
	}
}
FLXP_Login_URL::boot();

/** Adres değişince önbellek temizlenir (eski 404/sayfa yanıtları kalmasın). */
function flxp_login_slug_changed() {
	do_action( 'litespeed_purge_all' );
	if ( function_exists( 'wp_cache_flush' ) ) {
		wp_cache_flush();
	}
}
