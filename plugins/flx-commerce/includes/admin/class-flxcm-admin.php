<?php
/**
 * Yönetim: "Mağaza" menüsü. 0.7.0: sayfalar tek React ekranı (packages/modules/src/commerce) Flux GraphQL
 * ekran kabuğunda (flx_admin_screen), ilk sorgular preload; klasik sayfalar ve görünüm geçişi kaldırıldı.
 * Flux CRM kişi kartının Mağaza bölümleri (page-customer.php) PHP'de kalır.
 *
 * @package FLX_Commerce
 */

defined( 'ABSPATH' ) || exit;

require_once FLXCM_DIR . 'includes/admin/page-customer.php';

final class FLXCM_Admin {

	const CAP_VIEW   = 'view_woocommerce_reports';
	const CAP_MANAGE = 'manage_woocommerce';

	/** @var string[] */
	private static $hooks = array();

	public static function init() {
		add_action( 'admin_menu', array( __CLASS__, 'register_screens' ), 20 );
		add_action( 'admin_menu', array( __CLASS__, 'menu' ), 60 );
		add_action( 'admin_enqueue_scripts', array( __CLASS__, 'assets' ) );
		add_action( 'wp_dashboard_setup', array( __CLASS__, 'widget' ) );
		FLXCM_Page_Customer::init();
	}

	/** The Store pages and the route each one starts on. */
	const SCREENS = array(
		'flx-commerce'               => 'overview',
		'flx-commerce-customers'     => 'customers',
		'flx-commerce-subscriptions' => 'subscriptions',
		'flx-commerce-reports'       => 'reports',
		'flx-commerce-settings'      => 'settings',
	);

	/** @return array<string,string> */
	private static function current_args(): array {
		$page = isset( $_GET['page'] ) ? sanitize_key( wp_unslash( $_GET['page'] ) ) : 'flx-commerce'; // phpcs:ignore WordPress.Security.NonceVerification
		$args = array( 'page' => $page );
		foreach ( array( 'customer', 'email', 'days', 'preset', 'from', 'to', 's', 'segment', 'status', 'orderby', 'order' ) as $key ) {
			if ( ! isset( $_GET[ $key ] ) || '' === (string) $_GET[ $key ] ) { // phpcs:ignore WordPress.Security.NonceVerification
				continue;
			}
			$raw          = wp_unslash( $_GET[ $key ] ); // phpcs:ignore WordPress.Security.NonceVerification
			$args[ $key ] = 'customer' === $key ? (string) absint( $raw ) : ( 'email' === $key ? sanitize_email( $raw ) : sanitize_text_field( $raw ) );
		}
		return $args;
	}

	/** The route the current page starts on (customer:12, reports?preset=d7 …). */
	public static function flux_start_page(): string {
		$args  = self::current_args();
		$start = self::SCREENS[ $args['page'] ] ?? 'overview';
		if ( 'customers' === $start ) {
			if ( ! empty( $args['customer'] ) ) {
				$start = 'customer:' . $args['customer'];
			} elseif ( ! empty( $args['email'] ) ) {
				$start = 'email:' . $args['email'];
			}
		}
		$keep = array();
		foreach ( array( 'days', 'preset', 'from', 'to', 's', 'segment', 'status', 'orderby', 'order' ) as $key ) {
			if ( ! empty( $args[ $key ] ) ) {
				$keep[ $key ] = $args[ $key ];
			}
		}
		return $keep ? $start . '?' . http_build_query( $keep ) : $start;
	}

	/** Registers the pages on the Flux GraphQL screen shell (before the menu). */
	public static function register_screens(): void {
		if ( ! function_exists( 'flx_admin_screen' ) ) {
			return;
		}
		foreach ( self::SCREENS as $page => $route ) {
			flx_admin_screen(
				$page,
				array(
					'module'       => 'flxcm-store',
					'assets_dir'   => FLXCM_DIR . 'assets/flux/',
					'assets_url'   => FLXCM_URL . 'assets/flux/',
					'version'      => FLXCM_VERSION,
					'enabled'      => '__return_true',
					'options'      => array( __CLASS__, 'screen_options' ),
					'start'        => $route,
					'container_id' => 'flxcm-app',
					'preload'      => array( __CLASS__, 'screen_preload' ),
				)
			);
		}
	}

	/**
	 * Mount options besides what the shell adds.
	 *
	 * @param string $page Page slug.
	 * @return array<string,mixed>
	 */
	public static function screen_options( $page = '' ): array {
		unset( $page );
		$accent = function_exists( 'flxa_accent' ) ? (string) flxa_accent() : '#2c7a64';
		return array(
			'startPage' => self::flux_start_page(),
			'colors'    => array(
				'primary'         => $accent,
				'primaryContrast' => function_exists( 'flxa_on_color' ) ? (string) flxa_on_color( $accent ) : '#ffffff',
			),
			'appIcon'   => FLXCM_URL . 'assets/icon.svg',
		);
	}

	/**
	 * @param mixed $query Query arguments of the page (unused: the start route comes from the address).
	 * @return list<array{0:string,1:array<string,mixed>}>
	 */
	public static function screen_preload( $query = array() ): array {
		unset( $query );
		return self::preload_for( self::flux_start_page() );
	}

	/**
	 * The first queries of a start route, with the variables the screen sends (same store key). A route
	 * with filters (?preset=…) is left to the screen.
	 *
	 * @param string $start Start route.
	 * @return list<array{0:string,1:array<string,mixed>}>
	 */
	public static function preload_for( string $start ): array {
		if ( false !== strpos( $start, '?' ) ) {
			return array();
		}
		if ( 0 === strpos( $start, 'customer:' ) ) {
			return array( array( 'FlxCommerceCustomer', array( 'id' => substr( $start, 9 ) ) ) );
		}
		$d30 = array(
			'preset' => 'D30',
			'from'   => null,
			'to'     => null,
		);
		switch ( $start ) {
			case 'overview':
				return array(
					array( 'FlxCommerceSummary', array( 'days' => 30 ) ),
					array( 'FlxCommerceReportProducts', array( 'first' => 6 ) + $d30 ),
					array( 'FlxCommerceReportMethods', array( 'first' => 6 ) + $d30 ),
				);
			case 'customers':
				return array(
					array(
						'FlxCommerceCustomers',
						array(
							'first'   => 25,
							'after'   => null,
							'search'  => null,
							'segment' => 'ALL',
							'orderby' => 'LAST_AT',
							'order'   => 'DESC',
						),
					),
				);
			case 'subscriptions':
				return array(
					array(
						'FlxCommerceSubscriptions',
						array(
							'first'  => 25,
							'after'  => null,
							'search' => null,
							'status' => null,
						),
					),
				);
			case 'reports':
				return array(
					array( 'FlxCommerceReport', $d30 ),
					array( 'FlxCommerceReportDays', $d30 + array( 'first' => 50 ) ),
					array( 'FlxCommerceReportProducts', $d30 + array( 'first' => 50 ) ),
					array( 'FlxCommerceReportCategories', $d30 ),
					array( 'FlxCommerceReportMethods', $d30 ),
				);
			case 'settings':
				return array( array( 'FlxCommerceSettings', array() ) );
		}
		return array();
	}

	/** The menu callback: the shell prints the screen. */
	public static function screen(): void {
		if ( function_exists( 'flx_admin_screen_page' ) ) {
			flx_admin_screen_page();
			return;
		}
		echo '<div class="wrap"><div class="notice notice-error"><p>' . esc_html__( 'Something went wrong. Please try again.', 'flx-commerce' ) . ' (Flux GraphQL)</p></div></div>';
	}

	public static function menu_icon() {
		$svg = '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="#a7aaad" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M6 2 3 6v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2V6l-3-4Z"/><path d="M3 6h18"/><path d="M16 10a4 4 0 0 1-8 0"/></svg>';
		return 'data:image/svg+xml;base64,' . base64_encode( $svg ); // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions
	}

	public static function menu() {
		$count = FLXCM_Stats::awaiting_count();
		$badge = $count ? ' <span class="awaiting-mod count-' . (int) $count . '"><span class="pending-count">' . number_format_i18n( $count ) . '</span></span>' : '';
		self::$hooks[] = add_menu_page( __( 'Store', 'flx-commerce' ), __( 'Store', 'flx-commerce' ) . $badge, self::CAP_VIEW, 'flx-commerce', array( __CLASS__, 'screen' ), self::menu_icon(), 56 );
		self::$hooks[] = add_submenu_page( 'flx-commerce', __( 'Overview', 'flx-commerce' ), __( 'Overview', 'flx-commerce' ), self::CAP_VIEW, 'flx-commerce', array( __CLASS__, 'screen' ) );
		self::$hooks[] = add_submenu_page( 'flx-commerce', __( 'Customers', 'flx-commerce' ), __( 'Customers', 'flx-commerce' ), self::CAP_VIEW, 'flx-commerce-customers', array( __CLASS__, 'screen' ) );
		self::$hooks[] = add_submenu_page( 'flx-commerce', __( 'Subscriptions', 'flx-commerce' ), __( 'Subscriptions', 'flx-commerce' ), self::CAP_VIEW, 'flx-commerce-subscriptions', array( __CLASS__, 'screen' ) );
		self::$hooks[] = add_submenu_page( 'flx-commerce', __( 'Sales report', 'flx-commerce' ), __( 'Sales report', 'flx-commerce' ), self::CAP_VIEW, 'flx-commerce-reports', array( __CLASS__, 'screen' ) );
		self::$hooks[] = add_submenu_page( 'flx-commerce', __( 'Settings', 'flx-commerce' ), __( 'Settings', 'flx-commerce' ), self::CAP_MANAGE, 'flx-commerce-settings', array( __CLASS__, 'screen' ) );
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
		if ( function_exists( 'flx_admin_screen_current' ) && '' !== flx_admin_screen_current() ) {
			return; // 0.7.0: the Flux GraphQL screen shell loads the React screen.
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
