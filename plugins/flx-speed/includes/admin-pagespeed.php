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
			'safe' => array( 'is-safe', __( 'Güvenli', 'flx-speed' ) ),
			'test' => array( 'is-test', __( 'Önce deneyin', 'flx-speed' ) ),
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
					<p class="flxp-ps-forced"><?php esc_html_e( 'Tema ya da ön ayar bu özelliği kod ile değiştiriyor (flxp_pagespeed_enabled); ayar geçerli olmayabilir.', 'flx-speed' ); ?></p>
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
			echo '<div class="notice notice-success is-dismissible"><p>' . esc_html__( 'Kaydedildi. Önbellek ve LiteSpeed sayfa önbelleği temizlendi; birkaç sayfayı gezip kontrol edin.', 'flx-speed' ) . '</p></div>';
		} elseif ( 'cleared' === $msg ) {
			echo '<div class="notice notice-success is-dismissible"><p>' . esc_html__( 'PageSpeed önbelleği temizlendi.', 'flx-speed' ) . '</p></div>';
		}
		?>
		<p class="flxp-ps-intro"><?php esc_html_e( 'Google PageSpeed / Lighthouse önerilerinin tema değiştirmeden yapılabilenleri. Her biri ayrı açılır; "Önce deneyin" olanları açtıktan sonra sitenin birkaç sayfasını (ana sayfa, yazı, bağış/ödeme) kontrol edin.', 'flx-speed' ); ?></p>
		<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="flxp-ps">
			<?php wp_nonce_field( 'flxp_ps_save' ); ?>
			<input type="hidden" name="action" value="flxp_ps_save">

			<section class="flxp-ps-card">
				<h2><?php esc_html_e( 'Temel', 'flx-speed' ); ?></h2>
				<?php
				self::row( 'ps_viewport', __( 'Yakınlaştırma kilidini kaldır', 'flx-speed' ), 'safe', __( 'Viewport etiketindeki user-scalable=no ve maximum-scale değerleri silinir; az gören kullanıcılar sayfayı büyütebilir. Erişilebilirlik puanını doğrudan etkiler.', 'flx-speed' ) );
				self::row( 'ps_emoji', __( 'Emoji betiğini kaldır', 'flx-speed' ), 'safe', __( 'WordPress\'in emoji algılama betiği ve stili yüklenmez. Modern tarayıcılar emojiyi kendisi çizer.', 'flx-speed' ) );
				?>
			</section>

			<section class="flxp-ps-card">
				<h2>CSS</h2>
				<?php
				ob_start();
				?>
				<div class="flxp-ps-sub">
					<fieldset>
						<legend><?php esc_html_e( 'Kapsam', 'flx-speed' ); ?></legend>
						<label><input type="radio" name="flxp[ps_css_scope]" value="theme" <?php checked( 'theme', $s['ps_css_scope'] ); ?>> <?php esc_html_e( 'Yalnız tema CSS\'i (önerilen)', 'flx-speed' ); ?></label>
						<label><input type="radio" name="flxp[ps_css_scope]" value="local" <?php checked( 'local', $s['ps_css_scope'] ); ?>> <?php esc_html_e( 'Tema + eklentiler (wp-content altındaki tüm CSS)', 'flx-speed' ); ?></label>
					</fieldset>
					<p>
						<label for="flxp-ps-inline"><?php esc_html_e( 'Satır içi sınırı', 'flx-speed' ); ?></label>
						<input id="flxp-ps-inline" type="number" min="0" max="500" name="flxp[ps_inline_kb]" value="<?php echo (int) $s['ps_inline_kb']; ?>" class="small-text"> KB
						<span class="description"><?php esc_html_e( 'Kırpılmış CSS bu boyuta kadar <head> içine yazılır; fazlası önbellekten ayrı dosya olarak yüklenir. 0 = hep dosya.', 'flx-speed' ); ?></span>
					</p>
					<p>
						<label for="flxp-ps-safelist"><?php esc_html_e( 'Her zaman korunan sınıflar', 'flx-speed' ); ?></label>
						<textarea id="flxp-ps-safelist" name="flxp[ps_safelist]" rows="3" class="large-text code" placeholder="menu-open&#10;swiper-*"><?php echo esc_textarea( $s['ps_safelist'] ); ?></textarea>
						<span class="description"><?php esc_html_e( 'Satır başına bir sınıf ya da id, * joker. JavaScript\'in sonradan eklediği bir görünüm bozulursa sınıfını buraya yazın. is-*, has-*, wp-* her zaman korunur.', 'flx-speed' ); ?></span>
					</p>
				</div>
				<?php
				$sub = ob_get_clean();
				self::row( 'ps_css', __( 'Kullanılmayan CSS\'i kırp ve satır içine al', 'flx-speed' ), 'test', __( 'Her sayfanın sınıf/id envanteri çıkarılır (HTML + sayfadaki betikler); CSS dosyasından o sayfada karşılığı olmayan kurallar atılır, kalan kısım <head> içine yazılır. Engelleyen CSS isteği kalkar, LCP ve FCP hızlanır. Aynı envanterli sayfalar aynı kopyayı paylaşır; dosya değişince kopyalar yenilenir, çok fazla farklı sayfa olursa tam dosyaya dönülür.', 'flx-speed' ), $sub );
				self::row( 'ps_min_css', __( 'CSS dosyalarını sıkıştır', 'flx-speed' ), 'safe', __( 'Sıkıştırılmamış (.min olmayan) yerel CSS dosyaları için güncel .min kardeşi ya da yorumları ve boşlukları atılmış kopya kullanılır.', 'flx-speed' ) );
				?>
			</section>

			<section class="flxp-ps-card">
				<h2>JavaScript</h2>
				<?php
				self::row( 'ps_min_js', __( 'JS dosyalarını sıkıştır', 'flx-speed' ), 'test', __( 'Tema ve eklenti betikleri için .min kardeşi kaynaktan yeniyse o kullanılır; değilse yorumları ve satır boşlukları atılmış kopya üretilir (satır sonları korunur). Kendi konumundan parça yükleyen paketlere ve çevirisi olan betiklere dokunulmaz.', 'flx-speed' ) );
				ob_start();
				?>
				<div class="flxp-ps-sub">
					<p class="description"><?php esc_html_e( 'jQuery her zaman engelleyen kalır (sayfa içindeki eski betikler ona doğrudan bağlı olabilir). WordPress, sonradan çalışan satır içi betiği olan ya da engelleyen bir betiğin bağımlı olduğu betiklerde defer\'i kendisi geri alır.', 'flx-speed' ); ?></p>
				</div>
				<?php
				$sub = ob_get_clean();
				self::row( 'ps_defer', __( 'Head betiklerini ertele (defer)', 'flx-speed' ), 'test', __( 'Sayfanın <head> bölümünde yüklenen ve çizimi bekleten betiklere defer verilir (ör. dil önerisi, analiz betikleri).', 'flx-speed' ), $sub );
				?>
			</section>

			<section class="flxp-ps-card">
				<h2><?php esc_html_e( 'Yazı tipleri', 'flx-speed' ); ?></h2>
				<?php
				self::row( 'ps_fonts', __( 'Kullanılmayan yazı tiplerini çıkar', 'flx-speed' ), 'safe', __( 'theme.json\'da tanımlı ama sayfanın CSS\'inde geçmeyen yazı tipi aileleri (@font-face) sayfaya yazılmaz. Kullanım belirlenemezse hiçbiri çıkarılmaz.', 'flx-speed' ) );
				self::row( 'ps_preload', __( 'İlk ekran yazı tipini önden yükle (preload)', 'flx-speed' ), 'test', __( 'Kullanılan en fazla iki ailenin normal (400) woff2 dosyası <link rel="preload"> ile istenir; metin daha erken çizilir. Yanlış ağırlık önden yüklenirse boşa indirme olur; PageSpeed\'de "kullanılmayan preload" uyarısı çıkarsa kapatın.', 'flx-speed' ) );
				?>
			</section>

			<section class="flxp-ps-card">
				<h2><?php esc_html_e( 'Hariç tutma', 'flx-speed' ); ?></h2>
				<p>
					<label for="flxp-ps-exclude"><strong><?php esc_html_e( 'Dokunulmayacak dosyalar', 'flx-speed' ); ?></strong></label>
					<textarea id="flxp-ps-exclude" name="flxp[ps_exclude]" rows="3" class="large-text code" placeholder="woocommerce&#10;flx-donate"><?php echo esc_textarea( $s['ps_exclude'] ); ?></textarea>
					<span class="description"><?php esc_html_e( 'Satır başına tanıtıcı (handle) ya da adresin bir parçası. Kırpma, sıkıştırma ve defer bu dosyalara uygulanmaz.', 'flx-speed' ); ?></span>
				</p>
				<p>
					<label for="flxp-ps-skip"><strong><?php esc_html_e( 'Dokunulmayacak sayfalar', 'flx-speed' ); ?></strong></label>
					<textarea id="flxp-ps-skip" name="flxp[ps_skip_urls]" rows="2" class="large-text code" placeholder="/odeme/&#10;/hesabim/"><?php echo esc_textarea( $s['ps_skip_urls'] ); ?></textarea>
					<span class="description"><?php esc_html_e( 'Satır başına adres parçası. Bu sayfalarda hiçbir PageSpeed iyileştirmesi yapılmaz.', 'flx-speed' ); ?></span>
				</p>
			</section>
			<?php submit_button( __( 'Kaydet ve önbelleği temizle', 'flx-speed' ) ); ?>
		</form>

		<div class="flxp-env">
			<section class="flxp-ps-card">
				<h2><?php esc_html_e( 'Durum', 'flx-speed' ); ?></h2>
				<table class="widefat striped flxp-kv"><tbody>
					<tr><th><?php esc_html_e( 'Önbellek', 'flx-speed' ); ?></th><td>
						<?php
						/* translators: 1: CSS dosya sayısı, 2: JS dosya sayısı, 3: boyut */
						echo esc_html( sprintf( __( '%1$d CSS, %2$d JS kopyası · %3$s', 'flx-speed' ), $stats['css'], $stats['js'], size_format( $stats['bytes'] ) ) );
						?>
						<code>wp-content/uploads/flx-speed/</code>
					</td></tr>
					<tr><th><?php esc_html_e( 'Karşılaştır', 'flx-speed' ); ?></th><td>
						<a href="<?php echo esc_url( $home ); ?>" target="_blank" rel="noopener"><?php esc_html_e( 'İyileştirilmiş', 'flx-speed' ); ?></a> ·
						<a href="<?php echo esc_url( add_query_arg( 'flxp_off', '1', $home ) ); ?>" target="_blank" rel="noopener"><?php esc_html_e( 'Kapalı (?flxp_off=1)', 'flx-speed' ); ?></a>
						<p class="description"><?php esc_html_e( 'Adresin sonuna ?flxp_off=1 eklenince sayfa iyileştirmesiz açılır; bir görünüm farkı varsa sebebi buradadır.', 'flx-speed' ); ?></p>
					</td></tr>
					<tr><th><?php esc_html_e( 'Ölçüm', 'flx-speed' ); ?></th><td>
						<?php if ( function_exists( 'flxseo_admin_url' ) ) : ?>
							<a href="<?php echo esc_url( flxseo_admin_url( 'denetim' ) ); ?>"><?php esc_html_e( 'Flux SEO › Site denetimi', 'flx-speed' ); ?></a>
							<p class="description"><?php esc_html_e( 'Site denetimi bu ayarları kendisi önerir ve tek tıkla açar.', 'flx-speed' ); ?></p>
						<?php else : ?>
							<a href="<?php echo esc_url( 'https://pagespeed.web.dev/analysis?url=' . rawurlencode( $home ) ); ?>" target="_blank" rel="noopener">PageSpeed Insights</a>
						<?php endif; ?>
					</td></tr>
				</tbody></table>
				<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="flxp-ps-clear">
					<?php wp_nonce_field( 'flxp_ps_clear' ); ?>
					<input type="hidden" name="action" value="flxp_ps_clear">
					<button class="button"><?php esc_html_e( 'Önbelleği temizle', 'flx-speed' ); ?></button>
				</form>
			</section>

			<section class="flxp-ps-card">
				<h2><?php esc_html_e( 'GPU\'da çalışmayan animasyonlar', 'flx-speed' ); ?></h2>
				<p class="description"><?php esc_html_e( 'Lighthouse "bileşik olmayan animasyonlar" uyarısı: transform ve opacity dışındaki özellikler (stroke-dashoffset, background, width…) her karede yeniden çizim yapar. Bunu ayar düzeltemez; tema ya da ön ayarda transform/opacity ile yeniden yazılmalı.', 'flx-speed' ); ?></p>
				<?php if ( $anim ) : ?>
					<table class="widefat striped flxp-list"><thead><tr>
						<th><?php esc_html_e( 'Animasyon', 'flx-speed' ); ?></th>
						<th><?php esc_html_e( 'Özellikler', 'flx-speed' ); ?></th>
						<th><?php esc_html_e( 'Dosya', 'flx-speed' ); ?></th>
					</tr></thead><tbody>
					<?php foreach ( $anim as $a ) : ?>
						<tr>
							<td><code>@keyframes <?php echo esc_html( $a['name'] ); ?></code></td>
							<td><?php echo esc_html( implode( ', ', $a['props'] ) ); ?></td>
							<td class="flxp-url"><?php echo esc_html( $a['file'] ); ?><?php echo $a['reduced'] ? '' : ' <span class="flxp-flag flxp-flag-slow">' . esc_html__( 'prefers-reduced-motion yok', 'flx-speed' ) . '</span>'; ?></td>
						</tr>
					<?php endforeach; ?>
					</tbody></table>
				<?php else : ?>
					<p class="flxp-ps-ok"><?php esc_html_e( 'Sorun yok: görülen CSS dosyalarındaki animasyonlar transform/opacity kullanıyor.', 'flx-speed' ); ?></p>
				<?php endif; ?>
				<p class="description">
					<?php
					if ( is_array( $seen ) && ! empty( $seen['t'] ) ) {
						/* translators: 1: dosya sayısı, 2: "… önce" */
						echo esc_html( sprintf( __( 'Ön yüzde görülen %1$d CSS dosyası tarandı (%2$s).', 'flx-speed' ), count( (array) $seen['files'] ), function_exists( 'flx_time_ago' ) ? flx_time_ago( (int) $seen['t'] ) : gmdate( 'Y-m-d H:i', (int) $seen['t'] ) ) );
					} else {
						esc_html_e( 'Henüz ön yüz görülmedi; tema klasörü tarandı. Siteyi bir kez açınca sayfanın yüklediği dosyalar taranır.', 'flx-speed' );
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
