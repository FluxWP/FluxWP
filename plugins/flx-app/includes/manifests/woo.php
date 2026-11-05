<?php
/**
 * WooCommerce manifesti (`woo`): siparişler (liste, detay, durum, not), ürünler (fiyat/stok hızlı düzen), müşteriler.
 * WooCommerce REST (`wc/v3`) üzerinden; HPOS'tan bağımsız. Yalnız WooCommerce etkinse kaydolur.
 */

defined( 'ABSPATH' ) || exit;

add_filter(
	'flxapp_manifest',
	function ( $m, $user ) {
		if ( class_exists( 'WooCommerce' ) && ! isset( $m['woo'] ) ) {
			$m['woo'] = flxapp_woo_manifest();
		}
		return $m;
	},
	6,
	2
);

function flxapp_woo_manifest() {
	$statuses = array();
	foreach ( function_exists( 'wc_get_order_statuses' ) ? wc_get_order_statuses() : array() as $k => $label ) {
		$statuses[] = array( 'value' => substr( $k, 3 ), 'label' => $label );
	}
	$cur = function_exists( 'get_woocommerce_currency' ) ? get_woocommerce_currency() : 'TRY';
	return array(
		'id'       => 'woo',
		'plugin'   => 'woocommerce',
		'version'  => defined( 'WC_VERSION' ) ? WC_VERSION : '',
		'min_app'  => '1.0.0',
		'title'    => __( 'Mağaza', 'flx-app' ),
		'icon'     => 'cart',
		'order'    => 8,
		'menu'     => 'woocommerce',
		'entry'    => 'orders',
		'tabs'     => array( 'orders', 'products', 'customers' ),
		'caps'     => array(
			'view'      => array( 'manage_woocommerce', 'edit_shop_orders' ),
			'orders'    => array( 'manage_woocommerce', 'edit_shop_orders' ),
			'products'  => array( 'manage_woocommerce', 'edit_products' ),
			'customers' => array( 'manage_woocommerce' ),
		),
		'fallback' => array( 'web' => 'admin.php?page=wc-admin' ),
		'routes'   => array(
			'admin.php?page=wc-orders&action=edit&id={id}' => array( 'screen' => 'order', 'params' => array( 'id' => '{id}' ) ),
			'post.php?post={id}&action=edit&post_type=shop_order' => array( 'screen' => 'order', 'params' => array( 'id' => '{id}' ) ),
			'admin.php?page=wc-orders'                     => array( 'screen' => 'orders' ),
			'edit.php?post_type=shop_order'                => array( 'screen' => 'orders' ),
			'edit.php?post_type=product'                   => array( 'screen' => 'products' ),
			'users.php?role=customer'                      => array( 'screen' => 'customers' ),
		),
		'search'   => array(
			'source'  => 'orders',
			'param'   => 'search',
			'item'    => array( 'title' => '#{{number}} · {{billing.first_name}} {{billing.last_name}}', 'subtitle' => '{{total}} {{currency}}' ),
			'onPress' => array( 'target' => 'screen:woo/order', 'params' => array( 'id' => '{{id}}' ) ),
		),
		'sources'  => array(
			'orders'    => array(
				'path'   => 'wc/v3/orders',
				'query'  => array( 'orderby' => 'date', 'order' => 'desc' ),
				'paging' => array( 'mode' => 'wp', 'param' => 'page', 'size_param' => 'per_page', 'size' => 20 ),
				'key'    => 'id',
				'cache'  => array( 'stale' => 30, 'persist' => true ),
				'poll'   => 60,
			),
			'order'     => array( 'path' => 'wc/v3/orders/{{params.id}}', 'cache' => array( 'stale' => 15 ) ),
			'notes'     => array( 'path' => 'wc/v3/orders/{{params.id}}/notes', 'paging' => array( 'mode' => 'none' ) ),
			'products'  => array(
				'path'   => 'wc/v3/products',
				'query'  => array( 'orderby' => 'date', 'order' => 'desc', 'status' => 'any' ),
				'paging' => array( 'mode' => 'wp', 'param' => 'page', 'size_param' => 'per_page', 'size' => 20 ),
				'key'    => 'id',
				'cache'  => array( 'stale' => 120, 'persist' => true ),
			),
			'product'   => array( 'path' => 'wc/v3/products/{{params.id}}' ),
			'customers' => array(
				'path'   => 'wc/v3/customers',
				'query'  => array( 'orderby' => 'registered_date', 'order' => 'desc', 'role' => 'all' ),
				'paging' => array( 'mode' => 'wp', 'param' => 'page', 'size_param' => 'per_page', 'size' => 25 ),
				'key'    => 'id',
			),
			'customer'  => array( 'path' => 'wc/v3/customers/{{params.id}}' ),
		),
		'screens'  => array(
			'orders'       => array(
				'type'    => 'list',
				'title'   => __( 'Siparişler', 'flx-app' ),
				'can'     => 'orders',
				'source'  => 'orders',
				'columns' => array(
					array( 'label' => __( 'Sipariş', 'flx-app' ), 'value' => '#{{number}}' ),
					array( 'label' => __( 'Müşteri', 'flx-app' ), 'value' => '{{billing.first_name}} {{billing.last_name}}', 'width' => 2 ),
					array( 'label' => __( 'Durum', 'flx-app' ), 'value' => '{{status}}' ),
					array( 'label' => __( 'Tarih', 'flx-app' ), 'value' => '{{date_created_gmt|ago}}' ),
					array( 'label' => __( 'Tutar', 'flx-app' ), 'value' => '{{total|money:currency}}', 'align' => 'end' ),
				),
				'search'  => array( 'param' => 'search', 'placeholder' => __( 'Sipariş no, ad ya da e-posta', 'flx-app' ) ),
				'filters' => array( array( 'param' => 'status', 'label' => __( 'Durum', 'flx-app' ), 'type' => 'select', 'options' => $statuses ) ),
				'item'    => array(
					'title'    => '#{{number}} · {{billing.first_name}} {{billing.last_name}}',
					'subtitle' => '{{billing.email|default:billing.phone}}',
					'meta'     => '{{total|money:currency}}',
					'caption'  => '{{date_created_gmt|ago}}',
					'badges'   => array(
						array( 'text' => __( 'Ödeme bekliyor', 'flx-app' ), 'tone' => 'warn', 'when' => array( 'path' => 'status', 'op' => 'in', 'value' => array( 'pending', 'on-hold' ) ) ),
						array( 'text' => __( 'Hazırlanıyor', 'flx-app' ), 'tone' => 'accent', 'when' => array( 'path' => 'status', 'op' => 'eq', 'value' => 'processing' ) ),
						array( 'text' => __( 'İptal', 'flx-app' ), 'tone' => 'danger', 'when' => array( 'path' => 'status', 'op' => 'in', 'value' => array( 'cancelled', 'failed', 'refunded' ) ) ),
					),
				),
				'onPress' => array( 'target' => 'screen:woo/order', 'params' => array( 'id' => '{{id}}' ) ),
				'actions' => array( 'row' => array( 'order_complete' ) ),
				'empty'   => array( 'title' => __( 'Sipariş yok', 'flx-app' ), 'body' => __( 'Bu durumda sipariş bulunmuyor.', 'flx-app' ) ),
			),
			'order'        => array(
				'type'     => 'detail',
				'title'    => __( 'Sipariş', 'flx-app' ),
				'can'      => 'orders',
				'source'   => 'order',
				'header'   => array( 'title' => '#{{number}}', 'subtitle' => '{{billing.first_name}} {{billing.last_name}}', 'quick' => array( 'order_call', 'order_mail' ) ),
				'sections' => array(
					array(
						'type'  => 'stats',
						'items' => array(
							array( 'label' => __( 'Tutar', 'flx-app' ), 'value' => '{{total|money:currency}}' ),
							array( 'label' => __( 'Durum', 'flx-app' ), 'value' => '{{status}}' ),
							array( 'label' => __( 'Ödeme', 'flx-app' ), 'value' => '{{payment_method_title|default:\'—\'}}' ),
						),
					),
					array( 'type' => 'list', 'title' => __( 'Ürünler', 'flx-app' ), 'path' => 'line_items', 'item' => array( 'title' => '{{name}}', 'subtitle' => '× {{quantity}}', 'meta' => '{{total|money:' . $cur . '}}' ) ),
					array(
						'type'   => 'fields',
						'title'  => __( 'Müşteri', 'flx-app' ),
						'fields' => array(
							array( 'label' => __( 'E-posta', 'flx-app' ), 'value' => '{{billing.email}}', 'link' => 'mailto:{{billing.email}}', 'copy' => true, 'when' => array( 'path' => 'billing.email', 'op' => 'filled' ) ),
							array( 'label' => __( 'Telefon', 'flx-app' ), 'value' => '{{billing.phone}}', 'link' => 'tel:{{billing.phone}}', 'copy' => true, 'when' => array( 'path' => 'billing.phone', 'op' => 'filled' ) ),
							array( 'label' => __( 'Adres', 'flx-app' ), 'value' => '{{shipping.address_1|default:billing.address_1}} {{shipping.city|default:billing.city}}', 'when' => array( 'path' => 'billing.address_1', 'op' => 'filled' ) ),
							array( 'label' => __( 'Müşteri notu', 'flx-app' ), 'value' => '{{customer_note}}', 'when' => array( 'path' => 'customer_note', 'op' => 'filled' ) ),
						),
					),
				),
				'actions'  => array( 'header' => array( 'order_web' ), 'footer' => array( 'order_status', 'order_note', 'order_notes' ) ),
			),
			'order_notes'  => array(
				'type'   => 'list',
				'title'  => __( 'Sipariş notları', 'flx-app' ),
				'can'    => 'orders',
				'source' => 'notes',
				'item'   => array(
					'title'    => '{{note|truncate:200}}',
					'subtitle' => '{{author}}',
					'caption'  => '{{date_created_gmt|ago}}',
					'badges'   => array( array( 'text' => __( 'Müşteriye gönderildi', 'flx-app' ), 'tone' => 'info', 'when' => array( 'path' => 'customer_note', 'op' => 'truthy' ) ) ),
				),
				'actions' => array( 'header' => array( 'order_note' ) ),
				'empty'   => array( 'title' => __( 'Not yok', 'flx-app' ) ),
			),
			'products'     => array(
				'type'    => 'list',
				'title'   => __( 'Ürünler', 'flx-app' ),
				'can'     => 'products',
				'source'  => 'products',
				'layout'  => 'rows',
				'search'  => array( 'param' => 'search', 'placeholder' => __( 'Ürün adı ya da SKU', 'flx-app' ) ),
				'filters' => array(
					array(
						'param'   => 'stock_status',
						'label'   => __( 'Stok', 'flx-app' ),
						'type'    => 'select',
						'options' => array(
							array( 'value' => 'instock', 'label' => __( 'Stokta', 'flx-app' ) ),
							array( 'value' => 'outofstock', 'label' => __( 'Tükendi', 'flx-app' ) ),
							array( 'value' => 'onbackorder', 'label' => __( 'Ön sipariş', 'flx-app' ) ),
						),
					),
				),
				'item'    => array(
					'title'    => '{{name}}',
					'subtitle' => '{{sku}}',
					'meta'     => '{{price|money:' . $cur . '}}',
					'image'    => '{{images.0.src}}',
					'badges'   => array(
						array( 'text' => __( 'Tükendi', 'flx-app' ), 'tone' => 'danger', 'when' => array( 'path' => 'stock_status', 'op' => 'eq', 'value' => 'outofstock' ) ),
						array( 'text' => __( 'Taslak', 'flx-app' ), 'tone' => 'muted', 'when' => array( 'path' => 'status', 'op' => 'ne', 'value' => 'publish' ) ),
					),
				),
				'onPress' => array( 'target' => 'screen:woo/product_form', 'params' => array( 'id' => '{{id}}' ) ),
				'actions' => array( 'row' => array( 'product_web' ) ),
			),
			'product_form' => array(
				'type'   => 'form',
				'title'  => __( 'Ürün', 'flx-app' ),
				'can'    => 'products',
				'load'   => 'product',
				'submit' => array( 'method' => 'POST', 'path' => 'wc/v3/products/{{params.id}}', 'path_update' => 'wc/v3/products/{{params.id}}' ),
				'fields' => array(
					array( 'name' => 'name', 'type' => 'text', 'label' => __( 'Ad', 'flx-app' ), 'required' => true ),
					array( 'name' => 'regular_price', 'type' => 'money', 'label' => __( 'Fiyat', 'flx-app' ), 'currency' => $cur, 'width' => 'half' ),
					array( 'name' => 'sale_price', 'type' => 'money', 'label' => __( 'İndirimli fiyat', 'flx-app' ), 'currency' => $cur, 'width' => 'half' ),
					array( 'name' => 'manage_stock', 'type' => 'switch', 'label' => __( 'Stok takibi', 'flx-app' ) ),
					array( 'name' => 'stock_quantity', 'type' => 'number', 'label' => __( 'Stok', 'flx-app' ), 'when' => array( 'path' => 'manage_stock', 'op' => 'truthy' ) ),
					array(
						'name'    => 'stock_status',
						'type'    => 'segmented',
						'label'   => __( 'Stok durumu', 'flx-app' ),
						'when'    => array( 'path' => 'manage_stock', 'op' => 'falsy' ),
						'options' => array(
							array( 'value' => 'instock', 'label' => __( 'Stokta', 'flx-app' ) ),
							array( 'value' => 'outofstock', 'label' => __( 'Tükendi', 'flx-app' ) ),
						),
					),
					array(
						'name'    => 'status',
						'type'    => 'select',
						'label'   => __( 'Durum', 'flx-app' ),
						'options' => array(
							array( 'value' => 'publish', 'label' => __( 'Yayımlandı', 'flx-app' ) ),
							array( 'value' => 'draft', 'label' => __( 'Taslak', 'flx-app' ) ),
							array( 'value' => 'private', 'label' => __( 'Özel', 'flx-app' ) ),
						),
					),
				),
				'after'  => array( 'toast' => __( 'Kaydedildi', 'flx-app' ), 'back' => true, 'invalidate' => array( 'products', 'product' ) ),
			),
			'customers'    => array(
				'type'    => 'list',
				'title'   => __( 'Müşteriler', 'flx-app' ),
				'can'     => 'customers',
				'source'  => 'customers',
				'search'  => array( 'param' => 'search', 'placeholder' => __( 'Ad ya da e-posta', 'flx-app' ) ),
				'item'    => array(
					'title'    => '{{first_name}} {{last_name}}',
					'subtitle' => '{{email}}',
					'avatar'   => array( 'image' => '{{avatar_url}}', 'text' => '{{first_name}} {{last_name}}' ),
				),
				'onPress' => array( 'target' => 'screen:woo/customer', 'params' => array( 'id' => '{{id}}' ) ),
			),
			'customer'     => array(
				'type'     => 'detail',
				'title'    => __( 'Müşteri', 'flx-app' ),
				'can'      => 'customers',
				'source'   => 'customer',
				'header'   => array( 'title' => '{{first_name}} {{last_name}}', 'subtitle' => '{{email}}', 'avatar' => array( 'image' => '{{avatar_url}}', 'text' => '{{first_name}}' ) ),
				'sections' => array(
					array(
						'type'   => 'fields',
						'fields' => array(
							array( 'label' => __( 'E-posta', 'flx-app' ), 'value' => '{{email}}', 'link' => 'mailto:{{email}}', 'copy' => true ),
							array( 'label' => __( 'Telefon', 'flx-app' ), 'value' => '{{billing.phone}}', 'link' => 'tel:{{billing.phone}}', 'when' => array( 'path' => 'billing.phone', 'op' => 'filled' ) ),
							array( 'label' => __( 'Şehir', 'flx-app' ), 'value' => '{{billing.city}}', 'when' => array( 'path' => 'billing.city', 'op' => 'filled' ) ),
							array( 'label' => __( 'Kayıt', 'flx-app' ), 'value' => '{{date_created_gmt|date}}' ),
						),
					),
				),
				'actions'  => array( 'header' => array( 'customer_orders' ) ),
			),
		),
		'actions'  => array(
			'order_complete'  => array(
				'label'      => __( 'Tamamlandı', 'flx-app' ),
				'icon'       => 'check',
				'can'        => 'orders',
				'when'       => array( 'path' => 'status', 'op' => 'eq', 'value' => 'processing' ),
				'request'    => array( 'method' => 'POST', 'path' => 'wc/v3/orders/{{id}}', 'body' => array( 'status' => 'completed' ) ),
				'optimistic' => array( 'path' => 'status', 'value' => 'completed' ),
				'after'      => array( 'toast' => __( 'Sipariş tamamlandı', 'flx-app' ), 'merge' => true ),
			),
			'order_status'    => array(
				'label'   => __( 'Durumu değiştir', 'flx-app' ),
				'icon'    => 'edit',
				'can'     => 'orders',
				'prompt'  => array( 'title' => __( 'Sipariş durumu', 'flx-app' ), 'fields' => array( array( 'name' => 'status', 'type' => 'select', 'label' => __( 'Durum', 'flx-app' ), 'default' => '{{data.status}}', 'options' => $statuses, 'required' => true ) ) ),
				'request' => array( 'method' => 'POST', 'path' => 'wc/v3/orders/{{data.id}}', 'body' => array( 'status' => '{{input.status}}' ) ),
				'after'   => array( 'toast' => __( 'Güncellendi', 'flx-app' ), 'merge' => true, 'invalidate' => array( 'orders' ) ),
			),
			'order_note'      => array(
				'label'   => __( 'Not ekle', 'flx-app' ),
				'icon'    => 'note',
				'can'     => 'orders',
				'prompt'  => array(
					'title'  => __( 'Sipariş notu', 'flx-app' ),
					'fields' => array(
						array( 'name' => 'note', 'type' => 'textarea', 'label' => __( 'Not', 'flx-app' ), 'required' => true ),
						array( 'name' => 'customer_note', 'type' => 'switch', 'label' => __( 'Müşteriye gönder', 'flx-app' ), 'default' => false ),
					),
				),
				'request' => array( 'method' => 'POST', 'path' => 'wc/v3/orders/{{data.id|default:params.id}}/notes', 'body' => array( 'note' => '{{input.note}}', 'customer_note' => '{{input.customer_note}}' ) ),
				'after'   => array( 'toast' => __( 'Not eklendi', 'flx-app' ), 'refresh' => true, 'invalidate' => array( 'notes' ) ),
			),
			'order_notes'     => array( 'label' => __( 'Notlar', 'flx-app' ), 'icon' => 'note', 'can' => 'orders', 'navigate' => array( 'target' => 'screen:woo/order_notes', 'params' => array( 'id' => '{{data.id}}' ) ) ),
			'order_web'       => array( 'label' => __( 'Panelde aç', 'flx-app' ), 'icon' => 'globe', 'web' => 'admin.php?page=wc-orders&action=edit&id={{id}}' ),
			'order_call'      => array( 'label' => __( 'Ara', 'flx-app' ), 'icon' => 'phone', 'when' => array( 'path' => 'billing.phone', 'op' => 'filled' ), 'open' => 'tel:{{billing.phone}}' ),
			'order_mail'      => array( 'label' => __( 'E-posta', 'flx-app' ), 'icon' => 'mail', 'when' => array( 'path' => 'billing.email', 'op' => 'filled' ), 'open' => 'mailto:{{billing.email}}' ),
			'product_web'     => array( 'label' => __( 'Panelde aç', 'flx-app' ), 'icon' => 'globe', 'web' => 'post.php?post={{id}}&action=edit' ),
			'customer_orders' => array( 'label' => __( 'Siparişleri', 'flx-app' ), 'icon' => 'cart', 'can' => 'orders', 'navigate' => array( 'target' => 'screen:woo/orders', 'params' => array( 'customer' => '{{data.id}}' ) ) ),
		),
	);
}
