<?php
/**
 * Ayarlar › Diller.
 *
 * @package flx-lang
 */

defined( 'ABSPATH' ) || exit;

add_action(
	'admin_menu',
	function () {
		add_options_page( __( 'Languages', 'flx-lang' ), __( 'Languages', 'flx-lang' ), 'manage_options', 'flx-lang', 'flxl_settings_page' );
	}
);

add_filter(
	'plugin_action_links_' . plugin_basename( FLXL_FILE ),
	function ( $links ) {
		array_unshift( $links, '<a href="' . esc_url( admin_url( 'options-general.php?page=flx-lang' ) ) . '">' . esc_html__( 'Settings', 'flx-lang' ) . '</a>' );
		return $links;
	}
);

/**
 * Settings page address.
 *
 * @param array<string,string> $args Extra query arguments.
 */
function flxl_admin_url( array $args = array() ) {
	return add_query_arg( $args, admin_url( 'options-general.php?page=flx-lang' ) );
}

/*
 * New view (0.3.0, GraphQL): the page opens the same React screen as the FluxWP app (assets/flux/,
 * tools/fluxapp/embed-sync.sh flx-lang). It is the default and has every feature of the classic page;
 * a user can switch back to the classic page from the screen header for now. Needs Flux GraphQL.
 */
function flxl_flux_available() {
	return file_exists( FLXL_DIR . 'assets/flux/flux-modules.js' ) && function_exists( 'flx_gql_ready' ) && function_exists( 'flx_gql_all_caps' ) && flx_gql_ready();
}

function flxl_flux_ui() {
	return flxl_flux_available() && 'classic' !== get_user_meta( get_current_user_id(), 'flxl_ui', true );
}

/** Link that switches the current user between the views. */
function flxl_ui_switch_url( $ui ) {
	return wp_nonce_url( flxl_admin_url( array( 'flxl_ui' => 'flux' === $ui ? 'flux' : 'classic' ) ), 'flxl_ui' );
}

add_action(
	'admin_init',
	function () {
		if ( ! isset( $_GET['flxl_ui'], $_GET['page'] ) || 'flx-lang' !== $_GET['page'] || ! current_user_can( 'manage_options' ) ) { // phpcs:ignore WordPress.Security.NonceVerification
			return;
		}
		check_admin_referer( 'flxl_ui' );
		if ( 'classic' === $_GET['flxl_ui'] ) {
			update_user_meta( get_current_user_id(), 'flxl_ui', 'classic' );
		} else {
			delete_user_meta( get_current_user_id(), 'flxl_ui' );
		}
		wp_safe_redirect( flxl_admin_url() );
		exit;
	}
);

/** The chunked bundle loads as an ES module. */
function flxl_flux_module_tag( $tag, $handle ) {
	if ( 'flxl-flux' !== $handle ) {
		return $tag;
	}
	return (string) preg_replace_callback(
		'/<script\b[^>]*\ssrc=[^>]*>/',
		static function ( $m ) {
			$open = (string) preg_replace( '/\stype=("|\')[^"\']*\1/', '', $m[0] );
			return (string) preg_replace( '/^<script\b/', '<script type="module"', $open );
		},
		$tag,
		1
	);
}

function flxl_flux_assets() {
	$ver    = FLXL_VERSION . '-' . (int) filemtime( FLXL_DIR . 'assets/flux/flux-modules.js' );
	$accent = function_exists( 'flxa_accent' ) ? (string) flxa_accent() : '#2c7a64';
	$opts   = array(
		'module'     => 'flxl-lang',
		'rest'       => rest_url(),
		'nonce'      => wp_create_nonce( 'wp_rest' ),
		'locale'     => get_user_locale(),
		'siteName'   => get_bloginfo( 'name' ),
		'adminUrl'   => admin_url(),
		'theme'      => 'light',
		'colors'     => array(
			'primary'         => $accent,
			'primaryContrast' => function_exists( 'flxa_on_color' ) ? (string) flxa_on_color( $accent ) : '#ffffff',
		),
		'classicUrl' => flxl_ui_switch_url( 'classic' ),
	);
	wp_enqueue_style( 'flxl-flux', FLXL_URL . 'assets/flux/flux-modules.css', array(), $ver );
	wp_enqueue_script( 'flxl-flux', FLXL_URL . 'assets/flux/flux-modules.js', array(), $ver, true );
	add_filter( 'script_loader_tag', 'flxl_flux_module_tag', 10, 2 );
	// Light / dark follows Flux Admin (html.flxa-dark), also when the user switches it on the page.
	wp_add_inline_script(
		'flxl-flux',
		'(function(){var h=document.documentElement,o=' . wp_json_encode( $opts ) . ';o.theme=h.classList.contains("flxa-dark")?"dark":"light";var slot=window.FluxModulesOptions||(window.FluxModulesOptions={});slot.id="flxl-app";slot.opts=o;function sync(){var el=document.getElementById("flxl-app");if(el)el.dataset.theme=h.classList.contains("flxa-dark")?"dark":"light";}new MutationObserver(sync).observe(h,{attributes:true,attributeFilter:["class"]});function go(){if(slot.done||!window.FluxModules)return;var el=document.getElementById(slot.id);if(!el)return;slot.done=true;try{window.FluxModules.mount(el,slot.opts);}catch(e){el.textContent=String(e&&e.message||e);}}if(document.readyState==="loading")document.addEventListener("DOMContentLoaded",go);go();})();',
		'before'
	);
}

add_action(
	'admin_enqueue_scripts',
	function ( $hook ) {
		if ( 'settings_page_flx-lang' !== $hook ) {
			return;
		}
		if ( flxl_flux_ui() ) {
			flxl_flux_assets();
			return;
		}
		wp_enqueue_style( 'flxl-admin', FLXL_URL . 'assets/css/admin.css', array(), FLXL_VERSION );
	}
);

add_action(
	'admin_post_flxl_save',
	function () {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You are not allowed to do this.', 'flx-lang' ) );
		}
		check_admin_referer( 'flxl_save' );
		$in = wp_unslash( $_POST ); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput -- the service cleans every field.

		$langs = array();
		foreach ( (array) ( $in['langs'] ?? array() ) as $code => $l ) {
			if ( ! empty( $l['remove'] ) ) {
				continue; // A language that is not listed is removed.
			}
			$langs[] = array(
				'code'   => (string) $code,
				'name'   => (string) ( $l['name'] ?? '' ),
				'locale' => (string) ( $l['locale'] ?? '' ),
				'flag'   => (string) ( $l['flag'] ?? '' ),
				'active' => ! empty( $l['active'] ),
			);
		}
		$texts = array();
		foreach ( (array) ( $in['texts'] ?? array() ) as $code => $vals ) {
			foreach ( (array) $vals as $k => $v ) {
				$texts[] = array( 'lang' => (string) $code, 'key' => (string) $k, 'value' => (string) $v );
			}
		}
		FLXL_Language_Service::save_settings(
			array(
				'languages'      => $langs,
				'add_language'   => (string) ( $in['add_lang'] ?? '' ),
				'default'        => isset( $in['default'] ) ? (string) $in['default'] : null,
				'post_types'     => array_map( 'strval', (array) ( $in['post_types'] ?? array() ) ),
				'fallback'       => ! empty( $in['fallback'] ),
				'suggest'        => (string) ( $in['suggest'] ?? '' ),
				'suggest_to'     => (string) ( $in['suggest_to'] ?? '' ),
				'switcher'       => (string) ( $in['switcher'] ?? '' ),
				'switcher_place' => (string) ( $in['switcher_place'] ?? '' ),
				'home_countries' => (array) preg_split( '/[\s,;]+/', (string) ( $in['home_countries'] ?? '' ), -1, PREG_SPLIT_NO_EMPTY ),
				'texts'          => $texts,
			),
			false
		);
		wp_safe_redirect( admin_url( 'options-general.php?page=flx-lang&saved=1' ) );
		exit;
	}
);

function flxl_settings_page() {
	if ( ! current_user_can( 'manage_options' ) ) {
		return;
	}
	$s      = flxl_settings();
	$langs  = flxl_languages( true );
	$def    = flxl_default_language();
	$cat    = flxl_catalog();
	$geo    = flxl_geo_country();
	$fields = flxl_text_fields();
	if ( flxl_flux_ui() ) {
		echo '<style>#flxl-app.flux-root{height:auto;background:transparent;font-family:inherit}</style>';
		echo '<div class="wrap flxl-settings"><div id="flxl-app"><noscript>' . esc_html__( 'JavaScript is required for this screen.', 'flx-lang' ) . '</noscript></div></div>';
		return;
	}
	?>
	<div class="wrap flxl-settings">
		<h1><?php esc_html_e( 'Languages', 'flx-lang' ); ?></h1>
		<?php if ( flxl_flux_available() ) : ?>
			<p><a class="button" href="<?php echo esc_url( flxl_ui_switch_url( 'flux' ) ); ?>"><?php esc_html_e( 'New view', 'flx-lang' ); ?></a> <span class="description"><?php esc_html_e( 'Open the new view (same features, works in the FluxWP app too).', 'flx-lang' ); ?></span></p>
		<?php endif; ?>
		<?php if ( isset( $_GET['saved'] ) ) : // phpcs:ignore WordPress.Security.NonceVerification ?>
			<div class="notice notice-success is-dismissible"><p><?php esc_html_e( 'Saved.', 'flx-lang' ); ?></p></div>
		<?php endif; ?>
		<p><?php esc_html_e( 'The default language has no prefix in the address (example.org/hakkimizda/); other languages open with a prefix such as /en/. Each language version of a content is a separate page/post: create it from the language badge in the list or from the Language box in the editor. Theme and plugin interface texts use their own translations.', 'flx-lang' ); ?></p>
		<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
			<input type="hidden" name="action" value="flxl_save">
			<?php wp_nonce_field( 'flxl_save' ); ?>

			<h2><?php esc_html_e( 'Languages', 'flx-lang' ); ?></h2>
			<table class="widefat striped flxl-langs">
				<thead><tr>
					<th><?php esc_html_e( 'Active', 'flx-lang' ); ?></th>
					<th><?php esc_html_e( 'Code / address', 'flx-lang' ); ?></th>
					<th><?php esc_html_e( 'Name', 'flx-lang' ); ?></th>
					<th><?php esc_html_e( 'Flag', 'flx-lang' ); ?></th>
					<th><?php esc_html_e( 'WordPress locale', 'flx-lang' ); ?></th>
					<th><?php esc_html_e( 'Default', 'flx-lang' ); ?></th>
					<th><?php esc_html_e( 'Remove', 'flx-lang' ); ?></th>
				</tr></thead>
				<tbody>
				<?php foreach ( $langs as $code => $l ) : ?>
					<tr>
						<td data-label="<?php esc_attr_e( 'Active', 'flx-lang' ); ?>"><input type="checkbox" name="langs[<?php echo esc_attr( $code ); ?>][active]" value="1" <?php checked( $l['active'] ); ?> <?php disabled( $code === $def ); ?>><?php if ( $code === $def ) : ?><input type="hidden" name="langs[<?php echo esc_attr( $code ); ?>][active]" value="1"><?php endif; ?></td>
						<td data-label="<?php esc_attr_e( 'Code / address', 'flx-lang' ); ?>"><code><?php echo esc_html( $code === $def ? '/' : '/' . $code . '/' ); ?></code></td>
						<td data-label="<?php esc_attr_e( 'Name', 'flx-lang' ); ?>"><input type="text" class="regular-text flxl-small" name="langs[<?php echo esc_attr( $code ); ?>][name]" value="<?php echo esc_attr( $l['name'] ); ?>"></td>
						<td data-label="<?php esc_attr_e( 'Flag', 'flx-lang' ); ?>"><input type="text" class="small-text" name="langs[<?php echo esc_attr( $code ); ?>][flag]" value="<?php echo esc_attr( $l['flag'] ); ?>"></td>
						<td data-label="<?php esc_attr_e( 'WordPress locale', 'flx-lang' ); ?>"><input type="text" class="flxl-small" name="langs[<?php echo esc_attr( $code ); ?>][locale]" value="<?php echo esc_attr( $l['locale'] ); ?>"></td>
						<td data-label="<?php esc_attr_e( 'Default', 'flx-lang' ); ?>"><input type="radio" name="default" value="<?php echo esc_attr( $code ); ?>" <?php checked( $code, $def ); ?>></td>
						<td data-label="<?php esc_attr_e( 'Remove', 'flx-lang' ); ?>"><?php if ( $code !== $def ) : ?><input type="checkbox" name="langs[<?php echo esc_attr( $code ); ?>][remove]" value="1"><?php endif; ?></td>
					</tr>
				<?php endforeach; ?>
				</tbody>
			</table>
			<p>
				<label for="flxl-add"><?php esc_html_e( 'Add language', 'flx-lang' ); ?></label>
				<select id="flxl-add" name="add_lang"><option value=""><?php esc_html_e( '— choose —', 'flx-lang' ); ?></option>
				<?php foreach ( $cat as $code => $c ) : ?>
					<?php if ( ! isset( $langs[ $code ] ) ) : ?>
						<option value="<?php echo esc_attr( $code ); ?>"><?php echo esc_html( $c['flag'] . ' ' . $c['name'] . ' (/' . $code . '/)' ); ?></option>
					<?php endif; ?>
				<?php endforeach; ?>
				</select>
				<span class="description"><?php esc_html_e( 'A new language is added inactive: prepare its content, then activate it. Languages other than English also need the WordPress language pack (System check warns).', 'flx-lang' ); ?></span>
			</p>

			<h2><?php esc_html_e( 'Content', 'flx-lang' ); ?></h2>
			<table class="form-table" role="presentation">
				<tr><th scope="row"><?php esc_html_e( 'Translated content types', 'flx-lang' ); ?></th><td>
					<?php foreach ( get_post_types( array( 'public' => true ), 'objects' ) as $pt ) : ?>
						<?php if ( in_array( $pt->name, array( 'attachment', 'product' ), true ) ) { continue; } ?>
						<label style="margin-right:14px"><input type="checkbox" name="post_types[]" value="<?php echo esc_attr( $pt->name ); ?>" <?php checked( in_array( $pt->name, (array) $s['post_types'], true ) ); ?>> <?php echo esc_html( $pt->labels->name ); ?></label>
					<?php endforeach; ?>
					<p class="description"><?php esc_html_e( 'Cart, checkout and my account pages are shared by all languages. Categories are shared; their names are translated per language on the category edit screen. Language-specific alt text and replacement images are on the media edit screen.', 'flx-lang' ); ?></p>
				</td></tr>
				<tr><th scope="row"><?php esc_html_e( 'Content without a translation', 'flx-lang' ); ?></th><td>
					<label><input type="checkbox" name="fallback" value="1" <?php checked( ! empty( $s['fallback'] ) ); ?>> <?php esc_html_e( 'Show the Turkish version in other languages (menu and interface stay in that language; search engines get the Turkish canonical address)', 'flx-lang' ); ?></label>
					<p class="description"><?php esc_html_e( 'When off, the English site lists only content that has an English version.', 'flx-lang' ); ?></p>
				</td></tr>
				<tr><th scope="row"><label for="flxl-sw"><?php esc_html_e( 'Language switcher', 'flx-lang' ); ?></label></th><td>
					<select id="flxl-sw" name="switcher">
						<option value="code" <?php selected( $s['switcher'], 'code' ); ?>>TR · EN</option>
						<option value="name" <?php selected( $s['switcher'], 'name' ); ?>>Türkçe · English</option>
						<option value="flag" <?php selected( $s['switcher'], 'flag' ); ?>>🇹🇷 · 🇬🇧</option>
					</select>
					<p class="description"><?php esc_html_e( 'Elsewhere: the "Flux Language Switcher" block or the [flx_dil_secici] shortcode.', 'flx-lang' ); ?></p>
				</td></tr>
				<tr><th scope="row"><?php esc_html_e( 'Switcher position', 'flx-lang' ); ?></th><td>
					<label><input type="radio" name="switcher_place" value="footer" <?php checked( $s['switcher_place'], 'footer' ); ?>> <?php esc_html_e( 'Footer', 'flx-lang' ); ?></label><br>
					<label><input type="radio" name="switcher_place" value="header" <?php checked( $s['switcher_place'], 'header' ); ?>> <?php esc_html_e( 'Header and mobile menu', 'flx-lang' ); ?></label><br>
					<label><input type="radio" name="switcher_place" value="both" <?php checked( $s['switcher_place'], 'both' ); ?>> <?php esc_html_e( 'Both', 'flx-lang' ); ?></label>
				</td></tr>
			</table>

			<h2><?php esc_html_e( 'Language suggestion for visitors', 'flx-lang' ); ?></h2>
			<table class="form-table" role="presentation">
				<tr><th scope="row"><?php esc_html_e( 'Automatic language', 'flx-lang' ); ?></th><td>
					<fieldset>
						<label><input type="radio" name="suggest" value="redirect" <?php checked( $s['suggest'], 'redirect' ); ?>> <?php esc_html_e( 'Open directly in their language (recommended)', 'flx-lang' ); ?></label><br>
						<label><input type="radio" name="suggest" value="banner" <?php checked( $s['suggest'], 'banner' ); ?>> <?php esc_html_e( 'Show a suggestion bar at the top of the page', 'flx-lang' ); ?></label><br>
						<label><input type="radio" name="suggest" value="off" <?php checked( $s['suggest'], 'off' ); ?>> <?php esc_html_e( 'Off', 'flx-lang' ); ?></label>
					</fieldset>
					<p class="description"><?php esc_html_e( 'On the first visit, before the page renders, the site opens in the visitor\'s browser language if it is active here; otherwise in their country\'s language; otherwise in the suggested language below. The choice is kept on later visits, and a visitor who picks another language in the switcher stays in it. Search engine bots are never redirected. Not applied to logged-in users; use a private window to test.', 'flx-lang' ); ?></p>
				</td></tr>
				<tr><th scope="row"><label for="flxl-to"><?php esc_html_e( 'Suggested language', 'flx-lang' ); ?></label></th><td>
					<select id="flxl-to" name="suggest_to">
						<?php foreach ( $langs as $code => $l ) : ?>
							<option value="<?php echo esc_attr( $code ); ?>" <?php selected( $s['suggest_to'], $code ); ?>><?php echo esc_html( $l['name'] ); ?></option>
						<?php endforeach; ?>
					</select>
					<p class="description"><?php esc_html_e( 'Used when neither the browser language nor the country\'s language is active on the site.', 'flx-lang' ); ?></p>
				</td></tr>
				<tr><th scope="row"><label for="flxl-hc"><?php esc_html_e( 'Countries that see the default language', 'flx-lang' ); ?></label></th><td>
					<input id="flxl-hc" type="text" class="regular-text" name="home_countries" value="<?php echo esc_attr( implode( ', ', (array) $s['home_countries'] ) ); ?>">
					<p class="description"><?php esc_html_e( 'Two-letter country codes (TR, CY…). Used only when the browser language is not on the site.', 'flx-lang' ); ?></p>
				</td></tr>
				<tr><th scope="row"><?php esc_html_e( 'Country information', 'flx-lang' ); ?></th><td>
					<?php if ( $geo['country'] ) : ?>
						<p><?php /* translators: 1: country code, 2: header name */ printf( esc_html__( 'Country in your request: %1$s (%2$s header). Country-based suggestion works.', 'flx-lang' ), '<strong>' . esc_html( $geo['country'] ) . '</strong>', '<code>' . esc_html( $geo['source'] ) . '</code>' ); ?></p>
					<?php else : ?>
						<p><?php esc_html_e( 'The server sends no country information; the browser\'s time zone is used (Türkiye time = Turkish). For more accurate results, put the domain behind Cloudflare (free plan, the CF-IPCountry header is used automatically) or enable your host\'s GeoIP header.', 'flx-lang' ); ?></p>
					<?php endif; ?>
				</td></tr>
			</table>

			<?php
			$others = array_diff_key( $langs, array( $def => 1 ) );
			if ( $others ) :
				?>
				<h2><?php esc_html_e( 'Site texts per language', 'flx-lang' ); ?></h2>
				<p class="description"><?php esc_html_e( 'Empty fields are shown in Turkish.', 'flx-lang' ); ?></p>
				<table class="form-table flxl-texts" role="presentation">
					<?php foreach ( $others as $code => $l ) : ?>
						<tr><th colspan="2"><h3 style="margin:0"><?php echo esc_html( trim( $l['flag'] . ' ' . $l['name'] ) ); ?></h3></th></tr>
						<?php foreach ( $fields as $k => $f ) : ?>
							<?php
							$id  = 'flxl-t-' . $code . '-' . $k;
							$cur = flxl_text( $k, $code );
							$ph  = flxl_raw(
								function () use ( $f ) {
									$v = get_option( $f['option'] );
									return empty( $f['key'] ) ? (string) $v : (string) ( is_array( $v ) ? ( $v[ $f['key'] ] ?? '' ) : '' );
								}
							);
							?>
							<tr><th scope="row"><label for="<?php echo esc_attr( $id ); ?>"><?php echo esc_html( $f['label'] ); ?></label></th><td>
								<?php if ( ! empty( $f['textarea'] ) ) : ?>
									<textarea id="<?php echo esc_attr( $id ); ?>" rows="2" name="texts[<?php echo esc_attr( $code ); ?>][<?php echo esc_attr( $k ); ?>]" placeholder="<?php echo esc_attr( $ph ); ?>" lang="<?php echo esc_attr( $code ); ?>"><?php echo esc_textarea( $cur ); ?></textarea>
								<?php else : ?>
									<input id="<?php echo esc_attr( $id ); ?>" type="text" class="large-text" name="texts[<?php echo esc_attr( $code ); ?>][<?php echo esc_attr( $k ); ?>]" value="<?php echo esc_attr( $cur ); ?>" placeholder="<?php echo esc_attr( $ph ); ?>" lang="<?php echo esc_attr( $code ); ?>">
								<?php endif; ?>
							</td></tr>
						<?php endforeach; ?>
					<?php endforeach; ?>
				</table>
			<?php endif; ?>

			<?php submit_button( __( 'Save', 'flx-lang' ) ); ?>
		</form>
		<h2><?php esc_html_e( 'Translation with AI', 'flx-lang' ); ?></h2>
		<p><?php esc_html_e( 'With Flux AI installed, a "Translate with AI" button appears in the list and the editor. AI agents connected over MCP (Claude etc.) and the Flux AI chat use these tools: flxl_languages, flxl_untranslated, flxl_translations, flxl_save_translation, flxl_set_language, flxl_image_language, flxl_term_translation, flxl_site_texts. Flux SEO tools (list_content, create_content, update_content) have a lang parameter.', 'flx-lang' ); ?></p>
	</div>
	<?php
}
