<?php require ( getenv( 'WPENV' ) ?: '/tmp/wpenv' ) . '/site/wp-load.php'; global $wpdb;
$all=json_decode(file_get_contents(__DIR__ . '/forms-tr.json'),true); $fn=get_efbFunction();
foreach($all as $id=>$v){ $v=$fn->sanitize_obj_msg_efb($v); $val=str_replace('"','\\"',json_encode($v,JSON_UNESCAPED_UNICODE));
 echo $id,':',$wpdb->update($wpdb->prefix.'emsfb_form',['form_structer'=>$val],['form_id'=>(int)$id]),"\n"; }
