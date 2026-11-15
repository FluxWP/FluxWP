<?php
/**
 * Flux GraphQL readiness: PHP version, Flux GraphQL installed/active/working, and a notice with an
 * install button when a package with GraphQL screens is active without it.
 *
 * Packages call flxk_graphql_notice( 'Flux Tasks' ) on their screen when flx_gql_ready() is false:
 *
 *     if ( ! function_exists( 'flx_gql_ready' ) || ! flx_gql_ready() ) {
 *         if ( function_exists( 'flxk_graphql_notice' ) ) { flxk_graphql_notice( __( 'Flux Tasks', 'flx-tasks' ) ); }
 *     }
 *
 * @package FLX_Setup
 */

defined( 'ABSPATH' ) || exit;

/** Lowest PHP version of the GraphQL packages. */
const FLXK_GRAPHQL_PHP_MIN = '8.1';

/**
 * Plugin file of Flux GraphQL when installed ('' otherwise).
 */
function flxk_graphql_file() {
	if ( ! function_exists( 'get_plugins' ) ) {
		require_once ABSPATH . 'wp-admin/includes/plugin.php';
	}
	foreach ( array_keys( get_plugins() ) as $file ) {
		if ( 'flx-graphql' === dirname( $file ) ) {
			return $file;
		}
	}
	return '';
}

/**
 * Names of the active plugins whose "Requires Plugins" header lists flx-graphql.
 *
 * @return string[]
 */
function flxk_graphql_dependents() {
	if ( ! function_exists( 'get_plugins' ) ) {
		require_once ABSPATH . 'wp-admin/includes/plugin.php';
	}
	$out = array();
	foreach ( get_plugins() as $file => $data ) {
		$req = isset( $data['RequiresPlugins'] ) ? (string) $data['RequiresPlugins'] : '';
		if ( '' === $req || ! is_plugin_active( $file ) ) {
			continue;
		}
		if ( in_array( 'flx-graphql', array_map( 'trim', explode( ',', $req ) ), true ) ) {
			$out[] = (string) $data['Name'];
		}
	}
	return $out;
}

/**
 * State of Flux GraphQL on this site.
 *
 * @return array{state:string,version:string,file:string} state: missing|inactive|conflict|ok
 */
function flxk_graphql_state() {
	if ( function_exists( 'flx_gql_state' ) && defined( 'FLXGQL_VERSION' ) ) {
		$s = flx_gql_state();
		return array(
			'state'   => ! empty( $s['ok'] ) ? 'ok' : 'conflict',
			'version' => (string) FLXGQL_VERSION,
			'file'    => '',
		);
	}
	$file = flxk_graphql_file();
	return array(
		'state'   => '' === $file ? 'missing' : 'inactive',
		'version' => '',
		'file'    => $file,
	);
}

/**
 * Where the admin fixes the state: Packages install, Plugins activate, or Flux GraphQL settings.
 *
 * @param array{state:string,version:string,file:string} $st State.
 * @return array{0:string,1:string} URL and label ('' when there is nothing to do or no permission).
 */
function flxk_graphql_action( array $st ) {
	if ( 'missing' === $st['state'] && current_user_can( 'install_plugins' ) && class_exists( 'FLXU_Admin' ) ) {
		$url = FLXU_Admin::url(
			array(
				'flxu_install' => 'flx-graphql',
				'_wpnonce'     => wp_create_nonce( 'flxu_install_flx-graphql' ),
			)
		);
		return array( $url, __( 'Install Flux GraphQL', 'flx-setup' ) );
	}
	if ( 'inactive' === $st['state'] && current_user_can( 'activate_plugins' ) ) {
		$url = add_query_arg(
			array(
				'action'   => 'activate',
				'plugin'   => rawurlencode( $st['file'] ),
				'_wpnonce' => wp_create_nonce( 'activate-plugin_' . $st['file'] ),
			),
			admin_url( 'plugins.php' )
		);
		return array( $url, __( 'Activate Flux GraphQL', 'flx-setup' ) );
	}
	if ( 'conflict' === $st['state'] && current_user_can( 'manage_options' ) ) {
		return array( admin_url( 'options-general.php?page=flx-graphql' ), __( 'Flux GraphQL settings', 'flx-setup' ) );
	}
	return array( '', '' );
}

/**
 * Notice for a package screen that needs Flux GraphQL. Prints nothing when Flux GraphQL works.
 *
 * @param string $package Package name shown to the user.
 */
function flxk_graphql_notice( $package ) {
	$st = flxk_graphql_state();
	if ( 'ok' === $st['state'] ) {
		return;
	}
	list( $url, $label ) = flxk_graphql_action( $st );
	if ( version_compare( PHP_VERSION, FLXK_GRAPHQL_PHP_MIN, '<' ) ) {
		/* translators: 1: package name, 2: required PHP version, 3: current PHP version. */
		$text = sprintf( __( '%1$s needs PHP %2$s or newer; this site runs PHP %3$s. Raise the PHP version in the hosting panel.', 'flx-setup' ), $package, FLXK_GRAPHQL_PHP_MIN, PHP_VERSION );
		$url  = '';
	} elseif ( 'conflict' === $st['state'] ) {
		/* translators: %s: package name. */
		$text = sprintf( __( '%s cannot load its data because the Flux GraphQL server is not running. Open the Flux GraphQL settings to fix it.', 'flx-setup' ), $package );
	} else {
		/* translators: %s: package name. */
		$text = sprintf( __( '%s needs Flux GraphQL to load its data. Install and activate it; it takes a few seconds.', 'flx-setup' ), $package );
	}
	echo '<div class="notice notice-error flxk-graphql-notice"><p>' . esc_html( $text );
	if ( '' !== $url ) {
		echo ' <a class="button button-primary" href="' . esc_url( $url ) . '">' . esc_html( $label ) . '</a>';
	}
	echo '</p></div>';
}

/**
 * System check rows.
 *
 * @param array $checks Rows.
 * @return array
 */
function flxk_graphql_checks( $checks ) {
	$checks     = is_array( $checks ) ? $checks : array();
	$st         = flxk_graphql_state();
	$dependents = flxk_graphql_dependents();
	$source     = 'FluxWP Panel';

	$php_ok   = version_compare( PHP_VERSION, FLXK_GRAPHQL_PHP_MIN, '>=' );
	$checks[] = array(
		'id'      => 'flxk.php',
		'source'  => $source,
		'group'   => 'Config',
		'label'   => __( 'PHP version', 'flx-setup' ),
		'status'  => $php_ok ? 'ok' : 'warning',
		'message' => $php_ok
			/* translators: %s: PHP version. */
			? sprintf( __( 'PHP %s. FluxWP GraphQL packages run on it.', 'flx-setup' ), PHP_VERSION )
			/* translators: 1: current PHP version, 2: required PHP version. */
			: sprintf( __( 'PHP %1$s. New FluxWP packages (Flux GraphQL and the packages that use it) need PHP %2$s or newer: raise it in the hosting panel.', 'flx-setup' ), PHP_VERSION, FLXK_GRAPHQL_PHP_MIN ),
	);

	if ( 'missing' === $st['state'] && ! $dependents ) {
		return $checks; // Nothing uses GraphQL yet.
	}
	list( $url, $label ) = flxk_graphql_action( $st );
	$names               = implode( ', ', array_slice( $dependents, 0, 5 ) );
	if ( 'ok' === $st['state'] ) {
		$status = 'ok';
		/* translators: %s: Flux GraphQL version. */
		$message = sprintf( __( 'Flux GraphQL %s is installed and working; no separate WPGraphQL conflict.', 'flx-setup' ), $st['version'] );
	} elseif ( 'conflict' === $st['state'] ) {
		$status  = 'error';
		$message = __( 'Flux GraphQL is active, but its GraphQL server is not running (usually an unsupported, separately installed WPGraphQL), so GraphQL screens are off.', 'flx-setup' );
	} elseif ( $dependents ) {
		$status = 'error';
		/* translators: %s: package names. */
		$message = sprintf( __( 'These packages need Flux GraphQL, which is not active: %s. Their screens cannot load data.', 'flx-setup' ), $names );
	} else {
		$status  = 'info';
		$message = __( 'Flux GraphQL is installed but not active. No active package needs it.', 'flx-setup' );
	}
	$row = array(
		'id'      => 'flxk.graphql',
		'source'  => $source,
		'group'   => 'Plugins',
		'label'   => __( 'Flux GraphQL', 'flx-setup' ),
		'status'  => $status,
		'message' => $message,
	);
	if ( '' !== $url ) {
		$row['action_url']   = $url;
		$row['action_label'] = $label;
	}
	$checks[] = $row;
	return $checks;
}
add_filter( 'flx_system_checks', 'flxk_graphql_checks' );

/**
 * Admin notice when an active package needs Flux GraphQL and it does not work.
 */
add_action(
	'admin_notices',
	static function () {
		if ( ! current_user_can( 'activate_plugins' ) ) {
			return;
		}
		$st = flxk_graphql_state();
		if ( 'ok' === $st['state'] || 'conflict' === $st['state'] ) {
			return; // Flux GraphQL shows its own conflict notice.
		}
		$dependents = flxk_graphql_dependents();
		if ( $dependents ) {
			flxk_graphql_notice( implode( ', ', array_slice( $dependents, 0, 3 ) ) );
		}
	}
);
