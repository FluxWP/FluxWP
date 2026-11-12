# Flux Block Editor 0.1.0 — kurulum

## Ne var
- Blok eklentisi (`flx-blocks`, bloklar `flx/*`). 35 blok + alt bloklar (toplam 63), header/menü oluşturucu, gelişmiş form, Stil kütüphanesi (tasarım tokenları), blok varsayılanları.
- Dışarıya istek yok: lisans, telemetri, bulut kütüphane, Pexels, uzak AI kaldırıldı. Lottie oynatıcı ve video ikonları yerelden. Google Haritalar anahtarsız gömme. Google Fonts varsayılan olarak siteden sunulur.
- Türkçe çeviri (3246 metin). Renkli menü logosu ("Blok Editörü"). FluxWP tema renkleri ve koyu modu bloklara geçer.
- Sistem kontrolü satırları: Google Fonts, form spam koruması, kapalı bloklar.

## Kurulum
1. FluxWP Panel > Paketler > Flux Block Editor > Kur (ya da zip'i Eklentiler > Yükle).
2. Etkinleştir. Sol menüde "Blok Editörü" çıkar; Stil kütüphanesi, Formlar, Menüler, Header'lar alt menülerde.
3. Sayfa düzenlerken ekleyicide "Flux Block Editor" kategorisi.

## Bilinen sınırlar (sonraki fazlar)
- Blok kütüphanesi (link ile kütüphane ekleme), Flux AI + MCP, preset ayar eşitleme, panel tasarımı, Pro bloklar henüz yok (Faz 2–5).
- Formların Flux reCAPTCHA / Flux CRM / Flux Mail köprüsü Faz 4c'de. Şimdilik formun kendi captcha alanları ve e-posta gönderimi çalışır.
- FluxWP tema adaptörünün kalanı (header, içerik genişliği) Faz 4.te.

## Test kapsamı
- Cloud: WP 6.7 nightly + SQLite + FluxWP tema, `bin/test-update.sh` temiz kurulum geçti.
- Playwright: tüm bloklar eklenip kaydedildi, yeniden yüklemede geçersiz blok yok, ön yüz 200, dış host yalnız WordPress'in gravatar'ı.
- Gerçek sitede test edilmedi.
