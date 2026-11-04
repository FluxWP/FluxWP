<?php
/**
 * E-postalar: bütün e-postaların listesi ve şablon düzenleyici.
 *
 * @package flx-mail
 */

defined( 'ABSPATH' ) || exit;

function flxi_page_templates() {
	$key = isset( $_GET['key'] ) ? sanitize_text_field( wp_unslash( $_GET['key'] ) ) : ''; // phpcs:ignore
	if ( $key ) {
		flxi_page_template_edit( $key );
		return;
	}
	global $wpdb;
	flxi_page_head( __( 'E-postalar', 'flx-mail' ), __( 'Sitenin gönderdiği bütün e-postalar. Açıp kapatabilir, içeriği düzenleyebilir, kimden gideceğini seçebilirsiniz.', 'flx-mail' ) );

	// Özet
	$t     = flxi_table( 'log' );
	$since = gmdate( 'Y-m-d H:i:s', time() - 7 * DAY_IN_SECONDS );
	$sum   = array( 'sent' => 0, 'failed' => 0, 'blocked' => 0 );
	foreach ( (array) $wpdb->get_results( $wpdb->prepare( "SELECT status, COUNT(*) n FROM $t WHERE created_at >= %s GROUP BY status", $since ), ARRAY_A ) as $r ) { // phpcs:ignore
		$sum[ $r['status'] ] = (int) $r['n'];
	}
	$s       = flxi_settings();
	$method  = flxi_other_smtp_plugin() ? flxi_other_smtp_plugin() : ( flxi_smtp_ready() ? 'SMTP · ' . $s['smtp_host'] : __( 'PHP mail() (önerilmez)', 'flx-mail' ) );
	echo '<div class="flxi-stats">';
	printf( '<a class="flxi-stat" href="%s"><span class="flxi-stat__n">%s</span><span class="flxi-stat__l">%s</span></a>', esc_url( flxi_admin_url( 'flxi-kayitlar', array( 'status' => 'sent' ) ) ), esc_html( number_format_i18n( $sum['sent'] ) ), esc_html__( 'gönderildi (7 gün)', 'flx-mail' ) );
	printf( '<a class="flxi-stat%s" href="%s"><span class="flxi-stat__n">%s</span><span class="flxi-stat__l">%s</span></a>', $sum['failed'] ? ' flxi-stat--bad' : '', esc_url( flxi_admin_url( 'flxi-kayitlar', array( 'status' => 'failed' ) ) ), esc_html( number_format_i18n( $sum['failed'] ) ), esc_html__( 'başarısız', 'flx-mail' ) );
	printf( '<a class="flxi-stat" href="%s"><span class="flxi-stat__n">%s</span><span class="flxi-stat__l">%s</span></a>', esc_url( flxi_admin_url( 'flxi-kayitlar', array( 'status' => 'blocked' ) ) ), esc_html( number_format_i18n( $sum['blocked'] ) ), esc_html__( 'kapalı olduğu için gitmedi', 'flx-mail' ) );
	printf(
		'<a class="flxi-stat flxi-stat--wide%s" href="%s"><span class="flxi-stat__l">%s</span><span class="flxi-stat__v">%s &lt;%s&gt;</span><span class="flxi-stat__l">%s</span></a>',
		flxi_smtp_ready() || flxi_other_smtp_plugin() ? '' : ' flxi-stat--warn',
		esc_url( flxi_admin_url( 'flxi-ayarlar' ) ),
		esc_html__( 'Gönderen', 'flx-mail' ),
		esc_html( $s['from_name'] ),
		esc_html( $s['from_email'] ),
		esc_html( $method )
	);
	echo '</div>';

	// Gruplar
	$groups = array();
	foreach ( flxi_email_types() as $k => $type ) {
		$c                            = flxi_template( $k );
		$groups[ $type['group'] ][] = array(
			'key'      => $k,
			'label'    => $type['label'],
			'desc'     => $type['description'],
			'to'       => 'admin' === $type['recipient'] ? __( 'Yöneticiye', 'flx-mail' ) : __( 'Kişiye', 'flx-mail' ),
			'enabled'  => $c['enabled'],
			'sender'   => $c['sender'],
			'editable' => $type['editable'],
			'lock'     => ! $type['can_disable'],
			'kind'     => 'registered',
		);
	}
	foreach ( flxi_wc_emails() as $e ) {
		$groups['WooCommerce'][] = array(
			'key'      => $e['key'],
			'label'    => $e['label'],
			'desc'     => $e['description'],
			'to'       => $e['customer'] ? __( 'Kişiye', 'flx-mail' ) : __( 'Yöneticiye', 'flx-mail' ),
			'enabled'  => $e['enabled'],
			'sender'   => flxi_template( $e['key'] )['sender'] ? flxi_template( $e['key'] )['sender'] : 'info',
			'editable' => false,
			'lock'     => $e['manual'],
			'kind'     => 'woocommerce',
			'edit_url' => $e['edit_url'],
		);
	}
	foreach ( flxi_discovered() as $k => $d ) {
		$c                                           = flxi_template( $k );
		$groups[ flxi_source_label( $d['source'] ) ][] = array(
			'key'      => $k,
			'label'    => flxi_discovered_label( $k, $d ),
			/* translators: 1: sayı, 2: tarih */
			'desc'     => sprintf( __( '%1$s kez gönderildi, son: %2$s. İçeriği gönderen eklenti üretir; burada açıp kapatabilir, gönderen adresini ve kurumsal çerçeveyi seçebilirsiniz.', 'flx-mail' ), number_format_i18n( (int) $d['count'] ), flx_date( (int) $d['last'] ) ),
			'to'       => '',
			'enabled'  => $c['enabled'],
			'sender'   => $c['sender'] ? $c['sender'] : flxi_default_sender( $k, $d['source'] ),
			'editable' => false,
			'lock'     => false,
			'kind'     => 'discovered',
		);
	}
	$order = array( 'WordPress' => 0, 'Flux Certificates' => 1, 'WooCommerce' => 2 );
	uksort(
		$groups,
		function ( $a, $b ) use ( $order ) {
			return ( $order[ $a ] ?? 9 ) <=> ( $order[ $b ] ?? 9 ) ?: strcasecmp( $a, $b );
		}
	);

	echo '<div class="flxi-toolbar"><input type="search" class="flxi-search" id="flxi-tpl-search" placeholder="' . esc_attr__( 'E-posta ara…', 'flx-mail' ) . '" aria-label="' . esc_attr__( 'E-posta ara', 'flx-mail' ) . '"></div>';

	foreach ( $groups as $group => $rows ) {
		echo '<section class="flxi-card flxi-group" data-group><h2 class="flxi-card__title">' . esc_html( $group ) . ' <span class="flxi-muted">' . esc_html( number_format_i18n( count( $rows ) ) ) . '</span></h2><ul class="flxi-list">';
		foreach ( $rows as $r ) {
			$edit = flxi_admin_url( 'flxi', array( 'key' => $r['key'] ) );
			echo '<li class="flxi-row' . ( $r['enabled'] ? '' : ' is-off' ) . '" data-search="' . esc_attr( mb_strtolower( $r['label'] . ' ' . $r['key'] . ' ' . $group ) ) . '">';
			echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '" class="flxi-row__toggle">';
			wp_nonce_field( 'flxi_toggle_template' );
			echo '<input type="hidden" name="action" value="flxi_toggle_template"><input type="hidden" name="key" value="' . esc_attr( $r['key'] ) . '">';
			if ( $r['lock'] ) {
				echo '<span class="flxi-lock dashicons dashicons-lock" title="' . esc_attr__( 'Kapatılamaz', 'flx-mail' ) . '"></span>';
			} else {
				echo flxi_switch( 'enabled', $r['enabled'], '', 'data-autosubmit aria-label="' . esc_attr( sprintf( /* translators: %s: e-posta adı */ __( '%s açık', 'flx-mail' ), $r['label'] ) ) . '"' ); // phpcs:ignore
			}
			echo '</form>';
			echo '<div class="flxi-row__main"><a class="flxi-row__title" href="' . esc_url( $edit ) . '">' . esc_html( $r['label'] ) . '</a>';
			if ( $r['desc'] ) {
				echo '<div class="flxi-row__desc">' . esc_html( $r['desc'] ) . '</div>';
			}
			echo '</div><div class="flxi-row__meta">';
			if ( $r['to'] ) {
				echo flxi_badge( $r['to'] ); // phpcs:ignore
			}
			echo flxi_badge( 'noreply' === $r['sender'] ? 'no-reply' : strtok( (string) flxi_setting( 'from_email' ), '@' ) . '@', 'noreply' === $r['sender'] ? 'muted' : 'green' ); // phpcs:ignore
			if ( ! $r['enabled'] ) {
				echo flxi_badge( __( 'Kapalı', 'flx-mail' ), 'off' ); // phpcs:ignore
			}
			echo '</div><div class="flxi-row__actions">';
			if ( 'woocommerce' === $r['kind'] ) {
				echo '<a class="button" href="' . esc_url( $r['edit_url'] ) . '">' . esc_html__( 'WooCommerce\'te düzenle', 'flx-mail' ) . '</a>';
			} else {
				echo '<a class="button" href="' . esc_url( $edit ) . '">' . ( $r['editable'] ? esc_html__( 'Düzenle', 'flx-mail' ) : esc_html__( 'Ayarlar', 'flx-mail' ) ) . '</a>';
			}
			echo '</div></li>';
		}
		echo '</ul></section>';
	}
	if ( ! flxi_wc_emails() && class_exists( 'WooCommerce' ) ) {
		echo '<p class="flxi-muted">' . esc_html__( 'WooCommerce e-postaları yüklenemedi.', 'flx-mail' ) . '</p>';
	}
	echo '<p class="flxi-muted flxi-foot-note">' . esc_html__( 'Listede olmayan bir eklentinin e-postası ilk gönderildiğinde buraya kendiliğinden eklenir.', 'flx-mail' ) . '</p>';
	flxi_page_foot();
}

/** Şablon düzenleyici. */
function flxi_page_template_edit( $key ) {
	$types = flxi_email_types();
	$type  = $types[ $key ] ?? null;
	$conf  = flxi_template( $key );
	$disc  = flxi_discovered()[ $key ] ?? null;
	if ( ! $type && ! $disc && 0 !== strpos( $key, 'wc/' ) ) {
		flxi_page_head( __( 'E-posta bulunamadı', 'flx-mail' ) );
		echo '<p><a href="' . esc_url( flxi_admin_url( 'flxi' ) ) . '">&larr; ' . esc_html__( 'E-postalar', 'flx-mail' ) . '</a></p>';
		flxi_page_foot();
		return;
	}
	$label = $type ? $type['label'] : ( $disc ? flxi_discovered_label( $key, $disc ) : $key );
	flxi_page_head( $label, '<a href="' . esc_url( flxi_admin_url( 'flxi' ) ) . '">&larr; ' . esc_html__( 'Tüm e-postalar', 'flx-mail' ) . '</a>' . ( $type ? ' · ' . esc_html( $type['group'] ) : '' ) );

	$editable = $type && $type['editable'];
	$sender   = $conf['sender'] ? $conf['sender'] : ( $type ? $type['defaults']['sender'] : flxi_default_sender( $key, $disc['source'] ?? '' ) );
	echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '" class="flxi-editor' . ( $editable ? '' : ' flxi-editor--simple' ) . '" id="flxi-editor" data-key="' . esc_attr( $key ) . '">';
	wp_nonce_field( 'flxi_save_template' );
	echo '<input type="hidden" name="action" value="flxi_save_template"><input type="hidden" name="key" value="' . esc_attr( $key ) . '">';

	echo '<div class="flxi-editor__form"><div class="flxi-card">';
	if ( $type && $type['description'] ) {
		echo '<p class="flxi-muted">' . esc_html( $type['description'] ) . '</p>';
	} elseif ( $disc ) {
		/* translators: 1: kaynak, 2: fonksiyon */
		echo '<p class="flxi-muted">' . esc_html( sprintf( __( 'Gönderen: %1$s (%2$s). İçeriği o eklenti üretir.', 'flx-mail' ), flxi_source_label( $disc['source'] ), $disc['caller'] ) ) . '</p>';
		/* translators: %s: konu */
		echo '<p class="flxi-muted">' . esc_html( sprintf( __( 'Son konu: %s', 'flx-mail' ), $disc['subject'] ) ) . '</p>';
	}
	echo '<div class="flxi-inline">';
	if ( ! $type || $type['can_disable'] ) {
		echo flxi_switch( 'enabled', $conf['enabled'], __( 'Bu e-posta gönderilsin', 'flx-mail' ) ); // phpcs:ignore
	} else {
		echo '<span class="flxi-badge"><span class="dashicons dashicons-lock"></span> ' . esc_html__( 'Her zaman gönderilir', 'flx-mail' ) . '</span><input type="hidden" name="enabled" value="1">';
	}
	echo '</div>';

	echo '<label class="flxi-field"><span>' . esc_html__( 'Kimden gitsin', 'flx-mail' ) . '</span><select name="sender" data-preview>';
	printf( '<option value="info"%s>%s</option>', selected( $sender, 'info', false ), esc_html( flxi_setting( 'from_name' ) . ' <' . flxi_setting( 'from_email' ) . '>' ) );
	printf( '<option value="noreply"%s>%s</option>', selected( $sender, 'noreply', false ), esc_html( flxi_setting( 'noreply_name' ) . ' <' . flxi_setting( 'noreply_email' ) . '> · ' . __( 'yanıt beklenmez', 'flx-mail' ) ) );
	echo '</select></label>';

	if ( $editable ) {
		$f = function ( $name, $label, $value, $hint = '' ) {
			echo '<label class="flxi-field"><span>' . esc_html( $label ) . '</span><input type="text" name="' . esc_attr( $name ) . '" value="' . esc_attr( $value ) . '" data-preview data-insert-target>' . ( $hint ? '<small>' . esc_html( $hint ) . '</small>' : '' ) . '</label>';
		};
		$f( 'subject', __( 'Konu', 'flx-mail' ), $conf['subject'] );
		$f( 'heading', __( 'Başlık', 'flx-mail' ), $conf['heading'], __( 'E-postanın içindeki büyük başlık.', 'flx-mail' ) );
		echo '<div class="flxi-field"><span>' . esc_html__( 'İçerik', 'flx-mail' ) . '</span>';
		wp_editor(
			$conf['body'],
			'flxi_body',
			array(
				'textarea_name' => 'body',
				'textarea_rows' => 10,
				'media_buttons' => false,
				'teeny'         => true,
				'quicktags'     => array( 'buttons' => 'strong,em,link,ul,ol,li,close' ),
				'tinymce'       => array( 'toolbar1' => 'bold,italic,link,unlink,bullist,numlist,blockquote,undo,redo', 'block_formats' => 'Paragraf=p;Başlık=h2' ),
			)
		);
		echo '</div>';
		echo '<div class="flxi-grid2">';
		$f( 'button_text', __( 'Düğme yazısı', 'flx-mail' ), $conf['button_text'], __( 'Boşsa düğme gösterilmez.', 'flx-mail' ) );
		$f( 'button_url', __( 'Düğme bağlantısı', 'flx-mail' ), $conf['button_url'] );
		echo '</div>';
		if ( $type['vars'] ) {
			echo '<div class="flxi-vars"><span>' . esc_html__( 'Değişkenler (tıklayınca imlecin olduğu yere eklenir)', 'flx-mail' ) . '</span><div class="flxi-chips">';
			$vars = array_merge( (array) $type['vars'], array( 'site' => __( 'Site adı', 'flx-mail' ), 'kurum' => __( 'Kurum adı', 'flx-mail' ), 'kurum_eposta' => __( 'Kurum e-postası', 'flx-mail' ), 'kurum_telefon' => __( 'Kurum telefonu', 'flx-mail' ) ) );
			foreach ( $vars as $v => $desc ) {
				echo '<button type="button" class="flxi-chip" data-var="{' . esc_attr( $v ) . '}" title="' . esc_attr( $desc ) . '">{' . esc_html( $v ) . '}</button>';
			}
			echo '</div></div>';
		}
	} else {
		echo flxi_switch( 'wrap', $conf['wrap'], __( 'Kurumsal şablonla sar (logo, kurum bilgileri). Kendi tasarımı olan HTML e-postalara dokunulmaz.', 'flx-mail' ) ); // phpcs:ignore
	}

	echo '<div class="flxi-actions"><button type="submit" class="button button-primary button-large">' . esc_html__( 'Kaydet', 'flx-mail' ) . '</button>';
	if ( $editable ) {
		echo ' <button type="button" class="button button-large" data-test-template="' . esc_attr( $key ) . '">' . esc_html__( 'Bana test gönder', 'flx-mail' ) . '</button>';
		echo ' <button type="submit" name="reset" value="1" class="button-link flxi-link-danger" onclick="return confirm(this.dataset.confirm)" data-confirm="' . esc_attr__( 'İçerik varsayılana dönsün mü?', 'flx-mail' ) . '">' . esc_html__( 'Varsayılana dön', 'flx-mail' ) . '</button>';
	}
	echo '<span class="flxi-status" aria-live="polite"></span></div>';
	echo '</div></div>';

	if ( $editable ) {
		echo '<div class="flxi-editor__preview"><div class="flxi-preview">';
		echo '<div class="flxi-preview__bar"><div class="flxi-preview__subject"><span class="flxi-muted">' . esc_html__( 'Konu:', 'flx-mail' ) . '</span> <strong data-preview-subject></strong></div>';
		echo '<div class="flxi-seg" role="group" aria-label="' . esc_attr__( 'Önizleme boyutu', 'flx-mail' ) . '"><button type="button" class="is-active" data-device="desktop" aria-pressed="true"><span class="dashicons dashicons-desktop"></span><span class="screen-reader-text">' . esc_html__( 'Masaüstü', 'flx-mail' ) . '</span></button><button type="button" data-device="mobile" aria-pressed="false"><span class="dashicons dashicons-smartphone"></span><span class="screen-reader-text">' . esc_html__( 'Telefon', 'flx-mail' ) . '</span></button><button type="button" data-device="dark" aria-pressed="false"><span class="dashicons dashicons-admin-appearance"></span><span class="screen-reader-text">' . esc_html__( 'Koyu mod', 'flx-mail' ) . '</span></button></div></div>';
		echo '<div class="flxi-preview__frame"><iframe title="' . esc_attr__( 'E-posta önizlemesi', 'flx-mail' ) . '" data-preview-frame sandbox="allow-same-origin"></iframe></div>';
		echo '<p class="flxi-muted flxi-preview__note">' . esc_html__( 'Önizlemede örnek değerler kullanılır.', 'flx-mail' ) . '</p>';
		echo '</div></div>';
	}
	echo '</form>';
	flxi_page_foot();
}

add_action(
	'admin_post_flxi_toggle_template',
	function () {
		flxi_check( 'flxi_toggle_template' );
		$key = sanitize_text_field( wp_unslash( $_POST['key'] ?? '' ) );
		$on  = ! empty( $_POST['enabled'] );
		if ( 0 === strpos( $key, 'wc/' ) ) {
			flxi_wc_set_enabled( substr( $key, 3 ), $on );
		} else {
			$type = flxi_email_types()[ $key ] ?? null;
			if ( $type && ! $type['can_disable'] ) {
				$on = true;
			}
			flxi_save_template( $key, array( 'enabled' => $on ) );
		}
		flxi_redirect( flxi_back( flxi_admin_url( 'flxi' ) ), $on ? __( 'E-posta açıldı.', 'flx-mail' ) : __( 'E-posta kapatıldı; gönderilmeyecek.', 'flx-mail' ) );
	}
);

add_action(
	'admin_post_flxi_save_template',
	function () {
		flxi_check( 'flxi_save_template' );
		$key  = sanitize_text_field( wp_unslash( $_POST['key'] ?? '' ) );
		$type = flxi_email_types()[ $key ] ?? null;
		$back = flxi_admin_url( 'flxi', array( 'key' => $key ) );
		if ( ! empty( $_POST['reset'] ) ) {
			flxi_reset_template( $key );
			flxi_redirect( $back, __( 'Varsayılan içerik geri yüklendi.', 'flx-mail' ) );
		}
		$data = array(
			'enabled' => ! empty( $_POST['enabled'] ) || ( $type && ! $type['can_disable'] ),
			'sender'  => sanitize_key( wp_unslash( $_POST['sender'] ?? '' ) ),
		);
		if ( $type && $type['editable'] ) {
			foreach ( array( 'subject', 'heading', 'body', 'button_text', 'button_url' ) as $f ) {
				$v = wp_unslash( $_POST[ $f ] ?? '' ); // phpcs:ignore
				// Varsayılanla aynıysa kaydetme (varsayılan metin çevirisi değişince güncel kalsın).
				$data[ $f ] = trim( (string) $v ) === trim( (string) $type['defaults'][ $f ] ) ? '' : $v;
			}
		} else {
			$data['wrap'] = ! empty( $_POST['wrap'] );
		}
		if ( 0 === strpos( $key, 'wc/' ) ) {
			flxi_wc_set_enabled( substr( $key, 3 ), $data['enabled'] );
		}
		flxi_save_template( $key, $data );
		flxi_redirect( $back, __( 'Kaydedildi.', 'flx-mail' ) );
	}
);
