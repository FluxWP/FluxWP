# FluxWP mimarisi (taslak, 2026-11-01)

Geçici ad **FluxWP**. Ad, logo, renk, paket kimliği tek dosyadan (`brand/`) değişir. genel amaçlı, birden fazla WordPress sitesine bağlanır.

## 1. Hedef
| Platform | Teknoloji | Kod |
|---|---|---|
| Web | React SPA (Vite) | `packages/panel` |
| macOS + Windows | Tauri 2, aynı SPA'yı sarar | `apps/desktop` (yalnız kabuk) |
| iOS + Android | Expo / React Native, ekranlar ayrı, native | **ayrı repo** (Ahmet kararı 2026-11-01) |

Ortak olan her şey `packages/core`'da (mobil repo bunu `@fluxwp/core` paketi olarak kullanır: GitHub Packages'a yayın ya da git bağımlılığı; core DOM/RN bağımsız tutulur): API istemcisi, auth, hesaplar, state, i18n, tasarım token'ları, layout/düzen mantığı. Mobil ve panel yalnız görünüm katmanında ayrılır.

## 2. Yer: FluxWP/fluxwp monorepo (Ahmet kararı 2026-11-01)
FluxWP ayrı repo değil; eklentilerle aynı repoda. Kök `package.json` Bun workspaces + turbo.
`packages/*` aynı zamanda eklentilerin ara katmanı: eklenti admin ekranları da bu React ekranlarını derlenmiş bundle olarak kullanır (§6). Eklenti release akışı (`plugins/**`, `themes/**`) bundan etkilenmez; FluxWP CI yalnız `packages/ apps/ brands/ tools/fluxapp/` değişince çalışır, masaüstü sürüm tag'i `fluxapp-v<sürüm>`.
```
fluxwp/
  plugins/ themes/       (WordPress paketleri, değişmedi)
  brands/<slug>/         brand.json (ad, bundle id, şema, renkler, logo, defaultSite, lockSite, loginMethods), icon.svg, logo.svg
  tools/fluxapp/         brand.mjs (markayı uygular), screenshots.mjs
  packages/
    core/                TS, DOM/RN bağımsız
      api/               fetch istemcisi, 401 → refresh, X-FluxWP-Token yedeği, ?_locale=user
      auth/              OAuth 2.0 + PKCE, API key (flxak_), OTP (bağışçı)
      accounts/          çoklu site/hesap, aktif hesap, belirteç deposu arayüzü (SecureStore adapter)
      layout/            menü + modül + dashboard kartı birleştirme, kullanıcı özelleştirmesi
      i18n/              i18next, tr (varsayılan) + en; tüm metin key ile
      query/             TanStack Query hook'ları (web + RN ortak)
      modules/<id>/      modül veri katmanı: tipler + hook'lar (ör. bagis, gorev, cagri)
    tokens/              design token JSON → CSS değişkeni (web) + RN tema objesi
    panel/               React SPA: kabuk (sol menü, ⌘K, sekmeler) + modül ekranları
    ui-web/              web bileşen kütüphanesi (button, liste, tablo, form)
    ui-native/           RN bileşen kütüphanesi (aynı API isimleri)
  apps/
    desktop/             Tauri 2 (src-tauri: keychain, deep link, loopback OAuth, updater)
    wp-embed/            (sırada) modül ekranlarını WP admin sayfasında açan bundle, bkz. §6
```
Paket yöneticisi Bun (workspaces, `bun.lock`); testler Vitest. Dil: TypeScript strict. Test: Vitest (core), Playwright (panel ekran görüntüleri), Detox sonra.

## 3. Marka ve "default env" (white-label)
- `brand/brand.config.ts` tek kaynak. Derleme betiği `tauri.conf.json`, `app.config.ts` (Expo), `index.html` başlık/ikonlarını bundan üretir. Hardcoded ad/renk yok.
- `FLUX_BRAND=<marka>` gibi bir env ile `brands/<slug>/` seçilir. Her markada:
  - `defaultSite` boş → genel uygulama: açılışta "Site ekle" ekranı, çoklu hesap.
  - `defaultSite: "https://example.org"` + `lockSite: true` → doğrudan o sitenin girişi açılır, site ekleme gizli, kendi adı/logosu/rengi ile.
- Varsayılan marka `flux` (genel).

## 4. Çoklu site / hesap
- `Account = {id, siteUrl, siteName, siteIcon, user:{id,name,avatar}, authType:'oauth'|'apikey'|'otp'}`; belirteçler SecureStore'da (macOS Keychain / Windows Credential Manager / iOS Keychain / Android Keystore), hesap listesi düz storage'da.
- Hesap değiştirici sol üstte (masaüstü) / profil sekmesinde (mobil). Her hesap ayrı query cache anahtarı (`[accountId, ...]`), geçişte veri karışmaz.
- Site eklerken keşif: `GET /wp-json/` → `flxapp/v1` namespace var mı. Varsa OAuth+PKCE; yoksa (Flux App kurulu değil) WordPress Application Passwords ile sınırlı mod (yalnız WP çekirdek modülleri). Böylece herhangi bir WP sitesine de bağlanır.
- OAuth istemcisi: markaya göre değişen şema yüzünden sabit `flx-masaustu` yerine **dinamik kayıt** (`flxapp/v1/oauth/register`, zaten var) ya da Flux App'ya `fluxapp` istemcisi eklenir. Masaüstü: 127.0.0.1 loopback; mobil: `<scheme>://oauth/callback`.

## 5. Düzenlenebilirlik
Üç katman, sırayla birleşir (`core/layout`):
1. **Sunucu**: `flxapp/v1/me` → `menu` (rol bazlı), `modules` (flxapp_modules), dashboard kartları (`flxapp_dashboard`).
2. **Site varsayılanı**: yönetici "uygulama düzeni"ni kaydeder (menü sırası, gizli öğeler, kart seti, mobil alt sekmeler). Yeni uç: `GET/POST flxapp/v1/layout` (Flux App thread'i açar).
3. **Kullanıcı**: kendi sırası, sabitlenenler, gizledikleri, kart boyutları. `GET/POST flxapp/v1/prefs` (cihazlar arası senkron), çevrimdışıyken yerel.
Düzenleme modu: menüde sürükle bırak, kart ekle/kaldır/boyut, modülü yerel/web görünümü arasında değiştir. Yetki her zaman sunucuda: düzen, görülmeyen öğeyi açamaz.

## 6. Panel ekranları ve "tamamen aynı kod"
Bugün eklenti admin ekranları PHP + vanilla JS. Öneri:
- Modül ekranları tek yerde yazılır (`packages/modules/<id>`) ve hem uygulamada hem WordPress admin sayfasında aynı kodla çalışır (Ahmet: görünüm aynı olsun, geçişte zorlanılmasın). WP tarafında eklenti, `apps/wp-embed`'in ürettiği bundle'ı kendi admin sayfasına koyar ve çerez + nonce ile açar. Ekranlar tam REST uçlarını kullanır (Flux App belirteci her REST ucunda geçerli), yalnız `/app` özet uçlarını değil.
- **Web**: aynı SPA bundle'ı sitede servis edilir. Flux App'ya ince bir yükleyici: `wp-admin/admin.php?page=fluxapp` (ya da `/app/`) SPA'yı çerez + nonce ile açar. Masaüstü ile birebir aynı kod; tek fark auth adapter'ı (çerez vs belirteç).
- Portlanmamış ekran: web görünümüne düşer. Masaüstünde sayfa uygulama penceresinin içine gömülür (Tauri çocuk webview, `FLXApp/` user agent ile panelin üst/sol barı gizli), Flutter sürümündeki gibi; web'de normal wp-admin linki. Yani ilk günden her şey açılır, yerel ekranlar parça parça gelir.
- wp-admin PHP ekranları React ekranı hazır olana kadar yerinde kalır; hazır olunca isteğe bağlı olarak PHP ekranı SPA'ya yönlenir (o paketin thread'i karar verir).

## 7. Port sırası (her madde bir sürüm, ilk sürümler yalnız macOS)
| Sürüm | İş |
|---|---|
| 0.1.0 | İskelet: monorepo, brand config, core (api, auth, accounts, i18n), Tauri macOS kabuğu, site ekle + OAuth giriş, rol bazlı menü, her menü web görünümünde |
| 0.2.0 | Düzenlenebilir menü + Başlangıç dashboard (flxapp/v1/dashboard kartları), hesap değiştirici, white-label build |
| 0.3.0 | Bağış onayı + Bağışlar yönetimi (flxb/v1/app/*, ortak liste API) |
| 0.4.0 | WhatsApp gelen kutusu (flx-chat/v1/app) |
| 0.5.0 | Görevler (flx-tasks/v1/app) |
| 0.6.0 | Posta kutusu (Flux Mail) |
| 0.7.0 | Flux AI sohbet (stream) + Stüdyo |
| 0.8.0 | Sertifikalar, Ortam, Yazılar/Sayfalar (WP REST) |
| 0.9.0 | Web yükleyici (SPA sitede), Windows build |
| 1.0.0 | Mobil (Expo) ilk sürüm: bağışçı ekranları + onay + WhatsApp + görevler |
Flutter'da portlanmış ekranlar (dashboard, WhatsApp, Görevler, Onay, Bağışlarım, Stüdyo) önce taşınır; geri kalan PORT-PLANI.md sırasını izler.

## 8. Teknoloji seçimleri
- State: Zustand (oturum, hesap, düzen) + TanStack Query (sunucu verisi, poll 4/8/15 sn, sekme gizliyken durur).
- Router: panel TanStack Router; mobil Expo Router.
- Stil: panel CSS değişkenleri (token'lardan) + CSS modules; mobil token'lardan RN tema. Açık/koyu mod, Konsept B tasarım dili.
- Tauri eklentileri: deep-link, keyring (secure store), updater (FluxWP güncelleme sunucusu), opener, window-state.
- CI: GitHub Actions, macOS runner: `bun run build` + `tauri build` (imzasız, imza secret'ları gelince imzalı). Release `v*` tag.

## 9. Sunucu tarafında gerekenler (paket thread'lerine)
- Flux App: `fluxapp` OAuth istemcisi ya da dinamik kayıt ayarı açık; `flxapp/v1/layout`, `flxapp/v1/prefs`; ileride SPA yükleyici.
- Diğer paketler: mevcut `/app` uçları yeterli; eksik uç çıkarsa o paketin thread'i açar.

## 10. Açık kararlar (Ahmet)
1. ~~Repo~~ → FluxWP/fluxwp (karar verildi). Flutter uygulaması ayrı kalıyor.
3. Web panel sitede mi servis edilsin (öneri) yoksa ayrı alan adında mı (çoklu site web'de de lazım olursa; CORS gerekir).
