---
name: flx-readme-tanitim
description: Bir FluxWP paketinin tanıtım README'sini (EN + TR), ekran görüntülerini ya da demo GIF'ini yeniden üretirken veya yeni pakete eklerken kullan. Paket arayüzü değişince README görsellerini güncellemek için.
---

# Tanıtım README, ekran görüntüsü ve GIF

Her şey `tools/readme-media/` altında; ayrıntılı kullanım `tools/readme-media/README.md`.

1. `bash tools/readme-media/setup.sh` (bir kez; yerel WP + SQLite + WooCommerce, paketler symlink, sahte sunucular).
2. Arayüz değiştiyse `cfg/<slug>.json` içindeki URL/tıklama adımlarını, özellikler değiştiyse `text/<slug>.py` metnini güncelle.
3. `bash tools/readme-media/run.sh <slug> [plugins|themes]` → `docs/`, `README.md`, `README.tr.md`, kök README tablosu.
4. Görselleri gözle kontrol et (boş durum, hata mesajı, `127.0.0.1` görünmesin), zip'te `docs/`/README olmadığını doğrula.
5. Sonuç mesajına birkaç ekran görüntüsü ekle (`/mnt/project-files/<paket>/`), PR aç.

Kurallar: yalnız README/docs/tools değişir; paket kodu, sürüm ve CHANGELOG'a dokunulmaz. Demo verisinde gerçek kişi, telefon ya da anahtar olmaz (destek hattı ve kurum bilgileri hariç).
Yeni paket: `cfg/<slug>.json` + `text/<slug>.py` (+ gerekirse `seed/seed-<ad>.php`) ekle, `bin/readme-desc.tsv`'ye kısa açıklama yaz.
