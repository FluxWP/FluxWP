<?php
defined( 'ABSPATH' ) || exit;

add_action( 'admin_post_flxc_save_settings', 'flxc_save_settings' );

add_action(
	'admin_enqueue_scripts',
	function () {
		if ( ! isset( $_GET['page'] ) || 'flx-chat-ayarlar' !== $_GET['page'] ) { // phpcs:ignore
			return;
		}
		flxc_register_model_picker();
		wp_enqueue_style( 'flxc-settings', FLXC_URL . 'assets/css/settings.css', array( 'flxc-mp' ), FLXC_VERSION );
		wp_enqueue_script( 'flxc-settings', FLXC_URL . 'assets/js/settings.js', array( 'flxc-mp' ), FLXC_VERSION, true );
		wp_localize_script(
			'flxc-settings',
			'FLXC_SET',
			array(
				'rest'  => trailingslashit( rest_url( 'flx-chat/v1' ) ),
				'nonce' => wp_create_nonce( 'wp_rest' ),
				'i18n'  => array(
					'testing'     => __( 'Kontrol ediliyor…', 'flx-chat' ),
					'open'        => __( 'Bağlı ✓', 'flx-chat' ),
					'notOpen'     => __( 'Bağlı değil (durum: %s). QR ile bağlayın.', 'flx-chat' ),
					'whOk'        => __( 'Webhook bu siteye ayarlı ✓', 'flx-chat' ),
					/* translators: %s: Evolution API version */
					'evoVer'      => __( 'Evolution API sürümü: %s', 'flx-chat' ),
					'evoOld'      => __( '(eski: gizli numaralı (@lid) kişilere cevap gidemeyebilir, 2.3 veya üstüne güncelleyin)', 'flx-chat' ),
					'whMissing'   => __( 'Webhook bu siteye ayarlı değil. "Webhook\'u kur" düğmesine basın.', 'flx-chat' ),
					'whSet'       => __( 'Webhook kuruldu ✓ Artık gelen mesajlar buraya düşer.', 'flx-chat' ),
					'importing'   => __( 'Son sohbetler içe aktarılıyor, bu biraz sürebilir…', 'flx-chat' ),
					/* translators: 1: chats, 2: messages */
					'stored'      => __( 'Evolution\'da kayıtlı mesaj: %s', 'flx-chat' ),
					'noStore'     => __( 'Evolution mesajları kaydetmiyor, bu yüzden eski mesajlar gelemez. Evolution sunucusunda DATABASE_SAVE_DATA_NEW_MESSAGE ve DATABASE_SAVE_DATA_HISTORIC değerlerini true yapın; eski konuşmalar için hattı QR ile yeniden bağlarken instance ayarında "Sync full history" açık olsun.', 'flx-chat' ),
					'imported'    => __( '%1$s sohbet, %2$s mesaj içe aktarıldı ✓', 'flx-chat' ),
					'qrScan'      => __( 'WhatsApp › Bağlı cihazlar › Cihaz bağla ile bu kodu okutun.', 'flx-chat' ),
					'pairCode'    => __( 'Eşleştirme kodu:', 'flx-chat' ),
					'alreadyOpen' => __( 'Bu hat zaten bağlı ✓', 'flx-chat' ),
					'saveFirst'   => __( 'Önce ayarları kaydedin.', 'flx-chat' ),
					'copied'      => __( 'Kopyalandı', 'flx-chat' ),
					'error'       => __( 'Hata:', 'flx-chat' ),
					'confirmDel'  => __( 'Bu bağlantı silinsin mi? Sohbet geçmişi silinmez.', 'flx-chat' ),
					'aiTesting'   => __( 'Yapay zekâya soruluyor…', 'flx-chat' ),
					/* translators: 1: reply, 2: ms */
					'aiOk'        => __( 'Çalışıyor ✓ (%2$s ms): %1$s', 'flx-chat' ),
					'saveKey'     => __( 'Önce anahtarı ve modeli kaydedin.', 'flx-chat' ),
				),
			)
		);
	}
);

function flxc_settings_tabs() {
	return array(
		'baglantilar' => __( 'Bağlantılar', 'flx-chat' ),
		'genel'       => __( 'Genel', 'flx-chat' ),
		'otomatik'    => __( 'Otomatik yanıtlar', 'flx-chat' ),
		'etiketler'   => __( 'Etiketler', 'flx-chat' ),
		'kayitlar'    => __( 'Kayıtlar', 'flx-chat' ),
		'ai'          => __( 'Yapay zekâ ve asistan', 'flx-chat' ),
		'kontrol'     => __( 'Sistem kontrolü', 'flx-chat' ),
	);
}

function flxc_render_settings() {
	if ( ! flxc_can_manage() ) {
		wp_die( esc_html__( 'Bu sayfaya erişim yetkiniz yok.', 'flx-chat' ) );
	}
	$tabs = flxc_settings_tabs();
	$tab  = isset( $_GET['tab'] ) && isset( $tabs[ $_GET['tab'] ] ) ? sanitize_key( $_GET['tab'] ) : 'baglantilar'; // phpcs:ignore
	$s    = flxc_settings();
	?>
	<div class="wrap flxcs">
		<header class="flxcs-head">
			<div>
				<h1><?php esc_html_e( 'Çağrı Merkezi Ayarları', 'flx-chat' ); ?></h1>
				<p><?php esc_html_e( 'WhatsApp hattınızı bağlayın, otomatik yanıtları ve etiketleri yönetin.', 'flx-chat' ); ?></p>
			</div>
			<a class="flxcs-btn" href="<?php echo esc_url( admin_url( 'admin.php?page=flx-chat' ) ); ?>"><?php esc_html_e( 'Sohbetleri aç', 'flx-chat' ); ?> →</a>
		</header>
		<?php if ( isset( $_GET['saved'] ) ) : // phpcs:ignore ?>
			<div class="flxcs-note flxcs-note-ok"><?php esc_html_e( 'Ayarlar kaydedildi.', 'flx-chat' ); ?></div>
		<?php endif; ?>
		<?php if ( isset( $_GET['err'] ) ) : // phpcs:ignore ?>
			<div class="flxcs-note flxcs-note-err"><?php echo esc_html( sanitize_text_field( wp_unslash( $_GET['err'] ) ) ); // phpcs:ignore ?></div>
		<?php endif; ?>
		<nav class="flxcs-tabs">
			<?php foreach ( $tabs as $k => $label ) : ?>
				<a href="<?php echo esc_url( admin_url( 'admin.php?page=flx-chat-ayarlar&tab=' . $k ) ); ?>" class="<?php echo $k === $tab ? 'is-on' : ''; ?>"><?php echo esc_html( $label ); ?></a>
			<?php endforeach; ?>
		</nav>
		<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="flxcs-form">
			<input type="hidden" name="action" value="flxc_save_settings">
			<input type="hidden" name="tab" value="<?php echo esc_attr( $tab ); ?>">
			<?php wp_nonce_field( 'flxc_save_settings' ); ?>
			<?php call_user_func( 'flxc_settings_tab_' . $tab, $s ); ?>
			<?php if ( ! in_array( $tab, array( 'ai', 'kontrol' ), true ) ) : ?>
				<div class="flxcs-save"><button type="submit" class="flxcs-btn flxcs-btn-primary"><?php esc_html_e( 'Kaydet', 'flx-chat' ); ?></button></div>
			<?php endif; ?>
		</form>
	</div>
	<?php
}

function flxc_settings_tab_kontrol( $s ) {
	?>
	<section class="flxcs-card">
		<h2><?php esc_html_e( 'Sistem kontrolü', 'flx-chat' ); ?></h2>
		<p class="flxcs-desc"><?php esc_html_e( 'Çağrı Merkezi\'nin çalışması için gereken bağlantılar ve ayarlar. Kayıtlı durumlar gösterilir; canlı deneme için ilgili sekmedeki "test et" düğmelerini kullanın. Aynı kontroller temanın sistem kontrolü ekranında diğer eklentilerle birlikte de görünür.', 'flx-chat' ); ?></p>
		<?php flxc_render_system_checks( flxc_system_checks() ); ?>
	</section>
	<?php
}

function flxc_settings_tab_baglantilar( $s ) {
	$flxs   = flxc_flxs_evolution_providers();
	$conns = flxc_connections();
	?>
	<section class="flxcs-card">
		<h2><?php esc_html_e( 'WhatsApp hatları', 'flx-chat' ); ?></h2>
		<p class="flxcs-desc">
			<?php if ( flxc_flxi_on() ) : ?>
				<?php esc_html_e( 'Mesajlar Evolution API üzerinden gönderilir ve alınır. WhatsApp bağlantıları Flux Mail\'de yönetilir; burada hangisinin kullanılacağını seçin. Adres ve API key orada kalır, değişince burada ayrıca güncellemek gerekmez.', 'flx-chat' ); ?>
				<a href="<?php echo esc_url( flxc_flxi_connections_url() ); ?>"><?php esc_html_e( 'İletişim › Bağlantılar', 'flx-chat' ); ?> →</a>
			<?php else : ?>
				<?php esc_html_e( 'Mesajlar Evolution API üzerinden gönderilir ve alınır. Flux Certificates eklentisinde Evolution sağlayıcısı tanımlıysa aynı bağlantıyı seçebilirsiniz; adres ve API key orada kalır.', 'flx-chat' ); ?>
			<?php endif; ?>
		</p>
		<div class="flxcs-conns" id="flxcs-conns">
			<?php
			$i = 0;
			foreach ( $conns as $c ) {
				flxc_settings_conn_row( $i++, $c, $flxs );
			}
			?>
		</div>
		<?php
		$flxi = flxc_flxi_whatsapp_choices();
		if ( $flxi ) {
			$tpl = array( 'source' => 'flxi', 'provider_id' => (int) $flxi[0]['id'] );
		} elseif ( $flxs && ! flxc_flxi_on() ) {
			$tpl = array( 'source' => 'flxs', 'provider_id' => (int) $flxs[0]['id'] );
		} else {
			$tpl = array( 'source' => 'own' );
		}
		?>
		<template id="flxcs-conn-tpl"><?php flxc_settings_conn_row( '__i__', flxc_new_connection( array( 'id' => '', 'secret' => '' ) + $tpl ), $flxs ); ?></template>
		<button type="button" class="flxcs-btn" id="flxcs-add-conn">+ <?php esc_html_e( 'Hat ekle', 'flx-chat' ); ?></button>
	</section>
	<section class="flxcs-card flxcs-steps">
		<h2><?php esc_html_e( 'Kurulum adımları', 'flx-chat' ); ?></h2>
		<ol>
			<li><?php esc_html_e( 'Hattı ekleyip kaydedin.', 'flx-chat' ); ?></li>
			<li><?php esc_html_e( '"Bağlantıyı test et" ile Evolution\'a ulaşıldığını görün. Hat bağlı değilse "QR ile bağla" deyip telefondan okutun.', 'flx-chat' ); ?></li>
			<li><?php esc_html_e( '"Webhook\'u kur" düğmesine basın. Gelen mesajlar, telefondan ve Flux Certificates\'dan gönderilenler canlı olarak buraya düşer.', 'flx-chat' ); ?></li>
			<li><?php esc_html_e( 'İsterseniz "Son sohbetleri içe aktar" ile geçmiş mesajları getirin.', 'flx-chat' ); ?></li>
		</ol>
	</section>
	<?php
}

/** Hattın "Bağlantı bilgisi" seçenekleri: Flux Mail bağlantıları, (Flux Mail yoksa) Flux Certificates sağlayıcıları, ayrı bilgi. */
function flxc_connection_source_choices( $c, $flxs ) {
	$out = array();
	$states = array(
		'open'       => __( 'bağlı', 'flx-chat' ),
		'connecting' => __( 'bağlanıyor', 'flx-chat' ),
		'close'      => __( 'bağlı değil', 'flx-chat' ),
		'missing'    => __( 'sunucuda yok', 'flx-chat' ),
		'error'      => __( 'ulaşılamıyor', 'flx-chat' ),
	);
	foreach ( flxc_flxi_whatsapp_choices() as $x ) {
		// 1.34.0: Flux Mail 1.9.0 satırlarında tür (evolution | flxwpapi) ve önbellekli durum gelir.
		$type  = (string) ( $x['type'] ?? '' );
		$extra = array_filter(
			array(
				'evolution' === $type ? 'Evolution API' : ( in_array( $type, array( 'flxwpapi', 'flx-wpapi' ), true ) ? 'Flux WPAPI' : ( flxc_wacloud_type( $type ) ? 'Cloud API' : '' ) ),
				(string) ( $x['me'] ?? '' ),
				$states[ (string) ( $x['state'] ?? '' ) ] ?? '',
			)
		);
		/* translators: %s: connection name */
		$out[ 'flxi:' . (int) $x['id'] ] = sprintf( __( 'Flux Mail bağlantısı: %s', 'flx-chat' ), $x['name'] ) . ( $extra ? ' (' . implode( ' · ', $extra ) . ')' : '' );
	}
	if ( 'flxi' === $c['source'] && ! isset( $out[ 'flxi:' . (int) $c['provider_id'] ] ) && flxc_flxi_on() ) {
		/* translators: %d: connection id */
		$out[ 'flxi:' . (int) $c['provider_id'] ] = sprintf( __( 'Flux Mail bağlantısı #%d (bulunamadı ya da kapalı)', 'flx-chat' ), (int) $c['provider_id'] );
	}
	foreach ( $flxs as $p ) {
		// Flux Mail varken yalnız hâlâ bu sağlayıcıyı kullanan hat için gösterilir.
		if ( ! flxc_flxi_on() || ( 'flxs' === $c['source'] && (int) $c['provider_id'] === (int) $p['id'] ) ) {
			/* translators: %s: provider name */
			$out[ 'flxs:' . (int) $p['id'] ] = sprintf( __( 'Flux Certificates sağlayıcısını kullan: %s', 'flx-chat' ), $p['name'] );
		}
	}
	// 1.34.0 (Ahmet): hesaplar Flux Mail'de yönetilir; İletişim etkinken ayrı bilgi yalnız zaten öyle kurulmuş hatta kalır.
	if ( ! flxc_flxi_on() || 'own' === $c['source'] ) {
		$out['own'] = __( 'Ayrı Evolution API bilgisi gir', 'flx-chat' );
	}
	return $out;
}

function flxc_connection_source_value( $c ) {
	if ( 'flxi' === $c['source'] && ! flxc_flxi_on() && (int) $c['flxs_id'] ) {
		return 'flxs:' . (int) $c['flxs_id']; // Flux Mail kapalı: taşınmadan önceki sağlayıcı
	}
	return in_array( $c['source'], array( 'flxi', 'flxs' ), true ) ? $c['source'] . ':' . (int) $c['provider_id'] : 'own';
}

function flxc_flxi_connections_url() {
	return function_exists( 'flxi_admin_url' ) ? flxi_admin_url( 'flxi-baglantilar' ) : admin_url( 'admin.php?page=flxi-baglantilar' );
}

function flxc_settings_conn_row( $i, $c, $flxs ) {
	$n     = 'connections[' . $i . ']';
	$saved = '' !== $c['id'];
	$st    = $saved ? flxc_connection_state( $c['id'] ) : array( 'state' => '' );
	$last  = $saved ? (int) get_option( 'flxc_last_webhook_' . $c['id'] ) : 0;
	?>
	<div class="flxcs-conn" data-conn="<?php echo esc_attr( $c['id'] ); ?>">
		<input type="hidden" name="<?php echo esc_attr( $n ); ?>[id]" value="<?php echo esc_attr( $c['id'] ); ?>">
		<input type="hidden" class="flxcs-del-flag" name="<?php echo esc_attr( $n ); ?>[delete]" value="">
		<div class="flxcs-conn-head">
			<span class="flxcs-state flxcs-state-<?php echo esc_attr( $st['state'] ? $st['state'] : 'unknown' ); ?>"></span>
			<input type="text" class="flxcs-conn-label" name="<?php echo esc_attr( $n ); ?>[label]" value="<?php echo esc_attr( $c['label'] ); ?>" placeholder="<?php esc_attr_e( 'Hat adı (ör. Ana hat)', 'flx-chat' ); ?>">
			<button type="button" class="flxcs-link flxcs-del"><?php esc_html_e( 'Sil', 'flx-chat' ); ?></button>
		</div>
		<div class="flxcs-grid">
			<label class="flxcs-field flxcs-span2"><span><?php esc_html_e( 'Bağlantı bilgisi', 'flx-chat' ); ?></span>
				<select name="<?php echo esc_attr( $n ); ?>[source]" class="flxcs-source">
					<?php foreach ( flxc_connection_source_choices( $c, $flxs ) as $val => $label ) : ?>
						<option value="<?php echo esc_attr( $val ); ?>" <?php selected( flxc_connection_source_value( $c ), $val ); ?>><?php echo esc_html( $label ); ?></option>
					<?php endforeach; ?>
				</select>
			</label>
			<label class="flxcs-field flxcs-own"><span><?php esc_html_e( 'API adresi', 'flx-chat' ); ?></span>
				<input type="url" name="<?php echo esc_attr( $n ); ?>[base_url]" value="<?php echo esc_attr( $c['base_url'] ); ?>" placeholder="https://wa.ornek.com">
			</label>
			<label class="flxcs-field flxcs-own"><span><?php esc_html_e( 'Instance adı', 'flx-chat' ); ?></span>
				<input type="text" name="<?php echo esc_attr( $n ); ?>[instance]" value="<?php echo esc_attr( $c['instance'] ); ?>">
			</label>
			<label class="flxcs-field flxcs-own flxcs-span2"><span><?php esc_html_e( 'API key (apikey)', 'flx-chat' ); ?></span>
				<input type="password" name="<?php echo esc_attr( $n ); ?>[api_key]" value="" autocomplete="new-password" placeholder="<?php echo $c['api_key'] ? esc_attr__( '•••••••• (kayıtlı; değiştirmek için yazın)', 'flx-chat' ) : ''; ?>">
			</label>
		</div>
		<?php if ( $saved ) : ?>
			<div class="flxcs-wh">
				<span><?php esc_html_e( 'Webhook adresi', 'flx-chat' ); ?></span>
				<code class="flxcs-wh-url"><?php echo esc_html( flxc_webhook_url( $c ) ); ?></code>
				<button type="button" class="flxcs-link flxcs-copy"><?php esc_html_e( 'Kopyala', 'flx-chat' ); ?></button>
				<label class="flxcs-check"><input type="checkbox" name="<?php echo esc_attr( $n ); ?>[new_secret]" value="1"> <?php esc_html_e( 'Gizli anahtarı yenile', 'flx-chat' ); ?></label>
			</div>
			<p class="flxcs-small">
				<?php
				if ( $last ) {
					/* translators: %s: time ago */
					printf( esc_html__( 'Son webhook: %s', 'flx-chat' ), esc_html( flx_time_ago( $last ) ) );
				} else {
					esc_html_e( 'Henüz webhook gelmedi.', 'flx-chat' );
				}
				?>
			</p>
			<div class="flxcs-actions">
				<button type="button" class="flxcs-btn" data-act="test"><?php esc_html_e( 'Bağlantıyı test et', 'flx-chat' ); ?></button>
				<button type="button" class="flxcs-btn flxcs-not-flxi" data-act="qr"><?php esc_html_e( 'QR ile bağla', 'flx-chat' ); ?></button>
				<?php if ( flxc_flxi_on() ) : ?>
					<a class="flxcs-link flxcs-flxi-only" href="<?php echo esc_url( flxc_flxi_connections_url() ); ?>"><?php esc_html_e( 'QR, durum ve hesaplar: İletişim › Bağlantılar', 'flx-chat' ); ?> →</a>
				<?php endif; ?>
				<button type="button" class="flxcs-btn flxcs-btn-primary" data-act="webhook"><?php esc_html_e( 'Webhook\'u kur', 'flx-chat' ); ?></button>
				<button type="button" class="flxcs-btn" data-act="import"><?php esc_html_e( 'Son sohbetleri içe aktar', 'flx-chat' ); ?></button>
			</div>
			<div class="flxcs-out" aria-live="polite"></div>
		<?php else : ?>
			<p class="flxcs-small"><?php esc_html_e( 'Kaydettikten sonra webhook adresi ve test düğmeleri görünür.', 'flx-chat' ); ?></p>
		<?php endif; ?>
	</div>
	<?php
}

function flxc_settings_tab_genel( $s ) {
	?>
	<section class="flxcs-card">
		<h2><?php esc_html_e( 'Görünüm', 'flx-chat' ); ?></h2>
		<div class="flxcs-grid">
			<label class="flxcs-field"><span><?php esc_html_e( 'Panel başlığı', 'flx-chat' ); ?></span>
				<input type="text" name="brand_name" value="<?php echo esc_attr( $s['brand_name'] ); ?>" placeholder="<?php echo esc_attr( get_bloginfo( 'name' ) ); ?>">
				<small><?php esc_html_e( 'Boş bırakılırsa site adı. {brand} değişkeni de bunu kullanır.', 'flx-chat' ); ?></small>
			</label>
			<label class="flxcs-field"><span><?php esc_html_e( 'Vurgu rengi', 'flx-chat' ); ?></span>
				<input type="color" name="accent" value="<?php echo esc_attr( $s['accent'] ); ?>">
			</label>
		</div>
	</section>
	<section class="flxcs-card">
		<h2><?php esc_html_e( 'Çalışma şekli', 'flx-chat' ); ?></h2>
		<label class="flxcs-check"><input type="checkbox" name="send_read" value="1" <?php checked( $s['send_read'] ); ?>> <?php esc_html_e( 'Sohbet açılınca karşı tarafa okundu bilgisi (mavi tik) gönder', 'flx-chat' ); ?></label>
		<label class="flxcs-check"><input type="checkbox" name="agent_signature" value="1" <?php checked( $s['agent_signature'] ); ?>> <?php esc_html_e( 'Mesajın başına temsilci adını ekle (*Ad:*)', 'flx-chat' ); ?></label>
		<label class="flxcs-check"><input type="checkbox" name="restrict_agents" value="1" <?php checked( $s['restrict_agents'] ); ?>> <?php esc_html_e( 'Temsilciler yalnız kendine atanan ve atanmamış sohbetleri görsün (yöneticiler hepsini görür)', 'flx-chat' ); ?></label>
		<label class="flxcs-check"><input type="checkbox" name="show_groups" value="1" <?php checked( $s['show_groups'] ); ?>> <?php esc_html_e( 'WhatsApp grup mesajlarını da göster', 'flx-chat' ); ?></label>
		<label class="flxcs-check"><input type="checkbox" name="ui_flux" value="1" <?php checked( $s['ui_flux'] ); ?> <?php disabled( ! file_exists( FLXC_DIR . 'assets/flux/flux-modules.js' ) ); ?>> <?php esc_html_e( 'Yeni arayüz (deneysel): Sohbetler sayfası masaüstü ve mobil uygulamayla aynı ekranla açılır', 'flx-chat' ); ?></label>
		<label class="flxcs-field flxcs-narrow"><span><?php esc_html_e( 'Canlı güncelleme sıklığı (sn)', 'flx-chat' ); ?></span>
			<input type="number" min="3" max="60" name="poll_interval" value="<?php echo (int) $s['poll_interval']; ?>">
			<small><?php esc_html_e( 'Açık sekmede bu aralıkla yeni mesaj kontrol edilir (önerilen 10). Sekme arka plandayken dakikada bir, hata olursa giderek seyrekleşir.', 'flx-chat' ); ?></small>
		</label>
	</section>
	<section class="flxcs-card">
		<h2><?php esc_html_e( 'Temsilciler', 'flx-chat' ); ?></h2>
		<p class="flxcs-desc">
			<?php
			printf(
				/* translators: %s: role name */
				esc_html__( 'Bir kullanıcıya %s rolü verin; yalnız sohbet ekranını görür, ayarlara giremez. Yöneticiler her zaman erişir.', 'flx-chat' ),
				'<strong>' . esc_html__( 'Çağrı Merkezi Temsilcisi', 'flx-chat' ) . '</strong>'
			);
			?>
		</p>
		<ul class="flxcs-agents">
			<?php foreach ( flxc_agents() as $a ) : ?>
				<li><?php echo esc_html( $a['name'] ); ?></li>
			<?php endforeach; ?>
		</ul>
		<a class="flxcs-link" href="<?php echo esc_url( admin_url( 'user-new.php' ) ); ?>"><?php esc_html_e( 'Yeni kullanıcı ekle', 'flx-chat' ); ?> →</a>
	</section>
	<section class="flxcs-card">
		<h2><?php esc_html_e( 'Veri', 'flx-chat' ); ?></h2>
		<label class="flxcs-check"><input type="checkbox" name="delete_data" value="1" <?php checked( $s['delete_data'] ); ?>> <?php esc_html_e( 'Eklenti silinirken tüm sohbetleri, mesajları ve medya dosyalarını da sil', 'flx-chat' ); ?></label>
		<p class="flxcs-small"><?php esc_html_e( 'Medya dosyaları wp-content/uploads/flx-chat klasöründe, dışarıdan erişime kapalı tutulur. Site taşınırken uploads ile birlikte gider.', 'flx-chat' ); ?></p>
	</section>
	<?php
}

function flxc_settings_tab_otomatik( $s ) {
	$days = array(
		1 => __( 'Pzt', 'flx-chat' ),
		2 => __( 'Sal', 'flx-chat' ),
		3 => __( 'Çar', 'flx-chat' ),
		4 => __( 'Per', 'flx-chat' ),
		5 => __( 'Cum', 'flx-chat' ),
		6 => __( 'Cmt', 'flx-chat' ),
		7 => __( 'Paz', 'flx-chat' ),
	);
	$sel  = array_map( 'intval', (array) $s['away_days'] );
	?>
	<section class="flxcs-card">
		<h2><?php esc_html_e( 'Karşılama mesajı', 'flx-chat' ); ?></h2>
		<label class="flxcs-check"><input type="checkbox" name="welcome_enabled" value="1" <?php checked( $s['welcome_enabled'] ); ?>> <?php esc_html_e( 'Bize ilk kez yazan kişiye otomatik gönder', 'flx-chat' ); ?></label>
		<label class="flxcs-field"><textarea name="welcome_text" rows="3"><?php echo esc_textarea( $s['welcome_text'] ); ?></textarea></label>
	</section>
	<section class="flxcs-card">
		<h2><?php esc_html_e( 'Mesai dışı yanıtı', 'flx-chat' ); ?></h2>
		<label class="flxcs-check"><input type="checkbox" name="away_enabled" value="1" <?php checked( $s['away_enabled'] ); ?>> <?php esc_html_e( 'Mesai saatleri dışında gelen mesajlara otomatik yanıt ver', 'flx-chat' ); ?></label>
		<div class="flxcs-days">
			<span><?php esc_html_e( 'Mesai günleri', 'flx-chat' ); ?></span>
			<?php foreach ( $days as $d => $l ) : ?>
				<label class="flxcs-day"><input type="checkbox" name="away_days[]" value="<?php echo (int) $d; ?>" <?php checked( in_array( $d, $sel, true ) ); ?>><span><?php echo esc_html( $l ); ?></span></label>
			<?php endforeach; ?>
		</div>
		<div class="flxcs-grid flxcs-grid-3">
			<label class="flxcs-field"><span><?php esc_html_e( 'Başlangıç', 'flx-chat' ); ?></span><input type="time" name="away_start" value="<?php echo esc_attr( $s['away_start'] ); ?>"></label>
			<label class="flxcs-field"><span><?php esc_html_e( 'Bitiş', 'flx-chat' ); ?></span><input type="time" name="away_end" value="<?php echo esc_attr( $s['away_end'] ); ?>"></label>
			<label class="flxcs-field"><span><?php esc_html_e( 'Aynı kişiye en fazla (saatte bir)', 'flx-chat' ); ?></span><input type="number" min="1" max="72" name="away_throttle" value="<?php echo (int) $s['away_throttle']; ?>"></label>
		</div>
		<label class="flxcs-field"><textarea name="away_text" rows="3"><?php echo esc_textarea( $s['away_text'] ); ?></textarea></label>
		<p class="flxcs-small">
			<?php
			/* translators: 1: timezone, 2: now open/closed */
			printf( esc_html__( 'Saat dilimi: %1$s. Şu an: %2$s.', 'flx-chat' ), esc_html( wp_timezone_string() ), flxc_is_open_hours() ? esc_html__( 'mesai içi', 'flx-chat' ) : esc_html__( 'mesai dışı', 'flx-chat' ) );
			?>
		</p>
		<p class="flxcs-small"><?php esc_html_e( 'Metinlerde {first_name}, {name}, {brand}, {site}, {date} değişkenleri kullanılabilir.', 'flx-chat' ); ?></p>
	</section>
	<?php
}

function flxc_settings_tab_etiketler( $s ) {
	?>
	<section class="flxcs-card">
		<h2><?php esc_html_e( 'Sohbet etiketleri', 'flx-chat' ); ?></h2>
		<p class="flxcs-desc"><?php esc_html_e( 'Sohbetleri etiketleyip listede filtreleyin (ör. Bağış, Şikâyet, Takip).', 'flx-chat' ); ?></p>
		<div id="flxcs-labels" class="flxcs-labels">
			<?php
			foreach ( (array) $s['labels'] as $i => $l ) {
				flxc_settings_label_row( $i, $l );
			}
			?>
		</div>
		<template id="flxcs-label-tpl"><?php flxc_settings_label_row( '__i__', array( 'id' => '', 'name' => '', 'color' => '#25d366' ) ); ?></template>
		<button type="button" class="flxcs-btn" id="flxcs-add-label">+ <?php esc_html_e( 'Etiket ekle', 'flx-chat' ); ?></button>
	</section>
	<?php
}

function flxc_settings_label_row( $i, $l ) {
	$n = 'labels[' . $i . ']';
	?>
	<div class="flxcs-label">
		<input type="hidden" name="<?php echo esc_attr( $n ); ?>[id]" value="<?php echo esc_attr( $l['id'] ); ?>">
		<input type="color" name="<?php echo esc_attr( $n ); ?>[color]" value="<?php echo esc_attr( $l['color'] ); ?>">
		<input type="text" name="<?php echo esc_attr( $n ); ?>[name]" value="<?php echo esc_attr( $l['name'] ); ?>" placeholder="<?php esc_attr_e( 'Etiket adı', 'flx-chat' ); ?>">
		<button type="button" class="flxcs-link flxcs-del-label" aria-label="<?php esc_attr_e( 'Sil', 'flx-chat' ); ?>">✕</button>
	</div>
	<?php
}

function flxc_settings_tab_kayitlar( $s ) {
	$flxb = flxc_flxb_on();
	$flxs = flxc_flxs_on();
	?>
	<section class="flxcs-card">
		<h2><?php esc_html_e( 'Sohbetten kayıt oluşturma', 'flx-chat' ); ?></h2>
		<p class="flxcs-desc"><?php esc_html_e( 'Sohbet başlığındaki düğmelerle bağış kaydı (Flux Donate) ve sertifika (Flux Certificates) oluşturulur, gelen dekont kayıtla eşleştirilir. Eklenti kurulu değilse ilgili düğme görünmez.', 'flx-chat' ); ?></p>
		<ul class="flxcs-agents">
			<li><?php echo $flxb ? '✓ ' : '✕ '; ?>Flux Donate</li>
			<li><?php echo $flxs ? '✓ ' : '✕ '; ?>Flux Certificates</li>
		</ul>
		<label class="flxcs-check"><input type="checkbox" name="agents_donations" value="1" <?php checked( $s['agents_donations'] ); ?>> <?php esc_html_e( 'Temsilciler bağış kaydı oluşturabilsin ve dekont eşleştirebilsin', 'flx-chat' ); ?></label>
		<label class="flxcs-check"><input type="checkbox" name="agents_certificates" value="1" <?php checked( $s['agents_certificates'] ); ?>> <?php esc_html_e( 'Temsilciler sertifika oluşturup gönderebilsin', 'flx-chat' ); ?></label>
		<label class="flxcs-check"><input type="checkbox" name="chips_bar" value="1" <?php checked( $s['chips_bar'] ); ?>> <?php esc_html_e( 'Mesaj kutusunun üstünde hazır cevap baloncuklarını göster', 'flx-chat' ); ?></label>
	</section>
	<section class="flxcs-card">
		<h2><?php esc_html_e( 'Bağış mesajları', 'flx-chat' ); ?></h2>
		<p class="flxcs-desc"><?php esc_html_e( 'Kayıt oluşturulunca kişiye gönderilecek hazır metinler (gönderimden önce düzenlenebilir). Boş bırakılırsa varsayılan metin kullanılır.', 'flx-chat' ); ?></p>
		<label class="flxcs-field"><span><?php esc_html_e( 'Ödeme bekleniyor (havale sözü)', 'flx-chat' ); ?></span><textarea name="msg_donation_pending" rows="5" placeholder="<?php echo esc_attr( flxc_record_text( 'msg_donation_pending' ) ); ?>"><?php echo esc_textarea( $s['msg_donation_pending'] ); ?></textarea></label>
		<label class="flxcs-field"><span><?php esc_html_e( 'Bağış alındı', 'flx-chat' ); ?></span><textarea name="msg_donation_done" rows="4" placeholder="<?php echo esc_attr( flxc_record_text( 'msg_donation_done' ) ); ?>"><?php echo esc_textarea( $s['msg_donation_done'] ); ?></textarea></label>
		<label class="flxcs-field"><span><?php esc_html_e( 'Dekont onaylandı', 'flx-chat' ); ?></span><textarea name="msg_receipt" rows="3" placeholder="<?php echo esc_attr( flxc_record_text( 'msg_receipt' ) ); ?>"><?php echo esc_textarea( $s['msg_receipt'] ); ?></textarea></label>
		<label class="flxcs-field"><span><?php esc_html_e( 'Banka bilgisi ({bank})', 'flx-chat' ); ?></span><textarea name="bank_text" rows="3" placeholder="<?php echo esc_attr( flxc_bank_text() ? flxc_bank_text() : __( 'Alıcı adı, banka, IBAN', 'flx-chat' ) ); ?>"><?php echo esc_textarea( $s['bank_text'] ); ?></textarea><small><?php esc_html_e( 'Boşsa WooCommerce › Ödemeler › Havale / EFT ayarındaki hesap kullanılır.', 'flx-chat' ); ?></small></label>
		<p class="flxcs-small"><?php esc_html_e( 'Değişkenler: {first_name}, {name}, {donation}, {amount}, {quantity}, {bank}, {certificate_link}, {agent}, {brand}, {date}.', 'flx-chat' ); ?></p>
	</section>
	<?php
}

function flxc_settings_tab_ai( $s ) {
	$list  = flxc_ai_providers();
	$usage = get_option( 'flxc_ai_usage', array() );
	$types = function_exists( 'flxb_get_types' ) ? (array) flxb_get_types( array( 'status' => 'open', 'progress' => false ) ) : array();
	$sel   = array_map( 'intval', (array) $s['bot_types'] );
	?>
	<section class="flxcs-card">
		<h2><?php esc_html_e( 'Yapay zekâ bağlantısı', 'flx-chat' ); ?></h2>
		<?php if ( flxc_ai_hub() ) : ?>
		<p class="flxcs-desc"><?php esc_html_e( 'Anahtarlar, prompt önbelleği, harcama sınırı ve kullanım kayıtları Flux AI eklentisinden gelir; Çağrı Merkezi\'nin tüm yapay zekâ istekleri oradan geçer. Model seçimleri burada kalır; alanlar boşsa Flux AI\'de bu eklentiye seçilen model (ya da varsayılan) kullanılır.', 'flx-chat' ); ?> <a href="<?php echo esc_url( admin_url( 'admin.php?page=flx-ai-settings' ) ); ?>"><?php esc_html_e( 'Flux AI ayarlarını aç', 'flx-chat' ); ?></a></p>
		<?php else : ?>
		<div class="flxcs-note flxcs-note-warn"><?php esc_html_e( 'Yapay zekâ için Flux AI eklentisi gerekli. Kurup etkinleştirin ve Flux AI › Ayarlar\'a OpenRouter ve/veya Anthropic anahtarını girin. O zamana kadar öneri, asistan, dekont okuma, sesli mesaj ve özet kapalıdır.', 'flx-chat' ); ?></div>
		<?php endif; ?>
		<div class="flxcs-grid">
			<label class="flxcs-field"><span><?php esc_html_e( 'Yaratıcılık (0–1)', 'flx-chat' ); ?></span>
				<input type="number" name="ai_temperature" min="0" max="1" step="0.1" value="<?php echo esc_attr( (string) $s['ai_temperature'] ); ?>">
				<small><?php esc_html_e( 'Düşük değer daha tutarlı, yüksek değer daha serbest cevap verir. Önerilen 0,4.', 'flx-chat' ); ?></small>
			</label>
			<div class="flxcs-field flxcs-span2"><span><?php esc_html_e( 'Model (öneriler için)', 'flx-chat' ); ?></span>
				<div class="flxcs-mp" data-name="ai_model" data-value="<?php echo esc_attr( $s['ai_model'] ); ?>" data-empty="<?php esc_attr_e( 'Model seçin', 'flx-chat' ); ?>"><input type="hidden" name="ai_model" value="<?php echo esc_attr( $s['ai_model'] ); ?>"></div>
			</div>
			<div class="flxcs-field flxcs-span2"><span><?php esc_html_e( 'Asistan modeli (isteğe bağlı)', 'flx-chat' ); ?></span>
				<div class="flxcs-mp" data-name="ai_model_bot" data-tools="1" data-def="<?php echo esc_attr( $s['ai_model'] ); ?>" data-value="<?php echo esc_attr( $s['ai_model_bot'] ); ?>" data-empty="<?php esc_attr_e( 'Yukarıdaki model', 'flx-chat' ); ?>"><input type="hidden" name="ai_model_bot" value="<?php echo esc_attr( $s['ai_model_bot'] ); ?>"></div>
				<small><?php esc_html_e( 'Yalnız araç (tools) destekleyen modeller listelenir.', 'flx-chat' ); ?></small>
			</div>
			<div class="flxcs-field flxcs-span2"><span><?php esc_html_e( 'Dekont okuma modeli (isteğe bağlı)', 'flx-chat' ); ?></span>
				<div class="flxcs-mp" data-name="ai_model_vision" data-vision="1" data-def="<?php echo esc_attr( '' !== $s['ai_model_bot'] ? $s['ai_model_bot'] : $s['ai_model'] ); ?>" data-value="<?php echo esc_attr( $s['ai_model_vision'] ); ?>" data-empty="<?php esc_attr_e( 'Asistan modeli', 'flx-chat' ); ?>"><input type="hidden" name="ai_model_vision" value="<?php echo esc_attr( $s['ai_model_vision'] ); ?>"></div>
				<small><?php esc_html_e( 'Seçilen model görsel okuyamıyorsa görsel okuyan ucuz bir modele kendiliğinden geçilir.', 'flx-chat' ); ?></small>
			</div>
		</div>
		<p class="flxcs-row">
			<button type="button" class="flxcs-btn" id="flxcs-ai-test"><?php esc_html_e( 'Bağlantıyı test et', 'flx-chat' ); ?></button>
			<span class="flxcs-out" id="flxcs-ai-out"></span>
		</p>
		<?php if ( ! empty( $usage['month'] ) ) : ?>
			<p class="flxcs-small">
				<?php
				/* translators: 1: month, 2: requests, 3: tokens, 4: cost */
				printf( esc_html__( 'Bu ay (%1$s): %2$s istek, %3$s token, yaklaşık $%4$s.', 'flx-chat' ), esc_html( $usage['month'] ), esc_html( number_format_i18n( (int) $usage['calls'] ) ), esc_html( number_format_i18n( (int) $usage['tokens'] ) ), esc_html( number_format_i18n( (float) $usage['cost'], 4 ) ) );
				?>
			</p>
		<?php endif; ?>
	</section>

	<section class="flxcs-card">
		<h2><?php esc_html_e( 'Cevap önerisi', 'flx-chat' ); ?></h2>
		<p class="flxcs-desc"><?php esc_html_e( 'Mesaj kutusunun üstünde, konuşmaya göre sıradaki mesaj önerilir. Temsilci "Gönder" der ya da "Düzenle" ile kutuya alıp değiştirir. Öneri asla kendiliğinden gönderilmez.', 'flx-chat' ); ?></p>
		<label class="flxcs-check"><input type="checkbox" name="ai_auto_suggest" value="1" <?php checked( $s['ai_auto_suggest'] ); ?>> <?php esc_html_e( 'Son mesaj kişiden geldiyse öneri kendiliğinden hazırlansın (kapalıysa ✨ düğmesiyle istenir)', 'flx-chat' ); ?></label>
		<label class="flxcs-field"><span><?php esc_html_e( 'Kurum bilgisi ve üslup', 'flx-chat' ); ?></span>
			<textarea name="ai_instructions" rows="6" placeholder="<?php esc_attr_e( 'Ör. Derneğimiz İstanbul\'da fidan dikimi yapar. Dikimler her yıl Kasım–Mart arasında yapılır. Sertifika bağıştan sonra WhatsApp\'tan gönderilir. Samimi ama resmi bir dil kullan.', 'flx-chat' ); ?>"><?php echo esc_textarea( $s['ai_instructions'] ); ?></textarea>
			<small><?php esc_html_e( 'Öneri ve asistan bu bilgileri kullanır. Bağış türleri, fiyatlar, banka bilgisi ve hazır cevaplar ayrıca otomatik eklenir.', 'flx-chat' ); ?></small>
		</label>
		<?php if ( count( $list ) > 1 ) : ?>
			<label class="flxcs-field flxcs-narrow"><span><?php esc_html_e( 'Öneri sağlayıcısı', 'flx-chat' ); ?></span>
				<select name="ai_provider">
					<?php foreach ( $list as $id => $p ) : ?>
						<option value="<?php echo esc_attr( $id ); ?>" <?php selected( $s['ai_provider'], $id ); ?>><?php echo esc_html( $p['label'] ?? $id ); ?></option>
					<?php endforeach; ?>
				</select>
			</label>
		<?php endif; ?>
	</section>

	<section class="flxcs-card">
		<h2><?php esc_html_e( 'Bağış asistanı (otomatik cevap)', 'flx-chat' ); ?></h2>
		<p class="flxcs-desc"><?php esc_html_e( 'Açıkken asistan gelen mesajlara kendisi cevap verir: karşılar, hitap için adını sorar, bağış seçeneklerini ve fiyatları gönderir, isteğe bağlı e-posta ve "kimin adına" bilgilerini alır, her değişiklikte özet gösterip onay ister. Onaydan sonra Flux Donate\'ta "ödeme bekleniyor" kaydı açar ve bağış linki ile alıcı adı, IBAN, açıklamayı ayrı mesajlar hâlinde gönderir. Konu bağış dışına çıkarsa sohbeti ekibe devreder.', 'flx-chat' ); ?></p>
		<?php if ( ! flxc_flxb_on() ) : ?>
			<div class="flxcs-note flxcs-note-warn"><?php esc_html_e( 'Flux Donate eklentisi etkin değil; asistan bağış kaydı açamaz.', 'flx-chat' ); ?></div>
		<?php endif; ?>
		<p><a class="flxcs-btn" href="<?php echo esc_url( admin_url( 'admin.php?page=flx-chat-asistan' ) ); ?>"><?php esc_html_e( 'Talimat, belgeler, AI sohbetleri ve test sohbeti', 'flx-chat' ); ?> →</a></p>
		<label class="flxcs-check"><input type="checkbox" name="bot_enabled" value="1" <?php checked( $s['bot_enabled'] ); ?>> <strong><?php esc_html_e( 'Bağış asistanını aç (tümünü kapatmak için işareti kaldırın)', 'flx-chat' ); ?></strong></label>
		<div class="flxcs-field"><span><?php esc_html_e( 'Hangi sohbetlerde cevap versin', 'flx-chat' ); ?></span>
			<?php
			foreach ( array(
				'all'        => __( 'Tüm sohbetlerde (önerilen)', 'flx-chat' ),
				'unassigned' => __( 'Temsilciye atanmamış sohbetlerde', 'flx-chat' ),
				'away'       => __( 'Yalnız mesai saatleri dışında', 'flx-chat' ),
			) as $k => $l ) :
				?>
				<label class="flxcs-check"><input type="radio" name="bot_scope" value="<?php echo esc_attr( $k ); ?>" <?php checked( $s['bot_scope'], $k ); ?>> <?php echo esc_html( $l ); ?></label>
			<?php endforeach; ?>
			<small><?php esc_html_e( 'Bir temsilci sohbete yazdığında (panelden ya da telefondan) asistan o sohbette aşağıdaki süre kadar susar. Sohbet başlığındaki asistan düğmesiyle sohbet bazında açılıp kapatılır.', 'flx-chat' ); ?></small>
		</div>
		<label class="flxcs-check"><input type="checkbox" name="bot_test_only" value="1" <?php checked( $s['bot_test_only'] ); ?>> <?php esc_html_e( 'Test modu: yalnız aşağıdaki numaralarda cevap ver', 'flx-chat' ); ?></label>
		<label class="flxcs-field"><span><?php esc_html_e( 'Test numaraları', 'flx-chat' ); ?></span><textarea name="bot_test_numbers" rows="2" placeholder="905321234567, 905551112233"><?php echo esc_textarea( $s['bot_test_numbers'] ); ?></textarea><small><?php esc_html_e( 'Virgül ya da satır ile ayırın. Test modu kapalıyken kullanılmaz.', 'flx-chat' ); ?></small></label>
		<div class="flxcs-grid flxcs-grid-3">
			<label class="flxcs-field"><span><?php esc_html_e( 'Temsilci yazınca susma (saat)', 'flx-chat' ); ?></span><input type="number" min="1" max="720" name="bot_pause_hours" value="<?php echo (int) $s['bot_pause_hours']; ?>"></label>
			<label class="flxcs-field"><span><?php esc_html_e( 'Sohbet başına günlük cevap sınırı', 'flx-chat' ); ?></span><input type="number" min="5" max="500" name="bot_daily_limit" value="<?php echo (int) $s['bot_daily_limit']; ?>"></label>
			<label class="flxcs-field"><span><?php esc_html_e( 'Fiyat listesindeki adetler', 'flx-chat' ); ?></span><input type="text" name="bot_presets" value="<?php echo esc_attr( $s['bot_presets'] ); ?>" placeholder="1, 3, 5, 10"></label>
			<label class="flxcs-field"><span><?php esc_html_e( 'Konuşma geçmişi (saat)', 'flx-chat' ); ?></span><input type="number" min="1" max="168" name="bot_context_hours" value="<?php echo (int) $s['bot_context_hours']; ?>"><small><?php esc_html_e( 'Asistan cevaptan önce bu süredeki tüm mesajları okur.', 'flx-chat' ); ?></small></label>
		</div>
		<?php if ( $types ) : ?>
			<div class="flxcs-field"><span><?php esc_html_e( 'Asistanın sunduğu bağış türleri', 'flx-chat' ); ?></span>
				<?php foreach ( $types as $t ) : ?>
					<label class="flxcs-check"><input type="checkbox" name="bot_types[]" value="<?php echo (int) $t['id']; ?>" <?php checked( in_array( (int) $t['id'], $sel, true ) ); ?>> <?php echo esc_html( $t['title'] ); ?></label>
				<?php endforeach; ?>
				<small><?php esc_html_e( 'Hiçbiri seçilmezse açık olan tüm türler sunulur. Yeni tür Flux Donate\'ta açıldığında asistan onu da bilir.', 'flx-chat' ); ?></small>
			</div>
		<?php endif; ?>
		<label class="flxcs-check"><input type="checkbox" name="bot_receipt_ai" value="1" <?php checked( $s['bot_receipt_ai'] ); ?>> <?php esc_html_e( 'Dekont ön kontrolü: kişi dekont gönderince yapay zekâ tutarı, alıcıyı ve IBAN\'ı bağışla karşılaştırır, sonucu kişiye söyler, dosyayı bağış kaydına ekler ve son onay için sohbeti ekibe devreder (ödendi işaretlemez)', 'flx-chat' ); ?></label>
		<label class="flxcs-field"><span><?php esc_html_e( 'Fiyat listesinin son satırı', 'flx-chat' ); ?></span><input type="text" name="bot_price_footer" value="<?php echo esc_attr( $s['bot_price_footer'] ); ?>" placeholder="<?php esc_attr_e( 'Hangisini ve kaç adet istediğinizi yazmanız yeterli.', 'flx-chat' ); ?>"></label>
		<h3 class="flxcs-h3"><?php esc_html_e( 'Ödeme bilgileri', 'flx-chat' ); ?></h3>
		<p class="description"><?php esc_html_e( 'Alıcı adı ve IBAN boşsa WooCommerce › Ödemeler › Havale / EFT ayarındakiler kullanılır (siparişlerde görünen hesap). Yalnız asistan için farklı hesap istiyorsanız doldurun.', 'flx-chat' ); ?></p>
		<div class="flxcs-grid">
			<label class="flxcs-field"><span><?php esc_html_e( 'Alıcı adı', 'flx-chat' ); ?></span><input type="text" name="bank_holder" value="<?php echo esc_attr( $s['bank_holder'] ); ?>" placeholder="<?php echo esc_attr( flxc_bot_bank_account()['holder'] ); ?>"></label>
			<label class="flxcs-field"><span>IBAN</span><input type="text" name="bank_iban" value="<?php echo esc_attr( $s['bank_iban'] ); ?>" placeholder="<?php echo esc_attr( flxc_bot_bank_account()['iban'] ? flxc_bot_bank_account()['iban'] : 'TR00 0000 0000 0000 0000 0000 00' ); ?>"></label>
			<label class="flxcs-field"><span><?php esc_html_e( 'Havale açıklaması', 'flx-chat' ); ?></span><input type="text" name="bank_desc" value="<?php echo esc_attr( $s['bank_desc'] ); ?>" placeholder="{type} {name}"><small><?php esc_html_e( 'Değişkenler: {type}, {name}, {amount}, {quantity}', 'flx-chat' ); ?></small></label>
			<label class="flxcs-field"><span><?php esc_html_e( 'Bağış sayfası adresi', 'flx-chat' ); ?></span><input type="url" name="donate_url" value="<?php echo esc_attr( $s['donate_url'] ); ?>" placeholder="<?php echo esc_attr( flxc_donate_url() ? flxc_donate_url() : home_url( '/bagis/' ) ); ?>"><small><?php esc_html_e( 'Linke seçilen tür ve adet eklenir (?flxb_opt=…&flxb_qty=…).', 'flx-chat' ); ?></small></label>
		</div>
		<label class="flxcs-field"><span><?php esc_html_e( 'Ödeme bilgilerinden sonraki mesaj', 'flx-chat' ); ?></span><input type="text" name="bot_after_payment" value="<?php echo esc_attr( $s['bot_after_payment'] ); ?>" placeholder="<?php esc_attr_e( 'Ödemeyi yaptıktan sonra dekontu buradan gönderebilirsiniz. Desteğiniz için çok teşekkür ederiz 🤲', 'flx-chat' ); ?>"></label>
		<label class="flxcs-field"><span><?php esc_html_e( 'Bağış iptali onay sorusu', 'flx-chat' ); ?></span><textarea name="bot_cancel_text" rows="3" placeholder="<?php echo esc_attr( flxc_bot_cancel_ask( '{bagis}', '{tutar}' ) ); ?>"><?php echo esc_textarea( $s['bot_cancel_text'] ); ?></textarea><small><?php esc_html_e( 'Asistan yalnız ödenmemiş bağışları, kişinin onayıyla iptal eder. Boşsa varsayılan metin. Değişkenler: {bagis}, {tutar}.', 'flx-chat' ); ?></small></label>
		<label class="flxcs-field"><span><?php esc_html_e( 'Günlük sınır dolunca gönderilen mesaj', 'flx-chat' ); ?></span><input type="text" name="bot_limit_text" value="<?php echo esc_attr( $s['bot_limit_text'] ); ?>" placeholder="<?php echo esc_attr( __( 'Mesajınızı aldık, size en kısa sürede dönüş yapacağız 🌱', 'flx-chat' ) ); ?>"><small><?php esc_html_e( 'Sohbet o günkü cevap sınırına ulaşınca bir kez gider, sohbet ekibe devredilir. Sınır sohbetin ⋮ menüsü › Asistan kullanımı ekranından sıfırlanır.', 'flx-chat' ); ?></small></label>
		<label class="flxcs-field"><span><?php esc_html_e( 'Ekibe devrederken gönderilen mesaj', 'flx-chat' ); ?></span><input type="text" name="bot_handoff_text" value="<?php echo esc_attr( $s['bot_handoff_text'] ); ?>" placeholder="<?php echo esc_attr( __( 'Sizi ekibimizden bir arkadaşımıza aktarıyorum, en kısa sürede buradan dönüş yapacak 🌱', 'flx-chat' ) ); ?>"></label>
		<label class="flxcs-field"><span><?php esc_html_e( 'Asistan cevap veremezse giden mesaj', 'flx-chat' ); ?></span><input type="text" name="bot_fallback_text" value="<?php echo esc_attr( $s['bot_fallback_text'] ); ?>" placeholder="<?php echo esc_attr( __( 'Beklettiğimiz için üzgünüz. En kısa sürede size dönüş yapacağız. Anlayışınız için teşekkürler.', 'flx-chat' ) ); ?>"><small><?php esc_html_e( 'Yapay zekâ hata verir, süre aşılır ya da yoğunlukta sıra çok uzarsa kişiye bu mesaj gider ve sohbet ekibe devredilir; kişi hiçbir durumda cevapsız kalmaz.', 'flx-chat' ); ?></small></label>
		<label class="flxcs-field"><span><?php esc_html_e( 'Bekleme mesajından önce en az (sn)', 'flx-chat' ); ?></span><input type="number" min="0" max="45" name="bot_fallback_wait" value="<?php echo (int) $s['bot_fallback_wait']; ?>"><small><?php esc_html_e( 'Kişinin mesajından bu kadar saniye geçmeden bekleme mesajı gitmez; bu arada asistan yeniden dener.', 'flx-chat' ); ?></small></label>
		<label class="flxcs-field"><span><?php esc_html_e( 'Bir cevabın en uzun süresi (sn)', 'flx-chat' ); ?></span><input type="number" min="20" max="110" name="bot_time_budget" value="<?php echo (int) $s['bot_time_budget']; ?>"><small><?php esc_html_e( 'Hostinger istekleri ~60 sn\'de kesebilir; 50 önerilir.', 'flx-chat' ); ?></small></label>
		<label class="flxcs-field"><span><?php esc_html_e( 'Devirde bildirim gidecek numaralar', 'flx-chat' ); ?></span><textarea name="bot_notify_numbers" rows="2" placeholder="905321234567, 905551112233"><?php echo esc_textarea( $s['bot_notify_numbers'] ); ?></textarea><small><?php esc_html_e( 'Asistan bir sohbeti ekibe devredince bu numaralara aynı hattan WhatsApp mesajı gider: kişinin adı, telefonu, nedeni, son mesajları ve panel linki. Virgül ya da satır ile ayırın. Bu numaralarla asistan konuşmaz.', 'flx-chat' ); ?></small></label>
		<label class="flxcs-field"><span><?php esc_html_e( 'Temsilci isteği ifadeleri', 'flx-chat' ); ?></span><textarea name="bot_handoff_words" rows="2" placeholder="<?php echo esc_attr( implode( ', ', flxc_bot_handoff_words_default() ) ); ?>"><?php echo esc_textarea( $s['bot_handoff_words'] ); ?></textarea><small><?php esc_html_e( 'Kişi bunlardan birini yazınca asistan beklemeden yukarıdaki devir mesajını gönderir ve o sohbette kapanır. Boş bırakılırsa örnekteki liste kullanılır.', 'flx-chat' ); ?></small></label>
		<label class="flxcs-field"><span><?php esc_html_e( 'Asistana ek talimatlar', 'flx-chat' ); ?></span><textarea name="bot_instructions" rows="4" placeholder="<?php esc_attr_e( 'Ör. Fidanların dikileceği yer: Kocaeli. Sertifikalar bağış onaylanınca gönderilir.', 'flx-chat' ); ?>"><?php echo esc_textarea( $s['bot_instructions'] ); ?></textarea></label>
		<label class="flxcs-field"><span><?php esc_html_e( 'Kişi mesaj istemeyince gönderilen son mesaj', 'flx-chat' ); ?></span><input type="text" name="bot_optout_text" value="<?php echo esc_attr( $s['bot_optout_text'] ); ?>" placeholder="<?php echo esc_attr( __( 'Anlaşıldı, bundan sonra size bizden mesaj gelmeyecek. İyi günler dileriz.', 'flx-chat' ) ); ?>"><small><?php esc_html_e( 'Kişi "bana mesaj atmayın" gibi bir şey yazınca asistan bu mesajı gönderir ve kişiyi kara listeye alır. Kara listedeki kişiye WhatsApp, e-posta ve SMS gitmez; listeyi Sohbetler › ☰ › Kara liste\'den yönetirsiniz.', 'flx-chat' ); ?></small></label>

		<h3 class="flxcs-h3"><?php esc_html_e( 'Kişi hafızası ve arka plan işleri', 'flx-chat' ); ?></h3>
		<label class="flxcs-check"><input type="checkbox" name="mem_enabled" value="1" <?php checked( $s['mem_enabled'] ); ?>> <?php esc_html_e( 'Kişi hafızası: asistan kişinin kalıcı bilgilerini (hitap, yakınlarının adı, numaralar, tercihler) hatırlar; süresi dolan sohbetler özetlenir', 'flx-chat' ); ?></label>
		<div class="flxcs-grid flxcs-grid-3">
			<label class="flxcs-field"><span><?php esc_html_e( 'Sohbet kaç saat sessiz kalınca özetlensin', 'flx-chat' ); ?></span><input type="number" min="1" max="168" name="mem_after_hours" value="<?php echo (int) $s['mem_after_hours']; ?>"></label>
			<label class="flxcs-field"><span><?php esc_html_e( 'Aynı anda en fazla iş', 'flx-chat' ); ?></span><input type="number" min="1" max="30" name="workers_max" value="<?php echo (int) $s['workers_max']; ?>"><small><?php esc_html_e( 'Aynı anda cevap yazılabilecek kişi sayısı. Asistan cevapları her zaman önce gelir; cevap yazılan ya da sırada bekleyen kişi varken özet başlamaz. Her iş bir PHP işçisi kullanır: paylaşımlı hostingde sitelerin toplam işçi sınırının yarısını geçmeyin (ör. 60 işçide 20-30).', 'flx-chat' ); ?></small></label>
			<label class="flxcs-field"><span><?php esc_html_e( 'Yalnız cevaplara ayrılan işçi', 'flx-chat' ); ?></span><input type="number" min="0" max="30" name="workers_reserved" value="<?php echo (int) $s['workers_reserved']; ?>"><small><?php esc_html_e( 'Bu kadar işçi hiçbir zaman özet ya da hatırlatıcı almaz.', 'flx-chat' ); ?></small></label>
			<label class="flxcs-field"><span><?php esc_html_e( 'Özet saatleri', 'flx-chat' ); ?></span><span class="flxcs-inline"><input type="time" name="mem_window_from" value="<?php echo esc_attr( $s['mem_window_from'] ); ?>"> – <input type="time" name="mem_window_to" value="<?php echo esc_attr( $s['mem_window_to'] ); ?>"></span><small><?php esc_html_e( 'Özetler yalnız bu saatler arasında yapılır (elle "Şimdi özetle" hariç). İkisi aynıysa her saat.', 'flx-chat' ); ?></small></label>
		</div>

		<h3 class="flxcs-h3"><?php esc_html_e( 'Sesli mesaj', 'flx-chat' ); ?></h3>
		<label class="flxcs-check"><input type="checkbox" name="bot_voice" value="1" <?php checked( $s['bot_voice'] ); ?>> <?php esc_html_e( 'Kişi sesli mesaj gönderirse asistan sesi metne çevirip cevap versin (metin panelde sistem satırı olarak da görünür)', 'flx-chat' ); ?></label>
		<div class="flxcs-grid flxcs-grid-3">
			<label class="flxcs-field"><span><?php esc_html_e( 'En uzun ses (sn)', 'flx-chat' ); ?></span><input type="number" min="10" max="300" name="bot_voice_max" value="<?php echo (int) $s['bot_voice_max']; ?>"><small><?php esc_html_e( 'Daha uzun seslerde asistan "ses çok uzun olduğu için dinleyemedik" der.', 'flx-chat' ); ?></small></label>
			<div class="flxcs-field flxcs-span2"><span><?php esc_html_e( 'Sesli mesaj modeli (isteğe bağlı)', 'flx-chat' ); ?></span>
				<div class="flxcs-mp" data-name="ai_model_audio" data-value="<?php echo esc_attr( $s['ai_model_audio'] ); ?>" data-empty="<?php esc_attr_e( 'Ses okuyan ucuz model (Gemini Flash Lite)', 'flx-chat' ); ?>"><input type="hidden" name="ai_model_audio" value="<?php echo esc_attr( $s['ai_model_audio'] ); ?>"></div>
				<small><?php esc_html_e( 'Ses okuyan bir OpenRouter modeli olmalı (Claude ses okumaz). OpenRouter anahtarı gerekir.', 'flx-chat' ); ?></small>
			</div>
		</div>
		<h3 class="flxcs-h3"><?php esc_html_e( 'Görseller', 'flx-chat' ); ?></h3>
		<label class="flxcs-check"><input type="checkbox" name="bot_widgets" value="1" <?php checked( $s['bot_widgets'] ); ?>> <?php esc_html_e( 'Gerekince asistan görsel (tablo, rapor, şema) çizsin; WhatsApp\'a resim olarak gider, metin ayrıca gider', 'flx-chat' ); ?></label>
		<p class="flxcs-help"><?php echo esc_html( function_exists( 'flxai_render_widget' ) ? ( flxc_widgets_on() || ! (int) $s['bot_widgets'] ? __( 'Flux AI görsel çeviricisi kullanılır (Flux AI › Ayarlar › Görseller).', 'flx-chat' ) : __( 'Sunucuda görsel çevirici yok: Flux AI › Ayarlar › Görseller\'e Gotenberg adresi girin ya da Imagick (SVG) kurulsun. O zamana kadar asistan görsel çizmez.', 'flx-chat' ) ) : __( 'Flux AI 0.2.0 ya da sonrası kurulunca çalışır.', 'flx-chat' ) ); ?></p>
		<h3 class="flxcs-h3"><?php esc_html_e( 'Başkası adına bağış', 'flx-chat' ); ?></h3>
		<label class="flxcs-check"><input type="checkbox" name="benef_cert_notify" value="1" <?php checked( $s['benef_cert_notify'] ); ?>> <?php esc_html_e( 'Bağış başkası adınaysa ve o kişinin numarası verildiyse (kişi kartı ya da yazılı numara), ödeme onaylanınca sertifika o numaraya da WhatsApp\'tan gitsin', 'flx-chat' ); ?></label>
		<label class="flxcs-field"><span><?php esc_html_e( 'Sertifikadan önce giden mesaj', 'flx-chat' ); ?></span><input type="text" name="benef_cert_text" value="<?php echo esc_attr( $s['benef_cert_text'] ); ?>" placeholder="<?php echo esc_attr( flxc_benef_cert_default_text() ); ?>"><small><?php esc_html_e( 'Ardından Flux Certificates\'daki WhatsApp sertifika mesajı gider. Değişkenler: {ad}, {bagisci}, {bagis}, {brand}.', 'flx-chat' ); ?></small></label>
		<h3 class="flxcs-h3"><?php esc_html_e( 'Hatırlatıcılar', 'flx-chat' ); ?></h3>
		<label class="flxcs-check"><input type="checkbox" name="rem_enabled" value="1" <?php checked( $s['rem_enabled'] ); ?>> <?php esc_html_e( 'Kişi isterse asistan hatırlatıcı kurar (ör. "her ay fidan dikimini hatırlat", "her ay son projelerinizi gönderin", "her ay benden bir fidan iste"); zamanı gelince asistan mesajı yazıp gönderir', 'flx-chat' ); ?></label>
		<div class="flxcs-grid flxcs-grid-3">
			<label class="flxcs-field"><span><?php esc_html_e( 'Saat söylenmezse', 'flx-chat' ); ?></span><input type="time" name="rem_time" value="<?php echo esc_attr( $s['rem_time'] ); ?>"></label>
			<label class="flxcs-field"><span><?php esc_html_e( 'Kişi başına en fazla', 'flx-chat' ); ?></span><input type="number" min="1" max="50" name="rem_max" value="<?php echo (int) $s['rem_max']; ?>"></label>
		</div>
		<h3 class="flxcs-h3"><?php esc_html_e( 'Personel (Flux Roles)', 'flx-chat' ); ?></h3>
		<?php if ( ! function_exists( 'flxr_phone_identity' ) ) : ?>
			<p class="flxcs-help"><?php esc_html_e( 'Flux Roles 1.1.0 kurulunca çalışır: numarası Profil › WhatsApp numarası alanına yazılmış kullanıcılar asistanda yetkilerine göre ek araçlar kullanır.', 'flx-chat' ); ?></p>
		<?php endif; ?>
		<label class="flxcs-check"><input type="checkbox" name="staff_on" value="1" <?php checked( $s['staff_on'] ); ?>> <?php esc_html_e( 'Personel numarasından yazana asistan yetkisine göre ek araçlar açsın (başkası adına bağış ve ödeme linki, bağışçılarım, genel rapor)', 'flx-chat' ); ?></label>
		<label class="flxcs-check"><input type="checkbox" name="ref_notify" value="1" <?php checked( $s['ref_notify'] ); ?>> <?php esc_html_e( 'Referansı olduğu bağış ödenince ya da iptal olunca personele WhatsApp bildirimi gitsin', 'flx-chat' ); ?></label>
		<label class="flxcs-field"><span><?php esc_html_e( 'Personel adına açılan bağışta bağışçıya ilk mesaj', 'flx-chat' ); ?></span><input type="text" name="staff_donor_text" value="<?php echo esc_attr( $s['staff_donor_text'] ); ?>" placeholder="<?php echo esc_attr( __( 'Merhaba {ad} 🌱 {personel} aracılığıyla {bagis} bağış talebiniz alındı. Ödemeyi aşağıdaki bilgilerle yapabilirsiniz.', 'flx-chat' ) ); ?>"><small><?php esc_html_e( 'Ardından ödeme linki ve havale bilgileri gider. Değişkenler: {ad}, {adsoyad}, {personel}, {bagis}.', 'flx-chat' ); ?></small></label>
		<label class="flxcs-check"><input type="checkbox" name="ref_cert_notify" value="1" <?php checked( $s['ref_cert_notify'] ); ?>> <?php esc_html_e( 'Personelin referansı olduğu bağış ödenip sertifika oluşunca sertifika personele de WhatsApp\'tan gitsin (bağışçıya gittiği gibi)', 'flx-chat' ); ?></label>
		<div class="flxcs-grid flxcs-grid-3">
			<label class="flxcs-field"><span><?php esc_html_e( 'Personele sertifikadan önce giden mesaj', 'flx-chat' ); ?></span><input type="text" name="ref_cert_text" value="<?php echo esc_attr( $s['ref_cert_text'] ); ?>" placeholder="<?php echo esc_attr( flxc_ref_cert_default_text() ); ?>"><small><?php esc_html_e( 'Değişkenler: {ad}, {bagisci}, {bagis}, {no}, {brand}.', 'flx-chat' ); ?></small></label>
			<label class="flxcs-field"><span><?php esc_html_e( 'Personel bir bağışın sertifikasını en fazla kaç kez yeniden gönderebilir', 'flx-chat' ); ?></span><input type="number" min="0" max="20" name="staff_cert_max" value="<?php echo (int) $s['staff_cert_max']; ?>"><small><?php esc_html_e( 'Asistan göndermeden önce kime gideceğini yazıp onay ister. 0: kapalı.', 'flx-chat' ); ?></small></label>
		</div>
		<h3 class="flxcs-h3"><?php esc_html_e( 'Kayıt sorgusunda kod doğrulama', 'flx-chat' ); ?></h3>
		<label class="flxcs-check"><input type="checkbox" name="email_verify" value="1" <?php checked( $s['email_verify'] ); ?>> <?php esc_html_e( 'Kişi kayıtlarını sorar ve numarası gizliyse (ya da başka numara/e-postayla bağış yaptıysa) verdiği numaraya ya da e-postaya kod gönder; kod doğrulanınca onun kayıtları da gösterilir', 'flx-chat' ); ?></label>
		<div class="flxcs-grid flxcs-grid-3">
			<label class="flxcs-field"><span><?php esc_html_e( 'Kod geçerliliği (dk)', 'flx-chat' ); ?></span><input type="number" min="1" max="30" name="verify_ttl" value="<?php echo (int) $s['verify_ttl']; ?>"></label>
			<label class="flxcs-field"><span><?php esc_html_e( 'En fazla başarısız deneme', 'flx-chat' ); ?></span><input type="number" min="1" max="10" name="verify_max_fails" value="<?php echo (int) $s['verify_max_fails']; ?>"><small><?php esc_html_e( 'Gönderilemeyen, yanlış ya da süresi dolan kodlar sayılır. Sınırda kişiye aşağıdaki mesaj gider, ekibe haber verilir; asistan diğer sorulara cevap verir ama 24 saat kayıt bilgisi vermez.', 'flx-chat' ); ?></small></label>
			<label class="flxcs-field"><span><?php esc_html_e( 'Telefona kod', 'flx-chat' ); ?></span><select name="verify_phone_channel">
				<?php foreach ( array( 'auto' => __( 'WhatsApp, gitmezse SMS', 'flx-chat' ), 'whatsapp' => __( 'Yalnız WhatsApp', 'flx-chat' ), 'sms' => __( 'Yalnız SMS', 'flx-chat' ) ) as $flxc_k => $flxc_l ) : ?>
					<option value="<?php echo esc_attr( $flxc_k ); ?>" <?php selected( $s['verify_phone_channel'], $flxc_k ); ?>><?php echo esc_html( $flxc_l ); ?></option>
				<?php endforeach; ?>
			</select><small><?php esc_html_e( 'SMS, Flux Mail\'te SMS bağlantısı (ör. Netgsm) varsa kullanılır.', 'flx-chat' ); ?></small></label>
		</div>
		<label class="flxcs-field"><span><?php esc_html_e( 'Doğrulama yapılamazsa giden mesaj', 'flx-chat' ); ?></span><input type="text" name="verify_fail_text" value="<?php echo esc_attr( $s['verify_fail_text'] ); ?>" placeholder="<?php echo esc_attr( flxc_verify_fail_text() ); ?>"></label>
		<label class="flxcs-field"><span><?php esc_html_e( 'WhatsApp/SMS kod mesajı', 'flx-chat' ); ?></span><textarea name="verify_msg" rows="3" placeholder="<?php echo esc_attr( flxc_verify_default_text() ); ?>"><?php echo esc_textarea( $s['verify_msg'] ); ?></textarea><small><?php esc_html_e( 'Boşsa varsayılan metin. Değişkenler: {kod}, {dk}, {marka}. E-posta metni Flux Mail › E-posta şablonları\'ndan değişir.', 'flx-chat' ); ?></small></label>

		<h3 class="flxcs-h3"><?php esc_html_e( 'Sitedeki içerikler', 'flx-chat' ); ?></h3>
		<label class="flxcs-check"><input type="checkbox" name="bot_site_search" value="1" <?php checked( $s['bot_site_search'] ); ?>> <?php esc_html_e( 'Asistan sitede yayında olan içeriklerde arama yapabilsin (ör. "fidan dikimi geçen bir gönderiniz var mı")', 'flx-chat' ); ?></label>
		<div class="flxcs-field"><span><?php esc_html_e( 'Aranacak içerik türleri', 'flx-chat' ); ?></span>
			<?php
			$flxc_sel_types = (array) $s['bot_site_types'];
			$flxc_auto      = flxc_site_types();
			foreach ( get_post_types( array( 'public' => true ), 'objects' ) as $flxc_pt ) :
				if ( 'attachment' === $flxc_pt->name ) {
					continue;
				}
				$flxc_on = $flxc_sel_types ? in_array( $flxc_pt->name, $flxc_sel_types, true ) : isset( $flxc_auto[ $flxc_pt->name ] );
				?>
				<label class="flxcs-check"><input type="checkbox" name="bot_site_types[]" value="<?php echo esc_attr( $flxc_pt->name ); ?>" <?php checked( $flxc_on ); ?>> <?php echo esc_html( $flxc_pt->labels->name ); ?></label>
			<?php endforeach; ?>
			<small><?php esc_html_e( 'Yalnız yayında ve şifresiz içerikler döner. Taslak, özel ve şifreli içerik hiçbir zaman gösterilmez.', 'flx-chat' ); ?></small>
		</div>
		<label class="flxcs-field"><span><?php esc_html_e( 'Hiç paylaşılmayacak kategori ve etiketler', 'flx-chat' ); ?></span><input type="text" name="bot_site_exclude" value="<?php echo esc_attr( $s['bot_site_exclude'] ); ?>" placeholder="<?php esc_attr_e( 'ör. kurumsal-teklifler, ic-duyuru', 'flx-chat' ); ?>"><small><?php esc_html_e( 'Ad ya da kısa ad, virgülle. Tek bir içeriği gizlemek için düzenleme ekranındaki "Asistan bu içeriği kişilerle paylaşmasın" kutusunu işaretleyin.', 'flx-chat' ); ?></small></label>

		<h3 class="flxcs-h3"><?php esc_html_e( 'Ekip grubu', 'flx-chat' ); ?></h3>
		<?php $flxc_groups = flxc_mirror_groups( isset( $_GET['groups'] ) ); // phpcs:ignore ?>
		<label class="flxcs-field"><span><?php esc_html_e( 'WhatsApp grubu', 'flx-chat' ); ?></span>
			<select name="mirror_group" style="background-repeat:no-repeat">
				<option value=""><?php esc_html_e( '— Seçilmedi —', 'flx-chat' ); ?></option>
				<?php foreach ( $flxc_groups as $flxc_jid => $flxc_gname ) : ?>
					<option value="<?php echo esc_attr( $flxc_jid ); ?>" <?php selected( $s['mirror_group'], $flxc_jid ); ?>><?php echo esc_html( $flxc_gname ); ?></option>
				<?php endforeach; ?>
				<?php if ( '' !== $s['mirror_group'] && ! isset( $flxc_groups[ $s['mirror_group'] ] ) ) : ?>
					<option value="<?php echo esc_attr( $s['mirror_group'] ); ?>" selected><?php echo esc_html( $s['mirror_group'] ); ?></option>
				<?php endif; ?>
			</select>
			<small><?php esc_html_e( 'Hattın üye olduğu gruplar. Yeni açtığınız grup görünmüyorsa', 'flx-chat' ); ?> <a href="<?php echo esc_url( admin_url( 'admin.php?page=flx-chat-ayarlar&tab=ai&groups=1' ) ); ?>"><?php esc_html_e( 'listeyi yenileyin', 'flx-chat' ); ?></a>.</small>
		</label>
		<label class="flxcs-check"><input type="checkbox" name="mirror_enabled" value="1" <?php checked( $s['mirror_enabled'] ); ?>> <?php esc_html_e( 'Kişilerden gelen mesajları ve verilen cevapları (asistan, ekip, otomatik) bu gruba yaz', 'flx-chat' ); ?></label>
		<label class="flxcs-check"><input type="checkbox" name="notify_to_group" value="1" <?php checked( $s['notify_to_group'] ); ?>> <?php esc_html_e( 'Devir bildirimini numaralara ayrı ayrı değil bu gruba gönder; yukarıdaki numaralar mesajda etiketlenir', 'flx-chat' ); ?></label>
		<?php $flxc_merr = get_option( 'flxc_mirror_error' ); ?>
		<?php if ( is_array( $flxc_merr ) ) : ?>
			<p class="flxcs-warn"><?php echo esc_html( __( 'Gruba son gönderim başarısız: ', 'flx-chat' ) . $flxc_merr['error'] ); ?></p>
		<?php endif; ?>
		<?php
		$preview = flxc_bot_price_text();
		if ( '' !== $preview ) :
			?>
			<details class="flxcs-details"><summary><?php esc_html_e( 'Fiyat listesi önizlemesi', 'flx-chat' ); ?></summary><pre class="flxcs-pre"><?php echo esc_html( $preview ); ?></pre></details>
		<?php endif; ?>
		<?php if ( ! flxc_ai_ready() ) : ?>
			<p class="flxcs-small"><?php esc_html_e( 'Asistanın çalışması için önce yapay zekâ anahtarı ve model girin.', 'flx-chat' ); ?></p>
		<?php endif; ?>
		<?php $flxc_problem = flxc_ai_types_problem(); ?>
		<?php if ( '' !== $flxc_problem ) : ?>
			<p class="flxcs-warn"><?php echo esc_html( __( 'Asistan şu an konuşmaz: ', 'flx-chat' ) . $flxc_problem ); ?></p>
		<?php endif; ?>
	</section>
	<div class="flxcs-save"><button type="submit" class="flxcs-btn flxcs-btn-primary"><?php esc_html_e( 'Kaydet', 'flx-chat' ); ?></button></div>
	<?php
}

function flxc_save_settings() {
	if ( ! flxc_can_manage() ) {
		wp_die( esc_html__( 'Bu sayfaya erişim yetkiniz yok.', 'flx-chat' ) );
	}
	check_admin_referer( 'flxc_save_settings' );
	$tab = sanitize_key( $_POST['tab'] ?? 'baglantilar' );
	$in  = wp_unslash( $_POST );
	$cur = flxc_settings();
	$upd = array();

	if ( 'baglantilar' === $tab ) {
		$old  = flxc_connections();
		$list = array();
		foreach ( (array) ( $in['connections'] ?? array() ) as $row ) {
			if ( ! is_array( $row ) ) {
				continue;
			}
			$id = sanitize_key( $row['id'] ?? '' );
			if ( ! empty( $row['delete'] ) ) {
				continue;
			}
			$prev = $id && isset( $old[ $id ] ) ? $old[ $id ] : null;
			$c    = $prev ? $prev : flxc_new_connection();
			$src  = (string) ( $row['source'] ?? 'own' );
			if ( 0 === strpos( $src, 'flxi:' ) ) {
				$pid = (int) substr( $src, 5 );
				if ( 'flxi' !== $c['source'] || (int) $c['provider_id'] !== $pid ) {
					$c['flxs_id'] = 0; // başka bir bağlantı seçildi; eski Flux Certificates geri dönüşü geçersiz
				}
				$c['source']      = 'flxi';
				$c['provider_id'] = $pid;
			} elseif ( 0 === strpos( $src, 'flxs:' ) ) {
				$c['source']      = 'flxs';
				$c['provider_id'] = (int) substr( $src, 5 );
			} else {
				$c['source']      = 'own';
				$c['provider_id'] = 0;
			}
			$c['label']    = sanitize_text_field( $row['label'] ?? '' );
			$c['label']    = '' !== $c['label'] ? $c['label'] : 'WhatsApp';
			$c['base_url'] = esc_url_raw( trim( (string) ( $row['base_url'] ?? '' ) ) );
			$c['instance'] = sanitize_text_field( $row['instance'] ?? '' );
			$key           = trim( (string) ( $row['api_key'] ?? '' ) );
			if ( '' !== $key ) {
				$c['api_key'] = sanitize_text_field( $key );
			}
			if ( ! empty( $row['new_secret'] ) ) {
				$c['secret'] = wp_generate_password( 32, false, false );
			}
			$list[] = $c;
		}
		$upd['connections'] = $list;
	} elseif ( 'genel' === $tab ) {
		$upd['brand_name']      = sanitize_text_field( $in['brand_name'] ?? '' );
		$upd['accent']          = sanitize_hex_color( $in['accent'] ?? '' ) ? sanitize_hex_color( $in['accent'] ) : '#00a884';
		$upd['send_read']       = empty( $in['send_read'] ) ? 0 : 1;
		$upd['agent_signature'] = empty( $in['agent_signature'] ) ? 0 : 1;
		$upd['restrict_agents'] = empty( $in['restrict_agents'] ) ? 0 : 1;
		$upd['show_groups']     = empty( $in['show_groups'] ) ? 0 : 1;
		$upd['ui_flux']         = empty( $in['ui_flux'] ) ? 0 : 1;
		$upd['delete_data']     = empty( $in['delete_data'] ) ? 0 : 1;
		$upd['poll_interval']   = min( 60, max( 3, (int) ( $in['poll_interval'] ?? 10 ) ) );
	} elseif ( 'otomatik' === $tab ) {
		$upd['welcome_enabled'] = empty( $in['welcome_enabled'] ) ? 0 : 1;
		$upd['welcome_text']    = sanitize_textarea_field( $in['welcome_text'] ?? '' );
		$upd['away_enabled']    = empty( $in['away_enabled'] ) ? 0 : 1;
		$upd['away_text']       = sanitize_textarea_field( $in['away_text'] ?? '' );
		$upd['away_days']       = array_values( array_filter( array_map( 'intval', (array) ( $in['away_days'] ?? array() ) ), function ( $d ) {
			return $d >= 1 && $d <= 7;
		} ) );
		$upd['away_start']      = preg_match( '/^\d{2}:\d{2}$/', (string) ( $in['away_start'] ?? '' ) ) ? $in['away_start'] : '09:00';
		$upd['away_end']        = preg_match( '/^\d{2}:\d{2}$/', (string) ( $in['away_end'] ?? '' ) ) ? $in['away_end'] : '18:00';
		$upd['away_throttle']   = min( 72, max( 1, (int) ( $in['away_throttle'] ?? 6 ) ) );
	} elseif ( 'etiketler' === $tab ) {
		$labels = array();
		$seen   = array();
		foreach ( (array) ( $in['labels'] ?? array() ) as $l ) {
			$name = sanitize_text_field( $l['name'] ?? '' );
			if ( '' === $name ) {
				continue;
			}
			$id = sanitize_key( $l['id'] ?? '' );
			if ( '' === $id ) {
				$id = substr( sanitize_key( remove_accents( $name ) ), 0, 30 );
				$id = '' !== $id ? $id : 'l' . strtolower( wp_generate_password( 5, false, false ) );
			}
			while ( isset( $seen[ $id ] ) ) {
				$id .= '2';
			}
			$seen[ $id ] = 1;
			$labels[]    = array(
				'id'    => $id,
				'name'  => $name,
				'color' => sanitize_hex_color( $l['color'] ?? '' ) ? sanitize_hex_color( $l['color'] ) : '#25d366',
			);
		}
		$upd['labels'] = $labels;
	} elseif ( 'kayitlar' === $tab ) {
		foreach ( array( 'agents_donations', 'agents_certificates', 'chips_bar' ) as $k ) {
			$upd[ $k ] = empty( $in[ $k ] ) ? 0 : 1;
		}
		foreach ( array( 'msg_donation_pending', 'msg_donation_done', 'msg_receipt', 'bank_text' ) as $k ) {
			$upd[ $k ] = sanitize_textarea_field( $in[ $k ] ?? '' );
		}
	} elseif ( 'ai' === $tab ) {
		$upd['ai_provider'] = sanitize_key( $in['ai_provider'] ?? '' );
		foreach ( array( 'ai_model', 'ai_model_bot', 'ai_model_vision', 'ai_model_audio' ) as $f ) {
			// OpenRouter "en güncel" takma adları ~ ile başlar (ör. ~deepseek/deepseek-v4-flash-latest).
			$upd[ $f ] = preg_replace( '/[^a-zA-Z0-9_.:\/@~+-]/', '', trim( (string) ( $in[ $f ] ?? '' ) ) );
		}
		$upd['ai_temperature']  = min( 1, max( 0, (float) str_replace( ',', '.', (string) ( $in['ai_temperature'] ?? 0.4 ) ) ) );
		$upd['ai_auto_suggest'] = empty( $in['ai_auto_suggest'] ) ? 0 : 1;
		$upd['bot_enabled']     = empty( $in['bot_enabled'] ) ? 0 : 1;
		$upd['bot_receipt_ai']  = empty( $in['bot_receipt_ai'] ) ? 0 : 1;
		$upd['bot_scope']       = in_array( $in['bot_scope'] ?? '', array( 'all', 'away', 'unassigned' ), true ) ? $in['bot_scope'] : 'all';
		$upd['bot_test_only']   = empty( $in['bot_test_only'] ) ? 0 : 1;
		$upd['bot_test_numbers'] = implode( ', ', array_filter( array_map( 'flxc_normalize_phone', preg_split( '/[\s,;]+/', (string) ( $in['bot_test_numbers'] ?? '' ) ) ) ) );
		$upd['bot_notify_numbers'] = implode( ', ', array_filter( array_map( 'flxc_normalize_phone', preg_split( '/[\s,;]+/', (string) ( $in['bot_notify_numbers'] ?? '' ) ) ) ) );
		$upd['bot_context_hours'] = min( 168, max( 1, (int) ( $in['bot_context_hours'] ?? 24 ) ) );
		$upd['bot_pause_hours'] = min( 720, max( 1, (int) ( $in['bot_pause_hours'] ?? 12 ) ) );
		$upd['bot_daily_limit'] = min( 500, max( 5, (int) ( $in['bot_daily_limit'] ?? 40 ) ) );
		$upd['bot_types']       = array_values( array_filter( array_map( 'absint', (array) ( $in['bot_types'] ?? array() ) ) ) );
		$upd['bot_presets']     = implode( ', ', array_slice( array_filter( array_map( 'absint', preg_split( '/[\s,;]+/', (string) ( $in['bot_presets'] ?? '' ) ) ) ), 0, 8 ) );
		$upd['bank_iban']       = strtoupper( preg_replace( '/[^A-Za-z0-9 ]/', '', (string) ( $in['bank_iban'] ?? '' ) ) );
		$upd['donate_url']      = esc_url_raw( (string) ( $in['donate_url'] ?? '' ) );
		$upd['mem_enabled']     = empty( $in['mem_enabled'] ) ? 0 : 1;
		$upd['mem_after_hours'] = min( 168, max( 1, (int) ( $in['mem_after_hours'] ?? 24 ) ) );
		$upd['workers_max']     = min( 30, max( 1, (int) ( $in['workers_max'] ?? 20 ) ) );
		$upd['workers_reserved'] = min( 30, max( 0, (int) ( $in['workers_reserved'] ?? 5 ) ) );
		foreach ( array( 'mem_window_from' => '03:00', 'mem_window_to' => '05:00' ) as $f => $def ) {
			$upd[ $f ] = preg_match( '/^\d{2}:\d{2}$/', (string) ( $in[ $f ] ?? '' ) ) ? (string) $in[ $f ] : $def;
		}
		$upd['bot_site_search'] = empty( $in['bot_site_search'] ) ? 0 : 1;
		$upd['bot_site_types']  = array_values( array_intersect( array_map( 'sanitize_key', (array) ( $in['bot_site_types'] ?? array() ) ), get_post_types( array( 'public' => true ) ) ) );
		$upd['mirror_enabled']  = empty( $in['mirror_enabled'] ) ? 0 : 1;
		$upd['bot_time_budget'] = min( 110, max( 20, (int) ( $in['bot_time_budget'] ?? 50 ) ) );
		$upd['rem_enabled']     = empty( $in['rem_enabled'] ) ? 0 : 1;
		$upd['rem_time']        = preg_match( '/^\d{2}:\d{2}$/', (string) ( $in['rem_time'] ?? '' ) ) ? (string) $in['rem_time'] : '09:00';
		$upd['rem_max']         = min( 50, max( 1, (int) ( $in['rem_max'] ?? 10 ) ) );
		$upd['email_verify']    = empty( $in['email_verify'] ) ? 0 : 1;
		$upd['verify_ttl']      = min( 30, max( 1, (int) ( $in['verify_ttl'] ?? 5 ) ) );
		$upd['verify_phone_channel'] = in_array( $in['verify_phone_channel'] ?? '', array( 'auto', 'whatsapp', 'sms' ), true ) ? $in['verify_phone_channel'] : 'auto';
		$upd['verify_msg']      = sanitize_textarea_field( $in['verify_msg'] ?? '' );
		$upd['bot_cancel_text'] = sanitize_textarea_field( $in['bot_cancel_text'] ?? '' );
		$upd['verify_max_fails'] = min( 10, max( 1, (int) ( $in['verify_max_fails'] ?? 3 ) ) );
		$upd['verify_fail_text'] = sanitize_text_field( $in['verify_fail_text'] ?? '' );
		$upd['bot_fallback_wait'] = min( 45, max( 0, (int) ( $in['bot_fallback_wait'] ?? 30 ) ) );
		$upd['notify_to_group'] = empty( $in['notify_to_group'] ) ? 0 : 1;
		$upd['staff_on']        = empty( $in['staff_on'] ) ? 0 : 1;
		$upd['ref_notify']      = empty( $in['ref_notify'] ) ? 0 : 1;
		$upd['benef_cert_notify'] = empty( $in['benef_cert_notify'] ) ? 0 : 1;
		$upd['ref_cert_notify']   = empty( $in['ref_cert_notify'] ) ? 0 : 1;
		$upd['staff_cert_max']    = min( 20, max( 0, (int) ( $in['staff_cert_max'] ?? 3 ) ) );
		$upd['bot_widgets']     = empty( $in['bot_widgets'] ) ? 0 : 1;
		$upd['bot_voice']       = empty( $in['bot_voice'] ) ? 0 : 1;
		$upd['bot_voice_max']   = min( 300, max( 10, (int) ( $in['bot_voice_max'] ?? 60 ) ) );
		$upd['mirror_group']    = preg_match( '/^[0-9-]+@g\.us$/', (string) ( $in['mirror_group'] ?? '' ) ) ? (string) $in['mirror_group'] : '';
		foreach ( array( 'bot_price_footer', 'bank_holder', 'bank_desc', 'bot_after_payment', 'bot_handoff_text', 'bot_limit_text', 'staff_donor_text', 'benef_cert_text', 'ref_cert_text', 'bot_optout_text', 'bot_site_exclude', 'bot_fallback_text' ) as $f ) {
			$upd[ $f ] = sanitize_text_field( $in[ $f ] ?? '' );
		}
		foreach ( array( 'ai_instructions', 'bot_instructions', 'bot_handoff_words' ) as $f ) {
			$upd[ $f ] = sanitize_textarea_field( $in[ $f ] ?? '' );
		}
	}
	unset( $cur );
	flxc_update_settings( $upd );
	wp_safe_redirect( admin_url( 'admin.php?page=flx-chat-ayarlar&tab=' . $tab . '&saved=1' ) );
	exit;
}
