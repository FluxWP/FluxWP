# Flux Commerce — API

Önek `flxcm_` / `FLXCM_`, REST `flx-commerce/v1`, metin alanı `flx-commerce`.

## Fonksiyonlar
| | |
|---|---|
| `flxcm_setting( $key )`, `flxcm_settings()` | Ayarlar (option `flxcm_settings`) |
| `flxcm_paid_statuses()` | Ciroya sayılan durumlar (wc- öneki yok) |
| `flxcm_count_order( WC_Order )` | Sipariş istatistiklere girer mi |
| `FLXCM_Stats::summary( $from, $to )` | Y-m-d aralığı özeti: revenue, orders, aov, items, customers, series, products, categories, methods |
| `FLXCM_Bank::reference( WC_Order )` | Havale açıklaması |
| `FLXCM_Bank::mark_paid( WC_Order )` | Havale ödemesini onaylar |
| `FLXCM_Receipts::of( WC_Order )` | Yüklenen dekontlar |
| `FLXCM_Customers::recompute( $email )` | Müşteri satırını yeniden hesaplar |

## Filtreler
| Filtre | |
|---|---|
| `flxcm_default_settings` | Varsayılan ayarlar |
| `flxcm_paid_statuses` | Sayılan durumlar |
| `flxcm_count_order( bool, WC_Order )` | Siparişi istatistikten çıkarma (Flux Donate bağışları varsayılan olarak çıkar) |
| `flxcm_vip_threshold` | "En çok harcayan" eşiği |
| `flxcm_bank_account` | Havale hesabı { holder, iban, bank } |
| `flxcm_free_shipping_threshold` | Ücretsiz kargo eşiği |
| `flxcm_feed_products`, `flxcm_feed_item_extra` | Feed ürünleri / ek XML |
| `flxcm_wa_bank_placed`, `flxcm_wa_bank_confirmed` | Müşteri WhatsApp metinleri |
| `flxcm_phone` | Telefon normalleştirme |

## Eylemler
`flxcm_loaded`, `flxcm_bank_placed( $order )`, `flxcm_receipt_uploaded( $order, $rec )`, `flxcm_bank_confirmed( $order )`, `flxcm_daily`.

## REST
`POST /wp-json/flx-commerce/v1/receipt` — `order`, `key` (sipariş anahtarı), `dekont` (dosya). Yetki sipariş anahtarıdır.

## Sipariş meta
`_flxcm_ref`, `_flxcm_code`, `_flxcm_receipts`, `_flxcm_confirmed`, `_flxcm_confirmed_by`, `_flxcm_wa_placed`, `_flxcm_wa_admin`.
Ürün meta `_flxcm_no_feed` = feed'e girmez.

## Paylaşılan kancalar
`flx_system_checks`, `flxa_page_heads`, `flxa_package_heads`, `flxa_menu_logos`, `flxa_default_groups` (Flux Admin), `flxu_package_icons` (FluxWP Panel), Flux Mail `flxi_send_message`.
