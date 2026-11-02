<?php
defined( 'ABSPATH' ) || exit;

add_action( 'admin_post_flxs_person_save', 'flxs_handle_person_save' );
add_action( 'admin_post_flxs_person_delete', 'flxs_handle_person_delete' );
add_action( 'admin_post_flxs_people_bulk', 'flxs_handle_people_bulk' );

function flxs_handle_person_save() {
	check_admin_referer( 'flxs_person_save' );
	if ( ! flxs_can_use() ) {
		wp_die( 'Yetki yok' );
	}
	global $wpdb;
	$id    = (int) ( $_POST['id'] ?? 0 );
	$name  = sanitize_text_field( wp_unslash( $_POST['name'] ?? '' ) );
	$phone = sanitize_text_field( wp_unslash( $_POST['phone'] ?? '' ) );
	$email = sanitize_text_field( wp_unslash( $_POST['email'] ?? '' ) );
	$note  = sanitize_textarea_field( wp_unslash( $_POST['note'] ?? '' ) );

	if ( ! $id ) {
		$r = flxs_upsert_person( $name, $phone, $email );
		if ( is_wp_error( $r ) ) {
			flxs_redirect( 'flxs-kisiler', array( 'add' => 1, 'flxs_err' => $r->get_error_message() ) );
		}
		$id = (int) $r['person']['id'];
		if ( $note ) {
			$wpdb->update( flxs_table( 'people' ), array( 'note' => $note ), array( 'id' => $id ) );
		}
		flxs_redirect( 'flxs-kisiler', array( 'person' => $id, 'flxs_msg' => $r['created'] ? 'Kişi eklendi.' : 'Bu telefon/e-posta zaten kayıtlıydı, kişi güncellendi.' ) );
	}

	if ( ! flxs_can_see_person( $id ) ) {
		wp_die( 'Yetki yok' );
	}
	$p      = flxs_get_person( $id );
	$nphone = flxs_normalize_phone( $phone );
	$nemail = strtolower( sanitize_email( $email ) );
	if ( $phone && ! $nphone ) {
		flxs_redirect( 'flxs-kisiler', array( 'person' => $id, 'flxs_err' => 'Telefon numarası geçersiz.' ) );
	}
	if ( $nphone && ( $o = flxs_find_person_by_phone( $nphone ) ) && (int) $o['id'] !== $id ) {
		flxs_redirect( 'flxs-kisiler', array( 'person' => $id, 'flxs_err' => 'Bu telefon başka bir kişide kayıtlı: ' . $o['name'] ) );
	}
	if ( $nemail && ( $o = flxs_find_person_by_email( $nemail ) ) && (int) $o['id'] !== $id ) {
		flxs_redirect( 'flxs-kisiler', array( 'person' => $id, 'flxs_err' => 'Bu e-posta başka bir kişide kayıtlı: ' . $o['name'] ) );
	}
	$data = array( 'name' => $name, 'phone' => $nphone ? $nphone : null, 'email' => $nemail ? $nemail : null, 'note' => $note, 'updated_at' => flxs_now() );
	$wpdb->update( flxs_table( 'people' ), $data, array( 'id' => $id ) );
	flxs_log( 'person_updated', 'person', $id, $name, array( 'before' => array_intersect_key( $p, $data ), 'after' => $data ) );
	flxs_redirect( 'flxs-kisiler', array( 'person' => $id, 'flxs_msg' => 'Kaydedildi.' ) );
}

function flxs_handle_person_delete() {
	check_admin_referer( 'flxs_person_delete' );
	if ( ! flxs_can_manage() ) {
		wp_die( 'Yetki yok' );
	}
	$r = flxs_delete_person( (int) $_POST['id'] );
	if ( is_wp_error( $r ) ) {
		flxs_redirect( 'flxs-kisiler', array( 'person' => (int) $_POST['id'], 'flxs_err' => $r->get_error_message() ) );
	}
	flxs_redirect( 'flxs-kisiler', array( 'flxs_msg' => 'Kişi silindi.' ) );
}

function flxs_handle_people_bulk() {
	check_admin_referer( 'flxs_people_bulk' );
	$ids = array_filter( array_map( 'intval', (array) ( $_POST['ids'] ?? array() ) ) );
	if ( ! $ids ) {
		flxs_redirect( 'flxs-kisiler', array( 'flxs_err' => 'Kişi seçmediniz.' ) );
	}
	flxs_redirect( 'flxs-toplu', array( 'people' => implode( ',', $ids ), 'channel' => sanitize_key( $_POST['channel'] ?? 'sms' ) ) );
}

function flxs_page_people() {
	$pid = (int) flxs_get_param( 'person' );
	if ( $pid ) {
		flxs_page_person_detail( $pid );
		return;
	}
	if ( flxs_get_param( 'add' ) ) {
		flxs_admin_header( 'Kişi ekle' );
		flxs_person_form( array( 'id' => 0, 'name' => '', 'phone' => '', 'email' => '', 'note' => '' ) );
		flxs_admin_footer();
		return;
	}
	global $wpdb;
	$per   = flxs_list_per_page( 'flxs-people', 30 );
	$paged = max( 1, (int) flxs_get_param( 'paged', 1 ) );
	$s     = flxs_get_param( 's' );
	$pt    = flxs_table( 'people' );
	$ct    = flxs_table( 'certificates' );
	$where = '1=1';
	$prm   = array();
	if ( $s ) {
		$like  = '%' . $wpdb->esc_like( $s ) . '%';
		$dig   = preg_replace( '/\D+/', '', $s );
		$where = '(p.name LIKE %s OR p.email LIKE %s' . ( strlen( $dig ) >= 3 ? ' OR p.phone LIKE %s' : '' ) . ')';
		$prm   = array( $like, $like );
		if ( strlen( $dig ) >= 3 ) {
			$prm[] = '%' . $wpdb->esc_like( ltrim( $dig, '0' ) ) . '%';
		}
	}
	if ( $sc = flxs_people_scope_sql( 'p' ) ) {
		$where .= ' AND ' . $sc;
	}
	$total = (int) $wpdb->get_var( $prm ? $wpdb->prepare( "SELECT COUNT(*) FROM $pt p WHERE $where", $prm ) : "SELECT COUNT(*) FROM $pt p WHERE $where" );
	$sql   = "SELECT p.*, (SELECT COUNT(*) FROM $ct c WHERE c.person_id = p.id) AS certs FROM $pt p WHERE $where ORDER BY p.id DESC LIMIT %d OFFSET %d";
	$rows  = $wpdb->get_results( $wpdb->prepare( $sql, array_merge( $prm, array( $per, ( $paged - 1 ) * $per ) ) ), ARRAY_A );

	flxs_admin_header( 'Kişiler', array( array( 'label' => '+ Kişi ekle', 'url' => admin_url( 'admin.php?page=flxs-kisiler&add=1' ), 'class' => 'button-primary' ) ), 'Sertifika alan herkes burada kayıtlı. Aynı telefon ya da e-postayla yeni sertifika verilince mevcut kişiye bağlanır; iletişim bilgisi girilmeyen kişiler ayrı kayıt olur.' );
	?>
	<form method="get" class="flxs-filters"<?php echo flxs_list_form_attrs( 'flxs-people' ); // phpcs:ignore ?>>
		<input type="hidden" name="page" value="flxs-kisiler">
		<input type="search" name="s" value="<?php echo esc_attr( $s ); ?>" placeholder="Ad, telefon, e-posta…">
		<button class="button">Ara</button>
	</form>
	<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
		<?php wp_nonce_field( 'flxs_people_bulk' ); ?>
		<input type="hidden" name="action" value="flxs_people_bulk">
		<?php if ( flxs_can_bulk() ) : ?>
			<div class="flxs-bulkbar">
				<select name="channel"><option value="whatsapp">WhatsApp</option><option value="sms">SMS</option><option value="email">E-posta</option></select>
				<button class="button">Seçilenlere mesaj yaz</button>
			</div>
		<?php endif; ?>
		<table class="widefat flxs-table">
			<thead><tr><td class="check-column"><input type="checkbox" class="flxs-check-all"></td><th>Ad Soyad</th><th>Telefon</th><th>E-posta</th><th>Sertifika</th><th>Kayıt</th></tr></thead>
			<tbody>
			<?php if ( ! $rows ) : ?>
				<tr><td colspan="6" class="flxs-empty">Kayıt yok.</td></tr>
			<?php endif; ?>
			<?php foreach ( $rows as $p ) : ?>
				<tr>
					<th class="check-column"><input type="checkbox" name="ids[]" value="<?php echo (int) $p['id']; ?>"></th>
					<td data-label="Ad Soyad"><strong><a href="<?php echo esc_url( admin_url( 'admin.php?page=flxs-kisiler&person=' . (int) $p['id'] ) ); ?>"><?php echo esc_html( $p['name'] ); ?></a></strong></td>
					<td data-label="Telefon"><?php echo esc_html( flxs_format_phone( $p['phone'] ) ); ?></td>
					<td data-label="E-posta"><?php echo esc_html( $p['email'] ); ?></td>
					<td data-label="Sertifika"><a href="<?php echo esc_url( admin_url( 'admin.php?page=flxs&person=' . (int) $p['id'] ) ); ?>"><?php echo (int) $p['certs']; ?></a></td>
					<td data-label="Kayıt"><?php echo esc_html( flxs_date( $p['created_at'], 'd.m.Y' ) ); ?> · <?php echo esc_html( flxs_user_label( $p['created_by'] ) ); ?></td>
				</tr>
			<?php endforeach; ?>
			</tbody>
		</table>
	</form>
	<?php
	flxs_list_pages( $total, $per, $paged );
	flxs_admin_footer();
}

function flxs_person_form( $p ) {
	$phone = $p['phone'] ? ( 0 === strpos( $p['phone'], '90' ) && 12 === strlen( $p['phone'] ) ? '0' . substr( $p['phone'], 2 ) : '+' . $p['phone'] ) : '';
	?>
	<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="flxs-card flxs-form">
		<?php wp_nonce_field( 'flxs_person_save' ); ?>
		<input type="hidden" name="action" value="flxs_person_save">
		<input type="hidden" name="id" value="<?php echo (int) $p['id']; ?>">
		<div class="flxs-grid">
			<label class="flxs-field"><span>Ad Soyad *</span><input type="text" name="name" required value="<?php echo esc_attr( $p['name'] ); ?>"></label>
			<label class="flxs-field"><span>Telefon</span><input type="tel" name="phone" value="<?php echo esc_attr( $phone ); ?>" placeholder="0532 123 45 67"></label>
			<label class="flxs-field"><span>E-posta</span><input type="email" name="email" value="<?php echo esc_attr( $p['email'] ); ?>"></label>
		</div>
		<label class="flxs-field"><span>Not</span><textarea name="note" rows="2"><?php echo esc_textarea( $p['note'] ?? '' ); ?></textarea></label>
		<p><button class="button button-primary">Kaydet</button></p>
	</form>
	<?php
}

function flxs_page_person_detail( $id ) {
	$p = flxs_get_person( $id );
	if ( ! $p || ! flxs_can_see_person( $id ) ) {
		wp_die( 'Kişi bulunamadı.' );
	}
	$certs = flxs_query_certificates( array( 'person_id' => $id, 'per_page' => 50, 'scope' => true ) );
	$msgs  = flxs_query_messages( array( 'person_id' => $id, 'per_page' => 50, 'scope' => true ) );
	flxs_admin_header(
		$p['name'],
		array(
			array( 'label' => '+ Bu kişiye sertifika', 'url' => admin_url( 'admin.php?page=flxs-yeni' ), 'class' => 'button-primary' ),
			array( 'label' => '← Kişiler', 'url' => admin_url( 'admin.php?page=flxs-kisiler' ) ),
		),
		'Kayıt: ' . esc_html( flxs_date( $p['created_at'] ) . ' · ' . flxs_user_label( $p['created_by'] ) )
	);
	echo '<div class="flxs-two">';
	echo '<div>';
	flxs_person_form( $p );
	if ( flxs_can_manage() ) {
		?>
		<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
			<?php wp_nonce_field( 'flxs_person_delete' ); ?>
			<input type="hidden" name="action" value="flxs_person_delete"><input type="hidden" name="id" value="<?php echo (int) $id; ?>">
			<button class="button-link flxs-danger" data-confirm="Kişi silinsin mi? (Sertifikası olan kişi silinemez)">Kişiyi sil</button>
		</form>
		<?php
	}
	echo '</div><div>';
	echo '<div class="flxs-card"><h2 class="flxs-card__title">Sertifikalar (' . (int) $certs['total'] . ')</h2>';
	if ( ! $certs['items'] ) {
		echo '<p class="flxs-muted">Sertifika yok.</p>';
	}
	foreach ( $certs['items'] as $c ) {
		printf(
			'<div class="flxs-li"><a href="%s" target="_blank"><strong>%s</strong></a> · %s · %s %s</div>',
			esc_url( flxs_certificate_url( $c ) ),
			esc_html( $c['number'] ),
			esc_html( $c['template_name'] ),
			esc_html( flxs_date( $c['created_at'], 'd.m.Y' ) ),
			flxs_status_badge( $c['status'] ) // phpcs:ignore
		);
	}
	echo '</div>';
	echo '<div class="flxs-card"><h2 class="flxs-card__title">Gönderilen mesajlar (' . (int) $msgs['total'] . ')</h2>';
	if ( ! $msgs['items'] ) {
		echo '<p class="flxs-muted">Mesaj yok.</p>';
	}
	foreach ( $msgs['items'] as $m ) {
		printf(
			'<div class="flxs-li">%s %s · %s · %s <span class="flxs-muted">%s</span></div>',
			flxs_channel_icon( $m['channel'] ), // phpcs:ignore
			esc_html( $m['recipient'] ),
			flxs_status_badge( $m['status'] ), // phpcs:ignore
			esc_html( flxs_date( $m['sent_at'] ? $m['sent_at'] : $m['created_at'] ) ),
			esc_html( flxs_user_label( $m['created_by'] ) )
		);
	}
	echo '</div></div></div>';
	flxs_admin_footer();
}
