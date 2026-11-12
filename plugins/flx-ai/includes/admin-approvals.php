<?php
/**
 * Flux AI & MCP › Onay bekleyenler: tüm sohbet ve görevlerin onay bekleyen işlemleri tek listede.
 *
 * @package FLX_AI
 */

defined( 'ABSPATH' ) || exit;

add_action(
	'admin_init',
	function () {
		if ( empty( $_POST['flxai_approvals_nonce'] ) || ! current_user_can( 'manage_options' ) ) {
			return;
		}
		check_admin_referer( 'flxai_approvals', 'flxai_approvals_nonce' );
		$post = wp_unslash( $_POST ); // phpcs:ignore WordPress.Security.ValidationSanitization
		$do   = sanitize_key( $post['do'] ?? '' );
		$back = admin_url( 'admin.php?page=flx-ai-approvals' );
		if ( in_array( $do, array( 'pr_merge', 'pr_reject' ), true ) && function_exists( 'flxai_change_decide' ) ) {
			$c = flxai_change_get( (int) ( $post['change'] ?? 0 ) );
			$r = $c && $c['user_id'] === get_current_user_id() ? flxai_change_decide( $c, 'pr_merge' === $do ) : new WP_Error( 'flxai_not_found', __( 'Change not found.', 'flx-ai' ) );
			if ( ! is_wp_error( $r ) && ( $r['conflicts'] || $r['errors'] ) ) {
				/* translators: %s: file paths */
				$r = new WP_Error( 'flxai_change_partial', sprintf( __( 'Some files were not applied: %s', 'flx-ai' ), implode( ', ', array_merge( $r['conflicts'], array_keys( $r['errors'] ) ) ) ) );
			}
			if ( is_wp_error( $r ) ) {
				set_transient( 'flxai_appr_err_' . get_current_user_id(), $r->get_error_message(), 60 );
			}
			wp_safe_redirect( add_query_arg( 'msg', is_wp_error( $r ) ? 'error' : ( 'pr_merge' === $do ? 'merged' : 'rejected' ), $back ) );
			exit;
		}
		$args = null;
		if ( 'approve_edit' === $do ) {
			$args = json_decode( (string) ( $post['args'] ?? '' ), true );
			if ( ! is_array( $args ) ) {
				set_transient( 'flxai_appr_err_' . get_current_user_id(), __( 'The input is not valid JSON; nothing was run.', 'flx-ai' ), 60 );
				wp_safe_redirect( add_query_arg( 'msg', 'error', $back ) );
				exit;
			}
		}
		$r = flxai_approval_decide( (int) ( $post['chat'] ?? 0 ), sanitize_text_field( (string) ( $post['call'] ?? '' ) ), 'reject' !== $do, $args );
		if ( is_wp_error( $r ) ) {
			set_transient( 'flxai_appr_err_' . get_current_user_id(), $r->get_error_message(), 60 );
			$msg = 'error';
		} else {
			$msg = 'reject' === $do ? 'rejected' : ( 'continue' === $r['state'] ? 'running' : 'approved' );
		}
		wp_safe_redirect( add_query_arg( 'msg', $msg, $back ) );
		exit;
	}
);

function flxai_page_approvals() {
	flxai_page_head( __( 'Awaiting approval', 'flx-ai' ), __( 'Actions the assistant prepared in your chats and scheduled tasks. Nothing changes on the site until you approve. You can edit the input before approving.', 'flx-ai' ) );
	// phpcs:disable WordPress.Security.NonceVerification.Recommended
	$err = get_transient( 'flxai_appr_err_' . get_current_user_id() );
	if ( $err && 'error' === ( $_GET['msg'] ?? '' ) ) {
		delete_transient( 'flxai_appr_err_' . get_current_user_id() );
		echo '<div class="notice notice-error"><p>' . esc_html( $err ) . '</p></div>';
	} elseif ( isset( $_GET['msg'] ) ) {
		$m = array(
			'approved' => __( 'Approved and done. The assistant\'s reply is in the chat.', 'flx-ai' ),
			'running'  => __( 'Approved. The assistant is still working; open the chat to follow.', 'flx-ai' ),
			'rejected' => __( 'Rejected; nothing was changed.', 'flx-ai' ),
			'merged'   => __( 'Merged; the files were changed.', 'flx-ai' ),
		)[ sanitize_key( $_GET['msg'] ) ] ?? '';
		if ( $m ) {
			echo '<div class="notice notice-success is-dismissible"><p>' . esc_html( $m ) . '</p></div>';
		}
	}
	// phpcs:enable
	$items = flxai_approvals_list();
	$prs   = function_exists( 'flxai_changes_list' ) ? flxai_changes_list( array( 'user_id' => get_current_user_id(), 'kind' => 'pr', 'status' => 'open' ) ) : array();
	if ( $prs ) {
		echo '<div class="flxai-appr-list">';
		foreach ( $prs as $pr ) {
			$task = $pr['task_id'] ? flxai_task_get( $pr['task_id'] ) : null;
			$view = add_query_arg( array( 'flxai_chat' => $pr['chat_id'], 'flxai_ws' => $pr['id'] ), admin_url( 'admin.php?page=flx-ai-chat' ) );
			echo '<article class="flxai-card flxai-appr">';
			echo '<header class="flxai-appr-head"><div>';
			/* translators: %d: change request number */
			echo '<div class="flxai-appr-title">' . esc_html( sprintf( __( 'Change request #%d', 'flx-ai' ), $pr['id'] ) ) . ' · ' . esc_html( $pr['title'] ) . '</div><div class="flxai-note">';
			echo $task ? '<span class="flxai-pill is-ok">' . esc_html__( 'Scheduled task', 'flx-ai' ) . '</span> ' . esc_html( $task['title'] ) : '<span class="flxai-pill">' . esc_html__( 'Chat', 'flx-ai' ) . '</span>';
			echo ' · ' . esc_html( flx_time_ago( $pr['created'] ) ) . '</div></div>';
			echo '<a class="button button-small" href="' . esc_url( $view ) . '">' . esc_html__( 'Review the diff', 'flx-ai' ) . '</a></header>';
			echo '<ul class="flxai-pr-files">';
			foreach ( $pr['files'] as $f ) {
				$op = array(
					'add'    => __( 'added', 'flx-ai' ),
					'edit'   => __( 'changed', 'flx-ai' ),
					'delete' => __( 'deleted', 'flx-ai' ),
				)[ $f['op'] ?? 'edit' ] ?? '';
				echo '<li><span class="flxai-pill">' . esc_html( $op ) . '</span> <code>' . esc_html( $f['path'] ) . '</code> <span class="flxai-pr-n"><b>+' . (int) ( $f['add'] ?? 0 ) . '</b> <i>−' . (int) ( $f['del'] ?? 0 ) . '</i></span></li>';
			}
			echo '</ul><form method="post" class="flxai-appr-acts">';
			wp_nonce_field( 'flxai_approvals', 'flxai_approvals_nonce' );
			echo '<input type="hidden" name="change" value="' . esc_attr( $pr['id'] ) . '">';
			echo '<button type="submit" name="do" value="pr_merge" class="button button-primary">' . esc_html__( 'Merge', 'flx-ai' ) . '</button> ';
			echo '<button type="submit" name="do" value="pr_reject" class="button">' . esc_html__( 'Reject', 'flx-ai' ) . '</button>';
			echo '</form></article>';
		}
		echo '</div>';
	}
	if ( ! $items && $prs ) {
		echo '</div>';
		return;
	}
	if ( ! $items ) {
		echo '<section class="flxai-card flxai-appr-empty"><p><strong>' . esc_html__( 'Nothing is waiting for approval.', 'flx-ai' ) . '</strong></p>';
		echo '<p class="flxai-note">' . esc_html__( 'When the assistant or a scheduled task wants to change something (create a draft, update a setting, send a message), it appears here.', 'flx-ai' ) . '</p></section></div>';
		return;
	}
	echo '<div class="flxai-appr-list">';
	foreach ( $items as $it ) {
		$task = $it['task'];
		$chat = add_query_arg( 'flxai_chat', $it['chat_id'], admin_url( 'admin.php?page=flx-ai-approvals' ) );
		$json = wp_json_encode( $it['args'] ? $it['args'] : new stdClass(), JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES );
		echo '<article class="flxai-card flxai-appr">';
		echo '<header class="flxai-appr-head"><div>';
		echo '<div class="flxai-appr-title">' . esc_html( $it['title'] ) . ' <code>' . esc_html( $it['tool'] ) . '</code></div>';
		echo '<div class="flxai-note">';
		if ( $task ) {
			echo '<span class="flxai-pill is-ok">' . esc_html__( 'Scheduled task', 'flx-ai' ) . '</span> <a href="' . esc_url( flxai_task_open_url( $task ) ) . '">' . esc_html( $task['title'] ) . '</a>';
		} else {
			echo '<span class="flxai-pill">' . esc_html__( 'Chat', 'flx-ai' ) . '</span> ' . esc_html( '' !== $it['chat_title'] ? $it['chat_title'] : __( 'New chat', 'flx-ai' ) );
		}
		echo ' · ' . esc_html( flx_time_ago( $it['ts'] ) ) . '</div></div>';
		echo '<a class="button button-small" href="' . esc_url( $chat ) . '">' . esc_html__( 'Open in chat', 'flx-ai' ) . '</a></header>';
		if ( '' !== $it['note'] ) {
			echo '<div class="flxai-md flxai-appr-note" data-md="' . esc_attr( mb_substr( $it['note'], 0, 1500 ) ) . '">' . esc_html( mb_substr( $it['note'], 0, 1500 ) ) . '</div>';
		}
		echo '<div class="flxai-tl-k">' . esc_html__( 'Input', 'flx-ai' ) . '</div><pre class="flxai-tl-pre">' . esc_html( mb_substr( (string) $json, 0, 4000 ) ) . '</pre>';
		echo '<form method="post" class="flxai-appr-acts">';
		wp_nonce_field( 'flxai_approvals', 'flxai_approvals_nonce' );
		echo '<input type="hidden" name="chat" value="' . esc_attr( $it['chat_id'] ) . '"><input type="hidden" name="call" value="' . esc_attr( $it['call_id'] ) . '">';
		echo '<button type="submit" name="do" value="approve" class="button button-primary">' . esc_html__( 'Approve', 'flx-ai' ) . '</button> ';
		echo '<button type="submit" name="do" value="reject" class="button">' . esc_html__( 'Reject', 'flx-ai' ) . '</button>';
		echo '<details class="flxai-appr-edit"><summary>' . esc_html__( 'Edit and approve', 'flx-ai' ) . '</summary>';
		echo '<textarea name="args" rows="' . esc_attr( min( 18, max( 5, substr_count( (string) $json, "\n" ) + 2 ) ) ) . '" class="large-text code" spellcheck="false">' . esc_textarea( (string) $json ) . '</textarea>';
		echo '<p class="description">' . esc_html__( 'Change the values (JSON) and approve; the action runs with your version.', 'flx-ai' ) . '</p>';
		echo '<button type="submit" name="do" value="approve_edit" class="button button-primary">' . esc_html__( 'Approve with changes', 'flx-ai' ) . '</button></details>';
		echo '</form></article>';
	}
	echo '</div></div>';
}
