<?php
/**
 * Settings › Flux GraphQL.
 *
 * @package FLX_GraphQL
 */

defined( 'ABSPATH' ) || exit;

add_action(
	'admin_menu',
	static function () {
		add_options_page(
			__( 'Flux GraphQL', 'flx-graphql' ),
			__( 'Flux GraphQL', 'flx-graphql' ),
			'manage_options',
			'flx-graphql',
			'flx_gql_admin_render'
		);
	}
);

add_action(
	'admin_enqueue_scripts',
	static function ( $hook ) {
		if ( 'settings_page_flx-graphql' !== $hook ) {
			return;
		}
		wp_enqueue_style( 'flx-graphql-admin', FLXGQL_URL . 'assets/css/admin.css', array(), FLXGQL_VERSION );
		if ( function_exists( 'flx_field_enqueue' ) ) {
			flx_field_enqueue();
		}
	}
);

add_filter(
	'plugin_action_links_' . plugin_basename( FLXGQL_FILE ),
	static function ( $links ) {
		array_unshift( $links, '<a href="' . esc_url( admin_url( 'options-general.php?page=flx-graphql' ) ) . '">' . esc_html__( 'Settings', 'flx-graphql' ) . '</a>' );
		return $links;
	}
);

add_action(
	'admin_post_flxgql_save',
	static function () {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You do not have permission to do this.', 'flx-graphql' ), 403 );
		}
		check_admin_referer( 'flxgql_save' );
		$in = wp_unslash( $_POST ); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- flx_gql_sanitize_settings validates every key.
		update_option( 'flxgql_settings', flx_gql_sanitize_settings( is_array( $in ) ? $in : array() ), false );
		wp_safe_redirect( add_query_arg( 'flxgql_msg', 'saved', admin_url( 'options-general.php?page=flx-graphql' ) ) );
		exit;
	}
);

/**
 * Renders the settings page.
 */
function flx_gql_admin_render(): void {
	if ( ! current_user_can( 'manage_options' ) ) {
		return;
	}
	$s     = flx_gql_settings();
	$state = flx_gql_state();
	$msg   = isset( $_GET['flxgql_msg'] ) ? sanitize_key( wp_unslash( $_GET['flxgql_msg'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
	$pq    = flx_gql_persisted();
	$has   = function_exists( 'flx_field' ) && function_exists( 'flx_check' );
	?>
	<div class="wrap flxgql">
		<h1><?php esc_html_e( 'Flux GraphQL', 'flx-graphql' ); ?></h1>
		<p class="flxgql-lead"><?php esc_html_e( 'One GraphQL API for the FluxWP plugins. Screens in wp-admin, the FluxWP desktop and mobile apps and a headless front end all read the same typed schema.', 'flx-graphql' ); ?></p>

		<?php if ( 'saved' === $msg ) : ?>
			<div class="notice notice-success is-dismissible"><p><?php esc_html_e( 'Settings saved.', 'flx-graphql' ); ?></p></div>
		<?php elseif ( 'embedded' === $msg ) : ?>
			<div class="notice notice-success is-dismissible"><p><?php esc_html_e( 'The separate WPGraphQL plugin was deactivated; the built-in copy is in use.', 'flx-graphql' ); ?></p></div>
		<?php endif; ?>

		<div class="flxgql-grid">
			<section class="flxgql-card">
				<h2><?php esc_html_e( 'Status', 'flx-graphql' ); ?></h2>
				<dl class="flxgql-facts">
					<dt><?php esc_html_e( 'Endpoint', 'flx-graphql' ); ?></dt>
					<dd><code><?php echo esc_html( flx_gql_endpoint() ); ?></code></dd>
					<dt><?php esc_html_e( 'WPGraphQL', 'flx-graphql' ); ?></dt>
					<dd>
						<?php
						if ( 'embedded' === $state['source'] ) {
							/* translators: %s: version. */
							echo esc_html( sprintf( __( '%s (built in)', 'flx-graphql' ), $state['version'] ) );
						} elseif ( 'external' === $state['source'] ) {
							/* translators: %s: version. */
							echo esc_html( sprintf( __( '%s (separate plugin)', 'flx-graphql' ), '' !== $state['version'] ? $state['version'] : '?' ) );
						} else {
							esc_html_e( 'not loaded', 'flx-graphql' );
						}
						?>
						<span class="flxgql-pill flxgql-pill--<?php echo $state['ok'] ? 'ok' : 'bad'; ?>"><?php echo $state['ok'] ? esc_html__( 'working', 'flx-graphql' ) : esc_html__( 'off', 'flx-graphql' ); ?></span>
					</dd>
					<dt><?php esc_html_e( 'Known queries', 'flx-graphql' ); ?></dt>
					<dd>
						<?php
						/* translators: 1: query count, 2: package count. */
						echo esc_html( sprintf( __( '%1$d from %2$d packages', 'flx-graphql' ), count( $pq['queries'] ), count( $pq['files'] ) ) );
						?>
					</dd>
					<dt><?php esc_html_e( 'Upload endpoint', 'flx-graphql' ); ?></dt>
					<dd><code><?php echo esc_html( rest_url( 'flx/v1/upload' ) ); ?></code></dd>
				</dl>
				<?php if ( 'external' === $state['source'] && current_user_can( 'activate_plugins' ) ) : ?>
					<p><a class="button" href="<?php echo esc_url( wp_nonce_url( admin_url( 'admin-post.php?action=flxgql_use_embedded' ), 'flxgql_use_embedded' ) ); ?>"><?php esc_html_e( 'Use the Flux GraphQL version', 'flx-graphql' ); ?></a></p>
				<?php endif; ?>
				<?php if ( $s['ide'] && 'embedded' === $state['source'] ) : ?>
					<p><a class="button" href="<?php echo esc_url( admin_url( 'admin.php?page=graphiql-ide' ) ); ?>"><?php esc_html_e( 'Open GraphiQL IDE', 'flx-graphql' ); ?></a></p>
				<?php endif; ?>
				<ul class="flxgql-rows">
					<?php foreach ( flx_gql_check_rows() as $row ) : ?>
						<li class="flxgql-row flxgql-row--<?php echo esc_attr( $row['status'] ); ?>">
							<strong><?php echo esc_html( $row['label'] ); ?></strong>
							<span><?php echo esc_html( $row['message'] ); ?></span>
						</li>
					<?php endforeach; ?>
				</ul>
			</section>

			<form class="flxgql-card" method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
				<input type="hidden" name="action" value="flxgql_save">
				<?php wp_nonce_field( 'flxgql_save' ); ?>
				<h2><?php esc_html_e( 'Access', 'flx-graphql' ); ?></h2>
				<?php
				$modes = array(
					'strict' => __( 'Only queries shipped with FluxWP packages (recommended)', 'flx-graphql' ),
					'admins' => __( 'Administrators may also send any query', 'flx-graphql' ),
					'open'   => __( 'Anyone may send any query (public headless API)', 'flx-graphql' ),
				);
				if ( $has ) {
					echo flx_field( // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- flx_field escapes.
						array(
							'name'    => 'query_mode',
							'type'    => 'select',
							'label'   => __( 'Which queries run', 'flx-graphql' ),
							'value'   => $s['query_mode'],
							'options' => $modes,
							'help'    => __( 'Persisted queries keep the API small and let LiteSpeed cache public reads.', 'flx-graphql' ),
							'wide'    => true,
						)
					);
					echo flx_field( // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
						array(
							'name'        => 'cors_origins',
							'type'        => 'textarea',
							'label'       => __( 'Front-end origins', 'flx-graphql' ),
							'value'       => $s['cors_origins'],
							'rows'        => 3,
							'placeholder' => 'https://www.example.com',
							'help'        => __( 'Sites allowed to call /graphql from the browser, one per line. This site is always allowed.', 'flx-graphql' ),
							'wide'        => true,
						)
					);
					echo '<div class="flxgql-checks">';
					echo flx_check( // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
						array(
							'name'    => 'ide',
							'label'   => __( 'GraphiQL IDE for administrators', 'flx-graphql' ),
							'note'    => __( 'Also lets administrators run any query while it is on.', 'flx-graphql' ),
							'checked' => (bool) $s['ide'],
						)
					);
					echo flx_check( // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
						array(
							'name'    => 'introspection',
							'label'   => __( 'Public schema introspection', 'flx-graphql' ),
							'note'    => __( 'Administrators can always read the schema.', 'flx-graphql' ),
							'checked' => (bool) $s['introspection'],
						)
					);
					echo flx_check( // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
						array(
							'name'    => 'debug',
							'label'   => __( 'Debug messages for administrators', 'flx-graphql' ),
							'checked' => (bool) $s['debug'],
						)
					);
					echo '</div>';

					echo '<h2>' . esc_html__( 'Limits', 'flx-graphql' ) . '</h2><div class="flxgql-limits">';
					$limits = array(
						'max_depth'      => array( __( 'Deepest nesting', 'flx-graphql' ), '' ),
						'max_complexity' => array( __( 'Highest complexity', 'flx-graphql' ), '' ),
						'max_page'       => array( __( 'Records per page', 'flx-graphql' ), '' ),
						'batch_limit'    => array( __( 'Queries per batch', 'flx-graphql' ), '' ),
						'cache_ttl'      => array( __( 'Public cache', 'flx-graphql' ), __( 'sec', 'flx-graphql' ) ),
						'upload_max_mb'  => array( __( 'Largest upload', 'flx-graphql' ), 'MB' ),
					);
					foreach ( $limits as $key => list( $label, $suffix ) ) {
						echo flx_field( // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
							array(
								'name'   => $key,
								'type'   => 'number',
								'label'  => $label,
								'value'  => (string) $s[ $key ],
								'suffix' => $suffix,
								'format' => 'digits',
							)
						);
					}
					echo '</div>';
				} else {
					echo '<p>' . esc_html__( 'The FluxWP form fields could not load. Reinstall Flux GraphQL.', 'flx-graphql' ) . '</p>';
				}
				?>
				<p class="flxgql-hint"><?php esc_html_e( 'Shared hosting: keep the limits low. A query that goes over them is refused before it runs.', 'flx-graphql' ); ?></p>
				<p><button type="submit" class="button button-primary"><?php esc_html_e( 'Save', 'flx-graphql' ); ?></button></p>
			</form>
		</div>
	</div>
	<?php
}
