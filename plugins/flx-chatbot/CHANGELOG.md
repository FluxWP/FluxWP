# Flux AI Chatbot

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
