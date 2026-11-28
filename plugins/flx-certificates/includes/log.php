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
		'plugin_activated'     => __( 'Plugin activated', 'flx-certificates' ),
		'certificate_created'  => __( 'Certificate created', 'flx-certificates' ),
		'certificate_updated'  => __( 'Certificate updated', 'flx-certificates' ),
		'event_saved'          => __( 'Event saved', 'flx-certificates' ),
		'event_closed'         => __( 'Event closed (archived)', 'flx-certificates' ),
		'event_reopened'       => __( 'Event reopened', 'flx-certificates' ),
		'event_deleted'        => __( 'Event deleted', 'flx-certificates' ),
		'certificate_revoked'  => __( 'Certificate revoked', 'flx-certificates' ),
		'certificate_restored' => __( 'Certificate restored', 'flx-certificates' ),
		'certificate_deleted'  => __( 'Certificate deleted', 'flx-certificates' ),
		'certificates_exported' => __( 'Certificates exported to Excel', 'flx-certificates' ),
		'files_rendered'        => __( 'Files generated on the server', 'flx-certificates' ),
		'files_render_failed'   => __( 'Server file generation failed', 'flx-certificates' ),
		'person_created'       => __( 'Person added', 'flx-certificates' ),
		'person_updated'       => __( 'Person updated', 'flx-certificates' ),
		'person_deleted'       => __( 'Person deleted', 'flx-certificates' ),
		'template_saved'       => __( 'Template saved', 'flx-certificates' ),
		'template_deleted'     => __( 'Template deleted', 'flx-certificates' ),
		'provider_saved'       => __( 'Provider saved', 'flx-certificates' ),
		'provider_deleted'     => __( 'Provider deleted', 'flx-certificates' ),
		'provider_test'        => __( 'Provider test', 'flx-certificates' ),
		'settings_saved'       => __( 'Settings saved', 'flx-certificates' ),
		'message_queued'       => __( 'Message queued', 'flx-certificates' ),
		'message_sent'         => __( 'Message sent', 'flx-certificates' ),
		'message_failed'       => __( 'Message failed', 'flx-certificates' ),
		'message_retry'        => __( 'Message scheduled for retry', 'flx-certificates' ),
		'message_cancelled'    => __( 'Message cancelled', 'flx-certificates' ),
		'batch_created'        => __( 'Bulk send started', 'flx-certificates' ),
		'lookup_request'       => __( 'Certificate lookup', 'flx-certificates' ),
		'recipient_saved'      => __( 'Number added to contacts', 'flx-certificates' ),
		'recipient_deleted'    => __( 'Number removed from contacts', 'flx-certificates' ),
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
