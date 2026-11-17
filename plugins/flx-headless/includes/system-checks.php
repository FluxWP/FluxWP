<?php
/**
 * Shared system check (`flx_system_checks`): front ends and their webhooks. No outbound HTTP.
 *
 * @package FLX_Headless
 */

defined( 'ABSPATH' ) || exit;

add_filter(
	'flx_system_checks_versions',
	static function ( $v ) {
		$v                 = is_array( $v ) ? $v : array();
		$v['flx-headless'] = FLXHL_VERSION;
		return $v;
	}
);

add_filter(
	'flx_system_checks',
	static function ( $checks ) {
		$checks = is_array( $checks ) ? $checks : array();
		$fes    = array_filter( FLXHL_Frontend_Service::frontends(), static fn( FLXHL_Frontend $f ): bool => $f->enabled );
		if ( ! $fes ) {
			$status  = 'warning';
			$message = __( 'No front end is added yet; GraphQL works, but links, canonical addresses and the webhook have no front end to point at.', 'flx-headless' );
		} else {
			$no_hook = array();
			foreach ( $fes as $f ) {
				if ( '' === $f->revalidate_url || ! $f->secret_set ) {
					$no_hook[] = $f->name;
				}
			}
			$failed = array();
			foreach ( array_slice( FLXHL_Revalidate_Service::log(), 0, 10 ) as $d ) {
				if ( ! $d->ok && ! in_array( $d->frontend_name, $failed, true ) ) {
					$failed[] = $d->frontend_name;
				}
			}
			if ( $failed ) {
				$status = 'warning';
				/* translators: %s: front end names */
				$message = sprintf( __( 'The last webhook call failed for: %s. Open the delivery log on the Headless page.', 'flx-headless' ), implode( ', ', $failed ) );
			} elseif ( $no_hook ) {
				$status = 'warning';
				/* translators: %s: front end names */
				$message = sprintf( __( 'No revalidate webhook for: %s. These front ends show changes only when their cache runs out.', 'flx-headless' ), implode( ', ', $no_hook ) );
			} else {
				$status = 'ok';
				/* translators: %d: number of front ends */
				$message = sprintf( _n( '%d front end, webhook set.', '%d front ends, webhooks set.', count( $fes ), 'flx-headless' ), count( $fes ) );
			}
		}
		$checks[] = array(
			'id'           => 'flxhl.frontends',
			'source'       => 'Flux Headless',
			'group'        => 'Integrations',
			'label'        => __( 'Headless front ends', 'flx-headless' ),
			'status'       => $status,
			'message'      => $message,
			'action_url'   => flxhl_admin_url(),
			'action_label' => __( 'Settings', 'flx-headless' ),
		);
		return $checks;
	}
);
