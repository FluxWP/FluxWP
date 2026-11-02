<?php
/**
 * Sistem kontrolü satırları (ortak `flx_system_checks` sözleşmesi): Tema Ayarları, görünmez mod, kalıcı bağlantılar,
 * hazır güncellemeler ve Flux QR Menu. Ucuz: yalnız seçenek ve sayım okuması.
 *
 * @package flx-menu-theme
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

add_filter( 'flx_system_checks', 'yf_system_checks' );

function yf_system_checks( $checks ) {
	$checks = is_array( $checks ) ? $checks : array();
	$src    = __( 'Flux Menu Theme teması', 'flx-menu-theme' );
	$ayar   = admin_url( 'themes.php?page=yf-ayarlar' );
	$kur    = admin_url( 'themes.php?page=yf-kurulum' );
	$row    = function ( $id, $label, $status, $message, $url, $action ) use ( $src ) {
		return array(
			'id'           => 'yf.' . $id,
			'source'       => $src,
			'group'        => __( 'Tema', 'flx-menu-theme' ),
			'label'        => $label,
			'status'       => $status,
			'message'      => $message,
			'action_url'   => $url,
			'action_label' => $action,
		);
	};

	$off = array();
	foreach ( yf_ayar_labels() as $k => $l ) {
		if ( yf_ayar( $k ) ) {
			$off[] = $l[0];
		}
	}
	$all      = count( yf_ayar_defaults() );
	$checks[] = $row(
		'ayarlar',
		__( 'Kapalı WordPress özellikleri', 'flx-menu-theme' ),
		count( $off ) === $all ? 'ok' : 'info',
		/* translators: 1: closed count, 2: total, 3: list */
		sprintf( __( '%1$d / %2$d kapalı: %3$s', 'flx-menu-theme' ), count( $off ), $all, $off ? implode( ', ', $off ) : '-' ),
		$ayar,
		__( 'Tema Ayarları', 'flx-menu-theme' )
	);

	if ( yf_ayar( 'posts_off' ) ) {
		$n        = (int) wp_count_posts( 'post' )->publish;
		$checks[] = $row(
			'posts',
			__( 'Yazılar', 'flx-menu-theme' ),
			$n ? 'warning' : 'ok',
			$n
				/* translators: %d: number of published posts */
				? sprintf( __( 'Yazılar kapalı ama %d yayında yazı var; ziyaretçi göremez (404). Gerekmiyorsa silin.', 'flx-menu-theme' ), $n )
				: __( 'Yayında yazı yok.', 'flx-menu-theme' ),
			$n ? admin_url( 'edit.php' ) : $ayar,
			$n ? __( 'Yazılar', 'flx-menu-theme' ) : __( 'Tema Ayarları', 'flx-menu-theme' )
		);
	}

	if ( yf_ayar( 'comments_off' ) ) {
		$open     = 'open' === get_option( 'default_comment_status' ) || 'open' === get_option( 'default_ping_status' );
		$checks[] = $row(
			'comments',
			__( 'Yorum varsayılanları', 'flx-menu-theme' ),
			$open ? 'warning' : 'ok',
			$open ? __( 'Yorumlar kapalı ama yeni içerikte yorum/geri bildirim varsayılanı açık. Site Kurulumu › Yorumları kapat adımını çalıştırın.', 'flx-menu-theme' ) : __( 'Yorum ve geri bildirim varsayılanları kapalı.', 'flx-menu-theme' ),
			$kur,
			__( 'Site Kurulumu', 'flx-menu-theme' )
		);
	}

	$checks = array_merge( $checks, yf_gorunmez_checks( $row, $ayar ) );

	$plain    = '' === (string) get_option( 'permalink_structure' );
	$checks[] = $row(
		'permalink',
		__( 'Kalıcı bağlantılar', 'flx-menu-theme' ),
		$plain ? 'error' : 'ok',
		$plain ? __( 'Kalıcı bağlantılar "Düz" (?p=). QR menü adresleri çalışmaz; Site Kurulumu ile /%postname%/ yapın.', 'flx-menu-theme' ) : (string) get_option( 'permalink_structure' ),
		$kur,
		__( 'Site Kurulumu', 'flx-menu-theme' )
	);

	$pending  = count( yf_kurulum_pending() );
	$checks[] = $row(
		'migrations',
		__( 'Hazır güncellemeler', 'flx-menu-theme' ),
		$pending ? 'warning' : 'ok',
		$pending
			/* translators: %d: number of pending updates */
			? sprintf( __( 'Uygulanmayı bekleyen hazır güncelleme: %d.', 'flx-menu-theme' ), $pending )
			: __( 'Bütün hazır güncellemeler uygulandı.', 'flx-menu-theme' ),
		$kur . '#guncellemeler',
		__( 'Site Kurulumu', 'flx-menu-theme' )
	);

	if ( ! function_exists( 'flxq_settings' ) ) {
		$checks[] = $row(
			'qrmenu',
			__( 'Flux QR Menu', 'flx-menu-theme' ),
			'warning',
			__( 'Flux QR Menu eklentisi etkin değil; menu.example.org ana sayfası boş kalır ve menü yayınlanmaz.', 'flx-menu-theme' ),
			defined( 'FLXU_VERSION' ) ? admin_url( 'admin.php?page=flx-updater' ) : admin_url( 'plugins.php' ),
			defined( 'FLXU_VERSION' ) ? __( 'Flux Updater', 'flx-menu-theme' ) : __( 'Eklentiler', 'flx-menu-theme' )
		);
	}
	return $checks;
}

/** Görünmez mod satırları: durum, .htaccess, fiziksel robots.txt / site haritası dosyaları, Okuma ayarı, REST listesi. */
function yf_gorunmez_checks( $row, $ayar ) {
	$out = array();
	$url = $ayar . '#gorunmez';
	$lbl = __( 'Tema Ayarları', 'flx-menu-theme' );
	if ( ! yf_gizli() ) {
		$out[] = $row( 'gorunmez', __( 'Görünmez mod', 'flx-menu-theme' ), 'info', __( 'Kapalı: site arama motorlarına ve botlara açık, site haritası ve REST API çalışıyor.', 'flx-menu-theme' ), $url, $lbl );
		return $out;
	}
	$paths = yf_lines( yf_ayar_text( 'path_allow' ) );
	$out[] = $row(
		'gorunmez',
		__( 'Görünmez mod', 'flx-menu-theme' ),
		'ok',
		/* translators: 1: REST prefixes, 2: number of open paths */
		sprintf( __( 'Açık: robots.txt Disallow, noindex, site haritaları 404; admin-ajax, XML-RPC, beslemeler ve sürüm bilgisi kapalı. Açık REST: %1$s. Açık yol: %2$d.', 'flx-menu-theme' ), yf_rest_allow() ? implode( ', ', yf_rest_allow() ) : '-', count( $paths ) ),
		$url,
		$lbl
	);

	$st = (array) get_option( 'yf_gorunmez_state', array() );
	$ht = isset( $st['htaccess'] ) ? $st['htaccess'] : '';
	if ( 'unwritable' === $ht ) {
		$out[] = $row( 'htaccess', __( 'Görünmez mod: .htaccess', 'flx-menu-theme' ), 'warning', __( '.htaccess yazılamadı; dizin listesi ve dosya engelleri (readme, changelog, .md, .log, kurulum betikleri) uygulanmadı. Dosya iznini düzeltip Tema Ayarları\'nı yeniden kaydedin ya da "Yesil Fun" bloğunu elle ekleyin.', 'flx-menu-theme' ), $url, $lbl );
	} elseif ( 'unsupported' === $ht ) {
		$out[] = $row( 'htaccess', __( 'Görünmez mod: .htaccess', 'flx-menu-theme' ), 'info', __( 'Sunucu .htaccess okumuyor (Apache/LiteSpeed değil); dizin listesini, readme/.md/.log dosyalarını ve wp-admin/install.php erişimini sunucu ayarından kapatın.', 'flx-menu-theme' ), $url, $lbl );
	} elseif ( 'ok' === $ht ) {
		$out[] = $row( 'htaccess', __( 'Görünmez mod: .htaccess', 'flx-menu-theme' ), 'ok', __( 'Dizin listesi kapalı; readme, changelog, .md ve .log dosyalarına ve kurulum betiklerine erişim yok.', 'flx-menu-theme' ), $url, $lbl );
	}

	// Sunucunun doğrudan verdiği dosyalar WordPress'e hiç uğramaz.
	$root  = trailingslashit( function_exists( 'get_home_path' ) ? get_home_path() : ABSPATH );
	$robot = $root . 'robots.txt';
	if ( is_readable( $robot ) ) {
		$txt = (string) file_get_contents( $robot ); // phpcs:ignore WordPress.WP.AlternativeFunctions
		$ok  = (bool) preg_match( '/^\s*Disallow:\s*\/\s*$/mi', $txt ) && (bool) preg_match( '/^\s*User-agent:\s*\*\s*$/mi', $txt );
		$out[] = $row(
			'robots',
			__( 'Görünmez mod: robots.txt dosyası', 'flx-menu-theme' ),
			$ok ? 'info' : 'warning',
			$ok ? __( 'Sitede gerçek bir robots.txt dosyası var ve botları engelliyor; tema ayarı yerine o kullanılır.', 'flx-menu-theme' ) : __( 'Sitede gerçek bir robots.txt dosyası var; WordPress\'in "Disallow: /" çıktısı yerine o gösteriliyor. Dosyayı (Dosya Yöneticisi\'nden) silin.', 'flx-menu-theme' ),
			home_url( '/robots.txt' ),
			'robots.txt'
		);
	}
	$maps = array();
	foreach ( (array) glob( $root . '*sitemap*.xml*' ) as $f ) {
		$maps[] = basename( $f );
	}
	if ( $maps ) {
		/* translators: %s: file names */
		$out[] = $row( 'sitemapfile', __( 'Görünmez mod: site haritası dosyası', 'flx-menu-theme' ), 'warning', sprintf( __( 'Sitenin kökünde site haritası dosyası var (%s); sunucu bunu doğrudan verir. Silin.', 'flx-menu-theme' ), implode( ', ', $maps ) ), $url, $lbl );
	}

	if ( '0' !== (string) get_option( 'blog_public' ) ) {
		$out[] = $row( 'blogpublic', __( 'Görünmez mod: Okuma ayarı', 'flx-menu-theme' ), 'warning', __( 'Ayarlar › Okuma\'da "Arama motorlarının siteyi dizine eklemesini engelle" işaretli değil. Tema Ayarları\'nı yeniden kaydedin.', 'flx-menu-theme' ), admin_url( 'options-reading.php' ), __( 'Okuma ayarları', 'flx-menu-theme' ) );
	}
	if ( function_exists( 'flxq_settings' ) && ! yf_rest_allowed( '/flx-qr-menu/v1/rev/1' ) ) {
		$out[] = $row( 'restqr', __( 'Görünmez mod: QR menü REST', 'flx-menu-theme' ), 'warning', __( 'flx-qr-menu/v1 açık REST listesinde değil; açık menüler kendini yenilemez ve menü siteleri beslemeyi çekemez.', 'flx-menu-theme' ), $url, $lbl );
	}
	if ( defined( 'LSCWP_V' ) && ! yf_rest_allowed( '/litespeed/v1/x' ) ) {
		$out[] = $row( 'restls', __( 'Görünmez mod: LiteSpeed / QUIC.cloud', 'flx-menu-theme' ), 'info', __( 'QUIC.cloud hizmetleri (görsel optimizasyonu, CCSS) kullanıyorsanız açık REST listesine litespeed/v1 ve litespeed/v3 ekleyin; sayfa önbelleği için gerekmez.', 'flx-menu-theme' ), $url, $lbl );
	}
	if ( yf_wc() ) {
		$out[] = $row( 'wc', __( 'Görünmez mod: WooCommerce', 'flx-menu-theme' ), 'info', __( 'WooCommerce sayfaları, Store API, wc-ajax ve wc-api giriş yapmamış ziyaretçiye kapalı. Ödeme yöntemi geri dönüş adresi (ör. /wc-api/iyzico) kullanıyorsa "Açık kalacak yollar"a ekleyin.', 'flx-menu-theme' ), $url, $lbl );
	}
	return $out;
}
