<?php
defined( 'ABSPATH' ) || exit;

function flxs_page_bulk() {
	if ( ! flxs_can_bulk() ) {
		wp_die( 'Toplu gönderim yetkiniz yok.' );
	}
	$cert_ids   = array_filter( array_map( 'intval', explode( ',', flxs_get_param( 'ids' ) ) ) );
	$person_ids = array_filter( array_map( 'intval', explode( ',', flxs_get_param( 'people' ) ) ) );
	$channel    = flxs_get_param( 'channel', 'whatsapp' );
	$mode       = $cert_ids ? 'certificates' : ( $person_ids ? 'people' : 'certificates' );
	$tpls       = flxs_list_templates();
	$plain      = "Merhaba {ad},\n\n";

	flxs_admin_header( 'Toplu gönderim', array(), 'Sertifika linklerini ya da serbest bir mesajı çok kişiye gönderin. Önce önizleyin, sonra kuyruğa alın; ilerlemeyi Gönderim Kuyruğu\'ndan izleyin.' );
	?>
	<form id="flxs-bulk" class="flxs-card flxs-form"
		data-default-sms="<?php echo esc_attr( $plain . 'Örnek Vakfı' ); ?>"
		data-default-whatsapp="<?php echo esc_attr( $plain . "\nÖrnek Vakfı 🌱" ); ?>"
		data-default-email="<?php echo esc_attr( $plain . "\n\nÖrnek Vakfı" ); ?>"
		data-default-subject="<?php echo esc_attr( get_bloginfo( 'name' ) ); ?>">

		<h2 class="flxs-card__title">Kime?</h2>
		<div class="flxs-radios">
			<label><input type="radio" name="mode" value="certificates" <?php checked( $mode, 'certificates' ); ?>> Sertifika sahiplerine sertifika linki</label>
			<label><input type="radio" name="mode" value="people" <?php checked( $mode, 'people' ); ?>> Kayıtlı kişilere mesaj</label>
			<label><input type="radio" name="mode" value="list"> Numara / e-posta listesine</label>
		</div>

		<div data-mode="certificates">
			<?php if ( $cert_ids ) : ?>
				<p><strong><?php echo count( $cert_ids ); ?> seçili sertifika</strong> · <a href="<?php echo esc_url( admin_url( 'admin.php?page=flxs-toplu' ) ); ?>">seçimi temizle</a></p>
				<?php foreach ( $cert_ids as $id ) : ?><input type="hidden" name="certificate_ids[]" value="<?php echo (int) $id; ?>"><?php endforeach; ?>
			<?php else : ?>
				<label class="flxs-field"><span>Şablon: bu şablonla verilmiş tüm geçerli sertifikalar</span>
					<select name="template_id"><?php foreach ( $tpls as $t ) : ?><option value="<?php echo (int) $t['id']; ?>"><?php echo esc_html( $t['name'] ); ?></option><?php endforeach; ?></select>
				</label>
				<p class="description">Belirli sertifikaları seçmek için Sertifikalar listesinde işaretleyip "Seçilenlere › … ile gönder" deyin.</p>
			<?php endif; ?>
		</div>

		<div data-mode="people">
			<?php if ( $person_ids ) : ?>
				<p><strong><?php echo count( $person_ids ); ?> seçili kişi</strong> · <a href="<?php echo esc_url( admin_url( 'admin.php?page=flxs-toplu' ) ); ?>">seçimi temizle</a></p>
				<?php foreach ( $person_ids as $id ) : ?><input type="hidden" name="person_ids[]" value="<?php echo (int) $id; ?>"><?php endforeach; ?>
			<?php else : ?>
				<label class="flxs-field"><span>Kişiler</span>
					<select name="template_id_people" onchange="this.form.querySelector('[name=template_id]') && 0">
						<option value="">Tüm kayıtlı kişiler</option>
						<?php foreach ( $tpls as $t ) : ?><option value="<?php echo (int) $t['id']; ?>">"<?php echo esc_html( $t['name'] ); ?>" sertifikası olanlar</option><?php endforeach; ?>
					</select>
				</label>
			<?php endif; ?>
		</div>

		<div data-mode="list">
			<label class="flxs-field"><span>Liste (her satıra bir alıcı; isterseniz <code>Ad Soyad; numara</code>)</span>
				<textarea name="list" rows="6" placeholder="0532 111 22 33&#10;Ayşe Yılmaz; 0533 444 55 66&#10;ornek@eposta.com"></textarea>
			</label>
		</div>

		<h2 class="flxs-card__title">Nasıl?</h2>
		<div class="flxs-radios">
			<?php foreach ( flxs_channels() as $k => $l ) : ?>
				<label><input type="radio" name="channel" value="<?php echo esc_attr( $k ); ?>" <?php checked( $channel, $k ); ?>> <?php echo flxs_channel_icon( $k ); // phpcs:ignore ?> <?php echo esc_html( $l ); ?></label>
			<?php endforeach; ?>
		</div>
		<label class="flxs-field flxs-field--inline"><span>Sağlayıcı</span><select name="provider_id"></select></label>

		<h2 class="flxs-card__title">Mesaj</h2>
		<p class="description" data-cert-hint>Boş bırakırsanız şablonun / Ayarlar'daki varsayılan sertifika mesajı kullanılır.</p>
		<div data-channel="email"><label class="flxs-field"><span>Konu</span><input type="text" name="subject"></label></div>
		<label class="flxs-field"><span>Metin</span><textarea name="body" rows="6"></textarea></label>
		<p class="flxs-charcount flxs-muted"></p>
		<p class="description">Yer tutucular: <code>{ad}</code> <code>{ad_soyad}</code> <code>{soyad}</code> <code>{link}</code> <code>{sertifika}</code> <code>{sertifika_no}</code> <code>{tarih}</code> <code>{site}</code> · e-postada <code>{buton}</code>. Kişi modunda <code>{link}</code> kişinin son sertifikasıdır.</p>
		<input type="hidden" name="template_id_bridge" value="">
		<p><button class="button button-primary" data-bulk-preview>Önizle</button></p>
		<div id="flxs-bulk-out" class="flxs-bulk-out"></div>
	</form>
	<script>
	(function(){var f=document.getElementById('flxs-bulk');var s=f.querySelector('[name=template_id_people]');if(!s)return;
	f.addEventListener('submit',function(e){e.preventDefault();});
	var sync=function(){var m=f.querySelector('[name=mode]:checked').value;var h=f.querySelector('[name=template_id_bridge]');if(m==='people'){h.name='template_id';h.value=s.value;}else{h.name='template_id_bridge';}};
	f.addEventListener('change',sync);sync();})();
	document.getElementById('flxs-bulk').addEventListener('submit',function(e){e.preventDefault();});
	</script>
	<?php
	flxs_admin_footer();
}
