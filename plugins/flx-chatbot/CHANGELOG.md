# Flux AI Chatbot

## 0.4.0
- **Klasik sayfalar kaldırıldı.** Ayarlar ve Site sohbetleri yalnız React ekranıyla açılır; "Klasik görünüm" bağlantısı, görünüm değiştirme (`flxcb_ui`), eski istemci (assets/admin.js, admin.css, inbox.js) ve klasik form kaydı (`admin_post_flxcb_save`) silindi.
- Ekran açılamazsa (Flux GraphQL hazır değil, paket dosyası eksik, bundle yüklenmedi) boş sayfa yerine "Bu ekran yüklenemedi" kutusu ve **Sayfayı yenile** düğmesi çıkar.

## 0.3.0
- FluxWP mobil uygulamasında Sohbet botu native çizilir (ortak manifest `app/manifest.json` + `app/inbox.json`, GraphQL): son 30 gün özeti ve durum, temel ayarlar (kim cevaplar, karşılama, e-posta, puan, dosya), site sohbetleri listesi (arama, filtre), sohbet ayrıntısı (mesajlar, cevapla, kapat, sil, ara/e-posta). Flux Chat birleşik gelen kutusu açıksa sohbetler orada kalır. Masaüstü panel React ekranlarını kullanmaya devam eder.

## 0.2.0
- Chatbot ayarları ve Site sohbetleri ekranları yenilendi: aynı özellikler (6 sekme, rakamlar, bağlantı durumu, canlı önizleme; sohbet listesi, filtre, arama, yanıt, dosya, kapatma, silme) ortak FluxWP ekranında, açık/koyu temaya uyar ve FluxWP uygulamasında da açılır. wp-admin'de varsayılan budur; eski görünüm sayfadaki "Klasik görünüm" bağlantısıyla açılır (kullanıcı başına hatırlanır).
- Ayarlar, sohbet kayıtları, yanıt, kapatma, silme ve yazıyor bilgisi GraphQL'de (`flxSettings.chatbot`, `flxChatbotSettingsSave`, `flxChatbotConversations`, `flxChatbotMessages`, `flxChatbotConversationReply` …). Ziyaretçi uçları (`flx-chatbot/v1` session, message, poll …) REST'te kalır. Belge: docs/flx-chatbot/GRAPHQL.md.
- Temsilci REST uçları ve klasik ayar formu aynı servis katmanını kullanır; davranış değişmedi.
- Gereksinim: Flux GraphQL, PHP 8.1, WordPress 6.5.

## 0.1.8
- Tarayıcının onay/uyarı/giriş pencereleri (confirm, alert, prompt) yerine ortak FluxWP modalı (flx-brand flxDialog): yeni tarz, telefonda alttan açılır, koyu modla uyumlu. Silme gibi geri alınamaz işlemlerde kırmızı onay düğmesi.

## 0.1.7
- Sohbet yanıtları ortak önbelleksiz başlıkları kullanır. Akışın tampon kapatması durur.

## 0.1.6
- Mesaj, yoklama, dosya ve asistan sınırları ortak sayacı kullanır. Adres, güvenilen vekilin ilettiği ziyaretçi adresidir.

## 0.1.5
- Çevirilerde yer tutucuların (%s, %d, {ad}) yanında silinmiş boşluklar geri geldi (TR, DE, ES, FR, PT, RU, AR); .po/.mo/.l10n.php/JSON paketleri güncellendi.

## 0.1.4
- Cap each assistant reply at a 45-second wall-clock budget shared by every model round and the stream. A second request for the same chat while one is running gets HTTP 409; the widget puts the text back and does not show an error.
- Store an uploaded file's extension from the detected MIME type (allowlist). The client file name is not used for the extension.

## 0.1.3
- Remove legacy translation response framing from source IDs and all language packs; headings no longer display literal newline escapes.
- Refresh current English and Turkish product screenshots and browser walkthroughs, including mobile and dark views.


## 0.1.2
- Add native WordPress language packs for German, Spanish, French, Brazilian Portuguese, Japanese, Simplified Chinese, Arabic and Russian. English-keyed JSON catalogs preserve legacy gettext compatibility; no Flux Lang dependency.

## 0.1.1

- Sohbet penceresi masaüstünde butonun üstünde değil yanında açılır (sağdaysa solunda, soldaysa sağında); dar ekranda yine üstünde ya da tam ekran.
- Pencere küçüldü: varsayılan genişlik 360 px, yükseklik en fazla 580 px; başlık ve kartlar daha sıkı.

## 0.1.0

- İlk sürüm: sitede yapay zekâ destekli sohbet penceresi (Shadow DOM; tema stilleriyle çakışmaz).
- Asistan Flux AI & MCP üzerinden çalışır (anahtar, model, harcama sınırı, kayıt orada). Cevaplar akışlı gelir; asistan sayfaları, yazıları, WooCommerce ürünlerini ve Flux Wiki'yi arar, ürünleri kart olarak gösterir (Sepete ekle / İncele), giriş yapmış müşteriye kendi sipariş durumunu söyler, iletişim bilgisini kaydeder, gerekince temsilciye devreder.
- Çalışma şekli: AI + canlı destek, yalnız AI ya da yalnız canlı destek.
- Tek gelen kutusu: Flux Chat (1.37+) kuruluysa her site sohbeti ilk mesajdan itibaren çağrı merkezinde site sohbeti ikonuyla görünür (asistanın cevapladıkları dahil, "Asistan" süzgecinde); devirde ekip bildirilir, temsilciler WhatsApp ve diğer kanallarla aynı ekrandan cevaplar. Bu durumda eklentinin ayrı gelen kutusu menüde yer almaz, Chatbot menüsü yalnız ayarlardır. Flux Chat yoksa (ya da ayarda "Eklentinin gelen kutusu" seçilirse) Chatbot › Sohbetler ve e-posta bildirimi.
- Sesli dikte: yazma alanında dosya düğmesinin solunda mikrofon; konuşma tarayıcıda metne çevrilir, ziyaretçi kontrol edip gönderir (panelden kapatılabilir).
- Sıra bilgisi, yazıyor göstergesi, okundu bilgisi, dosya/görsel paylaşımı, sohbet sonu 1-5 yıldız puan, mesai saatleri ve mesai dışında "mesaj bırakın" formu, ziyaretçi ayrıldıysa temsilci cevabının e-postayla gitmesi.
- Görünüm panelden: ana renk (boşsa tema rengi), yazı rengi, açık/koyu/otomatik, degrade ya da düz başlık, avatar görseli ya da simgesi, başlatıcı simgesi ve etiketi, sağ/sol konum, kenar boşlukları, boyut, köşe yuvarlaklığı, mobilde tam ekran, bildirim sesi.
- Ana ekran: başlık, alt metin, ekip avatarları, hazır sorular, iletişim bağlantıları (WhatsApp, e-posta, telefon). Tanıtım balonu (gecikmeli, oturumda bir kez).
- Görünürlük: sayfa türleri, ziyaretçi/üye, masaüstü/mobil, hariç adresler (joker). Kısa kod `[flx_chatbot]`, `#flxcb-open` bağlantısı, `data-flxcb-open` / `data-flxcb-message`, `FluxChatbot.open()`.
- Ayarlar ekranında canlı önizleme (ana ekran, sohbet, balon, kapalı).
- WooCommerce: sohbetten sonraki 7 gün içinde verilen sipariş sohbete yazılır; panelde "sohbet sonrası sipariş" sayısı.
- Flux CRM kuruluysa iletişim bırakan ziyaretçi kişi olarak eklenir.
- Gizlilik: IP saklanmaz (yalnız hız sınırı için tuzlu özet), sohbet tarayıcıdaki rastgele anahtarla eşleşir; saklama süresi dolan sohbetler ve dosyalar silinir; dosyalar herkese açık klasöre konmaz.
- Flux AI & MCP araçları (yöneticiler): site sohbetleri, sohbet dökümü, istatistikler.
- Türkçe kaynak, İngilizce çeviri.
