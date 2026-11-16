<?php
/**
 * Personal Flux Admin choices of the signed-in user: light/dark mode and the filters of the dashboard
 * modules (Tasks, Donations). The moon/sun button and the dashboard (admin-ajax), GraphQL and the
 * FluxWP app call this. Any signed-in user may change their own; the modules check their package.
 *
 * @package flx-admin
 */

defined( 'ABSPATH' ) || exit;

/**
 * @phpstan-type FlxaWidgetPrefs array{tasks: array{scope: string, list: int, chart: string}, donations: array{days: int, split: string}}
 */
final class FLXA_User_Service {

	/**
	 * The user's mode: auto, light or dark (the site default when they have not chosen).
	 */
	public static function mode(): string {
		return flxa_user_mode();
	}

	/**
	 * Saves the user's mode.
	 *
	 * @param string $mode auto | light | dark.
	 * @return string|WP_Error
	 */
	public static function set_mode( string $mode ) {
		if ( ! in_array( $mode, array( 'auto', 'light', 'dark' ), true ) ) {
			return FLXA_Util::error( 'flxa_mode', __( 'Choose auto, light or dark.', 'flx-admin' ), 'INVALID_INPUT', 'mode' );
		}
		update_user_meta( get_current_user_id(), 'flxa_mode', $mode );
		return $mode;
	}

	/**
	 * Which dashboard modules this user can have.
	 *
	 * @return array{tasks: bool, donations: bool}
	 */
	public static function widgets_available(): array {
		return array(
			'tasks'     => (bool) flxa_task_available(),
			'donations' => function_exists( 'flxb_table' ) && flxa_can_view_donations(),
		);
	}

	/**
	 * The user's module filters.
	 *
	 * @return FlxaWidgetPrefs
	 */
	public static function widget_prefs(): array {
		$t = flxa_widget_prefs( 'tasks' );
		$d = flxa_widget_prefs( 'donations' );
		return array(
			'tasks'     => array(
				'scope' => FLXA_Util::str( $t['scope'] ?? 'mine', 'mine' ),
				'list'  => FLXA_Util::int( $t['list'] ?? 0 ),
				'chart' => FLXA_Util::str( $t['chart'] ?? 'status', 'status' ),
			),
			'donations' => array(
				'days'  => FLXA_Util::int( $d['days'] ?? 14, 14 ),
				'split' => FLXA_Util::str( $d['split'] ?? 'type', 'type' ),
			),
		);
	}

	/**
	 * Saves a module's filters and returns the module as the dashboard shows it (HTML).
	 *
	 * @param string              $widget tasks | donations.
	 * @param array<string,mixed> $prefs  Filters to change.
	 * @return array{widget: string, html: string, prefs: FlxaWidgetPrefs}|WP_Error
	 */
	public static function set_widget( string $widget, array $prefs ) {
		$avail = self::widgets_available();
		if ( 'tasks' === $widget && $avail['tasks'] ) {
			$html = flxa_render_tasks( flxa_widget_prefs_save( 'tasks', $prefs ) );
		} elseif ( 'donations' === $widget && $avail['donations'] ) {
			$html = flxa_render_donations( flxa_widget_prefs_save( 'donations', $prefs ) );
		} else {
			return FLXA_Util::error( 'flxa_widget_forbidden', __( 'You do not have permission to do this.', 'flx-admin' ), 'FORBIDDEN' );
		}
		return array(
			'widget' => $widget,
			'html'   => $html,
			'prefs'  => self::widget_prefs(),
		);
	}
}
