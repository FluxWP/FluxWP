<?php
/**
 * Yeni Sertifika (liste bazlı): solda şablon + kişi satırları, sağda canlı PDF önizleme.
 * Her satır için ayrı sertifika, link ve PDF üretilir; istenirse hepsi tek PDF olarak indirilir.
 */
defined( 'ABSPATH' ) || exit;

function flxs_page_create() {
	$templates = array_map( 'flxs_template_for_js', flxs_list_templates( true ) );
	$edit      = null;
	$id        = (int) flxs_get_param( 'id' );
	if ( $id ) {
		$cert = flxs_get_certificate( $id );
		if ( ! $cert || ! flxs_user_can_edit_certificate( $cert ) ) {
			wp_die( 'Bu sertifikayı düzenleme yetkiniz yok.' );
		}
		$edit = flxs_certificate_for_js( $cert );
		$t    = flxs_get_template( $cert['template_id'] );
		if ( $t && 'active' !== $t['status'] ) {
			$templates[] = flxs_template_for_js( $t );
		}
	}
	$data = array(
		'templates' => $templates,
		'edit'      => $edit,
		'newUrl'    => admin_url( 'admin.php?page=flxs-yeni' ),
		'today'     => wp_date( 'Y-m-d' ),
		'me'        => get_current_user_id(),
		'defaults'  => array( 'template' => (int) get_user_meta( get_current_user_id(), 'flxs_last_template', true ) ),
	);

	flxs_admin_header(
		$edit ? 'Sertifikayı düzenle' : 'Yeni sertifika',
		$edit ? array( array( 'label' => 'Sayfayı aç', 'url' => $edit['url'] ) ) : array(),
		$edit ? esc_html( $edit['number'] ) : 'Kişileri listeye ekleyin, her biri için ayrı sertifika ve link oluşur. Sağda canlı önizleme.'
	);
	if ( ! $templates ) {
		echo '<div class="notice notice-warning"><p>Aktif şablon yok. ' . ( flxs_can_manage() ? '<a href="' . esc_url( admin_url( 'admin.php?page=flxs-sablonlar&new=1' ) ) . '">Şablon ekleyin</a>.' : 'Yöneticiye haber verin.' ) . '</p></div>';
		flxs_admin_footer();
		return;
	}
	?>
	<script type="application/json" id="flxs-create-data"><?php echo wp_json_encode( $data, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP ); ?></script>
	<div class="flxs-create" id="flxs-create">
		<div class="flxs-create__form">

			<section class="flxs-card">
				<h2 class="flxs-card__title"><span class="flxs-step">1</span> Şablon</h2>
				<div class="flxs-tpls" id="flxs-tpls"></div>
			</section>

			<section class="flxs-card">
				<div class="flxs-card__head">
					<h2 class="flxs-card__title"><span class="flxs-step">2</span> Kişiler <span class="flxs-count" id="flxs-row-count"></span></h2>
					<?php if ( ! $edit ) : ?>
						<span class="flxs-card__acts">
							<button type="button" class="button button-small" data-flxs-reset title="Formu temizle">Temizle</button>
							<button type="button" class="button button-small" id="flxs-paste-toggle">Listeden yapıştır</button>
						</span>
					<?php endif; ?>
				</div>
				<div class="flxs-paste" id="flxs-paste" hidden>
					<p class="description">Her satıra bir kişi: <code>Ad Soyad; telefon; e-posta</code> (Excel'den kopyalanan sekmeli satırlar da olur). Şablonda ek alan varsa sırayla devam eder, ör. fidan sayısı.</p>
					<textarea rows="5" id="flxs-paste-text" placeholder="Seyfullah Doğan; 0532 111 22 33; seyfullah@ornek.com; 1&#10;Refika Doğan; 0533 444 55 66"></textarea>
					<p><button type="button" class="button" id="flxs-paste-apply">Listeye ekle</button></p>
				</div>
				<?php
				$flxs_events = flxs_get_events( 'open' );
				$flxs_cur_ev = $edit ? (int) $edit['event_id'] : 0;
				if ( $flxs_cur_ev && ! wp_list_filter( $flxs_events, array( 'id' => (string) $flxs_cur_ev ) ) && ( $flxs_e = flxs_get_event( $flxs_cur_ev ) ) ) {
					$flxs_events[] = $flxs_e; // düzenlenen sertifikanın kapalı etkinliği
				}
				?>
				<label class="flxs-ref flxs-ref--event">
					<span>Etkinlik</span>
					<select id="flxs-event">
						<option value="0">— Etkinliksiz —</option>
						<?php foreach ( $flxs_events as $flxs_e ) : ?>
							<option value="<?php echo (int) $flxs_e['id']; ?>"<?php disabled( 'closed' === $flxs_e['status'] && (int) $flxs_e['id'] !== $flxs_cur_ev ); ?>><?php echo esc_html( $flxs_e['name'] . ( 'closed' === $flxs_e['status'] ? ' (arşiv)' : '' ) ); ?></option>
						<?php endforeach; ?>
					</select>
					<small><?php echo $flxs_events ? 'Fidanlar bu etkinlik için toplanır; Dashboard ve listede etkinliğe göre görülür.' : 'Açık etkinlik yok.' . ( flxs_can_manage() ? ' <a href="' . esc_url( flxs_events_screen_url( array( 'add' => 1 ) ) ) . '">Etkinlik oluştur</a>' : '' ); ?></small>
				</label>
				<label class="flxs-ref">
					<span>Referans personel</span>
					<select id="flxs-ref">
						<option value="0">— Referans yok —</option>
						<?php foreach ( flxs_users_with_cap() as $u ) : ?>
							<option value="<?php echo (int) $u->ID; ?>"><?php echo esc_html( $u->display_name ); ?></option>
						<?php endforeach; ?>
					</select>
					<small>Bu listedeki sertifikalar kimin adına/aracılığıyla; raporlarda kullanılır.</small>
				</label>
				<?php if ( ! $edit && flxs_can_manage() ) : ?>
					<details class="flxs-backdate" id="flxs-backdate-box">
						<summary>Geçmiş tarihli oluştur <small>(mutabakat için, nadiren)</small></summary>
						<label class="flxs-check"><input type="checkbox" id="flxs-backdate"> Bu listeyi geçmiş tarihli oluştur</label>
						<input type="date" id="flxs-issued" value="<?php echo esc_attr( wp_date( 'Y-m-d' ) ); ?>" max="<?php echo esc_attr( wp_date( 'Y-m-d' ) ); ?>" min="2015-01-01" disabled>
						<small>Sertifikadaki tarih, numaradaki yıl, liste ve Dashboard raporları bu tarihi kullanır. Gönderim her zamanki gibi yapılır. Temizle'ye basınca kapanır.</small>
					</details>
				<?php endif; ?>
				<div class="flxs-common" id="flxs-common"></div>
				<div class="flxs-rows" id="flxs-rows"></div>
				<?php if ( ! $edit ) : ?>
					<button type="button" class="flxs-add-row" id="flxs-add-row"><span class="dashicons dashicons-plus-alt2"></span> Kişi ekle</button>
				<?php endif; ?>
			</section>

			<section class="flxs-card">
				<h2 class="flxs-card__title"><span class="flxs-step">3</span> Gönderim <small>isteğe bağlı</small></h2>
				<div class="flxs-send" id="flxs-send"></div>
				<p class="description">Mesajlar kuyruğa alınır ve arka planda gönderilir. Durumu <a href="<?php echo esc_url( admin_url( 'admin.php?page=flxs-kuyruk' ) ); ?>">Gönderim Kuyruğu</a>'ndan izleyebilirsiniz.</p>
			</section>

			<div class="flxs-submit">
				<button type="button" class="button button-primary button-hero" id="flxs-submit"><?php echo $edit ? 'Kaydet ve PDF\'i yenile' : 'Sertifikaları oluştur'; ?></button>
				<button type="button" class="button flxs-reset" id="flxs-reset" data-flxs-reset><span class="dashicons dashicons-update"></span> <?php echo $edit ? 'Yeni sertifikaya geç' : 'Temizle'; ?></button>
				<span class="flxs-progress" id="flxs-progress" aria-live="polite"></span>
			</div>

			<section class="flxs-card flxs-results" id="flxs-results" hidden>
				<div class="flxs-card__head">
					<h2 class="flxs-card__title">Oluşturulanlar</h2>
					<div class="flxs-results__actions">
						<button type="button" class="button" id="flxs-copy-all">Linkleri kopyala</button>
						<button type="button" class="button button-primary" id="flxs-merge">Tek PDF indir</button>
						<button type="button" class="button" id="flxs-new">Yeni liste</button>
					</div>
				</div>
				<div id="flxs-result-list"></div>
			</section>
		</div>

		<div class="flxs-create__preview">
			<div class="flxs-preview" id="flxs-preview">
				<div class="flxs-preview__bar">
					<strong>Canlı önizleme</strong>
					<span class="flxs-preview__nav">
						<button type="button" class="button button-small" data-nav="-1" aria-label="Önceki">‹</button>
						<span id="flxs-preview-pos">1 / 1</span>
						<button type="button" class="button button-small" data-nav="1" aria-label="Sonraki">›</button>
					</span>
					<button type="button" class="button button-small" id="flxs-preview-pdf">PDF</button>
				</div>
				<div class="flxs-preview__stage">
					<canvas id="flxs-canvas"></canvas>
					<div class="flxs-preview__spin" id="flxs-spin"></div>
				</div>
				<p class="flxs-preview__err" id="flxs-preview-err" hidden></p>
			</div>
		</div>
	</div>
	<?php
	flxs_admin_footer();
}
