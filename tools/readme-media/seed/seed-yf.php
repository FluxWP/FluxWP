<?php require ( getenv( 'WPENV' ) ?: '/tmp/wpenv' ) . '/site/wp-load.php';
// Tema flx-menu-theme: ayri menu sitesi gibi kurulur. cfg "snapshot": true oldugu icin run.sh veritabanini sonda geri yukler.
// Tema dizini sitede yoksa: ln -s <repo>/themes/flx-menu-theme $WPENV/site/wp-content/themes/flx-menu-theme
wp_set_current_user( 1 );
if ( 'flx-menu-theme' !== get_stylesheet() ) {
	update_option( 'template', 'flx-menu-theme' ); update_option( 'stylesheet', 'flx-menu-theme' ); update_option( 'theme_switched', 'fluxwp' ); // switch_theme() bu istekte eski temayi yeniden yukleyip coker
	passthru( 'php ' . escapeshellarg( __FILE__ ) ); exit;
}
foreach ( (array) yf_kurulum_run( array_keys( yf_kurulum_steps() ) ) as $l ) { echo '  ' . wp_strip_all_tags( is_array( $l ) ? (string) $l[0] : (string) $l ) . "\n"; }
echo "ok flx-menu-theme\n";
