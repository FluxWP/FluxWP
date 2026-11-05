# Flux Lang API

Prefix `flxl_`. Varsayılan dil `tr` (adreste öneksiz), diğer diller `/<kod>/`.

## Veri

| Ne | Nerede |
|---|---|
| İçeriğin dili | gizli taksonomi `flxl_lang` (terim = dil kodu). Terim yoksa varsayılan dil. |
| Çeviri grubu | post meta `_flxl_group` (grubun ilk içeriğinin ID'si); hızlı dizin seçeneği `flxl_index` |
| Ayarlar | seçenek `flxl_settings` |
| Görsel | `_flxl_alt_<dil>`, `_flxl_caption_<dil>`, `_flxl_variant_<dil>` (ek ID) |
| Terim | term meta `_flxl_name_<dil>`, `_flxl_desc_<dil>` |
| Sipariş | `_flxl_lang` (siparişin verildiği dil) |

## Fonksiyonlar

```php
flxl_current_language();            // 'tr' | 'en' …
flxl_default_language();            // 'tr'
flxl_languages();                   // kod => [code, name, locale, flag, active]
flxl_language_locale( 'en' );       // 'en_US'
flxl_post_language( $post );        // içeriğin dili ('' = çevrilmeyen tür / ortak sayfa)
flxl_set_post_language( $id, 'en' );
flxl_get_translations( $id );       // dil => ID (kendisi dahil)
flxl_get_translation( $id, 'en', $published = false ); // ID | 0
flxl_link_translations( [ 'tr' => 5, 'en' => 10 ] );
flxl_save_translation( $src_id, 'en', [ 'title', 'content', 'excerpt', 'slug', 'status', 'featured_image_id', 'meta' ] ); // ID | WP_Error
flxl_url_for_language( $url, 'en' ); // site içi adresin dil sürümü
flxl_translate_url( '/hakkimizda/', 'en' ); // içeriğin o dildeki adresi
flxl_switch_language( 'en' ); … flxl_restore_language(); // e-posta/iş için geçici dil (switch_to_locale dahil)
flxl_order_language( $order );      // siparişin dili
flxl_switcher( [ 'style' => 'code|name|flag' ] ); // dil seçici HTML
flxl_set_image_language_data( $att_id, 'en', [ 'alt', 'caption', 'variant_id' ] );
flxl_set_term_translation( $term_id, 'en', $name, $desc );
```

`WP_Query` argümanı: `'flxl_lang' => 'en'` (yalnız o dil, yedek yok) ya da `'all'`. Ön yüz sorguları istek diline göre kendiliğinden süzülür.

## Filtreler

| Filtre | Ne |
|---|---|
| `flxl_post_types` | Çevrilen içerik türleri |
| `flxl_shared_post_ids` | Her dilde aynı olan sayfa ID'leri (varsayılan: WC sepet/ödeme/hesabım) |
| `flxl_copy_meta_skip` | Çeviri oluşturulurken kopyalanmayan meta anahtarları |
| `flxl_text_fields` | Dile göre girilen site metinleri: `anahtar => [option, key?, textarea?, label?]` (ör. tema alt bilgi metni) |
| `flxl_hreflang` | `( $code, $locale )` hreflang değeri |
| `flxl_hreflang_links` | Basılacak hreflang listesi |
| `flxl_switcher_items` | Dil seçici satırları |
| `flxl_switcher_place` | Temadaki seçici yeri: `footer` (varsayılan; başlıktaki `fluxwp/dil-secici` ve mobil menü `.flx-mnav-lang` gizlenir, alt bilgi parçasına `.flxl-footer-lang` eklenir), `header`, `both` |
| `flxl_geo_headers` | Ülke kodu okunan sunucu değişkenleri |
| `flxl_catalog` | Panelden eklenebilen diller |
| `flxl_skip_link_blocks` | Bağlantıları çevrilmeyecek bloklar |

Eylemler: `flxl_post_language_set ( $id, $code )`, `flxl_translation_saved ( $id, $src_id, $code, $fields )`.

## REST

`GET /wp-json/wp/v2/pages?lang=en`, yanıtta `lang` ve `translations`; `POST …/pages/12 {"lang":"en"}`.
`/en/wp-json/…` ile yapılan istek İngilizce çalışır (yerel ayar, terim adları).
`GET /wp-json/flx-lang/v1/geo` → `{country}` (önbelleksiz).

## MCP / Flux AI araçları

`flxl_languages`, `flxl_untranslated`, `flxl_translations`, `flxl_save_translation`, `flxl_set_language`,
`flxl_image_language`, `flxl_term_translation`, `flxl_site_texts`.
Flux SEO çekirdek araçları sarılır: `list_content` (`lang`, satırda `lang`/`translations`), `get_content`
(`lang`, `translations`), `create_content` (`lang`, `translation_of`), `update_content` (`lang`).

Önerilen AI akışı: `flxl_languages` → `flxl_untranslated lang=en` → her içerik için `get_content` →
metni çevir (blok yapısını koru) → `flxl_save_translation source_id lang=en title content slug seo status`.

## Diğer paketler için

- Dilsiz kalması gereken yeni sayfa (ör. bağış ödeme adımı) → `flxl_shared_post_ids`.
- E-posta/WhatsApp metni ziyaretçinin dilinde: `flxl_switch_language( flxl_order_language( $order ) )` … `flxl_restore_language()`.
- Kendi önbellek anahtarınızda dil: `flxl_current_language()`.
- `home_url()` ön yüzde istek dilinin önekini taşır; dilsiz adres gerekiyorsa `flxl_raw( fn )`.

## Uygulama

Modül `lang` ayar sayfasını `options-general.php?page=flx-lang` web görünümünde açar.
