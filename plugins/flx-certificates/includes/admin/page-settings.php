<?php
defined( 'ABSPATH' ) || exit;

add_action( 'admin_post_flxs_settings_save', 'flxs_handle_settings_save' );
add_action( 'admin_post_flxs_font_add', 'flxs_handle_font_add' );
add_action( 'admin_post_flxs_font_del', 'flxs_handle_font_del' );

function flxs_handle_settings_save() {
	check_admin_referer( 'flxs_settings_save' );
	if ( ! flxs_can_manage() ) {
		wp_die( 'Yetki yok' );
	}
	$in  = wp_unslash( $_POST['s'] ?? array() ); // phpcs:ignore
	$old = flxs_settings();
	$s   = $old;
	$s['slug']           = sanitize_title( $in['slug'] ?? 'sertifika' ) ? sanitize_title( $in['slug'] ) : 'sertifika';
	$s['org_name']       = sanitize_text_field( $in['org_name'] ?? '' );
	$s['number_format']  = sanitize_text_field( $in['number_format'] ?? 'YT-{yil}-{sira}' );
	$s['code_length']    = max( 8, min( 32, (int) ( $in['code_length'] ?? 14 ) ) );
	$s['noindex']        = empty( $in['noindex'] ) ? 0 : 1;
	foreach ( array( 'msg_sms', 'msg_whatsapp', 'email_body' ) as $k ) {
		$s[ $k ] = sanitize_textarea_field( $in[ $k ] ?? '' );
	}
	$s['email_subject']  = sanitize_text_field( $in['email_subject'] ?? '' );
	$s['queue_batch']    = max( 1, min( 200, (int) ( $in['queue_batch'] ?? 25 ) ) );
	$s['max_attempts']   = max( 1, min( 10, (int) ( $in['max_attempts'] ?? 3 ) ) );
	$s['staff_bulk']     = empty( $in['staff_bulk'] ) ? 0 : 1;
	$s['staff_own_only'] = empty( $in['staff_own_only'] ) ? 0 : 1;
	$s['lookup_enabled'] = empty( $in['lookup_enabled'] ) ? 0 : 1;
	$s['lookup_channels'] = array_values( array_intersect( (array) ( $in['lookup_channels'] ?? array() ), array_keys( flxs_channels() ) ) );
	$s['log_retention']  = max( 0, (int) ( $in['log_retention'] ?? 0 ) );
	$s['delete_on_uninstall'] = empty( $in['delete_on_uninstall'] ) ? 0 : 1;
	update_option( 'flxs_settings', $s, false );
	if ( $s['slug'] !== $old['slug'] ) {
		update_option( 'flxs_flush_rewrite', 1 );
	}
	flxs_install_roles();
	$diff = array();
	foreach ( $s as $k => $v ) {
		if ( ( $old[ $k ] ?? null ) !== $v ) {
			$diff[] = $k;
		}
	}
	flxs_log( 'settings_saved', 'settings', 0, $diff ? 'Değişen: ' . implode( ', ', $diff ) : 'Değişiklik yok' );
	flxs_redirect( 'flxs-ayarlar', array( 'flxs_msg' => 'Ayarlar kaydedildi.' ) );
}

function flxs_handle_font_add() {
	check_admin_referer( 'flxs_font_add' );
	if ( ! flxs_can_manage() ) {
		wp_die( 'Yetki yok' );
	}
	$aid   = (int) ( $_POST['attachment_id'] ?? 0 );
	$label = sanitize_text_field( wp_unslash( $_POST['label'] ?? '' ) );
	$file  = $aid ? get_attached_file( $aid ) : '';
	if ( ! $file || ! preg_match( '/\.(ttf|otf)$/i', $file ) ) {
		flxs_redirect( 'flxs-ayarlar', array( 'flxs_err' => 'TTF ya da OTF font dosyası seçin.' ) );
	}
	$fonts   = (array) get_option( 'flxs_custom_fonts', array() );
	$fonts[] = array( 'attachment_id' => $aid, 'label' => $label ? $label : basename( $file ) );
	update_option( 'flxs_custom_fonts', $fonts, false );
	flxs_redirect( 'flxs-ayarlar', array( 'flxs_msg' => 'Font eklendi; şablon editöründe seçebilirsiniz.' ) );
}

function flxs_handle_font_del() {
	check_admin_referer( 'flxs_font_del' );
	if ( ! flxs_can_manage() ) {
		wp_die( 'Yetki yok' );
	}
	$aid   = (int) $_POST['attachment_id'];
	$fonts = array_values(
		array_filter(
			(array) get_option( 'flxs_custom_fonts', array() ),
			function ( $f ) use ( $aid ) {
				return (int) $f['attachment_id'] !== $aid;
			}
		)
	);
	update_option( 'flxs_custom_fonts', $fonts, false );
	flxs_redirect( 'flxs-ayarlar', array( 'flxs_msg' => 'Font kaldırıldı.' ) );
}

function flxs_page_settings() {
	$s = flxs_settings();
	wp_enqueue_media();
	flxs_admin_header( 'Sertifika ayarları' );
	$n = function ( $k ) {
		return 's[' . $k . ']';
	};
	?>
	<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="flxs-settings">
		<?php wp_nonce_field( 'flxs_settings_save' ); ?>
		<input type="hidden" name="action" value="flxs_settings_save">

		<section class="flxs-card">
			<h2 class="flxs-card__title">Genel</h2>
			<div class="flxs-grid">
				<label class="flxs-field"><span>Link adresi</span><span class="flxs-prefix"><?php echo esc_html( trailingslashit( home_url() ) ); ?><input type="text" name="<?php echo esc_attr( $n( 'slug' ) ); ?>" value="<?php echo esc_attr( $s['slug'] ); ?>" style="width:110px">/kod</span></label>
				<label class="flxs-field"><span>Kod uzunluğu</span><input type="number" name="<?php echo esc_attr( $n( 'code_length' ) ); ?>" value="<?php echo (int) $s['code_length']; ?>" min="8" max="32"></label>
				<label class="flxs-field"><span>Sertifika no biçimi</span><input type="text" name="<?php echo esc_attr( $n( 'number_format' ) ); ?>" value="<?php echo esc_attr( $s['number_format'] ); ?>"><small class="flxs-muted">{yil} {sira} {id} {kod}</small></label>
			</div>
			<label class="flxs-field"><span>Düzenleyen kurum (sertifika sayfasında, LinkedIn'de)</span><input type="text" name="<?php echo esc_attr( $n( 'org_name' ) ); ?>" value="<?php echo esc_attr( $s['org_name'] ); ?>"></label>
			<label><input type="checkbox" name="<?php echo esc_attr( $n( 'noindex' ) ); ?>" value="1" <?php checked( $s['noindex'] ); ?>> Sertifika sayfaları Google'da çıkmasın (noindex; kişisel veri için önerilir)</label>
		</section>

		<section class="flxs-card">
			<h2 class="flxs-card__title">Varsayılan mesajlar</h2>
			<p class="description">Şablonda özel mesaj yoksa bunlar kullanılır. Yer tutucular: <code>{ad}</code> <code>{ad_soyad}</code> <code>{soyad}</code> <code>{link}</code> <code>{sertifika}</code> <code>{sertifika_no}</code> <code>{tarih}</code> <code>{site}</code> <code>{kurum}</code>, şablon alanları (ör. <code>{fidan_sayisi}</code>), e-postada <code>{buton}</code>.</p>
			<label class="flxs-field"><span>SMS</span><textarea name="<?php echo esc_attr( $n( 'msg_sms' ) ); ?>" rows="3"><?php echo esc_textarea( $s['msg_sms'] ); ?></textarea></label>
			<label class="flxs-field"><span>WhatsApp (*kalın*, _italik_)</span><textarea name="<?php echo esc_attr( $n( 'msg_whatsapp' ) ); ?>" rows="5"><?php echo esc_textarea( $s['msg_whatsapp'] ); ?></textarea></label>
			<label class="flxs-field"><span>E-posta konusu</span><input type="text" name="<?php echo esc_attr( $n( 'email_subject' ) ); ?>" value="<?php echo esc_attr( $s['email_subject'] ); ?>"></label>
			<label class="flxs-field"><span>E-posta metni</span><textarea name="<?php echo esc_attr( $n( 'email_body' ) ); ?>" rows="6"><?php echo esc_textarea( $s['email_body'] ); ?></textarea></label>
		</section>

		<section class="flxs-card">
			<h2 class="flxs-card__title">Personel yetkileri</h2>
			<p class="description">Personel için Kullanıcılar › Yeni ekle › Rol: <strong>Sertifika Personeli</strong>. Bu rol yalnızca sertifika oluşturur, kişileri görür ve gönderim yapar; şablon, sağlayıcı, ayar ve logları göremez.</p>
			<label><input type="checkbox" name="<?php echo esc_attr( $n( 'staff_bulk' ) ); ?>" value="1" <?php checked( $s['staff_bulk'] ); ?>> Personel toplu gönderim yapabilsin</label><br>
			<label><input type="checkbox" name="<?php echo esc_attr( $n( 'staff_own_only' ) ); ?>" value="1" <?php checked( $s['staff_own_only'] ); ?>> Personel yalnızca kendi oluşturduğu sertifikaları görsün</label>
		</section>

		<section class="flxs-card">
			<h2 class="flxs-card__title">Kuyruk</h2>
			<div class="flxs-grid">
				<label class="flxs-field"><span>Bir çalışmada en çok mesaj</span><input type="number" name="<?php echo esc_attr( $n( 'queue_batch' ) ); ?>" value="<?php echo (int) $s['queue_batch']; ?>"></label>
				<label class="flxs-field"><span>Hata olursa deneme sayısı</span><input type="number" name="<?php echo esc_attr( $n( 'max_attempts' ) ); ?>" value="<?php echo (int) $s['max_attempts']; ?>"></label>
			</div>
			<p class="description">İşleyici: <strong><?php echo esc_html( flxs_queue_runner_name() ); ?></strong>. Canlı sunucuda düzenli çalışması için sunucu cron'u önerilir: <code>* * * * * wget -q -O - <?php echo esc_html( site_url( 'wp-cron.php?doing_wp_cron' ) ); ?> &gt;/dev/null 2&gt;&amp;1</code></p>
		</section>

		<section class="flxs-card">
			<h2 class="flxs-card__title">"Sertifikamı bul" sayfası</h2>
			<label><input type="checkbox" name="<?php echo esc_attr( $n( 'lookup_enabled' ) ); ?>" value="1" <?php checked( $s['lookup_enabled'] ); ?>> Açık</label>
			<p class="description">Bir sayfaya <code>[flx_sertifika_sorgula]</code> kısa kodunu ekleyin. Kişi telefonunu/e-postasını yazar; sertifika linkleri yalnızca o numaraya/adrese gönderilir, sayfada bilgi gösterilmez. IP başına saatte 10, aynı numaraya 15 dakikada 1 istek.</p>
			<?php foreach ( flxs_channels() as $k => $l ) : ?>
				<label><input type="checkbox" name="<?php echo esc_attr( $n( 'lookup_channels' ) ); ?>[]" value="<?php echo esc_attr( $k ); ?>" <?php checked( in_array( $k, (array) $s['lookup_channels'], true ) ); ?>> <?php echo esc_html( $l ); ?></label>
			<?php endforeach; ?>
		</section>

		<section class="flxs-card">
			<h2 class="flxs-card__title">Veri</h2>
			<label class="flxs-field flxs-field--inline"><span>Logları sakla (gün, 0 = süresiz)</span><input type="number" name="<?php echo esc_attr( $n( 'log_retention' ) ); ?>" value="<?php echo (int) $s['log_retention']; ?>"></label>
			<label><input type="checkbox" name="<?php echo esc_attr( $n( 'delete_on_uninstall' ) ); ?>" value="1" <?php checked( $s['delete_on_uninstall'] ); ?>> Eklenti silinirse tüm sertifika verisini de sil</label>
			<p class="description">Tüm veriler <code><?php echo esc_html( $GLOBALS['wpdb']->prefix ); ?>flxs_*</code> tablolarında, dosyalar <code>wp-content/uploads/flx-certificates/</code> klasöründe. Tam site taşıma (All-in-One WP Migration, Duplicator) ikisini de taşır.</p>
		</section>

		<p><button class="button button-primary button-large">Ayarları kaydet</button></p>
	</form>

	<section class="flxs-card">
		<h2 class="flxs-card__title">Ek fontlar</h2>
		<p class="description">Hazır fontlar: Nunito, Montserrat, Playfair Display, Cinzel, EB Garamond, Great Vibes, Dancing Script. Kendi TTF/OTF fontunuzu ekleyebilirsiniz (Türkçe karakter içermeli).</p>
		<?php foreach ( (array) get_option( 'flxs_custom_fonts', array() ) as $f ) : ?>
			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="flxs-li">
				<?php wp_nonce_field( 'flxs_font_del' ); ?><input type="hidden" name="action" value="flxs_font_del"><input type="hidden" name="attachment_id" value="<?php echo (int) $f['attachment_id']; ?>">
				<strong><?php echo esc_html( $f['label'] ); ?></strong> <button class="button-link flxs-danger">kaldır</button>
			</form>
		<?php endforeach; ?>
		<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" id="flxs-font-form">
			<?php wp_nonce_field( 'flxs_font_add' ); ?><input type="hidden" name="action" value="flxs_font_add">
			<input type="hidden" name="attachment_id" id="flxs-font-id">
			<input type="text" name="label" placeholder="Font adı">
			<button type="button" class="button" id="flxs-font-pick">Font dosyası seç (TTF/OTF)</button>
		</form>
		<script>
		document.getElementById('flxs-font-pick').addEventListener('click',function(){var f=wp.media({title:'Font dosyası',multiple:false,button:{text:'Ekle'}});f.on('select',function(){var a=f.state().get('selection').first().toJSON();document.getElementById('flxs-font-id').value=a.id;var l=document.querySelector('#flxs-font-form [name=label]');if(!l.value)l.value=a.title;document.getElementById('flxs-font-form').submit();});f.open();});
		</script>
	</section>
	<?php
	flxs_admin_footer();
}
