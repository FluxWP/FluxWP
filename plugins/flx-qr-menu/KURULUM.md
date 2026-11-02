# Flux QR Menu 1.3.0 — Kurulum ve kullanım

Menüler WordPress panelinden yönetilir ve iki yoldan yayınlanır (ikisi birlikte de olur):

- **Bu sitede** (1.1.0): menüye bir adres (`/menu/`) ve/veya alt alan adı (`menu1.menu.example.org`) verilir, sayfayı eklenti üretir.
  menu.example.org böyle çalışır (bkz. [8. menu.example.org kurulumu](#8-ornek-site-kurulumu-hostinger)).
- **Ayrı menü sitesinde** (`qr-menu`): menü sitesi bağlantı anahtarıyla veriyi bu siteden çeker, her kayıt ona anlık bildirilir.

Tüm değişikliklerin geçmişi tutulur.

## 1. Eklentiyi kurun

- **Ayarlar › Flux Updater › Kur** (yayınlandıktan sonra) ya da **Eklentiler › Yeni ekle › Eklenti yükle** ile `flx-qr-menu.zip`.
- Etkinleştirin. Sol menüde **QR Menüler** çıkar. Gerekenler: WordPress 6.4+, PHP 7.4+.
- Yetki: yöneticiler tüm menülere otomatik erişir. Yalnız belirli menüleri düzenleyecek kişiler için **Menü yöneticisi** rolü var
  (bkz. [6. Menü yöneticisi](#6-menü-yöneticisi)). Bir role tüm menüleri yönetme yetkisi vermek için `flxq_manage_menus` ekleyin.
- Menüyü bu sitede yayınlayacaksanız **Ayarlar › Kalıcı bağlantılar** "Düz" olmamalı ("Yazı adı" seçip kaydedin);
  aksi halde `/menu/` gibi adresler sunucuda 404 verir. Yeniden yazma kuralı eklenmez, kalıcı bağlantıları yenilemek gerekmez.

## 2. Menü oluşturun

1. **QR Menüler › Yeni menü**.
2. Menü adı yazın ve başlangıcı seçin:
   - **Boş menü**,
   - **Örnek menü** (Yenikapı Tanıtım Günleri menüsü),
   - **JSON içe aktar**: menü JSON'u, eski menü sitesinin `index.html` dosyasının tamamı (içindeki `menu-data` bölümü alınır)
     ya da veri ucunun yanıtı yapıştırılabilir.
3. **Menüyü oluştur** → düzenleyici açılır.

## 3. Düzenleme

- **Menü** sekmesi: kategoriler açılıp kapanır; "Kategori ayarları" altında başlık, not, ikon, kimlik ve alt çubuk sekmesi.
  Her ürün satırında ad, fiyat ve **Tükendi** / **Gizli** düğmeleri (telefonda büyük). Sağdaki ok tüm alanları açar:
  açıklama, eski fiyat, birim, görsel (**Ortamdan seç** ya da adres; `img/x.jpg` gibi göreli yollar menü sitesinin kendi dosyalarıdır),
  rozet (yok / varsayılan / özel metin), bağlantı, büyük görsel, tükendi notu, yukarı/aşağı, kategoriye taşı, kopyala, sil.
  Masaüstünde ⋮⋮ tutamacından sürükleyerek sıralanır. Üstteki arama kutusu ürünleri süzer.
- **Ürün görseli** (1.2.0): satırın solundaki görsel kutusuna tıklayın, ortam kütüphanesi açılır; görseli kutudaki × kaldırır.
  Dosya yükleme yetkisi olmayan kullanıcıya görsel adresi sorulur.
- **Canlı önizleme** (1.2.0): düzenleyicinin solunda telefon çerçevesinde menünün kendisi görünür; kaydetmeden önceki değişiklikler
  de yazmayı bıraktıktan ~0,5 sn sonra yansır, kaydırma konumu korunur. Dar ekranda ve telefonda sağ alttaki **Önizleme** düğmesiyle
  tam ekran açılır. Önizlemede teşekkür sayfasına geçilmez, bağlantılar tıklanmaz. Ziyaretçiler değişikliği yalnız kaydedince görür.
- Fiyat sayı ya da metin olabilir ("İkramımızdır"); boş bırakılırsa fiyat gösterilmez.
- **Ayarlar**: etkinlik başlığı, slogan, günler (etiket + tarih), site, kapanış (otomatik / teşekkür sayfası / menü açık), genel bilgi.
- **JSON**: ham veri; **Doğrula**, **Uygula** (sonra kaydedin).
- Alttaki çubuk kaydedilmemiş değişiklik olduğunu gösterir. **Kaydet** ya da Ctrl/Cmd+S. Kaydedilmeden sayfadan çıkarken uyarır.
- Aynı menüyü iki kişi düzenliyorsa sonra kaydeden üzerine yazmaz: araya giren değişiklikler gösterilir;
  "Sunucudakini yükle" ya da bilerek "Benimkini yine de kaydet".

## 4. Geçmiş

**Geçmiş** sekmesinde her kayıt: sürüm, zaman, kişi, işlem (kaydedildi, geri dönüldü, içe aktarıldı, anahtar, durum, yapay zekâ,
adres, düzenleyenler, görseller)
ve okunur özet (ör. "Samsa: tükendi; Karkade: fiyat 150 → 100"). **Görüntüle** o sürümün menüsünü (ve JSON'unu) gösterir,
**Bu sürüme dön** o sürümü yeni bir sürüm olarak kaydeder ve menü sitelerine bildirir. Menü başına son 300 kayıt tutulur.

## 5. Menüyü bu sitede yayınlama ve paylaşma

Menü düzenleyicide **Paylaş** sekmesi:

- **Adres ayarı** (yönetici ya da "Adres/alt alan adı değiştirebilir" yetkisi olan menü yöneticisi):
  - **Adres**: `menu` yazılırsa menü `https://site/menu/`, teşekkür sayfası `https://site/menu/tesekkur/` adresinde açılır.
    Yalnız küçük harf, rakam, tire. `wp-admin`, `wp-json`, `wp-login`, `wp-content`, `wp-includes`, `feed`, `admin`, `login`, `xmlrpc`,
    `tesekkur` gibi WordPress adresleri ve başka menünün adresi verilemez. Aynı adreste bir sayfa/yazı varsa uyarı çıkar
    (menü kazanır; sayfaya ulaşmak için sayfanın kısa adını değiştirin). `/menu` gibi sonda `/` olmayan adres `/menu/`'ye yönlenir.
  - **Alt alan adı** (isteğe bağlı): `menu1.menu.example.org` yazılırsa o alan adının kökü menüyü, `/tesekkur/` teşekkür sayfasını açar.
    Alan adının hostingde bu siteyle **aynı klasöre** yönlenmesi ve SSL sertifikasının onu kapsaması gerekir (bkz. 8).
- **Menünün adresi**: açık adresler, kopyala ve aç düğmeleri.
- **QR kod**: seçili adresin QR kodu; **SVG indir** (baskı için, her boyutta net) ve **PNG indir** (1 modül = 16 px).
  Kodun etrafındaki beyaz boşluğu kesmeyin. QR üretimi tarayıcıda yapılır (qrcode-generator, MIT; dış servis yok).
- **Düzenleyenler** (yalnız yönetici): bu menüyü görüp düzenleyebilecek menü yöneticileri.

Yayındaki menü sayfası:

- Menü sitesiyle aynı şablon: başlık, açıklama, paylaşım önizlemesi (`og:url` menünün adresi), simgeler eklentinin `public/` klasöründen.
  Etkinlik bitince (ya da `kapanis: true`) teşekkür sayfasına gider; adrese `?menu` eklenirse teşekkür sayfası atlanır.
- **Canlı güncelleme**: açık sayfa, sekme görünürken 20 saniyede bir menünün sürümüne bakar (çok hafif istek, değişmediyse gövdesiz 304;
  hata olursa bekleme 5 dakikaya kadar uzar). Menü kaydedilince sayfa kaydırma konumu korunarak yenilenir; ürün penceresi ya da oyun
  açıksa kapanmasını bekler. Teşekkür sayfası menü değişince menüye döner.
- **Yayından kaldır**: adreste 404 durumlu "Menü yayında değil" sayfası açılır; menü yeniden yayına alınınca açık sayfalar kendiliğinden menüye döner.
  Çöpe atılan menünün adresi boşa çıkar.
- **Önbellek**: menü sayfaları ve sürüm ucu önbelleğe **alınmaz** (`Cache-Control: no-store`, `X-LiteSpeed-Cache-Control: no-cache`,
  `CDN-Cache-Control: no-store`, LiteSpeed Cache eklentisine `litespeed_control_set_nocache`). Böylece kayıt anında görünür ve
  önbellek temizleme eklentisine bağlı kalınmaz; LiteSpeed Cache'te ek ayar gerekmez.

### Görseller

**Örnek menü** (1.3.0): "Yenikapı Tanıtım Günleri" örneğinin görselleri eklentiyle gelir (`assets/sample/img/`). Yeni menüde
**Örnek menü** seçilince görseller ortam kütüphanesine eklenir (ağ yok, ~2,5 sn) ve menüye tam adresle yazılır; ikinci örnek menü aynı
ekleri kullanır. Eklerde yalnız küçük resim ve orta boy üretilir.

Menü sitesinden içe aktarılan menülerde ürün görselleri `img/samsa.jpg` gibi menü sitesinin dosyalarıdır; bu sitede yayınlanan menüde
gösterilmezler (eklentinin `public/img/` klasöründe ya da örnek görsellerde aynı adla dosya varsa oradan gösterilir). **Menü** sekmesinin
üstündeki **Görselleri medya kütüphanesine aktar** düğmesi bunları ortam kütüphanesine ekler ve menüde tam adresle değiştirir: yol eklentiyle
gelen örnek görsellerden birine denk geliyorsa (1.1/1.2'de örnek menüden açılmış menüler) yerel dosyadan, menü sitesi bağlı olmasa da;
değilse bağlı menü sitesinden indirir (en son görülen sitenin adresi; site en az bir kez veri almış olmalı). Parça parça çalışır (her istekte 6 görsel), aynı dosyayı
ikinci kez indirmez, geçmişe "Görseller" olarak yazılır. SVG ve 8 MB'tan büyük dosyalar aktarılmaz (WordPress SVG yüklemeye izin vermez;
o ürünlere **Ortamdan seç** ile PNG/JPG verin). Kaydedilmemiş değişiklik varken çalışmaz.

## 6. Menü yöneticisi

- **Kullanıcılar › Yeni ekle** › rol **Menü yöneticisi**. Yetkileri: giriş, dosya yükleme, atandığı/açtığı menüleri düzenleme.
- **QR Menüler › Ayarlar › Yetkiler** tablosunda her menü yöneticisi için **Menü açabilir** ve **Adres/alt alan adı değiştirebilir**
  (aynı kutular kullanıcının profil sayfasında da var).
- Menüye atama: menünün **Paylaş** sekmesi › **Düzenleyenler**. Menüyü açan kişi her zaman düzenleyebilir.
- Menü yöneticisi `/wp-admin` adresinden girer, doğrudan **QR Menüler** açılır. Panelde yalnız Başlangıç, Ortam, QR Menüler ve Profil görünür;
  ortam kütüphanesinde yalnız kendi yüklediği dosyalar listelenir.
- Göremediği menüler listede yoktur; adresini bilse de düzenleme ekranı, REST uçları ve MCP araçları **403** döner.
- Yapabildikleri: menüyü düzenleme/kaydetme, yayından kaldırma/yayına alma, geçmiş ve geri alma, QR indirme, (yetkisi varsa) adres verme,
  menü açma ve kopyalama, kendi açtığı menüyü çöpe atma/geri alma.
  Yapamadıkları: bağlantı anahtarı, bağlı siteler (Bağlantı sekmesi görünmez), düzenleyen atama, kalıcı silme, eklenti ayarları.

## 7. Menü sitesini bağlayın (ayrı alan adı)

1. Menü düzenleyicide **Bağlantı** sekmesi › **Yeni anahtar oluştur**. Anahtar (`flxq_…`) **yalnız bir kez** gösterilir: kopyalayın.
   Yeni anahtar oluşturmak eskisini hemen geçersiz kılar.
2. Menü sitesinde (`https://menu-alanadi/admin/`) **bağlantı adresi** olarak Bağlantı sekmesindeki adresi
   (`https://example.org`) ve **anahtarı** girin, kaydedin.
3. Menü sitesi ilk veri isteğinde **Bağlı siteler** tablosunda görünür (adres, istemci, son görülme, anlık bildirim var/yok).
4. Artık her kayıtta menü sitesine imzalı bildirim gider; bildirim ulaşmazsa site en geç ~1 dakika içinde değişikliği kendisi alır.

- **Yayından kaldır**: menü siteleri "yayından kaldırıldı" yanıtı alır (veri silinmez). Listeden ya da Bağlantı sekmesinden.
- Bir siteyi listeden kaldırmak erişimi kesmez; kesmek için yeni anahtar oluşturun.

## 8. menu.example.org kurulumu (Hostinger)

menu.example.org, menülerin doğrudan yayınlandığı ayrı bir WordPress sitesidir: ana sayfa boş beyaz, menüler `/menu/`, `/menu1/` ya da
`menu1.menu.example.org` gibi adreslerde.

1. **WordPress**: hPanel'de menu.example.org için WordPress kurun (`public_html`). **Ayarlar › Genel**'de site adresi `https://menu.example.org`.
2. **Tema**: Görünüm › Temalar › Tema yükle › `flx-menu-theme.zip` › Etkinleştir (boş beyaz ana sayfa, üst/alt bilgi yok).
3. **Eklenti**: `flx-qr-menu.zip` yükleyip etkinleştirin. **Ayarlar › Kalıcı bağlantılar › Yazı adı › Kaydet**.
4. **Menü**: QR Menüler › Yeni menü (örnek menüyle ya da JSON içe aktararak) › **Paylaş** › Adres `menu` › **Adresi kaydet**.
   `https://menu.example.org/menu/` açılır. Görseller menü sitesinden geldiyse **Görselleri medya kütüphanesine aktar**.
5. **Ana sayfa** (QR Menüler › Ayarlar): boş bırakmak için "Temanın ana sayfası", bir menüye göndermek için "Seçili menüye yönlendir"
   (302, menünün adresine), menüyü doğrudan `https://menu.example.org/` adresinde açmak için "Seçili menüyü ana sayfada göster".
6. **Alt alan adları** (isteğe bağlı, ör. `menu1.menu.example.org`):
   - hPanel › Alan adları › **Alt alan adları**: joker alt alan adı `*` (yani `*.menu.example.org`) oluşturup klasörünü menu.example.org'un klasörü
     (`public_html`) yapın. Joker alt alan adı paketinizde yoksa her menü için ayrı alt alan adı (`menu1`) oluşturup klasörünü yine
     `public_html` seçin. DNS'te `*` (ya da `menu1`) için A kaydı menu.example.org ile aynı IP'ye gitmeli (hPanel çoğunlukla kendisi ekler).
   - **SSL**: her alt alan adı sertifika kapsamında olmalı; aksi halde tarayıcı "güvenli değil" der ve QR'ı okutan kişi menüyü açamaz.
     hPanel › Güvenlik › SSL'de alt alan adının listelendiğini ve sertifikanın *etkin* olduğunu kontrol edin. Ücretsiz sertifika joker
     alt alan adını kapsamıyorsa alt alan adlarını tek tek oluşturup her biri için SSL kurun (ya da alan adını Cloudflare üzerinden
     yayınlayın; Cloudflare'in ücretsiz sertifikası birinci düzey alt alan adlarını kapsar).
   - Menünün **Paylaş** sekmesinde **Alt alan adı** `menu1.menu.example.org` › **Adresi kaydet**. `https://menu1.menu.example.org/` menüyü açar.
     WordPress'in site adresi değişmez; alt alan adındaki diğer adresler WordPress'e kalır.
   - Denemek için: `curl -sI https://menu1.menu.example.org/` → `200`, `curl -s https://menu1.menu.example.org/ | grep og:url`.
7. **Menü yöneticileri**: Kullanıcılar › Yeni ekle (rol Menü yöneticisi) › QR Menüler › Ayarlar › Yetkiler › menü açma / adres yetkisi
   › menünün Paylaş sekmesinde Düzenleyenler.

Önbellek: LiteSpeed Cache açık olabilir; menü sayfaları kendini önbellek dışı bildirir. Hostinger CDN'i açıksa da `CDN-Cache-Control: no-store` gönderilir.
Hostinger işlem sınırları için: açık her menü sekmesi 20 saniyede bir küçük bir istek yapar (sekme arka plandayken hiç yapmaz);
gerekirse `add_filter( 'flxq_poll_seconds', fn() => 40 );` ile aralık uzatılabilir.

## 9. Hostinger / LiteSpeed notları

- Veri ucu ve yönetim uçları `Cache-Control: no-store`, `X-LiteSpeed-Cache-Control: no-cache`, `CDN-Cache-Control: no-store` döner;
  LiteSpeed Cache'te ek ayar gerekmez. Bir güvenlik eklentisi REST API'yi girişsiz isteklere kapatıyorsa
  `/wp-json/flx-qr-menu/v1/feed` yoluna izin verin.
- Hatalı anahtarla 10 dakikada 30'dan fazla deneyen IP 10 dakika boyunca `429` alır.

## 10. Sistem kontrolü, MCP, kaldırma

- Görünüm › Sistem Kontrolü: geçmiş tablosu, menü sayısı, menü adresleri (kalıcı bağlantılar "Düz" ise uyarı), anahtarı olmayan menüler
  (yalnız bu sitede yayınlanan menüler hariç), hiç bağlanmamış / 24 saattir görülmeyen menü siteleri.
- Flux SEO & MCP: `flxq_list_menus`, `flxq_get_menu`, `flxq_update_item` (kategori kimliği + ürün adıyla fiyat / tükendi / gizli;
  geçmişe "Yapay zekâ (MCP)" olarak yazılır, menü sitelerine bildirilir). Menü yöneticisi yalnız kendi menülerinde kullanabilir.
- Eklenti silinince ayarlar, adresler, yetkiler ve Menü yöneticisi rolü silinir (bu roldeki kullanıcılar Abone olur);
  menüler, anahtarlar ve geçmiş kalır. **QR Menüler › Ayarlar › Kaldırma** "Eklenti silinince menüleri ve geçmişi de sil" işaretliyse
  onlar da silinir.

## Test kapsamı

1.3.0: yerelde WordPress 6.5 + SQLite, PHP 8.3, Playwright: yönetici ve menü yöneticisiyle örnek menüden yeni menü (24 ek, tüm ürünlerde
tam adres, düzenleyicide küçük resimler ve önizleme dolu), ikinci örnek menüde eklerin yeniden kullanılması, menü sitesi bağlı olmayan göreli
menüde yayındaki sayfa + önizlemenin örnek görselleri göstermesi ve aktar düğmesinin tek tıkla düzeltmesi, örnek olmayan yolda "Menü sitesi
bağlı değil" hatası, `../` yolunun reddi, `bin/test-update.sh` (1.2.0'dan).

1.2.0: yerelde WordPress 6.6-RC3 + SQLite, PHP 8.3, Playwright (1440 ve 390 px, açık/koyu): satırdaki kutudan görsel seçme/kaldırma,
önizlemenin kaydetmeden ~1,2 sn içinde güncellenmesi, kaydırma konumunun korunması, mobil Önizleme düğmesi, menü yöneticisiyle
seçme + önizleme ve başka menünün önizlemesine 403, yükleme yetkisi olmayan kullanıcıda adres sorma, `bin/test-update.sh` (1.1.0'dan).

1.1.0: yerelde WordPress 6.6-RC3 + SQLite + Flux Menu Theme teması, PHP 8.3, Playwright: `/menu/`, `/menu/tesekkur/`, `Host: menu1.menu.example.org`
başlığıyla alt alan adı, ayrılmış/çakışan adres, sayfa çakışması uyarısı, Paylaş (QR SVG/PNG indirme), düzenleyen atama, görsel aktarma
(sahte menü sitesinden), canlı güncelleme (kayıttan sonra yenilenme + kaydırma konumu, yayından kaldır/yayına al), ana sayfanın üç modu,
menü yöneticisi (giriş yönlendirmesi, sade panel, menü açabilen/açamayan, başkasının menüsüne REST/ekran/MCP 403, atandığı menüyü
kaydetme, ortam kısıtı), `bin/test-update.sh` (1.0.0'dan güncelleme). Gerçek Hostinger joker alt alan adı + SSL canlıda denenmeli.

1.0.0: yerelde WordPress 6.6-RC3 ve 6.4.5 + SQLite, PHP 8.3 ile denendi (PHP 7.4 sözdizimi elle denetlendi, 7.4 yorumlayıcıyla çalıştırılmadı):
menü oluşturma (index.html içe aktarma), tükendi/fiyat/kategori/ayar değişiklikleri, özetler, geri alma, 409 çakışması, anahtar,
veri ucu (401/200/ETag/304/423/429), site kaydı ve sahte bir menü sitesine imzalı bildirim, MCP araçları, sistem kontrolü,
kaldırma (veri kalır / silinir), `bin/test-update.sh`. MySQL, gerçek Hostinger/LiteSpeed ve gerçek menü sitesiyle uçtan uca bağlantı
canlıda denenmeli.
