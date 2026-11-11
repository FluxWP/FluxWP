<?php
/**
 * CRM › Ayarlar: kaynaklar, kaynaktan gelen rol, roller, skorlama, eşitleme.
 *
 * @package FLX_CRM
 */

defined( 'ABSPATH' ) || exit;

final class FLXCRM_Page_Settings {

	public static function render() {
		if ( ! flxcrm_can( true ) ) {
			wp_die( esc_html__( 'You are not allowed to do this.', 'flx-crm' ), 403 );
		}
		$s     = flxcrm_settings();
		$roles = flxcrm_roles();
		FLXCRM_Admin::open( __( 'CRM settings', 'flx-crm' ), __( 'Where contacts come from, which role they get and how scoring works.', 'flx-crm' ) );

		/* Eşitleme */
		$synced = (int) get_option( 'flxcrm_synced_at' );
		echo '<section class="flxcrm-card flxcrm-sync"><div class="flxcrm-card__head"><h2>' . flxcrm_icon( 'refresh', 16 ) . esc_html__( 'Sync', 'flx-crm' ) . '</h2><span class="flxcrm-muted">' . esc_html( $synced ? sprintf( /* translators: %s: date */ __( 'Last full sync: %s', 'flx-crm' ), wp_date( 'd.m.Y H:i', $synced ) ) : __( 'Not synced yet', 'flx-crm' ) ) . '</span></div>'; // phpcs:ignore WordPress.Security.EscapeOutput
		echo '<p>' . esc_html__( 'New donations, orders, forms and chats are linked to contacts straight away. Sync everything to import past records, or to relink after switching a source on or off. Existing contacts and notes are kept, and nothing is added twice.', 'flx-crm' ) . '</p>';
		echo '<p><button type="button" class="button button-primary" data-flxcrm-sync>' . esc_html__( 'Sync everything', 'flx-crm' ) . '</button> <button type="button" class="button" data-flxcrm-score>' . esc_html__( 'Recalculate scores', 'flx-crm' ) . '</button></p>';
		echo '<p class="flxcrm-progress" data-flxcrm-progress hidden role="status"></p></section>';

		echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '">';
		wp_nonce_field( 'flxcrm_settings_save' );
		echo '<input type="hidden" name="action" value="flxcrm_settings_save">';

		/* Kaynaklar */
		$src = array();
		foreach ( flxcrm_registered_sources() as $k => $d ) {
			$src[ $k ] = array( $d['label'], $d['installed'], $d['description'], null !== $d['role'] );
		}
		echo '<section class="flxcrm-card"><div class="flxcrm-card__head"><h2>' . flxcrm_icon( 'link', 16 ) . esc_html__( 'Sources', 'flx-crm' ) . '</h2></div>'; // phpcs:ignore WordPress.Security.EscapeOutput
		echo '<table class="flxcrm-table flxcrm-table--settings"><thead><tr><th>' . esc_html__( 'Source', 'flx-crm' ) . '</th><th>' . esc_html__( 'On', 'flx-crm' ) . '</th><th>' . esc_html__( 'Role for new contacts', 'flx-crm' ) . '</th></tr></thead><tbody>';
		foreach ( $src as $k => $d ) {
			echo '<tr><td data-label="' . esc_attr__( 'Source', 'flx-crm' ) . '"><strong>' . esc_html( $d[0] ) . '</strong>' . ( $d[1] ? '' : ' <span class="flxcrm-badge">' . esc_html__( 'not installed', 'flx-crm' ) . '</span>' ) . '<br><small class="flxcrm-muted">' . esc_html( $d[2] ) . '</small></td>';
			echo '<td data-label="' . esc_attr__( 'On', 'flx-crm' ) . '"><label class="flxcrm-switch"><input type="checkbox" name="src_' . esc_attr( $k ) . '" value="1"' . checked( flxcrm_source_on( $k ), true, false ) . '><span class="screen-reader-text">' . esc_html( $d[0] ) . '</span></label></td>';
			if ( ! $d[3] ) {
				echo '<td data-label="' . esc_attr__( 'Role', 'flx-crm' ) . '"><span class="flxcrm-muted">—</span></td></tr>';
				continue;
			}
			echo '<td data-label="' . esc_attr__( 'Role', 'flx-crm' ) . '"><select name="role_' . esc_attr( $k ) . '"><option value="">' . esc_html__( '— no role —', 'flx-crm' ) . '</option>';
			foreach ( $roles as $rk => $r ) {
				echo '<option value="' . esc_attr( $rk ) . '"' . selected( flxcrm_source_role( $k ), $rk, false ) . '>' . esc_html( $r['label'] ) . '</option>';
			}
			echo '</select></td></tr>';
		}
		echo '</tbody></table>';
		echo '<label class="flxcrm-check"><input type="checkbox" name="chat_events" value="1"' . checked( (int) $s['chat_events'], 1, false ) . '> ' . esc_html__( 'Write WhatsApp messages to the timeline (one line per message)', 'flx-crm' ) . '</label>';
		echo '<p class="flxcrm-muted">' . esc_html__( 'The role is given once a payment completes (donor, customer). The "Lead" role drops off by itself when the contact gets another role.', 'flx-crm' ) . '</p></section>';

		/* Roller */
		echo '<section class="flxcrm-card"><div class="flxcrm-card__head"><h2>' . flxcrm_icon( 'badge', 16 ) . esc_html__( 'Roles', 'flx-crm' ) . '</h2></div>'; // phpcs:ignore WordPress.Security.EscapeOutput
		echo '<p class="flxcrm-muted">' . esc_html__( 'The contact\'s relationship with you. A contact can have several roles, e.g. donor and volunteer.', 'flx-crm' ) . '</p><div class="flxcrm-roles-edit">';
		$i = 0;
		foreach ( $roles as $k => $r ) {
			echo '<div class="flxcrm-roles-edit__row"><input type="hidden" name="roles[' . esc_attr( $k ) . '][key]" value="' . esc_attr( $k ) . '"><input type="color" name="roles[' . esc_attr( $k ) . '][color]" value="' . esc_attr( $r['color'] ) . '" aria-label="' . esc_attr__( 'Colour', 'flx-crm' ) . '"><input type="text" name="roles[' . esc_attr( $k ) . '][label]" value="' . esc_attr( $r['label'] ) . '" aria-label="' . esc_attr__( 'Name', 'flx-crm' ) . '"><code>' . esc_html( $k ) . '</code>';
			if ( empty( $r['builtin'] ) ) {
				echo '<label class="flxcrm-check"><input type="checkbox" name="roles[' . esc_attr( $k ) . '][delete]" value="1"> ' . esc_html__( 'Delete', 'flx-crm' ) . '</label>';
			}
			echo '</div>';
			++$i;
		}
		echo '<div class="flxcrm-roles-edit__row is-new"><input type="color" name="new_role_color" value="#64748b" aria-label="' . esc_attr__( 'Colour', 'flx-crm' ) . '"><input type="text" name="new_role_label" placeholder="' . esc_attr__( 'New role: Sponsor, Philanthropist, Dealer…', 'flx-crm' ) . '" aria-label="' . esc_attr__( 'New role', 'flx-crm' ) . '"></div>';
		echo '</div></section>';

		/* Skorlama */
		echo '<section class="flxcrm-card"><div class="flxcrm-card__head"><h2>' . flxcrm_icon( 'chart', 16 ) . esc_html__( 'Scoring', 'flx-crm' ) . '</h2></div><div class="flxcrm-form flxcrm-form--grid">'; // phpcs:ignore WordPress.Security.EscapeOutput
		echo '<label><span>' . esc_html__( 'Churn risk threshold (%)', 'flx-crm' ) . '</span><input type="number" name="risk_threshold" min="10" max="100" value="' . (int) $s['risk_threshold'] . '"><small>' . esc_html__( 'Contacts above this value go to the "Reach out" list. Risk follows each person\'s own rhythm: a monthly donor is at risk after 45 days, a yearly one is not.', 'flx-crm' ) . '</small></label>';
		echo '<label><span>' . esc_html__( 'Active window (days)', 'flx-crm' ) . '</span><input type="number" name="active_days" min="7" max="730" value="' . (int) $s['active_days'] . '"><small>' . esc_html__( 'A contact with any activity within this many days counts as active.', 'flx-crm' ) . '</small></label>';
		echo '<label><span>' . esc_html__( 'Reporting currency', 'flx-crm' ) . '</span><input type="text" name="base_currency" maxlength="3" value="' . esc_attr( (string) $s['base_currency'] ) . '" placeholder="' . esc_attr( flxcrm_currency() ) . '"><small>' . esc_html__( 'Empty means the store currency. Payments in other currencies don\'t count towards value unless a rate is given.', 'flx-crm' ) . '</small></label>';
		echo '</div></section>';

		self::ai_section( $s );

		echo '<p class="flxcrm-actions"><button class="button button-primary">' . esc_html__( 'Save settings', 'flx-crm' ) . '</button></p></form>';
		self::ai_log();
		FLXCRM_Admin::close();
	}

	/** Yapay zekâ ve MCP: izinler, bağlantı durumu, araç listesi. */
	private static function ai_section( array $s ) {
		echo '<section class="flxcrm-card flxcrm-ai" id="ai"><div class="flxcrm-card__head"><h2>' . flxcrm_icon( 'sparkles', 16 ) . esc_html__( 'AI & MCP', 'flx-crm' ) . '</h2></div>'; // phpcs:ignore WordPress.Security.EscapeOutput
		echo '<p class="flxcrm-muted">' . esc_html__( 'Everything in the CRM (contacts, fields, roles, tags, consents, timeline, segments, settings) can be read and edited from the Flux AI chat and over MCP (Claude, ChatGPT, Cursor…). Tools run with the user\'s own CRM permissions.', 'flx-crm' ) . '</p>';

		/* Bağlantı durumu */
		$ai_on  = function_exists( 'flxai_chat' );
		$mcp_on = function_exists( 'flxai_mcp_url' );
		$mcp_ok = $mcp_on && flxai_mcp_enabled();
		echo '<ul class="flxcrm-ai__status">';
		echo '<li><strong>Flux AI & MCP</strong> ' . ( $ai_on ? '<span class="flxcrm-badge is-ok">' . esc_html__( 'connected', 'flx-crm' ) . '</span>' : '<span class="flxcrm-badge">' . esc_html__( 'not installed', 'flx-crm' ) . '</span>' ) . '<small class="flxcrm-muted">' . esc_html__( 'CRM tools in the admin chat. Every tool that changes something waits on an approval card.', 'flx-crm' ) . '</small></li>';
		echo '<li><strong>MCP</strong> ';
		if ( $mcp_ok ) {
			echo '<span class="flxcrm-badge is-ok">' . esc_html__( 'on', 'flx-crm' ) . '</span><code class="flxcrm-ai__url">' . esc_html( flxai_mcp_url() ) . '</code>';
		} elseif ( $mcp_on ) {
			echo '<span class="flxcrm-badge is-warn">' . esc_html__( 'off', 'flx-crm' ) . '</span>';
		} else {
			echo '<span class="flxcrm-badge">' . esc_html__( 'Flux AI & MCP is not installed', 'flx-crm' ) . '</span>';
		}
		echo '<small class="flxcrm-muted">' . esc_html__( 'Delete, merge, bulk actions and sending return only a preview on the first call; they run when called again with the approval token.', 'flx-crm' );
		if ( $mcp_on && current_user_can( 'manage_options' ) ) {
			echo ' <a href="' . esc_url( flxai_mcp_admin_url() ) . '">' . esc_html__( 'MCP keys', 'flx-crm' ) . '</a>';
		}
		echo '</small></li></ul>';

		/* İzinler */
		echo '<div class="flxcrm-switches flxcrm-ai__perms">';
		$perm = array(
			'ai_tools'       => __( 'AI and MCP tools on', 'flx-crm' ),
			'ai_write'       => __( 'Editing: contacts, tags, segments, notes, settings', 'flx-crm' ),
			'ai_destructive' => __( 'Approval actions: delete, merge, bulk actions, sending messages', 'flx-crm' ),
		);
		foreach ( $perm as $k => $label ) {
			echo '<label class="flxcrm-check"><input type="checkbox" name="' . esc_attr( $k ) . '" value="1"' . checked( (int) $s[ $k ], 1, false ) . '> ' . esc_html( $label ) . '</label>';
		}
		echo '</div>';

		/* Araçlar */
		$kinds = flxcrm_kind_labels();
		$tones = array( 'read' => 'is-info', 'write' => 'is-ok', 'destructive' => 'is-warn' );
		$on    = flxcrm_tools_enabled();
		echo '<details class="flxcrm-ai__tools"><summary>' . esc_html( sprintf( /* translators: 1: enabled tool count, 2: all tool count */ __( 'Tools: %1$d / %2$d on', 'flx-crm' ), count( $on ), count( flxcrm_tools() ) ) ) . '</summary>';
		echo '<table class="flxcrm-table flxcrm-table--settings"><thead><tr><th>' . esc_html__( 'Tool', 'flx-crm' ) . '</th><th>' . esc_html__( 'Type', 'flx-crm' ) . '</th></tr></thead><tbody>';
		foreach ( flxcrm_tools() as $name => $t ) {
			echo '<tr' . ( isset( $on[ $name ] ) ? '' : ' class="is-off"' ) . '><td data-label="' . esc_attr__( 'Tool', 'flx-crm' ) . '"><strong>' . esc_html( $t['title'] ) . '</strong><br><code>' . esc_html( $name ) . '</code></td><td data-label="' . esc_attr__( 'Type', 'flx-crm' ) . '"><span class="flxcrm-badge ' . esc_attr( $tones[ $t['kind'] ] ) . '">' . esc_html( $kinds[ $t['kind'] ] ) . '</span></td></tr>';
		}
		echo '</tbody></table></details></section>';
	}

	/** Son yapay zekâ / MCP değişiklikleri. */
	private static function ai_log() {
		$rows = flxcrm_tool_log_rows( 15 );
		echo '<section class="flxcrm-card"><div class="flxcrm-card__head"><h2>' . flxcrm_icon( 'clock', 16 ) . esc_html__( 'Recent changes made with AI', 'flx-crm' ) . '</h2></div>'; // phpcs:ignore WordPress.Security.EscapeOutput
		if ( ! $rows ) {
			echo '<p class="flxcrm-muted">' . esc_html__( 'No changes yet. Every change made with Flux AI or MCP shows up here.', 'flx-crm' ) . '</p></section>';
			return;
		}
		$tools = flxcrm_tools();
		$chs   = flxcrm_channel_labels();
		echo '<table class="flxcrm-table flxcrm-table--settings"><thead><tr><th>' . esc_html__( 'When', 'flx-crm' ) . '</th><th>' . esc_html__( 'Who', 'flx-crm' ) . '</th><th>' . esc_html__( 'Action', 'flx-crm' ) . '</th><th>' . esc_html__( 'Result', 'flx-crm' ) . '</th></tr></thead><tbody>';
		foreach ( $rows as $r ) {
			$u    = get_userdata( (int) $r['u'] );
			$name = $tools[ $r['k'] ]['title'] ?? $r['k'];
			$on   = (string) $r['on'];
			if ( ctype_digit( $on ) && false === strpos( $r['k'], 'tag' ) && false === strpos( $r['k'], 'segment' ) && false === strpos( $r['k'], 'event' ) ) {
				$c  = FLXCRM_Contacts::get( (int) $on );
				$on = $c ? flxcrm_display_name( $c ) : '#' . $on;
			}
			echo '<tr><td data-label="' . esc_attr__( 'When', 'flx-crm' ) . '">' . esc_html( flxcrm_ago( gmdate( 'Y-m-d H:i:s', (int) $r['t'] ) ) ) . '</td>';
			echo '<td data-label="' . esc_attr__( 'Who', 'flx-crm' ) . '">' . esc_html( $u ? $u->display_name : '—' ) . ' <span class="flxcrm-badge">' . esc_html( $chs[ $r['ch'] ] ?? $r['ch'] ) . '</span></td>';
			echo '<td data-label="' . esc_attr__( 'Action', 'flx-crm' ) . '">' . esc_html( $name ) . ( '' !== $on ? ' <small class="flxcrm-muted">' . esc_html( $on ) . '</small>' : '' ) . '</td>';
			echo '<td data-label="' . esc_attr__( 'Result', 'flx-crm' ) . '">' . ( $r['ok'] ? '<span class="flxcrm-badge is-ok">' . esc_html__( 'done', 'flx-crm' ) . '</span>' : '<span class="flxcrm-badge is-bad" title="' . esc_attr( $r['err'] ) . '">' . esc_html__( 'error', 'flx-crm' ) . '</span>' ) . '</td></tr>';
		}
		echo '</tbody></table></section>';
	}
}
