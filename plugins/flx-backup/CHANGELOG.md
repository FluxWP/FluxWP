# Changelog

## 1.4.4 — 2026-11-10
- Run asynchronous backup steps on a detected Docker worker without web polling or loopback work while its heartbeat is healthy.
- Retain ordinary WordPress execution, cancellation, restores and synchronous pre-update snapshots.

## 1.4.3
- Remove legacy translation response framing from source IDs and all language packs; headings no longer display literal newline escapes.
- Refresh current English and Turkish product screenshots and browser walkthroughs, including mobile and dark views.


## 1.4.2
- Add native WordPress language packs for German, Spanish, French, Brazilian Portuguese, Japanese, Simplified Chinese, Arabic and Russian. English-keyed JSON catalogs preserve legacy gettext compatibility; no Flux Lang dependency.

## 1.4.1
- Eksik çeviriler eklendi. Arayüz WordPress dilini izler: Türkçe sitede Türkçe, İngilizce sitede (en_US ve en_GB gibi) İngilizce metin gelir.


## 1.4.0
- Uygulama: yedek listesi, şimdi yedekle ve durum (`flx-backup/v1/backups`). Geri yükleme panelde kalır.

## 1.3.1
- Tarih ve "… önce" metinleri artık kullanıcının dilinde, tek ortak yardımcıdan (FluxWP `flx_date` / `flx_time_ago`, flx-brand 1.1.0): "31 minutes önce", "August 10" gibi Türkçe/İngilizce karışımı kalmadı; çekirdek dil paketi inmemiş olsa da TR ve EN ay/gün adları doğru.

## 1.3.0
- Büyük tam yedeklerin %95 civarında takılıp kalması düzeltildi. Yeni yedekler yalnız ekleyerek yazılan zip ile alınır (ZipArchive her adımda arşivi baştan yazıyordu; 1 GB'lık arşivde tek adım sunucunun süre sınırını aşıp sonsuza dek tekrarlanıyordu). Her adımın süresi artık arşivin boyutundan bağımsız.
- Kaldığı yerden devam: her adım; dosya listesini, SQL dökümünü ve zip'i son kaydedilen noktaya kadar kırparak başlar. Sunucunun yarıda kestiği adım iz bırakmaz; dosyalar iki kez sayılmaz ("26.631 / 25.721 dosya" gibi toplamı aşan sayaç görülmez), dökümde yinelenen satır olmaz.
- Aynı noktadan art arda kesilen iş önce adımı kısaltır, 6 denemeden sonra anlaşılır bir hatayla biter (gösterge sonsuza dek "%95" kalmaz).
- İlerleme: arşivleme yüzdesi bayt ve dosya sayısının ortalaması; sayaçlar toplamı aşmaz.
- Yedek klasörü taramada gerçek yoluyla da atlanır (bağlantı veya farklı yoldan görünse bile).
- Temizlik: iptal edilen/başarısız yedeğin ve 1.3.0 öncesinden kalan ZipArchive geçici dosyaları (`flxy-….zip.XXXXXX`) silinir; günlük temizlik bir saatten eski olanları kaldırır.
- Biçim aynı (manifest.json, database.sql.gz, files/…); eski yedekler ve geri yükleme etkilenmez. 65.535'ten çok dosya veya 4 GB üstü arşiv için ZIP64 yazılır. Var olan yedeğe paket ekleme ZipArchive ile sürer.

## 1.2.4
- Ara bilgi metinleri ve çizgiler Flux Admin tonlarını izliyor (koyu modda okunur).

## 1.2.3
- FluxWP imzası: Flux ekranlarının alt satırında küçük FluxWP logosu ve fluxwp.com bağlantısı. Ortak modül `includes/flx-brand` (kaynak FluxWP/fluxwp `tools/brand`); kurulu Flux paketlerinden en yeni sürümü bir kez yüklenir, `flx_brand_show` filtresiyle gizlenir.

## 1.2.2
- Yeni logo (FluxWP ikon seti, grup rengi). `assets/icon-pastel.svg` Flux Admin › Görünüm › Paket logoları Pastel iken kullanılır.

## 1.2.1
- "Yalnız FluxWP paketleri" kapsamı yeni temaları da tanır: flx-menu-theme, fluxwp ve "Update URI" FluxWP güncelleme sunucusunu gösteren her tema.

## 1.2.0
- Güncelleme öncesi yedeklerin saklama süresi değişince mevcut süreli yedeklere de uygulanır (oluşturulma tarihi + yeni süre); süresi dolanlar hemen silinir. Kilitli ve süresiz yedeklere dokunulmaz.
- API: `flxy_pre_update_keep_days()`, `flxy_set_pre_update_keep_days( $days )` (Flux Updater, Güncellemeler sayfasından süreyi bunlarla değiştirir).

## 1.1.0
- "Şimdi yedek al" ve geri yükleme artık arka planda: onaydan sonra pencere kapanır, kısa bir bildirim çıkar, ilerleme yönetim çubuğundaki göstergede izlenir.
- Yönetim çubuğu göstergesi (yönetim ekranları ve ön yüz): "Yedek alınıyor %87" / "Geri yükleniyor", halka ve ince ilerleme çubuğu; bitince yeşil "Yedek hazır · 697 MB", hata olursa kırmızı. Tıklayınca açılır panel: aşama, ilerleme, geçen ve kalan süre, İptal et, Ayrıntılar. Göstergenin yoklaması işi de ilerletir (Hostinger loopback'i engellese bile iş, açık herhangi bir sayfayla sürer). İş yoksa sayfaya hiçbir şey eklenmez.
- Yedek ayrıntı sayfası: boyut dağılımı (veritabanı, WordPress çekirdeği, eklentiler, temalar, medya, diğer wp-content), yığılmış çubuk ve açılır tablo; her kategoride en büyük öğeler (eklenti/tema, uploads yılı, tablo + satır sayısı), özgün ve arşivdeki boyut. Yedek bitince hesaplanır; eski yedeklerde ilk açılışta hesaplanıp saklanır.
- Yedek yükleme: Yedekler sayfasında "Yedek yükle" ile başka bir siteden veya bilgisayardan Flux Backup zip'i parça parça yüklenir (Hostinger yükleme sınırına takılmaz). Zip, manifest ve yollar doğrulanır, kota denetlenir; yedek "Yüklendi: <dosya>" olarak listeye eklenir, süresi yoktur. Başka siteden gelen yedeğin geri yükleme penceresinde uyarı gösterilir.
- İptal, adım sürerken de çalışır (adım bitince uygulanır).
- Güvenlik: geri yüklemede `..` içeren zip yolları yazılmaz.

## 1.0.0
- İlk sürüm.
- Güncelleme öncesi otomatik yedek (`upgrader_pre_install`): elle, toplu, otomatik (cron) ve WP-CLI güncellemelerinde veritabanı + güncellenen paketin klasörü; aynı istekteki tüm paketler tek yedekte. Kapsam: tüm güncellemeler / yalnız FluxWP paketleri / kapalı. Saklama süresi (varsayılan 7 gün).
- Tam, veritabanı ve dosya yedekleri; zamanlanmış tam yedek (günlük/haftalık, seçilen saatte, son N tanesi tutulur).
- Kota (varsayılan 10 GB): yeni yedekten önce en eski kilitsiz yedekler silinir; kilitli yedekler asla otomatik silinmez.
- Hostinger uyumu: exec/mysqldump yok; ZipArchive + $wpdb, ~20 sn'lik adımlar, AJAX/WP-Cron/loopback ile kaldığı yerden devam. Depolama `wp-content/wp-snapshots/flx-backup/` (Hostinger kendi yedeklerine almaz), .htaccess/web.config ile kapalı.
- Geri yükleme: veritabanı (önce güvenlik yedeği), tek tek eklenti/tema klasörleri veya wp-content parçaları. Site adresi, Flux Backup ayarları ve oturum korunur.
- Panel: kota çubuğu, depolama geçmişi grafiği (180 gün), türlere göre dağılım, son yedekler ve olaylar. Liste: indir, geri yükle, sil, toplu sil, kilitle.
- Genel API (`flxy_backup_create`, `flxy_backup_restore` …), `flxy_backup_created` eylemi, Flux Sistem kontrolü satırları.
