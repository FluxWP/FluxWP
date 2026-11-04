<?php
/**
 * Ortak Sistem Kontrolü ekranına (tema: Görünüm › Sistem Kontrolü) Flux SEO kontrolleri.
 * Sözleşme: `flx_system_checks` filtresi; dış HTTP isteği yapılmaz.
 *
 * @package FLX_SEO
 */

defined( 'ABSPATH' ) || exit;

add_filter(
	'flx_system_checks',
	function ( $checks ) {
		$g    = 'SEO';
		$item = function ( $id, $label, $status, $message, $url = '', $btn = '' ) use ( $g ) {
			$c = array(
				'id'      => 'flxseo.' . $id,
				'source'  => 'flx-seo',
				'group'   => $g,
				'label'   => $label,
				'status'  => $status,
				'message' => $message,
			);
			if ( $url ) {
				$c['action_url']   = $url;
				$c['action_label'] = $btn ? $btn : 'Aç';
			}
			return $c;
		};

		// SEO isteğe bağlı: kapalıysa hata değil uyarı. Başka bir SEO eklentisi işi üstlendiyse bilgi.
		$label = __( 'SEO çıktısı', 'flx-seo' );
		if ( flxseo_other_seo_plugin() ) {
			$checks[] = $item( 'output', $label, 'info', __( 'Başka bir SEO eklentisi etkin; meta, Open Graph ve schema onun üzerinden. Flux SEO çakışmasın diye kendi çıktısını kapattı.', 'flx-seo' ) );
		} elseif ( flxseo_opt( 'enabled' ) ) {
			$checks[] = $item( 'output', $label, 'ok', __( 'Meta açıklama, Open Graph ve schema her sayfada ekleniyor.', 'flx-seo' ) );
		} else {
			$checks[] = $item( 'output', $label, 'warning', __( 'Kapalı. Google ve sosyal paylaşımlarda sayfa açıklaması, görsel ve kurum bilgisi görünmez. Google\'da bulunmak istiyorsanız açın.', 'flx-seo' ), flxseo_admin_url(), __( 'SEO ayarları', 'flx-seo' ) );
		}

		$sitemaps = function_exists( 'wp_sitemaps_get_server' ) && wp_sitemaps_get_server()->sitemaps_enabled();
		$checks[] = $sitemaps
			? $item( 'sitemap', 'Site haritası', 'ok', home_url( '/wp-sitemap.xml' ) )
			: $item( 'sitemap', 'Site haritası', 'error', 'Site haritası kapalı (arama motorları engelli olabilir ya da başka bir eklenti kapatıyor).', admin_url( 'options-reading.php' ), 'Okuma ayarları' );

		$state = (string) get_option( 'flxseo_cache_rules', '' );
		if ( flxseo_cache_active() ) {
			$checks[] = $item( 'browser-cache', 'Tarayıcı önbelleği', 'ok', '.htaccess kuralları yazılı: görseller 30 gün, CSS/JS/font 1 yıl önbellekte.' );
		} elseif ( 0 === strpos( $state, 'yazilamadi' ) ) {
			$checks[] = $item( 'browser-cache', 'Tarayıcı önbelleği', 'warning', '.htaccess yazılamadı (dosya izni). Görseller her yenilemede yeniden indirilir. Dosyayı yazılabilir yapıp SEO ayarlarını bir kez kaydedin.' );
		} elseif ( empty( $GLOBALS['is_apache'] ) ) {
			$checks[] = $item( 'browser-cache', 'Tarayıcı önbelleği', 'info', 'Sunucu Apache/LiteSpeed değil; önbellek başlıklarını sunucu ayarından verin.' );
		} else {
			$checks[] = $item( 'browser-cache', 'Tarayıcı önbelleği', 'warning', '.htaccess kuralları henüz yok. Yönetim paneline bir kez girildiğinde yazılır; bu sayfayı yenileyin.' );
		}

		$checks[] = has_site_icon() || file_exists( ABSPATH . 'favicon.ico' )
			? $item( 'favicon', 'Site simgesi', 'ok', has_site_icon() ? 'Site simgesi seçili.' : 'Kök dizinde favicon.ico var.' )
			: $item( 'favicon', 'Site simgesi', 'warning', 'Site simgesi yok; Google sonuçlarında boş simge görünür.', admin_url( 'customize.php?autofocus[section]=title_tagline' ), 'Özelleştir' );

		$missing = array();
		foreach ( array( 'phone' => 'telefon', 'email' => 'e-posta', 'street' => 'adres' ) as $k => $label ) {
			if ( '' === trim( (string) flxseo_opt( $k ) ) ) {
				$missing[] = $label;
			}
		}
		$checks[] = $missing
			? $item( 'org', 'Kurum bilgileri', in_array( 'e-posta', $missing, true ) ? 'warning' : 'info', 'Eksik: ' . implode( ', ', $missing ) . '. Google\'daki kurum kartı ve yerel aramalar için doldurun.', flxseo_admin_url( 'kurum' ), 'Kurum ve sosyal' )
			: $item( 'org', 'Kurum bilgileri', 'ok', 'Adres, telefon ve e-posta schema\'da.' );

		$checks[] = flxseo_opt( 'verify_google' )
			? $item( 'gsc', 'Search Console', 'ok', 'Google doğrulama kodu girili.' )
			: $item( 'gsc', 'Search Console', 'info', 'Doğrulama kodu girilmemiş. DNS ile doğruladıysanız sorun yok.', flxseo_admin_url(), 'SEO ayarları' );

		$checks[] = $item( 'mcp', 'Yapay zekâ (MCP)', function_exists( 'flxai_mcp_tools' ) ? 'info' : 'warning', function_exists( 'flxai_mcp_tools' ) ? ( flxseo_mcp_server_on() ? 'MCP sunucusu (Flux AI) açık; Flux SEO araçları kayıtlı.' : 'MCP sunucusu (Flux AI) kapalı; Flux SEO araçları yalnız Flux AI sohbetinde.' ) : 'Flux AI 0.7.0+ kurulu değil; Flux SEO yapay zekâ araçları çalışmıyor.', flxseo_mcp_admin_url(), 'Flux AI › MCP' );

		return $checks;
	},
	20
);
