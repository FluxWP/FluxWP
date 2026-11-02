<?php
defined( 'ABSPATH' ) || exit;

/**
 * Denetim kaydı. Kim, ne zaman, neyi, hangi IP'den yaptı.
 * $context içindeki gizli alanlar (şifre, key, token) maskelenir.
 */
function flxs_log( $action, $object_type = '', $object_id = 0, $message = '', $context = array(), $user_id = null ) {
	global $wpdb;
	if ( null === $user_id ) {
		$user_id = get_current_user_id();
	}
	$wpdb->insert(
		flxs_table( 'logs' ),
		array(
			'created_at'  => flxs_now(),
			'user_id'     => (int) $user_id,
			'action'      => substr( (string) $action, 0, 60 ),
			'object_type' => substr( (string) $object_type, 0, 30 ),
			'object_id'   => (int) $object_id,
			'message'     => (string) $message,
			'context'     => $context ? flxs_json( flxs_redact( $context ) ) : null,
			'ip'          => wp_doing_cron() ? '' : flxs_client_ip(),
		)
	);
}

function flxs_log_actions() {
	return array(
		'plugin_activated'     => 'Eklenti etkinleştirildi',
		'certificate_created'  => 'Sertifika oluşturuldu',
		'certificate_updated'  => 'Sertifika güncellendi',
		'event_saved'          => 'Etkinlik kaydedildi',
		'event_closed'         => 'Etkinlik kapatıldı (arşiv)',
		'event_reopened'       => 'Etkinlik yeniden açıldı',
		'event_deleted'        => 'Etkinlik silindi',
		'certificate_revoked'  => 'Sertifika iptal edildi',
		'certificate_restored' => 'Sertifika geri alındı',
		'certificate_deleted'  => 'Sertifika silindi',
		'certificates_exported' => 'Sertifikalar Excel\'e aktarıldı',
		'files_rendered'        => 'Dosyalar sunucuda oluşturuldu',
		'files_render_failed'   => 'Sunucuda dosya oluşturulamadı',
		'person_created'       => 'Kişi eklendi',
		'person_updated'       => 'Kişi güncellendi',
		'person_deleted'       => 'Kişi silindi',
		'template_saved'       => 'Şablon kaydedildi',
		'template_deleted'     => 'Şablon silindi',
		'provider_saved'       => 'Sağlayıcı kaydedildi',
		'provider_deleted'     => 'Sağlayıcı silindi',
		'provider_test'        => 'Sağlayıcı testi',
		'settings_saved'       => 'Ayarlar kaydedildi',
		'message_queued'       => 'Mesaj kuyruğa alındı',
		'message_sent'         => 'Mesaj gönderildi',
		'message_failed'       => 'Mesaj başarısız',
		'message_retry'        => 'Mesaj yeniden denenecek',
		'message_cancelled'    => 'Mesaj iptal edildi',
		'batch_created'        => 'Toplu gönderim başlatıldı',
		'lookup_request'       => 'Sertifika sorgulama',
		'recipient_saved'      => 'Rehbere numara eklendi',
		'recipient_deleted'    => 'Rehberden numara silindi',
	);
}

add_action(
	'flxs_daily',
	function () {
		global $wpdb;
		$days = (int) flxs_setting( 'log_retention' );
		if ( $days > 0 ) {
			$cut = gmdate( 'Y-m-d H:i:s', time() - $days * DAY_IN_SECONDS );
			$wpdb->query( $wpdb->prepare( 'DELETE FROM ' . flxs_table( 'logs' ) . ' WHERE created_at < %s', $cut ) );
		}
	}
);
