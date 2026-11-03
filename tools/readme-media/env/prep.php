<?php require ( getenv( 'WPENV' ) ?: '/tmp/wpenv' ) . '/site/wp-load.php';
update_user_meta(1,'show_welcome_panel',0);
update_user_meta(1,'metaboxhidden_dashboard',['dashboard_site_health','dashboard_right_now','dashboard_activity','dashboard_quick_press','dashboard_primary']);
$o=get_page_by_path('odeme'); if(!get_page_by_path('odeme/order-received')) wp_insert_post(['post_title'=>'Bağış tamamlandı','post_name'=>'order-received','post_parent'=>$o->ID,'post_type'=>'page','post_status'=>'publish']);
echo "ok\n";
// Demo site: gercek bir kurulumdaki gibi kalici baglantilar, Istanbul saat dilimi, TL; WooCommerce kurulum sihirbazi kapali
update_option('permalink_structure','/%postname%/'); flush_rewrite_rules();
update_option('timezone_string','Europe/Istanbul'); update_option('gmt_offset','');
update_option('woocommerce_currency','TRY'); update_option('woocommerce_coming_soon','no'); update_option('woocommerce_store_pages_only','no');
update_option('woocommerce_task_list_hidden','yes'); update_option('woocommerce_task_list_complete','yes'); update_option('woocommerce_onboarding_profile',['skipped'=>true]);
$a=(array)get_option("flx_ayarlar",[]); $a["cookie_enabled"]=0; update_option("flx_ayarlar",$a); // cerez bandi cekimleri kapatmasin
echo "demo ayarlari ok\n";
