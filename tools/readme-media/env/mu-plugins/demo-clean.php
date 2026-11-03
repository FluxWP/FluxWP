<?php
/**
 * Test sitesi: README çekimlerinde konu dışı WooCommerce uyarılarını ve kurulum kutusunu gizler.
 */
add_filter( 'woocommerce_show_admin_notice', '__return_false' );
add_action( 'wp_dashboard_setup', function () {
	remove_meta_box( 'wc_admin_dashboard_setup', 'dashboard', 'normal' );
	remove_meta_box( 'woocommerce_dashboard_status', 'dashboard', 'normal' );
}, 99 );
add_filter( 'woocommerce_coming_soon_exclude', '__return_true' );
