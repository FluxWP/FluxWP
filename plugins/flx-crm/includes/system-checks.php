<?php
/** Ortak sistem kontrolü (`flx_system_checks`). Dış HTTP yok. */

defined( 'ABSPATH' ) || exit;

add_filter( 'flx_system_checks', 'flxcrm_system_checks' );
add_filter(
	'flx_system_checks_versions',
	function ( $v ) {
		$v['flx-crm'] = FLXCRM_VERSION;
		return $v;
	}
);

function flxcrm_system_checks( $checks ) {
	$checks = is_array( $checks ) ? $checks : array();
	$src    = __( 'Flux CRM', 'flx-crm' );

	$sources = FLXCRM_Sync::sources();
	$names   = array();
	foreach ( $sources as $s ) {
		$names[] = $s[0];
	}
	$checks[] = array(
		'id'           => 'flxcrm.sources',
		'source'       => $src,
		'group'        => 'Config',
		'label'        => __( 'Kişi kaynakları', 'flx-crm' ),
		'status'       => $names ? 'ok' : 'warn',
		'message'      => $names ? implode( ', ', $names ) : __( 'Kişi getiren kaynak yok (Flux Donate, WooCommerce, Flux Chat, Flux Forms kapalı ya da kurulu değil). Kişiler yalnız elle eklenir.', 'flx-crm' ),
		'action_url'   => flxcrm_admin_url( 'settings' ),
		'action_label' => __( 'Ayarlar', 'flx-crm' ),
	);

	$synced   = (int) get_option( 'flxcrm_synced_at' );
	$checks[] = array(
		'id'           => 'flxcrm.sync',
		'source'       => $src,
		'group'        => 'Config',
		'label'        => __( 'İlk eşitleme', 'flx-crm' ),
		'status'       => $synced ? 'ok' : 'info',
		'message'      => $synced ? sprintf( /* translators: %s: date */ __( 'Son tam eşitleme: %s', 'flx-crm' ), wp_date( 'd.m.Y H:i', $synced ) ) : __( 'Geçmiş bağış, sipariş ve sohbetler henüz alınmadı. CRM › Ayarlar › "Tümünü eşitle".', 'flx-crm' ),
		'action_url'   => flxcrm_admin_url( 'settings' ),
		'action_label' => __( 'Eşitle', 'flx-crm' ),
	);

	$scored   = (int) get_option( 'flxcrm_scored_at' );
	$late     = $scored && $scored < time() - 3 * DAY_IN_SECONDS;
	$checks[] = array(
		'id'      => 'flxcrm.scoring',
		'source'  => $src,
		'group'   => 'Cron',
		'label'   => __( 'RFM skorlama', 'flx-crm' ),
		'status'  => ! $scored ? 'info' : ( $late ? 'warn' : 'ok' ),
		'message' => ! $scored ? __( 'Henüz hesaplanmadı.', 'flx-crm' ) : ( $late ? sprintf( /* translators: %s: date */ __( 'Son hesaplama %s. Günlük iş (WP-Cron) çalışmıyor olabilir; sunucu cron\'u ekleyin ya da elle hesaplayın.', 'flx-crm' ), wp_date( 'd.m.Y H:i', $scored ) ) : sprintf( /* translators: %s: date */ __( 'Son hesaplama: %s', 'flx-crm' ), wp_date( 'd.m.Y H:i', $scored ) ) ),
	);

	$fx = FLXCRM_Events::unconverted();
	if ( $fx ) {
		$checks[] = array(
			'id'      => 'flxcrm.currency',
			'source'  => $src,
			'group'   => 'Config',
			'label'   => __( 'Döviz ödemeleri', 'flx-crm' ),
			'status'  => 'warn',
			/* translators: 1: count, 2: currency */
			'message' => sprintf( __( '%1$s ödeme %2$s dışında bir para biriminde ve kur bulunamadı; sıklığa sayılıyor, toplam değere sayılmıyor. Kur için `flxcrm_fx_rate` filtresini kullanın.', 'flx-crm' ), number_format_i18n( $fx ), flxcrm_currency() ),
		);
	}
	return $checks;
}
