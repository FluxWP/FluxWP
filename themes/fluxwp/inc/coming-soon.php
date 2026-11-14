<?php
/**
 * Coming-soon modes: live, entire site, store pages only.
 *
 * With WooCommerce the theme setting and WooCommerce › Settings › Site visibility are one setting:
 * changing either updates the other, WooCommerce decides which pages are store pages and keeps its
 * private preview link. Without WooCommerce only the entire-site mode exists and the theme gates it.
 *
 * @package fluxwp
 */
defined( 'ABSPATH' ) || exit;

/** WooCommerce site visibility: live | site | store, or '' when WooCommerce is not active. */
function flx_wc_visibility() {
	if ( ! class_exists( 'WooCommerce' ) ) {
		return '';
	}
	if ( 'yes' !== get_option( 'woocommerce_coming_soon' ) ) {
		return 'live';
	}
	return 'yes' === get_option( 'woocommerce_store_pages_only' ) ? 'store' : 'site';
}

/** WooCommerce's own coming-soon gate runs only with the Launch Your Store feature. */
function flx_wc_gate_available() {
	return class_exists( 'WooCommerce' )
		&& class_exists( '\Automattic\WooCommerce\Admin\Features\Features' )
		&& \Automattic\WooCommerce\Admin\Features\Features::is_enabled( 'launch-your-store' );
}

/** Effective mode shown in the panel: live | coming-soon | store. */
function flx_visibility_mode() {
	if ( 'coming-soon' === flx_ayar( 'site_visibility' ) ) {
		return 'coming-soon';
	}
	$wc = flx_wc_visibility();
	if ( 'site' === $wc ) {
		return 'coming-soon';
	}
	return 'store' === $wc ? 'store' : 'live';
}

/** Theme option value → WooCommerce state. */
function flx_visibility_to_wc( $value ) {
	if ( 'coming-soon' === $value ) {
		return 'site';
	}
	return 'store' === $value ? 'store' : 'live';
}

/** Re-entrancy guard shared by both sync directions. */
function flx_visibility_syncing( $set = null ) {
	static $on = false;
	if ( null !== $set ) {
		$on = (bool) $set;
	}
	return $on;
}

/** Theme → WooCommerce: write only what differs, so WooCommerce's own hooks (cache purge, tracking) run once. */
function flx_visibility_push_to_wc( $value ) {
	$current = flx_wc_visibility();
	$want    = flx_visibility_to_wc( $value );
	if ( '' === $current || $current === $want || flx_visibility_syncing() ) {
		return;
	}
	flx_visibility_syncing( true );
	if ( 'live' === $want ) {
		update_option( 'woocommerce_coming_soon', 'no' );
	} else {
		update_option( 'woocommerce_store_pages_only', 'store' === $want ? 'yes' : 'no' );
		update_option( 'woocommerce_coming_soon', 'yes' );
	}
	flx_visibility_syncing( false );
}

/** WooCommerce → theme: Launch Your Store, WooCommerce settings or REST changes show up in Theme Settings. */
function flx_visibility_pull_from_wc() {
	if ( flx_visibility_syncing() || ! class_exists( 'WooCommerce' ) ) {
		return;
	}
	$map  = array(
		'live'  => 'auto',
		'site'  => 'coming-soon',
		'store' => 'store',
	);
	$want = $map[ flx_wc_visibility() ] ?? 'auto';
	$opts = (array) get_option( 'flx_ayarlar', array() );
	if ( ( $opts['site_visibility'] ?? 'auto' ) === $want ) {
		return;
	}
	$opts['site_visibility'] = $want;
	flx_visibility_syncing( true );
	update_option( 'flx_ayarlar', $opts );
	flx_visibility_syncing( false );
	flx_purge_page_cache();
}
foreach ( array( 'woocommerce_coming_soon', 'woocommerce_store_pages_only' ) as $flx_wc_opt ) {
	add_action( 'update_option_' . $flx_wc_opt, 'flx_visibility_pull_from_wc', 20 );
	add_action( 'add_option_' . $flx_wc_opt, 'flx_visibility_pull_from_wc', 20 );
}
unset( $flx_wc_opt );

/** Is a Theme Settings form save in progress? Set by flx_ayar_sanitize(). */
function flx_visibility_form_save( $set = null ) {
	static $on = false;
	if ( null !== $set ) {
		$on = (bool) $set;
	}
	return $on;
}

/** Explicit entire-site mode without WooCommerce's gate (no WooCommerce, or Launch Your Store disabled). */
function flx_coming_soon_required() {
	return 'coming-soon' === flx_visibility_mode()
		&& ! flx_wc_gate_available()
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

/** Editors who manage the theme can preview the site; WooCommerce only lets shop managers through. */
add_filter(
	'woocommerce_coming_soon_exclude',
	static function ( $exclude ) {
		return $exclude || current_user_can( 'edit_theme_options' );
	}
);

/** WooCommerce picks the pages; the theme supplies the design. Store mode keeps the site header and footer. */
function flx_coming_soon_template( $template ) {
	return get_theme_file_path( 'store' === flx_wc_visibility() ? 'coming-soon-store.php' : 'coming-soon.php' );
}
add_filter( 'coming-soon_template', 'flx_coming_soon_template' );

/** Store-mode notice texts; site type picks donation or shop wording. */
function flx_coming_soon_store_texts() {
	$title    = trim( (string) flx_ayar( 'coming_soon_store_title' ) );
	$text     = trim( (string) flx_ayar( 'coming_soon_store_text' ) );
	$donation = 'donation' === flx_ayar( 'site_mod' );
	return array(
		'title' => $title ? flx_t( $title ) : ( $donation ? __( 'Online donations open soon.', 'fluxwp' ) : __( 'Our online store opens soon.', 'fluxwp' ) ),
		'text'  => $text ? flx_t( $text ) : ( $donation ? __( "Donations can't be completed online just yet. You can keep exploring the rest of the site in the meantime.", 'fluxwp' ) : __( "Orders can't be placed online just yet. You can keep exploring the rest of the site in the meantime.", 'fluxwp' ) ),
	);
}

/** Purge after the new option is stored and keep WooCommerce in step, including programmatic/preset changes. */
function flx_coming_soon_settings_changed( $old, $new ) {
	$new = (array) $new;
	$old = (array) $old;
	if ( ! flx_visibility_syncing() && ( flx_visibility_form_save() || ( $old['site_visibility'] ?? 'auto' ) !== ( $new['site_visibility'] ?? 'auto' ) ) ) {
		flx_visibility_push_to_wc( $new['site_visibility'] ?? 'auto' );
	}
	$keys = array( 'site_visibility', 'coming_soon_title', 'coming_soon_text', 'coming_soon_store_title', 'coming_soon_store_text', 'coming_soon_email' );
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
	$mode   = flx_visibility_mode();
	$has_wc = class_exists( 'WooCommerce' );
	$soon   = 'live' === $mode ? '' : ' hidden';
	?>
	<h2 class="title"><?php esc_html_e( 'Site visibility', 'fluxwp' ); ?></h2>
	<table class="form-table flx-visibility" role="presentation">
		<tr><th scope="row"><?php esc_html_e( 'Visibility', 'fluxwp' ); ?></th><td>
			<fieldset id="flx-visibility" class="flx-visibility__choices">
				<legend class="screen-reader-text"><?php esc_html_e( 'Visibility', 'fluxwp' ); ?></legend>
				<label><input type="radio" name="flx_ayarlar[site_visibility]" value="auto" <?php checked( $mode, 'live' ); ?>> <strong><?php esc_html_e( 'Live', 'fluxwp' ); ?></strong><span class="description"><?php esc_html_e( 'Everyone can see the whole site.', 'fluxwp' ); ?></span></label>
				<label><input type="radio" name="flx_ayarlar[site_visibility]" value="coming-soon" <?php checked( $mode, 'coming-soon' ); ?>> <strong><?php esc_html_e( 'Coming soon: entire site', 'fluxwp' ); ?></strong><span class="description"><?php esc_html_e( 'Visitors, including signed-in customers, only see the coming-soon page.', 'fluxwp' ); ?></span></label>
				<label<?php echo $has_wc ? '' : ' class="is-disabled"'; ?>><input type="radio" name="flx_ayarlar[site_visibility]" value="store" <?php checked( $mode, 'store' ); ?><?php disabled( ! $has_wc ); ?>> <strong><?php esc_html_e( 'Coming soon: store pages only', 'fluxwp' ); ?></strong><span class="description"><?php echo $has_wc ? esc_html__( 'The site stays open. Shop, product, cart and checkout pages show a coming-soon notice inside the normal header and footer.', 'fluxwp' ) : esc_html__( 'Requires WooCommerce.', 'fluxwp' ); ?></span></label>
			</fieldset>
			<p class="description"><?php echo $has_wc ? esc_html__( 'Administrators, shop managers and editors keep seeing the site. This is the same setting as WooCommerce › Settings › Site visibility; changing either one updates the other, and WooCommerce private preview links keep working.', 'fluxwp' ) : esc_html__( 'Administrators and editors keep seeing the site.', 'fluxwp' ); ?></p>
		</td></tr>
		<tr class="flx-visibility__site<?php echo 'coming-soon' === $mode ? '' : ' hidden'; ?>"><th scope="row"><label for="flx-soon-title"><?php esc_html_e( 'Coming-soon title', 'fluxwp' ); ?></label></th><td><input id="flx-soon-title" class="large-text" name="flx_ayarlar[coming_soon_title]" value="<?php echo esc_attr( $o['coming_soon_title'] ); ?>" placeholder="<?php esc_attr_e( 'Something good is on its way.', 'fluxwp' ); ?>"></td></tr>
		<tr class="flx-visibility__site<?php echo 'coming-soon' === $mode ? '' : ' hidden'; ?>"><th scope="row"><label for="flx-soon-text"><?php esc_html_e( 'Coming-soon message', 'fluxwp' ); ?></label></th><td><textarea id="flx-soon-text" class="large-text" rows="3" name="flx_ayarlar[coming_soon_text]" placeholder="<?php esc_attr_e( "We're putting the finishing touches on our website. Please check back soon.", 'fluxwp' ); ?>"><?php echo esc_textarea( $o['coming_soon_text'] ); ?></textarea><p class="description"><?php esc_html_e( 'Leave the title and message empty to use the translated defaults.', 'fluxwp' ); ?></p></td></tr>
		<?php $store = flx_coming_soon_store_texts(); ?>
		<tr class="flx-visibility__store<?php echo 'store' === $mode ? '' : ' hidden'; ?>"><th scope="row"><label for="flx-soon-store-title"><?php esc_html_e( 'Store notice title', 'fluxwp' ); ?></label></th><td><input id="flx-soon-store-title" class="large-text" name="flx_ayarlar[coming_soon_store_title]" value="<?php echo esc_attr( $o['coming_soon_store_title'] ); ?>" placeholder="<?php echo esc_attr( $o['coming_soon_store_title'] ? '' : $store['title'] ); ?>"></td></tr>
		<tr class="flx-visibility__store<?php echo 'store' === $mode ? '' : ' hidden'; ?>"><th scope="row"><label for="flx-soon-store-text"><?php esc_html_e( 'Store notice message', 'fluxwp' ); ?></label></th><td><textarea id="flx-soon-store-text" class="large-text" rows="3" name="flx_ayarlar[coming_soon_store_text]" placeholder="<?php echo esc_attr( $o['coming_soon_store_text'] ? '' : $store['text'] ); ?>"><?php echo esc_textarea( $o['coming_soon_store_text'] ); ?></textarea><p class="description"><?php esc_html_e( 'Leave the title and message empty to use the translated defaults. The wording follows the site type (shop or donations).', 'fluxwp' ); ?></p></td></tr>
		<tr class="flx-visibility__email<?php echo esc_attr( $soon ); ?>"><th scope="row"><label for="flx-soon-email"><?php esc_html_e( 'Public contact email', 'fluxwp' ); ?></label></th><td><input id="flx-soon-email" type="email" class="regular-text" name="flx_ayarlar[coming_soon_email]" value="<?php echo esc_attr( $o['coming_soon_email'] ); ?>"><p class="description"><?php esc_html_e( 'Optional. Only this address is shown; the administrator email stays private.', 'fluxwp' ); ?></p></td></tr>
	</table>
	<style>.form-table .flx-visibility__choices label{display:block;margin:0 0 12px}.form-table .flx-visibility__choices .description{display:block;margin:2px 0 0;padding-inline-start:24px;overflow-wrap:anywhere}.flx-visibility__choices .is-disabled{opacity:.6}</style>
	<script>
	( function () {
		var box = document.getElementById( 'flx-visibility' );
		if ( ! box ) { return; }
		function sync() {
			var on = box.querySelector( 'input:checked' );
			var v = on ? on.value : 'auto';
			document.querySelectorAll( '.flx-visibility__site' ).forEach( function ( r ) { r.classList.toggle( 'hidden', 'coming-soon' !== v ); } );
			document.querySelectorAll( '.flx-visibility__store' ).forEach( function ( r ) { r.classList.toggle( 'hidden', 'store' !== v ); } );
			document.querySelectorAll( '.flx-visibility__email' ).forEach( function ( r ) { r.classList.toggle( 'hidden', 'auto' === v ); } );
		}
		box.addEventListener( 'change', sync );
	} )();
	</script>
	<?php
}

/** WooCommerce shows its own badge (Coming soon / Store coming soon / Live); the theme adds one only without it. */
function flx_coming_soon_admin_badge( $bar ) {
	$mode = flx_visibility_mode();
	if ( 'live' === $mode || ! current_user_can( 'edit_theme_options' ) ) {
		return;
	}
	if ( current_user_can( 'manage_woocommerce' ) && class_exists( '\Automattic\WooCommerce\Utilities\FeaturesUtil' ) && \Automattic\WooCommerce\Utilities\FeaturesUtil::feature_is_enabled( 'site_visibility_badge' ) ) {
		return;
	}
	$bar->add_node(
		array(
			'id'    => 'flx-coming-soon',
			'title' => 'store' === $mode ? __( 'Store coming soon', 'fluxwp' ) : __( 'Site coming soon', 'fluxwp' ),
			'href'  => admin_url( 'themes.php?page=flx-ayarlar#flx-visibility' ),
		)
	);
}
add_action( 'admin_bar_menu', 'flx_coming_soon_admin_badge', 90 );
