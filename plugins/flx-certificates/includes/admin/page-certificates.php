<?php
defined( 'ABSPATH' ) || exit;

add_action( 'admin_post_flxs_cert_bulk', 'flxs_handle_cert_bulk' );

function flxs_handle_cert_bulk() {
	check_admin_referer( 'flxs_cert_bulk' );
	if ( ! flxs_can_use() ) {
		wp_die( esc_html__( 'Not allowed', 'flx-certificates' ) );
	}
	$ids    = array_filter( array_map( 'intval', (array) ( $_POST['ids'] ?? array() ) ) );
	$action = sanitize_key( $_POST['bulk'] ?? '' );
	if ( ! $ids ) {
		flxs_redirect( 'flxs', array( 'flxs_err' => __( 'No certificates selected.', 'flx-certificates' ) ) );
	}
	if ( 0 === strpos( $action, 'send_' ) ) {
		$ch = substr( $action, 5 );
		if ( ! flxs_can_bulk() ) {
			// Personel toplu gönderemiyorsa seçilenleri tek tek kuyruğa al (en çok 20)
			$n = 0;
			foreach ( array_slice( $ids, 0, 20 ) as $id ) {
				$c = flxs_get_certificate( $id );
				if ( $c && flxs_user_can_edit_certificate( $c ) ) {
					$r = flxs_send_certificate( $c, array( $ch ) );
					$n += is_wp_error( $r[ $ch ] ?? null ) ? 0 : 1;
				}
			}
			flxs_kick_queue();
			flxs_redirect( 'flxs-kuyruk', array( 'flxs_msg' => sprintf( __( '%d messages queued.', 'flx-certificates' ), $n ) ) );
		}
		flxs_redirect( 'flxs-toplu', array( 'ids' => implode( ',', $ids ), 'channel' => $ch ) );
	}
	$n = 0;
	foreach ( $ids as $id ) {
		$c = flxs_get_certificate( $id );
		if ( ! $c || ! flxs_user_can_edit_certificate( $c ) ) {
			continue;
		}
		if ( 'revoke' === $action ) {
			$n += flxs_set_certificate_status( $id, 'revoked' ) ? 1 : 0;
		} elseif ( 'restore' === $action ) {
			$n += flxs_set_certificate_status( $id, 'active' ) ? 1 : 0;
		} elseif ( 'event' === $action ) {
			$n += true === flxs_set_certificate_event( $id, (int) ( $_POST['event'] ?? 0 ) ) ? 1 : 0;
		} elseif ( 'ref' === $action ) {
			$n += flxs_set_certificate_ref( $id, (int) ( $_POST['ref_user'] ?? 0 ) ) ? 1 : 0;
		} elseif ( 'delete' === $action && flxs_can_manage() ) {
			$n += flxs_delete_certificate( $id ) ? 1 : 0;
		}
	}
	flxs_redirect( 'flxs', array( 'flxs_msg' => sprintf( __( '%d certificates updated.', 'flx-certificates' ), $n ) ) );
}

function flxs_cert_message_summary( $ids ) {
	global $wpdb;
	if ( ! $ids ) {
		return array();
	}
	$rows = $wpdb->get_results( 'SELECT certificate_id, channel, status, COUNT(*) n FROM ' . flxs_table( 'messages' ) . ' WHERE certificate_id IN (' . implode( ',', array_map( 'intval', $ids ) ) . ') GROUP BY certificate_id, channel, status', ARRAY_A );
	$out  = array();
	foreach ( $rows as $r ) {
		$out[ $r['certificate_id'] ][ $r['channel'] ][ $r['status'] ] = (int) $r['n'];
	}
	return $out;
}

function flxs_page_certificates() {
	$per   = flxs_list_per_page( 'flxs-certs', 20 );
	$paged = max( 1, (int) flxs_get_param( 'paged', 1 ) );
	$args  = array_merge(
		flxs_cert_filter_args(),
		array(
			'per_page' => $per,
			'page'     => $paged,
		)
	);
	$q     = flxs_query_certificates( $args );
	$msgs  = flxs_cert_message_summary( wp_list_pluck( $q['items'], 'id' ) );
	$tpls  = flxs_list_templates();
	$users = flxs_users_with_cap();
	$refn  = flxs_ref_counts();
	$evts  = flxs_get_events();
	$evn   = flxs_event_counts();
	$evnam = array();
	foreach ( $evts as $e ) {
		$evnam[ (int) $e['id'] ] = $e['name'];
	}

	flxs_admin_header(
		__( 'Certificates', 'flx-certificates' ),
		array(
			array( 'label' => __( 'Download Excel', 'flx-certificates' ) . ( $q['total'] ? ' (' . number_format_i18n( $q['total'] ) . ')' : '' ), 'url' => flxs_cert_export_url(), 'class' => 'flxs-export' ),
			array( 'label' => '+ ' . __( 'New certificate', 'flx-certificates' ), 'url' => admin_url( 'admin.php?page=flxs-yeni' ), 'class' => 'button-primary' ),
		)
	);
	?>
	<form method="get" class="flxs-filters"<?php echo flxs_list_form_attrs( 'flxs-certs' ); // phpcs:ignore ?>>
		<input type="hidden" name="page" value="flxs">
		<input type="search" name="s" value="<?php echo esc_attr( $args['search'] ); ?>" placeholder="<?php esc_attr_e( 'Name, phone, email, certificate no…', 'flx-certificates' ); ?>">
		<select name="status" data-flxa-primary aria-label="<?php esc_attr_e( 'Status', 'flx-certificates' ); ?>">
			<option value=""><?php esc_html_e( 'All statuses', 'flx-certificates' ); ?></option>
			<option value="active" <?php selected( $args['status'], 'active' ); ?>><?php esc_html_e( 'Valid', 'flx-certificates' ); ?></option>
			<option value="revoked" <?php selected( $args['status'], 'revoked' ); ?>><?php esc_html_e( 'Cancelled', 'flx-certificates' ); ?></option>
		</select>
		<select name="event" data-flxa-primary aria-label="<?php esc_attr_e( 'Event', 'flx-certificates' ); ?>">
			<option value=""><?php esc_html_e( 'All events', 'flx-certificates' ); ?></option>
			<option value="0" <?php selected( $args['event_id'], '0' ); ?>><?php echo esc_html( sprintf( __( 'No event (%d)', 'flx-certificates' ), (int) ( $evn[0] ?? 0 ) ) ); ?></option>
			<?php foreach ( $evts as $e ) : ?>
				<option value="<?php echo (int) $e['id']; ?>" <?php selected( $args['event_id'], (string) $e['id'] ); ?>><?php echo esc_html( $e['name'] . ( 'closed' === $e['status'] ? ' · ' . __( 'archived', 'flx-certificates' ) : '' ) . ' (' . (int) ( $evn[ (int) $e['id'] ] ?? 0 ) . ')' ); ?></option>
			<?php endforeach; ?>
		</select>
		<select name="template" aria-label="<?php esc_attr_e( 'Template', 'flx-certificates' ); ?>">
			<option value=""><?php esc_html_e( 'All templates', 'flx-certificates' ); ?></option>
			<?php foreach ( $tpls as $t ) : ?>
				<option value="<?php echo (int) $t['id']; ?>" <?php selected( $args['template_id'], $t['id'] ); ?>><?php echo esc_html( $t['name'] ); ?></option>
			<?php endforeach; ?>
		</select>
		<select name="ref" aria-label="<?php esc_attr_e( 'Referrer', 'flx-certificates' ); ?>">
			<option value=""><?php esc_html_e( 'All referrers', 'flx-certificates' ); ?></option>
			<option value="0" <?php selected( $args['ref_user_id'], '0' ); ?>><?php echo esc_html( sprintf( __( 'No referrer (%d)', 'flx-certificates' ), (int) ( $refn[0] ?? 0 ) ) ); ?></option>
			<?php foreach ( $users as $u ) : ?>
				<option value="<?php echo (int) $u->ID; ?>" <?php selected( $args['ref_user_id'], (string) $u->ID ); ?>><?php echo esc_html( $u->display_name . ' (' . (int) ( $refn[ $u->ID ] ?? 0 ) . ')' ); ?></option>
			<?php endforeach; ?>
		</select>
		<?php if ( ! flxs_scoped() ) : ?>
			<select name="by" aria-label="<?php esc_attr_e( 'Created by', 'flx-certificates' ); ?>">
				<option value=""><?php esc_html_e( 'Created by: anyone', 'flx-certificates' ); ?></option>
				<?php foreach ( $users as $u ) : ?>
					<option value="<?php echo (int) $u->ID; ?>" <?php selected( $args['created_by'], $u->ID ); ?>><?php echo esc_html( $u->display_name ); ?></option>
				<?php endforeach; ?>
			</select>
		<?php endif; ?>
		<button class="button"><?php esc_html_e( 'Filter', 'flx-certificates' ); ?></button>
	</form>

	<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
		<?php wp_nonce_field( 'flxs_cert_bulk' ); ?>
		<input type="hidden" name="action" value="flxs_cert_bulk">
		<div class="flxs-bulkbar">
			<select name="bulk">
				<option value=""><?php esc_html_e( 'Bulk actions…', 'flx-certificates' ); ?></option>
				<option value="send_whatsapp"><?php esc_html_e( 'Send via WhatsApp', 'flx-certificates' ); ?></option>
				<option value="send_sms"><?php esc_html_e( 'Send via SMS', 'flx-certificates' ); ?></option>
				<option value="send_email"><?php esc_html_e( 'Send via email', 'flx-certificates' ); ?></option>
				<option value="revoke"><?php esc_html_e( 'Revoke', 'flx-certificates' ); ?></option>
				<option value="restore"><?php esc_html_e( 'Make valid', 'flx-certificates' ); ?></option>
				<option value="event"><?php esc_html_e( 'Assign to event…', 'flx-certificates' ); ?></option>
				<option value="ref"><?php esc_html_e( 'Assign referrer…', 'flx-certificates' ); ?></option>
				<?php if ( flxs_can_manage() ) : ?>
					<option value="delete"><?php esc_html_e( 'Delete permanently', 'flx-certificates' ); ?></option>
				<?php endif; ?>
			</select>
			<select name="event" class="flxs-bulk-event" hidden>
				<option value="0"><?php esc_html_e( '— No event —', 'flx-certificates' ); ?></option>
				<?php foreach ( $evts as $e ) : ?>
					<?php if ( 'open' === $e['status'] ) : ?>
						<option value="<?php echo (int) $e['id']; ?>"><?php echo esc_html( $e['name'] ); ?></option>
					<?php endif; ?>
				<?php endforeach; ?>
			</select>
			<select name="ref_user" class="flxs-bulk-ref" hidden>
				<option value="0"><?php esc_html_e( '— No referrer —', 'flx-certificates' ); ?></option>
				<?php foreach ( $users as $u ) : ?>
					<option value="<?php echo (int) $u->ID; ?>"><?php echo esc_html( $u->display_name ); ?></option>
				<?php endforeach; ?>
			</select>
			<button class="button" data-confirm="<?php esc_attr_e( 'Apply to the selected items?', 'flx-certificates' ); ?>"><?php esc_html_e( 'Apply', 'flx-certificates' ); ?></button>
		</div>
		<table class="widefat flxs-table flxs-table--certs">
			<thead><tr>
				<td class="check-column"><input type="checkbox" class="flxs-check-all"></td>
				<th><?php esc_html_e( 'Certificate', 'flx-certificates' ); ?></th><th><?php esc_html_e( 'Person', 'flx-certificates' ); ?></th><th><?php esc_html_e( 'Event / Template', 'flx-certificates' ); ?></th><th><?php esc_html_e( 'Deliveries', 'flx-certificates' ); ?></th><th><?php esc_html_e( 'Referrer / Created by', 'flx-certificates' ); ?></th><th><?php esc_html_e( 'Status', 'flx-certificates' ); ?></th><th></th>
			</tr></thead>
			<tbody>
			<?php if ( ! $q['items'] ) : ?>
				<tr><td colspan="8" class="flxs-empty"><?php esc_html_e( 'No certificates yet.', 'flx-certificates' ); ?> <a href="<?php echo esc_url( admin_url( 'admin.php?page=flxs-yeni' ) ); ?>"><?php esc_html_e( 'Create the first certificate', 'flx-certificates' ); ?></a></td></tr>
			<?php endif; ?>
			<?php
			foreach ( $q['items'] as $c ) :
				$url  = flxs_certificate_url( $c );
				$img  = flxs_certificate_file_url( $c, 'image' );
				$can  = flxs_user_can_edit_certificate( $c );
				$m    = $msgs[ $c['id'] ] ?? array();
				?>
				<tr>
					<th class="check-column"><input type="checkbox" name="ids[]" value="<?php echo (int) $c['id']; ?>"></th>
					<td data-label="<?php esc_attr_e( 'Certificate', 'flx-certificates' ); ?>">
						<div class="flxs-cert-cell">
							<?php if ( $img ) : ?><a href="<?php echo esc_url( $url ); ?>" target="_blank" class="flxs-thumb"><img src="<?php echo esc_url( $img ); ?>" alt="" loading="lazy"></a><?php endif; ?>
							<div>
								<strong><a href="<?php echo esc_url( $url ); ?>" target="_blank"><?php echo esc_html( $c['number'] ); ?></a></strong>
								<div class="flxs-muted"><?php echo esc_html( flxs_date( $c['created_at'] ) ); ?><?php echo ! empty( $c['data']['backdated'] ) ? ' <span class="flxs-pill flxs-pill--muted" title="' . esc_attr( sprintf( __( 'Actually created: %s', 'flx-certificates' ), flxs_date( $c['data']['backdated'] ) ) ) . '">' . esc_html__( 'backdated', 'flx-certificates' ) . '</span>' : ''; ?> · <?php echo esc_html( sprintf( _n( '[[\'%dviews\', \'tr\']]', '[[\'%dviews\', \'tr\']]', (int) $c['views'], 'flx-certificates' ), (int) $c['views'] ) ); ?></div>
							</div>
						</div>
					</td>
					<td data-label="<?php esc_attr_e( 'Person', 'flx-certificates' ); ?>">
						<a href="<?php echo esc_url( admin_url( 'admin.php?page=flxs-kisiler&person=' . (int) $c['person_id'] ) ); ?>"><?php echo esc_html( flxs_certificate_name( $c, array( 'name' => $c['person_name'] ) ) ); ?></a>
						<?php echo flxs_certificate_name( $c, array( 'name' => $c['person_name'] ) ) !== $c['person_name'] ? '<div class="flxs-muted">' . esc_html( sprintf( __( 'Registered person: %s', 'flx-certificates' ), $c['person_name'] ) ) . '</div>' : ''; ?>
						<div class="flxs-muted"><?php echo esc_html( implode( ' · ', array_filter( array( flxs_format_phone( $c['person_phone'] ), $c['person_email'] ) ) ) ); ?></div>
					</td>
					<td data-label="<?php esc_attr_e( 'Event', 'flx-certificates' ); ?>">
						<?php echo (int) $c['event_id'] && isset( $evnam[ (int) $c['event_id'] ] ) ? '<strong>' . esc_html( $evnam[ (int) $c['event_id'] ] ) . '</strong><br>' : ''; ?>
						<span class="flxs-muted"><?php echo esc_html( $c['template_name'] ); ?></span>
					</td>
					<td class="flxs-c-sends" data-label="<?php esc_attr_e( 'Deliveries', 'flx-certificates' ); ?>">
						<?php
						if ( ! $m ) {
							echo '<span class="flxs-muted flxs-c-none">–</span>';
						}
						foreach ( $m as $ch => $st ) {
							$sent = $st['sent'] ?? 0;
							$fail = $st['failed'] ?? 0;
							$wait = ( $st['queued'] ?? 0 ) + ( $st['processing'] ?? 0 );
							$cls  = $fail && ! $sent ? 'failed' : ( $wait ? 'queued' : 'sent' );
							echo '<a class="flxs-chip flxs-chip--' . esc_attr( $cls ) . '" href="' . esc_url( admin_url( 'admin.php?page=flxs-kuyruk&cert=' . (int) $c['id'] ) ) . '">' . flxs_channel_icon( $ch ) . ( $sent ? (int) $sent . '✓' : '' ) . ( $wait ? ' ' . (int) $wait . '…' : '' ) . ( $fail ? ' ' . (int) $fail . '✕' : '' ) . '</a> '; // phpcs:ignore
						}
						?>
					</td>
					<td class="flxs-c-ref" data-label="<?php esc_attr_e( 'Referrer', 'flx-certificates' ); ?>" data-ref-cell="<?php echo (int) $c['id']; ?>">
						<?php echo $c['ref_user_id'] ? '<strong>' . esc_html( flxs_user_label( $c['ref_user_id'] ) ) . '</strong>' : '<span class="flxs-muted flxs-c-none">–</span>'; ?>
						<div class="flxs-muted"><?php echo esc_html( sprintf( __( 'created by: %s', 'flx-certificates' ), flxs_user_label( $c['created_by'] ) ) ); ?></div>
					</td>
					<td class="flxs-c-status" data-label="<?php esc_attr_e( 'Status', 'flx-certificates' ); ?>"><?php echo flxs_status_badge( $c['status'] ); // phpcs:ignore ?></td>
					<td class="flxs-actions-cell">
						<details class="flxs-menu">
							<summary class="button button-small"><?php esc_html_e( 'Actions', 'flx-certificates' ); ?></summary>
							<div class="flxs-menu__list">
								<a href="<?php echo esc_url( $url ); ?>" target="_blank"><?php esc_html_e( 'Open page', 'flx-certificates' ); ?></a>
								<a href="#" data-flxs-copy="<?php echo esc_url( $url ); ?>"><?php esc_html_e( 'Copy link', 'flx-certificates' ); ?></a>
								<a href="<?php echo esc_url( flxs_certificate_pdf_url( $c ) ); ?>" target="_blank"><?php esc_html_e( 'PDF', 'flx-certificates' ); ?></a>
								<?php if ( 'active' === $c['status'] ) : ?>
									<a href="#" data-quick-send="whatsapp" data-id="<?php echo (int) $c['id']; ?>"><?php esc_html_e( 'Send via WhatsApp', 'flx-certificates' ); ?></a>
									<a href="#" data-quick-send="sms" data-id="<?php echo (int) $c['id']; ?>"><?php esc_html_e( 'Send via SMS', 'flx-certificates' ); ?></a>
									<a href="#" data-quick-send="email" data-id="<?php echo (int) $c['id']; ?>"><?php esc_html_e( 'Send via email', 'flx-certificates' ); ?></a>
									<a href="#" data-forward="<?php echo (int) $c['id']; ?>"><?php esc_html_e( 'Send to someone else…', 'flx-certificates' ); ?></a>
									<?php if ( $can ) : ?>
										<a href="#" data-ref-edit="<?php echo (int) $c['id']; ?>" data-ref="<?php echo (int) $c['ref_user_id']; ?>" data-label="<?php echo esc_attr( $c['number'] . ' · ' . flxs_certificate_name( $c, array( 'name' => $c['person_name'] ) ) ); ?>"><?php esc_html_e( 'Update referrer…', 'flx-certificates' ); ?></a>
									<?php endif; ?>
								<?php endif; ?>
								<?php if ( $can ) : ?>
									<a href="<?php echo esc_url( admin_url( 'admin.php?page=flxs-yeni&id=' . (int) $c['id'] ) ); ?>"><?php esc_html_e( 'Edit', 'flx-certificates' ); ?></a>
									<?php if ( 'active' === $c['status'] ) : ?>
										<a href="#" data-cert-status="revoked" data-id="<?php echo (int) $c['id']; ?>" data-confirm="<?php esc_attr_e( 'Revoke this certificate? The link will show "revoked".', 'flx-certificates' ); ?>"><?php esc_html_e( 'Revoke', 'flx-certificates' ); ?></a>
									<?php else : ?>
										<a href="#" data-cert-status="active" data-id="<?php echo (int) $c['id']; ?>"><?php esc_html_e( 'Make valid', 'flx-certificates' ); ?></a>
									<?php endif; ?>
								<?php endif; ?>
								<a href="<?php echo esc_url( admin_url( 'admin.php?page=flxs-kuyruk&cert=' . (int) $c['id'] ) ); ?>"><?php esc_html_e( 'Delivery history', 'flx-certificates' ); ?></a>
							</div>
						</details>
					</td>
				</tr>
			<?php endforeach; ?>
			</tbody>
		</table>
	</form>
	<?php
	flxs_list_pages( $q['total'], $per, $paged );
	flxs_admin_footer();
}

/** Referans başına sertifika sayısı (ref_user_id => adet) */
function flxs_ref_counts() {
	global $wpdb;
	$out = array();
	$where = flxs_scoped() ? ' WHERE ' . flxs_scope_sql( '' ) : '';
	foreach ( $wpdb->get_results( 'SELECT ref_user_id, COUNT(*) n FROM ' . flxs_table( 'certificates' ) . $where . ' GROUP BY ref_user_id', ARRAY_A ) as $r ) {
		$out[ (int) $r['ref_user_id'] ] = (int) $r['n'];
	}
	return $out;
}


/** Etkinlik başına sertifika sayısı (event_id => adet) */
function flxs_event_counts() {
	global $wpdb;
	$out   = array();
	$where = flxs_scoped() ? ' WHERE ' . flxs_scope_sql( '' ) : '';
	foreach ( $wpdb->get_results( 'SELECT event_id, COUNT(*) n FROM ' . flxs_table( 'certificates' ) . $where . ' GROUP BY event_id', ARRAY_A ) as $r ) { // phpcs:ignore
		$out[ (int) $r['event_id'] ] = (int) $r['n'];
	}
	return $out;
}
