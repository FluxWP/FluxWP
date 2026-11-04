# Flux Analytics (flx-analytics) — Kurulum

Kişisel veri tutmayan site istatistikleri (çerez yalnız referans bağlantısıyla gelene). Koko Analytics'ten esinlenilerek sıfırdan yazıldı; Koko'ya gerek yok.

## Kurulum

1. **Eklentiler › Yeni ekle › Yükle** → `flx-analytics.zip` → Etkinleştir. (Ya da FluxWP Panel'den.)
2. Sol menüde **Site Analitik** çıkar. **Site Analitik › Ayarlar**'ı bir kez açın: hızlı toplama ucu kendiliğinden test edilir
   ("Hızlı uç çalışıyor" yeşil olmalı). Olmazsa REST'e geçer, ayrıca bir şey yapmanız gerekmez.
3. Siteyi giriş yapmadığınız bir tarayıcıda / telefonda açın; ziyaret birkaç dakika içinde **Site Analitik › İstatistikler**'de görünür.
   0.2.0'dan beri giriş yapanlar da sayılır ve rolüyle etiketlenir; üstteki "Dışarıdan gelenler" süzgeci yalnız dış trafiği gösterir.
   Hiç sayılmasını istemediğiniz rol varsa Ayarlar › Sayılmayacaklar.
4. LiteSpeed: ek ayar gerekmez (sayaç script'i birleştirme/geciktirme dışında tutulur). Ayar değiştirince **Purge All**.
5. Koko Analytics kuruluysa: **Ayarlar › Veri › Koko Analytics verisini aktar** (Flux Analiz'in kurulduğu günden önceki günler gelir), sonra Koko'yu kaldırabilirsiniz.

## Nasıl çalışır

- Sayfada ~2 KB'lık `tracker.js`: yol, yazı kimliği, yönlendiren site, ekran genişliği, dil ve UTM'i `sendBeacon` ile gönderir.
- Uç `wp-content/plugins/flx-analytics/endpoint.php` WordPress yüklemeden satırı `wp-content/uploads/flx-analytics/buffer-*.ndjson`'a yazar
  (dizin `.htaccess` ile web'e kapalı). Yedek: `/wp-json/flxan/v1/c`.
- Toplayıcı kuyruğu günlük özet tablolarına (`wp_flxan_*`) işler: WP-Cron (5 dk) + panel açılışı + herhangi bir isteğin sonu (10 dk arayla).
  Hostinger'da cron tetiklenmese de veri birikmez.
- Rol: giriş yapmış kişinin ilk rolü (`editor`, `customer`…) her kayda eklenir; giriş yapmamış = "Dışarıdan gelenler".
- Ülke: IP'ye bakılmaz; tarayıcının saat diliminden çıkarılır (Europe/Istanbul → Türkiye). Şehir bu yüzden yok.
- Gizlilik: IP ve tarayıcı bilgisi saklanmaz. Ziyaretçi = günlük rastgele tuz + IP + tarayıcıdan kısa özet, yalnız o gün
  geçerli; tuz ertesi gün silinir. "Ziyaretçi" günlük tekil sayıların toplamıdır. Tek çerez `flxan_r`: yalnız `?r=kod` ile gelene,
  yalnız kodu tutar, 30 gün (birinci taraf, istatistik amaçlı). Gizlilik metnine bunu yazın.

## Referans bağlantıları (`?r=kod`)

- Paylaştığınız yerde adresin sonuna `?r=kod` ekleyin: `https://example.org/?r=ayse-ig`. Kod: küçük harf, rakam, `-`, `_`.
- **Site Analitik › İstatistikler › Referans bağlantıları › + Yeni bağlantı**: ad yazın ("Ayşe'nin Instagram'ı"), kod kendiliğinden
  oluşur, bağlantı kopyalanır. Adı sonradan vermek de olur; raporlar geriye dönük yeni adı gösterir. Flux AI'ye "Ayşe için ref linki yap" da denebilir.
- Gelen kişiye 30 günlük `flxan_r` çerezi konur, `?r=` adres çubuğundan silinir. Sonraki sayfaları, sonraki günleri ve bağışı o koda sayılır
  (WooCommerce siparişine `_flxan_ref` olarak yazılır).
- Tabloda ziyaretçi, ziyaret, sayfa/ziyaret, ortalama süre, huni içinde ulaştığı en ileri adım ve bağış (adet · tutar). Satıra tıklayınca
  bütün ekran o bağlantıyla gelenlere süzülür (hangi sayfalara baktılar, hangi adımda bıraktılar).

## Huni (ödeme sayfasına kadar kaç kişi geldi)

- Varsayılan "Bağış akışı": Siteye giriş → Bağış sayfası (`/bagis/`) → Ödeme sayfası → Formu doldurmaya başladı → "Ödeme yap"a bastı → Bağış tamamlandı.
- Ödeme sayfasında forma ilk dokunuş (`checkout:form`) ve "Ödeme yap" tıklaması (`checkout:submit`) kendiliğinden gönderilir
  (klasik ve blok ödeme). Teşekkür sayfası sipariş numarası olmadan tek yol sayılır (`/odeme/order-received/`).
- Her ziyaretçinin gün içinde ulaştığı en ileri adım sayılır. "Kanala / yönlendiren siteye / referans koduna / role / ülkeye göre" sekmeleri
  "nereden gelenler hangi adıma kadar gitti"yi gösterir.
- Adımları **Site Analitik › Ayarlar › Huniler**'deki görsel düzenleyiciden değiştirin (en fazla 5 huni × 10 adım): her adım için tür (Siteye giriş / Sayfa / Olay), sayfa yolu (öneri listesi gelir) ya da olay seçilir; sürükleyerek sıralanır. Bağış sayfanızın yolu `/bagis/` değilse buradan düzeltin.

## Yolculuklar (ziyaretçi hangi sırayla gezdi)

- **Site Analitik › Yolculuklar**: girişten itibaren adım adım akış (her kutuda ziyaret sayısı ve "burada bitti" oranı), en sık izlenen yollar ve hedefe ulaşma oranı.
- Hedef, huni adımlarından seçilir (varsayılan: ödeme sayfası). "Sayfaları grupla" `/haber/...` gibi alt sayfaları tek kutuda toplar.
- 0.3.0 kurulduktan sonraki ziyaretler görünür (eski ziyaretlerde sayfa sırası tutulmuyordu).

## Ekranlar

- **İstatistikler**: süzgeçler (aralık · ziyaretçi türü: tümü / dışarıdan gelenler / giriş yapanlar / rol · kaynak: tümü / yalnız referanslı / kod),
  6 gösterge (ziyaretçi, görüntülenme, ziyaret, ziyaretçi başına sayfa, ort. ziyaret süresi, hemen çıkma; önceki dönemle %),
  grafik, **Canlı** panel (şu an sitede, son 30 dk dakikalık çubuk, sayfalar, referanslar, ülkeler; ekran açıkken 5 sn'de bir, üst göstergeler ve grafikle birlikte; üst kısım sabit),
  **dünya haritası** + ülke listesi, **huni**, **referans bağlantıları**, kanallar, rollere göre, sayfalar (tıkla → sayfa grafiği),
  yönlendiren siteler, cihaz/tarayıcı/sistem/dil, UTM, olaylar, 404'ler, CSV. Süzgeç adres çubuğunda kalır (`#range=7d&seg=guest&rc=ayse-ig`).
- **Başlangıç** ekranında 4 widget (sekme açıkken Canlı 5 sn'de, diğerleri 30 sn'de bir kendiliğinden yenilenir): Canlı ziyaretçiler, Ziyaretçiler (bugün/dün/7 gün + 14 gün),
  Trafik kaynakları ve referanslar (dışarıdan / giriş yapan oranı), Bağış hunisi. Flux Admin kutucuğu ("Bugün ziyaretçi"), yazı/sayfa listesinde "Görüntülenme" sütunu (30 gün),
  ön yüzde yönetim çubuğunda sayfanın görüntülenmesi.
- **Sistem Kontrolü** (Performans › Sistem Kontrolü): sayım, veri dizini, yöntem, son işleme.

## Olaylar

Kendiliğinden: dış bağlantı, dosya indirme, telefon / e-posta / WhatsApp tıklaması, site içi arama, ödenen bağış/sipariş (WooCommerce; tür + tutar, sipariş başına bir kez).
Kendi olayınız: `<a data-flxan-event="paylas" data-flxan-param="instagram">`, JS `flxan.track('ad','ayrıntı')`, PHP `flxan_track_event('ad','ayrıntı',tutar)`.

## Kısa kodlar

- `[flx_analiz_populer sayi="5" gun="30" tur="post" baslik="En çok okunanlar"]`
- `[flx_analiz_sayac]` / `[flx_analiz_sayac gun="30" id="123"]`

## Yapay zekâ

Flux AI sohbeti ve MCP (Flux AI & MCP) şu araçları görür: `flxan_summary`, `flxan_timeseries`, `flxan_pages`, `flxan_referrers`,
`flxan_breakdown`, `flxan_events`, `flxan_realtime`, `flxan_page`, `flxan_compare`, `flxan_funnel`, `flxan_refs`, `flxan_segments` (salt okunur;
hepsi `seg` rol ve `rc` referans süzgeci alır) ve `flxan_ref_save` (referans bağlantısı oluşturur / adlandırır). Yetki: `flxan_view` (yöneticide var; Flux Roles'den diğer rollere).
İstatistik ekranında Flux AI sohbeti hazır sorular önerir. Ayrıntı: API.md.

## Kaldırma

Eklentiyi silmek tabloları, ayarları ve `uploads/flx-analytics` dizinini siler.
