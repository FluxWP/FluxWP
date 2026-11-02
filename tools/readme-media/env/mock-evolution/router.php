<?php header('Content-Type: application/json'); $u=parse_url($_SERVER['REQUEST_URI'],PHP_URL_PATH);
if(strpos($u,'/instance/connectionState')===0){echo json_encode(['instance'=>['instanceName'=>'ornek','state'=>'open']]);exit;}
if(strpos($u,'/instance/fetchInstances')===0){echo json_encode([['name'=>'ornek','connectionStatus'=>'open','ownerJid'=>'905550000000@s.whatsapp.net','profileName'=>'Örnek Vakfı']]);exit;}
echo json_encode(['ok'=>true]);
