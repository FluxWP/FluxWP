<?php require ( getenv( 'WPENV' ) ?: '/tmp/wpenv' ) . '/site/wp-load.php';
global $wpdb; flxst_install();
foreach(['items','folders','people','refs','templates'] as $t) $wpdb->query('DELETE FROM '.flxst_table($t));
$up=wp_upload_dir(); $dir=$up['basedir'].'/flxst-demo'; wp_mkdir_p($dir);
$src=dirname( __DIR__, 3 ) . '/themes/fluxwp/assets/images/';
$now=time();
$wpdb->insert(flxst_table('folders'),['user_id'=>1,'parent_id'=>0,'name'=>'Kampanyalar','shared'=>1,'created'=>$now]); $f1=$wpdb->insert_id;
$wpdb->insert(flxst_table('folders'),['user_id'=>1,'parent_id'=>$f1,'name'=>'11 Kasım','shared'=>1,'created'=>$now]); $f2=$wpdb->insert_id;
$wpdb->insert(flxst_table('folders'),['user_id'=>1,'parent_id'=>0,'name'=>'Sosyal medya','shared'=>0,'created'=>$now]); $f3=$wpdb->insert_id;
$wpdb->insert(flxst_table('folders'),['user_id'=>1,'parent_id'=>0,'name'=>'YouTube küçük resim','shared'=>0,'created'=>$now]);
$P=[
['proje-1','Young woman planting a sapling in a forest clearing at golden hour, gloves, soft backlight, editorial photo','google/nano-banana-2.1','fal',$f2,1],
['haber-fidan','Close-up of hands planting a small sapling in dark soil, shallow depth of field, warm light','openai/gpt-image-1','openai',$f3,0],
['sayfa-bagis','Two hands holding soil with a fresh green sprout, bokeh forest background, symbol of donation','google/nano-banana-2.1','fal',$f2,1],
['etkinlik-yuruyus','Group of hikers with backpacks walking a sunlit forest trail, seen from behind, summer','bytedance/seedream-4','fal',$f3,0],
['calisma-egitim','Grandfather and grandson planting a tree together, golden hour, documentary style','google/nano-banana-2.1','fal',$f2,1],
['sayfa-hakkimizda','Sunrise over forested hills and a winding lake, aerial landscape, cinematic','google/imagen-4','gemini',$f1,0],
['haber-kiyi','Volunteers in white T-shirts with a leaf logo collecting litter on a coastal path, sea view','google/nano-banana-2.1','fal',$f3,0],
['calisma-agaclandirma','Row of young seedlings sprouting from soil, macro, morning sun, hopeful mood','bytedance/seedream-4','fal',$f1,0],
['sayfa-iletisim','Lone oak tree on a green hill at sunset, valley and lake below, memory forest concept','google/imagen-4','gemini',$f2,1],
['etkinlik-dikim','Volunteers with backpacks walking towards a pine forest in the mountains, team spirit','google/nano-banana-2.1','fal',$f3,0],
['calisma-kentsel','Looking up through a green forest canopy, sun star, fresh spring leaves','openai/gpt-image-1','openai',$f1,0],
['proje-3','Aerial top-down view of a dense young pine forest with a winding road','bytedance/seedream-4','fal',$f1,0],
['haber-atolye','Children running through a sunny meadow at the forest edge, joyful, golden hour','google/nano-banana-2.1','fal',0,0],
['proje-2','Turquoise bay surrounded by pine forest, Mediterranean coast, drone shot','google/nano-banana-2.1','fal',0,0],
];
$i=0;
foreach($P as $p){ $i++;
 $f=$src.$p[0].'.webp'; if(!file_exists($f)) continue; copy($f,"$dir/{$p[0]}.webp"); [$w,$h]=getimagesize($f);
 $t=$now-(int)(($i-1)*2.1*DAY_IN_SECONDS)-$i*1700;
 $wpdb->insert(flxst_table('items'),['user_id'=>1,'folder_id'=>$p[4],'kind'=>'image','state'=>'done','job'=>'demo'.$i,'out_index'=>0,'batch'=>'b'.$i,'parent_id'=>0,'title'=>'','prompt'=>$p[1],'model'=>$p[2],'provider'=>$p[3],'params'=>wp_json_encode(['aspect'=>'16:9','resolution'=>'2K','n'=>1]),'url'=>$up['baseurl']."/flxst-demo/{$p[0]}.webp",'path'=>"$dir/{$p[0]}.webp",'thumb'=>'','mime'=>'image/webp','width'=>$w,'height'=>$h,'bytes'=>filesize($f),'cost'=>$p[3]=='openai'?0.04:0.039,'error'=>'','favorite'=>$p[5],'attachment_id'=>0,'created'=>$t,'updated'=>$t]);
}
$wpdb->insert(flxst_table('templates'),['user_id'=>1,'name'=>'Etkinlik afişi 4:5','prompt'=>'Event poster photo for {event}, volunteers planting trees, space for headline at top','params'=>wp_json_encode(['aspect'=>'4:5']),'shared'=>1,'created'=>$now]);
$wpdb->insert(flxst_table('templates'),['user_id'=>1,'name'=>'YouTube küçük resim','prompt'=>'Bold YouTube thumbnail, forest background, expressive face, high contrast','params'=>wp_json_encode(['aspect'=>'16:9']),'shared'=>0,'created'=>$now]);
echo "ok ".$wpdb->get_var('SELECT COUNT(*) FROM '.flxst_table('items'))."\n";
