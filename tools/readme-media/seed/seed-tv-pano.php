<?php require ( getenv( 'WPENV' ) ?: '/tmp/wpenv' ) . '/site/wp-load.php';
// Flux TV Board: bugune ait demo WooCommerce bagislari (yalniz yerel; onceki demo siparisleri silinir)
global $wpdb; // wc_get_orders SQLite'ta bos donebilir; demo siparisleri dogrudan meta tablosundan bulunur
$ids = array_unique( array_merge( (array) $wpdb->get_col( "SELECT order_id FROM {$wpdb->prefix}wc_orders_meta WHERE meta_key='_readme_demo'" ), (array) $wpdb->get_col( "SELECT post_id FROM {$wpdb->postmeta} WHERE meta_key='_readme_demo'" ) ) );
foreach ( $ids as $id ) { $o = wc_get_order( $id ); if ( $o ) { $o->delete( true ); } else { wp_delete_post( $id, true ); } }
$p = array();
foreach ( wc_get_products( array( 'limit' => 10 ) ) as $x ) { $p[ $x->get_name() ] = $x; }
$fidan = $p['Fidan Bağışı'] ?? reset( $p );
$hat   = $p['Hatıra Ormanı 2026'] ?? $fidan;
$names = array( array( 'Ayşe', 'Kara', 5, $fidan ), array( 'Mehmet', 'Yıldız', 2, $hat ), array( 'Elif', 'Aydın', 10, $fidan ), array( 'Can', 'Öztürk', 1, $fidan ), array( 'Zehra', 'Demir', 3, $hat ), array( 'Ali', 'Çelik', 20, $fidan ), array( 'Deniz', 'Arslan', 4, $fidan ), array( 'Selin', 'Koç', 2, $hat ), array( 'Murat', 'Şahin', 6, $fidan ) );
$now = current_time( 'timestamp' ); $start = strtotime( 'today 08:10', $now );
foreach ( $names as $i => $n ) {
	$o = wc_create_order();
	$o->add_product( $n[3], $n[2] );
	$o->set_billing_first_name( $n[0] ); $o->set_billing_last_name( $n[1] ); $o->set_billing_email( 'demo' . $i . '@example.org' );
	$o->calculate_totals();
	$t = min( $now - 300, $start + $i * (int) ( ( $now - $start ) / count( $names ) ) );
	$o->set_date_created( $t ); $o->set_date_paid( $t );
	$o->update_meta_data( '_readme_demo', 1 );
	$o->set_status( 'processing' ); $o->save();
}
echo 'ok ' . count( $names ) . "\n";
