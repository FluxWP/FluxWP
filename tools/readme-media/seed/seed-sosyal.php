<?php require ( getenv( 'WPENV' ) ?: '/tmp/wpenv' ) . '/site/wp-load.php'; require_once ABSPATH.'wp-admin/includes/image.php'; global $wpdb; mt_srand(11);
foreach(['accounts','posts','targets','metrics','log'] as $t) $wpdb->query('DELETE FROM '.flxsm_table($t));
$img=[]; $src=dirname( __DIR__, 3 ) . '/themes/fluxwp/assets/images/';
foreach(['etkinlik-dikim','calisma-agaclandirma','haber-fidan','calisma-egitim','etkinlik-yuruyus','haber-kiyi','hakkimizda-galeri-2','calisma-temizlik','hero-2'] as $f){
 $ex=get_posts(['post_type'=>'attachment','name'=>'sm-'.$f,'numberposts'=>1]); if($ex){$img[]=$ex[0]->ID;continue;}
 $up=wp_upload_bits('sm-'.$f.'.webp',null,file_get_contents($src.$f.'.webp')); $id=wp_insert_attachment(['post_title'=>'sm-'.$f,'post_name'=>'sm-'.$f,'post_mime_type'=>'image/webp','post_status'=>'inherit'],$up['file']); wp_update_attachment_metadata($id,wp_generate_attachment_metadata($id,$up['file'])); $img[]=$id; }
$logo=get_theme_file_uri('assets/images/avatar.webp'); $now=time();
$accs=[['facebook','Örnek Vakfı','ornekvakfi',8420],['instagram','Örnek Vakfı','@ornekvakfi',12650],['youtube','Örnek Vakfı','@ornekvakfi',2310],['linkedin','Örnek Vakfı','yesil-turkiye',1980],['x','Örnek Vakfı','@ornekvakfi',3540]];
$aid=[];
foreach($accs as $i=>[$p,$n,$u,$f]){ $wpdb->insert(flxsm_table('accounts'),['provider'=>$p,'via'=>'','ext_id'=>'demo-'.$p,'name'=>$n,'username'=>$u,'avatar'=>$logo,'token'=>'','expires'=>0,'status'=>'active','meta'=>'{}','user_id'=>1,'sort'=>$i,'last_error'=>'','created'=>$now-90*86400,'updated'=>$now]); $aid[$p]=$wpdb->insert_id;
 for($d=89;$d>=0;$d--){ $wpdb->insert(flxsm_table('metrics'),['account_id'=>$aid[$p],'day'=>wp_date('Y-m-d',$now-$d*86400),'followers'=>(int)($f-$d*$f*0.0016+mt_rand(-3,3)),'data'=>'{}']); } }
$texts=[['11 Kasım Milli Ağaçlandırma Günü','11 Kasım\'da Sarıyer\'de 2.000 fidanı toprakla buluşturuyoruz 🌱 Siz de bir fidan bağışlayın, adınıza sertifika gönderelim. example.org/bagis-yap'],
['Hatıra Ormanı','Sevdiklerinizin adını yaşatmak için Hatıra Ormanı\'na fidan bağışlayın. Her fidana kişiye özel sertifika 🌳'],
['Okul ziyareti','Bu hafta 4 okulda 600 öğrenciyle orman ve fidan atölyesi yaptık. Teşekkürler öğretmenlerimiz! 👩‍🏫'],
['Gönüllü çağrısı','Hafta sonu dikimleri için gönüllü arıyoruz. Formu doldurun, sizi arayalım 🙌'],
['Kıyı temizliği','Rumelifeneri kıyısında 340 kg atık topladık. Doğa için el ele 💚'],
['Fidan bakımı','Geçen bahar diktiğimiz fidanların bakımını yaptık; tutma oranı %92 🌿'],
['Kurumsal bağış','Şirketiniz adına çalışanlarınız için fidan bağışı yapın, kurumsal sertifika hazırlayalım.'],
['Teşekkürler','Ekim ayında 1.240 bağışçımız sayesinde 6.800 fidan bağışlandı. Hepinize teşekkürler 💚'],
['Etkinlik takvimi','Kasım etkinlik takvimimiz yayında: dikimler, yürüyüşler ve atölyeler.'],
['Yaban hayatı','Kuş yuvalarını ormana yerleştirdik 🐦 Bir sonraki adım: böcek otelleri.'],
['Doğa yürüyüşü','Pazar sabahı Belgrad Ormanı\'nda doğa yürüyüşü. Katılım ücretsiz, kayıt şart.'],
['Fidan bağışı','1 fidan = 150 TL. Bağışınız 24 saat içinde sertifikayla size ulaşır.']];
$nets=array_keys($aid);
foreach($texts as $i=>[$t,$c]){
 $st = $i<2?'scheduled':($i==2?'pending':($i==3?'draft':'published'));
 $when = $st=='scheduled'? $now+($i+1)*86400+36000 : ($st=='published'? $now-($i-3)*2.6*86400-mt_rand(0,30000):0);
 $wpdb->insert(flxsm_table('posts'),['user_id'=>1,'status'=>$st,'title'=>$t,'content'=>$c,'media'=>wp_json_encode([$img[$i%count($img)]]),'tags'=>'','scheduled_at'=>$st=='scheduled'?$when:0,'published_at'=>$st=='published'?$when:0,'repeat_days'=>0,'source'=>'manual','note'=>'','approved_by'=>$st=='published'?1:0,'created'=>$now-20*86400,'updated'=>$now]); $pid=$wpdb->insert_id;
 $sel=$i%3==0?$nets:array_slice($nets,0,mt_rand(2,4));
 foreach($sel as $p){ $tst=$st=='published'?($i==7&&$p=='x'?'failed':'published'):($st=='scheduled'?'scheduled':'draft');
  $m=$tst=='published'?['likes'=>mt_rand(20,480),'comments'=>mt_rand(2,60),'shares'=>mt_rand(1,80),'views'=>mt_rand(800,14000)]:[];
  $wpdb->insert(flxsm_table('targets'),['post_id'=>$pid,'account_id'=>$aid[$p],'status'=>$tst,'content'=>null,'media'=>null,'options'=>'{}','remote_id'=>$tst=='published'?'r'.$pid.$p:'','remote_url'=>$tst=='published'?'https://example.org/'.$p.'/'.$pid:'','state'=>'','attempts'=>0,'next_at'=>$st=='scheduled'?$when:0,'lock_until'=>0,'error'=>$tst=='failed'?'X API: 403 You are not permitted to perform this action.':'','published_at'=>$tst=='published'?$when:0,'metrics'=>wp_json_encode($m),'metrics_at'=>$now,'updated'=>$tst=='failed'?$when:$now]); } }
foreach([4,7,10,14,18,21,25] as $k=>$d){ [$t,$c]=$texts[($k+4)%count($texts)]; $when=strtotime(wp_date('Y-m-',$now).sprintf('%02d',$d).' '.(10+$k%6).':30:00'); 
 $wpdb->insert(flxsm_table('posts'),['user_id'=>1,'status'=>'scheduled','title'=>$t,'content'=>$c,'media'=>wp_json_encode([$img[($k+2)%count($img)]]),'tags'=>'','scheduled_at'=>$when,'published_at'=>0,'repeat_days'=>0,'source'=>'manual','note'=>'','approved_by'=>1,'created'=>$now,'updated'=>$now]); $pid=$wpdb->insert_id;
 foreach(array_slice($nets,0,2+$k%3) as $p) $wpdb->insert(flxsm_table('targets'),['post_id'=>$pid,'account_id'=>$aid[$p],'status'=>'scheduled','options'=>'{}','remote_id'=>'','remote_url'=>'','state'=>'','attempts'=>0,'next_at'=>$when,'lock_until'=>0,'error'=>'','published_at'=>0,'metrics'=>'{}','metrics_at'=>0,'updated'=>$now]); }
echo "ok\n";
