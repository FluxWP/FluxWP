<?php
/**
 * Setup wizard: the steps the wizard runs (site name, tagline, logo, pages, theme, colour, preset,
 * consent, finish / skip), the "updates waiting before we start" check and the logo upload. The wizard page
 * (admin-ajax handlers) and GraphQL call this. Permission checks stay with the caller (FLXK_Onboarding::cap()).
 *
 * Errors are WP_Error with `graphql_code` INVALID_INPUT and the input `field`.
 *
 * @package FLX_Setup
 */

defined( 'ABSPATH' ) || exit;

final class FLXK_Wizard_Service {

	/** Steps the wizard can run. */
	public const STEP_KINDS = array( 'name', 'tagline', 'logo', 'pages', 'theme', 'style', 'preset', 'consent', 'done', 'skip' );

	/** Upload context of the logo (see flx_gql_upload_contexts). */
	public const LOGO_CONTEXT = 'flx-setup/logo';

	/**
	 * Runs one wizard step.
	 *
	 * @param string $kind  One of STEP_KINDS.
	 * @param string $value Value of the step (name, attachment id, page keys, style key, preset id, 1/0 …).
	 * @return array{ok:bool,lines:list<array{ok:bool,text:string}>,pages:int|null}|WP_Error
	 */
	public static function step( string $kind, string $value ) {
		if ( ! in_array( $kind, self::STEP_KINDS, true ) ) {
			return new WP_Error( 'flxk_step', __( 'Unknown action.', 'flx-setup' ), array( 'graphql_code' => 'INVALID_INPUT', 'field' => 'kind' ) );
		}
		@set_time_limit( 300 ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged
		ob_start(); // A preset or a theme switch may print; it must not break the response.
		$res = FLXK_Onboarding::run( $kind, $value );
		ob_end_clean();
		$lines = array();
		foreach ( FLXK_Val::rows( $res['lines'] ?? array() ) as $l ) {
			$lines[] = array(
				'ok'   => FLXK_Val::bool( $l['ok'] ?? false ),
				'text' => FLXK_Val::str( $l['text'] ?? '' ),
			);
		}
		return array(
			'ok'    => ! empty( $res['ok'] ),
			'lines' => $lines,
			'pages' => isset( $res['pages'] ) ? FLXK_Val::int( $res['pages'] ) : null,
		);
	}

	/**
	 * Before the wizard starts: asks the server and lists the installed packages that have an update.
	 * FluxWP Panel itself comes first with self = true (the wizard reopens after it updates).
	 *
	 * @return array{ok:bool,error:string,items:list<array{slug:string,name:string,from:string,to:string,icon:string,self:bool}>}
	 */
	public static function updates(): array {
		@set_time_limit( 120 ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged
		FLXU_Updater::instance()->check_now();
		$status = FLXU_Updater::status();
		$items  = array();
		if ( ! empty( $status['ok'] ) ) {
			foreach ( FLXU_Admin::rows( FLXU_Updater::instance()->catalog() ) as $r ) {
				$r    = FLXK_Val::arr( $r );
				$slug = FLXK_Val::str( $r['slug'] ?? '' );
				if ( 'update' !== FLXK_Val::str( $r['state'] ?? '' ) || ! current_user_can( 'theme' === FLXK_Val::str( $r['type'] ?? '' ) ? 'update_themes' : 'update_plugins' ) ) {
					continue;
				}
				$icon    = FLXU_Updater::icons( $slug );
				$items[] = array(
					'slug' => $slug,
					'name' => FLXK_Val::str( $r['name'] ?? '' ),
					'from' => FLXK_Val::str( $r['installed'] ?? '' ),
					'to'   => FLXK_Val::str( $r['latest'] ?? '' ),
					'icon' => FLXK_Val::str( $icon['svg'] ?? '' ),
					'self' => 'flx-setup' === $slug,
				);
			}
			usort(
				$items,
				static fn( array $a, array $b ): int => array( ! $a['self'], $a['name'] ) <=> array( ! $b['self'], $b['name'] )
			);
		}
		return array(
			'ok'    => ! empty( $status['ok'] ),
			'error' => FLXK_Val::str( $status['error'] ?? '' ),
			'items' => $items,
		);
	}

	/**
	 * Adds an uploaded image to the media library for the logo (the wizard then sets it with the "logo" step).
	 *
	 * @param string $path Temporary file (an upload the caller took with flx_gql_upload_take()).
	 * @param string $name File name.
	 * @param string $mime Detected type.
	 * @return array{id:int,url:string}|WP_Error
	 */
	public static function logo_from_file( string $path, string $name, string $mime ) {
		if ( ! in_array( $mime, array( 'image/png', 'image/jpeg', 'image/webp', 'image/gif' ), true ) ) {
			return new WP_Error( 'flxk_logo', __( 'Upload a PNG, JPG, WebP or GIF.', 'flx-setup' ), array( 'graphql_code' => 'INVALID_INPUT', 'field' => 'uploadId' ) );
		}
		if ( ! current_user_can( 'upload_files' ) ) {
			return new WP_Error( 'flxk_logo', __( 'You are not allowed to do this.', 'flx-setup' ), array( 'graphql_code' => 'FORBIDDEN' ) );
		}
		require_once ABSPATH . 'wp-admin/includes/file.php';
		require_once ABSPATH . 'wp-admin/includes/media.php';
		require_once ABSPATH . 'wp-admin/includes/image.php';
		$id = media_handle_sideload(
			array(
				'name'     => sanitize_file_name( $name ),
				'tmp_name' => $path,
			),
			0
		);
		if ( is_wp_error( $id ) ) {
			$id->add_data( array( 'graphql_code' => 'INVALID_INPUT', 'field' => 'uploadId' ) );
			return $id;
		}
		return array(
			'id'  => $id,
			'url' => FLXK_Val::str( wp_get_attachment_image_url( $id, 'medium' ) ),
		);
	}
}
