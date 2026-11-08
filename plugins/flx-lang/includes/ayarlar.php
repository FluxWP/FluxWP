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

add_action(
	'admin_enqueue_scripts',
	function ( $hook ) {
		if ( 'settings_page_flx-lang' === $hook ) {
			wp_enqueue_style( 'flxl-admin', FLXL_URL . 'assets/css/admin.css', array(), FLXL_VERSION );
		}
	}
);

add_action(
	'admin_post_flxl_save',
	function () {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You are not allowed to do this.', 'flx-lang' ) );
		}
		check_admin_referer( 'flxl_save' );
		$in  = wp_unslash( $_POST ); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput -- alan alan temizleniyor.
		$old = flxl_settings();
		$s   = $old;
		$cat = flxl_catalog();

		$langs = array();
		foreach ( (array) ( $in['langs'] ?? array() ) as $code => $l ) {
			$code = sanitize_key( $code );
			if ( ! preg_match( '/^[a-z]{2,3}(?:-[a-z0-9]{2,8})*$/', $code ) || ! empty( $l['remove'] ) ) {
				continue;
			}
			$langs[ $code ] = array(
				'name'   => sanitize_text_field( $l['name'] ?? $code ),
				'locale' => preg_replace( '/[^A-Za-z0-9_]/', '', (string) ( $l['locale'] ?? '' ) ),
				'flag'   => sanitize_text_field( $l['flag'] ?? '' ),
				'active' => empty( $l['active'] ) ? 0 : 1,
			);
		}
		$add = sanitize_key( $in['add_lang'] ?? '' );
		if ( $add && isset( $cat[ $add ] ) && ! isset( $langs[ $add ] ) ) {
			$langs[ $add ] = $cat[ $add ] + array( 'active' => 0 ); // Pasif eklenir: önce içerik hazırlanır.
		}
		$def = sanitize_key( $in['default'] ?? $old['default'] );
		if ( ! isset( $langs[ $def ] ) ) {
			$def = isset( $langs[ $old['default'] ] ) ? $old['default'] : (string) key( $langs );
		}
		if ( isset( $langs[ $def ] ) ) {
			$langs[ $def ]['active'] = 1;
		}
		$s['languages'] = $langs ? $langs : $old['languages'];
		$s['default']   = $def;

		$s['post_types'] = array_values( array_filter( array_map( 'sanitize_key', (array) ( $in['post_types'] ?? array() ) ), 'post_type_exists' ) );
		$s['fallback']   = empty( $in['fallback'] ) ? 0 : 1;
		$s['suggest']    = in_array( $in['suggest'] ?? '', array( 'off', 'banner', 'redirect' ), true ) ? $in['suggest'] : 'redirect';
		$s['suggest_to'] = isset( $s['languages'][ sanitize_key( $in['suggest_to'] ?? '' ) ] ) ? sanitize_key( $in['suggest_to'] ) : 'en';
		$s['switcher']   = in_array( $in['switcher'] ?? '', array( 'code', 'name', 'flag' ), true ) ? $in['switcher'] : 'code';
		$s['switcher_place'] = in_array( $in['switcher_place'] ?? '', array( 'footer', 'header', 'both' ), true ) ? $in['switcher_place'] : 'footer';
		$countries       = preg_split( '/[\s,;]+/', strtoupper( (string) ( $in['home_countries'] ?? '' ) ), -1, PREG_SPLIT_NO_EMPTY );
		$s['home_countries'] = array_values( array_unique( array_filter( $countries, function ( $c ) {
			return (bool) preg_match( '/^[A-Z]{2}$/', $c );
		} ) ) );

		$fields = flxl_text_fields();
		$texts  = array();
		foreach ( (array) ( $in['texts'] ?? array() ) as $code => $vals ) {
			$code = sanitize_key( $code );
			if ( ! isset( $s['languages'][ $code ] ) || $code === $def ) {
				continue;
			}
			foreach ( (array) $vals as $k => $v ) {
				if ( isset( $fields[ $k ] ) && '' !== trim( (string) $v ) ) {
					$texts[ $code ][ $k ] = empty( $fields[ $k ]['textarea'] ) ? sanitize_text_field( $v ) : sanitize_textarea_field( $v );
				}
			}
		}
		$s['texts'] = $texts;

		update_option( 'flxl_settings', $s, true );
		flxl_ensure_terms();
		flxl_index_flush();
		wp_safe_redirect( admin_url( 'options-general.php?page=flx-lang&saved=1' ) );
		exit;
	}
);

function flxl_settings_page() {
	$s      = flxl_settings();
	$langs  = flxl_languages( true );
	$def    = flxl_default_language();
	$cat    = flxl_catalog();
	$geo    = flxl_geo_country();
	$fields = flxl_text_fields();
	?>
	<div class="wrap flxl-settings">
		<h1><?php esc_html_e( 'Languages', 'flx-lang' ); ?></h1>
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
				<tr><th scope="row"><?php esc_html_e( 'Visitors from outside Türkiye', 'flx-lang' ); ?></th><td>
					<fieldset>
						<label><input type="radio" name="suggest" value="redirect" <?php checked( $s['suggest'], 'redirect' ); ?>> <?php esc_html_e( 'Open directly in their language (recommended)', 'flx-lang' ); ?></label><br>
						<label><input type="radio" name="suggest" value="banner" <?php checked( $s['suggest'], 'banner' ); ?>> <?php esc_html_e( 'Show a suggestion bar at the top of the page', 'flx-lang' ); ?></label><br>
						<label><input type="radio" name="suggest" value="off" <?php checked( $s['suggest'], 'off' ); ?>> <?php esc_html_e( 'Off', 'flx-lang' ); ?></label>
					</fieldset>
					<p class="description"><?php esc_html_e( 'Visitors from outside Türkiye open the version in their language (English if none) before the page renders, and stay in it on later visits. A visitor who picks another language in the switcher stays in it. Search engine bots are never redirected. If the country or time zone is Türkiye, the visitor stays in Turkish. Not applied to logged-in users; use a private window to test.', 'flx-lang' ); ?></p>
				</td></tr>
				<tr><th scope="row"><label for="flxl-to"><?php esc_html_e( 'Suggested language', 'flx-lang' ); ?></label></th><td>
					<select id="flxl-to" name="suggest_to">
						<?php foreach ( $langs as $code => $l ) : ?>
							<option value="<?php echo esc_attr( $code ); ?>" <?php selected( $s['suggest_to'], $code ); ?>><?php echo esc_html( $l['name'] ); ?></option>
						<?php endforeach; ?>
					</select>
					<p class="description"><?php esc_html_e( 'If the browser language is one of the active languages (e.g. German once added), that one is suggested; otherwise this one.', 'flx-lang' ); ?></p>
				</td></tr>
				<tr><th scope="row"><label for="flxl-hc"><?php esc_html_e( 'Countries that see Turkish', 'flx-lang' ); ?></label></th><td>
					<input id="flxl-hc" type="text" class="regular-text" name="home_countries" value="<?php echo esc_attr( implode( ', ', (array) $s['home_countries'] ) ); ?>">
					<p class="description"><?php esc_html_e( 'Two-letter country codes (TR, CY…).', 'flx-lang' ); ?></p>
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
