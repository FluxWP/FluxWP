<?php
/**
 * Yönetim: İstatistikler (tek sayfa uygulama, assets/admin.js) ve Ayarlar; CSV dışa aktarma,
 * Koko Analytics aktarımı, veriyi sıfırlama.
 *
 * @package flx-analytics
 */

defined( 'ABSPATH' ) || exit;

class FLXAN_Admin {

	const SLUG = 'flx-analytics';

	public static function init() {
		add_action( 'admin_menu', array( __CLASS__, 'menu' ) );
		add_action( 'admin_head', array( __CLASS__, 'menu_icon' ) );
		add_action( 'admin_enqueue_scripts', array( __CLASS__, 'assets' ) );
		add_action( 'admin_post_flxan_save', array( __CLASS__, 'save' ) );
		add_action( 'admin_post_flxan_export', array( __CLASS__, 'export' ) );
		add_action( 'admin_post_flxan_reset', array( __CLASS__, 'reset' ) );
		add_action( 'admin_post_flxan_import_koko', array( __CLASS__, 'import_koko' ) );
		add_action( 'admin_post_flxan_aggregate', array( __CLASS__, 'aggregate' ) );
	}

	public static function menu() {
		$cap = current_user_can( 'manage_options' ) ? 'manage_options' : 'flxan_view';
		add_menu_page( __( 'Site Analytics', 'flx-analytics' ), __( 'Site Analytics', 'flx-analytics' ), $cap, self::SLUG, array( __CLASS__, 'page' ), 'none', 3 );
		add_submenu_page( self::SLUG, __( 'Statistics', 'flx-analytics' ), __( 'Statistics', 'flx-analytics' ), $cap, self::SLUG, array( __CLASS__, 'page' ) );
		add_submenu_page( self::SLUG, __( 'Journeys', 'flx-analytics' ), __( 'Journeys', 'flx-analytics' ), $cap, self::SLUG . '-journeys', array( __CLASS__, 'journeys_page' ) );
		add_submenu_page( self::SLUG, __( 'Site Analytics settings', 'flx-analytics' ), __( 'Settings', 'flx-analytics' ), 'manage_options', self::SLUG . '-settings', array( __CLASS__, 'settings_page' ) );
	}

	/** Renkli menü logosu (WP data-URI SVG'yi tek renge boyar). */
	public static function menu_icon() {
		echo '<style>#toplevel_page_flx-analytics .wp-menu-image{background:url(' . esc_url( FLXAN_URL . 'assets/icon.svg' ) . ') center/20px 20px no-repeat}</style>';
	}

	public static function assets( $hook ) {
		if ( false === strpos( (string) $hook, self::SLUG ) ) {
			return;
		}
		wp_enqueue_style( 'flxan-admin', FLXAN_URL . 'assets/admin.css', array(), FLXAN_VERSION );
		$roles   = (array) wp_get_current_user()->roles;
		$ignored = (bool) array_intersect( $roles, (array) flxan_settings( 'exclude_roles' ) );
		$test    = current_user_can( 'manage_options' ) && 'rest' !== flxan_settings( 'method' ) && flxan_fast_possible()
			&& ( time() - (int) get_option( 'flxan_fast_test_time', 0 ) > DAY_IN_SECONDS || false !== strpos( (string) $hook, 'settings' ) );
		$cfg     = array(
			'rest'    => esc_url_raw( rest_url( 'flxan/v1/' ) ),
			'nonce'   => wp_create_nonce( 'wp_rest' ),
			'home'    => home_url(),
			'admin'   => admin_url(),
			'locale'  => str_replace( '_', '-', determine_locale() ),
			'presets' => FLXAN_Stats::presets(),
			'kinds'   => FLXAN_Stats::kinds(),
			'events'  => FLXAN_Stats::event_labels(),
			'channels'=> array(
				'direct'   => flxan_dim_label( 'channel', 'direct' ),
				'search'   => flxan_dim_label( 'channel', 'search' ),
				'social'   => flxan_dim_label( 'channel', 'social' ),
				'ai'       => flxan_dim_label( 'channel', 'ai' ),
				'email'    => flxan_dim_label( 'channel', 'email' ),
				'paid'     => flxan_dim_label( 'channel', 'paid' ),
				'campaign' => flxan_dim_label( 'channel', 'campaign' ),
				'referral' => flxan_dim_label( 'channel', 'referral' ),
				'ref'      => flxan_dim_label( 'channel', 'ref' ),
			),
			'segs'    => FLXAN_Stats::seg_options(),
			'by'      => FLXAN_Stats::funnel_by(),
			'refedit' => flxan_can_manage_refs() ? 1 : 0,
			'settings'=> current_user_can( 'manage_options' ) ? admin_url( 'admin.php?page=' . self::SLUG . '-settings#flxan-funnels' ) : '',
			'ignore'  => $ignored ? 1 : 0,
			'fast'    => $test ? FLXAN_URL . 'endpoint.php' : '',
			'manage'  => current_user_can( 'manage_options' ) ? 1 : 0,
			'export'  => wp_nonce_url( admin_url( 'admin-post.php?action=flxan_export' ), 'flxan_export' ),
			'i18n'    => flxan_js_strings(),
		);
		$deps       = array();
		$is_journey = false !== strpos( (string) $hook, 'journeys' );
		$is_set     = false !== strpos( (string) $hook, 'settings' );
		if ( ! $is_set && ! $is_journey ) {
			wp_enqueue_script( 'flxan-world', FLXAN_URL . 'assets/world.js', array(), FLXAN_VERSION, true );
			$deps[] = 'flxan-world';
		}
		if ( $is_journey ) {
			$cfg['goals'] = FLXAN_Journeys::goals();
			$cfg['goal']  = FLXAN_Journeys::default_goal();
		}
		if ( $is_set ) {
			$cfg['funnels']  = flxan_funnels();
			$cfg['fdefault'] = flxan_default_funnels();
			$cfg['fevents']  = flxan_funnel_event_options();
			$cfg['fpages']   = flxan_funnel_page_options();
		}
		wp_enqueue_script( 'flxan-admin', FLXAN_URL . 'assets/admin.js', $deps, FLXAN_VERSION, true );
		wp_add_inline_script( 'flxan-admin', 'window.FLXAN=' . wp_json_encode( $cfg ) . ';', 'before' );
		if ( $is_journey ) {
			wp_enqueue_script( 'flxan-journeys', FLXAN_URL . 'assets/journeys.js', array( 'flxan-admin' ), FLXAN_VERSION, true );
		}
		if ( $is_set ) {
			wp_enqueue_script( 'flxan-funnels', FLXAN_URL . 'assets/funnels.js', array( 'flxan-admin' ), FLXAN_VERSION, true );
		}
	}

	private static function url( $page = '', $args = array() ) {
		return add_query_arg( $args, admin_url( 'admin.php?page=' . self::SLUG . ( $page ? '-' . $page : '' ) ) );
	}

	/** İstatistikler: iskelet; içerik admin.js ile gelir. */
	public static function page() {
		if ( ! flxan_can_view() ) {
			wp_die( esc_html__( 'You are not allowed to view this page.', 'flx-analytics' ) );
		}
		echo '<div class="wrap flxan"><div id="flxan-app" class="flxan-app" aria-busy="true">';
		echo '<div class="flxan-head"><h1>' . esc_html__( 'Statistics', 'flx-analytics' ) . '</h1></div>';
		echo '<div class="flxan-cards flxan-kpis">';
		for ( $i = 0; $i < 6; $i++ ) {
			echo '<div class="flxan-card flxan-skel"></div>';
		}
		echo '</div><div class="flxan-panel flxan-skel flxan-skel--chart"></div>';
		echo '<noscript><p>' . esc_html__( 'This screen requires JavaScript.', 'flx-analytics' ) . '</p></noscript>';
		echo '</div></div>';
	}

	/** Yolculuklar: iskelet; içerik journeys.js ile gelir. */
	public static function journeys_page() {
		if ( ! flxan_can_view() ) {
			wp_die( esc_html__( 'You are not allowed to view this page.', 'flx-analytics' ) );
		}
		echo '<div class="wrap flxan"><div id="flxan-journeys" class="flxan-app" aria-busy="true">';
		echo '<div class="flxan-head"><h1>' . esc_html__( 'Journeys', 'flx-analytics' ) . '</h1></div>';
		echo '<div class="flxan-cards flxan-kpis flxan-kpis--5">';
		for ( $i = 0; $i < 5; $i++ ) {
			echo '<div class="flxan-card flxan-skel"></div>';
		}
		echo '</div><div class="flxan-panel flxan-skel flxan-skel--chart"></div>';
		echo '<noscript><p>' . esc_html__( 'This screen requires JavaScript.', 'flx-analytics' ) . '</p></noscript>';
		echo '</div></div>';
	}

	private static function notice() {
		$m   = isset( $_GET['flxan_msg'] ) ? sanitize_key( $_GET['flxan_msg'] ) : ''; // phpcs:ignore
		$n   = isset( $_GET['n'] ) ? absint( $_GET['n'] ) : 0; // phpcs:ignore
		$all = array(
			'saved'    => __( 'Settings saved.', 'flx-analytics' ),
			'reset'    => __( 'All statistics were deleted.', 'flx-analytics' ),
			/* translators: %s: satır sayısı */
			'imported' => sprintf( __( 'Koko Analytics data imported: %s rows.', 'flx-analytics' ), number_format_i18n( $n ) ),
			/* translators: %s: satır sayısı */
			'agg'      => sprintf( __( '%s visit rows processed.', 'flx-analytics' ), number_format_i18n( $n ) ),
		);
		if ( isset( $all[ $m ] ) ) {
			echo '<div class="notice notice-success is-dismissible"><p>' . esc_html( $all[ $m ] ) . '</p></div>';
		}
	}

	public static function settings_page() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You are not allowed to view this page.', 'flx-analytics' ) );
		}
		$s      = flxan_settings();
		$method = flxan_method();
		$koko   = flxan_koko_available();
		$last   = (int) get_option( 'flxan_last_agg', 0 );
		?>
		<div class="wrap flxan flxan-settings">
			<h1><?php esc_html_e( 'Site Analytics settings', 'flx-analytics' ); ?></h1>
			<?php self::notice(); ?>
			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
				<input type="hidden" name="action" value="flxan_save">
				<?php wp_nonce_field( 'flxan_save' ); ?>

				<div class="flxan-box">
					<h2><?php esc_html_e( 'Counting', 'flx-analytics' ); ?></h2>
					<table class="form-table" role="presentation">
						<tr>
							<th scope="row"><?php esc_html_e( 'Count visits', 'flx-analytics' ); ?></th>
							<td><label><input type="checkbox" name="s[on]" value="1" <?php checked( $s['on'] ); ?>> <?php esc_html_e( 'On', 'flx-analytics' ); ?></label>
								<p class="description"><?php esc_html_e( 'No IP address or browser data is stored. A visitor is recognised only within the same day by a random key that is deleted the next day. A cookie is set only for visitors who arrive via a referral link (?r=code); it holds only the code (30 days). Mention in your privacy policy that statistics are collected.', 'flx-analytics' ); ?></p></td>
						</tr>
						<tr>
							<th scope="row"><?php esc_html_e( 'Collection method', 'flx-analytics' ); ?></th>
							<td>
								<select name="s[method]">
									<option value="auto" <?php selected( $s['method'], 'auto' ); ?>><?php esc_html_e( 'Automatic (recommended)', 'flx-analytics' ); ?></option>
									<option value="fast" <?php selected( $s['method'], 'fast' ); ?>><?php esc_html_e( 'Fast endpoint (WordPress is not loaded)', 'flx-analytics' ); ?></option>
									<option value="rest" <?php selected( $s['method'], 'rest' ); ?>><?php esc_html_e( 'WordPress REST', 'flx-analytics' ); ?></option>
								</select>
								<span class="flxan-pill flxan-pill--<?php echo 'fast' === $method ? 'ok' : 'info'; ?>" id="flxan-method"><?php echo 'fast' === $method ? esc_html__( 'Now: fast endpoint', 'flx-analytics' ) : esc_html__( 'Now: REST', 'flx-analytics' ); ?></span>
								<span class="flxan-pill" id="flxan-fast-test" hidden></span>
								<p class="description"><?php esc_html_e( 'Automatic: the fast endpoint is tested when this page opens; if it fails, REST is used.', 'flx-analytics' ); ?></p>
							</td>
						</tr>
						<tr>
							<th scope="row"><?php esc_html_e( '"Do Not Track"', 'flx-analytics' ); ?></th>
							<td><label><input type="checkbox" name="s[dnt]" value="1" <?php checked( $s['dnt'] ); ?>> <?php esc_html_e( 'Do not count visitors whose browser sends "Do Not Track" or GPC', 'flx-analytics' ); ?></label></td>
						</tr>
					</table>
				</div>

				<div class="flxan-box">
					<h2><?php esc_html_e( 'Exclusions', 'flx-analytics' ); ?></h2>
					<table class="form-table" role="presentation">
						<tr>
							<th scope="row"><?php esc_html_e( 'Roles', 'flx-analytics' ); ?></th>
							<td class="flxan-roles">
								<?php foreach ( wp_roles()->get_names() as $role => $name ) : ?>
									<label><input type="checkbox" name="s[exclude_roles][]" value="<?php echo esc_attr( $role ); ?>" <?php checked( in_array( $role, (array) $s['exclude_roles'], true ) ); ?>> <?php echo esc_html( translate_user_role( $name ) ); ?></label>
								<?php endforeach; ?>
								<p class="description"><?php esc_html_e( 'Logged-in people with these roles are not counted. The browser you open this screen in is not counted either (even after logging out).', 'flx-analytics' ); ?></p>
							</td>
						</tr>
						<tr>
							<th scope="row"><label for="flxan-ips"><?php esc_html_e( 'IP addresses', 'flx-analytics' ); ?></label></th>
							<td><textarea id="flxan-ips" name="s[exclude_ips]" rows="3" class="large-text code" placeholder="85.105.1.2&#10;10.0.0.0/8&#10;192.168.1.*"><?php echo esc_textarea( $s['exclude_ips'] ); ?></textarea>
								<p class="description"><?php esc_html_e( 'One address per line, "*" or CIDR (e.g. office network).', 'flx-analytics' ); ?></p></td>
						</tr>
					</table>
				</div>

				<div class="flxan-box">
					<h2><?php esc_html_e( 'Events', 'flx-analytics' ); ?></h2>
					<table class="form-table" role="presentation">
						<tr>
							<th scope="row"><?php esc_html_e( 'Tracked automatically', 'flx-analytics' ); ?></th>
							<td class="flxan-roles">
								<label><input type="checkbox" name="s[ev_outbound]" value="1" <?php checked( $s['ev_outbound'] ); ?>> <?php esc_html_e( 'Outbound link clicks', 'flx-analytics' ); ?></label>
								<label><input type="checkbox" name="s[ev_download]" value="1" <?php checked( $s['ev_download'] ); ?>> <?php esc_html_e( 'File downloads (PDF, ZIP, DOCX…)', 'flx-analytics' ); ?></label>
								<label><input type="checkbox" name="s[ev_contact]" value="1" <?php checked( $s['ev_contact'] ); ?>> <?php esc_html_e( 'Phone, email and WhatsApp clicks', 'flx-analytics' ); ?></label>
								<label><input type="checkbox" name="s[ev_search]" value="1" <?php checked( $s['ev_search'] ); ?>> <?php esc_html_e( 'Site searches (search term)', 'flx-analytics' ); ?></label>
								<label><input type="checkbox" name="s[ev_donation]" value="1" <?php checked( $s['ev_donation'] ); ?>> <?php esc_html_e( 'Paid donations and orders (WooCommerce; type and amount)', 'flx-analytics' ); ?></label>
								<p class="description"><?php esc_html_e( 'Your own event: add data-flxan-event="name" (and optional data-flxan-param) to a link, or call flxan.track("name", "detail") in JavaScript. PHP: flxan_track_event( "name", "detail", amount ).', 'flx-analytics' ); ?></p>
							</td>
						</tr>
					</table>
				</div>

				<div class="flxan-box" id="flxan-funnels">
					<h2><?php esc_html_e( 'Funnels', 'flx-analytics' ); ?></h2>
					<p class="description"><?php esc_html_e( 'Shows how far visitors get. Each step can be a page (all pages starting with that path), an event (e.g. the "Pay" click) or the site entry. Up to 5 funnels with 10 steps each. Changes apply to new visits; funnel steps can also be picked as goals on the Journeys screen.', 'flx-analytics' ); ?></p>
					<div id="flxan-fed" class="flxan-fed"></div>
					<textarea name="funnels" rows="9" class="large-text code flxan-ftext"><?php echo esc_textarea( flxan_funnels_to_text( flxan_funnels() ) ); ?></textarea>
				</div>

				<div class="flxan-box">
					<h2><?php esc_html_e( 'Display and retention', 'flx-analytics' ); ?></h2>
					<table class="form-table" role="presentation">
						<tr>
							<th scope="row"><?php esc_html_e( 'Admin screens', 'flx-analytics' ); ?></th>
							<td class="flxan-roles">
								<label><input type="checkbox" name="s[posts_column]" value="1" <?php checked( $s['posts_column'] ); ?>> <?php esc_html_e( '"Views" column in the post and page lists', 'flx-analytics' ); ?></label>
								<label><input type="checkbox" name="s[adminbar]" value="1" <?php checked( $s['adminbar'] ); ?>> <?php esc_html_e( 'Page views in the admin bar while browsing the site', 'flx-analytics' ); ?></label>
							</td>
						</tr>
						<tr>
							<th scope="row"><label for="flxan-keep"><?php esc_html_e( 'Retention', 'flx-analytics' ); ?></label></th>
							<td><input id="flxan-keep" type="number" min="0" max="120" name="s[keep_months]" value="<?php echo esc_attr( $s['keep_months'] ); ?>" class="small-text"> <?php esc_html_e( 'months (0 = unlimited)', 'flx-analytics' ); ?>
								<p class="description"><?php esc_html_e( 'Daily summaries take little space; keeping them forever is usually fine.', 'flx-analytics' ); ?></p></td>
						</tr>
					</table>
				</div>
				<div class="flxan-box" id="flxan-tm">
					<h2><?php esc_html_e( 'Telemetry hub', 'flx-analytics' ); ?></h2>
					<table class="form-table" role="presentation">
						<tr>
							<th scope="row"><?php esc_html_e( 'Receive data from FluxWP installs', 'flx-analytics' ); ?></th>
							<td><label><input type="checkbox" name="s[tm_hub]" value="1" <?php checked( $s['tm_hub'] ); ?>> <?php esc_html_e( 'On', 'flx-analytics' ); ?></label>
								<p class="description"><?php esc_html_e( 'Sites that tick "Help us improve the product" in FluxWP Panel send anonymous usage data once a day: which FluxWP plugins are used together, versions, FluxWP PHP errors and setup-wizard progress. IP and site address are not stored; records are kept for 90 days. When enabled, "Product telemetry" appears in the menu.', 'flx-analytics' ); ?></p>
								<?php if ( class_exists( 'FLXAN_Telemetry_Hub' ) ) : ?>
									<p><?php esc_html_e( 'Collection URL (FluxWP Panel › Settings › Telemetry URL):', 'flx-analytics' ); ?> <code><?php echo esc_html( FLXAN_Telemetry_Hub::endpoint() ); ?></code></p>
								<?php endif; ?>
							</td>
						</tr>
					</table>
				</div>
				<?php submit_button(); ?>
			</form>

			<div class="flxan-box">
				<h2><?php esc_html_e( 'Data', 'flx-analytics' ); ?></h2>
				<p>
					<?php
					echo esc_html(
						$last
							/* translators: 1: geçen süre, 2: bekleyen veri boyutu */
							? sprintf( __( 'Last processed %1$s. Waiting in queue: %2$s.', 'flx-analytics' ), flx_time_ago( $last ), size_format( FLXAN_Aggregator::pending_bytes() ) )
							: __( 'No visits processed yet.', 'flx-analytics' )
					);
					?>
				</p>
				<p class="flxan-actions">
					<a class="button" href="<?php echo esc_url( wp_nonce_url( admin_url( 'admin-post.php?action=flxan_aggregate' ), 'flxan_aggregate' ) ); ?>"><?php esc_html_e( 'Process now', 'flx-analytics' ); ?></a>
					<a class="button" href="<?php echo esc_url( wp_nonce_url( admin_url( 'admin-post.php?action=flxan_export&type=site&range=all' ), 'flxan_export' ) ); ?>"><?php esc_html_e( 'Download daily totals (CSV)', 'flx-analytics' ); ?></a>
					<a class="button" href="<?php echo esc_url( wp_nonce_url( admin_url( 'admin-post.php?action=flxan_export&type=pages&range=all' ), 'flxan_export' ) ); ?>"><?php esc_html_e( 'Download pages (CSV)', 'flx-analytics' ); ?></a>
				</p>
				<?php if ( $koko ) : ?>
					<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="flxan-inline">
						<input type="hidden" name="action" value="flxan_import_koko">
						<?php wp_nonce_field( 'flxan_import_koko' ); ?>
						<p>
							<?php
							/* translators: 1: gün sayısı, 2: ilk tarih, 3: son tarih */
							echo esc_html( sprintf( __( 'Koko Analytics data found: %1$s days (%2$s – %3$s). Days before Flux Analytics was installed are imported; Koko tables are not modified.', 'flx-analytics' ), number_format_i18n( $koko['days'] ), $koko['first'], $koko['last'] ) );
							?>
						</p>
						<button class="button button-secondary"><?php esc_html_e( 'Import Koko Analytics data', 'flx-analytics' ); ?></button>
					</form>
				<?php endif; ?>
				<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="flxan-inline flxan-danger" onsubmit="return confirm(this.dataset.confirm)" data-confirm="<?php esc_attr_e( 'All statistics will be permanently deleted. Are you sure?', 'flx-analytics' ); ?>">
					<input type="hidden" name="action" value="flxan_reset">
					<?php wp_nonce_field( 'flxan_reset' ); ?>
					<button class="button button-link-delete"><?php esc_html_e( 'Delete all statistics', 'flx-analytics' ); ?></button>
				</form>
			</div>

			<div class="flxan-box">
				<h2><?php esc_html_e( 'Shortcodes and AI', 'flx-analytics' ); ?></h2>
				<p><code>[flx_analiz_populer sayi="5" gun="30" tur="post" baslik="En çok okunanlar"]</code> — <?php esc_html_e( 'list of the most viewed posts.', 'flx-analytics' ); ?></p>
				<p><code>[flx_analiz_sayac]</code> — <?php esc_html_e( 'total views of this page (gun="30" for the last 30 days).', 'flx-analytics' ); ?></p>
				<p><?php esc_html_e( 'Flux AI chat and MCP clients (Claude and others) read stats with: flxan_summary, flxan_timeseries, flxan_pages, flxan_referrers, flxan_breakdown, flxan_events, flxan_realtime, flxan_page, flxan_compare, flxan_funnel, flxan_journeys, flxan_refs, flxan_ref_save. Access needs the "View statistics" capability (flxan_view), which Flux Roles can grant.', 'flx-analytics' ); ?></p>
			</div>
		</div>
		<?php
	}

	public static function save() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You are not allowed to do this.', 'flx-analytics' ) );
		}
		check_admin_referer( 'flxan_save' );
		$in = isset( $_POST['s'] ) && is_array( $_POST['s'] ) ? wp_unslash( $_POST['s'] ) : array(); // phpcs:ignore
		update_option( 'flxan_settings', flxan_sanitize_settings( $in ), false );
		flxan_settings( 'flush' );
		if ( isset( $_POST['funnels_json'] ) || isset( $_POST['funnels'] ) ) { // phpcs:ignore
			$def = flxan_funnels_to_text( flxan_default_funnels() );
			if ( isset( $_POST['funnels_json'] ) ) { // phpcs:ignore
				$f = flxan_funnels_sanitize( json_decode( (string) wp_unslash( $_POST['funnels_json'] ), true ) ); // phpcs:ignore
			} else {
				$text = trim( (string) wp_unslash( $_POST['funnels'] ) ); // phpcs:ignore
				$f    = '' === $text ? array() : flxan_funnels_from_text( $text );
			}
			if ( ! $f || trim( flxan_funnels_to_text( $f ) ) === trim( $def ) ) {
				delete_option( 'flxan_funnels' );
			} else {
				update_option( 'flxan_funnels', $f, false );
			}
		}
		flxan_write_config();
		wp_safe_redirect( self::url( 'settings', array( 'flxan_msg' => 'saved' ) ) );
		exit;
	}

	public static function aggregate() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You are not allowed to do this.', 'flx-analytics' ) );
		}
		check_admin_referer( 'flxan_aggregate' );
		$n = max( 0, FLXAN_Aggregator::run( 25 ) );
		wp_safe_redirect( self::url( 'settings', array( 'flxan_msg' => 'agg', 'n' => $n ) ) );
		exit;
	}

	public static function reset() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You are not allowed to do this.', 'flx-analytics' ) );
		}
		check_admin_referer( 'flxan_reset' );
		FLXAN_Aggregator::reset();
		wp_safe_redirect( self::url( 'settings', array( 'flxan_msg' => 'reset' ) ) );
		exit;
	}

	public static function import_koko() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You are not allowed to do this.', 'flx-analytics' ) );
		}
		check_admin_referer( 'flxan_import_koko' );
		$r = flxan_koko_import();
		wp_safe_redirect( self::url( 'settings', array( 'flxan_msg' => 'imported', 'n' => array_sum( $r ) ) ) );
		exit;
	}

	/** CSV: type = site | pages | referrers | events | dim (kind). */
	public static function export() {
		if ( ! flxan_can_view() ) {
			wp_die( esc_html__( 'You are not allowed to do this.', 'flx-analytics' ) );
		}
		check_admin_referer( 'flxan_export' );
		$g    = FLXAN_Stats::range( sanitize_key( $_GET['range'] ?? '30d' ), sanitize_text_field( wp_unslash( $_GET['start'] ?? '' ) ), sanitize_text_field( wp_unslash( $_GET['end'] ?? '' ) ) ); // phpcs:ignore
		$type = sanitize_key( $_GET['type'] ?? 'site' ); // phpcs:ignore
		$kind = sanitize_key( $_GET['kind'] ?? '' ); // phpcs:ignore
		$rows = array();
		switch ( $type ) {
			case 'pages':
				$rows[] = array( __( 'Address', 'flx-analytics' ), __( 'Title', 'flx-analytics' ), __( 'Visitors', 'flx-analytics' ), __( 'Views', 'flx-analytics' ) );
				foreach ( FLXAN_Stats::pages( $g['start'], $g['end'], 100000 )['items'] as $r ) {
					$rows[] = array( $r['path'], $r['title'], $r['visitors'], $r['pageviews'] );
				}
				break;
			case 'referrers':
				$rows[] = array( __( 'Site', 'flx-analytics' ), __( 'Channel', 'flx-analytics' ), __( 'Visitors', 'flx-analytics' ), __( 'Views', 'flx-analytics' ) );
				foreach ( FLXAN_Stats::referrers( $g['start'], $g['end'], 100000 )['items'] as $r ) {
					$rows[] = array( $r['host'], flxan_dim_label( 'channel', $r['channel'] ), $r['visitors'], $r['pageviews'] );
				}
				break;
			case 'events':
				$rows[] = array( __( 'Event', 'flx-analytics' ), __( 'Detail', 'flx-analytics' ), __( 'Count', 'flx-analytics' ), __( 'Unique', 'flx-analytics' ), __( 'Amount', 'flx-analytics' ) );
				foreach ( FLXAN_Stats::events( $g['start'], $g['end'], '', 100000 )['items'] as $r ) {
					$rows[] = array( $r['label'], $r['param'], $r['hits'], $r['uniques'], $r['amount'] );
				}
				break;
			case 'dim':
				$rows[] = array( FLXAN_Stats::kinds()[ $kind ] ?? $kind, __( 'Visitors', 'flx-analytics' ), __( 'Views', 'flx-analytics' ) );
				foreach ( FLXAN_Stats::dim( $kind, $g['start'], $g['end'], 100000 )['items'] as $r ) {
					$rows[] = array( $r['label'], $r['visitors'], $r['pageviews'] );
				}
				break;
			default:
				$type   = 'site';
				$rows[] = array( __( 'Date', 'flx-analytics' ), __( 'Visitors', 'flx-analytics' ), __( 'Views', 'flx-analytics' ) );
				foreach ( FLXAN_Stats::series( $g['start'], $g['end'], 'day' )['items'] as $r ) {
					$rows[] = array( $r['d'], $r['visitors'], $r['pageviews'] );
				}
		}
		flx_csv_stream(
			'flx-analytics-' . $type . '-' . $g['start'] . '_' . $g['end'] . '.csv',
			(array) array_shift( $rows ),
			$rows,
			array( 'delimiter' => ';' )
		);
		exit;
	}
}
FLXAN_Admin::init();
