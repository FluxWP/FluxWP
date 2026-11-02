<?php
defined( 'ABSPATH' ) || exit;

add_action( 'admin_post_flxs_logs_csv', 'flxs_handle_logs_csv' );

function flxs_logs_query( $a, $limit = 50, $offset = 0 ) {
	global $wpdb;
	$t     = flxs_table( 'logs' );
	$where = array( '1=1' );
	$prm   = array();
	if ( $a['action'] ) {
		$where[] = 'action = %s';
		$prm[]   = $a['action'];
	}
	if ( $a['user'] ) {
		$where[] = 'user_id = %d';
		$prm[]   = (int) $a['user'];
	}
	if ( $a['type'] ) {
		$where[] = 'object_type = %s';
		$prm[]   = $a['type'];
	}
	if ( $a['object'] ) {
		$where[] = 'object_id = %d';
		$prm[]   = (int) $a['object'];
	}
	if ( $a['from'] ) {
		$where[] = 'created_at >= %s';
		$prm[]   = get_gmt_from_date( $a['from'] . ' 00:00:00' );
	}
	if ( $a['to'] ) {
		$where[] = 'created_at <= %s';
		$prm[]   = get_gmt_from_date( $a['to'] . ' 23:59:59' );
	}
	if ( $a['s'] ) {
		$like    = '%' . $wpdb->esc_like( $a['s'] ) . '%';
		$where[] = '(message LIKE %s OR context LIKE %s)';
		array_push( $prm, $like, $like );
	}
	$w     = implode( ' AND ', $where );
	$total = (int) $wpdb->get_var( $prm ? $wpdb->prepare( "SELECT COUNT(*) FROM $t WHERE $w", $prm ) : "SELECT COUNT(*) FROM $t WHERE $w" );
	$sql   = "SELECT * FROM $t WHERE $w ORDER BY id DESC" . ( $limit ? $wpdb->prepare( ' LIMIT %d OFFSET %d', $limit, $offset ) : '' );
	return array( 'total' => $total, 'items' => $wpdb->get_results( $prm ? $wpdb->prepare( $sql, $prm ) : $sql, ARRAY_A ) );
}

function flxs_logs_args() {
	return array(
		'action' => sanitize_key( flxs_get_param( 'act' ) ),
		'user'   => (int) flxs_get_param( 'user' ),
		'type'   => sanitize_key( flxs_get_param( 'type' ) ),
		'object' => (int) flxs_get_param( 'object' ),
		'from'   => preg_match( '/^\d{4}-\d{2}-\d{2}$/', flxs_get_param( 'from' ) ) ? flxs_get_param( 'from' ) : '',
		'to'     => preg_match( '/^\d{4}-\d{2}-\d{2}$/', flxs_get_param( 'to' ) ) ? flxs_get_param( 'to' ) : '',
		's'      => flxs_get_param( 's' ),
	);
}

function flxs_handle_logs_csv() {
	check_admin_referer( 'flxs_logs_csv' );
	if ( ! flxs_can_manage() ) {
		wp_die( 'Yetki yok' );
	}
	$q = flxs_logs_query( flxs_logs_args(), 0 );
	nocache_headers();
	header( 'Content-Type: text/csv; charset=utf-8' );
	header( 'Content-Disposition: attachment; filename="sertifika-log-' . gmdate( 'Ymd-His' ) . '.csv"' );
	$out = fopen( 'php://output', 'w' );
	fwrite( $out, "\xEF\xBB\xBF" ); // Excel için BOM
	fputcsv( $out, array( 'Tarih', 'Kullanıcı', 'İşlem', 'Nesne', 'Nesne ID', 'Açıklama', 'Detay', 'IP' ), ';' );
	$labels = flxs_log_actions();
	foreach ( $q['items'] as $l ) {
		fputcsv( $out, array( flxs_date( $l['created_at'], 'Y-m-d H:i:s' ), flxs_user_label( $l['user_id'] ), $labels[ $l['action'] ] ?? $l['action'], $l['object_type'], $l['object_id'], $l['message'], $l['context'], $l['ip'] ), ';' );
	}
	exit;
}

function flxs_page_logs() {
	$a      = flxs_logs_args();
	$per    = flxs_list_per_page( 'flxs-logs', 50 );
	$paged  = max( 1, (int) flxs_get_param( 'paged', 1 ) );
	$q      = flxs_logs_query( $a, $per, ( $paged - 1 ) * $per );
	$labels = flxs_log_actions();
	flxs_admin_header(
		'Kayıtlar (log)',
		array( array( 'label' => 'CSV indir', 'url' => wp_nonce_url( add_query_arg( array_merge( array( 'action' => 'flxs_logs_csv' ), array_filter( $_GET ) ), admin_url( 'admin-post.php' ) ), 'flxs_logs_csv' ) ) ), // phpcs:ignore
		'Kim, ne zaman, neyi yaptı: sertifika oluşturma/düzenleme/iptal, her mesajın sonucu (gönderen kişiyle), sağlayıcı ve ayar değişiklikleri.'
	);
	?>
	<form method="get" class="flxs-filters"<?php echo flxs_list_form_attrs( 'flxs-logs' ); // phpcs:ignore ?>>
		<input type="hidden" name="page" value="flxs-kayitlar">
		<input type="search" name="s" value="<?php echo esc_attr( $a['s'] ); ?>" placeholder="Ara…">
		<select name="act" data-flxa-primary>
			<option value="">Tüm işlemler</option>
			<?php foreach ( $labels as $k => $l ) : ?>
				<option value="<?php echo esc_attr( $k ); ?>" <?php selected( $a['action'], $k ); ?>><?php echo esc_html( $l ); ?></option>
			<?php endforeach; ?>
		</select>
		<select name="user" data-flxa-primary>
			<option value="">Tüm kullanıcılar</option>
			<?php foreach ( flxs_users_with_cap() as $u ) : ?>
				<option value="<?php echo (int) $u->ID; ?>" <?php selected( $a['user'], $u->ID ); ?>><?php echo esc_html( $u->display_name ); ?></option>
			<?php endforeach; ?>
		</select>
		<div class="flxs-daterange"><input type="date" name="from" value="<?php echo esc_attr( $a['from'] ); ?>"> – <input type="date" name="to" value="<?php echo esc_attr( $a['to'] ); ?>"></div>
		<button class="button">Filtrele</button>
	</form>
	<table class="widefat flxs-table flxs-table--logs">
		<thead><tr><th>Zaman</th><th>Kullanıcı</th><th>İşlem</th><th>Açıklama</th><th>IP</th></tr></thead>
		<tbody>
		<?php if ( ! $q['items'] ) : ?>
			<tr><td colspan="5" class="flxs-empty">Kayıt yok.</td></tr>
		<?php endif; ?>
		<?php foreach ( $q['items'] as $l ) : ?>
			<tr>
				<td data-label="Zaman" class="flxs-nowrap"><?php echo esc_html( flxs_date( $l['created_at'], 'd.m.Y H:i:s' ) ); ?></td>
				<td data-label="Kullanıcı"><?php echo esc_html( flxs_user_label( $l['user_id'] ) ); ?></td>
				<td data-label="İşlem"><span class="flxs-act flxs-act--<?php echo esc_attr( $l['action'] ); ?>"><?php echo esc_html( $labels[ $l['action'] ] ?? $l['action'] ); ?></span></td>
				<td data-label="Açıklama">
					<?php
					echo esc_html( $l['message'] );
					if ( 'certificate' === $l['object_type'] && $l['object_id'] ) {
						$c = flxs_get_certificate( $l['object_id'] );
						if ( $c ) {
							echo ' · <a href="' . esc_url( flxs_certificate_url( $c ) ) . '" target="_blank">aç</a>';
						}
					} elseif ( 'message' === $l['object_type'] ) {
						echo ' · <a href="' . esc_url( admin_url( 'admin.php?page=flxs-kuyruk&s=' . rawurlencode( (string) ( json_decode( (string) $l['context'], true )['recipient'] ?? '' ) ) ) ) . '">kuyrukta</a>';
					}
					if ( $l['context'] ) {
						echo ' <details class="flxs-ctx"><summary>detay</summary><pre class="flxs-pre">' . esc_html( wp_json_encode( json_decode( $l['context'], true ), JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES ) ) . '</pre></details>';
					}
					?>
				</td>
				<td data-label="IP" class="flxs-muted"><?php echo esc_html( $l['ip'] ); ?></td>
			</tr>
		<?php endforeach; ?>
		</tbody>
	</table>
	<?php
	flxs_list_pages( $q['total'], $per, $paged );
	flxs_admin_footer();
}
