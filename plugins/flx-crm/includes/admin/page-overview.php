<?php
/**
 * CRM › Genel bakış.
 *
 * @package FLX_CRM
 */

defined( 'ABSPATH' ) || exit;

final class FLXCRM_Page_Overview {

	public static function render() {
		if ( ! flxcrm_can() ) {
			wp_die( esc_html__( 'Bu işlem için yetkiniz yok.', 'flx-crm' ), 403 );
		}
		$s       = FLXCRM_Contacts::stats();
		$can     = flxcrm_can( true );
		$actions = '';
		if ( $can ) {
			$actions .= '<a class="button button-primary" href="' . esc_url( flxcrm_admin_url( 'contacts', array( 'action' => 'new' ) ) ) . '">' . flxcrm_icon( 'plus', 15 ) . ' ' . esc_html__( 'Kişi ekle', 'flx-crm' ) . '</a>';
		}
		FLXCRM_Admin::open( __( 'CRM', 'flx-crm' ), __( 'Bağışçı, müşteri, gönüllü ve adaylar tek yerde: kim ne yaptı, kim uzaklaşıyor, kime ne gönderilmeli.', 'flx-crm' ), $actions );

		if ( ! get_option( 'flxcrm_synced_at' ) && $can ) {
			$names = array();
			foreach ( FLXCRM_Sync::sources() as $src ) {
				$names[] = $src[0];
			}
			echo '<div class="flxcrm-callout">' . flxcrm_icon( 'refresh', 18 ) . '<div><strong>' . esc_html__( 'Geçmiş veriyi içe alın.', 'flx-crm' ) . '</strong> ';
			/* translators: %s: source list */
			echo esc_html( $names ? sprintf( __( 'Yeni kayıtlar kendiliğinden geliyor. Önceki kayıtları da kişilere bağlamak için eşitleyin: %s.', 'flx-crm' ), implode( ', ', $names ) ) : __( 'Kişi getiren kaynak yok; kişileri elle ekleyebilirsiniz.', 'flx-crm' ) );
			if ( $names ) {
				echo ' <button type="button" class="button button-small" data-flxcrm-sync>' . esc_html__( 'Tümünü eşitle', 'flx-crm' ) . '</button>';
			}
			echo '</div></div><p class="flxcrm-progress" data-flxcrm-progress hidden role="status"></p>'; // phpcs:ignore WordPress.Security.EscapeOutput
		}

		$risk_url = flxcrm_admin_url( 'contacts', array( 'risk' => 1, 'orderby' => 'churn' ) );
		echo '<div class="flxcrm-kpis">';
		$d = $s['new_prev'] ? ( $s['new30'] - $s['new_prev'] ) / $s['new_prev'] * 100 : null;
		FLXCRM_Admin::kpi( 'users', __( 'Kişi', 'flx-crm' ), number_format_i18n( $s['total'] ), sprintf( /* translators: %s: count */ __( '%s ödeme yapmış', 'flx-crm' ), number_format_i18n( $s['paying'] ) ) );
		FLXCRM_Admin::kpi( 'plus', __( 'Son 30 gün yeni', 'flx-crm' ), number_format_i18n( $s['new30'] ), null !== $d ? sprintf( /* translators: %s: signed percent */ __( 'önceki 30 güne göre %s', 'flx-crm' ), ( $d > 0 ? '+' : '' ) . number_format_i18n( $d, 0 ) . '%' ) : '' );
		FLXCRM_Admin::kpi( 'trend', __( 'Aktif', 'flx-crm' ), number_format_i18n( $s['active'] ), sprintf( /* translators: %s: days */ __( 'son %s günde hareketi olan', 'flx-crm' ), number_format_i18n( (int) flxcrm_setting( 'active_days' ) ) ) );
		FLXCRM_Admin::kpi( 'alert', __( 'Kayıp riski yüksek', 'flx-crm' ), number_format_i18n( $s['risk'] ), sprintf( /* translators: %s: percent */ __( 'risk %%%s ve üstü', 'flx-crm' ), (int) flxcrm_setting( 'risk_threshold' ) ), $s['risk'] ? 'warn' : '' );
		FLXCRM_Admin::kpi( 'coins', __( 'Toplam değer', 'flx-crm' ), flxcrm_money_short( $s['ltv'] ), $s['paying'] ? sprintf( /* translators: %s: amount */ __( 'kişi başı %s', 'flx-crm' ), flxcrm_money_short( $s['ltv'] / $s['paying'] ) ) : '' );
		echo '</div>';

		echo '<div class="flxcrm-grid">';
		/* RFM segmentleri */
		echo '<section class="flxcrm-card flxcrm-span-2"><div class="flxcrm-card__head"><h2>' . flxcrm_icon( 'chart', 16 ) . esc_html__( 'RFM segmentleri', 'flx-crm' ) . '</h2>'; // phpcs:ignore WordPress.Security.EscapeOutput
		$scored = (int) get_option( 'flxcrm_scored_at' );
		echo '<span class="flxcrm-muted">' . ( $scored ? esc_html( sprintf( /* translators: %s: time ago */ __( 'Hesaplandı: %s', 'flx-crm' ), flx_time_ago( $scored ) ) ) : '' );
		if ( $can ) {
			echo ' <button type="button" class="button-link" data-flxcrm-score>' . esc_html__( 'Yeniden hesapla', 'flx-crm' ) . '</button>';
		}
		echo '</span></div>';
		if ( ! $s['segments'] ) {
			echo '<p class="flxcrm-empty">' . esc_html__( 'Ödeme yapan kişi olunca bağışçı ve müşteriler burada sıklık, yenilik ve değerlerine göre gruplanır.', 'flx-crm' ) . '</p>';
		} else {
			$max = 0;
			foreach ( $s['segments'] as $v ) {
				$max = max( $max, $v[0] );
			}
			echo '<ul class="flxcrm-segs">';
			foreach ( flxcrm_rfm_segments() as $k => $seg ) {
				$n = $s['segments'][ $k ][0] ?? 0;
				$v = $s['segments'][ $k ][1] ?? 0;
				echo '<li class="is-' . esc_attr( $seg[2] ) . '"><a href="' . esc_url( flxcrm_admin_url( 'contacts', array( 'segment' => $k ) ) ) . '"><span class="flxcrm-segs__top"><strong>' . esc_html( $seg[0] ) . '</strong><span>' . esc_html( number_format_i18n( $n ) ) . '</span></span>';
				echo '<span class="flxcrm-segs__track"><span style="width:' . esc_attr( $max ? round( $n / $max * 100, 1 ) : 0 ) . '%"></span></span>';
				echo '<span class="flxcrm-segs__sub">' . esc_html( flxcrm_money_short( $v ) ) . ' · ' . esc_html( $seg[1] ) . '</span></a></li>';
			}
			echo '</ul>';
		}
		echo '</section>';

		/* Roller */
		echo '<section class="flxcrm-card"><div class="flxcrm-card__head"><h2>' . flxcrm_icon( 'badge', 16 ) . esc_html__( 'Roller', 'flx-crm' ) . '</h2></div><ul class="flxcrm-list">'; // phpcs:ignore WordPress.Security.EscapeOutput
		$any = false;
		foreach ( flxcrm_roles() as $k => $r ) {
			if ( ! empty( $r['hidden'] ) ) {
				continue;
			}
			$n = $s['roles'][ $k ] ?? 0;
			if ( ! $n && empty( $r['builtin'] ) ) {
				continue;
			}
			$any = true;
			echo '<li><a href="' . esc_url( flxcrm_admin_url( 'contacts', array( 'role' => $k ) ) ) . '"><span class="flxcrm-role" style="--rc:' . esc_attr( $r['color'] ) . '">' . esc_html( $r['label'] ) . '</span><strong class="flxcrm-num">' . esc_html( number_format_i18n( $n ) ) . '</strong></a></li>';
		}
		echo '</ul>';
		if ( ! $any ) {
			echo '<p class="flxcrm-empty">' . esc_html__( 'Henüz kişi yok.', 'flx-crm' ) . '</p>';
		}
		echo '</section>';

		/* Risk altındakiler */
		$risk = FLXCRM_Contacts::query( array( 'risk' => 1, 'orderby' => 'ltv', 'per_page' => 6 ) );
		echo '<section class="flxcrm-card"><div class="flxcrm-card__head"><h2>' . flxcrm_icon( 'alert', 16 ) . esc_html__( 'Temas kurulmalı', 'flx-crm' ) . '</h2><a href="' . esc_url( $risk_url ) . '">' . esc_html__( 'Tümü', 'flx-crm' ) . flxcrm_icon( 'arrow', 14 ) . '</a></div>'; // phpcs:ignore WordPress.Security.EscapeOutput
		if ( ! $risk['rows'] ) {
			echo '<p class="flxcrm-empty">' . flxcrm_icon( 'check', 16 ) . esc_html__( 'Kayıp riski yüksek, değerli kişi yok.', 'flx-crm' ) . '</p>'; // phpcs:ignore WordPress.Security.EscapeOutput
		} else {
			echo '<ul class="flxcrm-list">';
			foreach ( $risk['rows'] as $c ) {
				echo '<li><a href="' . esc_url( flxcrm_contact_url( $c['id'] ) ) . '">' . FLXCRM_Admin::avatar( $c, 'sm' ) . '<span class="flxcrm-list__main"><strong>' . esc_html( flxcrm_display_name( $c ) ) . '</strong><small>' . esc_html( sprintf( /* translators: %s: time ago */ __( 'son ödeme %s', 'flx-crm' ), flxcrm_ago( $c['last_paid_at'] ) ) ) . '</small></span><span class="flxcrm-list__side"><strong>' . esc_html( flxcrm_money_short( $c['ltv'] ) ) . '</strong><span class="flxcrm-badge is-bad">%' . (int) $c['churn'] . '</span></span></a></li>'; // phpcs:ignore WordPress.Security.EscapeOutput
			}
			echo '</ul>';
		}
		echo '</section>';

		/* Son hareketler */
		$recent = FLXCRM_Events::recent( 8 );
		echo '<section class="flxcrm-card flxcrm-span-2"><div class="flxcrm-card__head"><h2>' . flxcrm_icon( 'clock', 16 ) . esc_html__( 'Son hareketler', 'flx-crm' ) . '</h2></div>'; // phpcs:ignore WordPress.Security.EscapeOutput
		if ( ! $recent ) {
			echo '<p class="flxcrm-empty">' . esc_html__( 'Bağış, sipariş, form ve WhatsApp hareketleri burada görünür.', 'flx-crm' ) . '</p>';
		} else {
			echo '<ul class="flxcrm-feed">';
			$types = flxcrm_event_types();
			foreach ( $recent as $e ) {
				$icon = $types[ $e['type'] ][1] ?? 'note';
				echo '<li><span class="flxcrm-feed__icon is-' . esc_attr( $e['type'] ) . '">' . flxcrm_icon( $icon, 15 ) . '</span><span class="flxcrm-feed__main"><a href="' . esc_url( flxcrm_contact_url( $e['contact_id'] ) ) . '"><strong>' . esc_html( flxcrm_display_name( $e ) ) . '</strong></a> <span class="flxcrm-muted">' . esc_html( flxcrm_event_label( $e['type'] ) ) . '</span><small>' . esc_html( $e['title'] ) . '</small></span><span class="flxcrm-feed__side">'; // phpcs:ignore WordPress.Security.EscapeOutput
				if ( (float) $e['amount'] > 0 ) {
					echo '<strong class="' . ( (int) $e['paid'] ? '' : 'flxcrm-strike' ) . '">' . esc_html( flxcrm_money( $e['amount'], $e['currency'] ) ) . '</strong>';
				}
				echo '<small>' . esc_html( flxcrm_ago( $e['at'] ) ) . '</small></span></li>';
			}
			echo '</ul>';
		}
		echo '</section>';

		/* Kaynaklar + segmentler */
		echo '<section class="flxcrm-card"><div class="flxcrm-card__head"><h2>' . flxcrm_icon( 'link', 16 ) . esc_html__( 'Nereden geldiler', 'flx-crm' ) . '</h2></div>'; // phpcs:ignore WordPress.Security.EscapeOutput
		if ( ! $s['sources'] ) {
			echo '<p class="flxcrm-empty">' . esc_html__( 'Henüz kişi yok.', 'flx-crm' ) . '</p>';
		} else {
			$max = max( $s['sources'] );
			echo '<ul class="flxcrm-bars">';
			foreach ( $s['sources'] as $k => $n ) {
				echo '<li><div class="flxcrm-bars__top"><span>' . esc_html( flxcrm_source_label( $k ) ) . '</span><strong>' . esc_html( number_format_i18n( $n ) ) . '</strong></div><div class="flxcrm-bars__track"><span style="width:' . esc_attr( round( $n / $max * 100, 1 ) ) . '%"></span></div></li>';
			}
			echo '</ul>';
		}
		$segs = FLXCRM_Segments::all();
		if ( $segs ) {
			echo '<h3 class="flxcrm-sub">' . esc_html__( 'Kayıtlı segmentler', 'flx-crm' ) . '</h3><ul class="flxcrm-list">';
			foreach ( array_slice( $segs, 0, 5 ) as $sg ) {
				echo '<li><a href="' . esc_url( flxcrm_admin_url( 'contacts', array( 'sid' => (int) $sg['id'] ) ) ) . '"><span class="flxcrm-list__main"><strong>' . esc_html( $sg['name'] ) . '</strong></span><strong class="flxcrm-num">' . esc_html( number_format_i18n( (int) $sg['count'] ) ) . '</strong></a></li>';
			}
			echo '</ul>';
		}
		echo '</section>';
		echo '</div>';

		FLXCRM_Admin::close();
	}
}
