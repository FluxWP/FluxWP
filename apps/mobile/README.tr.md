**Türkçe** · [English](README.md)

# FluxWP mobil uygulaması

FluxWP siteleri için iOS ve Android uygulaması (Expo SDK 58, React Native 0.88, expo-router). Tek uygulama, birden çok WordPress sitesi ve hesabı: sekmeler, yan menü ve üst bar siteden (`flxapp/v1/config`), ekranlar paket manifestlerinden (`flxapp/v1/manifest`) gelir. Uygulamada ekranı olmayan her panel sayfası, oturumu açık gömülü web görünümünde açılır.

## Bu temelde neler var

- **Siteler ve hesaplar:** site adresi yazılır → keşif (`/wp-json/`, `flxapp/v1/app/bootstrap`) → giriş. Uygulama içi tarayıcıda OAuth 2.0 + PKCE (istemci `flx-mobil`, dönüş `fluxwp://oauth/callback`, aynı siteye ikinci hesap için `prompt=login`), kodla giriş (OTP), API anahtarı ya da Flux App olmayan sitelerde WordPress uygulama şifresi (sınırlı mod). Belirteçler Keychain / Android Keystore'da (`expo-secure-store`), hesap listesi ve hesap başına önbellek MMKV'de. Üst bardan ve yan menüden hızlı hesap değiştirici.
- **Ayarlanabilir kabuk:** en çok beş sekme yuvası, yan menü (sabitler, gruplar, alt bölüm) ve üst bar, hepsi `flx-app-config/1`'den. Hedefler (`screen:`, `module:`, `menu:`, `web:`, `url:`, `native:`) manifest → yerel ekran → web görünümü sırasıyla çözülür.
- **Tema:** `@fluxwp/tokens` paleti + marka renkleri + site teması (`config.theme`, sunucuda WordPress temasından türetilir) + beyaz etiket ayarları; açık/koyu cihazı ya da kullanıcının seçimini izler. Unistyles temayı hesap başına yeniden çizmeden değiştirir.
- **Manifest ekranları:** liste (FlashList, arama, filtre, çekip yenileme, sayfalama, satır aksiyonları, FAB), detay (alanlar, istatistikler, etiketler, zaman tüneli, içerik, aksiyonlar), form (react-hook-form + valibot), pano; boş / hata / yükleniyor / çevrimdışı durumları. Bilinmeyen tür ve çizim hatası web görünümüne düşer.
- **Web görünümü yedeği:** tek seferlik SSO adresi (`flxapp/v1/session`), `FLXApp/<sürüm> (mobile; <marka>; <os>)` user agent'ı (flx-app admin barını ve menüyü gizler), bağlantı yakalama (manifest `routes` yerel açılır, başka siteler tarayıcıda, düşen oturum bir kez yeniden açılır), beyaz listeli köprü mesajları.
- **Önbellek:** TanStack Query, hesap başına kalıcı istemci (kendi MMKV deposu), önce önbellek sonra arka planda tazeleme; okuma çevrimdışı çalışır.
- **Beyaz etiket kancası:** `FLUX_BRAND=<slug>` `brands/<slug>/brand.json`'u seçer (ad, ikon, bundle kimliği, şema, renkler, `allowedSites`, `locked`, tema). FluxWP markası bunların hepsini açık bırakır.

Ortak mantık burada değil, masaüstü uygulamasının da kullandığı platformdan bağımsız paketlerde: `@fluxwp/core` (API, giriş, hesaplar), `@fluxwp/tokens` (tema), `@fluxwp/manifest` (sözleşmeler, JSON Schema'lar, şablonlar, hedefler) ve `@fluxwp/runtime` (görünümsüz React: hesap kapsamı, hook'lar, controller'lar, kabuk modeli, web oturumu). Bu klasör yalnız çizer.

## Geliştirme

```bash
bun install                      # repo kökünden
bun run --filter @fluxwp/mobile start      # Expo geliştirme sunucusu (dev client; Expo Go hedef değil)
bun run --filter @fluxwp/mobile web        # web önizlemesi (yalnız geliştirme)
```

İlk ekrandaki "Demo hesabıyla dene" sunucusuz çalışır: `packages/manifest/fixtures` sözleşme örnekleri ve örnek veri.

Denetimler: `bun run typecheck`, `bun run lint`, `bunx expo-doctor`, `bunx expo export --platform all` (hepsi `apps/mobile`'da); davranış testleri `packages/manifest` ve `packages/runtime`'da (kökte `bun run test`). CI: `.github/workflows/mobile-ci.yml`.

Web önizlemesinin ekran görüntüleri: `bunx expo export --platform web --output-dir dist && bunx serve -s dist -l 8090`, sonra `bun scripts/screenshots.ts`.

## Marka ve ikonlar

`scripts/brand.cjs` (`postinstall` ve `app.config.ts` çalıştırır) `brands/<slug>/`'dan `src/brand/current.json`, `src/brand/logo.json` ve `assets/brand/*` üretir. Mağaza ikonları `brands/<slug>/mobile/` altındaki PNG'lerdir; marka SVG'sinden kökte `bun run mobile:icons` ile çizilir.

## Bakım

FluxWP'yi **Ahmet Yılmaz** ([@sharkpaw](https://github.com/sharkpaw)) yönetir ve sürdürür.
