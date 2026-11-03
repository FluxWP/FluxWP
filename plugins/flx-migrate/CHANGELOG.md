# Değişiklikler

## 0.1.2
- FluxWP imzası: Flux ekranlarının alt satırında küçük FluxWP logosu ve fluxwp.com bağlantısı. Ortak modül `includes/flx-brand` (kaynak FluxWP/fluxwp `tools/brand`); kurulu Flux paketlerinden en yeni sürümü bir kez yüklenir, `flx_brand_show` filtresiyle gizlenir.

## 0.1.1
- Yeni logo (FluxWP ikon seti, grup rengi). `assets/icon-pastel.svg` Flux Admin › Görünüm › Paket logoları Pastel iken kullanılır.

## 0.1.0
- İlk sürüm: eski paket adlarıyla kaydedilmiş verileri FluxWP adlarına taşır (tablolar, tablo içerikleri, ayarlar, tema ve tema ayarları, meta, yazı türleri ve içerikteki blok/kısa kod/class adları, taksonomiler, yükleme klasörleri).
- Araçlar › Flux Migrate: önce Tara (değişiklik yapmaz), sonra Aktar. Parça parça çalışır, yarıda kalırsa yeniden başlatılabilir.
- WP-CLI: `wp flx-migrate scan`, `wp flx-migrate run`.
