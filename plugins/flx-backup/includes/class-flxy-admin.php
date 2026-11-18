<?php
/**
 * Yönetim: Yedekler menüsü (Panel, Yedekler, Ayarlar; ortak React ekranı), iş iptali ve indirme.
 * Klasik PHP sayfası yoktur.
 */

defined( 'ABSPATH' ) || exit;

final class FLXY_Admin {

	const SLUG = 'flx-backup';
	const CAP  = 'manage_options';

	/** @var self|null */
	private static $instance = null;

	/** @var string[] */
	private $hooks = array();

	public static function instance(): self {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	private function __construct() {
		add_action( 'admin_menu', array( $this, 'menu' ) );
		add_action( 'admin_menu', array( $this, 'register_screens' ), 20 );
		add_action( 'admin_head', array( $this, 'menu_icon_css' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'assets' ) );
		add_action( 'admin_post_flxy_download', array( $this, 'download' ) );
		add_action( 'wp_ajax_flxy_cancel', array( $this, 'ajax_cancel' ) ); // Toolbar indicator (assets/bar.js).
		add_filter( 'plugin_action_links_' . plugin_basename( FLXY_FILE ), array( $this, 'action_links' ) );
	}

	public static function url( string $page = '', array $args = array() ): string {
		$slug = '' === $page ? self::SLUG : self::SLUG . '-' . $page;
		return add_query_arg( array_merge( array( 'page' => $slug ), $args ), admin_url( 'admin.php' ) );
	}

	/*
	 * The pages open the same React screen as the FluxWP app (assets/flux/, tools/fluxapp/embed-sync.sh
	 * flx-backup). There is no classic page: without Flux GraphQL or the bundle the page says it cannot open.
	 */
	public static function flux_available(): bool {
		return file_exists( FLXY_DIR . 'assets/flux/flux-modules.js' ) && function_exists( 'flx_gql_ready' ) && function_exists( 'flx_gql_all_caps' ) && flx_gql_ready();
	}

	/** The page and backup of the request. */
	private static function current_args(): array {
		$args = array( 'page' => isset( $_GET['page'] ) ? sanitize_key( wp_unslash( $_GET['page'] ) ) : self::SLUG ); // phpcs:ignore WordPress.Security.NonceVerification
		if ( isset( $_GET['view'] ) && '' !== $_GET['view'] ) { // phpcs:ignore WordPress.Security.NonceVerification
			$args['view'] = sanitize_text_field( wp_unslash( $_GET['view'] ) ); // phpcs:ignore WordPress.Security.NonceVerification
		}
		return $args;
	}

	/** Which screen of the React app the current admin page opens. */
	private static function flux_start_page(): string {
		$page = self::current_args();
		if ( ! empty( $page['view'] ) ) {
			return 'detail:' . $page['view'];
		}
		if ( self::SLUG . '-list' === $page['page'] ) {
			return 'list';
		}
		return self::SLUG . '-settings' === $page['page'] ? 'settings' : 'dashboard';
	}

	/** The chunked bundle loads as an ES module. */
	public static function flux_module_tag( $tag, $handle ) {
		if ( 'flxy-flux' !== $handle ) {
			return $tag;
		}
		return (string) preg_replace_callback(
			'/<script\b[^>]*\ssrc=[^>]*>/',
			static function ( $m ) {
				$open = (string) preg_replace( '/\stype=("|\')[^"\']*\1/', '', $m[0] );
				return (string) preg_replace( '/^<script\b/', '<script type="module"', $open );
			},
			$tag,
			1
		);
	}

	/**
	 * Mount options of the screen besides what the Flux GraphQL shell adds (rest, nonce, locale…).
	 *
	 * @param string $page Page slug.
	 */
	public static function flux_options( string $page = '' ): array {
		$accent = function_exists( 'flxa_accent' ) ? (string) flxa_accent() : '#2c7a64';
		$here   = '' === $page || self::current_args()['page'] === $page;
		return array(
			'colors'     => array(
				'primary'         => $accent,
				'primaryContrast' => function_exists( 'flxa_on_color' ) ? (string) flxa_on_color( $accent ) : '#ffffff',
			),
			'appIcon'    => FLXY_URL . 'assets/icon.svg',
			// One backup (?view=…) opens its detail; the shell starts the pages themselves.
			'startPage'  => $here && 0 === strpos( self::flux_start_page(), 'detail:' ) ? self::flux_start_page() : '',
		);
	}

	/** Flux GraphQL screen shell: first data printed by PHP, in-place navigation between the pages. */
	public function register_screens(): void {
		if ( ! function_exists( 'flx_admin_screen' ) || ! self::flux_available() ) {
			return;
		}
		foreach ( array( self::SLUG => 'dashboard', self::SLUG . '-list' => 'list', self::SLUG . '-settings' => 'settings' ) as $page => $start ) {
			flx_admin_screen(
				$page,
				array(
					'module'       => 'flxy-backup',
					'assets_dir'   => FLXY_DIR . 'assets/flux/',
					'assets_url'   => FLXY_URL . 'assets/flux/',
					'version'      => FLXY_VERSION,
					'options'      => array( __CLASS__, 'flux_options' ),
					'start'        => $start,
					'container_id' => 'flxy-app',
					'preload'      => 'dashboard' === $start ? array( array( 'FlxBackupSettingsScreen' ), array( 'FlxBackupJobRead' ), array( 'FlxBackupDashboard' ) ) : array( array( 'FlxBackupSettingsScreen' ) ),
					'wrap_class'   => 'flxy-react',
				)
			);
		}
	}

	private static function flux_assets(): void {
		if ( function_exists( 'flx_admin_screen_current' ) && '' !== flx_admin_screen_current() ) {
			return; // The Flux GraphQL screen shell loads the bundle.
		}
		$ver  = FLXY_VERSION . '-' . (int) filemtime( FLXY_DIR . 'assets/flux/flux-modules.js' );
		$opts = array_merge(
			array(
				'module'   => 'flxy-backup',
				'rest'     => rest_url(),
				'nonce'    => wp_create_nonce( 'wp_rest' ),
				'locale'   => get_user_locale(),
				'siteName' => get_bloginfo( 'name' ),
				'adminUrl' => admin_url(),
				'theme'    => 'light',
			),
			self::flux_options()
		);
		$opts['startPage'] = self::flux_start_page();
		wp_enqueue_style( 'flxy-flux', FLXY_URL . 'assets/flux/flux-modules.css', array(), $ver );
		wp_enqueue_script( 'flxy-flux', FLXY_URL . 'assets/flux/flux-modules.js', array(), $ver, true );
		add_filter( 'script_loader_tag', array( __CLASS__, 'flux_module_tag' ), 10, 2 );
		// Light / dark follows Flux Admin (html.flxa-dark), also when the user switches it on the page.
		wp_add_inline_script(
			'flxy-flux',
			'(function(){var h=document.documentElement,o=' . wp_json_encode( $opts ) . ';o.theme=h.classList.contains("flxa-dark")?"dark":"light";var slot=window.FluxModulesOptions||(window.FluxModulesOptions={});slot.id="flxy-app";slot.opts=o;function sync(){var el=document.getElementById("flxy-app");if(el)el.dataset.theme=h.classList.contains("flxa-dark")?"dark":"light";}new MutationObserver(sync).observe(h,{attributes:true,attributeFilter:["class"]});function go(){if(slot.done||!window.FluxModules)return;var el=document.getElementById(slot.id);if(!el)return;slot.done=true;try{window.FluxModules.mount(el,slot.opts);}catch(e){el.textContent=String(e&&e.message||e);}}if(document.readyState==="loading")document.addEventListener("DOMContentLoaded",go);go();})();',
			'before'
		);
	}

	/** Every page: the React screen's mount point and the "could not be loaded" box. */
	public function render(): void {
		if ( ! current_user_can( self::CAP ) ) {
			wp_die( esc_html__( 'You are not allowed to do this.', 'flx-backup' ), 403 );
		}
		if ( function_exists( 'flx_admin_screen_render' ) && flx_admin_screen_render() ) {
			return;
		}
		if ( ! self::flux_available() ) {
			if ( function_exists( 'flx_admin_screen_unavailable' ) ) {
				flx_admin_screen_unavailable();
			}
			return;
		}
		echo '<style>#flxy-app.flux-root{height:auto;background:transparent;font-family:inherit}</style>';
		echo '<div class="wrap flxy-react"><div id="flxy-app"><noscript>' . esc_html__( 'JavaScript is required for this screen.', 'flx-backup' ) . '</noscript></div>';
		if ( function_exists( 'flx_admin_screen_failsafe' ) ) {
			flx_admin_screen_failsafe( 'flxy-app' );
		}
		echo '</div>';
	}

	public function menu(): void {
		$this->hooks[] = add_menu_page(
			__( 'Backups', 'flx-backup' ),
			__( 'Backups', 'flx-backup' ),
			self::CAP,
			self::SLUG,
			array( $this, 'render' ),
			'none',
			76
		);
		$this->hooks[] = add_submenu_page( self::SLUG, __( 'Backups › Dashboard', 'flx-backup' ), __( 'Dashboard', 'flx-backup' ), self::CAP, self::SLUG, array( $this, 'render' ) );
		$this->hooks[] = add_submenu_page( self::SLUG, __( 'Backups', 'flx-backup' ), __( 'Backups', 'flx-backup' ), self::CAP, self::SLUG . '-list', array( $this, 'render' ) );
		$this->hooks[] = add_submenu_page( self::SLUG, __( 'Backups › Settings', 'flx-backup' ), __( 'Settings', 'flx-backup' ), self::CAP, self::SLUG . '-settings', array( $this, 'render' ) );
	}

	/** WordPress menü SVG'lerini tek renge boyar; renkli logo CSS arka planı olarak çizilir. */
	public function menu_icon_css(): void {
		echo '<style>#toplevel_page_' . esc_attr( self::SLUG ) . ' .wp-menu-image{background:url(' . esc_url( FLXY_URL . 'assets/icon.svg' ) . ') center/20px 20px no-repeat}#toplevel_page_' . esc_attr( self::SLUG ) . ' .wp-menu-image:before{content:none}</style>';
	}

	public function action_links( $links ) {
		array_unshift( $links, '<a href="' . esc_url( self::url( 'settings' ) ) . '">' . esc_html__( 'Settings', 'flx-backup' ) . '</a>' );
		return $links;
	}

	public function assets( $hook ): void {
		if ( in_array( $hook, $this->hooks, true ) && self::flux_available() ) {
			self::flux_assets();
		}
	}

	private function ajax_guard(): void {
		if ( ! current_user_can( self::CAP ) ) {
			wp_send_json_error( array( 'message' => __( 'You are not allowed to do this.', 'flx-backup' ) ), 403 );
		}
		check_ajax_referer( 'flxy', 'nonce' );
	}

	public function ajax_cancel(): void {
		$this->ajax_guard();
		$res = FLXY_Service::cancel();
		if ( is_wp_error( $res ) ) {
			wp_send_json_error( array( 'message' => $res->get_error_message() ) );
		}
		// 'pending': the step is running and the job is cancelled when it ends.
		wp_send_json_success( array( 'pending' => $res['pending'] ) );
	}

	public function download(): void {
		$id = sanitize_text_field( wp_unslash( $_GET['id'] ?? '' ) ); // phpcs:ignore WordPress.Security.NonceVerification
		if ( ! current_user_can( self::CAP ) ) {
			wp_die( esc_html__( 'You are not allowed to do this.', 'flx-backup' ), 403 );
		}
		check_admin_referer( 'flxy_download_' . $id );
		$r = FLXY_Storage::get( $id );
		if ( ! $r || 'ok' !== $r['status'] || ! FLXY_Storage::is_inside( $r['file'] ) || ! is_readable( $r['file'] ) ) {
			wp_die( esc_html__( 'Backup not found.', 'flx-backup' ), 404 );
		}
		$host = (string) wp_parse_url( home_url(), PHP_URL_HOST );
		$name = sanitize_file_name( 'flx-backup-' . $host . '-' . str_replace( '_', '-', $r['type'] ) . '-' . gmdate( 'Ymd-His', $r['created'] ) . '.zip' );
		$size = (int) filesize( $r['file'] );

		if ( function_exists( 'set_time_limit' ) ) {
			@set_time_limit( 0 ); // phpcs:ignore WordPress.PHP.NoSilencedErrors
		}
		while ( ob_get_level() ) {
			ob_end_clean();
		}
		nocache_headers();
		header( 'Content-Type: application/zip' );
		header( 'Content-Disposition: attachment; filename="' . $name . '"' );
		header( 'Content-Length: ' . $size );
		header( 'X-Content-Type-Options: nosniff' );
		header( 'X-Accel-Buffering: no' );
		$fh = fopen( $r['file'], 'rb' ); // phpcs:ignore WordPress.WP.AlternativeFunctions
		while ( $fh && ! feof( $fh ) && ! connection_aborted() ) {
			echo fread( $fh, 1048576 ); // phpcs:ignore WordPress.Security.EscapeOutput,WordPress.WP.AlternativeFunctions
			flush();
		}
		if ( $fh ) {
			fclose( $fh ); // phpcs:ignore WordPress.WP.AlternativeFunctions
		}
		exit;
	}
}
