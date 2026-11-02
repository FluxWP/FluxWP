<?php
defined( 'ABSPATH' ) || exit;

function flxs_page_queue() {
	$per    = flxs_list_per_page( 'flxs-queue', 30 );
	$paged  = max( 1, (int) flxs_get_param( 'paged', 1 ) );
	$status = flxs_get_param( 'status' );
	$batch  = (int) flxs_get_param( 'batch' );
	$args   = array(
		'status'         => $status,
		'channel'        => flxs_get_param( 'channel' ),
		'batch_id'       => $batch,
		'certificate_id' => (int) flxs_get_param( 'cert' ),
		'search'         => flxs_get_param( 's' ),
		'scope'          => true,
		'per_page'       => $per,
		'page'           => $paged,
	);
	$q       = flxs_query_messages( $args );
	$counts  = flxs_queue_counts( $batch, true );
	$pending = $counts['queued'] + $counts['processing'];
	$labels  = flxs_status_labels();
	$base    = remove_query_arg( array( 'status', 'paged', 'flxs_msg', 'flxs_err' ) );
	$next    = wp_next_scheduled( 'flxs_queue_tick' );

	flxs_admin_header(
		'Gönderim kuyruğu',
		array(),
		'İşleyici: <strong>' . esc_html( flxs_queue_runner_name() ) . '</strong>. Bu sayfa açıkken bekleyen mesajlar tarayıcıdan da tetiklenir.'
	);
	echo '<div data-queue-live="' . (int) $pending . '"></div>';

	if ( ! $batch ) {
		$batches = flxs_recent_batches( 4 );
		if ( $batches ) {
			echo '<div class="flxs-batches">';
			foreach ( $batches as $b ) {
				$c    = $b['counts'];
				$done = $c['sent'] + $c['failed'] + $c['cancelled'];
				$pct  = $c['total'] ? round( 100 * $done / $c['total'] ) : 0;
				printf(
					'<a class="flxs-batch" href="%s"><div class="flxs-batch__name">%s %s</div><div class="flxs-bar"><span style="width:%d%%"></span></div><div class="flxs-muted">%d/%d · %d ✓ · %d ✕ · %s · %s</div></a>',
					esc_url( add_query_arg( 'batch', $b['id'], $base ) ),
					flxs_channel_icon( $b['channel'] ), // phpcs:ignore
					esc_html( $b['name'] ),
					(int) $pct,
					(int) $done,
					(int) $c['total'],
					(int) $c['sent'],
					(int) $c['failed'],
					esc_html( flxs_user_label( $b['created_by'] ) ),
					esc_html( flxs_date( $b['created_at'] ) )
				);
			}
			echo '</div>';
		}
	} else {
		echo '<p><a href="' . esc_url( remove_query_arg( 'batch', $base ) ) . '">← Tüm mesajlar</a></p>';
	}
	?>
	<div class="flxs-tabs">
		<a href="<?php echo esc_url( $base ); ?>" class="<?php echo '' === $status ? 'is-on' : ''; ?>">Tümü <b><?php echo (int) $counts['total']; ?></b></a>
		<?php foreach ( $labels as $k => $l ) : ?>
			<a href="<?php echo esc_url( add_query_arg( 'status', $k, $base ) ); ?>" class="flxs-tab--<?php echo esc_attr( $k ); ?> <?php echo $status === $k ? 'is-on' : ''; ?>"><?php echo esc_html( $l ); ?> <b><?php echo (int) $counts[ $k ]; ?></b></a>
		<?php endforeach; ?>
		<span class="flxs-tabs__right">
			<?php if ( $pending ) : ?>
				<button type="button" class="button button-small" data-queue-run>Şimdi işle</button>
			<?php endif; ?>
		</span>
	</div>

	<form method="get" class="flxs-filters"<?php echo flxs_list_form_attrs( 'flxs-queue' ); // phpcs:ignore ?>>
		<input type="hidden" name="page" value="flxs-kuyruk">
		<?php if ( $status ) : ?><input type="hidden" name="status" value="<?php echo esc_attr( $status ); ?>"><?php endif; ?>
		<?php if ( $batch ) : ?><input type="hidden" name="batch" value="<?php echo (int) $batch; ?>"><?php endif; ?>
		<input type="search" name="s" value="<?php echo esc_attr( $args['search'] ); ?>" placeholder="Alıcı, numara, metin…">
		<select name="channel" data-flxa-primary>
			<option value="">Tüm kanallar</option>
			<?php foreach ( flxs_channels() as $k => $l ) : ?>
				<option value="<?php echo esc_attr( $k ); ?>" <?php selected( $args['channel'], $k ); ?>><?php echo esc_html( $l ); ?></option>
			<?php endforeach; ?>
		</select>
		<button class="button">Filtrele</button>
	</form>

	<table class="widefat flxs-table flxs-table--queue">
		<thead><tr><th>#</th><th>Alıcı</th><th>Kanal</th><th>Durum</th><th>Zaman</th><th>Gönderen</th><th></th></tr></thead>
		<tbody>
		<?php if ( ! $q['items'] ) : ?>
			<tr><td colspan="7" class="flxs-empty">Mesaj yok.</td></tr>
		<?php endif; ?>
		<?php
		foreach ( $q['items'] as $m ) :
			$prov = $m['provider_id'] ? flxs_get_provider( $m['provider_id'] ) : null;
			$pn   = $prov ? $prov['name'] : ( 'email' === $m['channel'] ? 'wp_mail' : '?' );
			?>
			<tr>
				<td data-label="#"><?php echo (int) $m['id']; ?></td>
				<td data-label="Alıcı">
					<strong><?php echo esc_html( $m['recipient_name'] ? $m['recipient_name'] : '–' ); ?></strong>
					<div class="flxs-muted"><?php echo esc_html( 'email' === $m['channel'] ? $m['recipient'] : flxs_format_phone( $m['recipient'] ) ); ?></div>
				</td>
				<td data-label="Kanal"><?php echo flxs_channel_icon( $m['channel'] ); // phpcs:ignore ?> <?php echo esc_html( $pn ); ?></td>
				<td data-label="Durum">
					<?php echo flxs_status_badge( $m['status'] ); // phpcs:ignore ?>
					<?php if ( (int) $m['attempts'] > 1 || ( 'queued' === $m['status'] && $m['attempts'] ) ) : ?>
						<span class="flxs-muted"><?php echo (int) $m['attempts']; ?>. deneme</span>
					<?php endif; ?>
					<?php if ( $m['last_error'] ) : ?>
						<div class="flxs-err"><?php echo esc_html( $m['last_error'] ); ?></div>
					<?php endif; ?>
				</td>
				<td data-label="Zaman" class="flxs-muted">
					<?php if ( 'sent' === $m['status'] ) : ?>
						Gönderildi <?php echo esc_html( flxs_date( $m['sent_at'] ) ); ?>
					<?php elseif ( 'queued' === $m['status'] ) : ?>
						Planlanan <?php echo esc_html( flxs_date( $m['scheduled_at'], 'd.m H:i:s' ) ); ?>
					<?php else : ?>
						<?php echo esc_html( flxs_date( $m['updated_at'] ) ); ?>
					<?php endif; ?>
					<div>Eklendi <?php echo esc_html( flxs_date( $m['created_at'] ) ); ?></div>
				</td>
				<td data-label="Gönderen"><?php echo esc_html( flxs_user_label( $m['created_by'] ) ); ?><div class="flxs-muted"><?php echo esc_html( $m['source'] ); ?></div></td>
				<td class="flxs-actions-cell">
					<button type="button" class="button button-small" data-toggle="flxs-m<?php echo (int) $m['id']; ?>">Detay</button>
					<?php if ( in_array( $m['status'], array( 'failed', 'cancelled' ), true ) ) : ?>
						<button type="button" class="button button-small" data-msg-op="retry" data-id="<?php echo (int) $m['id']; ?>">Tekrar dene</button>
					<?php elseif ( 'queued' === $m['status'] ) : ?>
						<button type="button" class="button button-small" data-msg-op="cancel" data-id="<?php echo (int) $m['id']; ?>" data-confirm="Mesaj iptal edilsin mi?">İptal</button>
					<?php endif; ?>
				</td>
			</tr>
			<tr id="flxs-m<?php echo (int) $m['id']; ?>" class="flxs-detail" hidden>
				<td colspan="7">
					<div class="flxs-detail__grid">
						<div>
							<?php if ( $m['subject'] ) : ?><div><strong>Konu:</strong> <?php echo esc_html( $m['subject'] ); ?></div><?php endif; ?>
							<pre class="flxs-pre"><?php echo esc_html( str_replace( '{buton}', '[Sertifikayı görüntüle butonu]', $m['body'] ) ); ?></pre>
							<?php if ( $m['certificate_id'] ) : ?>
								<a href="<?php echo esc_url( admin_url( 'admin.php?page=flxs-kuyruk&cert=' . (int) $m['certificate_id'] ) ); ?>">Bu sertifikanın tüm gönderimleri</a>
							<?php endif; ?>
						</div>
						<div>
							<div><strong>Sağlayıcı yanıtı</strong> <?php echo $m['external_id'] ? '· ID: <code>' . esc_html( $m['external_id'] ) . '</code>' : ''; ?></div>
							<pre class="flxs-pre"><?php echo esc_html( $m['response'] ? wp_json_encode( json_decode( $m['response'], true ), JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES ) : '–' ); ?></pre>
						</div>
					</div>
				</td>
			</tr>
		<?php endforeach; ?>
		</tbody>
	</table>
	<?php
	flxs_list_pages( $q['total'], $per, $paged );
	if ( ! $next && ! function_exists( 'as_schedule_single_action' ) ) {
		echo '<p class="flxs-muted">Not: WP-Cron zamanlaması bulunamadı; eklentiyi devre dışı bırakıp yeniden etkinleştirin.</p>';
	}
	flxs_admin_footer();
}
