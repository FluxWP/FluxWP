<?php
/** Ortak sistem kontrolü (`flx_system_checks`) ve izinli düzeltmeler (`flx_system_fixes`). Dış HTTP yok. */
defined( 'ABSPATH' ) || exit;

add_filter( 'flx_system_checks', 'flxa_system_checks' );
add_filter( 'flx_system_fixes', 'flxa_system_fixes' );
add_filter( 'flx_system_checks_versions', 'flxa_system_versions' );

function flxa_system_versions( $v ) {
	$v['flx-admin'] = FLXA_VERSION;
	return $v;
}

function flxa_system_checks( $checks ) {
	$src = 'Flux Admin';
	$url = flxa_settings_url();

	$checks[] = array(
		'id'      => 'flxa.enabled',
		'source'  => $src,
		'group'   => 'Config',
		'label'   => __( 'Yönetim teması', 'flx-admin' ),
		'status'  => flxa_enabled() ? 'ok' : 'info',
		'message' => flxa_enabled() ? __( 'Açık. Rol bazlı menü, alt bar ve açık/koyu tema çalışıyor.', 'flx-admin' ) : __( 'Kapalı; WordPress\'in standart paneli görünüyor.', 'flx-admin' ),
		'action_url'   => $url,
		'action_label' => __( 'Ayarlar', 'flx-admin' ),
	);

	if ( flxa_setting( 'pwa' ) ) {
		$ok       = flxa_pwa_possible();
		$checks[] = array(
			'id'      => 'flxa.pwa.https',
			'source'  => $src,
			'group'   => 'Config',
			'label'   => __( 'Uygulama olarak yükleme (PWA)', 'flx-admin' ),
			'status'  => $ok ? 'ok' : 'warning',
			'message' => $ok ? __( 'HTTPS açık; panel telefona ve bilgisayara uygulama gibi yüklenebilir.', 'flx-admin' ) : __( 'Site HTTPS değil; tarayıcılar PWA yüklemeye izin vermez. Ayarlar › Genel\'de adresleri https:// yapın.', 'flx-admin' ),
			'action_url'   => $ok ? '' : admin_url( 'options-general.php' ),
			'action_label' => $ok ? '' : __( 'Genel ayarlar', 'flx-admin' ),
		);
		$checks[] = array(
			'id'      => 'flxa.pwa.icon',
			'source'  => $src,
			'group'   => 'Config',
			'label'   => __( 'Uygulama simgesi', 'flx-admin' ),
			'status'  => get_option( 'site_icon' ) ? 'ok' : 'info',
			'message' => get_option( 'site_icon' ) ? __( 'Site simgesi kullanılıyor.', 'flx-admin' ) : __( 'Site simgesi yok; Flux Admin simgesi kullanılıyor. Görünüm › Özelleştir › Site kimliği\'nden değiştirilebilir.', 'flx-admin' ),
		);
	}

	$menus    = flxa_menus_option();
	$n        = count( (array) ( $menus['roles'] ?? array() ) );
	$cat      = get_option( 'flxa_menu_catalog', array() );
	$checks[] = array(
		'id'      => 'flxa.menu',
		'source'  => $src,
		'group'   => 'Config',
		'label'   => __( 'Rol bazlı menü', 'flx-admin' ),
		'status'  => 'ok',
		'message' => $n
			/* translators: %s: role count */
			? sprintf( _n( '%s rol için özel menü düzeni var; diğer roller varsayılan gruplu menüyü görür.', '%s rol için özel menü düzeni var; diğer roller varsayılan gruplu menüyü görür.', $n, 'flx-admin' ), number_format_i18n( $n ) )
			: __( 'Tüm roller varsayılan gruplu menüyü görüyor.', 'flx-admin' ),
		'action_url'   => flxa_settings_url( 'menu' ),
		'action_label' => __( 'Menüyü düzenle', 'flx-admin' ),
	);
	if ( empty( $cat['items'] ) ) {
		$checks[] = array(
			'id'      => 'flxa.menu.catalog',
			'source'  => $src,
			'group'   => 'Config',
			'label'   => __( 'Menü listesi', 'flx-admin' ),
			'status'  => 'info',
			'message' => __( 'Menü düzenleyicinin listesi bir yönetici paneli açınca oluşur.', 'flx-admin' ),
		);
	}

	$hb       = (int) flxa_setting( 'heartbeat' );
	$checks[] = array(
		'id'      => 'flxa.live',
		'source'  => $src,
		'group'   => 'Config',
		'label'   => __( 'Canlı sayaçlar', 'flx-admin' ),
		'status'  => $hb < 30 ? 'warning' : 'ok',
		/* translators: %s: seconds */
		'message' => $hb < 30 ? sprintf( __( 'Heartbeat %s sn; Hostinger yoğun istekte IP\'yi geçici kesebilir. 60 sn önerilir.', 'flx-admin' ), $hb ) : sprintf( __( 'Rozetler WordPress Heartbeat ile %s sn\'de bir yenilenir (ek istek yok).', 'flx-admin' ), $hb ),
		'action_url'   => $hb < 30 ? $url : '',
		'action_label' => $hb < 30 ? __( 'Ayarlar', 'flx-admin' ) : '',
	);
	return $checks;
}

function flxa_system_fixes( $fixes ) {
	$save = function ( $key, $value ) {
		$s         = get_option( 'flxa_settings', array() );
		$s         = is_array( $s ) ? $s : array();
		$s[ $key ] = $value;
		update_option( 'flxa_settings', $s );
	};
	$fixes['flxa.enabled'] = array(
		'label'       => __( 'Yönetim temasını aç/kapat', 'flx-admin' ),
		'description' => __( '1 = açık, 0 = WordPress standart paneli.', 'flx-admin' ),
		'source'      => 'Flux Admin',
		'checks'      => array( 'flxa.enabled' ),
		'value'       => array( 'type' => 'enum', 'enum' => array( '1', '0' ) ),
		'current'     => function () {
			return flxa_enabled() ? '1' : '0';
		},
		'run'         => function ( $v ) use ( $save ) {
			$save( 'enabled', '1' === (string) $v ? 1 : 0 );
			return '1' === (string) $v ? __( 'Yönetim teması açıldı.', 'flx-admin' ) : __( 'Yönetim teması kapatıldı.', 'flx-admin' );
		},
	);
	$fixes['flxa.heartbeat'] = array(
		'label'       => __( 'Canlı sayaç aralığı', 'flx-admin' ),
		'description' => __( 'Saniye (30, 60 veya 120).', 'flx-admin' ),
		'source'      => 'Flux Admin',
		'checks'      => array( 'flxa.live' ),
		'value'       => array( 'type' => 'enum', 'enum' => array( '30', '60', '120' ) ),
		'current'     => function () {
			return (string) (int) flxa_setting( 'heartbeat' );
		},
		'run'         => function ( $v ) use ( $save ) {
			if ( ! in_array( (string) $v, array( '30', '60', '120' ), true ) ) {
				throw new Exception( 'Geçersiz değer.' );
			}
			$save( 'heartbeat', (int) $v );
			/* translators: %s: seconds */
			return sprintf( __( 'Aralık %s sn yapıldı.', 'flx-admin' ), (int) $v );
		},
	);
	$fixes['flxa.pwa'] = array(
		'label'       => __( 'PWA (uygulama olarak yükleme)', 'flx-admin' ),
		'description' => __( '1 = açık, 0 = kapalı (kayıtlı service worker kendini kaldırır).', 'flx-admin' ),
		'source'      => 'Flux Admin',
		'checks'      => array( 'flxa.pwa.https' ),
		'value'       => array( 'type' => 'enum', 'enum' => array( '1', '0' ) ),
		'current'     => function () {
			return flxa_setting( 'pwa' ) ? '1' : '0';
		},
		'run'         => function ( $v ) use ( $save ) {
			$save( 'pwa', '1' === (string) $v ? 1 : 0 );
			return '1' === (string) $v ? __( 'PWA açıldı.', 'flx-admin' ) : __( 'PWA kapatıldı.', 'flx-admin' );
		},
	);
	$roles = array_keys( (array) ( flxa_menus_option()['roles'] ?? array() ) );
	if ( $roles ) {
		$fixes['flxa.menu_reset'] = array(
			'label'       => __( 'Bir rolün menüsünü varsayılana döndür', 'flx-admin' ),
			'description' => __( 'Rol anahtarı (ör. editor).', 'flx-admin' ),
			'source'      => 'Flux Admin',
			'checks'      => array( 'flxa.menu' ),
			'value'       => array( 'type' => 'enum', 'enum' => $roles ),
			'current'     => function () use ( $roles ) {
				return implode( ', ', $roles );
			},
			'run'         => function ( $v ) {
				$o = flxa_menus_option();
				if ( ! isset( $o['roles'][ $v ] ) ) {
					throw new Exception( 'Bu rolün özel menüsü yok.' );
				}
				unset( $o['roles'][ $v ] );
				update_option( 'flxa_menus', $o );
				/* translators: %s: role */
				return sprintf( __( '%s menüsü varsayılana döndü.', 'flx-admin' ), $v );
			},
		);
	}
	return $fixes;
}
