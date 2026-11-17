# Flux Headless

Bu WordPress'i GraphQL ile bir ya da birden fazla headless ön yüze (Next.js, Nuxt, Astro ya da herhangi bir
framework) sunar. WordPress içerik yönetimi ve panel olarak kalır; siteyi ön yüzler çizer.

- `flxRoute(path)`: adresin ne gösterdiği: tür, HTTP durumu, yönlendirmeler (sonda eğik çizgi, eski slug),
  dil, WordPress nesnesi, Flux SEO'nun bastığı SEO head'in aynısı (canonical, Open Graph, JSON-LD) ön yüz
  adresleriyle, hreflang sürümleri ve önbellek etiketleri. Taslaklar için imzalı önizleme.
- `flxSite`: ad, logo, diller, menüler (menü konumları ve header/footer gezinme blokları), palet, iletişim
  bilgileri, para birimleri, yasal sayfalar ve açık captcha ayarları.
- Tek panelden çok ön yüz: her birinin kendi adresi, dilleri, revalidate webhook'u ve secret'ı. Origin'leri
  CORS'a kendiliğinden eklenir.
- İçerik değişince HMAC imzalı revalidate webhook'u; Test düğmesi ve gönderim kaydı.
- WordPress sayfaları açık kalabilir, arama motorlarına ön yüzü gösterebilir (noindex + canonical) ya da ön
  yüze yönlendirebilir. Site haritaları ön yüz adreslerini listeler.

Entegrasyon rehberi (EN): [docs/flx-headless/README.md](../../docs/flx-headless/README.md). Next.js örneği:
`apps/headless-next`.

PHP 8.1, WordPress 6.5 ve Flux GraphQL gerekir.

English: [README.md](README.md)

## Maintainers

Ahmet Yılmaz (@sharkpaw)
