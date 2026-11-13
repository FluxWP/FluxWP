<?php
/**
 * Performans › PageSpeed sekmesi: aç/kapa iyileştirmeler, önbellek, animasyon uyarıları.
 *
 * @package flx-speed
 */

defined( 'ABSPATH' ) || exit;

class FLXP_Admin_PageSpeed {

	public static function init() {
		add_action( 'admin_post_flxp_ps_save', array( __CLASS__, 'save' ) );
		add_action( 'admin_post_flxp_ps_clear', array( __CLASS__, 'clear' ) );
	}

	private static function url( $args = array() ) {
		return add_query_arg( array_merge( array( 'tab' => 'pagespeed' ), $args ), admin_url( 'admin.php?page=flx-speed' ) );
	}

	/** Tek satır: anahtar, başlık, rozet, açıklama, alt içerik. */
	private static function row( $key, $title, $risk, $desc, $extra = '' ) {
		$s  = flxp_settings();
		$on = ! empty( $s[ $key ] );
		$id = 'flxp-' . str_replace( '_', '-', $key );
		$b  = array(
			'safe' => array( 'is-safe', __( 'Safe', 'flx-speed' ) ),
			'test' => array( 'is-test', __( 'Test first', 'flx-speed' ) ),
		)[ $risk ];
		$forced = ( (bool) apply_filters( 'flxp_pagespeed_enabled', $on, $key ) ) !== $on;
		?>
		<div class="flxp-ps-row<?php echo $on ? ' is-on' : ''; ?>">
			<label class="flxp-switch" for="<?php echo esc_attr( $id ); ?>">
				<input type="checkbox" id="<?php echo esc_attr( $id ); ?>" name="flxp[<?php echo esc_attr( $key ); ?>]" value="1" <?php checked( $on ); ?>>
				<span class="flxp-switch-ui" aria-hidden="true"></span>
			</label>
			<div class="flxp-ps-text">
				<label for="<?php echo esc_attr( $id ); ?>" class="flxp-ps-title"><?php echo esc_html( $title ); ?></label>
				<span class="flxp-badge <?php echo esc_attr( $b[0] ); ?>"><?php echo esc_html( $b[1] ); ?></span>
				<p class="description"><?php echo esc_html( $desc ); ?></p>
				<?php if ( $forced ) : ?>
					<p class="flxp-ps-forced"><?php esc_html_e( 'The theme or a preset overrides this feature in code (flxp_pagespeed_enabled); this setting may not take effect.', 'flx-speed' ); ?></p>
				<?php endif; ?>
				<?php echo $extra; // phpcs:ignore WordPress.Security.EscapeOutput -- çağıran kaçışlar. ?>
			</div>
		</div>
		<?php
	}

	public static function render() {
		$s     = flxp_settings();
		$stats = FLXP_PageSpeed::cache_stats();
		$anim  = FLXP_PageSpeed::animation_report();
		$seen  = get_option( 'flxp_ps_seen' );
		$home  = home_url( '/' );
		$msg   = isset( $_GET['flxp_ps'] ) ? sanitize_key( $_GET['flxp_ps'] ) : ''; // phpcs:ignore
		if ( 'saved' === $msg ) {
			echo '<div class="notice notice-success is-dismissible"><p>' . esc_html__( 'Saved. The cache and the LiteSpeed page cache were cleared; browse a few pages to check.', 'flx-speed' ) . '</p></div>';
		} elseif ( 'cleared' === $msg ) {
			echo '<div class="notice notice-success is-dismissible"><p>' . esc_html__( 'PageSpeed cache cleared.', 'flx-speed' ) . '</p></div>';
		}
		?>
		<p class="flxp-ps-intro"><?php esc_html_e( 'Google PageSpeed / Lighthouse suggestions that can be applied without changing the theme. Each one is switched on separately; after enabling a "Test first" item, check a few pages of the site (home, a post, donation/checkout).', 'flx-speed' ); ?></p>
		<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="flxp-ps">
			<?php wp_nonce_field( 'flxp_ps_save' ); ?>
			<input type="hidden" name="action" value="flxp_ps_save">

			<section class="flxp-ps-card">
				<h2><?php esc_html_e( 'Basics', 'flx-speed' ); ?></h2>
				<?php
				self::row( 'ps_viewport', __( 'Remove the zoom lock', 'flx-speed' ), 'safe', __( 'user-scalable=no and maximum-scale are removed from the viewport tag so visitors with low vision can zoom in. Directly affects the Accessibility score.', 'flx-speed' ) );
				self::row( 'ps_emoji', __( 'Remove the emoji script', 'flx-speed' ), 'safe', __( 'WordPress\'s emoji detection script and style are not loaded. Modern browsers render emoji themselves.', 'flx-speed' ) );
				?>
			</section>

			<section class="flxp-ps-card">
				<h2>CSS</h2>
				<?php
				ob_start();
				?>
				<div class="flxp-ps-sub">
					<fieldset>
						<legend><?php esc_html_e( 'Scope', 'flx-speed' ); ?></legend>
						<label><input type="radio" name="flxp[ps_css_scope]" value="theme" <?php checked( 'theme', $s['ps_css_scope'] ); ?>> <?php esc_html_e( 'Theme CSS only (recommended)', 'flx-speed' ); ?></label>
						<label><input type="radio" name="flxp[ps_css_scope]" value="local" <?php checked( 'local', $s['ps_css_scope'] ); ?>> <?php esc_html_e( 'Theme + plugins (all CSS under wp-content)', 'flx-speed' ); ?></label>
					</fieldset>
					<p>
						<label for="flxp-ps-inline"><?php esc_html_e( 'Inline limit', 'flx-speed' ); ?></label>
						<input id="flxp-ps-inline" type="number" min="0" max="500" name="flxp[ps_inline_kb]" value="<?php echo (int) $s['ps_inline_kb']; ?>" class="small-text"> KB
						<span class="description"><?php esc_html_e( 'Trimmed CSS is written into <head> up to this size; anything beyond is loaded as a separate cached file. 0 = always a file.', 'flx-speed' ); ?></span>
					</p>
					<p>
						<label for="flxp-ps-safelist"><?php esc_html_e( 'Always keep these classes', 'flx-speed' ); ?></label>
						<textarea id="flxp-ps-safelist" name="flxp[ps_safelist]" rows="3" class="large-text code" placeholder="menu-open&#10;swiper-*"><?php echo esc_textarea( $s['ps_safelist'] ); ?></textarea>
						<span class="description"><?php esc_html_e( 'One class or id per line, * as wildcard. If a style added later by JavaScript breaks, add its class here. is-*, has-*, wp-*, swiper-* are always kept.', 'flx-speed' ); ?></span>
					</p>
				</div>
				<?php
				$sub = ob_get_clean();
				self::row( 'ps_css', __( 'Remove unused CSS and inline it', 'flx-speed' ), 'test', __( 'Each page\'s class/id inventory is collected (HTML + the page\'s scripts); rules with no match on that page are dropped from the CSS file and the rest is written into <head>. The render-blocking CSS request goes away and LCP/FCP improve. Pages with the same inventory share one copy; copies are rebuilt when the file changes, and the full file is used if there are too many different pages.', 'flx-speed' ), $sub );
				self::row( 'ps_min_css', __( 'Minify CSS files', 'flx-speed' ), 'safe', __( 'For unminified (non-.min) local CSS files, an up-to-date .min sibling or a copy without comments and whitespace is used.', 'flx-speed' ) );
				?>
			</section>

			<section class="flxp-ps-card">
				<h2>JavaScript</h2>
				<?php
				self::row( 'ps_min_js', __( 'Minify JS files', 'flx-speed' ), 'test', __( 'For theme and plugin scripts, the .min sibling is used if it is newer than the source; otherwise a copy without comments and indentation is generated (line breaks are kept). Bundles that load chunks relative to their own location and scripts with translations are left alone.', 'flx-speed' ) );
				ob_start();
				?>
				<div class="flxp-ps-sub">
					<p class="description"><?php esc_html_e( 'jQuery stays blocking unless "Defer jQuery too" is on. WordPress itself drops defer for scripts that have an inline script after them or that a blocking script depends on.', 'flx-speed' ); ?></p>
				</div>
				<?php
				$sub = ob_get_clean();
				self::row( 'ps_defer', __( 'Defer head scripts', 'flx-speed' ), 'test', __( 'Scripts loaded in the page <head> that hold up rendering get defer (e.g. language suggestion, analytics scripts).', 'flx-speed' ), $sub );
				self::row( 'ps_defer_jquery', __( 'Defer jQuery too', 'flx-speed' ), 'test', __( 'jQuery and jQuery Migrate get defer as well. WordPress keeps them blocking when a blocking script depends on them, and Flux Speed keeps them blocking on pages with an inline script that calls jQuery or $ directly.', 'flx-speed' ) );
				?>
			</section>

			<section class="flxp-ps-card">
				<h2><?php esc_html_e( 'Fonts', 'flx-speed' ); ?></h2>
				<?php
				self::row( 'ps_fonts', __( 'Remove unused fonts', 'flx-speed' ), 'safe', __( 'Font families defined in theme.json but not referenced in the page\'s CSS (@font-face) are not printed. If usage cannot be determined, nothing is removed.', 'flx-speed' ) );
				self::row( 'ps_preload', __( 'Preload the above-the-fold font', 'flx-speed' ), 'test', __( 'The regular (400) woff2 of up to two families in use is requested with <link rel="preload"> so text paints sooner. Preloading the wrong weight wastes a download; turn this off if PageSpeed reports an unused preload.', 'flx-speed' ) );
				?>
			</section>

			<section class="flxp-ps-card">
				<h2><?php esc_html_e( 'Exclusions', 'flx-speed' ); ?></h2>
				<p>
					<label for="flxp-ps-exclude"><strong><?php esc_html_e( 'Files to leave alone', 'flx-speed' ); ?></strong></label>
					<textarea id="flxp-ps-exclude" name="flxp[ps_exclude]" rows="3" class="large-text code" placeholder="woocommerce&#10;flx-donate"><?php echo esc_textarea( $s['ps_exclude'] ); ?></textarea>
					<span class="description"><?php esc_html_e( 'One handle or part of a URL per line. Trimming, minifying and defer are not applied to these files.', 'flx-speed' ); ?></span>
				</p>
				<p>
					<label for="flxp-ps-skip"><strong><?php esc_html_e( 'Pages to leave alone', 'flx-speed' ); ?></strong></label>
					<textarea id="flxp-ps-skip" name="flxp[ps_skip_urls]" rows="2" class="large-text code" placeholder="/odeme/&#10;/hesabim/"><?php echo esc_textarea( $s['ps_skip_urls'] ); ?></textarea>
					<span class="description"><?php esc_html_e( 'One URL part per line. No PageSpeed optimization runs on these pages.', 'flx-speed' ); ?></span>
				</p>
			</section>
			<?php submit_button( __( 'Save and clear cache', 'flx-speed' ) ); ?>
		</form>

		<div class="flxp-env">
			<section class="flxp-ps-card">
				<h2><?php esc_html_e( 'Status', 'flx-speed' ); ?></h2>
				<table class="widefat striped flxp-kv"><tbody>
					<tr><th><?php esc_html_e( 'Cache', 'flx-speed' ); ?></th><td>
						<?php
						/* translators: 1: CSS dosya sayısı, 2: JS dosya sayısı, 3: boyut */
						echo esc_html( sprintf( __( '%1$d CSS, %2$d JS copies · %3$s', 'flx-speed' ), $stats['css'], $stats['js'], size_format( $stats['bytes'] ) ) );
						?>
						<code>wp-content/uploads/flx-speed/</code>
					</td></tr>
					<tr><th><?php esc_html_e( 'Compare', 'flx-speed' ); ?></th><td>
						<a href="<?php echo esc_url( $home ); ?>" target="_blank" rel="noopener"><?php esc_html_e( 'Optimized', 'flx-speed' ); ?></a> ·
						<a href="<?php echo esc_url( add_query_arg( 'flxp_off', '1', $home ) ); ?>" target="_blank" rel="noopener"><?php esc_html_e( 'Off (?flxp_off=1)', 'flx-speed' ); ?></a>
						<p class="description"><?php esc_html_e( 'Adding ?flxp_off=1 to a URL opens the page without optimizations; if the page looks different, the cause is here.', 'flx-speed' ); ?></p>
					</td></tr>
					<tr><th><?php esc_html_e( 'Measure', 'flx-speed' ); ?></th><td>
						<?php if ( function_exists( 'flxseo_admin_url' ) ) : ?>
							<a href="<?php echo esc_url( flxseo_admin_url( 'denetim' ) ); ?>"><?php esc_html_e( 'Flux SEO › Site audit', 'flx-speed' ); ?></a>
							<p class="description"><?php esc_html_e( 'The site audit suggests these settings itself and turns them on with one click.', 'flx-speed' ); ?></p>
						<?php else : ?>
							<a href="<?php echo esc_url( 'https://pagespeed.web.dev/analysis?url=' . rawurlencode( $home ) ); ?>" target="_blank" rel="noopener">PageSpeed Insights</a>
						<?php endif; ?>
					</td></tr>
				</tbody></table>
				<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="flxp-ps-clear">
					<?php wp_nonce_field( 'flxp_ps_clear' ); ?>
					<input type="hidden" name="action" value="flxp_ps_clear">
					<button class="button"><?php esc_html_e( 'Clear cache', 'flx-speed' ); ?></button>
				</form>
			</section>

			<section class="flxp-ps-card">
				<h2><?php esc_html_e( 'Animations not running on the GPU', 'flx-speed' ); ?></h2>
				<p class="description"><?php esc_html_e( 'Lighthouse "non-composited animations": properties other than transform and opacity (stroke-dashoffset, background, width…) repaint every frame. A setting cannot fix this; the theme or preset must rewrite them with transform/opacity.', 'flx-speed' ); ?></p>
				<?php if ( $anim ) : ?>
					<table class="widefat striped flxp-list"><thead><tr>
						<th><?php esc_html_e( 'Animation', 'flx-speed' ); ?></th>
						<th><?php esc_html_e( 'Properties', 'flx-speed' ); ?></th>
						<th><?php esc_html_e( 'File', 'flx-speed' ); ?></th>
					</tr></thead><tbody>
					<?php foreach ( $anim as $a ) : ?>
						<tr>
							<td><code>@keyframes <?php echo esc_html( $a['name'] ); ?></code></td>
							<td><?php echo esc_html( implode( ', ', $a['props'] ) ); ?></td>
							<td class="flxp-url"><?php echo esc_html( $a['file'] ); ?><?php echo $a['reduced'] ? '' : ' <span class="flxp-flag flxp-flag-slow">' . esc_html__( 'no prefers-reduced-motion', 'flx-speed' ) . '</span>'; ?></td>
						</tr>
					<?php endforeach; ?>
					</tbody></table>
				<?php else : ?>
					<p class="flxp-ps-ok"><?php esc_html_e( 'All good: animations in the CSS files seen use transform/opacity.', 'flx-speed' ); ?></p>
				<?php endif; ?>
				<p class="description">
					<?php
					if ( is_array( $seen ) && ! empty( $seen['t'] ) ) {
						/* translators: 1: dosya sayısı, 2: "… önce" */
						echo esc_html( sprintf( __( 'Scanned %1$d CSS files seen on the front end (%2$s).', 'flx-speed' ), count( (array) $seen['files'] ), function_exists( 'flx_time_ago' ) ? flx_time_ago( (int) $seen['t'] ) : gmdate( 'Y-m-d H:i', (int) $seen['t'] ) ) );
					} else {
						esc_html_e( 'The front end has not been seen yet, so the theme folder was scanned. Once the site is opened, the files the page loads are scanned.', 'flx-speed' );
					}
					?>
				</p>
			</section>
		</div>
		<?php
	}

	public static function save() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( '', 403 );
		}
		check_admin_referer( 'flxp_ps_save' );
		$in  = isset( $_POST['flxp'] ) && is_array( $_POST['flxp'] ) ? wp_unslash( $_POST['flxp'] ) : array(); // phpcs:ignore
		$out = flxp_sanitize_settings( $in, 'pagespeed' );
		update_option( 'flxp_settings', $out, false );
		FLXP_PageSpeed::clear_cache();
		delete_transient( 'flxp_ps_anim' );
		do_action( 'litespeed_purge_all' );
		wp_safe_redirect( self::url( array( 'flxp_ps' => 'saved' ) ) );
		exit;
	}

	public static function clear() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( '', 403 );
		}
		check_admin_referer( 'flxp_ps_clear' );
		FLXP_PageSpeed::clear_cache();
		delete_transient( 'flxp_ps_anim' );
		do_action( 'litespeed_purge_all' );
		wp_safe_redirect( self::url( array( 'flxp_ps' => 'cleared' ) ) );
		exit;
	}
}
FLXP_Admin_PageSpeed::init();
