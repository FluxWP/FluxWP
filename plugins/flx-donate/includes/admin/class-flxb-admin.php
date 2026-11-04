<?php
/**
 * Flux Donate: admin menu "Bağışlar", assets, form handlers and shared UI helpers.
 *
 * @package FLX_Bagis
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

require_once __DIR__ . '/page-dashboard.php';
require_once __DIR__ . '/page-donations.php';
require_once __DIR__ . '/page-donors.php';
require_once __DIR__ . '/page-subscriptions.php';
require_once __DIR__ . '/page-types.php';
require_once __DIR__ . '/page-settings.php';
require_once __DIR__ . '/page-payments.php';
require_once __DIR__ . '/page-add.php';
require_once __DIR__ . '/page-track.php';
require_once __DIR__ . '/page-partners.php';
require_once __DIR__ . '/page-reports.php';
require_once __DIR__ . '/page-import.php';
require_once __DIR__ . '/class-flxb-approval.php';

final class FLXB_Admin {

	const SLUG = 'flxb-dashboard';

	/** @var string[] page hook suffixes of our screens */
	private static $hooks = array();

	public static function init() {
		add_action( 'admin_menu', array( __CLASS__, 'menu' ) );
		add_action( 'admin_head', array( __CLASS__, 'menu_icon_css' ) );
		add_action( 'admin_enqueue_scripts', array( __CLASS__, 'assets' ) );
		add_action( 'wp_ajax_flxb_stats', array( 'FLXB_Page_Dashboard', 'ajax' ) );
		add_action( 'admin_post_flxb_csv', array( 'FLXB_Page_Donations', 'csv' ) );
		add_action( 'admin_post_flxb_receipt', array( 'FLXB_Page_Donations', 'receipt' ) );
		foreach ( array( 'save', 'status', 'move', 'delete', 'samples' ) as $a ) {
			add_action( 'admin_post_flxb_type_' . $a, array( 'FLXB_Page_Types', $a ) );
		}
		add_action( 'admin_post_flxb_settings_save', array( 'FLXB_Page_Settings', 'save' ) );
		add_action( 'admin_post_flxb_backfill', array( 'FLXB_Page_Settings', 'backfill' ) );
		add_action( 'admin_post_flxb_donor_save', array( 'FLXB_Page_Donors', 'save' ) );
		add_action( 'admin_post_flxb_add_donation', array( 'FLXB_Page_Add', 'save' ) );
		add_action( 'admin_post_flxb_ref_request', array( 'FLXB_Page_Add', 'save_request' ) );
		FLXB_Approval::init();
		FLXB_Page_Track::init();
		FLXB_Page_Partners::init();
		FLXB_Page_Reports::init();
		FLXB_Page_Import::init();
		FLXB_Detail_Tabs::init();
		add_action( 'admin_post_flxb_bulk', array( 'FLXB_Page_Donations', 'bulk' ) );
		add_action( 'admin_post_flxb_cert_remake', array( 'FLXB_Page_Donations', 'cert_post' ) );
		add_action( 'admin_post_flxb_cert_send', array( 'FLXB_Page_Donations', 'cert_post' ) );
		add_action( 'admin_post_flxb_items_save', array( 'FLXB_Page_Donations', 'items_save' ) );
		add_action( 'admin_post_flxb_ref_save', array( 'FLXB_Page_Donations', 'ref_save' ) );
		add_action( 'admin_page_access_denied', array( __CLASS__, 'own_home' ) );
		add_action(
			'plugins_loaded',
			function () {
				if ( flxb_owns_payments() ) {
					FLXB_Page_Payments::init();
				}
			},
			2
		);
	}

	/** Colour menu logo (shared FluxWP logos). One swap point: replace assets/icon.svg or filter `flxb_menu_icon_url`. */
	public static function menu_icon_url() {
		return (string) apply_filters( 'flxb_menu_icon_url', FLXB_URL . 'assets/icon.svg?ver=' . FLXB_VERSION );
	}

	/** WordPress paints menu icons in one colour, so the colour logo is a background (default, hover, current, folded menu). */
	public static function menu_icon_css() {
		$top = flxb_can_view() ? self::SLUG : ( self::staff_pages() ? self::staff_pages()[0][0] : self::SLUG ); // staff: their first page
		$sel = '#adminmenu #toplevel_page_' . $top . ' .wp-menu-image';
		echo '<style id="flxb-menu-icon">' . $sel . '{background:url("' . esc_url( self::menu_icon_url() ) . '") center/20px 20px no-repeat;opacity:.9}' . $sel . ':before{content:none}#adminmenu #toplevel_page_' . $top . ':hover .wp-menu-image,#adminmenu #toplevel_page_' . $top . '.wp-has-current-submenu .wp-menu-image{opacity:1}</style>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- static CSS, URL escaped
	}

	/**
	 * 2.3.0: pages of a user without the full view (Flux Roles capabilities), in menu order:
	 * [ slug, page title, menu title, capability, callback ].
	 */
	public static function staff_pages() {
		$p = array();
		if ( flxb_can_view() || ! is_user_logged_in() ) {
			return $p;
		}
		if ( FLXB_Refs::can_dashboard() ) {
			$p[] = array( self::SLUG, __( 'Donation dashboard', 'flx-donate' ), __( 'Dashboard', 'flx-donate' ), FLXB_Refs::CAP_DASH, array( 'FLXB_Page_Dashboard', 'render' ) );
		}
		if ( current_user_can( FLXB_Refs::CAP_OWN ) ) {
			$p[] = array( 'flxb-donations', __( 'My referrals', 'flx-donate' ), __( 'My referrals', 'flx-donate' ), FLXB_Refs::CAP_OWN, array( 'FLXB_Page_Donations', 'render' ) );
		}
		if ( FLXB_Refs::staff_create() ) {
			$p[] = array( 'flxb-add', __( 'New donation request', 'flx-donate' ), __( 'New request', 'flx-donate' ), FLXB_Refs::CAP_CREATE, array( 'FLXB_Page_Add', 'render' ) );
		}
		if ( FLXB_Refs::donors_own() ) {
			$p[] = array( 'flxb-donors', __( 'My donors', 'flx-donate' ), __( 'My donors', 'flx-donate' ), FLXB_Refs::CAP_DONORS, array( 'FLXB_Page_Donors', 'render' ) );
		}
		if ( flxb_track_enabled() && FLXB_Track::can() ) {
			$p[] = array( FLXB_Page_Track::SLUG, FLXB_Track::label( 'title' ), FLXB_Track::label( 'menu' ), 'read', array( 'FLXB_Page_Track', 'render' ) );
		}
		return $p;
	}

	public static function menu() {
		$cap = FLXB_CAP_VIEW;
		/* 2.2.0 / 2.3.0 (Flux Roles): staff get only the pages their capabilities open. */
		if ( ! flxb_can_view() ) {
			$pages = self::staff_pages();
			if ( ! $pages ) {
				return;
			}
			$first         = $pages[0];
			self::$hooks[] = add_menu_page( $first[1], __( 'Donations', 'flx-donate' ), $first[3], $first[0], $first[4], 'none', 27 );
			if ( count( $pages ) > 1 ) {
				foreach ( $pages as $pg ) {
					self::$hooks[] = add_submenu_page( $first[0], $pg[1], $pg[2], $pg[3], $pg[0], $pg[4] );
				}
			}
			return;
		}
		self::$hooks[] = add_menu_page( __( 'Donations', 'flx-donate' ), __( 'Donations', 'flx-donate' ), $cap, self::SLUG, array( 'FLXB_Page_Dashboard', 'render' ), 'none', 27 );
		self::$hooks[] = add_submenu_page( self::SLUG, __( 'Donation dashboard', 'flx-donate' ), __( 'Dashboard', 'flx-donate' ), $cap, self::SLUG, array( 'FLXB_Page_Dashboard', 'render' ) );
		self::$hooks[] = add_submenu_page( self::SLUG, __( 'Donations', 'flx-donate' ), __( 'Donations', 'flx-donate' ), $cap, 'flxb-donations', array( 'FLXB_Page_Donations', 'render' ) );
		if ( flxb_track_enabled() ) {
			self::$hooks[] = add_submenu_page( self::SLUG, FLXB_Track::label( 'title' ), FLXB_Track::label( 'menu' ), $cap, FLXB_Page_Track::SLUG, array( 'FLXB_Page_Track', 'render' ) );
		}
		self::$hooks[] = add_submenu_page( self::SLUG, __( 'Add donation', 'flx-donate' ), __( 'Add donation', 'flx-donate' ), (string) apply_filters( 'flxb_manage_capability', 'manage_options' ), 'flxb-add', array( 'FLXB_Page_Add', 'render' ) );
		self::$hooks[] = add_submenu_page( self::SLUG, __( 'Donors', 'flx-donate' ), __( 'Donors', 'flx-donate' ), $cap, 'flxb-donors', array( 'FLXB_Page_Donors', 'render' ) );
		self::$hooks[] = add_submenu_page( self::SLUG, __( 'Recurring donations', 'flx-donate' ), __( 'Recurring donations', 'flx-donate' ), $cap, 'flxb-subscriptions', array( 'FLXB_Page_Subscriptions', 'render' ) );
		self::$hooks[] = add_submenu_page( self::SLUG, __( 'Donation types', 'flx-donate' ), __( 'Donation types', 'flx-donate' ), $cap, 'flxb-types', array( 'FLXB_Page_Types', 'render' ) );
		if ( flxb_owns_payments() ) {
			self::$hooks[] = add_submenu_page( self::SLUG, __( 'Payments', 'flx-donate' ), __( 'Payments', 'flx-donate' ), 'manage_options', 'flxb-payments', array( 'FLXB_Page_Payments', 'render' ) );
		}
		if ( flxb_track_enabled() ) {
			self::$hooks[] = add_submenu_page( self::SLUG, __( 'Partners', 'flx-donate' ), __( 'Partners', 'flx-donate' ), $cap, FLXB_Page_Partners::SLUG, array( 'FLXB_Page_Partners', 'render' ) );
		}
		do_action( 'flxb_admin_menu', self::SLUG );
		if ( FLXB_Import::enabled() ) {
			self::$hooks[] = add_submenu_page( self::SLUG, __( 'Import from Excel', 'flx-donate' ), __( 'Import from Excel', 'flx-donate' ), (string) apply_filters( 'flxb_manage_capability', 'manage_options' ), FLXB_Page_Import::SLUG, array( 'FLXB_Page_Import', 'render' ) );
		}
		if ( FLXB_Page_Reports::enabled() ) {
			self::$hooks[] = add_submenu_page( self::SLUG, __( 'Reports', 'flx-donate' ), __( 'Reports', 'flx-donate' ), $cap, FLXB_Page_Reports::SLUG, array( 'FLXB_Page_Reports', 'render' ) );
		}
		self::$hooks[] = add_submenu_page( self::SLUG, __( 'Donation settings', 'flx-donate' ), __( 'Settings', 'flx-donate' ), 'manage_options', 'flxb-settings', array( 'FLXB_Page_Settings', 'render' ) );
	}

	/**
	 * 2.2.0: staff without the dashboard: WordPress refuses page=flxb-dashboard (Flux Roles's start screen
	 * links there), so send them to their first page.
	 */
	public static function own_home() {
		$page = isset( $_GET['page'] ) ? sanitize_key( wp_unslash( $_GET['page'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		if ( self::SLUG !== $page || flxb_can_view() || FLXB_Refs::can_dashboard() ) {
			return;
		}
		$pages = self::staff_pages();
		if ( $pages ) {
			wp_safe_redirect( FLXB_Admin_Url::page( $pages[0][0] ) );
			exit;
		}
	}

	/** Lets another module (payments) mark its page as ours for the shared assets. */
	public static function add_hook( $hook ) {
		if ( $hook ) {
			self::$hooks[] = $hook;
		}
	}

	private static function ver( $file ) {
		$p = FLXB_DIR . 'assets/' . $file;
		return is_readable( $p ) ? (string) filemtime( $p ) : FLXB_VERSION;
	}

	public static function assets( $hook ) {
		if ( ! in_array( $hook, self::$hooks, true ) ) {
			return;
		}
		wp_enqueue_style( 'flxb-admin', FLXB_URL . 'assets/admin.css', array(), self::ver( 'admin.css' ) );
		wp_add_inline_style( 'flxb-admin', flxb_brand_css( '.flxb' ) );
		$page = isset( $_GET['page'] ) ? sanitize_key( wp_unslash( $_GET['page'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		if ( 'flxb-types' === $page || 'flxb-settings' === $page ) {
			wp_enqueue_media();
		}
		if ( 'flxb-donors' === $page && ! empty( $_GET['donor'] ) && function_exists( 'flxcrm_person_card_enqueue' ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			flxcrm_person_card_enqueue(); // 2.12.0: donor page is the Flux CRM person card.
		}
		wp_enqueue_script( 'flxb-admin', FLXB_URL . 'assets/admin.js', array(), self::ver( 'admin.js' ), true );
		wp_localize_script(
			'flxb-admin',
			'flxbAdmin',
			array(
				'ajax'  => admin_url( 'admin-ajax.php' ),
				'nonce' => wp_create_nonce( 'flxb_stats' ),
				'i18n'  => array(
					'loading'      => __( 'Loading…', 'flx-donate' ),
					/* translators: %s: number of selected donations */
					'selected'      => __( '%s selected', 'flx-donate' ),
					/* translators: %s: number of donations */
					'confirmDelete' => __( 'Delete %s donations permanently? Their WooCommerce orders are deleted too. This cannot be undone.', 'flx-donate' ),
					/* translators: %s: number of donations */
					'confirmTrash'  => __( 'Move %s donations to the trash? Their WooCommerce orders go to the trash too.', 'flx-donate' ),
					'error'        => __( 'The data could not be loaded.', 'flx-donate' ),
					'retry'        => __( 'Try again', 'flx-donate' ),
					'total'        => __( 'Total donations', 'flx-donate' ),
					'donors'       => __( 'Donors', 'flx-donate' ),
					'newDonors'    => __( 'New donors', 'flx-donate' ),
					'average'      => __( 'Average gift', 'flx-donate' ),
					'mrr'          => __( 'Recurring (monthly)', 'flx-donate' ),
					'activeSubs'   => __( 'Active recurring donations', 'flx-donate' ),
					'gifts'        => __( 'gifts', 'flx-donate' ),
					'giftsCol'     => __( 'Gifts', 'flx-donate' ),
					'gift1'        => __( 'gift', 'flx-donate' ),
					'vsPrev'       => __( 'vs previous period', 'flx-donate' ),
					'noPrev'       => __( 'no gifts in the previous period', 'flx-donate' ),
					'allTime'      => __( 'all time: %s donors', 'flx-donate' ),
					'firstGift'    => __( 'first gift in this period', 'flx-donate' ),
					'perGift'      => __( 'per gift', 'flx-donate' ),
					'pastDue'      => __( '%s past due', 'flx-donate' ),
					'mrrHint'      => __( 'monthly value of active ones', 'flx-donate' ),
					'daily'        => __( 'Donations per day', 'flx-donate' ),
					'dailyHint'    => __( 'Received amount, one-time and recurring', 'flx-donate' ),
					'oneTime'      => __( 'One-time', 'flx-donate' ),
					'recurring'    => __( 'Recurring', 'flx-donate' ),
					'split'        => __( 'One-time vs recurring', 'flx-donate' ),
					'byType'       => __( 'By donation type', 'flx-donate' ),
					'byForm'       => __( 'By form', 'flx-donate' ),
					'topDonors'    => __( 'Top donors', 'flx-donate' ),
					'donor'        => __( 'Donor', 'flx-donate' ),
					'amount'       => __( 'Amount', 'flx-donate' ),
					'nothing'      => __( 'No donations in this period.', 'flx-donate' ),
					'emptyTitle'   => __( 'No donations yet', 'flx-donate' ),
					'emptyText'    => __( 'Donations appear here as soon as someone pays through a Flux Forms payment form. Payments taken before Flux Donate was installed can be imported on the Settings page.', 'flx-donate' ),
					'emptyBtn'     => __( 'Import existing payments', 'flx-donate' ),
					'total2'       => __( 'Total', 'flx-donate' ),
					'viewAll'      => __( 'View all', 'flx-donate' ),
					'customRange'  => __( 'Custom range', 'flx-donate' ),
					'copied'       => __( 'Copied.', 'flx-donate' ),
					'copyFail'     => __( 'Could not copy. The address is selected: press Ctrl+C (or long-press and Copy).', 'flx-donate' ),
				),
				'settingsUrl' => FLXB_Admin_Url::page( 'flxb-settings' ),
				'listUrl'     => flxb_can_view() ? FLXB_Admin_Url::page( 'flxb-donations' ) : '',
				'donorsUrl'   => flxb_can_view() ? FLXB_Admin_Url::page( 'flxb-donors' ) : '',
				'locale'      => str_replace( '_', '-', get_user_locale() ),
			)
		);
	}

	/* ------------------------------------------------------------------
	 * UI helpers
	 * ------------------------------------------------------------------ */

	/** Read-only value with a Copy button (falls back to select + execCommand; the field is selectable on phones). */
	public static function copy_field( $id, $label, $value ) {
		return '<div class="flxb-copy"><label class="flxb-label" for="' . esc_attr( $id ) . '">' . esc_html( $label ) . '</label>'
			. '<div class="flxb-copy__row"><textarea readonly rows="2" class="flxb-copy__input" id="' . esc_attr( $id ) . '" spellcheck="false" autocomplete="off">' . esc_textarea( $value ) . '</textarea>'
			. '<button type="button" class="flxb-btn flxb-copy__btn" data-flxb-copy="' . esc_attr( $id ) . '">' . self::icon( 'copy' ) . '<span>' . esc_html__( 'Copy', 'flx-donate' ) . '</span></button></div>'
			. '<p class="flxb-copy__ok" role="status" aria-live="polite"></p></div>';
	}

	public static function icon( $name, $size = 16 ) {
		$p = array(
			'heart'    => '<path d="M19 14c1.49-1.46 3-3.21 3-5.5A5.5 5.5 0 0 0 16.5 3c-1.76 0-3 .5-4.5 2-1.5-1.5-2.74-2-4.5-2A5.5 5.5 0 0 0 2 8.5c0 2.3 1.5 4.05 3 5.5l7 7Z"/>',
			'download' => '<path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><path d="m7 10 5 5 5-5"/><path d="M12 15V3"/>',
			'back'     => '<path d="m15 18-6-6 6-6"/>',
			'mail'     => '<rect x="2" y="4" width="20" height="16" rx="2"/><path d="m22 7-10 6L2 7"/>',
			'phone'    => '<path d="M22 16.9v3a2 2 0 0 1-2.2 2 19.8 19.8 0 0 1-8.6-3.1 19.5 19.5 0 0 1-6-6A19.8 19.8 0 0 1 2.1 4.2 2 2 0 0 1 4.1 2h3a2 2 0 0 1 2 1.7c.1 1 .4 1.9.7 2.8a2 2 0 0 1-.5 2.1L8 9.9a16 16 0 0 0 6 6l1.3-1.3a2 2 0 0 1 2.1-.4c.9.3 1.8.6 2.8.7a2 2 0 0 1 1.7 2z"/>',
			'plus'     => '<path d="M12 5v14M5 12h14"/>',
			'list'     => '<path d="M8 6h13M8 12h13M8 18h13M3 6h.01M3 12h.01M3 18h.01"/>',
			'shield'   => '<path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/>',
			'external' => '<path d="M15 3h6v6"/><path d="M10 14 21 3"/><path d="M18 13v6a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2h6"/>',
			'edit'     => '<path d="M12 20h9"/><path d="M16.5 3.5a2.1 2.1 0 0 1 3 3L7 19l-4 1 1-4Z"/>',
			'print'    => '<path d="M6 9V2h12v7"/><path d="M6 18H4a2 2 0 0 1-2-2v-5a2 2 0 0 1 2-2h16a2 2 0 0 1 2 2v5a2 2 0 0 1-2 2h-2"/><path d="M6 14h12v8H6z"/>',
			'repeat'   => '<path d="m17 2 4 4-4 4"/><path d="M3 11v-1a4 4 0 0 1 4-4h14"/><path d="m7 22-4-4 4-4"/><path d="M21 13v1a4 4 0 0 1-4 4H3"/>',
			'flag'     => '<path d="M4 15s1-1 4-1 5 2 8 2 4-1 4-1V3s-1 1-4 1-5-2-8-2-4 1-4 1z"/><path d="M4 22v-7"/>',
			'card'     => '<rect x="2" y="5" width="20" height="14" rx="2"/><path d="M2 10h20"/>',
			'cert'     => '<circle cx="12" cy="8" r="6"/><path d="M15.5 13 17 22l-5-3-5 3 1.5-9"/>',
			'users'    => '<path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M22 21v-2a4 4 0 0 0-3-3.9M16 3.1a4 4 0 0 1 0 7.8"/>',
			'alert'    => '<path d="M12 9v4M12 17h.01"/><path d="M10.3 3.9 1.8 18a2 2 0 0 0 1.7 3h17a2 2 0 0 0 1.7-3L13.7 3.9a2 2 0 0 0-3.4 0z"/>',
			'file'     => '<path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><path d="M14 2v6h6"/>',
			'search'   => '<circle cx="11" cy="11" r="8"/><path d="m21 21-4.3-4.3"/>',
			'copy'     => '<rect x="9" y="9" width="13" height="13" rx="2"/><path d="M5 15H4a2 2 0 0 1-2-2V4a2 2 0 0 1 2-2h9a2 2 0 0 1 2 2v1"/>',
			'hook'     => '<path d="M18 16.98h-5.99c-1.1 0-1.95.94-2.48 1.9A4 4 0 0 1 2 17c.01-.7.2-1.4.57-2"/><path d="m6 17 3.13-5.78c.53-.97.1-2.18-.5-3.1a4 4 0 1 1 6.89-4.06"/><path d="m12 6 3.13 5.73C15.66 12.7 16.9 13 18 13a4 4 0 0 1 0 8"/>',
		);
		return '<svg class="flxb-ico" viewBox="0 0 24 24" width="' . (int) $size . '" height="' . (int) $size . '" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false">' . ( $p[ $name ] ?? '' ) . '</svg>';
	}

	/**
	 * Page header.
	 *
	 * @param array $actions array( array( 'label', 'url', 'primary' => bool, 'icon' => '' ) )
	 */
	public static function header( $title, $sub = '', array $actions = array(), $back = null ) {
		echo '<div class="wrap flxb">';
		if ( $back ) {
			echo '<a class="flxb-back" href="' . esc_url( $back[1] ) . '">' . self::icon( 'back', 14 ) . esc_html( $back[0] ) . '</a>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		}
		echo '<div class="flxb-head"><div><h1 class="flxb-title">' . esc_html( $title ) . '</h1>';
		if ( '' !== $sub ) {
			echo '<p class="flxb-sub">' . esc_html( $sub ) . '</p>';
		}
		echo '</div>';
		if ( $actions ) {
			echo '<div class="flxb-head__actions">';
			foreach ( $actions as $a ) {
				echo '<a class="flxb-btn' . ( ! empty( $a['primary'] ) ? ' flxb-btn--primary' : '' ) . '" href="' . esc_url( $a['url'] ) . '">' . ( ! empty( $a['icon'] ) ? self::icon( $a['icon'] ) : '' ) . esc_html( $a['label'] ) . '</a>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
			}
			echo '</div>';
		}
		echo '</div><hr class="wp-header-end">';
		self::notice();
		self::provider_notice();
	}

	/** Every Bağışlar page says so when online payments cannot be taken (no provider set up). */
	private static function provider_notice() {
		if ( ! function_exists( 'flxf_payment_active_provider_id' ) || ! function_exists( 'flxf_payment_provider' ) ) {
			return;
		}
		$p = flxf_payment_provider( flxf_payment_active_provider_id() );
		if ( $p && $p->is_configured() ) {
			return;
		}
		$link = current_user_can( 'manage_options' ) ? ' <a href="' . esc_url( FLXB_Admin_Url::page( 'flxb-payments' ) ) . '">' . esc_html__( 'Set up a payment provider', 'flx-donate' ) . '</a>' : '';
		echo '<div class="flxb-notice flxb-notice--warn" role="status">' . esc_html__( 'No payment provider is set up, so forms cannot take online payments. Visitors see "Online payment is not available at the moment."', 'flx-donate' ) . $link . '</div>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
	}

	public static function footer() {
		echo '</div>';
	}

	/** Redirect notice for pages that print their own frame (2.12.0: donor page on the Flux CRM card). */
	public static function notices() {
		self::notice();
	}

	/** One-shot notice after a redirect (?flxb_msg=). */
	private static function notice() {
		$msg = isset( $_GET['flxb_msg'] ) ? sanitize_key( wp_unslash( $_GET['flxb_msg'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification
		if ( '' === $msg ) {
			return;
		}
		$map = array(
			'saved'    => array( 'ok', __( 'Saved.', 'flx-donate' ) ),
			'deleted'  => array( 'ok', __( 'Deleted.', 'flx-donate' ) ),
			'refunded' => array( 'ok', __( 'The refund was sent to the payment provider.', 'flx-donate' ) ),
			'canceled' => array( 'ok', __( 'The recurring donation was canceled at the payment provider.', 'flx-donate' ) ),
			'voided'   => array( 'ok', __( 'The payment was canceled at the payment provider.', 'flx-donate' ) ),
			'imported' => array( 'ok', '' ),
			'error'    => array( 'error', __( 'Something went wrong. Please check the form and try again.', 'flx-donate' ) ),
			'warn'     => array( 'warn', __( 'Saved.', 'flx-donate' ) ),
			'approved' => array( 'ok', __( 'Payment approved. The certificate is made and sent to the donor.', 'flx-donate' ) ),
			'rejected' => array( 'warn', __( 'Payment rejected.', 'flx-donate' ) ),
		);
		if ( 'imported' === $msg ) {
			$last = get_option( 'flxb_last_sync', array() );
			$r    = is_array( $last['result'] ?? null ) ? $last['result'] : array();
			/* translators: 1: payments checked, 2: newly imported, 3: already known */
			$map['imported'][1] = sprintf( __( 'Import finished: %1$s payments checked, %2$s imported, %3$s already known.', 'flx-donate' ), number_format_i18n( (int) ( $r['seen'] ?? 0 ) ), number_format_i18n( (int) ( $r['imported'] ?? 0 ) ), number_format_i18n( (int) ( $r['existing'] ?? 0 ) ) );
		}
		$err = isset( $_GET['flxb_err'] ) ? sanitize_text_field( wp_unslash( $_GET['flxb_err'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification
		if ( isset( $map[ $msg ] ) ) {
			echo '<div class="flxb-notice flxb-notice--' . esc_attr( $map[ $msg ][0] ) . '" role="status">' . esc_html( '' !== $err ? $err : $map[ $msg ][1] ) . '</div>';
		}
	}

	public static function pill( $status ) {
		$s = flxb_status_label( $status );
		return '<span class="flxb-pill flxb-pill--' . esc_attr( $s[0] ) . '">' . esc_html( $s[1] ) . '</span>';
	}

	public static function type_pill( $type ) {
		return 'recurring' === $type
			? '<span class="flxb-pill flxb-pill--recurring">' . self::icon( 'repeat', 12 ) . esc_html( flxb_type_label( $type ) ) . '</span>'
			: '<span class="flxb-pill">' . esc_html( flxb_type_label( $type ) ) . '</span>';
	}

	public static function donor_name( array $r, $prefix = 'donor_' ) {
		$n = (string) ( $r[ $prefix . 'name' ] ?? '' );
		if ( '' !== $n ) {
			return $n;
		}
		if ( '' !== (string) ( $r[ $prefix . 'email' ] ?? '' ) ) {
			return (string) $r[ $prefix . 'email' ];
		}
		if ( '' !== (string) ( $r[ $prefix . 'phone' ] ?? '' ) ) {
			return flxb_format_phone( $r[ $prefix . 'phone' ] );
		}
		return __( 'Anonymous', 'flx-donate' );
	}

	public static function pagination( $total, $per_page, $current, array $args ) {
		$pages = (int) ceil( $total / max( 1, $per_page ) );
		if ( $pages <= 1 ) {
			return '';
		}
		$out  = '<nav class="flxb-pages" aria-label="' . esc_attr__( 'Pages', 'flx-donate' ) . '">';
		$show = array_unique( array_filter( array( 1, $current - 1, $current, $current + 1, $pages ), function ( $p ) use ( $pages ) {
			return $p >= 1 && $p <= $pages;
		} ) );
		sort( $show );
		$prev = 0;
		foreach ( $show as $p ) {
			if ( $prev && $p > $prev + 1 ) {
				$out .= '<span class="is-gap">…</span>';
			}
			$out .= $p === $current
				? '<span class="is-current" aria-current="page">' . esc_html( number_format_i18n( $p ) ) . '</span>'
				: '<a href="' . esc_url( add_query_arg( array_merge( $args, array( 'paged' => $p ) ), admin_url( 'admin.php' ) ) ) . '">' . esc_html( number_format_i18n( $p ) ) . '</a>';
			$prev = $p;
		}
		return $out . '</nav>';
	}

	/** 2.6.0: shared list library of Flux Admin (filter bar, per-page, pagination); old markup when it is missing. */
	public static function list_lib() {
		return defined( 'FLXA_LIST_API' ) && function_exists( 'flxa_list_form_attrs' ) && function_exists( 'flxa_list_pagination' ) && function_exists( 'flxa_list_per_page' );
	}

	/** Attributes for a filter form handled by the shared list library ('' without it). */
	public static function list_attrs( $key, $primary = 2 ) {
		return self::list_lib() ? flxa_list_form_attrs( array( 'key' => $key, 'primary' => $primary ) ) : '';
	}

	/** Rows per page: the user's choice from the shared list library, else the plugin default. */
	public static function per_page( $key, $fallback ) {
		return self::list_lib() ? max( 1, (int) flxa_list_per_page( $key, 20 ) ) : (int) $fallback;
	}

	/** Pagination with a rows-per-page picker when the shared list library is there. */
	public static function list_pagination( $total, $per_page, $current, array $args ) {
		if ( self::list_lib() ) {
			return (string) flxa_list_pagination( $total, $per_page, $current, array( 'echo' => false ) );
		}
		return self::pagination( $total, $per_page, $current, $args );
	}

	public static function redirect( $page, array $args = array() ) {
		if ( isset( $args['flxb_err'] ) ) {
			$args['flxb_err'] = rawurlencode( (string) $args['flxb_err'] ); // keep apostrophes and other characters
		}
		wp_safe_redirect( FLXB_Admin_Url::page( $page, $args ) );
		exit;
	}

	/** Request value from $_GET (sanitised text). */
	public static function q( $key, $default = '' ) {
		return isset( $_GET[ $key ] ) ? sanitize_text_field( wp_unslash( $_GET[ $key ] ) ) : $default; // phpcs:ignore WordPress.Security.NonceVerification
	}

	public static function deny() {
		wp_die( esc_html__( 'Sorry, you are not allowed to access this page.', 'flx-donate' ), 403 );
	}
}
