---
title: Ayarlar ve ayar şeması
slug: ayarlar
parent: eklenti-yazma
order: 1
excerpt: Ayarlar panelden girilir, tek option dizisinde saklanır ve flx_settings_schema ile ön ayar ve AI'ye tanıtılır.
---
FluxWP'de ayarlar **panelden** girilir. API anahtarı, sunucu adresi, token gibi değerler `wp-config.php` sabitine bağlanmaz.

## Saklama

Eklentinin ayarları tek bir option dizisinde durur: `flxex_settings`. Varsayılanlar bir fonksiyonda toplanır, okuma her zaman birleştirerek yapılır:

```php
function flxex_defaults() {
	return array( 'enabled' => 1, 'limit' => 20, 'mode' => 'auto' );
}
function flxex_settings() {
	$s = get_option( 'flxex_settings', array() );
	return array_merge( flxex_defaults(), is_array( $s ) ? $s : array() );
}
```

Gizli değerleri (API anahtarı) düz yazmayın; şifreli saklayın ve ekranda yalnız son karakterlerini gösterin.

## Kaydetme

- Form `admin-post.php`'ye gider, `check_admin_referer()` ve `current_user_can()` ile doğrulanır.
- Her alan tek tek temizlenir (`sanitize_text_field`, `absint`, izin listesi).
- Kayıttan sonra `wp_safe_redirect()` ile ayar sayfasına `?msg=saved` ile dönün.

> [!WARNING]
> Formdan gelmeyen onay kutusunu "kapalı" saymadan önce, isteğin gerçekten bu formdan geldiğini ayırın (`<option>[_form]=1` gibi gizli alan). Ön ayar ve AI ayarı kısmi dizilerle yazabilir; eksik anahtar "değişmedi" demektir.

## Ayar şeması: `flx_settings_schema`

Ön ayarlar (Flux Setup) ve AI, hangi option'da hangi alanın olduğunu bu şemadan okur. Şemada olmayan alan ön ayar önizlemesinde uyarı verir.

```php
add_filter( 'flx_settings_schema', function ( $s ) {
	$s['flxex_settings'] = array(
		'label'  => 'Flux Example',
		'fields' => array(
			'enabled' => array( 'type' => 'bool', 'default' => true, 'desc' => 'Turns the feature on.' ),
			'limit'   => array( 'type' => 'int', 'default' => 20, 'desc' => 'Max items per page.' ),
			'mode'    => array( 'type' => 'enum', 'enum' => array( 'auto', 'manual' ), 'default' => 'auto' ),
		),
	);
	return $s;
} );
```

Türler: `bool`, `int`, `string`, `text`, `html`, `enum`, `color`, `url`, `page`, `object`, `list`. `desc` İngilizce yazılır; AI okur.

Ön ayar sürümü değişince ayarlar eşitlenir ve yalnız bildirilen alanlar yazılır. Ayrıntı Flux Setup `API.md` dosyasında.
