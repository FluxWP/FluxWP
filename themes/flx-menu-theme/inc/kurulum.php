<?php
/**
 * Görünüm › Site Kurulumu: seçilen temel adımlar (bir kez, sonuç satır satır) ve "Hazır güncellemeler"
 * (tek tıkla, tekrar çalıştırılabilir; uygulananlar `yf_migrations` seçeneğinde tarihle). Yeni güncelleme =
 * yf_kurulum_migrations() dizisine bir kayıt.
 *
 * @package flx-menu-theme
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

function yf_kurulum_menu() {
	add_theme_page( __( 'Site Kurulumu', 'flx-menu-theme' ), __( 'Site Kurulumu', 'flx-menu-theme' ), 'manage_options', 'yf-kurulum', 'yf_kurulum_page' );
}
add_action( 'admin_menu', 'yf_kurulum_menu' );

/** Adım anahtarı => [başlık, açıklama]. */
function yf_kurulum_steps() {
	return array(
		'permalink' => array( __( 'Kalıcı bağlantılar: Yazı adı (/%postname%/)', 'flx-menu-theme' ), __( 'Menü adresleri (/menu/) için gerekli. Kurallar yenilenir.', 'flx-menu-theme' ) ),
		'timezone'  => array( __( 'Saat dilimi: İstanbul', 'flx-menu-theme' ), __( 'Europe/Istanbul.', 'flx-menu-theme' ) ),
		'dateformat' => array( __( 'Tarih biçimi: 24.10.2026, saat 14:30', 'flx-menu-theme' ), __( 'd.m.Y ve H:i.', 'flx-menu-theme' ) ),
		'language'  => array( __( 'Site dili: Türkçe', 'flx-menu-theme' ), __( 'Türkçe dil paketi yüklüyse seçilir (indirme yapılmaz).', 'flx-menu-theme' ) ),
		'cleanup'   => array( __( 'Varsayılan içerikleri temizle', 'flx-menu-theme' ), __( '"Merhaba dünya!" yazısı ve yorumu, "Örnek sayfa" ve taslak Gizlilik Politikası, yalnız hiç değiştirilmemişlerse silinir.', 'flx-menu-theme' ) ),
		'comments'  => array( __( 'Yorumları kapat', 'flx-menu-theme' ), __( 'Yeni içerikte yorum ve geri bildirim kapalı; var olan içeriklerde de kapatılır.', 'flx-menu-theme' ) ),
		'hidden'    => array( __( 'Görünmez modu aç', 'flx-menu-theme' ), __( 'Arama motorları ve botlar siteyi görmez; site haritası, REST API ve diğer adresler giriş yapmamış ziyaretçiye kapalı, QR menüler açık (Tema Ayarları › Görünmez mod).', 'flx-menu-theme' ) ),
		'front'     => array( __( 'Ana sayfayı kontrol et', 'flx-menu-theme' ), __( 'Okuma ayarına dokunulmaz; ana sayfanın temanın boş beyaz şablonuyla açıldığı denetlenir.', 'flx-menu-theme' ) ),
	);
}

/**
 * Seçili adımları çalıştırır.
 *
 * @return array<int,array{0:string,1:bool}> [satır, uyarı mı].
 */
function yf_kurulum_run( array $steps ) {
	$log = array();
	if ( in_array( 'permalink', $steps, true ) ) {
		if ( '/%postname%/' !== get_option( 'permalink_structure' ) ) {
			update_option( 'permalink_structure', '/%postname%/' );
		}
		flush_rewrite_rules( false );
		$log[] = array( __( 'Kalıcı bağlantılar /%postname%/ yapıldı, kurallar yenilendi.', 'flx-menu-theme' ), false );
	}
	if ( in_array( 'timezone', $steps, true ) ) {
		update_option( 'timezone_string', 'Europe/Istanbul' );
		update_option( 'gmt_offset', '' );
		$log[] = array( __( 'Saat dilimi Europe/Istanbul.', 'flx-menu-theme' ), false );
	}
	if ( in_array( 'dateformat', $steps, true ) ) {
		update_option( 'date_format', 'd.m.Y' );
		update_option( 'time_format', 'H:i' );
		$log[] = array( __( 'Tarih biçimi d.m.Y, saat H:i.', 'flx-menu-theme' ), false );
	}
	if ( in_array( 'language', $steps, true ) ) {
		if ( in_array( 'tr_TR', get_available_languages(), true ) ) {
			update_option( 'WPLANG', 'tr_TR' );
			$log[] = array( __( 'Site dili Türkçe.', 'flx-menu-theme' ), false );
		} else {
			$log[] = array( __( 'Türkçe dil paketi yüklü değil; Ayarlar › Genel › Site dili\'nden Türkçe seçip kaydedin (paket orada indirilir).', 'flx-menu-theme' ), true );
		}
	}
	if ( in_array( 'cleanup', $steps, true ) ) {
		$log = array_merge( $log, yf_kurulum_cleanup() );
	}
	if ( in_array( 'comments', $steps, true ) ) {
		$log[] = array( yf_close_comments(), false );
	}
	if ( in_array( 'hidden', $steps, true ) ) {
		$log[] = array( yf_gorunmez_on(), false );
	}
	if ( in_array( 'front', $steps, true ) ) {
		$log = array_merge( $log, yf_kurulum_front_check() );
	}
	update_option( 'yf_kurulum_done', time(), false );
	yf_purge_cache();
	return $log;
}

/** Hiç değiştirilmemiş bir içerik mi (oluşturulduğu andan sonra düzenlenmemiş)? */
function yf_is_untouched( $post ) {
	return $post && $post->post_modified_gmt === $post->post_date_gmt;
}

/** WordPress kurulumunun örnek içeriklerini (hiç değiştirilmemişse) siler. */
function yf_kurulum_cleanup() {
	$log  = array();
	$hits = array(
		array( 1, 'post', array( 'hello-world', 'merhaba-dunya' ), 'publish' ),
		array( 2, 'page', array( 'sample-page', 'ornek-sayfa' ), 'publish' ),
		array( 3, 'page', array( 'privacy-policy', 'gizlilik-politikasi' ), 'draft' ),
	);
	foreach ( $hits as $h ) {
		$p = get_post( $h[0] );
		if ( ! $p || $p->post_type !== $h[1] || ! in_array( $p->post_name, $h[2], true ) ) {
			continue;
		}
		$title = $p->post_title;
		if ( $p->post_status !== $h[3] || ! yf_is_untouched( $p ) ) {
			/* translators: %s: page or post title */
			$log[] = array( sprintf( __( '"%s" değiştirildiği için silinmedi.', 'flx-menu-theme' ), $title ), true );
			continue;
		}
		wp_delete_post( $p->ID, true ); // Yorumları da silinir.
		if ( 3 === $h[0] && (int) get_option( 'wp_page_for_privacy_policy' ) === 3 ) {
			update_option( 'wp_page_for_privacy_policy', 0 );
		}
		/* translators: %s: page or post title */
		$log[] = array( sprintf( __( '"%s" silindi.', 'flx-menu-theme' ), $title ), false );
	}
	if ( ! $log ) {
		$log[] = array( __( 'Silinecek varsayılan içerik yok.', 'flx-menu-theme' ), false );
	}
	return $log;
}

/** Yorum ve geri bildirim varsayılanlarını kapatır, var olan içeriklerde de kapatır. Tekrar çalıştırmak güvenli. */
function yf_close_comments() {
	global $wpdb;
	update_option( 'default_comment_status', 'closed' );
	update_option( 'default_ping_status', 'closed' );
	update_option( 'default_pingback_flag', 0 );
	$n = (int) $wpdb->query( "UPDATE {$wpdb->posts} SET comment_status = 'closed', ping_status = 'closed' WHERE comment_status <> 'closed' OR ping_status <> 'closed'" ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
	clean_post_cache( 0 );
	wp_cache_flush();
	/* translators: %d: number of posts/pages */
	return sprintf( __( 'Yorumlar varsayılan olarak kapalı; %d içerikte kapatıldı.', 'flx-menu-theme' ), $n );
}

/** Görünmez modu ve "yalnız menüler açık"ı kaydeder, .htaccess ve Okuma ayarını uygular. */
function yf_gorunmez_on() {
	$o               = yf_ayar_all();
	$o['hidden']     = 1;
	$o['menus_only'] = 1;
	if ( false === get_option( 'yf_ayarlar' ) ) {
		add_option( 'yf_ayarlar', yf_ayar_sanitize( $o ) );
	} else {
		update_option( 'yf_ayarlar', yf_ayar_sanitize( $o ) );
	}
	$st = yf_gorunmez_sync();
	return 'ok' === $st['htaccess']
		? __( 'Görünmez mod açık: robots.txt Disallow, noindex, site haritası ve REST kapalı, .htaccess bloğu eklendi.', 'flx-menu-theme' )
		: __( 'Görünmez mod açık: robots.txt Disallow, noindex, site haritası ve REST kapalı (.htaccess bloğu eklenemedi, Sistem Kontrolü\'ne bakın).', 'flx-menu-theme' );
}

/** Ana sayfa gerçekten temanın boş şablonuyla mı açılıyor? */
function yf_kurulum_front_check() {
	$log = array();
	$tpl = get_block_template( get_stylesheet() . '//front-page' );
	if ( $tpl && 'custom' === $tpl->source ) {
		$log[] = array( __( 'Ana sayfa şablonu Site Düzenleyici\'de değiştirilmiş; boş sayfaya dönmek için Görünüm › Düzenleyici › Şablonlar › Ana sayfa › Özelleştirmeleri temizle.', 'flx-menu-theme' ), true );
	} else {
		$log[] = array( __( 'Ana sayfa temanın boş beyaz şablonuyla açılıyor (yazı listesi yok).', 'flx-menu-theme' ), false );
	}
	if ( 'page' === get_option( 'show_on_front' ) ) {
		$log[] = array( __( 'Okuma ayarı "Sabit sayfa"; tema yine boş ana sayfayı gösterir (sayfa içeriği görünmez).', 'flx-menu-theme' ), false );
	}
	if ( function_exists( 'flxq_settings' ) ) {
		$s     = flxq_settings();
		$names = array(
			'blank'    => __( 'temanın boş sayfası', 'flx-menu-theme' ),
			'redirect' => __( 'seçili menüye yönlendirme', 'flx-menu-theme' ),
			'menu'     => __( 'seçili menü', 'flx-menu-theme' ),
		);
		/* translators: %s: front page mode */
		$log[] = array( sprintf( __( 'Flux QR Menu ana sayfa ayarı: %s.', 'flx-menu-theme' ), $names[ $s['front_mode'] ] ?? $s['front_mode'] ), false );
	}
	return $log;
}

/* ---------- Hazır güncellemeler ---------- */

/** Kimlik => {title, desc, run}. run() sonuç metni döndürür; tekrar çalıştırmak güvenli olmalı. */
function yf_kurulum_migrations() {
	return array(
		'yf120-gorunmez' => array(
			'title' => __( 'flx-menu-theme 1.2.0: görünmez modu aç', 'flx-menu-theme' ),
			'desc'  => __( 'Görünmez modu ve "yalnız menüler açık"ı açar: robots.txt Disallow, noindex, site haritaları ve REST API (flx-qr-menu/v1 hariç) kapalı, .htaccess bloğu, Okuma › arama motorlarını engelle. Tema Ayarları\'ndan kapatılabilir.', 'flx-menu-theme' ),
			'run'   => 'yf_gorunmez_on',
		),
		'yf110-kapat' => array(
			'title' => __( 'flx-menu-theme 1.1.0: yazı/yorum varsayılanlarını kapat', 'flx-menu-theme' ),
			'desc'  => __( 'Yorum ve geri bildirim varsayılanlarını kapatır, var olan içeriklerde kapatır, Tema Ayarları\'ndaki tüm "kapalı" anahtarlarını kaydeder.', 'flx-menu-theme' ),
			'run'   => function () {
				if ( false === get_option( 'yf_ayarlar' ) ) {
					add_option( 'yf_ayarlar', yf_ayar_all_defaults() );
				}
				$msg = yf_close_comments();
				update_option( 'yf_flush_rewrite', 1, true );
				return $msg;
			},
		),
		'permalink'   => array(
			'title' => __( 'Kalıcı bağlantıları yenile', 'flx-menu-theme' ),
			'desc'  => __( 'Bakım: yeniden yazma kurallarını yeniler (adres 404 verirse).', 'flx-menu-theme' ),
			'run'   => function () {
				flush_rewrite_rules( false );
				return __( 'Kalıcı bağlantılar yenilendi.', 'flx-menu-theme' );
			},
		),
	);
}

/** Bakım araçları (her zaman en altta, bekleyen sayılmaz). */
function yf_kurulum_tools() {
	return array( 'permalink' );
}

/** @return string[] Bekleyen (hiç uygulanmamış) güncelleme kimlikleri. */
function yf_kurulum_pending() {
	$done = (array) get_option( 'yf_migrations', array() );
	return array_values( array_diff( array_keys( yf_kurulum_migrations() ), array_keys( array_filter( $done ) ), yf_kurulum_tools() ) );
}

/** Panel sırası: bekleyenler (en yeni önce), uygulananlar (son uygulanan önce), bakım araçları. */
function yf_kurulum_migrations_sorted( $done ) {
	$list    = yf_kurulum_migrations();
	$tools   = array_intersect_key( $list, array_flip( yf_kurulum_tools() ) );
	$list    = array_diff_key( $list, $tools );
	$pending = array_reverse( array_diff_key( $list, array_filter( $done ) ), true );
	$applied = array_intersect_key( $list, array_filter( $done ) );
	uksort(
		$applied,
		function ( $a, $b ) use ( $done ) {
			return (int) $done[ $b ] <=> (int) $done[ $a ];
		}
	);
	return $pending + $applied + $tools;
}

function yf_kurulum_migrate_url( $id ) {
	return wp_nonce_url( admin_url( 'themes.php?page=yf-kurulum&yf_migrate=' . rawurlencode( $id ) ), 'yf_migrate_' . $id );
}

/** Bir güncellemeyi uygular ve tarihini işaretler. */
function yf_kurulum_apply_migration( $id ) {
	$list = yf_kurulum_migrations();
	if ( ! isset( $list[ $id ] ) ) {
		return '';
	}
	$msg         = (string) call_user_func( $list[ $id ]['run'] );
	$done        = (array) get_option( 'yf_migrations', array() );
	$done[ $id ] = time();
	update_option( 'yf_migrations', $done, false );
	yf_purge_cache();
	return $msg;
}

function yf_kurulum_handle_migrate() {
	if ( empty( $_GET['yf_migrate'] ) || ! current_user_can( 'manage_options' ) ) {
		return;
	}
	$id = sanitize_key( wp_unslash( $_GET['yf_migrate'] ) );
	check_admin_referer( 'yf_migrate_' . $id );
	if ( ! isset( yf_kurulum_migrations()[ $id ] ) ) {
		return;
	}
	set_transient( 'yf_migrate_msg_' . get_current_user_id(), yf_kurulum_apply_migration( $id ), 60 );
	wp_safe_redirect( admin_url( 'themes.php?page=yf-kurulum#guncellemeler' ) );
	exit;
}
add_action( 'load-appearance_page_yf-kurulum', 'yf_kurulum_handle_migrate' );

function yf_kurulum_page() {
	if ( ! current_user_can( 'manage_options' ) ) {
		return;
	}
	$log = array();
	if ( isset( $_POST['yf_kurulum'] ) && check_admin_referer( 'yf_kurulum' ) ) {
		$steps = isset( $_POST['yf_steps'] ) ? array_map( 'sanitize_key', (array) wp_unslash( $_POST['yf_steps'] ) ) : array();
		$log   = yf_kurulum_run( $steps );
	}
	$mmsg = get_transient( 'yf_migrate_msg_' . get_current_user_id() );
	if ( $mmsg ) {
		delete_transient( 'yf_migrate_msg_' . get_current_user_id() );
	}
	$done  = (array) get_option( 'yf_migrations', array() );
	$first = ! get_option( 'yf_kurulum_done' );
	?>
	<div class="wrap yf-admin yf-kurulum">
		<?php yf_admin_style(); ?>
		<h1><?php esc_html_e( 'Flux Menu Theme: Site Kurulumu', 'flx-menu-theme' ); ?></h1>
		<?php if ( $log ) : ?>
			<div class="notice notice-success"><p><strong><?php esc_html_e( 'Seçili adımlar çalıştı.', 'flx-menu-theme' ); ?></strong></p>
				<ul class="yf-log">
					<?php foreach ( $log as $line ) : ?>
						<li class="<?php echo $line[1] ? 'is-warn' : ''; ?>"><?php echo esc_html( $line[0] ); ?></li>
					<?php endforeach; ?>
				</ul>
			</div>
		<?php endif; ?>
		<?php if ( $mmsg ) : ?>
			<div class="notice notice-success is-dismissible"><p><?php echo esc_html( $mmsg ); ?></p></div>
		<?php endif; ?>

		<div class="yf-card">
			<h2><?php esc_html_e( '1. Temel kurulum', 'flx-menu-theme' ); ?></h2>
			<p><?php esc_html_e( 'İstemediğiniz adımın işaretini kaldırın; yalnız seçilenler çalışır. Tekrar çalıştırmak güvenlidir. Arama motorlarına görünürlük ayarını Görünmez mod yönetir.', 'flx-menu-theme' ); ?></p>
			<form method="post">
				<?php wp_nonce_field( 'yf_kurulum' ); ?>
				<div class="yf-steps">
					<?php foreach ( yf_kurulum_steps() as $k => $s ) : ?>
						<label><input type="checkbox" name="yf_steps[]" value="<?php echo esc_attr( $k ); ?>" checked> <span><strong><?php echo esc_html( $s[0] ); ?></strong><small><?php echo esc_html( $s[1] ); ?></small></span></label>
					<?php endforeach; ?>
				</div>
				<p><button type="submit" name="yf_kurulum" value="1" class="button button-primary"><?php esc_html_e( 'Seçili adımları çalıştır', 'flx-menu-theme' ); ?></button>
				<?php if ( ! $first ) : ?>
					<em style="margin-left:1em"><?php /* translators: %s: date */ echo esc_html( sprintf( __( 'Son çalıştırma: %s', 'flx-menu-theme' ), wp_date( 'd.m.Y H:i', (int) get_option( 'yf_kurulum_done' ) ) ) ); ?></em>
				<?php endif; ?></p>
			</form>
		</div>

		<div class="yf-card">
			<h2><?php esc_html_e( '2. QR menüler', 'flx-menu-theme' ); ?></h2>
			<?php if ( function_exists( 'flxq_settings' ) ) : ?>
				<p><?php esc_html_e( 'Ana sayfanın boş mu kalacağı, bir menüye mi yönleneceği ya da menüyü mü göstereceği Flux QR Menu ayarlarındadır.', 'flx-menu-theme' ); ?></p>
				<p><a class="button" href="<?php echo esc_url( admin_url( 'admin.php?page=flx-qr-menu-settings' ) ); ?>"><?php esc_html_e( 'QR Menüler › Ayarlar', 'flx-menu-theme' ); ?></a>
					<a class="button" href="<?php echo esc_url( admin_url( 'admin.php?page=flx-qr-menu' ) ); ?>"><?php esc_html_e( 'QR Menüler', 'flx-menu-theme' ); ?></a></p>
			<?php else : ?>
				<div class="notice notice-warning inline"><p>
					<?php esc_html_e( 'Flux QR Menu eklentisi etkin değil. Menüler bu eklentiyle yayınlanır.', 'flx-menu-theme' ); ?>
					<?php if ( defined( 'FLXU_VERSION' ) ) : ?>
						<a href="<?php echo esc_url( admin_url( 'admin.php?page=flx-updater' ) ); ?>"><?php esc_html_e( 'Flux Updater › Paketler\'den kurun.', 'flx-menu-theme' ); ?></a>
					<?php else : ?>
						<?php esc_html_e( 'Önce Flux Updater\'yi (Eklentiler › Yeni ekle › Eklenti yükle) kurup oradan Flux QR Menu\'yü kurun.', 'flx-menu-theme' ); ?>
					<?php endif; ?>
				</p></div>
			<?php endif; ?>
		</div>

		<div class="yf-card" id="guncellemeler">
			<h2><?php esc_html_e( '3. Hazır güncellemeler', 'flx-menu-theme' ); ?></h2>
			<p><?php esc_html_e( 'Tema güncellemesinden sonra canlı sitede tek tıkla uygulanır; tekrar uygulamak güvenlidir.', 'flx-menu-theme' ); ?></p>
			<ul class="yf-mig">
				<?php foreach ( yf_kurulum_migrations_sorted( $done ) as $id => $m ) : ?>
					<li data-id="<?php echo esc_attr( $id ); ?>">
						<strong><?php echo esc_html( $m['title'] ); ?></strong>
						<small><?php echo esc_html( $m['desc'] ); ?></small>
						<?php if ( ! empty( $done[ $id ] ) ) : ?>
							<span class="yf-done">✓ <?php /* translators: %s: date */ echo esc_html( sprintf( __( 'Uygulandı: %s', 'flx-menu-theme' ), wp_date( 'd.m.Y H:i', (int) $done[ $id ] ) ) ); ?></span>
						<?php endif; ?>
						<a class="button<?php echo empty( $done[ $id ] ) && ! in_array( $id, yf_kurulum_tools(), true ) ? ' button-primary' : ''; ?>" href="<?php echo esc_url( yf_kurulum_migrate_url( $id ) ); ?>"><?php echo empty( $done[ $id ] ) ? esc_html__( 'Uygula', 'flx-menu-theme' ) : esc_html__( 'Tekrar uygula', 'flx-menu-theme' ); ?></a>
					</li>
				<?php endforeach; ?>
			</ul>
		</div>
		<p><?php esc_html_e( 'Yazılar, yorumlar, beslemeler ve diğer kapatılan özellikler:', 'flx-menu-theme' ); ?> <a href="<?php echo esc_url( admin_url( 'themes.php?page=yf-ayarlar' ) ); ?>"><?php esc_html_e( 'Görünüm › Tema Ayarları', 'flx-menu-theme' ); ?></a></p>
	</div>
	<?php
}

/** Kurulum yapılmadıysa ya da bekleyen hazır güncelleme varsa yöneticiye bildirim. */
function yf_kurulum_notice() {
	if ( ! current_user_can( 'manage_options' ) ) {
		return;
	}
	$screen = get_current_screen();
	if ( $screen && 'appearance_page_yf-kurulum' === $screen->id ) {
		return;
	}
	$url = admin_url( 'themes.php?page=yf-kurulum' );
	if ( ! get_option( 'yf_kurulum_done' ) ) {
		echo '<div class="notice notice-info"><p><strong>' . esc_html__( 'Flux Menu Theme teması:', 'flx-menu-theme' ) . '</strong> <a href="' . esc_url( $url ) . '">' . esc_html__( 'Görünüm › Site Kurulumu', 'flx-menu-theme' ) . '</a> ' . esc_html__( 'sayfasından temel ayarları yapın.', 'flx-menu-theme' ) . '</p></div>';
		return;
	}
	$n = count( yf_kurulum_pending() );
	if ( $n ) {
		/* translators: %d: number of pending updates */
		echo '<div class="notice notice-warning"><p><strong>' . esc_html__( 'Flux Menu Theme teması:', 'flx-menu-theme' ) . '</strong> ' . esc_html( sprintf( /* translators: %d: number of pending updates */ __( 'Uygulanmayı bekleyen hazır güncelleme: %d.', 'flx-menu-theme' ), $n ) ) . ' <a href="' . esc_url( $url . '#guncellemeler' ) . '">' . esc_html__( 'Site Kurulumu', 'flx-menu-theme' ) . '</a></p></div>';
	}
}
add_action( 'admin_notices', 'yf_kurulum_notice' );
