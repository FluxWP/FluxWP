<?php require ( getenv( 'WPENV' ) ?: '/tmp/wpenv' ) . '/site/wp-load.php';
// Flux QR Menu: ornek menu + /menu/ adresi (yalniz yerel)
wp_set_current_user( 1 );
foreach ( flxq_menu_ids( 'any' ) as $old ) { flxq_delete_menu( $old ); }
$data = json_decode( (string) file_get_contents( FLXQ_DIR . 'assets/sample-menu.json' ) );
$data = flxq_localize_bundled_images( $data );
$id   = flxq_create_menu( 'Yenikapı Tanıtım Günleri', $data, 'import' );
update_post_meta( $id, '_flxq_slug', 'menu' );
flxq_rebuild_routes();
// birkac duzenleme: gecmis sekmesi dolu gorunsun
$d = flxq_menu_data( $id );
if ( isset( $d->kategoriler[0]->urunler[1] ) ) { $d->kategoriler[0]->urunler[1]->tukendi = true; }
flxq_save_menu( $id, 'Yenikapı Tanıtım Günleri', $d, array( 'summary' => 'Ürün tükendi olarak işaretlendi' ) );
echo "ok $id\n";
