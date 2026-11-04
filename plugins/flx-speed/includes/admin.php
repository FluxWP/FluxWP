<?php
/**
 * Yönetim ekranı: Özet, Kayıtlar, Sunucu, Veritabanı, Autoload, Cron, Sistem Kontrolü, Ayarlar ve tek kayıt ayrıntısı.
 *
 * @package flx-speed
 */

defined( 'ABSPATH' ) || exit;

class FLXP_Admin {

	const SLUG = 'flx-speed';

	public static function init() {
		add_action( 'admin_menu', array( __CLASS__, 'menu' ) );
		add_action( 'admin_head', array( __CLASS__, 'menu_icon' ) );
		add_action( 'admin_post_flxp_save', array( __CLASS__, 'save' ) );
		add_action( 'admin_post_flxp_clear', array( __CLASS__, 'clear' ) );
		add_action( 'admin_post_flxp_profile', array( __CLASS__, 'profile' ) );
		add_action( 'admin_post_flxp_cleanup', array( __CLASS__, 'cleanup' ) );
		add_action( 'admin_enqueue_scripts', array( __CLASS__, 'assets' ) );
		add_filter( 'submenu_file', array( __CLASS__, 'submenu_file' ) );
	}

	/** Sekme → [başlık, yalnız yönetici]. */
	private static function tab_list() {
		return array(
			'summary'  => array( __( 'Özet', 'flx-speed' ), false ),
			'records'  => array( __( 'Kayıtlar', 'flx-speed' ), false ),
			'server'   => array( __( 'Sunucu', 'flx-speed' ), false ),
			'database' => array( __( 'Veritabanı', 'flx-speed' ), false ),
			'autoload' => array( 'Autoload', false ),
			'cron'     => array( 'Cron', false ),
			'system'   => array( __( 'Sistem Kontrolü', 'flx-speed' ), true ),
			'settings' => array( __( 'Ayarlar', 'flx-speed' ), true ),
		);
	}

	private static function current_tab() {
		$tab = isset( $_GET['tab'] ) ? sanitize_key( $_GET['tab'] ) : 'summary'; // phpcs:ignore
		$all = self::tab_list();
		if ( ! isset( $all[ $tab ] ) || ( $all[ $tab ][1] && ! current_user_can( 'manage_options' ) ) ) {
			$tab = 'summary';
		}
		return $tab;
	}

	/** Alt menüde doğru öğe seçili görünsün. */
	public static function submenu_file( $file ) {
		if ( isset( $_GET['page'] ) && self::SLUG === $_GET['page'] ) { // phpcs:ignore
			$tab = isset( $_GET['id'] ) ? 'records' : self::current_tab(); // phpcs:ignore
			return 'summary' === $tab ? self::SLUG : self::SLUG . '&tab=' . $tab;
		}
		return $file;
	}

	public static function menu() {
		$cap = current_user_can( 'manage_options' ) ? 'manage_options' : 'flxp_view';
		add_menu_page( __( 'Flux Speed', 'flx-speed' ), __( 'Performans', 'flx-speed' ), $cap, self::SLUG, array( __CLASS__, 'page' ), 'none', 81 );
		foreach ( self::tab_list() as $k => $t ) {
			$slug = 'summary' === $k ? self::SLUG : self::SLUG . '&tab=' . $k;
			add_submenu_page( self::SLUG, $t[0], $t[0], $t[1] ? 'manage_options' : $cap, $slug, 'summary' === $k ? array( __CLASS__, 'page' ) : '__return_null' );
		}
	}

	/** Renkli menü logosu (WP data-URI SVG'yi tek renge boyar). */
	public static function menu_icon() {
		echo '<style>#toplevel_page_flx-speed .wp-menu-image{background:url(' . esc_url( FLXP_URL . 'assets/icon.svg' ) . ') center/20px 20px no-repeat}</style>';
	}

	public static function assets( $hook ) {
		if ( false === strpos( (string) $hook, self::SLUG ) ) {
			return;
		}
		wp_enqueue_style( 'flxp-admin', FLXP_URL . 'assets/admin.css', array(), FLXP_VERSION );
		if ( ! empty( $_GET['id'] ) ) { // phpcs:ignore
			wp_enqueue_style( 'flxp-panel', FLXP_URL . 'assets/panel.css', array(), FLXP_VERSION );
			wp_enqueue_script( 'flxp-panel', FLXP_URL . 'assets/panel.js', array(), FLXP_VERSION, true );
		} elseif ( in_array( self::current_tab(), array( 'summary', 'server' ), true ) ) {
			flxp_enqueue_live( 15 );
		}
	}

	private static function url( $args = array() ) {
		return add_query_arg( $args, admin_url( 'admin.php?page=' . self::SLUG ) );
	}

	private static function tabs( $current ) {
		echo '<nav class="nav-tab-wrapper flxp-tabs">';
		foreach ( self::tab_list() as $k => $t ) {
			if ( $t[1] && ! current_user_can( 'manage_options' ) ) {
				continue;
			}
			printf( '<a class="nav-tab%s" href="%s">%s</a>', $k === $current ? ' nav-tab-active' : '', esc_url( self::url( 'summary' === $k ? array() : array( 'tab' => $k ) ) ), esc_html( $t[0] ) );
		}
		echo '</nav>';
	}

	private static function notice() {
		$n = isset( $_GET['flxp_msg'] ) ? sanitize_key( $_GET['flxp_msg'] ) : ''; // phpcs:ignore
		$e = isset( $_GET['flxp_err'] ) ? sanitize_text_field( wp_unslash( $_GET['flxp_err'] ) ) : ''; // phpcs:ignore
		$m = array(
			'saved'   => __( 'Ayarlar kaydedildi.', 'flx-speed' ),
			'cleared'   => __( 'Tüm kayıtlar silindi.', 'flx-speed' ),
			'login_off' => __( 'Özel giriş adresi kapatıldı; giriş yeniden wp-login.php üzerinden.', 'flx-speed' ),
		);
		if ( 'login_on' === $n && FLXP_Login_URL::slug() ) {
			self::login_moved_notice();
		}
		if ( isset( $m[ $n ] ) ) {
			echo '<div class="notice notice-success is-dismissible"><p>' . esc_html( $m[ $n ] ) . '</p></div>';
		}
		if ( isset( $_GET['flxp_done'] ) ) { // phpcs:ignore
			/* translators: %s: satır/tablo sayısı */
			echo '<div class="notice notice-success is-dismissible"><p>' . esc_html( sprintf( __( 'Temizlik tamam: %s öğe.', 'flx-speed' ), number_format_i18n( absint( $_GET['flxp_done'] ) ) ) ) . '</p></div>'; // phpcs:ignore
		}
		if ( $e ) {
			echo '<div class="notice notice-error is-dismissible"><p>' . esc_html( $e ) . '</p></div>';
		}
	}

	/**
	 * Giriş adresi değişti: uyarı + 5 sn geri sayım, sonra çıkış yapılıp yeni giriş sayfası açılır.
	 * Giriş yapılınca Ayarlar'a dönülür. "Oturumda kal" sayımı durdurur.
	 */
	private static function login_moved_notice() {
		$new  = FLXP_Login_URL::url();
		$back = self::url( array( 'tab' => 'settings' ) );
		$go   = wp_logout_url( add_query_arg( array( 'flxp_moved' => 1, 'redirect_to' => rawurlencode( $back ) ), $new ) );
		?>
		<div class="notice notice-warning flxp-moved" id="flxp-moved" data-go="<?php echo esc_url( $go ); ?>" data-sec="5">
			<p><strong><?php esc_html_e( 'Giriş adresi değişti.', 'flx-speed' ); ?></strong> <?php esc_html_e( 'Yeni adres:', 'flx-speed' ); ?> <code><?php echo esc_html( $new ); ?></code></p>
			<p class="flxp-moved-count">
				<?php
				/* translators: %s: kalan saniye */
				printf( esc_html__( 'Yeni adresin çalıştığını görmeniz için oturumunuz kapatılıyor; %s saniye içinde yeni giriş sayfasına yönlendirileceksiniz. Giriş yapınca bu ekrana dönersiniz.', 'flx-speed' ), '<b data-flxp-sec>5</b>' );
				?>
			</p>
			<p class="flxp-moved-stopped" hidden><?php esc_html_e( 'Yönlendirme durduruldu. Oturumunuz açık; yeni adres bir sonraki girişte kullanılır.', 'flx-speed' ); ?></p>
			<p>
				<a class="button button-primary" href="<?php echo esc_url( $go ); ?>"><?php esc_html_e( 'Şimdi git', 'flx-speed' ); ?></a>
				<button type="button" class="button" data-flxp-stay><?php esc_html_e( 'Oturumda kal', 'flx-speed' ); ?></button>
			</p>
		</div>
		<script>
		(function(){var b=document.getElementById('flxp-moved');if(!b)return;var n=+b.dataset.sec,el=b.querySelector('[data-flxp-sec]'),t=setInterval(function(){n--;if(el)el.textContent=n;if(n<=0){clearInterval(t);location.href=b.dataset.go;}},1000);
		b.querySelector('[data-flxp-stay]').addEventListener('click',function(){clearInterval(t);b.querySelector('.flxp-moved-count').hidden=true;b.querySelector('.flxp-moved-stopped').hidden=false;this.hidden=true;});
		try{var u=new URL(location.href);u.searchParams.delete('flxp_msg');history.replaceState(null,'',u);}catch(e){}})();
		</script>
		<?php
	}

	public static function page() {
		if ( ! flxp_can_view() ) {
			wp_die( esc_html__( 'Bu sayfayı görme yetkiniz yok.', 'flx-speed' ) );
		}
		$tab = self::current_tab();
		$id  = isset( $_GET['id'] ) ? absint( $_GET['id'] ) : 0; // phpcs:ignore
		echo '<div class="wrap flxp-wrap"><h1 class="wp-heading-inline">' . esc_html__( 'Flux Speed', 'flx-speed' ) . '</h1>';
		self::notice();
		if ( $id ) {
			self::detail( $id );
		} else {
			self::tabs( $tab );
			switch ( $tab ) {
				case 'records':
					self::records();
					break;
				case 'server':
					self::server();
					break;
				case 'database':
					self::database();
					break;
				case 'autoload':
					self::autoload();
					break;
				case 'cron':
					self::cron();
					break;
				case 'system':
					self::system();
					break;
				case 'settings':
					self::settings();
					break;
				default:
					self::summary();
			}
		}
		echo '</div>';
	}

	/* ------------------------------------------------------------ Kayıtlar */

	private static function flag_html( $flags ) {
		$labels = FLXP_Store::flag_labels();
		$out    = '';
		foreach ( (array) $flags as $f ) {
			$out .= '<span class="flxp-flag flxp-flag-' . esc_attr( $f ) . '">' . esc_html( $labels[ $f ] ?? $f ) . '</span> ';
		}
		return $out;
	}

	private static function fmt_ms( $ms ) {
		return $ms >= 1000 ? number_format_i18n( $ms / 1000, 2 ) . ' s' : number_format_i18n( $ms ) . ' ms';
	}

	private static function fmt_kb( $kb ) {
		return $kb >= 1024 ? number_format_i18n( $kb / 1024, 1 ) . ' MB' : number_format_i18n( $kb ) . ' KB';
	}

	private static function records() {
		// phpcs:disable WordPress.Security.NonceVerification.Recommended
		$f = array(
			'hours' => isset( $_GET['hours'] ) ? absint( $_GET['hours'] ) : 0,
			'flag'  => isset( $_GET['flag'] ) ? sanitize_key( $_GET['flag'] ) : '',
			'type'  => isset( $_GET['type'] ) ? sanitize_key( $_GET['type'] ) : '',
			'q'     => isset( $_GET['q'] ) ? sanitize_text_field( wp_unslash( $_GET['q'] ) ) : '',
			'order' => isset( $_GET['order'] ) && 'slow' === $_GET['order'] ? 'slow' : 'new',
			'page'  => isset( $_GET['paged'] ) ? max( 1, absint( $_GET['paged'] ) ) : 1,
		);
		// phpcs:enable
		$f['per_page'] = 30;
		$res           = FLXP_Store::query( $f );
		$types         = FLXP_Store::type_labels();
		$s             = flxp_settings();

		if ( ! $s['record'] ) {
			echo '<div class="notice notice-warning inline"><p>' . esc_html__( 'Kayıt kapalı: yalnız anlık panel çalışıyor.', 'flx-speed' ) . '</p></div>';
		}
		?>
		<div class="flxp-toolbar">
			<form method="get" class="flxp-filters">
				<input type="hidden" name="page" value="<?php echo esc_attr( self::SLUG ); ?>">
				<input type="hidden" name="tab" value="records">
				<select name="hours">
					<option value="0"><?php esc_html_e( 'Tüm zamanlar', 'flx-speed' ); ?></option>
					<?php foreach ( array( 1 => __( 'Son 1 saat', 'flx-speed' ), 24 => __( 'Son 24 saat', 'flx-speed' ), 168 => __( 'Son 7 gün', 'flx-speed' ) ) as $h => $l ) : ?>
						<option value="<?php echo (int) $h; ?>" <?php selected( $f['hours'], $h ); ?>><?php echo esc_html( $l ); ?></option>
					<?php endforeach; ?>
				</select>
				<select name="flag">
					<option value=""><?php esc_html_e( 'Tüm durumlar', 'flx-speed' ); ?></option>
					<?php foreach ( FLXP_Store::flag_labels() as $k => $l ) : ?>
						<option value="<?php echo esc_attr( $k ); ?>" <?php selected( $f['flag'], $k ); ?>><?php echo esc_html( $l ); ?></option>
					<?php endforeach; ?>
				</select>
				<select name="type">
					<option value=""><?php esc_html_e( 'Tüm türler', 'flx-speed' ); ?></option>
					<?php foreach ( $types as $k => $l ) : ?>
						<option value="<?php echo esc_attr( $k ); ?>" <?php selected( $f['type'], $k ); ?>><?php echo esc_html( $l ); ?></option>
					<?php endforeach; ?>
				</select>
				<select name="order">
					<option value="new" <?php selected( $f['order'], 'new' ); ?>><?php esc_html_e( 'En yeni', 'flx-speed' ); ?></option>
					<option value="slow" <?php selected( $f['order'], 'slow' ); ?>><?php esc_html_e( 'En yavaş', 'flx-speed' ); ?></option>
				</select>
				<input type="search" name="q" value="<?php echo esc_attr( $f['q'] ); ?>" placeholder="<?php esc_attr_e( 'Adreste ara', 'flx-speed' ); ?>">
				<button class="button"><?php esc_html_e( 'Süz', 'flx-speed' ); ?></button>
			</form>
			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="flxp-profile">
				<?php wp_nonce_field( 'flxp_profile' ); ?>
				<input type="hidden" name="action" value="flxp_profile">
				<input type="text" name="url" placeholder="/bagis/" aria-label="<?php esc_attr_e( 'Ölçülecek adres', 'flx-speed' ); ?>">
				<button class="button button-primary"><?php esc_html_e( 'Sayfayı ölç', 'flx-speed' ); ?></button>
			</form>
		</div>
		<p class="description"><?php esc_html_e( '"Sayfayı ölç" adresi ziyaretçi gibi açar (önbelleksiz) ve tüm ayrıntıyla kaydeder.', 'flx-speed' ); ?></p>
		<table class="widefat striped flxp-list">
			<thead><tr>
				<th><?php esc_html_e( 'Zaman', 'flx-speed' ); ?></th>
				<th><?php esc_html_e( 'Adres', 'flx-speed' ); ?></th>
				<th class="n"><?php esc_html_e( 'Durum', 'flx-speed' ); ?></th>
				<th class="n"><?php esc_html_e( 'Süre', 'flx-speed' ); ?></th>
				<th class="n"><?php esc_html_e( 'Bellek', 'flx-speed' ); ?></th>
				<th class="n"><?php esc_html_e( 'Sorgu', 'flx-speed' ); ?></th>
				<th class="n">HTTP</th>
				<th class="n"><?php esc_html_e( 'Hata', 'flx-speed' ); ?></th>
				<th><?php esc_html_e( 'İşaretler', 'flx-speed' ); ?></th>
			</tr></thead>
			<tbody>
			<?php if ( ! $res['items'] ) : ?>
				<tr><td colspan="9" class="flxp-empty"><?php esc_html_e( 'Henüz kayıt yok. Yavaş ya da hatalı istekler ve örneklenen istekler burada görünür.', 'flx-speed' ); ?></td></tr>
			<?php endif; ?>
			<?php
			foreach ( $res['items'] as $r ) :
				$link = self::url( array( 'id' => $r['id'] ) );
				$ts   = strtotime( $r['created'] );
				?>
				<tr class="<?php echo ( array_intersect( array( 'fatal', 'error', '5xx' ), $r['flags'] ) ) ? 'flxp-row-err' : ''; ?>">
					<td data-l="<?php esc_attr_e( 'Zaman', 'flx-speed' ); ?>"><a href="<?php echo esc_url( $link ); ?>"><?php echo esc_html( wp_date( 'd.m H:i:s', $ts ) ); ?></a></td>
					<td data-l="<?php esc_attr_e( 'Adres', 'flx-speed' ); ?>" class="flxp-url"><a href="<?php echo esc_url( $link ); ?>"><span class="flxp-m"><?php echo esc_html( $r['method'] ); ?></span> <?php echo esc_html( $r['url'] ); ?></a> <span class="flxp-type"><?php echo esc_html( $types[ $r['type'] ] ?? $r['type'] ); ?></span></td>
					<td data-l="<?php esc_attr_e( 'Durum', 'flx-speed' ); ?>" class="n"><?php echo (int) $r['status']; ?></td>
					<td data-l="<?php esc_attr_e( 'Süre', 'flx-speed' ); ?>" class="n"><?php echo esc_html( self::fmt_ms( $r['ms'] ) ); ?></td>
					<td data-l="<?php esc_attr_e( 'Bellek', 'flx-speed' ); ?>" class="n"><?php echo esc_html( self::fmt_kb( $r['mem_kb'] ) ); ?></td>
					<td data-l="<?php esc_attr_e( 'Sorgu', 'flx-speed' ); ?>" class="n"><?php echo esc_html( number_format_i18n( $r['queries'] ) ); ?></td>
					<td data-l="HTTP" class="n"><?php echo esc_html( number_format_i18n( $r['http'] ) ); ?></td>
					<td data-l="<?php esc_attr_e( 'Hata', 'flx-speed' ); ?>" class="n"><?php echo esc_html( number_format_i18n( $r['errors'] ) ); ?></td>
					<td data-l="<?php esc_attr_e( 'İşaretler', 'flx-speed' ); ?>"><?php echo self::flag_html( $r['flags'] ); // phpcs:ignore ?></td>
				</tr>
			<?php endforeach; ?>
			</tbody>
		</table>
		<?php
		$pages = (int) ceil( $res['total'] / $f['per_page'] );
		echo '<div class="flxp-pager">';
		/* translators: %s: kayıt sayısı */
		echo '<span>' . esc_html( sprintf( __( 'Kayıt sayısı: %s', 'flx-speed' ), number_format_i18n( $res['total'] ) ) ) . '</span> ';
		if ( $pages > 1 ) {
			echo wp_kses_post(
				paginate_links(
					array(
						'base'    => add_query_arg( 'paged', '%#%' ),
						'format'  => '',
						'current' => $f['page'],
						'total'   => $pages,
					)
				)
			);
		}
		echo '</div>';
		if ( current_user_can( 'manage_options' ) && $res['total'] ) {
			?>
			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" onsubmit="return confirm('<?php echo esc_js( __( 'Tüm kayıtlar silinsin mi?', 'flx-speed' ) ); ?>');" class="flxp-clear">
				<?php wp_nonce_field( 'flxp_clear' ); ?>
				<input type="hidden" name="action" value="flxp_clear">
				<button class="button button-link-delete"><?php esc_html_e( 'Tüm kayıtları sil', 'flx-speed' ); ?></button>
			</form>
			<?php
		}
	}

	/* ------------------------------------------------------------ Ayrıntı */

	private static function detail( $id ) {
		$r = FLXP_Store::get( $id );
		echo '<p><a href="' . esc_url( self::url( array( 'tab' => 'records' ) ) ) . '">&larr; ' . esc_html__( 'Kayıtlar', 'flx-speed' ) . '</a></p>';
		if ( ! $r || empty( $r['data']['overview'] ) ) {
			echo '<div class="notice notice-error inline"><p>' . esc_html__( 'Kayıt bulunamadı.', 'flx-speed' ) . '</p></div>';
			return;
		}
		echo '<p class="flxp-detail-head"><strong>#' . (int) $r['id'] . '</strong> · ' . esc_html( wp_date( get_option( 'date_format' ) . ' H:i:s', strtotime( $r['created'] ) ) ) . ' · ' . self::flag_html( $r['flags'] ) . '</p>'; // phpcs:ignore
		echo '<div id="flxp-panel" class="flxp" hidden></div>';
		FLXP_Panel::print_data(
			$r['data'],
			array(
				'mode'   => 'embed',
				'slowQ'  => (int) flxp_settings( 'slow_query_ms' ),
				'slowMs' => (int) flxp_settings( 'slow_ms' ),
				'labels' => FLXP_Panel::component_labels( $r['data'] ),
			)
		);
	}

	/* ------------------------------------------------------------ Özet */

	private static function summary() {
		$hours = isset( $_GET['hours'] ) ? max( 1, min( 720, absint( $_GET['hours'] ) ) ) : 24; // phpcs:ignore
		$s     = FLXP_Store::summary( $hours );
		$fl    = FLXP_Store::flag_labels();
		?>
		<?php
		flxp_render_live();
		self::hints();
		?>
		<form method="get" class="flxp-filters">
			<input type="hidden" name="page" value="<?php echo esc_attr( self::SLUG ); ?>">
			<select name="hours" onchange="this.form.submit()">
				<?php foreach ( array( 1 => __( 'Son 1 saat', 'flx-speed' ), 24 => __( 'Son 24 saat', 'flx-speed' ), 168 => __( 'Son 7 gün', 'flx-speed' ) ) as $h => $l ) : ?>
					<option value="<?php echo (int) $h; ?>" <?php selected( $hours, $h ); ?>><?php echo esc_html( $l ); ?></option>
				<?php endforeach; ?>
			</select>
		</form>
		<div class="flxp-cards">
			<?php
			self::card( __( 'Kayıtlı istek', 'flx-speed' ), number_format_i18n( $s['recorded'] ) );
			self::card( __( 'Ortalama süre', 'flx-speed' ), self::fmt_ms( $s['ms_avg'] ) );
			self::card( 'p95', self::fmt_ms( $s['ms_p95'] ) );
			self::card( __( 'En uzun', 'flx-speed' ), self::fmt_ms( $s['ms_max'] ) );
			foreach ( array( 'fatal', 'error', '5xx', 'slow', 'slow_query', 'http_fail' ) as $k ) {
				if ( ! empty( $s['flags'][ $k ] ) ) {
					self::card( $fl[ $k ], number_format_i18n( $s['flags'][ $k ] ), in_array( $k, array( 'fatal', 'error', '5xx', 'http_fail' ), true ) ? 'err' : 'warn', self::url( array( 'tab' => 'records', 'flag' => $k, 'hours' => $hours ) ) );
				}
			}
			?>
		</div>
		<p class="description"><?php esc_html_e( 'Süreler kayıtlı isteklerden hesaplanır: örneklenen istekler + yavaş/hatalı istekler. Bu yüzden ortalama gerçek ortalamadan yüksek görünebilir.', 'flx-speed' ); ?></p>

		<h2><?php esc_html_e( 'İstek türüne göre', 'flx-speed' ); ?></h2>
		<p class="description"><?php esc_html_e( 'Ziyaretçinin beklediği süre "Ön yüz" satırıdır. Cron, Ajax ve REST çoğunlukla arka plan işidir (yedek adımı, zamanlanmış görev, Heartbeat); uzun sürmeleri sayfaları doğrudan yavaşlatmaz ama sunucuyu meşgul eder.', 'flx-speed' ); ?></p>
		<?php
		$tl = FLXP_Store::type_labels();
		self::simple_table(
			array( __( 'Tür', 'flx-speed' ), __( 'İstek', 'flx-speed' ), __( 'Ortalama', 'flx-speed' ), 'p50', 'p95', __( 'En uzun', 'flx-speed' ) ),
			array_map(
				function ( $ty, $r ) use ( $tl, $hours ) {
					return array( '<a href="' . esc_url( self::url( array( 'tab' => 'records', 'type' => $ty, 'hours' => $hours, 'order' => 'slow' ) ) ) . '">' . esc_html( $tl[ $ty ] ?? $ty ) . '</a>', number_format_i18n( $r['count'] ), self::fmt_ms( $r['ms_avg'] ), self::fmt_ms( $r['ms_p50'] ), self::fmt_ms( $r['ms_p95'] ), self::fmt_ms( $r['ms_max'] ) );
				},
				array_keys( $s['by_type'] ),
				array_values( $s['by_type'] )
			)
		);
		?>

		<h2><?php esc_html_e( 'En yavaş adresler', 'flx-speed' ); ?></h2>
		<?php
		self::simple_table(
			array( __( 'Adres', 'flx-speed' ), __( 'İstek', 'flx-speed' ), __( 'Ortalama', 'flx-speed' ), __( 'En uzun', 'flx-speed' ), __( 'Hata', 'flx-speed' ) ),
			array_map(
				function ( $u ) {
					return array( '<a href="' . esc_url( self::url( array( 'tab' => 'records', 'q' => $u['url'], 'order' => 'slow' ) ) ) . '">' . esc_html( $u['url'] ) . '</a>', number_format_i18n( $u['count'] ), self::fmt_ms( $u['ms_avg'] ), self::fmt_ms( $u['ms_max'] ), number_format_i18n( $u['errors'] ) );
				},
				$s['slowest_urls']
			)
		);
		?>
		<h2><?php esc_html_e( 'Sorgu süresi: bileşenlere göre (istek başına)', 'flx-speed' ); ?></h2>
		<?php
		self::simple_table(
			array( __( 'Bileşen', 'flx-speed' ), __( 'İstek', 'flx-speed' ), __( 'Sorgu', 'flx-speed' ), __( 'İstek başına', 'flx-speed' ) ),
			array_map(
				function ( $c ) {
					return array( esc_html( $c['component'] ), number_format_i18n( $c['requests'] ), number_format_i18n( $c['queries'] ), self::fmt_ms( $c['ms_per_request'] ) );
				},
				$s['db_components']
			)
		);
		?>
		<h2><?php esc_html_e( 'Yavaş ve hatalı sorgular', 'flx-speed' ); ?></h2>
		<?php
		self::simple_table(
			array( 'SQL', __( 'Bileşen', 'flx-speed' ), __( 'Adet', 'flx-speed' ), __( 'Ortalama', 'flx-speed' ), __( 'En uzun', 'flx-speed' ) ),
			array_map(
				function ( $q ) {
					return array( '<code>' . esc_html( $q['sql'] ) . '</code>' . ( $q['error'] ? '<div class="flxp-err">' . esc_html( $q['error'] ) . '</div>' : '' ) . ' <a href="' . esc_url( self::url( array( 'id' => $q['last_id'] ) ) ) . '">#' . (int) $q['last_id'] . '</a>', esc_html( $q['component'] ), number_format_i18n( $q['count'] ), self::fmt_ms( $q['ms_avg'] ), self::fmt_ms( $q['ms_max'] ) );
				},
				FLXP_Store::slow_queries( $hours, 10 )
			)
		);
		?>
		<h2><?php esc_html_e( 'PHP hataları', 'flx-speed' ); ?></h2>
		<?php
		self::simple_table(
			array( __( 'Seviye', 'flx-speed' ), __( 'Mesaj', 'flx-speed' ), __( 'Bileşen', 'flx-speed' ), __( 'İstek', 'flx-speed' ) ),
			array_map(
				function ( $e ) {
					return array( esc_html( $e['level'] ), esc_html( $e['message'] ) . '<br><code>' . esc_html( $e['file'] ) . '</code> <a href="' . esc_url( self::url( array( 'id' => $e['last_id'] ) ) ) . '">#' . (int) $e['last_id'] . '</a>', esc_html( $e['component'] ), number_format_i18n( $e['requests'] ) );
				},
				FLXP_Store::php_errors( $hours, 10 )
			)
		);
		?>
		<h2><?php esc_html_e( 'Dış istekler', 'flx-speed' ); ?></h2>
		<?php
		self::simple_table(
			array( __( 'Uç', 'flx-speed' ), __( 'Bileşen', 'flx-speed' ), __( 'Adet', 'flx-speed' ), __( 'Başarısız', 'flx-speed' ), __( 'Ortalama', 'flx-speed' ), __( 'Son hata', 'flx-speed' ) ),
			array_map(
				function ( $h ) {
					return array( '<code>' . esc_html( $h['endpoint'] ) . '</code>', esc_html( $h['component'] ), number_format_i18n( $h['count'] ), number_format_i18n( $h['failed'] ), self::fmt_ms( $h['ms_avg'] ), esc_html( $h['last_error'] ) );
				},
				FLXP_Store::http_calls( $hours, false, 10 )
			)
		);
	}

	private static function card( $label, $value, $cls = '', $link = '' ) {
		$inner = '<span>' . esc_html( $label ) . '</span><b>' . esc_html( $value ) . '</b>';
		if ( $link ) {
			echo '<a class="flxp-card ' . esc_attr( $cls ) . '" href="' . esc_url( $link ) . '">' . $inner . '</a>'; // phpcs:ignore
		} else {
			echo '<div class="flxp-card ' . esc_attr( $cls ) . '">' . $inner . '</div>'; // phpcs:ignore
		}
	}

	/** Basit tablo; hücreler önceden kaçışlı HTML. */
	private static function simple_table( $head, $rows ) {
		echo '<table class="widefat striped flxp-list"><thead><tr>';
		foreach ( $head as $h ) {
			echo '<th>' . esc_html( $h ) . '</th>';
		}
		echo '</tr></thead><tbody>';
		if ( ! $rows ) {
			echo '<tr><td colspan="' . count( $head ) . '" class="flxp-empty">' . esc_html__( 'Kayıt yok.', 'flx-speed' ) . '</td></tr>';
		}
		foreach ( $rows as $r ) {
			echo '<tr>';
			foreach ( $r as $i => $c ) {
				echo '<td data-l="' . esc_attr( $head[ $i ] ) . '">' . $c . '</td>'; // phpcs:ignore
			}
			echo '</tr>';
		}
		echo '</tbody></table>';
	}

	/* ------------------------------------------------------------ Canlı kutular */

	/** Bizim performans kontrollerimizden sorunlu olanlar (Özet'te öneri olarak). */
	private static function hints() {
		$bad = array_filter(
			flxp_server_checks(),
			function ( $c ) {
				return in_array( $c['status'], array( 'error', 'warning' ), true );
			}
		);
		if ( ! $bad ) {
			return;
		}
		echo '<div class="flxp-hints"><h2>' . esc_html__( 'Öneriler', 'flx-speed' ) . '</h2><ul>';
		foreach ( $bad as $c ) {
			echo '<li class="flxp-hint-' . esc_attr( $c['status'] ) . '"><strong>' . esc_html( $c['label'] ) . ':</strong> ' . esc_html( $c['message'] );
			if ( ! empty( $c['action_url'] ) ) {
				echo ' <a href="' . esc_url( $c['action_url'] ) . '">' . esc_html( $c['action_label'] ?? '' ) . '</a>';
			}
			echo '</li>';
		}
		echo '</ul></div>';
	}

	/* ------------------------------------------------------------ Sunucu */

	/** Kaynak değerlerinin kime ait olduğu. */
	private static function scope_label( $scope ) {
		if ( 'account' === $scope ) {
			return __( 'Hesap sınırı (cgroup)', 'flx-speed' );
		}
		if ( 'shared' === $scope ) {
			return __( 'Paylaşımlı hosting: hesap sınırı okunamıyor', 'flx-speed' );
		}
		return __( 'Sunucu bu siteye ait (VPS/dedicated)', 'flx-speed' );
	}

	/** Hesap diski ve (paylaşımlıysa) makinenin ortak değerleri, açıkça etiketli. */
	private static function resources( $l ) {
		$fmt = function ( $b ) {
			return ( null === $b || (float) $b <= 0 ) ? '—' : size_format( (float) $b, 1 );
		};
		$du  = $l['disk_usage'];
		if ( $du ) {
			$rows = array(
				__( 'Taranan klasör', 'flx-speed' )        => $du['root'],
				__( 'Dosyalar', 'flx-speed' )              => null === $du['bytes'] ? __( 'Hesaplanıyor…', 'flx-speed' ) : $fmt( $du['bytes'] ),
				__( 'Dosya ve klasör sayısı (inode)', 'flx-speed' ) => null === $du['files'] ? '—' : number_format_i18n( $du['files'] ),
				__( 'Veritabanı', 'flx-speed' )            => $fmt( $du['db'] ),
				__( 'Disk kotası', 'flx-speed' )           => flxp_settings( 'disk_quota_gb' ) ? $fmt( flxp_settings( 'disk_quota_gb' ) * 1073741824 ) : __( 'Girilmedi (Ayarlar)', 'flx-speed' ),
				__( 'Son ölçüm', 'flx-speed' )             => $du['time'] ? sprintf( /* translators: %s: süre */ __( '%s önce', 'flx-speed' ), human_time_diff( $du['time'] ) ) . ( $du['running'] ? ' · ' . __( 'yenileniyor', 'flx-speed' ) : '' ) : '—',
			);
			self::kv( __( 'Hesap diski', 'flx-speed' ), $rows );
		}
		if ( 'machine' === $l['scope'] && ! $l['disk_shared'] ) {
			return;
		}
		$m  = $l['machine'];
		$ld = $m['load'] ? implode( ' / ', $m['load'] ) . ( null === $m['load_pct'] ? '' : ' (%' . $m['load_pct'] . ')' ) : '—';
		self::kv(
			__( 'Makine geneli (paylaşımlı, tüm hesaplar)', 'flx-speed' ),
			array(
				__( 'Çekirdek sayısı', 'flx-speed' )      => $m['cores'] ? $m['cores'] : '—',
				__( 'Yük (1/5/15 dk)', 'flx-speed' )      => $ld,
				__( 'Bellek', 'flx-speed' )               => $m['ram_total_kb'] ? $fmt( $m['ram_used_kb'] * 1024 ) . ' / ' . $fmt( $m['ram_total_kb'] * 1024 ) : '—',
				__( 'Disk', 'flx-speed' )                 => $m['disk_total'] ? sprintf( __( '%s boş', 'flx-speed' ), $fmt( $m['disk_free'] ) ) . ' / ' . $fmt( $m['disk_total'] ) : '—',
				__( 'Not', 'flx-speed' )                  => __( 'Bu değerler sunucudaki tüm hesaplarındır, sitenizin kullanımı veya sınırı değildir.', 'flx-speed' ),
			)
		);
	}

	private static function server() {
		$st  = flxp_server_stats();
		$e   = flxp_environment();
		$yes = __( 'Evet', 'flx-speed' );
		$no  = __( 'Hayır', 'flx-speed' );
		$b   = function ( $v ) use ( $yes, $no ) {
			return $v ? $yes : $no;
		};
		flxp_render_live();
		echo '<div class="flxp-env">';
		self::kv(
			__( 'Sunucu', 'flx-speed' ),
			array(
				__( 'İşletim sistemi', 'flx-speed' ) => $st['server']['os'],
				__( 'Yazılım', 'flx-speed' )         => $st['server']['software'],
				__( 'Sunucu adı', 'flx-speed' )      => $st['server']['hostname'],
				__( 'IP adresi', 'flx-speed' )       => $st['server']['ip'],
				__( 'Port', 'flx-speed' )            => $st['server']['port'],
				__( 'Belge kökü', 'flx-speed' )      => $st['server']['doc_root'],
				__( 'Kaynak bilgisi', 'flx-speed' )  => self::scope_label( $st['live']['scope'] ),
				'CloudLinux'                            => $b( $st['server']['cloudlinux'] ),
				'HTTPS'                                 => $b( $e['server']['https'] ),
				'LiteSpeed'                             => $b( $e['server']['litespeed'] ),
			)
		);
		self::resources( $st['live'] );
		$p = $st['php'];
		self::kv(
			'PHP',
			array(
				__( 'Sürüm', 'flx-speed' )          => $p['version'] . ' (' . $p['sapi'] . ')',
				'memory_limit'                        => $p['memory_limit'],
				'WP_MEMORY_LIMIT'                     => $p['wp_memory_limit'],
				'WP_MAX_MEMORY_LIMIT'                 => $p['wp_max_memory_limit'],
				__( 'Bellek (bu istek)', 'flx-speed' ) => self::fmt_kb( $p['usage_kb'] ) . ' · ' . __( 'tepe', 'flx-speed' ) . ' ' . self::fmt_kb( $p['peak_kb'] ),
				'max_execution_time'                  => $p['max_execution_time'],
				'max_input_time'                      => $p['max_input_time'],
				'max_input_vars'                      => $p['max_input_vars'],
				'upload_max_filesize'                 => $p['upload_max_filesize'],
				'post_max_size'                       => $p['post_max_size'],
				'allow_url_fopen'                     => $b( $p['allow_url_fopen'] ),
				'display_errors'                      => $e['php']['display_errors'],
				__( 'Kapalı fonksiyonlar', 'flx-speed' ) => $p['disabled_functions'] ? $p['disabled_functions'] : '—',
				__( 'Uzantılar', 'flx-speed' )      => implode(
					', ',
					array_map(
						function ( $k, $v ) {
							return ( $v ? '✓ ' : '✗ ' ) . $k;
						},
						array_keys( $e['php']['extensions'] ),
						$e['php']['extensions']
					)
				),
			)
		);
		$o = $st['opcache'];
		self::kv(
			'OPcache',
			$o ? array(
				__( 'Durum', 'flx-speed' )            => $o['enabled'] ? __( 'Açık', 'flx-speed' ) : __( 'Kapalı', 'flx-speed' ),
				__( 'Bellek', 'flx-speed' )           => null === $o['used_mb'] ? '—' : $o['used_mb'] . ' MB / ' . ( $o['used_mb'] + $o['free_mb'] ) . ' MB',
				__( 'İsabet oranı', 'flx-speed' )     => null === $o['hit_rate'] ? '—' : $o['hit_rate'] . '%',
				__( 'Önbellekteki dosya', 'flx-speed' ) => null === $o['cached_files'] ? '—' : number_format_i18n( $o['cached_files'] ) . ' / ' . number_format_i18n( (int) $o['max_files'] ),
				__( 'Boşa giden', 'flx-speed' )       => null === $o['wasted_pct'] ? '—' : $o['wasted_pct'] . '%',
				__( 'Yeniden başlama', 'flx-speed' )  => (int) $o['restarts'],
			) : array( __( 'Durum', 'flx-speed' ) => __( 'Bilgi alınamadı (kapalı ya da sunucu izin vermiyor).', 'flx-speed' ) )
		);
		$c = $st['caches'];
		self::kv(
			__( 'Önbellek', 'flx-speed' ),
			array(
				__( 'Nesne önbelleği', 'flx-speed' ) => $c['object_cache'] ? __( 'Harici', 'flx-speed' ) : __( 'Yok', 'flx-speed' ),
				__( 'Sayfa önbelleği (WP_CACHE)', 'flx-speed' ) => $b( $c['page_cache'] ),
				'LiteSpeed Cache'                       => $b( $c['litespeed'] ),
				'APCu'                                  => $b( $c['apcu'] ),
				'Memcached'                             => $b( $c['memcached'] ),
				'Redis'                                 => $b( $c['redis'] ),
			)
		);
		$wp   = $e['wordpress'];
		$rows = array(
			__( 'Sürüm', 'flx-speed' )             => $wp['version'],
			__( 'Dil', 'flx-speed' )               => $wp['locale'],
			__( 'Saat dilimi', 'flx-speed' )       => $wp['timezone'],
			__( 'Kalıcı bağlantılar', 'flx-speed' ) => $wp['permalinks'] ? $wp['permalinks'] : '—',
			__( 'Ortam türü', 'flx-speed' )        => $wp['environment'],
			__( 'Tema', 'flx-speed' )              => $wp['theme'] . ( $wp['parent_theme'] ? ' (' . $wp['parent_theme'] . ')' : '' ),
		);
		foreach ( $wp['constants'] as $k => $v ) {
			$rows[ $k ] = null === $v ? __( 'tanımsız', 'flx-speed' ) : $v;
		}
		self::kv( 'WordPress', $rows );
		$pl = array();
		foreach ( $wp['plugins'] as $pp ) {
			$pl[ $pp['name'] ] = $pp['version'];
		}
		foreach ( $wp['mu_plugins'] as $pp ) {
			$pl[ 'MU: ' . $pp['name'] ] = $pp['version'];
		}
		foreach ( $wp['dropins'] as $d ) {
			$pl[ 'Drop-in: ' . $d ] = '';
		}
		self::kv( __( 'Etkin eklentiler', 'flx-speed' ), $pl );
		echo '</div>';
	}

	/* ------------------------------------------------------------ Veritabanı */

	private static function database() {
		global $wpdb;
		$db = flxp_database( true );
		$v  = $db['variables'];
		$s  = $db['status'];
		$t  = $db['totals'];
		$mb = function ( $bytes ) {
			return '' === (string) $bytes ? '—' : size_format( (float) $bytes, 1 );
		};
		echo '<div class="flxp-cards">';
		self::card( __( 'Veri', 'flx-speed' ), number_format_i18n( $t['data_mb'], 1 ) . ' MB' );
		self::card( __( 'İndeks', 'flx-speed' ), number_format_i18n( $t['index_mb'], 1 ) . ' MB' );
		self::card( __( 'Boş alan', 'flx-speed' ), number_format_i18n( $t['free_mb'], 1 ) . ' MB', $t['free_mb'] > 50 ? 'warn' : '' );
		self::card( __( 'Tablo', 'flx-speed' ), number_format_i18n( $t['tables'] ) );
		self::card( __( 'Satır (yaklaşık)', 'flx-speed' ), number_format_i18n( $t['rows'] ) );
		if ( isset( $s['Threads_connected'] ) ) {
			self::card( __( 'Açık bağlantı', 'flx-speed' ), number_format_i18n( (int) $s['Threads_connected'] ) . ( isset( $v['max_connections'] ) ? ' / ' . number_format_i18n( (int) $v['max_connections'] ) : '' ) );
		}
		echo '</div><div class="flxp-env">';
		self::kv(
			__( 'Sunucu', 'flx-speed' ),
			array(
				__( 'Yazılım', 'flx-speed' )            => trim( ( $v['version_comment'] ?? '' ) . ' ' . ( $v['version'] ?? $wpdb->db_server_info() ) ),
				'max_connections'                         => $v['max_connections'] ?? '—',
				'max_user_connections'                    => $v['max_user_connections'] ?? '—',
				'max_allowed_packet'                      => isset( $v['max_allowed_packet'] ) ? $mb( $v['max_allowed_packet'] ) : '—',
				'innodb_buffer_pool_size'                 => isset( $v['innodb_buffer_pool_size'] ) ? $mb( $v['innodb_buffer_pool_size'] ) : '—',
				'tmp_table_size'                          => isset( $v['tmp_table_size'] ) ? $mb( $v['tmp_table_size'] ) : '—',
				'wait_timeout'                            => $v['wait_timeout'] ?? '—',
				'slow_query_log'                          => ( $v['slow_query_log'] ?? '—' ) . ( isset( $v['long_query_time'] ) ? ' (' . $v['long_query_time'] . ' s)' : '' ),
				__( 'Karakter seti', 'flx-speed' )      => ( $v['character_set_server'] ?? '' ) . ' / ' . ( $v['collation_server'] ?? '' ),
				'sql_mode'                                => $v['sql_mode'] ?? '—',
			)
		);
		$d = $db['derived'];
		self::kv(
			__( 'Durum', 'flx-speed' ),
			array(
				__( 'Çalışma süresi', 'flx-speed' )        => isset( $s['Uptime'] ) ? flxp_fmt_uptime( (int) $s['Uptime'] ) : '—',
				__( 'Saniyede sorgu', 'flx-speed' )        => isset( $d['queries_per_sec'] ) ? number_format_i18n( $d['queries_per_sec'], 2 ) : '—',
				__( 'Yavaş sorgu (sunucu)', 'flx-speed' )  => isset( $s['Slow_queries'] ) ? number_format_i18n( (int) $s['Slow_queries'] ) : '—',
				__( 'En çok eş zamanlı bağlantı', 'flx-speed' ) => $s['Max_used_connections'] ?? '—',
				__( 'Çalışan iş parçacığı', 'flx-speed' )  => $s['Threads_running'] ?? '—',
				__( 'Buffer pool isabeti', 'flx-speed' )   => isset( $d['buffer_pool_hit_pct'] ) ? $d['buffer_pool_hit_pct'] . '%' : '—',
				__( 'Diske düşen geçici tablo', 'flx-speed' ) => isset( $d['tmp_disk_pct'] ) ? $d['tmp_disk_pct'] . '%' : '—',
				__( 'Reddedilen bağlantı', 'flx-speed' )   => $s['Aborted_connects'] ?? '—',
			)
		);
		echo '</div>';

		echo '<h2>' . esc_html__( 'Temizlik', 'flx-speed' ) . '</h2>';
		echo '<p class="description">' . esc_html__( 'Yalnız güvenli işler: yazı, sayfa ve revizyonlar silinmez. Revizyon sayısını wp-config\'te WP_POST_REVISIONS ile sınırlayın.', 'flx-speed' ) . '</p>';
		$cl    = $db['cleanup'];
		$count = array(
			'expired_transients' => $cl['expired_transients'],
			'auto_drafts'        => $cl['auto_drafts'],
			'spam_comments'      => $cl['spam_comments'],
			'trashed_comments'   => $cl['trashed_comments'],
			'optimize'           => count(
				array_filter(
					$db['tables'],
					function ( $x ) {
						return $x['free'] > 1048576;
					}
				)
			),
		);
		$rows  = array();
		foreach ( flxp_cleanup_jobs() as $k => $label ) {
			$btn = '';
			if ( current_user_can( 'manage_options' ) && $count[ $k ] ) {
				$btn = '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '" onsubmit="return confirm(\'' . esc_js( $label . ' — ' . __( 'devam edilsin mi?', 'flx-speed' ) ) . '\')">' . wp_nonce_field( 'flxp_cleanup', '_wpnonce', true, false ) . '<input type="hidden" name="action" value="flxp_cleanup"><input type="hidden" name="job" value="' . esc_attr( $k ) . '"><button class="button button-small">' . esc_html__( 'Temizle', 'flx-speed' ) . '</button></form>';
			}
			$rows[] = array( esc_html( $label ), number_format_i18n( $count[ $k ] ), $btn );
		}
		$rows[] = array( esc_html__( 'Revizyonlar', 'flx-speed' ), number_format_i18n( $cl['revisions'] ), '' );
		$rows[] = array( esc_html__( 'Çöpteki yazılar', 'flx-speed' ), number_format_i18n( $cl['trashed_posts'] ), '' );
		$rows[] = array( esc_html__( 'Tüm geçici veriler', 'flx-speed' ), number_format_i18n( $cl['transients'] ), '' );
		self::simple_table( array( __( 'İş', 'flx-speed' ), __( 'Adet', 'flx-speed' ), '' ), $rows );

		echo '<h2>' . esc_html__( 'Tablolar', 'flx-speed' ) . '</h2>';
		self::simple_table(
			array( __( 'Tablo', 'flx-speed' ), __( 'Motor', 'flx-speed' ), __( 'Satır', 'flx-speed' ), __( 'Veri', 'flx-speed' ), __( 'İndeks', 'flx-speed' ), __( 'Boş alan', 'flx-speed' ), __( 'Karşılaştırma', 'flx-speed' ) ),
			array_map(
				function ( $x ) use ( $mb ) {
					return array( '<code>' . esc_html( $x['name'] ) . '</code>', esc_html( $x['engine'] ), number_format_i18n( $x['rows'] ), esc_html( $mb( $x['data'] ) ), esc_html( $mb( $x['index'] ) ), esc_html( $x['free'] ? $mb( $x['free'] ) : '—' ), esc_html( $x['collate'] ) );
				},
				$db['tables']
			)
		);
	}

	/* ------------------------------------------------------------ Autoload */

	private static function autoload() {
		$a = flxp_autoload( 40 );
		echo '<div class="flxp-cards">';
		self::card( __( 'Autoload seçenek', 'flx-speed' ), number_format_i18n( $a['count'] ) );
		self::card( __( 'Toplam boyut', 'flx-speed' ), number_format_i18n( $a['total_kb'], 0 ) . ' KB', $a['total_kb'] > 1600 ? 'err' : ( $a['total_kb'] > 800 ? 'warn' : '' ) );
		echo '</div>';
		echo '<p class="description">' . esc_html__( 'Bu seçenekler her istekte veritabanından okunur. Toplam 800 KB üstü sayfaları yavaşlatır; büyük satırların sahibi eklentide ayarı ya da önbelleği küçültmek gerekir.', 'flx-speed' ) . '</p>';
		self::simple_table(
			array( __( 'Seçenek', 'flx-speed' ), __( 'Sahibi', 'flx-speed' ), __( 'Boyut', 'flx-speed' ) ),
			array_map(
				function ( $o ) {
					return array( '<code>' . esc_html( $o['name'] ) . '</code>', esc_html( $o['owner'] ? $o['owner'] : '—' ), esc_html( number_format_i18n( $o['kb'], 1 ) . ' KB' ) );
				},
				$a['largest']
			)
		);
	}

	/* ------------------------------------------------------------ Cron */

	private static function cron() {
		$c = flxp_cron();
		echo '<div class="flxp-cards">';
		self::card( __( 'Görev', 'flx-speed' ), number_format_i18n( $c['events'] ) );
		self::card( __( 'Gecikmiş', 'flx-speed' ), number_format_i18n( $c['overdue'] ), $c['overdue'] ? 'warn' : '' );
		$ago = $c['last_seen'] ? human_time_diff( $c['last_seen'] ) : '';
		/* translators: %s: süre */
		self::card( __( 'Son çalışma', 'flx-speed' ), $ago ? sprintf( __( '%s önce', 'flx-speed' ), $ago ) : __( 'Görülmedi', 'flx-speed' ), ( ! $c['last_seen'] || $c['last_seen'] < time() - HOUR_IN_SECONDS ) ? 'warn' : '' );
		self::card( 'DISABLE_WP_CRON', $c['disabled'] ? __( 'Açık', 'flx-speed' ) : __( 'Kapalı', 'flx-speed' ) );
		echo '</div>';
		if ( $c['disabled'] ) {
			echo '<p class="description">' . esc_html__( 'WordPress cron kapalı: görevler yalnız sunucu cron\'u wp-cron.php\'yi çağırınca çalışır. Hostinger\'da 5 dakikada bir çağıran bir cron işi tanımlı olmalı.', 'flx-speed' ) . '</p>';
		}
		echo '<h2>' . esc_html__( 'Görev süreleri', 'flx-speed' ) . '</h2>';
		echo '<p class="description">' . esc_html__( 'wp-cron.php her çalıştığında görev başına ölçülür; toplam süreye göre sıralı. Bir günden eski özet, görev yeniden çalışınca sıfırlanır. "Kesildi": görev süre sınırına takılıp yarıda kaldı.', 'flx-speed' ) . '</p>';
		self::simple_table(
			array( __( 'Kanca', 'flx-speed' ), __( 'Bileşen', 'flx-speed' ), __( 'Çalışma', 'flx-speed' ), __( 'Ortalama', 'flx-speed' ), __( 'En uzun', 'flx-speed' ), __( 'Son', 'flx-speed' ), __( 'Son çalışma', 'flx-speed' ) ),
			array_map(
				function ( $t ) {
					$slow = $t['avg_ms'] >= 5000;
					return array(
						'<code>' . esc_html( $t['hook'] ) . '</code>' . ( $t['cut'] ? ' <span class="flxp-err">' . esc_html__( 'Kesildi', 'flx-speed' ) . '</span>' : '' ),
						esc_html( $t['component'] ),
						number_format_i18n( $t['runs'] ),
						$slow ? '<span class="flxp-err">' . esc_html( self::fmt_ms( $t['avg_ms'] ) ) . '</span>' : esc_html( self::fmt_ms( $t['avg_ms'] ) ),
						esc_html( self::fmt_ms( $t['max_ms'] ) ),
						esc_html( self::fmt_ms( $t['last_ms'] ) ),
						/* translators: %s: süre */
						esc_html( $t['last_at'] ? sprintf( __( '%s önce', 'flx-speed' ), human_time_diff( $t['last_at'] ) ) : '—' ),
					);
				},
				FLXP_Cron_Timing::stats()
			)
		);
		echo '<h2>' . esc_html__( 'Zamanlanmış görevler', 'flx-speed' ) . '</h2>';
		$now = time();
		self::simple_table(
			array( __( 'Kanca', 'flx-speed' ), __( 'Sonraki', 'flx-speed' ), __( 'Tekrar', 'flx-speed' ), __( 'Argüman', 'flx-speed' ) ),
			array_map(
				function ( $e ) use ( $now ) {
					$when = wp_date( 'd.m H:i', $e['next'] ) . ' · ' . ( $e['next'] >= $now
						/* translators: %s: süre */
						? sprintf( __( '%s sonra', 'flx-speed' ), human_time_diff( $now, $e['next'] ) )
						/* translators: %s: süre */
						: sprintf( __( '%s gecikti', 'flx-speed' ), human_time_diff( $e['next'], $now ) ) );
					return array( '<code>' . esc_html( $e['hook'] ) . '</code>', $e['overdue'] ? '<span class="flxp-err">' . esc_html( $when ) . '</span>' : esc_html( $when ), esc_html( $e['schedule'] ? $e['schedule'] : __( 'bir kez', 'flx-speed' ) ), (int) $e['args'] );
				},
				$c['items']
			)
		);
	}

	/* ------------------------------------------------------------ Sistem Kontrolü */

	private static function system() {
		flx_system_checks_render( array( 'recheck_url' => self::url( array( 'tab' => 'system' ) ) ) );
		if ( ! function_exists( 'flxseo_system_fix_log' ) ) {
			return;
		}
		$log = flxseo_system_fix_log( 20 );
		echo '<h2>' . esc_html__( 'Son düzeltmeler (yapay zekâ / MCP)', 'flx-speed' ) . '</h2>';
		self::simple_table(
			array( __( 'Zaman', 'flx-speed' ), __( 'Kullanıcı', 'flx-speed' ), __( 'Düzeltme', 'flx-speed' ), __( 'Önce → Sonra', 'flx-speed' ), __( 'Sonuç', 'flx-speed' ) ),
			array_map(
				function ( $r ) {
					$u = get_userdata( (int) ( $r['u'] ?? 0 ) );
					return array(
						esc_html( wp_date( 'd.m H:i', (int) ( $r['t'] ?? 0 ) ) ),
						esc_html( $u ? $u->user_login : '?' ),
						esc_html( $r['label'] ?? '' ) . ( '' !== (string) ( $r['value'] ?? '' ) ? ' <code>' . esc_html( $r['value'] ) . '</code>' : '' ),
						esc_html( ( $r['before'] ?? '' ) . ' → ' . ( $r['after'] ?? '' ) ),
						( ! empty( $r['ok'] ) ? '✓ ' : '✗ ' ) . esc_html( $r['msg'] ?? '' ),
					);
				},
				$log
			)
		);
	}

	private static function kv( $title, $rows ) {
		echo '<div class="flxp-box"><h2>' . esc_html( $title ) . '</h2><table class="widefat striped flxp-kv"><tbody>';
		foreach ( $rows as $k => $v ) {
			echo '<tr><th>' . esc_html( $k ) . '</th><td>' . esc_html( (string) $v ) . '</td></tr>';
		}
		echo '</tbody></table></div>';
	}

	/* ------------------------------------------------------------ Ayarlar */

	private static function settings() {
		$s = flxp_settings();
		$c = function ( $k ) use ( $s ) {
			checked( ! empty( $s[ $k ] ) );
		};
		?>
		<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="flxp-settings">
			<?php wp_nonce_field( 'flxp_save' ); ?>
			<input type="hidden" name="action" value="flxp_save">
			<table class="form-table" role="presentation">
				<tr><th scope="row"><?php esc_html_e( 'Geliştirici paneli', 'flx-speed' ); ?></th><td>
					<label><input type="checkbox" name="flxp[panel]" value="1" <?php $c( 'panel' ); ?>> <?php esc_html_e( 'Yetkili kullanıcıya yönetici çubuğunda özet ve alt panel göster', 'flx-speed' ); ?></label><br>
					<label><input type="checkbox" name="flxp[panel_front]" value="1" <?php $c( 'panel_front' ); ?>> <?php esc_html_e( 'Ön yüzde de göster', 'flx-speed' ); ?></label><br>
					<label><input type="checkbox" name="flxp[caps]" value="1" <?php $c( 'caps' ); ?>> <?php esc_html_e( 'Yetki kontrollerini topla', 'flx-speed' ); ?></label>
					<p class="description"><?php esc_html_e( 'Panel; yöneticiye ve Flux Roles\'den "Performansı görüntüle" yetkisi verilen personele görünür.', 'flx-speed' ); ?></p>
				</td></tr>
				<tr><th scope="row"><?php esc_html_e( 'Kayıt', 'flx-speed' ); ?></th><td>
					<label><input type="checkbox" name="flxp[record]" value="1" <?php $c( 'record' ); ?>> <?php esc_html_e( 'Yavaş ve hatalı istekleri kaydet', 'flx-speed' ); ?></label><br>
					<label><input type="checkbox" name="flxp[record_admins]" value="1" <?php $c( 'record_admins' ); ?>> <?php esc_html_e( 'Panel açıkken kendi isteklerimi de kaydet (yalnız sorun varsa)', 'flx-speed' ); ?></label>
				</td></tr>
				<tr><th scope="row"><label for="flxp-sample"><?php esc_html_e( 'Örnekleme', 'flx-speed' ); ?></label></th><td>
					<input id="flxp-sample" type="number" min="0" max="100" name="flxp[sample]" value="<?php echo (int) $s['sample']; ?>" class="small-text"> %
					<p class="description"><?php esc_html_e( 'İsteklerin bu kadarı sorgu listesiyle ayrıntılı toplanır ve kaydedilir. Hostinger için 1-5 önerilir; 0 kapatır.', 'flx-speed' ); ?></p>
				</td></tr>
				<tr><th scope="row"><label for="flxp-slow"><?php esc_html_e( 'Yavaş istek eşiği', 'flx-speed' ); ?></label></th><td>
					<input id="flxp-slow" type="number" min="100" step="100" name="flxp[slow_ms]" value="<?php echo (int) $s['slow_ms']; ?>" class="small-text"> ms
				</td></tr>
				<tr><th scope="row"><label for="flxp-slowq"><?php esc_html_e( 'Yavaş sorgu eşiği', 'flx-speed' ); ?></label></th><td>
					<input id="flxp-slowq" type="number" min="1" name="flxp[slow_query_ms]" value="<?php echo (int) $s['slow_query_ms']; ?>" class="small-text"> ms
				</td></tr>
				<tr><th scope="row"><label for="flxp-keep"><?php esc_html_e( 'Saklama', 'flx-speed' ); ?></label></th><td>
					<input id="flxp-keep" type="number" min="1" max="90" name="flxp[keep_days]" value="<?php echo (int) $s['keep_days']; ?>" class="small-text"> <?php esc_html_e( 'gün', 'flx-speed' ); ?>,
					<?php esc_html_e( 'en fazla', 'flx-speed' ); ?> <input type="number" min="100" max="50000" step="100" name="flxp[max_rows]" value="<?php echo (int) $s['max_rows']; ?>" class="small-text"> <?php esc_html_e( 'kayıt', 'flx-speed' ); ?>
				</td></tr>
				<tr><th scope="row"><label for="flxp-quota"><?php esc_html_e( 'Disk kotası', 'flx-speed' ); ?></label></th><td>
					<input id="flxp-quota" type="number" min="0" step="0.1" name="flxp[disk_quota_gb]" value="<?php echo esc_attr( $s['disk_quota_gb'] ? $s['disk_quota_gb'] : '' ); ?>" class="small-text"> GB
					<p class="description"><?php esc_html_e( 'Paylaşımlı hosting planınızın disk sınırı (Hostinger: hPanel › Hesap kaynakları). PHP bu sınırı okuyamaz; girilirse Disk kutusu hesap kullanımını yüzde olarak gösterir. Boş bırakılırsa yalnız kullanılan alan görünür.', 'flx-speed' ); ?></p>
				</td></tr>
			</table>
			<?php self::login_settings( $s ); ?>
			<h2><?php esc_html_e( 'Yapay zekâ (MCP)', 'flx-speed' ); ?></h2>
			<p><?php esc_html_e( 'Kayıtlar Flux SEO & MCP sunucusunda ve Flux AI sohbetinde salt okunur araçlarla okunur: flxp_summary, flxp_requests, flxp_request, flxp_slow_queries, flxp_php_errors, flxp_http_calls, flxp_environment, flxp_server, flxp_database, flxp_autoload, flxp_cron, flxp_profile_url.', 'flx-speed' ); ?></p>
			<p><?php echo function_exists( 'flxseo_mcp_tools' ) ? esc_html__( 'Flux SEO & MCP etkin: araçlar kayıtlı.', 'flx-speed' ) : esc_html__( 'Flux SEO & MCP etkin değil: araçlar yalnız Flux AI sohbetinde.', 'flx-speed' ); ?></p>
			<?php submit_button(); ?>
		</form>
		<?php
	}

	/** Ayarlar › Yönetim giriş adresi. */
	private static function login_settings( $s ) {
		$const_slug = defined( 'FLXP_LOGIN_SLUG' );
		$off        = FLXP_Login_URL::disabled_by_constant();
		$active     = FLXP_Login_URL::slug();
		$value      = $const_slug ? FLXP_Login_URL::clean( FLXP_LOGIN_SLUG ) : $s['login_slug'];
		$prefix     = get_option( 'permalink_structure' ) ? trailingslashit( home_url() ) : trailingslashit( home_url() ) . '?';
		?>
		<h2 id="flxp-login"><?php esc_html_e( 'Yönetim giriş adresi', 'flx-speed' ); ?></h2>
		<p><?php esc_html_e( 'Giriş ekranını wp-login.php yerine size özel bir adreste açar. wp-login.php ve giriş yapmamış ziyaretçinin wp-admin istekleri yanıt vermez; botların şifre denemesi ve sunucu yükü azalır.', 'flx-speed' ); ?></p>
		<?php if ( $off ) : ?>
			<div class="notice notice-warning inline"><p><?php esc_html_e( 'wp-config.php içinde FLXP_LOGIN_DISABLE tanımlı: özel adres kapalı, giriş yine wp-login.php üzerinden. Sorun çözüldüyse bu satırı silin.', 'flx-speed' ); ?></p></div>
		<?php elseif ( $active ) : ?>
			<div class="flxp-login-now">
				<span><?php esc_html_e( 'Giriş adresiniz', 'flx-speed' ); ?></span>
				<code id="flxp-login-url"><?php echo esc_html( FLXP_Login_URL::url() ); ?></code>
				<button type="button" class="button button-small" data-flxp-copy="flxp-login-url" data-done="<?php esc_attr_e( 'Kopyalandı', 'flx-speed' ); ?>"><?php esc_html_e( 'Kopyala', 'flx-speed' ); ?></button>
			</div>
			<p class="description"><?php esc_html_e( 'Bu adresi yer imlerine ekleyin ve giriş yapan herkese iletin. Unutursanız aşağıdaki kurtarma adımını kullanın.', 'flx-speed' ); ?></p>
		<?php endif; ?>
		<table class="form-table" role="presentation">
			<tr><th scope="row"><label for="flxp-login-slug"><?php esc_html_e( 'Giriş adresi', 'flx-speed' ); ?></label></th><td>
				<div class="flxp-slug"><span class="flxp-slug-pre"><?php echo esc_html( $prefix ); ?></span><input id="flxp-login-slug" type="text" name="flxp[login_slug]" value="<?php echo esc_attr( $value ); ?>" placeholder="fluxadmin" maxlength="40" pattern="[a-z0-9][a-z0-9_\-]{2,39}" autocomplete="off" spellcheck="false" <?php disabled( $const_slug ); ?>></div>
				<p class="description">
					<?php
					if ( $const_slug ) {
						esc_html_e( 'Adres wp-config.php içindeki FLXP_LOGIN_SLUG sabitinden geliyor; değiştirmek için sabiti düzenleyin ya da silin.', 'flx-speed' );
					} else {
						esc_html_e( 'Boş bırakılırsa WordPress\'in standart adresi (wp-login.php) kullanılır. 3-40 karakter: küçük harf, rakam, tire, alt çizgi. Tahmin edilmesi zor bir ad seçin (admin, login kullanılamaz).', 'flx-speed' );
					}
					?>
				</p>
			</td></tr>
			<tr><th scope="row"><?php esc_html_e( 'Eski adreslere gelen ziyaretçi', 'flx-speed' ); ?></th><td>
				<fieldset>
					<label><input type="radio" name="flxp[login_blocked]" value="404" <?php checked( '404', $s['login_blocked'] ); ?>> <?php esc_html_e( '404 sayfası göster (önerilen)', 'flx-speed' ); ?></label><br>
					<label><input type="radio" name="flxp[login_blocked]" value="home" <?php checked( 'home', $s['login_blocked'] ); ?>> <?php esc_html_e( 'Ana sayfaya yönlendir', 'flx-speed' ); ?></label>
				</fieldset>
				<p class="description"><?php esc_html_e( 'wp-login.php ve giriş yapmamış ziyaretçinin wp-admin istekleri için. admin-ajax.php, admin-post.php, REST API, wp-cron, WooCommerce hesabım girişi ve Flux App bağlantısı etkilenmez.', 'flx-speed' ); ?></p>
			</td></tr>
			<tr><th scope="row"><?php esc_html_e( 'Kurtarma', 'flx-speed' ); ?></th><td>
				<p class="description"><?php esc_html_e( 'Adresi unutursanız ya da giriş açılmazsa: dosya yöneticisinden (Hostinger hPanel › Dosya Yöneticisi) wp-config.php dosyasına aşağıdaki satırı ekleyin. Giriş yeniden wp-login.php olur; girdikten sonra adresi buradan düzeltip satırı silin.', 'flx-speed' ); ?></p>
				<code class="flxp-code">define( 'FLXP_LOGIN_DISABLE', true );</code>
			</td></tr>
		</table>
		<script>
		document.addEventListener('click',function(e){var b=e.target.closest('[data-flxp-copy]');if(!b)return;var t=document.getElementById(b.dataset.flxpCopy).textContent;(navigator.clipboard?navigator.clipboard.writeText(t):Promise.reject()).then(function(){var o=b.textContent;b.textContent=b.dataset.done;setTimeout(function(){b.textContent=o;},1500);}).catch(function(){window.prompt('',t);});});
		</script>
		<?php
	}

	/* ------------------------------------------------------------ İşlemler */

	public static function save() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( '', 403 );
		}
		check_admin_referer( 'flxp_save' );
		$in = isset( $_POST['flxp'] ) && is_array( $_POST['flxp'] ) ? wp_unslash( $_POST['flxp'] ) : array(); // phpcs:ignore
		$out  = flxp_sanitize_settings( $in );
		$err  = '';
		$prev = (string) flxp_settings( 'login_slug' );
		if ( ! defined( 'FLXP_LOGIN_SLUG' ) && array_key_exists( 'login_slug', $in ) ) {
			$slug = FLXP_Login_URL::clean( $in['login_slug'] );
			$ok   = '' === $slug ? true : FLXP_Login_URL::validate( $slug );
			if ( is_wp_error( $ok ) ) {
				$err = $ok->get_error_message();
			} else {
				$out['login_slug'] = $slug;
			}
		}
		update_option( 'flxp_settings', $out, false );
		if ( $out['login_slug'] !== $prev ) {
			flxp_login_slug_changed();
		}
		$args = $err ? array( 'flxp_err' => rawurlencode( $err ) ) : array( 'flxp_msg' => $out['login_slug'] !== $prev ? ( '' === $out['login_slug'] ? 'login_off' : 'login_on' ) : 'saved' );
		wp_safe_redirect( self::url( array_merge( array( 'tab' => 'settings' ), $args ) ) . ( $err || $out['login_slug'] !== $prev ? '#flxp-login' : '' ) );
		exit;
	}

	public static function clear() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( '', 403 );
		}
		check_admin_referer( 'flxp_clear' );
		FLXP_Store::clear();
		wp_safe_redirect( self::url( array( 'tab' => 'records', 'flxp_msg' => 'cleared' ) ) );
		exit;
	}

	public static function cleanup() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( '', 403 );
		}
		check_admin_referer( 'flxp_cleanup' );
		$job = isset( $_POST['job'] ) ? sanitize_key( $_POST['job'] ) : '';
		$n   = flxp_cleanup( $job );
		$arg = is_wp_error( $n ) ? array( 'flxp_err' => rawurlencode( $n->get_error_message() ) ) : array( 'flxp_done' => (int) $n );
		wp_safe_redirect( self::url( array_merge( array( 'tab' => 'database' ), $arg ) ) );
		exit;
	}

	public static function profile() {
		if ( ! flxp_can_view() ) {
			wp_die( '', 403 );
		}
		check_admin_referer( 'flxp_profile' );
		$url = isset( $_POST['url'] ) ? sanitize_text_field( wp_unslash( $_POST['url'] ) ) : '';
		$res = flxp_profile_url( $url );
		if ( is_wp_error( $res ) ) {
			wp_safe_redirect( self::url( array( 'tab' => 'records', 'flxp_err' => rawurlencode( $res->get_error_message() ) ) ) );
		} else {
			wp_safe_redirect( self::url( array( 'id' => $res['id'] ) ) );
		}
		exit;
	}
}
FLXP_Admin::init();
