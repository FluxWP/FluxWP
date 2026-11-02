<?php
// Yalnız yerel test ortamı: SQLite'ta olmayan MySQL fonksiyonu.
add_action('muplugins_loaded', function(){ global $wpdb; $d=$wpdb->dbh??null; if(!$d) return;
 try{ $pdo=method_exists($d,'get_connection')?$d->get_connection()->get_pdo():(method_exists($d,'get_pdo')?$d->get_pdo():null);}catch(Throwable $e){return;}
 if($pdo) $pdo->sqliteCreateFunction('substring_index',function($s,$del,$n){$p=explode($del,(string)$s);$n=(int)$n;return $n>=0?implode($del,array_slice($p,0,$n)):implode($del,array_slice($p,$n));},3);
});
add_action('admin_bar_menu', function($b){ $b->remove_node('sqlite-db-integration'); }, 1000);
