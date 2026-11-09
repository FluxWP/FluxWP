<?php
/* Etkinlikler: fidanlar etkinlik bazlı toplanır; kapatılan etkinlik arşive düşer. */
defined( 'ABSPATH' ) || exit;

add_action( 'admin_post_flxs_event_save', 'flxs_handle_event_save' );
add_action( 'admin_post_flxs_event_status', 'flxs_handle_event_status' );
add_action( 'admin_post_flxs_event_delete', 'flxs_handle_event_delete' );
add_action( 'admin_post_flxs_event_page', 'flxs_handle_event_page' );
add_action( 'admin_post_flxs_site_save', 'flxs_handle_site_save' );

function flxs_handle_site_save() {
	check_admin_referer( 'flxs_site_save' );
	if ( ! flxs_can_manage() ) {
		wp_die( 'Yetki yok' );
	}
	flxs_site_save( wp_unslash( $_POST ) ); // phpcs:ignore
	flxs_redirect( 'flxs-etkinlikler', array( 'flxs_msg' => 'Site ayarları kaydedildi.' ) );
}

function flxs_handle_event_save() {
	check_admin_referer( 'flxs_event_save' );
	if ( ! flxs_can_manage() ) {
		wp_die( 'Yetki yok' );
	}
	global $wpdb;
	$id  = (int) ( $_POST['id'] ?? 0 );
	$new = ! $id;
	$r   = flxs_save_event( wp_unslash( $_POST ), $id ); // phpcs:ignore
	if ( is_wp_error( $r ) ) {
		flxs_redirect( 'flxs-etkinlikler', array( $id ? 'edit' : 'add' => $id ? $id : 1, 'flxs_err' => $r->get_error_message() ) );
	}
	$msg = $new ? 'Etkinlik oluşturuldu.' : 'Etkinlik kaydedildi.';
	if ( $new && ! empty( $_POST['adopt'] ) ) {
		$n = $wpdb->query( $wpdb->prepare( 'UPDATE ' . flxs_table( 'certificates' ) . ' SET event_id = %d WHERE event_id = 0', $r ) ); // phpcs:ignore
		if ( $n ) {
			flxs_log( 'event_saved', 'event', $r, sprintf( '%d etkinliksiz sertifika etkinliğe eklendi', $n ) );
			$msg .= sprintf( ' Etkinliksiz %d sertifika bu etkinliğe eklendi.', $n );
		}
	}
	flxs_redirect( 'flxs-etkinlikler', array( 'flxs_msg' => $msg ) );
}

function flxs_handle_event_status() {
	check_admin_referer( 'flxs_event_status' );
	if ( ! flxs_can_manage() ) {
		wp_die( 'Yetki yok' );
	}
	$status = 'closed' === ( $_POST['status'] ?? '' ) ? 'closed' : 'open';
	flxs_set_event_status( (int) ( $_POST['id'] ?? 0 ), $status );
	flxs_redirect( 'flxs-etkinlikler', array( 'tab' => 'closed' === $status ? 'arsiv' : '', 'flxs_msg' => 'closed' === $status ? 'Etkinlik kapatıldı ve arşive taşındı.' : 'Etkinlik yeniden açıldı.' ) );
}

/** Etkinliğin sitedeki sayfasını oluştur / bağla / yayınla */
function flxs_handle_event_page() {
	check_admin_referer( 'flxs_event_page' );
	if ( ! flxs_can_manage() ) {
		wp_die( 'Yetki yok' );
	}
	$pid = flxs_event_ensure_post( (int) ( $_POST['id'] ?? 0 ), true );
	flxs_redirect( 'flxs-etkinlikler', array( 'flxs_msg' => $pid ? 'Etkinlik sayfası sitede yayında.' : 'Sayfa oluşturulamadı: temada "etkinlik" yazı türü yok.' ) );
}

function flxs_handle_event_delete() {
	check_admin_referer( 'flxs_event_delete' );
	if ( ! flxs_can_manage() ) {
		wp_die( 'Yetki yok' );
	}
	flxs_delete_event( (int) ( $_POST['id'] ?? 0 ) );
	flxs_redirect( 'flxs-etkinlikler', array( 'flxs_msg' => 'Etkinlik silindi; sertifikaları etkinliksiz kaldı.' ) );
}

function flxs_page_events() {
	$edit = (int) flxs_get_param( 'edit' );
	if ( $edit || flxs_get_param( 'add' ) ) {
		$e = $edit ? flxs_get_event( $edit ) : array( 'id' => 0 );
		if ( ! $e ) {
			wp_die( 'Etkinlik bulunamadı.' );
		}
		flxs_admin_header( $edit ? 'Etkinliği düzenle' : 'Yeni etkinlik', array( array( 'label' => '← Etkinlikler', 'url' => admin_url( 'admin.php?page=flxs-etkinlikler' ) ) ) );
		flxs_event_form( $e );
		flxs_admin_footer();
		return;
	}
	$tab    = 'arsiv' === flxs_get_param( 'tab' ) ? 'closed' : 'open';
	$open   = flxs_get_events( 'open' );
	$closed = flxs_get_events( 'closed' );
	$tot    = flxs_event_totals();
	$none   = $tot[0] ?? null;
	flxs_admin_header(
		'Etkinlikler',
		array( array( 'label' => '+ Yeni etkinlik', 'url' => admin_url( 'admin.php?page=flxs-etkinlikler&add=1' ), 'class' => 'button-primary' ) ),
		'Sertifikalar bir etkinlik altında toplanır. Etkinlik bitince kapatın: arşive düşer, sertifikaları geçerli kalır, yeni sertifika eklenemez.'
	);
	$base = admin_url( 'admin.php?page=flxs-etkinlikler' );
	echo '<nav class="flxs-tabs"><a href="' . esc_url( $base ) . '" class="' . ( 'open' === $tab ? 'is-on' : '' ) . '">Açık <b>' . count( $open ) . '</b></a><a href="' . esc_url( $base . '&tab=arsiv' ) . '" class="' . ( 'closed' === $tab ? 'is-on' : '' ) . '">Arşiv <b>' . count( $closed ) . '</b></a></nav>';
	$list = 'open' === $tab ? $open : $closed;
	if ( ! $list ) {
		echo '<div class="flxs-card flxs-empty">' . ( 'open' === $tab ? 'Açık etkinlik yok. <a href="' . esc_url( $base . '&add=1' ) . '">Yeni etkinlik oluşturun</a>.' : 'Arşivde etkinlik yok.' ) . '</div>';
	}
	echo '<div class="flxs-events">';
	foreach ( $list as $e ) {
		$t   = $tot[ (int) $e['id'] ] ?? array( 'fidan' => 0, 'certs' => 0, 'people' => 0, 'first' => '', 'last' => '' );
		$pct = $e['target'] ? min( 100, round( $t['fidan'] / $e['target'] * 100 ) ) : 0;
		echo '<div class="flxs-event' . ( 'closed' === $e['status'] ? ' is-closed' : '' ) . '">';
		echo '<div class="flxs-event__head"><div><h3>' . esc_html( $e['name'] ) . '</h3><div class="flxs-muted">' . ( $e['event_date'] ? esc_html( flxs_tr_date( $e['event_date'] ) ) : 'Tarih yok' ) . '</div></div>';
		echo '<span class="flxs-badge flxs-badge--' . ( 'closed' === $e['status'] ? 'off' : 'on' ) . '">' . ( 'closed' === $e['status'] ? 'Arşiv' : 'Açık' ) . '</span></div>';
		$t['fidan'] += (int) $e['base_count'];
		echo '<div class="flxs-event__nums"><div><b>' . esc_html( flxs_n( $t['fidan'] ) ) . '</b><span>fidan' . ( $e['base_count'] ? ' (+' . (int) $e['base_count'] . ' ek)' : '' ) . '</span></div><div><b>' . esc_html( flxs_n( $t['certs'] ) ) . '</b><span>sertifika</span></div><div><b>' . esc_html( flxs_n( $t['people'] ) ) . '</b><span>kişi</span></div></div>';
		if ( $e['target'] ) {
			echo '<div class="flxs-event__goal"><div class="flxs-event__bar"><span style="width:' . (float) $pct . '%"></span></div><small>Hedef ' . esc_html( flxs_n( $e['target'] ) ) . ' fidan · %' . (int) $pct . '</small></div>';
		}
		echo '<div class="flxs-event__loc">' . ( trim( $e['loc_name'] . $e['loc_address'] . $e['loc_map'] ) ? '📍 ' . esc_html( $e['loc_name'] ? $e['loc_name'] : wp_trim_words( $e['loc_address'], 8 ) ) : '<span class="flxs-muted">📍 Konum henüz girilmedi</span>' ) . flxs_event_location_hits_html( $e ) . '</div>';
		if ( $e['note'] ) {
			echo '<p class="flxs-event__note">' . esc_html( $e['note'] ) . '</p>';
		}
		if ( $t['first'] ) {
			echo '<div class="flxs-muted flxs-event__span">' . esc_html( flxs_date( $t['first'] ) . ' – ' . flxs_date( $t['last'] ) ) . ( 'closed' === $e['status'] && $e['closed_at'] ? ' · kapatıldı ' . esc_html( flxs_date( $e['closed_at'] ) ) : '' ) . '</div>';
		}
		echo '<div class="flxs-event__acts">';
		echo '<a class="button button-small" href="' . esc_url( admin_url( 'admin.php?page=flxs-dashboard&event=' . (int) $e['id'] ) ) . '">Dashboard</a> ';
		echo '<a class="button button-small" href="' . esc_url( admin_url( 'admin.php?page=flxs&event=' . (int) $e['id'] ) ) . '">Sertifikalar</a> ';
		echo '<a class="button button-small" href="' . esc_url( $base . '&edit=' . (int) $e['id'] ) . '">Düzenle</a> ';
		if ( (int) $e['post_id'] && 'publish' === get_post_status( (int) $e['post_id'] ) ) {
			echo '<a class="button button-small" href="' . esc_url( get_permalink( (int) $e['post_id'] ) ) . '" target="_blank">Sitede gör</a> ';
		} elseif ( post_type_exists( 'etkinlik' ) ) {
			flxs_event_post_button( 'flxs_event_page', $e['id'], (int) $e['post_id'] && get_post( (int) $e['post_id'] ) ? 'Sitede yayınla' : 'Sitede sayfa oluştur', array(), '', 'button-primary' );
		}
		flxs_event_post_button( 'flxs_event_status', $e['id'], 'closed' === $e['status'] ? 'Yeniden aç' : 'Kapat ve arşivle', array( 'status' => 'closed' === $e['status'] ? 'open' : 'closed' ), 'closed' === $e['status'] ? '' : 'Etkinlik kapatılsın mı? Arşive taşınır; sertifikaları geçerli kalır ama bu etkinliğe yeni sertifika eklenemez. İstediğinizde yeniden açabilirsiniz.' );
		if ( 'closed' === $e['status'] ) {
			flxs_event_post_button( 'flxs_event_delete', $e['id'], 'Sil', array(), 'Etkinlik silinsin mi? Sertifikalar silinmez, etkinliksiz kalır.', 'flxs-danger' );
		}
		echo '</div></div>';
	}
	echo '</div>';
	if ( $none && $none['certs'] && 'open' === $tab ) {
		$none_msg = true;
	}
	if ( ! empty( $none_msg ) ) {
		echo '<p class="flxs-muted">Etkinliksiz ' . (int) $none['certs'] . ' sertifika (' . esc_html( flxs_n( $none['fidan'] ) ) . ' fidan) var. <a href="' . esc_url( admin_url( 'admin.php?page=flxs&event=0' ) ) . '">Listede görün</a>, seçip <em>Seçilenlere › Etkinliğe ata</em> ile bir etkinliğe ekleyin.</p>';
	}
	flxs_site_settings_form();
	flxs_admin_footer();
}

function flxs_event_post_button( $action, $id, $label, $fields = array(), $confirm = '', $class = '' ) {
	echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '" class="flxs-inline-form">';
	wp_nonce_field( $action );
	echo '<input type="hidden" name="action" value="' . esc_attr( $action ) . '"><input type="hidden" name="id" value="' . (int) $id . '">';
	foreach ( $fields as $k => $v ) {
		echo '<input type="hidden" name="' . esc_attr( $k ) . '" value="' . esc_attr( $v ) . '">';
	}
	echo '<button class="button button-small ' . esc_attr( $class ) . '"' . ( $confirm ? ' data-confirm="' . esc_attr( $confirm ) . '"' : '' ) . '>' . esc_html( $label ) . '</button></form> ';
}

function flxs_event_form( $e ) {
	global $wpdb;
	$e    = wp_parse_args( $e, array( 'id' => 0, 'name' => '', 'event_date' => '', 'event_time' => '', 'target' => 0, 'base_count' => 0, 'summary' => '', 'image_id' => 0, 'post_id' => 0, 'loc_name' => '', 'loc_address' => '', 'loc_map' => '', 'loc_announce' => '', 'note' => '' ) );
	$none = $e['id'] ? 0 : (int) $wpdb->get_var( 'SELECT COUNT(*) FROM ' . flxs_table( 'certificates' ) . ' WHERE event_id = 0' ); // phpcs:ignore
	$img  = (int) $e['image_id'] ? wp_get_attachment_image_url( (int) $e['image_id'], 'medium' ) : '';
	$has_cpt = post_type_exists( 'etkinlik' );
	$posts   = $has_cpt ? get_posts( array( 'post_type' => 'etkinlik', 'post_status' => array( 'publish', 'draft', 'future', 'private' ), 'numberposts' => 200, 'orderby' => 'date', 'order' => 'DESC' ) ) : array();
	$cnt     = $e['id'] ? flxs_event_fidan( $e ) : array( 'cert_fidan' => 0 );
	?>
	<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="flxs-form flxs-evform">
		<?php wp_nonce_field( 'flxs_event_save' ); ?>
		<input type="hidden" name="action" value="flxs_event_save">
		<input type="hidden" name="id" value="<?php echo (int) $e['id']; ?>">
		<div class="flxs-evform__grid">
		<div>
		<section class="flxs-card">
			<h2 class="flxs-card__title">Etkinlik</h2>
			<label class="flxs-field"><span>Etkinlik adı *</span><input type="text" name="name" required value="<?php echo esc_attr( $e['name'] ); ?>" placeholder="ör. 11 Kasım Milli Ağaçlandırma Günü"></label>
			<div class="flxs-grid">
				<label class="flxs-field"><span>Tarih</span><input type="date" name="event_date" value="<?php echo esc_attr( (string) $e['event_date'] ); ?>"></label>
				<label class="flxs-field"><span>Saat</span><input type="text" name="event_time" value="<?php echo esc_attr( (string) $e['event_time'] ); ?>" placeholder="ör. 10:00"></label>
			</div>
			<label class="flxs-field"><span>Kısa açıklama <small>(sitede kartta ve sayfa başında görünür)</small></span><textarea name="summary" rows="3" placeholder="ör. Milli Ağaçlandırma Günü'nde bağışlanan fidanları gönüllülerimizle birlikte toprakla buluşturuyoruz."><?php echo esc_textarea( (string) $e['summary'] ); ?></textarea></label>
			<div class="flxs-field"><span>Görsel</span>
				<div class="flxs-evimg">
					<input type="hidden" name="image_id" value="<?php echo (int) $e['image_id']; ?>">
					<div class="flxs-evimg__prev"><?php echo $img ? '<img src="' . esc_url( $img ) . '" alt="">' : '<span>Görsel yok</span>'; ?><i class="flxs-evimg__crop flxs-evimg__crop--t"></i><i class="flxs-evimg__crop flxs-evimg__crop--b"></i><i class="flxs-evimg__safe"></i><i class="flxs-evimg__badge">Tarih</i></div>
					<div><button type="button" class="button" data-evimg-pick>Görsel seç</button> <button type="button" class="button-link flxs-danger" data-evimg-clear<?php echo $img ? '' : ' hidden'; ?>>Kaldır</button><p class="description">Sitedeki etkinlik sayfasının öne çıkan görseli olur. Önerilen ölçü 1600 × 1000 px (16:10).</p><p class="description">Yazı ve logoyu kesikli çerçevenin (güvenli alan) içinde tutun: soldan ve sağdan %12,5, üstten ve alttan %12 boşluk (1600 × 1000'de x 200–1400, y 120–880). Etkinlik sayfası görseli 2:1 gösterir, gölgeli üst ve alt şeritler orada kesilir. Kartlarda sağ üstte tarih rozeti durur.</p></div>
				</div>
			</div>
		</section>
		<section class="flxs-card">
			<h2 class="flxs-card__title">Konum</h2>
			<p class="description" style="margin-top:-6px">Konum henüz belli değilse boş bırakın. Sitede "Konum yakında" yazar ve açıklanacağı tarih gösterilir.</p>
			<div class="flxs-grid">
				<label class="flxs-field"><span>Yer adı</span><input type="text" name="loc_name" value="<?php echo esc_attr( (string) $e['loc_name'] ); ?>" placeholder="ör. Kemerburgaz Kent Ormanı"></label>
				<label class="flxs-field"><span>Konum açıklanma tarihi</span><input type="date" name="loc_announce" value="<?php echo esc_attr( (string) $e['loc_announce'] ); ?>"><small class="flxs-muted">Boşsa etkinlikten 10 gün önce.</small></label>
			</div>
			<label class="flxs-field"><span>Adres / tarif</span><textarea name="loc_address" rows="2" placeholder="Mahalle, ilçe; buluşma noktası, otopark vb."><?php echo esc_textarea( (string) $e['loc_address'] ); ?></textarea></label>
			<label class="flxs-field"><span>Harita linki ya da koordinat</span><input type="text" name="loc_map" value="<?php echo esc_attr( (string) $e['loc_map'] ); ?>" placeholder="Google Maps linki ya da 41.1612, 28.9271"></label>
			<?php flxs_event_location_qr_box( $e ); ?>
		</section>
		</div>
		<div>
		<section class="flxs-card">
			<h2 class="flxs-card__title">Fidan</h2>
			<label class="flxs-field"><span>Hedef <small>(isteğe bağlı)</small></span><input type="number" name="target" min="0" value="<?php echo $e['target'] ? (int) $e['target'] : ''; ?>" placeholder="ör. 2000"></label>
			<label class="flxs-field"><span>Ek fidan <small>(sertifikasız bağışlar)</small></span><input type="number" name="base_count" min="0" value="<?php echo $e['base_count'] ? (int) $e['base_count'] : ''; ?>" placeholder="0"><small class="flxs-muted">Sitede görünen adet = sertifikalardaki fidan<?php echo $e['id'] ? ' (' . esc_html( flxs_n( $cnt['cert_fidan'] ) ) . ')' : ''; ?> + bu sayı.</small></label>
		</section>
		<section class="flxs-card">
			<h2 class="flxs-card__title">Sitedeki sayfa</h2>
			<?php if ( $has_cpt ) : ?>
				<label class="flxs-field"><span>Etkinlik sayfası</span>
					<select name="post_id">
						<option value="0">— Bağlı değil —</option>
						<?php foreach ( $posts as $p ) : ?>
							<option value="<?php echo (int) $p->ID; ?>" <?php selected( (int) $e['post_id'], $p->ID ); ?>><?php echo esc_html( $p->post_title . ( 'publish' !== $p->post_status ? ' (' . $p->post_status . ')' : '' ) ); ?></option>
						<?php endforeach; ?>
					</select>
				</label>
				<?php if ( ! (int) $e['post_id'] ) : ?>
					<label class="flxs-check"><input type="checkbox" name="create_post" value="1" checked> Bağlı değilse sitede etkinlik sayfası oluştur (aynı adlı sayfa varsa ona bağlanır)</label>
				<?php else : ?>
					<p><a class="button" href="<?php echo esc_url( get_edit_post_link( (int) $e['post_id'] ) ); ?>">Sayfa içeriğini düzenle</a> <a class="button" href="<?php echo esc_url( get_permalink( (int) $e['post_id'] ) ); ?>" target="_blank">Sayfayı aç</a></p>
				<?php endif; ?>
				<p class="description">Sayfanın başına fidan sayacı, hedef, tarih ve konum otomatik eklenir. Uzun açıklama ve fotoğraflar sayfa editöründen eklenir.</p>
			<?php else : ?>
				<p class="description">Temada "Etkinlikler" içerik tipi yok. Etkinlik kartı için <code>[flxs_etkinlikler]</code>, panel için <code>[flxs_etkinlik id="<?php echo (int) $e['id']; ?>"]</code> kısa kodunu kullanın.</p>
			<?php endif; ?>
		</section>
		<section class="flxs-card">
			<h2 class="flxs-card__title">İç not</h2>
			<label class="flxs-field"><span>Not <small>(sitede görünmez)</small></span><textarea name="note" rows="2" placeholder="İş birliği, sorumlu kişi vb."><?php echo esc_textarea( (string) $e['note'] ); ?></textarea></label>
			<?php if ( $none ) : ?>
				<label class="flxs-check"><input type="checkbox" name="adopt" value="1" checked> Şu an etkinliksiz olan <strong><?php echo (int) $none; ?></strong> sertifikayı bu etkinliğe ekle</label>
			<?php endif; ?>
			<p><button class="button button-primary button-large"><?php echo $e['id'] ? 'Kaydet' : 'Etkinliği oluştur'; ?></button></p>
		</section>
		</div>
		</div>
	</form>
	<script>
	// Medya kütüphanesi scriptleri sayfa sonunda yüklenir; wp.media'yı tıklama anında kullan
	(function () {
		var box = document.querySelector('.flxs-evimg'); if (!box) return;
		var inp = box.querySelector('[name=image_id]'), prev = box.querySelector('.flxs-evimg__prev'), noimg = 'Görsel yok', clr = box.querySelector('[data-evimg-clear]'), frame;
		box.querySelector('[data-evimg-pick]').addEventListener('click', function (ev) {
			ev.preventDefault();
			if (!window.wp || !wp.media) { alert('Medya kütüphanesi yüklenemedi. Sayfayı yenileyip tekrar deneyin.'); return; }
			frame = frame || wp.media({ title: 'Etkinlik görseli', library: { type: 'image' }, multiple: false, button: { text: 'Bu görseli kullan' } });
			frame.off('select').on('select', function () {
				var a = frame.state().get('selection').first().toJSON();
				inp.value = a.id; prev.firstElementChild.outerHTML = '<img src="' + ((a.sizes && (a.sizes.medium_large || a.sizes.large)) || a).url + '" alt="">'; clr.hidden = false;
			});
			frame.open();
		});
		clr.addEventListener('click', function () { inp.value = 0; prev.firstElementChild.outerHTML = '<span>' + noimg + '</span>'; clr.hidden = true; });
	})();
	</script>
	<?php
}

/** Sitede etkinlik bölümü ayarları (Etkinlikler sayfasının altında) */
function flxs_site_settings_form() {
	?>
	<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="flxs-card flxs-form" style="max-width:860px">
		<?php wp_nonce_field( 'flxs_site_save' ); ?>
		<input type="hidden" name="action" value="flxs_site_save">
		<h2 class="flxs-card__title">Sitede görünüm</h2>
		<label class="flxs-check"><input type="checkbox" name="home_section" value="1" <?php checked( (int) flxs_site_opt( 'home_section' ) ); ?>> Ana sayfada açık etkinlikler bölümünü göster <small class="flxs-muted">(istatistik bandının hemen altında)</small></label>
		<div class="flxs-grid">
			<label class="flxs-field"><span>Bölüm başlığı</span><input type="text" name="title" value="<?php echo esc_attr( flxs_site_opt( 'title' ) ); ?>"></label>
			<label class="flxs-field"><span>Bağış butonu adresi</span><input type="text" name="donate_url" value="<?php echo esc_attr( flxs_site_opt( 'donate_url' ) ); ?>" placeholder="/bagis/"></label>
		</div>
		<label class="flxs-field"><span>Bölüm açıklaması</span><input type="text" name="subtitle" value="<?php echo esc_attr( flxs_site_opt( 'subtitle' ) ); ?>"></label>
		<label class="flxs-field" style="max-width:320px"><span>Toplam fidan sayacına eklenecek sayı</span><input type="number" min="0" name="site_base" value="<?php echo (int) flxs_site_opt( 'site_base' ) ? (int) flxs_site_opt( 'site_base' ) : ''; ?>" placeholder="ör. 125000"><small class="flxs-muted"><code>[flxs_fidan_sayaci]</code> = bu sayı + tüm etkinliklerdeki fidan.</small></label>
		<p class="description">Kısa kodlar: <code>[flxs_etkinlikler]</code> bölüm, <code>[flxs_etkinlikler arsiv="1"]</code> arşiv dahil, <code>[flxs_etkinlik id="3"]</code> tek etkinlik paneli, <code>[flxs_fidan_sayaci etkinlik="3"]</code> sayı.</p>
		<p><button class="button button-primary">Kaydet</button></p>
	</form>
	<?php
}

/** Liste satırı: konum linki tıklamaları (QR / site) */
function flxs_event_location_hits_html( $e ) {
	$qr  = (int) ( $e['loc_hits_qr'] ?? 0 );
	$web = (int) ( $e['loc_hits_web'] ?? 0 );
	if ( ! $qr && ! $web ) {
		return '';
	}
	/* translators: 1: QR ile açılma sayısı, 2: siteden tıklama sayısı */
	return ' <span class="flxs-muted">· ' . esc_html( sprintf( __( 'Location opened: %1$s via QR, %2$s from the site', 'flx-certificates' ), flxs_n( $qr ), flxs_n( $web ) ) ) . '</span>';
}

/** Düzenleme formu: takipli konum linki, QR ve sayılar */
function flxs_event_location_qr_box( $e ) {
	if ( ! (int) $e['id'] ) {
		return;
	}
	$e += array( 'loc_hits_qr' => 0, 'loc_hits_web' => 0 );
	$link = flxs_event_location_link( $e, 'qr' );
	$svg  = '';
	if ( ! class_exists( 'TCPDF2DBarcode' ) && function_exists( 'flxs_load_tcpdf' ) ) {
		flxs_load_tcpdf();
	}
	if ( class_exists( 'TCPDF2DBarcode' ) ) {
		$bc  = new TCPDF2DBarcode( $link, 'QRCODE,M' );
		$svg = $bc->getBarcodeSVGcode( 6, 6, 'black' );
		$svg = preg_replace( '/^<\?xml[^>]*>\s*(<!DOCTYPE[^>]*>\s*)?/i', '', (string) $svg );
	}
	$has = '' !== flxs_event_map_target( $e );
	?>
	<div class="flxs-locqr">
		<?php if ( $svg ) : ?>
			<a class="flxs-locqr__img" href="<?php echo esc_attr( 'data:image/svg+xml;base64,' . base64_encode( $svg ) ); ?>" download="<?php echo esc_attr( 'konum-qr-' . (int) $e['id'] . '.svg' ); ?>" title="<?php esc_attr_e( 'Download QR (SVG)', 'flx-certificates' ); ?>"><?php echo $svg; // phpcs:ignore WordPress.Security.EscapeOutput -- TCPDF çıktısı ?></a>
		<?php endif; ?>
		<div>
			<strong><?php esc_html_e( 'Location link for QR codes', 'flx-certificates' ); ?></strong>
			<p class="description"><?php esc_html_e( 'Put this link (or the QR) on posters and covers instead of the map link. Visitors land on your site first, the visit is counted, then they are sent to the map. You can change the map later without reprinting.', 'flx-certificates' ); ?></p>
			<p><code><?php echo esc_html( $link ); ?></code></p>
			<p class="flxs-muted">
				<?php
				/* translators: 1: QR ile açılma sayısı, 2: siteden tıklama sayısı */
				echo esc_html( sprintf( __( 'Opened %1$s times via QR, %2$s times from the site.', 'flx-certificates' ), flxs_n( (int) $e['loc_hits_qr'] ), flxs_n( (int) $e['loc_hits_web'] ) ) );
				if ( ! $has ) {
					echo ' ' . esc_html__( 'No map yet: the link opens the event page until a location is entered.', 'flx-certificates' );
				}
				?>
			</p>
		</div>
	</div>
	<?php
}
