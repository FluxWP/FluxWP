<?php
/**
 * Bağlantılar: WhatsApp (Evolution) ve SMS (Netgsm); Flux Certificates'dan taşıma.
 *
 * @package flx-mail
 */

defined( 'ABSPATH' ) || exit;

function flxi_page_connections() {
	$edit = isset( $_GET['edit'] ) ? sanitize_text_field( wp_unslash( $_GET['edit'] ) ) : ''; // phpcs:ignore
	flxi_page_head( __( 'Bağlantılar', 'flx-mail' ), __( 'WhatsApp ve SMS hesapları tek yerde. Flux Certificates, Flux Chat ve diğer eklentiler bu bağlantıları kullanır. E-posta ayarları Ayarlar sekmesinde.', 'flx-mail' ) );
	$types = flxi_connection_types();
	$tests = (array) get_option( 'flxi_conn_tests', array() );

	if ( '' !== $edit ) {
		$conn = 'new' === $edit ? null : flxi_get_connection( (int) $edit );
		$type = $conn ? $conn['type'] : sanitize_key( wp_unslash( $_GET['type'] ?? 'evolution' ) ); // phpcs:ignore
		if ( ! isset( $types[ $type ] ) ) {
			$type = 'evolution';
		}
		$def = $types[ $type ];
		echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '" class="flxi-card flxi-narrow" autocomplete="off">';
		wp_nonce_field( 'flxi_save_connection' );
		echo '<input type="hidden" name="action" value="flxi_save_connection"><input type="hidden" name="id" value="' . (int) ( $conn['id'] ?? 0 ) . '"><input type="hidden" name="type" value="' . esc_attr( $type ) . '">';
		echo '<h2 class="flxi-card__title">' . esc_html( $def['label'] ) . '</h2><p class="flxi-muted">' . esc_html( $def['help'] ) . '</p>';
		if ( 'wacloud' === $type ) {
			flxi_wacloud_limits_note();
		}
		echo '<label class="flxi-field"><span>' . esc_html__( 'Ad', 'flx-mail' ) . '</span><input type="text" name="name" value="' . esc_attr( $conn['name'] ?? $def['label'] ) . '"></label>';
		$evo     = flxi_is_evo_type( $type );
		$servers = array();
		if ( $evo && ! $conn ) {
			// Aynı sunucuya yeni numara: adres + API key mevcut bağlantıdan alınır (key tarayıcıya gelmez).
			foreach ( flxi_list_connections( 'whatsapp' ) as $o ) {
				$k = untrailingslashit( (string) $o['config']['base_url'] );
				if ( $o['type'] === $type && $k && ! isset( $servers[ $k ] ) ) {
					$servers[ $k ] = $o;
				}
			}
		}
		if ( $servers ) {
			echo '<label class="flxi-field"><span>' . esc_html__( 'Sunucu', 'flx-mail' ) . '</span><select name="copy_from" data-wa-copy><option value="">' . esc_html__( 'Yeni sunucu bilgisi gireceğim', 'flx-mail' ) . '</option>';
			foreach ( $servers as $k => $o ) {
				/* translators: 1: bağlantı adı, 2: adres */
				printf( '<option value="%d" selected>%s</option>', (int) $o['id'], esc_html( sprintf( __( 'Aynı sunucu: %1$s (%2$s)', 'flx-mail' ), $o['name'], wp_parse_url( $k, PHP_URL_HOST ) ) ) );
			}
			echo '</select><small>' . esc_html__( 'Aynı sunucuya ikinci bir numara eklerken adres ve API key yeniden girilmez; yalnız yeni instance adını yazın.', 'flx-mail' ) . '</small></label>';
		}
		foreach ( $def['fields'] as $f ) {
			$val = $conn['config'][ $f['key'] ] ?? ( $f['default'] ?? '' );
			$nm  = 'cfg[' . $f['key'] . ']';
			if ( 'checkbox' === $f['type'] ) {
				echo '<div class="flxi-field">' . flxi_switch( $nm, $val, $f['label'] ) . '</div>'; // phpcs:ignore
				continue;
			}
			echo '<label class="flxi-field"' . ( $evo && in_array( $f['key'], array( 'base_url', 'api_key' ), true ) ? ' data-wa-server' : '' ) . '><span>' . esc_html( $f['label'] ) . ( ! empty( $f['required'] ) ? ' <em aria-hidden="true">*</em>' : '' ) . '</span>';
			if ( 'select' === $f['type'] ) {
				echo '<select name="' . esc_attr( $nm ) . '">';
				foreach ( $f['options'] as $ov => $ol ) {
					printf( '<option value="%s"%s>%s</option>', esc_attr( $ov ), selected( (string) $val, (string) $ov, false ), esc_html( $ol ) );
				}
				echo '</select>';
			} elseif ( 'password' === $f['type'] ) {
				echo '<input type="password" name="' . esc_attr( $nm ) . '" value="" autocomplete="new-password" placeholder="' . esc_attr( $val ? flxi_mask( $val ) . ' · ' . __( 'değiştirmek için yazın', 'flx-mail' ) : '' ) . '"' . ( ! empty( $f['required'] ) && ! $val ? ' required' : '' ) . '>';
			} else {
				echo '<input type="' . ( 'number' === $f['type'] ? 'number' : ( 'url' === $f['type'] ? 'url' : 'text' ) ) . '" name="' . esc_attr( $nm ) . '" value="' . esc_attr( $val ) . '" placeholder="' . esc_attr( $f['placeholder'] ?? '' ) . '"' . ( ! empty( $f['required'] ) ? ' required' : '' ) . '>';
			}
			if ( ! empty( $f['help'] ) ) {
				echo '<small>' . esc_html( $f['help'] ) . '</small>';
			}
			echo '</label>';
		}
		echo '<div class="flxi-field">' . flxi_switch( 'active', $conn ? $conn['active'] : 1, __( 'Etkin', 'flx-mail' ) ) . '</div>'; // phpcs:ignore
		echo '<div class="flxi-actions"><button class="button button-primary button-large">' . esc_html__( 'Kaydet', 'flx-mail' ) . '</button> <a class="button button-large" href="' . esc_url( flxi_admin_url( 'flxi-baglantilar' ) ) . '">' . esc_html__( 'Vazgeç', 'flx-mail' ) . '</a></div></form>';
		flxi_page_foot();
		return;
	}

	foreach ( array( 'whatsapp', 'sms' ) as $ch ) {
		$list = flxi_list_connections( $ch );
		echo '<section class="flxi-card"><div class="flxi-card__head"><h2 class="flxi-card__title">' . esc_html( flxi_channels()[ $ch ] ) . '</h2><div class="flxi-card__tools">';
		foreach ( $types as $tp => $td ) {
			if ( $ch === $td['channel'] ) {
				echo '<a class="button" href="' . esc_url( flxi_admin_url( 'flxi-baglantilar', array( 'edit' => 'new', 'type' => $tp ) ) ) . '">+ ' . esc_html( trim( preg_replace( '/^(WhatsApp|SMS)\s*·\s*/u', '', $td['label'] ) ) ) . '</a> ';
			}
		}
		echo '</div></div>';
		if ( 'whatsapp' === $ch && $list ) {
			echo '<p class="flxi-muted flxi-card__note">' . esc_html__( 'Her satır bir numara. Numarayı bağlamak, durumunu görmek ya da yeniden bağlamak için "Bağlan"a basın; ayrı bir panele girmeniz gerekmez.', 'flx-mail' ) . '</p>';
		}
		if ( ! $list ) {
			echo '<p class="flxi-muted">' . esc_html__( 'Henüz bağlantı yok.', 'flx-mail' ) . '</p>';
		}
		echo '<ul class="flxi-list">';
		foreach ( $list as $c ) {
			$t = $tests[ $c['id'] ] ?? null;
			echo '<li class="flxi-row flxi-row--conn' . ( $c['active'] ? '' : ' is-off' ) . '"><div class="flxi-row__main"><strong class="flxi-row__title">' . esc_html( $c['name'] ) . '</strong> ';
			if ( $c['is_default'] ) {
				echo flxi_badge( __( 'Varsayılan', 'flx-mail' ), 'green' ); // phpcs:ignore
			}
			if ( ! $c['active'] ) {
				echo flxi_badge( __( 'Kapalı', 'flx-mail' ), 'off' ); // phpcs:ignore
			}
			$evo   = flxi_is_evo_type( $c['type'] );
			$cloud = 'wacloud' === $c['type'];
			$st = array( 'state' => '' );
			if ( $evo || $cloud ) {
				$st = flxi_wa_cached_state( $c['id'] );
				echo '<span class="flxi-wa-state" data-wa-state="' . (int) $c['id'] . '" data-state="' . esc_attr( $st['state'] ) . '">' . esc_html( flxi_wa_state_label( $st ) ) . '</span>';
			}
			$detail = $cloud ? 'Phone ID ' . ( $c['config']['phone_id'] ?? '' ) : ( $evo ? wp_parse_url( (string) ( $c['config']['base_url'] ?? '' ), PHP_URL_HOST ) . ' · ' . ( $c['config']['instance'] ?? '' ) : ( $c['config']['header'] ?? '' ) . ' · ' . ( $c['config']['username'] ?? '' ) );
			echo '<div class="flxi-row__desc">' . esc_html( $types[ $c['type'] ]['label'] ?? $c['type'] ) . ' · ' . esc_html( $detail );
			if ( $t ) {
				echo ' · <span class="' . ( $t['ok'] ? 'flxi-ok' : 'flxi-err' ) . '">' . esc_html( $t['ok'] ? __( 'Test başarılı', 'flx-mail' ) : $t['error'] ) . '</span>';
			}
			if ( $c['origin'] ) {
				echo ' · <span class="flxi-muted">' . esc_html__( 'Flux Certificates\'dan taşındı', 'flx-mail' ) . '</span>';
			}
			echo '</div></div><div class="flxi-row__actions flxi-conn-test">';
			if ( $cloud ) {
				echo '<button type="button" class="button button-primary" data-wac-setup="' . (int) $c['id'] . '" data-wa-name="' . esc_attr( $c['name'] ) . '">' . esc_html__( 'Kurulum', 'flx-mail' ) . '</button> ';
			}
			if ( $evo ) {
				// Bağlıyken gizli; durum değişince JS açar.
				echo '<button type="button" class="button button-primary flxi-wa-connect-main" data-wa-connect="' . (int) $c['id'] . '" data-wa-name="' . esc_attr( $c['name'] ) . '"' . ( 'open' === $st['state'] ? ' hidden' : '' ) . '>' . esc_html__( 'Bağlan', 'flx-mail' ) . '</button> ';
			}
			echo '<button type="button" class="button' . ( ( $evo && 'open' === $st['state'] ) || ! $evo ? ' button-primary' : '' ) . ' flxi-test-open" data-conn-test="' . (int) $c['id'] . '" data-type="' . esc_attr( $c['type'] ) . '" data-label="' . esc_attr( $types[ $c['type'] ]['label'] ?? $c['type'] ) . '" data-name="' . esc_attr( $c['name'] ) . '" data-scenarios="' . esc_attr( implode( ',', flxi_test_scenarios( $c['type'] ) ) ) . '">' . esc_html__( 'Test et', 'flx-mail' ) . '</button>';
			echo ' <a class="button" href="' . esc_url( flxi_admin_url( 'flxi-baglantilar', array( 'edit' => $c['id'] ) ) ) . '">' . esc_html__( 'Düzenle', 'flx-mail' ) . '</a>';
			echo '<details class="flxi-more"><summary class="button" aria-label="' . esc_attr__( 'Diğer işlemler', 'flx-mail' ) . '">⋯</summary><div class="flxi-more__menu">';
			if ( $evo ) {
				echo '<button type="button" class="button-link" data-wa-connect="' . (int) $c['id'] . '" data-wa-name="' . esc_attr( $c['name'] ) . '">' . esc_html__( 'Durum / yeniden bağla (QR)', 'flx-mail' ) . '</button>';
				echo '<button type="button" class="button-link" data-wa-restart="' . (int) $c['id'] . '">' . esc_html__( 'Yeniden başlat', 'flx-mail' ) . '</button>';
				echo '<button type="button" class="button-link" data-wa-logout="' . (int) $c['id'] . '">' . esc_html__( 'Numaranın oturumunu kapat', 'flx-mail' ) . '</button>';
				if ( 'flxwpapi' === $c['type'] ) {
					echo '<button type="button" class="button-link" data-wa-server="' . (int) $c['id'] . '">' . esc_html__( 'Sunucu ayarları', 'flx-mail' ) . '</button>';
				}
			}
			if ( ! $c['is_default'] ) {
				echo flxi_post_button( 'flxi_default_connection', array( 'id' => $c['id'] ), __( 'Varsayılan yap', 'flx-mail' ) ); // phpcs:ignore
			}
			echo flxi_post_button( 'flxi_delete_connection', array( 'id' => $c['id'] ), $evo ? __( 'Sil (numara sunucuda kalır)', 'flx-mail' ) : __( 'Sil', 'flx-mail' ), __( 'Bağlantı silinsin mi?', 'flx-mail' ) ); // phpcs:ignore
			if ( $evo ) {
				echo flxi_post_button( 'flxi_delete_connection', array( 'id' => $c['id'], 'remote' => 1 ), __( 'Sunucudan da sil', 'flx-mail' ), __( 'Numara sunucudan da silinsin mi? Oturum, mesaj geçmişi ve kişiler sunucudan silinir; yeniden eklemek için QR gerekir.', 'flx-mail' ), 'button-link flxi-danger' ); // phpcs:ignore
			}
			echo '</div></details><span class="flxi-status" aria-live="polite"></span></div></li>';
		}
		echo '</ul></section>';
	}

	flxi_wa_dialogs();

	if ( flxi_flxs_active() ) {
		echo '<p class="flxi-muted">' . esc_html__( 'Flux Certificates WhatsApp ve SMS\'i buradaki etkin bağlantılarla gönderir; sertifika e-postaları kurumsal şablonla gider.', 'flx-mail' ) . '</p>';
	}
	flxi_page_foot();
}

/** Tek düğmelik POST formu. */
function flxi_post_button( $action, array $fields, $label, $confirm = '', $class = 'button-link' ) {
	$o  = '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '" class="flxi-inline-form">';
	$o .= wp_nonce_field( $action, '_wpnonce', true, false ) . '<input type="hidden" name="action" value="' . esc_attr( $action ) . '">';
	foreach ( $fields as $k => $v ) {
		$o .= '<input type="hidden" name="' . esc_attr( $k ) . '" value="' . esc_attr( $v ) . '">';
	}
	$o .= '<button type="submit" class="' . esc_attr( $class ) . '"' . ( $confirm ? ' onclick="return confirm(this.dataset.confirm)" data-confirm="' . esc_attr( $confirm ) . '"' : '' ) . '>' . esc_html( $label ) . '</button></form>';
	return $o;
}

add_action(
	'admin_post_flxi_save_connection',
	function () {
		flxi_check( 'flxi_save_connection' );
		$id   = (int) ( $_POST['id'] ?? 0 );
		$type = sanitize_key( wp_unslash( $_POST['type'] ?? '' ) );
		$cfg  = (array) wp_unslash( $_POST['cfg'] ?? array() ); // phpcs:ignore
		$from = $id ? null : flxi_get_connection( (int) ( $_POST['copy_from'] ?? 0 ) );
		if ( $from && $from['type'] === $type ) {
			$cfg['base_url'] = $from['config']['base_url'] ?? '';
			$cfg['api_key']  = $from['config']['api_key'] ?? '';
		}
		$res = flxi_save_connection( $id, $type, sanitize_text_field( wp_unslash( $_POST['name'] ?? '' ) ), $cfg, ! empty( $_POST['active'] ) );
		if ( is_wp_error( $res ) ) {
			flxi_redirect( flxi_admin_url( 'flxi-baglantilar', array( 'edit' => $id ? $id : 'new', 'type' => $type ) ), '', $res->get_error_message() );
		}
		if ( ! $id && 'wacloud' === $type ) {
			flxi_redirect( flxi_admin_url( 'flxi-baglantilar', array( 'wac_setup' => $res ) ), __( 'Bağlantı kaydedildi. Şimdi webhook adresini Meta uygulamasına girin.', 'flx-mail' ) );
		}
		if ( ! $id && flxi_is_evo_type( $type ) ) {
			// Yeni numara: liste açılınca QR penceresi kendiliğinden gelir.
			flxi_redirect( flxi_admin_url( 'flxi-baglantilar', array( 'wa_connect' => $res ) ), __( 'Bağlantı kaydedildi. Telefonda WhatsApp › Bağlı cihazlar › Cihaz bağla ile QR\'ı okutun.', 'flx-mail' ) );
		}
		flxi_redirect( flxi_admin_url( 'flxi-baglantilar' ), __( 'Bağlantı kaydedildi. "Test et" ile deneyin.', 'flx-mail' ) );
	}
);

add_action(
	'admin_post_flxi_delete_connection',
	function () {
		flxi_check( 'flxi_delete_connection' );
		$id = (int) ( $_POST['id'] ?? 0 );
		if ( ! empty( $_POST['remote'] ) ) {
			$r = flxi_wa_delete_remote( $id );
			if ( is_wp_error( $r ) ) {
				flxi_redirect( flxi_admin_url( 'flxi-baglantilar' ), '', $r->get_error_message() );
			}
		}
		flxi_delete_connection( $id );
		flxi_redirect( flxi_admin_url( 'flxi-baglantilar' ), __( 'Bağlantı silindi.', 'flx-mail' ) );
	}
);

add_action(
	'admin_post_flxi_default_connection',
	function () {
		flxi_check( 'flxi_default_connection' );
		flxi_set_default_connection( (int) ( $_POST['id'] ?? 0 ) );
		flxi_redirect( flxi_admin_url( 'flxi-baglantilar' ), __( 'Varsayılan bağlantı değişti.', 'flx-mail' ) );
	}
);


/** Durum rozeti metni. */
function flxi_wa_state_label( array $st ) {
	switch ( $st['state'] ) {
		case 'open':
			return __( 'Bağlı', 'flx-mail' ) . ( $st['me'] ? ' · ' . $st['me'] : '' );
		case 'connecting':
			return __( 'QR bekliyor', 'flx-mail' );
		case 'close':
			return __( 'Bağlı değil', 'flx-mail' );
		case 'missing':
			return __( 'Sunucuda yok', 'flx-mail' );
		case 'error':
			return __( 'Sunucuya ulaşılamadı', 'flx-mail' );
	}
	return __( 'Durum bilinmiyor', 'flx-mail' );
}

/** QR ve sunucu ayarları pencereleri (içeriği admin.js doldurur). */
function flxi_wa_dialogs() {
	$close = '<button type="button" class="button-link" data-close aria-label="' . esc_attr__( 'Kapat', 'flx-mail' ) . '"><span class="dashicons dashicons-no-alt"></span></button>';
	echo '<dialog class="flxi-dialog flxi-wa-dialog" id="flxi-wa-dialog" aria-labelledby="flxi-wa-title"><div class="flxi-dialog__head"><h2 id="flxi-wa-title"></h2>' . $close . '</div><div class="flxi-wa" data-wa-body>'; // phpcs:ignore
	echo '<p class="flxi-wa__state" data-wa-msg aria-live="polite"></p>';
	echo '<div class="flxi-wa__qr" data-wa-qrbox><img data-wa-qr alt="' . esc_attr__( 'WhatsApp QR kodu', 'flx-mail' ) . '" width="264" height="264" hidden><div class="flxi-wa__code" data-wa-code hidden></div></div>';
	echo '<ol class="flxi-wa__steps" data-wa-steps><li>' . esc_html__( 'Telefonda WhatsApp\'ı açın.', 'flx-mail' ) . '</li><li>' . esc_html__( 'Ayarlar › Bağlı cihazlar › Cihaz bağla.', 'flx-mail' ) . '</li><li>' . esc_html__( 'Bu QR\'ı okutun. Kod kendiliğinden yenilenir.', 'flx-mail' ) . '</li></ol>';
	echo '<details class="flxi-wa__pair" data-wa-pair><summary>' . esc_html__( 'QR okutamıyorum: telefon numarasıyla kod al', 'flx-mail' ) . '</summary><div class="flxi-wa__pairrow"><input type="tel" data-wa-number placeholder="905301234567" aria-label="' . esc_attr__( 'Telefon numarası', 'flx-mail' ) . '"><button type="button" class="button" data-wa-paircode>' . esc_html__( 'Kod al', 'flx-mail' ) . '</button></div><small>' . esc_html__( 'WhatsApp › Bağlı cihazlar › Cihaz bağla › "Bunun yerine telefon numarasıyla bağla" ekranına bu kodu yazın.', 'flx-mail' ) . '</small></details>';
	echo '</div></dialog>';
	echo '<dialog class="flxi-dialog flxi-wa-dialog flxi-wac-dialog" id="flxi-wac-setup" aria-labelledby="flxi-wac-title"><div class="flxi-dialog__head"><h2 id="flxi-wac-title"></h2>' . $close . '</div><div class="flxi-wa" data-wac-body></div><div class="flxi-wa flxi-wac-limits">'; // phpcs:ignore
	flxi_wacloud_limits_note();
	echo '</div></dialog>';
	echo '<dialog class="flxi-dialog flxi-wa-dialog flxi-test-dialog" id="flxi-test-dialog" aria-labelledby="flxi-test-title"><div class="flxi-dialog__head"><h2 id="flxi-test-title"></h2>' . $close . '</div><div class="flxi-wa flxi-test" data-test-body></div></dialog>'; // phpcs:ignore
	echo '<dialog class="flxi-dialog flxi-wa-dialog" id="flxi-wa-server" aria-labelledby="flxi-wa-server-title"><div class="flxi-dialog__head"><h2 id="flxi-wa-server-title">' . esc_html__( 'Flux WPAPI sunucusu', 'flx-mail' ) . '</h2>' . $close . '</div><div class="flxi-wa" data-ws-body></div></dialog>'; // phpcs:ignore
}

/** Cloud API'de farklı/eksik olanlar (form ve kurulum penceresinde). */
function flxi_wacloud_limits_note() {
	echo '<div class="flxi-callout"><strong>' . esc_html__( 'Cloud API\'de farklı olanlar', 'flx-mail' ) . '</strong><ul>';
	echo '<li>' . esc_html__( '24 saat kuralı: kişi son 24 saatte yazmadıysa yalnız Meta\'nın onayladığı şablon gönderilebilir.', 'flx-mail' ) . '</li>';
	echo '<li>' . esc_html__( 'Grup mesajı yok: Çağrı Merkezi\'nin ekip grubu aynası bu hatta çalışmaz.', 'flx-mail' ) . '</li>';
	echo '<li>' . esc_html__( 'Gönderilen mesaj düzenlenemez; geçmiş sohbetler içe aktarılamaz.', 'flx-mail' ) . '</li>';
	echo '<li>' . esc_html__( '"Yazıyor…" yalnız gelen mesaja cevap verirken görünür.', 'flx-mail' ) . '</li>';
	echo '<li>' . esc_html__( 'Ücret: şablon mesajları ve ayda 1000\'i aşan cevap mesajları Meta tarafından ücretlendirilir.', 'flx-mail' ) . '</li>';
	echo '<li>' . esc_html__( 'Ban riski yok; numara telefondaki WhatsApp Business uygulamasında da kalabilir (coexistence).', 'flx-mail' ) . '</li>';
	echo '</ul></div>';
}
