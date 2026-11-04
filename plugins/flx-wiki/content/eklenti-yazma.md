---
title: Yeni eklenti yazma
slug: eklenti-yazma
section: eklenti
order: 1
excerpt: Sıfırdan bir FluxWP eklentisi: klasör yapısı, ana dosya başlığı, sabitler, çeviri yükleyici, marka modülü, ikon ve paket dosyaları.
---
Bu rehber, `plugins/flx-ornek` adında yeni bir eklentiyi adım adım kurar. Gerçek bir örnek için repodaki `plugins/flx-recaptcha` ya da `plugins/flx-wiki` klasörüne bakın; ikisi de bu iskeleti izler.

## 1. Ad ve önek

Önce [[adlandirma]] sayfasına göre seçin:

- slug: `flx-ornek`, görünen ad: **Flux Örnek** değil **Flux Example** (görünen ad İngilizce ve her dilde aynı)
- kod öneki: `flxex_`, sınıf/sabit öneki: `FLXEX_`
- REST: `flx-ornek/v1`, option: `flxex_settings`

## 2. Klasör yapısı

```text
plugins/flx-ornek/
├── flx-ornek.php          # eklenti başlığı, sabitler, yükleyici
├── uninstall.php          # kaldırınca option/tablo temizliği
├── index.php              # "Silence is golden"
├── CHANGELOG.md           # Türkçe, en yeni sürüm üstte
├── README.md, README.tr.md
├── .distignore            # zip'e girmeyecek dosyalar
├── includes/
│   ├── helpers.php
│   ├── admin.php
│   ├── rest.php
│   ├── system-checks.php
│   └── flx-brand/         # tools/brand/sync.py kopyalar, elle değiştirmeyin
├── assets/                # css, js, icon.svg, icon-pastel.svg
├── languages/             # .pot, en_US .po/.mo/.l10n.php
├── tools/                 # genstr.py + en.json (zip'e girmez)
└── docs/logo.svg          # README için
```

Her klasöre boş `index.php` koyun.

## 3. Ana dosya

```php
<?php
/**
 * Plugin Name:       Flux Example
 * Description:       Tek cümlede ne yaptığı.
 * Version:           0.1.0
 * Requires at least: 6.4
 * Requires PHP:      7.4
 * Author:            FluxWP
 * License:           GPL-2.0-or-later
 * Text Domain:       flx-ornek
 * Domain Path:       /languages
 * Update URI:        https://updater.fluxwp.com/flx-ornek
 */

defined( 'ABSPATH' ) || exit;

require_once __DIR__ . '/includes/flx-brand/loader.php'; // FluxWP signature (tools/brand).

if ( defined( 'FLXEX_VERSION' ) ) {
	return; // aynı eklentinin iki kopyası yüklüyse
}

define( 'FLXEX_VERSION', '0.1.0' );
define( 'FLXEX_FILE', __FILE__ );
define( 'FLXEX_DIR', plugin_dir_path( __FILE__ ) );
define( 'FLXEX_URL', plugin_dir_url( __FILE__ ) );

require_once FLXEX_DIR . 'includes/helpers.php';
require_once FLXEX_DIR . 'includes/system-checks.php';
if ( is_admin() ) {
	require_once FLXEX_DIR . 'includes/admin.php';
}
```

> [!IMPORTANT]
> `Update URI` güncellemelerin FluxWP sunucusundan gelmesini sağlar; wordpress.org ile ad çakışmasını da önler. `Version` ile `FLXEX_VERSION` her zaman aynı olmalı.

## 4. Çeviri yükleyici

Kaynak metinler Türkçe yazılır, İngilizce çeviri pakette gelir. `en_GB`, `en_AU` gibi diller `en_US` çevirisine düşer:

```php
add_action( 'init', function () {
	load_plugin_textdomain( 'flx-ornek', false, dirname( plugin_basename( FLXEX_FILE ) ) . '/languages' );
}, 1 );

add_filter( 'load_textdomain_mofile', function ( $mofile, $domain ) {
	if ( 'flx-ornek' === $domain && ! is_readable( $mofile ) && preg_match( '/-en_[A-Z]{2}\.mo$/', $mofile ) ) {
		return FLXEX_DIR . 'languages/flx-ornek-en_US.mo';
	}
	return $mofile;
}, 10, 2 );
```

Ayrıntı: [[eklenti-yazma/ceviri]].

## 5. Marka imzası

```bash
python3 tools/brand/sync.py . ../wp-extras
```

Komut `includes/flx-brand/` klasörünü kopyalar ve ana dosyaya `require_once` satırını ekler. Bu modül hem "Altyapı: FluxWP" imzasını hem de `flx_date()` / `flx_time_ago()` yardımcılarını getirir. Kopyaları elle değiştirmeyin; kaynak `tools/brand/flx-brand/`.

## 6. İkon

`tools/icons/build.py` içindeki `PKGS` listesine bir satır ekleyin (slug, ad, renk grubu, [Lucide](https://lucide.dev/icons) simgesi) ve üretin:

```bash
cd tools/icons && bun install && cd ../..
python3 tools/icons/build.py
```

`assets/icon.svg`, `icon-pastel.svg`, `docs/logo.svg` ile Flux Setup ve Flux Admin'deki kopyalar yazılır. SVG'yi elle çizmeyin.

Renk grupları: `platform` (yönetim), `system` (bakım), `comms` (iletişim), `growth` (içerik ve büyüme), `commerce` (ticaret ve bağış).

## 7. Yönetim menüsü ve Flux Admin

Kendi menünüzü `add_menu_page()` ile açın; Flux Admin varsa sayfa başlığı, logo ve grup filtreleriyle tek menüye yerleşir: [[eklenti-yazma/flux-admin]].

## 8. Paket dosyaları

`.distignore` (GitHub dosyaları zip'e girmesin):

```text
# GitHub tanitim dosyalari pakete girmez
./docs
./README.md
./README.tr.md
./tools
```

> [!CAUTION]
> `.distignore` yorumlarında kesme işareti (`'`) kullanmayın; `bin/build.sh` satırları `xargs` ile okur ve bozulur.

`uninstall.php` yalnız `WP_UNINSTALL_PLUGIN` tanımlıyken çalışır ve kendi option'larınızı siler.

## 9. Kontrol listesi

- [ ] Ad ve önek benzersiz
- [ ] Tüm metinler `__( '…', 'flx-ornek' )`, İngilizce karşılıkları `tools/en.json`'da
- [ ] Ayarlar panelde, şeması `flx_settings_schema`'da: [[eklenti-yazma/ayarlar]]
- [ ] Sağlık satırları `flx_system_checks`'te: [[eklenti-yazma/sistem-kontrolu]]
- [ ] AI varsa yalnız Flux AI üzerinden: [[ai-destekli-eklenti]]
- [ ] `CHANGELOG.md` ve sürüm: [[surum-ve-yayin]]
