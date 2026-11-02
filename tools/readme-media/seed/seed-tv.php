<?php require ( getenv( 'WPENV' ) ?: '/tmp/wpenv' ) . '/site/wp-load.php';
// Flux TV Kumandasi: demo kopru durumu (env/mu-plugins/demo-tv.php ile)
update_option( 'readme_demo_tv', 1 );
echo "ok\n";
