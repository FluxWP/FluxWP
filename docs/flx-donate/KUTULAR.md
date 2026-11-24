# Flux Donate · Bağış detayında kutular (2.35.0)

Bağış detay sayfası WooCommerce sipariş ekranı gibi kutulardan oluşur; görünüm Flux kartıdır.

## Kullanım
- Her kutunun sağ üst köşesinde: yukarı/aşağı ok, "–" daralt. Geniş ekranda (≥1101 px, fare) ayrıca ⠿ tutamaktan sürüklenir.
- Oklar sayfa sırasını izler (önce ana sütun, sonra yan sütun); böylece kutu sütun değiştirebilir, telefonda da çalışır.
- Düzen kullanıcı başına saklanır (user meta `flxb_boxes`, anahtar `donation`). Sayfa PHP'den hazır sırayla basılır, yüklenince kaymaz.

## WooCommerce sipariş kutuları
- Bağışın WooCommerce siparişi varsa, başka eklentilerin sipariş ekranına eklediği kutular (`add_meta_boxes`, HPOS ve posts) bağış sayfasında açılır. Kutu id'si `wc:<kutu id>`.
- Gösterilmeyenler: `woocommerce-order-data/items/notes/actions/downloads`, `order_custom`, `postcustom`, Flux Donate'in kendi "Bağış" kutusu (`flxwb-box`). Filtre `flxb_wc_box_skip`.
- Kaydet: alan içeren kutularda. Tüm sipariş kutularının alanları birlikte gönderilir (sipariş ekranının tek formu gibi; işaretsiz onay kutusu yanlış "kapalı" kaydedilmesin diye). `woocommerce_meta_nonce` da gönderilir. WooCommerce'in kendi kayıt işleyicileri devre dışı bırakılıp `woocommerce_process_shop_order_meta` çalışır, sonra `$order->save()`.
- Sınırlar: yalnız `save_post` ile kaydeden eklenti (posts depolama) kapsanmaz. Script'ini yalnız sipariş ekranında yükleyen kutunun JS'i çalışmaz; eklenti `flxb_wc_boxes_enqueue` kancasında yükleyebilir (WooCommerce Order attribution script'i Flux Donate yüklüyor). Dosya alanı kopyalanmaz. Sipariş ekranı her zaman ⋯ menüsündeki "WooCommerce siparişi" linkinde.
- Yetki: `edit_shop_orders` ya da `manage_woocommerce` + Flux Donate yönetim yetkisi; yalnız kendi referanslarını gören kullanıcıya gösterilmez.

## Sipariş notları
- Yan sütunda yerel kutu: notlar (sistem/özel/bağışçıya), not ekle (özel ya da bağışçıya e-postayla), sil (flxDialog onayı).

## Geliştirici API
```php
// Bağış sayfasına kutu ekle (id önüne "x:" eklenir).
add_filter( 'flxb_detail_boxes', function ( $boxes, $donation ) {
	$boxes[] = array(
		'id'     => 'my-box',
		'title'  => __( 'My box', 'my-plugin' ),
		'col'    => 'side',            // main | side
		'render' => function ( $donation ) { echo '<p>…</p>'; }, // ya da 'body' => '<p>…</p>'
	);
	return $boxes;
}, 10, 2 );

// Sipariş kutusunun script'i bağış sayfasında.
add_action( 'flxb_wc_boxes_enqueue', function ( $order, $ids ) {
	if ( in_array( 'my_order_box', $ids, true ) ) {
		wp_enqueue_script( 'my-order-box' );
	}
}, 10, 2 );
```
Genel sınıf `FLXB_Boxes::render( $screen, $boxes )` başka Flux Donate sayfalarında da kullanılabilir (kutu: `id`, `title`, `col`, `html` ya da `body`/`render`).
