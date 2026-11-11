# Değişiklikler

## 0.4.6
- OAuth belirtecinin kayıtlı kapsamı uygulanır. `flx` uygulama uçlarını ve sitenin diğer REST uçlarını (panel oturumu dahil) açar; `mcp` yalnız `/flx-mcp/`. `offline_access` yenileme içindir, tek başına uç açmaz. Kapsamı boş kayıtlı belirteç tüm uçlara erişir. Kapsam yetmezse yanıt 403 `insufficient_scope` ve `WWW-Authenticate: Bearer error="insufficient_scope"`.
- MCP istemcisinin kendini kaydetmesi varsayılan kapalıdır (Uygulama ayarlarındaki mevcut kutu). Açıkken dönüş adresleri eskisi gibi doğrulanır. Kodla gelen istemciler kayıt kapalıyken çalışır.

## 0.4.5
- Kodla giriş yalnız kodun gittiği e-postanın hesabına ya da kayıtlı WhatsApp numarası o tam numara olan hesaba açılır. Sipariş veya fatura bilgisinden hesap açılmaz ve bağlanmaz.
- Hesap açma (`otp_create`) varsayılan kapalı. Açıkken yalnız doğrulanmış e-posta için yeni müşteri açılır; eski siparişler bağlanmaz.
- Gönderim ve doğrulama sınırları hem çağıranın IP'sine hem hedef telefon, e-posta ve hesaba bağlıdır. Yanlış kod denemesi yeni kod isteyince sıfırlanmaz. Kodlar sabit sürede karşılaştırılır.

## 0.4.4
- Remove legacy translation response framing from source IDs and all language packs; headings no longer display literal newline escapes.
- Refresh current English and Turkish product screenshots and browser walkthroughs, including mobile and dark views.


## 0.4.3
- Add native WordPress language packs for German, Spanish, French, Brazilian Portuguese, Japanese, Simplified Chinese, Arabic and Russian. English-keyed JSON catalogs preserve legacy gettext compatibility; no Flux Lang dependency.

## 0.4.2
- Ayrı kullanıcı ve site yöneticisi izniyle günlük iOS/Android aktif hesap toplamları. Varsayılan kapalı; yalnız başarılı oturumlu istekler; gün bazlı tekilleştirme, 5 hesap eşiği, içerik/kimlik göndermeyen telemetri ve süreli yerel sayaçlar.

## 0.4.1
- Site işareti artık yalnız WordPress site simgesine bakmıyor. Simge yoksa tema logosu, Flux Admin logosu ya da profil logosu gelir. Yeşil Türkiye gibi simgesi olan siteler eskisi gibi simgeyi kullanır.

## 0.4.0
- **Masaüstü ve mobil tek sözleşme.** FluxWP masaüstü (Tauri) ve yeni mobil uygulama (Expo, `com.fluxwp.app`) aynı uçları kullanır; `X-FluxWP-Platform: mobile | desktop` yalnız kabuğu seçer. Çıktılar `@fluxwp/manifest` JSON şemalarından geçer.
- **Giriş:** yerleşik `flx-mobil` istemcisi (`fluxwp://oauth/callback`). `flx-mobil` ve `flx-masaustu` hem deep link hem loopback (`http://127.0.0.1:<port>/callback`) kabul eder. `prompt=login` (aynı siteye ikinci hesap). Marka istemcileri `app_clients` ayarından (preset yazar). Site ana uygulamayı kapatabilir. Geliştirici modunda Expo `exp://` dönüşleri. Flux Speed özel giriş adresi (`/fluxadmin`) açıkken oturum devri de çalışır (önceden yalnız yetkilendirme geçiyordu).
- **Keşif:** `GET /app/bootstrap` (herkese açık): site, giriş yolları, istemciler, tema önizlemesi, etkin FluxWP paketleri, preset, marka istemcileri. `/wp-json/` dizininde `flxapp` ipucu.
- **Görünüm:** `GET /config` (`flx-app-config/1`): tema (theme.json + Flux Admin rengi + preset + override, açık/koyu), mobil kabuk (alt bar, yan menü, üst bar) ve masaüstü kabuk (kenar çubuğu, üst bar, ⌘K kısayolları), özellikler, webview kuralları; ETag/304. Yeni **Görünüm** sekmesi (iki platform önizlemesiyle) ve preset için `flx_settings_schema` kaydı.
- **Manifest:** `flxapp_manifest` filtresi, `flxapp_manifest_file()`, `GET /manifest` ve `/manifest/<id>` (yetki, platform ve dil süzülmüş; ETag/304), şema doğrulaması (Modüller sekmesi + sistem kontrolü). Çekirdek (`core`: başlangıç, yazılar, sayfalar, ortam, yorumlar, kullanıcılar, ayarlar, eklentiler), WooCommerce (`woo`: siparişler, ürünler, müşteriler) ve Flux Donate bağışçı (`flxb-donations`) manifestleri. Ekran ekran kapatma (`screens_off`).
- **Cihazlar ve push:** `GET/POST /devices`, `DELETE /devices/<id>`; cihaz listesi ve "Oturumu kapat" (Kullanıcılar › Uygulama erişimi, Tüm erişimler). Expo push (`do_action( 'flxapp_push', … )`, yanıt sonrası, ölü belirteç temizliği, şifreli belirteç), bildirim türleri (`flxapp_push_events`), içerik gizleme, deneme bildirimi. Masaüstü için `GET /sync` `events` listesi. Çekirdek olay: onay bekleyen yorum.
- **Uygulamalar** sekmesi: ana uygulama, mağaza linkleri, push durumu, Expo belirteci, marka istemcileri, geliştirici modu.
- **Tercihler:** `GET/POST /prefs` (sıra, sabitler, gizlenenler, sekmeler, bildirim türleri; cihazlar arası).
- **Ayar köprüsü:** `GET/POST /settings/<option>`; yalnız manifestte bildirilen alanlar, gizli alanlar hiç dönmez.
- **Webview:** `/session` ön yüz adresi (`front`, `url`) ve `embed`; `POST /session/end`; oturum kapanınca webview oturumları da biter. Gömülü görünüm çerezi/parametresi, ön yüzde admin bar yok, mobil safe-area, blok editörü tam ekran, `flxapp_mode` ile koyu tema, beyaz listeli köprü mesajları (başlık, adres, bildirim).
- `/me`: `caps`, `hashes`, `modules[].manifest`, paket sürümleri. `/dashboard` kartlarında §6 hedefleri (`screen:`, `module:`, `web:`); eski `mod:` çevrilir.
- Düzeltme: `X-FluxWP-Token` yedek başlığı ve `X-FluxWP-Platform` süzmesi okunmuyordu (yeniden adlandırma kalıntısı); ikisi de çalışıyor, eski adlar da okunur.
- Sistem kontrolü: push son gönderim, manifest doğrulaması, uygulama istemcileri satırları.

## 0.3.10
- Tarih ve "… önce" metinleri artık kullanıcının dilinde, tek ortak yardımcıdan (FluxWP `flx_date` / `flx_time_ago`, flx-brand 1.1.0): "31 minutes önce", "August 10" gibi Türkçe/İngilizce karışımı kalmadı; çekirdek dil paketi inmemiş olsa da TR ve EN ay/gün adları doğru.

## 0.3.9
- Uygulama erişimi ekranındaki işlemler (anahtar oluşturma, iptal, istemci, ayarlar, modüller) yanlış işleyiciye bağlanıyordu (`flxapp_` önek uzunluğu), düzeltildi.

## 0.3.8
- Kullanıcılar › Uygulama erişimi: "Masaüstü uygulaması" kartı. macOS ve Windows için indir düğmeleri (ziyaretçinin sistemi önde) ve kurumsal dağıtım için MSI bağlantısı. Bağlantılar güncelleme sunucusunun kalıcı adresine gider (`/v1/fluxapp/<marka>/download/<macos|windows|windows-msi>`, Flux Update Server 1.9.7), hep son sürümü verir. Filtreler: `flxapp_desktop_brand`, `flxapp_desktop_download_url`.

## 0.3.7
- Koyu mod Flux Admin koyu temasında da çalışıyor; ara bilgi metinleri tema tonunda.

## 0.3.6
- FluxWP imzası: Flux ekranlarının alt satırında küçük FluxWP logosu ve fluxwp.com bağlantısı. Ortak modül `includes/flx-brand` (kaynak FluxWP/fluxwp `tools/brand`); kurulu Flux paketlerinden en yeni sürümü bir kez yüklenir, `flx_brand_show` filtresiyle gizlenir.

## 0.3.5
- Menü logoları Flux Admin'deki logo stiline (Degrade / Pastel) uyar.

## 0.3.4
- Vurgu rengi Flux Admin'den (varsayılan olarak tema rengi; FluxWP'de indigo), sabit yeşil kaldırıldı.

## 0.3.3

- /me `layout`: sol menü düzeni paneldeki gibi (Flux Admin): `{ pins: [slug], groups: [ { id, label, collapsed, items: [slug] } ] }`. Rolün menü düzeni (yoksa varsayılan gruplar), kişinin sabitledikleri ve "Diğer"e taşıdıkları. Flux Admin yoksa alan gönderilmez.
- /me `menu[].icon_url`: FluxWP paketlerinin renkli logosu (Flux Admin ortak logoları, yoksa paketin `assets/icon.svg`'si; filtre `flxapp_menu_icon_url`).

## 0.3.2

- Yeni `GET flxapp/v1/dashboard`: uygulamanın ana sayfası (Başlangıç karşılığı) için kartlar ve listeler. Çekirdek: yazılar, sayfalar, onay bekleyen yorum, ortam, kullanıcılar, güncellemeler, taslaklarım, son yorumlar. WooCommerce: bugünkü siparişler/ciro, ödeme bekleyen, son siparişler. Paketler `flxapp_dashboard` filtresiyle kendi kartlarını ekler (yetkiye göre). Uygulama 0.3.13 kullanır.
- Uygulama içinde sayfa geçişinde çift yükleme çizgisi: Flux Admin'in üstteki ilerleme çubuğu (`#flxa-progress`) gömülü görünümde gizlenir; yalnız uygulamanın kendi çubuğu görünür. Uygulama sürümü gerekmez.

## 0.3.1

- /me `bottom`: telefondaki alt gezinme kısayolları paneldeki mobil alt bar ayarından (Flux Admin, rol bazlı). Uygulama 0.3.8 kullanır.

## 0.3.0

- Sol menüde yeni **Uygulama** sayfası (yöneticiler): uygulamayla ilgili her şey tek yerde. Sekmeler: Genel (giriş, oturum, panel oturumu, kodla giriş, API anahtarı ayarları), Modüller, Menü, Bağlı uygulamalar, Tüm erişimler. Kullanıcılar › Uygulama erişimi artık yalnız kişinin kendi oturumları ve API anahtarları.
- **Modüller:** paketlerin uygulamadaki yerel ekranları listelenir, tek tek kapatılabilir (`modules_off`); kapatılan modülün menüsü uygulamada panel sayfası olarak açılır.
- **Platforma göre menü:** Flux Admin 1.3.0 menü düzenleyicisinde her öğe için Web / Mobil / Masaüstü seçimi (`flxa_menu_platforms` filtresine kaydolur). Uygulama `X-FluxWP-Platform: desktop | mobile` başlığı gönderir; /me menüsü ve modüller o platformda kapalı olanlar çıkarılarak döner. Başlık göndermeyen eski sürümlerde süzme yok. Menü sekmesi platforma göre gizlenen öğeleri rol rol gösterir.

## 0.2.2

- Uygulama içi panel görünümü Flux Admin uygulama modundaki sayfalarda da (Çağrı Merkezi, Görevler, Stüdyo) çalışır: üst çubuk, sol menü ve uygulama geçiş düğmesi gizlenir, sayfa tüm alanı kullanır. 0.2.1'de bu sayfalarda Flux Admin kuralları gizlemeyi eziyordu.

## 0.2.1

- Uygulama izin (OAuth onay) sayfası yeni tasarım: her şey tek kartta. Üstte site logosu (site simgesi; filtre `flxapp_brand_logo`), başlık, hesap satırı (avatar, ad, e-posta, Değiştir), madde madde açıklama, eşit iki düğme, altta Siteye dön · KVKK. WP giriş logosu ve kart dışı bağlantılar gizli.
- Google One Tap (Site Kit vb.) bu sayfada görünmez.
- Uygulama içi panel görünümü: uygulama sayfayı gömülü açtığında (user agent `FLXApp/`) panelin sol menüsü, üst çubuğu, alt barı ve altlığı gizlenir; sayfa tüm alanı kullanır.
- `flxapp_modules`: `url` verilmezse `menu`'den üretilir; yerel ekranı olmayan uygulama sürümü modülü panel sayfası olarak açar.

## 0.2.0

- Bağışçı girişi: e-posta ya da telefona tek kullanımlık kod (/flxapp/v1/otp/start, /otp/verify); telefonda WhatsApp (Flux Chat, gizli gönderim), yoksa e-posta; personel numarası Flux Roles'den.
- Hesabı olmayan ama bağışı olan kişiye müşteri hesabı açılır, eski siparişleri bağlanır (ayar).
- Personel varsayılan olarak kodla giremez; OAuth kullanır.

## 0.1.0

- İlk sürüm: masaüstü uygulaması ve diğer istemciler için giriş altyapısı.
- OAuth 2.0 (yetkilendirme kodu + PKCE S256, dönen yenileme belirteci, iptal), WordPress girişi üzerinden onay ekranı.
- Yerleşik istemci "Flux Masaüstü" (flxmasaustu:// ve 127.0.0.1 yönlendirme).
- MCP istemcileri için RFC 8414/9728 metadata ve dinamik istemci kaydı; Flux SEO & MCP uç noktası OAuth belirteciyle de çalışır.
- Kişisel API anahtarları (Bearer flxak_…), süreli ya da süresiz; Kullanıcılar › Uygulama erişimi.
- Uygulama uçları: /flxapp/v1/me (kullanıcı, rol bazlı menü, modüller, sayaçlar), /counts, /session (tek seferlik panel oturumu), /logout.
- Sistem kontrolü satırları.
