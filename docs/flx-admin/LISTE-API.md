# Flux Admin ortak liste API'si (Flux Admin 1.2.0+)

Admin liste ekranları (Sertifikalar, Bağışlar, Kuyruk, Günlük…) için tek tip filtre çubuğu ve sayfalama.
Lib Flux Admin'de; paketler `function_exists()` ile kullanır, Flux Admin yoksa eski çıktılarına düşer. Özellik denetimi: `defined( 'FLXA_LIST_API' )`.

## Görünüm

- 1. satır: arama kutusu + **Filtreler** düğmesi (seçili ek filtre sayısı rozet olarak).
- 2. satır: 2 temel filtre (telefonda yan yana). Filtre varken **Temizle** bağlantısı.
- Diğer filtreler **Filtreler** paneline girer (telefonda 2 sütun, masaüstünde otomatik ızgara). Açık/kapalı durumu oturum boyunca liste başına hatırlanır.
- Açılır liste / tarih değişince form kendiliğinden gönderilir; arama Enter ile. "Filtrele" düğmesi otomatik gönderimde gizlenir (silmeyin, JS kapalıysa çalışır).
- Boş alanlar ve `paged` gönderimde adrese yazılmaz (filtre değişince 1. sayfa).
- Sayfalama: `21–40 / 160`, ‹ 1 2 3 … 8 ›, **Sayfa başı** 10/20/50/100. Seçim kullanıcı + liste anahtarı başına saklanır (user meta `flxa_list_pp`).

## Filtre formu

Formun **doğrudan çocukları** alan sayılır (tek `<select>`, `<input>` ya da içinde alan olan `<label>`/`<div>`).
İlk `input[type=search]` arama satırına gider. Görünür kalacak alanlar `data-flxa-primary` ile işaretlenir; işaret yoksa ilk N alan (varsayılan 2). `data-flxa-more` işaretli alan her zaman panele gider.

```php
<form method="get" class="flxs-filters"<?php echo function_exists( 'flxa_list_form_attrs' ) ? flxa_list_form_attrs( array( 'key' => 'flxs-certs' ) ) : ''; // phpcs:ignore ?>>
	<input type="hidden" name="page" value="flxs">
	<input type="search" name="s" …>
	<select name="status" data-flxa-primary>…</select>
	<select name="event" data-flxa-primary>…</select>
	<select name="template">…</select>
	<select name="ref">…</select>
	<button class="button"><?php esc_html_e( 'Filtrele', 'flx-certificates' ); ?></button>
</form>
```

`flxa_list_form_attrs( $args )`: `primary` (int, 2), `autosubmit` (bool, true), `key` (panel durumunu hatırlama anahtarı). Çağrı CSS/JS'i de yükler.

## Sayfa başı + sayfalama

```php
$per = function_exists( 'flxa_list_per_page' ) ? flxa_list_per_page( 'flxs-certs', 20 ) : 20;
// … sorgu $per ile …
if ( function_exists( 'flxa_list_pagination' ) ) {
	flxa_list_pagination( $q['total'], $per, $paged );   // args: page_arg ('paged'), echo (true)
} else {
	flxs_pagination( $q['total'], $per, $paged );        // eski çıktı
}
```

- `flxa_list_per_page( $key, $default )`: `?per_page=` geçerli seçenekse kaydeder ve döner; yoksa kayıtlı değer, o da yoksa `$default`.
- `flxa_list_pagination()` toplam 0 iken hiçbir şey basmaz; tek sayfa olsa da adet seçici görünür (toplam en küçük seçenekten büyükse).
- Seçenekler: filtre `flxa_list_per_page_options` (varsayılan `[10, 20, 50, 100]`).
- Adet seçici formu mevcut GET parametrelerini (filtreler) taşır; `paged` sıfırlanır.

## Notlar

- Toplu işlem çubuğu (Seçilenlere… / Uygula) bu lib'in parçası değil; liste formunun dışında kalır.
- Dinamik eklenen form için `window.flxaListInit()`.
- Stil Flux Admin token'larını kullanır (`--flxa-*`), kabuk kapalıysa WP renklerine düşer; koyu tema kendiliğinden.

## Geçiş durumu

| Paket | Ekran | Durum |
|---|---|---|
| Flux Certificates | Sertifikalar, Kişiler, Kuyruk, Kayıtlar | geçti (1.8.0) |
| Flux Donate | Bağışlar, Bağışçılar, Abonelikler (kendi "Detaylı filtre"si var) | bekliyor |
| Flux Mail | Kayıtlar | geçti (1.8.2) |
| Flux AI | Kayıtlar | geçti (0.5.6, PR #36) |
