<?php
/**
 * Kaldırma: veriler yalnız Ayarlar'da "Eklenti silinirken kayıtları ve sohbetleri de sil" açıksa silinir.
 */
defined( 'WP_UNINSTALL_PLUGIN' ) || exit;

$flxai_settings = get_option( 'flxai_settings', array() );
if ( empty( $flxai_settings['delete_data'] ) ) {
	return;
}
global $wpdb;
foreach ( array( 'log', 'log_body', 'chats', 'media' ) as $flxai_t ) {
	$wpdb->query( 'DROP TABLE IF EXISTS ' . $wpdb->prefix . 'flxai_' . $flxai_t ); // phpcs:ignore
}
foreach ( array( 'flxai_settings', 'flxai_db_version', 'flxai_budget_notified', 'flxai_or_credits', 'flxai_mcp_keys', 'flxai_mcp_log', 'flxai_oauth_clients' ) as $flxai_o ) {
	delete_option( $flxai_o );
}
foreach ( array( 'flxai_models_all', 'flxai_models_anthropic', 'flxai_models_openrouter', 'flxai_prune' ) as $flxai_o ) {
	delete_transient( $flxai_o );
}
foreach ( wp_roles()->role_objects as $flxai_role ) {
	$flxai_role->remove_cap( 'flxai_chat' );
	$flxai_role->remove_cap( 'flxai_mcp' );
}
foreach ( get_users( array( 'capability' => 'flxai_chat', 'fields' => 'ID' ) ) as $flxai_uid ) {
	( new WP_User( $flxai_uid ) )->remove_cap( 'flxai_chat' );
}
foreach ( get_users( array( 'capability' => 'flxai_mcp', 'fields' => 'ID' ) ) as $flxai_uid ) {
	( new WP_User( $flxai_uid ) )->remove_cap( 'flxai_mcp' );
}
