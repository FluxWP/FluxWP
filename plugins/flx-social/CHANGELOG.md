# Değişiklik günlüğü

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
