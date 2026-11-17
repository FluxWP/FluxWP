# Flux Headless 0.1.0 — kurulum

1. Flux GraphQL ve Flux Headless'ı kurup etkinleştirin (Flux SEO + Flux Lang önerilir).
2. WordPress'i ayrı adrese alın (ör. `cms.example.com`); ön yüz asıl adreste (`www.example.com`) çalışır.
3. FluxWP › Headless › **Ön yüz ekle**: ad, adres, diller (boş = hepsi), webhook adresi
   (ör. `https://www.example.com/api/revalidate`), **Üret** ile secret (ön yüze `FLUX_REVALIDATE_SECRET`
   olarak kopyalanır, bir daha gösterilmez), istenirse önizleme yolu (`/api/preview`).
4. **Webhook'u test et**; alttaki kayıtta ön yüzün cevabı görünür.
5. Ön yüz yayına girince **WordPress sayfaları**: "Göster ama aramadan gizle" ya da "Ziyaretçileri ön yüze
   gönder".
6. Ön yüz geliştiricisine: GraphQL adresi, ön yüz kimliği, secret ve
   `wp-content/plugins/flx-headless/graphql/persisted-queries.json` (paneldeki "Ön yüzün ortam
   değişkenleri" kutusu bunları hazır verir). Rehber: `docs/flx-headless/README.md`.

Çoklu site: en fazla 20 ön yüz (dil ya da alan adı başına). Ayarlar site başına; multisite'ta her alt site
kendi ön yüzlerini sunar.

Bilinen sınırlar: Flux Lang yoksa `alternates` boş. Önizleme için ön yüz sunucusunda bir editörün uygulama
parolası gerekir. Webhook çağrısı içerik kaydını beklemez; ulaşmazsa ön yüz önbelleği süre dolunca yenilenir.
