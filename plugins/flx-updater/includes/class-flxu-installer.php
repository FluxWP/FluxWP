<?php
/**
 * Seçilen paketleri gerekli eklentileriyle birlikte kurar / günceller / etkinleştirir.
 *
 * - Gerekli eklentiler paketin (eklenti ya da tema style.css) "Requires Plugins" başlığından gelir
 *   (sunucu `requires_plugins` bildirir; kurulu pakette kendi başlığı da okunur). Tema etkinleştirilmez,
 *   yalnız gerekli eklentileri kurulup etkinleştirilir.
 * - Eksik gerekli eklenti FluxWP paketiyse güncelleme sunucusundan, değilse wordpress.org'dan kurulur.
 * - Sıra: önce gerekli eklentiler kurulup etkinleştirilir, sonra paketler; güncellemeler en sonda
 *   WordPress'in toplu güncelleyicisiyle (Flux Backup yedeği + site kontrolü FLXU_Guard'da).
 * - Gerekli eklentisi etkin olmayan paket etkinleştirilmez; neden etkinleştirilmediği panelde yazar
 *   (WordPress'in "Hata: … gereklidir" sayfası çıkmaz).
 */

defined( 'ABSPATH' ) || exit;

final class FLXU_Installer {

	/** Bilinen wordpress.org eklentilerinin görünen adları (kurulu değilken ad bilinmez). */
	const KNOWN_NAMES = array(
		'woocommerce'    => 'WooCommerce',
		'contact-form-7' => 'Contact Form 7',
		'elementor'      => 'Elementor',
	);

	/** @var array Sonuç satırları: {ok, text} */
	private $results = array();

	/* ------------------------------------------------------------------ */
	/* Bağımlılık bilgisi                                                  */
	/* ------------------------------------------------------------------ */

	/** Kurulu eklentiler: slug (klasör) => {file, name, active}. */
	public static function installed_plugins(): array {
		if ( ! function_exists( 'get_plugins' ) ) {
			require_once ABSPATH . 'wp-admin/includes/plugin.php';
		}
		$out = array();
		foreach ( get_plugins() as $file => $data ) {
			$slug = dirname( $file );
			if ( '.' === $slug ) {
				$slug = basename( $file, '.php' );
			}
			if ( isset( $out[ $slug ] ) ) {
				continue;
			}
			$out[ $slug ] = array(
				'file'   => $file,
				'name'   => (string) $data['Name'],
				'active' => is_plugin_active( $file ),
			);
		}
		return $out;
	}

	public static function plugin_name( string $slug, array $catalog, array $installed ): string {
		if ( isset( $installed[ $slug ] ) ) {
			return $installed[ $slug ]['name'];
		}
		if ( isset( $catalog[ $slug ] ) ) {
			return $catalog[ $slug ]['name'];
		}
		return self::KNOWN_NAMES[ $slug ] ?? ucwords( str_replace( '-', ' ', $slug ) );
	}

	/** Panelin betiği için: paketler (durum + gerekli eklentiler) ve ilgili eklentilerin durumu. */
	public static function client_data( array $catalog, array $rows ): array {
		$installed = self::installed_plugins();
		$pk        = array();
		$need      = array();
		foreach ( $rows as $row ) {
			$req                 = FLXU_Updater::requires_plugins( $row['slug'], $catalog[ $row['slug'] ] ?? array() );
			$pk[ $row['slug'] ] = array(
				'name'  => $row['name'],
				'state' => $row['state'],
				'req'   => $req,
			);
			$need = array_merge( $need, $req );
		}
		$pl = array();
		foreach ( array_unique( $need ) as $slug ) {
			$pl[ $slug ] = array(
				'name'      => self::plugin_name( $slug, $catalog, $installed ),
				'installed' => isset( $installed[ $slug ] ),
				'active'    => ! empty( $installed[ $slug ]['active'] ),
				'flx'        => isset( $catalog[ $slug ] ),
			);
		}
		return array(
			'pk' => $pk,
			'pl' => $pl,
		);
	}

	/* ------------------------------------------------------------------ */
	/* Plan                                                                */
	/* ------------------------------------------------------------------ */

	/**
	 * Seçimi gerekli eklentilerle tamamlar ve sıralar.
	 *
	 * @param string[] $slugs  Seçilen katalog paketleri (kurulacak ya da güncellenecek).
	 * @return array install (sıralı katalog slug'ları), update, deps (sıralı, katalog dışı ya da kurulu-etkin değil), added (otomatik eklenenler: slug => isteyen paket adı)
	 */
	public static function plan( array $slugs, array $catalog, array $rows ): array {
		$state = array();
		foreach ( $rows as $r ) {
			$state[ $r['slug'] ] = $r['state'];
		}
		$installed = self::installed_plugins();
		$selected  = array();
		foreach ( $slugs as $s ) {
			if ( isset( $state[ $s ] ) && in_array( $state[ $s ], array( 'missing', 'update' ), true ) ) {
				$selected[ $s ] = true;
			}
		}

		$order = array();   // Topolojik sıra: önce gerekli olanlar.
		$added = array();
		$seen  = array();
		$visit = static function ( string $slug, string $by ) use ( &$visit, &$order, &$added, &$seen, $catalog, $state, $installed, $selected ) {
			if ( isset( $seen[ $slug ] ) ) {
				return;
			}
			$seen[ $slug ] = true;
			$req           = isset( $catalog[ $slug ] ) ? FLXU_Updater::requires_plugins( $slug, $catalog[ $slug ] ) : array();
			foreach ( $req as $dep ) {
				$ok = ! empty( $installed[ $dep ]['active'] );
				if ( ! $ok || isset( $selected[ $dep ] ) ) {
					if ( ! isset( $selected[ $dep ] ) && ! isset( $added[ $dep ] ) ) {
						$added[ $dep ] = isset( $catalog[ $slug ] ) ? $catalog[ $slug ]['name'] : $slug;
					}
					$visit( $dep, $slug );
				}
			}
			$order[] = $slug;
		};
		foreach ( array_keys( $selected ) as $s ) {
			$visit( $s, '' );
		}

		$install = array();
		$update  = array();
		$deps    = array();
		foreach ( $order as $slug ) {
			$st = $state[ $slug ] ?? '';
			if ( isset( $catalog[ $slug ] ) && 'missing' === $st ) {
				$install[] = $slug;
			} elseif ( isset( $catalog[ $slug ] ) && 'update' === $st && isset( $selected[ $slug ] ) ) {
				$update[] = $slug;
				// Güncellenen paketin gerekli eklentisi de kurulu/etkin olsun.
			} elseif ( ! isset( $catalog[ $slug ] ) || empty( $installed[ $slug ]['active'] ) ) {
				if ( ! isset( $selected[ $slug ] ) ) {
					$deps[] = $slug;
				}
			}
		}
		return compact( 'install', 'update', 'deps', 'added' );
	}

	/* ------------------------------------------------------------------ */
	/* Çalıştır                                                            */
	/* ------------------------------------------------------------------ */

	/**
	 * Planı adımlara çevirir (panel bunları sırayla, sayfadan çıkmadan çalıştırır).
	 *
	 * @return array[] {kind: install|wporg|activate|update, slug, name, why}
	 */
	public static function steps( array $plan, array $catalog, bool $activate ): array {
		$installed = self::installed_plugins();
		$steps     = array();
		foreach ( self::topo( array_merge( $plan['deps'], $plan['install'] ), $catalog ) as $slug ) {
			$p      = $catalog[ $slug ] ?? null;
			$is_dep = in_array( $slug, $plan['deps'], true );
			$base   = array(
				'slug' => $slug,
				'name' => self::plugin_name( $slug, $catalog, $installed ),
				'why'  => (string) ( $plan['added'][ $slug ] ?? '' ),
			);
			if ( ! isset( $installed[ $slug ] ) ) {
				$steps[] = array( 'kind' => $p ? 'install' : 'wporg' ) + $base;
			}
			if ( ( ! $p || 'plugin' === $p['type'] ) && empty( $installed[ $slug ]['active'] ) && ( $activate || $is_dep ) ) {
				$steps[] = array( 'kind' => 'activate' ) + $base;
			}
		}
		foreach ( $plan['update'] as $slug ) {
			$steps[] = array(
				'kind' => 'update',
				'slug' => $slug,
				'name' => $catalog[ $slug ]['name'],
				'why'  => '',
			);
		}
		return $steps;
	}

	/**
	 * Tek adımı çalıştırır.
	 *
	 * @param string[] $allowed_wporg wordpress.org'dan kurulabilecek slug'lar (katalogdaki paketlerin gerekli eklentileri).
	 * @return array {ok, lines: [{ok, text}]}
	 */
	public function step( string $kind, string $slug, array $catalog, array $allowed_wporg ): array {
		require_once ABSPATH . 'wp-admin/includes/class-wp-upgrader.php';
		require_once ABSPATH . 'wp-admin/includes/file.php';
		require_once ABSPATH . 'wp-admin/includes/misc.php';
		require_once ABSPATH . 'wp-admin/includes/plugin-install.php';
		if ( ! function_exists( 'activate_plugin' ) ) {
			require_once ABSPATH . 'wp-admin/includes/plugin.php';
		}
		$installed = self::installed_plugins();
		$p         = $catalog[ $slug ] ?? null;
		$name      = self::plugin_name( $slug, $catalog, $installed );
		$ok        = false;

		switch ( $kind ) {
			case 'install':
				if ( ! $p ) {
					$this->add( false, __( 'Paket bulunamadı.', 'flx-updater' ) );
					break;
				}
				if ( isset( $installed[ $slug ] ) || ( 'theme' === $p['type'] && wp_get_theme( $slug )->exists() ) ) {
					/* translators: %s: package */
					$this->add( true, sprintf( __( '%s zaten kurulu.', 'flx-updater' ), $name ) );
					$ok = true;
					break;
				}
				$ok = $this->install_package( $slug, $p );
				if ( $ok ) {
					/* translators: 1: package, 2: version */
					$this->add( true, sprintf( __( '%1$s %2$s kuruldu.', 'flx-updater' ), $name, $p['version'] ) );
					FLXU_Guard::instance()->record( $slug, $name, $p['type'], $p['version'], 'install', true );
				}
				break;

			case 'wporg':
				if ( ! in_array( $slug, $allowed_wporg, true ) ) {
					$this->add( false, __( 'Bu eklenti hiçbir FluxWP paketinin gereksinimi değil.', 'flx-updater' ) );
					break;
				}
				if ( isset( $installed[ $slug ] ) ) {
					/* translators: %s: plugin */
					$this->add( true, sprintf( __( '%s zaten kurulu.', 'flx-updater' ), $name ) );
					$ok = true;
					break;
				}
				$ok = $this->install_wporg( $slug, $name );
				if ( $ok ) {
					/* translators: %s: plugin */
					$this->add( true, sprintf( __( '%s wordpress.org\'dan kuruldu.', 'flx-updater' ), $name ) );
					FLXU_Guard::instance()->record( $slug, $name, 'plugin', '', 'install', true );
				}
				break;

			case 'activate':
				if ( ! isset( $installed[ $slug ] ) ) {
					/* translators: %s: plugin */
					$this->add( false, sprintf( __( '%s kurulu olmadığı için etkinleştirilemedi.', 'flx-updater' ), $name ) );
					break;
				}
				if ( $installed[ $slug ]['active'] ) {
					/* translators: %s: plugin */
					$this->add( true, sprintf( __( '%s zaten etkin.', 'flx-updater' ), $name ) );
					$ok = true;
					break;
				}
				$ok = $this->activate( $slug, $installed[ $slug ]['file'], $name, $catalog );
				break;

			default:
				$this->add( false, __( 'Bilinmeyen işlem.', 'flx-updater' ) );
		}
		return array(
			'ok'    => $ok,
			'lines' => $this->results,
		);
	}

	/** Katalogdaki paketlerin gerekli eklentilerinden katalog dışı olanlar (wordpress.org'dan kurulabilir). */
	public static function wporg_allowed( array $catalog ): array {
		$out = array();
		foreach ( $catalog as $slug => $p ) {
			foreach ( FLXU_Updater::requires_plugins( $slug, $p ) as $d ) {
				if ( ! isset( $catalog[ $d ] ) ) {
					$out[] = $d;
				}
			}
		}
		return array_values( array_unique( $out ) );
	}

	/** Seçilen slug'ları gerekli eklentiler önce gelecek biçimde sıralar. */
	private static function topo( array $slugs, array $catalog ): array {
		$set   = array_flip( $slugs );
		$out   = array();
		$seen  = array();
		$visit = static function ( string $s ) use ( &$visit, &$out, &$seen, $set, $catalog ) {
			if ( isset( $seen[ $s ] ) ) {
				return;
			}
			$seen[ $s ] = true;
			foreach ( FLXU_Updater::requires_plugins( $s, $catalog[ $s ] ?? array() ) as $d ) {
				if ( isset( $set[ $d ] ) ) {
					$visit( $d );
				}
			}
			$out[] = $s;
		};
		foreach ( $slugs as $s ) {
			$visit( $s );
		}
		return $out;
	}

	private function activate( string $slug, string $file, string $name, array $catalog ): bool {
		// Gerekli eklentiler etkin değilse WordPress'in hata sayfasına düşmeden açıkla.
		$installed = self::installed_plugins();
		$missing   = array();
		foreach ( FLXU_Updater::requires_plugins( $slug, $catalog[ $slug ] ?? array() ) as $dep ) {
			if ( empty( $installed[ $dep ]['active'] ) ) {
				$missing[] = self::plugin_name( $dep, $catalog, $installed );
			}
		}
		if ( $missing ) {
			/* translators: 1: plugin, 2: required plugins */
			$this->add( false, sprintf( __( '%1$s etkinleştirilmedi: önce %2$s kurulup etkinleştirilmeli.', 'flx-updater' ), $name, implode( ', ', $missing ) ) );
			return false;
		}
		$res = activate_plugin( $file, '', false, true );
		if ( is_wp_error( $res ) ) {
			/* translators: 1: plugin, 2: error */
			$this->add( false, sprintf( __( '%1$s etkinleştirilemedi: %2$s', 'flx-updater' ), $name, wp_strip_all_tags( $res->get_error_message() ) ) );
			return false;
		}
		/* translators: %s: plugin */
		$this->add( true, sprintf( __( '%s etkinleştirildi.', 'flx-updater' ), $name ) );
		return true;
	}

	private function install_package( string $slug, array $p ): bool {
		$cap = 'theme' === $p['type'] ? 'install_themes' : 'install_plugins';
		if ( ! current_user_can( $cap ) ) {
			/* translators: %s: package */
			$this->add( false, sprintf( __( '%s: kurma yetkiniz yok.', 'flx-updater' ), $p['name'] ) );
			return false;
		}
		$skin     = new WP_Ajax_Upgrader_Skin();
		$upgrader = 'theme' === $p['type'] ? new Theme_Upgrader( $skin ) : new Plugin_Upgrader( $skin );
		$res      = $upgrader->install( $p['package'] );
		return $this->check_install( $res, $skin, $p['name'], $slug, $p['type'] );
	}

	private function install_wporg( string $slug, string $name ): bool {
		if ( ! current_user_can( 'install_plugins' ) ) {
			/* translators: %s: plugin */
			$this->add( false, sprintf( __( '%s: kurma yetkiniz yok.', 'flx-updater' ), $name ) );
			return false;
		}
		$api = plugins_api(
			'plugin_information',
			array(
				'slug'   => $slug,
				'fields' => array( 'sections' => false ),
			)
		);
		if ( is_wp_error( $api ) || empty( $api->download_link ) ) {
			/* translators: 1: plugin, 2: error */
			$this->add( false, sprintf( __( '%1$s wordpress.org\'dan alınamadı: %2$s', 'flx-updater' ), $name, is_wp_error( $api ) ? $api->get_error_message() : __( 'indirme adresi yok', 'flx-updater' ) ) );
			return false;
		}
		$skin     = new WP_Ajax_Upgrader_Skin();
		$upgrader = new Plugin_Upgrader( $skin );
		$res      = $upgrader->install( $api->download_link );
		return $this->check_install( $res, $skin, $name, $slug, 'plugin' );
	}

	/** @param mixed $res */
	private function check_install( $res, WP_Ajax_Upgrader_Skin $skin, string $name, string $slug, string $type ): bool {
		$err = is_wp_error( $res ) ? $res : ( $skin->get_errors()->has_errors() ? $skin->get_errors() : null );
		if ( $err || ! $res ) {
			$msg = $err ? wp_strip_all_tags( $err->get_error_message() ) : __( 'bilinmeyen hata', 'flx-updater' );
			/* translators: 1: package, 2: error */
			$this->add( false, sprintf( __( '%1$s kurulamadı: %2$s', 'flx-updater' ), $name, $msg ) );
			FLXU_Guard::instance()->record( $slug, $name, $type, '', 'install', false, $msg );
			return false;
		}
		wp_clean_plugins_cache( false );
		return true;
	}

	private function add( bool $ok, string $text ): void {
		$this->results[] = array(
			'ok'   => $ok,
			'text' => $text,
		);
	}
}
