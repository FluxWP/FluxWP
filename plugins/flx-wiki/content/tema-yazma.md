---
title: Tema yazma
slug: tema-yazma
section: tema
order: 1
excerpt: FluxWP blok teması yapısı; theme.json renkleri, şablonlar, parçalar, stil varyasyonları, koyu mod ve eklentilerle uyum.
---
FluxWP temaları **blok temasıdır** (Site Editor uyumlu). Referans tema `themes/fluxwp`. Yeni bir tema yazmadan önce, ihtiyacın bir **ön ayar eklentisiyle** karşılanıp karşılanmadığına bakın: [[tema-yazma/on-ayar-eklentisi]]. Kurumlara özel her şey (logo, renk, içerik, sayfalar) ön ayardadır; tema geneldir.

## Yapı

```text
themes/fluxwp/
├── style.css          # tema başlığı (Theme Name, Version, Update URI, Text Domain)
├── theme.json         # renk, yazı, boşluk ön ayarları, düzen genişlikleri
├── functions.php      # theme support, varlıklar, inc/ yükleyici
├── templates/         # index, single, page, archive, search, 404 … (.html)
├── parts/             # header.html, footer.html
├── patterns/          # hero.php, ozellikler.php … (çevrilebilir metinlerle)
├── styles/            # stil varyasyonları: antrasit, indigo, mavi, orman, petrol
├── inc/               # ayarlar, dil, hız, erişilebilirlik, WooCommerce
├── preset/            # temanın kendi ön ayarı (preset.json)
└── assets/            # css, js, görseller
```

## Renkler: eklentiler tema rengini izler

Eklentiler kendi renklerini sabit yazmaz; tema ön ayar değişkenlerini kullanır. Böylece stil varyasyonu ya da koyu mod değişince eklenti ekranları da değişir.

| Değişken | Kullanım |
|---|---|
| `--wp--preset--color--primary` | ana renk (düğme, vurgu) |
| `--wp--preset--color--leaf` | ikinci vurgu, bağlantı |
| `--wp--preset--color--ink` | başlık |
| `--wp--preset--color--text` | metin |
| `--wp--preset--color--line` | çizgi, kenarlık |
| `--wp--preset--color--mint`, `--mint-soft` | açık zemin |
| `--flx-surface` | kart zemini (koyu modda koyu) |

Eklenti CSS'inde her zaman yedek değer verin; tema FluxWP değilse de düzgün görünsün:

```css
.flxex-card{
  border:1px solid var(--wp--preset--color--line,#E5E7EB);
  color:var(--wp--preset--color--text,#374151);
  background:var(--flx-surface,#fff);
}
```

## Koyu mod

FluxWP teması koyu modu `body.flx-dark` sınıfı ve `prefers-color-scheme: dark` ile açar; yukarıdaki değişkenleri koyu karşılıklarıyla değiştirir. Değişkenleri kullanan eklenti ek iş yapmadan koyu moda uyar. Sabit `#fff` zeminlerden kaçının.

## Temanın desteklediği özellikler

```php
add_theme_support( 'flx-theme' );   // FluxWP uyumlu tema
add_theme_support( 'flx-credit' );  // altlıkta "Altyapı: FluxWP" imzası (blok kaldırılırsa yedek çubuk)
add_theme_support( 'flx-setup-panel', array( 'page' => 'flx-ayarlar', 'arg' => 'bolum', 'value' => 'onayarlar' ) );
```

## Eklentinin tema içinde sayfa göstermesi

Bir eklenti kendi ön yüz sayfasını (bu wiki gibi) gösterecekse temanın başlık ve altlığını korumalı:

- **Blok temada**: tema başlık/altlık parçaları ile kendi dinamik bloğunuzu birleştiren bir şablon içeriği kurun ve `template-canvas.php` döndürün. Örnek: `plugins/flx-wiki/includes/front.php`.
- **Klasik temada**: `get_header()`, içerik, `get_footer()`.

## Metinler

Şablon (`.html`) dosyalarındaki metinler çevrilemez. Çevrilmesi gereken metinleri **pattern** (`patterns/*.php`) içinde `esc_html_e()` ile yazın ve şablonda pattern'i çağırın.
