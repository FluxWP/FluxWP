# @fluxwp/panel

## 0.3.7
- Loading screen: `index.html` draws it before any script runs (app icon, bar; follows the saved theme). It stays while accounts load, then shows the site's icon and name while the site is prepared, and fades out (at least ~0.45 s on screen, never more than 20 s).
- Site warm-up on entry (app start or site switch): the shell contract loads first, then every menu screen's code, then the pinned tiles are mounted in the background until the network goes quiet (4 s at most with a saved cache, 12 s for a new site). The rest of the menu warms after the loading screen, one screen at a time and only while the window is in front.
- Kept screens: native and manifest screens stay mounted after the first visit (`<Activity>`, up to 24). Going back is instant with state and data in place; hidden screens pause their effects and polling.
- Query cache per site in IndexedDB instead of localStorage (WebKit's ~5 MB limit made large caches fail to save, so pages loaded from the network every time). The old copy is moved over once; sign-out deletes it.
- Auto sync: `/sync` digests of config and manifest are compared on every poll; a change (preset or plugin settings) refreshes all cached data in the background. A changed plugin version set in `/me` also remounts kept screens. No app update or opening the plugin needed.
- Sidebar top shows the selected site's logo (dark logo in dark theme), or its icon and name when it has no wide logo. The FluxWP logo moved to Settings › About and opens fluxwp.com.
- Sidebar tiles lift and tint on hover; menu icons grow slightly.
- Dashboard lists and cards decode HTML entities (`Senegal&#8217;de` → `Senegal’de`).

## 0.3.6
- Panel açılışında FluxApp GraphQL taşıyıcısı kurulur (`installFlxAppGraphQL`); web ve masaüstü uygulaması bootstrap/me/sync/config/manifest çağrılarını GraphQL ile yapar, olmayan sitede REST'e döner.


## 0.3.5
- `react-i18next` and `i18next` are deduplicated, so module screens use the panel language.

## 0.3.4
- The open panel reads `/me` and the counters from the shared runtime queries, so the legacy hooks no longer start a second poll.

## 0.3.3
- Support ten shared locales, English translation keys and Arabic direction for WordPress embeds and shared screens.

## 0.3.2

- Her hesapta "Yeniden giriş" durur ve o sitenin adresini açar. Oturum düşünce hesabı silmeden giriş yapılır. Keşif, site simgesi yoksa marka logosunu gösterir.

## 0.3.1

- Site teması `--flux-font-heading` değişkenini de yazar; başlıklar Figtree ya da sitenin başlık yazı tipini kullanır.
