<?php require ( getenv( 'WPENV' ) ?: '/tmp/wpenv' ) . '/site/wp-load.php'; wp_set_current_user(1); mt_srand(7);
$st=(array)get_option('flxs_settings',[]); $st['number_format']='OV-{yil}-{sira}'; update_option('flxs_settings',$st);
// Hazır şablon önizlemeleri (demo arka planlar, mu-plugins/demo-certs.php)
global $wpdb; foreach($wpdb->get_results("SELECT id,config FROM {$wpdb->prefix}flxs_templates") as $t){ $c=json_decode($t->config,true); if(($c['background']['source']??'')==='bundled') flxs_install_bundled_thumb($t->id,$c['background']['file']); }
$ev=flxs_save_event(['name'=>'11 Kasım Milli Ağaçlandırma Günü Fidan Dikimi','event_date'=>'2026-11-11','event_time'=>'10:00','target'=>500,'base_count'=>120,'summary'=>'Belgrad Ormanı, İstanbul']);
$first=['Ayşe','Mehmet','Zeynep','Can','Elif','Emre','Deniz','Burak','Selin','Ahmet','Fatma','Mustafa','Esra','Kerem','Ece','Oğuz','Merve','Hakan','Gizem','Baran','Defne','Yusuf','İrem','Kaan'];
$last=['Yılmaz','Kaya','Demir','Şahin','Çelik','Yıldız','Aydın','Öztürk','Arslan','Doğan','Kılıç','Aslan','Koç','Kurt','Özdemir','Polat'];
$tpls=[1=>['fidan_sayisi'],2=>['fidan_sayisi'],3=>['etkinlik'],11=>['gonderen','fidan_sayisi'],18=>['gonderen','fidan_sayisi'],10=>['gonderen'],6=>['gonderen'],9=>['anilan','gonderen']];
$refs=[0,0,2,3,5,6];
$n=0;
for($i=0;$i<140;$i++){
  $name=$first[mt_rand(0,count($first)-1)].' '.$last[mt_rand(0,count($last)-1)];
  $phone='+90 5'.mt_rand(30,55).' '.mt_rand(100,999).' '.mt_rand(10,99).' '.mt_rand(10,99);
  $p=flxs_upsert_person($name,$phone,mt_rand(0,2)?strtolower(str_replace(['ı','ş','ç','ö','ü','ğ','İ',' '],['i','s','c','o','u','g','i','.'],$name)).'@example.org':'');
  if(is_wp_error($p)) continue; $pid=is_array($p)?$p['id']:$p;
  $tid=array_rand($tpls); if(mt_rand(0,2)) $tid=[1,1,2,18,11][mt_rand(0,4)];
  $v=['ad_soyad'=>$name];
  foreach($tpls[$tid] as $k){ if($k==='fidan_sayisi') $v[$k]=[1,1,1,2,3,5,10][mt_rand(0,6)]; if($k==='gonderen') $v[$k]=$first[mt_rand(0,23)].' '.$last[mt_rand(0,15)]; if($k==='etkinlik') $v[$k]='11 Kasım Fidan Dikimi'; if($k==='anilan') $v[$k]='Hasan '.$last[mt_rand(0,15)]; }
  $d=date('Y-m-d',strtotime('-'.(int)(pow(mt_rand(0,1000)/1000,1.6)*60).' days'));
  $c=flxs_create_certificate($tid,$pid,$v,$refs[mt_rand(0,5)],$d);
  if(!is_wp_error($c)) $n++; else echo $c->get_error_message(),"\n";
}
$T=$wpdb->prefix.'flxs_certificates'; $wpdb->query("UPDATE $T SET number=REPLACE(number,'YT-','OV-')");
// Ön yüz çekimi (cfg: /sertifika/mnmm7zhwr27gdm/) için sabit kod: en yeni Fidan Bağış Sertifikası
$wpdb->query("UPDATE $T SET code=CONCAT('x',id) WHERE code='mnmm7zhwr27gdm'"); $last=$wpdb->get_var("SELECT id FROM $T WHERE template_id=1 ORDER BY id DESC LIMIT 1"); $wpdb->update($T,['code'=>'mnmm7zhwr27gdm','pdf_file'=>'','image_file'=>''],['id'=>$last]);
echo "certs $n\n";
