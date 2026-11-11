# Flux Studio — Değişiklikler

## 0.4.4
- Stüdyo API yanıtları ortak önbelleksiz başlıkları kullanır. CDN işareti durur.

## 0.4.3
- Çevirilerde yer tutucuların (%s, %d, {ad}) yanında silinmiş boşluklar geri geldi (TR, DE, ES, FR, PT, RU, AR); .po/.mo/.l10n.php/JSON paketleri güncellendi.

## 0.4.2
- Remove legacy translation response framing from source IDs and all language packs; headings no longer display literal newline escapes.
- Refresh current English and Turkish product screenshots and browser walkthroughs, including mobile and dark views.


## 0.4.1
- Add native WordPress language packs for German, Spanish, French, Brazilian Portuguese, Japanese, Simplified Chinese, Arabic and Russian. English-keyed JSON catalogs preserve legacy gettext compatibility; no Flux Lang dependency.

## 0.4.0
- Uygulama galeri listesi. Üretim ekranı sonra açılır. Galeri sayfalama `page` parametresini kabul eder.

## 0.3.7
- Metinler: MCP sunucusu artık Flux AI & MCP eklentisinde; "Flux SEO & MCP" ve "Flux AI ›" adresleri "Flux AI & MCP" oldu.

## 0.3.6
- Açık tema: uyarı, hata ve mavi metinler yeterli kontrastta. Uygulama başlığında Flux Studio paket ikonu ve adı.

## 0.3.5
- Koyu modda yenilemede açık tema yanıp sönmesi giderildi: tema, uygulama kabı çizilir çizilmez (app.js beklenmeden) uygulanır.

## 0.3.4
- Yeşilimsi sabit palet yerine Flux Admin vurgu renginden türetilen tonlar; sönük (faint) metinler daha okunur.

## 0.3.3
- FluxWP imzası: Flux ekranlarının alt satırında küçük FluxWP logosu ve fluxwp.com bağlantısı. Ortak modül `includes/flx-brand` (kaynak FluxWP/fluxwp `tools/brand`); kurulu Flux paketlerinden en yeni sürümü bir kez yüklenir, `flx_brand_show` filtresiyle gizlenir.

## 0.3.2
- Yeni logo (FluxWP ikon seti, grup rengi). `assets/icon-pastel.svg` Flux Admin › Görünüm › Paket logoları Pastel iken kullanılır.

## 0.3.1
- Vurgu rengi Flux Admin'den (varsayılan olarak tema rengi; FluxWP'de indigo), sabit yeşil kaldırıldı.

## 0.3.0 (2026-10-28)

- **Model seçici:** ayrı sağlayıcı seçicisi kalktı. Model düğmesinde sağlayıcı ve model birlikte yazar ("fal.ai · Nano Banana 2.1"); açılınca önce sağlayıcı (anahtarı olanlar üstte, kaç model sunduğu yanında), sonra o sağlayıcının modelleri seçilir. Anahtarı girilmemiş sağlayıcı soluk görünür.
- **Kompakt üretim kutusu:** en-boy, çözünürlük, kalite, adet/süre ve ses referans "+" satırına, en sağa taşındı; alt satırda yalnız Görsel/Video, model ve düğmeler kalır. İstem kutusu en çok 2 satır büyür, fazlası kutuda kayar. Telefonda Görsel/Video yalnız simge.
- **Pano (yöneticiler):** sol menüde "Pano": 7/30/90 gün; üretim, harcama (görsel/video ve metin ayrı), üretim başına ortalama, hata oranı, kişi sayısı ve depolama; günlük üretim ve günlük harcama grafikleri; model, sağlayıcı ve kişi tabloları; Flux AI harcama sınırı; son hatalar. Harcama Flux AI kayıtlarından okunur. REST: `GET stats`.

## 0.2.2 (2026-10-28)

- Pencereler (görsel ayrıntısı, Kişiler, onaylar) ve açılır menüler sayfanın en üst katmanında: Flux Admin uygulama modunda karartma üst barı ve sol menüyü de örter, pencere ekranın ortasında açılır; menüye takılmaz, kapat düğmesi ve başlık kesilmez, kenarda ince çizgi kalmaz.

## 0.2.1 (2026-10-28)

- **Flux Admin uygulama modu:** Stüdyo artık üst barın altında ve sol menünün sağında açılır (Görevler, WhatsApp gibi); kendi sol paneli ve başlığı menünün arkasında kalmaz. Menü genişleyince Stüdyo da kayar. Telefonda alt bar açıksa Stüdyo onun üstünde biter.
- Açılır pencereler, menüler ve bildirimler sayfa üstü katmanlarda.

## 0.2.0 (2026-10-28)

- **Anında kuyruk:** Üret'e basınca kayıt hemen galeride "Sırada" kartı olarak döner; sağlayıcıya gönderim (45 sn sürebilir) cevap gittikten sonra yapılır (LiteSpeed/FPM bağlantıyı erken kapatır). Sunucu erken kapatamazsa gönderim 45 sn sonra ilk sorguda yapılır. Gönderilmeden iptal edilen iş sağlayıcıya hiç gitmez; gönderim sırasında iptal edilirse ardından durdurulur.
- İstem kutusu üretimden sonra boşalır (ayarlar karttan "Ayarları kullan" ile geri gelir).
- **Sağlayıcı görünür:** üretim kutusunda sağlayıcı seçimi her zaman var ("Otomatik (fal.ai)" gibi); bekleyen ve başarısız kartlarda model · sağlayıcı yazar.

## 0.1.0 (2026-10-27)

İlk sürüm. Banana Studio'dan (MIT) uyarlandı; altyapı Flux AI.

- **Üretim:** görsel ve video. Model, oran, çözünürlük, kalite, adet, süre, ses ve sağlayıcı seçimi; tahmini maliyet düğmede. Üretim Flux AI 0.6.0 medya API'si ile (fal.ai, EvoLink, OpenAI, Gemini, OpenRouter). Uzun işler arka planda sürer, galeri 4 sn'de bir sorar (uzadıkça seyrekleşir, sekme gizliyken durur).
- **Referanslar:** dosya yükle, yapıştır, sürükle-bırak, galeriden seç; her referansa rol (stil, ürün, arka plan, kişi, ilk/son kare). Kayıtlı referanslar.
- **Kişiler:** kişi başına 1-8 yüz fotoğrafı; istemde `@ad` yazınca kimlik referansı olarak gider (en çok 4 foto).
- **Geliştir:** taslağı seçili modele uygun ayrıntılı İngilizce isteme çevirir (Flux AI metin modeli); taslağa dönülebilir.
- **Beyin fırtınası:** bir fikirden 2-8 farklı istem (genel ya da YouTube küçük resmi); seçilenler tek seferde üretilir.
- **Alan düzenleme:** fırça/dikdörtgen/silgi ile maske; yalnız seçili alan değişir. Önce/sonra karşılaştırma kaydırıcısı.
- **Galeri:** klasör ağacı (alt klasör, ekiple paylaşım, sürükleyerek taşıma), favoriler, arama, çoklu seçim, sonsuz kaydırma. Görüntüleyici: indir, ayarları tekrar kullan, referans yap, Medya kitaplığına ekle, taşı, sil.
- **Şablonlar:** istem + ayarlar + referanslar kaydedilir, ekiple paylaşılabilir.
- **Yetkiler:** `flxst_use` (kendi galerisi ve paylaşılan klasörler), `flxst_manage` (herkesin galerisi, ayarlar). Flux Roles'e bildirilir.
- **Bağlantılar:** Flux App `flxapp_modules` (masaüstü/mobil uygulamada yerel ekran), Flux Admin uygulama geçişi, Flux AI Bağlı sistemler, Sistem kontrolü.
- Açık ve koyu tema (Flux Admin tercihini izler), mobil uyumlu. TR + EN.
