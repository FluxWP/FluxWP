<?php require ( getenv( 'WPENV' ) ?: '/tmp/wpenv' ) . '/site/wp-load.php';
update_user_meta(1,'show_welcome_panel',0);
update_user_meta(1,'metaboxhidden_dashboard',['dashboard_site_health','dashboard_right_now','dashboard_activity','dashboard_quick_press','dashboard_primary']);
$o=get_page_by_path('odeme'); if(!get_page_by_path('odeme/order-received')) wp_insert_post(['post_title'=>'Bağış tamamlandı','post_name'=>'order-received','post_parent'=>$o->ID,'post_type'=>'page','post_status'=>'publish']);
echo "ok\n";
