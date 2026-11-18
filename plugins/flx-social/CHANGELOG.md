# Değişiklik günlüğü

## 0.5.1
- Ekran ortak FluxWP kabuğuna taşındı: başlık, simge, sekmeler, kenar boşlukları, en fazla genişlik ve güvenli alan (safe area) bütün Flux ekranlarında aynı. Sekmeler eski Flux Admin sekmeleri gibi alt çizgili; taşarsa sayfa değil sekme çubuğu kayar. Ortak modül paketi yenilendi. Sosyal Medya ekranı Flux Admin uygulama modunda sol şeridin altına giriyordu (yan menü kesiliyordu); artık şeridin yanında açılır.

## 0.5.0
- **Klasik ekran kaldırıldı.** Sosyal Medya yalnız React ekranıyla açılır; "Klasik görünüm" bağlantısı, görünüm değiştirme (`flxsm_ui`), eski istemci (assets/js, assets/css/app.css) ve includes/js-strings.php silindi.
- Ekran açılamazsa (Flux GraphQL hazır değil, paket dosyası eksik, bundle yüklenmedi) boş sayfa yerine "Bu ekran yüklenemedi" kutusu ve **Sayfayı yenile** düğmesi çıkar.

## 0.4.1
- wp-admin ekranı Flux GraphQL ekran kabuğundan açılır: ilk veri PHP'de sayfaya basılır (yükleniyor göstergesi yok), menüden diğer Flux ekranlarına sayfa yenilenmeden geçilir. Ekranın parçaları sayfa başında birlikte iner (modulepreload).
- Ortak modül paketi yenilendi.

## 0.4.0
- Mobil uygulama manifesti GraphQL'e geçti: gönderiler (arama, durum filtresi), gönderi ayrıntısı, takvim, hesaplar; onayla, geri gönder, şimdi yayımla, sil, hesabı aç/kapat. Metinler İngilizce kaynak + Türkçe çeviri.

## 0.3.1
- Tarih alanları ortak FluxWP tarih seçicisini kullanır: sitenin dilinde ay/gün adları, Pazartesi başlangıç, hızlı seçim listesi, yazılabilir tarih ve saat, Uygula düğmesi, açık/koyu, telefonda alttan açılan pencere. Tarayıcının kendi takvimi (İngilizce çıkan) kalktı.

## 0.3.0
- GraphQL: gönderi, hesap, ayar, istatistik, medya ve yazım yardımı alanları `/graphql` üzerinden (13 sorgu, 22 mutation, 34 kayıtlı işlem). Belirteç, uygulama anahtarı ve hesap meta'sı hiçbir alanda dönmez; dış zamanlayıcı adresi maskeli gelir, tam adres yalnız yöneticiye `flxSocialTickKeyReveal` ile. Servis katmanı ve readonly DTO'lar (`includes/services`); REST uçları aynı servisleri çağırır, çıktı değişmedi.
- Yeni Sosyal Medya ekranı (React): pano, gönderiler ve toplu işlemler, onaylar, takvim (sürükle bırak), hesaplar (bağlama, seçim adımı, WhatsApp), ayarlar, oluşturucu (hesap başına metin/ortam/seçenek, AI, önizleme) ve gönderi ayrıntısı. wp-admin'de varsayılan; "Klasik görünüm" bağlantısıyla eski ekrana dönülür. FluxApp modül kimliği `flxsm-social` (eski sürümler yönetim sayfasını açar).
- REST'te kalanlar: OAuth, dış cron `/tick`, dosya yükleme (`/flx/v1/upload`, bağlam `flx-social/media`), eski `/calendar`.
- Gereksinim: PHP 8.1, WordPress 6.5, Flux GraphQL (`Requires Plugins`).
- Türkçe çeviriler ve 10 dillik ekran sözlükleri güncellendi.

## 0.2.7
- Tarayıcının onay/uyarı/giriş pencereleri (confirm, alert, prompt) yerine ortak FluxWP modalı (flx-brand flxDialog): yeni tarz, telefonda alttan açılır, koyu modla uyumlu. Silme gibi geri alınamaz işlemlerde kırmızı onay düğmesi.

## 0.2.6
- Uygulama gizli anahtarları ve hesap belirteçleri bir sonraki kayıtta ortak mühürle saklanır. Eski kayıtlar açılır. WordPress tuzları değişirse bunları yeniden girin.

## 0.2.5
- Sosyal API yanıtları ortak önbelleksiz başlıkları kullanır. CDN işareti durur.

## 0.2.4
- Çevirilerde yer tutucuların (%s, %d, {ad}) yanında silinmiş boşluklar geri geldi (TR, DE, ES, FR, PT, RU, AR); .po/.mo/.l10n.php/JSON paketleri güncellendi.

## 0.2.3
- Remove legacy translation response framing from source IDs and all language packs; headings no longer display literal newline escapes.
- Refresh current English and Turkish product screenshots and browser walkthroughs, including mobile and dark views.


## 0.2.2
- Add native WordPress language packs for German, Spanish, French, Brazilian Portuguese, Japanese, Simplified Chinese, Arabic and Russian. English-keyed JSON catalogs preserve legacy gettext compatibility; no Flux Lang dependency.

## 0.2.1
- Güvenlik (CodeQL): `h()` çocukları yalnız DOM düğümü ya da düz metin olarak eklenir.

## 0.2.0
- Uygulama: gönderiler, takvim ve hesaplar. Medya yazarı yerelde sonra açılır (`flxsm-composer`). Takvim ucu: `flx-social/v1/calendar`.

## 0.1.8
- Güvenlik (CodeQL): `h()` yardımcısındaki kullanılmayan `html` özniteliği kaldırıldı; adres çubuğundan gelen değerler hiçbir yolla HTML olarak eklenmez.

## 0.1.7
- Metinler: MCP sunucusu artık Flux AI & MCP eklentisinde; "Flux SEO & MCP" ve "Flux AI ›" adresleri "Flux AI & MCP" oldu.

## 0.1.6
- Açık tema: uyarı, hata ve mavi metinler (ör. "Henüz hesap bağlı değil" bandı, yaklaşan gönderi saatleri) yeterli kontrastta. Uygulama başlığında Flux Social paket ikonu ve adı.

## 0.1.5
- Koyu modda yenilemede açık tema yanıp sönmesi giderildi: tema, uygulama kabı çizilir çizilmez (app.js beklenmeden) uygulanır.

## 0.1.4
- Yeşilimsi sabit palet yerine Flux Admin vurgu renginden türetilen tonlar; sönük (faint) metinler daha okunur.

## 0.1.3
- FluxWP imzası: Flux ekranlarının alt satırında küçük FluxWP logosu ve fluxwp.com bağlantısı. Ortak modül `includes/flx-brand` (kaynak FluxWP/fluxwp `tools/brand`); kurulu Flux paketlerinden en yeni sürümü bir kez yüklenir, `flx_brand_show` filtresiyle gizlenir.

## 0.1.2
- Yeni logo (FluxWP ikon seti, grup rengi). `assets/icon-pastel.svg` Flux Admin › Görünüm › Paket logoları Pastel iken kullanılır.

## 0.1.1
- Vurgu rengi Flux Admin'den (varsayılan olarak tema rengi; FluxWP'de indigo), sabit yeşil kaldırıldı.

## 0.1.0
- İlk sürüm (Faz 1). Mixpost/Postiz fikirleri sitede çalışan kendi eklentimize taşındı.
- Ağlar: Facebook sayfası, Instagram (Facebook girişi ve Instagram girişi), YouTube, X (Free/Basic/Pro/Pay-as-you-go katmanı, Premium uzun metin), LinkedIn (profil + sayfa), TikTok (taslak/doğrudan mod), Pinterest (sandbox seçeneği), Google İşletme Profili, WhatsApp Durum/grup (Evolution, Flux Mail/Çağrı Merkezi bağlantısı).
- Uygulama anahtarları panelden (Ayarlar › Uygulamalar); gizli değerler sodium ile şifreli, yalnız yazılır.
- Gönderi oluşturma: ortak metin + hesap başına ayrı metin/medya/seçenek, ağ kuralları denetimi, önizleme, taslak/onaya gönder/zamanla/şimdi paylaş, tekrar, imza, UTM.
- Onay akışı (flxsm_post / flxsm_publish / flxsm_manage yetkileri, Flux Roles'e kayıtlı).
- Yayın motoru: veritabanı kuyruğu, kilit, adım adım yükleme (Hostinger 60 sn sınırı), WP-Cron + loopback + panel nabzı + dış ping (/tick?key=).
- Gönderilenleri ağda düzenleme/silme (desteklenen ağlarda), yeniden deneme.
- Pano: günlük paylaşım grafiği, ağ bazında tablo, takipçi değişimi, en iyi gönderiler, yaklaşanlar, hatalar.
- Takvim (ay/liste, sürükle-bırak yeniden zamanlama), gönderi listesi (filtre, toplu işlem).
- Otomatik paylaşım: sitede yazı yayınlanınca taslak ya da zamanlanmış sosyal gönderi.
- MCP araçları (Flux SEO & MCP): flxsm_accounts, flxsm_posts, flxsm_post_get, flxsm_post_create, flxsm_post_update, flxsm_post_delete, flxsm_remote_edit, flxsm_stats, flxsm_ai_write.
- Flux AI ile metin yaz/iyileştir/kısalt/etiket/çevir/ağa uyarla; Flux Studio'dan medya seçimi.
- Sistem kontrolü, Flux App modülü (social), Flux Admin uygulama ekranı, TR + EN.
