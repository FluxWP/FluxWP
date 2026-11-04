---
title: AI için iyi uygulamalar
slug: iyi-uygulamalar
parent: ai-destekli-eklenti
order: 2
excerpt: Maliyet, önbellek, model seçimi, kişisel veri, onay, ziyaretçiye açık AI ve test için kısa kontrol listesi.
---
## Maliyet

- Varsayılan model ucuz ve hızlı olsun; pahalı modeli yalnız gerekli amaçlarda (`purpose`) seçin.
- `max_tokens` değerini işe göre verin; kısa özet için 300–500 yeter.
- Sabit sistem talimatını `cache => true` parçasına koyun.
- Aynı girdi için cevabı kendi tarafınızda saklayın (ör. ürün açıklaması bir kez üretilir).

## Ziyaretçiye açık AI

Herkese açık bir AI özelliği (ör. bu wiki'deki **Wiki'ye sor**) bütçeyi hızla tüketebilir:

- Varsayılanı "giriş yapmış kullanıcılar" yapın.
- Kişi ya da IP başına saatlik sınır koyun.
- Bağlamı kısıtlayın: model yalnız sizin verdiğiniz içerikten cevap versin, kaynakları listelesin.
- Uzunluk sınırı koyun (soru 1.000 karakter gibi).

## Kişisel veri

- Modele yalnız gereken alanları gönderin. Telefon, e-posta, IBAN gibi bilgiler gerekmiyorsa çıkarın.
- Flux AI kayıtları e-posta, 10+ haneli sayı, IBAN ve anahtarları maskeler. Kendi alanınız için `flxai_log_mask` filtresine kural ekleyin.

## Onay

Veri değiştiren her AI işlemi kullanıcı onayından geçmeli. Flux AI sohbetinde `readonly => false` araçlar zaten onay ister. Kendi ekranınızda da önizleme gösterip "Uygula" ile yazın.

## Cevabı gösterme

- Model Markdown döner; ekranda gösterirken temizleyin (`wp_kses_post`).
- Sohbet gibi uzun cevaplarda akış kullanın: `flxai_chat( $messages, array( 'stream' => function ( $ev ) { … } ) )`.
- Grafik ya da tablo için ```widget ve ```mermaid blokları: `flxai_widget_rules()`.

## Test

- Flux AI yokken eklenti açılıyor mu, AI düğmeleri gizli mi?
- Anahtar yanlışken kullanıcı anlamlı mesaj görüyor mu?
- Harcama sınırı dolunca toplu iş duruyor mu?
- Bağlı sistemler'den kapatınca özellik gerçekten kapanıyor mu?
