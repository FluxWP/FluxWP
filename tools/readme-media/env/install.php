<?php
define('WP_INSTALLING',true);
require ( getenv( 'WPENV' ) ?: '/tmp/wpenv' ) . '/site/wp-load.php';
require_once ABSPATH.'wp-admin/includes/upgrade.php';
$r=wp_install('Örnek Vakfı','admin','admin@example.com',true,'','admin12345');
echo "ok ",$r['user_id'],"\n";
