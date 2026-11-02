<?php require ( getenv( 'WPENV' ) ?: '/tmp/wpenv' ) . '/site/wp-load.php'; global $wpdb; mt_srand(5);
$c=flxc_new_connection(['id'=>'cdemo001','source'=>'flxi','provider_id'=>1,'label'=>'Dernek WhatsApp','color'=>'#1f8f5f']);
flxc_update_settings(['connections'=>[$c]]); flxc_set_connection_state('cdemo001','open');
$ct=flxc_table('chats'); $mt=flxc_table('messages'); $wpdb->query("DELETE FROM $ct"); $wpdb->query("DELETE FROM $mt");
$convs=[
 ['Ayşe Yılmaz','905321234500',[['in','Merhaba, annem adına 5 fidan bağışlamak istiyorum. Sertifikada onun adı yazabilir mi?'],['out','Merhaba Ayşe Hanım 🌱 Elbette! Sertifikada hangi ismin yazmasını istersiniz?','bot'],['in','Fatma Yılmaz yazsın lütfen'],['out','Teşekkürler. 5 fidan 700 ₺. Kartla ödeme linki ya da havale bilgisi gönderebilirim, hangisini tercih edersiniz?','bot'],['in','Havale yapacağım']],2,'bagis'],
 ['Mehmet Demir','905321234537',[['in','Dekontu gönderiyorum, 1.400 ₺ yatırdım'],['in','[dekont.pdf]'],['out','Dekontunuz alındı, teşekkürler 🙏 Yetkili onayından sonra sertifikanız WhatsApp\'tan gelecek.','bot']],0,'dekont'],
 ['Elif Arslan','905321234574',[['in','11 Kasım fidan dikimine gönüllü katılmak istiyorum, nasıl kayıt olabilirim?'],['out','Merhaba Elif Hanım! Gönüllü kaydı için example.org/gonullu-ol sayfasını doldurmanız yeterli. Etkinlik Sarıyer\'de, saat 10:00\'da.','agent']],0,'gonullu'],
 ['Can Şahin','905321234611',[['in','Sertifikam gelmedi, 3 gün önce kartla ödedim'],['out','Hemen kontrol ediyorum Can Bey, bir dakika lütfen.','agent']],1,'destek'],
 ['Zeynep Kaya','905321234648',[['in','Okulumuz için 30 fidanlık kampanya yapabilir miyiz?']],1,''],
 ['Burak Çelik','905321234685',[['in','Teşekkürler, sertifika çok güzel olmuş 💚'],['out','Biz teşekkür ederiz Burak Bey 🌳','bot']],0,'bagis'],
 ['Merve Öztürk','905321234722',[['in','Aylık bağış nasıl iptal edilir?'],['out','Merhaba Merve Hanım, e-postanızdaki iptal bağlantısından ya da buradan yazarak iptal edebilirsiniz. İptal etmemi ister misiniz?','bot']],0,'destek'],
 ['Emre Koç','905321234759',[['in','Hatıra ormanı için babam adına bağış yapacağım, fiyat nedir?']],3,'bagis'],
];
$now=time(); $users=[0,2,3];
foreach($convs as $i=>[$name,$phone,$msgs,$unread,$label]){
 $base=$now-($i*2400)-mt_rand(60,900);
 $wpdb->insert($ct,['conn'=>'cdemo001','jid'=>$phone.'@s.whatsapp.net','phone'=>$phone,'name'=>$name,'push_name'=>$name,'labels'=>$label,'status'=>'open','assigned_to'=>$users[$i%3],'unread'=>$unread,'created_at'=>gmdate('Y-m-d H:i:s',$base-86400)]);
 $cid=$wpdb->insert_id; $ts=$base-count($msgs)*90; $last=0;
 foreach($msgs as $m){ $ts+=mt_rand(40,140);
  $wpdb->insert($mt,['chat_id'=>$cid,'conn'=>'cdemo001','wa_id'=>strtoupper(wp_generate_password(16,false)),'direction'=>$m[0],'type'=>'text','body'=>$m[1],'status'=>$m[0]=='out'?'read':'','user_id'=>($m[2]??'')=='agent'?1:0,'source'=>$m[2]??'','ts'=>$ts*1000,'created_at'=>gmdate('Y-m-d H:i:s',$ts)]); $last=$wpdb->insert_id; }
 $wpdb->update($ct,['last_msg_id'=>$last,'last_ts'=>$ts*1000,'last_in_ts'=>$ts*1000],['id'=>$cid]);
}
echo "ok ".count($convs)."\n";
