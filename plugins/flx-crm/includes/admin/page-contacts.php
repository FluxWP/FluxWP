<?php
/**
 * CRM › Kişiler (liste, filtreler, toplu işlem, CSV).
 *
 * @package FLX_CRM
 */

defined( 'ABSPATH' ) || exit;

final class FLXCRM_Page_Contacts {

	/** GET/POST dizisinden liste filtresi. */
	public static function filters_from( array $src ) {
		$f = array(
			's'       => isset( $src['s'] ) ? sanitize_text_field( (string) $src['s'] ) : '',
			'role'    => isset( $src['role'] ) ? sanitize_key( (string) $src['role'] ) : '',
			'segment' => isset( $src['segment'] ) ? sanitize_key( (string) $src['segment'] ) : '',
			'tag'     => isset( $src['tag'] ) ? absint( $src['tag'] ) : 0,
			'consent' => isset( $src['consent'] ) ? sanitize_key( (string) $src['consent'] ) : '',
			'risk'    => ! empty( $src['risk'] ),
			'status'  => isset( $src['status'] ) && 'archived' === $src['status'] ? 'archived' : 'active',
			'orderby' => isset( $src['orderby'] ) ? sanitize_key( (string) $src['orderby'] ) : 'last_activity_at',
			'order'   => isset( $src['order'] ) ? sanitize_key( (string) $src['order'] ) : 'desc',
		);
		$sid = isset( $src['sid'] ) ? absint( $src['sid'] ) : 0;
		if ( $sid ) {
			$s = FLXCRM_Segments::get( $sid );
			if ( $s ) {
				$f['rules'] = $s['rules'];
				$f['sid']   = $sid;
			}
		}
		return $f;
	}

	public static function render() {
		if ( ! flxcrm_can() ) {
			wp_die( esc_html__( 'Bu işlem için yetkiniz yok.', 'flx-crm' ), 403 );
		}
		$f        = self::filters_from( wp_unslash( $_GET ) ); // phpcs:ignore WordPress.Security
		$per      = function_exists( 'flxa_list_per_page' ) ? flxa_list_per_page( 'flxcrm-contacts', 25 ) : 25;
		$paged    = isset( $_GET['paged'] ) ? max( 1, absint( $_GET['paged'] ) ) : 1; // phpcs:ignore WordPress.Security.NonceVerification
		$q        = FLXCRM_Contacts::query( array_merge( $f, array( 'per_page' => $per, 'paged' => $paged ) ) );
		$can      = flxcrm_can( true );
		$roles    = flxcrm_roles();
		$stats    = FLXCRM_Contacts::stats();
		$segments = FLXCRM_Segments::all();
		$tags     = FLXCRM_Tags::all( true );
		$keep     = array_filter(
			array(
				's'       => $f['s'],
				'role'    => $f['role'],
				'segment' => $f['segment'],
				'tag'     => $f['tag'] ? $f['tag'] : '',
				'consent' => $f['consent'],
				'risk'    => $f['risk'] ? 1 : '',
				'status'  => 'archived' === $f['status'] ? 'archived' : '',
				'sid'     => $f['sid'] ?? '',
			)
		);

		$actions = '';
		if ( $can ) {
			$actions .= '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '" class="flxcrm-inline">' . wp_nonce_field( 'flxcrm_csv', '_wpnonce', true, false ) . '<input type="hidden" name="action" value="flxcrm_csv">';
			foreach ( $keep as $k => $v ) {
				$actions .= '<input type="hidden" name="' . esc_attr( 'sid' === $k ? 'segment_id' : $k ) . '" value="' . esc_attr( (string) $v ) . '">';
			}
			$actions .= '<button class="button">' . flxcrm_icon( 'download', 15 ) . ' ' . esc_html__( 'CSV', 'flx-crm' ) . '</button></form>';
			$actions .= '<a class="button button-primary" href="' . esc_url( flxcrm_admin_url( 'contacts', array( 'action' => 'new' ) ) ) . '">' . flxcrm_icon( 'plus', 15 ) . ' ' . esc_html__( 'Kişi ekle', 'flx-crm' ) . '</a>';
		}
		$title = __( 'Kişiler', 'flx-crm' );
		$lead  = __( 'Bağışçılar, müşteriler, adaylar: her kişi tek kart. Kaynaklardan kendiliğinden gelir, elle de eklenir.', 'flx-crm' );
		if ( ! empty( $f['sid'] ) ) {
			$sg    = FLXCRM_Segments::get( $f['sid'] );
			$title = $sg['name'];
			$lead  = FLXCRM_Segments::describe( $sg['rules'] );
		}
		FLXCRM_Admin::open( $title, $lead, $actions );

		/* Rol çipleri */
		$base = array_diff_key( $keep, array( 'role' => 1 ) );
		echo '<div class="flxcrm-chips" role="navigation" aria-label="' . esc_attr__( 'Rol', 'flx-crm' ) . '">';
		$on = '' === $f['role'];
		echo '<a class="flxcrm-chip' . ( $on ? ' is-on' : '' ) . '" href="' . esc_url( flxcrm_admin_url( 'contacts', $base ) ) . '"' . ( $on ? ' aria-current="true"' : '' ) . '>' . esc_html__( 'Tümü', 'flx-crm' ) . ' <span>' . esc_html( number_format_i18n( $stats['total'] ) ) . '</span></a>';
		foreach ( $roles as $k => $r ) {
			$n = $stats['roles'][ $k ] ?? 0;
			if ( ! $n && $k !== $f['role'] ) {
				continue;
			}
			$on = $k === $f['role'];
			echo '<a class="flxcrm-chip' . ( $on ? ' is-on' : '' ) . '" style="--rc:' . esc_attr( $r['color'] ) . '" href="' . esc_url( flxcrm_admin_url( 'contacts', array_merge( $base, array( 'role' => $k ) ) ) ) . '"' . ( $on ? ' aria-current="true"' : '' ) . '><i></i>' . esc_html( $r['label'] ) . ' <span>' . esc_html( number_format_i18n( $n ) ) . '</span></a>';
		}
		echo '</div>';

		/* Filtre formu (Flux Admin liste API'si varsa onun düzeni) */
		$attrs = function_exists( 'flxa_list_form_attrs' ) ? flxa_list_form_attrs( array( 'key' => 'flxcrm-contacts' ) ) : '';
		echo '<form method="get" class="flxcrm-filters"' . $attrs . '>'; // phpcs:ignore WordPress.Security.EscapeOutput
		echo '<input type="hidden" name="page" value="flx-crm-contacts">';
		if ( '' !== $f['role'] ) {
			echo '<input type="hidden" name="role" value="' . esc_attr( $f['role'] ) . '">';
		}
		echo '<input type="search" name="s" value="' . esc_attr( $f['s'] ) . '" placeholder="' . esc_attr__( 'Ad, e-posta, telefon, kurum, şehir', 'flx-crm' ) . '" aria-label="' . esc_attr__( 'Kişi ara', 'flx-crm' ) . '">';
		echo '<select name="segment" data-flxa-primary aria-label="' . esc_attr__( 'RFM segmenti', 'flx-crm' ) . '"><option value="">' . esc_html__( 'Tüm RFM segmentleri', 'flx-crm' ) . '</option>';
		foreach ( flxcrm_rfm_segments() as $k => $s ) {
			echo '<option value="' . esc_attr( $k ) . '"' . selected( $f['segment'], $k, false ) . '>' . esc_html( $s[0] ) . '</option>';
		}
		echo '<option value="none"' . selected( $f['segment'], 'none', false ) . '>' . esc_html__( 'Ödemesi yok', 'flx-crm' ) . '</option></select>';
		echo '<select name="sid" data-flxa-primary aria-label="' . esc_attr__( 'Kayıtlı segment', 'flx-crm' ) . '"><option value="">' . esc_html__( 'Kayıtlı segment', 'flx-crm' ) . '</option>';
		foreach ( $segments as $sg ) {
			echo '<option value="' . (int) $sg['id'] . '"' . selected( (int) ( $f['sid'] ?? 0 ), (int) $sg['id'], false ) . '>' . esc_html( $sg['name'] ) . '</option>';
		}
		echo '</select>';
		echo '<select name="tag" aria-label="' . esc_attr__( 'Etiket', 'flx-crm' ) . '"><option value="">' . esc_html__( 'Tüm etiketler', 'flx-crm' ) . '</option>';
		foreach ( $tags as $t ) {
			echo '<option value="' . (int) $t['id'] . '"' . selected( $f['tag'], (int) $t['id'], false ) . '>' . esc_html( $t['name'] . ' (' . number_format_i18n( (int) $t['count'] ) . ')' ) . '</option>';
		}
		echo '</select>';
		echo '<select name="consent" aria-label="' . esc_attr__( 'İzin', 'flx-crm' ) . '"><option value="">' . esc_html__( 'İzin fark etmez', 'flx-crm' ) . '</option>';
		foreach ( FLXCRM_Contacts::consent_labels() as $k => $l ) {
			/* translators: %s: channel */
			echo '<option value="' . esc_attr( $k ) . '"' . selected( $f['consent'], $k, false ) . '>' . esc_html( sprintf( __( '%s izni var', 'flx-crm' ), $l ) ) . '</option>';
		}
		echo '</select>';
		echo '<label class="flxcrm-check"><input type="checkbox" name="risk" value="1"' . checked( $f['risk'], true, false ) . '> ' . esc_html__( 'Yalnız kayıp riski yüksek', 'flx-crm' ) . '</label>';
		echo '<label class="flxcrm-check"><input type="checkbox" name="status" value="archived"' . checked( $f['status'], 'archived', false ) . '> ' . esc_html__( 'Arşiv', 'flx-crm' ) . '</label>';
		echo '<button class="button">' . esc_html__( 'Filtrele', 'flx-crm' ) . '</button></form>';

		if ( ! $q['rows'] ) {
			$filtered = (bool) array_diff_key( $keep, array( 'status' => 1 ) );
			echo '<div class="flxcrm-card flxcrm-empty-state">' . flxcrm_icon( 'users', 28 ) . '<p><strong>' . esc_html( $filtered ? __( 'Filtreye uyan kişi yok.', 'flx-crm' ) : __( 'Henüz kişi yok.', 'flx-crm' ) ) . '</strong></p>'; // phpcs:ignore WordPress.Security.EscapeOutput
			if ( ! $filtered && $can ) {
				echo '<p class="flxcrm-muted">' . esc_html__( 'Bağış, sipariş, form ya da WhatsApp sohbeti geldikçe kişiler kendiliğinden oluşur. Geçmiş kayıtlar için Ayarlar › Tümünü eşitle.', 'flx-crm' ) . '</p><p><a class="button" href="' . esc_url( flxcrm_admin_url( 'settings' ) ) . '">' . esc_html__( 'Ayarlar', 'flx-crm' ) . '</a></p>';
			}
			echo '</div>';
			FLXCRM_Admin::close();
			return;
		}

		$sort = static function ( $col, $label ) use ( $f, $keep ) {
			$on    = $f['orderby'] === $col;
			$order = $on && 'desc' === $f['order'] ? 'asc' : 'desc';
			$arrow = $on ? ( 'desc' === $f['order'] ? ' ↓' : ' ↑' ) : '';
			return '<a href="' . esc_url( flxcrm_admin_url( 'contacts', array_merge( $keep, array( 'orderby' => $col, 'order' => $order ) ) ) ) . '">' . esc_html( $label . $arrow ) . '</a>';
		};

		$tag_map = FLXCRM_Tags::of_many( wp_list_pluck( $q['rows'], 'id' ) );
		echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '" data-flxcrm-bulk>';
		wp_nonce_field( 'flxcrm_bulk' );
		echo '<input type="hidden" name="action" value="flxcrm_bulk"><input type="hidden" name="back" value="' . esc_attr( flxcrm_admin_url( 'contacts', $keep ) ) . '">';
		if ( $can ) {
			echo '<div class="flxcrm-bulk" data-flxcrm-bulkbar hidden><span data-flxcrm-selcount></span>';
			echo '<select name="bulk_action" data-flxcrm-bulkaction aria-label="' . esc_attr__( 'Toplu işlem', 'flx-crm' ) . '"><option value="tag">' . esc_html__( 'Etiket ekle', 'flx-crm' ) . '</option><option value="role">' . esc_html__( 'Rol ekle', 'flx-crm' ) . '</option>';
			echo 'archived' === $f['status'] ? '<option value="restore">' . esc_html__( 'Arşivden çıkar', 'flx-crm' ) . '</option>' : '<option value="archive">' . esc_html__( 'Arşivle', 'flx-crm' ) . '</option>';
			echo '<option value="delete">' . esc_html__( 'Sil', 'flx-crm' ) . '</option></select>';
			echo '<input type="text" name="bulk_value" list="flxcrm-tags" data-flxcrm-bulkvalue="tag" placeholder="' . esc_attr__( 'Etiket adı', 'flx-crm' ) . '" aria-label="' . esc_attr__( 'Etiket adı', 'flx-crm' ) . '">';
			echo '<select name="bulk_value" data-flxcrm-bulkvalue="role" disabled hidden aria-label="' . esc_attr__( 'Rol', 'flx-crm' ) . '">';
			foreach ( $roles as $k => $r ) {
				echo '<option value="' . esc_attr( $k ) . '">' . esc_html( $r['label'] ) . '</option>';
			}
			echo '</select><button class="button" data-flxcrm-bulkgo>' . esc_html__( 'Uygula', 'flx-crm' ) . '</button></div>';
			echo '<datalist id="flxcrm-tags">';
			foreach ( $tags as $t ) {
				echo '<option value="' . esc_attr( $t['name'] ) . '">';
			}
			echo '</datalist>';
		}
		echo '<div class="flxcrm-card flxcrm-card--flush"><table class="flxcrm-table flxcrm-table--contacts"><thead><tr>';
		if ( $can ) {
			echo '<th class="flxcrm-col-check"><input type="checkbox" data-flxcrm-checkall aria-label="' . esc_attr__( 'Tümünü seç', 'flx-crm' ) . '"></th>';
		}
		echo '<th>' . $sort( 'name', __( 'Kişi', 'flx-crm' ) ) . '</th>'; // phpcs:ignore WordPress.Security.EscapeOutput
		echo '<th>' . esc_html__( 'Rol ve segment', 'flx-crm' ) . '</th>';
		echo '<th class="num">' . $sort( 'ltv', __( 'Değer', 'flx-crm' ) ) . '</th>'; // phpcs:ignore WordPress.Security.EscapeOutput
		echo '<th>' . $sort( 'churn', __( 'Kayıp riski', 'flx-crm' ) ) . '</th>'; // phpcs:ignore WordPress.Security.EscapeOutput
		echo '<th>' . $sort( 'last_activity_at', __( 'Son hareket', 'flx-crm' ) ) . '</th>'; // phpcs:ignore WordPress.Security.EscapeOutput
		echo '</tr></thead><tbody>';
		foreach ( $q['rows'] as $c ) {
			$url = flxcrm_contact_url( $c['id'] );
			echo '<tr>';
			if ( $can ) {
				echo '<td class="flxcrm-col-check"><input type="checkbox" name="ids[]" value="' . (int) $c['id'] . '" aria-label="' . esc_attr( flxcrm_display_name( $c ) ) . '"></td>';
			}
			echo '<td data-label="' . esc_attr__( 'Kişi', 'flx-crm' ) . '"><a class="flxcrm-who" href="' . esc_url( $url ) . '">' . FLXCRM_Admin::avatar( $c ) . '<span><strong>' . esc_html( flxcrm_display_name( $c ) ) . '</strong><small>'; // phpcs:ignore WordPress.Security.EscapeOutput
			$bits = array();
			if ( $c['email'] ) {
				$bits[] = esc_html( $c['email'] );
			}
			if ( $c['phone'] ) {
				$bits[] = esc_html( flxcrm_phone_display( $c['phone'] ) );
			}
			if ( $c['city'] ) {
				$bits[] = esc_html( $c['city'] );
			}
			echo implode( ' · ', $bits ) . '</small>'; // phpcs:ignore WordPress.Security.EscapeOutput
			if ( ! empty( $tag_map[ (int) $c['id'] ] ) ) {
				echo '<span class="flxcrm-tags">';
				foreach ( array_slice( $tag_map[ (int) $c['id'] ], 0, 3 ) as $t ) {
					echo '<span class="flxcrm-tag" style="--tc:' . esc_attr( $t['color'] ) . '">' . esc_html( $t['name'] ) . '</span>';
				}
				echo '</span>';
			}
			echo '</span></a></td>';
			echo '<td data-label="' . esc_attr__( 'Rol ve segment', 'flx-crm' ) . '"><span class="flxcrm-pills">' . FLXCRM_Admin::role_pills( $c['roles'] ) . ( (int) $c['paid_days'] ? FLXCRM_Admin::segment_badge( $c['segment'] ) : '' ) . '</span></td>'; // phpcs:ignore WordPress.Security.EscapeOutput
			echo '<td class="num" data-label="' . esc_attr__( 'Değer', 'flx-crm' ) . '">' . ( (float) $c['ltv'] > 0 ? '<strong>' . esc_html( flxcrm_money( $c['ltv'] ) ) . '</strong><small>' . esc_html( self::counts_label( $c ) ) . '</small>' : '<span class="flxcrm-muted">—</span>' ) . '</td>';
			echo '<td data-label="' . esc_attr__( 'Kayıp riski', 'flx-crm' ) . '">' . FLXCRM_Admin::churn_meter( (int) $c['churn'], (int) $c['paid_days'] > 0 ) . '</td>'; // phpcs:ignore WordPress.Security.EscapeOutput
			echo '<td data-label="' . esc_attr__( 'Son hareket', 'flx-crm' ) . '"><span title="' . esc_attr( flxcrm_date( $c['last_activity_at'], 'd.m.Y H:i' ) ) . '">' . esc_html( flxcrm_ago( $c['last_activity_at'] ) ) . '</span></td>';
			echo '</tr>';
		}
		echo '</tbody></table></div></form>';
		FLXCRM_Admin::pagination( $q['total'], $per, $q['paged'] );
		FLXCRM_Admin::close();
	}

	public static function counts_label( array $c ) {
		$p = array();
		if ( (int) $c['donations'] ) {
			/* translators: %s: count */
			$p[] = sprintf( _n( '%s bağış', '%s bağış', (int) $c['donations'], 'flx-crm' ), number_format_i18n( (int) $c['donations'] ) );
		}
		if ( (int) $c['orders'] ) {
			/* translators: %s: count */
			$p[] = sprintf( _n( '%s sipariş', '%s sipariş', (int) $c['orders'], 'flx-crm' ), number_format_i18n( (int) $c['orders'] ) );
		}
		return implode( ' · ', $p );
	}
}
