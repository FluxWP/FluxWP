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
		add_menu_page( __( 'Site Analitik', 'flx-analytics' ), __( 'Site Analitik', 'flx-analytics' ), $cap, self::SLUG, array( __CLASS__, 'page' ), 'none', 3 );
		add_submenu_page( self::SLUG, __( 'İstatistikler', 'flx-analytics' ), __( 'İstatistikler', 'flx-analytics' ), $cap, self::SLUG, array( __CLASS__, 'page' ) );
		add_submenu_page( self::SLUG, __( 'Yolculuklar', 'flx-analytics' ), __( 'Yolculuklar', 'flx-analytics' ), $cap, self::SLUG . '-journeys', array( __CLASS__, 'journeys_page' ) );
		add_submenu_page( self::SLUG, __( 'Site Analitik ayarları', 'flx-analytics' ), __( 'Ayarlar', 'flx-analytics' ), 'manage_options', self::SLUG . '-settings', array( __CLASS__, 'settings_page' ) );
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
			wp_die( esc_html__( 'Bu sayfayı görme yetkiniz yok.', 'flx-analytics' ) );
		}
		echo '<div class="wrap flxan"><div id="flxan-app" class="flxan-app" aria-busy="true">';
		echo '<div class="flxan-head"><h1>' . esc_html__( 'İstatistikler', 'flx-analytics' ) . '</h1></div>';
		echo '<div class="flxan-cards flxan-kpis">';
		for ( $i = 0; $i < 6; $i++ ) {
			echo '<div class="flxan-card flxan-skel"></div>';
		}
		echo '</div><div class="flxan-panel flxan-skel flxan-skel--chart"></div>';
		echo '<noscript><p>' . esc_html__( 'Bu ekran için JavaScript gerekli.', 'flx-analytics' ) . '</p></noscript>';
		echo '</div></div>';
	}

	/** Yolculuklar: iskelet; içerik journeys.js ile gelir. */
	public static function journeys_page() {
		if ( ! flxan_can_view() ) {
			wp_die( esc_html__( 'Bu sayfayı görme yetkiniz yok.', 'flx-analytics' ) );
		}
		echo '<div class="wrap flxan"><div id="flxan-journeys" class="flxan-app" aria-busy="true">';
		echo '<div class="flxan-head"><h1>' . esc_html__( 'Yolculuklar', 'flx-analytics' ) . '</h1></div>';
		echo '<div class="flxan-cards flxan-kpis flxan-kpis--5">';
		for ( $i = 0; $i < 5; $i++ ) {
			echo '<div class="flxan-card flxan-skel"></div>';
		}
		echo '</div><div class="flxan-panel flxan-skel flxan-skel--chart"></div>';
		echo '<noscript><p>' . esc_html__( 'Bu ekran için JavaScript gerekli.', 'flx-analytics' ) . '</p></noscript>';
		echo '</div></div>';
	}

	private static function notice() {
		$m   = isset( $_GET['flxan_msg'] ) ? sanitize_key( $_GET['flxan_msg'] ) : ''; // phpcs:ignore
		$n   = isset( $_GET['n'] ) ? absint( $_GET['n'] ) : 0; // phpcs:ignore
		$all = array(
			'saved'    => __( 'Ayarlar kaydedildi.', 'flx-analytics' ),
			'reset'    => __( 'Tüm istatistikler silindi.', 'flx-analytics' ),
			/* translators: %s: satır sayısı */
			'imported' => sprintf( __( 'Koko Analytics verisi aktarıldı: %s satır.', 'flx-analytics' ), number_format_i18n( $n ) ),
			/* translators: %s: satır sayısı */
			'agg'      => sprintf( __( '%s ziyaret satırı işlendi.', 'flx-analytics' ), number_format_i18n( $n ) ),
		);
		if ( isset( $all[ $m ] ) ) {
			echo '<div class="notice notice-success is-dismissible"><p>' . esc_html( $all[ $m ] ) . '</p></div>';
		}
	}

	public static function settings_page() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'Bu sayfayı görme yetkiniz yok.', 'flx-analytics' ) );
		}
		$s      = flxan_settings();
		$method = flxan_method();
		$koko   = flxan_koko_available();
		$last   = (int) get_option( 'flxan_last_agg', 0 );
		?>
		<div class="wrap flxan flxan-settings">
			<h1><?php esc_html_e( 'Site Analitik ayarları', 'flx-analytics' ); ?></h1>
			<?php self::notice(); ?>
			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
				<input type="hidden" name="action" value="flxan_save">
				<?php wp_nonce_field( 'flxan_save' ); ?>

				<div class="flxan-box">
					<h2><?php esc_html_e( 'Sayım', 'flx-analytics' ); ?></h2>
					<table class="form-table" role="presentation">
						<tr>
							<th scope="row"><?php esc_html_e( 'Ziyaretleri say', 'flx-analytics' ); ?></th>
							<td><label><input type="checkbox" name="s[on]" value="1" <?php checked( $s['on'] ); ?>> <?php esc_html_e( 'Açık', 'flx-analytics' ); ?></label>
								<p class="description"><?php esc_html_e( 'IP ve tarayıcı bilgisi saklanmaz. Ziyaretçi yalnız aynı gün içinde, ertesi gün silinen rastgele bir anahtarla tanınır. Çerez yalnız referans bağlantısıyla (?r=kod) gelen ziyaretçiye konur ve yalnız kodu tutar (30 gün); gizlilik metninde istatistik tutulduğunu belirtin.', 'flx-analytics' ); ?></p></td>
						</tr>
						<tr>
							<th scope="row"><?php esc_html_e( 'Toplama yöntemi', 'flx-analytics' ); ?></th>
							<td>
								<select name="s[method]">
									<option value="auto" <?php selected( $s['method'], 'auto' ); ?>><?php esc_html_e( 'Otomatik (önerilir)', 'flx-analytics' ); ?></option>
									<option value="fast" <?php selected( $s['method'], 'fast' ); ?>><?php esc_html_e( 'Hızlı uç (WordPress yüklenmez)', 'flx-analytics' ); ?></option>
									<option value="rest" <?php selected( $s['method'], 'rest' ); ?>><?php esc_html_e( 'WordPress REST', 'flx-analytics' ); ?></option>
								</select>
								<span class="flxan-pill flxan-pill--<?php echo 'fast' === $method ? 'ok' : 'info'; ?>" id="flxan-method"><?php echo 'fast' === $method ? esc_html__( 'Şu an: hızlı uç', 'flx-analytics' ) : esc_html__( 'Şu an: REST', 'flx-analytics' ); ?></span>
								<span class="flxan-pill" id="flxan-fast-test" hidden></span>
								<p class="description"><?php esc_html_e( 'Otomatik: hızlı uç bu sayfa açılınca test edilir; çalışmazsa REST kullanılır.', 'flx-analytics' ); ?></p>
							</td>
						</tr>
						<tr>
							<th scope="row"><?php esc_html_e( '"Do Not Track"', 'flx-analytics' ); ?></th>
							<td><label><input type="checkbox" name="s[dnt]" value="1" <?php checked( $s['dnt'] ); ?>> <?php esc_html_e( 'Tarayıcısı "izleme" ya da GPC gönderen ziyaretçileri sayma', 'flx-analytics' ); ?></label></td>
						</tr>
					</table>
				</div>

				<div class="flxan-box">
					<h2><?php esc_html_e( 'Sayılmayacaklar', 'flx-analytics' ); ?></h2>
					<table class="form-table" role="presentation">
						<tr>
							<th scope="row"><?php esc_html_e( 'Roller', 'flx-analytics' ); ?></th>
							<td class="flxan-roles">
								<?php foreach ( wp_roles()->get_names() as $role => $name ) : ?>
									<label><input type="checkbox" name="s[exclude_roles][]" value="<?php echo esc_attr( $role ); ?>" <?php checked( in_array( $role, (array) $s['exclude_roles'], true ) ); ?>> <?php echo esc_html( translate_user_role( $name ) ); ?></label>
								<?php endforeach; ?>
								<p class="description"><?php esc_html_e( 'Bu rollerdeki kişiler giriş yapmışken sayılmaz. Bu ekranı açtığınız tarayıcı da (çıkış yapsanız bile) sayılmaz.', 'flx-analytics' ); ?></p>
							</td>
						</tr>
						<tr>
							<th scope="row"><label for="flxan-ips"><?php esc_html_e( 'IP adresleri', 'flx-analytics' ); ?></label></th>
							<td><textarea id="flxan-ips" name="s[exclude_ips]" rows="3" class="large-text code" placeholder="85.105.1.2&#10;10.0.0.0/8&#10;192.168.1.*"><?php echo esc_textarea( $s['exclude_ips'] ); ?></textarea>
								<p class="description"><?php esc_html_e( 'Satır başına bir adres, "*" ya da CIDR (ör. ofis ağı).', 'flx-analytics' ); ?></p></td>
						</tr>
					</table>
				</div>

				<div class="flxan-box">
					<h2><?php esc_html_e( 'Olaylar', 'flx-analytics' ); ?></h2>
					<table class="form-table" role="presentation">
						<tr>
							<th scope="row"><?php esc_html_e( 'Kendiliğinden izlenenler', 'flx-analytics' ); ?></th>
							<td class="flxan-roles">
								<label><input type="checkbox" name="s[ev_outbound]" value="1" <?php checked( $s['ev_outbound'] ); ?>> <?php esc_html_e( 'Dış bağlantı tıklamaları', 'flx-analytics' ); ?></label>
								<label><input type="checkbox" name="s[ev_download]" value="1" <?php checked( $s['ev_download'] ); ?>> <?php esc_html_e( 'Dosya indirmeleri (PDF, ZIP, DOCX…)', 'flx-analytics' ); ?></label>
								<label><input type="checkbox" name="s[ev_contact]" value="1" <?php checked( $s['ev_contact'] ); ?>> <?php esc_html_e( 'Telefon, e-posta ve WhatsApp tıklamaları', 'flx-analytics' ); ?></label>
								<label><input type="checkbox" name="s[ev_search]" value="1" <?php checked( $s['ev_search'] ); ?>> <?php esc_html_e( 'Site içi aramalar (aranan kelime)', 'flx-analytics' ); ?></label>
								<label><input type="checkbox" name="s[ev_donation]" value="1" <?php checked( $s['ev_donation'] ); ?>> <?php esc_html_e( 'Ödenen bağış ve siparişler (WooCommerce; tür ve tutar)', 'flx-analytics' ); ?></label>
								<p class="description"><?php esc_html_e( 'Kendi olayınız: bağlantıya data-flxan-event="ad" (ve isteğe bağlı data-flxan-param) ekleyin ya da JavaScript\'te flxan.track("ad", "ayrıntı") çağırın. PHP: flxan_track_event( "ad", "ayrıntı", tutar ).', 'flx-analytics' ); ?></p>
							</td>
						</tr>
					</table>
				</div>

				<div class="flxan-box" id="flxan-funnels">
					<h2><?php esc_html_e( 'Huniler', 'flx-analytics' ); ?></h2>
					<p class="description"><?php esc_html_e( 'Ziyaretçinin hangi adıma kadar geldiğini gösterir. Her adım bir sayfa (o yolla başlayan sayfalar), bir olay (ör. "Ödeme yap" tıklaması) ya da siteye giriş olabilir. En fazla 5 huni, her birinde 10 adım. Değişiklik yeni ziyaretlerden itibaren işler; huni adımları Yolculuklar ekranında hedef olarak da seçilir.', 'flx-analytics' ); ?></p>
					<div id="flxan-fed" class="flxan-fed"></div>
					<textarea name="funnels" rows="9" class="large-text code flxan-ftext"><?php echo esc_textarea( flxan_funnels_to_text( flxan_funnels() ) ); ?></textarea>
				</div>

				<div class="flxan-box">
					<h2><?php esc_html_e( 'Görünüm ve saklama', 'flx-analytics' ); ?></h2>
					<table class="form-table" role="presentation">
						<tr>
							<th scope="row"><?php esc_html_e( 'Yönetim ekranları', 'flx-analytics' ); ?></th>
							<td class="flxan-roles">
								<label><input type="checkbox" name="s[posts_column]" value="1" <?php checked( $s['posts_column'] ); ?>> <?php esc_html_e( 'Yazı ve sayfa listesinde "Görüntülenme" sütunu', 'flx-analytics' ); ?></label>
								<label><input type="checkbox" name="s[adminbar]" value="1" <?php checked( $s['adminbar'] ); ?>> <?php esc_html_e( 'Sitede gezerken yönetim çubuğunda sayfanın görüntülenmesi', 'flx-analytics' ); ?></label>
							</td>
						</tr>
						<tr>
							<th scope="row"><label for="flxan-keep"><?php esc_html_e( 'Saklama süresi', 'flx-analytics' ); ?></label></th>
							<td><input id="flxan-keep" type="number" min="0" max="120" name="s[keep_months]" value="<?php echo esc_attr( $s['keep_months'] ); ?>" class="small-text"> <?php esc_html_e( 'ay (0 = sınırsız)', 'flx-analytics' ); ?>
								<p class="description"><?php esc_html_e( 'Günlük özetler çok yer tutmaz; sınırsız bırakmak genelde sorun değildir.', 'flx-analytics' ); ?></p></td>
						</tr>
					</table>
				</div>
				<div class="flxan-box" id="flxan-tm">
					<h2><?php esc_html_e( 'Telemetri merkezi', 'flx-analytics' ); ?></h2>
					<table class="form-table" role="presentation">
						<tr>
							<th scope="row"><?php esc_html_e( 'FluxWP kurulumlarından veri al', 'flx-analytics' ); ?></th>
							<td><label><input type="checkbox" name="s[tm_hub]" value="1" <?php checked( $s['tm_hub'] ); ?>> <?php esc_html_e( 'Açık', 'flx-analytics' ); ?></label>
								<p class="description"><?php esc_html_e( 'FluxWP Panel\'de "Ürünü geliştirmemize yardımcı olun" kutusunu işaretleyen siteler günde bir kez anonim kullanım verisi gönderir: hangi FluxWP eklentilerinin birlikte kullanıldığı, sürümler, FluxWP kaynaklı PHP hataları ve kurulum sihirbazında ilerleme. IP ve site adresi saklanmaz, kayıtlar 90 gün tutulur. Açınca sol menüde "Ürün telemetrisi" görünür.', 'flx-analytics' ); ?></p>
								<?php if ( class_exists( 'FLXAN_Telemetry_Hub' ) ) : ?>
									<p><?php esc_html_e( 'Toplama adresi (FluxWP Panel › Ayarlar › Telemetri adresi):', 'flx-analytics' ); ?> <code><?php echo esc_html( FLXAN_Telemetry_Hub::endpoint() ); ?></code></p>
								<?php endif; ?>
							</td>
						</tr>
					</table>
				</div>
				<?php submit_button(); ?>
			</form>

			<div class="flxan-box">
				<h2><?php esc_html_e( 'Veri', 'flx-analytics' ); ?></h2>
				<p>
					<?php
					echo esc_html(
						$last
							/* translators: 1: geçen süre, 2: bekleyen veri boyutu */
							? sprintf( __( 'Son işleme %1$s önce. Kuyrukta bekleyen: %2$s.', 'flx-analytics' ), human_time_diff( $last ), size_format( FLXAN_Aggregator::pending_bytes() ) )
							: __( 'Henüz işlenen ziyaret yok.', 'flx-analytics' )
					);
					?>
				</p>
				<p class="flxan-actions">
					<a class="button" href="<?php echo esc_url( wp_nonce_url( admin_url( 'admin-post.php?action=flxan_aggregate' ), 'flxan_aggregate' ) ); ?>"><?php esc_html_e( 'Şimdi işle', 'flx-analytics' ); ?></a>
					<a class="button" href="<?php echo esc_url( wp_nonce_url( admin_url( 'admin-post.php?action=flxan_export&type=site&range=all' ), 'flxan_export' ) ); ?>"><?php esc_html_e( 'Günlük toplamları indir (CSV)', 'flx-analytics' ); ?></a>
					<a class="button" href="<?php echo esc_url( wp_nonce_url( admin_url( 'admin-post.php?action=flxan_export&type=pages&range=all' ), 'flxan_export' ) ); ?>"><?php esc_html_e( 'Sayfaları indir (CSV)', 'flx-analytics' ); ?></a>
				</p>
				<?php if ( $koko ) : ?>
					<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="flxan-inline">
						<input type="hidden" name="action" value="flxan_import_koko">
						<?php wp_nonce_field( 'flxan_import_koko' ); ?>
						<p>
							<?php
							/* translators: 1: gün sayısı, 2: ilk tarih, 3: son tarih */
							echo esc_html( sprintf( __( 'Koko Analytics verisi bulundu: %1$s gün (%2$s – %3$s). Site Analitik\'in kurulduğu günden önceki günler aktarılır; Koko tablolarına dokunulmaz.', 'flx-analytics' ), number_format_i18n( $koko['days'] ), $koko['first'], $koko['last'] ) );
							?>
						</p>
						<button class="button button-secondary"><?php esc_html_e( 'Koko Analytics verisini aktar', 'flx-analytics' ); ?></button>
					</form>
				<?php endif; ?>
				<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="flxan-inline flxan-danger" onsubmit="return confirm(this.dataset.confirm)" data-confirm="<?php esc_attr_e( 'Tüm istatistikler kalıcı olarak silinecek. Emin misiniz?', 'flx-analytics' ); ?>">
					<input type="hidden" name="action" value="flxan_reset">
					<?php wp_nonce_field( 'flxan_reset' ); ?>
					<button class="button button-link-delete"><?php esc_html_e( 'Tüm istatistikleri sil', 'flx-analytics' ); ?></button>
				</form>
			</div>

			<div class="flxan-box">
				<h2><?php esc_html_e( 'Kısa kodlar ve yapay zekâ', 'flx-analytics' ); ?></h2>
				<p><code>[flx_analiz_populer sayi="5" gun="30" tur="post" baslik="En çok okunanlar"]</code> — <?php esc_html_e( 'en çok görüntülenen yazılar listesi.', 'flx-analytics' ); ?></p>
				<p><code>[flx_analiz_sayac]</code> — <?php esc_html_e( 'bu sayfanın toplam görüntülenmesi (gun="30" ile son 30 gün).', 'flx-analytics' ); ?></p>
				<p><?php esc_html_e( 'Flux AI sohbeti ve MCP istemcileri (Claude vb.) şu araçlarla istatistikleri okur: flxan_summary, flxan_timeseries, flxan_pages, flxan_referrers, flxan_breakdown, flxan_events, flxan_realtime, flxan_page, flxan_compare, flxan_funnel, flxan_journeys, flxan_refs, flxan_ref_save. Erişim için "İstatistikleri görüntüle" (flxan_view) yetkisi gerekir; Flux Roles\'den verilebilir.', 'flx-analytics' ); ?></p>
			</div>
		</div>
		<?php
	}

	public static function save() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'Yetkiniz yok.', 'flx-analytics' ) );
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
			wp_die( esc_html__( 'Yetkiniz yok.', 'flx-analytics' ) );
		}
		check_admin_referer( 'flxan_aggregate' );
		$n = max( 0, FLXAN_Aggregator::run( 25 ) );
		wp_safe_redirect( self::url( 'settings', array( 'flxan_msg' => 'agg', 'n' => $n ) ) );
		exit;
	}

	public static function reset() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'Yetkiniz yok.', 'flx-analytics' ) );
		}
		check_admin_referer( 'flxan_reset' );
		FLXAN_Aggregator::reset();
		wp_safe_redirect( self::url( 'settings', array( 'flxan_msg' => 'reset' ) ) );
		exit;
	}

	public static function import_koko() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'Yetkiniz yok.', 'flx-analytics' ) );
		}
		check_admin_referer( 'flxan_import_koko' );
		$r = flxan_koko_import();
		wp_safe_redirect( self::url( 'settings', array( 'flxan_msg' => 'imported', 'n' => array_sum( $r ) ) ) );
		exit;
	}

	/** CSV: type = site | pages | referrers | events | dim (kind). */
	public static function export() {
		if ( ! flxan_can_view() ) {
			wp_die( esc_html__( 'Yetkiniz yok.', 'flx-analytics' ) );
		}
		check_admin_referer( 'flxan_export' );
		$g    = FLXAN_Stats::range( sanitize_key( $_GET['range'] ?? '30d' ), sanitize_text_field( wp_unslash( $_GET['start'] ?? '' ) ), sanitize_text_field( wp_unslash( $_GET['end'] ?? '' ) ) ); // phpcs:ignore
		$type = sanitize_key( $_GET['type'] ?? 'site' ); // phpcs:ignore
		$kind = sanitize_key( $_GET['kind'] ?? '' ); // phpcs:ignore
		$rows = array();
		switch ( $type ) {
			case 'pages':
				$rows[] = array( __( 'Adres', 'flx-analytics' ), __( 'Başlık', 'flx-analytics' ), __( 'Ziyaretçi', 'flx-analytics' ), __( 'Görüntülenme', 'flx-analytics' ) );
				foreach ( FLXAN_Stats::pages( $g['start'], $g['end'], 100000 )['items'] as $r ) {
					$rows[] = array( $r['path'], $r['title'], $r['visitors'], $r['pageviews'] );
				}
				break;
			case 'referrers':
				$rows[] = array( __( 'Site', 'flx-analytics' ), __( 'Kanal', 'flx-analytics' ), __( 'Ziyaretçi', 'flx-analytics' ), __( 'Görüntülenme', 'flx-analytics' ) );
				foreach ( FLXAN_Stats::referrers( $g['start'], $g['end'], 100000 )['items'] as $r ) {
					$rows[] = array( $r['host'], flxan_dim_label( 'channel', $r['channel'] ), $r['visitors'], $r['pageviews'] );
				}
				break;
			case 'events':
				$rows[] = array( __( 'Olay', 'flx-analytics' ), __( 'Ayrıntı', 'flx-analytics' ), __( 'Sayı', 'flx-analytics' ), __( 'Tekil', 'flx-analytics' ), __( 'Tutar', 'flx-analytics' ) );
				foreach ( FLXAN_Stats::events( $g['start'], $g['end'], '', 100000 )['items'] as $r ) {
					$rows[] = array( $r['label'], $r['param'], $r['hits'], $r['uniques'], $r['amount'] );
				}
				break;
			case 'dim':
				$rows[] = array( FLXAN_Stats::kinds()[ $kind ] ?? $kind, __( 'Ziyaretçi', 'flx-analytics' ), __( 'Görüntülenme', 'flx-analytics' ) );
				foreach ( FLXAN_Stats::dim( $kind, $g['start'], $g['end'], 100000 )['items'] as $r ) {
					$rows[] = array( $r['label'], $r['visitors'], $r['pageviews'] );
				}
				break;
			default:
				$type   = 'site';
				$rows[] = array( __( 'Tarih', 'flx-analytics' ), __( 'Ziyaretçi', 'flx-analytics' ), __( 'Görüntülenme', 'flx-analytics' ) );
				foreach ( FLXAN_Stats::series( $g['start'], $g['end'], 'day' )['items'] as $r ) {
					$rows[] = array( $r['d'], $r['visitors'], $r['pageviews'] );
				}
		}
		nocache_headers();
		header( 'Content-Type: text/csv; charset=utf-8' );
		header( 'Content-Disposition: attachment; filename="flx-analytics-' . $type . '-' . $g['start'] . '_' . $g['end'] . '.csv"' );
		$out = fopen( 'php://output', 'w' );
		fwrite( $out, "\xEF\xBB\xBF" ); // Excel UTF-8
		foreach ( $rows as $r ) {
			fputcsv( $out, array_map(
				function ( $v ) {
					$v = (string) $v;
					return preg_match( '/^[=+\-@]/', $v ) ? "'" . $v : $v; // CSV formül enjeksiyonu
				},
				$r
			), ';' );
		}
		fclose( $out ); // phpcs:ignore
		exit;
	}
}
FLXAN_Admin::init();
