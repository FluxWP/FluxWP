<?php
/**
 * Site denetimi: PageSpeed Insights / Lighthouse kategorilerinin (Performans, Erişilebilirlik, En iyi uygulamalar,
 * SEO, Ajan uyumu) site içinden yapılabilen kontrolleri + isteğe bağlı PageSpeed Insights API ölçümü.
 * Bulgular önceliğe göre sıralanır (critical, high, medium, low); panel, Sistem Kontrolü, MCP ve Flux AI aynı sonucu kullanır.
 *
 * Kayıt: option `flxseo_audit` (autoload kapalı) = array(
 *   local => array( time, url, ms, findings ), psi => array( mobile|desktop => array( time, url, scores, metrics, findings, lighthouse ) ) ).
 * Filtreler: `flxseo_audit_findings` (birleşik liste), `flxseo_audit_local_checks` (yerel bulgular, HTML ve başlıklarla),
 * `flxseo_psi_categories` (PageSpeed kategorileri).
 *
 * @package flx-seo
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

const FLXSEO_PSI_ENDPOINT = 'https://www.googleapis.com/pagespeedonline/v5/runPagespeed';

/** Öncelik sırası (küçük = önemli). */
function flxseo_audit_priorities() {
	return array(
		'critical' => array( 0, __( 'Kritik', 'flx-seo' ) ),
		'high'     => array( 1, __( 'Yüksek', 'flx-seo' ) ),
		'medium'   => array( 2, __( 'Orta', 'flx-seo' ) ),
		'low'      => array( 3, __( 'Düşük', 'flx-seo' ) ),
	);
}

function flxseo_audit_categories() {
	return array(
		'performance'    => __( 'Performans', 'flx-seo' ),
		'accessibility'  => __( 'Erişilebilirlik', 'flx-seo' ),
		'best-practices' => __( 'En iyi uygulamalar', 'flx-seo' ),
		'seo'            => __( 'SEO', 'flx-seo' ),
		'agentic'        => __( 'Ajan uyumu', 'flx-seo' ),
	);
}

/** Düzeltmeyi kimin yapacağı (panelde etiket). */
function flxseo_audit_owners() {
	return array(
		'theme'   => __( 'Tema / ön ayar', 'flx-seo' ),
		'content' => __( 'İçerik', 'flx-seo' ),
		'server'  => __( 'Sunucu', 'flx-seo' ),
		'flx-seo' => __( 'Flux SEO (tek tık)', 'flx-seo' ),
		'flx-speed' => __( 'Flux Speed (tek tık)', 'flx-seo' ),
		'setting' => __( 'WordPress ayarı', 'flx-seo' ),
	);
}

/**
 * Bilinen bulgular: id => category, title, fix, owner, also (ek kategoriler), fix_id (tek tık düzeltme, flx_system_fixes).
 * Kimlikler Lighthouse denetim kimlikleriyle aynı tutulur; böylece yerel bulgu ile PageSpeed bulgusu birleşir.
 */
function flxseo_audit_catalog() {
	static $c = null;
	if ( null !== $c ) {
		return $c;
	}
	$c = array(
		/* SEO */
		'http-status-code'             => array( 'seo', __( 'Ana sayfa başarılı durum kodu döndürmüyor', 'flx-seo' ), __( 'Sayfa 200 dönmeli. Bakım modu, yönlendirme döngüsü ya da PHP hatası olabilir; Sistem Kontrolü ve hata günlüğüne bakın.', 'flx-seo' ), 'server' ),
		'is-crawlable'                 => array( 'seo', __( 'Sayfa arama motorlarına kapalı', 'flx-seo' ), __( 'Ayarlar › Okuma › "Arama motorlarının siteyi dizine eklemesini engelle" kutusunu kaldırın; sayfanın kendi SEO panelindeki noindex işaretini kapatın.', 'flx-seo' ), 'setting' ),
		'robots-txt'                   => array( 'seo', __( 'robots.txt tüm siteyi engelliyor', 'flx-seo' ), __( 'Kökteki robots.txt dosyasında "User-agent: *" altında "Disallow: /" olmamalı. Dosyayı silin ya da düzeltin; WordPress kendi robots.txt\'sini üretir.', 'flx-seo' ), 'server' ),
		'search-bots-blocked'          => array( 'seo', __( 'robots.txt Bing ya da Yandex tarayıcısını engelliyor', 'flx-seo' ), __( 'Bingbot, YandexBot, DuckDuckBot gibi arama motoru tarayıcıları için "Disallow: /" olmamalı; site yalnız Google\'da görünür. Kökteki robots.txt dosyasını düzeltin.', 'flx-seo' ), 'server' ),
		'robots-sitemap'               => array( 'seo', __( 'robots.txt\'de Sitemap satırı yok', 'flx-seo' ), __( 'Bing ve Yandex sitemap\'i robots.txt\'deki "Sitemap:" satırından bulur. Kökte elle konmuş bir robots.txt varsa silin (Flux SEO satırı ekler) ya da satırı elle ekleyin.', 'flx-seo' ), 'server' ),
		'indexnow'                     => array( 'seo', __( 'IndexNow kapalı', 'flx-seo' ), __( 'IndexNow yeni ve güncellenen sayfaları Bing, Yandex, Seznam ve Naver\'e anında bildirir; Google dışındaki arama motorlarında görünmenin en hızlı yolu. Açmak için tek tık.', 'flx-seo' ), 'flx-seo', array(), 'flxseo_indexnow' ),
		'indexnow-error'               => array( 'seo', __( 'IndexNow bildirimi başarısız', 'flx-seo' ), __( 'Anahtar dosyası (/<anahtar>.txt) dışarıdan açılabilmeli. Önbellek ya da güvenlik eklentisi engelliyorsa istisna ekleyin, sonra SEO › Genel › IndexNow › "Tüm siteyi şimdi bildir".', 'flx-seo' ), 'flx-seo' ),
		'search-verify'                => array( 'seo', __( 'Bing / Yandex doğrulama kodu girilmemiş', 'flx-seo' ), __( 'Site Bing Webmaster Tools ve Yandex Webmaster\'a eklenmemiş olabilir; eklenmezse bu arama motorlarında geç ya da hiç görünmez. Bing\'de "Google Search Console\'dan içe aktar" ile kod gerekmez; o yolla eklediyseniz yok sayın. Kodlar SEO › Genel › Arama motorları\'nda.', 'flx-seo' ), 'setting' ),
		'php-unpatched'                => array( 'best-practices', __( 'PHP sürümü güvenlik yaması almamış', 'flx-seo' ), __( 'Sunucudaki PHP bilinen bir açığa karşı yamasız ya da artık desteklenmiyor. Hosting panelinden PHP\'yi güncel yama sürümüne yükseltin.', 'flx-seo' ), 'server' ),
		'php-upload-progress'          => array( 'best-practices', __( 'session.upload_progress açık', 'flx-seo' ), __( 'WordPress bu PHP özelliğini kullanmaz; PHP2Shell gibi açıklarda istismar yolu olur. php.ini ya da hosting panelinde session.upload_progress.enabled = Off yapın.', 'flx-seo' ), 'server' ),
		'document-title'               => array( 'seo', __( 'Sayfa başlığı (<title>) yok', 'flx-seo' ), __( 'Tema add_theme_support( \'title-tag\' ) desteklemeli; SEO › Genel\'de ana sayfa başlığını doldurun.', 'flx-seo' ), 'theme', array( 'accessibility' ) ),
		'title-length'                 => array( 'seo', __( 'Sayfa başlığı uzun', 'flx-seo' ), __( 'Başlığı 60 karakter civarında tutun; Google sonrasını keser.', 'flx-seo' ), 'content' ),
		'meta-description'             => array( 'seo', __( 'Meta açıklama yok', 'flx-seo' ), __( 'SEO çıktısını açın ve ana sayfa açıklamasını (150–160 karakter) yazın.', 'flx-seo' ), 'flx-seo' ),
		'canonical'                    => array( 'seo', __( 'Canonical bağlantısı eksik ya da birden fazla', 'flx-seo' ), __( 'Her sayfada tek bir rel=canonical olmalı. Başka bir eklenti ya da tema da ekliyorsa birini kapatın.', 'flx-seo' ), 'theme' ),
		'hreflang'                     => array( 'seo', __( 'Çok dilli sitede hreflang yok', 'flx-seo' ), __( 'Flux Lang etkinse dil sürümleri hreflang ile bağlanmalı; Flux Lang ayarlarında dil eşleştirmelerini kontrol edin.', 'flx-seo' ), 'theme' ),
		'heading-h1'                   => array( 'seo', __( 'Ana başlık (H1) yok ya da birden fazla', 'flx-seo' ), __( 'Her sayfada konuyu anlatan tek bir H1 olsun; ara başlıklar H2/H3.', 'flx-seo' ), 'content', array( 'accessibility' ) ),
		'structured-data'              => array( 'seo', __( 'Yapılandırılmış veri (JSON-LD) yok ya da bozuk', 'flx-seo' ), __( 'Flux SEO çıktısı açıkken Organization, WebSite ve Breadcrumb schema eklenir. Bozuk JSON-LD üreten blok ya da eklentiyi bulun.', 'flx-seo' ), 'flx-seo', array( 'agentic' ) ),
		'link-text'                    => array( 'seo', __( 'Bağlantı metinleri açıklayıcı değil', 'flx-seo' ), __( '"Tıklayın", "devamı", "buraya" yerine hedefi anlatan metin yazın (ör. "Bağış seçeneklerini görün").', 'flx-seo' ), 'content', array( 'accessibility', 'agentic' ) ),
		'duplicate-titles'             => array( 'seo', __( 'Aynı başlıkta birden fazla yayın var', 'flx-seo' ), __( 'Kopya sayfayı (çoğunlukla adresi -2 ile biten) silin ya da asıl sayfaya yönlendirin; gerekiyorsa başlıkları ayırın. Aynı metinli bağlantılar farklı adreslere gidince "Aynı bağlantılar aynı amaca sahip" denetimi de düşer.', 'flx-seo' ), 'content', array( 'accessibility' ) ),
		'generator'                    => array( 'best-practices', __( 'WordPress sürümü sayfada görünüyor', 'flx-seo' ), __( 'meta generator etiketi sürümü ele verir. Tema remove_action( \'wp_head\', \'wp_generator\' ) yapmalı; Flux SEO güvenlik başlıkları açıkken kaldırılır.', 'flx-seo' ), 'flx-seo', array(), 'flxseo_headers' ),

		/* Erişilebilirlik */
		'meta-viewport'                => array( 'accessibility', __( 'Yakınlaştırma engellenmiş (user-scalable=no / maximum-scale)', 'flx-seo' ), __( 'Viewport etiketinden user-scalable=no ve maximum-scale=1 kaldırın: <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">. Az gören kullanıcılar sayfayı büyütemiyor; Erişilebilirlik puanı doğrudan düşer.', 'flx-seo' ), 'theme' ),
		'viewport'                     => array( 'performance', __( 'Viewport etiketi yok', 'flx-seo' ), __( 'Tema <head> içine <meta name="viewport" content="width=device-width, initial-scale=1"> eklemeli; yoksa mobilde sayfa masaüstü genişliğinde açılır.', 'flx-seo' ), 'theme', array( 'seo' ) ),
		'aria-required-children'       => array( 'accessibility', __( 'ARIA rolü gerekli alt öğeleri içermiyor', 'flx-seo' ), __( 'Ör. role="tablist" yalnız role="tab" öğeleri içermeli. <ol role="tablist"><li><button role="tab"> yerine <div role="tablist"><button role="tab"> kullanın ya da li öğelerine role="presentation" verin. Erişilebilirlik ağacı bozulur; Lighthouse "Ajan uyumu" puanını da düşürür.', 'flx-seo' ), 'theme', array( 'agentic' ) ),
		'aria-required-parent'         => array( 'accessibility', __( 'ARIA rolü gerekli üst öğenin içinde değil', 'flx-seo' ), __( 'role="tab" doğrudan role="tablist" içinde, role="option" role="listbox" içinde olmalı. Aradaki li/div öğelerini kaldırın ya da role="presentation" verin.', 'flx-seo' ), 'theme', array( 'agentic' ) ),
		'listitem'                     => array( 'accessibility', __( 'Liste öğeleri (<li>) liste içinde değil', 'flx-seo' ), __( '<li> yalnız <ul>, <ol> ya da <menu> içinde olmalı ve üst listeye liste dışı bir role verilmemeli (ör. <ol role="tablist">).', 'flx-seo' ), 'theme' ),
		'image-alt'                    => array( 'accessibility', __( 'Görsellerde alt metin yok', 'flx-seo' ), __( 'Her anlamlı görsele içeriği anlatan alt metin yazın; süs görselleri alt="" alsın. Medya kütüphanesinde alt metni doldurun ya da SEO › Genel › Görseller seçeneğini açın.', 'flx-seo' ), 'content', array( 'seo', 'agentic' ) ),
		'link-name'                    => array( 'accessibility', __( 'Adı olmayan bağlantılar var', 'flx-seo' ), __( 'Yalnız simge içeren bağlantılara aria-label ya da görünmez metin (screen-reader-text) ekleyin.', 'flx-seo' ), 'theme', array( 'agentic' ) ),
		'button-name'                  => array( 'accessibility', __( 'Adı olmayan düğmeler var', 'flx-seo' ), __( 'Simge düğmelerine aria-label verin (ör. "Menüyü aç"). Ajanlar ve ekran okuyucular düğmenin ne yaptığını bilemez.', 'flx-seo' ), 'theme', array( 'agentic' ) ),
		'label'                        => array( 'accessibility', __( 'Etiketi olmayan form alanları var', 'flx-seo' ), __( 'Her alanın <label for> ya da aria-label ile bir adı olmalı; placeholder etiket yerine geçmez.', 'flx-seo' ), 'theme', array( 'agentic' ) ),
		'html-has-lang'                => array( 'accessibility', __( '<html> etiketinde dil (lang) yok', 'flx-seo' ), __( 'Tema <html <?php language_attributes(); ?>> kullanmalı.', 'flx-seo' ), 'theme', array( 'seo' ) ),
		'duplicate-id-aria'            => array( 'accessibility', __( 'Yinelenen ya da bulunmayan id başvuruları', 'flx-seo' ), __( 'Aynı id iki öğede olmamalı; aria-controls, aria-labelledby ve label for var olan bir id\'yi göstermeli.', 'flx-seo' ), 'theme', array( 'agentic' ) ),
		'heading-order'                => array( 'accessibility', __( 'Başlık sırası atlıyor', 'flx-seo' ), __( 'Başlıklar sırayla inmeli (H2 → H3); görünüm için seviye atlamayın, CSS ile boyutlandırın.', 'flx-seo' ), 'content' ),
		'tabindex'                     => array( 'accessibility', __( 'tabindex değeri 0\'dan büyük', 'flx-seo' ), __( 'tabindex="1" gibi değerler klavye sırasını bozar; 0 ya da -1 kullanın.', 'flx-seo' ), 'theme' ),
		'identical-links-same-purpose' => array( 'accessibility', __( 'Aynı metinli bağlantılar farklı adreslere gidiyor', 'flx-seo' ), __( 'Aynı adı taşıyan bağlantılar aynı sayfaya gitmeli. Kopya sayfayı kaldırın ya da bağlantı metinlerini ayırın.', 'flx-seo' ), 'content', array( 'seo' ) ),
		'frame-title'                  => array( 'accessibility', __( 'Başlıksız iframe', 'flx-seo' ), __( 'Her <iframe> title almalı (ör. "Konum haritası").', 'flx-seo' ), 'content' ),
		'landmark-one-main'            => array( 'accessibility', __( 'Sayfada <main> bölgesi yok', 'flx-seo' ), __( 'Ana içerik <main> içinde olmalı; ekran okuyucu ve ajanlar ana içeriği böyle bulur.', 'flx-seo' ), 'theme', array( 'agentic' ) ),
		'color-contrast'               => array( 'accessibility', __( 'Metin/zemin karşıtlığı yetersiz', 'flx-seo' ), __( 'Normal metin için en az 4.5:1, büyük metin için 3:1 karşıtlık. Tema paletindeki soluk renkleri koyulaştırın.', 'flx-seo' ), 'theme' ),

		/* Performans */
		'render-blocking-insight'      => array( 'performance', __( 'Oluşturmayı engelleyen istekler', 'flx-seo' ), __( 'İlk ekran için gereken kritik CSS\'i satır içine alın, gerisini media="print" onload ile ya da sayfa sonunda yükleyin; JS\'e defer verin. LiteSpeed Cache › Page Optimization › CSS/JS ayarları ya da temanın kendi birleştirilmiş CSS\'i.', 'flx-seo' ), 'theme' ),
		'unused-css-rules'             => array( 'performance', __( 'Kullanılmayan CSS', 'flx-seo' ), __( 'Tek büyük CSS dosyasını sayfa türüne göre bölün ya da kullanılmayan kuralları ayıklayın (LiteSpeed "Remove Unused CSS"). Kazanç çoğunlukla LCP\'ye yansır.', 'flx-seo' ), 'theme' ),
		'unminified-javascript'        => array( 'performance', __( 'Sıkıştırılmamış (minify edilmemiş) JavaScript', 'flx-seo' ), __( 'Tema/eklenti JS dosyalarının .min sürümünü yayınlayın ya da LiteSpeed "JS Minify"ı açın.', 'flx-seo' ), 'theme' ),
		'unminified-css'               => array( 'performance', __( 'Sıkıştırılmamış CSS', 'flx-seo' ), __( 'CSS\'i minify edin (LiteSpeed "CSS Minify" ya da derleme adımı).', 'flx-seo' ), 'theme' ),
		'non-composited-animations'    => array( 'performance', __( 'GPU\'da çalışmayan animasyonlar', 'flx-seo' ), __( 'Animasyonları transform ve opacity ile yapın; stroke-dashoffset, top/left, width gibi özellikler her karede yeniden çizim yapar. prefers-reduced-motion\'da durdurun.', 'flx-seo' ), 'theme' ),
		'dom-size-insight'             => array( 'performance', __( 'DOM çok büyük', 'flx-seo' ), __( '1.500 öğenin altını hedefleyin. Kayan şeritlerdeki (marquee) kopyaları azaltın, gizli menüleri ve uzun listeleri ihtiyaç olunca oluşturun, iç içe sarmalayıcıları sadeleştirin.', 'flx-seo' ), 'theme' ),
		'lcp-breakdown-insight'        => array( 'performance', __( 'En büyük içerik (LCP) geç çiziliyor', 'flx-seo' ), __( 'LCP öğesi metinse yazı tipini preload edip font-display: swap kullanın ve giriş animasyonunu ilk karede göstermeyin; görselse fetchpriority="high" verin, lazy load etmeyin.', 'flx-seo' ), 'theme' ),
		'cls-culprits-insight'         => array( 'performance', __( 'Yerleşim kayması (CLS) kaynakları', 'flx-seo' ), __( 'Görsellere width/height, sonradan gelen bloklara sabit yükseklik verin; web fontları için size-adjust/fallback metrikleri kullanın; absolute konumlu animasyonlu katmanlar yerleşimi itmesin.', 'flx-seo' ), 'theme', array( 'agentic' ) ),
		'font-display-insight'         => array( 'performance', __( 'Yazı tipleri metni geciktiriyor', 'flx-seo' ), __( '@font-face içinde font-display: swap kullanın, ilk ekranda kullanılan 1-2 yazı tipini <link rel="preload" as="font" crossorigin> ile önden yükleyin, kullanılmayan ağırlıkları kaldırın.', 'flx-seo' ), 'theme' ),
		'network-dependency-tree-insight' => array( 'performance', __( 'Kritik istek zinciri uzun', 'flx-seo' ), __( 'CSS içinden yüklenen fontlar zinciri uzatır; ilk ekran fontlarını preload edin, zincirdeki gereksiz istekleri (ör. guest.vary.php) ertelenmiş yükleyin.', 'flx-seo' ), 'theme' ),
		'forced-reflow-insight'        => array( 'performance', __( 'Zorunlu yeniden yerleşim (forced reflow)', 'flx-seo' ), __( 'JS\'te stil değiştirdikten sonra offsetWidth/getBoundingClientRect okumayın; okumaları önce, yazmaları sonra requestAnimationFrame içinde yapın.', 'flx-seo' ), 'theme' ),
		'long-tasks'                   => array( 'performance', __( 'Uzun ana iş parçacığı görevleri', 'flx-seo' ), __( 'Açılışta çalışan JS\'i bölün, görünür olmayan bölümlerin işini IntersectionObserver ile erteleyin.', 'flx-seo' ), 'theme' ),
		'image-delivery-insight'       => array( 'performance', __( 'Görseller büyük ya da eski biçimde', 'flx-seo' ), __( 'WebP/AVIF kullanın, görünen boyuttan büyük görsel göndermeyin (srcset), görsel sıkıştırmayı açın.', 'flx-seo' ), 'content' ),
		'unsized-images'               => array( 'performance', __( 'Genişlik/yükseklik vermeyen görseller', 'flx-seo' ), __( '<img> etiketlerine width ve height verin; tarayıcı yer ayırır, sayfa kaymaz.', 'flx-seo' ), 'theme', array( 'agentic' ) ),
		'lcp-lazy-loaded'              => array( 'performance', __( 'İlk ekrandaki görsel lazy yükleniyor', 'flx-seo' ), __( 'Sayfanın ilk görseline loading="lazy" vermeyin, fetchpriority="high" verin. LiteSpeed lazy load açıksa ilk görseli hariç tutun (data-no-lazy ya da "Lazy Load Image Excludes").', 'flx-seo' ), 'theme' ),
		'server-response-time'         => array( 'performance', __( 'Sunucu yanıtı yavaş', 'flx-seo' ), __( 'Sayfa önbelleğini (LiteSpeed Cache) açın; Flux Speed › yavaş eklentiler ve cron raporuna bakın.', 'flx-seo' ), 'server' ),
		'uses-text-compression'        => array( 'performance', __( 'Metin sıkıştırma (gzip/brotli) yok', 'flx-seo' ), __( 'Sunucuda gzip ya da brotli açın (Hostinger/LiteSpeed\'de varsayılan açıktır; CDN ayarını kontrol edin).', 'flx-seo' ), 'server' ),
		'document-size'                => array( 'performance', __( 'HTML belgesi büyük', 'flx-seo' ), __( 'Satır içi SVG ikonları sprite\'a, büyük satır içi CSS/JSON\'u dosyaya taşıyın.', 'flx-seo' ), 'theme' ),
		'emoji-script'                 => array( 'performance', __( 'WordPress emoji betiği yükleniyor', 'flx-seo' ), __( 'Modern tarayıcılar emojiyi kendisi çizer; tema print_emoji_detection_script\'i kaldırabilir.', 'flx-seo' ), 'theme' ),
		'largest-contentful-paint'     => array( 'performance', __( 'Largest Contentful Paint (LCP) yavaş', 'flx-seo' ), __( 'Hedef 2,5 sn altı. Engelleyen CSS\'i azaltın, LCP yazı tipini/görselini önden yükleyin, sunucu önbelleğini açın.', 'flx-seo' ), 'theme' ),
		'cumulative-layout-shift'      => array( 'performance', __( 'Cumulative Layout Shift (CLS) yüksek', 'flx-seo' ), __( 'Hedef 0,1 altı. Görsellere boyut verin, fontların kaymasını önleyin, sonradan eklenen öğelere yer ayırın.', 'flx-seo' ), 'theme', array( 'agentic' ) ),
		'total-blocking-time'          => array( 'performance', __( 'Total Blocking Time (TBT) yüksek', 'flx-seo' ), __( 'Hedef 200 ms altı. Açılış JS\'ini azaltın, üçüncü taraf betikleri erteleyin.', 'flx-seo' ), 'theme' ),
		'first-contentful-paint'       => array( 'performance', __( 'First Contentful Paint (FCP) yavaş', 'flx-seo' ), __( 'Hedef 1,8 sn altı. Engelleyen CSS/JS ve sunucu yanıt süresini azaltın.', 'flx-seo' ), 'theme' ),
		'speed-index'                  => array( 'performance', __( 'Speed Index yavaş', 'flx-seo' ), __( 'İlk ekranın hızlı dolması için kritik CSS ve yazı tiplerini önceliklendirin, giriş animasyonlarını kısaltın.', 'flx-seo' ), 'theme' ),

		/* En iyi uygulamalar / güvenlik */
		'is-on-https'                  => array( 'best-practices', __( 'Site HTTPS değil ya da karışık içerik var', 'flx-seo' ), __( 'Ayarlar › Genel\'de adresleri https:// yapın, http:// ile yüklenen görsel/betikleri düzeltin.', 'flx-seo' ), 'setting', array( 'seo' ) ),
		'has-hsts'                     => array( 'best-practices', __( 'HSTS politikası zayıf', 'flx-seo' ), __( 'Strict-Transport-Security: max-age=31536000; includeSubDomains. Tüm alt alan adları HTTPS ise "preload" da ekleyip hstspreload.org\'a başvurabilirsiniz.', 'flx-seo' ), 'flx-seo', array(), 'flxseo_hsts' ),
		'csp-xss'                      => array( 'best-practices', __( 'İçerik Güvenlik Politikası (CSP) yok ya da eksik', 'flx-seo' ), __( 'Flux SEO temel CSP\'yi (object-src \'none\'; base-uri \'self\'; frame-ancestors \'self\') ekleyebilir. Tam script-src politikası WordPress\'te eklentileri bozabilir; önce Content-Security-Policy-Report-Only ile deneyin.', 'flx-seo' ), 'flx-seo', array(), 'flxseo_headers' ),
		'clickjacking-mitigation'      => array( 'best-practices', __( 'Tıklama korsanlığına (clickjacking) karşı koruma yok', 'flx-seo' ), __( 'X-Frame-Options: SAMEORIGIN ve CSP frame-ancestors \'self\' gönderin. Siteniz başka sitelerde iframe ile gösteriliyorsa açmayın.', 'flx-seo' ), 'flx-seo', array(), 'flxseo_headers' ),
		'origin-isolation'             => array( 'best-practices', __( 'Pencere yalıtımı (COOP) yok', 'flx-seo' ), __( 'Cross-Origin-Opener-Policy: same-origin-allow-popups gönderin; ödeme ve giriş açılır pencereleri çalışmaya devam eder.', 'flx-seo' ), 'flx-seo', array(), 'flxseo_headers' ),
		'trusted-types-xss'            => array( 'best-practices', __( 'Trusted Types tanımlı değil', 'flx-seo' ), __( 'DOM tabanlı XSS\'e karşı ileri seviye koruma. WordPress eklentilerinin çoğu henüz uyumlu değil; yalnız özel temalı, az eklentili sitelerde Report-Only ile deneyin.', 'flx-seo' ), 'server' ),
		'x-content-type-options'       => array( 'best-practices', __( 'X-Content-Type-Options başlığı yok', 'flx-seo' ), __( 'X-Content-Type-Options: nosniff. Flux SEO bunu kendiliğinden gönderir; sunucu ya da önbellek eklentisi siliyor olabilir.', 'flx-seo' ), 'server' ),
		'doctype'                      => array( 'best-practices', __( 'HTML doctype ya da charset eksik', 'flx-seo' ), __( 'Sayfa <!DOCTYPE html> ile başlamalı ve <meta charset="UTF-8"> ilk satırlarda olmalı.', 'flx-seo' ), 'theme' ),

		/* Ajan uyumu */
		'llms-txt'                     => array( 'agentic', __( 'llms.txt yok', 'flx-seo' ), __( 'Yapay zekâ ajanları siteyi /llms.txt ile özetler. Flux SEO site adı, açıklama, menü sayfaları ve son içeriklerden kendiliğinden üretir; açmak için tek tık.', 'flx-seo' ), 'flx-seo', array(), 'flxseo_llms' ),
		'llms-txt-format'              => array( 'agentic', __( 'llms.txt önerilen biçimde değil', 'flx-seo' ), __( 'İlk satır "# Site adı", ardından "> kısa özet" ve [başlık](adres) biçiminde bağlantı listeleri olmalı. Kökte elle konmuş eski bir dosya varsa silin; Flux SEO üretir.', 'flx-seo' ), 'flx-seo', array(), 'flxseo_llms' ),
		'webmcp-forms'                 => array( 'agentic', __( 'Formlar WebMCP aracı olarak tanımlı değil', 'flx-seo' ), __( 'WebMCP, tarayıcıdaki ajanların formları araç olarak çağırmasını sağlar (form toolname/tooldescription). Flux SEO arama formuna kendiliğinden ekler; kendi formlarınıza da ekleyin.', 'flx-seo' ), 'flx-seo', array(), 'flxseo_webmcp' ),
		'ai-crawlers-blocked'          => array( 'agentic', __( 'robots.txt yapay zekâ tarayıcılarını engelliyor', 'flx-seo' ), __( 'GPTBot, ClaudeBot, Google-Extended, PerplexityBot gibi tarayıcılar engelliyse içeriğiniz yapay zekâ yanıtlarında görünmez. Bilerek engellemediyseniz robots.txt\'den kaldırın.', 'flx-seo' ), 'server' ),
	);
	return $c;
}

/**
 * Bulgu nesnesi. Katalogdaki metinler kullanılır; $extra ile detail, items, priority ve title ezilebilir.
 */
function flxseo_audit_finding( $id, $priority, $items = array(), $detail = '', $source = 'local' ) {
	$cat = flxseo_audit_catalog()[ $id ] ?? null;
	return array(
		'id'       => $id,
		'category' => $cat ? $cat[0] : 'seo',
		'also'     => $cat[4] ?? array(),
		'priority' => $priority,
		'title'    => $cat ? $cat[1] : $id,
		'detail'   => $detail,
		'fix'      => $cat ? $cat[2] : '',
		'owner'    => $cat ? $cat[3] : '',
		'fix_id'   => $cat[5] ?? '',
		'items'    => array_slice( array_values( array_unique( array_filter( array_map( 'strval', (array) $items ) ) ) ), 0, 8 ),
		'sources'  => array( $source ),
	);
}

/* ---------------------------------------------------------------------------
 * Yerel denetim
 * ------------------------------------------------------------------------- */

/** Sayfayı sunucu içinden ister (önbellek atlanmaz; ziyaretçinin gördüğü sayfa). */
function flxseo_audit_fetch( $url ) {
	$t0 = microtime( true );
	$r  = wp_remote_get(
		$url,
		array(
			'timeout'     => 20,
			'redirection' => 3,
			'sslverify'   => false,
			'user-agent'  => 'Mozilla/5.0 (compatible; FluxSEO-Audit/' . FLXSEO_VERSION . '; +' . home_url( '/' ) . ')',
			'headers'     => array( 'Accept' => 'text/html', 'Accept-Encoding' => 'gzip, deflate' ),
			'decompress'  => true,
		)
	);
	if ( is_wp_error( $r ) ) {
		return $r;
	}
	$headers = array();
	foreach ( (array) wp_remote_retrieve_headers( $r )->getAll() as $k => $v ) {
		$headers[ strtolower( $k ) ] = is_array( $v ) ? implode( ', ', $v ) : (string) $v;
	}
	return array(
		'code'    => (int) wp_remote_retrieve_response_code( $r ),
		'headers' => $headers,
		'body'    => (string) wp_remote_retrieve_body( $r ),
		'ms'      => (int) round( ( microtime( true ) - $t0 ) * 1000 ),
	);
}

/** Öğenin açılış etiketi (kısaltılmış), panelde ve MCP'de gösterilir. */
function flxseo_audit_snippet( DOMElement $el, $text = true ) {
	$s = '<' . $el->nodeName;
	foreach ( $el->attributes as $a ) {
		$v  = mb_substr( $a->value, 0, 60 );
		$s .= ' ' . $a->name . ( '' === $a->value ? '' : '="' . $v . '"' );
	}
	$s .= '>';
	if ( $text ) {
		$t = trim( preg_replace( '/\s+/u', ' ', $el->textContent ) );
		if ( '' !== $t ) {
			$s .= ' ' . mb_substr( $t, 0, 60 );
		}
	}
	return mb_substr( $s, 0, 220 );
}

/** Açık ya da örtük ARIA rolü. Rolsüz kap (div, span…) için ''. */
function flxseo_audit_role( DOMElement $el ) {
	$r = trim( strtolower( $el->getAttribute( 'role' ) ) );
	if ( '' !== $r ) {
		return strtok( $r, ' ' );
	}
	$map = array(
		'li'       => 'listitem',
		'ul'       => 'list',
		'ol'       => 'list',
		'menu'     => 'list',
		'button'   => 'button',
		'nav'      => 'navigation',
		'main'     => 'main',
		'table'    => 'table',
		'tr'       => 'row',
		'td'       => 'cell',
		'th'       => 'columnheader',
		'option'   => 'option',
		'select'   => 'combobox',
		'textarea' => 'textbox',
		'img'      => 'img',
		'h1'       => 'heading',
		'h2'       => 'heading',
		'h3'       => 'heading',
		'h4'       => 'heading',
		'h5'       => 'heading',
		'h6'       => 'heading',
		'p'        => 'paragraph',
		'article'  => 'article',
		'form'     => 'form',
	);
	$n = strtolower( $el->nodeName );
	if ( 'a' === $n ) {
		return $el->hasAttribute( 'href' ) ? 'link' : '';
	}
	if ( 'input' === $n ) {
		return 'hidden' === strtolower( $el->getAttribute( 'type' ) ) ? 'none' : 'textbox';
	}
	return $map[ $n ] ?? '';
}

/** Ad hesabı (kaba): görünür metin, aria-label, aria-labelledby, title, alt. */
function flxseo_audit_has_name( DOMElement $el, DOMXPath $xp ) {
	if ( '' !== trim( $el->getAttribute( 'aria-label' ) ) || '' !== trim( $el->getAttribute( 'title' ) ) ) {
		return true;
	}
	foreach ( preg_split( '/\s+/', trim( $el->getAttribute( 'aria-labelledby' ) ) ) as $id ) {
		if ( $id && $xp->query( '//*[@id="' . str_replace( '"', '', $id ) . '"]' )->length ) {
			return true;
		}
	}
	if ( '' !== trim( preg_replace( '/[\s\x{00a0}\x{200b}]+/u', '', $el->textContent ) ) ) {
		return true;
	}
	if ( $xp->query( './/img[normalize-space(@alt)!=""] | .//svg[title] | .//*[@aria-label!=""]', $el )->length ) {
		return true;
	}
	if ( 'input' === strtolower( $el->nodeName ) && '' !== trim( $el->getAttribute( 'value' ) ) ) {
		return true;
	}
	return false;
}

/** Erişilebilirlik ağacında bir öğenin "sahip olduğu" alt rolleri (rolsüz kaplar atlanır). */
function flxseo_audit_owned_roles( DOMElement $el, $depth = 0 ) {
	$roles = array();
	foreach ( $el->childNodes as $ch ) {
		if ( ! $ch instanceof DOMElement || in_array( strtolower( $ch->nodeName ), array( 'script', 'template', 'style' ), true ) || 'true' === $ch->getAttribute( 'aria-hidden' ) ) {
			continue;
		}
		$r = flxseo_audit_role( $ch );
		if ( '' === $r || in_array( $r, array( 'none', 'presentation', 'generic' ), true ) ) {
			if ( $depth < 6 ) {
				$roles = array_merge( $roles, flxseo_audit_owned_roles( $ch, $depth + 1 ) );
			}
			continue;
		}
		$roles[] = array( $r, $ch );
	}
	return $roles;
}

/** En yakın rollü üst öğe. */
function flxseo_audit_parent_role( DOMElement $el ) {
	$p = $el->parentNode;
	while ( $p instanceof DOMElement ) {
		$r = flxseo_audit_role( $p );
		if ( '' !== $r && ! in_array( $r, array( 'none', 'presentation', 'generic' ), true ) ) {
			return $r;
		}
		$p = $p->parentNode;
	}
	return '';
}

/**
 * HTML ve yanıt başlıklarından bulgular.
 *
 * @param array $page flxseo_audit_fetch sonucu.
 */
/**
 * Görsel lazy mi yükleniyor? loading="lazy" ya da lazy load eklentisi işareti (lazy/lazyload sınıfı, data-src).
 * "skip-lazy", "no-lazyload" sınıfları ve data-no-lazy hariç tutma işaretidir, lazy sayılmaz.
 */
function flxseo_audit_img_lazy( DOMElement $img ) {
	if ( 'lazy' === strtolower( $img->getAttribute( 'loading' ) ) ) {
		return true;
	}
	$cls = preg_split( '/\s+/', strtolower( $img->getAttribute( 'class' ) ), -1, PREG_SPLIT_NO_EMPTY );
	if ( $img->hasAttribute( 'data-no-lazy' ) || array_intersect( $cls, array( 'skip-lazy', 'no-lazy', 'no-lazyload', 'nolazy' ) ) ) {
		return false;
	}
	return (bool) array_intersect( $cls, array( 'lazy', 'lazyload', 'lazyloading', 'litespeed-lazy', 'lazyloaded' ) ) || $img->hasAttribute( 'data-src' ) || $img->hasAttribute( 'data-lazy-src' );
}

function flxseo_audit_analyze( array $page, $url ) {
	$f    = array();
	$html = $page['body'];
	$h    = $page['headers'];
	$add  = function ( $id, $prio, $items = array(), $detail = '' ) use ( &$f ) {
		$f[] = flxseo_audit_finding( $id, $prio, $items, $detail );
	};

	if ( 200 !== $page['code'] ) {
		/* translators: %d: HTTP durum kodu */
		$add( 'http-status-code', 'critical', array(), sprintf( __( 'HTTP %d döndü.', 'flx-seo' ), $page['code'] ) );
		if ( '' === $html ) {
			return $f;
		}
	}

	libxml_use_internal_errors( true );
	$doc = new DOMDocument();
	$doc->loadHTML( '<?xml encoding="UTF-8">' . $html, LIBXML_NOWARNING | LIBXML_NOERROR | LIBXML_COMPACT );
	libxml_clear_errors();
	$xp = new DOMXPath( $doc );
	// <template> içeriği tarayıcıda çizilmez (ör. WooCommerce mini sepet şablonları); denetime girmesin.
	foreach ( iterator_to_array( $xp->query( '//template' ) ) as $tpl ) {
		if ( $tpl->parentNode ) {
			$tpl->parentNode->removeChild( $tpl );
		}
	}

	/* --- SEO --- */
	$robots_meta = strtolower( (string) ( $xp->evaluate( 'string(//meta[translate(@name,"ROBTS","robts")="robots"]/@content)' ) ) );
	if ( ! get_option( 'blog_public' ) ) {
		$add( 'is-crawlable', 'critical', array( __( 'Ayarlar › Okuma: arama motorları engelli', 'flx-seo' ) ) );
	} elseif ( false !== strpos( $robots_meta, 'noindex' ) || false !== stripos( $h['x-robots-tag'] ?? '', 'noindex' ) ) {
		$add( 'is-crawlable', 'critical', array( 'robots: ' . $robots_meta . ' ' . ( $h['x-robots-tag'] ?? '' ) ) );
	}
	$title = trim( (string) $xp->evaluate( 'string(//head/title)' ) );
	if ( '' === $title ) {
		$add( 'document-title', 'critical' );
	} elseif ( mb_strlen( $title ) > 65 ) {
		/* translators: %d: karakter sayısı */
		$add( 'title-length', 'low', array( $title ), sprintf( __( '%d karakter.', 'flx-seo' ), mb_strlen( $title ) ) );
	}
	if ( '' === trim( (string) $xp->evaluate( 'string(//meta[translate(@name,"DESCRIPTION","description")="description"]/@content)' ) ) ) {
		$add( 'meta-description', 'high' );
	}
	$canon = $xp->query( '//link[translate(@rel,"CANONICAL","canonical")="canonical"]' );
	if ( 0 === $canon->length ) {
		$add( 'canonical', 'medium' );
	} elseif ( $canon->length > 1 ) {
		$items = array();
		foreach ( $canon as $c ) {
			$items[] = $c->getAttribute( 'href' );
		}
		$add( 'canonical', 'high', $items );
	}
	if ( function_exists( 'flxl_languages' ) || defined( 'FLXL_VERSION' ) ) {
		if ( 0 === $xp->query( '//link[@hreflang]' )->length ) {
			$add( 'hreflang', 'medium' );
		}
	}
	$h1 = $xp->query( '//h1' );
	if ( 0 === $h1->length ) {
		$add( 'heading-h1', 'medium' );
	} elseif ( $h1->length > 1 ) {
		$items = array();
		foreach ( $h1 as $e ) {
			$items[] = flxseo_audit_snippet( $e );
		}
		/* translators: %d: H1 sayısı */
		$add( 'heading-h1', 'low', $items, sprintf( __( '%d adet H1 var.', 'flx-seo' ), $h1->length ) );
	}
	$ld_ok = 0;
	$ld_bad = array();
	foreach ( $xp->query( '//script[@type="application/ld+json"]' ) as $s ) {
		json_decode( trim( $s->textContent ) );
		if ( JSON_ERROR_NONE === json_last_error() ) {
			++$ld_ok;
		} else {
			$ld_bad[] = mb_substr( trim( $s->textContent ), 0, 120 );
		}
	}
	if ( $ld_bad ) {
		$add( 'structured-data', 'high', $ld_bad, __( 'JSON-LD ayrıştırılamadı.', 'flx-seo' ) );
	} elseif ( ! $ld_ok ) {
		$add( 'structured-data', 'medium' );
	}
	if ( preg_match( '/<meta[^>]+name=["\']generator["\'][^>]+WordPress\s*[\d.]+/i', $html, $m ) ) {
		$add( 'generator', 'low', array( $m[0] ) );
	}

	/* --- Bağlantılar: ad, açıklayıcı metin, aynı metin farklı hedef --- */
	$generic  = array( 'tıklayın', 'tıkla', 'buraya', 'burada', 'devamı', 'devamını oku', 'daha fazla', 'click here', 'here', 'read more', 'more', 'learn more', 'link' );
	$noname   = array();
	$vague    = array();
	$by_text  = array();
	$home     = untrailingslashit( home_url() );
	foreach ( $xp->query( '//a[@href]' ) as $a ) {
		if ( 'true' === $a->getAttribute( 'aria-hidden' ) ) {
			continue;
		}
		if ( ! flxseo_audit_has_name( $a, $xp ) ) {
			$noname[] = flxseo_audit_snippet( $a, false );
			continue;
		}
		$text = mb_strtolower( trim( preg_replace( '/\s+/u', ' ', '' !== trim( $a->getAttribute( 'aria-label' ) ) ? $a->getAttribute( 'aria-label' ) : $a->textContent ) ) );
		if ( in_array( trim( $text, ' .…›»→' ), $generic, true ) ) {
			$vague[] = flxseo_audit_snippet( $a );
		}
		$href = $a->getAttribute( 'href' );
		if ( '' === $href || '#' === $href[0] || 0 === stripos( $href, 'javascript:' ) || 0 === stripos( $href, 'mailto:' ) || 0 === stripos( $href, 'tel:' ) || mb_strlen( $text ) < 3 ) {
			continue;
		}
		$norm                      = untrailingslashit( strtok( 0 === strpos( $href, '/' ) && 0 !== strpos( $href, '//' ) ? $home . $href : $href, '#' ) );
		$by_text[ $text ][ $norm ] = $href;
	}
	if ( $noname ) {
		$add( 'link-name', 'high', $noname );
	}
	if ( $vague ) {
		$add( 'link-text', 'medium', $vague );
	}
	$dupe_links = array();
	foreach ( $by_text as $text => $targets ) {
		if ( count( $targets ) > 1 ) {
			$dupe_links[] = mb_substr( $text, 0, 70 ) . ' → ' . implode( ' | ', array_values( $targets ) );
		}
	}
	if ( $dupe_links ) {
		$add( 'identical-links-same-purpose', 'medium', $dupe_links );
	}

	/* --- Erişilebilirlik --- */
	$vp = $xp->query( '//meta[translate(@name,"VIEWPORT","viewport")="viewport"]' );
	if ( 0 === $vp->length ) {
		$add( 'viewport', 'critical' );
	} else {
		$content = strtolower( preg_replace( '/\s+/', '', $vp->item( 0 )->getAttribute( 'content' ) ) );
		$max     = preg_match( '/maximum-scale=([\d.]+)/', $content, $mm ) ? (float) $mm[1] : 10;
		if ( false !== strpos( $content, 'user-scalable=no' ) || false !== strpos( $content, 'user-scalable=0' ) || $max < 5 ) {
			$add( 'meta-viewport', 'high', array( flxseo_audit_snippet( $vp->item( 0 ), false ) ) );
		}
	}
	if ( '' === trim( (string) $xp->evaluate( 'string(/html/@lang)' ) ) ) {
		$add( 'html-has-lang', 'high' );
	}

	$req_children = array(
		'list'       => array( 'listitem' ),
		'tablist'    => array( 'tab' ),
		'menu'       => array( 'menuitem', 'menuitemcheckbox', 'menuitemradio', 'group' ),
		'menubar'    => array( 'menuitem', 'menuitemcheckbox', 'menuitemradio', 'group' ),
		'radiogroup' => array( 'radio' ),
		'listbox'    => array( 'option', 'group' ),
		'tree'       => array( 'treeitem', 'group' ),
		'grid'       => array( 'row', 'rowgroup' ),
		'treegrid'   => array( 'row', 'rowgroup' ),
		'feed'       => array( 'article' ),
	);
	$req_parent = array(
		'tab'              => array( 'tablist' ),
		'listitem'         => array( 'list', 'directory' ),
		'option'           => array( 'listbox', 'group' ),
		'menuitem'         => array( 'menu', 'menubar', 'group' ),
		'menuitemcheckbox' => array( 'menu', 'menubar', 'group' ),
		'menuitemradio'    => array( 'menu', 'menubar', 'group' ),
		'treeitem'         => array( 'tree', 'group' ),
	);
	$bad_children = array();
	$bad_parent   = array();
	foreach ( $xp->query( '//*[@role]' ) as $el ) {
		$role = flxseo_audit_role( $el );
		if ( isset( $req_children[ $role ] ) && 'true' !== $el->getAttribute( 'aria-busy' ) && '' === $el->getAttribute( 'aria-owns' ) ) {
			$owned = flxseo_audit_owned_roles( $el );
			$ok    = (bool) $owned;
			foreach ( $owned as $o ) {
				if ( ! in_array( $o[0], $req_children[ $role ], true ) ) {
					$ok = false;
				}
			}
			if ( ! $ok ) {
				$bad_children[] = flxseo_audit_snippet( $el );
				foreach ( $owned as $o ) {
					if ( ! in_array( $o[0], $req_children[ $role ], true ) ) {
						$bad_children[] = '  ' . flxseo_audit_snippet( $o[1] );
					}
				}
			}
		}
		if ( isset( $req_parent[ $role ] ) && $el->hasAttribute( 'role' ) && ! in_array( flxseo_audit_parent_role( $el ), $req_parent[ $role ], true ) ) {
			$bad_parent[] = flxseo_audit_snippet( $el );
		}
	}
	if ( $bad_children ) {
		$add( 'aria-required-children', 'high', $bad_children );
	}
	if ( $bad_parent ) {
		$add( 'aria-required-parent', 'high', $bad_parent );
	}
	$orphan_li = array();
	foreach ( $xp->query( '//li' ) as $li ) {
		$p    = $li->parentNode;
		$pn   = $p instanceof DOMElement ? strtolower( $p->nodeName ) : '';
		$prol = $p instanceof DOMElement ? strtolower( trim( $p->getAttribute( 'role' ) ) ) : '';
		if ( '' !== trim( $li->getAttribute( 'role' ) ) ) {
			continue;
		}
		if ( ! in_array( $pn, array( 'ul', 'ol', 'menu' ), true ) || ( '' !== $prol && 'list' !== $prol ) ) {
			$orphan_li[] = flxseo_audit_snippet( $li );
		}
	}
	if ( $orphan_li ) {
		$add( 'listitem', 'medium', $orphan_li );
	}

	$noalt = array();
	foreach ( $xp->query( '//img[not(@alt)]' ) as $img ) {
		if ( 'true' !== $img->getAttribute( 'aria-hidden' ) && 'presentation' !== $img->getAttribute( 'role' ) ) {
			$noalt[] = flxseo_audit_snippet( $img, false );
		}
	}
	if ( $noalt ) {
		$add( 'image-alt', 'high', $noalt );
	}
	$nobtn = array();
	foreach ( $xp->query( '//button | //*[@role="button"] | //input[@type="submit" or @type="button"]' ) as $b ) {
		if ( 'true' !== $b->getAttribute( 'aria-hidden' ) && ! flxseo_audit_has_name( $b, $xp ) ) {
			$nobtn[] = flxseo_audit_snippet( $b, false );
		}
	}
	if ( $nobtn ) {
		$add( 'button-name', 'high', $nobtn );
	}
	$nolabel = array();
	foreach ( $xp->query( '//input[not(@type) or not(contains(" hidden submit button reset image ", concat(" ", translate(@type,"HIDENSUBMTRAG","hidensubmtrag"), " ")))] | //select | //textarea' ) as $in ) {
		$id = $in->getAttribute( 'id' );
		if ( '' !== trim( $in->getAttribute( 'aria-label' ) ) || '' !== trim( $in->getAttribute( 'aria-labelledby' ) ) || '' !== trim( $in->getAttribute( 'title' ) ) ) {
			continue;
		}
		if ( ( $id && $xp->query( '//label[@for="' . str_replace( '"', '', $id ) . '"]' )->length ) || $xp->query( 'ancestor::label', $in )->length ) {
			continue;
		}
		$nolabel[] = flxseo_audit_snippet( $in, false );
	}
	if ( $nolabel ) {
		$add( 'label', 'high', $nolabel );
	}
	$ids  = array();
	$dupe = array();
	foreach ( $xp->query( '//*[@id]' ) as $el ) {
		$id = $el->getAttribute( 'id' );
		if ( isset( $ids[ $id ] ) ) {
			$dupe[ $id ] = 'id="' . $id . '"';
		}
		$ids[ $id ] = true;
	}
	foreach ( $xp->query( '//*[@aria-controls or @aria-labelledby or @aria-describedby] | //label[@for]' ) as $el ) {
		foreach ( array( 'aria-controls', 'aria-labelledby', 'aria-describedby', 'for' ) as $attr ) {
			foreach ( preg_split( '/\s+/', trim( $el->getAttribute( $attr ) ) ) as $ref ) {
				if ( $ref && ! isset( $ids[ $ref ] ) ) {
					$dupe[ $attr . $ref ] = $attr . '="' . $ref . '" → ' . __( 'bulunamadı', 'flx-seo' );
				}
			}
		}
	}
	if ( $dupe ) {
		$add( 'duplicate-id-aria', 'medium', array_values( $dupe ) );
	}
	$last = 0;
	$skip = array();
	foreach ( $xp->query( '//h1|//h2|//h3|//h4|//h5|//h6' ) as $hd ) {
		$lvl = (int) substr( $hd->nodeName, 1 );
		if ( $last && $lvl > $last + 1 ) {
			$skip[] = 'H' . $last . ' → ' . flxseo_audit_snippet( $hd );
		}
		$last = $lvl;
	}
	if ( $skip ) {
		$add( 'heading-order', 'low', $skip );
	}
	$tab = array();
	foreach ( $xp->query( '//*[@tabindex]' ) as $el ) {
		if ( (int) $el->getAttribute( 'tabindex' ) > 0 ) {
			$tab[] = flxseo_audit_snippet( $el, false );
		}
	}
	if ( $tab ) {
		$add( 'tabindex', 'low', $tab );
	}
	$frames = array();
	foreach ( $xp->query( '//iframe[not(@title) or normalize-space(@title)=""]' ) as $el ) {
		$frames[] = flxseo_audit_snippet( $el, false );
	}
	if ( $frames ) {
		$add( 'frame-title', 'medium', $frames );
	}
	if ( 0 === $xp->query( '//main | //*[@role="main"]' )->length ) {
		$add( 'landmark-one-main', 'medium' );
	}

	/* --- Performans --- */
	$block = array();
	foreach ( $xp->query( '//head/link[translate(@rel,"STYLESHEET","stylesheet")="stylesheet"]' ) as $l ) {
		$media = strtolower( trim( $l->getAttribute( 'media' ) ) );
		if ( ( '' === $media || 'all' === $media || 'screen' === $media ) && ! $l->hasAttribute( 'disabled' ) ) {
			$block[] = $l->getAttribute( 'href' );
		}
	}
	foreach ( $xp->query( '//head/script[@src]' ) as $s ) {
		if ( ! $s->hasAttribute( 'async' ) && ! $s->hasAttribute( 'defer' ) && 'module' !== $s->getAttribute( 'type' ) ) {
			$block[] = $s->getAttribute( 'src' );
		}
	}
	if ( count( $block ) > 2 ) {
		/* translators: %d: istek sayısı */
		$add( 'render-blocking-insight', count( $block ) > 6 ? 'high' : 'medium', $block, sprintf( __( '<head> içinde %d engelleyen CSS/JS isteği.', 'flx-seo' ), count( $block ) ) );
	} elseif ( $block ) {
		/* translators: %d: istek sayısı */
		$add( 'render-blocking-insight', 'low', $block, sprintf( __( '<head> içinde %d engelleyen CSS/JS isteği. PageSpeed ile tahmini kazancı ölçün.', 'flx-seo' ), count( $block ) ) );
	}
	$nodes = $xp->query( '//body//*' )->length;
	if ( $nodes > 800 ) {
		/* translators: %s: öğe sayısı */
		$add( 'dom-size-insight', $nodes > 3000 ? 'high' : ( $nodes > 1500 ? 'medium' : 'low' ), array(), sprintf( __( 'Sayfada %s öğe var.', 'flx-seo' ), number_format_i18n( $nodes ) ) );
	}
	$unsized = array();
	$first   = null;
	foreach ( $xp->query( '//body//img' ) as $img ) {
		if ( null === $first ) {
			$first = $img;
		}
		if ( ( ! $img->hasAttribute( 'width' ) || ! $img->hasAttribute( 'height' ) ) && false === stripos( $img->getAttribute( 'style' ), 'aspect-ratio' ) ) {
			$unsized[] = flxseo_audit_snippet( $img, false );
		}
	}
	if ( $unsized ) {
		$add( 'unsized-images', count( $unsized ) > 3 ? 'medium' : 'low', $unsized );
	}
	if ( $first && flxseo_audit_img_lazy( $first ) ) {
		$add( 'lcp-lazy-loaded', 'medium', array( flxseo_audit_snippet( $first, false ) ) );
	}
	$fonts_no_swap = array();
	foreach ( $xp->query( '//style' ) as $st ) {
		if ( preg_match_all( '/@font-face\s*\{[^}]*\}/i', $st->textContent, $ff ) ) {
			foreach ( $ff[0] as $rule ) {
				if ( false === stripos( $rule, 'font-display' ) ) {
					$fonts_no_swap[] = mb_substr( preg_replace( '/\s+/', ' ', $rule ), 0, 140 );
				}
			}
		}
	}
	if ( $fonts_no_swap ) {
		$add( 'font-display-insight', 'medium', $fonts_no_swap );
	}
	if ( $page['ms'] > 600 ) {
		/* translators: %d: milisaniye */
		$add( 'server-response-time', $page['ms'] > 1800 ? 'high' : 'medium', array(), sprintf( __( 'Sunucu içinden ölçülen yanıt: %d ms (önbellek dışı olabilir).', 'flx-seo' ), $page['ms'] ) );
	}
	if ( strlen( $html ) > 2048 && empty( $h['content-encoding'] ) && ! preg_match( '/^(127\.|localhost)/', (string) wp_parse_url( $url, PHP_URL_HOST ) ) ) {
		$add( 'uses-text-compression', 'medium' );
	}
	if ( strlen( $html ) > 150 * 1024 ) {
		/* translators: %s: boyut */
		$add( 'document-size', strlen( $html ) > 400 * 1024 ? 'medium' : 'low', array(), sprintf( __( 'HTML %s.', 'flx-seo' ), size_format( strlen( $html ) ) ) );
	}
	if ( false !== strpos( $html, 'wp-emoji-release.min.js' ) || false !== strpos( $html, '_wpemojiSettings' ) ) {
		$add( 'emoji-script', 'low' );
	}

	/* --- En iyi uygulamalar: HTTPS ve güvenlik başlıkları --- */
	$https = 0 === strpos( home_url(), 'https://' );
	$local = (bool) preg_match( '/^(localhost|127\.|10\.|192\.168\.)|\.(local|test)$/', (string) wp_parse_url( home_url(), PHP_URL_HOST ) );
	if ( ! $https && ! $local ) {
		$add( 'is-on-https', 'high' );
	} elseif ( $https && preg_match_all( '/<(?:img|script|link|iframe|source)\b[^>]+(?:src|href)=["\']http:\/\/[^"\']+/i', $html, $mixed ) ) {
		$add( 'is-on-https', 'high', array_slice( $mixed[0], 0, 5 ), __( 'HTTPS sayfada http:// ile yüklenen içerik var.', 'flx-seo' ) );
	}
	if ( $https || $local ) {
		$hsts = strtolower( $h['strict-transport-security'] ?? '' );
		if ( '' === $hsts ) {
			$add( 'has-hsts', $local ? 'low' : 'medium' );
		} elseif ( false === strpos( $hsts, 'includesubdomains' ) || false === strpos( $hsts, 'preload' ) ) {
			$add( 'has-hsts', 'low', array( 'Strict-Transport-Security: ' . $h['strict-transport-security'] ) );
		}
	}
	$csp = strtolower( $h['content-security-policy'] ?? '' );
	if ( '' === $csp ) {
		$add( 'csp-xss', 'medium', array(), __( 'Content-Security-Policy başlığı yok.', 'flx-seo' ) );
	} else {
		$miss = array();
		foreach ( array( 'object-src', 'base-uri', 'script-src' ) as $d ) {
			if ( false === strpos( $csp, $d ) && ( 'script-src' !== $d || false === strpos( $csp, 'default-src' ) ) ) {
				$miss[] = $d;
			}
		}
		if ( $miss ) {
			/* translators: %s: eksik yönergeler */
			$add( 'csp-xss', 'low', array( 'Content-Security-Policy: ' . mb_substr( $h['content-security-policy'], 0, 160 ) ), sprintf( __( 'Eksik yönerge: %s.', 'flx-seo' ), implode( ', ', $miss ) ) );
		}
	}
	if ( empty( $h['x-frame-options'] ) && false === strpos( $csp, 'frame-ancestors' ) ) {
		$add( 'clickjacking-mitigation', 'medium' );
	}
	if ( empty( $h['cross-origin-opener-policy'] ) ) {
		$add( 'origin-isolation', 'low' );
	}
	if ( false === strpos( $csp, 'require-trusted-types-for' ) ) {
		$add( 'trusted-types-xss', 'low' );
	}
	if ( empty( $h['x-content-type-options'] ) ) {
		$add( 'x-content-type-options', 'low' );
	}
	if ( 0 !== stripos( ltrim( $html ), '<!doctype html' ) || ! preg_match( '/<meta[^>]+charset=/i', substr( $html, 0, 2048 ) ) ) {
		$add( 'doctype', 'medium' );
	}

	/* --- Ajan uyumu: WebMCP --- */
	$forms   = $xp->query( '//form' );
	$no_tool = array();
	foreach ( $forms as $form ) {
		if ( ! $form->hasAttribute( 'toolname' ) && $xp->query( './/input[not(@type="hidden")] | .//textarea | .//select', $form )->length ) {
			$no_tool[] = flxseo_audit_snippet( $form, false );
		}
	}
	if ( $no_tool ) {
		$add( 'webmcp-forms', flxseo_opt( 'webmcp' ) ? 'low' : 'medium', $no_tool );
	}

	return (array) apply_filters( 'flxseo_audit_local_checks', $f, $html, $h, $url );
}

/** Site geneli bulgular (sayfaya bağlı değil): robots.txt, llms.txt, aynı başlıklı yayınlar. */
function flxseo_audit_site_checks() {
	global $wpdb;
	$f     = array();
	$robot = flxseo_audit_fetch( home_url( '/robots.txt' ) );
	if ( ! is_wp_error( $robot ) && 200 === $robot['code'] ) {
		$txt   = $robot['body'];
		$block = flxseo_robots_blocks( $txt );
		if ( in_array( '*', $block, true ) ) {
			$f[] = flxseo_audit_finding( 'robots-txt', 'critical', array( 'Disallow: /' ) );
		}
		$ai = array_values( array_intersect( $block, array( 'gptbot', 'claudebot', 'anthropic-ai', 'google-extended', 'perplexitybot', 'ccbot', 'oai-searchbot', 'chatgpt-user', 'claude-searchbot', 'applebot-extended' ) ) );
		if ( $ai && ! in_array( '*', $block, true ) ) {
			$f[] = flxseo_audit_finding( 'ai-crawlers-blocked', 'low', $ai );
		}
	}
	if ( ! is_wp_error( $robot ) && 200 === $robot['code'] ) {
		$se = array_values( array_intersect( $block, array( 'bingbot', 'msnbot', 'yandex', 'yandexbot', 'duckduckbot', 'slurp', 'seznambot', 'yeti', 'applebot' ) ) );
		if ( $se && ! in_array( '*', $block, true ) ) {
			$f[] = flxseo_audit_finding( 'search-bots-blocked', 'high', $se );
		}
		if ( ! preg_match( '/^\s*sitemap:\s*\S+/mi', $txt ) ) {
			$f[] = flxseo_audit_finding( 'robots-sitemap', 'low' );
		}
	}
	if ( get_option( 'blog_public' ) ) {
		if ( ! flxseo_opt( 'indexnow' ) ) {
			$f[] = flxseo_audit_finding( 'indexnow', 'medium' );
		} else {
			$last = (array) get_option( 'flxseo_indexnow_last', array() );
			if ( ! empty( $last['code'] ) && (int) $last['code'] >= 400 ) {
				$f[] = flxseo_audit_finding( 'indexnow-error', 'medium', array( flxseo_indexnow_last_text( $last ) ) );
			}
			$key = flxseo_audit_fetch( home_url( '/' . flxseo_indexnow_key() . '.txt' ) );
			if ( ! is_wp_error( $key ) && ( 200 !== $key['code'] || trim( $key['body'] ) !== flxseo_indexnow_key() ) ) {
				$f[] = flxseo_audit_finding( 'indexnow-error', 'medium', array( home_url( '/' . flxseo_indexnow_key() . '.txt' ) . ' → HTTP ' . (int) $key['code'] ) );
			}
		}
		$miss = array();
		foreach ( array( 'verify_bing' => 'Bing', 'verify_yandex' => 'Yandex' ) as $k => $label ) {
			if ( '' === trim( (string) flxseo_opt( $k ) ) ) {
				$miss[] = $label;
			}
		}
		if ( $miss ) {
			$f[] = flxseo_audit_finding( 'search-verify', 'low', $miss );
		}
	}
	$php = flxseo_php_security_status();
	if ( 'ok' !== $php['status'] ) {
		$f[] = flxseo_audit_finding( 'php-unpatched', 'critical', array( 'PHP ' . $php['version'] . ' → ' . $php['fixed'] ), flxseo_php_security_message( $php ) );
	}
	if ( flxseo_php_upload_progress_on() ) {
		$f[] = flxseo_audit_finding( 'php-upload-progress', 'ok' !== $php['status'] ? 'high' : 'medium', array( 'session.upload_progress.enabled = On' ) );
	}
	$llms = flxseo_audit_fetch( home_url( '/llms.txt' ) );
	if ( is_wp_error( $llms ) || 200 !== $llms['code'] || '' === trim( $llms['body'] ) || false !== stripos( $llms['body'], '<html' ) ) {
		$f[] = flxseo_audit_finding( 'llms-txt', 'medium' );
	} else {
		$body = ltrim( $llms['body'] );
		$miss = array();
		if ( 0 !== strpos( $body, '# ' ) ) {
			$miss[] = __( 'ilk satır "# Başlık" değil', 'flx-seo' );
		}
		if ( ! preg_match( '/^>\s+\S/m', $body ) ) {
			$miss[] = __( '"> özet" satırı yok', 'flx-seo' );
		}
		if ( ! preg_match( '/\[[^\]]+\]\([^)]+\)/', $body ) ) {
			$miss[] = __( 'bağlantı listesi yok', 'flx-seo' );
		}
		if ( $miss ) {
			$f[] = flxseo_audit_finding( 'llms-txt-format', 'low', $miss );
		}
	}

	$types = flxseo_post_types();
	$in    = implode( ',', array_fill( 0, count( $types ), '%s' ) );
	// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery
	// Taşınabilir sorgu (HAVING bazı veritabanı katmanlarında çalışmıyor): başlığa göre sırala, ardışık aynıları topla.
	$rows   = $wpdb->get_results( $wpdb->prepare( "SELECT ID, post_title, post_type FROM {$wpdb->posts} WHERE post_status = 'publish' AND post_type IN ($in) AND post_title <> '' ORDER BY post_type, post_title, ID LIMIT 5000", $types ) );
	$groups = array();
	foreach ( (array) $rows as $r ) {
		$groups[ $r->post_type . "\0" . mb_strtolower( trim( $r->post_title ) ) ][] = $r;
	}
	$items = array();
	foreach ( $groups as $g ) {
		if ( count( $g ) > 1 && count( $items ) < 20 ) {
			$items[] = $g[0]->post_title . ' → ' . implode( ' | ', array_map( fn( $r ) => get_permalink( (int) $r->ID ), array_slice( $g, 0, 4 ) ) );
		}
	}
	if ( $items ) {
		$f[] = flxseo_audit_finding( 'duplicate-titles', 'medium', $items );
	}
	return $f;
}

/** robots.txt'de "Disallow: /" olan user-agent'lar (küçük harf). */
function flxseo_robots_blocks( $txt ) {
	$blocked = array();
	$agents  = array();
	$in_rule = false;
	foreach ( preg_split( '/\r?\n/', (string) $txt ) as $line ) {
		$line = trim( preg_replace( '/#.*$/', '', $line ) );
		if ( '' === $line ) {
			continue;
		}
		if ( preg_match( '/^user-agent:\s*(.+)$/i', $line, $m ) ) {
			if ( $in_rule ) {
				$agents  = array();
				$in_rule = false;
			}
			$agents[] = strtolower( trim( $m[1] ) );
			continue;
		}
		$in_rule = true;
		if ( preg_match( '/^disallow:\s*\/\s*$/i', $line ) ) {
			$blocked = array_merge( $blocked, $agents );
		}
	}
	return array_values( array_unique( $blocked ) );
}

/**
 * Yerel denetimi çalıştırır ve kaydeder.
 *
 * @return array|WP_Error
 */
function flxseo_audit_run_local( $url = '' ) {
	$url  = $url ? esc_url_raw( $url ) : home_url( '/' );
	$page = flxseo_audit_fetch( add_query_arg( 'flxseo_audit', time(), $url ) );
	if ( is_wp_error( $page ) ) {
		return $page;
	}
	$findings = array_merge( flxseo_audit_analyze( $page, $url ), flxseo_audit_site_checks() );
	$store    = flxseo_audit_store();
	$store['local'] = array(
		'time'     => time(),
		'url'      => $url,
		'ms'       => $page['ms'],
		'code'     => $page['code'],
		'findings' => $findings,
	);
	update_option( 'flxseo_audit', $store, false );
	return $store['local'];
}

function flxseo_audit_store() {
	$s = get_option( 'flxseo_audit', array() );
	return is_array( $s ) ? $s + array( 'local' => array(), 'psi' => array() ) : array( 'local' => array(), 'psi' => array() );
}

/* ---------------------------------------------------------------------------
 * PageSpeed Insights API
 * ------------------------------------------------------------------------- */

function flxseo_psi_key() {
	return (string) get_option( 'flxseo_psi_key', '' );
}

/**
 * PageSpeed Insights ölçümü (tek strateji ~15–40 sn). Anahtar isteğe bağlı; anahtarsız kota çok düşüktür.
 *
 * @param string $strategy mobile|desktop.
 * @return array|WP_Error
 */
function flxseo_psi_run( $strategy = 'mobile', $url = '' ) {
	$strategy = 'desktop' === $strategy ? 'desktop' : 'mobile';
	$url      = $url ? esc_url_raw( $url ) : home_url( '/' );
	if ( preg_match( '/^(localhost|127\.|10\.|192\.168\.)|\.(local|test)$/', (string) wp_parse_url( $url, PHP_URL_HOST ) ) ) {
		return new WP_Error( 'flxseo_psi_local', __( 'PageSpeed Insights yalnız internetten erişilebilen adresleri ölçebilir.', 'flx-seo' ) );
	}
	$cats  = (array) apply_filters( 'flxseo_psi_categories', array( 'performance', 'accessibility', 'best-practices', 'seo' ) );
	$query = 'url=' . rawurlencode( $url ) . '&strategy=' . $strategy . '&locale=' . ( 0 === strpos( determine_locale(), 'tr' ) ? 'tr' : 'en' );
	foreach ( $cats as $c ) {
		$query .= '&category=' . rawurlencode( strtoupper( str_replace( '-', '_', $c ) ) );
	}
	if ( flxseo_psi_key() ) {
		$query .= '&key=' . rawurlencode( flxseo_psi_key() );
	}
	if ( function_exists( 'set_time_limit' ) ) {
		@set_time_limit( 120 ); // phpcs:ignore WordPress.PHP.NoSilencedErrors
	}
	// Anahtar "Web siteleri" (HTTP referrer) kısıtlıysa Google boş Referer'ı reddeder; site adresini gönder.
	$r = wp_remote_get(
		FLXSEO_PSI_ENDPOINT . '?' . $query,
		array(
			'timeout' => 90,
			'headers' => array( 'Referer' => home_url( '/' ) ),
		)
	);
	if ( is_wp_error( $r ) ) {
		return $r;
	}
	$code = (int) wp_remote_retrieve_response_code( $r );
	$data = json_decode( (string) wp_remote_retrieve_body( $r ), true );
	if ( 200 !== $code || empty( $data['lighthouseResult'] ) ) {
		$msg = $data['error']['message'] ?? ( 'HTTP ' . $code );
		if ( 429 === $code ) {
			$msg = __( 'PageSpeed günlük kotası doldu. SEO › Ayarlar\'a ücretsiz bir Google API anahtarı girin.', 'flx-seo' );
		}
		return new WP_Error( 'flxseo_psi', $msg );
	}
	$res              = flxseo_psi_parse( $data['lighthouseResult'], $strategy );
	$res['time']      = time();
	$res['url']       = $url;
	$store            = flxseo_audit_store();
	$store['psi'][ $strategy ] = $res;
	update_option( 'flxseo_audit', $store, false );
	return $res;
}

/** Lighthouse sonucunu sade bulgu listesine çevirir. */
function flxseo_psi_parse( array $lh, $strategy ) {
	$audits   = (array) ( $lh['audits'] ?? array() );
	$scores   = array();
	$findings = array();
	$cat_map  = array( 'best-practices' => 'best-practices', 'performance' => 'performance', 'accessibility' => 'accessibility', 'seo' => 'seo' );
	$metrics  = array();
	foreach ( array( 'first-contentful-paint', 'largest-contentful-paint', 'total-blocking-time', 'cumulative-layout-shift', 'speed-index' ) as $m ) {
		if ( isset( $audits[ $m ] ) ) {
			$metrics[ $m ] = array(
				'title' => (string) ( $audits[ $m ]['title'] ?? $m ),
				'value' => (string) ( $audits[ $m ]['displayValue'] ?? '' ),
				'score' => isset( $audits[ $m ]['score'] ) ? (float) $audits[ $m ]['score'] : null,
			);
		}
	}
	$source = 'psi-' . $strategy;
	foreach ( (array) ( $lh['categories'] ?? array() ) as $cid => $cat ) {
		$our    = $cat_map[ $cid ] ?? ( false !== strpos( $cid, 'agent' ) ? 'agentic' : $cid );
		$passed = 0;
		$total  = 0;
		foreach ( (array) ( $cat['auditRefs'] ?? array() ) as $ref ) {
			$a = $audits[ $ref['id'] ] ?? null;
			if ( ! $a ) {
				continue;
			}
			$mode   = (string) ( $a['scoreDisplayMode'] ?? '' );
			$score  = isset( $a['score'] ) ? (float) $a['score'] : null;
			$weight = (float) ( $ref['weight'] ?? 0 );
			if ( in_array( $mode, array( 'notApplicable', 'manual' ), true ) ) {
				continue;
			}
			if ( 'binary' === $mode || 'numeric' === $mode || 'metricSavings' === $mode ) {
				++$total;
				if ( null !== $score && $score >= 0.9 ) {
					++$passed;
				}
			}
			$prio = flxseo_psi_priority( $our, $ref['id'], $a, $weight );
			if ( ! $prio ) {
				continue;
			}
			$items = array();
			foreach ( array_slice( (array) ( $a['details']['items'] ?? array() ), 0, 8 ) as $it ) {
				if ( ! is_array( $it ) ) {
					continue;
				}
				if ( ! empty( $it['node']['snippet'] ) ) {
					$items[] = $it['node']['snippet'];
				} elseif ( ! empty( $it['url'] ) ) {
					$items[] = $it['url'] . ( isset( $it['wastedBytes'] ) ? ' (−' . size_format( (int) $it['wastedBytes'] ) . ')' : '' );
				} elseif ( ! empty( $it['description'] ) && is_string( $it['description'] ) ) {
					$items[] = $it['description'] . ( isset( $it['severity'] ) && is_string( $it['severity'] ) ? ' · ' . $it['severity'] : '' );
				} elseif ( ! empty( $it['directive'] ) && is_string( $it['directive'] ) ) {
					$items[] = $it['directive'];
				}
			}
			$known = isset( flxseo_audit_catalog()[ $ref['id'] ] );
			$fd    = flxseo_audit_finding( $ref['id'], $prio, $items, '', $source );
			if ( ! $known ) {
				$fd['category'] = $our;
				$fd['title']    = (string) ( $a['title'] ?? $ref['id'] );
				$fd['fix']      = flxseo_md_strip( (string) ( $a['description'] ?? '' ) );
			}
			$fd['detail'] = trim( (string) ( $a['displayValue'] ?? '' ) );
			$findings[]   = $fd;
		}
		$scores[ $our ] = array(
			'title'  => (string) ( $cat['title'] ?? $cid ),
			'score'  => isset( $cat['score'] ) ? (int) round( (float) $cat['score'] * 100 ) : null,
			'passed' => $passed,
			'total'  => $total,
		);
	}
	return array(
		'strategy'   => $strategy,
		'scores'     => $scores,
		'metrics'    => $metrics,
		'findings'   => $findings,
		'lighthouse' => (string) ( $lh['lighthouseVersion'] ?? '' ),
	);
}

/** PageSpeed denetiminin önceliği; geçen ya da önemsiz denetim için ''. */
function flxseo_psi_priority( $cat, $id, array $a, $weight ) {
	$mode  = (string) ( $a['scoreDisplayMode'] ?? '' );
	$score = isset( $a['score'] ) ? (float) $a['score'] : null;
	$core  = array( 'largest-contentful-paint', 'cumulative-layout-shift', 'total-blocking-time' );
	if ( 'performance' === $cat && $weight > 0 ) {
		if ( null === $score || $score >= 0.9 ) {
			return '';
		}
		if ( $score < 0.5 ) {
			return in_array( $id, $core, true ) ? 'critical' : 'high';
		}
		return in_array( $id, $core, true ) ? 'medium' : 'low';
	}
	if ( 'performance' === $cat ) {
		$sav   = (array) ( $a['metricSavings'] ?? array() );
		$ms    = max( (float) ( $sav['LCP'] ?? 0 ), (float) ( $sav['FCP'] ?? 0 ), (float) ( $sav['TBT'] ?? 0 ), (float) ( $a['details']['overallSavingsMs'] ?? 0 ) );
		$cls   = (float) ( $sav['CLS'] ?? 0 );
		$bytes = (float) ( $a['details']['overallSavingsBytes'] ?? 0 );
		if ( null !== $score && $score >= 0.9 && $ms < 50 && $cls < 0.01 ) {
			return '';
		}
		if ( $ms >= 1000 || $cls >= 0.1 ) {
			return 'high';
		}
		if ( $ms >= 150 || $cls >= 0.025 || $bytes >= 40 * 1024 ) {
			return 'medium';
		}
		if ( $ms > 0 || $cls > 0 || $bytes > 0 || ( null !== $score && $score < 0.9 && ( 'informative' !== $mode || ! empty( $a['details']['items'] ) ) ) ) {
			return 'low';
		}
		return '';
	}
	if ( 'informative' === $mode ) {
		// Güven ve güvenlik denetimleri (csp-xss, has-hsts …) puanlanmaz ama Lighthouse "Yüksek önem" der.
		if ( isset( flxseo_audit_catalog()[ $id ] ) && ! empty( $a['details']['items'] ) ) {
			return in_array( $id, array( 'csp-xss', 'clickjacking-mitigation' ), true ) ? 'medium' : 'low';
		}
		return '';
	}
	if ( null === $score || $score >= 0.9 ) {
		return '';
	}
	if ( 'seo' === $cat && in_array( $id, array( 'is-crawlable', 'http-status-code', 'document-title' ), true ) ) {
		return 'critical';
	}
	if ( 'agentic' === $cat ) {
		return 'high';
	}
	if ( $weight >= 7 ) {
		return 'high';
	}
	return $weight >= 3 ? 'medium' : 'low';
}

/** Lighthouse açıklamasındaki Markdown bağlantılarını düz metne çevirir. */
function flxseo_md_strip( $s ) {
	$s = preg_replace( '/\[([^\]]+)\]\((https?:[^)]+)\)/', '$1 ($2)', $s );
	return trim( str_replace( array( '`', '**' ), '', $s ) );
}

/* ---------------------------------------------------------------------------
 * Birleşik sonuç
 * ------------------------------------------------------------------------- */

/**
 * Yerel + PageSpeed (mobil, masaüstü) bulguları birleştirir, önceliğe göre sıralar.
 *
 * @return array{findings:array, counts:array, local:array, psi:array}
 */
function flxseo_audit_report() {
	$store = flxseo_audit_store();
	$all   = array();
	$lists = array( (array) ( $store['local']['findings'] ?? array() ) );
	foreach ( array( 'mobile', 'desktop' ) as $s ) {
		$lists[] = (array) ( $store['psi'][ $s ]['findings'] ?? array() );
	}
	$order = flxseo_audit_priorities();
	foreach ( $lists as $list ) {
		foreach ( $list as $fd ) {
			if ( empty( $fd['id'] ) ) {
				continue;
			}
			$k = $fd['id'];
			if ( ! isset( $all[ $k ] ) ) {
				$all[ $k ] = $fd;
				continue;
			}
			if ( ( $order[ $fd['priority'] ][0] ?? 9 ) < ( $order[ $all[ $k ]['priority'] ][0] ?? 9 ) ) {
				$all[ $k ]['priority'] = $fd['priority'];
			}
			$all[ $k ]['sources'] = array_values( array_unique( array_merge( $all[ $k ]['sources'], $fd['sources'] ) ) );
			$all[ $k ]['items']   = array_slice( array_values( array_unique( array_merge( $all[ $k ]['items'], $fd['items'] ) ) ), 0, 10 );
			if ( '' === $all[ $k ]['detail'] && '' !== $fd['detail'] ) {
				$all[ $k ]['detail'] = $fd['detail'];
			}
		}
	}
	// Metinler kayıt anındaki dilde; katalogdaki bulgular gösterilen dilde yenilenir.
	$cat = flxseo_audit_catalog();
	foreach ( $all as $k => $fd ) {
		if ( isset( $cat[ $k ] ) ) {
			$all[ $k ]['title'] = $cat[ $k ][1];
			$all[ $k ]['fix']   = $cat[ $k ][2];
		}
		// Tek tık düzeltme zaten açıksa düğmeyi gösterme.
		if ( ! empty( $fd['fix_id'] ) && flxseo_fix_is_on( $fd['fix_id'] ) ) {
			$all[ $k ]['fix_id'] = '';
		}
	}
	$all = array_values( (array) apply_filters( 'flxseo_audit_findings', $all ) );
	usort(
		$all,
		function ( $a, $b ) use ( $order ) {
			return array( $order[ $a['priority'] ][0] ?? 9, $a['category'] ) <=> array( $order[ $b['priority'] ][0] ?? 9, $b['category'] );
		}
	);
	$counts = array_fill_keys( array_keys( $order ), 0 );
	foreach ( $all as $fd ) {
		if ( isset( $counts[ $fd['priority'] ] ) ) {
			++$counts[ $fd['priority'] ];
		}
	}
	return array(
		'findings' => $all,
		'counts'   => $counts,
		'local'    => array_diff_key( (array) $store['local'], array( 'findings' => 1 ) ),
		'psi'      => array_map(
			fn( $p ) => array_diff_key( (array) $p, array( 'findings' => 1 ) ),
			(array) $store['psi']
		),
	);
}

/* Zamanlanmış denetim ve geçmiş: includes/denetim-gecmis.php */

/* Sistem Kontrolü satırı: kritik/yüksek bulgu varsa uyarı. */
add_filter(
	'flx_system_checks',
	function ( $checks ) {
		$r     = flxseo_audit_report();
		$label = __( 'Site denetimi', 'flx-seo' );
		$url   = flxseo_admin_url( 'denetim' );
		$base  = array(
			'id'           => 'flxseo.audit',
			'source'       => 'flx-seo',
			'group'        => 'SEO',
			'label'        => $label,
			'action_url'   => $url,
			'action_label' => __( 'Denetimi aç', 'flx-seo' ),
		);
		if ( empty( $r['local']['time'] ) && empty( $r['psi'] ) ) {
			$checks[] = $base + array(
				'status'  => 'info',
				'message' => __( 'Henüz çalıştırılmadı. Performans, erişilebilirlik, SEO, güvenlik ve ajan uyumunu kontrol edin.', 'flx-seo' ),
			);
			return $checks;
		}
		$c = $r['counts'];
		/* translators: 1: kritik, 2: yüksek, 3: orta, 4: düşük bulgu sayısı */
		$msg      = sprintf( __( 'Kritik %1$d, yüksek %2$d, orta %3$d, düşük %4$d öneri.', 'flx-seo' ), $c['critical'], $c['high'], $c['medium'], $c['low'] );
		$top      = array_slice( array_filter( $r['findings'], fn( $f ) => in_array( $f['priority'], array( 'critical', 'high' ), true ) ), 0, 3 );
		if ( $top ) {
			$msg .= ' ' . implode( ' · ', wp_list_pluck( $top, 'title' ) );
		}
		$checks[] = $base + array(
			'status'  => $c['critical'] ? 'error' : ( $c['high'] ? 'warning' : 'ok' ),
			'message' => $msg,
		);
		return $checks;
	},
	21
);
