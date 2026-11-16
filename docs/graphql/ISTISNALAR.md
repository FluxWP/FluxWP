# GraphQL istisnaları

GraphQL'e taşınmayan, REST ya da kendi protokolünde kalan uçların **tek** listesi (plan §0).
Yeni istisna satır eklenerek ve PR'da gerekçeyle açılır. Listede olmayan bir REST ucu paket GraphQL'e geçerken
taşınır (geçiş süresince REST de çalışır).

**Tek pakete ait istisna bu dosyaya değil, paketin durum dosyasına yazılır:** `docs/graphql/durum/<slug>.md`
içinde `## İstisnalar` tablosu (aynı sütunlar). Böylece paralel PR'lar bu dosyada çakışmaz (KURALLAR §12).
Bu dosyada yalnız birden çok paketi ilgilendiren satırlar kalır. Tam liste:

```sh
bun tools/graphql/durum.ts --istisnalar
```

## Kalıcı istisnalar

| Uç / akış | Paket | Neden | GraphQL tarafı |
|---|---|---|---|
| Ödeme webhook ve 3DS dönüşleri (iyzico, EsnekPOS, WC gateway'leri) | flx-donate, flx-commerce | Banka belirli URL'e form POST atar | Ödeme durumu sorgusu GraphQL'de |
| OAuth: `.well-known/*`, `/oauth/token`, `/authorize` | flx-app, flx-ai | Standart protokol | — |
| MCP sunucusu `/mcp` (JSON-RPC) | flx-ai | MCP protokolü | Araçlar aynı servisleri çağırır |
| AI sohbet akışı (SSE) | flx-ai, flx-chat | WPGraphQL'de subscription/stream yok | Başlatma ve geçmiş GraphQL'de |
| Dosya yükleme `POST /wp-json/flx/v1/upload` | flx-graphql | Multipart; tek ortak uç | Mutation `uploadId` alır |
| PWA protokol uçları: `admin-ajax.php?action=flxa_manifest`, `flxa_sw`, `flxa_offline`, `flxa_splash`, `flxa_auth` | flx-admin | Tarayıcı ve service worker'ın beklediği dosya/yanıt biçimi (manifest, JS, HTML, görsel) ve oturum köprüsü; oturum kontrolünden önce çalışır | PWA ayarları (`pwa`, `pwaName`, `pwaShort`) `flxAdminSettings` içinde |
| WooCommerce Store API (sepet / checkout) | flx-commerce, flx-donate | Gateway'ler Store API'ye bağlı (EsnekPOS `PaymentContext`, iyzico) | `flxCart` + `flxCart*` mutation'ları (Flux GraphQL 0.3.0) içeride `rest_do_request` ile Store API'yi çağırır; checkout (ödeme) `POST /wc/store/v1/checkout`'ta kalır. Bkz. `SEPET.md` |
| Flux SEO `flxseo_audit_tick` (arka plan denetim tetiği, nopriv) | flx-seo | Hostinger'da WP cron tetiklenmiyor; loopback isteği, jetonla doğrulanır | Denetim durumu `flxSeoAuditHistory.running`, çalıştırma `flxSeoAuditRun` |
| FluxWP/updater, FluxWP/whatsapp-api | — | WordPress eklentisi değil, ayrı servis | Kapsam dışı |
| FluxWP/quiz (flx-quiz, flx-preset-arifiye) | — | Ahmet kararı: dokunulmaz | Kapsam dışı |
| Blok editörü iç REST uçları: `fxb-design-tokens/v1` (Stil Kütüphanesi), `kbp/v1`, `fxb-lottieanimation/v1`, `fxb-vector/v1`, form entegrasyonları (`fxb-mailerlite/v1`, `fxb-fluentcrm/v1`, `fxb-activecampaign/v1`, `fxb-getresponse/v1`), header/navigasyon/gelişmiş form otomatik taslak | flx-blocks | Fork'un editör içi kodu (plan §5 Faz 4: editöre dokunulmaz); yalnız editör ve Stil Kütüphanesi uygulaması çağırır | İçerik `flxBlocks`, kütüphane ve pano GraphQL'de |
| Siteler arası tasarım kütüphanesi `GET /wp-json/flx-blocks/v1/library` | flx-blocks | Başka Flux siteleri anahtarla çağırır (sürümden bağımsız protokol, LIBRARY.md) | Sitenin kendi okuması `flxBlocksLibrary` |

## Geçici istisnalar

Kaldırılma koşulu olan uçlar. Koşul gerçekleşince satır silinir, uç kaldırılır.

| Uç | Paket | Neden | Ne zaman kalkar |
|---|---|---|---|
| FluxApp mobil/masaüstü eski sürümlerinin kullandığı REST uçları | flx-app ve modül paketleri | Mağazadaki eski sürümler | En düşük desteklenen uygulama sürümü GraphQL istemcisine geçince (Faz 7) |
| Flux SEO REST `flx-seo/v1/audit` (GET/POST) | flx-seo | Eski FluxApp sürümleri | Faz 7 (eski uygulama sürümleri) |
| Flux Tasks: pilot dışındaki `flx-tasks/v1` uçları (sync, yapı, belgeler, ayarlar, pano, dosya) | flx-tasks | Pilot yalnız görev dilimini taşıdı; ekran REST yedekli adaptörle çalışır | Faz 1 flx-tasks paket PR'ı (ESLEME/flx-tasks.md) |
| Flux Admin: pano hızlı görev formu `flxa_task_form` + `flxa_task_create` | flx-admin | Pano JS'i (yan kabuk) bu iki AJAX'a bağlı; görev oluşturma zaten `flxTaskCreate` ile GraphQL'de. Çift yol yeni mantık içermez (flx-tasks fonksiyonlarını çağırır) | Pano JS'i GraphQL istemcisine taşınınca (kabuk/tema işi, Faz 2) |
| Blok formu gönderimi: admin-ajax `fxb_process_ajax_submit`, `fxb_process_advanced_form_submit` | flx-blocks | Form gönderimi flx-forms paketinin işi | flx-forms GraphQL PR'ı (`flxFormSubmit`), docs/flx-blocks/GRAPHQL.md |
| FluxApp OAuth (`/oauth/*`), OTP (`/otp/*`), telefon (`/me/phone*`), cihazlar (`/devices*`), ayar köprüsü (`/settings*`), marka (`/app/brand`) | flx-app | OAuth/OTP protokol uçları; cihaz ve ayar köprüsü ekran akışı yok | Bootstrap, me, sync, config, manifest, dashboard, prefs, oturum GraphQL'de (`docs/flx-app/GRAPHQL.md`) |
| FluxApp bootstrap/me/sync/config/manifest/dashboard/prefs/session REST uçları | flx-app | Mağazadaki eski uygulama sürümleri | Faz 7 (satır 1 ile birlikte kalkar) |

## Satır ekleme

```
| <uç ya da akış> | <slug> | <neden: dış sistem / protokol / ölçülmüş performans> | <GraphQL'de ne var> |
```

"Daha kolay" ya da "zaman yok" gerekçe değildir. Performans gerekçesi canlı ölçümle (sorgu süresi, Flux Speed) yazılır.
