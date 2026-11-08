<?php
/**
 * Kayıtlar: giden her e-posta, WhatsApp ve SMS.
 *
 * @package flx-mail
 */

defined( 'ABSPATH' ) || exit;

function flxi_status_labels() {
	return array(
		'sent'    => __( 'Sent', 'flx-mail' ),
		'failed'  => __( 'Failed', 'flx-mail' ),
		'blocked' => __( 'Disabled, not sent', 'flx-mail' ),
		'sending' => __( 'Sending', 'flx-mail' ),
	);
}

function flxi_page_log() {
	global $wpdb;
	// phpcs:disable WordPress.Security.NonceVerification
	$status   = sanitize_key( wp_unslash( $_GET['status'] ?? '' ) );
	$channel  = sanitize_key( wp_unslash( $_GET['channel'] ?? '' ) );
	$search   = sanitize_text_field( wp_unslash( $_GET['s'] ?? '' ) );
	$template = sanitize_text_field( wp_unslash( $_GET['template'] ?? '' ) );
	$camp     = (int) ( $_GET['campaign'] ?? 0 );
	$paged    = max( 1, (int) ( $_GET['paged'] ?? 1 ) );
	// phpcs:enable
	$lib = function_exists( 'flxa_list_form_attrs' );
	$per = function_exists( 'flxa_list_per_page' ) ? flxa_list_per_page( 'flxi-log', 20 ) : 30;

	flxi_page_head( __( 'Log', 'flx-mail' ), __( 'Every email, WhatsApp and SMS message sent from the site. Click a row to see its content.', 'flx-mail' ) );

	$t     = flxi_table( 'log' );
	$where = array( '1=1' );
	$prm   = array();
	if ( $status ) {
		$where[] = 'status = %s';
		$prm[]   = $status;
	}
	if ( $channel ) {
		$where[] = 'channel = %s';
		$prm[]   = $channel;
	}
	if ( $template ) {
		$where[] = 'template = %s';
		$prm[]   = $template;
	}
	if ( $camp ) {
		$where[] = 'campaign_id = %d';
		$prm[]   = $camp;
	}
	if ( $search ) {
		$like    = '%' . $wpdb->esc_like( $search ) . '%';
		$where[] = '(recipient LIKE %s OR subject LIKE %s)';
		$prm[]   = $like;
		$prm[]   = $like;
	}
	$w     = implode( ' AND ', $where );
	$total = (int) $wpdb->get_var( $prm ? $wpdb->prepare( "SELECT COUNT(*) FROM $t WHERE $w", $prm ) : "SELECT COUNT(*) FROM $t WHERE $w" ); // phpcs:ignore
	$off   = ( $paged - 1 ) * $per;
	$sql   = "SELECT id, created_at, channel, template, source, recipient, subject, status, error, mailer, sender FROM $t WHERE $w ORDER BY id DESC LIMIT $per OFFSET $off";
	$rows  = $wpdb->get_results( $prm ? $wpdb->prepare( $sql, $prm ) : $sql, ARRAY_A ); // phpcs:ignore

	echo '<form method="get" class="flxi-filters"' . ( $lib ? flxa_list_form_attrs( array( 'key' => 'flxi-log' ) ) : '' ) . '><input type="hidden" name="page" value="flxi-kayitlar">'; // phpcs:ignore
	echo '<input type="search" name="s" value="' . esc_attr( $search ) . '" placeholder="' . esc_attr__( 'Recipient or subject', 'flx-mail' ) . '">';
	echo '<select name="status" aria-label="' . esc_attr__( 'Status', 'flx-mail' ) . '"><option value="">' . esc_html__( 'All statuses', 'flx-mail' ) . '</option>';
	foreach ( flxi_status_labels() as $k => $l ) {
		printf( '<option value="%s"%s>%s</option>', esc_attr( $k ), selected( $status, $k, false ), esc_html( $l ) );
	}
	echo '</select><select name="channel" aria-label="' . esc_attr__( 'Channel', 'flx-mail' ) . '"><option value="">' . esc_html__( 'All channels', 'flx-mail' ) . '</option>';
	foreach ( flxi_channels() as $k => $l ) {
		printf( '<option value="%s"%s>%s</option>', esc_attr( $k ), selected( $channel, $k, false ), esc_html( $l ) );
	}
	echo '</select>';
	if ( $template ) {
		echo '<input type="hidden" name="template" value="' . esc_attr( $template ) . '">';
	}
	if ( $camp ) {
		echo '<input type="hidden" name="campaign" value="' . (int) $camp . '">';
	}
	echo '<button class="button">' . esc_html__( 'Filter', 'flx-mail' ) . '</button>';
	if ( ! $lib && ( $status || $channel || $search || $template || $camp ) ) {
		echo ' <a class="button-link" href="' . esc_url( flxi_admin_url( 'flxi-kayitlar' ) ) . '">' . esc_html__( 'Clear', 'flx-mail' ) . '</a>';
	}
	/* translators: %s: sayı */
	echo '<span class="flxi-muted flxi-filters__count">' . esc_html( sprintf( __( '%s entries', 'flx-mail' ), number_format_i18n( $total ) ) ) . '</span></form>';

	$types  = flxi_email_types();
	$disc   = flxi_discovered();
	$labels = flxi_status_labels();
	echo '<div class="flxi-card flxi-card--flush"><div class="flxi-tablewrap"><table class="flxi-table flxi-table--log"><thead><tr><th>' . esc_html__( 'Time', 'flx-mail' ) . '</th><th>' . esc_html__( 'Recipient', 'flx-mail' ) . '</th><th>' . esc_html__( 'Subject / email', 'flx-mail' ) . '</th><th>' . esc_html__( 'Status', 'flx-mail' ) . '</th></tr></thead><tbody>';
	if ( ! $rows ) {
		echo '<tr><td colspan="4" class="flxi-empty">' . esc_html__( 'No entries.', 'flx-mail' ) . '</td></tr>';
	}
	foreach ( (array) $rows as $r ) {
		$name = $types[ $r['template'] ]['label'] ?? ( isset( $disc[ $r['template'] ] ) ? flxi_discovered_label( $r['template'], $disc[ $r['template'] ] ) : '' );
		if ( 0 === strpos( $r['template'], 'wc/' ) ) {
			$name = 'WooCommerce · ' . substr( $r['template'], 3 );
		} elseif ( 'flx-mail/campaign' === $r['template'] ) {
			$name = __( 'Bulk send', 'flx-mail' );
		} elseif ( 'flx-mail/test' === $r['template'] ) {
			$name = __( 'Test', 'flx-mail' );
		}
		$kind = 'sent' === $r['status'] ? 'green' : ( 'failed' === $r['status'] ? 'red' : 'muted' );
		echo '<tr class="flxi-logrow" tabindex="0" data-log="' . (int) $r['id'] . '">';
		echo '<td class="flxi-nowrap">' . esc_html( get_date_from_gmt( $r['created_at'], 'd.m.Y H:i' ) ) . '<br><span class="flxi-muted">' . esc_html( flxi_channels()[ $r['channel'] ] ?? $r['channel'] ) . '</span></td>';
		echo '<td class="flxi-break">' . esc_html( $r['recipient'] ) . '</td>';
		echo '<td><strong>' . esc_html( $r['subject'] ? $r['subject'] : '—' ) . '</strong><br><span class="flxi-muted">' . esc_html( trim( $name . ( $r['source'] ? ' · ' . flxi_source_label( $r['source'] ) : '' ), ' ·' ) ) . '</span></td>';
		echo '<td>' . flxi_badge( $labels[ $r['status'] ] ?? $r['status'], $kind ); // phpcs:ignore
		if ( $r['error'] ) {
			echo '<div class="flxi-err">' . esc_html( mb_substr( $r['error'], 0, 160 ) ) . '</div>';
		}
		echo '</td></tr>';
	}
	echo '</tbody></table></div></div>';

	if ( function_exists( 'flxa_list_pagination' ) ) {
		flxa_list_pagination( $total, $per, $paged );
	} else {
		$pages = (int) ceil( $total / $per );
		if ( $pages > 1 ) {
			echo '<div class="flxi-pager">' . paginate_links( // phpcs:ignore
				array(
					'base'    => add_query_arg( 'paged', '%#%' ),
					'format'  => '',
					'current' => $paged,
					'total'   => $pages,
				)
			) . '</div>';
		}
	}

	echo '<dialog class="flxi-dialog" id="flxi-log-dialog" aria-labelledby="flxi-log-title"><div class="flxi-dialog__head"><h2 id="flxi-log-title"></h2><button type="button" class="button-link" data-close aria-label="' . esc_attr__( 'Close', 'flx-mail' ) . '"><span class="dashicons dashicons-no-alt"></span></button></div><dl class="flxi-dl" data-log-meta></dl><iframe title="' . esc_attr__( 'Content', 'flx-mail' ) . '" sandbox="allow-same-origin" data-log-frame></iframe></dialog>';
	flxi_page_foot();
}
