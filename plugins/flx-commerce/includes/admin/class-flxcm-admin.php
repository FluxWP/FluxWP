<?php
/**
 * Yönetim: "Mağaza" menüsü (Genel bakış, Müşteriler, Raporlar, Ayarlar), ortak parçalar ve ayar kaydı.
 *
 * @package FLX_Commerce
 */

defined( 'ABSPATH' ) || exit;

require_once FLXCM_DIR . 'includes/admin/page-dashboard.php';
require_once FLXCM_DIR . 'includes/admin/page-customers.php';
require_once FLXCM_DIR . 'includes/admin/page-customer.php';
require_once FLXCM_DIR . 'includes/admin/page-reports.php';
require_once FLXCM_DIR . 'includes/admin/page-subscriptions.php';
require_once FLXCM_DIR . 'includes/admin/page-settings.php';

final class FLXCM_Admin {

	const CAP_VIEW   = 'view_woocommerce_reports';
	const CAP_MANAGE = 'manage_woocommerce';

	/** @var string[] */
	private static $hooks = array();

	public static function init() {
		add_action( 'admin_menu', array( __CLASS__, 'menu' ), 60 );
		add_action( 'admin_enqueue_scripts', array( __CLASS__, 'assets' ) );
		add_action( 'admin_init', array( __CLASS__, 'switch_ui' ) );
		add_action( 'admin_post_flxcm_save', array( 'FLXCM_Page_Settings', 'save' ) );
		add_action( 'admin_post_flxcm_report_csv', array( 'FLXCM_Page_Reports', 'csv' ) );
		add_action( 'wp_dashboard_setup', array( __CLASS__, 'widget' ) );
		FLXCM_Page_Customer::init();
		FLXCM_Page_Subscriptions::init();
	}

	/**
	 * React screens are the default when the embed and Flux GraphQL are present.
	 * User meta flxcm_ui=classic keeps the previous pages.
	 */
	public static function flux_available(): bool {
		return is_readable( FLXCM_DIR . 'assets/flux/flux-modules.js' ) && function_exists( 'flx_gql_ready' ) && flx_gql_ready();
	}

	public static function flux_ui(): bool {
		return self::flux_available() && 'classic' !== get_user_meta( get_current_user_id(), 'flxcm_ui', true );
	}

	/**
	 * @return array<string,string>
	 */
	private static function current_args(): array {
		$page = isset( $_GET['page'] ) ? sanitize_key( wp_unslash( $_GET['page'] ) ) : 'flx-commerce'; // phpcs:ignore WordPress.Security.NonceVerification
		$args = array( 'page' => $page );
		foreach ( array( 'customer', 'email', 'days', 'preset', 'from', 'to', 's', 'segment', 'status', 'orderby', 'order' ) as $key ) {
			if ( ! isset( $_GET[ $key ] ) || '' === (string) $_GET[ $key ] ) { // phpcs:ignore WordPress.Security.NonceVerification
				continue;
			}
			$raw = wp_unslash( $_GET[ $key ] ); // phpcs:ignore WordPress.Security.NonceVerification
			$args[ $key ] = 'customer' === $key ? (string) absint( $raw ) : ( 'email' === $key ? sanitize_email( $raw ) : sanitize_text_field( $raw ) );
		}
		return $args;
	}

	public static function ui_switch_url( string $ui ): string {
		return wp_nonce_url( add_query_arg( array_merge( self::current_args(), array( 'flxcm_ui' => 'flux' === $ui ? 'flux' : 'classic' ) ), admin_url( 'admin.php' ) ), 'flxcm_ui' );
	}

	public static function switch_ui(): void {
		$page = isset( $_GET['page'] ) ? sanitize_key( wp_unslash( $_GET['page'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification
		if ( ! isset( $_GET['flxcm_ui'] ) || 0 !== strpos( $page, 'flx-commerce' ) ) { // phpcs:ignore WordPress.Security.NonceVerification
			return;
		}
		if ( ! current_user_can( self::CAP_VIEW ) && ! current_user_can( self::CAP_MANAGE ) ) {
			return;
		}
		check_admin_referer( 'flxcm_ui' );
		if ( 'classic' === $_GET['flxcm_ui'] ) { // phpcs:ignore WordPress.Security.NonceVerification
			update_user_meta( get_current_user_id(), 'flxcm_ui', 'classic' );
		} else {
			delete_user_meta( get_current_user_id(), 'flxcm_ui' );
		}
		wp_safe_redirect( add_query_arg( self::current_args(), admin_url( 'admin.php' ) ) );
		exit;
	}

	private static function flux_start_page(): string {
		$args  = self::current_args();
		$page  = $args['page'];
		$start = 'overview';
		if ( 'flx-commerce-customers' === $page ) {
			if ( ! empty( $args['customer'] ) ) {
				$start = 'customer:' . $args['customer'];
			} elseif ( ! empty( $args['email'] ) ) {
				$start = 'email:' . $args['email'];
			} else {
				$start = 'customers';
			}
		} elseif ( 'flx-commerce-subscriptions' === $page ) {
			$start = 'subscriptions';
		} elseif ( 'flx-commerce-reports' === $page ) {
			$start = 'reports';
		} elseif ( 'flx-commerce-settings' === $page ) {
			$start = 'settings';
		}
		$keep = array();
		foreach ( array( 'days', 'preset', 'from', 'to', 's', 'segment', 'status', 'orderby', 'order' ) as $key ) {
			if ( ! empty( $args[ $key ] ) ) {
				$keep[ $key ] = $args[ $key ];
			}
		}
		return $keep ? $start . '?' . http_build_query( $keep ) : $start;
	}

	public static function flux_module_tag( $tag, $handle ) {
		if ( 'flxcm-flux' !== $handle ) {
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

	private static function flux_assets(): void {
		$ver    = FLXCM_VERSION . '-' . (int) filemtime( FLXCM_DIR . 'assets/flux/flux-modules.js' );
		$accent = function_exists( 'flxa_accent' ) ? (string) flxa_accent() : '#2c7a64';
		$opts   = array(
			'module'     => 'flxcm-store',
			'rest'       => rest_url(),
			'nonce'      => wp_create_nonce( 'wp_rest' ),
			'locale'     => get_user_locale(),
			'siteName'   => get_bloginfo( 'name' ),
			'adminUrl'   => admin_url(),
			'theme'      => 'light',
			'colors'     => array(
				'primary'         => $accent,
				'primaryContrast' => function_exists( 'flxa_on_color' ) ? (string) flxa_on_color( $accent ) : '#ffffff',
			),
			'appIcon'    => FLXCM_URL . 'assets/icon.svg',
			'startPage'  => self::flux_start_page(),
			'classicUrl' => self::ui_switch_url( 'classic' ),
		);
		wp_enqueue_style( 'flxcm-flux', FLXCM_URL . 'assets/flux/flux-modules.css', array(), $ver );
		wp_enqueue_script( 'flxcm-flux', FLXCM_URL . 'assets/flux/flux-modules.js', array(), $ver, true );
		add_filter( 'script_loader_tag', array( __CLASS__, 'flux_module_tag' ), 10, 2 );
		wp_add_inline_script(
			'flxcm-flux',
			'(function(){var h=document.documentElement,o=' . wp_json_encode( $opts ) . ';o.theme=h.classList.contains("flxa-dark")?"dark":"light";var slot=window.FluxModulesOptions||(window.FluxModulesOptions={});slot.id="flxcm-app";slot.opts=o;function sync(){var el=document.getElementById("flxcm-app");if(el)el.dataset.theme=h.classList.contains("flxa-dark")?"dark":"light";}new MutationObserver(sync).observe(h,{attributes:true,attributeFilter:["class"]});function go(){if(slot.done||!window.FluxModules)return;var el=document.getElementById(slot.id);if(!el)return;slot.done=true;try{window.FluxModules.mount(el,slot.opts);}catch(e){el.textContent=String(e&&e.message||e);}}if(document.readyState==="loading")document.addEventListener("DOMContentLoaded",go);go();})();',
			'before'
		);
	}

	/** Prints the React mount point. False means the classic page should render. */
	public static function render_flux(): bool {
		if ( ! self::flux_ui() ) {
			return false;
		}
		echo '<style>#flxcm-app.flux-root{height:auto;background:transparent;font-family:inherit}</style>';
		echo '<div class="wrap flxcm-react"><div id="flxcm-app"><noscript>' . esc_html__( 'JavaScript is required for this screen.', 'flx-commerce' ) . '</noscript></div></div>';
		return true;
	}

	public static function menu_icon() {
		$svg = '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="#a7aaad" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M6 2 3 6v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2V6l-3-4Z"/><path d="M3 6h18"/><path d="M16 10a4 4 0 0 1-8 0"/></svg>';
		return 'data:image/svg+xml;base64,' . base64_encode( $svg ); // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions
	}

	public static function menu() {
		$count = FLXCM_Stats::awaiting_count();
		$badge = $count ? ' <span class="awaiting-mod count-' . (int) $count . '"><span class="pending-count">' . number_format_i18n( $count ) . '</span></span>' : '';
		self::$hooks[] = add_menu_page( __( 'Store', 'flx-commerce' ), __( 'Store', 'flx-commerce' ) . $badge, self::CAP_VIEW, 'flx-commerce', array( 'FLXCM_Page_Dashboard', 'render' ), self::menu_icon(), 56 );
		self::$hooks[] = add_submenu_page( 'flx-commerce', __( 'Overview', 'flx-commerce' ), __( 'Overview', 'flx-commerce' ), self::CAP_VIEW, 'flx-commerce', array( 'FLXCM_Page_Dashboard', 'render' ) );
		self::$hooks[] = add_submenu_page( 'flx-commerce', __( 'Customers', 'flx-commerce' ), __( 'Customers', 'flx-commerce' ), self::CAP_VIEW, 'flx-commerce-customers', array( 'FLXCM_Page_Customers', 'render' ) );
		self::$hooks[] = add_submenu_page( 'flx-commerce', __( 'Subscriptions', 'flx-commerce' ), __( 'Subscriptions', 'flx-commerce' ), self::CAP_VIEW, 'flx-commerce-subscriptions', array( 'FLXCM_Page_Subscriptions', 'render' ) );
		self::$hooks[] = add_submenu_page( 'flx-commerce', __( 'Sales report', 'flx-commerce' ), __( 'Sales report', 'flx-commerce' ), self::CAP_VIEW, 'flx-commerce-reports', array( 'FLXCM_Page_Reports', 'render' ) );
		self::$hooks[] = add_submenu_page( 'flx-commerce', __( 'Settings', 'flx-commerce' ), __( 'Settings', 'flx-commerce' ), self::CAP_MANAGE, 'flx-commerce-settings', array( 'FLXCM_Page_Settings', 'render' ) );
	}

	public static function is_screen() {
		$page = isset( $_GET['page'] ) ? sanitize_key( $_GET['page'] ) : ''; // phpcs:ignore WordPress.Security.NonceVerification
		return 0 === strpos( $page, 'flx-commerce' );
	}

	public static function assets() {
		$page = isset( $_GET['page'] ) ? sanitize_key( $_GET['page'] ) : ''; // phpcs:ignore WordPress.Security.NonceVerification
		// Flux CRM kişi kartındaki Mağaza bölümleri (CRM'in kendi sayfasında da gösterilir).
		if ( self::is_screen() || 0 === strpos( $page, 'flx-crm' ) ) {
			wp_enqueue_style( 'flxcm-card', FLXCM_URL . 'assets/person-card.css', array(), FLXCM_VERSION );
		}
		if ( ! self::is_screen() ) {
			return;
		}
		if ( self::flux_ui() ) {
			self::flux_assets();
			return;
		}
		if ( isset( $_GET['customer'] ) || isset( $_GET['email'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification
			if ( function_exists( 'flxcrm_person_card_enqueue' ) ) {
				flxcrm_person_card_enqueue();
			}
		}
		wp_enqueue_style( 'flxcm-admin', FLXCM_URL . 'assets/admin.css', array(), FLXCM_VERSION );
		wp_enqueue_script( 'flxcm-admin', FLXCM_URL . 'assets/admin.js', array(), FLXCM_VERSION, true );
		wp_localize_script(
			'flxcm-admin',
			'flxcmAdmin',
			array(
				'ajax'    => admin_url( 'admin-ajax.php' ),
				'nonce'   => wp_create_nonce( 'flxcm_admin' ),
				'working' => __( 'Scanning: %1$s / %2$s orders', 'flx-commerce' ),
				'done'    => __( 'Done. Reloading…', 'flx-commerce' ),
				'fail'    => __( 'Something went wrong. Please try again.', 'flx-commerce' ),
				'saved'   => __( 'Saved', 'flx-commerce' ),
				'copied'  => __( 'Copied', 'flx-commerce' ),
			)
		);
	}

	/** Pano (Başlangıç) küçük kartı: bugün ve bekleyen ödemeler. */
	public static function widget() {
		if ( current_user_can( self::CAP_VIEW ) ) {
			wp_add_dashboard_widget( 'flxcm_today', __( 'Store · today', 'flx-commerce' ), array( __CLASS__, 'widget_render' ) );
		}
	}

	public static function widget_render() {
		$t = FLXCM_Stats::summary( FLXCM_Stats::today(), FLXCM_Stats::today() );
		echo '<p style="display:flex;gap:24px;margin:0 0 8px"><span><strong style="font-size:20px">' . esc_html( flxcm_money_short( $t['revenue'] ) ) . '</strong><br><span class="description">' . esc_html__( 'Revenue', 'flx-commerce' ) . '</span></span>';
		echo '<span><strong style="font-size:20px">' . esc_html( number_format_i18n( $t['orders'] ) ) . '</strong><br><span class="description">' . esc_html__( 'Orders', 'flx-commerce' ) . '</span></span>';
		echo '<span><strong style="font-size:20px">' . esc_html( number_format_i18n( FLXCM_Stats::awaiting_count() ) ) . '</strong><br><span class="description">' . esc_html__( 'Awaiting payment', 'flx-commerce' ) . '</span></span></p>';
		echo '<p style="margin:0"><a href="' . esc_url( flxcm_admin_url() ) . '">' . esc_html__( 'Open the overview', 'flx-commerce' ) . ' →</a></p>';
	}

	/* ---------- Ortak parçalar ---------- */

	public static function open( $title, $lead = '', $actions = '' ) {
		echo '<div class="wrap flxcm">';
		echo '<div class="flxcm-head"><div><h1>' . esc_html( $title ) . '</h1>';
		if ( '' !== $lead ) {
			echo '<p class="flxcm-lead">' . esc_html( $lead ) . '</p>';
		}
		echo '</div>';
		if ( '' !== $actions ) {
			echo '<div class="flxcm-head__actions">' . $actions . '</div>'; // phpcs:ignore WordPress.Security.EscapeOutput
		}
		echo '</div>';
		if ( self::flux_available() && ! self::flux_ui() ) {
			echo '<p class="flxcm-newview"><a class="button" href="' . esc_url( self::ui_switch_url( 'flux' ) ) . '">' . esc_html__( 'New view', 'flx-commerce' ) . '</a></p>';
		}
		self::tabs();
	}

	public static function close() {
		echo '</div>';
	}

	public static function tabs() {
		$page = isset( $_GET['page'] ) ? sanitize_key( $_GET['page'] ) : 'flx-commerce'; // phpcs:ignore WordPress.Security.NonceVerification
		$tabs = array(
			'flx-commerce'           => __( 'Overview', 'flx-commerce' ),
			'flx-commerce-customers' => __( 'Customers', 'flx-commerce' ),
			'flx-commerce-subscriptions' => __( 'Subscriptions', 'flx-commerce' ),
			'flx-commerce-reports'   => __( 'Sales report', 'flx-commerce' ),
		);
		if ( current_user_can( self::CAP_MANAGE ) ) {
			$tabs['flx-commerce-settings'] = __( 'Settings', 'flx-commerce' );
		}
		echo '<nav class="flxcm-tabs" aria-label="' . esc_attr__( 'Store', 'flx-commerce' ) . '">';
		foreach ( $tabs as $slug => $label ) {
			echo '<a class="flxcm-tab' . ( $slug === $page ? ' is-on' : '' ) . '" href="' . esc_url( admin_url( 'admin.php?page=' . $slug ) ) . '"' . ( $slug === $page ? ' aria-current="page"' : '' ) . '>' . esc_html( $label ) . '</a>';
		}
		echo '</nav>';
	}

	/**
	 * KPI kartı.
	 *
	 * @param float|null $delta Önceki döneme göre %.
	 */
	public static function kpi( $icon, $label, $value, $delta = null, $hint = '' ) {
		$d = '';
		if ( null !== $delta ) {
			$cls = $delta > 0.05 ? 'is-up' : ( $delta < -0.05 ? 'is-down' : 'is-flat' );
			$d   = '<span class="flxcm-delta ' . $cls . '">' . ( $delta > 0 ? '+' : '' ) . esc_html( number_format_i18n( $delta, abs( $delta ) < 10 ? 1 : 0 ) ) . '%</span>';
		}
		echo '<div class="flxcm-kpi"><span class="flxcm-kpi__icon">' . flxcm_icon( $icon, 18 ) . '</span><div class="flxcm-kpi__body"><span class="flxcm-kpi__label">' . esc_html( $label ) . '</span><strong class="flxcm-kpi__value">' . esc_html( $value ) . '</strong>' . $d . ( '' !== $hint ? '<span class="flxcm-kpi__hint">' . esc_html( $hint ) . '</span>' : '' ) . '</div></div>'; // phpcs:ignore WordPress.Security.EscapeOutput
	}

	/** Günlük ciro sütun grafiği (satır içi SVG, kütüphanesiz). */
	public static function chart( array $series ) {
		$n   = count( $series );
		if ( ! $n ) {
			return;
		}
		$max = 0.0;
		foreach ( $series as $v ) {
			$max = max( $max, (float) $v[0] );
		}
		$max  = $max > 0 ? $max : 1;
		$nice = self::nice_max( $max );
		$w    = 720;
		$h    = 200;
		$pl   = 0;
		$gap  = $n > 60 ? 1 : ( $n > 20 ? 2 : 6 );
		$bw   = max( 1, ( $w - $pl - $gap * ( $n - 1 ) ) / $n );
		$keys = array_keys( $series );
		echo '<figure class="flxcm-chart"><svg viewBox="0 0 ' . (int) $w . ' ' . (int) $h . '" preserveAspectRatio="none" role="img" aria-label="' . esc_attr__( 'Daily revenue', 'flx-commerce' ) . '">';
		foreach ( array( 0.25, 0.5, 0.75, 1 ) as $g ) {
			$y = $h - $h * $g;
			echo '<line class="flxcm-chart__grid" x1="0" x2="' . (int) $w . '" y1="' . esc_attr( $y ) . '" y2="' . esc_attr( $y ) . '"/>';
		}
		$i = 0;
		foreach ( $series as $day => $v ) {
			$bh = $v[0] > 0 ? max( 2, $h * (float) $v[0] / $nice ) : 0;
			$x  = $pl + $i * ( $bw + $gap );
			/* translators: 1: date, 2: revenue, 3: order count */
			$tip = sprintf( __( '%1$s · %2$s · %3$s orders', 'flx-commerce' ), flxcm_date( FLXCM_Stats::ts( $day ) + 2 * HOUR_IN_SECONDS, 'short' ), flxcm_money( $v[0] ), number_format_i18n( $v[1] ) );
			echo '<rect class="flxcm-chart__bar" x="' . esc_attr( round( $x, 2 ) ) . '" y="' . esc_attr( round( $h - $bh, 2 ) ) . '" width="' . esc_attr( round( $bw, 2 ) ) . '" height="' . esc_attr( round( $bh, 2 ) ) . '" rx="' . ( $bw > 8 ? 3 : 1 ) . '"><title>' . esc_html( $tip ) . '</title></rect>';
			++$i;
		}
		echo '</svg><div class="flxcm-chart__axis" aria-hidden="true">';
		// Ekseni kalabalıklaştırmadan en fazla ~7 tarih (HTML; SVG ölçeklenince yazı bozulmasın).
		$step = max( 1, (int) ceil( $n / 7 ) );
		for ( $j = 0; $j < $n; $j += $step ) {
			$x = ( $pl + $j * ( $bw + $gap ) + $bw / 2 ) / $w * 100;
			echo '<span style="left:' . esc_attr( round( $x, 2 ) ) . '%">' . esc_html( flxcm_date( FLXCM_Stats::ts( $keys[ $j ] ) + 2 * HOUR_IN_SECONDS, 'short' ) ) . '</span>';
		}
		echo '</div><figcaption class="flxcm-chart__max">' . esc_html( flxcm_money_short( $nice ) ) . '</figcaption></figure>';
	}

	private static function nice_max( $v ) {
		$e = pow( 10, floor( log10( $v ) ) );
		foreach ( array( 1, 2, 2.5, 5, 10 ) as $m ) {
			if ( $v <= $m * $e ) {
				return $m * $e;
			}
		}
		return 10 * $e;
	}

	/** Oransal çubuklu küçük tablo satırları. */
	public static function bars_table( array $rows, $label_i, $value_i, $count_i, $count_label, $empty ) {
		if ( ! $rows ) {
			echo '<p class="flxcm-empty">' . esc_html( $empty ) . '</p>';
			return;
		}
		$max = 0.0;
		foreach ( $rows as $r ) {
			$max = max( $max, (float) $r[ $value_i ] );
		}
		echo '<ul class="flxcm-bars">';
		foreach ( $rows as $r ) {
			$pct = $max > 0 ? (float) $r[ $value_i ] / $max * 100 : 0;
			echo '<li><div class="flxcm-bars__top"><span class="flxcm-bars__name">' . esc_html( (string) $r[ $label_i ] ) . '</span><span class="flxcm-bars__val">' . esc_html( flxcm_money( $r[ $value_i ] ) ) . '</span></div>';
			echo '<div class="flxcm-bars__track"><span style="width:' . esc_attr( round( $pct, 1 ) ) . '%"></span></div>';
			echo '<span class="flxcm-bars__sub">' . esc_html( number_format_i18n( (float) $r[ $count_i ] ) . ' ' . $count_label ) . '</span></li>';
		}
		echo '</ul>';
	}
}
