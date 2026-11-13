<?php
/** Live delivery queue. */
defined( 'ABSPATH' ) || exit;
function flxi_queue_ui_strings() {
	return array( 'all' => __( 'All sends', 'flx-mail' ), 'empty' => __( 'No queue entries yet.', 'flx-mail' ), 'stop' => __( 'Stop queued messages', 'flx-mail' ), 'pause' => __( 'Pause', 'flx-mail' ), 'eta' => __( 'Estimated finish:', 'flx-mail' ), 'resume' => __( 'Resume', 'flx-mail' ), 'cancel' => __( 'Request Netgsm cancellation', 'flx-mail' ), 'report' => __( 'Check delivery reports', 'flx-mail' ), 'checked' => __( 'Last report', 'flx-mail' ), 'confirm' => __( 'Continue with this action?', 'flx-mail' ), 'error' => __( 'Could not refresh. Retrying shortly.', 'flx-mail' ), 'worker' => __( 'Worker active', 'flx-mail' ), 'native' => __( 'WordPress background processing', 'flx-mail' ), 'ready' => __( 'Updated', 'flx-mail' ) );
}
function flxi_page_queue() {
	if ( ! flxi_can_manage() ) { return; }
	flxi_page_head( __( 'Delivery queue', 'flx-mail' ), __( 'Live email, WhatsApp and SMS progress. Acceptance by a provider is not proof of delivery.', 'flx-mail' ) );
	echo '<div id="flxi-queue" data-campaign="' . absint( $_GET['campaign'] ?? 0 ) . '">'; // phpcs:ignore WordPress.Security.NonceVerification
	echo '<div class="flxi-card"><div class="flxi-queue-toolbar"><label>' . esc_html__( 'Campaign', 'flx-mail' ) . ' <select data-campaign-picker></select></label><button class="button" data-pause hidden>' . esc_html__( 'Pause', 'flx-mail' ) . '</button><button class="button button-primary" data-resume hidden>' . esc_html__( 'Resume', 'flx-mail' ) . '</button><span class="flxi-muted" data-eta></span><button class="button" data-stop hidden>' . esc_html__( 'Stop queued messages', 'flx-mail' ) . '</button><span data-health role="status" aria-live="polite"></span></div><div class="flxi-queue-summary" data-summary></div><p class="flxi-muted">' . esc_html__( 'Stopping the local queue does not recall messages already accepted by a provider. Netgsm cancellation is available only for future scheduled sends.', 'flx-mail' ) . '</p></div>';
	echo '<div data-batches></div><div class="flxi-card"><h2>' . esc_html__( 'Recipients', 'flx-mail' ) . '</h2><div class="flxi-tablewrap flxi-queue-recipients"><table class="flxi-table"><thead><tr>';
	foreach ( array( __( 'Recipient', 'flx-mail' ), __( 'Channel', 'flx-mail' ), __( 'Status', 'flx-mail' ), __( 'Attempts', 'flx-mail' ), __( 'Provider job ID', 'flx-mail' ), __( 'Date', 'flx-mail' ), __( 'Error', 'flx-mail' ) ) as $label ) { echo '<th scope="col">' . esc_html( $label ) . '</th>'; }
	echo '</tr></thead><tbody data-rows></tbody></table></div><div class="flxi-queue-toolbar"><button class="button" data-prev>' . esc_html__( 'Previous', 'flx-mail' ) . '</button><span data-page></span><button class="button" data-next>' . esc_html__( 'Next', 'flx-mail' ) . '</button></div></div>';
	echo '<div class="flxi-card"><h2>' . esc_html__( 'Operational history', 'flx-mail' ) . '</h2><p class="flxi-muted">' . esc_html__( 'Recent background events across plugins. Message content and credentials are excluded. Detailed delivery records remain in Log.', 'flx-mail' ) . '</p><ol class="flxi-queue-events" data-events></ol></div></div>';
	flxi_page_foot();
}
