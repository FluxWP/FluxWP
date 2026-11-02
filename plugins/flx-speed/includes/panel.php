<?php
/**
 * Alt panel: yönetici çubuğunda özet + sayfanın altında açılan geliştirici paneli.
 * Aynı JS, Kayıtlar ekranında kaydedilmiş bir isteği göstermek için de kullanılır.
 *
 * @package flx-speed
 */

defined( 'ABSPATH' ) || exit;

class FLXP_Panel {

	public static function init() {
		add_action( 'admin_bar_menu', array( __CLASS__, 'admin_bar' ), 999 );
	}

	/** Yönetici çubuğu düğümü; sayılar JS ile doldurulur. */
	public static function admin_bar( $bar ) {
		if ( ! FLXP_Collector::$panel ) {
			return;
		}
		$bar->add_node(
			array(
				'id'    => 'flxp',
				'title' => '<span class="ab-icon" aria-hidden="true"></span><span class="ab-label flxp-ab-label">' . esc_html__( 'Performans', 'flx-speed' ) . '</span>',
				'href'  => '#flxp-panel',
				'meta'  => array( 'class' => 'flxp-ab' ),
			)
		);
	}

	/** HTML sayfası mı? (Ajax, REST, feed, JSON çıktısı değil.) */
	private static function is_html_request() {
		if ( wp_doing_ajax() || wp_doing_cron() || ( defined( 'REST_REQUEST' ) && REST_REQUEST ) || ( defined( 'XMLRPC_REQUEST' ) && XMLRPC_REQUEST ) || ( defined( 'IFRAME_REQUEST' ) && IFRAME_REQUEST ) ) {
			return false;
		}
		if ( function_exists( 'is_feed' ) && did_action( 'wp' ) && ( is_feed() || is_robots() || is_trackback() || is_embed() ) ) {
			return false;
		}
		if ( ! did_action( 'wp_footer' ) && ! did_action( 'admin_footer' ) ) {
			return false;
		}
		foreach ( headers_list() as $h ) {
			if ( 0 === stripos( $h, 'content-type:' ) && false === stripos( $h, 'text/html' ) ) {
				return false;
			}
		}
		return true;
	}

	/** Shutdown'da paneli basar. */
	public static function maybe_output( $snap ) {
		// Kayıt ayrıntısı ekranı kendi panelini gömer.
		if ( ! self::is_html_request() || ( is_admin() && isset( $_GET['page'], $_GET['id'] ) && 'flx-speed' === $_GET['page'] ) ) { // phpcs:ignore
			return;
		}
		$cfg = array(
			'mode'     => 'bar',
			'slowQ'    => (int) flxp_settings( 'slow_query_ms' ),
			'slowMs'   => (int) flxp_settings( 'slow_ms' ),
			'adminUrl' => admin_url( 'admin.php?page=flx-speed' ),
			'labels'   => self::component_labels( $snap ),
		);
		self::print_assets();
		echo '<div id="flxp-panel" class="flxp" hidden></div>';
		self::print_data( $snap, $cfg );
	}

	/** CSS + JS etiketleri (shutdown'da enqueue mümkün değil). */
	public static function print_assets() {
		$v = FLXP_VERSION;
		echo '<link rel="stylesheet" id="flxp-css" href="' . esc_url( FLXP_URL . 'assets/panel.css?ver=' . $v ) . '" media="all">'; // phpcs:ignore
		echo '<script src="' . esc_url( FLXP_URL . 'assets/panel.js?ver=' . $v ) . '" defer></script>'; // phpcs:ignore
	}

	/** Veri + çeviri + ayar JSON'u. */
	public static function print_data( $snap, $cfg ) {
		$flags = JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_PARTIAL_OUTPUT_ON_ERROR;
		echo '<script type="application/json" id="flxp-data">' . wp_json_encode( $snap, $flags ) . '</script>'; // phpcs:ignore
		echo '<script type="application/json" id="flxp-cfg">' . wp_json_encode( $cfg, $flags ) . '</script>'; // phpcs:ignore
		echo '<script type="application/json" id="flxp-i18n">' . wp_json_encode( flxp_js_strings(), $flags ) . '</script>'; // phpcs:ignore
	}

	/** Verideki bileşenlerin okunur adları: "plugin:flx-donate" => "Eklenti: flx-donate". */
	public static function component_labels( $snap ) {
		$out  = array();
		$walk = function ( $list ) use ( &$out ) {
			foreach ( (array) $list as $it ) {
				if ( isset( $it['comp'] ) && is_array( $it['comp'] ) ) {
					$out[ flxp_component_key( $it['comp'] ) ] = flxp_component_label( $it['comp'] );
				}
			}
		};
		$walk( $snap['db']['queries'] ?? array() );
		$walk( $snap['db']['by_component'] ?? array() );
		foreach ( array( 'php', 'wrong', 'http', 'caps', 'transients', 'logs' ) as $k ) {
			$walk( $snap[ $k ] ?? array() );
		}
		$walk( $snap['assets']['scripts'] ?? array() );
		$walk( $snap['assets']['styles'] ?? array() );
		return $out;
	}
}
FLXP_Panel::init();
