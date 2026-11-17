# Flux Headless — kurulum

1. Flux GraphQL ve Flux Headless'ı kurup etkinleştirin (Flux SEO + Flux Lang önerilir).
2. WordPress'i ayrı adrese alın (ör. `cms.example.com`); ön yüz asıl adreste (`www.example.com`) çalışır.
3. FluxWP › Headless › **Ön yüz ekle**: ad, adres, diller (boş = hepsi), webhook adresi
   (ör. `https://www.example.com/api/revalidate`), **Üret** ile secret (bir daha gösterilmez), istenirse önizleme yolu (`/api/preview`).
   Kaydedince panel ön yüzün **bağlantısını** bir kez gösterir; ön yüze yalnız bu iki satır yeter
   (önizleme dahil): `FLUX_WP_URL=https://cms.example.com` ve `FLUX_KEY=<ön yüz kimliği>.<secret>`.
   Yeni anahtar için secret'ı yeniden üretip kaydedin.
4. **Webhook'u test et**; alttaki kayıtta ön yüzün cevabı görünür.
5. Ön yüz yayına girince **WordPress sayfaları**: "Göster ama aramadan gizle" ya da "Ziyaretçileri ön yüze
   gönder".
6. Ön yüz geliştiricisine: `FLUX_WP_URL` + `FLUX_KEY` ve
   `wp-content/plugins/flx-headless/graphql/persisted-queries.json`. Rehber: `docs/flx-headless/README.md`.

Çoklu site: en fazla 20 ön yüz (dil ya da alan adı başına). Ayarlar site başına; multisite'ta her alt site
kendi ön yüzlerini sunar.

Bilinen sınırlar: Flux Lang yoksa `alternates` boş. Önizleme anahtarla imzalanır (uygulama parolası gerekmez); ön yüz
sunucusunun saati 5 dakikadan fazla kaymamalı. Webhook çağrısı içerik kaydını beklemez; ulaşmazsa ön yüz önbelleği süre dolunca yenilenir.
