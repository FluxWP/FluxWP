# Flux AI: zamanlanmış görevler, skill'ler ve botlar (plan)

Amaç: brifing, bağışçı takibi, site performansı, haber tarama, reklam taslakları gibi işler sabit özellik olarak tek tek eklentilere gömülmesin. Hepsi Flux AI & MCP'de tek bir sistemle, sohbetten kurulup değiştirilsin. Diğer eklentiler (CRM, Donate, Ads, SEO, Speed…) yalnız MCP/AI araçlarını açar; görevler ve botlar bu araçları kullanır.

## Faz 1: zamanlanmış görevler (0.11.0, yayında)
- Görev = istem + zamanlama. Sahibinin yetkisiyle ortak sohbet motorunda çalışır, raporu kendi sohbetine yazar.
- Sohbetten kur/değiştir: `flxai_task_*` araçları (sohbet + MCP), değiştirenler onay kartı ister.
- Değişiklik yapan araç onay bekler: taslak/onay akışının ilk hali budur.
- Yönetim ekranı, Şimdi çalıştır, duraklat, sistem kontrolü. WP-Cron + istek sonrası loopback (Hostinger'da cron gelmese de çalışır).

## Faz 2: skill'ler ve bildirim (0.12.0, yayında)
- **Skill** = adlı, sürümlü talimat paketi (ör. "Günlük brifing", "Bağışçı incelemesi", "Reklam taslağı"). Alanlar: ad, açıklama, talimat (Markdown), önerilen araçlar, örnek çıktı. Görev ve sohbet bir skill'i adla çağırır; skill düzenlenince ona bağlı tüm görevler güncellenir.
- Eklentiler hazır skill getirebilir (`flxai_skills` filtresi); kullanıcı kopyalayıp değiştirir. Dışa/içe aktarma (SKILL.md biçimi).
- Bildirim: görev bitince e-posta / WhatsApp / admin bildirimi (Flux Mail üzerinden), "yalnız önemli bir şey varsa" seçeneği.
- Onay kuyruğu ekranı: tüm görevlerin onay bekleyen işlemleri tek listede (onayla, reddet, düzenleyip onayla).

## Faz 2b: çalışma alanı (0.13.0)
- Her sohbet ve görevin Flux Files üzerinden kendi klasörü; skill'ler `.skills/<ad>/SKILL.md`.
- Mini git: kendi klasöründeki yazmalar tur başına commit; site dosyalarına yazmalar onaylı değişiklik isteği (PR).
- Sert kurallar: kendi klasörü dışı varsayılan kapalı, başka sohbetler her zaman kapalı, hassas dosya/çekirdek yok, 1 MB / 50 dosya.
- Botların dosya alanı (Faz 3) bu altyapıyı kullanır: `bots/<bot>/` aynı politika ile.

## Faz 3: botlar
- **Bot** = kalıcı kimlik: ad, avatar, rol talimatı, izinli araç listesi (beyaz liste), izinli skill'ler, model, bütçe sınırı.
- Kısıtlı yetki: bot sahibinin yetkisini aşamaz; ayrıca araç beyaz listesi ve okuma/yazma ayrımı. Ön ayarlar: "Pazarlama botu", "Bağışçı ilişkileri botu", "Site bakım botu".
- Bot görevleri: Faz 1 görevleri `bot` sütunuyla bota bağlanır.
- **Dosya alanı**: 0.13.0 çalışma alanı (`wp-content/flx-ai/bots/<bot>/`), Flux Files araçları ve aynı güvenlik kuralları. Eksik: URL'den indirme aracı.
- **Hafıza**: bot başına kalıcı notlar (tercihler, düzeltmeler). Kullanıcı revize verdikçe bot hafızasına "bundan sonra şöyle" kaydı düşer, sonraki çalışmalarda talimata eklenir. Hafıza ekranda görünür ve silinebilir.

## Faz 4: taslak akışları (reklam vb.)
- Bot çıktısı "taslak" olarak saklanır (ör. Flux Ads kampanya/metin taslağı). Kullanıcı düzenler, onaylar ya da revize ister; revize notu bot hafızasına gider.
- Flux Ads, Flux Social, Flux Mail taslak araçlarını MCP ile açar; yayın yine onaylı araçla olur.

## Açık sorular
- Bildirim kanalı varsayılanı (e-posta mı WhatsApp mı).
- Bot başına günlük bütçe sınırı gerekli mi (genel OpenRouter sınırına ek).
