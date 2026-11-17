# Flux Headless

## 0.1.0
- İlk sürüm. Bu WordPress'i bir ya da birden fazla headless ön yüze (Next.js, Nuxt, Astro ya da herhangi bir framework) GraphQL ile sunar.
- `flxRoute(path)`: adresin türü, HTTP durumu, yönlendirme (sonda eğik çizgi, eski slug, canonical), dil, WordPress nesnesi, Flux SEO ile aynı SEO head (ön yüz adresleriyle), hreflang sürümleri ve önbellek etiketleri. Taslak önizleme `previewId` ile, yalnız düzenleyebilen kullanıcıya, önbelleksiz.
- `flxSite`: ad, logo, simge, diller, menüler (klasik konumlar + header/footer gezinme blokları), palet, iletişim, para birimleri, yasal sayfalar, açık captcha ayarları, site haritası; istenirse işlenmiş şablon parçaları.
- Ön yüz listesi (en fazla 20): adres, diller, varsayılan, revalidate webhook'u, kendi secret'ı (şifreli saklanır, hiçbir yanıtta dönmez), önizleme yolu. Etkin ön yüzlerin origin'i Flux GraphQL CORS listesine kendiliğinden eklenir.
- İçerik değişince HMAC-SHA256 imzalı revalidate webhook'u (`X-Flx-Signature`), istek sonunda tek çağrı; panelde Test düğmesi ve son 30 çağrının kaydı.
- WordPress sayfaları: göster, noindex + canonical ya da ön yüze 301 (sepet, ödeme, hesap, önizleme, editörler WordPress'te kalır). Site haritası `?flxhl=<id>` ile ön yüz adresleri verir. Editörün Önizle düğmesi ön yüzde imzalı bağlantı açar.
- React ayar ekranı (FluxWP uygulamasında da). Entegrasyon rehberi `docs/flx-headless/README.md`, Next.js örneği `apps/headless-next`.
- PHP 8.1, WordPress 6.5 ve Flux GraphQL gerekir.
