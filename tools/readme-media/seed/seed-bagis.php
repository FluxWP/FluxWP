<?php require ( getenv( 'WPENV' ) ?: '/tmp/wpenv' ) . '/site/wp-load.php'; global $wpdb; mt_srand(11); wp_set_current_user(1);
foreach(['donations','donors','subscriptions','types','items'] as $t){ $tb=flxb_table($t); if($wpdb->get_var("SHOW TABLES LIKE '$tb'")) $wpdb->query("DELETE FROM $tb"); }
update_option('flxb_categories',['general'=>'Genel','fidan'=>'Fidan','kampanya'=>'Kampanya']);
$fid=FLXB_Types::save(['title'=>'Fidan Bağışı','slug'=>'fidan','category'=>'fidan','description'=>'Her fidan 150 ₺. Bağışınız için kişiye özel sertifika gönderilir.','options'=>[['label'=>'1 fidan','price'=>150,'unit'=>'fidan'],['label'=>'5 fidan','price'=>700,'unit'=>'fidan','units_per'=>5,'badge'=>'popular'],['label'=>'10 fidan','price'=>1400,'unit'=>'fidan','units_per'=>10,'badge'=>'value']],'allow_quantity'=>1,'max_quantity'=>100,'allow_recurring'=>1,'status'=>'active','currency'=>'TRY']);
$hid=FLXB_Types::save(['title'=>'Hatıra Ormanı 2026','slug'=>'hatira-ormani','category'=>'kampanya','description'=>'Sevdikleriniz adına İstanbul\'da bir hatıra ormanı.','goal_amount'=>250000,'start_date'=>date('Y-m-d',strtotime('-50 days')),'end_date'=>date('Y-m-d',strtotime('+40 days')),'options'=>[['label'=>'1 fidan','price'=>150,'unit'=>'fidan'],['label'=>'Aile paketi (7 fidan)','price'=>1000,'unit'=>'fidan','units_per'=>7,'badge'=>'family']],'allow_quantity'=>1,'max_quantity'=>50,'status'=>'active','currency'=>'TRY']);
$gid=FLXB_Types::save(['title'=>'Genel Bağış','slug'=>'genel','category'=>'general','description'=>'Derneğin çalışmalarına destek.','options'=>[],'allow_free'=>1,'min_amount'=>50,'allow_recurring'=>1,'status'=>'active','currency'=>'TRY']);
$types=[$fid,$hid,$gid]; foreach($types as $t) if(is_wp_error($t)) die($t->get_error_message());
$names=['Ayşe Yılmaz','Mehmet Demir','Elif Arslan','Can Şahin','Zeynep Kaya','Burak Çelik','Merve Öztürk','Emre Koç','Selin Aydın','Oğuz Arslan','Fatma Yıldız','Deniz Aslan','Kerem Polat','Ezgi Özdemir','Yusuf Aslan','Hasan Kılıç','Gizem Er','Murat Tekin','Seda Güneş','Ali Vural','Büşra Kurt','Tolga Acar','Esra Bulut','Okan Yalçın','Derya Uçar','Kaan Erdem','Nur Aksoy','Serkan Öz','İrem Taş','Volkan Ateş'];
$donors=[]; foreach($names as $i=>$n){ $donors[]=FLXB_Donors::upsert(['name'=>$n,'phone'=>'0532'.sprintf('%07d',1234500+$i*37),'email'=>strtolower(remove_accents(str_replace(' ','.',$n))).'@example.org']); }
$refs=[0,0,0,0,2,3,5,6]; $now=current_time('timestamp'); $n=0;
for($d=59;$d>=0;$d--){ $day=$now-$d*DAY_IN_SECONDS; $cnt=mt_rand(2,6)+((int)date('N',$day)>=6?3:0)+($d<12?mt_rand(0,4):0);
 for($i=0;$i<$cnt;$i++){ $t=date('Y-m-d',$day).' '.sprintf('%02d:%02d:%02d',mt_rand(8,23),mt_rand(0,59),mt_rand(0,59)); if($t>current_time('mysql')) continue; $utc=get_gmt_from_date($t);
  $r=mt_rand(1,10); $tid=$r<=6?$fid:($r<=8?$hid:$gid);
  if($tid==$fid){ $o=[[ 'k'=>'1-fidan','l'=>'1 fidan','p'=>150,'q'=>mt_rand(1,4)],['k'=>'5-fidan','l'=>'5 fidan','p'=>700,'q'=>1],['k'=>'10-fidan','l'=>'10 fidan','p'=>1400,'q'=>1]][mt_rand(0,2)]; }
  elseif($tid==$hid){ $o=[['k'=>'1-fidan','l'=>'1 fidan','p'=>150,'q'=>mt_rand(1,3)],['k'=>'aile-paketi-7-fidan','l'=>'Aile paketi (7 fidan)','p'=>1000,'q'=>1]][mt_rand(0,1)]; }
  else { $a=[250,500,1000,300,2000][mt_rand(0,4)]; $o=['k'=>'','l'=>'Serbest tutar','p'=>$a,'q'=>1]; }
  $amt=$o['p']*$o['q']; $prov=mt_rand(1,10)<=6?'flxb_iyzico':'flxb_havale'; $st='completed'; if($prov=='flxb_havale' && $d<3 && mt_rand(0,1)) $st='pending'; if(mt_rand(1,80)==1) $st='refunded';
  $rec=($tid!=$hid && mt_rand(1,9)==1)?'recurring':'one_time'; $don=$donors[array_rand($donors)];
  $id=FLXB_Donations::record(['provider'=>$prov,'provider_ref'=>'seed-'.(++$n),'amount'=>$amt,'currency'=>'TRY','type'=>$rec,'status'=>$st,'donor_id'=>$don,'type_id'=>$tid,'option_key'=>$o['k'],'option_label'=>$o['l'],'quantity'=>$o['q'],'unit_price'=>$o['p'],'source'=>'woocommerce','ref_user_id'=>$refs[array_rand($refs)],'created_at'=>$utc,'track'=>'FluxWP'.mt_rand(10,99)]);
  if($rec=='recurring' && mt_rand(0,1)) FLXB_Subscriptions::upsert(['provider'=>'flxb_iyzico','provider_ref'=>'sub-'.$n,'donor_id'=>$don,'amount'=>$amt,'type_id'=>$tid,'status'=>mt_rand(1,6)==1?'canceled':'active','started_at'=>$utc,'next_charge_at'=>gmdate('Y-m-d H:i:s',strtotime($utc.' +1 month'))]);
 }}
echo "donations $n types ".implode(',',$types)."\n";
