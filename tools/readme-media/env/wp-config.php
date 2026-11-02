<?php
define('DB_NAME','wp');define('DB_USER','x');define('DB_PASSWORD','x');define('DB_HOST','localhost');
define('DB_CHARSET','utf8mb4');define('DB_COLLATE','');
$table_prefix='wp_';
define('AUTH_KEY','a1');define('SECURE_AUTH_KEY','a2');define('LOGGED_IN_KEY','a3');define('NONCE_KEY','a4');
define('AUTH_SALT','b1');define('SECURE_AUTH_SALT','b2');define('LOGGED_IN_SALT','b3');define('NONCE_SALT','b4');
define('WP_DEBUG',true);define('WP_DEBUG_LOG',true);define('WP_DEBUG_DISPLAY',false);
define('WP_HTTP_BLOCK_EXTERNAL',true);define('DISABLE_WP_CRON',true);define('AUTOMATIC_UPDATER_DISABLED',true);
define('WP_HOME','http://127.0.0.1:8080');define('WP_SITEURL','http://127.0.0.1:8080');
if(!defined('ABSPATH'))define('ABSPATH',__DIR__.'/');
require_once ABSPATH.'wp-settings.php';
