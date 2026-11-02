<?php require ( getenv( 'WPENV' ) ?: '/tmp/wpenv' ) . '/site/wp-load.php'; require_once ABSPATH.'wp-admin/includes/plugin.php'; wp_set_current_user(1);
foreach(array_slice($argv,1) as $s){ $r=activate_plugin("$s/$s.php"); echo $s,': ',is_wp_error($r)?$r->get_error_message():'ok',"\n"; }
global $menu,$submenu; 
