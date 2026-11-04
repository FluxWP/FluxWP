<?php
/**
 * CRM › Segmentler: kayıtlı dinamik listeler ve kural düzenleyici.
 *
 * @package FLX_CRM
 */

defined( 'ABSPATH' ) || exit;

final class FLXCRM_Page_Segments {

	public static function render() {
		if ( ! flxcrm_can() ) {
			wp_die( esc_html__( 'Bu işlem için yetkiniz yok.', 'flx-crm' ), 403 );
		}
		$edit = isset( $_GET['edit'] ) ? sanitize_key( $_GET['edit'] ) : ''; // phpcs:ignore WordPress.Security.NonceVerification
		if ( '' !== $edit && flxcrm_can( true ) ) {
			self::editor( 'new' === $edit ? null : FLXCRM_Segments::get( absint( $edit ) ) );
			return;
		}
		$can     = flxcrm_can( true );
		$actions = $can ? '<a class="button button-primary" href="' . esc_url( flxcrm_admin_url( 'segments', array( 'edit' => 'new' ) ) ) . '">' . flxcrm_icon( 'plus', 15 ) . ' ' . esc_html__( 'Yeni segment', 'flx-crm' ) . '</a>' : '';
		FLXCRM_Admin::open( __( 'Segmentler', 'flx-crm' ), __( 'Kurallarla tanımlanan canlı listeler: kişi kurala uyunca girer, uymayınca çıkar. Flux Mail\'de toplu gönderimin alıcısı olur.', 'flx-crm' ), $actions );

		$list = FLXCRM_Segments::all();
		if ( ! $list ) {
			echo '<div class="flxcrm-card flxcrm-empty-state">' . flxcrm_icon( 'layers', 28 ) . '<p><strong>' . esc_html__( 'Henüz segment yok.', 'flx-crm' ) . '</strong></p><p class="flxcrm-muted">' . esc_html__( 'Örnek: "Geçen yıl kurban bağışı yapıp bu yıl yapmayanlar", "İstanbul\'daki gönüllüler", "3 aydır alışveriş yapmayan müşteriler".', 'flx-crm' ) . '</p></div>'; // phpcs:ignore WordPress.Security.EscapeOutput
			FLXCRM_Admin::close();
			return;
		}
		$mail = flxcrm_mail_send_url();
		echo '<div class="flxcrm-seglist">';
		foreach ( $list as $s ) {
			echo '<article class="flxcrm-card flxcrm-segcard"><div class="flxcrm-segcard__head"><div><h2><a href="' . esc_url( flxcrm_admin_url( 'contacts', array( 'sid' => (int) $s['id'] ) ) ) . '">' . esc_html( $s['name'] ) . '</a></h2>';
			if ( '' !== $s['description'] ) {
				echo '<p class="flxcrm-muted">' . esc_html( $s['description'] ) . '</p>';
			}
			echo '</div><strong class="flxcrm-segcard__count">' . esc_html( number_format_i18n( (int) $s['count'] ) ) . '<small>' . esc_html__( 'kişi', 'flx-crm' ) . '</small></strong></div>';
			echo '<p class="flxcrm-rule-sum">' . flxcrm_icon( 'filter', 14 ) . esc_html( FLXCRM_Segments::describe( $s['rules'] ) ) . '</p>'; // phpcs:ignore WordPress.Security.EscapeOutput
			echo '<div class="flxcrm-segcard__actions"><a class="button button-small" href="' . esc_url( flxcrm_admin_url( 'contacts', array( 'sid' => (int) $s['id'] ) ) ) . '">' . esc_html__( 'Kişileri gör', 'flx-crm' ) . '</a>';
			if ( $can ) {
				echo '<a class="button button-small" href="' . esc_url( flxcrm_admin_url( 'segments', array( 'edit' => (int) $s['id'] ) ) ) . '">' . esc_html__( 'Düzenle', 'flx-crm' ) . '</a>';
				if ( $mail ) {
					echo '<a class="button button-small" href="' . esc_url( $mail ) . '" title="' . esc_attr__( 'Flux Mail › Gönder: Alıcılar altında bu segmenti seçin. Yalnız ilgili kanala izin verenler alıcı olur.', 'flx-crm' ) . '">' . flxcrm_icon( 'send', 14 ) . ' ' . esc_html__( 'Gönderim yap', 'flx-crm' ) . '</a>'; // phpcs:ignore WordPress.Security.EscapeOutput
				}
				echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '" class="flxcrm-inline">' . wp_nonce_field( 'flxcrm_csv', '_wpnonce', true, false ) . '<input type="hidden" name="action" value="flxcrm_csv"><input type="hidden" name="segment_id" value="' . (int) $s['id'] . '"><button class="button button-small">' . esc_html__( 'CSV', 'flx-crm' ) . '</button></form>'; // phpcs:ignore WordPress.Security.EscapeOutput
				echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '" class="flxcrm-inline" data-flxcrm-confirm>' . wp_nonce_field( 'flxcrm_segment_delete', '_wpnonce', true, false ) . '<input type="hidden" name="action" value="flxcrm_segment_delete"><input type="hidden" name="id" value="' . (int) $s['id'] . '"><button class="button-link button-link-delete">' . esc_html__( 'Sil', 'flx-crm' ) . '</button></form>'; // phpcs:ignore WordPress.Security.EscapeOutput
			}
			echo '</div></article>';
		}
		echo '</div>';
		FLXCRM_Admin::close();
	}

	/** Kural düzenleyicinin seçenekleri (JS). */
	private static function schema() {
		$opts = array(
			'role'    => array(),
			'tag'     => array(),
			'rfm'     => array(),
			'consent' => array(),
			'source'  => array(),
		);
		foreach ( flxcrm_roles() as $k => $r ) {
			$opts['role'][] = array( $k, $r['label'] );
		}
		foreach ( FLXCRM_Tags::all() as $t ) {
			$opts['tag'][] = array( (string) $t['id'], $t['name'] );
		}
		foreach ( flxcrm_rfm_segments() as $k => $s ) {
			$opts['rfm'][] = array( $k, $s[0] );
		}
		$opts['rfm'][] = array( 'none', __( 'Ödemesi yok', 'flx-crm' ) );
		foreach ( FLXCRM_Contacts::consent_labels() as $k => $l ) {
			$opts['consent'][] = array( $k, $l );
		}
		foreach ( flxcrm_sources() as $k => $l ) {
			$opts['source'][] = array( $k, $l );
		}
		$fields = array();
		foreach ( FLXCRM_Segments::fields() as $k => $f ) {
			$fields[ $k ] = array( 'label' => $f[0], 'type' => $f[1], 'ops' => $f[2] );
		}
		return array( 'fields' => $fields, 'ops' => FLXCRM_Segments::op_labels(), 'options' => $opts );
	}

	private static function editor( $s ) {
		$is_new = ! $s;
		$s      = $s ? $s : array( 'id' => 0, 'name' => '', 'description' => '', 'rules' => array( 'match' => 'all', 'rules' => array( array( 'field' => 'role', 'op' => 'has', 'value' => 'donor' ) ) ) );
		FLXCRM_Admin::open( $is_new ? __( 'Yeni segment', 'flx-crm' ) : __( 'Segmenti düzenle', 'flx-crm' ), __( 'Kuralları ekleyin; sağda kaç kişinin uyduğunu anında görürsünüz.', 'flx-crm' ) );
		echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '" class="flxcrm-segedit" data-flxcrm-segedit>';
		wp_nonce_field( 'flxcrm_segment_save' );
		echo '<input type="hidden" name="action" value="flxcrm_segment_save"><input type="hidden" name="id" value="' . (int) $s['id'] . '">';
		echo '<input type="hidden" name="rules_json" value="' . esc_attr( wp_json_encode( $s['rules'] ) ) . '" data-flxcrm-rules>';
		echo '<script type="application/json" data-flxcrm-schema>' . wp_json_encode( self::schema() ) . '</script>'; // phpcs:ignore WordPress.Security.EscapeOutput
		echo '<div class="flxcrm-segedit__main"><div class="flxcrm-card"><div class="flxcrm-form">';
		echo '<label><span>' . esc_html__( 'Ad', 'flx-crm' ) . '</span><input type="text" name="name" value="' . esc_attr( $s['name'] ) . '" required placeholder="' . esc_attr__( 'Örn. Uzaklaşan bağışçılar', 'flx-crm' ) . '"></label>';
		echo '<label><span>' . esc_html__( 'Açıklama', 'flx-crm' ) . '</span><input type="text" name="description" value="' . esc_attr( $s['description'] ) . '"></label>';
		echo '</div></div>';
		echo '<div class="flxcrm-card"><div class="flxcrm-card__head"><h2>' . flxcrm_icon( 'filter', 16 ) . esc_html__( 'Kurallar', 'flx-crm' ) . '</h2>'; // phpcs:ignore WordPress.Security.EscapeOutput
		echo '<label class="flxcrm-match">' . esc_html__( 'Kişi', 'flx-crm' ) . ' <select data-flxcrm-match><option value="all"' . selected( $s['rules']['match'] ?? 'all', 'all', false ) . '>' . esc_html__( 'tüm kurallara', 'flx-crm' ) . '</option><option value="any"' . selected( $s['rules']['match'] ?? 'all', 'any', false ) . '>' . esc_html__( 'en az birine', 'flx-crm' ) . '</option></select> ' . esc_html__( 'uysun', 'flx-crm' ) . '</label></div>';
		echo '<ol class="flxcrm-rules" data-flxcrm-rulelist></ol>';
		echo '<button type="button" class="button" data-flxcrm-addrule>' . flxcrm_icon( 'plus', 14 ) . ' ' . esc_html__( 'Kural ekle', 'flx-crm' ) . '</button></div>'; // phpcs:ignore WordPress.Security.EscapeOutput
		echo '<p class="flxcrm-actions"><button class="button button-primary">' . esc_html__( 'Segmenti kaydet', 'flx-crm' ) . '</button> <a class="button" href="' . esc_url( flxcrm_admin_url( 'segments' ) ) . '">' . esc_html__( 'Vazgeç', 'flx-crm' ) . '</a></p></div>';
		echo '<aside class="flxcrm-card flxcrm-segedit__side" aria-live="polite"><span class="flxcrm-kpi__label">' . esc_html__( 'Uyan kişi', 'flx-crm' ) . '</span><strong class="flxcrm-preview__count" data-flxcrm-count>…</strong><p class="flxcrm-muted" data-flxcrm-summary></p><ul class="flxcrm-preview__list" data-flxcrm-sample></ul></aside>';
		echo '</form>';
		FLXCRM_Admin::close();
	}
}
