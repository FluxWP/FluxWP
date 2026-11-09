<?php
/** Shared coming-soon page and optional whole-site visibility gate. @package fluxwp */
defined( 'ABSPATH' ) || exit;

/** Explicit theme mode works even without WooCommerce; customers cannot bypass it. */
function flx_coming_soon_required() {
	return 'coming-soon' === flx_ayar( 'site_visibility' )
		&& ! current_user_can( 'edit_theme_options' )
		&& ! current_user_can( 'manage_woocommerce' );
}

function flx_coming_soon_gate() {
	if ( is_admin() || wp_doing_ajax() || wp_doing_cron() || ( defined( 'REST_REQUEST' ) && REST_REQUEST ) || ! flx_coming_soon_required() ) {
		return;
	}
	include get_theme_file_path( 'coming-soon.php' );
	exit;
}
add_action( 'template_redirect', 'flx_coming_soon_gate', -100 );

/** Keep WooCommerce's scope, exclusions and private preview handling intact. */
function flx_coming_soon_template( $template ) {
	return get_theme_file_path( 'coming-soon.php' );
}
add_filter( 'coming-soon_template', 'flx_coming_soon_template' );

/** Purge after the new option is stored, including programmatic/preset changes. */
function flx_coming_soon_settings_changed( $old, $new ) {
	$keys = array( 'site_visibility', 'coming_soon_title', 'coming_soon_text', 'coming_soon_email' );
	foreach ( $keys as $key ) {
		if ( ( $old[ $key ] ?? '' ) !== ( $new[ $key ] ?? '' ) ) {
			flx_purge_page_cache();
			return;
		}
	}
}
add_action( 'update_option_flx_ayarlar', 'flx_coming_soon_settings_changed', 10, 2 );
add_action( 'add_option_flx_ayarlar', static function ( $option, $value ) {
	flx_coming_soon_settings_changed( array(), (array) $value );
}, 10, 2 );

/** Settings live with the rest of the theme; no wp-config constants are needed. */
function flx_coming_soon_settings( $o ) {
	?>
	<h2 class="title"><?php esc_html_e( 'Site visibility', 'fluxwp' ); ?></h2>
	<table class="form-table" role="presentation">
		<tr><th scope="row"><label for="flx-visibility"><?php esc_html_e( 'Visibility', 'fluxwp' ); ?></label></th><td>
			<select id="flx-visibility" name="flx_ayarlar[site_visibility]">
				<option value="auto" <?php selected( $o['site_visibility'], 'auto' ); ?>><?php esc_html_e( 'Live / follow WooCommerce', 'fluxwp' ); ?></option>
				<option value="coming-soon" <?php selected( $o['site_visibility'], 'coming-soon' ); ?>><?php esc_html_e( 'Coming soon: entire site', 'fluxwp' ); ?></option>
			</select>
			<p class="description"><?php esc_html_e( 'Entire-site mode hides public pages from visitors, including signed-in customers. Administrators and shop managers can keep working. Automatic mode keeps WooCommerce visibility settings and private preview links unchanged.', 'fluxwp' ); ?></p>
		</td></tr>
		<tr><th scope="row"><label for="flx-soon-title"><?php esc_html_e( 'Coming-soon title', 'fluxwp' ); ?></label></th><td><input id="flx-soon-title" class="large-text" name="flx_ayarlar[coming_soon_title]" value="<?php echo esc_attr( $o['coming_soon_title'] ); ?>" placeholder="<?php esc_attr_e( 'Something good is on its way.', 'fluxwp' ); ?>"></td></tr>
		<tr><th scope="row"><label for="flx-soon-text"><?php esc_html_e( 'Coming-soon message', 'fluxwp' ); ?></label></th><td><textarea id="flx-soon-text" class="large-text" rows="3" name="flx_ayarlar[coming_soon_text]" placeholder="<?php esc_attr_e( "We're putting the finishing touches on our website. Please check back soon.", 'fluxwp' ); ?>"><?php echo esc_textarea( $o['coming_soon_text'] ); ?></textarea><p class="description"><?php esc_html_e( 'Leave the title and message empty to use the translated defaults.', 'fluxwp' ); ?></p></td></tr>
		<tr><th scope="row"><label for="flx-soon-email"><?php esc_html_e( 'Public contact email', 'fluxwp' ); ?></label></th><td><input id="flx-soon-email" type="email" class="regular-text" name="flx_ayarlar[coming_soon_email]" value="<?php echo esc_attr( $o['coming_soon_email'] ); ?>"><p class="description"><?php esc_html_e( 'Optional. Only this address is shown; the administrator email stays private.', 'fluxwp' ); ?></p></td></tr>
	</table>
	<?php
}

function flx_coming_soon_admin_badge( $bar ) {
	if ( 'coming-soon' !== flx_ayar( 'site_visibility' ) || ! current_user_can( 'edit_theme_options' ) ) {
		return;
	}
	$bar->add_node( array( 'id' => 'flx-coming-soon', 'title' => __( 'Site coming soon', 'fluxwp' ), 'href' => admin_url( 'themes.php?page=flx-ayarlar#flx-visibility' ) ) );
}
add_action( 'admin_bar_menu', 'flx_coming_soon_admin_badge', 90 );
