# FluxWP logo seti (B · Modül)

Bloklardan kurulmuş F + turuncu "eklenen eklenti" bloğu. Wordmark Inter Display (Flux Bold, WP Light), path'e çevrili, font gerekmez.

Renkler: indigo `#4338CA`, gece mavisi `#1E1B4B`, vurgu `#F59E0B`, koyu zeminde WP `#A5B4FC`. İkon degradesi `#6366F1 → #3730A3 → #1E1B4B`.

| Dosya | Nerede |
|---|---|
| fluxwp-logo-light.svg / -dark.svg | README, site başlığı, FluxApp giriş/üst bar |
| fluxwp-mark.svg / -dark.svg | Sol menü, küçük alanlar |
| fluxwp-mark-mono-black/white.svg | Tek renk (WP admin menüsü, baskı) |
| fluxwp-app-icon.svg | 256 px, yuvarlatılmış; `guncelleme-sistemi/logolar/fluxwp.svg` ile aynı |
| fluxwp-app-icon-fullbleed.svg | iOS, macOS, Tauri, Expo `icon` (platform maskeler) |
| fluxwp-maskable.svg | PWA `purpose: maskable` |
| fluxwp-android-foreground.svg | Expo `adaptiveIcon.foregroundImage`, `backgroundColor: #312E81` |
| favicon.svg | `<link rel="icon" type="image/svg+xml">`, açık/koyu tema uyumlu, 16 px piksel ızgara |
| favicon.ico | 16/32/48 eski tarayıcılar |
| png/ | apple-touch-icon (180), icon-192/512, maskable-192/512, app-icon 16–1024, fullbleed-1024, android-foreground-1024, logo-*-h128, mark-*-512 |

Tauri: `bun tauri icon png/app-icon-fullbleed-1024.png` tüm icns/ico/png'leri üretir.
Yeniden üretmek: `python3 kaynak/final.py` (fonttools gerekli), PNG'ler Playwright ile.
