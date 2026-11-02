<?php require ( getenv( 'WPENV' ) ?: '/tmp/wpenv' ) . '/site/wp-load.php';
// Flux Lang: birkac yazi ve sayfaya Ingilizce ceviri (yalniz yerel demo)
wp_set_current_user( 1 );
require_once ABSPATH . 'wp-admin/includes/plugin.php'; if ( ! is_plugin_active( 'flx-lang/flx-lang.php' ) ) { activate_plugin( 'flx-lang/flx-lang.php' ); passthru( 'php ' . escapeshellarg( __FILE__ ) ); exit; }
$tr = array(
	"Belgrad Ormanı'nda 10 bin fidan" => array( '10,000 saplings in Belgrad Forest', 'Together with our volunteers we planted 10,000 saplings in Belgrad Forest. Here is how you can join the next planting day or donate a memorial sapling.' ),
	'Okullarda doğa eğitimi'          => array( 'Nature education in schools', 'This term we ran forest and sapling workshops in 12 schools with 1,800 pupils.' ),
	'Yangın sonrası ağaçlandırma'     => array( 'Reforestation after wildfires', 'Burned areas are replanted with native species chosen with forestry engineers.' ),
	'Hatıra Ormanı nedir?'            => array( 'What is the Memory Forest?', 'Keep the name of a loved one alive by donating a sapling to the Memory Forest.' ),
);
foreach ( $tr as $title => $en ) {
	$p = get_page_by_title( $title, OBJECT, array( 'post', 'page' ) );
	if ( ! $p ) { echo "yok: $title\n"; continue; }
	flxl_set_post_language( $p->ID, 'tr' );
	$t = flxl_get_translation( $p->ID, 'en' );
	if ( $t ) { wp_update_post( array( 'ID' => is_object( $t ) ? $t->ID : (int) $t, 'post_status' => 'publish' ) ); continue; } // seed-tema taslaga almis olabilir
	$id = wp_insert_post( array( 'post_type' => $p->post_type, 'post_status' => 'publish', 'post_title' => $en[0], 'post_content' => '<!-- wp:paragraph --><p>' . $en[1] . '</p><!-- /wp:paragraph -->', 'post_excerpt' => $en[1], 'post_date' => $p->post_date ) );
	set_post_thumbnail( $id, get_post_thumbnail_id( $p->ID ) );
	flxl_set_post_language( $id, 'en' );
	flxl_link_translations( array( 'tr' => $p->ID, 'en' => $id ) );
}
update_user_meta( 1, 'manageedit-postcolumnshidden', array( 'author', 'tags', 'comments', 'flxan_views' ) );
echo "ok\n";
