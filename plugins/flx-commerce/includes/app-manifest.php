<?php
/**
 * Flux Commerce uygulama manifesti. Yalnız Flux App etkinken kaydolur.
 * Ayarlar web görünümünde kalır.
 *
 * @package FLX_Commerce
 */

defined( 'ABSPATH' ) || exit;

add_action(
	'plugins_loaded',
	function () {
		if ( ! defined( 'FLXAPP_VERSION' ) ) {
			return;
		}
		add_filter( 'flxapp_manifest', 'flxcm_app_manifest', 10, 2 );
	},
	20
);

/**
 * @param array   $m
 * @param WP_User $user
 */
function flxcm_app_manifest( $m, $user ) {
	unset( $user );
	if ( ! class_exists( 'WooCommerce' ) ) {
		return $m;
	}
	$m['flxcm'] = flxcm_app_manifest_def();
	return $m;
}

function flxcm_app_manifest_def() {
	$page = array( 'mode' => 'page', 'param' => 'page', 'size_param' => 'per_page', 'size' => 25, 'items' => 'items', 'total' => 'total', 'pages' => 'pages' );
	return array(
		'id'       => 'flxcm',
		'plugin'   => 'flx-commerce',
		'version'  => FLXCM_VERSION,
		'min_app'  => '1.0.0',
		'title'    => __( 'Store', 'flx-commerce' ),
		'icon'     => 'cart',
		'order'    => 22,
		'menu'     => 'flx-commerce',
		'entry'    => 'overview',
		'tabs'     => array( 'overview', 'customers', 'receipts' ),
		'caps'     => array(
			'view'    => array( 'manage_woocommerce', 'view_woocommerce_reports' ),
			'approve' => array( 'manage_woocommerce', 'edit_shop_orders' ),
			'manage'  => 'manage_woocommerce',
		),
		'fallback' => array( 'web' => 'admin.php?page=flx-commerce' ),
		'routes'   => array(
			'admin.php?page=flx-commerce'          => array( 'screen' => 'overview' ),
			'admin.php?page=flx-commerce-customers' => array( 'screen' => 'customers' ),
			'admin.php?page=flx-commerce-settings' => array( 'screen' => 'settings' ),
		),
		'search'   => array(
			'source'  => 'customers',
			'param'   => 's',
			'item'    => array( 'title' => '{{name|default:email}}', 'subtitle' => '{{email}}' ),
			'onPress' => array( 'target' => 'screen:flxcm/customer', 'params' => array( 'id' => '{{id}}' ) ),
		),
		'sources'  => array(
			'summary'   => array( 'path' => 'flx-commerce/v1/summary', 'cache' => array( 'stale' => 120, 'persist' => true ) ),
			'customers' => array( 'path' => 'flx-commerce/v1/customers', 'paging' => $page, 'key' => 'id', 'cache' => array( 'stale' => 60, 'persist' => true ) ),
			'customer'  => array( 'path' => 'flx-commerce/v1/customers/{{params.id}}', 'cache' => array( 'stale' => 30 ) ),
			'receipts'  => array( 'path' => 'flx-commerce/v1/receipts', 'paging' => $page, 'key' => 'id', 'cache' => array( 'stale' => 30 ), 'poll' => 60 ),
			'receipt'   => array( 'path' => 'flx-commerce/v1/receipts/{{params.id}}', 'cache' => array( 'stale' => 15 ) ),
		),
		'screens'  => array(
			'overview'  => array(
				'type'     => 'dashboard',
				'title'    => __( 'Sales summary', 'flx-commerce' ),
				'sources'  => array( 'summary' ),
				'sections' => array(
					array(
						'type'  => 'stats',
						'items' => array(
							array( 'label' => __( 'Revenue', 'flx-commerce' ), 'value' => '{{data.revenue_label}}' ),
							array( 'label' => __( 'Orders', 'flx-commerce' ), 'value' => '{{data.orders|number}}' ),
							array( 'label' => __( 'Average order', 'flx-commerce' ), 'value' => '{{data.aov_label}}' ),
							array( 'label' => __( 'New customers', 'flx-commerce' ), 'value' => '{{data.new_customers|number}}' ),
							array( 'label' => __( 'Awaiting a receipt', 'flx-commerce' ), 'value' => '{{data.awaiting|number}}', 'tone' => 'warn', 'onPress' => array( 'target' => 'screen:flxcm/receipts' ) ),
						),
					),
					array(
						'type'   => 'chart',
						'title'  => __( 'Daily revenue', 'flx-commerce' ),
						'chart'  => 'bar',
						'path'   => 'series',
						'series' => array( array( 'label' => '{{label}}', 'value' => '{{revenue}}' ) ),
					),
					array( 'type' => 'actions', 'items' => array( 'open_receipts', 'open_settings' ) ),
				),
			),
			'customers' => array(
				'type'    => 'list',
				'title'   => __( 'Customers', 'flx-commerce' ),
				'source'  => 'customers',
				'columns' => array(
					array( 'label' => __( 'Customers', 'flx-commerce' ), 'value' => '{{name|default:email}}', 'width' => 2 ),
					array( 'label' => __( 'Orders', 'flx-commerce' ), 'value' => '{{orders|number}}' ),
					array( 'label' => __( 'Total', 'flx-commerce' ), 'value' => '{{total_label}}', 'align' => 'end' ),
					array( 'label' => __( 'Last order', 'flx-commerce' ), 'value' => '{{last_label}}', 'align' => 'end' ),
				),
				'search'  => array( 'param' => 's', 'placeholder' => __( 'Name, e-mail or phone', 'flx-commerce' ) ),
				'filters' => array(
					array(
						'param'   => 'segment',
						'label'   => __( 'Segment', 'flx-commerce' ),
						'type'    => 'select',
						'options' => array(
							array( 'value' => 'repeat', 'label' => __( 'Repeat', 'flx-commerce' ) ),
							array( 'value' => 'vip', 'label' => __( 'High spenders', 'flx-commerce' ) ),
							array( 'value' => 'new', 'label' => __( 'New', 'flx-commerce' ) ),
							array( 'value' => 'lapsed', 'label' => __( 'Lapsed', 'flx-commerce' ) ),
						),
					),
				),
				'sort'    => array(
					'param'       => 'orderby',
					'order_param' => 'order',
					'options'     => array(
						array( 'value' => 'last_at', 'label' => __( 'Last order', 'flx-commerce' ) ),
						array( 'value' => 'total', 'label' => __( 'Total', 'flx-commerce' ) ),
						array( 'value' => 'orders', 'label' => __( 'Orders', 'flx-commerce' ) ),
						array( 'value' => 'name', 'label' => __( 'Name', 'flx-commerce' ) ),
					),
				),
				'item'    => array(
					'title'    => '{{name|default:email}}',
					'subtitle' => '{{email}}',
					'meta'     => '{{total_label}}',
					'caption'  => '{{last_label}}',
					'avatar'   => array( 'text' => '{{name|default:email}}' ),
				),
				'onPress' => array( 'target' => 'screen:flxcm/customer', 'params' => array( 'id' => '{{id}}' ) ),
				'empty'   => array( 'title' => __( 'No customers', 'flx-commerce' ), 'body' => __( 'Customers appear here as orders come in.', 'flx-commerce' ) ),
			),
			'customer'  => array(
				'type'     => 'detail',
				'title'    => __( 'Customers', 'flx-commerce' ),
				'source'   => 'customer',
				'header'   => array( 'title' => '{{name|default:email}}', 'subtitle' => '{{email}}', 'avatar' => array( 'text' => '{{name|default:email}}' ) ),
				'sections' => array(
					array(
						'type'  => 'stats',
						'items' => array(
							array( 'label' => __( 'Total', 'flx-commerce' ), 'value' => '{{total_label}}' ),
							array( 'label' => __( 'Orders', 'flx-commerce' ), 'value' => '{{orders|number}}' ),
						),
					),
					array(
						'type'   => 'fields',
						'title'  => __( 'Contact', 'flx-commerce' ),
						'fields' => array(
							array( 'label' => __( 'E-mail', 'flx-commerce' ), 'value' => '{{email}}', 'link' => 'mailto:{{email}}', 'copy' => true, 'when' => array( 'path' => 'email', 'op' => 'filled' ) ),
							array( 'label' => __( 'Phone', 'flx-commerce' ), 'value' => '{{phone}}', 'link' => 'tel:{{phone}}', 'when' => array( 'path' => 'phone', 'op' => 'filled' ) ),
							array( 'label' => __( 'City', 'flx-commerce' ), 'value' => '{{city}}', 'when' => array( 'path' => 'city', 'op' => 'filled' ) ),
							array( 'label' => __( 'First order', 'flx-commerce' ), 'value' => '{{first_label}}' ),
							array( 'label' => __( 'Last order', 'flx-commerce' ), 'value' => '{{last_label}}' ),
							array( 'label' => __( 'Note', 'flx-commerce' ), 'value' => '{{note}}', 'when' => array( 'path' => 'note', 'op' => 'filled' ) ),
						),
					),
				),
				'actions'  => array( 'header' => array( 'call', 'email' ) ),
			),
			'receipts'  => array(
				'type'    => 'list',
				'title'   => __( 'Receipt approval', 'flx-commerce' ),
				'source'  => 'receipts',
				'can'     => 'approve',
				'item'    => array(
					'title'    => '{{name|default:email}}',
					'subtitle' => '#{{number}}',
					'meta'     => '{{total_label}}',
					'caption'  => '{{date_label}}',
					'badges'   => array(
						array( 'text' => __( 'Receipt', 'flx-commerce' ), 'tone' => 'accent', 'when' => array( 'path' => 'receipts', 'op' => 'gt', 'value' => 0 ) ),
					),
				),
				'onPress' => array( 'target' => 'screen:flxcm/receipt', 'params' => array( 'id' => '{{id}}' ) ),
				'empty'   => array( 'title' => __( 'No receipts waiting', 'flx-commerce' ), 'body' => __( 'Bank-transfer orders appear here while payment is pending.', 'flx-commerce' ) ),
			),
			'receipt'   => array(
				'type'     => 'detail',
				'title'    => __( 'Receipt', 'flx-commerce' ),
				'source'   => 'receipt',
				'can'      => 'approve',
				'header'   => array( 'title' => '{{name|default:email}}', 'subtitle' => '#{{number}}' ),
				'sections' => array(
					array(
						'type'   => 'fields',
						'title'  => __( 'Orders', 'flx-commerce' ),
						'fields' => array(
							array( 'label' => __( 'Amount', 'flx-commerce' ), 'value' => '{{total_label}}' ),
							array( 'label' => __( 'Reference', 'flx-commerce' ), 'value' => '{{reference}}', 'copy' => true ),
							array( 'label' => __( 'E-mail', 'flx-commerce' ), 'value' => '{{email}}', 'when' => array( 'path' => 'email', 'op' => 'filled' ) ),
							array( 'label' => __( 'Phone', 'flx-commerce' ), 'value' => '{{phone}}', 'link' => 'tel:{{phone}}', 'when' => array( 'path' => 'phone', 'op' => 'filled' ) ),
						),
					),
					array(
						'type'  => 'list',
						'title' => __( 'Files', 'flx-commerce' ),
						'path'  => 'files',
						'item'  => array( 'title' => '{{name}}' ),
						'onPress' => array( 'target' => 'url:{{url}}' ),
					),
				),
				'actions'  => array( 'footer' => array( 'approve' ) ),
			),
			'settings'  => array(
				'type'  => 'web',
				'title' => __( 'Settings', 'flx-commerce' ),
				'path'  => 'admin.php?page=flx-commerce-settings',
				'can'   => 'manage',
			),
		),
		'actions'  => array(
			'open_receipts' => array( 'label' => __( 'Receipt approval', 'flx-commerce' ), 'icon' => 'check', 'can' => 'approve', 'navigate' => array( 'target' => 'screen:flxcm/receipts' ) ),
			'open_settings' => array( 'label' => __( 'Settings', 'flx-commerce' ), 'icon' => 'settings', 'can' => 'manage', 'web' => 'admin.php?page=flx-commerce-settings' ),
			'approve'       => array(
				'label'   => __( 'Confirm payment', 'flx-commerce' ),
				'icon'    => 'check',
				'tone'    => 'ok',
				'can'     => 'approve',
				'confirm' => array( 'title' => __( 'Approve this payment?', 'flx-commerce' ), 'body' => __( 'The order is marked as paid.', 'flx-commerce' ), 'ok' => __( 'Approve', 'flx-commerce' ) ),
				'request' => array( 'method' => 'POST', 'path' => 'flx-commerce/v1/receipts/{{data.id}}/approve' ),
				'after'   => array( 'toast' => __( 'Payment approved', 'flx-commerce' ), 'back' => true, 'invalidate' => array( 'receipts', 'summary' ) ),
			),
			'call'          => array( 'label' => __( 'Call', 'flx-commerce' ), 'icon' => 'phone', 'when' => array( 'path' => 'phone', 'op' => 'filled' ), 'open' => 'tel:{{phone}}' ),
			'email'         => array( 'label' => __( 'E-mail', 'flx-commerce' ), 'icon' => 'mail', 'when' => array( 'path' => 'email', 'op' => 'filled' ), 'open' => 'mailto:{{email}}' ),
		),
	);
}
