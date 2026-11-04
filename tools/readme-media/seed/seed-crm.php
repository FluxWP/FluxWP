<?php
/* Flux CRM demo: bağış/sipariş/sohbet/form seed'lerinin üstüne 2 yıllık geçmiş, gönüllü/ortak rolleri,
 * etiketler, notlar ve izinler. Önce seed-bagis, seed-commerce, seed-cagri, seed-form çalışmış olmalı. */
require ( getenv( 'WPENV' ) ?: '/tmp/wpenv' ) . '/site/wp-load.php';
global $wpdb; mt_srand( 23 ); wp_set_current_user( 1 );
foreach ( array( 'contacts', 'links', 'events', 'tags', 'contact_tags', 'segments' ) as $t ) {
	$wpdb->query( 'DELETE FROM ' . flxcrm_table( $t ) );
	$wpdb->query( "DELETE FROM sqlite_sequence WHERE name = '" . flxcrm_table( $t ) . "'" ); // Sabit id'ler (cfg adresleri).
}
delete_option( 'flxcrm_segments_seeded' );

/* Geçmiş bağışlar: bir kısmı uzaklaşmış, bir kısmı yıllık düzenli. */
$donors = $wpdb->get_col( 'SELECT id FROM ' . flxb_table( 'donors' ) . ' ORDER BY id' );
$fid    = (int) $wpdb->get_var( 'SELECT id FROM ' . flxb_table( 'types' ) . " WHERE slug='fidan'" );
$now    = current_time( 'timestamp' );
$n      = 0;
$lapsed = array( 'Ahmet Korkmaz', 'Sevgi Arıkan', 'Hakan Uzun', 'Gülşen Kara', 'Barış Önal', 'Pınar Coşkun', 'Cem Yavuz', 'Leyla Sarı' );
foreach ( $lapsed as $i => $nm ) {
	$donors[] = FLXB_Donors::upsert( array( 'name' => $nm, 'phone' => '0533' . sprintf( '%07d', 7654300 + $i * 41 ), 'email' => strtolower( remove_accents( str_replace( ' ', '.', $nm ) ) ) . '@example.org' ) );
}
foreach ( $donors as $k => $don ) {
	$times = $k >= count( $donors ) - count( $lapsed ) ? mt_rand( 2, 6 ) : mt_rand( 0, 4 );
	$start = $k >= count( $donors ) - count( $lapsed ) ? mt_rand( 140, 700 ) : mt_rand( 90, 720 );
	for ( $i = 0; $i < $times; $i++ ) {
		$d   = $start + $i * mt_rand( 25, 120 );
		$utc = gmdate( 'Y-m-d H:i:s', $now - $d * DAY_IN_SECONDS - mt_rand( 0, 50000 ) );
		$q   = mt_rand( 1, 6 );
		FLXB_Donations::record( array( 'provider' => 'flxb_havale', 'provider_ref' => 'hist-' . ( ++$n ), 'amount' => 150 * $q, 'currency' => 'TRY', 'type' => 'one_time', 'status' => 'completed', 'donor_id' => $don, 'type_id' => $fid, 'option_key' => '1-fidan', 'option_label' => '1 fidan', 'quantity' => $q, 'unit_price' => 150, 'source' => 'woocommerce', 'created_at' => $utc ) );
	}
}
echo "history $n\n";

FLXCRM_Sync::run_all();

/* Elle eklenen kişiler: gönüllüler, ortaklar. */
$extra = array(
	array( 'Selim Doğan', 'selim.dogan@example.org', '05054410021', array( 'volunteer' ), 'Kadıköy' ),
	array( 'Ebru Kalkan', 'ebru.kalkan@example.org', '05054410022', array( 'volunteer' ), 'Üsküdar' ),
	array( 'Tuna Erol', 'tuna.erol@example.org', '05054410023', array( 'volunteer' ), 'Beşiktaş' ),
	array( 'Yeşil Lojistik A.Ş.', 'iletisim@yesil-lojistik.example', '02124410024', array( 'partner' ), 'Ataşehir' ),
	array( 'Ormanköy Kooperatifi', 'bilgi@ormankoy.example', '02164410025', array( 'partner' ), 'Şile' ),
);
foreach ( $extra as $e ) {
	$id = FLXCRM_Contacts::upsert( array( 'name' => $e[0], 'email' => $e[1], 'phone' => $e[2], 'roles' => $e[3], 'city' => $e[4], 'source' => 'manual', 'consent_email' => 1 ) );
	FLXCRM_REST::manual_event( $id, 'meeting', 'Tanışma toplantısı yapıldı, fidan dikim günü için takvim konuşuldu.' );
}

/* Gönüllü olan bağışçılar, etiketler, izinler, şehirler. */
$cities = array( 'Kadıköy', 'Beşiktaş', 'Üsküdar', 'Ataşehir', 'Bakırköy', 'Şişli', 'Ankara', 'İzmir', 'Bursa', 'Kocaeli' );
$tags   = array( 'Kurban 2026', 'Fidan dikim günü', 'Kurumsal', 'Basın', 'VIP' );
$ids    = $wpdb->get_col( 'SELECT id FROM ' . flxcrm_table( 'contacts' ) . ' ORDER BY id' );
foreach ( $ids as $i => $id ) {
	$upd = array( 'city' => $cities[ $i % count( $cities ) ], 'consent_email' => mt_rand( 1, 10 ) <= 7 ? 1 : 0, 'consent_whatsapp' => mt_rand( 1, 10 ) <= 5 ? 1 : 0, 'consent_sms' => mt_rand( 1, 10 ) <= 3 ? 1 : 0 );
	$wpdb->update( flxcrm_table( 'contacts' ), $upd, array( 'id' => $id ) );
	if ( 0 === $i % 5 ) {
		FLXCRM_Tags::attach( $id, FLXCRM_Tags::ensure( $tags[ ( $i / 5 ) % count( $tags ) ] ) );
	}
	if ( 0 === $i % 7 ) {
		FLXCRM_Contacts::add_roles( $id, array( 'volunteer' ) );
	}
}
FLXCRM_Contacts::flush_stats();

/* Örnek kişiye notlar. */
$top = (int) $wpdb->get_var( 'SELECT id FROM ' . flxcrm_table( 'contacts' ) . " WHERE segment='champion' ORDER BY ltv DESC LIMIT 1" );
FLXCRM_Tags::attach( $top, FLXCRM_Tags::ensure( 'VIP' ) );
FLXCRM_REST::manual_event( $top, 'call', 'Teşekkür araması yapıldı. Kurban bağışını bu yıl da düşündüğünü söyledi, Haziran başında hatırlatalım.' );
FLXCRM_REST::manual_event( $top, 'note', 'Şirketi adına kurumsal bağış için görüşme istiyor; Ebru Hanım ile tanıştırılacak.' );
FLXCRM_Scoring::run();
FLXCRM_Segments::seed();
FLXCRM_Segments::save( array( 'name' => 'İstanbul gönüllüleri', 'description' => 'Fidan dikim günleri için WhatsApp duyuruları', 'rules' => array( 'match' => 'all', 'rules' => array( array( 'field' => 'role', 'op' => 'has', 'value' => 'volunteer' ), array( 'field' => 'consent', 'op' => 'yes', 'value' => 'whatsapp' ) ) ) ) );
FLXCRM_Segments::recount_all();
echo 'contacts ' . count( $ids ) . " top $top\n";
