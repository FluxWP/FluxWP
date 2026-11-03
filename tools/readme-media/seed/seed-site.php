<?php
// Genel demo site (Örnek Vakfı / example.org): bağış, proje ve kurumsal sayfalar + üst menü. Gerçek kurum içeriği yok.
require ( getenv( 'WPENV' ) ?: '/tmp/wpenv' ) . '/site/wp-load.php';
$pg = function ( $title, $slug, $content, $parent = 0 ) {
	$p = get_page_by_path( $parent ? get_post_field( 'post_name', $parent ) . '/' . $slug : $slug );
	$a = array( 'post_title' => $title, 'post_name' => $slug, 'post_type' => 'page', 'post_status' => 'publish', 'post_content' => $content, 'post_parent' => $parent );
	if ( $p ) { $a['ID'] = $p->ID; return wp_update_post( $a ); }
	return wp_insert_post( $a );
};
$par = function ( $t ) { return "<!-- wp:paragraph -->\n<p>$t</p>\n<!-- /wp:paragraph -->\n"; };
$h2  = function ( $t ) { return "<!-- wp:heading -->\n<h2 class=\"wp-block-heading\">$t</h2>\n<!-- /wp:heading -->\n"; };
$about = $pg( 'Hakkımızda', 'hakkimizda', $par( 'Örnek Vakfı; ağaçlandırma, eğitim bursu ve afet desteği alanlarında çalışan bir sivil toplum kuruluşudur. Bağışçılarımızla birlikte her yıl binlerce fidanı toprakla buluşturuyoruz.' ) . $h2( 'Neler yapıyoruz?' ) . $par( 'Fidan dikimi, gönüllü etkinlikleri, okullarda çevre eğitimi ve ihtiyaç sahiplerine düzenli destek.' ) );
$proj  = $pg( 'Projeler', 'projeler', $par( 'Devam eden projelerimiz: Hatıra Ormanı, Okul Bahçeleri ve Afet Bölgesi Fidanlığı.' ) );
$pg( 'Hatıra Ormanı', 'hatira-ormani', $par( 'Sevdiklerinizin adına dikilen fidanlarla büyüyen ortak ormanımız. Her bağış için adınıza bir sertifika gönderilir.' ) );
$don   = $pg( 'Bağış Yap', 'bagis-yap', "<!-- wp:shortcode -->\n[flx_bagis_secenekleri tur=\"fidan,hatira-ormani,genel\"]\n<!-- /wp:shortcode -->\n" );
$cont  = $pg( 'İletişim', 'iletisim', $par( 'E-posta: bilgi@example.org · Telefon: +90 212 000 00 00' ) );
// WooCommerce ödeme sayfası "odeme" adıyla (eski cfg ve seed'ler bu yolu kullanır)
$co = (int) get_option( 'woocommerce_checkout_page_id' );
if ( $co ) { wp_update_post( array( 'ID' => $co, 'post_name' => 'odeme', 'post_title' => 'Ödeme' ) ); }
foreach ( get_posts( array( 'post_type' => 'page', 'name' => 'order-received', 'numberposts' => -1, 'post_status' => 'any' ) ) as $p ) {
	if ( (int) $p->post_parent !== $co ) { wp_delete_post( $p->ID, true ); }
}
if ( $co && ! get_page_by_path( 'odeme/order-received' ) ) { $pg( 'Bağış tamamlandı', 'order-received', '', $co ); }
foreach ( array( 'sample-page' ) as $s ) { if ( $p = get_page_by_path( $s ) ) { wp_delete_post( $p->ID, true ); } }
// Üst menü (blok tema: wp_navigation)
$link = function ( $label, $id ) { return '<!-- wp:navigation-link {"label":"' . $label . '","type":"page","id":' . (int) $id . ',"url":"' . esc_url( get_permalink( $id ) ) . '","kind":"post-type"} /-->'; };
$shop = (int) get_option( 'woocommerce_shop_page_id' );
$nav  = $link( 'Hakkımızda', $about ) . $link( 'Projeler', $proj ) . ( $shop ? $link( 'Mağaza', $shop ) : '' ) . $link( 'İletişim', $cont ) . $link( 'Bağış Yap', $don );
foreach ( get_posts( array( 'post_type' => 'wp_navigation', 'numberposts' => -1, 'post_status' => 'any' ) ) as $n ) { wp_delete_post( $n->ID, true ); }
wp_insert_post( array( 'post_type' => 'wp_navigation', 'post_title' => 'Ana menü', 'post_status' => 'publish', 'post_content' => $nav ) );
update_option( 'blogdescription', 'Birlikte daha yeşil bir gelecek' );
echo "site ok\n";
