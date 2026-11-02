<?php require ( getenv( 'WPENV' ) ?: '/tmp/wpenv' ) . '/site/wp-load.php';
wp_delete_post(1,true);
$o=get_option('flxseo',array());
$o=array_merge($o,array('phone'=>'+90 555 000 00 00','email'=>'bilgi@example.org','postal'=>'34467','same_as'=>"https://www.instagram.com/ornekvakfi\nhttps://www.youtube.com/@ornekvakfi\nhttps://www.facebook.com/ornekvakfi\nhttps://www.linkedin.com/company/ornekvakfi",'twitter'=>'@ornekvakfi','mcp_enabled'=>1));
update_option('flxseo',$o);
update_option('flxseo_mcp_keys',array(
 'k1'=>array('id'=>'k1','name'=>'Claude Desktop – Ahmet','hint'=>'a91f','user'=>1,'tools'=>array(),'created'=>time()-86400*12,'last_used'=>time()-3600*2,'hash'=>wp_hash_password('x1')),
 'k2'=>array('id'=>'k2','name'=>'Cursor – içerik','hint'=>'4c07','user'=>1,'tools'=>array('site_info','list_content','get_content','update_seo'),'created'=>time()-86400*5,'last_used'=>time()-86400,'hash'=>wp_hash_password('x2')),
),false);
echo "ok\n";
