# Eklenti ekranları (Flux runtime, Flux GraphQL 0.5.0)

FluxWP dışındaki bir eklenti (wp-extras, müşteri eklentisi, üçüncü taraf) wp-admin'e FluxWP ekranlarıyla aynı
kabukta React ekranı ekler. Eklenti FluxWP'nin kodunu kopyalamaz ve FluxWP monoreposuna girmez.

## Parçalar

| Parça | Nerede | Ne yapar |
|---|---|---|
| Ortak runtime | `plugins/flx-graphql/assets/runtime/` (`apps/wp-embed/src/runtime.tsx`, `tools/fluxapp/runtime-sync.sh`) | React, React Query, i18next, `@fluxwp/core`, `tokens`, `ui`, `graphql`, `graphql/react` ve `@fluxwp/host` (useHost, useModuleApi). Sayfaya bir kez yüklenir, `globalThis.FluxRuntime` |
| `@fluxwp/plugin-kit` | `packages/plugin-kit` | Vite preset'i (`fluxScreens()`): yukarıdaki modüller bundle'a girmez, runtime'dan okunur. Çıktı `assets/flux/flux-screens.js` + parçalar + `flux-screens.css` + `chunks.json` (`"mode":"screens"`) |
| Kabuk | `plugins/flx-graphql/includes/screens.php`, `assets/js/screens.js` | Klasörde `flux-screens.js` varsa ekranı runtime üzerinden açar |

Örnek eklenti: `packages/plugin-kit/example/acme-hello` (`bun run --filter @fluxwp/plugin-kit example`).

## Eklenti ne yazar

1. Ekran girişi: `screens` (modül id → lazy loader) ve isteğe bağlı `resources` (dil → namespace → anahtarlar).
   ```ts
   export const screens = { 'acme-orders': () => import('./Orders').then((m) => ({ default: m.Orders })) };
   export const resources = { en: { acme: { title: 'Orders' } }, tr: { acme: { title: 'Siparişler' } } };
   ```
2. `vite.config.ts`: `export default fluxScreens({ entry: 'screens/index.ts', outDir: 'assets/flux' });`
3. PHP: sayfa `flx_admin_screen( $page, array( 'module' => 'acme-orders', 'assets_dir' => …, 'assets_url' => … ) )`
   ile kaydedilir, callback `flx_admin_screen_render()` çağırır (EKRAN-KABUGU.md'deki kurallar aynen). Başlıkta
   `Requires Plugins: flx-graphql`.
4. Ekran kendi `FlxGraphQLProvider`'ını kurar (`createGraphQLClient({ api: useHost().client })`), FluxWP ekranları gibi.

## Kurallar

- Eklenti yalnız runtime modüllerini import eder (`packages/plugin-kit/src/modules.ts`). Başka bir `@fluxwp/*`
  paketi (ör. `@fluxwp/modules` ekranları, `runtime`, `render-web`) derlemede hata verir. `@fluxwp/modules` yalnız
  host kısmı olarak okunur.
- `@fluxwp/ui` stilleri runtime'da; eklenti CSS'i yalnız kendi sınıflarını yazar. Her kural `.flux-root` altına alınır.
- Runtime modül listesi ya da bir modülden değer çıkarmak kırıcıdır: `RUNTIME_API` artar (`runtime.tsx` ve
  `plugin-kit/src/vite.ts`), eski eklenti açık bir hata mesajı verir. Ekleme kırıcı değil.
- Runtime'ın dışa açtığı adlar `packages/plugin-kit/runtime-exports.json`'da; `runtime-sync.sh` yeniler,
  CI `exports:check` ile denetler.

## Sırada

- `@fluxwp/plugin-kit` codegen: eklentinin `graphql/operations/*.graphql` dosyalarından tipler ve
  `persisted-queries.json`, FluxWP şemasına karşı (`@fluxwp/schema`).
- npm'de public yayın (`@fluxwp/*`), `FluxWP/plugin-starter` şablon deposu.
