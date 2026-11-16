# flx-blocks 0.3.0

- Faz: 4
- Durum: ✓
- PR: #312

`gql/flx-blocks-1` (Faz 4): `ContentNode.flxBlocks` (blok ağacı + işlenmiş HTML + CSS/stil/betik/modül/yazı tipi/şema, `headHtml`/`footerHtml`), `flxBlocksSiteStyles`, `flxBlocksRender`; tasarım kütüphanesi (4 sorgu + 3 mutation) ve pano (2 sorgu + 2 mutation); 13 kayıtlı işlem, sözleşme testi 65/65 (canlı sayfa CSS'i ile karşılaştırmalı). React ekranı (`flxbk-dashboard`) klasikle eşit + tasarım kütüphanesi, wp-admin varsayılanı; klasik sayfadaki bağlantıyla. Editör içi REST istisna. flx-forms bağlantısı `docs/flx-blocks/GRAPHQL.md`. Eşleme `ESLEME/flx-blocks.md`

## İstisnalar

| Uç / akış | Paket | Neden | GraphQL tarafı |
|---|---|---|---|
| Blok editörü iç REST uçları: `fxb-design-tokens/v1` (Stil Kütüphanesi), `kbp/v1`, `fxb-lottieanimation/v1`, `fxb-vector/v1`, form entegrasyonları (`fxb-mailerlite/v1`, `fxb-fluentcrm/v1`, `fxb-activecampaign/v1`, `fxb-getresponse/v1`), header/navigasyon/gelişmiş form otomatik taslak | flx-blocks | Fork'un editör içi kodu (plan §5 Faz 4: editöre dokunulmaz); yalnız editör ve Stil Kütüphanesi uygulaması çağırır | İçerik `flxBlocks`, kütüphane ve pano GraphQL'de |
| Siteler arası tasarım kütüphanesi `GET /wp-json/flx-blocks/v1/library` | flx-blocks | Başka Flux siteleri anahtarla çağırır (sürümden bağımsız protokol, LIBRARY.md) | Sitenin kendi okuması `flxBlocksLibrary` |
| Blok formu gönderimi: admin-ajax `fxb_process_ajax_submit`, `fxb_process_advanced_form_submit` | flx-blocks | Form gönderimi flx-forms paketinin işi | Geçici; kalkar: flx-forms GraphQL PR'ı (`flxFormSubmit`), docs/flx-blocks/GRAPHQL.md |
