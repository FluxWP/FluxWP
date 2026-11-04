---
title: Çeviri ve tarih
slug: ceviri
parent: eklenti-yazma
order: 2
excerpt: Kaynak dil Türkçe, İngilizce çeviri pakette; genstr.py ile .pot/.po/.mo üretimi, JS metinleri ve flx_date / flx_time_ago.
---
Her FluxWP paketi iki dille gelir: **Türkçe (kaynak)** ve **İngilizce**. Arayüzde koda gömülü metin olmaz.

## PHP'de metin

```php
esc_html_e( 'Ayarlar kaydedildi.', 'flx-ornek' );
/* translators: %d: sayfa sayısı */
printf( esc_html( _n( '%d sayfa', '%d sayfa', $n, 'flx-ornek' ) ), $n );
```

- Text domain her zaman eklentinin slug'ı.
- Yer tutuculu metinlerin üstüne `translators:` yorumu yazın.
- Metni parçalara bölüp birleştirmeyin; cümle tek parça çevrilir.

## JS'de metin

JS'ye metinler `wp_localize_script()` ile gider; JS'de Türkçe metin yazılmaz:

```php
wp_localize_script( 'flxex-admin', 'flxexAdmin', array(
	'i18n' => array( 'saved' => __( 'Kaydedildi', 'flx-ornek' ) ),
) );
```

## Çeviri dosyalarını üretmek

Her pakette `tools/genstr.py` ve `tools/en.json` bulunur. Betik PHP'deki `__()`, `esc_html__()`, `_n()` metinlerini toplar, karşılıklarını `en.json`'dan alır ve `languages/` altına `.pot`, `en_US.po`, `.mo` ve `.l10n.php` yazar.

```bash
python3 plugins/flx-ornek/tools/genstr.py
```

Eksik çeviri varsa listeler ve 1 koduyla çıkar. Yeni metin eklediyseniz `en.json`'a karşılığını yazıp tekrar çalıştırın. Yeni pakette `genstr.py`'yi başka bir paketten kopyalayıp `DOMAIN` ve proje adını değiştirin.

## Tarih ve "önce"

> [!IMPORTANT]
> `human_time_diff()`, `wp_date()` ve `date_i18n()` doğrudan kullanılmaz. Dil karışmasın diye tek yardımcı vardır.

```php
echo esc_html( flx_time_ago( $timestamp ) ); // "3 gün önce" / "3 days ago"
echo esc_html( flx_date( $timestamp ) );     // "10 Ağustos 2026" / "Aug 10, 2026"
echo esc_html( flx_date( $timestamp, 'datetime' ) );
```

İkisi de `flx-brand` modülüyle gelir; `$time` zaman damgası, MySQL tarihi ya da `DateTimeInterface` olabilir.
