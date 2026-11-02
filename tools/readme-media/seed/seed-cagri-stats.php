<?php require ( getenv( 'WPENV' ) ?: '/tmp/wpenv' ) . '/site/wp-load.php'; mt_srand(5); $all=[];
for($i=29;$i>=0;$i--){ $day=wp_date('Y-m-d',time()-$i*86400); $t=mt_rand(1,4); $calls=$t+mt_rand(0,2); $in=$calls*mt_rand(2500,3500); $out=$calls*mt_rand(150,260);
 $cost=round($in*0.3/1e6+$out*2.5/1e6,4);
 $all[$day]=['turns'=>$t,'calls'=>$calls,'in'=>$in,'out'=>$out,'cost'=>$cost,'price'=>mt_rand(0,1),'donations'=>mt_rand(0,1),'handoffs'=>mt_rand(0,1),'rc'=>mt_rand(0,1),'rc_match'=>mt_rand(0,1),'rc_bad'=>0,'summaries'=>mt_rand(0,1),'reminders'=>mt_rand(0,1),'cert_resent'=>0,'pl'=>[1,2,6],'m'=>['google/gemini-2.5-flash'=>['n'=>$calls,'in'=>$in,'out'=>$out,'cost'=>$cost]]]; }
update_option('flxc_bot_stats',$all,false); echo "ok\n";
