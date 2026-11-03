---
name: flx-wp-eklenti
description: yeni WordPress eklentisi yazarken ya da FluxWP/fluxwp'deki bir FluxWP eklentisini değiştirirken kullan; dosya düzeni, i18n, ortak sistem kontrolü, MCP, e-posta, WooCommerce ve Hostinger tuzakları.
---

# Flux WordPress eklentisi yazma

Bu proje (FluxWP/fluxwp) için bir eklenti yazarken ya da değiştirirken bu kurallara uy.
Kurallar 10+ eklentide (Flux Donate, Form, Sertifika, İletişim, Çağrı Merkezi, Görev, Güncelleyici, Yedek,
SEO & MCP, Admin) yaşanan hatalardan çıktı. Ayrıntılı sözleşmeler repoda `docs/` altında.

## 0. Başlamadan

- Monorepo: `plugins/<slug>/`, ana dosya `plugins/<slug>/<slug>.php`. Bir thread yalnız kendi paket klasörüne
  commit eder. Başka paketin koduna dokunma; ihtiyacı not dosyasıyla sahibine (coordinator üzerinden) ilet.
- `main`'e push = release. Push için kullanıcı (Ahmet) onayı gerekir. Yayın akışı için `flx-paket-yayin` skill'i.
- Prefix tekil olmalı. Kullanılanlar: `flxb_` Bağış, `flxf_`/`Emsfb` Form, `flxs_` Sertifika, `flxi_` İletişim,
  `flxc_` Çağrı, `flxg_` Görev, `flxu_` Güncelleyici, `flxy_` Yedek, `flxseo_` SEO, `flxa_` Admin.
  Yeni pakette `grep -rn "<prefix>_" plugins themes` ile çakışma ara (ör. `FLXG_` Görev'de, Güncelleyici `FLXU_` seçti).
- Proje canlıda değilse eski veri için migration yazma; kullanıcı temiz kurar (önce sor/hafızaya bak).

## 1. Ana dosya ve düzen

```php
<?php
/**
 * Plugin Name:       Flux Örnek
 * Description:       Tek cümle, ne yaptığı.
 * Version:           1.0.0
 * Requires at least: 6.4
 * Requires PHP:      7.4
 * Requires Plugins:  woocommerce            // yalnız gerçekten şartsa
 * Author:            FluxWP
 * License:           GPL-2.0-or-later
 * Text Domain:       flx-ornek
 * Domain Path:       /languages
 * Update URI:        https://updater.fluxwp.com/flx-ornek
 */
defined( 'ABSPATH' ) || exit;
if ( defined( 'FLXO_VERSION' ) ) { return; }     // iki kopya yüklenirse fatal olmasın
define( 'FLXO_VERSION', '1.0.0' );                // Version: başlığıyla HER ZAMAN aynı
define( 'FLXO_FILE', __FILE__ );
define( 'FLXO_DIR', plugin_dir_path( __FILE__ ) );
define( 'FLXO_URL', plugin_dir_url( __FILE__ ) );
require_once FLXO_DIR . 'includes/...';
```

- `Update URI` wordpress.org'un aynı slug'la "güncelleme" sunmasını engeller.
- Klasör: `includes/` (sınıf başına dosya ya da konu başına dosya), `assets/{css,js,img}`, `assets/icon.svg`,
  `languages/`, `uninstall.php`, `CHANGELOG.md`, `index.php` (boş, dizin listesi kapalı), gerekiyorsa `API.md`, `KURULUM.md`.
- Sürüm sabiti ile DB şema sürümünü ayır (`flxo_db_version` seçeneği + `plugins_loaded`'ta `maybe_upgrade`).
  Aktivasyon kancası güncellemede çalışmaz; tablo/rol/cap kurulumunu `maybe_upgrade` da yapmalı.
- `uninstall.php`: ayar, cron, cap temizliği; kullanıcı verisi (dosya, yedek, bağış) yalnız ayarda işaretliyse silinir.
- Bağımlılık: `function_exists()` / `class_exists()` ile kontrol, yoksa sessizce eski davranış. Asla doğrudan çağırıp fatal üretme.
- Ön yüz JS/CSS yalnız gereken sayfada yüklenir (kısa kod/blok varsa). Vendored kütüphane, CDN yok.

## 2. Ayarlar ve gizli bilgiler

- Sunucu adresi, API key, token **panelden bir kez girilir**; `wp-config` sabiti yalnız isteğe bağlı override.
- Gizli değer panelde, logda, REST'te, MCP'de, sistem kontrolünde asla tam gösterilmez (son 4 hane ya da "kayıtlı").
  Form alanı write-only: boş gönderilirse eskisi kalır. Saklarken mümkünse `sodium` ile şifrele.
- Ayarlar tek seçenekte dizi (`flxo_settings`), `autoload` gereksizse `false`.
- Her form/aksiyon: `current_user_can()` + nonce. REST: `permission_callback` asla `__return_true` değil
  (herkese açık uçlarda bile oran sınırı/doğrulama düşün).

## 3. i18n (zorunlu)

- **Kaynak dil Türkçe**, tüm kullanıcıya görünen metin `__()`/`esc_html__()` ile sarılı, text domain = slug.
  Hardcoded Türkçe metin bırakma; tarih `date_i18n()`/`wp_date()`, sayı `number_format_i18n()`, para `NumberFormatter`/WC fonksiyonları.
- `languages/<domain>-en_US.l10n.php` (WP 6.5+ PHP çeviri dosyası) + `.po`/`.mo` + `.pot`.
  `msgfmt` yoksa l10n.php'yi script ile üret (bkz. `plugins/flx-tasks/tools/genstr.py` mantığı:
  PHP `__()` + JS `t()` metinlerini topla, EN karşılıklarını JSON'dan al, `return array('messages'=>…)` yaz).
- Yükleme `init` önceliği 0–1'de; en_GB/en_AU gibi diller en_US'e düşsün:

```php
add_action( 'init', function () {
	load_plugin_textdomain( 'flx-ornek', false, dirname( plugin_basename( FLXO_FILE ) ) . '/languages' );
}, 1 );
add_filter( 'load_textdomain_mofile', function ( $mofile, $domain ) {
	if ( 'flx-ornek' === $domain && ! is_readable( $mofile ) && ! is_readable( str_replace( '.mo', '.l10n.php', $mofile ) )
		&& preg_match( '/-en_[A-Z]{2}\.mo$/', $mofile ) ) {
		return FLXO_DIR . 'languages/flx-ornek-en_US.mo';
	}
	return $mofile;
}, 10, 2 );
```

- JS metinleri: `wp_localize_script` ile çevrilmiş sözlük ya da `wp_set_script_translations`. REST isteklerine
  `_locale=user` ekle, yoksa yanıt site dilinde döner.
- `/* translators: */` yorumu her `sprintf` placeholder'lı metnin üstünde. Yeni metin eklediğinde EN çevirisini aynı commit'te ekle.
- Hukuki metinler (KVKK, vekâlet) çeviriye bilinçli olarak sokulmayabilir; kararı belgeye yaz.

## 4. Ortak sözleşmeler (her yeni paket kaydolur)

### Sistem kontrolü: `flx_system_checks`
Ortak ekran (Görünüm › Sistem Kontrolü, Bağışlar › Ayarlar, Formlar) tüm FluxWP paketlerinin satırlarını gösterir.

```php
add_filter( 'flx_system_checks', function ( $checks ) {
	$checks   = is_array( $checks ) ? $checks : array();
	$checks[] = array(
		'id'           => 'flxo.cron',               // <prefix>.<konu>, benzersiz
		'source'       => __( 'Flux Örnek', 'flx-ornek' ),
		'group'        => 'Cron',                   // Cron|Payments|Plugins|Config|Email|Security|Forms|kendi grubun
		'label'        => __( 'Zamanlanmış görev', 'flx-ornek' ),
		'status'       => 'ok',                     // ok|warning|error|info
		'message'      => __( 'Ne bulundu, ne yapmalı (düz metin).', 'flx-ornek' ),
		'action_url'   => admin_url( 'admin.php?page=flx-ornek' ), // isteğe bağlı
		'action_label' => __( 'Ayarlar', 'flx-ornek' ),
	);
	return $checks;
} );
```

Kurallar: kontroller ucuz (seçenek, sabit, birkaç sorgu); **dış HTTP yalnız yönetici "Test"e tıklayınca**,
sonuç seçenekte saklanıp satırda gösterilir; gizli değer yazılmaz. Kendi ekranında göstermek için
`function_exists( 'flx_system_checks_render' )` ise onu çağır, yoksa kendi satırlarını basit tabloyla göster.
Cron'a bağımlıysan her çalışmada heartbeat seçeneğine zaman yaz ve "son çalışma" satırı ver
(Hostinger'da `DISABLE_WP_CRON` açık ama gerçek cron tetiklenmeyebiliyor).

### Sistem düzeltmesi: `flx_system_fixes`
Flux SEO & MCP'nin `system_fix` aracı bunları uygular. Alanlar: `label, description, source, checks[] (çözdüğü satır id'leri),
value (şema: {type:string} | {type:enum, enum:[…]} | null), current(callable), run(callable → mesaj; hata: throw Exception)`.
Gizli anahtar/parola, script/kod alanı ve MCP'yi kapatan ayar asla düzeltme olarak açılmaz. `run` girdiyi kendisi doğrular.

### MCP aracı: `flxseo_mcp_tools`
```php
add_filter( 'flxseo_mcp_tools', function ( $tools ) {
	$tools['flxo_list'] = array(
		'title' => 'Örnekleri listele', 'description' => '...', 'readonly' => true,
		'cap'   => 'manage_options',
		'schema'=> array( 'type' => 'object', 'properties' => new stdClass() ),
		'run'   => function ( $args ) { return array( 'items' => array() ); }, // dizi; hata için Exception
	);
	return $tools;
} );
```
Araç adı prefix'li. Yeni içerik taslak, silme çöpe. Sözleşme: `docs/seo-mcp/MCP-API.md`.

### E-posta / WhatsApp / SMS: Flux Mail
Kendi şablonunu `flxi_email_types` ile kaydet (`<slug>/<anahtar>`, group = paket adı, `vars`, `sample`, `defaults`),
gönderimi `flxi_send_email( array( 'to', 'template', 'vars' ) )` ile yap; yoksa `wp_mail`. WhatsApp/SMS
`flxi_send_message( 'whatsapp', $tel, $metin, array( 'source' => '<slug>' ) )`. Bağlantı ayarı (Evolution, Netgsm, SMTP)
paket içinde tutulmaz; Flux Mail'den okunur (`flxi_get_channel_connection`, `flxi_connection_choices`). API: `docs/iletisim/API.md`.

### Bağış / ödeme
Ödeme ve bağış kaydı tek yerde: Flux Donate (WooCommerce üzerinde). Başka paket bağış açacaksa `flxb_create_order()` /
`FLXB_Donations::record()`; fiyatın tek kaynağı Flux Donate türü. Kendi ödeme sağlayıcını yazma. API: `docs/bagis-eklentisi/API.md`.

### Logo ve menü ikonu
Ortak logo `plugins/flx-setup/assets/icons/<slug>.svg` (256×256, yuvarlak kare, degrade, beyaz sembol).
Yeni pakette aynı tarzda logo ekle (Güncelleyici'deki ikon listesine sahibi ekler ya da `flxu_package_icons` filtresi).
Admin menüsünde renkli logo: SVG'yi `assets/icon.svg` yap, `add_menu_page( …, 'none', … )`, `admin_head`'de
`#toplevel_page_<menu-slug> .wp-menu-image{background:url(...) center/20px 20px no-repeat}`. (WP data-URI SVG'yi tek renge boyar.)

## 5. Hostinger / LiteSpeed tuzakları

- **Rate-limit**: yoğun wp-admin/admin-ajax/REST isteğinde Hostinger IP'yi kesiyor. Polling yazarsan:
  varsayılan ≥10 sn, sekme gizliyse dur, hareketsizlikte 45–60 sn, aynı anda tek istek, 20 sn timeout,
  hata/429/503'te üstel backoff (2–5 dk). Long-poll/SSE yok (entry-process limiti). Mümkünse WordPress **Heartbeat**'e bin
  (`heartbeat_received`), ek istek açma.
- **LiteSpeed REST GET önbelleği** eski veri döndürebiliyor. Değişken veri dönen REST uçlarında `rest_post_dispatch` ile:
  `Cache-Control: no-store…`, `X-LiteSpeed-Cache-Control: no-cache`, `CDN-Cache-Control: no-store`,
  `do_action( 'litespeed_control_set_nocache', '…' )`; istemcide GET'e `_t=` ekle. (Örnek: `plugins/flx-tasks/includes/rest.php`.)
- Bazı hosting'ler `PATCH`/`DELETE`'i kesiyor: yazma `POST`, silme `POST` + `X-HTTP-Method-Override: DELETE`.
- LiteSpeed lazy load logoyu/kritik görseli geciktirir: `data-no-lazy`, `skip-lazy`, `fetchpriority="high"` ve `litespeed_media_lazy_img_excludes`.
- Uzun iş (webhook sonrası AI, toplu gönderim): yanıtı gönder, sonra `fastcgi_finish_request()` / `litespeed_finish_request()` ile aynı süreçte devam et; ya da Action Scheduler/cron kuyruğu + sayfa açıkken tarayıcı "pompası".
- WP-Cron'a güvenme: kuyruklar gerçek cron olmadan da (sayfa açıkken) ilerleyebilmeli; sistem kontrolünde son çalışmayı göster.
- Saat dilimi canlıda +00:00 olabilir: `wp_date()`/`current_time()` kullan, `date()` kullanma.
- `.ico` gibi bazı statik istekler WP'ye ulaşmaz; dosyayı fiziksel olarak kök dizine koymak gerekebilir.

## 6. wp-admin arayüzü

- WP'nin `forms.css`'i `input[type=text]` (özgüllük 0,1,1) ile tek sınıflı seçicileri ezer (koyu temada beyaz kutu).
  Kendi stilini kapsayıcıyla yükselt: `.flxo .flxo-input`. Testte gerçek `wp-admin/css`'i yükle, sahte stille test etme.
- Tam ekran SPA yazacaksan admin sayfasında WP emoji script'ini kapat (kırık `img` sorunu), `#wpcontent` padding'ini sıfırla,
  Flux Admin'in geçiş düğmesi için `flxa_app_screens` filtresine ekranını ekle.
- Flux Admin kurulu olabilir: `#adminmenu` yapısını değiştirme, yalnız stil; menü sayıları için `flxa_counts`, kutucuk için `flxa_dashboard_stats`.
- Loader/skeleton ve optimistic UI: yorum/alt kayıt gibi eklemelerde önce ekranda göster, hata olursa geri al + taslağı koru.
- Mobil ≤782 px'te tablo → kart görünümü. Light/dark: `prefers-color-scheme` + tema `body.flx-dark`.

## 7. WooCommerce

- WooCommerce her zaman kurulu kabul edilir ama yine de `class_exists( 'WooCommerce' )` kontrolü.
- `before_woocommerce_init`'te `FeaturesUtil::declare_compatibility( 'custom_order_tables' | 'cart_checkout_blocks', __FILE__, true )`.
- Sipariş işlemleri yalnız `wc_get_order()`, `$order->get_meta()/update_meta_data()/save()`, `wc_get_orders()`, `wc_create_refund()` (HPOS).
- Tutar sunucuda hesaplanır (`woocommerce_before_calculate_totals`); istemciden gelen fiyata güvenme.
- Bağış türleri gizli sanal ürünler. Blok checkout için ödeme yöntemi `AbstractPaymentMethodType` + JS `registerPaymentMethod`; klasik checkout'u da destekle.
- WC personeli wp-admin'den Hesabım'a atar: kendi rolün için `woocommerce_prevent_admin_access` / `woocommerce_disable_admin_bar` filtreleri.
- iyzico: resmî iyzico WC eklentisi tek seferlik kart; anahtarları `woocommerce_iyzico_settings`'ten oku, kopyalama.

## 8. Güncelleme sistemiyle uyum

- `site_transient_update_plugins` filtresinde **başka paketlerin ya da kendi FluxWP kaydını `response`'tan silme**.
  (Flux Forms fork'u wordpress.org'u engellemek için kendi anahtarını sildi, FluxWP güncellemesi de kayboldu.)
  wordpress.org'u engellemek için `Update URI` başlığı yeterli; ille de filtre gerekiyorsa yalnız `package` alanı
  `downloads.wordpress.org` olan kaydı sil.
- Eklenti etkinleşirken/güncellenirken fatal = otomatik geri alma. `php -l` temiz, PHP 7.4 uyumlu sözdizimi
  (typed property, `match`, `?->`, `str_contains` yok ya da polyfill'li).

## 9. Teslim

Her değişiklikte: sürüm artır (başlık + sabit), `CHANGELOG.md`'ye `## x.y.z` bölümü, EN çeviri, sistem kontrolü satırları,
`bin/test-update.sh` geçer, zip + `KURULUM.md` (+ `API.md`) `/mnt/project-files/<klasör>/` altına, ekran görüntüleri
(masaüstü + mobil, light + dark). Diğer paketlerin yapması gerekenler varsa `diger-sahipler-icin.md` gibi bir not yaz.
Ayrıntı: `flx-paket-yayin` skill'i.

## Kontrol listesi

- [ ] Prefix tekil, `defined('ABSPATH')`, çift yükleme koruması, sürüm sabiti = başlık
- [ ] Ayar/anahtar panelden, gizli değer hiçbir yerde açık değil, nonce + cap
- [ ] Tüm metin `__()`, TR kaynak + `en_US.l10n.php`/`.mo`, tarih/sayı i18n
- [ ] `flx_system_checks` satırları (ucuz, dış HTTP yok), gerekiyorsa `flx_system_fixes`, `flxseo_mcp_tools`
- [ ] E-posta Flux Mail üzerinden, fallback `wp_mail`
- [ ] Polling hafif / Heartbeat, REST no-cache başlıkları
- [ ] Admin CSS kapsayıcı ile, mobil + dark kontrol edildi
- [ ] `update_plugins` response'una dokunulmadı
- [ ] CHANGELOG, test-update.sh, zip + KURULUM.md
