<?php
/**
 * SEO › Site denetimi ekranı, ayarları ve AJAX uçları.
 *
 * @package flx-seo
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/** Menüde kritik + yüksek bulgu sayısı. */
function flxseo_audit_menu_badge() {
	$s = get_option( 'flxseo_audit' );
	if ( ! is_array( $s ) ) {
		return '';
	}
	$c = flxseo_audit_report()['counts'];
	$n = (int) $c['critical'] + (int) $c['high'];
	return $n ? ' <span class="awaiting-mod count-' . $n . '"><span class="pending-count">' . number_format_i18n( $n ) . '</span></span>' : '';
}

/** Ajan uyumu özeti: geçen / toplam. */
function flxseo_audit_agentic( array $report ) {
	$ids    = wp_list_pluck( $report['findings'], 'id' );
	$checks = array(
		'llms'   => array( __( 'llms.txt önerilere uygun', 'flx-seo' ), ! array_intersect( $ids, array( 'llms-txt', 'llms-txt-format' ) ) ),
		'tree'   => array( __( 'Erişilebilirlik ağacı düzgün', 'flx-seo' ), ! array_intersect( $ids, array( 'aria-required-children', 'aria-required-parent' ) ) ),
		'webmcp' => array( __( 'Formlar WebMCP aracı', 'flx-seo' ), ! in_array( 'webmcp-forms', $ids, true ) ),
	);
	if ( ! empty( $report['psi'] ) ) {
		$checks['cls'] = array( __( 'Yerleşim kayması düşük (CLS)', 'flx-seo' ), ! in_array( 'cumulative-layout-shift', $ids, true ) );
	}
	return $checks;
}

function flxseo_audit_admin_page() {
	$r      = flxseo_audit_report();
	$prios  = flxseo_audit_priorities();
	$cats   = flxseo_audit_categories();
	$owners = flxseo_audit_owners();
	$url    = $r['local']['url'] ?? home_url( '/' );
	$o      = wp_parse_args( (array) get_option( 'flxseo', array() ), flxseo_defaults() );
	flxseo_audit_flash();
	$sources = array(
		'local'       => __( 'Site içi', 'flx-seo' ),
		'psi-mobile'  => __( 'PageSpeed mobil', 'flx-seo' ),
		'psi-desktop' => __( 'PageSpeed masaüstü', 'flx-seo' ),
	);
	$agentic = flxseo_audit_agentic( $r );
	$ag_pass = count( array_filter( $agentic, fn( $c ) => $c[1] ) );
	flxseo_audit_css();
	?>
	<div class="flxseo-au" id="flxseo-au">
		<section class="au-card au-head">
			<div class="au-head__text">
				<h2><?php esc_html_e( 'Site denetimi', 'flx-seo' ); ?></h2>
				<p><?php esc_html_e( 'PageSpeed Insights\'ın beş başlığı (Performans, Erişilebilirlik, En iyi uygulamalar, SEO, Ajan uyumu) önceliğe göre. Site içi denetim anahtarsızdır; PageSpeed ölçümü Google\'ın gerçek Lighthouse sonucunu ekler. Otomatik denetim, API anahtarı ve güvenlik başlıkları Ayarlar sekmesinde.', 'flx-seo' ); ?></p>
				<p class="au-meta">
					<?php if ( ! empty( $r['local']['time'] ) ) : ?>
						<?php
						/* translators: 1: zaman ("5 dakika önce"), 2: adres */
						printf( esc_html__( 'Site içi denetim: %1$s · %2$s', 'flx-seo' ), esc_html( flx_time_ago( $r['local']['time'] ) ), '<a href="' . esc_url( $r['local']['url'] ) . '" target="_blank" rel="noopener">' . esc_html( $r['local']['url'] ) . '</a>' );
						?>
					<?php else : ?>
						<?php esc_html_e( 'Henüz denetim yapılmadı.', 'flx-seo' ); ?>
					<?php endif; ?>
				</p>
			</div>
			<form class="au-run" id="au-run" onsubmit="return false">
				<label class="screen-reader-text" for="au-url"><?php esc_html_e( 'Denetlenecek adres', 'flx-seo' ); ?></label>
				<input type="url" id="au-url" class="regular-text" value="<?php echo esc_attr( $url ); ?>" placeholder="<?php echo esc_attr( home_url( '/' ) ); ?>">
				<button type="button" class="button button-primary" data-run="local"><?php esc_html_e( 'Site içi denetim', 'flx-seo' ); ?></button>
				<button type="button" class="button" data-run="psi"><?php esc_html_e( 'PageSpeed ile ölç', 'flx-seo' ); ?></button>
			</form>
			<p class="au-status" id="au-status" role="status" aria-live="polite"></p>
		</section>

		<section class="au-scores">
			<?php
			foreach ( array( 'mobile' => __( 'Mobil', 'flx-seo' ), 'desktop' => __( 'Masaüstü', 'flx-seo' ) ) as $sk => $sl ) :
				$p = $r['psi'][ $sk ] ?? null;
				?>
				<div class="au-card au-strat">
					<h3><?php echo esc_html( $sl ); ?>
						<?php if ( $p ) : ?>
							<span class="au-meta"><?php echo esc_html( flx_time_ago( $p['time'] ) . ( $p['lighthouse'] ? ' · Lighthouse ' . $p['lighthouse'] : '' ) ); ?></span>
						<?php endif; ?>
					</h3>
					<?php if ( ! $p ) : ?>
						<p class="au-empty"><?php esc_html_e( 'PageSpeed ölçümü yok. "PageSpeed ile ölç" düğmesine basın (mobil + masaüstü yaklaşık 1 dakika).', 'flx-seo' ); ?></p>
					<?php else : ?>
						<div class="au-rings">
							<?php foreach ( $p['scores'] as $cid => $sc ) : ?>
								<?php echo flxseo_audit_ring( $sc['score'], $cats[ $cid ] ?? $sc['title'], false !== strpos( $cid, 'agent' ) ? $sc['passed'] . '/' . $sc['total'] : '' ); // phpcs:ignore WordPress.Security.EscapeOutput ?>
							<?php endforeach; ?>
						</div>
						<?php if ( $p['metrics'] ) : ?>
							<dl class="au-metrics">
								<?php foreach ( $p['metrics'] as $mid => $m ) : ?>
									<div class="au-metric au-tone-<?php echo esc_attr( flxseo_audit_tone( null === $m['score'] ? null : $m['score'] * 100 ) ); ?>">
										<dt><?php echo esc_html( $m['title'] ); ?></dt>
										<dd><?php echo esc_html( $m['value'] ); ?></dd>
									</div>
								<?php endforeach; ?>
							</dl>
						<?php endif; ?>
					<?php endif; ?>
				</div>
			<?php endforeach; ?>
			<div class="au-card au-strat au-agentic">
				<h3><?php esc_html_e( 'Ajan uyumu', 'flx-seo' ); ?> <span class="au-pill au-tone-<?php echo esc_attr( $ag_pass === count( $agentic ) ? 'good' : 'mid' ); ?>"><?php echo esc_html( $ag_pass . '/' . count( $agentic ) ); ?></span></h3>
				<ul class="au-checks">
					<?php foreach ( $agentic as $c ) : ?>
						<li class="<?php echo $c[1] ? 'is-ok' : 'is-bad'; ?>"><span aria-hidden="true"><?php echo $c[1] ? '✓' : '✕'; ?></span> <?php echo esc_html( $c[0] ); ?></li>
					<?php endforeach; ?>
				</ul>
				<p class="au-meta"><a href="<?php echo esc_url( home_url( '/llms.txt' ) ); ?>" target="_blank" rel="noopener">/llms.txt</a> · <?php echo esc_html( flxseo_mcp_server_on() ? __( 'MCP sunucusu açık', 'flx-seo' ) : __( 'MCP sunucusu kapalı', 'flx-seo' ) ); ?></p>
			</div>
		</section>

		<section class="au-card au-findings">
			<div class="au-bar">
				<h2><?php esc_html_e( 'Öneriler', 'flx-seo' ); ?> <span class="au-meta"><?php echo esc_html( number_format_i18n( count( $r['findings'] ) ) ); ?></span></h2>
				<div class="au-filters" role="group" aria-label="<?php esc_attr_e( 'Önceliğe göre süz', 'flx-seo' ); ?>">
					<button type="button" class="au-chip is-on" data-prio="" aria-pressed="true"><?php esc_html_e( 'Tümü', 'flx-seo' ); ?></button>
					<?php foreach ( $prios as $pk => $pv ) : ?>
						<button type="button" class="au-chip au-p-<?php echo esc_attr( $pk ); ?>" data-prio="<?php echo esc_attr( $pk ); ?>" aria-pressed="false"><?php echo esc_html( $pv[1] ); ?> <b><?php echo (int) $r['counts'][ $pk ]; ?></b></button>
					<?php endforeach; ?>
				</div>
				<label class="screen-reader-text" for="au-cat"><?php esc_html_e( 'Başlık', 'flx-seo' ); ?></label>
				<select id="au-cat">
					<option value=""><?php esc_html_e( 'Tüm başlıklar', 'flx-seo' ); ?></option>
					<?php foreach ( $cats as $ck => $cl ) : ?>
						<option value="<?php echo esc_attr( $ck ); ?>"><?php echo esc_html( $cl ); ?></option>
					<?php endforeach; ?>
				</select>
			</div>
			<?php if ( ! $r['findings'] ) : ?>
				<p class="au-empty"><?php echo esc_html( empty( $r['local']['time'] ) ? __( 'Denetimi başlatın; öneriler burada önceliğe göre sıralanır.', 'flx-seo' ) : __( 'Öneri yok. Her şey yolunda.', 'flx-seo' ) ); ?></p>
			<?php endif; ?>
			<div class="au-list">
				<?php foreach ( $r['findings'] as $fd ) : ?>
					<?php $all_cats = array_unique( array_merge( array( $fd['category'] ), (array) $fd['also'] ) ); ?>
					<details class="au-f au-p-<?php echo esc_attr( $fd['priority'] ); ?>" data-prio="<?php echo esc_attr( $fd['priority'] ); ?>" data-cat="<?php echo esc_attr( implode( ' ', $all_cats ) ); ?>">
						<summary>
							<span class="au-badge au-p-<?php echo esc_attr( $fd['priority'] ); ?>"><?php echo esc_html( $prios[ $fd['priority'] ][1] ?? $fd['priority'] ); ?></span>
							<span class="au-f__title"><?php echo esc_html( $fd['title'] ); ?><?php echo '' !== $fd['detail'] ? ' <span class="au-f__detail">' . esc_html( $fd['detail'] ) . '</span>' : ''; ?></span>
							<span class="au-tags">
								<?php foreach ( $all_cats as $ck ) : ?>
									<span class="au-tag"><?php echo esc_html( $cats[ $ck ] ?? $ck ); ?></span>
								<?php endforeach; ?>
							</span>
						</summary>
						<div class="au-f__body">
							<?php if ( $fd['fix'] ) : ?>
								<p class="au-fix"><strong><?php esc_html_e( 'Nasıl düzeltilir:', 'flx-seo' ); ?></strong> <?php echo esc_html( $fd['fix'] ); ?></p>
							<?php endif; ?>
							<?php if ( $fd['items'] ) : ?>
								<ul class="au-items">
									<?php foreach ( $fd['items'] as $it ) : ?>
										<li><code><?php echo esc_html( $it ); ?></code></li>
									<?php endforeach; ?>
								</ul>
							<?php endif; ?>
							<p class="au-meta">
								<?php if ( $fd['owner'] ) : ?>
									<?php
									/* translators: %s: kim düzeltir (Tema, İçerik, Sunucu…) */
									printf( esc_html__( 'Düzeltme yeri: %s', 'flx-seo' ), esc_html( $owners[ $fd['owner'] ] ?? $fd['owner'] ) );
									?>
									·
								<?php endif; ?>
								<?php echo esc_html( implode( ', ', array_map( fn( $s ) => $sources[ $s ] ?? $s, $fd['sources'] ) ) ); ?>
								· <code><?php echo esc_html( $fd['id'] ); ?></code>
							</p>
							<?php if ( ! empty( $fd['fix_id'] ) ) : ?>
								<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
									<input type="hidden" name="action" value="flxseo_audit_fix">
									<input type="hidden" name="fix" value="<?php echo esc_attr( $fd['fix_id'] ); ?>">
									<?php wp_nonce_field( 'flxseo_audit_fix' ); ?>
									<button class="button button-primary"><?php echo esc_html( flxseo_system_fixes()[ $fd['fix_id'] ]['label'] ?? __( 'Düzelt', 'flx-seo' ) ); ?></button>
								</form>
							<?php endif; ?>
						</div>
					</details>
				<?php endforeach; ?>
			</div>
		</section>

		<?php flxseo_audit_history_card(); ?>

	</div>
	<?php
	flxseo_audit_js();
}

/** Kaydet/düzelt sonrası tek seferlik bildirim. */
function flxseo_audit_flash() {
	$msg = get_transient( 'flxseo_audit_msg_' . get_current_user_id() );
	if ( $msg ) {
		delete_transient( 'flxseo_audit_msg_' . get_current_user_id() );
		printf( '<div class="notice notice-%s is-dismissible"><p>%s</p></div>', esc_attr( $msg[0] ), esc_html( $msg[1] ) );
	}
}

/**
 * SEO › Ayarlar: site denetimi ayarları (PageSpeed anahtarı, otomatik denetim, ajan uyumu, güvenlik başlıkları)
 * ve yeni site yapım rehberi.
 */
function flxseo_audit_settings_page() {
	$o = wp_parse_args( (array) get_option( 'flxseo', array() ), flxseo_defaults() );
	flxseo_audit_flash();
	flxseo_audit_css();
	?>
	<div class="flxseo-au" id="flxseo-au">
		<section class="au-grid2">
			<form class="au-card au-settings" data-freq="<?php echo esc_attr( $o['audit_freq'] ); ?>" method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
				<h2><?php esc_html_e( 'Site denetimi ve ajan uyumu', 'flx-seo' ); ?></h2>
				<input type="hidden" name="action" value="flxseo_audit_settings">
				<?php wp_nonce_field( 'flxseo_audit_settings' ); ?>
				<p>
					<label for="au-key"><strong><?php esc_html_e( 'PageSpeed Insights API anahtarı', 'flx-seo' ); ?></strong></label><br>
					<input type="password" id="au-key" name="psi_key" class="regular-text code" autocomplete="off" value="" placeholder="<?php echo esc_attr( flxseo_psi_key() ? __( 'Kayıtlı (değiştirmek için yazın)', 'flx-seo' ) : 'AIza…' ); ?>">
					<?php if ( flxseo_psi_key() ) : ?>
						<label><input type="checkbox" name="psi_key_clear" value="1"> <?php esc_html_e( 'Anahtarı sil', 'flx-seo' ); ?></label>
					<?php endif; ?>
					<span class="description"><?php esc_html_e( 'İsteğe bağlı ama önerilir: anahtarsız kota paylaşımlı ve çabuk dolar. Google Cloud Console › API\'ler › "PageSpeed Insights API"yi etkinleştirip ücretsiz anahtar oluşturun.', 'flx-seo' ); ?></span>
				</p>
				<fieldset>
					<legend><strong><?php esc_html_e( 'Ajan uyumu', 'flx-seo' ); ?></strong></legend>
					<label><input type="checkbox" name="o[llms]" value="1" <?php checked( $o['llms'] ); ?>> <?php esc_html_e( '/llms.txt üret (site adı, açıklama, menü sayfaları, son içerikler)', 'flx-seo' ); ?></label><br>
					<label for="au-intro" class="au-sub"><?php esc_html_e( 'llms.txt giriş metni (isteğe bağlı)', 'flx-seo' ); ?></label>
					<textarea id="au-intro" name="o[llms_intro]" rows="3" class="large-text" placeholder="<?php esc_attr_e( 'Ör. Kurumumuz kimdir, ne yapar, ajanlar hangi sayfaları öncelikle okumalı.', 'flx-seo' ); ?>"><?php echo esc_textarea( $o['llms_intro'] ); ?></textarea>
					<label><input type="checkbox" name="o[webmcp]" value="1" <?php checked( $o['webmcp'] ); ?>> <?php esc_html_e( 'WebMCP: arama formunu tarayıcı ajanlarına araç olarak tanıt', 'flx-seo' ); ?></label>
				</fieldset>
				<fieldset>
					<legend><strong><?php esc_html_e( 'Güvenlik başlıkları', 'flx-seo' ); ?></strong></legend>
					<label><input type="checkbox" name="o[hdr_frame]" value="1" <?php checked( $o['hdr_frame'] ); ?>> <?php esc_html_e( 'Clickjacking koruması (X-Frame-Options: SAMEORIGIN, frame-ancestors \'self\')', 'flx-seo' ); ?></label><br>
					<label><input type="checkbox" name="o[hdr_coop]" value="1" <?php checked( $o['hdr_coop'] ); ?>> <?php esc_html_e( 'Pencere yalıtımı (Cross-Origin-Opener-Policy: same-origin-allow-popups)', 'flx-seo' ); ?></label><br>
					<label><input type="checkbox" name="o[hdr_csp]" value="1" <?php checked( $o['hdr_csp'] ); ?>> <?php esc_html_e( 'Temel CSP (object-src \'none\'; base-uri \'self\'; upgrade-insecure-requests)', 'flx-seo' ); ?></label><br>
					<label><input type="checkbox" name="o[hsts_sub]" value="1" <?php checked( $o['hsts_sub'] ); ?>> <?php esc_html_e( 'HSTS alt alan adlarını kapsasın (includeSubDomains)', 'flx-seo' ); ?></label><br>
					<label><input type="checkbox" name="o[hsts_preload]" value="1" <?php checked( $o['hsts_preload'] ); ?>> <?php esc_html_e( 'HSTS preload (geri alması aylar sürer; tüm alt alan adları HTTPS olmalı)', 'flx-seo' ); ?></label>
				</fieldset>
				<fieldset>
					<legend><strong><?php esc_html_e( 'Otomatik denetim', 'flx-seo' ); ?></strong></legend>
					<span class="au-sched">
						<label><?php esc_html_e( 'Sıklık', 'flx-seo' ); ?>
							<select name="o[audit_freq]" id="au-freq">
								<?php foreach ( flxseo_audit_freqs() as $k => $l ) : ?>
									<option value="<?php echo esc_attr( $k ); ?>" <?php selected( $o['audit_freq'], $k ); ?>><?php echo esc_html( $l ); ?></option>
								<?php endforeach; ?>
							</select>
						</label>
						<label class="au-day"><?php esc_html_e( 'Gün', 'flx-seo' ); ?>
							<select name="o[audit_day]">
								<?php foreach ( flxseo_audit_days() as $k => $l ) : ?>
									<option value="<?php echo esc_attr( $k ); ?>" <?php selected( (string) $o['audit_day'], (string) $k ); ?>><?php echo esc_html( $l ); ?></option>
								<?php endforeach; ?>
							</select>
						</label>
						<label><?php esc_html_e( 'Saat', 'flx-seo' ); ?>
							<select name="o[audit_hour]">
								<?php for ( $hh = 0; $hh < 24; $hh++ ) : ?>
									<option value="<?php echo (int) $hh; ?>" <?php selected( (int) $o['audit_hour'], $hh ); ?>><?php echo esc_html( sprintf( '%02d:00', $hh ) ); ?></option>
								<?php endfor; ?>
							</select>
						</label>
					</span>
					<span class="description"><?php esc_html_e( 'Arka planda çalışır, sonuç Rapor geçmişine yazılır. Saat sitenin saat dilimine göredir; site o saatte ziyaret almazsa ilk istekte başlar.', 'flx-seo' ); ?></span>
					<label><input type="checkbox" name="o[audit_psi]" value="1" <?php checked( $o['audit_psi'] ); ?>> <?php esc_html_e( 'PageSpeed Insights (mobil + masaüstü) da çalışsın (API anahtarı gerekir)', 'flx-seo' ); ?></label><br>
					<label><input type="checkbox" name="o[audit_mail]" value="1" <?php checked( $o['audit_mail'] ); ?>> <?php esc_html_e( 'Yeni kritik ya da yüksek öncelikli öneri çıkınca e-posta gönder', 'flx-seo' ); ?></label>
					<label for="au-mail" class="au-sub"><?php esc_html_e( 'E-posta adresi (boşsa site yöneticisi)', 'flx-seo' ); ?></label>
					<input type="email" id="au-mail" name="o[audit_mail_to]" class="regular-text" value="<?php echo esc_attr( $o['audit_mail_to'] ); ?>" placeholder="<?php echo esc_attr( get_option( 'admin_email' ) ); ?>">
				</fieldset>
				<?php submit_button( __( 'Kaydet', 'flx-seo' ), 'primary', 'submit', false ); ?>
			</form>

			<div class="au-card au-guide">
				<h2><?php esc_html_e( 'Yeni site yapım rehberi', 'flx-seo' ); ?></h2>
				<p><?php esc_html_e( 'Yeni sayfa, tema ya da ön ayar yaparken SEO, performans, erişilebilirlik, güvenlik ve ajan uyumu için kurallar ve kontrol listesi. Yapay zekâ ajanları aynı rehberi MCP aracı site_build_guide ile, Flux AI sohbeti de araç olarak alır.', 'flx-seo' ); ?></p>
				<p>
					<a class="button" href="<?php echo esc_url( wp_nonce_url( admin_url( 'admin-post.php?action=flxseo_guide&lang=tr' ), 'flxseo_guide' ) ); ?>"><?php esc_html_e( 'Türkçe (.md) indir', 'flx-seo' ); ?></a>
					<a class="button" href="<?php echo esc_url( wp_nonce_url( admin_url( 'admin-post.php?action=flxseo_guide&lang=en' ), 'flxseo_guide' ) ); ?>"><?php esc_html_e( 'İngilizce (.md) indir', 'flx-seo' ); ?></a>
				</p>
				<ul class="au-toc">
					<?php foreach ( flxseo_guide_sections() as $sec ) : ?>
						<li><?php echo esc_html( $sec ); ?></li>
					<?php endforeach; ?>
				</ul>
			</div>
		</section>
	</div>
	<?php
	flxseo_audit_js();
}

function flxseo_audit_tone( $score ) {
	if ( null === $score ) {
		return 'na';
	}
	return $score >= 90 ? 'good' : ( $score >= 50 ? 'mid' : 'bad' );
}

/** Puan halkası (SVG). */
function flxseo_audit_ring( $score, $label, $text = '' ) {
	$tone = flxseo_audit_tone( $score );
	$val  = null === $score ? 0 : max( 0, min( 100, (int) $score ) );
	$c    = 2 * M_PI * 26;
	return sprintf(
		'<figure class="au-ring au-tone-%1$s"><svg viewBox="0 0 64 64" aria-hidden="true"><circle cx="32" cy="32" r="26" class="au-ring__bg"/><circle cx="32" cy="32" r="26" class="au-ring__fg" stroke-dasharray="%2$.1f %3$.1f" transform="rotate(-90 32 32)"/></svg><span class="au-ring__val">%4$s</span><figcaption>%5$s</figcaption></figure>',
		esc_attr( $tone ),
		$c * $val / 100,
		$c,
		esc_html( '' !== $text ? $text : ( null === $score ? '–' : (string) $val ) ),
		esc_html( $label )
	);
}

function flxseo_audit_css() {
	?>
	<style>
	.flxseo-au{--au-card:var(--flxa-card,#fff);--au-border:var(--flxa-border,#dcdcde);--au-text:var(--flxa-text,#1d2327);--au-muted:var(--flxa-muted,#646970);--au-good:var(--flxa-success,#0a7d32);--au-mid:var(--flxa-warn,#b26200);--au-bad:var(--flxa-error,#c62828);--au-info:var(--flxa-info,#2271b1);--au-soft:var(--flxa-surface-2,#f6f7f7);--au-radius:var(--flxa-radius,10px);color:var(--au-text);margin-top:16px;display:grid;gap:16px;max-width:1280px}
	.flxseo-au>*,.au-scores>*,.au-grid2>*,.au-head>*{min-width:0}.au-f__title,.au-f summary{overflow-wrap:anywhere}.au-status:empty{display:none}
	.flxseo-au .au-card{background:var(--au-card);border:1px solid var(--au-border);border-radius:var(--au-radius);padding:16px 20px}
	.flxseo-au h2{margin:0 0 6px;font-size:18px;color:var(--au-text)}.flxseo-au h3{margin:0 0 12px;font-size:15px;color:var(--au-text);display:flex;gap:8px;align-items:center;flex-wrap:wrap}
	.flxseo-au p{margin:6px 0}.flxseo-au .au-meta{color:var(--au-muted);font-size:12px;font-weight:400}
	.au-head{display:grid;grid-template-columns:1fr auto;gap:12px 24px;align-items:center}.au-head__text p{max-width:70ch}
	.au-run{display:flex;gap:8px;flex-wrap:wrap;align-items:center}.au-run input{min-width:260px}
	.au-status{grid-column:1/-1;margin:0;font-weight:600;min-height:1.2em}.au-status.is-err{color:var(--au-bad)}
	.au-scores{display:grid;grid-template-columns:repeat(auto-fit,minmax(300px,1fr));gap:16px}
	.au-rings{display:flex;flex-wrap:wrap;gap:12px 18px}
	.au-ring{margin:0;width:78px;text-align:center;position:relative}.au-ring svg{width:64px;height:64px;display:block;margin:0 auto}
	.au-ring__bg{fill:none;stroke:var(--au-soft);stroke-width:6}.au-ring__fg{fill:none;stroke:currentColor;stroke-width:6;stroke-linecap:round}
	.au-ring__val{position:absolute;top:0;left:0;right:0;height:64px;display:flex;align-items:center;justify-content:center;font-weight:700;font-size:17px}
	.au-ring figcaption{font-size:12px;color:var(--au-muted);margin-top:4px;line-height:1.3}
	.au-tone-good{color:var(--au-good)}.au-tone-mid{color:var(--au-mid)}.au-tone-bad{color:var(--au-bad)}.au-tone-na{color:var(--au-muted)}
	.au-metrics{display:grid;grid-template-columns:repeat(auto-fit,minmax(120px,1fr));gap:8px;margin:14px 0 0}
	.au-metric{background:var(--au-soft);border-radius:8px;padding:8px 10px;border-left:3px solid currentColor}.au-metric dt{font-size:11px;color:var(--au-muted)}.au-metric dd{margin:2px 0 0;font-weight:700;font-size:15px}
	.au-pill{display:inline-block;border-radius:999px;padding:1px 10px;font-size:12px;border:1px solid currentColor;font-weight:700}
	.au-checks{margin:0;padding:0;list-style:none;display:grid;gap:6px}.au-checks .is-ok span{color:var(--au-good)}.au-checks .is-bad span{color:var(--au-bad)}.au-checks span{display:inline-block;width:18px;font-weight:700}
	.au-bar{display:flex;flex-wrap:wrap;gap:10px 16px;align-items:center;margin-bottom:12px}.au-bar h2{margin:0;margin-right:auto}
	.au-filters{display:flex;flex-wrap:wrap;gap:6px}
	.au-chip{border:1px solid var(--au-border);background:var(--au-card);color:var(--au-text);border-radius:999px;padding:4px 12px;cursor:pointer;font-size:13px;min-height:32px}
	.au-chip.is-on{background:var(--au-text);color:var(--au-card);border-color:var(--au-text)}.au-chip b{margin-left:4px}
	.au-list{display:grid;gap:8px}
	.au-f{border:1px solid var(--au-border);border-left:4px solid var(--au-p);border-radius:8px;background:var(--au-card)}
	.au-f[hidden]{display:none}
	.au-f summary{display:flex;gap:10px;align-items:center;padding:10px 14px;cursor:pointer;list-style:none;flex-wrap:wrap}.au-f summary::-webkit-details-marker{display:none}
	.au-f summary:focus-visible{outline:2px solid var(--au-info);outline-offset:2px}
	.au-f__title{font-weight:600;flex:1 1 280px}.au-f__detail{font-weight:400;color:var(--au-muted)}
	.au-f__body{padding:0 14px 12px 14px;border-top:1px solid var(--au-border)}
	.au-p-critical{--au-p:var(--au-bad)}.au-p-high{--au-p:#d9480f}.au-p-medium{--au-p:var(--au-mid)}.au-p-low{--au-p:var(--au-info)}
	.au-badge{background:var(--au-p);color:var(--au-card);border-radius:4px;font-size:11px;font-weight:700;padding:2px 8px;text-transform:uppercase;letter-spacing:.03em;min-width:54px;text-align:center}
	.au-tags{display:flex;gap:4px;flex-wrap:wrap}.au-tag{background:var(--au-soft);color:var(--au-muted);border-radius:4px;padding:1px 8px;font-size:11px}
	.au-items{margin:8px 0;padding:0;list-style:none;display:grid;gap:4px;max-height:260px;overflow:auto}.au-items code{display:block;white-space:pre-wrap;word-break:break-word;font-size:12px;background:var(--au-soft);color:var(--au-text);padding:4px 8px;border-radius:4px}
	.au-fix{margin-top:10px}
	.au-grid2{display:grid;grid-template-columns:repeat(auto-fit,minmax(340px,1fr));gap:16px;align-items:start}
	.au-settings fieldset{margin:14px 0;display:block}.au-settings legend{margin-bottom:6px}.au-settings label{line-height:2}.au-sub{display:block;margin-top:6px}.au-settings .description{display:block;color:var(--au-muted)}
	.au-toc{margin:10px 0 0;padding:0;list-style:none;color:var(--au-muted);columns:2 220px}.au-toc li{margin:0 0 4px}
	.au-empty{color:var(--au-muted)}
	.au-history .au-bar p{margin:0}.au-charts{display:grid;grid-template-columns:repeat(auto-fit,minmax(320px,1fr));gap:16px;margin:0 0 14px}.au-charts>*{min-width:0}
	.au-chart{margin:0;background:var(--au-soft);border-radius:8px;padding:10px 12px}.au-chart figcaption{display:flex;flex-wrap:wrap;gap:8px;align-items:center;justify-content:space-between;font-weight:600;margin-bottom:6px;min-height:32px}.au-chart svg{display:block;width:100%;height:auto}.au-chart svg[hidden]{display:none}
	.au-grid{stroke:var(--au-border);stroke-width:1}.au-axis{fill:var(--au-muted);font-size:11px}.au-seg{display:flex;gap:4px}
	.au-chart polyline{fill:none;stroke-width:2.5;stroke-linejoin:round;stroke-linecap:round}.au-chart g circle{stroke:var(--au-soft);stroke-width:1.5}
	.au-l-performance{--au-l:var(--au-info)}.au-l-accessibility{--au-l:#8e44c9}.au-l-best-practices{--au-l:var(--au-good)}.au-l-seo{--au-l:#d9480f}g[class^=au-l-] polyline{stroke:var(--au-l)}g[class^=au-l-] circle{fill:var(--au-l)}
	.au-legend{display:flex;flex-wrap:wrap;gap:4px 14px;margin:6px 0 0;padding:0;list-style:none;font-size:12px;color:var(--au-muted)}.au-key{display:inline-block;width:10px;height:10px;border-radius:2px;margin-right:5px;vertical-align:-1px;background:var(--au-l,currentColor)}.au-key.au-bar-critical{background:var(--au-bad)}.au-key.au-bar-high{background:#d9480f}.au-key.au-bar-medium{background:var(--au-mid)}.au-key.au-bar-low{background:var(--au-info)}
	.au-bar-critical{fill:var(--au-bad)}.au-bar-high{fill:#d9480f}.au-bar-medium{fill:var(--au-mid)}.au-bar-low{fill:var(--au-info)}
	.au-table-wrap{overflow-x:auto}.au-table{width:100%;border-collapse:collapse;font-size:13px}
	.au-table th,.au-table td{text-align:left;padding:8px 10px;border-top:1px solid var(--au-border);vertical-align:top}.au-table th{color:var(--au-muted);font-weight:600;border-top:0;white-space:nowrap}
	.au-counts{white-space:nowrap}.au-counts .au-badge{display:inline-block;min-width:26px;padding:2px 6px;margin-right:2px}
	.au-srow{display:flex;flex-wrap:wrap;gap:2px 8px;align-items:baseline}.au-srow+.au-srow{margin-top:4px}.au-srow>span{font-weight:700}.au-srow .au-srow__k{font-weight:400;color:var(--au-muted);min-width:64px}.au-srow .au-meta{font-weight:400}
	.au-table summary{cursor:pointer}.au-table summary span{margin-right:8px;font-weight:600}.au-diff{margin:6px 0 0;padding:0;list-style:none;display:grid;gap:2px}
	.au-sched{display:flex;flex-wrap:wrap;gap:8px 14px;margin-bottom:4px}.au-sched label{display:flex;flex-direction:column;line-height:1.6}
	.au-settings[data-freq="daily"] .au-day,.au-settings[data-freq="off"] .au-day{display:none}
	@media (max-width:782px){.au-table thead{display:none}.au-table,.au-table tbody{display:block}.au-table tr{display:grid;grid-template-columns:1fr auto;gap:6px 10px;padding:12px 0;border-top:1px solid var(--au-border)}.au-table td{border:0;padding:0}.au-td-trig{text-align:right;color:var(--au-muted)}.au-counts,.au-scorecell,.au-td-diff{grid-column:1/-1}.au-td-date br{display:none}.au-td-date .au-meta::before{content:' · '}.au-head{grid-template-columns:1fr}.au-run input{min-width:0;width:100%}.au-run .button{flex:1}.flxseo-au .au-card{padding:14px}.au-grid2,.au-scores{grid-template-columns:1fr}.au-bar h2{width:100%}}
	@media (prefers-reduced-motion:no-preference){.au-ring__fg{transition:stroke-dasharray .6s ease}}
	</style>
	<?php
}

function flxseo_audit_js() {
	$data = array(
		'ajax'    => admin_url( 'admin-ajax.php' ),
		'nonce'   => wp_create_nonce( 'flxseo_audit' ),
		'local'   => __( 'Site içi denetim çalışıyor…', 'flx-seo' ),
		'mobile'  => __( 'PageSpeed mobil ölçülüyor (30–40 sn)…', 'flx-seo' ),
		'desktop' => __( 'PageSpeed masaüstü ölçülüyor…', 'flx-seo' ),
		'done'    => __( 'Bitti, sayfa yenileniyor…', 'flx-seo' ),
		'fail'    => __( 'Olmadı:', 'flx-seo' ),
	);
	?>
	<script>
	(function(){
		var D=<?php echo wp_json_encode( $data ); ?>,root=document.getElementById('flxseo-au');if(!root)return;
		var st=document.getElementById('au-status'),url=document.getElementById('au-url'),btns=root.querySelectorAll('[data-run]');
		function call(action,extra){var b=new URLSearchParams(Object.assign({action:action,_wpnonce:D.nonce,url:url.value},extra||{}));return fetch(D.ajax,{method:'POST',credentials:'same-origin',body:b}).then(function(r){return r.json()}).then(function(j){if(!j||!j.success)throw new Error(j&&j.data?j.data:'?');return j.data})}
		function busy(on){btns.forEach(function(b){b.disabled=on})}
		function fail(e){busy(false);st.classList.add('is-err');st.textContent=D.fail+' '+e.message}
		btns.forEach(function(b){b.addEventListener('click',function(){
			st.classList.remove('is-err');busy(true);
			var p=b.dataset.run==='local'?(st.textContent=D.local,call('flxseo_audit_local')):(st.textContent=D.mobile,call('flxseo_audit_psi',{strategy:'mobile'}).then(function(){st.textContent=D.desktop;return call('flxseo_audit_psi',{strategy:'desktop'})}).then(function(){st.textContent=D.local;return call('flxseo_audit_local')}));
			p.then(function(){st.textContent=D.done;location.reload()}).catch(fail);
		})});
		var prio='',cat='',sel=document.getElementById('au-cat');
		function apply(){root.querySelectorAll('.au-f').forEach(function(f){f.hidden=!((!prio||f.dataset.prio===prio)&&(!cat||(' '+f.dataset.cat+' ').indexOf(' '+cat+' ')>-1))})}
		root.querySelectorAll('.au-filters .au-chip').forEach(function(c){c.addEventListener('click',function(){prio=c.dataset.prio;root.querySelectorAll('.au-filters .au-chip').forEach(function(x){x.classList.toggle('is-on',x===c);x.setAttribute('aria-pressed',x===c?'true':'false')});apply()})});
		if(sel)sel.addEventListener('change',function(){cat=sel.value;apply()});
		root.querySelectorAll('.au-seg .au-chip').forEach(function(c){c.addEventListener('click',function(){var f=c.closest('figure');f.querySelectorAll('.au-seg .au-chip').forEach(function(x){x.classList.toggle('is-on',x===c);x.setAttribute('aria-pressed',x===c?'true':'false')});f.querySelectorAll('svg[data-strat]').forEach(function(v){v.hidden=v.dataset.strat!==c.dataset.strat})})});
		var fq=document.getElementById('au-freq');if(fq)fq.addEventListener('change',function(){fq.form.dataset.freq=fq.value});
	})();
	</script>
	<?php
}

/* ---------------------------------------------------------------------------
 * Uçlar
 * ------------------------------------------------------------------------- */

add_action(
	'wp_ajax_flxseo_audit_local',
	function () {
		check_ajax_referer( 'flxseo_audit' );
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( __( 'Yetkiniz yok.', 'flx-seo' ), 403 );
		}
		$r = flxseo_audit_run_local( isset( $_POST['url'] ) ? flxseo_audit_url( wp_unslash( $_POST['url'] ) ) : '' ); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput
		if ( ! is_wp_error( $r ) ) {
			do_action( 'flxseo_audit_done', 'manual' );
		}
		is_wp_error( $r ) ? wp_send_json_error( $r->get_error_message() ) : wp_send_json_success( array( 'count' => count( $r['findings'] ) ) );
	}
);

add_action(
	'wp_ajax_flxseo_audit_psi',
	function () {
		check_ajax_referer( 'flxseo_audit' );
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( __( 'Yetkiniz yok.', 'flx-seo' ), 403 );
		}
		$strategy = isset( $_POST['strategy'] ) && 'desktop' === $_POST['strategy'] ? 'desktop' : 'mobile';
		$r        = flxseo_psi_run( $strategy, isset( $_POST['url'] ) ? flxseo_audit_url( wp_unslash( $_POST['url'] ) ) : '' ); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput
		is_wp_error( $r ) ? wp_send_json_error( $r->get_error_message() ) : wp_send_json_success( array( 'scores' => $r['scores'] ) );
	}
);

/** Yalnız bu sitenin adresleri denetlenir (sunucuyu başka adreslere istek atmak için kullandırmayalım). */
function flxseo_audit_url( $url ) {
	$url  = esc_url_raw( trim( (string) $url ) );
	$host = (string) wp_parse_url( $url, PHP_URL_HOST );
	return '' !== $host && strtolower( $host ) === strtolower( (string) wp_parse_url( home_url(), PHP_URL_HOST ) ) ? $url : home_url( '/' );
}

add_action(
	'admin_post_flxseo_audit_settings',
	function () {
		check_admin_referer( 'flxseo_audit_settings' );
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'Yetkiniz yok.', 'flx-seo' ) );
		}
		$in  = isset( $_POST['o'] ) ? (array) wp_unslash( $_POST['o'] ) : array(); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput
		$o   = (array) get_option( 'flxseo', array() );
		foreach ( array( 'llms', 'webmcp', 'hdr_frame', 'hdr_coop', 'hdr_csp', 'hsts_sub', 'hsts_preload', 'audit_psi', 'audit_mail' ) as $k ) {
			$o[ $k ] = empty( $in[ $k ] ) ? 0 : 1;
		}
		$o['llms_intro']    = sanitize_textarea_field( $in['llms_intro'] ?? '' );
		$o['audit_freq']    = isset( flxseo_audit_freqs()[ $in['audit_freq'] ?? '' ] ) ? $in['audit_freq'] : 'daily';
		$o['audit_day']     = (string) max( 1, min( 7, (int) ( $in['audit_day'] ?? 1 ) ) );
		$o['audit_hour']    = (string) max( 0, min( 23, (int) ( $in['audit_hour'] ?? 3 ) ) );
		$o['audit_mail_to'] = sanitize_email( $in['audit_mail_to'] ?? '' );
		update_option( 'flxseo', $o );
		if ( ! empty( $_POST['psi_key_clear'] ) ) {
			delete_option( 'flxseo_psi_key' );
		} elseif ( ! empty( $_POST['psi_key'] ) ) {
			update_option( 'flxseo_psi_key', sanitize_text_field( wp_unslash( $_POST['psi_key'] ) ), false ); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput
		}
		flxseo_llms_flush();
		set_transient( 'flxseo_audit_msg_' . get_current_user_id(), array( 'success', __( 'Kaydedildi.', 'flx-seo' ) ), 60 );
		wp_safe_redirect( flxseo_admin_url( 'ayarlar' ) );
		exit;
	}
);

add_action(
	'admin_post_flxseo_audit_fix',
	function () {
		check_admin_referer( 'flxseo_audit_fix' );
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'Yetkiniz yok.', 'flx-seo' ) );
		}
		$id = sanitize_key( wp_unslash( $_POST['fix'] ?? '' ) ); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput
		try {
			$keys = flxseo_fix_apply( $id );
			if ( function_exists( 'flxseo_system_fix_log_add' ) ) {
				flxseo_system_fix_log_add(
					array(
						't'   => time(),
						'u'   => get_current_user_id(),
						'fix' => $id,
						'ok'  => true,
						'via' => 'audit',
						'new' => implode( ',', $keys ),
					)
				);
			}
			flxseo_audit_run_local();
			do_action( 'flxseo_audit_done', 'fix' );
			$msg = array( 'success', __( 'Açıldı ve denetim yenilendi. Sayfa önbelleği (LiteSpeed) varsa Purge All yapın.', 'flx-seo' ) );
		} catch ( Exception $e ) {
			$msg = array( 'error', $e->getMessage() );
		}
		set_transient( 'flxseo_audit_msg_' . get_current_user_id(), $msg, 60 );
		wp_safe_redirect( flxseo_admin_url( 'denetim' ) );
		exit;
	}
);

add_action(
	'admin_post_flxseo_guide',
	function () {
		check_admin_referer( 'flxseo_guide' );
		if ( ! current_user_can( 'edit_posts' ) ) {
			wp_die( esc_html__( 'Yetkiniz yok.', 'flx-seo' ) );
		}
		$lang = isset( $_GET['lang'] ) && 'en' === $_GET['lang'] ? 'en' : 'tr';
		nocache_headers();
		header( 'Content-Type: text/markdown; charset=utf-8' );
		header( 'Content-Disposition: attachment; filename="site-build-guide.' . $lang . '.md"' );
		echo flxseo_guide( 'all', $lang ); // phpcs:ignore WordPress.Security.EscapeOutput
		exit;
	}
);
