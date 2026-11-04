---
title: Eklentiyi AI destekli yapmak
slug: ai-destekli-eklenti
section: ai
order: 1
excerpt: AI kullanan her FluxWP eklentisi Flux AI üzerinden geçer; flxai_chat, flxai_ready, kaynak kaydı, hatalar ve yedek kod.
---
Eklentinize AI özelliği (özet, öneri, sınıflandırma, sohbet aracı) eklerken sağlayıcıya **doğrudan istek atmayın**. Tüm AI çağrıları **Flux AI & MCP** (`flx-ai`) üzerinden geçer. Böylece:

- API anahtarları, model seçimi ve önbellek tek yerde,
- günlük / haftalık / aylık harcama sınırı tüm paketlerde geçerli,
- her istek **Kayıtlar** ve **Raporlar** ekranına eklenti ve amaç bazında düşer,
- yönetici bir eklentinin AI'sini **Bağlı sistemler**'den tek tıkla kapatabilir.

## 1. Kendini tanıt

```php
add_filter( 'flxai_systems', function ( $s ) {
	$s['flx-ornek'] = array(
		'label'  => 'Flux Example',
		'plugin' => plugin_basename( FLXEX_FILE ),
		'page'   => 'admin.php?page=flx-ornek',
	);
	return $s;
} );
add_filter( 'flxai_sources', function ( $s ) {
	$s['flx-ornek'] = 'Flux Example';
	return $s;
} );
add_filter( 'flxai_purpose_labels', function ( $l ) {
	$l['summary'] = __( 'Özet', 'flx-ornek' );
	return $l;
} );
```

## 2. Hazır mı?

```php
function flxex_ai_ready() {
	return function_exists( 'flxai_chat' )
		&& flxai_ready( 'flx-ornek' )                 // anahtar var mı
		&& flxai_source_enabled( 'flx-ornek' );       // Bağlı sistemler'de açık mı
}
```

Hazır değilse AI düğmesini gizleyin ya da "Flux AI'yi kurun" bağlantısı gösterin. Flux AI kurulu değilken eklenti **hata vermeden** çalışmaya devam etmeli.

## 3. İstek

```php
$res = flxai_chat(
	array(
		array( 'role' => 'system', 'content' => 'You summarize orders in one sentence.' ),
		array( 'role' => 'user', 'content' => $text ),
	),
	array(
		'source'     => 'flx-ornek',   // zorunlu: raporlar ve eklentiye özel model
		'purpose'    => 'summary',     // raporda "Amaca göre"
		'max_tokens' => 400,
		'timeout'    => 40,            // paylaşımlı sunucu ~60 sn'de keser
	)
);
if ( is_wp_error( $res ) ) {
	if ( flxai_is_account_error( $res ) ) {
		// harcama sınırı, anahtar, kapalı sistem: toplu işi durdurun
	}
	return $res;
}
$summary = trim( $res['content'] );
```

Mesajlar OpenAI biçimindedir; Claude'a otomatik çevrilir. Tek metin için kısa yol: `flxai_complete( $prompt, array( 'source' => 'flx-ornek', 'system' => '…' ) )`.

Dönen değerde `content`, varsa `tool_calls`, `_finish` (`stop`, `tool_calls`, `length`) ve `_usage` (model, token, maliyet) bulunur.

## 4. Sistem talimatını önbelleğe uygun yazın

Sistem içeriği parça dizisi olabilir. Sabit talimatı başa koyup `cache => true` işaretleyin, değişen bilgiyi sonra ekleyin:

```php
'content' => array(
	array( 'type' => 'text', 'text' => FLXEX_RULES, 'cache' => true ), // sabit
	array( 'type' => 'text', 'text' => 'Order: ' . $json ),             // değişken
),
```

## 5. Hatalar

| Kod | Anlam |
|---|---|
| `flxai_limit` | Harcama sınırı doldu (402) |
| `flxai_key` | Anahtar yok |
| `flxai_source_off` | Yönetici bu eklentinin AI'sini kapattı |
| `flxai_http` | Ağ hatası |
| `flxai_api` | Sağlayıcı hatası (`data.http`, `data.msg`) |
| `flxai_empty` | Boş cevap |

> [!TIP]
> Kullanıcıya sağlayıcı hata metnini olduğu gibi göstermeyin; kısa, çevrilmiş bir mesaj ve "tekrar dene" yeterli. Ayrıntı Flux AI › Kayıtlar'da zaten var.

## 6. Ayar ekranı

- Kendi anahtar alanınızı açmayın; "Anahtarlar Flux AI'den gelir" notu yeterli.
- Eklentiye özel model seçtirmek isterseniz: okuma `flxai_model_for( 'flx-ornek' )`, yazma `flxai_set_source_model( 'flx-ornek', $model )`, liste için `flxai_enqueue_model_picker()` ve alana `data-flxai-model`.

## 7. Uzun işler

Paylaşımlı sunucuda PHP isteği ~60 saniyede kesilir. Toplu AI işlerini (100 ürün açıklaması gibi) **parça parça** yapın: tarayıcı bir REST ucunu yoklar, her çağrıda birkaç öğe işlenir, durum option ya da tabloda tutulur. `flxai_is_account_error()` `true` dönerse kuyruğu durdurun.

## Sonraki adım

- Özelliğinizi Flux AI sohbetine ve dış ajanlara açmak: [[ai-destekli-eklenti/mcp-araci-yazma]]
- Maliyet, gizlilik ve test: [[ai-destekli-eklenti/iyi-uygulamalar]]
