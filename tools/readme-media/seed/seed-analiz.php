<?php
require ( getenv( 'WPENV' ) ?: '/tmp/wpenv' ) . '/site/wp-load.php';
require_once ABSPATH.'wp-admin/includes/plugin.php';
update_option('timezone_string','Europe/Istanbul');
update_option('permalink_structure','/%postname%/');
activate_plugin('flx-analytics/flx-analytics.php');
flush_rewrite_rules();
$mk=function($t,$slug,$type='page'){ $p=get_page_by_path($slug,OBJECT,$type); if($p) return $p->ID; return wp_insert_post(['post_title'=>$t,'post_name'=>$slug,'post_type'=>$type,'post_status'=>'publish','post_content'=>'<p>'.$t.'</p>']);};
$home=$mk('Ana Sayfa','ana-sayfa'); update_option('show_on_front','page'); update_option('page_on_front',$home);
$pages=[
 '/'=>$home,
 '/bagis/'=>$mk('Bağış Yap','bagis'),
 '/odeme/'=>$mk('Ödeme','odeme'),
 '/hatira-ormani/'=>$mk('Hatıra Ormanı','hatira-ormani'),
 '/projeler/'=>$mk('Projelerimiz','projeler'),
 '/hakkimizda/'=>$mk('Hakkımızda','hakkimizda'),
 '/iletisim/'=>$mk('İletişim','iletisim'),
 '/odeme/order-received/'=>(get_page_by_path('odeme/order-received')->ID??0),
 '/11-kasim-fidan-dikim-senligi/'=>$mk('11 Kasım Fidan Dikim Şenliği','11-kasim-fidan-dikim-senligi','post'),
 '/belgrad-ormaninda-10-bin-fidan/'=>$mk('Belgrad Ormanı\'nda 10 bin fidan','belgrad-ormaninda-10-bin-fidan','post'),
 '/okullarda-doga-egitimi/'=>$mk('Okullarda doğa eğitimi','okullarda-doga-egitimi','post'),
 '/yangin-sonrasi-agaclandirma/'=>$mk('Yangın sonrası ağaçlandırma','yangin-sonrasi-agaclandirma','post'),
];
flxan_ref_save('ayse','Ayşe Yılmaz (gönüllü)'); flxan_ref_save('okul-kampanyasi','Okul kampanyası'); flxan_ref_save('instagram-bio','Instagram bio');
mt_srand(42);
$uas=[['Mozilla/5.0 (iPhone; CPU iPhone OS 18_1 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/18.1 Mobile/15E148 Safari/604.1',390],
['Mozilla/5.0 (Linux; Android 15; SM-S921B) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/130.0.0.0 Mobile Safari/537.36',412],
['Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/130.0.0.0 Safari/537.36',1536],
['Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/18.1 Safari/605.1.15',1440],
['Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:132.0) Gecko/20100101 Firefox/132.0',1920],
['Mozilla/5.0 (iPad; CPU OS 18_1 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/18.1 Mobile/15E148 Safari/604.1',820]];
$uaw=[30,30,20,10,5,5];
$refs=[[ '',30],['https://www.google.com/',28],['https://www.instagram.com/',12],['https://l.facebook.com/',6],['https://chatgpt.com/',4],['https://t.co/',3],['https://www.bing.com/',3],['https://example.org/',5],['https://mail.google.com/',3],['https://yandex.com.tr/',2],['https://www.linkedin.com/',2]];
$tzs=[['Europe/Istanbul',82],['Europe/Berlin',6],['Europe/Amsterdam',3],['Europe/London',3],['America/New_York',2],['Asia/Baku',2],['Europe/Paris',2]];
$pick=function($a){$s=array_sum(array_column($a,1));$r=mt_rand(1,$s);foreach($a as $x){$r-=$x[1];if($r<=0)return $x[0];}return $a[0][0];};
$entries=[['/',30],['/11-kasim-fidan-dikim-senligi/',18],['/hatira-ormani/',14],['/bagis/',12],['/belgrad-ormaninda-10-bin-fidan/',10],['/projeler/',6],['/okullarda-doga-egitimi/',5],['/yangin-sonrasi-agaclandirma/',5]];
$next=['/'=>[['/bagis/',30],['/projeler/',15],['/hatira-ormani/',20],['/11-kasim-fidan-dikim-senligi/',15],['/hakkimizda/',10],['/iletisim/',5],['',25]],
 '/projeler/'=>[['/belgrad-ormaninda-10-bin-fidan/',30],['/bagis/',25],['',30]],
 '/hatira-ormani/'=>[['/bagis/',45],['/iletisim/',10],['',35]],
 '/bagis/'=>[['/odeme/',55],['',45]],
 '/odeme/'=>[['/odeme/order-received/',38],['',62]],
 '*'=>[['/bagis/',22],['/',18],['/projeler/',8],['/hatira-ormani/',10],['',55]]];
$rows=[]; $now=time(); $tz=wp_timezone();
$types=['Fidan bağışı','Hatıra ormanı','Okul ormanı','Genel bağış'];
for($day=59;$day>=0;$day--){
  $base=(new DateTime("today -$day days",$tz))->getTimestamp();
  $dow=(int)date('N',$base); $n=(int)(40+ max(0,59-$day)*0.8 + ($dow>=6?-8:6) + mt_rand(-6,8)); if($day<=3) $n+=25;
  if($day==0) $n=(int)($n*min(1,(($now-$base)/86400)+0.35));
  for($v=0;$v<$n;$v++){
    $ui=$pick(array_map(fn($u,$w,$i)=>[$i,$w],$uas,$uaw,array_keys($uas)));
    [$ua,$w]=$uas[$ui];
    $h=substr(md5("v$day-$v"),0,16);
    $t=$base+mt_rand(7*3600,23*3600); if($day==0){$t=min($t,$now-mt_rand(60,4000)); if($v<9) $t=$now-mt_rand(10,1500);}
    $d=wp_date('Y-m-d',$t);
    $ref=$pick($refs); $z=$pick($tzs); $lang=$z==='Europe/Istanbul'?'tr':(mt_rand(0,1)?'en':'de');
    $rc=''; $r=mt_rand(1,100); if($r<=5)$rc='ayse'; elseif($r<=8)$rc='okul-kampanyasi'; elseif($r<=12){$rc='instagram-bio';$ref='https://www.instagram.com/';}
    $g=''; if(mt_rand(1,100)<=6) $g=mt_rand(0,2)?'administrator':'editor';
    $utm=[]; if(mt_rand(1,100)<=7) $utm=['us'=>'newsletter','um'=>'email','uc'=>'kasim-bulteni']; elseif(mt_rand(1,100)<=5) $utm=['us'=>'instagram','um'=>'social','uc'=>'11-kasim'];
    $path=$pick($entries); $seq=0;
    while($path!=='' && $seq<7){
      $pid=$pages[$path]??0;
      $rec=['k'=>'p','t'=>$t,'d'=>$d,'h'=>$h,'p'=>$path,'i'=>$pid,'r'=>$seq?'':$ref,'u'=>$ua,'w'=>$w,'l'=>$lang,'z'=>$z]+$utm;
      if($g)$rec['g']=$g; if($rc)$rec['rc']=$rc;
      $rows[]=$rec;
      if($path==='/odeme/'){ if(mt_rand(1,100)<=75){$rows[]=['k'=>'e','t'=>$t+20,'d'=>$d,'h'=>$h,'p'=>'','n'=>'checkout','v'=>'form']+($rc?['rc'=>$rc]:[]); if(mt_rand(1,100)<=70) $rows[]=['k'=>'e','t'=>$t+90,'d'=>$d,'h'=>$h,'p'=>'','n'=>'checkout','v'=>'submit']+($rc?['rc'=>$rc]:[]);} }
      if($path==='/odeme/order-received/'){ $amt=[150,300,450,750,1500,3000][mt_rand(0,5)]; $rows[]=['k'=>'e','t'=>$t+5,'d'=>$d,'h'=>$h,'p'=>'','n'=>'donation','v'=>$types[mt_rand(0,3)],'a'=>$amt]+($rc?['rc'=>$rc]:[]); break; }
      if($path==='/iletisim/' && mt_rand(0,1)) $rows[]=['k'=>'e','t'=>$t+30,'d'=>$d,'h'=>$h,'p'=>'','n'=>'whatsapp','v'=>'+905550000000'];
      if(mt_rand(1,100)<=4) $rows[]=['k'=>'e','t'=>$t+40,'d'=>$d,'h'=>$h,'p'=>'','n'=>'search','v'=>['fidan','sertifika','hatıra ormanı','bağış'][mt_rand(0,3)]];
      if(mt_rand(1,100)<=3) $rows[]=['k'=>'e','t'=>$t+50,'d'=>$d,'h'=>$h,'p'=>'','n'=>'download','v'=>'/wp-content/uploads/faaliyet-raporu-2025.pdf'];
      $nx=$next[$path]??$next['*']; $path=$pick($nx); $t+=mt_rand(25,240); $seq++;
    }
  }
}
if(mt_rand(0,1)||true){ $rows[]=['k'=>'p','t'=>$now-3000,'d'=>wp_date('Y-m-d'),'h'=>'404x','p'=>'/eski-kampanya/','i'=>0,'r'=>'','u'=>$uas[2][0],'w'=>1536,'l'=>'tr','z'=>'Europe/Istanbul','x'=>1]; }
foreach ($rows as &$row) { $row['t'] = min( $row['t'], $now - 1 ); } unset($row);
usort($rows,fn($a,$b)=>$a['t']<=>$b['t']);
FLXAN_Aggregator::reset(); $byd=[]; foreach($rows as $r) $byd[$r["d"]][]=$r; foreach($byd as $c) FLXAN_Aggregator::process($c);
echo count($rows)," rows\n";
