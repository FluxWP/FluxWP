<?php
/**
 * Flux AI & MCP › MCP: sunucuyu aç/kapa, anahtarlar, asistana bağlama ve son işlemler.
 *
 * @package FLX_AI
 */

defined( 'ABSPATH' ) || exit;

function flxai_page_mcp() {
	$uid = get_current_user_id();
	$new = get_transient( 'flxai_mcp_new_key_' . $uid );
	delete_transient( 'flxai_mcp_new_key_' . $uid );
	$url   = flxai_mcp_url();
	$keys  = flxai_mcp_keys();
	$log   = array_slice( (array) get_option( 'flxai_mcp_log', array() ), 0, 20 );
	$tools = flxai_mcp_tools();
	$on    = flxai_mcp_enabled();
	$form  = function ( $do, $inner, $button, $class = 'button' ) {
		echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '" class="flxai-inline-form">';
		wp_nonce_field( 'flxai_mcp' );
		echo '<input type="hidden" name="action" value="flxai_mcp"><input type="hidden" name="do" value="' . esc_attr( $do ) . '">' . $inner; // phpcs:ignore WordPress.Security.EscapeOutput -- callers escape
		echo ' <button class="' . esc_attr( $class ) . '">' . esc_html( $button ) . '</button></form>';
	};
	$slug     = sanitize_title( get_bloginfo( 'name' ) );
	$slug     = '' !== $slug ? $slug : 'fluxwp';
	$key_show = $new ? $new : __( 'YOUR_KEY', 'flx-ai' );
	$json     = wp_json_encode(
		array(
			'mcpServers' => array(
				$slug => array(
					'command' => 'npx',
					'args'    => array( '-y', 'mcp-remote', $url, '--header', 'Authorization:Bearer ' . $key_show ),
				),
			),
		),
		JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE
	);

	flxai_page_head( 'MCP', __( 'AI assistants such as Claude connect to this site over MCP (Model Context Protocol). Tools come from the installed FluxWP plugins (SEO, CRM, Mail, Speed…); Flux AI chat uses the same tools.', 'flx-ai' ) );

	if ( flxai_mcp_legacy() ) {
		echo '<div class="notice notice-warning inline"><p>' . esc_html__( 'An old Flux SEO version is running its own MCP server. Update Flux SEO; keys and the setting move here automatically.', 'flx-ai' ) . '</p></div>';
	}
	?>
	<div class="flxai-mcp">
		<section class="flxai-card">
			<h2><?php esc_html_e( '1. Server', 'flx-ai' ); ?></h2>
			<?php
			$oauth_set = get_option( 'flxai_settings', array() );
			$oauth_on  = ! is_array( $oauth_set ) || ! array_key_exists( 'mcp_oauth', $oauth_set ) || ! empty( $oauth_set['mcp_oauth'] );
			$form(
				'toggle',
				'<label class="flxai-check"><input type="checkbox" name="mcp_enabled" value="1"' . checked( $on, true, false ) . '> ' . esc_html__( 'MCP server enabled', 'flx-ai' ) . '</label>'
				. '<label class="flxai-check"><input type="checkbox" name="mcp_oauth" value="1"' . checked( $oauth_on, true, false ) . '> <span class="flxai-check-text">' . esc_html__( 'Sign in with OAuth: assistants connect with only the URL; the user signs in to WordPress and approves each connection', 'flx-ai' ) . '</span></label>',
				__( 'Save', 'flx-ai' ),
				'button button-primary'
			);
			?>
			<p><?php esc_html_e( 'Server URL', 'flx-ai' ); ?>: <code><?php echo esc_html( $url ); ?></code>
				<span class="flxai-pill<?php echo $on ? ' is-ok' : ''; ?>"><?php echo $on ? esc_html__( 'on', 'flx-ai' ) : esc_html__( 'off', 'flx-ai' ); ?></span>
				<?php if ( $on && flxai_oauth_enabled() ) : ?>
					<span class="flxai-pill is-ok">OAuth</span>
				<?php endif; ?></p>
			<?php if ( flxai_mcp_rest_url() !== $url ) : ?>
				<p class="flxai-note"><?php esc_html_e( 'The same server also answers at:', 'flx-ai' ); ?> <code><?php echo esc_html( flxai_mcp_rest_url() ); ?></code></p>
			<?php endif; ?>
			<p class="flxai-note">
				<?php
				/* translators: %d: tool count */
				echo esc_html( sprintf( _n( '%d tool is registered.', '%d tools are registered.', count( $tools ), 'flx-ai' ), count( $tools ) ) );
				?>
				<a href="<?php echo esc_url( admin_url( 'admin.php?page=flx-ai-tools' ) ); ?>"><?php esc_html_e( 'Tools', 'flx-ai' ); ?></a>
			</p>
		</section>

		<section class="flxai-card">
			<h2><?php esc_html_e( '2. Connections and keys', 'flx-ai' ); ?></h2>
			<p class="flxai-note"><?php esc_html_e( 'The assistant works with the permissions of the user who created the key. New content is created as a draft; deleting only moves to trash.', 'flx-ai' ); ?></p>
			<?php if ( $new ) : ?>
				<div class="notice notice-success inline"><p><strong><?php esc_html_e( 'New key (shown only now, copy it):', 'flx-ai' ); ?></strong></p><p><code class="flxai-key"><?php echo esc_html( $new ); ?></code></p></div>
			<?php endif; ?>
			<?php
			$checks = '';
			foreach ( $tools as $name => $t ) {
				$checks .= '<label class="flxai-check"><input type="checkbox" name="tools[]" value="' . esc_attr( $name ) . '"> <code>' . esc_html( $name ) . '</code> '
					. esc_html( $t['title'] ) . ( empty( $t['readonly'] ) ? '' : ' <span class="flxai-pill">' . esc_html__( 'reads', 'flx-ai' ) . '</span>' ) . '</label>';
			}
			$scope = '<details class="flxai-scope"><summary>' . esc_html__( 'Limit tools (optional)', 'flx-ai' ) . '</summary>'
				. '<p class="flxai-note">' . esc_html__( 'If none is selected the key works with all of the user\'s permissions. If some are selected it only sees and runs those tools (e.g. only order_summary for a TV order board).', 'flx-ai' ) . '</p>'
				. '<div class="flxai-scope-list">' . $checks . '</div></details>';
			$form( 'create', '<input name="name" placeholder="' . esc_attr__( 'e.g. Claude – Alex', 'flx-ai' ) . '" class="regular-text">' . $scope, __( 'Create new key', 'flx-ai' ), 'button button-secondary' );
			?>
			<?php if ( $keys ) : ?>
				<div class="flxai-tscroll"><table class="flxai-table">
					<thead><tr><th><?php esc_html_e( 'Name', 'flx-ai' ); ?></th><th><?php esc_html_e( 'User', 'flx-ai' ); ?></th><th><?php esc_html_e( 'Tools', 'flx-ai' ); ?></th><th><?php esc_html_e( 'Created', 'flx-ai' ); ?></th><th><?php esc_html_e( 'Last used', 'flx-ai' ); ?></th><th></th></tr></thead>
					<tbody>
					<?php foreach ( $keys as $id => $k ) : ?>
						<?php $u = get_userdata( (int) ( $k['user'] ?? 0 ) ); ?>
						<tr>
							<td><?php echo esc_html( $k['name'] ?? '' ); ?>
								<?php if ( 'oauth' === ( $k['type'] ?? '' ) ) : ?>
									<span class="flxai-pill"><?php esc_html_e( 'OAuth', 'flx-ai' ); ?></span>
								<?php else : ?>
									<code>…<?php echo esc_html( $k['hint'] ?? '' ); ?></code>
								<?php endif; ?></td>
							<td><?php echo esc_html( $u ? $u->user_login : '?' ); ?></td>
							<td><?php echo empty( $k['tools'] ) ? esc_html__( 'All', 'flx-ai' ) : '<code>' . esc_html( implode( ', ', (array) $k['tools'] ) ) . '</code>'; ?></td>
							<td><?php echo esc_html( flx_date( (int) ( $k['created'] ?? 0 ), 'datetime' ) ); ?></td>
							<td><?php echo ! empty( $k['last_used'] ) ? esc_html( flx_time_ago( (int) $k['last_used'] ) ) : '—'; ?></td>
							<td><?php $form( 'revoke', '<input type="hidden" name="id" value="' . esc_attr( $id ) . '">', __( 'Revoke', 'flx-ai' ), 'button-link button-link-delete' ); ?></td>
						</tr>
					<?php endforeach; ?>
					</tbody>
				</table></div>
			<?php endif; ?>
		</section>
	</div>

	<section class="flxai-card">
		<h2><?php esc_html_e( '3. Connect an assistant', 'flx-ai' ); ?></h2>
		<?php if ( flxai_oauth_enabled() ) : ?>
			<p><strong>Claude.ai, Claude Desktop, ChatGPT, Cursor, VS Code</strong> — <?php esc_html_e( 'add a custom connector / MCP server with only this URL. You will sign in to WordPress and approve; no key needed:', 'flx-ai' ); ?></p>
			<pre class="flxai-code"><?php echo esc_html( $url ); ?></pre>
			<p class="flxai-note"><?php esc_html_e( 'With a key instead (clients without OAuth, devices, scripts):', 'flx-ai' ); ?></p>
		<?php endif; ?>
		<p><strong>Claude Desktop / Cursor</strong> (claude_desktop_config.json › mcpServers):</p>
		<pre class="flxai-code"><?php echo esc_html( $json ); ?></pre>
		<p><strong>Claude Code</strong>:</p>
		<pre class="flxai-code">claude mcp add --transport http <?php echo esc_html( $slug . ' ' . $url ); ?> --header "Authorization: Bearer <?php echo esc_html( $key_show ); ?>"</pre>
		<p><strong>Claude.ai › <?php esc_html_e( 'Connectors › Add custom connector', 'flx-ai' ); ?></strong> — <?php esc_html_e( 'URL with the key, for places where headers can\'t be added; don\'t share it:', 'flx-ai' ); ?></p>
		<pre class="flxai-code"><?php echo esc_html( add_query_arg( 'key', $key_show, $url ) ); ?></pre>
		<p class="flxai-note"><?php esc_html_e( 'On Apache, if you see "Invalid key", add SetEnvIf Authorization "(.*)" HTTP_AUTHORIZATION=$1 to .htaccess, or use the keyed URL.', 'flx-ai' ); ?></p>
	</section>

	<?php if ( $log ) : ?>
	<section class="flxai-card">
		<h2><?php esc_html_e( 'Recent activity', 'flx-ai' ); ?></h2>
		<div class="flxai-tscroll"><table class="flxai-table">
			<thead><tr><th><?php esc_html_e( 'Time', 'flx-ai' ); ?></th><th><?php esc_html_e( 'User', 'flx-ai' ); ?></th><th><?php esc_html_e( 'Tool', 'flx-ai' ); ?></th><th><?php esc_html_e( 'Target', 'flx-ai' ); ?></th><th><?php esc_html_e( 'Result', 'flx-ai' ); ?></th></tr></thead>
			<tbody>
			<?php foreach ( $log as $l ) : ?>
				<?php $u = get_userdata( (int) ( $l['u'] ?? 0 ) ); ?>
				<tr>
					<td><?php echo esc_html( flx_date( (int) $l['t'], 'datetime' ) ); ?></td>
					<td><?php echo esc_html( $u ? $u->user_login : '?' ); ?></td>
					<td><code><?php echo esc_html( $l['tool'] ); ?></code></td>
					<td><?php echo esc_html( $l['on'] ); ?></td>
					<td><?php echo $l['ok'] ? '<span class="flxai-pill is-ok">✓</span>' : '<span class="flxai-pill is-err">✗</span>'; ?></td>
				</tr>
			<?php endforeach; ?>
			</tbody>
		</table></div>
	</section>
	<?php endif; ?>
	</div>
	<?php
}
