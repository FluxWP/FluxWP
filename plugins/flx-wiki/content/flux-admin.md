---
title: Flux Admin entegrasyonu
slug: flux-admin
parent: eklenti-yazma
order: 3
excerpt: Sayfa başlığı, menü logosu, menü grubu, uygulama kısayolu ve güncelleme ekranı logosu için filtreler.
---
Flux Admin kuruluysa tüm FluxWP ekranları tek bir menüde, aynı başlık ve renklerle görünür. Eklentiniz Flux Admin'e bağımlı olmamalı; filtreler yalnız Flux Admin varken etkili olur.

## Sayfa başlığı

```php
add_filter( 'flxa_page_heads', function ( $map ) {
	$map['flx-ornek'] = 'flx-ornek'; // admin.php?page=… → paket
	return $map;
} );
add_filter( 'flxa_package_heads', function ( $heads ) {
	$heads['flx-ornek'] = array( 'Flux Example', __( 'Kısa açıklama', 'flx-ornek' ) );
	return $heads;
} );
```

## Menü logosu ve grup

```php
add_filter( 'flxa_menu_logos', function ( $m ) {
	$m['flx-ornek'] = 'flx-ornek.svg'; // flx-admin/assets/logos/ altında (build.py üretir)
	return $m;
} );
add_filter( 'flxa_default_groups', function ( $groups ) {
	if ( isset( $groups['system'][1] ) && ! in_array( 'flx-ornek', $groups['system'][1], true ) ) {
		$groups['system'][1][] = 'flx-ornek';
	}
	return $groups;
} );
```

Gruplar: `general`, `comms` (İletişim), `donate` (Bağış), `content` (İçerik), `growth` (Büyüme), `screens` (Ekranlar), `system` (Sistem). Menü öğesi bir yazı türüyse slug `edit.php?post_type=<tür>` olur.

## Güncellemeler ekranı logosu

```php
add_filter( 'flxu_package_icons', function ( $icons, $slug ) {
	if ( 'flx-ornek' === $slug && ! $icons ) {
		$url   = FLXEX_URL . 'assets/icon.svg?ver=' . FLXEX_VERSION;
		$icons = array( 'svg' => $url, '1x' => $url, '2x' => $url );
	}
	return $icons;
}, 10, 2 );
```

## Uygulama kısayolu

FluxApp ve Flux Admin hızlı erişim listesine ekran eklemek için `flxa_apps`:

```php
add_filter( 'flxa_apps', function ( $apps ) {
	$apps['ornek'] = array(
		'label' => __( 'Örnek', 'flx-ornek' ),
		'url'   => admin_url( 'admin.php?page=flx-ornek' ),
		'cap'   => 'manage_options',
		'icon'  => 'sparkles',
	);
	return $apps;
} );
```
