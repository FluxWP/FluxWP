# Değişiklikler

## 0.3.6
- **Telemetri merkezi** (Ayarlar › Telemetri merkezi, varsayılan kapalı). Açınca `POST /wp-json/flxan/v1/telemetry` herkese açılır ve FluxWP Panel 1.0.8+ kurulumlarının anonim kullanım verisini alır; sol menüde **Ürün telemetrisi** görünür. Kapalıyken uç 404 döner.
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
