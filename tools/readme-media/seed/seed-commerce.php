<?php
require ( getenv( 'WPENV' ) ?: '/tmp/wpenv' ) . '/site/wp-load.php';
// Flux Commerce demo mağazası: 13 ürün, 38 müşteri, ~95 günlük sipariş. Gerçek kişi yok (example.com).
if ( ! function_exists( 'flxcm_wc_ready' ) ) { echo "flx-commerce etkin degil\n"; return; }
wp_set_current_user(1);
mt_srand(42);
add_filter('pre_wp_mail', '__return_true');
// Kategoriler + ürünler
$cats = [];
foreach (['Giyim','Ev & Yaşam','Aksesuar','Kırtasiye'] as $c) { $t = term_exists($c,'product_cat') ?: wp_insert_term($c,'product_cat'); $cats[$c] = (int)$t['term_id']; }
$defs = [
 ['Organik Pamuk Tişört','Giyim',349,null,null,'variable'],
 ['Keten Gömlek','Giyim',899,749,14,'simple'],
 ['Yün Bere','Giyim',279,null,2,'simple'],
 ['Seramik Kupa','Ev & Yaşam',189,null,40,'simple'],
 ['Bambu Kesme Tahtası','Ev & Yaşam',429,null,3,'simple'],
 ['Soya Mum (Lavanta)','Ev & Yaşam',259,219,1,'simple'],
 ['Pamuklu Havlu Seti','Ev & Yaşam',649,null,25,'simple'],
 ['Deri Kartlık','Aksesuar',399,null,0,'simple'],
 ['Kanvas Çanta','Aksesuar',299,null,60,'simple'],
 ['Güneş Gözlüğü','Aksesuar',1249,null,8,'simple'],
 ['Nokta Defter A5','Kırtasiye',149,null,90,'simple'],
 ['Dolma Kalem','Kırtasiye',549,null,2,'simple'],
];
$ids = [];
foreach ($defs as $i=>$d) {
  [$name,$cat,$reg,$sale,$stock,$type] = $d;
  $ex = get_page_by_title($name, OBJECT, 'product');
  if ($ex) { $ids[] = $ex->ID; continue; }
  if ($type==='variable') {
    $p = new WC_Product_Variable(); $p->set_name($name); $p->set_category_ids([$cats[$cat]]); $p->set_sku('TS-ORG');
    $att = new WC_Product_Attribute(); $att->set_name('Beden'); $att->set_options(['S','M','L']); $att->set_visible(true); $att->set_variation(true);
    $p->set_attributes([$att]); $p->set_status('publish'); $p->set_description('Yüzde yüz organik pamuk, unisex kesim.'); $pid=$p->save();
    foreach (['S'=>12,'M'=>4,'L'=>0] as $o=>$st) { $v=new WC_Product_Variation(); $v->set_parent_id($pid); $v->set_attributes(['beden'=>$o]); $v->set_regular_price($reg); $v->set_manage_stock(true); $v->set_stock_quantity($st); $v->set_sku('TS-ORG-'.$o); $v->set_weight('0.2'); $v->save(); }
    $ids[]=$pid; continue;
  }
  $p = new WC_Product_Simple(); $p->set_name($name); $p->set_regular_price($reg); if($sale) $p->set_sale_price($sale);
  $p->set_category_ids([$cats[$cat]]); $p->set_status('publish'); $p->set_sku('SKU-'.(100+$i)); $p->set_weight('0.5');
  $p->set_description($name.' — özenle seçilmiş malzemelerle üretildi.');
  if ($stock!==null) { $p->set_manage_stock(true); $p->set_stock_quantity($stock); }
  $ids[] = $p->save();
}
// Dijital ürün
if (!get_page_by_title('Dijital Hediye Kartı', OBJECT, 'product')) { $p=new WC_Product_Simple(); $p->set_name('Dijital Hediye Kartı'); $p->set_regular_price(500); $p->set_virtual(true); $p->set_status('publish'); $p->set_category_ids([$cats['Kırtasiye']]); $p->save(); }
// Banka hesabı, havale yöntemi, ücretsiz kargo
update_option('woocommerce_bacs_accounts', [['account_name'=>'Atölye Doğa Ltd. Şti.','account_number'=>'','bank_name'=>'Ziraat Bankası','sort_code'=>'','iban'=>'TR330006100519786457841326','bic'=>'']]);
update_option('woocommerce_flxcm_bank_settings', ['enabled'=>'yes','title'=>'','description'=>'','holder'=>'','iban'=>'','bank'=>'','ref_format'=>'','ref_prefix'=>'AD']);
update_option('woocommerce_cod_settings', ['enabled'=>'yes','title'=>'Kapıda ödeme']);
if (!WC_Shipping_Zones::get_zones()) {
  $z = new WC_Shipping_Zone(); $z->set_zone_name('Türkiye'); $z->add_location('TR','country'); $z->save();
  $fid=$z->add_shipping_method('flat_rate'); update_option('woocommerce_flat_rate_'.$fid.'_settings',['title'=>'Kargo','cost'=>'79','tax_status'=>'none']);
  $id=$z->add_shipping_method('free_shipping'); update_option('woocommerce_free_shipping_'.$id.'_settings',['title'=>'Ücretsiz kargo','requires'=>'min_amount','min_amount'=>'750','ignore_discounts'=>'no']);
}
update_option('woocommerce_default_country','TR:TR34'); update_option('woocommerce_currency','TRY'); update_option('woocommerce_price_thousand_sep','.'); update_option('woocommerce_price_decimal_sep',','); update_option('woocommerce_currency_pos','right_space');
// Müşteriler + siparişler (bir kez)
if ( get_option( 'flxcm_demo_seeded' ) ) { echo "zaten var\n"; return; }
update_option( 'flxcm_demo_seeded', 1, false );
$first=['Ayşe','Mehmet','Zeynep','Can','Elif','Burak','Selin','Emre','Deniz','Ece','Mert','Derya','Kerem','Naz','Oğuz','Pelin','Tolga','İrem','Kaan','Melis','Umut','Gizem','Barış','Sena','Onur','Ceren','Hakan','Duygu'];
$last=['Yılmaz','Kaya','Demir','Şahin','Çelik','Aydın','Arslan','Doğan','Koç','Kurt','Öztürk','Aksoy','Polat','Erdem'];
$cities=['İstanbul','Ankara','İzmir','Bursa','Antalya','Eskişehir','Muğla','Trabzon'];
$people=[]; for($i=0;$i<38;$i++){ $f=$first[$i%count($first)]; $l=$last[mt_rand(0,count($last)-1)]; $people[]=[$f,$l,strtolower(remove_accents($f.'.'.$l)).$i.'@example.com','05'.mt_rand(30,55).mt_rand(1000000,9999999),$cities[mt_rand(0,count($cities)-1)]]; }
$methods=[['iyzico','Kredi kartı',60],['flxcm_bank','Havale/EFT',28],['cod','Kapıda ödeme',12]];
$now=time(); $n=0;
for($d=95;$d>=0;$d--){
  $k = mt_rand(0, 3 + (int)((95-$d)/30)) ;
  for($j=0;$j<$k;$j++){
    $pp = $people[ min(count($people)-1, (int)floor(pow(mt_rand(0,1000)/1000,1.7)*count($people))) ];
    $o = wc_create_order(['customer_id'=>0]);
    $lines = mt_rand(1,3);
    for($l=0;$l<$lines;$l++){ $pid=$ids[mt_rand(0,count($ids)-1)]; $prod=wc_get_product($pid); if($prod->is_type('variable')){ $ch=$prod->get_children(); $prod=wc_get_product($ch[mt_rand(0,1)]); } $o->add_product($prod, mt_rand(1,2)); }
    $o->set_address(['first_name'=>$pp[0],'last_name'=>$pp[1],'email'=>$pp[2],'phone'=>$pp[3],'city'=>$pp[4],'country'=>'TR','address_1'=>'Örnek Mah. '.mt_rand(1,90).'. Sk.'],'billing');
    $r=mt_rand(1,100); $m = $r<=60?$methods[0]:($r<=88?$methods[1]:$methods[2]);
    $o->set_payment_method($m[0]); $o->set_payment_method_title($m[1]);
    $sub=0; foreach($o->get_items() as $it){$sub+=$it->get_total();}
    if($sub<750){ $s=new WC_Order_Item_Shipping(); $s->set_method_title('Kargo'); $s->set_method_id('flat_rate'); $s->set_total(79); $o->add_item($s);} 
    $o->calculate_totals(false);
    $ts = $now - $d*DAY_IN_SECONDS - mt_rand(0, 12*3600);
    $o->set_date_created($ts);
    $o->save();
    if ($m[0]==='flxcm_bank') FLXCM_Bank::reference($o);
    if ($d<=2 && $m[0]==='flxcm_bank' && mt_rand(0,1)) { $o->set_status('on-hold'); }
    elseif ($d<=1 && mt_rand(0,4)==0) { $o->set_status('pending'); }
    elseif (mt_rand(1,100)<=4) { $o->set_status('cancelled'); }
    else { $o->set_status($d<=4 ? 'processing':'completed'); if($m[0]!=='cod') $o->set_date_paid($ts+600); }
    $o->save();
    if ($o->has_status('completed') && mt_rand(1,100)<=3) { wc_create_refund(['order_id'=>$o->get_id(),'amount'=>round($o->get_total()/2,2),'reason'=>'Kısmi iade']); }
    $n++;
  }
}
// Bekleyen havale: biri dekontlu
$oh = wc_get_orders(['status'=>'on-hold','limit'=>1]);
if ($oh) { $o=$oh[0]; $o->update_meta_data('_flxcm_receipts',[['file'=>'x/none.pdf','name'=>'dekont-ziraat.pdf','type'=>'application/pdf','size'=>48211,'at'=>time()-3600,'by'=>0]]); $o->save(); }
// Demo stokları siparişlerden sonra gerçekçi değerlere döner.
foreach ( $defs as $d ) { if ( null === $d[4] ) continue; $ex = get_page_by_title( $d[0], OBJECT, 'product' ); if ( $ex ) { $pr = wc_get_product( $ex->ID ); $pr->set_stock_quantity( $d[4] ); $pr->save(); } }
foreach ( array( 'TS-ORG-S' => 12, 'TS-ORG-M' => 4, 'TS-ORG-L' => 0 ) as $sku => $q ) { $vid = wc_get_product_id_by_sku( $sku ); if ( $vid ) { $v = wc_get_product( $vid ); $v->set_stock_quantity( $q ); $v->save(); } }
$p = 1; do { $r = FLXCM_Customers::rebuild_step( $p++ ); } while ( ! $r['done'] );
flxcm_save_settings( array( 'feed' => 1, 'feed_brand' => 'Atölye Doğa' ) );
echo "orders: $n\n";
