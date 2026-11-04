---
title: Sistem kontrolü
slug: sistem-kontrolu
parent: eklenti-yazma
order: 4
excerpt: Her paket kendi sağlık satırlarını flx_system_checks ile ortak Sistem kontrolü ekranına ekler.
---
FluxWP paketleri ve tema, sağlık durumlarını tek bir **Sistem kontrolü** ekranında gösterir. Hata varsa üst çubukta "Sistem: N hata" görünür.

```php
add_filter( 'flx_system_checks', function ( $checks ) {
	$checks[] = array(
		'id'           => 'flxex.key',            // <önek>.<konu>
		'source'       => 'Flux Example',
		'group'        => 'Config',               // Cron, Payments, Plugins, Config, Email, Security, Forms …
		'label'        => __( 'API anahtarı', 'flx-ornek' ),
		'status'       => flxex_has_key() ? 'ok' : 'warning', // ok | warning | error | info
		'message'      => flxex_has_key() ? __( 'Anahtar girilmiş.', 'flx-ornek' ) : __( 'Anahtar yok; özellik kapalı.', 'flx-ornek' ),
		'action_url'   => admin_url( 'admin.php?page=flx-ornek' ),
		'action_label' => __( 'Ayarlar', 'flx-ornek' ),
	);
	return $checks;
} );

add_filter( 'flx_system_checks_versions', function ( $v ) {
	$v['flx-ornek'] = FLXEX_VERSION; // sürüm değişince gizlenen uyarılar yeniden görünür
	return $v;
} );
```

## Kurallar

- Kontroller **ucuz** olmalı: option, sabit, birkaç sorgu. Her sayfa açılışında dış HTTP isteği yok.
- Dış servis testi yalnız yönetici "Test" bağlantısına tıklayınca çalışır; sonuç saklanıp satırda gösterilir.
- Gizli anahtar hiçbir satırda yazılmaz.
- Mesaj kişiye yöneliktir: ne bulundu ve ne yapmalı.
