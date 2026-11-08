<?php
/**
 * Güncellemeler panelinde sayfadan çıkmadan kurma / güncelleme / etkinleştirme.
 *
 * Panel önce `flxu_plan` ile seçimi gerekli eklentilerle tamamlanmış, sıralı adımlara çevirir,
 * sonra her adımı ayrı bir `flxu_step` isteğiyle çalıştırır (Hostinger'ın istek süresi sınırına takılmaz).
 * JavaScript kapalıysa aynı adımlar FLXU_Admin'de tek istekte sırayla çalışır (run_step ortak).
 */

defined( 'ABSPATH' ) || exit;

final class FLXU_Ajax {

	const NONCE = 'flxu_ajax';

	public static function init(): void {
		add_action( 'wp_ajax_flxu_plan', array( __CLASS__, 'plan' ) );
		add_action( 'wp_ajax_flxu_step', array( __CLASS__, 'step' ) );
		add_action( 'wp_ajax_flxu_check', array( __CLASS__, 'check' ) );
	}

	/** "Güncellemeleri kontrol et": sunucuya sorar, sayfa yenilenmeden tablo tazelenir. */
	public static function check(): void {
		self::guard();
		FLXU_Updater::instance()->check_now();
		$status  = FLXU_Updater::status();
		$pending = FLXU_Admin::pending_count();
		if ( empty( $status['ok'] ) ) {
			$summary = __( 'Could not connect to the server.', 'flx-setup' );
		} elseif ( $pending ) {
			/* translators: %d: number of updates */
			$summary = sprintf( _n( '%d update available', '%d updates available', $pending, 'flx-setup' ), $pending );
		} else {
			$summary = __( 'All up to date ✓', 'flx-setup' );
		}
		wp_send_json_success(
			array(
				'ok'      => ! empty( $status['ok'] ),
				'pending' => $pending,
				'label'   => FLXU_Admin::checked_label( $status ),
				'summary' => $summary,
			)
		);
	}

	private static function guard(): void {
		check_ajax_referer( self::NONCE, 'nonce' );
		if ( ! current_user_can( 'update_plugins' ) && ! current_user_can( 'install_plugins' ) ) {
			wp_send_json_error( array( 'message' => __( 'You are not allowed to do this.', 'flx-setup' ) ), 403 );
		}
	}

	public static function plan(): void {
		self::guard();
		$slugs    = array_map( 'sanitize_key', (array) wp_unslash( $_POST['slugs'] ?? array() ) );
		$activate = ! empty( $_POST['activate'] );
		$catalog  = FLXU_Updater::instance()->catalog();
		$rows     = FLXU_Admin::rows( $catalog );
		if ( ! empty( $_POST['all'] ) ) {
			$slugs = wp_list_pluck(
				array_filter(
					$rows,
					static function ( $r ) {
						return 'update' === $r['state'];
					}
				),
				'slug'
			);
		}
		$plan = FLXU_Installer::plan( $slugs, $catalog, $rows );
		wp_send_json_success(
			array(
				'steps' => FLXU_Installer::steps( $plan, $catalog, $activate ),
			)
		);
	}

	public static function step(): void {
		self::guard();
		@set_time_limit( 300 ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged
		$kind    = sanitize_key( wp_unslash( $_POST['kind'] ?? '' ) );
		$slug    = sanitize_key( wp_unslash( $_POST['slug'] ?? '' ) );
		$version = sanitize_text_field( wp_unslash( $_POST['version'] ?? '' ) );

		// Kurulumun/güncellemenin yazdırdığı her şey JSON'u bozmasın.
		ob_start();
		$res = self::run_step( $kind, $slug, $version );
		ob_end_clean();

		$catalog = FLXU_Updater::instance()->catalog();
		$row     = null;
		foreach ( FLXU_Admin::rows( $catalog ) as $r ) {
			if ( $r['slug'] === $slug ) {
				$row = $r;
			}
		}
		$res['row']     = $row;
		$res['pending'] = FLXU_Admin::pending_count();
		wp_send_json_success( $res );
	}

	/**
	 * Tek adım: install | wporg | activate | update | version.
	 *
	 * @return array {ok, lines: [{ok, text}]}
	 */
	public static function run_step( string $kind, string $slug, string $version = '' ): array {
		$catalog = FLXU_Updater::instance()->catalog();
		$p       = $catalog[ $slug ] ?? null;

		if ( in_array( $kind, array( 'install', 'wporg', 'activate' ), true ) ) {
			$cap = 'activate' === $kind ? 'activate_plugins' : ( $p && 'theme' === $p['type'] ? 'install_themes' : 'install_plugins' );
			if ( ! current_user_can( $cap ) ) {
				return self::fail( __( 'You are not allowed to do this.', 'flx-setup' ) );
			}
			return ( new FLXU_Installer() )->step( $kind, $slug, $catalog, FLXU_Installer::wporg_allowed( $catalog ) );
		}

		if ( ! $p ) {
			return self::fail( __( 'Package not found.', 'flx-setup' ) );
		}
		if ( ! current_user_can( 'theme' === $p['type'] ? 'update_themes' : 'update_plugins' ) ) {
			return self::fail( __( 'You are not allowed to do this.', 'flx-setup' ) );
		}

		require_once ABSPATH . 'wp-admin/includes/class-wp-upgrader.php';
		require_once ABSPATH . 'wp-admin/includes/file.php';
		require_once ABSPATH . 'wp-admin/includes/misc.php';
		$guard  = FLXU_Guard::instance();
		$before = $guard->logged;
		$skin   = new WP_Ajax_Upgrader_Skin();
		$lines  = array();

		if ( 'version' === $kind ) {
			$res = $guard->change_version( $slug, $version, $skin );
			if ( is_wp_error( $res ) ) {
				$lines[] = array( 'ok' => false, 'text' => $p['name'] . ': ' . $res->get_error_message() );
			}
		} elseif ( 'update' === $kind ) {
			if ( 'theme' === $p['type'] ) {
				self::ensure_update_data( 'update_themes' );
				$upgrader = new Theme_Upgrader( $skin );
				$res      = $upgrader->bulk_upgrade( array( $slug ) );
			} else {
				$files = FLXU_Updater::plugin_files();
				if ( ! isset( $files[ $slug ] ) ) {
					return self::fail( __( 'The package is not installed.', 'flx-setup' ) );
				}
				self::ensure_update_data( 'update_plugins' );
				$upgrader = new Plugin_Upgrader( $skin );
				$res      = $upgrader->bulk_upgrade( array( $files[ $slug ] ) );
			}
			if ( is_array( $res ) && ! array_filter( $res ) && $guard->logged === $before ) {
				/* translators: %s: package */
				$lines[] = array( 'ok' => false, 'text' => sprintf( __( '%s was not updated: WordPress sees no update for it. Click "Check for updates" and try again.', 'flx-setup' ), $p['name'] ) );
			}
		} else {
			return self::fail( __( 'Unknown action.', 'flx-setup' ) );
		}

		foreach ( $skin->get_errors()->get_error_messages() as $m ) {
			$lines[] = array( 'ok' => false, 'text' => $p['name'] . ': ' . wp_strip_all_tags( $m ) );
		}
		// FLXU_Guard'ın bu istekte yazdığı geçmiş satırları (yedek, site kontrolü, geri alma).
		$new = array_reverse( array_slice( FLXU_Guard::history(), 0, $guard->logged - $before ) );
		foreach ( $new as $e ) {
			$lines[] = array(
				'ok'   => 'ok' === $e['result'],
				'text' => FLXU_Admin::entry_text( $e ),
			);
		}
		$ok = $lines && ! array_filter(
			$lines,
			static function ( $l ) {
				return ! $l['ok'];
			}
		);
		return array(
			'ok'    => $ok,
			'lines' => $lines,
		);
	}

	/**
	 * Önceki adım güncelleme verisini sildiyse (WordPress güncellemeden sonra siler) boş bir kayıt yaz:
	 * FluxWP paketleri pre_set filtresiyle eklenir; wordpress.org'a istek atılmaz.
	 */
	private static function ensure_update_data( string $transient ): void {
		if ( is_object( get_site_transient( $transient ) ) ) {
			return;
		}
		set_site_transient(
			$transient,
			(object) array(
				'last_checked' => 0,
				'checked'      => array(),
				'response'     => array(),
				'no_update'    => array(),
				'translations' => array(),
			)
		);
	}

	private static function fail( string $text ): array {
		return array(
			'ok'    => false,
			'lines' => array(
				array(
					'ok'   => false,
					'text' => $text,
				),
			),
		);
	}
}
