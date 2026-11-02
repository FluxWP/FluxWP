<?php require ( getenv( 'WPENV' ) ?: '/tmp/wpenv' ) . '/site/wp-load.php';
$roles=array_keys(wp_roles()->get_names());
$find=function($needle) use($roles){ foreach($roles as $r) if(stripos($r,$needle)!==false) return $r; return 'editor'; };
$u=[['ayse','Ayşe','Yılmaz',$find('personel')],['mehmet','Mehmet','Demir',$find('idareci')],['zeynep','Zeynep','Kaya',$find('yonetici')],['emre','Emre','Şahin',$find('personel')],['elif','Elif','Arslan',$find('personel')]];
foreach($u as [$l,$f,$s,$r]){ if(username_exists($l)) continue; $id=wp_insert_user(['user_login'=>$l,'user_pass'=>wp_generate_password(),'user_email'=>"$l@example.org",'first_name'=>$f,'last_name'=>$s,'display_name'=>"$f $s",'role'=>$r]); echo "$l $r ",is_wp_error($id)?$id->get_error_message():$id,"\n"; }
