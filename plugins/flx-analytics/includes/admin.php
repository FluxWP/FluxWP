<?php
/**
 * Yönetim: İstatistikler, Yolculuklar ve Ayarlar menüsü (sayfalar React ekranı, includes/flux-ui.php) ve
 * CSV dışa aktarma (ekranların exportUrl adresi). Klasik PHP sayfası yoktur.
 *
 * @package flx-analytics
 */

defined( 'ABSPATH' ) || exit;

class FLXAN_Admin {

	const SLUG = 'flx-analytics';

	public static function init() {
		add_action( 'admin_menu', array( __CLASS__, 'menu' ) );
		add_action( 'admin_head', array( __CLASS__, 'menu_icon' ) );
		add_action( 'admin_post_flxan_export', array( __CLASS__, 'export' ) );
	}

	public static function menu() {
		$cap = current_user_can( 'manage_options' ) ? 'manage_options' : 'flxan_view';
		add_menu_page( __( 'Site Analytics', 'flx-analytics' ), __( 'Site Analytics', 'flx-analytics' ), $cap, self::SLUG, array( __CLASS__, 'page' ), 'none', 3 );
		add_submenu_page( self::SLUG, __( 'Statistics', 'flx-analytics' ), __( 'Statistics', 'flx-analytics' ), $cap, self::SLUG, array( __CLASS__, 'page' ) );
		add_submenu_page( self::SLUG, __( 'Journeys', 'flx-analytics' ), __( 'Journeys', 'flx-analytics' ), $cap, self::SLUG . '-journeys', array( __CLASS__, 'journeys_page' ) );
		add_submenu_page( self::SLUG, __( 'Site Analytics settings', 'flx-analytics' ), __( 'Settings', 'flx-analytics' ), 'manage_options', self::SLUG . '-settings', array( __CLASS__, 'settings_page' ) );
	}

	/** Renkli menü logosu (WP data-URI SVG'yi tek renge boyar). */
	public static function menu_icon() {
		echo '<style>#toplevel_page_flx-analytics .wp-menu-image{background:url(' . esc_url( FLXAN_URL . 'assets/icon.svg' ) . ') center/20px 20px no-repeat}</style>';
	}

	/** İstatistikler (React ekranı, Flux GraphQL ekran kabuğu). */
	public static function page() {
		if ( ! flxan_can_view() ) {
			wp_die( esc_html__( 'You are not allowed to view this page.', 'flx-analytics' ) );
		}
		flx_admin_screen_page();
	}

	/** Yolculuklar (React ekranı). */
	public static function journeys_page() {
		self::page();
	}

	/** Ayarlar (React ekranı). */
	public static function settings_page() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You are not allowed to view this page.', 'flx-analytics' ) );
		}
		flx_admin_screen_page();
	}

	/** CSV: type = site | pages | referrers | events | dim (kind). */
	public static function export() {
		if ( ! flxan_can_view() ) {
			wp_die( esc_html__( 'You are not allowed to do this.', 'flx-analytics' ) );
		}
		check_admin_referer( 'flxan_export' );
		$g    = FLXAN_Stats::range( sanitize_key( $_GET['range'] ?? '30d' ), sanitize_text_field( wp_unslash( $_GET['start'] ?? '' ) ), sanitize_text_field( wp_unslash( $_GET['end'] ?? '' ) ) ); // phpcs:ignore
		$type = sanitize_key( $_GET['type'] ?? 'site' ); // phpcs:ignore
		$kind = sanitize_key( $_GET['kind'] ?? '' ); // phpcs:ignore
		$rows = array();
		switch ( $type ) {
			case 'pages':
				$rows[] = array( __( 'Address', 'flx-analytics' ), __( 'Title', 'flx-analytics' ), __( 'Visitors', 'flx-analytics' ), __( 'Views', 'flx-analytics' ) );
				foreach ( FLXAN_Stats::pages( $g['start'], $g['end'], 100000 )['items'] as $r ) {
					$rows[] = array( $r['path'], $r['title'], $r['visitors'], $r['pageviews'] );
				}
				break;
			case 'referrers':
				$rows[] = array( __( 'Site', 'flx-analytics' ), __( 'Channel', 'flx-analytics' ), __( 'Visitors', 'flx-analytics' ), __( 'Views', 'flx-analytics' ) );
				foreach ( FLXAN_Stats::referrers( $g['start'], $g['end'], 100000 )['items'] as $r ) {
					$rows[] = array( $r['host'], flxan_dim_label( 'channel', $r['channel'] ), $r['visitors'], $r['pageviews'] );
				}
				break;
			case 'events':
				$rows[] = array( __( 'Event', 'flx-analytics' ), __( 'Detail', 'flx-analytics' ), __( 'Count', 'flx-analytics' ), __( 'Unique', 'flx-analytics' ), __( 'Amount', 'flx-analytics' ) );
				foreach ( FLXAN_Stats::events( $g['start'], $g['end'], '', 100000 )['items'] as $r ) {
					$rows[] = array( $r['label'], $r['param'], $r['hits'], $r['uniques'], $r['amount'] );
				}
				break;
			case 'dim':
				$rows[] = array( FLXAN_Stats::kinds()[ $kind ] ?? $kind, __( 'Visitors', 'flx-analytics' ), __( 'Views', 'flx-analytics' ) );
				foreach ( FLXAN_Stats::dim( $kind, $g['start'], $g['end'], 100000 )['items'] as $r ) {
					$rows[] = array( $r['label'], $r['visitors'], $r['pageviews'] );
				}
				break;
			default:
				$type   = 'site';
				$rows[] = array( __( 'Date', 'flx-analytics' ), __( 'Visitors', 'flx-analytics' ), __( 'Views', 'flx-analytics' ) );
				foreach ( FLXAN_Stats::series( $g['start'], $g['end'], 'day' )['items'] as $r ) {
					$rows[] = array( $r['d'], $r['visitors'], $r['pageviews'] );
				}
		}
		flx_csv_stream(
			'flx-analytics-' . $type . '-' . $g['start'] . '_' . $g['end'] . '.csv',
			(array) array_shift( $rows ),
			$rows,
			array( 'delimiter' => ';' )
		);
		exit;
	}
}
FLXAN_Admin::init();
