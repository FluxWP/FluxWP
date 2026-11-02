<?php
// Yalnız yerel README ekranları için yapay yük: yavaş sayfa, dış istek hatası, PHP uyarısı.
add_action('template_redirect', function(){
  $u=$_SERVER['REQUEST_URI']??'';
  if(strpos($u,'/projeler/')===0){ usleep(mt_rand(500,1100)*1000); wp_remote_get('https://api.example.org/v1/projects?limit=20',['timeout'=>3]); }
  if(strpos($u,'/hatira-ormani/')===0){ global $wpdb; for($i=0;$i<25;$i++) $wpdb->get_results("SELECT p.ID FROM {$wpdb->posts} p LEFT JOIN {$wpdb->postmeta} m ON m.post_id=p.ID WHERE p.post_status='publish' ORDER BY p.post_date DESC LIMIT 50"); usleep(250000); }
  if(strpos($u,'/hakkimizda/')===0 && mt_rand(0,1)){ $a=[]; trigger_error('Undefined array key "baslik"', E_USER_WARNING); }
  if(strpos($u,'/bagis/')===0){ usleep(mt_rand(150,700)*1000); }
});
