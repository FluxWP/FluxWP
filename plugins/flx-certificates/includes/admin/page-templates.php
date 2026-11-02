<?php
defined( 'ABSPATH' ) || exit;

add_action( 'admin_post_flxs_tpl_action', 'flxs_handle_tpl_action' );
add_action( 'admin_post_flxs_tpl_import', 'flxs_handle_tpl_import' );

function flxs_handle_tpl_action() {
	$id = (int) ( $_GET['id'] ?? 0 );
	$op = sanitize_key( $_GET['op'] ?? '' );
	check_admin_referer( 'flxs_tpl_' . $op . '_' . $id );
	if ( ! flxs_can_manage() ) {
		wp_die( 'Yetki yok' );
	}
	$t = flxs_get_template( $id );
	if ( ! $t ) {
		flxs_redirect( 'flxs-sablonlar', array( 'flxs_err' => 'Şablon yok.' ) );
	}
	switch ( $op ) {
		case 'duplicate':
			$new = flxs_save_template( 0, $t['name'] . ' (kopya)', $t['config'], 'inactive' );
			flxs_redirect( 'flxs-sablonlar', array( 'edit' => $new, 'flxs_msg' => 'Kopyalandı (pasif). Düzenleyip aktif yapabilirsiniz.' ) );
			break;
		case 'toggle':
			flxs_save_template( $id, $t['name'], $t['config'], 'active' === $t['status'] ? 'inactive' : 'active' );
			flxs_redirect( 'flxs-sablonlar', array( 'flxs_msg' => 'Durum değişti.' ) );
			break;
		case 'delete':
			$r = flxs_delete_template( $id );
			flxs_redirect( 'flxs-sablonlar', is_wp_error( $r ) ? array( 'flxs_err' => $r->get_error_message() ) : array( 'flxs_msg' => 'Silindi.' ) );
			break;
		case 'export':
			$bg  = $t['config']['background'];
			$out = array( 'flxs_template' => 1, 'name' => $t['name'], 'config' => $t['config'] );
			unset( $out['config']['background']['url'] );
			$path = '';
			if ( 'attachment' === $bg['source'] && $bg['id'] ) {
				$path = get_attached_file( (int) $bg['id'] );
			} elseif ( 'bundled' === $bg['source'] ) {
				$path = flxs_bundled_path( $bg['file'] );
			}
			if ( $path && file_exists( $path ) ) {
				$out['background_file'] = array( 'name' => basename( $path ), 'data' => base64_encode( file_get_contents( $path ) ) ); // phpcs:ignore
			}
			nocache_headers();
			header( 'Content-Type: application/json; charset=utf-8' );
			header( 'Content-Disposition: attachment; filename="sablon-' . sanitize_title( $t['name'] ) . '.json"' );
			echo wp_json_encode( $out, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT ); // phpcs:ignore
			exit;
	}
	flxs_redirect( 'flxs-sablonlar' );
}

function flxs_handle_tpl_import() {
	check_admin_referer( 'flxs_tpl_import' );
	if ( ! flxs_can_manage() ) {
		wp_die( 'Yetki yok' );
	}
	if ( empty( $_FILES['file']['tmp_name'] ) ) {
		flxs_redirect( 'flxs-sablonlar', array( 'flxs_err' => 'Dosya seçin.' ) );
	}
	$j = json_decode( file_get_contents( $_FILES['file']['tmp_name'] ), true ); // phpcs:ignore
	if ( empty( $j['flxs_template'] ) || empty( $j['config'] ) ) {
		flxs_redirect( 'flxs-sablonlar', array( 'flxs_err' => 'Geçerli bir şablon dosyası değil.' ) );
	}
	$config = $j['config'];
	if ( ! empty( $j['background_file']['data'] ) ) {
		$name = sanitize_file_name( $j['background_file']['name'] );
		$bits = base64_decode( $j['background_file']['data'] ); // phpcs:ignore
		$ext  = strtolower( pathinfo( $name, PATHINFO_EXTENSION ) );
		if ( in_array( $ext, array( 'pdf', 'jpg', 'jpeg', 'png', 'webp' ), true ) ) {
			$up = wp_upload_bits( $name, null, $bits );
			if ( empty( $up['error'] ) ) {
				$type = wp_check_filetype( $up['file'] );
				$aid  = wp_insert_attachment( array( 'post_mime_type' => $type['type'], 'post_title' => 'Sertifika arka planı: ' . sanitize_text_field( $j['name'] ), 'post_status' => 'inherit' ), $up['file'] );
				$config['background']['source'] = 'attachment';
				$config['background']['id']     = (int) $aid;
			}
		}
	}
	$id = flxs_save_template( 0, sanitize_text_field( $j['name'] ) . ' (içe aktarıldı)', $config, 'inactive' );
	flxs_redirect( 'flxs-sablonlar', array( 'edit' => $id, 'flxs_msg' => 'Şablon içe aktarıldı (pasif).' ) );
}

function flxs_page_templates() {
	if ( flxs_get_param( 'edit' ) || flxs_get_param( 'new' ) ) {
		flxs_page_template_editor( (int) flxs_get_param( 'edit' ) );
		return;
	}
	global $wpdb;
	$counts = array();
	foreach ( $wpdb->get_results( 'SELECT template_id, COUNT(*) n FROM ' . flxs_table( 'certificates' ) . ' GROUP BY template_id', ARRAY_A ) as $r ) {
		$counts[ $r['template_id'] ] = (int) $r['n'];
	}
	flxs_admin_header(
		'Şablonlar',
		array( array( 'label' => '+ Yeni şablon', 'url' => admin_url( 'admin.php?page=flxs-sablonlar&new=1' ), 'class' => 'button-primary' ) ),
		'Her şablon bir arka plan (PDF ya da görsel) ve üzerine yazılan alanlardan oluşur. Canva vb. ile hazırladığınız PDF\'i yükleyip isim, tarih gibi alanları sürükleyerek yerleştirin.'
	);
	echo '<div class="flxs-tplgrid">';
	foreach ( flxs_list_templates() as $t ) {
		$thumb = $t['thumb'] ? flxs_upload_dir()['url'] . '/' . $t['thumb'] : '';
		$act   = function ( $op, $label, $extra = '' ) use ( $t ) {
			$url = wp_nonce_url( admin_url( 'admin-post.php?action=flxs_tpl_action&op=' . $op . '&id=' . (int) $t['id'] ), 'flxs_tpl_' . $op . '_' . (int) $t['id'] );
			return '<a href="' . esc_url( $url ) . '" ' . $extra . '>' . esc_html( $label ) . '</a>';
		};
		echo '<div class="flxs-tplcard' . ( 'active' === $t['status'] ? '' : ' is-off' ) . '">';
		echo '<a class="flxs-tplcard__img" href="' . esc_url( admin_url( 'admin.php?page=flxs-sablonlar&edit=' . (int) $t['id'] ) ) . '">' . ( $thumb ? '<img src="' . esc_url( $thumb ) . '" alt="">' : '<span class="dashicons dashicons-awards"></span><small>Önizleme kaydedilince oluşur</small>' ) . '</a>';
		echo '<div class="flxs-tplcard__body"><strong>' . esc_html( $t['name'] ) . '</strong> ' . flxs_status_badge( $t['status'] ); // phpcs:ignore
		echo ! empty( $t['config']['donor']['selectable'] ) ? ' <span class="flxs-pill flxs-pill--sent">' . esc_html__( 'Bağış sayfasında', 'flx-certificates' ) . '</span>' : '';
		echo '<div class="flxs-muted">' . count( $t['config']['fields'] ) . ' alan · ' . (int) ( $counts[ $t['id'] ] ?? 0 ) . ' sertifika</div>';
		echo '<div class="flxs-tplcard__acts"><a href="' . esc_url( admin_url( 'admin.php?page=flxs-sablonlar&edit=' . (int) $t['id'] ) ) . '">Düzenle</a>' .
			$act( 'duplicate', 'Çoğalt' ) . $act( 'toggle', 'active' === $t['status'] ? 'Pasif yap' : 'Aktif yap' ) . $act( 'export', 'Dışa aktar' ) .
			$act( 'delete', 'Sil', 'class="flxs-danger" data-confirm="Şablon silinsin mi?"' ) . '</div>'; // phpcs:ignore
		echo '</div></div>';
	}
	echo '</div>';
	flxs_ecards_box();
	?>
	<form method="post" enctype="multipart/form-data" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="flxs-card flxs-import">
		<?php wp_nonce_field( 'flxs_tpl_import' ); ?>
		<input type="hidden" name="action" value="flxs_tpl_import">
		<strong>Şablon içe aktar</strong> <span class="flxs-muted">(başka bir sitedeki "Dışa aktar" dosyası, arka planıyla birlikte)</span>
		<input type="file" name="file" accept=".json,application/json"> <button class="button">İçe aktar</button>
	</form>
	<?php
	flxs_admin_footer();
}

function flxs_page_template_editor( $id ) {
	$t = $id ? flxs_get_template( $id ) : null;
	if ( $id && ! $t ) {
		wp_die( 'Şablon yok.' );
	}
	if ( ! $t ) {
		$t = array(
			'id'     => 0,
			'name'   => '',
			'status' => 'active',
			'thumb'  => '',
			'config' => flxs_normalize_template_config(
				array(
					'title'  => 'Sertifika',
					'fields' => array(
						array( 'id' => 'ad', 'key' => 'ad_soyad', 'label' => 'Ad Soyad', 'type' => 'text', 'required' => true, 'x' => 120.9, 'y' => 250, 'w' => 600, 'size' => 40, 'font' => 'playfair-bold', 'align' => 'center' ),
					),
				)
			),
		);
	}
	$fonts = array();
	foreach ( flxs_fonts() as $fid => $f ) {
		$fonts[] = array( 'id' => $fid, 'label' => $f['label'] );
	}
	$data = array(
		'template' => flxs_template_for_js( $t ),
		'fonts'    => $fonts,
		'types'    => flxs_field_types(),
		'defaults' => array(
			'sms'           => flxs_setting( 'msg_sms' ),
			'whatsapp'      => flxs_setting( 'msg_whatsapp' ),
			'email_subject' => flxs_setting( 'email_subject' ),
			'email_body'    => flxs_setting( 'email_body' ),
		),
	);
	flxs_admin_header( $t['id'] ? 'Şablonu düzenle' : 'Yeni şablon', array( array( 'label' => '← Şablonlar', 'url' => admin_url( 'admin.php?page=flxs-sablonlar' ) ) ) );
	?>
	<script type="application/json" id="flxs-tpl-data"><?php echo wp_json_encode( $data, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP ); ?></script>
	<div class="flxs-te" id="flxs-te">
		<div class="flxs-te__side">
			<section class="flxs-card">
				<label class="flxs-field"><span>Şablon adı (panelde görünür)</span><input type="text" id="te-name"></label>
				<label class="flxs-field"><span>Sertifika başlığı (sayfada, mesajlarda {sertifika})</span><input type="text" id="te-title"></label>
				<div class="flxs-grid">
					<label class="flxs-field"><span>Durum</span><select id="te-status"><option value="active">Aktif</option><option value="inactive">Pasif</option></select></label>
					<label class="flxs-field"><span>Tarih biçimi</span><select id="te-datefmt"><option value="d.m.Y">09.10.2026</option><option value="j F Y">9 Ekim 2026</option><option value="d/m/Y">09/10/2026</option><option value="F Y">Ekim 2026</option></select></label>
				</div>
			</section>

			<section class="flxs-card">
				<h2 class="flxs-card__title">Arka plan</h2>
				<div class="flxs-grid">
					<label class="flxs-field"><span>Tür</span><select id="te-bgtype"><option value="pdf">PDF (önerilen)</option><option value="image">Görsel (JPG/PNG)</option><option value="none">Düz renk</option></select></label>
					<label class="flxs-field" id="te-color-wrap"><span>Renk</span><input type="color" id="te-bgcolor"></label>
				</div>
				<div class="flxs-bgfile" id="te-bgfile">
					<span id="te-bgname" class="flxs-muted"></span>
					<button type="button" class="button" id="te-bgpick">Dosya seç / yükle</button>
				</div>
				<div class="flxs-grid" id="te-size">
					<label class="flxs-field"><span>Sayfa boyutu</span><select id="te-preset"><option value="">Özel</option><option value="841.89x595.28">A4 yatay</option><option value="595.28x841.89">A4 dikey</option><option value="1060.5x1500">Canva dikey (1060×1500)</option><option value="1500x1060.5">Canva yatay</option></select></label>
					<label class="flxs-field"><span>Genişlik (pt)</span><input type="number" step="0.01" id="te-w"></label>
					<label class="flxs-field"><span>Yükseklik (pt)</span><input type="number" step="0.01" id="te-h"></label>
				</div>
				<p class="description">PDF/görsel seçince boyut otomatik alınır. Canva'dan "PDF Baskı" olarak indirip değişecek yazıları (isim vb.) silerek yükleyin.</p>
			</section>

			<section class="flxs-card">
				<div class="flxs-card__head"><h2 class="flxs-card__title">Alanlar</h2>
					<select id="te-add" class="flxs-add-field"><option value="">+ Alan ekle</option></select>
				</div>
				<ul class="flxs-fields" id="te-fields"></ul>
			</section>

			<section class="flxs-card flxs-fprops" id="te-props" hidden></section>

			<section class="flxs-card">
				<h2 class="flxs-card__title"><?php esc_html_e( 'Bağış sayfası', 'flx-certificates' ); ?></h2>
				<label class="flxs-check"><input type="checkbox" id="te-donor"> <?php esc_html_e( 'Bağışçı bu tasarımı bağış sayfasında seçebilir', 'flx-certificates' ); ?></label>
				<div class="flxs-grid" id="te-donor-opts">
					<label class="flxs-field"><span><?php esc_html_e( 'Başlık (grup)', 'flx-certificates' ); ?></span><input type="text" id="te-donor-group" placeholder="<?php esc_attr_e( 'Sertifikalar', 'flx-certificates' ); ?>"></label>
					<label class="flxs-field"><span><?php esc_html_e( 'Sıra', 'flx-certificates' ); ?></span><input type="number" id="te-donor-sort" step="1"></label>
				</div>
				<p class="description"><?php esc_html_e( 'Bağışçı tasarımı seçer, şablondaki metin, tarih ve seçim alanlarını (ör. alıcı adı, mesaj) doldurur. Fiyat bağış türünden gelir.', 'flx-certificates' ); ?></p>
			</section>

			<section class="flxs-card">
				<h2 class="flxs-card__title">Paylaşım önizlemesi (link)</h2>
				<label class="flxs-field"><span>Başlık (og:title)</span><input type="text" id="te-ogt"></label>
				<label class="flxs-field"><span>Açıklama</span><input type="text" id="te-ogd"></label>
				<p class="description">WhatsApp/Telegram/Facebook'ta link paylaşılınca bu başlık ve sertifikanın görseli çıkar. Yer tutucular: {ad_soyad} {ad} {sertifika} {tarih} {site} ve alan anahtarları.</p>
			</section>

			<section class="flxs-card">
				<h2 class="flxs-card__title">Bu şablona özel mesajlar <small>boşsa Ayarlar'daki varsayılan</small></h2>
				<label class="flxs-field"><span>SMS</span><textarea id="te-msg-sms" rows="3"></textarea></label>
				<label class="flxs-field"><span>WhatsApp</span><textarea id="te-msg-whatsapp" rows="4"></textarea></label>
				<label class="flxs-field"><span>E-posta konusu</span><input type="text" id="te-msg-email_subject"></label>
				<label class="flxs-field"><span>E-posta metni ({buton} = sertifika butonu)</span><textarea id="te-msg-email_body" rows="5"></textarea></label>
			</section>
		</div>

		<div class="flxs-te__main">
			<div class="flxs-te__bar">
				<span class="flxs-muted">Alanları sürükleyin, sağ kenardan genişliği ayarlayın. Ok tuşlarıyla 1pt (Shift ile 10pt) kaydırın.</span>
				<label class="flxs-te__sample"><input type="checkbox" id="te-sample" checked> Örnek verilerle</label>
				<button type="button" class="button button-primary" id="te-save">Kaydet</button>
			</div>
			<div class="flxs-te__stage" id="te-stage">
				<canvas id="te-canvas"></canvas>
				<div class="flxs-te__overlay" id="te-overlay"></div>
				<div class="flxs-preview__spin" id="te-spin"></div>
			</div>
			<p class="flxs-preview__err" id="te-err" hidden></p>
		</div>
	</div>
	<?php
	flxs_admin_footer();
}
