# Flux Headless

## 0.3.0
- FluxWP mobil uygulamasında Headless ekranı native çizilir (ortak manifest `app/manifest.json`, GraphQL): ön yüz listesi, yeni ön yüz, aç/kapat, varsayılan yap, test webhook'u, sil, tüm sayfaları yenile, WordPress sayfaları modu, webhook kaydı. Masaüstü panel React ekranını kullanmaya devam eder.

## 0.2.0
- Tam sayfa modu: ön yüz bir sayfayı `?flxhl_page=<ön yüz kimliği>` ile ister, WordPress temanın çizdiği sayfanın tamamını (şablon, header/footer, bloklar, CSS, JS) linkleri ön yüze çevrilmiş olarak verir. `/wp-content/`, `/wp-includes/`, `/wp-json/` ve admin-ajax de ön yüz üzerinden gider (fontlar, REST, AJAX aynı origin). `X-Flx-Cache-Tags` başlığı webhook etiketlerini taşır; sepet, ödeme ve hesap sayfaları `X-Flx-Live: 1` ile işaretlenir (önbelleğe girmez). Yönlendirmeler ön yüze çevrilir. Filtreler: `flx_headless_page_tags`, `flx_headless_live_paths`.
- Next örneği (apps/headless-next) varsayılan olarak bu modla çalışır: site WordPress'le birebir aynı görünür. React tasarımı `FLUX_RENDER=react` ile.

## 0.1.3
- Ön yüz ekleme/düzenleme ve bağlantı pencereleri wp-admin'de sol menünün üstünde açılır (@fluxwp/ui 0.3.2).
- Yeni ön yüz için yalnız ad ve adres girilir: webhook adresi (`<adres>/api/revalidate`), önizleme yolu (`/api/preview`) ve secret kendiliğinden dolar, "Gelişmiş" altında değiştirilebilir.

## 0.1.2
- Ön yüz yalnız iki değişkenle bağlanır: `FLUX_WP_URL` (WordPress adresi) ve `FLUX_KEY` (bağlantı anahtarı = ön yüz kimliği + secret). Yeni secret ile kaydedince panel bu iki satırı bir kez gösterir (Kopyala).
- Önizleme anahtarla: ön yüz sunucusu taslak isteğini `X-Flx-Preview` başlığıyla imzalar (5 dakika geçerli), yalnız adı geçen taslak o istekte açılır. Uygulama parolası artık gerekmez (hâlâ desteklenir).
- `flxHeadlessSettings.cmsUrl`: ön yüze verilecek WordPress adresi.
- Next.js örneği `FLUX_WP_URL` + `FLUX_KEY` ile çalışır; eski ayrı değişkenler de geçerli.

## 0.1.1
- `flxSite.logoOnDark`: footer gibi koyu zeminler için logo (kurum profilindeki açık renk logo; yoksa site logosu). Logo seçilmemişse kurum profilindeki logo kullanılır.
- Footer'daki gezinme bloklarının adı, hemen üstlerindeki başlıktan gelir ("Mağaza", "Kurumsal" sütunları).
- Sayfa listesi yedeği (gezinme bloğunda menü yoksa) Flux Lang ile yalnız o dilin sayfalarını verir.
- Next.js örneği FluxWP teması görünümünde: tema yazı tipleri ve paleti, koyu çok sütunlu footer (kısa sayfada en altta, telefonda açılır kapanır), açılır alt menüler, bloklarla kurulmuş ana sayfa.

## 0.1.0
- İlk sürüm. Bu WordPress'i bir ya da birden fazla headless ön yüze (Next.js, Nuxt, Astro ya da herhangi bir framework) GraphQL ile sunar.
- `flxRoute(path)`: adresin türü, HTTP durumu, yönlendirme (sonda eğik çizgi, eski slug, canonical), dil, WordPress nesnesi, Flux SEO ile aynı SEO head (ön yüz adresleriyle), hreflang sürümleri ve önbellek etiketleri. Taslak önizleme `previewId` ile, yalnız düzenleyebilen kullanıcıya, önbelleksiz.
- `flxSite`: ad, logo, simge, diller, menüler (klasik konumlar + header/footer gezinme blokları), palet, iletişim, para birimleri, yasal sayfalar, açık captcha ayarları, site haritası; istenirse işlenmiş şablon parçaları.
- Ön yüz listesi (en fazla 20): adres, diller, varsayılan, revalidate webhook'u, kendi secret'ı (şifreli saklanır, hiçbir yanıtta dönmez), önizleme yolu. Etkin ön yüzlerin origin'i Flux GraphQL CORS listesine kendiliğinden eklenir.
- İçerik değişince HMAC-SHA256 imzalı revalidate webhook'u (`X-Flx-Signature`), istek sonunda tek çağrı; panelde Test düğmesi ve son 30 çağrının kaydı.
- WordPress sayfaları: göster, noindex + canonical ya da ön yüze 301 (sepet, ödeme, hesap, önizleme, editörler WordPress'te kalır). Site haritası `?flxhl=<id>` ile ön yüz adresleri verir. Editörün Önizle düğmesi ön yüzde imzalı bağlantı açar.
- React ayar ekranı (FluxWP uygulamasında da). Entegrasyon rehberi `docs/flx-headless/README.md`, Next.js örneği `apps/headless-next`.
- PHP 8.1, WordPress 6.5 ve Flux GraphQL gerekir.
