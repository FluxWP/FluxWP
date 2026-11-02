<?php require ( getenv( 'WPENV' ) ?: '/tmp/wpenv' ) . '/site/wp-load.php';
flxi_update_settings(['from_email'=>'bilgi@example.org','from_name'=>'Örnek Vakfı','mailer'=>'smtp','smtp_host'=>'smtp.hostinger.com','smtp_user'=>'bilgi@example.org','imap_host'=>'imap.hostinger.com','imap_port'=>993]);
$s=flxi_mailbox_state(); $s['error']=''; $s['last_ok']=time()-120; $s['last_run']=time()-120; flxi_mailbox_state_save($s); echo "ok\n";
