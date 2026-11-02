<?php require ( getenv( 'WPENV' ) ?: '/tmp/wpenv' ) . '/site/wp-load.php';
// Flux Gorev: demo projeler, listeler, gorevler (yalniz yerel)
global $wpdb; wp_set_current_user( 1 );
flxg_maybe_upgrade();
flxg_update_settings( array( 'roles_use' => array( 'flx_personel', 'flx_idareci', 'flx_yonetici' ) ) ); flxg_users( true );
foreach ( array( 'spaces', 'folders', 'lists', 'members', 'tasks', 'assignees', 'watchers', 'tags', 'task_tags', 'fields', 'values', 'views', 'comments', 'activity', 'notifications' ) as $t ) {
	$wpdb->query( 'TRUNCATE TABLE ' . flxg_table( $t ) );
}
flxg_all_spaces( true ); flxg_all_lists( true ); flxg_all_folders( true );
$u = function ( $l ) { $x = get_user_by( 'login', $l ); return $x ? (int) $x->ID : 1; };
$A = 1; $AY = $u( 'ayse' ); $ME = $u( 'mehmet' ); $ZE = $u( 'zeynep' ); $EM = $u( 'emre' ); $EL = $u( 'elif' );
$d = function ( $n ) { return gmdate( 'Y-m-d', strtotime( "2026-11-01 +$n days" ) ); };
$s1 = flxg_create_space( array( 'name' => 'Ağaçlandırma', 'color' => '#2e7d32', 'icon' => 'tree' ), $A );
$s2 = flxg_create_space( array( 'name' => 'İletişim ve kampanya', 'color' => '#0a7cff' ), $A );
$s3 = flxg_create_space( array( 'name' => 'Yönetim', 'color' => '#a855f7' ), $A );
$l1 = flxg_create_list_row( array( 'space_id' => $s1, 'name' => '11 Kasım dikim etkinliği', 'owner_id' => $A ) );
$l2 = flxg_create_list_row( array( 'space_id' => $s1, 'name' => 'Fidan tedariki', 'owner_id' => $A ) );
$l3 = flxg_create_list_row( array( 'space_id' => $s2, 'name' => 'Sosyal medya takvimi', 'owner_id' => $A ) );
$l4 = flxg_create_list_row( array( 'space_id' => $s2, 'name' => 'Basın ve web', 'owner_id' => $A ) );
$l5 = flxg_create_list_row( array( 'space_id' => $s3, 'name' => 'Yönetim kurulu toplantıları', 'owner_id' => $A, 'kind' => 'meeting' ) );
$T = array(
	array( $l1, 'Sarıyer dikim alanı için Orman Müdürlüğü izni', 'done', 1, -12, array( $ME ), array( 'Resmi' ) ),
	array( $l1, 'Gönüllü kayıt formunu yayınla', 'done', 2, -8, array( $AY ), array( 'Web' ) ),
	array( $l1, 'Servis otobüsü kirala (3 araç)', 'doing', 1, 3, array( $EM ), array( 'Lojistik' ) ),
	array( $l1, 'Kürek, eldiven ve su tedariki', 'doing', 2, 5, array( $EM, $EL ), array( 'Lojistik' ) ),
	array( $l1, 'Okullara davet yazısı gönder', 'review', 2, 2, array( $AY , $A ), array( 'Resmi', 'Okul' ) ),
	array( $l1, 'Etkinlik günü akış planı', 'todo', 1, 6, array( $ZE , $A ), array() ),
	array( $l1, 'Hatıra fidanı sertifikalarını bas', 'todo', 2, 8, array( $AY ), array( 'Sertifika' ) ),
	array( $l1, 'Basın bülteni ve fotoğrafçı', 'todo', 3, 9, array( $ZE ), array( 'Basın' ) ),
	array( $l2, '2.000 meşe ve 1.500 çam fidanı siparişi', 'done', 1, -5, array( $ME ), array() ),
	array( $l2, 'Fidanlık teslim tarihini teyit et', 'doing', 1, 4, array( $ME ), array() ),
	array( $l2, 'Bağış adetleriyle fidan sayısını eşleştir', 'todo', 2, 7, array( $EL , $A ), array( 'Bağış' ) ),
	array( $l3, 'Kasım içerik takvimi', 'doing', 2, 1, array( $ZE , $A ), array( 'Instagram' ) ),
	array( $l3, '11 Kasım geri sayım hikâyeleri', 'todo', 2, 4, array( $ZE, $AY ), array( 'Instagram' ) ),
	array( $l3, 'YouTube: dikim günü kısa video', 'todo', 3, 12, array( $EM ), array( 'YouTube' ) ),
	array( $l3, 'LinkedIn kurumsal ortaklık duyurusu', 'review', 3, 2, array( $ZE ), array( 'LinkedIn' ) ),
	array( $l4, 'Web sitesinde etkinlik sayfası', 'done', 2, -3, array( $AY ), array( 'Web' ) ),
	array( $l4, 'Gazetelere röportaj teklifi', 'todo', 3, 10, array( $ZE ), array( 'Basın' ) ),
);
foreach ( $T as $t ) {
	$tags = array();
	foreach ( $t[6] as $n ) { $tags[] = flxg_create_tag( flxg_get_list( $t[0] )['space_id'], $n ); }
	$id = flxg_create_task( array( 'list_id' => $t[0], 'title' => $t[1], 'status' => $t[2], 'priority' => $t[3], 'due' => $d( $t[4] ), 'start' => $d( $t[4] - 4 ), 'assignees' => $t[5], 'tags' => $tags, 'description' => '<p>' . esc_html( $t[1] ) . ' için yapılacaklar ve notlar.</p>' ), $A );
	if ( 'Servis otobüsü kirala (3 araç)' === $t[1] ) {
		foreach ( array( 'Üç firmadan teklif al', 'Kalkış noktaları: Kadıköy, Mecidiyeköy, Bakırköy', 'Yolcu listesini firmaya gönder' ) as $i => $st ) {
			flxg_create_task( array( 'list_id' => $t[0], 'parent_id' => $id, 'title' => $st, 'status' => $i ? 'todo' : 'done', 'assignees' => array( $EM ) ), $A );
		}
		flxg_add_comment( $id, 'İki firmadan teklif geldi, üçüncüsü yarın. Kişi başı maliyet 85 TL civarı.', array(), $EM );
		flxg_add_comment( $id, 'Tamam, en geç Çarşamba karar verelim.', array(), $ME );
	}
}
$m = flxg_create_task( array( 'list_id' => $l5, 'title' => 'Kasım yönetim kurulu toplantısı', 'kind' => 'meeting', 'due' => $d( 3 ), 'assignees' => array( $A, $ME, $ZE ), 'description' => '<h3>Gündem</h3><ol><li>11 Kasım etkinliği hazırlık durumu</li><li>Yıl sonu bağış kampanyası</li><li>Yeni gönüllü koordinatörü</li></ol>' ), $A );
echo "ok\n";
