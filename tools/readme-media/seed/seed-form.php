<?php
require ( getenv( 'WPENV' ) ?: '/tmp/wpenv' ) . '/site/wp-load.php';
global $wpdb; mt_srand(7);
// Formlar yoksa (temiz kurulum) 6 demo formu ekle: yapılar forms-tr.json, adlar aşağıdaki $titles ile aynı
$F=$wpdb->prefix.'emsfb_form';
if(!(int)$wpdb->get_var("SELECT COUNT(*) FROM $F")){
  $types=[1=>'form',2=>'payment',3=>'form',4=>'survey',5=>'survey',6=>'form']; $ttl=[1=>'İletişim Formu',2=>'Fidan Bağışı',3=>'Destek Talebi',4=>'Gönüllü Anketi',5=>'Etkinlik Geri Bildirimi',6=>'Gönüllü Başvurusu'];
  $all=json_decode(file_get_contents(__DIR__.'/forms-tr.json'),true); $fn=get_efbFunction();
  foreach($all as $id=>$v){ $v=$fn->sanitize_obj_msg_efb($v); $val=str_replace('"','\\"',json_encode($v,JSON_UNESCAPED_UNICODE));
    $wpdb->insert($F,['form_id'=>(int)$id,'form_name'=>$ttl[(int)$id],'form_structer'=>$val,'form_email'=>'','form_type'=>$types[(int)$id],'form_created_by'=>'1','form_access_by'=>'1','form_create_date'=>wp_date('Y-m-d H:i:s',strtotime('-70 days'))]); }
  echo "forms ".count($all)."\n";
}
if(!get_page_by_path('fidan-formu')) wp_insert_post(['post_title'=>'Fidan Bağışı','post_name'=>'fidan-formu','post_type'=>'page','post_status'=>'publish','post_content'=>"<!-- wp:shortcode -->\n[EMS_Form_Builder id=2]\n<!-- /wp:shortcode -->"]);
$m=$wpdb->prefix.'emsfb_msg_'; $s=$wpdb->prefix.'emsfb_stts_';
$wpdb->query("DELETE FROM $m"); $wpdb->query("DELETE FROM $s");
$names=['Ayşe Yılmaz','Mehmet Demir','Elif Arslan','Can Şahin','Zeynep Kaya','Burak Çelik','Merve Öztürk','Emre Koç','Selin Aydın','Oğuz Arslan','Fatma Yıldız','Deniz Aslan','Kerem Polat','Ezgi Özdemir','Yusuf Aslan'];
$msgs=['Okulumuz için fidan dikimi etkinliği düzenlemek istiyoruz.','11 Kasım etkinliğine gönüllü olarak katılabilir miyim?','Sertifikamdaki ismi düzeltebilir misiniz?','Şirketimiz adına toplu bağış yapmak istiyoruz.','Hatıra ormanı için bilgi rica ederim.','Dekontu gönderdim, kontrol eder misiniz?'];
$weights=[1=>5,2=>9,3=>2,4=>3,5=>2,6=>2]; $pool=[]; foreach($weights as $f=>$w) for($i=0;$i<$w;$i++) $pool[]=$f;
$titles=[1=>'İletişim Formu',2=>'Fidan Bağışı',3=>'Destek Talebi',4=>'Gönüllü Anketi',5=>'Etkinlik Geri Bildirimi',6=>'Gönüllü Başvurusu'];
$now=current_time('timestamp'); $n=0;
for($d=59;$d>=0;$d--){
  $day=$now-$d*DAY_IN_SECONDS; $wk=(int)date('N',$day);
  $views=mt_rand(25,55)+($wk>=6?20:0)+(int)((60-$d)/2);
  for($v=0;$v<$views;$v++){ $f=$pool[array_rand($pool)]; $t=date('Y-m-d',$day).' '.sprintf('%02d:%02d:%02d',mt_rand(8,22),mt_rand(0,59),mt_rand(0,59)); if($t>current_time('mysql'))continue;
    $wpdb->insert($s,['sid'=>wp_generate_password(20,false),'fid'=>$f,'type_'=>1,'date'=>$t,'status'=>'visit','ip'=>'10.0.0.'.mt_rand(1,250),'os'=>mt_rand(0,2)?'Android':'Windows','browser'=>'Chrome','read_date'=>$t,'uid'=>0,'tc'=>'','active'=>1]); }
  $cnt=(int)round($views*mt_rand(8,16)/100);
  for($i=0;$i<$cnt;$i++){
    $f=$pool[array_rand($pool)]; $nm=$names[array_rand($names)]; $em=strtolower(remove_accents(str_replace(' ','.',$nm))).'@example.org';
    $t=date('Y-m-d',$day).' '.sprintf('%02d:%02d:%02d',mt_rand(8,22),mt_rand(0,59),mt_rand(0,59)); if($t>current_time('mysql'))continue;
    $c=[['id_'=>'n1','name'=>'Ad Soyad','type'=>'text','value'=>$nm,'amount'=>1],['id_'=>'e1','name'=>'E-posta','type'=>'email','value'=>$em,'amount'=>2]];
    $read=$d>2?1:(mt_rand(0,1)?0:1);
    if($f==2){ $q=[1,1,2,3,5,10][mt_rand(0,5)]; $c[]=['id_'=>'q1','name'=>'Fidan sayısı','type'=>'number','value'=>$q,'amount'=>3];
      if(mt_rand(0,4)){ $c[]=['type'=>'payment','total'=>$q*150,'paymentcurrency'=>'TRY','paymentGateway'=>'stripe','amount'=>4]; } else { $read=2; $c[]=['id_'=>'p1','name'=>'Tutar','type'=>'prcfld','value'=>$q*150,'price'=>$q*150,'amount'=>4]; } }
    elseif($f==4){ $c[]=['id_'=>'r1','name'=>'Memnuniyet','type'=>'rating','value'=>mt_rand(3,5),'amount'=>3]; }
    else { $c[]=['id_'=>'m1','name'=>'Mesaj','type'=>'textarea','value'=>$msgs[array_rand($msgs)],'amount'=>3]; }
    $wpdb->insert($m,['form_id'=>$f,'track'=>strtoupper(wp_generate_password(10,false)),'ip'=>'10.0.0.'.mt_rand(1,250),'form_title_x'=>$titles[$f],'content'=>wp_json_encode($c,JSON_UNESCAPED_UNICODE),'date'=>$t,'read_date'=>$t,'read_'=>$read,'read_by'=>'']); $n++;
  }
}
echo "msgs $n views ".$wpdb->get_var("SELECT COUNT(*) FROM $s")."\n";
