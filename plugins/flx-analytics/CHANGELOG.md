# Değişiklikler

## 0.8.0
- Ziyaretçi/sayfa görüntüleme grafiği ortak etkileşimli grafiğe geçti: ziyaretçi çubuk, görüntüleme alan; legend'dan seri aç/kapa, koyu/açık tema. Ortak modül paketi yenilendi.

## 0.7.4
- Telemetri merkezi: Flux Admin duyuru kartlarının gösterim/kapatma adetlerini (yalnız kart kimliği ve sayı, anonim) `flxan_ann_counts` seçeneğinde toplar; Flux Cloud › Duyurular okur.

## 0.7.3
- Telefon genişliğinde tablolar ve kartlar ekranın dışına taşmıyor (grid sütunları minmax(0,1fr)).

## 0.7.2
- Ekran ortak FluxWP kabuğuna taşındı: başlık, simge, sekmeler, kenar boşlukları, en fazla genişlik ve güvenli alan (safe area) bütün Flux ekranlarında aynı. Sekmeler eski Flux Admin sekmeleri gibi alt çizgili; taşarsa sayfa değil sekme çubuğu kayar. Ortak modül paketi yenilendi.

## 0.7.1
- Yolculuklar sayfası ilk ziyarette de spinner'sız açılır: son 30 günün varsayılan görünümü PHP'den sayfaya basılır.

## 0.7.0
- Klasik (PHP) İstatistikler, Yolculuklar, Ayarlar ve Ürün telemetrisi sayfaları kaldırıldı; wp-admin'de yalnız React ekranları açılır. "Klasik görünüm" bağlantısı, kullanıcı başına görünüm tercihi (`flxan_ui`), eski `admin-post` uçları (`flxan_save`, `flxan_reset`, `flxan_import_koko`, `flxan_aggregate`, `flxan_tm_forget`), `assets/admin.{js,css}`, `assets/journeys.js`, `assets/funnels.js`, `includes/js-strings.php` ve `tools/genstr.py` silindi. CSV indirme (`flxan_export`) ekranlar kullandığı için kaldı. Ekran açılamazsa boş sayfa yerine "Bu ekran yüklenemedi" uyarısı ve yenile düğmesi görünür.

## 0.6.1
- Ortak modül paketi yenilendi: ekranın parçaları sayfa başında birlikte iner, giriş dosyası iki kez çalışmaz, ayar ekranlarında ilk karede yükleniyor göstergesi çıkmaz.

## 0.6.0
- Mobil uygulama manifesti GraphQL'e geçti: özet (ziyaretçi, görüntüleme, şu anda), canlı (15 sn yenileme), sayfalar (arama, yalnız 404), yönlendirenler ve kanallar. Metinler İngilizce kaynak + Türkçe çeviri.

## 0.5.2
- Tarih alanları ortak FluxWP tarih seçicisini kullanır: sitenin dilinde ay/gün adları, Pazartesi başlangıç, hızlı seçim listesi, yazılabilir tarih ve saat, Uygula düğmesi, açık/koyu, telefonda alttan açılan pencere. Tarayıcının kendi takvimi (İngilizce çıkan) kalktı.

## 0.5.1
- İstatistikler, Yolculuklar, Ayarlar ve Telemetri ekranları Flux GraphQL ekran kabuğuyla açılır: ilk rapor PHP'den hazır gelir, ekranlar arası menü geçişi sayfa yenilemeden olur.

## 0.5.0
- GraphQL: istatistik, canlı, sayfa, kaynak, kırılım, olay, huni, yolculuk, referans bağlantıları, ayarlar ve telemetri merkezi `/graphql` üzerinden (Flux GraphQL gerekir). 14 sorgu, 7 mutation, kayıtlı işlemler. REST, MCP ve admin-post davranışı değişmedi; hepsi aynı servis katmanını (`includes/services`) çağırır.
- Yeni React ekranları (İstatistikler, Yolculuklar, Ayarlar, Telemetri): klasikle aynı özellikler, wp-admin varsayılanı; klasik görünüme sayfadaki bağlantıyla geçilir (kullanıcı başına). FluxApp'te `flxan-stats`, `flxan-journeys`, `flxan-settings` modülleri.
- Ziyaret toplama ve telemetri alımı GraphQL dışında kalır (ISTISNALAR.md).
- Gereken: PHP 8.1, `Requires Plugins: flx-graphql`.

## 0.4.11
- Verileri sıfırlama ve kaldırma `GLOB_BRACE` kullanmıyor: Alpine/musl PHP'de (Docker) bu sabit tanımsız olduğu için fatal hata veriyordu.

## 0.4.10
- İçe aktarılan eski siparişler bağış olayı olarak sayılmaz (`flx_silent`).

## 0.4.9
- Tarayıcının onay/uyarı/giriş pencereleri (confirm, alert, prompt) yerine ortak FluxWP modalı (flx-brand flxDialog): yeni tarz, telefonda alttan açılır, koyu modla uyumlu. Silme gibi geri alınamaz işlemlerde kırmızı onay düğmesi.

## 0.4.8
- Analitik API yanıtları ortak önbelleksiz başlıkları kullanır. noindex ve CDN işareti durur.

## 0.4.7
- CSV indirmede formül gibi açılacak hücrelerin başına tek tırnak gelir. Sütunlar, noktalı virgül ve Excel BOM aynı kalır.

## 0.4.6
- Çevirilerde yer tutucuların (%s, %d, {ad}) yanında silinmiş boşluklar geri geldi (TR, DE, ES, FR, PT, RU, AR); .po/.mo/.l10n.php/JSON paketleri güncellendi.

## 0.4.5
- Telemetry hub: a new install id is refused after 60 new ids in an hour, and the install table is kept at 5000 rows (when full, the 200 least recently seen rows are dropped, so ids that reported once go before active installs). The per-install hourly limit stays. The client id must still be 32 hex characters and the body at most 128 KiB.
- Collection trusts `CF-Connecting-IP` and other forwarded headers only when the direct peer is in Cloudflare's published ranges. `X-Forwarded-For` / `X-Real-IP` are still read when the direct peer is a private address (a local reverse proxy); otherwise the peer address is used. The raw address is still not stored.
- The tracking snippet carries a 12-hour HMAC token (the previous window is accepted, so a cached page keeps counting for 24 hours). A request without that token is rejected.
- The report endpoint serves the stored aggregate. Refresh stays on the 5-minute job and the shutdown pump, under the existing lock.

## 0.4.4
- Remove legacy translation response framing from source IDs and all language packs; headings no longer display literal newline escapes.
- Refresh current English and Turkish product screenshots and browser walkthroughs, including mobile and dark views.
- Check the current user in the dashboard REST permission callback instead of passing a request object as a user.


## 0.4.3
- Add native WordPress language packs for German, Spanish, French, Brazilian Portuguese, Japanese, Simplified Chinese, Arabic and Russian. English-keyed JSON catalogs preserve legacy gettext compatibility; no Flux Lang dependency.

## 0.4.2
- Mobil uygulama kullanım toplamlarını telemetri merkezinde gün, platform ve site ülkesi bazında gösterir. Eksik/küçük gruplar, bilinmeyen alanlar ve geçersiz sayılar kabul edilmez; kişi veya site adresi eklenmez.

## 0.4.1
- Eksik çeviriler eklendi. Arayüz WordPress dilini izler: Türkçe sitede Türkçe, İngilizce sitede (en_US ve en_GB gibi) İngilizce metin gelir.


## 0.4.0
- Mobil ve masaüstü uygulama manifesti: genel bakış, canlı ziyaret, sayfalar ve kaynaklar (`flxan/v1`).

## 0.3.9
- Metinler: MCP sunucusu artık Flux AI & MCP eklentisinde; "Flux SEO & MCP" ve "Flux AI ›" adresleri "Flux AI & MCP" oldu.

## 0.3.8
- Ürün telemetrisi › Kurulum sihirbazı: adımlar ve ilk ekran kartları okunur adla görünür ("Eklentiler: İletişim", "Mağaza kartı"); önce ham anahtar (`contact`, `store`) yazıyordu.
- Ürün telemetrisi ülkeye göre gruplanır: Genel bakışta "Ülke" tablosu, üstte ülke süzgeci (tüm sekmeler; Hatalar'da "Site" o ülkedeki kurulum sayısı). Ülke gönderen sitenin ayarlarından gelir (FluxWP Panel 1.0.20+ `cc`: WooCommerce mağaza ülkesi ya da saat dilimi); IP'ye bakılmaz, saklanmaz. Eski Panel sürümleri "Bilinmiyor" görünür. Tablo `flxan_tm_installs` `cc` sütunu alır (otomatik).

## 0.3.7
- Tarih ve "… önce" metinleri artık kullanıcının dilinde, tek ortak yardımcıdan (FluxWP `flx_date` / `flx_time_ago`, flx-brand 1.1.0): "31 minutes önce", "August 10" gibi Türkçe/İngilizce karışımı kalmadı; çekirdek dil paketi inmemiş olsa da TR ve EN ay/gün adları doğru.

## 0.3.6
- **Telemetri merkezi** (Ayarlar › Telemetri merkezi, varsayılan kapalı). Açınca `POST /wp-json/flxan/v1/telemetry` herkese açılır ve FluxWP Panel 1.0.11+ kurulumlarının anonim kullanım verisini alır; sol menüde **Ürün telemetrisi** görünür. Kapalıyken uç 404 döner.
- Ürün telemetrisi ekranı: Genel bakış (raporlayan site, eklenti kullanımı ve yaygın sürüm, birlikte kullanılan ikililer, en sık paket setleri, PHP / WordPress / dil), Hatalar (paket ve türe göre süzme, kaç sitede ve kaç kez görüldü, "Çözüldü" ile kaldırma), Kurulum sihirbazı (adımlara ulaşan, nerede bırakıldı, kart seçimleri ve vazgeçilenler, ön ayar ve renk stili, ortanca süre). 7 / 30 / 90 gün.
- IP, site adresi, User-Agent saklanmaz; kurulumun rastgele kimliği bile özetle tutulur. Hata mesajları sunucuda bir kez daha temizlenir. Aynı kurulumdan saatte en fazla bir rapor işlenir; kayıtlar 90 gün sonra silinir.

## 0.3.5
- Koyu mod Flux Admin tonlarını kullanıyor (yeşilimsi sabit zeminler kaldırıldı); harita ve ısı renkleri vurgu renginden; birincil düğme yazısı koyu modda okunur.

## 0.3.4
- FluxWP imzası: Flux ekranlarının alt satırında küçük FluxWP logosu ve fluxwp.com bağlantısı. Ortak modül `includes/flx-brand` (kaynak FluxWP/fluxwp `tools/brand`); kurulu Flux paketlerinden en yeni sürümü bir kez yüklenir, `flx_brand_show` filtresiyle gizlenir.

## 0.3.3
- Yeni logo (FluxWP ikon seti, grup rengi). `assets/icon-pastel.svg` Flux Admin › Görünüm › Paket logoları Pastel iken kullanılır.

## 0.3.2
- Vurgu rengi Flux Admin'den (varsayılan olarak tema rengi; FluxWP'de indigo), sabit yeşil kaldırıldı.

## 0.3.1

- İstatistikler grafiği: telefonda çubuklar daha geniş, sıfır olmayan değer en az 3 px (ziyaretçi çubuğu kaybolmuyordu).
- Huni ve yolculuklarda daha ayrıntılı adımlar (WooCommerce / Flux Donate): sepete ekleme (`add_to_cart`, ürün/bağış türü adıyla), sepetten çıkarma, kupon, ödeme yöntemi seçme (`payment`), sipariş oluştu (`order`, ödeme öncesi). Sunucuda olan olaylar kısa ömürlü `flxan_pe` çereziyle ziyaretçinin kendi olayı olarak gönderilir, böylece huniye ve yolculuğa bağlanır.
- Yolculuklarda olaylar ⚡ ile adım olarak görünür (sepete ekledi, ödeme formuna başladı, "Ödeme yap"a bastı, ödeme yöntemi, sipariş, WhatsApp/telefon tıklaması, dosya indirme). Liste `flxan_journey_events` filtresiyle değişir.
- Varsayılan bağış hunisine "Sepete ekledi" ve "Sipariş oluştu" adımları eklendi; huni düzenleyicisinin olay listesinde yeni olaylar var.
- İstatistikler ve Yolculuklar ekranında bütün bloklar sayfa açılır açılmaz yerinde iskelet (yanıp sönen gri) olarak görünür; tarih, ziyaretçi türü, referans, hedef ya da adım değişince de veri hesaplanırken iskelet gösterilir. Bloklar artık sırayla sonradan belirmez.

## 0.3.0

- Yeni ekran **Site Analitik › Yolculuklar**: ziyaretçinin girişten sonra hangi sayfaları hangi sırayla gezdiği. Özet kartlar (çok sayfalı ziyaret, ortalama yol uzunluğu, en sık giriş, 2+ sayfadan sonra en sık çıkış, hedefe ulaşma %), adım adım akış grafiği (her kutuda kaç ziyaret, yüzde kaçı orada bitti), en sık izlenen yollar tablosu (pay, önceki döneme göre, hedefe ulaşma; Tümü / Hedefe ulaşan / Ulaşmayan sekmeleri ve arama). Süzgeçler: aralık, ziyaretçi türü, referans, hedef sayfa (huni adımları; varsayılan ödeme sayfası), başlangıç/bitiş sayfası, adım sayısı (2–6), sayfaları grupla, önceki dönemle karşılaştır.
- Ziyaret başına sayfa sırası (ilk 8 sayfa) ve çıkış sayfası kaydedilir (tablo güncellemesi kendiliğinden; veriler bu sürümden itibaren dolar).
- REST `GET journeys`, MCP aracı `flxan_journeys`.
- Ayarlar › Huniler: metin kutusu yerine görsel düzenleyici. Adım kartları, tür seçimi (Siteye giriş / Sayfa / Olay), sayfa önerileri, olay listesi, sürükle-bırak ve ↑↓ ile sıralama, hazır adımlar, yeni huni, varsayılana dönüş. İsteyen "Metin olarak düzenle" ile eski biçimi kullanabilir.

## 0.2.3

- Üst göstergeler geniş ekranda tek satırda (dar ekranda 3, telefonda 2 sütun); her kartın ikonu ve kendi rengi var (koyu temada da).

## 0.2.2

- Görünen ad "Flux Analytics"; menüde "Site Analitik" (EN: Site Analytics). Klasör, önek ve güncelleme yolu değişmedi (`flx-analytics`).

## 0.2.1

- Düzeltme: 0.1.0'dan güncellenen sitelerde tablolar yeni şemaya geçmiyordu; özet tablolara yazılamadığı için yalnız Canlı doluyordu. Güncellemede eksik sütun/tablolar eklenir, birincil anahtarlar düzeltilir (veri silinmez). Sistem Kontrolü'ne "İstatistik tablolarını onar" eklendi.
- İstatistikler ekranı açıkken canlı panel, üst göstergeler ve grafik 5 sn'de bir; tablolar 60 sn'de bir sessizce yenilenir. Başlangıç ekranında Canlı widget 5 sn, diğerleri 30 sn.
- Üst kısım (süzgeçler + göstergeler) kaydırınca sabit kalır ve küçülür.
- Ülke bayrağı emojisi yerine ülke kodu (WordPress emoji betiği bayrağı kırık görsele çeviriyordu).

## 0.2.0

- Rol etiketi: giriş yapanlar rolüyle sayılır. Her ekranda ziyaretçi türü süzgeci (tümü / dışarıdan gelenler / giriş yapanlar / tek rol) ve "Rollere göre" tablosu.
- Referans bağlantıları (`?r=kod`): panelden ad verme ve bağlantı üretme, 30 günlük `flxan_r` çerezi (sonraki sayfalar, günler ve bağış o koda sayılır; siparişe `_flxan_ref`), kanal "Referans bağlantısı", kaynak süzgeci, referans raporu (ziyaret, süre, en ileri adım, bağış).
- Huni: varsayılan bağış akışı (giriş → bağış sayfası → ödeme sayfası → forma başladı → "Ödeme yap" → tamamlandı); ödeme sayfası olayları kendiliğinden; kanala / yönlendiren siteye / referans koduna / role / ülkeye göre kırılım; Ayarlar › Huniler.
- Yeni tasarım: 6 gösterge (ziyaret, ort. süre, hemen çıkma dahil), canlı panel (30 sn), dünya haritası (ülke saat diliminden, IP'siz), huni ve referans panelleri.
- Başlangıç ekranında 4 canlı widget: Canlı ziyaretçiler, Ziyaretçiler, Trafik kaynakları ve referanslar, Bağış hunisi.
- Yapay zekâ: tüm araçlara `seg`/`rc` süzgeci; yeni `flxan_funnel`, `flxan_refs`, `flxan_segments`, `flxan_ref_save`.
- Tablo şeması değişti (yayınlanmamış 0.1.0'dan temiz kurulum).

## 0.1.0

- İlk sürüm. Koko Analytics'ten esinlenilerek sıfırdan yazıldı (kod kopyalanmadı).
- Çerezsiz sayaç: IP ve tarayıcı bilgisi saklanmaz; ziyaretçi yalnız aynı gün, ertesi gün silinen rastgele tuzla tanınır. Botlar, ön yüklemeler, dışlanan roller/IP'ler ve isteğe bağlı "Do Not Track" sayılmaz. Önbellekteki sayfalarda da çalışır.
- Hızlı toplama ucu (WordPress yüklenmez, kuyruk dosyasına yazar); çalışmazsa kendiliğinden REST ucu. Toplayıcı WP-Cron'a ek olarak panel açılışında ve isteklerin sonunda (10 dk arayla) çalışır.
- İstatistikler ekranı: tarih aralıkları + önceki dönemle karşılaştırma, ziyaretçi/görüntülenme grafiği, son 30 dakika (canlı), sayfalar (arama, sayfa ayrıntısı), yönlendiren siteler, kanallar (doğrudan, arama, sosyal, yapay zekâ, e-posta, reklam, kampanya), cihaz/tarayıcı/sistem/dil/ülke, UTM kampanyaları, olaylar, 404 sayfaları, CSV.
- Olaylar: dış bağlantı, dosya indirme, telefon/e-posta/WhatsApp tıklaması, site içi arama, ödenen bağış/sipariş (tür + tutar); kendi olaylar için `data-flxan-event`, `flxan.track()`, `flxan_track_event()`.
- Yapay zekâ: `flxan_summary`, `flxan_timeseries`, `flxan_pages`, `flxan_referrers`, `flxan_breakdown`, `flxan_events`, `flxan_realtime`, `flxan_page`, `flxan_compare` (salt okunur; Flux SEO & MCP + Flux AI). İstatistik ekranında sohbet önerileri.
- Başlangıç widget'ı, Flux Admin kutucuğu, yazı/sayfa listesinde görüntülenme sütunu, yönetim çubuğunda sayfa sayısı, `[flx_analiz_populer]` ve `[flx_analiz_sayac]` kısa kodları.
- Koko Analytics geçmiş verisinin aktarımı (Koko tablolarına dokunmadan).
- Sistem kontrolü satırları + "İstatistikleri şimdi işle" düzeltmesi, Flux Roles yetkisi `flxan_view`, TR + EN.
