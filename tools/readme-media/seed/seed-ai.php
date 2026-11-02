<?php require ( getenv( 'WPENV' ) ?: '/tmp/wpenv' ) . '/site/wp-load.php'; global $wpdb; mt_srand(21); $t=flxai_table('log'); $wpdb->query("DELETE FROM $t"); $wpdb->query("DELETE FROM ".flxai_table('log_body'));
$src=[['flx-chat','assistant',14,'google/gemini-2.5-flash'],['flx-chat','suggest',5,'google/gemini-2.5-flash'],['flx-ai','chat',4,'anthropic/claude-haiku-4.5'],['flx-mail','mailbox',3,'google/gemini-2.5-flash'],['flx-social','write',2,'openai/gpt-4.1-mini'],['flx-lang','translate',3,'google/gemini-2.5-flash'],['flx-studio','prompt',1,'openai/gpt-4.1-mini']];
$price=['google/gemini-2.5-flash'=>[0.3,2.5],'anthropic/claude-haiku-4.5'=>[1,5],'openai/gpt-4.1-mini'=>[0.4,1.6]];
$tools=['site_info','list_content','get_content','seo_audit','flxi_email_log','flxb_donations','flxsm_posts','flxan_summary'];
$now=time(); $rows=[];
for($d=29;$d>=0;$d--) foreach($src as [$s,$p,$n,$m]) for($i=0,$k=max(0,$n+mt_rand(-2,3));$i<$k;$i++){
 $ts=$now-$d*86400-mt_rand(0,80000); if($ts>$now) continue; $in=mt_rand(1500,9000); $out=mt_rand(80,700); $cr=$m=='anthropic/claude-haiku-4.5'?(int)($in*0.6):(mt_rand(0,2)?(int)($in*0.4):0);
 $c=(($in-$cr)*$price[$m][0]+$cr*$price[$m][0]*0.1+$out*$price[$m][1])/1e6; $full=($in*$price[$m][0]+$out*$price[$m][1])/1e6; $ok=mt_rand(1,60)>1;
 $rows[]=['ts'=>$ts,'kind'=>'model','source'=>$s,'purpose'=>$p,'user_id'=>1,'chat_id'=>0,'provider'=>strpos($m,'anthropic')===0?'openrouter':'openrouter','model'=>$m,'tool'=>'','in_tok'=>$in,'out_tok'=>$out,'cache_read'=>$cr,'cache_write'=>0,'cost'=>$ok?$c:0,'full_cost'=>$ok?$full:0,'est'=>0,'ms'=>mt_rand(700,4200),'ok'=>$ok?1:0,'error'=>$ok?'':'429 Rate limit, retried'];
 if($s=='flx-ai'&&mt_rand(0,1)) $rows[]=['ts'=>$ts+2,'kind'=>'tool','source'=>$s,'purpose'=>'chat','user_id'=>1,'chat_id'=>0,'provider'=>'','model'=>'','tool'=>$tools[array_rand($tools)],'in_tok'=>0,'out_tok'=>0,'cache_read'=>0,'cache_write'=>0,'cost'=>0,'full_cost'=>0,'est'=>0,'ms'=>mt_rand(40,900),'ok'=>1,'error'=>''];
}
usort($rows,fn($a,$b)=>$a['ts']<=>$b['ts']); foreach($rows as $r) $wpdb->insert($t,$r);
flxai_update_settings(['limit_day'=>0.5,'limit_week'=>3,'limit_month'=>12]);
echo count($rows)," sum ",$wpdb->get_var("SELECT SUM(cost) FROM $t"),"\n";
